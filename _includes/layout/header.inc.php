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
<?php echo $layout->skipLinks(); ?>
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

                <!-- User menu: who's signed in, their role, MFA, and last sign-in (DomainMOD\UserMenu, from the session), then
                     their own settings pages and Logout. The names and email address are stored HTML-escaped, so they're
                     shown as they are (the email address isolated left to right, like Layout::ltr()). -->
                <?php
                $user_menu_initials = DomainMOD\UserMenu::initials($_SESSION['s_first_name'] ?? '', $_SESSION['s_last_name'] ?? '');
                $user_menu_last_sign_in = DomainMOD\UserMenu::lastSignIn($_SESSION['s_previous_login'] ?? ''); ?>
                <li class="nav-item dropdown user-menu">
                    <a href="#" class="nav-link dropdown-toggle" id="domainmod-user-menu" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?php echo _('User Profile'); ?>">
                        <i class="bi bi-person-fill"></i>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end py-2" aria-labelledby="domainmod-user-menu">
                        <li class="px-3 pt-1 pb-2">
                            <div class="d-flex align-items-start">
                                <span class="img-size-50 flex-shrink-0 me-3" aria-hidden="true"><span class="ratio ratio-1x1"><span class="d-flex align-items-center justify-content-center rounded-circle bg-secondary-subtle text-secondary-emphasis fw-semibold"><?php
                                    if ($user_menu_initials !== '') {
                                        echo $user_menu_initials;
                                    } else { ?><i class="bi bi-person-fill"></i><?php
                                    } ?></span></span></span>
                                <!-- overflow-hidden lets the column shrink so a long email address wraps (AdminLTE's
                                     ".dropdown-menu-lg p" rule breaks it, in the right-to-left stylesheet too) -->
                                <div class="flex-grow-1 overflow-hidden text-break">
                                    <p class="fw-semibold"><?php echo trim(($_SESSION['s_first_name'] ?? '') . ' ' . ($_SESSION['s_last_name'] ?? '')); ?></p>
                                    <p class="small text-body-secondary"><bdi dir="ltr"><?php echo $_SESSION['s_email_address'] ?? ''; ?></bdi></p>
                                    <div class="mt-1"><?php
                                        foreach (DomainMOD\UserMenu::roleLabels($_SESSION['s_is_admin'] ?? 0, $_SESSION['s_read_only'] ?? '1') as $user_menu_role) { ?>
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle"><?php echo $user_menu_role; ?></span><?php
                                        } ?>

                                    </div>
                                </div>
                            </div><?php
                            if (isset($_SESSION['s_two_factor_on'])) { ?>

                            <div class="small mt-2"><?php
                                if ($_SESSION['s_two_factor_on'] === 1) {
                                    echo _('MFA') . ': ' . _('On');
                                } else {
                                    echo _('MFA') . ': ' . _('Off');
                                    // MFA can't be set up on the demo, so it gets no link there
                                    if (DEMO_INSTALLATION != true) { ?> &middot; <a href="<?php echo $web_root; ?>/settings/security/"><?php echo _('Set it up'); ?></a><?php
                                    }
                                } ?></div><?php
                            }
                            if ($user_menu_last_sign_in !== '') { ?>

                            <div class="small text-body-secondary mt-1"><?php echo sprintf(_('Last Login: %s'), $layout->ltr($user_menu_last_sign_in)); ?></div><?php
                            } ?>

                        </li>
                        <li><hr class="dropdown-divider my-1"></li><?php
                        // The same order as the Settings menu in the sidebar
                        foreach (array(array('/settings/profile/', 'bi-person-circle', _('User Profile')),
                                     array('/settings/display/', 'bi-display', _('Display Settings')),
                                     array('/settings/defaults/', 'bi-person-gear', _('User Defaults')),
                                     array('/settings/security/', 'bi-shield-lock', _('Security'))) as $user_menu_link) { ?>

                        <li><a class="dropdown-item d-flex align-items-center" href="<?php echo $web_root . $user_menu_link[0]; ?>"><i class="bi <?php echo $user_menu_link[1]; ?> me-2" aria-hidden="true"></i><?php echo $user_menu_link[2]; ?></a></li><?php
                        } ?>

                        <li><hr class="dropdown-divider my-1"></li>
                        <li><a class="dropdown-item d-flex align-items-center" href="<?php echo $web_root; ?>/logout.php"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i><?php echo _('Logout'); ?></a></li>
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
            <form class="sidebar-search" role="search" aria-label="<?php echo _('Domain Search'); ?>" action="<?php echo $web_root; ?>/domains/index.php">
                <label for="sidebar-domain-search-input" class="visually-hidden"><?php echo _('Domain Search'); ?></label>
                <div class="domainmod-css-search-field">
                    <input type="search" id="sidebar-domain-search-input" name="search_for" class="form-control form-control-sm" placeholder="<?php echo _('Domain Search'); ?>" autocomplete="off"<?php if ($search_for && $search_for != '') echo ' value="' . $search_for . '"'; ?>>
                    <button class="domainmod-css-search-submit" type="submit" aria-label="<?php echo _('Search'); ?>"><i class="bi bi-search" aria-hidden="true"></i></button>
                </div>
            </form>

            <!-- Menu filter (AdminLTE's sidebar search, which narrows the menu below as you type) -->
            <div class="sidebar-search" role="search" aria-label="<?php echo _('Filter the Menu'); ?>">
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
    <main class="app-main" id="main" data-domainmod-focus-home>
        <!-- Page header --><?php
        // Everything from here to the footer is held back, so the page's actions, which a page can declare after this
        // point, can be drawn in the header (the footer swaps the marker for them, see Layout::renderPageActions())
        ob_start();
        $domainmod_page_actions_buffer = ob_get_level(); ?>
        <div class="app-content-header">

            <div class="container-fluid">
                <div class="domainmod-page-breadcrumb d-flex">
                    <?php  require_once DIR_INC . '/layout/breadcrumbs.inc.php'; ?>
                </div>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                    <h1 class="mb-0 fs-3"><?php echo $page_title; ?></h1>
                    <?php echo DomainMOD\Layout::PAGE_ACTIONS_MARKER; ?>
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

                $_SESSION['s_message_warning'] = $_SESSION['s_message_warning'] ?? '';
                if ($_SESSION['s_message_warning'] != "") {
                    echo $system->showMessageWarning($_SESSION['s_message_warning']);
                    $_SESSION['s_message_warning'] = '';
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
