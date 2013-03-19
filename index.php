<?php
// index.php
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

include("_includes/config.inc.php");
include("_includes/database.inc.php");
include("_includes/software.inc.php");
include("_includes/auth/login-check.inc.php");

include("_includes/system/installation-check.inc.php");

if ($_SESSION['session_installation_mode'] == 1) {
	
	$page_title = "Installation";
	$software_section = "installation";

} else {

	$page_title = "Login";
	$software_section = "login";

}

$new_username = $_POST['new_username'];
$new_password = $_POST['new_password'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_username != "" && $new_password != "") {
	
	$sql = "select id, first_name, last_name, username, email_address, password, admin
			from users
			where username = '$new_username'
			and password = password('$new_password')
			and active = '1'";
	$result = mysql_query($sql,$connection) or die('Login Failed'); 
	
   if (mysql_num_rows($result) == 1) {
	   
	   while ($row = mysql_fetch_object($result)) {

			include("_includes/system/check-for-missing-domain-fees.inc.php");
			include("_includes/system/check-for-missing-ssl-fees.inc.php");

			$_SESSION['session_user_id'] = $row->id;
			$_SESSION['session_first_name'] = $row->first_name;
			$_SESSION['session_last_name'] = $row->last_name;
			$_SESSION['session_username'] = $row->username;
			$_SESSION['session_email_address'] = $row->email_address;
			if ($row->admin == 1) $_SESSION['session_is_admin'] = 1;
			$_SESSION['session_is_logged_in'] = 1;
			
			header("Location: _includes/auth/login-checks/main.inc.php");
			exit;
	   }

	} else {

		$_SESSION['session_result_message'] = "Login Failed<BR>";
	   
   }
	
} else {
	

	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
		if ($new_username == "" && $new_password == "") {
			$_SESSION['session_result_message'] .= "Enter your username & password<BR>";
		} elseif ($new_username == "" || $new_password == "") {
			if ($new_username == "") $_SESSION['session_result_message'] .= "Enter your username<BR>";
			if ($new_password == "") $_SESSION['session_result_message'] .= "Enter your password<BR>";
		}
	}

}
$new_password = "";
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("_includes/head-tags.inc.php"); ?>
</head>
<?php if ($new_username == "") { ?>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php } else { ?>
<body onLoad="document.forms[0].elements[1].focus()";>
<?php } ?>
<?php include("_includes/header.inc.php"); ?>
<?php if ($_SESSION['session_installation_mode'] != 1) { ?>
<BR>
<form name="login_form" method="post" action="<?=$PHP_SELF?>">
<strong>Username:<strong><BR><input name="new_username" type="text" value="<?php echo $new_username; ?>" size="20" maxlength="20"><BR><BR>
<strong>Password:</strong><BR><input name="new_password" type="password" id="new_password" size="20" maxlength="20"><br>
<font size="1"><i>(<a href="reset-password.php"><i>Forgot your Password?</i></a>)</i></font><BR><BR>
<input type="submit" name="button" value="Login">
</form>
<?php } ?>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>