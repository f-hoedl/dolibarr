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
 * \file        htdocs/custom/anxhr/time_periods.php
 * \ingroup     anxhr
 * \brief       Monthly time periods: confirm (employee), approve (supervisor), reopen (HR), CSV export (payroll)
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

$langs->loadLangs(array('anxhr@anxhr', 'anxhr_time@anxhr', 'hrm'));

$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');
$reason = GETPOST('reason', 'alphanohtml');
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
$isadmin = $user->hasRight('anxhr', 'time', 'admin');
$canexport = $user->hasRight('anxhr', 'time', 'export');
if (!$user->hasRight('anxhr', 'time', 'own') && !$user->hasRight('anxhr', 'time', 'approve') && !$isadmin && !$canexport) {
	accessforbidden();
}
// Users visible in the list (null = all). Export right gives access to all periods.
$visible = ($canexport ? null : anxhrTimeVisibleUserIds($user));

$object = new TimePeriod($db);
if ($id > 0) {
	if ($object->fetch($id) <= 0) {
		accessforbidden('Record not found');
	}
	if ($visible !== null && !in_array((int) $object->fk_user, $visible, true)) {
		accessforbidden();
	}
}
$backurl = $_SERVER['PHP_SELF'].'?year='.$year.'&month='.$month;


/*
 * Actions
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$res = null;
	if ($action == 'confirmown' && $user->hasRight('anxhr', 'time', 'own')) {
		$res = $object->fetchOrCreate($user->id, $year, $month, $user);
		if ($res > 0) {
			$res = $object->confirmByEmployee($user);
		}
	} elseif ($action == 'confirm' && $id > 0) {
		$res = $object->confirmByEmployee($user);
	} elseif ($action == 'approve' && $id > 0) {
		$res = $object->approve($user);
	} elseif ($action == 'reopen' && $id > 0) {
		$res = $object->reopen($user, $reason);
	} elseif ($action == 'recompute' && $id > 0 && $isadmin) {
		$res = $object->recomputeBalances($user);
		if ($res === 0) {
			$object->error = 'AnxhrErrorPeriodLocked';
			$res = -1;
		}
	} elseif ($action == 'createall' && $isadmin) {
		$nb = TimePeriod::createForAllUsers($db, $user, $year, $month);
		if ($nb >= 0) {
			setEventMessages($langs->trans('AnxhrPeriodsCreated', $nb), null, 'mesgs');
		} else {
			setEventMessages($langs->trans('Error'), null, 'errors');
		}
	} elseif ($action == 'massgenerate' && $isadmin) {
		anxhrTimeMassGenerateSheets($db, $langs, $user, GETPOST('toselect', 'array:int'));
	} elseif ($action == 'exportcsv' && $canexport) {
		anxhrTimeExportCsv($db, $langs, $user, $year, $month);
		exit;
	}
	if ($res !== null) {
		if ($res > 0) {
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		} else {
			setEventMessages($langs->trans($object->error ? $object->error : 'Error'), $object->errors, 'errors');
		}
	}
	header('Location: '.$backurl);
	exit;
}


/*
 * View
 */

$form = new Form($db);
$title = $langs->trans('AnxhrPeriods');

$sql = "SELECT p.rowid, p.fk_user, p.year, p.month, p.target_min, p.worked_min, p.overtime50_min, p.overtime100_min, p.extra_parttime_min,";
$sql .= " p.vacation_days, p.sick_days, p.balance_start_min, p.balance_end_min, p.status, p.fk_user_approve, p.date_approve,";
$sql .= " u.login, u.firstname, u.lastname";
$sql .= " FROM ".$db->prefix()."anxhr_time_period as p";
$sql .= " INNER JOIN ".$db->prefix()."user as u ON u.rowid = p.fk_user";
$sql .= " WHERE p.entity IN (".getEntity('anxhr_time_period').")";
$sql .= " AND p.year = ".((int) $year)." AND p.month = ".((int) $month);
if ($visible !== null) {
	$sql .= " AND p.fk_user IN (".$db->sanitize(implode(',', array_map('intval', $visible))).")";
}
$sql .= $db->order('u.lastname,u.firstname', 'ASC,ASC');
$sql .= $db->plimit(2000);
$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
}
$rows = array();
$ownFound = false;
while ($resql && ($obj = $db->fetch_object($resql))) {
	$rows[] = $obj;
	if ((int) $obj->fk_user === (int) $user->id) {
		$ownFound = true;
	}
}

