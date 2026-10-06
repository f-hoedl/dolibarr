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
 *	\file       htdocs/custom/anxhr/vault.php
 *	\ingroup    anxhr
 *	\brief      HR document vault: list of documents (HR: all employees, employee: own documents), upload and bulk payslip import
 */

// Load Dolibarr environment
$res = 0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include str_replace("..", "", $_SERVER["CONTEXT_DOCUMENT_ROOT"])."/main.inc.php";
}
// Try main.inc.php into web root detected using web root calculated from SCRIPT_FILENAME
$tmp = empty($_SERVER['SCRIPT_FILENAME']) ? '' : $_SERVER['SCRIPT_FILENAME'];
$tmp2 = realpath(__FILE__);
$i = strlen($tmp) - 1;
$j = strlen($tmp2) - 1;
while ($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i] == $tmp2[$j]) {
	$i--;
	$j--;
}
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/main.inc.php";
}
if (!$res && $i > 0 && file_exists(dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php")) {
	$res = @include dirname(substr($tmp, 0, ($i + 1)))."/main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../main.inc.php")) {
	$res = @include "../main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}
/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
dol_include_once('/anxhr/lib/anxhr_hr.lib.php');
dol_include_once('/anxhr/lib/anxhr_vault.lib.php');
dol_include_once('/anxhr/class/vaultdoc.class.php');

$langs->loadLangs(array('anxhr@anxhr', 'anxhr_vault@anxhr', 'users', 'other', 'errors'));

// Parameters
$action = GETPOST('action', 'aZ09');
$confirm = GETPOST('confirm', 'alpha');
$id = GETPOSTINT('id');
$search_fk_user = GETPOSTINT('search_fk_user');
$search_category = GETPOST('search_category', 'aZ09');
$search_period = GETPOST('search_period', 'alphanohtml');
$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT('page');
if (empty($page) || $page < 0 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha')) {
	$page = 0;
}
if (GETPOST('button_removefilter', 'alpha')) {
	$search_fk_user = 0;
	$search_category = '';
	$search_period = '';
}
$offset = $limit * $page;

$categories = anxhrVaultCategories();
if (!isset($categories[$search_category])) {
	$search_category = '';
}
if (!anxhrVaultIsValidPeriod($search_period)) {
	$search_period = '';
}

// Security check
if (!isModEnabled('anxhr')) {
	accessforbidden('Module not enabled');
}
$object = new VaultDoc($db);
$canadmin = $user->hasRight('anxhr', 'vault', 'admin');
$canupload = $object->canUpload($user);
$canown = $user->hasRight('anxhr', 'vault', 'own');
if (!$canadmin && !$canupload && !$canown) {
	http_response_code(403);
	accessforbidden();
}
// Without vault admin right, only own documents are listed
if (!$canadmin) {
	$search_fk_user = (int) $user->id;
}

$hookmanager->initHooks(array('anxhrvault', 'globalcard'));
$selfurl = $_SERVER['PHP_SELF'];
$maxsize = anxhrVaultMaxSize(true);
$allowedext = anxhrVaultAllowedExtensions();


/*
 * Actions
 */

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action);
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

/**
 * Return the uploaded file array of a field, or an error message
 *
 * @param	string		$field	Field name
 * @return	array{tmp_name:string,name:string,size:int,type:string}|string	File info or translated error
 */
function anxhrVaultGetUpload($field)
{
	global $langs;

	if (empty($_FILES[$field]) || !is_array($_FILES[$field]) || is_array($_FILES[$field]['name'])) {
		return $langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('File'));
	}
	$f = $_FILES[$field];
	$err = (int) $f['error'];
	if ($err == UPLOAD_ERR_INI_SIZE || $err == UPLOAD_ERR_FORM_SIZE) {
		return $langs->trans('ErrorFileSizeTooLarge');
	}
	if ($err == UPLOAD_ERR_NO_FILE) {
		return $langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('File'));
	}
	if ($err != UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name'])) {
		return $langs->trans('ErrorFailToCreateFile', '');
	}
	return array('tmp_name' => (string) $f['tmp_name'], 'name' => (string) $f['name'], 'size' => (int) $f['size'], 'type' => (string) $f['type']);
}

