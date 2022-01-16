<?php
/**
 * /dashboard/index.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2022 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: http://chetcuti.com
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

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/dashboard-main.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm select2-red<?php echo $layout->bodyDarkMode(); ?>">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
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

if ($total_count_domains || $total_count_ssl) { ?>

    <div class="row">
        <h4>&nbsp;&nbsp;<?php echo sprintf(ngettext('Expiring in the next day', 'Expiring in the next %s days', $expiration_days), $expiration_days); ?></h4>
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

        echo $dashboard->displayExpPanel(_('Domains'), $total_count, '/domains/index.php?daterange=' . urlencode($daterange));

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

        echo $dashboard->displayExpPanel(_('SSL Certs'), $total_count, '/ssl/index.php?daterange=' . urlencode($daterange));

    } ?>

    </div><?php

}

////////////////////////////////////////////////////////////////////////////////////////////////////
// System Totals
////////////////////////////////////////////////////////////////////////////////////////////////////
?>
<div class="row">
    <h4>&nbsp;&nbsp;<?php echo _('System Totals'); ?></h4>
</div>

<div class="row">

    <?php
    //////////////////////////////////////////////////
    // Active Domains
    //////////////////////////////////////////////////
    $total_count = $pdo->query("
        SELECT count(*)
        FROM domains
        WHERE active NOT IN ('0', '10')")->fetchColumn();

    echo $dashboard->displayPanel(_('Domains'), $total_count, 'green', 'checkmark-circled', '/domains/index.php?is_active=LIVE');

    //////////////////////////////////////////////////
    // Active SSL Certificates
    //////////////////////////////////////////////////
    $total_count = $pdo->query("
        SELECT count(*)
        FROM ssl_certs
        WHERE active NOT IN ('0', '10')")->fetchColumn();

    echo $dashboard->displayPanel(_('SSL Certificates'), $total_count, 'green', 'checkmark-circled', '/ssl/index.php?is_active=LIVE');

    //////////////////////////////////////////////////
    // Sold Domains
    //////////////////////////////////////////////////
    $total_count = $pdo->query("
        SELECT count(*)
        FROM domains
        WHERE active = '10'")->fetchColumn();

    if ($total_count) {

        echo $dashboard->displayPanel(_('Sold Domains'), $total_count, 'info', 'android-cart', '/domains/index.php?is_active=10');

    } ?>

</div>
<?php
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

    <div class="row">
        <h4><?php echo _('Domain Queue'); ?></h4>
    </div>

    <div class="row">

        <?php
        //////////////////////////////////////////////////
        // Pending
        //////////////////////////////////////////////////
        if ($total_count_pending) {

            echo $dashboard->displayPanel(_('Pending'), $total_count_pending, 'yellow', 'clock', '/queue/');

        }

        //////////////////////////////////////////////////
        // Processing
        //////////////////////////////////////////////////
        if ($total_count_processing) {

            echo $dashboard->displayPanel(_('Processing'), $total_count_processing, 'yellow', 'clock', '/queue/');

        }

        //////////////////////////////////////////////////
        // Finished
        //////////////////////////////////////////////////
        if ($total_count_finished) {

            echo $dashboard->displayPanel(_('Finished'), $total_count_finished, 'green', 'checkmark-circled', '/queue/');

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

    <div class="row">
        <h4>&nbsp;&nbsp;<?php echo _('Pending (Domains)'); ?></h4>
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

            echo $dashboard->displayPanel(_('Pending Renewals'), $total_count, 'red', 'clock', '/domains/index.php?is_active=3');

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

            echo $dashboard->displayPanel(_('Pending Registrations'), $total_count, 'yellow', 'clock', '/domains/index.php?is_active=5');

        }

        //////////////////////////////////////////////////
        // Pending Transfers (Domains)
        //////////////////////////////////////////////////
        $total_count = $pdo->query("
            SELECT count(*)
            FROM domains
            WHERE active = '2'")->fetchColumn();

        if ($total_count) {

            echo $dashboard->displayPanel(_('Pending Transfers'), $total_count, 'info', 'clock', '/domains/index.php?is_active=2');

        }

        //////////////////////////////////////////////////
        // Pending Other (Domains)
        //////////////////////////////////////////////////
        $total_count = $pdo->query("
            SELECT count(*)
            FROM domains
            WHERE active = '4'")->fetchColumn();

        if ($total_count) {

            echo $dashboard->displayPanel(_('Pending (Other)'), $total_count, 'green', 'clock', '/domains/index.php?is_active=4');

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

    <div class="row">
        <h4>&nbsp;&nbsp;<?php echo _('Pending (SSL Certificates)'); ?></h4>
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

            echo $dashboard->displayPanel(_('Pending Renewals'), $total_count, 'red', 'clock', '/ssl/index.php?is_active=3');

        }

        //////////////////////////////////////////////////
        // Pending Registrations (SSL Certificates)
        //////////////////////////////////////////////////
        $total_count = $pdo->query("
            SELECT count(*)
            FROM ssl_certs
            WHERE active = '5'")->fetchColumn();

        if ($total_count) {

            echo $dashboard->displayPanel(_('Pending Registrations'), $total_count, 'yellow', 'clock', '/ssl/index.php?is_active=5');

        }

        //////////////////////////////////////////////////
        // Pending Other (SSL Certificates)
        //////////////////////////////////////////////////
        $total_count = $pdo->query("
            SELECT count(*)
            FROM ssl_certs
            WHERE active = '4'")->fetchColumn();

        if ($total_count) {

            echo $dashboard->displayPanel(_('Pending (Other)'), $total_count, 'green', 'clock', '/ssl/index.php?is_active=4');

        } ?>

    </div><?php

} ?>

<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
