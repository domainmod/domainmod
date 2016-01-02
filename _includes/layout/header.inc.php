<?php
/**
 * /_includes/layout/header.inc.php
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
    <a name="top"></a>
    <div class="main-container">

    <div class="header-container">
        <div class="header-left">
            <a href="<?php echo $web_root . "/domains.php\"><img border=\"0\" src=\"" . $web_root; ?>/images/logo.png"></a>
        </div>
        <div class="header-right"><?php //@formatter:off ?>
            <em>Currency: <a class="invisiblelink" href="<?php echo $web_root; ?>/settings/defaults/"><?php
                    echo $_SESSION['s_default_currency']; ?></a>&nbsp;&nbsp;Time Zone: <a class="invisiblelink"
                    href="<?php echo $web_root; ?>/settings/defaults/"><?php
                    echo $_SESSION['s_default_timezone']; ?></a></em>
            <BR>
            <em>logged in as <?php echo $_SESSION['s_username']; ?> (<a
                    class="subtlelink" href="<?php echo $web_root; ?>/settings/update-profile.php"><?php
                    echo $_SESSION['s_first_name'] . " "; ?> <?php echo $_SESSION['s_last_name']; ?></a>)</em>
            <BR><BR>
            <?php //@formatter:on ?>
        </div>
    </div>
    <div class="main-outer">
    <div>
        <div class="main-menu"><?php
            if ($software_section != "login" && $software_section != "installation" && $software_section != "resetpassword" && $_SESSION['s_running_login_checks'] != 1) { ?>
                <?php include(DIR_INC . "layout/menu-main.inc.php"); ?><BR><?php
            } ?>
        </div>
        <div class="update_box_header">
            <?php
            if ($_SESSION['s_is_logged_in'] == 1) { ?>
                [ <a target="_blank"
                     href="http://domainmod.org/news/">News</a> ]&nbsp;&nbsp;[
                <a target="_blank"
                   href="http://domainmod.org/support/">Support</a> ]&nbsp;&nbsp;[ <a href="<?php echo $web_root;
                ?>/logout.php">Logout</a> ]<?php
            } ?>
        </div>
        <div style="clear: both;"></div>
    </div>
    <div class="main-inner"><?php
if ($software_section != "login" && $software_section != "installation" && $software_section != "resetpassword" && $_SESSION['s_running_login_checks'] != 1) { ?>
    <hr width="100%" size="1" noshade><BR><?php
} ?>
    <div class="headline"><?php echo $page_title; ?></div>
    <BR>
<?php
include(DIR_INC . "layout/table-maintenance.inc.php");
?>
<?php
if ($_SESSION['s_result_message'] != "") {

    echo $system->showResultMessage($_SESSION['s_result_message']);
    unset($_SESSION['s_result_message']);

}
