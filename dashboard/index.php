<?php
/**
 * /dashboard/index.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2016 Greg Chetcuti <greg@chetcuti.com>
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
include("../_includes/start-session.inc.php");
include("../_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();
$error = new DomainMOD\Error();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "settings/dashboard-main.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();
?>
<?php include(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
    <?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body class="hold-transition skin-red sidebar-mini" onLoad="document.forms[0].elements[0].focus()">
<?php include(DIR_INC . "layout/header.inc.php"); ?>

<!-- Small boxes (Stat box) -->
<div class="row">

    <!-- Main Boxes -->
    <div class="col-lg-3 col-xs-6">
        <!-- small box -->
        <div class="small-box bg-green">
            <div class="inner">
                <?php
                $sql = "SELECT count(*) AS total_count
                        FROM domains
                        WHERE active NOT IN ('0', '10')";
                $result = mysqli_query($connection, $sql);
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
                $result = mysqli_query($connection, $sql);
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
                $result = mysqli_query($connection, $sql);
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
                $result = mysqli_query($connection, $sql);
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
                $result = mysqli_query($connection, $sql);
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
                $result = mysqli_query($connection, $sql);
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
                $result = mysqli_query($connection, $sql);
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
                $result = mysqli_query($connection, $sql);
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
                $result = mysqli_query($connection, $sql);
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
                $result = mysqli_query($connection, $sql);
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

<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
