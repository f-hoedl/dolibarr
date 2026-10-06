<?php
/* Copyright (C) 2004-2017  Laurent Destailleur     <eldy@users.sourceforge.net>
 * Copyright (C) 2024       Frédéric France         <frederic.france@free.fr>
 * Copyright (C) 2026		ANX HR contributors
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
 * \file    htdocs/custom/anxhr/admin/setup.php
 * \ingroup anxhr
 * \brief   ANX HR setup page.
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
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

// Libraries
require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php";
require_once '../lib/anxhr.lib.php';

/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var HookManager $hookmanager
 * @var Translate $langs
 * @var User $user
 */

// Translations
$langs->loadLangs(array("admin", "anxhr@anxhr"));

$hookmanager->initHooks(array('anxhrsetup', 'globalsetup'));

// Parameters
$action = GETPOST('action', 'aZ09');
$backtopage = GETPOST('backtopage', 'alpha');
$modulepart = GETPOST('modulepart', 'aZ09');	// Used by actions_setmoduleoptions.inc.php

// Access control
if (!$user->admin) {
	accessforbidden();
}

if (!class_exists('FormSetup')) {
	require_once DOL_DOCUMENT_ROOT.'/core/class/html.formsetup.class.php';
}
$formSetup = new FormSetup($db);

// Section: general
$formSetup->newItem('AnxhrSetupSectionGeneral')->setAsTitle();

$item = $formSetup->newItem('ANXHR_DEFAULT_KV');
$item->setAsString();
$item->defaultFieldValue = 'SWOE';
$item->helpText = $langs->transnoentities('ANXHR_DEFAULT_KVTooltip');
$item->cssClass = 'maxwidth200';

$item = $formSetup->newItem('ANXHR_VACATION_IN_HOURS');
$item->setAsYesNo();
$item->helpText = $langs->transnoentities('ANXHR_VACATION_IN_HOURSTooltip');

// Section: time tracking
$formSetup->newItem('AnxhrSetupSectionTime')->setAsTitle();

$item = $formSetup->newItem('ANXHR_CLOCK_ALLOW_SELF_CORRECTION_SAME_DAY');
$item->setAsYesNo();
$item->helpText = $langs->transnoentities('ANXHR_CLOCK_ALLOW_SELF_CORRECTION_SAME_DAYTooltip');

$item = $formSetup->newItem('ANXHR_PERIOD_AUTOCREATE_DAY');
$item->setAsNumber(1, 28, 1);
$item->defaultFieldValue = '1';
$item->helpText = $langs->transnoentities('ANXHR_PERIOD_AUTOCREATE_DAYTooltip');
$item->cssClass = 'maxwidth75';

// Section: data protection
$formSetup->newItem('AnxhrSetupSectionPrivacy')->setAsTitle();

$item = $formSetup->newItem('ANXHR_RETENTION_YEARS');
$item->setAsNumber(1, 30, 1);
$item->defaultFieldValue = '7';
$item->helpText = $langs->transnoentities('ANXHR_RETENTION_YEARSTooltip');
$item->cssClass = 'maxwidth75';


/*
 * Actions
 */

include DOL_DOCUMENT_ROOT.'/core/actions_setmoduleoptions.inc.php';

$action = 'edit';


/*
 * View
 */

$form = new Form($db);

$help_url = '';
$title = "AnxhrSetup";

llxHeader('', $langs->trans($title), $help_url, '', 0, 0, '', '', '', 'mod-anxhr page-admin');

// Subheader
$linkback = '<a href="'.($backtopage ? dol_escape_htmltag($backtopage) : DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1').'">'.img_picto($langs->trans("BackToModuleList"), 'back', 'class="pictofixedwidth"').'<span class="hideonsmartphone">'.$langs->trans("BackToModuleList").'</span></a>';

print load_fiche_titre($langs->trans($title), $linkback, 'title_setup');

// Configuration header
$head = anxhrAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans($title), -1, 'fa-id-card');

print '<span class="opacitymedium">'.$langs->trans("AnxhrSetupPage").'</span><br><br>';

if (!empty($formSetup->items)) {
	print $formSetup->generateOutput(true);
	print '<br>';
}

// Page end
print dol_get_fiche_end();

llxFooter();
$db->close();
