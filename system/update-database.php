<?php
// /system/update-database.php
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
include("../_includes/timestamps/current-timestamp.inc.php");
include("../_includes/auth/auth-check.inc.php");

$page_title = "Update Database";
$software_section = "system";

$sql = "SELECT db_version
		FROM settings";
$result = mysql_query($sql,$connection) or die(mysql_error());

while ($row = mysql_fetch_object($result)) {
	$current_db_version = $row->db_version;
}

if ($current_db_version < $most_recent_db_version) {

	// upgrade database from 1.1 to 1.2
	if ($current_db_version == 1.1) {

		$sql = "ALTER TABLE `ssl_certs`  
				ADD `ip` VARCHAR(50) NOT NULL AFTER `name`";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$sql = "UPDATE settings
				SET db_version = '1.2', 
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.2;
		
	}

	// upgrade database from 1.2 to 1.3
	if ($current_db_version == 1.2) {

		$sql = "CREATE TABLE IF NOT EXISTS `ip_addresses` (
				`id` int(10) NOT NULL auto_increment,
				`name` varchar(255) NOT NULL,
				`ip` varchar(255) NOT NULL,
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
				) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$sql = "UPDATE settings
				SET db_version = '1.3', 
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.3;
		
	}

	// upgrade database from 1.3 to 1.4
	if ($current_db_version == 1.3) {

		$sql = "ALTER TABLE `ip_addresses` 
				ADD `notes` longtext NOT NULL AFTER `ip`";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$sql = "UPDATE settings
				SET db_version = '1.4', 
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.4;
		
	}

	// upgrade database from 1.4 to 1.5
	if ($current_db_version == 1.4) {

		$sql = "ALTER TABLE `domains`  
				ADD `ip_id` int(10) NOT NULL default '0' AFTER `dns_id`";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$sql = "UPDATE settings
				SET db_version = '1.5', 
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.5;
		
	}

	// upgrade database from 1.5 to 1.6
	if ($current_db_version == 1.5) {

		$sql = "ALTER TABLE `domains` 
				CHANGE `ip_id` `ip_id` INT(10) NOT NULL DEFAULT '1'";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE `domains` 
				SET ip_id = '1'";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "TRUNCATE `ip_addresses`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "INSERT INTO `ip_addresses` 
				(`id`, `name`, `ip`, `insert_time`) VALUES 
				('1', '[no ip address]', '-', '$current_timestamp')";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$sql = "UPDATE settings
				SET db_version = '1.6', 
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.6;
		
	}

	// upgrade database from 1.6 to 1.7
	if ($current_db_version == 1.6) {

		$sql = "ALTER TABLE `ssl_certs` DROP `ip`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$sql = "UPDATE settings
				SET db_version = '1.7', 
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.7;
		
	}

	// upgrade database from 1.7 to 1.8
	if ($current_db_version == 1.7) {

		$sql = "ALTER TABLE `ip_addresses`  
				ADD `test_data` int(1) NOT NULL default '0' AFTER `notes`";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$sql = "UPDATE settings
				SET db_version = '1.8', 
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.8;
		
	}

	// upgrade database from 1.8 to 1.9
	if ($current_db_version == 1.8) {

		$sql = "ALTER TABLE `settings`  
				ADD `email_address` VARCHAR(255) NOT NULL AFTER `db_version`";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$sql = "UPDATE settings
				SET db_version = '1.9', 
					email_address = 'code@aysmedia.com',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.9;
		
	}

	// upgrade database from 1.9 to 1.91
	if ($current_db_version == 1.9) {

		$sql = "ALTER TABLE `ip_addresses` 
				ADD `rdns` VARCHAR(255) NOT NULL DEFAULT '-' AFTER `ip`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$sql = "UPDATE settings
				SET db_version = '1.91',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.91;
		
	}

	// upgrade database from 1.91 to 1.92
	if ($current_db_version == 1.91) {

		$sql = "ALTER TABLE `settings` 
				ADD `type` VARCHAR(50) NOT NULL AFTER `id`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings 
				SET type =  'system'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$sql = "UPDATE settings
				SET db_version = '1.92',
					update_time = '$current_timestamp'
				WHERE type = 'system'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.92;
		
	}

	// upgrade database from 1.92 to 1.93
	if ($current_db_version == 1.92) {

		$sql = "ALTER TABLE `settings` DROP `type`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '1.93',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.93;
		
	}

	// upgrade database from 1.93 to 1.94
	if ($current_db_version == 1.93) {

		$sql = "ALTER TABLE `settings` 
				ADD `number_of_domains` INT(5) NOT NULL DEFAULT '50' AFTER `email_address`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `settings` 
				ADD `number_of_ssl_certs` INT(5) NOT NULL DEFAULT '50' AFTER `number_of_domains`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '1.94',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.94;
		
	}

	// upgrade database from 1.94 to 1.95
	if ($current_db_version == 1.94) {

		$sql = "ALTER TABLE `currencies` 
				DROP `default_currency`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `settings` 
				ADD `default_currency` VARCHAR(5) NOT NULL DEFAULT 'CAD' AFTER `email_address`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '1.95',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.95;
		
	}

	// upgrade database from 1.95 to 1.96
	if ($current_db_version == 1.95) {

		$sql = "ALTER TABLE `currencies` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '1.96',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.96;
		
	}

	// upgrade database from 1.96 to 1.97
	if ($current_db_version == 1.96) {

		$sql = "CREATE TABLE IF NOT EXISTS `owners` ( 
					`id` int(5) NOT NULL auto_increment,
					`name` varchar(255) NOT NULL,
					`notes` longtext NOT NULL,
					`active` int(1) NOT NULL default '1',
					`test_data` int(1) NOT NULL default '0',
					`insert_time` datetime NOT NULL,
					`update_time` datetime NOT NULL,
					PRIMARY KEY  (`id`),
					KEY `name` (`name`)
				) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "INSERT INTO owners 
					(id, name, notes, active, test_data, insert_time, update_time) 
					SELECT id, name, notes, active, test_data, insert_time, update_time FROM companies ORDER BY id;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "DROP TABLE `companies`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `domains` CHANGE `company_id` `owner_id` INT(5) NOT NULL;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `registrar_accounts` CHANGE `company_id` `owner_id` INT(5) NOT NULL;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `ssl_accounts` CHANGE `company_id` `owner_id` INT(5) NOT NULL;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `ssl_certs` CHANGE `company_id` `owner_id` INT(5) NOT NULL;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '1.97',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.97;
		
	}

	// upgrade database from 1.97 to 1.98
	if ($current_db_version == 1.97) {

		$sql = "INSERT INTO `categories` 
					(`name`, `owner`, `insert_time`) VALUES 
					('[no category]', '[no stakeholder]', '$current_timestamp');";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$sql = "SELECT id
				FROM categories
				WHERE default_category = '1';";
		$result = mysql_query($sql,$connection);

		if (mysql_num_rows($result) == 0) {
			$sql2 = "UPDATE categories
					 SET default_category = '1'
					 WHERE name = '[no category]'";
			$result2 = mysql_query($sql2,$connection);
		}

		$sql = "ALTER TABLE `dns` 
					ADD `default_dns` INT(1) NOT NULL DEFAULT '0' AFTER `number_of_servers`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "INSERT INTO `dns` 
					(`name`, `dns1`, `dns2`, `number_of_servers`, `insert_time`) VALUES 
					('[no dns]', 'ns1.no-dns.com', 'ns2.no-dns.com', '2', '$current_timestamp');";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "SELECT id
				FROM dns
				WHERE default_dns = '1';";
		$result = mysql_query($sql,$connection);

		if (mysql_num_rows($result) == 0) {
			$sql2 = "UPDATE dns
					 SET default_dns = '1'
					 WHERE name = '[no dns]'";
			$result2 = mysql_query($sql2,$connection);
		}

		$sql = "ALTER TABLE `owners`  
					ADD `default_owner` INT(1) NOT NULL DEFAULT '0' AFTER `notes`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "INSERT INTO `owners` 
					(`name`, `insert_time`) VALUES 
					('[no owner]', '$current_timestamp');";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "SELECT id
				FROM owners
				WHERE default_owner = '1';";
		$result = mysql_query($sql,$connection);

		if (mysql_num_rows($result) == 0) {
			$sql2 = "UPDATE owners
					 SET default_owner = '1'
					 WHERE name = '[no owner]'";
			$result2 = mysql_query($sql2,$connection);
		}

		$sql = "ALTER TABLE `ip_addresses` 
					ADD `default_ip_address` INT(1) NOT NULL DEFAULT '0' AFTER `notes`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "SELECT id
				FROM ip_addresses
				WHERE default_ip_address = '1';";
		$result = mysql_query($sql,$connection);

		if (mysql_num_rows($result) == 0) {
			$sql2 = "UPDATE ip_addresses
					 SET default_ip_address = '1'
					 WHERE name = '[no ip address]'";
			$result2 = mysql_query($sql2,$connection);
		}

		$sql = "UPDATE settings
				SET db_version = '1.98',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.98;
		
	}

	// upgrade database from 1.98 to 1.99
	if ($current_db_version == 1.98) {

		$sql = "ALTER TABLE `categories` 
					CHANGE `owner` `stakeholder` VARCHAR(255) NOT NULL;";
		$result = mysql_query($sql,$connection);

		$sql = "UPDATE `categories`
					SET `stakeholder` = '[no stakeholder]' 
				WHERE `stakeholder` = '[no category owner]';";
		$result = mysql_query($sql,$connection);

		$sql = "UPDATE settings
				SET db_version = '1.99',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.99;
		
	}

	// upgrade database from 1.99 to 2.0001
	if ($current_db_version == 1.99) {

		$sql = "ALTER TABLE `currencies` 
					ADD `default_currency` INT(1) NOT NULL DEFAULT '0' AFTER `notes`";
		$result = mysql_query($sql,$connection);

		$sql = "SELECT default_currency
				FROM settings";
		$result = mysql_query($sql,$connection);
		
		while ($row = mysql_fetch_object($result)) {
			$default_currency = $row->default_currency;
		}
		
		$sql = "UPDATE currencies
				SET default_currency = '0'";
		$result = mysql_query($sql,$connection);

		$sql = "UPDATE currencies
				SET default_currency = '1'
				WHERE currency = '" . $default_currency . "'";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `settings` DROP `default_currency`";
		$result = mysql_query($sql,$connection);

		$sql = "UPDATE settings
				SET db_version = '2.0001',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0001;
		
	}

	// upgrade database from 2.0001 to 2.0002
	if ($current_db_version == 2.0001) {

		$sql = "ALTER TABLE `ssl_cert_functions` 
					ADD `default_function` INT(1) NOT NULL DEFAULT '0' AFTER `notes`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `ssl_cert_types` 
					ADD `default_type` INT(1) NOT NULL DEFAULT '0' AFTER `notes`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE ssl_cert_functions
				SET default_function = '1'
				WHERE function = 'Web Server SSL/TLS Certificate'";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE ssl_cert_types
				SET default_type = '1'
				WHERE type = 'Wildcard'";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0002',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0002;

	}

	// upgrade database from 2.0002 to 2.0003
	if ($current_db_version == 2.0002) {

		$sql = "DROP TABLE `ssl_cert_types`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `ssl_certs` DROP `type_id`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `ssl_fees` DROP `type_id`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0003',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0003;

	}

	// upgrade database from 2.0003 to 2.0004
	if ($current_db_version == 2.0003) {

		$sql = "CREATE TABLE IF NOT EXISTS `ssl_cert_types` ( 
					`id` int(10) NOT NULL auto_increment,
					`type` varchar(255) NOT NULL,
					`notes` longtext NOT NULL,
					`default_type` int(1) NOT NULL default '0',
					`active` int(1) NOT NULL default '1',
					`insert_time` datetime NOT NULL,
					`update_time` datetime NOT NULL,
					PRIMARY KEY  (`id`)
				) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "INSERT INTO ssl_cert_types 
					(id, type, notes, default_type, active, insert_time, update_time) 
					SELECT id, function, notes, default_function, active, insert_time, update_time FROM ssl_cert_functions ORDER BY id;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "DROP TABLE `ssl_cert_functions`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `ssl_certs` 
					CHANGE `function_id` `type_id` INT(10) NOT NULL";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `ssl_fees` 
					CHANGE `function_id` `type_id` INT(5) NOT NULL";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0004',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0004;

	}

	// upgrade database from 2.0004 to 2.0005
	if ($current_db_version == 2.0004) {

		$sql = "ALTER TABLE `ssl_cert_types`  
					ADD `test_data` int(1) NOT NULL default '0' AFTER `active`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0005',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0005;

	}

	// upgrade database from 2.0005 to 2.0006
	if ($current_db_version == 2.0005) {

		$sql = "ALTER TABLE `ip_addresses` 
					ADD `active` INT(1) NOT NULL DEFAULT '1' AFTER `default_ip_address`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `domains` 
					CHANGE `active` `active` INT(2) NOT NULL DEFAULT '1'";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0006',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0006;

	}

	// upgrade database from 2.0006 to 2.0007
	if ($current_db_version == 2.0006) {

		$sql = "ALTER TABLE `registrars` 
					ADD `default_registrar` INT(1) NOT NULL DEFAULT '0' AFTER `notes`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `registrar_accounts` 
					ADD `default_account` INT(1) NOT NULL DEFAULT '0' AFTER `reseller`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `ssl_providers` 
					ADD `default_provider` INT(1) NOT NULL DEFAULT '0' AFTER `notes`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `ssl_accounts` 
					ADD `default_account` INT(1) NOT NULL DEFAULT '0' AFTER `reseller`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0007',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0007;

	}

	// upgrade database from 2.0007 to 2.0008
	if ($current_db_version == 2.0007) {

		$sql = "ALTER TABLE `owners` 
					CHANGE `id` `id` INT(10) NOT NULL AUTO_INCREMENT";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `registrars` 
					CHANGE `id` `id` INT(10) NOT NULL AUTO_INCREMENT";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `ssl_providers` 
					CHANGE `id` `id` INT(10) NOT NULL AUTO_INCREMENT";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0008',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0008;

	}

	// upgrade database from 2.0008 to 2.0009
	if ($current_db_version == 2.0008) {

		$sql = "ALTER TABLE `currencies`  
				ADD `test_data` int(1) NOT NULL default '0' AFTER `active`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0009',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0009;

	}

	// upgrade database from 2.0009 to 2.0010
	if ($current_db_version == 2.0009) {

		$sql = "CREATE TABLE IF NOT EXISTS `user_settings` (
					`id` int(10) NOT NULL auto_increment,
					`user_id` int(10) NOT NULL,
					`number_of_domains` int(5) NOT NULL default '50',
					`number_of_ssl_certs` int(5) NOT NULL default '50',
					`display_domain_owner` int(1) NOT NULL default '0',
					`display_domain_registrar` int(1) NOT NULL default '0',
					`display_domain_account` int(1) NOT NULL default '1',
					`display_domain_expiry_date` int(1) NOT NULL default '1',
					`display_domain_category` int(1) NOT NULL default '1',
					`display_domain_dns` int(1) NOT NULL default '0',
					`display_domain_ip` int(1) NOT NULL default '0',
					`display_domain_tld` int(1) NOT NULL default '0',
					`display_ssl_owner` int(1) NOT NULL default '0',
					`display_ssl_provider` int(1) NOT NULL default '0',
					`display_ssl_account` int(1) NOT NULL default '0',
					`display_ssl_domain` int(1) NOT NULL default '0',
					`display_ssl_type` int(1) NOT NULL default '0',
					`display_ssl_expiry_date` int(1) NOT NULL default '0',
					`insert_time` datetime NOT NULL,
					`update_time` datetime NOT NULL,
					PRIMARY KEY  (`id`)
				) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
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

		$sql = "UPDATE settings
				SET db_version = '2.001',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.001;

	}

	// upgrade database from 2.0010 to 2.0011
	if ($current_db_version == 2.001) {

		$sql = "ALTER TABLE `settings` 
					DROP `number_of_domains`, 
					DROP `number_of_ssl_certs`;";
		$result = mysql_query($sql,$connection);

		$sql = "UPDATE settings
				SET db_version = '2.0011',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0011;

	}

	// upgrade database from 2.0011 to 2.0012
	if ($current_db_version == 2.0011) {

		$sql = "ALTER TABLE `user_settings` 
				CHANGE `display_domain_account` `display_domain_account` INT(1) NOT NULL DEFAULT '1'";
		$result = mysql_query($sql,$connection);

		$sql = "UPDATE settings
				SET db_version = '2.0012',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0012;

	}

	// upgrade database from 2.0012 to 2.0013
	if ($current_db_version == 2.0012) {

		$sql = "ALTER TABLE `categories` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `currencies` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `dns` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `domains` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `fees` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `ip_addresses` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `owners` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `registrars` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `registrar_accounts` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `segments` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `segments` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `ssl_accounts` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `ssl_certs` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `ssl_cert_types` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `ssl_fees` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `ssl_providers` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection);

		$sql = "UPDATE settings
				SET db_version = '2.0013',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0013;

	}

	// upgrade database from 2.0013 to 2.0014
	if ($current_db_version == 2.0013) {

		$sql = "CREATE TABLE IF NOT EXISTS `segment_data` (
				`id` int(10) NOT NULL auto_increment,
				`segment_id` int(10) NOT NULL,
				`domain` varchar(255) NOT NULL,
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
				) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
		$result = mysql_query($sql,$connection);

		$sql = "UPDATE settings
				SET db_version = '2.0014',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0014;

	}

	// upgrade database from 2.0014 to 2.0015
	if ($current_db_version == 2.0014) {

		$sql = "ALTER TABLE `user_settings` 
					ADD `display_domain_fee` INT(1) NOT NULL DEFAULT '0' AFTER `display_domain_tld`";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `user_settings` 
					ADD `display_ssl_fee` INT(1) NOT NULL DEFAULT '0' AFTER `display_ssl_expiry_date`";
		$result = mysql_query($sql,$connection);

		$sql = "UPDATE settings
				SET db_version = '2.0015',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0015;

	}

	// upgrade database from 2.0015 to 2.0016
	if ($current_db_version == 2.0015) {

		$sql = "ALTER TABLE `segment_data` 
					ADD `active` INT(1) NOT NULL DEFAULT '0' AFTER `domain`";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `segment_data` 
					ADD `inactive` INT(1) NOT NULL DEFAULT '0' AFTER `active`";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `segment_data` 
					ADD `missing` INT(1) NOT NULL DEFAULT '0' AFTER `inactive`";
		$result = mysql_query($sql,$connection);

		$sql = "UPDATE settings
				SET db_version = '2.0016',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0016;

	}

	// upgrade database from 2.0016 to 2.0017
	if ($current_db_version == 2.0016) {

		$sql = "ALTER TABLE `segment_data` 
					ADD `filtered` INT(1) NOT NULL DEFAULT '0' AFTER `missing`";
		$result = mysql_query($sql,$connection);

		$sql = "UPDATE settings
				SET db_version = '2.0017',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0017;

	}

	// upgrade database from 2.0017 to 2.0018
	if ($current_db_version == 2.0017) {

		$sql = "ALTER TABLE `ssl_certs` 
					CHANGE `domain_id` `domain_id` INT(10) NOT NULL DEFAULT '0'";
		$result = mysql_query($sql,$connection);

		$sql = "UPDATE settings
				SET db_version = '2.0018',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0018;

	}

	// upgrade database from 2.0018 to 2.0019
	if ($current_db_version == 2.0018) {

		$sql = "ALTER TABLE `ssl_certs` 
					CHANGE `domain_id` `domain_id` INT(10) NOT NULL";
		$result = mysql_query($sql,$connection);

		$sql = "UPDATE settings
				SET db_version = '2.0019',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0019;

	}

	// upgrade database from 2.0019 to 2.0020
	if ($current_db_version == 2.0019) {

		$sql = "ALTER TABLE `user_settings`  
					ADD `expiration_emails` INT(1) NOT NULL DEFAULT '1' AFTER `user_id`";
		$result = mysql_query($sql,$connection);

		$sql = "UPDATE settings
				SET db_version = '2.0020',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0020;

	}

	// upgrade database from 2.0020 to 2.0021
	if ($current_db_version == 2.002) {

		$sql = "ALTER TABLE `settings` 
					ADD `full_url` VARCHAR(100) NOT NULL DEFAULT 'http://' AFTER `id`";
		$result = mysql_query($sql,$connection);

		$sql = "UPDATE settings
				SET db_version = '2.0021',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0021;

	}

	// upgrade database from 2.0021 to 2.0022
	if ($current_db_version == 2.0021) {

		$sql = "ALTER TABLE `settings`  
					ADD `timezone` VARCHAR(10) NOT NULL DEFAULT 'Etc/GMT' AFTER `email_address`";
		$result = mysql_query($sql,$connection);

		$sql = "UPDATE settings
				SET db_version = '2.0022',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0022;

	}

	// upgrade database from 2.0022 to 2.0023
	if ($current_db_version == 2.0022) {

		$sql = "CREATE TABLE IF NOT EXISTS `timezones` (
				`id` int(5) NOT NULL auto_increment,
				`timezone` varchar(50) NOT NULL,
				`insert_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
				) ENGINE=MyISAM DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
		$result = mysql_query($sql,$connection);

		$sql = "INSERT INTO `timezones` 
				(`timezone`, `insert_time`) VALUES 
				('Africa/Abidjan', '$current_timestamp'), ('Africa/Accra', '$current_timestamp'), ('Africa/Addis_Ababa', '$current_timestamp'), ('Africa/Algiers', '$current_timestamp'), ('Africa/Asmara', '$current_timestamp'), ('Africa/Asmera', '$current_timestamp'), ('Africa/Bamako', '$current_timestamp'), ('Africa/Bangui', '$current_timestamp'), ('Africa/Banjul', '$current_timestamp'), ('Africa/Bissau', '$current_timestamp'), ('Africa/Blantyre', '$current_timestamp'), ('Africa/Brazzaville', '$current_timestamp'), ('Africa/Bujumbura', '$current_timestamp'), ('Africa/Cairo', '$current_timestamp'), ('Africa/Casablanca', '$current_timestamp'), ('Africa/Ceuta', '$current_timestamp'), ('Africa/Conakry', '$current_timestamp'), ('Africa/Dakar', '$current_timestamp'), ('Africa/Dar_es_Salaam', '$current_timestamp'), ('Africa/Djibouti', '$current_timestamp'), ('Africa/Douala', '$current_timestamp'), ('Africa/El_Aaiun', '$current_timestamp'), ('Africa/Freetown', '$current_timestamp'), ('Africa/Gaborone', '$current_timestamp'), ('Africa/Harare', '$current_timestamp'), ('Africa/Johannesburg', '$current_timestamp'), ('Africa/Juba', '$current_timestamp'), ('Africa/Kampala', '$current_timestamp'), ('Africa/Khartoum', '$current_timestamp'), ('Africa/Kigali', '$current_timestamp'), ('Africa/Kinshasa', '$current_timestamp'), ('Africa/Lagos', '$current_timestamp'), ('Africa/Libreville', '$current_timestamp'), ('Africa/Lome', '$current_timestamp'), ('Africa/Luanda', '$current_timestamp'), ('Africa/Lubumbashi', '$current_timestamp'), ('Africa/Lusaka', '$current_timestamp'), ('Africa/Malabo', '$current_timestamp'), ('Africa/Maputo', '$current_timestamp'), ('Africa/Maseru', '$current_timestamp'), ('Africa/Mbabane', '$current_timestamp'), ('Africa/Mogadishu', '$current_timestamp'), ('Africa/Monrovia', '$current_timestamp'), ('Africa/Nairobi', '$current_timestamp'), ('Africa/Ndjamena', '$current_timestamp'), ('Africa/Niamey', '$current_timestamp'), ('Africa/Nouakchott', '$current_timestamp'), ('Africa/Ouagadougou', '$current_timestamp'), ('Africa/Porto-Novo', '$current_timestamp'), ('Africa/Sao_Tome', '$current_timestamp'), ('Africa/Timbuktu', '$current_timestamp'), ('Africa/Tripoli', '$current_timestamp'), ('Africa/Tunis', '$current_timestamp'), ('Africa/Windhoek', '$current_timestamp'), ('America/Adak', '$current_timestamp'), ('America/Anchorage', '$current_timestamp'), ('America/Anguilla', '$current_timestamp'), ('America/Antigua', '$current_timestamp'), ('America/Araguaina', '$current_timestamp'), ('America/Argentina/Buenos_Aires', '$current_timestamp'), ('America/Argentina/Catamarca', '$current_timestamp'), ('America/Argentina/ComodRivadavia', '$current_timestamp'), ('America/Argentina/Cordoba', '$current_timestamp'), ('America/Argentina/Jujuy', '$current_timestamp'), ('America/Argentina/La_Rioja', '$current_timestamp'), ('America/Argentina/Mendoza', '$current_timestamp'), ('America/Argentina/Rio_Gallegos', '$current_timestamp'), ('America/Argentina/Salta', '$current_timestamp'), ('America/Argentina/San_Juan', '$current_timestamp'), ('America/Argentina/San_Luis', '$current_timestamp'), ('America/Argentina/Tucuman', '$current_timestamp'), ('America/Argentina/Ushuaia', '$current_timestamp'), ('America/Aruba', '$current_timestamp'), ('America/Asuncion', '$current_timestamp'), ('America/Atikokan', '$current_timestamp'), ('America/Atka', '$current_timestamp'), ('America/Bahia', '$current_timestamp'), ('America/Bahia_Banderas', '$current_timestamp'), ('America/Barbados', '$current_timestamp'), ('America/Belem', '$current_timestamp'), ('America/Belize', '$current_timestamp'), ('America/Blanc-Sablon', '$current_timestamp'), ('America/Boa_Vista', '$current_timestamp'), ('America/Bogota', '$current_timestamp'), ('America/Boise', '$current_timestamp'), ('America/Buenos_Aires', '$current_timestamp'), ('America/Cambridge_Bay', '$current_timestamp'), ('America/Campo_Grande', '$current_timestamp'), ('America/Cancun', '$current_timestamp'), ('America/Caracas', '$current_timestamp'), ('America/Catamarca', '$current_timestamp'), ('America/Cayenne', '$current_timestamp'), ('America/Cayman', '$current_timestamp'), ('America/Chicago', '$current_timestamp'), ('America/Chihuahua', '$current_timestamp'), ('America/Coral_Harbour', '$current_timestamp'), ('America/Cordoba', '$current_timestamp'), ('America/Costa_Rica', '$current_timestamp'), ('America/Creston', '$current_timestamp'), ('America/Cuiaba', '$current_timestamp'), ('America/Curacao', '$current_timestamp'), ('America/Danmarkshavn', '$current_timestamp'), ('America/Dawson', '$current_timestamp'), ('America/Dawson_Creek', '$current_timestamp'), ('America/Denver', '$current_timestamp'), ('America/Detroit', '$current_timestamp'), ('America/Dominica', '$current_timestamp'), ('America/Edmonton', '$current_timestamp'), ('America/Eirunepe', '$current_timestamp'), ('America/El_Salvador', '$current_timestamp'), ('America/Ensenada', '$current_timestamp'), ('America/Fort_Wayne', '$current_timestamp'), ('America/Fortaleza', '$current_timestamp'), ('America/Glace_Bay', '$current_timestamp'), ('America/Godthab', '$current_timestamp'), ('America/Goose_Bay', '$current_timestamp'), ('America/Grand_Turk', '$current_timestamp'), ('America/Grenada', '$current_timestamp'), ('America/Guadeloupe', '$current_timestamp'), ('America/Guatemala', '$current_timestamp'), ('America/Guayaquil', '$current_timestamp'), ('America/Guyana', '$current_timestamp'), ('America/Halifax', '$current_timestamp'), ('America/Havana', '$current_timestamp'), ('America/Hermosillo', '$current_timestamp'), ('America/Indiana/Indianapolis', '$current_timestamp'), ('America/Indiana/Knox', '$current_timestamp'), ('America/Indiana/Marengo', '$current_timestamp'), ('America/Indiana/Petersburg', '$current_timestamp'), ('America/Indiana/Tell_City', '$current_timestamp'), ('America/Indiana/Vevay', '$current_timestamp'), ('America/Indiana/Vincennes', '$current_timestamp'), ('America/Indiana/Winamac', '$current_timestamp'), ('America/Indianapolis', '$current_timestamp'), ('America/Inuvik', '$current_timestamp'), ('America/Iqaluit', '$current_timestamp'), ('America/Jamaica', '$current_timestamp'), ('America/Jujuy', '$current_timestamp'), ('America/Juneau', '$current_timestamp'), ('America/Kentucky/Louisville', '$current_timestamp'), ('America/Kentucky/Monticello', '$current_timestamp'), ('America/Knox_IN', '$current_timestamp'), ('America/Kralendijk', '$current_timestamp'), ('America/La_Paz', '$current_timestamp'), ('America/Lima', '$current_timestamp'), ('America/Los_Angeles', '$current_timestamp'), ('America/Louisville', '$current_timestamp'), ('America/Lower_Princes', '$current_timestamp'), ('America/Maceio', '$current_timestamp'), ('America/Managua', '$current_timestamp'), ('America/Manaus', '$current_timestamp'), ('America/Marigot', '$current_timestamp'), ('America/Martinique', '$current_timestamp'), ('America/Matamoros', '$current_timestamp'), ('America/Mazatlan', '$current_timestamp'), ('America/Mendoza', '$current_timestamp'), ('America/Menominee', '$current_timestamp'), ('America/Merida', '$current_timestamp'), ('America/Metlakatla', '$current_timestamp'), ('America/Mexico_City', '$current_timestamp'), ('America/Miquelon', '$current_timestamp'), ('America/Moncton', '$current_timestamp'), ('America/Monterrey', '$current_timestamp'), ('America/Montevideo', '$current_timestamp'), ('America/Montreal', '$current_timestamp'), ('America/Montserrat', '$current_timestamp'), ('America/Nassau', '$current_timestamp'), ('America/New_York', '$current_timestamp'), ('America/Nipigon', '$current_timestamp'), ('America/Nome', '$current_timestamp'), ('America/Noronha', '$current_timestamp'), ('America/North_Dakota/Beulah', '$current_timestamp'), ('America/North_Dakota/Center', '$current_timestamp'), ('America/North_Dakota/New_Salem', '$current_timestamp'), ('America/Ojinaga', '$current_timestamp'), ('America/Panama', '$current_timestamp'), ('America/Pangnirtung', '$current_timestamp'), ('America/Paramaribo', '$current_timestamp'), ('America/Phoenix', '$current_timestamp'), ('America/Port-au-Prince', '$current_timestamp'), ('America/Port_of_Spain', '$current_timestamp'), ('America/Porto_Acre', '$current_timestamp'), ('America/Porto_Velho', '$current_timestamp'), ('America/Puerto_Rico', '$current_timestamp'), ('America/Rainy_River', '$current_timestamp'), ('America/Rankin_Inlet', '$current_timestamp'), ('America/Recife', '$current_timestamp'), ('America/Regina', '$current_timestamp'), ('America/Resolute', '$current_timestamp'), ('America/Rio_Branco', '$current_timestamp'), ('America/Rosario', '$current_timestamp'), ('America/Santa_Isabel', '$current_timestamp'), ('America/Santarem', '$current_timestamp'), ('America/Santiago', '$current_timestamp'), ('America/Santo_Domingo', '$current_timestamp'), ('America/Sao_Paulo', '$current_timestamp'), ('America/Scoresbysund', '$current_timestamp'), ('America/Shiprock', '$current_timestamp'), ('America/Sitka', '$current_timestamp'), ('America/St_Barthelemy', '$current_timestamp'), ('America/St_Johns', '$current_timestamp'), ('America/St_Kitts', '$current_timestamp'), ('America/St_Lucia', '$current_timestamp'), ('America/St_Thomas', '$current_timestamp'), ('America/St_Vincent', '$current_timestamp'), ('America/Swift_Current', '$current_timestamp'), ('America/Tegucigalpa', '$current_timestamp'), ('America/Thule', '$current_timestamp'), ('America/Thunder_Bay', '$current_timestamp'), ('America/Tijuana', '$current_timestamp'), ('America/Toronto', '$current_timestamp'), ('America/Tortola', '$current_timestamp'), ('America/Vancouver', '$current_timestamp'), ('America/Virgin', '$current_timestamp'), ('America/Whitehorse', '$current_timestamp'), ('America/Winnipeg', '$current_timestamp'), ('America/Yakutat', '$current_timestamp'), ('America/Yellowknife', '$current_timestamp'), ('Antarctica/Casey', '$current_timestamp'), ('Antarctica/Davis', '$current_timestamp'), ('Antarctica/DumontDUrville', '$current_timestamp'), ('Antarctica/Macquarie', '$current_timestamp'), ('Antarctica/Mawson', '$current_timestamp'), ('Antarctica/McMurdo', '$current_timestamp'), ('Antarctica/Palmer', '$current_timestamp'), ('Antarctica/Rothera', '$current_timestamp'), ('Antarctica/South_Pole', '$current_timestamp'), ('Antarctica/Syowa', '$current_timestamp'), ('Antarctica/Vostok', '$current_timestamp'), ('Arctic/Longyearbyen', '$current_timestamp'), ('Asia/Aden', '$current_timestamp'), ('Asia/Almaty', '$current_timestamp'), ('Asia/Amman', '$current_timestamp'), ('Asia/Anadyr', '$current_timestamp'), ('Asia/Aqtau', '$current_timestamp'), ('Asia/Aqtobe', '$current_timestamp'), ('Asia/Ashgabat', '$current_timestamp'), ('Asia/Ashkhabad', '$current_timestamp'), ('Asia/Baghdad', '$current_timestamp'), ('Asia/Bahrain', '$current_timestamp'), ('Asia/Baku', '$current_timestamp'), ('Asia/Bangkok', '$current_timestamp'), ('Asia/Beirut', '$current_timestamp'), ('Asia/Bishkek', '$current_timestamp'), ('Asia/Brunei', '$current_timestamp'), ('Asia/Calcutta', '$current_timestamp'), ('Asia/Choibalsan', '$current_timestamp'), ('Asia/Chongqing', '$current_timestamp'), ('Asia/Chungking', '$current_timestamp'), ('Asia/Colombo', '$current_timestamp'), ('Asia/Dacca', '$current_timestamp'), ('Asia/Damascus', '$current_timestamp'), ('Asia/Dhaka', '$current_timestamp'), ('Asia/Dili', '$current_timestamp'), ('Asia/Dubai', '$current_timestamp'), ('Asia/Dushanbe', '$current_timestamp'), ('Asia/Gaza', '$current_timestamp'), ('Asia/Harbin', '$current_timestamp'), ('Asia/Hebron', '$current_timestamp'), ('Asia/Ho_Chi_Minh', '$current_timestamp'), ('Asia/Hong_Kong', '$current_timestamp'), ('Asia/Hovd', '$current_timestamp'), ('Asia/Irkutsk', '$current_timestamp'), ('Asia/Istanbul', '$current_timestamp'), ('Asia/Jakarta', '$current_timestamp'), ('Asia/Jayapura', '$current_timestamp'), ('Asia/Jerusalem', '$current_timestamp'), ('Asia/Kabul', '$current_timestamp'), ('Asia/Kamchatka', '$current_timestamp'), ('Asia/Karachi', '$current_timestamp'), ('Asia/Kashgar', '$current_timestamp'), ('Asia/Kathmandu', '$current_timestamp'), ('Asia/Katmandu', '$current_timestamp'), ('Asia/Khandyga', '$current_timestamp'), ('Asia/Kolkata', '$current_timestamp'), ('Asia/Krasnoyarsk', '$current_timestamp'), ('Asia/Kuala_Lumpur', '$current_timestamp'), ('Asia/Kuching', '$current_timestamp'), ('Asia/Kuwait', '$current_timestamp'), ('Asia/Macao', '$current_timestamp'), ('Asia/Macau', '$current_timestamp'), ('Asia/Magadan', '$current_timestamp'), ('Asia/Makassar', '$current_timestamp'), ('Asia/Manila', '$current_timestamp'), ('Asia/Muscat', '$current_timestamp'), ('Asia/Nicosia', '$current_timestamp'), ('Asia/Novokuznetsk', '$current_timestamp'), ('Asia/Novosibirsk', '$current_timestamp'), ('Asia/Omsk', '$current_timestamp'), ('Asia/Oral', '$current_timestamp'), ('Asia/Phnom_Penh', '$current_timestamp'), ('Asia/Pontianak', '$current_timestamp'), ('Asia/Pyongyang', '$current_timestamp'), ('Asia/Qatar', '$current_timestamp'), ('Asia/Qyzylorda', '$current_timestamp'), ('Asia/Rangoon', '$current_timestamp'), ('Asia/Riyadh', '$current_timestamp'), ('Asia/Saigon', '$current_timestamp'), ('Asia/Sakhalin', '$current_timestamp'), ('Asia/Samarkand', '$current_timestamp'), ('Asia/Seoul', '$current_timestamp'), ('Asia/Shanghai', '$current_timestamp'), ('Asia/Singapore', '$current_timestamp'), ('Asia/Taipei', '$current_timestamp'), ('Asia/Tashkent', '$current_timestamp'), ('Asia/Tbilisi', '$current_timestamp'), ('Asia/Tehran', '$current_timestamp'), ('Asia/Tel_Aviv', '$current_timestamp'), ('Asia/Thimbu', '$current_timestamp'), ('Asia/Thimphu', '$current_timestamp'), ('Asia/Tokyo', '$current_timestamp'), ('Asia/Ujung_Pandang', '$current_timestamp'), ('Asia/Ulaanbaatar', '$current_timestamp'), ('Asia/Ulan_Bator', '$current_timestamp'), ('Asia/Urumqi', '$current_timestamp'), ('Asia/Ust-Nera', '$current_timestamp'), ('Asia/Vientiane', '$current_timestamp'), ('Asia/Vladivostok', '$current_timestamp'), ('Asia/Yakutsk', '$current_timestamp'), ('Asia/Yekaterinburg', '$current_timestamp'), ('Asia/Yerevan', '$current_timestamp'), ('Atlantic/Azores', '$current_timestamp'), ('Atlantic/Bermuda', '$current_timestamp'), ('Atlantic/Canary', '$current_timestamp'), ('Atlantic/Cape_Verde', '$current_timestamp'), ('Atlantic/Faeroe', '$current_timestamp'), ('Atlantic/Faroe', '$current_timestamp'), ('Atlantic/Jan_Mayen', '$current_timestamp'), ('Atlantic/Madeira', '$current_timestamp'), ('Atlantic/Reykjavik', '$current_timestamp'), ('Atlantic/South_Georgia', '$current_timestamp'), ('Atlantic/St_Helena', '$current_timestamp'), ('Atlantic/Stanley', '$current_timestamp'), ('Australia/ACT', '$current_timestamp'), ('Australia/Adelaide', '$current_timestamp'), ('Australia/Brisbane', '$current_timestamp'), ('Australia/Broken_Hill', '$current_timestamp'), ('Australia/Canberra', '$current_timestamp'), ('Australia/Currie', '$current_timestamp'), ('Australia/Darwin', '$current_timestamp'), ('Australia/Eucla', '$current_timestamp'), ('Australia/Hobart', '$current_timestamp'), ('Australia/LHI', '$current_timestamp'), ('Australia/Lindeman', '$current_timestamp'), ('Australia/Lord_Howe', '$current_timestamp'), ('Australia/Melbourne', '$current_timestamp'), ('Australia/North', '$current_timestamp'), ('Australia/NSW', '$current_timestamp'), ('Australia/Perth', '$current_timestamp'), ('Australia/Queensland', '$current_timestamp'), ('Australia/South', '$current_timestamp'), ('Australia/Sydney', '$current_timestamp'), ('Australia/Tasmania', '$current_timestamp'), ('Australia/Victoria', '$current_timestamp'), ('Australia/West', '$current_timestamp'), ('Australia/Yancowinna', '$current_timestamp'), ('Brazil/Acre', '$current_timestamp'), ('Brazil/DeNoronha', '$current_timestamp'), ('Brazil/East', '$current_timestamp'), ('Brazil/West', '$current_timestamp'), ('Canada/Atlantic', '$current_timestamp'), ('Canada/Central', '$current_timestamp'), ('Canada/East-Saskatchewan', '$current_timestamp'), ('Canada/Eastern', '$current_timestamp'), ('Canada/Mountain', '$current_timestamp'), ('Canada/Newfoundland', '$current_timestamp'), ('Canada/Pacific', '$current_timestamp'), ('Canada/Saskatchewan', '$current_timestamp'), ('Canada/Yukon', '$current_timestamp'), ('Chile/Continental', '$current_timestamp'), ('Chile/EasterIsland', '$current_timestamp'), ('Cuba', '$current_timestamp'), ('Egypt', '$current_timestamp'), ('Eire', '$current_timestamp'), ('Europe/Amsterdam', '$current_timestamp'), ('Europe/Andorra', '$current_timestamp'), ('Europe/Athens', '$current_timestamp'), ('Europe/Belfast', '$current_timestamp'), ('Europe/Belgrade', '$current_timestamp'), ('Europe/Berlin', '$current_timestamp'), ('Europe/Bratislava', '$current_timestamp'), ('Europe/Brussels', '$current_timestamp'), ('Europe/Bucharest', '$current_timestamp'), ('Europe/Budapest', '$current_timestamp'), ('Europe/Busingen', '$current_timestamp'), ('Europe/Chisinau', '$current_timestamp'), ('Europe/Copenhagen', '$current_timestamp'), ('Europe/Dublin', '$current_timestamp'), ('Europe/Gibraltar', '$current_timestamp'), ('Europe/Guernsey', '$current_timestamp'), ('Europe/Helsinki', '$current_timestamp'), ('Europe/Isle_of_Man', '$current_timestamp'), ('Europe/Istanbul', '$current_timestamp'), ('Europe/Jersey', '$current_timestamp'), ('Europe/Kaliningrad', '$current_timestamp'), ('Europe/Kiev', '$current_timestamp'), ('Europe/Lisbon', '$current_timestamp'), ('Europe/Ljubljana', '$current_timestamp'), ('Europe/London', '$current_timestamp'), ('Europe/Luxembourg', '$current_timestamp'), ('Europe/Madrid', '$current_timestamp'), ('Europe/Malta', '$current_timestamp'), ('Europe/Mariehamn', '$current_timestamp'), ('Europe/Minsk', '$current_timestamp'), ('Europe/Monaco', '$current_timestamp'), ('Europe/Moscow', '$current_timestamp'), ('Europe/Nicosia', '$current_timestamp'), ('Europe/Oslo', '$current_timestamp'), ('Europe/Paris', '$current_timestamp'), ('Europe/Podgorica', '$current_timestamp'), ('Europe/Prague', '$current_timestamp'), ('Europe/Riga', '$current_timestamp'), ('Europe/Rome', '$current_timestamp'), ('Europe/Samara', '$current_timestamp'), ('Europe/San_Marino', '$current_timestamp'), ('Europe/Sarajevo', '$current_timestamp'), ('Europe/Simferopol', '$current_timestamp'), ('Europe/Skopje', '$current_timestamp'), ('Europe/Sofia', '$current_timestamp'), ('Europe/Stockholm', '$current_timestamp'), ('Europe/Tallinn', '$current_timestamp'), ('Europe/Tirane', '$current_timestamp'), ('Europe/Tiraspol', '$current_timestamp'), ('Europe/Uzhgorod', '$current_timestamp'), ('Europe/Vaduz', '$current_timestamp'), ('Europe/Vatican', '$current_timestamp'), ('Europe/Vienna', '$current_timestamp'), ('Europe/Vilnius', '$current_timestamp'), ('Europe/Volgograd', '$current_timestamp'), ('Europe/Warsaw', '$current_timestamp'), ('Europe/Zagreb', '$current_timestamp'), ('Europe/Zaporozhye', '$current_timestamp'), ('Europe/Zurich', '$current_timestamp'), ('Greenwich', '$current_timestamp'), ('Hongkong', '$current_timestamp'), ('Iceland', '$current_timestamp'), ('Indian/Antananarivo', '$current_timestamp'), ('Indian/Chagos', '$current_timestamp'), ('Indian/Christmas', '$current_timestamp'), ('Indian/Cocos', '$current_timestamp'), ('Indian/Comoro', '$current_timestamp'), ('Indian/Kerguelen', '$current_timestamp'), ('Indian/Mahe', '$current_timestamp'), ('Indian/Maldives', '$current_timestamp'), ('Indian/Mauritius', '$current_timestamp'), ('Indian/Mayotte', '$current_timestamp'), ('Indian/Reunion', '$current_timestamp'), ('Iran', '$current_timestamp'), ('Israel', '$current_timestamp'), ('Jamaica', '$current_timestamp'), ('Japan', '$current_timestamp'), ('Kwajalein', '$current_timestamp'), ('Libya', '$current_timestamp'), ('Mexico/BajaNorte', '$current_timestamp'), ('Mexico/BajaSur', '$current_timestamp'), ('Mexico/General', '$current_timestamp'), ('Pacific/Apia', '$current_timestamp'), ('Pacific/Auckland', '$current_timestamp'), ('Pacific/Chatham', '$current_timestamp'), ('Pacific/Chuuk', '$current_timestamp'), ('Pacific/Easter', '$current_timestamp'), ('Pacific/Efate', '$current_timestamp'), ('Pacific/Enderbury', '$current_timestamp'), ('Pacific/Fakaofo', '$current_timestamp'), ('Pacific/Fiji', '$current_timestamp'), ('Pacific/Funafuti', '$current_timestamp'), ('Pacific/Galapagos', '$current_timestamp'), ('Pacific/Gambier', '$current_timestamp'), ('Pacific/Guadalcanal', '$current_timestamp'), ('Pacific/Guam', '$current_timestamp'), ('Pacific/Honolulu', '$current_timestamp'), ('Pacific/Johnston', '$current_timestamp'), ('Pacific/Kiritimati', '$current_timestamp'), ('Pacific/Kosrae', '$current_timestamp'), ('Pacific/Kwajalein', '$current_timestamp'), ('Pacific/Majuro', '$current_timestamp'), ('Pacific/Marquesas', '$current_timestamp'), ('Pacific/Midway', '$current_timestamp'), ('Pacific/Nauru', '$current_timestamp'), ('Pacific/Niue', '$current_timestamp'), ('Pacific/Norfolk', '$current_timestamp'), ('Pacific/Noumea', '$current_timestamp'), ('Pacific/Pago_Pago', '$current_timestamp'), ('Pacific/Palau', '$current_timestamp'), ('Pacific/Pitcairn', '$current_timestamp'), ('Pacific/Pohnpei', '$current_timestamp'), ('Pacific/Ponape', '$current_timestamp'), ('Pacific/Port_Moresby', '$current_timestamp'), ('Pacific/Rarotonga', '$current_timestamp'), ('Pacific/Saipan', '$current_timestamp'), ('Pacific/Samoa', '$current_timestamp'), ('Pacific/Tahiti', '$current_timestamp'), ('Pacific/Tarawa', '$current_timestamp'), ('Pacific/Tongatapu', '$current_timestamp'), ('Pacific/Truk', '$current_timestamp'), ('Pacific/Wake', '$current_timestamp'), ('Pacific/Wallis', '$current_timestamp'), ('Pacific/Yap', '$current_timestamp'), ('Poland', '$current_timestamp'), ('Portugal', '$current_timestamp'), ('Singapore', '$current_timestamp'), ('Turkey', '$current_timestamp'), ('US/Alaska', '$current_timestamp'), ('US/Aleutian', '$current_timestamp'), ('US/Arizona', '$current_timestamp'), ('US/Central', '$current_timestamp'), ('US/East-Indiana', '$current_timestamp'), ('US/Eastern', '$current_timestamp'), ('US/Hawaii', '$current_timestamp'), ('US/Indiana-Starke', '$current_timestamp'), ('US/Michigan', '$current_timestamp'), ('US/Mountain', '$current_timestamp'), ('US/Pacific', '$current_timestamp'), ('US/Pacific-New', '$current_timestamp'), ('US/Samoa', '$current_timestamp'), ('Zulu', '$current_timestamp');";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0023',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0023;

	}

	// upgrade database from 2.0023 to 2.0024
	if ($current_db_version == 2.0023) {

		$sql = "ALTER TABLE `settings` 
					CHANGE `timezone` `timezone` VARCHAR(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'Canada/Pacific'";
		$result = mysql_query($sql,$connection);

		$sql = "UPDATE settings
				SET db_version = '2.0024',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0024;

	}

	// upgrade database from 2.0024 to 2.0025
	if ($current_db_version == 2.0024) {

		$sql = "CREATE TABLE IF NOT EXISTS `hosting` ( 
				`id` int(10) NOT NULL auto_increment,
				`name` varchar(255) NOT NULL,
				`notes` longtext NOT NULL,
				`default_host` int(1) NOT NULL default '0',
				`active` int(1) NOT NULL default '1',
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
				) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;";
		$result = mysql_query($sql,$connection);

		$sql = "INSERT INTO `hosting` 
					(`name`, `default_host`, `insert_time`) VALUES 
					('[no hosting]', 1, '$current_timestamp');";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `domains`  
					ADD `hosting_id` int(10) NOT NULL default '1' AFTER `ip_id`";
		$result = mysql_query($sql,$connection);

		$sql = "SELECT id
				FROM hosting
				WHERE name = '[no hosting]'";
		$result = mysql_query($sql,$connection);
		
		while ($row = mysql_fetch_object($result)) {
			$temp_hosting_id = $row->id;
		}
		
		$sql = "UPDATE domains
				SET hosting_id = '" . $temp_hosting_id . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `domains` 
					CHANGE `owner_id` `owner_id` INT(5) NOT NULL DEFAULT '1'";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `domains` 
					CHANGE `registrar_id` `registrar_id` INT(5) NOT NULL DEFAULT '1'";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `domains` 
					CHANGE `account_id` `account_id` INT(5) NOT NULL DEFAULT '1'";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `domains` 
					CHANGE `dns_id` `dns_id` INT(5) NOT NULL DEFAULT '1'";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0025',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0025;

	}

	include("../_includes/auth/login-checks/database-version-check.inc.php");

	$_SESSION['session_result_message'] .= "Database Updated<BR>";

} else {

	$_SESSION['session_result_message'] .= "Your database is already up-to-date<BR>";
	
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
?>