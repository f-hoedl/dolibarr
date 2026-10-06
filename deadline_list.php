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
 * \file        htdocs/custom/anxhr/deadline_list.php
 * \ingroup     anxhr
 * \brief       List page of HR deadlines
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once('/anxhr/lib/anxhr_hr.lib.php');

// BEGIN LIST CONFIGURATION (only this block differs between the HR list pages)
dol_include_once('/anxhr/class/hrdeadline.class.php');
$objectclass = 'HrDeadline';
$objectlabel = 'AnxhrDeadline';
$permkey = 'deadline';
$cardpath = '/anxhr/deadline_card.php';
$titlekey = 'AnxhrDeadlines';
$exportname = 'deadlines';
$defaultsortfield = 't.date_due';
$defaultsortorder = 'ASC';
$defaultsearch = array('status' => '0');	// Open deadlines by default, badges show urgency
// END LIST CONFIGURATION

$langs->loadLangs(array('anxhr@anxhr', 'anxhr_hr@anxhr', 'users', 'other'));

// Get parameters
$action = GETPOST('action', 'aZ09') ? GETPOST('action', 'aZ09') : 'view';
$massaction = GETPOST('massaction', 'alpha');
$confirm = GETPOST('confirm', 'alpha');
$cancel = GETPOST('cancel', 'alpha');
$toselect = GETPOST('toselect', 'array:int');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : getDolDefaultContextPage(__FILE__);
$backtopage = GETPOST('backtopage', 'alpha');
$optioncss = GETPOST('optioncss', 'aZ');
$mode = GETPOST('mode', 'aZ');

$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$page = 0;
}
$offset = $limit * $page;

// Initialize technical objects
$object = new $objectclass($db);
$extrafields = new ExtraFields($db);
$hookmanager->initHooks(array($contextpage));
$extrafields->fetch_name_optionals_label($object->table_element);
$search_array_options = $extrafields->getOptionalsFromPost($object->table_element, '', 'search_');

if (!$sortfield) {
	$sortfield = $defaultsortfield;
}
if (!$sortorder) {
	$sortorder = $defaultsortorder;
}

// Initialize array of search criteria
$search_all = trim(GETPOST('search_all', 'alphanohtml'));
$search = array();
$hassearchparam = false;
foreach ($object->fields as $key => $val) {
	if (GETPOSTISSET('search_'.$key)) {
		$hassearchparam = true;
	}
	if (GETPOST('search_'.$key, 'alpha') !== '') {
		$search[$key] = GETPOST('search_'.$key, 'alpha');
	}
	if (preg_match('/^(date|timestamp|datetime)/', $val['type'])) {
		$search[$key.'_dtstart'] = dol_mktime(0, 0, 0, GETPOSTINT('search_'.$key.'_dtstartmonth'), GETPOSTINT('search_'.$key.'_dtstartday'), GETPOSTINT('search_'.$key.'_dtstartyear'));
		$search[$key.'_dtend'] = dol_mktime(23, 59, 59, GETPOSTINT('search_'.$key.'_dtendmonth'), GETPOSTINT('search_'.$key.'_dtendday'), GETPOSTINT('search_'.$key.'_dtendyear'));
	}
}
// Default filters when page is opened without any search criteria
if (!$hassearchparam && !GETPOST('button_removefilter', 'alpha') && !GETPOST('button_removefilter_x', 'alpha')) {
	foreach ($defaultsearch as $key => $val) {
		$search[$key] = $val;
	}
}

// List of fields to search into when doing a "search in all"
$fieldstosearchall = array();
foreach ($object->fields as $key => $val) {
	if (!empty($val['searchall'])) {
		$fieldstosearchall['t.'.$key] = $val['label'];
	}
}

// Definition of array of fields for columns
$arrayfields = array();
foreach ($object->fields as $key => $val) {
	$visible = (int) dol_eval((string) $val['visible'], 1);
	// Fields visible only on forms (3) or never (0) are not proposed as columns (sensitive data such as disability_status)
	if ($visible != 0 && abs($visible) != 3) {
		$arrayfields['t.'.$key] = array(
			'label' => $val['label'],
			'checked' => (($visible < 0) ? '0' : '1'),
			'enabled' => (string) (int) ((bool) dol_eval((string) $val['enabled'], 1)),
			'position' => $val['position'],
			'help' => isset($val['help']) ? $val['help'] : ''
		);
	}
}
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_array_fields.tpl.php';
$parameters = array('arrayfields' => &$arrayfields);
$reshook = $hookmanager->executeHooks('completeArrayFields', $parameters, $object, $action);
$object->fields = dol_sort_array($object->fields, 'position');
$arrayfields = dol_sort_array($arrayfields, 'position');

