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
 * \file        htdocs/custom/anxhr/lib/anxhr_vault.lib.php
 * \ingroup     anxhr
 * \brief       HR document vault: file encryption (chunked AES-256-CBC + HMAC-SHA256), storage and display helpers.
 *
 * Encrypted file format (version 1):
 *   IV (16 bytes) || AES-256-CBC ciphertext with PKCS#7 padding || HMAC-SHA256(IV || ciphertext) (32 bytes)
 * Encrypt-then-MAC. The encryption key and the MAC key are both derived from the Dolibarr crypt key of conf.php
 * ($dolibarr_main_dolcrypt_key, fallback $dolibarr_main_instance_unique_id) with SHA-256 and a domain separator.
 * The key never goes into the database. Files are processed in chunks so memory usage stays low for big files.
 */

/** Size of a processing chunk, must be a multiple of the AES block size (16) */
define('ANXHR_VAULT_CHUNK', 1048576);
/** Cipher used for files */
define('ANXHR_VAULT_CIPHER', 'aes-256-cbc');

/**
 * Return the list of candidate master keys from conf.php. The first one is used to encrypt, all are tried to decrypt
 * (same convention as dolDecrypt: several keys separated with a comma allow a key rotation).
 *
 * @return string[]		List of keys, empty if no key is configured
 */
function anxhrVaultGetMasterKeys()
{
	global $conf, $dolibarr_main_dolcrypt_key, $dolibarr_main_instance_unique_id;

	$raw = '';
	if (!empty($conf->file->dolcrypt_key)) {
		$raw = (string) $conf->file->dolcrypt_key;
	} elseif (!empty($dolibarr_main_dolcrypt_key)) {
		$raw = (string) $dolibarr_main_dolcrypt_key;
	} elseif (!empty($conf->file->instance_unique_id)) {
		$raw = (string) $conf->file->instance_unique_id;
	} elseif (!empty($dolibarr_main_instance_unique_id)) {
		$raw = (string) $dolibarr_main_instance_unique_id;
	}
	$keys = array();
	foreach (explode(',', $raw) as $key) {
		$key = trim($key);
		if ($key !== '') {
			$keys[] = $key;
		}
	}
	return $keys;
}

/**
 * Derive the encryption key and the MAC key from a master key
 *
 * @param	string	$key	Master key
 * @return	array{0:string,1:string}	Array(binary encryption key 32 bytes, binary MAC key 32 bytes)
 */
function anxhrVaultDeriveKeys($key)
{
	return array(hash('sha256', 'anxhr-vault-enc-v1|'.$key, true), hash('sha256', 'anxhr-vault-mac-v1|'.$key, true));
}

/**
 * Read up to $len bytes from a stream (loops until $len bytes or EOF)
 *
 * @param	resource	$fh		Stream
 * @param	int			$len	Number of bytes
 * @return	string				Data (shorter than $len only at end of stream)
 */
function anxhrVaultReadExactly($fh, $len)
{
	$data = '';
	while (strlen($data) < $len && !feof($fh)) {
		$part = fread($fh, $len - strlen($data));
		if ($part === false || $part === '') {
			break;
		}
		$data .= $part;
	}
	return $data;
}

/**
 * Encrypt a file into another file (chunked AES-256-CBC, random IV as the first 16 bytes, HMAC-SHA256 appended).
 * The output is first written to a temporary file in the target directory then renamed, with permissions 0600.
 *
 * @param	string	$in		Plaintext file path
 * @param	string	$out	Encrypted file path to create
 * @param	string	$key	Master key
 * @return	array{size:int,sha256:string}|false	Plaintext size and SHA-256 (hex) or false on error
 */
