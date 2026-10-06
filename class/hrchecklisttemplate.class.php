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
 * \file        htdocs/custom/anxhr/class/hrchecklisttemplate.class.php
 * \ingroup     anxhr
 * \brief       CRUD class for HrChecklistTemplate and its items HrChecklistTemplateItem
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobjectline.class.php';
require_once __DIR__.'/../lib/anxhr_hr.lib.php';

/**
 * Class for HrChecklistTemplate
 */
class HrChecklistTemplate extends CommonObject
{
	/** @var string */
	public $module = 'anxhr';
	/** @var string */
	public $element = 'anxhr_checklist_template';
	/** @var string */
	public $TRIGGER_PREFIX = 'ANXHR_CHECKLIST_TEMPLATE';
	/** @var string */
	public $table_element = 'anxhr_checklist_template';
	/** @var string */
	public $picto = 'fa-list-ol';
	/** @var int<0,1> */
	public $isextrafieldmanaged = 0;
	/** @var int<0,1>|string */
	public $ismultientitymanaged = 1;

	/** @var string Name of subtable line */
	public $table_element_line = 'anxhr_checklist_template_item';
	/** @var string Field with id of parent in line table */
	public $fk_element = 'fk_template';
	/** @var string Class of lines */
	public $class_element_line = 'HrChecklistTemplateItem';
	/** @var string[] Child tables deleted on cascade */
	protected $childtablesoncascade = array('anxhr_checklist_template_item');

	/** @var HrChecklistTemplateItem[] */
	public $lines = array();

	const STATUS_DISABLED = 0;
	const STATUS_ENABLED = 1;

	/**
	 * @var array<string,array<string,mixed>> Fields definition
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'default' => '1', 'enabled' => 1, 'visible' => 0, 'notnull' => 1, 'position' => 5, 'index' => 1),
		'ref' => array('type' => 'varchar(128)', 'label' => 'Code', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'css' => 'maxwidth150'),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1, 'searchall' => 1, 'showoncombobox' => 2, 'css' => 'minwidth300'),
		'template_type' => array('type' => 'varchar(16)', 'label' => 'Type', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'default' => 'onboarding', 'arrayofkeyval' => array('onboarding' => 'AnxhrChecklistTypeOnboarding', 'offboarding' => 'AnxhrChecklistTypeOffboarding', 'transfer' => 'AnxhrChecklistTypeTransfer')),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 510, 'notnull' => 1, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'position' => 511, 'notnull' => -1, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => -1, 'visible' => -2),
		'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => 1, 'position' => 2000, 'notnull' => 1, 'default' => '1', 'visible' => 1, 'index' => 1, 'arrayofkeyval' => array(0 => 'Disabled', 1 => 'Enabled')),
	);

	/** @var string */
	public $ref;
	/** @var string */
	public $label;
	/** @var string */
	public $template_type;
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
			unset($this->fields['entity']);
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
	 * Create object
	 *
	 * @param  User     $user      User that creates
	 * @param  int<0,1> $notrigger 1=disable triggers
	 * @return int                 Id if OK, <0 if KO
	 */
	public function create(User $user, $notrigger = 0)
	{
		if ($this->status === null || $this->status === '') {
			$this->status = self::STATUS_ENABLED;
		}
		if (empty($this->ref)) {
			// Generate a code from the type and a counter, so a template can be saved without typing a code
			$prefix = strtoupper(substr((string) $this->template_type, 0, 3));
			$sql = "SELECT COUNT(rowid) as nb FROM ".$this->db->prefix().$this->table_element;
			$sql .= " WHERE entity IN (".getEntity($this->element).")";
			$resql = $this->db->query($sql);
			$obj = ($resql ? $this->db->fetch_object($resql) : null);
			$this->ref = ($prefix ? $prefix : 'TPL').'-'.(($obj ? (int) $obj->nb : 0) + 1);
		}
		$this->db->begin();
		$result = $this->createCommon($user, 1);
		if ($result > 0 && !$notrigger && $this->call_trigger('ANXHR_CHECKLIST_TEMPLATE_CREATE', $user) < 0) {
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
	 * Load object and its lines
	 *
	 * @param  int    $id      Id
	 * @param  string $ref     Ref
	 * @param  int    $nolines 1=Do not load lines
	 * @return int             <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id, $ref = null, $nolines = 0)
	{
		$result = $this->fetchCommon($id, $ref);
		if ($result > 0 && empty($nolines)) {
			$this->fetchLines();
		}
		return $result;
	}

	/**
	 * Load lines (items) of template, ordered by position
	 *
	 * @return int <0 if KO, >0 if OK
	 */
	public function fetchLines()
	{
		$this->lines = array();
		$line = new HrChecklistTemplateItem($this->db);
		$sql = "SELECT ".$line->getFieldList('l')." FROM ".$this->db->prefix().$this->table_element_line." as l";
		$sql .= " WHERE l.fk_template = ".((int) $this->id);
		$sql .= $this->db->order('l.position,l.rowid', 'ASC,ASC');
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$newline = new HrChecklistTemplateItem($this->db);
			$newline->setVarsFromFetchObj($obj);
			$this->lines[] = $newline;
		}
		$this->db->free($resql);
		return 1;
	}

