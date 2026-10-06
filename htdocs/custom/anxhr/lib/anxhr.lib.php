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
 * \file    htdocs/custom/anxhr/lib/anxhr.lib.php
 * \ingroup anxhr
 * \brief   Common functions of module ANX HR (admin tabs, visibility checks, table guards)
 */

/**
 * Prepare admin pages header
 *
 * @return array<array{string,string,string}>
 */
function anxhrAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("anxhr@anxhr");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/anxhr/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	$head[$h][0] = dol_buildpath("/anxhr/admin/timemodels.php", 1);
	$head[$h][1] = $langs->trans("AnxhrAdminTimeModels");
	$head[$h][2] = 'timemodels';
	$h++;

	$head[$h][0] = dol_buildpath("/anxhr/admin/rates.php", 1);
	$head[$h][1] = $langs->trans("AnxhrAdminRates");
	$head[$h][2] = 'rates';
	$h++;

	$head[$h][0] = dol_buildpath("/anxhr/admin/checklist_templates.php", 1);
	$head[$h][1] = $langs->trans("AnxhrAdminChecklistTemplates");
	$head[$h][2] = 'checklist_templates';
	$h++;

	$head[$h][0] = dol_buildpath("/anxhr/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'anxhr@anxhr');
	complete_head_from_modules($conf, $langs, null, $head, $h, 'anxhr@anxhr', 'remove');

	return $head;
}

/**
 * Prepare the head of the HR tab of a user (standard user card tabs, HR tab active code is 'anxhr').
 *
 * @param	User	$object		User shown
 * @return	array<array{string,string,string}>
 */
function anxhrUserTabPrepareHead($object)
{
	require_once DOL_DOCUMENT_ROOT.'/core/lib/usergroups.lib.php';

	return user_prepare_head($object);
}

/**
 * Check if a viewer can see the HR data of a user.
 * True if viewer has employee read_all, or is the user himself (with read_own), or is a (direct or indirect) supervisor.
 *
 * @param	User	$viewer		User who wants to see data
 * @param	int		$userid		Id of employee
 * @return	bool
 */
function anxhrCanSeeUser($viewer, $userid)
{
	$userid = (int) $userid;
	if ($userid <= 0 || !is_object($viewer)) {
		return false;
	}
	if ($viewer->hasRight('anxhr', 'employee', 'read_all')) {
		return true;
	}
	if ($viewer->id == $userid) {
		return (bool) $viewer->hasRight('anxhr', 'employee', 'read_own');
	}

	return in_array($userid, anxhrGetSubordinateIds($viewer));
}

/**
 * Return ids of all subordinates (direct and indirect, through llx_user.fk_user) of a user.
 * Result is cached per request.
 *
 * @param	User	$viewer		Supervisor
 * @return	int[]				Ids of subordinates (viewer excluded)
 */
function anxhrGetSubordinateIds($viewer)
{
	static $cache = array();

	if (!is_object($viewer) || empty($viewer->id)) {
		return array();
	}
	if (!isset($cache[$viewer->id])) {
		$ids = array();
		if (method_exists($viewer, 'getAllChildIds')) {
			$ids = $viewer->getAllChildIds(0);
		}
		$cache[$viewer->id] = array_map('intval', is_array($ids) ? $ids : array());
	}

	return $cache[$viewer->id];
}

/**
 * Check if a table exists in the database (result cached per request).
 * Used to keep pages working when tables of optional sub parts are not (yet) installed.
 *
 * @param	DoliDB	$db			Database handler
 * @param	string	$table		Table name WITHOUT prefix (example 'anxhr_time_entry')
 * @return	bool
 */
function anxhrTableExists($db, $table)
{
	static $cache = array();

	$fulltable = $db->prefix().$table;
	if (!isset($cache[$fulltable])) {
		$list = $db->DDLListTables($db->database_name, $fulltable);
		$found = false;
		if (is_array($list)) {
			foreach ($list as $tmp) {
				if (strtolower((string) $tmp) == strtolower($fulltable)) {
					$found = true;
					break;
				}
			}
		}
		$cache[$fulltable] = $found;
	}

	return $cache[$fulltable];
}

/**
 * Return html of a simple progress bar
 *
 * @param	int		$pct	Percent 0..100
 * @return	string			HTML
 */
function anxhrProgressBar($pct)
{
	$pct = max(0, min(100, (int) $pct));
	$cls = ($pct >= 100 ? ' anxhr-progress-done' : '');

	return '<div class="anxhr-progress'.$cls.'" title="'.$pct.' %" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="'.$pct.'"><div class="anxhr-progress-bar" style="width: '.$pct.'%"></div></div>';
}

