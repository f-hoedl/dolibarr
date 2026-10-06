<?php
/* Copyright (C) 2026  ANX HR contributors
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
 * \file        htdocs/custom/anxhr/class/timeengine.class.php
 * \ingroup     anxhr
 * \brief       Pure working time calculation engine for Austrian labour law (AZG, ARG, collective agreements).
 *
 * This file has NO Dolibarr dependency (no database, no globals). It can be required standalone
 * (unit tests, API, PWA sync). All durations are integer minutes. All times are wall clock times
 * in the user time zone ('Y-m-d H:i:s'). Wall clock arithmetic is done on a naive UTC clock, so a
 * shift crossing a DST change is off by the DST offset (accepted simplification, documented).
 *
 * Violations are warnings, never blockers: the law (paragraph 26 AZG) requires recording, not prevention.
 */

/**
 * Class TimeEngine
 *
 * Known simplifications (not implemented yet, to be done before productive payroll use):
 * TODO Weekly averaging of normal working time (paragraph 4 (6) AZG, KV "Durchrechnung"): today every day is evaluated alone.
 * TODO KV band between 37/38.5 and 40 hours ("Mehrstunden" with KV specific surcharge, e.g. SWOE-KV, IT-KV): treated as overtime 50.
 * TODO Part-time Mehrarbeit compensated within the quarter or the flexitime period is surcharge free (paragraph 19d (3b) AZG).
 * TODO Split breaks (paragraph 11 (1) AZG: 2 x 15 or 3 x 10 minutes when agreed): only the total break length is checked.
 * TODO Weekly rest of 36 hours (paragraph 3, 4 ARG) and weekend rest: not checked.
 * TODO Average maximum of 48 hours per week over 17 weeks (paragraph 9 (4) AZG): only the absolute 60 hours per week is checked.
 */
class TimeEngine
{
	/** Minimum daily working time that requires a break (paragraph 11 (1) AZG: more than 6 hours). */
	const BREAK_THRESHOLD_MIN = 360;
	/** Minimum break length (paragraph 11 (1) AZG: 30 minutes). */
	const BREAK_REQUIRED_MIN = 30;
	/** Absolute daily maximum (paragraph 9 (1) AZG: 12 hours). */
	const ABSOLUTE_DAILY_MAX_MIN = 720;
	/** Absolute weekly maximum (paragraph 9 (1) AZG: 60 hours). */
	const ABSOLUTE_WEEKLY_MAX_MIN = 3600;
	/** Minimum daily rest (paragraph 12 (1) AZG: 11 consecutive hours). */
	const DAILY_REST_MIN = 660;
	/** Default normal daily maximum (paragraph 9 (1) AZG / paragraph 4b (4) AZG flexitime: 10 hours). */
	const DEFAULT_NORMAL_DAILY_MAX_MIN = 600;

	const LEVEL_INFO = 'info';
	const LEVEL_WARNING = 'warning';
	const LEVEL_ERROR = 'error';

	/**
	 * Absence codes that fulfil the daily target (the day counts as worked, paid leave or sick pay).
	 * 'za' (Zeitausgleich, time off in lieu) is deliberately NOT in this list: a ZA day consumes the
	 * time balance, so the target stays and the diff becomes negative (paragraph 10 (2) AZG / KV).
	 *
	 * @var string[]
	 */
	public static $absenceFulfilsTarget = array('vacation', 'sick', 'holiday', 'special', 'special_leave', 'care', 'personal_holiday', 'prevented');

	/**
	 * Map of violation code to translation key.
	 *
	 * @var array<string,string>
	 */
	public static $messageKeys = array(
		'BREAK_MISSING' => 'AnxhrViolationBreakMissing',
		'BREAK_AUTO_ADDED' => 'AnxhrViolationBreakAutoAdded',
		'MAX_DAILY_10H' => 'AnxhrViolationMaxDaily10h',
		'MAX_DAILY_12H' => 'AnxhrViolationMaxDaily12h',
		'MAX_WEEKLY_60H' => 'AnxhrViolationMaxWeekly60h',
		'REST_11H' => 'AnxhrViolationRest11h',
		'HOLIDAY_WORK' => 'AnxhrViolationHolidayWork',
		'SUNDAY_WORK' => 'AnxhrViolationSundayWork',
		'OPEN_ENTRY' => 'AnxhrViolationOpenEntry',
		'ORDER_INVALID' => 'AnxhrViolationOrderInvalid',
	);

