<?php
/* Copyright (C) 2026		ANX HR contributors
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/custom/anxhr/user_tab.php
 *	\ingroup    anxhr
 *	\brief      HR tab on user card: summary of contract, deadlines, emergency contacts, handovers, checklists and time
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
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
// Try main.inc.php using relative path
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/usergroups.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
dol_include_once('/anxhr/lib/anxhr.lib.php');

// Load translation files required by the page
$langs->loadLangs(array("anxhr@anxhr", "anxhr_hr@anxhr", "anxhr_vault@anxhr", "users", "companies"));

// Parameters
$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$ecid = GETPOSTINT('ecid');

// Security check
if (!isModEnabled('anxhr')) {
	accessforbidden('Module not enabled');
}
$object = new User($db);
if ($id <= 0 || $object->fetch($id) <= 0) {
	accessforbidden();
}
if (!anxhrCanSeeUser($user, (int) $object->id)) {
	accessforbidden();
}

$isself = ((int) $user->id == (int) $object->id);
$canwriteemployee = $user->hasRight('anxhr', 'employee', 'write');
// Emergency contacts are private data: visible only to the employee himself or to HR (employee read_all),
// never to supervisors; adding / deleting needs employee write in addition.
$canseeemergency = ($isself || $user->hasRight('anxhr', 'employee', 'read_all'));
$canwriteemergency = ($canseeemergency && $canwriteemployee);
$canreadcontract = ($user->hasRight('anxhr', 'contract', 'read') || $isself);
$canwritecontract = $user->hasRight('anxhr', 'contract', 'write');
$canreaddeadline = ($user->hasRight('anxhr', 'deadline', 'read') || $isself);
$canreadhandover = ($user->hasRight('anxhr', 'handover', 'read') || $isself);
$canreadchecklist = ($user->hasRight('anxhr', 'checklist', 'read') || $isself);
$canreadtime = (($isself && $user->hasRight('anxhr', 'time', 'own')) || $user->hasRight('anxhr', 'time', 'admin')
	|| ($user->hasRight('anxhr', 'time', 'approve') && in_array((int) $object->id, anxhrGetSubordinateIds($user))));
// Document vault: own documents (right vault/own) or HR (vault/admin). Supervisors never.
$canreadvault = (($isself && $user->hasRight('anxhr', 'vault', 'own')) || $user->hasRight('anxhr', 'vault', 'admin'));

$hookmanager->initHooks(array('anxhrusertab', 'globalcard'));

$tableemergency = ($canseeemergency && anxhrTableExists($db, 'anxhr_emergency_contact'));
$selfurl = $_SERVER["PHP_SELF"].'?id='.((int) $object->id);


/*
 * Actions
 */

$parameters = array('id' => $object->id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook) && $canwriteemergency && $tableemergency) {
	if ($action == 'add_emergency') {
		$ecname = trim(GETPOST('ec_name', 'alphanohtml'));
		$ecrelation = trim(GETPOST('ec_relation', 'alphanohtml'));
		$ecphone = trim(GETPOST('ec_phone', 'alphanohtml'));
		$ecemail = trim(GETPOST('ec_email', 'alphanohtml'));

		$error = 0;
		if ($ecname === '') {
			setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Name')), null, 'errors');
			$error++;
		}
		if ($ecemail !== '' && !isValidEmail($ecemail)) {
			setEventMessages($langs->trans('ErrorBadEMail', $ecemail), null, 'errors');
			$error++;
		}

		if (!$error) {
			$sql = "INSERT INTO ".$db->prefix()."anxhr_emergency_contact";
			$sql .= " (entity, fk_user, name, relation, phone, email, date_creation, fk_user_creat)";
			$sql .= " VALUES (".((int) $conf->entity).", ".((int) $object->id).",";
			$sql .= " '".$db->escape(dol_trunc($ecname, 255, 'right', 'UTF-8', 1))."',";
			$sql .= " ".($ecrelation !== '' ? "'".$db->escape(dol_trunc($ecrelation, 128, 'right', 'UTF-8', 1))."'" : "NULL").",";
			$sql .= " ".($ecphone !== '' ? "'".$db->escape(dol_trunc($ecphone, 64, 'right', 'UTF-8', 1))."'" : "NULL").",";
			$sql .= " ".($ecemail !== '' ? "'".$db->escape($ecemail)."'" : "NULL").",";
			$sql .= " '".$db->idate(dol_now())."', ".((int) $user->id).")";
			if ($db->query($sql)) {
				setEventMessages($langs->trans('RecordSaved'), null, 'mesgs');
				header('Location: '.$selfurl);
				exit;
			}
			dol_syslog('user_tab add_emergency '.$db->lasterror(), LOG_ERR);
			setEventMessages($db->lasterror(), null, 'errors');
		}
		$action = '';
	}

	if ($action == 'confirm_delete_emergency' && $confirm == 'yes' && $ecid > 0) {
		$sql = "DELETE FROM ".$db->prefix()."anxhr_emergency_contact";
		$sql .= " WHERE rowid = ".((int) $ecid);
		$sql .= " AND fk_user = ".((int) $object->id);
		$sql .= " AND entity IN (".getEntity('anxhr_emergency_contact').")";
		if ($db->query($sql)) {
			setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
			header('Location: '.$selfurl);
			exit;
		}
		dol_syslog('user_tab delete_emergency '.$db->lasterror(), LOG_ERR);
		setEventMessages($db->lasterror(), null, 'errors');
		$action = '';
	}
}


