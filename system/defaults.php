<?php
// /system/defaults.php
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
include("../_includes/timestamps/current-timestamp.inc.php");
include("../_includes/auth/auth-check.inc.php");

$page_title = "Edit User Defaults";
$software_section = "system";

// Form Variables
$new_default_currency = $_POST['new_default_currency'];
$new_default_timezone = $_POST['new_default_timezone'];
$new_default_category = $_POST['new_default_category'];
$new_default_dns = $_POST['new_default_dns'];
$new_default_host = $_POST['new_default_host'];
$new_default_ip_address = $_POST['new_default_ip_address'];
$new_default_owner = $_POST['new_default_owner'];
$new_default_registrar = $_POST['new_default_registrar'];
$new_default_registrar_account = $_POST['new_default_registrar_account'];
$new_default_ssl_provider_account = $_POST['new_default_ssl_provider_account'];
$new_default_ssl_type = $_POST['new_default_ssl_type'];
$new_default_ssl_provider = $_POST['new_default_ssl_provider'];

if ($_SESSION['http_referer_set'] != "1") {
	$_SESSION['http_referer'] = $_SERVER['HTTP_REFERER'];
	$_SESSION['http_referer_set'] = "1";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	$_SESSION['result_message'] .= "Your Defaults were updated<BR>";

	$sql = "SELECT *
			FROM user_settings
			WHERE user_id = '" . $_SESSION['user_id'] . "'";
	$result = mysql_query($sql,$connection);
	while ($row = mysql_fetch_object($result)) { 
	
		$saved_default_currency = $row->default_currency; 
		$saved_default_timezone = $row->default_timezone; 
		$saved_default_category = $row->default_category; 
		$saved_default_dns = $row->default_dns; 
		$saved_default_host = $row->default_host; 
		$saved_default_ip_address = $row->default_ip_address; 
		$saved_default_owner = $row->default_owner; 
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
		$result_get_currency_id = mysql_query($sql_get_currency_id,$connection);
		while ($row_get_currency_id = mysql_fetch_object($result_get_currency_id)) { $temp_new_currency_id = $row_get_currency_id->id; }

		$sql_new_currency = "SELECT id
							 FROM currency_conversions
							 WHERE user_id = '" . $_SESSION['user_id'] . "'
							   AND currency_id = '" . $temp_new_currency_id . "'";
		$result_new_currency = mysql_query($sql_new_currency,$connection);
		
		if (mysql_num_rows($result_new_currency) == 0) {

			$sql_insert_currency = "INSERT INTO currency_conversions
									(currency_id, user_id, conversion, insert_time, update_time) VALUES 
									('" . $temp_new_currency_id . "', '" . $_SESSION['user_id'] . "', '1', '" . $current_timestamp . "', '" . $current_timestamp . "')";
			$result_insert_currency = mysql_query($sql_insert_currency,$connection);

		}

		$temp_input_user_id = $_SESSION['user_id'];
		$temp_input_default_currency = $new_default_currency;
		include("../_includes/system/update-conversion-rates.inc.php");

	}

	$sql = "UPDATE user_settings
			SET default_currency = '$new_default_currency',
				default_timezone = '$new_default_timezone',
				default_category = '$new_default_category',
				default_dns = '$new_default_dns',
				default_host = '$new_default_host',
				default_ip_address = '$new_default_ip_address',
				default_owner = '$new_default_owner',
				default_registrar = '$new_default_registrar',
				default_registrar_account = '$new_default_registrar_account',
				default_ssl_provider_account = '$new_default_ssl_provider_account',
				default_ssl_type = '$new_default_ssl_type',
				default_ssl_provider = '$new_default_ssl_provider',
				update_time = '$current_timestamp'
			WHERE user_id = '" . $_SESSION['user_id'] . "'";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$_SESSION['default_currency'] = $new_default_currency;
	$_SESSION['default_timezone'] = $new_default_timezone;
	$_SESSION['default_category'] = $new_default_category;
	$_SESSION['default_dns'] = $new_default_dns;
	$_SESSION['default_host'] = $new_default_host;
	$_SESSION['default_ip_address'] = $new_default_ip_address;
	$_SESSION['default_owner'] = $new_default_owner;
	$_SESSION['default_registrar'] = $new_default_registrar;
	$_SESSION['default_registrar_account'] = $new_default_registrar_account;
	$_SESSION['default_ssl_provider_account'] = $new_default_ssl_provider_account;
	$_SESSION['default_ssl_type'] = $new_default_ssl_type;
	$_SESSION['default_ssl_provider'] = $new_default_ssl_provider;

	$sql_currencies = "SELECT name, symbol, symbol_order, symbol_space
					   FROM currencies
					   WHERE currency = '" . $new_default_currency . "'";
	$result_currencies = mysql_query($sql_currencies,$connection);
	
	while ($row_currencies = mysql_fetch_object($result_currencies)) {
		$_SESSION['default_currency_name'] = $row_currencies->name;
		$_SESSION['default_currency_symbol'] = $row_currencies->symbol;
		$_SESSION['default_currency_symbol_order'] = $row_currencies->symbol_order;
		$_SESSION['default_currency_symbol_space'] = $row_currencies->symbol_space;
	}
	
	$_SESSION['http_referer_set'] = "";
	header("Location: " . $_SESSION['http_referer']);
	exit;

} else {


	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
		
		// placeholder

	} else {
		
 		$sql = "SELECT *
				FROM user_settings
				WHERE user_id = '" . $_SESSION['user_id'] . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		while ($row = mysql_fetch_object($result)) {
			
			$new_default_currency = $row->default_currency;
			$new_default_timezone = $row->default_timezone;
			$new_default_category = $row->default_category;
			$new_default_dns = $row->default_dns;
			$new_default_host = $row->default_host;
			$new_default_ip_address = $row->default_ip_address;
			$new_default_owner = $row->default_owner;
			$new_default_registrar = $row->default_registrar;
			$new_default_registrar_account = $row->default_registrar_account;
			$new_default_ssl_provider_account = $row->default_ssl_provider_account;
			$new_default_ssl_type = $row->default_ssl_type;
			$new_default_ssl_provider = $row->default_ssl_provider;

		}

	}
}
?>
<?php include("../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../_includes/header.inc.php"); ?>
<form name="default_user_settings_form" method="post" action="<?=$PHP_SELF?>">
<font class="headline">System Defaults</font><BR><BR>
<strong>Default Currency:</strong><BR><BR>
<select name="new_default_currency">
<?php
$sql = "SELECT currency, name, symbol
		FROM currencies
		ORDER BY name";
