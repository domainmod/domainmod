<?php
/**
 * /tests/Unit/HostingProjectionTest.php
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

use DomainMOD\Hosting;
use DomainMOD\HostingReport;
use DomainMOD\Tests\Support\FakeDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * The Hosting reports' projected renewals (DomainMOD\HostingReport): the payments in a range, the Month grouping's
 * totals and range, Hosting Renewals' list and chart, and At-Risk Hosting Services with option B. The expected dates and
 * amounts are worked out by hand.
 */
class HostingProjectionTest extends TestCase
{
    const TODAY = '2026-11-20';

    protected function setUp(): void
    {
        Hosting::forgetAvailability();
    }

    protected function tearDown(): void
    {
        Hosting::forgetAvailability();
    }

    // A services() row: Active, with a fee and a rate
    private function service($id, $date, $term, $day, $cost = 10.0, $extra = array())
    {
        return (object) array_merge(array('id' => $id, 'name' => 'service-' . $id, 'expiry_date' => $date,
            'billing_term' => (string) $term, 'renewal_day' => (string) $day, 'autorenew' => '1', 'active' => '1',
            'provider_name' => 'Provider', 'owner_name' => 'Owner', 'username' => 'user', 'type' => 'Shared',
            'has_fee' => 1, 'has_rate' => 1, 'converted_cost' => $cost), $extra);
    }

    private function dates($payments)
    {
        return array_map(function ($payment) { return $payment->renewal_date; }, $payments);
    }

    public function testAMonthlyServiceOverThreeAndTwelveMonths()
    {
        $service = $this->service(1, '2026-01-15', 1, 15);

        $this->assertSame(array('2026-01-15', '2026-02-15', '2026-03-15'),
            $this->dates(HostingReport::payments(array($service), '2026-01-01', '2026-03-31')));

        $totals = HostingReport::monthTotals(array($service), '2026-01-01', '2026-12-31');
        $this->assertSame(120.0, $totals['total']);
        $this->assertSame(1, $totals['count']);
        $this->assertCount(12, $totals['rows']);
        $this->assertSame(10.0, $totals['month']['2026-1']);
        $this->assertSame(10.0, $totals['month']['2026-12']);
        $this->assertSame(array(2026 => 120.0), $totals['year']);
    }

    // A service whose next renewal is before the range start is overdue, and its later renewals in the range still count
    public function testAServiceRenewingBeforeTheRangeStillCountsItsLaterRenewals()
    {
        $service = $this->service(1, '2025-11-10', 1, 10);

        $payments = HostingReport::payments(array($service), '2026-01-01', '2026-03-31');
        $this->assertSame(array('2026-01-10', '2026-02-10', '2026-03-10'), $this->dates($payments));
        foreach ($payments as $payment) $this->assertTrue($payment->projected);
    }

    public function testAJanuaryThirtyFirstMonthlyServiceKeepsItsDay()
    {
        $service = $this->service(1, '2026-01-31', 1, 31);

        $payments = HostingReport::payments(array($service), '2026-01-01', '2026-04-30');
        $this->assertSame(array('2026-01-31', '2026-02-28', '2026-03-31', '2026-04-30'), $this->dates($payments));
        $this->assertFalse($payments[0]->projected);
        $this->assertTrue($payments[1]->projected);
    }

    // The plan's case: stored January 31, 2026 with Renews On Day 1 has five payments in the 90 days from that date
    public function testTheFivePaymentsInNinetyDays()
    {
        $service = $this->service(1, '2026-01-31', 1, 1);

        $this->assertSame(array('2026-01-31', '2026-02-01', '2026-03-01', '2026-04-01', '2026-05-01'),
            $this->dates(HostingReport::payments(array($service), '2026-01-31', Hosting::addDays('2026-01-31', 90))));
    }

    public function testAThirtySixMonthServiceInsideAndOutsideTheRange()
    {
        $service = $this->service(1, '2026-06-01', 36, 1, 360.0);

        $inside = HostingReport::monthTotals(array($service), '2026-01-01', '2026-12-31');
        $this->assertSame(360.0, $inside['total']);
        $this->assertSame(array('2026-6' => 360.0), $inside['month']);

        $outside = HostingReport::monthTotals(array($service), '2027-01-01', '2028-12-31');
        $this->assertSame(0.0, $outside['total']);
        $this->assertSame(0, $outside['count']);
        $this->assertSame(array(), $outside['rows']);

        $this->assertSame(array('2029-06-01'),
            $this->dates(HostingReport::payments(array($service), '2029-01-01', '2029-12-31')));
    }

