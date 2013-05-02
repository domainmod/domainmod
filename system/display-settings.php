<?php
// /system/system-settings.php
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

$page_title = "Edit System Settings";
$software_section = "system";

// Form Variables
$new_default_currency = $_POST['new_default_currency'];
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
$new_number_of_domains = $_POST['new_number_of_domains'];
$new_number_of_ssl_certs = $_POST['new_number_of_ssl_certs'];
$new_display_domain_owner = $_POST['new_display_domain_owner'];
$new_display_domain_registrar = $_POST['new_display_domain_registrar'];
$new_display_domain_account = $_POST['new_display_domain_account'];
$new_display_domain_category = $_POST['new_display_domain_category'];
$new_display_domain_expiry_date = $_POST['new_display_domain_expiry_date'];
$new_display_domain_dns = $_POST['new_display_domain_dns'];
$new_display_domain_host = $_POST['new_display_domain_host'];
$new_display_domain_ip = $_POST['new_display_domain_ip'];
$new_display_domain_tld = $_POST['new_display_domain_tld'];
$new_display_domain_fee = $_POST['new_display_domain_fee'];
$new_display_ssl_owner = $_POST['new_display_ssl_owner'];
$new_display_ssl_provider = $_POST['new_display_ssl_provider'];
$new_display_ssl_account = $_POST['new_display_ssl_account'];
$new_display_ssl_domain = $_POST['new_display_ssl_domain'];
$new_display_ssl_type = $_POST['new_display_ssl_type'];
$new_display_ssl_ip = $_POST['new_display_ssl_ip'];
$new_display_ssl_category = $_POST['new_display_ssl_category'];
$new_display_ssl_expiry_date = $_POST['new_display_ssl_expiry_date'];
$new_display_ssl_fee = $_POST['new_display_ssl_fee'];

