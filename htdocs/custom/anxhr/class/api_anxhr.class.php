<?php
/* Copyright (C) 2026 ANX HR module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
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
 * \file    htdocs/custom/anxhr/class/api_anxhr.class.php
 * \ingroup anxhr
 * \brief   REST API for the employee PWA (own time clock and own vault documents only).
 */

use Luracast\Restler\RestException;

dol_include_once('/anxhr/class/timeentry.class.php');
dol_include_once('/anxhr/class/timeday.class.php');
dol_include_once('/anxhr/class/timeperiod.class.php');
dol_include_once('/anxhr/class/vaultdoc.class.php');
dol_include_once('/anxhr/lib/anxhr_time.lib.php');

/**
 * API class for the anxhr employee PWA. Every endpoint works on the authenticated user only.
 *
 * @access protected
 * @class  DolibarrApiAccess {@requires user,external}
 */
class Anxhr extends DolibarrApi
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		global $db;
		$this->db = $db;
	}

	/**
	 * Get the time clock dashboard of the current user
	 *
	 * Return name, state, today's worked and target minutes, month balance, today's entries and the last 7 days.
	 *
	 * @return	array<string,mixed>
	 *
	 * @url	GET me
	 *
	 * @throws RestException 403 Not allowed
	 */
	public function me()
	{
		$user = DolibarrApiAccess::$user;
		$this->checkModule();
		$canTime = $user->hasRight('anxhr', 'time', 'own');
		$res = array(
			'id' => (int) $user->id,
			'login' => $user->login,
			'name' => $user->getFullName(null),
			'rights' => array('time' => (bool) $canTime, 'vault' => (bool) $user->hasRight('anxhr', 'vault', 'own')),
		);
		if (!$canTime) {
			return $res;
		}
		return array_merge($res, $this->buildTimeSummary($user));
	}

	/**
	 * Record a clock action for the current user
	 *
	 * @param	string	$type			in|out|break_start|break_end
	 * @param	int		$homeoffice		1=home office
	 * @param	string	$client_time	Optional local time 'Y-m-d H:i:s' (company time zone) of an action queued offline
	 * @return	array<string,mixed>		New state (same content as GET me) plus result code
	 *
	 * @url	POST clock
	 *
	 * @throws RestException 400 Bad request
	 * @throws RestException 403 Not allowed
	 * @throws RestException 409 Action not allowed in the current state or day locked
	 */
	public function clock($type, $homeoffice = 0, $client_time = '')
	{
		$user = DolibarrApiAccess::$user;
		$this->checkModule();
		if (!$user->hasRight('anxhr', 'time', 'own')) {
			throw new RestException(403, 'Not allowed');
		}
		if (!in_array($type, array('in', 'out', 'break_start', 'break_end'), true)) {
			throw new RestException(400, 'Bad type');
		}
		$now = dol_now();
		$ts = $now;
		if (!empty($client_time)) {
			if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(:\d{2})?$/', $client_time)) {
				throw new RestException(400, 'Bad client_time');
			}
			$ts = anxhrTzToTs(strlen($client_time) == 16 ? $client_time.':00' : $client_time);
			// Offline replays are accepted up to 48 hours back and never in the future.
			if (empty($ts) || $ts > $now + 300 || $ts < $now - 48 * 3600) {
				throw new RestException(400, 'client_time out of range');
			}
			$ts = min($ts, $now);
		}
		$result = TimeEntry::clockForUser($this->db, $user, $type, (int) $homeoffice, 'pwa', $ts);
		if ($result['result'] < 0) {
			if ($result['code'] === 'Error') {
				throw new RestException(500, 'Error: '.$result['error']);
			}
			throw new RestException(409, $result['code']);
		}
		$out = array_merge(array('id' => (int) $user->id, 'login' => $user->login, 'name' => $user->getFullName(null)), $this->buildTimeSummary($user));
		$out['result'] = $result['code'];
		$out['entry_id'] = $result['id'];
		return $out;
	}

	/**
	 * List the vault documents of the current user that are visible to him
	 *
	 * The download itself is done in the browser with the Dolibarr session on /custom/anxhr/vault.php
	 * (vault_download.php requires the session CSRF token, which an API key cannot provide).
	 *
	 * @return	array<int,array<string,mixed>>
	 *
	 * @url	GET vault
	 *
	 * @throws RestException 403 Not allowed
	 */
	public function vault()
	{
		$user = DolibarrApiAccess::$user;
		$this->checkModule();
		if (!$user->hasRight('anxhr', 'vault', 'own')) {
			throw new RestException(403, 'Not allowed');
		}
		$doc = new VaultDoc($this->db);
		$list = $doc->fetchAllForUser($user->id, '', true);
		if (!is_array($list)) {
			throw new RestException(500, 'Error: '.$doc->error);
		}
		$out = array();
		foreach ($list as $d) {
			if (!$d->canRead($user)) {
				continue;
			}
			$out[] = array(
				'id' => (int) $d->id,
				'category' => (string) $d->category,
				'period' => (string) $d->period,
				'label' => (string) $d->label,
				'filename' => (string) $d->filename_original,
				'size' => (int) $d->filesize,
				'unread' => empty($d->first_viewed_at),
				'open_url' => '/custom/anxhr/vault.php',
			);
		}
		return $out;
	}

	/**
	 * Throw if the module is disabled
	 *
	 * @return void
	 * @throws RestException
	 */
	private function checkModule()
	{
		if (!isModEnabled('anxhr')) {
			throw new RestException(403, 'Module anxhr not enabled');
		}
	}

	/**
	 * Build the time summary of a user (same computation as time_clock.php)
	 *
	 * @param	User	$user	User
	 * @return	array<string,mixed>
	 */
	private function buildTimeSummary(User $user)
	{
		$now = dol_now();
		$today = TimeEntry::timestampToDay($now);
		$yesterday = TimeEntry::timestampToDay(TimeEntry::dayToTimestamp($today) - 12 * 3600);
		$state = TimeEntry::getLastStateForUser($this->db, $user->id);
		$last = TimeEntry::getLastEntryForUser($this->db, $user->id);

		$entryObj = new TimeEntry($this->db);
		$shiftDay = $today;
		$entries = $entryObj->fetchForUserDay($user->id, $today);
		if (!is_array($entries)) {
			$entries = array();
		}
		if ($state !== 'out' && empty($entries)) {
			$shiftDay = $yesterday;
			$entries = $entryObj->fetchForUserDay($user->id, $yesterday);
			if (!is_array($entries)) {
				$entries = array();
			}
		}
		$tday = new TimeDay($this->db);
		$live = $tday->computeLive($user->id, $shiftDay, $now);
		if (!is_array($live)) {
			$live = array('worked_min' => 0, 'break_min' => 0, 'target_min' => 0, 'diff_min' => 0, 'violations' => array());
		}

		$year = (int) substr($today, 0, 4);
		$month = (int) substr($today, 5, 2);
		$monthDays = $tday->fetchMonth($user->id, $year, $month);
		$period = new TimePeriod($this->db);
		$period->fk_user = $user->id;
		$period->year = $year;
		$period->month = $month;
		$balance = (int) $period->getPreviousBalance();
		if (is_array($monthDays)) {
			foreach ($monthDays as $d => $row) {
				if ($d !== $shiftDay) {
					$balance += (int) $row['diff_min'];
				}
			}
		}
		if (substr($shiftDay, 0, 7) === substr($today, 0, 7)) {
			$balance += (int) $live['diff_min'];
		}

		$from7 = TimeEntry::timestampToDay(TimeEntry::dayToTimestamp($today) - 6 * 86400 - 3600);
		$days = $tday->fetchDays($user->id, $from7, $today);
		$last7 = array();
		if (is_array($days)) {
			foreach (array_reverse($days, true) as $d => $row) {
				$codes = array();
				foreach ($row['violations'] as $v) {
					if (is_array($v) && !empty($v['code'])) {
						$codes[] = (string) $v['code'];
					}
				}
				$last7[] = array(
					'day' => $d,
					'worked' => (int) ($d === $shiftDay ? $live['worked_min'] : $row['worked_min']),
					'target' => (int) $row['target_min'],
					'diff' => (int) ($d === $shiftDay ? $live['diff_min'] : $row['diff_min']),
					'absence' => (string) $row['absence_code'],
					'violations' => $codes,
				);
			}
		}

		$todayEntries = array();
		foreach ($entries as $e) {
			$todayEntries[] = array('id' => (int) $e['id'], 'type' => $e['type'], 'time' => $e['time'], 'ts' => (int) $e['ts'], 'homeoffice' => (int) $e['homeoffice'], 'source' => $e['source']);
		}

		return array(
			'state' => $state,
			'allowed' => TimeEntry::getNextActions($state),
			'last_ts' => empty($last) ? null : (int) $last['ts'],
			'server_ts' => (int) $now,
			'shift_day' => $shiftDay,
			'worked_today_min' => (int) $live['worked_min'],
			'break_today_min' => (int) $live['break_min'],
			'target_today_min' => (int) $live['target_min'],
			'balance_month_min' => $balance,
			'today_entries' => $todayEntries,
			'last7' => $last7,
		);
	}
}
