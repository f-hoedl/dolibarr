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
 * \file        htdocs/custom/anxhr/class/hrdeadline.class.php
 * \ingroup     anxhr
 * \brief       CRUD class for HrDeadline (HR deadlines with reminders)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once __DIR__.'/../lib/anxhr_hr.lib.php';

/**
 * Class for HrDeadline
 */
class HrDeadline extends CommonObject
{
	/** @var string */
	public $module = 'anxhr';
	/** @var string */
	public $element = 'anxhr_deadline';
	/** @var string */
	public $TRIGGER_PREFIX = 'ANXHR_DEADLINE';
	/** @var string */
	public $table_element = 'anxhr_deadline';
	/** @var string */
	public $picto = 'fa-hourglass-half';
	/** @var int<0,1> */
	public $isextrafieldmanaged = 0;
	/** @var int<0,1>|string */
	public $ismultientitymanaged = 1;

	const STATUS_OPEN = 0;
	const STATUS_DONE = 1;
	const STATUS_CANCELED = 9;

	/** Number of days below which an open deadline is shown as urgent */
	const WARNING_DAYS = 7;

	/**
	 * @var array<string,array<string,mixed>> Fields definition
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'default' => '1', 'enabled' => 1, 'visible' => 0, 'notnull' => 1, 'position' => 5, 'index' => 1),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 10, 'notnull' => 0, 'visible' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'minwidth300', 'csslist' => 'tdoverflowmax200'),
		'fk_user' => array('type' => 'integer:User:user/class/user.class.php:0:(t.employee:=:1)', 'label' => 'AnxhrEmployee', 'picto' => 'user', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'foreignkey' => 'user.rowid', 'css' => 'minwidth300 maxwidth500', 'csslist' => 'tdoverflowmax150'),
		'type' => array('type' => 'varchar(32)', 'label' => 'Type', 'enabled' => 1, 'position' => 25, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'default' => 'custom', 'arrayofkeyval' => array('probation_end' => 'AnxhrDeadlineTypeProbationEnd', 'fixed_term_end' => 'AnxhrDeadlineTypeFixedTermEnd', 'contract_end' => 'AnxhrDeadlineTypeContractEnd', 'certificate' => 'AnxhrDeadlineTypeCertificate', 'permit' => 'AnxhrDeadlineTypePermit', 'review' => 'AnxhrDeadlineTypeReview', 'custom' => 'AnxhrDeadlineTypeCustom'), 'css' => 'minwidth200'),
		'date_due' => array('type' => 'date', 'label' => 'AnxhrDateDue', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'remind_days' => array('type' => 'varchar(64)', 'label' => 'AnxhrRemindDays', 'enabled' => 1, 'position' => 35, 'notnull' => 0, 'visible' => -1, 'default' => '30,14,7', 'css' => 'maxwidth150', 'help' => 'AnxhrRemindDaysHelp'),
		'last_remind_date' => array('type' => 'date', 'label' => 'AnxhrLastRemindDate', 'enabled' => 1, 'position' => 36, 'notnull' => 0, 'visible' => -5, 'noteditable' => 1),
		'fk_user_responsible' => array('type' => 'integer:User:user/class/user.class.php:0:(t.statut:=:1)', 'label' => 'AnxhrResponsible', 'picto' => 'user', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1, 'index' => 1, 'css' => 'minwidth300 maxwidth500', 'csslist' => 'tdoverflowmax150'),
		'fk_contract' => array('type' => 'integer:HrContract:anxhr/class/hrcontract.class.php', 'label' => 'AnxhrContract', 'picto' => 'fa-file-signature', 'enabled' => 1, 'position' => 45, 'notnull' => 0, 'visible' => -1, 'index' => 1, 'css' => 'maxwidth300', 'csslist' => 'tdoverflowmax150'),
		'fk_actioncomm' => array('type' => 'integer', 'label' => 'AnxhrAgendaEvent', 'enabled' => 1, 'position' => 46, 'notnull' => 0, 'visible' => 0),
		'note_private' => array('type' => 'text', 'label' => 'NotePrivate', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => 3, 'cssview' => 'wordbreak'),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'picto' => 'user', 'enabled' => 1, 'position' => 510, 'notnull' => 1, 'visible' => -2, 'csslist' => 'tdoverflowmax150'),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'picto' => 'user', 'enabled' => 1, 'position' => 511, 'notnull' => -1, 'visible' => -2, 'csslist' => 'tdoverflowmax150'),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => -1, 'visible' => -2),
		'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => 1, 'position' => 2000, 'notnull' => 1, 'default' => '0', 'visible' => 1, 'index' => 1, 'arrayofkeyval' => array(0 => 'AnxhrStatusOpen', 1 => 'AnxhrStatusDone', 9 => 'Canceled')),
	);

	/** @var string */
	public $label;
	/** @var int */
	public $fk_user;
	/** @var string */
	public $type;
	/** @var int|string */
	public $date_due;
	/** @var string */
	public $remind_days;
	/** @var int|string */
	public $last_remind_date;
	/** @var ?int */
	public $fk_user_responsible;
	/** @var ?int */
	public $fk_contract;
	/** @var ?int */
	public $fk_actioncomm;
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
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		global $langs;