	/**
	 * Compute one working day.
	 *
	 * @param	array<string,mixed>	$input	See CONVENTIONS.md (day, entries, model, kv, contract, is_public_holiday, absence_code, previous_out).
	 *                                      Optional: 'absence_fraction' (0..1, default 1, 0.5 for a half day absence).
	 * @return	array<string,mixed>			Day result
	 */
	public function computeDay(array $input)
	{
		$day = isset($input['day']) ? (string) $input['day'] : '';
		$model = (isset($input['model']) && is_array($input['model'])) ? $input['model'] : array();
		$kv = (isset($input['kv']) && is_array($input['kv'])) ? $input['kv'] : array();
		$contract = (isset($input['contract']) && is_array($input['contract'])) ? $input['contract'] : array();
		$isPublicHoliday = !empty($input['is_public_holiday']);
		$absenceCode = (isset($input['absence_code']) && $input['absence_code'] !== '') ? strtolower((string) $input['absence_code']) : null;
		$absenceFraction = isset($input['absence_fraction']) ? max(0.0, min(1.0, (float) $input['absence_fraction'])) : 1.0;

		$violations = array();
		$dayStart = $this->parseTime($day.' 00:00:00');
		if ($dayStart === null) {
			$dayStart = 0;
		}
		$dayIndex = intdiv($dayStart, 1440);
		$isoWeekday = (int) gmdate('N', $dayStart * 60);
		$monthDay = gmdate('m-d', $dayStart * 60);

		// 1. Normalize entries and walk the state machine to get work and break segments.
		$entries = $this->normalizeEntries(isset($input['entries']) && is_array($input['entries']) ? $input['entries'] : array());
		$walk = $this->buildSegments($entries);
		if ($walk['order_invalid'] > 0) {
			$violations[] = $this->violation('ORDER_INVALID', $walk['order_invalid'], self::LEVEL_WARNING);
		}
		if ($walk['open']) {
			// Forgotten clock out: we count until the last recorded entry (best effort), employee must correct.
			$violations[] = $this->violation('OPEN_ENTRY', 1, self::LEVEL_WARNING);
		}

		// 2. Raw worked and break minutes, classification of night and Sunday / public holiday minutes.
		$nightStart = $this->hhmmToMinutes(isset($kv['night_start']) ? $kv['night_start'] : '22:00', 1320);
		$nightEnd = $this->hhmmToMinutes(isset($kv['night_end']) ? $kv['night_end'] : '06:00', 360);
		$worked = 0;
		$night = 0;
		$sundayHoliday = 0;
		$premium = 0;
		foreach ($walk['work'] as $seg) {
			$cls = $this->classifySegment($seg[0], $seg[1], $nightStart, $nightEnd, $isPublicHoliday ? $dayIndex : null);
			$worked += $cls['total'];
			$night += $cls['night'];
			$sundayHoliday += $cls['sunday_holiday'];
			$premium += $cls['premium'];
		}
		$break = 0;
		foreach ($walk['break'] as $seg) {
			$break += max(0, $seg[1] - $seg[0]);
		}

		// 3. Break rule, paragraph 11 (1) AZG: if daily working time exceeds 6 hours, a break of at least
		// 30 minutes is mandatory. Mode 'auto': the missing minutes are deducted (but never below the 6h
		// threshold, since a shorter day would not have required a break) and flagged as info.
		// Mode 'check' / 'required': the missing break is reported as violation, worked time unchanged.
		$breakRule = isset($model['break_rule']) ? (string) $model['break_rule'] : 'auto';
		$breakAutoAdded = 0;
		if ($worked > self::BREAK_THRESHOLD_MIN && $break < self::BREAK_REQUIRED_MIN) {
			$missing = self::BREAK_REQUIRED_MIN - $break;
			if ($breakRule === 'auto') {
				$breakAutoAdded = min($missing, $worked - self::BREAK_THRESHOLD_MIN);
				$worked -= $breakAutoAdded;
				$break += $breakAutoAdded;
				// Deducted minutes are taken from non premium minutes first.
				$night = min($night, $worked);
				$sundayHoliday = min($sundayHoliday, $worked);
				$premium = min($premium, $worked);
				$violations[] = $this->violation('BREAK_AUTO_ADDED', $breakAutoAdded, self::LEVEL_INFO);
			} else {
				$violations[] = $this->violation('BREAK_MISSING', $missing, self::LEVEL_WARNING);
			}
		}

		// 4. Daily target from the time model (target per ISO weekday).
		$target = 0;
		if (isset($model['daily_minutes']) && is_array($model['daily_minutes'])) {
			$dm = $model['daily_minutes'];
			if (isset($dm[$isoWeekday])) {
				$target = (int) $dm[$isoWeekday];
			} elseif (isset($dm[(string) $isoWeekday])) {
				$target = (int) $dm[(string) $isoWeekday];
			}
		}
		// Public holiday: no work obligation, paid holiday pay (paragraph 7, 9 ARG) -> target 0.
		if ($isPublicHoliday) {
			$target = 0;
		}
		// Dec 24 / Dec 31: free with pay when the collective agreement says so (e.g. SWOE-KV).
		// Work on that day becomes a time credit (diff positive) without surcharge.
		$isFreeKvDay = (($monthDay === '12-24' && !empty($kv['dec24_free'])) || ($monthDay === '12-31' && !empty($kv['dec31_free'])));
		if ($isFreeKvDay) {
			$target = 0;
		}
		// Absences: paid leave / sick leave fulfil the target (UrlG, paragraph 8 AngG / EFZG).
		// 'za' keeps the target so the time balance is consumed (paragraph 10 AZG compensatory time off).
		if ($absenceCode !== null && in_array($absenceCode, self::$absenceFulfilsTarget, true)) {
			$target = (int) round($target * (1 - $absenceFraction));
		}
		$diff = $worked - $target;

		// 5. Overtime classification.
		$normalDailyMax = isset($model['normal_daily_max_min']) && (int) $model['normal_daily_max_min'] > 0 ? (int) $model['normal_daily_max_min'] : self::DEFAULT_NORMAL_DAILY_MAX_MIN;
		$modelType = isset($model['model_type']) ? (string) $model['model_type'] : 'fixed';
		$kvWeekly = isset($kv['weekly_hours']) ? (float) $kv['weekly_hours'] : 0.0;
		$contractWeekly = isset($contract['weekly_hours']) ? (float) $contract['weekly_hours'] : 0.0;
		$isPartTime = ($kvWeekly > 0 && $contractWeekly > 0 && $contractWeekly < $kvWeekly);

		$ot50 = 0;
		$ot100 = 0;
		$extraPartTime = 0;
		if (!$isFreeKvDay) {
			// Surcharges (paragraph 10 AZG) apply to overtime minutes only. Night or Sunday / public holiday
			// minutes worked WITHIN the daily target are normal working time: they are reported separately
			// in night_min / sunday_holiday_min (KV allowances, e.g. night allowance), but never as overtime.
			// Overtime 100 = the premium (night, Sunday, public holiday) part of the overtime minutes,
			// ot100 = min(premium, overtime). The rest of the overtime is overtime 50.
			$excess = max(0, $worked - $target);
			// Sunday and public holidays are weekly / holiday rest (paragraph 3, 7 ARG): never part of a flexitime frame.
			$isRestDay = ($isoWeekday === 7 || $isPublicHoliday);
			if ($isPartTime) {
				// Part-time (paragraph 19d (3a) AZG), checked first so that it also applies to part-timers on a
				// flexitime model: minutes above the agreed daily time up to the full-time daily normal hours of
				// the KV are "Mehrarbeit" (25 percent surcharge), beyond that overtime.
				$kvDaily = (int) round($kvWeekly * 60 / 5);
				$overtime = max(0, $worked - max($target, $kvDaily));
				$ot100 = min($premium, $overtime);
				$ot50 = $overtime - $ot100;
				$extraPartTime = $excess - $overtime;
			} elseif (($modelType === 'flex' || $modelType === 'selfdetermined') && !$isRestDay) {
				// Flexitime (paragraph 4b AZG): minutes above target within the normal daily maximum are
				// flex credit carried over the period (diff_min), not overtime. Only minutes above
				// max(target, normal daily maximum) are overtime.
				$overtime = max(0, $worked - max($target, $normalDailyMax));
				$ot100 = min($premium, $overtime);
				$ot50 = $overtime - $ot100;
			} else {
				// Fixed working time full-time (paragraph 6 (1), 10 AZG): every minute above the target is overtime.
				$ot100 = min($premium, $excess);
				$ot50 = $excess - $ot100;
			}
		}

		// 6. Daily limits (paragraph 9 AZG). Above normal maximum (10h, or the model value) -> warning;
		// above 12h absolute maximum -> error (administrative penalty paragraph 28 AZG).
		if ($worked > $normalDailyMax) {
			$violations[] = $this->violation('MAX_DAILY_10H', $worked, self::LEVEL_WARNING);
		}
		if ($worked > self::ABSOLUTE_DAILY_MAX_MIN) {
			$violations[] = $this->violation('MAX_DAILY_12H', $worked, self::LEVEL_ERROR);
		}

		// 7. Work on a public holiday (paragraph 7, 9 ARG) needs justification, reported as warning.
		if ($isPublicHoliday && $worked > 0) {
			$violations[] = $this->violation('HOLIDAY_WORK', $worked, self::LEVEL_WARNING);
		} elseif ($isoWeekday == 7 && $worked > 0) {
			// Sunday work is only allowed under an exception of the ARG (paragraph 3, 10 ff ARG), reported as info.
			$violations[] = $this->violation('SUNDAY_WORK', $worked, self::LEVEL_INFO);
		}

		// 8. Daily rest of 11 hours (paragraph 12 (1) AZG) between previous clock out and first clock in.
		if (!empty($input['previous_out']) && $walk['first_in'] !== null) {
			$prevOut = $this->parseTime((string) $input['previous_out']);
			if ($prevOut !== null) {
				$gap = $walk['first_in'] - $prevOut;
				if ($gap >= 0 && $gap < self::DAILY_REST_MIN) {
					$violations[] = $this->violation('REST_11H', $gap, self::LEVEL_WARNING);
				}
			}
		}

		$result = array(
			'day' => $day,
			'target_min' => $target,
			'worked_min' => $worked,
			'break_min' => $break,
			'break_auto_added_min' => $breakAutoAdded,
			'diff_min' => $diff,
			'overtime50_min' => $ot50,
			'overtime100_min' => $ot100,
			'extra_parttime_min' => $extraPartTime,
			'night_min' => $night,
			'sunday_holiday_min' => $sundayHoliday,
			'holiday_flag' => $isPublicHoliday ? 1 : 0,
			'kv_free_day' => $isFreeKvDay ? 1 : 0,
			'absence_code' => $absenceCode,
			'part_time' => $isPartTime ? 1 : 0,
			'homeoffice' => $walk['homeoffice'],
			'open_entry' => (bool) $walk['open'],
			'first_in' => $walk['first_in'] !== null ? $this->formatTime($walk['first_in']) : null,
			'last_out' => $walk['last_out'] !== null ? $this->formatTime($walk['last_out']) : null,
			'violations' => $violations,
		);
		$result['summary'] = $this->buildSummary($result);

		return $result;
	}

