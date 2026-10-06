<?php
/* Copyright (C) 2001-2005	Rodolphe Quiedeville	<rodolphe@quiedeville.org>
 * Copyright (C) 2004-2015	Laurent Destailleur		<eldy@users.sourceforge.net>
 * Copyright (C) 2026		ANX HR contributors
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
 *	\file       htdocs/custom/anxhr/anxhrindex.php
 *	\ingroup    anxhr
 *	\brief      HR cockpit (home page of the HR top menu)
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
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
dol_include_once('/anxhr/lib/anxhr.lib.php');

// Load translation files required by the page
$langs->loadLangs(array("anxhr@anxhr", "anxhr_hr@anxhr", "anxhr_time@anxhr", "users", "hrm"));

$action = GETPOST('action', 'aZ09');

// Security check
if (!isModEnabled('anxhr')) {
	accessforbidden('Module not enabled');
}
$canreaddeadline = $user->hasRight('anxhr', 'deadline', 'read');
$canreadchecklist = $user->hasRight('anxhr', 'checklist', 'read');
$cantimeadmin = $user->hasRight('anxhr', 'time', 'admin');
$cantimeapprove = $user->hasRight('anxhr', 'time', 'approve');
$canreadown = $user->hasRight('anxhr', 'employee', 'read_own');
$canreadall = $user->hasRight('anxhr', 'employee', 'read_all');
$cantimeown = $user->hasRight('anxhr', 'time', 'own');
if (!$canreaddeadline && !$canreadchecklist && !$cantimeadmin && !$cantimeapprove && !$canreadown && !$canreadall && !$cantimeown) {
	accessforbidden();
}

$hookmanager->initHooks(array('anxhrindex'));

$now = dol_now();
$today = dol_get_first_hour($now);
$limitdays = 30;
$max = 10;

/**
 * Return SQL restriction on fk_user for time data depending on rights (admin: all, approver: subordinates).
 *
 * @param	User	$user			Current user
 * @param	bool	$cantimeadmin	Has time admin right
 * @param	string	$field			Field name
 * @return	string					SQL condition starting with ' AND ' or '' if no restriction
 */
function anxhrCockpitUserScope($user, $cantimeadmin, $field)
{
	if ($cantimeadmin) {
		return '';
	}
	$ids = anxhrGetSubordinateIds($user);
	if (empty($ids)) {
		return ' AND 1 = 0';
	}

	return ' AND '.$field.' IN ('.implode(',', array_map('intval', $ids)).')';
}

/**
 * Run a COUNT query and return the value (or null on error)
 *
 * @param	DoliDB	$db		Database handler
 * @param	string	$sql	SQL with a column named nb
 * @return	?int
 */
function anxhrCockpitCount($db, $sql)
{
	$resql = $db->query($sql);
	if (!$resql) {
		dol_syslog('anxhrindex count error '.$db->lasterror(), LOG_WARNING);
		return null;
	}
	$obj = $db->fetch_object($resql);
	$db->free($resql);

	return $obj ? (int) $obj->nb : 0;
}


/*
 * Actions
 */

// None


/*
 * Data loading (read only)
 */

$kpis = array();
$deadlines = array();
$missingclockout = array();

// Deadlines in the next 30 days (overdue included)
if ($canreaddeadline && anxhrTableExists($db, 'anxhr_deadline')) {
	$sqlwhere = " FROM ".$db->prefix()."anxhr_deadline as d";
	$sqlwhere .= " LEFT JOIN ".$db->prefix()."user as u ON u.rowid = d.fk_user";
	$sqlwhere .= " WHERE d.status = 0";
	$sqlwhere .= " AND d.entity IN (".getEntity('anxhr_deadline').")";
	$sqlwhere .= " AND d.date_due <= '".$db->idate(dol_time_plus_duree($today, $limitdays, 'd'))."'";

	$kpis['deadlines'] = array(
		'label' => $langs->trans('AnxhrKpiDeadlines30'),
		'value' => anxhrCockpitCount($db, "SELECT COUNT(d.rowid) as nb".$sqlwhere),
		'url' => dol_buildpath('/anxhr/deadline_list.php', 1),
		'picto' => 'fa-calendar-check',
	);

	$sql = "SELECT d.rowid, d.label, d.type, d.date_due, d.fk_user,";
	$sql .= " u.firstname, u.lastname, u.login, u.statut as ustatus, u.photo, u.email, u.gender";
	$sql .= $sqlwhere;
	$sql .= " ORDER BY d.date_due ASC";
	$sql .= $db->plimit($max);
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$deadlines[] = $obj;
		}
		$db->free($resql);
	} else {
		dol_syslog('anxhrindex deadlines error '.$db->lasterror(), LOG_WARNING);
	}
}

