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
 * \file        htdocs/custom/anxhr/time_sheet.php
 * \ingroup     anxhr
 * \brief       Monthly sheet (AZG paragraph 26 record) of one period: generate, download, open.
 *              Files are streamed by this page after the permission check (never via document.php).
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

require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
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

$langs->loadLangs(array('anxhr@anxhr', 'anxhr_time@anxhr', 'other'));

$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');
$attachment = GETPOSTINT('attachment');

// Security check: own data (time own), supervisor of the employee (time approve) or time admin
if (!isModEnabled('anxhr')) {
	accessforbidden('Module not enabled');
}
$object = new TimePeriod($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	http_response_code(404);
	accessforbidden('Record not found');
}
if (!anxhrTimeCanSeeUser($user, (int) $object->fk_user)) {
	// Real HTTP status (the file endpoint is also called by scripts and browsers' PDF viewers)
	http_response_code(403);
	accessforbidden();
}
$selfurl = $_SERVER['PHP_SELF'].'?id='.((int) $object->id);


/*
 * Actions
 */

if ($action == 'generate' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	// An approved or exported month is a legal record: only HR may regenerate it
	if (in_array((int) $object->status, array(TimePeriod::STATUS_APPROVED, TimePeriod::STATUS_EXPORTED), true) && !$user->hasRight('anxhr', 'time', 'admin')) {
		accessforbidden();
	}
	// Open months are recomputed first so the sheet shows current totals (approved months are locked)
	if (!in_array((int) $object->status, array(TimePeriod::STATUS_APPROVED, TimePeriod::STATUS_EXPORTED), true)) {
		$object->recomputeBalances($user);
	}
	if ($object->generateDocument('monthly_timesheet', null) > 0) {
		setEventMessages($langs->trans('AnxhrSheetGenerated'), null, 'mesgs');
	} else {
		setEventMessages($langs->trans('AnxhrSheetGenerateError', $object->error), $object->errors, 'errors');
	}
	header('Location: '.$selfurl);
	exit;
}

if ($action == 'download') {
	$file = $object->getSheetFile();
	if ($file === '' || !is_readable($file)) {
		setEventMessages($langs->trans('AnxhrSheetFileNotFound'), null, 'warnings');
		header('Location: '.$selfurl);
		exit;
	}
	dol_syslog('time_sheet.php download of monthly sheet period='.$object->id.' by user='.$user->id, LOG_INFO);
	$filename = basename($file);
	$db->close();
	header('Content-Type: application/pdf');
	header('Content-Disposition: '.($attachment ? 'attachment' : 'inline').'; filename="'.str_replace('"', '', $filename).'"');
	header('Content-Length: '.dol_filesize($file));
	header('Cache-Control: no-store, no-cache, must-revalidate, private');
	header('Pragma: no-cache');
	header('X-Content-Type-Options: nosniff');
	readfile($file);
	exit;
}


/*
 * View
 */

$employee = new User($db);
$employee->fetch((int) $object->fk_user);
$file = $object->getSheetFile();
$ym = sprintf('%04d-%02d', (int) $object->year, (int) $object->month);
$monthlabel = $langs->trans('Month'.sprintf('%02d', (int) $object->month)).' '.((int) $object->year);
$title = $langs->trans('AnxhrSheetPageTitle');

llxHeader('', $title.' '.$ym, '', '', 0, 0, '', array('/anxhr/css/anxhr_time.css.php'), '', 'mod-anxhr page-time-sheet');

print load_fiche_titre($title.' - '.dol_escape_htmltag($employee->getFullName($langs)).' - '.dol_escape_htmltag($monthlabel), '', 'fa-file-pdf');
print dol_get_fiche_head(anxhrTimePrepareHead((int) $object->fk_user), 'periods', '', -1, '');

