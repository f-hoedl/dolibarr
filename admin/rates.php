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
 * \file        htdocs/custom/anxhr/admin/rates.php
 * \ingroup     anxhr
 * \brief       Admin tab: travel allowance rates (Taggeld, Naechtigung, Kilometergeld) with validity history
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
	$res = @include "../../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once __DIR__.'/../lib/anxhr.lib.php';
require_once __DIR__.'/../lib/anxhr_time.lib.php';
require_once __DIR__.'/../class/travelrate.class.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 * @var User $user
 */

$langs->loadLangs(array('admin', 'anxhr@anxhr', 'anxhr_time@anxhr'));

$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$id = GETPOSTINT('id');
$search_code = GETPOST('search_code', 'aZ09');

// Access control
if (!isModEnabled('anxhr')) {
	accessforbidden('Module not enabled');
}
if (!$user->admin && !$user->hasRight('anxhr', 'travel', 'admin')) {
	accessforbidden();
}

$object = new TravelRate($db);
if ($id > 0 && $object->fetch($id) <= 0) {
	accessforbidden('Record not found');
}


/*
 * Actions
 */

if (GETPOST('cancel', 'alpha')) {
	$action = '';
}

if ($action == 'installdefaults' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$nb = $object->installDefaults($user);
	if ($nb >= 0) {
		setEventMessages($langs->trans('AnxhrDefaultRatesInstalled', $nb), null, 'mesgs');
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

if (in_array($action, array('add', 'update'), true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$object->code = GETPOST('code', 'aZ09');
	$object->label = GETPOST('label', 'alphanohtml');
	$object->valid_from = GETPOSTDATE('valid_from', '00:00:00', 'tzserver');
	$amount = anxhrTimeParseNumber(GETPOST('amount', 'alphanohtml'));
	$object->amount = ($amount === null ? '' : $amount);
	$cap = GETPOST('cap_per_year', 'alphanohtml');
	$object->cap_per_year = anxhrTimeParseNumber($cap);
	$object->note = GETPOST('note', 'alphanohtml');
	if (empty($object->code) || empty($object->valid_from) || $object->amount === '' || (float) $object->amount < 0) {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Code').', '.$langs->transnoentitiesnoconv('AnxhrValidFrom').', '.$langs->transnoentitiesnoconv('Amount')), null, 'errors');
		$action = ($action == 'add' ? 'create' : 'edit');
	} else {
		$res = ($action == 'add') ? $object->create($user) : $object->update($user);
		if ($res > 0) {
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
			header('Location: '.$_SERVER['PHP_SELF']);
			exit;
		}
		setEventMessages($object->error, $object->errors, 'errors');
		$action = ($action == 'add' ? 'create' : 'edit');
	}
}

if ($action == 'confirm_delete' && $confirm == 'yes' && $id > 0) {
	if ($object->delete($user) > 0) {
		setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}


/*
 * View
 */

$form = new Form($db);
llxHeader('', $langs->trans('AnxhrAdminRates'), '', '', 0, 0, '', '', '', 'mod-anxhr page-admin-rates');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.img_picto($langs->trans('BackToModuleList'), 'back', 'class="pictofixedwidth"').'<span class="hideonsmartphone">'.$langs->trans('BackToModuleList').'</span></a>';
print load_fiche_titre($langs->trans('AnxhrSetup'), $linkback, 'title_setup');
print dol_get_fiche_head(anxhrAdminPrepareHead(), 'rates', $langs->trans('AnxhrSetup'), -1, 'fa-id-card');

if ($action == 'delete' && $id > 0) {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$id, $langs->trans('Delete'), $langs->trans('AnxhrConfirmDeleteRate', $object->code), 'confirm_delete', '', 0, 1);
}

print '<div class="opacitymedium marginbottomonly">'.$langs->trans('AnxhrRatesHelp').'</div>';

$rates = $object->fetchAllRates();
if (!is_array($rates)) {
	$rates = array();
	setEventMessages($object->error, null, 'errors');
}
$codes = array();
foreach ($rates as $r) {
	$codes[$r->code] = $r->code;
}

$buttons = dolGetButtonTitle($langs->trans('AnxhrNewRate'), '', 'fa fa-plus-circle', $_SERVER['PHP_SELF'].'?action=create');
print load_fiche_titre($langs->trans('AnxhrTravelRates'), $buttons, 'fa-route');

print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" class="marginbottomonly">';
print $langs->trans('Code').': '.$form->selectarray('search_code', $codes, $search_code, 1, 0, 0, '', 0, 0, 0, '', 'minwidth150');
print ' <input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Search')).'">';
print '</form>';

print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Code').'</th><th>'.$langs->trans('Label').'</th><th>'.$langs->trans('AnxhrValidFrom').'</th><th class="right">'.$langs->trans('Amount').'</th><th class="right">'.$langs->trans('AnxhrCapPerYear').'</th><th class="hideonsmartphone">'.$langs->trans('Note').'</th><th class="center">'.$langs->trans('AnxhrCurrentlyValid').'</th><th></th></tr>';
if (empty($rates)) {
	print '<tr class="oddeven"><td colspan="8"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
// Rows are sorted by code then valid_from DESC: the first row per code with valid_from <= today is the current one.
$today = anxhrTsToTz(dol_now(), 'Y-m-d');
$currentDone = array();
foreach ($rates as $r) {
	if ($search_code !== '' && $r->code !== $search_code) {
		continue;
	}
	$from = dol_print_date($r->valid_from, '%Y-%m-%d', 'tzserver');
	$iscurrent = false;
	if (empty($currentDone[$r->code]) && $from <= $today) {
		$iscurrent = true;
		$currentDone[$r->code] = 1;
	}
	print '<tr class="oddeven">';
	print '<td>'.dol_escape_htmltag($r->code).'</td>';
	print '<td class="tdoverflowmax200">'.dol_escape_htmltag($r->label).'</td>';
	print '<td>'.dol_print_date($r->valid_from, 'day', 'tzserver').'</td>';
	print '<td class="right">'.price($r->amount, 0, $langs, 0, -1, 4).'</td>';
	print '<td class="right">'.(($r->cap_per_year === null || $r->cap_per_year === '') ? '' : price($r->cap_per_year, 0, $langs, 0, -1, 0)).'</td>';
	print '<td class="hideonsmartphone tdoverflowmax200">'.dol_escape_htmltag($r->note).'</td>';
	print '<td class="center">'.($iscurrent ? img_picto($langs->trans('AnxhrCurrentlyValid'), 'tick') : '').'</td>';
	print '<td class="right nowraponall">';
	print '<a class="editfielda marginrightonly" href="'.$_SERVER['PHP_SELF'].'?action=edit&id='.$r->id.'">'.img_edit().'</a>';
	print '<a href="'.$_SERVER['PHP_SELF'].'?action=delete&id='.$r->id.'&token='.newToken().'">'.img_delete().'</a>';
	print '</td></tr>';
}
print '</table></div>';

if ($action == 'create' || $action == 'edit') {
	$isnew = ($action == 'create');
	print '<br>';
	print load_fiche_titre($langs->trans($isnew ? 'AnxhrNewRate' : 'AnxhrEditRate'), '', '');
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="'.($isnew ? 'add' : 'update').'">';
	if (!$isnew) {
		print '<input type="hidden" name="id" value="'.$object->id.'">';
	}
	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefieldcreate fieldrequired">'.$form->textwithpicto($langs->trans('Code'), $langs->trans('AnxhrTravelRateCodeHelp')).'</td><td><input type="text" name="code" class="maxwidth200" list="anxhr-rate-codes" value="'.dol_escape_htmltag($isnew ? '' : $object->code).'">';
	print '<datalist id="anxhr-rate-codes">';
	foreach (array_unique(array_merge(array_keys(TravelDiet::defaultAustrianRates()), array_values($codes))) as $c) {
		print '<option value="'.dol_escape_htmltag($c).'">';
	}
	print '</datalist></td></tr>';
	print '<tr><td>'.$langs->trans('Label').'</td><td><input type="text" name="label" class="minwidth300" value="'.dol_escape_htmltag($isnew ? '' : $object->label).'"></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('AnxhrValidFrom').'</td><td>'.$form->selectDate($isnew ? dol_now() : $object->valid_from, 'valid_from', 0, 0, 0, '', 1, 0, 0, '', '', '', '', 1, '', '', 'tzserver').'</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('Amount').'</td><td><input type="text" name="amount" class="maxwidth100" value="'.dol_escape_htmltag($isnew ? '' : price2num($object->amount)).'"></td></tr>';
	print '<tr><td>'.$form->textwithpicto($langs->trans('AnxhrCapPerYear'), $langs->trans('AnxhrCapPerYearHelp')).'</td><td><input type="text" name="cap_per_year" class="maxwidth100" value="'.dol_escape_htmltag(($isnew || $object->cap_per_year === null) ? '' : price2num($object->cap_per_year)).'"></td></tr>';
	print '<tr><td>'.$langs->trans('Note').'</td><td><input type="text" name="note" class="minwidth300" value="'.dol_escape_htmltag($isnew ? '' : $object->note).'"></td></tr>';
	print '</table>';
	print $form->buttonsSaveCancel('Save', 'Cancel');
	print '</form>';
}

print dol_get_fiche_end();

print '<div class="tabsAction">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'" class="inline-block">';
print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="installdefaults">';
print '<input type="submit" class="butAction" value="'.dol_escape_htmltag($langs->trans('AnxhrInstallAustrianDefaults')).'" title="'.dol_escape_htmltag($langs->trans('AnxhrInstallAustrianDefaultsHelp')).'">';
print '</form>';
print '</div>';

llxFooter();
$db->close();