$result = mysql_query($sql,$connection);
while ($row = mysql_fetch_object($result)) {
	?>
	<option value="<?=$row->currency?>"<?php if ($_SESSION['default_currency'] == $row->currency) echo " selected"; ?>><?=$row->name?> (<?=$row->currency?> <?=$row->symbol?>)</option>
    <?php
}
?>
</select>
<BR><BR>
<strong>Default Timezone:</strong><BR><BR>
<select name="new_default_timezone">
<?php
$sql = "SELECT timezone
		FROM timezones
		ORDER BY timezone";
$result = mysql_query($sql,$connection);
while ($row = mysql_fetch_object($result)) {
	?>
	<option value="<?=$row->timezone?>"<?php if ($_SESSION['default_timezone'] == $row->timezone) echo " selected"; ?>><?=$row->timezone?></option>
    <?php
}
?>
</select>
<BR><BR><BR>
<font class="headline">Domain Defaults</font><BR><BR>
<strong>Default Domain Registrar:</strong><BR><BR>
<select name="new_default_registrar">
<?php
$sql = "SELECT id, name
		FROM registrars
		ORDER BY name";
$result = mysql_query($sql,$connection);
while ($row = mysql_fetch_object($result)) {
	?>
	<option value="<?=$row->id?>"<?php if ($_SESSION['default_registrar'] == $row->id) echo " selected"; ?>><?=$row->name?></option>
    <?php
}
?>
</select>
<BR><BR>
<strong>Default Domain Registrar Account:</strong><BR><BR>
<select name="new_default_registrar_account">
<?php
$sql = "SELECT ra.id, ra.username, r.name AS r_name, o.name AS o_name
		FROM registrars AS r, registrar_accounts AS ra, owners AS o
		WHERE r.id = ra.registrar_id
		  AND ra.owner_id = o.id
		ORDER BY r.name, o.name, ra.username";
$result = mysql_query($sql,$connection);
while ($row = mysql_fetch_object($result)) {
	?>
	<option value="<?=$row->id?>"<?php if ($_SESSION['default_registrar_account'] == $row->id) echo " selected"; ?>><?=$row->r_name?> :: <?=$row->o_name?> :: <?=$row->username?></option>
    <?php
}
?>
</select>
<BR><BR>
<strong>Default DNS Profile:</strong><BR><BR>
<select name="new_default_dns">
<?php
$sql = "SELECT id, name
		FROM dns
		ORDER BY name";
