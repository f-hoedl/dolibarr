<?php
/* Copyright (C) 2026 ANX
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
 * \file        htdocs/custom/anxhr/contract_card.php
 * \ingroup     anxhr
 * \brief       Page to create/edit/view an employment contract
 */

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
}
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
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
/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
dol_include_once('/anxhr/class/hrcontract.class.php');
dol_include_once('/anxhr/class/hrdeadline.class.php');
dol_include_once('/anxhr/lib/anxhr_hr.lib.php');

$langs->loadLangs(array('anxhr@anxhr', 'anxhr_hr@anxhr', 'users', 'other'));

// Get parameters
$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'aZ09');
$tab = GETPOST('tab', 'aZ09');
$fromid = GETPOSTINT('fromid');
$backtopage = GETPOST('backtopage', 'alpha');
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha');

// Initialize technical objects
$object = new HrContract($db);
$extrafields = new ExtraFields($db);
$hookmanager->initHooks(array('anxhrcontractcard', 'globalcard'));
$extrafields->fetch_name_optionals_label($object->table_element);

if (empty($action) && empty($id) && empty($ref)) {
	$action = 'view';
}

// Load object
include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';

// Permissions
$permissiontoread = anxhrHrCanRead($user, 'contract', ($object->id > 0 ? (int) $object->fk_user : 0));
$permissiontoadd = $user->hasRight('anxhr', 'contract', 'write');
$permissiontodelete = $user->hasRight('anxhr', 'contract', 'delete');
$permissionnote = $permissiontoadd;
// Private notes are for HR only (employees reading their own contract do not see them)
if (!$user->hasRight('anxhr', 'contract', 'read')) {
	unset($object->fields['note_private']);
}

// Security check
if (!isModEnabled('anxhr')) {
	accessforbidden('Module anxhr not enabled');
}
if ($user->socid > 0) {
	accessforbidden();
}
if (in_array($action, array('create', 'add', 'edit', 'update')) && !$permissiontoadd) {
	accessforbidden();
}
if ($object->id > 0 && !$permissiontoread) {
	accessforbidden();
}
if (empty($object->id) && !in_array($action, array('create', 'add')) && !$user->hasRight('anxhr', 'contract', 'read')) {
	accessforbidden();
}

$error = 0;


/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	$backurlforlist = dol_buildpath('/anxhr/contract_list.php', 1);
	if (empty($backtopage) || ($cancel && empty($id))) {
		if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) {
			$backtopage = $backurlforlist;
		} else {
			$backtopage = dol_buildpath('/anxhr/contract_card.php', 1).'?id='.((!empty($id) && $id > 0) ? $id : '__ID__');
		}
	}
	$triggermodname = 'ANXHR_CONTRACT_MODIFY';

	// Activate a draft contract
	if ($action == 'confirm_activate' && $confirm == 'yes' && $permissiontoadd && $object->status == HrContract::STATUS_DRAFT) {
		$result = $object->activate($user);
		if ($result > 0) {
			setEventMessages($langs->trans('AnxhrContractActivated'), null, 'mesgs');
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		$action = '';
	}

	// End an active contract
	if ($action == 'confirm_endcontract' && $confirm == 'yes' && $permissiontoadd && $object->status == HrContract::STATUS_ACTIVE) {
		$dateend = dol_mktime(12, 0, 0, GETPOSTINT('enddatemonth'), GETPOSTINT('enddateday'), GETPOSTINT('enddateyear'));
		if (empty($dateend)) {
			setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('AnxhrContractDateEnd')), null, 'errors');
		} elseif ($object->close($user, $dateend) > 0) {
			setEventMessages($langs->trans('AnxhrContractEnded'), null, 'mesgs');
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		$action = '';
	}

	// Regenerate automatic deadlines
	if ($action == 'regeneratedeadlines' && $permissiontoadd && $object->status == HrContract::STATUS_ACTIVE) {
		$result = anxhrGenerateDeadlinesForContract($object, $user);
		if ($result >= 0) {
			setEventMessages($langs->trans('AnxhrDeadlinesGenerated', $result), null, 'mesgs');
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		$action = '';
	}

	// Actions cancel, add, update, confirm_delete (standard include). We redirect ourself after add/update
	// to be able to show the list of contracts that were ended automatically.
	$noback = (in_array($action, array('add', 'update')) ? 1 : 0);
	$actionbeforeinclude = $action;
	include DOL_DOCUMENT_ROOT.'/core/actions_addupdatedelete.inc.php';

	if (in_array($actionbeforeinclude, array('add', 'update')) && empty($error) && $object->id > 0 && !in_array($action, array('create', 'edit'))) {
		if (!empty($object->context['anxhr_ended_contracts'])) {
			setEventMessages($langs->trans('AnxhrContractsAutoEnded', implode(', ', $object->context['anxhr_ended_contracts'])), null, 'warnings');
		}
		header('Location: '.dol_buildpath('/anxhr/contract_card.php', 1).'?id='.((int) $object->id));
		exit;
	}
	if ($actionbeforeinclude == 'confirm_delete' && empty($error) && $confirm == 'yes') {
		header('Location: '.$backurlforlist);
		exit;
	}
}


