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
 * \file        htdocs/custom/anxhr/class/traveldiet.class.php
 * \ingroup     anxhr
 * \brief       Pure calculator for Austrian travel allowances (Taggeld, Naechtigungsgeld, Kilometergeld).
 *
 * No Dolibarr dependency. Rates are passed in as data (table with valid_from), never hard coded in the
 * calculation. defaultAustrianRates() only provides seed data for installation and tests.
 */

/**
 * Class TravelDiet
 */
class TravelDiet
{
	/**
	 * @var array<string,array<int,array{valid_from:string,amount:float,cap_per_year:?float}>>	Rates by code
	 */
	protected $rates = array();

	/**
	 * Meal deduction in percent of the full daily rate per provided lunch or dinner.
	 * Paragraph 26 Z 4 EStG / LStR Rz 735: 15.00 EUR per meal at 30.00 EUR (50 percent).
	 *
	 * @var float
	 */
	public $mealDeductionPct = 50.0;

	/**
	 * Constructor
	 *
	 * @param	array<string,array<int,array<string,mixed>>>	$rates		Rates: code => list of array(valid_from, amount, cap_per_year)
	 * @param	float											$mealPct	Meal deduction percentage per meal
	 */
	public function __construct(array $rates = array(), $mealPct = 50.0)
	{
		foreach ($rates as $code => $list) {
			if (!is_array($list)) {
				continue;
			}
			foreach ($list as $r) {
				if (!is_array($r) || empty($r['valid_from']) || !isset($r['amount'])) {
					continue;
				}
				$this->rates[strtolower((string) $code)][] = array(
					'valid_from' => substr((string) $r['valid_from'], 0, 10),
					'amount' => (float) $r['amount'],
					'cap_per_year' => (isset($r['cap_per_year']) && $r['cap_per_year'] !== null && $r['cap_per_year'] !== '') ? (float) $r['cap_per_year'] : null,
				);
			}
		}
		foreach ($this->rates as $code => $list) {
			usort($list, function ($a, $b) {
				return strcmp($a['valid_from'], $b['valid_from']);
			});
			$this->rates[$code] = $list;
		}
		$this->mealDeductionPct = (float) $mealPct;
	}

	/**
	 * Return the rate valid at a date (latest valid_from <= date).
	 *
	 * @param	string	$code	Rate code
	 * @param	string	$date	Date Y-m-d
	 * @return	array{valid_from:string,amount:float,cap_per_year:?float}|null
	 */
	public function getRate($code, $date)
	{
		$code = strtolower((string) $code);
		$date = substr((string) $date, 0, 10);
		if (empty($this->rates[$code])) {
			return null;
		}
		$found = null;
		foreach ($this->rates[$code] as $r) {
			if ($r['valid_from'] <= $date) {
				$found = $r;
			}
		}
		return $found;
	}

