<?php
/**
 * /tests/Unit/EmailHostingSectionTest.php
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

use DomainMOD\Email;
use DomainMOD\Hosting;
use DomainMOD\Tests\Support\Instance;
use DomainMOD\Tests\Support\RecordingPdo;
use DomainMOD\Time;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/*
 * The expiration email's Hosting section: which services it keeps (Email::hostingRows(), each on its stored date), their
 * order, each one's renewal state against the hosting date (Overdue, or Confirm renewal while an Active, auto-renewing
 * service waits for its renewal to be confirmed), the HTML and plain text parts, and the opening line that names hosting
 * services.
 */
class EmailHostingSectionTest extends TestCase
{
    private function service($id, $name, $expiry_date, $term = 1, $day = 1, $autorenew = 1, $active = 1, $type = 'Shared')
    {
        return (object) array('id' => $id, 'name' => $name, 'expiry_date' => $expiry_date, 'billing_term' => $term,
            'renewal_day' => $day, 'autorenew' => $autorenew, 'active' => $active, 'type' => $type);
    }

    private function states($rows)
    {
        $states = array();
        foreach ($rows as $row) $states[$row->name] = $row->state;
        return $states;
    }

    // Every service is listed on its stored date (DomainMOD never moves a date by itself), with its renewal state: an
    // Active, auto-renewing service is waiting for its renewal to be confirmed for 14 days after its date, then overdue,
    // and auto-renew off, a Pending status, or an invalid term is overdue as soon as the date passes
    public function testEachServiceKeepsItsStoredDateWithItsState()
    {
        $rows = Email::hostingRows(array(
            $this->service(1, 'Yesterday', '2026-07-14', 1, 14),
            $this->service(2, 'Day 14', '2026-07-01', 12, 1),
            $this->service(3, 'Day 15', '2026-06-30', 1, 30),
            $this->service(4, 'Not Auto', '2026-07-14', 1, 14, 0),
            $this->service(5, 'Pending', '2026-07-14', 1, 14, 1, 3),
            $this->service(6, 'Bad Term', '2026-07-14', 2),
            $this->service(7, 'Today', '2026-07-15', 1, 15)), '2026-07-15', '2026-08-31');

        $this->assertSame(array('Day 15' => 'overdue', 'Day 14' => 'confirm', 'Bad Term' => 'overdue', 'Not Auto' => 'overdue',
            'Pending' => 'overdue', 'Yesterday' => 'confirm', 'Today' => ''), $this->states($rows));
        foreach ($rows as $row) {
            $this->assertSame($row->state === Hosting::STATE_OVERDUE, $row->overdue, $row->name);
            $this->assertSame($row->state === Hosting::STATE_CONFIRM, $row->confirm, $row->name);
            $this->assertObjectNotHasProperty('effective_date', $row);
        }
    }

    // A service waiting for its renewal to be confirmed is listed on its stored date, so it's always inside the cut-off
    // and keeps an otherwise empty email going, while a later one outside the cut-off isn't listed
    public function testAServiceToConfirmKeepsTheEmailGoing()
    {
        $rows = Email::hostingRows(array($this->service(1, 'Monthly', '2026-07-01', 1, 1),
            $this->service(2, 'Later', '2026-07-17', 1, 17)), '2026-07-15', '2026-07-16');

        $this->assertSame(array('Monthly' => 'confirm'), $this->states($rows));
        $this->assertSame('2026-07-01', $rows[0]->expiry_date);
    }

    // The hosting date decides what has passed: a date on the hosting date itself is due today, not marked
    public function testTheHostingDateDecidesWhatHasPassed()
    {
        $rows = Email::hostingRows(array(
            $this->service(1, 'Today', '2026-07-15', 1, 15),
            $this->service(2, 'Tomorrow', '2026-07-16', 1, 16)), '2026-07-15', '2026-07-16');

        $this->assertSame(array('Today' => '', 'Tomorrow' => ''), $this->states($rows));
    }

    public function testSortedByRenewalDateThenName()
    {
        $rows = Email::hostingRows(array(
            $this->service(1, 'Beta', '2026-07-20', 1, 20, 0),
            $this->service(2, 'Alpha', '2026-07-20', 1, 20, 0),
            $this->service(3, 'Passed', '2026-06-18', 1, 18)), '2026-07-15', '2026-08-31');

        $this->assertSame(array('Passed', 'Alpha', 'Beta'), array_column(array_map('get_object_vars', $rows), 'name'));
    }

