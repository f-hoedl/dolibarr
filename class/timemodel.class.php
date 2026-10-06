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
 * \file        htdocs/custom/anxhr/class/timemodel.class.php
 * \ingroup     anxhr
 * \brief       Working time model (fixed, flexitime, self determined) - CRUD class
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class TimeModel
 */
class TimeModel extends CommonObject
{
	/** @var string */
	public $module = 'anxhr';
	/** @var string */
	public $element = 'anxhr_timemodel';
	/** @var string */
	public $TRIGGER_PREFIX = 'ANXHR_TIMEMODEL';
	/** @var string */
	public $table_element = 'anxhr_timemodel';
	/** @var string */
	public $picto = 'fa-business-time';
	/** @var int */
	public $isextrafieldmanaged = 0;
	/** @var int|string */
	public $ismultientitymanaged = 1;

	const STATUS_DISABLED = 0;
	const STATUS_ACTIVE = 1;

	/**
	 * @var array<string,array<string,mixed>>
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'default' => '1', 'enabled' => 1, 'visible' => 0, 'notnull' => 1, 'position' => 5, 'index' => 1),
		'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'maxwidth150'),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 20, 'notnull' => 0, 'visible' => 1, 'searchall' => 1, 'showoncombobox' => 2, 'css' => 'minwidth200'),
		'model_type' => array('type' => 'varchar(16)', 'label' => 'AnxhrModelType', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1, 'default' => 'fixed', 'arrayofkeyval' => array('fixed' => 'AnxhrModelTypeFixed', 'flex' => 'AnxhrModelTypeFlex', 'selfdetermined' => 'AnxhrModelTypeSelfdetermined'), 'help' => 'AnxhrModelTypeHelp'),
		'weekly_hours' => array('type' => 'double(6,2)', 'label' => 'AnxhrWeeklyHours', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1, 'css' => 'maxwidth75'),
		'daily_minutes_json' => array('type' => 'varchar(255)', 'label' => 'AnxhrDailyMinutes', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1),
		'flex_start' => array('type' => 'varchar(5)', 'label' => 'AnxhrFlexStart', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => -1, 'css' => 'maxwidth75'),
		'flex_end' => array('type' => 'varchar(5)', 'label' => 'AnxhrFlexEnd', 'enabled' => 1, 'position' => 61, 'notnull' => 0, 'visible' => -1, 'css' => 'maxwidth75'),
		'core_start' => array('type' => 'varchar(5)', 'label' => 'AnxhrCoreStart', 'enabled' => 1, 'position' => 62, 'notnull' => 0, 'visible' => -1, 'css' => 'maxwidth75'),
		'core_end' => array('type' => 'varchar(5)', 'label' => 'AnxhrCoreEnd', 'enabled' => 1, 'position' => 63, 'notnull' => 0, 'visible' => -1, 'css' => 'maxwidth75'),
		'flex_period_months' => array('type' => 'integer', 'label' => 'AnxhrFlexPeriodMonths', 'enabled' => 1, 'position' => 64, 'notnull' => 0, 'visible' => -1, 'default' => '1'),
		'max_carry_plus_min' => array('type' => 'integer', 'label' => 'AnxhrMaxCarryPlus', 'enabled' => 1, 'position' => 65, 'notnull' => 0, 'visible' => -1),
		'max_carry_minus_min' => array('type' => 'integer', 'label' => 'AnxhrMaxCarryMinus', 'enabled' => 1, 'position' => 66, 'notnull' => 0, 'visible' => -1),
		'break_rule' => array('type' => 'varchar(16)', 'label' => 'AnxhrBreakRule', 'enabled' => 1, 'position' => 70, 'notnull' => 0, 'visible' => 1, 'default' => 'auto', 'arrayofkeyval' => array('auto' => 'AnxhrBreakRuleAuto', 'check' => 'AnxhrBreakRuleCheck', 'required' => 'AnxhrBreakRuleRequired')),
		'normal_daily_max_min' => array('type' => 'integer', 'label' => 'AnxhrNormalDailyMax', 'enabled' => 1, 'position' => 80, 'notnull' => 0, 'visible' => 1, 'default' => '600'),
		'kv_code' => array('type' => 'varchar(32)', 'label' => 'AnxhrKvCode', 'enabled' => 1, 'position' => 90, 'notnull' => 0, 'visible' => 1),
		'note_private' => array('type' => 'text', 'label' => 'NotePrivate', 'enabled' => 1, 'position' => 100, 'notnull' => 0, 'visible' => 0),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 1, 'visible' => -2, 'foreignkey' => 'user.rowid'),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => -1, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => -1, 'visible' => -2),
		'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => 1, 'position' => 2000, 'notnull' => 1, 'visible' => 1, 'default' => '1', 'index' => 1, 'arrayofkeyval' => array(0 => 'Disabled', 1 => 'Enabled')),
	);

	/** @var int */
	public $rowid;
	/** @var string */
	public $ref;
	/** @var string */
	public $label;
	/** @var string */
	public $model_type;
	/** @var float|string|null */
	public $weekly_hours;
	/** @var string|null */
	public $daily_minutes_json;
	/** @var string|null */
	public $flex_start;
	/** @var string|null */
	public $flex_end;
	/** @var string|null */
	public $core_start;
	/** @var string|null */
	public $core_end;
	/** @var int|null */
	public $flex_period_months;
	/** @var int|null */
	public $max_carry_plus_min;
	/** @var int|null */
	public $max_carry_minus_min;
	/** @var string|null */
	public $break_rule;
	/** @var int|null */
	public $normal_daily_max_min;
	/** @var string|null */
	public $kv_code;
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
	 * @param	User	$user		User that creates
	 * @param	int		$notrigger	1=disable triggers
	 * @return	int					<0 if KO, id if OK
	 */
	public function create(User $user, $notrigger = 0)
	{
		$this->normalize();
		$this->db->begin();
		$res = $this->createCommon($user, 1);
		if ($res > 0 && !$notrigger && $this->call_trigger('ANXHR_TIMEMODEL_CREATE', $user) < 0) {
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
	 * Load object
	 *
	 * @param	int			$id		Id
	 * @param	string|null	$ref	Ref
	 * @return	int					<0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id, $ref = null)
	{
		return $this->fetchCommon($id, $ref);
	}

	/**
	 * Update object
	 *
	 * @param	User	$user		User
	 * @param	int		$notrigger	1=disable triggers
	 * @return	int					<0 if KO, >0 if OK
	 */
	public function update(User $user, $notrigger = 0)
	{
		$this->normalize();
		$this->db->begin();
		$res = $this->updateCommon($user, 1);
		if ($res > 0 && !$notrigger && $this->call_trigger('ANXHR_TIMEMODEL_MODIFY', $user) < 0) {
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
	 * Delete object
	 *
	 * @param	User	$user		User
	 * @param	int		$notrigger	1=disable triggers
	 * @return	int					<0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = 0)
	{
		$this->db->begin();
		$res = 1;
		if (!$notrigger && $this->call_trigger('ANXHR_TIMEMODEL_DELETE', $user) < 0) {
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
	 * Normalize values before saving (valid JSON, valid enums).
	 *
	 * @return void
	 */
	protected function normalize()
	{
		if (!in_array($this->model_type, array('fixed', 'flex', 'selfdetermined'), true)) {
			$this->model_type = 'fixed';
		}
		if (!in_array($this->break_rule, array('auto', 'check', 'required'), true)) {
			$this->break_rule = 'auto';
		}
		$this->setDailyMinutes($this->getDailyMinutes());
		if (empty($this->normal_daily_max_min)) {
			$this->normal_daily_max_min = 600;
		}
	}

	/**
	 * Return target minutes per ISO weekday (1=Monday..7=Sunday).
	 * If no JSON is defined, weekly hours are spread over Monday to Friday.
	 *
	 * @return array<int,int>
	 */
	public function getDailyMinutes()
	{
		$res = array();
		$decoded = empty($this->daily_minutes_json) ? null : json_decode((string) $this->daily_minutes_json, true);
		for ($d = 1; $d <= 7; $d++) {
			if (is_array($decoded)) {
				$res[$d] = isset($decoded[(string) $d]) ? max(0, (int) $decoded[(string) $d]) : 0;
			} else {
				$res[$d] = ($d <= 5) ? (int) round(((float) $this->weekly_hours) * 60 / 5) : 0;
			}
		}
		return $res;
	}

	/**
	 * Set target minutes per ISO weekday.
	 *
	 * @param	array<int,int|string>	$minutes	Minutes per weekday
	 * @return	void
	 */
	public function setDailyMinutes(array $minutes)
	{
		$clean = array();
		for ($d = 1; $d <= 7; $d++) {
			$clean[(string) $d] = isset($minutes[$d]) ? max(0, min(1440, (int) $minutes[$d])) : 0;
		}
		$this->daily_minutes_json = json_encode($clean);
	}

	/**
	 * Return the model as array for TimeEngine::computeDay().
	 *
	 * @param	float	$contractWeeklyHours	If > 0 and different from the model weekly hours, targets are scaled
	 * @return	array<string,mixed>
	 */
	public function toEngineArray($contractWeeklyHours = 0.0)
	{
		$daily = $this->getDailyMinutes();
		$modelWeekly = (float) $this->weekly_hours;
		if ($contractWeeklyHours > 0 && $modelWeekly > 0 && abs($contractWeeklyHours - $modelWeekly) > 0.001) {
			$factor = $contractWeeklyHours / $modelWeekly;
			foreach ($daily as $d => $m) {
				$daily[$d] = (int) round($m * $factor);
			}
		}
		return array(
			'model_type' => (string) $this->model_type,
			'daily_minutes' => $daily,
			'break_rule' => $this->break_rule ? (string) $this->break_rule : 'auto',
			'normal_daily_max_min' => (int) ($this->normal_daily_max_min ? $this->normal_daily_max_min : 600),
			'weekly_hours' => $modelWeekly,
		);
	}

	/**
	 * Built-in fallback model when nothing is configured (fixed 40h, paragraph 3 AZG).
	 *
	 * @return array<string,mixed>
	 */
	public static function fallbackEngineArray()
	{
		return array('model_type' => 'fixed', 'daily_minutes' => array(1 => 480, 2 => 480, 3 => 480, 4 => 480, 5 => 480, 6 => 0, 7 => 0), 'break_rule' => 'auto', 'normal_daily_max_min' => 600, 'weekly_hours' => 40);
	}

	/**
	 * Return list of models (id => TimeModel).
	 *
	 * @param	int		$onlyactive		1=only active
	 * @return	array<int,TimeModel>|int	<0 if KO
	 */
	public function fetchAllModels($onlyactive = 0)
	{
		$sql = "SELECT ".$this->getFieldList()." FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE entity IN (".getEntity($this->element).")";
		if ($onlyactive) {
			$sql .= " AND status = ".self::STATUS_ACTIVE;
		}
		$sql .= " ORDER BY ref ASC";
		$sql .= $this->db->plimit(500);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$list = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$tmp = new self($this->db);
			$tmp->setVarsFromFetchObj($obj);
			$tmp->id = (int) $obj->rowid;
			$list[$tmp->id] = $tmp;
		}
		$this->db->free($resql);
		return $list;
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
		$label = $status == self::STATUS_ACTIVE ? $langs->transnoentitiesnoconv('Enabled') : $langs->transnoentitiesnoconv('Disabled');
		return dolGetStatus($label, $label, '', $status == self::STATUS_ACTIVE ? 'status4' : 'status5', $mode);
	}
}
