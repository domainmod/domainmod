<?php
/**
 * /system/admin/users.php
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
<?php
include("../../_includes/start-session.inc.php");
include("../../_includes/init.inc.php");
include(DIR_INC . "head.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "database.inc.php");
include(DIR_INC . "auth/auth-check.inc.php");
include(DIR_INC . "auth/admin-user-check.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$error = new DomainMOD\Error();
$time = new DomainMOD\Timestamp();

$page_title = $software_title . " Users";
$software_section = "admin-user-list";

$export_data = $_GET['export_data'];

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

if ($export_data == "1") {

	$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $export = new DomainMOD\Export();
    $export_file = $export->openFile('user_list', strtotime($time->time()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        'Status',
        'First Name',
        'Last Name',
        'Username',
        'Email Address',
        'Is Admin?',
        'Default Currency',
        'Default Timezone',
        'Number of Logins',
        'Last Login',
        'Inserted',
        'Updated'
    );
    $export->writeRow($export_file, $row_contents);

    if (mysqli_num_rows($result) > 0) {
	
		while ($row = mysqli_fetch_object($result)) {
			
			if ($row->admin == "1") {
				
				$is_admin = "1";
				
			} else {
				
				$is_admin = "";
				
			}

            $row_contents = array(
                'Active',
                $row->first_name,
                $row->last_name,
                $row->username,
                $row->email_address,
                $is_admin,
                $row->default_currency,
                $row->default_timezone,
                $row->number_of_logins,
                $row->last_login,
                $row->insert_time,
                $row->update_time
            );
            $export->writeRow($export_file, $row_contents);

        }
			
	}
	
	$sql = "SELECT u.id, u.first_name, u.last_name, u.username, u.email_address, u.admin, u.number_of_logins, u.last_login, u.insert_time, u.update_time, us.default_timezone, us.default_currency
			FROM users AS u, user_settings AS us
			WHERE u.id = us.user_id
			  AND u.active = '0'
			ORDER BY u.first_name, u.last_name, u.username, u.email_address";
	$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
	
	if (mysqli_num_rows($result) > 0) {
	
		while ($row = mysqli_fetch_object($result)) {
	
			if ($row->admin == "1") {
				
				$is_admin = "1";
				
			} else {
				
				$is_admin = "";
				
			}

            $row_contents = array(
                'Inactive',
                $row->first_name,
                $row->last_name,
                $row->username,
                $row->email_address,
                $is_admin,
                $row->default_currency,
                $row->default_timezone,
                $row->number_of_logins,
                $row->last_login,
                $row->insert_time,
                $row->update_time
            );
            $export->writeRow($export_file, $row_contents);

        }

	}

    $export->closeFile($export_file);

}
?>
<?php include(DIR_INC . "doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
Below is a list of all users that have access to <?php echo $software_title; ?>.<BR><BR>
[<a href="users.php?export_data=1">EXPORT</a>]<?php

$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

if (mysqli_num_rows($result) > 0) { ?>

    <table class="main_table" cellpadding="0" cellspacing="0">
    <tr class="main_table_row_heading_active">
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Active Users (<?php echo mysqli_num_rows($result); ?>)</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Username</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Email Address</font>
        </td>
    </tr><?php

    while ($row = mysqli_fetch_object($result)) { ?>

        <tr class="main_table_row_active">
            <td class="main_table_cell_active">
                <a class="invisiblelink" href="edit/user.php?uid=<?php echo $row->id; ?>"><?php echo $row->first_name; ?> <?php echo $row->last_name; ?></a><?php if ($row->admin == "1") echo "<a title=\"Admin User\"><font class=\"default_highlight\">*</font></a>"; ?>
            </td>
            <td class="main_table_cell_active">
                <a class="invisiblelink" href="edit/user.php?uid=<?php echo $row->id; ?>"><?php echo $row->username; ?></a>
            </td>
            <td class="main_table_cell_active">
                <a class="invisiblelink" href="edit/user.php?uid=<?php echo $row->id; ?>"><?php echo $row->email_address; ?></a>
            </td>
        </tr><?php 
	}
		
}

$sql = "SELECT id, first_name, last_name, username, email_address, admin
		FROM users
		WHERE active = '0'
		ORDER BY first_name, last_name, username, email_address";
$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

if (mysqli_num_rows($result) > 0) { ?>

    <tr class="main_table_row_heading_inactive">
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">Inactive Users (<?php echo mysqli_num_rows($result); ?>)</font>
        </td>
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">Username</font>
        </td>
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">Email Address</font>
        </td>
    </tr><?php

    while ($row = mysqli_fetch_object($result)) { ?>

        <tr class="main_table_row_inactive">
            <td class="main_table_cell_inactive">
                <a class="invisiblelink" href="edit/user.php?uid=<?php echo $row->id; ?>"><?php echo $row->first_name; ?> <?php echo $row->last_name; ?><?php if ($row->admin == "1") echo "<a title=\"Admin User\"><font class=\"default_highlight\">*</font></a>"; ?></a>
            </td>
            <td class="main_table_cell_inactive">
                <a class="invisiblelink" href="edit/user.php?uid=<?php echo $row->id; ?>"><?php echo $row->username; ?></a>
            </td>
            <td class="main_table_cell_inactive">
                <a class="invisiblelink" href="edit/user.php?uid=<?php echo $row->id; ?>"><?php echo $row->email_address; ?></a>
            </td>
        </tr><?php 

    }
	
} ?>
</table>
<BR><font class="default_highlight">*</font> = Admin Account
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
