<?php
/**
 * /index.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2016 Greg Chetcuti <greg@chetcuti.com>
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
include("_includes/start-session.inc.php");
include("_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$error = new DomainMOD\Error();
$login = new DomainMOD\Login();
$maint = new DomainMOD\Maintenance();
$system = new DomainMOD\System();
$time = new DomainMOD\Time();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "config-demo.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "database.inc.php");

$system->loginCheck();

list($installation_mode, $result_message) = $system->installCheck($connection, $web_root);
$_SESSION['s_installation_mode'] = $installation_mode;
$_SESSION['s_result_message'] .= $result_message;

if ($_SESSION['s_installation_mode'] == '1') {

    $page_title = "";
    $software_section = "installation";

} else {

    $page_title = "";
    $software_section = "login";

}

$new_username = $_POST['new_username'];
$new_password = $_POST['new_password'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_username != "" && $new_password != "") {

    $sql = "SELECT id
            FROM users
            WHERE username = '" . $new_username . "'
              AND `password` = password('" . $new_password . "')
              AND active = '1'";
    $result = mysqli_query($connection, $sql);

    if (mysqli_num_rows($result) == 1) {

        $login_succeeded = '1';

    } else {

        $login_succeeded = '0';

    }

    if ($login_succeeded == '1') {

        $result = $login->getUserInfo($connection, $new_username, $new_password);

        while ($row = mysqli_fetch_object($result)) {

            $_SESSION['s_user_id'] = $row->id;
            $_SESSION['s_first_name'] = $row->first_name;
            $_SESSION['s_last_name'] = $row->last_name;
            $_SESSION['s_username'] = $row->username;
            $_SESSION['s_email_address'] = $row->email_address;
            $_SESSION['s_is_new_password'] = $row->new_password;
            $_SESSION['s_number_of_logins'] = $row->number_of_logins;
            if ($row->admin == 1) $_SESSION['s_is_admin'] = 1;
            $_SESSION['s_is_logged_in'] = 1;

        }

        $result_settings = $login->getSystemSettings($connection);

        while ($row_settings = mysqli_fetch_object($result_settings)) {

            $_SESSION['s_system_full_url'] = $row_settings->full_url;
            $_SESSION['s_system_db_version'] = (string) $row_settings->db_version;
            $_SESSION['s_system_upgrade_available'] = $row_settings->upgrade_available;
            $_SESSION['s_system_email_address'] = $row_settings->email_address;
            $_SESSION['s_system_default_category_domains'] = $row_settings->default_category_domains;
            $_SESSION['s_system_default_category_ssl'] = $row_settings->default_category_ssl;
            $_SESSION['s_system_default_dns'] = $row_settings->default_dns;
            $_SESSION['s_system_default_host'] = $row_settings->default_host;
            $_SESSION['s_system_default_ip_address_domains'] = $row_settings->default_ip_address_domains;
            $_SESSION['s_system_default_ip_address_ssl'] = $row_settings->default_ip_address_ssl;
            $_SESSION['s_system_default_owner_domains'] = $row_settings->default_owner_domains;
            $_SESSION['s_system_default_owner_ssl'] = $row_settings->default_owner_ssl;
            $_SESSION['s_system_default_registrar'] = $row_settings->default_registrar;
            $_SESSION['s_system_default_registrar_account'] = $row_settings->default_registrar_account;
            $_SESSION['s_system_default_ssl_provider_account'] = $row_settings->default_ssl_provider_account;
            $_SESSION['s_system_default_ssl_type'] = $row_settings->default_ssl_type;
            $_SESSION['s_system_default_ssl_provider'] = $row_settings->default_ssl_provider;
            $_SESSION['s_system_expiration_email_days'] = $row_settings->expiration_email_days;

        }

        $result_user_settings = $login->getUserSettings($connection, $_SESSION['s_user_id']);

        while ($row_user_settings = mysqli_fetch_object($result_user_settings)) {

            $_SESSION['s_default_currency'] = $row_user_settings->default_currency;
            $_SESSION['s_default_timezone'] = $row_user_settings->default_timezone;
            $_SESSION['s_default_category_domains'] = $row_user_settings->default_category_domains;
            $_SESSION['s_default_category_ssl'] = $row_user_settings->default_category_ssl;
            $_SESSION['s_default_dns'] = $row_user_settings->default_dns;
            $_SESSION['s_default_host'] = $row_user_settings->default_host;
            $_SESSION['s_default_ip_address_domains'] = $row_user_settings->default_ip_address_domains;
            $_SESSION['s_default_ip_address_ssl'] = $row_user_settings->default_ip_address_ssl;
            $_SESSION['s_default_owner_domains'] = $row_user_settings->default_owner_domains;
            $_SESSION['s_default_owner_ssl'] = $row_user_settings->default_owner_ssl;
            $_SESSION['s_default_registrar'] = $row_user_settings->default_registrar;
            $_SESSION['s_default_registrar_account'] = $row_user_settings->default_registrar_account;
            $_SESSION['s_default_ssl_provider_account'] = $row_user_settings->default_ssl_provider_account;
            $_SESSION['s_default_ssl_type'] = $row_user_settings->default_ssl_type;
            $_SESSION['s_default_ssl_provider'] = $row_user_settings->default_ssl_provider;
            $_SESSION['s_number_of_domains'] = $row_user_settings->number_of_domains;
            $_SESSION['s_number_of_ssl_certs'] = $row_user_settings->number_of_ssl_certs;
            $_SESSION['s_display_domain_owner'] = $row_user_settings->display_domain_owner;
            $_SESSION['s_display_domain_registrar'] = $row_user_settings->display_domain_registrar;
            $_SESSION['s_display_domain_account'] = $row_user_settings->display_domain_account;
            $_SESSION['s_display_domain_expiry_date'] = $row_user_settings->display_domain_expiry_date;
            $_SESSION['s_display_domain_category'] = $row_user_settings->display_domain_category;
            $_SESSION['s_display_domain_dns'] = $row_user_settings->display_domain_dns;
            $_SESSION['s_display_domain_host'] = $row_user_settings->display_domain_host;
            $_SESSION['s_display_domain_ip'] = $row_user_settings->display_domain_ip;
            $_SESSION['s_display_domain_host'] = $row_user_settings->display_domain_host;
            $_SESSION['s_display_domain_tld'] = $row_user_settings->display_domain_tld;
            $_SESSION['s_display_domain_fee'] = $row_user_settings->display_domain_fee;
            $_SESSION['s_display_ssl_owner'] = $row_user_settings->display_ssl_owner;
            $_SESSION['s_display_ssl_provider'] = $row_user_settings->display_ssl_provider;
            $_SESSION['s_display_ssl_account'] = $row_user_settings->display_ssl_account;
            $_SESSION['s_display_ssl_domain'] = $row_user_settings->display_ssl_domain;
            $_SESSION['s_display_ssl_type'] = $row_user_settings->display_ssl_type;
            $_SESSION['s_display_ssl_ip'] = $row_user_settings->display_ssl_ip;
            $_SESSION['s_display_ssl_category'] = $row_user_settings->display_ssl_category;
            $_SESSION['s_display_ssl_expiry_date'] = $row_user_settings->display_ssl_expiry_date;
            $_SESSION['s_display_ssl_fee'] = $row_user_settings->display_ssl_fee;
            $_SESSION['s_display_inactive_assets'] = $row_user_settings->display_inactive_assets;
            $_SESSION['s_display_dw_intro_page'] = $row_user_settings->display_dw_intro_page;

        }

        $result_currencies = $login->getCurrencyInfo($connection, $_SESSION['s_default_currency']);

        while ($row_currencies = mysqli_fetch_object($result_currencies)) {

            $_SESSION['s_default_currency_name'] = $row_currencies->name;
            $_SESSION['s_default_currency_symbol'] = $row_currencies->symbol;
            $_SESSION['s_default_currency_symbol_order'] = $row_currencies->symbol_order;
            $_SESSION['s_default_currency_symbol_space'] = $row_currencies->symbol_space;

        }

        header("Location: checks.php");
        exit;

    } else {

        $_SESSION['s_result_message'] = "Login Failed<BR>";

    }

} else {


    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        if ($new_username == "" && $new_password == "") {

            $_SESSION['s_result_message'] .= "Enter your username & password<BR>";

        } elseif ($new_username == "" || $new_password == "") {

            if ($new_username == "") $_SESSION['s_result_message'] .= "Enter your username<BR>";
            if ($new_password == "") $_SESSION['s_result_message'] .= "Enter your password<BR>";

        }
    }

}
$new_password = "";
?>
<?php include(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <?php
    if ($page_title != "") { ?>
        <title><?php echo $system->pageTitle($software_title, $page_title); ?></title><?php
    } else { ?>
        <title><?php echo $software_title; ?></title><?php
    } ?>
    <?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<?php
if ($new_username == "") { ?>
<body onLoad="document.forms[0].elements[0].focus()"><?php
} else { ?>
<body onLoad="document.forms[0].elements[1].focus()"><?php
} ?>
<?php include(DIR_INC . "layout/header-login.inc.php"); ?>
<?php
if ($_SESSION['s_installation_mode'] == '0') { ?>

    <BR>
    <form name="login_form" method="post">
    <?php if ($demo_install == "1") { ?>
        <div align="center"><strong>Demo Username & Password:</strong> "demo"</div><BR><BR><?php } ?>
    <div class="login_form">
        <strong>Username:</strong>&nbsp;
        <input name="new_username" type="text" value="<?php echo $new_username; ?>" size="20" maxlength="20"><BR><BR>
        &nbsp;<strong>Password:</strong>&nbsp;
        <input name="new_password" type="password" id="new_password" size="20" maxlength="255"><br>
    </div>
    <div class="login_form">
        <BR><BR>
        <input type="submit" name="button" value="Manage Your Domains &raquo;">
        <?php if ($demo_install != "1") { ?>

            <BR><BR><a class="invisiblelink" href="reset.php">Forgot your Password?</a><BR>

        <?php } ?>

    </div>
    </form><?php
} ?>
<?php include(DIR_INC . "layout/footer-login.inc.php"); ?>
</body>
</html>
