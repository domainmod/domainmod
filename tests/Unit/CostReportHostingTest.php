<?php
/**
 * /tests/Unit/CostReportHostingTest.php
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

use DomainMOD\CostReport;
use DomainMOD\Hosting;
use DomainMOD\HostingList;
use DomainMOD\Tests\Support\FakeDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * Hosting Costs (reporting/hosting/costs.php), the third section of DomainMOD\CostReport: its groupings, the yearly
 * figures (with no cost per term, since a group's services can have different terms), the IP Address grouping's None
 * row, and the Month grouping's projected renewals, worked out once per request from the hosting services rather than
 * by grouped queries. The expected amounts are worked out by hand.
 */
class CostReportHostingTest extends TestCase
{
    const KEYS = array('category', 'ip-address', 'month', 'owner', 'provider', 'type');

    // The services query's text, which FakeDatabase answers with the test's services
    const SERVICES_SQL = 'LEFT JOIN hosting_provider_accounts AS hpa';

    protected function setUp(): void
    {
        Hosting::forgetAvailability();
    }

    protected function tearDown(): void
    {
        Hosting::forgetAvailability();
    }

    // Every grouping but Month, which is projected (see the Month tests below)
    public static function groupingCases()
    {
        $cases = array();
        foreach (self::KEYS as $key) if ($key !== 'month') $cases[$key] = array($key);
        return $cases;
    }

    public function testTheGroupingsInMenuOrder()
    {
        $this->assertSame(self::KEYS, array_keys(CostReport::groupings('hosting')));
        $this->assertSame('provider', CostReport::grouping('hosting', 'nonsense')['key']);
        foreach (CostReport::groupings('hosting') as $key => $grouping) {
            $this->assertSame($key, $grouping['key']);
            $this->assertSame($grouping['export'] . '_all', $grouping['export_all']);
        }
        $this->assertSame('../../hosting/index.php', CostReport::SECTIONS['hosting']['list']);
    }

    // Every grouping but Month shows the yearly cost, and none shows a cost per term, since a group's services can have
    // different billing terms and their sum isn't the cost of any one term
    public function testEachGroupingShowsTheYearlyCostAndNoCostPerTerm()
    {
        foreach (CostReport::groupings('hosting') as $key => $grouping) {
            $values = array_column($grouping['columns'], 'value');
            $this->assertNotContains('term_cost', $values, $key);
            $this->assertNotContains(_('Cost Per Term'), array_column($grouping['columns'], 'heading'), $key);
            if ($key !== 'month') $this->assertContains('cost', $values, $key);
        }
    }

    // A service has no single domain, so there's no Domain grouping (an old link to it opens the default grouping)
    public function testThereIsNoDomainGrouping()
    {
        $this->assertArrayNotHasKey('domain', CostReport::groupings('hosting'));
        $this->assertSame('provider', CostReport::grouping('hosting', 'domain')['key']);
    }

    // The Type grouping is named for service types
    public function testTheTypeGroupingIsNamedServiceType()
    {
        $grouping = CostReport::grouping('hosting', 'type');
        $this->assertSame(_('Service Type'), $grouping['label']);
        $this->assertSame(_('Service Type'), $grouping['columns'][0]['heading']);
        $this->assertSame(_('Hosting Cost by Service Type'), $grouping['name']);
    }

    // The IP Address grouping keeps services with no IP address as one None row, linking to the list's None filter
    public function testTheIpAddressGroupingKeepsServicesWithNoIp()
    {
        $grouping = CostReport::grouping('hosting', 'ip-address');
        $this->assertSame(array('table' => 'ip_addresses', 'alias' => 'ip', 'on' => 'ip.id = hs.ip_id'), $grouping['left_join']);
        $this->assertSame(array(), $grouping['from']);
        $this->assertSame(array(), $grouping['where']);
        $count = $grouping['columns'][4];
        $this->assertSame('count', $count['value']);
        $this->assertSame(HostingList::NO_IP, $count['null_link']);
        $this->assertSame(-1, $count['null_link']);
        $this->assertSame(_('None'), $grouping['columns'][0]['null_label']);

        // The NULL row's name is None and its count opens the list's None filter
        $none = (object) array('id' => null, 'name' => null, 'ip' => null, 'rdns' => null, 'total_cost' => '12.00',
            'number_of_assets' => 2);
        $this->assertSame(_('None'), CostReport::fieldText($grouping['columns'][0], $none));
        $this->assertNull(CostReport::fieldText($grouping['columns'][1], $none));
        $this->assertSame(-1, CostReport::linkValue($count, $none));
    }