// Permissions
$permissiontoreadall = $user->hasRight('anxhr', $permkey, 'read');
$permissiontoreadown = $user->hasRight('anxhr', 'employee', 'read_own');
$permissiontoread = ($permissiontoreadall || $permissiontoreadown);
$permissiontoadd = $user->hasRight('anxhr', $permkey, 'write');
$permissiontodelete = $user->hasRight('anxhr', $permkey, 'delete');
$permissiontoexport = $permissiontoreadall;

// Security check
if (!isModEnabled('anxhr')) {
	accessforbidden('Module anxhr not enabled');
}
if ($user->socid > 0 || !$permissiontoread) {
	accessforbidden();
}


/*
 * Actions
 */

if (GETPOST('cancel', 'alpha')) {
	$action = 'list';
	$massaction = '';
}
if (!GETPOST('confirmmassaction', 'alpha') && $massaction != 'presend' && $massaction != 'confirm_presend') {
	$massaction = '';
}

$parameters = array('arrayfields' => &$arrayfields);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	// Selection of new fields
	include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

	// Purge search criteria
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
		foreach ($object->fields as $key => $val) {
			$search[$key] = '';
			if (preg_match('/^(date|timestamp|datetime)/', $val['type'])) {
				$search[$key.'_dtstart'] = '';
				$search[$key.'_dtend'] = '';
			}
		}
		$search_all = '';
		$toselect = array();
		$search_array_options = array();
	}
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')
		|| GETPOST('button_search_x', 'alpha') || GETPOST('button_search.x', 'alpha') || GETPOST('button_search', 'alpha')) {
		$massaction = '';
	}

	// Mass actions (delete)
	$uploaddir = (!empty($conf->anxhr->dir_output) ? $conf->anxhr->dir_output : DOL_DATA_ROOT.'/anxhr');
	global $error;
	include DOL_DOCUMENT_ROOT.'/core/actions_massactions.inc.php';
}


/*
 * View
 */

$form = new Form($db);
$now = dol_now();
$title = $langs->trans($titlekey);

// Build and execute select
$sql = "SELECT ".$object->getFieldList('t');
if (!empty($extrafields->attributes[$object->table_element]['label'])) {
	foreach ($extrafields->attributes[$object->table_element]['label'] as $key => $val) {
		$sql .= ($extrafields->attributes[$object->table_element]['type'][$key] != 'separate' ? ", ef.".$key." as options_".$key : "");
	}
}
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListSelect', $parameters, $object, $action);
$sql .= $hookmanager->resPrint;
$sql = preg_replace('/,\s*$/', '', $sql);
$sqlfields = $sql;

