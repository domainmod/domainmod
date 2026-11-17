<?php
/**
 * /_includes/layout/cost-report.inc.php
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
/*
 * The Domain Costs and SSL Costs reports (reporting/domains/costs.php and reporting/ssl/costs.php), after the page has
 * set $cost_section, $cost_grouping (DomainMOD\CostReport::grouping()), and its settings: the export, or the page with
 * its Group By and date filter, summary, chart, and table. Every view is an address (the filter is a GET form), and
 * the request's values are read only when they're text, so an array can't stop the page.
 */
$system->authCheck();
// Rates for every currency in use, or the lists and reports below would leave out what uses a missing one
(new DomainMOD\Conversion())->ensureRates($_SESSION['s_user_id'] ?? 0, $_SESSION['s_default_currency'] ?? '');

$cost_report = new DomainMOD\CostReport($deeb->cnxx, $_SESSION['s_user_id'] ?? 0);
$cost_key = $cost_grouping['key'];

$export_data = DomainMOD\CostReport::exportRequested($_GET);
$daterange = is_string($_GET['daterange'] ?? null) ? $sanitize->text($_GET['daterange']) : '';

list($new_start_date, $new_end_date) = $date->splitAndCheckRange($daterange);

$submission_failed = 0;
if ($new_start_date != '' && $new_start_date > $new_end_date) {

    $_SESSION['s_message_danger'] .= _('The end date is before the start date.') . '<BR>';
    $submission_failed = 1;

}

$cost_rows = array();
$cost_chart_rows = array();
$cost_subtotals = array();
$grand_total = null;
$number_total = 0;
if ($submission_failed != 1) {

    $cost_rows = $cost_report->rows($cost_section, $cost_grouping, $new_start_date, $new_end_date);
    $cost_totals = $cost_report->totals($cost_section, $cost_grouping, $new_start_date, $new_end_date);
    $grand_total = $cost_totals->grand_total;
    $number_total = $cost_totals->number_total;
    if ($cost_rows) {
        $cost_chart_rows = $cost_report->chartRows($cost_section, $cost_grouping, $new_start_date, $new_end_date);
        $cost_subtotals = $cost_report->subtotals($cost_section, $cost_grouping, $new_start_date, $new_end_date);
    }

}
$total_rows = count($cost_rows);

$money = function ($amount) use ($currency) {
    return $currency->format($amount, $_SESSION['s_default_currency_symbol'],
        $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);
};

$grand_total = $money($grand_total);

/*
 * One cell of a row: its text (for the export), its HTML (for the table, with stored names echoed as they're saved,
 * already escaped), and its data-order (the raw number, for a cost, count, or cost per asset), or null
 */
$reporting_months = new DomainMOD\Reporting();
$cost_list = DomainMOD\CostReport::SECTIONS[$cost_section]['list'];
$cost_cell = function ($column, $row) use ($cost_list, $cost_subtotals, $money, $reporting_months) {
    $order = null;
    $link = '';
    switch ($column['value']) {
        case 'field':
            $text = $row->{$column['field']};
            if (!empty($column['tld'])) {
                return array('text' => '.' . $text, 'html' => '<bdi dir="ltr">.' . $text . '</bdi>', 'order' => null);
            }
            return array('text' => $text, 'html' => $text, 'order' => null);
        case 'cost':
            $amount = $row->total_cost;
            $text = $money($amount);
            $order = round((float) $amount, 2);
            break;
        case 'count':
            $text = $row->number_of_assets;
            $order = (int) $text;
            break;
        case 'per':
            $amount = $row->total_cost / $row->number_of_assets;
            $text = $money($amount);
            $order = round($amount, 2);
            break;
        case 'group_cost':
        case 'group_count':
        case 'group_per':
            $group = $cost_subtotals['group'][$row->id] ?? null;
            $group_cost = $group->total_cost ?? null;
            $group_count = $group->number_of_assets ?? 0;
            if ($column['value'] === 'group_count') {
                $text = $group_count;
                $order = (int) $group_count;
            } else {
                $amount = $column['value'] === 'group_cost' ? $group_cost : ($group_count ? $group_cost / $group_count : 0);
                $text = $money($amount);
                $order = round((float) $amount, 2);
            }
            break;
        case 'account':
            $text = $row->owner_name . ' (' . $row->username . ')';
            return array('text' => $text, 'html' => $text, 'order' => null);
        case 'year':
            return array('text' => $row->year, 'html' => $row->year, 'order' => null);
        case 'month':
            $text = $reporting_months->monthName($row->month);
            return array('text' => $text, 'html' => $text, 'order' => null);
        case 'month_cost':
        case 'year_cost':
            $amount = $column['value'] === 'month_cost'
                ? ($cost_subtotals['month'][$row->year . '-' . $row->month] ?? null)
                : ($cost_subtotals['year'][$row->year] ?? null);
            $text = $money($amount);
            $order = round((float) $amount, 2);
            break;
        default:
            $text = '';
    }
    $html = $text;
    if (isset($column['link'])) {
        $html = '<a href="' . $cost_list . '?' . $column['link'][0] . '=' . $row->{$column['link'][1]} . '">' . $text . '</a>';
    }
    return array('text' => $text, 'html' => $html, 'order' => $order);
};