function anxhrVaultEncryptFile($in, $out, $key)
{
	if ($key === '' || !function_exists('openssl_encrypt')) {
		return false;
	}
	list($enckey, $mackey) = anxhrVaultDeriveKeys($key);
	$fin = @fopen($in, 'rb');
	if (!$fin) {
		return false;
	}
	$tmpout = $out.'.part'.bin2hex(random_bytes(4));
	$fout = @fopen($tmpout, 'xb');
	if (!$fout) {
		fclose($fin);
		return false;
	}
	@chmod($tmpout, 0600);

	$iv = random_bytes(16);
	$hmac = hash_init('sha256', HASH_HMAC, $mackey);
	$plainhash = hash_init('sha256');
	$size = 0;
	$ok = (fwrite($fout, $iv) === 16);
	hash_update($hmac, $iv);
	$chain = $iv;
	while ($ok) {
		$chunk = anxhrVaultReadExactly($fin, ANXHR_VAULT_CHUNK);
		$final = (strlen($chunk) < ANXHR_VAULT_CHUNK);
		$size += strlen($chunk);
		hash_update($plainhash, $chunk);
		if ($final) {
			$cipher = openssl_encrypt($chunk, ANXHR_VAULT_CIPHER, $enckey, OPENSSL_RAW_DATA, $chain);
		} else {
			$cipher = openssl_encrypt($chunk, ANXHR_VAULT_CIPHER, $enckey, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $chain);
		}
		if ($cipher === false || fwrite($fout, $cipher) !== strlen($cipher)) {
			$ok = false;
			break;
		}
		hash_update($hmac, $cipher);
		$chain = substr($cipher, -16);
		if ($final) {
			break;
		}
	}
	if ($ok) {
		$mac = hash_final($hmac, true);
		$ok = (fwrite($fout, $mac) === 32);
	}
	fclose($fin);
	$ok = (fclose($fout) && $ok);
	if ($ok) {
		$ok = @rename($tmpout, $out);
	}
	if (!$ok) {
		@unlink($tmpout);
		return false;
	}
	@chmod($out, 0600);
	return array('size' => $size, 'sha256' => hash_final($plainhash));
}

/**
 * Verify the HMAC of an encrypted file and return the master key that matches
 *
 * @param	string			$in		Encrypted file path
 * @param	string|string[]	$keys	Master key or list of candidate master keys
 * @return	string|false			The matching master key, or false if the file is corrupt or no key matches
 */
function anxhrVaultVerifyFile($in, $keys)
{
	$size = @filesize($in);
	// IV + at least one block + MAC, ciphertext length multiple of 16
	if ($size === false || $size < 64 || (($size - 48) % 16) != 0) {
		return false;
	}
	foreach ((array) $keys as $key) {
		if ($key === '') {
			continue;
		}
		list($enckey, $mackey) = anxhrVaultDeriveKeys($key);
		$fh = @fopen($in, 'rb');
		if (!$fh) {
			return false;
		}
		$hmac = hash_init('sha256', HASH_HMAC, $mackey);
		$remaining = $size - 32;
		while ($remaining > 0) {
			$data = anxhrVaultReadExactly($fh, (int) min(ANXHR_VAULT_CHUNK, $remaining));
			if ($data === '') {
				break;
			}
			hash_update($hmac, $data);
			$remaining -= strlen($data);
		}
		$mac = anxhrVaultReadExactly($fh, 32);
		fclose($fh);
		if ($remaining == 0 && strlen($mac) == 32 && hash_equals(hash_final($hmac, true), $mac)) {
			return $key;
		}
	}
	return false;
}

/**
 * Verify the HMAC then decrypt an encrypted file into a stream (php://output by default).
 * Nothing is written to the output if the verification fails.
 *
 * @param	string			$in		Encrypted file path
 * @param	string|string[]	$keys	Master key or list of candidate master keys
 * @param	string			$out	Output stream or file path
 * @return	array{size:int,sha256:string}|false	Size and SHA-256 of the plaintext written, false on error
 */