$sql .= " FROM ".$db->prefix().$object->table_element." as t";
if (isset($extrafields->attributes[$object->table_element]['label']) && is_array($extrafields->attributes[$object->table_element]['label']) && count($extrafields->attributes[$object->table_element]['label'])) {
	$sql .= " LEFT JOIN ".$db->prefix().$object->table_element."_extrafields as ef on (t.rowid = ef.fk_object)";
}
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListFrom', $parameters, $object, $action);
$sql .= $hookmanager->resPrint;
$sql .= " WHERE t.entity IN (".getEntity($object->element).")";
// Restrict to own records if user can only read his own data
if (!$permissiontoreadall) {
	if (isset($object->fields['fk_user_responsible'])) {
		$sql .= " AND (t.fk_user = ".((int) $user->id)." OR t.fk_user_responsible = ".((int) $user->id).")";
	} else {
		$sql .= " AND t.fk_user = ".((int) $user->id);
	}
}
foreach ($search as $key => $val) {
	if (array_key_exists($key, $object->fields)) {
		if ($key == 'status' && $search[$key] == -1) {
			continue;
		}
		$field_spec = $object->fields[$key];
		$mode_search = (($object->isInt($field_spec) || $object->isFloat($field_spec)) ? 1 : 0);
		if ((strpos($field_spec['type'], 'integer:') === 0) || (strpos($field_spec['type'], 'sellist:') === 0) || !empty($field_spec['arrayofkeyval'])) {
			if ($search[$key] == '-1' || ($search[$key] === '0' && (empty($field_spec['arrayofkeyval']) || !array_key_exists('0', $field_spec['arrayofkeyval'])))) {
				$search[$key] = '';
			}
			$mode_search = 2;
		}
		if ($field_spec['type'] === 'boolean') {
			$mode_search = 1;
			if ($search[$key] == '-1') {
				$search[$key] = '';
			}
		}
		if (!is_array($search[$key]) && $search[$key] != '') {
			$sql .= natural_search("t.".$db->escape($key), $search[$key], (($key == 'status') ? 2 : $mode_search));
		}
	} else {
		if (preg_match('/(_dtstart|_dtend)$/', $key) && $search[$key] != '') {
			$columnName = preg_replace('/(_dtstart|_dtend)$/', '', $key);
			if (preg_match('/^(date|timestamp|datetime)/', $object->fields[$columnName]['type'])) {
				if (preg_match('/_dtstart$/', $key)) {
					$sql .= " AND t.".$db->sanitize($columnName)." >= '".$db->idate($search[$key])."'";
				}
				if (preg_match('/_dtend$/', $key)) {
					$sql .= " AND t.".$db->sanitize($columnName)." <= '".$db->idate($search[$key])."'";
				}
			}
		}
	}
}
if ($search_all) {
	$sql .= natural_search(array_keys($fieldstosearchall), $search_all);
}
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_sql.tpl.php';
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldListWhere', $parameters, $object, $action);
$sql .= $hookmanager->resPrint;

// Export of filtered list as CSV (no HTML output)
if ($action == 'exportcsv' && $permissiontoexport) {
	anxhrListExportCsv($db, $sql.$db->order($sortfield, $sortorder), $object, $arrayfields, $exportname);
}

// Count total nb of records
$nbtotalofrecords = '';
if (!getDolGlobalInt('MAIN_DISABLE_FULL_SCANLIST')) {
	$sqlforcount = preg_replace('/^'.preg_quote($sqlfields, '/').'/', 'SELECT COUNT(*) as nbtotalofrecords', $sql);
	$resql = $db->query($sqlforcount);
	if ($resql) {
		$objforcount = $db->fetch_object($resql);
		$nbtotalofrecords = $objforcount->nbtotalofrecords;
		$db->free($resql);
	} else {
		dol_print_error($db);
	}
	if (($page * $limit) > (int) $nbtotalofrecords) {
		$page = 0;
		$offset = 0;
	}
}

$sql .= $db->order($sortfield, $sortorder);
if ($limit) {
	$sql .= $db->plimit($limit + 1, $offset);
}
$resql = $db->query($sql);
if (!$resql) {
	dol_print_error($db);
	exit;
}
$num = $db->num_rows($resql);

// Load rows of page and users used in user columns with one query (no fetch per line)
$rows = array();
$userids = array();
$imaxinloop = ($limit ? min($num, $limit) : $num);
for ($k = 0; $k < $imaxinloop; $k++) {
	$obj = $db->fetch_object($resql);
	if (!$obj) {
		break;
	}
	$rows[] = $obj;
	foreach ($object->fields as $key => $val) {
		if (strpos($val['type'], 'integer:User:') === 0 && !empty($obj->$key)) {
			$userids[] = (int) $obj->$key;
		}
	}
}
$db->free($resql);
$userscache = anxhrLoadUsersCache($db, $userids);

// Output page
llxHeader('', $title, '', '', 0, 0, array(), array(), '', 'mod-anxhr page-list bodyforlist');

$arrayofselected = is_array($toselect) ? $toselect : array();

