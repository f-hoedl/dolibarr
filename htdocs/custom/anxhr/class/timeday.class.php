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
 * \file        htdocs/custom/anxhr/class/timeday.class.php
 * \ingroup     anxhr
 * \brief       Computed working day (cache of TimeEngine results per user and day)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once __DIR__.'/timeengine.class.php';
require_once __DIR__.'/timeentry.class.php';
require_once __DIR__.'/timemodel.class.php';
require_once __DIR__.'/kvrate.class.php';

/**
 * Class TimeDay
 */
class TimeDay extends CommonObject
{
	/** @var string */
	public $module = 'anxhr';
	/** @var string */
	public $element = 'anxhr_time_day';
	/** @var string */
	public $TRIGGER_PREFIX = 'ANXHR_TIMEDAY';
	/** @var string */
	public $table_element = 'anxhr_time_day';
	/** @var string */
	public $picto = 'fa-calendar-day';
	/** @var int */
	public $isextrafieldmanaged = 0;
	/** @var int|string */
	public $ismultientitymanaged = 1;

	/** Max number of days recomputed in one call of recomputeRange(). */
	const MAX_RANGE_DAYS = 400;

	/**
	 * @var array<string,array<string,mixed>>
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'default' => '1', 'enabled' => 1, 'visible' => 0, 'notnull' => 1, 'position' => 5, 'index' => 1),
		'fk_user' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Employee', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'day' => array('type' => 'date', 'label' => 'Date', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'target_min' => array('type' => 'integer', 'label' => 'AnxhrTarget', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1, 'isameasure' => 1),
		'worked_min' => array('type' => 'integer', 'label' => 'AnxhrWorked', 'enabled' => 1, 'position' => 31, 'notnull' => 1, 'visible' => 1, 'isameasure' => 1),
		'break_min' => array('type' => 'integer', 'label' => 'AnxhrBreak', 'enabled' => 1, 'position' => 32, 'notnull' => 1, 'visible' => 1, 'isameasure' => 1),
		'diff_min' => array('type' => 'integer', 'label' => 'AnxhrDiff', 'enabled' => 1, 'position' => 33, 'notnull' => 1, 'visible' => 1, 'isameasure' => 1),
		'overtime50_min' => array('type' => 'integer', 'label' => 'AnxhrOvertime50', 'enabled' => 1, 'position' => 40, 'notnull' => 1, 'visible' => 1, 'isameasure' => 1),
		'overtime100_min' => array('type' => 'integer', 'label' => 'AnxhrOvertime100', 'enabled' => 1, 'position' => 41, 'notnull' => 1, 'visible' => 1, 'isameasure' => 1),
		'extra_parttime_min' => array('type' => 'integer', 'label' => 'AnxhrExtraParttime', 'enabled' => 1, 'position' => 42, 'notnull' => 1, 'visible' => 1, 'isameasure' => 1),
		'night_min' => array('type' => 'integer', 'label' => 'AnxhrNight', 'enabled' => 1, 'position' => 43, 'notnull' => 1, 'visible' => 1, 'isameasure' => 1),
		'holiday_flag' => array('type' => 'integer', 'label' => 'AnxhrPublicHoliday', 'enabled' => 1, 'position' => 50, 'notnull' => 1, 'visible' => 1),
		'absence_code' => array('type' => 'varchar(32)', 'label' => 'AnxhrAbsence', 'enabled' => 1, 'position' => 51, 'notnull' => 0, 'visible' => 1),
		'open_entry' => array('type' => 'integer', 'label' => 'AnxhrOpenEntry', 'enabled' => 1, 'position' => 52, 'notnull' => 1, 'visible' => 1),
		'violations_json' => array('type' => 'text', 'label' => 'AnxhrViolations', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => 1),
		'locked' => array('type' => 'integer', 'label' => 'AnxhrLocked', 'enabled' => 1, 'position' => 70, 'notnull' => 1, 'visible' => 1),
		'computed_at' => array('type' => 'datetime', 'label' => 'AnxhrComputedAt', 'enabled' => 1, 'position' => 80, 'notnull' => 0, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
	);

	/** @var int */
	public $fk_user;
	/** @var int|string */
	public $day;
	/** @var int */
	public $target_min;
	/** @var int */
	public $worked_min;
	/** @var int */
	public $break_min;
	/** @var int */
	public $diff_min;
	/** @var int */
	public $overtime50_min;
	/** @var int */
	public $overtime100_min;
	/** @var int */
	public $extra_parttime_min;
	/** @var int */
	public $night_min;
	/** @var int */
	public $holiday_flag;
	/** @var string|null */
	public $absence_code;
	/** @var int */
	public $open_entry;
	/** @var string|null */
	public $violations_json;
	/** @var int */
	public $locked;
	/** @var int|string */
	public $computed_at;

