<?php
/* Copyright (C) 2026  ANX HR contributors
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file        htdocs/custom/anxhr/time_clock.php
 * \ingroup     anxhr
 * \brief       "My time": clock in / break / clock out, today's entries, running counters, last 7 days
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
}
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once __DIR__.'/class/timeentry.class.php';
require_once __DIR__.'/class/timeday.class.php';
require_once __DIR__.'/class/timeperiod.class.php';
require_once __DIR__.'/lib/anxhr_time.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('anxhr@anxhr', 'anxhr_time@anxhr'));

$action = GETPOST('action', 'aZ09');
$type = GETPOST('type', 'aZ09');
$homeoffice = GETPOSTINT('homeoffice');

// Security check
if (!isModEnabled('anxhr')) {
	accessforbidden('Module not enabled');
}
if (!$user->hasRight('anxhr', 'time', 'own')) {
	accessforbidden();
}

$now = dol_now();
$today = TimeEntry::timestampToDay($now);
$yesterday = TimeEntry::timestampToDay(TimeEntry::dayToTimestamp($today) - 12 * 3600);


/*
 * Actions
 */

if ($action == 'clock' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$state = TimeEntry::getLastStateForUser($db, $user->id);
	$allowed = TimeEntry::getNextActions($state);
	$last = TimeEntry::getLastEntryForUser($db, $user->id);
	if (!in_array($type, $allowed, true)) {
		setEventMessages($langs->trans('AnxhrErrorActionNotAllowed'), null, 'errors');
	} elseif (!empty($last) && $last['type'] === $type && ($now - $last['ts']) < 60) {
		// Double click protection: same action within one minute is ignored.
		setEventMessages($langs->trans('AnxhrClockAlreadyRecorded'), null, 'warnings');
	} else {
		$entry = new TimeEntry($db);
		$entry->fk_user = $user->id;
		$entry->entry_type = $type;
		$entry->entry_datetime = $now;
		$entry->homeoffice = $homeoffice ? 1 : 0;
		$entry->source = 'web';
		$entry->ip = anxhrTimeClientIp();
		$entry->status = TimeEntry::STATUS_ACTIVE;
		if ($entry->create($user) > 0) {
			$tday = new TimeDay($db);
			// A shift belongs to the day of its clock in: recompute yesterday too (night shifts).
			$tday->recomputeForUserDay($user, $user->id, $yesterday);
			$tday->recomputeForUserDay($user, $user->id, $today);
			setEventMessages($langs->trans('AnxhrClockRecorded', anxhrTimeEntryTypeLabel($type, $langs), dol_print_date($now, 'hour', 'tzserver')), null, 'mesgs');
		} else {
			setEventMessages($entry->error, $entry->errors, 'errors');
		}
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}


/*
 * View
 */

$state = TimeEntry::getLastStateForUser($db, $user->id);
$allowed = TimeEntry::getNextActions($state);
$lastEntry = TimeEntry::getLastEntryForUser($db, $user->id);

$entryObj = new TimeEntry($db);
$todayEntries = $entryObj->fetchForUserDay($user->id, $today);
if (!is_array($todayEntries)) {
	$todayEntries = array();
}
// When the running shift started yesterday (night shift), show its entries.
$shiftDay = $today;
if ($state !== 'out' && empty($todayEntries)) {
	$shiftDay = $yesterday;
	$todayEntries = $entryObj->fetchForUserDay($user->id, $yesterday);
	if (!is_array($todayEntries)) {
		$todayEntries = array();
	}
}

$tday = new TimeDay($db);
$live = $tday->computeLive($user->id, $shiftDay, $now);
if (!is_array($live)) {
	$live = array('worked_min' => 0, 'break_min' => 0, 'target_min' => 0, 'diff_min' => 0, 'violations' => array());
}

// Month balance: previous period end + sum of diffs of this month (today replaced by the live value).
$year = (int) substr($today, 0, 4);
$month = (int) substr($today, 5, 2);
$monthDays = $tday->fetchMonth($user->id, $year, $month);
$period = new TimePeriod($db);
$period->fk_user = $user->id;
$period->year = $year;
$period->month = $month;
$balance = $period->getPreviousBalance();
if (is_array($monthDays)) {
	foreach ($monthDays as $d => $row) {
		if ($d !== $shiftDay) {
			$balance += (int) $row['diff_min'];
		}
	}
}
if (substr($shiftDay, 0, 7) === substr($today, 0, 7)) {
	$balance += (int) $live['diff_min'];
}

$from7 = TimeEntry::timestampToDay(TimeEntry::dayToTimestamp($today) - 6 * 86400 - 3600);
$last7 = $tday->fetchDays($user->id, $from7, $today);
if (!is_array($last7)) {
	$last7 = array();
}

$title = $langs->trans('AnxhrMyTime');
llxHeader('', $title, '', '', 0, 0, array('/anxhr/js/anxhr_time.js'), array('/anxhr/css/anxhr_time.css.php'), '', 'mod-anxhr page-time-clock');

print load_fiche_titre($title, '', 'fa-user-clock');
print dol_get_fiche_head(anxhrTimePrepareHead(), 'clock', '', -1, '');

// Warning when yesterday has an open entry (forgotten clock out).
$ydays = isset($last7[$yesterday]) ? $last7[$yesterday] : null;
if ($ydays && !empty($ydays['open_entry']) && $shiftDay !== $yesterday) {
	$link = '';
	if ($user->hasRight('anxhr', 'time', 'correct_own')) {
		$link = ' <a href="'.dol_buildpath('/anxhr/time_corrections.php', 1).'?action=create&day='.urlencode($yesterday).'">'.$langs->trans('AnxhrRequestCorrection').'</a>';
	}
	print '<div class="warning">'.img_warning().' '.$langs->trans('AnxhrYesterdayOpenEntry', dol_print_date(TimeEntry::dayToTimestamp($yesterday), 'day', 'tzserver')).$link.'</div>';
}

print '<div class="anxhr-clock-panel">';

// State and counters
print '<div class="anxhr-clock-state">';
print '<div>'.$langs->trans('AnxhrCurrentState').': '.anxhrTimeStateBadge($state, $langs).'</div>';
print '<div class="anxhr-now" id="anxhr-now" aria-live="polite">'.dol_print_date($now, '%H:%M', 'tzserver').'</div>';
print '<div class="opacitymedium">'.dol_print_date($now, 'daytext', 'tzserver').'</div>';
if ($lastEntry && $state !== 'out') {
	print '<div class="small">'.$langs->trans('AnxhrSince', anxhrTimeEntryTypeLabel($lastEntry['type'], $langs), dol_print_date($lastEntry['ts'], 'hour', 'tzserver')).'</div>';
}
print '<div class="anxhr-counters">';
$running = ($state === 'in') ? 1 : 0;
print '<div class="anxhr-counter"><span class="anxhr-counter-value" id="anxhr-worked" data-minutes="'.((int) $live['worked_min']).'" data-running="'.$running.'">'.anxhrTimeFormatMinutes($live['worked_min']).'</span><span class="anxhr-counter-label">'.$langs->trans('AnxhrWorkedToday').'</span></div>';
print '<div class="anxhr-counter"><span class="anxhr-counter-value">'.anxhrTimeFormatMinutes($live['target_min']).'</span><span class="anxhr-counter-label">'.$langs->trans('AnxhrTargetToday').'</span></div>';
print '<div class="anxhr-counter"><span class="anxhr-counter-value">'.anxhrTimeFormatMinutes($live['break_min']).'</span><span class="anxhr-counter-label">'.$langs->trans('AnxhrBreak').'</span></div>';
print '<div class="anxhr-counter"><span class="anxhr-counter-value">'.anxhrTimeBalanceHtml($balance).'</span><span class="anxhr-counter-label">'.$langs->trans('AnxhrBalanceMonth').'</span></div>';
print '</div>';
print '</div>';

// Action buttons (only valid next actions enabled)
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" class="anxhr-clock-actions">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="clock">';
$buttons = array(
	'in' => array('AnxhrClockIn', 'fa-sign-in-alt'),
	'break_start' => array('AnxhrStartBreak', 'fa-coffee'),
	'break_end' => array('AnxhrEndBreak', 'fa-play'),
	'out' => array('AnxhrClockOut', 'fa-sign-out-alt'),
);
foreach ($buttons as $btype => $def) {
	$enabled = in_array($btype, $allowed, true);
	print '<button type="submit" name="type" value="'.$btype.'" class="anxhr-bigbutton anxhr-btn-'.$btype.'"'.($enabled ? '' : ' disabled aria-disabled="true"').'>';
	print img_picto('', $def[1], 'class="pictofixedwidth"').$langs->trans($def[0]);
	print '</button>';
}
$hochecked = ($lastEntry && $state !== 'out' && !empty($lastEntry['homeoffice'])) ? ' checked' : '';
print '<label class="anxhr-homeoffice"><input type="checkbox" name="homeoffice" value="1"'.$hochecked.'>'.$langs->trans('AnxhrHomeoffice').'</label>';
print '</form>';

print '</div>';

// Today's entries
print load_fiche_titre($langs->trans('AnxhrEntriesOfDay', dol_print_date(TimeEntry::dayToTimestamp($shiftDay), 'day', 'tzserver')), '', '');
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('AnxhrEntryTime').'</th><th>'.$langs->trans('AnxhrEntryType').'</th><th class="center">'.$langs->trans('AnxhrHomeoffice').'</th><th class="hideonsmartphone">'.$langs->trans('AnxhrSource').'</th></tr>';
if (empty($todayEntries)) {
	print '<tr class="oddeven"><td colspan="4"><span class="opacitymedium">'.$langs->trans('AnxhrNoEntryToday').'</span></td></tr>';
}
foreach ($todayEntries as $e) {
	print '<tr class="oddeven">';
	print '<td class="nowraponall">'.dol_print_date($e['ts'], ($shiftDay === TimeEntry::timestampToDay($e['ts']) ? 'hour' : 'dayhour'), 'tzserver').'</td>';
	print '<td>'.anxhrTimeEntryTypeLabel($e['type'], $langs).'</td>';
	print '<td class="center">'.($e['homeoffice'] ? img_picto($langs->trans('AnxhrHomeoffice'), 'fa-home') : '').'</td>';
	print '<td class="hideonsmartphone">'.dol_escape_htmltag($langs->trans('AnxhrSource_'.$e['source'])).'</td>';
	print '</tr>';
}
print '</table>';
print '</div>';

if (!empty($live['violations'])) {
	print '<div class="opacitymedium margintoponly">'.$langs->trans('AnxhrViolations').': '.anxhrViolationBadges($live['violations'], $langs).'</div>';
}

print '<div class="tabsAction">';
if ($user->hasRight('anxhr', 'time', 'correct_own')) {
	print dolGetButtonAction('', $langs->trans('AnxhrRequestCorrection'), 'default', dol_buildpath('/anxhr/time_corrections.php', 1).'?action=create&day='.urlencode($shiftDay), '', true);
}
print dolGetButtonAction('', $langs->trans('AnxhrMonthView'), 'default', dol_buildpath('/anxhr/time_day.php', 1), '', true);
print '</div>';

// Last 7 days
print load_fiche_titre($langs->trans('AnxhrLast7Days'), '', '');
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Date').'</th><th class="right">'.$langs->trans('AnxhrWorked').'</th><th class="right">'.$langs->trans('AnxhrTarget').'</th><th class="right">'.$langs->trans('AnxhrDiff').'</th><th class="center">'.$langs->trans('AnxhrViolations').'</th></tr>';
$cursor = TimeEntry::dayToTimestamp($today);
for ($i = 0; $i < 7; $i++) {
	$d = TimeEntry::timestampToDay($cursor);
	$row = isset($last7[$d]) ? $last7[$d] : null;
	print '<tr class="oddeven'.($d === $today ? ' anxhr-today' : '').'">';
	print '<td class="nowraponall">'.dol_print_date($cursor, 'daytextshort', 'tzserver').'</td>';
	if ($row) {
		print '<td class="right">'.anxhrTimeFormatMinutes($row['worked_min']).'</td>';
		print '<td class="right">'.anxhrTimeFormatMinutes($row['target_min']).'</td>';
		print '<td class="right">'.anxhrTimeBalanceHtml($row['diff_min']).'</td>';
		print '<td class="center">'.anxhrViolationBadges($row['violations'], $langs).($row['absence_code'] ? ' <span class="badge badge-secondary">'.anxhrTimeAbsenceLabel($row['absence_code'], $langs).'</span>' : '').'</td>';
	} else {
		print '<td colspan="4" class="opacitymedium center">-</td>';
	}
	print '</tr>';
	$cursor = TimeEntry::dayToTimestamp(TimeEntry::timestampToDay($cursor - 12 * 3600));
}
print '</table>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
