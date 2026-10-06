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
 * \file        htdocs/custom/anxhr/class/timeentry.class.php
 * \ingroup     anxhr
 * \brief       Clock entry (in, out, break_start, break_end) - CRUD class
 *
 * Time zone convention of the time module: entries are stored as absolute timestamps ($db->idate()),
 * every working day / wall clock computation uses the company time zone (constant ANXHR_TIMEZONE,
 * default Europe/Vienna, see anxhrTimeZone()), so results never depend on the PHP server zone or on
 * who triggers a recomputation.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once __DIR__.'/../lib/anxhr_time.lib.php';

/**
 * Class TimeEntry
 */
class TimeEntry extends CommonObject
{
	/** @var string */
	public $module = 'anxhr';
	/** @var string */
	public $element = 'anxhr_time_entry';
	/** @var string */
	public $TRIGGER_PREFIX = 'ANXHR_TIMEENTRY';
	/** @var string */
	public $table_element = 'anxhr_time_entry';
	/** @var string */
	public $picto = 'fa-clock';
	/** @var int */
	public $isextrafieldmanaged = 0;
	/** @var int|string */
	public $ismultientitymanaged = 1;

	const STATUS_REPLACED = 0;
	const STATUS_ACTIVE = 1;

	/** Entries older than this (hours) do not keep a user "clocked in" (forgotten clock out). */
	const OPEN_STATE_MAX_HOURS = 16;

	/** @var string[] */
	public static $types = array('in', 'break_start', 'break_end', 'out');
	/** @var string[] */
	public static $sources = array('web', 'pwa', 'pwa_offline', 'kiosk', 'api', 'manual', 'import');