	/**
	 * Fetch first enabled template of a type
	 *
	 * @param  string $type onboarding, offboarding or transfer
	 * @return int          >0 if found, 0 if none, <0 if KO
	 */
	public function fetchFirstActiveOfType($type)
	{
		$sql = "SELECT t.rowid FROM ".$this->db->prefix().$this->table_element." as t";
		$sql .= " WHERE t.template_type = '".$this->db->escape($type)."' AND t.status = ".self::STATUS_ENABLED;
		$sql .= " AND t.entity IN (".getEntity($this->element).")";
		$sql .= $this->db->order('t.rowid', 'ASC');
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
		if ($result > 0 && !$notrigger && $this->call_trigger('ANXHR_CHECKLIST_TEMPLATE_MODIFY', $user) < 0) {
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
	 * Delete object and its items
	 *
	 * @param  User     $user      User that deletes
	 * @param  int<0,1> $notrigger 1=disable triggers
	 * @return int                 <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = 0)
	{
		if (!$notrigger && $this->call_trigger('ANXHR_CHECKLIST_TEMPLATE_DELETE', $user) < 0) {
			return -1;
		}
		return $this->deleteCommon($user, 1);
	}

	/**
	 * Add an item to template
	 *
	 * @param  User                    $user User acting
	 * @param  HrChecklistTemplateItem $item Item (fk_template is forced)
	 * @return int                           Id of item if OK, <0 if KO
	 */
	public function addItem(User $user, HrChecklistTemplateItem $item)
	{
		$item->fk_template = $this->id;
		if (empty($item->position)) {
			$item->position = $this->getNextItemPosition();
		}
		$result = $item->create($user);
		if ($result < 0) {
			$this->setErrorsFromObject($item);
		}
		return $result;
	}

	/**
	 * Return next free position for an item
	 *
	 * @return int
	 */
	protected function getNextItemPosition()
	{
		$sql = "SELECT MAX(position) as maxpos FROM ".$this->db->prefix().$this->table_element_line." WHERE fk_template = ".((int) $this->id);
		$resql = $this->db->query($sql);
		if (!$resql) {
			return 10;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		return ((int) $obj->maxpos) + 10;
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
		$datas['picto'] = img_picto('', $this->picto).' <u>'.$langs->trans("AnxhrChecklistTemplate").'</u> '.$this->getLibStatut(5);
		$datas['ref'] = '<br><b>'.$langs->trans('Code').':</b> '.dol_escape_htmltag($this->ref);
		$datas['label'] = '<br><b>'.$langs->trans('Label').':</b> '.dol_escape_htmltag($this->label);
		return $datas;
	}

	/**
	 * Return a link to the template (admin page)
	 *
	 * @param  int    $withpicto             0=No picto, 1=Include picto, 2=Only picto
	 * @param  string $option                'nolink' or ''
	 * @param  int    $notooltip             1=Disable tooltip
	 * @param  string $morecss               More css
	 * @param  int    $save_lastsearch_value Not used
	 * @return string
	 */
	public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $morecss = '', $save_lastsearch_value = -1)
	{
		return anxhrBuildNomUrl($this, '/anxhr/admin/checklist_templates.php', $this->ref.' - '.$this->label, $withpicto, $option, $notooltip, $morecss, 0, array('templateid' => (int) $this->id));
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
		$label = ($status == self::STATUS_ENABLED ? $langs->transnoentitiesnoconv('Enabled') : $langs->transnoentitiesnoconv('Disabled'));
		$type = ($status == self::STATUS_ENABLED ? 'status4' : 'status5');
		return dolGetStatus($label, $label, '', $type, $mode);
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
		$this->ref = 'ONB-STD';
		$this->label = 'Standard onboarding';
		$this->template_type = 'onboarding';
		$this->status = self::STATUS_ENABLED;
		return $ret;
	}
}


/**
 * Class HrChecklistTemplateItem (line of a checklist template)
 */
class HrChecklistTemplateItem extends CommonObjectLine
{
	/** @var string */
	public $module = 'anxhr';
	/** @var string */
	public $element = 'anxhr_checklist_template_item';
	/** @var string */
	public $table_element = 'anxhr_checklist_template_item';
	/** @var string */
	public $parent_element = 'anxhr_checklist_template';
	/** @var string */
	public $fk_parent_attribute = 'fk_template';
	/** @var int<0,1> */
	public $isextrafieldmanaged = 0;
	/** @var int<0,1>|string */
	public $ismultientitymanaged = 0;