    // Overdue is marked in red and Confirm renewal in amber in the HTML part, and both in the plain text part, each with
    // its translated name in capitals between asterisks
    public function testTheHtmlAndTextPartsMarkTheStates()
    {
        $email = Instance::withoutConstructor(Email::class, array('full_url' => 'https://example.invalid'));
        $rows = Email::hostingRows(array(
            // Names are stored HTML-escaped, as the add and edit pages save them
            $this->service(7, 'Main &lt;Site&gt;', '2026-07-01', 1, 1, 0),
            $this->service(9, 'Shop', '2026-07-10', 1, 10, 1),
            $this->service(8, 'Blog', '2026-07-20', 1, 20, 0, 1, '')), '2026-07-15', '2026-08-31');

        $html = $email->showHostingHtml($rows);
        $text = $email->showHostingText($rows);

        $this->assertStringContainsString('<u>Hosting</u>', $html);
        $this->assertStringContainsString('/hosting/edit.php?hsid=7', $html);
        $this->assertStringContainsString('Main &lt;Site&gt; (Shared)', $html);
        $this->assertSame(1, substr_count($html, '*OVERDUE*'));
        $this->assertSame(1, substr_count($html, '*CONFIRM RENEWAL*'));
        $this->assertMatchesRegularExpression('#<font color="\#CC0000">2026-07-01</font>.*?color="\#CC0000">\*OVERDUE\*</font>#s', $html);
        $this->assertMatchesRegularExpression('#<font color="\#995C00">2026-07-10</font>.*?hsid=9.*?color="\#995C00">\*CONFIRM RENEWAL\*</font>#s', $html);
        $this->assertStringContainsString("[Hosting]\n2026-07-01 - Main <Site> (Shared) *OVERDUE*\n2026-07-10 - Shop (Shared) *CONFIRM RENEWAL*\n2026-07-20 - Blog\n\n", $text);
        $this->assertSame('', $email->showHostingHtml(array()));
        $this->assertSame('', $email->showHostingText(array()));
    }

    // With nothing else due, one service waiting for its renewal to be confirmed is enough for the email to be sent
    public function testOneServiceToConfirmIsEnoughForAnEmail()
    {
        Hosting::forgetAvailability();
        $yesterday = Hosting::addDays(gmdate('Y-m-d'), -1);
        $pdo = new RecordingPdo(array(
            'SELECT db_version' => array(array(array('db_version' => '4.27.0'))),
            'FROM hosting_services' => array(array(array('id' => 4, 'name' => 'Shop', 'expiry_date' => $yesterday,
                'billing_term' => 1, 'renewal_day' => 1, 'autorenew' => 1, 'active' => 1, 'type' => 'Shared')))));
        $email = Instance::withoutConstructor(Email::class, array('deeb' => (object) array('cnxx' => $pdo),
            'time' => new Time(), 'number_of_days' => 30));

        try {
            $expiring = $email->checkExpiring(true);
        } finally {
            Hosting::forgetAvailability();
        }

        $this->assertIsArray($expiring);
        $this->assertSame('0', $expiring[0]);
        $this->assertSame('0', $expiring[1]);
        $this->assertCount(1, $expiring[2]);
        $this->assertTrue($expiring[2][0]->confirm);
        // The hosting time zone is the expiration email task's
        $this->assertSame(array('slug' => 'expiration-email'), $pdo->statementsWith('FROM scheduler')[0]['params']);
    }

    // The opening line names hosting services once the database update has added them
    #[RunInSeparateProcess]
    public function testTheOpeningLineNamesHostingServices()
    {
        if (!defined('SOFTWARE_TITLE')) define('SOFTWARE_TITLE', 'DomainMOD');
        $email = Instance::withoutConstructor(Email::class, array('number_of_days' => 30, 'hosting_available' => true));
        $this->assertSame('Below is a list of all the domains, SSL certificates, and hosting services in DomainMOD that are expiring or renewing in the next 30 days.', $email->introLine());

        $email->hosting_available = false;
        $this->assertStringNotContainsString('hosting', $email->introLine());
    }

} //@formatter:on
