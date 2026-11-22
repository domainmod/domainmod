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
$today = $time->userToday();

// Hosting services are shown once the database update has added the hosting tables. Their dates are projected renewals,
// and whether a stored date has passed is judged by the hosting date (see DomainMOD\Hosting::hostingDate()).
$hosting_available = DomainMOD\Hosting::isAvailable($deeb->cnxx);
$hosting_today = $hosting_available ? DomainMOD\Hosting::hostingDate($deeb->cnxx) : $today;

// What's shown: everything, or just domains, SSL certificates, or hosting services
$show_choices = $hosting_available ? array('all', 'domains', 'ssl', 'hosting') : array('all', 'domains', 'ssl');
$show = $_GET['show'] ?? 'all';
if (!is_string($show) || !in_array($show, $show_choices, true)) $show = 'all';

// The month shown (YYYY-MM, from 2000-01 through 2099-12), or today's month for anything else (the hosting date's month
// when only hosting services are shown)
$month = $_GET['month'] ?? '';
if (!is_string($month) || !preg_match('/^(20\d{2})-(0[1-9]|1[0-2])\z/', $month)) {
    $month = substr($show === 'hosting' ? $hosting_today : $today, 0, 7);
}
$this_month = substr($show === 'hosting' ? $hosting_today : $today, 0, 7);

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

/*
 * The month's live items, domains, SSL certificates, and hosting services together, sorted by date, then type, then
 * name. A hosting service appears on each of its projected renewals in the month (see DomainMOD\HostingReport), and
 * only its stored date can be overdue or waiting for its renewal to be confirmed (see DomainMOD\Hosting::renewalState()).
 * Services with no fee or no rate are still shown, as domains and SSL certificates
 * are, so every service that isn't Cancelled is read.
 */
$month_end = (new DateTime($next_month . '-01'))->modify('-1 day')->format('Y-m-d');
$items = array();
if ($show === 'all' || $show === 'domains') {
    foreach ($reporting->renewalDomains($next_month . '-01', $month_start) as $row) {
        $items[] = array('type' => 'domain', 'id' => (int) $row->id, 'name' => $row->domain, 'date' => $row->expiry_date,
            'provider' => $row->registrar_name, 'owner' => $row->owner_name, 'username' => $row->username,
            'has_fee' => $row->has_fee, 'has_rate' => $row->has_rate, 'cost' => $row->converted_cost,
            'overdue' => $row->expiry_date < $today, 'confirm' => false);
    }
}
if ($show === 'all' || $show === 'ssl') {
    foreach ($reporting->renewalCerts($next_month . '-01', $month_start) as $row) {
        $items[] = array('type' => 'ssl', 'id' => (int) $row->id, 'name' => $row->name, 'date' => $row->expiry_date,
            'provider' => $row->provider_name, 'owner' => $row->owner_name, 'username' => $row->username,
            'has_fee' => $row->has_fee, 'has_rate' => $row->has_rate, 'cost' => $row->converted_cost,
            'overdue' => $row->expiry_date < $today, 'confirm' => false);
    }
}
if ($hosting_available && ($show === 'all' || $show === 'hosting')) {
    $hosting_report = new DomainMOD\HostingReport($deeb->cnxx, $_SESSION['s_user_id'] ?? 0);
    $hosting_services = $hosting_report->services($month_end);
    $hosting_rows = DomainMOD\HostingReport::payments($hosting_services, $month_start, $month_end);
    // A billing term DomainMOD doesn't know gives no projected renewals, but the stored date still shows in its month
    foreach ($hosting_services as $service) {
        if (DomainMOD\Hosting::validTerm($service->billing_term) || $service->expiry_date < $month_start
            || $service->expiry_date > $month_end) continue;
        $row = clone $service;
        $row->renewal_date = $service->expiry_date;
        $row->projected = false;
        $hosting_rows[] = $row;
    }
    foreach ($hosting_rows as $row) {
        // Only the stored date has a renewal state, never a projected renewal
        $state = $row->projected ? '' : DomainMOD\Hosting::renewalState($row->active, $row->autorenew, $row->renewal_date,
            $row->billing_term, $hosting_today);
        $items[] = array('type' => 'hosting', 'id' => (int) $row->id, 'name' => $row->name, 'date' => $row->renewal_date,
            'provider' => $row->provider_name, 'owner' => $row->owner_name, 'username' => $row->username,
            'has_fee' => $row->has_fee, 'has_rate' => $row->has_rate, 'cost' => $row->converted_cost,
            'overdue' => $state === DomainMOD\Hosting::STATE_OVERDUE, 'confirm' => $state === DomainMOD\Hosting::STATE_CONFIRM);
    }
}
usort($items, function ($a, $b) {
    return array($a['date'], $a['type'], strtolower($a['name'])) <=> array($b['date'], $b['type'], strtolower($b['name']));
});

