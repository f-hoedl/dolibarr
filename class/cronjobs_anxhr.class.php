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
 * \file    htdocs/custom/anxhr/class/cronjobs_anxhr.class.php
 * \ingroup anxhr
 * \brief   Scheduled jobs of module ANX HR
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
dol_include_once('/anxhr/lib/anxhr.lib.php');


/**
 * Class with methods called by the Dolibarr cron module
 */
class CronjobsAnxhr
{
	/**
	 * @var DoliDB Database handler
	 */
	public $db;

	/**
	 * @var string Error message
	 */
	public $error = '';

	/**
	 * @var string[] Error messages
	 */
	public $errors = array();

	/**
	 * @var string Output shown in cron job list
	 */
	public $output = '';

	/**
	 * Constructor
	 *
	 * @param	DoliDB	$db		Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Send reminders for open HR deadlines. A reminder is sent when the number of days until the due date
	 * is one of the values of remind_days (csv) and no reminder was already sent today.
	 * For each reminder an agenda event is created for the responsible user and the business event
	 * ANXHR_DEADLINE_REMIND is triggered (the Notification module then sends emails).
	 *
	 * @return	int		0 if OK, <0 if KO
	 */
	public function sendDeadlineReminders()
	{
		global $user, $langs;

		$langs->load('anxhr@anxhr');
		$this->output = '';

		if (!anxhrTableExists($this->db, 'anxhr_deadline')) {
			$this->output = 'Table anxhr_deadline not found, nothing done';
			return 0;
		}

		$today = dol_get_first_hour(dol_now(), 'gmt');
		$todaystr = dol_print_date($today, 'dayrfc', 'gmt');
		$maxdate = dol_time_plus_duree($today, 1, 'y');

		$sql = "SELECT d.rowid, d.fk_user, d.label, d.type, d.date_due, d.remind_days, d.fk_user_responsible,";
		$sql .= " u.fk_user as fk_supervisor, u.firstname, u.lastname";
		$sql .= " FROM ".$this->db->prefix()."anxhr_deadline as d";
		$sql .= " LEFT JOIN ".$this->db->prefix()."user as u ON u.rowid = d.fk_user";
		$sql .= " WHERE d.status = 0";
		$sql .= " AND d.entity IN (".getEntity('anxhr_deadline').")";
		$sql .= " AND d.date_due >= '".$this->db->idate($today, 'gmt')."'";
		$sql .= " AND d.date_due <= '".$this->db->idate($maxdate, 'gmt')."'";
		$sql .= " AND (d.last_remind_date IS NULL OR d.last_remind_date < '".$this->db->escape($todaystr)."')";
		$sql .= " ORDER BY d.date_due ASC";
		$sql .= $this->db->plimit(5000);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->output = $this->error;
			dol_syslog(__METHOD__.' '.$this->error, LOG_ERR);
			return -1;
		}