/**
 * Return true if the user id is a user of the current entities
 *
 * @param	DoliDB	$db		Database handler
 * @param	int		$userid	User id
 * @return	bool
 */
function anxhrVaultIsKnownUser($db, $userid)
{
	if ($userid <= 0) {
		return false;
	}
	$sql = "SELECT u.rowid FROM ".$db->prefix()."user as u WHERE u.rowid = ".((int) $userid)." AND u.entity IN (".getEntity('user').")";
	$resql = $db->query($sql);
	$found = ($resql && $db->num_rows($resql) > 0);
	if ($resql) {
		$db->free($resql);
	}
	return $found;
}

if (empty($reshook) && $action == 'upload' && $canupload) {
	$up_user = GETPOSTINT('up_fk_user');
	$up_category = GETPOST('up_category', 'aZ09');
	$up_period = trim(GETPOST('up_period', 'alphanohtml'));
	$up_label = trim(GETPOST('up_label', 'alphanohtml'));
	$up_visible = GETPOSTINT('up_visible') ? 1 : 0;

	$error = 0;
	if (!anxhrVaultIsKnownUser($db, $up_user)) {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('AnxhrEmployee')), null, 'errors');
		$error++;
	}
	if (!isset($categories[$up_category])) {
		setEventMessages($langs->trans('ErrorFieldRequired', $langs->transnoentitiesnoconv('Category')), null, 'errors');
		$error++;
	}
	if ($up_period !== '' && !anxhrVaultIsValidPeriod($up_period)) {
		setEventMessages($langs->trans('AnxhrVaultErrorBadPeriod'), null, 'errors');
		$error++;
	}
	$file = anxhrVaultGetUpload('up_file');
	if (!is_array($file)) {
		setEventMessages($file, null, 'errors');
		$error++;
	}
	if (!$error) {
		$doc = new VaultDoc($db);
		$doc->fk_user = $up_user;
		$doc->category = $up_category;
		$doc->period = ($up_period !== '' ? $up_period : null);
		$doc->label = ($up_label !== '' ? dol_trunc($up_label, 250, 'right', 'UTF-8', 1) : anxhrVaultCategoryLabel($up_category, $langs).($up_period !== '' ? ' '.$up_period : ''));
		$doc->visible_to_employee = $up_visible;
		$result = $doc->storeUploadedFile($user, $file['tmp_name'], $file['name'], $file['type']);
		if ($result > 0) {
			setEventMessages($langs->trans('AnxhrVaultUploaded', $doc->ref), null, 'mesgs');
			header('Location: '.$selfurl.($canadmin ? '?search_fk_user='.((int) $up_user) : ''));
			exit;
		}
		setEventMessages($doc->error, $doc->errors, 'errors');
	} elseif (is_array($file) && is_file($file['tmp_name'])) {
		@unlink($file['tmp_name']);
	}
	$action = '';
}

