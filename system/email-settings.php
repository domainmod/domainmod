<?php
// /system/email-settings.php
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
include("../_includes/start-session.inc.php");
include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");
include("../_includes/auth/auth-check.inc.php");

$page_title = "Email Settings";
$software_section = "system-email-settings";

$new_expiration_email = $_POST['new_expiration_email'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	$sql = "UPDATE user_settings
			SET expiration_emails = '$new_expiration_email',
				update_time = '$current_timestamp'
			WHERE user_id = '" . $_SESSION['user_id'] . "'";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$_SESSION['expiration_email'] = $new_expiration_email;

	$_SESSION['result_message'] .= "Your Email Settings were updated<BR>";

	header("Location: index.php");
	exit;

} else {

	$sql = "SELECT expiration_emails
			FROM user_settings
			WHERE user_id = '" . $_SESSION['user_id'] . "'";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	while ($row = mysql_fetch_object($result)) {
		
		$new_expiration_email = $row->expiration_emails;

	}

}
?>
<?php include("../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../_includes/layout/header.inc.php"); ?>
<form name="email_settings_form" method="post" action="<?=$PHP_SELF?>">
<strong>Subscribe to Domain & SSL Certificate expiration emails?</strong>&nbsp;
<select name="new_expiration_email">
<option value="1"<?php if ($new_expiration_email == "1") echo " selected"; ?>>Yes</option>
<option value="0"<?php if ($new_expiration_email == "0") echo " selected"; ?>>No</option>
</select>
<BR><BR>
<input type="submit" name="button" value="Update Email Settings&raquo;">
</form>
<?php include("../_includes/layout/footer.inc.php"); ?>
</body>
</html>
