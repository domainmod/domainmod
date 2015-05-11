<?php
/**
 * /add/domain.php
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
include(DIR_INC . "software.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "database.inc.php");
include(DIR_INC . "auth/auth-check.inc.php");
require_once(DIR_INC . "classes/Autoloader.class.php");

spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$error = new DomainMOD\Error();
$time = new DomainMOD\Timestamp();
$timestamp = $time->time();
$timestamp_basic_plus_one_year = $time->timeBasicPlusYears(1);

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
    $domain = new DomainMOD\Domain();

    if ($date->checkDateFormat($new_expiry_date) && $domain->checkDomainFormat($new_domain) && $new_cat_id != "" && $new_dns_id != "" && $new_ip_id != "" && $new_hosting_id != "" && $new_account_id != "" && $new_cat_id != "0" && $new_dns_id != "0" && $new_ip_id != "0" && $new_hosting_id != "0" && $new_account_id != "0") {

        $sql = "SELECT domain
                FROM domains
                WHERE domain = '" . mysqli_real_escape_string($connection, $new_domain) . "'";
        $result = mysqli_query($connection, $sql);

        if (mysqli_num_rows($result) == 0) {

			$tld = preg_replace("/^((.*?)\.)(.*)$/", "\\3", $new_domain);
			
			$sql = "SELECT registrar_id, owner_id
					FROM registrar_accounts
					WHERE id = '" . $new_account_id . "'";
			$result = mysqli_query($connection, $sql);
			
			while ($row = mysqli_fetch_object($result)) { $new_registrar_id = $row->registrar_id; $new_owner_id = $row->owner_id; }

			if ($new_privacy == "1") {

                $fee_string = "renewal_fee + privacy_fee + misc_fee";

            } else {

                $fee_string = "renewal_fee + misc_fee";

            }

            $sql = "SELECT id, (" . $fee_string . ") AS total_cost
                    FROM fees
                    WHERE registrar_id = '" . $new_registrar_id . "'
                      AND tld = '" . $tld . "'";
            $result = mysqli_query($connection, $sql);

            while ($row = mysqli_fetch_object($result)) { $new_fee_id = $row->id; $new_total_cost = $row->total_cost; }
	
			$sql = "INSERT INTO domains
					(owner_id, registrar_id, account_id, domain, tld, expiry_date, cat_id, dns_id, ip_id, hosting_id, fee_id, total_cost, function, notes, privacy, active, insert_time) VALUES
					('" . $new_owner_id . "', '" . $new_registrar_id . "', '" . $new_account_id . "', '" . mysqli_real_escape_string($connection, $new_domain) . "', '" . $tld . "', '" . $new_expiry_date . "', '" . $new_cat_id . "', '" . $new_dns_id . "', '" . $new_ip_id . "', '" . $new_hosting_id . "', '" . $new_fee_id . "', '" . $new_total_cost . "', '" . mysqli_real_escape_string($connection, $new_function) . "', '" . mysqli_real_escape_string($connection, $new_notes) . "', '" . $new_privacy . "', '" . $new_active . "', '" . $timestamp . "')";
			$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
			
			$sql = "SELECT id
					FROM domains
					WHERE domain = '" . $new_domain . "'
					  AND insert_time = '" . $timestamp . "'";
			$result = mysqli_query($connection, $sql);
			while ($row = mysqli_fetch_object($result)) { $temp_domain_id = $row->id; }

			$sql = "INSERT INTO domain_field_data
					(domain_id, insert_time) VALUES 
					('" . $temp_domain_id . "', '" . $timestamp . "')";
			$result = mysqli_query($connection, $sql);

			$sql = "SELECT field_name
					FROM domain_fields
					ORDER BY name";
			$result = mysqli_query($connection, $sql);

            if (mysqli_num_rows($result) > 0) {

                $count = 0;

                while ($row = mysqli_fetch_object($result)) {

                    $field_array[$count] = $row->field_name;
                    $count++;

                }

                foreach ($field_array as $field) {

                    $full_field = "new_" . $field;

                    $sql = "UPDATE domain_field_data
                            SET `" . $field . "` = '" . mysqli_real_escape_string($connection, ${$full_field}) . "'
                            WHERE domain_id = '" . $temp_domain_id . "'";
                    $result = mysqli_query($connection, $sql);

                }

            }

            $_SESSION['result_message'] = "Domain <font class=\"highlight\">$new_domain</font> Added<BR>";

            include(DIR_INC . "system/update-segments.inc.php");
            include(DIR_INC . "system/check-domain-fees.inc.php");
			include(DIR_INC . "auth/login-checks/domain-and-ssl-asset-check.inc.php");

        } else {

			$_SESSION['result_message'] .= "The domain you entered is already in $software_title<BR>";

		}

	} else {
	
		if (!$domain->checkDomainFormat($new_domain)) { $_SESSION['result_message'] .= "The domain format is incorrect<BR>"; }
		if (!$date->checkDateFormat($new_expiry_date)) { $_SESSION['result_message'] .= "The expiry date you entered is invalid<BR>"; }

	}

}
?>
<?php include(DIR_INC . "doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<form name="add_domain_form" method="post">
<strong>Domain (255)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR>
<input name="new_domain" type="text" size="50" maxlength="255" value="<?php echo $new_domain; ?>">
<BR><BR>
<strong>Function (255)</strong><BR><BR>
<input name="new_function" type="text" size="50" maxlength="255" value="<?php echo $new_function; ?>">
<BR><BR>
<strong>Expiry Date (YYYY-MM-DD)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR>
<input name="new_expiry_date" type="text" size="10" maxlength="10" value="<?php if ($new_expiry_date != "") { echo $new_expiry_date; } else { echo $timestamp_basic_plus_one_year; } ?>">
<BR><BR>
<strong>Registrar Account</strong><BR><BR>
<?php 
$sql_account = "SELECT ra.id, ra.username, o.name AS o_name, r.name AS r_name
                FROM registrar_accounts AS ra, owners AS o, registrars AS r
                WHERE ra.owner_id = o.id
                  AND ra.registrar_id = r.id
                ORDER BY r_name, o_name, ra.username";
$result_account = mysqli_query($connection, $sql_account) or $error->outputOldSqlError($connection);
echo "<select name=\"new_account_id\">";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_account_id;

} else {

    $to_compare = $_SESSION['default_registrar_account'];

}

while ($row_account = mysqli_fetch_object($result_account)) { ?>

    <option value="<?php echo $row_account->id; ?>"<?php if ($row_account->id == $to_compare) echo " selected"; ?>><?php echo $row_account->r_name; ?>, <?php echo $row_account->o_name; ?> (<?php echo $row_account->username; ?>)</option><?php

}
echo "</select>";
?>
<BR><BR>
<strong>DNS Profile</strong><BR><BR>
<?php
$sql_dns = "SELECT id, name
			FROM dns
			ORDER BY name";
$result_dns = mysqli_query($connection, $sql_dns) or $error->outputOldSqlError($connection);
echo "<select name=\"new_dns_id\">";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_dns_id;

} else {

    $to_compare = $_SESSION['default_dns'];

}

while ($row_dns = mysqli_fetch_object($result_dns)) { ?>

	<option value="<?php echo $row_dns->id; ?>"<?php if ($row_dns->id == $to_compare) echo " selected";?>><?php echo $row_dns->name; ?></option><?php

}
echo "</select>";
?>
<BR><BR>
<strong>IP Address</strong><BR><BR>
<?php
$sql_ip = "SELECT id, name, ip
		   FROM ip_addresses
		   ORDER BY name, ip";
$result_ip = mysqli_query($connection, $sql_ip) or $error->outputOldSqlError($connection);
echo "<select name=\"new_ip_id\">";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_ip_id;

} else {

    $to_compare = $_SESSION['default_ip_address_domains'];

}

while ($row_ip = mysqli_fetch_object($result_ip)) { ?>

	<option value="<?php echo $row_ip->id; ?>"<?php if ($row_ip->id == $to_compare) echo " selected";?>><?php echo $row_ip->name; ?> (<?php echo $row_ip->ip; ?>)</option><?php

}
echo "</select>";
?>
<BR><BR>
<strong>Web Hosting Provider</strong><BR><BR>
<?php
$sql_hosting = "SELECT id, name
				FROM hosting
				ORDER BY name";
$result_hosting = mysqli_query($connection, $sql_hosting) or $error->outputOldSqlError($connection);
echo "<select name=\"new_hosting_id\">";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_hosting_id;

} else {

    $to_compare = $_SESSION['default_host'];

}

while ($row_hosting = mysqli_fetch_object($result_hosting)) { ?>

	<option value="<?php echo $row_hosting->id; ?>"<?php if ($row_hosting->id == $to_compare) echo " selected";?>><?php echo $row_hosting->name; ?></option><?php

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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $to_compare = $new_cat_id;

} else {

    $to_compare = $_SESSION['default_category_domains'];

}

while ($row_cat = mysqli_fetch_object($result_cat)) { ?>

	<option value="<?php echo $row_cat->id; ?>"<?php if ($row_cat->id == $to_compare) echo " selected";?>><?php echo $row_cat->name; ?></option><?php

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
<textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?></textarea>
<BR><BR>
<?php
$sql = "SELECT field_name
		FROM domain_fields
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
		
		$sql = "SELECT df.name, df.field_name, df.type_id, df.description
				FROM domain_fields AS df, custom_field_types AS cft
				WHERE df.type_id = cft.id
				  AND df.field_name = '" . $field . "'";
		$result = mysqli_query($connection, $sql);
		
		while ($row = mysqli_fetch_object($result)) {

			if ($row->type_id == "1") { // Check Box ?>

                <input type="checkbox" name="new_<?php echo $row->field_name; ?>" value="1"<?php if (${'new_' . $field} == "1") echo " checked"; ?>>
                &nbsp;<strong><?php echo $row->name; ?></strong><BR><?php
				
				if ($row->description != "") {
					
					echo $row->description . "<BR><BR>";
					
				} else {
					
					echo "<BR>";
					
				}
	
			} elseif ($row->type_id == "2") { // Text ?>

				<strong><?php echo $row->name; ?> (255)</strong><?php

				if ($row->description != "") {
					
					echo "<BR>" . $row->description . "<BR><BR>";
					
				} else {
					
					echo "<BR><BR>";
					
				} ?>
                <input type="text" name="new_<?php echo $row->field_name; ?>" size="50" maxlength="255" value="<?php echo ${'new_' . $row->field_name}; ?>"><BR><BR><?php

			} elseif ($row->type_id == "3") { // Text Area ?>

				<strong><?php echo $row->name; ?></strong><?php

				if ($row->description != "") {
					
					echo "<BR>" . $row->description . "<BR><BR>";
					
				} else {
					
					echo "<BR><BR>";
					
				} ?>
                <textarea name="new_<?php echo $row->field_name; ?>" cols="60" rows="5"><?php echo ${'new_' . $row->field_name}; ?></textarea><BR><BR><?php

			}

		}
	
	}
	
	echo "<BR>";
	
}
?>
<input type="submit" name="button" value="Add This Domain &raquo;">
</form>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