$param = '';
if (!empty($contextpage) && $contextpage != getDolDefaultContextPage(__FILE__)) {
	$param .= '&contextpage='.urlencode($contextpage);
}
if ($limit > 0 && $limit != $conf->liste_limit) {
	$param .= '&limit='.((int) $limit);
}
if ($optioncss != '') {
	$param .= '&optioncss='.urlencode($optioncss);
}
foreach ($search as $key => $val) {
	if (is_array($search[$key])) {
		foreach ($search[$key] as $skey) {
			if ($skey != '') {
				$param .= '&search_'.$key.'[]='.urlencode($skey);
			}
		}
	} elseif (preg_match('/(_dtstart|_dtend)$/', $key) && !empty($val)) {
		$param .= '&search_'.$key.'month='.GETPOSTINT('search_'.$key.'month');
		$param .= '&search_'.$key.'day='.GETPOSTINT('search_'.$key.'day');
		$param .= '&search_'.$key.'year='.GETPOSTINT('search_'.$key.'year');
	} elseif ($search[$key] != '') {
		$param .= '&search_'.$key.'='.urlencode((string) $search[$key]);
	}
}
if ($search_all) {
	$param .= '&search_all='.urlencode($search_all);
}
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_param.tpl.php';
$parameters = array('param' => &$param);
$reshook = $hookmanager->executeHooks('printFieldListSearchParam', $parameters, $object, $action);
$param .= $hookmanager->resPrint;

// List of mass actions available
$arrayofmassactions = array();
if (!empty($permissiontodelete)) {
	$arrayofmassactions['predelete'] = img_picto('', 'delete', 'class="pictofixedwidth"').$langs->trans("Delete");
}
if (GETPOSTINT('nomassaction') || in_array($massaction, array('presend', 'predelete'))) {
	$arrayofmassactions = array();
}
$massactionbutton = $form->selectMassAction('', $arrayofmassactions);

print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">'."\n";
if ($optioncss != '') {
	print '<input type="hidden" name="optioncss" value="'.dol_escape_htmltag($optioncss).'">';
}
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
print '<input type="hidden" name="action" value="list">';
print '<input type="hidden" name="sortfield" value="'.dol_escape_htmltag($sortfield).'">';
print '<input type="hidden" name="sortorder" value="'.dol_escape_htmltag($sortorder).'">';
print '<input type="hidden" name="page" value="'.((int) $page).'">';
print '<input type="hidden" name="contextpage" value="'.dol_escape_htmltag($contextpage).'">';
print '<input type="hidden" name="page_y" value="">';
print '<input type="hidden" name="mode" value="'.dol_escape_htmltag($mode).'">';

$newcardbutton = '';
$newcardbutton .= dolGetButtonTitle($langs->trans('Export'), $langs->trans('AnxhrExportCsvHelp'), 'fa fa-file-csv', $_SERVER["PHP_SELF"].'?action=exportcsv&token='.newToken().'&sortfield='.urlencode($sortfield).'&sortorder='.urlencode($sortorder).$param, '', $permissiontoexport);
$newcardbutton .= dolGetButtonTitleSeparator();
$newcardbutton .= dolGetButtonTitle($langs->trans('New'), '', 'fa fa-plus-circle', dol_buildpath($cardpath, 1).'?action=create&backtopage='.urlencode($_SERVER['PHP_SELF']), '', $permissiontoadd);

print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, $object->picto, 0, $newcardbutton, '', $limit, 0, 0, 1);

// Pre mass action (confirmation)
$topicmail = '';
$modelmail = '';
$objecttmp = new $objectclass($db);
$trackid = '';
include DOL_DOCUMENT_ROOT.'/core/tpl/massactions_pre.tpl.php';

if ($search_all) {
	foreach ($fieldstosearchall as $key => $val) {
		$fieldstosearchall[$key] = $langs->trans($val);
	}
	print '<div class="divsearchfieldfilter">'.$langs->trans("FilterOnInto", dol_escape_htmltag($search_all)).implode(', ', $fieldstosearchall).'</div>'."\n";
}

$moreforfilter = '';
$parameters = array();
$reshook = $hookmanager->executeHooks('printFieldPreListTitle', $parameters, $object, $action);
if (empty($reshook)) {
	$moreforfilter .= $hookmanager->resPrint;
} else {
	$moreforfilter = $hookmanager->resPrint;
}
if (!empty($moreforfilter)) {
	print '<div class="liste_titre liste_titre_bydiv centpercent">'.$moreforfilter.'</div>';
}

