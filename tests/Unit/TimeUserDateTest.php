<?php
/**
 * /tests/Unit/TimeUserDateTest.php
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
 * Dates shown in the user's time zone: the fee reports' Last Updated, and the expiry date a new domain or SSL
 * certificate starts with. Both used to convert a bare date (midnight UTC), which is the previous day west of UTC.
 */
class TimeUserDateTest extends TestCase
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

    public function testLastChangedIsTheUpdateDateInTheUsersZone()
    {
        $time = new Time();

        // 2026-11-17 15:00 UTC is still the 17th in Vancouver, where the old midnight-UTC date showed the 16th
        $_SESSION['s_default_timezone'] = 'America/Vancouver';
        $this->assertSame('2026-11-17', $time->lastChangedDate('2026-11-17 15:00:00', '2026-01-01 00:00:00'));
        // Early in the UTC day it's the evening before in Vancouver
        $this->assertSame('2026-11-16', $time->lastChangedDate('2026-11-17 03:00:00', '2026-01-01 00:00:00'));

        // Late in the UTC day it's the next day east of UTC
        $_SESSION['s_default_timezone'] = 'Asia/Tokyo';
        $this->assertSame('2026-11-18', $time->lastChangedDate('2026-11-17 20:00:00', '2026-01-01 00:00:00'));

        $_SESSION['s_default_timezone'] = 'UTC';
        $this->assertSame('2026-11-17', $time->lastChangedDate('2026-11-17 23:59:59', '2026-01-01 00:00:00'));
    }

    public function testLastChangedFallsBackToTheInsertTimeThenBlank()
    {
        $time = new Time();
        $_SESSION['s_default_timezone'] = 'America/Vancouver';

        $this->assertSame('2026-03-01', $time->lastChangedDate('1970-01-01 00:00:00', '2026-03-01 18:00:00'));
        $this->assertSame('2026-03-01', $time->lastChangedDate(null, '2026-03-01 18:00:00'));
        $this->assertSame('', $time->lastChangedDate('1970-01-01 00:00:00', '0000-00-00 00:00:00'));
        $this->assertSame('-', $time->lastChangedDate('', '', '-'));
    }

    public function testANewExpiryDateIsAYearFromTodayInTheUsersZone()
    {
        $time = new Time();

        // 10:00 on the 17th in Vancouver (18:00 UTC): a year from the 17th, not from the 16th
        $_SESSION['s_default_timezone'] = 'America/Vancouver';
        $this->assertSame('2027-11-17', $time->userDatePlusYears(1, '@' . strtotime('2026-11-17 18:00:00 UTC')));
        // 20:00 on the 17th in Vancouver is already the 18th in UTC: still the 17th here
        $this->assertSame('2027-11-17', $time->userDatePlusYears(1, '@' . strtotime('2026-11-18 04:00:00 UTC')));

        // 08:00 on the 18th in Tokyo is still the 17th in UTC
        $_SESSION['s_default_timezone'] = 'Asia/Tokyo';
        $this->assertSame('2027-11-18', $time->userDatePlusYears(1, '@' . strtotime('2026-11-17 23:00:00 UTC')));

        // A leap day moves on to 1 March, as the old date did
        $_SESSION['s_default_timezone'] = 'UTC';
        $this->assertSame('2029-03-01', $time->userDatePlusYears(1, '2028-02-29 12:00:00'));
    }
}