if ($submission_failed != 1 && $total_rows > 0 && $export_data) {

    $export = new DomainMOD\Export();

    if ($daterange == '') {

        $export_file = $export->openFile($cost_grouping['export_all'], strtotime($time->stamp()));

    } else {

        $export_file = $export->openFile($cost_grouping['export'], $new_start_date . '--' . $new_end_date);

    }

    $export->writeRow($export_file, array($page_title));

    $export->writeBlankRow($export_file);

    if ($daterange == '') {

        $row_contents = array(_('Date Range') . ':', _('All dates'));

    } elseif (!empty($cost_grouping['range_cells'])) {

        $row_contents = array(_('Date Range') . ':', $new_start_date, $new_end_date);

    } else {

        $row_contents = array(_('Date Range') . ':', $daterange);

    }
    $export->writeRow($export_file, $row_contents);

    $export->writeRow($export_file, array(
        $cost_section == 'domains' ? _('Number of Domains') . ':' : _('Number of SSL Certificates') . ':',
        $number_total
    ));

    $export->writeRow($export_file, array(
        _('Total Cost') . ':',
        $grand_total,
        $_SESSION['s_default_currency']
    ));

    $export->writeBlankRow($export_file);

    $export->writeRow($export_file, array_map(function ($column) {
        return $column['csv'] ?? $column['heading'];
    }, $cost_grouping['columns']));

    // Every row has every cell in the export, its group's ones included
    foreach ($cost_rows as $row) {

        $export->writeRow($export_file, array_map(function ($column) use ($cost_cell, $row) {
            return $cost_cell($column, $row)['text'];
        }, $cost_grouping['columns']));

    }
    $export->closeFile($export_file);

}

if ($submission_failed == 1) $total_rows = 0;
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
    <?php require_once DIR_INC . '/layout/date-range-picker-head.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
// The page's actions, drawn in the page header (see Layout::renderPageActions())
$layout->addPageAction(_('View Full Report'), $report_filename . '?' . http_build_query(array('group' => $cost_key)), 'bi-arrow-counterclockwise');
if ($total_rows > 0) {
    $layout->addPageAction(_('Export Report'), $report_filename . '?' . http_build_query(array('group' => $cost_key,
        'export_data' => 1, 'daterange' => html_entity_decode($daterange, ENT_QUOTES, 'UTF-8'))), 'bi-download');
}

// Whether the report covers a date range (a whole, valid one), or every date. The summary below uses it too.
$report_has_range = $new_start_date != '' && $new_end_date != '';
$report_range = $report_has_range ? array('daterange' => $new_start_date . ' - ' . $new_end_date) : array(); ?>

<?php // The Group By menu, the date filter, and their button on one row, with a way back to every date once a range is set ?>
<form method="get" action="<?php echo $report_filename; ?>" class="domainmod-form">
    <div class="domainmod-date-filter d-flex flex-wrap align-items-center gap-2 mb-3">
        <label for="group" class="form-label fw-bold mb-0"><?php echo _('Group By'); ?></label>
        <select name="group" id="group" class="form-select w-auto"><?php
            foreach (DomainMOD\CostReport::groupings($cost_section) as $cost_option) {
                echo $form->showDropdownOption($cost_option['key'], $cost_option['label'], $cost_key);
            } ?>
        </select>
        <label for="daterange" class="form-label fw-bold mb-0"><?php echo _('Filter By Date Range'); ?></label>
        <input type="text" name="daterange" id="daterange" class="form-control w-auto" size="23" dir="ltr" value="<?php echo $daterange; ?>" />
        <?php echo $form->showSubmitButton(_('Filter Report'), '', ''); ?>
        <?php if ($report_has_range) echo $layout->linkButton($report_filename . '?' . http_build_query(array('group' => $cost_key)), _('Clear')); ?>
    </div>
</form>
<?php
/*
 * The dates the report covers, under the filter, then the summary: one card with the number of domains or SSL
 * certificates as its number, the total cost as its detail line, and a View link to the table below. The total is
 * money, so it never goes in the card's number, which Dashboard::displayPanel() rounds to a whole number. A report with
 * nothing in it gets an empty state instead, and a date range that failed its check shows only its error.
 */