	/** @var array<string,mixed>|null	Last engine result of recomputeForUserDay() */
	public $lastResult = null;
	/** @var array<string,mixed>|null	Last engine input of recomputeForUserDay() */
	public $lastInput = null;

	/** @var array<int,TimeModel> */
	private $cacheModels = array();
	/** @var array<string,array<int,KvRate>> */
	private $cacheKv = array();
	/** @var array<int,array<string,mixed>> */
	private $cacheContract = array();

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
	 * Recompute one day of one user and store the result (insert or update llx_anxhr_time_day).
	 *
	 * @param	User	$actor		User doing the action
	 * @param	int		$userid		Employee id
	 * @param	string	$day		Day 'Y-m-d'
	 * @param	int		$force		1=recompute even if the day is locked (HR after reopening)
	 * @return	int					1 if computed, 0 if skipped because locked, <0 if KO
	 */
	public function recomputeForUserDay(User $actor, $userid, $day, $force = 0)
	{
		global $conf;

		$userid = (int) $userid;
		$tsday = TimeEntry::dayToTimestamp($day);
		if ($userid <= 0 || $tsday === null) {
			$this->error = 'ErrorBadParameters';
			return -1;
		}

		$existing = $this->fetchRowForUserDay($userid, $day);
		if ($existing === -1) {
			return -1;
		}
		if (is_array($existing) && !empty($existing['locked']) && !$force) {
			return 0;
		}

		$input = $this->buildEngineInput($userid, $day);
		if (!is_array($input)) {
			return -1;
		}
		$engine = new TimeEngine();
		$res = $engine->computeDay($input);
		$this->lastInput = $input;
		$this->lastResult = $res;

		return $this->storeResult($userid, $day, $res, is_array($existing) ? (int) $existing['rowid'] : 0, (int) $conf->entity);
	}

	/**
	 * Build the TimeEngine input of a user and day from the database (entries, contract, model, KV,
	 * public holiday, absence, previous clock out).
	 *
	 * @param	int		$userid		Employee id
	 * @param	string	$day		Day 'Y-m-d'
	 * @return	array<string,mixed>|int		Engine input or <0 if KO
	 */
	public function buildEngineInput($userid, $day)
	{
		$userid = (int) $userid;
		$tsday = TimeEntry::dayToTimestamp($day);
		if ($userid <= 0 || $tsday === null) {
			$this->error = 'ErrorBadParameters';
			return -1;
		}
		$entryObj = new TimeEntry($this->db);
		$ctx = $entryObj->fetchDayContext($userid, $day);
		if (!is_array($ctx)) {
			$this->error = $entryObj->error;
			return -1;
		}
		$contract = $this->getContractData($userid, $tsday, $day);
		$model = $this->getModelArray($contract);
		$kv = $this->getKvArray($contract, $model, $day);
		$absence = $this->getAbsence($userid, $tsday, $day);

		$engineEntries = array();
		foreach ($ctx['entries'] as $e) {
			$engineEntries[] = array('type' => $e['type'], 'time' => $e['time'], 'homeoffice' => $e['homeoffice']);
		}
		return array(
			'day' => $day,
			'entries' => $engineEntries,
			'model' => $model,
			'kv' => $kv,
			'contract' => array('weekly_hours' => $contract['weekly_hours']),
			'is_public_holiday' => $this->isPublicHoliday($tsday),
			'absence_code' => $absence['code'],
			'absence_fraction' => $absence['fraction'],
			'previous_out' => $ctx['previous_out'],
		);
	}

	/**
	 * Compute a day "live" without storing it: an open shift is closed virtually at $nowts.
	 * Used by the clock page for running counters.
	 *
	 * @param	int		$userid		Employee id
	 * @param	string	$day		Day 'Y-m-d'
	 * @param	int		$nowts		Current timestamp
	 * @return	array<string,mixed>|int		Engine result or <0 if KO
	 */
	public function computeLive($userid, $day, $nowts)
	{
		$input = $this->buildEngineInput($userid, $day);
		if (!is_array($input)) {
			return -1;
		}
		$last = empty($input['entries']) ? null : end($input['entries']);
		if ($last !== null && $last['type'] !== 'out') {
			$input['entries'][] = array('type' => 'out', 'time' => dol_print_date($nowts, '%Y-%m-%d %H:%M:%S', 'tzserver'), 'homeoffice' => 0);
		}
		$engine = new TimeEngine();
		return $engine->computeDay($input);
	}

