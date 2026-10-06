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
 * \file        htdocs/custom/anxhr/handover_card.php
 * \ingroup     anxhr
 * \brief       Page to create/edit/view a handover (hardware, key, token, access right)
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
dol_include_once('/anxhr/class/hrhandover.class.php');
dol_include_once('/anxhr/class/hrchecklist.class.php');
dol_include_once('/anxhr/lib/anxhr_hr.lib.php');

$langs->loadLangs(array('anxhr@anxhr', 'anxhr_hr@anxhr', 'users', 'other'));

// Get parameters
$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'aZ09');
$tab = GETPOST('tab', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha');
$fk_checklist = GETPOSTINT('fk_checklist');			// When created from a checklist item
$fk_checklist_item = GETPOSTINT('fk_checklist_item');

// Initialize technical objects
$object = new HrHandover($db);
$extrafields = new ExtraFields($db);
$hookmanager->initHooks(array('anxhrhandovercard', 'globalcard'));
$extrafields->fetch_name_optionals_label($object->table_element);

if (empty($action) && empty($id) && empty($ref)) {
	$action = 'view';
}

include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';

// Permissions
$permissiontoreadall = $user->hasRight('anxhr', 'handover', 'read');
$permissiontoread = anxhrHrCanRead($user, 'handover', ($object->id > 0 ? (int) $object->fk_user : 0));
$permissiontoadd = $user->hasRight('anxhr', 'handover', 'write');
$permissiontodelete = $user->hasRight('anxhr', 'handover', 'delete');
// Private notes are for HR only (employees reading their own records do not see them)
if (!$permissiontoreadall) {
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
if (empty($object->id) && !in_array($action, array('create', 'add')) && !$permissiontoreadall) {
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
	$backurlforlist = dol_buildpath('/anxhr/handover_list.php', 1);
	if (empty($backtopage) || ($cancel && empty($id))) {
		if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) {
			$backtopage = $backurlforlist;
		} else {
			$backtopage = dol_buildpath('/anxhr/handover_card.php', 1).'?id='.((!empty($id) && $id > 0) ? $id : '__ID__');
		}
	}
	$triggermodname = 'ANXHR_HANDOVER_MODIFY';

	if ($action == 'confirm_setreturned' && $confirm == 'yes' && $permissiontoadd && $object->status == HrHandover::STATUS_OUT) {
		$datereturn = dol_mktime(12, 0, 0, GETPOSTINT('datereturnmonth'), GETPOSTINT('datereturnday'), GETPOSTINT('datereturnyear'));
		if ($object->setReturned($user, $datereturn, GETPOST('condition_return', 'alphanohtml')) > 0) {
			setEventMessages($langs->trans('AnxhrHandoverReturned'), null, 'mesgs');
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		$action = '';
	}
	if ($action == 'confirm_setlost' && $confirm == 'yes' && $permissiontoadd && $object->status == HrHandover::STATUS_OUT) {
		if ($object->setLost($user) < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		$action = '';
	}
	if ($action == 'confirm_reopenhandover' && $confirm == 'yes' && $permissiontoadd && $object->status != HrHandover::STATUS_OUT) {
		if ($object->reopen($user) < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		$action = '';
	}

	// When created from a checklist item, we link the new handover to the item and go back to the checklist
	$actionbeforeinclude = $action;
	$noback = ($action == 'add' && $fk_checklist > 0 && $fk_checklist_item > 0) ? 1 : 0;
	include DOL_DOCUMENT_ROOT.'/core/actions_addupdatedelete.inc.php';

	if ($actionbeforeinclude == 'add' && $noback && empty($error) && $object->id > 0 && $action != 'create') {
		$checklist = new HrChecklist($db);
		if ($checklist->fetch($fk_checklist, null, 1) > 0 && $checklist->linkItemHandover($user, $fk_checklist_item, $object->id) > 0) {
			setEventMessages($langs->trans('AnxhrHandoverLinkedToItem'), null, 'mesgs');
		} else {
			setEventMessages($checklist->error, $checklist->errors, 'warnings');
		}
		header('Location: '.dol_buildpath('/anxhr/checklist_card.php', 1).'?id='.((int) $fk_checklist));
		exit;
	}
}


/*
 * View
 */

$form = new Form($db);

$title = $langs->trans('AnxhrHandover');
if ($action == 'create') {
	$title = $langs->trans('AnxhrNewHandover');
}
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-anxhr page-handover-card');

// Part to create
if ($action == 'create') {
	print load_fiche_titre($title, '', $object->picto);

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';
	if ($fk_checklist > 0 && $fk_checklist_item > 0) {
		print '<input type="hidden" name="fk_checklist" value="'.((int) $fk_checklist).'">';
		print '<input type="hidden" name="fk_checklist_item" value="'.((int) $fk_checklist_item).'">';
	}
	if ($backtopage) {
		print '<input type="hidden" name="backtopage" value="'.dol_escape_htmltag($backtopage).'">';
	}
	if ($backtopageforcancel) {
		print '<input type="hidden" name="backtopageforcancel" value="'.dol_escape_htmltag($backtopageforcancel).'">';
	}
	print dol_get_fiche_head(array(), '');
	print '<table class="border centpercent tableforfieldcreate">'."\n";
	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_add.tpl.php';
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_add.tpl.php';
	print '</table>'."\n";
	print dol_get_fiche_end();
	print $form->buttonsSaveCancel('Create');
	print '</form>';
}

// Part to edit record
if (($id || $ref) && $action == 'edit') {
	print load_fiche_titre($langs->trans('AnxhrHandover'), '', $object->picto);

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="id" value="'.((int) $object->id).'">';
	if ($backtopage) {
		print '<input type="hidden" name="backtopage" value="'.dol_escape_htmltag($backtopage).'">';
	}
	print dol_get_fiche_head();
	print '<table class="border centpercent tableforfieldedit">'."\n";
	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_edit.tpl.php';
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_edit.tpl.php';
	print '</table>';
	print dol_get_fiche_end();
	print $form->buttonsSaveCancel();
	print '</form>';
}

// Part to show record
if ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'create'))) {
	$formconfirm = '';
	if ($action == 'delete') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('AnxhrDeleteHandover'), $langs->trans('AnxhrConfirmDeleteHandover'), 'confirm_delete', '', 0, 1);
	}
	if ($action == 'setreturned') {
		$formquestion = array(
			array('type' => 'date', 'name' => 'datereturn', 'label' => $langs->trans('AnxhrDateReturn'), 'value' => dol_now()),
			array('type' => 'text', 'name' => 'condition_return', 'label' => $langs->trans('AnxhrConditionReturn'), 'value' => '', 'morecss' => 'minwidth300'),
		);
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('AnxhrSetReturned'), $langs->trans('AnxhrConfirmSetReturned', $object->ref), 'confirm_setreturned', $formquestion, 'yes', 1, 250);
	}
	if ($action == 'setlost') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('AnxhrSetLost'), $langs->trans('AnxhrConfirmSetLost', $object->ref), 'confirm_setlost', '', 0, 1);
	}
	if ($action == 'reopenhandover') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('ReOpen'), $langs->trans('AnxhrConfirmReopenHandover', $object->ref), 'confirm_reopenhandover', '', 0, 1);
	}
	$parameters = array('formConfirm' => $formconfirm);
	$reshook = $hookmanager->executeHooks('formConfirm', $parameters, $object, $action);
	if (empty($reshook)) {
		$formconfirm .= $hookmanager->resPrint;
	} elseif ($reshook > 0) {
		$formconfirm = $hookmanager->resPrint;
	}
	print $formconfirm;

	$head = anxhrHandoverPrepareHead($object);
	print dol_get_fiche_head($head, ($tab == 'info' ? 'info' : 'card'), $langs->trans('AnxhrHandover'), -1, $object->picto, 0, '', '', 0, '', 1);

	$linkback = '<a href="'.dol_buildpath('/anxhr/handover_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
	$employees = anxhrLoadUsersCache($db, array((int) $object->fk_user));
	$morehtmlref = '<div class="refidno">'.dol_escape_htmltag($object->label);
	if (!empty($employees[(int) $object->fk_user])) {
		$morehtmlref .= '<br>'.$employees[(int) $object->fk_user]->getNomUrl(-1);
	}
	$morehtmlref .= '</div>';
	dol_banner_tab($object, 'ref', $linkback, ($permissiontoreadall ? 1 : 0), 'ref', 'ref', $morehtmlref);

	print '<div class="fichecenter">';
	if ($tab == 'info') {
		print '<div class="underbanner clearboth"></div>';
		$object->info($object->id);
		print '<br>';
		dol_print_object_info($object, 1);
	} else {
		// Fields of access rights are only relevant for category ACCESS
		if ($object->category != 'ACCESS' && empty($object->system_name) && empty($object->role_name)) {
			unset($object->fields['system_name'], $object->fields['role_name']);
		}
		print '<div class="fichehalfleft">';
		print '<div class="underbanner clearboth"></div>';
		print '<table class="border centpercent tableforfield">'."\n";
		$keyforbreak = 'date_out';
		include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_view.tpl.php';
		include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_view.tpl.php';
		print '</table>';
		print '</div>';
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
		if ($object->status == HrHandover::STATUS_OUT) {
			$labelreturn = ($object->category == 'ACCESS' ? $langs->trans('AnxhrSetRevoked') : $langs->trans('AnxhrSetReturned'));
			print dolGetButtonAction('', $labelreturn, 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=setreturned&token='.newToken(), '', $permissiontoadd);
			print dolGetButtonAction('', $langs->trans('AnxhrSetLost'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=setlost&token='.newToken(), '', $permissiontoadd);
		} else {
			print dolGetButtonAction('', $langs->trans('ReOpen'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=reopenhandover&token='.newToken(), '', $permissiontoadd);
		}
		print dolGetButtonAction('', $langs->trans('Delete'), 'delete', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete&token='.newToken(), '', $permissiontodelete);
	}
	print '</div>'."\n";
}

llxFooter();
$db->close();
