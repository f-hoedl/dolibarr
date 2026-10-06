<?php
/* Copyright (C) 2004-2018	Laurent Destailleur			<eldy@users.sourceforge.net>
 * Copyright (C) 2018-2019	Nicolas ZABOURI				<info@inovea-conseil.com>
 * Copyright (C) 2019-2024	Frédéric France				<frederic.france@free.fr>
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
 * 	\defgroup   anxhr     Module Anxhr
 *  \brief      ANX HR Austria module descriptor.
 *
 *  \file       htdocs/custom/anxhr/core/modules/modAnxhr.class.php
 *  \ingroup    anxhr
 *  \brief      Description and activation file for module Anxhr
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';


/**
 *  Description and activation class for module Anxhr
 */
class modAnxhr extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $conf, $langs;

		$this->db = $db;

		$this->numero = 500100;
		$this->rights_class = 'anxhr';
		$this->family = "hr";
		$this->module_position = '50';
		$this->name = preg_replace('/^mod/i', '', get_class($this));

		// Module description, used if translation string 'ModuleAnxhrDesc' not found
		$this->description = "ModuleAnxhrDesc";
		$this->descriptionlong = "ModuleAnxhrDescLong";

		$this->editor_name = 'ANX';
		$this->editor_url = '';
		$this->editor_squarred_logo = '';

		$this->version = '1.0.0';
		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

		$this->picto = 'fa-id-card';

		$this->module_parts = array(
			'triggers' => 1,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 0,
			'printing' => 0,
			'theme' => 0,
			'css' => array(
				'/anxhr/css/anxhr.css.php',
			),
			'js' => array(
				'/anxhr/js/anxhr.js',
			),
			'hooks' => array(
				'usercard',
				'userlist',
				'holidaycard',
				'expensereportcard',
				'login',
				'notification',
				'globalcard',
				'main',
				'toprightmenu',
			),
			'moduleforexternal' => 0,
			'websitetemplates' => 0,
			'captcha' => 0
		);

		// Data directories to create when module is enabled (relative to DOL_DATA_ROOT)
		$this->dirs = array("/anxhr/temp", "/anxhr/vault");

		$this->config_page_url = array("setup.php@anxhr");

		$this->hidden = getDolGlobalInt('MODULE_ANXHR_DISABLED');
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();

		$this->langfiles = array("anxhr@anxhr");

		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(20, -3);
		$this->need_javascript_ajax = 0;

		$this->warnings_activation = array();
		$this->warnings_activation_ext = array();

		// Constants (key, type, value, desc, visible, 'current' or 'allentities', deleteonunactive)
		$this->const = array(
			1 => array('ANXHR_DEFAULT_KV', 'chaine', 'SWOE', 'Default collective agreement code', 0, 'current', 0),
			2 => array('ANXHR_TIMEZONE', 'chaine', 'Europe/Vienna', 'Company time zone used for working days, night work and Sundays', 0, 'current', 0),
			3 => array('ANXHR_CLOCK_ALLOW_SELF_CORRECTION_SAME_DAY', 'chaine', '0', 'Allow employees to correct own entries on the same day', 0, 'current', 0),
			4 => array('ANXHR_PERIOD_AUTOCREATE_DAY', 'chaine', '1', 'Day of month on which monthly periods of previous month are created', 0, 'current', 0),
			5 => array('ANXHR_RETENTION_YEARS', 'chaine', '7', 'Retention in years after end of employment', 0, 'current', 0),
			6 => array('ANXHR_VAULT_MAX_SIZE_MB', 'chaine', '20', 'Max size in MB of a document of the HR vault', 0, 'current', 0),
			7 => array('ANXHR_VAULT_ALLOWED_EXT', 'chaine', 'pdf,png,jpg,jpeg,docx,xlsx,txt,csv', 'Allowed file extensions of the HR vault', 0, 'current', 0),
			8 => array('ANXHR_VAULT_NOTIFY_CATEGORIES', 'chaine', 'payslip', 'Vault categories that fire ANXHR_VAULTDOC_CREATE (notification) when a document visible to the employee is stored', 0, 'current', 0),
		);

		if (!isModEnabled("anxhr")) {
			$conf->anxhr = new stdClass();
			$conf->anxhr->enabled = 0;
		}

		// Tabs. Note: the tab definition is split on ':' so the condition must not contain ':'.
		// $objectoffield is the object of the card (the User shown), see complete_head_from_modules().
		$this->tabs = array();
		$this->tabs[] = array('data' => 'user:+anxhr:AnxhrHrTab:anxhr@anxhr:$user->hasRight("anxhr", "employee", "read_all") || ($user->hasRight("anxhr", "employee", "read_own") && $objectoffield->id == $user->id):/anxhr/user_tab.php?id=__ID__');

		// Dictionaries
		$this->dictionaries = array(
			'langs' => 'anxhr@anxhr',
			'tabname' => array("c_anxhr_deadline_type", "c_anxhr_handover_cat"),
			'tablib' => array("AnxhrDictDeadlineTypes", "AnxhrDictHandoverCategories"),
			'tabsql' => array(
				'SELECT f.rowid as rowid, f.code, f.label, f.remind_days, f.active FROM '.$this->db->prefix().'c_anxhr_deadline_type as f WHERE f.entity IN ('.getEntity('c_anxhr_deadline_type').')',
				'SELECT f.rowid as rowid, f.code, f.label, f.active FROM '.$this->db->prefix().'c_anxhr_handover_cat as f WHERE f.entity IN ('.getEntity('c_anxhr_handover_cat').')',
			),
			'tabsqlsort' => array("label ASC", "label ASC"),
			'tabfield' => array("code,label,remind_days", "code,label"),
			'tabfieldvalue' => array("code,label,remind_days", "code,label"),
			'tabfieldinsert' => array("code,label,remind_days,entity", "code,label,entity"),
			'tabrowid' => array("rowid", "rowid"),
			'tabcond' => array(isModEnabled('anxhr'), isModEnabled('anxhr')),
			'tabhelp' => array(
				array('code' => $langs->trans('AnxhrDictCodeHelp'), 'remind_days' => $langs->trans('AnxhrDictRemindDaysHelp')),
				array('code' => $langs->trans('AnxhrDictCodeHelp')),
			),
		);

		$this->boxes = array();

		// Cronjobs
		$arraydate = dol_getdate(dol_now());
		$datestartmorning = dol_mktime(6, 10, 0, $arraydate['mon'], $arraydate['mday'], $arraydate['year']);
		$datestartnight = dol_mktime(1, 20, 0, $arraydate['mon'], $arraydate['mday'], $arraydate['year']);
		$this->cronjobs = array(
			0 => array(
				'label' => 'AnxhrDeadlineReminders',
				'jobtype' => 'method',
				'class' => '/anxhr/class/cronjobs_anxhr.class.php',
				'objectname' => 'CronjobsAnxhr',
				'method' => 'sendDeadlineReminders',
				'parameters' => '',
				'comment' => 'AnxhrCronDeadlineRemindersComment',
				'frequency' => 1,
				'unitfrequency' => 86400,
				'status' => 1,
				'test' => 'isModEnabled("anxhr")',
				'priority' => 50,
				'datestart' => $datestartmorning,
			),
			1 => array(
				'label' => 'AnxhrComputeTimeDays',
				'jobtype' => 'method',
				'class' => '/anxhr/class/cronjobs_anxhr.class.php',
				'objectname' => 'CronjobsAnxhr',
				'method' => 'computeTimeDays',
				'parameters' => '',
				'comment' => 'AnxhrCronComputeTimeDaysComment',
				'frequency' => 1,
				'unitfrequency' => 86400,
				'status' => 1,
				'test' => 'isModEnabled("anxhr")',
				'priority' => 50,
				'datestart' => $datestartnight,
			),
			2 => array(
				'label' => 'AnxhrCreateMonthlyPeriods',
				'jobtype' => 'method',
				'class' => '/anxhr/class/cronjobs_anxhr.class.php',
				'objectname' => 'CronjobsAnxhr',
				'method' => 'createMonthlyPeriods',
				'parameters' => '',
				'comment' => 'AnxhrCronCreateMonthlyPeriodsComment',
				'frequency' => 1,
				'unitfrequency' => 86400,
				'status' => 0,
				'test' => 'isModEnabled("anxhr")',
				'priority' => 50,
				'datestart' => $datestartnight,
			),
			3 => array(
				'label' => 'AnxhrRetentionCleanup',
				'jobtype' => 'method',
				'class' => '/anxhr/class/cronjobs_anxhr.class.php',
				'objectname' => 'CronjobsAnxhr',
				'method' => 'retentionCleanup',
				'parameters' => '',
				'comment' => 'AnxhrCronRetentionCleanupComment',
				'frequency' => 1,
				'unitfrequency' => 86400,
				'status' => 0,
				'test' => 'isModEnabled("anxhr")',
				'priority' => 90,
				'datestart' => $datestartnight,
			),
		);

		// Permissions. Ids 50010001 upwards (reserved in CONVENTIONS.md). Labels are translated with key 'Permission<id>'.
		$this->rights = array();
		$rightsdef = array(
			array('employee', 'read_own', 'Read own HR file'),
			array('employee', 'read_all', 'Read HR file of all employees'),
			array('employee', 'write', 'Create/update HR master data of employees'),
			array('contract', 'read', 'Read employment contracts'),
			array('contract', 'write', 'Create/update employment contracts'),
			array('contract', 'delete', 'Delete employment contracts'),
			array('deadline', 'read', 'Read HR deadlines'),
			array('deadline', 'write', 'Create/update HR deadlines'),
			array('deadline', 'delete', 'Delete HR deadlines'),
			array('handover', 'read', 'Read equipment and access handovers'),
			array('handover', 'write', 'Create/update equipment and access handovers'),
			array('handover', 'delete', 'Delete equipment and access handovers'),
			array('checklist', 'read', 'Read onboarding/offboarding checklists'),
			array('checklist', 'write', 'Create/update onboarding/offboarding checklists'),
			array('checklist', 'delete', 'Delete onboarding/offboarding checklists'),
			array('time', 'own', 'Clock in/out and see own time records'),
			array('time', 'correct_own', 'Request corrections of own time records'),
			array('time', 'approve', 'Approve time records of subordinates'),
			array('time', 'admin', 'Administrate time records of all employees (HR)'),
			array('time', 'export', 'Export time records for payroll'),
			array('travel', 'admin', 'Manage travel allowance rates'),
			array('travel', 'pay', 'Release expense reports for payment (second approval)'),
			array('vault', 'own', 'Read own documents in the HR vault'),
			array('vault', 'upload', 'Upload documents into the HR vault'),
			array('vault', 'admin', 'Administrate the HR vault (all documents)'),
			array('audit', 'read', 'Read HR audit log'),
			array('setup', 'admin', 'Setup of ANX HR module'),
		);
		$r = 0;
		foreach ($rightsdef as $rightdef) {
			$this->rights[$r][0] = 50010001 + $r;
			$this->rights[$r][1] = $rightdef[2];
			$this->rights[$r][4] = $rightdef[0];
			$this->rights[$r][5] = $rightdef[1];
			$r++;
		}

		// Menus
		$this->menu = array();
		$r = 0;
		$permcockpit = '$user->hasRight("anxhr", "employee", "read_own") || $user->hasRight("anxhr", "employee", "read_all") || $user->hasRight("anxhr", "time", "own") || $user->hasRight("anxhr", "time", "approve")';
		$permtime = '$user->hasRight("anxhr", "time", "own") || $user->hasRight("anxhr", "time", "approve") || $user->hasRight("anxhr", "time", "admin")';

		$this->menu[$r++] = array(
			'fk_menu' => '',
			'type' => 'top',
			'titre' => 'AnxhrMenuHR',
			'prefix' => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'anxhr',
			'leftmenu' => '',
			'url' => '/anxhr/anxhrindex.php',
			'langs' => 'anxhr@anxhr',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("anxhr")',
			'perms' => $permcockpit,
			'target' => '',
			'user' => 0,
		);

		// Block 1: personnel administration
		$this->addLeftMenu($r, 'fk_mainmenu=anxhr', 'AnxhrMenuCockpit', 'anxhr_hr', '/anxhr/anxhrindex.php', $permcockpit, 'isModEnabled("anxhr")', 'fa-tachometer-alt');
		$this->addLeftMenu($r, 'fk_mainmenu=anxhr,fk_leftmenu=anxhr_hr', 'AnxhrMenuEmployees', 'anxhr_employees', '/user/list.php?mainmenu=anxhr&leftmenu=anxhr_hr', '$user->hasRight("anxhr", "employee", "read_all")');
		$this->addLeftMenu($r, 'fk_mainmenu=anxhr,fk_leftmenu=anxhr_hr', 'AnxhrMenuContracts', 'anxhr_contracts', '/anxhr/contract_list.php', '$user->hasRight("anxhr", "contract", "read")');
		$this->addLeftMenu($r, 'fk_mainmenu=anxhr,fk_leftmenu=anxhr_hr', 'AnxhrMenuDeadlines', 'anxhr_deadlines', '/anxhr/deadline_list.php', '$user->hasRight("anxhr", "deadline", "read")');
		$this->addLeftMenu($r, 'fk_mainmenu=anxhr,fk_leftmenu=anxhr_hr', 'AnxhrMenuChecklists', 'anxhr_checklists', '/anxhr/checklist_list.php', '$user->hasRight("anxhr", "checklist", "read")');
		$this->addLeftMenu($r, 'fk_mainmenu=anxhr,fk_leftmenu=anxhr_hr', 'AnxhrMenuHandover', 'anxhr_handovers', '/anxhr/handover_list.php', '$user->hasRight("anxhr", "handover", "read")');
		// Document vault (HR group) and its access log (audit position, right below)
		$this->addLeftMenu($r, 'fk_mainmenu=anxhr,fk_leftmenu=anxhr_hr', 'AnxhrMenuVault', 'anxhr_vault', '/anxhr/vault.php', '$user->hasRight("anxhr", "vault", "own") || $user->hasRight("anxhr", "vault", "upload") || $user->hasRight("anxhr", "vault", "admin")');
		$this->menu[$r - 1]['langs'] = 'anxhr_vault@anxhr';
		$this->addLeftMenu($r, 'fk_mainmenu=anxhr,fk_leftmenu=anxhr_hr', 'AnxhrMenuVaultLog', 'anxhr_vault_log', '/anxhr/vault_log.php', '$user->hasRight("anxhr", "vault", "admin") || $user->hasRight("anxhr", "audit", "read")');
		$this->menu[$r - 1]['langs'] = 'anxhr_vault@anxhr';

		// Block 2: time tracking (separate left menu group acts as separator)
		$this->addLeftMenu($r, 'fk_mainmenu=anxhr', 'AnxhrMenuMyTime', 'anxhr_time', '/anxhr/time_clock.php', $permtime, 'isModEnabled("anxhr")', 'fa-user-clock');
		$this->addLeftMenu($r, 'fk_mainmenu=anxhr,fk_leftmenu=anxhr_time', 'AnxhrMenuTimeDays', 'anxhr_time_days', '/anxhr/time_day.php', $permtime);
		$this->addLeftMenu($r, 'fk_mainmenu=anxhr,fk_leftmenu=anxhr_time', 'AnxhrMenuCorrections', 'anxhr_time_corrections', '/anxhr/time_corrections.php', '$user->hasRight("anxhr", "time", "correct_own") || $user->hasRight("anxhr", "time", "approve") || $user->hasRight("anxhr", "time", "admin")');
		$this->addLeftMenu($r, 'fk_mainmenu=anxhr,fk_leftmenu=anxhr_time', 'AnxhrMenuMonthlyPeriods', 'anxhr_time_periods', '/anxhr/time_periods.php', $permtime);

		// Block 3: absences and expenses (core modules)
		$this->addLeftMenu($r, 'fk_mainmenu=anxhr', 'AnxhrMenuLeaveRequests', 'anxhr_holiday', '/holiday/list.php?mainmenu=anxhr&leftmenu=anxhr_holiday', '$user->hasRight("holiday", "read")', 'isModEnabled("anxhr") && isModEnabled("holiday")', 'holiday');
		$this->addLeftMenu($r, 'fk_mainmenu=anxhr', 'AnxhrMenuExpenseReports', 'anxhr_expensereport', '/expensereport/list.php?mainmenu=anxhr&leftmenu=anxhr_expensereport', '$user->hasRight("expensereport", "lire")', 'isModEnabled("anxhr") && isModEnabled("expensereport")', 'trip');
	}

	/**
	 * Add a left menu entry into $this->menu
	 *
	 * @param	int		$r			Menu counter (incremented)
	 * @param	string	$fkmenu		Parent definition (fk_mainmenu=...,fk_leftmenu=...)
	 * @param	string	$titre		Translation key of label
	 * @param	string	$leftmenu	Left menu code
	 * @param	string	$url		Url relative to module root resolution (dol_buildpath)
	 * @param	string	$perms		Permission condition
	 * @param	string	$enabled	Enable condition
	 * @param	string	$picto		Picto for level 0 entries
	 * @return	void
	 */
	private function addLeftMenu(&$r, $fkmenu, $titre, $leftmenu, $url, $perms, $enabled = 'isModEnabled("anxhr")', $picto = '')
	{
		$menu = array(
			'fk_menu' => $fkmenu,
			'type' => 'left',
			'titre' => $titre,
			'mainmenu' => 'anxhr',
			'leftmenu' => $leftmenu,
			'url' => $url,
			'langs' => 'anxhr@anxhr',
			'position' => 1000 + $r,
			'enabled' => $enabled,
			'perms' => $perms,
			'target' => '',
			'user' => 0,
		);
		if ($picto) {
			$menu['prefix'] = img_picto('', $picto, 'class="pictofixedwidth valignmiddle paddingright"');
		}
		$this->menu[$r++] = $menu;
	}

	/**
	 *  Function called when module is enabled.
	 *  The init function add constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
	 *  It also creates data directories
	 *
	 *  @param      string  $options    Options when enabling module ('', 'noboxes')
	 *  @return     int<-1,1>          	1 if OK, <=0 if KO
	 */
	public function init($options = '')
	{
		global $conf;

		// Create tables of module. The path is relative to each entry of $conf->file->dol_document_root,
		// so '/anxhr/sql/' is found in the 'alt' root that maps htdocs/custom.
		$result = $this->_load_tables('/anxhr/sql/');
		if ($result < 0) {
			return -1;
		}

		// Verified for this Dolibarr version (develop 24.0): DolibarrModules::_load_tables() only runs the llx_*.sql
		// files, the execution of *.key.sql, functions*, data*.sql and update*.sql is commented out in core.
		// So keys and data are loaded here. Every data table has a unique key, so on re-activation run_sql()
		// meets only accepted errors (DB_ERROR_KEY_NAME_ALREADY_EXISTS, DB_ERROR_RECORD_ALREADY_EXISTS) which it
		// ignores without error log: this is idempotent. Remove this method once core runs these files again.
		$this->loadKeysAndData('/anxhr/sql/');

		// Document vault directory: deny-all .htaccess and empty index.html (defense in depth, documents are
		// outside the web root anyway and are only served by vault_download.php)
		dol_include_once('/anxhr/lib/anxhr_vault.lib.php');
		if (function_exists('anxhrVaultEnsureRootDir')) {
			anxhrVaultEnsureRootDir(anxhrVaultRootDir((int) $conf->entity));
		}

		// Permissions
		$this->remove($options);

		$sql = array();

		return $this->_init($sql, $options);
	}

	/**
	 * Run llx_*.key.sql files then data*.sql files of the module sql directory.
	 *
	 * @param	string	$reldir		Relative directory (dol_buildpath syntax)
	 * @return	int					Number of files with errors
	 */
	private function loadKeysAndData($reldir)
	{
		include_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

		$dir = dol_buildpath($reldir, 0);
		if (!is_dir($dir)) {
			return 0;
		}
		$files = scandir($dir);
		if (!is_array($files)) {
			return 0;
		}
		sort($files);

		$keyfiles = array();
		$datafiles = array();
		foreach ($files as $file) {
			if (preg_match('/^llx_.*\.key\.sql$/i', $file)) {
				$keyfiles[] = $file;
			} elseif (preg_match('/^data.*\.sql$/i', $file)) {
				$datafiles[] = $file;
			}
		}

		$nberror = 0;
		foreach (array_merge($keyfiles, $datafiles) as $file) {
			$res = run_sql($dir.$file, !getDolGlobalString('MAIN_DISPLAY_SQL_INSTALL_LOG') ? 1 : 0, 0, 1);
			if ($res <= 0) {
				$nberror++;
				dol_syslog(get_class($this).'::loadKeysAndData error while running '.$file, LOG_WARNING);
			}
		}

		return $nberror;
	}

	/**
	 *	Function called when module is disabled.
	 *	Remove from database constants, boxes and permissions from Dolibarr database.
	 *	Data directories are not deleted
	 *
	 *	@param	string		$options	Options when enabling module ('', 'noboxes')
	 *	@return	int<-1,1>				1 if OK, <=0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
