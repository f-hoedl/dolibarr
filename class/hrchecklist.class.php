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
 * \file        htdocs/custom/anxhr/class/hrchecklist.class.php
 * \ingroup     anxhr
 * \brief       CRUD class for HrChecklist (on/offboarding instance) and its items HrChecklistItem
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/commonobjectline.class.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once __DIR__.'/hrchecklisttemplate.class.php';
require_once __DIR__.'/../lib/anxhr_hr.lib.php';

/**
 * Class for HrChecklist
 */
class HrChecklist extends CommonObject
{
	/** @var string */
	public $module = 'anxhr';
	/** @var string */
	public $element = 'anxhr_checklist';
	/** @var string */
	public $TRIGGER_PREFIX = 'ANXHR_CHECKLIST';
	/** @var string */
	public $table_element = 'anxhr_checklist';
	/** @var string */
	public $picto = 'fa-tasks';
	/** @var int<0,1> */
	public $isextrafieldmanaged = 0;
	/** @var int<0,1>|string */
	public $ismultientitymanaged = 1;

	/** @var string */
	public $table_element_line = 'anxhr_checklist_item';
	/** @var string */
	public $fk_element = 'fk_checklist';
	/** @var string */
	public $class_element_line = 'HrChecklistItem';
	/** @var string[] */
	protected $childtablesoncascade = array('anxhr_checklist_item');

	/** @var HrChecklistItem[] */
	public $lines = array();

	/** @var ?HrChecklistTemplate Template already loaded by instantiateFromTemplate() */
	protected $templateloaded = null;

	const STATUS_OPEN = 0;
	const STATUS_DONE = 1;
	const STATUS_CANCELED = 9;

	/**
	 * @var array<string,array<string,mixed>> Fields definition
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'default' => '1', 'enabled' => 1, 'visible' => 0, 'notnull' => 1, 'position' => 5, 'index' => 1),
		'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 4, 'noteditable' => 1, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'csslist' => 'nowraponall'),
		'fk_user' => array('type' => 'integer:User:user/class/user.class.php:0:(t.employee:=:1)', 'label' => 'AnxhrEmployee', 'picto' => 'user', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'foreignkey' => 'user.rowid', 'css' => 'minwidth300 maxwidth500', 'csslist' => 'tdoverflowmax150'),
		'fk_template' => array('type' => 'integer:HrChecklistTemplate:anxhr/class/hrchecklisttemplate.class.php:0:(t.status:=:1)', 'label' => 'AnxhrChecklistTemplate', 'picto' => 'fa-list-ol', 'enabled' => 1, 'position' => 25, 'notnull' => 0, 'visible' => 1, 'index' => 1, 'css' => 'minwidth300 maxwidth500', 'csslist' => 'tdoverflowmax150', 'help' => 'AnxhrChecklistTemplateHelp'),
		'checklist_type' => array('type' => 'varchar(16)', 'label' => 'Type', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'default' => 'onboarding', 'arrayofkeyval' => array('onboarding' => 'AnxhrChecklistTypeOnboarding', 'offboarding' => 'AnxhrChecklistTypeOffboarding', 'transfer' => 'AnxhrChecklistTypeTransfer')),
		'anchor_date' => array('type' => 'date', 'label' => 'AnxhrAnchorDate', 'enabled' => 1, 'position' => 35, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'help' => 'AnxhrAnchorDateHelp'),
		'progress_pct' => array('type' => 'integer', 'label' => 'Progress', 'enabled' => 1, 'position' => 40, 'notnull' => 1, 'default' => '0', 'visible' => 5, 'noteditable' => 1, 'css' => 'maxwidth50', 'isameasure' => 2),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'picto' => 'user', 'enabled' => 1, 'position' => 510, 'notnull' => 1, 'visible' => -2, 'csslist' => 'tdoverflowmax150'),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'picto' => 'user', 'enabled' => 1, 'position' => 511, 'notnull' => -1, 'visible' => -2, 'csslist' => 'tdoverflowmax150'),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => -1, 'visible' => -2),
		'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => 1, 'position' => 2000, 'notnull' => 1, 'default' => '0', 'visible' => 5, 'index' => 1, 'arrayofkeyval' => array(0 => 'AnxhrStatusOpen', 1 => 'AnxhrStatusDone', 9 => 'Canceled')),
	);

	/** @var string */
	public $ref;
	/** @var int */
	public $fk_user;
	/** @var ?int */
	public $fk_template;
	/** @var string */
	public $checklist_type;
	/** @var int|string */
	public $anchor_date;
	/** @var int */
	public $progress_pct;
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
	 * Create checklist. If fk_template is set, the items of the template are copied.
	 * For offboarding checklists, one return item is added for each handover still out.
	 *
	 * @param  User     $user      User that creates
	 * @param  int<0,1> $notrigger 1=disable triggers
	 * @return int                 Id if OK, <0 if KO
	 */
	public function create(User $user, $notrigger = 0)
	{
		$error = 0;

		if (empty($this->ref) || preg_match('/^\(?PROV/i', $this->ref)) {
			$this->ref = anxhrGetNextUserRef($this->db, $this->table_element, 'CL', (int) $this->fk_user);
		}
		$this->status = self::STATUS_OPEN;
		$this->progress_pct = 0;
		if (empty($this->anchor_date)) {
			$this->anchor_date = dol_now();
		}

		$tpl = null;
		if ($this->fk_template > 0 && is_object($this->templateloaded) && $this->templateloaded->id == $this->fk_template) {
			$tpl = $this->templateloaded;
		} elseif ($this->fk_template > 0) {
			$tpl = new HrChecklistTemplate($this->db);
			if ($tpl->fetch((int) $this->fk_template) <= 0) {
				$this->error = 'ErrorRecordNotFound';
				$this->errors[] = $this->error;
				return -1;
			}
			$this->checklist_type = $tpl->template_type;
		}
		if (empty($this->checklist_type)) {
			$this->checklist_type = 'onboarding';
		}

		$this->db->begin();
		if ($this->createCommon($user, 1) <= 0) {
			$error++;
		}
		if (!$error && $tpl !== null && $this->createItemsFromTemplate($user, $tpl) < 0) {
			$error++;
		}
		if (!$error && $this->checklist_type == 'offboarding' && $this->createReturnItemsForOpenHandovers($user) < 0) {
			$error++;
		}
		if (!$error && $this->recomputeProgress($user) < 0) {
			$error++;
		}
		if (!$error && !$notrigger && $this->call_trigger('ANXHR_CHECKLIST_CREATE', $user) < 0) {
			$error++;
		}
		if ($error) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();
		return $this->id;
	}

