<?php
// /edit/ssl-provider.php
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

include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/auth/auth-check.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");

$page_title = "Editting An SSL Provider";
$software_section = "ssl-providers";

// 'Delete SSL Provider' Confirmation Variables
$del = $_GET['del'];
$really_del = $_GET['really_del'];

$sslpid = $_GET['sslpid'];

// Form Variables
$new_ssl_provider = $_POST['new_ssl_provider'];
$new_url = $_POST['new_url'];
$new_notes = $_POST['new_notes'];
$new_sslpid = $_POST['new_sslpid'];
$new_default_ssl_provider = $_POST['new_default_ssl_provider'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_ssl_provider != "" && $new_url != "") {

		if ($new_default_ssl_provider == "1") {

			$sql = "UPDATE ssl_providers
					SET default_provider = '0',
					    update_time = '$current_timestamp'";
			$result = mysql_query($sql,$connection) or die(mysql_error());
			
		} else { 
		
			$sql = "SELECT default_provider
					FROM ssl_providers
					WHERE default_provider = '1'
					  AND id != '$new_sslpid'";
			$result = mysql_query($sql,$connection) or die(mysql_error());
			while ($row = mysql_fetch_object($result)) { $temp_default_ssl_provider = $row->default_ssl_provider; }
			if ($temp_default_ssl_provider == "") { $new_default_ssl_provider = "1"; }
		
		}

		$sql = "UPDATE ssl_providers
				SET name = '" . mysql_real_escape_string($new_ssl_provider) . "', 
					url = '" . mysql_real_escape_string($new_url) . "', 
					notes = '" . mysql_real_escape_string($new_notes) . "',
					default_provider = '$new_default_ssl_provider',
					update_time = '$current_timestamp'
				WHERE id = '$new_sslpid'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$sslpid = $new_sslpid;

		$_SESSION['session_result_message'] = "SSL Provider <font class=\"highlight\">$new_ssl_provider</font> Updated<BR>";
		
	} else {

		if ($new_ssl_provider == "") $_SESSION['session_result_message'] .= "Please enter the SSL provider's name<BR>";
		if ($new_url == "") $_SESSION['session_result_message'] .= "Please enter the SSL provider's URL<BR>";

	}

} else {

	$sql = "SELECT name, url, notes, default_provider
			FROM ssl_providers
			WHERE id = '$sslpid'";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	while ($row = mysql_fetch_object($result)) { 
	
		$new_ssl_provider = $row->name;
		$new_url = $row->url;
		$new_notes = $row->notes;
		$new_default_ssl_provider = $row->default_provider;
	
	}

}
if ($del == "1") {

	$sql = "SELECT ssl_provider_id
			FROM ssl_accounts
			WHERE ssl_provider_id = '$sslpid'";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	while ($row = mysql_fetch_object($result)) {
		$existing_ssl_provider_accounts = 1;
	}

	$sql = "SELECT ssl_provider_id
			FROM ssl_certs
			WHERE ssl_provider_id = '$sslpid'";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	while ($row = mysql_fetch_object($result)) {
		$existing_ssl_certs = 1;
	}

	if ($existing_ssl_provider_accounts > 0 || $existing_ssl_certs > 0) {
		
		if ($existing_ssl_provider_accounts > 0) $_SESSION['session_result_message'] .= "This SSL Provider has Accounts associated with it and cannot be deleted<BR>";
		if ($existing_ssl_certs > 0) $_SESSION['session_result_message'] .= "This SSL Provider has SSL Certificates associated with it and cannot be deleted<BR>";

	} else {

		$_SESSION['session_result_message'] = "Are you sure you want to delete this SSL Provider?<BR><BR><a href=\"$PHP_SELF?sslpid=$sslpid&really_del=1\">YES, REALLY DELETE THIS SSL PROVIDER</a><BR>";

	}

}

if ($really_del == "1") {

	$sql = "DELETE FROM ssl_fees
			WHERE ssl_provider_id = '$sslpid'";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "DELETE FROM ssl_accounts
			WHERE ssl_provider_id = '$sslpid'";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "DELETE FROM ssl_providers 
			WHERE id = '$sslpid'";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$_SESSION['session_result_message'] = "SSL Provider <font class=\"highlight\">$new_ssl_provider</font> Deleted<BR>";

	include("../_includes/auth/login-checks/domain-and-ssl-asset-check.inc.php");
	
	header("Location: ../ssl-providers.php");
	exit;

}
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../_includes/header.inc.php"); ?>
<form name="edit_ssl_provider_form" method="post" action="<?=$PHP_SELF?>">
<strong>SSL Provider Name:</strong><BR><BR>
<input name="new_ssl_provider" type="text" value="<?=$new_ssl_provider?>" size="50" maxlength="255">
<BR><BR>
<strong>SSL Provider's URL:</strong><BR><BR>
<input name="new_url" type="text" value="<?=$new_url?>" size="50" maxlength="255">
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=$new_notes?></textarea>
<BR><BR>
<strong>Default SSL Provider?:</strong>&nbsp;
<input name="new_default_ssl_provider" type="checkbox" value="1"<?php if ($new_default_ssl_provider == "1") echo " checked"; ?>>
<BR><BR><BR>
<input type="hidden" name="new_sslpid" value="<?=$sslpid?>">
<input type="submit" name="button" value="Update This SSL Provider &raquo;">
</form>
<BR><a href="ssl-provider-fees.php?sslpid=<?=$sslpid?>">EDIT THIS SSL PROVIDER'S FEES</a><BR><BR>
<BR><a href="<?=$PHP_SELF?>?sslpid=<?=$sslpid?>&del=1">DELETE THIS SSL PROVIDER</a>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>