/*
 * Data loading (read only)
 */

$now = dol_now();
$today = dol_get_first_hour($now);

// Current contract (active first, else most recent)
$contract = null;
if ($canreadcontract && anxhrTableExists($db, 'anxhr_contract')) {
	$withmodel = anxhrTableExists($db, 'anxhr_timemodel');
	$sql = "SELECT c.rowid, c.ref, c.date_start, c.date_end, c.probation_end, c.fixed_term_end, c.weekly_hours,";
	$sql .= " c.employment_type, c.kv_code, c.kv_group, c.kv_level, c.status, c.fk_timemodel";
	$sql .= ($withmodel ? ", tm.label as model_label" : "");
	$sql .= " FROM ".$db->prefix()."anxhr_contract as c";
	if ($withmodel) {
		$sql .= " LEFT JOIN ".$db->prefix()."anxhr_timemodel as tm ON tm.rowid = c.fk_timemodel";
	}
	$sql .= " WHERE c.fk_user = ".((int) $object->id);
	$sql .= " AND c.entity IN (".getEntity('anxhr_contract').")";
	$sql .= " ORDER BY CASE WHEN c.status = 1 THEN 0 ELSE 1 END, c.date_start DESC";
	$sql .= $db->plimit(1);
	$resql = $db->query($sql);
	if ($resql) {
		$contract = $db->fetch_object($resql);
		$db->free($resql);
	}
}

// Next open deadlines
$deadlines = array();
if ($canreaddeadline && anxhrTableExists($db, 'anxhr_deadline')) {
	$sql = "SELECT d.rowid, d.label, d.type, d.date_due FROM ".$db->prefix()."anxhr_deadline as d";
	$sql .= " WHERE d.fk_user = ".((int) $object->id);
	$sql .= " AND d.entity IN (".getEntity('anxhr_deadline').")";
	$sql .= " AND d.status = 0";
	$sql .= " ORDER BY d.date_due ASC";
	$sql .= $db->plimit(5);
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$deadlines[] = $obj;
		}
		$db->free($resql);
	}
}

// Emergency contacts
$contacts = array();
if ($tableemergency) {
	$sql = "SELECT ec.rowid, ec.name, ec.relation, ec.phone, ec.email FROM ".$db->prefix()."anxhr_emergency_contact as ec";
	$sql .= " WHERE ec.fk_user = ".((int) $object->id);
	$sql .= " AND ec.entity IN (".getEntity('anxhr_emergency_contact').")";
	$sql .= " ORDER BY ec.rowid ASC";
	$sql .= $db->plimit(20);
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$contacts[] = $obj;
		}
		$db->free($resql);
	}
}

