<?php
/**
 * /_includes/layout/header-install.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2024 Greg Chetcuti <greg@chetcuti.com>
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
<div class="row">
    <div class="col-4">
        &nbsp;
    </div>
    <div class="col-4">
        <BR><BR><BR>
        <div class="login-logo">
            <a href="<?php echo WEB_ROOT; ?>/install/"><img src="<?php echo $web_root; ?>/images/logo-lg.png"></a>
        </div>
        <!-- /.login-logo -->
        <BR>
        <?php
        $_SESSION['s_message_danger'] = $_SESSION['s_message_danger'] ?? '';
        if ($_SESSION['s_message_danger'] != "") {
            echo $system->showMessageDanger($_SESSION['s_message_danger']);
            unset($_SESSION['s_message_danger']);
        }

        $_SESSION['s_message_success'] = $_SESSION['s_message_success'] ?? '';
        if ($_SESSION['s_message_success'] != "") {
            echo $system->showMessageSuccess($_SESSION['s_message_success']);
            unset($_SESSION['s_message_success']);
        }

        $_SESSION['s_message_info'] = $_SESSION['s_message_info'] ?? '';
        if ($_SESSION['s_message_info'] != "") {
            echo $system->showMessageInfo($_SESSION['s_message_info']);
            unset($_SESSION['s_message_info']);
        }

        require_once DIR_INC . '/layout/table-maintenance.inc.php';
        ?>
            <h3><?php echo $page_title; ?></h3>
        <?php
        $full_filename = DIR_INC . '/layout/header.DEMO.inc.php';

        if (file_exists($full_filename)) {

            require_once DIR_INC . '/layout/header.DEMO.inc.php';

        }
