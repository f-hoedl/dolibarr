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
 * \file        htdocs/custom/anxhr/class/travelrate.class.php
 * \ingroup     anxhr
 * \brief       Travel allowance rates (Taggeld, Naechtigung, Kilometergeld) with validity date - CRUD class
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once __DIR__.'/traveldiet.class.php';

/**
 * Class TravelRate
 */
class TravelRate extends CommonObject
{
	/** @var string */
	public $module = 'anxhr';
	/** @var string */
	public $element = 'anxhr_travel_rate';
	/** @var string */
	public $TRIGGER_PREFIX = 'ANXHR_TRAVELRATE';
	/** @var string */
	public $table_element = 'anxhr_travel_rate';
	/** @var string */
	public $picto = 'fa-route';
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
		'code' => array('type' => 'varchar(32)', 'label' => 'Code', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'css' => 'maxwidth150', 'help' => 'AnxhrTravelRateCodeHelp'),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 20, 'notnull' => 0, 'visible' => 1, 'css' => 'minwidth200'),
		'valid_from' => array('type' => 'date', 'label' => 'AnxhrValidFrom', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1),
		'amount' => array('type' => 'double(10,4)', 'label' => 'Amount', 'enabled' => 1, 'position' => 40, 'notnull' => 1, 'visible' => 1, 'css' => 'maxwidth100'),
		'cap_per_year' => array('type' => 'double(10,2)', 'label' => 'AnxhrCapPerYear', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1, 'css' => 'maxwidth100'),
		'note' => array('type' => 'varchar(255)', 'label' => 'Note', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => 1),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 1, 'visible' => -2, 'foreignkey' => 'user.rowid'),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => -1, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => -1, 'visible' => -2),
	);

	/** @var string */
	public $code;
	/** @var string */
	public $label;
	/** @var int|string */
	public $valid_from;
	/** @var float|string */
	public $amount;
	/** @var float|string|null */
	public $cap_per_year;
	/** @var string|null */
	public $note;
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
		$this->code = strtolower(preg_replace('/[^a-z0-9_]/i', '', (string) $this->code));
		if ($this->cap_per_year === '') {
			$this->cap_per_year = null;
		}
		$this->db->begin();
		$res = $this->createCommon($user, 1);
		if ($res > 0 && !$notrigger && $this->call_trigger('ANXHR_TRAVELRATE_CREATE', $user) < 0) {
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
		$this->code = strtolower(preg_replace('/[^a-z0-9_]/i', '', (string) $this->code));
		if ($this->cap_per_year === '') {
			$this->cap_per_year = null;
		}
		$this->db->begin();
		$res = $this->updateCommon($user, 1);
		if ($res > 0 && !$notrigger && $this->call_trigger('ANXHR_TRAVELRATE_MODIFY', $user) < 0) {
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
		if (!$notrigger && $this->call_trigger('ANXHR_TRAVELRATE_DELETE', $user) < 0) {
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
	 * Return all rate rows (for admin list).
	 *
	 * @return array<int,TravelRate>|int	<0 if KO
	 */
	public function fetchAllRates()
	{
		$sql = "SELECT ".$this->getFieldList()." FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE entity IN (".getEntity($this->element).")";
		$sql .= " ORDER BY code ASC, valid_from DESC";
		$sql .= $this->db->plimit(1000);
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
	 * Return the rates in the TravelDiet constructor format.
	 *
	 * @return array<string,array<int,array{valid_from:string,amount:float,cap_per_year:?float}>>
	 */
	public function getRatesArray()
	{
		$res = array();
		$all = $this->fetchAllRates();
		if (!is_array($all)) {
			return $res;
		}
		foreach ($all as $r) {
			$res[$r->code][] = array(
				'valid_from' => dol_print_date($r->valid_from, '%Y-%m-%d', 'tzserver'),
				'amount' => (float) $r->amount,
				'cap_per_year' => ($r->cap_per_year === null || $r->cap_per_year === '') ? null : (float) $r->cap_per_year,
			);
		}
		return $res;
	}

	/**
	 * Return a TravelDiet calculator loaded with the database rates.
	 *
	 * @return TravelDiet
	 */
	public function getDiet()
	{
		return new TravelDiet($this->getRatesArray());
	}

	/**
	 * Insert the Austrian default rates (TravelDiet::defaultAustrianRates()) that do not exist yet
	 * (same code and valid_from).
	 *
	 * @param	User	$user	User
	 * @return	int				Number of rows inserted, <0 if KO
	 */
	public function installDefaults(User $user)
	{
		$existing = array();
		$all = $this->fetchAllRates();
		if (!is_array($all)) {
			return -1;
		}
		foreach ($all as $r) {
			$existing[$r->code.'|'.dol_print_date($r->valid_from, '%Y-%m-%d', 'tzserver')] = 1;
		}
		$nb = 0;
		$this->db->begin();
		foreach (TravelDiet::defaultAustrianRates() as $code => $rows) {
			foreach ($rows as $row) {
				if (isset($existing[$code.'|'.$row['valid_from']])) {
					continue;
				}
				$new = new self($this->db);
				$new->code = $code;
				$new->label = $row['label'];
				$new->valid_from = dol_mktime(0, 0, 0, (int) substr($row['valid_from'], 5, 2), (int) substr($row['valid_from'], 8, 2), (int) substr($row['valid_from'], 0, 4), 'tzserver');
				$new->amount = $row['amount'];
				$new->cap_per_year = $row['cap_per_year'];
				if ($new->create($user) < 0) {
					$this->error = $new->error;
					$this->errors = $new->errors;
					$this->db->rollback();
					return -1;
				}
				$nb++;
			}
		}
		$this->db->commit();
		return $nb;
	}
}
