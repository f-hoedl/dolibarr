<?php
/* Copyright (C) 2026  ANX HR contributors
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
 * \file        htdocs/custom/anxhr/class/timeperiod.class.php
 * \ingroup     anxhr
 * \brief       Monthly time period (closing): employee confirms, supervisor approves, HR exports.
 *              Also contains AnxhrTimeIntegration (leave approval / cancellation integration).
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once __DIR__.'/timeentry.class.php';
require_once __DIR__.'/timeday.class.php';

/**
 * Class TimePeriod
 */
class TimePeriod extends CommonObject
{
	/** @var string */
	public $module = 'anxhr';
	/** @var string */
	public $element = 'anxhr_time_period';
	/** @var string */
	public $TRIGGER_PREFIX = 'ANXHR_TIMEPERIOD';
	/** @var string */
	public $table_element = 'anxhr_time_period';
	/** @var string */
	public $picto = 'fa-calendar-check';
	/** @var int */
	public $isextrafieldmanaged = 0;
	/** @var int|string */
	public $ismultientitymanaged = 1;

	const STATUS_OPEN = 0;
	const STATUS_CONFIRMED = 1;
	const STATUS_APPROVED = 2;
	const STATUS_EXPORTED = 3;
	const STATUS_REOPENED = 9;

	/**
	 * @var array<string,array<string,mixed>>
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'default' => '1', 'enabled' => 1, 'visible' => 0, 'notnull' => 1, 'position' => 5, 'index' => 1),
		'fk_user' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Employee', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'foreignkey' => 'user.rowid'),
		'year' => array('type' => 'integer', 'label' => 'Year', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1),
		'month' => array('type' => 'integer', 'label' => 'Month', 'enabled' => 1, 'position' => 21, 'notnull' => 1, 'visible' => 1),
		'target_min' => array('type' => 'integer', 'label' => 'AnxhrTarget', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1, 'default' => '0'),
		'worked_min' => array('type' => 'integer', 'label' => 'AnxhrWorked', 'enabled' => 1, 'position' => 31, 'notnull' => 1, 'visible' => 1, 'default' => '0'),
		'overtime50_min' => array('type' => 'integer', 'label' => 'AnxhrOvertime50', 'enabled' => 1, 'position' => 32, 'notnull' => 1, 'visible' => 1, 'default' => '0'),
		'overtime100_min' => array('type' => 'integer', 'label' => 'AnxhrOvertime100', 'enabled' => 1, 'position' => 33, 'notnull' => 1, 'visible' => 1, 'default' => '0'),
		'extra_parttime_min' => array('type' => 'integer', 'label' => 'AnxhrExtraParttime', 'enabled' => 1, 'position' => 34, 'notnull' => 1, 'visible' => 1, 'default' => '0'),
		'night_min' => array('type' => 'integer', 'label' => 'AnxhrNight', 'enabled' => 1, 'position' => 35, 'notnull' => 1, 'visible' => 1, 'default' => '0'),
		'vacation_days' => array('type' => 'double(6,2)', 'label' => 'AnxhrVacationDays', 'enabled' => 1, 'position' => 36, 'notnull' => 0, 'visible' => 1, 'default' => '0'),
		'sick_days' => array('type' => 'double(6,2)', 'label' => 'AnxhrSickDays', 'enabled' => 1, 'position' => 37, 'notnull' => 0, 'visible' => 1, 'default' => '0'),
		'balance_start_min' => array('type' => 'integer', 'label' => 'AnxhrBalanceStart', 'enabled' => 1, 'position' => 40, 'notnull' => 1, 'visible' => 1, 'default' => '0'),
		'balance_end_min' => array('type' => 'integer', 'label' => 'AnxhrBalanceEnd', 'enabled' => 1, 'position' => 41, 'notnull' => 1, 'visible' => 1, 'default' => '0'),
		'overtime_paid_min' => array('type' => 'integer', 'label' => 'AnxhrOvertimePaid', 'enabled' => 1, 'position' => 42, 'notnull' => 1, 'visible' => 1, 'default' => '0'),
		'za_taken_min' => array('type' => 'integer', 'label' => 'AnxhrZaTaken', 'enabled' => 1, 'position' => 43, 'notnull' => 1, 'visible' => 1, 'default' => '0'),
		'fk_user_confirm' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'AnxhrConfirmedBy', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => -1),
		'date_confirm' => array('type' => 'datetime', 'label' => 'AnxhrDateConfirm', 'enabled' => 1, 'position' => 51, 'notnull' => 0, 'visible' => -1),
		'fk_user_approve' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'AnxhrApprover', 'enabled' => 1, 'position' => 52, 'notnull' => 0, 'visible' => 1),
		'date_approve' => array('type' => 'datetime', 'label' => 'AnxhrDateApprove', 'enabled' => 1, 'position' => 53, 'notnull' => 0, 'visible' => 1),
		'note' => array('type' => 'text', 'label' => 'Note', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => 0),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 1, 'visible' => -2, 'foreignkey' => 'user.rowid'),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => -1, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => -1, 'visible' => -2),
		'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => 1, 'position' => 2000, 'notnull' => 1, 'visible' => 1, 'default' => '0', 'index' => 1, 'arrayofkeyval' => array(0 => 'AnxhrPeriodOpen', 1 => 'AnxhrPeriodConfirmed', 2 => 'AnxhrPeriodApproved', 3 => 'AnxhrPeriodExported', 9 => 'AnxhrPeriodReopened')),
	);

	/** @var int */
	public $fk_user;
	/** @var int */
	public $year;
	/** @var int */
	public $month;
	/** @var int */
	public $target_min;
	/** @var int */
	public $worked_min;
	/** @var int */
	public $overtime50_min;
	/** @var int */
	public $overtime100_min;
	/** @var int */
	public $extra_parttime_min;
	/** @var int */
	public $night_min;
	/** @var float */
	public $vacation_days;
	/** @var float */
	public $sick_days;
	/** @var int */
	public $balance_start_min;
	/** @var int */
	public $balance_end_min;
	/** @var int */
	public $overtime_paid_min;
	/** @var int */
	public $za_taken_min;
	/** @var int|null */
	public $fk_user_confirm;
	/** @var int|string|null */
	public $date_confirm;
	/** @var int|null */
	public $fk_user_approve;
	/** @var int|string|null */
	public $date_approve;
	/** @var string|null */
	public $note;
	/** @var int */
	public $status;
	/** @var int */
	public $fk_user_creat;
	/** @var int|null */
	public $fk_user_modif;
	/** @var string|null */
	public $import_key;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
		if (!isModEnabled('multicompany')) {
			unset($this->fields['entity']);
		}
	}

	/**
	 * Create
	 *
	 * @param	User	$user		User
	 * @param	int		$notrigger	1=no trigger
	 * @return	int					<0 if KO, id if OK
	 */
	public function create(User $user, $notrigger = 0)
	{
		$this->db->begin();
		$res = $this->createCommon($user, 1);
		if ($res > 0 && !$notrigger && $this->call_trigger('ANXHR_TIMEPERIOD_CREATE', $user) < 0) {
			$res = -1;
		}
		if ($res > 0) {
			$this->db->commit();
		} else {
			$this->db->rollback();
		}
		return $res;
	}

	/**
	 * Fetch
	 *
	 * @param	int		$id		Id
	 * @return	int				<0 if KO, 0 not found, >0 OK
	 */
	public function fetch($id)
	{
		return $this->fetchCommon($id);
	}

	/**
	 * Fetch the period of a user and month, create it when missing.
	 *
	 * @param	int		$userid		Employee id
	 * @param	int		$year		Year
	 * @param	int		$month		Month 1..12
	 * @param	User	$actor		User creating (default: global $user)
	 * @return	int					<0 if KO, >0 if OK
	 */
	public function fetchOrCreate($userid, $year, $month, $actor = null)
	{
		global $user;

		$sql = "SELECT rowid FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE fk_user = ".((int) $userid)." AND year = ".((int) $year)." AND month = ".((int) $month);
		$sql .= " AND entity IN (".getEntity($this->element).")";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if ($obj) {
			return $this->fetch((int) $obj->rowid);
		}
		$this->fk_user = (int) $userid;
		$this->year = (int) $year;
		$this->month = (int) $month;
		$this->status = self::STATUS_OPEN;
		$this->balance_start_min = $this->getPreviousBalance();
		$this->balance_end_min = $this->balance_start_min;
		$res = $this->create(is_object($actor) ? $actor : $user);
		if ($res <= 0) {
			return -1;
		}
		return $this->fetch($res);
	}

	/**
	 * Return balance_end of the previous month period of the same user (0 if none).
	 *
	 * @return int
	 */
	public function getPreviousBalance()
	{
		$py = (int) $this->year;
		$pm = (int) $this->month - 1;
		if ($pm < 1) {
			$pm = 12;
			$py--;
		}
		$sql = "SELECT balance_end_min FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE fk_user = ".((int) $this->fk_user)." AND year = ".$py." AND month = ".$pm;
		$sql .= " AND entity IN (".getEntity($this->element).")";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return 0;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		return $obj ? (int) $obj->balance_end_min : 0;
	}

	/**
	 * Recompute the period totals from llx_anxhr_time_day.
	 * balance_end = balance_start (previous period) + sum(diff) - overtime_paid.
	 * Not allowed on approved / exported periods.
	 *
	 * @param	User|null	$actor	User
	 * @return	int					<0 if KO, 0 if not allowed (locked), >0 if OK
	 */
	public function recomputeBalances($actor = null)
	{
		global $user;

		if (in_array((int) $this->status, array(self::STATUS_APPROVED, self::STATUS_EXPORTED), true)) {
			return 0;
		}
		list($first, $last) = $this->getMonthBounds();
		$sql = "SELECT SUM(target_min) as target, SUM(worked_min) as worked, SUM(diff_min) as diff, SUM(overtime50_min) as ot50, SUM(overtime100_min) as ot100,";
		$sql .= " SUM(extra_parttime_min) as extra, SUM(night_min) as night,";
		$sql .= " SUM(CASE WHEN absence_code = 'vacation' THEN 1 ELSE 0 END) as vacation_days,";
		$sql .= " SUM(CASE WHEN absence_code = 'sick' THEN 1 ELSE 0 END) as sick_days,";
		$sql .= " SUM(CASE WHEN absence_code = 'za' THEN target_min ELSE 0 END) as za";
		$sql .= " FROM ".$this->db->prefix()."anxhr_time_day";
		$sql .= " WHERE fk_user = ".((int) $this->fk_user)." AND entity IN (".getEntity('anxhr_time_day').")";
		$sql .= " AND day >= '".$this->db->escape($first)."' AND day <= '".$this->db->escape($last)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);

		$this->target_min = (int) ($obj ? $obj->target : 0);
		$this->worked_min = (int) ($obj ? $obj->worked : 0);
		$this->overtime50_min = (int) ($obj ? $obj->ot50 : 0);
		$this->overtime100_min = (int) ($obj ? $obj->ot100 : 0);
		$this->extra_parttime_min = (int) ($obj ? $obj->extra : 0);
		$this->night_min = (int) ($obj ? $obj->night : 0);
		$this->vacation_days = (float) ($obj ? $obj->vacation_days : 0);
		$this->sick_days = (float) ($obj ? $obj->sick_days : 0);
		$this->za_taken_min = (int) ($obj ? $obj->za : 0);
		$this->balance_start_min = $this->getPreviousBalance();
		$this->balance_end_min = (int) $this->balance_start_min + (int) ($obj ? $obj->diff : 0) - (int) $this->overtime_paid_min;

		$res = $this->updateCommon(is_object($actor) ? $actor : $user, 1);
		return $res > 0 ? 1 : -1;
	}

	/**
	 * Employee confirms his monthly sheet.
	 *
	 * @param	User	$actor	Employee (must be the period owner, or time admin)
	 * @return	int				<0 if KO, >0 if OK
	 */
	public function confirmByEmployee(User $actor)
	{
		if ((int) $actor->id !== (int) $this->fk_user && !$actor->hasRight('anxhr', 'time', 'admin')) {
			$this->error = 'NotEnoughPermissions';
			return -1;
		}
		if (!in_array((int) $this->status, array(self::STATUS_OPEN, self::STATUS_REOPENED), true)) {
			$this->error = 'ErrorBadStatus';
			return -1;
		}
		if ($this->recomputeBalances($actor) < 0) {
			return -1;
		}
		$this->status = self::STATUS_CONFIRMED;
		$this->fk_user_confirm = (int) $actor->id;
		$this->date_confirm = dol_now();
		return $this->updateWithTrigger($actor, 'ANXHR_TIMEPERIOD_CONFIRM');
	}

	/**
	 * Supervisor (subordinates only) or HR approves the period. Days get locked.
	 *
	 * @param	User	$approver	Approver
	 * @return	int					<0 if KO, >0 if OK
	 */
	public function approve(User $approver)
	{
		if (!TimeEntry::userCanApproveFor($approver, (int) $this->fk_user)) {
			$this->error = 'NotEnoughPermissions';
			return -1;
		}
		$allowed = array(self::STATUS_CONFIRMED);
		if ($approver->hasRight('anxhr', 'time', 'admin')) {
			$allowed = array(self::STATUS_OPEN, self::STATUS_CONFIRMED, self::STATUS_REOPENED);
		}
		if (!in_array((int) $this->status, $allowed, true)) {
			$this->error = 'ErrorBadStatus';
			return -1;
		}
		if ($this->recomputeBalances($approver) < 0) {
			return -1;
		}
		$this->db->begin();
		$this->status = self::STATUS_APPROVED;
		$this->fk_user_approve = (int) $approver->id;
		$this->date_approve = dol_now();
		$res = $this->updateCommon($approver, 1);
		if ($res > 0 && $this->lockDays(1) < 0) {
			$res = -1;
		}
		if ($res > 0 && $this->call_trigger('ANXHR_TIMEPERIOD_APPROVE', $approver) < 0) {
			$res = -1;
		}
		if ($res > 0) {
			$this->db->commit();
			return 1;
		}
		$this->db->rollback();
		return -1;
	}

	/**
	 * HR reopens an approved / exported period (with mandatory reason, audit via trigger).
	 *
	 * @param	User	$actor		HR user (time admin)
	 * @param	string	$reason		Reason
	 * @return	int					<0 if KO, >0 if OK
	 */
	public function reopen(User $actor, $reason)
	{
		if (!$actor->hasRight('anxhr', 'time', 'admin')) {
			$this->error = 'NotEnoughPermissions';
			return -1;
		}
		$reason = trim((string) $reason);
		if ($reason === '') {
			$this->error = 'AnxhrErrorReasonRequired';
			return -1;
		}
		if ((int) $this->status === self::STATUS_OPEN || (int) $this->status === self::STATUS_REOPENED) {
			$this->error = 'ErrorBadStatus';
			return -1;
		}
		$this->db->begin();
		$this->status = self::STATUS_REOPENED;
		$this->note = trim((string) $this->note."\n".dol_print_date(dol_now(), 'dayhour', 'tzserver').' '.$actor->login.': '.$reason);
		$this->context['reopen_reason'] = $reason;
		$res = $this->updateCommon($actor, 1);
		if ($res > 0 && $this->lockDays(0) < 0) {
			$res = -1;
		}
		if ($res > 0 && $this->call_trigger('ANXHR_TIMEPERIOD_REOPEN', $actor) < 0) {
			$res = -1;
		}
		if ($res > 0) {
			$this->db->commit();
			return 1;
		}
		$this->db->rollback();
		return -1;
	}

	/**
	 * Mark the period as exported to payroll.
	 *
	 * @param	User	$actor	User with export right
	 * @return	int				<0 if KO, >0 if OK
	 */
	public function setExported(User $actor)
	{
		if ((int) $this->status !== self::STATUS_APPROVED) {
			return 0;
		}
		$this->status = self::STATUS_EXPORTED;
		return $this->updateWithTrigger($actor, 'ANXHR_TIMEPERIOD_EXPORT');
	}

	/**
	 * Lock or unlock the computed days of the period.
	 *
	 * @param	int		$lock	1=lock, 0=unlock
	 * @return	int				<0 if KO, >0 if OK
	 */
	public function lockDays($lock = 1)
	{
		list($first, $last) = $this->getMonthBounds();
		$sql = "UPDATE ".$this->db->prefix()."anxhr_time_day SET locked = ".($lock ? 1 : 0);
		$sql .= " WHERE fk_user = ".((int) $this->fk_user)." AND entity IN (".getEntity('anxhr_time_day').")";
		$sql .= " AND day >= '".$this->db->escape($first)."' AND day <= '".$this->db->escape($last)."'";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return 1;
	}

	/**
	 * Return first and last day of the period month.
	 *
	 * @return array{0:string,1:string}
	 */
	public function getMonthBounds()
	{
		$y = (int) $this->year;
		$m = (int) $this->month;
		$first = sprintf('%04d-%02d-01', $y, $m);
		$last = sprintf('%04d-%02d-%02d', $y, $m, (int) gmdate('t', gmmktime(0, 0, 0, $m, 1, $y)));
		return array($first, $last);
	}

	/**
	 * Create (and recompute) the periods of a month for all relevant users:
	 * active users having computed days in the month or an active contract.
	 *
	 * @param	DoliDB	$db		Database handler
	 * @param	User	$actor	User
	 * @param	int		$year	Year
	 * @param	int		$month	Month
	 * @return	int				Number of periods created or updated, <0 if KO
	 */
	public static function createForAllUsers($db, User $actor, $year, $month)
	{
		$tmp = new self($db);
		$tmp->year = (int) $year;
		$tmp->month = (int) $month;
		list($first, $last) = $tmp->getMonthBounds();

		$userids = array();
		$sql = "SELECT DISTINCT d.fk_user FROM ".$db->prefix()."anxhr_time_day as d";
		$sql .= " INNER JOIN ".$db->prefix()."user as u ON u.rowid = d.fk_user";
		$sql .= " WHERE d.entity IN (".getEntity('anxhr_time_day').")";
		$sql .= " AND d.day >= '".$db->escape($first)."' AND d.day <= '".$db->escape($last)."'";
		$resql = $db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' '.$db->lasterror(), LOG_ERR);
			return -1;
		}
		while ($obj = $db->fetch_object($resql)) {
			$userids[(int) $obj->fk_user] = (int) $obj->fk_user;
		}
		$db->free($resql);
		if (file_exists(__DIR__.'/hrcontract.class.php')) {
			$sql = "SELECT DISTINCT c.fk_user FROM ".$db->prefix()."anxhr_contract as c";
			$sql .= " INNER JOIN ".$db->prefix()."user as u ON u.rowid = c.fk_user AND u.statut = 1";
			$sql .= " WHERE c.entity IN (".getEntity('anxhr_contract').") AND c.status = 1";
			$sql .= " AND c.date_start <= '".$db->escape($last)."' AND (c.date_end IS NULL OR c.date_end >= '".$db->escape($first)."')";
			$resql = $db->query($sql);
			if ($resql) {
				while ($obj = $db->fetch_object($resql)) {
					$userids[(int) $obj->fk_user] = (int) $obj->fk_user;
				}
				$db->free($resql);
			}
		}

		$nb = 0;
		foreach ($userids as $uid) {
			$period = new self($db);
			if ($period->fetchOrCreate($uid, $year, $month, $actor) > 0) {
				$period->recomputeBalances($actor);
				$nb++;
			} else {
				dol_syslog(__METHOD__.' error for user '.$uid.': '.$period->error, LOG_ERR);
			}
		}
		return $nb;
	}

	/**
	 * Update and fire a trigger inside one transaction.
	 *
	 * @param	User	$user		User
	 * @param	string	$trigger	Trigger code
	 * @return	int					<0 if KO, >0 if OK
	 */
	protected function updateWithTrigger(User $user, $trigger)
	{
		$this->db->begin();
		$res = $this->updateCommon($user, 1);
		if ($res > 0 && $this->call_trigger($trigger, $user) < 0) {
			$res = -1;
		}
		if ($res > 0) {
			$this->db->commit();
			return 1;
		}
		$this->db->rollback();
		return -1;
	}

	/**
	 * Return the status label
	 *
	 * @param	int		$mode	Mode
	 * @return	string
	 */
	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 * Return the label of a status
	 *
	 * @param	int		$status	Status
	 * @param	int		$mode	Mode
	 * @return	string
	 */
	public function LibStatut($status, $mode = 0)
	{
		// phpcs:enable
		global $langs;
		$map = array(
			self::STATUS_OPEN => array('AnxhrPeriodOpen', 'status0'),
			self::STATUS_CONFIRMED => array('AnxhrPeriodConfirmed', 'status1'),
			self::STATUS_APPROVED => array('AnxhrPeriodApproved', 'status4'),
			self::STATUS_EXPORTED => array('AnxhrPeriodExported', 'status6'),
			self::STATUS_REOPENED => array('AnxhrPeriodReopened', 'status8'),
		);
		$status = (int) $status;
		if (!isset($map[$status])) {
			return '';
		}
		$label = $langs->transnoentitiesnoconv($map[$status][0]);
		return dolGetStatus($label, $label, '', $map[$status][1], $mode);
	}
}