	/**
	 * Compute per diem for a business trip, split per calendar day.
	 *
	 * Rule (paragraph 26 Z 4 lit b EStG): trip longer than 3 hours -> one twelfth of the daily rate per
	 * started hour, full rate for 12 twelfths. We apply it per calendar day (KV / LStR Rz 721 calendar
	 * day method). Night allowance (paragraph 26 Z 4 lit c EStG) per night without receipt.
	 * Abroad (paragraph 26 Z 4 lit d EStG, Reisegebuehrenvorschrift): rates taggeld_<ISO2> /
	 * naechtigung_<ISO2>; missing rate falls back to the inland rate and sets flag rate_missing.
	 *
	 * @param	array<string,mixed>	$trip	start, end ('Y-m-d H:i'), country (ISO2), nights (int|null), meals_provided (date => array(lunch, dinner))
	 * @return	array<string,mixed>			array('days' => list, 'totals' => array, 'rate_missing' => bool, 'nights' => int, 'error' => string|null)
	 */
	public function computeTrip(array $trip)
	{
		$res = array('days' => array(), 'totals' => array('hours' => 0.0, 'twelfths' => 0, 'taggeld_amount' => 0.0, 'meal_deduction' => 0.0, 'nacht_amount' => 0.0, 'total' => 0.0), 'rate_missing' => false, 'nights' => 0, 'error' => null);
		$start = $this->parse(isset($trip['start']) ? $trip['start'] : '');
		$end = $this->parse(isset($trip['end']) ? $trip['end'] : '');
		if ($start === null || $end === null || $end <= $start) {
			$res['error'] = 'ErrorInvalidTripDates';
			return $res;
		}
		$country = strtoupper(isset($trip['country']) && $trip['country'] !== '' ? (string) $trip['country'] : 'AT');
		$codeDay = ($country === 'AT') ? 'taggeld_in' : 'taggeld_'.strtolower($country);
		$codeNight = ($country === 'AT') ? 'naechtigung_in' : 'naechtigung_'.strtolower($country);
		$meals = (isset($trip['meals_provided']) && is_array($trip['meals_provided'])) ? $trip['meals_provided'] : array();

		// Split into calendar days.
		$segments = array();
		$cursor = $start;
		while ($cursor < $end) {
			$dayStart = gmmktime(0, 0, 0, (int) gmdate('m', $cursor), (int) gmdate('d', $cursor), (int) gmdate('Y', $cursor));
			$next = $dayStart + 86400;
			$segEnd = min($next, $end);
			$segments[] = array('date' => gmdate('Y-m-d', $cursor), 'seconds' => $segEnd - $cursor);
			$cursor = $segEnd;
		}

		$nights = (isset($trip['nights']) && $trip['nights'] !== null && $trip['nights'] !== '') ? max(0, (int) $trip['nights']) : max(0, count($segments) - 1);
		$res['nights'] = $nights;

		$nightsLeft = $nights;
		foreach ($segments as $idx => $seg) {
			$hours = $seg['seconds'] / 3600;
			$twelfths = ($hours > 3) ? (int) min(12, ceil($hours - 1e-9)) : 0;
			$rate = $this->rateWithFallback($codeDay, 'taggeld_in', $seg['date'], $res['rate_missing']);
			$daily = $rate !== null ? $rate['amount'] : 0.0;
			$taggeld = round($daily * $twelfths / 12, 2);

			// Meal deduction: per provided lunch / dinner a percentage of the FULL daily rate, capped at the day's amount.
			$mealCount = 0;
			if (isset($meals[$seg['date']]) && is_array($meals[$seg['date']])) {
				$mealCount = (empty($meals[$seg['date']]['lunch']) ? 0 : 1) + (empty($meals[$seg['date']]['dinner']) ? 0 : 1);
			}
			$deduction = round(min($taggeld, $daily * $this->mealDeductionPct / 100 * $mealCount), 2);

			// Nights are attributed to the calendar days of the trip in order (all but the last day by default).
			$nachtAmount = 0.0;
			$nightsHere = 0;
			$isLast = ($idx === count($segments) - 1);
			if ($nightsLeft > 0) {
				$nightsHere = $isLast ? $nightsLeft : 1;
			}
			if ($nightsHere > 0) {
				$nrate = $this->rateWithFallback($codeNight, 'naechtigung_in', $seg['date'], $res['rate_missing']);
				$nachtAmount = round(($nrate !== null ? $nrate['amount'] : 0.0) * $nightsHere, 2);
				$nightsLeft -= $nightsHere;
			}

			$total = round($taggeld - $deduction + $nachtAmount, 2);
			$res['days'][] = array(
				'date' => $seg['date'],
				'hours' => round($hours, 2),
				'twelfths' => $twelfths,
				'taggeld_rate' => $daily,
				'taggeld_amount' => $taggeld,
				'meal_deduction' => $deduction,
				'nights' => $nightsHere,
				'nacht_amount' => $nachtAmount,
				'total' => $total,
			);
			$res['totals']['hours'] += $hours;
			$res['totals']['twelfths'] += $twelfths;
			$res['totals']['taggeld_amount'] += $taggeld;
			$res['totals']['meal_deduction'] += $deduction;
			$res['totals']['nacht_amount'] += $nachtAmount;
			$res['totals']['total'] += $total;
		}
		foreach (array('hours', 'taggeld_amount', 'meal_deduction', 'nacht_amount', 'total') as $k) {
			$res['totals'][$k] = round($res['totals'][$k], 2);
		}
		return $res;
	}

	/**
	 * Compute mileage allowance (Kilometergeld, paragraph 26 Z 4 lit a EStG, Reisegebuehrenvorschrift
	 * paragraph 10, BBG 2025). Annual tax free caps per vehicle (car 30000 km, bike 3000 km) apply via
	 * cap_per_year of the rate. Passenger supplement per passenger and km (car only).
	 *
	 * @param	array<string,mixed>	$in		date, vehicle (car|motorbike|bike), km, passengers, km_year_so_far
	 * @return	array<string,mixed>			rate, km, km_capped, amount, amount_capped, taxable_excess, passenger_amount, rate_missing
	 */
	public function computeKm(array $in)
	{
		$date = isset($in['date']) ? substr((string) $in['date'], 0, 10) : gmdate('Y-m-d');
		$vehicle = isset($in['vehicle']) ? strtolower((string) $in['vehicle']) : 'car';
		$km = isset($in['km']) ? max(0.0, (float) $in['km']) : 0.0;
		$passengers = isset($in['passengers']) ? max(0, (int) $in['passengers']) : 0;
		$soFar = isset($in['km_year_so_far']) ? max(0.0, (float) $in['km_year_so_far']) : 0.0;

		$rate = $this->getRate('km_'.$vehicle, $date);
		$res = array('rate' => 0.0, 'km' => $km, 'km_capped' => $km, 'amount' => 0.0, 'amount_capped' => 0.0, 'taxable_excess' => 0.0, 'passenger_amount' => 0.0, 'rate_missing' => ($rate === null));
		if ($rate === null) {
			return $res;
		}
		$res['rate'] = $rate['amount'];
		$kmCapped = $km;
		if ($rate['cap_per_year'] !== null && $rate['cap_per_year'] > 0) {
			$kmCapped = max(0.0, min($km, $rate['cap_per_year'] - $soFar));
		}
		$passengerRate = 0.0;
		if ($vehicle === 'car' && $passengers > 0) {
			$prate = $this->getRate('km_passenger', $date);
			$passengerRate = $prate !== null ? $prate['amount'] * $passengers : 0.0;
		}
		$res['km_capped'] = $kmCapped;
		$res['amount'] = round($km * ($rate['amount'] + $passengerRate), 2);
		$res['amount_capped'] = round($kmCapped * ($rate['amount'] + $passengerRate), 2);
		$res['passenger_amount'] = round($kmCapped * $passengerRate, 2);
		$res['taxable_excess'] = round($res['amount'] - $res['amount_capped'], 2);
		return $res;
	}

