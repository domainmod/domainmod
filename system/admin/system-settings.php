<?php
// system-settings.php
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
include("../../_includes/timestamps/current-timestamp.inc.php");
include("../../_includes/auth/auth-check.inc.php");

$page_title = "Edit System Settings";
$software_section = "system";

// Form Variables
$new_email_address = $_POST['new_email_address'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_email_address != "") {

	$sql = "UPDATE settings
			SET email_address = '$new_email_address'";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$_SESSION['session_result_message'] = "The System Settings were updated<BR><BR>";
	
	header("Location: ../index.php");
	exit;

} else {


	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
		if ($new_email_address == "") $_SESSION['session_result_message'] .= "Enter the system email address<BR>";
		
	} else {
		
		$sql = "SELECT email_address
				FROM settings";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		while ($row = mysql_fetch_object($result)) {
			
			$new_email_address = $row->email_address;

		}

	}
}
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../../_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../../_includes/header.inc.php"); ?>
<form name="system_settings_form" method="post" action="<?=$PHP_SELF?>">
<strong>Email Address:</strong><BR><BR>
This should be a valid email address that is able to receive mail. It will be used in various system locations, such as the FROM address for emails sent by <?=$software_title?>.<BR><BR>
<input name="new_email_address" type="text" size="50" maxlength="255" value="<?php if ($new_email_address != "") echo $new_email_address; ?>">
<BR><BR><BR>
<input type="submit" name="button" value="Update System Settings&raquo;">
</form>
<?php include("../../_includes/footer.inc.php"); ?>
</body>
</html>