		$this->db = $db;
		if (!isModEnabled('multicompany')) {
			$this->fields['entity']['enabled'] = 0;
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
	 * Create object into database
	 *
	 * @param  User     $user      User that creates
	 * @param  int<0,1> $notrigger 1=disable triggers
	 * @return int                 Id if OK, <0 if KO
	 */
	public function create(User $user, $notrigger = 0)
	{
		if ($this->status === null || $this->status === '') {
			$this->status = self::STATUS_OPEN;
		}
		if ($this->remind_days === null || $this->remind_days === '') {
			$this->remind_days = '30,14,7';
		}
		$this->remind_days = anxhrCleanRemindDays($this->remind_days);

		$this->db->begin();
		$result = $this->createCommon($user, 1);
		if ($result > 0 && !$notrigger) {
			if ($this->call_trigger('ANXHR_DEADLINE_CREATE', $user) < 0) {
				$result = -1;
			}
		}
		if ($result > 0) {
			$this->db->commit();
			return $this->id;
		}
		$this->db->rollback();
		return -1;
	}

	/**
	 * Load object in memory from the database
	 *
	 * @param  int    $id  Id object
	 * @param  string $ref Not used (no ref on deadlines)
	 * @return int         <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id, $ref = null)
	{
		return $this->fetchCommon($id, null);
	}

	/**
	 * Update object into database
	 *
	 * @param  User     $user      User that modifies
	 * @param  int<0,1> $notrigger 1=disable triggers
	 * @return int                 <0 if KO, >0 if OK
	 */
	public function update(User $user, $notrigger = 0)
	{
		$this->remind_days = anxhrCleanRemindDays((string) $this->remind_days);

		$this->db->begin();
		$result = $this->updateCommon($user, 1);
		if ($result > 0 && !$notrigger) {
			if ($this->call_trigger('ANXHR_DEADLINE_MODIFY', $user) < 0) {
				$result = -1;
			}
		}
		if ($result > 0) {
			$this->db->commit();
			return 1;
		}
		$this->db->rollback();
		return -1;
	}

	/**
	 * Delete object in database
	 *
	 * @param  User     $user      User that deletes
	 * @param  int<0,1> $notrigger 1=disable triggers
	 * @return int                 <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = 0)
	{
		if (!$notrigger) {
			if ($this->call_trigger('ANXHR_DEADLINE_DELETE', $user) < 0) {
				return -1;
			}
		}
		return $this->deleteCommon($user, 1);
	}

	/**
	 * Mark deadline as done
	 *
	 * @param  User $user User acting
	 * @return int        <0 if KO, 0 if nothing done, >0 if OK
	 */
	public function markDone(User $user)
	{
		if ($this->status == self::STATUS_DONE) {
			return 0;
		}
		return $this->setStatusCommon($user, self::STATUS_DONE, 0, 'ANXHR_DEADLINE_DONE');
	}

	/**
	 * Cancel deadline
	 *
	 * @param  User $user User acting
	 * @return int        <0 if KO, 0 if nothing done, >0 if OK
	 */
	public function cancel(User $user)
	{
		if ($this->status == self::STATUS_CANCELED) {
			return 0;
		}
		return $this->setStatusCommon($user, self::STATUS_CANCELED, 0, 'ANXHR_DEADLINE_CANCEL');
	}

	/**
	 * Reopen deadline
	 *
	 * @param  User $user User acting
	 * @return int        <0 if KO, 0 if nothing done, >0 if OK
	 */
	public function reopen(User $user)
	{
		if ($this->status == self::STATUS_OPEN) {
			return 0;
		}
		return $this->setStatusCommon($user, self::STATUS_OPEN, 0, 'ANXHR_DEADLINE_REOPEN');
	}

	/**
	 * Fetch open deadlines of an employee, sorted by due date
	 *
	 * @param  int $fk_user Employee id
	 * @param  int $limit   Max number of records
	 * @return HrDeadline[]|int  Array of deadlines or <0 if KO
	 */
	public function fetchOpenForUser($fk_user, $limit = 100)
	{
		$where = " AND t.fk_user = ".((int) $fk_user)." AND t.status = ".self::STATUS_OPEN;
		return $this->fetchByWhere($where, $limit);
	}

	/**
	 * Fetch open deadlines due between two dates (all employees of current entities)
	 *
	 * @param  int $dateFrom Start timestamp (included)
	 * @param  int $dateTo   End timestamp (included)
	 * @param  int $limit    Max number of records
	 * @return HrDeadline[]|int  Array of deadlines or <0 if KO
	 */
	public function fetchDueBetween($dateFrom, $dateTo, $limit = 1000)
	{
		$where = " AND t.status = ".self::STATUS_OPEN;
		$where .= " AND t.date_due >= '".$this->db->escape(dol_print_date($dateFrom, '%Y-%m-%d'))."'";
		$where .= " AND t.date_due <= '".$this->db->escape(dol_print_date($dateTo, '%Y-%m-%d'))."'";
		return $this->fetchByWhere($where, $limit);
	}

	/**
	 * Load records with a hard coded where clause
	 *
	 * @param  string $where SQL filter (must start with AND, no user input)
	 * @param  int    $limit Max number of records
	 * @return HrDeadline[]|int
	 */
	protected function fetchByWhere($where, $limit)
	{
		$records = array();
		$sql = "SELECT ".$this->getFieldList('t');
		$sql .= " FROM ".$this->db->prefix().$this->table_element." as t";
		$sql .= " WHERE t.entity IN (".getEntity($this->element).")";
		$sql .= $where;
		$sql .= $this->db->order('t.date_due', 'ASC');
		$sql .= $this->db->plimit((int) $limit);

		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->errors[] = $this->error;
			return -1;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$record = new self($this->db);
			$record->setVarsFromFetchObj($obj);
			$records[$record->id] = $record;
		}
		$this->db->free($resql);
		return $records;
	}

