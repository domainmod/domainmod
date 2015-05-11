<?php
/**
 * /edit/ssl-cert.php
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
include("../_includes/start-session.inc.php");
include("../_includes/init.inc.php");
include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "database.inc.php");
include(DIR_INC . "auth/auth-check.inc.php");
require_once(DIR_INC . "classes/Autoloader.class.php");

spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$error = new DomainMOD\Error();
$time = new DomainMOD\Timestamp();
$timestamp = $time->time();

$page_title = "Editing An SSL Certificate";
$software_section = "ssl-cert-edit";

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$sslcid = $_GET['sslcid'];

$new_domain_id = $_POST['new_domain_id'];
$new_name = $_POST['new_name'];
$new_type_id = $_POST['new_type_id'];
$new_ip_id = $_POST['new_ip_id'];
$new_cat_id = $_POST['new_cat_id'];
$new_expiry_date = $_POST['new_expiry_date'];
$new_account_id = $_POST['new_account_id'];
$new_active = $_POST['new_active'];
$new_notes = $_POST['new_notes'];
$new_sslcid = $_POST['new_sslcid'];

// Custom Fields
$sql = "SELECT field_name
		FROM ssl_cert_fields
		ORDER BY name";
$result = mysqli_query($connection, $sql);

if (mysqli_num_rows($result) > 0) {

    $count = 0;

    while ($row = mysqli_fetch_object($result)) {

        $field_array[$count] = $row->field_name;
        $count++;

    }

    foreach ($field_array as $field) {

        $full_field = "new_" . $field . "";
        ${'new_' . $field} = $_POST[$full_field];

    }

}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $date = new DomainMOD\Date();

    if ($date->checkDateFormat($new_expiry_date) && $new_name != "" && $new_domain_id != "" && $new_account_id != "" && $new_type_id != "" && $new_ip_id != "" && $new_cat_id != "" && $new_domain_id != "0" && $new_account_id != "0" && $new_type_id != "0" && $new_ip_id != "0" && $new_cat_id != "0") {

		$sql = "SELECT ssl_provider_id, owner_id
				FROM ssl_accounts
				WHERE id = '" . $new_account_id . "'";
		$result = mysqli_query($connection, $sql);
		
		while ($row = mysqli_fetch_object($result)) { $new_ssl_provider_id = $row->ssl_provider_id; $new_owner_id = $row->owner_id; }

		$sql_fee_id = "SELECT id
					   FROM ssl_fees
					   WHERE ssl_provider_id = '" . $new_ssl_provider_id . "'
					     AND type_id = '" . $new_type_id . "'";
		$result_fee_id = mysqli_query($connection, $sql_fee_id);
		
		if (mysqli_num_rows($result_fee_id) >= 1) { 
		
			while ($row_fee_id = mysqli_fetch_object($result_fee_id)) {
				$temp_fee_id = $row_fee_id->id;
			}
			$temp_fee_fixed = "1"; 

		} else { 

			$temp_fee_id = "0";
			$temp_fee_fixed = "0";

		}

        $fee_string = "renewal_fee + misc_fee";

        $sql = "SELECT (" . $fee_string . ") AS total_cost
                FROM ssl_fees
                WHERE ssl_provider_id = '" . $new_ssl_provider_id . "'
                  AND type_id = '" . $new_type_id . "'";
        $result = mysqli_query($connection, $sql);

        while ($row = mysqli_fetch_object($result)) { $new_total_cost = $row->total_cost; }

		$sql_update = "UPDATE ssl_certs
					   SET owner_id = '" . $new_owner_id . "',
					   	   ssl_provider_id = '" . $new_ssl_provider_id . "',
						   account_id = '" . $new_account_id . "',
						   domain_id = '" . $new_domain_id . "',
						   name = '" . mysqli_real_escape_string($connection, $new_name) . "',
						   type_id = '" . $new_type_id . "',
						   ip_id = '" . $new_ip_id . "',
						   cat_id = '" . $new_cat_id . "',
						   expiry_date = '" . $new_expiry_date . "',
						   fee_id = '" . $temp_fee_id . "',
						   total_cost = '" . $new_total_cost . "',
						   notes = '" . mysqli_real_escape_string($connection, $new_notes) . "',
						   active = '" . $new_active . "',
						   fee_fixed = '" . $temp_fee_fixed . "',
						   update_time = '" . $timestamp . "'
					   WHERE id = '" . $new_sslcid . "'";
		$result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);

		$sql = "SELECT field_name
				FROM ssl_cert_fields
				ORDER BY name";
		$result = mysqli_query($connection, $sql);
		
		$count = 0;
		
		while ($row = mysqli_fetch_object($result)) {
			
			$field_array[$count] = $row->field_name;
			$count++;
		
		}
		
		foreach($field_array as $field) {
			
			$full_field = "new_" . $field;
			
			$sql = "UPDATE ssl_cert_field_data
					SET `" . $field . "` = '" . mysqli_real_escape_string($connection, ${$full_field}) . "',
						update_time = '" . $timestamp . "'
					WHERE ssl_id = '" . $new_sslcid . "'";
			$result = mysqli_query($connection, $sql);
		
		}
		
		$sslcid = $new_sslcid;

		$_SESSION['result_message'] = "SSL Certificate <font class=\"highlight\">$new_name</font> Updated<BR>";

        include(DIR_INC . "system/check-ssl-fees.inc.php");

		header("Location: ssl-cert.php?sslcid=$sslcid");
		exit;

	} else {
	
		if ($new_name == "") { $_SESSION['result_message'] .= "Enter the SSL certificate name<BR>"; }
		if (!$date->checkDateFormat($new_expiry_date)) { $_SESSION['result_message'] .= "The expiry date you entered is invalid<BR>"; }

	}

} else {

	$sql = "SELECT sslc.domain_id, sslc.name, sslc.expiry_date, sslc.notes, sslc.active, sslpa.id AS account_id, sslcf.id AS type_id, ip.id AS ip_id, cat.id AS cat_id
			FROM ssl_certs AS sslc, ssl_accounts AS sslpa, ssl_cert_types AS sslcf, ip_addresses AS ip, categories AS cat
			WHERE sslc.account_id = sslpa.id
			  AND sslc.type_id = sslcf.id
			  AND sslc.ip_id = ip.id
			  AND sslc.cat_id = cat.id
			  AND sslc.id = '" . $sslcid . "'";
	$result = mysqli_query($connection, $sql);
	
	while ($row = mysqli_fetch_object($result)) { 
	
		$new_domain_id = $row->domain_id;
		$new_name = $row->name;
		$new_type_id = $row->type_id;
		$new_ip_id = $row->ip_id;
		$new_cat_id = $row->cat_id;
		$new_expiry_date = $row->expiry_date;
		$new_notes = $row->notes;
		$new_active = $row->active;
		$new_account_id = $row->account_id;
	
	}

}

if ($del == "1") {

	$_SESSION['result_message'] = "Are you sure you want to delete this SSL Certificate?<BR><BR><a href=\"ssl-cert.php?sslcid=$sslcid&really_del=1\">YES, REALLY DELETE THIS SSL CERTIFICATE ACCOUNT</a><BR>";

}

if ($really_del == "1") {

	$sql = "DELETE FROM ssl_certs
			WHERE id = '" . $sslcid . "'";
	$result = mysqli_query($connection, $sql);

	$sql = "DELETE FROM ssl_cert_field_data
			WHERE ssl_id = '" . $sslcid . "'";
	$result = mysqli_query($connection, $sql);
	
	$sql = "SELECT type
			FROM ssl_cert_types
			WHERE id = '" . $new_type_id . "'";
	$result = mysqli_query($connection, $sql);
	while ($row = mysqli_fetch_object($result)) { $temp_type = $row->type; }
	
	$_SESSION['result_message'] = "SSL Certificate <font class=\"highlight\">$new_name ($temp_type)</font> Deleted<BR>";

	include(DIR_INC . "auth/login-checks/domain-and-ssl-asset-check.inc.php");
	
	header("Location: ../ssl-certs.php");
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
<form name="edit_ssl_cert_form" method="post">
<strong>Host / Label (100)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR>
<input name="new_name" type="text" size="50" maxlength="100" value="<?php if ($new_name != "") echo htmlentities($new_name); ?>">
<BR><BR>
<strong>Expiry Date (YYYY-MM-DD)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR>
<input name="new_expiry_date" type="text" size="10" maxlength="10" value="<?php if ($new_expiry_date != "") echo $new_expiry_date; ?>">
<BR><BR>
<strong>Domain</strong><BR><BR>
<?php
$sql_domain = "SELECT id, domain
			   FROM domains
			   WHERE (active NOT IN ('0', '10') OR id = '" . $new_domain_id . "')
			   ORDER BY domain";
$result_domain = mysqli_query($connection, $sql_domain) or $error->outputOldSqlError($connection);
echo "<select name=\"new_domain_id\">";
while ($row_domain = mysqli_fetch_object($result_domain)) { ?>

	<option value="<?php echo $row_domain->id; ?>"<?php if ($row_domain->id == $new_domain_id) echo " selected";?>><?php echo $row_domain->domain; ?></option><?php

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
$result_account = mysqli_query($connection, $sql_account) or $error->outputOldSqlError($connection);
echo "<select name=\"new_account_id\">";
while ($row_account = mysqli_fetch_object($result_account)) { ?>

	<option value="<?php echo $row_account->id; ?>"<?php if ($row_account->id == $new_account_id) echo " selected";?>><?php echo $row_account->sslp_name; ?>, <?php echo $row_account->o_name; ?> (<?php echo $row_account->username; ?>)</option><?php

}
echo "</select>";
?>
<BR><BR>
<strong>Certificate Type</strong><BR><BR>
<?php
$sql_type = "SELECT id, type
			 FROM ssl_cert_types
			 ORDER BY type asc";
$result_type = mysqli_query($connection, $sql_type) or $error->outputOldSqlError($connection);
echo "<select name=\"new_type_id\">";
while ($row_type = mysqli_fetch_object($result_type)) { ?>

	<option value="<?php echo $row_type->id; ?>"<?php if ($row_type->id == $new_type_id) echo " selected";?>><?php echo $row_type->type; ?></option><?php

}
echo "</select>";
?>
<BR><BR>
<strong>IP Address</strong><BR><BR>
<?php
$sql_ip = "SELECT id, ip, name
		   FROM ip_addresses
		   ORDER BY name, ip";
$result_ip = mysqli_query($connection, $sql_ip) or $error->outputOldSqlError($connection);
echo "<select name=\"new_ip_id\">";
while ($row_ip = mysqli_fetch_object($result_ip)) { ?>

	<option value="<?php echo $row_ip->id; ?>"<?php if ($row_ip->id == $new_ip_id) echo " selected";?>><?php echo $row_ip->name; ?> (<?php echo $row_ip->ip; ?>)</option><?php

}
echo "</select>";
?>
<BR><BR>
<strong>Category</strong><BR><BR>
<?php
$sql_cat = "SELECT id, name
			FROM categories
			ORDER BY name";
$result_cat = mysqli_query($connection, $sql_cat) or $error->outputOldSqlError($connection);
echo "<select name=\"new_cat_id\">";
while ($row_cat = mysqli_fetch_object($result_cat)) { ?>

	<option value="<?php echo $row_cat->id; ?>"<?php if ($row_cat->id == $new_cat_id) echo " selected";?>><?php echo $row_cat->name; ?></option><?php

}
echo "</select>";
?>
<BR><BR>
<strong>Certificate Status</strong><BR><BR>
<?php
echo "<select name=\"new_active\">";
echo "<option value=\"1\""; if ($new_active == "1") echo " selected"; echo ">Active</option>";
echo "<option value=\"2\""; if ($new_active == "2") echo " selected"; echo ">Pending (Registration)</option>";
echo "<option value=\"3\""; if ($new_active == "3") echo " selected"; echo ">Pending (Renewal)</option>";
echo "<option value=\"4\""; if ($new_active == "4") echo " selected"; echo ">Pending (Other)</option>";
echo "<option value=\"0\""; if ($new_active == "0") echo " selected"; echo ">Expired</option>";
echo "</select>";
?>
<BR><BR>
<strong>Notes</strong><?php if ($new_notes != "") { ?> [<a target="_blank" href="ssl-cert-notes.php?sslcid=<?php echo $sslcid; ?>">view full notes</a>]<?php } ?><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?></textarea>
<input type="hidden" name="new_sslcid" value="<?php echo $sslcid; ?>">
<BR><BR>
<?php
$sql = "SELECT field_name
		FROM ssl_cert_fields
		ORDER BY type_id, name";
$result = mysqli_query($connection, $sql);

if (mysqli_num_rows($result) > 0) { ?>

	<BR><font class="subheadline">Custom Fields</font><BR><BR><?php

	$count = 0;
	
	while ($row = mysqli_fetch_object($result)) {
		
		$field_array[$count] = $row->field_name;
		$count++;
	
	}
	
	foreach($field_array as $field) {
		
		$sql = "SELECT sf.name, sf.field_name, sf.type_id, sf.description
				FROM ssl_cert_fields AS sf, custom_field_types AS cft
				WHERE sf.type_id = cft.id
				  AND sf.field_name = '" . $field . "'";
		$result = mysqli_query($connection, $sql);
	
		while ($row = mysqli_fetch_object($result)) {
			
			$sql_data = "SELECT " . $row->field_name . " 
						 FROM ssl_cert_field_data
						 WHERE ssl_id = '" . $sslcid . "'";
			$result_data = mysqli_query($connection, $sql_data);
			
			while ($row_data = mysqli_fetch_object($result_data)) {
				
				$field_data = $row_data->{$row->field_name};
	
			}
	
			if ($row->type_id == "1") { // Check Box ?>

                <input type="checkbox" name="new_<?php echo $row->field_name; ?>" value="1"<?php if ($field_data == "1") echo " checked"; ?>>
                &nbsp;<strong><?php echo $row->name; ?></strong><BR><?php
				
				if ($row->description != "") {

					echo $row->description . "<BR><BR>";

				} else {
					
					echo "<BR>";
					
				}
	
			} elseif ($row->type_id == "2") { // Text ?>
	
				<strong><?php echo $row->name; ?> (255)</strong><BR><?php
				if ($row->description != "") {

					echo $row->description . "<BR><BR>";

				} else {
					
					echo "<BR>";
					
				} ?>
                <input type="text" name="new_<?php echo $row->field_name; ?>" size="50" maxlength="255" value="<?php echo htmlentities($field_data); ?>"><BR><BR><?php

			} elseif ($row->type_id == "3") { // Text Area ?>
	
				<strong><?php echo $row->name; ?></strong><BR><?php
				if ($row->description != "") {

					echo $row->description . "<BR><BR>";

				} else {
					
					echo "<BR>";
					
				} ?>
                <textarea name="new_<?php echo $row->field_name; ?>" cols="60" rows="5"><?php echo $field_data; ?></textarea><BR><BR><?php

			}
			
		}
	
	}
	
	echo "<BR>";
	
}
?>
<input type="submit" name="button" value="Update This SSL Certificate &raquo;">
</form>
<BR><BR>
<a href="ssl-cert.php?sslcid=<?php echo $sslcid; ?>&del=1">DELETE THIS SSL CERTIFICATE</a>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