// Open handovers (not returned)
$handovers = array();
if ($canreadhandover && anxhrTableExists($db, 'anxhr_handover')) {
	$sql = "SELECT h.rowid, h.ref, h.category, h.label, h.serial, h.system_name, h.date_out FROM ".$db->prefix()."anxhr_handover as h";
	$sql .= " WHERE h.fk_user = ".((int) $object->id);
	$sql .= " AND h.entity IN (".getEntity('anxhr_handover').")";
	$sql .= " AND h.status = 0";
	$sql .= " ORDER BY h.date_out DESC";
	$sql .= $db->plimit(20);
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$handovers[] = $obj;
		}
		$db->free($resql);
	}
}

// Checklists with progress computed from items
$checklists = array();
if ($canreadchecklist && anxhrTableExists($db, 'anxhr_checklist')) {
	$withitems = anxhrTableExists($db, 'anxhr_checklist_item');
	$sql = "SELECT c.rowid, c.ref, c.checklist_type, c.anchor_date, c.status, c.progress_pct";
	if ($withitems) {
		$sql .= ", COUNT(i.rowid) as nbitems, SUM(CASE WHEN i.status IN (1, 2) THEN 1 ELSE 0 END) as nbdone";
	}
	$sql .= " FROM ".$db->prefix()."anxhr_checklist as c";
	if ($withitems) {
		$sql .= " LEFT JOIN ".$db->prefix()."anxhr_checklist_item as i ON i.fk_checklist = c.rowid";
	}
	$sql .= " WHERE c.fk_user = ".((int) $object->id);
	$sql .= " AND c.entity IN (".getEntity('anxhr_checklist').")";
	if ($withitems) {
		$sql .= " GROUP BY c.rowid, c.ref, c.checklist_type, c.anchor_date, c.status, c.progress_pct";
	}
	$sql .= " ORDER BY c.anchor_date DESC";
	$sql .= $db->plimit(10);
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			if ($withitems && $obj->nbitems > 0) {
				$obj->pct = (int) floor(100 * $obj->nbdone / $obj->nbitems);
			} else {
				$obj->pct = (int) $obj->progress_pct;
			}
			$checklists[] = $obj;
		}
		$db->free($resql);
	}
}

// Time of current month
$timemonth = null;
if ($canreadtime && anxhrTableExists($db, 'anxhr_time_day')) {
	$tmparray = dol_getdate($now);
	$monthstart = dol_get_first_day((int) $tmparray['year'], (int) $tmparray['mon']);
	$monthend = dol_get_last_day((int) $tmparray['year'], (int) $tmparray['mon']);
	$sql = "SELECT SUM(td.worked_min) as worked, SUM(td.target_min) as target, SUM(td.diff_min) as diff, COUNT(td.rowid) as nbdays";
	$sql .= " FROM ".$db->prefix()."anxhr_time_day as td";
	$sql .= " WHERE td.fk_user = ".((int) $object->id);
	$sql .= " AND td.entity IN (".getEntity('anxhr_time_day').")";
	$sql .= " AND td.day BETWEEN '".$db->idate($monthstart)."' AND '".$db->idate($monthend)."'";
	$resql = $db->query($sql);
	if ($resql) {
		$timemonth = $db->fetch_object($resql);
		$db->free($resql);
	}
}

// Vault documents: count per category (one query)
$vaultcounts = array();
$vaultunread = 0;
if ($canreadvault && anxhrTableExists($db, 'anxhr_vault_doc')) {
	dol_include_once('/anxhr/lib/anxhr_vault.lib.php');
	$sql = "SELECT vd.category, COUNT(vd.rowid) as nb, SUM(CASE WHEN vd.first_viewed_at IS NULL AND vd.visible_to_employee = 1 THEN 1 ELSE 0 END) as nbunread";
	$sql .= " FROM ".$db->prefix()."anxhr_vault_doc as vd";
	$sql .= " WHERE vd.fk_user = ".((int) $object->id);
	$sql .= " AND vd.entity IN (".getEntity('anxhr_vaultdoc').")";
	$sql .= " AND vd.status = 1";
	if (!$user->hasRight('anxhr', 'vault', 'admin')) {
		$sql .= " AND vd.visible_to_employee = 1";
	}
	$sql .= " GROUP BY vd.category";
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$vaultcounts[$obj->category] = (int) $obj->nb;
			$vaultunread += (int) $obj->nbunread;
		}
		$db->free($resql);
	}
}


/*
 * View
 */