	/**
	 * Sum several day results and check the weekly maximum (paragraph 9 (1) AZG: 60 hours).
	 *
	 * @param	array<int,array<string,mixed>>	$days	Results of computeDay()
	 * @return	array<string,mixed>
	 */
	public function computeWeek(array $days)
	{
		$keys = array('worked_min', 'target_min', 'diff_min', 'break_min', 'overtime50_min', 'overtime100_min', 'extra_parttime_min', 'night_min');
		$res = array_fill_keys($keys, 0);
		$res['days'] = 0;
		foreach ($days as $d) {
			if (!is_array($d)) {
				continue;
			}
			foreach ($keys as $k) {
				$res[$k] += isset($d[$k]) ? (int) $d[$k] : 0;
			}
			$res['days']++;
		}
		$res['violations'] = array();
		if ($res['worked_min'] > self::ABSOLUTE_WEEKLY_MAX_MIN) {
			$res['violations'][] = $this->violation('MAX_WEEKLY_60H', $res['worked_min'], self::LEVEL_ERROR);
		}
		$res['summary'] = $this->buildSummary($res);

		return $res;
	}

	/**
	 * Format minutes as [+|-]HH:MM.
	 *
	 * @param	int		$min	Minutes
	 * @param	bool	$signed	Always output a sign
	 * @return	string
	 */
	public static function formatMinutes($min, $signed = false)
	{
		$min = (int) $min;
		$sign = $min < 0 ? '-' : ($signed ? '+' : '');
		$abs = abs($min);
		return $sign.sprintf('%02d:%02d', intdiv($abs, 60), $abs % 60);
	}

