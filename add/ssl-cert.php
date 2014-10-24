<?php
// /add/ssl-cert.php
// 
// DomainMOD is an open source application written in PHP & MySQL used to track and manage your web resources.
// Copyright (C) 2010 Greg Chetcuti
// 
// DomainMOD is free software; you can redistribute it and/or modify it under the terms of the GNU General
// Public License as published by the Free Software Foundation; either version 2 of the License, or (at your
// option) any later version.
// 
// DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the
// implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License
// for more details.
// 
// You should have received a copy of the GNU General Public License along with DomainMOD. If not, please see
// http://www.gnu.org/licenses/
?>
<?php
include("../_includes/start-session.inc.php");
include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/auth/auth-check.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");
include("../_includes/timestamps/current-timestamp-basic-plus-one-year.inc.php");
include("../_includes/system/functions/check-date-format.inc.php");

$page_title = "Adding A New SSL Certificate";
$software_section = "ssl-cert-add";

// Form Variables
$new_domain_id = $_POST['new_domain_id'];
$new_name = $_POST['new_name'];
$new_type_id = $_POST['new_type_id'];
$new_ip_id = $_POST['new_ip_id'];
$new_cat_id = $_POST['new_cat_id'];
$new_expiry_date = $_POST['new_expiry_date'];
$new_account_id = $_POST['new_account_id'];
$new_active = $_POST['new_active'];
$new_notes = $_POST['new_notes'];

// Custom Fields
$sql = "SELECT field_name
		FROM ssl_cert_fields
		ORDER BY name";
$result = mysql_query($sql,$connection);

$count = 0;

while ($row = mysql_fetch_object($result)) {
	
	$field_array[$count] = $row->field_name;
	$count++;

}