    // The no-IP fixture: the chart labels the NULL row None rather than " ()", and an IP address by name and address
    public function testTheIpAddressChartLabelsTheNoIpRowNone()
    {
        $grouping = CostReport::grouping('hosting', 'ip-address');
        $rows = array(
            (object) array('id' => null, 'name' => null, 'ip' => null, 'rdns' => null, 'total_cost' => '12.00'),
            (object) array('id' => 3, 'name' => 'Web', 'ip' => '10.0.0.1', 'rdns' => 'web.example.com', 'total_cost' => '30.00'));

        list($labels, $values) = (new CostReport(new FakeDatabase(), 1))->chartData($grouping, $rows, array(), array());

        $this->assertSame(array(_('None'), 'Web (10.0.0.1)'), $labels);
        $this->assertSame(array('12.00', '30.00'), $values);
    }

    // The SSL IP Address grouping, an inner join, still labels every row by name and address
    public function testTheSslIpAddressChartIsUnchanged()
    {
        $grouping = CostReport::grouping('ssl', 'ip-address');
        $this->assertArrayNotHasKey('null_label', $grouping['chart']);
        $rows = array((object) array('id' => 3, 'name' => 'Web', 'ip' => '10.0.0.1', 'rdns' => '', 'total_cost' => '5'));
        list($labels) = (new CostReport(new FakeDatabase(), 1))->chartData($grouping, $rows, array(), array());
        $this->assertSame(array('Web (10.0.0.1)'), $labels);
    }

    // The counts link to the Hosting list with its own filter names
    public function testTheCountsLinkToTheListsFilters()
    {
        $links = array();
        foreach (CostReport::groupings('hosting') as $key => $grouping) {
            foreach ($grouping['columns'] as $column) {
                if (isset($column['link'])) $links[] = $column['link'][0];
            }
        }
        sort($links);
        $this->assertSame(array('hcid', 'hid', 'hipid', 'hpaid', 'htid', 'oid'), $links);
        foreach ($links as $link) {
            $this->assertArrayHasKey($link, HostingList::ID_FILTERS, $link);
        }
    }

    // Every query sums the yearly figure, leaves out Cancelled services and invalid terms, and binds the user and dates
    #[DataProvider('groupingCases')]
    public function testEveryQueryUsesTheYearlyFigure($key)
    {
        $grouping = CostReport::grouping('hosting', $key);
        $pdo = new FakeDatabase();
        $report = new CostReport($pdo, '7');

        $report->rows('hosting', $grouping, '2026-01-01', '2026-12-31');
        $report->totals('hosting', $grouping, '2026-01-01', '2026-12-31');
        $report->chartRows('hosting', $grouping, '2026-01-01', '2026-12-31');
        $report->subtotals('hosting', $grouping, '2026-01-01', '2026-12-31');

        $yearly = Hosting::yearlyCostSql('hs', 'hs.total_cost * cc.conversion');
        $this->assertNotEmpty($pdo->queries);
        foreach ($pdo->queries as $index => $sql) {
            $this->assertStringContainsString('FROM hosting_services AS hs', $sql, 'query ' . $index);
            $this->assertStringContainsString('hosting_fees AS f', $sql);
            $this->assertStringContainsString('SUM(' . $yearly . ')', $sql);
            $this->assertStringContainsString("hs.active NOT IN ('0')", $sql);
            $this->assertStringContainsString('hs.billing_term IN (1, 3, 6, 12, 24, 36)', $sql);
            $this->assertStringContainsString('hs.expiry_date BETWEEN :start_date AND :end_date', $sql);
            $this->assertStringContainsString('cc.user_id = :user_id', $sql);
            $this->assertStringNotContainsString('2026-', $sql);
            foreach (array_merge($grouping['from'], $grouping['where']) as $part) {
                $this->assertStringContainsString($part, $sql, 'query ' . $index);
            }
        }
        // No query adds up costs per term
        foreach ($pdo->queries as $sql) $this->assertStringNotContainsString('term_cost', $sql);
        // The IP Address grouping's LEFT JOIN is written straight after the services table, and IP 0 isn't excluded
        if ($key === 'ip-address') {
            $this->assertStringContainsString('FROM hosting_services AS hs LEFT JOIN ip_addresses AS ip ON ip.id = hs.ip_id', $pdo->queries[0]);
            foreach ($pdo->queries as $sql) $this->assertStringNotContainsString('hs.ip_id = ip.id', $sql);
        }
    }

    // The other sections' costs are unchanged
    public function testTheOtherSectionsKeepTheirCost()
    {
        $pdo = new FakeDatabase();
        (new CostReport($pdo, 1))->totals('ssl', CostReport::grouping('ssl', 'type'), '', '');

        $this->assertStringContainsString('SUM(sslc.total_cost * cc.conversion) AS grand_total', $pdo->queries[0]);
        $this->assertStringNotContainsString('billing_term', $pdo->queries[0]);
    }

