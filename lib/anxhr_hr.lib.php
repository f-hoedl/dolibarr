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
 * \file        htdocs/custom/anxhr/lib/anxhr_hr.lib.php
 * \ingroup     anxhr
 * \brief       Business helpers of ANX HR core: contracts, deadlines, checklists, handovers, card heads and list helpers
 */


/*
 * Generic object helpers
 */

/**
 * Return next reference of type PREFIX-<userid>-<n> for a table
 *
 * @param  DoliDB $db      Database handler
 * @param  string $table   Table name without prefix
 * @param  string $prefix  Ref prefix (CT, HO, CL)
 * @param  int    $fk_user Employee id
 * @return string          New reference
 */
function anxhrGetNextUserRef($db, $table, $prefix, $fk_user)
{
	$base = $prefix.'-'.((int) $fk_user).'-';
	$sql = "SELECT ref FROM ".$db->prefix().$db->sanitize($table);
	$sql .= " WHERE ref LIKE '".$db->escape($db->escapeforlike($base))."%'";
	$sql .= " AND entity IN (".getEntity($table).")";
	$max = 0;
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$num = (int) substr($obj->ref, strlen($base));
			if ($num > $max) {
				$max = $num;
			}
		}
		$db->free($resql);
	}
	return $base.($max + 1);
}

/**
 * Build the HTML link of an ANX HR object (shared implementation of getNomUrl)
 *
 * @param  CommonObject             $object                Object
 * @param  string                   $path                  Relative path of card (ex '/anxhr/contract_card.php')
 * @param  string                   $text                  Text of link
 * @param  int                      $withpicto             0=No picto, 1=Include picto, 2=Only picto
 * @param  string                   $option                'nolink' or ''
 * @param  int                      $notooltip             1=Disable tooltip
 * @param  string                   $morecss               More css on link
 * @param  int                      $save_lastsearch_value -1=Auto, 0=No, 1=Yes
 * @param  array<string,int|string> $query                 Query parameters (default: id => object id)
 * @return string
 */
function anxhrBuildNomUrl($object, $path, $text, $withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1, $query = null)
{
	global $conf, $langs, $hookmanager, $action;

	if (!empty($conf->dol_no_mouse_hover)) {
		$notooltip = 1;
	}
	$params = array('id' => (string) $object->id, 'objecttype' => $object->element.'@anxhr', 'option' => $option);
	$classfortooltip = 'classfortooltip';
	$dataparams = '';
	if (getDolGlobalInt('MAIN_ENABLE_AJAX_TOOLTIP')) {
		$classfortooltip = 'classforajaxtooltip';
		$dataparams = ' data-params="'.dol_escape_htmltag(json_encode($params)).'"';
		$label = '';
	} else {
		$label = implode($object->getTooltipContentArray($params));
	}

	if ($query === null) {
		$query = array('id' => (int) $object->id);
	}
	if ($option !== 'nolink') {
		$add_save_lastsearch_values = ($save_lastsearch_value == 1 ? 1 : 0);
		if ($save_lastsearch_value == -1 && isset($_SERVER["PHP_SELF"]) && preg_match('/list\.php/', $_SERVER["PHP_SELF"])) {
			$add_save_lastsearch_values = 1;
		}
		if ($add_save_lastsearch_values) {
			$query['save_lastsearch_values'] = 1;
		}
	}
	$url = dol_buildpath($path, 1).'?'.http_build_query($query);

	$linkclose = '';
	if (empty($notooltip)) {
		$linkclose .= ($label ? ' title="'.dolPrintHTMLForAttribute($label).'"' : ' title="tocomplete"');
		$linkclose .= $dataparams.' class="'.$classfortooltip.($morecss ? ' '.$morecss : '').'"';
	} else {
		$linkclose = ($morecss ? ' class="'.$morecss.'"' : '');
	}
	$linkstart = ($option == 'nolink' ? '<span' : '<a href="'.$url.'"').$linkclose.'>';
	$linkend = ($option == 'nolink' ? '</span>' : '</a>');

	$result = $linkstart;
	if ($withpicto) {
		$result .= img_object(($notooltip ? '' : $label), ($object->picto ? $object->picto : 'generic'), (($withpicto != 2) ? 'class="paddingright"' : ''), 0, 0, $notooltip ? 0 : 1);
	}
	if ($withpicto != 2) {
		$result .= dol_escape_htmltag($text);
	}
	$result .= $linkend;

	if (is_object($hookmanager)) {
		$hookmanager->initHooks(array($object->element.'dao'));
		$parameters = array('id' => $object->id, 'getnomurl' => &$result);
		$reshook = $hookmanager->executeHooks('getNomUrl', $parameters, $object, $action);
		if ($reshook > 0) {
			$result = $hookmanager->resPrint;
		} else {
			$result .= $hookmanager->resPrint;
		}
	}
	return $result;
}

