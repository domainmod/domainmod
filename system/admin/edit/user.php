<?php
/**
 * /system/admin/edit/user.php
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
include("../../../_includes/start-session.inc.php");
include("../../../_includes/init.inc.php");

// If the user isn't an administrator, redirect them to $full_redirect
$full_redirect = "../../../invalid.php";
include(DIR_INC . "auth/admin-user-check.inc.php");

include(DIR_INC . "config.inc.php");
include(DIR_INC . "database.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "timestamps/current-timestamp.inc.php");
include(DIR_INC . "auth/auth-check.inc.php");
include(DIR_INC . "classes/Error.class.php");

$error = new DomainMOD\Error();

$page_title = "Editing A User";
$software_section = "admin-user-edit";

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
		WHERE id = '" . $uid . "'";
$result = mysqli_query($connection, $sql);

while ($row = mysqli_fetch_object($result)) {
	
	if ($row->username == "admin" && $_SESSION['username'] != "admin") {

		$_SESSION['result_message'] .= "You're trying to edit an invalid user<BR>";

		header("Location: ../users.php");
		exit;
		
	}

}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_first_name != "" && $new_last_name != "" && $new_username != "" && $new_email_address != "") {

	// Check to see if another user already has the username
	$sql = "SELECT username
			FROM users
			WHERE username = '" . mysqli_real_escape_string($connection, $new_username) . "'
			AND id != '" . $new_uid . "'";
	$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
	$is_username_taken = mysqli_num_rows($result);
	if ($is_username_taken > 0) { $invalid_username = 1; $new_username = ""; }
	
	// Make sure they aren't trying to assign a reserved username
	if ($new_username == "admin" || $new_username == "administrator") { 

		$sql = "SELECT username
				FROM users
				WHERE username = '" . mysqli_real_escape_string($connection, $new_username) . "'
				AND id = '" . $new_uid . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		$is_it_my_username = mysqli_num_rows($result);
		
		if ($is_it_my_username == 0) {

			$invalid_username = 1; 
			$new_username = "";
			
		}

	}

}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_first_name != "" && $new_last_name != "" && $new_username != "" && $new_email_address != "" && $invalid_username != 1) {

	$sql = "UPDATE users
			SET first_name = '" . mysqli_real_escape_string($connection, $new_first_name). "',
				last_name = '" . mysqli_real_escape_string($connection, $new_last_name). "',
				username = '" . mysqli_real_escape_string($connection, $new_username) . "',
				email_address = '" . mysqli_real_escape_string($connection, $new_email_address) . "',
				admin = '" . $new_is_admin . "',
				active = '" . $new_is_active . "',
				update_time = '" . $current_timestamp . "'
			WHERE id = '" . $new_uid . "'";
	$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
	
	$_SESSION['result_message'] .= "User <font class=\"highlight\">" . $new_first_name . " " . $new_last_name . " (" . $new_username . ")</font> Updated<BR>";
	
	if ($_SESSION['username'] == $new_username) {
	
		$_SESSION['first_name'] = $new_first_name;
		$_SESSION['last_name'] = $new_last_name;
		$_SESSION['email_address'] = $new_email_address;
		
	}

	header("Location: ../users.php");
	exit;

} else {

	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
		if ($invalid_username == 1 || $new_username == "") $_SESSION['result_message'] .= "You have entered an invalid username<BR>";
		if ($new_first_name == "") $_SESSION['result_message'] .= "Enter the user's first name<BR>";
		if ($new_last_name == "") $_SESSION['result_message'] .= "Enter the user's last name<BR>";
		if ($new_email_address == "") $_SESSION['result_message'] .= "Enter the user's email address<BR>";
		
	} else {
		
		$sql = "SELECT first_name, last_name, username, email_address, admin, active
				FROM users
				WHERE id = '" . $uid . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		while ($row = mysqli_fetch_object($result)) {
			
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

	$_SESSION['result_message'] = "Are you sure you want to delete this User?<BR><BR><a href=\"user.php?uid=" . $uid . "&really_del=1\">YES, REALLY DELETE THIS USER</a><BR>";

}

if ($really_del == "1") {
	
	$sql = "SELECT id
			FROM users
			WHERE username = 'admin'";
	$result = mysqli_query($connection, $sql);
	while ($row = mysqli_fetch_object($result)) {
		$temp_uid = $row->id;
	}

	if ($uid == $temp_uid || $uid == $_SESSION['user_id']) {

		if ($uid == $temp_uid) $_SESSION['result_message'] = "The user <font class=\"highlight\">admin</font> cannot be deleted<BR>";
		if ($uid == $_SESSION['user_id']) $_SESSION['result_message'] = "You can't delete yourself<BR>";

	} else {

		$sql = "DELETE FROM user_settings
				WHERE user_id = '" . $uid . "'";
		$result = mysqli_query($connection, $sql);

		$sql = "DELETE FROM users
				WHERE id = '" . $uid . "'";
		$result = mysqli_query($connection, $sql);
		
		$_SESSION['result_message'] = "User <font class=\"highlight\">" . $new_first_name . " " . $new_last_name . " (" . $new_username . ")</font> Deleted<BR>";
		
		header("Location: ../users.php");
		exit;

	}

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
<form name="edit_user_form" method="post" action="user.php">
<strong>First Name (50)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR><input name="new_first_name" type="text" size="50" maxlength="50" value="<?php if ($new_first_name != "") echo htmlentities($new_first_name); ?>"><BR><BR>
<strong>Last Name (50)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR><input name="new_last_name" type="text" size="50" maxlength="50" value="<?php if ($new_last_name != "") echo htmlentities($new_last_name); ?>"><BR><BR>
<?php if ($new_username == "admin" || $new_username == "administrator") { ?>
	<strong>Username</strong><BR><BR><?php echo $new_username; ?><BR><BR>
<?php } else { ?>
	<strong>Username (30)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR><input name="new_username" type="text" size="20" maxlength="30" value="<?php if ($new_username != "") echo htmlentities($new_username); ?>"><BR><BR>
<?php } ?>
<strong>Email Address (100)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR><input name="new_email_address" type="text" size="50" maxlength="100" value="<?php if ($new_email_address != "") echo htmlentities($new_email_address); ?>"><BR><BR>
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

<?php } else { ?>

    <strong>Active Account?</strong>&nbsp;
    <select name="new_is_active">
        <option value="0">No</option>
        <option value="1"<?php if ($new_is_active == "1") echo " selected"; ?>>Yes</option>
    </select>

<?php } ?>

<?php if ($new_username == "admin" || $new_username == "administrator") { ?>
    <input type="hidden" name="new_username" value="admin">
    <input type="hidden" name="new_is_admin" value="1">
    <input type="hidden" name="new_is_active" value="1">
<?php } ?>

<input type="hidden" name="new_uid" value="<?php echo $uid; ?>">
<BR><BR>
<input type="submit" name="button" value="Update User &raquo;">
</form>
<BR><BR><a href="../reset-password.php?new_username=<?php echo $new_username; ?>">RESET AND EMAIL NEW PASSWORD TO USER</a><BR>
<BR><a href="user.php?uid=<?php echo $uid; ?>&del=1">DELETE THIS USER</a>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
