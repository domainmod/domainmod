<?php
// /edit/ssl-cert.php
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

$page_title = "Editting An SSL Certificate";
$software_section = "ssl-certs";

// 'Delete Cert' Confirmation Variables
$del = $_GET['del'];
$really_del = $_GET['really_del'];

$sslcid = $_GET['sslcid'];

// Form Variables
$new_domain_id = $_POST['new_domain_id'];
$new_name = $_POST['new_name'];
$new_type_id = $_POST['new_type_id'];
$new_expiry_date = $_POST['new_expiry_date'];
$new_account_id = $_POST['new_account_id'];
$new_active = $_POST['new_active'];
$new_notes = $_POST['new_notes'];
$new_sslcid = $_POST['new_sslcid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	function MyCheckDate( $postedDate ) {
	   if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $postedDate, $datebit)) {
		  return checkdate($datebit[2] , $datebit[3] , $datebit[1]);
	   } else {
		  return false;
	   }
	} 	

	if (MyCheckDate($new_expiry_date) && $new_name != "" && $new_domain_id != "" && $new_account_id != "" && $new_type_id != "" && $new_domain_id != "0" && $new_account_id != "0" && $new_type_id != "0") {

		$sql = "SELECT ssl_provider_id, owner_id
				FROM ssl_accounts
				WHERE id = '$new_account_id'";
		$result = mysql_query($sql,$connection);
		
		while ($row = mysql_fetch_object($result)) { $new_ssl_provider_id = $row->ssl_provider_id; $new_owner_id = $row->owner_id; }

		$sql2 = "SELECT id
				 FROM ssl_fees
				 WHERE ssl_provider_id = '$new_ssl_provider_id'
				   AND type_id = '$new_type_id'";
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

		$sql2 = "UPDATE ssl_certs
				 SET owner_id = '$new_owner_id',
					ssl_provider_id = '$new_ssl_provider_id',
					account_id = '$new_account_id',
					domain_id = '$new_domain_id',
					name = '" . mysql_real_escape_string($new_name) . "',
					type_id = '$new_type_id',
					expiry_date = '$new_expiry_date',
					fee_id = '$temp_fee_id',
					notes = '" . mysql_real_escape_string($new_notes) . "',
					active = '$new_active',
					fee_fixed = '$temp_fee_fixed',
					update_time = '$current_timestamp'
				WHERE id = '$new_sslcid'";
		$result2 = mysql_query($sql2,$connection) or die(mysql_error());
		
		$sslcid = $new_sslcid;

		$_SESSION['session_result_message'] = "SSL Certificate <font class=\"highlight\">$new_name</font> Updated<BR>";

		include("../_includes/system/update-ssl-fees.inc.php");

	} else {
	
		if ($new_name == "") { $_SESSION['session_result_message'] .= "Enter the SSL certificate name<BR>"; }
		if (!MyCheckDate($new_expiry_date)) { $_SESSION['session_result_message'] .= "The expiry date you entered is invalid<BR>"; }

	}

} else {

	$sql = "SELECT sslc.domain_id, sslc.name, sslc.expiry_date, sslc.notes, sslc.active, sslpa.id AS account_id, sslcf.id AS type_id, sslcf.type
			FROM ssl_certs AS sslc, ssl_accounts AS sslpa, ssl_cert_types AS sslcf
			WHERE sslc.account_id = sslpa.id
			  AND sslc.type_id = sslcf.id
			  AND sslc.id = '$sslcid'";
	$result = mysql_query($sql,$connection);
	
	while ($row = mysql_fetch_object($result)) { 
	
		$new_domain_id = $row->domain_id;
		$new_name = $row->name;
		$new_type_id = $row->type_id;
		$new_type = $row->type;
		$new_expiry_date = $row->expiry_date;
		$new_notes = $row->notes;
		$new_active = $row->active;
		$new_account_id = $row->account_id;
	
	}

}