    // A services() row with a fee and a rate
    private function service($id, $date, $term, $day, $cost, $extra = array())
    {
        return (object) array_merge(array('id' => $id, 'name' => 'service-' . $id, 'expiry_date' => $date,
            'billing_term' => (string) $term, 'renewal_day' => (string) $day, 'autorenew' => '1', 'active' => '1',
            'provider_name' => 'Provider', 'owner_name' => 'Owner', 'username' => 'user', 'type' => 'Shared',
            'has_fee' => 1, 'has_rate' => 1, 'converted_cost' => $cost), $extra);
    }

    private function monthReport($services)
    {
        $pdo = new FakeDatabase(array('SELECT db_version' => '4.27.0', self::SERVICES_SQL => $services));
        return array($pdo, new CostReport($pdo, 1));
    }

    /*
     * The Month grouping adds up projected renewals: a $10 monthly service from January 15, a $100 yearly service in
     * March, a Pending (Renewal) $30 quarterly service from February 1, and a monthly service with no fee (left out).
     * Over January to June: 6 x 10 + 100 + 2 x 30 = 220, with three services counted.
     */
    public function testTheMonthGroupingAddsUpTheProjectedRenewals()
    {
        $services = array(
            $this->service(1, '2026-01-15', 1, 15, 10.0),
            $this->service(2, '2026-03-20', 12, 20, 100.0),
            $this->service(3, '2026-02-01', 3, 1, 30.0, array('active' => (string) Hosting::PENDING_RENEWAL)),
            $this->service(4, '2026-01-10', 1, 10, 5.0, array('has_fee' => 0)),
        );
        list($pdo, $report) = $this->monthReport($services);
        $grouping = CostReport::grouping('hosting', 'month');

        $rows = $report->rows('hosting', $grouping, '2026-01-01', '2026-06-30');
        $totals = $report->totals('hosting', $grouping, '2026-01-01', '2026-06-30');
        $chart_rows = $report->chartRows('hosting', $grouping, '2026-01-01', '2026-06-30');
        $subtotals = $report->subtotals('hosting', $grouping, '2026-01-01', '2026-06-30');

        $this->assertSame(array(1, 2, 3, 4, 5, 6), array_map(function ($row) { return $row->month; }, $rows));
        $this->assertSame(220.0, $totals->grand_total);
        $this->assertSame(3, $totals->number_total);
        $this->assertSame(array(), $chart_rows);
        $this->assertSame(array('2026-1' => 10.0, '2026-2' => 40.0, '2026-3' => 110.0, '2026-4' => 10.0,
            '2026-5' => 40.0, '2026-6' => 10.0), $subtotals['month']);
        $this->assertSame(array(2026 => 220.0), $subtotals['year']);

        // The chart's bars are the same months and amounts
        list($labels, $values) = $report->chartData($grouping, $rows, $chart_rows, $subtotals);
        $this->assertCount(6, $labels);
        $this->assertSame(array(10.0, 40.0, 110.0, 10.0, 40.0, 10.0), $values);

        // The services are read once for the whole request, never filtered by the range's start
        $this->assertCount(1, $pdo->queriesWith(self::SERVICES_SQL));
        $this->assertCount(0, $pdo->queriesWith('GROUP BY'));
    }

    // Two years in the range get a subtotal each, and a 36-month service outside the range adds nothing
    public function testTheMonthGroupingSubtotalsEachYear()
    {
        $services = array(
            $this->service(1, '2026-12-01', 1, 1, 10.0),
            $this->service(2, '2030-01-01', 36, 1, 360.0),
        );
        list(, $report) = $this->monthReport($services);
        $grouping = CostReport::grouping('hosting', 'month');

        $subtotals = $report->subtotals('hosting', $grouping, '2026-11-01', '2027-10-31');
        $this->assertSame(array(2026 => 10.0, 2027 => 100.0), $subtotals['year']);
        $this->assertSame(110.0, $report->totals('hosting', $grouping, '2026-11-01', '2027-10-31')->grand_total);
    }

    public function testTheMonthGroupingIsEmptyWithNoServices()
    {
        list(, $report) = $this->monthReport(array());
        $grouping = CostReport::grouping('hosting', 'month');

        $this->assertSame(array(), $report->rows('hosting', $grouping, '2026-01-01', '2026-12-31'));
        $this->assertSame(0, $report->totals('hosting', $grouping, '2026-01-01', '2026-12-31')->number_total);
    }
}
