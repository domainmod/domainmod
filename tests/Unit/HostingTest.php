<?php
/**
 * /tests/Unit/HostingTest.php
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

use DomainMOD\Date;
use DomainMOD\Hosting;
use DomainMOD\Scheduler;
use DomainMOD\Tests\Support\FakeDatabase;
use DomainMOD\Tests\Support\RecordingPdo;
use PHPUnit\Framework\Attributes\DataProvider;
use DomainMOD\Tests\Support\UnconnectedDatabase;
use PHPUnit\Framework\TestCase;

class HostingTest extends TestCase
{
    public static function terms()
    {
        return array(
            'monthly' => array(1, 10.00, 120.00),
            'quarterly' => array(3, 30.00, 120.00),
            'six months' => array(6, 60.00, 120.00),
            'yearly' => array(12, 100.00, 100.00),
            'two years' => array(24, 100.00, 50.00),
            'three years' => array(36, 90.00, 30.00),
            'not a term' => array(2, 10.00, null),
            'zero' => array(0, 10.00, null),
            'a string' => array('12x', 10.00, null),
        );
    }

    #[DataProvider('terms')]
    public function testYearlyCostScalesOneTermToTwelveMonths($term, $cost, $expected)
    {
        $this->assertSame($expected, Hosting::yearlyCost($cost, $term));
    }

    public function testValidTermAcceptsOnlyTheAllowedTerms()
    {
        foreach (Hosting::BILLING_TERMS as $term) {
            $this->assertTrue(Hosting::validTerm($term));
            $this->assertTrue(Hosting::validTerm((string) $term));
        }
        foreach (array(0, 2, 5, 13, 48, -1, '', '1.0', ' 1', null) as $term) $this->assertFalse(Hosting::validTerm($term));
    }

    public function testYearlyCostSqlLeavesAnInvalidTermOut()
    {
        $this->assertSame('(CASE WHEN hs.billing_term IN (1, 3, 6, 12, 24, 36) THEN hs.total_cost * 12 / hs.billing_term ELSE NULL END)',
            Hosting::yearlyCostSql());
    }

    /*
     * The renewal state on the hosting date: none until the date has passed, Confirm renewal for an Active, auto-renewing
     * service with a valid term from the day after its date through the 14th day after it, Overdue from the 15th day,
     * and Overdue as soon as the date passes for auto-renew off, a Pending status, or an invalid term. Cancelled has none.
     */
    public static function stateCases()
    {
        $confirm = Hosting::STATE_CONFIRM;
        $overdue = Hosting::STATE_OVERDUE;
        return array(
            'the renewal date itself' => array(Hosting::ACTIVE, 1, '2026-07-15', 1, '2026-07-15', ''),
            'not due yet' => array(Hosting::ACTIVE, 1, '2026-08-01', 1, '2026-07-15', ''),
            'one day after' => array(Hosting::ACTIVE, 1, '2026-07-14', 1, '2026-07-15', $confirm),
            'day 14' => array(Hosting::ACTIVE, 1, '2026-07-01', 1, '2026-07-15', $confirm),
            'day 15' => array(Hosting::ACTIVE, 1, '2026-06-30', 1, '2026-07-15', $overdue),
            'day 14 across a month end' => array(Hosting::ACTIVE, 1, '2026-02-28', 12, '2026-03-14', $confirm),
            'day 15 across a month end' => array(Hosting::ACTIVE, 1, '2026-02-28', 12, '2026-03-15', $overdue),
            'a year ago' => array(Hosting::ACTIVE, 1, '2025-07-14', 1, '2026-07-15', $overdue),
            'auto-renew off, one day after' => array(Hosting::ACTIVE, 0, '2026-07-14', 1, '2026-07-15', $overdue),
            'auto-renew off, on the date' => array(Hosting::ACTIVE, 0, '2026-07-15', 1, '2026-07-15', ''),
            'Pending (Setup)' => array(Hosting::PENDING_SETUP, 1, '2026-07-14', 1, '2026-07-15', $overdue),
            'Pending (Renewal)' => array(Hosting::PENDING_RENEWAL, 1, '2026-07-14', 1, '2026-07-15', $overdue),
            'Pending (Other)' => array(Hosting::PENDING_OTHER, 1, '2026-07-14', 1, '2026-07-15', $overdue),
            'Cancelled, passed' => array(Hosting::CANCELLED, 1, '2026-07-14', 1, '2026-07-15', ''),
            'Cancelled, long passed' => array(Hosting::CANCELLED, 0, '2020-01-01', 1, '2026-07-15', ''),
            'an invalid term' => array(Hosting::ACTIVE, 1, '2026-07-14', 2, '2026-07-15', $overdue),
            'stored as strings' => array('1', '1', '2026-07-14', '12', '2026-07-15', $confirm),
        );
    }

    #[DataProvider('stateCases')]
    public function testTheRenewalState($active, $autorenew, $date, $term, $today, $expected)
    {
        $this->assertSame($expected, Hosting::renewalState($active, $autorenew, $date, $term, $today));
    }

    // The hosting date decides, not the UTC date: just after midnight in Melbourne, a service due on the UTC date has
    // already passed there
    public function testTheRenewalStateFollowsTheHostingDate()
    {
        $now = gmmktime(14, 30, 0, 7, 15, 2026);
        $this->assertSame('', Hosting::renewalState(Hosting::ACTIVE, 1, '2026-07-15', 1, Hosting::dateInZone('UTC', $now)));
        $this->assertSame(Hosting::STATE_CONFIRM, Hosting::renewalState(Hosting::ACTIVE, 1, '2026-07-15', 1,
            Hosting::dateInZone('Australia/Melbourne', $now)));
        $this->assertSame(Hosting::STATE_OVERDUE, Hosting::renewalState(Hosting::ACTIVE, 0, '2026-07-15', 1,
            Hosting::dateInZone('Australia/Melbourne', $now)));
        $this->assertSame(14, Hosting::CONFIRM_DAYS);
    }

    // The hosting time zone is the expiration email task's, read each time, or UTC when its row or zone isn't usable
    public function testTheHostingZoneIsTheExpirationEmailsZone()
    {
        $pdo = new RecordingPdo(array('FROM scheduler' => array(array(array('timezone' => 'Australia/Melbourne')))));
        $this->assertSame('Australia/Melbourne', Hosting::hostingZone($pdo));
        $this->assertSame(array('slug' => 'expiration-email'), $pdo->log[0]['params']);
        $this->assertStringContainsString('SELECT `timezone`', $pdo->log[0]['sql']);

        $this->assertSame('UTC', Hosting::hostingZone(new RecordingPdo()));
        $this->assertSame('UTC', Hosting::hostingZone(new RecordingPdo(array('FROM scheduler' => array(array(array('timezone' => 'Mars/Olympus')))))));
        $this->assertSame('UTC', Hosting::hostingZone(new RecordingPdo(array('FROM scheduler' => new \PDOException('gone')))));
    }

    public function testRenewalsInRangeStartsWithTheStoredDate()
    {
        // Stored January 31 with "Renews On Day" 1: five payments in the 90 days from that date (May 1 is day 90)
        $this->assertSame(array('2026-01-31', '2026-02-01', '2026-03-01', '2026-04-01', '2026-05-01'),
            Hosting::renewalsInRange('2026-01-31', 1, 1, '2026-01-31', Hosting::addDays('2026-01-31', 90)));
    }

    public function testRenewalsInRangeSkipsToTheFirstRenewalInTheRange()
    {
        $this->assertSame(array('2026-04-15', '2026-05-15', '2026-06-15'),
            Hosting::renewalsInRange('2001-01-15', 1, 15, '2026-04-01', '2026-06-30'));
        $this->assertSame(array('2026-04-15', '2026-05-31'), Hosting::renewalsInRange('2026-04-15', 1, 31, '2026-04-01', '2026-06-15'));
    }

    public function testRenewalsInRangeMatchesOneTermStepping()
    {
        $dates = new Date();
        foreach (array(array('2009-01-31', 1, 31), array('2011-08-30', 3, 30), array('2004-02-29', 12, 29), array('2000-06-01', 36, 1)) as $case) {
            list($date, $term, $day) = $case;
            $expected = array();
            for ($stepped = $date; $stepped <= '2031-12-31'; $stepped = $dates->addMonths($stepped, $term, $day)) {
                if ($stepped >= '2026-03-01') $expected[] = $stepped;
            }
            $this->assertSame($expected, Hosting::renewalsInRange($date, $term, $day, '2026-03-01', '2031-12-31'), implode(' ', $case));
        }
    }

    public function testRenewalsInRangeLeavesOutWhatIsOutside()
    {
        $this->assertSame(array(), Hosting::renewalsInRange('2026-01-15', 36, 15, '2026-02-01', '2028-12-31'));
        $this->assertSame(array('2029-01-15'), Hosting::renewalsInRange('2026-01-15', 36, 15, '2026-02-01', '2029-01-15'));
        $this->assertSame(array(), Hosting::renewalsInRange('2026-01-15', 2, 15, '2026-01-01', '2026-12-31'));
        $this->assertSame(array(), Hosting::renewalsInRange('2026-01-15', 1, 15, '2026-12-31', '2026-01-01'));
    }

    public static function dayEdits()
    {
        return array(
            'only the date, March 31 to April 15' => array('2026-03-31', 31, '2026-04-15', 31, 15),
            'only the date, January 31 to February 28' => array('2026-01-31', 31, '2026-02-28', 31, 31),
            'only the date, to a 30th in a 31-day month' => array('2026-01-31', 31, '2026-03-30', 31, 30),
            'only the day' => array('2026-03-31', 31, '2026-03-31', 15, 15),
            'both' => array('2026-03-31', 31, '2026-04-15', 20, 20),
            'neither' => array('2026-03-31', 31, '2026-03-31', 31, 31),
            'February 28 with day 28' => array('2026-01-28', 28, '2026-02-28', 28, 28),
        );
    }

    #[DataProvider('dayEdits')]
    public function testRenewalDayAfterEditFollowsTheDate($old_date, $old_day, $new_date, $posted_day, $expected)
    {
        $this->assertSame($expected, Hosting::renewalDayAfterEdit($old_date, $old_day, $new_date, $posted_day));
    }

    public static function dayChoices()
    {
        return array(
            'the renewal date\'s own day' => array('2027-01-31', 31, 0),
            'a day the date was clamped from' => array('2027-02-28', 31, 31),
            'a different day' => array('2027-03-15', 1, 1),
            'an invalid stored date' => array('0000-00-00', 15, 15),
        );
    }

    // The edit page's Renews On Day menu opens on "Same as the Renewal Date" (0) when the stored day is the date's own
    // day
    #[DataProvider('dayChoices')]
    public function testRenewalDayChoiceOpensOnSameAsTheRenewalDate($date, $day, $expected)
    {
        $this->assertSame($expected, Hosting::renewalDayChoice($date, $day));
    }

    public static function daySaves()
    {
        return array(
            // Due January 31 with day 31, opened with "Same as the Renewal Date", and moved to February 28: the choice
            // is unchanged, so the date-only rule keeps day 31 (and the service renews on March 31)
            'a date-only edit keeps a clamped day' => array('2027-01-31', 31, '2027-02-28', 0, 0, 31),
            'a date-only edit follows the date' => array('2027-03-31', 31, '2027-04-15', 0, 0, 15),
            'a date-only edit with a day chosen' => array('2027-03-15', 1, '2027-04-20', 1, 1, 20),
            'nothing changed' => array('2027-02-28', 31, '2027-02-28', 31, 31, 31),
            // Stored February 28 with day 31 (so the form opens on 31), and the user picks "Same as the Renewal Date"
            'picking Same takes the date\'s day' => array('2027-02-28', 31, '2027-02-28', 0, 31, 28),
            'picking Same with a new date' => array('2027-02-28', 31, '2027-05-17', 0, 31, 17),
            'picking a day saves it as given' => array('2027-01-31', 31, '2027-02-28', 15, 0, 15),
            'a missing opened choice counts as changed' => array('2027-01-31', 31, '2027-02-28', 0, -1, 28),
        );
    }

    #[DataProvider('daySaves')]
    public function testRenewalDayToSaveTellsAChangedChoiceFromADateOnlyEdit($old_date, $old_day, $new_date, $posted, $opened, $expected)
    {
        $this->assertSame($expected, Hosting::renewalDayToSave($old_date, $old_day, $new_date, $posted, $opened));
    }

    /*
     * The edit page's flow for a service stored at February 28 with day 31: the form opens on 31, the user picks "Same
     * as the Renewal Date" and saves with a blank name, which fails, so the form shows again with the posted choice and
     * the opened choice posted back unchanged (the page's hidden field, see HostingServicePagesTest), and the retry,
     * fixing only the name, still saves 28
     */
    public function testAFailedSaveKeepsTheChoiceTheFormOpenedWith()
    {
        $opened = Hosting::renewalDayChoice('2027-02-28', 31);
        $this->assertSame(31, $opened);

        // The first Save (refused for the blank name) posts 0 with the opened 31, and the form is drawn again from them
        $first_posted = Hosting::postedInt('0', -1);
        $redisplayed_opened = Hosting::postedInt((string) $opened, -1);
        $this->assertSame(0, $first_posted);
        $this->assertSame(31, $redisplayed_opened);

        // The retry posts the same choice and the same opened value, so the change is still seen
        $this->assertSame(28, Hosting::renewalDayToSave('2027-02-28', 31, '2027-02-28', $first_posted, $redisplayed_opened));
    }

    // A posted id is digits only, and a number past PHP_INT_MAX is refused rather than clamped to another row's id (on
    // 32-bit PHP that's anything over 2,147,483,647, which the 32-bit run covers)
    public function testPostedIntRefusesWhatIsntAWholeNumber()
    {
        $this->assertSame(0, Hosting::postedInt('0', -1));
        $this->assertSame(7, Hosting::postedInt('7', -1));
        $this->assertSame(7, Hosting::postedInt('007', -1));
        $this->assertSame(0, Hosting::postedInt('000', -1));
        $this->assertSame(PHP_INT_MAX, Hosting::postedInt((string) PHP_INT_MAX, -1));
        foreach (array(null, '', '-1', '1.5', '1e3', ' 12', '12 ', 'abc', array('123'), 12) as $bad) {
            $this->assertSame(-1, Hosting::postedInt($bad, -1), var_export($bad, true));
        }
    }

    public function testPostedIntRefusesANumberPastTheLargestInteger()
    {
        // One past PHP_INT_MAX, worked out as a string so it never becomes a float
        $past = substr((string) PHP_INT_MAX, 0, -1) . ((int) substr((string) PHP_INT_MAX, -1) + 1);
        $this->assertSame(-1, Hosting::postedInt($past, -1));
        $this->assertSame(-1, Hosting::postedInt('99999999999999999999999', -1));
        $this->assertSame(-1, Hosting::postedInt('2147483648' . (PHP_INT_SIZE === 4 ? '' : '0000000000'), -1));
        if (PHP_INT_SIZE === 4) {
            $this->assertSame(2147483647, Hosting::postedInt('2147483647', -1));
            $this->assertSame(-1, Hosting::postedInt('2147483648', -1));
        }
    }

    // The domain pages' service menu: every service that isn't Cancelled, plus the domain's own (kept even when
    // Cancelled), sorted in the query, labelled with its provider, owner, and account, with " #id" only on repeats
    public function testTheServiceMenuLabelsEachService()
    {
        $pdo = new RecordingPdo(array('FROM hosting_services AS hs' => array(array(
            array('id' => 4, 'name' => 'Shared Pro', 'provider_name' => 'Acme', 'owner_name' => 'Greg', 'username' => 'greg'),
            array('id' => 9, 'name' => 'VPS', 'provider_name' => 'Acme', 'owner_name' => 'Greg', 'username' => 'a-very-long-username'),
            array('id' => 2, 'name' => 'VPS', 'provider_name' => 'Acme', 'owner_name' => 'Greg', 'username' => 'a-very-long-username-too'),
            array('id' => 3, 'name' => 'VPS', 'provider_name' => 'Other', 'owner_name' => 'Greg', 'username' => 'greg'),
            array('id' => 7, 'name' => 'Web', 'provider_name' => 'Acme', 'owner_name' => 'Greg', 'username' => '12345678901234élise')))));

        $menu = Hosting::serviceMenu($pdo, 12);

        $this->assertSame(array(
            4 => 'Shared Pro (Acme, Greg, greg)',
            9 => 'VPS (Acme, Greg, a-very-long-use...) #9',
            2 => 'VPS (Acme, Greg, a-very-long-use...) #2',
            3 => 'VPS (Other, Greg, greg)',
            // Cut by character, so the accented letter is kept whole
            7 => 'Web (Acme, Greg, 12345678901234é...)'), $menu);
        $this->assertTrue(mb_check_encoding($menu[7], 'UTF-8'));

        $sql = $pdo->log[0]['sql'];
        $this->assertStringContainsString('hs.active <> :cancelled OR hs.id = :keep_id', $sql);
        $this->assertStringContainsString('ORDER BY hs.name, h.name, o.name, hpa.username, hs.id', $sql);
        // The owner comes from the account, as on the Hosting list
        $this->assertStringContainsString('LEFT JOIN owners AS o ON o.id = hpa.owner_id', $sql);
        $this->assertSame(array('cancelled' => Hosting::CANCELLED, 'keep_id' => 12), $pdo->log[0]['params']);
    }

    public function testServiceLabelsReadOnlyTheGivenIds()
    {
        $pdo = new RecordingPdo(array('FROM hosting_services AS hs' => array(array(
            array('id' => 5, 'name' => 'DNS', 'provider_name' => 'Acme', 'owner_name' => 'Greg', 'username' => 'greg')))));

        $this->assertSame(array(5 => 'DNS (Acme, Greg, greg)'), Hosting::serviceLabels($pdo, array('5', 5, 0, -3, 'x')));
        $this->assertStringContainsString('WHERE hs.id IN (5)', $pdo->log[0]['sql']);

        // No ids, no query
        $empty = new RecordingPdo();
        $this->assertSame(array(), Hosting::serviceLabels($empty, array(0, '')));
        $this->assertSame(array(), $empty->log);
    }

    // The domain writes' check of a service: None needs nothing, a gone one and a Cancelled one are refused, and a
    // Cancelled one the domain is already on is kept
    public function testTheServiceErrorRefusesAGoneOrCancelledService()
    {
        $this->assertSame('', Hosting::serviceError(new RecordingPdo(), 0));

        $active = new RecordingPdo(array('FROM hosting_services' => array(array(array('active' => Hosting::ACTIVE)))));
        $this->assertSame('', Hosting::serviceError($active, 4));
        $this->assertStringContainsString('LOCK IN SHARE MODE', $active->log[0]['sql']);

        $this->assertSame('The hosting service you chose no longer exists. Please choose another one.',
            Hosting::serviceError(new RecordingPdo(), 4));

        $cancelled = array('FROM hosting_services' => array(array(array('active' => Hosting::CANCELLED))));
        $this->assertSame('The hosting service you chose is cancelled. Please choose another one.',
            Hosting::serviceError(new RecordingPdo($cancelled), 4));
        $this->assertSame('The hosting service you chose is cancelled. Please choose another one.',
            Hosting::serviceError(new RecordingPdo($cancelled), 4, 7));
        $this->assertSame('', Hosting::serviceError(new RecordingPdo($cancelled), 4, 4));
    }

    public function testTheUnavailableMessageNamesHosting()
    {
        $this->assertSame('Hosting becomes available after the next database update.', Hosting::unavailableMessage());
    }

    /*
     * The pre-4.27.0 domains.hosting_id statements run only while the column is there: before the update, but not once
     * a stopped upgrade has dropped it without moving the version, and never after the update (which isn't asked about
     * the column at all)
     */
    public function testTheLegacyDomainColumnOnlyWhileItIsThere()
    {
        $cases = array(array('4.26.0', '1', true), array('4.26.0', '0', false), array('4.27.0', '1', false));
        foreach ($cases as $case) {
            list($version, $columns, $expected) = $case;
            Hosting::forgetAvailability();
            $database = new FakeDatabase(array('SELECT db_version' => $version, 'INFORMATION_SCHEMA' => $columns));
            $this->assertSame($expected, Hosting::legacyDomainColumn($database), $version . ' ' . $columns);
            $this->assertSame($expected, Hosting::legacyDomainColumn($database), 'cached');
            $this->assertLessThanOrEqual(1, count($database->queriesWith('INFORMATION_SCHEMA')));
            foreach ($database->queriesWith('INFORMATION_SCHEMA') as $sql) {
                $this->assertMatchesRegularExpression("/`TABLE_NAME` = 'domains'\\s+AND `COLUMN_NAME` = 'hosting_id'/", $sql);
            }
            if ($version === '4.27.0') $this->assertCount(0, $database->queriesWith('INFORMATION_SCHEMA'));
        }
        Hosting::forgetAvailability();
    }

    // A shared database object with no connection (as the unit tests' pages have) isn't available, rather than an error
    public function testHostingIsntAvailableWithoutAConnection()
    {
        UnconnectedDatabase::install();
        try {
            Hosting::forgetAvailability();
            $this->assertFalse(Hosting::isAvailable());
        } finally {
            UnconnectedDatabase::remove();
            Hosting::forgetAvailability();
        }
    }

    public function testStatusNames()
    {
        $this->assertSame(array(1, 5, 3, 4, 0), array_keys(Hosting::statusOptions()));
        $this->assertSame('Cancelled', Hosting::statusName(0));
        $this->assertSame('', Hosting::statusName(9));
    }

    /*
     * The System Cleanup's hosting fee step waits for the hosting lock only as long as a cron run with a time limit can
     * spare, leaving the needs of a lookup task still to come in the run (Scheduler::reserveFor()). Run Now and a run
     * with no limit wait the full TASK_WAIT.
     */
    public function testTheCleanupsLockWaitFitsTheCronRunsTimeAndTurn()
    {
        $turn = new \ReflectionProperty(Scheduler::class, 'turn');
        $max_execution_time = ini_get('max_execution_time');
        $request_time = $_SERVER['REQUEST_TIME_FLOAT'] ?? null;
        try {
            ini_set('max_execution_time', '0');
            $this->assertSame(Hosting::TASK_WAIT, Hosting::taskWait('cleanup', true));

            ini_set('max_execution_time', '30');
            $_SERVER['REQUEST_TIME_FLOAT'] = 1000.0;
            $this->assertSame(Hosting::TASK_WAIT, Hosting::taskWait('cleanup', false, 1000.0));
            $this->assertSame(22, Hosting::taskWait('cleanup', true, 1000.0));
            // on the Watch List's turn, before it has run: its needs are left too
            $turn->setValue(null, array('owner' => 'watch-list', 'ran' => array()));
            $this->assertSame(2, Hosting::taskWait('cleanup', true, 1000.0));
            // once the owner has run, nothing more is kept for it
            $turn->setValue(null, array('owner' => 'ssl-queue', 'ran' => array('ssl-queue' => true)));
            $this->assertSame(22, Hosting::taskWait('cleanup', true, 1000.0));
            // never below zero
            $turn->setValue(null, array('owner' => 'watch-list', 'ran' => array()));
            $this->assertSame(0, Hosting::taskWait('cleanup', true, 1025.0));
            // a run with no owner leaves the needs of a lookup task due later in it, but not of one that has run
            $turn->setValue(null, array('owner' => '', 'ran' => array(), 'due' => array('watch-list')));
            $this->assertSame(2, Hosting::taskWait('cleanup', true, 1000.0));
            $turn->setValue(null, array('owner' => '', 'ran' => array('ssl-queue' => true), 'due' => array('ssl-queue')));
            $this->assertSame(22, Hosting::taskWait('cleanup', true, 1000.0));
            $turn->setValue(null, array('owner' => '', 'ran' => array(), 'due' => array('ssl-queue')));
            $this->assertSame(2, Hosting::taskWait('cleanup', true, 1000.0));
            $this->assertSame(Hosting::TASK_WAIT, Hosting::taskWait('cleanup', false, 1000.0));
        } finally {
            $turn->setValue(null, null);
            ini_set('max_execution_time', $max_execution_time);
            if ($request_time === null) unset($_SERVER['REQUEST_TIME_FLOAT']); else $_SERVER['REQUEST_TIME_FLOAT'] = $request_time;
        }
    }

    public function testHostingDateUsesTheZone()
    {
        // 2026-07-15 13:30 UTC is already July 16 in Melbourne and still July 15 in Los Angeles
        $now = gmmktime(14, 30, 0, 7, 15, 2026);
        $this->assertSame('2026-07-16', Hosting::dateInZone('Australia/Melbourne', $now));
        $this->assertSame('2026-07-15', Hosting::dateInZone('America/Los_Angeles', $now));
    }

} //@formatter:on