if (empty($reshook) && $action == 'bulkimport' && $canadmin) {
	$file = anxhrVaultGetUpload('zip_file');
	$visible = GETPOSTINT('zip_visible') ? 1 : 0;
	if (!is_array($file)) {
		setEventMessages($file, null, 'errors');
	} elseif (anxhrVaultExtension($file['name']) != 'zip') {
		setEventMessages($langs->trans('AnxhrVaultErrorExtension', 'zip'), null, 'errors');
		@unlink($file['tmp_name']);
	} elseif ($file['size'] > $maxsize) {
		setEventMessages($langs->trans('AnxhrVaultErrorTooLarge', dol_print_size($maxsize)), null, 'errors');
		@unlink($file['tmp_name']);
	} else {
		$res = VaultDoc::importPayslipZip($db, $user, $file['tmp_name'], (bool) $visible);
		@unlink($file['tmp_name']);
		if (!empty($res['matched'])) {
			$lines = array();
			foreach ($res['matched'] as $m) {
				$lines[] = dol_escape_htmltag($m['file']).' &rarr; '.dol_escape_htmltag($m['user']).' ('.dol_escape_htmltag($m['period']).')';
			}
			setEventMessages($langs->trans('AnxhrVaultImportMatched', count($res['matched'])), $lines, 'mesgs');
		}
		if (!empty($res['skipped'])) {
			setEventMessages($langs->trans('AnxhrVaultImportSkipped', count($res['skipped'])), array_map('dol_escape_htmltag', $res['skipped']), 'warnings');
		}
		if (!empty($res['unmatched'])) {
			setEventMessages($langs->trans('AnxhrVaultImportUnmatched', count($res['unmatched'])), array_map('dol_escape_htmltag', $res['unmatched']), 'warnings');
		}
		if (!empty($res['errors'])) {
			setEventMessages($langs->trans('AnxhrVaultImportErrors', count($res['errors'])), array_map('dol_escape_htmltag', $res['errors']), 'errors');
		}
		if (empty($res['matched']) && empty($res['unmatched']) && empty($res['errors']) && empty($res['skipped'])) {
			setEventMessages($langs->trans('AnxhrVaultImportEmpty'), null, 'warnings');
		}
		header('Location: '.$selfurl);
		exit;
	}
	$action = '';
}

if (empty($reshook) && $action == 'confirm_delete' && $confirm == 'yes' && $canadmin) {
	$doc = new VaultDoc($db);
	if ($id > 0 && $doc->fetch($id) > 0 && (int) $doc->status === VaultDoc::STATUS_ACTIVE) {
		if ($doc->deleteWithFile($user) > 0) {
			setEventMessages($langs->trans('RecordDeleted'), null, 'mesgs');
			header('Location: '.$selfurl.'?search_fk_user='.((int) $doc->fk_user));
			exit;
		}
		setEventMessages($doc->error, $doc->errors, 'errors');
	} else {
		setEventMessages($langs->trans('ErrorRecordNotFound'), null, 'errors');
	}
	$action = '';
}


/*
 * Data loading
 */

$docs = array();
$nbtotalofrecords = 0;
$showadminlist = $canadmin;
if ($showadminlist) {
	$sqlwhere = " WHERE t.entity IN (".getEntity('anxhr_vaultdoc').") AND t.status = ".VaultDoc::STATUS_ACTIVE;
	if ($search_fk_user > 0) {
		$sqlwhere .= " AND t.fk_user = ".((int) $search_fk_user);
	}
	if ($search_category !== '') {
		$sqlwhere .= " AND t.category = '".$db->escape($search_category)."'";
	}
	if ($search_period !== '') {
		$sqlwhere .= " AND t.period = '".$db->escape($search_period)."'";
	}
	$resql = $db->query("SELECT COUNT(t.rowid) as nb FROM ".$db->prefix()."anxhr_vault_doc as t".$sqlwhere);
	if ($resql) {
		$obj = $db->fetch_object($resql);
		$nbtotalofrecords = $obj ? (int) $obj->nb : 0;
		$db->free($resql);
	}
	if ($offset > $nbtotalofrecords) {
		$page = 0;
		$offset = 0;
	}
	$sql = "SELECT t.rowid, t.ref, t.fk_user, t.category, t.period, t.label, t.filename_original, t.filesize, t.uploaded_by, t.date_upload, t.visible_to_employee, t.first_viewed_at";
	$sql .= " FROM ".$db->prefix()."anxhr_vault_doc as t".$sqlwhere;
	$sql .= " ORDER BY t.date_upload DESC, t.rowid DESC";
	$sql .= $db->plimit($limit, $offset);
	$resql = $db->query($sql);
	if ($resql) {
		while ($obj = $db->fetch_object($resql)) {
			$docs[] = $obj;
		}
		$db->free($resql);
	} else {
		dol_print_error($db);
	}
} elseif ($canown) {
	$owndocs = $object->fetchAllForUser((int) $user->id, '', true);
	if (is_array($owndocs)) {
		$docs = $owndocs;
	}
}

