<?php
/**
 * /tests/Unit/UserTodayListsTest.php
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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * The Domains and SSL Certificates lists marked expiry dates as past using today's date in UTC, and the CSV Importer
 * and the Bulk Updater started new dates from it, so west of UTC the evening was already tomorrow (and the Bulk
 * Updater's Change Expiry Date then converted that date again, which moved it back a day in the morning). They now use
 * Time::userToday(), the user's own date. The Dashboard's Coming Up counts also bind their end date as a parameter
 * rather than pasting it into the SQL. These tests read the page source, since the pages aren't classes.
 */
class UserTodayListsTest extends TestCase
{
    public static function pages()
    {
        return array(
            'domains list' => array('domains/index.php', array('$today = $time->userToday();')),
            'SSL list' => array('ssl/index.php', array('$today = $time->userToday();')),
            'domains list rows' => array('domains/list-data.php', array('$today = $time->userToday();')),
            'SSL list rows' => array('ssl/list-data.php', array('$today = $time->userToday();')),
            'CSV Importer domains' => array('classes/DomainMOD/DomainAdd.php', array('$todays_date = $this->time->userToday();',
                'if ($expiry == \'\') $expiry = $todays_date;')),
            'Bulk Updater' => array('bulk/main/index.php', array('$timestamp_basic = $time->userToday();',
                '$temp_expiry_date = $timestamp_basic;')),
            // (its two dates aren't read by the page yet, so only the UTC checks apply)
            'Bulk Updater custom fields' => array('bulk/cf/step-two/index.php', array()),
        );
    }

    private function source($page)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $page);
        $this->assertNotFalse($source, $page);
        return $source;
    }

    #[DataProvider('pages')]
    public function testTodayIsTheUsersDate($page, array $assignments)
    {
        $source = $this->source($page);

        foreach ($assignments as $assignment) $this->assertStringContainsString($assignment, $source, $page);
        $this->assertDoesNotMatchRegularExpression('/\b(gm)?date\(\s*[\'"]Y-m-d[\'"]/', $source, $page . " uses UTC's date");
        $this->assertStringNotContainsString('timeBasic()', $source, $page . " uses UTC's date");
        $this->assertStringNotContainsString('timeBasicPlusYears(', $source, $page . " uses UTC's date");
    }

    public function testTheBulkUpdatersExpiryDateIsNotConvertedAgain()
    {
        $this->assertStringNotContainsString('toUserTimezone($timestamp_basic', $this->source('bulk/main/index.php'));
    }

    // Coming Up's windows end at the user's date (or, for Hosting, the hosting date) plus each window's days, and
    // Dashboard::dueCounts() binds the end date as a parameter (DashboardComingUpTest checks the statements)
    public function testTheDashboardBindsItsEndDate()
    {
        $source = $this->source('dashboard/index.php');

        $this->assertStringNotContainsString('$end_date . "', $source);
        $this->assertStringContainsString('$coming_up_ends[$window] = $time->userTodayPlusDays($window, $today);', $source);
        $this->assertStringContainsString('if ($hosting_available) $hosting_ends[$window] = DomainMOD\\Hosting::addDays($hosting_today, $window);', $source);
        $class = $this->source('classes/DomainMOD/Dashboard.php');
        $this->assertSame(3, substr_count($class, 'expiry_date <= :end_date'));
        $this->assertSame(1, substr_count($class, "\$stmt->bindValue('end_date', max(\$end_dates), \\PDO::PARAM_STR);"));
    }
}
