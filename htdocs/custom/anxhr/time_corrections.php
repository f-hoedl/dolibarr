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
 * \file        htdocs/custom/anxhr/time_corrections.php
 * \ingroup     anxhr
 * \brief       Correction requests of clock entries: list, create, approve / refuse
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
require_once __DIR__.'/class/timecorrection.class.php';
require_once __DIR__.'/lib/anxhr_time.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('anxhr@anxhr', 'anxhr_time@anxhr', 'hrm'));

$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');
$comment = GETPOST('comment', 'alphanohtml');
$search_status = GETPOSTISSET('search_status') ? GETPOST('search_status', 'intcomma') : '0';
$limit = GETPOSTINT('limit') > 0 ? GETPOSTINT('limit') : $conf->liste_limit;
$page = max(0, GETPOSTINT('page'));
$offset = $limit * $page;

$now = dol_now();
$today = TimeEntry::timestampToDay($now);
$isadmin = $user->hasRight('anxhr', 'time', 'admin');
$isapprover = $user->hasRight('anxhr', 'time', 'approve');

// Day and employee of the create form
$day = GETPOST('day', 'alpha');
if (GETPOSTISSET('reday')) {
	$tsre = GETPOSTDATE('re', '00:00:00', 'tzserver');
	$day = $tsre ? TimeEntry::timestampToDay($tsre) : '';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $day) || $day > $today) {
	$day = $today;
}
$userid = GETPOSTINT('userid') > 0 ? GETPOSTINT('userid') : (int) $user->id;

// Security check
if (!isModEnabled('anxhr')) {
	accessforbidden('Module not enabled');
}
if (!$user->hasRight('anxhr', 'time', 'correct_own') && !$isapprover && !$isadmin) {
	accessforbidden();
}
$cancreatefor = ($userid == $user->id) ? $user->hasRight('anxhr', 'time', 'correct_own') : $isadmin;

$object = new TimeCorrection($db);
if ($id > 0 && $object->fetch($id) <= 0) {
	accessforbidden('Record not found');
}
if ($id > 0 && !anxhrTimeCanSeeUser($user, (int) $object->fk_user)) {
	accessforbidden();
}


/*
 * Actions
 */

if (GETPOST('cancel', 'alpha')) {
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

if ($action == 'add' && $cancreatefor && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$nextdayTs = TimeEntry::dayToTimestamp($day) + 26 * 3600;
	$nextday = TimeEntry::timestampToDay($nextdayTs);
	$rows = array();
	for ($i = 0; $i < 20; $i++) {
		$type = GETPOST('type_'.$i, 'aZ09');
		$time = GETPOST('time_'.$i, 'alpha');
		if ($type === '' || $time === '' || !in_array($type, TimeEntry::$types, true) || !preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $time)) {
			continue;
		}
		$rows[] = array('type' => $type, 'time' => (GETPOSTINT('nextday_'.$i) ? $nextday : $day).' '.$time.':00', 'homeoffice' => GETPOSTINT('homeoffice_'.$i) ? 1 : 0);
	}
	$reason = trim(GETPOST('reason', 'alphanohtml'));
	$error = 0;
	if (empty($rows)) {
		setEventMessages($langs->trans('AnxhrErrorNoEntries'), null, 'errors');
		$error++;
	}
	if ($reason === '') {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('AnxhrReason')), null, 'errors');
		$error++;
	}
	$tday = new TimeDay($db);
	$existing = $tday->fetchRowForUserDay($userid, $day);
	if (is_array($existing) && !empty($existing['locked'])) {
		setEventMessages($langs->trans('AnxhrErrorDayLocked'), null, 'errors');
		$error++;
	}
	if (!$error) {
		$object->fk_user = $userid;
		$object->day = TimeEntry::dayToTimestamp($day);
		$object->new_json = json_encode($rows);
		$object->reason = $reason;
		$res = $object->create($user);
		if ($res > 0) {
			setEventMessages($langs->trans('AnxhrCorrectionCreated'), null, 'mesgs');
			// HR admin correcting another employee: apply immediately (four eyes principle not applicable to HR).
			if ($isadmin && $userid != $user->id) {
				$object->fetch($res);
				if ($object->approve($user, $langs->transnoentitiesnoconv('AnxhrAutoApprovedByHr')) > 0) {
					setEventMessages($langs->trans('AnxhrCorrectionApplied'), null, 'mesgs');
				}
			}
			header('Location: '.$_SERVER['PHP_SELF']);
			exit;
		}
		setEventMessages($langs->trans($object->error), $object->errors, 'errors');
	}
	$action = 'create';
}