if ($submission_failed != 1) {

    echo $layout->factList(array(array(
        'label' => _('Date Range'),
        'value' => $report_has_range ? $new_start_date . ' - ' . $new_end_date : _('All dates'),
        'ltr' => $report_has_range
    )));

    $report_url = '/reporting/' . $report_section . '/' . $report_filename;
    $report_group_url = $report_url . '?' . http_build_query(array('group' => $cost_key));

    if ($total_rows > 0) {

        // The amount and its currency code stay in order on right-to-left pages (isolated with Unicode marks, since
        // the card's detail line is plain text). displayPanel() prints the link as given, so its & is escaped here.
        $report_total = "\u{2066}" . $grand_total . ' ' . $_SESSION['s_default_currency'] . "\u{2069}";
        $report_card_url = $report_url . '?' . http_build_query(array('group' => $cost_key) + $report_range, '', '&amp;') . '#' . $slug;

        echo '<div class="row mb-4">';
        if ($report_section == 'domains') {
            echo $dashboard->displayPanel(_('Domains'), (int) $number_total, 'danger', 'bi-globe2', $report_card_url, $report_name, $report_total);
        } else {
            echo $dashboard->displayPanel(_('SSL Certificates'), (int) $number_total, 'danger', 'bi-shield-lock', $report_card_url, $report_name, $report_total);
        }
        echo '</div>';

    } elseif ($report_has_range) {

        $report_empty_text = $report_section == 'domains'
            ? _('No live domains with a fee expire between these dates.')
            : _('No live SSL certificates with a fee expire between these dates.');
        echo $layout->emptyState('bi-calendar-x', _('No costs in this date range'), $report_empty_text, '', '',
            $report_group_url, _('Clear the date range'));

    } else {

        $report_empty_text = $report_section == 'domains'
            ? _('Live domains are counted here once they have a fee.')
            : _('Live SSL certificates are counted here once they have a fee.');
        echo $layout->emptyState('bi-cash-coin', _('No costs to report yet'), $report_empty_text);

    }

}

if ($total_rows > 0) {

    // Each group's share of the total (Month: each month's cost)
    list($chart_labels, $chart_values) = $cost_report->chartData($cost_grouping, $cost_rows, $cost_chart_rows, $cost_subtotals);
    if ($cost_grouping['chart']['type'] === 'bar') {
        echo $chart->bar($slug . '-chart', $report_name, $chart_labels, $chart_values);
    } else {
        echo $chart->doughnut($slug . '-chart', $report_name, $chart_labels, $chart_values);
    }

    // Month's table has only four short columns, so it stops at half the page on wide screens
    $narrow_table = $cost_key === 'month';
    if ($narrow_table) echo '<div class="row"><div class="col-lg-8 col-xl-6">'; ?>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th><span class="visually-hidden"><?php echo _('Details'); ?></span></th><?php
            foreach ($cost_grouping['columns'] as $column) { ?>
            <th><?php echo $column['heading']; ?></th><?php
            } ?>
        </tr>
        </thead>
        <tbody><?php

        // Registrar, Provider, and Month show their group's cells (such as the registrar and its totals) only on the
        // group's first row
        $last_group = '';
        foreach ($cost_rows as $row) {

            $first_of_group = true;
            if (isset($cost_grouping['subtotal'])) {
                $group_value = DomainMOD\CostReport::groupKey($cost_grouping, $row);
                $first_of_group = $group_value != $last_group || $group_value == '';
                $last_group = $group_value;
            } ?>

            <tr>
                <td></td><?php
                foreach ($cost_grouping['columns'] as $column) {
                    if (!empty($column['group_only']) && !$first_of_group) {
                        // A number column's blank cell still has a (blank) sort value, as DataTables expects
                        $blank_order = in_array($column['value'], array('group_cost', 'group_count', 'group_per', 'year_cost'), true) ? ' data-order=""' : ''; ?>
                <td<?php echo $blank_order; ?>><?php echo $cost_grouping['subtotal']['blank']; ?></td><?php
                        continue;
                    }
                    $cell = $cost_cell($column, $row); ?>
                <td<?php if ($cell['order'] !== null) echo ' data-order="' . $cell['order'] . '"'; ?>><?php echo $cell['html']; ?></td><?php
                } ?>
            </tr><?php

        } ?>

        </tbody>
    </table><?php

    if ($narrow_table) echo '</div></div>';

}
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
<?php require_once DIR_INC . '/layout/date-range-picker-footer.inc.php'; ?>
<?php require_once DIR_INC . '/layout/chart-footer.inc.php'; ?>
</body>
</html>