	/**
	 * @var array<string,array<string,mixed>> Fields definition
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1),
		'fk_template' => array('type' => 'integer', 'label' => 'AnxhrChecklistTemplate', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => 0),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'default' => '0', 'visible' => 1, 'css' => 'maxwidth50'),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1, 'css' => 'minwidth300'),
		'responsible_role' => array('type' => 'varchar(16)', 'label' => 'AnxhrResponsibleRole', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'default' => 'hr', 'visible' => 1, 'arrayofkeyval' => array('hr' => 'AnxhrRoleHr', 'it' => 'AnxhrRoleIt', 'supervisor' => 'AnxhrRoleSupervisor', 'employee' => 'AnxhrRoleEmployee')),
		'offset_days' => array('type' => 'integer', 'label' => 'AnxhrOffsetDays', 'enabled' => 1, 'position' => 40, 'notnull' => 1, 'default' => '0', 'visible' => 1, 'css' => 'maxwidth50', 'help' => 'AnxhrOffsetDaysHelp'),
		'creates_handover' => array('type' => 'boolean', 'label' => 'AnxhrCreatesHandover', 'enabled' => 1, 'position' => 50, 'notnull' => 1, 'default' => '0', 'visible' => 1, 'help' => 'AnxhrCreatesHandoverHelp'),
		'handover_category' => array('type' => 'varchar(32)', 'label' => 'AnxhrHandoverCategory', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => 1),
	);

	/** @var int */
	public $fk_template;
	/** @var int */
	public $position;
	/** @var string */
	public $label;
	/** @var string */
	public $responsible_role;
	/** @var int */
	public $offset_days;
	/** @var int */
	public $creates_handover;
	/** @var string */
	public $handover_category;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	/**
	 * Create item
	 *
	 * @param  User     $user      User that creates
	 * @param  int<0,1> $notrigger 1=disable triggers (no trigger on template items)
	 * @return int                 Id if OK, <0 if KO
	 */
	public function create(User $user, $notrigger = 1)
	{
		$this->normalize();
		return $this->createCommon($user, 1);
	}

	/**
	 * Load item
	 *
	 * @param  int $id Id
	 * @return int     <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id)
	{
		return $this->fetchCommon($id);
	}

	/**
	 * Update item
	 *
	 * @param  User     $user      User that modifies
	 * @param  int<0,1> $notrigger 1=disable triggers
	 * @return int                 <0 if KO, >0 if OK
	 */
	public function update(User $user, $notrigger = 1)
	{
		$this->normalize();
		return $this->updateCommon($user, 1);
	}

	/**
	 * Delete item
	 *
	 * @param  User     $user      User that deletes
	 * @param  int<0,1> $notrigger 1=disable triggers
	 * @return int                 <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = 1)
	{
		$sql = "DELETE FROM ".$this->db->prefix().$this->table_element." WHERE rowid = ".((int) $this->id);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return 1;
	}

	/**
	 * Normalize values before save
	 *
	 * @return void
	 */
	protected function normalize()
	{
		if (!in_array($this->responsible_role, array('hr', 'it', 'supervisor', 'employee'))) {
			$this->responsible_role = 'hr';
		}
		$this->offset_days = (int) $this->offset_days;
		$this->creates_handover = empty($this->creates_handover) ? 0 : 1;
		if (!$this->creates_handover) {
			$this->handover_category = '';
		}
	}
}
