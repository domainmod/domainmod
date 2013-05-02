<?php
// /index.php
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
include("_includes/start-session.inc.php");
include("_includes/config.inc.php");

$_SESSION['full_server_path'] = $full_server_path;

include("_includes/database.inc.php");
include("_includes/software.inc.php");
include("_includes/auth/login-check.inc.php");
include("_includes/system/installation-check.inc.php");
include("_includes/timestamps/current-timestamp.inc.php");

if ($_SESSION['installation_mode'] == 1) {
	
	// $page_title = "Installation";
	$software_section = "installation";

} else {

	// $page_title = "Please Login";
	$software_section = "login";

}

$new_username = $_POST['new_username'];
$new_password = $_POST['new_password'];

include("_includes/demo-settings.inc.php"); // $demo_url, $demo_username, $demo_password

if ($_SERVER['HTTP_HOST'] == $demo_url) $demo_install = "1";

if ($demo_install == "1" && !stripos($_SERVER['HTTP_REFERER'], "" . $demo_url . "")) {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$new_username = $demo_username;
	$new_password = $demo_password;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_username != "" && $new_password != "") {
	
	$sql = "SELECT id, first_name, last_name, username, email_address, password, admin, number_of_logins, last_login
			FROM users
			WHERE username = '$new_username'
			  AND password = password('$new_password')
			  AND active = '1'";
	$result = mysql_query($sql,$connection) or die('Login Failed'); 
	
   if (mysql_num_rows($result) == 1) {
	   
	   while ($row = mysql_fetch_object($result)) {

			$_SESSION['user_id'] = $row->id;
			$_SESSION['first_name'] = $row->first_name;
			$_SESSION['last_name'] = $row->last_name;
			$_SESSION['username'] = $row->username;
			$_SESSION['email_address'] = $row->email_address;
			$_SESSION['number_of_logins'] = $row->number_of_logins;
			if ($row->admin == 1) $_SESSION['is_admin'] = 1;
			$_SESSION['is_logged_in'] = 1;
			
			$sql_settings = "SELECT *
							 FROM settings";
			$result_settings = mysql_query($sql_settings,$connection);
			
			while ($row_settings = mysql_fetch_object($result_settings)) {
				
				$_SESSION['system_full_url'] = $row_settings->full_url;
				$_SESSION['system_db_version'] = $row_settings->db_version;
				$_SESSION['system_email_address'] = $row_settings->email_address;
				$_SESSION['system_default_currency'] = $row_settings->default_currency;
				$_SESSION['system_timezone'] = $row_settings->timezone;
				$_SESSION['system_expiration_email_days'] = $row_settings->expiration_email_days;

			}

			$sql_user_settings = "SELECT *
								  FROM user_settings
								  WHERE user_id = '" . $_SESSION['user_id'] . "'";
			$result_user_settings = mysql_query($sql_user_settings,$connection);

			while ($row_user_settings = mysql_fetch_object($result_user_settings)) {
				$_SESSION['default_currency'] = $row_user_settings->default_currency;
				$_SESSION['number_of_domains'] = $row_user_settings->number_of_domains;
				$_SESSION['number_of_ssl_certs'] = $row_user_settings->number_of_ssl_certs;
				$_SESSION['display_domain_owner'] = $row_user_settings->display_domain_owner;
				$_SESSION['display_domain_registrar'] = $row_user_settings->display_domain_registrar;
				$_SESSION['display_domain_account'] = $row_user_settings->display_domain_account;
				$_SESSION['display_domain_expiry_date'] = $row_user_settings->display_domain_expiry_date;
				$_SESSION['display_domain_category'] = $row_user_settings->display_domain_category;
				$_SESSION['display_domain_dns'] = $row_user_settings->display_domain_dns;
				$_SESSION['display_domain_host'] = $row_user_settings->display_domain_host;
				$_SESSION['display_domain_ip'] = $row_user_settings->display_domain_ip;
				$_SESSION['display_domain_host'] = $row_user_settings->display_domain_host;
				$_SESSION['display_domain_tld'] = $row_user_settings->display_domain_tld;
				$_SESSION['display_domain_fee'] = $row_user_settings->display_domain_fee;
				$_SESSION['display_ssl_owner'] = $row_user_settings->display_ssl_owner;
				$_SESSION['display_ssl_provider'] = $row_user_settings->display_ssl_provider;
				$_SESSION['display_ssl_account'] = $row_user_settings->display_ssl_account;
				$_SESSION['display_ssl_domain'] = $row_user_settings->display_ssl_domain;
				$_SESSION['display_ssl_type'] = $row_user_settings->display_ssl_type;
				$_SESSION['display_ssl_ip'] = $row_user_settings->display_ssl_ip;
				$_SESSION['display_ssl_category'] = $row_user_settings->display_ssl_category;
				$_SESSION['display_ssl_expiry_date'] = $row_user_settings->display_ssl_expiry_date;
				$_SESSION['display_ssl_fee'] = $row_user_settings->display_ssl_fee;
			}

			$sql_currencies = "SELECT name, symbol, symbol_order, symbol_space
							   FROM currencies
							   WHERE currency = '" . $_SESSION['default_currency'] . "'";
			$result_currencies = mysql_query($sql_currencies,$connection);

			while ($row_currencies = mysql_fetch_object($result_currencies)) {
				$_SESSION['default_currency_name'] = $row_currencies->name;
				$_SESSION['default_currency_symbol'] = $row_currencies->symbol;
				$_SESSION['default_currency_symbol_order'] = $row_currencies->symbol_order;
				$_SESSION['default_currency_symbol_space'] = $row_currencies->symbol_space;
			}
			
			$current_date = date("Y-m-d", strtotime($current_timestamp));
			$last_login_date = date("Y-m-d", strtotime($row->last_login));

			include("_includes/auth/login-checks/main.inc.php");

			if (($_SESSION['run_update_includes'] == "1" || $last_login_date < $current_date) && $_SESSION['need_domain'] == "0") {
				
				include("_includes/system/update-domain-fees.inc.php");
				include("_includes/system/update-ssl-fees.inc.php");
				include("_includes/system/update-segments.inc.php");
				include("_includes/system/update-tlds.inc.php");

			}
			
			$_SESSION['run_update_includes'] = "";

			if (isset($_SESSION['user_redirect'])) {
			
				$temp_redirect = $_SESSION['user_redirect'];
				unset($_SESSION['user_redirect']);
			
				header("Location: $temp_redirect");
				exit;
			
			} else {
			
				header("Location: domains.php");
				exit;
			
			}

	   }

	} else {

		$_SESSION['result_message'] = "Login Failed<BR>";
	   
   }
	
} else {
	

	if ($_SERVER['REQUEST_METHOD'] == 'POST') {

		if ($new_username == "" && $new_password == "") {

			$_SESSION['result_message'] .= "Enter your username & password<BR>";

		} elseif ($new_username == "" || $new_password == "") {

			if ($new_username == "") $_SESSION['result_message'] .= "Enter your username<BR>";
			if ($new_password == "") $_SESSION['result_message'] .= "Enter your password<BR>";

		}
	}

}
$new_password = "";
?>
<?php include("_includes/doctype.inc.php"); ?>
<html>
<head>
<?php 
if ($page_title != "") { ?>
	<title><?=$software_title?> :: <?=$page_title?></title><?php 
} else { ?>
	<title><?=$software_title?></title><?php 
} ?>
<?php include("_includes/head-tags.inc.php"); ?>
</head><?php 
if ($new_username == "") { ?>
	<body onLoad="document.forms[0].elements[0].focus()";><?php 
} else { ?>
	<body onLoad="document.forms[0].elements[1].focus()";><?php 
} ?>
<?php include("_includes/header-login.inc.php"); ?>
<?php 
if ($_SESSION['installation_mode'] != 1) { ?>

    <BR>
    <form name="login_form" method="post" action="<?=$PHP_SELF?>">
		<?php if ($demo_install == "1") { ?><div align="center"><strong>Demo Username & Password:</strong> "demo"</div><BR><BR><?php } ?>
        <div class="login_form">
            <strong>Username:</strong>&nbsp;
            <input name="new_username" type="text" value="<?php echo $new_username; ?>" size="20" maxlength="20"><BR><BR>
            &nbsp;<strong>Password:</strong>&nbsp;
            <input name="new_password" type="password" id="new_password" size="20" maxlength="20"><br>
        </div>
        <div class="login_form">
            <?php if ($demo_install != "1") { ?><BR><font size="1"><a class="invisiblelink" href="reset-password.php">Forgot your Password?</a></font><BR><?php } ?>
            <BR><BR>
            <input type="submit" name="button" value="Manage Your Domains &raquo;">
        </div>
    </form><?php 
} ?>
<?php include("_includes/footer-login.inc.php"); ?>
</body>
</html>