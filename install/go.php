<?php
/**
 * /install/go/index.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2022 Greg Chetcuti <greg@chetcuti.com>
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
require_once __DIR__ . '/../_includes/start-session.inc.php';
require_once __DIR__ . '/../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$goal = new DomainMOD\Goal();
$log = new DomainMOD\Log('/install/go/index.php');
$system = new DomainMOD\System();
$time = new DomainMOD\Time();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';

$system->loginCheck();
$pdo = $deeb->cnxx;
$timestamp = $time->stamp();

// Admin
$temp_admin_first_name = _('Domain');
$temp_admin_last_name = _('Administrator');

// Assets
$temp_default_category = _('[no category]');
$temp_default_stakeholder = _('[no stakeholder]');
$temp_default_hosting = _('[no hosting]');
$temp_default_owner = _('[no owner]');
$temp_default_dns = _('[no dns]');
$temp_default_ip_address = _('[no ip address]');

// SSL Cert Types
$temp_default_ssl_type_one = _('Web Server SSL/TLS Certificate');
$temp_default_ssl_type_two = _('S/MIME and Authentication Certificate');
$temp_default_ssl_type_three = _('Object Code Signing Certificate');
$temp_default_ssl_type_four = _('Digital ID');

// Task Scheduler
$temp_task_scheduler_one = _('Domain Queue Processing');
$temp_task_scheduler_two = _('Send Expiration Email');
$temp_task_scheduler_three = _('Update Conversion Rates');
$temp_task_scheduler_four = _('System Cleanup');
$temp_task_scheduler_five = _('Check For New Version');
$temp_task_scheduler_six = _('Data Warehouse Build');

// System Settings
$installation_language = DEFAULT_LANGUAGE;
$installation_timezone = $_SESSION['s_installation_timezone'];
$installation_currency = $_SESSION['s_installation_currency'];

$system->installCheck();

try {

    $pdo->beginTransaction();

    $_SESSION['s_installation_mode'] = 1;

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `creation_types` (
            `id` TINYINT(2) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        INSERT INTO creation_types
        (`name`, insert_time)
         VALUES
        ('Installation', '" . $timestamp . "'),
        ('Manual', '" . $timestamp . "'),
        ('Bulk Updater', '" . $timestamp . "'),
        ('Manual or Bulk Updater', '" . $timestamp . "'),
        ('Queue', '" . $timestamp . "'),
        ('CSV Importer', '" . $timestamp . "')");

    $creation_type_id_installation = $system->getCreationTypeId('Installation');
    $creation_type_id_manual = $system->getCreationTypeId('Manual');

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `users` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `first_name` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `last_name` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `username` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `email_address` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `password` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `new_password` TINYINT(1) NOT NULL DEFAULT '1',
            `admin` TINYINT(1) NOT NULL DEFAULT '0',
            `read_only` TINYINT(1) NOT NULL DEFAULT '1',
            `active` TINYINT(1) NOT NULL DEFAULT '1',
            `number_of_logins` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `last_login` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
            `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $stmt = $pdo->prepare("
        INSERT INTO `users`
        (`first_name`, `last_name`, `username`, `email_address`, `password`, `admin`, `read_only`, `creation_type_id`,
         `insert_time`)
        VALUES
        (:new_first_name, :new_last_name, 'admin', :new_admin_email, '*4ACFE3202A5FF5CF467898FC58AAB1D615029441', '1', '0',
         :creation_type_id_installation, :timestamp)");
    $stmt->bindValue('new_first_name', $temp_admin_first_name, PDO::PARAM_STR);
    $stmt->bindValue('new_last_name', $temp_admin_last_name, PDO::PARAM_STR);
    $stmt->bindValue('new_admin_email', $_SESSION['new_admin_email'], PDO::PARAM_STR);
    $stmt->bindValue('creation_type_id_installation', $creation_type_id_installation, PDO::PARAM_INT);
    $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
    $stmt->execute();

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `user_settings` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT(10) UNSIGNED NOT NULL,
            `default_language` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '" . $installation_language . "',
            `default_currency` VARCHAR(3) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `default_timezone` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '" . $installation_timezone . "',
            `default_category_domains` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `default_category_ssl` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `default_dns` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `default_host` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `default_ip_address_domains` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `default_ip_address_ssl` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `default_owner_domains` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `default_owner_ssl` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `default_registrar` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `default_registrar_account` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `default_ssl_provider_account` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `default_ssl_type` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `default_ssl_provider` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `expiration_emails` TINYINT(1) NOT NULL DEFAULT '0',
            `number_of_domains` INT(5) NOT NULL DEFAULT '50',
            `number_of_ssl_certs` INT(5) NOT NULL DEFAULT '50',
            `display_domain_owner` TINYINT(1) NOT NULL DEFAULT '0',
            `display_domain_registrar` TINYINT(1) NOT NULL DEFAULT '0',
            `display_domain_account` TINYINT(1) NOT NULL DEFAULT '1',
            `display_domain_expiry_date` TINYINT(1) NOT NULL DEFAULT '1',
            `display_domain_category` TINYINT(1) NOT NULL DEFAULT '1',
            `display_domain_dns` TINYINT(1) NOT NULL DEFAULT '1',
            `display_domain_host` TINYINT(1) NOT NULL DEFAULT '0',
            `display_domain_ip` TINYINT(1) NOT NULL DEFAULT '0',
            `display_domain_tld` TINYINT(1) NOT NULL DEFAULT '1',
            `display_domain_fee` TINYINT(1) NOT NULL DEFAULT '1',
            `display_ssl_owner` TINYINT(1) NOT NULL DEFAULT '1',
            `display_ssl_provider` TINYINT(1) NOT NULL DEFAULT '0',
            `display_ssl_account` TINYINT(1) NOT NULL DEFAULT '1',
            `display_ssl_domain` TINYINT(1) NOT NULL DEFAULT '1',
            `display_ssl_type` TINYINT(1) NOT NULL DEFAULT '1',
            `display_ssl_expiry_date` TINYINT(1) NOT NULL DEFAULT '1',
            `display_ssl_ip` TINYINT(1) NOT NULL DEFAULT '0',
            `display_ssl_category` TINYINT(1) NOT NULL DEFAULT '0',
            `display_ssl_fee` TINYINT(1) NOT NULL DEFAULT '0',
            `display_inactive_assets` TINYINT(1) NOT NULL DEFAULT '1',
            `display_dw_intro_page` TINYINT(1) NOT NULL DEFAULT '1',
            `dark_mode` TINYINT(1) NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $temp_user_id = $pdo->query("
        SELECT id
        FROM users
        WHERE username = 'admin'")->fetchColumn();

   $stmt = $pdo->prepare("
        INSERT INTO user_settings
        (user_id, default_currency, expiration_emails, insert_time)
        VALUES
        (:temp_user_id, :installation_currency, '1', :timestamp)");
    $stmt->bindValue('temp_user_id', $temp_user_id, PDO::PARAM_INT);
    $stmt->bindValue('installation_currency', $installation_currency, PDO::PARAM_STR);
    $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
    $stmt->execute();

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `categories` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(150) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `stakeholder` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
            `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

   $stmt = $pdo->prepare("
        INSERT INTO `categories`
        (`name`, `stakeholder`, `creation_type_id`, `insert_time`)
        VALUES
        (:temp_default_category, :temp_default_stakeholder, :creation_type_id_installation, :timestamp)");
    $stmt->bindValue('temp_default_category', $temp_default_category, PDO::PARAM_STR);
    $stmt->bindValue('temp_default_stakeholder', $temp_default_stakeholder, PDO::PARAM_STR);
    $stmt->bindValue('creation_type_id_installation', $creation_type_id_installation, PDO::PARAM_INT);
    $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
    $stmt->execute();

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `hosting` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `url` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
            `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

   $stmt = $pdo->prepare("
        INSERT INTO `hosting`
        (`name`, `creation_type_id`, `insert_time`)
        VALUES
        (:temp_default_hosting, :creation_type_id_installation, :timestamp)");
    $stmt->bindValue('temp_default_hosting', $temp_default_hosting, PDO::PARAM_STR);
    $stmt->bindValue('creation_type_id_installation', $creation_type_id_installation, PDO::PARAM_INT);
    $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
    $stmt->execute();

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `owners` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
            `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`),
            KEY `name` (`name`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

   $stmt = $pdo->prepare("
        INSERT INTO `owners`
        (`name`, `creation_type_id`, `insert_time`)
        VALUES
        (:temp_default_owner, :creation_type_id_installation, :timestamp)");
    $stmt->bindValue('temp_default_owner', $temp_default_owner, PDO::PARAM_STR);
    $stmt->bindValue('creation_type_id_installation', $creation_type_id_installation, PDO::PARAM_INT);
    $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
    $stmt->execute();

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `languages` (
            `id` INT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `language` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        INSERT INTO `languages`
        (`name`, language, insert_time)
         VALUES
        ('English (Canada)', 'en_CA.UTF-8', '" . $timestamp . "'),
        ('English (United States)', 'en_US.UTF-8', '" . $timestamp . "'),
        ('German', 'de_DE.UTF-8', '" . $timestamp . "'),
        ('Spanish', 'es_ES.UTF-8', '" . $timestamp . "'),
        ('French', 'fr_FR.UTF-8', '" . $timestamp . "'),
        ('Italian', 'it_IT.UTF-8', '" . $timestamp . "'),
        ('Dutch', 'nl_NL.UTF-8', '" . $timestamp . "'),
        ('Polish', 'pl_PL.UTF-8', '" . $timestamp . "'),
        ('Portuguese', 'pt_PT.UTF-8', '" . $timestamp . "'),
        ('Russian', 'ru_RU.UTF-8', '" . $timestamp . "')");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `currencies` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `currency` VARCHAR(4) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `name` VARCHAR(75) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `symbol` VARCHAR(4) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `symbol_order` TINYINT(1) NOT NULL DEFAULT '0',
            `symbol_space` TINYINT(1) NOT NULL DEFAULT '0',
            `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        INSERT INTO currencies
        (`name`, currency, symbol, insert_time)
        VALUES
        ('Albania Lek', 'ALL', 'Lek', '" . $timestamp . "'),
        ('Afghanistan Afghani', 'AFN', '؋', '" . $timestamp . "'),
        ('Argentina Peso', 'ARS', '$', '" . $timestamp . "'),
        ('Aruba Guilder', 'AWG', 'ƒ', '" . $timestamp . "'),
        ('Australia Dollar', 'AUD', '$', '" . $timestamp . "'),
        ('Azerbaijan New Manat', 'AZN', '" . 'ман' . "', '" . $timestamp . "'),
        ('Bahamas Dollar', 'BSD', '$', '" . $timestamp . "'),
        ('Barbados Dollar', 'BBD', '$', '" . $timestamp . "'),
        ('Belarus Ruble', 'BYR', 'p.', '" . $timestamp . "'),
        ('Belize Dollar', 'BZD', 'BZ$', '" . $timestamp . "'),
        ('Bermuda Dollar', 'BMD', '$', '" . $timestamp . "'),
        ('Bolivia Boliviano', 'BOB', '\$b', '" . $timestamp . "'),
        ('Bosnia and Herzegovina Convertible Marka', 'BAM', 'KM', '" . $timestamp . "'),
        ('Botswana Pula', 'BWP', 'P', '" . $timestamp . "'),
        ('Bulgaria Lev', 'BGN', 'лв', '" . $timestamp . "'),
        ('Brazil Real', 'BRL', 'R$', '" . $timestamp . "'),
        ('Brunei Darussalam Dollar', 'BND', '$', '" . $timestamp . "'),
        ('Cambodia Riel', 'KHR', '៛', '" . $timestamp . "'),
        ('Canada Dollar', 'CAD', '$', '" . $timestamp . "'),
        ('Cayman Islands Dollar', 'KYD', '$', '" . $timestamp . "'),
        ('Chile Peso', 'CLP', '$', '" . $timestamp . "'),
        ('China Yuan Renminbi', 'CNY', '¥', '" . $timestamp . "'),
        ('Colombia Peso', 'COP', '$', '" . $timestamp . "'),
        ('Costa Rica Colon', 'CRC', '₡', '" . $timestamp . "'),
        ('Croatia Kuna', 'HRK', 'kn', '" . $timestamp . "'),
        ('Cuba Peso', 'CUP', '₱', '" . $timestamp . "'),
        ('Czech Republic Koruna', 'CZK', 'Kč', '" . $timestamp . "'),
        ('Denmark Krone', 'DKK', 'kr', '" . $timestamp . "'),
        ('Dominican Republic Peso', 'DOP', 'RD$', '" . $timestamp . "'),
        ('East Caribbean Dollar', 'XCD', '$', '" . $timestamp . "'),
        ('Egypt Pound', 'EGP', '£', '" . $timestamp . "'),
        ('El Salvador Colon', 'SVC', '$', '" . $timestamp . "'),
        ('Estonia Kroon', 'EEK', 'kr', '" . $timestamp . "'),
        ('Euro Member Countries', 'EUR', '€', '" . $timestamp . "'),
        ('Falkland Islands (Malvinas) Pound', 'FKP', '£', '" . $timestamp . "'),
        ('Fiji Dollar', 'FJD', '$', '" . $timestamp . "'),
        ('Ghana Cedis', 'GHC', '¢', '" . $timestamp . "'),
        ('Gibraltar Pound', 'GIP', '£', '" . $timestamp . "'),
        ('Guatemala Quetzal', 'GTQ', 'Q', '" . $timestamp . "'),
        ('Guernsey Pound', 'GGP', '£', '" . $timestamp . "'),
        ('Guyana Dollar', 'GYD', '$', '" . $timestamp . "'),
        ('Honduras Lempira', 'HNL', 'L', '" . $timestamp . "'),
        ('Hong Kong Dollar', 'HKD', '$', '" . $timestamp . "'),
        ('Hungary Forint', 'HUF', 'Ft', '" . $timestamp . "'),
        ('Iceland Krona', 'ISK', 'kr', '" . $timestamp . "'),
        ('India Rupee', 'INR', 'Rs', '" . $timestamp . "'),
        ('Indonesia Rupiah', 'IDR', 'Rp', '" . $timestamp . "'),
        ('Iran Rial', 'IRR', '﷼', '" . $timestamp . "'),
        ('Isle of Man Pound', 'IMP', '£', '" . $timestamp . "'),
        ('Israel Shekel', 'ILS', '₪', '" . $timestamp . "'),
        ('Jamaica Dollar', 'JMD', 'J$', '" . $timestamp . "'),
        ('Japan Yen', 'JPY', '¥', '" . $timestamp . "'),
        ('Jersey Pound', 'JEP', '£', '" . $timestamp . "'),
        ('Kazakhstan Tenge', 'KZT', 'лв', '" . $timestamp . "'),
        ('Korea (North) Won', 'KPW', '₩', '" . $timestamp . "'),
        ('Korea (South) Won', 'KRW', '₩', '" . $timestamp . "'),
        ('Kyrgyzstan Som', 'KGS', 'лв', '" . $timestamp . "'),
        ('Laos Kip', 'LAK', '₭', '" . $timestamp . "'),
        ('Latvia Lat', 'LVL', 'Ls', '" . $timestamp . "'),
        ('Lebanon Pound', 'LBP', '£', '" . $timestamp . "'),
        ('Liberia Dollar', 'LRD', '$', '" . $timestamp . "'),
        ('Lithuania Litas', 'LTL', 'Lt', '" . $timestamp . "'),
        ('Macedonia Denar', 'MKD', 'ден', '" . $timestamp . "'),
        ('Malaysia Ringgit', 'RM', 'RM', '" . $timestamp . "'),
        ('Mauritius Rupee', 'MUR', '₨', '" . $timestamp . "'),
        ('Mexico Peso', 'MXN', '$', '" . $timestamp . "'),
        ('Mongolia Tughrik', 'MNT', '₮', '" . $timestamp . "'),
        ('Mozambique Metical', 'MZN', 'MT', '" . $timestamp . "'),
        ('Namibia Dollar', 'NAD', '$', '" . $timestamp . "'),
        ('Nepal Rupee', 'NPR', '₨', '" . $timestamp . "'),
        ('Netherlands Antilles Guilder', 'ANG', 'ƒ', '" . $timestamp . "'),
        ('New Zealand Dollar', 'NZD', '$', '" . $timestamp . "'),
        ('Nicaragua Cordoba', 'NIO', 'C$', '" . $timestamp . "'),
        ('Nigeria Naira', 'NGN', '₦', '" . $timestamp . "'),
        ('Norway Krone', 'NOK', 'kr', '" . $timestamp . "'),
        ('Oman Rial', 'OMR', '﷼', '" . $timestamp . "'),
        ('Pakistan Rupee', 'PKR', '₨', '" . $timestamp . "'),
        ('Panama Balboa', 'PAB', 'B/.', '" . $timestamp . "'),
        ('Paraguay Guarani', 'PYG', 'Gs', '" . $timestamp . "'),
        ('Peru Nuevo Sol', 'PEN', 'S/.', '" . $timestamp . "'),
        ('Philippines Peso', 'PHP', '₱', '" . $timestamp . "'),
        ('Poland Zloty', 'PLN', 'zł', '" . $timestamp . "'),
        ('Qatar Riyal', 'QAR', '﷼', '" . $timestamp . "'),
        ('Romania New Leu', 'RON', 'lei', '" . $timestamp . "'),
        ('Russia Ruble', 'RUB', 'руб', '" . $timestamp . "'),
        ('Saint Helena Pound', 'SHP', '£', '" . $timestamp . "'),
        ('Saudi Arabia Riyal', 'SAR', '﷼', '" . $timestamp . "'),
        ('Serbia Dinar', 'RSD', 'Дин.', '" . $timestamp . "'),
        ('Seychelles Rupee', 'SCR', '₨', '" . $timestamp . "'),
        ('Singapore Dollar', 'SGD', '$', '" . $timestamp . "'),
        ('Solomon Islands Dollar', 'SBD', '$', '" . $timestamp . "'),
        ('Somalia Shilling', 'SOS', 'S', '" . $timestamp . "'),
        ('South Africa Rand', 'ZAR', 'R', '" . $timestamp . "'),
        ('Sri Lanka Rupee', 'LKR', '₨', '" . $timestamp . "'),
        ('Sweden Krona', 'SEK', 'kr', '" . $timestamp . "'),
        ('Switzerland Franc', 'CHF', 'CHF', '" . $timestamp . "'),
        ('Suriname Dollar', 'SRD', '$', '" . $timestamp . "'),
        ('Syria Pound', 'SYP', '£', '" . $timestamp . "'),
        ('Taiwan New Dollar', 'TWD', 'NT$', '" . $timestamp . "'),
        ('Thailand Baht', 'THB', '฿', '" . $timestamp . "'),
        ('Trinidad and Tobago Dollar', 'TTD', 'TT$', '" . $timestamp . "'),
        ('Turkey Lira', 'TRY', '₺', '" . $timestamp . "'),
        ('Tuvalu Dollar', 'TVD', '$', '" . $timestamp . "'),
        ('Ukraine Hryvna', 'UAH', '₴', '" . $timestamp . "'),
        ('United Kingdom Pound', 'GBP', '£', '" . $timestamp . "'),
        ('United States Dollar', 'USD', '$', '" . $timestamp . "'),
        ('Uruguay Peso', 'UYU', '\$U', '" . $timestamp . "'),
        ('Uzbekistan Som', 'UZS', 'лв', '" . $timestamp . "'),
        ('Venezuela Bolivar', 'VEF', 'Bs', '" . $timestamp . "'),
        ('Viet Nam Dong', 'VND', '₫', '" . $timestamp . "'),
        ('Yemen Rial', 'YER', '﷼', '" . $timestamp . "'),
        ('Zimbabwe Dollar', 'ZWD', 'Z$', '" . $timestamp . "'),
        ('Emirati Dirham', 'AED', 'د.إ', '" . $timestamp . "'),
        ('Malaysian Ringgit', 'MYR', 'RM', '" . $timestamp . "'),
        ('Kuwaiti Dinar', 'KWD', 'ك', '" . $timestamp . "'),
        ('Moroccan Dirham', 'MAD', 'م.', '" . $timestamp . "'),
        ('Iraqi Dinar', 'IQD', 'د.ع', '" . $timestamp . "'),
        ('Bangladeshi Taka', 'BDT', 'Tk', '" . $timestamp . "'),
        ('Bahraini Dinar', 'BHD', 'BD', '" . $timestamp . "'),
        ('Kenyan Shilling', 'KES', 'KSh', '" . $timestamp . "'),
        ('CFA Franc', 'XOF', 'CFA', '" . $timestamp . "'),
        ('Jordanian Dinar', 'JOD', 'JD', '" . $timestamp . "'),
        ('Tunisian Dinar', 'TND', 'د.ت', '" . $timestamp . "'),
        ('Ghanaian Cedi', 'GHS', 'GH¢', '" . $timestamp . "'),
        ('Central African CFA Franc BEAC', 'XAF', 'FCFA', '" . $timestamp . "'),
        ('Algerian Dinar', 'DZD', 'دج', '" . $timestamp . "'),
        ('CFP Franc', 'XPF', 'F', '" . $timestamp . "'),
        ('Ugandan Shilling', 'UGX', 'USh', '" . $timestamp . "'),
        ('Tanzanian Shilling', 'TZS', 'TZS', '" . $timestamp . "'),
        ('Ethiopian Birr', 'ETB', 'Br', '" . $timestamp . "'),
        ('Georgian Lari', 'GEL', 'GEL', '" . $timestamp . "'),
        ('Cuban Convertible Peso', 'CUC', 'CUC$', '" . $timestamp . "'),
        ('Burmese Kyat', 'MMK', 'K', '" . $timestamp . "'),
        ('Libyan Dinar', 'LYD', 'LD', '" . $timestamp . "'),
        ('Zambian Kwacha', 'ZMK', 'ZK', '" . $timestamp . "'),
        ('Zambian Kwacha', 'ZMW', 'ZK', '" . $timestamp . "'),
        ('Macau Pataca', 'MOP', 'MOP$', '" . $timestamp . "'),
        ('Armenian Dram', 'AMD', 'AMD', '" . $timestamp . "'),
        ('Angolan Kwanza', 'AOA', 'Kz', '" . $timestamp . "'),
        ('Papua New Guinean Kina', 'PGK', 'K', '" . $timestamp . "'),
        ('Malagasy Ariary', 'MGA', 'Ar', '" . $timestamp . "'),
        ('Ni-Vanuatu Vatu', 'VUV', 'VT', '" . $timestamp . "'),
        ('Sudanese Pound', 'SDG', 'SDG', '" . $timestamp . "'),
        ('Malawian Kwacha', 'MWK', 'MK', '" . $timestamp . "'),
        ('Rwandan Franc', 'RWF', 'FRw', '" . $timestamp . "'),
        ('Gambian Dalasi', 'GMD', 'D', '" . $timestamp . "'),
        ('Maldivian Rufiyaa', 'MVR', 'Rf', '" . $timestamp . "'),
        ('Congolese Franc', 'CDF', 'FC', '" . $timestamp . "'),
        ('Djiboutian Franc', 'DJF', 'Fdj', '" . $timestamp . "'),
        ('Haitian Gourde', 'HTG', 'G', '" . $timestamp . "'),
        ('Samoan Tala', 'WST', '$', '" . $timestamp . "'),
        ('Guinean Franc', 'GNF', 'FG', '" . $timestamp . "'),
        ('Cape Verdean Escudo', 'CVE', '$', '" . $timestamp . "'),
        ('Tongan Pa\'anga', 'TOP', 'T$', '" . $timestamp . "'),
        ('Moldovan Leu', 'MDL', 'MDL', '" . $timestamp . "'),
        ('Sierra Leonean Leone', 'SLL', 'Le', '" . $timestamp . "'),
        ('Burundian Franc', 'BIF', 'FBu', '" . $timestamp . "'),
        ('Mauritanian Ouguiya', 'MRO', 'UM', '" . $timestamp . "'),
        ('Bhutanese Ngultrum', 'BTN', 'Nu.', '" . $timestamp . "'),
        ('Swazi Lilangeni', 'SZL', 'SZL', '" . $timestamp . "'),
        ('Tajikistani Somoni', 'TJS', 'TJS', '" . $timestamp . "'),
        ('Turkmenistani Manat', 'TMT', 'm', '" . $timestamp . "'),
        ('Basotho Loti', 'LSL', 'LSL', '" . $timestamp . "'),
        ('Comoran Franc', 'KMF', 'CF', '" . $timestamp . "'),
        ('Sao Tomean Dobra', 'STD', 'STD', '" . $timestamp . "'),
        ('Seborgan Luigino', 'SPL', 'SPL', '" . $timestamp . "')");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `currency_conversions` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `currency_id` INT(10) UNSIGNED NOT NULL,
            `user_id` INT(10) UNSIGNED NOT NULL,
            `conversion` DECIMAL(12,4) NOT NULL,
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `fees` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `registrar_id` INT(10) UNSIGNED NOT NULL,
            `tld` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `initial_fee` DECIMAL(10,2) NOT NULL,
            `renewal_fee` DECIMAL(10,2) NOT NULL,
            `transfer_fee` DECIMAL(10,2) NOT NULL,
            `privacy_fee` DECIMAL(10,2) NOT NULL,
            `misc_fee` DECIMAL(10,2) NOT NULL,
            `currency_id` INT(10) UNSIGNED NOT NULL,
            `fee_fixed` TINYINT(1) NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `ssl_fees` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `ssl_provider_id` INT(10) UNSIGNED NOT NULL,
            `type_id` INT(10) UNSIGNED NOT NULL,
            `initial_fee` DECIMAL(10,2) NOT NULL,
            `renewal_fee` DECIMAL(10,2) NOT NULL,
            `misc_fee` DECIMAL(10,2) NOT NULL,
            `currency_id` INT(10) UNSIGNED NOT NULL,
            `fee_fixed` TINYINT(1) NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `domains` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `owner_id` INT(10) UNSIGNED NOT NULL DEFAULT '1',
            `registrar_id` INT(10) UNSIGNED NOT NULL DEFAULT '1',
            `account_id` INT(10) UNSIGNED NOT NULL DEFAULT '1',
            `domain` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `tld` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `expiry_date` DATE NOT NULL DEFAULT '1970-01-01',
            `cat_id` INT(10) UNSIGNED NOT NULL DEFAULT '1',
            `fee_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `total_cost` DECIMAL(10,2) NOT NULL,
            `dns_id` INT(10) UNSIGNED NOT NULL DEFAULT '1',
            `ip_id` INT(10) UNSIGNED NOT NULL DEFAULT '1',
            `hosting_id` INT(10) UNSIGNED NOT NULL DEFAULT '1',
            `function` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `autorenew` TINYINT(1) NOT NULL DEFAULT '0',
            `privacy` TINYINT(1) NOT NULL DEFAULT '0',
            `active` TINYINT(2) NOT NULL DEFAULT '1',
            `fee_fixed` TINYINT(1) NOT NULL DEFAULT '0',
            `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
            `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`),
            KEY `domain` (`domain`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `domain_queue` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `api_registrar_id` SMALLINT(5) NOT NULL DEFAULT '0',
            `domain_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `owner_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `registrar_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `account_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `domain` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `tld` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `expiry_date` DATE NOT NULL DEFAULT '1970-01-01',
            `cat_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `dns_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `ip_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `hosting_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `autorenew` TINYINT(1) NOT NULL DEFAULT '0',
            `privacy` TINYINT(1) NOT NULL DEFAULT '0',
            `processing` TINYINT(1) NOT NULL DEFAULT '0',
            `ready_to_import` TINYINT(1) NOT NULL DEFAULT '0',
            `finished` TINYINT(1) NOT NULL DEFAULT '0',
            `already_in_domains` TINYINT(1) NOT NULL DEFAULT '0',
            `already_in_queue` TINYINT(1) NOT NULL DEFAULT '0',
            `invalid_domain` TINYINT(1) NOT NULL DEFAULT '0',
            `copied_to_history` TINYINT(1) NOT NULL DEFAULT '0',
            `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `domain_queue_history` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `api_registrar_id` SMALLINT(5) NOT NULL DEFAULT '0',
            `domain_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `owner_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `registrar_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `account_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `domain` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `tld` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `expiry_date` DATE NOT NULL DEFAULT '1970-01-01',
            `cat_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `dns_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `ip_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `hosting_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `autorenew` TINYINT(1) NOT NULL DEFAULT '0',
            `privacy` TINYINT(1) NOT NULL DEFAULT '0',
            `already_in_domains` TINYINT(1) NOT NULL DEFAULT '0',
            `already_in_queue` TINYINT(1) NOT NULL DEFAULT '0',
            `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `domain_queue_list` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `api_registrar_id` SMALLINT(5) NOT NULL DEFAULT '0',
            `domain_count` INT(6) NOT NULL DEFAULT '0',
            `owner_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `registrar_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `account_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `processing` TINYINT(1) NOT NULL DEFAULT '0',
            `ready_to_import` TINYINT(1) NOT NULL DEFAULT '0',
            `finished` TINYINT(1) NOT NULL DEFAULT '0',
            `copied_to_history` TINYINT(1) NOT NULL DEFAULT '0',
            `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `domain_queue_list_history` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `api_registrar_id` SMALLINT(5) NOT NULL DEFAULT '0',
            `domain_count` INT(6) NOT NULL DEFAULT '0',
            `owner_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `registrar_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `account_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `domain_queue_temp` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `account_id` INT(10) UNSIGNED NOT NULL,
            `domain` VARCHAR(255) NOT NULL,
            `expiry_date` DATE NOT NULL DEFAULT '1970-01-01',
            `ns1` VARCHAR(255) NOT NULL,
            `ns2` VARCHAR(255) NOT NULL,
            `ns3` VARCHAR(255) NOT NULL,
            `ns4` VARCHAR(255) NOT NULL,
            `ns5` VARCHAR(255) NOT NULL,
            `ns6` VARCHAR(255) NOT NULL,
            `ns7` VARCHAR(255) NOT NULL,
            `ns8` VARCHAR(255) NOT NULL,
            `ns9` VARCHAR(255) NOT NULL,
            `ns10` VARCHAR(255) NOT NULL,
            `autorenew` TINYINT(1) NOT NULL DEFAULT '0',
            `privacy` TINYINT(1) NOT NULL DEFAULT '0',
            PRIMARY KEY  (`id`),
            KEY `domain` (`domain`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `custom_field_types` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(150) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
        PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        INSERT INTO custom_field_types
        (id, `name`, insert_time)
        VALUES
        (1, 'Check Box', '" . $timestamp . "'),
        (2, 'Text', '" . $timestamp . "'),
        (3, 'Text Area', '" . $timestamp . "'),
        (4, 'Date', '" . $timestamp . "'),
        (5, 'Time Stamp', '" . $timestamp . "'),
        (6, 'URL', '" . $timestamp . "')");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `domain_fields` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(75) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `field_name` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `type_id` INT(10) UNSIGNED NOT NULL,
            `description` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
            `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `domain_field_data` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `domain_id` INT(10) UNSIGNED NOT NULL,
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `ssl_certs` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `owner_id` INT(10) UNSIGNED NOT NULL,
            `ssl_provider_id` INT(10) UNSIGNED NOT NULL,
            `account_id` INT(10) UNSIGNED NOT NULL,
            `domain_id` INT(10) UNSIGNED NOT NULL,
            `type_id` INT(10) UNSIGNED NOT NULL,
            `ip_id` INT(10) UNSIGNED NOT NULL,
            `cat_id` INT(10) UNSIGNED NOT NULL,
            `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `expiry_date` DATE NOT NULL DEFAULT '1970-01-01',
            `fee_id` INT(10) UNSIGNED NOT NULL,
            `total_cost` DECIMAL(10,2) NOT NULL,
            `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `active` TINYINT(2) NOT NULL DEFAULT '1',
            `fee_fixed` TINYINT(1) NOT NULL DEFAULT '0',
            `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
            `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `ssl_cert_types` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `type` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
            `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $stmt = $pdo->prepare("
        INSERT INTO `ssl_cert_types`
        (`id`, `type`, `creation_type_id`, `insert_time`)
        VALUES
        (1, :temp_default_ssl_type_one, :creation_type_id_installation, :timestamp)");
    $stmt->bindValue('temp_default_ssl_type_one', $temp_default_ssl_type_one, PDO::PARAM_STR);
    $stmt->bindValue('creation_type_id_installation', $creation_type_id_installation, PDO::PARAM_INT);
    $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
    $stmt->execute();

    $stmt = $pdo->prepare("
        INSERT INTO `ssl_cert_types`
        (`id`, `type`, `creation_type_id`, `insert_time`)
        VALUES
        (2, :temp_default_ssl_type_two, :creation_type_id_installation, :timestamp)");
    $stmt->bindValue('temp_default_ssl_type_two', $temp_default_ssl_type_two, PDO::PARAM_STR);
    $stmt->bindValue('creation_type_id_installation', $creation_type_id_installation, PDO::PARAM_INT);
    $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
    $stmt->execute();

    $stmt = $pdo->prepare("
        INSERT INTO `ssl_cert_types`
        (`id`, `type`, `creation_type_id`, `insert_time`)
        VALUES
        (3, :temp_default_ssl_type_three, :creation_type_id_installation, :timestamp)");
    $stmt->bindValue('temp_default_ssl_type_three', $temp_default_ssl_type_three, PDO::PARAM_STR);
    $stmt->bindValue('creation_type_id_installation', $creation_type_id_installation, PDO::PARAM_INT);
    $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
    $stmt->execute();

    $stmt = $pdo->prepare("
        INSERT INTO `ssl_cert_types`
        (`id`, `type`, `creation_type_id`, `insert_time`)
        VALUES
        (4, :temp_default_ssl_type_four, :creation_type_id_installation, :timestamp)");
    $stmt->bindValue('temp_default_ssl_type_four', $temp_default_ssl_type_four, PDO::PARAM_STR);
    $stmt->bindValue('creation_type_id_installation', $creation_type_id_installation, PDO::PARAM_INT);
    $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
    $stmt->execute();

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `ssl_cert_fields` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(75) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `field_name` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `type_id` INT(10) UNSIGNED NOT NULL,
            `description` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
            `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `ssl_cert_field_data` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `ssl_id` INT(10) UNSIGNED NOT NULL,
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `dns` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `dns1` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `dns2` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `dns3` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `dns4` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `dns5` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `dns6` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `dns7` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `dns8` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `dns9` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `dns10` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `ip1` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `ip2` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `ip3` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `ip4` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `ip5` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `ip6` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `ip7` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `ip8` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `ip9` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `ip10` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `number_of_servers` TINYINT(2) NOT NULL DEFAULT '0',
            `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
            `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        INSERT INTO `dns`
        (`name`, `dns1`, `dns2`, `number_of_servers`, `creation_type_id`, `insert_time`)
        VALUES
        ('" . $temp_default_dns . "', 'ns1.no-dns.com', 'ns2.no-dns.com', '2', '" . $creation_type_id_installation . "', '" . $timestamp . "')");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `registrars` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `url` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `api_registrar_id` TINYINT(3) NOT NULL DEFAULT '0',
            `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
            `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`),
            KEY `name` (`name`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `registrar_accounts` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `owner_id` INT(10) UNSIGNED NOT NULL,
            `registrar_id` INT(10) UNSIGNED NOT NULL,
            `email_address` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `username` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `password` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `account_id` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `reseller` TINYINT(1) NOT NULL DEFAULT '0',
            `reseller_id` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `api_app_name` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `api_key` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `api_secret` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `api_ip_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
            `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`),
            KEY `registrar_id` (`registrar_id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `ssl_providers` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `url` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
            `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `ssl_accounts` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `owner_id` INT(10) UNSIGNED NOT NULL,
            `ssl_provider_id` INT(10) UNSIGNED NOT NULL,
            `email_address` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `username` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `password` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `reseller` TINYINT(1) NOT NULL DEFAULT '0',
            `reseller_id` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
            `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`),
            KEY `ssl_provider_id` (`ssl_provider_id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `segments` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(35) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `description` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `segment` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `number_of_domains` INT(6) NOT NULL,
            `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
            `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `segment_data` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `segment_id` INT(10) UNSIGNED NOT NULL,
            `domain` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `active` TINYINT(1) NOT NULL DEFAULT '0',
            `inactive` TINYINT(1) NOT NULL DEFAULT '0',
            `missing` TINYINT(1) NOT NULL DEFAULT '0',
            `filtered` TINYINT(1) NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
        PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `ip_addresses` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `ip` VARCHAR(45) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `rdns` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
            `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

   $stmt = $pdo->prepare("
        INSERT INTO `ip_addresses`
        (`id`, `name`, `ip`, `rdns`, `creation_type_id`, `insert_time`)
        VALUES
        ('1', :temp_default_ip_address, '-', '-', :creation_type_id_installation, :timestamp)");
    $stmt->bindValue('temp_default_ip_address', $temp_default_ip_address, PDO::PARAM_STR);
    $stmt->bindValue('creation_type_id_installation', $creation_type_id_installation, PDO::PARAM_INT);
    $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
    $stmt->execute();

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `timezones` (
            `id` INT(5) UNSIGNED NOT NULL AUTO_INCREMENT,
            `timezone` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        INSERT INTO `timezones`
        (`timezone`, `insert_time`)
        VALUES
        ('Africa/Abidjan', '" . $timestamp . "'), ('Africa/Accra', '" . $timestamp . "'), ('Africa/Addis_Ababa', '" . $timestamp . "'), ('Africa/Algiers', '" . $timestamp . "'), ('Africa/Asmara', '" . $timestamp . "'), ('Africa/Asmera', '" . $timestamp . "'), ('Africa/Bamako', '" . $timestamp . "'), ('Africa/Bangui', '" . $timestamp . "'), ('Africa/Banjul', '" . $timestamp . "'), ('Africa/Bissau', '" . $timestamp . "'), ('Africa/Blantyre', '" . $timestamp . "'), ('Africa/Brazzaville', '" . $timestamp . "'), ('Africa/Bujumbura', '" . $timestamp . "'), ('Africa/Cairo', '" . $timestamp . "'), ('Africa/Casablanca', '" . $timestamp . "'), ('Africa/Ceuta', '" . $timestamp . "'), ('Africa/Conakry', '" . $timestamp . "'), ('Africa/Dakar', '" . $timestamp . "'), ('Africa/Dar_es_Salaam', '" . $timestamp . "'), ('Africa/Djibouti', '" . $timestamp . "'), ('Africa/Douala', '" . $timestamp . "'), ('Africa/El_Aaiun', '" . $timestamp . "'), ('Africa/Freetown', '" . $timestamp . "'), ('Africa/Gaborone', '" . $timestamp . "'), ('Africa/Harare', '" . $timestamp . "'), ('Africa/Johannesburg', '" . $timestamp . "'), ('Africa/Juba', '" . $timestamp . "'), ('Africa/Kampala', '" . $timestamp . "'), ('Africa/Khartoum', '" . $timestamp . "'), ('Africa/Kigali', '" . $timestamp . "'), ('Africa/Kinshasa', '" . $timestamp . "'), ('Africa/Lagos', '" . $timestamp . "'), ('Africa/Libreville', '" . $timestamp . "'), ('Africa/Lome', '" . $timestamp . "'), ('Africa/Luanda', '" . $timestamp . "'), ('Africa/Lubumbashi', '" . $timestamp . "'), ('Africa/Lusaka', '" . $timestamp . "'), ('Africa/Malabo', '" . $timestamp . "'), ('Africa/Maputo', '" . $timestamp . "'), ('Africa/Maseru', '" . $timestamp . "'), ('Africa/Mbabane', '" . $timestamp . "'), ('Africa/Mogadishu', '" . $timestamp . "'), ('Africa/Monrovia', '" . $timestamp . "'), ('Africa/Nairobi', '" . $timestamp . "'), ('Africa/Ndjamena', '" . $timestamp . "'), ('Africa/Niamey', '" . $timestamp . "'), ('Africa/Nouakchott', '" . $timestamp . "'), ('Africa/Ouagadougou', '" . $timestamp . "'), ('Africa/Porto-Novo', '" . $timestamp . "'), ('Africa/Sao_Tome', '" . $timestamp . "'), ('Africa/Timbuktu', '" . $timestamp . "'), ('Africa/Tripoli', '" . $timestamp . "'), ('Africa/Tunis', '" . $timestamp . "'), ('Africa/Windhoek', '" . $timestamp . "'), ('America/Adak', '" . $timestamp . "'), ('America/Anchorage', '" . $timestamp . "'), ('America/Anguilla', '" . $timestamp . "'), ('America/Antigua', '" . $timestamp . "'), ('America/Araguaina', '" . $timestamp . "'), ('America/Argentina/Buenos_Aires', '" . $timestamp . "'), ('America/Argentina/Catamarca', '" . $timestamp . "'), ('America/Argentina/ComodRivadavia', '" . $timestamp . "'), ('America/Argentina/Cordoba', '" . $timestamp . "'), ('America/Argentina/Jujuy', '" . $timestamp . "'), ('America/Argentina/La_Rioja', '" . $timestamp . "'), ('America/Argentina/Mendoza', '" . $timestamp . "'), ('America/Argentina/Rio_Gallegos', '" . $timestamp . "'), ('America/Argentina/Salta', '" . $timestamp . "'), ('America/Argentina/San_Juan', '" . $timestamp . "'), ('America/Argentina/San_Luis', '" . $timestamp . "'), ('America/Argentina/Tucuman', '" . $timestamp . "'), ('America/Argentina/Ushuaia', '" . $timestamp . "'), ('America/Aruba', '" . $timestamp . "'), ('America/Asuncion', '" . $timestamp . "'), ('America/Atikokan', '" . $timestamp . "'), ('America/Atka', '" . $timestamp . "'), ('America/Bahia', '" . $timestamp . "'), ('America/Bahia_Banderas', '" . $timestamp . "'), ('America/Barbados', '" . $timestamp . "'), ('America/Belem', '" . $timestamp . "'), ('America/Belize', '" . $timestamp . "'), ('America/Blanc-Sablon', '" . $timestamp . "'), ('America/Boa_Vista', '" . $timestamp . "'), ('America/Bogota', '" . $timestamp . "'), ('America/Boise', '" . $timestamp . "'), ('America/Buenos_Aires', '" . $timestamp . "'), ('America/Cambridge_Bay', '" . $timestamp . "'), ('America/Campo_Grande', '" . $timestamp . "'), ('America/Cancun', '" . $timestamp . "'), ('America/Caracas', '" . $timestamp . "'), ('America/Catamarca', '" . $timestamp . "'), ('America/Cayenne', '" . $timestamp . "'), ('America/Cayman', '" . $timestamp . "'), ('America/Chicago', '" . $timestamp . "'), ('America/Chihuahua', '" . $timestamp . "'), ('America/Coral_Harbour', '" . $timestamp . "'), ('America/Cordoba', '" . $timestamp . "'), ('America/Costa_Rica', '" . $timestamp . "'), ('America/Creston', '" . $timestamp . "'), ('America/Cuiaba', '" . $timestamp . "'), ('America/Curacao', '" . $timestamp . "'), ('America/Danmarkshavn', '" . $timestamp . "'), ('America/Dawson', '" . $timestamp . "'), ('America/Dawson_Creek', '" . $timestamp . "'), ('America/Denver', '" . $timestamp . "'), ('America/Detroit', '" . $timestamp . "'), ('America/Dominica', '" . $timestamp . "'), ('America/Edmonton', '" . $timestamp . "'), ('America/Eirunepe', '" . $timestamp . "'), ('America/El_Salvador', '" . $timestamp . "'), ('America/Ensenada', '" . $timestamp . "'), ('America/Fort_Wayne', '" . $timestamp . "'), ('America/Fortaleza', '" . $timestamp . "'), ('America/Glace_Bay', '" . $timestamp . "'), ('America/Godthab', '" . $timestamp . "'), ('America/Goose_Bay', '" . $timestamp . "'), ('America/Grand_Turk', '" . $timestamp . "'), ('America/Grenada', '" . $timestamp . "'), ('America/Guadeloupe', '" . $timestamp . "'), ('America/Guatemala', '" . $timestamp . "'), ('America/Guayaquil', '" . $timestamp . "'), ('America/Guyana', '" . $timestamp . "'), ('America/Halifax', '" . $timestamp . "'), ('America/Havana', '" . $timestamp . "'), ('America/Hermosillo', '" . $timestamp . "'), ('America/Indiana/Indianapolis', '" . $timestamp . "'), ('America/Indiana/Knox', '" . $timestamp . "'), ('America/Indiana/Marengo', '" . $timestamp . "'), ('America/Indiana/Petersburg', '" . $timestamp . "'), ('America/Indiana/Tell_City', '" . $timestamp . "'), ('America/Indiana/Vevay', '" . $timestamp . "'), ('America/Indiana/Vincennes', '" . $timestamp . "'), ('America/Indiana/Winamac', '" . $timestamp . "'), ('America/Indianapolis', '" . $timestamp . "'), ('America/Inuvik', '" . $timestamp . "'), ('America/Iqaluit', '" . $timestamp . "'), ('America/Jamaica', '" . $timestamp . "'), ('America/Jujuy', '" . $timestamp . "'), ('America/Juneau', '" . $timestamp . "'), ('America/Kentucky/Louisville', '" . $timestamp . "'), ('America/Kentucky/Monticello', '" . $timestamp . "'), ('America/Knox_IN', '" . $timestamp . "'), ('America/Kralendijk', '" . $timestamp . "'), ('America/La_Paz', '" . $timestamp . "'), ('America/Lima', '" . $timestamp . "'), ('America/Los_Angeles', '" . $timestamp . "'), ('America/Louisville', '" . $timestamp . "'), ('America/Lower_Princes', '" . $timestamp . "'), ('America/Maceio', '" . $timestamp . "'), ('America/Managua', '" . $timestamp . "'), ('America/Manaus', '" . $timestamp . "'), ('America/Marigot', '" . $timestamp . "'), ('America/Martinique', '" . $timestamp . "'), ('America/Matamoros', '" . $timestamp . "'), ('America/Mazatlan', '" . $timestamp . "'), ('America/Mendoza', '" . $timestamp . "'), ('America/Menominee', '" . $timestamp . "'), ('America/Merida', '" . $timestamp . "'), ('America/Metlakatla', '" . $timestamp . "'), ('America/Mexico_City', '" . $timestamp . "'), ('America/Miquelon', '" . $timestamp . "'), ('America/Moncton', '" . $timestamp . "'), ('America/Monterrey', '" . $timestamp . "'), ('America/Montevideo', '" . $timestamp . "'), ('America/Montreal', '" . $timestamp . "'), ('America/Montserrat', '" . $timestamp . "'), ('America/Nassau', '" . $timestamp . "'), ('America/New_York', '" . $timestamp . "'), ('America/Nipigon', '" . $timestamp . "'), ('America/Nome', '" . $timestamp . "'), ('America/Noronha', '" . $timestamp . "'), ('America/North_Dakota/Beulah', '" . $timestamp . "'), ('America/North_Dakota/Center', '" . $timestamp . "'), ('America/North_Dakota/New_Salem', '" . $timestamp . "'), ('America/Ojinaga', '" . $timestamp . "'), ('America/Panama', '" . $timestamp . "'), ('America/Pangnirtung', '" . $timestamp . "'), ('America/Paramaribo', '" . $timestamp . "'), ('America/Phoenix', '" . $timestamp . "'), ('America/Port-au-Prince', '" . $timestamp . "'), ('America/Port_of_Spain', '" . $timestamp . "'), ('America/Porto_Acre', '" . $timestamp . "'), ('America/Porto_Velho', '" . $timestamp . "'), ('America/Puerto_Rico', '" . $timestamp . "'), ('America/Rainy_River', '" . $timestamp . "'), ('America/Rankin_Inlet', '" . $timestamp . "'), ('America/Recife', '" . $timestamp . "'), ('America/Regina', '" . $timestamp . "'), ('America/Resolute', '" . $timestamp . "'), ('America/Rio_Branco', '" . $timestamp . "'), ('America/Rosario', '" . $timestamp . "'), ('America/Santa_Isabel', '" . $timestamp . "'), ('America/Santarem', '" . $timestamp . "'), ('America/Santiago', '" . $timestamp . "'), ('America/Santo_Domingo', '" . $timestamp . "'), ('America/Sao_Paulo', '" . $timestamp . "'), ('America/Scoresbysund', '" . $timestamp . "'), ('America/Shiprock', '" . $timestamp . "'), ('America/Sitka', '" . $timestamp . "'), ('America/St_Barthelemy', '" . $timestamp . "'), ('America/St_Johns', '" . $timestamp . "'), ('America/St_Kitts', '" . $timestamp . "'), ('America/St_Lucia', '" . $timestamp . "'), ('America/St_Thomas', '" . $timestamp . "'), ('America/St_Vincent', '" . $timestamp . "'), ('America/Swift_Current', '" . $timestamp . "'), ('America/Tegucigalpa', '" . $timestamp . "'), ('America/Thule', '" . $timestamp . "'), ('America/Thunder_Bay', '" . $timestamp . "'), ('America/Tijuana', '" . $timestamp . "'), ('America/Toronto', '" . $timestamp . "'), ('America/Tortola', '" . $timestamp . "'), ('America/Vancouver', '" . $timestamp . "'), ('America/Virgin', '" . $timestamp . "'), ('America/Whitehorse', '" . $timestamp . "'), ('America/Winnipeg', '" . $timestamp . "'), ('America/Yakutat', '" . $timestamp . "'), ('America/Yellowknife', '" . $timestamp . "'), ('Antarctica/Casey', '" . $timestamp . "'), ('Antarctica/Davis', '" . $timestamp . "'), ('Antarctica/DumontDUrville', '" . $timestamp . "'), ('Antarctica/Macquarie', '" . $timestamp . "'), ('Antarctica/Mawson', '" . $timestamp . "'), ('Antarctica/McMurdo', '" . $timestamp . "'), ('Antarctica/Palmer', '" . $timestamp . "'), ('Antarctica/Rothera', '" . $timestamp . "'), ('Antarctica/South_Pole', '" . $timestamp . "'), ('Antarctica/Syowa', '" . $timestamp . "'), ('Antarctica/Vostok', '" . $timestamp . "'), ('Arctic/Longyearbyen', '" . $timestamp . "'), ('Asia/Aden', '" . $timestamp . "'), ('Asia/Almaty', '" . $timestamp . "'), ('Asia/Amman', '" . $timestamp . "'), ('Asia/Anadyr', '" . $timestamp . "'), ('Asia/Aqtau', '" . $timestamp . "'), ('Asia/Aqtobe', '" . $timestamp . "'), ('Asia/Ashgabat', '" . $timestamp . "'), ('Asia/Ashkhabad', '" . $timestamp . "'), ('Asia/Baghdad', '" . $timestamp . "'), ('Asia/Bahrain', '" . $timestamp . "'), ('Asia/Baku', '" . $timestamp . "'), ('Asia/Bangkok', '" . $timestamp . "'), ('Asia/Beirut', '" . $timestamp . "'), ('Asia/Bishkek', '" . $timestamp . "'), ('Asia/Brunei', '" . $timestamp . "'), ('Asia/Calcutta', '" . $timestamp . "'), ('Asia/Choibalsan', '" . $timestamp . "'), ('Asia/Chongqing', '" . $timestamp . "'), ('Asia/Chungking', '" . $timestamp . "'), ('Asia/Colombo', '" . $timestamp . "'), ('Asia/Dacca', '" . $timestamp . "'), ('Asia/Damascus', '" . $timestamp . "'), ('Asia/Dhaka', '" . $timestamp . "'), ('Asia/Dili', '" . $timestamp . "'), ('Asia/Dubai', '" . $timestamp . "'), ('Asia/Dushanbe', '" . $timestamp . "'), ('Asia/Gaza', '" . $timestamp . "'), ('Asia/Harbin', '" . $timestamp . "'), ('Asia/Hebron', '" . $timestamp . "'), ('Asia/Ho_Chi_Minh', '" . $timestamp . "'), ('Asia/Hong_Kong', '" . $timestamp . "'), ('Asia/Hovd', '" . $timestamp . "'), ('Asia/Irkutsk', '" . $timestamp . "'), ('Asia/Istanbul', '" . $timestamp . "'), ('Asia/Jakarta', '" . $timestamp . "'), ('Asia/Jayapura', '" . $timestamp . "'), ('Asia/Jerusalem', '" . $timestamp . "'), ('Asia/Kabul', '" . $timestamp . "'), ('Asia/Kamchatka', '" . $timestamp . "'), ('Asia/Karachi', '" . $timestamp . "'), ('Asia/Kashgar', '" . $timestamp . "'), ('Asia/Kathmandu', '" . $timestamp . "'), ('Asia/Katmandu', '" . $timestamp . "'), ('Asia/Khandyga', '" . $timestamp . "'), ('Asia/Kolkata', '" . $timestamp . "'), ('Asia/Krasnoyarsk', '" . $timestamp . "'), ('Asia/Kuala_Lumpur', '" . $timestamp . "'), ('Asia/Kuching', '" . $timestamp . "'), ('Asia/Kuwait', '" . $timestamp . "'), ('Asia/Macao', '" . $timestamp . "'), ('Asia/Macau', '" . $timestamp . "'), ('Asia/Magadan', '" . $timestamp . "'), ('Asia/Makassar', '" . $timestamp . "'), ('Asia/Manila', '" . $timestamp . "'), ('Asia/Muscat', '" . $timestamp . "'), ('Asia/Nicosia', '" . $timestamp . "'), ('Asia/Novokuznetsk', '" . $timestamp . "'), ('Asia/Novosibirsk', '" . $timestamp . "'), ('Asia/Omsk', '" . $timestamp . "'), ('Asia/Oral', '" . $timestamp . "'), ('Asia/Phnom_Penh', '" . $timestamp . "'), ('Asia/Pontianak', '" . $timestamp . "'), ('Asia/Pyongyang', '" . $timestamp . "'), ('Asia/Qatar', '" . $timestamp . "'), ('Asia/Qyzylorda', '" . $timestamp . "'), ('Asia/Rangoon', '" . $timestamp . "'), ('Asia/Riyadh', '" . $timestamp . "'), ('Asia/Saigon', '" . $timestamp . "'), ('Asia/Sakhalin', '" . $timestamp . "'), ('Asia/Samarkand', '" . $timestamp . "'), ('Asia/Seoul', '" . $timestamp . "'), ('Asia/Shanghai', '" . $timestamp . "'), ('Asia/Singapore', '" . $timestamp . "'), ('Asia/Taipei', '" . $timestamp . "'), ('Asia/Tashkent', '" . $timestamp . "'), ('Asia/Tbilisi', '" . $timestamp . "'), ('Asia/Tehran', '" . $timestamp . "'), ('Asia/Tel_Aviv', '" . $timestamp . "'), ('Asia/Thimbu', '" . $timestamp . "'), ('Asia/Thimphu', '" . $timestamp . "'), ('Asia/Tokyo', '" . $timestamp . "'), ('Asia/Ujung_Pandang', '" . $timestamp . "'), ('Asia/Ulaanbaatar', '" . $timestamp . "'), ('Asia/Ulan_Bator', '" . $timestamp . "'), ('Asia/Urumqi', '" . $timestamp . "'), ('Asia/Ust-Nera', '" . $timestamp . "'), ('Asia/Vientiane', '" . $timestamp . "'), ('Asia/Vladivostok', '" . $timestamp . "'), ('Asia/Yakutsk', '" . $timestamp . "'), ('Asia/Yekaterinburg', '" . $timestamp . "'), ('Asia/Yerevan', '" . $timestamp . "'), ('Atlantic/Azores', '" . $timestamp . "'), ('Atlantic/Bermuda', '" . $timestamp . "'), ('Atlantic/Canary', '" . $timestamp . "'), ('Atlantic/Cape_Verde', '" . $timestamp . "'), ('Atlantic/Faeroe', '" . $timestamp . "'), ('Atlantic/Faroe', '" . $timestamp . "'), ('Atlantic/Jan_Mayen', '" . $timestamp . "'), ('Atlantic/Madeira', '" . $timestamp . "'), ('Atlantic/Reykjavik', '" . $timestamp . "'), ('Atlantic/South_Georgia', '" . $timestamp . "'), ('Atlantic/St_Helena', '" . $timestamp . "'), ('Atlantic/Stanley', '" . $timestamp . "'), ('Australia/ACT', '" . $timestamp . "'), ('Australia/Adelaide', '" . $timestamp . "'), ('Australia/Brisbane', '" . $timestamp . "'), ('Australia/Broken_Hill', '" . $timestamp . "'), ('Australia/Canberra', '" . $timestamp . "'), ('Australia/Currie', '" . $timestamp . "'), ('Australia/Darwin', '" . $timestamp . "'), ('Australia/Eucla', '" . $timestamp . "'), ('Australia/Hobart', '" . $timestamp . "'), ('Australia/LHI', '" . $timestamp . "'), ('Australia/Lindeman', '" . $timestamp . "'), ('Australia/Lord_Howe', '" . $timestamp . "'), ('Australia/Melbourne', '" . $timestamp . "'), ('Australia/North', '" . $timestamp . "'), ('Australia/NSW', '" . $timestamp . "'), ('Australia/Perth', '" . $timestamp . "'), ('Australia/Queensland', '" . $timestamp . "'), ('Australia/South', '" . $timestamp . "'), ('Australia/Sydney', '" . $timestamp . "'), ('Australia/Tasmania', '" . $timestamp . "'), ('Australia/Victoria', '" . $timestamp . "'), ('Australia/West', '" . $timestamp . "'), ('Australia/Yancowinna', '" . $timestamp . "'), ('Brazil/Acre', '" . $timestamp . "'), ('Brazil/DeNoronha', '" . $timestamp . "'), ('Brazil/East', '" . $timestamp . "'), ('Brazil/West', '" . $timestamp . "'), ('Canada/Atlantic', '" . $timestamp . "'), ('Canada/Central', '" . $timestamp . "'), ('Canada/East-Saskatchewan', '" . $timestamp . "'), ('Canada/Eastern', '" . $timestamp . "'), ('Canada/Mountain', '" . $timestamp . "'), ('Canada/Newfoundland', '" . $timestamp . "'), ('Canada/Pacific', '" . $timestamp . "'), ('Canada/Saskatchewan', '" . $timestamp . "'), ('Canada/Yukon', '" . $timestamp . "'), ('Chile/Continental', '" . $timestamp . "'), ('Chile/EasterIsland', '" . $timestamp . "'), ('Cuba', '" . $timestamp . "'), ('Egypt', '" . $timestamp . "'), ('Eire', '" . $timestamp . "'), ('Europe/Amsterdam', '" . $timestamp . "'), ('Europe/Andorra', '" . $timestamp . "'), ('Europe/Athens', '" . $timestamp . "'), ('Europe/Belfast', '" . $timestamp . "'), ('Europe/Belgrade', '" . $timestamp . "'), ('Europe/Berlin', '" . $timestamp . "'), ('Europe/Bratislava', '" . $timestamp . "'), ('Europe/Brussels', '" . $timestamp . "'), ('Europe/Bucharest', '" . $timestamp . "'), ('Europe/Budapest', '" . $timestamp . "'), ('Europe/Busingen', '" . $timestamp . "'), ('Europe/Chisinau', '" . $timestamp . "'), ('Europe/Copenhagen', '" . $timestamp . "'), ('Europe/Dublin', '" . $timestamp . "'), ('Europe/Gibraltar', '" . $timestamp . "'), ('Europe/Guernsey', '" . $timestamp . "'), ('Europe/Helsinki', '" . $timestamp . "'), ('Europe/Isle_of_Man', '" . $timestamp . "'), ('Europe/Istanbul', '" . $timestamp . "'), ('Europe/Jersey', '" . $timestamp . "'), ('Europe/Kaliningrad', '" . $timestamp . "'), ('Europe/Kiev', '" . $timestamp . "'), ('Europe/Lisbon', '" . $timestamp . "'), ('Europe/Ljubljana', '" . $timestamp . "'), ('Europe/London', '" . $timestamp . "'), ('Europe/Luxembourg', '" . $timestamp . "'), ('Europe/Madrid', '" . $timestamp . "'), ('Europe/Malta', '" . $timestamp . "'), ('Europe/Mariehamn', '" . $timestamp . "'), ('Europe/Minsk', '" . $timestamp . "'), ('Europe/Monaco', '" . $timestamp . "'), ('Europe/Moscow', '" . $timestamp . "'), ('Europe/Nicosia', '" . $timestamp . "'), ('Europe/Oslo', '" . $timestamp . "'), ('Europe/Paris', '" . $timestamp . "'), ('Europe/Podgorica', '" . $timestamp . "'), ('Europe/Prague', '" . $timestamp . "'), ('Europe/Riga', '" . $timestamp . "'), ('Europe/Rome', '" . $timestamp . "'), ('Europe/Samara', '" . $timestamp . "'), ('Europe/San_Marino', '" . $timestamp . "'), ('Europe/Sarajevo', '" . $timestamp . "'), ('Europe/Simferopol', '" . $timestamp . "'), ('Europe/Skopje', '" . $timestamp . "'), ('Europe/Sofia', '" . $timestamp . "'), ('Europe/Stockholm', '" . $timestamp . "'), ('Europe/Tallinn', '" . $timestamp . "'), ('Europe/Tirane', '" . $timestamp . "'), ('Europe/Tiraspol', '" . $timestamp . "'), ('Europe/Uzhgorod', '" . $timestamp . "'), ('Europe/Vaduz', '" . $timestamp . "'), ('Europe/Vatican', '" . $timestamp . "'), ('Europe/Vienna', '" . $timestamp . "'), ('Europe/Vilnius', '" . $timestamp . "'), ('Europe/Volgograd', '" . $timestamp . "'), ('Europe/Warsaw', '" . $timestamp . "'), ('Europe/Zagreb', '" . $timestamp . "'), ('Europe/Zaporozhye', '" . $timestamp . "'), ('Europe/Zurich', '" . $timestamp . "'), ('Greenwich', '" . $timestamp . "'), ('Hongkong', '" . $timestamp . "'), ('Iceland', '" . $timestamp . "'), ('Indian/Antananarivo', '" . $timestamp . "'), ('Indian/Chagos', '" . $timestamp . "'), ('Indian/Christmas', '" . $timestamp . "'), ('Indian/Cocos', '" . $timestamp . "'), ('Indian/Comoro', '" . $timestamp . "'), ('Indian/Kerguelen', '" . $timestamp . "'), ('Indian/Mahe', '" . $timestamp . "'), ('Indian/Maldives', '" . $timestamp . "'), ('Indian/Mauritius', '" . $timestamp . "'), ('Indian/Mayotte', '" . $timestamp . "'), ('Indian/Reunion', '" . $timestamp . "'), ('Iran', '" . $timestamp . "'), ('Israel', '" . $timestamp . "'), ('Jamaica', '" . $timestamp . "'), ('Japan', '" . $timestamp . "'), ('Kwajalein', '" . $timestamp . "'), ('Libya', '" . $timestamp . "'), ('Mexico/BajaNorte', '" . $timestamp . "'), ('Mexico/BajaSur', '" . $timestamp . "'), ('Mexico/General', '" . $timestamp . "'), ('Pacific/Apia', '" . $timestamp . "'), ('Pacific/Auckland', '" . $timestamp . "'), ('Pacific/Chatham', '" . $timestamp . "'), ('Pacific/Chuuk', '" . $timestamp . "'), ('Pacific/Easter', '" . $timestamp . "'), ('Pacific/Efate', '" . $timestamp . "'), ('Pacific/Enderbury', '" . $timestamp . "'), ('Pacific/Fakaofo', '" . $timestamp . "'), ('Pacific/Fiji', '" . $timestamp . "'), ('Pacific/Funafuti', '" . $timestamp . "'), ('Pacific/Galapagos', '" . $timestamp . "'), ('Pacific/Gambier', '" . $timestamp . "'), ('Pacific/Guadalcanal', '" . $timestamp . "'), ('Pacific/Guam', '" . $timestamp . "'), ('Pacific/Honolulu', '" . $timestamp . "'), ('Pacific/Johnston', '" . $timestamp . "'), ('Pacific/Kiritimati', '" . $timestamp . "'), ('Pacific/Kosrae', '" . $timestamp . "'), ('Pacific/Kwajalein', '" . $timestamp . "'), ('Pacific/Majuro', '" . $timestamp . "'), ('Pacific/Marquesas', '" . $timestamp . "'), ('Pacific/Midway', '" . $timestamp . "'), ('Pacific/Nauru', '" . $timestamp . "'), ('Pacific/Niue', '" . $timestamp . "'), ('Pacific/Norfolk', '" . $timestamp . "'), ('Pacific/Noumea', '" . $timestamp . "'), ('Pacific/Pago_Pago', '" . $timestamp . "'), ('Pacific/Palau', '" . $timestamp . "'), ('Pacific/Pitcairn', '" . $timestamp . "'), ('Pacific/Pohnpei', '" . $timestamp . "'), ('Pacific/Ponape', '" . $timestamp . "'), ('Pacific/Port_Moresby', '" . $timestamp . "'), ('Pacific/Rarotonga', '" . $timestamp . "'), ('Pacific/Saipan', '" . $timestamp . "'), ('Pacific/Samoa', '" . $timestamp . "'), ('Pacific/Tahiti', '" . $timestamp . "'), ('Pacific/Tarawa', '" . $timestamp . "'), ('Pacific/Tongatapu', '" . $timestamp . "'), ('Pacific/Truk', '" . $timestamp . "'), ('Pacific/Wake', '" . $timestamp . "'), ('Pacific/Wallis', '" . $timestamp . "'), ('Pacific/Yap', '" . $timestamp . "'), ('Poland', '" . $timestamp . "'), ('Portugal', '" . $timestamp . "'), ('Singapore', '" . $timestamp . "'), ('Turkey', '" . $timestamp . "'), ('US/Alaska', '" . $timestamp . "'), ('US/Aleutian', '" . $timestamp . "'), ('US/Arizona', '" . $timestamp . "'), ('US/Central', '" . $timestamp . "'), ('US/East-Indiana', '" . $timestamp . "'), ('US/Eastern', '" . $timestamp . "'), ('US/Hawaii', '" . $timestamp . "'), ('US/Indiana-Starke', '" . $timestamp . "'), ('US/Michigan', '" . $timestamp . "'), ('US/Mountain', '" . $timestamp . "'), ('US/Pacific', '" . $timestamp . "'), ('US/Pacific-New', '" . $timestamp . "'), ('US/Samoa', '" . $timestamp . "'), ('Zulu', '" . $timestamp . "')");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `dw_servers` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `host` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `protocol` VARCHAR(5) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `port` INT(5) NOT NULL,
            `username` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `api_token` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `hash` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `dw_accounts` INT(10) UNSIGNED NOT NULL,
            `dw_dns_zones` INT(10) UNSIGNED NOT NULL,
            `dw_dns_records` INT(10) UNSIGNED NOT NULL,
            `build_status` TINYINT(1) NOT NULL DEFAULT '0',
            `build_start_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `build_end_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `build_time` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `has_ever_been_built` TINYINT(1) NOT NULL DEFAULT '0',
            `build_status_overall` TINYINT(1) NOT NULL DEFAULT '0',
            `build_start_time_overall` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `build_end_time_overall` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `build_time_overall` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `has_ever_been_built_overall` TINYINT(1) NOT NULL DEFAULT '0',
            `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
            `created_by` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `scheduler` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `slug` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `description` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `interval` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Daily',
            `expression` VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '0 7 * * * *',
            `last_run` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `last_duration` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `next_run` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `sort_order` INT(4) NOT NULL DEFAULT '1',
            `is_running` TINYINT(1) NOT NULL DEFAULT '0',
            `active` TINYINT(1) NOT NULL DEFAULT '1',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
         ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $stmt = $pdo->prepare("
        INSERT INTO scheduler
        (`name`, description, `interval`, expression, slug, sort_order, is_running, active, insert_time)
         VALUES
        (:temp_task_scheduler_one, 'Retrieves information for domains in the queue and adds them to DomainMOD.', 'Every 5 Minutes', '*/5 * * * * *', 'domain-queue', '10', '0', '1', '" . $timestamp . "')");
    $stmt->bindValue('temp_task_scheduler_one', $temp_task_scheduler_one, PDO::PARAM_STR);
    $stmt->execute();

    $stmt = $pdo->prepare("
        INSERT INTO scheduler
        (`name`, description, `interval`, expression, slug, sort_order, is_running, active, insert_time)
         VALUES
        (:temp_task_scheduler_two, 'Sends an email out to everyone who\'s subscribed, letting them know of upcoming Domain & SSL Certificate expirations.<BR><BR>Users can subscribe via their User Profile.<BR><BR>Administrators can set the FROM email address and the number of days in the future to display in the email via System Settings.', 'Daily', '0 0 * * * *', 'expiration-email', '20', '0', '1', '" . $timestamp . "')");
    $stmt->bindValue('temp_task_scheduler_two', $temp_task_scheduler_two, PDO::PARAM_STR);
    $stmt->execute();

    $stmt = $pdo->prepare("
        INSERT INTO scheduler
        (`name`, description, `interval`, expression, slug, sort_order, is_running, active, insert_time)
         VALUES
        (:temp_task_scheduler_three, 'Retrieves the current currency conversion rates and updates the entire system, which keeps all of the financial information in DomainMOD accurate and up-to-date.<BR><BR>Users can set their default currency via their User Profile.', 'Daily', '0 0 * * * *', 'update-conversion-rates', '40', '0', '1', '" . $timestamp . "')");
    $stmt->bindValue('temp_task_scheduler_three', $temp_task_scheduler_three, PDO::PARAM_STR);
    $stmt->execute();

    $stmt = $pdo->prepare("
        INSERT INTO scheduler
        (`name`, description, `interval`, expression, slug, sort_order, is_running, active, insert_time)
         VALUES
        (:temp_task_scheduler_four, '" . "<" . "em>Domains:" . "<" . "/em> Converts all domain entries to lowercase." . "<" . "BR>" . "<" . "BR> " . "<" . "em>TLDs:" . "<" . "/em> Updates all TLD entries in the database to ensure their accuracy." . "<" . "BR>" . "<" . "BR> " . "<" . "em>Segments:" . "<" . "/em> Compares the Segment data to the domain database and records the status of each domain. This keeps the Segment filtering data up-to-date and running smoothly." . "<" . "BR>" . "<" . "BR>" . "<" . "em>Fees:" . "<" . "/em> Cross-references the Domain, SSL Certificate, and fee tables, making sure that everything is accurate. It also deletes all unused fees.', 'Daily', '0 0 * * * *', 'cleanup', '60', '0', '1', '" . $timestamp . "')");
    $stmt->bindValue('temp_task_scheduler_four', $temp_task_scheduler_four, PDO::PARAM_STR);
    $stmt->execute();

    $stmt = $pdo->prepare("
        INSERT INTO scheduler
        (`name`, description, `interval`, expression, slug, sort_order, is_running, active, insert_time)
         VALUES
        (:temp_task_scheduler_five, 'Checks to see if there is a newer version of DomainMOD available to download.', 'Daily', '0 0 * * * *', 'check-new-version', '80', '0', '1', '" . $timestamp . "')");
    $stmt->bindValue('temp_task_scheduler_five', $temp_task_scheduler_five, PDO::PARAM_STR);
    $stmt->execute();

    $stmt = $pdo->prepare("
        INSERT INTO scheduler
        (`name`, description, `interval`, expression, slug, sort_order, is_running, active, insert_time)
         VALUES
        (:temp_task_scheduler_six, 'Rebuilds the Data Warehouse so that you have the most up-to-date information available.', 'Daily', '0 0 * * * *', 'data-warehouse-build', '100', '0', '1', '" . $timestamp . "')");
    $stmt->bindValue('temp_task_scheduler_six', $temp_task_scheduler_six, PDO::PARAM_STR);
    $stmt->execute();

    // Update tasks that run daily
    $cron = \Cron\CronExpression::factory('0 7 * * * *');
    $next_run = $cron->getNextRunDate()->format('Y-m-d H:i:s');

    $pdo->query("
        UPDATE scheduler
        SET next_run = '" . $next_run . "'
        WHERE `interval` = 'Daily'");

    // Update tasks that run every 5 minutes
    $cron = \Cron\CronExpression::factory('*/5 * * * * *');
    $next_run = $cron->getNextRunDate()->format('Y-m-d H:i:s');

    $pdo->query("
        UPDATE scheduler
        SET next_run = '" . $next_run . "'
        WHERE `interval` = 'Every 5 Minutes'");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `api_registrars` (
            `id` TINYINT(3) UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `req_account_username` TINYINT(1) NOT NULL DEFAULT '0',
            `req_account_password` TINYINT(1) NOT NULL DEFAULT '0',
            `req_account_id` TINYINT(1) NOT NULL DEFAULT '0',
            `req_reseller_id` TINYINT(1) NOT NULL DEFAULT '0',
            `req_api_app_name` TINYINT(1) NOT NULL DEFAULT '0',
            `req_api_key` TINYINT(1) NOT NULL DEFAULT '0',
            `req_api_secret` TINYINT(1) NOT NULL DEFAULT '0',
            `req_ip_address` TINYINT(1) NOT NULL DEFAULT '0',
            `lists_domains` TINYINT(1) NOT NULL DEFAULT '0',
            `ret_expiry_date` TINYINT(1) NOT NULL DEFAULT '0',
            `ret_dns_servers` TINYINT(1) NOT NULL DEFAULT '0',
            `ret_privacy_status` TINYINT(1) NOT NULL DEFAULT '0',
            `ret_autorenewal_status` TINYINT(1) NOT NULL DEFAULT '0',
            `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        INSERT INTO api_registrars
        (`name`, req_account_username, req_account_password, req_account_id, req_reseller_id, req_api_app_name,
         req_api_key, req_api_secret, req_ip_address, lists_domains, ret_expiry_date, ret_dns_servers,
         ret_privacy_status, ret_autorenewal_status, notes, insert_time)
         VALUES
        ('Above.com', '0', '0', '0', '0', '0', '1', '0', '0', '1', '1', '1', '1', '1', '', '" . $timestamp . "'),
        ('DNSimple', '0', '0', '0', '0', '0', '1', '0', '0', '1', '1', '1', '1', '1', '', '" . $timestamp . "'),
        ('DreamHost', '0', '0', '0', '0', '0', '1', '0', '0', '1', '1', '1', '0', '1', 'DreamHost does not currently allow the WHOIS privacy status of a domain to be retrieved via their API, so all domains added to the Domain Queue from a DreamHost account will have their WHOIS privacy status set to No by default.', '" . $timestamp . "'),
        ('Dynadot', '0', '0', '0', '0', '0', '1', '0', '1', '1', '1', '1', '1', '1', '', '" . $timestamp . "'),
        ('eNom', '1', '1', '0', '0', '0', '0', '0', '0', '1', '1', '1', '1', '1', '', '" . $timestamp . "'),
        ('Fabulous', '1', '1', '0', '0', '0', '0', '0', '0', '1', '1', '1', '1', '1', '', '" . $timestamp . "'),
        ('Freenom', '1', '1', '0', '0', '0', '0', '0', '0', '1', '1', '1', '1', '1', 'Freenom currently only gives API access to reseller accounts.', '" . $timestamp . "'),
        ('Gandi', '0', '0', '0', '0', '0', '1', '0', '0', '1', '1', '1', '0', '1', 'Gandi does not currently allow the WHOIS privacy status of a domain to be retrieved via their API, so all domains added to the Domain Queue from a Gandi account will have their WHOIS privacy status set to No by default.', '" . $timestamp . "'),
        ('GoDaddy', '0', '0', '0', '0', '0', '1', '1', '0', '1', '1', '1', '1', '1', 'When retrieving your list of domains from GoDaddy, the current limit is 1,000 domains. If you have more than this you should export the full list of domains from GoDaddy and paste it into the <strong>Domains to add</strong> field when adding domains via the Domain Queue.', '" . $timestamp . "'),
        ('Internet.bs', '0', '0', '0', '0', '0', '1', '1', '0', '1', '1', '1', '1', '1', '', '" . $timestamp . "'),
        ('Name.com', '1', '0', '0', '0', '0', '1', '0', '0', '1', '1', '1', '1', '1', '', '" . $timestamp . "'),
        ('NameBright', '1', '0', '0', '0', '1', '0', '1', '0', '1', '1', '1', '1', '1', '', '" . $timestamp . "'),
        ('Namecheap', '1', '0', '0', '0', '0', '1', '0', '1', '1', '1', '1', '1', '1', '', '" . $timestamp . "'),
        ('NameSilo', '0', '0', '0', '0', '0', '1', '0', '0', '1', '1', '1', '1', '1', 'NameSilo\'s domains have 6 possible statuses: Active, Expired (grace period), Expired (restore period), Expired (pending delete), Inactive, and Pending Outbound Transfer<BR><BR>When retrieving your list of domains via the API, <STRONG>Inactive</STRONG> domains are not returned.<BR><BR>When retrieving the details of a specific domain via the API, <STRONG>Inactive</STRONG> and <STRONG>Expired (pending delete)</STRONG> domains will not return any data.', '" . $timestamp . "'),
        ('OpenSRS', '1', '0', '0', '0', '0', '1', '0', '1', '1', '1', '1', '1', '1', '', '" . $timestamp . "'),
        ('ResellerClub', '0', '0', '0', '1', '0', '1', '0', '0', '0', '1', '1', '1', '0', 'ResellerClub does not allow users to retrieve a list of their domains via the API, nor do they return the Auto Renewal status when retrieving the details of a domain. All domains imported via the API will have their Auto Renewal status set to No by default.', '" . $timestamp . "'),
        ('Cloudflare', '1', '0', '1', '0', '0', '1', '0', '0', '1', '1', '1', '1', '1', '', '" . $timestamp . "')");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `goal_activity` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `type` VARCHAR(7) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'unknown',
            `old_version` VARCHAR(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'unknown',
            `new_version` VARCHAR(10) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'unknown',
            `ip` VARCHAR(45) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'unknown',
            `agent` LONGTEXT COLLATE utf8_unicode_ci NOT NULL,
            `language` VARCHAR(100) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'unknown',
            `new_activity` TINYINT(1) NOT NULL DEFAULT '1',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `log` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `area` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `level` VARCHAR(9) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `message` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `extra` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `url` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $pdo->query("
        CREATE TABLE IF NOT EXISTS `settings` (
            `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `full_url` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'http://',
            `db_version` VARCHAR(12) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `upgrade_available` TINYINT(1) NOT NULL DEFAULT '0',
            `email_address` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `large_mode` TINYINT(1) NOT NULL DEFAULT '0',
            `default_category_domains` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `default_category_ssl` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `default_dns` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `default_host` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `default_ip_address_domains` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `default_ip_address_ssl` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `default_owner_domains` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `default_owner_ssl` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `default_registrar` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `default_registrar_account` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `default_ssl_provider_account` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `default_ssl_type` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `default_ssl_provider` INT(10) UNSIGNED NOT NULL DEFAULT '0',
            `expiration_days` INT(3) NOT NULL DEFAULT '60',
            `email_signature` INT(10) UNSIGNED NOT NULL DEFAULT '1',
            `currency_converter` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'erh',
            `use_smtp` TINYINT(1) NOT NULL DEFAULT '0',
            `smtp_server` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `smtp_protocol` VARCHAR(3) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'tls',
            `smtp_port` VARCHAR(5) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '587',
            `smtp_email_address` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `smtp_username` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `smtp_password` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `debug_mode` TINYINT(1) NOT NULL DEFAULT '0',
            `local_php_log` TINYINT(1) NOT NULL DEFAULT '0',
            `insert_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            `update_time` DATETIME NOT NULL DEFAULT '1970-01-01 00:00:00',
            PRIMARY KEY  (`id`)
        ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1");

    $full_url = substr($_SERVER["HTTP_REFERER"], 0, -1);

    $stmt = $pdo->prepare("
        INSERT INTO `settings`
        (`full_url`, `db_version`, `email_address`, `insert_time`)
        VALUES
        (:full_url, :software_version, :new_system_email, :timestamp)");
    $stmt->bindValue('full_url', $full_url, PDO::PARAM_STR);
    $software_version = SOFTWARE_VERSION;
    $stmt->bindValue('software_version', $software_version, PDO::PARAM_STR);
    $stmt->bindValue('new_system_email', $_SESSION['new_system_email'], PDO::PARAM_STR);
    $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
    $stmt->execute();

    // Without this, the "DomainMOD is not yet installed" message will continue to display after installation. The header isn't displayed on the install file, which is when this normally unsets.
    unset($_SESSION['s_message_danger']);

    unset($_SESSION['new_system_email']);
    unset($_SESSION['new_admin_email']);
    unset($_SESSION['s_installation_mode']);

    $goal->installation();

    $pdo->commit();

    $_SESSION['s_message_success'] .= sprintf(_('%s has been successfully installed and you should now delete the %s folder'), SOFTWARE_TITLE, '/install/') . "<BR><BR>" . sprintf(_("The default username and password are %s, and you'll be prompted to change the password after logging in"), '"admin"') . "<BR>";

    header("Location: ../");
    exit;

} catch (Exception $e) {

    $pdo->rollback();

    $log_message = 'Installation failed';
    $log_extra = array('Error' => $e);
    $log->emergency($log_message, $log_extra);

    $_SESSION['s_message_danger'] .= $log_message . '<BR>';

    throw $e;

}