    // A Pending (Renewal) service counts (services() leaves out only Cancelled ones), and no fee or rate isn't costed
    public function testPendingCountsAndOnlyCostedServicesAreInTheTotals()
    {
        $pending = $this->service(1, '2026-03-01', 12, 1, 100.0, array('active' => (string) Hosting::PENDING_RENEWAL));
        $no_fee = $this->service(2, '2026-03-05', 12, 5, 50.0, array('has_fee' => 0));
        $no_rate = $this->service(3, '2026-03-06', 12, 6, null, array('has_rate' => 0));

        $totals = HostingReport::monthTotals(array($pending, $no_fee, $no_rate), '2026-01-01', '2026-12-31');
        $this->assertSame(100.0, $totals['total']);
        $this->assertSame(1, $totals['count']);
    }

    public function testCancelledServicesAreNeverRead()
    {
        $pdo = new FakeDatabase(array('SELECT db_version' => '4.27.0'));
        (new HostingReport($pdo, 1))->services('2026-12-31');

        $sql = $pdo->queriesWith('FROM hosting_services AS hs');
        $this->assertCount(1, $sql);
        $this->assertStringContainsString("hs.active NOT IN ('0')", $sql[0]);
        $this->assertStringContainsString('hs.expiry_date <= :end_date', $sql[0]);
        $this->assertStringNotContainsString('2026-', $sql[0]);
        // A service has no single domain, so no domain is joined or read
        $this->assertStringNotContainsString('domains', $sql[0]);
        $this->assertStringNotContainsString('domain_id', $sql[0]);
    }

    public function testNothingIsReadBeforeTheDatabaseUpdate()
    {
        $pdo = new FakeDatabase(array('SELECT db_version' => '4.26.0'));

        $this->assertSame(array(), (new HostingReport($pdo, 1))->services());
        $this->assertCount(0, $pdo->queriesWith('hosting_services'));
    }

    public function testTwoServicesOnDifferentDaysKeepTheirOwnDays()
    {
        $payments = HostingReport::payments(array($this->service(1, '2026-12-20', 12, 20),
            $this->service(2, '2026-12-03', 1, 3)), '2026-12-01', '2026-12-31');

        $this->assertSame(array('2026-12-03', '2026-12-20'), $this->dates($payments));
        $this->assertSame(array(2, 1), array_map(function ($payment) { return $payment->id; }, $payments));
    }

    public function testAnInvalidTermHasNoPayments()
    {
        $service = $this->service(1, '2026-03-01', 7, 1);

        $this->assertSame(array(), HostingReport::payments(array($service), '2026-01-01', '2026-12-31'));
        $this->assertSame(0, HostingReport::monthTotals(array($service), '2026-01-01', '2026-12-31')['count']);
    }

    public static function rangeCases()
    {
        return array(
            'missing' => array('', '2026-11-01', '2027-10-31', ''),
            'valid' => array('2026-01-01 - 2026-06-30', '2026-01-01', '2026-06-30', ''),
            'exactly 36 months' => array('2026-01-01 - 2028-12-31', '2026-01-01', '2028-12-31', ''),
            'invalid' => array('nonsense', '2026-11-01', '2027-10-31', 'invalid'),
            'not a real date' => array('2026-02-30 - 2026-06-30', '2026-11-01', '2027-10-31', 'invalid'),
            'reversed' => array('2026-06-30 - 2026-01-01', '2026-11-01', '2027-10-31', 'invalid'),
            'over 36 months' => array('2026-01-01 - 2029-01-01', '2026-11-01', '2027-10-31', 'too_long'),
        );
    }

    #[DataProvider('rangeCases')]
    public function testTheMonthRange($daterange, $start, $end, $problem)
    {
        $this->assertSame(array($start, $end, $problem), HostingReport::resolveMonthRange($daterange, self::TODAY));
    }

    public function testTheDefaultRangeIsTwelveMonthsFromThisMonth()
    {
        $this->assertSame(array('2026-11-01', '2027-10-31'), HostingReport::defaultMonthRange(self::TODAY));
        $this->assertSame(array('2028-01-01', '2028-12-31'), HostingReport::defaultMonthRange('2028-01-31'));
    }

    // Hosting Renewals: an overdue stored date once, marked overdue, then the projected renewals from today
    public function testTheRenewalsListShowsAnOverdueDateOnceThenItsRenewals()
    {
        $service = $this->service(1, '2026-11-01', 1, 1);

        $list = HostingReport::renewalList(array($service), self::TODAY, Hosting::addDays(self::TODAY, 90));
        $this->assertSame(array('2026-11-01', '2026-12-01', '2027-01-01', '2027-02-01'), $this->dates($list));
        $this->assertSame(array(true, false, false, false), array_map(function ($row) { return $row->overdue; }, $list));
    }

