<?php
// domain.php
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
include("../_includes/timestamps/current-timestamp-basic-plus-one-year.inc.php");
$software_section = "domains";

// Form Variables
$new_domain = $_POST['new_domain'];
$new_expiry_date = $_POST['new_expiry_date'];
$new_function = $_POST['new_function'];
$new_status = $_POST['new_status'];
$new_status_notes = $_POST['new_status_notes'];
$new_cat_id = $_POST['new_cat_id'];
$new_dns_id = $_POST['new_dns_id'];
$new_ip_id = $_POST['new_ip_id'];
$new_account_id = $_POST['new_account_id'];
$new_privacy = $_POST['new_privacy'];
$new_active = $_POST['new_active'];
$new_notes = $_POST['new_notes'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if (preg_match("/^(19|20)\d\d[-](0[1-9]|1[012])[-](0[1-9]|[12][0-9]|3[01])$/i", $new_expiry_date) && preg_match("/^[A-Z0-9.-]+\.[A-Z]{2,10}$/i", $new_domain)) {

		$tld = preg_replace("/^((.*?)\.)(.*)$/", "\\3", $new_domain);
		
		$sql = "select registrar_id, company_id
				from registrar_accounts
				where id = '$new_account_id'";
		$result = mysql_query($sql,$connection);
		
		while ($row = mysql_fetch_object($result)) { $new_registrar_id = $row->registrar_id; $new_company_id = $row->company_id; }

		$sql = "select id
				from fees
				where registrar_id = '$new_registrar_id' and tld = '$tld'";
		$result = mysql_query($sql,$connection);
		
		while ($row = mysql_fetch_object($result)) { $new_fee_id = $row->id; }

		$sql = "insert into domains
				(company_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, dns_id, ip_id, fee_id, function, status, status_notes, notes, privacy, active, insert_time)
				values ('$new_company_id', '$new_registrar_id', '$new_account_id', '" . mysql_real_escape_string($new_domain) . "', '$tld', '$new_expiry_date', '$new_cat_id', '$new_dns_id', '$new_ip_id', '$new_fee_id', '" . mysql_real_escape_string($new_function) . "', '" . mysql_real_escape_string($new_status) . "', '" . mysql_real_escape_string($new_status_notes) . "', '" . mysql_real_escape_string($new_notes) . "', '$new_privacy', '$new_active', '$current_timestamp')";

		$result = mysql_query($sql,$connection) or die(mysql_error());
		

		include("../_includes/system/check-for-missing-domain-fees.inc.php");

		$_SESSION['session_result_message'] = "Domain Added ($new_domain)<BR>";
		
	} else {
	
		if (!preg_match("/^[A-Z0-9.-]+\.[A-Z]{2,10}$/i", $new_domain)) { $_SESSION['session_result_message'] .= "The Domain Format Is Incorrect<BR>"; }

		if (!preg_match("/^(19|20)\d\d[-](0[1-9]|1[012])[-](0[1-9]|[12][0-9]|3[01])$/i", $new_expiry_date)) { $_SESSION['session_result_message'] .= "The Expiry Date Format Is Incorrect<BR>"; }

	}

}
$page_title = "Adding A New Domain";
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php include("../_includes/header.inc.php"); ?>
<form name="form1" method="post" action="<?=$PHP_SELF?>">
<strong>Domain:</strong><BR><BR>
<input name="new_domain" type="text" size="50" maxlength="255" value="<?=$new_domain?>">
<BR><BR>
<strong>Expiry Date (YYYY-MM-DD):</strong><BR><BR>
<input name="new_expiry_date" type="text" size="10" maxlength="10" value="<?php if ($new_expiry_date != "") { echo $new_expiry_date; } else { echo $current_timestamp_basic_plus_one_year; } ?>">
<BR><BR>
<strong>Function:</strong><BR><BR>
<input name="new_function" type="text" size="50" maxlength="255" value="<?=$new_function?>">
<BR><BR>
<strong>Status:</strong><BR><BR>
<input name="new_status" type="text" size="50" maxlength="255" value="<?=$new_status?>">
<BR><BR>
<strong>Status Notes:</strong><BR><BR>
<textarea name="new_status_notes" cols="60" rows="5"><?=$new_status_notes?>
</textarea>
<BR><BR>
<strong>Primary Category:</strong><BR><BR>
<?php
$sql_cat = "select id, name
				from categories
				where active = '1'
				order by default_category desc, name asc";
$result_cat = mysql_query($sql_cat,$connection) or die(mysql_error());
echo "<select name=\"new_cat_id\">";
while ($row_cat = mysql_fetch_object($result_cat)) {

	if ($row_cat->id == $new_cat_id) {

		echo "<option value=\"$row_cat->id\" selected>[ $row_cat->name ]</option>";
	
	} else {

		echo "<option value=\"$row_cat->id\">$row_cat->name</option>";
	
	}
}
echo "</select>";
?>
<BR><BR>
<strong>DNS Profile:</strong><BR><BR>
<?php
$sql_dns = "select id, name
				from dns
				where active = '1'
				order by name asc";
$result_dns = mysql_query($sql_dns,$connection) or die(mysql_error());
echo "<select name=\"new_dns_id\">";
while ($row_dns = mysql_fetch_object($result_dns)) {

	if ($row_dns->id == $new_dns_id) {

		echo "<option value=\"$row_dns->id\" selected>[ $row_dns->name ]</option>";
	
	} else {

		echo "<option value=\"$row_dns->id\">$row_dns->name</option>";
	
	}
}
echo "</select>";
?>
<BR><BR>
<strong>IP Address:</strong><BR><BR>
<?php
$sql_ip = "select id, name, ip
				from ip_addresses
				order by name asc, ip asc";
$result_ip = mysql_query($sql_ip,$connection) or die(mysql_error());
echo "<select name=\"new_ip_id\">";

while ($row_ip = mysql_fetch_object($result_ip)) {

	if ($row_ip->id == $new_ip_id) {

		echo "<option value=\"$row_ip->id\" selected>[ $row_ip->name ($row_ip->ip) ]</option>";
	
	} else {

		echo "<option value=\"$row_ip->id\">$row_ip->name ($row_ip->ip)</option>";
	
	}
}
echo "</select>";
?>
<BR><BR>
<strong>Registrar Account:</strong><BR><BR>
<?php
$sql_account = "select ra.id, ra.username, c.name as c_name, r.name as r_name
				from registrar_accounts as ra, companies as c, registrars as r
				where ra.company_id = c.id
				and ra.registrar_id = r.id
				and ra.active = '1'
				order by r_name asc, c_name asc, ra.username asc";
$result_account = mysql_query($sql_account,$connection) or die(mysql_error());
echo "<select name=\"new_account_id\">";
while ($row_account = mysql_fetch_object($result_account)) {

	if ($row_account->id == $new_account_id) {

		echo "<option value=\"$row_account->id\" selected>[ $row_account->r_name :: $row_account->c_name :: $row_account->username ]</option>";
	
	} else {

		echo "<option value=\"$row_account->id\">$row_account->r_name :: $row_account->c_name :: $row_account->username</option>";
	
	}
}
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
<strong>Domain Status:</strong><BR><BR>
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
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=$new_notes?></textarea>
<BR><BR><BR>
<input type="submit" name="button" value="Add This Domain &raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>