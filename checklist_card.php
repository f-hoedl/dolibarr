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
 * \file        htdocs/custom/anxhr/checklist_card.php
 * \ingroup     anxhr
 * \brief       Page to create/view an onboarding/offboarding checklist and work on its items
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
$lineid = GETPOSTINT('lineid');
$backtopage = GETPOST('backtopage', 'alpha');
$backtopageforcancel = GETPOST('backtopageforcancel', 'alpha');

// Initialize technical objects
$object = new HrChecklist($db);
$extrafields = new ExtraFields($db);
$hookmanager->initHooks(array('anxhrchecklistcard', 'globalcard'));
$extrafields->fetch_name_optionals_label($object->table_element);

if (empty($action) && empty($id) && empty($ref)) {
	$action = 'view';
}

include DOL_DOCUMENT_ROOT.'/core/actions_fetchobject.inc.php';

// Permissions
$permissiontoreadall = $user->hasRight('anxhr', 'checklist', 'read');
$permissiontoread = anxhrHrCanRead($user, 'checklist', ($object->id > 0 ? (int) $object->fk_user : 0));
$permissiontoadd = $user->hasRight('anxhr', 'checklist', 'write');
$permissiontodelete = $user->hasRight('anxhr', 'checklist', 'delete');

// A user responsible of at least one item may see the checklist and work on his items
$currentitem = null;
$isresponsible = false;
if ($object->id > 0) {
	foreach ($object->lines as $line) {
		if ($line->fk_user_responsible == $user->id) {
			$isresponsible = true;
		}
		if ($lineid > 0 && $line->id == $lineid) {
			$currentitem = $line;
		}
	}
}

// Security check
if (!isModEnabled('anxhr')) {
	accessforbidden('Module anxhr not enabled');
}
if ($user->socid > 0) {
	accessforbidden();
}
if (in_array($action, array('create', 'add')) && !$permissiontoadd) {
	accessforbidden();
}
if ($object->id > 0 && !$permissiontoread && !$isresponsible) {
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
	$backurlforlist = dol_buildpath('/anxhr/checklist_list.php', 1);
	if (empty($backtopage) || ($cancel && empty($id))) {
		if (empty($id) && (($action != 'add' && $action != 'create') || $cancel)) {
			$backtopage = $backurlforlist;
		} else {
			$backtopage = dol_buildpath('/anxhr/checklist_card.php', 1).'?id='.((!empty($id) && $id > 0) ? $id : '__ID__');
		}
	}
	$triggermodname = 'ANXHR_CHECKLIST_MODIFY';

	// Change status of an item (POST only): allowed with write right or for the responsible of the item
	$itemactions = array('itemdone' => HrChecklistItem::STATUS_DONE, 'itemskip' => HrChecklistItem::STATUS_SKIPPED, 'itemreopen' => HrChecklistItem::STATUS_OPEN);
	if (isset($itemactions[$action]) && $_SERVER['REQUEST_METHOD'] == 'POST' && is_object($currentitem)) {
		$canitem = ($permissiontoadd || $currentitem->fk_user_responsible == $user->id);
		if ($canitem && $object->status != HrChecklist::STATUS_CANCELED) {
			if ($object->setItemStatus($user, $currentitem->id, $itemactions[$action]) < 0) {
				setEventMessages($object->error, $object->errors, 'errors');
			}
		} else {
			setEventMessages($langs->trans('NotEnoughPermissions'), null, 'errors');
		}
		header('Location: '.$_SERVER['PHP_SELF'].'?id='.((int) $object->id).'#item_'.((int) $currentitem->id));
		exit;
	}

	// Add an item
	if ($action == 'additem' && $permissiontoadd && $object->id > 0) {
		$item = new HrChecklistItem($db);
		$item->label = GETPOST('item_label', 'alphanohtml');
		$item->responsible_role = GETPOST('item_role', 'aZ09');
		$item->fk_user_responsible = (GETPOSTINT('item_fk_user_responsible') > 0 ? GETPOSTINT('item_fk_user_responsible') : null);
		$item->date_due = dol_mktime(12, 0, 0, GETPOSTINT('item_date_duemonth'), GETPOSTINT('item_date_dueday'), GETPOSTINT('item_date_dueyear'));
		if (empty($item->label)) {
			setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Label')), null, 'errors');
			$action = '';
		} elseif ($object->addItem($user, $item) > 0 && $object->recomputeProgress($user) >= 0) {
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.((int) $object->id));
			exit;
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
			$action = '';
		}
	}

	// Delete an item
	if ($action == 'confirm_deleteitem' && $confirm == 'yes' && $permissiontoadd && is_object($currentitem)) {
		if ($currentitem->delete($user) > 0 && $object->recomputeProgress($user) >= 0) {
			header('Location: '.$_SERVER['PHP_SELF'].'?id='.((int) $object->id));
			exit;
		}
		setEventMessages($currentitem->error, $currentitem->errors, 'errors');
		$action = '';
	}

	if ($action == 'confirm_cancelchecklist' && $confirm == 'yes' && $permissiontoadd) {
		if ($object->cancel($user) < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		$action = '';
	}
	if ($action == 'confirm_reopenchecklist' && $confirm == 'yes' && $permissiontoadd) {
		if ($object->reopen($user) < 0) {
			setEventMessages($object->error, $object->errors, 'errors');
		}
		$action = '';
	}

	// Standard actions (add with instantiation from template, delete)
	if (in_array($action, array('edit', 'update'))) {
		$action = '';	// Checklists are not edited as a whole, only items are processed
	}
	include DOL_DOCUMENT_ROOT.'/core/actions_addupdatedelete.inc.php';
}


