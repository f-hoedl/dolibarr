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
 * \file        htdocs/custom/anxhr/test/phpunit/TimeEngineTest.php
 * \ingroup     anxhr
 * \brief       Standalone unit tests for TimeEngine (no Dolibarr bootstrap).
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__.'/../../class/timeengine.class.php';

/**
 * Class TimeEngineTest
 */
class TimeEngineTest extends TestCase
{
	/**
	 * Fixed full-time model 40h (480 min Mo-Fr).
	 *
	 * @param string $type Model type
	 * @param int    $daily Daily minutes Mo-Fr
	 * @param string $breakRule Break rule
	 * @return array<string,mixed>
	 */
	private function model($type = 'fixed', $daily = 480, $breakRule = 'auto')
	{
		return array('model_type' => $type, 'daily_minutes' => array(1 => $daily, 2 => $daily, 3 => $daily, 4 => $daily, 5 => $daily, 6 => 0, 7 => 0), 'break_rule' => $breakRule, 'normal_daily_max_min' => 600);
	}

	/**
	 * KV parameters (SWOE like).
	 *
	 * @param int $weekly Weekly hours
	 * @param int $free Dec 24/31 free
	 * @return array<string,mixed>
	 */
	private function kv($weekly = 40, $free = 0)
	{
		return array('ot_pct' => 50, 'ot_night_pct' => 100, 'ot_sunday_pct' => 100, 'parttime_extra_pct' => 25, 'night_start' => '22:00', 'night_end' => '06:00', 'dec24_free' => $free, 'dec31_free' => $free, 'weekly_hours' => $weekly);
	}

	/**
	 * Build entries from pairs.
	 *
	 * @param string $day Day
	 * @param array<int,array{0:string,1:string}> $list type,time
	 * @return array<int,array<string,string>>
	 */
	private function entries($day, array $list)
	{
		$out = array();
		foreach ($list as $e) {
			$time = (strlen($e[1]) > 5) ? $e[1] : $day.' '.$e[1].':00';
			$out[] = array('type' => $e[0], 'time' => $time);
		}
		return $out;
	}

	/**
	 * Run engine.
	 *
	 * @param array<string,mixed> $over Overrides
	 * @return array<string,mixed>
	 */
	private function compute(array $over)
	{
		$in = array_merge(array(
			'day' => '2026-03-02',
			'entries' => array(),
			'model' => $this->model(),
			'kv' => $this->kv(),
			'contract' => array('weekly_hours' => 40),
			'is_public_holiday' => false,
			'absence_code' => null,
			'previous_out' => null,
		), $over);
		$engine = new TimeEngine();
		return $engine->computeDay($in);
	}

	/**
	 * Return violation codes.
	 *
	 * @param array<string,mixed> $r Result
	 * @return string[]
	 */
	private function codes(array $r)
	{
		return array_map(function ($v) {
			return $v['code'];
		}, $r['violations']);
	}

	/** @return array<int,array{0:string,1:string}> */
	private function std8h()
	{
		return array(array('in', '08:00'), array('break_start', '12:00'), array('break_end', '12:30'), array('out', '16:30'));
	}

	public function testNormalDayWithBreak(): void
	{
		$r = $this->compute(array('entries' => $this->entries('2026-03-02', $this->std8h())));
		$this->assertSame(480, $r['worked_min']);
		$this->assertSame(30, $r['break_min']);
		$this->assertSame(480, $r['target_min']);
		$this->assertSame(0, $r['diff_min']);
		$this->assertSame(0, $r['overtime50_min']);
		$this->assertSame(array(), $r['violations']);
		$this->assertSame('+00:00', $r['summary']['diff']);
	}

