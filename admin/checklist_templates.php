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
 * \file        htdocs/custom/anxhr/admin/checklist_templates.php
 * \ingroup     anxhr
 * \brief       Setup page to manage checklist templates (onboarding, offboarding, transfer) and their items
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once __DIR__.'/../lib/anxhr.lib.php';
require_once __DIR__.'/../lib/anxhr_hr.lib.php';
require_once __DIR__.'/../class/hrchecklisttemplate.class.php';
require_once __DIR__.'/../class/hrhandover.class.php';

$langs->loadLangs(array('admin', 'anxhr@anxhr', 'anxhr_hr@anxhr'));

// Get parameters
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$templateid = GETPOSTINT('templateid');
$itemid = GETPOSTINT('itemid');

// Security check
if (!isModEnabled('anxhr')) {
	accessforbidden('Module anxhr not enabled');
}
$permissiontoedit = ($user->admin || $user->hasRight('anxhr', 'setup', 'admin'));
if (!$permissiontoedit) {
	accessforbidden();
}

$hookmanager->initHooks(array('anxhrchecklisttemplatesetup', 'globalsetup'));

$template = new HrChecklistTemplate($db);
if ($templateid > 0 && $template->fetch($templateid) <= 0) {
	setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
	$templateid = 0;
}
$itemstatic = new HrChecklistTemplateItem($db);
$roles = array();
foreach ($itemstatic->fields['responsible_role']['arrayofkeyval'] as $code => $labelkey) {
	$roles[$code] = $langs->trans($labelkey);
}
$handoverstatic = new HrHandover($db);
$handovercategories = $handoverstatic->fields['category']['arrayofkeyval'];
$types = $template->fields['template_type']['arrayofkeyval'];
$selfurl = $_SERVER['PHP_SELF'];
$error = 0;


