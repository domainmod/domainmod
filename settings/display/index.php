<?php
/**
 * /settings/display/index.php
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
<?php //@formatter:off
include("../../_includes/start-session.inc.php");
include("../../_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$error = new DomainMOD\Error();
$system = new DomainMOD\System();
$form = new DomainMOD\Form();
$time = new DomainMOD\Time();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "settings/settings-display.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();

$new_number_of_domains = $_POST['new_number_of_domains'];
$new_number_of_ssl_certs = $_POST['new_number_of_ssl_certs'];
$domain_column_options = $_POST['domain_column_options'];
$ssl_column_options = $_POST['ssl_column_options'];
$new_display_inactive_assets = $_POST['new_display_inactive_assets'];
$new_display_dw_intro_page = $_POST['new_display_dw_intro_page'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (is_null($domain_column_options)) $domain_column_options = array('');
    if (is_null($ssl_column_options)) $ssl_column_options = array('');

    if (in_array("expiry", $domain_column_options)) { $new_display_domain_expiry_date = '1'; } else { $new_display_domain_expiry_date = '0'; }
    if (in_array("fee", $domain_column_options)) { $new_display_domain_fee = '1'; } else { $new_display_domain_fee = '0'; }
    if (in_array("tld", $domain_column_options)) { $new_display_domain_tld = '1'; } else { $new_display_domain_tld = '0'; }
    if (in_array("registrar", $domain_column_options)) { $new_display_domain_registrar = '1'; } else { $new_display_domain_registrar = '0'; }
    if (in_array("account", $domain_column_options)) { $new_display_domain_account = '1'; } else { $new_display_domain_account = '0'; }
    if (in_array("dns", $domain_column_options)) { $new_display_domain_dns = '1'; } else { $new_display_domain_dns = '0'; }
    if (in_array("ip", $domain_column_options)) { $new_display_domain_ip = '1'; } else { $new_display_domain_ip = '0'; }
    if (in_array("host", $domain_column_options)) { $new_display_domain_host = '1'; } else { $new_display_domain_host = '0'; }
    if (in_array("category", $domain_column_options)) { $new_display_domain_category = '1'; } else { $new_display_domain_category = '0'; }
    if (in_array("owner", $domain_column_options)) { $new_display_domain_owner = '1'; } else { $new_display_domain_owner = '0'; }

    if (in_array("expiry", $ssl_column_options)) { $new_display_ssl_expiry_date = '1'; } else { $new_display_ssl_expiry_date = '0'; }
    if (in_array("fee", $ssl_column_options)) { $new_display_ssl_fee = '1'; } else { $new_display_ssl_fee = '0'; }
    if (in_array("domain", $ssl_column_options)) { $new_display_ssl_domain = '1'; } else { $new_display_ssl_domain = '0'; }
    if (in_array("provider", $ssl_column_options)) { $new_display_ssl_provider = '1'; } else { $new_display_ssl_provider = '0'; }
    if (in_array("account", $ssl_column_options)) { $new_display_ssl_account = '1'; } else { $new_display_ssl_account = '0'; }
    if (in_array("type", $ssl_column_options)) { $new_display_ssl_type = '1'; } else { $new_display_ssl_type = '0'; }
    if (in_array("ip", $ssl_column_options)) { $new_display_ssl_ip = '1'; } else { $new_display_ssl_ip = '0'; }
    if (in_array("category", $ssl_column_options)) { $new_display_ssl_category = '1'; } else { $new_display_ssl_category = '0'; }
    if (in_array("owner", $ssl_column_options)) { $new_display_ssl_owner = '1'; } else { $new_display_ssl_owner = '0'; }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_number_of_domains != "" && $new_number_of_ssl_certs != "") {

    $query = "UPDATE user_settings
              SET number_of_domains = ?,
                  display_domain_owner = ?,
                  display_domain_registrar = ?,
                  display_domain_account = ?,
                  display_domain_category = ?,
                  display_domain_expiry_date = ?,
                  display_domain_dns = ?,
                  display_domain_host = ?,
                  display_domain_ip = ?,
                  display_domain_tld = ?,
                  display_domain_fee = ?,
                  display_ssl_owner = ?,
                  display_ssl_provider = ?,
                  display_ssl_account = ?,
                  display_ssl_domain = ?,
                  display_ssl_type = ?,
                  display_ssl_ip = ?,
                  display_ssl_category = ?,
                  display_ssl_expiry_date = ?,
                  display_ssl_fee = ?,
                  display_inactive_assets = ?,
                  display_dw_intro_page = ?,
                  number_of_ssl_certs = ?,
                  update_time = ?
              WHERE user_id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $timestamp = $time->stamp();
        $q->bind_param('iiiiiiiiiiiiiiiiiiiiiiisi', $new_number_of_domains, $new_display_domain_owner, $new_display_domain_registrar, $new_display_domain_account, $new_display_domain_category, $new_display_domain_expiry_date, $new_display_domain_dns, $new_display_domain_host, $new_display_domain_ip, $new_display_domain_tld, $new_display_domain_fee, $new_display_ssl_owner, $new_display_ssl_provider, $new_display_ssl_account, $new_display_ssl_domain, $new_display_ssl_type, $new_display_ssl_ip, $new_display_ssl_category, $new_display_ssl_expiry_date, $new_display_ssl_fee, $new_display_inactive_assets, $new_display_dw_intro_page, $new_number_of_ssl_certs, $timestamp, $_SESSION['s_user_id']);
        $q->execute();
        $q->close();

    } else $error->outputSqlError($conn, "ERROR");

    $_SESSION['s_number_of_domains'] = $new_number_of_domains;
    $_SESSION['s_number_of_ssl_certs'] = $new_number_of_ssl_certs;
    $_SESSION['s_display_domain_owner'] = $new_display_domain_owner;
    $_SESSION['s_display_domain_registrar'] = $new_display_domain_registrar;
    $_SESSION['s_display_domain_account'] = $new_display_domain_account;
    $_SESSION['s_display_domain_category'] = $new_display_domain_category;
    $_SESSION['s_display_domain_expiry_date'] = $new_display_domain_expiry_date;
    $_SESSION['s_display_domain_dns'] = $new_display_domain_dns;
    $_SESSION['s_display_domain_host'] = $new_display_domain_host;
    $_SESSION['s_display_domain_ip'] = $new_display_domain_ip;
    $_SESSION['s_display_domain_host'] = $new_display_domain_host;
    $_SESSION['s_display_domain_tld'] = $new_display_domain_tld;
    $_SESSION['s_display_domain_fee'] = $new_display_domain_fee;
    $_SESSION['s_display_ssl_owner'] = $new_display_ssl_owner;
    $_SESSION['s_display_ssl_provider'] = $new_display_ssl_provider;
    $_SESSION['s_display_ssl_account'] = $new_display_ssl_account;
    $_SESSION['s_display_ssl_domain'] = $new_display_ssl_domain;
    $_SESSION['s_display_ssl_type'] = $new_display_ssl_type;
    $_SESSION['s_display_ssl_ip'] = $new_display_ssl_ip;
    $_SESSION['s_display_ssl_category'] = $new_display_ssl_category;
    $_SESSION['s_display_ssl_expiry_date'] = $new_display_ssl_expiry_date;
    $_SESSION['s_display_ssl_fee'] = $new_display_ssl_fee;
    $_SESSION['s_display_inactive_assets'] = $new_display_inactive_assets;
    $_SESSION['s_display_dw_intro_page'] = $new_display_dw_intro_page;

    $_SESSION['s_message_success'] .= "Display Settings updated<BR>";

    header("Location: ../index.php");
    exit;

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        if ($new_number_of_domains == "") $_SESSION['s_message_danger'] .= "Enter the default number of domains to display<BR>";
        if ($new_number_of_ssl_certs == "") $_SESSION['s_message_danger'] .= "Enter the default number of SSL certficates to display<BR>";

    } else {

        $query = "SELECT number_of_domains, number_of_ssl_certs, display_domain_owner, display_domain_registrar, display_domain_account, display_domain_category, display_domain_expiry_date, display_domain_dns, display_domain_host, display_domain_ip, display_domain_tld, display_domain_fee, display_ssl_owner, display_ssl_provider, display_ssl_account, display_ssl_domain, display_ssl_type, display_ssl_ip, display_ssl_category, display_ssl_expiry_date, display_ssl_fee, display_inactive_assets, display_dw_intro_page
                  FROM user_settings
                  WHERE user_id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('i', $_SESSION['s_user_id']);
            $q->execute();
            $q->store_result();
            $q->bind_result($t_number_of_domains, $t_number_of_ssl_certs, $t_display_domain_owner, $t_display_domain_registrar, $t_display_domain_account, $t_display_domain_category, $t_display_domain_expiry_date, $t_display_domain_dns, $t_display_domain_host, $t_display_domain_ip, $t_display_domain_tld, $t_display_domain_fee, $t_display_ssl_owner, $t_display_ssl_provider, $t_display_ssl_account, $t_display_ssl_domain, $t_display_ssl_type, $t_display_ssl_ip, $t_display_ssl_category, $t_display_ssl_expiry_date, $t_display_ssl_fee, $t_display_inactive_assets, $t_display_dw_intro_page);

            while ($q->fetch()) {

                $new_number_of_domains = $t_number_of_domains;
                $new_number_of_ssl_certs = $t_number_of_ssl_certs;
                $new_display_domain_owner = $t_display_domain_owner;
                $new_display_domain_registrar = $t_display_domain_registrar;
                $new_display_domain_account = $t_display_domain_account;
                $new_display_domain_category = $t_display_domain_category;
                $new_display_domain_expiry_date = $t_display_domain_expiry_date;
                $new_display_domain_dns = $t_display_domain_dns;
                $new_display_domain_host = $t_display_domain_host;
                $new_display_domain_ip = $t_display_domain_ip;
                $new_display_domain_tld = $t_display_domain_tld;
                $new_display_domain_fee = $t_display_domain_fee;
                $new_display_ssl_owner = $t_display_ssl_owner;
                $new_display_ssl_provider = $t_display_ssl_provider;
                $new_display_ssl_account = $t_display_ssl_account;
                $new_display_ssl_domain = $t_display_ssl_domain;
                $new_display_ssl_type = $t_display_ssl_type;
                $new_display_ssl_ip = $t_display_ssl_ip;
                $new_display_ssl_category = $t_display_ssl_category;
                $new_display_ssl_expiry_date = $t_display_ssl_expiry_date;
                $new_display_ssl_fee = $t_display_ssl_fee;
                $new_display_inactive_assets = $t_display_inactive_assets;
                $new_display_dw_intro_page = $t_display_dw_intro_page;

            }

            $q->close();

        } else $error->outputSqlError($conn, "ERROR");

    }
}
?>
<?php include(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
    <?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php include(DIR_INC . "layout/header.inc.php"); ?>

<?php echo $form->showFormTop(''); ?>

<h3>Main Domain Page</h3><?php

if ($new_number_of_domains != "") {
    $temp_number_of_domains = $new_number_of_domains;
} else {
    $temp_number_of_domains = '';
}
echo $form->showInputText('new_number_of_domains', 'Number of domains per page', '', $temp_number_of_domains, '5', '', '1', '', '');

echo $form->showMultipleSelectTop('domain_column_options', 'Columns to Display', '');
echo $form->showMultipleSelectOption('Expiry Date', 'expiry', $new_display_domain_expiry_date);
echo $form->showMultipleSelectOption('Fee', 'fee', $new_display_domain_fee);
echo $form->showMultipleSelectOption('TLD', 'tld', $new_display_domain_tld);
echo $form->showMultipleSelectOption('Registrar', 'registrar', $new_display_domain_registrar);
echo $form->showMultipleSelectOption('Account', 'account', $new_display_domain_account);
echo $form->showMultipleSelectOption('DNS', 'dns', $new_display_domain_dns);
echo $form->showMultipleSelectOption('IP Address', 'ip', $new_display_domain_ip);
echo $form->showMultipleSelectOption('Web Host', 'host', $new_display_domain_host);
echo $form->showMultipleSelectOption('Category', 'category', $new_display_domain_category);
echo $form->showMultipleSelectOption('Owner', 'owner', $new_display_domain_owner);
echo $form->showMultipleSelectBottom('<BR>');
?>

<h3>Main SSL Certificate Page</h3><?php

if ($new_number_of_ssl_certs != "") {
    $temp_number_of_ssl_certs = $new_number_of_ssl_certs;
} else {
    $temp_number_of_ssl_certs = '';
}
echo $form->showInputText('new_number_of_ssl_certs', 'Number of SSL certificates per page', '', $temp_number_of_ssl_certs, '5', '', '1', '', '');

echo $form->showMultipleSelectTop('ssl_column_options', 'Columns to Display', '');
echo $form->showMultipleSelectOption('Expiry Date', 'expiry', $new_display_ssl_expiry_date);
echo $form->showMultipleSelectOption('Fee', 'fee', $new_display_ssl_fee);
echo $form->showMultipleSelectOption('Domain', 'domain', $new_display_ssl_domain);
echo $form->showMultipleSelectOption('SSL Provider', 'provider', $new_display_ssl_provider);
echo $form->showMultipleSelectOption('Account', 'account', $new_display_ssl_account);
echo $form->showMultipleSelectOption('SSL Type', 'type', $new_display_ssl_type);
echo $form->showMultipleSelectOption('IP Address', 'ip', $new_display_ssl_ip);
echo $form->showMultipleSelectOption('Category', 'category', $new_display_ssl_category);
echo $form->showMultipleSelectOption('Owner', 'owner', $new_display_ssl_owner);
echo $form->showMultipleSelectBottom('<BR>');
?>

<h3>Asset Management Pages</h3>
<?php echo $form->showCheckbox('new_display_inactive_assets', '1', 'Display inactive Assets', '', $new_display_inactive_assets, '', '<BR>'); ?>

<h3>Data Warehouse</h3>
<?php echo $form->showCheckbox('new_display_dw_intro_page', '1', 'Display intro page', '', $new_display_dw_intro_page, '', '<BR>'); ?>

<?php echo $form->showSubmitButton('Update Display Settings', '', ''); ?>

<?php echo $form->showFormBottom(''); ?>
<?php include(DIR_INC . "layout/footer.inc.php"); //@formatter:on ?>
</body>
</html>