	public function testNineHoursWithoutBreakAutoDeducted(): void
	{
		$r = $this->compute(array('entries' => $this->entries('2026-03-02', array(array('in', '08:00'), array('out', '17:00')))));
		$this->assertSame(510, $r['worked_min']);
		$this->assertSame(30, $r['break_min']);
		$this->assertSame(30, $r['break_auto_added_min']);
		$this->assertContains('BREAK_AUTO_ADDED', $this->codes($r));
		$this->assertSame('info', $r['violations'][0]['level']);
		$this->assertNotContains('BREAK_MISSING', $this->codes($r));
	}

	public function testBreakCheckModeReportsMissing(): void
	{
		$r = $this->compute(array('model' => $this->model('fixed', 480, 'check'), 'entries' => $this->entries('2026-03-02', array(array('in', '08:00'), array('out', '17:00')))));
		$this->assertSame(540, $r['worked_min']);
		$this->assertSame(0, $r['break_auto_added_min']);
		$this->assertContains('BREAK_MISSING', $this->codes($r));
	}

	public function testTenAndHalfHoursOvertimeAndMax10h(): void
	{
		$r = $this->compute(array('entries' => $this->entries('2026-03-02', array(array('in', '07:00'), array('break_start', '12:00'), array('break_end', '12:30'), array('out', '18:00')))));
		$this->assertSame(630, $r['worked_min']);
		$this->assertSame(150, $r['overtime50_min']);
		$this->assertContains('MAX_DAILY_10H', $this->codes($r));
		$this->assertNotContains('MAX_DAILY_12H', $this->codes($r));
	}

	public function testTwelveAndHalfHoursMax12h(): void
	{
		$r = $this->compute(array('entries' => $this->entries('2026-03-02', array(array('in', '06:00'), array('break_start', '12:00'), array('break_end', '12:30'), array('out', '19:00')))));
		$this->assertSame(750, $r['worked_min']);
		$this->assertContains('MAX_DAILY_12H', $this->codes($r));
		$this->assertContains('MAX_DAILY_10H', $this->codes($r));
	}

	public function testNightWorkAcrossMidnight(): void
	{
		$r = $this->compute(array('day' => '2026-03-03', 'entries' => $this->entries('2026-03-03', array(array('in', '2026-03-03 20:00:00'), array('out', '2026-03-04 02:00:00')))));
		// 20:00-02:00 on a 480 min target day: 360 min worked, all within the target, so no overtime.
		// The 240 night minutes are reported (KV night allowance) but carry no overtime surcharge
		// (paragraph 10 AZG: the surcharge applies to overtime only).
		$this->assertSame(360, $r['worked_min']);
		$this->assertSame(240, $r['night_min']);
		$this->assertSame(0, $r['overtime100_min']);
		$this->assertSame(0, $r['overtime50_min']);
		$this->assertSame(-120, $r['diff_min']);
		$this->assertSame(0, $r['break_auto_added_min']);
	}

	public function testPremiumWithinTargetGivesNoOt100(): void
	{
		// 444 min target, 20:00-03:54 with 30 min break = 444 min worked, 324 of them night minutes.
		$r = $this->compute(array('day' => '2026-03-03', 'model' => $this->model('fixed', 444), 'kv' => $this->kv(37), 'contract' => array('weekly_hours' => 37), 'entries' => $this->entries('2026-03-03', array(array('in', '2026-03-03 20:00:00'), array('break_start', '2026-03-03 23:00:00'), array('break_end', '2026-03-03 23:30:00'), array('out', '2026-03-04 03:54:00')))));
		$this->assertSame(444, $r['worked_min']);
		$this->assertSame(0, $r['diff_min']);
		$this->assertSame(324, $r['night_min']);
		$this->assertSame(0, $r['overtime100_min']);
		$this->assertSame(0, $r['overtime50_min']);
	}

