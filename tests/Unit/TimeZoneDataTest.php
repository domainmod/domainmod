<?php
/**
 * /tests/Unit/TimeZoneDataTest.php
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TimeZoneDataTest extends TestCase
{
    // Versions compared with the real MIN_TZDB_VERSION, worked out from it, so the tests still hold when it's raised
    public static function versions()
    {
        list($year, $release) = array_map('intval', explode('.', Time::MIN_TZDB_VERSION));

        return array(
            'the same version' => array($year . '.' . $release, false),
            'a later release in the same year' => array($year . '.' . ($release + 1), false),
            'an earlier release in the same year' => array($year . '.' . ($release - 1), $release > 1),
            'a later year with a lower release' => array(($year + 1) . '.1', false),
            'an earlier year with a higher release' => array(($year - 1) . '.' . ($release + 9), true),
            'a two-digit release in the same year' => array($year . '.' . ($release + 10), false),
            // The unreadable versions below are older than the minimum, so reading them by mistake would fail a test
            'leading zeros in the release' => array(($year - 1) . '.01', false),
            'a trailing newline' => array(($year - 1) . ".1\n", false),
            'the system data' => array('0.system', false),
            'empty' => array('', false),
            'junk' => array('not a version', false),
            'a release letter instead of a number' => array(($year - 1) . 'a', false),
            'release zero' => array(($year - 1) . '.0', false),
            'a trailing part' => array(($year - 1) . '.1.1', false),
        );
    }

    #[DataProvider('versions')]
    public function testItComparesWithTheMinimumVersion($version, $expected)
    {
        $this->assertSame($expected, Time::isTzdbOutdated($version));
    }

    public function testTwoDigitReleasesCompareAsNumbers()
    {
        // As text, 2026.10 would sort before 2026.9
        $this->assertFalse(Time::isTzdbOutdated('2026.10', '2026.9'));
        $this->assertTrue(Time::isTzdbOutdated('2026.9', '2026.10'));
    }

    public function testTheYearComesBeforeTheRelease()
    {
        $this->assertTrue(Time::isTzdbOutdated('2025.12', '2026.1'));
        $this->assertFalse(Time::isTzdbOutdated('2027.1', '2026.12'));
    }

    public function testAnUnreadableMinimumNeverWarns()
    {
        $this->assertFalse(Time::isTzdbOutdated('2020.1', '0.system'));
    }

    public function testTheMinimumIsInPhpsForm()
    {
        $this->assertMatchesRegularExpression('/^[1-9][0-9]{3}\.[1-9][0-9]*\z/', Time::MIN_TZDB_VERSION);
    }

    public function testWithoutAVersionItChecksThisServersData()
    {
        $this->assertSame(Time::isTzdbOutdated(timezone_version_get()), Time::isTzdbOutdated());
    }

} //@formatter:on