$userids = array();
foreach ($docs as $d) {
	$userids[] = (int) $d->fk_user;
	$userids[] = (int) $d->uploaded_by;
}
$users = anxhrLoadUsersCache($db, $userids);


/*
 * View
 */

$form = new Form($db);

/**
 * Return the download button of a document
 *
 * @param	int			$docid	Document id
 * @param	string		$name	File name (for title)
 * @param	Translate	$langs	Translations
 * @return	string
 */
function anxhrVaultDownloadLink($docid, $name, $langs)
{
	$url = dol_buildpath('/anxhr/vault_download.php', 1).'?id='.((int) $docid).'&token='.newToken();
	return '<a class="button smallpaddingimp anxhr-vault-dl" href="'.$url.'" rel="nofollow noopener" title="'.dolPrintHTMLForAttribute($langs->trans('Download').' '.$name).'">'.img_picto('', 'fa-download', 'class="pictofixedwidth"').'<span class="hideonsmartphone">'.$langs->trans('Download').'</span></a>';
}

$title = $langs->trans('AnxhrVault');
llxHeader('', $title, '', '', 0, 0, '', array('/anxhr/css/anxhr_vault.css'), '', 'mod-anxhr page-vault');

$morehtmlright = '';
if ($canupload) {
	$morehtmlright = dolGetButtonTitle($langs->trans('AnxhrVaultUpload'), '', 'fa fa-upload', '#anxhr-vault-upload');
}
print load_fiche_titre($title, $morehtmlright, 'fa-shield-alt');
print dol_get_fiche_head(anxhrVaultPrepareHead(), 'documents', '', -1, '');

if (empty(anxhrVaultGetMasterKeys())) {
	print '<div class="error">'.$langs->trans('AnxhrVaultNoCryptKey').'</div>';
}

// Confirm deletion
if ($action == 'delete' && $canadmin && $id > 0) {
	$doc = new VaultDoc($db);
	if ($doc->fetch($id) > 0 && (int) $doc->status === VaultDoc::STATUS_ACTIVE) {
		print $form->formconfirm($selfurl.'?id='.((int) $doc->id), $langs->trans('AnxhrVaultDelete'), $langs->trans('AnxhrVaultConfirmDelete', $doc->ref.' - '.$doc->filename_original), 'confirm_delete', '', 0, 0);
	}
}