// Checklists in progress
if ($canreadchecklist && anxhrTableExists($db, 'anxhr_checklist')) {
	$sql = "SELECT COUNT(c.rowid) as nb FROM ".$db->prefix()."anxhr_checklist as c";
	$sql .= " WHERE c.entity IN (".getEntity('anxhr_checklist').")";
	$sql .= " AND c.status = 0";
	$kpis['checklists'] = array(
		'label' => $langs->trans('AnxhrKpiChecklistsInProgress'),
		'value' => anxhrCockpitCount($db, $sql),
		'url' => dol_buildpath('/anxhr/checklist_list.php', 1),
		'picto' => 'fa-tasks',
	);
}

// Open time corrections
if (($cantimeadmin || $cantimeapprove) && anxhrTableExists($db, 'anxhr_time_correction')) {
	$sql = "SELECT COUNT(tc.rowid) as nb FROM ".$db->prefix()."anxhr_time_correction as tc";
	$sql .= " WHERE tc.entity IN (".getEntity('anxhr_time_correction').")";
	$sql .= " AND tc.status = 0";
	$sql .= anxhrCockpitUserScope($user, $cantimeadmin, 'tc.fk_user');
	$kpis['corrections'] = array(
		'label' => $langs->trans('AnxhrKpiOpenCorrections'),
		'value' => anxhrCockpitCount($db, $sql),
		'url' => dol_buildpath('/anxhr/time_corrections.php', 1),
		'picto' => 'fa-edit',
	);
}

// Monthly periods awaiting approval (confirmed by employee)
if (($cantimeadmin || $cantimeapprove) && anxhrTableExists($db, 'anxhr_time_period')) {
	$sql = "SELECT COUNT(tp.rowid) as nb FROM ".$db->prefix()."anxhr_time_period as tp";
	$sql .= " WHERE tp.entity IN (".getEntity('anxhr_time_period').")";
	$sql .= " AND tp.status = 1";
	$sql .= anxhrCockpitUserScope($user, $cantimeadmin, 'tp.fk_user');
	$kpis['periods'] = array(
		'label' => $langs->trans('AnxhrKpiPeriodsToApprove'),
		'value' => anxhrCockpitCount($db, $sql),
		'url' => dol_buildpath('/anxhr/time_periods.php', 1),
		'picto' => 'fa-calendar-alt',
	);
}

// Employees with missing clock-out yesterday (last entry of yesterday is not 'out')
if (($cantimeadmin || $cantimeapprove) && anxhrTableExists($db, 'anxhr_time_entry')) {
	$ystart = dol_time_plus_duree($today, -1, 'd');
	$yend = $today - 1;
	$scope = anxhrCockpitUserScope($user, $cantimeadmin, 'e.fk_user');

	$sql = "SELECT e.fk_user, e.entry_type, e.entry_datetime, u.firstname, u.lastname, u.login, u.statut as ustatus, u.photo, u.email, u.gender";
	$sql .= " FROM ".$db->prefix()."anxhr_time_entry as e";
	$sql .= " INNER JOIN (SELECT e2.fk_user, MAX(e2.entry_datetime) as maxdt FROM ".$db->prefix()."anxhr_time_entry as e2";
	$sql .= " WHERE e2.status = 1 AND e2.entity IN (".getEntity('anxhr_time_entry').")";
	$sql .= " AND e2.entry_datetime BETWEEN '".$db->idate($ystart)."' AND '".$db->idate($yend)."'";
	$sql .= " GROUP BY e2.fk_user) as m ON m.fk_user = e.fk_user AND m.maxdt = e.entry_datetime";
	$sql .= " LEFT JOIN ".$db->prefix()."user as u ON u.rowid = e.fk_user";
	$sql .= " WHERE e.status = 1 AND e.entry_type <> 'out'";
	$sql .= $scope;
	$sql .= " ORDER BY u.lastname ASC";
	$sql .= $db->plimit(100);
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$missingclockout[(int) $obj->fk_user] = $obj;
		}
		$db->free($resql);
	} else {
		dol_syslog('anxhrindex missing clock-out error '.$db->lasterror(), LOG_WARNING);
	}
	$kpis['missingout'] = array(
		'label' => $langs->trans('AnxhrKpiMissingClockOut'),
		'value' => count($missingclockout),
		'url' => dol_buildpath('/anxhr/time_day.php', 1),
		'picto' => 'fa-user-clock',
	);
}