$varpage = empty($contextpage) ? $_SERVER["PHP_SELF"] : $contextpage;
$htmlofselectarray = $form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage, $conf->main_checkbox_left_column, 1);
$selectedfields = $htmlofselectarray;
$selectedfields .= (count($arrayofmassactions) ? $form->showCheckAddButtons('checkforselect', 1) : '');

print '<div class="div-table-responsive">';
print '<table class="tagtable nobottomiftotal noborder liste'.($moreforfilter ? " listwithfilterbefore" : "").'">'."\n";

// Fields title search
print '<tr class="liste_titre_filter">';
if ($conf->main_checkbox_left_column) {
	print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons('left').'</td>';
}
foreach ($object->fields as $key => $val) {
	if (empty($arrayfields['t.'.$key]['checked'])) {
		continue;
	}
	$cssforfield = anxhrListColumnCss($key, $val);
	print '<td class="liste_titre'.($cssforfield ? ' '.$cssforfield : '').($key == 'status' ? ' parentonrightofpage' : '').'">';
	if (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
		print $form->selectarray('search_'.$key, $val['arrayofkeyval'], (isset($search[$key]) ? $search[$key] : ''), 1, 0, 0, '', 1, 0, 0, '', 'maxwidth100'.($key == 'status' ? ' search_status width100 onrightofpage' : ''), 1);
	} elseif ($key == 'fk_user') {
		print $form->select_dolusers((isset($search[$key]) && $search[$key] > 0 ? $search[$key] : -1), 'search_'.$key, 1, null, ($permissiontoreadall ? 0 : 1), '', '', '', 0, 0, '(employee:=:1)', 0, '', 'maxwidth150');
	} elseif ((strpos($val['type'], 'integer:') === 0) || (strpos($val['type'], 'sellist:') === 0)) {
		print $object->showInputField($val, $key, (isset($search[$key]) ? $search[$key] : ''), '', '', 'search_', $cssforfield.' maxwidth150', 1);
	} elseif (preg_match('/^(date|timestamp|datetime)/', $val['type'])) {
		print '<div class="nowrap">';
		print $form->selectDate($search[$key.'_dtstart'] ? $search[$key.'_dtstart'] : '', "search_".$key."_dtstart", 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('From'));
		print '</div><div class="nowrap">';
		print $form->selectDate($search[$key.'_dtend'] ? $search[$key.'_dtend'] : '', "search_".$key."_dtend", 0, 0, 1, '', 1, 0, 0, '', '', '', '', 1, '', $langs->trans('to'));
		print '</div>';
	} elseif ($val['type'] === 'boolean') {
		print $form->selectyesno('search_'.$key, isset($search[$key]) ? $search[$key] : '', 1, false, 1);
	} else {
		print '<input type="text" class="flat maxwidth75" name="search_'.$key.'" value="'.dol_escape_htmltag(isset($search[$key]) ? $search[$key] : '').'">';
	}
	print '</td>';
}
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_input.tpl.php';
$parameters = array('arrayfields' => $arrayfields);
$reshook = $hookmanager->executeHooks('printFieldListOption', $parameters, $object, $action);
print $hookmanager->resPrint;
if (!$conf->main_checkbox_left_column) {
	print '<td class="liste_titre center maxwidthsearch">'.$form->showFilterButtons().'</td>';
}
print '</tr>'."\n";

