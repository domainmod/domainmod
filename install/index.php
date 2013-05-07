<?php
// /install/index.php
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
include("../_includes/start-session.inc.php");
include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/system/installation-check.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");

if (mysql_num_rows( mysql_query("SHOW TABLES LIKE '".settings."'"))) {
	
	$_SESSION['result_message'] = "$software_title is already installed<BR>";

	header("Location: ../");
	exit;

} else {

	$_SESSION['installation_mode'] = 1;

	$sql = "ALTER DATABASE " . $dbname . " 
			CHARACTER SET utf8 
			DEFAULT CHARACTER SET utf8 
			COLLATE utf8_unicode_ci
			DEFAULT COLLATE utf8_unicode_ci;";
	$result = mysql_query($sql,$connection);

	$sql = "CREATE TABLE IF NOT EXISTS `users` (
				`id` int(10) NOT NULL auto_increment,
				`first_name` varchar(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`last_name` varchar(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`username` varchar(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`email_address` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`password` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`new_password` int(1) NOT NULL default '1',
				`admin` int(1) NOT NULL default '0',
				`active` int(1) NOT NULL default '1',
				`number_of_logins` int(10) NOT NULL default '0',
				`last_login` datetime NOT NULL,
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$sql = "INSERT INTO `users` 
			(`first_name`, `last_name`, `username`, `email_address`, `password`, `admin`, `insert_time`) VALUES 
			('Domain', 'Administrator', 'admin', 'admin@aysmedia.com', '*4ACFE3202A5FF5CF467898FC58AAB1D615029441', '1', '$current_timestamp');";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "CREATE TABLE IF NOT EXISTS `user_settings` (
				`id` int(10) NOT NULL auto_increment,
				`user_id` int(10) NOT NULL,
				`default_currency` varchar(3) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`default_timezone` varchar(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL default 'Canada/Pacific',
				`default_category_domains` int(10) NOT NULL default '0',
				`default_category_ssl` int(10) NOT NULL default '0',
				`default_dns` int(10) NOT NULL default '0',
				`default_host` int(10) NOT NULL default '0',
				`default_ip_address_domains` int(10) NOT NULL default '0',
				`default_ip_address_ssl` int(10) NOT NULL default '0',
				`default_owner_domains` int(10) NOT NULL default '0',
				`default_owner_ssl` int(10) NOT NULL default '0',
				`default_registrar` int(10) NOT NULL default '0',
				`default_registrar_account` int(10) NOT NULL default '0',
				`default_ssl_provider_account` int(10) NOT NULL default '0',
				`default_ssl_type` int(10) NOT NULL default '0',
				`default_ssl_provider` int(10) NOT NULL default '0',
				`expiration_emails` int(1) NOT NULL default '1',
				`number_of_domains` int(5) NOT NULL default '50',
				`number_of_ssl_certs` int(5) NOT NULL default '50',
				`display_domain_owner` int(1) NOT NULL default '0',
				`display_domain_registrar` int(1) NOT NULL default '0',
				`display_domain_account` int(1) NOT NULL default '1',
				`display_domain_expiry_date` int(1) NOT NULL default '1',
				`display_domain_category` int(1) NOT NULL default '1',
				`display_domain_dns` int(1) NOT NULL default '1',
				`display_domain_host` int(1) NOT NULL default '0',
				`display_domain_ip` int(1) NOT NULL default '0',
				`display_domain_tld` int(1) NOT NULL default '0',
				`display_domain_fee` int(1) NOT NULL default '0',
				`display_ssl_owner` int(1) NOT NULL default '1',
				`display_ssl_provider` int(1) NOT NULL default '0',
				`display_ssl_account` int(1) NOT NULL default '1',
				`display_ssl_domain` int(1) NOT NULL default '1',
				`display_ssl_type` int(1) NOT NULL default '1',
				`display_ssl_expiry_date` int(1) NOT NULL default '1',
				`display_ssl_ip` int(1) NOT NULL default '0',
				`display_ssl_category` int(1) NOT NULL default '0',
				`display_ssl_fee` int(1) NOT NULL default '0',
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "SELECT id
			FROM users";
	$result = mysql_query($sql,$connection);
	
	while ($row = mysql_fetch_object($result)) {
		$sql_temp = "INSERT INTO user_settings
					 (user_id, default_currency, insert_time) VALUES 
					 ('$row->id', 'CAD', '$current_timestamp');";
		$result_temp = mysql_query($sql_temp,$connection);
	}

	$sql = "CREATE TABLE IF NOT EXISTS `categories` ( 
				`id` int(10) NOT NULL auto_increment,
				`name` varchar(150) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`stakeholder` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`notes` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "INSERT INTO `categories` 
			(`name`, `stakeholder`, `insert_time`) VALUES 
			('[no category]', '[no stakeholder]', '$current_timestamp');";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "CREATE TABLE IF NOT EXISTS `hosting` ( 
				`id` int(10) NOT NULL auto_increment,
				`name` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`notes` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "INSERT INTO `hosting` 
			(`name`, `insert_time`) VALUES 
			('[no hosting]', '$current_timestamp');";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "CREATE TABLE IF NOT EXISTS `owners` ( 
				`id` int(10) NOT NULL auto_increment,
				`name` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`notes` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`),
				KEY `name` (`name`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "INSERT INTO `owners` 
			(`name`, `insert_time`) VALUES 
			('[no owner]', '$current_timestamp');";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "CREATE TABLE IF NOT EXISTS `currencies` ( 
				`id` int(10) NOT NULL auto_increment,
				`currency` varchar(4) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`name` varchar(75) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`symbol` varchar(4) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`symbol_order` int(1) NOT NULL default '0',
				`symbol_space` int(1) NOT NULL default '0',
				`notes` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "INSERT INTO currencies
			(name, currency, symbol, insert_time) VALUES 
			('Albania Lek', 'ALL', 'Lek', '" . $current_timestamp . "'),
			('Afghanistan Afghani', 'AFN', '؋', '" . $current_timestamp . "'),
			('Argentina Peso', 'ARS', '$', '" . $current_timestamp . "'),
			('Aruba Guilder', 'AWG', 'ƒ', '" . $current_timestamp . "'),
			('Australia Dollar', 'AUD', '$', '" . $current_timestamp . "'),
			('Azerbaijan New Manat', 'AZN', '" . 'ман' . "', '" . $current_timestamp . "'),
			('Bahamas Dollar', 'BSD', '$', '" . $current_timestamp . "'),
			('Barbados Dollar', 'BBD', '$', '" . $current_timestamp . "'),
			('Belarus Ruble', 'BYR', 'p.', '" . $current_timestamp . "'),
			('Belize Dollar', 'BZD', 'BZ$', '" . $current_timestamp . "'),
			('Bermuda Dollar', 'BMD', '$', '" . $current_timestamp . "'),
			('Bolivia Boliviano', 'BOB', '\$b', '" . $current_timestamp . "'),
			('Bosnia and Herzegovina Convertible Marka', 'BAM', 'KM', '" . $current_timestamp . "'),
			('Botswana Pula', 'BWP', 'P', '" . $current_timestamp . "'),
			('Bulgaria Lev', 'BGN', 'лв', '" . $current_timestamp . "'),
			('Brazil Real', 'BRL', 'R$', '" . $current_timestamp . "'),
			('Brunei Darussalam Dollar', 'BND', '$', '" . $current_timestamp . "'),
			('Cambodia Riel', 'KHR', '៛', '" . $current_timestamp . "'),
			('Canada Dollar', 'CAD', '$', '" . $current_timestamp . "'),
			('Cayman Islands Dollar', 'KYD', '$', '" . $current_timestamp . "'),
			('Chile Peso', 'CLP', '$', '" . $current_timestamp . "'),
			('China Yuan Renminbi', 'CNY', '¥', '" . $current_timestamp . "'),
			('Colombia Peso', 'COP', '$', '" . $current_timestamp . "'),
			('Costa Rica Colon', 'CRC', '₡', '" . $current_timestamp . "'),
			('Croatia Kuna', 'HRK', 'kn', '" . $current_timestamp . "'),
			('Cuba Peso', 'CUP', '₱', '" . $current_timestamp . "'),
			('Czech Republic Koruna', 'CZK', 'Kč', '" . $current_timestamp . "'),
			('Denmark Krone', 'DKK', 'kr', '" . $current_timestamp . "'),
			('Dominican Republic Peso', 'DOP', 'RD$', '" . $current_timestamp . "'),
			('East Caribbean Dollar', 'XCD', '$', '" . $current_timestamp . "'),
			('Egypt Pound', 'EGP', '£', '" . $current_timestamp . "'),
			('El Salvador Colon', 'SVC', '$', '" . $current_timestamp . "'),
			('Estonia Kroon', 'EEK', 'kr', '" . $current_timestamp . "'),
			('Euro Member Countries', 'EUR', '€', '" . $current_timestamp . "'),
			('Falkland Islands (Malvinas) Pound', 'FKP', '£', '" . $current_timestamp . "'),
			('Fiji Dollar', 'FJD', '$', '" . $current_timestamp . "'),
			('Ghana Cedis', 'GHC', '¢', '" . $current_timestamp . "'),
			('Gibraltar Pound', 'GIP', '£', '" . $current_timestamp . "'),
			('Guatemala Quetzal', 'GTQ', 'Q', '" . $current_timestamp . "'),
			('Guernsey Pound', 'GGP', '£', '" . $current_timestamp . "'),
			('Guyana Dollar', 'GYD', '$', '" . $current_timestamp . "'),
			('Honduras Lempira', 'HNL', 'L', '" . $current_timestamp . "'),
			('Hong Kong Dollar', 'HKD', '$', '" . $current_timestamp . "'),
			('Hungary Forint', 'HUF', 'Ft', '" . $current_timestamp . "'),
			('Iceland Krona', 'ISK', 'kr', '" . $current_timestamp . "'),
			('India Rupee', 'INR', 'Rs', '" . $current_timestamp . "'),
			('Indonesia Rupiah', 'IDR', 'Rp', '" . $current_timestamp . "'),
			('Iran Rial', 'IRR', '﷼', '" . $current_timestamp . "'),
			('Isle of Man Pound', 'IMP', '£', '" . $current_timestamp . "'),
			('Israel Shekel', 'ILS', '₪', '" . $current_timestamp . "'),
			('Jamaica Dollar', 'JMD', 'J$', '" . $current_timestamp . "'),
			('Japan Yen', 'JPY', '¥', '" . $current_timestamp . "'),
			('Jersey Pound', 'JEP', '£', '" . $current_timestamp . "'),
			('Kazakhstan Tenge', 'KZT', 'лв', '" . $current_timestamp . "'),
			('Korea (North) Won', 'KPW', '₩', '" . $current_timestamp . "'),
			('Korea (South) Won', 'KRW', '₩', '" . $current_timestamp . "'),
			('Kyrgyzstan Som', 'KGS', 'лв', '" . $current_timestamp . "'),
			('Laos Kip', 'LAK', '₭', '" . $current_timestamp . "'),
			('Latvia Lat', 'LVL', 'Ls', '" . $current_timestamp . "'),
			('Lebanon Pound', 'LBP', '£', '" . $current_timestamp . "'),
			('Liberia Dollar', 'LRD', '$', '" . $current_timestamp . "'),
			('Lithuania Litas', 'LTL', 'Lt', '" . $current_timestamp . "'),
			('Macedonia Denar', 'MKD', 'ден', '" . $current_timestamp . "'),
			('Malaysia Ringgit', 'RM', 'RM', '" . $current_timestamp . "'),
			('Mauritius Rupee', 'MUR', '₨', '" . $current_timestamp . "'),
			('Mexico Peso', 'MXN', '$', '" . $current_timestamp . "'),
			('Mongolia Tughrik', 'MNT', '₮', '" . $current_timestamp . "'),
			('Mozambique Metical', 'MZN', 'MT', '" . $current_timestamp . "'),
			('Namibia Dollar', 'NAD', '$', '" . $current_timestamp . "'),
			('Nepal Rupee', 'NPR', '₨', '" . $current_timestamp . "'),
			('Netherlands Antilles Guilder', 'ANG', 'ƒ', '" . $current_timestamp . "'),
			('New Zealand Dollar', 'NZD', '$', '" . $current_timestamp . "'),
			('Nicaragua Cordoba', 'NIO', 'C$', '" . $current_timestamp . "'),
			('Nigeria Naira', 'NGN', '₦', '" . $current_timestamp . "'),
			('Norway Krone', 'NOK', 'kr', '" . $current_timestamp . "'),
			('Oman Rial', 'OMR', '﷼', '" . $current_timestamp . "'),
			('Pakistan Rupee', 'PKR', '₨', '" . $current_timestamp . "'),
			('Panama Balboa', 'PAB', 'B/.', '" . $current_timestamp . "'),
			('Paraguay Guarani', 'PYG', 'Gs', '" . $current_timestamp . "'),
			('Peru Nuevo Sol', 'PEN', 'S/.', '" . $current_timestamp . "'),
			('Philippines Peso', 'PHP', '₱', '" . $current_timestamp . "'),
			('Poland Zloty', 'PLN', 'zł', '" . $current_timestamp . "'),
			('Qatar Riyal', 'QAR', '﷼', '" . $current_timestamp . "'),
			('Romania New Leu', 'RON', 'lei', '" . $current_timestamp . "'),
			('Russia Ruble', 'RUB', 'руб', '" . $current_timestamp . "'),
			('Saint Helena Pound', 'SHP', '£', '" . $current_timestamp . "'),
			('Saudi Arabia Riyal', 'SAR', '﷼', '" . $current_timestamp . "'),
			('Serbia Dinar', 'RSD', 'Дин.', '" . $current_timestamp . "'),
			('Seychelles Rupee', 'SCR', '₨', '" . $current_timestamp . "'),
			('Singapore Dollar', 'SGD', '$', '" . $current_timestamp . "'),
			('Solomon Islands Dollar', 'SBD', '$', '" . $current_timestamp . "'),
			('Somalia Shilling', 'SOS', 'S', '" . $current_timestamp . "'),
			('South Africa Rand', 'ZAR', 'R', '" . $current_timestamp . "'),
			('Sri Lanka Rupee', 'LKR', '₨', '" . $current_timestamp . "'),
			('Sweden Krona', 'SEK', 'kr', '" . $current_timestamp . "'),
			('Switzerland Franc', 'CHF', 'CHF', '" . $current_timestamp . "'),
			('Suriname Dollar', 'SRD', '$', '" . $current_timestamp . "'),
			('Syria Pound', 'SYP', '£', '" . $current_timestamp . "'),
			('Taiwan New Dollar', 'TWD', 'NT$', '" . $current_timestamp . "'),
			('Thailand Baht', 'THB', '฿', '" . $current_timestamp . "'),
			('Trinidad and Tobago Dollar', 'TTD', 'TT$', '" . $current_timestamp . "'),
			('Turkey Lira', 'TRY', '₤', '" . $current_timestamp . "'),
			('Tuvalu Dollar', 'TVD', '$', '" . $current_timestamp . "'),
			('Ukraine Hryvna', 'UAH', '₴', '" . $current_timestamp . "'),
			('United Kingdom Pound', 'GBP', '£', '" . $current_timestamp . "'),
			('United States Dollar', 'USD', '$', '" . $current_timestamp . "'),
			('Uruguay Peso', 'UYU', '\$U', '" . $current_timestamp . "'),
			('Uzbekistan Som', 'UZS', 'лв', '" . $current_timestamp . "'),
			('Venezuela Bolivar', 'VEF', 'Bs', '" . $current_timestamp . "'),
			('Viet Nam Dong', 'VND', '₫', '" . $current_timestamp . "'),
			('Yemen Rial', 'YER', '﷼', '" . $current_timestamp . "'),
			('Zimbabwe Dollar', 'ZWD', 'Z$', '" . $current_timestamp . "'),
			('Emirati Dirham', 'AED', 'د.إ', '" . $current_timestamp . "'),
			('Malaysian Ringgit', 'MYR', 'RM', '" . $current_timestamp . "'),
			('Kuwaiti Dinar', 'KWD', 'ك', '" . $current_timestamp . "'),
			('Moroccan Dirham', 'MAD', 'م.', '" . $current_timestamp . "'),
			('Iraqi Dinar', 'IQD', 'د.ع', '" . $current_timestamp . "'),
			('Bangladeshi Taka', 'BDT', 'Tk', '" . $current_timestamp . "'),
			('Bahraini Dinar', 'BHD', 'BD', '" . $current_timestamp . "'),
			('Kenyan Shilling', 'KES', 'KSh', '" . $current_timestamp . "'),
			('CFA Franc', 'XOF', 'CFA', '" . $current_timestamp . "'),
			('Jordanian Dinar', 'JOD', 'JD', '" . $current_timestamp . "'),
			('Tunisian Dinar', 'TND', 'د.ت', '" . $current_timestamp . "'),
			('Ghanaian Cedi', 'GHS', 'GH¢', '" . $current_timestamp . "'),
			('Central African CFA Franc BEAC', 'XAF', 'FCFA', '" . $current_timestamp . "'),
			('Algerian Dinar', 'DZD', 'دج', '" . $current_timestamp . "'),
			('CFP Franc', 'XPF', 'F', '" . $current_timestamp . "'),
			('Ugandan Shilling', 'UGX', 'USh', '" . $current_timestamp . "'),
			('Tanzanian Shilling', 'TZS', 'TZS', '" . $current_timestamp . "'),
			('Ethiopian Birr', 'ETB', 'Br', '" . $current_timestamp . "'),
			('Georgian Lari', 'GEL', 'GEL', '" . $current_timestamp . "'),
			('Cuban Convertible Peso', 'CUC', 'CUC$', '" . $current_timestamp . "'),
			('Burmese Kyat', 'MMK', 'K', '" . $current_timestamp . "'),
			('Libyan Dinar', 'LYD', 'LD', '" . $current_timestamp . "'),
			('Zambian Kwacha', 'ZMK', 'ZK', '" . $current_timestamp . "'),
			('Zambian Kwacha', 'ZMW', 'ZK', '" . $current_timestamp . "'),
			('Macau Pataca', 'MOP', 'MOP$', '" . $current_timestamp . "'),
			('Armenian Dram', 'AMD', 'AMD', '" . $current_timestamp . "'),
			('Angolan Kwanza', 'AOA', 'Kz', '" . $current_timestamp . "'),
			('Papua New Guinean Kina', 'PGK', 'K', '" . $current_timestamp . "'),
			('Malagasy Ariary', 'MGA', 'Ar', '" . $current_timestamp . "'),
			('Ni-Vanuatu Vatu', 'VUV', 'VT', '" . $current_timestamp . "'),
			('Sudanese Pound', 'SDG', 'SDG', '" . $current_timestamp . "'),
			('Malawian Kwacha', 'MWK', 'MK', '" . $current_timestamp . "'),
			('Rwandan Franc', 'RWF', 'FRw', '" . $current_timestamp . "'),
			('Gambian Dalasi', 'GMD', 'D', '" . $current_timestamp . "'),
			('Maldivian Rufiyaa', 'MVR', 'Rf', '" . $current_timestamp . "'),
			('Congolese Franc', 'CDF', 'FC', '" . $current_timestamp . "'),
			('Djiboutian Franc', 'DJF', 'Fdj', '" . $current_timestamp . "'),
			('Haitian Gourde', 'HTG', 'G', '" . $current_timestamp . "'),
			('Samoan Tala', 'WST', '$', '" . $current_timestamp . "'),
			('Guinean Franc', 'GNF', 'FG', '" . $current_timestamp . "'),
			('Cape Verdean Escudo', 'CVE', '$', '" . $current_timestamp . "'),
			('Tongan Pa\'anga', 'TOP', 'T$', '" . $current_timestamp . "'),
			('Moldovan Leu', 'MDL', 'MDL', '" . $current_timestamp . "'),
			('Sierra Leonean Leone', 'SLL', 'Le', '" . $current_timestamp . "'),
			('Burundian Franc', 'BIF', 'FBu', '" . $current_timestamp . "'),
			('Mauritanian Ouguiya', 'MRO', 'UM', '" . $current_timestamp . "'),
			('Bhutanese Ngultrum', 'BTN', 'Nu.', '" . $current_timestamp . "'),
			('Swazi Lilangeni', 'SZL', 'SZL', '" . $current_timestamp . "'),
			('Tajikistani Somoni', 'TJS', 'TJS', '" . $current_timestamp . "'),
			('Turkmenistani Manat', 'TMT', 'm', '" . $current_timestamp . "'),
			('Basotho Loti', 'LSL', 'LSL', '" . $current_timestamp . "'),
			('Comoran Franc', 'KMF', 'CF', '" . $current_timestamp . "'),
			('Sao Tomean Dobra', 'STD', 'STD', '" . $current_timestamp . "'),
			('Seborgan Luigino', 'SPL', 'SPL', '" . $current_timestamp . "')";
	$result = mysql_query($sql,$connection);

	$sql = "CREATE TABLE IF NOT EXISTS `currency_conversions` (
			`id` int(10) NOT NULL auto_increment,
			`currency_id` int(10) NOT NULL,
			`user_id` int(10) NOT NULL,
			`conversion` float NOT NULL,
			`insert_time` datetime NOT NULL,
			`update_time` datetime NOT NULL,
			PRIMARY KEY  (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection);

	$sql = "CREATE TABLE IF NOT EXISTS `fees` ( 
				`id` int(10) NOT NULL auto_increment,
				`registrar_id` int(10) NOT NULL,
				`tld` varchar(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`initial_fee` float NOT NULL,
				`renewal_fee` float NOT NULL,
				`transfer_fee` float NOT NULL,
				`currency_id` int(10) NOT NULL,
				`fee_fixed` int(1) NOT NULL,
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$sql = "CREATE TABLE IF NOT EXISTS `ssl_fees` ( 
				`id` int(10) NOT NULL auto_increment,
				`ssl_provider_id` int(10) NOT NULL,
				`type_id` int(10) NOT NULL,
				`initial_fee` float NOT NULL,
				`renewal_fee` float NOT NULL,
				`currency_id` int(10) NOT NULL,
				`fee_fixed` int(1) NOT NULL,
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$sql = "CREATE TABLE IF NOT EXISTS `domains` ( 
				`id` int(10) NOT NULL auto_increment,
				`owner_id` int(10) NOT NULL default '1',
				`registrar_id` int(10) NOT NULL default '1',
				`account_id` int(10) NOT NULL default '1',
				`domain` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`tld` varchar(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`expiry_date` date NOT NULL,
				`cat_id` int(10) NOT NULL default '1',
				`fee_id` int(10) NOT NULL default '0',
				`dns_id` int(10) NOT NULL default '1',
				`ip_id` int(10) NOT NULL default '1',
				`hosting_id` int(10) NOT NULL default '1',
				`function` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`notes` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`privacy` int(1) NOT NULL default '0',
				`active` int(2) NOT NULL default '1',
				`fee_fixed` int(1) NOT NULL,
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`),
				KEY `domain` (`domain`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "CREATE TABLE IF NOT EXISTS `ssl_certs` ( 
				`id` int(10) NOT NULL auto_increment,
				`owner_id` int(10) NOT NULL,
				`ssl_provider_id` int(10) NOT NULL,
				`account_id` int(10) NOT NULL,
				`domain_id` int(10) NOT NULL,
				`type_id` int(10) NOT NULL,
				`ip_id` int(10) NOT NULL,
				`cat_id` int(10) NOT NULL,
				`name` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`expiry_date` date NOT NULL,
				`fee_id` int(10) NOT NULL,
				`notes` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`active` int(1) NOT NULL default '1',
				`fee_fixed` int(1) NOT NULL,
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$sql = "CREATE TABLE IF NOT EXISTS `ssl_cert_types` ( 
				`id` int(10) NOT NULL auto_increment,
				`type` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`notes` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$sql = "INSERT INTO `ssl_cert_types` 
			(`id`, `type`, `insert_time`) VALUES 
			(1, 'Web Server SSL/TLS Certificate', '$current_timestamp'),
			(2, 'S/MIME and Authentication Certificate', '$current_timestamp'),
			(3, 'Object Code Signing Certificate', '$current_timestamp'),
			(4, 'Digital ID', '$current_timestamp');";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$sql = "CREATE TABLE IF NOT EXISTS `dns` ( 
				`id` int(10) NOT NULL auto_increment,
				`name` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`dns1` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`dns2` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`dns3` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`dns4` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`dns5` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`dns6` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`dns7` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`dns8` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`dns9` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`dns10` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`ip1` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`ip2` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`ip3` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`ip4` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`ip5` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`ip6` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`ip7` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`ip8` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`ip9` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`ip10` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`notes` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`number_of_servers` int(2) NOT NULL default '0',
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "INSERT INTO `dns` 
			(`name`, `dns1`, `dns2`, `number_of_servers`, `insert_time`) VALUES 
			('[no dns]', 'ns1.no-dns.com', 'ns2.no-dns.com', '2', '$current_timestamp');";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$sql = "CREATE TABLE IF NOT EXISTS `registrars` ( 
				`id` int(10) NOT NULL auto_increment,
				`name` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`url` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`notes` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`),
				KEY `name` (`name`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$sql = "CREATE TABLE IF NOT EXISTS `ssl_providers` ( 
				`id` int(10) NOT NULL auto_increment,
				`name` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`url` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`notes` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$sql = "CREATE TABLE IF NOT EXISTS `registrar_accounts` ( 
				`id` int(10) NOT NULL auto_increment,
				`owner_id` int(10) NOT NULL,
				`registrar_id` int(10) NOT NULL,
				`username` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`password` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`notes` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`reseller` int(1) NOT NULL default '0',
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`),
				KEY `registrar_id` (`registrar_id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$sql = "CREATE TABLE IF NOT EXISTS `ssl_accounts` ( 
				`id` int(10) NOT NULL auto_increment,
				`owner_id` int(10) NOT NULL,
				`ssl_provider_id` int(10) NOT NULL,
				`username` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`password` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`notes` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`reseller` int(1) NOT NULL default '0',
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`),
				KEY `ssl_provider_id` (`ssl_provider_id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$sql = "CREATE TABLE IF NOT EXISTS `segments` ( 
				`id` int(10) NOT NULL auto_increment,
				`name` varchar(35) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`description` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`segment` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`number_of_domains` int(6) NOT NULL,
				`notes` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "CREATE TABLE IF NOT EXISTS `segment_data` (
			`id` int(10) NOT NULL auto_increment,
			`segment_id` int(10) NOT NULL,
			`domain` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
			`active` int(1) NOT NULL default '0',
			`inactive` int(1) NOT NULL default '0',
			`missing` int(1) NOT NULL default '0',
			`filtered` int(1) NOT NULL default '0',
			`insert_time` datetime NOT NULL,
			`update_time` datetime NOT NULL,
			PRIMARY KEY  (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$sql = "CREATE TABLE IF NOT EXISTS `ip_addresses` ( 
				`id` int(10) NOT NULL auto_increment,
				`name` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`ip` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`rdns` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL default '-',
				`notes` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$sql = "INSERT INTO `ip_addresses` 
			(`id`, `name`, `ip`, `rdns`, `insert_time`) VALUES 
			('1', '[no ip address]', '-', '-', '$current_timestamp');";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$sql = "CREATE TABLE IF NOT EXISTS `settings` ( 
				`id` int(10) NOT NULL auto_increment,
				`full_url` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL default 'http://',
				`db_version` float NOT NULL,
				`email_address` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`default_category_domains` int(10) NOT NULL default '0',
				`default_category_ssl` int(10) NOT NULL default '0',
				`default_dns` int(10) NOT NULL default '0',
				`default_host` int(10) NOT NULL default '0',
				`default_ip_address_domains` int(10) NOT NULL default '0',
				`default_ip_address_ssl` int(10) NOT NULL default '0',
				`default_owner_domains` int(10) NOT NULL default '0',
				`default_owner_ssl` int(10) NOT NULL default '0',
				`default_registrar` int(10) NOT NULL default '0',
				`default_registrar_account` int(10) NOT NULL default '0',
				`default_ssl_provider_account` int(10) NOT NULL default '0',
				`default_ssl_type` int(10) NOT NULL default '0',
				`default_ssl_provider` int(10) NOT NULL default '0',
				`expiration_email_days` int(3) NOT NULL default '60',
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$full_url = substr($_SERVER["HTTP_REFERER"], 0, -1);

	$sql = "INSERT INTO `settings` 
			(`full_url`, `db_version`, `email_address`, `insert_time`) VALUES 
			('$full_url', '$most_recent_db_version', 'dm@aysmedia.com', '$current_timestamp');";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "CREATE TABLE IF NOT EXISTS `timezones` (
				`id` int(5) NOT NULL auto_increment,
				`timezone` varchar(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`insert_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "INSERT INTO `timezones` 
			(`timezone`, `insert_time`) VALUES 
			('Africa/Abidjan', '$current_timestamp'), ('Africa/Accra', '$current_timestamp'), ('Africa/Addis_Ababa', '$current_timestamp'), ('Africa/Algiers', '$current_timestamp'), ('Africa/Asmara', '$current_timestamp'), ('Africa/Asmera', '$current_timestamp'), ('Africa/Bamako', '$current_timestamp'), ('Africa/Bangui', '$current_timestamp'), ('Africa/Banjul', '$current_timestamp'), ('Africa/Bissau', '$current_timestamp'), ('Africa/Blantyre', '$current_timestamp'), ('Africa/Brazzaville', '$current_timestamp'), ('Africa/Bujumbura', '$current_timestamp'), ('Africa/Cairo', '$current_timestamp'), ('Africa/Casablanca', '$current_timestamp'), ('Africa/Ceuta', '$current_timestamp'), ('Africa/Conakry', '$current_timestamp'), ('Africa/Dakar', '$current_timestamp'), ('Africa/Dar_es_Salaam', '$current_timestamp'), ('Africa/Djibouti', '$current_timestamp'), ('Africa/Douala', '$current_timestamp'), ('Africa/El_Aaiun', '$current_timestamp'), ('Africa/Freetown', '$current_timestamp'), ('Africa/Gaborone', '$current_timestamp'), ('Africa/Harare', '$current_timestamp'), ('Africa/Johannesburg', '$current_timestamp'), ('Africa/Juba', '$current_timestamp'), ('Africa/Kampala', '$current_timestamp'), ('Africa/Khartoum', '$current_timestamp'), ('Africa/Kigali', '$current_timestamp'), ('Africa/Kinshasa', '$current_timestamp'), ('Africa/Lagos', '$current_timestamp'), ('Africa/Libreville', '$current_timestamp'), ('Africa/Lome', '$current_timestamp'), ('Africa/Luanda', '$current_timestamp'), ('Africa/Lubumbashi', '$current_timestamp'), ('Africa/Lusaka', '$current_timestamp'), ('Africa/Malabo', '$current_timestamp'), ('Africa/Maputo', '$current_timestamp'), ('Africa/Maseru', '$current_timestamp'), ('Africa/Mbabane', '$current_timestamp'), ('Africa/Mogadishu', '$current_timestamp'), ('Africa/Monrovia', '$current_timestamp'), ('Africa/Nairobi', '$current_timestamp'), ('Africa/Ndjamena', '$current_timestamp'), ('Africa/Niamey', '$current_timestamp'), ('Africa/Nouakchott', '$current_timestamp'), ('Africa/Ouagadougou', '$current_timestamp'), ('Africa/Porto-Novo', '$current_timestamp'), ('Africa/Sao_Tome', '$current_timestamp'), ('Africa/Timbuktu', '$current_timestamp'), ('Africa/Tripoli', '$current_timestamp'), ('Africa/Tunis', '$current_timestamp'), ('Africa/Windhoek', '$current_timestamp'), ('America/Adak', '$current_timestamp'), ('America/Anchorage', '$current_timestamp'), ('America/Anguilla', '$current_timestamp'), ('America/Antigua', '$current_timestamp'), ('America/Araguaina', '$current_timestamp'), ('America/Argentina/Buenos_Aires', '$current_timestamp'), ('America/Argentina/Catamarca', '$current_timestamp'), ('America/Argentina/ComodRivadavia', '$current_timestamp'), ('America/Argentina/Cordoba', '$current_timestamp'), ('America/Argentina/Jujuy', '$current_timestamp'), ('America/Argentina/La_Rioja', '$current_timestamp'), ('America/Argentina/Mendoza', '$current_timestamp'), ('America/Argentina/Rio_Gallegos', '$current_timestamp'), ('America/Argentina/Salta', '$current_timestamp'), ('America/Argentina/San_Juan', '$current_timestamp'), ('America/Argentina/San_Luis', '$current_timestamp'), ('America/Argentina/Tucuman', '$current_timestamp'), ('America/Argentina/Ushuaia', '$current_timestamp'), ('America/Aruba', '$current_timestamp'), ('America/Asuncion', '$current_timestamp'), ('America/Atikokan', '$current_timestamp'), ('America/Atka', '$current_timestamp'), ('America/Bahia', '$current_timestamp'), ('America/Bahia_Banderas', '$current_timestamp'), ('America/Barbados', '$current_timestamp'), ('America/Belem', '$current_timestamp'), ('America/Belize', '$current_timestamp'), ('America/Blanc-Sablon', '$current_timestamp'), ('America/Boa_Vista', '$current_timestamp'), ('America/Bogota', '$current_timestamp'), ('America/Boise', '$current_timestamp'), ('America/Buenos_Aires', '$current_timestamp'), ('America/Cambridge_Bay', '$current_timestamp'), ('America/Campo_Grande', '$current_timestamp'), ('America/Cancun', '$current_timestamp'), ('America/Caracas', '$current_timestamp'), ('America/Catamarca', '$current_timestamp'), ('America/Cayenne', '$current_timestamp'), ('America/Cayman', '$current_timestamp'), ('America/Chicago', '$current_timestamp'), ('America/Chihuahua', '$current_timestamp'), ('America/Coral_Harbour', '$current_timestamp'), ('America/Cordoba', '$current_timestamp'), ('America/Costa_Rica', '$current_timestamp'), ('America/Creston', '$current_timestamp'), ('America/Cuiaba', '$current_timestamp'), ('America/Curacao', '$current_timestamp'), ('America/Danmarkshavn', '$current_timestamp'), ('America/Dawson', '$current_timestamp'), ('America/Dawson_Creek', '$current_timestamp'), ('America/Denver', '$current_timestamp'), ('America/Detroit', '$current_timestamp'), ('America/Dominica', '$current_timestamp'), ('America/Edmonton', '$current_timestamp'), ('America/Eirunepe', '$current_timestamp'), ('America/El_Salvador', '$current_timestamp'), ('America/Ensenada', '$current_timestamp'), ('America/Fort_Wayne', '$current_timestamp'), ('America/Fortaleza', '$current_timestamp'), ('America/Glace_Bay', '$current_timestamp'), ('America/Godthab', '$current_timestamp'), ('America/Goose_Bay', '$current_timestamp'), ('America/Grand_Turk', '$current_timestamp'), ('America/Grenada', '$current_timestamp'), ('America/Guadeloupe', '$current_timestamp'), ('America/Guatemala', '$current_timestamp'), ('America/Guayaquil', '$current_timestamp'), ('America/Guyana', '$current_timestamp'), ('America/Halifax', '$current_timestamp'), ('America/Havana', '$current_timestamp'), ('America/Hermosillo', '$current_timestamp'), ('America/Indiana/Indianapolis', '$current_timestamp'), ('America/Indiana/Knox', '$current_timestamp'), ('America/Indiana/Marengo', '$current_timestamp'), ('America/Indiana/Petersburg', '$current_timestamp'), ('America/Indiana/Tell_City', '$current_timestamp'), ('America/Indiana/Vevay', '$current_timestamp'), ('America/Indiana/Vincennes', '$current_timestamp'), ('America/Indiana/Winamac', '$current_timestamp'), ('America/Indianapolis', '$current_timestamp'), ('America/Inuvik', '$current_timestamp'), ('America/Iqaluit', '$current_timestamp'), ('America/Jamaica', '$current_timestamp'), ('America/Jujuy', '$current_timestamp'), ('America/Juneau', '$current_timestamp'), ('America/Kentucky/Louisville', '$current_timestamp'), ('America/Kentucky/Monticello', '$current_timestamp'), ('America/Knox_IN', '$current_timestamp'), ('America/Kralendijk', '$current_timestamp'), ('America/La_Paz', '$current_timestamp'), ('America/Lima', '$current_timestamp'), ('America/Los_Angeles', '$current_timestamp'), ('America/Louisville', '$current_timestamp'), ('America/Lower_Princes', '$current_timestamp'), ('America/Maceio', '$current_timestamp'), ('America/Managua', '$current_timestamp'), ('America/Manaus', '$current_timestamp'), ('America/Marigot', '$current_timestamp'), ('America/Martinique', '$current_timestamp'), ('America/Matamoros', '$current_timestamp'), ('America/Mazatlan', '$current_timestamp'), ('America/Mendoza', '$current_timestamp'), ('America/Menominee', '$current_timestamp'), ('America/Merida', '$current_timestamp'), ('America/Metlakatla', '$current_timestamp'), ('America/Mexico_City', '$current_timestamp'), ('America/Miquelon', '$current_timestamp'), ('America/Moncton', '$current_timestamp'), ('America/Monterrey', '$current_timestamp'), ('America/Montevideo', '$current_timestamp'), ('America/Montreal', '$current_timestamp'), ('America/Montserrat', '$current_timestamp'), ('America/Nassau', '$current_timestamp'), ('America/New_York', '$current_timestamp'), ('America/Nipigon', '$current_timestamp'), ('America/Nome', '$current_timestamp'), ('America/Noronha', '$current_timestamp'), ('America/North_Dakota/Beulah', '$current_timestamp'), ('America/North_Dakota/Center', '$current_timestamp'), ('America/North_Dakota/New_Salem', '$current_timestamp'), ('America/Ojinaga', '$current_timestamp'), ('America/Panama', '$current_timestamp'), ('America/Pangnirtung', '$current_timestamp'), ('America/Paramaribo', '$current_timestamp'), ('America/Phoenix', '$current_timestamp'), ('America/Port-au-Prince', '$current_timestamp'), ('America/Port_of_Spain', '$current_timestamp'), ('America/Porto_Acre', '$current_timestamp'), ('America/Porto_Velho', '$current_timestamp'), ('America/Puerto_Rico', '$current_timestamp'), ('America/Rainy_River', '$current_timestamp'), ('America/Rankin_Inlet', '$current_timestamp'), ('America/Recife', '$current_timestamp'), ('America/Regina', '$current_timestamp'), ('America/Resolute', '$current_timestamp'), ('America/Rio_Branco', '$current_timestamp'), ('America/Rosario', '$current_timestamp'), ('America/Santa_Isabel', '$current_timestamp'), ('America/Santarem', '$current_timestamp'), ('America/Santiago', '$current_timestamp'), ('America/Santo_Domingo', '$current_timestamp'), ('America/Sao_Paulo', '$current_timestamp'), ('America/Scoresbysund', '$current_timestamp'), ('America/Shiprock', '$current_timestamp'), ('America/Sitka', '$current_timestamp'), ('America/St_Barthelemy', '$current_timestamp'), ('America/St_Johns', '$current_timestamp'), ('America/St_Kitts', '$current_timestamp'), ('America/St_Lucia', '$current_timestamp'), ('America/St_Thomas', '$current_timestamp'), ('America/St_Vincent', '$current_timestamp'), ('America/Swift_Current', '$current_timestamp'), ('America/Tegucigalpa', '$current_timestamp'), ('America/Thule', '$current_timestamp'), ('America/Thunder_Bay', '$current_timestamp'), ('America/Tijuana', '$current_timestamp'), ('America/Toronto', '$current_timestamp'), ('America/Tortola', '$current_timestamp'), ('America/Vancouver', '$current_timestamp'), ('America/Virgin', '$current_timestamp'), ('America/Whitehorse', '$current_timestamp'), ('America/Winnipeg', '$current_timestamp'), ('America/Yakutat', '$current_timestamp'), ('America/Yellowknife', '$current_timestamp'), ('Antarctica/Casey', '$current_timestamp'), ('Antarctica/Davis', '$current_timestamp'), ('Antarctica/DumontDUrville', '$current_timestamp'), ('Antarctica/Macquarie', '$current_timestamp'), ('Antarctica/Mawson', '$current_timestamp'), ('Antarctica/McMurdo', '$current_timestamp'), ('Antarctica/Palmer', '$current_timestamp'), ('Antarctica/Rothera', '$current_timestamp'), ('Antarctica/South_Pole', '$current_timestamp'), ('Antarctica/Syowa', '$current_timestamp'), ('Antarctica/Vostok', '$current_timestamp'), ('Arctic/Longyearbyen', '$current_timestamp'), ('Asia/Aden', '$current_timestamp'), ('Asia/Almaty', '$current_timestamp'), ('Asia/Amman', '$current_timestamp'), ('Asia/Anadyr', '$current_timestamp'), ('Asia/Aqtau', '$current_timestamp'), ('Asia/Aqtobe', '$current_timestamp'), ('Asia/Ashgabat', '$current_timestamp'), ('Asia/Ashkhabad', '$current_timestamp'), ('Asia/Baghdad', '$current_timestamp'), ('Asia/Bahrain', '$current_timestamp'), ('Asia/Baku', '$current_timestamp'), ('Asia/Bangkok', '$current_timestamp'), ('Asia/Beirut', '$current_timestamp'), ('Asia/Bishkek', '$current_timestamp'), ('Asia/Brunei', '$current_timestamp'), ('Asia/Calcutta', '$current_timestamp'), ('Asia/Choibalsan', '$current_timestamp'), ('Asia/Chongqing', '$current_timestamp'), ('Asia/Chungking', '$current_timestamp'), ('Asia/Colombo', '$current_timestamp'), ('Asia/Dacca', '$current_timestamp'), ('Asia/Damascus', '$current_timestamp'), ('Asia/Dhaka', '$current_timestamp'), ('Asia/Dili', '$current_timestamp'), ('Asia/Dubai', '$current_timestamp'), ('Asia/Dushanbe', '$current_timestamp'), ('Asia/Gaza', '$current_timestamp'), ('Asia/Harbin', '$current_timestamp'), ('Asia/Hebron', '$current_timestamp'), ('Asia/Ho_Chi_Minh', '$current_timestamp'), ('Asia/Hong_Kong', '$current_timestamp'), ('Asia/Hovd', '$current_timestamp'), ('Asia/Irkutsk', '$current_timestamp'), ('Asia/Istanbul', '$current_timestamp'), ('Asia/Jakarta', '$current_timestamp'), ('Asia/Jayapura', '$current_timestamp'), ('Asia/Jerusalem', '$current_timestamp'), ('Asia/Kabul', '$current_timestamp'), ('Asia/Kamchatka', '$current_timestamp'), ('Asia/Karachi', '$current_timestamp'), ('Asia/Kashgar', '$current_timestamp'), ('Asia/Kathmandu', '$current_timestamp'), ('Asia/Katmandu', '$current_timestamp'), ('Asia/Khandyga', '$current_timestamp'), ('Asia/Kolkata', '$current_timestamp'), ('Asia/Krasnoyarsk', '$current_timestamp'), ('Asia/Kuala_Lumpur', '$current_timestamp'), ('Asia/Kuching', '$current_timestamp'), ('Asia/Kuwait', '$current_timestamp'), ('Asia/Macao', '$current_timestamp'), ('Asia/Macau', '$current_timestamp'), ('Asia/Magadan', '$current_timestamp'), ('Asia/Makassar', '$current_timestamp'), ('Asia/Manila', '$current_timestamp'), ('Asia/Muscat', '$current_timestamp'), ('Asia/Nicosia', '$current_timestamp'), ('Asia/Novokuznetsk', '$current_timestamp'), ('Asia/Novosibirsk', '$current_timestamp'), ('Asia/Omsk', '$current_timestamp'), ('Asia/Oral', '$current_timestamp'), ('Asia/Phnom_Penh', '$current_timestamp'), ('Asia/Pontianak', '$current_timestamp'), ('Asia/Pyongyang', '$current_timestamp'), ('Asia/Qatar', '$current_timestamp'), ('Asia/Qyzylorda', '$current_timestamp'), ('Asia/Rangoon', '$current_timestamp'), ('Asia/Riyadh', '$current_timestamp'), ('Asia/Saigon', '$current_timestamp'), ('Asia/Sakhalin', '$current_timestamp'), ('Asia/Samarkand', '$current_timestamp'), ('Asia/Seoul', '$current_timestamp'), ('Asia/Shanghai', '$current_timestamp'), ('Asia/Singapore', '$current_timestamp'), ('Asia/Taipei', '$current_timestamp'), ('Asia/Tashkent', '$current_timestamp'), ('Asia/Tbilisi', '$current_timestamp'), ('Asia/Tehran', '$current_timestamp'), ('Asia/Tel_Aviv', '$current_timestamp'), ('Asia/Thimbu', '$current_timestamp'), ('Asia/Thimphu', '$current_timestamp'), ('Asia/Tokyo', '$current_timestamp'), ('Asia/Ujung_Pandang', '$current_timestamp'), ('Asia/Ulaanbaatar', '$current_timestamp'), ('Asia/Ulan_Bator', '$current_timestamp'), ('Asia/Urumqi', '$current_timestamp'), ('Asia/Ust-Nera', '$current_timestamp'), ('Asia/Vientiane', '$current_timestamp'), ('Asia/Vladivostok', '$current_timestamp'), ('Asia/Yakutsk', '$current_timestamp'), ('Asia/Yekaterinburg', '$current_timestamp'), ('Asia/Yerevan', '$current_timestamp'), ('Atlantic/Azores', '$current_timestamp'), ('Atlantic/Bermuda', '$current_timestamp'), ('Atlantic/Canary', '$current_timestamp'), ('Atlantic/Cape_Verde', '$current_timestamp'), ('Atlantic/Faeroe', '$current_timestamp'), ('Atlantic/Faroe', '$current_timestamp'), ('Atlantic/Jan_Mayen', '$current_timestamp'), ('Atlantic/Madeira', '$current_timestamp'), ('Atlantic/Reykjavik', '$current_timestamp'), ('Atlantic/South_Georgia', '$current_timestamp'), ('Atlantic/St_Helena', '$current_timestamp'), ('Atlantic/Stanley', '$current_timestamp'), ('Australia/ACT', '$current_timestamp'), ('Australia/Adelaide', '$current_timestamp'), ('Australia/Brisbane', '$current_timestamp'), ('Australia/Broken_Hill', '$current_timestamp'), ('Australia/Canberra', '$current_timestamp'), ('Australia/Currie', '$current_timestamp'), ('Australia/Darwin', '$current_timestamp'), ('Australia/Eucla', '$current_timestamp'), ('Australia/Hobart', '$current_timestamp'), ('Australia/LHI', '$current_timestamp'), ('Australia/Lindeman', '$current_timestamp'), ('Australia/Lord_Howe', '$current_timestamp'), ('Australia/Melbourne', '$current_timestamp'), ('Australia/North', '$current_timestamp'), ('Australia/NSW', '$current_timestamp'), ('Australia/Perth', '$current_timestamp'), ('Australia/Queensland', '$current_timestamp'), ('Australia/South', '$current_timestamp'), ('Australia/Sydney', '$current_timestamp'), ('Australia/Tasmania', '$current_timestamp'), ('Australia/Victoria', '$current_timestamp'), ('Australia/West', '$current_timestamp'), ('Australia/Yancowinna', '$current_timestamp'), ('Brazil/Acre', '$current_timestamp'), ('Brazil/DeNoronha', '$current_timestamp'), ('Brazil/East', '$current_timestamp'), ('Brazil/West', '$current_timestamp'), ('Canada/Atlantic', '$current_timestamp'), ('Canada/Central', '$current_timestamp'), ('Canada/East-Saskatchewan', '$current_timestamp'), ('Canada/Eastern', '$current_timestamp'), ('Canada/Mountain', '$current_timestamp'), ('Canada/Newfoundland', '$current_timestamp'), ('Canada/Pacific', '$current_timestamp'), ('Canada/Saskatchewan', '$current_timestamp'), ('Canada/Yukon', '$current_timestamp'), ('Chile/Continental', '$current_timestamp'), ('Chile/EasterIsland', '$current_timestamp'), ('Cuba', '$current_timestamp'), ('Egypt', '$current_timestamp'), ('Eire', '$current_timestamp'), ('Europe/Amsterdam', '$current_timestamp'), ('Europe/Andorra', '$current_timestamp'), ('Europe/Athens', '$current_timestamp'), ('Europe/Belfast', '$current_timestamp'), ('Europe/Belgrade', '$current_timestamp'), ('Europe/Berlin', '$current_timestamp'), ('Europe/Bratislava', '$current_timestamp'), ('Europe/Brussels', '$current_timestamp'), ('Europe/Bucharest', '$current_timestamp'), ('Europe/Budapest', '$current_timestamp'), ('Europe/Busingen', '$current_timestamp'), ('Europe/Chisinau', '$current_timestamp'), ('Europe/Copenhagen', '$current_timestamp'), ('Europe/Dublin', '$current_timestamp'), ('Europe/Gibraltar', '$current_timestamp'), ('Europe/Guernsey', '$current_timestamp'), ('Europe/Helsinki', '$current_timestamp'), ('Europe/Isle_of_Man', '$current_timestamp'), ('Europe/Istanbul', '$current_timestamp'), ('Europe/Jersey', '$current_timestamp'), ('Europe/Kaliningrad', '$current_timestamp'), ('Europe/Kiev', '$current_timestamp'), ('Europe/Lisbon', '$current_timestamp'), ('Europe/Ljubljana', '$current_timestamp'), ('Europe/London', '$current_timestamp'), ('Europe/Luxembourg', '$current_timestamp'), ('Europe/Madrid', '$current_timestamp'), ('Europe/Malta', '$current_timestamp'), ('Europe/Mariehamn', '$current_timestamp'), ('Europe/Minsk', '$current_timestamp'), ('Europe/Monaco', '$current_timestamp'), ('Europe/Moscow', '$current_timestamp'), ('Europe/Nicosia', '$current_timestamp'), ('Europe/Oslo', '$current_timestamp'), ('Europe/Paris', '$current_timestamp'), ('Europe/Podgorica', '$current_timestamp'), ('Europe/Prague', '$current_timestamp'), ('Europe/Riga', '$current_timestamp'), ('Europe/Rome', '$current_timestamp'), ('Europe/Samara', '$current_timestamp'), ('Europe/San_Marino', '$current_timestamp'), ('Europe/Sarajevo', '$current_timestamp'), ('Europe/Simferopol', '$current_timestamp'), ('Europe/Skopje', '$current_timestamp'), ('Europe/Sofia', '$current_timestamp'), ('Europe/Stockholm', '$current_timestamp'), ('Europe/Tallinn', '$current_timestamp'), ('Europe/Tirane', '$current_timestamp'), ('Europe/Tiraspol', '$current_timestamp'), ('Europe/Uzhgorod', '$current_timestamp'), ('Europe/Vaduz', '$current_timestamp'), ('Europe/Vatican', '$current_timestamp'), ('Europe/Vienna', '$current_timestamp'), ('Europe/Vilnius', '$current_timestamp'), ('Europe/Volgograd', '$current_timestamp'), ('Europe/Warsaw', '$current_timestamp'), ('Europe/Zagreb', '$current_timestamp'), ('Europe/Zaporozhye', '$current_timestamp'), ('Europe/Zurich', '$current_timestamp'), ('Greenwich', '$current_timestamp'), ('Hongkong', '$current_timestamp'), ('Iceland', '$current_timestamp'), ('Indian/Antananarivo', '$current_timestamp'), ('Indian/Chagos', '$current_timestamp'), ('Indian/Christmas', '$current_timestamp'), ('Indian/Cocos', '$current_timestamp'), ('Indian/Comoro', '$current_timestamp'), ('Indian/Kerguelen', '$current_timestamp'), ('Indian/Mahe', '$current_timestamp'), ('Indian/Maldives', '$current_timestamp'), ('Indian/Mauritius', '$current_timestamp'), ('Indian/Mayotte', '$current_timestamp'), ('Indian/Reunion', '$current_timestamp'), ('Iran', '$current_timestamp'), ('Israel', '$current_timestamp'), ('Jamaica', '$current_timestamp'), ('Japan', '$current_timestamp'), ('Kwajalein', '$current_timestamp'), ('Libya', '$current_timestamp'), ('Mexico/BajaNorte', '$current_timestamp'), ('Mexico/BajaSur', '$current_timestamp'), ('Mexico/General', '$current_timestamp'), ('Pacific/Apia', '$current_timestamp'), ('Pacific/Auckland', '$current_timestamp'), ('Pacific/Chatham', '$current_timestamp'), ('Pacific/Chuuk', '$current_timestamp'), ('Pacific/Easter', '$current_timestamp'), ('Pacific/Efate', '$current_timestamp'), ('Pacific/Enderbury', '$current_timestamp'), ('Pacific/Fakaofo', '$current_timestamp'), ('Pacific/Fiji', '$current_timestamp'), ('Pacific/Funafuti', '$current_timestamp'), ('Pacific/Galapagos', '$current_timestamp'), ('Pacific/Gambier', '$current_timestamp'), ('Pacific/Guadalcanal', '$current_timestamp'), ('Pacific/Guam', '$current_timestamp'), ('Pacific/Honolulu', '$current_timestamp'), ('Pacific/Johnston', '$current_timestamp'), ('Pacific/Kiritimati', '$current_timestamp'), ('Pacific/Kosrae', '$current_timestamp'), ('Pacific/Kwajalein', '$current_timestamp'), ('Pacific/Majuro', '$current_timestamp'), ('Pacific/Marquesas', '$current_timestamp'), ('Pacific/Midway', '$current_timestamp'), ('Pacific/Nauru', '$current_timestamp'), ('Pacific/Niue', '$current_timestamp'), ('Pacific/Norfolk', '$current_timestamp'), ('Pacific/Noumea', '$current_timestamp'), ('Pacific/Pago_Pago', '$current_timestamp'), ('Pacific/Palau', '$current_timestamp'), ('Pacific/Pitcairn', '$current_timestamp'), ('Pacific/Pohnpei', '$current_timestamp'), ('Pacific/Ponape', '$current_timestamp'), ('Pacific/Port_Moresby', '$current_timestamp'), ('Pacific/Rarotonga', '$current_timestamp'), ('Pacific/Saipan', '$current_timestamp'), ('Pacific/Samoa', '$current_timestamp'), ('Pacific/Tahiti', '$current_timestamp'), ('Pacific/Tarawa', '$current_timestamp'), ('Pacific/Tongatapu', '$current_timestamp'), ('Pacific/Truk', '$current_timestamp'), ('Pacific/Wake', '$current_timestamp'), ('Pacific/Wallis', '$current_timestamp'), ('Pacific/Yap', '$current_timestamp'), ('Poland', '$current_timestamp'), ('Portugal', '$current_timestamp'), ('Singapore', '$current_timestamp'), ('Turkey', '$current_timestamp'), ('US/Alaska', '$current_timestamp'), ('US/Aleutian', '$current_timestamp'), ('US/Arizona', '$current_timestamp'), ('US/Central', '$current_timestamp'), ('US/East-Indiana', '$current_timestamp'), ('US/Eastern', '$current_timestamp'), ('US/Hawaii', '$current_timestamp'), ('US/Indiana-Starke', '$current_timestamp'), ('US/Michigan', '$current_timestamp'), ('US/Mountain', '$current_timestamp'), ('US/Pacific', '$current_timestamp'), ('US/Pacific-New', '$current_timestamp'), ('US/Samoa', '$current_timestamp'), ('Zulu', '$current_timestamp');";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "CREATE TABLE IF NOT EXISTS `updates` (
			`id` int(10) NOT NULL auto_increment,
			`name` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
			`update` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
			`insert_time` datetime NOT NULL,
			`update_time` datetime NOT NULL,
			PRIMARY KEY  (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "INSERT INTO updates
			(name, `update`, insert_time, update_time) VALUES 
			('Domain Manager now contains a Software Updates section!', '<em>[This feature was implemented on 2013-05-04, but it seemed appropriate that the very first post in the Software Updates section be information about the new section itself, so the post was duplicated and backdated]</em><BR><BR>After upgrading Domain Manager I\'m sure it would be nice to know what new features have been added, as well as any important changes to the software that you should know about, so I\'ve added a Software Updates section that chronicles the most important and most useful new features. Now after an upgrade you can simply visit the Software Updates section and view a list of the updates since your previous version.', '2013-03-20 00:00:00', '2013-03-20 00:00:00'),
			('Support has been added for automatic currency updates!', 'Thanks to Yahoo! Finance\'s free API, I\'m happy to announce that currency conversions have been completely automated. Now instead of having to manually update the conversions one-by-one on a regular basis to ensure proper financial reporting, all you have to do is make sure your default currency is set and your conversion rates will be updated automatically and seemlessly in the background while you use the software.<BR><BR>To say that this feature pleases me would be a huge understatement. I personally use the Domain Manager software on a daily basis, and updating the currency conversions manually was always such a boring, tedious task, and I\'m happy that nobody will ever have to go through that process ever again. If I could give Yahoo! Finance a big hug, I would.', '2013-03-20 00:00:01', '2013-03-20 00:00:01'),
			('A new \'IP Address\' section has been added to the UI so that you can keep track of all your IP Addresses within Domain Manager', '', '2013-03-26 00:00:00', '2013-03-26 00:00:00'),
			('Test Data System removed, Demo launched', 'In order to focus on the development of the actual Domain Manager software, I\'ve decided to remove the Test Data System entirely. Although this system allowed users to easily generate some test data and get a feel for the software, it complicated the development process and added unecessary overhead to the software as a whole. Most importantly, it took me away from adding other, more useful features to the core software.<BR><BR>Now instead of testing the software by installing it and generating the test data, you can simply visit <a class=\"invisiblelink\" target=\"_blank\" href=\"http://demos.aysmedia.com/domainmanager/\">http://demos.aysmedia.com/domainmanager/</a> to take Domain Manager for a test drive.', '2013-04-06 00:00:00', '2013-04-06 00:00:00'),
			('Update the Segments UI to give the user a lot more information and flexibility', 'Now when filtering your domains using a segment, Domain Manager will tell you which domains in the segment are stored in your Domain Manager (indicating whether or not the domain is active or inactive), as well as which domains don\'t match, and lastly it will tell you which domains matched but were filtered out based on your other search criteria. Each of the resulting lists can be easily viewed and exported for your convenience.<BR><BR>It took quite a bit of work to get this feature implemented, but the segment filtering just felt incomplete without it. It was still a very useful feature, but now it\'s incredibly powerful, and I hope to add on the functionality in the future.', '2013-04-07 00:00:00', '2013-04-07 00:00:00'),
			('The Domain & SSL search pages have been updated to allow for the exporting of results', '', '2013-04-07 00:00:00', '2013-04-07 00:00:00'),
			('A logo has now been added to the Domain Manager software in order to pretty things up a little bit', '', '2013-04-10 00:00:00', '2013-04-10 00:00:00'),
			('Cron job added for sending an email to users about upcoming Domain and SSL Certificate renewals', 'A cron job has now been added to send a daily email to users letting them know about upcoming domain and SSL expirations. Your system administrator can set how many days are included in the email, and users can subscribe and unsubscribe from this email through their Control Panel.<BR><BR>If you would like to use this feature, just let your system administrator know so that they can schedule the cron job to run.', '2013-04-15 00:00:00', '2013-04-15 00:00:00'),
			('A new \'Web Hosting\' section has been added to the UI so that you can now keep track of your web hosting providers within Domain Manager', '', '2013-04-17 00:00:00', '2013-04-17 00:00:00'),
			('A password field has now been added to Registrar & SSL Provider accounts so that passwords can be managed through Domain Manager', '', '2013-04-19 00:00:00', '2013-04-19 00:00:00'),
			('Update the expiration email so that the System Adminstrator can set the number of days in the future to display in the email', 'Previously when the daily expiration emails were sent out to users they would automatically include the next 60 days of expirations, but this has now been converted to a system setting so that your system administrator can now specify the number of days to include in the email.', '2013-04-19 00:00:01', '2013-04-19 00:00:01'),
			('Remove the (redundant) Domain Status and Status Notes fields', 'Although the Domain Status & Status Notes fields were removed because they were redundant, if you had data stored in either of these fields it would have been appended to the primary Notes field when your Domain Manager database was upgraded. So don\'t worry, dropping these two fields didn\'t cause you to lose any data.', '2013-04-20 00:00:00', '2013-04-20 00:00:00'),
			('Added a \'view full notes\' feature to the Domain and SSL Cert edit pages', 'When editing a Domain or SSL certificate, if you want to view the notes but scrolling through the text box just isn\'t your thing, you can now click on a link to view the full notes on a separate page, making them much easier to read.', '2013-04-24 00:00:00', '2013-04-24 00:00:00'),
			('Reporting section added', 'Domain Manager now includes a handful of reports that can give you valuable insight into your data, and I\'m always on the lookout for more reports that can be added. If you have any new report ideas, or any suggestions for the current reports, feel free to drop me a line at <a class=\"invisiblelink\" href=\"mailto:greg@chetcuti.com\">greg@chetcuti.com</a>.', '2013-04-25 00:00:00', '2013-04-25 00:00:00'),
			('Cron job added for automating currency conversions at regular intervals', 'Never worry about having outdated exchange rates again! Domain Manager now includes a cron job that automates currency conversions. This means you can have the cron job set to run overnight, and when you go to use the Domain Manager software in the morning your currency conversions will already be completely up-to-date.<BR><BR>If you would like to use this feature, just let your system administrator know so that they can schedule the cron job to run.', '2013-04-27 00:00:00', '2013-04-27 00:00:00'),
			('Domain Manager has been converted to UTF-8', 'The entire Domain Manager system has been converted to use the UTF-8 character set in order to allow for support of non-ASCII characters, such as the characters found in some IDNs (Internationalized Domain Names).', '2013-04-27 00:00:01', '2013-04-27 00:00:01'),
			('Currencies have been updated to be user-based instead of system-based', 'Now that Currencies have been re-worked to be user-based, every user in the system can set their own default currency, and this currency will be used for them throughout the system. Every setting, webpage, and report in the Domain Manager system will automatically be converted to display monetary values using the user\'s default currency.', '2013-04-29 00:00:00', '2013-04-29 00:00:00'),
			('Overhaul of Domain Manager Settings Complete!', 'Over the past few months the Domain Manager settings have been undergoing a complete overhaul. The changes include but are not limited to making currency conversions user-based instead of system- based, updating all Domain & SSL default settings to be user-based instead of system-based, separating out Category, IP Addres and Owner settings so that Domains & SSLs have thier own options instead of sharing them, adding support for saving passwords for Registrar & SSL Provider accounts, removing the redundant Status and Status Notes fields from the Domains section, and so on.<BR><BR>I\'m constantly trying to improve the software and make it more user-friendly, so if you have any suggestions or feedback feel free to drop me a line at <a class=\"invisiblelink\" href=\"mailto:greg@chetcuti.com\">greg@chetcuti.com</a>.', '2013-05-02 00:00:00', '2013-05-02 00:00:00'),
			('Domain Manager now contains a Software Updates section!', 'After upgrading Domain Manager I\'m sure it would be nice to know what new features have been added, as well as any important changes to the software that you should know about, so I\'ve added a Software Updates section that chronicles the most important and most useful new features. Now after an upgrade you can simply visit the Software Updates section and view a list of the updates since your previous version.', '2013-05-04 00:00:00', '2013-05-04 00:00:00'),
			('An Export option has been added to all Asset pages', '', '2013-05-06 00:00:00', '2013-05-06 00:00:00')";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$sql = "CREATE TABLE IF NOT EXISTS update_data (
			`id` int(10) NOT NULL auto_increment,
			`user_id` int(10) NOT NULL,
			`update_id` int(10) NOT NULL,
			`insert_time` datetime NOT NULL,
			PRIMARY KEY  (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$sql = "SELECT id
			FROM users";
	$result = mysql_query($sql,$connection);

	while ($row = mysql_fetch_object($result)) {

		$sql_updates = "SELECT id
						FROM `updates`";
		$result_updates = mysql_query($sql_updates,$connection);

		while ($row_updates = mysql_fetch_object($result_updates)) {

			$sql_insert = "INSERT INTO 
						   update_data
						   (user_id, update_id, insert_time) VALUES 
						   ('" . $row->id . "', '" . $row_updates->id . "', '" . $current_timestamp . "')";
			$result_insert = mysql_query($sql_insert,$connection);

		}

	}
	
	$sql = "SELECT *
			FROM `update_data`
			WHERE user_id = '" . $_SESSION['user_id'] . "'";
	$result = mysql_query($sql,$connection);
	if (mysql_num_rows($result) != 0) { $_SESSION['are_there_updates'] = "1"; }

	$sql_settings = "SELECT *
					 FROM settings";
	$result_settings = mysql_query($sql_settings,$connection);
	
	while ($row_settings = mysql_fetch_object($result_settings)) {
		
		$_SESSION['system_full_url'] = $row_settings->full_url;
		$_SESSION['system_db_version'] = $row_settings->db_version;
		$_SESSION['system_email_address'] = $row_settings->email_address;
		$_SESSION['system_default_category_domains'] = $row_settings->default_category_domains;
		$_SESSION['system_default_category_ssl'] = $row_settings->default_category_ssl;
		$_SESSION['system_default_dns'] = $row_settings->default_dns;
		$_SESSION['system_default_host'] = $row_settings->default_host;
		$_SESSION['system_default_ip_address_domains'] = $row_settings->default_ip_address_domains;
		$_SESSION['system_default_ip_address_ssl'] = $row_settings->default_ip_address_ssl;
		$_SESSION['system_default_owner_domains'] = $row_settings->default_owner_domains;
		$_SESSION['system_default_owner_ssl'] = $row_settings->default_owner_ssl;
		$_SESSION['system_default_registrar'] = $row_settings->default_registrar;
		$_SESSION['system_default_registrar_account'] = $row_settings->default_registrar_account;
		$_SESSION['system_default_ssl_provider_account'] = $row_settings->default_ssl_provider_account;
		$_SESSION['system_default_ssl_type'] = $row_settings->default_ssl_type;
		$_SESSION['system_default_ssl_provider'] = $row_settings->default_ssl_provider;
		$_SESSION['system_expiration_email_days'] = $row_settings->expiration_email_days;

	}

	$sql_user_settings = "SELECT *
						  FROM user_settings
						  ORDER BY id desc
						  LIMIT 1";
	$result_user_settings = mysql_query($sql_user_settings,$connection);

	while ($row_user_settings = mysql_fetch_object($result_user_settings)) {

		$_SESSION['default_currency'] = $row_user_settings->default_currency;
		$_SESSION['default_timezone'] = $row_user_settings->default_timezone;
		$_SESSION['default_category_domains'] = $row_user_settings->default_category_domains;
		$_SESSION['default_category_ssl'] = $row_user_settings->default_category_ssl;
		$_SESSION['default_dns'] = $row_user_settings->default_dns;
		$_SESSION['default_host'] = $row_user_settings->default_host;
		$_SESSION['default_ip_address_domains'] = $row_user_settings->default_ip_address_domains;
		$_SESSION['default_ip_address_ssl'] = $row_user_settings->default_ip_address_ssl;
		$_SESSION['default_owner_domains'] = $row_user_settings->default_owner_domains;
		$_SESSION['default_owner_ssl'] = $row_user_settings->default_owner_ssl;
		$_SESSION['default_registrar'] = $row_user_settings->default_registrar;
		$_SESSION['default_registrar_account'] = $row_user_settings->default_registrar_account;
		$_SESSION['default_ssl_provider_account'] = $row_user_settings->default_ssl_provider_account;
		$_SESSION['default_ssl_type'] = $row_user_settings->default_ssl_type;
		$_SESSION['default_ssl_provider'] = $row_user_settings->default_ssl_provider;
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

	$_SESSION['institallation_mode'] = 0;
	$_SESSION['result_message'] = "$software_title has been installed<BR><BR>The default username and password are both set to \"admin\"<BR>";
	
	header("Location: ../");
	exit;

}
?>