<?php
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
include("../_includes/timestamps/current-timestamp-basic.inc.php");
include("../_includes/timestamps/current-timestamp-plus-one-year-date-only.inc.php");
$software_section = "ssl-certs";

// Form Variables
$new_domain_id = $_POST['new_domain_id'];
$new_name = $_POST['new_name'];
$new_type_id = $_POST['new_type_id'];
$new_expiry_date = $_POST['new_expiry_date'];
$new_account_id = $_POST['new_account_id'];
$new_active = $_POST['new_active'];
$new_notes = $_POST['new_notes'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if (preg_match("/^(19|20)\d\d[-](0[1-9]|1[012])[-](0[1-9]|[12][0-9]|3[01])$/i", $new_expiry_date) && $new_name != "" && $new_type_id != "" && $new_domain_id != "") {

		$sql = "select ssl_provider_id, company_id
				from ssl_accounts
				where id = '$new_account_id'";
		$result = mysql_query($sql,$connection);
		
		while ($row = mysql_fetch_object($result)) { $new_ssl_provider_id = $row->ssl_provider_id; $new_company_id = $row->company_id; }

		$sql = "select id
				from ssl_fees
				where ssl_provider_id = '$new_ssl_provider_id' and type_id = '$new_type_id'";
		$result = mysql_query($sql,$connection);
		
		while ($row = mysql_fetch_object($result)) { $new_fee_id = $row->id; }

		$sql = "insert into ssl_certs
				(company_id, ssl_provider_id, account_id, domain_id, name, type_id, expiry_date, fee_id, notes, active, insert_time)
				values ('$new_company_id', '$new_ssl_provider_id', '$new_account_id', '$new_domain_id', '$new_name', '$new_type_id', '$new_expiry_date', '$new_fee_id', '$new_notes', '$new_active', '$current_timestamp')";

		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		include("../_includes/system/check-for-missing-ssl-fees.inc.php");
		
		$_SESSION['session_result_message'] = "SSL Certificate Added ($new_name)<BR>";
		
	} else {
	
		if ($new_name == "") { $_SESSION['session_result_message'] .= "Enter The SSL Certificate Name<BR>"; }

		if (!preg_match("/^(19|20)\d\d[-](0[1-9]|1[012])[-](0[1-9]|[12][0-9]|3[01])$/i", $new_expiry_date)) { $_SESSION['session_result_message'] .= "The Expiry Date Format Is Incorrect<BR>"; }

	}

}
$page_title = "Adding A New SSL Certificate";
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../_includes/header.inc.php"); ?>
<form name="form1" method="post" action="<?=$PHP_SELF?>">
<strong>Domain:</strong><BR><BR>
<?php
$sql_domain = "select id, domain
				from domains
				where active in ('1', '2', '3', '4', '5', '6', '7', '8', '9')
				order by domain asc";
$result_domain = mysql_query($sql_domain,$connection) or die(mysql_error());
echo "<select name=\"new_domain_id\">";
while ($row_domain = mysql_fetch_object($result_domain)) {

	if ($row_domain->id == $new_domain_id) {

		echo "<option value=\"$row_domain->id\" selected>$row_domain->domain</option>";
	
	} else {

		echo "<option value=\"$row_domain->id\">$row_domain->domain</option>";
	
	}
}
echo "</select>";
?>
<BR><BR>
<strong>Host / Label:</strong><BR><BR>
<input name="new_name" type="text" size="50" maxlength="255" value="<?=stripslashes($new_name)?>">
<BR><BR>
<strong>Type:</strong><BR><BR>
<?php
$sql_type = "select id, type
				from ssl_cert_types
				where active = '1'
				order by type asc";
$result_type = mysql_query($sql_type,$connection) or die(mysql_error());
echo "<select name=\"new_type_id\">";
while ($row_type = mysql_fetch_object($result_type)) {

	if ($row_type->id == $new_type_id) {

		echo "<option value=\"$row_type->id\" selected>$row_type->type</option>";
	
	} else {

		echo "<option value=\"$row_type->id\">$row_type->type</option>";
	
	}
}
echo "</select>";
?>
<BR><BR>
<strong>Expiry Date (YYYY-MM-DD):</strong><BR><BR>
<input name="new_expiry_date" type="text" size="10" maxlength="10" value="<?php if ($new_expiry_date != "") { echo $new_expiry_date; } else { echo $current_timestamp_plus_one_year_date_only; } ?>">
<BR><BR>
<strong>SSL Provider Account:</strong><BR><BR>
<?php
$sql_account = "select sslpa.id, sslpa.username, c.name as c_name, sslp.name as sslp_name
				from ssl_accounts as sslpa, companies as c, ssl_providers as sslp
				where sslpa.company_id = c.id
				and sslpa.ssl_provider_id = sslp.id
				and sslpa.active = '1'
				order by sslp_name asc, c_name asc, sslpa.username asc";
$result_account = mysql_query($sql_account,$connection) or die(mysql_error());
echo "<select name=\"new_account_id\">";
while ($row_account = mysql_fetch_object($result_account)) {

	if ($row_account->id == $new_account_id) {

		echo "<option value=\"$row_account->id\" selected>[ $row_account->sslp_name :: $row_account->c_name :: $row_account->username ]</option>";
	
	} else {

		echo "<option value=\"$row_account->id\">$row_account->sslp_name :: $row_account->c_name :: $row_account->username</option>";
	
	}
}
echo "</select>";
?>
<BR><BR>
<strong>Certificate Status:</strong><BR><BR>
<?php
echo "<select name=\"new_active\">";
echo "<option value=\"1\""; if ($new_active == "1") echo " selected"; echo ">Active</option>";
echo "<option value=\"5\""; if ($new_active == "5") echo " selected"; echo ">Pending (Registration)</option>";
echo "<option value=\"3\""; if ($new_active == "3") echo " selected"; echo ">Pending (Renewal)</option>";
echo "<option value=\"4\""; if ($new_active == "4") echo " selected"; echo ">Pending (Other)</option>";
echo "<option value=\"0\""; if ($new_active == "0") echo " selected"; echo ">Expired</option>";
echo "</select>";
?>
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=stripslashes($new_notes)?></textarea>
<BR><BR><BR>
<input type="submit" name="button" value="Add This SSL Certificate &raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>