    public function testTheRenewalChartProjectsEachMonth()
    {
        $monthly = $this->service(1, '2026-11-01', 1, 1, 10.0);
        $yearly = $this->service(2, '2027-03-15', 12, 15, 100.0, array('has_fee' => 0));

        $months = HostingReport::renewalMonths(array($monthly, $yearly), self::TODAY);

        // Overdue, then November 2026 through October 2027
        $this->assertCount(13, $months);
        $this->assertTrue($months[0]['overdue']);
        $this->assertSame(1, $months[0]['count']);
        // November's renewal (the 1st) is before today, so it's only the Overdue bar
        $this->assertSame(0, $months[1]['count']);
        for ($i = 2; $i <= 12; $i++) $this->assertSame(10.0, $months[$i]['cost'], 'bar ' . $i);
        // March 2027 has the yearly service too, which has no fee
        $this->assertSame(2, $months[5]['count']);
        $this->assertSame(1, $months[5]['missing']);
    }

    // At-Risk with option B
    public function testAtRiskOptionB()
    {
        $rows = array(
            'healthy auto-renewing' => $this->service(1, '2026-12-01', 1, 1),
            'not auto-renewing' => $this->service(2, '2026-12-01', 1, 1, 10.0, array('autorenew' => '0')),
            'auto-renewing, a day passed' => $this->service(3, '2026-11-19', 1, 19),
            'auto-renewing, 15 days passed' => $this->service(7, '2026-11-05', 1, 5),
            'no fee' => $this->service(4, '2026-12-01', 1, 1, 10.0, array('has_fee' => 0)),
            'no rate' => $this->service(5, '2026-12-01', 1, 1, null, array('has_rate' => 0)),
            'yearly with no fee in six months' => $this->service(6, '2027-05-20', 12, 20, 10.0, array('has_fee' => 0,
                'autorenew' => '0')),
        );

        $listed = HostingReport::atRisk(array_values($rows), self::TODAY, '2026-12-20');

        $reasons = array();
        foreach ($listed as $row) $reasons[$row->id] = $row->reasons;
        $this->assertSame(array(2 => array('expiring'), 3 => array('confirm'), 7 => array('overdue'), 4 => array('nofee'),
            5 => array('nofee')), $reasons);
    }

    // A service has no single domain, so there are no domain reasons, and a healthy auto-renewing service isn't listed
    public function testAtRiskHasNoDomainReasons()
    {
        $healthy = $this->service(1, '2026-12-01', 1, 1);
        $this->assertSame(array(), HostingReport::atRisk(array($healthy), self::TODAY, '2026-12-20'));

        $listed = HostingReport::atRisk(array($this->service(2, '2026-12-01', 1, 1, 10.0, array('autorenew' => '0'))),
            self::TODAY, '2026-12-20');
        $this->assertSame(array('expiring'), $listed[0]->reasons);
        $this->assertObjectNotHasProperty('domain_problem', $listed[0]);
    }

    // The renewal state and window checks use the hosting date
    public function testAtRiskUsesTheHostingDate()
    {
        $row = $this->service(1, '2026-11-20', 1, 20);
        $not_auto = $this->service(2, '2026-11-20', 1, 20, 10.0, array('autorenew' => '0'));

        // The hosting date is already the 21st (east of UTC), while it's still the 20th in UTC
        $listed = HostingReport::atRisk(array($row, $not_auto), '2026-11-21', '2026-12-21');
        $this->assertSame(array('confirm'), $listed[0]->reasons);
        $this->assertSame(array('overdue'), $listed[1]->reasons);
        $this->assertSame(array(), HostingReport::atRisk(array(clone $row), '2026-11-20', '2026-12-20'));
    }

    // One fixture for the Confirm renewal rule: the Dashboard's count (confirmCount()) and At-Risk's Confirm renewal rows
    // pick the same services, from the day after the date through day 14, Active, auto-renewing, with a valid term
    public function testTheConfirmCountMatchesAtRisksConfirmRows()
    {
        $rows = array(
            $this->service(1, '2026-11-20', 1, 20),                                         // the date itself
            $this->service(2, '2026-11-19', 1, 19),                                         // one day after
            $this->service(3, '2026-11-06', 12, 6),                                         // day 14
            $this->service(4, '2026-11-05', 1, 5),                                          // day 15
            $this->service(5, '2026-11-19', 1, 19, 10.0, array('autorenew' => '0')),        // auto-renew off
            $this->service(6, '2026-11-19', 1, 19, 10.0, array('active' => '3')),           // Pending (Renewal)
            $this->service(7, '2026-11-19', 1, 19, 10.0, array('active' => '5')),           // Pending (Setup)
            $this->service(8, '2026-11-19', 1, 19, 10.0, array('active' => '0')),           // Cancelled
            $this->service(9, '2026-11-19', 2, 19),                                         // an invalid term
            $this->service(10, '2026-11-12', 1, 12, null, array('has_rate' => 0)),          // no rate, in the window
        );

        $confirm_ids = array();
        foreach (HostingReport::atRisk(array_map(function ($row) { return clone $row; }, $rows), self::TODAY,
            Hosting::addDays(self::TODAY, 30)) as $row) {
            if (in_array('confirm', $row->reasons, true)) $confirm_ids[] = $row->id;
        }
        $this->assertSame(array(2, 3, 10), $confirm_ids);
        $this->assertSame(count($confirm_ids), HostingReport::confirmCount($rows, self::TODAY));
    }