	/**
	 * Return number of days until due date (negative if overdue)
	 *
	 * @return int|null
	 */
	public function getDaysLeft()
	{
		if (empty($this->date_due)) {
			return null;
		}
		return anxhrDaysFromToday($this->date_due);
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
		$datas['picto'] = img_picto('', $this->picto).' <u>'.$langs->trans("AnxhrDeadline").'</u> '.$this->getLibStatut(5);
		$datas['label'] = '<br><b>'.$langs->trans('Label').':</b> '.dol_escape_htmltag($this->label);
		if (!empty($this->date_due)) {
			$datas['date'] = '<br><b>'.$langs->trans('AnxhrDateDue').':</b> '.dol_print_date($this->date_due, 'day');
		}
		return $datas;
	}

	/**
	 * Return a link to the object card
	 *
	 * @param  int    $withpicto             0=No picto, 1=Include picto, 2=Only picto
	 * @param  string $option                'nolink' or ''
	 * @param  int    $notooltip             1=Disable tooltip
	 * @param  string $morecss               More css
	 * @param  int    $save_lastsearch_value -1=Auto, 0=No, 1=Yes
	 * @return string
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
	{
		$text = ($this->label !== null && $this->label !== '') ? $this->label : '#'.$this->id;
		return anxhrBuildNomUrl($this, '/anxhr/deadline_card.php', $text, $withpicto, $option, $notooltip, $morecss, $save_lastsearch_value);
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
	 * Return label of a status. For open deadlines the badge color shows urgency:
	 * overdue = danger (status8), less than WARNING_DAYS days = warning, else info.
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
		$label = $langs->transnoentitiesnoconv('AnxhrStatusOpen');
		$type = 'info';
		if ($status == self::STATUS_DONE) {
			$label = $langs->transnoentitiesnoconv('AnxhrStatusDone');
			$type = 'status4';
		} elseif ($status == self::STATUS_CANCELED) {
			$label = $langs->transnoentitiesnoconv('Canceled');
			$type = 'status9';
		} else {
			$days = $this->getDaysLeft();
			if ($days !== null && $days < 0) {
				$label = $langs->transnoentitiesnoconv('AnxhrOverdue');
				$type = 'status8';
			} elseif ($days !== null && $days < self::WARNING_DAYS) {
				$label = $langs->transnoentitiesnoconv('AnxhrDueSoon');
				$type = 'warning';
			}
		}
		$params = array('badgeParams' => array('attr' => array('data-status-element' => $this->element, 'data-status' => (int) $status)));
		return dolGetStatus($label, $label, '', $type, $mode, '', $params);
	}

	/**
	 * Load info of object
	 *
	 * @param  int  $id Id
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
		$this->label = 'Probation end';
		$this->type = 'probation_end';
		$this->fk_user = 1;
		$this->date_due = dol_time_plus_duree(dol_now(), 10, 'd');
		$this->remind_days = '30,14,7';
		$this->status = self::STATUS_OPEN;
		return $ret;
	}
}
