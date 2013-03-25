<?php
// ssl-cert.php
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
$software_section = "ssl-certs";

$sslcid = $_GET['sslcid'];

// 'Delete Cert' Confirmation Variables
$del = $_GET['del'];
$really_del = $_GET['really_del'];

// Form Variables
$new_domain_id = $_POST['new_domain_id'];
$new_name = mysql_real_escape_string($_POST['new_name']);
$new_ip = mysql_real_escape_string($_POST['new_ip']);
$new_type_id = $_POST['new_type_id'];
$new_function_id = $_POST['new_function_id'];
$new_expiry_date = $_POST['new_expiry_date'];
$new_account_id = $_POST['new_account_id'];
$new_active = $_POST['new_active'];
$new_notes = mysql_real_escape_string($_POST['new_notes']);
$new_sslcid = $_POST['new_sslcid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if (preg_match("/^(19|20)\d\d[-](0[1-9]|1[012])[-](0[1-9]|[12][0-9]|3[01])$/i", $new_expiry_date) && $new_name != "" && $new_ip != "") {

		$sql = "select ssl_provider_id, company_id
				from ssl_accounts
				where id = '$new_account_id'";
		$result = mysql_query($sql,$connection);
		
		while ($row = mysql_fetch_object($result)) { $new_ssl_provider_id = $row->ssl_provider_id; $new_company_id = $row->company_id; }

		$sql2 = "select id
				from ssl_fees
				where ssl_provider_id = '$new_ssl_provider_id'
				and type_id = '$new_type_id'
				and function_id = '$new_function_id'";
		$result2 = mysql_query($sql2,$connection);
		
		if (mysql_num_rows($result2) >= 1) { 
		
			while ($row2 = mysql_fetch_object($result2)) {
				$temp_fee_id = $row2->id;
			}
			$temp_fee_fixed = "1"; 

		} else { 

			$temp_fee_id = "0";
			$temp_fee_fixed = "0";

		}

		$sql2 = "update ssl_certs
				 set company_id = '$new_company_id',
					ssl_provider_id = '$new_ssl_provider_id',
					account_id = '$new_account_id',
					domain_id = '$new_domain_id',
					name = '$new_name',
					ip = '$new_ip',
					type_id = '$new_type_id',
					function_id = '$new_function_id',
					expiry_date = '$new_expiry_date',
					fee_id = '$temp_fee_id',
					notes = '$new_notes',
					active = '$new_active',
					fee_fixed = '$temp_fee_fixed',
					update_time = '$current_timestamp'
				where id = '$new_sslcid'";
		$result2 = mysql_query($sql2,$connection) or die(mysql_error());
		
		$sslcid = $new_sslcid;

		include("../_includes/system/check-for-missing-ssl-fees.inc.php");

		$_SESSION['session_result_message'] = "SSL Cert Updated<BR>";

	} else {
	
		if ($new_name == "") { $_SESSION['session_result_message'] .= "Enter The SSL Certificate Name<BR>"; }

		if ($new_ip == "") { $_SESSION['session_result_message'] .= "Enter The SSL Certificate's IP Address<BR>"; }

		if (!preg_match("/^(19|20)\d\d[-](0[1-9]|1[012])[-](0[1-9]|[12][0-9]|3[01])$/i", $new_expiry_date)) { $_SESSION['session_result_message'] .= "The Expiry Date Format Is Incorrect<BR>"; }

	}

} else {

	$sql = "select sslc.domain_id, sslc.name, sslc.ip, sslc.expiry_date, sslc.notes, sslc.active, sslpa.id as account_id, sslct.id as type_id, sslct.type, sslcf.id as function_id, sslcf.function
			from ssl_certs as sslc, ssl_accounts as sslpa, ssl_cert_types as sslct, ssl_cert_functions as sslcf
			where sslc.account_id = sslpa.id
			and sslc.type_id = sslct.id
			and sslc.function_id = sslcf.id
			and sslc.id = '$sslcid'";
	$result = mysql_query($sql,$connection);
	
	while ($row = mysql_fetch_object($result)) { 
	
		$new_domain_id = $row->domain_id;
		$new_name = $row->name;
		$new_ip = $row->ip;
		$new_type_id = $row->type_id;
		$new_function_id = $row->function_id;
		$new_type = $row->type;
		$new_function = $row->function;
		$new_expiry_date = $row->expiry_date;
		$new_notes = $row->notes;
		$new_active = $row->active;
		$new_account_id = $row->account_id;
	
	}

}

