<?php
// /_includes/layout/header.inc.php
// 
// Domain Manager - A web-based application written in PHP & MySQL used to manage a collection of domain names.
// Copyright (C) 2010 Greg Chetcuti
// 
// Domain Manager is free software; you can redistribute it and/or modify it under the terms of the GNU General
// Public License as published by the Free Software Foundation; either version 2 of the License, or (at your
// option) any later version.
// 
// Domain Manager is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the
// implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License
// for more details.
// 
// You should have received a copy of the GNU General Public License along with Domain Manager. If not, please 
// see http://www.gnu.org/licenses/
?>
<a name="top"></a>
<div class="main-container">

    <div class="header-container">
        <div class="header-left">
            <a href="<?=$web_root?>/domains.php"><img border="0" src="<?=$web_root?>/images/logo.png"></a>
        </div>
        <div class="header-right">
            <?php if ($_SESSION['is_logged_in'] == 1) { ?>
                <em>logged in as <strong><?=$_SESSION['username']?></strong> (<a class="subtlelink" href="<?=$web_root?>/system/update-profile.php"><?=$_SESSION['first_name']?> <?=$_SESSION['last_name']?></a>)</em>&nbsp;&nbsp;[ <a target="_blank" href="http://aysmedia.com/contact/">Help</a> ]&nbsp;&nbsp;[ <a href="<?=$web_root?>/logout.php">Logout</a> ]
            <?php } ?>
        </div>
    </div>

    <div class="main-outer">
        <div class="main-menu">
            <?php if ($software_section != "login" && $software_section != "installation" && $software_section != "resetpassword" && $_SESSION['running_login_checks'] != 1) { ?>
            <?php include($full_server_path . "/_includes/layout/menu-main.inc.php"); ?><BR>
            <hr width="100%" size="1" noshade>
            <?php } ?>
        </div>
        <div class="main-inner">
            <font class="headline"><?=$page_title?></font>
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