/**
 * Load creation/modification info of an object (used by dol_print_object_info)
 *
 * @param  CommonObject $object Object
 * @param  int          $id     Id of record
 * @return void
 */
function anxhrLoadInfo($object, $id)
{
	$db = $object->db;
	$sql = "SELECT t.rowid, t.date_creation as datec, t.tms as datem, t.fk_user_creat, t.fk_user_modif";
	$sql .= " FROM ".$db->prefix().$object->table_element." as t WHERE t.rowid = ".((int) $id);
	$resql = $db->query($sql);
	if (!$resql) {
		dol_print_error($db);
		return;
	}
	$obj = $db->fetch_object($resql);
	if ($obj) {
		$object->id = (int) $obj->rowid;
		$object->user_creation_id = $obj->fk_user_creat;
		$object->user_modification_id = $obj->fk_user_modif;
		$object->date_creation = $db->jdate($obj->datec);
		$object->date_modification = empty($obj->datem) ? '' : $db->jdate($obj->datem);
	}
	$db->free($resql);
}

/**
 * Clean a list of reminder days ("60, 30,14" -> "60,30,14"), sorted descending, unique, positive
 *
 * @param  string $str Raw value
 * @return string      Cleaned value
 */
function anxhrCleanRemindDays($str)
{
	$days = array();
	foreach (explode(',', (string) $str) as $part) {
		$part = trim($part);
		if ($part !== '' && is_numeric($part) && (int) $part >= 0) {
			$days[(int) $part] = (int) $part;
		}
	}
	krsort($days);
	return implode(',', $days);
}

/**
 * Return number of days between today (in user timezone) and a date (date field loaded from database).
 * 0 = today, negative = in the past.
 *
 * @param  int $ts Timestamp of a date field
 * @return int     Number of days
 */
function anxhrDaysFromToday($ts)
{
	$d = explode('-', dol_print_date($ts, '%Y-%m-%d', 'tzserver'));
	$t = explode('-', dol_print_date(dol_now(), '%Y-%m-%d', 'tzuserrel'));
	$due = gmmktime(0, 0, 0, (int) $d[1], (int) $d[2], (int) $d[0]);
	$today = gmmktime(0, 0, 0, (int) $t[1], (int) $t[2], (int) $t[0]);
	return (int) round(($due - $today) / 86400);
}

/**
 * Return active handover categories of dictionary llx_c_anxhr_handover_cat (code => label). Cached.
 *
 * @param  DoliDB $db Database handler
 * @return array<string,string>
 */
function anxhrGetHandoverCategories($db)
{
	global $langs;
	static $cache = null;

	if ($cache !== null) {
		return $cache;
	}
	$cache = array();
	$sql = "SELECT code, label FROM ".$db->prefix()."c_anxhr_handover_cat WHERE active = 1 AND entity IN (".getEntity('c_anxhr_handover_cat').") ORDER BY code";
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$key = 'AnxhrHandoverCat'.ucfirst(strtolower($obj->code));
			$cache[$obj->code] = ($langs->trans($key) != $key ? $langs->trans($key) : $obj->label);
		}
		$db->free($resql);
	} else {
		dol_syslog(__FUNCTION__.' dictionary c_anxhr_handover_cat not available, using built-in categories', LOG_DEBUG);
	}
	return $cache;
}

