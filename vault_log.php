<?php
/* Copyright (C) 2026 ANX
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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/custom/anxhr/vault_log.php
 *	\ingroup    anxhr
 *	\brief      Access log of the HR document vault (filters, pagination, CSV export)
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
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once('/anxhr/lib/anxhr_hr.lib.php');
dol_include_once('/anxhr/lib/anxhr_vault.lib.php');
dol_include_once('/anxhr/class/vaultdoc.class.php');

$langs->loadLangs(array('anxhr@anxhr', 'anxhr_vault@anxhr', 'users', 'other'));

// Parameters
$search_user = GETPOSTINT('search_user');
$search_doc = trim(GETPOST('search_doc', 'alphanohtml'));
$search_action = GETPOST('search_action', 'aZ09');
$search_date_start = dol_mktime(0, 0, 0, GETPOSTINT('search_date_startmonth'), GETPOSTINT('search_date_startday'), GETPOSTINT('search_date_startyear'));
$search_date_end = dol_mktime(23, 59, 59, GETPOSTINT('search_date_endmonth'), GETPOSTINT('search_date_endday'), GETPOSTINT('search_date_endyear'));
$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$page = 0;
}
if (GETPOST('button_removefilter', 'alpha')) {
	$search_user = 0;
	$search_doc = '';
	$search_action = '';
	$search_date_start = '';
	$search_date_end = '';
}
if (!in_array($search_action, VaultDoc::LOG_ACTIONS, true)) {
	$search_action = '';
}
$offset = $limit * $page;
$export = GETPOST('button_export', 'alpha') ? 1 : 0;

// Security check
if (!isModEnabled('anxhr')) {
	accessforbidden('Module not enabled');
}
if (!$user->hasRight('anxhr', 'vault', 'admin') && !$user->hasRight('anxhr', 'audit', 'read')) {
	http_response_code(403);
	accessforbidden();
}

$hookmanager->initHooks(array('anxhrvaultlog'));


/*
 * Actions
 */

$sqlfrom = " FROM ".$db->prefix()."anxhr_vault_access_log as l";
$sqlfrom .= " LEFT JOIN ".$db->prefix()."anxhr_vault_doc as d ON d.rowid = l.fk_doc";
$sqlfrom .= " LEFT JOIN ".$db->prefix()."user as u ON u.rowid = l.fk_user";
$sqlfrom .= " LEFT JOIN ".$db->prefix()."user as e ON e.rowid = d.fk_user";
$sqlfrom .= " WHERE l.entity IN (".getEntity('anxhr_vaultdoc').")";
if ($search_user > 0) {
	$sqlfrom .= " AND (l.fk_user = ".((int) $search_user)." OR d.fk_user = ".((int) $search_user).")";
}
if ($search_doc !== '') {
	if (preg_match('/^\d+$/', $search_doc)) {
		$sqlfrom .= " AND l.fk_doc = ".((int) $search_doc);
	} else {
		$sqlfrom .= natural_search('d.ref', $search_doc);
	}
}
if ($search_action !== '') {
	$sqlfrom .= " AND l.action = '".$db->escape($search_action)."'";
}
if ($search_date_start) {
	$sqlfrom .= " AND l.datec >= '".$db->idate($search_date_start)."'";
}
if ($search_date_end) {
	$sqlfrom .= " AND l.datec <= '".$db->idate($search_date_end)."'";
}
$sqlselect = "SELECT l.rowid, l.fk_doc, l.fk_user, l.action, l.ip, l.user_agent, l.datec, d.ref as docref, d.category, d.period, d.fk_user as doc_user,";
$sqlselect .= " u.login as actor_login, u.firstname as actor_firstname, u.lastname as actor_lastname,";
$sqlselect .= " e.login as emp_login, e.firstname as emp_firstname, e.lastname as emp_lastname";
$sqlorder = " ORDER BY l.datec DESC, l.rowid DESC";

/**
 * Protect a CSV cell against formula injection
 *
 * @param	string	$cell	Value
 * @return	string
 */
function anxhrVaultCsvCell($cell)
{
	$cell = (string) $cell;
	if ($cell !== '' && preg_match('/^[=+\-@\t\r]/', $cell) && !is_numeric($cell)) {
		$cell = "'".$cell;
	}
	return $cell;
}

