<?php
/**
 * /tests/Unit/ChartColoursTest.php
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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * The colours of Domains, Hosting, and SSL Certificates on every chart of their costs: one CSS variable each per colour
 * mode in css/base.css, which js/charts.js reads, plus the Overdue label's red. Chart passes a chart's colour name only
 * when it's one of SERIES_COLOURS, the Dashboard's series and the renewal and cost reports name theirs, and an Overdue
 * bar is hatched in its own colour (with a red label) instead of being drawn in red.
 */
class ChartColoursTest extends TestCase
{
    protected function source($path)
    {
        return file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    // Each colour mode's block of chart variables in css/base.css
    protected function block($selector)
    {
        $css = $this->source('css/base.css');
        $this->assertSame(1, preg_match('/' . preg_quote($selector, '/') . ' \{([^}]*--domainmod-chart-1:[^}]*)\}/', $css, $match), $selector);
        return $match[1];
    }

    public static function colours()
    {
        return array(
            'light' => array(":root,\n[data-bs-theme=\"light\"]", array('domains' => '#3e6fb0', 'hosting' => '#2f9e72', 'ssl' => '#d4602a', 'overdue-text' => '#b02a37')),
            'dark' => array('[data-bs-theme="dark"]', array('domains' => '#5b8fd6', 'hosting' => '#2aa57a', 'ssl' => '#e0703a', 'overdue-text' => '#f0a0a0')));
    }

    #[DataProvider('colours')]
    public function testEachModeDefinesTheColours($selector, $colours)
    {
        $block = $this->block($selector);
        foreach ($colours as $name => $hex) {
            $this->assertStringContainsString('--domainmod-chart-' . $name . ': ' . $hex . ';', $block, $name);
        }
    }

    protected function chartData($html)
    {
        $this->assertSame(1, preg_match('/data-domainmod-chart="([^"]*)"/', $html, $match));
        return json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true);
    }

    public function testABarChartCarriesOnlyAKnownColour()
    {
        $chart = new Chart();
        $this->assertSame(array('domains', 'hosting', 'ssl'), Chart::SERIES_COLOURS);
        $data = $this->chartData($chart->bar('a', 'A', array('Overdue', 'May'), array(1, 2), true, '', true, 'hosting'));
        $this->assertSame('hosting', $data['colour']);
        $this->assertTrue($data['firstIsOverdue']);
        $data = $this->chartData($chart->bar('b', 'B', array('May'), array(1), false, '', true, 'red; x'));
        $this->assertArrayNotHasKey('colour', $data);
        $data = $this->chartData($chart->bar('c', 'C', array('May'), array(1)));
        $this->assertArrayNotHasKey('colour', $data);
    }

    // A cost report with more than five groups draws bars in its colour, and a doughnut doesn't need one
    public function testTheCostReportsBarFallbackCarriesTheColour()
    {
        $chart = new Chart();
        $labels = array('a', 'b', 'c', 'd', 'e', 'f');
        $data = $this->chartData($chart->doughnut('d', 'D', $labels, array(1, 2, 3, 4, 5, 6), 'ssl'));
        $this->assertSame('hbar', $data['type']);
        $this->assertSame('ssl', $data['colour']);
        $data = $this->chartData($chart->doughnut('e', 'E', array('a', 'b'), array(1, 2), 'ssl'));
        $this->assertSame('doughnut', $data['type']);
    }

    public function testTheCallersNameTheirColours()
    {
        $dashboard = $this->source('dashboard/index.php');
        foreach (array("'domains'), 'cost'), 'colour' => 'domains')", "'hosting'), 'cost'), 'colour' => 'hosting')", "'certs'), 'cost'), 'colour' => 'ssl')") as $series) {
            $this->assertStringContainsString($series, $dashboard);
        }
        $this->assertStringContainsString("'</div>', true, 'domains');", $this->source('reporting/domains/upcoming-renewals.php'));
        $this->assertStringContainsString("'</div>', true, 'hosting');", $this->source('reporting/hosting/upcoming-renewals.php'));
        $this->assertStringContainsString("'</div>', true, 'ssl');", $this->source('reporting/ssl/upcoming-renewals.php'));
        $report = $this->source('_includes/layout/cost-report.inc.php');
        $this->assertStringContainsString("\$chart_colour = \$cost_section;", $report);
        $this->assertStringContainsString("\$chart_values, false, '', true, \$chart_colour);", $report);
        $this->assertStringContainsString("\$chart_values, \$chart_colour);", $report);
    }

    // The Overdue bar is hatched in its own colour with a red label, and the legend and tooltips show the solid colours
    public function testTheOverdueBarIsHatched()
    {
        $js = $this->source('js/charts.js');
        $this->assertStringContainsString('return overdue(context) ? hatch(colour(item.colour)) : colour(item.colour);', $js);
        $this->assertStringContainsString("out[0] = data.colour ? hatch(base) : colour('2');", $js);
        $this->assertStringContainsString("return data.firstIsOverdue && context.index === 0 ? colour('overdue-text') : text;", $js);
        $this->assertStringContainsString('legend_item.fillStyle = legend_item.strokeStyle = colour(series.colour);', $js);
        $this->assertSame(2, substr_count($js, 'tooltip.callbacks.labelColor = function'));
        $this->assertStringNotContainsString('OVERDUE_SHADES', $js);
    }

    // A top legend gets 16px below it on every chart but a doughnut, and the bar totals keep their own plugin
    public function testATopLegendGetsRoomBelowIt()
    {
        $js = $this->source('js/charts.js');
        $this->assertStringContainsString("id: 'domainmodLegendGap',\n        beforeInit: function (chart) {", $js);
        $this->assertStringContainsString("if (this.options.display !== false && this.options.position === 'top') this.height += 16;", $js);
        // The legend's own fit runs first, so the gap is added to its measured height
        $this->assertNotFalse(strpos($js, 'fit.call(this);'));
        $this->assertLessThan(strpos($js, 'this.height += 16;'), strpos($js, 'fit.call(this);'));
        $this->assertStringContainsString("plugins: data.type === 'doughnut' ? [] : (data.type === 'bar' ? [barTotals, legendGap] : [legendGap])", $js);
    }

} //@formatter:on