		$todo = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$datedue = $this->db->jdate($obj->date_due, 'gmt');
			$daysleft = (int) round(($datedue - $today) / 86400);
			$reminddays = array_map('intval', array_filter(array_map('trim', explode(',', (string) $obj->remind_days)), 'strlen'));
			if (in_array($daysleft, $reminddays, true)) {
				$obj->datedue_ts = $datedue;
				$obj->daysleft = $daysleft;
				$todo[] = $obj;
			}
		}
		$this->db->free($resql);

		if (empty($todo)) {
			$this->output = $langs->trans('AnxhrCronNoReminder');
			return 0;
		}

		$fallbackuserid = $this->getFallbackAdminId();
		$deadlineclassok = $this->loadDeadlineClass();

		$nbok = 0;
		$nberror = 0;
		$doneids = array();
		foreach ($todo as $obj) {
			$responsible = (int) ($obj->fk_user_responsible > 0 ? $obj->fk_user_responsible : ($obj->fk_supervisor > 0 ? $obj->fk_supervisor : $fallbackuserid));

			$res = $this->createReminderEvent($obj, $responsible, $user);
			if ($res < 0) {
				$nberror++;
				continue;
			}
			if ($deadlineclassok) {
				$this->fireReminderTrigger((int) $obj->rowid, $user);
			}
			$doneids[] = (int) $obj->rowid;
			$nbok++;
		}

		if (!empty($doneids)) {
			$sql = "UPDATE ".$this->db->prefix()."anxhr_deadline";
			$sql .= " SET last_remind_date = '".$this->db->escape($todaystr)."'";
			$sql .= " WHERE rowid IN (".$this->db->sanitize(implode(',', $doneids)).")";
			if (!$this->db->query($sql)) {
				$nberror++;
				dol_syslog(__METHOD__.' '.$this->db->lasterror(), LOG_ERR);
			}
		}

		$this->output = $langs->trans('AnxhrCronRemindersSent', $nbok).($nberror ? ' - '.$langs->trans('Errors').': '.$nberror : '');
		dol_syslog(__METHOD__.' '.$this->output, LOG_INFO);

		return ($nberror ? -1 : 0);
	}

	/**
	 * Recompute day values of the time tracking for yesterday (all relevant users).
	 * Thin wrapper, logic is in the time classes: static TimeDay::recomputeAllUsersForDay(DoliDB $db, User $actor, string $day).
	 *
	 * @return	int		0 if OK, <0 if KO
	 */
	public function computeTimeDays()
	{
		global $user;

		$this->output = '';

		$classname = $this->loadTimeClass('/anxhr/class/timeday.class.php', array('TimeDay'), 'recomputeAllUsersForDay');
		if (!$classname) {
			$this->output = 'Time module classes not present';
			return 0;
		}

		$yesterday = dol_print_date(dol_time_plus_duree(dol_get_first_hour(dol_now()), -1, 'd'), '%Y-%m-%d');

		$result = call_user_func(array($classname, 'recomputeAllUsersForDay'), $this->db, $user, $yesterday);
		if (is_numeric($result) && $result < 0) {
			$this->error = $classname.'::recomputeAllUsersForDay failed';
			$this->output = $this->error;
			return -1;
		}
		$this->output = 'Time days recomputed for '.$yesterday.(is_numeric($result) ? ' ('.$result.' users)' : '');

		return 0;
	}

	/**
	 * Create monthly time periods of the previous month, on the day of month set in ANXHR_PERIOD_AUTOCREATE_DAY.
	 * Contract: static TimePeriod::createPeriodsForMonth(DoliDB $db, int $year, int $month) returning int.
	 *
	 * @return	int		0 if OK, <0 if KO
	 */
	public function createMonthlyPeriods()
	{
		$this->output = '';

		$day = getDolGlobalInt('ANXHR_PERIOD_AUTOCREATE_DAY', 1);
		$now = dol_getdate(dol_now());
		if ((int) $now['mday'] != $day) {
			$this->output = 'Not the configured day ('.$day.'), nothing done';
			return 0;
		}

		$classname = $this->loadTimeClass('/anxhr/class/timeperiod.class.php', array('TimePeriod', 'AnxhrTimePeriod'), 'createPeriodsForMonth');
		if (!$classname) {
			$this->output = 'Time module classes not present';
			return 0;
		}

		$prev = dol_get_prev_month((int) $now['mon'], (int) $now['year']);
		$result = call_user_func(array($classname, 'createPeriodsForMonth'), $this->db, (int) $prev['year'], (int) $prev['month']);
		if (is_numeric($result) && $result < 0) {
			$this->error = $classname.'::createPeriodsForMonth failed';
			$this->output = $this->error;
			return -1;
		}
		$this->output = 'Periods created for '.sprintf('%04d-%02d', $prev['year'], $prev['month']).(is_numeric($result) ? ' ('.$result.')' : '');

		return 0;
	}

	/**
	 * Delete time records of former employees once the retention period is over
	 * (end of employment older than ANXHR_RETENTION_YEARS years).
	 *
	 * @return	int		0 if OK, <0 if KO
	 */
	public function retentionCleanup()
	{
		$this->output = '';

		$years = getDolGlobalInt('ANXHR_RETENTION_YEARS', 7);
		if ($years < 1) {
			$this->output = 'Retention disabled';
			return 0;
		}
		$cutoff = dol_time_plus_duree(dol_get_first_hour(dol_now()), -$years, 'y');

		$subsql = "SELECT u.rowid FROM ".$this->db->prefix()."user as u";
		$subsql .= " WHERE u.dateemploymentend IS NOT NULL AND u.dateemploymentend < '".$this->db->idate($cutoff)."'";

		$targets = array(
			'anxhr_time_entry' => 'entry_datetime',
			'anxhr_time_day' => 'day',
		);

		$this->db->begin();
		$messages = array();
		foreach ($targets as $table => $datefield) {
			if (!anxhrTableExists($this->db, $table)) {
				continue;
			}
			$sql = "DELETE FROM ".$this->db->prefix().$table;
			$sql .= " WHERE entity IN (".getEntity($table).")";
			$sql .= " AND ".$datefield." < '".$this->db->idate($cutoff)."'";
			$sql .= " AND fk_user IN (".$subsql.")";
			$resql = $this->db->query($sql);
			if (!$resql) {
				$this->error = $this->db->lasterror();
				$this->output = $this->error;
				$this->db->rollback();
				dol_syslog(__METHOD__.' '.$this->error, LOG_ERR);
				return -1;
			}
			$messages[] = $table.': '.((int) $this->db->affected_rows($resql));
		}
		$this->db->commit();

		$this->output = 'Cutoff '.dol_print_date($cutoff, 'day').' - deleted '.(empty($messages) ? '0' : implode(', ', $messages));
		dol_syslog(__METHOD__.' '.$this->output, LOG_INFO);

		return 0;
	}

	/**
	 * Create the agenda event of a reminder.
	 *
	 * @param	stdClass	$obj			Deadline row
	 * @param	int			$responsible	Id of user owning the event
	 * @param	User		$user			User running the job
	 * @return	int							Id of event or <0 if KO
	 */
	private function createReminderEvent($obj, $responsible, $user)
	{
		global $langs;

		require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';

		$employee = dolGetFirstLastname((string) $obj->firstname, (string) $obj->lastname);
		$label = $langs->transnoentitiesnoconv('AnxhrDeadlineReminderLabel', (string) ($obj->label ? $obj->label : $obj->type), $employee);

		$event = new ActionComm($this->db);
		$event->type_code = 'AC_OTH_AUTO';
		$event->code = 'AC_ANXHR_DEADLINE_REMIND';
		$event->label = dol_trunc($label, 120, 'right', 'UTF-8', 1);
		$event->note_private = $langs->transnoentitiesnoconv('AnxhrDeadlineReminderNote', dol_print_date($obj->datedue_ts, 'day'), $obj->daysleft);
		$event->datep = $obj->datedue_ts;
		$event->datef = $obj->datedue_ts;
		$event->fulldayevent = 1;
		$event->percentage = 0;
		$event->userownerid = $responsible;
		$event->userassigned = array($responsible => array('id' => $responsible, 'transparency' => 0));
		$event->elementtype = 'anxhr_deadline@anxhr';
		$event->fk_element = (int) $obj->rowid;

		$result = $event->create($user, 1);
		if ($result < 0) {
			$this->errors[] = $event->error;
			dol_syslog(__METHOD__.' deadline '.$obj->rowid.' '.$event->error, LOG_ERR);
		}

		return $result;
	}

	/**
	 * Fire trigger ANXHR_DEADLINE_REMIND on the deadline object so the Notification module
	 * (and other modules) can react. Errors are logged only.
	 *
	 * @param	int		$id		Deadline id
	 * @param	User	$user	User running the job
	 * @return	void
	 */
	private function fireReminderTrigger($id, $user)
	{
		$deadline = new HrDeadline($this->db);
		if ($deadline->fetch($id) <= 0) {
			return;
		}
		$res = $deadline->call_trigger('ANXHR_DEADLINE_REMIND', $user);
		if ($res < 0) {
			dol_syslog(__METHOD__.' deadline '.$id.' trigger error '.$deadline->error, LOG_WARNING);
		}
	}

	/**
	 * Load class HrDeadline if available
	 *
	 * @return	bool
	 */
	private function loadDeadlineClass()
	{
		$file = dol_buildpath('/anxhr/class/hrdeadline.class.php', 0);
		if (file_exists($file)) {
			include_once $file;
		}

		return class_exists('HrDeadline') && method_exists('HrDeadline', 'call_trigger');
	}

	/**
	 * Load an optional time class and return the name of the class that provides the static method.
	 *
	 * @param	string		$relpath		Relative path of class file
	 * @param	string[]	$classnames		Candidate class names
	 * @param	string		$method			Static method required
	 * @return	string						Class name or '' if not available
	 */
	private function loadTimeClass($relpath, $classnames, $method)
	{
		$file = dol_buildpath($relpath, 0);
		if (!file_exists($file)) {
			return '';
		}
		include_once $file;
		foreach ($classnames as $classname) {
			if (class_exists($classname) && method_exists($classname, $method)) {
				return $classname;
			}
		}

		return '';
	}

	/**
	 * Return id of first active admin user (fallback recipient of reminders)
	 *
	 * @return	int
	 */
	private function getFallbackAdminId()
	{
		$sql = "SELECT rowid FROM ".$this->db->prefix()."user";
		$sql .= " WHERE admin = 1 AND statut = 1";
		$sql .= " ORDER BY entity ASC, rowid ASC";
		$sql .= $this->db->plimit(1);
		$resql = $this->db->query($sql);
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			$this->db->free($resql);
			if ($obj) {
				return (int) $obj->rowid;
			}
		}

		return 1;
	}
}
