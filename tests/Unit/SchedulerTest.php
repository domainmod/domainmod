<?php
/**
 * /tests/Unit/SchedulerTest.php
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
 * The Task Scheduler's time maths, with a fixed "now". The cases come from the clock change checks made for
 * DomainMOD 4.25.0. Every time here is UTC.
 */
class SchedulerTest extends TestCase
{
    private function scheduler()
    {
        return Instance::withoutConstructor(Scheduler::class);
    }

    private function nextRun($expression, $zone, $now)
    {
        return $this->scheduler()->getNextRun($expression, $zone, strtotime($now . ' UTC'));
    }

    public static function dailyRuns()
    {
        // expression, zone, now, next run
        return array(
            'Vancouver in winter' => array('0 7 * * *', 'America/Vancouver', '2026-01-15 00:00:00', '2026-01-15 15:00:00'),
            'Vancouver in summer' => array('0 7 * * *', 'America/Vancouver', '2026-07-15 00:00:00', '2026-07-15 14:00:00'),
            'a legacy name in summer' => array('0 7 * * *', 'Canada/Pacific', '2026-07-15 00:00:00', '2026-07-15 14:00:00'),
            'a legacy name in winter' => array('0 7 * * *', 'US/Pacific', '2026-01-15 00:00:00', '2026-01-15 15:00:00'),
            'London in winter' => array('0 7 * * *', 'Europe/London', '2026-01-15 00:00:00', '2026-01-15 07:00:00'),
            'London in summer' => array('0 7 * * *', 'Europe/London', '2026-07-15 00:00:00', '2026-07-15 06:00:00'),
            'Sydney in its summer' => array('0 7 * * *', 'Australia/Sydney', '2026-01-15 00:00:00', '2026-01-15 20:00:00'),
            'Sydney in its winter' => array('0 7 * * *', 'Australia/Sydney', '2026-07-15 00:00:00', '2026-07-15 21:00:00'),
            'a half-hour offset' => array('0 7 * * *', 'Asia/Kolkata', '2026-01-15 00:00:00', '2026-01-15 01:30:00'),
            'today\'s time has passed' => array('0 7 * * *', 'UTC', '2026-01-15 07:00:00', '2026-01-16 07:00:00'),
            'one second before' => array('0 7 * * *', 'UTC', '2026-01-15 06:59:59', '2026-01-15 07:00:00'),
            'the next day across a month end' => array('30 23 * * *', 'UTC', '2026-01-31 23:45:00', '2026-02-01 23:30:00'),

            // the clocks go forward: a time that doesn't exist runs once, just after the change
            'Los Angeles, in the missing hour' => array('30 2 * * *', 'America/Los_Angeles', '2026-03-08 08:00:00', '2026-03-08 10:30:00'),
            'Los Angeles, the day after' => array('30 2 * * *', 'America/Los_Angeles', '2026-03-08 10:30:00', '2026-03-09 09:30:00'),
            'Lord Howe, in the missing half hour' => array('0 2 * * *', 'Australia/Lord_Howe', '2026-10-03 14:00:00', '2026-10-03 15:30:00'),

            // the clocks go back: a time that happens twice runs once, at the first occurrence
            'Los Angeles, the first 01:00' => array('0 1 * * *', 'America/Los_Angeles', '2026-11-01 06:00:00', '2026-11-01 08:00:00'),
            'Los Angeles, not the second 01:00' => array('0 1 * * *', 'America/Los_Angeles', '2026-11-01 08:00:00', '2026-11-02 09:00:00'),
            'Lord Howe, the first 01:45' => array('45 1 * * *', 'Australia/Lord_Howe', '2026-04-04 13:00:00', '2026-04-04 14:45:00'),
            'Lord Howe, not the second 01:45' => array('45 1 * * *', 'Australia/Lord_Howe', '2026-04-04 14:45:00', '2026-04-05 15:15:00'),

            // across midnight: yesterday's 23:30 was moved forward into today by the change
            'Nuuk, 23:30 moved past midnight' => array('30 23 * * *', 'America/Nuuk', '2026-03-29 01:10:00', '2026-03-29 01:30:00'),
        );
    }