/**
 * Load a set of users with one query (to avoid one fetch per line in lists)
 *
 * @param  DoliDB $db  Database handler
 * @param  int[]  $ids User ids
 * @return array<int,User>
 */
function anxhrLoadUsersCache($db, $ids)
{
	require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

	$users = array();
	$ids = array_filter(array_unique(array_map('intval', $ids)));
	if (empty($ids)) {
		return $users;
	}
	$sql = "SELECT u.rowid, u.login, u.lastname, u.firstname, u.email, u.statut as status, u.photo, u.gender, u.employee, u.entity, u.admin";
	$sql .= " FROM ".$db->prefix()."user as u WHERE u.rowid IN (".$db->sanitize(implode(',', $ids)).")";
	$resql = $db->query($sql);
	if (!$resql) {
		return $users;
	}
	while ($obj = $db->fetch_object($resql)) {
		$tmp = new User($db);
		$tmp->id = (int) $obj->rowid;
		$tmp->login = $obj->login;
		$tmp->lastname = $obj->lastname;
		$tmp->firstname = $obj->firstname;
		$tmp->email = $obj->email;
		$tmp->status = (int) $obj->status;
		$tmp->statut = (int) $obj->status;
		$tmp->photo = $obj->photo;
		$tmp->gender = $obj->gender;
		$tmp->employee = $obj->employee;
		$tmp->entity = $obj->entity;
		$tmp->admin = $obj->admin;
		$users[$tmp->id] = $tmp;
	}
	$db->free($resql);
	return $users;
}


/*
 * Permissions
 */

/**
 * Return true if user can read records of an object type for a given employee.
 * Allowed with right anxhr/<object>/read, or with anxhr/employee/read_own for own records.
 *
 * @param  User   $user      User
 * @param  string $objectkey Rights object key (contract, deadline, handover, checklist)
 * @param  int    $fk_user   Employee of the record (0 = unknown / list)
 * @return bool
 */
function anxhrHrCanRead($user, $objectkey, $fk_user = 0)
{
	if ($user->hasRight('anxhr', $objectkey, 'read')) {
		return true;
	}
	return ($fk_user > 0 && $fk_user == $user->id && $user->hasRight('anxhr', 'employee', 'read_own'));
}


/*
 * Business functions
 */

/**
 * Create or update the automatic deadlines of a contract (probation end, fixed term end, contract end).
 * Deadlines are matched per employee and type (open ones), so a new contract version updates
 * the existing deadline instead of creating a duplicate. When a deadline date is removed from the
 * contract, the matching open automatic deadline is canceled.
 * A contract end also instantiates the offboarding checklist (first enabled offboarding template)
 * if no offboarding checklist exists yet for the employee.
 *
 * @param  HrContract $c    Contract (saved, with id)
 * @param  User       $user User acting
 * @return int              Number of deadlines created or updated, <0 if KO
 */
