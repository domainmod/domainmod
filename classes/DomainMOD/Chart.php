<?php
/**
 * /classes/DomainMOD/Chart.php
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
//@formatter:off
namespace DomainMOD;

/*
 * The reports' charts. Each method returns a card holding a canvas with the chart's data as JSON in its
 * data-domainmod-chart attribute, which js/charts.js (loaded by _includes/layout/chart-footer.inc.php) draws with
 * Chart.js. An attribute is never run by the browser, so the CSP doesn't apply to it, and it comes through an htmx swap
 * (which drops script blocks). Every chart sits above a table with the same figures, which screen readers and people
 * without JavaScript use instead.
 */
class Chart
{
    public $layout;

    const MAX_SLICES = 5;  // a cost report with up to 5 groups draws a doughnut, and one with more a bar chart
    const MAX_BARS = 15;   // a cost report's bar chart shows the largest 15 groups, and the rest together as "Other"
    const MAX_HEIGHT_BARS = 30; // horizontal bar charts grow with their bars up to 30, and stay that tall above it

    public function __construct()
    {
        $this->layout = new Layout();
    }

    /*
     * A cost report's chart of each group's share, picked from the data. $labels are the groups' names (saved
     * HTML-escaped, as the tables print them) and $values their amounts, already in the user's currency. Up to 5 groups
     * draw a doughnut. More draw a horizontal bar chart, largest first, with the largest 15 and the rest together as one
     * "Other" bar. A doughnut can't show a negative amount, so when any is negative, the bar chart is drawn instead.
     * Groups with no cost are left out first (the table still lists them, and a note under the chart says so), so
     * they're not counted in the choice or in "Other", and when every group's cost is zero, no chart is drawn.
     */
    public function doughnut($id, $title, $labels, $values)
    {
        $labels = array_values($labels);
        $values = array_map('floatval', array_values($values));

        $with_cost = array_filter($values, function ($value) {
            return $value != 0;
        });
        if (!$with_cost) return '';
        $note = count($with_cost) < count($values)
            ? '<p class="small text-body-secondary mt-2 mb-0">' . _("Groups with no cost aren't shown in the chart.") . '</p>'
            : '';
        $labels = array_values(array_intersect_key($labels, $with_cost));
        $values = array_values($with_cost);

        $negative = false;
        foreach ($values as $value) {
            if ($value < 0) $negative = true;
        }

        if (!$negative && count($values) <= self::MAX_SLICES) {
            return $this->card($id, 'doughnut', $title, $labels, $values, false, false, $note);
        }

        // Largest first, then the 15 largest and the rest together as "Other"
        arsort($values);
        $has_other = count($values) > self::MAX_BARS;
        $kept = array_slice($values, 0, self::MAX_BARS, true);
        $new_labels = array();
        $new_values = array();
        foreach ($kept as $index => $value) {
            $new_labels[] = $labels[$index];
            $new_values[] = $value;
        }
        if ($has_other) {
            $new_labels[] = _('Other');
            $new_values[] = array_sum(array_slice($values, self::MAX_BARS, null, true));
        }

        return $this->card($id, 'hbar', $title, $new_labels, $new_values, false, $has_other, $note);
    }

    /*
     * A bar chart, one bar per label, such as a month. With $first_is_overdue, the first bar is drawn in the warning
     * colour, for Upcoming Renewals' Overdue bar. $after is HTML shown in the card under the chart, such as a link.
     * Without $card_title, the card has no header, for a page that puts the title in a section heading above it (the
     * title still names the chart for screen readers).
     */
    public function bar($id, $title, $labels, $values, $first_is_overdue = false, $after = '', $card_title = true)
    {
        $labels = array_values($labels);
        $values = array_map('floatval', array_values($values));
        if (!$values) return '';

        return $this->card($id, 'bar', $title, $labels, $values, $first_is_overdue, false, $after, array('card_title' => $card_title));
    }

