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
 * \file        htdocs/custom/anxhr/class/hrcontract.class.php
 * \ingroup     anxhr
 * \brief       CRUD class for HrContract (employment contract, historised)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once __DIR__.'/../lib/anxhr_hr.lib.php';

/**
 * Class for HrContract
 */
class HrContract extends CommonObject
{
	/**
	 * @var string ID of module
	 */
	public $module = 'anxhr';

	/**
	 * @var string ID to identify managed object
	 */
	public $element = 'anxhr_contract';

	/**
	 * @var string Prefix for trigger codes
	 */
	public $TRIGGER_PREFIX = 'ANXHR_CONTRACT';

	/**
	 * @var string Name of table without prefix where object is stored
	 */
	public $table_element = 'anxhr_contract';

	/**
	 * @var string Picto
	 */
	public $picto = 'fa-file-signature';

	/**
	 * @var int<0,1> Does object support extrafields
	 */
	public $isextrafieldmanaged = 0;

	/**
	 * @var int<0,1>|string Multicompany support
	 */
	public $ismultientitymanaged = 1;

	/**
	 * @var string[] Child tables deleted on cascade (deadlines of this contract)
	 */
	protected $childtablesoncascade = array('anxhr_deadline');

	/**
	 * @var string Foreign key field of child tables
	 */
	public $fk_element = 'fk_contract';

	const STATUS_DRAFT = 0;
	const STATUS_ACTIVE = 1;
	const STATUS_ENDED = 9;