/**
 * Return badge html for a due date depending on urgency
 *
 * @param	int		$datedue	Timestamp of due date
 * @param	int		$now		Current timestamp
 * @return	string				HTML
 */
function anxhrDueBadge($datedue, $now)
{
	global $langs;

	$days = (int) floor(($datedue - $now) / 86400);
	if ($days < 0) {
		$type = 'danger';
		$label = $langs->trans('AnxhrOverdue');
	} elseif ($days == 0) {
		$type = 'warning';
		$label = $langs->trans('Today');
	} elseif ($days <= 7) {
		$type = 'warning';
		$label = $langs->trans('AnxhrInNbDays', $days);
	} elseif ($days <= 14) {
		$type = 'info';
		$label = $langs->trans('AnxhrInNbDays', $days);
	} else {
		$type = 'secondary';
		$label = $langs->trans('AnxhrInNbDays', $days);
	}

	return dolGetBadge($label, '', $type);
}

/**
 * Format a number of minutes as hours:minutes
 *
 * @param	int		$minutes	Minutes (can be negative)
 * @return	string
 */
function anxhrFormatMinutes($minutes)
{
	$minutes = (int) $minutes;
	$sign = ($minutes < 0 ? '-' : '');
	$minutes = abs($minutes);

	return $sign.sprintf('%d:%02d', (int) floor($minutes / 60), $minutes % 60);
}

/**
 * Translate a code using a map code => translation key (keys are defined in anxhr_hr.lang of HRCORE).
 *
 * @param	array<string,string>	$map	Map code => translation key
 * @param	?string					$code	Code
 * @return	string							Translated label, or code if unknown
 */
function anxhrTranslateCode($map, $code)
{
	global $langs;

	$code = (string) $code;
	if (isset($map[$code])) {
		return $langs->trans($map[$code]);
	}

	return $code;
}

/**
 * Label of a deadline type (codes of HrDeadline::fields['type'])
 *
 * @param	?string	$type	Type code
 * @return	string
 */
function anxhrDeadlineTypeLabel($type)
{
	return anxhrTranslateCode(array(
		'probation_end' => 'AnxhrDeadlineTypeProbationEnd',
		'fixed_term_end' => 'AnxhrDeadlineTypeFixedTermEnd',
		'contract_end' => 'AnxhrDeadlineTypeContractEnd',
		'certificate' => 'AnxhrDeadlineTypeCertificate',
		'permit' => 'AnxhrDeadlineTypePermit',
		'review' => 'AnxhrDeadlineTypeReview',
		'custom' => 'AnxhrDeadlineTypeCustom',
	), $type);
}

/**
 * Label of a handover category (codes of HrHandover::fields['category'])
 *
 * @param	?string	$category	Category code
 * @return	string
 */
function anxhrHandoverCategoryLabel($category)
{
	return anxhrTranslateCode(array(
		'HARDWARE' => 'AnxhrHandoverCatHardware',
		'KEY' => 'AnxhrHandoverCatKey',
		'TOKEN' => 'AnxhrHandoverCatToken',
		'ACCESS' => 'AnxhrHandoverCatAccess',
		'OTHER' => 'AnxhrHandoverCatOther',
	), strtoupper((string) $category));
}

/**
 * Label of a checklist type (codes of HrChecklist::fields['checklist_type'])
 *
 * @param	?string	$type	Type code
 * @return	string
 */
function anxhrChecklistTypeLabel($type)
{
	return anxhrTranslateCode(array(
		'onboarding' => 'AnxhrChecklistTypeOnboarding',
		'offboarding' => 'AnxhrChecklistTypeOffboarding',
		'transfer' => 'AnxhrChecklistTypeTransfer',
	), $type);
}

/**
 * Label of an employment type (codes of HrContract::fields['employment_type'])
 *
 * @param	?string	$type	Type code
 * @return	string
 */
function anxhrEmploymentTypeLabel($type)
{
	return anxhrTranslateCode(array(
		'fulltime' => 'AnxhrEmploymentFulltime',
		'parttime' => 'AnxhrEmploymentParttime',
		'marginal' => 'AnxhrEmploymentMarginal',
		'apprentice' => 'AnxhrEmploymentApprentice',
		'freelancer' => 'AnxhrEmploymentFreelancer',
	), $type);
}