if ($showadminlist) {
	// Filters
	print '<form method="GET" action="'.$selfurl.'" class="anxhr-vault-filters">';
	print '<div class="anxhr-vault-filter"><label for="search_fk_user">'.$langs->trans('AnxhrEmployee').'</label>'.anxhrGetEmployeeSelect($form, $search_fk_user, 'search_fk_user', 1).'</div>';
	print '<div class="anxhr-vault-filter"><label for="search_category">'.$langs->trans('Category').'</label><select name="search_category" id="search_category" class="flat minwidth150">';
	print '<option value="">&nbsp;</option>';
	foreach ($categories as $code => $def) {
		print '<option value="'.dol_escape_htmltag($code).'"'.($search_category === $code ? ' selected' : '').'>'.dol_escape_htmltag($langs->trans($def[0])).'</option>';
	}
	print '</select>'.ajax_combobox('search_category').'</div>';
	print '<div class="anxhr-vault-filter"><label for="search_period">'.$langs->trans('AnxhrVaultPeriod').'</label><input type="month" name="search_period" id="search_period" class="flat maxwidth150" pattern="\d{4}-\d{2}" placeholder="YYYY-MM" value="'.dol_escape_htmltag($search_period).'"></div>';
	print '<div class="anxhr-vault-filter anxhr-vault-filter-buttons">'.$form->showFilterButtons().'</div>';
	print '</form>';

	$param = '';
	if ($search_fk_user > 0) {
		$param .= '&search_fk_user='.((int) $search_fk_user);
	}
	if ($search_category !== '') {
		$param .= '&search_category='.urlencode($search_category);
	}
	if ($search_period !== '') {
		$param .= '&search_period='.urlencode($search_period);
	}
	if ($limit != $conf->liste_limit) {
		$param .= '&limit='.((int) $limit);
	}
	print_barre_liste($langs->trans('AnxhrVaultDocuments'), $page, $selfurl, $param, '', '', '', count($docs), $nbtotalofrecords, 'fa-file-alt', 0, '', '', $limit, 0, 0, 1);

	print '<div class="div-table-responsive">';
	print '<table class="tagtable noborder centpercent liste anxhr-vault-table">';
	print '<tr class="liste_titre">';
	print '<th>'.$langs->trans('Ref').'</th>';
	print '<th>'.$langs->trans('AnxhrEmployee').'</th>';
	print '<th>'.$langs->trans('Category').'</th>';
	print '<th class="center">'.$langs->trans('AnxhrVaultPeriod').'</th>';
	print '<th class="hideonsmartphone">'.$langs->trans('Label').'</th>';
	print '<th class="hideonsmartphone">'.$langs->trans('AnxhrVaultFileName').'</th>';
	print '<th class="right hideonsmartphone">'.$langs->trans('Size').'</th>';
	print '<th class="hideonsmartphone">'.$langs->trans('AnxhrVaultUploadedOnBy').'</th>';
	print '<th class="center">'.$langs->trans('AnxhrVaultReadStatus').'</th>';
	print '<th class="right"></th>';
	print '</tr>';
	if (empty($docs)) {
		print '<tr class="oddeven"><td colspan="10"><div class="anxhr-vault-empty">'.img_picto('', 'fa-folder-open', 'class="pictofixedwidth"').$langs->trans('AnxhrVaultNoDocuments').'</div></td></tr>';
	}
	foreach ($docs as $d) {
		$emp = isset($users[(int) $d->fk_user]) ? $users[(int) $d->fk_user] : null;
		$upl = isset($users[(int) $d->uploaded_by]) ? $users[(int) $d->uploaded_by] : null;
		print '<tr class="oddeven">';
		print '<td class="nowraponall">'.dol_escape_htmltag($d->ref).(empty($d->visible_to_employee) ? ' '.img_picto($langs->trans('AnxhrVaultHiddenFromEmployee'), 'fa-eye-slash', 'class="opacitymedium"') : '').'</td>';
		print '<td class="tdoverflowmax150">'.($emp ? $emp->getNomUrl(-1) : '').'</td>';
		print '<td class="nowraponall">'.anxhrVaultCategoryBadge($d->category, $langs).'</td>';
		print '<td class="center nowraponall">'.dol_escape_htmltag((string) $d->period).'</td>';
		print '<td class="tdoverflowmax200 hideonsmartphone" title="'.dolPrintHTMLForAttribute((string) $d->label).'">'.dol_escape_htmltag((string) $d->label).'</td>';
		print '<td class="tdoverflowmax150 hideonsmartphone" title="'.dolPrintHTMLForAttribute($d->filename_original).'">'.dol_escape_htmltag($d->filename_original).'</td>';
		print '<td class="right nowraponall hideonsmartphone">'.dol_print_size((int) $d->filesize, 1, 1).'</td>';
		print '<td class="nowraponall hideonsmartphone">'.dol_print_date($db->jdate($d->date_upload), 'dayhour', 'tzuserrel');
		if ($upl) {
			print '<br><span class="opacitymedium small">'.dol_escape_htmltag($upl->getFullName($langs)).'</span>';
		}
		print '</td>';
		print '<td class="center nowraponall">'.(empty($d->visible_to_employee) ? '<span class="opacitymedium">-</span>' : anxhrVaultViewedBadge($db->jdate($d->first_viewed_at), $langs)).'</td>';
		print '<td class="right nowraponall">';
		print anxhrVaultDownloadLink((int) $d->rowid, $d->filename_original, $langs);
		print ' <a class="reposition marginleftonly" href="'.$selfurl.'?action=delete&id='.((int) $d->rowid).'&token='.newToken().$param.'" title="'.dolPrintHTMLForAttribute($langs->trans('AnxhrVaultDelete')).'" aria-label="'.dolPrintHTMLForAttribute($langs->trans('AnxhrVaultDelete')).'">'.img_delete($langs->trans('AnxhrVaultDelete')).'</a>';
		print '</td>';
		print '</tr>';
	}
	print '</table>';
	print '</div>';
} elseif ($canown) {
	// Employee view: own documents grouped by category, latest first
	$bycat = array();
	foreach ($docs as $d) {
		$bycat[$d->category][] = $d;
	}
	if (empty($docs)) {
		print '<div class="anxhr-vault-empty anxhr-vault-empty-big">'.img_picto('', 'fa-folder-open', 'class="pictofixedwidth"').$langs->trans('AnxhrVaultNoDocuments').'<br><span class="opacitymedium small">'.$langs->trans('AnxhrVaultNoDocumentsHelp').'</span></div>';
	}
	foreach ($categories as $code => $def) {
		if (empty($bycat[$code])) {
			continue;
		}
		print '<div class="div-table-responsive-no-min anxhr-vault-group">';
		print '<table class="noborder centpercent anxhr-vault-table">';
		print '<tr class="liste_titre"><th colspan="4">'.img_picto('', $def[1], 'class="pictofixedwidth"').dol_escape_htmltag($langs->trans($def[0])).' <span class="badge marginleftonlyshort">'.count($bycat[$code]).'</span></th></tr>';
		foreach ($bycat[$code] as $d) {
			print '<tr class="oddeven">';
			print '<td class="tdoverflowmax300"><span class="anxhr-vault-doclabel">'.dol_escape_htmltag($d->label ? $d->label : $d->filename_original).'</span>';
			if (empty($d->first_viewed_at)) {
				print ' <span class="badge badge-status1 anxhr-vault-new">'.$langs->trans('AnxhrVaultNew').'</span>';
			}
			print '<br><span class="opacitymedium small">'.dol_escape_htmltag($d->filename_original).'</span></td>';
			print '<td class="center nowraponall">'.dol_escape_htmltag((string) $d->period).'</td>';
			print '<td class="right nowraponall hideonsmartphone"><span class="opacitymedium">'.dol_print_size((int) $d->filesize, 1, 1).'</span><br><span class="opacitymedium small">'.dol_print_date($d->date_upload, 'day', 'tzuserrel').'</span></td>';
			print '<td class="right nowraponall">'.anxhrVaultDownloadLink((int) $d->id, $d->filename_original, $langs).'</td>';
			print '</tr>';
		}
		print '</table>';
		print '</div>';
	}
}

