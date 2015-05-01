<?php
/**
 * /system/defaults.php
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
include(DIR_INC . "config.inc.php");
include(DIR_INC . "database.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "timestamps/current-timestamp.inc.php");
include(DIR_INC . "auth/auth-check.inc.php");
include(DIR_INC . "classes/Error.class.php");

$error = new DomainMOD\Error();

$page_title = "User Defaults";
$software_section = "system-user-defaults";

$new_default_currency = $_POST['new_default_currency'];
$new_default_timezone = $_POST['new_default_timezone'];
$new_default_category_domains = $_POST['new_default_category_domains'];
$new_default_category_ssl = $_POST['new_default_category_ssl'];
$new_default_dns = $_POST['new_default_dns'];
$new_default_host = $_POST['new_default_host'];
$new_default_ip_address_domains = $_POST['new_default_ip_address_domains'];
$new_default_ip_address_ssl = $_POST['new_default_ip_address_ssl'];
$new_default_owner_domains = $_POST['new_default_owner_domains'];
$new_default_owner_ssl = $_POST['new_default_owner_ssl'];
$new_default_registrar = $_POST['new_default_registrar'];
$new_default_registrar_account = $_POST['new_default_registrar_account'];
$new_default_ssl_provider_account = $_POST['new_default_ssl_provider_account'];
$new_default_ssl_type = $_POST['new_default_ssl_type'];
$new_default_ssl_provider = $_POST['new_default_ssl_provider'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	$_SESSION['result_message'] .= "Your Defaults were updated<BR>";

	$sql = "SELECT *
			FROM user_settings
			WHERE user_id = '" . $_SESSION['user_id'] . "'";
	$result = mysqli_query($connection, $sql);
	while ($row = mysqli_fetch_object($result)) { 
	
		$saved_default_currency = $row->default_currency; 
		$saved_default_timezone = $row->default_timezone; 
		$saved_default_category_domains = $row->default_category_domains; 
		$saved_default_category_ssl = $row->default_category_ssl; 
		$saved_default_dns = $row->default_dns; 
		$saved_default_host = $row->default_host; 
		$saved_default_ip_address_domains = $row->default_ip_address_domains; 
		$saved_default_ip_address_ssl = $row->default_ip_address_ssl; 
		$saved_default_owner_domains = $row->default_owner_domains; 
		$saved_default_owner_ssl = $row->default_owner_ssl; 
		$saved_default_registrar = $row->default_registrar; 
		$saved_default_registrar_account = $row->default_registrar_account; 
		$saved_default_ssl_provider_account = $row->default_ssl_provider_account; 
		$saved_default_ssl_type = $row->default_ssl_type; 
		$saved_default_ssl_provider = $row->default_ssl_provider; 

	}
	
	if ($saved_default_currency != $new_default_currency) {
		
		$sql_get_currency_id = "SELECT id
								FROM currencies
								WHERE currency = '" . $new_default_currency . "'";
		$result_get_currency_id = mysqli_query($connection, $sql_get_currency_id);
		while ($row_get_currency_id = mysqli_fetch_object($result_get_currency_id)) { $temp_new_currency_id = $row_get_currency_id->id; }

		$sql_new_currency = "SELECT id
							 FROM currency_conversions
							 WHERE user_id = '" . $_SESSION['user_id'] . "'
							   AND currency_id = '" . $temp_new_currency_id . "'";
		$result_new_currency = mysqli_query($connection, $sql_new_currency);
		
		if (mysqli_num_rows($result_new_currency) == 0) {

			$sql_insert_currency = "INSERT INTO currency_conversions
									(currency_id, user_id, conversion, insert_time, update_time) VALUES 
									('" . $temp_new_currency_id . "', '" . $_SESSION['user_id'] . "', '1', '" . $current_timestamp . "', '" . $current_timestamp . "')";
			$result_insert_currency = mysqli_query($connection, $sql_insert_currency);

		}

		$temp_input_user_id = $_SESSION['user_id'];
		$temp_input_default_currency = $new_default_currency;
		include(DIR_INC . "system/update-conversion-rates.inc.php");

	}

	$sql = "UPDATE user_settings
			SET default_currency = '$new_default_currency',
				default_timezone = '$new_default_timezone',
				default_category_domains = '$new_default_category_domains',
				default_category_ssl = '$new_default_category_ssl',
				default_dns = '$new_default_dns',
				default_host = '$new_default_host',
				default_ip_address_domains = '$new_default_ip_address_domains',
				default_ip_address_ssl = '$new_default_ip_address_ssl',
				default_owner_domains = '$new_default_owner_domains',
				default_owner_ssl = '$new_default_owner_ssl',
				default_registrar = '$new_default_registrar',
				default_registrar_account = '$new_default_registrar_account',
				default_ssl_provider_account = '$new_default_ssl_provider_account',
				default_ssl_type = '$new_default_ssl_type',
				default_ssl_provider = '$new_default_ssl_provider',
				update_time = '$current_timestamp'
			WHERE user_id = '" . $_SESSION['user_id'] . "'";
	$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

	$_SESSION['default_currency'] = $new_default_currency;
	$_SESSION['default_timezone'] = $new_default_timezone;
	$_SESSION['default_category_domains'] = $new_default_category_domains;
	$_SESSION['default_category_ssl'] = $new_default_category_ssl;
	$_SESSION['default_dns'] = $new_default_dns;
	$_SESSION['default_host'] = $new_default_host;
	$_SESSION['default_ip_address_domains'] = $new_default_ip_address_domains;
	$_SESSION['default_ip_address_ssl'] = $new_default_ip_address_ssl;
	$_SESSION['default_owner_domains'] = $new_default_owner_domains;
	$_SESSION['default_owner_ssl'] = $new_default_owner_ssl;
	$_SESSION['default_registrar'] = $new_default_registrar;
	$_SESSION['default_registrar_account'] = $new_default_registrar_account;
	$_SESSION['default_ssl_provider_account'] = $new_default_ssl_provider_account;
	$_SESSION['default_ssl_type'] = $new_default_ssl_type;
	$_SESSION['default_ssl_provider'] = $new_default_ssl_provider;

	$sql_currencies = "SELECT name, symbol, symbol_order, symbol_space
					   FROM currencies
					   WHERE currency = '" . $new_default_currency . "'";
	$result_currencies = mysqli_query($connection, $sql_currencies);
	
	while ($row_currencies = mysqli_fetch_object($result_currencies)) {
		$_SESSION['default_currency_name'] = $row_currencies->name;
		$_SESSION['default_currency_symbol'] = $row_currencies->symbol;
		$_SESSION['default_currency_symbol_order'] = $row_currencies->symbol_order;
		$_SESSION['default_currency_symbol_space'] = $row_currencies->symbol_space;
	}
	
	header("Location: index.php");
	exit;

} else {


	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
		
		// placeholder

	} else {
		
 		$sql = "SELECT *
				FROM user_settings
				WHERE user_id = '" . $_SESSION['user_id'] . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		while ($row = mysqli_fetch_object($result)) {
			
			$new_default_currency = $row->default_currency;
			$new_default_timezone = $row->default_timezone;
			$new_default_category_domains = $row->default_category_domains;
			$new_default_category_ssl = $row->default_category_ssl;
			$new_default_dns = $row->default_dns;
			$new_default_host = $row->default_host;
			$new_default_ip_address_domains = $row->default_ip_address_domains;
			$new_default_ip_address_ssl = $row->default_ip_address_ssl;
			$new_default_owner_domains = $row->default_owner_domains;
			$new_default_owner_ssl = $row->default_owner_ssl;
			$new_default_registrar = $row->default_registrar;
			$new_default_registrar_account = $row->default_registrar_account;
			$new_default_ssl_provider_account = $row->default_ssl_provider_account;
			$new_default_ssl_type = $row->default_ssl_type;
			$new_default_ssl_provider = $row->default_ssl_provider;

		}

	}
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
<form name="default_user_settings_form" method="post">
<BR><font class="subheadline">System Defaults</font><BR><BR>
<strong>Default Currency</strong><BR><BR>
<select name="new_default_currency">
<?php
$sql = "SELECT currency, name, symbol
		FROM currencies
		ORDER BY name";
$result = mysqli_query($connection, $sql);
while ($row = mysqli_fetch_object($result)) {
	?>
	<option value="<?php echo $row->currency; ?>"<?php if ($_SESSION['default_currency'] == $row->currency) echo " selected"; ?>><?php echo $row->name; ?> (<?php echo $row->currency; ?> <?php echo $row->symbol; ?>)</option>
    <?php
}
?>
</select>
<BR><BR>
<strong>Default Timezone</strong><BR><BR>
<select name="new_default_timezone">
<?php
$sql = "SELECT timezone
		FROM timezones
		ORDER BY timezone";
$result = mysqli_query($connection, $sql);
while ($row = mysqli_fetch_object($result)) {
	?>
	<option value="<?php echo $row->timezone; ?>"<?php if ($_SESSION['default_timezone'] == $row->timezone) echo " selected"; ?>><?php echo $row->timezone; ?></option>
    <?php
}
?>
</select>
<BR><BR><BR>
<font class="subheadline">Domain Defaults</font><BR><BR>
<strong>Default Domain Registrar</strong><BR><BR>
<select name="new_default_registrar">
<?php
$sql = "SELECT id, name
		FROM registrars
		ORDER BY name";
$result = mysqli_query($connection, $sql);
while ($row = mysqli_fetch_object($result)) {
	?>
	<option value="<?php echo $row->id; ?>"<?php if ($_SESSION['default_registrar'] == $row->id) echo " selected"; ?>><?php echo $row->name; ?></option>
    <?php
}
?>
</select>
<BR><BR>
<strong>Default Domain Registrar Account</strong><BR><BR>
<select name="new_default_registrar_account">
<?php
$sql = "SELECT ra.id, ra.username, r.name AS r_name, o.name AS o_name
		FROM registrars AS r, registrar_accounts AS ra, owners AS o
		WHERE r.id = ra.registrar_id
		  AND ra.owner_id = o.id
		ORDER BY r.name, o.name, ra.username";
$result = mysqli_query($connection, $sql);
while ($row = mysqli_fetch_object($result)) {
	?>
	<option value="<?php echo $row->id; ?>"<?php if ($_SESSION['default_registrar_account'] == $row->id) echo " selected"; ?>><?php echo $row->r_name; ?> :: <?php echo $row->o_name; ?> :: <?php echo $row->username; ?></option>
    <?php
}
?>
</select>
<BR><BR>
<strong>Default DNS Profile</strong><BR><BR>
<select name="new_default_dns">
<?php
$sql = "SELECT id, name
		FROM dns
		ORDER BY name";
$result = mysqli_query($connection, $sql);
while ($row = mysqli_fetch_object($result)) {
	?>
	<option value="<?php echo $row->id; ?>"<?php if ($_SESSION['default_dns'] == $row->id) echo " selected"; ?>><?php echo $row->name; ?></option>
    <?php
}
?>
</select>
<BR><BR>
<strong>Default Web Hosting Provider</strong><BR><BR>
<select name="new_default_host">
<?php
$sql = "SELECT id, name
		FROM hosting
		ORDER BY name";
$result = mysqli_query($connection, $sql);
while ($row = mysqli_fetch_object($result)) {
	?>
	<option value="<?php echo $row->id; ?>"<?php if ($_SESSION['default_host'] == $row->id) echo " selected"; ?>><?php echo $row->name; ?></option>
    <?php
}
?>
</select>
<BR><BR>
<strong>Default IP Address</strong><BR><BR>
<select name="new_default_ip_address_domains">
<?php
$sql = "SELECT id, ip, name
		FROM ip_addresses
		ORDER BY name";
$result = mysqli_query($connection, $sql);
while ($row = mysqli_fetch_object($result)) {
	?>
	<option value="<?php echo $row->id; ?>"<?php if ($_SESSION['default_ip_address_domains'] == $row->id) echo " selected"; ?>><?php echo $row->name; ?> (<?php echo $row->ip; ?>)</option>
    <?php
}
?>
</select>
<BR><BR>
<strong>Default Category</strong><BR><BR>
<select name="new_default_category_domains">
<?php
$sql = "SELECT id, name
		FROM categories
		ORDER BY name";
$result = mysqli_query($connection, $sql);
while ($row = mysqli_fetch_object($result)) {
	?>
	<option value="<?php echo $row->id; ?>"<?php if ($_SESSION['default_category_domains'] == $row->id) echo " selected"; ?>><?php echo $row->name; ?></option>
    <?php
}
?>
</select>
<BR><BR>
<strong>Default Account Owner</strong><BR><BR>
<select name="new_default_owner_domains">
<?php
$sql = "SELECT id, name
		FROM owners
		ORDER BY name";
$result = mysqli_query($connection, $sql);
while ($row = mysqli_fetch_object($result)) {
	?>
	<option value="<?php echo $row->id; ?>"<?php if ($_SESSION['default_owner_domains'] == $row->id) echo " selected"; ?>><?php echo $row->name; ?></option>
    <?php
}
?>
</select>
<BR><BR><BR>
<font class="subheadline">SSL Defaults</font><BR><BR>
<strong>Default SSL Provider</strong><BR><BR>
<select name="new_default_ssl_provider">
<?php
$sql = "SELECT id, name
		FROM ssl_providers
		ORDER BY name";
$result = mysqli_query($connection, $sql);
while ($row = mysqli_fetch_object($result)) {
	?>
	<option value="<?php echo $row->id; ?>"<?php if ($_SESSION['default_ssl_provider'] == $row->id) echo " selected"; ?>><?php echo $row->name; ?></option>
    <?php
}
?>
</select>
<BR><BR>
<strong>Default SSL Provider Account</strong><BR><BR>
<select name="new_default_ssl_provider_account">
<?php
$sql = "SELECT sslpa.id, sslpa.username, sslp.name AS p_name, o.name AS o_name
		FROM ssl_providers AS sslp, ssl_accounts AS sslpa, owners AS o
		WHERE sslp.id = sslpa.ssl_provider_id
		  AND sslpa.owner_id = o.id
		ORDER BY sslp.name, o.name, sslpa.username";
$result = mysqli_query($connection, $sql);
while ($row = mysqli_fetch_object($result)) {
	?>
	<option value="<?php echo $row->id; ?>"<?php if ($_SESSION['default_ssl_provider_account'] == $row->id) echo " selected"; ?>><?php echo $row->p_name; ?> :: <?php echo $row->o_name; ?> :: <?php echo $row->username; ?></option>
    <?php
}
?>
</select>
<BR><BR>
<strong>Default SSL Type</strong><BR><BR>
<select name="new_default_ssl_type">
<?php
$sql = "SELECT id, type
		FROM ssl_cert_types
		ORDER BY type";
$result = mysqli_query($connection, $sql);
while ($row = mysqli_fetch_object($result)) {
	?>
	<option value="<?php echo $row->id; ?>"<?php if ($_SESSION['default_ssl_type'] == $row->id) echo " selected"; ?>><?php echo $row->type; ?></option>
    <?php
}
?>
</select>
<BR><BR>
<strong>Default IP Address</strong><BR><BR>
<select name="new_default_ip_address_ssl">
<?php
$sql = "SELECT id, ip, name
		FROM ip_addresses
		ORDER BY name";
$result = mysqli_query($connection, $sql);
while ($row = mysqli_fetch_object($result)) {
	?>
	<option value="<?php echo $row->id; ?>"<?php if ($_SESSION['default_ip_address_ssl'] == $row->id) echo " selected"; ?>><?php echo $row->name; ?> (<?php echo $row->ip; ?>)</option>
    <?php
}
?>
</select>
<BR><BR>
<strong>Default Category</strong><BR><BR>
<select name="new_default_category_ssl">
<?php
$sql = "SELECT id, name
		FROM categories
		ORDER BY name";
$result = mysqli_query($connection, $sql);
while ($row = mysqli_fetch_object($result)) {
	?>
	<option value="<?php echo $row->id; ?>"<?php if ($_SESSION['default_category_ssl'] == $row->id) echo " selected"; ?>><?php echo $row->name; ?></option>
    <?php
}
?>
</select>
<BR><BR>
<strong>Default Account Owner</strong><BR><BR>
<select name="new_default_owner_ssl">
<?php
$sql = "SELECT id, name
		FROM owners
		ORDER BY name";
$result = mysqli_query($connection, $sql);
while ($row = mysqli_fetch_object($result)) {
	?>
	<option value="<?php echo $row->id; ?>"<?php if ($_SESSION['default_owner_ssl'] == $row->id) echo " selected"; ?>><?php echo $row->name; ?></option>
    <?php
}
?>
</select>
<BR><BR><BR>
<input type="submit" name="button" value="Update User Defaults &raquo;">
</form>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