	/**
	 * Recompute a range of days for one user.
	 *
	 * @param	DoliDB		$db			Database handler
	 * @param	User		$actor		User doing the action
	 * @param	int			$userid		Employee id
	 * @param	string|int	$from		First day 'Y-m-d' or timestamp
	 * @param	string|int	$to			Last day 'Y-m-d' or timestamp (included)
	 * @param	int			$force		1=also locked days
	 * @return	int						Number of days computed, <0 if KO
	 */
	public static function recomputeRange($db, User $actor, $userid, $from, $to, $force = 0)
	{
		$fromDay = is_numeric($from) ? TimeEntry::timestampToDay((int) $from) : (string) $from;
		$toDay = is_numeric($to) ? TimeEntry::timestampToDay((int) $to) : (string) $to;
		$cursor = TimeEntry::dayToTimestamp($fromDay);
		$end = TimeEntry::dayToTimestamp($toDay);
		if ($cursor === null || $end === null || $end < $cursor) {
			return -1;
		}
		$obj = new self($db);
		$nb = 0;
		$i = 0;
		while ($cursor <= $end && $i < self::MAX_RANGE_DAYS) {
			$day = TimeEntry::timestampToDay($cursor);
			$r = $obj->recomputeForUserDay($actor, $userid, $day, $force);
			if ($r < 0) {
				dol_syslog(__METHOD__.' error on user '.((int) $userid).' day '.$day.': '.$obj->error, LOG_ERR);
				return -1;
			}
			$nb += $r;
			// Add 26 hours then normalize to midnight to be safe on DST changes.
			$cursor = TimeEntry::dayToTimestamp(TimeEntry::timestampToDay($cursor + 26 * 3600));
			$i++;
		}
		return $nb;
	}

	/**
	 * Recompute one day for all relevant users: users with entries around that day or with an active contract.
	 * Used by the nightly cron job.
	 *
	 * @param	DoliDB	$db		Database handler
	 * @param	User	$actor	User doing the action
	 * @param	string	$day	Day 'Y-m-d'
	 * @return	int				Number of users computed, <0 if KO
	 */
	public static function recomputeAllUsersForDay($db, User $actor, $day)
	{
		$ts = TimeEntry::dayToTimestamp($day);
		if ($ts === null) {
			return -1;
		}
		$userids = array();
		$sql = "SELECT DISTINCT fk_user FROM ".$db->prefix()."anxhr_time_entry";
		$sql .= " WHERE entity IN (".getEntity('anxhr_time_entry').")";
		$sql .= " AND entry_datetime >= '".$db->idate($ts - 86400)."' AND entry_datetime < '".$db->idate($ts + 2 * 86400)."'";
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
			$sql .= " AND c.date_start <= '".$db->escape($day)."' AND (c.date_end IS NULL OR c.date_end >= '".$db->escape($day)."')";
			$resql = $db->query($sql);
			if ($resql) {
				while ($obj = $db->fetch_object($resql)) {
					$userids[(int) $obj->fk_user] = (int) $obj->fk_user;
				}
				$db->free($resql);
			} else {
				dol_syslog(__METHOD__.' contract table not available: '.$db->lasterror(), LOG_WARNING);
			}
		}

