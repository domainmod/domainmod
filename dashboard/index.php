<?php
/**
 * /dashboard/index.php
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
<?php
require_once __DIR__ . '/../_includes/start-session.inc.php';
require_once __DIR__ . '/../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$dashboard = new DomainMOD\Dashboard();
$reporting = new DomainMOD\Reporting();
$currency = new DomainMOD\Currency();
$chart = new DomainMOD\Chart();
$schedule = new DomainMOD\Scheduler();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/config-demo.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/dashboard-main.inc.php';

$system->authCheck();
// Rates for every currency in use, or the renewal cost chart below would leave out what uses a missing one
(new DomainMOD\Conversion())->ensureRates($_SESSION['s_user_id'] ?? 0, $_SESSION['s_default_currency'] ?? '');
$pdo = $deeb->cnxx;

// The domains renewing in the renewal cost chart's 12 months (overdue ones included), shared with the At Risk item and
// the Domain Renewals and At-Risk Domains reports, and the SSL certificates renewing in them, as SSL Renewals
// counts them
$today = $time->userToday();
// (with each domain's lock and registry status, so the At Risk item counts what the At-Risk Domains report lists)
$renewal_rows = $reporting->renewalDomainsWithStatus($reporting->renewalChartEnd($today));
$renewal_cert_rows = $reporting->renewalCerts($reporting->renewalChartEnd($today));
$at_risk_count = count($reporting->atRiskDomains($renewal_rows, $today, $time->userTodayPlusDays(DomainMOD\Reporting::AT_RISK_DAYS, $today)));
// Hosting's counts and chart series, once the database update has added its tables. Hosting compares with the
// hosting date (today in the hosting time zone, see Hosting::hostingDate()), not the user's date domains and SSL use.
$hosting_available = DomainMOD\Hosting::isAvailable($pdo);
$hosting_today = $hosting_available ? DomainMOD\Hosting::hostingDate($pdo) : '';
// The hosting services' payments in the chart's months, matched to them by year and month. The months follow the
// user's date, as the domains' and certificates' do, while the hosting date decides which services are overdue.
$renewal_hosting_months = $hosting_available ? $reporting->renewalHostingMonths(substr($today, 0, 7) . '-01',
    DomainMOD\Hosting::addDays($reporting->renewalChartEnd($today), -1), $hosting_today) : array();
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
////////////////////////////////////////////////////////////////////////////////////////////////////
// The counts by status: the totals, the Pending block, and whether Hosting is tracked at all
////////////////////////////////////////////////////////////////////////////////////////////////////
$domain_counts = DomainMOD\Dashboard::statusCounts($pdo, 'domains');
$ssl_counts = DomainMOD\Dashboard::statusCounts($pdo, 'ssl_certs');
$hosting_counts = $hosting_available ? DomainMOD\Dashboard::statusCounts($pdo, 'hosting_services') : array();

$total_live_domains = DomainMOD\Dashboard::countOf($domain_counts, array(0, 10), true);
$total_live_ssl = DomainMOD\Dashboard::countOf($ssl_counts, array(0, 10), true);
$total_sold_domains = DomainMOD\Dashboard::countOf($domain_counts, array(10));
// Every live service (all but cancelled), as the Domains and SSL Certificates totals count their live items
$total_live_hosting = DomainMOD\Dashboard::countOf($hosting_counts, array(1, 2, 3, 4, 5, 6, 7, 8, 9));
$total_hosting = array_sum($hosting_counts);

// While there are no domains, SSL certificates, or hosting services in any status, the Getting Started checklist takes
// the totals' place, since they'd only be zeros (Greg's choice, 2026-11-15), unless the user has hidden it
$show_getting_started = $dashboard->showGettingStarted();

// Administrators are warned when cron.php has stopped running (or, once there are domains, has never run)
echo $schedule->healthAlert('dashboard', $show_getting_started);

////////////////////////////////////////////////////////////////////////////////////////////////////
// The totals strip: each total's icon, number, and label, the label linking to its list (Greg's choice, 2026-11-21)
////////////////////////////////////////////////////////////////////////////////////////////////////
// Each total: its link, icon, number, label (which agrees with the number, as in "1 Domain"), and the link's name for
// screen readers, the number and label as one translated phrase (such as "12 Domains"). The number and label shown are
// hidden from screen readers, so the total is read once, as the link.
$totals = array();
$totals[] = array('/domains/index.php?is_active=LIVE', 'bi-globe2', number_format($total_live_domains), ngettext('Domain', 'Domains', $total_live_domains),
    sprintf(ngettext('%s Domain', '%s Domains', $total_live_domains), number_format($total_live_domains)));
// Always shown, like SSL Certificates, so an install with no hosting services yet sees its zero (Greg's choice, 2026-11-21)
if ($hosting_available) {
    $totals[] = array('/hosting/index.php?is_active=LIVE', 'bi-hdd-rack', number_format($total_live_hosting), ngettext('Hosting Service', 'Hosting Services', $total_live_hosting),
        sprintf(ngettext('%s Hosting Service', '%s Hosting Services', $total_live_hosting), number_format($total_live_hosting)));
}
$totals[] = array('/ssl/index.php?is_active=LIVE', 'bi-shield-lock', number_format($total_live_ssl), ngettext('SSL Certificate', 'SSL Certificates', $total_live_ssl),
    sprintf(ngettext('%s SSL Certificate', '%s SSL Certificates', $total_live_ssl), number_format($total_live_ssl)));
if ($total_sold_domains) {
    $totals[] = array('/domains/index.php?is_active=10', 'bi-cart', number_format($total_sold_domains), ngettext('Sold Domain', 'Sold Domains', $total_sold_domains),
        sprintf(ngettext('%s Sold Domain', '%s Sold Domains', $total_sold_domains), number_format($total_sold_domains)));
}
if (!$show_getting_started) { ?>
<h2 class="visually-hidden"><?php echo _('Totals'); ?></h2>
<div class="card domainmod-dashboard-totals d-inline-block mw-100 mb-0">
    <div class="d-flex flex-column flex-sm-row"><?php
        foreach ($totals as $total) { ?>
            <div class="domainmod-dashboard-total position-relative d-flex align-items-center gap-3 px-4 py-2">
                <i class="bi <?php echo $total[1]; ?> fs-4" aria-hidden="true"></i>
                <div>
                    <div class="fs-5 fw-semibold lh-1" aria-hidden="true"><?php echo $total[2]; ?></div>
                    <a href="<?php echo WEB_ROOT . $total[0]; ?>" class="stretched-link text-body-secondary link-underline-opacity-0 small"><span class="visually-hidden"><?php echo $total[4]; ?></span><span aria-hidden="true"><?php echo $total[3]; ?></span></a>
                </div>
            </div><?php
        } ?>
    </div>
</div><?php
} else {
    // The Getting Started checklist, while nothing has been added
    echo $dashboard->gettingStarted();
}

////////////////////////////////////////////////////////////////////////////////////////////////////
// Needs Attention: At-Risk Domains, hosting renewals to confirm, and watched domains about to drop, each a sentence
// linking to its list, with a hint under it. It shares a row with Coming Up, side by side on extra-large screens and
// stacked below them, and takes the whole row when Coming Up is hidden (Greg's choice, 2026-11-21).
////////////////////////////////////////////////////////////////////////////////////////////////////
// Hosting services waiting for their renewal to be confirmed (see Hosting::renewalState()), counted from the same rows
// and rule as At-Risk Hosting Services, which the item opens, whatever the Expiration Days setting
$confirm_count_hosting = $hosting_available ? DomainMOD\HostingReport::confirmCount((new DomainMOD\HostingReport($pdo,
    $_SESSION['s_user_id'] ?? 0))->services($hosting_today), $hosting_today) : 0;

// Watched domains whose deletion is pending, so they're about to drop, counted with the Watch List's own filter, so the
// item's count is what its link lists (unless the Watch List is turned off in System Settings)
$watch_count_pending_delete = 0;
if (DomainMOD\WatchList::isEnabled()) {
    $watch_list = new DomainMOD\WatchList($pdo, (int) ($_SESSION['s_user_id'] ?? 0));
    $watch_filter = $watch_list->filterParts(DomainMOD\WatchList::normalizeState(array('stage' => 'pending_delete')));
    $watch_count_pending_delete = $watch_list->count($watch_filter['sql'], $watch_filter['params']);
}

// Each item: its count, sentence, hint, colour (a Bootstrap colour, for the icon's tint), icon, and link. At Risk counts
// only domains (Reporting::atRiskDomains(), as the At-Risk Domains report lists them), with its own fixed 30 days. The
// hints don't show the count, so each picks between its two sentences itself (ngettext()'s plural forms follow a shown
// number).
$attention = array();
if ($at_risk_count) {
    $attention[] = array($at_risk_count,
        sprintf(ngettext('%s domain is at risk', '%s domains are at risk', $at_risk_count), number_format($at_risk_count)),
        sprintf($at_risk_count === 1
            ? _("It's overdue, or it expires in the next %s days and has auto-renewal, privacy, or the transfer lock turned off, is on hold at the registry, or is being deleted or restored by the registry.")
            : _("They're overdue, or they expire in the next %s days and have auto-renewal, privacy, or the transfer lock turned off, are on hold at the registry, or are being deleted or restored by the registry."),
            DomainMOD\Reporting::AT_RISK_DAYS),
        'danger', 'bi-exclamation-triangle', '/reporting/domains/at-risk.php');
}
if ($confirm_count_hosting) {
    $attention[] = array($confirm_count_hosting,
        sprintf(ngettext('%s hosting renewal to confirm', '%s hosting renewals to confirm', $confirm_count_hosting), number_format($confirm_count_hosting)),
        $confirm_count_hosting === 1
            ? _('Its renewal date has passed. Mark it renewed once your host has charged you.')
            : _('Their renewal dates have passed. Mark each one renewed once your host has charged you.'),
        'warning', 'bi-hourglass-split', '/reporting/hosting/at-risk.php?show=confirm#hosting-list');
}
if ($watch_count_pending_delete) {
    $attention[] = array($watch_count_pending_delete,
        sprintf(ngettext('%s watched domain is about to drop', '%s watched domains are about to drop', $watch_count_pending_delete), number_format($watch_count_pending_delete)),
        $watch_count_pending_delete === 1 ? _('Its deletion is pending at the registry.') : _('Their deletion is pending at the registry.'),
        'info', 'bi-binoculars', '/watch/?stage=pending_delete');
} ?>
<div class="row g-4 mt-0">
    <div class="col-xl">
        <section class="card card-outline card-danger domainmod-dashboard-block h-100 mb-0" aria-labelledby="domainmod-attention-heading">
            <div class="card-header domainmod-dashboard-block-header">
                <h2 class="card-title" id="domainmod-attention-heading"><?php echo _('Needs Attention'); ?></h2><?php
                if ($attention) { ?>
                <span class="domainmod-dashboard-block-note"><?php echo sprintf(ngettext('%s item', '%s items', count($attention)), number_format(count($attention))); ?></span><?php
                } ?>
            </div><?php
            if ($attention) { ?>
            <ul class="list-group list-group-flush"><?php
                foreach ($attention as $index => $item) { ?>
                <li class="list-group-item domainmod-attention-item position-relative">
                    <span class="domainmod-attention-icon bg-<?php echo $item[3]; ?>-subtle text-<?php echo $item[3]; ?>-emphasis" aria-hidden="true"><i class="bi <?php echo $item[4]; ?>"></i></span>
                    <span class="domainmod-attention-text">
                        <a href="<?php echo WEB_ROOT . $item[5]; ?>" class="stretched-link link-underline-opacity-0 link-underline-opacity-75-hover" aria-describedby="domainmod-attention-hint-<?php echo $index; ?>"><?php echo $item[1]; ?></a>
                        <span class="domainmod-attention-hint" id="domainmod-attention-hint-<?php echo $index; ?>"><?php echo $item[2]; ?></span>
                    </span>
                    <i class="bi bi-chevron-right text-body-secondary" aria-hidden="true"></i>
                </li><?php
                } ?>
            </ul><?php
            } else { ?>
            <div class="card-body domainmod-attention-none text-success-emphasis">
                <i class="bi bi-check-circle" aria-hidden="true"></i> <?php echo _('Nothing needs attention.'); ?>
            </div><?php
            } ?>
        </section>
    </div>
<?php
////////////////////////////////////////////////////////////////////////////////////////////////////
// Coming Up: the renewals due within 7 days, 30 days, and the Expiration Days setting, by type, each count linking to
// the type's list filtered to that window (overdue ones included, as the old expiring tiles counted them)
////////////////////////////////////////////////////////////////////////////////////////////////////
$expiration_days = (int) $pdo->query("
    SELECT expiration_days
    FROM settings")->fetchColumn();
$coming_up_windows = DomainMOD\Dashboard::comingUpWindows($expiration_days);

// Domains and SSL certificates count to the user's date plus each window, and hosting services to the hosting date
// (today in the hosting time zone, see Hosting::hostingDate()) plus each window
$coming_up_ends = array();
$hosting_ends = array();
foreach ($coming_up_windows as $window) {
    $coming_up_ends[$window] = $time->userTodayPlusDays($window, $today);
    if ($hosting_available) $hosting_ends[$window] = DomainMOD\Hosting::addDays($hosting_today, $window);
}

// Each row: its name, chart colour, list page, counts, window end dates, and the count's words for its links' text for
// screen readers (such as "4 domains", then the window, as in "4 domains, due within 7 days"). Hosting's row shows
// once there are hosting services in any status.
$coming_up = array();
$coming_up[] = array(_('Domains'), 'domains', '/domains/index.php',
    DomainMOD\Dashboard::dueCounts($pdo, 'domains', $coming_up_ends), $coming_up_ends,
    function ($count) { return sprintf(ngettext('%s domain', '%s domains', $count), number_format($count)); });
if ($total_hosting) {
    $coming_up[] = array(_('Hosting Services'), 'hosting', '/hosting/index.php',
        DomainMOD\Dashboard::dueCounts($pdo, 'hosting', $hosting_ends), $hosting_ends,
        function ($count) { return sprintf(ngettext('%s hosting service', '%s hosting services', $count), number_format($count)); });
}
$coming_up[] = array(_('SSL Certificates'), 'ssl', '/ssl/index.php',
    DomainMOD\Dashboard::dueCounts($pdo, 'ssl', $coming_up_ends), $coming_up_ends,
    function ($count) { return sprintf(ngettext('%s SSL certificate', '%s SSL certificates', $count), number_format($count)); });

$coming_up_total = 0;
foreach ($coming_up as $row) $coming_up_total += array_sum($row[3]);

if ($coming_up_total) { ?>
    <div class="col-xl">
        <section class="card card-outline card-danger domainmod-dashboard-block h-100 mb-0" aria-labelledby="domainmod-coming-up-heading">
            <div class="card-header domainmod-dashboard-block-header">
                <h2 class="card-title" id="domainmod-coming-up-heading"><?php echo _('Coming Up'); ?></h2>
                <span class="domainmod-dashboard-block-note"><?php echo _('Renewals due, including overdue ones'); ?></span>
            </div>
            <div class="table-responsive">
                <table class="table mb-0 domainmod-coming-up">
                    <thead>
                        <tr>
                            <th scope="col"><span class="visually-hidden"><?php echo _('Type'); ?></span></th><?php
                            foreach ($coming_up_windows as $window) { ?>
                            <th scope="col"><?php echo sprintf(ngettext('%s day', '%s days', $window), number_format($window)); ?></th><?php
                            } ?>
                        </tr>
                    </thead>
                    <tbody><?php
                    foreach ($coming_up as $row) { ?>
                        <tr>
                            <th scope="row"><span class="domainmod-type-dot domainmod-type-dot-<?php echo $row[1]; ?>" aria-hidden="true"></span><?php echo $row[0]; ?></th><?php
                            foreach ($coming_up_windows as $window) {
                                $count = $row[3][$window];
                                if ($count) {
                                    $url = $row[2] . '?daterange=' . urlencode(DomainMOD\Dashboard::DUE_FROM . ' - ' . $row[4][$window]); ?>
                            <td><a href="<?php echo WEB_ROOT . $url; ?>" class="link-underline-opacity-0 link-underline-opacity-75-hover"><span aria-hidden="true"><?php echo number_format($count); ?></span><span class="visually-hidden"><?php echo sprintf(_('%1$s, %2$s'), $row[5]($count), sprintf(ngettext('due within %s day', 'due within %s days', $window), number_format($window))); ?></span></a></td><?php
                                } else { ?>
                            <td class="domainmod-coming-up-zero">0</td><?php
                                }
                            } ?>
                        </tr><?php
                    } ?>
                    </tbody>
                </table>
            </div>
        </section>
    </div><?php
} ?>
</div><?php

////////////////////////////////////////////////////////////////////////////////////////////////////
// Pending and Import Queue, side by side (stacked on a phone), as pills with counts, each shown only with a count
////////////////////////////////////////////////////////////////////////////////////////////////////
// Domains, Hosting, then SSL Certificates, each type's statuses in the order the old tiles had
$pending = array(
    array(_('Domain renewals'), $domain_counts[3] ?? 0, '/domains/index.php?is_active=3'),
    array(_('Domain registrations'), $domain_counts[5] ?? 0, '/domains/index.php?is_active=5'),
    array(_('Domain transfers'), $domain_counts[2] ?? 0, '/domains/index.php?is_active=2'),
    array(_('Domain other'), $domain_counts[4] ?? 0, '/domains/index.php?is_active=4'),
    array(_('Hosting renewals'), $hosting_counts[3] ?? 0, '/hosting/index.php?is_active=3'),
    array(_('Hosting setup'), $hosting_counts[5] ?? 0, '/hosting/index.php?is_active=5'),
    array(_('Hosting other'), $hosting_counts[4] ?? 0, '/hosting/index.php?is_active=4'),
    array(_('SSL renewals'), $ssl_counts[3] ?? 0, '/ssl/index.php?is_active=3'),
    array(_('SSL registrations'), $ssl_counts[5] ?? 0, '/ssl/index.php?is_active=5'),
    array(_('SSL other'), $ssl_counts[4] ?? 0, '/ssl/index.php?is_active=4'));

// The Domain Queue's counts and the SSL Queue's hostnames waiting (once the database update has added it) together, as
// the Import Queue page shows both queues
$total_count_processing = (int) $pdo->query("
    SELECT count(*)
    FROM domain_queue
    WHERE processing = '1'
      AND finished != '1'")->fetchColumn();

$total_count_pending = (int) $pdo->query("
    SELECT count(*)
    FROM domain_queue
    WHERE processing = '0'
      AND finished != '1'")->fetchColumn();

$total_count_finished = (int) $pdo->query("
    SELECT count(*)
    FROM domain_queue
    WHERE finished = '1'")->fetchColumn();

$ssl_queue = new DomainMOD\SslQueue();
$total_count_ssl_queue = $ssl_queue->isAvailable() ? (int) $ssl_queue->countUnfinished() : 0;

$import_queue = array(
    array(_('Domains pending'), $total_count_pending, '/queue/'),
    array(_('Domains processing'), $total_count_processing, '/queue/'),
    array(_('Domains finished'), $total_count_finished, '/queue/'),
    array(_('SSL waiting'), $total_count_ssl_queue, '/queue/'));

$pill_blocks = array();
foreach (array(array('pending', _('Pending'), $pending), array('import-queue', _('Import Queue'), $import_queue)) as $block) {
    $pills = array_values(array_filter($block[2], function ($pill) { return $pill[1] > 0; }));
    if ($pills) $pill_blocks[] = array($block[0], $block[1], $pills);
}

if ($pill_blocks) { ?>
<div class="row g-4 mt-0"><?php
    foreach ($pill_blocks as $block) { ?>
    <div class="col-md">
        <section class="card card-outline card-danger domainmod-dashboard-block h-100 mb-0" aria-labelledby="domainmod-<?php echo $block[0]; ?>-heading">
            <div class="card-header domainmod-dashboard-block-header">
                <h2 class="card-title" id="domainmod-<?php echo $block[0]; ?>-heading"><?php echo $block[1]; ?></h2>
            </div>
            <div class="card-body">
                <ul class="domainmod-dashboard-pills list-unstyled d-flex flex-wrap mb-0"><?php
                foreach ($block[2] as $pill) { ?>
                    <li><a href="<?php echo WEB_ROOT . $pill[2]; ?>" class="domainmod-dashboard-pill"><?php echo $pill[0]; ?> <span class="domainmod-dashboard-pill-count"><?php echo number_format($pill[1]); ?></span></a></li><?php
                } ?>
                </ul>
            </div>
        </section>
    </div><?php
    } ?>
</div><?php
}

////////////////////////////////////////////////////////////////////////////////////////////////////
// Renewal Costs, Next 12 Months, at the bottom of the page (Greg's choice, 2026-11-21, away from the other data):
// Domain Renewals' bars, Hosting's payments once the hosting tables exist, and SSL Renewals' bars, stacked in that
// order, so each month's bar is the total, with the figures as a table under the chart
////////////////////////////////////////////////////////////////////////////////////////////////////
if ($renewal_rows || $renewal_cert_rows || $renewal_hosting_months) {

    $renewal_months = $reporting->renewalMonthsBoth($renewal_rows, $renewal_cert_rows, $today,
        $hosting_available ? $renewal_hosting_months : null);
    ob_start(); ?>

    <?php // The figures toggle and the report links share one row, so the card doesn't end with a gap under the toggle (on a phone, open figures can push the links below them, at the end side) ?>
    <div class="d-flex flex-wrap align-items-start gap-2 mt-3">
        <details class="flex-grow-1">
            <summary><?php echo _('Show the figures'); ?></summary>
            <div class="table-responsive">
                <?php echo $reporting->renewalMonthsBothTable($renewal_months); ?>
            </div>
        </details>
        <div class="ms-auto d-flex flex-wrap column-gap-3">
            <a href="<?php echo WEB_ROOT; ?>/reporting/domains/upcoming-renewals.php" class="link-underline-opacity-0 link-underline-opacity-75-hover"><?php echo _('Domain Renewals'); ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></a><?php
            if ($hosting_available) { ?>
                <a href="<?php echo WEB_ROOT; ?>/reporting/hosting/upcoming-renewals.php" class="link-underline-opacity-0 link-underline-opacity-75-hover"><?php echo _('Hosting Renewals'); ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></a><?php
            } ?>
            <a href="<?php echo WEB_ROOT; ?>/reporting/ssl/upcoming-renewals.php" class="link-underline-opacity-0 link-underline-opacity-75-hover"><?php echo _('SSL Renewals'); ?> <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        </div>
    </div><?php

    $after = ob_get_clean();

    $renewal_series = array(
        array('name' => _('Domains'), 'values' => array_column(array_column($renewal_months, 'domains'), 'cost'), 'colour' => 'domains'));
    if ($hosting_available) {
        $renewal_series[] = array('name' => _('Hosting'), 'values' => array_column(array_column($renewal_months, 'hosting'), 'cost'), 'colour' => 'hosting');
    }
    $renewal_series[] = array('name' => _('SSL Certificates'), 'values' => array_column(array_column($renewal_months, 'certs'), 'cost'), 'colour' => 'ssl');
    // The chart's own card title is its heading, as the blocks above have theirs
    echo $chart->stackedMonthBars('dashboard-renewal-costs', _('Renewal Costs, Next 12 Months'), array_column($renewal_months, 'label'),
        $renewal_series, !empty($renewal_months[0]['overdue']), $after);

    // The chart's card ends the page, so the footer skips its usual gap above it
    $footer_no_gap = true;

}
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
<?php require_once DIR_INC . '/layout/chart-footer.inc.php'; ?>
</body>
</html>