/*
 * View
 */

$userstatic = new User($db);

/**
 * Fill a reusable User object from a SQL row (no extra query)
 *
 * @param	User		$userstatic		User object to fill
 * @param	stdClass	$obj			Row with fk_user, firstname, lastname, login, ustatus, photo, email, gender
 * @return	User
 */
function anxhrCockpitFillUser($userstatic, $obj)
{
	$userstatic->id = (int) $obj->fk_user;
	$userstatic->firstname = (string) $obj->firstname;
	$userstatic->lastname = (string) $obj->lastname;
	$userstatic->login = (string) $obj->login;
	$userstatic->status = (int) $obj->ustatus;
	$userstatic->statut = (int) $obj->ustatus;
	$userstatic->photo = (string) $obj->photo;
	$userstatic->email = (string) $obj->email;
	$userstatic->gender = (string) $obj->gender;

	return $userstatic;
}

llxHeader("", $langs->trans("AnxhrCockpit"), '', '', 0, 0, '', '', '', 'mod-anxhr page-index');

print load_fiche_titre($langs->trans("AnxhrCockpit"), '', 'fa-id-card');

// KPI tiles
if (!empty($kpis)) {
	print '<div class="anxhr-kpi-grid">';
	foreach ($kpis as $code => $kpi) {
		$value = ($kpi['value'] === null ? '-' : (int) $kpi['value']);
		$cls = (is_int($value) && $value > 0 ? ' anxhr-kpi-attention' : ' anxhr-kpi-zero');
		$url = $kpi['url'].(strpos($kpi['url'], '?') === false ? '?' : '&').'mainmenu=anxhr';
		print '<a class="anxhr-kpi'.$cls.'" id="anxhr-kpi-'.dol_escape_htmltag($code).'" href="'.dol_escape_htmltag($url).'">';
		print '<span class="anxhr-kpi-picto" aria-hidden="true">'.img_picto('', $kpi['picto']).'</span>';
		print '<span class="anxhr-kpi-value">'.$value.'</span>';
		print '<span class="anxhr-kpi-label">'.dol_escape_htmltag($kpi['label']).'</span>';
		print '<span class="anxhr-kpi-link">'.$langs->trans('AnxhrKpiShowList').img_picto('', 'fa-angle-right', 'class="marginleftonlyshort"').'</span>';
		print '</a>';
	}
	print '</div>';
}

print '<div class="fichecenter">';

// Left column: personal shortcuts
print '<div class="fichethirdleft">';

print '<div class="div-table-responsive-no-min">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>'.$langs->trans("AnxhrMySpace").'</th></tr>';
if ($canreadown || $canreadall) {
	print '<tr class="oddeven"><td>'.img_picto('', 'fa-id-card', 'class="pictofixedwidth"');
	print '<a href="'.dol_buildpath('/anxhr/user_tab.php', 1).'?id='.((int) $user->id).'">'.$langs->trans("AnxhrMyHrFile").'</a></td></tr>';
}
if ($cantimeown) {
	print '<tr class="oddeven"><td>'.img_picto('', 'fa-clock', 'class="pictofixedwidth"');
	print '<a href="'.dol_buildpath('/anxhr/time_clock.php', 1).'?mainmenu=anxhr">'.$langs->trans("AnxhrMyTime").'</a></td></tr>';
}
if (isModEnabled('holiday') && $user->hasRight('holiday', 'write')) {
	print '<tr class="oddeven"><td>'.img_picto('', 'holiday', 'class="pictofixedwidth"');
	print '<a href="'.DOL_URL_ROOT.'/holiday/card.php?action=create&mainmenu=anxhr">'.$langs->trans("AnxhrNewLeaveRequest").'</a></td></tr>';
}
if (isModEnabled('expensereport') && $user->hasRight('expensereport', 'creer')) {
	print '<tr class="oddeven"><td>'.img_picto('', 'trip', 'class="pictofixedwidth"');
	print '<a href="'.DOL_URL_ROOT.'/expensereport/card.php?action=create&mainmenu=anxhr">'.$langs->trans("AnxhrNewExpenseReport").'</a></td></tr>';
}
print '</table>';
print '</div>';

