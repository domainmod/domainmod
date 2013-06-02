<?php
// /_includes/layout/header.inc.php
// 
// DomainMOD - A web-based application written in PHP & MySQL used to manage a collection of domain names.
// Copyright (C) 2010 Greg Chetcuti
// 
// DomainMOD is free software; you can redistribute it and/or modify it under the terms of the GNU General
// Public License as published by the Free Software Foundation; either version 2 of the License, or (at your
// option) any later version.
// 
// DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the
// implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License
// for more details.
// 
// You should have received a copy of the GNU General Public License along with DomainMOD. If not, please see
// http://www.gnu.org/licenses/
?>
<a name="top"></a>
<div class="main-container">

    <div class="header-container">
        <div class="header-left">
            <a href="<?php if ($web_root != "") echo $web_root; ?>/domains.php"><img border="0" src="<?php if ($web_root != "") echo $web_root; ?>/images/logo.png"></a>
        </div>
        <div class="header-right">
            <?php if ($_SESSION['is_logged_in'] == 1) { ?>
                <em>logged in as <strong><?=$_SESSION['username']?></strong> (<a class="subtlelink" href="<?php if ($web_root != "") echo $web_root; ?>/system/update-profile.php"><?=$_SESSION['first_name']?> <?=$_SESSION['last_name']?></a>)</em>&nbsp;&nbsp;[ <a href="<?php if ($web_root != "") echo $web_root; ?>/updates.php">Updates</a> ]&nbsp;&nbsp;[ <a target="_blank" href="http://aysmedia.com/contact/">Help</a> ]&nbsp;&nbsp;[ <a href="<?php if ($web_root != "") echo $web_root; ?>/logout.php">Logout</a> ]
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
            <div class="update_box_header"><?php 
				if ($_SESSION['are_there_updates'] == "1") { ?>
	
					<a href="<?php if ($web_root != "") echo $web_root; ?>/updates.php"><font class="subheadline">Recent Software Updates</font></a><BR><?php
					$sql_updates = "SELECT u.id, u.name
									FROM updates AS u, update_data AS ud
									WHERE u.id = ud.update_id
									  AND ud.user_id = '" . $_SESSION['user_id'] . "'
									ORDER BY u.insert_time desc, u.id desc
									LIMIT 3";
					$result_updates = mysql_query($sql_updates,$connection);
					while ($row_updates = mysql_fetch_object($result_updates)) {

						if ($web_root != "") {

							echo "<a class=\"invisiblelink\" href=\"" . $web_root . "/updates.php?id=" . $row_updates->id . "\">";

						} else {

							echo "<a class=\"invisiblelink\" href=\"/updates.php?id=" . $row_updates->id . "\">";

						}
						echo substr($row_updates->name, 0, 65); 
						if (strlen($row_updates->name) >= 65) echo "...";
						echo "</a>";
						echo "<BR>";

					} ?>
                    
					[<a class="invisiblelink" href="<?php if ($web_root != "") echo $web_root; ?>/_includes/system/mark-updates-read.inc.php?direct=1">mark all updates as read</a>]<?php

				}
				?>
            </div>
            <div style="clear: both;"></div>
        </div>
        <div class="main-inner">
			<?php if ($software_section != "login" && $software_section != "installation" && $software_section != "resetpassword" && $_SESSION['running_login_checks'] != 1) { ?>
            <hr width="100%" size="1" noshade><BR>
            <?php } ?>
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