function anxhrGenerateDeadlinesForContract($c, $user)
{
	global $langs;

	require_once __DIR__.'/../class/hrdeadline.class.php';
	$langs->load('anxhr_hr@anxhr');
	$db = $c->db;

	$employee = new User($db);
	$employee->fetch((int) $c->fk_user);
	$creatorid = ($c->fk_user_creat > 0 ? (int) $c->fk_user_creat : (int) $user->id);
	$hrid = getDolGlobalInt('ANXHR_HR_DEFAULT_USER') > 0 ? getDolGlobalInt('ANXHR_HR_DEFAULT_USER') : $creatorid;

	$specs = array(
		'probation_end' => array('date' => $c->probation_end, 'remind' => '30,14', 'responsible' => ($employee->fk_user > 0 ? (int) $employee->fk_user : $creatorid), 'label' => 'AnxhrDeadlineTypeProbationEnd'),
		'fixed_term_end' => array('date' => $c->fixed_term_end, 'remind' => '60,30,14', 'responsible' => $hrid, 'label' => 'AnxhrDeadlineTypeFixedTermEnd'),
		'contract_end' => array('date' => $c->date_end, 'remind' => '30,14,7', 'responsible' => $hrid, 'label' => 'AnxhrDeadlineTypeContractEnd'),
	);

	// Load existing open automatic deadlines of employee with one query
	$existing = array();
	$sql = "SELECT rowid, type FROM ".$db->prefix()."anxhr_deadline";
	$sql .= " WHERE fk_user = ".((int) $c->fk_user)." AND status = ".HrDeadline::STATUS_OPEN;
	$sql .= " AND type IN ('probation_end','fixed_term_end','contract_end') AND entity IN (".getEntity('anxhr_deadline').")";
	$sql .= " ORDER BY rowid DESC";
	$resql = $db->query($sql);
	if (!$resql) {
		$c->error = $db->lasterror();
		return -1;
	}
	while ($obj = $db->fetch_object($resql)) {
		if (!isset($existing[$obj->type])) {
			$existing[$obj->type] = (int) $obj->rowid;
		}
	}
	$db->free($resql);

	$nb = 0;
	foreach ($specs as $type => $spec) {
		$deadline = new HrDeadline($db);
		if (!empty($existing[$type])) {
			$deadline->fetch($existing[$type]);
		}
		if (empty($spec['date'])) {
			if ($deadline->id > 0 && $deadline->fk_contract == $c->id && $deadline->cancel($user) < 0) {
				$c->setErrorsFromObject($deadline);
				return -1;
			}
			continue;
		}
		$label = $langs->transnoentitiesnoconv($spec['label']).' - '.$employee->getFullName($langs);
		if ($deadline->id > 0) {
			if ($deadline->date_due == $spec['date'] && $deadline->fk_contract == $c->id) {
				continue;
			}
			$deadline->date_due = $spec['date'];
			$deadline->fk_contract = $c->id;
			$deadline->last_remind_date = '';
			$result = $deadline->update($user);
		} else {
			$deadline->fk_user = (int) $c->fk_user;
			$deadline->fk_contract = (int) $c->id;
			$deadline->type = $type;
			$deadline->label = dol_trunc($label, 250, 'right', 'UTF-8', 1);
			$deadline->date_due = $spec['date'];
			$deadline->remind_days = $spec['remind'];
			$deadline->fk_user_responsible = $spec['responsible'];
			$result = $deadline->create($user);
		}
		if ($result < 0) {
			$c->setErrorsFromObject($deadline);
			return -1;
		}
		$nb++;
		dol_syslog(__FUNCTION__." contract ".$c->ref." deadline ".$type." set to ".dol_print_date($spec['date'], 'day'), LOG_DEBUG);
	}

	if (!empty($c->date_end) && !getDolGlobalInt('ANXHR_DISABLE_AUTO_OFFBOARDING')) {
		if (anxhrInstantiateChecklistOfType($db, $user, (int) $c->fk_user, 'offboarding', $c->date_end) < 0) {
			return -1;
		}
	}
	return $nb;
}

/**
 * Instantiate the onboarding checklist of a contract (first enabled onboarding template,
 * anchor = contract start) if no onboarding checklist exists yet for the employee.
 *
 * @param  HrContract $c    Contract
 * @param  User       $user User acting
 * @return int              Id of checklist created, 0 if nothing done, <0 if KO
 */
function anxhrInstantiateOnboarding($c, $user)
{
	return anxhrInstantiateChecklistOfType($c->db, $user, (int) $c->fk_user, 'onboarding', $c->date_start);
}

/**
 * Instantiate the first enabled template of a type for an employee if no checklist of this type exists yet
 *
 * @param  DoliDB $db         Database handler
 * @param  User   $user       User acting
 * @param  int    $fk_user    Employee id
 * @param  string $type       onboarding, offboarding or transfer
 * @param  int    $anchorDate Anchor date (timestamp)
 * @return int                Id of checklist created, 0 if nothing done, <0 if KO
 */
function anxhrInstantiateChecklistOfType($db, $user, $fk_user, $type, $anchorDate)
{
	require_once __DIR__.'/../class/hrchecklist.class.php';

	$checklist = new HrChecklist($db);
	if ($checklist->existsForUser($fk_user, $type)) {
		return 0;
	}
	$tpl = new HrChecklistTemplate($db);
	$res = $tpl->fetchFirstActiveOfType($type);
	if ($res <= 0) {
		return ($res < 0 ? -1 : 0);
	}
	$result = $checklist->instantiateFromTemplate($user, $tpl, $fk_user, $anchorDate);
	if ($result > 0) {
		dol_syslog(__FUNCTION__." checklist ".$checklist->ref." (".$type.") created for user ".$fk_user." from template ".$tpl->ref, LOG_INFO);
	}
	return $result;
}

