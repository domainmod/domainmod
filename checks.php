<?php
/**
 * /checks.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2024 Greg Chetcuti <greg@chetcuti.com>
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
require_once __DIR__ . '/_includes/start-session.inc.php';
require_once __DIR__ . '/_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$goal = new DomainMOD\Goal();
$log = new DomainMOD\Log('/checks.php');
$login = new DomainMOD\Login();
$maint = new DomainMOD\Maintenance();
$system = new DomainMOD\System();
$time = new DomainMOD\Time();
$upgrade = new DomainMOD\Upgrade();
$currency = new DomainMOD\Currency();
$custom_field = new DomainMOD\CustomField();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$_SESSION['s_running_login_checks'] = '1';

$upgrade_approved = $_GET['u'] ?? '';

/*
 * If the database and software versions are different and the user hasn't already approved the upgrade, send them to
 * notice.php and ask the user to confirm the upgrade
 *
 * If the database and software versions are different and the user HAS approved the upgrade, perform the upgrade
 */
$_SESSION['s_system_db_version'] = $_SESSION['s_system_db_version'] ?? '';
if ($_SESSION['s_system_db_version'] !== SOFTWARE_VERSION && $upgrade_approved != '1') {

    header("Location: notice.php?a=u");
    exit;

} elseif ($_SESSION['s_system_db_version'] !== SOFTWARE_VERSION && $upgrade_approved == '1') {

    $timestamp = $time->stamp();
    require_once DIR_INC . '/update.inc.php';

}

// Load User Info
$result = $login->getUserInfo($_SESSION['s_user_id']);
$_SESSION['s_first_name'] = $result->first_name;
$_SESSION['s_last_name'] = $result->last_name;
$_SESSION['s_email_address'] = $result->email_address;
$_SESSION['s_is_new_password'] = $result->new_password;
$_SESSION['s_number_of_logins'] = $result->number_of_logins;
if ($result->admin == 1) $_SESSION['s_is_admin'] = 1;
if ($result->read_only == '0') $_SESSION['s_read_only'] = '0';

// Load System Settings
$result = $login->getSystemSettings();
$_SESSION['s_system_full_url'] = $result->full_url;
$_SESSION['s_system_upgrade_available'] = $result->upgrade_available;
$_SESSION['s_system_email_address'] = $result->email_address;
$_SESSION['s_system_large_mode'] = $result->large_mode;
$_SESSION['s_system_default_category_domains'] = $result->default_category_domains;
$_SESSION['s_system_default_category_ssl'] = $result->default_category_ssl;
$_SESSION['s_system_default_dns'] = $result->default_dns;
$_SESSION['s_system_default_host'] = $result->default_host;
$_SESSION['s_system_default_ip_address_domains'] = $result->default_ip_address_domains;
$_SESSION['s_system_default_ip_address_ssl'] = $result->default_ip_address_ssl;
$_SESSION['s_system_default_owner_domains'] = $result->default_owner_domains;
$_SESSION['s_system_default_owner_ssl'] = $result->default_owner_ssl;
$_SESSION['s_system_default_registrar'] = $result->default_registrar;
$_SESSION['s_system_default_registrar_account'] = $result->default_registrar_account;
$_SESSION['s_system_default_ssl_provider_account'] = $result->default_ssl_provider_account;
$_SESSION['s_system_default_ssl_type'] = $result->default_ssl_type;
$_SESSION['s_system_default_ssl_provider'] = $result->default_ssl_provider;
$_SESSION['s_system_expiration_days'] = $result->expiration_days;
$_SESSION['s_system_email_signature'] = $result->email_signature;
$_SESSION['s_system_currency_converter'] = $result->currency_converter;
$_SESSION['s_system_local_php_log'] = $result->local_php_log;

