<?php
// /add/domain.php
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
include("../_includes/start-session.inc.php");
include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/auth/auth-check.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");
include("../_includes/timestamps/current-timestamp-basic-plus-one-year.inc.php");
include("../_includes/system/functions/check-domain-format.inc.php");
include("../_includes/system/functions/check-date-format.inc.php");

$page_title = "Adding A New Domain";
$software_section = "domains";

// Form Variables
$new_domain = $_POST['new_domain'];
$new_expiry_date = $_POST['new_expiry_date'];
$new_function = $_POST['new_function'];
$new_cat_id = $_POST['new_cat_id'];
$new_dns_id = $_POST['new_dns_id'];
$new_ip_id = $_POST['new_ip_id'];
$new_hosting_id = $_POST['new_hosting_id'];
$new_account_id = $_POST['new_account_id'];
$new_privacy = $_POST['new_privacy'];
$new_active = $_POST['new_active'];
$new_notes = $_POST['new_notes'];

if ($_SESSION['http_referer_set'] != "1") {
	$_SESSION['http_referer'] = $_SERVER['HTTP_REFERER'];
	$_SESSION['http_referer_set'] = "1";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if (CheckDateFormat($new_expiry_date) && CheckDomainFormat($new_domain) && $new_cat_id != "" && $new_dns_id != "" && $new_ip_id != "" && $new_hosting_id != "" && $new_account_id != "" && $new_cat_id != "0" && $new_dns_id != "0" && $new_ip_id != "0" && $new_hosting_id != "0" && $new_account_id != "0") {

		$tld = preg_replace("/^((.*?)\.)(.*)$/", "\\3", $new_domain);
		
		$sql = "SELECT registrar_id, owner_id
				FROM registrar_accounts
				WHERE id = '$new_account_id'";
		$result = mysql_query($sql,$connection);
		
		while ($row = mysql_fetch_object($result)) { $new_registrar_id = $row->registrar_id; $new_owner_id = $row->owner_id; }

		$sql = "SELECT id
				FROM fees
				WHERE registrar_id = '$new_registrar_id' 
				  AND tld = '$tld'";
		$result = mysql_query($sql,$connection);
		
		while ($row = mysql_fetch_object($result)) { $new_fee_id = $row->id; }

		$sql = "INSERT INTO domains
				(owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, dns_id, ip_id, hosting_id, fee_id, function, notes, privacy, active, insert_time) VALUES 
				('$new_owner_id', '$new_registrar_id', '$new_account_id', '" . mysql_real_escape_string($new_domain) . "', '$tld', '$new_expiry_date', '$new_cat_id', '$new_dns_id', '$new_ip_id', '$new_hosting_id', '$new_fee_id', '" . mysql_real_escape_string($new_function) . "', '" . mysql_real_escape_string($new_notes) . "', '$new_privacy', '$new_active', '$current_timestamp')";

		$result = mysql_query($sql,$connection) or die(mysql_error());

		$_SESSION['result_message'] = "Domain <font class=\"highlight\">$new_domain</font> Added<BR>";

		include("../_includes/system/update-domain-fees.inc.php");
		include("../_includes/system/update-segments.inc.php");
		include("../_includes/auth/login-checks/domain-and-ssl-asset-check.inc.php");

		$_SESSION['http_referer_set'] = "";
		header("Location: " . $_SESSION['http_referer']);
		exit;

	} else {
	
		if (!CheckDomainFormat($new_domain)) { $_SESSION['result_message'] .= "The domain format is incorrect<BR>"; }
		if (!CheckDateFormat($new_expiry_date)) { $_SESSION['result_message'] .= "The expiry date you entered is invalid<BR>"; }

	}

}
?>
<?php include("../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php include("../_includes/header.inc.php"); ?>
<form name="add_domain_form" method="post" action="<?=$PHP_SELF?>">
<strong>Domain</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR>
<input name="new_domain" type="text" size="50" maxlength="255" value="<?=$new_domain?>">
<BR><BR>
<strong>Function</strong><BR><BR>
<input name="new_function" type="text" size="50" maxlength="255" value="<?=$new_function?>">
<BR><BR>
<strong>Expiry Date (YYYY-MM-DD)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR>
<input name="new_expiry_date" type="text" size="10" maxlength="10" value="<?php if ($new_expiry_date != "") { echo $new_expiry_date; } else { echo $current_timestamp_basic_plus_one_year; } ?>">
<BR><BR>
<strong>Registrar Account</strong><BR><BR>
<?php 
$sql_account = "SELECT ra.id, ra.username, o.name AS o_name, r.name AS r_name
				FROM registrar_accounts AS ra, owners AS o, registrars AS r
				WHERE ra.owner_id = o.id
				  AND ra.registrar_id = r.id
				ORDER BY ra.default_account desc, r_name asc, o_name asc, ra.username asc";
$result_account = mysql_query($sql_account,$connection) or die(mysql_error());
echo "<select name=\"new_account_id\">";
while ($row_account = mysql_fetch_object($result_account)) {

	if ($row_account->id == $new_account_id) {

		echo "<option value=\"$row_account->id\" selected>$row_account->r_name :: $row_account->o_name :: $row_account->username</option>";
	
	} else {

		echo "<option value=\"$row_account->id\">$row_account->r_name :: $row_account->o_name :: $row_account->username</option>";
	
	}
}
echo "</select>";
?>
<BR><BR>
<strong>DNS Profile</strong><BR><BR>
<?php
$sql_dns = "SELECT id, name
			FROM dns
			ORDER BY default_dns desc, name asc";
$result_dns = mysql_query($sql_dns,$connection) or die(mysql_error());
echo "<select name=\"new_dns_id\">";
while ($row_dns = mysql_fetch_object($result_dns)) {

	if ($row_dns->id == $new_dns_id) {

		echo "<option value=\"$row_dns->id\" selected>$row_dns->name</option>";
	
	} else {

		echo "<option value=\"$row_dns->id\">$row_dns->name</option>";
	
	}
}
echo "</select>";
?>
<BR><BR>
<strong>IP Address</strong><BR><BR>
<?php
$sql_ip = "SELECT id, name, ip
		   FROM ip_addresses
		   ORDER BY default_ip_address desc, name asc, ip asc";
$result_ip = mysql_query($sql_ip,$connection) or die(mysql_error());
echo "<select name=\"new_ip_id\">";

while ($row_ip = mysql_fetch_object($result_ip)) {

	if ($row_ip->id == $new_ip_id) {

		echo "<option value=\"$row_ip->id\" selected>$row_ip->name ($row_ip->ip)</option>";
	
	} else {

		echo "<option value=\"$row_ip->id\">$row_ip->name ($row_ip->ip)</option>";
	
	}
}
echo "</select>";
?>
<BR><BR>
<strong>Web Hosting Provider</strong><BR><BR>
<?php
$sql_hosting = "SELECT id, name
				FROM hosting
				ORDER BY default_host desc, name asc";
$result_hosting = mysql_query($sql_hosting,$connection) or die(mysql_error());
echo "<select name=\"new_hosting_id\">";
while ($row_hosting = mysql_fetch_object($result_hosting)) {

	if ($row_hosting->id == $new_hosting_id) {

		echo "<option value=\"$row_hosting->id\" selected>$row_hosting->name</option>";
	
	} else {

		echo "<option value=\"$row_hosting->id\">$row_hosting->name</option>";
	
	}
}
echo "</select>";
?>
<BR><BR>
<strong>Category</strong><BR><BR>
<?php
$sql_cat = "SELECT id, name
			FROM categories
			ORDER BY default_category desc, name asc";
$result_cat = mysql_query($sql_cat,$connection) or die(mysql_error());
echo "<select name=\"new_cat_id\">";
while ($row_cat = mysql_fetch_object($result_cat)) {

	if ($row_cat->id == $new_cat_id) {

		echo "<option value=\"$row_cat->id\" selected>$row_cat->name</option>";
	
	} else {

		echo "<option value=\"$row_cat->id\">$row_cat->name</option>";
	
	}
}
echo "</select>";
?>
<BR><BR>
<strong>Domain Status</strong><BR><BR>
<?php
echo "<select name=\"new_active\">";
echo "<option value=\"1\""; if ($new_active == "1") echo " selected"; echo ">Active</option>";
echo "<option value=\"2\""; if ($new_active == "2") echo " selected"; echo ">In Transfer</option>";
echo "<option value=\"5\""; if ($new_active == "5") echo " selected"; echo ">Pending (Registration)</option>";
echo "<option value=\"3\""; if ($new_active == "3") echo " selected"; echo ">Pending (Renewal)</option>";
echo "<option value=\"4\""; if ($new_active == "4") echo " selected"; echo ">Pending (Other)</option>";
echo "<option value=\"0\""; if ($new_active == "0") echo " selected"; echo ">Expired</option>";
echo "<option value=\"10\""; if ($new_active == "10") echo " selected"; echo ">Sold</option>";
echo "</select>";
?>
<BR><BR>
<strong>Privacy Enabled?</strong><BR><BR>
<?php
echo "<select name=\"new_privacy\">";
echo "<option value=\"0\""; if ($new_privacy == "0") echo " selected"; echo ">No</option>";
echo "<option value=\"1\""; if ($new_privacy == "1") echo " selected"; echo ">Yes</option>";
echo "</select>";
?>
<BR><BR>
<strong>Notes</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=$new_notes?></textarea>
<BR><BR><BR>
<input type="submit" name="button" value="Add This Domain &raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>