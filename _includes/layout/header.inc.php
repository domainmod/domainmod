<?php
/**
 * /_includes/layout/header.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (C) 2010-2015 Greg Chetcuti <greg@chetcuti.com>
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
<a name="top"></a>
<div class="main-container">

    <div class="header-container">
        <div class="header-left">
            <a href="<?php echo $web_root; ?>/domains.php"><img border="0" src="<?php echo $web_root; ?>/images/logo.png"></a>
        </div>
        <div class="header-right">
            <?php if ($_SESSION['is_logged_in'] == 1) { ?>
                <em>logged in as <strong><?php echo $_SESSION['username']; ?></strong> (<a class="subtlelink" href="<?php echo $web_root; ?>/system/update-profile.php"><?php echo $_SESSION['first_name']; ?> <?php echo $_SESSION['last_name']; ?></a>)</em>&nbsp;&nbsp;[ <a target="_blank" href="http://domainmod.org/news/">News</a> ]&nbsp;&nbsp;[ <a target="_blank" href="http://domainmod.org/support/">Support</a> ]&nbsp;&nbsp;[ <a href="<?php echo $web_root; ?>/logout.php">Logout</a> ]
            <?php } ?>
        </div>
    </div>
    <div class="main-outer">
        <div>
            <div class="main-menu">
                <?php if ($software_section != "login" && $software_section != "installation" && $software_section != "resetpassword" && $_SESSION['running_login_checks'] != 1) { ?>
                <?php include($full_server_path . "/_includes/layout/menu-main.inc.php"); ?><BR>
                <?php } ?>
            </div>
            <div class="update_box_header">
                &nbsp;
			</div>
            <div style="clear: both;"></div>
        </div>
        <div class="main-inner">
			<?php if ($software_section != "login" && $software_section != "installation" && $software_section != "resetpassword" && $_SESSION['running_login_checks'] != 1) { ?>
            <hr width="100%" size="1" noshade><BR>
            <?php } ?>
            <font class="headline"><?php echo $page_title; ?></font>
            <BR><BR>
            <?php 
                include($full_server_path . "/_includes/layout/table-maintenance.inc.php"); 
            ?>
            <?php 
            if ($_SESSION['result_message'] != "") {
                include($full_server_path . "/_includes/layout/table-result-message.inc.php"); 
                unset($_SESSION['result_message']);
            }
            ?>
