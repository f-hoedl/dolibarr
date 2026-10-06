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
 * \file        htdocs/custom/anxhr/test/phpunit/TravelDietTest.php
 * \ingroup     anxhr
 * \brief       Standalone unit tests for TravelDiet (no Dolibarr bootstrap).
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../class/traveldiet.class.php';

/**
 * Class TravelDietTest
 */
class TravelDietTest extends TestCase
{
	/**
	 * @return TravelDiet
	 */
	private function diet()
	{
		return new TravelDiet(TravelDiet::defaultAustrianRates());
	}

	public function testRateLookupByDate(): void
	{
		$d = $this->diet();
		$this->assertSame(26.40, $d->getRate('taggeld_in', '2024-12-31')['amount']);
		$this->assertSame(30.00, $d->getRate('taggeld_in', '2025-01-01')['amount']);
		$this->assertSame(0.25, $d->getRate('km_bike', '2025-08-01')['amount']);
		$this->assertNull($d->getRate('unknown', '2025-01-01'));
	}

	public function testOneDayTripSevenTwelfths(): void
	{
		$r = $this->diet()->computeTrip(array('start' => '2026-03-02 08:00', 'end' => '2026-03-02 14:30', 'country' => 'AT'));
		$this->assertCount(1, $r['days']);
		$this->assertSame(7, $r['days'][0]['twelfths']);
		$this->assertEqualsWithDelta(17.50, $r['totals']['total'], 0.001);
		$this->assertSame(0, $r['nights']);
	}

	public function testShortTripNoAllowance(): void
	{
		$r = $this->diet()->computeTrip(array('start' => '2026-03-02 08:00', 'end' => '2026-03-02 10:00'));
		$this->assertSame(0, $r['days'][0]['twelfths']);
		$this->assertEqualsWithDelta(0.0, $r['totals']['total'], 0.001);
	}

	public function testExactlyThreeHoursNoAllowance(): void
	{
		$r = $this->diet()->computeTrip(array('start' => '2026-03-02 08:00', 'end' => '2026-03-02 11:00'));
		$this->assertSame(0, $r['days'][0]['twelfths']);
	}

	public function testTwoDayTripWithNight(): void
	{
		// Default 24 hour method: Mon 08:00 - Tue 08:00 = 12/12, Tue 08:00 - 18:00 = 10/12.
		$r = $this->diet()->computeTrip(array('start' => '2026-03-02 08:00', 'end' => '2026-03-03 18:00', 'country' => 'AT'));
		$this->assertCount(2, $r['days']);
		$this->assertSame(12, $r['days'][0]['twelfths']);
		$this->assertSame(10, $r['days'][1]['twelfths']);
		$this->assertSame(1, $r['nights']);
		$this->assertEqualsWithDelta(17.00, $r['totals']['nacht_amount'], 0.001);
		$this->assertEqualsWithDelta(30.00 + 25.00 + 17.00, $r['totals']['total'], 0.001);
	}

	public function testTwoDayTripWithNightCalendarMethod(): void
	{
		$r = $this->diet()->computeTrip(array('start' => '2026-03-02 08:00', 'end' => '2026-03-03 18:00', 'country' => 'AT', 'method' => 'calendar'));
		$this->assertCount(2, $r['days']);
		$this->assertSame(12, $r['days'][0]['twelfths']);
		$this->assertSame(12, $r['days'][1]['twelfths']);
		$this->assertEqualsWithDelta(77.00, $r['totals']['total'], 0.001);
	}

	public function testTwoDayTripPartialSecondDay(): void
	{
		// 24 hour method: 20.5 hours are one period -> 12/12, plus one night (calendar day crossed).
		$r = $this->diet()->computeTrip(array('start' => '2026-03-02 14:00', 'end' => '2026-03-03 10:30'));
		$this->assertCount(1, $r['days']);
		$this->assertSame(12, $r['days'][0]['twelfths']);
		$this->assertSame(1, $r['nights']);
		$this->assertEqualsWithDelta(30.00 + 17.00, $r['totals']['total'], 0.001);
		// Calendar method: 10/12 + 11/12.
		$r = $this->diet()->computeTrip(array('start' => '2026-03-02 14:00', 'end' => '2026-03-03 10:30', 'method' => 'calendar'));
		$this->assertSame(10, $r['days'][0]['twelfths']);
		$this->assertSame(11, $r['days'][1]['twelfths']);
		$this->assertEqualsWithDelta(25.00 + 27.50 + 17.00, $r['totals']['total'], 0.001);
	}

	public function testNightTripThresholdAppliesToWholeTrip(): void
	{
		// 22:00 - 04:00: 6 hours trip, more than 3 hours as a whole -> 6 twelfths with both methods.
		$r = $this->diet()->computeTrip(array('start' => '2026-03-02 22:00', 'end' => '2026-03-03 04:00', 'nights' => 0));
		$this->assertSame(6, $r['totals']['twelfths']);
		$this->assertEqualsWithDelta(15.00, $r['totals']['total'], 0.001);
		$r = $this->diet()->computeTrip(array('start' => '2026-03-02 22:00', 'end' => '2026-03-03 04:00', 'nights' => 0, 'method' => 'calendar'));
		$this->assertSame(2, $r['days'][0]['twelfths']);
		$this->assertSame(4, $r['days'][1]['twelfths']);
		$this->assertSame(6, $r['totals']['twelfths']);
	}

