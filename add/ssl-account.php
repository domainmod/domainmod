<?php
// ssl-account.php
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

$page_title = "Adding A New SSL Provider Account";
$software_section = "ssl-accounts";

// Form Variables
$new_owner_id = $_POST['new_owner_id'];
$new_ssl_provider_id = $_POST['new_ssl_provider_id'];
$new_username = $_POST['new_username'];
$new_reseller = $_POST['new_reseller'];
$new_notes = $_POST['new_notes'];
$new_default_account = $_POST['new_default_account'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_username != "" && $new_owner_id != "" && $new_ssl_provider_id != "" && $new_owner_id != "0" && $new_ssl_provider_id != "0") {

		if ($new_default_account == "1") {
			
			$sql = "UPDATE ssl_accounts
					SET default_account = '0',
						update_time = '$current_timestamp'";
			$result = mysql_query($sql,$connection);
			
		} else { 
		
			$sql = "SELECT count(*) as total_count
					FROM ssl_accounts
					WHERE default_account = '1'";
			$result = mysql_query($sql,$connection);
			while ($row = mysql_fetch_object($result)) { $temp_total = $row->total_count; }
			if ($temp_total == "0") $new_default_account = "1";
		
		}

		$sql = "INSERT into ssl_accounts
				(owner_id, ssl_provider_id, username, notes, reseller, default_account, insert_time) VALUES 
				('$new_owner_id', '$new_ssl_provider_id', '" . mysql_real_escape_string($new_username) . "', '" . mysql_real_escape_string($new_notes) . "', '$new_reseller', '$new_default_account', '$current_timestamp')";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "SELECT name
				FROM ssl_providers
				WHERE id = '$new_ssl_provider_id'";
		$result = mysql_query($sql,$connection);
		while ($row = mysql_fetch_object($result)) { $temp_ssl_provider = $row->name; }

		$sql = "SELECT name
				FROM owners
				WHERE id = '$new_owner_id'";
		$result = mysql_query($sql,$connection);
		while ($row = mysql_fetch_object($result)) { $temp_owner = $row->name; }
		
		$_SESSION['session_result_message'] = "SSL Account <font class=\"highlight\">$new_username ($temp_ssl_provider, $temp_owner)</font> Added<BR>";

		if ($_SESSION['session_need_ssl_account'] == "1") {
			
			include("../_includes/auth/login-checks/domain-and-ssl-asset-check.inc.php");
			header("Location: ../ssl-certs.php");

		} else {

			header("Location: ../ssl-accounts.php");
			
		}
		exit;

	} else {
	
		if ($username == "") { $_SESSION['session_result_message'] .= "Please enter a username<BR>"; }

	}

}
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[2].focus()";>
<?php include("../_includes/header.inc.php"); ?>
<form name="add_ssl_account_form" method="post" action="<?=$PHP_SELF?>">
<strong>Owner:</strong><BR><BR>
<?php
$sql_owner = "SELECT id, name
			  FROM owners
			  WHERE active = '1'
			  ORDER BY name asc";
$result_owner = mysql_query($sql_owner,$connection) or die(mysql_error());
echo "<select name=\"new_owner_id\">";
while ($row_owner = mysql_fetch_object($result_owner)) {

	if ($row_owner->id == $new_owner_id) {

		echo "<option value=\"$row_owner->id\" selected>[ $row_owner->name ]</option>";
	
	} else {

		echo "<option value=\"$row_owner->id\">$row_owner->name</option>";
	
	}
}
echo "</select>";
?>
<BR><BR>
<strong>SSL Provider:</strong><BR><BR>
<?php
$sql_ssl_provider = "SELECT id, name
					 FROM ssl_providers
					 WHERE active = '1'
					 ORDER BY name asc";
$result_ssl_provider = mysql_query($sql_ssl_provider,$connection) or die(mysql_error());
echo "<select name=\"new_ssl_provider_id\">";
while ($row_ssl_provider = mysql_fetch_object($result_ssl_provider)) {

	if ($row_ssl_provider->id == $new_ssl_provider_id) {

		echo "<option value=\"$row_ssl_provider->id\" selected>[ $row_ssl_provider->name ]</option>";
	
	} else {

		echo "<option value=\"$row_ssl_provider->id\">$row_ssl_provider->name</option>";
	
	}
}
echo "</select>";
?>
<BR><BR>
<strong>Username:</strong><BR><BR>
<input name="new_username" type="text" size="50" maxlength="255" value="<?=$new_username?>">
<BR><BR>
<strong>Reseller Account?</strong><BR><BR>
<select name="new_reseller">";
<option value="0"<?php if ($new_reseller != "1") echo " selected"; ?>>No</option>
<option value="1"<?php if ($new_reseller == "1") echo " selected"; ?>>Yes</option>
</select>
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=$new_notes?>
</textarea>
<BR><BR>
<strong>Default Account?:</strong>&nbsp;
<input name="new_default_account" type="checkbox" id="new_default_account" value="1"<?php if ($new_default_account == "1") echo " checked";?>>
<BR><BR><BR>
<input type="submit" name="button" value="Add This SSL Provider Account &raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>