<?php
/**
 * /edit/domain.php
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

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('Autoloader::classAutoloader');

$error = new DomainMOD\Error();
$system = new DomainMOD\System();
$time = new DomainMOD\Timestamp();
$timestamp = $time->time();

$page_title = "Editing A Domain";
$software_section = "domain-edit";

$did = $_GET['did'];

$del = $_GET['del'];
$really_del = $_GET['really_del'];

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
$new_did = $_POST['new_did'];

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

		$tld = preg_replace("/^((.*?)\.)(.*)$/", "\\3", $new_domain);

		$sql = "SELECT registrar_id, owner_id
				FROM registrar_accounts
				WHERE id = '" . $new_account_id . "'";
		$result = mysqli_query($connection, $sql);
		
		while ($row = mysqli_fetch_object($result)) { $new_registrar_id = $row->registrar_id; $new_owner_id = $row->owner_id; }

		$sql_fee_id = "SELECT id
					   FROM fees
					   WHERE registrar_id = '" . $new_registrar_id . "'
						 AND tld = '" . $tld . "'";
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

        if ($new_privacy == "1") {

            $fee_string = "renewal_fee + privacy_fee + misc_fee";

        } else {

            $fee_string = "renewal_fee + misc_fee";

        }

        $sql = "SELECT (" . $fee_string . ") AS total_cost
                FROM fees
                WHERE registrar_id = '" . $new_registrar_id . "'
                  AND tld = '" . $tld . "'";
        $result = mysqli_query($connection, $sql);

        while ($row = mysqli_fetch_object($result)) { $new_total_cost = $row->total_cost; }

        $sql_update = "UPDATE domains
					   SET owner_id = '" . $new_owner_id . "',
						   registrar_id = '" . $new_registrar_id . "',
						   account_id = '" . $new_account_id . "',
						   domain = '" . mysqli_real_escape_string($connection, $new_domain) . "',
						   tld = '" . $tld . "',
						   expiry_date = '" . $new_expiry_date . "',
						   cat_id = '" . $new_cat_id . "',
						   dns_id = '" . $new_dns_id . "',
						   ip_id = '" . $new_ip_id . "',
						   hosting_id = '" . $new_hosting_id . "',
						   fee_id = '" . $temp_fee_id . "',
						   total_cost = '" . $new_total_cost . "',
						   function = '" . mysqli_real_escape_string($connection, $new_function) . "',
						   notes = '" . mysqli_real_escape_string($connection, $new_notes) . "',
						   privacy = '" . $new_privacy . "',
						   active = '" . $new_active . "',
						   fee_fixed = '" . $temp_fee_fixed . "',
						   update_time = '" . $timestamp . "'
					   WHERE id = '" . $new_did . "'";
		$result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);

		$sql = "SELECT field_name
				FROM domain_fields
				ORDER BY name";
		$result = mysqli_query($connection, $sql);
		
		$count = 0;
		
		while ($row = mysqli_fetch_object($result)) {
			
			$field_array[$count] = $row->field_name;
			$count++;
		
		}
		
		foreach($field_array as $field) {
			
			$full_field = "new_" . $field;
			
			$sql = "UPDATE domain_field_data
					SET `" . $field . "` = '" . mysqli_real_escape_string($connection, ${$full_field}) . "',
						update_time = '" . $timestamp . "'
					WHERE domain_id = '" . $new_did . "'";
			$result = mysqli_query($connection, $sql);
		
		}

		$did = $new_did;
		
		$_SESSION['result_message'] = "Domain <font class=\"highlight\">$new_domain</font> Updated<BR>";

        $_SESSION['result_message'] .= $system->updateSegments($connection);
        $_SESSION['missing_domain_fees'] = $system->checkMissingDomainFees($connection);

		header("Location: domain.php?did=$did");
		exit;

	} else {
	
		if (!$domain->checkDomainFormat($new_domain)) { $_SESSION['result_message'] .= "The domain format is incorrect<BR>"; }
		if (!$date->checkDateFormat($new_expiry_date)) { $_SESSION['result_message'] .= "The expiry date you entered is invalid<BR>"; }

	}

} else {

	$sql = "SELECT d.domain, d.expiry_date, d.cat_id, d.dns_id, d.ip_id, d.hosting_id, d.function, d.notes, d.privacy, d.active, ra.id as account_id
			FROM domains as d, registrar_accounts as ra
			WHERE d.account_id = ra.id
			  AND d.id = '" . $did . "'";
	$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
	
	while ($row = mysqli_fetch_object($result)) {
	
		$new_domain = $row->domain;
		$new_expiry_date = $row->expiry_date;
		$new_cat_id = $row->cat_id;
		$new_dns_id = $row->dns_id;
		$new_ip_id = $row->ip_id;
		$new_hosting_id = $row->hosting_id;
		$new_function = $row->function;
		$new_notes = $row->notes;
		$new_privacy = $row->privacy;
		$new_active = $row->active;
		$new_account_id = $row->account_id;
	
	}

}

if ($del == "1") {

	$sql = "SELECT domain_id
			FROM ssl_certs
			WHERE domain_id = '" . $did . "'";
	$result = mysqli_query($connection, $sql);
	
	while ($row = mysqli_fetch_object($result)) {
		$existing_ssl_certs = 1;
	}
	
	if ($existing_ssl_certs > 0) {

		$_SESSION['result_message'] = "This Domain has SSL Certificates associated with it and cannot be deleted<BR>";

	} else {

		$_SESSION['result_message'] = "Are you sure you want to delete this Domain?<BR><BR><a href=\"domain.php?did=$did&really_del=1\">YES, REALLY DELETE THIS DOMAIN</a><BR>";

	}

}

if ($really_del == "1") {

	$sql = "DELETE FROM domains
			WHERE id = '" . $did . "'";
	$result = mysqli_query($connection, $sql);

	$sql = "DELETE FROM domain_field_data
			WHERE domain_id = '" . $did . "'";
	$result = mysqli_query($connection, $sql);
	
	$_SESSION['result_message'] = "Domain <font class=\"highlight\">$new_domain</font> Deleted<BR>";

	$_SESSION['result_message'] .= $system->updateSegments($connection);
	include(DIR_INC . "auth/login-checks/domain-and-ssl-asset-check.inc.php");
	
	header("Location: ../domains.php");
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
<form name="edit_domain_form" method="post">
<strong>Domain (255)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR>
<input name="new_domain" type="text" size="50" maxlength="255" value="<?php if ($new_domain != "") echo htmlentities($new_domain); ?>">
<BR><BR>
<strong>Function (255)</strong><BR><BR>
<input name="new_function" type="text" size="50" maxlength="255" value="<?php if ($new_function != "") echo htmlentities($new_function); ?>">
<BR><BR>
<strong>Expiry Date (YYYY-MM-DD)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR>
<input name="new_expiry_date" type="text" size="10" maxlength="10" value="<?php if ($new_expiry_date != "") echo $new_expiry_date; ?>">
<BR><BR>
<strong>Registrar Account</strong><BR><BR>
<?php 
$sql_account = "SELECT ra.id, ra.username, o.name AS o_name, r.name AS r_name
				FROM registrar_accounts AS ra, owners AS o, registrars AS r
				WHERE ra.owner_id = o.id
				  AND ra.registrar_id = r.id
				ORDER BY r_name asc, o_name asc, ra.username asc";
$result_account = mysqli_query($connection, $sql_account) or $error->outputOldSqlError($connection);
echo "<select name=\"new_account_id\">";
while ($row_account = mysqli_fetch_object($result_account)) { ?>

	<option value="<?php echo $row_account->id; ?>"<?php if ($row_account->id == $new_account_id) echo " selected";?>><?php echo $row_account->r_name; ?>, <?php echo $row_account->o_name; ?> (<?php echo $row_account->username; ?>)</option><?php

}
echo "</select>";
?>
<BR><BR>
<strong>DNS Profile</strong><BR><BR>
<?php
$sql_dns = "SELECT id, name
			FROM dns
			ORDER BY name asc";
$result_dns = mysqli_query($connection, $sql_dns) or $error->outputOldSqlError($connection);
echo "<select name=\"new_dns_id\">";
while ($row_dns = mysqli_fetch_object($result_dns)) { ?>

	<option value="<?php echo $row_dns->id; ?>"<?php if ($row_dns->id == $new_dns_id) echo " selected";?>><?php echo $row_dns->name; ?></option><?php

}
echo "</select>";
?>
<BR><BR>
<strong>IP Address</strong><BR><BR>
<?php
$sql_ip = "SELECT id, name, ip
		   FROM ip_addresses
		   ORDER BY name asc, ip asc";
$result_ip = mysqli_query($connection, $sql_ip) or $error->outputOldSqlError($connection);
echo "<select name=\"new_ip_id\">";

while ($row_ip = mysqli_fetch_object($result_ip)) { ?>

	<option value="<?php echo $row_ip->id; ?>"<?php if ($row_ip->id == $new_ip_id) echo " selected";?>><?php echo $row_ip->name; ?> (<?php echo $row_ip->ip; ?>)</option><?php

}
echo "</select>";
?>
<BR><BR>
<strong>Web Hosting Provider</strong><BR><BR>
<?php
$sql_hosting = "SELECT id, name
				FROM hosting
				ORDER BY name asc";
$result_hosting = mysqli_query($connection, $sql_hosting) or $error->outputOldSqlError($connection);
echo "<select name=\"new_hosting_id\">";
while ($row_hosting = mysqli_fetch_object($result_hosting)) { ?>

	<option value="<?php echo $row_hosting->id; ?>"<?php if ($row_hosting->id == $new_hosting_id) echo " selected";?>><?php echo $row_hosting->name; ?></option><?php

}
echo "</select>";
?>
<BR><BR>
<strong>Category</strong><BR><BR>
<?php
$sql_cat = "SELECT id, name
			FROM categories
			ORDER BY name asc";
$result_cat = mysqli_query($connection, $sql_cat) or $error->outputOldSqlError($connection);
echo "<select name=\"new_cat_id\">";
while ($row_cat = mysqli_fetch_object($result_cat)) { ?>

	<option value="<?php echo $row_cat->id; ?>"<?php if ($row_cat->id == $new_cat_id) echo " selected";?>><?php echo $row_cat->name; ?></option><?php

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
<strong>Notes</strong><?php if ($new_notes != "") { ?> [<a target="_blank" href="domain-notes.php?did=<?php echo $did; ?>">view full notes</a>]<?php } ?><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?></textarea>
<input type="hidden" name="new_did" value="<?php echo $did; ?>">
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
			
			$sql_data = "SELECT " . $row->field_name . " 
						 FROM domain_field_data
						 WHERE domain_id = '" . $did . "'";
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
<input type="submit" name="button" value="Update This Domain &raquo;">
</form>

<?php
$sql_accounts = "SELECT id
				 FROM dw_accounts
				 WHERE domain = '" . mysqli_real_escape_string($connection, $new_domain) . "'";
$result_accounts = mysqli_query($connection, $sql_accounts);

if ($result_accounts === FALSE || mysqli_num_rows($result_accounts) <= 0) {

    $no_results_accounts = 1;

}

$sql_dns_zones = "SELECT id
				  FROM dw_dns_zones
				  WHERE domain = '" . mysqli_real_escape_string($connection, $new_domain) . "'";
$result_dns_zones = mysqli_query($connection, $sql_dns_zones);

if ($result_dns_zones === FALSE || mysqli_num_rows($result_dns_zones) <= 0) {

    $no_results_dns_zones = 1;

}

if ($no_results_accounts !== 1 || $no_results_dns_zones !== 1) { ?>

    <BR><BR><font class="subheadline">Data Warehouse Information for <?php echo $new_domain; ?></font><?php

}

if ($no_results_accounts === 1) {

    // No matching DW accounts, or the query failed

} else { ?>

    <BR><BR><strong>Accounts</strong><?php

	$sql_dw_account_temp = "SELECT a.*, s.id AS dw_server_id, s.name AS dw_server_name, s.host AS dw_server_host
							FROM dw_accounts AS a, dw_servers AS s
							WHERE a.server_id = s.id
							  AND a.domain = '" . $new_domain . "'
							ORDER BY s.name, a.unix_startdate DESC";
	$result_dw_account_temp = mysqli_query($connection, $sql_dw_account_temp) or $error->outputOldSqlError($connection);

    $from_main_dw_account_page = 0;

    include(DIR_INC . "dw/display-account.inc.php");

}

if ($no_results_dns_zones === 1) {

    // No matching DW DNS zones or the query failed

} else { ?>

    <BR><BR><strong>DNS Zones & Records</strong><BR><BR><?php
	$sql_dw_dns_zone_temp = "SELECT z.*, s.id AS dw_server_id, s.name AS dw_server_name, s.host AS dw_server_host
							 FROM dw_dns_zones AS z, dw_servers AS s
							 WHERE z.server_id = s.id
							   AND z.domain = '" . $new_domain . "'
							 ORDER BY s.name, z.zonefile, z.domain";
	$result_dw_dns_zone_temp = mysqli_query($connection, $sql_dw_dns_zone_temp) or $error->outputOldSqlError($connection);

    $from_main_dw_dns_zone_page = 0;

    include(DIR_INC . "dw/display-dns-zone.inc.php");

}
?>
<BR><BR><a href="domain.php?did=<?php echo $did; ?>&del=1">DELETE THIS DOMAIN</a>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
