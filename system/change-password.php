<?php
// change-password.php
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
$page_title = "Change Password";
$software_section = "system";

// Form Variables
$new_password = $_POST['new_password'];
$new_password_confirmation = $_POST['new_password_confirmation'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_password != "" && $new_password_confirmation != "" && $new_password == $new_password_confirmation) {

	$sql = "select id 
			from users 
			where id = '" . $_SESSION['session_user_id'] . "' 
			and email_address = '" . $_SESSION['session_email_address'] . "'";
	$result = mysql_query($sql,$connection);

   if (mysql_num_rows($result) == 1) {

		$sql2 = "update users 
				 set password = password('$new_password'), 
				 new_password = '0', 
				 update_time = '$current_timestamp'
				 where id = '" . $_SESSION['session_user_id'] . "' 
				 and email_address = '" . $_SESSION['session_email_address'] . "'";
		$result2 = mysql_query($sql2,$connection) or die("Your password could not be updated. Please try again later.");

		if ($_SESSION['session_running_login_checks'] == 1) {

		$_SESSION['session_result_message'] .= "Your password was changed.<BR>";

			header("Location: ../_includes/auth/login-checks/main.inc.php");
			exit;

		}

		$_SESSION['session_result_message'] .= "Your password was changed.<BR>";

		header("Location: index.php");
		exit;

   } else {

		$_SESSION['session_result_message'] .= "Your password could not be updated.<BR>";
		$_SESSION['session_result_message'] .= "If the problem persists, please contact your administrator.<BR>";

   }


} else {


	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
		if ($new_password == "" && $new_password_confirmation == "") {
		
			$_SESSION['session_result_message'] .= "Your passwords were blank.<BR>";

		} else {

			$_SESSION['session_result_message'] .= "Your passwords didn't match.<BR>";
		
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
New Password<BR><input type="password" name="new_password" size="20">
<BR><BR>
Confirm New Password<BR><input type="password" name="new_password_confirmation" size="20">
<BR><BR>
<input type="submit" name="button" value="Change Password &raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>