// The month's totals (each type and all together), and each day's items, count, and cost.
// A day is marked Overdue when it has a domain or SSL certificate whose expiry date has passed, or an overdue hosting
// service's stored date, and Confirm renewal when it has a hosting service's stored date waiting for its renewal to be
// confirmed (both when it has both), so a past day with only projected hosting renewals is marked neither.
$totals = array('domain' => array('count' => 0, 'cost' => 0.0), 'ssl' => array('count' => 0, 'cost' => 0.0),
    'hosting' => array('count' => 0, 'cost' => 0.0));
$missing = 0;
$days = array();
foreach ($items as $item) {
    $date = $item['date'];
    if (!isset($days[$date])) $days[$date] = array('items' => array(), 'domain' => 0, 'ssl' => 0, 'hosting' => 0, 'cost' => 0.0, 'costed' => 0, 'overdue' => false, 'confirm' => false);
    $days[$date]['items'][] = $item;
    $days[$date][$item['type']]++;
    if ($item['overdue']) $days[$date]['overdue'] = true;
    if ($item['confirm']) $days[$date]['confirm'] = true;
    $totals[$item['type']]['count']++;
    if ($item['has_fee'] && $item['has_rate']) {
        $days[$date]['cost'] += (float) $item['cost'];
        $days[$date]['costed']++;
        $totals[$item['type']]['cost'] += (float) $item['cost'];
    } else {
        $missing++;
    }
}
$total_count = $totals['domain']['count'] + $totals['ssl']['count'] + $totals['hosting']['count'];
$total_cost = $totals['domain']['cost'] + $totals['ssl']['cost'] + $totals['hosting']['cost'];

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
    $paths = array('domain' => '/domains/edit.php?did=', 'ssl' => '/ssl/edit.php?sslcid=', 'hosting' => '/hosting/edit.php?hsid=');
    return WEB_ROOT . $paths[$item['type']] . $item['id'];
};
$type_name = function ($item) {
    $names = array('domain' => _('Domain'), 'ssl' => _('SSL Certificate'), 'hosting' => _('Hosting Service'));
    return $names[$item['type']];
};
$type_icons = array('domain' => 'bi-globe2', 'ssl' => 'bi-shield-lock', 'hosting' => 'bi-hdd-stack');

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
    if ($day['hosting'] > 0) $parts[] = sprintf(ngettext('%s hosting service', '%s hosting services', $day['hosting']), number_format($day['hosting']));
    return implode(', ', $parts);
};

$show_names = array('all' => _('All'), 'domains' => _('Domains'), 'ssl' => _('SSL Certificates'));
if ($hosting_available) $show_names['hosting'] = _('Hosting Services');

// The list's columns, and each one's name as its cells' data-label, which phones show before each value (the Date
// column's cells are row headers, which phones show as each row's heading instead)
$list_columns = array(_('Date'), _('Type'), _('Name'), _('Registrar or Provider'), _('Account'), _('Renewal Cost'));
$list_labels = array_map(function ($column) {
    return htmlspecialchars($column, ENT_QUOTES, 'UTF-8');
}, $list_columns);

// What a month with nothing to renew says, for what's shown
$empty_texts = array(
    'all' => _('No live domains or SSL certificates expire in this month.'),
    'domains' => _('No live domains expire in this month.'),
    'ssl' => _('No live SSL certificates expire in this month.'));