	public function testKmMotorbikeSharesCarCap(): void
	{
		// The 30000 km cap is combined car + motorbike: callers pass the combined km of the year.
		$r = $this->diet()->computeKm(array('date' => '2026-03-02', 'vehicle' => 'motorbike', 'km' => 100, 'km_year_so_far' => 29960));
		$this->assertEqualsWithDelta(40.0, $r['km_capped'], 0.001);
		$this->assertEqualsWithDelta(10.00, $r['amount_capped'], 0.001);
		$this->assertEqualsWithDelta(15.00, $r['taxable_excess'], 0.001);
	}

	public function testTripDecember2024UsesOldRate(): void
	{
		$r = $this->diet()->computeTrip(array('start' => '2024-12-10 06:00', 'end' => '2024-12-10 20:00'));
		$this->assertSame(26.40, $r['days'][0]['taggeld_rate']);
		$this->assertEqualsWithDelta(26.40, $r['totals']['taggeld_amount'], 0.001);
	}

	public function testLunchProvidedDeduction(): void
	{
		$r = $this->diet()->computeTrip(array('start' => '2026-03-02 07:00', 'end' => '2026-03-02 19:00', 'meals_provided' => array('2026-03-02' => array('lunch' => 1, 'dinner' => 0))));
		$this->assertEqualsWithDelta(15.00, $r['days'][0]['meal_deduction'], 0.001);
		$this->assertEqualsWithDelta(15.00, $r['totals']['total'], 0.001);
	}

	public function testMealDeductionCappedAtTaggeld(): void
	{
		$r = $this->diet()->computeTrip(array('start' => '2026-03-02 08:00', 'end' => '2026-03-02 13:00', 'meals_provided' => array('2026-03-02' => array('lunch' => 1, 'dinner' => 1))));
		$this->assertEqualsWithDelta(12.50, $r['days'][0]['meal_deduction'], 0.001);
		$this->assertEqualsWithDelta(0.0, $r['totals']['total'], 0.001);
	}

	public function testForeignCountryFallbackFlag(): void
	{
		$r = $this->diet()->computeTrip(array('start' => '2026-03-02 08:00', 'end' => '2026-03-02 20:00', 'country' => 'DE'));
		$this->assertTrue($r['rate_missing']);
		$this->assertEqualsWithDelta(30.00, $r['totals']['total'], 0.001);

		$rates = TravelDiet::defaultAustrianRates();
		$rates['taggeld_de'] = array(array('valid_from' => '2020-01-01', 'amount' => 35.30));
		$d = new TravelDiet($rates);
		$r = $d->computeTrip(array('start' => '2026-03-02 08:00', 'end' => '2026-03-02 20:00', 'country' => 'DE'));
		$this->assertFalse($r['rate_missing']);
		$this->assertEqualsWithDelta(35.30, $r['totals']['total'], 0.001);
	}

	public function testInvalidTrip(): void
	{
		$r = $this->diet()->computeTrip(array('start' => '2026-03-02 10:00', 'end' => '2026-03-02 08:00'));
		$this->assertSame('ErrorInvalidTripDates', $r['error']);
	}

	public function testKmCar(): void
	{
		$r = $this->diet()->computeKm(array('date' => '2026-03-02', 'vehicle' => 'car', 'km' => 100, 'km_year_so_far' => 0));
		$this->assertEqualsWithDelta(50.00, $r['amount_capped'], 0.001);
		$this->assertEqualsWithDelta(0.0, $r['taxable_excess'], 0.001);
	}

	public function testKmBikeAfterJuly2025(): void
	{
		$r = $this->diet()->computeKm(array('date' => '2025-08-01', 'vehicle' => 'bike', 'km' => 200));
		$this->assertEqualsWithDelta(50.00, $r['amount_capped'], 0.001);
		$this->assertSame(0.25, $r['rate']);
	}

	public function testKmCarCap(): void
	{
		$r = $this->diet()->computeKm(array('date' => '2026-03-02', 'vehicle' => 'car', 'km' => 100, 'km_year_so_far' => 29950));
		$this->assertEqualsWithDelta(50.0, $r['km_capped'], 0.001);
		$this->assertEqualsWithDelta(25.00, $r['amount_capped'], 0.001);
		$this->assertEqualsWithDelta(25.00, $r['taxable_excess'], 0.001);
		$this->assertEqualsWithDelta(50.00, $r['amount'], 0.001);
	}

	public function testKmPassenger(): void
	{
		$r = $this->diet()->computeKm(array('date' => '2026-03-02', 'vehicle' => 'car', 'km' => 100, 'passengers' => 2));
		$this->assertEqualsWithDelta(30.00, $r['passenger_amount'], 0.001);
		$this->assertEqualsWithDelta(80.00, $r['amount_capped'], 0.001);
	}

	public function testKmMissingRate(): void
	{
		$r = $this->diet()->computeKm(array('date' => '2026-03-02', 'vehicle' => 'boat', 'km' => 10));
		$this->assertTrue($r['rate_missing']);
	}
}
