<?php
/**
 * /tests/Unit/RenewalChartHostingTest.php
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

use DomainMOD\Database;
use DomainMOD\Reporting;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\TestCase;

/*
 * The Dashboard's renewal chart's Hosting series: Reporting::hostingMonths() totals each service's payments by month,
 * with an overdue stored date (before the hosting date) in the Overdue bar instead of its month, and a stored date
 * waiting for its renewal to be confirmed in no bar, renewalMonthsBoth()
 * puts each month's total on the bar for the same year and month (never by position), and an overdue service shows the
 * Overdue bar by itself, and renewalMonthsBothTable() gains its column only when the bars have one.
 */
class RenewalChartHostingTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = array('s_default_currency_symbol' => '$', 's_default_currency_symbol_order' => '0',
            's_default_currency_symbol_space' => '0');
        $this->setDatabase(Instance::withoutConstructor(Database::class));
    }

    protected function tearDown(): void
    {
        $_SESSION = array();
        $this->setDatabase(null);
    }

    private function setDatabase($value)
    {
        (new \ReflectionProperty(Database::class, 'instance'))->setValue(null, $value);
    }

    private function row($expiry_date, $cost, $has_fee = 1, $has_rate = 1)
    {
        return (object) array('expiry_date' => $expiry_date, 'converted_cost' => $cost, 'has_fee' => $has_fee,
            'has_rate' => $has_rate);
    }

    // A renewalHostingMonths() row: Active, with auto-renew off unless $autorenew is 1
    private function service($expiry_date, $term, $day, $cost, $has_fee = 1, $has_rate = 1, $autorenew = 0)
    {
        return (object) array('expiry_date' => $expiry_date, 'billing_term' => $term, 'renewal_day' => $day,
            'active' => 1, 'autorenew' => $autorenew, 'converted_cost' => $cost, 'has_fee' => $has_fee,
            'has_rate' => $has_rate);
    }

    public function testMonthlyAndYearlyServicesAreTotalledByMonth()
    {
        $reporting = new Reporting();
        $months = $reporting->hostingMonths(array(
            $this->service('2026-11-20', 1, 20, '10.00'),
            $this->service('2027-03-05', 12, 5, '100.00'),
            $this->service('2026-12-01', 1, 1, null, 0, 0)), '2026-11-01', '2027-10-31');

        $this->assertCount(12, $months);
        $this->assertSame(array('count' => 1, 'cost' => 10.0, 'missing' => 0), $months['2026-11']);
        $this->assertSame(2, $months['2026-12']['count']);
        $this->assertSame(1, $months['2026-12']['missing']);
        $this->assertEqualsWithDelta(110.0, $months['2027-03']['cost'], 0.001);
        $this->assertEqualsWithDelta(10.0, $months['2027-10']['cost'], 0.001);
    }

    public function testAnOverdueServicesPaymentsBeforeTheFirstMonthAreLeftOut()
    {
        $reporting = new Reporting();
        $months = $reporting->hostingMonths(array($this->service('2026-08-15', 1, 15, '10.00')), '2026-11-01', '2027-10-31');

        $this->assertSame(array('2026-11', '2026-12', '2027-01', '2027-02', '2027-03', '2027-04', '2027-05', '2027-06',
            '2027-07', '2027-08', '2027-09', '2027-10'), array_keys($months));
    }

    public function testAnInvalidTermHasNoPayments()
    {
        $this->assertSame(array(), (new Reporting())->hostingMonths(array($this->service('2026-11-20', 2, 20, '10.00')),
            '2026-11-01', '2027-10-31'));
    }

    public function testEachMonthsHostingTotalGoesOnItsOwnBar()
    {
        $reporting = new Reporting();
        $hosting = array('2026-11' => array('count' => 1, 'cost' => 10.0, 'missing' => 0),
            '2027-10' => array('count' => 2, 'cost' => 20.0, 'missing' => 1),
            '2026-10' => array('count' => 1, 'cost' => 99.0, 'missing' => 0));

        // An overdue domain adds the Overdue bar, so the hosting months have to be matched by year and month
        $months = $reporting->renewalMonthsBoth(array($this->row('2026-10-01', '5.00'), $this->row('2026-11-20', '1.00')),
            array(), '2026-11-16', $hosting);

        $this->assertCount(13, $months);
        $this->assertTrue($months[0]['overdue']);
        $this->assertSame(0, $months[0]['hosting']['count']);
        $this->assertEqualsWithDelta(5.0, $months[0]['cost'], 0.001);
        $this->assertSame('November 2026', $months[1]['hosting']['label']);
        $this->assertEqualsWithDelta(10.0, $months[1]['hosting']['cost'], 0.001);
        $this->assertEqualsWithDelta(11.0, $months[1]['cost'], 0.001);
        $this->assertSame('October 2027', $months[12]['label']);
        $this->assertSame(2, $months[12]['hosting']['count']);
        $this->assertSame(1, $months[12]['hosting']['missing']);
        $this->assertEqualsWithDelta(20.0, $months[12]['cost'], 0.001);
        $total = array_sum(array_column(array_column($months, 'hosting'), 'cost'));
        $this->assertEqualsWithDelta(30.0, $total, 0.001);
    }

    public function testWithoutHostingTheBarsAreAsBefore()
    {
        $months = (new Reporting())->renewalMonthsBoth(array($this->row('2026-11-20', '1.00')), array(), '2026-11-16');

        $this->assertArrayNotHasKey('hosting', $months[0]);
        $html = (new Reporting())->renewalMonthsBothTable($months);
        $this->assertStringNotContainsString('>Hosting<', $html);
    }

    public function testTheFiguresTableGainsTheHostingColumn()
    {
        $reporting = new Reporting();
        $months = $reporting->renewalMonthsBoth(array(), array(), '2026-11-16',
            array('2026-11' => array('count' => 3, 'cost' => 1234.5, 'missing' => 2)));

        $html = $reporting->renewalMonthsBothTable($months);

        $this->assertStringContainsString('<th scope="col" class="text-end">Hosting</th>', $html);
        $this->assertStringContainsString('$1,234.50', $html);
        $this->assertStringContainsString('2 hosting services have no fee or rate', $html);
        $this->assertSame(12, substr_count($html, '<th scope="row"'));
        // Month, domains, SSL certificates, hosting, and the total, in each row
        $this->assertSame(12 * 4, substr_count($html, '<td class="text-end">'));
    }

    // An overdue stored date goes in the Overdue bar, not its month, and the next projected payment is in its own month
    public function testAnOverdueStoredDateIsInTheOverdueBar()
    {
        $months = (new Reporting())->hostingMonths(array($this->service('2026-10-10', 1, 10, '10.00'),
            $this->service('2026-12-05', 12, 5, '50.00', 0)), '2026-11-01', '2027-10-31', '2026-11-21');

        $this->assertSame(array('count' => 1, 'cost' => 10.0, 'missing' => 0), $months['overdue']);
        // October's stored date is before the chart, and its projected November 10 payment stays in November
        $this->assertSame(array('count' => 1, 'cost' => 10.0, 'missing' => 0), $months['2026-11']);
        // December has the monthly service's next payment and the service due in December, which isn't overdue
        $this->assertSame(array('count' => 2, 'cost' => 10.0, 'missing' => 1), $months['2026-12']);
    }

    // An overdue date earlier in the current month is only in the Overdue bar, and its next payment in its own month
    public function testAnOverdueDateEarlierThisMonthLeavesThisMonth()
    {
        $months = (new Reporting())->hostingMonths(array($this->service('2026-11-10', 1, 10, '10.00')),
            '2026-11-01', '2027-10-31', '2026-11-21');

        $this->assertSame(1, $months['overdue']['count']);
        $this->assertArrayNotHasKey('2026-11', $months);
        $this->assertSame(array('count' => 1, 'cost' => 10.0, 'missing' => 0), $months['2026-12']);
        $this->assertSame(10.0 * 12, array_sum(array_column($months, 'cost')));

        // The bars: Overdue first, then November with nothing from hosting and December with the next payment
        $bars = (new Reporting())->renewalMonthsBoth(array(), array(), '2026-11-21', $months);
        $this->assertCount(13, $bars);
        $this->assertTrue($bars[0]['overdue']);
        $this->assertEqualsWithDelta(10.0, $bars[0]['hosting']['cost'], 0.001);
        $this->assertSame(0, $bars[1]['hosting']['count']);
        $this->assertSame('December 2026', $bars[2]['label']);
        $this->assertEqualsWithDelta(10.0, $bars[2]['hosting']['cost'], 0.001);
    }

    // One overdue service, with no overdue domain or SSL certificate, still gives the Overdue bar, with empty stacks
    public function testAnOverdueServiceAloneGivesTheOverdueBar()
    {
        $hosting = array('overdue' => array('count' => 1, 'cost' => 25.0, 'missing' => 0),
            '2026-12' => array('count' => 1, 'cost' => 25.0, 'missing' => 0));

        $months = (new Reporting())->renewalMonthsBoth(array($this->row('2026-11-25', '3.00')),
            array($this->row('2026-12-02', '4.00')), '2026-11-21', $hosting);

        $this->assertCount(13, $months);
        $this->assertTrue($months[0]['overdue']);
        $this->assertSame(_('Overdue'), $months[0]['label']);
        $this->assertSame(0, $months[0]['domains']['count']);
        $this->assertSame(0, $months[0]['certs']['count']);
        $this->assertSame(1, $months[0]['hosting']['count']);
        $this->assertEqualsWithDelta(25.0, $months[0]['cost'], 0.001);
        $this->assertEqualsWithDelta(3.0, $months[1]['cost'], 0.001);
        $this->assertEqualsWithDelta(29.0, $months[2]['cost'], 0.001);
    }

    // Without overdue services, and no other overdue items, there's no Overdue bar
    public function testNoOverdueBarWithoutOverdueServices()
    {
        $months = (new Reporting())->hostingMonths(array($this->service('2026-11-21', 1, 21, '10.00')),
            '2026-11-01', '2027-10-31', '2026-11-21');
        $this->assertArrayNotHasKey('overdue', $months);

        $bars = (new Reporting())->renewalMonthsBoth(array(), array(), '2026-11-21', $months);
        $this->assertCount(12, $bars);
        $this->assertFalse($bars[0]['overdue']);
        $this->assertSame(1, $bars[0]['hosting']['count']);
    }

    /*
     * The user's date anchors the months, while the hosting date decides what's overdue: it's January 31 for the user
     * and already February 1 in the hosting time zone, so a service due January 31 with auto-renew off is overdue, and
     * one due February 1 is in February
     */
    public function testTheUsersDateAnchorsTheMonthsAndTheHostingDateDecidesOverdue()
    {
        $reporting = new Reporting();
        $hosting = $reporting->hostingMonths(array($this->service('2027-01-31', 12, 31, '100.00'),
            $this->service('2027-02-01', 12, 1, '40.00')), '2027-01-01', '2027-12-31', '2027-02-01');

        $this->assertSame(array('count' => 1, 'cost' => 100.0, 'missing' => 0), $hosting['overdue']);
        $this->assertArrayNotHasKey('2027-01', $hosting);
        $this->assertSame(array('count' => 1, 'cost' => 40.0, 'missing' => 0), $hosting['2027-02']);

        $bars = $reporting->renewalMonthsBoth(array(), array(), '2027-01-31', $hosting);
        $this->assertCount(13, $bars);
        $this->assertTrue($bars[0]['overdue']);
        $this->assertEqualsWithDelta(100.0, $bars[0]['hosting']['cost'], 0.001);
        $this->assertSame('January 2027', $bars[1]['label']);
        $this->assertSame(0, $bars[1]['hosting']['count']);
        $this->assertSame('February 2027', $bars[2]['label']);
        $this->assertEqualsWithDelta(40.0, $bars[2]['hosting']['cost'], 0.001);
    }

    /*
     * The same split with a monthly service due January 31 with auto-renew on: on the hosting date (February 1) it's
     * waiting for its renewal to be confirmed, so its stored date is in neither the Overdue bar nor January, and its next
     * payment (February 28) is in February
     */
    public function testADateToConfirmIsInNoBarWhileItsNextPaymentIsInItsMonth()
    {
        $reporting = new Reporting();
        $hosting = $reporting->hostingMonths(array($this->service('2027-01-31', 1, 31, '10.00', 1, 1, 1)), '2027-01-01',
            '2027-12-31', '2027-02-01');

        $this->assertArrayNotHasKey('overdue', $hosting);
        $this->assertArrayNotHasKey('2027-01', $hosting);
        $this->assertSame(array('count' => 1, 'cost' => 10.0, 'missing' => 0), $hosting['2027-02']);

        $bars = $reporting->renewalMonthsBoth(array(), array(), '2027-01-31', $hosting);
        $this->assertCount(12, $bars);
        $this->assertFalse($bars[0]['overdue']);
        $this->assertSame(0, $bars[0]['hosting']['count']);
        $this->assertSame(1, $bars[1]['hosting']['count']);

        // 15 days on, it's overdue
        $later = $reporting->hostingMonths(array($this->service('2027-01-31', 1, 31, '10.00', 1, 1, 1)), '2027-02-01',
            '2027-12-31', '2027-02-15');
        $this->assertSame(1, $later['overdue']['count']);
    }

    // The chart's query reads each service's status and auto-renew, which decide whether a passed date is overdue
    public function testTheChartsQueryReadsTheStatusAndAutoRenew()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/classes/DomainMOD/Reporting.php');
        $this->assertStringContainsString('SELECT hs.expiry_date, hs.billing_term, hs.renewal_day, hs.active, hs.autorenew,', $source);
    }

    // A service with a billing term outside the allowed list has no projected renewals, but a passed stored date is
    // still overdue (it can't wait to be confirmed without a valid term), as At-Risk and Hosting Renewals show it
    public function testAnInvalidTermIsOverdueWithNoProjections()
    {
        $months = (new Reporting())->hostingMonths(array($this->service('2026-10-10', 2, 10, '10.00')),
            '2026-11-01', '2027-10-31', '2026-11-21');
        $this->assertSame(array('overdue'), array_keys($months));
        $this->assertSame(1, $months['overdue']['count']);
    }


    /*
     * The Dashboard's count of hosting renewals to confirm comes from the same rows and rule as At-Risk Hosting Services
     * (HostingReport::services() and confirmCount(), both given the hosting date), whatever the Expiration Days setting,
     * and the Needs Attention item opens At-Risk's Confirm renewal list, saying it's about hosting renewals
     */
    public function testTheDashboardsConfirmRenewalItem()
    {
        $dashboard = file_get_contents(dirname(__DIR__, 2) . '/dashboard/index.php');
        $this->assertStringContainsString("\$confirm_count_hosting = \$hosting_available ? DomainMOD\\HostingReport::confirmCount((new DomainMOD\\HostingReport(\$pdo,
    \$_SESSION['s_user_id'] ?? 0))->services(\$hosting_today), \$hosting_today) : 0;", $dashboard);
        $this->assertStringContainsString("sprintf(ngettext('%s hosting renewal to confirm', '%s hosting renewals to confirm', \$confirm_count_hosting), number_format(\$confirm_count_hosting)),", $dashboard);
        $this->assertStringContainsString("'warning', 'bi-hourglass-split', '/reporting/hosting/at-risk.php?show=confirm#hosting-list');", $dashboard);
        $this->assertStringNotContainsString('Also counted in Hosting Services', $dashboard);
        $this->assertStringNotContainsString("_('Confirm renewal')", $dashboard);
    }

    // The dashboard gives the chart the hosting date, which decides what's overdue, while the months follow the user's
    public function testTheDashboardPassesTheHostingDate()
    {
        $dashboard = file_get_contents(dirname(__DIR__, 2) . '/dashboard/index.php');
        $this->assertStringContainsString('$hosting_today = $hosting_available ? DomainMOD\\Hosting::hostingDate($pdo) : \'\';', $dashboard);
        $this->assertStringContainsString("\$reporting->renewalHostingMonths(substr(\$today, 0, 7) . '-01',\n    DomainMOD\\Hosting::addDays(\$reporting->renewalChartEnd(\$today), -1), \$hosting_today)", $dashboard);
    }
} //@formatter:on
