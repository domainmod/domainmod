<?php
// /reset-password.php
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
include("_includes/start-session.inc.php");
include("_includes/config.inc.php");
include("_includes/database.inc.php");
include("_includes/software.inc.php");
include("_includes/auth/login-check.inc.php");

$page_title = "Reset Password";
$software_section = "resetpassword";

$new_username = $_REQUEST['new_username'];

if ($new_username != "") {

   $sql = "SELECT username, email_address
           FROM users
		   WHERE username = '$new_username'
		     AND active = '1'";

   $result = mysql_query($sql,$connection) 
             or die('Query failed. 1. ' . mysql_error()); 

	if (mysql_num_rows($result) == 1) {
   
		while($row = mysql_fetch_object($result)) {
	
			$new_password = substr(md5(time()),0,8);
			
			$sql2 = "UPDATE users 
					 SET password = password('$new_password'), 
					 	 new_password = '1',
					 	 update_time = '$current_timestamp'
					 WHERE username = '$row->username'
					   AND email_address = '$row->email_address'";
			$result2 = mysql_query($sql2,$connection);
			
			include("_includes/email/send-new-password.inc.php");
					
			$_SESSION['session_result_message'] .= "Your new password has been emailed to you<BR>";
			
			header("Location: index.php");
			exit;
			
		}

	} else {

		$_SESSION['session_result_message'] .= "You have entered an invalid username<BR>";
		
	}

} else {

	if ($_SERVER['REQUEST_METHOD'] == 'POST') {

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
<?php include("_includes/header-login.inc.php"); ?>
<table align="center" border="0">
    <tr>
        <td align="center">
            <font class="headline">Reset Your Password</font>
            <BR><BR><BR>
            <form name="reset_password_form" method="post" action="<?=$PHP_SELF?>">
            <strong>Username:<strong>&nbsp;<input name="new_username" type="text" value="<?php echo $new_username; ?>" size="20" maxlength="20"><BR><BR><BR>
            <input type="submit" name="button" value="Reset Password &raquo;">
            </form>
            <BR><BR><BR>[<a class="subtlelink" href="index.php">Cancel Password Reset</a>]
        </td>
    </tr>
</table>
<?php include("_includes/footer-login.inc.php"); ?>
</body>
</html>