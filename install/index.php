<?php
/**
 * /install/index.php
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
include("../_includes/start-session.inc.php");
include("../_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

require DIR_ROOT . 'vendor/autoload.php';

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$time = new DomainMOD\Time();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "database.inc.php");

$system->installCheck($connection);

if (mysqli_num_rows(mysqli_query($connection, "SHOW TABLES LIKE '" . settings . "'"))) {

    $_SESSION['s_message_danger'] .= $software_title . " is already installed<BR><BR>You should delete the /install/ folder<BR>";

    header("Location: ../");
    exit;

} else {

    $_SESSION['s_installation_mode'] = '1';

    $sql = "ALTER DATABASE " . $dbname . "
            CHARACTER SET utf8
            DEFAULT CHARACTER SET utf8
            COLLATE utf8_unicode_ci
            DEFAULT COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `creation_types` (
                `id` TINYINT(2) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `insert_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO creation_types
            (`name`, insert_time)
             VALUES
            ('Installation', '" . $time->stamp() . "'),
            ('Manual', '" . $time->stamp() . "'),
            ('Bulk Updater', '" . $time->stamp() . "'),
            ('Manual or Bulk Updater', '" . $time->stamp() . "'),
            ('Queue', '" . $time->stamp() . "')";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
    
    $creation_type_id_installation = $system->getCreationTypeId($connection, 'Installation');
    $creation_type_id_manual = $system->getCreationTypeId($connection, 'Manual');

    $sql = "CREATE TABLE IF NOT EXISTS `users` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `first_name` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `last_name` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `username` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `email_address` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `password` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `new_password` INT(1) NOT NULL DEFAULT '1',
                `admin` INT(1) NOT NULL DEFAULT '0',
                `active` INT(1) NOT NULL DEFAULT '1',
                `number_of_logins` INT(10) NOT NULL DEFAULT '0',
                `last_login` DATETIME NOT NULL,
                `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
                `created_by` INT(10) NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO `users`
            (`first_name`, `last_name`, `username`, `email_address`, `password`, `admin`, `creation_type_id`, `insert_time`) VALUES
            ('Domain', 'Administrator', 'admin', '" . $_SESSION['new_install_email'] . "', '*4ACFE3202A5FF5CF467898FC58AAB1D615029441', '1', '" . $creation_type_id_installation . "', '" . $time->stamp() . "');";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
    
    $sql = "CREATE TABLE IF NOT EXISTS `user_settings` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `user_id` INT(10) NOT NULL,
                `default_currency` VARCHAR(3) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `default_timezone` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Canada/Pacific',
                `default_category_domains` INT(10) NOT NULL DEFAULT '0',
                `default_category_ssl` INT(10) NOT NULL DEFAULT '0',
                `default_dns` INT(10) NOT NULL DEFAULT '0',
                `default_host` INT(10) NOT NULL DEFAULT '0',
                `default_ip_address_domains` INT(10) NOT NULL DEFAULT '0',
                `default_ip_address_ssl` INT(10) NOT NULL DEFAULT '0',
                `default_owner_domains` INT(10) NOT NULL DEFAULT '0',
                `default_owner_ssl` INT(10) NOT NULL DEFAULT '0',
                `default_registrar` INT(10) NOT NULL DEFAULT '0',
                `default_registrar_account` INT(10) NOT NULL DEFAULT '0',
                `default_ssl_provider_account` INT(10) NOT NULL DEFAULT '0',
                `default_ssl_type` INT(10) NOT NULL DEFAULT '0',
                `default_ssl_provider` INT(10) NOT NULL DEFAULT '0',
                `expiration_emails` INT(1) NOT NULL DEFAULT '1',
                `number_of_domains` INT(5) NOT NULL DEFAULT '50',
                `number_of_ssl_certs` INT(5) NOT NULL DEFAULT '50',
                `display_domain_owner` INT(1) NOT NULL DEFAULT '0',
                `display_domain_registrar` INT(1) NOT NULL DEFAULT '0',
                `display_domain_account` INT(1) NOT NULL DEFAULT '1',
                `display_domain_expiry_date` INT(1) NOT NULL DEFAULT '1',
                `display_domain_category` INT(1) NOT NULL DEFAULT '1',
                `display_domain_dns` INT(1) NOT NULL DEFAULT '1',
                `display_domain_host` INT(1) NOT NULL DEFAULT '0',
                `display_domain_ip` INT(1) NOT NULL DEFAULT '0',
                `display_domain_tld` INT(1) NOT NULL DEFAULT '1',
                `display_domain_fee` INT(1) NOT NULL DEFAULT '1',
                `display_ssl_owner` INT(1) NOT NULL DEFAULT '1',
                `display_ssl_provider` INT(1) NOT NULL DEFAULT '0',
                `display_ssl_account` INT(1) NOT NULL DEFAULT '1',
                `display_ssl_domain` INT(1) NOT NULL DEFAULT '1',
                `display_ssl_type` INT(1) NOT NULL DEFAULT '1',
                `display_ssl_expiry_date` INT(1) NOT NULL DEFAULT '1',
                `display_ssl_ip` INT(1) NOT NULL DEFAULT '0',
                `display_ssl_category` INT(1) NOT NULL DEFAULT '0',
                `display_ssl_fee` INT(1) NOT NULL DEFAULT '0',
                `display_inactive_assets` INT(1) NOT NULL DEFAULT '1',
                `display_dw_intro_page` INT(1) NOT NULL DEFAULT '1',
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "SELECT id
            FROM users";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    while ($row = mysqli_fetch_object($result)) {

        $temp_user_id = $row->id;

    }

    $sql_temp = "INSERT INTO user_settings
                 (user_id, default_currency, insert_time) VALUES
                 ('$temp_user_id', 'USD', '" . $time->stamp() . "');";
    $result_temp = mysqli_query($connection, $sql_temp) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `categories` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(150) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `stakeholder` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
                `created_by` INT(10) NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO `categories`
            (`name`, `stakeholder`, `creation_type_id`, `insert_time`) VALUES
            ('[no category]', '[no stakeholder]', '" . $creation_type_id_installation . "', '" . $time->stamp() . "');";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `hosting` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `url` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
                `created_by` INT(10) NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO `hosting`
            (`name`, `creation_type_id`, `insert_time`) VALUES
            ('[no hosting]', '" . $creation_type_id_installation . "', '" . $time->stamp() . "');";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `owners` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
                `created_by` INT(10) NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`),
                KEY `name` (`name`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO `owners`
            (`name`, `creation_type_id`, `insert_time`) VALUES
            ('[no owner]', '" . $creation_type_id_installation . "', '" . $time->stamp() . "');";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `currencies` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `currency` VARCHAR(4) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `name` VARCHAR(75) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `symbol` VARCHAR(4) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `symbol_order` INT(1) NOT NULL DEFAULT '0',
                `symbol_space` INT(1) NOT NULL DEFAULT '0',
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO currencies
            (`name`, currency, symbol, insert_time) VALUES
            ('Albania Lek', 'ALL', 'Lek', '" . $time->stamp() . "'),
            ('Afghanistan Afghani', 'AFN', '؋', '" . $time->stamp() . "'),
            ('Argentina Peso', 'ARS', '$', '" . $time->stamp() . "'),
            ('Aruba Guilder', 'AWG', 'ƒ', '" . $time->stamp() . "'),
            ('Australia Dollar', 'AUD', '$', '" . $time->stamp() . "'),
            ('Azerbaijan New Manat', 'AZN', '" . 'ман' . "', '" . $time->stamp() . "'),
            ('Bahamas Dollar', 'BSD', '$', '" . $time->stamp() . "'),
            ('Barbados Dollar', 'BBD', '$', '" . $time->stamp() . "'),
            ('Belarus Ruble', 'BYR', 'p.', '" . $time->stamp() . "'),
            ('Belize Dollar', 'BZD', 'BZ$', '" . $time->stamp() . "'),
            ('Bermuda Dollar', 'BMD', '$', '" . $time->stamp() . "'),
            ('Bolivia Boliviano', 'BOB', '\$b', '" . $time->stamp() . "'),
            ('Bosnia and Herzegovina Convertible Marka', 'BAM', 'KM', '" . $time->stamp() . "'),
            ('Botswana Pula', 'BWP', 'P', '" . $time->stamp() . "'),
            ('Bulgaria Lev', 'BGN', 'лв', '" . $time->stamp() . "'),
            ('Brazil Real', 'BRL', 'R$', '" . $time->stamp() . "'),
            ('Brunei Darussalam Dollar', 'BND', '$', '" . $time->stamp() . "'),
            ('Cambodia Riel', 'KHR', '៛', '" . $time->stamp() . "'),
            ('Canada Dollar', 'CAD', '$', '" . $time->stamp() . "'),
            ('Cayman Islands Dollar', 'KYD', '$', '" . $time->stamp() . "'),
            ('Chile Peso', 'CLP', '$', '" . $time->stamp() . "'),
            ('China Yuan Renminbi', 'CNY', '¥', '" . $time->stamp() . "'),
            ('Colombia Peso', 'COP', '$', '" . $time->stamp() . "'),
            ('Costa Rica Colon', 'CRC', '₡', '" . $time->stamp() . "'),
            ('Croatia Kuna', 'HRK', 'kn', '" . $time->stamp() . "'),
            ('Cuba Peso', 'CUP', '₱', '" . $time->stamp() . "'),
            ('Czech Republic Koruna', 'CZK', 'Kč', '" . $time->stamp() . "'),
            ('Denmark Krone', 'DKK', 'kr', '" . $time->stamp() . "'),
            ('Dominican Republic Peso', 'DOP', 'RD$', '" . $time->stamp() . "'),
            ('East Caribbean Dollar', 'XCD', '$', '" . $time->stamp() . "'),
            ('Egypt Pound', 'EGP', '£', '" . $time->stamp() . "'),
            ('El Salvador Colon', 'SVC', '$', '" . $time->stamp() . "'),
            ('Estonia Kroon', 'EEK', 'kr', '" . $time->stamp() . "'),
            ('Euro Member Countries', 'EUR', '€', '" . $time->stamp() . "'),
            ('Falkland Islands (Malvinas) Pound', 'FKP', '£', '" . $time->stamp() . "'),
            ('Fiji Dollar', 'FJD', '$', '" . $time->stamp() . "'),
            ('Ghana Cedis', 'GHC', '¢', '" . $time->stamp() . "'),
            ('Gibraltar Pound', 'GIP', '£', '" . $time->stamp() . "'),
            ('Guatemala Quetzal', 'GTQ', 'Q', '" . $time->stamp() . "'),
            ('Guernsey Pound', 'GGP', '£', '" . $time->stamp() . "'),
            ('Guyana Dollar', 'GYD', '$', '" . $time->stamp() . "'),
            ('Honduras Lempira', 'HNL', 'L', '" . $time->stamp() . "'),
            ('Hong Kong Dollar', 'HKD', '$', '" . $time->stamp() . "'),
            ('Hungary Forint', 'HUF', 'Ft', '" . $time->stamp() . "'),
            ('Iceland Krona', 'ISK', 'kr', '" . $time->stamp() . "'),
            ('India Rupee', 'INR', 'Rs', '" . $time->stamp() . "'),
            ('Indonesia Rupiah', 'IDR', 'Rp', '" . $time->stamp() . "'),
            ('Iran Rial', 'IRR', '﷼', '" . $time->stamp() . "'),
            ('Isle of Man Pound', 'IMP', '£', '" . $time->stamp() . "'),
            ('Israel Shekel', 'ILS', '₪', '" . $time->stamp() . "'),
            ('Jamaica Dollar', 'JMD', 'J$', '" . $time->stamp() . "'),
            ('Japan Yen', 'JPY', '¥', '" . $time->stamp() . "'),
            ('Jersey Pound', 'JEP', '£', '" . $time->stamp() . "'),
            ('Kazakhstan Tenge', 'KZT', 'лв', '" . $time->stamp() . "'),
            ('Korea (North) Won', 'KPW', '₩', '" . $time->stamp() . "'),
            ('Korea (South) Won', 'KRW', '₩', '" . $time->stamp() . "'),
            ('Kyrgyzstan Som', 'KGS', 'лв', '" . $time->stamp() . "'),
            ('Laos Kip', 'LAK', '₭', '" . $time->stamp() . "'),
            ('Latvia Lat', 'LVL', 'Ls', '" . $time->stamp() . "'),
            ('Lebanon Pound', 'LBP', '£', '" . $time->stamp() . "'),
            ('Liberia Dollar', 'LRD', '$', '" . $time->stamp() . "'),
            ('Lithuania Litas', 'LTL', 'Lt', '" . $time->stamp() . "'),
            ('Macedonia Denar', 'MKD', 'ден', '" . $time->stamp() . "'),
            ('Malaysia Ringgit', 'RM', 'RM', '" . $time->stamp() . "'),
            ('Mauritius Rupee', 'MUR', '₨', '" . $time->stamp() . "'),
            ('Mexico Peso', 'MXN', '$', '" . $time->stamp() . "'),
            ('Mongolia Tughrik', 'MNT', '₮', '" . $time->stamp() . "'),
            ('Mozambique Metical', 'MZN', 'MT', '" . $time->stamp() . "'),
            ('Namibia Dollar', 'NAD', '$', '" . $time->stamp() . "'),
            ('Nepal Rupee', 'NPR', '₨', '" . $time->stamp() . "'),
            ('Netherlands Antilles Guilder', 'ANG', 'ƒ', '" . $time->stamp() . "'),
            ('New Zealand Dollar', 'NZD', '$', '" . $time->stamp() . "'),
            ('Nicaragua Cordoba', 'NIO', 'C$', '" . $time->stamp() . "'),
            ('Nigeria Naira', 'NGN', '₦', '" . $time->stamp() . "'),
            ('Norway Krone', 'NOK', 'kr', '" . $time->stamp() . "'),
            ('Oman Rial', 'OMR', '﷼', '" . $time->stamp() . "'),
            ('Pakistan Rupee', 'PKR', '₨', '" . $time->stamp() . "'),
            ('Panama Balboa', 'PAB', 'B/.', '" . $time->stamp() . "'),
            ('Paraguay Guarani', 'PYG', 'Gs', '" . $time->stamp() . "'),
            ('Peru Nuevo Sol', 'PEN', 'S/.', '" . $time->stamp() . "'),
            ('Philippines Peso', 'PHP', '₱', '" . $time->stamp() . "'),
            ('Poland Zloty', 'PLN', 'zł', '" . $time->stamp() . "'),
            ('Qatar Riyal', 'QAR', '﷼', '" . $time->stamp() . "'),
            ('Romania New Leu', 'RON', 'lei', '" . $time->stamp() . "'),
            ('Russia Ruble', 'RUB', 'руб', '" . $time->stamp() . "'),
            ('Saint Helena Pound', 'SHP', '£', '" . $time->stamp() . "'),
            ('Saudi Arabia Riyal', 'SAR', '﷼', '" . $time->stamp() . "'),
            ('Serbia Dinar', 'RSD', 'Дин.', '" . $time->stamp() . "'),
            ('Seychelles Rupee', 'SCR', '₨', '" . $time->stamp() . "'),
            ('Singapore Dollar', 'SGD', '$', '" . $time->stamp() . "'),
            ('Solomon Islands Dollar', 'SBD', '$', '" . $time->stamp() . "'),
            ('Somalia Shilling', 'SOS', 'S', '" . $time->stamp() . "'),
            ('South Africa Rand', 'ZAR', 'R', '" . $time->stamp() . "'),
            ('Sri Lanka Rupee', 'LKR', '₨', '" . $time->stamp() . "'),
            ('Sweden Krona', 'SEK', 'kr', '" . $time->stamp() . "'),
            ('Switzerland Franc', 'CHF', 'CHF', '" . $time->stamp() . "'),
            ('Suriname Dollar', 'SRD', '$', '" . $time->stamp() . "'),
            ('Syria Pound', 'SYP', '£', '" . $time->stamp() . "'),
            ('Taiwan New Dollar', 'TWD', 'NT$', '" . $time->stamp() . "'),
            ('Thailand Baht', 'THB', '฿', '" . $time->stamp() . "'),
            ('Trinidad and Tobago Dollar', 'TTD', 'TT$', '" . $time->stamp() . "'),
            ('Turkey Lira', 'TRY', '₤', '" . $time->stamp() . "'),
            ('Tuvalu Dollar', 'TVD', '$', '" . $time->stamp() . "'),
            ('Ukraine Hryvna', 'UAH', '₴', '" . $time->stamp() . "'),
            ('United Kingdom Pound', 'GBP', '£', '" . $time->stamp() . "'),
            ('United States Dollar', 'USD', '$', '" . $time->stamp() . "'),
            ('Uruguay Peso', 'UYU', '\$U', '" . $time->stamp() . "'),
            ('Uzbekistan Som', 'UZS', 'лв', '" . $time->stamp() . "'),
            ('Venezuela Bolivar', 'VEF', 'Bs', '" . $time->stamp() . "'),
            ('Viet Nam Dong', 'VND', '₫', '" . $time->stamp() . "'),
            ('Yemen Rial', 'YER', '﷼', '" . $time->stamp() . "'),
            ('Zimbabwe Dollar', 'ZWD', 'Z$', '" . $time->stamp() . "'),
            ('Emirati Dirham', 'AED', 'د.إ', '" . $time->stamp() . "'),
            ('Malaysian Ringgit', 'MYR', 'RM', '" . $time->stamp() . "'),
            ('Kuwaiti Dinar', 'KWD', 'ك', '" . $time->stamp() . "'),
            ('Moroccan Dirham', 'MAD', 'م.', '" . $time->stamp() . "'),
            ('Iraqi Dinar', 'IQD', 'د.ع', '" . $time->stamp() . "'),
            ('Bangladeshi Taka', 'BDT', 'Tk', '" . $time->stamp() . "'),
            ('Bahraini Dinar', 'BHD', 'BD', '" . $time->stamp() . "'),
            ('Kenyan Shilling', 'KES', 'KSh', '" . $time->stamp() . "'),
            ('CFA Franc', 'XOF', 'CFA', '" . $time->stamp() . "'),
            ('Jordanian Dinar', 'JOD', 'JD', '" . $time->stamp() . "'),
            ('Tunisian Dinar', 'TND', 'د.ت', '" . $time->stamp() . "'),
            ('Ghanaian Cedi', 'GHS', 'GH¢', '" . $time->stamp() . "'),
            ('Central African CFA Franc BEAC', 'XAF', 'FCFA', '" . $time->stamp() . "'),
            ('Algerian Dinar', 'DZD', 'دج', '" . $time->stamp() . "'),
            ('CFP Franc', 'XPF', 'F', '" . $time->stamp() . "'),
            ('Ugandan Shilling', 'UGX', 'USh', '" . $time->stamp() . "'),
            ('Tanzanian Shilling', 'TZS', 'TZS', '" . $time->stamp() . "'),
            ('Ethiopian Birr', 'ETB', 'Br', '" . $time->stamp() . "'),
            ('Georgian Lari', 'GEL', 'GEL', '" . $time->stamp() . "'),
            ('Cuban Convertible Peso', 'CUC', 'CUC$', '" . $time->stamp() . "'),
            ('Burmese Kyat', 'MMK', 'K', '" . $time->stamp() . "'),
            ('Libyan Dinar', 'LYD', 'LD', '" . $time->stamp() . "'),
            ('Zambian Kwacha', 'ZMK', 'ZK', '" . $time->stamp() . "'),
            ('Zambian Kwacha', 'ZMW', 'ZK', '" . $time->stamp() . "'),
            ('Macau Pataca', 'MOP', 'MOP$', '" . $time->stamp() . "'),
            ('Armenian Dram', 'AMD', 'AMD', '" . $time->stamp() . "'),
            ('Angolan Kwanza', 'AOA', 'Kz', '" . $time->stamp() . "'),
            ('Papua New Guinean Kina', 'PGK', 'K', '" . $time->stamp() . "'),
            ('Malagasy Ariary', 'MGA', 'Ar', '" . $time->stamp() . "'),
            ('Ni-Vanuatu Vatu', 'VUV', 'VT', '" . $time->stamp() . "'),
            ('Sudanese Pound', 'SDG', 'SDG', '" . $time->stamp() . "'),
            ('Malawian Kwacha', 'MWK', 'MK', '" . $time->stamp() . "'),
            ('Rwandan Franc', 'RWF', 'FRw', '" . $time->stamp() . "'),
            ('Gambian Dalasi', 'GMD', 'D', '" . $time->stamp() . "'),
            ('Maldivian Rufiyaa', 'MVR', 'Rf', '" . $time->stamp() . "'),
            ('Congolese Franc', 'CDF', 'FC', '" . $time->stamp() . "'),
            ('Djiboutian Franc', 'DJF', 'Fdj', '" . $time->stamp() . "'),
            ('Haitian Gourde', 'HTG', 'G', '" . $time->stamp() . "'),
            ('Samoan Tala', 'WST', '$', '" . $time->stamp() . "'),
            ('Guinean Franc', 'GNF', 'FG', '" . $time->stamp() . "'),
            ('Cape Verdean Escudo', 'CVE', '$', '" . $time->stamp() . "'),
            ('Tongan Pa\'anga', 'TOP', 'T$', '" . $time->stamp() . "'),
            ('Moldovan Leu', 'MDL', 'MDL', '" . $time->stamp() . "'),
            ('Sierra Leonean Leone', 'SLL', 'Le', '" . $time->stamp() . "'),
            ('Burundian Franc', 'BIF', 'FBu', '" . $time->stamp() . "'),
            ('Mauritanian Ouguiya', 'MRO', 'UM', '" . $time->stamp() . "'),
            ('Bhutanese Ngultrum', 'BTN', 'Nu.', '" . $time->stamp() . "'),
            ('Swazi Lilangeni', 'SZL', 'SZL', '" . $time->stamp() . "'),
            ('Tajikistani Somoni', 'TJS', 'TJS', '" . $time->stamp() . "'),
            ('Turkmenistani Manat', 'TMT', 'm', '" . $time->stamp() . "'),
            ('Basotho Loti', 'LSL', 'LSL', '" . $time->stamp() . "'),
            ('Comoran Franc', 'KMF', 'CF', '" . $time->stamp() . "'),
            ('Sao Tomean Dobra', 'STD', 'STD', '" . $time->stamp() . "'),
            ('Seborgan Luigino', 'SPL', 'SPL', '" . $time->stamp() . "')";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `currency_conversions` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `currency_id` INT(10) NOT NULL,
                `user_id` INT(10) NOT NULL,
                `conversion` FLOAT NOT NULL,
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `fees` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `registrar_id` INT(10) NOT NULL,
                `tld` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `initial_fee` FLOAT NOT NULL,
                `renewal_fee` FLOAT NOT NULL,
                `transfer_fee` FLOAT NOT NULL,
                `privacy_fee` FLOAT NOT NULL,
                `misc_fee` FLOAT NOT NULL,
                `currency_id` INT(10) NOT NULL,
                `fee_fixed` INT(1) NOT NULL,
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `ssl_fees` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `ssl_provider_id` INT(10) NOT NULL,
                `type_id` INT(10) NOT NULL,
                `initial_fee` FLOAT NOT NULL,
                `renewal_fee` FLOAT NOT NULL,
                `misc_fee` FLOAT NOT NULL,
                `currency_id` INT(10) NOT NULL,
                `fee_fixed` INT(1) NOT NULL,
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `domains` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `owner_id` INT(10) NOT NULL DEFAULT '1',
                `registrar_id` INT(10) NOT NULL DEFAULT '1',
                `account_id` INT(10) NOT NULL DEFAULT '1',
                `domain` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `tld` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `expiry_date` DATE NOT NULL,
                `cat_id` INT(10) NOT NULL DEFAULT '1',
                `fee_id` INT(10) NOT NULL DEFAULT '0',
                `total_cost` FLOAT NOT NULL,
                `dns_id` INT(10) NOT NULL DEFAULT '1',
                `ip_id` INT(10) NOT NULL DEFAULT '1',
                `hosting_id` INT(10) NOT NULL DEFAULT '1',
                `function` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `autorenew` TINYINT(1) NOT NULL DEFAULT '0',
                `privacy` INT(1) NOT NULL DEFAULT '0',
                `active` INT(2) NOT NULL DEFAULT '1',
                `fee_fixed` INT(1) NOT NULL,
                `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
                `created_by` INT(10) NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`),
                KEY `domain` (`domain`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `domain_queue` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `api_registrar_id` SMALLINT(5) NOT NULL DEFAULT '0',
                `domain_id` INT(10) NOT NULL DEFAULT '0',
                `owner_id` INT(10) NOT NULL DEFAULT '0',
                `registrar_id` INT(10) NOT NULL DEFAULT '0',
                `account_id` INT(10) NOT NULL DEFAULT '0',
                `domain` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `tld` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `expiry_date` DATE NOT NULL,
                `cat_id` INT(10) NOT NULL DEFAULT '0',
                `dns_id` INT(10) NOT NULL DEFAULT '0',
                `ip_id` INT(10) NOT NULL DEFAULT '0',
                `hosting_id` INT(10) NOT NULL DEFAULT '0',
                `autorenew` TINYINT(1) NOT NULL DEFAULT '0',
                `privacy` TINYINT(1) NOT NULL DEFAULT '0',
                `processing` TINYINT(1) NOT NULL DEFAULT '0',
                `ready_to_import` TINYINT(1) NOT NULL DEFAULT '0',
                `finished` TINYINT(1) NOT NULL DEFAULT '0',
                `already_in_domains` TINYINT(1) NOT NULL DEFAULT '0',
                `already_in_queue` TINYINT(1) NOT NULL DEFAULT '0',
                `copied_to_history` TINYINT(1) NOT NULL DEFAULT '0',
                `created_by` INT(10) NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `domain_queue_history` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `api_registrar_id` SMALLINT(5) NOT NULL DEFAULT '0',
                `domain_id` INT(10) NOT NULL DEFAULT '0',
                `owner_id` INT(10) NOT NULL DEFAULT '0',
                `registrar_id` INT(10) NOT NULL DEFAULT '0',
                `account_id` INT(10) NOT NULL DEFAULT '0',
                `domain` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `tld` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `expiry_date` DATE NOT NULL,
                `cat_id` INT(10) NOT NULL DEFAULT '0',
                `dns_id` INT(10) NOT NULL DEFAULT '0',
                `ip_id` INT(10) NOT NULL DEFAULT '0',
                `hosting_id` INT(10) NOT NULL DEFAULT '0',
                `autorenew` TINYINT(1) NOT NULL DEFAULT '0',
                `privacy` TINYINT(1) NOT NULL DEFAULT '0',
                `already_in_domains` TINYINT(1) NOT NULL DEFAULT '0',
                `already_in_queue` TINYINT(1) NOT NULL DEFAULT '0',
                `created_by` INT(10) NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `domain_queue_list` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `api_registrar_id` SMALLINT(5) NOT NULL DEFAULT '0',
                `domain_count` INT(6) NOT NULL DEFAULT '0',
                `owner_id` INT(10) NOT NULL DEFAULT '0',
                `registrar_id` INT(10) NOT NULL DEFAULT '0',
                `account_id` INT(10) NOT NULL DEFAULT '0',
                `processing` TINYINT(1) NOT NULL DEFAULT '0',
                `ready_to_import` TINYINT(1) NOT NULL DEFAULT '0',
                `finished` TINYINT(1) NOT NULL DEFAULT '0',
                `copied_to_history` TINYINT(1) NOT NULL DEFAULT '0',
                `created_by` INT(10) NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `domain_queue_list_history` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `api_registrar_id` SMALLINT(5) NOT NULL DEFAULT '0',
                `domain_count` INT(6) NOT NULL DEFAULT '0',
                `owner_id` INT(10) NOT NULL DEFAULT '0',
                `registrar_id` INT(10) NOT NULL DEFAULT '0',
                `account_id` INT(10) NOT NULL DEFAULT '0',
                `created_by` INT(10) NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `custom_field_types` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(150) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
            PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO custom_field_types
            (id, `name`, insert_time) VALUES
            (1, 'Check Box', '" . $time->stamp() . "'),
            (2, 'Text', '" . $time->stamp() . "'),
            (3, 'Text Area', '" . $time->stamp() . "')";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `domain_fields` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(75) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `field_name` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `type_id` INT(10) NOT NULL,
                `description` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
                `created_by` INT(10) NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `domain_field_data` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `domain_id` INT(10) NOT NULL,
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `ssl_certs` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `owner_id` INT(10) NOT NULL,
                `ssl_provider_id` INT(10) NOT NULL,
                `account_id` INT(10) NOT NULL,
                `domain_id` INT(10) NOT NULL,
                `type_id` INT(10) NOT NULL,
                `ip_id` INT(10) NOT NULL,
                `cat_id` INT(10) NOT NULL,
                `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `expiry_date` DATE NOT NULL,
                `fee_id` INT(10) NOT NULL,
                `total_cost` FLOAT NOT NULL,
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `active` INT(1) NOT NULL DEFAULT '1',
                `fee_fixed` INT(1) NOT NULL,
                `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
                `created_by` INT(10) NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `ssl_cert_types` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `type` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
                `created_by` INT(10) NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO `ssl_cert_types`
            (`id`, `type`, `creation_type_id`, `insert_time`) VALUES
            (1, 'Web Server SSL/TLS Certificate', '" . $creation_type_id_installation . "', '" . $time->stamp() . "'),
            (2, 'S/MIME and Authentication Certificate', '" . $creation_type_id_installation . "', '" . $time->stamp() . "'),
            (3, 'Object Code Signing Certificate', '" . $creation_type_id_installation . "', '" . $time->stamp() . "'),
            (4, 'Digital ID', '" . $creation_type_id_installation . "', '" . $time->stamp() . "');";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `ssl_cert_fields` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(75) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `field_name` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `type_id` INT(10) NOT NULL,
                `description` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
                `created_by` INT(10) NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `ssl_cert_field_data` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `ssl_id` INT(10) NOT NULL,
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `dns` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
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
                `ip1` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `ip2` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `ip3` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `ip4` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `ip5` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `ip6` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `ip7` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `ip8` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `ip9` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `ip10` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `number_of_servers` INT(2) NOT NULL DEFAULT '0',
                `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
                `created_by` INT(10) NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO `dns`
            (`name`, `dns1`, `dns2`, `number_of_servers`, `creation_type_id`, `insert_time`) VALUES
            ('[no dns]', 'ns1.no-dns.com', 'ns2.no-dns.com', '2', '" . $creation_type_id_installation . "', '" . $time->stamp() . "');";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `registrars` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `url` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `api_registrar_id` TINYINT(3) NOT NULL DEFAULT '0',
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
                `created_by` INT(10) NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`),
                KEY `name` (`name`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `registrar_accounts` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `owner_id` INT(10) NOT NULL,
                `registrar_id` INT(10) NOT NULL,
                `email_address` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `username` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `password` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `reseller` INT(1) NOT NULL DEFAULT '0',
                `reseller_id` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `api_app_name` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `api_key` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `api_secret` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `api_ip_id` INT(10) NOT NULL DEFAULT '0',
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
                `created_by` INT(10) NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`),
                KEY `registrar_id` (`registrar_id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `ssl_providers` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `url` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
                `created_by` INT(10) NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `ssl_accounts` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `owner_id` INT(10) NOT NULL,
                `ssl_provider_id` INT(10) NOT NULL,
                `email_address` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `username` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `password` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `reseller` INT(1) NOT NULL DEFAULT '0',
                `reseller_id` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
                `created_by` INT(10) NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`),
                KEY `ssl_provider_id` (`ssl_provider_id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `segments` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(35) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `description` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `segment` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `number_of_domains` INT(6) NOT NULL,
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
                `created_by` INT(10) NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `segment_data` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `segment_id` INT(10) NOT NULL,
                `domain` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `active` INT(1) NOT NULL DEFAULT '0',
                `inactive` INT(1) NOT NULL DEFAULT '0',
                `missing` INT(1) NOT NULL DEFAULT '0',
                `filtered` INT(1) NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
            PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `ip_addresses` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `ip` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `rdns` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
                `created_by` INT(10) NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO `ip_addresses`
            (`id`, `name`, `ip`, `rdns`, `creation_type_id`, `insert_time`) VALUES
            ('1', '[no ip address]', '-', '-', '" . $creation_type_id_installation . "', '" . $time->stamp() . "');";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `timezones` (
                `id` INT(5) NOT NULL AUTO_INCREMENT,
                `timezone` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `insert_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO `timezones`
            (`timezone`, `insert_time`) VALUES
            ('Africa/Abidjan', '" . $time->stamp() . "'), ('Africa/Accra', '" . $time->stamp() . "'), ('Africa/Addis_Ababa', '" . $time->stamp() . "'), ('Africa/Algiers', '" . $time->stamp() . "'), ('Africa/Asmara', '" . $time->stamp() . "'), ('Africa/Asmera', '" . $time->stamp() . "'), ('Africa/Bamako', '" . $time->stamp() . "'), ('Africa/Bangui', '" . $time->stamp() . "'), ('Africa/Banjul', '" . $time->stamp() . "'), ('Africa/Bissau', '" . $time->stamp() . "'), ('Africa/Blantyre', '" . $time->stamp() . "'), ('Africa/Brazzaville', '" . $time->stamp() . "'), ('Africa/Bujumbura', '" . $time->stamp() . "'), ('Africa/Cairo', '" . $time->stamp() . "'), ('Africa/Casablanca', '" . $time->stamp() . "'), ('Africa/Ceuta', '" . $time->stamp() . "'), ('Africa/Conakry', '" . $time->stamp() . "'), ('Africa/Dakar', '" . $time->stamp() . "'), ('Africa/Dar_es_Salaam', '" . $time->stamp() . "'), ('Africa/Djibouti', '" . $time->stamp() . "'), ('Africa/Douala', '" . $time->stamp() . "'), ('Africa/El_Aaiun', '" . $time->stamp() . "'), ('Africa/Freetown', '" . $time->stamp() . "'), ('Africa/Gaborone', '" . $time->stamp() . "'), ('Africa/Harare', '" . $time->stamp() . "'), ('Africa/Johannesburg', '" . $time->stamp() . "'), ('Africa/Juba', '" . $time->stamp() . "'), ('Africa/Kampala', '" . $time->stamp() . "'), ('Africa/Khartoum', '" . $time->stamp() . "'), ('Africa/Kigali', '" . $time->stamp() . "'), ('Africa/Kinshasa', '" . $time->stamp() . "'), ('Africa/Lagos', '" . $time->stamp() . "'), ('Africa/Libreville', '" . $time->stamp() . "'), ('Africa/Lome', '" . $time->stamp() . "'), ('Africa/Luanda', '" . $time->stamp() . "'), ('Africa/Lubumbashi', '" . $time->stamp() . "'), ('Africa/Lusaka', '" . $time->stamp() . "'), ('Africa/Malabo', '" . $time->stamp() . "'), ('Africa/Maputo', '" . $time->stamp() . "'), ('Africa/Maseru', '" . $time->stamp() . "'), ('Africa/Mbabane', '" . $time->stamp() . "'), ('Africa/Mogadishu', '" . $time->stamp() . "'), ('Africa/Monrovia', '" . $time->stamp() . "'), ('Africa/Nairobi', '" . $time->stamp() . "'), ('Africa/Ndjamena', '" . $time->stamp() . "'), ('Africa/Niamey', '" . $time->stamp() . "'), ('Africa/Nouakchott', '" . $time->stamp() . "'), ('Africa/Ouagadougou', '" . $time->stamp() . "'), ('Africa/Porto-Novo', '" . $time->stamp() . "'), ('Africa/Sao_Tome', '" . $time->stamp() . "'), ('Africa/Timbuktu', '" . $time->stamp() . "'), ('Africa/Tripoli', '" . $time->stamp() . "'), ('Africa/Tunis', '" . $time->stamp() . "'), ('Africa/Windhoek', '" . $time->stamp() . "'), ('America/Adak', '" . $time->stamp() . "'), ('America/Anchorage', '" . $time->stamp() . "'), ('America/Anguilla', '" . $time->stamp() . "'), ('America/Antigua', '" . $time->stamp() . "'), ('America/Araguaina', '" . $time->stamp() . "'), ('America/Argentina/Buenos_Aires', '" . $time->stamp() . "'), ('America/Argentina/Catamarca', '" . $time->stamp() . "'), ('America/Argentina/ComodRivadavia', '" . $time->stamp() . "'), ('America/Argentina/Cordoba', '" . $time->stamp() . "'), ('America/Argentina/Jujuy', '" . $time->stamp() . "'), ('America/Argentina/La_Rioja', '" . $time->stamp() . "'), ('America/Argentina/Mendoza', '" . $time->stamp() . "'), ('America/Argentina/Rio_Gallegos', '" . $time->stamp() . "'), ('America/Argentina/Salta', '" . $time->stamp() . "'), ('America/Argentina/San_Juan', '" . $time->stamp() . "'), ('America/Argentina/San_Luis', '" . $time->stamp() . "'), ('America/Argentina/Tucuman', '" . $time->stamp() . "'), ('America/Argentina/Ushuaia', '" . $time->stamp() . "'), ('America/Aruba', '" . $time->stamp() . "'), ('America/Asuncion', '" . $time->stamp() . "'), ('America/Atikokan', '" . $time->stamp() . "'), ('America/Atka', '" . $time->stamp() . "'), ('America/Bahia', '" . $time->stamp() . "'), ('America/Bahia_Banderas', '" . $time->stamp() . "'), ('America/Barbados', '" . $time->stamp() . "'), ('America/Belem', '" . $time->stamp() . "'), ('America/Belize', '" . $time->stamp() . "'), ('America/Blanc-Sablon', '" . $time->stamp() . "'), ('America/Boa_Vista', '" . $time->stamp() . "'), ('America/Bogota', '" . $time->stamp() . "'), ('America/Boise', '" . $time->stamp() . "'), ('America/Buenos_Aires', '" . $time->stamp() . "'), ('America/Cambridge_Bay', '" . $time->stamp() . "'), ('America/Campo_Grande', '" . $time->stamp() . "'), ('America/Cancun', '" . $time->stamp() . "'), ('America/Caracas', '" . $time->stamp() . "'), ('America/Catamarca', '" . $time->stamp() . "'), ('America/Cayenne', '" . $time->stamp() . "'), ('America/Cayman', '" . $time->stamp() . "'), ('America/Chicago', '" . $time->stamp() . "'), ('America/Chihuahua', '" . $time->stamp() . "'), ('America/Coral_Harbour', '" . $time->stamp() . "'), ('America/Cordoba', '" . $time->stamp() . "'), ('America/Costa_Rica', '" . $time->stamp() . "'), ('America/Creston', '" . $time->stamp() . "'), ('America/Cuiaba', '" . $time->stamp() . "'), ('America/Curacao', '" . $time->stamp() . "'), ('America/Danmarkshavn', '" . $time->stamp() . "'), ('America/Dawson', '" . $time->stamp() . "'), ('America/Dawson_Creek', '" . $time->stamp() . "'), ('America/Denver', '" . $time->stamp() . "'), ('America/Detroit', '" . $time->stamp() . "'), ('America/Dominica', '" . $time->stamp() . "'), ('America/Edmonton', '" . $time->stamp() . "'), ('America/Eirunepe', '" . $time->stamp() . "'), ('America/El_Salvador', '" . $time->stamp() . "'), ('America/Ensenada', '" . $time->stamp() . "'), ('America/Fort_Wayne', '" . $time->stamp() . "'), ('America/Fortaleza', '" . $time->stamp() . "'), ('America/Glace_Bay', '" . $time->stamp() . "'), ('America/Godthab', '" . $time->stamp() . "'), ('America/Goose_Bay', '" . $time->stamp() . "'), ('America/Grand_Turk', '" . $time->stamp() . "'), ('America/Grenada', '" . $time->stamp() . "'), ('America/Guadeloupe', '" . $time->stamp() . "'), ('America/Guatemala', '" . $time->stamp() . "'), ('America/Guayaquil', '" . $time->stamp() . "'), ('America/Guyana', '" . $time->stamp() . "'), ('America/Halifax', '" . $time->stamp() . "'), ('America/Havana', '" . $time->stamp() . "'), ('America/Hermosillo', '" . $time->stamp() . "'), ('America/Indiana/Indianapolis', '" . $time->stamp() . "'), ('America/Indiana/Knox', '" . $time->stamp() . "'), ('America/Indiana/Marengo', '" . $time->stamp() . "'), ('America/Indiana/Petersburg', '" . $time->stamp() . "'), ('America/Indiana/Tell_City', '" . $time->stamp() . "'), ('America/Indiana/Vevay', '" . $time->stamp() . "'), ('America/Indiana/Vincennes', '" . $time->stamp() . "'), ('America/Indiana/Winamac', '" . $time->stamp() . "'), ('America/Indianapolis', '" . $time->stamp() . "'), ('America/Inuvik', '" . $time->stamp() . "'), ('America/Iqaluit', '" . $time->stamp() . "'), ('America/Jamaica', '" . $time->stamp() . "'), ('America/Jujuy', '" . $time->stamp() . "'), ('America/Juneau', '" . $time->stamp() . "'), ('America/Kentucky/Louisville', '" . $time->stamp() . "'), ('America/Kentucky/Monticello', '" . $time->stamp() . "'), ('America/Knox_IN', '" . $time->stamp() . "'), ('America/Kralendijk', '" . $time->stamp() . "'), ('America/La_Paz', '" . $time->stamp() . "'), ('America/Lima', '" . $time->stamp() . "'), ('America/Los_Angeles', '" . $time->stamp() . "'), ('America/Louisville', '" . $time->stamp() . "'), ('America/Lower_Princes', '" . $time->stamp() . "'), ('America/Maceio', '" . $time->stamp() . "'), ('America/Managua', '" . $time->stamp() . "'), ('America/Manaus', '" . $time->stamp() . "'), ('America/Marigot', '" . $time->stamp() . "'), ('America/Martinique', '" . $time->stamp() . "'), ('America/Matamoros', '" . $time->stamp() . "'), ('America/Mazatlan', '" . $time->stamp() . "'), ('America/Mendoza', '" . $time->stamp() . "'), ('America/Menominee', '" . $time->stamp() . "'), ('America/Merida', '" . $time->stamp() . "'), ('America/Metlakatla', '" . $time->stamp() . "'), ('America/Mexico_City', '" . $time->stamp() . "'), ('America/Miquelon', '" . $time->stamp() . "'), ('America/Moncton', '" . $time->stamp() . "'), ('America/Monterrey', '" . $time->stamp() . "'), ('America/Montevideo', '" . $time->stamp() . "'), ('America/Montreal', '" . $time->stamp() . "'), ('America/Montserrat', '" . $time->stamp() . "'), ('America/Nassau', '" . $time->stamp() . "'), ('America/New_York', '" . $time->stamp() . "'), ('America/Nipigon', '" . $time->stamp() . "'), ('America/Nome', '" . $time->stamp() . "'), ('America/Noronha', '" . $time->stamp() . "'), ('America/North_Dakota/Beulah', '" . $time->stamp() . "'), ('America/North_Dakota/Center', '" . $time->stamp() . "'), ('America/North_Dakota/New_Salem', '" . $time->stamp() . "'), ('America/Ojinaga', '" . $time->stamp() . "'), ('America/Panama', '" . $time->stamp() . "'), ('America/Pangnirtung', '" . $time->stamp() . "'), ('America/Paramaribo', '" . $time->stamp() . "'), ('America/Phoenix', '" . $time->stamp() . "'), ('America/Port-au-Prince', '" . $time->stamp() . "'), ('America/Port_of_Spain', '" . $time->stamp() . "'), ('America/Porto_Acre', '" . $time->stamp() . "'), ('America/Porto_Velho', '" . $time->stamp() . "'), ('America/Puerto_Rico', '" . $time->stamp() . "'), ('America/Rainy_River', '" . $time->stamp() . "'), ('America/Rankin_Inlet', '" . $time->stamp() . "'), ('America/Recife', '" . $time->stamp() . "'), ('America/Regina', '" . $time->stamp() . "'), ('America/Resolute', '" . $time->stamp() . "'), ('America/Rio_Branco', '" . $time->stamp() . "'), ('America/Rosario', '" . $time->stamp() . "'), ('America/Santa_Isabel', '" . $time->stamp() . "'), ('America/Santarem', '" . $time->stamp() . "'), ('America/Santiago', '" . $time->stamp() . "'), ('America/Santo_Domingo', '" . $time->stamp() . "'), ('America/Sao_Paulo', '" . $time->stamp() . "'), ('America/Scoresbysund', '" . $time->stamp() . "'), ('America/Shiprock', '" . $time->stamp() . "'), ('America/Sitka', '" . $time->stamp() . "'), ('America/St_Barthelemy', '" . $time->stamp() . "'), ('America/St_Johns', '" . $time->stamp() . "'), ('America/St_Kitts', '" . $time->stamp() . "'), ('America/St_Lucia', '" . $time->stamp() . "'), ('America/St_Thomas', '" . $time->stamp() . "'), ('America/St_Vincent', '" . $time->stamp() . "'), ('America/Swift_Current', '" . $time->stamp() . "'), ('America/Tegucigalpa', '" . $time->stamp() . "'), ('America/Thule', '" . $time->stamp() . "'), ('America/Thunder_Bay', '" . $time->stamp() . "'), ('America/Tijuana', '" . $time->stamp() . "'), ('America/Toronto', '" . $time->stamp() . "'), ('America/Tortola', '" . $time->stamp() . "'), ('America/Vancouver', '" . $time->stamp() . "'), ('America/Virgin', '" . $time->stamp() . "'), ('America/Whitehorse', '" . $time->stamp() . "'), ('America/Winnipeg', '" . $time->stamp() . "'), ('America/Yakutat', '" . $time->stamp() . "'), ('America/Yellowknife', '" . $time->stamp() . "'), ('Antarctica/Casey', '" . $time->stamp() . "'), ('Antarctica/Davis', '" . $time->stamp() . "'), ('Antarctica/DumontDUrville', '" . $time->stamp() . "'), ('Antarctica/Macquarie', '" . $time->stamp() . "'), ('Antarctica/Mawson', '" . $time->stamp() . "'), ('Antarctica/McMurdo', '" . $time->stamp() . "'), ('Antarctica/Palmer', '" . $time->stamp() . "'), ('Antarctica/Rothera', '" . $time->stamp() . "'), ('Antarctica/South_Pole', '" . $time->stamp() . "'), ('Antarctica/Syowa', '" . $time->stamp() . "'), ('Antarctica/Vostok', '" . $time->stamp() . "'), ('Arctic/Longyearbyen', '" . $time->stamp() . "'), ('Asia/Aden', '" . $time->stamp() . "'), ('Asia/Almaty', '" . $time->stamp() . "'), ('Asia/Amman', '" . $time->stamp() . "'), ('Asia/Anadyr', '" . $time->stamp() . "'), ('Asia/Aqtau', '" . $time->stamp() . "'), ('Asia/Aqtobe', '" . $time->stamp() . "'), ('Asia/Ashgabat', '" . $time->stamp() . "'), ('Asia/Ashkhabad', '" . $time->stamp() . "'), ('Asia/Baghdad', '" . $time->stamp() . "'), ('Asia/Bahrain', '" . $time->stamp() . "'), ('Asia/Baku', '" . $time->stamp() . "'), ('Asia/Bangkok', '" . $time->stamp() . "'), ('Asia/Beirut', '" . $time->stamp() . "'), ('Asia/Bishkek', '" . $time->stamp() . "'), ('Asia/Brunei', '" . $time->stamp() . "'), ('Asia/Calcutta', '" . $time->stamp() . "'), ('Asia/Choibalsan', '" . $time->stamp() . "'), ('Asia/Chongqing', '" . $time->stamp() . "'), ('Asia/Chungking', '" . $time->stamp() . "'), ('Asia/Colombo', '" . $time->stamp() . "'), ('Asia/Dacca', '" . $time->stamp() . "'), ('Asia/Damascus', '" . $time->stamp() . "'), ('Asia/Dhaka', '" . $time->stamp() . "'), ('Asia/Dili', '" . $time->stamp() . "'), ('Asia/Dubai', '" . $time->stamp() . "'), ('Asia/Dushanbe', '" . $time->stamp() . "'), ('Asia/Gaza', '" . $time->stamp() . "'), ('Asia/Harbin', '" . $time->stamp() . "'), ('Asia/Hebron', '" . $time->stamp() . "'), ('Asia/Ho_Chi_Minh', '" . $time->stamp() . "'), ('Asia/Hong_Kong', '" . $time->stamp() . "'), ('Asia/Hovd', '" . $time->stamp() . "'), ('Asia/Irkutsk', '" . $time->stamp() . "'), ('Asia/Istanbul', '" . $time->stamp() . "'), ('Asia/Jakarta', '" . $time->stamp() . "'), ('Asia/Jayapura', '" . $time->stamp() . "'), ('Asia/Jerusalem', '" . $time->stamp() . "'), ('Asia/Kabul', '" . $time->stamp() . "'), ('Asia/Kamchatka', '" . $time->stamp() . "'), ('Asia/Karachi', '" . $time->stamp() . "'), ('Asia/Kashgar', '" . $time->stamp() . "'), ('Asia/Kathmandu', '" . $time->stamp() . "'), ('Asia/Katmandu', '" . $time->stamp() . "'), ('Asia/Khandyga', '" . $time->stamp() . "'), ('Asia/Kolkata', '" . $time->stamp() . "'), ('Asia/Krasnoyarsk', '" . $time->stamp() . "'), ('Asia/Kuala_Lumpur', '" . $time->stamp() . "'), ('Asia/Kuching', '" . $time->stamp() . "'), ('Asia/Kuwait', '" . $time->stamp() . "'), ('Asia/Macao', '" . $time->stamp() . "'), ('Asia/Macau', '" . $time->stamp() . "'), ('Asia/Magadan', '" . $time->stamp() . "'), ('Asia/Makassar', '" . $time->stamp() . "'), ('Asia/Manila', '" . $time->stamp() . "'), ('Asia/Muscat', '" . $time->stamp() . "'), ('Asia/Nicosia', '" . $time->stamp() . "'), ('Asia/Novokuznetsk', '" . $time->stamp() . "'), ('Asia/Novosibirsk', '" . $time->stamp() . "'), ('Asia/Omsk', '" . $time->stamp() . "'), ('Asia/Oral', '" . $time->stamp() . "'), ('Asia/Phnom_Penh', '" . $time->stamp() . "'), ('Asia/Pontianak', '" . $time->stamp() . "'), ('Asia/Pyongyang', '" . $time->stamp() . "'), ('Asia/Qatar', '" . $time->stamp() . "'), ('Asia/Qyzylorda', '" . $time->stamp() . "'), ('Asia/Rangoon', '" . $time->stamp() . "'), ('Asia/Riyadh', '" . $time->stamp() . "'), ('Asia/Saigon', '" . $time->stamp() . "'), ('Asia/Sakhalin', '" . $time->stamp() . "'), ('Asia/Samarkand', '" . $time->stamp() . "'), ('Asia/Seoul', '" . $time->stamp() . "'), ('Asia/Shanghai', '" . $time->stamp() . "'), ('Asia/Singapore', '" . $time->stamp() . "'), ('Asia/Taipei', '" . $time->stamp() . "'), ('Asia/Tashkent', '" . $time->stamp() . "'), ('Asia/Tbilisi', '" . $time->stamp() . "'), ('Asia/Tehran', '" . $time->stamp() . "'), ('Asia/Tel_Aviv', '" . $time->stamp() . "'), ('Asia/Thimbu', '" . $time->stamp() . "'), ('Asia/Thimphu', '" . $time->stamp() . "'), ('Asia/Tokyo', '" . $time->stamp() . "'), ('Asia/Ujung_Pandang', '" . $time->stamp() . "'), ('Asia/Ulaanbaatar', '" . $time->stamp() . "'), ('Asia/Ulan_Bator', '" . $time->stamp() . "'), ('Asia/Urumqi', '" . $time->stamp() . "'), ('Asia/Ust-Nera', '" . $time->stamp() . "'), ('Asia/Vientiane', '" . $time->stamp() . "'), ('Asia/Vladivostok', '" . $time->stamp() . "'), ('Asia/Yakutsk', '" . $time->stamp() . "'), ('Asia/Yekaterinburg', '" . $time->stamp() . "'), ('Asia/Yerevan', '" . $time->stamp() . "'), ('Atlantic/Azores', '" . $time->stamp() . "'), ('Atlantic/Bermuda', '" . $time->stamp() . "'), ('Atlantic/Canary', '" . $time->stamp() . "'), ('Atlantic/Cape_Verde', '" . $time->stamp() . "'), ('Atlantic/Faeroe', '" . $time->stamp() . "'), ('Atlantic/Faroe', '" . $time->stamp() . "'), ('Atlantic/Jan_Mayen', '" . $time->stamp() . "'), ('Atlantic/Madeira', '" . $time->stamp() . "'), ('Atlantic/Reykjavik', '" . $time->stamp() . "'), ('Atlantic/South_Georgia', '" . $time->stamp() . "'), ('Atlantic/St_Helena', '" . $time->stamp() . "'), ('Atlantic/Stanley', '" . $time->stamp() . "'), ('Australia/ACT', '" . $time->stamp() . "'), ('Australia/Adelaide', '" . $time->stamp() . "'), ('Australia/Brisbane', '" . $time->stamp() . "'), ('Australia/Broken_Hill', '" . $time->stamp() . "'), ('Australia/Canberra', '" . $time->stamp() . "'), ('Australia/Currie', '" . $time->stamp() . "'), ('Australia/Darwin', '" . $time->stamp() . "'), ('Australia/Eucla', '" . $time->stamp() . "'), ('Australia/Hobart', '" . $time->stamp() . "'), ('Australia/LHI', '" . $time->stamp() . "'), ('Australia/Lindeman', '" . $time->stamp() . "'), ('Australia/Lord_Howe', '" . $time->stamp() . "'), ('Australia/Melbourne', '" . $time->stamp() . "'), ('Australia/North', '" . $time->stamp() . "'), ('Australia/NSW', '" . $time->stamp() . "'), ('Australia/Perth', '" . $time->stamp() . "'), ('Australia/Queensland', '" . $time->stamp() . "'), ('Australia/South', '" . $time->stamp() . "'), ('Australia/Sydney', '" . $time->stamp() . "'), ('Australia/Tasmania', '" . $time->stamp() . "'), ('Australia/Victoria', '" . $time->stamp() . "'), ('Australia/West', '" . $time->stamp() . "'), ('Australia/Yancowinna', '" . $time->stamp() . "'), ('Brazil/Acre', '" . $time->stamp() . "'), ('Brazil/DeNoronha', '" . $time->stamp() . "'), ('Brazil/East', '" . $time->stamp() . "'), ('Brazil/West', '" . $time->stamp() . "'), ('Canada/Atlantic', '" . $time->stamp() . "'), ('Canada/Central', '" . $time->stamp() . "'), ('Canada/East-Saskatchewan', '" . $time->stamp() . "'), ('Canada/Eastern', '" . $time->stamp() . "'), ('Canada/Mountain', '" . $time->stamp() . "'), ('Canada/Newfoundland', '" . $time->stamp() . "'), ('Canada/Pacific', '" . $time->stamp() . "'), ('Canada/Saskatchewan', '" . $time->stamp() . "'), ('Canada/Yukon', '" . $time->stamp() . "'), ('Chile/Continental', '" . $time->stamp() . "'), ('Chile/EasterIsland', '" . $time->stamp() . "'), ('Cuba', '" . $time->stamp() . "'), ('Egypt', '" . $time->stamp() . "'), ('Eire', '" . $time->stamp() . "'), ('Europe/Amsterdam', '" . $time->stamp() . "'), ('Europe/Andorra', '" . $time->stamp() . "'), ('Europe/Athens', '" . $time->stamp() . "'), ('Europe/Belfast', '" . $time->stamp() . "'), ('Europe/Belgrade', '" . $time->stamp() . "'), ('Europe/Berlin', '" . $time->stamp() . "'), ('Europe/Bratislava', '" . $time->stamp() . "'), ('Europe/Brussels', '" . $time->stamp() . "'), ('Europe/Bucharest', '" . $time->stamp() . "'), ('Europe/Budapest', '" . $time->stamp() . "'), ('Europe/Busingen', '" . $time->stamp() . "'), ('Europe/Chisinau', '" . $time->stamp() . "'), ('Europe/Copenhagen', '" . $time->stamp() . "'), ('Europe/Dublin', '" . $time->stamp() . "'), ('Europe/Gibraltar', '" . $time->stamp() . "'), ('Europe/Guernsey', '" . $time->stamp() . "'), ('Europe/Helsinki', '" . $time->stamp() . "'), ('Europe/Isle_of_Man', '" . $time->stamp() . "'), ('Europe/Istanbul', '" . $time->stamp() . "'), ('Europe/Jersey', '" . $time->stamp() . "'), ('Europe/Kaliningrad', '" . $time->stamp() . "'), ('Europe/Kiev', '" . $time->stamp() . "'), ('Europe/Lisbon', '" . $time->stamp() . "'), ('Europe/Ljubljana', '" . $time->stamp() . "'), ('Europe/London', '" . $time->stamp() . "'), ('Europe/Luxembourg', '" . $time->stamp() . "'), ('Europe/Madrid', '" . $time->stamp() . "'), ('Europe/Malta', '" . $time->stamp() . "'), ('Europe/Mariehamn', '" . $time->stamp() . "'), ('Europe/Minsk', '" . $time->stamp() . "'), ('Europe/Monaco', '" . $time->stamp() . "'), ('Europe/Moscow', '" . $time->stamp() . "'), ('Europe/Nicosia', '" . $time->stamp() . "'), ('Europe/Oslo', '" . $time->stamp() . "'), ('Europe/Paris', '" . $time->stamp() . "'), ('Europe/Podgorica', '" . $time->stamp() . "'), ('Europe/Prague', '" . $time->stamp() . "'), ('Europe/Riga', '" . $time->stamp() . "'), ('Europe/Rome', '" . $time->stamp() . "'), ('Europe/Samara', '" . $time->stamp() . "'), ('Europe/San_Marino', '" . $time->stamp() . "'), ('Europe/Sarajevo', '" . $time->stamp() . "'), ('Europe/Simferopol', '" . $time->stamp() . "'), ('Europe/Skopje', '" . $time->stamp() . "'), ('Europe/Sofia', '" . $time->stamp() . "'), ('Europe/Stockholm', '" . $time->stamp() . "'), ('Europe/Tallinn', '" . $time->stamp() . "'), ('Europe/Tirane', '" . $time->stamp() . "'), ('Europe/Tiraspol', '" . $time->stamp() . "'), ('Europe/Uzhgorod', '" . $time->stamp() . "'), ('Europe/Vaduz', '" . $time->stamp() . "'), ('Europe/Vatican', '" . $time->stamp() . "'), ('Europe/Vienna', '" . $time->stamp() . "'), ('Europe/Vilnius', '" . $time->stamp() . "'), ('Europe/Volgograd', '" . $time->stamp() . "'), ('Europe/Warsaw', '" . $time->stamp() . "'), ('Europe/Zagreb', '" . $time->stamp() . "'), ('Europe/Zaporozhye', '" . $time->stamp() . "'), ('Europe/Zurich', '" . $time->stamp() . "'), ('Greenwich', '" . $time->stamp() . "'), ('Hongkong', '" . $time->stamp() . "'), ('Iceland', '" . $time->stamp() . "'), ('Indian/Antananarivo', '" . $time->stamp() . "'), ('Indian/Chagos', '" . $time->stamp() . "'), ('Indian/Christmas', '" . $time->stamp() . "'), ('Indian/Cocos', '" . $time->stamp() . "'), ('Indian/Comoro', '" . $time->stamp() . "'), ('Indian/Kerguelen', '" . $time->stamp() . "'), ('Indian/Mahe', '" . $time->stamp() . "'), ('Indian/Maldives', '" . $time->stamp() . "'), ('Indian/Mauritius', '" . $time->stamp() . "'), ('Indian/Mayotte', '" . $time->stamp() . "'), ('Indian/Reunion', '" . $time->stamp() . "'), ('Iran', '" . $time->stamp() . "'), ('Israel', '" . $time->stamp() . "'), ('Jamaica', '" . $time->stamp() . "'), ('Japan', '" . $time->stamp() . "'), ('Kwajalein', '" . $time->stamp() . "'), ('Libya', '" . $time->stamp() . "'), ('Mexico/BajaNorte', '" . $time->stamp() . "'), ('Mexico/BajaSur', '" . $time->stamp() . "'), ('Mexico/General', '" . $time->stamp() . "'), ('Pacific/Apia', '" . $time->stamp() . "'), ('Pacific/Auckland', '" . $time->stamp() . "'), ('Pacific/Chatham', '" . $time->stamp() . "'), ('Pacific/Chuuk', '" . $time->stamp() . "'), ('Pacific/Easter', '" . $time->stamp() . "'), ('Pacific/Efate', '" . $time->stamp() . "'), ('Pacific/Enderbury', '" . $time->stamp() . "'), ('Pacific/Fakaofo', '" . $time->stamp() . "'), ('Pacific/Fiji', '" . $time->stamp() . "'), ('Pacific/Funafuti', '" . $time->stamp() . "'), ('Pacific/Galapagos', '" . $time->stamp() . "'), ('Pacific/Gambier', '" . $time->stamp() . "'), ('Pacific/Guadalcanal', '" . $time->stamp() . "'), ('Pacific/Guam', '" . $time->stamp() . "'), ('Pacific/Honolulu', '" . $time->stamp() . "'), ('Pacific/Johnston', '" . $time->stamp() . "'), ('Pacific/Kiritimati', '" . $time->stamp() . "'), ('Pacific/Kosrae', '" . $time->stamp() . "'), ('Pacific/Kwajalein', '" . $time->stamp() . "'), ('Pacific/Majuro', '" . $time->stamp() . "'), ('Pacific/Marquesas', '" . $time->stamp() . "'), ('Pacific/Midway', '" . $time->stamp() . "'), ('Pacific/Nauru', '" . $time->stamp() . "'), ('Pacific/Niue', '" . $time->stamp() . "'), ('Pacific/Norfolk', '" . $time->stamp() . "'), ('Pacific/Noumea', '" . $time->stamp() . "'), ('Pacific/Pago_Pago', '" . $time->stamp() . "'), ('Pacific/Palau', '" . $time->stamp() . "'), ('Pacific/Pitcairn', '" . $time->stamp() . "'), ('Pacific/Pohnpei', '" . $time->stamp() . "'), ('Pacific/Ponape', '" . $time->stamp() . "'), ('Pacific/Port_Moresby', '" . $time->stamp() . "'), ('Pacific/Rarotonga', '" . $time->stamp() . "'), ('Pacific/Saipan', '" . $time->stamp() . "'), ('Pacific/Samoa', '" . $time->stamp() . "'), ('Pacific/Tahiti', '" . $time->stamp() . "'), ('Pacific/Tarawa', '" . $time->stamp() . "'), ('Pacific/Tongatapu', '" . $time->stamp() . "'), ('Pacific/Truk', '" . $time->stamp() . "'), ('Pacific/Wake', '" . $time->stamp() . "'), ('Pacific/Wallis', '" . $time->stamp() . "'), ('Pacific/Yap', '" . $time->stamp() . "'), ('Poland', '" . $time->stamp() . "'), ('Portugal', '" . $time->stamp() . "'), ('Singapore', '" . $time->stamp() . "'), ('Turkey', '" . $time->stamp() . "'), ('US/Alaska', '" . $time->stamp() . "'), ('US/Aleutian', '" . $time->stamp() . "'), ('US/Arizona', '" . $time->stamp() . "'), ('US/Central', '" . $time->stamp() . "'), ('US/East-Indiana', '" . $time->stamp() . "'), ('US/Eastern', '" . $time->stamp() . "'), ('US/Hawaii', '" . $time->stamp() . "'), ('US/Indiana-Starke', '" . $time->stamp() . "'), ('US/Michigan', '" . $time->stamp() . "'), ('US/Mountain', '" . $time->stamp() . "'), ('US/Pacific', '" . $time->stamp() . "'), ('US/Pacific-New', '" . $time->stamp() . "'), ('US/Samoa', '" . $time->stamp() . "'), ('Zulu', '" . $time->stamp() . "');";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `dw_servers` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `host` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `protocol` VARCHAR(5) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `port` INT(5) NOT NULL,
                `username` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `hash` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `dw_accounts` INT(10) NOT NULL,
                `dw_dns_zones` INT(10) NOT NULL,
                `dw_dns_records` INT(10) NOT NULL,
                `build_status` INT(1) NOT NULL DEFAULT '0',
                `build_start_time` DATETIME NOT NULL,
                `build_end_time` DATETIME NOT NULL,
                `build_time` INT(10) NOT NULL DEFAULT '0',
                `has_ever_been_built` INT(1) NOT NULL DEFAULT '0',
                `build_status_overall` INT(1) NOT NULL DEFAULT '0',
                `build_start_time_overall` DATETIME NOT NULL,
                `build_end_time_overall` DATETIME NOT NULL,
                `build_time_overall` INT(10) NOT NULL DEFAULT '0',
                `has_ever_been_built_overall` INT(1) NOT NULL DEFAULT '0',
                `creation_type_id` TINYINT(2) NOT NULL DEFAULT '" . $creation_type_id_manual . "',
                `created_by` INT(10) NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `scheduler` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `slug` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `description` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `interval` VARCHAR(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Daily',
                `expression` VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '0 7 * * * *',
                `last_run` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                `last_duration` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `next_run` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                `sort_order` INT(4) NOT NULL DEFAULT '1',
                `is_running` INT(1) NOT NULL DEFAULT '0',
                `active` INT(1) NOT NULL DEFAULT '1',
                `insert_time` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                `update_time` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                PRIMARY KEY  (`id`)
             ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO scheduler
            (`name`, description, `interval`, expression, slug, sort_order, is_running, active, insert_time)
             VALUES
            ('Domain Queue Processing', 'Retrieves information for domains in the queue and adds them to DomainMOD.', 'Every 5 Minutes', '*/5 * * * * *', 'domain-queue', '10', '0', '1', '" . $time->stamp() . "'),
            ('Send Expiration Email', 'Sends an email out to everyone who\'s subscribed, letting them know of upcoming Domain & SSL Certificate expirations.<BR><BR>Users can subscribe via their User Profile.<BR><BR>Administrators can set the FROM email address and the number of days in the future to display in the email via System Settings.', 'Daily', '0 0 * * * *', 'expiration-email', '20', '0', '1', '" . $time->stamp() . "'),
            ('Update Conversion Rates', 'Retrieves the current currency conversion rates and updates the entire system, which keeps all of the financial information in DomainMOD accurate and up-to-date.<BR><BR>Users can set their default currency via their User Profile.', 'Daily', '0 0 * * * *', 'update-conversion-rates', '40', '0', '1', '" . $time->stamp() . "'),
            ('System Cleanup', '" . "<" . "em>Fees:" . "<" . "/em> Cross-references the Domain, SSL Certificate, and fee tables, making sure that everything is accurate. It also deletes all unused fees." . "<" . "BR>" . "<" . "BR> " . "<" . "em>Segments:" . "<" . "/em> Compares the Segment data to the domain database and records the status of each domain. This keeps the Segment filtering data up-to-date and running quickly." . "<" . "BR>" . "<" . "BR>" . "<" . "em>TLDs:" . "<" . "/em> Makes sure that the TLD entries recorded in the database are accurate.', 'Daily', '0 0 * * * *', 'cleanup', '60', '0', '1', '" . $time->stamp() . "'),
            ('Check For New Version', 'Checks to see if there is a newer version of DomainMOD available to download.', 'Daily', '0 0 * * * *', 'check-new-version', '80', '0', '1', '" . $time->stamp() . "'),
            ('Data Warehouse Build', 'Rebuilds the Data Warehouse so that you have the most up-to-date information available.', 'Daily', '0 0 * * * *', 'data-warehouse-build', '100', '0', '1', '" . $time->stamp() . "')";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    // Update tasks that run daily
    $cron = \Cron\CronExpression::factory('0 7 * * * *');
    $next_run = $cron->getNextRunDate()->format('Y-m-d H:i:s');

    $sql = "UPDATE scheduler
            SET next_run = '" . $next_run . "'
            WHERE `interval` = 'Daily'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    // Update tasks that run every 5 minutes
    $cron = \Cron\CronExpression::factory('*/5 * * * * *');
    $next_run = $cron->getNextRunDate()->format('Y-m-d H:i:s');

    $sql = "UPDATE scheduler
            SET next_run = '" . $next_run . "'
            WHERE `interval` = 'Every 5 Minutes'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `api_registrars` (
                `id` TINYINT(3) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `req_account_username` TINYINT(1) NOT NULL DEFAULT '0',
                `req_account_password` TINYINT(1) NOT NULL DEFAULT '0',
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
                `insert_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO api_registrars
            (`name`, req_account_username, req_account_password, req_reseller_id, req_api_app_name, req_api_key,
             req_api_secret, req_ip_address, lists_domains, ret_expiry_date, ret_dns_servers, ret_privacy_status,
             ret_autorenewal_status, notes, insert_time)
             VALUES
            ('DNSimple', '0', '0', '0', '0', '1', '0', '0', '1', '1', '1', '1', '1', '', '" . $time->stamp() . "'),
            ('Dynadot', '0', '0', '0', '0', '1', '0', '0', '1', '1', '1', '1', '1', '', '" . $time->stamp() . "'),
            ('eNom', '1', '1', '0', '0', '0', '0', '0', '1', '1', '1', '1', '1', '', '" . $time->stamp() . "'),
            ('Fabulous', '1', '1', '0', '0', '0', '0', '0', '1', '1', '1', '0', '0', 'Fabulous does not currently allow the privacy or auto renewal status of a domain to be retrieved using their API, so all domains added to the queue from a Fabulous account will have their privacy and auto renewal status set to No.', '" . $time->stamp() . "'),
            ('GoDaddy', '0', '0', '0', '0', '1', '1', '0', '1', '1', '1', '1', '1', '', '" . $time->stamp() . "'),
            ('Internet.bs', '0', '0', '0', '0', '1', '1', '0', '1', '1', '1', '1', '1', '', '" . $time->stamp() . "'),
            ('Name.com', '1', '0', '0', '0', '1', '0', '0', '1', '1', '1', '1', '1', '', '" . $time->stamp() . "'),
            ('NameBright', '1', '0', '0', '1', '0', '1', '0', '1', '1', '1', '1', '1', '', '" . $time->stamp() . "'),
            ('Namecheap', '1', '0', '0', '0', '1', '0', '1', '1', '1', '1', '1', '1', '', '" . $time->stamp() . "'),
            ('NameSilo', '0', '0', '0', '0', '1', '0', '0', '1', '1', '1', '1', '1', '', '" . $time->stamp() . "'),
            ('OpenSRS', '1', '0', '0', '0', '1', '0', '0', '1', '1', '1', '1', '1', '', '" . $time->stamp() . "'),
            ('ResellerClub', '0', '0', '1', '0', '1', '0', '0', '0', '1', '1', '1', '0', 'ResellerClub does not currently allow the auto renewal status of a domain to be retrieved using their API, so all domains added to the queue from a ResellerClub account will have their auto renewal status set to No.', '" . $time->stamp() . "')";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `settings` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `full_url` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'http://',
                `db_version` VARCHAR(12) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `upgrade_available` INT(1) NOT NULL DEFAULT '0',
                `email_address` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `large_mode` TINYINT(1) NOT NULL DEFAULT '0',
                `default_category_domains` INT(10) NOT NULL DEFAULT '0',
                `default_category_ssl` INT(10) NOT NULL DEFAULT '0',
                `default_dns` INT(10) NOT NULL DEFAULT '0',
                `default_host` INT(10) NOT NULL DEFAULT '0',
                `default_ip_address_domains` INT(10) NOT NULL DEFAULT '0',
                `default_ip_address_ssl` INT(10) NOT NULL DEFAULT '0',
                `default_owner_domains` INT(10) NOT NULL DEFAULT '0',
                `default_owner_ssl` INT(10) NOT NULL DEFAULT '0',
                `default_registrar` INT(10) NOT NULL DEFAULT '0',
                `default_registrar_account` INT(10) NOT NULL DEFAULT '0',
                `default_ssl_provider_account` INT(10) NOT NULL DEFAULT '0',
                `default_ssl_type` INT(10) NOT NULL DEFAULT '0',
                `default_ssl_provider` INT(10) NOT NULL DEFAULT '0',
                `expiration_days` INT(3) NOT NULL DEFAULT '60',
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $full_url = substr($_SERVER["HTTP_REFERER"], 0, -1);

    $sql = "INSERT INTO `settings`
            (`full_url`, `db_version`, `email_address`, `insert_time`) VALUES
            ('" . $full_url . "', '" . $software_version . "', '" . $_SESSION['new_install_email'] . "', '" . $time->stamp() . "');";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql_settings = "SELECT *
                     FROM settings";
    $result_settings = mysqli_query($connection, $sql_settings) or $error->outputOldSqlError($connection);

    while ($row_settings = mysqli_fetch_object($result_settings)) {

        $_SESSION['s_system_full_url'] = $row_settings->full_url;
        $_SESSION['s_system_db_version'] = (string) $row_settings->db_version;
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
        $_SESSION['s_system_expiration_days'] = $row_settings->expiration_days;

    }

    $sql_user_settings = "SELECT *
                          FROM user_settings
                          ORDER BY id DESC
                          LIMIT 1";
    $result_user_settings = mysqli_query($connection, $sql_user_settings) or $error->outputOldSqlError($connection);

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

    $sql_currencies = "SELECT `name`, symbol, symbol_order, symbol_space
                       FROM currencies
                       WHERE currency = '" . $_SESSION['s_default_currency'] . "'";
    $result_currencies = mysqli_query($connection, $sql_currencies) or $error->outputOldSqlError($connection);

    while ($row_currencies = mysqli_fetch_object($result_currencies)) {
        
        $_SESSION['s_default_currency_name'] = $row_currencies->name;
        $_SESSION['s_default_currency_symbol'] = $row_currencies->symbol;
        $_SESSION['s_default_currency_symbol_order'] = $row_currencies->symbol_order;
        $_SESSION['s_default_currency_symbol_space'] = $row_currencies->symbol_space;
    
    }

    // Without this, the "DomainMOD is not yet installed" message will continue to display after installation. The header isn't displayed on the install file, which is when this normally unsets.
    unset($_SESSION['s_message_danger']);

    $_SESSION['s_installation_mode'] = '0';
    $_SESSION['s_message_success'] .= $software_title . " has been installed and you should now delete the /install/ folder<BR><BR>The default username and password are \"admin\"<BR>";
    
    header("Location: ../");
    exit;

}
