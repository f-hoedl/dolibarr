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
 * \file        htdocs/custom/anxhr/class/hrhandover.class.php
 * \ingroup     anxhr
 * \brief       CRUD class for HrHandover (hardware, keys, tokens and access rights given to an employee)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once __DIR__.'/../lib/anxhr_hr.lib.php';

/**
 * Class for HrHandover
 */
class HrHandover extends CommonObject
{
	/** @var string */
	public $module = 'anxhr';
	/** @var string */
	public $element = 'anxhr_handover';
	/** @var string */
	public $TRIGGER_PREFIX = 'ANXHR_HANDOVER';
	/** @var string */
	public $table_element = 'anxhr_handover';
	/** @var string */
	public $picto = 'fa-laptop';
	/** @var int<0,1> */
	public $isextrafieldmanaged = 0;
	/** @var int<0,1>|string */
	public $ismultientitymanaged = 1;

	const STATUS_OUT = 0;
	const STATUS_RETURNED = 1;
	const STATUS_LOST = 9;

	/**
	 * @var array<string,array<string,mixed>> Fields definition
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'default' => '1', 'enabled' => 1, 'visible' => 0, 'notnull' => 1, 'position' => 5, 'index' => 1),
		'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 4, 'noteditable' => 1, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'csslist' => 'nowraponall'),
		'fk_user' => array('type' => 'integer:User:user/class/user.class.php:0:(t.employee:=:1)', 'label' => 'AnxhrEmployee', 'picto' => 'user', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'foreignkey' => 'user.rowid', 'css' => 'minwidth300 maxwidth500', 'csslist' => 'tdoverflowmax150'),
		'category' => array('type' => 'varchar(32)', 'label' => 'Category', 'enabled' => 1, 'position' => 25, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'default' => 'HARDWARE', 'arrayofkeyval' => array('HARDWARE' => 'AnxhrHandoverCatHardware', 'KEY' => 'AnxhrHandoverCatKey', 'TOKEN' => 'AnxhrHandoverCatToken', 'ACCESS' => 'AnxhrHandoverCatAccess', 'OTHER' => 'AnxhrHandoverCatOther'), 'css' => 'minwidth200'),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1, 'searchall' => 1, 'showoncombobox' => 2, 'css' => 'minwidth300', 'csslist' => 'tdoverflowmax200'),
		'serial' => array('type' => 'varchar(128)', 'label' => 'AnxhrSerial', 'enabled' => 1, 'position' => 35, 'notnull' => 0, 'visible' => 1, 'searchall' => 1, 'css' => 'minwidth200', 'csslist' => 'tdoverflowmax150'),
		'system_name' => array('type' => 'varchar(128)', 'label' => 'AnxhrSystemName', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => -1, 'searchall' => 1, 'css' => 'minwidth200', 'help' => 'AnxhrSystemNameHelp'),
		'role_name' => array('type' => 'varchar(128)', 'label' => 'AnxhrRoleName', 'enabled' => 1, 'position' => 41, 'notnull' => 0, 'visible' => -1, 'css' => 'minwidth200', 'help' => 'AnxhrRoleNameHelp'),
		'fk_asset' => array('type' => 'integer:Asset:asset/class/asset.class.php', 'label' => 'AnxhrAsset', 'picto' => 'asset', 'enabled' => 'isModEnabled("asset")', 'position' => 45, 'notnull' => 0, 'visible' => -1, 'index' => 1, 'css' => 'maxwidth300', 'csslist' => 'tdoverflowmax150'),
		'date_out' => array('type' => 'date', 'label' => 'AnxhrDateOut', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1, 'index' => 1),
		'condition_out' => array('type' => 'varchar(255)', 'label' => 'AnxhrConditionOut', 'enabled' => 1, 'position' => 51, 'notnull' => 0, 'visible' => -1, 'css' => 'minwidth300'),
		'date_return' => array('type' => 'date', 'label' => 'AnxhrDateReturn', 'enabled' => 1, 'position' => 55, 'notnull' => 0, 'visible' => 1, 'index' => 1),
		'condition_return' => array('type' => 'varchar(255)', 'label' => 'AnxhrConditionReturn', 'enabled' => 1, 'position' => 56, 'notnull' => 0, 'visible' => -1, 'css' => 'minwidth300'),
		'note_private' => array('type' => 'text', 'label' => 'NotePrivate', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => 3, 'cssview' => 'wordbreak'),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'picto' => 'user', 'enabled' => 1, 'position' => 510, 'notnull' => 1, 'visible' => -2, 'csslist' => 'tdoverflowmax150'),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'picto' => 'user', 'enabled' => 1, 'position' => 511, 'notnull' => -1, 'visible' => -2, 'csslist' => 'tdoverflowmax150'),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => -1, 'visible' => -2),
		'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => 1, 'position' => 2000, 'notnull' => 1, 'default' => '0', 'visible' => 1, 'index' => 1, 'arrayofkeyval' => array(0 => 'AnxhrHandoverStatusOut', 1 => 'AnxhrHandoverStatusReturned', 9 => 'AnxhrHandoverStatusLost')),
	);

	/** @var string */
	public $ref;
	/** @var int */
	public $fk_user;
	/** @var string */
	public $category;
	/** @var string */
	public $label;
	/** @var string */
	public $serial;
	/** @var string */
	public $system_name;
	/** @var string */
	public $role_name;
	/** @var ?int */
	public $fk_asset;
	/** @var int|string */
	public $date_out;
	/** @var int|string */
	public $date_return;
	/** @var string */
	public $condition_out;
	/** @var string */
	public $condition_return;
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
			if (isset($val['enabled']) && !verifCond((string) $val['enabled'])) {
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
		// Complete categories with the active entries of dictionary llx_c_anxhr_handover_cat (if installed)
		$dict = anxhrGetHandoverCategories($db);
		if (!empty($dict)) {
			$this->fields['category']['arrayofkeyval'] = array_merge($this->fields['category']['arrayofkeyval'], $dict);
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
		if (empty($this->ref) || preg_match('/^\(?PROV/i', $this->ref)) {
			$this->ref = anxhrGetNextUserRef($this->db, $this->table_element, 'HO', (int) $this->fk_user);
		}
		if ($this->status === null || $this->status === '') {
			$this->status = self::STATUS_OUT;
		}
		if (empty($this->date_out)) {
			$this->date_out = dol_now();
		}

		$this->db->begin();
		$result = $this->createCommon($user, 1);
		if ($result > 0 && !$notrigger && $this->call_trigger('ANXHR_HANDOVER_CREATE', $user) < 0) {
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
	 * @param  string $ref Ref
	 * @return int         <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id, $ref = null)
	{
		return $this->fetchCommon($id, $ref);
	}

	/**
	 * Fetch handovers of an employee that are still out (not returned)
	 *
	 * @param  int $fk_user Employee id
	 * @return HrHandover[]|int  Array or <0 if KO
	 */
	public function fetchOpenForUser($fk_user)
	{
		$records = array();
		$sql = "SELECT ".$this->getFieldList('t')." FROM ".$this->db->prefix().$this->table_element." as t";
		$sql .= " WHERE t.fk_user = ".((int) $fk_user)." AND t.status = ".self::STATUS_OUT;
		$sql .= " AND t.entity IN (".getEntity($this->element).")";
		$sql .= $this->db->order('t.category,t.date_out', 'ASC,ASC');
		$sql .= $this->db->plimit(500);
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
		if ($result > 0 && !$notrigger && $this->call_trigger('ANXHR_HANDOVER_MODIFY', $user) < 0) {
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
	 * Delete object. Links from checklist items are removed first.
	 *
	 * @param  User     $user      User that deletes
	 * @param  int<0,1> $notrigger 1=disable triggers
	 * @return int                 <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = 0)
	{
		$this->db->begin();
		$sql = "UPDATE ".$this->db->prefix()."anxhr_checklist_item SET fk_handover = NULL WHERE fk_handover = ".((int) $this->id);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		if (!$notrigger && $this->call_trigger('ANXHR_HANDOVER_DELETE', $user) < 0) {
			$this->db->rollback();
			return -1;
		}
		$result = $this->deleteCommon($user, 1);
		if ($result > 0) {
			$this->db->commit();
			return 1;
		}
		$this->db->rollback();
		return -1;
	}

	/**
	 * Set handover as returned. Open offboarding checklist items linked to this handover are set to done.
	 *
	 * @param  User     $user      User acting
	 * @param  int|null $date      Return date (timestamp), now if empty
	 * @param  string   $condition Condition at return
	 * @return int                 <0 if KO, >0 if OK
	 */
	public function setReturned(User $user, $date = null, $condition = '')
	{
		$this->date_return = empty($date) ? dol_now() : $date;
		$this->condition_return = $condition;
		$this->status = self::STATUS_RETURNED;

		$this->db->begin();
		$result = $this->update($user, 1);
		if ($result > 0 && $this->call_trigger('ANXHR_HANDOVER_RETURN', $user) < 0) {
			$result = -1;
		}
		if ($result > 0) {
			$result = $this->closeLinkedOffboardingItems($user);
		}
		if ($result >= 0) {
			$this->db->commit();
			return 1;
		}
		$this->db->rollback();
		return -1;
	}

	/**
	 * Set handover as lost
	 *
	 * @param  User $user User acting
	 * @return int        <0 if KO, >0 if OK
	 */
	public function setLost(User $user)
	{
		return $this->setStatusCommon($user, self::STATUS_LOST, 0, 'ANXHR_HANDOVER_LOST');
	}

	/**
	 * Set back to status out
	 *
	 * @param  User $user User acting
	 * @return int        <0 if KO, >0 if OK
	 */
	public function reopen(User $user)
	{
		$this->date_return = '';
		$this->condition_return = '';
		$this->status = self::STATUS_OUT;
		return $this->update($user);
	}

	/**
	 * Mark open offboarding checklist items linked to this handover as done
	 *
	 * @param  User $user User acting
	 * @return int        Number of checklists recomputed, <0 if KO
	 */
	protected function closeLinkedOffboardingItems(User $user)
	{
		$sql = "SELECT DISTINCT i.fk_checklist FROM ".$this->db->prefix()."anxhr_checklist_item as i";
		$sql .= " INNER JOIN ".$this->db->prefix()."anxhr_checklist as c ON c.rowid = i.fk_checklist";
		$sql .= " WHERE i.fk_handover = ".((int) $this->id)." AND i.status = 0 AND c.checklist_type = 'offboarding'";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$checklistids = array();
		while ($obj = $this->db->fetch_object($resql)) {
			$checklistids[] = (int) $obj->fk_checklist;
		}
		$this->db->free($resql);
		if (empty($checklistids)) {
			return 0;
		}

		$sql = "UPDATE ".$this->db->prefix()."anxhr_checklist_item SET status = 1, date_done = '".$this->db->idate(dol_now())."', fk_user_done = ".((int) $user->id);
		$sql .= " WHERE fk_handover = ".((int) $this->id)." AND status = 0 AND fk_checklist IN (".$this->db->sanitize(implode(',', $checklistids)).")";
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}

		require_once __DIR__.'/hrchecklist.class.php';
		foreach ($checklistids as $checklistid) {
			$checklist = new HrChecklist($this->db);
			$checklist->id = $checklistid;
			if ($checklist->recomputeProgress($user) < 0) {
				$this->error = $checklist->error;
				return -1;
			}
		}
		return count($checklistids);
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
		$datas['picto'] = img_picto('', $this->picto).' <u>'.$langs->trans("AnxhrHandover").'</u> '.$this->getLibStatut(5);
		$datas['ref'] = '<br><b>'.$langs->trans('Ref').':</b> '.dol_escape_htmltag($this->ref);
		$datas['label'] = '<br><b>'.$langs->trans('Label').':</b> '.dol_escape_htmltag($this->label);
		if (!empty($this->category) && isset($this->fields['category']['arrayofkeyval'][$this->category])) {
			$datas['category'] = '<br><b>'.$langs->trans('Category').':</b> '.dol_escape_htmltag($this->fields['category']['arrayofkeyval'][$this->category]);
		}
		if (!empty($this->serial)) {
			$datas['serial'] = '<br><b>'.$langs->trans('AnxhrSerial').':</b> '.dol_escape_htmltag($this->serial);
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
		return anxhrBuildNomUrl($this, '/anxhr/handover_card.php', $this->ref, $withpicto, $option, $notooltip, $morecss, $save_lastsearch_value);
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
	 * Return label of a status
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
			self::STATUS_OUT => $langs->transnoentitiesnoconv('AnxhrHandoverStatusOut'),
			self::STATUS_RETURNED => $langs->transnoentitiesnoconv('AnxhrHandoverStatusReturned'),
			self::STATUS_LOST => $langs->transnoentitiesnoconv('AnxhrHandoverStatusLost'),
		);
		$types = array(self::STATUS_OUT => 'status1', self::STATUS_RETURNED => 'status4', self::STATUS_LOST => 'status8');
		$label = isset($labels[$status]) ? $labels[$status] : (string) $status;
		$type = isset($types[$status]) ? $types[$status] : 'status0';
		return dolGetStatus($label, $label, '', $type, $mode, '', array('badgeParams' => array('attr' => array('data-status-element' => $this->element, 'data-status' => (int) $status))));
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
		$this->ref = 'HO-1-1';
		$this->fk_user = 1;
		$this->category = 'HARDWARE';
		$this->label = 'Notebook';
		$this->serial = 'SN-0001';
		$this->date_out = dol_now();
		$this->status = self::STATUS_OUT;
		return $ret;
	}
}