if ($export) {
	$resql = $db->query($sqlselect.$sqlfrom.$sqlorder.$db->plimit(50000));
	if (!$resql) {
		dol_print_error($db);
		exit;
	}
	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="vault_access_log_'.dol_print_date(dol_now(), 'dayxcard').'.csv"');
	header('Cache-Control: no-store');
	header('X-Content-Type-Options: nosniff');
	$fh = fopen('php://output', 'w');
	fwrite($fh, "\xEF\xBB\xBF");
	fputcsv($fh, array($langs->transnoentitiesnoconv('Date'), $langs->transnoentitiesnoconv('User'), $langs->transnoentitiesnoconv('Login'), $langs->transnoentitiesnoconv('Action'), $langs->transnoentitiesnoconv('AnxhrVaultDocument'), $langs->transnoentitiesnoconv('AnxhrEmployee'), $langs->transnoentitiesnoconv('Category'), $langs->transnoentitiesnoconv('AnxhrVaultPeriod'), $langs->transnoentitiesnoconv('IPAddress'), $langs->transnoentitiesnoconv('UserAgent')), ';', '"', '\\');
	while ($obj = $db->fetch_object($resql)) {
		$line = array(
			dol_print_date($db->jdate($obj->datec), '%Y-%m-%d %H:%M:%S', 'tzuserrel'),
			dolGetFirstLastname((string) $obj->actor_firstname, (string) $obj->actor_lastname),
			(string) $obj->actor_login,
			$langs->transnoentitiesnoconv('AnxhrVaultLogAction_'.$obj->action),
			$obj->docref ? $obj->docref : ($obj->fk_doc ? '#'.$obj->fk_doc : ''),
			dolGetFirstLastname((string) $obj->emp_firstname, (string) $obj->emp_lastname),
			$obj->category ? anxhrVaultCategoryLabel($obj->category, $langs) : '',
			(string) $obj->period,
			(string) $obj->ip,
			(string) $obj->user_agent,
		);
		fputcsv($fh, array_map('anxhrVaultCsvCell', $line), ';', '"', '\\');
	}
	fclose($fh);
	$db->free($resql);
	exit;
}


/*
 * View
 */

$nbtotalofrecords = 0;
$resql = $db->query("SELECT COUNT(l.rowid) as nb".$sqlfrom);
if ($resql) {
	$obj = $db->fetch_object($resql);
	$nbtotalofrecords = $obj ? (int) $obj->nb : 0;
	$db->free($resql);
}
if ($offset > $nbtotalofrecords) {
	$page = 0;
	$offset = 0;
}
$rows = array();
$resql = $db->query($sqlselect.$sqlfrom.$sqlorder.$db->plimit($limit, $offset));
if ($resql) {
	while ($obj = $db->fetch_object($resql)) {
		$rows[] = $obj;
	}
	$db->free($resql);
} else {
	dol_print_error($db);
}

$form = new Form($db);
$title = $langs->trans('AnxhrVaultAccessLog');
llxHeader('', $title, '', '', 0, 0, '', array('/anxhr/css/anxhr_vault.css'), '', 'mod-anxhr page-vault-log');

print load_fiche_titre($langs->trans('AnxhrVault'), '', 'fa-shield-alt');
print dol_get_fiche_head(anxhrVaultPrepareHead(), 'log', '', -1, '');

$param = '';
if ($search_user > 0) {
	$param .= '&search_user='.((int) $search_user);
}
if ($search_doc !== '') {
	$param .= '&search_doc='.urlencode($search_doc);
}
if ($search_action !== '') {
	$param .= '&search_action='.urlencode($search_action);
}
if ($search_date_start) {
	$param .= '&search_date_startday='.((int) dol_print_date($search_date_start, '%d')).'&search_date_startmonth='.((int) dol_print_date($search_date_start, '%m')).'&search_date_startyear='.((int) dol_print_date($search_date_start, '%Y'));
}
if ($search_date_end) {
	$param .= '&search_date_endday='.((int) dol_print_date($search_date_end, '%d')).'&search_date_endmonth='.((int) dol_print_date($search_date_end, '%m')).'&search_date_endyear='.((int) dol_print_date($search_date_end, '%Y'));
}
if ($limit != $conf->liste_limit) {
	$param .= '&limit='.((int) $limit);
}