if ($del == "1") {

	$_SESSION['session_result_message'] = "Are You Sure You Want To Delete This SSL Certificate?<BR><BR><a href=\"$PHP_SELF?sslcid=$sslcid&really_del=1\">YES, REALLY DELETE THIS SSL CERTIFICATE</a><BR>";

}

if ($really_del == "1") {

	$sql = "delete from ssl_certs where id = '$sslcid'";
	$result = mysql_query($sql,$connection);
	
	$_SESSION['session_result_message'] = "SSL Certificate Deleted ($new_name)<BR>";
	
	header("Location: ../ssl-certs.php");
	exit;

}
$page_title = "Editting An SSL Certificate";
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
				where active not in ('0', '10')
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
<input name="new_name" type="text" size="50" maxlength="255" value="<?php if ($new_name != "") echo stripslashes($new_name); ?>">
<BR><BR>
<strong>IP Address:</strong><BR><BR>
<input name="new_ip" type="text" size="50" maxlength="255" value="<?php if ($new_ip != "") echo stripslashes($new_ip); ?>">
<BR><BR>

<strong>Function:</strong><BR><BR>
<?php
$sql_function = "select id, function
				from ssl_cert_functions
				where active = '1'
				order by function asc";
$result_function = mysql_query($sql_function,$connection) or die(mysql_error());
echo "<select name=\"new_function_id\">";
while ($row_function = mysql_fetch_object($result_function)) {

	if ($row_function->id == $new_function_id) {

		echo "<option value=\"$row_function->id\" selected>[ $row_function->function ]</option>";
	
	} else {

		echo "<option value=\"$row_function->id\">$row_function->function</option>";
	
	}
}
echo "</select>";
?>
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

		echo "<option value=\"$row_type->id\" selected>[ $row_type->type ]</option>";
	
	} else {

		echo "<option value=\"$row_type->id\">$row_type->type</option>";
	
	}
}
echo "</select>";
?>
<BR><BR>

<strong>Expiry Date (YYYY-MM-DD):</strong><BR><BR>
<input name="new_expiry_date" type="text" size="10" maxlength="10" value="<?php if ($new_expiry_date != "") echo $new_expiry_date; ?>">
<BR><BR>
<strong>SSL Provider Account:</strong><BR><BR>
<?php
$sql_account = "select sslpa.id, sslpa.username, c.name as c_name, sslp.name as sslp_name
				from ssl_accounts as sslpa, companies as c, ssl_providers as sslp
				where sslpa.company_id = c.id
				and sslpa.ssl_provider_id = sslp.id
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
echo "<option value=\"5\""; if ($new_active == "2") echo " selected"; echo ">Pending (Registration)</option>";
echo "<option value=\"3\""; if ($new_active == "3") echo " selected"; echo ">Pending (Renewal)</option>";
echo "<option value=\"4\""; if ($new_active == "4") echo " selected"; echo ">Pending (Other)</option>";
echo "<option value=\"0\""; if ($new_active == "0") echo " selected"; echo ">Expired</option>";
echo "</select>";
?>
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=stripslashes($new_notes)?></textarea>
<BR><BR><BR>
<input type="hidden" name="new_sslcid" value="<?=$sslcid?>">
<input type="submit" name="button" value="Update This SSL Certificate &raquo;">
</form>
<BR><BR>
<a href="<?=$PHP_SELF?>?sslcid=<?=$sslcid?>&del=1">DELETE THIS SSL CERTIFICATE</a>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>