/*
 * View
 */

$form = new Form($db);

$title = $langs->trans('AnxhrChecklist');
if ($action == 'create') {
	$title = $langs->trans('AnxhrNewChecklist');
}
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-anxhr page-checklist-card');

// Part to create
if ($action == 'create') {
	print load_fiche_titre($title, '', $object->picto);

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
	include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_add.tpl.php';
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_add.tpl.php';
	print '</table>'."\n";
	print dol_get_fiche_end();
	print $form->buttonsSaveCancel('Create');
	print '</form>';
}

// Part to show record
if ($object->id > 0 && $action != 'create') {
	$formconfirm = '';
	if ($action == 'delete') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('AnxhrDeleteChecklist'), $langs->trans('AnxhrConfirmDeleteChecklist'), 'confirm_delete', '', 0, 1);
	}
	if ($action == 'deleteitem' && is_object($currentitem)) {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id.'&lineid='.$currentitem->id, $langs->trans('DeleteLine'), $langs->trans('ConfirmDeleteLine'), 'confirm_deleteitem', '', 0, 1);
	}
	if ($action == 'cancelchecklist') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('Cancel'), $langs->trans('AnxhrConfirmCancelChecklist', $object->ref), 'confirm_cancelchecklist', '', 0, 1);
	}
	if ($action == 'reopenchecklist') {
		$formconfirm = $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id, $langs->trans('ReOpen'), $langs->trans('AnxhrConfirmReopenChecklist', $object->ref), 'confirm_reopenchecklist', '', 'yes', 1);
	}
	$parameters = array('formConfirm' => $formconfirm, 'lineid' => $lineid);
	$reshook = $hookmanager->executeHooks('formConfirm', $parameters, $object, $action);
	if (empty($reshook)) {
		$formconfirm .= $hookmanager->resPrint;
	} elseif ($reshook > 0) {
		$formconfirm = $hookmanager->resPrint;
	}
	print $formconfirm;

	$head = anxhrChecklistPrepareHead($object);
	print dol_get_fiche_head($head, ($tab == 'info' ? 'info' : 'card'), $langs->trans('AnxhrChecklist'), -1, $object->picto, 0, '', '', 0, '', 1);

	// Users used on this page (employee, responsibles, done by) loaded with one query
	$userids = array((int) $object->fk_user);
	$handoverids = array();
	foreach ($object->lines as $line) {
		$userids[] = (int) $line->fk_user_responsible;
		$userids[] = (int) $line->fk_user_done;
		if ($line->fk_handover > 0) {
			$handoverids[] = (int) $line->fk_handover;
		}
	}
	$userscache = anxhrLoadUsersCache($db, $userids);

	$linkback = '<a href="'.dol_buildpath('/anxhr/checklist_list.php', 1).'?restore_lastsearch_values=1">'.$langs->trans('BackToList').'</a>';
	$morehtmlref = '<div class="refidno">';
	if (!empty($userscache[(int) $object->fk_user])) {
		$morehtmlref .= $userscache[(int) $object->fk_user]->getNomUrl(-1);
	}
	$morehtmlref .= '<br>'.anxhrHrProgressBar((int) $object->progress_pct);
	$morehtmlref .= '</div>';
	dol_banner_tab($object, 'ref', $linkback, ($permissiontoreadall ? 1 : 0), 'ref', 'ref', $morehtmlref);

	print '<div class="fichecenter">';
	if ($tab == 'info') {
		print '<div class="underbanner clearboth"></div>';
		$object->info($object->id);
		print '<br>';
		dol_print_object_info($object, 1);
	} else {
		print '<div class="fichehalfleft">';
		print '<div class="underbanner clearboth"></div>';
		print '<table class="border centpercent tableforfield">'."\n";
		$keyforbreak = 'anchor_date';
		include DOL_DOCUMENT_ROOT.'/core/tpl/commonfields_view.tpl.php';
		include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_view.tpl.php';
		print '</table>';
		print '</div>';
	}
	print '</div>';
	print '<div class="clearboth"></div>';
	print dol_get_fiche_end();

	if ($tab != 'info') {
		// Handovers linked to items (one query)
		$handovers = array();
		if (!empty($handoverids)) {
			dol_include_once('/anxhr/class/hrhandover.class.php');
			$sql = "SELECT h.rowid, h.ref, h.label, h.status, h.category FROM ".$db->prefix()."anxhr_handover as h";
			$sql .= " WHERE h.rowid IN (".$db->sanitize(implode(',', array_unique($handoverids))).")";
			$resql = $db->query($sql);
			if ($resql) {
				while ($obj = $db->fetch_object($resql)) {
					$ho = new HrHandover($db);
					$ho->id = (int) $obj->rowid;
					$ho->ref = $obj->ref;
					$ho->label = $obj->label;
					$ho->status = (int) $obj->status;
					$ho->category = $obj->category;
					$handovers[$ho->id] = $ho;
				}
				$db->free($resql);
			}
		}

		$itemstatic = new HrChecklistItem($db);
		$roles = $itemstatic->fields['responsible_role']['arrayofkeyval'];
		$caneditlist = ($object->status != HrChecklist::STATUS_CANCELED);

		print load_fiche_titre($langs->trans('AnxhrChecklistItems'), '', 'fa-tasks');
		print '<div class="div-table-responsive-no-min">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre">';
		print '<th class="center width100">'.$langs->trans('Status').'</th>';
		print '<th>'.$langs->trans('Label').'</th>';
		print '<th class="hideonsmartphone">'.$langs->trans('AnxhrResponsible').'</th>';
		print '<th class="center">'.$langs->trans('AnxhrDateDue').'</th>';
		print '<th class="center hideonsmartphone">'.$langs->trans('AnxhrDateDone').'</th>';
		print '<th class="right">'.$langs->trans('Action').'</th>';
		print '</tr>';

		if (empty($object->lines)) {
			print '<tr><td colspan="6"><span class="opacitymedium">'.$langs->trans('AnxhrNoItems').'</span></td></tr>';
		}
		foreach ($object->lines as $line) {
			$canitem = $caneditlist && ($permissiontoadd || $line->fk_user_responsible == $user->id);
			print '<tr class="oddeven" id="item_'.((int) $line->id).'">';
			print '<td class="center">'.$line->getLibStatut(5).'</td>';

			// Label and linked handover
			print '<td class="tdoverflowmax400">';
			print '<span class="'.($line->status != HrChecklistItem::STATUS_OPEN ? 'opacitymedium' : '').'">'.dol_escape_htmltag($line->label).'</span>';
			if ($line->fk_handover > 0 && isset($handovers[(int) $line->fk_handover])) {
				print '<br>'.$handovers[(int) $line->fk_handover]->getNomUrl(1).' '.$handovers[(int) $line->fk_handover]->getLibStatut(3);
			}
			if (!empty($line->note)) {
				print '<br><span class="small opacitymedium">'.dolPrintHTML($line->note).'</span>';
			}
			print '</td>';

			// Responsible
			print '<td class="hideonsmartphone tdoverflowmax200">';
			if (!empty($line->responsible_role) && isset($roles[$line->responsible_role])) {
				print '<span class="badge badge-secondary">'.dol_escape_htmltag($roles[$line->responsible_role]).'</span> ';
			}
			if ($line->fk_user_responsible > 0 && isset($userscache[(int) $line->fk_user_responsible])) {
				print $userscache[(int) $line->fk_user_responsible]->getNomUrl(-1);
			}
			print '</td>';

			print '<td class="center nowraponall">'.dol_print_date($line->date_due, 'day').($line->isLate() ? ' '.img_warning($langs->trans('AnxhrOverdue')) : '').'</td>';
			print '<td class="center hideonsmartphone nowraponall">';
			if (!empty($line->date_done)) {
				print dol_print_date($line->date_done, 'dayhour', 'tzuserrel');
				if ($line->fk_user_done > 0 && isset($userscache[(int) $line->fk_user_done])) {
					print '<br>'.$userscache[(int) $line->fk_user_done]->getNomUrl(-1, '', 0, 0, 16);
				}
			}
			print '</td>';

			// Actions (POST forms with token)
			print '<td class="right nowraponall">';
			if ($canitem) {
				if ($line->status == HrChecklistItem::STATUS_OPEN) {
					if (!empty($line->creates_handover) && empty($line->fk_handover) && $user->hasRight('anxhr', 'handover', 'write')) {
						$urlhandover = dol_buildpath('/anxhr/handover_card.php', 1).'?action=create&fk_user='.((int) $object->fk_user).'&category='.urlencode((string) $line->handover_category).'&label='.urlencode((string) $line->label).'&fk_checklist='.((int) $object->id).'&fk_checklist_item='.((int) $line->id).'&backtopageforcancel='.urlencode($_SERVER['PHP_SELF'].'?id='.$object->id);
						print '<a class="button smallpaddingimp" href="'.$urlhandover.'">'.img_picto('', 'fa-laptop', 'class="pictofixedwidth"').$langs->trans('AnxhrCreateHandover').'</a> ';
					}
					print anxhrItemActionButton($object->id, $line->id, 'itemdone', $langs->trans('AnxhrItemDone'), 'fa-check');
					print anxhrItemActionButton($object->id, $line->id, 'itemskip', $langs->trans('AnxhrItemSkip'), 'fa-forward');
				} else {
					print anxhrItemActionButton($object->id, $line->id, 'itemreopen', $langs->trans('ReOpen'), 'fa-undo');
				}
			}
			if ($permissiontoadd) {
				print '<a class="reposition marginleftonly" href="'.$_SERVER['PHP_SELF'].'?id='.((int) $object->id).'&lineid='.((int) $line->id).'&action=deleteitem&token='.newToken().'">'.img_delete().'</a>';
			}
			print '</td>';
			print '</tr>';
		}

		// Form to add an item
		if ($permissiontoadd && $caneditlist) {
			print '<tr class="liste_titre nodrag nodrop"><td colspan="6">'.$langs->trans('AnxhrAddItem').'</td></tr>';
			print '<tr class="oddeven nohover"><td colspan="6">';
			print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.((int) $object->id).'" class="inline-block centpercent">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="action" value="additem">';
			print '<input type="text" name="item_label" class="flat minwidth300 maxwidth500 marginrightonly" placeholder="'.dol_escape_htmltag($langs->trans('Label')).'" value="">';
			print $form->selectarray('item_role', $roles, 'hr', 0, 0, 0, '', 0, 0, 0, '', 'maxwidth150 marginrightonly');
			print $form->select_dolusers(-1, 'item_fk_user_responsible', 1, null, 0, '', '', '', 0, 0, '', 0, '', 'maxwidth200 marginrightonly');
			print $form->selectDate(-1, 'item_date_due', 0, 0, 1, '', 1, 0);
			print ' <input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Add')).'">';
			print '</form>';
			print '</td></tr>';
		}
		print '</table>';
		print '</div>';
	}

	// Buttons for actions
	print '<div class="tabsAction">'."\n";
	$parameters = array();
	$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action);
	if ($reshook < 0) {
		setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
	}
	if (empty($reshook)) {
		if ($object->status == HrChecklist::STATUS_CANCELED) {
			print dolGetButtonAction('', $langs->trans('ReOpen'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=reopenchecklist&token='.newToken(), '', $permissiontoadd);
		} else {
			print dolGetButtonAction('', $langs->trans('Cancel'), 'default', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=cancelchecklist&token='.newToken(), '', $permissiontoadd);
		}
		print dolGetButtonAction('', $langs->trans('Delete'), 'delete', $_SERVER["PHP_SELF"].'?id='.$object->id.'&action=delete&token='.newToken(), '', $permissiontodelete);
	}
	print '</div>'."\n";
}

llxFooter();
$db->close();

