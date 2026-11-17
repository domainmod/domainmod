<?php
/**
 * /tests/Unit/TimeUnsetTest.php
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

use DomainMOD\Time;
use PHPUnit\Framework\TestCase;

/*
 * Dates and times that were never set (the 1970-01-01 column default, or a zero date older installs can hold) show
 * blank instead of a converted time near 1970, while a real time near 1970 still converts. The check is made on the
 * stored UTC value, so the user's time zone can't turn the default into a different-looking time. New York stands in
 * for a zone west of UTC, since its zone data has the same offsets everywhere the tests run.
 */
class TimeUnsetTest extends TestCase
{
    private $saved_zone;

    protected function setUp(): void
    {
        $this->saved_zone = $_SESSION['s_default_timezone'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->saved_zone === null) {
            unset($_SESSION['s_default_timezone']);
        } else {
            $_SESSION['s_default_timezone'] = $this->saved_zone;
        }
    }

    public function testEachUnsetValue()
    {
        foreach (array(null, '', '   ', '1970-01-01 00:00:00', '1970-01-01', '0000-00-00 00:00:00', '0000-00-00',
                     ' 1970-01-01 ') as $value) {
            $this->assertTrue(Time::isUnset($value), var_export($value, true));
        }
    }

    public function testRealTimesNearTheEpochAreSet()
    {
        foreach (array('1970-01-01 00:00:01', '1970-01-01 08:00:00', '1969-12-31 16:00:00', '2026-11-17',
                     '2026-11-17 12:00:00') as $value) {
            $this->assertFalse(Time::isUnset($value), $value);
        }
    }

    public function testAnUnsetTimeConvertsToBlankOrTheGivenValue()
    {
        $time = new Time();
        $_SESSION['s_default_timezone'] = 'America/New_York';

        $this->assertSame('', $time->toUserTimezone('1970-01-01 00:00:00'));
        $this->assertSame('', $time->toUserTimezone('0000-00-00', 'Y-m-d'));
        $this->assertSame('', $time->toUserTimezone(null));
        $this->assertSame('-', $time->toUserTimezone('1970-01-01', 'Y-m-d', '-'));
        $this->assertSame('n/a', $time->toUserTimezone('0000-00-00 00:00:00', 'M jS @ g:i:sa', 'n/a'));
    }

    public function testNearEpochTimesConvertWestOfUtc()
    {
        $time = new Time();
        $_SESSION['s_default_timezone'] = 'America/New_York';

        // One second after the default is a real time, the evening before in New York (EST, five hours behind)
        $this->assertSame('1969-12-31 19:00:01', $time->toUserTimezone('1970-01-01 00:00:01'));
        $this->assertSame('1969-12-31', $time->toUserTimezone('1970-01-01 00:00:01', 'Y-m-d'));
        $this->assertSame('Dec 31st @ 7:00:01pm', $time->toUserTimezone('1970-01-01 00:00:01', 'M jS @ g:i:sa'));
        // The default as Vancouver would show it, stored as UTC, is real too
        $this->assertSame('1970-01-01 03:00:00', $time->toUserTimezone('1970-01-01 08:00:00'));
        $this->assertSame('Dec 31st @ 11:00:00am', $time->toUserTimezone('1969-12-31 16:00:00', 'M jS @ g:i:sa'));
        // Daylight time in the summer, four hours behind
        $this->assertSame('2026-07-01 08:00:00', $time->toUserTimezone('2026-07-01 12:00:00'));
    }

    public function testNearEpochTimesConvertEastOfUtcAndInUtc()
    {
        $time = new Time();

        $_SESSION['s_default_timezone'] = 'Asia/Tokyo';
        $this->assertSame('1970-01-01 09:00:01', $time->toUserTimezone('1970-01-01 00:00:01'));
        $this->assertSame('1970-01-01', $time->toUserTimezone('1969-12-31 16:00:00', 'Y-m-d'));
        $this->assertSame('Jan 1st @ 5:00:00pm', $time->toUserTimezone('1970-01-01 08:00:00', 'M jS @ g:i:sa'));

        $_SESSION['s_default_timezone'] = 'UTC';
        $this->assertSame('1970-01-01 00:00:01', $time->toUserTimezone('1970-01-01 00:00:01'));
        $this->assertSame('1969-12-31', $time->toUserTimezone('1969-12-31 16:00:00', 'Y-m-d'));
        $this->assertSame('Jan 1st @ 8:00:00am', $time->toUserTimezone('1970-01-01 08:00:00', 'M jS @ g:i:sa'));
    }

    public function testToUtcGuardsUnsetTimesAndRoundTrips()
    {
        $time = new Time();
        $_SESSION['s_default_timezone'] = 'America/New_York';

        $this->assertSame('', $time->toUtcTimezone('1970-01-01 00:00:00'));
        $this->assertSame('', $time->toUtcTimezone(null));
        $this->assertSame('-', $time->toUtcTimezone('0000-00-00', 'Y-m-d', '-'));

        $this->assertSame('1970-01-01 00:00:01', $time->toUtcTimezone('1969-12-31 19:00:01'));
        foreach (array('1970-01-01 00:00:01', '1970-01-01 08:00:00', '2026-07-01 12:00:00', '2026-01-15 23:30:00') as $utc) {
            $this->assertSame($utc, $time->toUtcTimezone($time->toUserTimezone($utc)), $utc);
        }
    }

} //@formatter:on
