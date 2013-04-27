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
			CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
	$result = mysql_query($sql,$connection);

	$sql = "CREATE TABLE IF NOT EXISTS `users` (
				`id` int(10) NOT NULL auto_increment,
				`first_name` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
				`last_name` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
				`username` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
				`email_address` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`password` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
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
					 (user_id, insert_time) VALUES 
					 ('$row->id', '$current_timestamp');";
		$result_temp = mysql_query($sql_temp,$connection);
	}

	$sql = "CREATE TABLE IF NOT EXISTS `categories` ( 
				`id` int(10) NOT NULL auto_increment,
				`name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`stakeholder` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`notes` longtext NOT NULL,
				`default_category` int(1) NOT NULL default '0',
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "INSERT INTO `categories` 
			(`name`, `stakeholder`, `default_category`, `insert_time`) VALUES 
			('[no category]', '[no stakeholder]', 1, '$current_timestamp');";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "CREATE TABLE IF NOT EXISTS `hosting` ( 
				`id` int(10) NOT NULL auto_increment,
				`name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`notes` longtext NOT NULL,
				`default_host` int(1) NOT NULL default '0',
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "INSERT INTO `hosting` 
			(`name`, `default_host`, `insert_time`) VALUES 
			('[no hosting]', 1, '$current_timestamp');";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "CREATE TABLE IF NOT EXISTS `owners` ( 
				`id` int(10) NOT NULL auto_increment,
				`name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`notes` longtext NOT NULL,
				`default_owner` int(1) NOT NULL default '0',
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`),
				KEY `name` (`name`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "INSERT INTO `owners` 
			(`name`, `default_owner`, `insert_time`) VALUES 
			('[no owner]', 1, '$current_timestamp');";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "CREATE TABLE IF NOT EXISTS `currencies` ( 
				`id` int(10) NOT NULL auto_increment,
				`currency` varchar(3) COLLATE utf8_unicode_ci NOT NULL,
				`name` varchar(75) COLLATE utf8_unicode_ci NOT NULL,
				`conversion` float NOT NULL,
				`notes` longtext NOT NULL,
				`default_currency` int(1) NOT NULL default '0',
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$sql = "INSERT INTO `currencies` 
			(`id`, `currency`, `name`, `conversion`, `default_currency`, `insert_time`) VALUES 
			(1, 'CAD', 'Canadian Dollars', 1, 1, '$current_timestamp'),
			(2, 'USD', 'United States Dollars', 0, 0, '$current_timestamp'),
			(3, 'EUR', 'Euros', 0, 0, '$current_timestamp'),
			(4, 'AUD', 'Australian Dollars', 0, 0, '$current_timestamp'),
			(5, 'CHF', 'Switzerland Francs', 0, 0, '$current_timestamp'),
			(6, 'CNY', 'China Yuan Renminbi', 0, 0, '$current_timestamp'),
			(7, 'DKK', 'Denmark Kroner', 0, 0, '$current_timestamp'),
			(8, 'GBP', 'United Kingdom Pounds', 0, 0, '$current_timestamp'),
			(9, 'HKD', 'Hong Kong Dollars', 0, 0, '$current_timestamp'),
			(10, 'HUF', 'Hungary Forint', 0, 0, '$current_timestamp'),
			(11, 'INR', 'India Rupees', 0, 0, '$current_timestamp'),
			(12, 'JPY', 'Japan Yen', 0, 0, '$current_timestamp'),
			(13, 'MXN', 'Mexico Pesos', 0, 0, '$current_timestamp'),
			(14, 'MYR', 'Malaysia Ringgits', 0, 0, '$current_timestamp'),
			(15, 'NOK', 'Norway Kroner', 0, 0, '$current_timestamp'),
			(16, 'NZD', 'New Zealand Dollars', 0, 0, '$current_timestamp'),
			(17, 'RUB', 'Russia Rubles', 0, 0, '$current_timestamp'),
			(18, 'SEK', 'Sweden Kronor', 0, 0, '$current_timestamp'),
			(19, 'SGD', 'Singapore Dollars', 0, 0, '$current_timestamp'),
			(20, 'THB', 'Thailand Baht', 0, 0, '$current_timestamp'),
			(21, 'ZAR', 'South Africa Rand', 0, 0, '$current_timestamp'),
			(22, 'AED', 'United Arab Emirates Dirhams', 0, 0, '$current_timestamp');";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$sql = "CREATE TABLE IF NOT EXISTS `fees` ( 
				`id` int(10) NOT NULL auto_increment,
				`registrar_id` int(10) NOT NULL,
				`tld` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
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
				`domain` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`tld` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
				`expiry_date` date NOT NULL,
				`cat_id` int(10) NOT NULL default '1',
				`fee_id` int(10) NOT NULL default '0',
				`dns_id` int(10) NOT NULL default '1',
				`ip_id` int(10) NOT NULL default '1',
				`hosting_id` int(10) NOT NULL default '1',
				`function` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`notes` longtext NOT NULL,
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
				`name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`expiry_date` date NOT NULL,
				`fee_id` int(10) NOT NULL,
				`notes` longtext NOT NULL,
				`active` int(1) NOT NULL default '1',
				`fee_fixed` int(1) NOT NULL,
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$sql = "CREATE TABLE IF NOT EXISTS `ssl_cert_types` ( 
				`id` int(10) NOT NULL auto_increment,
				`type` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`notes` longtext NOT NULL,
				`default_type` int(1) NOT NULL default '0',
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$sql = "INSERT INTO `ssl_cert_types` 
			(`id`, `type`, `default_type`, `insert_time`) VALUES 
			(1, 'Web Server SSL/TLS Certificate', 1, '$current_timestamp'),
			(2, 'S/MIME and Authentication Certificate', 0, '$current_timestamp'),
			(3, 'Object Code Signing Certificate', 0, '$current_timestamp'),
			(4, 'Digital ID', 0, '$current_timestamp');";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$sql = "CREATE TABLE IF NOT EXISTS `dns` ( 
				`id` int(10) NOT NULL auto_increment,
				`name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`dns1` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`dns2` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`dns3` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`dns4` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`dns5` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`dns6` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`dns7` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`dns8` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`dns9` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`dns10` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`ip1` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`ip2` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`ip3` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`ip4` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`ip5` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`ip6` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`ip7` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`ip8` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`ip9` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`ip10` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`notes` longtext NOT NULL,
				`number_of_servers` int(2) NOT NULL default '0',
				`default_dns` int(1) NOT NULL default '0',
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "INSERT INTO `dns` 
			(`name`, `dns1`, `dns2`, `number_of_servers`, `default_dns`, `insert_time`) VALUES 
			('[no dns]', 'ns1.no-dns.com', 'ns2.no-dns.com', '2', 1, '$current_timestamp');";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$sql = "CREATE TABLE IF NOT EXISTS `registrars` ( 
				`id` int(10) NOT NULL auto_increment,
				`name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`url` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`notes` longtext NOT NULL,
				`default_registrar` int(1) NOT NULL default '0',
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`),
				KEY `name` (`name`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$sql = "CREATE TABLE IF NOT EXISTS `ssl_providers` ( 
				`id` int(10) NOT NULL auto_increment,
				`name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`url` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`notes` longtext NOT NULL,
				`default_provider` int(1) NOT NULL default '0',
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$sql = "CREATE TABLE IF NOT EXISTS `registrar_accounts` ( 
				`id` int(10) NOT NULL auto_increment,
				`owner_id` int(10) NOT NULL,
				`registrar_id` int(10) NOT NULL,
				`username` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`password` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
				`notes` longtext NOT NULL,
				`reseller` int(1) NOT NULL default '0',
				`default_account` int(1) NOT NULL default '0',
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
				`username` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`password` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
				`notes` longtext NOT NULL,
				`reseller` int(1) NOT NULL default '0',
				`default_account` int(1) NOT NULL default '0',
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`),
				KEY `ssl_provider_id` (`ssl_provider_id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$sql = "CREATE TABLE IF NOT EXISTS `segments` ( 
				`id` int(10) NOT NULL auto_increment,
				`name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`description` longtext NOT NULL,
				`segment` longtext NOT NULL,
				`number_of_domains` int(6) NOT NULL,
				`notes` longtext NOT NULL,
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "CREATE TABLE IF NOT EXISTS `segment_data` (
			`id` int(10) NOT NULL auto_increment,
			`segment_id` int(10) NOT NULL,
			`domain` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
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
				`name` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`ip` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`rdns` varchar(255) COLLATE utf8_unicode_ci NOT NULL default '-',
				`notes` longtext NOT NULL,
				`default_ip_address` int(1) NOT NULL default '0',
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$sql = "INSERT INTO `ip_addresses` 
			(`id`, `name`, `ip`, `rdns`, `default_ip_address`, `insert_time`) VALUES 
			('1', '[no ip address]', '-', '-', 1, '$current_timestamp');";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$sql = "CREATE TABLE IF NOT EXISTS `settings` ( 
				`id` int(10) NOT NULL auto_increment,
				`full_url` varchar(100) COLLATE utf8_unicode_ci NOT NULL default 'http://',
				`db_version` float NOT NULL,
				`email_address` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
				`timezone` varchar(50) COLLATE utf8_unicode_ci NOT NULL default 'Canada/Pacific',
				`expiration_email_days` int(3) NOT NULL default '60',
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "INSERT INTO `settings` 
			(`db_version`, `email_address`, `insert_time`) VALUES 
			('$most_recent_db_version', 'dm@aysmedia.com', '$current_timestamp');";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "CREATE TABLE IF NOT EXISTS `timezones` (
				`id` int(5) NOT NULL auto_increment,
				`timezone` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
				`insert_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "INSERT INTO `timezones` 
			(`timezone`, `insert_time`) VALUES 
			('Africa/Abidjan', '$current_timestamp'), ('Africa/Accra', '$current_timestamp'), ('Africa/Addis_Ababa', '$current_timestamp'), ('Africa/Algiers', '$current_timestamp'), ('Africa/Asmara', '$current_timestamp'), ('Africa/Asmera', '$current_timestamp'), ('Africa/Bamako', '$current_timestamp'), ('Africa/Bangui', '$current_timestamp'), ('Africa/Banjul', '$current_timestamp'), ('Africa/Bissau', '$current_timestamp'), ('Africa/Blantyre', '$current_timestamp'), ('Africa/Brazzaville', '$current_timestamp'), ('Africa/Bujumbura', '$current_timestamp'), ('Africa/Cairo', '$current_timestamp'), ('Africa/Casablanca', '$current_timestamp'), ('Africa/Ceuta', '$current_timestamp'), ('Africa/Conakry', '$current_timestamp'), ('Africa/Dakar', '$current_timestamp'), ('Africa/Dar_es_Salaam', '$current_timestamp'), ('Africa/Djibouti', '$current_timestamp'), ('Africa/Douala', '$current_timestamp'), ('Africa/El_Aaiun', '$current_timestamp'), ('Africa/Freetown', '$current_timestamp'), ('Africa/Gaborone', '$current_timestamp'), ('Africa/Harare', '$current_timestamp'), ('Africa/Johannesburg', '$current_timestamp'), ('Africa/Juba', '$current_timestamp'), ('Africa/Kampala', '$current_timestamp'), ('Africa/Khartoum', '$current_timestamp'), ('Africa/Kigali', '$current_timestamp'), ('Africa/Kinshasa', '$current_timestamp'), ('Africa/Lagos', '$current_timestamp'), ('Africa/Libreville', '$current_timestamp'), ('Africa/Lome', '$current_timestamp'), ('Africa/Luanda', '$current_timestamp'), ('Africa/Lubumbashi', '$current_timestamp'), ('Africa/Lusaka', '$current_timestamp'), ('Africa/Malabo', '$current_timestamp'), ('Africa/Maputo', '$current_timestamp'), ('Africa/Maseru', '$current_timestamp'), ('Africa/Mbabane', '$current_timestamp'), ('Africa/Mogadishu', '$current_timestamp'), ('Africa/Monrovia', '$current_timestamp'), ('Africa/Nairobi', '$current_timestamp'), ('Africa/Ndjamena', '$current_timestamp'), ('Africa/Niamey', '$current_timestamp'), ('Africa/Nouakchott', '$current_timestamp'), ('Africa/Ouagadougou', '$current_timestamp'), ('Africa/Porto-Novo', '$current_timestamp'), ('Africa/Sao_Tome', '$current_timestamp'), ('Africa/Timbuktu', '$current_timestamp'), ('Africa/Tripoli', '$current_timestamp'), ('Africa/Tunis', '$current_timestamp'), ('Africa/Windhoek', '$current_timestamp'), ('America/Adak', '$current_timestamp'), ('America/Anchorage', '$current_timestamp'), ('America/Anguilla', '$current_timestamp'), ('America/Antigua', '$current_timestamp'), ('America/Araguaina', '$current_timestamp'), ('America/Argentina/Buenos_Aires', '$current_timestamp'), ('America/Argentina/Catamarca', '$current_timestamp'), ('America/Argentina/ComodRivadavia', '$current_timestamp'), ('America/Argentina/Cordoba', '$current_timestamp'), ('America/Argentina/Jujuy', '$current_timestamp'), ('America/Argentina/La_Rioja', '$current_timestamp'), ('America/Argentina/Mendoza', '$current_timestamp'), ('America/Argentina/Rio_Gallegos', '$current_timestamp'), ('America/Argentina/Salta', '$current_timestamp'), ('America/Argentina/San_Juan', '$current_timestamp'), ('America/Argentina/San_Luis', '$current_timestamp'), ('America/Argentina/Tucuman', '$current_timestamp'), ('America/Argentina/Ushuaia', '$current_timestamp'), ('America/Aruba', '$current_timestamp'), ('America/Asuncion', '$current_timestamp'), ('America/Atikokan', '$current_timestamp'), ('America/Atka', '$current_timestamp'), ('America/Bahia', '$current_timestamp'), ('America/Bahia_Banderas', '$current_timestamp'), ('America/Barbados', '$current_timestamp'), ('America/Belem', '$current_timestamp'), ('America/Belize', '$current_timestamp'), ('America/Blanc-Sablon', '$current_timestamp'), ('America/Boa_Vista', '$current_timestamp'), ('America/Bogota', '$current_timestamp'), ('America/Boise', '$current_timestamp'), ('America/Buenos_Aires', '$current_timestamp'), ('America/Cambridge_Bay', '$current_timestamp'), ('America/Campo_Grande', '$current_timestamp'), ('America/Cancun', '$current_timestamp'), ('America/Caracas', '$current_timestamp'), ('America/Catamarca', '$current_timestamp'), ('America/Cayenne', '$current_timestamp'), ('America/Cayman', '$current_timestamp'), ('America/Chicago', '$current_timestamp'), ('America/Chihuahua', '$current_timestamp'), ('America/Coral_Harbour', '$current_timestamp'), ('America/Cordoba', '$current_timestamp'), ('America/Costa_Rica', '$current_timestamp'), ('America/Creston', '$current_timestamp'), ('America/Cuiaba', '$current_timestamp'), ('America/Curacao', '$current_timestamp'), ('America/Danmarkshavn', '$current_timestamp'), ('America/Dawson', '$current_timestamp'), ('America/Dawson_Creek', '$current_timestamp'), ('America/Denver', '$current_timestamp'), ('America/Detroit', '$current_timestamp'), ('America/Dominica', '$current_timestamp'), ('America/Edmonton', '$current_timestamp'), ('America/Eirunepe', '$current_timestamp'), ('America/El_Salvador', '$current_timestamp'), ('America/Ensenada', '$current_timestamp'), ('America/Fort_Wayne', '$current_timestamp'), ('America/Fortaleza', '$current_timestamp'), ('America/Glace_Bay', '$current_timestamp'), ('America/Godthab', '$current_timestamp'), ('America/Goose_Bay', '$current_timestamp'), ('America/Grand_Turk', '$current_timestamp'), ('America/Grenada', '$current_timestamp'), ('America/Guadeloupe', '$current_timestamp'), ('America/Guatemala', '$current_timestamp'), ('America/Guayaquil', '$current_timestamp'), ('America/Guyana', '$current_timestamp'), ('America/Halifax', '$current_timestamp'), ('America/Havana', '$current_timestamp'), ('America/Hermosillo', '$current_timestamp'), ('America/Indiana/Indianapolis', '$current_timestamp'), ('America/Indiana/Knox', '$current_timestamp'), ('America/Indiana/Marengo', '$current_timestamp'), ('America/Indiana/Petersburg', '$current_timestamp'), ('America/Indiana/Tell_City', '$current_timestamp'), ('America/Indiana/Vevay', '$current_timestamp'), ('America/Indiana/Vincennes', '$current_timestamp'), ('America/Indiana/Winamac', '$current_timestamp'), ('America/Indianapolis', '$current_timestamp'), ('America/Inuvik', '$current_timestamp'), ('America/Iqaluit', '$current_timestamp'), ('America/Jamaica', '$current_timestamp'), ('America/Jujuy', '$current_timestamp'), ('America/Juneau', '$current_timestamp'), ('America/Kentucky/Louisville', '$current_timestamp'), ('America/Kentucky/Monticello', '$current_timestamp'), ('America/Knox_IN', '$current_timestamp'), ('America/Kralendijk', '$current_timestamp'), ('America/La_Paz', '$current_timestamp'), ('America/Lima', '$current_timestamp'), ('America/Los_Angeles', '$current_timestamp'), ('America/Louisville', '$current_timestamp'), ('America/Lower_Princes', '$current_timestamp'), ('America/Maceio', '$current_timestamp'), ('America/Managua', '$current_timestamp'), ('America/Manaus', '$current_timestamp'), ('America/Marigot', '$current_timestamp'), ('America/Martinique', '$current_timestamp'), ('America/Matamoros', '$current_timestamp'), ('America/Mazatlan', '$current_timestamp'), ('America/Mendoza', '$current_timestamp'), ('America/Menominee', '$current_timestamp'), ('America/Merida', '$current_timestamp'), ('America/Metlakatla', '$current_timestamp'), ('America/Mexico_City', '$current_timestamp'), ('America/Miquelon', '$current_timestamp'), ('America/Moncton', '$current_timestamp'), ('America/Monterrey', '$current_timestamp'), ('America/Montevideo', '$current_timestamp'), ('America/Montreal', '$current_timestamp'), ('America/Montserrat', '$current_timestamp'), ('America/Nassau', '$current_timestamp'), ('America/New_York', '$current_timestamp'), ('America/Nipigon', '$current_timestamp'), ('America/Nome', '$current_timestamp'), ('America/Noronha', '$current_timestamp'), ('America/North_Dakota/Beulah', '$current_timestamp'), ('America/North_Dakota/Center', '$current_timestamp'), ('America/North_Dakota/New_Salem', '$current_timestamp'), ('America/Ojinaga', '$current_timestamp'), ('America/Panama', '$current_timestamp'), ('America/Pangnirtung', '$current_timestamp'), ('America/Paramaribo', '$current_timestamp'), ('America/Phoenix', '$current_timestamp'), ('America/Port-au-Prince', '$current_timestamp'), ('America/Port_of_Spain', '$current_timestamp'), ('America/Porto_Acre', '$current_timestamp'), ('America/Porto_Velho', '$current_timestamp'), ('America/Puerto_Rico', '$current_timestamp'), ('America/Rainy_River', '$current_timestamp'), ('America/Rankin_Inlet', '$current_timestamp'), ('America/Recife', '$current_timestamp'), ('America/Regina', '$current_timestamp'), ('America/Resolute', '$current_timestamp'), ('America/Rio_Branco', '$current_timestamp'), ('America/Rosario', '$current_timestamp'), ('America/Santa_Isabel', '$current_timestamp'), ('America/Santarem', '$current_timestamp'), ('America/Santiago', '$current_timestamp'), ('America/Santo_Domingo', '$current_timestamp'), ('America/Sao_Paulo', '$current_timestamp'), ('America/Scoresbysund', '$current_timestamp'), ('America/Shiprock', '$current_timestamp'), ('America/Sitka', '$current_timestamp'), ('America/St_Barthelemy', '$current_timestamp'), ('America/St_Johns', '$current_timestamp'), ('America/St_Kitts', '$current_timestamp'), ('America/St_Lucia', '$current_timestamp'), ('America/St_Thomas', '$current_timestamp'), ('America/St_Vincent', '$current_timestamp'), ('America/Swift_Current', '$current_timestamp'), ('America/Tegucigalpa', '$current_timestamp'), ('America/Thule', '$current_timestamp'), ('America/Thunder_Bay', '$current_timestamp'), ('America/Tijuana', '$current_timestamp'), ('America/Toronto', '$current_timestamp'), ('America/Tortola', '$current_timestamp'), ('America/Vancouver', '$current_timestamp'), ('America/Virgin', '$current_timestamp'), ('America/Whitehorse', '$current_timestamp'), ('America/Winnipeg', '$current_timestamp'), ('America/Yakutat', '$current_timestamp'), ('America/Yellowknife', '$current_timestamp'), ('Antarctica/Casey', '$current_timestamp'), ('Antarctica/Davis', '$current_timestamp'), ('Antarctica/DumontDUrville', '$current_timestamp'), ('Antarctica/Macquarie', '$current_timestamp'), ('Antarctica/Mawson', '$current_timestamp'), ('Antarctica/McMurdo', '$current_timestamp'), ('Antarctica/Palmer', '$current_timestamp'), ('Antarctica/Rothera', '$current_timestamp'), ('Antarctica/South_Pole', '$current_timestamp'), ('Antarctica/Syowa', '$current_timestamp'), ('Antarctica/Vostok', '$current_timestamp'), ('Arctic/Longyearbyen', '$current_timestamp'), ('Asia/Aden', '$current_timestamp'), ('Asia/Almaty', '$current_timestamp'), ('Asia/Amman', '$current_timestamp'), ('Asia/Anadyr', '$current_timestamp'), ('Asia/Aqtau', '$current_timestamp'), ('Asia/Aqtobe', '$current_timestamp'), ('Asia/Ashgabat', '$current_timestamp'), ('Asia/Ashkhabad', '$current_timestamp'), ('Asia/Baghdad', '$current_timestamp'), ('Asia/Bahrain', '$current_timestamp'), ('Asia/Baku', '$current_timestamp'), ('Asia/Bangkok', '$current_timestamp'), ('Asia/Beirut', '$current_timestamp'), ('Asia/Bishkek', '$current_timestamp'), ('Asia/Brunei', '$current_timestamp'), ('Asia/Calcutta', '$current_timestamp'), ('Asia/Choibalsan', '$current_timestamp'), ('Asia/Chongqing', '$current_timestamp'), ('Asia/Chungking', '$current_timestamp'), ('Asia/Colombo', '$current_timestamp'), ('Asia/Dacca', '$current_timestamp'), ('Asia/Damascus', '$current_timestamp'), ('Asia/Dhaka', '$current_timestamp'), ('Asia/Dili', '$current_timestamp'), ('Asia/Dubai', '$current_timestamp'), ('Asia/Dushanbe', '$current_timestamp'), ('Asia/Gaza', '$current_timestamp'), ('Asia/Harbin', '$current_timestamp'), ('Asia/Hebron', '$current_timestamp'), ('Asia/Ho_Chi_Minh', '$current_timestamp'), ('Asia/Hong_Kong', '$current_timestamp'), ('Asia/Hovd', '$current_timestamp'), ('Asia/Irkutsk', '$current_timestamp'), ('Asia/Istanbul', '$current_timestamp'), ('Asia/Jakarta', '$current_timestamp'), ('Asia/Jayapura', '$current_timestamp'), ('Asia/Jerusalem', '$current_timestamp'), ('Asia/Kabul', '$current_timestamp'), ('Asia/Kamchatka', '$current_timestamp'), ('Asia/Karachi', '$current_timestamp'), ('Asia/Kashgar', '$current_timestamp'), ('Asia/Kathmandu', '$current_timestamp'), ('Asia/Katmandu', '$current_timestamp'), ('Asia/Khandyga', '$current_timestamp'), ('Asia/Kolkata', '$current_timestamp'), ('Asia/Krasnoyarsk', '$current_timestamp'), ('Asia/Kuala_Lumpur', '$current_timestamp'), ('Asia/Kuching', '$current_timestamp'), ('Asia/Kuwait', '$current_timestamp'), ('Asia/Macao', '$current_timestamp'), ('Asia/Macau', '$current_timestamp'), ('Asia/Magadan', '$current_timestamp'), ('Asia/Makassar', '$current_timestamp'), ('Asia/Manila', '$current_timestamp'), ('Asia/Muscat', '$current_timestamp'), ('Asia/Nicosia', '$current_timestamp'), ('Asia/Novokuznetsk', '$current_timestamp'), ('Asia/Novosibirsk', '$current_timestamp'), ('Asia/Omsk', '$current_timestamp'), ('Asia/Oral', '$current_timestamp'), ('Asia/Phnom_Penh', '$current_timestamp'), ('Asia/Pontianak', '$current_timestamp'), ('Asia/Pyongyang', '$current_timestamp'), ('Asia/Qatar', '$current_timestamp'), ('Asia/Qyzylorda', '$current_timestamp'), ('Asia/Rangoon', '$current_timestamp'), ('Asia/Riyadh', '$current_timestamp'), ('Asia/Saigon', '$current_timestamp'), ('Asia/Sakhalin', '$current_timestamp'), ('Asia/Samarkand', '$current_timestamp'), ('Asia/Seoul', '$current_timestamp'), ('Asia/Shanghai', '$current_timestamp'), ('Asia/Singapore', '$current_timestamp'), ('Asia/Taipei', '$current_timestamp'), ('Asia/Tashkent', '$current_timestamp'), ('Asia/Tbilisi', '$current_timestamp'), ('Asia/Tehran', '$current_timestamp'), ('Asia/Tel_Aviv', '$current_timestamp'), ('Asia/Thimbu', '$current_timestamp'), ('Asia/Thimphu', '$current_timestamp'), ('Asia/Tokyo', '$current_timestamp'), ('Asia/Ujung_Pandang', '$current_timestamp'), ('Asia/Ulaanbaatar', '$current_timestamp'), ('Asia/Ulan_Bator', '$current_timestamp'), ('Asia/Urumqi', '$current_timestamp'), ('Asia/Ust-Nera', '$current_timestamp'), ('Asia/Vientiane', '$current_timestamp'), ('Asia/Vladivostok', '$current_timestamp'), ('Asia/Yakutsk', '$current_timestamp'), ('Asia/Yekaterinburg', '$current_timestamp'), ('Asia/Yerevan', '$current_timestamp'), ('Atlantic/Azores', '$current_timestamp'), ('Atlantic/Bermuda', '$current_timestamp'), ('Atlantic/Canary', '$current_timestamp'), ('Atlantic/Cape_Verde', '$current_timestamp'), ('Atlantic/Faeroe', '$current_timestamp'), ('Atlantic/Faroe', '$current_timestamp'), ('Atlantic/Jan_Mayen', '$current_timestamp'), ('Atlantic/Madeira', '$current_timestamp'), ('Atlantic/Reykjavik', '$current_timestamp'), ('Atlantic/South_Georgia', '$current_timestamp'), ('Atlantic/St_Helena', '$current_timestamp'), ('Atlantic/Stanley', '$current_timestamp'), ('Australia/ACT', '$current_timestamp'), ('Australia/Adelaide', '$current_timestamp'), ('Australia/Brisbane', '$current_timestamp'), ('Australia/Broken_Hill', '$current_timestamp'), ('Australia/Canberra', '$current_timestamp'), ('Australia/Currie', '$current_timestamp'), ('Australia/Darwin', '$current_timestamp'), ('Australia/Eucla', '$current_timestamp'), ('Australia/Hobart', '$current_timestamp'), ('Australia/LHI', '$current_timestamp'), ('Australia/Lindeman', '$current_timestamp'), ('Australia/Lord_Howe', '$current_timestamp'), ('Australia/Melbourne', '$current_timestamp'), ('Australia/North', '$current_timestamp'), ('Australia/NSW', '$current_timestamp'), ('Australia/Perth', '$current_timestamp'), ('Australia/Queensland', '$current_timestamp'), ('Australia/South', '$current_timestamp'), ('Australia/Sydney', '$current_timestamp'), ('Australia/Tasmania', '$current_timestamp'), ('Australia/Victoria', '$current_timestamp'), ('Australia/West', '$current_timestamp'), ('Australia/Yancowinna', '$current_timestamp'), ('Brazil/Acre', '$current_timestamp'), ('Brazil/DeNoronha', '$current_timestamp'), ('Brazil/East', '$current_timestamp'), ('Brazil/West', '$current_timestamp'), ('Canada/Atlantic', '$current_timestamp'), ('Canada/Central', '$current_timestamp'), ('Canada/East-Saskatchewan', '$current_timestamp'), ('Canada/Eastern', '$current_timestamp'), ('Canada/Mountain', '$current_timestamp'), ('Canada/Newfoundland', '$current_timestamp'), ('Canada/Pacific', '$current_timestamp'), ('Canada/Saskatchewan', '$current_timestamp'), ('Canada/Yukon', '$current_timestamp'), ('Chile/Continental', '$current_timestamp'), ('Chile/EasterIsland', '$current_timestamp'), ('Cuba', '$current_timestamp'), ('Egypt', '$current_timestamp'), ('Eire', '$current_timestamp'), ('Europe/Amsterdam', '$current_timestamp'), ('Europe/Andorra', '$current_timestamp'), ('Europe/Athens', '$current_timestamp'), ('Europe/Belfast', '$current_timestamp'), ('Europe/Belgrade', '$current_timestamp'), ('Europe/Berlin', '$current_timestamp'), ('Europe/Bratislava', '$current_timestamp'), ('Europe/Brussels', '$current_timestamp'), ('Europe/Bucharest', '$current_timestamp'), ('Europe/Budapest', '$current_timestamp'), ('Europe/Busingen', '$current_timestamp'), ('Europe/Chisinau', '$current_timestamp'), ('Europe/Copenhagen', '$current_timestamp'), ('Europe/Dublin', '$current_timestamp'), ('Europe/Gibraltar', '$current_timestamp'), ('Europe/Guernsey', '$current_timestamp'), ('Europe/Helsinki', '$current_timestamp'), ('Europe/Isle_of_Man', '$current_timestamp'), ('Europe/Istanbul', '$current_timestamp'), ('Europe/Jersey', '$current_timestamp'), ('Europe/Kaliningrad', '$current_timestamp'), ('Europe/Kiev', '$current_timestamp'), ('Europe/Lisbon', '$current_timestamp'), ('Europe/Ljubljana', '$current_timestamp'), ('Europe/London', '$current_timestamp'), ('Europe/Luxembourg', '$current_timestamp'), ('Europe/Madrid', '$current_timestamp'), ('Europe/Malta', '$current_timestamp'), ('Europe/Mariehamn', '$current_timestamp'), ('Europe/Minsk', '$current_timestamp'), ('Europe/Monaco', '$current_timestamp'), ('Europe/Moscow', '$current_timestamp'), ('Europe/Nicosia', '$current_timestamp'), ('Europe/Oslo', '$current_timestamp'), ('Europe/Paris', '$current_timestamp'), ('Europe/Podgorica', '$current_timestamp'), ('Europe/Prague', '$current_timestamp'), ('Europe/Riga', '$current_timestamp'), ('Europe/Rome', '$current_timestamp'), ('Europe/Samara', '$current_timestamp'), ('Europe/San_Marino', '$current_timestamp'), ('Europe/Sarajevo', '$current_timestamp'), ('Europe/Simferopol', '$current_timestamp'), ('Europe/Skopje', '$current_timestamp'), ('Europe/Sofia', '$current_timestamp'), ('Europe/Stockholm', '$current_timestamp'), ('Europe/Tallinn', '$current_timestamp'), ('Europe/Tirane', '$current_timestamp'), ('Europe/Tiraspol', '$current_timestamp'), ('Europe/Uzhgorod', '$current_timestamp'), ('Europe/Vaduz', '$current_timestamp'), ('Europe/Vatican', '$current_timestamp'), ('Europe/Vienna', '$current_timestamp'), ('Europe/Vilnius', '$current_timestamp'), ('Europe/Volgograd', '$current_timestamp'), ('Europe/Warsaw', '$current_timestamp'), ('Europe/Zagreb', '$current_timestamp'), ('Europe/Zaporozhye', '$current_timestamp'), ('Europe/Zurich', '$current_timestamp'), ('Greenwich', '$current_timestamp'), ('Hongkong', '$current_timestamp'), ('Iceland', '$current_timestamp'), ('Indian/Antananarivo', '$current_timestamp'), ('Indian/Chagos', '$current_timestamp'), ('Indian/Christmas', '$current_timestamp'), ('Indian/Cocos', '$current_timestamp'), ('Indian/Comoro', '$current_timestamp'), ('Indian/Kerguelen', '$current_timestamp'), ('Indian/Mahe', '$current_timestamp'), ('Indian/Maldives', '$current_timestamp'), ('Indian/Mauritius', '$current_timestamp'), ('Indian/Mayotte', '$current_timestamp'), ('Indian/Reunion', '$current_timestamp'), ('Iran', '$current_timestamp'), ('Israel', '$current_timestamp'), ('Jamaica', '$current_timestamp'), ('Japan', '$current_timestamp'), ('Kwajalein', '$current_timestamp'), ('Libya', '$current_timestamp'), ('Mexico/BajaNorte', '$current_timestamp'), ('Mexico/BajaSur', '$current_timestamp'), ('Mexico/General', '$current_timestamp'), ('Pacific/Apia', '$current_timestamp'), ('Pacific/Auckland', '$current_timestamp'), ('Pacific/Chatham', '$current_timestamp'), ('Pacific/Chuuk', '$current_timestamp'), ('Pacific/Easter', '$current_timestamp'), ('Pacific/Efate', '$current_timestamp'), ('Pacific/Enderbury', '$current_timestamp'), ('Pacific/Fakaofo', '$current_timestamp'), ('Pacific/Fiji', '$current_timestamp'), ('Pacific/Funafuti', '$current_timestamp'), ('Pacific/Galapagos', '$current_timestamp'), ('Pacific/Gambier', '$current_timestamp'), ('Pacific/Guadalcanal', '$current_timestamp'), ('Pacific/Guam', '$current_timestamp'), ('Pacific/Honolulu', '$current_timestamp'), ('Pacific/Johnston', '$current_timestamp'), ('Pacific/Kiritimati', '$current_timestamp'), ('Pacific/Kosrae', '$current_timestamp'), ('Pacific/Kwajalein', '$current_timestamp'), ('Pacific/Majuro', '$current_timestamp'), ('Pacific/Marquesas', '$current_timestamp'), ('Pacific/Midway', '$current_timestamp'), ('Pacific/Nauru', '$current_timestamp'), ('Pacific/Niue', '$current_timestamp'), ('Pacific/Norfolk', '$current_timestamp'), ('Pacific/Noumea', '$current_timestamp'), ('Pacific/Pago_Pago', '$current_timestamp'), ('Pacific/Palau', '$current_timestamp'), ('Pacific/Pitcairn', '$current_timestamp'), ('Pacific/Pohnpei', '$current_timestamp'), ('Pacific/Ponape', '$current_timestamp'), ('Pacific/Port_Moresby', '$current_timestamp'), ('Pacific/Rarotonga', '$current_timestamp'), ('Pacific/Saipan', '$current_timestamp'), ('Pacific/Samoa', '$current_timestamp'), ('Pacific/Tahiti', '$current_timestamp'), ('Pacific/Tarawa', '$current_timestamp'), ('Pacific/Tongatapu', '$current_timestamp'), ('Pacific/Truk', '$current_timestamp'), ('Pacific/Wake', '$current_timestamp'), ('Pacific/Wallis', '$current_timestamp'), ('Pacific/Yap', '$current_timestamp'), ('Poland', '$current_timestamp'), ('Portugal', '$current_timestamp'), ('Singapore', '$current_timestamp'), ('Turkey', '$current_timestamp'), ('US/Alaska', '$current_timestamp'), ('US/Aleutian', '$current_timestamp'), ('US/Arizona', '$current_timestamp'), ('US/Central', '$current_timestamp'), ('US/East-Indiana', '$current_timestamp'), ('US/Eastern', '$current_timestamp'), ('US/Hawaii', '$current_timestamp'), ('US/Indiana-Starke', '$current_timestamp'), ('US/Michigan', '$current_timestamp'), ('US/Mountain', '$current_timestamp'), ('US/Pacific', '$current_timestamp'), ('US/Pacific-New', '$current_timestamp'), ('US/Samoa', '$current_timestamp'), ('Zulu', '$current_timestamp');";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$_SESSION['institallation_mode'] = 0;
	$_SESSION['result_message'] = "$software_title has been installed<BR><BR>The default username and password are both set to \"admin\"<BR>";
	
	header("Location: ../");
	exit;

}
?>