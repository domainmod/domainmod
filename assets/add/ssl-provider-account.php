<?php
/**
 * /assets/add/ssl-provider-account.php
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
include(DIR_INC . "config.inc.php");
include(DIR_INC . "database.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "auth/auth-check.inc.php");
include(DIR_INC . "timestamps/current-timestamp.inc.php");
include(DIR_INC . "classes/Error.class.php");

$error = new DomainMOD\Error();

$page_title = "Adding A New SSL Provider Account";
$software_section = "ssl-provider-accounts-add";

// Form Variables
$new_owner_id = $_POST['new_owner_id'];
$new_ssl_provider_id = $_POST['new_ssl_provider_id'];
$new_username = $_POST['new_username'];
$new_password = $_POST['new_password'];
$new_reseller = $_POST['new_reseller'];
$new_notes = $_POST['new_notes'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_username != "" && $new_owner_id != "" && $new_ssl_provider_id != "" && $new_owner_id != "0" && $new_ssl_provider_id != "0") {

		$sql = "INSERT into ssl_accounts
				(owner_id, ssl_provider_id, username, password, notes, reseller, insert_time) VALUES 
				('" . $new_owner_id . "', '" . $new_ssl_provider_id . "', '" . mysqli_real_escape_string($connection, $new_username) . "', '" . mysqli_real_escape_string($connection, $new_password) . "', '" . mysqli_real_escape_string($connection, $new_notes) . "', '" . $new_reseller . "', '" . $current_timestamp . "')";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

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
		
		$_SESSION['result_message'] = "SSL Account <font class=\"highlight\">$new_username (" . $temp_ssl_provider . ", " . $temp_owner . ")</font> Added<BR>";

		if ($_SESSION['need_ssl_account'] == "1") {
			
			include(DIR_INC . "auth/login-checks/domain-and-ssl-asset-check.inc.php");
			header("Location: ../../ssl-certs.php");

		} else {

			header("Location: ../ssl-accounts.php");
			
		}
		exit;

	} else {
	
		if ($username == "") { $_SESSION['result_message'] .= "Please enter a username<BR>"; }

	}

}
?>
<?php include(DIR_INC . "doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[2].focus()";>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<form name="add_ssl_account_form" method="post" action="ssl-provider-account.php">
<strong>Owner</strong><BR><BR>
<?php
$sql_owner = "SELECT id, name
			  FROM owners
			  ORDER BY name asc";
$result_owner = mysqli_query($connection, $sql_owner) or $error->outputOldSqlError($connection);
echo "<select name=\"new_owner_id\">";
while ($row_owner = mysqli_fetch_object($result_owner)) {

	if ($row_owner->id == $_SESSION['default_owner_ssl']) {

		echo "<option value=\"" . $row_owner->id . "\" selected>" . $row_owner->name . "</option>";
	
	} else {

		echo "<option value=\"" . $row_owner->id . "\">" . $row_owner->name . "</option>";
	
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
$result_ssl_provider = mysqli_query($connection, $sql_ssl_provider) or $error->outputOldSqlError($connection);
echo "<select name=\"new_ssl_provider_id\">";
while ($row_ssl_provider = mysqli_fetch_object($result_ssl_provider)) {

	if ($row_ssl_provider->id == $_SESSION['default_ssl_provider']) {

		echo "<option value=\"" . $row_ssl_provider->id . "\" selected>" . $row_ssl_provider->name . "</option>";
	
	} else {

		echo "<option value=\"" . $row_ssl_provider->id . "\">" . $row_ssl_provider->name . "</option>";
	
	}
}
echo "</select>";
?>
<BR><BR>
<strong>Username (100)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR>
<input name="new_username" type="text" size="50" maxlength="100" value="<?php echo $new_username; ?>">
<BR><BR>
<strong>Password (255)</strong><BR><BR>
<input name="new_password" type="text" size="50" maxlength="255" value="<?php echo $new_password; ?>">
<BR><BR>
<strong>Reseller Account?</strong><BR><BR>
<select name="new_reseller">";
<option value="0"<?php if ($new_reseller != "1") echo " selected"; ?>>No</option>
<option value="1"<?php if ($new_reseller == "1") echo " selected"; ?>>Yes</option>
</select>
<BR><BR>
<strong>Notes</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?>
</textarea>
<BR><BR>
<input type="submit" name="button" value="Add This SSL Provider Account &raquo;">
</form>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