	/**
	 * @var array<string,array<string,mixed>>
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'default' => '1', 'enabled' => 1, 'visible' => 0, 'notnull' => 1, 'position' => 5, 'index' => 1),
		'fk_user' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Employee', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'foreignkey' => 'user.rowid'),
		'entry_type' => array('type' => 'varchar(16)', 'label' => 'AnxhrEntryType', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1, 'arrayofkeyval' => array('in' => 'AnxhrEntryIn', 'break_start' => 'AnxhrEntryBreakStart', 'break_end' => 'AnxhrEntryBreakEnd', 'out' => 'AnxhrEntryOut')),
		'entry_datetime' => array('type' => 'datetime', 'label' => 'AnxhrEntryTime', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'homeoffice' => array('type' => 'integer', 'label' => 'AnxhrHomeoffice', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1, 'default' => '0'),
		'source' => array('type' => 'varchar(16)', 'label' => 'AnxhrSource', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1, 'default' => 'web'),
		'fk_correction' => array('type' => 'integer', 'label' => 'AnxhrCorrection', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => -1),
		'device_id' => array('type' => 'varchar(64)', 'label' => 'AnxhrDeviceId', 'enabled' => 1, 'position' => 70, 'notnull' => 0, 'visible' => -1),
		'ip' => array('type' => 'varchar(45)', 'label' => 'IPAddress', 'enabled' => 1, 'position' => 80, 'notnull' => 0, 'visible' => -1),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 1, 'visible' => -2, 'foreignkey' => 'user.rowid'),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => -1, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => -1, 'visible' => -2),
		'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => 1, 'position' => 2000, 'notnull' => 1, 'visible' => 1, 'default' => '1', 'index' => 1, 'arrayofkeyval' => array(0 => 'AnxhrEntryReplaced', 1 => 'AnxhrEntryActive')),
	);

	/** @var int */
	public $rowid;
	/** @var int */
	public $fk_user;
	/** @var string */
	public $entry_type;
	/** @var int|string	Timestamp */
	public $entry_datetime;
	/** @var int */
	public $homeoffice;
	/** @var string */
	public $source;
	/** @var int|null */
	public $fk_correction;
	/** @var string|null */
	public $device_id;
	/** @var string|null */
	public $ip;
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
		if (!in_array($this->entry_type, self::$types, true)) {
			$this->error = 'ErrorBadValueForParameter';
			return -1;
		}
		if (!in_array($this->source, self::$sources, true)) {
			$this->source = 'web';
		}
		if ($this->status === null || $this->status === '') {
			$this->status = self::STATUS_ACTIVE;
		}
		$this->db->begin();
		$res = $this->createCommon($user, 1);
		if ($res > 0 && !$notrigger && $this->call_trigger('ANXHR_TIMEENTRY_CREATE', $user) < 0) {
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
	 * Update
	 *
	 * @param	User	$user		User
	 * @param	int		$notrigger	1=no trigger
	 * @return	int					<0 if KO, >0 if OK
	 */
	public function update(User $user, $notrigger = 0)
	{
		$this->db->begin();
		$res = $this->updateCommon($user, 1);
		if ($res > 0 && !$notrigger && $this->call_trigger('ANXHR_TIMEENTRY_MODIFY', $user) < 0) {
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
	 * Delete (physical deletion is reserved to admin tools; business flows mark entries as replaced)
	 *
	 * @param	User	$user		User
	 * @param	int		$notrigger	1=no trigger
	 * @return	int					<0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = 0)
	{
		$this->db->begin();
		$res = 1;
		if (!$notrigger && $this->call_trigger('ANXHR_TIMEENTRY_DELETE', $user) < 0) {
			$res = -1;
		}
		if ($res > 0) {
			$res = $this->deleteCommon($user, 1);
		}
		if ($res > 0) {
			$this->db->commit();
		} else {
			$this->db->rollback();
		}
		return $res;
	}

	/**
	 * Return current clock state of a user: 'out', 'in' or 'break'.
	 * An entry older than OPEN_STATE_MAX_HOURS is treated as 'out' (forgotten clock out stays an
	 * OPEN_ENTRY violation on its day and must be corrected via a correction request).
	 *
	 * @param	DoliDB	$db			Database handler
	 * @param	int		$userid		User id
	 * @return	string				'out'|'in'|'break'
	 */
	public static function getLastStateForUser($db, $userid)
	{
		$last = self::getLastEntryForUser($db, $userid);
		if (empty($last) || (dol_now() - $last['ts']) > self::OPEN_STATE_MAX_HOURS * 3600) {
			return 'out';
		}
		switch ($last['type']) {
			case 'in':
			case 'break_end':
				return 'in';
			case 'break_start':
				return 'break';
			default:
				return 'out';
		}
	}

	/**
	 * Return the last active entry of a user.
	 *
	 * @param	DoliDB	$db			Database handler
	 * @param	int		$userid		User id
	 * @return	array{id:int,type:string,ts:int,homeoffice:int}|null
	 */
	public static function getLastEntryForUser($db, $userid)
	{
		$sql = "SELECT rowid, entry_type, entry_datetime, homeoffice FROM ".$db->prefix()."anxhr_time_entry";
		$sql .= " WHERE fk_user = ".((int) $userid)." AND status = ".self::STATUS_ACTIVE;
		$sql .= " AND entity IN (".getEntity('anxhr_time_entry').")";
		$sql .= " AND entry_datetime <= '".$db->idate(dol_now() + 300)."'";
		$sql .= " ORDER BY entry_datetime DESC, rowid DESC";
		$sql .= $db->plimit(1);
		$resql = $db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' '.$db->lasterror(), LOG_ERR);
			return null;
		}
		$obj = $db->fetch_object($resql);
		$db->free($resql);
		if (!$obj) {
			return null;
		}
		return array('id' => (int) $obj->rowid, 'type' => (string) $obj->entry_type, 'ts' => (int) $db->jdate($obj->entry_datetime), 'homeoffice' => (int) $obj->homeoffice);
	}

	/**
	 * Return the valid next actions for a state.
	 *
	 * @param	string	$state	'out'|'in'|'break'
	 * @return	string[]
	 */
	public static function getNextActions($state)
	{
		switch ($state) {
			case 'in':
				return array('break_start', 'out');
			case 'break':
				return array('break_end', 'out');
			default:
				return array('in');
		}
	}

	/**
	 * Return the active entries belonging to a working day of a user.
	 *
	 * @param	int		$userid		User id
	 * @param	string	$day		Day 'Y-m-d'
	 * @return	array<int,array<string,mixed>>|int	List of entries (id, type, time, ts, homeoffice, source, fk_correction) or <0 if KO
	 */
	public function fetchForUserDay($userid, $day)
	{
		$ctx = $this->fetchDayContext($userid, $day);
		if (!is_array($ctx)) {
			return -1;
		}
		return $ctx['entries'];
	}