$result = mysql_query($sql,$connection);
while ($row = mysql_fetch_object($result)) {
	?>
	<option value="<?=$row->id?>"<?php if ($_SESSION['default_dns'] == $row->id) echo " selected"; ?>><?=$row->name?></option>
    <?php
}
?>
</select>
<BR><BR>
<strong>Default Web Hosting Provider:</strong><BR><BR>
<select name="new_default_host">
<?php
$sql = "SELECT id, name
		FROM hosting
		ORDER BY name";
$result = mysql_query($sql,$connection);
while ($row = mysql_fetch_object($result)) {
	?>
	<option value="<?=$row->id?>"<?php if ($_SESSION['default_host'] == $row->id) echo " selected"; ?>><?=$row->name?></option>
    <?php
}
?>
</select>
<BR><BR><BR>
<font class="headline">SSL Defaults</font><BR><BR>
<strong>Default SSL Provider:</strong><BR><BR>
<select name="new_default_ssl_provider">
<?php
$sql = "SELECT id, name
		FROM ssl_providers
		ORDER BY name";
$result = mysql_query($sql,$connection);
while ($row = mysql_fetch_object($result)) {
	?>
	<option value="<?=$row->id?>"<?php if ($_SESSION['default_ssl_provider'] == $row->id) echo " selected"; ?>><?=$row->name?></option>
    <?php
}
?>
</select>
<BR><BR>
<strong>Default SSL Provider Account:</strong><BR><BR>
<select name="new_default_ssl_provider_account">
<?php
$sql = "SELECT sslpa.id, sslpa.username, sslp.name AS p_name, o.name AS o_name
		FROM ssl_providers AS sslp, ssl_accounts AS sslpa, owners AS o
		WHERE sslp.id = sslpa.ssl_provider_id
		  AND sslpa.owner_id = o.id
		ORDER BY sslp.name, o.name, sslpa.username";
$result = mysql_query($sql,$connection);
while ($row = mysql_fetch_object($result)) {
	?>
	<option value="<?=$row->id?>"<?php if ($_SESSION['default_ssl_provider_account'] == $row->id) echo " selected"; ?>><?=$row->p_name?> :: <?=$row->o_name?> :: <?=$row->username?></option>
    <?php
}
?>
</select>
<BR><BR>
<strong>Default SSL Type:</strong><BR><BR>
<select name="new_default_ssl_type">
<?php
$sql = "SELECT id, type
		FROM ssl_cert_types
		ORDER BY type";
$result = mysql_query($sql,$connection);
while ($row = mysql_fetch_object($result)) {
	?>
	<option value="<?=$row->id?>"<?php if ($_SESSION['default_ssl_type'] == $row->id) echo " selected"; ?>><?=$row->type?></option>
    <?php
}
?>
</select>
<BR><BR><BR>
<font class="headline">Shared Defaults</font><BR><BR>
<strong>Default Account Owner:</strong><BR><BR>
<select name="new_default_owner">
<?php
$sql = "SELECT id, name
		FROM owners
		ORDER BY name";
$result = mysql_query($sql,$connection);
while ($row = mysql_fetch_object($result)) {
	?>
	<option value="<?=$row->id?>"<?php if ($_SESSION['default_owner'] == $row->id) echo " selected"; ?>><?=$row->name?></option>
    <?php
}
?>
</select>
<BR><BR>
<strong>Default Category:</strong><BR><BR>
<select name="new_default_category">
<?php
$sql = "SELECT id, name
		FROM categories
		ORDER BY name";
$result = mysql_query($sql,$connection);
while ($row = mysql_fetch_object($result)) {
	?>
	<option value="<?=$row->id?>"<?php if ($_SESSION['default_category'] == $row->id) echo " selected"; ?>><?=$row->name?></option>
    <?php
}
?>
</select>
<BR><BR>
<strong>Default IP Address:</strong><BR><BR>
<select name="new_default_ip_address">
<?php
$sql = "SELECT id, ip, name
		FROM ip_addresses
		ORDER BY name";
$result = mysql_query($sql,$connection);
while ($row = mysql_fetch_object($result)) {
	?>
	<option value="<?=$row->id?>"<?php if ($_SESSION['default_ip_address'] == $row->id) echo " selected"; ?>><?=$row->name?> (<?=$row->ip?>)</option>
    <?php
}
?>
</select>
<BR><BR><BR>
<input type="submit" name="button" value="Update User Defaults &raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>