/**
 * Print the title row of a summary block
 *
 * @param	string	$title			Title (already translated)
 * @param	string	$picto			Picto
 * @param	string	$morehtmlright	Html shown on the right
 * @param	int		$colspan		Colspan
 * @return	void
 */
function anxhrTabBlockTitle($title, $picto, $morehtmlright = '', $colspan = 2)
{
	// One header cell spanning the whole table: title on the left, actions on the right (never squeezed into a column)
	print '<tr class="liste_titre">';
	print '<th colspan="'.((int) $colspan).'"><div class="anxhr-blocktitle">';
	print '<span class="anxhr-blocktitle-label">'.img_picto('', $picto, 'class="pictofixedwidth"').dol_escape_htmltag($title).'</span>';
	if ($morehtmlright) {
		print '<span class="anxhr-blocktitle-actions nowraponall">'.$morehtmlright.'</span>';
	}
	print '</div></th>';
	print '</tr>';
}

/**
 * Print a label/value row
 *
 * @param	string	$label		Label (already translated)
 * @param	string	$value		Value (already escaped html)
 * @return	void
 */
function anxhrTabRow($label, $value)
{
	print '<tr class="oddeven"><td class="titlefield">'.dol_escape_htmltag($label).'</td><td>'.$value.'</td></tr>';
}

$form = new Form($db);

$title = $langs->trans("User").' - '.$langs->trans("AnxhrHrTab");
llxHeader('', $title, '', '', 0, 0, '', '', '', 'mod-anxhr page-user_tab');

$head = user_prepare_head($object);
print dol_get_fiche_head($head, 'anxhr', $langs->trans("User"), -1, 'user');

$linkback = '';
if ($user->hasRight("user", "user", "read") || $user->admin) {
	$linkback = '<a href="'.DOL_URL_ROOT.'/user/list.php?restore_lastsearch_values=1">'.$langs->trans("BackToList").'</a>';
}
dol_banner_tab($object, 'id', $linkback, $user->hasRight("user", "user", "read") || $user->admin, 'rowid', 'ref');

print '<div class="underbanner clearboth"></div>';

// Confirm deletion of emergency contact
if ($action == 'delete_emergency' && $canwriteemergency && $ecid > 0) {
	print $form->formconfirm($selfurl.'&ecid='.((int) $ecid), $langs->trans('AnxhrDeleteEmergencyContact'), $langs->trans('AnxhrConfirmDeleteEmergencyContact'), 'confirm_delete_emergency', '', '', 1);
}

print '<div class="fichecenter anxhr-usertab">';

/* Left column */
print '<div class="fichehalfleft">';

// Contract
if ($canreadcontract) {
	$buttons = '';
	if ($canwritecontract) {
		if ($contract) {
			$buttons .= '<a class="editfielda" href="'.dol_buildpath('/anxhr/contract_card.php', 1).'?id='.((int) $contract->rowid).'&action=edit&token='.newToken().'" title="'.dolPrintHTMLForAttribute($langs->trans('Modify')).'">'.img_edit().'</a> ';
		}
		$buttons .= dolGetButtonTitle($langs->trans('AnxhrNewContract'), '', 'fa fa-plus-circle', dol_buildpath('/anxhr/contract_card.php', 1).'?action=create&fk_user='.((int) $object->id));
	}
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	anxhrTabBlockTitle($langs->trans('AnxhrContract'), 'fa-file-signature', $buttons);
	if (!$contract) {
		print '<tr class="oddeven"><td colspan="2"><span class="opacitymedium">'.$langs->trans('AnxhrNoContract').'</span></td></tr>';
	} else {
		anxhrTabRow($langs->trans('Ref'), '<a href="'.dol_buildpath('/anxhr/contract_card.php', 1).'?id='.((int) $contract->rowid).'">'.dol_escape_htmltag($contract->ref).'</a>');
		$period = dol_print_date($db->jdate($contract->date_start), 'day');
		$period .= ' - '.($contract->date_end ? dol_print_date($db->jdate($contract->date_end), 'day') : $langs->trans('AnxhrOpenEnded'));
		anxhrTabRow($langs->trans('AnxhrEmploymentPeriod'), $period);
		if ($contract->weekly_hours !== null && $contract->weekly_hours !== '') {
			anxhrTabRow($langs->trans('AnxhrWeeklyHours'), price((float) $contract->weekly_hours, 0, $langs, 0, -1, -1).' '.$langs->trans('Hours'));
		}
		if (!empty($contract->employment_type)) {
			anxhrTabRow($langs->trans('AnxhrEmploymentType'), dol_escape_htmltag(anxhrEmploymentTypeLabel($contract->employment_type)));
		}
		if (!empty($contract->model_label)) {
			anxhrTabRow($langs->trans('AnxhrTimeModel'), dol_escape_htmltag($contract->model_label));
		}
		if (!empty($contract->kv_code)) {
			anxhrTabRow($langs->trans('AnxhrCollectiveAgreement'), dol_escape_htmltag(trim($contract->kv_code.' '.$contract->kv_group.' '.$contract->kv_level)));
		}
		if (!empty($contract->probation_end)) {
			$ts = $db->jdate($contract->probation_end);
			anxhrTabRow($langs->trans('AnxhrProbationEnd'), dol_print_date($ts, 'day').($ts >= $today ? ' '.anxhrDueBadge($ts, $today) : ''));
		}
		if (!empty($contract->fixed_term_end)) {
			$ts = $db->jdate($contract->fixed_term_end);
			anxhrTabRow($langs->trans('AnxhrFixedTermEnd'), dol_print_date($ts, 'day').($ts >= $today ? ' '.anxhrDueBadge($ts, $today) : ''));
		}
	}
	print '</table>';
	print '</div>';
	print '<br>';
}