		$obj = new self($db);
		$nb = 0;
		foreach ($userids as $uid) {
			if ($obj->recomputeForUserDay($actor, $uid, $day) >= 0) {
				$nb++;
			} else {
				dol_syslog(__METHOD__.' error for user '.$uid.': '.$obj->error, LOG_ERR);
			}
		}
		return $nb;
	}

	/**
	 * Load the computed days of a month of one user.
	 *
	 * @param	int		$userid		User id
	 * @param	int		$year		Year
	 * @param	int		$month		Month
	 * @return	array<string,array<string,mixed>>|int	day => row (violations decoded), <0 if KO
	 */
	public function fetchMonth($userid, $year, $month)
	{
		$first = sprintf('%04d-%02d-01', (int) $year, (int) $month);
		$last = sprintf('%04d-%02d-%02d', (int) $year, (int) $month, (int) gmdate('t', gmmktime(0, 0, 0, (int) $month, 1, (int) $year)));
		return $this->fetchDays($userid, $first, $last);
	}

	/**
	 * Load the computed days of a user between two days (included).
	 *
	 * @param	int		$userid		User id
	 * @param	string	$first		First day Y-m-d
	 * @param	string	$last		Last day Y-m-d
	 * @return	array<string,array<string,mixed>>|int	day => row, <0 if KO
	 */
	public function fetchDays($userid, $first, $last)
	{
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $first) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $last)) {
			return -1;
		}
		$sql = "SELECT rowid, day, target_min, worked_min, break_min, diff_min, overtime50_min, overtime100_min, extra_parttime_min, night_min, holiday_flag, absence_code, open_entry, violations_json, locked";
		$sql .= " FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE fk_user = ".((int) $userid)." AND entity IN (".getEntity($this->element).")";
		$sql .= " AND day >= '".$this->db->escape($first)."' AND day <= '".$this->db->escape($last)."'";
		$sql .= " ORDER BY day ASC";
		$sql .= $this->db->plimit(400);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$res = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$row = (array) $obj;
			$row['day'] = substr((string) $obj->day, 0, 10);
			$decoded = empty($obj->violations_json) ? array() : json_decode((string) $obj->violations_json, true);
			$row['violations'] = is_array($decoded) ? $decoded : array();
			$res[$row['day']] = $row;
		}
		$this->db->free($resql);
		return $res;
	}

	/**
	 * Return the existing row of a user and day.
	 *
	 * @param	int		$userid		User id
	 * @param	string	$day		Day Y-m-d
	 * @return	array{rowid:int,locked:int}|int		Row, 0 if none, -1 if KO
	 */
	public function fetchRowForUserDay($userid, $day)
	{
		$sql = "SELECT rowid, locked FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE fk_user = ".((int) $userid)." AND day = '".$this->db->escape($day)."'";
		$sql .= " AND entity IN (".getEntity($this->element).")";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		return $obj ? array('rowid' => (int) $obj->rowid, 'locked' => (int) $obj->locked) : 0;
	}

	/**
	 * Insert or update the computed row.
	 *
	 * @param	int					$userid		User id
	 * @param	string				$day		Day
	 * @param	array<string,mixed>	$res		Engine result
	 * @param	int					$rowid		Existing row id or 0
	 * @param	int					$entity		Entity
	 * @return	int								1 if OK, <0 if KO
	 */
	protected function storeResult($userid, $day, array $res, $rowid, $entity)
	{
		$now = $this->db->idate(dol_now());
		$vals = array(
			'target_min' => (int) $res['target_min'],
			'worked_min' => (int) $res['worked_min'],
			'break_min' => (int) $res['break_min'],
			'diff_min' => (int) $res['diff_min'],
			'overtime50_min' => (int) $res['overtime50_min'],
			'overtime100_min' => (int) $res['overtime100_min'],
			'extra_parttime_min' => (int) $res['extra_parttime_min'],
			'night_min' => (int) $res['night_min'],
			'holiday_flag' => (int) $res['holiday_flag'],
			'open_entry' => empty($res['open_entry']) ? 0 : 1,
		);
		$absence = ($res['absence_code'] === null) ? "NULL" : "'".$this->db->escape((string) $res['absence_code'])."'";
		$violations = empty($res['violations']) ? "NULL" : "'".$this->db->escape(json_encode($res['violations']))."'";

		if ($rowid > 0) {
			$set = array();
			foreach ($vals as $k => $v) {
				$set[] = $k." = ".((int) $v);
			}
			$sql = "UPDATE ".$this->db->prefix().$this->table_element." SET ".implode(', ', $set);
			$sql .= ", absence_code = ".$absence.", violations_json = ".$violations.", computed_at = '".$now."'";
			$sql .= " WHERE rowid = ".((int) $rowid);
		} else {
			$sql = "INSERT INTO ".$this->db->prefix().$this->table_element." (entity, fk_user, day, ".implode(', ', array_keys($vals)).", absence_code, violations_json, locked, computed_at)";
			$sql .= " VALUES (".((int) $entity).", ".((int) $userid).", '".$this->db->escape($day)."', ".implode(', ', array_map('intval', $vals)).", ".$absence.", ".$violations.", 0, '".$now."')";
		}
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			dol_syslog(__METHOD__.' '.$this->error, LOG_ERR);
			return -1;
		}
		return 1;
	}

	/**
	 * Return contract data valid at a day: weekly_hours, fk_timemodel, kv_code.
	 * Source: HrContract (if available), else llx_user.weeklyhours.
	 *
	 * @param	int		$userid		User id
	 * @param	int		$tsday		Timestamp of day
	 * @param	string	$day		Day Y-m-d
	 * @return	array{weekly_hours:float,fk_timemodel:int,kv_code:string}
	 */
	protected function getContractData($userid, $tsday, $day)
	{
		if (isset($this->cacheContract[$userid])) {
			$c = $this->cacheContract[$userid];
			if ($c['from'] <= $day && ($c['to'] === '' || $c['to'] >= $day)) {
				return $c['data'];
			}
		}
		$data = array('weekly_hours' => 0.0, 'fk_timemodel' => 0, 'kv_code' => '');
		$from = $day;
		$to = $day;
		$found = false;
		if (file_exists(__DIR__.'/hrcontract.class.php')) {
			include_once __DIR__.'/hrcontract.class.php';
			if (class_exists('HrContract')) {
				$contract = new HrContract($this->db);
				if (method_exists($contract, 'fetchActiveForUser') && $contract->fetchActiveForUser($userid, $tsday) > 0) {
					$data['weekly_hours'] = (float) $contract->weekly_hours;
					$data['fk_timemodel'] = (int) $contract->fk_timemodel;
					$data['kv_code'] = (string) $contract->kv_code;
					$from = empty($contract->date_start) ? $day : dol_print_date($contract->date_start, '%Y-%m-%d', 'tzserver');
					$to = empty($contract->date_end) ? '' : dol_print_date($contract->date_end, '%Y-%m-%d', 'tzserver');
					$found = true;
				}
			}
		}
		if (!$found || $data['weekly_hours'] <= 0) {
			$sql = "SELECT weeklyhours FROM ".$this->db->prefix()."user WHERE rowid = ".((int) $userid);
			$resql = $this->db->query($sql);
			if ($resql) {
				$obj = $this->db->fetch_object($resql);
				if ($obj && (float) $obj->weeklyhours > 0) {
					$data['weekly_hours'] = (float) $obj->weeklyhours;
				}
				$this->db->free($resql);
			}
		}
		$this->cacheContract[$userid] = array('from' => $from, 'to' => $to, 'data' => $data);
		return $data;
	}

	/**
	 * Return the engine model array for a contract (contract model, else setup default, else built-in).
	 *
	 * @param	array{weekly_hours:float,fk_timemodel:int,kv_code:string}	$contract	Contract data
	 * @return	array<string,mixed>
	 */
	protected function getModelArray(array $contract)
	{
		$modelid = (int) $contract['fk_timemodel'];
		if ($modelid <= 0) {
			$modelid = getDolGlobalInt('ANXHR_TIME_DEFAULT_MODEL');
		}
		if ($modelid > 0) {
			if (!isset($this->cacheModels[$modelid])) {
				$m = new TimeModel($this->db);
				$this->cacheModels[$modelid] = ($m->fetch($modelid) > 0) ? $m : null;
			}
			if ($this->cacheModels[$modelid] !== null) {
				$arr = $this->cacheModels[$modelid]->toEngineArray($contract['weekly_hours']);
				$arr['kv_code'] = (string) $this->cacheModels[$modelid]->kv_code;
				return $arr;
			}
		}
		$arr = TimeModel::fallbackEngineArray();
		if ($contract['weekly_hours'] > 0) {
			$daily = (int) round($contract['weekly_hours'] * 60 / 5);
			for ($d = 1; $d <= 5; $d++) {
				$arr['daily_minutes'][$d] = $daily;
			}
		}
		$arr['kv_code'] = '';
		return $arr;
	}

	/**
	 * Return the KV parameters valid at a day (contract KV, else model KV, else setup default, else AZG fallback).
	 *
	 * @param	array{weekly_hours:float,fk_timemodel:int,kv_code:string}	$contract	Contract data
	 * @param	array<string,mixed>											$model		Model array
	 * @param	string														$day		Day
	 * @return	array<string,mixed>
	 */
	protected function getKvArray(array $contract, array $model, $day)
	{
		$code = $contract['kv_code'] !== '' ? $contract['kv_code'] : (!empty($model['kv_code']) ? $model['kv_code'] : getDolGlobalString('ANXHR_DEFAULT_KV', 'SWOE'));
		if (!isset($this->cacheKv[$code])) {
			$this->cacheKv[$code] = array();
			$kvobj = new KvRate($this->db);
			$all = $kvobj->fetchAllRates();
			if (is_array($all)) {
				foreach ($all as $r) {
					if ($r->kv_code === $code) {
						$this->cacheKv[$code][] = $r;
					}
				}
			}
		}
		$best = null;
		foreach ($this->cacheKv[$code] as $r) {
			$from = dol_print_date($r->valid_from, '%Y-%m-%d', 'tzserver');
			if ($from <= $day && ($best === null || $from > dol_print_date($best->valid_from, '%Y-%m-%d', 'tzserver'))) {
				$best = $r;
			}
		}
		return $best !== null ? $best->toEngineArray() : KvRate::fallbackEngineArray();
	}

	/**
	 * Check if a day is a public holiday of the company country (dictionary c_hrm_public_holiday).
	 *
	 * @param	int		$tsday	Timestamp of day (server tz midnight)
	 * @return	bool
	 */
	protected function isPublicHoliday($tsday)
	{
		global $mysoc;

		$day = TimeEntry::timestampToDay($tsday);
		$gmts = dol_mktime(0, 0, 0, (int) substr($day, 5, 2), (int) substr($day, 8, 2), (int) substr($day, 0, 4), 'gmt');
		$country = (is_object($mysoc) && !empty($mysoc->country_code)) ? $mysoc->country_code : 'AT';
		// Start = end with lastday=1 counts only this day; weekends are not counted as non working days here.
		$nb = num_public_holiday($gmts, $gmts, $country, 1, 0, 0, 0, 0);
		return (is_numeric($nb) && (int) $nb > 0);
	}

	/**
	 * Return absence of a user on a day from approved leave requests (core holiday module).
	 * Mapping of leave type code: contains 'ZA' (or label Zeitausgleich) -> za, 'SICK'/'KRANK' -> sick, else vacation.
	 *
	 * @param	int		$userid		User id
	 * @param	int		$tsday		Timestamp of day
	 * @param	string	$day		Day Y-m-d
	 * @return	array{code:?string,fraction:float}
	 */
	protected function getAbsence($userid, $tsday, $day)
	{
		$res = array('code' => null, 'fraction' => 1.0);
		if (!isModEnabled('holiday')) {
			return $res;
		}
		require_once DOL_DOCUMENT_ROOT.'/holiday/class/holiday.class.php';
		$holiday = new Holiday($this->db);
		$avail = $holiday->verifDateHolidayForTimestamp($userid, $tsday, (string) Holiday::STATUS_APPROVED);
		$absentMorning = empty($avail['morning']);
		$absentAfternoon = empty($avail['afternoon']);
		if (!$absentMorning && !$absentAfternoon) {
			return $res;
		}
		$res['fraction'] = ($absentMorning && $absentAfternoon) ? 1.0 : 0.5;

		$sql = "SELECT t.code, t.label FROM ".$this->db->prefix()."holiday as h";
		$sql .= " LEFT JOIN ".$this->db->prefix()."c_holiday_types as t ON t.rowid = h.fk_type";
		$sql .= " WHERE h.fk_user = ".((int) $userid)." AND h.statut = ".((int) Holiday::STATUS_APPROVED);
		$sql .= " AND h.entity IN (".getEntity('holiday').")";
		$sql .= " AND h.date_debut <= '".$this->db->escape($day)."' AND h.date_fin >= '".$this->db->escape($day)."'";
		$sql .= $this->db->plimit(1);
		$resql = $this->db->query($sql);
		$code = '';
		$label = '';
		if ($resql) {
			$obj = $this->db->fetch_object($resql);
			if ($obj) {
				$code = strtoupper((string) $obj->code);
				$label = (string) $obj->label;
			}
			$this->db->free($resql);
		}
		$res['code'] = self::mapLeaveType($code, $label);
		return $res;
	}

	/**
	 * Map a core leave type to an engine absence code.
	 *
	 * @param	string	$code	Leave type code
	 * @param	string	$label	Leave type label
	 * @return	string
	 */
	public static function mapLeaveType($code, $label = '')
	{
		$code = strtoupper((string) $code);
		if (strpos($code, 'ZA') !== false || stripos((string) $label, 'Zeitausgleich') !== false) {
			return 'za';
		}
		if (strpos($code, 'SICK') !== false || strpos($code, 'KRANK') !== false) {
			return 'sick';
		}
		return 'vacation';
	}
}
