<?php
// edit-profile.php
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

include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");
include("../_includes/auth/auth-check.inc.php");
$page_title = "Edit User";
$software_section = "system";

// Form Variables
$new_first_name = $_POST['new_first_name'];
$new_last_name = $_POST['new_last_name'];
$new_username = $_POST['new_username'];
$new_email_address = $_POST['new_email_address'];


if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_first_name != "" && $new_last_name != "" && $new_username != "" && $new_email_address != "") {

	// If the username has changed, check to see if it is already taken
	if ($_SESSION['session_username'] != $new_username) {
		
		$sql = "select username
				from users
				where username = '" . $new_username . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$matching_usernames = mysql_num_rows($result);
	
		if ($matching_usernames > 0 || $new_username == "admin" || $new_username == "administrator") {
			
			$invalid_username = 1;

		}
		
	}

}


if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_first_name != "" && $new_last_name != "" && $new_username != "" && $new_email_address != "" && $invalid_username != 1) {
	
	$sql = "update users
			set first_name = '$new_first_name',
			    last_name = '$new_last_name',
				username = '$new_username',
				email_address = '$new_email_address',
				update_time = '$current_timestamp'
			where id = '" . $_SESSION['session_user_id'] . "'
			  and username = '" . $_SESSION['session_username'] . "'
			  and email_address = '" . $_SESSION['session_email_address'] . "'";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$_SESSION['session_first_name'] = $new_first_name;
	$_SESSION['session_last_name'] = $new_last_name;
	$_SESSION['session_username'] = $new_username;
	$_SESSION['session_email_address'] = $new_email_address;

	$_SESSION['session_result_message'] .= "Your profile was updated.<BR>";

} else {


	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
		if ($invalid_username == 1) $_SESSION['session_result_message'] .= "You have entered an invalid username.<BR>";
		if ($new_first_name == "") $_SESSION['session_result_message'] .= "Enter your first name.<BR>";
		if ($new_last_name == "") $_SESSION['session_result_message'] .= "Enter your last name.<BR>";
		if ($new_username == "") $_SESSION['session_result_message'] .= "Enter your username.<BR>";
		if ($new_email_address == "") $_SESSION['session_result_message'] .= "Enter your email address.<BR>";
		
	} else {
		
		$sql = "select first_name, last_name, username, email_address
				from users
				where id = '" . $_SESSION['session_user_id'] . "'
				  and username = '" . $_SESSION['session_username'] . "'
				  and email_address = '" . $_SESSION['session_email_address'] . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		while ($row = mysql_fetch_object($result)) {
			
			$new_first_name = $row->first_name;
			$new_last_name = $row->last_name;
			$new_username = $row->username;
			$new_email_address = $row->email_address;

		}

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
<strong>First Name:</strong><BR><input name="new_first_name" type="text" size="50" maxlength="50" value="<?php if ($new_first_name != "") echo $new_first_name; ?>"><BR><BR>
<strong>Last Name:</strong><BR><input name="new_last_name" type="text" size="50" maxlength="50" value="<?php if ($new_last_name != "") echo $new_last_name; ?>"><BR><BR>
<strong>Username:</strong><BR><input name="new_username" type="text" size="50" maxlength="20" value="<?php if ($new_username != "") echo $new_username; ?>"><BR><BR>
<strong>Email Address:</strong><BR><input name="new_email_address" type="text" size="50" maxlength="255" value="<?php if ($new_email_address != "") echo $new_email_address; ?>"><BR><BR>
<input type="submit" name="button" value="Update Profile &raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>