	/**
	 * Load the entries of a working day plus context (previous clock out for the rest check).
	 * A shift belongs to the day of its clock in: entries after midnight up to the first clock out
	 * are attached to the previous day; leading entries of a day that close a shift of the previous
	 * day are skipped. One query covers day-1 .. day+1.
	 *
	 * @param	int		$userid		User id
	 * @param	string	$day		Day 'Y-m-d'
	 * @return	array{entries:array<int,array<string,mixed>>,previous_out:?string}|int	<0 if KO
	 */
	public function fetchDayContext($userid, $day)
	{
		$ts = self::dayToTimestamp($day);
		if ($ts === null) {
			return -1;
		}
		// Day boundaries in the company time zone (days have 23 or 25 hours on DST change days).
		$dayEnd = self::dayEndTimestamp($day);
		$from = self::dayToTimestamp(self::timestampToDay($ts - 43200));
		$to = self::dayEndTimestamp(self::timestampToDay($dayEnd + 43200)) - 1;
		$sql = "SELECT rowid, entry_type, entry_datetime, homeoffice, source, fk_correction FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE fk_user = ".((int) $userid)." AND status = ".self::STATUS_ACTIVE;
		$sql .= " AND entity IN (".getEntity($this->element).")";
		$sql .= " AND entry_datetime >= '".$this->db->idate($from)."' AND entry_datetime <= '".$this->db->idate($to)."'";
		$sql .= " ORDER BY entry_datetime ASC, rowid ASC";
		$sql .= $this->db->plimit(1000);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$prev = array();
		$cur = array();
		$next = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$ets = (int) $this->db->jdate($obj->entry_datetime);
			$row = array(
				'id' => (int) $obj->rowid,
				'type' => (string) $obj->entry_type,
				'ts' => $ets,
				'time' => anxhrTsToTz($ets, 'Y-m-d H:i:s'),
				'homeoffice' => (int) $obj->homeoffice,
				'source' => (string) $obj->source,
				'fk_correction' => (int) $obj->fk_correction,
			);
			if ($ets < $ts) {
				$prev[] = $row;
			} elseif ($ets < $dayEnd) {
				$cur[] = $row;
			} else {
				$next[] = $row;
			}
		}
		$this->db->free($resql);

