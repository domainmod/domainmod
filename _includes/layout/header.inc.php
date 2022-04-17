<?php
/**
 * /_includes/layout/header.inc.php
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
<!-- Site wrapper -->
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">

        <!-- Left navbar links -->

        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
            <li class="nav-item d-sm-inline-block">
                <a href="<?php echo $web_root; ?>/feedback/" class="nav-link">Feedback</a>
            </li>
<?php /* ?>
            <li class="nav-item d-sm-inline-block">
                <a href="#" class="nav-link">Contact</a>
            </li>
            <li class="nav-item d-sm-inline-block">
                <a href="#" class="nav-link">Contact</a>
            </li>
<?php */ ?>
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto">

            <!-- Navbar Search -->
            <li class="nav-item">
                <a class="nav-link" data-widget="navbar-search" href="#" role="button">
                    <i class="fas fa-search"></i>
                </a>
                <div class="navbar-search-block">
                    <form class="form-inline" action="<?php echo $web_root; ?>/domains/index.php">
                        <div class="input-group input-group-sm">
                            <input class="form-control form-control-navbar" type="search" placeholder="<?php echo _('Domain Search'); ?>" aria-label="<?php echo _('Domain Search'); ?>" name="search_for"<?php if ($search_for && $search_for != '') echo ' value="' . $search_for . '"'; ?>>
                            <div class="input-group-append">
                                <button class="btn btn-navbar" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                                <button class="btn btn-navbar" type="button" data-widget="navbar-search">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </li>
<?php /* ?>
            <!-- Notifications Dropdown Menu -->
            <li class="nav-item dropdown">
                <a class="nav-link" data-toggle="dropdown" href="#">
                    <i class="far fa-bell"></i>
                    <span class="badge badge-warning navbar-badge">15</span>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <span class="dropdown-header">15 Notifications</span>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item">
                        <i class="fas fa-envelope mr-2"></i> 4 new messages
                        <span class="float-right text-muted text-sm">3 mins</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item">
                        <i class="fas fa-users mr-2"></i> 8 friend requests
                        <span class="float-right text-muted text-sm">12 hours</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item">
                        <i class="fas fa-file mr-2"></i> 3 new reports
                        <span class="float-right text-muted text-sm">2 days</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="#" class="dropdown-item dropdown-footer">See All Notifications</a>
                </div>
            </li>
<?php */ ?>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo $web_root; ?>/settings/toggles/dark-mode/" role="button">
                    <i class="fas fa-adjust"></i>
                </a>
            </li>
            <li class="nav-item dropdown user-menu">
                <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">
                    <span><i class="fa fa-user"></i></span>
                </a>
                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
                    <!-- User image -->
                    <li class="user-header bg-red">
                        <p>
                            <?php echo $_SESSION['s_first_name'] . " "; ?> <?php echo $_SESSION['s_last_name']; ?><BR>
                            <?php echo $_SESSION['s_email_address']; ?><BR>
                            <small><BR>
                                <?php echo _('Language'); ?>: <?php echo $_SESSION['s_default_language_name']; ?><BR>
                                <?php echo _('Currency'); ?>: <?php echo $_SESSION['s_default_currency']; ?><BR>
                                <?php echo _('Time Zone'); ?>: <?php echo $_SESSION['s_default_timezone']; ?><BR>
                                <?php echo _('Expiration Emails'); ?>: <?php
                                if ($_SESSION['s_expiration_emails'] == '1') {
                                    echo _('Yes');
                                } else {
                                    echo _('No');
                                } ?>
                            </small>
                        </p>
                    </li>
                    <!-- Menu Footer-->
                    <li class="user-footer">
                        <a href="<?php echo $web_root; ?>/settings/profile/" class="btn btn-default btn-flat"><?php echo _('User Profile'); ?></a>
                        <a href="<?php echo $web_root; ?>/settings/display/" class="btn btn-default btn-flat float-right"><?php echo _('Display Settings'); ?></a>
                    </li>
                </ul>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo $web_root; ?>/logout.php" role="button">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </li>
        </ul>
    </nav>
    <!-- /.navbar -->
    <!-- Main Sidebar Container -->
    <!aside class="main-sidebar main-sidebar-custom sidebar-dark-red elevation-2">
    <aside class="main-sidebar main-sidebar-custom<?php echo $layout->sidebarDarkMode(); ?> elevation-2">
        <?php /* ?>
    <aside class="main-sidebar main-sidebar-custom sidebar-dark-red elevation-2">
<?php */ ?>
    <!aside class="main-sidebar main-sidebar-custom sidebar-light-red elevation-2">

        <!-- Brand Logo -->

        <a href="<?php echo $web_root; ?>" class="brand-link domainmod-css-logo-background-colour">
            <!a href="<?php echo $web_root; ?>" class="brand-link">
            <img src="<?php echo $web_root; ?>/images/logo-mini.png" alt="DomainMOD Logo Mini" class="brand-image-xs logo-xs domainmod-css-padding-left">
            <img src="<?php echo $web_root; ?>/images/logo.png" alt="DomainMOD Logo" class="brand-image-xs logo-xl domainmod-css-padding-left">
            <span>&nbsp;</span>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">