if ($del == "1") {

	$_SESSION['session_result_message'] = "Are you sure you want to delete this SSL Certificate?<BR><BR><a href=\"$PHP_SELF?sslcid=$sslcid&really_del=1\">YES, REALLY DELETE THIS SSL CERTIFICATE ACCOUNT</a><BR>";

}

if ($really_del == "1") {

	$sql = "DELETE FROM ssl_certs
			WHERE id = '$sslcid'";
	$result = mysql_query($sql,$connection);
	
	$sql = "SELECT type
			FROM ssl_cert_types
			WHERE id = '$new_type_id'";
	$result = mysql_query($sql,$connection);
	while ($row = mysql_fetch_object($result)) { $temp_type = $row->type; }
	
	$_SESSION['session_result_message'] = "SSL Certificate <font class=\"highlight\">$new_name ($temp_type)</font> Deleted<BR>";

	include("../_includes/auth/login-checks/domain-and-ssl-asset-check.inc.php");
	
	header("Location: ../ssl-certs.php");
	exit;

}
?>
<?php include("../_includes/doctype.inc.php"); ?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../_includes/header.inc.php"); ?>
<form name="edit_ssl_cert_form" method="post" action="<?=$PHP_SELF?>">
<strong>Host / Label</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR>
<input name="new_name" type="text" size="50" maxlength="255" value="<?php if ($new_name != "") echo $new_name; ?>">
<BR><BR>
<strong>Expiry Date (YYYY-MM-DD)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR>
<input name="new_expiry_date" type="text" size="10" maxlength="10" value="<?php if ($new_expiry_date != "") echo $new_expiry_date; ?>">
<BR><BR>
<strong>Domain</strong><BR><BR>
<?php
$sql_domain = "SELECT id, domain
			   FROM domains
			   ORDER BY domain asc";
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
<strong>SSL Provider Account</strong><BR><BR>
<?php
$sql_account = "SELECT sslpa.id, sslpa.username, o.name AS o_name, sslp.name AS sslp_name
				FROM ssl_accounts AS sslpa, owners AS o, ssl_providers AS sslp
				WHERE sslpa.owner_id = o.id
				  AND sslpa.ssl_provider_id = sslp.id
				ORDER BY sslp_name asc, o_name asc, sslpa.username asc";
$result_account = mysql_query($sql_account,$connection) or die(mysql_error());
echo "<select name=\"new_account_id\">";
while ($row_account = mysql_fetch_object($result_account)) {

	if ($row_account->id == $new_account_id) {

		echo "<option value=\"$row_account->id\" selected>$row_account->sslp_name :: $row_account->o_name :: $row_account->username</option>";
	
	} else {

		echo "<option value=\"$row_account->id\">$row_account->sslp_name :: $row_account->o_name :: $row_account->username</option>";
	
	}
}
echo "</select>";
?>
<BR><BR>
<strong>Certificate Type</strong><BR><BR>
<?php
$sql_type = "SELECT id, type
			 FROM ssl_cert_types
			 ORDER BY type asc";
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
<strong>Certificate Status</strong><BR><BR>
<?php
echo "<select name=\"new_active\">";
echo "<option value=\"1\""; if ($new_active == "1") echo " selected"; echo ">Active</option>";
echo "<option value=\"2\""; if ($new_active == "5") echo " selected"; echo ">Pending (Registration)</option>";
echo "<option value=\"3\""; if ($new_active == "3") echo " selected"; echo ">Pending (Renewal)</option>";
echo "<option value=\"4\""; if ($new_active == "4") echo " selected"; echo ">Pending (Other)</option>";
echo "<option value=\"0\""; if ($new_active == "0") echo " selected"; echo ">Expired</option>";
echo "</select>";
?>
<BR><BR>
<strong>Notes</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=$new_notes?></textarea>
<BR><BR><BR>
<input type="hidden" name="new_sslcid" value="<?=$sslcid?>">
<input type="submit" name="button" value="Update This SSL Certificate &raquo;">
</form>
<BR><BR>
<a href="<?=$PHP_SELF?>?sslcid=<?=$sslcid?>&del=1">DELETE THIS SSL CERTIFICATE</a>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>