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
 * The reports' charts. Each method returns a card holding a canvas and the chart's data as a JSON block, which
 * js/charts.js (loaded by _includes/layout/chart-footer.inc.php) draws with Chart.js. The data block is never run by
 * the browser, so the CSP doesn't apply to it. Every chart sits above a table with the same figures, which screen
 * readers and people without JavaScript use instead.
 */
class Chart
{
    public $layout;

    const MAX_SLICES = 5;  // a cost report with up to 5 groups draws a doughnut, and one with more a bar chart
    const MAX_BARS = 15;   // a cost report's bar chart shows the largest 15 groups, and the rest together as "Other"

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
     */
    public function bar($id, $title, $labels, $values, $first_is_overdue = false, $after = '')
    {
        $labels = array_values($labels);
        $values = array_map('floatval', array_values($values));
        if (!$values) return '';

        return $this->card($id, 'bar', $title, $labels, $values, $first_is_overdue, false, $after);
    }

    private function card($id, $type, $title, $labels, $values, $first_is_overdue, $has_other, $after = '')
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

        $json = json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
        $aria_label = sprintf(_('%s, chart. The table below lists the same figures.'), $title);
        $id = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');

        ob_start(); ?>

        <div class="row mt-4">
            <?php echo $this->layout->contentBoxTop(htmlspecialchars($title, ENT_QUOTES, 'UTF-8'), '12'); ?>
            <div class="domainmod-chart<?php echo $type === 'hbar' ? ' domainmod-chart-bars-' . count($values) : ''; ?>">
                <canvas id="<?php echo $id; ?>" data-domainmod-chart role="img" aria-label="<?php echo htmlspecialchars($aria_label, ENT_QUOTES, 'UTF-8'); ?>"></canvas>
            </div>
            <script type="application/json" id="<?php echo $id; ?>-data" nonce="<?php echo CURRENT_NONCE; ?>"><?php echo $json; ?></script>
            <?php echo $after; ?>
            <?php echo $this->layout->contentBoxBottom(); ?>
        </div><?php

        return ob_get_clean();
    }

} //@formatter:on
