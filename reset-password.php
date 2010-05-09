<?php
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

$page_title = "Reset Password";
$software_section = "resetpassword";

$new_username = $_POST['new_username'];
$submitted = $_POST['submitted'];

if ($submitted == "YES" && $new_username != "") {

   $sql = "select username, email_address
           from users
		   where username = '$new_username'";

   $result = mysql_query($sql,$connection) 
             or die('Query failed. 1. ' . mysql_error()); 

	if (mysql_num_rows($result) == 1) {
   
		while($row = mysql_fetch_object($result)) {
	
			$new_password = substr(md5(time()),0,8);
			
			$sql2 = "update users set password = password('$new_password'), 
								 update_time = '$current_timestamp'
					 where username = '$row->username'
					 and email_address = '$row->email_address'";
			$result2 = mysql_query($sql2,$connection);
			
			include("_includes/email/send-new-password.inc.php");
					
			$_SESSION['session_result_message'] .= "Your new password has been emailed to you.<BR>";
			
			header("Location: index.php");
			exit;
			
		}

	} else {

		$_SESSION['session_result_message'] .= "You have entered an invalid username.<BR>";
		
	}

} else {

	if ($submitted == "YES") {

		if ($new_username == "") $_SESSION['session_result_message'] .= "Enter your username<BR>";
	}

}
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("_includes/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php include("_includes/header.inc.php"); ?>
<BR>
<form name="login_form" method="post" action="<?=$PHP_SELF?>">
<strong>Username:<strong><BR><input name="new_username" type="text" value="<?php echo $new_username; ?>" size="20" maxlength="20"><BR><BR>
<input name="submitted" type="hidden" value="YES">
<input type="submit" name="button" value="Reset Password &raquo;">
</form>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>