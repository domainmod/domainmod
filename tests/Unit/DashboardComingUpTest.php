<?php
/**
 * /tests/Unit/DashboardComingUpTest.php
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
//@formatter:off
namespace DomainMOD\Tests\Unit;

use DomainMOD\Dashboard;
use DomainMOD\Tests\Support\RecordingPdo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * The Dashboard's counts: Coming Up's windows (7 days, 30 days, and the Expiration Days setting, smallest first and each
 * once), the renewals due in each, counted from one query per type that groups by expiry date, overdue ones included
 * back to DUE_FROM, and the counts by status behind the totals and the Pending pills
 */
class DashboardComingUpTest extends TestCase
{
    public static function windows()
    {
        return array(
            'the default 60' => array(60, array(7, 30, 60)),
            'exactly 30' => array(30, array(7, 30)),
            'exactly 7' => array(7, array(7, 30)),
            'between' => array(14, array(7, 14, 30)),
            'under 7' => array(3, array(3, 7, 30)),
            'a year' => array('365', array(7, 30, 365)),
            'none' => array(0, array(7, 30)),
            'far too many' => array(3000000, array(7, 30, 36500)),
        );
    }

    #[DataProvider('windows')]
    public function testTheWindows($expiration_days, array $expected)
    {
        $this->assertSame($expected, Dashboard::comingUpWindows($expiration_days));
    }

    // Each window counts every date up to and including its last day, overdue ones too
    public function testCountByWindow()
    {
        $dates = array('0000-00-00' => 1, '2026-10-01' => 2, '2026-11-21' => 3, '2026-11-28' => 4, '2026-11-29' => 5,
            '2026-12-21' => 6, '2027-01-20' => 7);
        $ends = array(7 => '2026-11-28', 30 => '2026-12-21', 60 => '2027-01-20');
        $this->assertSame(array(7 => 10, 30 => 21, 60 => 28), Dashboard::countByWindow($dates, $ends));
        $this->assertSame(array(7 => 0, 30 => 0), Dashboard::countByWindow(array(), array(7 => '2026-11-28', 30 => '2026-12-21')));
    }

    public static function types()
    {
        return array(
            'domains' => array('domains', "FROM domains\n                WHERE active NOT IN ('0', '10')", 'SELECT expiry_date, count(*)', 'GROUP BY expiry_date'),
            'hosting' => array('hosting', "FROM hosting_services\n                WHERE active <> 0", 'SELECT expiry_date, count(*)', 'GROUP BY expiry_date'),
            'ssl' => array('ssl', "FROM ssl_certs AS sslc, ssl_cert_types AS sslt\n                WHERE sslc.type_id = sslt.id\n                  AND sslc.active NOT IN ('0')", 'SELECT sslc.expiry_date, count(*)', 'GROUP BY sslc.expiry_date'),
        );
    }

    // One statement per type, from DUE_FROM (which the links start at too) to the latest window's last day, with the
    // live statuses the old expiring tiles used
    #[DataProvider('types')]
    public function testDueCounts($type, $from, $select, $group)
    {
        $pdo = new RecordingPdo(array('GROUP BY' => array(array(
            array('expiry_date' => '2026-11-01', 'count' => '2'), array('expiry_date' => '2026-11-25', 'count' => '1'),
            array('expiry_date' => '2026-12-15', 'count' => '4')))));
        $counts = Dashboard::dueCounts($pdo, $type, array(7 => '2026-11-28', 30 => '2026-12-21', 60 => '2027-01-20'));
        $this->assertSame(array(7 => 3, 30 => 7, 60 => 7), $counts);
        $this->assertCount(1, $pdo->log);
        $this->assertStringContainsString($from, $pdo->log[0]['sql']);
        // It selects and groups by the expiry date alone, so ONLY_FULL_GROUP_BY accepts it
        $this->assertStringContainsString($select, $pdo->log[0]['sql']);
        $this->assertStringEndsWith($group, $pdo->log[0]['sql']);
        $this->assertStringContainsString("expiry_date >= :start_date\n", $pdo->log[0]['sql']);
        $this->assertStringContainsString('expiry_date <= :end_date', $pdo->log[0]['sql']);
        $this->assertSame(array('start_date' => '2000-01-01', 'end_date' => '2027-01-20'), $pdo->log[0]['params']);
        $this->assertSame('2000-01-01', Dashboard::DUE_FROM);
    }

    public function testDueCountsWithoutWindowsSendsNothing()
    {
        $pdo = new RecordingPdo();
        $this->assertSame(array(), Dashboard::dueCounts($pdo, 'domains', array()));
        $this->assertSame(array(), $pdo->log);
    }

    public function testAnUnknownTypeOrTableIsRefused()
    {
        $this->expectException(\InvalidArgumentException::class);
        Dashboard::dueCounts(new RecordingPdo(), 'watch', array(7 => '2026-11-28'));
    }

    public function testAnUnknownTableIsRefused()
    {
        $this->expectException(\InvalidArgumentException::class);
        Dashboard::statusCounts(new RecordingPdo(), 'users');
    }

    // The counts by status, and the totals the strip takes from them
    public function testStatusCountsAndTotals()
    {
        $pdo = new RecordingPdo(array('GROUP BY active' => array(array(
            array('active' => '0', 'count' => '4'), array('active' => '1', 'count' => '100'), array('active' => '3', 'count' => '7'),
            array('active' => '5', 'count' => '2'), array('active' => '10', 'count' => '5')))));
        $counts = Dashboard::statusCounts($pdo, 'domains');
        $this->assertSame(array(0 => 4, 1 => 100, 3 => 7, 5 => 2, 10 => 5), $counts);
        $this->assertStringContainsString("FROM domains\n            GROUP BY active", $pdo->log[0]['sql']);
        $this->assertSame(109, Dashboard::countOf($counts, array(0, 10), true));
        $this->assertSame(5, Dashboard::countOf($counts, array(10)));
        $this->assertSame(109, Dashboard::countOf($counts, array(1, 2, 3, 4, 5, 6, 7, 8, 9)));
        $this->assertSame(0, Dashboard::countOf(array(), array(1)));
    }

} //@formatter:on
