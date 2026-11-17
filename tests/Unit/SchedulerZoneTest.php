<?php
/**
 * /tests/Unit/SchedulerZoneTest.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2026 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: https://greg.ca
 *
 * DomainMOD is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later
 * version.
 *
 * DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with DomainMOD. If not, see
 * http://www.gnu.org/licenses/.
 *
 */
//@formatter:off
namespace DomainMOD\Tests\Unit;

use DomainMOD\Scheduler;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * The Task Scheduler's run time menu shows a daily task's time in the user's time zone, the zone update.php saves in
 * (Scheduler::getDailyTimeIn()), so saving the time shown keeps the task's time.
 */
class SchedulerZoneTest extends TestCase
{
    // 2026-11-17 00:00 UTC (New York on standard time) and 2026-07-01 00:00 UTC (New York on daylight time)
    const NOVEMBER = 1794873600;
    const JULY = 1782864000;

    public static function times(): array
    {
        return array(
            'same zone' => array('0 7 * * *', 'America/New_York', 'America/New_York', self::NOVEMBER, array(7, 0)),
            'older name for the same zone' => array('0 7 * * *', 'US/Eastern', 'America/New_York', self::NOVEMBER, array(7, 0)),
            'to UTC on standard time' => array('0 7 * * *', 'America/New_York', 'UTC', self::NOVEMBER, array(12, 0)),
            'to UTC on daylight time' => array('0 7 * * *', 'America/New_York', 'UTC', self::JULY, array(11, 0)),
            'from UTC' => array('0 23 * * *', 'UTC', 'Europe/Berlin', self::NOVEMBER, array(0, 0)),
            'half-hour zone' => array('0 7 * * *', 'America/New_York', 'Asia/Kolkata', self::NOVEMBER, array(17, 30)),
            'minutes kept' => array('30 6 * * *', 'Asia/Kolkata', 'UTC', self::NOVEMBER, array(1, 0)),
            'not a daily task' => array('*/5 * * * *', 'America/New_York', 'UTC', self::NOVEMBER, null),
        );
    }

    #[DataProvider('times')]
    public function testDailyTimeInTheUsersZone($expression, $task_zone, $user_zone, $now, $expected)
    {
        $schedule = Instance::withoutConstructor(Scheduler::class);

        $this->assertSame($expected, $schedule->getDailyTimeIn($expression, $task_zone, $user_zone, $now));
    }

    // Saving the hour the menu shows, in the user's zone as update.php does, gives the same next run as before.
    public function testSavingTheShownHourKeepsTheNextRun()
    {
        $schedule = Instance::withoutConstructor(Scheduler::class);
        $before = $schedule->getNextRun('0 7 * * *', 'America/New_York', self::NOVEMBER);

        list($hour, $minute) = $schedule->getDailyTimeIn('0 7 * * *', 'America/New_York', 'Europe/London', self::NOVEMBER);
        $after = $schedule->getNextRun($minute . ' ' . $hour . ' * * *', 'Europe/London', self::NOVEMBER);

        $this->assertSame(0, $minute);
        $this->assertSame($before, $after);
    }

    // When the clocks go back, the hour shown can be the second 01:00, which a saved 01:00 would skip (it runs at the
    // first), so the hour shown counts as unchanged and the task is kept as it is.
    public function testTheShownHourDuringAnOverlapIsKept()
    {
        $schedule = Instance::withoutConstructor(Scheduler::class);
        $now = 1793511000; // 2026-11-01 05:30 UTC, between New York's two 01:00s

        $this->assertSame(array(1, 0), $schedule->getDailyTimeIn('0 6 * * *', 'UTC', 'America/New_York', $now));
        $this->assertSame('2026-11-02 06:00:00', $schedule->getNextRun('0 1 * * *', 'America/New_York', $now));
        $this->assertTrue($schedule->isShownHour('0 6 * * *', 'UTC', 'America/New_York', '01', $now));
        $this->assertFalse($schedule->isShownHour('0 6 * * *', 'UTC', 'America/New_York', '02', $now));
    }

    // A time off the hour is never one of the menu's hours.
    public function testATimeOffTheHourIsNeverAShownHour()
    {
        $schedule = Instance::withoutConstructor(Scheduler::class);

        $this->assertFalse($schedule->isShownHour('0 7 * * *', 'America/New_York', 'Asia/Kolkata', '17', self::NOVEMBER));
        $this->assertTrue($schedule->isShownHour('0 7 * * *', 'America/New_York', 'America/New_York', '07', self::NOVEMBER));
    }

    // A time that isn't on the hour in the user's zone is its own chosen option, with no value, before the hours.
    public function testHourMenuShowsATimeOffTheHour()
    {
        $schedule = Instance::withoutConstructor(Scheduler::class);
        $menu = $schedule->hourSelect('', '20:30');

        $this->assertStringStartsWith('<option value="" dir="auto" selected>20:30</option>', trim($menu));
        $this->assertSame(1, substr_count($menu, 'selected'));
        $this->assertSame(25, substr_count($menu, '<option'));
    }

    public function testHourMenuChoosesTheHour()
    {
        $schedule = Instance::withoutConstructor(Scheduler::class);
        $menu = $schedule->hourSelect('07');

        $this->assertStringContainsString('<option value="07" dir="auto" selected>07:00</option>', $menu);
        $this->assertSame(1, substr_count($menu, 'selected'));
        $this->assertSame(24, substr_count($menu, '<option'));
    }

} //@formatter:on