llxHeader('', $title, '', '', 0, 0, '', array('/anxhr/css/anxhr_time.css.php'), '', 'mod-anxhr page-time-periods');

print load_fiche_titre($title, '', 'fa-calendar-check');
print dol_get_fiche_head(anxhrTimePrepareHead(), 'periods', '', -1, '');

$prev = dol_get_prev_month($month, $year);
$next = dol_get_next_month($month, $year);
print '<div class="anxhr-monthnav">';
print '<a class="butActionSmall" href="'.$_SERVER['PHP_SELF'].'?year='.$prev['year'].'&month='.$prev['month'].'" title="'.dol_escape_htmltag($langs->trans('Previous')).'">'.img_picto('', 'fa-chevron-left').'</a>';
print '<strong>'.$langs->trans('Month'.sprintf('%02d', $month)).' '.$year.'</strong>';
print '<a class="butActionSmall" href="'.$_SERVER['PHP_SELF'].'?year='.$next['year'].'&month='.$next['month'].'" title="'.dol_escape_htmltag($langs->trans('Next')).'">'.img_picto('', 'fa-chevron-right').'</a>';
print '</div>';

if ($isadmin) {
	// Mass action form (rows refer to it with the form attribute, their own action buttons are separate forms)
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" id="anxhrmassform">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="massgenerate">';
	print '<input type="hidden" name="year" value="'.((int) $year).'"><input type="hidden" name="month" value="'.((int) $month).'">';
	print '<div class="right marginbottomonly"><input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('AnxhrSheetMassGenerate')).'"></div>';
	print '</form>';
}
$nbcols = ($isadmin ? 11 : 10);
print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
if ($isadmin) {
	print '<th class="center width25">'.$form->showCheckAddButtons('checkforselect', 1).'</th>';
}
print '<th>'.$langs->trans('Employee').'</th>';
print '<th class="right">'.$langs->trans('AnxhrTarget').'</th>';
print '<th class="right">'.$langs->trans('AnxhrWorked').'</th>';
print '<th class="right hideonsmartphone">'.$langs->trans('AnxhrOvertime50Short').'</th>';
print '<th class="right hideonsmartphone">'.$langs->trans('AnxhrOvertime100Short').'</th>';
print '<th class="right hideonsmartphone">'.$langs->trans('AnxhrExtraParttime').'</th>';
print '<th class="right hideonsmartphone">'.$langs->trans('AnxhrBalanceStart').'</th>';
print '<th class="right">'.$langs->trans('AnxhrBalanceEnd').'</th>';
print '<th class="center">'.$langs->trans('Status').'</th>';
print '<th></th>';
print '</tr>';