/*
 * View
 */

$form = new Form($db);

$title = $langs->trans('AnxhrContract');
if ($action == 'create') {
	$title = $langs->trans('AnxhrNewContract');
} elseif ($object->id > 0) {
	$title = $object->ref.' - '.$langs->trans('AnxhrContract');
}
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-anxhr page-contract-card');

// Card sections (fields grouped by topic)
$sections = array(
	'AnxhrSectionGeneral' => array('ref', 'fk_user', 'employment_type', 'status'),
	'AnxhrSectionDates' => array('date_start', 'date_end', 'probation_end', 'fixed_term_end', 'notice_period_weeks'),
	'AnxhrSectionWorkingTime' => array('weekly_hours', 'fk_timemodel'),
	'AnxhrSectionKv' => array('kv_code', 'kv_group', 'kv_level', 'kv_level_date'),
	'AnxhrSectionHomeoffice' => array('homeoffice_days_week', 'homeoffice_agreement_date'),
	'AnxhrSectionVacation' => array('vacation_days_year', 'vacation_in_hours', 'prior_service_months'),
	'AnxhrSectionSensitive' => array('disability_status', 'note_private'),
);

// Part to create
if ($action == 'create') {
	$prefill = null;
	if ($fromid > 0) {
		$prefill = new HrContract($db);
		if ($prefill->fetch($fromid) > 0) {
			$prefill->date_start = '';
			$prefill->date_end = '';
			$prefill->status = HrContract::STATUS_DRAFT;
		} else {
			$prefill = null;
		}
	}
	if (!$prefill) {
		// Sensible defaults for a new contract: field defaults, start today, active unless the employee already
		// has an active contract (activating a second one would end the current one automatically).
		$prefill = new HrContract($db);
		foreach ($object->fields as $key => $val) {
			$prefill->$key = (isset($val['default']) && $val['default'] !== '(PROV)' ? $val['default'] : '');
		}
		$prefill->date_start = dol_now();
		$prefill->status = HrContract::STATUS_ACTIVE;
		$fkuserdefault = GETPOSTINT('fk_user');
		if ($fkuserdefault > 0) {
			$prefill->fk_user = $fkuserdefault;
			$sqlactive = "SELECT COUNT(t.rowid) as nb FROM ".$db->prefix()."anxhr_contract as t";
			$sqlactive .= " WHERE t.fk_user = ".((int) $fkuserdefault)." AND t.status = ".((int) HrContract::STATUS_ACTIVE);
			$sqlactive .= " AND t.entity IN (".getEntity('anxhr_contract').")";
			$resactive = $db->query($sqlactive);
			$objactive = ($resactive ? $db->fetch_object($resactive) : null);
			if (!$objactive || (int) $objactive->nb > 0) {
				$prefill->status = HrContract::STATUS_DRAFT;
			}
		}
	}

	print load_fiche_titre($title, '', $object->picto);
	if ($fromid > 0 && $prefill && !empty($prefill->ref)) {
		print info_admin($langs->trans('AnxhrNewVersionInfo', $prefill->ref), 0, 0, '1');
	}

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';
	if ($backtopage) {
		print '<input type="hidden" name="backtopage" value="'.dol_escape_htmltag($backtopage).'">';
	}
	if ($backtopageforcancel) {
		print '<input type="hidden" name="backtopageforcancel" value="'.dol_escape_htmltag($backtopageforcancel).'">';
	}

	print dol_get_fiche_head(array(), '');
	print '<table class="border centpercent tableforfieldcreate">'."\n";
	anxhrPrintFieldsBySection($object, $sections, 'create', $form, $prefill);
	print '</table>'."\n";
	print dol_get_fiche_end();

	print $form->buttonsSaveCancel('Create');
	print '</form>';
}