/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $template, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	// Add a template
	if ($action == 'addtemplate') {
		$newtpl = new HrChecklistTemplate($db);
		$newtpl->ref = GETPOST('tpl_ref', 'alphanohtml');
		$newtpl->label = GETPOST('tpl_label', 'alphanohtml');
		$newtpl->template_type = GETPOST('tpl_type', 'aZ09');
		$newtpl->status = HrChecklistTemplate::STATUS_ENABLED;
		if (empty($newtpl->ref) || empty($newtpl->label) || !isset($types[$newtpl->template_type])) {
			setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Code').', '.$langs->transnoentitiesnoconv('Label')), null, 'errors');
		} elseif ($newtpl->create($user) > 0) {
			header('Location: '.$selfurl.'?templateid='.((int) $newtpl->id));
			exit;
		} else {
			setEventMessages($newtpl->error, $newtpl->errors, 'errors');
		}
		$action = '';
	}

	// Update header of template
	if ($action == 'updatetemplate' && $template->id > 0) {
		$template->label = GETPOST('tpl_label', 'alphanohtml');
		$type = GETPOST('tpl_type', 'aZ09');
		if (isset($types[$type])) {
			$template->template_type = $type;
		}
		$template->status = (GETPOSTINT('tpl_status') ? HrChecklistTemplate::STATUS_ENABLED : HrChecklistTemplate::STATUS_DISABLED);
		if (empty($template->label)) {
			setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Label')), null, 'errors');
		} elseif ($template->update($user) > 0) {
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
		} else {
			setEventMessages($template->error, $template->errors, 'errors');
		}
		$action = '';
	}

	// Enable / disable a template
	if (in_array($action, array('enabletemplate', 'disabletemplate')) && $template->id > 0) {
		$template->status = ($action == 'enabletemplate' ? HrChecklistTemplate::STATUS_ENABLED : HrChecklistTemplate::STATUS_DISABLED);
		if ($template->update($user) < 0) {
			setEventMessages($template->error, $template->errors, 'errors');
		}
		$action = '';
	}

	// Delete a template and its items
	if ($action == 'confirm_deletetemplate' && $confirm == 'yes' && $template->id > 0) {
		if ($template->delete($user) > 0) {
			setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
			header('Location: '.$selfurl);
			exit;
		}
		setEventMessages($template->error, $template->errors, 'errors');
		$action = '';
	}

	// Save all items of template (inline edition) and add the new item if a label is provided
	if ($action == 'saveitems' && $template->id > 0) {
		$labels = GETPOST('item_label', 'array:alphanohtml');
		$positions = GETPOST('item_position', 'array:int');
		$itemroles = GETPOST('item_role', 'array:aZ09');
		$offsets = GETPOST('item_offset', 'array:int');
		$creates = GETPOST('item_creates_handover', 'array:int');
		$categories = GETPOST('item_handover_category', 'array:aZ09');

		$db->begin();
		foreach ($template->lines as $line) {
			if (!isset($labels[$line->id])) {
				continue;
			}
			$line->label = dol_string_nohtmltag((string) $labels[$line->id]);
			$line->position = isset($positions[$line->id]) ? (int) $positions[$line->id] : $line->position;
			$line->responsible_role = isset($itemroles[$line->id]) ? $itemroles[$line->id] : $line->responsible_role;
			$line->offset_days = isset($offsets[$line->id]) ? (int) $offsets[$line->id] : 0;
			$line->creates_handover = empty($creates[$line->id]) ? 0 : 1;
			$line->handover_category = (isset($categories[$line->id]) && isset($handovercategories[$categories[$line->id]])) ? $categories[$line->id] : '';
			if ($line->label === '') {
				setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Label')), null, 'errors');
				$error++;
				break;
			}
			if ($line->update($user) < 0) {
				setEventMessages($line->error, $line->errors, 'errors');
				$error++;
				break;
			}
		}
		$newlabel = GETPOST('new_label', 'alphanohtml');
		if (!$error && $newlabel !== '') {
			$newitem = new HrChecklistTemplateItem($db);
			$newitem->label = $newlabel;
			$newitem->position = GETPOSTINT('new_position');
			$newitem->responsible_role = GETPOST('new_role', 'aZ09');
			$newitem->offset_days = GETPOSTINT('new_offset');
			$newitem->creates_handover = GETPOSTINT('new_creates_handover') ? 1 : 0;
			$newcategory = GETPOST('new_handover_category', 'aZ09');
			$newitem->handover_category = isset($handovercategories[$newcategory]) ? $newcategory : '';
			if ($template->addItem($user, $newitem) < 0) {
				setEventMessages($template->error, $template->errors, 'errors');
				$error++;
			}
		}
		if ($error) {
			$db->rollback();
		} else {
			$db->commit();
			setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
			header('Location: '.$selfurl.'?templateid='.((int) $template->id));
			exit;
		}
		$action = '';
	}

	// Delete an item
	if ($action == 'confirm_deleteitem' && $confirm == 'yes' && $template->id > 0 && $itemid > 0) {
		foreach ($template->lines as $line) {
			if ($line->id == $itemid) {
				if ($line->delete($user) > 0) {
					header('Location: '.$selfurl.'?templateid='.((int) $template->id));
					exit;
				}
				setEventMessages($line->error, $line->errors, 'errors');
			}
		}
		$action = '';
	}
}


/*
 * View
 */

$form = new Form($db);

$title = $langs->trans('AnxhrAdminChecklistTemplates');
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-anxhr page-admin-checklisttemplates');

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans('BackToModuleList').'</a>';
print load_fiche_titre($langs->trans('AnxhrSetup'), $linkback, 'title_setup');

$head = anxhrAdminPrepareHead();
print dol_get_fiche_head($head, 'checklist_templates', $langs->trans('AnxhrSetup'), -1, 'fa-users');

// Confirmations
if ($action == 'deletetemplate' && $template->id > 0) {
	print $form->formconfirm($selfurl.'?templateid='.$template->id, $langs->trans('AnxhrDeleteChecklistTemplate'), $langs->trans('AnxhrConfirmDeleteChecklistTemplate', $template->ref), 'confirm_deletetemplate', '', 0, 1);
}
if ($action == 'deleteitem' && $template->id > 0 && $itemid > 0) {
	print $form->formconfirm($selfurl.'?templateid='.$template->id.'&itemid='.$itemid, $langs->trans('DeleteLine'), $langs->trans('ConfirmDeleteLine'), 'confirm_deleteitem', '', 0, 1);
}

print '<span class="opacitymedium">'.$langs->trans('AnxhrChecklistTemplatesDesc').'</span><br><br>';

