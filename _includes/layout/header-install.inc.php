<?php
/**
 * /_includes/layout/header-install.inc.php
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
<?php // One centred column: narrow on a desktop, and the phone's width on a phone (mx-0, since the row sits outside a
      // container and its negative margins would otherwise make the page scroll sideways) ?>
<?php echo $layout->skipLinks(false); ?>
<div class="row justify-content-center mx-0">
    <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-4 domainmod-install-column">
        <main id="main" data-domainmod-focus-home>
        <BR><BR><BR>
        <div class="login-logo">
            <a href="<?php echo WEB_ROOT; ?>/install/"><span class="domainmod-login-logo"><img src="<?php echo $web_root; ?>/images/logo-lg.png" alt="DomainMOD Logo" class="domainmod-login-logo-image"><img src="<?php echo $web_root; ?>/images/logo-lg.png" alt="" class="domainmod-login-logo-icon"></span></a>
        </div>
        <!-- /.login-logo -->
        <BR>
        <?php
        $_SESSION['s_message_danger'] = $_SESSION['s_message_danger'] ?? '';
        if ($_SESSION['s_message_danger'] != "") {
            echo $system->showMessageDanger($_SESSION['s_message_danger']);
            $_SESSION['s_message_danger'] = '';
        }

        $_SESSION['s_message_success'] = $_SESSION['s_message_success'] ?? '';
        if ($_SESSION['s_message_success'] != "") {
            echo $system->showMessageSuccessInline($_SESSION['s_message_success']);
            $_SESSION['s_message_success'] = '';
        }

        $_SESSION['s_message_info'] = $_SESSION['s_message_info'] ?? '';
        if ($_SESSION['s_message_info'] != "") {
            echo $system->showMessageInfo($_SESSION['s_message_info']);
            $_SESSION['s_message_info'] = '';
        }

        require_once DIR_INC . '/layout/table-maintenance.inc.php';
        ?>
        <?php
        // The installer's steps, on the pages that are one (each page's settings file sets $install_step)
        if (isset($install_step)) {
            $install_steps = array(_('Language'), _('Software Requirements'), _('Currency'), _('Time Zone'), _('Administrator Account'), _('System Email'), _('Ready to Install'));
            // Each step keeps its answer in the session, so the steps already done link back to their page
            $install_step_urls = array(WEB_ROOT . '/install/language/', WEB_ROOT . '/install/requirements/', WEB_ROOT . '/install/currency/', WEB_ROOT . '/install/timezone/', WEB_ROOT . '/install/administrator/', WEB_ROOT . '/install/email-system/', WEB_ROOT . '/install/ready/');
            // The furthest step reached, so going back to an earlier step keeps the later ones clickable
            $_SESSION['s_installation_reached'] = max((int) ($_SESSION['s_installation_reached'] ?? 0), (int) $install_step);
            echo $layout->stepIndicator($install_steps, $install_step, _('Installation steps'), $install_step_urls, $_SESSION['s_installation_reached']);
        } ?>
            <h1 class="h3"><?php echo $page_title; ?></h1>
        <?php
        $full_filename = DIR_INC . '/layout/header.DEMO.inc.php';

        if (file_exists($full_filename)) {

            require_once DIR_INC . '/layout/header.DEMO.inc.php';

        }
