<?php
// reset-password.php
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
include("../../_includes/auth/admin-user-check.inc.php");

include("../../_includes/config.inc.php");
include("../../_includes/database.inc.php");
include("../../_includes/software.inc.php");
include("../../_includes/auth/auth-check.inc.php");

$page_title = "Reset Password";
$software_section = "system";

$new_username = $_GET['new_username'];

if ($new_username != "") {

   $sql = "SELECT id, username, email_address
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
			
			include("../../_includes/email/send-new-password.inc.php");
					
			$_SESSION['session_result_message'] .= "The password has been reset and emailed to the account holder<BR>";
			
			header("Location: edit/user.php?uid=$row->id");
			exit;
			
		}

	} else {

		$_SESSION['session_result_message'] .= "You have entered an invalid username<BR>";

		header("Location: users.php");
		exit;
		
	}

} else {

	if ($_SERVER['REQUEST_METHOD'] == 'GET') {

		if ($new_username == "") $_SESSION['session_result_message'] .= "Enter the username<BR>";

		header("Location: users.php");
		exit;

	}

}
?>