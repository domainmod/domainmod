<?php
// owner.php
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

$page_title = "Editting An Owner";
$software_section = "owners";

// 'Delete Owner' Confirmation Variables
$del = $_GET['del'];
$really_del = $_GET['really_del'];

$oid = $_GET['oid'];

// Form Variables
$new_owner = $_POST['new_owner'];
$new_notes = $_POST['new_notes'];
$new_default_owner = $_REQUEST['new_default_owner'];
$new_oid = $_POST['new_oid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_owner != "") {

		if ($new_default_owner == "1") {
			
			$sql = "UPDATE owners
					SET default_owner = '0',
					    update_time = '$current_timestamp'";
			$result = mysql_query($sql,$connection);
			
		} else { 
		
			$sql = "SELECT count(*) AS total_count
					FROM owners
					WHERE default_owner = '1'";
			$result = mysql_query($sql,$connection);
			while ($row = mysql_fetch_object($result)) { $temp_total = $row->total_count; }
			if ($temp_total == "0") $new_default_owner = "1";
		
		}

		$sql = "UPDATE owners
				SET name = '" . mysql_real_escape_string($new_owner) . "',
					notes = '" . mysql_real_escape_string($new_notes) . "',
					default_owner = '$new_default_owner',
					update_time = '$current_timestamp'
				WHERE id = '$new_oid'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$new_owner = $new_owner;
		$new_default_owner = $new_default_owner;
		$new_notes = $new_notes;

		$oid = $new_oid;
		
		$_SESSION['session_result_message'] = "Owner Updated<BR>";

	} else {
	
		$_SESSION['session_result_message'] = "Please Enter The Owner's Name<BR>";

	}

} else {

	$sql = "SELECT name, notes, default_owner
			FROM owners
			WHERE id = '$oid'";
	$result = mysql_query($sql,$connection);
	
	while ($row = mysql_fetch_object($result)) { 
	
		$new_owner = $row->name;
		$new_default_owner = $row->default_owner;
		$new_notes = $row->notes;
	
	}

}

if ($del == "1") {

	$sql = "SELECT owner_id
			FROM registrar_accounts
			WHERE owner_id = '$oid'";
	$result = mysql_query($sql,$connection);
	
	while ($row = mysql_fetch_object($result)) {
		$existing_registrar_accounts = 1;
	}

	$sql = "SELECT owner_id
			FROM ssl_accounts
			WHERE owner_id = '$oid'";
	$result = mysql_query($sql,$connection);
	
	while ($row = mysql_fetch_object($result)) {
		$existing_ssl_accounts = 1;
	}

	$sql = "SELECT owner_id
			FROM domains
			WHERE owner_id = '$oid'";
	$result = mysql_query($sql,$connection);
	
	while ($row = mysql_fetch_object($result)) {
		$existing_domains = 1;
	}

	$sql = "SELECT owner_id
			FROM ssl_certs
			WHERE owner_id = '$oid'";
	$result = mysql_query($sql,$connection);
	
	while ($row = mysql_fetch_object($result)) {
		$existing_ssl_certs = 1;
	}
	
	if ($existing_registrar_accounts > 0 || $existing_ssl_accounts > 0 || $existing_domains > 0 || $existing_ssl_certs > 0) {
		
		if ($existing_registrar_accounts > 0) $_SESSION['session_result_message'] .= "This Owner has registrar accounts associated with it and cannot be deleted.<BR>";
		if ($existing_domains > 0) $_SESSION['session_result_message'] .= "This Owner has domains associated with it and cannot be deleted.<BR>";
		if ($existing_ssl_accounts > 0) $_SESSION['session_result_message'] .= "This Owner has SSL accounts associated with it and cannot be deleted.<BR>";
		if ($existing_ssl_certs > 0) $_SESSION['session_result_message'] .= "This Owner has SSL certificates associated with it and cannot be deleted.<BR>";

	} else {

		$_SESSION['session_result_message'] = "Are you sure you want to delete this Owner?<BR><BR><a href=\"$PHP_SELF?oid=$oid&really_del=1\">YES, REALLY DELETE THIS OWNER</a><BR>";

	}

}

if ($really_del == "1") {

	$sql = "DELETE FROM owners 
			WHERE id = '$oid'";
	$result = mysql_query($sql,$connection);
	
	$_SESSION['session_result_message'] = "Owner Deleted ($new_owner)<BR>";
	
	header("Location: ../owners.php");
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
<form name="edit_owner_form" method="post" action="<?=$PHP_SELF?>">
<strong>Owner Name:</strong><BR><BR>
<input name="new_owner" type="text" value="<?php if ($new_owner != "") echo $new_owner; ?>
" size="50" maxlength="255">
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=$new_notes?></textarea>
<BR><BR>
<strong>Default Owner?:</strong>&nbsp;
<input name="new_default_owner" type="checkbox" value="1"<?php if ($new_default_owner == "1") echo " checked"; ?>>
<BR><BR><BR>
<input type="hidden" name="new_oid" value="<?=$oid?>">
<input type="submit" name="button" value="Update This Owner &raquo;">
</form>
<BR>
<a href="<?=$PHP_SELF?>?oid=<?=$oid?>&del=1">DELETE THIS OWNER</a>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>