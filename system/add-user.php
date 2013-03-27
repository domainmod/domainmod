<?php
// add-user.php
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
include("../_includes/auth/admin-user-check.inc.php");

include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");
include("../_includes/auth/auth-check.inc.php");

$page_title = "Add User";
$software_section = "system";

// Form Variables
$new_first_name = $_POST['new_first_name'];
$new_last_name = $_POST['new_last_name'];
$new_username = $_POST['new_username'];
$new_email_address = $_POST['new_email_address'];
$new_admin = $_POST['new_admin'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_first_name != "" && $new_last_name != "" && $new_username != "" && $new_email_address != "") {
	
	$new_password = substr(md5(time()),0,8);
	
	$sql = "insert into users
				(first_name, last_name, username, email_address, password, new_password, admin, insert_time) VALUES
				('$new_first_name', '$new_last_name', '$new_username', '$new_email_address', password('$new_password'), '1', '$new_admin', '$current_timestamp')";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$_SESSION['session_result_message'] .= "The user '" . $new_first_name . " " . $new_last_name . "' (" . $new_username . " / " . $new_password . ") was created.<BR>";

} else {


	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
		if ($new_first_name == "") $_SESSION['session_result_message'] .= "Enter the new user's first name.<BR>";
		if ($new_last_name == "") $_SESSION['session_result_message'] .= "Enter the new user's last name.<BR>";
		if ($new_username == "") $_SESSION['session_result_message'] .= "Enter the new user's username.<BR>";
		if ($new_email_address == "") $_SESSION['session_result_message'] .= "Enter the new user's email address.<BR>";
		
	}
}
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php include("../_includes/header.inc.php"); ?>
<form name="change_password_form" method="post" action="<?=$PHP_SELF?>">
<strong>First Name:</strong><BR><input name="new_first_name" type="text" size="50" maxlength="50"><BR><BR>
<strong>Last Name:</strong><BR><input name="new_last_name" type="text" size="50" maxlength="50"><BR><BR>
<strong>Username:</strong><BR><input name="new_username" type="text" size="50" maxlength="20"><BR><BR>
<strong>Email Address:</strong><BR><input name="new_email_address" type="text" size="50" maxlength="255"><BR><BR>
<strong>Admin Privileges?</strong><BR>
<select name="new_admin"><option value="0">No</option><option value="1">Yes</option></select>
<BR><BR>
<input type="submit" name="button" value="Add User &raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>