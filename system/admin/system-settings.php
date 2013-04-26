<?php
// /system/admin/system-settings.php
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
include("../../_includes/start-session.inc.php");

// If the user isn't an administrator, redirect them to $full_redirect
$full_redirect = "index.php";
include("../../_includes/auth/admin-user-check.inc.php");

include("../../_includes/config.inc.php");
include("../../_includes/database.inc.php");
include("../../_includes/software.inc.php");
include("../../_includes/timestamps/current-timestamp.inc.php");
include("../../_includes/auth/auth-check.inc.php");

$page_title = "Edit System Settings";
$software_section = "system";

// Form Variables
$new_email_address = $_POST['new_email_address'];
$new_full_url = $_POST['new_full_url'];
$new_timezone = $_POST['new_timezone'];
$new_expiration_email_days = $_POST['new_expiration_email_days'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_email_address != "" && $new_full_url != "" && $new_expiration_email_days != "") {

	$sql = "UPDATE settings
			SET full_url = '$new_full_url',
				email_address = '$new_email_address',
				timezone = '$new_timezone',
				expiration_email_days = '$new_expiration_email_days',
				update_time = '$current_timestamp'";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$_SESSION['system_full_url'] = $new_full_url;
	$_SESSION['system_email_address'] = $new_email_address;
	$_SESSION['system_timezone'] = $new_timezone;
	$_SESSION['system_expiration_email_days'] = $new_expiration_email_days;
	
	$_SESSION['result_message'] = "The System Settings were updated<BR><BR>";
	
	header("Location: ../index.php");
	exit;

} else {


	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
		if ($new_email_address == "") $_SESSION['result_message'] .= "Enter the system email address<BR>";
		if ($new_full_url == "") $_SESSION['result_message'] .= "Enter the full URL of your " . $software_title . " installation<BR>";
		if ($new_expiration_email_days == "") $_SESSION['result_message'] .= "Enter the number of days to display in expiration emails<BR>";
		
	} else {
		
		$sql = "SELECT full_url, email_address, timezone, expiration_email_days
				FROM settings";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		while ($row = mysql_fetch_object($result)) {
			
			$new_full_url = $row->full_url;
			$new_email_address = $row->email_address;
			$new_timezone = $row->timezone;
			$new_expiration_email_days = $row->expiration_email_days;

		}

	}
}
?>
<?php include("../../_includes/doctype.inc.php"); ?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../../_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../../_includes/header.inc.php"); ?>
<form name="system_settings_form" method="post" action="<?=$PHP_SELF?>">
<strong>Full URL:</strong><BR><BR>
Enter the full URL of your <?=$software_title?> installation, excluding the trailing slash (Example: http://yourdomain.com/domainmanager).<BR><BR>
<input name="new_full_url" type="text" size="50" maxlength="100" value="<?php if ($new_full_url != "") echo $new_full_url; ?>">
<BR><BR>
<strong>Email Address:</strong><BR><BR>
This should be a valid email address that is able to receive mail. It will be used in various system locations, such as the FROM address for emails sent by <?=$software_title?>.<BR><BR>
<input name="new_email_address" type="text" size="50" maxlength="255" value="<?php if ($new_email_address != "") echo $new_email_address; ?>">
<BR><BR>
<strong>Days to Display in Expiration Emails:</strong><BR><BR>
This is the number of days in the future to display in the expiration emails.<BR><BR>
<input name="new_expiration_email_days" type="text" size="4" maxlength="3" value="<?php if ($new_expiration_email_days != "") echo $new_expiration_email_days; ?>">
<BR><BR>
<strong>Default Timezone:</strong><BR><BR>
<select name="new_timezone">
<?php
$sql = "SELECT timezone
		FROM timezones
		ORDER BY timezone";
$result = mysql_query($sql,$connection);
while ($row = mysql_fetch_object($result)) {
	?>
	<option value="<?=$row->timezone?>"<?php if ($_SESSION['system_timezone'] == "$row->timezone") echo " selected"; ?>><?=$row->timezone?></option>
    <?php
}
?>
</select>
<BR><BR><BR>
<input type="submit" name="button" value="Update System Settings&raquo;">
</form>
<?php include("../../_includes/footer.inc.php"); ?>
</body>
</html>