<?php
/**
 * /reporting/renewal-calendar.php
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
?>
<?php //@formatter:off
require_once __DIR__ . '/../_includes/start-session.inc.php';
require_once __DIR__ . '/../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$layout = new DomainMOD\Layout;
$time = new DomainMOD\Time();
$reporting = new DomainMOD\Reporting();
$currency = new DomainMOD\Currency();
$dashboard = new DomainMOD\Dashboard();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/reporting-renewal-calendar.inc.php';

$system->authCheck();
// Rates for every currency in use, or the calendar would leave out what uses a missing one
(new DomainMOD\Conversion())->ensureRates($_SESSION['s_user_id'] ?? 0, $_SESSION['s_default_currency'] ?? '');

$export_data = (int) ($_GET['export_data'] ?? 0);
$today = $time->timeBasic();

// The month shown (YYYY-MM, from 2000-01 through 2099-12), or today's month for anything else
$month = $_GET['month'] ?? '';
if (!is_string($month) || !preg_match('/^(20\d{2})-(0[1-9]|1[0-2])\z/', $month)) $month = substr($today, 0, 7);

// What's shown: everything, or just domains or just SSL certificates
$show = $_GET['show'] ?? 'all';
if (!is_string($show) || !in_array($show, array('all', 'domains', 'ssl'), true)) $show = 'all';

$month_start = $month . '-01';
$next_month = (new DateTime($month_start))->modify('+1 month')->format('Y-m');
$previous_month = (new DateTime($month_start))->modify('-1 month')->format('Y-m');
$month_label = sprintf('%s %s', $reporting->monthName(substr($month, 5, 2)), substr($month, 0, 4));
$month_label_of = function ($value) use ($reporting) {
    return sprintf('%s %s', $reporting->monthName(substr($value, 5, 2)), substr($value, 0, 4));
};

// This page's address with a month and what's shown (relative, as the other reports' links are)
$page_url = function ($for_month, $for_show, $extra = array()) use ($report_filename) {
    return $report_filename . '?' . http_build_query(array_merge(array('month' => $for_month, 'show' => $for_show), $extra));
};

// The month's live items, domains and SSL certificates together, sorted by date, then type, then name
$items = array();
if ($show !== 'ssl') {
    foreach ($reporting->renewalDomains($next_month . '-01', $month_start) as $row) {
        $items[] = array('type' => 'domain', 'id' => (int) $row->id, 'name' => $row->domain, 'date' => $row->expiry_date,
            'provider' => $row->registrar_name, 'owner' => $row->owner_name, 'username' => $row->username,
            'has_fee' => $row->has_fee, 'has_rate' => $row->has_rate, 'cost' => $row->converted_cost);
    }
}
if ($show !== 'domains') {
    foreach ($reporting->renewalCerts($next_month . '-01', $month_start) as $row) {
        $items[] = array('type' => 'ssl', 'id' => (int) $row->id, 'name' => $row->name, 'date' => $row->expiry_date,
            'provider' => $row->provider_name, 'owner' => $row->owner_name, 'username' => $row->username,
            'has_fee' => $row->has_fee, 'has_rate' => $row->has_rate, 'cost' => $row->converted_cost);
    }
}
usort($items, function ($a, $b) {
    return array($a['date'], $a['type'], strtolower($a['name'])) <=> array($b['date'], $b['type'], strtolower($b['name']));
});

// The month's totals (each type and both together), and each day's items, count, and cost
$totals = array('domain' => array('count' => 0, 'cost' => 0.0), 'ssl' => array('count' => 0, 'cost' => 0.0));
$missing = 0;
$days = array();
foreach ($items as $item) {
    $date = $item['date'];
    if (!isset($days[$date])) $days[$date] = array('items' => array(), 'domain' => 0, 'ssl' => 0, 'cost' => 0.0, 'costed' => 0);
    $days[$date]['items'][] = $item;
    $days[$date][$item['type']]++;
    $totals[$item['type']]['count']++;
    if ($item['has_fee'] && $item['has_rate']) {
        $days[$date]['cost'] += (float) $item['cost'];
        $days[$date]['costed']++;
        $totals[$item['type']]['cost'] += (float) $item['cost'];
    } else {
        $missing++;
    }
}
$total_count = $totals['domain']['count'] + $totals['ssl']['count'];
$total_cost = $totals['domain']['cost'] + $totals['ssl']['cost'];

// An amount in the user's currency, as the other reports show it
$money = function ($amount) use ($currency) {
    return $currency->format($amount, $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);
};

// What an item's cost shows: its renewal cost, or why it has none
$cost_text = function ($item) use ($money) {
    if (!$item['has_fee']) return _('No fee');
    if (!$item['has_rate']) return _('No rate');
    return $money($item['cost']);
};

// Names are stored with their special characters encoded, so they're decoded once before being escaped for the page
$html = function ($text) {
    return htmlspecialchars(html_entity_decode((string) $text, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
};

$item_url = function ($item) {
    return WEB_ROOT . ($item['type'] === 'domain' ? '/domains/edit.php?did=' : '/ssl/edit.php?sslcid=') . $item['id'];
};
$type_name = function ($item) {
    return $item['type'] === 'domain' ? _('Domain') : _('SSL Certificate');
};

// The weekday names, Sunday first (date('w') order): the full name, and the short one the calendar's columns show
$weekdays = array(_('Sunday'), _('Monday'), _('Tuesday'), _('Wednesday'), _('Thursday'), _('Friday'), _('Saturday'));
$weekdays_short = array(_('Sun'), _('Mon'), _('Tue'), _('Wed'), _('Thu'), _('Fri'), _('Sat'));
$weekday_of = function ($date) use ($weekdays) {
    return $weekdays[(int) (new DateTime($date))->format('w')];
};
// A day as screen readers hear it in a "+N more" link, such as "December 3, 2026"
$day_label = function ($date) use ($reporting) {
    return sprintf(_('%1$s %2$s, %3$s'), $reporting->monthName(substr($date, 5, 2)), (int) substr($date, 8, 2), substr($date, 0, 4));
};

// A day's summary line, such as "3 domains, 1 SSL certificate"
$day_summary = function ($day) {
    $parts = array();
    if ($day['domain'] > 0) $parts[] = sprintf(ngettext('%s domain', '%s domains', $day['domain']), number_format($day['domain']));
    if ($day['ssl'] > 0) $parts[] = sprintf(ngettext('%s SSL certificate', '%s SSL certificates', $day['ssl']), number_format($day['ssl']));
    return implode(', ', $parts);
};

$show_names = array('all' => _('All'), 'domains' => _('Domains'), 'ssl' => _('SSL Certificates'));

// The day lists' columns, and each one's name as its cells' data-label, which phones show before each value
$list_columns = array(_('Type'), _('Name'), _('Registrar or Provider'), _('Account'), _('Renewal Cost'));
$list_labels = array_map(function ($column) {
    return htmlspecialchars($column, ENT_QUOTES, 'UTF-8');
}, $list_columns);

// What a month with nothing to renew says, for what's shown
$empty_texts = array(
    'all' => _('No live domains or SSL certificates expire in this month.'),
    'domains' => _('No live domains expire in this month.'),
    'ssl' => _('No live SSL certificates expire in this month.'));

// A month with no renewals still exports, with its summary and the column headings, since Export is always offered
if ($export_data === 1) {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('renewal_calendar_report'), $month);

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(_('Month') . ':', $month_label);
    $export->writeRow($export_file, $row_contents);

    $row_contents = array(_('Showing') . ':', $show_names[$show]);
    $export->writeRow($export_file, $row_contents);

    $row_contents = array(_('Renewals') . ':', number_format($total_count));
    $export->writeRow($export_file, $row_contents);

    $row_contents = array(_('Renewal Cost') . ':', $money($total_cost), $_SESSION['s_default_currency']);
    $export->writeRow($export_file, $row_contents);

    if ($missing > 0) {
        $row_contents = array(sprintf(ngettext('%s item has no fee or rate, so it\'s left out of the costs.', '%s items have no fee or rate, so they\'re left out of the costs.', $missing), number_format($missing)));
        $export->writeRow($export_file, $row_contents);
    }

    $export->writeBlankRow($export_file);

    $row_contents = array(
        _('Date'),
        _('Type'),
        _('Name'),
        _('Registrar or Provider'),
        _('Account'),
        _('Renewal Cost')
    );
    $export->writeRow($export_file, $row_contents);

    foreach ($items as $item) {
        $row_contents = array(
            $item['date'],
            $type_name($item),
            // Names anyone could have typed, so one starting like a formula can't run when the file is opened
            $export->protectFormula(html_entity_decode((string) $item['name'], ENT_QUOTES, 'UTF-8')),
            $export->protectFormula(html_entity_decode((string) $item['provider'], ENT_QUOTES, 'UTF-8')),
            $export->protectFormula(html_entity_decode($item['owner'] . ' (' . $item['username'] . ')', ENT_QUOTES, 'UTF-8')),
            $cost_text($item)
        );
        $export->writeRow($export_file, $row_contents);
    }

    $export->closeFile($export_file);

}

// The calendar's htmx requests get this whole page, from which htmx takes just the calendar. X-DomainMOD-Result marks
// it as a real result for the footer's check, and HX-Push-Url puts the month's own address in the address bar.
if ($system->isHtmxRequest()) {
    header('X-DomainMOD-Result: 1');
    header('HX-Push-Url: ' . WEB_ROOT . '/reporting/' . $page_url($month, $show));
}

/*
 * The page's actions, drawn in the page header (see Layout::renderPageActions()). Export exports the month and filter
 * being shown, so each calendar update also swaps in the new page's Export link (hx-select-oob below). It's offered
 * for every month, so the link is always there to swap.
 */