if (in_array($action, array('approve', 'refuse', 'cancel'), true) && $id > 0 && $_SERVER['REQUEST_METHOD'] === 'POST') {
	if ($action == 'approve') {
		$res = $object->approve($user, $comment);
	} elseif ($action == 'refuse') {
		$res = $object->refuse($user, $comment);
	} else {
		$res = $object->cancel($user);
	}
	if ($res > 0) {
		setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
	} else {
		setEventMessages($langs->trans($object->error), $object->errors, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF'].'?search_status='.urlencode((string) $search_status));
	exit;
}


/*
 * View
 */

$form = new Form($db);
$title = $langs->trans('AnxhrCorrections');
llxHeader('', $title, '', '', 0, 0, '', array('/anxhr/css/anxhr_time.css.php'), '', 'mod-anxhr page-time-corrections');

if ($action == 'create' && $cancreatefor) {
	print load_fiche_titre($langs->trans('AnxhrNewCorrection'), '', 'fa-user-edit');
	print dol_get_fiche_head(anxhrTimePrepareHead(), 'corrections', '', -1, '');

	// Step 1: choose the day (GET reload)
	print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="action" value="create">';
	print '<input type="hidden" name="userid" value="'.$userid.'">';
	print '<table class="border centpercent tableforfield">';
	if ($userid != $user->id) {
		$emp = new User($db);
		$emp->fetch($userid);
		print '<tr><td class="titlefield">'.$langs->trans('Employee').'</td><td>'.$emp->getNomUrl(-1).'</td></tr>';
	}
	print '<tr><td class="titlefield fieldrequired">'.$langs->trans('Date').'</td><td>';
	print $form->selectDate(TimeEntry::dayToTimestamp($day), 're', 0, 0, 0, '', 1, 0, 0, '', '', '', '', 1, '', '', 'tzserver');
	print ' <input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('AnxhrLoadDay')).'">';
	print '</td></tr>';
	print '</table>';
	print '</form>';

	// Step 2: entries of the day
	$entryObj = new TimeEntry($db);
	$current = $entryObj->fetchForUserDay($userid, $day);
	if (!is_array($current)) {
		$current = array();
	}
	print '<br>';
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';
	print '<input type="hidden" name="day" value="'.dol_escape_htmltag($day).'">';
	print '<input type="hidden" name="userid" value="'.$userid.'">';

	print '<div class="opacitymedium">'.$langs->trans('AnxhrCurrentEntries').': '.(empty($current) ? $langs->trans('None') : dol_escape_htmltag(TimeEntry::formatCompact($current))).'</div><br>';

	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('AnxhrEntryType').'</th><th>'.$langs->trans('AnxhrEntryTime').'</th><th class="center">'.$langs->trans('AnxhrNextDay').'</th><th class="center">'.$langs->trans('AnxhrHomeoffice').'</th></tr>';
	$typeoptions = array();
	foreach (TimeEntry::$types as $t) {
		$typeoptions[$t] = anxhrTimeEntryTypeLabel($t, $langs);
	}
	$nbrows = max(4, count($current) + 2);
	for ($i = 0; $i < $nbrows; $i++) {
		$c = isset($current[$i]) ? $current[$i] : null;
		$selType = $c ? $c['type'] : '';
		$selTime = $c ? dol_print_date($c['ts'], '%H:%M', 'tzserver') : '';
		$isNext = ($c && TimeEntry::timestampToDay($c['ts']) !== $day);
		// Each input gets an accessible name (row number + column), as the visible label is only the column header
		$rowlabel = $langs->transnoentitiesnoconv('AnxhrEntryTypeNth', $i + 1);
		print '<tr class="oddeven anxhr-correction-row">';
		print '<td>'.$form->selectarray('type_'.$i, $typeoptions, $selType, 1, 0, 0, 'aria-label="'.dolPrintHTMLForAttribute($rowlabel.' - '.$langs->transnoentitiesnoconv('AnxhrEntryType')).'"', 0, 0, 0, '', 'minwidth150').'</td>';
		print '<td><input type="time" name="time_'.$i.'" value="'.dol_escape_htmltag($selTime).'" step="60" aria-label="'.dolPrintHTMLForAttribute($rowlabel.' - '.$langs->transnoentitiesnoconv('AnxhrEntryTime')).'"></td>';
		print '<td class="center"><input type="checkbox" name="nextday_'.$i.'" value="1"'.($isNext ? ' checked' : '').' aria-label="'.dolPrintHTMLForAttribute($rowlabel.' - '.$langs->transnoentitiesnoconv('AnxhrNextDay')).'"></td>';
		print '<td class="center"><input type="checkbox" name="homeoffice_'.$i.'" value="1"'.(($c && $c['homeoffice']) ? ' checked' : '').' aria-label="'.dolPrintHTMLForAttribute($rowlabel.' - '.$langs->transnoentitiesnoconv('AnxhrHomeoffice')).'"></td>';
		print '</tr>';
	}
	print '</table>';
	print '</div>';

	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefield fieldrequired"><label for="reason">'.$langs->trans('AnxhrReason').'</label></td><td><textarea id="reason" name="reason" class="quatrevingtpercent" rows="3" aria-required="true">'.dol_escape_htmltag(GETPOST('reason', 'alphanohtml')).'</textarea></td></tr>';
	print '</table>';

	print dol_get_fiche_end();
	print $form->buttonsSaveCancel('AnxhrSendCorrection', 'Cancel');
	print '</form>';
} else {
	// List
	$visible = anxhrTimeVisibleUserIds($user);
	$sql = "SELECT c.rowid, c.fk_user, c.day, c.old_json, c.new_json, c.reason, c.status, c.fk_user_approve, c.date_approve, c.approve_comment, c.date_creation,";
	$sql .= " u.login, u.firstname, u.lastname";
	$sql .= " FROM ".$db->prefix()."anxhr_time_correction as c";
	$sql .= " INNER JOIN ".$db->prefix()."user as u ON u.rowid = c.fk_user";
	$sql .= " WHERE c.entity IN (".getEntity('anxhr_time_correction').")";
	if ($visible !== null) {
		$sql .= " AND c.fk_user IN (".$db->sanitize(implode(',', array_map('intval', $visible))).")";
	}
	if ($search_status !== '' && $search_status !== '-1') {
		$sql .= " AND c.status IN (".$db->sanitize($search_status).")";
	}
	$sql .= $db->order('c.date_creation', 'DESC');
	$sql .= $db->plimit($limit + 1, $offset);
	$resql = $db->query($sql);
	if (!$resql) {
		dol_print_error($db);
	}
	$num = $resql ? $db->num_rows($resql) : 0;

	$newbutton = '';
	if ($user->hasRight('anxhr', 'time', 'correct_own')) {
		$newbutton = dolGetButtonTitle($langs->trans('AnxhrNewCorrection'), '', 'fa fa-plus-circle', $_SERVER['PHP_SELF'].'?action=create');
	}
	$param = '&search_status='.urlencode((string) $search_status);
	print_barre_liste($title, $page, $_SERVER['PHP_SELF'], $param, '', '', '', $num, $num, 'fa-user-edit', 0, $newbutton, '', $limit);
	print dol_get_fiche_head(anxhrTimePrepareHead(), 'corrections', '', -1, '');

	// Filter
	$statusoptions = array('-1' => $langs->trans('All'), '0' => $langs->trans('AnxhrCorrectionRequested'), '1' => $langs->trans('AnxhrCorrectionApproved'), '2' => $langs->trans('AnxhrCorrectionRefused'), '9' => $langs->trans('Canceled'));
	print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" class="marginbottomonly">';
	print $langs->trans('Status').': '.$form->selectarray('search_status', $statusoptions, $search_status, 0, 0, 0, '', 0, 0, 0, '', 'minwidth150');
	print ' <input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Search')).'">';
	print '</form>';

	print '<div class="div-table-responsive">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<th>'.$langs->trans('Employee').'</th><th>'.$langs->trans('Date').'</th><th class="hideonsmartphone">'.$langs->trans('AnxhrOldEntries').'</th><th>'.$langs->trans('AnxhrNewEntries').'</th><th>'.$langs->trans('AnxhrReason').'</th><th class="center">'.$langs->trans('Status').'</th><th></th>';
	print '</tr>';
	if ($num == 0) {
		print '<tr class="oddeven"><td colspan="7"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
	}
	$i = 0;
	$tmp = new TimeCorrection($db);
	while ($resql && $i < min($num, $limit)) {
		$obj = $db->fetch_object($resql);
		$i++;
		$tmp->id = (int) $obj->rowid;
		$tmp->fk_user = (int) $obj->fk_user;
		$tmp->old_json = $obj->old_json;
		$tmp->new_json = $obj->new_json;
		$tmp->status = (int) $obj->status;
		$newlist = array();
		foreach ($tmp->getNewEntries() as $n) {
			$newlist[] = array('type' => $n['type'], 'ts' => TimeCorrection::stringToTimestamp($n['time']));
		}
		print '<tr class="oddeven">';
		print '<td class="tdoverflowmax150">'.dol_escape_htmltag(dolGetFirstLastname($obj->firstname, $obj->lastname) ?: $obj->login).'</td>';
		print '<td class="nowraponall">'.dol_print_date($db->jdate($obj->day), 'day', 'tzserver').'</td>';
		print '<td class="hideonsmartphone anxhr-entries-compact">'.dol_escape_htmltag(TimeEntry::formatCompact($tmp->getOldEntries())).'</td>';
		print '<td class="anxhr-entries-compact">'.dol_escape_htmltag(TimeEntry::formatCompact($newlist)).'</td>';
		print '<td class="tdoverflowmax200" title="'.dol_escape_htmltag($obj->reason).'">'.dol_escape_htmltag($obj->reason);
		if ($obj->approve_comment) {
			print '<br><span class="opacitymedium small">'.dol_escape_htmltag($obj->approve_comment).'</span>';
		}
		print '</td>';
		print '<td class="center">'.$tmp->getLibStatut(5).'</td>';
		print '<td class="right nowraponall">';
		if ((int) $obj->status === TimeCorrection::STATUS_REQUESTED) {
			if (TimeEntry::userCanApproveFor($user, (int) $obj->fk_user)) {
				print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" class="inline-block">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="id" value="'.((int) $obj->rowid).'">';
				print '<input type="hidden" name="search_status" value="'.dol_escape_htmltag((string) $search_status).'">';
				print '<input type="text" name="comment" class="maxwidth150" placeholder="'.dol_escape_htmltag($langs->trans('AnxhrApproveComment')).'"> ';
				print '<button type="submit" name="action" value="approve" class="button smallpaddingimp">'.$langs->trans('Approve').'</button> ';
				print '<button type="submit" name="action" value="refuse" class="button button-cancel smallpaddingimp">'.$langs->trans('AnxhrRefuse').'</button>';
				print '</form>';
			} elseif ((int) $obj->fk_user === (int) $user->id) {
				print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" class="inline-block">';
				print '<input type="hidden" name="token" value="'.newToken().'">';
				print '<input type="hidden" name="id" value="'.((int) $obj->rowid).'">';
				print '<button type="submit" name="action" value="cancel" class="button button-cancel smallpaddingimp">'.$langs->trans('Cancel').'</button>';
				print '</form>';
			}
		}
		print '</td>';
		print '</tr>';
	}
	print '</table>';
	print '</div>';
	print dol_get_fiche_end();
}

llxFooter();
$db->close();