function anxhrVaultDecryptToStream($in, $keys, $out = 'php://output')
{
	$key = anxhrVaultVerifyFile($in, $keys);
	if ($key === false) {
		return false;
	}
	list($enckey, $mackey) = anxhrVaultDeriveKeys($key);
	$size = filesize($in);
	$fin = @fopen($in, 'rb');
	if (!$fin) {
		return false;
	}
	$fout = @fopen($out, 'wb');
	if (!$fout) {
		fclose($fin);
		return false;
	}
	$chain = anxhrVaultReadExactly($fin, 16);
	$remaining = $size - 48;
	$plainhash = hash_init('sha256');
	$written = 0;
	$ok = true;
	while ($remaining > 0) {
		$cipher = anxhrVaultReadExactly($fin, (int) min(ANXHR_VAULT_CHUNK, $remaining));
		if ($cipher === '' || strlen($cipher) % 16 != 0) {
			$ok = false;
			break;
		}
		$remaining -= strlen($cipher);
		$options = OPENSSL_RAW_DATA | ($remaining > 0 ? OPENSSL_ZERO_PADDING : 0);
		$plain = openssl_decrypt($cipher, ANXHR_VAULT_CIPHER, $enckey, $options, $chain);
		if ($plain === false) {
			$ok = false;
			break;
		}
		$chain = substr($cipher, -16);
		hash_update($plainhash, $plain);
		if ($plain !== '' && fwrite($fout, $plain) !== strlen($plain)) {
			$ok = false;
			break;
		}
		$written += strlen($plain);
	}
	fclose($fin);
	fclose($fout);
	if (!$ok) {
		return false;
	}
	return array('size' => $written, 'sha256' => hash_final($plainhash));
}

/**
 * Return true if a stored file name has the expected format (random hex name + .enc). Protects against path traversal.
 *
 * @param	string	$name	Stored file name
 * @return	bool
 */
function anxhrVaultIsValidStoredName($name)
{
	return (bool) preg_match('/^[a-f0-9]{32}\.enc$/', (string) $name);
}

/**
 * Generate a new random stored file name
 *
 * @return	string
 */
function anxhrVaultNewStoredName()
{
	return bin2hex(random_bytes(16)).'.enc';
}

/**
 * Return the root directory of the vault for an entity
 *
 * @param	int		$entity		Entity
 * @return	string				Path (empty if module output dir is unknown)
 */
function anxhrVaultRootDir($entity)
{
	global $conf;

	$entity = (int) $entity;
	if (!empty($conf->anxhr->multidir_output[$entity])) {
		return $conf->anxhr->multidir_output[$entity].'/vault';
	}
	if (!empty($conf->anxhr->dir_output)) {
		return $conf->anxhr->dir_output.'/vault';
	}
	return DOL_DATA_ROOT.($entity > 1 ? '/'.$entity : '').'/anxhr/vault';
}

/**
 * Create the vault root directory with a deny-all .htaccess and an empty index.html (defense in depth).
 *
 * @param	string	$dir	Vault root directory
 * @return	bool			True if the directory exists
 */
function anxhrVaultEnsureRootDir($dir)
{
	if (!is_dir($dir) && dol_mkdir($dir) < 0) {
		return false;
	}
	if (!file_exists($dir.'/.htaccess')) {
		@file_put_contents($dir.'/.htaccess', "# ANX HR vault: never serve these files directly\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n");
	}
	if (!file_exists($dir.'/index.html')) {
		@file_put_contents($dir.'/index.html', '');
	}
	return is_dir($dir);
}

/**
 * Return the directory of an employee in the vault (created if needed, permissions 0700 when possible)
 *
 * @param	int		$entity		Entity
 * @param	int		$fk_user	Employee id
 * @return	string				Directory path or '' on error
 */
function anxhrVaultUserDir($entity, $fk_user)
{
	$root = anxhrVaultRootDir($entity);
	if (!anxhrVaultEnsureRootDir($root)) {
		return '';
	}
	$dir = $root.'/'.((int) $fk_user);
	if (!is_dir($dir)) {
		if (dol_mkdir($dir) < 0) {
			return '';
		}
		@chmod($dir, 0700);
	}
	return $dir;
}