	/**
	 * Instantiate a checklist from a template for an employee
	 *
	 * @param  User                $user       User acting (creator)
	 * @param  HrChecklistTemplate $tpl        Template (loaded)
	 * @param  int                 $fk_user    Employee id
	 * @param  int                 $anchorDate Anchor date (timestamp) for relative due dates
	 * @return int                             Id of checklist if OK, <0 if KO
	 */
	public function instantiateFromTemplate(User $user, HrChecklistTemplate $tpl, $fk_user, $anchorDate)
	{
		$this->fk_user = (int) $fk_user;
		$this->fk_template = (int) $tpl->id;
		$this->checklist_type = $tpl->template_type;
		$this->anchor_date = $anchorDate;
		$this->templateloaded = $tpl;
		return $this->create($user);
	}

	/**
	 * Copy template items into this checklist
	 *
	 * @param  User                $user User acting
	 * @param  HrChecklistTemplate $tpl  Template with lines loaded
	 * @return int                       <0 if KO, number of items created if OK
	 */
	protected function createItemsFromTemplate(User $user, HrChecklistTemplate $tpl)
	{
		if (empty($tpl->lines)) {
			$tpl->fetchLines();
		}
		$employee = new User($this->db);
		$employee->fetch((int) $this->fk_user);

		$nb = 0;
		foreach ($tpl->lines as $tplline) {
			$item = new HrChecklistItem($this->db);
			$item->position = $tplline->position;
			$item->label = $tplline->label;
			$item->responsible_role = $tplline->responsible_role;
			$item->fk_user_responsible = $this->resolveResponsible($tplline->responsible_role, $employee, $user);
			$item->date_due = dol_time_plus_duree($this->anchor_date, (int) $tplline->offset_days, 'd');
			$item->creates_handover = (int) $tplline->creates_handover;
			$item->handover_category = $tplline->handover_category;
			if ($this->addItem($user, $item) < 0) {
				return -1;
			}
			$nb++;
		}
		return $nb;
	}