/**
 * Write denormalized contract values into llx_user (weeklyhours, dateemployment, dateemploymentend)
 *
 * @param  HrContract $c    Contract
 * @param  User       $user User acting
 * @return int              <0 if KO, >0 if OK
 */
function anxhrSyncUserFromContract($c, $user)
{
	return $c->setUserDenormalizedFields($user);
}


/*
 * Card heads
 */

/**
 * Generic head builder (card + optional documents + info)
 *
 * @param  CommonObject $object   Object
 * @param  string       $cardpath Path of card page (ex '/anxhr/contract_card.php')
 * @param  string       $docpath  Path of optional document page
 * @param  string       $title    Translation key of card tab
 * @param  string       $type     Context for complete_head_from_modules
 * @return array<array{string,string,string}>
 */
function anxhrBuildHead($object, $cardpath, $docpath, $title, $type)
{
	global $langs, $conf;

	$langs->loadLangs(array('anxhr@anxhr', 'anxhr_hr@anxhr'));
	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath($cardpath, 1).'?id='.((int) $object->id);
	$head[$h][1] = $langs->trans($title);
	$head[$h][2] = 'card';
	$h++;

	if ($docpath && file_exists(dol_buildpath($docpath, 0))) {
		$head[$h][0] = dol_buildpath($docpath, 1).'?id='.((int) $object->id);
		$head[$h][1] = $langs->trans('Documents');
		$head[$h][2] = 'document';
		$h++;
	}

	$head[$h][0] = dol_buildpath($cardpath, 1).'?id='.((int) $object->id).'&tab=info';
	$head[$h][1] = $langs->trans('Info');
	$head[$h][2] = 'info';
	$h++;

	complete_head_from_modules($conf, $langs, $object, $head, $h, $type.'@anxhr');
	complete_head_from_modules($conf, $langs, $object, $head, $h, $type.'@anxhr', 'remove');

	return $head;
}

/**
 * Prepare tabs of contract card
 *
 * @param  HrContract $object Contract
 * @return array<array{string,string,string}>
 */
function anxhrContractPrepareHead($object)
{
	return anxhrBuildHead($object, '/anxhr/contract_card.php', '/anxhr/contract_document.php', 'AnxhrContract', 'anxhr_contract');
}

/**
 * Prepare tabs of deadline card
 *
 * @param  HrDeadline $object Deadline
 * @return array<array{string,string,string}>
 */
function anxhrDeadlinePrepareHead($object)
{
	return anxhrBuildHead($object, '/anxhr/deadline_card.php', '', 'AnxhrDeadline', 'anxhr_deadline');
}

/**
 * Prepare tabs of handover card
 *
 * @param  HrHandover $object Handover
 * @return array<array{string,string,string}>
 */
function anxhrHandoverPrepareHead($object)
{
	return anxhrBuildHead($object, '/anxhr/handover_card.php', '/anxhr/handover_document.php', 'AnxhrHandover', 'anxhr_handover');
}

/**
 * Prepare tabs of checklist card
 *
 * @param  HrChecklist $object Checklist
 * @return array<array{string,string,string}>
 */
function anxhrChecklistPrepareHead($object)
{
	return anxhrBuildHead($object, '/anxhr/checklist_card.php', '', 'AnxhrChecklist', 'anxhr_checklist');
}


/*
 * Form and view helpers
 */

/**
 * Return a select of employees (users with employee flag)
 *
 * @param  Form       $form       Form object
 * @param  int|string $selected   Preselected user id
 * @param  string     $htmlname   Name of field
 * @param  int        $showempty  1=Add an empty value
 * @param  int        $disabled   1=Disabled
 * @return string                 HTML select
 */