	/**
	 * Default Austrian rates (seed data, verified 10/2026). Format: code => list of rate rows.
	 *
	 * @return array<string,array<int,array{valid_from:string,amount:float,cap_per_year:?float,label:string}>>
	 */
	public static function defaultAustrianRates()
	{
		return array(
			'taggeld_in' => array(
				array('valid_from' => '1900-01-01', 'amount' => 26.40, 'cap_per_year' => null, 'label' => 'Taggeld Inland'),
				array('valid_from' => '2025-01-01', 'amount' => 30.00, 'cap_per_year' => null, 'label' => 'Taggeld Inland'),
			),
			'naechtigung_in' => array(
				array('valid_from' => '1900-01-01', 'amount' => 15.00, 'cap_per_year' => null, 'label' => 'Naechtigungsgeld Inland'),
				array('valid_from' => '2025-01-01', 'amount' => 17.00, 'cap_per_year' => null, 'label' => 'Naechtigungsgeld Inland'),
			),
			'km_car' => array(
				array('valid_from' => '1900-01-01', 'amount' => 0.42, 'cap_per_year' => 30000.0, 'label' => 'Kilometergeld PKW'),
				array('valid_from' => '2025-01-01', 'amount' => 0.50, 'cap_per_year' => 30000.0, 'label' => 'Kilometergeld PKW'),
			),
			'km_motorbike' => array(
				array('valid_from' => '1900-01-01', 'amount' => 0.24, 'cap_per_year' => null, 'label' => 'Kilometergeld Motorrad'),
				array('valid_from' => '2025-01-01', 'amount' => 0.50, 'cap_per_year' => null, 'label' => 'Kilometergeld Motorrad'),
				array('valid_from' => '2025-07-01', 'amount' => 0.25, 'cap_per_year' => null, 'label' => 'Kilometergeld Motorrad'),
			),
			'km_bike' => array(
				array('valid_from' => '1900-01-01', 'amount' => 0.38, 'cap_per_year' => 2500.0, 'label' => 'Kilometergeld Fahrrad'),
				array('valid_from' => '2025-01-01', 'amount' => 0.50, 'cap_per_year' => 3000.0, 'label' => 'Kilometergeld Fahrrad'),
				array('valid_from' => '2025-07-01', 'amount' => 0.25, 'cap_per_year' => 3000.0, 'label' => 'Kilometergeld Fahrrad'),
			),
			'km_passenger' => array(
				array('valid_from' => '1900-01-01', 'amount' => 0.05, 'cap_per_year' => null, 'label' => 'Kilometergeld Mitfahrer'),
				array('valid_from' => '2025-01-01', 'amount' => 0.15, 'cap_per_year' => null, 'label' => 'Kilometergeld Mitfahrer'),
			),
		);
	}

	/**
	 * Rate lookup with fallback code.
	 *
	 * @param	string	$code		Wanted code
	 * @param	string	$fallback	Fallback code
	 * @param	string	$date		Date
	 * @param	bool	$missing	Set to true when fallback was used
	 * @return	array{valid_from:string,amount:float,cap_per_year:?float}|null
	 */
	protected function rateWithFallback($code, $fallback, $date, &$missing)
	{
		$r = $this->getRate($code, $date);
		if ($r === null && $code !== $fallback) {
			$missing = true;
			$r = $this->getRate($fallback, $date);
		}
		if ($r === null) {
			$missing = true;
		}
		return $r;
	}

	/**
	 * Parse 'Y-m-d H:i[:s]' as naive UTC timestamp.
	 *
	 * @param	mixed	$str	Input
	 * @return	int|null
	 */
	protected function parse($str)
	{
		if (!is_string($str) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})[ T](\d{1,2}):(\d{2})(?::(\d{2}))?$/', trim($str), $m)) {
			return null;
		}
		return gmmktime((int) $m[4], (int) $m[5], isset($m[6]) ? (int) $m[6] : 0, (int) $m[2], (int) $m[3], (int) $m[1]);
	}
}
