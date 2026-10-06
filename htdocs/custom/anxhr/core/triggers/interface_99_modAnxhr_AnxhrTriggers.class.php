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
 * \file    htdocs/custom/anxhr/core/triggers/interface_99_modAnxhr_AnxhrTriggers.class.php
 * \ingroup anxhr
 * \brief   Triggers of module ANX HR (leave integration, user lifecycle)
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';


/**
 *  Class of triggers for Anxhr module
 */
class InterfaceAnxhrTriggers extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->family = "hr";
		$this->description = "ANX HR triggers.";
		$this->version = '1.0.0';
		$this->picto = 'fa-id-card';
	}

	/**
	 * Function called when a Dolibarr business event is done.
	 *
	 * @param string 		$action 	Event action code
	 * @param CommonObject 	$object 	Object
	 * @param User 			$user 		Object user
	 * @param Translate 	$langs 		Object langs
	 * @param Conf 			$conf 		Object conf
	 * @return int              		Return integer <0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('anxhr')) {
			return 0;
		}

		switch ($action) {
			case 'HOLIDAY_APPROVE':
			case 'HOLIDAY_CANCEL':
			case 'HOLIDAY_MODIFY':
			case 'HOLIDAY_DELETE':
				return $this->handleHolidayEvent($action, $object, $user);

			case 'USER_CREATE':
				// Nothing yet. Checklists are instantiated when a contract is created (see HRCORE).
				return 0;

			case 'USER_DELETE':
				dol_syslog("Trigger '".$this->name."' for action '".$action."' user id=".((int) $object->id)." deleted. HR data are kept for retention.", LOG_INFO);
				return 0;

			default:
				return 0;
		}
	}

	/**
	 * Handle approval, cancellation, modification and deletion of leave requests of ANY type: the computed
	 * days are recomputed and TimeDay::mapLeaveType() (one shared helper) decides how the leave type counts
	 * (vacation / sick fulfil the target, ZA consumes the balance, other types keep the target).
	 *
	 * @param	string			$action		Trigger code
	 * @param	CommonObject	$object		Holiday object
	 * @param	User			$user		User doing the action
	 * @return	int							<0 if KO, 0 if nothing done, >0 if OK
	 */
	private function handleHolidayEvent($action, $object, User $user)
	{
		if (empty($object->fk_user)) {
			return 0;
		}
		$file = dol_buildpath('/anxhr/class/timeperiod.class.php', 0);
		if (!file_exists($file)) {
			return 0;
		}
		include_once $file;

		dol_syslog("Trigger '".$this->name."' for action '".$action."' on leave id=".((int) $object->id), LOG_DEBUG);

		$method = ($action == 'HOLIDAY_APPROVE' ? 'onLeaveApproved' : 'onLeaveCanceled');
		if (class_exists('AnxhrTimeIntegration') && method_exists('AnxhrTimeIntegration', $method)) {
			$result = call_user_func(array('AnxhrTimeIntegration', $method), $this->db, $object, $user);
			if (is_numeric($result) && $result < 0) {
				$this->errors[] = 'AnxhrTimeIntegration::'.$method.' failed';
				return -1;
			}
			return 1;
		}

		return 0;
	}
}
