<?php
// users.php
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
session_start();

// If the user isn't an administrator, redirect them to $full_redirect
$full_redirect = "index.php";
include("../../_includes/auth/admin-user-check.inc.php");

include("../../_includes/config.inc.php");
include("../../_includes/database.inc.php");
include("../../_includes/software.inc.php");
include("../../_includes/auth/auth-check.inc.php");

$page_title = "User List";
$software_section = "system";
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../../_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../../_includes/header.inc.php"); ?>
<?php
if ($_SESSION['session_username'] == "admin") {
	$sql = "SELECT id, first_name, last_name, username, email_address, admin
			FROM users
			WHERE active = '1'
			ORDER BY first_name, last_name, username, email_address";
	$result = mysql_query($sql,$connection);

} else {
	
	$sql = "SELECT id, first_name, last_name, username, email_address, admin
			FROM users
			WHERE active = '1'
			  AND username != 'admin'
			ORDER BY first_name, last_name, username, email_address";
	$result = mysql_query($sql,$connection);

}
?>
<?php
if (mysql_num_rows($result) > 0) { ?>
    <table class="main_table">
        <tr class="main_table_row_heading_active">
            <td class="main_table_cell_heading_active">
                <font class="subheadline">Active Users (<?=mysql_num_rows($result)?>)</font>
            </td>
            <td class="main_table_cell_heading_active">
                <font class="subheadline">Username</font>
            </td>
            <td class="main_table_cell_heading_active">
                <font class="subheadline">Email Address</font>
            </td>
        </tr>

		<?php
        while ($row = mysql_fetch_object($result)) { ?>
    
            <tr class="main_table_row_active">
                <td class="main_table_cell_active">
                    <a class="subtlelink" href="edit/user.php?uid=<?=$row->id?>"><?=$row->first_name?> <?=$row->last_name?></a><?php if ($row->admin == "1") echo "<a title=\"Admin User\"><font class=\"default_highlight\"><strong>*</strong></font></a>"; ?></a>
                </td>
                <td class="main_table_cell_active">
                    <a class="subtlelink" href="edit/user.php?uid=<?=$row->id?>"><?=$row->username?></a>
                </td>
                <td class="main_table_cell_active">
                    <a class="subtlelink" href="edit/user.php?uid=<?=$row->id?>"><?=$row->email_address?></a>
                </td>
            </tr>
        <?php 
        } ?>
	<?php 
} ?>
<?php
$sql = "SELECT id, first_name, last_name, username, email_address, admin
		FROM users
		WHERE active = '0'
		ORDER BY first_name, last_name, username, email_address";
$result = mysql_query($sql,$connection);
?>
<?php
if (mysql_num_rows($result) > 0) { ?>
        <tr class="main_table_row_heading_inactive">
            <td class="main_table_cell_heading_inactive">
                <font class="subheadline">Inactive Users (<?=mysql_num_rows($result)?>)</font>
            </td>
            <td class="main_table_cell_heading_inactive">
                <font class="subheadline">Username</font>
            </td>
            <td class="main_table_cell_heading_inactive">
                <font class="subheadline">Email Address</font>
            </td>
        </tr>

		<?php
        while ($row = mysql_fetch_object($result)) { ?>
    
            <tr class="main_table_row_inactive">
                <td class="main_table_cell_inactive">
                    <a class="subtlelink" href="edit/user.php?uid=<?=$row->id?>"><?=$row->first_name?> <?=$row->last_name?><?php if ($row->admin == "1") echo "<a title=\"Admin User\"><font class=\"default_highlight\"><strong>*</strong></font></a>"; ?></a>
                </td>
                <td class="main_table_cell_inactive">
                    <a class="subtlelink" href="edit/user.php?uid=<?=$row->id?>"><?=$row->username?></a>
                </td>
                <td class="main_table_cell_inactive">
                    <a class="subtlelink" href="edit/user.php?uid=<?=$row->id?>"><?=$row->email_address?></a>
                </td>
            </tr>
        <?php 
        } ?>

	<?php 
} ?>
	</table>
<BR><font class="default_highlight"><strong>*</strong></font> = Admin Account
<?php include("../../_includes/footer.inc.php"); ?>
</body>
</html>