	public function testFixedNightOvertimeIsOt100(): void
	{
		// 444 min target, 14:00-23:00 with 30 min break = 510 min worked, 66 min overtime, 60 night minutes.
		$r = $this->compute(array('model' => $this->model('fixed', 444), 'kv' => $this->kv(37), 'contract' => array('weekly_hours' => 37), 'entries' => $this->entries('2026-03-02', array(array('in', '14:00'), array('break_start', '18:00'), array('break_end', '18:30'), array('out', '23:00')))));
		$this->assertSame(510, $r['worked_min']);
		$this->assertSame(60, $r['night_min']);
		$this->assertSame(60, $r['overtime100_min']);
		$this->assertSame(6, $r['overtime50_min']);
	}

	public function testFlexNineHoursWithNightMinutes(): void
	{
		// Flex 444 target, 13:00-22:30 with 30 min break = 540 min worked, 30 night minutes:
		// 96 min flex credit within the 10h normal maximum, no overtime, no surcharge.
		$r = $this->compute(array('model' => $this->model('flex', 444), 'kv' => $this->kv(37), 'contract' => array('weekly_hours' => 37), 'entries' => $this->entries('2026-03-02', array(array('in', '13:00'), array('break_start', '17:00'), array('break_end', '17:30'), array('out', '22:30')))));
		$this->assertSame(540, $r['worked_min']);
		$this->assertSame(30, $r['night_min']);
		$this->assertSame(96, $r['diff_min']);
		$this->assertSame(0, $r['overtime100_min']);
		$this->assertSame(0, $r['overtime50_min']);
	}

	public function testSundayFourHoursOnZeroTarget(): void
	{
		$r = $this->compute(array('day' => '2026-03-08', 'entries' => $this->entries('2026-03-08', array(array('in', '08:00'), array('out', '12:00')))));
		$this->assertSame(0, $r['target_min']);
		$this->assertSame(240, $r['sunday_holiday_min']);
		$this->assertSame(240, $r['overtime100_min']);
		$this->assertSame(0, $r['overtime50_min']);
		// Same on a flexitime model: Sunday is outside any flexitime frame (paragraph 3 ARG).
		$r = $this->compute(array('day' => '2026-03-08', 'model' => $this->model('flex', 444), 'entries' => $this->entries('2026-03-08', array(array('in', '08:00'), array('out', '12:00')))));
		$this->assertSame(240, $r['overtime100_min']);
	}

	public function testPartTimeFlexGetsExtraParttime(): void
	{
		$r = $this->compute(array('model' => $this->model('flex', 240), 'kv' => $this->kv(37), 'contract' => array('weekly_hours' => 20), 'entries' => $this->entries('2026-03-02', array(array('in', '08:00'), array('out', '14:00')))));
		$this->assertSame(1, $r['part_time']);
		$this->assertSame(120, $r['extra_parttime_min']);
		$this->assertSame(0, $r['overtime50_min']);
		$this->assertSame(0, $r['overtime100_min']);
	}

	public function testSundayWork(): void
	{
		$r = $this->compute(array('day' => '2026-03-08', 'entries' => $this->entries('2026-03-08', $this->std8h())));
		$this->assertSame(0, $r['target_min']);
		$this->assertSame(480, $r['overtime100_min']);
		$this->assertSame(0, $r['overtime50_min']);
		$this->assertNotContains('HOLIDAY_WORK', $this->codes($r));
	}

	public function testPublicHolidayWork(): void
	{
		$r = $this->compute(array('day' => '2026-05-01', 'is_public_holiday' => true, 'entries' => $this->entries('2026-05-01', $this->std8h())));
		$this->assertSame(0, $r['target_min']);
		$this->assertSame(1, $r['holiday_flag']);
		$this->assertSame(480, $r['overtime100_min']);
		$this->assertContains('HOLIDAY_WORK', $this->codes($r));
	}

	public function testPublicHolidayWithoutWork(): void
	{
		$r = $this->compute(array('day' => '2026-05-01', 'is_public_holiday' => true));
		$this->assertSame(0, $r['target_min']);
		$this->assertSame(0, $r['diff_min']);
		$this->assertSame(array(), $r['violations']);
	}

