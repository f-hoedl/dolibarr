<?php
/* Copyright (C) 2026		ANX HR contributors
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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/custom/anxhr/ajax/clockstate.php
 *	\ingroup    anxhr
 *	\brief      Return clock state (in, break, out) of current user as JSON. Read only.
 */

if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', '1');
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', '1');
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', '1');
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}
if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}

// Load Dolibarr environment
$res = 0;
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
dol_include_once('/anxhr/lib/anxhr.lib.php');
dol_include_once('/anxhr/class/actions_anxhr.class.php');

$token = GETPOST('token', 'alpha');

top_httphead('application/json');

// Security check
$tokenok = ($token !== '' && (hash_equals((string) newToken(), $token) || hash_equals((string) currentToken(), $token)));
if (!isModEnabled('anxhr') || empty($user->id) || !$user->hasRight('anxhr', 'time', 'own') || !$tokenok) {
	http_response_code(403);
	print json_encode(array('error' => 'forbidden'));
	exit;
}


/*
 * Actions
 */

// None (read only)


/*
 * View
 */

$langs->load('anxhr@anxhr');

if (!anxhrTableExists($db, 'anxhr_time_entry')) {
	print json_encode(array('state' => 'unknown', 'label' => $langs->transnoentitiesnoconv('AnxhrClockStateUnknown')));
	$db->close();
	exit;
}

$state = ActionsAnxhr::getClockState($db, (int) $user->id);
print json_encode(array('state' => $state, 'label' => $langs->transnoentitiesnoconv('AnxhrClockState'.ucfirst($state))));

$db->close();