foreach($field_array as $field) {

	$full_field = "new_" . $field . "";
	${'new_' . $field} = $_POST[$full_field];
	
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if (CheckDateFormat($new_expiry_date) && $new_name != "" && $new_type_id != "" && $new_ip_id != "" && $new_cat_id != "" && $new_domain_id != "" && $new_account_id != "" && $new_type_id != "0" && $new_ip_id != "0" && $new_cat_id != "0" && $new_domain_id != "0" && $new_account_id != "0") {

		$sql = "SELECT ssl_provider_id, owner_id
				FROM ssl_accounts
				WHERE id = '" . $new_account_id . "'";
		$result = mysql_query($sql,$connection);
		
		while ($row = mysql_fetch_object($result)) { $new_ssl_provider_id = $row->ssl_provider_id; $new_owner_id = $row->owner_id; }

        $fee_string = "renewal_fee + misc_fee";

        $sql = "SELECT id, (" . $fee_string . ") AS total_cost
                FROM ssl_fees
                WHERE ssl_provider_id = '" . $new_ssl_provider_id . "'
                  AND type_id = '" . $new_type_id . "'";
        $result = mysql_query($sql,$connection);

        while ($row = mysql_fetch_object($result)) { $new_fee_id = $row->id; $new_total_cost = $row->total_cost; }

		$sql = "INSERT INTO ssl_certs
				(owner_id, ssl_provider_id, account_id, domain_id, name, type_id, ip_id, cat_id, expiry_date, fee_id, total_cost, notes, active, insert_time) VALUES
				('" . $new_owner_id . "', '" . $new_ssl_provider_id . "', '" . $new_account_id . "', '" . $new_domain_id . "', '" . mysql_real_escape_string($new_name) . "', '" . $new_type_id . "', '" . $new_ip_id . "', '" . $new_cat_id . "', '" . $new_expiry_date . "', '" . $new_fee_id . "', '" . $new_total_cost . "', '" . mysql_real_escape_string($new_notes) . "', '" . $new_active . "', '" . $current_timestamp . "')";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "SELECT id
				FROM ssl_certs
				WHERE name = '" . mysql_real_escape_string($new_name) . "'
				  AND insert_time = '" . $current_timestamp . "'";
		$result = mysql_query($sql,$connection);
		while ($row = mysql_fetch_object($result)) { $temp_ssl_id = $row->id; }

		$sql = "INSERT INTO ssl_cert_field_data
				(ssl_id, insert_time) VALUES 
				('" . $temp_ssl_id . "', '" . $current_timestamp . "')";
		$result = mysql_query($sql,$connection);

		$sql = "SELECT field_name
				FROM ssl_cert_fields
				ORDER BY name";
		$result = mysql_query($sql,$connection);
		
		$count = 0;
		
		while ($row = mysql_fetch_object($result)) {
			
			$field_array[$count] = $row->field_name;
			$count++;
		
		}
		
		foreach($field_array as $field) {
			
			$full_field = "new_" . $field;
			
			$sql = "UPDATE ssl_cert_field_data
					SET `" . $field . "` = '" . mysql_real_escape_string(${$full_field}) . "' 
					WHERE ssl_id = '" . $temp_ssl_id . "'";
			$result = mysql_query($sql,$connection);
		
		}

		$_SESSION['result_message'] = "SSL Certificate <font class=\"highlight\">$new_name</font> Added<BR>";

		include("../_includes/system/update-ssl-fees.inc.php");
		include("../_includes/auth/login-checks/domain-and-ssl-asset-check.inc.php");

		header("Location: ../ssl-certs.php");
		exit;

	} else {
	
		if ($new_name == "") { $_SESSION['result_message'] .= "Enter a name for the SSL certificate<BR>"; }
		if (!CheckDateFormat($new_expiry_date)) { $_SESSION['result_message'] .= "The expiry date you entered is invalid<BR>"; }

	}

}
?>
<?php include("../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/layout/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php include("../_includes/layout/header.inc.php"); ?>
<form name="add_ssl_cert_form" method="post" action="<?=$PHP_SELF?>">
<strong>Host / Label (100)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR>
<input name="new_name" type="text" size="50" maxlength="100" value="<?=$new_name?>">
<BR><BR>
<strong>Expiry Date (YYYY-MM-DD)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR>
<input name="new_expiry_date" type="text" size="10" maxlength="10" value="<?php if ($new_expiry_date != "") { echo $new_expiry_date; } else { echo $current_timestamp_basic_plus_one_year; } ?>">
<BR><BR>
<strong>Domain</strong><BR><BR>
<?php
$sql_domain = "SELECT id, domain
			   FROM domains
			   WHERE active NOT IN ('0', '10')
			   ORDER BY domain asc";
$result_domain = mysql_query($sql_domain,$connection) or die(mysql_error());
echo "<select name=\"new_domain_id\">";
while ($row_domain = mysql_fetch_object($result_domain)) { ?>

	<option value="<?=$row_domain->id?>"<?php if ($row_domain->id == $new_domain_id) echo " selected";?>><?=$row_domain->domain?></option><?php

}
echo "</select>";
?>
<BR><BR>
<strong>SSL Provider Account</strong><BR><BR>
<?php
$sql_account = "SELECT sslpa.id, sslpa.username, o.name as o_name, sslp.name as sslp_name
				FROM ssl_accounts as sslpa, owners as o, ssl_providers as sslp
				WHERE sslpa.owner_id = o.id
				  AND sslpa.ssl_provider_id = sslp.id
				ORDER BY sslp_name, o_name, sslpa.username";
$result_account = mysql_query($sql_account,$connection) or die(mysql_error());
echo "<select name=\"new_account_id\">";
while ($row_account = mysql_fetch_object($result_account)) { ?>

	<option value="<?=$row_account->id?>"<?php if ($row_account->id == $_SESSION['default_ssl_provider_account']) echo " selected";?>><?=$row_account->sslp_name?>, <?=$row_account->o_name?> (<?=$row_account->username?>)</option><?php

}
echo "</select>";
?>
<BR><BR>
<strong>Certificate Type</strong><BR><BR>
<?php
$sql_type = "SELECT id, type
			 FROM ssl_cert_types
			 ORDER BY type";
$result_type = mysql_query($sql_type,$connection) or die(mysql_error());
echo "<select name=\"new_type_id\">";
while ($row_type = mysql_fetch_object($result_type)) { ?>

	<option value="<?=$row_type->id?>"<?php if ($row_type->id == $_SESSION['default_ssl_type']) echo " selected";?>><?=$row_type->type?></option><?php

}
echo "</select>";
?>
<BR><BR>
<strong>IP Address</strong><BR><BR>
<?php
$sql_ip = "SELECT id, ip, name
		   FROM ip_addresses
		   ORDER BY name, ip";
$result_ip = mysql_query($sql_ip,$connection) or die(mysql_error());
echo "<select name=\"new_ip_id\">";
while ($row_ip = mysql_fetch_object($result_ip)) { ?>

	<option value="<?=$row_ip->id?>"<?php if ($row_ip->id == $_SESSION['default_ip_address_ssl']) echo " selected";?>><?=$row_ip->name?> (<?=$row_ip->ip?>)</option><?php

}
echo "</select>";
?>
<BR><BR>
<strong>Category</strong><BR><BR>
<?php
$sql_cat = "SELECT id, name
			FROM categories
			ORDER BY name";
$result_cat = mysql_query($sql_cat,$connection) or die(mysql_error());
echo "<select name=\"new_cat_id\">";
while ($row_cat = mysql_fetch_object($result_cat)) { ?>

	<option value="<?=$row_cat->id?>"<?php if ($row_cat->id == $_SESSION['default_category_ssl']) echo " selected";?>><?=$row_cat->name?></option><?php

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
<BR><BR>
<?php
$sql = "SELECT field_name
		FROM ssl_cert_fields
		ORDER BY type_id, name";
$result = mysql_query($sql,$connection);

if (mysql_num_rows($result) > 0) { ?>

	<BR><font class="subheadline">Custom Fields</font><BR><BR><?php

	$count = 0;
	
	while ($row = mysql_fetch_object($result)) {
		
		$field_array[$count] = $row->field_name;
		$count++;
	
	}
	
	foreach($field_array as $field) {
		
		$sql = "SELECT sf.name, sf.field_name, sf.type_id, sf.description
				FROM ssl_cert_fields AS sf, custom_field_types AS cft
				WHERE sf.type_id = cft.id
				  AND sf.field_name = '" . $field . "'";
		$result = mysql_query($sql,$connection);
	
		while ($row = mysql_fetch_object($result)) {
			
			if ($row->type_id == "1") { // Check Box ?>

                <input type="checkbox" name="new_<?=$row->field_name?>" value="1"<?php if (${'new_' . $field} == "1") echo " checked"; ?>>
                &nbsp;<strong><?=$row->name?></strong><BR><?php
				
				if ($row->description != "") {
					
					echo $row->description . "<BR><BR>";
					
				} else {
					
					echo "<BR>";
					
				}
	
			} elseif ($row->type_id == "2") { // Text ?>

				<strong><?=$row->name?> (255)</strong><?php

				if ($row->description != "") {
					
					echo "<BR>" . $row->description . "<BR><BR>";
					
				} else {
					
					echo "<BR><BR>";
					
				} ?>
                <input type="text" name="new_<?=$row->field_name?>" size="50" maxlength="255" value="<?=${'new_' . $row->field_name}?>"><BR><BR><?php

			} elseif ($row->type_id == "3") { // Text Area ?>

				<strong><?=$row->name?></strong><?php

				if ($row->description != "") {
					
					echo "<BR>" . $row->description . "<BR><BR>";
					
				} else {
					
					echo "<BR><BR>";
					
				} ?>
                <textarea name="new_<?=$row->field_name?>" cols="60" rows="5"><?=${'new_' . $row->field_name}?></textarea><BR><BR><?php

			}
			
		}
	
	}
	
	echo "<BR>";

}
?>
<input type="submit" name="button" value="Add This SSL Certificate &raquo;">
</form>
<?php include("../_includes/layout/footer.inc.php"); ?>
</body>
</html>
