<?php
/**
 * /assets/edit/ssl-provider-account.php
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

$page_title = "Editing An SSL Provider Account";
$software_section = "ssl-provider-accounts-edit";

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$sslpaid = $_GET['sslpaid'];
$new_owner_id = $_POST['new_owner_id'];
$new_ssl_provider_id = $_POST['new_ssl_provider_id'];
$new_username = $_POST['new_username'];
$new_password = $_POST['new_password'];
$new_reseller = $_POST['new_reseller'];
$new_notes = $_POST['new_notes'];
$new_sslpaid = $_POST['new_sslpaid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_username != "" && $new_owner_id != "" && $new_ssl_provider_id != "" && $new_owner_id != "0" && $new_ssl_provider_id != "0") {

		$sql = "UPDATE ssl_accounts
				SET owner_id = '" . $new_owner_id . "',
					ssl_provider_id = '" . $new_ssl_provider_id . "',
					username = '" . mysqli_real_escape_string($connection, $new_username) . "',
					password = '" . mysqli_real_escape_string($connection, $new_password) . "',
					notes = '" . mysqli_real_escape_string($connection, $new_notes) . "',
					reseller = '" . $new_reseller . "',
					update_time = '" . $current_timestamp . "'
				WHERE id = '" . $new_sslpaid . "'";
		$result = mysqli_query($connection, $sql) or die(mysqli_error());
		
		$sslpaid = $new_sslpaid; 

		$sql = "SELECT name
				FROM ssl_providers
				WHERE id = '" . $new_ssl_provider_id . "'";
		$result = mysqli_query($connection, $sql);
		while ($row = mysqli_fetch_object($result)) { $temp_ssl_provider = $row->name; }

		$sql = "SELECT name
				FROM owners
				WHERE id = '" . $new_owner_id . "'";
		$result = mysqli_query($connection, $sql);
		while ($row = mysqli_fetch_object($result)) { $temp_owner = $row->name; }

		$_SESSION['result_message'] = "SSL Account <font class=\"highlight\">$new_username ($temp_ssl_provider, $temp_owner)</font> Updated<BR>";

		header("Location: ../ssl-accounts.php");
		exit;

	} else {
	
		if ($username == "") { $_SESSION['result_message'] .= "Please enter a username<BR>"; }

	}

} else {

	$sql = "SELECT owner_id, ssl_provider_id, username, password, notes, reseller
			FROM ssl_accounts
			WHERE id = '" . $sslpaid . "'";
	$result = mysqli_query($connection, $sql);
	
	while ($row = mysqli_fetch_object($result)) { 
	
		$new_owner_id = $row->owner_id;
		$new_ssl_provider_id = $row->ssl_provider_id;
		$new_username = $row->username;
		$new_password = $row->password;
		$new_notes = $row->notes;
		$new_reseller = $row->reseller;
	
	}

}
if ($del == "1") {

	$sql = "SELECT account_id
			FROM ssl_certs
			WHERE account_id = '" . $sslpaid . "'";
	$result = mysqli_query($connection, $sql);
	
	while ($row = mysqli_fetch_object($result)) {
		$existing_ssl_certs = 1;
	}
	
	if ($existing_ssl_certs > 0) {

		$_SESSION['result_message'] = "This SSL Account has SSL certificates associated with it and cannot be deleted<BR>";

	} else {

		$_SESSION['result_message'] = "Are you sure you want to delete this SSL Account?<BR><BR><a href=\"$PHP_SELF?sslpaid=$sslpaid&really_del=1\">YES, REALLY DELETE THIS SSL PROVIDER ACCOUNT</a><BR>";

	}

}

if ($really_del == "1") {

	$sql = "SELECT a.username as username, o.name as owner_name, p.name as ssl_provider_name
			FROM ssl_accounts as a, owners as o, ssl_providers as p
			WHERE a.owner_id = o.id
			  AND a.ssl_provider_id = p.id
			  AND a.id = '" . $sslpaid . "'";
	$result = mysqli_query($connection, $sql) or die(mysqli_error());

	while ($row = mysqli_fetch_object($result)) { 
		$temp_username = $row->username; 
		$temp_owner_name = $row->owner_name; 
		$temp_ssl_provider_name = $row->ssl_provider_name;
	}

	$sql = "DELETE FROM ssl_accounts 
			WHERE id = '" . $sslpaid . "'";
	$result = mysqli_query($connection, $sql) or die(mysqli_error());

	$_SESSION['result_message'] = "SSL Account <font class=\"highlight\">$temp_username ($temp_ssl_provider_name, $temp_owner_name)</font> Deleted<BR>";

	include("../../_includes/auth/login-checks/domain-and-ssl-asset-check.inc.php");
	
	header("Location: ../ssl-accounts.php");
	exit;

}
?>
<?php include("../../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title; ?> :: <?php echo $page_title; ?></title>
<?php include("../../_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../../_includes/layout/header.inc.php"); ?>
<form name="edit_ssl_account_form" method="post" action="<?php echo $PHP_SELF; ?>">
<strong>Owner</strong><BR><BR>
<?php
$sql_owner = "SELECT id, name
			  FROM owners
			  ORDER BY name asc";
$result_owner = mysqli_query($connection, $sql_owner) or die(mysqli_error());
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
<strong>SSL Provider</strong><BR><BR>
<?php
$sql_ssl_provider = "SELECT id, name
					 FROM ssl_providers
					 ORDER BY name asc";
$result_ssl_provider = mysqli_query($connection, $sql_ssl_provider) or die(mysqli_error());
echo "<select name=\"new_ssl_provider_id\">";
while ($row_ssl_provider = mysqli_fetch_object($result_ssl_provider)) {

	if ($row_ssl_provider->id == $new_ssl_provider_id) {

		echo "<option value=\"$row_ssl_provider->id\" selected>$row_ssl_provider->name</option>";
	
	} else {

		echo "<option value=\"$row_ssl_provider->id\">$row_ssl_provider->name</option>";
	
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
<input type="hidden" name="new_sslpaid" value="<?php echo $sslpaid; ?>">
<BR><BR>
<input type="submit" name="button" value="Update This SSL Provider Account &raquo;">
</form>
<BR><BR><a href="<?php echo $PHP_SELF; ?>?sslpaid=<?php echo $sslpaid; ?>&del=1">DELETE THIS SSL PROVIDER ACCOUNT</a>
<?php include("../../_includes/layout/footer.inc.php"); ?>
</body>
</html>