// Part to edit record
if (($id || $ref) && $action == 'edit') {
	print load_fiche_titre($langs->trans('AnxhrContract'), '', $object->picto);

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="id" value="'.((int) $object->id).'">';
	if ($backtopage) {
		print '<input type="hidden" name="backtopage" value="'.dol_escape_htmltag($backtopage).'">';
	}

	print dol_get_fiche_head();
	print '<table class="border centpercent tableforfieldedit">'."\n";
	anxhrPrintFieldsBySection($object, $sections, 'edit', $form);
	print '</table>';
	print dol_get_fiche_end();

	print $form->buttonsSaveCancel();
	print '</form>';
}

// Part to show record
if ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'create'))) {
	$formconfirm = '';
	if ($action == 'delete') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('AnxhrDeleteContract'), $langs->trans('AnxhrConfirmDeleteContract'), 'confirm_delete', '', 0, 1);
	}
	if ($action == 'activate') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('AnxhrActivateContract'), $langs->trans('AnxhrConfirmActivateContract', $object->ref), 'confirm_activate', '', 0, 1);
	}
	if ($action == 'endcontract') {
		$formquestion = array(
			array('type' => 'date', 'name' => 'enddate', 'label' => $langs->trans('AnxhrContractDateEnd'), 'value' => (empty($object->date_end) ? dol_now() : $object->date_end)),
		);
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('AnxhrEndContract'), $langs->trans('AnxhrConfirmEndContract', $object->ref), 'confirm_endcontract', $formquestion, 0, 1, 250);
	}
	$parameters = array('formConfirm' => $formconfirm);
	$reshook = $hookmanager->executeHooks('formConfirm', $parameters, $object, $action);
	if (empty($reshook)) {
		$formconfirm .= $hookmanager->resPrint;
	} elseif ($reshook > 0) {
		$formconfirm = $hookmanager->resPrint;
	}
	print $formconfirm;

	$head = anxhrContractPrepareHead($object);
	print dol_get_fiche_head($head, ($tab == 'info' ? 'info' : 'card'), $langs->trans('AnxhrContract'), -1, $object->picto, 0, '', '', 0, '', 1);

	$linkback = '<a href="'.dol_buildpath('/anxhr/contract_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
	$employees = anxhrLoadUsersCache($db, array((int) $object->fk_user));
	$morehtmlref = '<div class="refidno">';
	if (!empty($employees[(int) $object->fk_user])) {
		$morehtmlref .= $employees[(int) $object->fk_user]->getNomUrl(-1);
	}
	$morehtmlref .= '</div>';
	dol_banner_tab($object, 'ref', $linkback, ($user->hasRight('anxhr', 'contract', 'read') ? 1 : 0), 'ref', 'ref', $morehtmlref);

	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';
	if ($tab == 'info') {
		$object->info($object->id);
		print '<br>';
		dol_print_object_info($object, 1);
	} else {
		print '<table class="border centpercent tableforfield">'."\n";
		anxhrPrintFieldsBySection($object, $sections, 'view', $form);
		$parameters = array();
		$reshook = $hookmanager->executeHooks('formObjectOptions', $parameters, $object, $action);
		print $hookmanager->resPrint;
		print '</table>';
	}
	print '</div>';
	print '<div class="clearboth"></div>';
	print dol_get_fiche_end();

	// Buttons for actions
	print '<div class="tabsAction">'."\n";
	$parameters = array();
	$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action);
	if ($reshook < 0) {
		setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
	}
	if (empty($reshook)) {
		print dolGetButtonAction('', $langs->trans('Modify'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit&token='.newToken(), '', $permissiontoadd);
		if ($object->status == HrContract::STATUS_DRAFT) {
			print dolGetButtonAction('', $langs->trans('AnxhrActivateContract'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=activate&token='.newToken(), '', $permissiontoadd);
		}
		if ($object->status == HrContract::STATUS_ACTIVE) {
			print dolGetButtonAction($langs->trans('AnxhrNewVersionHelp'), $langs->trans('AnxhrNewVersion'), 'default', $_SERVER["PHP_SELF"].'?action=create&fromid='.$object->id.'&fk_user='.((int) $object->fk_user).'&token='.newToken(), '', $permissiontoadd);
			print dolGetButtonAction($langs->trans('AnxhrRegenerateDeadlinesHelp'), $langs->trans('AnxhrRegenerateDeadlines'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=regeneratedeadlines&token='.newToken(), '', $permissiontoadd);
			print dolGetButtonAction('', $langs->trans('AnxhrEndContract'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=endcontract&token='.newToken(), '', $permissiontoadd);
		}
		print dolGetButtonAction('', $langs->trans('Delete'), 'delete', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete&token='.newToken(), '', $permissiontodelete);
	}
	print '</div>'."\n";

	if ($tab != 'info') {
		print '<div class="fichecenter"><div class="fichehalfleft">';

		// Deadlines of this contract
		$deadlinestatic = new HrDeadline($db);
		$sql = "SELECT ".$deadlinestatic->getFieldList('t')." FROM ".$db->prefix()."anxhr_deadline as t";
		$sql .= " WHERE t.fk_contract = ".((int) $object->id)." AND t.entity IN (".getEntity('anxhr_deadline').")";
		$sql .= $db->order('t.date_due', 'ASC');
		$sql .= $db->plimit(50);
		$resql = $db->query($sql);
		print load_fiche_titre($langs->trans('AnxhrDeadlines'), '', 'fa-hourglass-half');
		print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
		print '<tr class="liste_titre"><th>'.$langs->trans('Label').'</th><th class="center">'.$langs->trans('AnxhrDateDue').'</th><th class="right">'.$langs->trans('Status').'</th></tr>';
		$nb = 0;
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$deadlinestatic->setVarsFromFetchObj($obj);
				print '<tr class="oddeven"><td class="tdoverflowmax200">'.$deadlinestatic->getNomUrl(1).'</td>';
				print '<td class="center nowraponall">'.dol_print_date($deadlinestatic->date_due, 'day').'</td>';
				print '<td class="right">'.$deadlinestatic->getLibStatut(5).'</td></tr>';
				$nb++;
			}
			$db->free($resql);
		}
		if (!$nb) {
			print '<tr><td colspan="3"><span class="opacitymedium">'.$langs->trans('None').'</span></td></tr>';
		}
		print '</table></div>';

		print '</div><div class="fichehalfright">';

		// History of contracts of employee
		$contractstatic = new HrContract($db);
		$sql = "SELECT t.rowid, t.ref, t.date_start, t.date_end, t.weekly_hours, t.status FROM ".$db->prefix()."anxhr_contract as t";
		$sql .= " WHERE t.fk_user = ".((int) $object->fk_user)." AND t.entity IN (".getEntity('anxhr_contract').")";
		$sql .= $db->order('t.date_start', 'DESC');
		$sql .= $db->plimit(50);
		$resql = $db->query($sql);
		print load_fiche_titre($langs->trans('AnxhrContractHistory'), '', $object->picto);
		print '<div class="div-table-responsive-no-min"><table class="noborder centpercent">';
		print '<tr class="liste_titre"><th>'.$langs->trans('Ref').'</th><th class="center">'.$langs->trans('AnxhrPeriod').'</th><th class="right hideonsmartphone">'.$langs->trans('AnxhrWeeklyHours').'</th><th class="right">'.$langs->trans('Status').'</th></tr>';
		if ($resql) {
			while ($obj = $db->fetch_object($resql)) {
				$contractstatic->id = (int) $obj->rowid;
				$contractstatic->ref = $obj->ref;
				$contractstatic->status = (int) $obj->status;
				$contractstatic->date_start = $db->jdate($obj->date_start);
				$contractstatic->date_end = $db->jdate($obj->date_end);
				$contractstatic->weekly_hours = $obj->weekly_hours;
				print '<tr class="oddeven'.($obj->rowid == $object->id ? ' highlight' : '').'"><td class="nowraponall">'.$contractstatic->getNomUrl(1).'</td>';
				print '<td class="center nowraponall">'.dol_print_date($contractstatic->date_start, 'day').' - '.(empty($contractstatic->date_end) ? '...' : dol_print_date($contractstatic->date_end, 'day')).'</td>';
				print '<td class="right hideonsmartphone">'.($obj->weekly_hours !== null ? price($obj->weekly_hours, 0, $langs, 0, -1, 2) : '').'</td>';
				print '<td class="right">'.$contractstatic->getLibStatut(5).'</td></tr>';
			}
			$db->free($resql);
		}
		print '</table></div>';

		print '</div></div>';
	}
}

// End of page
llxFooter();
$db->close();
