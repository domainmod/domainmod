<?php
// /system/update-profile.php
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
include("../_includes/start-session.inc.php");
include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");
include("../_includes/auth/auth-check.inc.php");

$page_title = "Update Profile";
$software_section = "system";

// Form Variables
$new_first_name = $_POST['new_first_name'];
$new_last_name = $_POST['new_last_name'];
$new_email_address = $_POST['new_email_address'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_email_address != "" && $new_first_name != "" && $new_last_name != "") {

	$sql = "SELECT id 
			FROM users 
			WHERE id = '" . $_SESSION['session_user_id'] . "' 
			  AND email_address = '" . $_SESSION['session_email_address'] . "'";
	$result = mysql_query($sql,$connection);

   if (mysql_num_rows($result) == 1) {

		$sql2 = "UPDATE users 
				 SET first_name = '" . mysql_real_escape_string($new_first_name) . "',
				 	 last_name = '" . mysql_real_escape_string($new_last_name) . "',
					 email_address = '$new_email_address', 
				 	 update_time = '$current_timestamp'
				 WHERE id = '" . $_SESSION['session_user_id'] . "' 
				   AND email_address = '" . $_SESSION['session_email_address'] . "'";
		$result2 = mysql_query($sql2,$connection) or die("Your profile could not be updated. Please try again later.");
		
		$_SESSION['session_email_address'] = $new_email_address;
		$_SESSION['session_first_name'] = $new_first_name;
		$_SESSION['session_last_name'] = $new_last_name;

		$_SESSION['session_result_message'] .= "Your profile was updated<BR>";

		header("Location: index.php");
		exit;

   } else {
	   
		$_SESSION['session_result_message'] .= "Your profile could not be updated<BR>";
		$_SESSION['session_result_message'] .= "If the problem persists please contact your administrator<BR>";

   }

} else {


	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
	   if ($new_email_address == "") $_SESSION['session_result_message'] .= "Your email address could not be updated<BR>";
	   if ($new_first_name == "") $_SESSION['session_result_message'] .= "Your first name could not be updated<BR>";
	   if ($new_last_name == "") $_SESSION['session_result_message'] .= "Your last name could not be updated<BR>";

	}

}
?>
<?php include("../_includes/doctype.inc.php"); ?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../_includes/header.inc.php"); ?>
<form name="change_email_address_form" method="post" action="<?=$PHP_SELF?>">
<strong>First Name:</strong><BR><BR>
<input name="new_first_name" type="text" size="50" maxlength="50" value="<?php if ($new_first_name != "") { echo $new_first_name; } else { echo $_SESSION['session_first_name']; }?>">
<BR><BR>
<strong>Last Name:</strong><BR><BR>
<input name="new_last_name" type="text" size="50" maxlength="50" value="<?php if ($new_last_name != "") { echo $new_last_name; } else { echo $_SESSION['session_last_name']; }?>">
<BR><BR>
<strong>Email Address:</strong><BR><BR>
<input name="new_email_address" type="text" size="50" maxlength="255" value="<?php if ($new_email_address != "") { echo $new_email_address; } else { echo $_SESSION['session_email_address']; }?>">
<BR><BR><BR>
<input type="submit" name="button" value="Update Profile &raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>