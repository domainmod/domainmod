<?php
/**
 * /tests/Unit/RenewalChartTest.php
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

use DomainMOD\Chart;
use DomainMOD\Database;
use DomainMOD\Reporting;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\TestCase;

/*
 * The Dashboard's renewal chart with domains and SSL certificates stacked: Reporting::renewalMonthsBoth() lines up the
 * two sets of bars, renewalMonthsBothTable() lists the figures, and Chart::stackedMonthBars() hands js/charts.js each
 * series and the bars' totals. The single-series bar() is checked too, so its callers are unchanged. The table's
 * Currency connects to the database when it's built, so a stand-in connection is set, and none of these use it.
 */
class RenewalChartTest extends TestCase
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

    private function chartData($html)
    {
        $this->assertSame(1, preg_match('/data-domainmod-chart="([^"]*)"/', $html, $match));
        return json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true);
    }

    public function testBothSetsLineUpMonthByMonthWithTotals()
    {
        $reporting = new Reporting();
        $domains = array($this->row('2026-11-20', '10.00'), $this->row('2027-01-05', '15.50'));
        $certs = array($this->row('2026-11-30', '99.00'), $this->row('2027-10-31', '5.00'));

        $months = $reporting->renewalMonthsBoth($domains, $certs, '2026-11-16');

        $this->assertCount(12, $months);
        $this->assertSame('November 2026', $months[0]['label']);
        $this->assertFalse($months[0]['overdue']);
        $this->assertEqualsWithDelta(10.0, $months[0]['domains']['cost'], 0.001);
        $this->assertEqualsWithDelta(99.0, $months[0]['certs']['cost'], 0.001);
        $this->assertEqualsWithDelta(109.0, $months[0]['cost'], 0.001);
        $this->assertEqualsWithDelta(15.5, $months[2]['cost'], 0.001);
        $this->assertSame('October 2027', $months[11]['label']);
        $this->assertEqualsWithDelta(5.0, $months[11]['certs']['cost'], 0.001);
        $this->assertSame(1, $months[11]['certs']['count']);
        $this->assertSame(0, $months[11]['domains']['count']);
    }

    public function testOverdueCertificatesAloneStillAddTheOverdueBar()
    {
        $reporting = new Reporting();
        $domains = array($this->row('2026-12-01', '10.00'));
        $certs = array($this->row('2026-10-01', '50.00'), $this->row('2026-09-01', '1.00', 0, 0));

        $months = $reporting->renewalMonthsBoth($domains, $certs, '2026-11-16');

        $this->assertCount(13, $months);
        $this->assertSame('Overdue', $months[0]['label']);
        $this->assertTrue($months[0]['overdue']);
        $this->assertSame(0, $months[0]['domains']['count']);
        $this->assertSame(2, $months[0]['certs']['count']);
        $this->assertSame(1, $months[0]['certs']['missing']);
        $this->assertEqualsWithDelta(50.0, $months[0]['cost'], 0.001);
        $this->assertSame('November 2026', $months[1]['label']);
        $this->assertEqualsWithDelta(10.0, $months[2]['domains']['cost'], 0.001);
    }

    public function testOverdueDomainsAloneStillAddTheOverdueBar()
    {
        $reporting = new Reporting();
        $months = $reporting->renewalMonthsBoth(array($this->row('2026-11-01', '8.00')), array(), '2026-11-16');

        $this->assertCount(13, $months);
        $this->assertTrue($months[0]['overdue']);
        $this->assertSame(1, $months[0]['domains']['count']);
        $this->assertSame(0, $months[0]['certs']['count']);
        $this->assertSame('Overdue', $months[0]['certs']['label']);
    }

    public function testNoOverdueBarWhenNeitherHasOverdueItems()
    {
        $reporting = new Reporting();
        $months = $reporting->renewalMonthsBoth(array(), array($this->row('2026-11-16', '3.00')), '2026-11-16');

        $this->assertCount(12, $months);
        $this->assertFalse($months[0]['overdue']);
        $this->assertEqualsWithDelta(3.0, $months[0]['certs']['cost'], 0.001);
    }

    public function testTheFiguresTableHasEachColumnAndTheMissingNotes()
    {
        $reporting = new Reporting();
        $months = $reporting->renewalMonthsBoth(array($this->row('2026-11-20', '1234.5'), $this->row('2026-11-21', null, 0, 0)),
            array($this->row('2026-11-25', '10'), $this->row('2026-11-26', null, 1, 0), $this->row('2026-11-27', null, 0, 1)), '2026-11-16');

        $html = $reporting->renewalMonthsBothTable($months);

        $this->assertStringContainsString('<th scope="col">Month</th>', $html);
        $this->assertStringContainsString('<th scope="col" class="text-end">Domains</th>', $html);
        $this->assertStringContainsString('<th scope="col" class="text-end">SSL Certificates</th>', $html);
        $this->assertStringContainsString('<th scope="col" class="text-end">Total Cost</th>', $html);
        $this->assertStringContainsString('<th scope="row" class="fw-normal">November 2026</th>', $html);
        $this->assertStringContainsString('$1,234.50', $html);
        $this->assertStringContainsString('$10.00', $html);
        $this->assertStringContainsString('$1,244.50', $html);
        $this->assertStringContainsString('1 domain has no fee or rate', $html);
        $this->assertStringContainsString('2 SSL certificates have no fee or rate', $html);
        $this->assertSame(12, substr_count($html, '<th scope="row"'));
    }

    public function testStackedMonthBarsSendsEachSeriesAndTheTotals()
    {
        $chart = new Chart();
        $html = $chart->stackedMonthBars('test-chart', 'Renewal Costs', array('Overdue', 'May &amp; June'),
            array(
                array('name' => 'Domains', 'values' => array('10.5', 2), 'colour' => '1'),
                array('name' => 'SSL &amp; More', 'values' => array(1, '0.25'), 'colour' => '4')),
            true, '<p>after</p>', false);

        $data = $this->chartData($html);
        $this->assertSame('bar', $data['type']);
        $this->assertSame(array('Overdue', 'May & June'), $data['labels']);
        $this->assertEqualsWithDelta(11.5, $data['values'][0], 0.001);
        $this->assertEqualsWithDelta(2.25, $data['values'][1], 0.001);
        $this->assertTrue($data['firstIsOverdue']);
        $this->assertSame('Total Cost', $data['totalLabel']);
        $this->assertCount(2, $data['series']);
        $this->assertSame('Domains', $data['series'][0]['name']);
        $this->assertSame('1', $data['series'][0]['colour']);
        $this->assertSame('SSL & More', $data['series'][1]['name']);
        $this->assertEqualsWithDelta(0.25, $data['series'][1]['values'][1], 0.001);
        $this->assertStringContainsString('<p>after</p>', $html);
        $this->assertStringContainsString('id="test-chart"', $html);
        $this->assertStringContainsString('aria-label="Renewal Costs, chart. The table below lists the same figures."', $html);
        $this->assertStringNotContainsString(' mt-4', $html);
    }

    public function testStackedMonthBarsDrawsNothingWithoutBarsOrSeries()
    {
        $chart = new Chart();
        $this->assertSame('', $chart->stackedMonthBars('a', 'A', array(), array(array('name' => 'X', 'values' => array(), 'colour' => '1'))));
        $this->assertSame('', $chart->stackedMonthBars('a', 'A', array('May'), array()));
    }

    public function testThePlainBarChartHasNoSeries()
    {
        $chart = new Chart();
        $data = $this->chartData($chart->bar('plain', 'Plain', array('May', 'June'), array('1', '2.5')));

        $this->assertSame('bar', $data['type']);
        $this->assertEquals(array(1, 2.5), $data['values']);
        $this->assertArrayNotHasKey('series', $data);
        $this->assertArrayNotHasKey('totalLabel', $data);
        $this->assertFalse($data['firstIsOverdue']);
    }
}