/**
 * Return document categories of the vault: code => array(translation key, picto)
 *
 * @return array<string,array{0:string,1:string}>
 */
function anxhrVaultCategories()
{
	return array(
		'payslip' => array('AnxhrVaultCatPayslip', 'fa-money-check-alt'),
		'contract' => array('AnxhrVaultCatContract', 'fa-file-signature'),
		'certificate' => array('AnxhrVaultCatCertificate', 'fa-award'),
		'timesheet' => array('AnxhrVaultCatTimesheet', 'fa-calendar-alt'),
		'sickness_certificate' => array('AnxhrVaultCatSicknessCertificate', 'fa-notes-medical'),
		'other' => array('AnxhrVaultCatOther', 'fa-file'),
	);
}

/**
 * Return translated label of a category
 *
 * @param	string		$code	Category code
 * @param	Translate	$langs	Translation object
 * @return	string				Label (not escaped)
 */
function anxhrVaultCategoryLabel($code, $langs)
{
	$cats = anxhrVaultCategories();
	return isset($cats[$code]) ? $langs->trans($cats[$code][0]) : (string) $code;
}

/**
 * Return the HTML badge of a category, with icon
 *
 * @param	string		$code	Category code
 * @param	Translate	$langs	Translation object
 * @return	string				HTML
 */
function anxhrVaultCategoryBadge($code, $langs)
{
	$cats = anxhrVaultCategories();
	$picto = isset($cats[$code]) ? $cats[$code][1] : 'fa-file';
	$css = preg_replace('/[^a-z_]/', '', (string) $code);
	return '<span class="badge badge-secondary anxhr-vault-cat anxhr-vault-cat-'.$css.'">'.img_picto('', $picto, 'class="pictofixedwidth"').dol_escape_htmltag(anxhrVaultCategoryLabel($code, $langs)).'</span>';
}

/**
 * Return the HTML badge Unread / Viewed of a document
 *
 * @param	int|string|null	$firstviewed	First view date (timestamp or empty)
 * @param	Translate		$langs			Translation object
 * @return	string							HTML
 */
function anxhrVaultViewedBadge($firstviewed, $langs)
{
	if (empty($firstviewed)) {
		return '<span class="badge badge-status1 anxhr-vault-unread">'.img_picto('', 'fa-envelope', 'class="pictofixedwidth"').$langs->trans('AnxhrVaultUnread').'</span>';
	}
	return '<span class="badge badge-status4 anxhr-vault-viewed" title="'.dolPrintHTMLForAttribute(dol_print_date($firstviewed, 'dayhour', 'tzuserrel')).'">'.img_picto('', 'fa-check', 'class="pictofixedwidth"').$langs->trans('AnxhrVaultViewed').'</span>';
}

/**
 * Return the allowed file extensions (lower case)
 *
 * @return string[]
 */
function anxhrVaultAllowedExtensions()
{
	$list = getDolGlobalString('ANXHR_VAULT_ALLOWED_EXT', 'pdf,png,jpg,jpeg,docx,xlsx,txt,csv');
	$exts = array();
	foreach (explode(',', strtolower($list)) as $ext) {
		$ext = preg_replace('/[^a-z0-9]/', '', $ext);
		if ($ext !== '') {
			$exts[] = $ext;
		}
	}
	return array_values(array_unique($exts));
}

/**
 * Return the maximum size of a vault document in bytes
 *
 * @param	bool	$isupload	True for a browser upload: the global Dolibarr upload limit MAIN_UPLOAD_DOC (in Kb) applies too
 * @return	int
 */