// Missing clock-out list (with an empty state when the user may see this information)
if (isset($kpis['missingout']) && empty($missingclockout)) {
	print '<br>';
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th>'.$langs->trans("AnxhrKpiMissingClockOut").'</th></tr>';
	print '<tr class="oddeven"><td>'.img_picto('', 'fa-check', 'class="pictofixedwidth opacitymedium"').'<span class="opacitymedium">'.$langs->trans("AnxhrNoMissingClockOut").'</span></td></tr>';
	print '</table>';
	print '</div>';
}
if (!empty($missingclockout)) {
	print '<br>';
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th colspan="2">'.$langs->trans("AnxhrKpiMissingClockOut").' <span class="badge marginleftonlyshort">'.count($missingclockout).'</span></th></tr>';
	$i = 0;
	foreach ($missingclockout as $obj) {
		if ($i++ >= $max) {
			break;
		}
		anxhrCockpitFillUser($userstatic, $obj);
		print '<tr class="oddeven">';
		print '<td class="tdoverflowmax200">'.$userstatic->getNomUrl(-1).'</td>';
		print '<td class="right nowraponall"><a href="'.dol_buildpath('/anxhr/time_day.php', 1).'?id='.((int) $obj->fk_user).'&mainmenu=anxhr" title="'.dolPrintHTMLForAttribute($langs->trans('AnxhrMonthView')).'">'.dol_print_date($db->jdate($obj->entry_datetime), 'dayhour', 'tzuserrel').'</a></td>';
		print '</tr>';
	}
	print '</table>';
	print '</div>';
}

print '</div>';

// Right column: next deadlines
print '<div class="fichetwothirdright">';

if ($canreaddeadline) {
	print '<div class="div-table-responsive-no-min">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre">';
	print '<th>'.img_picto('', 'fa-calendar-check', 'class="pictofixedwidth"').$langs->trans("AnxhrNextDeadlines").'</th>';
	print '<th class="hideonsmartphone">'.$langs->trans("Employee").'</th>';
	print '<th class="right hideonsmartphone">'.$langs->trans("AnxhrDateDue").'</th>';
	print '<th class="right"><a href="'.dol_buildpath('/anxhr/deadline_list.php', 1).'?mainmenu=anxhr">'.$langs->trans("AnxhrShowAllDeadlines").'</a></th>';
	print '</tr>';
	if (empty($deadlines)) {
		print '<tr class="oddeven"><td colspan="4">'.img_picto('', 'fa-check', 'class="pictofixedwidth opacitymedium"').'<span class="opacitymedium">'.$langs->trans("AnxhrNoDeadlineSoon").'</span></td></tr>';
	}
	foreach ($deadlines as $obj) {
		$datedue = $db->jdate($obj->date_due);
		anxhrCockpitFillUser($userstatic, $obj);
		print '<tr class="oddeven">';
		$dlabel = ($obj->label ? $obj->label : anxhrDeadlineTypeLabel($obj->type));
		print '<td class="tdoverflowmax250" title="'.dolPrintHTMLForAttribute($dlabel).'">';
		print '<a href="'.dol_buildpath('/anxhr/deadline_card.php', 1).'?id='.((int) $obj->rowid).'&mainmenu=anxhr">'.img_picto('', 'fa-hourglass-half', 'class="pictofixedwidth"');
		print dol_escape_htmltag($dlabel).'</a>';
		print '</td>';
		print '<td class="tdoverflowmax150 hideonsmartphone">'.$userstatic->getNomUrl(-1).'</td>';
		print '<td class="right nowraponall hideonsmartphone">'.dol_print_date($datedue, 'day').'</td>';
		print '<td class="right nowraponall" title="'.dolPrintHTMLForAttribute(dol_print_date($datedue, 'day')).'">'.anxhrDueBadge($datedue, $today).'</td>';
		print '</tr>';
	}
	print '</table>';
	print '</div>';
} else {
	print '<div class="opacitymedium">'.$langs->trans("AnxhrCockpitWelcome").'</div>';
}

print '</div>';

print '</div>';

$parameters = array('user' => $user);
$object = null;
$reshook = $hookmanager->executeHooks('dashboardAnxhr', $parameters, $object, $action);
print $hookmanager->resPrint;

// End of page
llxFooter();
$db->close();
