<?php
/**
 * /system/display-settings.php
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
require_once(DIR_INC . "classes/Autoloader.php");

spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$error = new DomainMOD\Error();
$time = new DomainMOD\Timestamp();

$page_title = "Display Settings";
$software_section = "system-display-settings";

// Form Variables
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
$new_display_inactive_assets = $_POST['new_display_inactive_assets'];
$new_display_dw_intro_page = $_POST['new_display_dw_intro_page'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_number_of_domains != "" && $new_number_of_ssl_certs != "") {

	$_SESSION['result_message'] .= "Your Display Settings were updated<BR>";

	$sql = "UPDATE user_settings
			SET number_of_domains = '$new_number_of_domains',
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
				display_inactive_assets = '$new_display_inactive_assets',
				display_dw_intro_page = '$new_display_dw_intro_page',
				number_of_ssl_certs = '$new_number_of_ssl_certs',
				update_time = '" . $time->time() . "'
			WHERE user_id = '" . $_SESSION['user_id'] . "'";
	$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

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
    $_SESSION['display_inactive_assets'] = $new_display_inactive_assets;
    $_SESSION['display_dw_intro_page'] = $new_display_dw_intro_page;

	header("Location: index.php");
	exit;

} else {


	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
		if ($new_number_of_domains == "") $_SESSION['result_message'] .= "Enter the default number of domains to display<BR>";
		if ($new_number_of_ssl_certs == "") $_SESSION['result_message'] .= "Enter the default number of SSL certficates to display<BR>";
		
	} else {
		
 		$sql = "SELECT *
				FROM user_settings
				WHERE user_id = '" . $_SESSION['user_id'] . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		while ($row = mysqli_fetch_object($result)) {
			
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
            $new_display_inactive_assets = $row->display_inactive_assets;
            $new_display_dw_intro_page = $row->display_dw_intro_page;

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
<form name="display_settings_form" method="post">
<BR><font class="subheadline">Main Domain Page</font><BR><BR>
<strong>Number of domains per page:</strong> <input name="new_number_of_domains" type="text" size="3" maxlength="5" value="<?php if ($new_number_of_domains != "") echo $new_number_of_domains; ?>">
<BR><BR>
<strong>Columns to display: </strong><BR><BR>
<table class="main_table" cellpadding="0" cellspacing="0">
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
	<tr class="main_table_row_active_nohover">
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
<font class="subheadline">Main SSL Certificate Page</font><BR><BR>
<strong>Number of SSL certificates per page:</strong> <input name="new_number_of_ssl_certs" type="text" size="3" maxlength="5" value="<?php if ($new_number_of_ssl_certs != "") echo $new_number_of_ssl_certs; ?>">
<BR><BR>
<strong>Columns to display: </strong><BR><BR>
<table class="main_table" cellpadding="0" cellspacing="0">
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
	<tr class="main_table_row_active_nohover">
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
<font class="subheadline">Asset Management Pages</font><BR><BR>
<strong>Display inactive Assets?</strong> <input type="checkbox" name="new_display_inactive_assets" value="1"<?php if ($new_display_inactive_assets == "1") echo " checked"; ?>>
<BR><BR><BR>
<font class="subheadline">Data Warehouse</font><BR><BR>
<strong>Display intro page?</strong> <input type="checkbox" name="new_display_dw_intro_page" value="1"<?php if ($new_display_dw_intro_page == "1") echo " checked"; ?>>
<BR><BR><BR>
<input type="submit" name="button" value="Update Display Settings&raquo;">
</form>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