/**
 * Class AnxhrTimeIntegration
 * Static entry points used by the module triggers (leave approval / cancellation).
 */
class AnxhrTimeIntegration
{
	/**
	 * Leave request approved: recompute the affected days (ZA consumes the time balance,
	 * vacation / sick fulfil the target).
	 *
	 * @param	DoliDB			$db			Database handler
	 * @param	CommonObject	$holiday	Holiday object (fk_user, date_debut, date_fin)
	 * @param	User			$user		User doing the action
	 * @return	int							>=0 number of days recomputed (errors are logged, never blocking)
	 */
	public static function onLeaveApproved($db, $holiday, $user)
	{
		return self::recomputeLeave($db, $holiday, $user);
	}

	/**
	 * Leave request canceled / refused after approval: recompute the affected days.
	 *
	 * @param	DoliDB			$db			Database handler
	 * @param	CommonObject	$holiday	Holiday object
	 * @param	User			$user		User doing the action
	 * @return	int							>=0 number of days recomputed (errors are logged, never blocking)
	 */
	public static function onLeaveCanceled($db, $holiday, $user)
	{
		return self::recomputeLeave($db, $holiday, $user);
	}

	/**
	 * Recompute the days of a leave request.
	 *
	 * @param	DoliDB			$db			Database handler
	 * @param	CommonObject	$holiday	Holiday object
	 * @param	User			$user		User
	 * @return	int
	 */
	protected static function recomputeLeave($db, $holiday, $user)
	{
		if (!is_object($holiday) || empty($holiday->fk_user) || empty($holiday->date_debut) || empty($holiday->date_fin)) {
			return 0;
		}
		$from = TimeEntry::timestampToDay((int) $holiday->date_debut);
		$to = TimeEntry::timestampToDay((int) $holiday->date_fin);
		$res = TimeDay::recomputeRange($db, $user, (int) $holiday->fk_user, $from, $to);
		if ($res < 0) {
			// Never block the core leave workflow because of a time recomputation problem: log only.
			dol_syslog(__METHOD__.' recompute failed for leave '.((int) $holiday->id), LOG_ERR);
			return 0;
		}
		return $res;
	}
}
