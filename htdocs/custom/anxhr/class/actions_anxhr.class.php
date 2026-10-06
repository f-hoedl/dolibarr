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
 * \file    htdocs/custom/anxhr/class/actions_anxhr.class.php
 * \ingroup anxhr
 * \brief   Hooks of module ANX HR (clock widget, user card button, expense report block, notifications)
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonhookactions.class.php';

/**
 * Class ActionsAnxhr
 */
class ActionsAnxhr extends CommonHookActions
{
	/**
	 * @var DoliDB Database handler.
	 */
	public $db;

	/**
	 * @var string Error code (or message)
	 */
	public $error = '';

	/**
	 * @var string[] Errors
	 */
	public $errors = array();

	/**
	 * @var mixed[] Hook results. Propagated to $hookmanager->resArray for later reuse
	 */
	public $results = array();

	/**
	 * @var ?string String displayed by executeHook() immediately after return
	 */
	public $resprints;

	/**
	 * @var int		Priority of hook (50 is used if value is not defined)
	 */
	public $priority;

	/**
	 * Notification codes provided by this module
	 */
	const NOTIF_CODES = array('ANXHR_DEADLINE_REMIND', 'ANXHR_TIMEPERIOD_APPROVE', 'ANXHR_TIMECORRECTION_APPROVE');


	/**
	 * Constructor
	 *
	 *  @param	DoliDB	$db      Database handler
	 */
	public function __construct($db)
	{
		$this->db = $db;
	}

	/**
	 * Add a small clock state badge into the top right menu.
	 *
	 * @param	array<string,mixed>	$parameters		Hook metadata
	 * @param	?CommonObject		$object			Object
	 * @param	?string				$action			Action
	 * @param	HookManager			$hookmanager	Hook manager
	 * @return	int									0
	 */
	public function printTopRightMenu($parameters, &$object, &$action, $hookmanager)
	{
		global $user, $langs;

		$this->resprints = '';
		$this->results = array();

		if (!isModEnabled('anxhr') || empty($user->id) || !$user->hasRight('anxhr', 'time', 'own')) {
			return 0;
		}
		if (!$this->timeEntryTableExists()) {
			return 0;
		}

		$langs->load('anxhr@anxhr');

		$state = self::getClockState($this->db, $user->id);
		$url = dol_buildpath('/anxhr/time_clock.php', 1);
		$ajaxurl = dol_buildpath('/anxhr/ajax/clockstate.php', 1);

		$html = '<div class="inline-block nowrap anxhr-clock-widget">';
		$html .= '<a href="'.dol_escape_htmltag($url).'" id="anxhr-clock-badge" class="anxhr-clock-badge anxhr-clock-'.$state.'"';
		$html .= ' data-ajaxurl="'.dol_escape_htmltag($ajaxurl).'"';
		$html .= ' title="'.dolPrintHTMLForAttribute($langs->trans('AnxhrClockState'.ucfirst($state))).'">';
		$html .= img_picto('', 'fa-clock', 'class="pictofixedwidth"');
		$html .= '<span class="anxhr-clock-label hideonsmartphone">'.dol_escape_htmltag($langs->trans('AnxhrClockState'.ucfirst($state))).'</span>';
		$html .= '</a>';
		$html .= '</div>';

		$this->resprints = $html;

		return 0;
	}

	/**
	 * Add button "HR file" on user card.
	 *
	 * @param	array<string,mixed>	$parameters		Hook metadata
	 * @param	CommonObject		$object			Object (User)
	 * @param	?string				$action			Action
	 * @param	HookManager			$hookmanager	Hook manager
	 * @return	int									0
	 */
	public function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $user, $langs;

		$this->resprints = '';
		$this->results = array();

		$contexts = explode(':', (string) $parameters['context']);
		if (!in_array('usercard', $contexts) || !isModEnabled('anxhr')) {
			return 0;
		}
		if (empty($object->id) || in_array($action, array('create', 'edit'))) {
			return 0;
		}

		dol_include_once('/anxhr/lib/anxhr.lib.php');
		if (!function_exists('anxhrCanSeeUser') || !anxhrCanSeeUser($user, (int) $object->id)) {
			return 0;
		}

		$langs->load('anxhr@anxhr');
		$url = dol_buildpath('/anxhr/user_tab.php', 1).'?id='.((int) $object->id);
		print dolGetButtonAction('', $langs->trans('AnxhrHrFile'), 'default', $url, 'anxhr-hrfile', 1);

		return 0;
	}

	/**
	 * Add placeholder block on expense report card. Content is provided by the Travel part (js/traveldiet).
	 *
	 * @param	array<string,mixed>	$parameters		Hook metadata
	 * @param	CommonObject		$object			Object
	 * @param	?string				$action			Action
	 * @param	HookManager			$hookmanager	Hook manager
	 * @return	int									0
	 */
	public function formObjectOptions($parameters, &$object, &$action, $hookmanager)
	{
		global $langs;

		$this->resprints = '';
		$this->results = array();

		$contexts = explode(':', (string) $parameters['context']);
		if (!in_array('expensereportcard', $contexts) || !isModEnabled('anxhr')) {
			return 0;
		}

		$langs->load('anxhr@anxhr');
		$colspan = empty($parameters['colspan']) ? '' : (string) $parameters['colspan'];

		$this->resprints = '<tr class="anxhr-travel-diet-row"><td>'.dol_escape_htmltag($langs->trans('AnxhrTravelDiet')).'</td>';
		$this->resprints .= '<td'.$colspan.'><div id="anxhr-travel-diet" data-id="'.((int) $object->id).'">';
		$this->resprints .= '<span class="opacitymedium">'.dol_escape_htmltag($langs->trans('AnxhrTravelDietPlaceholder')).'</span>';
		$this->resprints .= '</div></td></tr>';

		return 0;
	}

	/**
	 * Declare notification codes of this module to the Notification module.
	 *
	 * @param	array<string,mixed>	$parameters		Hook metadata
	 * @param	?CommonObject		$object			Object
	 * @param	?string				$action			Action
	 * @param	HookManager			$hookmanager	Hook manager
	 * @return	int									0
	 */
	public function notifsupported($parameters, &$object, &$action, $hookmanager)
	{
		$this->resprints = '';
		$this->results = array();

		if (!isModEnabled('anxhr')) {
			return 0;
		}
		$this->results = array('arrayofnotifsupported' => self::NOTIF_CODES);

		return 0;
	}

	/**
	 * Return the clock state of a user: 'in', 'break' or 'out'.
	 * Caller must make sure that table llx_anxhr_time_entry exists.
	 *
	 * @param	DoliDB	$db			Database handler
	 * @param	int		$userid		User id
	 * @return	string				'in', 'break' or 'out'
	 */
	public static function getClockState($db, $userid)
	{
		// Same rule as the clock page (one source of truth, same open state window).
		dol_include_once('/anxhr/class/timeentry.class.php');

		return TimeEntry::getLastStateForUser($db, (int) $userid);
	}

	/**
	 * Check if time entry table exists. The positive result is cached in session to avoid
	 * a SHOW TABLES on every page.
	 *
	 * @return	bool
	 */
	private function timeEntryTableExists()
	{
		if (!empty($_SESSION['anxhr_has_time_entry_table'])) {
			return true;
		}
		dol_include_once('/anxhr/lib/anxhr.lib.php');
		if (!function_exists('anxhrTableExists')) {
			return false;
		}
		$exists = anxhrTableExists($this->db, 'anxhr_time_entry');
		if ($exists) {
			$_SESSION['anxhr_has_time_entry_table'] = 1;
		}

		return $exists;
	}
}
