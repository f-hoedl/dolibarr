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
 * \file        htdocs/custom/anxhr/time_day.php
 * \ingroup     anxhr
 * \brief       Month view of the computed working days of one employee
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
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
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
$id = GETPOSTINT('id') > 0 ? GETPOSTINT('id') : (int) $user->id;
$now = dol_now();
$today = TimeEntry::timestampToDay($now);
$year = GETPOSTINT('year') > 0 ? GETPOSTINT('year') : (int) substr($today, 0, 4);
$month = GETPOSTINT('month') > 0 ? GETPOSTINT('month') : (int) substr($today, 5, 2);
if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
	$year = (int) substr($today, 0, 4);
	$month = (int) substr($today, 5, 2);
}

// Security check
if (!isModEnabled('anxhr')) {
	accessforbidden('Module not enabled');
}
if (!anxhrTimeCanSeeUser($user, $id)) {
	accessforbidden();
}
$isadmin = $user->hasRight('anxhr', 'time', 'admin');

$period = new TimePeriod($db);
$period->year = $year;
$period->month = $month;
list($first, $last) = $period->getMonthBounds();


/*
 * Actions
 */

if ($action == 'recompute' && $isadmin && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$lastToCompute = ($last > $today) ? $today : $last;
	if ($first <= $lastToCompute) {
		$nb = TimeDay::recomputeRange($db, $user, $id, $first, $lastToCompute);
		if ($nb >= 0) {
			setEventMessages($langs->trans('AnxhrDaysRecomputed', $nb), null, 'mesgs');
		} else {
			setEventMessages($langs->trans('Error'), null, 'errors');
		}
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?id='.$id.'&year='.$year.'&month='.$month);
	exit;
}


/*
 * View
 */

$form = new Form($db);
$employee = new User($db);
$employee->fetch($id);

$tday = new TimeDay($db);
$days = $tday->fetchMonth($id, $year, $month);
if (!is_array($days)) {
	$days = array();
	setEventMessages($tday->error, null, 'errors');
}
$entryObj = new TimeEntry($db);
$entries = $entryObj->fetchRangeGroupedByDay($id, $first, $last);
if (!is_array($entries)) {
	$entries = array();
}
$period->fk_user = $id;
$balanceStart = $period->getPreviousBalance();

$title = $langs->trans('AnxhrMonthView');
llxHeader('', $title, '', '', 0, 0, '', array('/anxhr/css/anxhr_time.css.php'), '', 'mod-anxhr page-time-day');

print load_fiche_titre($title.' - '.dol_escape_htmltag($employee->getFullName($langs)), '', 'fa-calendar-alt');
print dol_get_fiche_head(anxhrTimePrepareHead($id), 'month', '', -1, '');

// Navigation: user selector and month navigation
$prev = dol_get_prev_month($month, $year);
$next = dol_get_next_month($month, $year);
$base = $_SERVER['PHP_SELF'].'?id='.$id;
print '<div class="anxhr-monthnav">';
print '<a class="butActionSmall" href="'.$base.'&year='.$prev['year'].'&month='.$prev['month'].'" title="'.dol_escape_htmltag($langs->trans('Previous')).'">'.img_picto('', 'fa-chevron-left').'</a>';
print '<strong class="fontsizeunset">'.$langs->trans('Month'.sprintf('%02d', $month)).' '.$year.'</strong>';
print '<a class="butActionSmall" href="'.$base.'&year='.$next['year'].'&month='.$next['month'].'" title="'.dol_escape_htmltag($langs->trans('Next')).'">'.img_picto('', 'fa-chevron-right').'</a>';
$visible = anxhrTimeVisibleUserIds($user);
if ($visible === null || count($visible) > 1) {
	print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" class="inline-block">';
	print '<input type="hidden" name="year" value="'.$year.'"><input type="hidden" name="month" value="'.$month.'">';
	print $form->select_dolusers($id, 'id', 0, null, 0, ($visible === null ? '' : $visible), '', '0', 0, 0, '', 0, '', 'minwidth200 maxwidth300');
	print ' <input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Show')).'">';
	print '</form>';
}
print '</div>';

print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Date').'</th>';
print '<th class="hideonsmartphone">'.$langs->trans('AnxhrEntries').'</th>';
print '<th class="right">'.$langs->trans('AnxhrWorked').'</th>';
print '<th class="right hideonsmartphone">'.$langs->trans('AnxhrBreak').'</th>';
print '<th class="right">'.$langs->trans('AnxhrTarget').'</th>';
print '<th class="right">'.$langs->trans('AnxhrDiff').'</th>';
print '<th class="right hideonsmartphone">'.$langs->trans('AnxhrOvertime50Short').'</th>';
print '<th class="right hideonsmartphone">'.$langs->trans('AnxhrOvertime100Short').'</th>';
print '<th class="center">'.$langs->trans('AnxhrAbsence').'</th>';
print '<th class="center">'.$langs->trans('AnxhrViolations').'</th>';
print '<th></th>';
print '</tr>';