$layout->addPageAction(_('Export Report'), $page_url($month, $show, array('export_data' => 1)), 'bi-download', array('id' => 'renewal-calendar-export'));

// Each month and filter link updates the calendar in place with htmx, and is a normal link without JavaScript
$calendar_htmx = 'hx-target="#renewal-calendar" hx-select="#renewal-calendar" hx-select-oob="#renewal-calendar-export" hx-swap="outerHTML" hx-sync="#renewal-calendar:replace"';
$htmx_link = function ($url, $text, $class = '', $extra = '') use ($calendar_htmx) {
    return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" hx-get="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" ' . $calendar_htmx . ($class !== '' ? ' class="' . $class . '"' : '') . $extra . '>' . $text . '</a>';
};
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php echo $layout->calloutTop(); ?>
<?php echo _('What expires on each day of the month, with each day\'s renewal cost in your currency. Only live domains and SSL certificates are shown. Expired and sold ones are left out.'); ?>
<?php echo $layout->calloutBottom(); ?>

<div id="renewal-calendar">

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <h2 class="h3 mb-0"><?php echo $month_label; ?></h2>
        <nav class="d-flex flex-wrap gap-2" aria-label="<?php echo htmlspecialchars(_('Month'), ENT_QUOTES, 'UTF-8'); ?>"><?php
            if ($month > '2000-01') {
                echo $htmx_link($page_url($previous_month, $show), '<i class="bi bi-chevron-left" aria-hidden="true"></i> ' . _('Previous Month') . '<span class="visually-hidden">, ' . $month_label_of($previous_month) . '</span>', 'btn btn-sm btn-outline-secondary');
            }
            if ($month !== substr($today, 0, 7)) {
                echo $htmx_link($page_url(substr($today, 0, 7), $show), _('This Month'), 'btn btn-sm btn-outline-secondary');
            }
            if ($month < '2099-12') {
                echo $htmx_link($page_url($next_month, $show), _('Next Month') . '<span class="visually-hidden">, ' . $month_label_of($next_month) . '</span> <i class="bi bi-chevron-right" aria-hidden="true"></i>', 'btn btn-sm btn-outline-secondary');
            } ?>
        </nav>
    </div>

    <p class="small text-body-secondary" data-domainmod-focus><?php echo sprintf(ngettext('Showing %1$s: %2$s renewal.', 'Showing %1$s: %2$s renewals.', $total_count), $month_label, number_format($total_count)); ?></p>

    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <div class="btn-group" role="group" aria-label="<?php echo htmlspecialchars(_('Show'), ENT_QUOTES, 'UTF-8'); ?>"><?php
            foreach ($show_names as $show_value => $show_name) {
                echo $htmx_link($page_url($month, $show_value), $show_name, 'btn btn-sm ' . ($show_value === $show ? 'btn-danger active' : 'btn-outline-secondary'), $show_value === $show ? ' aria-current="true"' : '');
            } ?>
        </div>
    </div>

    <div class="row"><?php
        $tile_url = '/reporting/' . $page_url($month, $show) . '#renewal-list';
        if ($show !== 'ssl') {
            echo $dashboard->displayPanel(_('Domains'), $totals['domain']['count'], 'danger', 'bi-calendar-event', $tile_url, _('Renewal Calendar'), $money($totals['domain']['cost']));
        }
        if ($show !== 'domains') {
            echo $dashboard->displayPanel(_('SSL Certificates'), $totals['ssl']['count'], 'danger', 'bi-shield-lock', $tile_url, _('Renewal Calendar'), $money($totals['ssl']['cost']));
        }
        echo $dashboard->displayPanel(_('All Renewals'), $total_count, 'secondary', 'bi-calendar3', $tile_url, _('Renewal Calendar'), $money($total_cost)); ?>
    </div><?php

    if ($missing > 0) { ?>
        <p class="small text-body-secondary mt-2"><?php echo sprintf(ngettext('%s item has no fee or rate, so it\'s left out of the costs.', '%s items have no fee or rate, so they\'re left out of the costs.', $missing), number_format($missing)); ?></p><?php
    } ?>

    <table class="table table-bordered domainmod-calendar d-none d-md-table mt-3">
        <caption class="visually-hidden"><?php echo sprintf(_('Renewals in %s'), $month_label); ?></caption>
        <thead>
        <tr><?php
            foreach ($weekdays_short as $index => $short) { ?>
                <th scope="col"><abbr title="<?php echo htmlspecialchars($weekdays[$index], ENT_QUOTES, 'UTF-8'); ?>"><?php echo $short; ?></abbr></th><?php
            } ?>
        </tr>
        </thead>
        <tbody><?php
        foreach ($reporting->calendarWeeks($month) as $week) { ?>
            <tr><?php
            foreach ($week as $date) {
                if ($date === null) { ?>
                    <td class="domainmod-calendar-outside"></td><?php
                    continue;
                }
                $day = $days[$date] ?? null;
                $is_today = $date === $today; ?>
                <td data-date="<?php echo $date; ?>"<?php echo $is_today ? ' class="domainmod-calendar-today" aria-current="date"' : ''; ?>>
                    <div class="domainmod-calendar-cell">
                        <span class="domainmod-calendar-day"><?php echo (int) substr($date, 8, 2); ?></span><?php
                        if ($day) {
                            if ($date < $today) { ?>
                                <span class="badge text-bg-danger"><?php echo _('Overdue'); ?></span><?php
                            } ?>
                            <div class="small text-body-secondary"><?php echo $day_summary($day); ?></div>
                            <div class="small fw-semibold"><?php echo $day['costed'] > 0 ? $money($day['cost']) : _('No fee or rate'); ?></div>
                            <ul class="list-unstyled mb-0 mt-1"><?php
                            foreach (array_slice($day['items'], 0, 3) as $item) { ?>
                                <li class="text-truncate"><i class="bi <?php echo $item['type'] === 'domain' ? 'bi-globe2' : 'bi-shield-lock'; ?> text-body-secondary" aria-hidden="true"></i> <a href="<?php echo $item_url($item); ?>" title="<?php echo $html($item['name']); ?>"><span class="visually-hidden"><?php echo $type_name($item); ?>: </span><?php echo $html($item['name']); ?></a></li><?php
                            }
                            $more = count($day['items']) - 3;
                            if ($more > 0) { ?>
                                <li><a href="#renewals-<?php echo $date; ?>" class="small"><?php echo sprintf(_('+%s more'), number_format($more)); ?><span class="visually-hidden"> <?php echo sprintf(_('on %s'), $day_label($date)); ?></span></a></li><?php
                            } ?>
                            </ul><?php
                        } ?>
                    </div>
                </td><?php
            } ?>
            </tr><?php
        } ?>
        </tbody>
    </table>

    <h2 class="h4 mt-4" id="renewal-list"><?php echo sprintf(_('Renewals in %s'), $month_label); ?></h2>
    <p class="small text-body-secondary d-md-none"><?php echo _('The calendar shows on wider screens.'); ?></p><?php

    if ($items) {
        foreach ($days as $date => $day) { ?>
            <h3 class="h5 mt-3" id="renewals-<?php echo $date; ?>"><?php echo $date . ', ' . $weekday_of($date); ?><?php
                if ($date < $today) { ?> <span class="badge text-bg-danger fs-6 align-middle"><?php echo _('Overdue'); ?></span><?php } ?></h3>
            <div class="table-responsive">
            <table class="table table-striped table-sm domainmod-calendar-list domainmod-stack-table" role="table">
                <thead role="rowgroup">
                <tr role="row"><?php
                    foreach ($list_columns as $index => $column) { ?>
                    <th scope="col" role="columnheader"<?php echo $index === 4 ? ' class="text-sm-end"' : ''; ?>><?php echo $column; ?></th><?php
                    } ?>
                </tr>
                </thead>
                <tbody role="rowgroup"><?php
                foreach ($day['items'] as $item) { ?>
                    <tr role="row">
                        <td role="cell" data-label="<?php echo $list_labels[0]; ?>"><?php echo $type_name($item); ?></td>
                        <td role="cell" data-label="<?php echo $list_labels[1]; ?>"><a href="<?php echo $item_url($item); ?>"><?php echo $html($item['name']); ?></a></td>
                        <td role="cell" data-label="<?php echo $list_labels[2]; ?>"><?php echo $html($item['provider']); ?></td>
                        <td role="cell" data-label="<?php echo $list_labels[3]; ?>"><?php echo $html($item['owner']); ?> (<?php echo $html($item['username']); ?>)</td>
                        <td role="cell" data-label="<?php echo $list_labels[4]; ?>" class="text-sm-end"><?php echo $cost_text($item); ?></td>
                    </tr><?php
                } ?>
                </tbody>
            </table>
            </div><?php
        }
    } else {
        // TRANSLATORS: The heading when the Renewal Calendar's month has nothing to renew. %s is a month and year, such as "December 2026".
        echo $layout->emptyState('bi-calendar-check', sprintf(_('No renewals in %s'), $month_label), $empty_texts[$show], '', '', '', '', 3);
    } ?>

</div>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
</body>
</html>
