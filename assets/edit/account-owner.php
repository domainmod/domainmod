<?php
/**
 * /assets/edit/account-owner.php
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
include("../../_includes/config.inc.php");
include("../../_includes/database.inc.php");
include("../../_includes/software.inc.php");
include("../../_includes/auth/auth-check.inc.php");
include("../../_includes/timestamps/current-timestamp.inc.php");
include("../../_includes/system/functions/error-reporting.inc.php");

$page_title = "Editing An Account Owner";
$software_section = "account-owners-edit";

// 'Delete Owner' Confirmation Variables
$del = $_GET['del'];
$really_del = $_GET['really_del'];

$oid = $_GET['oid'];

// Form Variables
$new_owner = $_POST['new_owner'];
$new_notes = $_POST['new_notes'];
$new_oid = $_POST['new_oid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_owner != "") {

		$sql = "UPDATE owners
				SET name = '" . mysqli_real_escape_string($connection, $new_owner) . "',
					notes = '" . mysqli_real_escape_string($connection, $new_notes) . "',
					update_time = '" . $current_timestamp . "'
				WHERE id = '" . $new_oid . "'";
		$result = mysqli_query($connection, $sql) or OutputOldSQLError($connection);
		
		$new_owner = $new_owner;
		$new_notes = $new_notes;

		$oid = $new_oid;
		
		$_SESSION['result_message'] = "Owner <font class=\"highlight\">$new_owner</font> Updated<BR>";

		header("Location: ../account-owners.php");
		exit;

	} else {
	
		$_SESSION['result_message'] = "Please enter the owner's name<BR>";

	}

} else {

	$sql = "SELECT name, notes
			FROM owners
			WHERE id = '" . $oid . "'";
	$result = mysqli_query($connection, $sql);
	
	while ($row = mysqli_fetch_object($result)) { 
	
		$new_owner = $row->name;
		$new_notes = $row->notes;
	
	}

}

if ($del == "1") {

	$sql = "SELECT owner_id
			FROM registrar_accounts
			WHERE owner_id = '" . $oid . "'";
	$result = mysqli_query($connection, $sql);
	
	while ($row = mysqli_fetch_object($result)) {
		$existing_registrar_accounts = 1;
	}

	$sql = "SELECT owner_id
			FROM ssl_accounts
			WHERE owner_id = '" . $oid . "'";
	$result = mysqli_query($connection, $sql);
	
	while ($row = mysqli_fetch_object($result)) {
		$existing_ssl_accounts = 1;
	}

	$sql = "SELECT owner_id
			FROM domains
			WHERE owner_id = '" . $oid . "'";
	$result = mysqli_query($connection, $sql);
	
	while ($row = mysqli_fetch_object($result)) {
		$existing_domains = 1;
	}

	$sql = "SELECT owner_id
			FROM ssl_certs
			WHERE owner_id = '" . $oid . "'";
	$result = mysqli_query($connection, $sql);
	
	while ($row = mysqli_fetch_object($result)) {
		$existing_ssl_certs = 1;
	}
	
	if ($existing_registrar_accounts > 0 || $existing_ssl_accounts > 0 || $existing_domains > 0 || $existing_ssl_certs > 0) {
		
		if ($existing_registrar_accounts > 0) $_SESSION['result_message'] .= "This Owner has registrar accounts associated with it and cannot be deleted<BR>";
		if ($existing_domains > 0) $_SESSION['result_message'] .= "This Owner has domains associated with it and cannot be deleted<BR>";
		if ($existing_ssl_accounts > 0) $_SESSION['result_message'] .= "This Owner has SSL accounts associated with it and cannot be deleted<BR>";
		if ($existing_ssl_certs > 0) $_SESSION['result_message'] .= "This Owner has SSL certificates associated with it and cannot be deleted<BR>";

	} else {

		$_SESSION['result_message'] = "Are you sure you want to delete this Owner?<BR><BR><a href=\"$PHP_SELF?oid=$oid&really_del=1\">YES, REALLY DELETE THIS OWNER</a><BR>";

	}

}

if ($really_del == "1") {

	$sql = "DELETE FROM owners 
			WHERE id = '" . $oid . "'";
	$result = mysqli_query($connection, $sql);
	
	$_SESSION['result_message'] = "Owner <font class=\"highlight\">$new_owner</font> Deleted<BR>";
	
	header("Location: ../account-owners.php");
	exit;

}
?>
<?php include("../../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include("../../_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../../_includes/layout/header.inc.php"); ?>
<form name="edit_owner_form" method="post" action="<?php echo $PHP_SELF; ?>">
<strong>Owner Name (100)</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
<input name="new_owner" type="text" value="<?php if ($new_owner != "") echo htmlentities($new_owner); ?>
" size="50" maxlength="100">
<BR><BR>
<strong>Notes</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?></textarea>
<input type="hidden" name="new_oid" value="<?php echo $oid; ?>">
<BR><BR>
<input type="submit" name="button" value="Update This Account Owner &raquo;">
</form>
<BR><BR><a href="<?php echo $PHP_SELF; ?>?oid=<?php echo $oid; ?>&del=1">DELETE THIS OWNER</a>
<?php include("../../_includes/layout/footer.inc.php"); ?>
</body>
</html>