    #[DataProvider('dailyRuns')]
    public function testGetNextRunForADailyTask($expression, $zone, $now, $expected)
    {
        $this->assertSame($expected, $this->nextRun($expression, $zone, $now));
    }

    public static function otherRuns()
    {
        return array(
            'every five minutes' => array('*/5 * * * *', 'UTC', '2026-01-01 00:00:00', '2026-01-01 00:05:00'),
            'every five minutes, through a repeated hour' => array('*/5 * * * *', 'America/Los_Angeles', '2026-11-01 08:57:00', '2026-11-01 09:00:00'),
            'every five minutes, in the repeated hour' => array('*/5 * * * *', 'America/Los_Angeles', '2026-11-01 09:02:00', '2026-11-01 09:05:00'),
            'hourly' => array('0 * * * *', 'UTC', '2026-01-01 10:30:00', '2026-01-01 11:00:00'),
            'weekly' => array('0 7 * * 1', 'America/New_York', '2026-11-15 00:00:00', '2026-11-16 12:00:00'),
        );
    }

    #[DataProvider('otherRuns')]
    public function testGetNextRunForOtherExpressions($expression, $zone, $now, $expected)
    {
        $this->assertSame($expected, $this->nextRun($expression, $zone, $now));
    }

    public static function dailyTimes()
    {
        return array(
            'a daily time' => array('0 7 * * *', array(7, 0)),
            'single digits' => array('5 9 * * *', array(9, 5)),
            'padded' => array(' 30 23 * * * ', array(23, 30)),
            'leading zeros' => array('05 07 * * *', array(7, 5)),
            'the last minute' => array('59 23 * * *', array(23, 59)),
            'minute 60' => array('60 7 * * *', null),
            'hour 24' => array('0 24 * * *', null),
            'every five minutes' => array('*/5 * * * *', null),
            'weekly' => array('0 7 * * 1', null),
            'two hours' => array('0 7,19 * * *', null),
            'empty' => array('', null),
            'null' => array(null, null),
        );
    }

    #[DataProvider('dailyTimes')]
    public function testGetDailyTime($expression, $expected)
    {
        $this->assertSame($expected, $this->scheduler()->getDailyTime($expression));
    }

    public function testGetZoneAndIsValidZone()
    {
        $scheduler = $this->scheduler();

        $this->assertSame('America/Vancouver', $scheduler->getZone('America/Vancouver')->getName());
        // a PHP with the legacy names keeps the name, and one without them swaps it for the zone it points to
        $this->assertContains($scheduler->getZone('Canada/Pacific')->getName(), array('Canada/Pacific', 'America/Vancouver'));
        $this->assertTrue($scheduler->isValidZone('Canada/Pacific'));
        $this->assertFalse($scheduler->isValidZone('Not/AZone'));
        $this->assertFalse($scheduler->isValidZone(''));
    }

    public function testGetTimeDifference()
    {
        $scheduler = $this->scheduler();

        $this->assertSame(' (<em>30s</em>)', $scheduler->getTimeDifference('2026-01-01 00:00:00', '2026-01-01 00:00:30'));
        $this->assertSame(' (<em>0s</em>)', $scheduler->getTimeDifference('2026-01-01 00:00:00', '2026-01-01 00:00:00'));
        $this->assertSame(' (<em>1m 5s</em>)', $scheduler->getTimeDifference('2026-01-01 00:00:00', '2026-01-01 00:01:05'));
        $this->assertSame(' (<em>120m 0s</em>)', $scheduler->getTimeDifference('2026-01-01 00:00:00', '2026-01-01 02:00:00'));
    }

    public function testGetDateOutput()
    {
        $this->assertSame('n/a', $this->scheduler()->getDateOutput('1970-01-01 00:00:00'));
        $this->assertSame('2026-01-01 07:00:00', $this->scheduler()->getDateOutput('2026-01-01 07:00:00'));
    }

} //@formatter:on