	public function testDec24FreeIsCreditWithoutOvertime(): void
	{
		$r = $this->compute(array('day' => '2026-12-24', 'kv' => $this->kv(37, 1), 'entries' => $this->entries('2026-12-24', array(array('in', '08:00'), array('out', '12:00')))));
		$this->assertSame(0, $r['target_min']);
		$this->assertSame(240, $r['diff_min']);
		$this->assertSame(0, $r['overtime50_min']);
		$this->assertSame(0, $r['overtime100_min']);
		$this->assertSame(1, $r['kv_free_day']);
	}

	public function testDec24NotFreeWithoutKvFlag(): void
	{
		$r = $this->compute(array('day' => '2026-12-24', 'kv' => $this->kv(40, 0)));
		$this->assertSame(480, $r['target_min']);
	}

	public function testPartTimeExtraWork(): void
	{
		$r = $this->compute(array('model' => $this->model('fixed', 240), 'kv' => $this->kv(37), 'contract' => array('weekly_hours' => 20), 'entries' => $this->entries('2026-03-02', array(array('in', '08:00'), array('out', '14:00')))));
		$this->assertSame(360, $r['worked_min']);
		$this->assertSame(120, $r['extra_parttime_min']);
		$this->assertSame(0, $r['overtime50_min']);
		$this->assertSame(1, $r['part_time']);
	}

	public function testPartTimeAboveKvDailyNormalIsOvertime(): void
	{
		$r = $this->compute(array('model' => $this->model('fixed', 240), 'kv' => $this->kv(37), 'contract' => array('weekly_hours' => 20), 'entries' => $this->entries('2026-03-02', array(array('in', '08:00'), array('break_start', '12:00'), array('break_end', '12:30'), array('out', '17:30')))));
		$this->assertSame(540, $r['worked_min']);
		$this->assertSame(204, $r['extra_parttime_min']);
		$this->assertSame(96, $r['overtime50_min']);
	}

	public function testFlexCreditNoOvertime(): void
	{
		$r = $this->compute(array('model' => $this->model('flex', 444), 'kv' => $this->kv(37), 'contract' => array('weekly_hours' => 37), 'entries' => $this->entries('2026-03-02', array(array('in', '08:00'), array('break_start', '12:00'), array('break_end', '12:30'), array('out', '17:30')))));
		$this->assertSame(96, $r['diff_min']);
		$this->assertSame(0, $r['overtime50_min']);
	}

	public function testFixedSameDayIsOvertime(): void
	{
		$r = $this->compute(array('model' => $this->model('fixed', 444), 'kv' => $this->kv(37), 'contract' => array('weekly_hours' => 37), 'entries' => $this->entries('2026-03-02', array(array('in', '08:00'), array('break_start', '12:00'), array('break_end', '12:30'), array('out', '17:30')))));
		$this->assertSame(96, $r['diff_min']);
		$this->assertSame(96, $r['overtime50_min']);
	}

	public function testFlexAboveNormalMaxIsOvertime(): void
	{
		$r = $this->compute(array('model' => $this->model('flex', 444), 'kv' => $this->kv(37), 'contract' => array('weekly_hours' => 37), 'entries' => $this->entries('2026-03-02', array(array('in', '07:00'), array('break_start', '12:00'), array('break_end', '12:30'), array('out', '18:00')))));
		$this->assertSame(630, $r['worked_min']);
		$this->assertSame(30, $r['overtime50_min']);
	}

	public function testRestViolation(): void
	{
		$r = $this->compute(array('previous_out' => '2026-03-01 23:00:00', 'entries' => $this->entries('2026-03-02', $this->std8h())));
		$this->assertContains('REST_11H', $this->codes($r));
		$r = $this->compute(array('previous_out' => '2026-03-01 23:00:00', 'entries' => $this->entries('2026-03-02', array(array('in', '10:00'), array('out', '14:00')))));
		$this->assertNotContains('REST_11H', $this->codes($r));
	}

