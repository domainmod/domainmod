<?php
// /system/admin/edit/user.php
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
include("../../../_includes/start-session.inc.php");

// If the user isn't an administrator, redirect them to $full_redirect
$full_redirect = "index.php";
include("../../../_includes/auth/admin-user-check.inc.php");

include("../../../_includes/config.inc.php");
include("../../../_includes/database.inc.php");
include("../../../_includes/software.inc.php");
include("../../../_includes/timestamps/current-timestamp.inc.php");
include("../../../_includes/auth/auth-check.inc.php");

$page_title = "Edit User";
$software_section = "system";

// 'Delete User' Confirmation Variables
$del = $_GET['del'];
$really_del = $_GET['really_del'];

$uid = $_GET['uid'];

if ($new_uid == "") $new_uid = $uid;

// Form Variables
$new_first_name = $_POST['new_first_name'];
$new_last_name = $_POST['new_last_name'];
$new_username = $_POST['new_username'];
$new_email_address = $_POST['new_email_address'];
$new_is_admin = $_POST['new_is_admin'];
$new_is_active = $_POST['new_is_active'];
$new_uid = $_POST['new_uid'];

$sql = "SELECT username
		FROM users
		WHERE id = '$uid'";
$result = mysql_query($sql,$connection);

while ($row = mysql_fetch_object($result)) {
	
	if ($row->username == "admin" && $_SESSION['session_username'] != "admin") {

		$_SESSION['session_result_message'] .= "You're trying to edit an invalid user<BR>";

		header("Location: ../users.php");
		exit;
		
	}

}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_first_name != "" && $new_last_name != "" && $new_username != "" && $new_email_address != "") {

	// Check to see if another user already has the username
	$sql = "SELECT username
			FROM users
			WHERE username = '$new_username'
			AND id != '$new_uid'";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	$is_username_taken = mysql_num_rows($result);
	if ($is_username_taken > 0) { $invalid_username = 1; $new_username = ""; }
	
	// Make sure they aren't trying to assign a reserved username
	if ($new_username == "admin" || $new_username == "administrator") { 

		$sql = "SELECT username
				FROM users
				WHERE username = '$new_username'
				AND id = '$new_uid'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		$is_it_my_username = mysql_num_rows($result);
		
		if ($is_it_my_username == 0) {

			$invalid_username = 1; 
			$new_username = "";
			
		}

	}

}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_first_name != "" && $new_last_name != "" && $new_username != "" && $new_email_address != "" && $invalid_username != 1) {

	$sql = "UPDATE users
			SET first_name = '$new_first_name',
				last_name = '$new_last_name',
				username = '$new_username',
				email_address = '$new_email_address',
				admin = '$new_is_admin',
				active = '$new_is_active',
				update_time = '$current_timestamp'
			WHERE id = '$new_uid'";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$_SESSION['session_result_message'] .= "User <font class=\"highlight\">$new_first_name $new_last_name ($new_username)</font> Updated<BR>";
	
	if ($_SESSION['session_username'] == $new_username) {
	
		$_SESSION['session_first_name'] = $new_first_name;
		$_SESSION['session_last_name'] = $new_last_name;
		$_SESSION['session_email_address'] = $new_email_address;
		
	}
	
} else {

	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
		if ($invalid_username == 1 || $new_username == "") $_SESSION['session_result_message'] .= "You have entered an invalid username<BR>";
		if ($new_first_name == "") $_SESSION['session_result_message'] .= "Enter the user's first name<BR>";
		if ($new_last_name == "") $_SESSION['session_result_message'] .= "Enter the user's last name<BR>";
		if ($new_email_address == "") $_SESSION['session_result_message'] .= "Enter the user's email address<BR>";
		
	} else {
		
		$sql = "SELECT first_name, last_name, username, email_address, admin, active
				FROM users
				WHERE id = '$uid'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		while ($row = mysql_fetch_object($result)) {
			
			$new_first_name = $row->first_name;
			$new_last_name = $row->last_name;
			$new_username = $row->username;
			$new_email_address = $row->email_address;
			$new_is_admin = $row->admin;
			$new_is_active = $row->active;

		}

	}
}
if ($del == "1") {

	$_SESSION['session_result_message'] = "Are you sure you want to delete this User?<BR><BR><a href=\"$PHP_SELF?uid=$uid&really_del=1\">YES, REALLY DELETE THIS USER</a><BR>";

}