// Deadlines
if ($canreaddeadline) {
	$more = '<a href="'.dol_buildpath('/anxhr/deadline_list.php', 1).'?search_fk_user='.((int) $object->id).'&mainmenu=anxhr">'.$langs->trans('FullList').'</a>';
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	anxhrTabBlockTitle($langs->trans('AnxhrNextDeadlines'), 'fa-calendar-check', $more, 3);
	if (empty($deadlines)) {
		print '<tr class="oddeven"><td colspan="3"><span class="opacitymedium">'.$langs->trans('AnxhrNoOpenDeadline').'</span></td></tr>';
	}
	foreach ($deadlines as $obj) {
		$ts = $db->jdate($obj->date_due);
		print '<tr class="oddeven">';
		$dlabel = ($obj->label ? $obj->label : anxhrDeadlineTypeLabel($obj->type));
		print '<td class="tdoverflowmax200" title="'.dolPrintHTMLForAttribute($dlabel).'"><a href="'.dol_buildpath('/anxhr/deadline_card.php', 1).'?id='.((int) $obj->rowid).'">'.img_picto('', 'fa-hourglass-half', 'class="pictofixedwidth opacitymedium"').dol_escape_htmltag($dlabel).'</a></td>';
		print '<td class="nowraponall hideonsmartphone">'.dol_print_date($ts, 'day').'</td>';
		print '<td class="right nowraponall" title="'.dolPrintHTMLForAttribute(dol_print_date($ts, 'day')).'">'.anxhrDueBadge($ts, $today).'</td>';
		print '</tr>';
	}
	print '</table>';
	print '</div>';
	print '<br>';
}