	public function testRestViolationValue(): void
	{
		$r = $this->compute(array('previous_out' => '2026-03-01 23:00:00', 'entries' => $this->entries('2026-03-02', array(array('in', '07:00'), array('out', '11:00')))));
		$found = null;
		foreach ($r['violations'] as $v) {
			if ($v['code'] === 'REST_11H') {
				$found = $v;
			}
		}
		$this->assertNotNull($found);
		$this->assertSame(480, $found['value']);
	}

	public function testOpenEntry(): void
	{
		$r = $this->compute(array('entries' => $this->entries('2026-03-02', array(array('in', '08:00'), array('break_start', '12:00'), array('break_end', '12:30')))));
		$this->assertTrue($r['open_entry']);
		$this->assertContains('OPEN_ENTRY', $this->codes($r));
		$this->assertSame(240, $r['worked_min']);
	}

	public function testInvalidOrder(): void
	{
		$r = $this->compute(array('entries' => $this->entries('2026-03-02', array(array('out', '08:00'), array('in', '09:00'), array('break_end', '10:00'), array('out', '13:00')))));
		$this->assertContains('ORDER_INVALID', $this->codes($r));
		$this->assertSame(240, $r['worked_min']);
	}

	public function testUnsortedInputIsSorted(): void
	{
		$r = $this->compute(array('entries' => $this->entries('2026-03-02', array(array('out', '16:30'), array('break_end', '12:30'), array('in', '08:00'), array('break_start', '12:00')))));
		$this->assertSame(480, $r['worked_min']);
		$this->assertNotContains('ORDER_INVALID', $this->codes($r));
	}

	public function testSameMinuteEntriesKeepInputOrder(): void
	{
		$entries = array(
			array('type' => 'in', 'time' => '2026-03-02 08:00:01'),
			array('type' => 'break_start', 'time' => '2026-03-02 08:00:10'),
			array('type' => 'break_end', 'time' => '2026-03-02 08:00:20'),
			array('type' => 'out', 'time' => '2026-03-02 08:00:30'),
		);
		$r = $this->compute(array('entries' => $entries));
		$this->assertNotContains('ORDER_INVALID', $this->codes($r));
		$this->assertSame(0, $r['worked_min']);
	}

	public function testVacationAbsence(): void
	{
		$r = $this->compute(array('absence_code' => 'vacation'));
		$this->assertSame(0, $r['target_min']);
		$this->assertSame(0, $r['diff_min']);
		$this->assertSame('vacation', $r['absence_code']);
	}

	public function testZaAbsenceConsumesBalance(): void
	{
		$r = $this->compute(array('absence_code' => 'za'));
		$this->assertSame(480, $r['target_min']);
		$this->assertSame(-480, $r['diff_min']);
	}

	public function testHalfDayVacation(): void
	{
		$r = $this->compute(array('absence_code' => 'vacation', 'absence_fraction' => 0.5));
		$this->assertSame(240, $r['target_min']);
	}

	public function testWeeklyMax60h(): void
	{
		$engine = new TimeEngine();
		$days = array();
		for ($i = 0; $i < 5; $i++) {
			$days[] = array('worked_min' => 732, 'target_min' => 480, 'diff_min' => 252);
		}
		$w = $engine->computeWeek($days);
		$this->assertSame(3660, $w['worked_min']);
		$this->assertSame('MAX_WEEKLY_60H', $w['violations'][0]['code']);

		$w = $engine->computeWeek(array(array('worked_min' => 2400)));
		$this->assertSame(array(), $w['violations']);
	}

	public function testFormatMinutes(): void
	{
		$this->assertSame('+07:24', TimeEngine::formatMinutes(444, true));
		$this->assertSame('-01:05', TimeEngine::formatMinutes(-65));
	}
}