	/**
	 * For offboarding: add one item per handover still out (return item, or revoke item for access rights)
	 *
	 * @param  User $user User acting
	 * @return int        <0 if KO, number of items created if OK
	 */
	protected function createReturnItemsForOpenHandovers(User $user)
	{
		global $langs;

		require_once __DIR__.'/hrhandover.class.php';
		$langs->load('anxhr_hr@anxhr');

		$handover = new HrHandover($this->db);
		$handovers = $handover->fetchOpenForUser((int) $this->fk_user);
		if (!is_array($handovers)) {
			$this->error = $handover->error;
			return -1;
		}
		$itdefault = getDolGlobalInt('ANXHR_IT_DEFAULT_USER');
		$nb = 0;
		foreach ($handovers as $ho) {
			$item = new HrChecklistItem($this->db);
			$item->position = 1000 + $nb;
			if ($ho->category == 'ACCESS') {
				$item->label = $langs->transnoentitiesnoconv('AnxhrItemRevokeAccess', trim($ho->system_name.' '.$ho->role_name.' '.$ho->label));
			} else {
				$item->label = $langs->transnoentitiesnoconv('AnxhrItemReturnHandover', $ho->ref.' '.$ho->label);
			}
			$item->responsible_role = 'it';
			$item->fk_user_responsible = ($itdefault > 0 ? $itdefault : (int) $user->id);
			$item->date_due = $this->anchor_date;
			$item->fk_handover = $ho->id;
			if ($this->addItem($user, $item) < 0) {
				return -1;
			}
			$nb++;
		}
		return $nb;
	}

	/**
	 * Resolve responsible user of an item from its role
	 * hr -> ANXHR_HR_DEFAULT_USER else creator, it -> ANXHR_IT_DEFAULT_USER else creator,
	 * supervisor -> supervisor of employee else creator, employee -> employee.
	 *
	 * @param  string $role     Role code
	 * @param  User   $employee Employee
	 * @param  User   $creator  User creating the checklist
	 * @return int              Id of responsible user
	 */
	public function resolveResponsible($role, User $employee, User $creator)
	{
		switch ($role) {
			case 'hr':
				$id = getDolGlobalInt('ANXHR_HR_DEFAULT_USER');
				return ($id > 0 ? $id : (int) $creator->id);
			case 'it':
				$id = getDolGlobalInt('ANXHR_IT_DEFAULT_USER');
				return ($id > 0 ? $id : (int) $creator->id);
			case 'supervisor':
				return ($employee->fk_user > 0 ? (int) $employee->fk_user : (int) $creator->id);
			case 'employee':
				return ($employee->id > 0 ? (int) $employee->id : (int) $creator->id);
			default:
				return (int) $creator->id;
		}
	}