<?php /* ?>
            <!-- Sidebar user panel (optional) -->
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                <div class="image">
                    <img src="dist/img/user2-160x160.jpg" class="img-circle elevation-2" alt="User Image">
                </div>
                <div class="info">
                    <a href="#" class="d-block">Alexander Pierce</a>
                </div>
            </div>
<?php */ ?>
            <!-- SidebarSearch Form -->
            <form class="form-inline" action="<?php echo $web_root; ?>/domains/index.php">
                <div class="form-inline">
                    <div class="input-group domainmod-css-sidebar-search-padding">
                        <input class="form-control form-control-sidebar" type="search" placeholder="<?php echo _('Domain Search'); ?>" aria-label="<?php echo _('Domain Search');; ?>" name="search_for"<?php if ($search_for && $search_for != '') echo ' value="' . $search_for . '"'; ?>>
                        <div class="input-group-append">
                            <button class="btn btn-sidebar">
                                <i class="fas fa-search fa-fw"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Sidebar Menu -->
            <?php require_once DIR_INC . '/layout/menu-main.inc.php'; ?>

        </div>
        <!-- /.sidebar -->

<?php /* ?>
        <div class="sidebar-custom">
            <a href="#" class="btn btn-link"><i class="fas fa-cogs"></i></a>
            <a href="#" class="btn btn-secondary hide-on-collapse pos-right">Help</a>
        </div>
        <!-- /.sidebar-custom -->
<?php */ ?>
    </aside>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">

            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0"><?php echo $page_title; ?></h1>
                    </div><!-- /.col -->
                    <div class="col-sm-6">
                        <?php  require_once DIR_INC . '/layout/breadcrumbs.inc.php'; ?>
                    </div><!-- /.col -->
                </div><!-- /.row -->
            </div><!-- /.container-fluid -->

            <div class="container-fluid"><?php
                if ($_SESSION['s_message_danger'] != "") {
                    echo $system->showMessageDanger($_SESSION['s_message_danger']);
                    unset($_SESSION['s_message_danger']);
                }

                if ($_SESSION['s_message_success'] != "") {
                    echo $system->showMessageSuccess($_SESSION['s_message_success']);
                    unset($_SESSION['s_message_success']);
                }

                if ($_SESSION['s_message_info'] != "") {
                    echo $system->showMessageInfo($_SESSION['s_message_info']);
                    unset($_SESSION['s_message_info']);
                }

                require_once DIR_INC . '/layout/table-maintenance.inc.php'; ?>
            </div>

        </div>
        <!-- /.content-header -->

        <!-- Main content -->
        <div class="content">
            <div class="container-fluid"><?php

            $full_filename = DIR_INC . '/layout/header.DEMO.inc.php';

            if (file_exists($full_filename)) {

                require_once DIR_INC . '/layout/header.DEMO.inc.php';

            } ?>
