<?php
/**
 * /tests/Unit/CostReportTest.php
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
use DomainMOD\SslList;
use DomainMOD\Tests\Support\FakeDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// The Domain Costs and SSL Costs reports' groupings, queries, and the old reports' redirects (DomainMOD\CostReport)
class CostReportTest extends TestCase
{
    // The old reports' file names (reporting/<section>/cost-by-<key>.php), which are the groupings' keys
    const OLD_KEYS = array(
        'domains' => array('category', 'dns', 'host', 'ip-address', 'month', 'owner', 'registrar', 'tld'),
        'ssl' => array('category', 'domain', 'ip-address', 'month', 'owner', 'provider', 'type'),
    );

    public static function groupingCases()
    {
        $cases = array();
        foreach (self::OLD_KEYS as $section => $keys) {
            foreach ($keys as $key) {
                $cases[$section . ' ' . $key] = array($section, $key);
            }
        }
        return $cases;
    }

    public function testTheGroupingsAreTheOldReports()
    {
        foreach (self::OLD_KEYS as $section => $keys) {
            $groupings = CostReport::groupings($section);
            $found = array_keys($groupings);
            sort($found);
            $this->assertSame($keys, $found, $section);
            foreach ($groupings as $key => $grouping) {
                $this->assertSame($key, $grouping['key']);
                foreach (array('label', 'title', 'name', 'export', 'export_all') as $field) {
                    $this->assertIsString($grouping[$field], $section . ' ' . $key . ' ' . $field);
                    $this->assertNotSame('', $grouping[$field], $section . ' ' . $key . ' ' . $field);
                }
                $this->assertSame($grouping['export'] . '_all', $grouping['export_all']);
                $this->assertNotEmpty($grouping['columns'], $section . ' ' . $key);
            }
        }
    }

    // The Group By menu lists them in the Reporting page's order (by label, Web Host last)
    public function testTheMenuOrderIsTheReportingPagesOrder()
    {
        $this->assertSame(array('category', 'dns', 'ip-address', 'month', 'owner', 'registrar', 'tld', 'host'),
            array_keys(CostReport::groupings('domains')));
        $this->assertSame(array('category', 'domain', 'ip-address', 'month', 'owner', 'provider', 'type'),
            array_keys(CostReport::groupings('ssl')));
    }

    public function testAnUnknownGroupingOpensTheDefault()
    {
        foreach (array('nonsense', '', null, array('month'), 7, 'MONTH', ' month') as $key) {
            $this->assertSame('registrar', CostReport::grouping('domains', $key)['key'], var_export($key, true));
            $this->assertSame('provider', CostReport::grouping('ssl', $key)['key'], var_export($key, true));
        }
        $this->assertSame('month', CostReport::grouping('ssl', 'month')['key']);
        $this->assertSame('tld', CostReport::grouping('domains', 'tld')['key']);
        // A key from the other section isn't one of this section's
        $this->assertSame('provider', CostReport::grouping('ssl', 'tld')['key']);
    }

    // Each query keeps the grouping's own tables (so the totals match the old report's, which left out assets whose
    // lookup row is missing), the user's rates, and the dates
    #[DataProvider('groupingCases')]
    public function testEveryQueryHasTheGroupingsTablesTheUserAndTheDates($section, $key)
    {
        $grouping = CostReport::grouping($section, $key);
        $pdo = new FakeDatabase();
        $report = new CostReport($pdo, '7');

        $report->rows($section, $grouping, '2026-01-01', '2026-12-31');
        $report->totals($section, $grouping, '2026-01-01', '2026-12-31');
        $report->chartRows($section, $grouping, '2026-01-01', '2026-12-31');
        $report->subtotals($section, $grouping, '2026-01-01', '2026-12-31');

        $alias = $section === 'ssl' ? 'sslc' : 'd';
        $this->assertNotEmpty($pdo->queries);
        foreach ($pdo->queries as $index => $sql) {
            foreach (array_merge($grouping['from'], $grouping['where']) as $part) {
                $this->assertStringContainsString($part, $sql, 'query ' . $index);
            }
            if (isset($grouping['left_join'])) {
                $join = $grouping['left_join'];
                $this->assertStringContainsString('FROM ' . CostReport::SECTIONS[$section]['table'] . ' AS ' . $alias
                    . ' LEFT JOIN ' . $join['table'] . ' AS ' . $join['alias'] . ' ON ' . $join['on'] . ', ', $sql);
            } else {
                $this->assertStringNotContainsString('LEFT JOIN', $sql);
            }
            $this->assertStringContainsString($alias . '.expiry_date BETWEEN :start_date AND :end_date', $sql);
            $this->assertStringContainsString($alias . '.active NOT IN (' . ($section === 'ssl' ? "'0'" : "'0', '10'") . ')', $sql);
            $this->assertStringNotContainsString('2026-', $sql);
            // Only Month's list of months leaves out the rates (as the old report did)
            if ($key === 'month' && $index === 0) {
                $this->assertStringNotContainsString('currency_conversions', $sql);
                $this->assertStringNotContainsString(':user_id', $sql);
            } else {
                $this->assertStringContainsString('currency_conversions AS cc', $sql);
                $this->assertStringContainsString('cc.user_id = :user_id', $sql);
            }
        }
    }

    /*
     * SSL Cost by Domain keeps certificates with no domain, as one NULL row, so its total matches every other
     * grouping's. Its label is None and its count links to the list's None filter (did=-1). No other grouping, in
     * either section, has a LEFT JOIN.
     */
    public function testSslCostByDomainKeepsCertificatesWithNoDomain()
    {
        $grouping = CostReport::grouping('ssl', 'domain');
        $report = new CostReport(new FakeDatabase(), 1);
        list($sql) = $report->sql('ssl', $grouping, $grouping['select'], $grouping['group_by'], $grouping['order_by'], '', '');

        $this->assertStringContainsString('FROM ssl_certs AS sslc LEFT JOIN domains AS d ON d.id = sslc.domain_id, ssl_fees AS f', $sql);
        $this->assertStringNotContainsString('sslc.domain_id = d.id', $sql);
        $this->assertStringNotContainsString('domains AS d,', $sql);
        $this->assertSame(1, substr_count($sql, 'domains AS d'));

        $none = (object) array('id' => null, 'domain' => null, 'total_cost' => '4.00', 'number_of_assets' => 2);
        $linked = (object) array('id' => 3, 'domain' => 'example.com', 'total_cost' => '6.00', 'number_of_assets' => 1);
        $field = $grouping['columns'][0];
        $count = $grouping['columns'][2];
        $this->assertSame('None', CostReport::fieldText($field, $none));
        $this->assertSame('example.com', CostReport::fieldText($field, $linked));
        $this->assertSame(array('did', 'id'), $count['link']);
        $this->assertSame(-1, CostReport::linkValue($count, $none));
        $this->assertSame(SslList::NO_DOMAIN, CostReport::linkValue($count, $none));
        $this->assertSame(3, CostReport::linkValue($count, $linked));
        $this->assertSame(array(array('None', 'example.com'), array('4.00', '6.00')),
            $report->chartData($grouping, array($none, $linked), array(), array()));

        foreach (array('domains', 'ssl') as $section) {
            foreach (CostReport::groupings($section) as $key => $other) {
                if ($section === 'ssl' && $key === 'domain') continue;
                $this->assertArrayNotHasKey('left_join', $other, $section . ' ' . $key);
                list($other_sql) = $report->sql($section, $other, 'count(*)', '', '', '', '');
                $this->assertStringNotContainsString('LEFT JOIN', $other_sql, $section . ' ' . $key);
            }
        }

        // A NULL field without a null_label stays NULL, and a row's own link value is used as it is
        $this->assertNull(CostReport::fieldText(array('field' => 'name'), (object) array('name' => null)));
        $this->assertNull(CostReport::linkValue(array('link' => array('oid', 'id')), (object) array('id' => null)));
    }

    public function testThereIsNoDateConditionWithoutBothDates()
    {
        $grouping = CostReport::grouping('domains', 'category');
        $report = new CostReport(new FakeDatabase(), 1);

        foreach (array(array('', ''), array('2026-01-01', ''), array('', '2026-12-31')) as $dates) {
            list($sql, $params) = $report->sql('domains', $grouping, 'count(*)', '', '', $dates[0], $dates[1]);
            $this->assertStringNotContainsString('expiry_date', $sql);
            $this->assertSame(array('user_id' => 1), $params);
        }

        list($sql, $params) = $report->sql('domains', $grouping, 'count(*)', '', '', '2026-01-01', '2026-12-31');
        $this->assertSame(array('user_id' => 1, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31'), $params);
    }

    public function testTheUserIsBoundAsANumber()
    {
        $report = new CostReport(new FakeDatabase(), "3' OR '1'='1");
        list($sql, $params) = $report->sql('ssl', CostReport::grouping('ssl', 'type'), 'count(*)', '', '', '', '');
        $this->assertSame(3, $params['user_id']);
        $this->assertStringNotContainsString("'1'='1", $sql);
    }

    public function testMonthSubtotalsAreKeyedByYearAndMonthAndByYear()
    {
        $pdo = new FakeDatabase(array(
            'MONTH(d.expiry_date) AS month, SUM' => array((object) array('year' => 2026, 'month' => 3, 'cost' => '12.50'),
                (object) array('year' => 2027, 'month' => 11, 'cost' => '4.00')),
            'YEAR(d.expiry_date) AS year, SUM' => array((object) array('year' => 2026, 'cost' => '20.00')),
        ));
        $report = new CostReport($pdo, 1);

        $subtotals = $report->subtotals('domains', CostReport::grouping('domains', 'month'), '', '');

        $this->assertSame(array('month' => array('2026-3' => '12.50', '2027-11' => '4.00'), 'year' => array(2026 => '20.00')),
            $subtotals);
        // Two grouped queries in place of two per row
        $this->assertCount(2, $pdo->queries);
        $this->assertCount(1, $pdo->queriesWith('GROUP BY year, month'));
    }

    public function testRegistrarAndProviderSubtotalsAreKeyedByTheirId()
    {
        $pdo = new FakeDatabase(array(
            'r.id AS id' => array((object) array('id' => 4, 'total_cost' => '30.00', 'number_of_assets' => 3)),
            'sslp.id AS id' => array((object) array('id' => 9, 'total_cost' => '5.00', 'number_of_assets' => 1)),
        ));
        $report = new CostReport($pdo, 1);

        $this->assertSame(array(4), array_keys($report->subtotals('domains', CostReport::grouping('domains', 'registrar'), '', '')['group']));
        $this->assertSame(array(9), array_keys($report->subtotals('ssl', CostReport::grouping('ssl', 'provider'), '', '')['group']));
        $this->assertCount(1, $pdo->queriesWith('GROUP BY r.id'));
        $this->assertCount(1, $pdo->queriesWith('GROUP BY sslp.id'));
        $this->assertSame(array(), $report->subtotals('domains', CostReport::grouping('domains', 'owner'), '', ''));
    }

    public function testTheChartLabels()
    {
        $report = new CostReport(new FakeDatabase(), 1);
        $row = function ($fields) {
            return (object) $fields;
        };

        $this->assertSame(array(array('.com', '.co.uk'), array('10.00', '2.50')), $report->chartData(
            CostReport::grouping('domains', 'tld'), array($row(array('tld' => 'com', 'total_cost' => '10.00')),
                $row(array('tld' => 'co.uk', 'total_cost' => '2.50'))), array(), array()));

        $this->assertSame(array(array('Web (10.0.0.1)'), array('3.00')), $report->chartData(
            CostReport::grouping('ssl', 'ip-address'),
            array($row(array('name' => 'Web', 'ip' => '10.0.0.1', 'total_cost' => '3.00'))), array(), array()));

        // Month: each listed month, with 0 for one that has no cost (such as one whose only currency has no rate)
        $this->assertSame(array(array('March 2026', 'April 2026'), array('7.00', 0)), $report->chartData(
            CostReport::grouping('domains', 'month'),
            array($row(array('year' => 2026, 'month' => 3)), $row(array('year' => 2026, 'month' => 4))), array(),
            array('month' => array('2026-3' => '7.00'))));

        // Registrar: its own chart rows, not the grouped rows
        $this->assertSame(array(array('Alpha'), array('9.00')), $report->chartData(
            CostReport::grouping('domains', 'registrar'), array($row(array('registrar_name' => 'Ignored'))),
            array($row(array('registrar_name' => 'Alpha', 'total_cost' => '9.00'))), array()));
    }

    /*
     * Every grouping with a record behind it groups by the record's id, not its name, and orders same-named records by
     * id, so two with the same name (such as two owners called Greg) get a row each, linking to its own record. TLD
     * and Month have no record behind them.
     */
    #[DataProvider('groupingCases')]
    public function testGroupingsGroupByIdNotName($section, $key)
    {
        $grouping = CostReport::grouping($section, $key);
        if ($key === 'tld' || $key === 'month') {
            $this->assertArrayNotHasKey('select', $grouping['chart']);
            return;
        }

        $id = explode(',', $grouping['select'])[0];
        $this->assertMatchesRegularExpression('/^[a-z]+\.id$/', $id);
        $this->assertStringStartsWith($id . ',', $grouping['group_by']);
        $this->assertStringContainsString($id, $grouping['order_by']);

        // Every column the rows show is grouped on (not left to the server to pick from a merged group)
        foreach (explode(',', $grouping['select']) as $column) {
            $this->assertStringContainsString(trim(explode(' AS ', $column)[0]), $grouping['group_by']);
        }
    }

    public function testRegistrarAndProviderRowsGroupByEachRecordsId()
    {
        $this->assertSame('r.id, r.name, o.id, o.name, ra.id, ra.username',
            CostReport::grouping('domains', 'registrar')['group_by']);
        $this->assertSame('r.name, r.id, o.name, o.id, ra.username, ra.id',
            CostReport::grouping('domains', 'registrar')['order_by']);
        $this->assertSame('sslp.id, sslp.name, o.id, o.name, sslpa.id, sslpa.username',
            CostReport::grouping('ssl', 'provider')['group_by']);
        $this->assertSame('sslp.name, sslp.id, o.name, o.id, sslpa.username, sslpa.id',
            CostReport::grouping('ssl', 'provider')['order_by']);
    }

    // Two registrars with the same name are separate groups in the table, each showing its own cells. Month's group is
    // its year, and other groupings have no groups.
    public function testTheGroupKeyKeepsSameNamedRegistrarsApart()
    {
        $registrar = CostReport::grouping('domains', 'registrar');
        $first = (object) array('id' => 4, 'registrar_name' => 'Same');
        $second = (object) array('id' => 7, 'registrar_name' => 'Same');

        $this->assertSame(4, CostReport::groupKey($registrar, $first));
        $this->assertSame(7, CostReport::groupKey($registrar, $second));
        $this->assertSame(9, CostReport::groupKey(CostReport::grouping('ssl', 'provider'),
            (object) array('id' => 9, 'provider_name' => 'Same')));
        $this->assertSame(2026, CostReport::groupKey(CostReport::grouping('ssl', 'month'),
            (object) array('year' => 2026, 'month' => 3)));
        $this->assertNull(CostReport::groupKey(CostReport::grouping('domains', 'owner'), $first));
    }

    public function testExportIsRequestedOnlyByExactlyOne()
    {
        $this->assertTrue(CostReport::exportRequested(array('export_data' => '1')));
        // As the old pages' (int) read it
        $this->assertTrue(CostReport::exportRequested(array('export_data' => '01')));
        foreach (array(array(), array('export_data' => ''), array('export_data' => '0'),
                     array('export_data' => '2'), array('export_data' => array('1')), array('export_data' => array('0')),
                     array('export_data' => 1)) as $get) {
            $this->assertFalse(CostReport::exportRequested($get), var_export($get, true));
        }
    }

    #[DataProvider('groupingCases')]
    public function testEachOldAddressLeadsToItsGrouping($section, $key)
    {
        $this->assertSame('costs.php?group=' . $key, CostReport::redirectUrl($section, $key, array(), array()));
        $this->assertSame('costs.php?group=' . $key . '&daterange=2026-01-01+-+2026-12-31',
            CostReport::redirectUrl($section, $key, array(), array('daterange' => '2026-01-01 - 2026-12-31')));
        $this->assertSame('costs.php?group=' . $key . '&daterange=2026-01-01+-+2026-12-31&export_data=1',
            CostReport::redirectUrl($section, $key, array(),
                array('daterange' => '2026-01-01 - 2026-12-31', 'export_data' => '1')));
    }

    public function testRedirectsCarryOnlyTheRangeAndTheExport()
    {
        // Nothing else from the request is copied
        $this->assertSame('costs.php?group=tld', CostReport::redirectUrl('domains', 'tld', array('group' => 'month',
            'csrf_token' => 'x'), array('group' => 'month', 'foo' => 'bar', 'export_data' => '0')));
        // A POSTed range comes first, as in $_REQUEST
        $this->assertSame('costs.php?group=type&daterange=2025-01-01+-+2025-12-31', CostReport::redirectUrl('ssl',
            'type', array('daterange' => '2025-01-01 - 2025-12-31'), array('daterange' => '2026-01-01 - 2026-12-31')));
        // A POSTed export request doesn't export, and arrays are left out
        $this->assertSame('costs.php?group=owner', CostReport::redirectUrl('ssl', 'owner',
            array('export_data' => '1', 'daterange' => array('x')), array('export_data' => array('1'))));
        // A malformed range is passed on as it is, and the costs page checks it
        $this->assertSame('costs.php?group=month&daterange=bad%26%3Cx%3E', CostReport::redirectUrl('domains', 'month',
            array(), array('daterange' => 'bad&<x>')));
    }

} //@formatter:on