		// Leading entries of the day that close a shift started the day before belong to the previous day.
		$prevOpen = (!empty($prev) && end($prev)['type'] !== 'out');
		$previousOut = null;
		foreach ($prev as $p) {
			if ($p['type'] === 'out') {
				$previousOut = $p['time'];
			}
		}
		if ($prevOpen) {
			while (!empty($cur) && $cur[0]['type'] !== 'in') {
				$closing = array_shift($cur);
				if ($closing['type'] === 'out') {
					$previousOut = $closing['time'];
					break;
				}
			}
		}
		// Shift still open at midnight: attach next day entries up to and including the first clock out.
		$lastType = empty($cur) ? 'out' : end($cur)['type'];
		if ($lastType !== 'out' && !empty($cur)) {
			foreach ($next as $n) {
				if ($n['type'] === 'in') {
					break;
				}
				$cur[] = $n;
				if ($n['type'] === 'out') {
					break;
				}
			}
		}
		return array('entries' => array_values($cur), 'previous_out' => $previousOut);
	}

	/**
	 * Return active entries of a user between two days, grouped by calendar day (display helper).
	 *
	 * @param	int		$userid		User id
	 * @param	string	$first		First day Y-m-d
	 * @param	string	$last		Last day Y-m-d (included)
	 * @return	array<string,array<int,array{id:int,type:string,ts:int,homeoffice:int,source:string}>>|int	<0 if KO
	 */
	public function fetchRangeGroupedByDay($userid, $first, $last)
	{
		$from = self::dayToTimestamp($first);
		$to = self::dayEndTimestamp($last);
		if ($from === null || $to === null) {
			return -1;
		}
		$sql = "SELECT rowid, entry_type, entry_datetime, homeoffice, source FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE fk_user = ".((int) $userid)." AND status = ".self::STATUS_ACTIVE;
		$sql .= " AND entity IN (".getEntity($this->element).")";
		$sql .= " AND entry_datetime >= '".$this->db->idate($from)."' AND entry_datetime < '".$this->db->idate($to)."'";
		$sql .= " ORDER BY entry_datetime ASC, rowid ASC";
		$sql .= $this->db->plimit(5000);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$res = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$ts = (int) $this->db->jdate($obj->entry_datetime);
			$res[self::timestampToDay($ts)][] = array('id' => (int) $obj->rowid, 'type' => (string) $obj->entry_type, 'ts' => $ts, 'homeoffice' => (int) $obj->homeoffice, 'source' => (string) $obj->source);
		}
		$this->db->free($resql);
		return $res;
	}

	/**
	 * Format a list of entries compactly: "08:00-12:00, 12:30-17:00" (breaks shown as gaps).
	 *
	 * @param	array<int,array{type:string,ts:int}>	$entries	Entries
	 * @return	string
	 */
	public static function formatCompact(array $entries)
	{
		$parts = array();
		$start = null;
		foreach ($entries as $e) {
			$hm = anxhrTsToTz($e['ts'], 'H:i');
			if ($e['type'] === 'in' || $e['type'] === 'break_end') {
				if ($start !== null) {
					$parts[] = $start.'-?';
				}
				$start = $hm;
			} else {
				$parts[] = ($start !== null ? $start : '?').'-'.$hm;
				$start = null;
			}
		}
		if ($start !== null) {
			$parts[] = $start.'-';
		}
		return implode(', ', $parts);
	}

	/**
	 * Check if a user may approve time data (corrections, periods) of an employee:
	 * time admin, or time approve right and employee is a direct or indirect subordinate.
	 * Nobody approves his own time data (four eyes principle), not even a time admin, unless the
	 * constant ANXHR_ALLOW_SELF_APPROVAL is set (very small companies).
	 *
	 * @param	User	$approver	Approver
	 * @param	int		$userid		Employee id
	 * @return	bool
	 */
	public static function userCanApproveFor(User $approver, $userid)
	{
		$userid = (int) $userid;
		if ($userid <= 0) {
			return false;
		}
		if ($userid == (int) $approver->id && !getDolGlobalInt('ANXHR_ALLOW_SELF_APPROVAL')) {
			return false;
		}
		if ($approver->hasRight('anxhr', 'time', 'admin')) {
			return true;
		}
		if (!$approver->hasRight('anxhr', 'time', 'approve')) {
			return false;
		}
		if ($userid == (int) $approver->id) {
			// Self approval allowed by setup.
			return true;
		}
		$childs = $approver->getAllChildIds(0);
		return is_array($childs) && in_array($userid, array_map('intval', $childs), true);
	}

	/**
	 * Convert 'Y-m-d' into timestamp of midnight in the company time zone (ANXHR_TIMEZONE).
	 *
	 * @param	string	$day	Day
	 * @return	int|null
	 */
	public static function dayToTimestamp($day)
	{
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $day)) {
			return null;
		}
		return anxhrTzToTs($day);
	}

	/**
	 * Convert a timestamp into 'Y-m-d' in the company time zone (ANXHR_TIMEZONE).
	 *
	 * @param	int		$ts		Timestamp
	 * @return	string
	 */
	public static function timestampToDay($ts)
	{
		return anxhrTsToTz($ts, 'Y-m-d');
	}

	/**
	 * Return the timestamp of the midnight following a day in the company time zone
	 * (a day is 23 or 25 hours long on DST change days).
	 *
	 * @param	string	$day	Day Y-m-d
	 * @return	int|null
	 */
	public static function dayEndTimestamp($day)
	{
		$ts = self::dayToTimestamp($day);
		if ($ts === null) {
			return null;
		}
		return anxhrTzToTs(gmdate('Y-m-d', gmmktime(12, 0, 0, (int) substr($day, 5, 2), (int) substr($day, 8, 2) + 1, (int) substr($day, 0, 4))));
	}
}
