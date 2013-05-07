<?php
// /system/admin/users.php
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
<?php
include("../../_includes/start-session.inc.php");

// If the user isn't an administrator, redirect them to $full_redirect
$full_redirect = "index.php";
include("../../_includes/auth/admin-user-check.inc.php");

include("../../_includes/config.inc.php");
include("../../_includes/database.inc.php");
include("../../_includes/software.inc.php");
include("../../_includes/auth/auth-check.inc.php");
include("../../_includes/timestamps/current-timestamp.inc.php");

$page_title = $software_title . " Users";
$software_section = "admin-user-list";

$export = $_GET['export'];

if ($_SESSION['username'] == "admin") {

	$sql = "SELECT u.id, u.first_name, u.last_name, u.username, u.email_address, u.admin, u.number_of_logins, u.last_login, u.insert_time, u.update_time, us.default_timezone, us.default_currency
			FROM users AS u, user_settings AS us
			WHERE u.id = us.user_id
			  AND u.active = '1'
			ORDER BY u.first_name, u.last_name, u.username, u.email_address";

} else {
	
	$sql = "SELECT u.id, u.first_name, u.last_name, u.username, u.email_address, u.admin, u.number_of_logins, u.last_login, u.insert_time, u.update_time, us.default_timezone, us.default_currency
			FROM users AS u, user_settings AS us
			WHERE u.id = us.user_id
			  AND u.active = '1'
			ORDER BY u.first_name, u.last_name, u.username, u.email_address";

}

if ($export == "1") {
	
	$full_export = "";
	$full_export .= "\"" . $page_title . "\"\n\n";
	$full_export .= "\"Status\",\"First Name\",\"Last Name\",\"Username\",\"Email Address\",\"Is Admin?\",\"Default Currency\",\"Default Timezone\",\"Number of Logins\",\"Last Login\",\"Added\",\"Last Updated\",\n";

	$result = mysql_query($sql,$connection) or die(mysql_error());

	if (mysql_num_rows($result) > 0) {
	
		while ($row = mysql_fetch_object($result)) {
			
			if ($row->admin == "1") {
				
				$is_admin = "1";
				
			} else {
				
				$is_admin = "";
				
			}
	
			$full_export .= "\"Active\",\"" . $row->first_name . "\",\"" . $row->last_name . "\",\"" . $row->username . "\",\"" . $row->email_address . "\",\"" . $is_admin . "\",\"" . $row->default_currency . "\",\"" . $row->default_timezone . "\",\"" . $row->number_of_logins . "\",\"" . $row->last_login . "\",\"" . $row->insert_time . "\",\"" . $row->update_time . "\",\n";
	
		}
			
	}
	
	$sql = "SELECT u.id, u.first_name, u.last_name, u.username, u.email_address, u.admin, u.number_of_logins, u.last_login, u.insert_time, u.update_time, us.default_timezone, us.default_currency
			FROM users AS u, user_settings AS us
			WHERE u.id = us.user_id
			  AND u.active = '0'
			ORDER BY u.first_name, u.last_name, u.username, u.email_address";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	if (mysql_num_rows($result) > 0) {
	
		while ($row = mysql_fetch_object($result)) {
	
			if ($row->admin == "1") {
				
				$is_admin = "1";
				
			} else {
				
				$is_admin = "";
				
			}
	
			$full_export .= "\"Inactive\",\"" . $row->first_name . "\",\"" . $row->last_name . "\",\"" . $row->username . "\",\"" . $row->email_address . "\",\"" . $is_admin . "\",\"" . $row->default_currency . "\",\"" . $row->default_timezone . "\",\"" . $row->number_of_logins . "\",\"" . $row->last_login . "\",\"" . $row->insert_time . "\",\"" . $row->update_time . "\",\n";
	
		}
		
	}

	$full_export .= "\n";
	$current_timestamp_unix = strtotime($current_timestamp);
	$export_filename = "user_list_" . $current_timestamp_unix . ".csv";
	include("../../_includes/system/export-to-csv.inc.php");
	exit;
}
?>
<?php include("../../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../../_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../../_includes/layout/header.inc.php"); ?>
Below is a list of all users that have access to <?=$software_title?>.<BR><BR>
[<a href="<?=$PHP_SELF?>?export=1">EXPORT</a>]<?php

$result = mysql_query($sql,$connection) or die(mysql_error());

if (mysql_num_rows($result) > 0) { ?>

    <table class="main_table">
    <tr class="main_table_row_heading_active">
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Active Users (<?=mysql_num_rows($result)?>)</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Username</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Email Address</font>
        </td>
    </tr><?php

    while ($row = mysql_fetch_object($result)) { ?>

        <tr class="main_table_row_active">
            <td class="main_table_cell_active">
                <a class="invisiblelink" href="edit/user.php?uid=<?=$row->id?>"><?=$row->first_name?> <?=$row->last_name?></a><?php if ($row->admin == "1") echo "<a title=\"Admin User\"><font class=\"default_highlight\">*</font></a>"; ?></a>
            </td>
            <td class="main_table_cell_active">
                <a class="invisiblelink" href="edit/user.php?uid=<?=$row->id?>"><?=$row->username?></a>
            </td>
            <td class="main_table_cell_active">
                <a class="invisiblelink" href="edit/user.php?uid=<?=$row->id?>"><?=$row->email_address?></a>
            </td>
        </tr><?php 
	}
		
}

$sql = "SELECT id, first_name, last_name, username, email_address, admin
		FROM users
		WHERE active = '0'
		ORDER BY first_name, last_name, username, email_address";
$result = mysql_query($sql,$connection) or die(mysql_error());

if (mysql_num_rows($result) > 0) { ?>

    <tr class="main_table_row_heading_inactive">
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">Inactive Users (<?=mysql_num_rows($result)?>)</font>
        </td>
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">Username</font>
        </td>
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">Email Address</font>
        </td>
    </tr><?php

    while ($row = mysql_fetch_object($result)) { ?>

        <tr class="main_table_row_inactive">
            <td class="main_table_cell_inactive">
                <a class="invisiblelink" href="edit/user.php?uid=<?=$row->id?>"><?=$row->first_name?> <?=$row->last_name?><?php if ($row->admin == "1") echo "<a title=\"Admin User\"><font class=\"default_highlight\">*</font></a>"; ?></a>
            </td>
            <td class="main_table_cell_inactive">
                <a class="invisiblelink" href="edit/user.php?uid=<?=$row->id?>"><?=$row->username?></a>
            </td>
            <td class="main_table_cell_inactive">
                <a class="invisiblelink" href="edit/user.php?uid=<?=$row->id?>"><?=$row->email_address?></a>
            </td>
        </tr><?php 

    }
	
} ?>
</table>
<BR><font class="default_highlight">*</font> = Admin Account
<?php include("../../_includes/layout/footer.inc.php"); ?>
</body>
</html>