	/**
	 * Build a violation record.
	 *
	 * @param	string	$code	Violation code
	 * @param	int		$value	Value (minutes or count)
	 * @param	string	$level	info|warning|error
	 * @return	array<string,mixed>
	 */
	protected function violation($code, $value, $level)
	{
		return array(
			'code' => $code,
			'message_key' => isset(self::$messageKeys[$code]) ? self::$messageKeys[$code] : 'AnxhrViolation'.$code,
			'value' => (int) $value,
			'level' => $level,
		);
	}

	/**
	 * Normalize entries: keep valid types and parseable times, sort by time (stable on type order).
	 *
	 * @param	array<int,mixed>	$entries	Raw entries
	 * @return	array<int,array{type:string,t:int,homeoffice:int}>
	 */
	protected function normalizeEntries(array $entries)
	{
		$typeOrder = array('in' => 0, 'break_end' => 1, 'break_start' => 2, 'out' => 3);
		$list = array();
		$i = 0;
		foreach ($entries as $e) {
			if (!is_array($e) || empty($e['type']) || empty($e['time'])) {
				continue;
			}
			$type = strtolower((string) $e['type']);
			if (!isset($typeOrder[$type])) {
				continue;
			}
			$t = $this->parseTime((string) $e['time']);
			if ($t === null) {
				continue;
			}
			// Sort key with seconds precision (computation itself is minute based).
			$sec = preg_match('/:(\d{2}):(\d{2})$/', trim((string) $e['time']), $mm) ? (int) $mm[2] : 0;
			$list[] = array('type' => $type, 't' => $t, 'k' => $t * 60 + $sec, 'homeoffice' => empty($e['homeoffice']) ? 0 : 1, 'o' => $typeOrder[$type], 'i' => $i++);
		}
		usort($list, function ($a, $b) {
			if ($a['k'] !== $b['k']) {
				return $a['k'] < $b['k'] ? -1 : 1;
			}
			// Same second: keep the input order (database order), type order only as last resort.
			if ($a['i'] !== $b['i']) {
				return $a['i'] < $b['i'] ? -1 : 1;
			}
			if ($a['o'] !== $b['o']) {
				return $a['o'] < $b['o'] ? -1 : 1;
			}
			return $a['i'] < $b['i'] ? -1 : 1;
		});
		return $list;
	}

