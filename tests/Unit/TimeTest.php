<?php
/**
 * /tests/Unit/TimeTest.php
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

class TimeTest extends TestCase
{
    private $savedZone;

    protected function setUp(): void
    {
        $this->savedZone = array_key_exists('s_default_timezone', $_SESSION ?? array())
            ? array($_SESSION['s_default_timezone']) : null;
        unset($_SESSION['s_default_timezone']);
    }

    // Puts back any zone an earlier test left (even a null one), rather than only clearing it
    protected function tearDown(): void
    {
        if ($this->savedZone === null) {
            unset($_SESSION['s_default_timezone']);
        } else {
            $_SESSION['s_default_timezone'] = $this->savedZone[0];
        }
    }

    public static function legacyZones()
    {
        $cases = array();
        foreach (Time::LEGACY_ZONES as $legacy => $target) {
            $cases[$legacy] = array($legacy, $target);
        }
        return $cases;
    }

    public function testTheLegacyZoneMapIsTheReviewedOne()
    {
        // Written out here, so a mistaken change to Time::LEGACY_ZONES fails even on a PHP without the legacy names,
        // where the other tests can only compare a name's offsets with the target the map itself picked. Change this
        // list only together with the map, after checking the new target against the IANA time zone data's backward
        // file.
        // Compared in key order, since only the pairs matter, not the order they're written in
        $expected = array(
            'Africa/Asmera' => 'Africa/Nairobi',
            'America/Argentina/ComodRivadavia' => 'America/Argentina/Catamarca',
            'America/Buenos_Aires' => 'America/Argentina/Buenos_Aires',
            'America/Catamarca' => 'America/Argentina/Catamarca',
            'America/Cordoba' => 'America/Argentina/Cordoba',
            'America/Fort_Wayne' => 'America/Indiana/Indianapolis',
            'America/Godthab' => 'America/Nuuk',
            'America/Indianapolis' => 'America/Indiana/Indianapolis',
            'America/Jujuy' => 'America/Argentina/Jujuy',
            'America/Knox_IN' => 'America/Indiana/Knox',
            'America/Louisville' => 'America/Kentucky/Louisville',
            'America/Mendoza' => 'America/Argentina/Mendoza',
            'America/Rosario' => 'America/Argentina/Cordoba',
            'Antarctica/South_Pole' => 'Pacific/Auckland',
            'Asia/Ashkhabad' => 'Asia/Ashgabat',
            'Asia/Calcutta' => 'Asia/Kolkata',
            'Asia/Choibalsan' => 'Asia/Ulaanbaatar',
            'Asia/Chungking' => 'Asia/Shanghai',
            'Asia/Dacca' => 'Asia/Dhaka',
            'Asia/Katmandu' => 'Asia/Kathmandu',
            'Asia/Macao' => 'Asia/Macau',
            'Asia/Rangoon' => 'Asia/Yangon',
            'Asia/Saigon' => 'Asia/Ho_Chi_Minh',
            'Asia/Thimbu' => 'Asia/Thimphu',
            'Asia/Ujung_Pandang' => 'Asia/Makassar',
            'Asia/Ulan_Bator' => 'Asia/Ulaanbaatar',
            'Atlantic/Faeroe' => 'Atlantic/Faroe',
            'Australia/ACT' => 'Australia/Sydney',
            'Australia/LHI' => 'Australia/Lord_Howe',
            'Australia/NSW' => 'Australia/Sydney',
            'Australia/North' => 'Australia/Darwin',
            'Australia/Queensland' => 'Australia/Brisbane',
            'Australia/South' => 'Australia/Adelaide',
            'Australia/Tasmania' => 'Australia/Hobart',
            'Australia/Victoria' => 'Australia/Melbourne',
            'Australia/West' => 'Australia/Perth',
            'Brazil/Acre' => 'America/Rio_Branco',
            'Brazil/DeNoronha' => 'America/Noronha',
            'Brazil/East' => 'America/Sao_Paulo',
            'Brazil/West' => 'America/Manaus',
            'Canada/Atlantic' => 'America/Halifax',
            'Canada/Central' => 'America/Winnipeg',
            'Canada/East-Saskatchewan' => 'America/Regina',
            'Canada/Eastern' => 'America/Toronto',
            'Canada/Mountain' => 'America/Edmonton',
            'Canada/Newfoundland' => 'America/St_Johns',
            'Canada/Pacific' => 'America/Vancouver',
            'Canada/Saskatchewan' => 'America/Regina',
            'Canada/Yukon' => 'America/Whitehorse',
            'Chile/Continental' => 'America/Santiago',
            'Chile/EasterIsland' => 'Pacific/Easter',
            'Cuba' => 'America/Havana',
            'Egypt' => 'Africa/Cairo',
            'Eire' => 'Europe/Dublin',
            'Europe/Kiev' => 'Europe/Kyiv',
            'Europe/Uzhgorod' => 'Europe/Kyiv',
            'Europe/Zaporozhye' => 'Europe/Kyiv',
            'Greenwich' => 'Etc/GMT',
            'Hongkong' => 'Asia/Hong_Kong',
            'Iceland' => 'Africa/Abidjan',
            'Iran' => 'Asia/Tehran',
            'Israel' => 'Asia/Jerusalem',
            'Jamaica' => 'America/Jamaica',
            'Japan' => 'Asia/Tokyo',
            'Kwajalein' => 'Pacific/Kwajalein',
            'Libya' => 'Africa/Tripoli',
            'Mexico/BajaNorte' => 'America/Tijuana',
            'Mexico/BajaSur' => 'America/Mazatlan',
            'Mexico/General' => 'America/Mexico_City',
            'Pacific/Enderbury' => 'Pacific/Kanton',
            'Pacific/Ponape' => 'Pacific/Guadalcanal',
            'Pacific/Truk' => 'Pacific/Port_Moresby',
            'Poland' => 'Europe/Warsaw',
            'Portugal' => 'Europe/Lisbon',
            'Singapore' => 'Asia/Singapore',
            'Turkey' => 'Europe/Istanbul',
            'US/Alaska' => 'America/Anchorage',
            'US/Aleutian' => 'America/Adak',
            'US/Arizona' => 'America/Phoenix',
            'US/Central' => 'America/Chicago',
            'US/East-Indiana' => 'America/Indiana/Indianapolis',
            'US/Eastern' => 'America/New_York',
            'US/Hawaii' => 'Pacific/Honolulu',
            'US/Indiana-Starke' => 'America/Indiana/Knox',
            'US/Michigan' => 'America/Detroit',
            'US/Mountain' => 'America/Denver',
            'US/Pacific' => 'America/Los_Angeles',
            'US/Pacific-New' => 'America/Los_Angeles',
            'US/Samoa' => 'Pacific/Pago_Pago',
            'Zulu' => 'Etc/UTC',
        );
        $actual = Time::LEGACY_ZONES;
        ksort($expected);
        ksort($actual);
        $this->assertSame($expected, $actual);
    }

    #[DataProvider('legacyZones')]
    public function testEveryLegacyTargetIsAZoneThisPhpAccepts($legacy, $target)
    {
        // On a PHP that uses the system's time zone list without the legacy names (the Debian run), only the targets
        // are accepted, so a target PHP rejects would leave the setting with no zone at all.
        $this->assertSame($target, (new \DateTimeZone($target))->getName());
    }

    #[DataProvider('legacyZones')]
    public function testEveryLegacyNameGivesItsTargetsOffsetInWinterAndSummer($legacy, $target)
    {
        $zone = Time::zone($legacy);

        // Mid-November too, where zones that share their winter and summer offsets can still differ (such as British
        // Columbia's permanent daylight time from November 2026, in newer time zone data)
        foreach (array('2026-01-15 12:00:00', '2026-07-15 12:00:00', '2026-11-15 12:00:00') as $instant) {
            $moment = new \DateTimeImmutable($instant, new \DateTimeZone('UTC'));
            $this->assertSame((new \DateTimeZone($target))->getOffset($moment), $zone->getOffset($moment),
                $legacy . ' at ' . $instant);
            // Where this PHP still knows the legacy name itself, its own offset is an independent check on the target
            // LEGACY_ZONES picked, so a wrong mapping fails here even though the line above compares with the target
            try {
                $own = new \DateTimeZone($legacy);
            } catch (\Exception $e) {
                $own = null;
            }
            if ($own !== null) {
                $this->assertSame($own->getOffset($moment), $zone->getOffset($moment), $legacy . ' (own) at ' . $instant);
            }
        }
        $this->assertTrue(Time::isKnownZone($legacy));
    }

    public static function legacyOffsets()
    {
        // Offsets written out independently of LEGACY_ZONES, in seconds, in January and July 2026
        return array(
            'US/Pacific' => array('US/Pacific', -28800, -25200),
            'US/Eastern' => array('US/Eastern', -18000, -14400),
            'US/Arizona, no daylight saving time' => array('US/Arizona', -25200, -25200),
            'Canada/Pacific' => array('Canada/Pacific', -28800, -25200),
            'Canada/Newfoundland' => array('Canada/Newfoundland', -12600, -9000),
            'Asia/Calcutta' => array('Asia/Calcutta', 19800, 19800),
            'Asia/Katmandu' => array('Asia/Katmandu', 20700, 20700),
            'Asia/Saigon' => array('Asia/Saigon', 25200, 25200),
            'Japan' => array('Japan', 32400, 32400),
            'Europe/Kiev' => array('Europe/Kiev', 7200, 10800),
            'Australia/NSW' => array('Australia/NSW', 39600, 36000),
            'Australia/LHI' => array('Australia/LHI', 39600, 37800),
            'Brazil/East' => array('Brazil/East', -10800, -10800),
            'Zulu' => array('Zulu', 0, 0),
        );
    }

    #[DataProvider('legacyOffsets')]
    public function testLegacyNamesGiveTheRightOffsets($legacy, $winter, $summer)
    {
        $zone = Time::zone($legacy);

        $this->assertSame($winter, $zone->getOffset(new \DateTimeImmutable('2026-01-15 12:00:00', new \DateTimeZone('UTC'))));
        $this->assertSame($summer, $zone->getOffset(new \DateTimeImmutable('2026-07-15 12:00:00', new \DateTimeZone('UTC'))));
    }

    public function testCurrentZonesAreUsedAsTheyAre()
    {
        $this->assertSame('America/Vancouver', Time::zone('America/Vancouver')->getName());
        $this->assertSame('Asia/Kolkata', Time::zone('Asia/Kolkata')->getName());
        $this->assertTrue(Time::isKnownZone('Europe/London'));
    }

    public static function unusableZones()
    {
        return array(
            'unknown' => array('Not/AZone'),
            'empty' => array(''),
            'null' => array(null),
            'NUL byte' => array("UTC\0junk"),
            'NUL byte in a legacy name' => array("US/Pacific\0"),
            'spaces' => array('   '),
        );
    }

    #[DataProvider('unusableZones')]
    public function testAnUnusableNameGivesUtcWithoutThrowing($name)
    {
        $this->assertSame('UTC', Time::zone($name)->getName());
        $this->assertFalse(Time::isKnownZone($name));
    }

    // TimeUnsetTest covers the other placeholders and real times near the epoch
    public function testIsUnsetForAPaddedColumnDefault()
    {
        $this->assertTrue(Time::isUnset(' 1970-01-01 00:00:00 '));
    }

    public static function nearEpochTimes()
    {
        // zone, stored UTC time, then the default format, Y-m-d, and 'M jS @ g:i:sa'
        return array(
            'Vancouver, midnight local' => array('America/Vancouver', '1970-01-01 08:00:00',
                '1970-01-01 00:00:00', '1970-01-01', 'Jan 1st @ 12:00:00am'),
            'Vancouver, one second after the epoch' => array('America/Vancouver', '1970-01-01 00:00:01',
                '1969-12-31 16:00:01', '1969-12-31', 'Dec 31st @ 4:00:01pm'),
            'Vancouver, eight hours before the epoch' => array('America/Vancouver', '1969-12-31 16:00:00',
                '1969-12-31 08:00:00', '1969-12-31', 'Dec 31st @ 8:00:00am'),
        );
    }

    #[DataProvider('nearEpochTimes')]
    public function testToUserTimezoneConvertsARealTimeNearTheEpoch($zone, $stored, $default, $date, $long)
    {
        $time = new Time();
        $_SESSION['s_default_timezone'] = $zone;

        $this->assertSame($default, $time->toUserTimezone($stored));
        $this->assertSame($date, $time->toUserTimezone($stored, 'Y-m-d'));
        $this->assertSame($long, $time->toUserTimezone($stored, 'M jS @ g:i:sa'));
    }

    public function testToUserTimezoneUsesUtcWithoutASessionZone()
    {
        $this->assertSame('2026-07-01 12:00:00', (new Time())->toUserTimezone('2026-07-01 12:00:00'));
    }

    public function testToUserTimezoneAcceptsALegacySessionZone()
    {
        // US/Pacific is the name that stopped pages on servers without the legacy time zone list in 4.25.0
        $time = new Time();
        $_SESSION['s_default_timezone'] = 'US/Pacific';

        $this->assertSame('2026-07-01 05:00:00', $time->toUserTimezone('2026-07-01 12:00:00'));
        $this->assertSame('2026-01-01 04:00:00', $time->toUserTimezone('2026-01-01 12:00:00'));
    }

    public function testToUtcTimezoneConvertsFromTheSessionZone()
    {
        $time = new Time();
        $_SESSION['s_default_timezone'] = 'America/Vancouver';

        $this->assertSame('1970-01-01 16:00:00', $time->toUtcTimezone('1970-01-01 08:00:00'));
        $this->assertSame('2026-07-01 19:00:00', $time->toUtcTimezone('2026-07-01 12:00:00'));
    }

    public static function roundTripZones()
    {
        return array(array('America/Vancouver'), array('Asia/Tokyo'), array('UTC'), array('Canada/Pacific'));
    }

    #[DataProvider('roundTripZones')]
    public function testToUtcTimezoneUndoesToUserTimezone($zone)
    {
        $time = new Time();
        $_SESSION['s_default_timezone'] = $zone;

        foreach (array('1970-01-01 00:00:01', '1969-12-31 16:00:00', '2026-03-08 10:30:00', '2026-11-15 23:59:59') as $stored) {
            $this->assertSame($stored, $time->toUtcTimezone($time->toUserTimezone($stored)), $zone . ' ' . $stored);
        }
    }

} //@formatter:on
