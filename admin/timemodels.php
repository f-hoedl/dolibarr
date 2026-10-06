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
 * \file        htdocs/custom/anxhr/admin/timemodels.php
 * \ingroup     anxhr
 * \brief       Admin tab: working time models and collective agreement (KV) parameters
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
require_once __DIR__.'/../class/timemodel.class.php';
require_once __DIR__.'/../class/kvrate.class.php';

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
$kvid = GETPOSTINT('kvid');

// Access control
if (!isModEnabled('anxhr')) {
	accessforbidden('Module not enabled');
}
if (!$user->admin && !$user->hasRight('anxhr', 'time', 'admin')) {
	accessforbidden();
}

$model = new TimeModel($db);
if ($id > 0 && $model->fetch($id) <= 0) {
	accessforbidden('Record not found');
}
$kv = new KvRate($db);
if ($kvid > 0 && $kv->fetch($kvid) <= 0) {
	accessforbidden('Record not found');
}
$weekdays = array(1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday');


/*
 * Actions
 */

if (GETPOST('cancel', 'alpha')) {
	$action = '';
}

if ($action == 'setdefaults' && $_SERVER['REQUEST_METHOD'] === 'POST') {
	dolibarr_set_const($db, 'ANXHR_TIME_DEFAULT_MODEL', (string) GETPOSTINT('ANXHR_TIME_DEFAULT_MODEL'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'ANXHR_TIME_DEFAULT_KV', GETPOST('ANXHR_TIME_DEFAULT_KV', 'aZ09'), 'chaine', 0, '', $conf->entity);
	setEventMessages($langs->trans('SetupSaved'), null, 'mesgs');
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

if (in_array($action, array('addmodel', 'updatemodel'), true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$model->ref = GETPOST('ref', 'alphanohtml');
	$model->label = GETPOST('label', 'alphanohtml');
	$model->model_type = GETPOST('model_type', 'aZ09');
	$model->weekly_hours = (float) price2num(GETPOST('weekly_hours', 'alphanohtml'));
	$daily = array();
	$sum = 0;
	foreach (array_keys($weekdays) as $d) {
		$daily[$d] = (int) anxhrTimeParseDuration(GETPOST('daily_'.$d, 'alphanohtml'));
		$sum += $daily[$d];
	}
	$model->setDailyMinutes($daily);
	if ($model->weekly_hours <= 0) {
		$model->weekly_hours = round($sum / 60, 2);
	}
	$model->flex_start = anxhrTimeCleanHhmm(GETPOST('flex_start', 'alphanohtml'));
	$model->flex_end = anxhrTimeCleanHhmm(GETPOST('flex_end', 'alphanohtml'));
	$model->core_start = anxhrTimeCleanHhmm(GETPOST('core_start', 'alphanohtml'));
	$model->core_end = anxhrTimeCleanHhmm(GETPOST('core_end', 'alphanohtml'));
	$model->flex_period_months = max(1, GETPOSTINT('flex_period_months'));
	$model->max_carry_plus_min = anxhrTimeParseDuration(GETPOST('max_carry_plus', 'alphanohtml'));
	$model->max_carry_minus_min = anxhrTimeParseDuration(GETPOST('max_carry_minus', 'alphanohtml'));
	$model->break_rule = GETPOST('break_rule', 'aZ09');
	$normalMax = anxhrTimeParseDuration(GETPOST('normal_daily_max', 'alphanohtml'));
	$model->normal_daily_max_min = $normalMax ? min(720, max(60, $normalMax)) : 600;
	$model->kv_code = GETPOST('kv_code', 'aZ09');
	$model->status = GETPOSTINT('status') ? TimeModel::STATUS_ACTIVE : TimeModel::STATUS_DISABLED;
	if (empty($model->ref)) {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Ref')), null, 'errors');
		$action = ($action == 'addmodel' ? 'createmodel' : 'editmodel');
	} else {
		$res = ($action == 'addmodel') ? $model->create($user) : $model->update($user);
		if ($res > 0) {
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
			header('Location: '.$_SERVER['PHP_SELF']);
			exit;
		}
		setEventMessages($model->error, $model->errors, 'errors');
		$action = ($action == 'addmodel' ? 'createmodel' : 'editmodel');
	}
}

if ($action == 'confirm_deletemodel' && $confirm == 'yes' && $id > 0) {
	if ($model->delete($user) > 0) {
		setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
	} else {
		setEventMessages($model->error, $model->errors, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}

if (in_array($action, array('addkv', 'updatekv'), true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$kv->kv_code = GETPOST('kv_code', 'aZ09');
	$kv->label = GETPOST('label', 'alphanohtml');
	$kv->valid_from = GETPOSTDATE('valid_from', '00:00:00', 'tzserver');
	$kv->weekly_hours = (float) price2num(GETPOST('weekly_hours', 'alphanohtml'));
	$kv->ot_pct = GETPOSTINT('ot_pct');
	$kv->ot_night_pct = GETPOSTINT('ot_night_pct');
	$kv->ot_sunday_pct = GETPOSTINT('ot_sunday_pct');
	$kv->parttime_extra_pct = GETPOSTINT('parttime_extra_pct');
	$kv->za_factor = (float) price2num(GETPOST('za_factor', 'alphanohtml'));
	$kv->dec24_free = GETPOSTINT('dec24_free') ? 1 : 0;
	$kv->dec31_free = GETPOSTINT('dec31_free') ? 1 : 0;
	$kv->night_start = anxhrTimeCleanHhmm(GETPOST('night_start', 'alphanohtml')) ?: '22:00';
	$kv->night_end = anxhrTimeCleanHhmm(GETPOST('night_end', 'alphanohtml')) ?: '06:00';
	if (empty($kv->kv_code) || empty($kv->valid_from) || $kv->weekly_hours <= 0) {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('AnxhrKvCode').', '.$langs->transnoentitiesnoconv('AnxhrValidFrom').', '.$langs->transnoentitiesnoconv('AnxhrWeeklyHours')), null, 'errors');
		$action = ($action == 'addkv' ? 'createkv' : 'editkv');
	} else {
		$res = ($action == 'addkv') ? $kv->create($user) : $kv->update($user);
		if ($res > 0) {
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
			header('Location: '.$_SERVER['PHP_SELF']);
			exit;
		}
		setEventMessages($kv->error, $kv->errors, 'errors');
		$action = ($action == 'addkv' ? 'createkv' : 'editkv');
	}
}

if ($action == 'confirm_deletekv' && $confirm == 'yes' && $kvid > 0) {
	if ($kv->delete($user) > 0) {
		setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
	} else {
		setEventMessages($kv->error, $kv->errors, 'errors');
	}
	header('Location: '.$_SERVER['PHP_SELF']);
	exit;
}


/*
 * View
 */

$form = new Form($db);
$title = 'AnxhrAdminTimeModels';
llxHeader('', $langs->trans($title), '', '', 0, 0, '', '', '', 'mod-anxhr page-admin-timemodels');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.img_picto($langs->trans('BackToModuleList'), 'back', 'class="pictofixedwidth"').'<span class="hideonsmartphone">'.$langs->trans('BackToModuleList').'</span></a>';
print load_fiche_titre($langs->trans('AnxhrSetup'), $linkback, 'title_setup');
print dol_get_fiche_head(anxhrAdminPrepareHead(), 'timemodels', $langs->trans('AnxhrSetup'), -1, 'fa-id-card');

if ($action == 'deletemodel' && $id > 0) {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?id='.$id, $langs->trans('Delete'), $langs->trans('AnxhrConfirmDeleteModel', $model->ref), 'confirm_deletemodel', '', 0, 1);
}
if ($action == 'deletekv' && $kvid > 0) {
	print $form->formconfirm($_SERVER['PHP_SELF'].'?kvid='.$kvid, $langs->trans('Delete'), $langs->trans('AnxhrConfirmDeleteKv', $kv->kv_code), 'confirm_deletekv', '', 0, 1);
}

$models = $model->fetchAllModels(0);
if (!is_array($models)) {
	$models = array();
}
$kvlist = $kv->fetchAllRates();
if (!is_array($kvlist)) {
	$kvlist = array();
}
$kvcodes = array();
foreach ($kvlist as $r) {
	$kvcodes[$r->kv_code] = $r->kv_code;
}

// Defaults
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'"><input type="hidden" name="action" value="setdefaults">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Parameter').'</th><th>'.$langs->trans('Value').'</th></tr>';
$modeloptions = array();
foreach ($models as $m) {
	$modeloptions[$m->id] = $m->ref.' - '.$m->label;
}
print '<tr class="oddeven"><td>'.$form->textwithpicto($langs->trans('AnxhrDefaultTimeModel'), $langs->trans('AnxhrDefaultTimeModelHelp')).'</td><td>'.$form->selectarray('ANXHR_TIME_DEFAULT_MODEL', $modeloptions, getDolGlobalInt('ANXHR_TIME_DEFAULT_MODEL'), 1, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td></tr>';
print '<tr class="oddeven"><td>'.$form->textwithpicto($langs->trans('AnxhrDefaultKv'), $langs->trans('AnxhrDefaultKvHelp')).'</td><td>'.$form->selectarray('ANXHR_TIME_DEFAULT_KV', $kvcodes, getDolGlobalString('ANXHR_TIME_DEFAULT_KV', 'AZG'), 1, 0, 0, '', 0, 0, 0, '', 'minwidth200').'</td></tr>';
print '</table>';
print '<div class="center"><input type="submit" class="button button-save" value="'.dol_escape_htmltag($langs->trans('Save')).'"></div>';
print '</form><br>';

// Time models list
$newmodel = dolGetButtonTitle($langs->trans('AnxhrNewTimeModel'), '', 'fa fa-plus-circle', $_SERVER['PHP_SELF'].'?action=createmodel');
print load_fiche_titre($langs->trans('AnxhrTimeModels'), $newmodel, 'fa-business-time');
print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('Ref').'</th><th>'.$langs->trans('Label').'</th><th>'.$langs->trans('AnxhrModelType').'</th><th class="right">'.$langs->trans('AnxhrWeeklyHours').'</th><th class="hideonsmartphone">'.$langs->trans('AnxhrDailyMinutes').'</th><th>'.$langs->trans('AnxhrBreakRule').'</th><th class="right">'.$langs->trans('AnxhrNormalDailyMax').'</th><th>'.$langs->trans('AnxhrKvCode').'</th><th class="center">'.$langs->trans('Status').'</th><th></th></tr>';
if (empty($models)) {
	print '<tr class="oddeven"><td colspan="10"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
foreach ($models as $m) {
	$dm = $m->getDailyMinutes();
	$dmtxt = array();
	foreach ($weekdays as $d => $wkey) {
		$dmtxt[] = dol_substr($langs->trans($wkey), 0, 2).' '.anxhrTimeFormatMinutes($dm[$d]);
	}
	print '<tr class="oddeven">';
	print '<td>'.dol_escape_htmltag($m->ref).'</td>';
	print '<td class="tdoverflowmax200">'.dol_escape_htmltag($m->label).'</td>';
	print '<td>'.dol_escape_htmltag(isset($m->fields['model_type']['arrayofkeyval'][$m->model_type]) ? $m->fields['model_type']['arrayofkeyval'][$m->model_type] : $m->model_type).'</td>';
	print '<td class="right">'.price($m->weekly_hours, 0, $langs, 0, -1, 2).'</td>';
	print '<td class="hideonsmartphone small">'.dol_escape_htmltag(implode(', ', $dmtxt)).'</td>';
	print '<td>'.dol_escape_htmltag(isset($m->fields['break_rule']['arrayofkeyval'][$m->break_rule]) ? $m->fields['break_rule']['arrayofkeyval'][$m->break_rule] : $m->break_rule).'</td>';
	print '<td class="right">'.anxhrTimeFormatMinutes($m->normal_daily_max_min).'</td>';
	print '<td>'.dol_escape_htmltag($m->kv_code).'</td>';
	print '<td class="center">'.$m->getLibStatut(5).'</td>';
	print '<td class="right nowraponall">';
	print '<a class="editfielda marginrightonly" href="'.$_SERVER['PHP_SELF'].'?action=editmodel&id='.$m->id.'">'.img_edit().'</a>';
	print '<a href="'.$_SERVER['PHP_SELF'].'?action=deletemodel&id='.$m->id.'&token='.newToken().'">'.img_delete().'</a>';
	print '</td></tr>';
}
print '</table></div>';

if ($action == 'createmodel' || $action == 'editmodel') {
	$isnew = ($action == 'createmodel');
	$dm = $isnew ? array(1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 480, 6 => 0, 7 => 0) : $model->getDailyMinutes();
	print '<br>';
	print load_fiche_titre($langs->trans($isnew ? 'AnxhrNewTimeModel' : 'AnxhrEditTimeModel'), '', '');
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="'.($isnew ? 'addmodel' : 'updatemodel').'">';
	if (!$isnew) {
		print '<input type="hidden" name="id" value="'.$model->id.'">';
	}
	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('Ref').'</td><td><input type="text" name="ref" class="maxwidth150" value="'.dol_escape_htmltag($isnew ? '' : $model->ref).'"></td></tr>';
	print '<tr><td>'.$langs->trans('Label').'</td><td><input type="text" name="label" class="minwidth300" value="'.dol_escape_htmltag($isnew ? '' : $model->label).'"></td></tr>';
	print '<tr><td>'.$form->textwithpicto($langs->trans('AnxhrModelType'), $langs->trans('AnxhrModelTypeHelp')).'</td><td>'.$form->selectarray('model_type', $model->fields['model_type']['arrayofkeyval'], $isnew ? 'fixed' : $model->model_type).'</td></tr>';
	print '<tr><td>'.$form->textwithpicto($langs->trans('AnxhrWeeklyHours'), $langs->trans('AnxhrWeeklyHoursModelHelp')).'</td><td><input type="text" name="weekly_hours" class="maxwidth75" value="'.dol_escape_htmltag($isnew ? '' : price2num($model->weekly_hours)).'"></td></tr>';
	print '<tr><td>'.$form->textwithpicto($langs->trans('AnxhrDailyMinutes'), $langs->trans('AnxhrDailyMinutesHelp')).'</td><td>';
	foreach ($weekdays as $d => $wkey) {
		print '<label class="inline-block marginrightonly">'.dol_substr($langs->trans($wkey), 0, 2).' <input type="text" name="daily_'.$d.'" class="width50" value="'.anxhrTimeFormatMinutes($dm[$d]).'"></label>';
	}
	print '</td></tr>';
	print '<tr><td>'.$langs->trans('AnxhrFlexFrame').'</td><td><input type="time" name="flex_start" value="'.dol_escape_htmltag($isnew ? '' : $model->flex_start).'"> - <input type="time" name="flex_end" value="'.dol_escape_htmltag($isnew ? '' : $model->flex_end).'"></td></tr>';
	print '<tr><td>'.$langs->trans('AnxhrCoreTime').'</td><td><input type="time" name="core_start" value="'.dol_escape_htmltag($isnew ? '' : $model->core_start).'"> - <input type="time" name="core_end" value="'.dol_escape_htmltag($isnew ? '' : $model->core_end).'"></td></tr>';
	print '<tr><td>'.$langs->trans('AnxhrFlexPeriodMonths').'</td><td><input type="number" min="1" max="12" name="flex_period_months" class="width50" value="'.((int) ($isnew ? 1 : $model->flex_period_months)).'"></td></tr>';
	print '<tr><td>'.$langs->trans('AnxhrMaxCarryPlus').'</td><td><input type="text" name="max_carry_plus" class="width75" placeholder="40:00" value="'.(($isnew || $model->max_carry_plus_min === null || $model->max_carry_plus_min === '') ? '' : anxhrTimeFormatMinutes($model->max_carry_plus_min)).'"></td></tr>';
	print '<tr><td>'.$langs->trans('AnxhrMaxCarryMinus').'</td><td><input type="text" name="max_carry_minus" class="width75" placeholder="10:00" value="'.(($isnew || $model->max_carry_minus_min === null || $model->max_carry_minus_min === '') ? '' : anxhrTimeFormatMinutes($model->max_carry_minus_min)).'"></td></tr>';
	print '<tr><td>'.$form->textwithpicto($langs->trans('AnxhrBreakRule'), $langs->trans('AnxhrBreakRuleHelp')).'</td><td>'.$form->selectarray('break_rule', $model->fields['break_rule']['arrayofkeyval'], $isnew ? 'auto' : $model->break_rule).'</td></tr>';
	print '<tr><td>'.$form->textwithpicto($langs->trans('AnxhrNormalDailyMax'), $langs->trans('AnxhrNormalDailyMaxHelp')).'</td><td><input type="text" name="normal_daily_max" class="width75" value="'.anxhrTimeFormatMinutes($isnew ? 600 : $model->normal_daily_max_min).'"></td></tr>';
	print '<tr><td>'.$langs->trans('AnxhrKvCode').'</td><td>'.$form->selectarray('kv_code', $kvcodes, $isnew ? '' : $model->kv_code, 1).'</td></tr>';
	print '<tr><td>'.$langs->trans('Enabled').'</td><td><input type="checkbox" name="status" value="1"'.(($isnew || $model->status) ? ' checked' : '').'></td></tr>';
	print '</table>';
	print $form->buttonsSaveCancel('Save', 'Cancel');
	print '</form>';
}

// KV rates
print '<br>';
$newkv = dolGetButtonTitle($langs->trans('AnxhrNewKvRate'), '', 'fa fa-plus-circle', $_SERVER['PHP_SELF'].'?action=createkv');
print load_fiche_titre($langs->trans('AnxhrKvRates'), $newkv, 'fa-balance-scale');
print '<div class="div-table-responsive">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans('AnxhrKvCode').'</th><th>'.$langs->trans('Label').'</th><th>'.$langs->trans('AnxhrValidFrom').'</th><th class="right">'.$langs->trans('AnxhrWeeklyHours').'</th><th class="right">'.$langs->trans('AnxhrOtPct').'</th><th class="right">'.$langs->trans('AnxhrOtNightPct').'</th><th class="right">'.$langs->trans('AnxhrOtSundayPct').'</th><th class="right">'.$langs->trans('AnxhrParttimeExtraPct').'</th><th class="center">'.$langs->trans('AnxhrDec24Free').'</th><th class="center">'.$langs->trans('AnxhrDec31Free').'</th><th class="hideonsmartphone">'.$langs->trans('AnxhrNightWindow').'</th><th></th></tr>';
if (empty($kvlist)) {
	print '<tr class="oddeven"><td colspan="12"><span class="opacitymedium">'.$langs->trans('NoRecordFound').'</span></td></tr>';
}
foreach ($kvlist as $r) {
	print '<tr class="oddeven">';
	print '<td>'.dol_escape_htmltag($r->kv_code).'</td>';
	print '<td class="tdoverflowmax200">'.dol_escape_htmltag($r->label).'</td>';
	print '<td>'.dol_print_date($r->valid_from, 'day', 'tzserver').'</td>';
	print '<td class="right">'.price($r->weekly_hours, 0, $langs, 0, -1, 2).'</td>';
	print '<td class="right">'.((int) $r->ot_pct).' %</td>';
	print '<td class="right">'.((int) $r->ot_night_pct).' %</td>';
	print '<td class="right">'.((int) $r->ot_sunday_pct).' %</td>';
	print '<td class="right">'.((int) $r->parttime_extra_pct).' %</td>';
	print '<td class="center">'.yn($r->dec24_free).'</td>';
	print '<td class="center">'.yn($r->dec31_free).'</td>';
	print '<td class="hideonsmartphone">'.dol_escape_htmltag($r->night_start.' - '.$r->night_end).'</td>';
	print '<td class="right nowraponall">';
	print '<a class="editfielda marginrightonly" href="'.$_SERVER['PHP_SELF'].'?action=editkv&kvid='.$r->id.'">'.img_edit().'</a>';
	print '<a href="'.$_SERVER['PHP_SELF'].'?action=deletekv&kvid='.$r->id.'&token='.newToken().'">'.img_delete().'</a>';
	print '</td></tr>';
}
print '</table></div>';

if ($action == 'createkv' || $action == 'editkv') {
	$isnew = ($action == 'createkv');
	print '<br>';
	print load_fiche_titre($langs->trans($isnew ? 'AnxhrNewKvRate' : 'AnxhrEditKvRate'), '', '');
	print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="'.($isnew ? 'addkv' : 'updatekv').'">';
	if (!$isnew) {
		print '<input type="hidden" name="kvid" value="'.$kv->id.'">';
	}
	print '<table class="border centpercent tableforfield">';
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans('AnxhrKvCode').'</td><td><input type="text" name="kv_code" class="maxwidth150" value="'.dol_escape_htmltag($isnew ? '' : $kv->kv_code).'"></td></tr>';
	print '<tr><td>'.$langs->trans('Label').'</td><td><input type="text" name="label" class="minwidth300" value="'.dol_escape_htmltag($isnew ? '' : $kv->label).'"></td></tr>';
	print '<tr><td class="fieldrequired">'.$form->textwithpicto($langs->trans('AnxhrValidFrom'), $langs->trans('AnxhrValidFromHelp')).'</td><td>'.$form->selectDate($isnew ? dol_now() : $kv->valid_from, 'valid_from', 0, 0, 0, '', 1, 0, 0, '', '', '', '', 1, '', '', 'tzserver').'</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans('AnxhrWeeklyHours').'</td><td><input type="text" name="weekly_hours" class="maxwidth75" value="'.dol_escape_htmltag($isnew ? '40' : price2num($kv->weekly_hours)).'"></td></tr>';
	foreach (array('ot_pct' => 50, 'ot_night_pct' => 100, 'ot_sunday_pct' => 100, 'parttime_extra_pct' => 25) as $f => $def) {
		$label = 'Anxhr'.str_replace(' ', '', ucwords(str_replace('_', ' ', $f)));
		print '<tr><td>'.$langs->trans($label).'</td><td><input type="number" min="0" max="300" name="'.$f.'" class="width75" value="'.((int) ($isnew ? $def : $kv->$f)).'"> %</td></tr>';
	}
	print '<tr><td>'.$form->textwithpicto($langs->trans('AnxhrZaFactor'), $langs->trans('AnxhrZaFactorHelp')).'</td><td><input type="text" name="za_factor" class="width75" value="'.dol_escape_htmltag($isnew ? '1.5' : price2num($kv->za_factor)).'"></td></tr>';
	print '<tr><td>'.$langs->trans('AnxhrDec24Free').'</td><td><input type="checkbox" name="dec24_free" value="1"'.((!$isnew && $kv->dec24_free) ? ' checked' : '').'></td></tr>';
	print '<tr><td>'.$langs->trans('AnxhrDec31Free').'</td><td><input type="checkbox" name="dec31_free" value="1"'.((!$isnew && $kv->dec31_free) ? ' checked' : '').'></td></tr>';
	print '<tr><td>'.$langs->trans('AnxhrNightWindow').'</td><td><input type="time" name="night_start" value="'.dol_escape_htmltag($isnew ? '22:00' : $kv->night_start).'"> - <input type="time" name="night_end" value="'.dol_escape_htmltag($isnew ? '06:00' : $kv->night_end).'"></td></tr>';
	print '</table>';
	print $form->buttonsSaveCancel('Save', 'Cancel');
	print '</form>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
