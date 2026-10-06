<?php
/* Copyright (C) 2026 ANX HR contributors
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
 * \file    htdocs/custom/anxhr/css/anxhr.css.php
 * \ingroup anxhr
 * \brief   CSS file for module ANX HR.
 */

//if (!defined('NOREQUIREUSER')) define('NOREQUIREUSER','1');	// Not disabled because need to load personalized language
//if (!defined('NOREQUIREDB'))   define('NOREQUIREDB','1');	// Not disabled. Language code is found on url.
if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}
//if (!defined('NOREQUIRETRAN')) define('NOREQUIRETRAN','1');	// Not disabled because need to do translations
//if (!defined('NOCSRFCHECK'))   define('NOCSRFCHECK', 1);		// Should be disable only for special situation
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1);
}
if (!defined('NOLOGIN')) {
	define('NOLOGIN', 1); // File must be accessed by logon page so without login
}
//if (! defined('NOREQUIREMENU'))   define('NOREQUIREMENU',1);  // We need top menu content
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', 1);
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}

session_cache_limiter('public');
// false or '' = keep cache instruction added by server
// 'public'  = remove cache instruction added by server
// and if no cache-control added later, a default cache delay (10800) will be added by PHP.

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
if (!$res && $i > 0 && file_exists(substr($tmp, 0, ($i + 1))."/../main.inc.php")) {
	$res = @include substr($tmp, 0, ($i + 1))."/../main.inc.php";
}
// Try main.inc.php using relative path
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';

// Load user to have $user->conf loaded (not done by default here because of NOLOGIN constant defined) and load permission if we need to use them in CSS
/*if (empty($user->id) && !empty($_SESSION['dol_login'])) {
	$user->fetch('',$_SESSION['dol_login']);
	$user->loadRights();
}*/


// Define css type
header('Content-type: text/css');
// Important: Following code is to cache this file to avoid page request by browser at each Dolibarr page access.
// You can use CTRL+F5 to refresh your browser cache.
header('Cache-Control: max-age=10800, public, must-revalidate');

?>



/* KPI tiles of the HR cockpit: grid so all tiles have the same width and height */
.anxhr-kpi-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
	gap: 12px;
	margin: 10px 0 20px 0;
}
a.anxhr-kpi {
	display: flex;
	flex-direction: column;
	min-height: 110px;
	padding: 12px 15px;
	border: 1px solid var(--colortopbordertitle1, #e0e0e0);
	border-left: 4px solid var(--colortopbordertitle1, #e0e0e0);
	border-radius: 6px;
	background: var(--colorbacklineimpair1, #fff);
	text-decoration: none;
	color: inherit;
	position: relative;
	box-sizing: border-box;
}
a.anxhr-kpi:hover, a.anxhr-kpi:focus-visible {
	box-shadow: 0 2px 6px rgba(0, 0, 0, 0.12);
	text-decoration: none;
}
.anxhr-kpi-picto {
	position: absolute;
	top: 10px;
	right: 12px;
	opacity: 0.35;
	font-size: 1.6em;
}
.anxhr-kpi-value {
	font-size: 2em;
	font-weight: bold;
	line-height: 1.2em;
}
.anxhr-kpi-label {
	opacity: 0.8;
	font-size: 0.9em;
	padding-right: 30px;
}
.anxhr-kpi-link {
	margin-top: auto;
	padding-top: 6px;
	font-size: 0.85em;
	color: var(--colortextlink, #0a1464);
}
a.anxhr-kpi.anxhr-kpi-attention {
	border-left-color: #bc9526;
}
a.anxhr-kpi.anxhr-kpi-zero .anxhr-kpi-value {
	opacity: 0.6;
}

/* Progress bar */
.anxhr-progress {
	width: 100%;
	min-width: 60px;
	height: 8px;
	border-radius: 4px;
	background: #e5e5e5;
	overflow: hidden;
}
.anxhr-progress-bar {
	height: 100%;
	background: #4a8bc2;
}
.anxhr-progress-done .anxhr-progress-bar {
	background: #55a580;
}

/* Values */
.anxhr-negative {
	color: #a94442;
	font-weight: bold;
}
.anxhr-positive {
	color: #3c763d;
}

/* Clock badge in top right menu */
.anxhr-clock-widget {
	vertical-align: middle;
	padding: 0 6px;
}
a.anxhr-clock-badge {
	display: inline-block;
	padding: 2px 8px;
	border-radius: 10px;
	font-size: 0.85em;
	line-height: 1.6em;
	text-decoration: none;
	color: #fff !important;
	white-space: nowrap;
}
a.anxhr-clock-badge.anxhr-clock-in {
	background: #55a580;
}
a.anxhr-clock-badge.anxhr-clock-break {
	background: #bc9526;
}
a.anxhr-clock-badge.anxhr-clock-out {
	background: #888;
}
a.anxhr-clock-badge .pictofixedwidth {
	color: #fff;
}

/* Block titles of the HR tab: label left, actions right */
.anxhr-blocktitle {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: space-between;
	gap: 4px 10px;
}
.anxhr-blocktitle-actions {
	display: inline-flex;
	align-items: center;
	gap: 6px;
	font-weight: normal;
}
.anxhr-blocktitle-actions .btnTitle {
	margin: 0;
	padding: 0 4px;
	min-width: 0;
}

/* Section separators of card pages (same idiom as core extrafield separators) */
tr.anxhr-section td {
	padding-top: 14px !important;
}

/* Add item row of a checklist */
.anxhr-additem {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: 6px 10px;
}
.anxhr-additem input[name=item_label] {
	flex: 1 1 220px;
}

/* Inline add form on HR tab */
form.anxhr-inline-form {
	display: flex;
	flex-wrap: wrap;
	gap: 4px;
	margin-top: 6px;
}

/* Mobile tweaks */
@media only screen and (max-width: 767px) {
	.anxhr-kpi-grid {
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 8px;
	}
	a.anxhr-kpi {
		min-height: 100px;
		padding: 10px;
	}
	.anxhr-kpi-value {
		font-size: 1.6em;
	}
	a.anxhr-clock-badge {
		padding: 2px 5px;
	}
	form.anxhr-inline-form input {
		flex: 1 1 45%;
		max-width: none;
		min-height: 36px;
	}
	form.anxhr-inline-form button {
		flex: 1 1 100%;
		min-height: 40px;
	}
}
