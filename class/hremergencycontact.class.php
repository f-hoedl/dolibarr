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
 * \file        htdocs/custom/anxhr/class/hremergencycontact.class.php
 * \ingroup     anxhr
 * \brief       CRUD class for HrEmergencyContact (emergency contacts of an employee)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once __DIR__.'/../lib/anxhr_hr.lib.php';

/**
 * Class for HrEmergencyContact
 */
class HrEmergencyContact extends CommonObject
{
	/** @var string */
	public $module = 'anxhr';
	/** @var string */
	public $element = 'anxhr_emergency_contact';
	/** @var string */
	public $TRIGGER_PREFIX = 'ANXHR_EMERGENCY_CONTACT';
	/** @var string */
	public $table_element = 'anxhr_emergency_contact';
	/** @var string */
	public $picto = 'fa-phone-alt';
	/** @var int<0,1> */
	public $isextrafieldmanaged = 0;
	/** @var int<0,1>|string */
	public $ismultientitymanaged = 1;

	/**
	 * @var array<string,array<string,mixed>> Fields definition
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'default' => '1', 'enabled' => 1, 'visible' => 0, 'notnull' => 1, 'position' => 5, 'index' => 1),
		'fk_user' => array('type' => 'integer:User:user/class/user.class.php:0:(t.employee:=:1)', 'label' => 'AnxhrEmployee', 'picto' => 'user', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'foreignkey' => 'user.rowid', 'css' => 'minwidth300 maxwidth500', 'csslist' => 'tdoverflowmax150'),
		'name' => array('type' => 'varchar(255)', 'label' => 'Name', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'minwidth300'),
		'relation' => array('type' => 'varchar(128)', 'label' => 'AnxhrRelation', 'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => 1, 'css' => 'minwidth200', 'help' => 'AnxhrRelationHelp'),
		'phone' => array('type' => 'phone', 'label' => 'Phone', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1, 'css' => 'minwidth200'),
		'email' => array('type' => 'email', 'label' => 'Email', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1, 'css' => 'minwidth300'),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 1, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => -1, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => -1, 'visible' => -2),
	);

	/** @var int */
	public $fk_user;
	/** @var string */
	public $name;
	/** @var string */
	public $relation;
	/** @var string */
	public $phone;
	/** @var string */
	public $email;
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
		$this->db = $db;
		if (!isModEnabled('multicompany')) {
			unset($this->fields['entity']);
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
		$this->db->begin();
		$result = $this->createCommon($user, 1);
		if ($result > 0 && !$notrigger && $this->call_trigger('ANXHR_EMERGENCY_CONTACT_CREATE', $user) < 0) {
			$result = -1;
		}
		if ($result > 0) {
			$this->db->commit();
			return $this->id;
		}
		$this->db->rollback();
		return -1;
	}

	/**
	 * Load object
	 *
	 * @param  int    $id  Id
	 * @param  string $ref Not used
	 * @return int         <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id, $ref = null)
	{
		return $this->fetchCommon($id, null);
	}

	/**
	 * Fetch all emergency contacts of an employee
	 *
	 * @param  int $fk_user Employee id
	 * @return HrEmergencyContact[]|int  Array or <0 if KO
	 */
	public function fetchAllForUser($fk_user)
	{
		$records = array();
		$sql = "SELECT ".$this->getFieldList('t')." FROM ".$this->db->prefix().$this->table_element." as t";
		$sql .= " WHERE t.fk_user = ".((int) $fk_user)." AND t.entity IN (".getEntity($this->element).")";
		$sql .= $this->db->order('t.rowid', 'ASC');
		$sql .= $this->db->plimit(100);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
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
	 * Update object
	 *
	 * @param  User     $user      User that modifies
	 * @param  int<0,1> $notrigger 1=disable triggers
	 * @return int                 <0 if KO, >0 if OK
	 */
	public function update(User $user, $notrigger = 0)
	{
		$this->db->begin();
		$result = $this->updateCommon($user, 1);
		if ($result > 0 && !$notrigger && $this->call_trigger('ANXHR_EMERGENCY_CONTACT_MODIFY', $user) < 0) {
			$result = -1;
		}
		if ($result > 0) {
			$this->db->commit();
			return 1;
		}
		$this->db->rollback();
		return -1;
	}

	/**
	 * Delete object
	 *
	 * @param  User     $user      User that deletes
	 * @param  int<0,1> $notrigger 1=disable triggers
	 * @return int                 <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = 0)
	{
		if (!$notrigger && $this->call_trigger('ANXHR_EMERGENCY_CONTACT_DELETE', $user) < 0) {
			return -1;
		}
		return $this->deleteCommon($user, 1);
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
		$datas['picto'] = img_picto('', $this->picto).' <u>'.$langs->trans("AnxhrEmergencyContact").'</u>';
		$datas['name'] = '<br><b>'.$langs->trans('Name').':</b> '.dol_escape_htmltag($this->name);
		if ($this->relation) {
			$datas['relation'] = '<br><b>'.$langs->trans('AnxhrRelation').':</b> '.dol_escape_htmltag($this->relation);
		}
		if ($this->phone) {
			$datas['phone'] = '<br><b>'.$langs->trans('Phone').':</b> '.dol_escape_htmltag($this->phone);
		}
		return $datas;
	}

	/**
	 * Return a link (no dedicated card: links to the HR user tab when available)
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
		return anxhrBuildNomUrl($this, '/anxhr/user_tab.php', (string) $this->name, $withpicto, $option, $notooltip, $morecss, 0, array('id' => (int) $this->fk_user));
	}

	/**
	 * Return status label (no status for this object)
	 *
	 * @param  int $mode Display mode
	 * @return string
	 */
	public function getLibStatut($mode = 0)
	{
		return '';
	}

	// phpcs:disable PEAR.NamingConventions.ValidFunctionName.ScopeNotCamelCaps
	/**
	 * Return status label (no status for this object)
	 *
	 * @param  int $status Status
	 * @param  int $mode   Display mode
	 * @return string
	 */
	public function LibStatut($status, $mode = 0)
	{
		// phpcs:enable
		return '';
	}

	/**
	 * Load info
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
		$this->fk_user = 1;
		$this->name = 'Jane Doe';
		$this->relation = 'Partner';
		$this->phone = '+43 1 234567';
		$this->email = 'jane.doe@example.com';
		return $ret;
	}
}
