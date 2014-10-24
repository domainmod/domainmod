<?php
// /add/domain.php
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
include("../_includes/system/functions/check-domain-format.inc.php");
include("../_includes/system/functions/check-date-format.inc.php");

$page_title = "Adding A New Domain";
$software_section = "domain-add";

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

// Custom Fields
$sql = "SELECT field_name
		FROM domain_fields
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

	if (CheckDateFormat($new_expiry_date) && CheckDomainFormat($new_domain) && $new_cat_id != "" && $new_dns_id != "" && $new_ip_id != "" && $new_hosting_id != "" && $new_account_id != "" && $new_cat_id != "0" && $new_dns_id != "0" && $new_ip_id != "0" && $new_hosting_id != "0" && $new_account_id != "0") {
		
		$sql = "SELECT domain
				FROM domains
				WHERE domain = '" . $new_domain . "'";
		$result = mysql_query($sql,$connection);
		
		if (mysql_num_rows($result) == 0) {

			$tld = preg_replace("/^((.*?)\.)(.*)$/", "\\3", $new_domain);
			
			$sql = "SELECT registrar_id, owner_id
					FROM registrar_accounts
					WHERE id = '" . $new_account_id . "'";
			$result = mysql_query($sql,$connection);
			
			while ($row = mysql_fetch_object($result)) { $new_registrar_id = $row->registrar_id; $new_owner_id = $row->owner_id; }

			if ($new_privacy == "1") {

                $fee_string = "renewal_fee + privacy_fee + misc_fee";

            } else {

                $fee_string = "renewal_fee + misc_fee";

            }

            $sql = "SELECT id, (" . $fee_string . ") AS total_cost
                    FROM fees
                    WHERE registrar_id = '" . $new_registrar_id . "'
                      AND tld = '" . $tld . "'";
            $result = mysql_query($sql,$connection);

            while ($row = mysql_fetch_object($result)) { $new_fee_id = $row->id; $new_total_cost = $row->total_cost; }
	
			$sql = "INSERT INTO domains
					(owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, dns_id, ip_id, hosting_id, fee_id, total_cost, function, notes, privacy, active, insert_time) VALUES
					('" . $new_owner_id . "', '" . $new_registrar_id . "', '" . $new_account_id . "', '" . mysql_real_escape_string($new_domain) . "', '" . $tld . "', '" . $new_expiry_date . "', '" . $new_cat_id . "', '" . $new_dns_id . "', '" . $new_ip_id . "', '" . $new_hosting_id . "', '" . $new_fee_id . "', '" . $new_total_cost . "', '" . mysql_real_escape_string($new_function) . "', '" . mysql_real_escape_string($new_notes) . "', '" . $new_privacy . "', '" . $new_active . "', '" . $current_timestamp . "')";
			$result = mysql_query($sql,$connection) or die(mysql_error());
			
			$sql = "SELECT id
					FROM domains
					WHERE domain = '" . $new_domain . "'
					  AND insert_time = '" . $current_timestamp . "'";
			$result = mysql_query($sql,$connection);
			while ($row = mysql_fetch_object($result)) { $temp_domain_id = $row->id; }

			$sql = "INSERT INTO domain_field_data
					(domain_id, insert_time) VALUES 
					('" . $temp_domain_id . "', '" . $current_timestamp . "')";
			$result = mysql_query($sql,$connection);

			$sql = "SELECT field_name
					FROM domain_fields
					ORDER BY name";
			$result = mysql_query($sql,$connection);
			
			$count = 0;
			
			while ($row = mysql_fetch_object($result)) {
				
				$field_array[$count] = $row->field_name;
				$count++;
			
			}
			
			foreach($field_array as $field) {
				
				$full_field = "new_" . $field;
				
				$sql = "UPDATE domain_field_data
						SET `" . $field . "` = '" . mysql_real_escape_string(${$full_field}) . "' 
						WHERE domain_id = '" . $temp_domain_id . "'";
				$result = mysql_query($sql,$connection);
			
			}

            $_SESSION['result_message'] = "Domain <font class=\"highlight\">$new_domain</font> Added<BR>";

            include("../_includes/system/update-segments.inc.php");
            include("../_includes/system/check-domain-fees.inc.php");
			include("../_includes/auth/login-checks/domain-and-ssl-asset-check.inc.php");

			header("Location: ../domains.php");
			exit;

		} else {

			$_SESSION['result_message'] .= "The domain you entered is already in $software_title<BR>";

		}

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
<?php include("../_includes/layout/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php include("../_includes/layout/header.inc.php"); ?>
<form name="add_domain_form" method="post" action="<?=$PHP_SELF?>">
<strong>Domain (255)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR>
<input name="new_domain" type="text" size="50" maxlength="255" value="<?=$new_domain?>">
<BR><BR>
<strong>Function (255)</strong><BR><BR>
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
				ORDER BY r_name, o_name, ra.username";
$result_account = mysql_query($sql_account,$connection) or die(mysql_error());
echo "<select name=\"new_account_id\">";
while ($row_account = mysql_fetch_object($result_account)) { ?>

	<option value="<?=$row_account->id?>"<?php if ($row_account->id == $_SESSION['default_registrar_account']) echo " selected";?>><?=$row_account->r_name?>, <?=$row_account->o_name?> (<?=$row_account->username?>)</option><?php

}
echo "</select>";
?>
<BR><BR>
<strong>DNS Profile</strong><BR><BR>
<?php
$sql_dns = "SELECT id, name
			FROM dns
			ORDER BY name";
$result_dns = mysql_query($sql_dns,$connection) or die(mysql_error());
echo "<select name=\"new_dns_id\">";
while ($row_dns = mysql_fetch_object($result_dns)) { ?>

	<option value="<?=$row_dns->id?>"<?php if ($row_dns->id == $_SESSION['default_dns']) echo " selected";?>><?=$row_dns->name?></option><?php

}
echo "</select>";
?>
<BR><BR>
<strong>IP Address</strong><BR><BR>
<?php
$sql_ip = "SELECT id, name, ip
		   FROM ip_addresses
		   ORDER BY name, ip";
$result_ip = mysql_query($sql_ip,$connection) or die(mysql_error());
echo "<select name=\"new_ip_id\">";

while ($row_ip = mysql_fetch_object($result_ip)) { ?>

	<option value="<?=$row_ip->id?>"<?php if ($row_ip->id == $_SESSION['default_ip_address_domains']) echo " selected";?>><?=$row_ip->name?> (<?=$row_ip->ip?>)</option><?php

}
echo "</select>";
?>
<BR><BR>
<strong>Web Hosting Provider</strong><BR><BR>
<?php
$sql_hosting = "SELECT id, name
				FROM hosting
				ORDER BY name";
$result_hosting = mysql_query($sql_hosting,$connection) or die(mysql_error());
echo "<select name=\"new_hosting_id\">";
while ($row_hosting = mysql_fetch_object($result_hosting)) { ?>

	<option value="<?=$row_hosting->id?>"<?php if ($row_hosting->id == $_SESSION['default_host']) echo " selected";?>><?=$row_hosting->name?></option><?php

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

	<option value="<?=$row_cat->id?>"<?php if ($row_cat->id == $_SESSION['default_category_domains']) echo " selected";?>><?=$row_cat->name?></option><?php

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
<BR><BR>
<?php
$sql = "SELECT field_name
		FROM domain_fields
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
		
		$sql = "SELECT df.name, df.field_name, df.type_id, df.description
				FROM domain_fields AS df, custom_field_types AS cft
				WHERE df.type_id = cft.id
				  AND df.field_name = '" . $field . "'";
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
<input type="submit" name="button" value="Add This Domain &raquo;">
</form>
<?php include("../_includes/layout/footer.inc.php"); ?>
</body>
</html>