function anxhrGetEmployeeSelect($form, $selected, $htmlname, $showempty = 1, $disabled = 0)
{
	return img_picto('', 'user', 'class="pictofixedwidth"').$form->select_dolusers(($selected > 0 ? $selected : -1), $htmlname, $showempty, null, $disabled, '', '', '', 0, 0, '(employee:=:1)', 0, '', 'minwidth300 maxwidth500');
}

/**
 * Return value of a field posted by a form (same parsing as core commonfields templates)
 *
 * @param  array<string,mixed> $val Field definition
 * @param  string              $key Field key
 * @return mixed
 */
function anxhrGetPostedFieldValue($val, $key)
{
	if (in_array($val['type'], array('date', 'datetime'))) {
		return dol_mktime(($val['type'] == 'date' ? 12 : GETPOSTINT($key.'hour')), ($val['type'] == 'date' ? 0 : GETPOSTINT($key.'min')), 0, GETPOSTINT($key.'month'), GETPOSTINT($key.'day'), GETPOSTINT($key.'year'));
	}
	if (preg_match('/^(double|real|price)/', $val['type'])) {
		return price2num(GETPOST($key, 'alphanohtml'));
	}
	if ($val['type'] == 'boolean') {
		return ((GETPOST($key) == 'on' || GETPOST($key) == '1') ? 1 : 0);
	}
	if (preg_match('/^text/', $val['type'])) {
		return GETPOST($key, 'nohtml');
	}
	if (preg_match('/^integer/', $val['type'])) {
		return GETPOST($key, 'int');
	}
	return GETPOST($key, 'alphanohtml');
}

/**
 * Print rows of fields grouped in sections with sub headers (<tr class="liste_titre">) inside a card table.
 *
 * @param  CommonObject             $object   Object (fields definition and values)
 * @param  array<string,string[]>   $sections Array of section title translation key => list of field keys
 * @param  string                   $mode     'create', 'edit' or 'view'
 * @param  Form                     $form     Form object
 * @param  ?CommonObject            $prefill  Object used to prefill values in create mode (ex: new version of a contract)
 * @return void
 */
function anxhrPrintFieldsBySection($object, $sections, $mode, $form, $prefill = null)
{
	global $langs;

	$allowedvisible = array('create' => array(1, 3, 6), 'edit' => array(1, 3, 4), 'view' => array(1, 3, 4, 5));
	foreach ($sections as $title => $keys) {
		$rows = '';
		foreach ($keys as $key) {
			if (empty($object->fields[$key])) {
				continue;
			}
			$val = $object->fields[$key];
			if (!in_array(abs((int) $val['visible']), $allowedvisible[$mode])) {
				continue;
			}
			if ($mode == 'view' && in_array($key, array('ref', 'status'))) {
				continue;
			}
			$rows .= anxhrGetFieldRow($object, $key, $val, $mode, $form, $prefill);
		}
		if ($rows !== '') {
			// Same markup as core extrafield separators, so sections look native in view, create and edit mode
			print '<tr class="trextrafieldseparator anxhr-section"><td colspan="2"><span class="fas fa-square opacitymedium"></span>&nbsp;<strong>'.$langs->trans($title).'</strong></td></tr>'."\n";
			print $rows;
		}
	}
}

/**
 * Return one row of a card table for a field
 *
 * @param  CommonObject        $object  Object
 * @param  string              $key     Field key
 * @param  array<string,mixed> $val     Field definition
 * @param  string              $mode    'create', 'edit' or 'view'
 * @param  Form                $form    Form object
 * @param  ?CommonObject       $prefill Prefill object for create mode
 * @return string
 */