print '<div class="fichecenter">';
print '<table class="border centpercent tableforfield">';
print '<tr><td class="titlefield">'.$langs->trans('Employee').'</td><td>'.$employee->getNomUrl(-1).'</td></tr>';
print '<tr><td>'.$langs->trans('AnxhrSheetPeriod').'</td><td>'.dol_escape_htmltag($monthlabel).'</td></tr>';
print '<tr><td>'.$langs->trans('Status').'</td><td>'.$object->getLibStatut(5).'</td></tr>';
print '<tr><td>'.$langs->trans('AnxhrWorked').' / '.$langs->trans('AnxhrTarget').'</td><td>'.anxhrTimeFormatMinutes($object->worked_min).' / '.anxhrTimeFormatMinutes($object->target_min).'</td></tr>';
print '<tr><td>'.$langs->trans('AnxhrBalanceEnd').'</td><td>'.anxhrTimeBalanceHtml($object->balance_end_min).'</td></tr>';
if ((int) $object->fk_user_approve > 0) {
	$approver = new User($db);
	$approver->fetch((int) $object->fk_user_approve);
	print '<tr><td>'.$langs->trans('AnxhrSheetApproval').'</td><td>'.$approver->getNomUrl(-1).' '.dol_print_date($object->date_approve, 'dayhour', 'tzuserrel').'</td></tr>';
}
print '<tr><td>'.$langs->trans('AnxhrSheetFile').'</td><td>';
if ($file !== '') {
	print img_mime($file, '', 'pictofixedwidth').dol_escape_htmltag(basename($file)).' <span class="opacitymedium">('.dol_print_size(dol_filesize($file), 1, 1).')</span>';
} else {
	print '<span class="opacitymedium">'.$langs->trans('AnxhrSheetNotGenerated').'</span>';
}
print '</td></tr>';
print '<tr><td>'.$langs->trans('AnxhrSheetGeneratedAt').'</td><td>'.($file !== '' ? dol_print_date(dol_filemtime($file), 'dayhour', 'tzuserrel') : '').'</td></tr>';
print '</table>';
print '</div>';

print dol_get_fiche_end();

// Buttons
print '<div class="tabsAction">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" class="inline-block">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="generate">';
print '<input type="hidden" name="id" value="'.((int) $object->id).'">';
print '<input type="submit" class="butAction" value="'.dol_escape_htmltag($langs->trans($file !== '' ? 'AnxhrSheetRegenerate' : 'AnxhrSheetGenerate')).'">';
print '</form>';
if ($file !== '') {
	print dolGetButtonAction('', $langs->trans('AnxhrSheetDownload'), 'default', $selfurl.'&action=download&attachment=1&token='.newToken(), '', 1);
	print dolGetButtonAction('', $langs->trans('AnxhrSheetOpen'), 'default', $selfurl.'&action=download&token='.newToken(), '', 1, array('attr' => array('target' => '_blank', 'rel' => 'noopener')));
} else {
	print dolGetButtonAction($langs->trans('AnxhrSheetNotGenerated'), $langs->trans('AnxhrSheetDownload'), 'default', '#', '', 0);
	print dolGetButtonAction($langs->trans('AnxhrSheetNotGenerated'), $langs->trans('AnxhrSheetOpen'), 'default', '#', '', 0);
}
print '</div>';

print info_admin($langs->trans('AnxhrSheetLegalHint'), 0, 0, '0', '');

print '<div class="margintoponly"><a href="'.dol_buildpath('/anxhr/time_periods.php', 1).'?year='.((int) $object->year).'&month='.((int) $object->month).'">'.img_picto('', 'fa-arrow-left', 'class="pictofixedwidth"').$langs->trans('AnxhrPeriods').'</a>';
print ' &nbsp; <a href="'.dol_buildpath('/anxhr/time_day.php', 1).'?id='.((int) $object->fk_user).'&year='.((int) $object->year).'&month='.((int) $object->month).'">'.img_picto('', 'fa-calendar-alt', 'class="pictofixedwidth"').$langs->trans('AnxhrMonthView').'</a></div>';

llxFooter();
$db->close();
