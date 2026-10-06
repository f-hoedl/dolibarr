<?php
/* Copyright (C) 2026 ANX
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
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
 * \file        htdocs/custom/anxhr/class/vaultdoc.class.php
 * \ingroup     anxhr
 * \brief       Document of the HR vault (payslip, contract, certificate, time sheet...). The file is stored encrypted.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once __DIR__.'/../lib/anxhr_hr.lib.php';
require_once __DIR__.'/../lib/anxhr_vault.lib.php';

/**
 * Class VaultDoc
 */
class VaultDoc extends CommonObject
{
	/** @var string */
	public $module = 'anxhr';
	/** @var string */
	public $element = 'anxhr_vaultdoc';
	/** @var string */
	public $TRIGGER_PREFIX = 'ANXHR_VAULTDOC';
	/** @var string */
	public $table_element = 'anxhr_vault_doc';
	/** @var string */
	public $picto = 'fa-shield-alt';
	/** @var int<0,1> */
	public $isextrafieldmanaged = 0;
	/** @var int<0,1>|string */
	public $ismultientitymanaged = 1;

	const STATUS_ACTIVE = 1;
	const STATUS_DELETED = 9;

	/** Access log actions */
	const LOG_ACTIONS = array('upload', 'view', 'download', 'delete', 'denied');