function anxhrGetFieldRow($object, $key, $val, $mode, $form, $prefill = null)
{
	global $langs;

	$label = $langs->trans($val['label']);
	if (!empty($val['help'])) {
		$label = $form->textwithpicto($label, $langs->trans($val['help']));
	}
	$tdclass = ($mode == 'view' ? 'titlefieldmiddle' : 'titlefieldcreate'.((!empty($val['notnull']) && $val['notnull'] > 0) ? ' fieldrequired' : ''));
	if (preg_match('/^(text|html)/', $val['type'])) {
		$tdclass .= ' tdtop';
	}
	$out = '<tr class="field_'.$key.'"><td class="'.$tdclass.'">'.$label.'</td>';
	$out .= '<td class="'.($mode == 'view' ? 'valuefield'.(empty($val['cssview']) ? '' : ' '.$val['cssview']) : 'valuefieldcreate').'">';

	if ($mode == 'view') {
		$out .= $object->showOutputField($val, $key, $object->$key, '', '', '', 0);
	} else {
		if (GETPOSTISSET($key) || GETPOSTISSET($key.'day')) {
			$value = anxhrGetPostedFieldValue($val, $key);
		} elseif ($mode == 'edit') {
			$value = $object->$key;
		} elseif (is_object($prefill)) {
			$value = $prefill->$key;
		} else {
			$value = (isset($val['default']) && $val['default'] !== '(PROV)' ? $val['default'] : '');
		}
		if (!empty($val['noteditable'])) {
			$out .= $object->showOutputField($val, $key, $value, '', '', '', 0);
		} elseif ($key == 'fk_user') {
			$out .= anxhrGetEmployeeSelect($form, $value, $key, 1);
		} else {
			if (!empty($val['picto'])) {
				$out .= img_picto('', $val['picto'], 'class="pictofixedwidth"');
			}
			$out .= $object->showInputField($val, $key, $value, '', '', '', 0);
		}
	}
	$out .= '</td></tr>'."\n";
	return $out;
}

/**
 * Return a progress bar HTML for a percentage
 *
 * @param  int    $pct     Percentage 0..100
 * @param  string $morecss More css
 * @return string
 */
function anxhrHrProgressBar($pct, $morecss = 'width200')
{
	$pct = max(0, min(100, (int) $pct));
	$color = ($pct >= 100 ? 'progress-bar-green' : 'progress-bar-success');
	$out = '<div class="progress sm inline-block valignmiddle '.$morecss.'" title="'.$pct.' %">';
	$out .= '<div class="progress-bar '.$color.'" style="width: '.$pct.'%"></div>';
	$out .= '</div> <span class="valignmiddle">'.$pct.' %</span>';
	return $out;
}


/**
 * Return a small POST form with a button to change the status of a checklist item
 *
 * @param  int    $checklistid Checklist id
 * @param  int    $itemid      Item id
 * @param  string $action      Action code (itemdone, itemskip, itemreopen)
 * @param  string $label       Label of button
 * @param  string $picto       Picto
 * @return string
 */
function anxhrItemActionButton($checklistid, $itemid, $action, $label, $picto)
{
	$out = '<form method="POST" action="'.$_SERVER['PHP_SELF'].'?id='.((int) $checklistid).'" class="inline-block">';
	$out .= '<input type="hidden" name="token" value="'.newToken().'">';
	$out .= '<input type="hidden" name="action" value="'.dol_escape_htmltag($action).'">';
	$out .= '<input type="hidden" name="lineid" value="'.((int) $itemid).'">';
	$out .= '<button type="submit" class="button smallpaddingimp" title="'.dol_escape_htmltag($label).'">'.img_picto('', $picto, 'class="pictofixedwidth"').dol_escape_htmltag($label).'</button>';
	$out .= '</form> ';
	return $out;
}


/*
 * List helpers
 */

/**
 * Return css of a column of list for a field
 *
 * @param  string              $key Field key
 * @param  array<string,mixed> $val Field definition
 * @return string
 */
function anxhrListColumnCss($key, $val)
{
	$css = (empty($val['csslist']) ? (empty($val['css']) ? '' : $val['css']) : $val['csslist']);
	if ($key == 'status' || in_array($val['type'], array('date', 'datetime', 'timestamp'))) {
		$css .= ($css ? ' ' : '').'center';
	} elseif (preg_match('/^(double|integer|real|price)/', $val['type']) && strpos($val['type'], 'integer:') !== 0 && !in_array($key, array('rowid', 'ref', 'status')) && empty($val['arrayofkeyval'])) {
		$css .= ($css ? ' ' : '').'right';
	}
	return preg_replace('/(minwidth\d+|maxwidth\d+|widthcentpercentminusx+)\s*/', '', $css);
}

