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
 * \file        htdocs/custom/anxhr/class/timecorrection.class.php
 * \ingroup     anxhr
 * \brief       Correction request for the clock entries of one day (employee requests, supervisor approves)
 *
 * Original entries are never deleted: on approval they are marked as replaced (status 0) and new
 * entries with source 'manual' and fk_correction are inserted (audit trail, paragraph 26 AZG).
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once __DIR__.'/timeentry.class.php';
require_once __DIR__.'/timeday.class.php';

/**
 * Class TimeCorrection
 */
class TimeCorrection extends CommonObject
{
	/** @var string */
	public $module = 'anxhr';
	/** @var string */
	public $element = 'anxhr_time_correction';
	/** @var string */
	public $TRIGGER_PREFIX = 'ANXHR_TIMECORRECTION';
	/** @var string */
	public $table_element = 'anxhr_time_correction';
	/** @var string */
	public $picto = 'fa-user-edit';
	/** @var int */
	public $isextrafieldmanaged = 0;
	/** @var int|string */
	public $ismultientitymanaged = 1;

	const STATUS_REQUESTED = 0;
	const STATUS_APPROVED = 1;
	const STATUS_REFUSED = 2;
	const STATUS_CANCELED = 9;

	/**
	 * @var array<string,array<string,mixed>>
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'default' => '1', 'enabled' => 1, 'visible' => 0, 'notnull' => 1, 'position' => 5, 'index' => 1),
		'fk_user' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'Employee', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'foreignkey' => 'user.rowid'),
		'day' => array('type' => 'date', 'label' => 'Date', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'old_json' => array('type' => 'text', 'label' => 'AnxhrOldEntries', 'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => 0),
		'new_json' => array('type' => 'text', 'label' => 'AnxhrNewEntries', 'enabled' => 1, 'position' => 31, 'notnull' => 0, 'visible' => 0),
		'reason' => array('type' => 'text', 'label' => 'AnxhrReason', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1),
		'fk_user_approve' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'AnxhrApprover', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1),
		'date_approve' => array('type' => 'datetime', 'label' => 'AnxhrDateApprove', 'enabled' => 1, 'position' => 51, 'notnull' => 0, 'visible' => 1),
		'approve_comment' => array('type' => 'text', 'label' => 'AnxhrApproveComment', 'enabled' => 1, 'position' => 52, 'notnull' => 0, 'visible' => 1),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 1, 'visible' => -2, 'foreignkey' => 'user.rowid'),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => -1, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => -1, 'visible' => -2),
		'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => 1, 'position' => 2000, 'notnull' => 1, 'visible' => 1, 'default' => '0', 'index' => 1, 'arrayofkeyval' => array(0 => 'AnxhrCorrectionRequested', 1 => 'AnxhrCorrectionApproved', 2 => 'AnxhrCorrectionRefused', 9 => 'Canceled')),
	);

	/** @var int */
	public $fk_user;
	/** @var int|string	Timestamp of day */
	public $day;
	/** @var string|null */
	public $old_json;
	/** @var string|null */
	public $new_json;
	/** @var string|null */
	public $reason;
	/** @var int|null */
	public $fk_user_approve;
	/** @var int|string|null */
	public $date_approve;
	/** @var string|null */
	public $approve_comment;
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
	 * Create a correction request. old_json is filled with the current entries of the day when empty.
	 *
	 * @param	User	$user		User
	 * @param	int		$notrigger	1=no trigger
	 * @return	int					<0 if KO, id if OK
	 */
	public function create(User $user, $notrigger = 0)
	{
		$day = $this->getDayString();
		if ($day === '' || (int) $this->fk_user <= 0) {
			$this->error = 'ErrorBadParameters';
			return -1;
		}
		$new = $this->getNewEntries();
		if (empty($new)) {
			$this->error = 'AnxhrErrorNoEntries';
			return -1;
		}
		$this->new_json = json_encode($new);
		if (empty($this->old_json)) {
			$entry = new TimeEntry($this->db);
			$old = $entry->fetchForUserDay((int) $this->fk_user, $day);
			$this->old_json = json_encode(is_array($old) ? $old : array());
		}
		$this->status = self::STATUS_REQUESTED;

		$this->db->begin();
		$res = $this->createCommon($user, 1);
		if ($res > 0 && !$notrigger && $this->call_trigger('ANXHR_TIMECORRECTION_CREATE', $user) < 0) {
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
	 * Delete (only requested or canceled corrections)
	 *
	 * @param	User	$user		User
	 * @param	int		$notrigger	1=no trigger
	 * @return	int					<0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = 0)
	{
		if ((int) $this->status === self::STATUS_APPROVED) {
			$this->error = 'AnxhrErrorCorrectionAlreadyApproved';
			return -1;
		}
		$this->db->begin();
		$res = 1;
		if (!$notrigger && $this->call_trigger('ANXHR_TIMECORRECTION_DELETE', $user) < 0) {
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
	 * Approve the request: replace the entries listed in old_json by the new entries and recompute the day.
	 * Refused when the active entries of the day changed since the request (AnxhrErrorDayChangedSinceRequest).
	 *
	 * @param	User	$approver		Approver
	 * @param	string	$comment		Comment
	 * @param	int		$selfsameday	1=self correction of the current day by the employee, allowed without
	 *									approval when ANXHR_CLOCK_ALLOW_SELF_CORRECTION_SAME_DAY is set
	 * @return	int						<0 if KO, >0 if OK
	 */
	public function approve(User $approver, $comment = '', $selfsameday = 0)
	{
		if ((int) $this->status !== self::STATUS_REQUESTED) {
			$this->error = 'ErrorBadStatus';
			return -1;
		}
		$day = $this->getDayString();
		$allowed = TimeEntry::userCanApproveFor($approver, (int) $this->fk_user);
		if (!$allowed && $selfsameday && getDolGlobalInt('ANXHR_CLOCK_ALLOW_SELF_CORRECTION_SAME_DAY')
			&& (int) $approver->id === (int) $this->fk_user && $day === TimeEntry::timestampToDay(dol_now())) {
			$allowed = true;
		}
		if (!$allowed) {
			$this->error = 'NotEnoughPermissions';
			return -1;
		}
		$tday = new TimeDay($this->db);
		$row = $tday->fetchRowForUserDay((int) $this->fk_user, $day);
		if (is_array($row) && !empty($row['locked'])) {
			$this->error = 'AnxhrErrorDayLocked';
			return -1;
		}

		// The request replaces exactly the entries it was based on (old_json). If the active entries of the
		// day changed in the meantime (clocking, other correction), the request is outdated and refused.
		$entry = new TimeEntry($this->db);
		$current = $entry->fetchForUserDay((int) $this->fk_user, $day);
		if (!is_array($current)) {
			$this->error = $entry->error;
			return -1;
		}
		$ids = array();
		foreach ($current as $c) {
			$ids[] = (int) $c['id'];
		}
		$oldIds = array();
		foreach ($this->getOldEntries() as $o) {
			if (is_array($o) && isset($o['id'])) {
				$oldIds[] = (int) $o['id'];
			}
		}
		sort($ids);
		sort($oldIds);
		if ($ids !== $oldIds) {
			$this->error = 'AnxhrErrorDayChangedSinceRequest';
			return -1;
		}

		$this->db->begin();
		$error = 0;

		// Mark the replaced entries as replaced (kept as history).
		if (!empty($ids)) {
			$sql = "UPDATE ".$this->db->prefix()."anxhr_time_entry SET status = ".TimeEntry::STATUS_REPLACED.", fk_user_modif = ".((int) $approver->id);
			$sql .= " WHERE rowid IN (".$this->db->sanitize(implode(',', $ids)).")";
			if (!$this->db->query($sql)) {
				$error++;
				$this->error = $this->db->lasterror();
			}
		}

		// Insert new entries.
		foreach ($this->getNewEntries() as $n) {
			if ($error) {
				break;
			}
			$ts = self::stringToTimestamp($n['time']);
			if ($ts === null) {
				continue;
			}
			$new = new TimeEntry($this->db);
			$new->fk_user = (int) $this->fk_user;
			$new->entry_type = $n['type'];
			$new->entry_datetime = $ts;
			$new->homeoffice = empty($n['homeoffice']) ? 0 : 1;
			$new->source = 'manual';
			$new->fk_correction = (int) $this->id;
			$new->status = TimeEntry::STATUS_ACTIVE;
			if ($new->create($approver) < 0) {
				$error++;
				$this->error = $new->error;
				$this->errors = array_merge($this->errors, $new->errors);
			}
		}

		if (!$error) {
			$this->status = self::STATUS_APPROVED;
			$this->fk_user_approve = (int) $approver->id;
			$this->date_approve = dol_now();
			$this->approve_comment = (string) $comment;
			if ($this->updateCommon($approver, 1) < 0) {
				$error++;
			}
		}
		if (!$error && $this->call_trigger('ANXHR_TIMECORRECTION_APPROVE', $approver) < 0) {
			$error++;
		}
		if ($error) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();

		// Recompute the day (and the next one, rest time check depends on it).
		$tday->recomputeForUserDay($approver, (int) $this->fk_user, $day);
		$next = TimeEntry::timestampToDay(TimeEntry::dayToTimestamp($day) + 26 * 3600);
		$tday->recomputeForUserDay($approver, (int) $this->fk_user, $next);
		return 1;
	}

	/**
	 * Refuse the request.
	 *
	 * @param	User	$approver	Approver
	 * @param	string	$comment	Comment (reason)
	 * @return	int					<0 if KO, >0 if OK
	 */
	public function refuse(User $approver, $comment = '')
	{
		if ((int) $this->status !== self::STATUS_REQUESTED) {
			$this->error = 'ErrorBadStatus';
			return -1;
		}
		if (!TimeEntry::userCanApproveFor($approver, (int) $this->fk_user)) {
			$this->error = 'NotEnoughPermissions';
			return -1;
		}
		$this->status = self::STATUS_REFUSED;
		$this->fk_user_approve = (int) $approver->id;
		$this->date_approve = dol_now();
		$this->approve_comment = (string) $comment;
		return $this->updateWithTrigger($approver, 'ANXHR_TIMECORRECTION_REFUSE');
	}

	/**
	 * Cancel the request (by the employee).
	 *
	 * @param	User	$user	User
	 * @return	int				<0 if KO, >0 if OK
	 */
	public function cancel(User $user)
	{
		if ((int) $this->status !== self::STATUS_REQUESTED) {
			$this->error = 'ErrorBadStatus';
			return -1;
		}
		if ((int) $user->id !== (int) $this->fk_user && !$user->hasRight('anxhr', 'time', 'admin')) {
			$this->error = 'NotEnoughPermissions';
			return -1;
		}
		$this->status = self::STATUS_CANCELED;
		return $this->updateWithTrigger($user, 'ANXHR_TIMECORRECTION_CANCEL');
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
	 * Return the requested new entries (validated, sorted).
	 *
	 * @return array<int,array{type:string,time:string,homeoffice:int}>
	 */
	public function getNewEntries()
	{
		$raw = empty($this->new_json) ? array() : json_decode((string) $this->new_json, true);
		$res = array();
		if (!is_array($raw)) {
			return $res;
		}
		foreach ($raw as $r) {
			if (!is_array($r) || empty($r['type']) || empty($r['time'])) {
				continue;
			}
			if (!in_array($r['type'], TimeEntry::$types, true) || self::stringToTimestamp((string) $r['time']) === null) {
				continue;
			}
			$res[] = array('type' => (string) $r['type'], 'time' => (string) $r['time'], 'homeoffice' => empty($r['homeoffice']) ? 0 : 1);
		}
		usort($res, function ($a, $b) {
			return strcmp($a['time'], $b['time']);
		});
		return $res;
	}

	/**
	 * Return the original entries stored at request time.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function getOldEntries()
	{
		$raw = empty($this->old_json) ? array() : json_decode((string) $this->old_json, true);
		return is_array($raw) ? $raw : array();
	}

	/**
	 * Return the day as 'Y-m-d'.
	 *
	 * @return string
	 */
	public function getDayString()
	{
		if (is_numeric($this->day) && (int) $this->day > 0) {
			// DATE column: written and read by core in the server zone.
			return dol_print_date((int) $this->day, '%Y-%m-%d', 'tzserver');
		}
		if (is_string($this->day) && preg_match('/^\d{4}-\d{2}-\d{2}/', $this->day)) {
			return substr($this->day, 0, 10);
		}
		return '';
	}

	/**
	 * Convert 'Y-m-d H:i[:s]' (company time zone ANXHR_TIMEZONE) into a timestamp.
	 *
	 * @param	string	$str	Date time
	 * @return	int|null
	 */
	public static function stringToTimestamp($str)
	{
		if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim((string) $str), $m)) {
			return null;
		}
		if ((int) $m[4] > 23 || (int) $m[5] > 59) {
			return null;
		}
		return anxhrTzToTs(trim((string) $str));
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
			self::STATUS_REQUESTED => array('AnxhrCorrectionRequested', 'status1'),
			self::STATUS_APPROVED => array('AnxhrCorrectionApproved', 'status4'),
			self::STATUS_REFUSED => array('AnxhrCorrectionRefused', 'status8'),
			self::STATUS_CANCELED => array('Canceled', 'status9'),
		);
		$status = (int) $status;
		if (!isset($map[$status])) {
			return '';
		}
		$label = $langs->transnoentitiesnoconv($map[$status][0]);
		return dolGetStatus($label, $label, '', $map[$status][1], $mode);
	}
}
