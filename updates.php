<?php
// /updates.php
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
<?php
include("_includes/start-session.inc.php");
include("_includes/config.inc.php");
include("_includes/database.inc.php");
include("_includes/software.inc.php");
include("_includes/auth/auth-check.inc.php");
include("_includes/system/functions/pagination.inc.php");

$page_title = $software_title . " Updates";
$software_section = "updates";

// Search Navigation Variables
$numBegin = $_GET['numBegin'];
$begin = $_GET['begin'];
$num = $_GET['num'];

$id = $_GET['id'];

if ($id != "") {
	
	$sql_real_id = "SELECT u.id, ud.id AS update_id
					FROM `updates` AS u, update_data AS ud
					WHERE u.id = ud.update_id
					  AND u.id = '" . mysql_real_escape_string($id) . "'";
	$result_real_id = mysql_query($sql_real_id,$connection) or die(mysql_error());
	
	if (mysql_num_rows($result_real_id) == 0) {
		
		header("Location: updates.php");
		exit;

	} else {

/*

*/
	}

}
?>
<?php include("_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("_includes/layout/header.inc.php"); ?>
<?php if ($id == "" && $_SESSION['are_there_updates'] == "1") { ?>
[<a href="_includes/system/mark-updates-read.inc.php?direct=1">mark all updates as read</a>]<BR><BR>
<?php } ?>
<?php
if ($id != "") {

	$sql = "SELECT id, name, `update`, insert_time
			FROM updates
			WHERE id = '" . mysql_real_escape_string($id) . "'
			ORDER BY insert_time desc, id desc";

} else {

	$sql = "SELECT id, name, `update`, insert_time
			FROM updates
			ORDER BY insert_time desc, id desc";

}
$result_limit = 15;
$totalrows = mysql_num_rows(mysql_query($sql));
$navigate = pageBrowser($totalrows,15,$result_limit, "",$_GET[numBegin],$_GET[begin],$_GET[num]);
$sql = $sql.$navigate[0];
$result = mysql_query($sql,$connection); ?>
<?php if ($id == "") { ?>
<?php include("_includes/layout/pagination.menu.inc.php"); ?><BR>
<?php } ?>

    <table class="update-block-outer"><?php

		while ($row = mysql_fetch_object($result)) {

			if ($row->update != "") { ?>

                <tr>
                    <td class="update-block-left">
                    	<strong><?=date("Y-m-d", strtotime($row->insert_time))?></strong>
					</td>
                    <td class="update-block-right"><?php 

						if ($id == "") { ?>

	                        <a href="updates.php?id=<?=$row->id?>"><font class="subheadline"><?=$row->name?></font></a>
                            <?php
							$sql_exists = "SELECT *
										   FROM update_data
										   WHERE user_id = '" . $_SESSION['user_id'] . "'
										     AND update_id = '" . $row->id . "'";
							$result_exists = mysql_query($sql_exists,$connection);
							if (mysql_num_rows($result_exists) != 0) { ?>
	                            &nbsp;&nbsp;<font class="default_highlight">*NEW*</font>&nbsp;&nbsp;[<a href="_includes/system/mark-updates-read.inc.php?direct=1&id=<?=$row->id?>">mark read</a>]<?php
							}

						} else { ?>

	                        <font class="subheadline"><?=$row->name?></font>
                            <?php
							$sql_exists = "SELECT *
										   FROM update_data
										   WHERE user_id = '" . $_SESSION['user_id'] . "'
										     AND update_id = '" . $row->id . "'";
							$result_exists = mysql_query($sql_exists,$connection);
							if (mysql_num_rows($result_exists) != 0) { ?>
	                            &nbsp;&nbsp;<font class="default_highlight">*NEW*</font>&nbsp;&nbsp;[<a href="_includes/system/mark-updates-read.inc.php?direct=1&id=<?=$row->id?>">mark read</a>]<?php
							}

						} ?>

                    </td>
                </tr>
	
				<tr>
					<td class="update-block-left">&nbsp;
                    	
					</td>
					<td class="update-block-right">
						<?=$row->update?>
                        <?php if ($id == "") echo "<BR><BR>"; ?>
                    </td>
				</tr><?php

			} else { ?>

                <tr>
                    <td class="update-block-left">
                    	<strong><?=date("Y-m-d", strtotime($row->insert_time))?></strong>
					</td>
                    <td class="update-block-right"><?php 

						if ($id == "") { ?>

	                        <a href="updates.php?id=<?=$row->id?>"><font class="subheadline"><?=$row->name?></font></a>
                            <?php
							$sql_exists = "SELECT *
										   FROM update_data
										   WHERE user_id = '" . $_SESSION['user_id'] . "'
										     AND update_id = '" . $row->id . "'";
							$result_exists = mysql_query($sql_exists,$connection);
							if (mysql_num_rows($result_exists) != 0) { ?>
	                            &nbsp;&nbsp;<font class="default_highlight">*NEW*</font>&nbsp;&nbsp;[<a href="_includes/system/mark-updates-read.inc.php?direct=1&id=<?=$row->id?>">mark read</a>]<?php
							} 

						} else { ?>
                        
	                        <font class="subheadline"><?=$row->name?></font>
                            <?php
							$sql_exists = "SELECT *
										   FROM update_data
										   WHERE user_id = '" . $_SESSION['user_id'] . "'
										     AND update_id = '" . $row->id . "'";
							$result_exists = mysql_query($sql_exists,$connection);
							if (mysql_num_rows($result_exists) != 0) { ?>
	                            &nbsp;&nbsp;<font class="default_highlight">*NEW*</font>&nbsp;&nbsp;[<a href="_includes/system/mark-updates-read.inc.php?direct=1&id=<?=$row->id?>">mark read</a>]<?php
							} 

						} 
						
						if ($id == "") echo "<BR><BR>";
						
						?>
                        
                    </td>
                </tr><?php

			}

		} ?>

	</table>
    
<?php if ($id == "") { ?>
<?php include("_includes/layout/pagination.menu.inc.php"); ?>
<?php } ?>
<?php include("_includes/layout/footer.inc.php"); ?>
</body>
</html>