	/**
	 * @var array<string,array<string,mixed>> Fields definition (see modulebuilder template for syntax)
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'default' => '1', 'enabled' => 1, 'visible' => 0, 'notnull' => 1, 'position' => 5, 'index' => 1),
		'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 4, 'noteditable' => 1, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'csslist' => 'nowraponall'),
		'fk_user' => array('type' => 'integer:User:user/class/user.class.php:0:(t.employee:=:1)', 'label' => 'AnxhrEmployee', 'picto' => 'user', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'foreignkey' => 'user.rowid', 'css' => 'minwidth300 maxwidth500', 'csslist' => 'tdoverflowmax150'),
		'employment_type' => array('type' => 'varchar(32)', 'label' => 'AnxhrEmploymentType', 'enabled' => 1, 'position' => 25, 'notnull' => 0, 'visible' => 1, 'arrayofkeyval' => array('fulltime' => 'AnxhrEmploymentFulltime', 'parttime' => 'AnxhrEmploymentParttime', 'marginal' => 'AnxhrEmploymentMarginal', 'apprentice' => 'AnxhrEmploymentApprentice', 'freelancer' => 'AnxhrEmploymentFreelancer'), 'css' => 'minwidth200'),
		'date_start' => array('type' => 'date', 'label' => 'AnxhrContractDateStart', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'help' => 'AnxhrContractDateStartHelp'),
		'date_end' => array('type' => 'date', 'label' => 'AnxhrContractDateEnd', 'enabled' => 1, 'position' => 31, 'notnull' => 0, 'visible' => 1, 'index' => 1, 'help' => 'AnxhrContractDateEndHelp'),
		'probation_end' => array('type' => 'date', 'label' => 'AnxhrProbationEnd', 'enabled' => 1, 'position' => 32, 'notnull' => 0, 'visible' => -1, 'help' => 'AnxhrProbationEndHelp'),
		'fixed_term_end' => array('type' => 'date', 'label' => 'AnxhrFixedTermEnd', 'enabled' => 1, 'position' => 33, 'notnull' => 0, 'visible' => -1, 'help' => 'AnxhrFixedTermEndHelp'),
		'notice_period_weeks' => array('type' => 'integer', 'label' => 'AnxhrNoticePeriodWeeks', 'enabled' => 1, 'position' => 34, 'notnull' => 0, 'visible' => -1, 'css' => 'maxwidth75'),
		'weekly_hours' => array('type' => 'double(8,2)', 'label' => 'AnxhrWeeklyHours', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1, 'css' => 'maxwidth75', 'help' => 'AnxhrWeeklyHoursHelp'),
		'fk_timemodel' => array('type' => 'sellist:anxhr_timemodel:label:rowid::(status:=:1)', 'label' => 'AnxhrTimeModel', 'enabled' => 1, 'position' => 41, 'notnull' => 0, 'visible' => -1, 'css' => 'minwidth200 maxwidth300', 'help' => 'AnxhrTimeModelHelp'),
		'kv_code' => array('type' => 'varchar(32)', 'label' => 'AnxhrKvCode', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => -1, 'css' => 'maxwidth150', 'help' => 'AnxhrKvCodeHelp'),
		'kv_group' => array('type' => 'varchar(64)', 'label' => 'AnxhrKvGroup', 'enabled' => 1, 'position' => 51, 'notnull' => 0, 'visible' => -1, 'css' => 'maxwidth200', 'help' => 'AnxhrKvGroupHelp'),
		'kv_level' => array('type' => 'varchar(32)', 'label' => 'AnxhrKvLevel', 'enabled' => 1, 'position' => 52, 'notnull' => 0, 'visible' => -1, 'css' => 'maxwidth100', 'help' => 'AnxhrKvLevelHelp'),
		'kv_level_date' => array('type' => 'date', 'label' => 'AnxhrKvLevelDate', 'enabled' => 1, 'position' => 53, 'notnull' => 0, 'visible' => -1, 'help' => 'AnxhrKvLevelDateHelp'),
		'homeoffice_days_week' => array('type' => 'double(4,2)', 'label' => 'AnxhrHomeofficeDaysWeek', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => -1, 'css' => 'maxwidth75', 'help' => 'AnxhrHomeofficeDaysWeekHelp'),
		'homeoffice_agreement_date' => array('type' => 'date', 'label' => 'AnxhrHomeofficeAgreementDate', 'enabled' => 1, 'position' => 61, 'notnull' => 0, 'visible' => -1),
		'vacation_days_year' => array('type' => 'double(6,2)', 'label' => 'AnxhrVacationDaysYear', 'enabled' => 1, 'position' => 70, 'notnull' => 0, 'visible' => -1, 'default' => '25', 'css' => 'maxwidth75', 'help' => 'AnxhrVacationDaysYearHelp'),
		'vacation_in_hours' => array('type' => 'boolean', 'label' => 'AnxhrVacationInHours', 'enabled' => 1, 'position' => 71, 'notnull' => 0, 'visible' => -1, 'default' => '0', 'help' => 'AnxhrVacationInHoursHelp'),
		'prior_service_months' => array('type' => 'integer', 'label' => 'AnxhrPriorServiceMonths', 'enabled' => 1, 'position' => 72, 'notnull' => 0, 'visible' => -1, 'default' => '0', 'css' => 'maxwidth75', 'help' => 'AnxhrPriorServiceMonthsHelp'),
		'disability_status' => array('type' => 'varchar(255)', 'label' => 'AnxhrDisabilityStatus', 'enabled' => 1, 'position' => 80, 'notnull' => 0, 'visible' => 3, 'css' => 'minwidth300', 'help' => 'AnxhrDisabilityStatusHelp'),
		'note_private' => array('type' => 'text', 'label' => 'NotePrivate', 'enabled' => 1, 'position' => 90, 'notnull' => 0, 'visible' => 3, 'cssview' => 'wordbreak'),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'picto' => 'user', 'enabled' => 1, 'position' => 510, 'notnull' => 1, 'visible' => -2, 'csslist' => 'tdoverflowmax150'),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'picto' => 'user', 'enabled' => 1, 'position' => 511, 'notnull' => -1, 'visible' => -2, 'csslist' => 'tdoverflowmax150'),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => -1, 'visible' => -2),
		'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => 1, 'position' => 2000, 'notnull' => 1, 'default' => '0', 'visible' => 1, 'index' => 1, 'arrayofkeyval' => array(0 => 'Draft', 1 => 'AnxhrStatusActive', 9 => 'AnxhrStatusEnded')),
	);

	/** @var string */
	public $ref;
	/** @var int */
	public $fk_user;
	/** @var string */
	public $employment_type;
	/** @var int|string */
	public $date_start;
	/** @var int|string */
	public $date_end;
	/** @var int|string */
	public $probation_end;
	/** @var int|string */
	public $fixed_term_end;
	/** @var ?int */
	public $notice_period_weeks;
	/** @var ?float */
	public $weekly_hours;
	/** @var ?int */
	public $fk_timemodel;
	/** @var string */
	public $kv_code;
	/** @var string */
	public $kv_group;
	/** @var string */
	public $kv_level;
	/** @var int|string */
	public $kv_level_date;
	/** @var ?float */
	public $homeoffice_days_week;
	/** @var int|string */
	public $homeoffice_agreement_date;
	/** @var ?float */
	public $vacation_days_year;
	/** @var int */
	public $vacation_in_hours;
	/** @var ?int */
	public $prior_service_months;
	/** @var string Clear value (only loaded when current user has contract write right) */
	public $disability_status;
	/** @var string */
	public $note_private;
	/** @var int */
	public $status;
	/** @var int */
	public $fk_user_creat;
	/** @var ?int */
	public $fk_user_modif;
	/** @var string */
	public $import_key;

