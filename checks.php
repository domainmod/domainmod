<?php
/**
 * /checks.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>
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
require_once('_includes/start-session.inc.php');
require_once('_includes/init.inc.php');

require_once(DIR_ROOT . 'classes/Autoloader.php');
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

require_once(DIR_ROOT . 'vendor/autoload.php');

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$maint = new DomainMOD\Maintenance();
$login = new DomainMOD\Login();
$time = new DomainMOD\Time();
$log = new DomainMOD\Log();

require_once(DIR_INC . 'head.inc.php');
require_once(DIR_INC . 'config.inc.php');
require_once(DIR_INC . 'software.inc.php');
require_once(DIR_INC . 'database.inc.php');

$system->authCheck($web_root);

$upgrade_approved = $_GET['u'];

/*
 * If the database and software versions are different and the user hasn't already approved the upgrade, send them to
 * notice.php and ask the user to confirm the upgrade
 *
 * If If the database and software versions are different and the user HAS approved the upgrade, perform the upgrade
 */
if ($_SESSION['s_system_db_version'] !== $software_version && $upgrade_approved != '1') {

    header("Location: notice.php?a=u");
    exit;

} elseif ($_SESSION['s_system_db_version'] !== $software_version && $upgrade_approved == '1') {

    require_once(DIR_INC . 'update.inc.php');

}

// Load system and user data
$result = $login->getUserInfo($connection, $_SESSION['s_user_id'], $_SESSION['s_username']);

while ($row = mysqli_fetch_object($result)) {

    $_SESSION['s_first_name'] = $row->first_name;
    $_SESSION['s_last_name'] = $row->last_name;
    $_SESSION['s_email_address'] = $row->email_address;
    $_SESSION['s_is_new_password'] = $row->new_password;
    $_SESSION['s_number_of_logins'] = $row->number_of_logins;
    if ($row->admin == 1) $_SESSION['s_is_admin'] = 1;
    if ($row->read_only == '0') $_SESSION['s_read_only'] = '0';

}

$result_settings = $login->getSystemSettings($connection);

while ($row_settings = mysqli_fetch_object($result_settings)) {

    $_SESSION['s_system_full_url'] = $row_settings->full_url;
    $_SESSION['s_system_upgrade_available'] = $row_settings->upgrade_available;
    $_SESSION['s_system_email_address'] = $row_settings->email_address;
    $_SESSION['s_system_large_mode'] = $row_settings->large_mode;
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
    $_SESSION['s_system_expiration_days'] = $row_settings->expiration_days;

}

$result_user_settings = $login->getUserSettings($connection, $_SESSION['s_user_id']);

while ($row_user_settings = mysqli_fetch_object($result_user_settings)) {

    $_SESSION['s_default_currency'] = $row_user_settings->default_currency;
    $_SESSION['s_default_timezone'] = $row_user_settings->default_timezone;
    $_SESSION['s_expiration_email'] = $row_user_settings->expiration_emails;
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

// Check to see if there are any domain lists or domains in the queue
$queue = new DomainMOD\DomainQueue();
$queue->checkListQueue($connection);
$queue->checkDomainQueue($connection);

// Check for existing Domain and SSL assets
$system->checkExistingAssets($connection);

unset($_SESSION['s_installation_mode']);

$login->setLastLogin($connection, $_SESSION['s_user_id'], $_SESSION['s_email_address']);

if ($_SESSION['s_version_error'] != '1') {

    if ($_SESSION['s_system_upgrade_available'] == '1') {

        if ($_SESSION['s_is_admin'] === 1) {

            $_SESSION['s_message_danger'] .= $system->getUpgradeMessage();

        }

    }

    $queryB = new DomainMOD\QueryBuild();

    $sql = $queryB->missingFees('domains');
    $_SESSION['s_missing_domain_fees'] = $system->checkForRows($connection, $sql);

    $queryB = new DomainMOD\QueryBuild();

    $sql = $queryB->missingFees('ssl_certs');
    $_SESSION['s_missing_ssl_fees'] = $system->checkForRows($connection, $sql);

    if ($_SESSION['s_is_new_password'] == 1) {

        $_SESSION['s_message_danger'] .= "Your password should be changed for security purposes<BR>";
        header("Location: settings/password/");
        exit;

    }

}

// Check GitHub to see if a newer version is available
$system->checkVersion($connection, $software_version);

if (isset($_SESSION['s_user_redirect'])) {

    $temp_redirect = $_SESSION['s_user_redirect'];
    unset($_SESSION['s_user_redirect']);

    header("Location: $temp_redirect");
    exit;

} else {

    header("Location: dashboard/");

    exit;

}
