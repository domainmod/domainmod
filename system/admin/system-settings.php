<?php
/**
 * /system/admin/system-settings.php
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
include("../../_includes/start-session.inc.php");
include("../../_includes/init.inc.php");
include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "database.inc.php");
include(DIR_INC . "auth/auth-check.inc.php");
include(DIR_INC . "auth/admin-user-check.inc.php");
require_once(DIR_INC . "classes/Autoloader.class.php");
include(DIR_INC . "timestamps/current-timestamp.inc.php");

spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$error = new DomainMOD\Error();

$page_title = "System Settings";
$software_section = "admin-system-settings";

// Form Variables
$new_email_address = $_POST['new_email_address'];
$new_full_url = $_POST['new_full_url'];
$new_expiration_email_days = $_POST['new_expiration_email_days'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_email_address != "" && $new_full_url != "" && $new_expiration_email_days != "") {

	$sql = "UPDATE settings
			SET full_url = '$new_full_url',
				email_address = '$new_email_address',
				expiration_email_days = '$new_expiration_email_days',
				update_time = '$current_timestamp'";
	$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

	$_SESSION['system_full_url'] = $new_full_url;
	$_SESSION['system_email_address'] = $new_email_address;
	$_SESSION['system_expiration_email_days'] = $new_expiration_email_days;

	$_SESSION['result_message'] .= "The System Settings were updated<BR>";

	header("Location: ../index.php");
	exit;

} else {


	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
		if ($new_email_address == "") $_SESSION['result_message'] .= "Enter the system email address<BR>";
		if ($new_full_url == "") $_SESSION['result_message'] .= "Enter the full URL of your " . $software_title . " installation<BR>";
		if ($new_expiration_email_days == "") $_SESSION['result_message'] .= "Enter the number of days to display in expiration emails<BR>";
		
	} else {
		
		$sql = "SELECT full_url, email_address, expiration_email_days
				FROM settings";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		while ($row = mysqli_fetch_object($result)) {
			
			$new_full_url = $row->full_url;
			$new_email_address = $row->email_address;
			$new_expiration_email_days = $row->expiration_email_days;

		}

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
<form name="system_settings_form" method="post">
<strong>Full <?php echo $software_title; ?> URL (100):</strong><BR><BR>
Enter the full URL of your <?php echo $software_title; ?> installation, excluding the trailing slash (Example: http://example.com/domainmod).<BR><BR>
<input name="new_full_url" type="text" size="50" maxlength="100" value="<?php if ($new_full_url != "") echo $new_full_url; ?>">
<BR><BR>
<strong>System Email Address (100):</strong><BR><BR>
This should be a valid email address that is able to receive mail. It will be used in various system locations, such as the FROM address for emails sent by <?php echo $software_title; ?>.<BR><BR>
<input name="new_email_address" type="text" size="50" maxlength="100" value="<?php if ($new_email_address != "") echo $new_email_address; ?>">
<BR><BR>
<strong>Days to Display in Expiration Emails:</strong><BR><BR>
This is the number of days in the future to display in the expiration emails.<BR><BR>
<input name="new_expiration_email_days" type="text" size="4" maxlength="3" value="<?php if ($new_expiration_email_days != "") echo $new_expiration_email_days; ?>">
<BR><BR>
<input type="submit" name="button" value="Update System Settings&raquo;">
</form>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