function anxhrVaultMaxSize($isupload = true)
{
	$max = max(1, getDolGlobalInt('ANXHR_VAULT_MAX_SIZE_MB', 20)) * 1048576;
	if ($isupload && getDolGlobalInt('MAIN_UPLOAD_DOC') > 0) {
		$max = min($max, getDolGlobalInt('MAIN_UPLOAD_DOC') * 1024);
	}
	return (int) $max;
}

/**
 * Return the mime type to use for an extension of the whitelist (never trust the browser)
 *
 * @param	string	$ext	Extension (lower case)
 * @return	string
 */
function anxhrVaultMimeFromExt($ext)
{
	$map = array(
		'pdf' => 'application/pdf',
		'png' => 'image/png',
		'jpg' => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'txt' => 'text/plain',
		'csv' => 'text/csv',
	);
	return isset($map[$ext]) ? $map[$ext] : 'application/octet-stream';
}

/**
 * Return the lower case extension of a file name
 *
 * @param	string	$filename	File name
 * @return	string
 */
function anxhrVaultExtension($filename)
{
	return strtolower((string) pathinfo((string) $filename, PATHINFO_EXTENSION));
}

/**
 * Check that the content of a file matches its extension (magic bytes) for binary formats
 *
 * @param	string	$path	File path
 * @param	string	$ext	Extension (lower case)
 * @return	bool
 */
function anxhrVaultCheckMagic($path, $ext)
{
	$magics = array(
		'pdf' => array('%PDF-'),
		'png' => array("\x89PNG\r\n\x1a\n"),
		'jpg' => array("\xFF\xD8\xFF"),
		'jpeg' => array("\xFF\xD8\xFF"),
		'docx' => array("PK\x03\x04"),
		'xlsx' => array("PK\x03\x04"),
	);
	if (!isset($magics[$ext])) {
		return true;
	}
	$fh = @fopen($path, 'rb');
	if (!$fh) {
		return false;
	}
	$head = (string) fread($fh, 16);
	fclose($fh);
	foreach ($magics[$ext] as $magic) {
		if (strpos($head, $magic) === 0) {
			return true;
		}
	}
	return false;
}

/**
 * Return true if a period has format YYYY-MM
 *
 * @param	string	$period	Period
 * @return	bool
 */
function anxhrVaultIsValidPeriod($period)
{
	return (bool) preg_match('/^(19|20)\d\d-(0[1-9]|1[0-2])$/', (string) $period);
}

/**
 * Return a clean user agent for the access log
 *
 * @return string
 */
function anxhrVaultUserAgent()
{
	$ua = empty($_SERVER['HTTP_USER_AGENT']) ? '' : (string) $_SERVER['HTTP_USER_AGENT'];
	return dol_trunc(dol_string_nohtmltag(preg_replace('/[\x00-\x1F\x7F]/', '', $ua)), 250, 'right', 'UTF-8', 1);
}

/**
 * Return the client IP for the access log
 *
 * @return string
 */
function anxhrVaultClientIp()
{
	$ip = function_exists('getUserRemoteIP') ? (string) getUserRemoteIP() : '';
	return substr(preg_replace('/[^0-9a-fA-F:.]/', '', $ip), 0, 45);
}

/**
 * Prepare head tabs of the vault pages
 *
 * @return array<array{0:string,1:string,2:string}>
 */
function anxhrVaultPrepareHead()
{
	global $langs, $user;

	$h = 0;
	$head = array();
	$head[$h][0] = dol_buildpath('/anxhr/vault.php', 1);
	$head[$h][1] = $langs->trans('AnxhrVaultDocuments');
	$head[$h][2] = 'documents';
	$h++;
	if ($user->hasRight('anxhr', 'vault', 'admin') || $user->hasRight('anxhr', 'audit', 'read')) {
		$head[$h][0] = dol_buildpath('/anxhr/vault_log.php', 1);
		$head[$h][1] = $langs->trans('AnxhrVaultAccessLog');
		$head[$h][2] = 'log';
		$h++;
	}
	return $head;
}
