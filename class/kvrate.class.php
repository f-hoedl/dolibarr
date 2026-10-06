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
 * \file        htdocs/custom/anxhr/class/kvrate.class.php
 * \ingroup     anxhr
 * \brief       Collective agreement (KV) parameters with validity date - CRUD class
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class KvRate
 */
class KvRate extends CommonObject
{
	/** @var string */
	public $module = 'anxhr';
	/** @var string */
	public $element = 'anxhr_kv_rate';
	/** @var string */
	public $TRIGGER_PREFIX = 'ANXHR_KVRATE';
	/** @var string */
	public $table_element = 'anxhr_kv_rate';
	/** @var string */
	public $picto = 'fa-balance-scale';
	/** @var int */
	public $isextrafieldmanaged = 0;
	/** @var int|string */
	public $ismultientitymanaged = 1;

	/**
	 * @var array<string,array<string,mixed>>
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'default' => '1', 'enabled' => 1, 'visible' => 0, 'notnull' => 1, 'position' => 5, 'index' => 1),
		'kv_code' => array('type' => 'varchar(32)', 'label' => 'AnxhrKvCode', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'css' => 'maxwidth100'),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 20, 'notnull' => 0, 'visible' => 1, 'css' => 'minwidth200'),
		'valid_from' => array('type' => 'date', 'label' => 'AnxhrValidFrom', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1),
		'weekly_hours' => array('type' => 'double(6,2)', 'label' => 'AnxhrWeeklyHours', 'enabled' => 1, 'position' => 40, 'notnull' => 1, 'visible' => 1, 'default' => '40'),
		'ot_pct' => array('type' => 'integer', 'label' => 'AnxhrOtPct', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1, 'default' => '50'),
		'ot_night_pct' => array('type' => 'integer', 'label' => 'AnxhrOtNightPct', 'enabled' => 1, 'position' => 51, 'notnull' => 0, 'visible' => 1, 'default' => '100'),
		'ot_sunday_pct' => array('type' => 'integer', 'label' => 'AnxhrOtSundayPct', 'enabled' => 1, 'position' => 52, 'notnull' => 0, 'visible' => 1, 'default' => '100'),
		'parttime_extra_pct' => array('type' => 'integer', 'label' => 'AnxhrParttimeExtraPct', 'enabled' => 1, 'position' => 53, 'notnull' => 0, 'visible' => 1, 'default' => '25'),
		'za_factor' => array('type' => 'double(4,2)', 'label' => 'AnxhrZaFactor', 'enabled' => 1, 'position' => 54, 'notnull' => 0, 'visible' => 1, 'default' => '1.5'),
		'dec24_free' => array('type' => 'integer', 'label' => 'AnxhrDec24Free', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => 1, 'default' => '0'),
		'dec31_free' => array('type' => 'integer', 'label' => 'AnxhrDec31Free', 'enabled' => 1, 'position' => 61, 'notnull' => 0, 'visible' => 1, 'default' => '0'),
		'night_start' => array('type' => 'varchar(5)', 'label' => 'AnxhrNightStart', 'enabled' => 1, 'position' => 70, 'notnull' => 0, 'visible' => 1, 'default' => '22:00', 'css' => 'maxwidth75'),
		'night_end' => array('type' => 'varchar(5)', 'label' => 'AnxhrNightEnd', 'enabled' => 1, 'position' => 71, 'notnull' => 0, 'visible' => 1, 'default' => '06:00', 'css' => 'maxwidth75'),
		'note_private' => array('type' => 'text', 'label' => 'NotePrivate', 'enabled' => 1, 'position' => 100, 'notnull' => 0, 'visible' => 0),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 1, 'visible' => -2, 'foreignkey' => 'user.rowid'),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => -1, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => -1, 'visible' => -2),
	);

	/** @var int */
	public $rowid;
	/** @var string */
	public $kv_code;
	/** @var string */
	public $label;
	/** @var int|string	Timestamp */
	public $valid_from;
	/** @var float|string */
	public $weekly_hours;
	/** @var int */
	public $ot_pct;
	/** @var int */
	public $ot_night_pct;
	/** @var int */
	public $ot_sunday_pct;
	/** @var int */
	public $parttime_extra_pct;
	/** @var float|string */
	public $za_factor;
	/** @var int */
	public $dec24_free;
	/** @var int */
	public $dec31_free;
	/** @var string */
	public $night_start;
	/** @var string */
	public $night_end;
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
		$this->kv_code = strtoupper(dol_string_nospecial(trim((string) $this->kv_code)));
		$this->db->begin();
		$res = $this->createCommon($user, 1);
		if ($res > 0 && !$notrigger && $this->call_trigger('ANXHR_KVRATE_CREATE', $user) < 0) {
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
		$this->kv_code = strtoupper(dol_string_nospecial(trim((string) $this->kv_code)));
		$this->db->begin();
		$res = $this->updateCommon($user, 1);
		if ($res > 0 && !$notrigger && $this->call_trigger('ANXHR_KVRATE_MODIFY', $user) < 0) {
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
	 * Delete
	 *
	 * @param	User	$user		User
	 * @param	int		$notrigger	1=no trigger
	 * @return	int					<0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = 0)
	{
		$this->db->begin();
		$res = 1;
		if (!$notrigger && $this->call_trigger('ANXHR_KVRATE_DELETE', $user) < 0) {
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
	 * Load the KV parameter set valid at a given day (latest valid_from <= day).
	 *
	 * @param	string	$kvcode		KV code (example SWOE)
	 * @param	string	$day		Day 'Y-m-d'
	 * @return	int					<0 if KO, 0 if not found, >0 if OK
	 */
	public function fetchValidAt($kvcode, $day)
	{
		if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $day)) {
			return -1;
		}
		$sql = "SELECT rowid FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE entity IN (".getEntity($this->element).")";
		$sql .= " AND kv_code = '".$this->db->escape((string) $kvcode)."'";
		$sql .= " AND valid_from <= '".$this->db->escape($day)."'";
		$sql .= " ORDER BY valid_from DESC";
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
		return $this->fetch((int) $obj->rowid);
	}

	/**
	 * Return parameters as array for TimeEngine::computeDay().
	 *
	 * @return array<string,mixed>
	 */
	public function toEngineArray()
	{
		return array(
			'kv_code' => (string) $this->kv_code,
			'ot_pct' => (int) $this->ot_pct,
			'ot_night_pct' => (int) $this->ot_night_pct,
			'ot_sunday_pct' => (int) $this->ot_sunday_pct,
			'parttime_extra_pct' => (int) $this->parttime_extra_pct,
			'za_factor' => (float) $this->za_factor,
			'night_start' => $this->night_start ? (string) $this->night_start : '22:00',
			'night_end' => $this->night_end ? (string) $this->night_end : '06:00',
			'dec24_free' => (int) $this->dec24_free,
			'dec31_free' => (int) $this->dec31_free,
			'weekly_hours' => (float) $this->weekly_hours,
		);
	}

	/**
	 * Built-in fallback when no KV row exists: legal defaults of the AZG (40h week, paragraph 3 AZG).
	 *
	 * @return array<string,mixed>
	 */
	public static function fallbackEngineArray()
	{
		return array('kv_code' => '', 'ot_pct' => 50, 'ot_night_pct' => 100, 'ot_sunday_pct' => 100, 'parttime_extra_pct' => 25, 'za_factor' => 1.5, 'night_start' => '22:00', 'night_end' => '06:00', 'dec24_free' => 0, 'dec31_free' => 0, 'weekly_hours' => 40.0);
	}

	/**
	 * Return all rows (for admin list), ordered by code and validity.
	 *
	 * @return array<int,KvRate>|int	<0 if KO
	 */
	public function fetchAllRates()
	{
		$sql = "SELECT ".$this->getFieldList()." FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE entity IN (".getEntity($this->element).")";
		$sql .= " ORDER BY kv_code ASC, valid_from DESC";
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
}
