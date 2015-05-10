<?php
/**
 * /assets/edit/registrar-account.php
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
require_once(DIR_INC . "functions.inc.php");
include(DIR_INC . "timestamps/current-timestamp.inc.php");

spl_autoload_register('classAutoloader');

$error = new DomainMOD\Error();

$page_title = "Editing A Registrar Account";
$software_section = "registrar-accounts-edit";

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$raid = $_GET['raid'];
$new_owner_id = $_POST['new_owner_id'];
$new_registrar_id = $_POST['new_registrar_id'];
$new_username = $_POST['new_username'];
$new_password = $_POST['new_password'];
$new_reseller = $_POST['new_reseller'];
$new_notes = $_POST['new_notes'];
$new_raid = $_POST['new_raid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_username != "" && $new_owner_id != "" && $new_registrar_id != "" && $new_owner_id != "0" && $new_registrar_id != "0") {

		$sql = "UPDATE registrar_accounts
				SET owner_id = '" . $new_owner_id . "',
					registrar_id = '" . $new_registrar_id . "',
					username = '" . mysqli_real_escape_string($connection, $new_username) . "',
					password = '" . mysqli_real_escape_string($connection, $new_password) . "',
					notes = '" . mysqli_real_escape_string($connection, $new_notes) . "',
					reseller = '" . $new_reseller . "',
					update_time = '" . $current_timestamp . "'
				WHERE id = '" . $new_raid . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$sql = "UPDATE domains
				SET owner_id = '" . $new_owner_id . "'
				WHERE account_id = '" . $new_raid . "'";
		$result = mysqli_query($connection, $sql);
		
		$raid = $new_raid; 

		$sql = "SELECT name
				FROM registrars
				WHERE id = '" . $new_registrar_id . "'";
		$result = mysqli_query($connection, $sql);
		while ($row = mysqli_fetch_object($result)) { $temp_registrar = $row->name; }

		$sql = "SELECT name
				FROM owners
				WHERE id = '" . $new_owner_id . "'";
		$result = mysqli_query($connection, $sql);
		while ($row = mysqli_fetch_object($result)) { $temp_owner = $row->name; }
		
		$_SESSION['result_message'] = "Registrar Account <font class=\"highlight\">$new_username ($temp_registrar, $temp_owner)</font> Updated<BR>";

		header("Location: ../registrar-accounts.php");
		exit;

	} else {
	
		if ($username == "") { $_SESSION['result_message'] .= "Please enter the username<BR>"; }

	}

} else {

	$sql = "SELECT owner_id, registrar_id, username, password, notes, reseller
			FROM registrar_accounts
			WHERE id = '" . $raid . "'"; 
	$result = mysqli_query($connection, $sql);
	
	while ($row = mysqli_fetch_object($result)) { 
	
		$new_owner_id = $row->owner_id;
		$new_registrar_id = $row->registrar_id;
		$new_username = $row->username;
		$new_password = $row->password;
		$new_notes = $row->notes;
		$new_reseller = $row->reseller;

	}

}
if ($del == "1") {

	$sql = "SELECT account_id
			FROM domains
			WHERE account_id = '" . $raid . "'";
	$result = mysqli_query($connection, $sql);
	
	while ($row = mysqli_fetch_object($result)) {
		$existing_domains = 1;
	}
	
	if ($existing_domains > 0) {

		$_SESSION['result_message'] = "This Registrar Account has domains associated with it and cannot be deleted<BR>";

	} else {

		$_SESSION['result_message'] = "Are you sure you want to delete this Registrar Account?<BR><BR><a href=\"registrar-account.php?raid=$raid&really_del=1\">YES, REALLY DELETE THIS DOMAIN REGISTRAR ACCOUNT</a><BR>";

	}

}

if ($really_del == "1") {

	$sql = "SELECT ra.username as username, o.name as owner_name, r.name as registrar_name
			FROM registrar_accounts as ra, owners as o, registrars as r
			WHERE ra.owner_id = o.id
			  AND ra.registrar_id = r.id
			  AND ra.id = '" . $raid . "'";
	$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

	while ($row = mysqli_fetch_object($result)) { 
		$temp_username = $row->username; 
		$temp_owner_name = $row->owner_name; 
		$temp_registrar_name = $row->registrar_name;
	}

	$sql = "DELETE FROM registrar_accounts 
			WHERE id = '$raid'";
	$result = mysqli_query($connection, $sql);
	
	$_SESSION['result_message'] = "Registrar Account <font class=\"highlight\">$temp_username ($temp_registrar_name, $temp_owner_name)</font> Deleted<BR>";

	include(DIR_INC . "auth/login-checks/domain-and-ssl-asset-check.inc.php");
	
	header("Location: ../registrar-accounts.php");
	exit;

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
<form name="edit_account_form" method="post">
<strong>Owner</strong><BR><BR>
<?php
$sql_owner = "SELECT id, name
			  FROM owners
			  ORDER BY name asc";
$result_owner = mysqli_query($connection, $sql_owner) or $error->outputOldSqlError($connection);
echo "<select name=\"new_owner_id\">";
while ($row_owner = mysqli_fetch_object($result_owner)) {

	if ($row_owner->id == $new_owner_id) {

		echo "<option value=\"$row_owner->id\" selected>$row_owner->name</option>";
	
	} else {

		echo "<option value=\"$row_owner->id\">$row_owner->name</option>";
	
	}
}
echo "</select>";
?>
<BR><BR>
<strong>Registrar</strong><BR><BR>
<?php
$sql_registrar = "SELECT id, name
				  FROM registrars
				  ORDER BY name asc";
$result_registrar = mysqli_query($connection, $sql_registrar) or $error->outputOldSqlError($connection);
echo "<select name=\"new_registrar_id\">";
while ($row_registrar = mysqli_fetch_object($result_registrar)) {

	if ($row_registrar->id == $new_registrar_id) {

		echo "<option value=\"$row_registrar->id\" selected>$row_registrar->name</option>";
	
	} else {

		echo "<option value=\"$row_registrar->id\">$row_registrar->name</option>";
	
	}
}
echo "</select>";
?>
<BR><BR>
<strong>Username (100)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR>
<input name="new_username" type="text" size="50" maxlength="100" value="<?php echo htmlentities($new_username); ?>">
<BR><BR>
<strong>Password (255)</strong><BR><BR>
<input name="new_password" type="text" size="50" maxlength="255" value="<?php echo htmlentities($new_password); ?>">
<BR><BR>
<strong>Reseller Account?</strong><BR><BR>
<select name="new_reseller">";
<option value="0"<?php if ($new_reseller == "0") echo " selected"; ?>>No</option>
<option value="1"<?php if ($new_reseller == "1") echo " selected"; ?>>Yes</option>
</select>
<BR><BR>
<strong>Notes</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?></textarea>
<BR><BR>
<input type="hidden" name="new_raid" value="<?php echo $raid; ?>">
<input type="submit" name="button" value="Update This Registrar Account &raquo;">
</form>
<BR><BR><a href="registrar-account.php?raid=<?php echo $raid; ?>&del=1">DELETE THIS REGISTRAR ACCOUNT</a>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