// Load User Settings
$result = $login->getUserSettings($_SESSION['s_user_id']);
$_SESSION['s_default_language'] = $result->default_language;
$_SESSION['s_default_language_name'] = $result->language_name;
$_SESSION['s_default_currency'] = $result->default_currency;
$_SESSION['s_default_timezone'] = $result->default_timezone;
$_SESSION['s_expiration_emails'] = $result->expiration_emails;
$_SESSION['s_default_category_domains'] = $result->default_category_domains;
$_SESSION['s_default_category_ssl'] = $result->default_category_ssl;
$_SESSION['s_default_dns'] = $result->default_dns;
$_SESSION['s_default_host'] = $result->default_host;
$_SESSION['s_default_ip_address_domains'] = $result->default_ip_address_domains;
$_SESSION['s_default_ip_address_ssl'] = $result->default_ip_address_ssl;
$_SESSION['s_default_owner_domains'] = $result->default_owner_domains;
$_SESSION['s_default_owner_ssl'] = $result->default_owner_ssl;
$_SESSION['s_default_registrar'] = $result->default_registrar;
$_SESSION['s_default_registrar_account'] = $result->default_registrar_account;
$_SESSION['s_default_ssl_provider_account'] = $result->default_ssl_provider_account;
$_SESSION['s_default_ssl_type'] = $result->default_ssl_type;
$_SESSION['s_default_ssl_provider'] = $result->default_ssl_provider;
$_SESSION['s_number_of_domains'] = $result->number_of_domains;
$_SESSION['s_number_of_ssl_certs'] = $result->number_of_ssl_certs;
$_SESSION['s_display_domain_owner'] = $result->display_domain_owner;
$_SESSION['s_display_domain_registrar'] = $result->display_domain_registrar;
$_SESSION['s_display_domain_account'] = $result->display_domain_account;
$_SESSION['s_display_domain_expiry_date'] = $result->display_domain_expiry_date;
$_SESSION['s_display_domain_category'] = $result->display_domain_category;
$_SESSION['s_display_domain_dns'] = $result->display_domain_dns;
$_SESSION['s_display_domain_host'] = $result->display_domain_host;
$_SESSION['s_display_domain_ip'] = $result->display_domain_ip;
$_SESSION['s_display_domain_tld'] = $result->display_domain_tld;
$_SESSION['s_display_domain_fee'] = $result->display_domain_fee;
$_SESSION['s_display_ssl_owner'] = $result->display_ssl_owner;
$_SESSION['s_display_ssl_provider'] = $result->display_ssl_provider;
$_SESSION['s_display_ssl_account'] = $result->display_ssl_account;
$_SESSION['s_display_ssl_domain'] = $result->display_ssl_domain;
$_SESSION['s_display_ssl_type'] = $result->display_ssl_type;
$_SESSION['s_display_ssl_ip'] = $result->display_ssl_ip;
$_SESSION['s_display_ssl_category'] = $result->display_ssl_category;
$_SESSION['s_display_ssl_expiry_date'] = $result->display_ssl_expiry_date;
$_SESSION['s_display_ssl_fee'] = $result->display_ssl_fee;
$_SESSION['s_display_inactive_assets'] = $result->display_inactive_assets;
$_SESSION['s_display_dw_intro_page'] = $result->display_dw_intro_page;
$_SESSION['s_dark_mode'] = (int) $result->dark_mode;

// Load Custom Domain Field Data
$_SESSION['s_cdf_data'] = $custom_field->getCDFData();

// Load Custom SSL Field Data
$_SESSION['s_csf_data'] = $custom_field->getCSFData();

// Load Currency Info
list($_SESSION['s_default_currency_name'], $_SESSION['s_default_currency_symbol'],
    $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space'])
    = $currency->getCurrencyInfo($_SESSION['s_default_currency']);

// Check to see if there are any domain lists or domains in the queue
$queue = new DomainMOD\DomainQueue();
$queue->checkListQueue();
$queue->checkDomainQueue();

// Check for existing Domain and SSL assets
$system->checkExistingAssets();

unset($_SESSION['s_installation_mode']);

$login->setLastLogin($_SESSION['s_user_id']);

$_SESSION['s_version_error'] = $_SESSION['s_version_error'] ?? 0;
if ($_SESSION['s_version_error'] != '1') {

    // Log installation and upgrade activity
    $goal->remote();

    // Notify if there's a new version available for download
    if ($_SESSION['s_system_upgrade_available'] == '1') {

        if ($_SESSION['s_is_admin'] === 1) {

            $_SESSION['s_message_info'] .= $system->getUpgradeMessage();

        }

    }

    $queryB = new DomainMOD\QueryBuild();

    // Check for missing domain fees
    $sql = $queryB->missingFees('domains');
    $_SESSION['s_missing_domain_fees'] = $system->checkForRows($sql);

    // Check for missing ssl fees
    $sql = $queryB->missingFees('ssl_certs');
    $_SESSION['s_missing_ssl_fees'] = $system->checkForRows($sql);

    // If it's a new password ask the user to change it
    if ($_SESSION['s_is_new_password'] == 1) {

        $_SESSION['s_message_danger'] .= _('Your password should be changed for security purposes') . '<BR>';
        header("Location: settings/password/");
        exit;

    }

}

// Check GitHub to see if a newer version is available
$system->checkVersion(SOFTWARE_VERSION);

unset($_SESSION['s_running_login_checks']);

if (isset($_SESSION['s_user_redirect'])) {

    $temp_redirect = $_SESSION['s_user_redirect'];
    unset($_SESSION['s_user_redirect']);

    header('Location: ' . $temp_redirect);
    exit;

} else {

    header('Location: dashboard/');
    exit;

}