    /*
     * A price comparison: a horizontal bar chart in the order given (the page sorts it cheapest first), every bar in the
     * palette's first colour except the bars whose indexes are in $highlight, which are drawn in green. Every
     * registrar or provider is listed, with no "Other". $values are amounts in the user's currency.
     */
    public function priceBars($id, $title, $labels, $values, $highlight = array(), $after = '')
    {
        $labels = array_values($labels);
        $values = array_map('floatval', array_values($values));
        if (!$values) return '';

        return $this->card($id, 'pricebar', $title, $labels, $values, false, false, $after,
            array('highlight' => array_values(array_map('intval', $highlight))));
    }

    /*
     * A horizontal stacked bar chart of counts, one bar per label (such as a server). $series is a list of the stacks,
     * each array('name' => ..., 'values' => one count per label, 'colour' => a palette colour name, such as '3' for
     * green or 'other' for grey). The legend shows the series names, and the amounts are whole numbers, not money.
     */
    public function stackedBars($id, $title, $labels, $series, $after = '')
    {
        $labels = array_values($labels);
        if (!$labels || !$series) return '';

        $clean = array();
        foreach ($series as $item) {
            $clean[] = array(
                'name' => html_entity_decode((string) $item['name'], ENT_QUOTES, 'UTF-8'),
                'values' => array_map('intval', array_values($item['values'])),
                'colour' => (string) $item['colour']);
        }

        return $this->card($id, 'stacked', $title, $labels, array_fill(0, count($labels), 0), false, false, $after,
            array('series' => $clean, 'format' => 'count'));
    }

    private function card($id, $type, $title, $labels, $values, $first_is_overdue, $has_other, $after = '', $extra = array())
    {
        $labels = array_map(function ($label) {
            return html_entity_decode((string) $label, ENT_QUOTES, 'UTF-8');
        }, $labels);

        $data = array(
            'type' => $type,
            'title' => $title,
            'labels' => $labels,
            'values' => $values,
            'firstIsOverdue' => (bool) $first_is_overdue,
            'hasOther' => (bool) $has_other,
            'currency' => array(
                'symbol' => html_entity_decode((string) ($_SESSION['s_default_currency_symbol'] ?? ''), ENT_QUOTES, 'UTF-8'),
                'order' => (string) ($_SESSION['s_default_currency_symbol_order'] ?? '0'),
                'space' => (string) ($_SESSION['s_default_currency_symbol_space'] ?? '0')));
        $data = array_merge($data, $extra);

        $json = json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
        $aria_label = sprintf(_('%s, chart. The table below lists the same figures.'), $title);
        $id = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');

        // A horizontal bar chart's height follows its bars, so each keeps the same thickness. A stacked chart also has a
        // legend above it, so it's never shorter than three bars' height.
        $bars = 0;
        if (in_array($type, array('hbar', 'pricebar', 'stacked'), true)) {
            $bars = min(count($values), self::MAX_HEIGHT_BARS);
            if ($type === 'stacked') $bars = max($bars, 3);
        }

        ob_start(); ?>

        <?php $card_title = !isset($extra['card_title']) || $extra['card_title']; ?>
        <div class="row<?php echo $card_title ? ' mt-4' : ''; ?>">
            <?php echo $this->layout->contentBoxTop($card_title ? htmlspecialchars($title, ENT_QUOTES, 'UTF-8') : '', '12'); ?>
            <div class="domainmod-chart<?php echo $bars ? ' domainmod-chart-bars-' . $bars : ''; ?>">
                <canvas id="<?php echo $id; ?>" data-domainmod-chart="<?php echo htmlspecialchars($json, ENT_QUOTES, 'UTF-8'); ?>" role="img" aria-label="<?php echo htmlspecialchars($aria_label, ENT_QUOTES, 'UTF-8'); ?>"></canvas>
            </div>
            <?php echo $after; ?>
            <?php echo $this->layout->contentBoxBottom(); ?>
        </div><?php

        return ob_get_clean();
    }

} //@formatter:on
