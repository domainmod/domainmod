<?php
/**
 * /dashboard/index.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>
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

require_once DIR_ROOT . '/vendor/autoload.php';

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$time = new DomainMOD\Time();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/dashboard-main.inc.php';
require_once DIR_INC . '/database.inc.php';

$pdo = $system->db();
$system->authCheck();
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $system->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition skin-red sidebar-mini" onLoad="document.forms[0].elements[0].focus()">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>

<!-- Small boxes (Stat box) -->
<div class="row">

    <!-- Expiring Boxes -->
    <?php
    $expiration_days = $pdo->query("
        SELECT expiration_days
        FROM settings")->fetchColumn();

    $start_date = '2000-01-01';
    $end_date = $time->timeBasicPlusDays($expiration_days);
    $daterange = $start_date . ' - ' . $end_date;
    ?>
    <h3 style="padding-left:20px;">Expiring in the next <?php echo $expiration_days; ?> days</h3>
    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-red">
            <div class="inner">
                <?php
                $stmt = $pdo->prepare("
                    SELECT id, expiry_date, domain
                    FROM domains
                    WHERE active NOT IN ('0', '10')
                      AND expiry_date <= :end_date
                    ORDER BY expiry_date, domain");
                $stmt->bindValue('end_date', $end_date, PDO::PARAM_STR);
                $stmt->execute();
                $result = $stmt->fetchAll();

                if (!$result) {

                    $to_display = '0';

                } else {

                    $stmt = $pdo->prepare("
                        SELECT count(*)
                        FROM domains
                        WHERE active NOT IN ('0', '10')
                          AND expiry_date <= :end_date");
                    $stmt->bindValue('end_date', $end_date, PDO::PARAM_STR);
                    $stmt->execute();
                    $to_display = $stmt->fetchColumn();

                }
                ?>
                <h3><?php echo number_format($to_display); ?></h3>
                <p>Domains</p>
            </div>
            <div class="icon">
                <i class="ion ion-close-circled" style="padding-top:16px;"></i>
            </div>
            <a href="<?php echo $web_root; ?>/domains/index.php?daterange=<?php echo urlencode($daterange); ?>" class="small-box-footer">View <i
                    class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <!-- ./col -->

    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-red">
            <div class="inner">
                <?php
                $stmt = $pdo->prepare("
                    SELECT sslc.id, sslc.expiry_date, sslc.name, sslt.type
                    FROM ssl_certs AS sslc, ssl_cert_types AS sslt
                    WHERE sslc.type_id = sslt.id
                      AND sslc.active NOT IN ('0')
                      AND sslc.expiry_date <= :end_date
                    ORDER BY sslc.expiry_date, sslc.name");
                $stmt->bindValue('end_date', $end_date, PDO::PARAM_STR);
                $stmt->execute();
                $result = $stmt->fetchAll();

                if (!$result) {

                    $to_display = '0';

                } else {

                    $stmt = $pdo->prepare("
                        SELECT count(*)
                        FROM ssl_certs AS sslc, ssl_cert_types AS sslt
                        WHERE sslc.type_id = sslt.id
                          AND sslc.active NOT IN ('0')
                          AND sslc.expiry_date <= :end_date");
                    $stmt->bindValue('end_date', $end_date, PDO::PARAM_STR);
                    $stmt->execute();
                    $to_display = $stmt->fetchColumn();

                }
                ?>
                <h3><?php echo number_format($to_display); ?></h3>
                <p>SSL Certificates</p>
            </div>
            <div class="icon">
                <i class="ion ion-close-circled" style="padding-top:16px;"></i>
            </div>
            <a href="<?php echo $web_root; ?>/ssl/index.php?daterange=<?php echo urlencode($daterange); ?>" class="small-box-footer">View <i
                    class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <!-- ./col -->

</div>

<div class="row">

    <!-- Main Boxes -->
    <h3 style="padding-left:20px;">System Totals</h3>


    <?php // only display the queue widget if there are results
    $sql = "SELECT id
            FROM domain_queue
            LIMIT 1";
    $result = mysqli_query($dbcon, $sql);

    if (mysqli_num_rows($result) > 0) { ?>

        <!-- ./col -->
        <div class="col-lg-3 col-xs-6">
            <!-- small box -->
            <div class="small-box bg-yellow">
                <div class="inner">
                    <?php
                    $sql = "SELECT count(*) AS total_count
                            FROM domain_queue";
                    $result = mysqli_query($dbcon, $sql);
                    while ($row = mysqli_fetch_object($result)) {
                        $total_count = $row->total_count;
                    }
                    ?>
                    <h3><?php echo number_format($total_count); ?></h3>

                    <p>Domains in Queue</p>
                </div>
                <div class="icon">
                    <i class="ion ion-clock" style="padding-top:16px;"></i>
                </div>
                <a href="<?php echo $web_root; ?>/queue/" class="small-box-footer">View <i
                        class="fa fa-arrow-circle-right"></i></a>
            </div>
        </div>
        <!-- ./col --><?php

    } ?>

    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-green">
            <div class="inner">
                <?php
                $sql = "SELECT count(*) AS total_count
                        FROM domains
                        WHERE active NOT IN ('0', '10')";
                $result = mysqli_query($dbcon, $sql);
                while ($row = mysqli_fetch_object($result)) {
                    $total_count = $row->total_count;
                }
                ?>
                <h3><?php echo number_format($total_count); ?></h3>

                <p>Domains</p>
            </div>
            <div class="icon">
                <i class="ion ion-checkmark-circled" style="padding-top:17px;"></i>
            </div>
            <a href="<?php echo $web_root; ?>/domains/index.php?is_active=LIVE" class="small-box-footer">View <i
                    class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-green">
            <div class="inner">
                <?php
                $sql = "SELECT count(*) AS total_count
                        FROM ssl_certs
                        WHERE active NOT IN ('0', '10')";
                $result = mysqli_query($dbcon, $sql);
                while ($row = mysqli_fetch_object($result)) {
                    $total_count = $row->total_count;
                }
                ?>
                <h3><?php echo number_format($total_count); ?></h3>

                <p>SSL Certificates</p>
            </div>
            <div class="icon">
                <i class="ion ion-checkmark-circled" style="padding-top:17px;"></i>
            </div>
            <a href="<?php echo $web_root; ?>/ssl/index.php?is_active=LIVE" class="small-box-footer">View <i
                    class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>

    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-aqua">
            <div class="inner">
                <?php
                $sql = "SELECT count(*) AS total_count
                        FROM domains
                        WHERE active = '10'";
                $result = mysqli_query($dbcon, $sql);
                while ($row = mysqli_fetch_object($result)) {
                    $total_count = $row->total_count;
                }
                ?>
                <h3><?php echo number_format($total_count); ?></h3>
                <p>Sold Domains</p>
            </div>
            <div class="icon">
                <i class="ion ion-android-cart" style="padding-top:17px;"></i>
            </div>
            <a href="<?php echo $web_root; ?>/domains/index.php?is_active=10" class="small-box-footer">View <i
                    class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <!-- ./col -->

</div>

<!-- Small boxes (Stat box) -->
<div class="row">

    <!-- Domain Panels -->
    <h3 style="padding-left:20px;">Domains</h3>
    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-red">
            <div class="inner">
                <?php
                $sql = "SELECT count(*) AS total_count
                        FROM domains
                        WHERE active = '3'";
                $result = mysqli_query($dbcon, $sql);
                while ($row = mysqli_fetch_object($result)) {
                    $total_count = $row->total_count;
                }
                ?>
                <h3><?php echo number_format($total_count); ?></h3>

                <p>Pending Renewals</p>
            </div>
            <div class="icon">
                <i class="ion ion-clock" style="padding-top:16px;"></i>
            </div>
            <a href="<?php echo $web_root; ?>/domains/index.php?is_active=3" class="small-box-footer">View <i
                    class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <!-- ./col -->

    <!-- ./col -->
    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-yellow">
            <div class="inner">
                <?php
                $sql = "SELECT count(*) AS total_count
                        FROM domains
                        WHERE active = '5'";
                $result = mysqli_query($dbcon, $sql);
                while ($row = mysqli_fetch_object($result)) {
                    $total_count = $row->total_count;
                }
                ?>
                <h3><?php echo number_format($total_count); ?></h3>

                <p>Pending Registrations</p>
            </div>
            <div class="icon">
                <i class="ion ion-clock" style="padding-top:16px;"></i>
            </div>
            <a href="<?php echo $web_root; ?>/domains/index.php?is_active=5" class="small-box-footer">View <i
                    class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <!-- ./col -->

    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-aqua">
            <div class="inner">
                <?php
                $sql = "SELECT count(*) AS total_count
                        FROM domains
                        WHERE active = '2'";
                $result = mysqli_query($dbcon, $sql);
                while ($row = mysqli_fetch_object($result)) {
                    $total_count = $row->total_count;
                }
                ?>
                <h3><?php echo number_format($total_count); ?></h3>
                <p>Pending Transfers</p>
            </div>
            <div class="icon">
                <i class="ion ion-clock" style="padding-top:16px;"></i>
            </div>
            <a href="<?php echo $web_root; ?>/domains/index.php?is_active=2" class="small-box-footer">View <i
                    class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <!-- ./col -->

    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-green">
            <div class="inner">
                <?php
                $sql = "SELECT count(*) AS total_count
                        FROM domains
                        WHERE active = '4'";
                $result = mysqli_query($dbcon, $sql);
                while ($row = mysqli_fetch_object($result)) {
                    $total_count = $row->total_count;
                }
                ?>
                <h3><?php echo number_format($total_count); ?></h3>

                <p>Pending (Other)</p>
            </div>
            <div class="icon">
                <i class="ion ion-clock" style="padding-top:16px;"></i>
            </div>
            <a href="<?php echo $web_root; ?>/domains/index.php?is_active=4" class="small-box-footer">View <i
                    class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>

</div>

<div class="row">

    <!-- SSL Certificate Panels -->
    <h3 style="padding-left:20px;">SSL Certificates</h3>
    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-red">
            <div class="inner">
                <?php
                $sql = "SELECT count(*) AS total_count
                        FROM ssl_certs
                        WHERE active = '3'";
                $result = mysqli_query($dbcon, $sql);
                while ($row = mysqli_fetch_object($result)) {
                    $total_count = $row->total_count;
                }
                ?>
                <h3><?php echo number_format($total_count); ?></h3>

                <p>Pending Renewals</p>
            </div>
            <div class="icon">
                <i class="ion ion-clock" style="padding-top:16px;"></i>
            </div>
            <a href="<?php echo $web_root; ?>/ssl/index.php?is_active=3" class="small-box-footer">View <i
                    class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <!-- ./col -->

    <!-- ./col -->
    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-yellow">
            <div class="inner">
                <?php
                $sql = "SELECT count(*) AS total_count
                        FROM ssl_certs
                        WHERE active = '5'";
                $result = mysqli_query($dbcon, $sql);
                while ($row = mysqli_fetch_object($result)) {
                    $total_count = $row->total_count;
                }
                ?>
                <h3><?php echo number_format($total_count); ?></h3>

                <p>Pending Registrations</p>
            </div>
            <div class="icon">
                <i class="ion ion-clock" style="padding-top:16px;"></i>
            </div>
            <a href="<?php echo $web_root; ?>/ssl/index.php?is_active=5" class="small-box-footer">View <i
                    class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>
    <!-- ./col -->

    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-green">
            <div class="inner">
                <?php
                $sql = "SELECT count(*) AS total_count
                        FROM ssl_certs
                        WHERE active = '4'";
                $result = mysqli_query($dbcon, $sql);
                while ($row = mysqli_fetch_object($result)) {
                    $total_count = $row->total_count;
                }
                ?>
                <h3><?php echo number_format($total_count); ?></h3>

                <p>Pending (Other)</p>
            </div>
            <div class="icon">
                <i class="ion ion-clock" style="padding-top:16px;"></i>
            </div>
            <a href="<?php echo $web_root; ?>/ssl/index.php?is_active=4" class="small-box-footer">View <i
                    class="fa fa-arrow-circle-right"></i></a>
        </div>
    </div>

</div>
<!-- /.row -->

<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