$totals = array('worked_min' => 0, 'break_min' => 0, 'target_min' => 0, 'diff_min' => 0, 'overtime50_min' => 0, 'overtime100_min' => 0);
$cancorrect = (($id == $user->id && $user->hasRight('anxhr', 'time', 'correct_own')) || $isadmin);
$nbdays = (int) gmdate('t', gmmktime(0, 0, 0, $month, 1, $year));
for ($d = 1; $d <= $nbdays; $d++) {
	$day = sprintf('%04d-%02d-%02d', $year, $month, $d);
	$ts = TimeEntry::dayToTimestamp($day);
	$row = isset($days[$day]) ? $days[$day] : null;
	$isoday = (int) gmdate('N', gmmktime(0, 0, 0, $month, $d, $year));
	$css = 'oddeven';
	$css .= ($isoday >= 6 ? ' anxhr-weekend' : '');
	$css .= ($day === $today ? ' anxhr-today' : '');
	$css .= (($row && !empty($row['holiday_flag'])) ? ' anxhr-holiday' : '');
	print '<tr class="'.$css.'">';
	print '<td class="nowraponall anxhr-daycell">'.dol_print_date($ts, 'daytextshort', 'tzserver');
	if ($row && !empty($row['holiday_flag'])) {
		print ' '.img_picto($langs->trans('AnxhrPublicHoliday'), 'fa-star', 'class="opacitymedium"');
	}
	print '</td>';
	print '<td class="hideonsmartphone anxhr-entries-compact">'.(isset($entries[$day]) ? dol_escape_htmltag(TimeEntry::formatCompact($entries[$day])) : '').'</td>';
	if ($row) {
		foreach (array_keys($totals) as $k) {
			$totals[$k] += (int) $row[$k];
		}
		print '<td class="right">'.anxhrTimeFormatMinutes($row['worked_min']).'</td>';
		print '<td class="right hideonsmartphone">'.anxhrTimeFormatMinutes($row['break_min']).'</td>';
		print '<td class="right">'.anxhrTimeFormatMinutes($row['target_min']).'</td>';
		print '<td class="right">'.anxhrTimeBalanceHtml($row['diff_min']).'</td>';
		print '<td class="right hideonsmartphone">'.($row['overtime50_min'] ? anxhrTimeFormatMinutes($row['overtime50_min']) : '').'</td>';
		print '<td class="right hideonsmartphone">'.($row['overtime100_min'] ? anxhrTimeFormatMinutes($row['overtime100_min']) : '').'</td>';
		print '<td class="center">'.($row['absence_code'] ? '<span class="badge badge-secondary">'.anxhrTimeAbsenceLabel($row['absence_code'], $langs).'</span>' : '').'</td>';
		print '<td class="center">'.anxhrViolationBadges($row['violations'], $langs).(!empty($row['locked']) ? ' '.img_picto($langs->trans('AnxhrLocked'), 'fa-lock', 'class="opacitymedium"') : '').'</td>';
	} else {
		print '<td colspan="8" class="opacitymedium center">'.($day > $today ? '' : '-').'</td>';
	}
	print '<td class="right nowraponall">';
	if ($cancorrect && $day <= $today && (!$row || empty($row['locked']))) {
		print '<a class="editfielda" href="'.dol_buildpath('/anxhr/time_corrections.php', 1).'?action=create&day='.urlencode($day).($id != $user->id ? '&userid='.$id : '').'" title="'.dol_escape_htmltag($langs->trans('AnxhrCorrect')).'">'.img_picto($langs->trans('AnxhrCorrect'), 'edit').'</a>';
	}
	print '</td>';
	print '</tr>';
}

// Totals
print '<tr class="liste_total">';
print '<td>'.$langs->trans('Total').'</td><td class="hideonsmartphone"></td>';
print '<td class="right">'.anxhrTimeFormatMinutes($totals['worked_min']).'</td>';
print '<td class="right hideonsmartphone">'.anxhrTimeFormatMinutes($totals['break_min']).'</td>';
print '<td class="right">'.anxhrTimeFormatMinutes($totals['target_min']).'</td>';
print '<td class="right">'.anxhrTimeBalanceHtml($totals['diff_min']).'</td>';
print '<td class="right hideonsmartphone">'.anxhrTimeFormatMinutes($totals['overtime50_min']).'</td>';
print '<td class="right hideonsmartphone">'.anxhrTimeFormatMinutes($totals['overtime100_min']).'</td>';
print '<td colspan="3"></td>';
print '</tr>';
print '</table>';
print '</div>';

print '<div class="margintoponly">'.$langs->trans('AnxhrBalanceStart').': '.anxhrTimeBalanceHtml($balanceStart).' &nbsp; '.$langs->trans('AnxhrBalanceEnd').': '.anxhrTimeBalanceHtml($balanceStart + $totals['diff_min']).'</div>';

print dol_get_fiche_end();

// Buttons
print '<div class="tabsAction">';
if ($isadmin) {
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" class="inline-block">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="recompute">';
	print '<input type="hidden" name="id" value="'.$id.'"><input type="hidden" name="year" value="'.$year.'"><input type="hidden" name="month" value="'.$month.'">';
	print '<input type="submit" class="butAction" value="'.dol_escape_htmltag($langs->trans('AnxhrRecomputeMonth')).'">';
	print '</form>';
}
print dolGetButtonAction($langs->trans('AnxhrComingSoon'), $langs->trans('AnxhrMonthlySheetPdf'), 'default', '#', '', -1);
print '</div>';

llxFooter();
$db->close();
