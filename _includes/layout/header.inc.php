<?php
/**
 * /_includes/layout/header.inc.php
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
<!-- App wrapper -->
<?php
$search_for = $search_for ?? '';
?>
<div class="app-wrapper">

    <!-- Header -->
    <nav class="app-header navbar navbar-expand bg-body">
        <div class="container-fluid">

            <!-- Start navbar links -->
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button" aria-label="<?php echo _('Toggle the menu'); ?>"><i class="bi bi-list"></i></a>
                </li>
                <li class="nav-item">
                    <a href="<?php echo $web_root; ?>/feedback/" class="nav-link"><?php echo _("Feedback"); ?></a>
                </li>
            </ul>

            <!-- End navbar links -->
            <ul class="navbar-nav ms-auto">

                <!-- Colour mode: AdminLTE's switcher, with the choice saved to the user's account (js/colour-mode-save.js) -->
                <li class="nav-item dropdown" id="domainmod-colour-mode" data-save-url="<?php echo $web_root; ?>/settings/toggles/dark-mode/" data-csrf-token="<?php echo htmlspecialchars((new DomainMOD\Csrf())->getToken(), ENT_QUOTES, 'UTF-8'); ?>">
                    <a class="nav-link" href="#" id="domainmod-colour-mode-menu" aria-label="<?php echo _('Color Mode'); ?>" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-sun-fill<?php if ($layout->colourMode() !== 'light') echo ' d-none'; ?>" data-lte-theme-icon="light"></i>
                        <i class="bi bi-moon-fill<?php if ($layout->colourMode() !== 'dark') echo ' d-none'; ?>" data-lte-theme-icon="dark"></i>
                        <i class="bi bi-circle-half<?php if ($layout->colourMode() !== 'auto') echo ' d-none'; ?>" data-lte-theme-icon="auto"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end domainmod-css-colour-mode-menu" aria-labelledby="domainmod-colour-mode-menu"><?php
                        foreach (array('light' => array('bi-sun-fill', _('Light')), 'dark' => array('bi-moon-fill', _('Dark')), 'auto' => array('bi-circle-half', _('Auto'))) as $mode => $mode_details) {
                            $is_current_mode = $layout->colourMode() === $mode; ?>

                        <li>
                            <button type="button" class="dropdown-item d-flex align-items-center<?php if ($is_current_mode) echo ' active'; ?>" data-bs-theme-value="<?php echo $mode; ?>" aria-pressed="<?php echo $is_current_mode ? 'true' : 'false'; ?>">
                                <i class="bi <?php echo $mode_details[0]; ?> me-2"></i>
                                <?php echo $mode_details[1]; ?>

                                <i class="bi bi-check-lg ms-auto<?php if (!$is_current_mode) echo ' d-none'; ?>"></i>
                            </button>
                        </li><?php

                        } ?>

                    </ul>
                </li>

                <!-- User menu -->
                <li class="nav-item dropdown user-menu">
                    <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" aria-label="<?php echo _('User Profile'); ?>">
                        <i class="bi bi-person-fill"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                        <li class="user-header text-bg-primary">
                            <p>
                                <?php echo ($_SESSION['s_first_name'] ?? '') . " "; ?> <?php echo $_SESSION['s_last_name'] ?? ''; ?><BR>
                                <?php echo $_SESSION['s_email_address'] ?? ''; ?><BR>
                                <small><BR>
                                    <?php echo _('Language'); ?>: <?php echo $_SESSION['s_default_language_name'] ?? ''; ?><BR>
                                    <?php echo _('Currency'); ?>: <?php echo $_SESSION['s_default_currency'] ?? ''; ?><BR>
                                    <?php echo _('Time Zone'); ?>: <?php echo $_SESSION['s_default_timezone'] ?? ''; ?><BR>
                                    <?php echo _('Expiration Emails'); ?>: <?php
                                    if (($_SESSION['s_expiration_emails'] ?? '') == '1') {
                                        echo _('Yes');
                                    } else {
                                        echo _('No');
                                    } ?>
                                </small>
                            </p>
                        </li>
                        <li class="user-footer">
                            <a href="<?php echo $web_root; ?>/settings/profile/" class="btn btn-outline-secondary"><?php echo _('User Profile'); ?></a>
                            <a href="<?php echo $web_root; ?>/settings/display/" class="btn btn-outline-secondary float-end"><?php echo _('Display Settings'); ?></a>
                        </li>
                    </ul>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?php echo $web_root; ?>/logout.php" role="button" aria-label="<?php echo _('Logout'); ?>">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                </li>
            </ul>
        </div>
    </nav>
    <!-- /.app-header -->

    <!-- Sidebar (it collapses to icons only, and AdminLTE remembers that in the browser) -->
    <aside class="app-sidebar bg-body-secondary shadow" data-enable-persistence="true">

        <!-- Brand logo (on a light background in light mode. Dark mode draws the lettering light, see base.css) -->
        <div class="sidebar-brand domainmod-css-logo-background-colour">
            <a href="<?php echo $web_root; ?>/" class="brand-link logo-switch">
                <img src="<?php echo $web_root; ?>/images/logo-mini.png" alt="DomainMOD Logo Mini" class="brand-image-xl logo-xs">
                <img src="<?php echo $web_root; ?>/images/logo.png" alt="DomainMOD Logo" class="brand-image-xs logo-xl domainmod-logo-image">
                <!-- Dark mode only: the same image again, cropped to the red icon and laid over the off-white logo -->
                <img src="<?php echo $web_root; ?>/images/logo.png" alt="" class="brand-image-xs logo-xl domainmod-logo-icon">
            </a>
        </div>

        <div class="sidebar-wrapper">

            <!-- Domain search, styled like the menu filter below it, with the search button inside the field -->
            <form class="sidebar-search" role="search" action="<?php echo $web_root; ?>/domains/index.php">
                <label for="sidebar-domain-search-input" class="visually-hidden"><?php echo _('Domain Search'); ?></label>
                <div class="domainmod-css-search-field">
                    <input type="search" id="sidebar-domain-search-input" name="search_for" class="form-control form-control-sm" placeholder="<?php echo _('Domain Search'); ?>" autocomplete="off"<?php if ($search_for && $search_for != '') echo ' value="' . $search_for . '"'; ?>>
                    <button class="domainmod-css-search-submit" type="submit" aria-label="<?php echo _('Search'); ?>"><i class="bi bi-search" aria-hidden="true"></i></button>
                </div>
            </form>

            <!-- Menu filter (AdminLTE's sidebar search, which narrows the menu below as you type) -->
            <div class="sidebar-search" role="search">
                <label for="sidebar-search-input" class="visually-hidden"><?php echo _('Filter the Menu'); ?></label>
                <div class="domainmod-css-search-field">
                    <input type="search" id="sidebar-search-input" class="form-control form-control-sm" placeholder="<?php echo _('Filter the Menu'); ?>" autocomplete="off" data-lte-toggle="sidebar-search" data-lte-target="#navigation">
                    <span class="domainmod-css-search-icon" aria-hidden="true"><i class="bi bi-funnel"></i></span>
                </div>
                <p class="fs-7 text-secondary mt-2 mb-0" data-lte-search-empty role="status" hidden><?php echo _('No matching pages.'); ?></p>
            </div>

            <!-- Sidebar menu -->
            <?php require_once DIR_INC . '/layout/menu-main.inc.php'; ?>

        </div>
        <!-- /.sidebar-wrapper -->

    </aside>

    <!-- Main content area -->
    <main class="app-main">
        <!-- Page header -->
        <div class="app-content-header">

            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6">
                        <h1 class="mb-0 fs-3"><?php echo $page_title; ?></h1>
                    </div>
                    <div class="col-sm-6">
                        <?php  require_once DIR_INC . '/layout/breadcrumbs.inc.php'; ?>
                    </div>
                </div>
            </div>

            <div class="container-fluid"><?php
                $_SESSION['s_message_danger'] = $_SESSION['s_message_danger'] ?? '';
                if ($_SESSION['s_message_danger'] != "") {
                    echo $system->showMessageDanger($_SESSION['s_message_danger']);
                    $_SESSION['s_message_danger'] = '';
                }

                $_SESSION['s_message_success'] = $_SESSION['s_message_success'] ?? '';
                if ($_SESSION['s_message_success'] != "") {
                    echo $system->showMessageSuccess($_SESSION['s_message_success']);
                    $_SESSION['s_message_success'] = '';
                }

                $_SESSION['s_message_info'] = $_SESSION['s_message_info'] ?? '';
                if ($_SESSION['s_message_info'] != "") {
                    echo $system->showMessageInfo($_SESSION['s_message_info']);
                    $_SESSION['s_message_info'] = '';
                }

                require_once DIR_INC . '/layout/table-maintenance.inc.php'; ?>
            </div>

        </div>
        <!-- /.app-content-header -->

        <!-- Page content -->
        <div class="app-content">
            <div class="container-fluid"><?php

            $full_filename = DIR_INC . '/layout/header.DEMO.inc.php';

            if (file_exists($full_filename)) {

                require_once DIR_INC . '/layout/header.DEMO.inc.php';

            } ?>