// Number of items per template (one query)
$nbitems = array();
$sql = "SELECT fk_template, COUNT(rowid) as nb FROM ".$db->prefix()."anxhr_checklist_template_item GROUP BY fk_template";
$resql = $db->query($sql);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$nbitems[(int) $obj->fk_template] = (int) $obj->nb;
	}
	$db->free($resql);
}

// List of templates
$sql = "SELECT t.rowid, t.ref, t.label, t.template_type, t.status FROM ".$db->prefix()."anxhr_checklist_template as t";
$sql .= " WHERE t.entity IN (".getEntity('anxhr_checklist_template').")";
$sql .= $db->order('t.template_type,t.ref', 'ASC,ASC');
$sql .= $db->plimit(500);
$resql = $db->query($sql);

print '<form method="POST" action="'.$selfurl.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="addtemplate">';
print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Code').'</th>';
print '<th>'.$langs->trans('Label').'</th>';
print '<th>'.$langs->trans('Type').'</th>';
print '<th class="center">'.$langs->trans('AnxhrNbItems').'</th>';
print '<th class="center">'.$langs->trans('Status').'</th>';
print '<th class="right"></th>';
print '</tr>';

// Line to add a template
print '<tr class="oddeven nohover">';
print '<td><input type="text" name="tpl_ref" class="flat maxwidth100" value="" placeholder="'.dol_escape_htmltag($langs->trans('Code')).'"></td>';
print '<td><input type="text" name="tpl_label" class="flat minwidth200" value="" placeholder="'.dol_escape_htmltag($langs->trans('Label')).'"></td>';
print '<td>'.$form->selectarray('tpl_type', $types, 'onboarding', 0, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td>';
print '<td></td><td></td>';
print '<td class="right"><input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Add')).'"></td>';
print '</tr>';

$tplstatic = new HrChecklistTemplate($db);
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$tplstatic->id = (int) $obj->rowid;
		$tplstatic->ref = $obj->ref;
		$tplstatic->label = $obj->label;
		$tplstatic->status = (int) $obj->status;
		print '<tr class="oddeven'.($obj->rowid == $templateid ? ' highlight' : '').'">';
		print '<td class="nowraponall"><a href="'.$selfurl.'?templateid='.((int) $obj->rowid).'">'.img_picto('', $tplstatic->picto, 'class="pictofixedwidth"').dol_escape_htmltag($obj->ref).'</a></td>';
		print '<td class="tdoverflowmax300">'.dol_escape_htmltag($obj->label).'</td>';
		print '<td>'.dol_escape_htmltag(isset($types[$obj->template_type]) ? $types[$obj->template_type] : $obj->template_type).'</td>';
		print '<td class="center">'.(isset($nbitems[(int) $obj->rowid]) ? $nbitems[(int) $obj->rowid] : 0).'</td>';
		print '<td class="center">';
		if ($obj->status == HrChecklistTemplate::STATUS_ENABLED) {
			print '<a class="reposition" href="'.$selfurl.'?templateid='.((int) $obj->rowid).'&action=disabletemplate&token='.newToken().'">'.img_picto($langs->trans('Activated'), 'switch_on').'</a>';
		} else {
			print '<a class="reposition" href="'.$selfurl.'?templateid='.((int) $obj->rowid).'&action=enabletemplate&token='.newToken().'">'.img_picto($langs->trans('Disabled'), 'switch_off').'</a>';
		}
		print '</td>';
		print '<td class="right nowraponall">';
		print '<a class="editfielda marginrightonly" href="'.$selfurl.'?templateid='.((int) $obj->rowid).'">'.img_edit($langs->trans('AnxhrEditItems')).'</a>';
		print '<a class="reposition" href="'.$selfurl.'?templateid='.((int) $obj->rowid).'&action=deletetemplate&token='.newToken().'">'.img_delete().'</a>';
		print '</td>';
		print '</tr>';
	}
	$db->free($resql);
} else {
	dol_print_error($db);
}
print '</table>';
print '</div>';
print '</form>';