	/**
	 * Walk sorted entries (state machine out -> in -> break -> in -> out) and build segments.
	 * Invalid transitions are counted and skipped (best effort).
	 *
	 * @param	array<int,array{type:string,t:int,homeoffice:int}>	$entries	Sorted entries
	 * @return	array<string,mixed>
	 */
	protected function buildSegments(array $entries)
	{
		$state = 'out';
		$work = array();
		$breaks = array();
		$start = null;
		$invalid = 0;
		$firstIn = null;
		$lastOut = null;
		$lastTime = null;
		$homeoffice = 0;
		foreach ($entries as $e) {
			$t = $e['t'];
			$lastTime = $t;
			if ($e['homeoffice']) {
				$homeoffice = 1;
			}
			switch ($e['type']) {
				case 'in':
					if ($state !== 'out') {
						$invalid++;
						break;
					}
					if ($firstIn === null) {
						$firstIn = $t;
					}
					$state = 'in';
					$start = $t;
					break;
				case 'break_start':
					if ($state !== 'in') {
						$invalid++;
						break;
					}
					$work[] = array($start, $t);
					$state = 'break';
					$start = $t;
					break;
				case 'break_end':
					if ($state !== 'break') {
						$invalid++;
						break;
					}
					$breaks[] = array($start, $t);
					$state = 'in';
					$start = $t;
					break;
				case 'out':
					if ($state === 'out') {
						$invalid++;
						break;
					}
					if ($state === 'in') {
						$work[] = array($start, $t);
					} else {
						// Clock out during a break: the break ends with the clock out.
						$breaks[] = array($start, $t);
					}
					$state = 'out';
					$lastOut = $t;
					break;
			}
		}
		$open = ($state !== 'out');
		if ($open && $start !== null && $lastTime !== null) {
			// Open entry: count until the last recorded entry.
			if ($state === 'in') {
				$work[] = array($start, $lastTime);
			} else {
				$breaks[] = array($start, $lastTime);
			}
		}
		return array('work' => $work, 'break' => $breaks, 'order_invalid' => $invalid, 'open' => $open, 'first_in' => $firstIn, 'last_out' => $lastOut, 'homeoffice' => $homeoffice);
	}

