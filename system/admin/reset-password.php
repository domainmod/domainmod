<?php
// /system/admin/reset-password.php
// 
// DomainMOD is an open source application written in PHP & MySQL used to track and manage your web resources.
// Copyright (C) 2010 Greg Chetcuti
// 
// DomainMOD is free software; you can redistribute it and/or modify it under the terms of the GNU General
// Public License as published by the Free Software Foundation; either version 2 of the License, or (at your
// option) any later version.
// 
// DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the
// implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License
// for more details.
// 
// You should have received a copy of the GNU General Public License along with DomainMOD. If not, please see
// http://www.gnu.org/licenses/
?>
<?php
include("../../_includes/start-session.inc.php");

// If the user isn't an administrator, redirect them to $full_redirect
$full_redirect = "../../invalid.php";
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
			
			$sql_update = "UPDATE users 
						   SET password = password('$new_password'), 
						   	   new_password = '1',
							   update_time = '$current_timestamp'
						   WHERE username = '$row->username'
						     AND email_address = '$row->email_address'";
			$result_update = mysql_query($sql_update,$connection);
			
			include("../../_includes/email/send-new-password.inc.php");
					
			$_SESSION['result_message'] .= "The password has been reset and emailed to the account holder<BR>";
			
			header("Location: edit/user.php?uid=$row->id");
			exit;
			
		}

	} else {

		$_SESSION['result_message'] .= "You have entered an invalid username<BR>";

		header("Location: users.php");
		exit;
		
	}

} else {

	if ($_SERVER['REQUEST_METHOD'] == 'GET') {

		if ($new_username == "") $_SESSION['result_message'] .= "Enter the username<BR>";

		header("Location: users.php");
		exit;

	}

}
?>
