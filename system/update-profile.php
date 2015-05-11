<?php
/**
 * /system/update-profile.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (C) 2010-2015 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: http://chetcuti.com
 *
 * DomainMOD is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later
 * version.
 *
 * DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with DomainMOD. If not, see
 * http://www.gnu.org/licenses/.
 *
 */
?>
<?php
include("../_includes/start-session.inc.php");
include("../_includes/init.inc.php");
include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "database.inc.php");
include(DIR_INC . "auth/auth-check.inc.php");
require_once(DIR_INC . "classes/Autoloader.class.php");

spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$error = new DomainMOD\Error();
$time = new DomainMOD\Timestamp();

$page_title = "Update Profile";
$software_section = "system-update-profile";

$new_first_name = $_POST['new_first_name'];
$new_last_name = $_POST['new_last_name'];
$new_email_address = $_POST['new_email_address'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_email_address != "" && $new_first_name != "" && $new_last_name != "") {

	$sql = "SELECT id 
			FROM users 
			WHERE id = '" . $_SESSION['user_id'] . "' 
			  AND email_address = '" . $_SESSION['email_address'] . "'";
	$result = mysqli_query($connection, $sql);

   if (mysqli_num_rows($result) == 1) {

		$sql_update = "UPDATE users 
					   SET first_name = '" . mysqli_real_escape_string($connection, $new_first_name) . "',
					   	   last_name = '" . mysqli_real_escape_string($connection, $new_last_name) . "',
						   email_address = '$new_email_address', 
						   update_time = '" . $time->time() . "'
					   WHERE id = '" . $_SESSION['user_id'] . "' 
					     AND email_address = '" . $_SESSION['email_address'] . "'";
		$result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);
		
		$_SESSION['email_address'] = $new_email_address;
		$_SESSION['first_name'] = $new_first_name;
		$_SESSION['last_name'] = $new_last_name;

		$_SESSION['result_message'] .= "Your profile was updated<BR>";

		header("Location: index.php");
		exit;

   } else {
	   
		$_SESSION['result_message'] .= "Your profile could not be updated<BR>";
		$_SESSION['result_message'] .= "If the problem persists please contact your administrator<BR>";

   }

} else {


	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
	   if ($new_email_address == "") $_SESSION['result_message'] .= "Your email address could not be updated<BR>";
	   if ($new_first_name == "") $_SESSION['result_message'] .= "Your first name could not be updated<BR>";
	   if ($new_last_name == "") $_SESSION['result_message'] .= "Your last name could not be updated<BR>";

	}

}
?>
<?php include(DIR_INC . "doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<form name="change_email_address_form" method="post">
<strong>First Name (50):</strong><BR><BR>
<input name="new_first_name" type="text" size="50" maxlength="50" value="<?php if ($new_first_name != "") { echo $new_first_name; } else { echo $_SESSION['first_name']; }?>">
<BR><BR>
<strong>Last Name (50):</strong><BR><BR>
<input name="new_last_name" type="text" size="50" maxlength="50" value="<?php if ($new_last_name != "") { echo $new_last_name; } else { echo $_SESSION['last_name']; }?>">
<BR><BR>
<strong>Email Address (100):</strong><BR><BR>
<input name="new_email_address" type="text" size="50" maxlength="100" value="<?php if ($new_email_address != "") { echo $new_email_address; } else { echo $_SESSION['email_address']; }?>">
<BR><BR>
<input type="submit" name="button" value="Update Profile &raquo;">
</form>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