	/**
	 * Add an item to this checklist
	 *
	 * @param  User            $user User acting
	 * @param  HrChecklistItem $item Item
	 * @return int                   Id of item if OK, <0 if KO
	 */
	public function addItem(User $user, HrChecklistItem $item)
	{
		$item->fk_checklist = $this->id;
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
		$sql = "SELECT MAX(position) as maxpos FROM ".$this->db->prefix().$this->table_element_line." WHERE fk_checklist = ".((int) $this->id);
		$resql = $this->db->query($sql);
		if (!$resql) {
			return 10;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		return ((int) $obj->maxpos) + 10;
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
	 * Load items ordered by position
	 *
	 * @return int <0 if KO, >0 if OK
	 */
	public function fetchLines()
	{
		$this->lines = array();
		$line = new HrChecklistItem($this->db);
		$sql = "SELECT ".$line->getFieldList('l')." FROM ".$this->db->prefix().$this->table_element_line." as l";
		$sql .= " WHERE l.fk_checklist = ".((int) $this->id);
		$sql .= $this->db->order('l.position,l.rowid', 'ASC,ASC');
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		while ($obj = $this->db->fetch_object($resql)) {
			$newline = new HrChecklistItem($this->db);
			$newline->setVarsFromFetchObj($obj);
			$this->lines[] = $newline;
		}
		$this->db->free($resql);
		return 1;
	}

	/**
	 * Return true if an open or done checklist of a type already exists for an employee
	 *
	 * @param  int    $fk_user Employee id
	 * @param  string $type    Checklist type
	 * @return bool
	 */
	public function existsForUser($fk_user, $type)
	{
		$sql = "SELECT COUNT(rowid) as nb FROM ".$this->db->prefix().$this->table_element;
		$sql .= " WHERE fk_user = ".((int) $fk_user)." AND checklist_type = '".$this->db->escape($type)."'";
		$sql .= " AND status <> ".self::STATUS_CANCELED." AND entity IN (".getEntity($this->element).")";
		$resql = $this->db->query($sql);
		if (!$resql) {
			return false;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		return ((int) $obj->nb > 0);
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
		if ($result > 0 && !$notrigger && $this->call_trigger('ANXHR_CHECKLIST_MODIFY', $user) < 0) {
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
	 * Delete object and items
	 *
	 * @param  User     $user      User that deletes
	 * @param  int<0,1> $notrigger 1=disable triggers
	 * @return int                 <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = 0)
	{
		if (!$notrigger && $this->call_trigger('ANXHR_CHECKLIST_DELETE', $user) < 0) {
			return -1;
		}
		return $this->deleteCommon($user, 1);
	}

	/**
	 * Set an item as done
	 *
	 * @param  User $user   User acting
	 * @param  int  $itemid Item id
	 * @return int          <0 if KO, >0 if OK
	 */
	public function setItemDone(User $user, $itemid)
	{
		return $this->setItemStatus($user, $itemid, HrChecklistItem::STATUS_DONE);
	}

	/**
	 * Change status of an item (open, done, skipped) and recompute progress
	 *
	 * @param  User $user   User acting
	 * @param  int  $itemid Item id
	 * @param  int  $status New status
	 * @return int          <0 if KO, >0 if OK
	 */
	public function setItemStatus(User $user, $itemid, $status)
	{
		$status = (int) $status;
		if (!in_array($status, array(HrChecklistItem::STATUS_OPEN, HrChecklistItem::STATUS_DONE, HrChecklistItem::STATUS_SKIPPED))) {
			$this->error = 'ErrorBadValueForParameter';
			return -1;
		}
		$sql = "UPDATE ".$this->db->prefix().$this->table_element_line." SET status = ".$status;
		if ($status == HrChecklistItem::STATUS_OPEN) {
			$sql .= ", date_done = NULL, fk_user_done = NULL";
		} else {
			$sql .= ", date_done = '".$this->db->idate(dol_now())."', fk_user_done = ".((int) $user->id);
		}
		$sql .= " WHERE rowid = ".((int) $itemid)." AND fk_checklist = ".((int) $this->id);

		$this->db->begin();
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		$this->context['anxhr_item_id'] = (int) $itemid;
		$this->context['anxhr_item_status'] = $status;
		$triggercode = ($status == HrChecklistItem::STATUS_DONE ? 'ANXHR_CHECKLIST_ITEM_DONE' : ($status == HrChecklistItem::STATUS_SKIPPED ? 'ANXHR_CHECKLIST_ITEM_SKIP' : 'ANXHR_CHECKLIST_ITEM_REOPEN'));
		if ($this->call_trigger($triggercode, $user) < 0 || $this->recomputeProgress($user) < 0) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();
		return 1;
	}

	/**
	 * Link a handover to an item and set the item as done
	 *
	 * @param  User $user       User acting
	 * @param  int  $itemid     Item id
	 * @param  int  $handoverid Handover id
	 * @return int              <0 if KO, >0 if OK
	 */
	public function linkItemHandover(User $user, $itemid, $handoverid)
	{
		$sql = "UPDATE ".$this->db->prefix().$this->table_element_line." SET fk_handover = ".((int) $handoverid);
		$sql .= " WHERE rowid = ".((int) $itemid)." AND fk_checklist = ".((int) $this->id);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		return $this->setItemDone($user, $itemid);
	}

	/**
	 * Recompute progress (done + skipped / total) and set checklist status open/done accordingly.
	 * Canceled checklists keep their status.
	 *
	 * @param  User|null $user User acting (for fk_user_modif)
	 * @return int             Progress in percent, <0 if KO
	 */
	public function recomputeProgress($user = null)
	{
		$sql = "SELECT COUNT(rowid) as nbtotal, SUM(CASE WHEN status IN (".HrChecklistItem::STATUS_DONE.",".HrChecklistItem::STATUS_SKIPPED.") THEN 1 ELSE 0 END) as nbclosed";
		$sql .= " FROM ".$this->db->prefix().$this->table_element_line." WHERE fk_checklist = ".((int) $this->id);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$obj = $this->db->fetch_object($resql);
		$this->db->free($resql);
		$total = (int) $obj->nbtotal;
		$closed = (int) $obj->nbclosed;
		$pct = ($total > 0 ? (int) floor(100 * $closed / $total) : 0);

		$sql = "UPDATE ".$this->db->prefix().$this->table_element." SET progress_pct = ".$pct;
		$sql .= ", status = CASE WHEN status = ".self::STATUS_CANCELED." THEN status WHEN ".(($total > 0 && $closed >= $total) ? 1 : 0)." = 1 THEN ".self::STATUS_DONE." ELSE ".self::STATUS_OPEN." END";
		if (is_object($user) && $user->id > 0) {
			$sql .= ", fk_user_modif = ".((int) $user->id);
		}
		$sql .= " WHERE rowid = ".((int) $this->id);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$this->progress_pct = $pct;
		if ($this->status != self::STATUS_CANCELED) {
			$this->status = (($total > 0 && $closed >= $total) ? self::STATUS_DONE : self::STATUS_OPEN);
		}
		return $pct;
	}

	/**
	 * Cancel checklist
	 *
	 * @param  User $user User acting
	 * @return int        <0 if KO, >0 if OK
	 */
	public function cancel(User $user)
	{
		return $this->setStatusCommon($user, self::STATUS_CANCELED, 0, 'ANXHR_CHECKLIST_CANCEL');
	}

	/**
	 * Reopen a canceled checklist
	 *
	 * @param  User $user User acting
	 * @return int        <0 if KO, >0 if OK
	 */
	public function reopen(User $user)
	{
		$result = $this->setStatusCommon($user, self::STATUS_OPEN, 0, 'ANXHR_CHECKLIST_REOPEN');
		if ($result > 0) {
			$this->recomputeProgress($user);
		}
		return $result;
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
		$datas['picto'] = img_picto('', $this->picto).' <u>'.$langs->trans("AnxhrChecklist").'</u> '.$this->getLibStatut(5);
		$datas['ref'] = '<br><b>'.$langs->trans('Ref').':</b> '.dol_escape_htmltag($this->ref);
		if (!empty($this->checklist_type) && isset($this->fields['checklist_type']['arrayofkeyval'][$this->checklist_type])) {
			$datas['type'] = '<br><b>'.$langs->trans('Type').':</b> '.dol_escape_htmltag($this->fields['checklist_type']['arrayofkeyval'][$this->checklist_type]);
		}
		$datas['progress'] = '<br><b>'.$langs->trans('Progress').':</b> '.((int) $this->progress_pct).' %';
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
		return anxhrBuildNomUrl($this, '/anxhr/checklist_card.php', $this->ref, $withpicto, $option, $notooltip, $morecss, $save_lastsearch_value);
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
			self::STATUS_OPEN => $langs->transnoentitiesnoconv('AnxhrStatusOpen'),
			self::STATUS_DONE => $langs->transnoentitiesnoconv('AnxhrStatusDone'),
			self::STATUS_CANCELED => $langs->transnoentitiesnoconv('Canceled'),
		);
		$types = array(self::STATUS_OPEN => 'status1', self::STATUS_DONE => 'status4', self::STATUS_CANCELED => 'status9');
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
		$this->ref = 'CL-1-1';
		$this->fk_user = 1;
		$this->checklist_type = 'onboarding';
		$this->anchor_date = dol_now();
		$this->progress_pct = 50;
		$this->status = self::STATUS_OPEN;
		return $ret;
	}
}


/**
 * Class HrChecklistItem (line of a checklist)
 */
class HrChecklistItem extends CommonObjectLine
{
	/** @var string */
	public $module = 'anxhr';
	/** @var string */
	public $element = 'anxhr_checklist_item';
	/** @var string */
	public $table_element = 'anxhr_checklist_item';
	/** @var string */
	public $parent_element = 'anxhr_checklist';
	/** @var string */
	public $fk_parent_attribute = 'fk_checklist';
	/** @var int<0,1> */
	public $isextrafieldmanaged = 0;
	/** @var int<0,1>|string */
	public $ismultientitymanaged = 0;

	const STATUS_OPEN = 0;
	const STATUS_DONE = 1;
	const STATUS_SKIPPED = 2;

	/**
	 * @var array<string,array<string,mixed>> Fields definition
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1),
		'fk_checklist' => array('type' => 'integer', 'label' => 'AnxhrChecklist', 'enabled' => 1, 'position' => 5, 'notnull' => 1, 'visible' => 0),
		'position' => array('type' => 'integer', 'label' => 'Position', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'default' => '0', 'visible' => 0),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1),
		'responsible_role' => array('type' => 'varchar(16)', 'label' => 'AnxhrResponsibleRole', 'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => 1, 'arrayofkeyval' => array('hr' => 'AnxhrRoleHr', 'it' => 'AnxhrRoleIt', 'supervisor' => 'AnxhrRoleSupervisor', 'employee' => 'AnxhrRoleEmployee')),
		'fk_user_responsible' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'AnxhrResponsible', 'enabled' => 1, 'position' => 35, 'notnull' => 0, 'visible' => 1),
		'date_due' => array('type' => 'date', 'label' => 'AnxhrDateDue', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1),
		'date_done' => array('type' => 'datetime', 'label' => 'AnxhrDateDone', 'enabled' => 1, 'position' => 45, 'notnull' => 0, 'visible' => 1),
		'fk_user_done' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'AnxhrDoneBy', 'enabled' => 1, 'position' => 46, 'notnull' => 0, 'visible' => 1),
		'fk_handover' => array('type' => 'integer', 'label' => 'AnxhrHandover', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1),
		'creates_handover' => array('type' => 'boolean', 'label' => 'AnxhrCreatesHandover', 'enabled' => 1, 'position' => 55, 'notnull' => 1, 'default' => '0', 'visible' => 0),
		'handover_category' => array('type' => 'varchar(32)', 'label' => 'AnxhrHandoverCategory', 'enabled' => 1, 'position' => 56, 'notnull' => 0, 'visible' => 0),
		'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => 1, 'position' => 60, 'notnull' => 1, 'default' => '0', 'visible' => 1),
		'note' => array('type' => 'text', 'label' => 'Note', 'enabled' => 1, 'position' => 70, 'notnull' => 0, 'visible' => 1),
	);

	/** @var int */
	public $fk_checklist;
	/** @var int */
	public $position;
	/** @var string */
	public $label;
	/** @var string */
	public $responsible_role;
	/** @var ?int */
	public $fk_user_responsible;
	/** @var int|string */
	public $date_due;
	/** @var int|string */
	public $date_done;
	/** @var ?int */
	public $fk_user_done;
	/** @var ?int */
	public $fk_handover;
	/** @var int */
	public $creates_handover;
	/** @var string */
	public $handover_category;
	/** @var int */
	public $status;
	/** @var string */
	public $note;

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
	 * @param  int<0,1> $notrigger Not used (no trigger on items)
	 * @return int                 Id if OK, <0 if KO
	 */
	public function create(User $user, $notrigger = 1)
	{
		if ($this->status === null || $this->status === '') {
			$this->status = self::STATUS_OPEN;
		}
		$this->creates_handover = empty($this->creates_handover) ? 0 : 1;
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
	 * @param  int<0,1> $notrigger Not used
	 * @return int                 <0 if KO, >0 if OK
	 */
	public function update(User $user, $notrigger = 1)
	{
		return $this->updateCommon($user, 1);
	}

	/**
	 * Delete item
	 *
	 * @param  User     $user      User that deletes
	 * @param  int<0,1> $notrigger Not used
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
	 * Return true if the due date is passed and item is still open
	 *
	 * @return bool
	 */
	public function isLate()
	{
		return ($this->status == self::STATUS_OPEN && !empty($this->date_due) && anxhrDaysFromToday($this->date_due) < 0);
	}

	/**
	 * Return status badge of item
	 *
	 * @param  int $mode Display mode
	 * @return string
	 */
	public function getLibStatut($mode = 5)
	{
		global $langs;

		$langs->load('anxhr_hr@anxhr');
		if ($this->status == self::STATUS_DONE) {
			return dolGetStatus($langs->transnoentitiesnoconv('AnxhrStatusDone'), '', '', 'status4', $mode);
		}
		if ($this->status == self::STATUS_SKIPPED) {
			return dolGetStatus($langs->transnoentitiesnoconv('AnxhrStatusSkipped'), '', '', 'status5', $mode);
		}
		if ($this->isLate()) {
			return dolGetStatus($langs->transnoentitiesnoconv('AnxhrOverdue'), '', '', 'status8', $mode);
		}
		return dolGetStatus($langs->transnoentitiesnoconv('AnxhrStatusOpen'), '', '', 'status1', $mode);
	}
}