/**
 * Output the records of a list SQL request as CSV file (export button of list pages) and exit.
 * Only the checked columns of $arrayfields are exported. User fields are resolved with one query.
 *
 * @param  DoliDB                           $db          Database handler
 * @param  string                           $sql         SQL request of list (without LIMIT)
 * @param  CommonObject                     $object      Object of list
 * @param  array<string,array<string,mixed>> $arrayfields Array fields of list (t.key => array(checked,...))
 * @param  string                           $filename    Name of file without extension
 * @return void
 */
function anxhrListExportCsv($db, $sql, $object, $arrayfields, $filename)
{
	global $langs;

	$maxrecords = 10000;
	$resql = $db->query($sql.$db->plimit($maxrecords));
	if (!$resql) {
		dol_print_error($db);
		exit;
	}
	$rows = array();
	$userids = array();
	$columns = array();
	foreach ($object->fields as $key => $val) {
		if (!empty($arrayfields['t.'.$key]['checked']) && !empty($arrayfields['t.'.$key]['enabled']) && $key != 'disability_status') {
			$columns[$key] = $val;
		}
	}
	while ($obj = $db->fetch_object($resql)) {
		$rows[] = $obj;
		foreach ($columns as $key => $val) {
			if (strpos($val['type'], 'integer:User:') === 0 && !empty($obj->$key)) {
				$userids[] = (int) $obj->$key;
			}
		}
	}
	$db->free($resql);
	$users = anxhrLoadUsersCache($db, $userids);

	header('Content-Type: text/csv; charset=UTF-8');
	header('Content-Disposition: attachment; filename="'.dol_sanitizeFileName($filename).'_'.dol_print_date(dol_now(), 'dayxcard').'.csv"');
	$fh = fopen('php://output', 'w');
	fwrite($fh, "\xEF\xBB\xBF");
	$header = array();
	foreach ($columns as $key => $val) {
		$header[] = $langs->transnoentitiesnoconv($val['label']);
	}
	fputcsv($fh, $header, ';', '"', '\\');
	foreach ($rows as $obj) {
		$object->setVarsFromFetchObj($obj);
		$line = array();
		foreach ($columns as $key => $val) {
			$cell = (string) anxhrGetExportValue($object, $key, $val, $users);
			// Protect against CSV formula injection (same rule as the time export)
			if (!is_numeric($cell) && preg_match('/^[=+\-@]/', $cell)) {
				$cell = "'".$cell;
			}
			$line[] = $cell;
		}
		fputcsv($fh, $line, ';', '"', '\\');
	}
	fclose($fh);
	exit;
}

/**
 * Return plain text value of a field for CSV export
 *
 * @param  CommonObject        $object Object loaded with current row
 * @param  string              $key    Field key
 * @param  array<string,mixed> $val    Field definition
 * @param  array<int,User>     $users  Users cache
 * @return string
 */
function anxhrGetExportValue($object, $key, $val, $users)
{
	global $langs;

	$value = ($key == 'rowid' ? $object->id : $object->$key);
	if ($key == 'status') {
		return dol_string_nohtmltag($object->getLibStatut(0));
	}
	if ($value === null || $value === '') {
		return '';
	}
	if (strpos($val['type'], 'integer:User:') === 0) {
		return isset($users[(int) $value]) ? $users[(int) $value]->getFullName($langs) : (string) $value;
	}
	if (in_array($val['type'], array('date'))) {
		return dol_print_date($value, '%Y-%m-%d');
	}
	if (in_array($val['type'], array('datetime', 'timestamp'))) {
		return dol_print_date($value, '%Y-%m-%d %H:%M:%S', 'tzuserrel');
	}
	if (!empty($val['arrayofkeyval']) && isset($val['arrayofkeyval'][$value])) {
		return (string) $val['arrayofkeyval'][$value];
	}
	if ($val['type'] == 'boolean') {
		return ($value ? $langs->transnoentitiesnoconv('Yes') : $langs->transnoentitiesnoconv('No'));
	}
	return (string) $value;
}
