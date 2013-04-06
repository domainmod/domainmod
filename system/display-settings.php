<?php
// display-settings.php
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
session_start();

include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");
include("../_includes/auth/auth-check.inc.php");

$page_title = "Edit Display Settings";
$software_section = "system";

// Form Variables
$new_number_of_domains = $_POST['new_number_of_domains'];
$new_number_of_ssl_certs = $_POST['new_number_of_ssl_certs'];
$new_display_domain_owner = $_POST['new_display_domain_owner'];
$new_display_domain_registrar = $_POST['new_display_domain_registrar'];
$new_display_domain_account = $_POST['new_display_domain_account'];
$new_display_domain_category = $_POST['new_display_domain_category'];
$new_display_domain_expiry_date = $_POST['new_display_domain_expiry_date'];
$new_display_domain_dns = $_POST['new_display_domain_dns'];
$new_display_domain_ip = $_POST['new_display_domain_ip'];
$new_display_domain_tld = $_POST['new_display_domain_tld'];
$new_display_ssl_owner = $_POST['new_display_ssl_owner'];
$new_display_ssl_provider = $_POST['new_display_ssl_provider'];
$new_display_ssl_account = $_POST['new_display_ssl_account'];
$new_display_ssl_domain = $_POST['new_display_ssl_domain'];
$new_display_ssl_type = $_POST['new_display_ssl_type'];
$new_display_ssl_expiry_date = $_POST['new_display_ssl_expiry_date'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_number_of_domains != "" && $new_number_of_ssl_certs != "") {

	$sql = "UPDATE user_settings
			SET number_of_domains = '$new_number_of_domains',
				display_domain_owner = '$new_display_domain_owner',
				display_domain_registrar = '$new_display_domain_registrar',
				display_domain_account = '$new_display_domain_account',
				display_domain_category = '$new_display_domain_category',
				display_domain_expiry_date = '$new_display_domain_expiry_date',
				display_domain_dns = '$new_display_domain_dns',
				display_domain_ip = '$new_display_domain_ip',
				display_domain_tld = '$new_display_domain_tld',
				display_ssl_owner = '$new_display_ssl_owner',
				display_ssl_provider = '$new_display_ssl_provider',
				display_ssl_account = '$new_display_ssl_account',
				display_ssl_domain = '$new_display_ssl_domain',
				display_ssl_type = '$new_display_ssl_type',
				display_ssl_expiry_date = '$new_display_ssl_expiry_date',
				number_of_ssl_certs = '$new_number_of_ssl_certs'
			WHERE user_id = '" . $_SESSION['session_user_id'] . "'";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$_SESSION['session_number_of_domains'] = $new_number_of_domains;
	$_SESSION['session_number_of_ssl_certs'] = $new_number_of_ssl_certs;
	$_SESSION['session_display_domain_owner'] = $new_display_domain_owner;
	$_SESSION['session_display_domain_registrar'] = $new_display_domain_registrar;
	$_SESSION['session_display_domain_account'] = $new_display_domain_account;
	$_SESSION['session_display_domain_category'] = $new_display_domain_category;
	$_SESSION['session_display_domain_expiry_date'] = $new_display_domain_expiry_date;
	$_SESSION['session_display_domain_dns'] = $new_display_domain_dns;
	$_SESSION['session_display_domain_ip'] = $new_display_domain_ip;
	$_SESSION['session_display_domain_tld'] = $new_display_domain_tld;
	$_SESSION['session_display_ssl_owner'] = $new_display_ssl_owner;
	$_SESSION['session_display_ssl_provider'] = $new_display_ssl_provider;
	$_SESSION['session_display_ssl_account'] = $new_display_ssl_account;
	$_SESSION['session_display_ssl_domain'] = $new_display_ssl_domain;
	$_SESSION['session_display_ssl_type'] = $new_display_ssl_type;
	$_SESSION['session_display_ssl_expiry_date'] = $new_display_ssl_expiry_date;

	$_SESSION['session_result_message'] .= "The Display Settings were updated<BR>";
	
	header("Location: index.php");
	exit;

} else {


	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
		if ($new_number_of_domains == "") $_SESSION['session_result_message'] .= "Enter the default number of domains to display<BR>";
		if ($new_number_of_ssl_certs == "") $_SESSION['session_result_message'] .= "Enter the default number of SSL certficates to display<BR>";
		
	} else {
		
 		$sql = "SELECT number_of_domains, number_of_ssl_certs, display_domain_owner, display_domain_registrar, display_domain_account, display_domain_account, display_domain_expiry_date, display_domain_category, display_domain_dns, display_domain_ip, display_domain_tld, display_ssl_owner, display_ssl_provider, display_ssl_account, display_ssl_account, display_ssl_domain, display_ssl_type, display_ssl_expiry_date
				FROM user_settings
				WHERE user_id = '" . $_SESSION['session_user_id'] . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		while ($row = mysql_fetch_object($result)) {
			
			$new_number_of_domains = $row->number_of_domains;
			$new_number_of_ssl_certs = $row->number_of_ssl_certs;
			$new_display_domain_owner = $row->display_domain_owner;
			$new_display_domain_registrar = $row->display_domain_registrar;
			$new_display_domain_account = $row->display_domain_account;
			$new_display_domain_category = $row->display_domain_category;
			$new_display_domain_expiry_date = $row->display_domain_expiry_date;
			$new_display_domain_dns = $row->display_domain_dns;
			$new_display_domain_ip = $row->display_domain_ip;
			$new_display_domain_tld = $row->display_domain_tld;
			$new_display_ssl_owner = $row->display_ssl_owner;
			$new_display_ssl_provider = $row->display_ssl_provider;
			$new_display_ssl_account = $row->display_ssl_account;
			$new_display_ssl_domain = $row->display_ssl_domain;
			$new_display_ssl_type = $row->display_ssl_type;
			$new_display_ssl_expiry_date = $row->display_ssl_expiry_date;

		}

	}
}
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../_includes/header.inc.php"); ?>
<form name="display_settings_form" method="post" action="<?=$PHP_SELF?>">
<BR>
<font class="headline">Main Domain Page</font><BR><BR>
<strong>Number of domains per page:</strong> <input name="new_number_of_domains" type="text" size="3" maxlength="5" value="<?php if ($new_number_of_domains != "") echo $new_number_of_domains; ?>">
<BR><BR>
<table class="main_table">
	<tr class="main_table_row_heading_active">
    	<td class="main_table_cell_heading_active"><strong>Columns to display: </strong></td>
    	<td class="main_table_cell_heading_active">Expiry Date</td>
    	<td class="main_table_cell_heading_active">TLD</td>
    	<td class="main_table_cell_heading_active">IP Address</td>
    	<td class="main_table_cell_heading_active">DNS Profile</td>
    	<td class="main_table_cell_heading_active">Category</td>
    	<td class="main_table_cell_heading_active">Owner</td>
    	<td class="main_table_cell_heading_active">Registrar</td>
    	<td class="main_table_cell_heading_active">Account</td>
    </tr>
	<tr class="main_table_row_active">
    	<td class="main_table_cell_active_centered"></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_domain_expiry_date" value="1"<?php if ($new_display_domain_expiry_date == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_domain_tld" value="1"<?php if ($new_display_domain_tld == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_domain_ip" value="1"<?php if ($new_display_domain_ip == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_domain_dns" value="1"<?php if ($new_display_domain_dns == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_domain_category" value="1"<?php if ($new_display_domain_category == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_domain_owner" value="1"<?php if ($new_display_domain_owner == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_domain_registrar" value="1"<?php if ($new_display_domain_registrar == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_domain_account" value="1"<?php if ($new_display_domain_account == "1") echo " checked"; ?>></td>
    </tr>
</table>        
<BR><BR>
<font class="headline">Main SSL Certificate Page</font><BR><BR>
<strong>Number of SSL certificates per page:</strong> <input name="new_number_of_ssl_certs" type="text" size="3" maxlength="5" value="<?php if ($new_number_of_ssl_certs != "") echo $new_number_of_ssl_certs; ?>">
<BR><BR>
<table class="main_table">
	<tr class="main_table_row_heading_active">
    	<td class="main_table_cell_heading_active"><strong>Columns to display: </strong></td>
    	<td class="main_table_cell_heading_active">Expiry Date</td>
    	<td class="main_table_cell_heading_active">Domain</td>
    	<td class="main_table_cell_heading_active">SSL Type</td>
    	<td class="main_table_cell_heading_active">Owner</td>
    	<td class="main_table_cell_heading_active">SSL Provider</td>
    	<td class="main_table_cell_heading_active">Account</td>
    </tr>
	<tr class="main_table_row_active">
    	<td class="main_table_cell_heading_active"></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_ssl_expiry_date" value="1"<?php if ($new_display_ssl_expiry_date == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_ssl_domain" value="1"<?php if ($new_display_ssl_domain == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_ssl_type" value="1"<?php if ($new_display_ssl_type == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_ssl_owner" value="1"<?php if ($new_display_ssl_owner == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_ssl_provider" value="1"<?php if ($new_display_ssl_provider == "1") echo " checked"; ?>></td>
    	<td class="main_table_cell_active_centered"><input type="checkbox" name="new_display_ssl_account" value="1"<?php if ($new_display_ssl_account == "1") echo " checked"; ?>></td>
    </tr>
</table>        
<BR><BR>
<input type="submit" name="button" value="Update Display Settings&raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>