// Edition of selected template
if ($template->id > 0) {
	print '<br>';
	print load_fiche_titre($langs->trans('AnxhrChecklistTemplate').' : '.dol_escape_htmltag($template->ref), '', $template->picto);

	// Header of template
	print '<form method="POST" action="'.$selfurl.'?templateid='.((int) $template->id).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="updatetemplate">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans('Label').'</th><th>'.$langs->trans('Type').'</th><th class="center">'.$langs->trans('Enabled').'</th><th></th></tr>';
	print '<tr class="oddeven nohover">';
	print '<td><input type="text" name="tpl_label" class="flat minwidth300" value="'.dol_escape_htmltag($template->label).'"></td>';
	print '<td>'.$form->selectarray('tpl_type', $types, $template->template_type, 0, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td>';
	print '<td class="center"><input type="checkbox" name="tpl_status" value="1"'.($template->status == HrChecklistTemplate::STATUS_ENABLED ? ' checked' : '').'></td>';
	print '<td class="right"><input type="submit" class="button smallpaddingimp" value="'.dol_escape_htmltag($langs->trans('Save')).'"></td>';
	print '</tr>';
	print '</table>';
	print '</form>';
	print '<br>';

	// Items of template (inline edition)
	print '<form method="POST" action="'.$selfurl.'?templateid='.((int) $template->id).'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="saveitems">';
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<th class="center width50">'.$langs->trans('Position').'</th>';
	print '<th>'.$langs->trans('Label').'</th>';
	print '<th>'.$langs->trans('AnxhrResponsibleRole').'</th>';
	print '<th class="center">'.$form->textwithpicto($langs->trans('AnxhrOffsetDays'), $langs->trans('AnxhrOffsetDaysHelp')).'</th>';
	print '<th class="center">'.$form->textwithpicto($langs->trans('AnxhrCreatesHandover'), $langs->trans('AnxhrCreatesHandoverHelp')).'</th>';
	print '<th>'.$langs->trans('AnxhrHandoverCategory').'</th>';
	print '<th class="right"></th>';
	print '</tr>';

	foreach ($template->lines as $line) {
		$lid = (int) $line->id;
		print '<tr class="oddeven">';
		print '<td class="center"><input type="number" name="item_position['.$lid.']" class="flat width50" value="'.((int) $line->position).'"></td>';
		print '<td><input type="text" name="item_label['.$lid.']" class="flat minwidth300 widthcentpercentminusx" value="'.dol_escape_htmltag($line->label).'"></td>';
		print '<td>'.$form->selectarray('item_role['.$lid.']', $roles, $line->responsible_role, 0, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td>';
		print '<td class="center"><input type="number" name="item_offset['.$lid.']" class="flat width50" value="'.((int) $line->offset_days).'"></td>';
		print '<td class="center"><input type="checkbox" name="item_creates_handover['.$lid.']" value="1"'.(!empty($line->creates_handover) ? ' checked' : '').'></td>';
		print '<td>'.$form->selectarray('item_handover_category['.$lid.']', $handovercategories, $line->handover_category, 1, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td>';
		print '<td class="right"><a class="reposition" href="'.$selfurl.'?templateid='.((int) $template->id).'&itemid='.$lid.'&action=deleteitem&token='.newToken().'">'.img_delete().'</a></td>';
		print '</tr>';
	}
	if (empty($template->lines)) {
		print '<tr><td colspan="7"><span class="opacitymedium">'.$langs->trans('AnxhrNoItems').'</span></td></tr>';
	}

	// New item
	print '<tr class="liste_titre"><td colspan="7">'.$langs->trans('AnxhrAddItem').'</td></tr>';
	print '<tr class="oddeven nohover">';
	print '<td class="center"><input type="number" name="new_position" class="flat width50" value=""></td>';
	print '<td><input type="text" name="new_label" class="flat minwidth300 widthcentpercentminusx" value="" placeholder="'.dol_escape_htmltag($langs->trans('Label')).'"></td>';
	print '<td>'.$form->selectarray('new_role', $roles, 'hr', 0, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td>';
	print '<td class="center"><input type="number" name="new_offset" class="flat width50" value="0"></td>';
	print '<td class="center"><input type="checkbox" name="new_creates_handover" value="1"></td>';
	print '<td>'.$form->selectarray('new_handover_category', $handovercategories, '', 1, 0, 0, '', 0, 0, 0, '', 'maxwidth150').'</td>';
	print '<td></td>';
	print '</tr>';
	print '</table>';
	print '</div>';
	print '<div class="center"><input type="submit" class="button button-save" value="'.dol_escape_htmltag($langs->trans('Save')).'"></div>';
	print '</form>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