    // Hosting Renewals lists the passed stored dates (overdue and waiting to be confirmed together, by date), then the
    // projected renewals, each with its state
    public function testTheRenewalsListShowsEachPassedDatesState()
    {
        $list = HostingReport::renewalList(array(
            $this->service(1, '2026-11-01', 12, 1, 10.0, array('autorenew' => '0')),       // overdue
            $this->service(2, '2026-11-10', 12, 10),                                        // waiting to be confirmed
            $this->service(3, '2026-11-15', 12, 15, 10.0, array('autorenew' => '0')),       // overdue
            $this->service(4, '2026-12-01', 12, 1)), self::TODAY, Hosting::addDays(self::TODAY, 90));

        $this->assertSame(array('2026-11-01', '2026-11-10', '2026-11-15', '2026-12-01'), $this->dates($list));
        $this->assertSame(array('overdue', 'confirm', 'overdue', ''), array_map(function ($row) { return $row->state; }, $list));
        $this->assertSame(array(true, false, true, false), array_map(function ($row) { return $row->overdue; }, $list));
        $this->assertSame(array(false, true, false, false), array_map(function ($row) { return $row->confirm; }, $list));
    }

    // A stored date waiting for its renewal to be confirmed is in no bar of Hosting Renewals' chart (not the Overdue bar,
    // and not its month), while its next renewal is in its month
    public function testTheRenewalChartLeavesADateToConfirmOut()
    {
        $months = HostingReport::renewalMonths(array($this->service(1, '2026-11-19', 1, 19, 10.0)), self::TODAY);

        $this->assertCount(12, $months);
        $this->assertFalse($months[0]['overdue']);
        $this->assertSame(0, $months[0]['count']);
        $this->assertSame(1, $months[1]['count']);
        $this->assertSame(10.0, $months[1]['cost']);
    }

    // The tiles count from today on: a monthly service due yesterday adds only its next renewal to the 30-day tile, and an
    // overdue one only its renewals from today
    public function testTheWindowTilesLeaveOutEveryPassedDate()
    {
        $rows = HostingReport::renewalList(array(
            $this->service(1, '2026-11-19', 1, 19, 10.0),
            $this->service(2, '2026-11-01', 1, 1, 5.0, array('autorenew' => '0', 'has_fee' => 0))), self::TODAY,
            Hosting::addDays(self::TODAY, 90));

        $windows = HostingReport::windowTotals($rows, self::TODAY, array(30, 60, 90));

        $this->assertSame(array('count' => 2, 'cost' => 10.0, 'missing' => 1), $windows[30]);
        $this->assertSame(array('count' => 4, 'cost' => 20.0, 'missing' => 2), $windows[60]);
        // February 19 is past the 90 days (February 18)
        $this->assertSame(array('count' => 5, 'cost' => 20.0, 'missing' => 3), $windows[90]);
    }

    // A billing term DomainMOD doesn't know gives no projected renewals, but a passed stored date is still listed as
    // overdue on Hosting Renewals and counted in its chart's Overdue bar, as At-Risk and the Hosting list show it
    public function testAnInvalidTermStillShowsItsOverdueDate()
    {
        $service = $this->service(1, '2026-11-19', 2, 19);
        $list = HostingReport::renewalList(array($service), self::TODAY, Hosting::addDays(self::TODAY, 90));
        $this->assertSame(array('2026-11-19'), $this->dates($list));
        $this->assertSame('overdue', $list[0]->state);

        $months = HostingReport::renewalMonths(array($this->service(1, '2026-11-19', 2, 19)), self::TODAY);
        $this->assertTrue($months[0]['overdue']);
        $this->assertSame(1, $months[0]['count']);
        $this->assertSame(0, array_sum(array_column(array_slice($months, 1), 'count')));
    }
}
