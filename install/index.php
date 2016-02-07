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

$error = new DomainMOD\Error();
$system = new DomainMOD\System();
$time = new DomainMOD\Time();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "database.inc.php");

$system->installCheck($connection, $web_root);

if (mysqli_num_rows(mysqli_query($connection, "SHOW TABLES LIKE '" . settings . "'"))) {

    $_SESSION['s_result_message'] = "$software_title is already installed<BR><BR>You should delete the /install/ folder<BR>";

    header("Location: ../");
    exit;

} else {

    $_SESSION['s_installation_mode'] = '1';

    $sql = "ALTER DATABASE " . $dbname . "
            CHARACTER SET utf8
            DEFAULT CHARACTER SET utf8
            COLLATE utf8_unicode_ci
            DEFAULT COLLATE utf8_unicode_ci;";
    $result = mysqli_query($connection, $sql);

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
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO `users`
            (`first_name`, `last_name`, `username`, `email_address`, `password`, `admin`, `insert_time`) VALUES
            ('Domain', 'Administrator', 'admin', 'admin@domainmod.org', '*4ACFE3202A5FF5CF467898FC58AAB1D615029441', '1', '" . $time->time() . "');";
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
                `display_domain_tld` INT(1) NOT NULL DEFAULT '0',
                `display_domain_fee` INT(1) NOT NULL DEFAULT '0',
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
    $result = mysqli_query($connection, $sql);

    while ($row = mysqli_fetch_object($result)) {
        $sql_temp = "INSERT INTO user_settings
                     (user_id, default_currency, insert_time) VALUES
                     ('$row->id', 'USD', '" . $time->time() . "');";
        $result_temp = mysqli_query($connection, $sql_temp);
    }

    $sql = "CREATE TABLE IF NOT EXISTS `categories` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(150) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `stakeholder` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO `categories`
            (`name`, `stakeholder`, `insert_time`) VALUES
            ('[no category]', '[no stakeholder]', '" . $time->time() . "');";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `hosting` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `url` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO `hosting`
            (`name`, `insert_time`) VALUES
            ('[no hosting]', '" . $time->time() . "');";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `owners` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`),
                KEY `name` (`name`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO `owners`
            (`name`, `insert_time`) VALUES
            ('[no owner]', '" . $time->time() . "');";
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
            (name, currency, symbol, insert_time) VALUES
            ('Albania Lek', 'ALL', 'Lek', '" . $time->time() . "'),
            ('Afghanistan Afghani', 'AFN', '؋', '" . $time->time() . "'),
            ('Argentina Peso', 'ARS', '$', '" . $time->time() . "'),
            ('Aruba Guilder', 'AWG', 'ƒ', '" . $time->time() . "'),
            ('Australia Dollar', 'AUD', '$', '" . $time->time() . "'),
            ('Azerbaijan New Manat', 'AZN', '" . 'ман' . "', '" . $time->time() . "'),
            ('Bahamas Dollar', 'BSD', '$', '" . $time->time() . "'),
            ('Barbados Dollar', 'BBD', '$', '" . $time->time() . "'),
            ('Belarus Ruble', 'BYR', 'p.', '" . $time->time() . "'),
            ('Belize Dollar', 'BZD', 'BZ$', '" . $time->time() . "'),
            ('Bermuda Dollar', 'BMD', '$', '" . $time->time() . "'),
            ('Bolivia Boliviano', 'BOB', '\$b', '" . $time->time() . "'),
            ('Bosnia and Herzegovina Convertible Marka', 'BAM', 'KM', '" . $time->time() . "'),
            ('Botswana Pula', 'BWP', 'P', '" . $time->time() . "'),
            ('Bulgaria Lev', 'BGN', 'лв', '" . $time->time() . "'),
            ('Brazil Real', 'BRL', 'R$', '" . $time->time() . "'),
            ('Brunei Darussalam Dollar', 'BND', '$', '" . $time->time() . "'),
            ('Cambodia Riel', 'KHR', '៛', '" . $time->time() . "'),
            ('Canada Dollar', 'CAD', '$', '" . $time->time() . "'),
            ('Cayman Islands Dollar', 'KYD', '$', '" . $time->time() . "'),
            ('Chile Peso', 'CLP', '$', '" . $time->time() . "'),
            ('China Yuan Renminbi', 'CNY', '¥', '" . $time->time() . "'),
            ('Colombia Peso', 'COP', '$', '" . $time->time() . "'),
            ('Costa Rica Colon', 'CRC', '₡', '" . $time->time() . "'),
            ('Croatia Kuna', 'HRK', 'kn', '" . $time->time() . "'),
            ('Cuba Peso', 'CUP', '₱', '" . $time->time() . "'),
            ('Czech Republic Koruna', 'CZK', 'Kč', '" . $time->time() . "'),
            ('Denmark Krone', 'DKK', 'kr', '" . $time->time() . "'),
            ('Dominican Republic Peso', 'DOP', 'RD$', '" . $time->time() . "'),
            ('East Caribbean Dollar', 'XCD', '$', '" . $time->time() . "'),
            ('Egypt Pound', 'EGP', '£', '" . $time->time() . "'),
            ('El Salvador Colon', 'SVC', '$', '" . $time->time() . "'),
            ('Estonia Kroon', 'EEK', 'kr', '" . $time->time() . "'),
            ('Euro Member Countries', 'EUR', '€', '" . $time->time() . "'),
            ('Falkland Islands (Malvinas) Pound', 'FKP', '£', '" . $time->time() . "'),
            ('Fiji Dollar', 'FJD', '$', '" . $time->time() . "'),
            ('Ghana Cedis', 'GHC', '¢', '" . $time->time() . "'),
            ('Gibraltar Pound', 'GIP', '£', '" . $time->time() . "'),
            ('Guatemala Quetzal', 'GTQ', 'Q', '" . $time->time() . "'),
            ('Guernsey Pound', 'GGP', '£', '" . $time->time() . "'),
            ('Guyana Dollar', 'GYD', '$', '" . $time->time() . "'),
            ('Honduras Lempira', 'HNL', 'L', '" . $time->time() . "'),
            ('Hong Kong Dollar', 'HKD', '$', '" . $time->time() . "'),
            ('Hungary Forint', 'HUF', 'Ft', '" . $time->time() . "'),
            ('Iceland Krona', 'ISK', 'kr', '" . $time->time() . "'),
            ('India Rupee', 'INR', 'Rs', '" . $time->time() . "'),
            ('Indonesia Rupiah', 'IDR', 'Rp', '" . $time->time() . "'),
            ('Iran Rial', 'IRR', '﷼', '" . $time->time() . "'),
            ('Isle of Man Pound', 'IMP', '£', '" . $time->time() . "'),
            ('Israel Shekel', 'ILS', '₪', '" . $time->time() . "'),
            ('Jamaica Dollar', 'JMD', 'J$', '" . $time->time() . "'),
            ('Japan Yen', 'JPY', '¥', '" . $time->time() . "'),
            ('Jersey Pound', 'JEP', '£', '" . $time->time() . "'),
            ('Kazakhstan Tenge', 'KZT', 'лв', '" . $time->time() . "'),
            ('Korea (North) Won', 'KPW', '₩', '" . $time->time() . "'),
            ('Korea (South) Won', 'KRW', '₩', '" . $time->time() . "'),
            ('Kyrgyzstan Som', 'KGS', 'лв', '" . $time->time() . "'),
            ('Laos Kip', 'LAK', '₭', '" . $time->time() . "'),
            ('Latvia Lat', 'LVL', 'Ls', '" . $time->time() . "'),
            ('Lebanon Pound', 'LBP', '£', '" . $time->time() . "'),
            ('Liberia Dollar', 'LRD', '$', '" . $time->time() . "'),
            ('Lithuania Litas', 'LTL', 'Lt', '" . $time->time() . "'),
            ('Macedonia Denar', 'MKD', 'ден', '" . $time->time() . "'),
            ('Malaysia Ringgit', 'RM', 'RM', '" . $time->time() . "'),
            ('Mauritius Rupee', 'MUR', '₨', '" . $time->time() . "'),
            ('Mexico Peso', 'MXN', '$', '" . $time->time() . "'),
            ('Mongolia Tughrik', 'MNT', '₮', '" . $time->time() . "'),
            ('Mozambique Metical', 'MZN', 'MT', '" . $time->time() . "'),
            ('Namibia Dollar', 'NAD', '$', '" . $time->time() . "'),
            ('Nepal Rupee', 'NPR', '₨', '" . $time->time() . "'),
            ('Netherlands Antilles Guilder', 'ANG', 'ƒ', '" . $time->time() . "'),
            ('New Zealand Dollar', 'NZD', '$', '" . $time->time() . "'),
            ('Nicaragua Cordoba', 'NIO', 'C$', '" . $time->time() . "'),
            ('Nigeria Naira', 'NGN', '₦', '" . $time->time() . "'),
            ('Norway Krone', 'NOK', 'kr', '" . $time->time() . "'),
            ('Oman Rial', 'OMR', '﷼', '" . $time->time() . "'),
            ('Pakistan Rupee', 'PKR', '₨', '" . $time->time() . "'),
            ('Panama Balboa', 'PAB', 'B/.', '" . $time->time() . "'),
            ('Paraguay Guarani', 'PYG', 'Gs', '" . $time->time() . "'),
            ('Peru Nuevo Sol', 'PEN', 'S/.', '" . $time->time() . "'),
            ('Philippines Peso', 'PHP', '₱', '" . $time->time() . "'),
            ('Poland Zloty', 'PLN', 'zł', '" . $time->time() . "'),
            ('Qatar Riyal', 'QAR', '﷼', '" . $time->time() . "'),
            ('Romania New Leu', 'RON', 'lei', '" . $time->time() . "'),
            ('Russia Ruble', 'RUB', 'руб', '" . $time->time() . "'),
            ('Saint Helena Pound', 'SHP', '£', '" . $time->time() . "'),
            ('Saudi Arabia Riyal', 'SAR', '﷼', '" . $time->time() . "'),
            ('Serbia Dinar', 'RSD', 'Дин.', '" . $time->time() . "'),
            ('Seychelles Rupee', 'SCR', '₨', '" . $time->time() . "'),
            ('Singapore Dollar', 'SGD', '$', '" . $time->time() . "'),
            ('Solomon Islands Dollar', 'SBD', '$', '" . $time->time() . "'),
            ('Somalia Shilling', 'SOS', 'S', '" . $time->time() . "'),
            ('South Africa Rand', 'ZAR', 'R', '" . $time->time() . "'),
            ('Sri Lanka Rupee', 'LKR', '₨', '" . $time->time() . "'),
            ('Sweden Krona', 'SEK', 'kr', '" . $time->time() . "'),
            ('Switzerland Franc', 'CHF', 'CHF', '" . $time->time() . "'),
            ('Suriname Dollar', 'SRD', '$', '" . $time->time() . "'),
            ('Syria Pound', 'SYP', '£', '" . $time->time() . "'),
            ('Taiwan New Dollar', 'TWD', 'NT$', '" . $time->time() . "'),
            ('Thailand Baht', 'THB', '฿', '" . $time->time() . "'),
            ('Trinidad and Tobago Dollar', 'TTD', 'TT$', '" . $time->time() . "'),
            ('Turkey Lira', 'TRY', '₤', '" . $time->time() . "'),
            ('Tuvalu Dollar', 'TVD', '$', '" . $time->time() . "'),
            ('Ukraine Hryvna', 'UAH', '₴', '" . $time->time() . "'),
            ('United Kingdom Pound', 'GBP', '£', '" . $time->time() . "'),
            ('United States Dollar', 'USD', '$', '" . $time->time() . "'),
            ('Uruguay Peso', 'UYU', '\$U', '" . $time->time() . "'),
            ('Uzbekistan Som', 'UZS', 'лв', '" . $time->time() . "'),
            ('Venezuela Bolivar', 'VEF', 'Bs', '" . $time->time() . "'),
            ('Viet Nam Dong', 'VND', '₫', '" . $time->time() . "'),
            ('Yemen Rial', 'YER', '﷼', '" . $time->time() . "'),
            ('Zimbabwe Dollar', 'ZWD', 'Z$', '" . $time->time() . "'),
            ('Emirati Dirham', 'AED', 'د.إ', '" . $time->time() . "'),
            ('Malaysian Ringgit', 'MYR', 'RM', '" . $time->time() . "'),
            ('Kuwaiti Dinar', 'KWD', 'ك', '" . $time->time() . "'),
            ('Moroccan Dirham', 'MAD', 'م.', '" . $time->time() . "'),
            ('Iraqi Dinar', 'IQD', 'د.ع', '" . $time->time() . "'),
            ('Bangladeshi Taka', 'BDT', 'Tk', '" . $time->time() . "'),
            ('Bahraini Dinar', 'BHD', 'BD', '" . $time->time() . "'),
            ('Kenyan Shilling', 'KES', 'KSh', '" . $time->time() . "'),
            ('CFA Franc', 'XOF', 'CFA', '" . $time->time() . "'),
            ('Jordanian Dinar', 'JOD', 'JD', '" . $time->time() . "'),
            ('Tunisian Dinar', 'TND', 'د.ت', '" . $time->time() . "'),
            ('Ghanaian Cedi', 'GHS', 'GH¢', '" . $time->time() . "'),
            ('Central African CFA Franc BEAC', 'XAF', 'FCFA', '" . $time->time() . "'),
            ('Algerian Dinar', 'DZD', 'دج', '" . $time->time() . "'),
            ('CFP Franc', 'XPF', 'F', '" . $time->time() . "'),
            ('Ugandan Shilling', 'UGX', 'USh', '" . $time->time() . "'),
            ('Tanzanian Shilling', 'TZS', 'TZS', '" . $time->time() . "'),
            ('Ethiopian Birr', 'ETB', 'Br', '" . $time->time() . "'),
            ('Georgian Lari', 'GEL', 'GEL', '" . $time->time() . "'),
            ('Cuban Convertible Peso', 'CUC', 'CUC$', '" . $time->time() . "'),
            ('Burmese Kyat', 'MMK', 'K', '" . $time->time() . "'),
            ('Libyan Dinar', 'LYD', 'LD', '" . $time->time() . "'),
            ('Zambian Kwacha', 'ZMK', 'ZK', '" . $time->time() . "'),
            ('Zambian Kwacha', 'ZMW', 'ZK', '" . $time->time() . "'),
            ('Macau Pataca', 'MOP', 'MOP$', '" . $time->time() . "'),
            ('Armenian Dram', 'AMD', 'AMD', '" . $time->time() . "'),
            ('Angolan Kwanza', 'AOA', 'Kz', '" . $time->time() . "'),
            ('Papua New Guinean Kina', 'PGK', 'K', '" . $time->time() . "'),
            ('Malagasy Ariary', 'MGA', 'Ar', '" . $time->time() . "'),
            ('Ni-Vanuatu Vatu', 'VUV', 'VT', '" . $time->time() . "'),
            ('Sudanese Pound', 'SDG', 'SDG', '" . $time->time() . "'),
            ('Malawian Kwacha', 'MWK', 'MK', '" . $time->time() . "'),
            ('Rwandan Franc', 'RWF', 'FRw', '" . $time->time() . "'),
            ('Gambian Dalasi', 'GMD', 'D', '" . $time->time() . "'),
            ('Maldivian Rufiyaa', 'MVR', 'Rf', '" . $time->time() . "'),
            ('Congolese Franc', 'CDF', 'FC', '" . $time->time() . "'),
            ('Djiboutian Franc', 'DJF', 'Fdj', '" . $time->time() . "'),
            ('Haitian Gourde', 'HTG', 'G', '" . $time->time() . "'),
            ('Samoan Tala', 'WST', '$', '" . $time->time() . "'),
            ('Guinean Franc', 'GNF', 'FG', '" . $time->time() . "'),
            ('Cape Verdean Escudo', 'CVE', '$', '" . $time->time() . "'),
            ('Tongan Pa\'anga', 'TOP', 'T$', '" . $time->time() . "'),
            ('Moldovan Leu', 'MDL', 'MDL', '" . $time->time() . "'),
            ('Sierra Leonean Leone', 'SLL', 'Le', '" . $time->time() . "'),
            ('Burundian Franc', 'BIF', 'FBu', '" . $time->time() . "'),
            ('Mauritanian Ouguiya', 'MRO', 'UM', '" . $time->time() . "'),
            ('Bhutanese Ngultrum', 'BTN', 'Nu.', '" . $time->time() . "'),
            ('Swazi Lilangeni', 'SZL', 'SZL', '" . $time->time() . "'),
            ('Tajikistani Somoni', 'TJS', 'TJS', '" . $time->time() . "'),
            ('Turkmenistani Manat', 'TMT', 'm', '" . $time->time() . "'),
            ('Basotho Loti', 'LSL', 'LSL', '" . $time->time() . "'),
            ('Comoran Franc', 'KMF', 'CF', '" . $time->time() . "'),
            ('Sao Tomean Dobra', 'STD', 'STD', '" . $time->time() . "'),
            ('Seborgan Luigino', 'SPL', 'SPL', '" . $time->time() . "')";
    $result = mysqli_query($connection, $sql);

    $sql = "CREATE TABLE IF NOT EXISTS `currency_conversions` (
            `id` INT(10) NOT NULL AUTO_INCREMENT,
            `currency_id` INT(10) NOT NULL,
            `user_id` INT(10) NOT NULL,
            `conversion` FLOAT NOT NULL,
            `insert_time` DATETIME NOT NULL,
            `update_time` DATETIME NOT NULL,
            PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql);

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
                `privacy` INT(1) NOT NULL DEFAULT '0',
                `active` INT(2) NOT NULL DEFAULT '1',
                `fee_fixed` INT(1) NOT NULL,
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`),
                KEY `domain` (`domain`)
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
            (id, name, insert_time) VALUES
            (1, 'Check Box', '" . $time->time() . "'),
            (2, 'Text', '" . $time->time() . "'),
            (3, 'Text Area', '" . $time->time() . "')";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `domain_fields` (
            `id` INT(10) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(75) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `field_name` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `type_id` INT(10) NOT NULL,
            `description` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
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
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `ssl_cert_types` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `type` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO `ssl_cert_types`
            (`id`, `type`, `insert_time`) VALUES
            (1, 'Web Server SSL/TLS Certificate', '" . $time->time() . "'),
            (2, 'S/MIME and Authentication Certificate', '" . $time->time() . "'),
            (3, 'Object Code Signing Certificate', '" . $time->time() . "'),
            (4, 'Digital ID', '" . $time->time() . "');";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `ssl_cert_fields` (
            `id` INT(10) NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(75) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `field_name` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `type_id` INT(10) NOT NULL,
            `description` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
            `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
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
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO `dns`
            (`name`, `dns1`, `dns2`, `number_of_servers`, `insert_time`) VALUES
            ('[no dns]', 'ns1.no-dns.com', 'ns2.no-dns.com', '2', '" . $time->time() . "');";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `registrars` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `url` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`),
                KEY `name` (`name`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `ssl_providers` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `url` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `registrar_accounts` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `owner_id` INT(10) NOT NULL,
                `registrar_id` INT(10) NOT NULL,
                `username` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `password` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `reseller` INT(1) NOT NULL DEFAULT '0',
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`),
                KEY `registrar_id` (`registrar_id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `ssl_accounts` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `owner_id` INT(10) NOT NULL,
                `ssl_provider_id` INT(10) NOT NULL,
                `username` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `password` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `reseller` INT(1) NOT NULL DEFAULT '0',
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
                `rdns` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '-',
                `notes` LONGTEXT CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "INSERT INTO `ip_addresses`
            (`id`, `name`, `ip`, `rdns`, `insert_time`) VALUES
            ('1', '[no ip address]', '-', '-', '" . $time->time() . "');";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql = "CREATE TABLE IF NOT EXISTS `settings` (
                `id` INT(10) NOT NULL AUTO_INCREMENT,
                `full_url` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'http://',
                `db_version` VARCHAR(12) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
                `upgrade_available` INT(1) NOT NULL DEFAULT '0',
                `email_address` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
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
                `expiration_email_days` INT(3) NOT NULL DEFAULT '60',
                `insert_time` DATETIME NOT NULL,
                `update_time` DATETIME NOT NULL,
                PRIMARY KEY  (`id`)
            ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $full_url = substr($_SERVER["HTTP_REFERER"], 0, -1);

    $temp_system_email = "support@" . $_SERVER["HTTP_HOST"];

    $sql = "INSERT INTO `settings`
            (`full_url`, `db_version`, `email_address`, `insert_time`) VALUES
            ('" . $full_url . "', '" . $db_version . "', '" . $temp_system_email . "', '" . $time->time() . "');";
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
            ('Africa/Abidjan', '" . $time->time() . "'), ('Africa/Accra', '" . $time->time() . "'), ('Africa/Addis_Ababa', '" . $time->time() . "'), ('Africa/Algiers', '" . $time->time() . "'), ('Africa/Asmara', '" . $time->time() . "'), ('Africa/Asmera', '" . $time->time() . "'), ('Africa/Bamako', '" . $time->time() . "'), ('Africa/Bangui', '" . $time->time() . "'), ('Africa/Banjul', '" . $time->time() . "'), ('Africa/Bissau', '" . $time->time() . "'), ('Africa/Blantyre', '" . $time->time() . "'), ('Africa/Brazzaville', '" . $time->time() . "'), ('Africa/Bujumbura', '" . $time->time() . "'), ('Africa/Cairo', '" . $time->time() . "'), ('Africa/Casablanca', '" . $time->time() . "'), ('Africa/Ceuta', '" . $time->time() . "'), ('Africa/Conakry', '" . $time->time() . "'), ('Africa/Dakar', '" . $time->time() . "'), ('Africa/Dar_es_Salaam', '" . $time->time() . "'), ('Africa/Djibouti', '" . $time->time() . "'), ('Africa/Douala', '" . $time->time() . "'), ('Africa/El_Aaiun', '" . $time->time() . "'), ('Africa/Freetown', '" . $time->time() . "'), ('Africa/Gaborone', '" . $time->time() . "'), ('Africa/Harare', '" . $time->time() . "'), ('Africa/Johannesburg', '" . $time->time() . "'), ('Africa/Juba', '" . $time->time() . "'), ('Africa/Kampala', '" . $time->time() . "'), ('Africa/Khartoum', '" . $time->time() . "'), ('Africa/Kigali', '" . $time->time() . "'), ('Africa/Kinshasa', '" . $time->time() . "'), ('Africa/Lagos', '" . $time->time() . "'), ('Africa/Libreville', '" . $time->time() . "'), ('Africa/Lome', '" . $time->time() . "'), ('Africa/Luanda', '" . $time->time() . "'), ('Africa/Lubumbashi', '" . $time->time() . "'), ('Africa/Lusaka', '" . $time->time() . "'), ('Africa/Malabo', '" . $time->time() . "'), ('Africa/Maputo', '" . $time->time() . "'), ('Africa/Maseru', '" . $time->time() . "'), ('Africa/Mbabane', '" . $time->time() . "'), ('Africa/Mogadishu', '" . $time->time() . "'), ('Africa/Monrovia', '" . $time->time() . "'), ('Africa/Nairobi', '" . $time->time() . "'), ('Africa/Ndjamena', '" . $time->time() . "'), ('Africa/Niamey', '" . $time->time() . "'), ('Africa/Nouakchott', '" . $time->time() . "'), ('Africa/Ouagadougou', '" . $time->time() . "'), ('Africa/Porto-Novo', '" . $time->time() . "'), ('Africa/Sao_Tome', '" . $time->time() . "'), ('Africa/Timbuktu', '" . $time->time() . "'), ('Africa/Tripoli', '" . $time->time() . "'), ('Africa/Tunis', '" . $time->time() . "'), ('Africa/Windhoek', '" . $time->time() . "'), ('America/Adak', '" . $time->time() . "'), ('America/Anchorage', '" . $time->time() . "'), ('America/Anguilla', '" . $time->time() . "'), ('America/Antigua', '" . $time->time() . "'), ('America/Araguaina', '" . $time->time() . "'), ('America/Argentina/Buenos_Aires', '" . $time->time() . "'), ('America/Argentina/Catamarca', '" . $time->time() . "'), ('America/Argentina/ComodRivadavia', '" . $time->time() . "'), ('America/Argentina/Cordoba', '" . $time->time() . "'), ('America/Argentina/Jujuy', '" . $time->time() . "'), ('America/Argentina/La_Rioja', '" . $time->time() . "'), ('America/Argentina/Mendoza', '" . $time->time() . "'), ('America/Argentina/Rio_Gallegos', '" . $time->time() . "'), ('America/Argentina/Salta', '" . $time->time() . "'), ('America/Argentina/San_Juan', '" . $time->time() . "'), ('America/Argentina/San_Luis', '" . $time->time() . "'), ('America/Argentina/Tucuman', '" . $time->time() . "'), ('America/Argentina/Ushuaia', '" . $time->time() . "'), ('America/Aruba', '" . $time->time() . "'), ('America/Asuncion', '" . $time->time() . "'), ('America/Atikokan', '" . $time->time() . "'), ('America/Atka', '" . $time->time() . "'), ('America/Bahia', '" . $time->time() . "'), ('America/Bahia_Banderas', '" . $time->time() . "'), ('America/Barbados', '" . $time->time() . "'), ('America/Belem', '" . $time->time() . "'), ('America/Belize', '" . $time->time() . "'), ('America/Blanc-Sablon', '" . $time->time() . "'), ('America/Boa_Vista', '" . $time->time() . "'), ('America/Bogota', '" . $time->time() . "'), ('America/Boise', '" . $time->time() . "'), ('America/Buenos_Aires', '" . $time->time() . "'), ('America/Cambridge_Bay', '" . $time->time() . "'), ('America/Campo_Grande', '" . $time->time() . "'), ('America/Cancun', '" . $time->time() . "'), ('America/Caracas', '" . $time->time() . "'), ('America/Catamarca', '" . $time->time() . "'), ('America/Cayenne', '" . $time->time() . "'), ('America/Cayman', '" . $time->time() . "'), ('America/Chicago', '" . $time->time() . "'), ('America/Chihuahua', '" . $time->time() . "'), ('America/Coral_Harbour', '" . $time->time() . "'), ('America/Cordoba', '" . $time->time() . "'), ('America/Costa_Rica', '" . $time->time() . "'), ('America/Creston', '" . $time->time() . "'), ('America/Cuiaba', '" . $time->time() . "'), ('America/Curacao', '" . $time->time() . "'), ('America/Danmarkshavn', '" . $time->time() . "'), ('America/Dawson', '" . $time->time() . "'), ('America/Dawson_Creek', '" . $time->time() . "'), ('America/Denver', '" . $time->time() . "'), ('America/Detroit', '" . $time->time() . "'), ('America/Dominica', '" . $time->time() . "'), ('America/Edmonton', '" . $time->time() . "'), ('America/Eirunepe', '" . $time->time() . "'), ('America/El_Salvador', '" . $time->time() . "'), ('America/Ensenada', '" . $time->time() . "'), ('America/Fort_Wayne', '" . $time->time() . "'), ('America/Fortaleza', '" . $time->time() . "'), ('America/Glace_Bay', '" . $time->time() . "'), ('America/Godthab', '" . $time->time() . "'), ('America/Goose_Bay', '" . $time->time() . "'), ('America/Grand_Turk', '" . $time->time() . "'), ('America/Grenada', '" . $time->time() . "'), ('America/Guadeloupe', '" . $time->time() . "'), ('America/Guatemala', '" . $time->time() . "'), ('America/Guayaquil', '" . $time->time() . "'), ('America/Guyana', '" . $time->time() . "'), ('America/Halifax', '" . $time->time() . "'), ('America/Havana', '" . $time->time() . "'), ('America/Hermosillo', '" . $time->time() . "'), ('America/Indiana/Indianapolis', '" . $time->time() . "'), ('America/Indiana/Knox', '" . $time->time() . "'), ('America/Indiana/Marengo', '" . $time->time() . "'), ('America/Indiana/Petersburg', '" . $time->time() . "'), ('America/Indiana/Tell_City', '" . $time->time() . "'), ('America/Indiana/Vevay', '" . $time->time() . "'), ('America/Indiana/Vincennes', '" . $time->time() . "'), ('America/Indiana/Winamac', '" . $time->time() . "'), ('America/Indianapolis', '" . $time->time() . "'), ('America/Inuvik', '" . $time->time() . "'), ('America/Iqaluit', '" . $time->time() . "'), ('America/Jamaica', '" . $time->time() . "'), ('America/Jujuy', '" . $time->time() . "'), ('America/Juneau', '" . $time->time() . "'), ('America/Kentucky/Louisville', '" . $time->time() . "'), ('America/Kentucky/Monticello', '" . $time->time() . "'), ('America/Knox_IN', '" . $time->time() . "'), ('America/Kralendijk', '" . $time->time() . "'), ('America/La_Paz', '" . $time->time() . "'), ('America/Lima', '" . $time->time() . "'), ('America/Los_Angeles', '" . $time->time() . "'), ('America/Louisville', '" . $time->time() . "'), ('America/Lower_Princes', '" . $time->time() . "'), ('America/Maceio', '" . $time->time() . "'), ('America/Managua', '" . $time->time() . "'), ('America/Manaus', '" . $time->time() . "'), ('America/Marigot', '" . $time->time() . "'), ('America/Martinique', '" . $time->time() . "'), ('America/Matamoros', '" . $time->time() . "'), ('America/Mazatlan', '" . $time->time() . "'), ('America/Mendoza', '" . $time->time() . "'), ('America/Menominee', '" . $time->time() . "'), ('America/Merida', '" . $time->time() . "'), ('America/Metlakatla', '" . $time->time() . "'), ('America/Mexico_City', '" . $time->time() . "'), ('America/Miquelon', '" . $time->time() . "'), ('America/Moncton', '" . $time->time() . "'), ('America/Monterrey', '" . $time->time() . "'), ('America/Montevideo', '" . $time->time() . "'), ('America/Montreal', '" . $time->time() . "'), ('America/Montserrat', '" . $time->time() . "'), ('America/Nassau', '" . $time->time() . "'), ('America/New_York', '" . $time->time() . "'), ('America/Nipigon', '" . $time->time() . "'), ('America/Nome', '" . $time->time() . "'), ('America/Noronha', '" . $time->time() . "'), ('America/North_Dakota/Beulah', '" . $time->time() . "'), ('America/North_Dakota/Center', '" . $time->time() . "'), ('America/North_Dakota/New_Salem', '" . $time->time() . "'), ('America/Ojinaga', '" . $time->time() . "'), ('America/Panama', '" . $time->time() . "'), ('America/Pangnirtung', '" . $time->time() . "'), ('America/Paramaribo', '" . $time->time() . "'), ('America/Phoenix', '" . $time->time() . "'), ('America/Port-au-Prince', '" . $time->time() . "'), ('America/Port_of_Spain', '" . $time->time() . "'), ('America/Porto_Acre', '" . $time->time() . "'), ('America/Porto_Velho', '" . $time->time() . "'), ('America/Puerto_Rico', '" . $time->time() . "'), ('America/Rainy_River', '" . $time->time() . "'), ('America/Rankin_Inlet', '" . $time->time() . "'), ('America/Recife', '" . $time->time() . "'), ('America/Regina', '" . $time->time() . "'), ('America/Resolute', '" . $time->time() . "'), ('America/Rio_Branco', '" . $time->time() . "'), ('America/Rosario', '" . $time->time() . "'), ('America/Santa_Isabel', '" . $time->time() . "'), ('America/Santarem', '" . $time->time() . "'), ('America/Santiago', '" . $time->time() . "'), ('America/Santo_Domingo', '" . $time->time() . "'), ('America/Sao_Paulo', '" . $time->time() . "'), ('America/Scoresbysund', '" . $time->time() . "'), ('America/Shiprock', '" . $time->time() . "'), ('America/Sitka', '" . $time->time() . "'), ('America/St_Barthelemy', '" . $time->time() . "'), ('America/St_Johns', '" . $time->time() . "'), ('America/St_Kitts', '" . $time->time() . "'), ('America/St_Lucia', '" . $time->time() . "'), ('America/St_Thomas', '" . $time->time() . "'), ('America/St_Vincent', '" . $time->time() . "'), ('America/Swift_Current', '" . $time->time() . "'), ('America/Tegucigalpa', '" . $time->time() . "'), ('America/Thule', '" . $time->time() . "'), ('America/Thunder_Bay', '" . $time->time() . "'), ('America/Tijuana', '" . $time->time() . "'), ('America/Toronto', '" . $time->time() . "'), ('America/Tortola', '" . $time->time() . "'), ('America/Vancouver', '" . $time->time() . "'), ('America/Virgin', '" . $time->time() . "'), ('America/Whitehorse', '" . $time->time() . "'), ('America/Winnipeg', '" . $time->time() . "'), ('America/Yakutat', '" . $time->time() . "'), ('America/Yellowknife', '" . $time->time() . "'), ('Antarctica/Casey', '" . $time->time() . "'), ('Antarctica/Davis', '" . $time->time() . "'), ('Antarctica/DumontDUrville', '" . $time->time() . "'), ('Antarctica/Macquarie', '" . $time->time() . "'), ('Antarctica/Mawson', '" . $time->time() . "'), ('Antarctica/McMurdo', '" . $time->time() . "'), ('Antarctica/Palmer', '" . $time->time() . "'), ('Antarctica/Rothera', '" . $time->time() . "'), ('Antarctica/South_Pole', '" . $time->time() . "'), ('Antarctica/Syowa', '" . $time->time() . "'), ('Antarctica/Vostok', '" . $time->time() . "'), ('Arctic/Longyearbyen', '" . $time->time() . "'), ('Asia/Aden', '" . $time->time() . "'), ('Asia/Almaty', '" . $time->time() . "'), ('Asia/Amman', '" . $time->time() . "'), ('Asia/Anadyr', '" . $time->time() . "'), ('Asia/Aqtau', '" . $time->time() . "'), ('Asia/Aqtobe', '" . $time->time() . "'), ('Asia/Ashgabat', '" . $time->time() . "'), ('Asia/Ashkhabad', '" . $time->time() . "'), ('Asia/Baghdad', '" . $time->time() . "'), ('Asia/Bahrain', '" . $time->time() . "'), ('Asia/Baku', '" . $time->time() . "'), ('Asia/Bangkok', '" . $time->time() . "'), ('Asia/Beirut', '" . $time->time() . "'), ('Asia/Bishkek', '" . $time->time() . "'), ('Asia/Brunei', '" . $time->time() . "'), ('Asia/Calcutta', '" . $time->time() . "'), ('Asia/Choibalsan', '" . $time->time() . "'), ('Asia/Chongqing', '" . $time->time() . "'), ('Asia/Chungking', '" . $time->time() . "'), ('Asia/Colombo', '" . $time->time() . "'), ('Asia/Dacca', '" . $time->time() . "'), ('Asia/Damascus', '" . $time->time() . "'), ('Asia/Dhaka', '" . $time->time() . "'), ('Asia/Dili', '" . $time->time() . "'), ('Asia/Dubai', '" . $time->time() . "'), ('Asia/Dushanbe', '" . $time->time() . "'), ('Asia/Gaza', '" . $time->time() . "'), ('Asia/Harbin', '" . $time->time() . "'), ('Asia/Hebron', '" . $time->time() . "'), ('Asia/Ho_Chi_Minh', '" . $time->time() . "'), ('Asia/Hong_Kong', '" . $time->time() . "'), ('Asia/Hovd', '" . $time->time() . "'), ('Asia/Irkutsk', '" . $time->time() . "'), ('Asia/Istanbul', '" . $time->time() . "'), ('Asia/Jakarta', '" . $time->time() . "'), ('Asia/Jayapura', '" . $time->time() . "'), ('Asia/Jerusalem', '" . $time->time() . "'), ('Asia/Kabul', '" . $time->time() . "'), ('Asia/Kamchatka', '" . $time->time() . "'), ('Asia/Karachi', '" . $time->time() . "'), ('Asia/Kashgar', '" . $time->time() . "'), ('Asia/Kathmandu', '" . $time->time() . "'), ('Asia/Katmandu', '" . $time->time() . "'), ('Asia/Khandyga', '" . $time->time() . "'), ('Asia/Kolkata', '" . $time->time() . "'), ('Asia/Krasnoyarsk', '" . $time->time() . "'), ('Asia/Kuala_Lumpur', '" . $time->time() . "'), ('Asia/Kuching', '" . $time->time() . "'), ('Asia/Kuwait', '" . $time->time() . "'), ('Asia/Macao', '" . $time->time() . "'), ('Asia/Macau', '" . $time->time() . "'), ('Asia/Magadan', '" . $time->time() . "'), ('Asia/Makassar', '" . $time->time() . "'), ('Asia/Manila', '" . $time->time() . "'), ('Asia/Muscat', '" . $time->time() . "'), ('Asia/Nicosia', '" . $time->time() . "'), ('Asia/Novokuznetsk', '" . $time->time() . "'), ('Asia/Novosibirsk', '" . $time->time() . "'), ('Asia/Omsk', '" . $time->time() . "'), ('Asia/Oral', '" . $time->time() . "'), ('Asia/Phnom_Penh', '" . $time->time() . "'), ('Asia/Pontianak', '" . $time->time() . "'), ('Asia/Pyongyang', '" . $time->time() . "'), ('Asia/Qatar', '" . $time->time() . "'), ('Asia/Qyzylorda', '" . $time->time() . "'), ('Asia/Rangoon', '" . $time->time() . "'), ('Asia/Riyadh', '" . $time->time() . "'), ('Asia/Saigon', '" . $time->time() . "'), ('Asia/Sakhalin', '" . $time->time() . "'), ('Asia/Samarkand', '" . $time->time() . "'), ('Asia/Seoul', '" . $time->time() . "'), ('Asia/Shanghai', '" . $time->time() . "'), ('Asia/Singapore', '" . $time->time() . "'), ('Asia/Taipei', '" . $time->time() . "'), ('Asia/Tashkent', '" . $time->time() . "'), ('Asia/Tbilisi', '" . $time->time() . "'), ('Asia/Tehran', '" . $time->time() . "'), ('Asia/Tel_Aviv', '" . $time->time() . "'), ('Asia/Thimbu', '" . $time->time() . "'), ('Asia/Thimphu', '" . $time->time() . "'), ('Asia/Tokyo', '" . $time->time() . "'), ('Asia/Ujung_Pandang', '" . $time->time() . "'), ('Asia/Ulaanbaatar', '" . $time->time() . "'), ('Asia/Ulan_Bator', '" . $time->time() . "'), ('Asia/Urumqi', '" . $time->time() . "'), ('Asia/Ust-Nera', '" . $time->time() . "'), ('Asia/Vientiane', '" . $time->time() . "'), ('Asia/Vladivostok', '" . $time->time() . "'), ('Asia/Yakutsk', '" . $time->time() . "'), ('Asia/Yekaterinburg', '" . $time->time() . "'), ('Asia/Yerevan', '" . $time->time() . "'), ('Atlantic/Azores', '" . $time->time() . "'), ('Atlantic/Bermuda', '" . $time->time() . "'), ('Atlantic/Canary', '" . $time->time() . "'), ('Atlantic/Cape_Verde', '" . $time->time() . "'), ('Atlantic/Faeroe', '" . $time->time() . "'), ('Atlantic/Faroe', '" . $time->time() . "'), ('Atlantic/Jan_Mayen', '" . $time->time() . "'), ('Atlantic/Madeira', '" . $time->time() . "'), ('Atlantic/Reykjavik', '" . $time->time() . "'), ('Atlantic/South_Georgia', '" . $time->time() . "'), ('Atlantic/St_Helena', '" . $time->time() . "'), ('Atlantic/Stanley', '" . $time->time() . "'), ('Australia/ACT', '" . $time->time() . "'), ('Australia/Adelaide', '" . $time->time() . "'), ('Australia/Brisbane', '" . $time->time() . "'), ('Australia/Broken_Hill', '" . $time->time() . "'), ('Australia/Canberra', '" . $time->time() . "'), ('Australia/Currie', '" . $time->time() . "'), ('Australia/Darwin', '" . $time->time() . "'), ('Australia/Eucla', '" . $time->time() . "'), ('Australia/Hobart', '" . $time->time() . "'), ('Australia/LHI', '" . $time->time() . "'), ('Australia/Lindeman', '" . $time->time() . "'), ('Australia/Lord_Howe', '" . $time->time() . "'), ('Australia/Melbourne', '" . $time->time() . "'), ('Australia/North', '" . $time->time() . "'), ('Australia/NSW', '" . $time->time() . "'), ('Australia/Perth', '" . $time->time() . "'), ('Australia/Queensland', '" . $time->time() . "'), ('Australia/South', '" . $time->time() . "'), ('Australia/Sydney', '" . $time->time() . "'), ('Australia/Tasmania', '" . $time->time() . "'), ('Australia/Victoria', '" . $time->time() . "'), ('Australia/West', '" . $time->time() . "'), ('Australia/Yancowinna', '" . $time->time() . "'), ('Brazil/Acre', '" . $time->time() . "'), ('Brazil/DeNoronha', '" . $time->time() . "'), ('Brazil/East', '" . $time->time() . "'), ('Brazil/West', '" . $time->time() . "'), ('Canada/Atlantic', '" . $time->time() . "'), ('Canada/Central', '" . $time->time() . "'), ('Canada/East-Saskatchewan', '" . $time->time() . "'), ('Canada/Eastern', '" . $time->time() . "'), ('Canada/Mountain', '" . $time->time() . "'), ('Canada/Newfoundland', '" . $time->time() . "'), ('Canada/Pacific', '" . $time->time() . "'), ('Canada/Saskatchewan', '" . $time->time() . "'), ('Canada/Yukon', '" . $time->time() . "'), ('Chile/Continental', '" . $time->time() . "'), ('Chile/EasterIsland', '" . $time->time() . "'), ('Cuba', '" . $time->time() . "'), ('Egypt', '" . $time->time() . "'), ('Eire', '" . $time->time() . "'), ('Europe/Amsterdam', '" . $time->time() . "'), ('Europe/Andorra', '" . $time->time() . "'), ('Europe/Athens', '" . $time->time() . "'), ('Europe/Belfast', '" . $time->time() . "'), ('Europe/Belgrade', '" . $time->time() . "'), ('Europe/Berlin', '" . $time->time() . "'), ('Europe/Bratislava', '" . $time->time() . "'), ('Europe/Brussels', '" . $time->time() . "'), ('Europe/Bucharest', '" . $time->time() . "'), ('Europe/Budapest', '" . $time->time() . "'), ('Europe/Busingen', '" . $time->time() . "'), ('Europe/Chisinau', '" . $time->time() . "'), ('Europe/Copenhagen', '" . $time->time() . "'), ('Europe/Dublin', '" . $time->time() . "'), ('Europe/Gibraltar', '" . $time->time() . "'), ('Europe/Guernsey', '" . $time->time() . "'), ('Europe/Helsinki', '" . $time->time() . "'), ('Europe/Isle_of_Man', '" . $time->time() . "'), ('Europe/Istanbul', '" . $time->time() . "'), ('Europe/Jersey', '" . $time->time() . "'), ('Europe/Kaliningrad', '" . $time->time() . "'), ('Europe/Kiev', '" . $time->time() . "'), ('Europe/Lisbon', '" . $time->time() . "'), ('Europe/Ljubljana', '" . $time->time() . "'), ('Europe/London', '" . $time->time() . "'), ('Europe/Luxembourg', '" . $time->time() . "'), ('Europe/Madrid', '" . $time->time() . "'), ('Europe/Malta', '" . $time->time() . "'), ('Europe/Mariehamn', '" . $time->time() . "'), ('Europe/Minsk', '" . $time->time() . "'), ('Europe/Monaco', '" . $time->time() . "'), ('Europe/Moscow', '" . $time->time() . "'), ('Europe/Nicosia', '" . $time->time() . "'), ('Europe/Oslo', '" . $time->time() . "'), ('Europe/Paris', '" . $time->time() . "'), ('Europe/Podgorica', '" . $time->time() . "'), ('Europe/Prague', '" . $time->time() . "'), ('Europe/Riga', '" . $time->time() . "'), ('Europe/Rome', '" . $time->time() . "'), ('Europe/Samara', '" . $time->time() . "'), ('Europe/San_Marino', '" . $time->time() . "'), ('Europe/Sarajevo', '" . $time->time() . "'), ('Europe/Simferopol', '" . $time->time() . "'), ('Europe/Skopje', '" . $time->time() . "'), ('Europe/Sofia', '" . $time->time() . "'), ('Europe/Stockholm', '" . $time->time() . "'), ('Europe/Tallinn', '" . $time->time() . "'), ('Europe/Tirane', '" . $time->time() . "'), ('Europe/Tiraspol', '" . $time->time() . "'), ('Europe/Uzhgorod', '" . $time->time() . "'), ('Europe/Vaduz', '" . $time->time() . "'), ('Europe/Vatican', '" . $time->time() . "'), ('Europe/Vienna', '" . $time->time() . "'), ('Europe/Vilnius', '" . $time->time() . "'), ('Europe/Volgograd', '" . $time->time() . "'), ('Europe/Warsaw', '" . $time->time() . "'), ('Europe/Zagreb', '" . $time->time() . "'), ('Europe/Zaporozhye', '" . $time->time() . "'), ('Europe/Zurich', '" . $time->time() . "'), ('Greenwich', '" . $time->time() . "'), ('Hongkong', '" . $time->time() . "'), ('Iceland', '" . $time->time() . "'), ('Indian/Antananarivo', '" . $time->time() . "'), ('Indian/Chagos', '" . $time->time() . "'), ('Indian/Christmas', '" . $time->time() . "'), ('Indian/Cocos', '" . $time->time() . "'), ('Indian/Comoro', '" . $time->time() . "'), ('Indian/Kerguelen', '" . $time->time() . "'), ('Indian/Mahe', '" . $time->time() . "'), ('Indian/Maldives', '" . $time->time() . "'), ('Indian/Mauritius', '" . $time->time() . "'), ('Indian/Mayotte', '" . $time->time() . "'), ('Indian/Reunion', '" . $time->time() . "'), ('Iran', '" . $time->time() . "'), ('Israel', '" . $time->time() . "'), ('Jamaica', '" . $time->time() . "'), ('Japan', '" . $time->time() . "'), ('Kwajalein', '" . $time->time() . "'), ('Libya', '" . $time->time() . "'), ('Mexico/BajaNorte', '" . $time->time() . "'), ('Mexico/BajaSur', '" . $time->time() . "'), ('Mexico/General', '" . $time->time() . "'), ('Pacific/Apia', '" . $time->time() . "'), ('Pacific/Auckland', '" . $time->time() . "'), ('Pacific/Chatham', '" . $time->time() . "'), ('Pacific/Chuuk', '" . $time->time() . "'), ('Pacific/Easter', '" . $time->time() . "'), ('Pacific/Efate', '" . $time->time() . "'), ('Pacific/Enderbury', '" . $time->time() . "'), ('Pacific/Fakaofo', '" . $time->time() . "'), ('Pacific/Fiji', '" . $time->time() . "'), ('Pacific/Funafuti', '" . $time->time() . "'), ('Pacific/Galapagos', '" . $time->time() . "'), ('Pacific/Gambier', '" . $time->time() . "'), ('Pacific/Guadalcanal', '" . $time->time() . "'), ('Pacific/Guam', '" . $time->time() . "'), ('Pacific/Honolulu', '" . $time->time() . "'), ('Pacific/Johnston', '" . $time->time() . "'), ('Pacific/Kiritimati', '" . $time->time() . "'), ('Pacific/Kosrae', '" . $time->time() . "'), ('Pacific/Kwajalein', '" . $time->time() . "'), ('Pacific/Majuro', '" . $time->time() . "'), ('Pacific/Marquesas', '" . $time->time() . "'), ('Pacific/Midway', '" . $time->time() . "'), ('Pacific/Nauru', '" . $time->time() . "'), ('Pacific/Niue', '" . $time->time() . "'), ('Pacific/Norfolk', '" . $time->time() . "'), ('Pacific/Noumea', '" . $time->time() . "'), ('Pacific/Pago_Pago', '" . $time->time() . "'), ('Pacific/Palau', '" . $time->time() . "'), ('Pacific/Pitcairn', '" . $time->time() . "'), ('Pacific/Pohnpei', '" . $time->time() . "'), ('Pacific/Ponape', '" . $time->time() . "'), ('Pacific/Port_Moresby', '" . $time->time() . "'), ('Pacific/Rarotonga', '" . $time->time() . "'), ('Pacific/Saipan', '" . $time->time() . "'), ('Pacific/Samoa', '" . $time->time() . "'), ('Pacific/Tahiti', '" . $time->time() . "'), ('Pacific/Tarawa', '" . $time->time() . "'), ('Pacific/Tongatapu', '" . $time->time() . "'), ('Pacific/Truk', '" . $time->time() . "'), ('Pacific/Wake', '" . $time->time() . "'), ('Pacific/Wallis', '" . $time->time() . "'), ('Pacific/Yap', '" . $time->time() . "'), ('Poland', '" . $time->time() . "'), ('Portugal', '" . $time->time() . "'), ('Singapore', '" . $time->time() . "'), ('Turkey', '" . $time->time() . "'), ('US/Alaska', '" . $time->time() . "'), ('US/Aleutian', '" . $time->time() . "'), ('US/Arizona', '" . $time->time() . "'), ('US/Central', '" . $time->time() . "'), ('US/East-Indiana', '" . $time->time() . "'), ('US/Eastern', '" . $time->time() . "'), ('US/Hawaii', '" . $time->time() . "'), ('US/Indiana-Starke', '" . $time->time() . "'), ('US/Michigan', '" . $time->time() . "'), ('US/Mountain', '" . $time->time() . "'), ('US/Pacific', '" . $time->time() . "'), ('US/Pacific-New', '" . $time->time() . "'), ('US/Samoa', '" . $time->time() . "'), ('Zulu', '" . $time->time() . "');";
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
                `interval` VARCHAR(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Daily',
                `expression` VARCHAR(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '0 7 * * * *',
                `last_run` DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
                `last_duration` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
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
            (`name`, description, slug, sort_order, is_running, active, insert_time)
             VALUES
            ('Send Expiration Email', 'Sends an email out to everyone who\'s subscribed, letting them know of upcoming Domain & SSL Certificate expirations." . "<" . "BR>" . "<" . "BR>Users can subscribe via " . "<" . "a href=\'../../settings/email.php\'>Email Settings" . "<" . "/a>." . "<" . "BR>" . "<" . "BR>Administrators can set the FROM email address and the number of days in the future to display in the email via " . "<" . "a href=\'../system-settings.php\'>System Settings" . "<" . "/a>.', 'expiration-email', '20', '0', '1', '" . $time->time() . "'),
            ('Update Conversion Rates', 'Retrieves the current currency conversion rates and updates the entire system, which keeps all of the financial information in DomainMOD accurate and up-to-date." . "<" . "BR>" . "<" . "BR>Users can set their default currency via " . "<" . "a href=\'../../settings/defaults.php\'>User Defaults" . "<" . "/a>." . "<" . "BR>" . "<" . "BR>Administrators can set the default system currency via " . "<" . "a href=\'../defaults.php\'>System Defaults" . "<" . "/a>.', 'update-conversion-rates', '40', '0', '1', '" . $time->time() . "'),
            ('System Cleanup', '" . "<" . "em>Fees:" . "<" . "/em> Cross-references the Domain, SSL Certificate, and fee tables, making sure that everything is accurate. It also deletes all unused fees." . "<" . "BR>" . "<" . "BR> " . "<" . "em>Segments:" . "<" . "/em> Compares the Segment data to the domain database and records the status of each domain. This keeps the Segment filtering data up-to-date and running quickly." . "<" . "BR>" . "<" . "BR>" . "<" . "em>TLDs:" . "<" . "/em> Makes sure that the TLD entries recorded in the database are accurate.', 'cleanup', '60', '0', '1', '" . $time->time() . "'),
            ('Check For New Version', 'Checks to see if there is a newer version of DomainMOD available to download." . "<" . "BR>" . "<" . "BR>You can view your current version on the " . "<" . "a href=\'../system-info.php\'>System Information" . "<" . "/a> page.', 'check-new-version', '80', '0', '1', '" . $time->time() . "')";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $cron = \Cron\CronExpression::factory('0 7 * * * *');
    $next_run = $cron->getNextRunDate()->format('Y-m-d H:i:s');

    $sql = "UPDATE scheduler
            SET next_run = '" . $next_run . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $sql_settings = "SELECT *
                     FROM settings";
    $result_settings = mysqli_query($connection, $sql_settings);

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
        $_SESSION['s_system_expiration_email_days'] = $row_settings->expiration_email_days;

    }

    $sql_user_settings = "SELECT *
                          FROM user_settings
                          ORDER BY id DESC
                          LIMIT 1";
    $result_user_settings = mysqli_query($connection, $sql_user_settings);

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

    $sql_currencies = "SELECT name, symbol, symbol_order, symbol_space
                       FROM currencies
                       WHERE currency = '" . $_SESSION['s_default_currency'] . "'";
    $result_currencies = mysqli_query($connection, $sql_currencies);

    while ($row_currencies = mysqli_fetch_object($result_currencies)) {
        $_SESSION['s_default_currency_name'] = $row_currencies->name;
        $_SESSION['s_default_currency_symbol'] = $row_currencies->symbol;
        $_SESSION['s_default_currency_symbol_order'] = $row_currencies->symbol_order;
        $_SESSION['s_default_currency_symbol_space'] = $row_currencies->symbol_space;
    }

    $_SESSION['s_installation_mode'] = '0';
    $_SESSION['s_result_message'] = "$software_title has been installed<BR><BR>The default username and password are both set to \"admin\"<BR>";

    header("Location: ../");
    exit;

}