print '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" class="anxhr-vault-filters">';
print '<div class="anxhr-vault-filter"><label for="search_user">'.$langs->trans('User').'</label>'.img_picto('', 'user', 'class="pictofixedwidth"').$form->select_dolusers(($search_user > 0 ? $search_user : -1), 'search_user', 1, null, 0, '', '', '', 0, 0, '', 0, '', 'minwidth200 maxwidth300').'</div>';
print '<div class="anxhr-vault-filter"><label for="search_doc">'.$langs->trans('AnxhrVaultDocument').'</label><input type="text" name="search_doc" id="search_doc" class="flat maxwidth150" placeholder="VD-..." value="'.dol_escape_htmltag($search_doc).'"></div>';
print '<div class="anxhr-vault-filter"><label for="search_action">'.$langs->trans('Action').'</label><select name="search_action" id="search_action" class="flat minwidth100"><option value="">&nbsp;</option>';
foreach (VaultDoc::LOG_ACTIONS as $a) {
	print '<option value="'.$a.'"'.($search_action === $a ? ' selected' : '').'>'.dol_escape_htmltag($langs->trans('AnxhrVaultLogAction_'.$a)).'</option>';
}
print '</select></div>';
print '<div class="anxhr-vault-filter"><label>'.$langs->trans('DateStart').'</label>'.$form->selectDate($search_date_start ? $search_date_start : -1, 'search_date_start', 0, 0, 1, '', 1, 0).'</div>';
print '<div class="anxhr-vault-filter"><label>'.$langs->trans('DateEnd').'</label>'.$form->selectDate($search_date_end ? $search_date_end : -1, 'search_date_end', 0, 0, 1, '', 1, 0).'</div>';
print '<div class="anxhr-vault-filter anxhr-vault-filter-buttons">'.$form->showFilterButtons();
print ' <button type="submit" name="button_export" value="1" class="button smallpaddingimp">'.img_picto('', 'fa-file-csv', 'class="pictofixedwidth"').$langs->trans('Export').'</button></div>';
print '</form>';

print_barre_liste($langs->trans('AnxhrVaultAccessLog'), $page, $_SERVER['PHP_SELF'], $param, '', '', '', count($rows), $nbtotalofrecords, 'fa-history', 0, '', '', $limit, 0, 0, 1);

$actionbadges = array('upload' => 'badge-status4', 'view' => 'badge-status1', 'download' => 'badge-status1', 'delete' => 'badge-status6', 'denied' => 'badge-status8');
print '<div class="div-table-responsive">';
print '<table class="tagtable noborder centpercent liste">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Date').'</th>';
print '<th>'.$langs->trans('User').'</th>';
print '<th class="center">'.$langs->trans('Action').'</th>';
print '<th>'.$langs->trans('AnxhrVaultDocument').'</th>';
print '<th class="hideonsmartphone">'.$langs->trans('AnxhrEmployee').'</th>';
print '<th class="hideonsmartphone">'.$langs->trans('IPAddress').'</th>';
print '<th class="hideonsmartphone">'.$langs->trans('UserAgent').'</th>';
print '</tr>';
if (empty($rows)) {
	print '<tr class="oddeven"><td colspan="7"><div class="anxhr-vault-empty">'.img_picto('', 'fa-history', 'class="pictofixedwidth"').$langs->trans('AnxhrVaultNoLog').'</div></td></tr>';
}
foreach ($rows as $obj) {
	$badge = isset($actionbadges[$obj->action]) ? $actionbadges[$obj->action] : 'badge-status0';
	print '<tr class="oddeven">';
	print '<td class="nowraponall">'.dol_print_date($db->jdate($obj->datec), 'dayhoursec', 'tzuserrel').'</td>';
	print '<td class="tdoverflowmax150">'.dol_escape_htmltag(dolGetFirstLastname((string) $obj->actor_firstname, (string) $obj->actor_lastname)).($obj->actor_login ? ' <span class="opacitymedium small">'.dol_escape_htmltag($obj->actor_login).'</span>' : '').'</td>';
	print '<td class="center"><span class="badge '.$badge.'">'.dol_escape_htmltag($langs->trans('AnxhrVaultLogAction_'.$obj->action)).'</span></td>';
	print '<td class="nowraponall">'.($obj->docref ? dol_escape_htmltag($obj->docref) : ($obj->fk_doc ? '<span class="opacitymedium">#'.((int) $obj->fk_doc).'</span>' : '')).($obj->category ? ' '.anxhrVaultCategoryBadge($obj->category, $langs) : '').'</td>';
	print '<td class="tdoverflowmax150 hideonsmartphone">'.dol_escape_htmltag(dolGetFirstLastname((string) $obj->emp_firstname, (string) $obj->emp_lastname)).'</td>';
	print '<td class="nowraponall hideonsmartphone">'.dol_escape_htmltag((string) $obj->ip).'</td>';
	print '<td class="tdoverflowmax200 hideonsmartphone" title="'.dolPrintHTMLForAttribute((string) $obj->user_agent).'">'.dol_escape_htmltag((string) $obj->user_agent).'</td>';
	print '</tr>';
}
print '</table>';
print '</div>';

print dol_get_fiche_end();

llxFooter();
$db->close();