	/**
	 * @var array<string,array<string,mixed>> Fields definition
	 */
	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'entity' => array('type' => 'integer', 'label' => 'Entity', 'default' => '1', 'enabled' => 1, 'visible' => 0, 'notnull' => 1, 'position' => 5, 'index' => 1),
		'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 4, 'noteditable' => 1, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1, 'csslist' => 'nowraponall'),
		'fk_user' => array('type' => 'integer:User:user/class/user.class.php:0:(t.employee:=:1)', 'label' => 'AnxhrEmployee', 'picto' => 'user', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'foreignkey' => 'user.rowid', 'css' => 'minwidth300 maxwidth500', 'csslist' => 'tdoverflowmax150'),
		'category' => array('type' => 'varchar(32)', 'label' => 'Category', 'enabled' => 1, 'position' => 25, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'default' => 'other', 'arrayofkeyval' => array('payslip' => 'AnxhrVaultCatPayslip', 'contract' => 'AnxhrVaultCatContract', 'certificate' => 'AnxhrVaultCatCertificate', 'timesheet' => 'AnxhrVaultCatTimesheet', 'sickness_certificate' => 'AnxhrVaultCatSicknessCertificate', 'other' => 'AnxhrVaultCatOther'), 'css' => 'minwidth200'),
		'period' => array('type' => 'varchar(7)', 'label' => 'AnxhrVaultPeriod', 'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => 1, 'index' => 1, 'css' => 'maxwidth100'),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 35, 'notnull' => 0, 'visible' => 1, 'searchall' => 1, 'css' => 'minwidth300'),
		'filename_original' => array('type' => 'varchar(255)', 'label' => 'AnxhrVaultFileName', 'enabled' => 1, 'position' => 40, 'notnull' => 1, 'visible' => 1, 'searchall' => 1),
		'filename_stored' => array('type' => 'varchar(255)', 'label' => 'AnxhrVaultStoredName', 'enabled' => 1, 'position' => 41, 'notnull' => 1, 'visible' => 0),
		'filesize' => array('type' => 'integer', 'label' => 'Size', 'enabled' => 1, 'position' => 45, 'notnull' => 1, 'default' => '0', 'visible' => 1),
		'mimetype' => array('type' => 'varchar(128)', 'label' => 'AnxhrVaultMimeType', 'enabled' => 1, 'position' => 46, 'notnull' => 0, 'visible' => 0),
		'sha256' => array('type' => 'varchar(64)', 'label' => 'AnxhrVaultSha256', 'enabled' => 1, 'position' => 47, 'notnull' => 1, 'visible' => 0),
		'encrypted' => array('type' => 'integer', 'label' => 'AnxhrVaultEncrypted', 'enabled' => 1, 'position' => 48, 'notnull' => 1, 'default' => '1', 'visible' => 0),
		'uploaded_by' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'AnxhrVaultUploadedBy', 'picto' => 'user', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1),
		'date_upload' => array('type' => 'datetime', 'label' => 'AnxhrVaultUploadedOn', 'enabled' => 1, 'position' => 51, 'notnull' => 0, 'visible' => 1),
		'visible_to_employee' => array('type' => 'integer', 'label' => 'AnxhrVaultVisibleToEmployee', 'enabled' => 1, 'position' => 55, 'notnull' => 1, 'default' => '1', 'visible' => 1),
		'first_viewed_at' => array('type' => 'datetime', 'label' => 'AnxhrVaultFirstViewedAt', 'enabled' => 1, 'position' => 56, 'notnull' => 0, 'visible' => 1),
		'note_private' => array('type' => 'text', 'label' => 'NotePrivate', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => 0),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
		'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'picto' => 'user', 'enabled' => 1, 'position' => 510, 'notnull' => 1, 'visible' => -2),
		'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'picto' => 'user', 'enabled' => 1, 'position' => 511, 'notnull' => -1, 'visible' => -2),
		'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'position' => 1000, 'notnull' => -1, 'visible' => -2),
		'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => 1, 'position' => 2000, 'notnull' => 1, 'default' => '1', 'visible' => 1, 'index' => 1, 'arrayofkeyval' => array(1 => 'AnxhrVaultStatusActive', 9 => 'AnxhrVaultStatusDeleted')),
	);

	/** @var string */
	public $ref;
	/** @var int */
	public $fk_user;
	/** @var string */
	public $category;
	/** @var ?string */
	public $period;
	/** @var string */
	public $label;
	/** @var string */
	public $filename_original;
	/** @var string */
	public $filename_stored;
	/** @var int */
	public $filesize;
	/** @var string */
	public $mimetype;
	/** @var string */
	public $sha256;
	/** @var int */
	public $encrypted;
	/** @var ?int */
	public $uploaded_by;
	/** @var int|string */
	public $date_upload;
	/** @var int */
	public $visible_to_employee;
	/** @var int|string|null */
	public $first_viewed_at;
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
			$langs->load('anxhr_vault@anxhr');
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
	 * Create object into database. Use storeUploadedFile() or createFromFile() to create a document with its file.
	 *
	 * @param  User     $user      User that creates
	 * @param  int<0,1> $notrigger 1=disable triggers
	 * @return int                 Id if OK, <0 if KO
	 */
	public function create(User $user, $notrigger = 0)
	{
		if (empty($this->ref) || preg_match('/^\(?PROV/i', $this->ref)) {
			$this->ref = anxhrGetNextUserRef($this->db, $this->table_element, 'VD', (int) $this->fk_user);
		}
		if (empty($this->status)) {
			$this->status = self::STATUS_ACTIVE;
		}
		$this->visible_to_employee = empty($this->visible_to_employee) ? 0 : 1;
		$this->encrypted = 1;

		$this->db->begin();
		$result = $this->createCommon($user, 1);
		if ($result <= 0) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();

		// The trigger feeds the Notification module ("a new document is available", never the document itself).
		// It runs after the commit on purpose: a mail failure must not lose a stored payslip (bulk import).
		if (!$notrigger && $this->shouldNotify()) {
			global $langs;
			if (is_object($langs)) {
				$langs->load('anxhr_vault@anxhr');
			}
			if ($this->call_trigger('ANXHR_VAULTDOC_CREATE', $user) < 0) {
				dol_syslog(__METHOD__.' trigger ANXHR_VAULTDOC_CREATE failed for '.$this->ref.': '.$this->error.' '.implode(', ', $this->errors), LOG_WARNING);
				$this->errors = array();
				$this->error = '';
			}
		}
		return $this->id;
	}

	/**
	 * Return true if the creation must fire ANXHR_VAULTDOC_CREATE (used by the Notification module):
	 * only documents visible to the employee, of a category listed in ANXHR_VAULT_NOTIFY_CATEGORIES (default payslip).
	 *
	 * @return bool
	 */
	protected function shouldNotify()
	{
		if (empty($this->visible_to_employee)) {
			return false;
		}
		$cats = array_map('trim', explode(',', getDolGlobalString('ANXHR_VAULT_NOTIFY_CATEGORIES', 'payslip')));
		return in_array($this->category, $cats, true);
	}

	/**
	 * Load object (restricted to the entities visible for the current context)
	 *
	 * @param  int    $id  Id
	 * @param  string $ref Ref
	 * @return int         <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id, $ref = null)
	{
		return $this->fetchCommon($id, $ref, ' AND t.entity IN ('.getEntity($this->element).')');
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
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Hard delete of the record is not allowed (audit). Use deleteWithFile().
	 *
	 * @param  User     $user      User that deletes
	 * @param  int<0,1> $notrigger 1=disable triggers
	 * @return int                 <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = 0)
	{
		return $this->deleteWithFile($user, $notrigger);
	}

	/**
	 * Fetch the active documents of an employee (one query), latest period first
	 *
	 * @param  int    $fk_user          Employee id
	 * @param  string $category         Category filter ('' = all)
	 * @param  bool   $onlyvisible      True to return only documents visible to the employee
	 * @return VaultDoc[]|int           Array or <0 if KO
	 */
	public function fetchAllForUser($fk_user, $category = '', $onlyvisible = false)
	{
		$records = array();
		$sql = "SELECT ".$this->getFieldList('t')." FROM ".$this->db->prefix().$this->table_element." as t";
		$sql .= " WHERE t.fk_user = ".((int) $fk_user)." AND t.status = ".self::STATUS_ACTIVE;
		$sql .= " AND t.entity IN (".getEntity($this->element).")";
		if ($category !== '') {
			$sql .= " AND t.category = '".$this->db->escape($category)."'";
		}
		if ($onlyvisible) {
			$sql .= " AND t.visible_to_employee = 1";
		}
		$sql .= " ORDER BY t.period DESC, t.date_upload DESC, t.rowid DESC";
		$sql .= $this->db->plimit(1000);
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
	 * Return true if the viewer can read (download) this document.
	 * Employee: own document, visible to employee, right vault/own. HR: right vault/admin.
	 * Supervisors never get access through the hierarchy.
	 *
	 * @param  User $viewer User
	 * @return bool
	 */
	public function canRead(User $viewer)
	{
		if (empty($this->id) || (int) $this->status !== self::STATUS_ACTIVE) {
			return false;
		}
		if ($viewer->hasRight('anxhr', 'vault', 'admin')) {
			return true;
		}
		return ((int) $viewer->id === (int) $this->fk_user && !empty($this->visible_to_employee) && $viewer->hasRight('anxhr', 'vault', 'own'));
	}

	/**
	 * Return true if the user can upload documents into the vault
	 *
	 * @param  User $actor User
	 * @return bool
	 */
	public function canUpload(User $actor)
	{
		return ($actor->hasRight('anxhr', 'vault', 'upload') || $actor->hasRight('anxhr', 'vault', 'admin'));
	}

	/**
	 * Return true if the user can delete documents of the vault
	 *
	 * @param  User $actor User
	 * @return bool
	 */
	public function canDelete(User $actor)
	{
		return (bool) $actor->hasRight('anxhr', 'vault', 'admin');
	}

	/**
	 * Write a line into the access log of this document
	 *
	 * @param  User   $actor  User
	 * @param  string $action upload, view, download, delete or denied
	 * @return int            >0 if OK, <0 if KO
	 */
	public function logAccess(User $actor, $action)
	{
		return self::writeAccessLog($this->db, (int) $actor->id, (int) $this->id, $action, (int) ($this->entity ? $this->entity : 0));
	}

	/**
	 * Write a line into the access log (also usable when the document could not be loaded)
	 *
	 * @param  DoliDB $db     Database handler
	 * @param  int    $userid User id
	 * @param  int    $docid  Document id (requested id, may not exist)
	 * @param  string $action upload, view, download, delete or denied
	 * @param  int    $entity Entity (0 = current)
	 * @return int            >0 if OK, <0 if KO
	 */
	public static function writeAccessLog($db, $userid, $docid, $action, $entity = 0)
	{
		global $conf;

		if (!in_array($action, self::LOG_ACTIONS, true)) {
			return -1;
		}
		$sql = "INSERT INTO ".$db->prefix()."anxhr_vault_access_log (entity, fk_doc, fk_user, action, ip, user_agent, datec)";
		$sql .= " VALUES (".((int) ($entity > 0 ? $entity : $conf->entity)).", ".($docid > 0 ? (int) $docid : "NULL").", ".($userid > 0 ? (int) $userid : "NULL").",";
		$sql .= " '".$db->escape($action)."', '".$db->escape(anxhrVaultClientIp())."', '".$db->escape(anxhrVaultUserAgent())."', '".$db->idate(dol_now())."')";
		if (!$db->query($sql)) {
			dol_syslog(__METHOD__.' '.$db->lasterror(), LOG_ERR);
			return -1;
		}
		if ($action == 'denied') {
			dol_syslog('ANX HR vault: access denied to document '.((int) $docid).' for user '.((int) $userid), LOG_WARNING);
		}
		return 1;
	}

	/**
	 * Set first view date if not yet set (called when the employee opens his own document)
	 *
	 * @return int >0 if updated, 0 if already viewed, <0 if KO
	 */
	public function markViewed()
	{
		if (!empty($this->first_viewed_at)) {
			return 0;
		}
		$now = dol_now();
		$sql = "UPDATE ".$this->db->prefix().$this->table_element." SET first_viewed_at = '".$this->db->idate($now)."'";
		$sql .= " WHERE rowid = ".((int) $this->id)." AND first_viewed_at IS NULL";
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			return -1;
		}
		$this->first_viewed_at = $now;
		return $this->db->affected_rows($resql) > 0 ? 1 : 0;
	}

	/**
	 * Return the full path of the encrypted file, or '' if the stored name is invalid
	 *
	 * @return string
	 */
	public function getStoredPath()
	{
		if (!anxhrVaultIsValidStoredName($this->filename_stored) || (int) $this->fk_user <= 0) {
			return '';
		}
		return anxhrVaultRootDir((int) ($this->entity ? $this->entity : 1)).'/'.((int) $this->fk_user).'/'.$this->filename_stored;
	}

	/**
	 * Verify and decrypt the file to php://output. Headers must be sent by the caller before.
	 *
	 * @return int >0 if OK, <0 if KO (nothing was written)
	 */
	public function streamDecrypted()
	{
		$path = $this->getStoredPath();
		if ($path === '' || !is_file($path)) {
			$this->error = 'AnxhrVaultFileMissing';
			return -1;
		}
		$res = anxhrVaultDecryptToStream($path, anxhrVaultGetMasterKeys());
		if ($res === false) {
			$this->error = 'AnxhrVaultIntegrityError';
			dol_syslog(__METHOD__.' integrity check failed for document '.$this->id, LOG_ERR);
			return -2;
		}
		if ($res['sha256'] !== $this->sha256) {
			dol_syslog(__METHOD__.' sha256 of plaintext differs from database for document '.$this->id, LOG_ERR);
		}
		return 1;
	}

	/**
	 * Check that the file is ready to be decrypted (exists and HMAC valid), without output
	 *
	 * @return bool
	 */
	public function isFileValid()
	{
		$path = $this->getStoredPath();
		return ($path !== '' && is_file($path) && anxhrVaultVerifyFile($path, anxhrVaultGetMasterKeys()) !== false);
	}

	/**
	 * Validate, encrypt and store a plaintext file into the vault directory of $this->fk_user.
	 * On success, filename_stored, filesize, sha256, mimetype and filename_original are set.
	 *
	 * @param  string $path         Plaintext file path
	 * @param  string $originalName Original file name
	 * @param  bool   $isupload     True for a browser upload (global upload limit applies)
	 * @return int                  >0 if OK, <0 if KO (error key in $this->error)
	 */
	protected function encryptAndStore($path, $originalName, $isupload)
	{
		global $conf, $langs;

		$keys = anxhrVaultGetMasterKeys();
		if (empty($keys)) {
			$this->error = $langs->trans('AnxhrVaultNoCryptKey');
			return -1;
		}
		if ((int) $this->fk_user <= 0 || !array_key_exists((string) $this->category, anxhrVaultCategories())) {
			$this->error = $langs->trans('ErrorBadParameters');
			return -1;
		}
		if ($this->period !== null && $this->period !== '' && !anxhrVaultIsValidPeriod($this->period)) {
			$this->error = $langs->trans('AnxhrVaultErrorBadPeriod');
			return -1;
		}
		$originalName = dol_sanitizeFileName(basename((string) $originalName));
		$ext = anxhrVaultExtension($originalName);
		if ($originalName === '' || !in_array($ext, anxhrVaultAllowedExtensions(), true)) {
			$this->error = $langs->trans('AnxhrVaultErrorExtension', implode(', ', anxhrVaultAllowedExtensions()));
			return -1;
		}
		$size = @filesize($path);
		if ($size === false || $size <= 0) {
			$this->error = $langs->trans('AnxhrVaultErrorEmptyFile');
			return -1;
		}
		if ($size > anxhrVaultMaxSize($isupload)) {
			$this->error = $langs->trans('AnxhrVaultErrorTooLarge', dol_print_size(anxhrVaultMaxSize($isupload)));
			return -1;
		}
		if (!anxhrVaultCheckMagic($path, $ext)) {
			$this->error = $langs->trans('AnxhrVaultErrorContentMismatch');
			return -1;
		}
		$virus = dolCheckVirus($path, $originalName);
		if (!empty($virus)) {
			$this->error = $langs->trans('AnxhrVaultErrorVirus').' '.implode(', ', $virus);
			return -1;
		}

		$this->entity = (int) ($this->entity ? $this->entity : $conf->entity);
		$dir = anxhrVaultUserDir($this->entity, (int) $this->fk_user);
		if ($dir === '') {
			$this->error = $langs->trans('ErrorFailToCreateDir', 'vault');
			return -1;
		}
		$stored = anxhrVaultNewStoredName();
		$res = anxhrVaultEncryptFile($path, $dir.'/'.$stored, $keys[0]);
		if ($res === false) {
			$this->error = $langs->trans('AnxhrVaultErrorEncrypt');
			dol_syslog(__METHOD__.' encryption failed for user '.$this->fk_user, LOG_ERR);
			return -1;
		}
		$this->filename_original = dol_trunc($originalName, 250, 'right', 'UTF-8', 1);
		$this->filename_stored = $stored;
		$this->filesize = (int) $res['size'];
		$this->sha256 = $res['sha256'];
		$this->mimetype = anxhrVaultMimeFromExt($ext);
		$this->encrypted = 1;
		return 1;
	}

	/**
	 * Delete the encrypted file of the current stored name (no error if missing)
	 *
	 * @return bool
	 */
	protected function deleteStoredFile()
	{
		$path = $this->getStoredPath();
		if ($path === '' || !file_exists($path)) {
			return true;
		}
		return (bool) dol_delete_file($path, 1, 1, 1, null, false, 0);
	}

	/**
	 * Encrypt and store an uploaded file then create the record. fk_user, category, period, label and
	 * visible_to_employee must be set before. The temporary file is always deleted.
	 *
	 * @param  User   $actor        User uploading
	 * @param  string $tmpPath      Uploaded temporary file ($_FILES[..]['tmp_name'])
	 * @param  string $originalName Original file name
	 * @param  string $mimetype     Mime type sent by the browser (ignored, the type is derived from the checked extension)
	 * @return int                  Id if OK, <0 if KO (message in $this->error)
	 */
	public function storeUploadedFile(User $actor, $tmpPath, $originalName, $mimetype = '')
	{
		$result = $this->encryptAndStore($tmpPath, $originalName, true);
		if ($result > 0) {
			$result = $this->createWithStoredFile($actor);
		}
		// Never keep the plaintext temporary file
		if (is_file($tmpPath)) {
			@unlink($tmpPath);
		}
		return $result;
	}

	/**
	 * Create the record after encryptAndStore(). Removes the stored file if the record can not be created.
	 *
	 * @param  User $actor User
	 * @return int         Id or <0
	 */
	protected function createWithStoredFile(User $actor)
	{
		$this->uploaded_by = (int) $actor->id;
		$this->date_upload = dol_now();
		$this->first_viewed_at = null;
		$this->status = self::STATUS_ACTIVE;
		$id = $this->create($actor);
		if ($id <= 0) {
			// The ref may have been taken by a concurrent upload: retry once with a new ref
			$this->ref = '';
			$this->id = 0;
			$id = $this->create($actor);
		}
		if ($id <= 0) {
			$this->deleteStoredFile();
			dol_syslog(__METHOD__.' '.$this->error.' '.implode(',', $this->errors), LOG_ERR);
			return -1;
		}
		$this->logAccess($actor, 'upload');
		return $id;
	}

	/**
	 * Create (or replace) a vault document from a file of the server, for example a generated monthly time sheet.
	 * The source file is NOT deleted. If an active document with the same employee, category and period exists,
	 * its file is replaced (the record keeps its id and ref, first_viewed_at is reset).
	 *
	 * @param  DoliDB $db                Database handler
	 * @param  User   $actor             User acting
	 * @param  int    $fk_user           Employee id
	 * @param  string $category          Category code (payslip, contract, certificate, timesheet, sickness_certificate, other)
	 * @param  string $period            Period YYYY-MM or ''
	 * @param  string $label             Label
	 * @param  string $absolutePath      Plaintext file path
	 * @param  bool   $visibleToEmployee Visible to employee
	 * @return int                       Rowid of the document or <0 if KO (error in dol_syslog)
	 */
	public static function createFromFile($db, User $actor, int $fk_user, string $category, string $period, string $label, string $absolutePath, bool $visibleToEmployee = true): int
	{
		if (!is_file($absolutePath) || !is_readable($absolutePath)) {
			dol_syslog(__METHOD__.' file not readable '.$absolutePath, LOG_ERR);
			return -1;
		}
		$doc = new self($db);
		$existingid = 0;
		if ($period !== '') {
			$sql = "SELECT t.rowid FROM ".$db->prefix()."anxhr_vault_doc as t";
			$sql .= " WHERE t.fk_user = ".((int) $fk_user)." AND t.category = '".$db->escape($category)."'";
			$sql .= " AND t.period = '".$db->escape($period)."' AND t.status = ".self::STATUS_ACTIVE;
			$sql .= " AND t.entity IN (".getEntity('anxhr_vaultdoc').")";
			$sql .= " ORDER BY t.rowid DESC";
			$sql .= $db->plimit(1);
			$resql = $db->query($sql);
			if (!$resql) {
				dol_syslog(__METHOD__.' '.$db->lasterror(), LOG_ERR);
				return -1;
			}
			$obj = $db->fetch_object($resql);
			$existingid = $obj ? (int) $obj->rowid : 0;
			$db->free($resql);
		}

		if ($existingid > 0) {
			return $doc->replaceFile($actor, $existingid, $label, $absolutePath, $visibleToEmployee);
		}

		$doc->fk_user = $fk_user;
		$doc->category = $category;
		$doc->period = ($period !== '' ? $period : null);
		$doc->label = $label;
		$doc->visible_to_employee = $visibleToEmployee ? 1 : 0;
		if ($doc->encryptAndStore($absolutePath, basename($absolutePath), false) < 0) {
			dol_syslog(__METHOD__.' '.$doc->error, LOG_ERR);
			return -1;
		}
		return $doc->createWithStoredFile($actor);
	}

	/**
	 * Replace the file of an existing document (used by createFromFile)
	 *
	 * @param  User   $actor             User acting
	 * @param  int    $id                Document id
	 * @param  string $label             New label ('' keeps the current one)
	 * @param  string $absolutePath      Plaintext file path (not deleted)
	 * @param  bool   $visibleToEmployee Visible to employee
	 * @return int                       Document id or <0
	 */
	protected function replaceFile(User $actor, $id, $label, $absolutePath, $visibleToEmployee)
	{
		if ($this->fetch($id) <= 0) {
			dol_syslog(__METHOD__.' document '.$id.' not found', LOG_ERR);
			return -1;
		}
		$old = clone $this;
		if ($label !== '') {
			$this->label = $label;
		}
		$this->visible_to_employee = $visibleToEmployee ? 1 : 0;
		if ($this->encryptAndStore($absolutePath, basename($absolutePath), false) < 0) {
			dol_syslog(__METHOD__.' '.$this->error, LOG_ERR);
			return -1;
		}
		$this->uploaded_by = (int) $actor->id;
		$this->date_upload = dol_now();
		$this->first_viewed_at = null;
		if ($this->updateCommon($actor, 1) <= 0) {
			dol_syslog(__METHOD__.' '.$this->error, LOG_ERR);
			$this->deleteStoredFile();
			return -1;
		}
		// updateCommon does not write NULL into a datetime that was set: reset the read flag explicitly
		$this->db->query("UPDATE ".$this->db->prefix().$this->table_element." SET first_viewed_at = NULL WHERE rowid = ".((int) $this->id));
		$old->deleteStoredFile();
		$this->call_trigger('ANXHR_VAULTDOC_MODIFY', $actor);
		$this->logAccess($actor, 'upload');
		return (int) $this->id;
	}

	/**
	 * Delete the document: the encrypted file is removed (hard delete), the record is kept with status 9 for the audit.
	 * The retention cron purges records later.
	 *
	 * @param  User     $actor     User deleting
	 * @param  int<0,1> $notrigger 1=disable triggers
	 * @return int                 >0 if OK, <0 if KO
	 */
	public function deleteWithFile(User $actor, $notrigger = 0)
	{
		$this->db->begin();
		$sql = "UPDATE ".$this->db->prefix().$this->table_element." SET status = ".self::STATUS_DELETED.", fk_user_modif = ".((int) $actor->id);
		$sql .= " WHERE rowid = ".((int) $this->id);
		if (!$this->db->query($sql)) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}
		$this->status = self::STATUS_DELETED;
		if (!$notrigger && $this->call_trigger('ANXHR_VAULTDOC_DELETE', $actor) < 0) {
			$this->db->rollback();
			return -1;
		}
		$this->db->commit();
		if (!$this->deleteStoredFile()) {
			dol_syslog(__METHOD__.' could not delete file of document '.$this->id, LOG_ERR);
		}
		$this->logAccess($actor, 'delete');
		return 1;
	}

	/**
	 * Import a ZIP file of payslips. Each entry must be named <login|employee ref|user id>_<YYYY-MM or YYYYMM>.pdf.
	 * Users are matched by login first, then employee ref (ref_employee), then numeric id. Identical files already
	 * in the vault (same employee, period and SHA-256) are skipped.
	 *
	 * @param  DoliDB $db       Database handler
	 * @param  User   $actor    User importing
	 * @param  string $zipPath  Path of the ZIP file
	 * @param  bool   $visible  Documents visible to employees
	 * @return array{matched:array<int,array{file:string,user:string,period:string,id:int}>,unmatched:string[],skipped:string[],errors:string[]}|false
	 */
	public static function importPayslipZip($db, User $actor, $zipPath, $visible = true)
	{
		global $conf, $langs;

		$result = array('matched' => array(), 'unmatched' => array(), 'skipped' => array(), 'errors' => array());
		if (!class_exists('ZipArchive')) {
			$result['errors'][] = $langs->trans('AnxhrVaultErrorNoZip');
			return $result;
		}
		$zip = new ZipArchive();
		if ($zip->open($zipPath) !== true) {
			$result['errors'][] = $langs->trans('AnxhrVaultErrorBadZip');
			return $result;
		}
		$maxentries = 2000;
		$maxsize = anxhrVaultMaxSize(false);
		$entries = array();
		$keys = array();
		for ($i = 0; $i < min($zip->numFiles, $maxentries); $i++) {
			$stat = $zip->statIndex($i);
			if (!$stat || substr($stat['name'], -1) === '/') {
				continue;
			}
			$base = basename(str_replace('\\', '/', $stat['name']));
			if ($base === '' || $base[0] === '.' || strpos($stat['name'], '__MACOSX') === 0) {
				continue;
			}
			$reg = array();
			if (!preg_match('/^(.+)_((?:19|20)\d\d)-?(0[1-9]|1[0-2])\.pdf$/i', $base, $reg)) {
				$result['unmatched'][] = $base;
				continue;
			}
			if ((int) $stat['size'] > $maxsize || (int) $stat['size'] <= 0) {
				$result['errors'][] = $base.': '.$langs->trans('AnxhrVaultErrorTooLarge', dol_print_size($maxsize));
				continue;
			}
			$entries[] = array('index' => $i, 'name' => $base, 'key' => $reg[1], 'period' => $reg[2].'-'.$reg[3], 'size' => (int) $stat['size']);
			$keys[] = $reg[1];
		}
		if ($zip->numFiles > $maxentries) {
			$result['errors'][] = $langs->trans('AnxhrVaultErrorTooManyEntries', $maxentries);
		}

		$users = self::matchUsers($db, $keys);

		$tmpdir = anxhrVaultRootDir($conf->entity).'/_tmp';
		if (!is_dir($tmpdir) && dol_mkdir($tmpdir) < 0) {
			$zip->close();
			$result['errors'][] = $langs->trans('ErrorFailToCreateDir', '_tmp');
			return $result;
		}
		@chmod($tmpdir, 0700);
		foreach ($entries as $entry) {
			if (empty($users[$entry['key']])) {
				$result['unmatched'][] = $entry['name'];
				continue;
			}
			$u = $users[$entry['key']];
			$tmpfile = $tmpdir.'/'.bin2hex(random_bytes(16)).'.pdf';
			$ok = self::extractZipEntry($zip, $entry['index'], $tmpfile, $maxsize);
			if ($ok && self::isDuplicate($db, (int) $u['id'], 'payslip', $entry['period'], hash_file('sha256', $tmpfile))) {
				$result['skipped'][] = $entry['name'];
				@unlink($tmpfile);
				continue;
			}
			$doc = new self($db);
			$doc->fk_user = (int) $u['id'];
			$doc->category = 'payslip';
			$doc->period = $entry['period'];
			$doc->label = $langs->transnoentitiesnoconv('AnxhrVaultCatPayslip').' '.$entry['period'];
			$doc->visible_to_employee = $visible ? 1 : 0;
			$id = -1;
			if ($ok && $doc->encryptAndStore($tmpfile, $entry['name'], false) > 0) {
				$id = $doc->createWithStoredFile($actor);
			}
			@unlink($tmpfile);
			if ($id > 0) {
				$result['matched'][] = array('file' => $entry['name'], 'user' => $u['label'], 'period' => $entry['period'], 'id' => $id);
			} else {
				$result['errors'][] = $entry['name'].': '.($doc->error ? $doc->error : $langs->trans('Error'));
			}
		}
		$zip->close();
		return $result;
	}

	/**
	 * Extract one ZIP entry into a file with a size limit (protection against forged sizes / zip bombs)
	 *
	 * @param  ZipArchive $zip     Archive
	 * @param  int        $index   Entry index
	 * @param  string     $target  Target file
	 * @param  int        $maxsize Max size in bytes
	 * @return bool
	 */
	protected static function extractZipEntry($zip, $index, $target, $maxsize)
	{
		$in = $zip->getStream($zip->getNameIndex($index));
		if (!$in) {
			return false;
		}
		$out = @fopen($target, 'xb');
		if (!$out) {
			fclose($in);
			return false;
		}
		@chmod($target, 0600);
		$copied = stream_copy_to_stream($in, $out, $maxsize + 1);
		fclose($in);
		fclose($out);
		if ($copied === false || $copied > $maxsize) {
			@unlink($target);
			return false;
		}
		return true;
	}

	/**
	 * Return true if an identical active document exists
	 *
	 * @param  DoliDB $db       Database handler
	 * @param  int    $fk_user  Employee
	 * @param  string $category Category
	 * @param  string $period   Period
	 * @param  string $sha256   SHA-256 of plaintext
	 * @return bool
	 */
	protected static function isDuplicate($db, $fk_user, $category, $period, $sha256)
	{
		$sql = "SELECT COUNT(t.rowid) as nb FROM ".$db->prefix()."anxhr_vault_doc as t";
		$sql .= " WHERE t.fk_user = ".((int) $fk_user)." AND t.category = '".$db->escape($category)."'";
		$sql .= " AND t.period = '".$db->escape($period)."' AND t.sha256 = '".$db->escape((string) $sha256)."'";
		$sql .= " AND t.status = ".self::STATUS_ACTIVE." AND t.entity IN (".getEntity('anxhr_vaultdoc').")";
		$resql = $db->query($sql);
		if (!$resql) {
			return false;
		}
		$obj = $db->fetch_object($resql);
		$db->free($resql);
		return ($obj && $obj->nb > 0);
	}

	/**
	 * Match file name keys to users with one query: login first, then employee ref, then numeric id
	 *
	 * @param  DoliDB   $db   Database handler
	 * @param  string[] $keys Keys from file names
	 * @return array<string,array{id:int,label:string}>  key => user
	 */
	protected static function matchUsers($db, $keys)
	{
		$keys = array_values(array_unique($keys));
		$matches = array();
		if (empty($keys)) {
			return $matches;
		}
		$escaped = array();
		$ids = array();
		foreach ($keys as $k) {
			$escaped[] = "'".$db->escape($k)."'";
			if (preg_match('/^\d+$/', $k)) {
				$ids[] = (int) $k;
			}
		}
		$sql = "SELECT u.rowid, u.login, u.ref_employee, u.firstname, u.lastname FROM ".$db->prefix()."user as u";
		$sql .= " WHERE u.entity IN (".getEntity('user').")";
		$sql .= " AND (u.login IN (".implode(',', $escaped).") OR u.ref_employee IN (".implode(',', $escaped).")";
		if (!empty($ids)) {
			$sql .= " OR u.rowid IN (".$db->sanitize(implode(',', $ids)).")";
		}
		$sql .= ")";
		$resql = $db->query($sql);
		if (!$resql) {
			dol_syslog(__METHOD__.' '.$db->lasterror(), LOG_ERR);
			return $matches;
		}
		$bylogin = $byref = $byid = array();
		while ($obj = $db->fetch_object($resql)) {
			$u = array('id' => (int) $obj->rowid, 'label' => dolGetFirstLastname((string) $obj->firstname, (string) $obj->lastname).' ('.$obj->login.')');
			$bylogin[(string) $obj->login] = $u;
			if ((string) $obj->ref_employee !== '') {
				$byref[(string) $obj->ref_employee][] = $u;
			}
			$byid[(string) $obj->rowid] = $u;
		}
		$db->free($resql);
		foreach ($keys as $k) {
			if (isset($bylogin[$k])) {
				$matches[$k] = $bylogin[$k];
			} elseif (isset($byref[$k]) && count($byref[$k]) == 1) {
				// An employee ref shared by several users is ambiguous: never guess
				$matches[$k] = $byref[$k][0];
			} elseif (isset($byid[$k])) {
				$matches[$k] = $byid[$k];
			}
		}
		return $matches;
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

		$langs->loadLangs(array('anxhr@anxhr', 'anxhr_vault@anxhr'));
		$datas = array();
		$datas['picto'] = img_picto('', $this->picto).' <u>'.$langs->trans("AnxhrVaultDocument").'</u> '.$this->getLibStatut(5);
		$datas['ref'] = '<br><b>'.$langs->trans('Ref').':</b> '.dol_escape_htmltag($this->ref);
		$datas['label'] = '<br><b>'.$langs->trans('Label').':</b> '.dol_escape_htmltag((string) $this->label);
		$datas['category'] = '<br><b>'.$langs->trans('Category').':</b> '.dol_escape_htmltag(anxhrVaultCategoryLabel($this->category, $langs));
		return $datas;
	}

	/**
	 * Return a link to the vault list filtered on the employee of this document
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
		return anxhrBuildNomUrl($this, '/anxhr/vault.php', $this->ref, $withpicto, $option, $notooltip, $morecss, 0, array('search_fk_user' => (int) $this->fk_user));
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
		$langs->load('anxhr_vault@anxhr');
		$labels = array(
			self::STATUS_ACTIVE => $langs->transnoentitiesnoconv('AnxhrVaultStatusActive'),
			self::STATUS_DELETED => $langs->transnoentitiesnoconv('AnxhrVaultStatusDeleted'),
		);
		$types = array(self::STATUS_ACTIVE => 'status4', self::STATUS_DELETED => 'status9');
		$label = isset($labels[$status]) ? $labels[$status] : (string) $status;
		$type = isset($types[$status]) ? $types[$status] : 'status0';
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
		$this->ref = 'VD-1-1';
		$this->fk_user = 1;
		$this->category = 'payslip';
		$this->period = dol_print_date(dol_now(), '%Y-%m');
		$this->label = 'Payslip';
		$this->filename_original = 'payslip.pdf';
		$this->status = self::STATUS_ACTIVE;
		return $ret;
	}
}