if (!$ownFound && $user->hasRight('anxhr', 'time', 'own')) {
	print '<tr class="oddeven">';
	if ($isadmin) {
		print '<td></td>';
	}
	print '<td>'.dol_escape_htmltag($user->getFullName($langs)).'</td>';
	// Cells follow the header columns (some are hidden on phones), the message sits in the status column
	print '<td></td><td></td><td class="hideonsmartphone"></td><td class="hideonsmartphone"></td><td class="hideonsmartphone"></td><td class="hideonsmartphone"></td><td></td>';
	print '<td class="center opacitymedium">'.$langs->trans('AnxhrPeriodNotCreatedYet').'</td><td class="right anxhr-period-actions">';
	print anxhrTimePeriodButton('confirmown', 0, $year, $month, $langs->trans('AnxhrConfirmMonth'));
	print '</td></tr>';
}
if (empty($rows) && ($ownFound || !$user->hasRight('anxhr', 'time', 'own'))) {
	print '<tr class="oddeven"><td colspan="'.$nbcols.'"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
$tmp = new TimePeriod($db);
foreach ($rows as $obj) {
	$tmp->status = (int) $obj->status;
	$status = (int) $obj->status;
	print '<tr class="oddeven">';
	if ($isadmin) {
		print '<td class="center"><input type="checkbox" class="checkforselect" name="toselect[]" value="'.((int) $obj->rowid).'" form="anxhrmassform" aria-label="'.dolPrintHTMLForAttribute(dolGetFirstLastname($obj->firstname, $obj->lastname) ?: $obj->login).'"></td>';
	}
	$link = dol_buildpath('/anxhr/time_day.php', 1).'?id='.((int) $obj->fk_user).'&year='.$year.'&month='.$month;
	print '<td class="tdoverflowmax200"><a href="'.$link.'">'.dol_escape_htmltag(dolGetFirstLastname($obj->firstname, $obj->lastname) ?: $obj->login).'</a></td>';
	print '<td class="right">'.anxhrTimeFormatMinutes($obj->target_min).'</td>';
	print '<td class="right">'.anxhrTimeFormatMinutes($obj->worked_min).'</td>';
	print '<td class="right hideonsmartphone">'.anxhrTimeFormatMinutes($obj->overtime50_min).'</td>';
	print '<td class="right hideonsmartphone">'.anxhrTimeFormatMinutes($obj->overtime100_min).'</td>';
	print '<td class="right hideonsmartphone">'.anxhrTimeFormatMinutes($obj->extra_parttime_min).'</td>';
	print '<td class="right hideonsmartphone">'.anxhrTimeBalanceHtml($obj->balance_start_min).'</td>';
	print '<td class="right">'.anxhrTimeBalanceHtml($obj->balance_end_min).'</td>';
	print '<td class="center">'.$tmp->getLibStatut(5).'</td>';
	print '<td class="right anxhr-period-actions">';
	$isowner = ((int) $obj->fk_user === (int) $user->id);
	if (in_array($status, array(TimePeriod::STATUS_OPEN, TimePeriod::STATUS_REOPENED), true) && ($isowner || $isadmin)) {
		print anxhrTimePeriodButton('confirm', (int) $obj->rowid, $year, $month, $langs->trans('AnxhrConfirmMonth'));
	}
	if (TimeEntry::userCanApproveFor($user, (int) $obj->fk_user) && ($status === TimePeriod::STATUS_CONFIRMED || ($isadmin && in_array($status, array(TimePeriod::STATUS_OPEN, TimePeriod::STATUS_REOPENED), true)))) {
		print anxhrTimePeriodButton('approve', (int) $obj->rowid, $year, $month, $langs->trans('Approve'));
	}
	if ($isadmin && in_array($status, array(TimePeriod::STATUS_OPEN, TimePeriod::STATUS_REOPENED), true)) {
		print anxhrTimePeriodButton('recompute', (int) $obj->rowid, $year, $month, $langs->trans('AnxhrRecompute'));
	}
	if ($isadmin && in_array($status, array(TimePeriod::STATUS_CONFIRMED, TimePeriod::STATUS_APPROVED, TimePeriod::STATUS_EXPORTED), true)) {
		print anxhrTimePeriodButton('reopen', (int) $obj->rowid, $year, $month, $langs->trans('AnxhrReopen'), true);
	}
	// Monthly sheet (AZG paragraph 26), every viewer of the row may get it
	print '<a class="button smallpaddingimp" href="'.dol_buildpath('/anxhr/time_sheet.php', 1).'?id='.((int) $obj->rowid).'" title="'.dolPrintHTMLForAttribute($langs->trans('AnxhrMonthlySheetPdf')).'">'.img_picto('', 'fa-file-pdf', 'class="pictofixedwidth"').'<span class="hideonsmartphone">'.$langs->trans('AnxhrMonthlySheetPdf').'</span></a>';
	print '</td>';
	print '</tr>';
}
print '</table>';
print '</div>';

print dol_get_fiche_end();

print '<div class="tabsAction">';
if ($isadmin) {
	print anxhrTimePeriodButton('createall', 0, $year, $month, $langs->trans('AnxhrCreatePeriodsForMonth'), false, 'butAction');
}
if ($canexport) {
	print anxhrTimePeriodButton('exportcsv', 0, $year, $month, $langs->trans('AnxhrExportCsv'), false, 'butAction');
}
print '</div>';
if ($canexport) {
	$nbunapproved = 0;
	foreach ($rows as $obj) {
		if (!in_array((int) $obj->status, array(TimePeriod::STATUS_APPROVED, TimePeriod::STATUS_EXPORTED), true)) {
			$nbunapproved++;
		}
	}
	if ($nbunapproved > 0) {
		print '<div class="opacitymedium right">'.img_warning().' '.$langs->trans('AnxhrExportSkippedUnapproved', $nbunapproved).'</div>';
	}
}

llxFooter();
$db->close();


/**
 * Return a small POST form with one action button (CSRF token included).
 *
 * @param	string	$action		Action
 * @param	int		$id			Period id (0 = none)
 * @param	int		$year		Year
 * @param	int		$month		Month
 * @param	string	$label		Button label
 * @param	bool	$withreason	Add a reason input (reopen)
 * @param	string	$css		Button css
 * @return	string				HTML
 */
function anxhrTimePeriodButton($action, $id, $year, $month, $label, $withreason = false, $css = 'button smallpaddingimp')
{
	global $langs;

	$out = '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" class="inline-block">';
	$out .= '<input type="hidden" name="token" value="'.newToken().'">';
	$out .= '<input type="hidden" name="action" value="'.dol_escape_htmltag($action).'">';
	$out .= '<input type="hidden" name="year" value="'.((int) $year).'"><input type="hidden" name="month" value="'.((int) $month).'">';
	if ($id > 0) {
		$out .= '<input type="hidden" name="id" value="'.((int) $id).'">';
	}
	if ($withreason) {
		$out .= '<input type="text" name="reason" class="maxwidth150" required placeholder="'.dol_escape_htmltag($langs->trans('AnxhrReason')).'" aria-label="'.dol_escape_htmltag($langs->trans('AnxhrReason')).'"> ';
	}
	$out .= '<input type="submit" class="'.$css.'" value="'.dol_escape_htmltag($label).'">';
	$out .= '</form> ';
	return $out;
}

/**
 * Generate the monthly sheets of the selected periods (time admin) and set the result messages.
 *
 * @param	DoliDB		$db		Database handler
 * @param	Translate	$langs	Language
 * @param	User		$user	Time admin
 * @param	int[]		$ids	Period ids
 * @return	void
 */
function anxhrTimeMassGenerateSheets($db, $langs, $user, $ids)
{
	$nbok = 0;
	$nbko = 0;
	foreach (array_unique(array_map('intval', is_array($ids) ? $ids : array())) as $pid) {
		$period = new TimePeriod($db);
		if ($pid <= 0 || $period->fetch($pid) <= 0 || !anxhrTimeCanSeeUser($user, (int) $period->fk_user)) {
			$nbko++;
			continue;
		}
		if (!in_array((int) $period->status, array(TimePeriod::STATUS_APPROVED, TimePeriod::STATUS_EXPORTED), true)) {
			$period->recomputeBalances($user);
		}
		if ($period->generateDocument('monthly_timesheet', null) > 0) {
			$nbok++;
		} else {
			$nbko++;
		}
	}
	if ($nbok > 0 || $nbko == 0) {
		setEventMessages($langs->trans('AnxhrSheetMassGenerated', $nbok), null, 'mesgs');
	}
	if ($nbko > 0) {
		setEventMessages($langs->trans('AnxhrSheetMassErrors', $nbko), null, 'errors');
	}
}

/**
 * Output the generic payroll CSV of a month (UTF-8 with BOM, ';' separator) and mark approved periods as exported.
 * TODO Phase 5: BMD NTCS / Sage DPW formats with wage type mapping (payrollexport.class.php).
 *
 * @param	DoliDB		$db		Database handler
 * @param	Translate	$langs	Language
 * @param	User		$user	User exporting
 * @param	int			$year	Year
 * @param	int			$month	Month
 * @return	void
 */
function anxhrTimeExportCsv($db, $langs, $user, $year, $month)
{
	// Only approved (2) or already exported (3) periods are payroll relevant. Unapproved periods are skipped
	// and counted, the user gets a notice on the next page (the CSV download itself cannot show it).
	$exportable = array(TimePeriod::STATUS_APPROVED, TimePeriod::STATUS_EXPORTED);
	$sql = "SELECT COUNT(p.rowid) as nb FROM ".$db->prefix()."anxhr_time_period as p";
	$sql .= " WHERE p.entity IN (".getEntity('anxhr_time_period').")";
	$sql .= " AND p.year = ".((int) $year)." AND p.month = ".((int) $month);
	$sql .= " AND p.status NOT IN (".$db->sanitize(implode(',', $exportable)).")";
	$resql = $db->query($sql);
	$nbskipped = 0;
	if ($resql) {
		$objcount = $db->fetch_object($resql);
		$nbskipped = $objcount ? (int) $objcount->nb : 0;
		$db->free($resql);
	}
	if ($nbskipped > 0) {
		setEventMessages($langs->trans('AnxhrExportSkippedUnapproved', $nbskipped), null, 'warnings');
	}

	$sql = "SELECT p.rowid, p.fk_user, p.year, p.month, p.target_min, p.worked_min, p.balance_start_min, p.balance_end_min, p.overtime50_min, p.overtime100_min,";
	$sql .= " p.extra_parttime_min, p.night_min, p.vacation_days, p.sick_days, p.status, u.login, u.ref_employee, u.firstname, u.lastname";
	$sql .= " FROM ".$db->prefix()."anxhr_time_period as p";
	$sql .= " INNER JOIN ".$db->prefix()."user as u ON u.rowid = p.fk_user";
	$sql .= " WHERE p.entity IN (".getEntity('anxhr_time_period').")";
	$sql .= " AND p.year = ".((int) $year)." AND p.month = ".((int) $month);
	$sql .= " AND p.status IN (".$db->sanitize(implode(',', $exportable)).")";
	$sql .= $db->order('u.lastname,u.firstname', 'ASC,ASC');
	$sql .= $db->plimit(5000);
	$resql = $db->query($sql);
	if (!$resql) {
		dol_print_error($db);
		return;
	}

	$hours = function ($min) {
		return number_format(((int) $min) / 60, 2, ',', '');
	};
	$text = function ($s) {
		$s = str_replace(array("\r", "\n", ';', '"'), ' ', (string) $s);
		// Protect against CSV formula injection
		return preg_match('/^[=+\-@]/', $s) ? "'".$s : $s;
	};

	$filename = 'anxhr_time_'.sprintf('%04d-%02d', $year, $month).'.csv';
	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	header('Cache-Control: private, must-revalidate');

	$keys = array('AnxhrCsvPersonnelNo', 'AnxhrCsvName', 'AnxhrCsvYear', 'AnxhrCsvMonth', 'AnxhrCsvTarget', 'AnxhrCsvWorked', 'AnxhrCsvDiff', 'AnxhrCsvOvertime50', 'AnxhrCsvOvertime100', 'AnxhrCsvExtraWork', 'AnxhrCsvNight', 'AnxhrCsvVacationDays', 'AnxhrCsvSickDays', 'AnxhrCsvBalanceEnd');
	$head = array();
	foreach ($keys as $k) {
		$head[] = $langs->transnoentitiesnoconv($k);
	}
	print "\xEF\xBB\xBF";
	print implode(';', $head)."\r\n";

	$toexport = array();
	while ($obj = $db->fetch_object($resql)) {
		$line = array(
			$text($obj->ref_employee !== null && $obj->ref_employee !== '' ? $obj->ref_employee : $obj->login),
			$text(dolGetFirstLastname($obj->firstname, $obj->lastname)),
			(int) $obj->year,
			(int) $obj->month,
			$hours($obj->target_min),
			$hours($obj->worked_min),
			$hours((int) $obj->balance_end_min - (int) $obj->balance_start_min),
			$hours($obj->overtime50_min),
			$hours($obj->overtime100_min),
			$hours($obj->extra_parttime_min),
			$hours($obj->night_min),
			number_format((float) $obj->vacation_days, 2, ',', ''),
			number_format((float) $obj->sick_days, 2, ',', ''),
			$hours($obj->balance_end_min),
		);
		print implode(';', $line)."\r\n";
		if ((int) $obj->status === TimePeriod::STATUS_APPROVED) {
			$toexport[] = (int) $obj->rowid;
		}
	}
	$db->free($resql);

	// Mark approved periods as exported in one batch (bookings stay locked).
	TimePeriod::setExportedBatch($db, $user, $toexport);
	$db->close();
}
