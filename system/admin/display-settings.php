<?php
// display-settings.php
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

$page_title = "Edit Display Settings";
$software_section = "system";

// Form Variables
$new_number_of_domains = $_POST['new_number_of_domains'];
$new_number_of_ssl_certs = $_POST['new_number_of_ssl_certs'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_number_of_domains != "" && $new_number_of_ssl_certs != "") {

	$sql = "UPDATE settings
			SET number_of_domains = '$new_number_of_domains',
				number_of_ssl_certs = '$new_number_of_ssl_certs'";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$_SESSION['session_number_of_domains'] = $new_number_of_domains;
	$_SESSION['session_number_of_ssl_certs'] = $new_number_of_ssl_certs;

	$_SESSION['session_result_message'] .= "The Display Settings were updated.<BR>";
	
	header("Location: ../index.php");
	exit;

} else {


	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
		if ($new_number_of_domains == "") $_SESSION['session_result_message'] .= "Enter the default number of domains to display.<BR>";
		if ($new_number_of_ssl_certs == "") $_SESSION['session_result_message'] .= "Enter the default number of SSL certficates to display.<BR>";
		
	} else {
		
		$sql = "SELECT number_of_domains, number_of_ssl_certs
				FROM settings";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		while ($row = mysql_fetch_object($result)) {
			
			$new_number_of_domains = $row->number_of_domains;
			$new_number_of_ssl_certs = $row->number_of_ssl_certs;

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
<form name="display_settings_form" method="post" action="<?=$PHP_SELF?>">
<strong>Default # of Domains to Display:</strong><BR><BR>
This is the default number of domains to display on the <a href="../../domains.php">main page</a>.<BR><BR>
<input name="new_number_of_domains" type="text" size="50" maxlength="255" value="<?php if ($new_number_of_domains != "") echo $new_number_of_domains; ?>">
<BR><BR><BR>
<strong>Default # of SSL Certificates to Display:</strong><BR><BR>
This is the default number of SSL Certificates to display on the <a href="../../ssl-certs.php">main page</a>.<BR><BR>
<input name="new_number_of_ssl_certs" type="text" size="50" maxlength="255" value="<?php if ($new_number_of_ssl_certs != "") echo $new_number_of_ssl_certs; ?>">

<BR><BR><BR>
<input type="submit" name="button" value="Update Display Settings&raquo;">
</form>
<?php include("../../_includes/footer.inc.php"); ?>
</body>
</html>