	/**
	 * @var bool True if the sensitive field disability_status is managed for current user
	 */
	protected $sensitiveAllowed = false;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		global $langs, $user;

		$this->db = $db;

		if (!isModEnabled('multicompany')) {
			$this->fields['entity']['enabled'] = 0;
		}

		// Sensitive field (art. 9 GDPR): only managed for users with contract write right.
		// If not allowed, the field is removed so it is never selected, shown nor overwritten.
		$this->sensitiveAllowed = (is_object($user) && !empty($user->id) && $user->hasRight('anxhr', 'contract', 'write'));
		if (!$this->sensitiveAllowed) {
			unset($this->fields['disability_status']);
		}

		foreach ($this->fields as $key => $val) {
			if (isset($val['enabled']) && empty($val['enabled'])) {
				unset($this->fields[$key]);
			}
		}

		if (is_object($langs)) {
			foreach ($this->fields as $key => $val) {
				if (!empty($val['arrayofkeyval']) && is_array($val['arrayofkeyval'])) {
					foreach ($val['arrayofkeyval'] as $key2 => $val2) {
						$this->fields[$key]['arrayofkeyval'][$key2] = $langs->trans($val2);
					}
				}
			}
		}
	}

	/**
	 * Create object into database.
	 * Side effects when status is active: older active contracts of same employee are ended,
	 * deadlines are generated, onboarding checklist is instantiated (first contract only) and user fields are synced.
	 *
	 * @param  User     $user      User that creates
	 * @param  int<0,1> $notrigger 0=launch triggers, 1=disable triggers
	 * @return int<-1,max>         Id of created object if OK, <0 if KO
	 */
	public function create(User $user, $notrigger = 0)
	{
		$error = 0;

		if (empty($this->fk_user) || $this->fk_user <= 0) {
			$this->error = 'ErrorFieldRequired';
			$this->errors[] = $this->error;
			return -1;
		}
		if (empty($this->ref) || preg_match('/^\(?PROV/i', $this->ref)) {
			$this->ref = anxhrGetNextUserRef($this->db, $this->table_element, 'CT', (int) $this->fk_user);
		}
		if ($this->status === null || $this->status === '') {
			$this->status = self::STATUS_DRAFT;
		}
		$this->vacation_in_hours = empty($this->vacation_in_hours) ? 0 : 1;
		// Apply defaults of fields not provided (explicit NULL would override the database default)
		foreach (array('vacation_days_year', 'prior_service_months') as $key) {
			if (($this->$key === null || $this->$key === '') && isset($this->fields[$key]['default'])) {
				$this->$key = $this->fields[$key]['default'];
			}
		}
		$isfirstcontract = ($this->countContractsForUser((int) $this->fk_user) == 0);

		$this->db->begin();

		$plain = $this->disability_status;
		if (!$this->encryptSensitive()) {
			$this->disability_status = $plain;
			$this->db->rollback();
			return -1;
		}
		$result = $this->createCommon($user, 1);
		$this->disability_status = $plain;
		if ($result <= 0) {
			$error++;
		}

		if (!$error && !$notrigger) {
			if ($this->call_trigger('ANXHR_CONTRACT_CREATE', $user) < 0) {
				$error++;
			}
		}

		if (!$error && $this->status == self::STATUS_ACTIVE) {
			if ($this->applyActiveSideEffects($user, $isfirstcontract) < 0) {
				$error++;
			}
		}

		if ($error) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();
		return $this->id;
	}

	/**
	 * Load object in memory from the database
	 *
	 * @param  int    $id  Id object
	 * @param  string $ref Ref
	 * @return int<-1,1>   <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id, $ref = null)
	{
		$result = $this->fetchCommon($id, $ref);
		if ($result > 0 && $this->sensitiveAllowed) {
			$this->disability_status = self::decryptSensitiveValue((string) $this->disability_status);
		}
		return $result;
	}

	/**
	 * Fetch the active contract of a user at a given date
	 *
	 * @param  int      $userid Id of employee
	 * @param  int|null $date   Timestamp of reference date (null = now)
	 * @return int<-1,1>        1 if found, 0 if none, -1 on error
	 */
	public function fetchActiveForUser($userid, $date = null)
	{
		if ($date === null) {
			$date = dol_now();
		}
		$day = dol_print_date($date, '%Y-%m-%d');

		$sql = "SELECT t.rowid FROM ".$this->db->prefix().$this->table_element." as t";
		$sql .= " WHERE t.fk_user = ".((int) $userid);
		$sql .= " AND t.entity IN (".getEntity($this->element).")";
		$sql .= " AND t.status = ".self::STATUS_ACTIVE;
		$sql .= " AND t.date_start <= '".$this->db->escape($day)."'";
		$sql .= " AND (t.date_end IS NULL OR t.date_end >= '".$this->db->escape($day)."')";
		$sql .= " ORDER BY t.date_start DESC";
		$sql .= $this->db->plimit(1);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		if (!$obj) {
			return 0;
		}
		return ($this->fetch((int) $obj->rowid) > 0 ? 1 : -1);
	}

	/**
	 * Update object into database
	 *
	 * @param  User     $user      User that modifies
	 * @param  int<0,1> $notrigger 0=launch triggers, 1=disable triggers
	 * @return int<-1,1>           <0 if KO, >0 if OK
	 */
	public function update(User $user, $notrigger = 0)
	{
		$error = 0;
		$this->vacation_in_hours = empty($this->vacation_in_hours) ? 0 : 1;

		$this->db->begin();

		$plain = $this->disability_status;
		if (!$this->encryptSensitive()) {
			$this->disability_status = $plain;
			$this->db->rollback();
			return -1;
		}
		$result = $this->updateCommon($user, 1);
		$this->disability_status = $plain;
		if ($result <= 0) {
			$error++;
		}

		if (!$error && !$notrigger) {
			if ($this->call_trigger('ANXHR_CONTRACT_MODIFY', $user) < 0) {
				$error++;
			}
		}

		if (!$error && $this->status == self::STATUS_ACTIVE) {
			if ($this->applyActiveSideEffects($user, false) < 0) {
				$error++;
			}
		}

		if ($error) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();
		return 1;
	}

	/**
	 * Delete object (and its deadlines) in database
	 *
	 * @param  User     $user      User that deletes
	 * @param  int<0,1> $notrigger 0=launch triggers, 1=disable triggers
	 * @return int<-1,1>           <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = 0)
	{
		if (!$notrigger) {
			if ($this->call_trigger('ANXHR_CONTRACT_DELETE', $user) < 0) {
				return -1;
			}
		}
		return $this->deleteCommon($user, 1);
	}

	/**
	 * Activate a draft contract
	 *
	 * @param  User $user User that activates
	 * @return int<-1,1>  <0 if KO, 0 if nothing done, >0 if OK
	 */
	public function activate(User $user)
	{
		if ($this->status == self::STATUS_ACTIVE) {
			return 0;
		}
		$isfirst = ($this->countContractsForUser((int) $this->fk_user, (int) $this->id) == 0);
		$this->status = self::STATUS_ACTIVE;
		$result = $this->update($user);
		if ($result > 0 && $isfirst && !getDolGlobalInt('ANXHR_DISABLE_AUTO_ONBOARDING')) {
			anxhrInstantiateOnboarding($this, $user);
		}
		return $result;
	}

	/**
	 * Set contract as ended
	 *
	 * @param  User     $user    User that ends the contract
	 * @param  int|null $dateEnd End date (timestamp), keep current end date if null
	 * @return int<-1,1>         <0 if KO, >0 if OK
	 */
	public function close(User $user, $dateEnd = null)
	{
		if (!empty($dateEnd)) {
			$this->date_end = $dateEnd;
		}
		$this->status = self::STATUS_ENDED;
		return $this->update($user);
	}

	/**
	 * Run the side effects of an active contract (in current transaction)
	 *
	 * @param  User $user            User acting
	 * @param  bool $isfirstcontract True if this is the first contract of the employee
	 * @return int                   <0 if KO, >=0 if OK
	 */
	protected function applyActiveSideEffects(User $user, $isfirstcontract)
	{
		if ($this->endOtherActiveContracts($user) < 0) {
			return -1;
		}
		if (anxhrGenerateDeadlinesForContract($this, $user) < 0) {
			return -1;
		}
		if ($isfirstcontract && !getDolGlobalInt('ANXHR_DISABLE_AUTO_ONBOARDING')) {
			if (anxhrInstantiateOnboarding($this, $user) < 0) {
				return -1;
			}
		}
		// Sync the user card when this is the most recent active contract, so a future start date
		// of a new hire is visible on the user card too.
		if ($this->isLatestActive()) {
			if ($this->setUserDenormalizedFields($user) < 0) {
				return -1;
			}
		}
		return 1;
	}

	/**
	 * Return true if no other active contract of the same employee starts later than this one
	 *
	 * @return bool
	 */
	public function isLatestActive()
	{
		if (empty($this->date_start) || empty($this->fk_user)) {
			return false;
		}
		$sql = "SELECT COUNT(t.rowid) as nb FROM ".$this->db->prefix().$this->table_element." as t";
		$sql .= " WHERE t.fk_user = ".((int) $this->fk_user);
		$sql .= " AND t.entity IN (".getEntity($this->element).")";
		$sql .= " AND t.status = ".self::STATUS_ACTIVE;
		$sql .= " AND t.rowid <> ".((int) $this->id);
		$sql .= " AND t.date_start > '".$this->db->idate($this->date_start)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return false;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		return ((int) $obj->nb == 0);
	}

	/**
	 * Return true if contract is valid today (started and not ended)
	 *
	 * @return bool
	 */
	public function isCurrent()
	{
		$now = dol_now();
		if (empty($this->date_start) || $this->date_start > $now) {
			return false;
		}
		return (empty($this->date_end) || $this->date_end >= dol_time_plus_duree($now, -1, 'd'));
	}

	/**
	 * End other active contracts of same employee that started before this one.
	 * They get date_end = this date_start - 1 day and status ended. This is logged explicitly
	 * and the list of ended refs is stored in $this->context['anxhr_ended_contracts'] for display.
	 *
	 * @param  User $user User acting
	 * @return int        Number of contracts ended, <0 if KO
	 */
	public function endOtherActiveContracts(User $user)
	{
		if (empty($this->date_start) || empty($this->fk_user)) {
			return 0;
		}
		$sql = "SELECT t.rowid, t.ref FROM ".$this->db->prefix().$this->table_element." as t";
		$sql .= " WHERE t.fk_user = ".((int) $this->fk_user);
		$sql .= " AND t.entity IN (".getEntity($this->element).")";
		$sql .= " AND t.status = ".self::STATUS_ACTIVE;
		$sql .= " AND t.rowid <> ".((int) $this->id);
		$sql .= " AND t.date_start < '".$this->db->idate($this->date_start)."'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$ids = array();
		$refs = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$ids[] = (int) $obj->rowid;
			$refs[] = $obj->ref;
		}
		$this->db->free($resql);
		if (empty($ids)) {
			return 0;
		}

		$newend = dol_time_plus_duree($this->date_start, -1, 'd');
		$sql = "UPDATE ".$this->db->prefix().$this->table_element;
		$sql .= " SET date_end = '".$this->db->idate($newend)."', status = ".self::STATUS_ENDED.", fk_user_modif = ".((int) $user->id);
		$sql .= " WHERE rowid IN (".$this->db->sanitize(implode(',', $ids)).")";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		dol_syslog(__METHOD__." contract ".$this->ref." (user ".$this->fk_user.") ended previous active contracts ".implode(',', $refs)." with date_end=".dol_print_date($newend, 'day'), LOG_NOTICE);
		$this->context['anxhr_ended_contracts'] = $refs;
		return count($ids);
	}

	/**
	 * Write denormalized values into llx_user (weeklyhours, dateemployment, dateemploymentend)
	 *
	 * @param  User $user User acting
	 * @return int        <0 if KO, 0 if nothing to do, >0 if OK
	 */
	public function setUserDenormalizedFields(User $user)
	{
		$employee = new User($this->db);
		if ($employee->fetch((int) $this->fk_user) <= 0) {
			$this->error = 'ErrorUserNotFound';
			return -1;
		}
		$employee->weeklyhours = ($this->weekly_hours !== null && $this->weekly_hours !== '') ? $this->weekly_hours : '';
		$employee->dateemployment = $this->date_start;
		$employee->dateemploymentend = $this->date_end;
		$employee->context['anxhr_sync_from_contract'] = $this->id;
		$result = $employee->update($user, 1, 1, 1, 1);
		if ($result < 0) {
			$this->error = $employee->error;
			$this->errors = array_merge($this->errors, $employee->errors);
			dol_syslog(__METHOD__." failed to sync user ".$employee->id.": ".$employee->error, LOG_ERR);
			return -1;
		}
		return 1;
	}

	/**
	 * Return the clear disability status if the given user is allowed to see it
	 *
	 * @param  User   $user User asking
	 * @return string       Clear value or '' if not allowed / empty
	 */
	public function getDisabilityStatus(User $user)
	{
		if (!$user->hasRight('anxhr', 'contract', 'write') || empty($this->id)) {
			return '';
		}
		$sql = "SELECT disability_status FROM ".$this->db->prefix().$this->table_element." WHERE rowid = ".((int) $this->id);
		$resql = $this->db->query($sql);
		if (!$resql) {
			return '';
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		return ($obj ? self::decryptSensitiveValue((string) $obj->disability_status) : '');
	}

	/**
	 * Encrypt the sensitive property before saving
	 *
	 * @return bool False if the encrypted value does not fit into the database column
	 */
	protected function encryptSensitive()
	{
		global $langs;

		if (isset($this->fields['disability_status'])) {
			$this->disability_status = self::encryptSensitiveValue((string) $this->disability_status);
			if (strlen($this->disability_status) > 255) {
				$this->error = $langs->trans('AnxhrDisabilityStatusTooLong');
				$this->errors[] = $this->error;
				return false;
			}
		}
		return true;
	}

	/**
	 * Encrypt a sensitive value with dolEncrypt(). The clear value is base64 encoded first because
	 * dolDecrypt() only accepts ASCII results (German texts contain umlauts).
	 *
	 * @param  string $value Clear value
	 * @return string        Encrypted value ('' if empty)
	 */
	public static function encryptSensitiveValue($value)
	{
		if ($value === '') {
			return '';
		}
		if (preg_match('/^dolcrypt:/', $value)) {
			return $value;	// Already encrypted
		}
		return dolEncrypt('b64:'.base64_encode($value));
	}

	/**
	 * Decrypt a value encrypted with encryptSensitiveValue()
	 *
	 * @param  string $value Encrypted value
	 * @return string        Clear value
	 */
	public static function decryptSensitiveValue($value)
	{
		if ($value === '') {
			return '';
		}
		$clear = dolDecrypt($value);
		if (preg_match('/^dolcrypt:/', $clear)) {
			dol_syslog(__METHOD__.' failed to decrypt sensitive value (wrong instance key ?)', LOG_WARNING);
			return '';
		}
		if (strpos($clear, 'b64:') === 0) {
			$decoded = base64_decode(substr($clear, 4), true);
			return ($decoded === false ? '' : $decoded);
		}
		return $clear;
	}

	/**
	 * Count contracts of an employee
	 *
	 * @param  int $fk_user   Employee id
	 * @param  int $excludeid Contract id to exclude
	 * @return int            Number of contracts
	 */
	public function countContractsForUser($fk_user, $excludeid = 0)
	{
		$sql = "SELECT COUNT(rowid) as nb FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE fk_user = ".((int) $fk_user)." AND entity IN (".getEntity($this->element).")";
		if ($excludeid > 0) {
			$sql .= " AND rowid <> ".((int) $excludeid);
		}
		$resql = $this->db->query($sql);
		if (!$resql) {
			return 0;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		return (int) $obj->nb;
	}

	/**
	 * Tooltip content
	 *
	 * @param  array<string,string> $params Params
	 * @return array<string,string>
	 */
	public function getTooltipContentArray($params)
	{
		global $langs;

		$langs->loadLangs(array('anxhr@anxhr', 'anxhr_hr@anxhr'));
		$datas = array();
		$datas['picto'] = img_picto('', $this->picto).' <u>'.$langs->trans("AnxhrContract").'</u>';
		if (isset($this->status)) {
			$datas['picto'] .= ' '.$this->getLibStatut(5);
		}
		$datas['ref'] = '<br><b>'.$langs->trans('Ref').':</b> '.dol_escape_htmltag($this->ref);
		if (!empty($this->date_start)) {
			$datas['period'] = '<br><b>'.$langs->trans('AnxhrPeriod').':</b> '.dol_print_date($this->date_start, 'day').' - '.(empty($this->date_end) ? '...' : dol_print_date($this->date_end, 'day'));
		}
		if ($this->weekly_hours !== null && $this->weekly_hours !== '') {
			$datas['hours'] = '<br><b>'.$langs->trans('AnxhrWeeklyHours').':</b> '.price($this->weekly_hours, 0, $langs, 0, -1, 2);
		}
		return $datas;
	}

	/**
	 * Return a link to the object card (with optionally the picto)
	 *
	 * @param  int    $withpicto             0=No picto, 1=Include picto into link, 2=Only picto
	 * @param  string $option                'nolink' or ''
	 * @param  int    $notooltip             1=Disable tooltip
	 * @param  string $morecss               More css on link
	 * @param  int    $save_lastsearch_value -1=Auto, 0=No, 1=Yes
	 * @return string                        HTML link
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
	{
		return anxhrBuildNomUrl($this, '/anxhr/contract_card.php', $this->ref, $withpicto, $option, $notooltip, $morecss, $save_lastsearch_value);
	}

	/**
	 * Return label of status
	 *
	 * @param  int<0,6> $mode Display mode
	 * @return string
	 */
	public function getLibStatut($mode = 0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	/**
	 * Return label of status
	 *
	 * @param  int<0,6> $mode Display mode
	 * @return string
	 */
	public function getLabelStatus($mode = 0)
	{
		return $this->LibStatut($this->status, $mode);
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 * Return the label of a given status
	 *
	 * @param  int      $status Status
	 * @param  int<0,6> $mode   Display mode
	 * @return string
	 */
	public function LibStatut($status, $mode = 0)
	{
		// phpcs:enable
		global $langs;

		if (is_null($status) || $status === '') {
			return '';
		}
		$langs->load('anxhr_hr@anxhr');
		$labels = array(
			self::STATUS_DRAFT => $langs->transnoentitiesnoconv('Draft'),
			self::STATUS_ACTIVE => $langs->transnoentitiesnoconv('AnxhrStatusActive'),
			self::STATUS_ENDED => $langs->transnoentitiesnoconv('AnxhrStatusEnded'),
		);
		$types = array(self::STATUS_DRAFT => 'status0', self::STATUS_ACTIVE => 'status4', self::STATUS_ENDED => 'status6');
		$label = isset($labels[$status]) ? $labels[$status] : (string) $status;
		$type = isset($types[$status]) ? $types[$status] : 'status0';

		return dolGetStatus($label, $label, '', $type, $mode, '', array('badgeParams' => array('attr' => array('data-status-element' => $this->element, 'data-status' => (int) $status))));
	}

	/**
	 * Load info (creation / modification) of object
	 *
	 * @param  int  $id Id of object
	 * @return void
	 */
	public function info($id)
	{
		anxhrLoadInfo($this, $id);
	}

	/**
	 * Initialise object with example values
	 *
	 * @return int
	 */
	public function initAsSpecimen()
	{
		$ret = $this->initAsSpecimenCommon();
		$this->ref = 'CT-1-1';
		$this->fk_user = 1;
		$this->date_start = dol_now();
		$this->weekly_hours = 38.5;
		$this->employment_type = 'fulltime';
		$this->vacation_days_year = 25;
		$this->status = self::STATUS_ACTIVE;
		return $ret;
	}
}