if ($_SESSION['http_referer_set'] != "1") {
	$_SESSION['http_referer'] = $_SERVER['HTTP_REFERER'];
	$_SESSION['http_referer_set'] = "1";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_number_of_domains != "" && $new_number_of_ssl_certs != "") {

	$_SESSION['result_message'] .= "Your System Settings were updated<BR>";

	$sql = "SELECT *
			FROM user_settings
			WHERE user_id = '" . $_SESSION['user_id'] . "'";
	$result = mysql_query($sql,$connection);
	while ($row = mysql_fetch_object($result)) { 
	
		$saved_default_currency = $row->default_currency; 
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

		$temp_input_user_id = $_SESSION['user_id'];
		$temp_input_default_currency = $new_default_currency;
		include("../_includes/system/update-conversion-rates.inc.php");

	}

	$sql = "UPDATE user_settings
			SET default_currency = '$new_default_currency',
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
				number_of_domains = '$new_number_of_domains',
				display_domain_owner = '$new_display_domain_owner',
				display_domain_registrar = '$new_display_domain_registrar',
				display_domain_account = '$new_display_domain_account',
				display_domain_category = '$new_display_domain_category',
				display_domain_expiry_date = '$new_display_domain_expiry_date',
				display_domain_dns = '$new_display_domain_dns',
				display_domain_host = '$new_display_domain_host',
				display_domain_ip = '$new_display_domain_ip',
				display_domain_tld = '$new_display_domain_tld',
				display_domain_fee = '$new_display_domain_fee',
				display_ssl_owner = '$new_display_ssl_owner',
				display_ssl_provider = '$new_display_ssl_provider',
				display_ssl_account = '$new_display_ssl_account',
				display_ssl_domain = '$new_display_ssl_domain',
				display_ssl_type = '$new_display_ssl_type',
				display_ssl_ip = '$new_display_ssl_ip',
				display_ssl_category = '$new_display_ssl_category',
				display_ssl_expiry_date = '$new_display_ssl_expiry_date',
				display_ssl_fee = '$new_display_ssl_fee',
				number_of_ssl_certs = '$new_number_of_ssl_certs',
				update_time = '$current_timestamp'
			WHERE user_id = '" . $_SESSION['user_id'] . "'";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$_SESSION['default_currency'] = $new_default_currency;
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
	$_SESSION['number_of_domains'] = $new_number_of_domains;
	$_SESSION['number_of_ssl_certs'] = $new_number_of_ssl_certs;
	$_SESSION['display_domain_owner'] = $new_display_domain_owner;
	$_SESSION['display_domain_registrar'] = $new_display_domain_registrar;
	$_SESSION['display_domain_account'] = $new_display_domain_account;
	$_SESSION['display_domain_category'] = $new_display_domain_category;
	$_SESSION['display_domain_expiry_date'] = $new_display_domain_expiry_date;
	$_SESSION['display_domain_dns'] = $new_display_domain_dns;
	$_SESSION['display_domain_host'] = $new_display_domain_host;
	$_SESSION['display_domain_ip'] = $new_display_domain_ip;
	$_SESSION['display_domain_host'] = $new_display_domain_host;
	$_SESSION['display_domain_tld'] = $new_display_domain_tld;
	$_SESSION['display_domain_fee'] = $new_display_domain_fee;
	$_SESSION['display_ssl_owner'] = $new_display_ssl_owner;
	$_SESSION['display_ssl_provider'] = $new_display_ssl_provider;
	$_SESSION['display_ssl_account'] = $new_display_ssl_account;
	$_SESSION['display_ssl_domain'] = $new_display_ssl_domain;
	$_SESSION['display_ssl_type'] = $new_display_ssl_type;
	$_SESSION['display_ssl_ip'] = $new_display_ssl_ip;
	$_SESSION['display_ssl_category'] = $new_display_ssl_category;
	$_SESSION['display_ssl_expiry_date'] = $new_display_ssl_expiry_date;
	$_SESSION['display_ssl_fee'] = $new_display_ssl_fee;

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
	
		if ($new_number_of_domains == "") $_SESSION['result_message'] .= "Enter the default number of domains to display<BR>";
		if ($new_number_of_ssl_certs == "") $_SESSION['result_message'] .= "Enter the default number of SSL certficates to display<BR>";
		
	} else {
		
 		$sql = "SELECT *
				FROM user_settings
				WHERE user_id = '" . $_SESSION['user_id'] . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		while ($row = mysql_fetch_object($result)) {
			
			$new_default_currency = $row->default_currency;
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
			$new_number_of_domains = $row->number_of_domains;
			$new_number_of_ssl_certs = $row->number_of_ssl_certs;
			$new_display_domain_owner = $row->display_domain_owner;
			$new_display_domain_registrar = $row->display_domain_registrar;
			$new_display_domain_account = $row->display_domain_account;
			$new_display_domain_category = $row->display_domain_category;
			$new_display_domain_expiry_date = $row->display_domain_expiry_date;
			$new_display_domain_dns = $row->display_domain_dns;
			$new_display_domain_host = $row->display_domain_host;
			$new_display_domain_ip = $row->display_domain_ip;
			$new_display_domain_tld = $row->display_domain_tld;
			$new_display_domain_fee = $row->display_domain_fee;
			$new_display_ssl_owner = $row->display_ssl_owner;
			$new_display_ssl_provider = $row->display_ssl_provider;
			$new_display_ssl_account = $row->display_ssl_account;
			$new_display_ssl_domain = $row->display_ssl_domain;
			$new_display_ssl_type = $row->display_ssl_type;
			$new_display_ssl_ip = $row->display_ssl_ip;
			$new_display_ssl_category = $row->display_ssl_category;
			$new_display_ssl_expiry_date = $row->display_ssl_expiry_date;
			$new_display_ssl_fee = $row->display_ssl_fee;

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
<form name="system_settings_form" method="post" action="<?=$PHP_SELF?>">
<BR>
<font class="headline">Main Domain Page</font><BR><BR>
<strong>Number of domains per page:</strong> <input name="new_number_of_domains" type="text" size="3" maxlength="5" value="<?php if ($new_number_of_domains != "") echo $new_number_of_domains; ?>">
<BR><BR>
<strong>Columns to display: </strong><BR><BR>
<table class="main_table">
	<tr class="main_table_row_heading_active">
    	<td class="main_table_cell_heading_active">Expiry Date</td>
    	<td class="main_table_cell_heading_active">Fee</td>
    	<td class="main_table_cell_heading_active">TLD</td>
    	<td class="main_table_cell_heading_active">Registrar</td>
    	<td class="main_table_cell_heading_active">Account</td>
    	<td class="main_table_cell_heading_active">DNS</td>
    	<td class="main_table_cell_heading_active">IP Address</td>
    	<td class="main_table_cell_heading_active">Web Host</td>
    	<td class="main_table_cell_heading_active">Category</td>
    	<td class="main_table_cell_heading_active">Owner</td>
    </tr>
	<tr class="main_table_row_active">
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_domain_expiry_date" value="1"<?php if ($new_display_domain_expiry_date == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_domain_fee" value="1"<?php if ($new_display_domain_fee == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_domain_tld" value="1"<?php if ($new_display_domain_tld == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_domain_registrar" value="1"<?php if ($new_display_domain_registrar == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_domain_account" value="1"<?php if ($new_display_domain_account == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_domain_dns" value="1"<?php if ($new_display_domain_dns == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_domain_ip" value="1"<?php if ($new_display_domain_ip == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_domain_host" value="1"<?php if ($new_display_domain_host == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_domain_category" value="1"<?php if ($new_display_domain_category == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_domain_owner" value="1"<?php if ($new_display_domain_owner == "1") echo " checked"; ?>></td>
    </tr>
</table>        
<BR><BR>
<font class="headline">Main SSL Certificate Page</font><BR><BR>
<strong>Number of SSL certificates per page:</strong> <input name="new_number_of_ssl_certs" type="text" size="3" maxlength="5" value="<?php if ($new_number_of_ssl_certs != "") echo $new_number_of_ssl_certs; ?>">
<BR><BR>
<strong>Columns to display: </strong><BR><BR>
<table class="main_table">
	<tr class="main_table_row_heading_active">
    	<td class="main_table_cell_heading_active">Expiry Date</td>
    	<td class="main_table_cell_heading_active">Fee</td>
    	<td class="main_table_cell_heading_active">Domain</td>
    	<td class="main_table_cell_heading_active">SSL Provider</td>
    	<td class="main_table_cell_heading_active">Account</td>
    	<td class="main_table_cell_heading_active">SSL Type</td>
    	<td class="main_table_cell_heading_active">IP Address</td>
    	<td class="main_table_cell_heading_active">Category</td>
    	<td class="main_table_cell_heading_active">Owner</td>
    </tr>
	<tr class="main_table_row_active">
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_ssl_expiry_date" value="1"<?php if ($new_display_ssl_expiry_date == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_ssl_fee" value="1"<?php if ($new_display_ssl_fee == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_ssl_domain" value="1"<?php if ($new_display_ssl_domain == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_ssl_provider" value="1"<?php if ($new_display_ssl_provider == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_ssl_account" value="1"<?php if ($new_display_ssl_account == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_ssl_type" value="1"<?php if ($new_display_ssl_type == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_ssl_ip" value="1"<?php if ($new_display_ssl_ip == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_ssl_category" value="1"<?php if ($new_display_ssl_category == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_ssl_owner" value="1"<?php if ($new_display_ssl_owner == "1") echo " checked"; ?>></td>
    </tr>
</table>        
<BR><BR>
<font class="headline">Domain & SSL Defaults</font><BR><BR>
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
<BR><BR>
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
<BR><BR>
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
<input type="submit" name="button" value="Update System Settings&raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>