// Upload forms
if ($canupload) {
	print '<div class="fichecenter anxhr-vault-forms" id="anxhr-vault-upload">';
	print '<div class="'.($canadmin ? 'fichehalfleft' : '').'">';
	print '<form method="POST" action="'.$selfurl.'" enctype="multipart/form-data" class="anxhr-vault-form">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="upload">';
	print '<input type="hidden" name="MAX_FILE_SIZE" value="'.((int) $maxsize).'">';
	print '<table class="noborder centpercent">';
	print '<tr class="liste_titre"><th colspan="2">'.img_picto('', 'fa-upload', 'class="pictofixedwidth"').$langs->trans('AnxhrVaultUpload').'</th></tr>';
	print '<tr class="oddeven"><td class="titlefield fieldrequired"><label for="up_fk_user">'.$langs->trans('AnxhrEmployee').'</label></td><td>'.anxhrGetEmployeeSelect($form, ($search_fk_user > 0 ? $search_fk_user : GETPOSTINT('up_fk_user')), 'up_fk_user', 1).'</td></tr>';
	print '<tr class="oddeven"><td class="fieldrequired"><label for="up_category">'.$langs->trans('Category').'</label></td><td><select name="up_category" id="up_category" class="flat minwidth200" required>';
	foreach ($categories as $code => $def) {
		print '<option value="'.dol_escape_htmltag($code).'"'.(GETPOST('up_category', 'aZ09') === $code ? ' selected' : '').'>'.dol_escape_htmltag($langs->trans($def[0])).'</option>';
	}
	print '</select>'.ajax_combobox('up_category').'</td></tr>';
	print '<tr class="oddeven"><td><label for="up_period">'.$langs->trans('AnxhrVaultPeriod').'</label></td><td><input type="month" name="up_period" id="up_period" class="flat maxwidth150" pattern="\d{4}-\d{2}" placeholder="YYYY-MM" value="'.dol_escape_htmltag(GETPOST('up_period', 'alphanohtml')).'"> <span class="opacitymedium small">'.$langs->trans('AnxhrVaultPeriodHelp').'</span></td></tr>';
	print '<tr class="oddeven"><td><label for="up_label">'.$langs->trans('Label').'</label></td><td><input type="text" name="up_label" id="up_label" class="flat minwidth300 maxwidth500 widthcentpercentminusx" maxlength="255" value="'.dol_escape_htmltag(GETPOST('up_label', 'alphanohtml')).'"></td></tr>';
	print '<tr class="oddeven"><td class="fieldrequired"><label for="up_file">'.$langs->trans('File').'</label></td><td><input type="file" name="up_file" id="up_file" class="flat minwidth200" required accept=".'.implode(',.', array_map('dol_escape_htmltag', $allowedext)).'">';
	print '<br><span class="opacitymedium small">'.$langs->trans('AnxhrVaultAllowedFiles', implode(', ', $allowedext), dol_print_size($maxsize)).'</span></td></tr>';
	print '<tr class="oddeven"><td></td><td><input type="checkbox" name="up_visible" id="up_visible" value="1" checked> <label for="up_visible">'.$langs->trans('AnxhrVaultVisibleToEmployee').'</label></td></tr>';
	print '</table>';
	print '<div class="center"><button type="submit" class="button">'.img_picto('', 'fa-lock', 'class="pictofixedwidth"').$langs->trans('AnxhrVaultUploadEncrypt').'</button></div>';
	print '</form>';
	print '</div>';

	if ($canadmin) {
		print '<div class="fichehalfright">';
		print '<form method="POST" action="'.$selfurl.'" enctype="multipart/form-data" class="anxhr-vault-form">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="bulkimport">';
		print '<input type="hidden" name="MAX_FILE_SIZE" value="'.((int) $maxsize).'">';
		print '<table class="noborder centpercent">';
		print '<tr class="liste_titre"><th colspan="2">'.img_picto('', 'fa-file-archive', 'class="pictofixedwidth"').$langs->trans('AnxhrVaultBulkImport').'</th></tr>';
		if (!class_exists('ZipArchive')) {
			print '<tr class="oddeven"><td colspan="2"><div class="warning">'.$langs->trans('AnxhrVaultErrorNoZip').'</div></td></tr>';
		} else {
			print '<tr class="oddeven"><td colspan="2"><span class="opacitymedium">'.$langs->trans('AnxhrVaultBulkImportHelp').'</span></td></tr>';
			print '<tr class="oddeven"><td class="titlefield fieldrequired"><label for="zip_file">'.$langs->trans('AnxhrVaultZipFile').'</label></td><td><input type="file" name="zip_file" id="zip_file" class="flat minwidth200" required accept=".zip">';
			print '<br><span class="opacitymedium small">'.$langs->trans('AnxhrVaultMaxSize', dol_print_size($maxsize)).'</span></td></tr>';
			print '<tr class="oddeven"><td></td><td><input type="checkbox" name="zip_visible" id="zip_visible" value="1" checked> <label for="zip_visible">'.$langs->trans('AnxhrVaultVisibleToEmployee').'</label></td></tr>';
		}
		print '</table>';
		if (class_exists('ZipArchive')) {
			print '<div class="center"><button type="submit" class="button">'.img_picto('', 'fa-file-import', 'class="pictofixedwidth"').$langs->trans('AnxhrVaultImport').'</button></div>';
		}
		print '</form>';
		print '</div>';
	}
	print '</div>';
	print '<div class="clearboth"></div>';
}

print dol_get_fiche_end();

llxFooter();
$db->close();
