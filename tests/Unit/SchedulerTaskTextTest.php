<?php
/**
 * /tests/Unit/SchedulerTaskTextTest.php
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

use DomainMOD\DomainRefresh;
use DomainMOD\Scheduler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/*
 * The Task Scheduler page's text for each task: its name and description found by its slug (Scheduler::taskText()),
 * its interval (intervalText()), and its last run's duration as plain text (durationText()).
 */
// SOFTWARE_TITLE can't change once it's defined, so these tests define it in their own processes.
#[RunTestsInSeparateProcesses]
class SchedulerTaskTextTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('SOFTWARE_TITLE')) define('SOFTWARE_TITLE', 'DomainMOD');
    }

    public static function taskCases()
    {
        return array(
            'domain-queue' => array('domain-queue', 'Domain Queue Processing', 'Retrieves information for the domains in the Domain Queue and adds them to DomainMOD.'),
            'ssl-queue' => array('ssl-queue', 'SSL Queue Processing', 'Reads the certificates of the sites in the SSL Queue and adds them to DomainMOD.'),
            'expiration-email' => array('expiration-email', 'Send Expiration Email', "Emails everyone who's subscribed about upcoming domain, hosting service, and SSL certificate renewals. Users can subscribe in their User Profile, and administrators can set the From address and the number of days the email covers in System Settings."),
            'update-conversion-rates' => array('update-conversion-rates', 'Update Conversion Rates', "Retrieves the current currency conversion rates and updates every user's costs, which keeps the financial information in DomainMOD accurate. Users can set their currency in their User Profile."),
            'cleanup' => array('cleanup', 'System Cleanup', 'Converts domains to lowercase, checks that every TLD is correct, and records the status of each domain in your segments, which keeps segment filtering up to date. It also checks the domain, SSL certificate, and fee data against each other, and deletes unused fees. Finally, it removes change history older than the retention period set in System Settings.'),
            'check-new-version' => array('check-new-version', 'Check For New Version', 'Checks whether a newer version of DomainMOD is available to download.'),
            'data-warehouse-build' => array('data-warehouse-build', 'Data Warehouse Build', 'Rebuilds the Data Warehouse, so it has the latest information from your servers.'),
        );
    }

    #[DataProvider('taskCases')]
    public function testFindsEachTasksTextBySlug($slug, $name, $description)
    {
        // The stored text is ignored for a known task, so a name stored in the installer's language doesn't show
        $this->assertSame($name, Scheduler::taskText($slug, 'name', 'Stored name'));
        $this->assertSame($description, Scheduler::taskText($slug, 'description', 'Stored <BR>description'));
    }

    // The Refresh Domains task's translated description reads as the English text the database stores
    // (DomainRefresh::TASK_DESCRIPTION)
    public function testTheRefreshDomainsDescriptionMatchesTheStoredText()
    {
        $this->assertSame(DomainRefresh::TASK_DESCRIPTION, Scheduler::taskText('domain-refresh', 'description', ''));
    }

    public function testAnUnknownTaskShowsItsStoredTextAsPlainText()
    {
        $this->assertSame('My Own Task', Scheduler::taskText('my-task', 'name', 'My Own Task'));
        $this->assertSame('Domains: Converts all domain entries. TLDs: Updates & checks them.',
            Scheduler::taskText('my-task', 'description', '<em>Domains:</em> Converts all domain entries.<BR><BR> <em>TLDs:</em> Updates &amp; checks them.'));
        $this->assertSame('<script> stays text', Scheduler::taskText('', 'description', '&lt;script&gt; stays text'));
        $this->assertSame('', Scheduler::taskText('my-task', 'description', null));
    }

    public function testAnUnknownFieldShowsTheStoredText()
    {
        $this->assertSame('Stored', Scheduler::taskText('cleanup', 'interval', 'Stored'));
    }

    public function testTranslatesTheKnownIntervals()
    {
        $this->assertSame('Every 5 Minutes', Scheduler::intervalText('Every 5 Minutes'));
        $this->assertSame('Daily', Scheduler::intervalText('Daily'));
        $this->assertSame('Weekly', Scheduler::intervalText('Weekly'));
    }

    public static function durationCases()
    {
        return array(
            'minutes and seconds' => array(' (<em>2m 3s</em>)', '2m 3s'),
            'seconds only' => array(' (<em>45s</em>)', '45s'),
            'no seconds' => array(' (<em>0s</em>)', '0s'),
            'already plain' => array('1m 5s', '1m 5s'),
            'empty' => array('', ''),
            'null' => array(null, ''),
        );
    }

    #[DataProvider('durationCases')]
    public function testShowsTheDurationAsPlainText($stored, $expected)
    {
        $this->assertSame($expected, Scheduler::durationText($stored));
    }

} //@formatter:on
