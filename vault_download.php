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
 *	\file       htdocs/custom/anxhr/vault_download.php
 *	\ingroup    anxhr
 *	\brief      The ONLY download path of the HR document vault: checks rights and CSRF token, logs the access, streams the decrypted file
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
 * @var Translate $langs
 * @var User $user
 */
dol_include_once('/anxhr/lib/anxhr_vault.lib.php');
dol_include_once('/anxhr/class/vaultdoc.class.php');

$langs->loadLangs(array('anxhr@anxhr', 'anxhr_vault@anxhr', 'errors'));

$id = GETPOSTINT('id');

/**
 * Log a denied access and stop with HTTP 403. The same answer is given for a missing and a forbidden document
 * so an unauthorized user can not find out whether a document exists.
 *
 * @param	DoliDB	$db		Database handler
 * @param	User	$user	Current user
 * @param	int		$id		Requested document id
 * @param	string	$reason	Reason for the system log only
 * @return	never
 */
function anxhrVaultDeny($db, $user, $id, $reason)
{
	VaultDoc::writeAccessLog($db, (int) $user->id, (int) $id, 'denied');
	dol_syslog('vault_download denied id='.((int) $id).' user='.((int) $user->id).' reason='.$reason, LOG_WARNING);
	http_response_code(403);
	accessforbidden('', 1, 1, 1);
	exit;
}

// Security check
if (!isModEnabled('anxhr') || empty($user->id)) {
	http_response_code(403);
	accessforbidden();
}

// CSRF: download links are GET links carrying the session token (newToken() when the link was built, which is
// the value of $_SESSION['token'] once main.inc.php has rolled the token for this request). main.inc.php checks
// GET tokens only for some action names, so the check is done here. An invalid token is also removed by
// main.inc.php when MAIN_SECURITY_CSRF_WITH_TOKEN is set, which leads to the same refusal.
$token = GETPOST('token', 'alpha');
$sessiontoken = currentToken();
if (!GETPOSTISSET('token') || $token === '' || $sessiontoken === '' || !hash_equals((string) $sessiontoken, (string) $token)) {
	anxhrVaultDeny($db, $user, $id, 'badtoken');
}

$doc = new VaultDoc($db);
if ($id <= 0 || $doc->fetch($id) <= 0) {
	anxhrVaultDeny($db, $user, $id, 'notfound');
}
if (!$doc->canRead($user)) {
	anxhrVaultDeny($db, $user, $id, 'forbidden');
}


/*
 * Actions
 */

// The file must exist and its MAC must be valid before anything is sent
if (!$doc->isFileValid()) {
	dol_syslog('vault_download file missing or integrity error for document '.((int) $doc->id), LOG_ERR);
	http_response_code(500);
	llxHeader('', $langs->trans('AnxhrVault'));
	print '<div class="error">'.$langs->trans('AnxhrVaultIntegrityError').'</div>';
	llxFooter();
	exit;
}

$doc->logAccess($user, 'download');
if ((int) $user->id === (int) $doc->fk_user) {
	$doc->markViewed();
}


/*
 * View
 */

// Release the session lock: the download may take some time
session_write_close();

// Remove any buffered output so that the file is not corrupted
while (ob_get_level() > 0) {
	ob_end_clean();
}

$mimetype = preg_match('/^[a-z0-9.+\-]+\/[a-z0-9.+\-]+$/i', (string) $doc->mimetype) ? $doc->mimetype : 'application/octet-stream';
$filename = dol_sanitizeFileName((string) $doc->filename_original);
$filename = str_replace(array('"', "\r", "\n", ';'), '_', $filename);
if ($filename === '') {
	$filename = 'document';
}
$asciiname = preg_replace('/[^A-Za-z0-9._\-]/', '_', $filename);

header('Content-Type: '.$mimetype);
header('Content-Disposition: attachment; filename="'.$asciiname.'"; filename*=UTF-8\'\''.rawurlencode($filename));
header('Content-Description: File Transfer');
header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'none'; sandbox");
header('Referrer-Policy: no-referrer');
// Content-Length is not sent: the size is only known after decryption (streamed in chunks)

$doc->streamDecrypted();

$db->close();
exit;
