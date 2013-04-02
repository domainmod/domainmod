<?php
// list-users.php
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
			ORDER BY first_name asc, last_name asc";
	$result = mysql_query($sql,$connection);

} else {
	
	$sql = "SELECT id, first_name, last_name, username, email_address, admin
			FROM users
			WHERE active = '1'
			  AND username != 'admin'
			ORDER BY first_name asc, last_name asc";
	$result = mysql_query($sql,$connection);

}
?>
<strong>Number of Active User Accounts:</strong> <?=mysql_num_rows($result)?>
<?php
if (mysql_num_rows($result) > 0) { ?>

    <BR><BR>
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr height="30">
            <td width="200">
                <font class="subheadline">Name</font>
            </td>
            <td width="150">
                <font class="subheadline">Username</font>
            </td>
            <td>
                <font class="subheadline">Email Address</font>
            </td>
        </tr>

		<?php
        while ($row = mysql_fetch_object($result)) { ?>
    
            <tr height="20">
                <td>
                    <a class="subtlelink" href="edit/user.php?uid=<?=$row->id?>"><?=$row->first_name?> <?=$row->last_name?></a><?php if ($row->admin == "1") echo "<a title=\"Admin User\"><font color=\"#DD0000\"><strong>*</strong></font></a>"; ?></a>
                </td>
                <td>
                    <a class="subtlelink" href="edit/user.php?uid=<?=$row->id?>"><?=$row->username?></a>
                </td>
                <td>
                    <a class="subtlelink" href="edit/user.php?uid=<?=$row->id?>"><?=$row->email_address?></a>
                </td>
            </tr>
        <?php 
        } ?>

	</table>
	<?php 
} ?>
<?php
$sql = "SELECT id, first_name, last_name, username, email_address, admin
		FROM users
		WHERE active = '0'
		ORDER BY first_name asc, last_name asc";
$result = mysql_query($sql,$connection);
?>
<?php
if (mysql_num_rows($result) > 0) { ?>

    <BR><BR>
	<strong>Number of Inactive User Accounts:</strong> <?=mysql_num_rows($result)?>
    <BR><BR>
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
        <tr height="30">
            <td width="200">
                <font class="subheadline">Name</font>
            </td>
            <td width="150">
                <font class="subheadline">Username</font>
            </td>
            <td>
                <font class="subheadline">Email Address</font>
            </td>
        </tr>

		<?php
        while ($row = mysql_fetch_object($result)) { ?>
    
            <tr height="20">
                <td>
                    <a class="subtlelink" href="edit/user.php?uid=<?=$row->id?>"><?=$row->first_name?> <?=$row->last_name?><?php if ($row->admin == "1") echo "<a title=\"Admin User\"><font color=\"#DD0000\"><strong>*</strong></font></a>"; ?></a>
                </td>
                <td>
                    <a class="subtlelink" href="edit/user.php?uid=<?=$row->id?>"><?=$row->username?></a>
                </td>
                <td>
                    <a class="subtlelink" href="edit/user.php?uid=<?=$row->id?>"><?=$row->email_address?></a>
                </td>
            </tr>
        <?php 
        } ?>

	</table>
	<?php 
} ?>
<BR><font color="#DD0000"><strong>*</strong></font> = Admin Account
<?php include("../../_includes/footer.inc.php"); ?>
</body>
</html>