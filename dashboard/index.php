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

// The domains renewing in the renewal cost chart's 12 months (overdue ones included), shared with the At-Risk tile and
// the Upcoming Renewals and At-Risk Domains reports
$today = $time->timeBasic();
$renewal_rows = $reporting->renewalDomains($reporting->renewalChartEnd($today));
$at_risk_count = count($reporting->atRiskDomains($renewal_rows, $today, $time->timeBasicPlusDays(DomainMOD\Reporting::AT_RISK_DAYS)));
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
// Totals, as one line under the page title (Greg's choice, 2026-11-14), so the action cards come first
////////////////////////////////////////////////////////////////////////////////////////////////////
$total_live_domains = (int) $pdo->query("
    SELECT count(*)
    FROM domains
    WHERE active NOT IN ('0', '10')")->fetchColumn();

$total_live_ssl = (int) $pdo->query("
    SELECT count(*)
    FROM ssl_certs
    WHERE active NOT IN ('0', '10')")->fetchColumn();

$total_sold_domains = (int) $pdo->query("
    SELECT count(*)
    FROM domains
    WHERE active = '10'")->fetchColumn();

// On an install with no domains yet, the Getting Started checklist takes the totals' place, since they'd only be zeros
// (Greg's choice, 2026-11-15)
$show_getting_started = $dashboard->showGettingStarted();

// Administrators are warned when cron.php has stopped running (or, once there are domains, has never run)
echo $schedule->healthAlert('dashboard', $show_getting_started);

$totals = array();
$totals[] = array('/domains/index.php?is_active=LIVE', 'bi-globe2', number_format($total_live_domains), _('Domains'));
$totals[] = array('/ssl/index.php?is_active=LIVE', 'bi-shield-lock', number_format($total_live_ssl), _('SSL Certificates'));
if ($total_sold_domains) {
    $totals[] = array('/domains/index.php?is_active=10', 'bi-cart', number_format($total_sold_domains), _('Sold Domains'));
}
if (!$show_getting_started) { ?>
<div class="card domainmod-dashboard-totals d-inline-block mw-100 mb-0">
    <div class="d-flex flex-column flex-sm-row"><?php
        foreach ($totals as $total) { ?>
            <div class="domainmod-dashboard-total position-relative d-flex align-items-center gap-3 px-4 py-2">
                <i class="bi <?php echo $total[1]; ?> fs-4" aria-hidden="true"></i>
                <div>
                    <div class="fs-5 fw-semibold lh-1"><?php echo $total[2]; ?></div>
                    <a href="<?php echo WEB_ROOT . $total[0]; ?>" class="stretched-link text-body-secondary link-underline-opacity-0 small"><?php echo $total[3]; ?></a>
                </div>
            </div><?php
        } ?>
    </div>
</div><?php
} else {
    // The Getting Started checklist, on an install with no domains yet
    echo $dashboard->gettingStarted();
}
////////////////////////////////////////////////////////////////////////////////////////////////////
// Expiration Panels
////////////////////////////////////////////////////////////////////////////////////////////////////
$expiration_days = $pdo->query("
    SELECT expiration_days
    FROM settings")->fetchColumn();

$start_date = '2000-01-01';
$end_date = $time->timeBasicPlusDays($expiration_days);
$daterange = $start_date . ' - ' . $end_date;

$total_count_domains = $pdo->query("
    SELECT count(*)
    FROM domains
    WHERE active NOT IN ('0', '10')
      AND expiry_date <= '" . $end_date . "'")->fetchColumn();

$total_count_ssl = $pdo->query("
    SELECT count(*)
    FROM ssl_certs AS sslc, ssl_cert_types AS sslt
    WHERE sslc.type_id = sslt.id
      AND sslc.active NOT IN ('0')
      AND sslc.expiry_date <= '" . $end_date . "'")->fetchColumn();

if ($total_count_domains || $total_count_ssl || $at_risk_count) { ?>

    <?php $section = sprintf(ngettext('Expiring in the next day', 'Expiring in the next %s days', $expiration_days), $expiration_days); ?>
    <div class="row">
        <h2 class="h4 mt-4 pt-2"><?php echo $section; ?></h2>
    </div>

    <div class="row">

    <?php
    //////////////////////////////////////////////////
    // Expiring Domains
    //////////////////////////////////////////////////
    $total_count = $pdo->query("
                SELECT count(*)
                FROM domains
                WHERE active NOT IN ('0', '10')
                  AND expiry_date <= '" . $end_date . "'")->fetchColumn();

    if ($total_count) {

        echo $dashboard->displayExpPanel(_('Domains'), $total_count, '/domains/index.php?daterange=' . urlencode($daterange), $section);

    }

    //////////////////////////////////////////////////
    // Expiring SSL Certificates
    //////////////////////////////////////////////////
    $total_count = $pdo->query("
                SELECT count(*)
                FROM ssl_certs AS sslc, ssl_cert_types AS sslt
                WHERE sslc.type_id = sslt.id
                  AND sslc.active NOT IN ('0')
                  AND sslc.expiry_date <= '" . $end_date . "'")->fetchColumn();

    if ($total_count) {

        echo $dashboard->displayExpPanel(_('SSL Certificates'), $total_count, '/ssl/index.php?daterange=' . urlencode($daterange), $section);

    }

    //////////////////////////////////////////////////
    // At-Risk Domains (its own fixed 30 days, which the tile's title says, beside this row's Expiration Days range)
    //////////////////////////////////////////////////
    if ($at_risk_count) {

        echo $dashboard->displayPanel(sprintf(_('At Risk (%s days)'), DomainMOD\Reporting::AT_RISK_DAYS), $at_risk_count, 'danger', 'bi-exclamation-triangle', '/reporting/domains/at-risk.php', $section);

    } ?>

    </div><?php

}

////////////////////////////////////////////////////////////////////////////////////////////////////
// Domain Panels
////////////////////////////////////////////////////////////////////////////////////////////////////
$total_count = $pdo->query("
    SELECT count(*)
    FROM domains
    WHERE active IN ('3', '5', '2', '4')")->fetchColumn();

if ($total_count) { ?>

    <?php $section = _('Pending Domains'); ?>
    <div class="row">
        <h2 class="h4 mt-4 pt-2"><?php echo $section; ?></h2>
    </div>

    <div class="row">

        <?php
        //////////////////////////////////////////////////
        // Pending Renewals (Domains)
        //////////////////////////////////////////////////
        $total_count = $pdo->query("
            SELECT count(*)
            FROM domains
            WHERE active = '3'")->fetchColumn();

        if ($total_count) {

            echo $dashboard->displayPanel(_('Renewals'), $total_count, 'warning', 'bi-arrow-repeat', '/domains/index.php?is_active=3', $section);

        } ?>

        <?php
        //////////////////////////////////////////////////
        // Pending Registrations (Domains)
        //////////////////////////////////////////////////
        $total_count = $pdo->query("
            SELECT count(*)
            FROM domains
            WHERE active = '5'")->fetchColumn();

        if ($total_count) {

            echo $dashboard->displayPanel(_('Registrations'), $total_count, 'warning', 'bi-plus-circle', '/domains/index.php?is_active=5', $section);

        }

        //////////////////////////////////////////////////
        // Pending Transfers (Domains)
        //////////////////////////////////////////////////
        $total_count = $pdo->query("
            SELECT count(*)
            FROM domains
            WHERE active = '2'")->fetchColumn();

        if ($total_count) {

            echo $dashboard->displayPanel(_('Transfers'), $total_count, 'warning', 'bi-arrow-left-right', '/domains/index.php?is_active=2', $section);

        }

        //////////////////////////////////////////////////
        // Pending Other (Domains)
        //////////////////////////////////////////////////
        $total_count = $pdo->query("
            SELECT count(*)
            FROM domains
            WHERE active = '4'")->fetchColumn();

        if ($total_count) {

            echo $dashboard->displayPanel(_('Other'), $total_count, 'warning', 'bi-three-dots', '/domains/index.php?is_active=4', $section);

        } ?>

    </div><?php

}

////////////////////////////////////////////////////////////////////////////////////////////////////
// SSL Certificate Panels
////////////////////////////////////////////////////////////////////////////////////////////////////
$total_count = $pdo->query("
    SELECT count(*)
    FROM ssl_certs
    WHERE active IN ('3', '5', '4')")->fetchColumn();

if ($total_count) { ?>

    <?php $section = _('Pending SSL Certificates'); ?>
    <div class="row">
        <h2 class="h4 mt-4 pt-2"><?php echo $section; ?></h2>
    </div>

    <div class="row">

        <?php
        //////////////////////////////////////////////////
        // Pending Renewals (SSL Certificates)
        //////////////////////////////////////////////////
        $total_count = $pdo->query("
            SELECT count(*)
            FROM ssl_certs
            WHERE active = '3'")->fetchColumn();

        if ($total_count) {

            echo $dashboard->displayPanel(_('Renewals'), $total_count, 'warning', 'bi-arrow-repeat', '/ssl/index.php?is_active=3', $section);

        }

        //////////////////////////////////////////////////
        // Pending Registrations (SSL Certificates)
        //////////////////////////////////////////////////
        $total_count = $pdo->query("
            SELECT count(*)
            FROM ssl_certs
            WHERE active = '5'")->fetchColumn();

        if ($total_count) {

            echo $dashboard->displayPanel(_('Registrations'), $total_count, 'warning', 'bi-plus-circle', '/ssl/index.php?is_active=5', $section);

        }

        //////////////////////////////////////////////////
        // Pending Other (SSL Certificates)
        //////////////////////////////////////////////////
        $total_count = $pdo->query("
            SELECT count(*)
            FROM ssl_certs
            WHERE active = '4'")->fetchColumn();

        if ($total_count) {

            echo $dashboard->displayPanel(_('Other'), $total_count, 'warning', 'bi-three-dots', '/ssl/index.php?is_active=4', $section);

        } ?>

    </div><?php

}

////////////////////////////////////////////////////////////////////////////////////////////////////
// Domain Queue
////////////////////////////////////////////////////////////////////////////////////////////////////
$total_count_processing = $pdo->query("
    SELECT count(*)
    FROM domain_queue
    WHERE processing = '1'
      AND finished != '1'")->fetchColumn();

$total_count_pending = $pdo->query("
    SELECT count(*)
    FROM domain_queue
    WHERE processing = '0'
      AND finished != '1'")->fetchColumn();

$total_count_finished = $pdo->query("
    SELECT count(*)
    FROM domain_queue
    WHERE finished = '1'")->fetchColumn();

if ($total_count_processing || $total_count_pending || $total_count_finished) { ?>

    <?php $section = _('Domain Queue'); ?>
    <div class="row">
        <h2 class="h4 mt-4 pt-2"><?php echo $section; ?></h2>
    </div>

    <div class="row">

        <?php
        //////////////////////////////////////////////////
        // Pending
        //////////////////////////////////////////////////
        if ($total_count_pending) {

            echo $dashboard->displayPanel(_('Pending'), $total_count_pending, 'warning', 'bi-hourglass-split', '/queue/', $section);

        }

        //////////////////////////////////////////////////
        // Processing
        //////////////////////////////////////////////////
        if ($total_count_processing) {

            echo $dashboard->displayPanel(_('Processing'), $total_count_processing, 'warning', 'bi-gear', '/queue/', $section);

        }

        //////////////////////////////////////////////////
        // Finished
        //////////////////////////////////////////////////
        if ($total_count_finished) {

            echo $dashboard->displayPanel(_('Finished'), $total_count_finished, 'success', 'bi-check-circle-fill', '/queue/', $section);

        } ?>

    </div><?php
}
?>
<?php
////////////////////////////////////////////////////////////////////////////////////////////////////
// Renewal Costs, Next 12 Months (the same bars as Upcoming Renewals, with its figures as a table under the chart)
////////////////////////////////////////////////////////////////////////////////////////////////////
if ($renewal_rows) {

    $renewal_months = $reporting->renewalMonths($renewal_rows, $today);
    ob_start(); ?>

    <?php // The figures toggle and the View link share one row, so the card doesn't end with a gap under the toggle (on a phone, open figures can push the link below them, at the end side) ?>
    <div class="d-flex flex-wrap align-items-start gap-2 mt-3">
        <details class="flex-grow-1">
            <summary><?php echo _('Show the figures'); ?></summary>
            <?php echo $reporting->renewalMonthsTable($renewal_months); ?>
        </details>
        <a href="<?php echo WEB_ROOT; ?>/reporting/domains/upcoming-renewals.php" class="ms-auto link-underline-opacity-0 link-underline-opacity-75-hover"><?php echo _('View'); ?><span class="visually-hidden"> <?php echo _('Upcoming Renewals'); ?></span> <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
    </div><?php

    $after = ob_get_clean(); ?>

    <div class="row">
        <h2 class="h4 mt-4 pt-2"><?php echo _('Renewal Costs, Next 12 Months'); ?></h2>
    </div>
    <?php echo $chart->bar('dashboard-renewal-costs', _('Renewal Costs, Next 12 Months'), array_column($renewal_months, 'label'), array_column($renewal_months, 'cost'), !empty($renewal_months[0]['overdue']), $after, false);

    // The chart's card ends the page, so the footer skips its usual gap above it
    $footer_no_gap = true;

}
?>

<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
<?php require_once DIR_INC . '/layout/chart-footer.inc.php'; ?>
</body>
</html>