if ($really_del == "1") {

	$sql = "DELETE FROM users 
			WHERE id = '$uid'";
	$result = mysql_query($sql,$connection);

	$sql = "DELETE FROM user_settings
			WHERE user_id = '$uid'";
	$result = mysql_query($sql,$connection);
	
	$_SESSION['session_result_message'] = "User <font class=\"highlight\">$new_first_name $new_last_name ($new_username)</font> Deleted<BR>";
	
	header("Location: ../users.php");
	exit;

}
?>
<?php include("../../../_includes/doctype.inc.php"); ?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../../../_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../../../_includes/header.inc.php"); ?>
<form name="edit_user_form" method="post" action="<?=$PHP_SELF?>">
<strong>First Name</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR><input name="new_first_name" type="text" size="50" maxlength="50" value="<?php if ($new_first_name != "") echo $new_first_name; ?>"><BR><BR>
<strong>Last Name</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR><input name="new_last_name" type="text" size="50" maxlength="50" value="<?php if ($new_last_name != "") echo $new_last_name; ?>"><BR><BR>
<?php if ($new_username == "admin" || $new_username == "administrator") { ?>
	<strong>Username</strong><BR><BR><?=$new_username?><BR><BR>
<?php } else { ?>
	<strong>Username</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR><input name="new_username" type="text" size="50" maxlength="20" value="<?php if ($new_username != "") echo $new_username; ?>"><BR><BR>
<?php } ?>
<strong>Email Address</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR><input name="new_email_address" type="text" size="50" maxlength="255" value="<?php if ($new_email_address != "") echo $new_email_address; ?>"><BR><BR>
<?php if ($new_username == "admin" || $new_username == "administrator") { ?>

    <strong>Admin Privileges?</strong>&nbsp;&nbsp;Yes
    <BR><BR>

<?php } else { ?>

    <strong>Admin Privileges?</strong>&nbsp;
    <select name="new_is_admin">
        <option value="0">No</option>
        <option value="1"<?php if ($new_is_admin == "1") echo " selected"; ?>>Yes</option>
    </select>
    <BR><BR>

<?php } ?>

<?php if ($new_username == "admin" || $new_username == "administrator") { ?>

    <strong>Active Account?</strong>&nbsp;&nbsp;Yes
    <BR><BR><BR>

<?php } else { ?>

    <strong>Active Account?</strong>&nbsp;
    <select name="new_is_active">
        <option value="0">No</option>
        <option value="1"<?php if ($new_is_active == "1") echo " selected"; ?>>Yes</option>
    </select>
    <BR><BR><BR>

<?php } ?>

<?php if ($new_username == "admin" || $new_username == "administrator") { ?>
    <input type="hidden" name="new_username" value="admin">
    <input type="hidden" name="new_is_admin" value="1">
    <input type="hidden" name="new_is_active" value="1">
<?php } ?>

<input type="hidden" name="new_uid" value="<?=$uid?>">
<input type="submit" name="button" value="Update User &raquo;">
</form>
<BR><BR><a href="../reset-password.php?new_username=<?=$new_username?>">RESET AND EMAIL NEW PASSWORD TO USER</a><BR>
<BR><a href="<?=$PHP_SELF?>?uid=<?=$uid?>&del=1">DELETE THIS USER</a>
<?php include("../../../_includes/footer.inc.php"); ?>
</body>
</html>