// Fields title label
$totalarray = array('nbfield' => 0);
print '<tr class="liste_titre">';
if ($conf->main_checkbox_left_column) {
	print getTitleFieldOfList($selectedfields, 0, $_SERVER["PHP_SELF"], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ')."\n";
	$totalarray['nbfield']++;
}
foreach ($object->fields as $key => $val) {
	if (empty($arrayfields['t.'.$key]['checked'])) {
		continue;
	}
	$cssforfield = anxhrListColumnCss($key, $val);
	print getTitleFieldOfList($arrayfields['t.'.$key]['label'], 0, $_SERVER['PHP_SELF'], 't.'.$key, '', $param, ($cssforfield ? 'class="'.$cssforfield.'"' : ''), $sortfield, $sortorder, ($cssforfield ? $cssforfield.' ' : ''), 0, (empty($val['helplist']) ? '' : $val['helplist']))."\n";
	$totalarray['nbfield']++;
}
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_title.tpl.php';
$parameters = array('arrayfields' => $arrayfields, 'param' => $param, 'sortfield' => $sortfield, 'sortorder' => $sortorder, 'totalarray' => &$totalarray);
$reshook = $hookmanager->executeHooks('printFieldListTitle', $parameters, $object, $action);
print $hookmanager->resPrint;
if (!$conf->main_checkbox_left_column) {
	print getTitleFieldOfList($selectedfields, 0, $_SERVER["PHP_SELF"], '', '', '', '', $sortfield, $sortorder, 'center maxwidthsearch ')."\n";
	$totalarray['nbfield']++;
}
print '</tr>'."\n";

// Loop on records
$savnbfield = $totalarray['nbfield'];
$totalarray = array('nbfield' => 0);
$i = 0;
foreach ($rows as $obj) {
	$object->setVarsFromFetchObj($obj);

	print '<tr data-rowid="'.((int) $object->id).'" class="oddeven row-with-select">';
	$selectcell = '';
	if ($massactionbutton || $massaction) {
		$selected = (in_array($object->id, $arrayofselected) ? 1 : 0);
		$selectcell = '<input id="cb'.((int) $object->id).'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.((int) $object->id).'"'.($selected ? ' checked="checked"' : '').'>';
	}
	if ($conf->main_checkbox_left_column) {
		print '<td class="nowrap center">'.$selectcell.'</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}
	foreach ($object->fields as $key => $val) {
		if (empty($arrayfields['t.'.$key]['checked'])) {
			continue;
		}
		$cssforfield = (empty($val['csslist']) ? '' : $val['csslist']);
		if ($key == 'status' || in_array($val['type'], array('date', 'datetime', 'timestamp'))) {
			$cssforfield .= ($cssforfield ? ' ' : '').'center';
		} elseif (preg_match('/^(double|integer|real|price)/', $val['type']) && strpos($val['type'], 'integer:') !== 0 && !in_array($key, array('rowid', 'ref', 'status')) && empty($val['arrayofkeyval'])) {
			$cssforfield .= ($cssforfield ? ' ' : '').'right';
		}
		print '<td'.($cssforfield ? ' class="'.$cssforfield.'"' : '').'>';
		if ($key == 'status') {
			print $object->getLibStatut(5);
		} elseif ($key == 'ref' || ($key == 'label' && empty($object->fields['ref']))) {
			print $object->getNomUrl(1);
		} elseif ($key == 'progress_pct') {
			print anxhrHrProgressBar((int) $object->progress_pct, 'width75');
		} elseif (strpos($val['type'], 'integer:User:') === 0) {
			$uid = (int) $object->$key;
			print ($uid > 0 && isset($userscache[$uid]) ? $userscache[$uid]->getNomUrl(-1) : '');
		} else {
			print $object->showOutputField($val, $key, (string) $object->$key, '');
		}
		print '</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_print_fields.tpl.php';
	$parameters = array('arrayfields' => $arrayfields, 'object' => $object, 'obj' => $obj, 'i' => $i, 'totalarray' => &$totalarray);
	$reshook = $hookmanager->executeHooks('printFieldListValue', $parameters, $object, $action);
	print $hookmanager->resPrint;
	if (empty($conf->main_checkbox_left_column)) {
		print '<td class="nowrap center">'.$selectcell.'</td>';
		if (!$i) {
			$totalarray['nbfield']++;
		}
	}
	print '</tr>'."\n";
	$i++;
}

// If no record found
if ($num == 0) {
	$colspan = 1;
	foreach ($arrayfields as $key => $val) {
		if (!empty($val['checked'])) {
			$colspan++;
		}
	}
	print '<tr><td colspan="'.$colspan.'"><span class="opacitymedium">'.$langs->trans("NoRecordFound").'</span></td></tr>';
}

$parameters = array('arrayfields' => $arrayfields, 'sql' => $sql);
$reshook = $hookmanager->executeHooks('printFieldListFooter', $parameters, $object, $action);
print $hookmanager->resPrint;

print '</table>'."\n";
print '</div>'."\n";
print '</form>'."\n";

// End of page
llxFooter();
$db->close();