if ($hosting_available) {
    $empty_texts['all'] = _('No live domains or SSL certificates expire in this month, and no hosting services have a projected renewal in it.');
    $empty_texts['hosting'] = _('No hosting services have a projected renewal in this month.');
}

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
<?php
if ($hosting_available) {
    echo _('What renews on each day of the month, with each day\'s renewal cost in your currency. Only live domains, SSL certificates, and hosting services are shown. Expired and sold domains, expired SSL certificates, and cancelled hosting services are left out. Hosting services\' dates are projected renewals, so a monthly service appears on each day it renews.');
} else {
    echo _('What expires on each day of the month, with each day\'s renewal cost in your currency. Only live domains and SSL certificates are shown. Expired and sold ones are left out.');
} ?>
<?php echo $layout->calloutBottom(); ?>

<div id="renewal-calendar">

    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
        <h2 class="h3 mb-0"><?php echo $month_label; ?></h2>
        <nav class="d-flex flex-wrap gap-2" aria-label="<?php echo htmlspecialchars(_('Month'), ENT_QUOTES, 'UTF-8'); ?>"><?php
            if ($month > '2000-01') {
                echo $htmx_link($page_url($previous_month, $show), '<i class="bi bi-chevron-left" aria-hidden="true"></i> ' . _('Previous Month') . '<span class="visually-hidden">, ' . $month_label_of($previous_month) . '</span>', 'btn btn-sm btn-outline-secondary');
            }
            if ($month !== $this_month) {
                echo $htmx_link($page_url($this_month, $show), _('This Month'), 'btn btn-sm btn-outline-secondary');
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
        if ($show === 'all' || $show === 'domains') {
            echo $dashboard->displayPanel(_('Domains'), $totals['domain']['count'], 'danger', 'bi-calendar-event', $tile_url, _('Renewal Calendar'), $money($totals['domain']['cost']));
        }
        if ($show === 'all' || $show === 'ssl') {
            echo $dashboard->displayPanel(_('SSL Certificates'), $totals['ssl']['count'], 'danger', 'bi-shield-lock', $tile_url, _('Renewal Calendar'), $money($totals['ssl']['cost']));
        }
        if ($hosting_available && ($show === 'all' || $show === 'hosting')) {
            echo $dashboard->displayPanel(_('Hosting Services'), $totals['hosting']['count'], 'danger', 'bi-hdd-stack', $tile_url, _('Renewal Calendar'), $money($totals['hosting']['cost']));
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
                // Today is the hosting date when only hosting services are shown, as This Month is
                $is_today = $date === ($show === 'hosting' ? $hosting_today : $today); ?>
                <td data-date="<?php echo $date; ?>"<?php echo $is_today ? ' class="domainmod-calendar-today" aria-current="date"' : ''; ?>>
                    <div class="domainmod-calendar-cell">
                        <span class="domainmod-calendar-day"><?php echo (int) substr($date, 8, 2); ?></span><?php
                        if ($day) {
                            if ($day['overdue']) { ?>
                                <span class="badge text-bg-danger"><?php echo _('Overdue'); ?></span><?php
                            }
                            if ($day['confirm']) { ?>
                                <span class="badge text-bg-warning"><?php echo _('Confirm renewal'); ?></span><?php
                            } ?>
                            <div class="small text-body-secondary"><?php echo $day_summary($day); ?></div>
                            <div class="small fw-semibold"><?php echo $day['costed'] > 0 ? $money($day['cost']) : _('No fee or rate'); ?></div>
                            <ul class="list-unstyled mb-0 mt-1"><?php
                            foreach (array_slice($day['items'], 0, 3) as $item) { ?>
                                <li class="text-truncate"><i class="bi <?php echo $type_icons[$item['type']]; ?> text-body-secondary" aria-hidden="true"></i> <a href="<?php echo $item_url($item); ?>" title="<?php echo $html($item['name']); ?>"><span class="visually-hidden"><?php echo $type_name($item); ?>: </span><?php echo $html($item['name']); ?></a></li><?php
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

    if ($items) { ?>
        <div class="table-responsive">
        <table class="table table-striped table-sm domainmod-calendar-list domainmod-stack-table" role="table">
            <?php // TRANSLATORS: The hidden caption of the Renewal Calendar's table of the month's renewals. %s is a month and year, such as "December 2026". ?>
            <caption class="visually-hidden"><?php echo sprintf(_('Renewals in %s, by date'), $month_label); ?></caption>
            <thead role="rowgroup">
            <tr role="row"><?php
                foreach ($list_columns as $index => $column) { ?>
                <th scope="col" role="columnheader"<?php echo $index === 5 ? ' class="text-sm-end"' : ''; ?>><?php echo $column; ?></th><?php
                } ?>
            </tr>
            </thead>
            <tbody role="rowgroup"><?php
            // A row's state: overdue, waiting for its renewal to be confirmed (a hosting service's stored date), or
            // neither, with each state's icon, colour, and name
            $row_state = function ($item) {
                return $item['overdue'] ? 'overdue' : ($item['confirm'] ? 'confirm' : '');
            };
            $state_icons = array('overdue' => '<i class="bi bi-exclamation-triangle text-danger" aria-hidden="true"></i> ',
                'confirm' => '<i class="bi bi-hourglass-split text-warning-emphasis" aria-hidden="true"></i> ');
            $state_classes = array('overdue' => ' text-danger-emphasis', 'confirm' => ' text-warning-emphasis');
            $state_names = array('overdue' => _('Overdue'), 'confirm' => _('Confirm renewal'));
            foreach ($days as $date => $day) {
                $first_state = $row_state($day['items'][0]);
                foreach ($day['items'] as $index => $item) {
                    /*
                     * The row's date. An overdue renewal's date (a domain or SSL certificate past its expiry date, or an
                     * overdue hosting service's stored date, never a projected renewal) shows in red (the theme's darker
                     * or lighter red, which keeps its contrast in light and dark mode), after a red warning icon, and
                     * tells screen readers it's overdue. A hosting service's stored date waiting for its renewal to be
                     * confirmed shows the same way in amber, and tells screen readers so.
                     */
                    $state = $row_state($item);
                    $date_text = ($state_icons[$state] ?? '') . $date . ', ' . $weekday_of($date);
                    $date_class = 'd-block d-sm-table-cell' . ($state_classes[$state] ?? '');
                    $overdue_text = $state !== '' ? '<span class="visually-hidden">, ' . $state_names[$state] . '</span>' : ''; ?>
                    <tr role="row"><?php
                        /*
                         * Each row's date is its row header. The date shows on the day's first row, which the calendar's
                         * "+N more" links jump to, and on a later row whose state (overdue, waiting for its renewal to be
                         * confirmed, or neither) differs from the first row's, so its own warning (or its lack of one)
                         * shows. On the day's other rows it shows only on phones, where each row
                         * stacks on its own, and is visually hidden on wider screens, so screen readers still hear it.
                         */
                        if ($index === 0) { ?>
                            <th scope="row" role="rowheader" id="renewals-<?php echo $date; ?>" class="<?php echo $date_class; ?>"><?php echo $date_text . $overdue_text; ?></th><?php
                        } elseif ($state !== $first_state) { ?>
                            <th scope="row" role="rowheader" class="<?php echo $date_class; ?>"><?php echo $date_text . $overdue_text; ?></th><?php
                        } else { ?>
                            <th scope="row" role="rowheader" class="<?php echo $date_class; ?>"><span class="d-sm-none"><?php echo $date_text; ?></span><span class="visually-hidden d-none d-sm-inline"><?php echo $date_text; ?></span><?php echo $overdue_text; ?></th><?php
                        } ?>
                        <td role="cell" data-label="<?php echo $list_labels[1]; ?>"><?php echo $type_name($item); ?></td>
                        <td role="cell" data-label="<?php echo $list_labels[2]; ?>"><a href="<?php echo $item_url($item); ?>"><?php echo $html($item['name']); ?></a></td>
                        <td role="cell" data-label="<?php echo $list_labels[3]; ?>"><?php echo $html($item['provider']); ?></td>
                        <td role="cell" data-label="<?php echo $list_labels[4]; ?>"><?php echo $html($item['owner']); ?> (<?php echo $html($item['username']); ?>)</td>
                        <td role="cell" data-label="<?php echo $list_labels[5]; ?>" class="text-sm-end"><?php echo $cost_text($item); ?></td>
                    </tr><?php
                }
            } ?>
            </tbody>
        </table>
        </div><?php
    } else {
        // TRANSLATORS: The heading when the Renewal Calendar's month has nothing to renew. %s is a month and year, such as "December 2026".
        echo $layout->emptyState('bi-calendar-check', sprintf(_('No renewals in %s'), $month_label), $empty_texts[$show], '', '', '', '', 3);
    } ?>

</div>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
</body>
</html>
