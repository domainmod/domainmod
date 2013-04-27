<?php
// /system/change-password.php
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

$page_title = "Change Password";
$software_section = "system";

// Form Variables
$new_password = $_POST['new_password'];
$new_password_confirmation = $_POST['new_password_confirmation'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_password != "" && $new_password_confirmation != "" && $new_password == $new_password_confirmation) {

	$sql = "SELECT id 
			FROM users 
			WHERE id = '" . $_SESSION['user_id'] . "' 
			  AND email_address = '" . $_SESSION['email_address'] . "'";
	$result = mysql_query($sql,$connection);

   if (mysql_num_rows($result) == 1) {

		$sql_update = "UPDATE users 
					   SET password = password('$new_password'), 
					   	   new_password = '0', 
						   update_time = '$current_timestamp'
					   WHERE id = '" . $_SESSION['user_id'] . "' 
					     AND email_address = '" . $_SESSION['email_address'] . "'";
		$result_update = mysql_query($sql_update,$connection) or die("Your password could not be updated. Please try again later.");

		if ($_SESSION['running_login_checks'] == 1) {

		$_SESSION['result_message'] .= "Your password has been changed<BR>";

			header("Location: ../_includes/auth/login-checks/main.inc.php");
			exit;

		}

		$_SESSION['result_message'] .= "Your password has been changed<BR>";

		header("Location: index.php");
		exit;

   } else {

		$_SESSION['result_message'] .= "Your password could not be updated<BR>";
		$_SESSION['result_message'] .= "If the problem persists please contact your administrator<BR>";

   }


} else {


	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
		if ($new_password == "" && $new_password_confirmation == "") {
		
			$_SESSION['result_message'] .= "Your passwords were left blank<BR>";

		} else {

			$_SESSION['result_message'] .= "Your passwords didn't match<BR>";
		
		}
		
	}
}
?>
<?php include("../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php if ($_SESSION['running_login_checks'] == 1) { ?>
	<?php include("../_includes/header-login.inc.php"); ?>
	<div align="center">
<?php } else { ?>
	<?php include("../_includes/header.inc.php"); ?>
<?php } ?>
<form name="change_password_form" method="post" action="<?=$PHP_SELF?>">
<strong>New Password</strong><BR><input type="password" name="new_password" size="20">
<BR><BR>
<strong>Confirm New Password</strong><BR><input type="password" name="new_password_confirmation" size="20">
<BR><BR>
<input type="submit" name="button" value="Change Password &raquo;">
</form>
<?php if ($_SESSION['running_login_checks'] == 1) { ?>
	</div>
	<?php include("../_includes/footer-login.inc.php"); ?>
<?php } else { ?>
	<?php include("../_includes/footer.inc.php"); ?>
<?php } ?>
</body>
</html>