	/**
	 * Classify the minutes of a work segment: total, night, Sunday or public holiday, premium (union).
	 *
	 * @param	int			$from				Start minute (naive epoch minutes)
	 * @param	int			$to					End minute (exclusive)
	 * @param	int			$nightStart			Night start minute of day
	 * @param	int			$nightEnd			Night end minute of day
	 * @param	int|null	$holidayDayIndex	Day index (epoch days) that is a public holiday, or null
	 * @return	array{total:int,night:int,sunday_holiday:int,premium:int}
	 */
	protected function classifySegment($from, $to, $nightStart, $nightEnd, $holidayDayIndex)
	{
		$res = array('total' => 0, 'night' => 0, 'sunday_holiday' => 0, 'premium' => 0);
		if ($to <= $from) {
			return $res;
		}
		$dowCache = array();
		for ($m = $from; $m < $to; $m++) {
			$res['total']++;
			$mod = $m % 1440;
			$dayIdx = intdiv($m, 1440);
			if ($nightStart < $nightEnd) {
				$isNight = ($mod >= $nightStart && $mod < $nightEnd);
			} else {
				$isNight = ($mod >= $nightStart || $mod < $nightEnd);
			}
			if (!isset($dowCache[$dayIdx])) {
				$dowCache[$dayIdx] = (int) gmdate('N', $dayIdx * 86400);
			}
			// Sunday rest (paragraph 3 ARG) and public holiday rest (paragraph 7 ARG).
			$isSunHol = ($dowCache[$dayIdx] === 7 || ($holidayDayIndex !== null && $dayIdx === $holidayDayIndex));
			if ($isNight) {
				$res['night']++;
			}
			if ($isSunHol) {
				$res['sunday_holiday']++;
			}
			if ($isNight || $isSunHol) {
				$res['premium']++;
			}
		}
		return $res;
	}

	/**
	 * Parse 'Y-m-d H:i:s', 'Y-m-d H:i' or 'Y-m-d' into naive epoch minutes.
	 *
	 * @param	string	$str	Date time string
	 * @return	int|null
	 */
	protected function parseTime($str)
	{
		$str = trim($str);
		if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{1,2}):(\d{2})(?::(\d{2}))?)?$/', $str, $m)) {
			return null;
		}
		$ts = gmmktime(isset($m[4]) && $m[4] !== '' ? (int) $m[4] : 0, isset($m[5]) && $m[5] !== '' ? (int) $m[5] : 0, 0, (int) $m[2], (int) $m[3], (int) $m[1]);
		if ($ts === false) {
			return null;
		}
		return intdiv($ts, 60);
	}

	/**
	 * Format naive epoch minutes as 'Y-m-d H:i:s'.
	 *
	 * @param	int		$min	Minutes
	 * @return	string
	 */
	protected function formatTime($min)
	{
		return gmdate('Y-m-d H:i:s', $min * 60);
	}

	/**
	 * Convert 'HH:MM' into minutes of day.
	 *
	 * @param	string	$hhmm		Time
	 * @param	int		$default	Default value
	 * @return	int
	 */
	protected function hhmmToMinutes($hhmm, $default)
	{
		if (preg_match('/^(\d{1,2}):(\d{2})/', (string) $hhmm, $m)) {
			return ((int) $m[1] * 60 + (int) $m[2]) % 1440;
		}
		return $default;
	}

	/**
	 * Build human readable summary values.
	 *
	 * @param	array<string,mixed>	$r	Result
	 * @return	array<string,string>
	 */
	protected function buildSummary(array $r)
	{
		$codes = array();
		foreach ((isset($r['violations']) ? $r['violations'] : array()) as $v) {
			$codes[] = $v['code'];
		}
		$s = array(
			'worked' => self::formatMinutes(isset($r['worked_min']) ? $r['worked_min'] : 0),
			'target' => self::formatMinutes(isset($r['target_min']) ? $r['target_min'] : 0),
			'diff' => self::formatMinutes(isset($r['diff_min']) ? $r['diff_min'] : 0, true),
			'break' => self::formatMinutes(isset($r['break_min']) ? $r['break_min'] : 0),
			'overtime50' => self::formatMinutes(isset($r['overtime50_min']) ? $r['overtime50_min'] : 0),
			'overtime100' => self::formatMinutes(isset($r['overtime100_min']) ? $r['overtime100_min'] : 0),
			'extra_parttime' => self::formatMinutes(isset($r['extra_parttime_min']) ? $r['extra_parttime_min'] : 0),
			'night' => self::formatMinutes(isset($r['night_min']) ? $r['night_min'] : 0),
			'violations' => implode(', ', $codes),
		);
		$s['text'] = 'Worked '.$s['worked'].' / target '.$s['target'].' / diff '.$s['diff'].($codes ? ' / '.$s['violations'] : '');
		return $s;
	}
}