// Emergency contacts
if ($tableemergency) {
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	anxhrTabBlockTitle($langs->trans('AnxhrEmergencyContacts'), 'fa-phone', '', 5);
	print '<tr class="liste_titre_filter">';
	print '<td>'.$langs->trans('Name').'</td><td class="hideonsmartphone">'.$langs->trans('AnxhrRelation').'</td><td>'.$langs->trans('Phone').'</td><td class="hideonsmartphone">'.$langs->trans('Email').'</td><td></td>';
	print '</tr>';
	if (empty($contacts)) {
		print '<tr class="oddeven"><td colspan="5"><span class="opacitymedium">'.$langs->trans('AnxhrNoEmergencyContact').'</span></td></tr>';
	}
	foreach ($contacts as $obj) {
		print '<tr class="oddeven">';
		print '<td class="tdoverflowmax150">'.dol_escape_htmltag($obj->name).'</td>';
		print '<td class="tdoverflowmax100 hideonsmartphone">'.dol_escape_htmltag((string) $obj->relation).'</td>';
		print '<td class="nowraponall">'.dol_print_phone((string) $obj->phone, '', 0, 0, 'AC_TEL').'</td>';
		print '<td class="tdoverflowmax150 hideonsmartphone">'.dol_print_email((string) $obj->email, 0, 0, 1).'</td>';
		print '<td class="right">';
		if ($canwriteemergency) {
			print '<a class="reposition" href="'.$selfurl.'&action=delete_emergency&ecid='.((int) $obj->rowid).'&token='.newToken().'" aria-label="'.dolPrintHTMLForAttribute($langs->trans('AnxhrDeleteEmergencyContact')).'">'.img_delete($langs->trans('AnxhrDeleteEmergencyContact')).'</a>';
		}
		print '</td>';
		print '</tr>';
	}
	print '</table>';
	print '</div>';

	if ($canwriteemergency) {
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" class="anxhr-inline-form">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="add_emergency">';
		print '<input type="hidden" name="id" value="'.((int) $object->id).'">';
		print '<input type="text" name="ec_name" class="minwidth100 maxwidth150" maxlength="255" placeholder="'.dolPrintHTMLForAttribute($langs->trans('Name')).'*" aria-label="'.dolPrintHTMLForAttribute($langs->trans('Name')).'" required aria-required="true">';
		print '<input type="text" name="ec_relation" class="maxwidth100" maxlength="128" placeholder="'.dolPrintHTMLForAttribute($langs->trans('AnxhrRelation')).'" aria-label="'.dolPrintHTMLForAttribute($langs->trans('AnxhrRelation')).'">';
		print '<input type="tel" name="ec_phone" class="maxwidth125" maxlength="64" placeholder="'.dolPrintHTMLForAttribute($langs->trans('Phone')).'" aria-label="'.dolPrintHTMLForAttribute($langs->trans('Phone')).'">';
		print '<input type="email" name="ec_email" class="maxwidth150" maxlength="255" placeholder="'.dolPrintHTMLForAttribute($langs->trans('Email')).'" aria-label="'.dolPrintHTMLForAttribute($langs->trans('Email')).'">';
		print '<button type="submit" class="button smallpaddingimp">'.img_picto('', 'fa-plus', 'class="pictofixedwidth"').$langs->trans('Add').'</button>';
		print '</form>';
	}
	print '<br>';
}

print '</div>';

/* Right column */
print '<div class="fichehalfright">';

// Equipment and access
if ($canreadhandover) {
	$more = '';
	if ($user->hasRight('anxhr', 'handover', 'write')) {
		$more = dolGetButtonTitle($langs->trans('AnxhrNewHandover'), '', 'fa fa-plus-circle', dol_buildpath('/anxhr/handover_card.php', 1).'?action=create&fk_user='.((int) $object->id));
	}
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	anxhrTabBlockTitle($langs->trans('AnxhrEquipmentAndAccess'), 'fa-key', $more, 3);
	if (empty($handovers)) {
		print '<tr class="oddeven"><td colspan="3"><span class="opacitymedium">'.$langs->trans('AnxhrNoOpenHandover').'</span></td></tr>';
	}
	foreach ($handovers as $obj) {
		$detail = ($obj->serial ? $obj->serial : (string) $obj->system_name);
		print '<tr class="oddeven">';
		print '<td class="tdoverflowmax200"><a href="'.dol_buildpath('/anxhr/handover_card.php', 1).'?id='.((int) $obj->rowid).'">'.dol_escape_htmltag($obj->label).'</a>';
		print ' <span class="opacitymedium small">'.dol_escape_htmltag(anxhrHandoverCategoryLabel($obj->category)).'</span></td>';
		print '<td class="tdoverflowmax100 hideonsmartphone">'.dol_escape_htmltag($detail).'</td>';
		print '<td class="right nowraponall">'.dol_print_date($db->jdate($obj->date_out), 'day').'</td>';
		print '</tr>';
	}
	print '</table>';
	print '</div>';
	print '<br>';
}

// Checklists
if ($canreadchecklist) {
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	anxhrTabBlockTitle($langs->trans('AnxhrChecklists'), 'fa-tasks', '', 3);
	if (empty($checklists)) {
		print '<tr class="oddeven"><td colspan="3"><span class="opacitymedium">'.$langs->trans('AnxhrNoChecklist').'</span></td></tr>';
	}
	foreach ($checklists as $obj) {
		print '<tr class="oddeven">';
		print '<td class="tdoverflowmax150"><a href="'.dol_buildpath('/anxhr/checklist_card.php', 1).'?id='.((int) $obj->rowid).'">'.dol_escape_htmltag($obj->ref).'</a>';
		print ' <span class="opacitymedium small">'.dol_escape_htmltag(anxhrChecklistTypeLabel($obj->checklist_type)).'</span></td>';
		print '<td class="minwidth100">'.anxhrProgressBar($obj->pct).'</td>';
		print '<td class="right nowraponall">'.((int) $obj->pct).' %';
		if (!empty($obj->nbitems)) {
			print ' <span class="opacitymedium small">('.((int) $obj->nbdone).'/'.((int) $obj->nbitems).')</span>';
		}
		print '</td>';
		print '</tr>';
	}
	print '</table>';
	print '</div>';
	print '<br>';
}

// Time of current month
if ($canreadtime) {
	$more = '<a href="'.dol_buildpath('/anxhr/time_day.php', 1).'?id='.((int) $object->id).'&mainmenu=anxhr">'.$langs->trans('AnxhrDetails').'</a>';
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	anxhrTabBlockTitle($langs->trans('AnxhrTimeThisMonth'), 'fa-clock', $more);
	if (!$timemonth || empty($timemonth->nbdays)) {
		print '<tr class="oddeven"><td colspan="2"><span class="opacitymedium">'.$langs->trans('AnxhrNoTimeData').'</span></td></tr>';
	} else {
		$worked = (int) $timemonth->worked;
		$target = (int) $timemonth->target;
		$diff = (int) $timemonth->diff;
		anxhrTabRow($langs->trans('AnxhrWorked'), anxhrFormatMinutes($worked));
		anxhrTabRow($langs->trans('AnxhrTarget'), anxhrFormatMinutes($target));
		anxhrTabRow($langs->trans('AnxhrBalance'), '<span class="'.($diff < 0 ? 'anxhr-negative' : 'anxhr-positive').'">'.($diff > 0 ? '+' : '').anxhrFormatMinutes($diff).'</span>');
		anxhrTabRow($langs->trans('AnxhrProgress'), anxhrProgressBar($target > 0 ? (int) floor(100 * $worked / $target) : 0));
	}
	print '</table>';
	print '</div>';
	print '<br>';
}

// Documents (vault)
if ($canreadvault) {
	$vaulturl = dol_buildpath('/anxhr/vault.php', 1).($user->hasRight('anxhr', 'vault', 'admin') ? '?search_fk_user='.((int) $object->id) : '');
	$more = '<a href="'.$vaulturl.'">'.$langs->trans('AnxhrVaultOpen').'</a>';
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	anxhrTabBlockTitle($langs->trans('AnxhrVaultTabDocuments'), 'fa-shield-alt', $more);
	if (empty($vaultcounts)) {
		print '<tr class="oddeven"><td colspan="2"><span class="opacitymedium">'.$langs->trans('AnxhrVaultNoDocuments').'</span></td></tr>';
	}
	foreach (anxhrVaultCategories() as $code => $def) {
		if (empty($vaultcounts[$code])) {
			continue;
		}
		print '<tr class="oddeven"><td>'.anxhrVaultCategoryBadge($code, $langs).'</td><td class="right"><a href="'.$vaulturl.($user->hasRight('anxhr', 'vault', 'admin') ? '&search_category='.urlencode($code) : '').'">'.((int) $vaultcounts[$code]).'</a></td></tr>';
	}
	if ($vaultunread > 0) {
		print '<tr class="oddeven"><td colspan="2"><span class="badge badge-status1">'.img_picto('', 'fa-envelope', 'class="pictofixedwidth"').$langs->trans('AnxhrVaultUnreadCount', $vaultunread).'</span></td></tr>';
	}
	print '</table>';
	print '</div>';
	print '<br>';
}

print '</div>';

print '</div>';
print '<div class="clearboth"></div>';

print dol_get_fiche_end();

// End of page
llxFooter();
$db->close();
