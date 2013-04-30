<?php
// /_includes/system/update-database.inc.php
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
$direct = $_GET['direct'];
if ($direct == "1") { session_start(); }

include($_SESSION['full_server_path'] . "/_includes/config.inc.php");
include($_SESSION['full_server_path'] . "/_includes/database.inc.php");
include($_SESSION['full_server_path'] . "/_includes/software.inc.php");
include($_SESSION['full_server_path'] . "/_includes/timestamps/current-timestamp.inc.php");
include($_SESSION['full_server_path'] . "/_includes/auth/auth-check.inc.php");

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
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
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
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
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
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
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
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.5;
		
	}

	// upgrade database from 1.5 to 1.6
	if ($current_db_version == 1.5) {

		$sql = "ALTER TABLE `domains` 
				CHANGE `ip_id` `ip_id` INT(10) NOT NULL DEFAULT '1'";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE `domains` 
				SET ip_id = '1',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "TRUNCATE `ip_addresses`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "INSERT INTO `ip_addresses` 
				(`id`, `name`, `ip`, `insert_time`) VALUES 
				('1', '[no ip address]', '-', '" . mysql_real_escape_string($current_timestamp) . "')";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$sql = "UPDATE settings
				SET db_version = '1.6', 
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.6;
		
	}

	// upgrade database from 1.6 to 1.7
	if ($current_db_version == 1.6) {

		$sql = "ALTER TABLE `ssl_certs` 
				DROP `ip`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$sql = "UPDATE settings
				SET db_version = '1.7', 
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
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
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
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
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
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
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.91;
		
	}

	// upgrade database from 1.91 to 1.92
	if ($current_db_version == 1.91) {

		$sql = "ALTER TABLE `settings` 
				ADD `type` VARCHAR(50) NOT NULL AFTER `id`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings 
				SET type =  'system',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$sql = "UPDATE settings
				SET db_version = '1.92',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'
				WHERE type = 'system'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.92;
		
	}

	// upgrade database from 1.92 to 1.93
	if ($current_db_version == 1.92) {

		$sql = "ALTER TABLE `settings` 
				DROP `type`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '1.93',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
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
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
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
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
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
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
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
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.97;
		
	}

	// upgrade database from 1.97 to 1.98
	if ($current_db_version == 1.97) {

		$sql = "INSERT INTO `categories` 
					(`name`, `owner`, `insert_time`) VALUES 
					('[no category]', '[no stakeholder]', '" . mysql_real_escape_string($current_timestamp) . "');";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$sql = "SELECT id
				FROM categories
				WHERE default_category = '1';";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		if (mysql_num_rows($result) == 0) {
			$sql_update = "UPDATE categories
						   SET default_category = '1',
						   	   update_time = '" . mysql_real_escape_string($current_timestamp) . "'
						   WHERE name = '[no category]'";
			$result_update = mysql_query($sql_update,$connection) or die(mysql_error());
		}

		$sql = "ALTER TABLE `dns` 
					ADD `default_dns` INT(1) NOT NULL DEFAULT '0' AFTER `number_of_servers`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "INSERT INTO `dns` 
					(`name`, `dns1`, `dns2`, `number_of_servers`, `insert_time`) VALUES 
					('[no dns]', 'ns1.no-dns.com', 'ns2.no-dns.com', '2', '" . mysql_real_escape_string($current_timestamp) . "');";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "SELECT id
				FROM dns
				WHERE default_dns = '1';";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		if (mysql_num_rows($result) == 0) {
			$sql_update = "UPDATE dns
						   SET default_dns = '1',
						   	   update_time = '" . mysql_real_escape_string($current_timestamp) . "'
						   WHERE name = '[no dns]'";
			$result_update = mysql_query($sql_update,$connection) or die(mysql_error());
		}

		$sql = "ALTER TABLE `owners`  
					ADD `default_owner` INT(1) NOT NULL DEFAULT '0' AFTER `notes`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "INSERT INTO `owners` 
					(`name`, `insert_time`) VALUES 
					('[no owner]', '" . mysql_real_escape_string($current_timestamp) . "');";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "SELECT id
				FROM owners
				WHERE default_owner = '1';";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		if (mysql_num_rows($result) == 0) {
			$sql_update = "UPDATE owners
						   SET default_owner = '1',
						   	   update_time = '" . mysql_real_escape_string($current_timestamp) . "'
						   WHERE name = '[no owner]'";
			$result_update = mysql_query($sql_update,$connection) or die(mysql_error());
		}

		$sql = "ALTER TABLE `ip_addresses` 
					ADD `default_ip_address` INT(1) NOT NULL DEFAULT '0' AFTER `notes`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "SELECT id
				FROM ip_addresses
				WHERE default_ip_address = '1';";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		if (mysql_num_rows($result) == 0) {
			$sql_update = "UPDATE ip_addresses
						   SET default_ip_address = '1',
						   	   update_time = '" . mysql_real_escape_string($current_timestamp) . "'
						   WHERE name = '[no ip address]'";
			$result_update = mysql_query($sql_update,$connection) or die(mysql_error());
		}

		$sql = "UPDATE settings
				SET db_version = '1.98',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.98;
		
	}

	// upgrade database from 1.98 to 1.99
	if ($current_db_version == 1.98) {

		$sql = "ALTER TABLE `categories` 
					CHANGE `owner` `stakeholder` VARCHAR(255) NOT NULL;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE `categories`
					SET `stakeholder` = '[no stakeholder]',
						`update_time` = '" . mysql_real_escape_string($current_timestamp) . "'
				WHERE `stakeholder` = '[no category owner]';";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '1.99',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.99;
		
	}

	// upgrade database from 1.99 to 2.0001
	if ($current_db_version == 1.99) {

		$sql = "ALTER TABLE `currencies` 
					ADD `default_currency` INT(1) NOT NULL DEFAULT '0' AFTER `notes`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "SELECT default_currency
				FROM settings";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		while ($row = mysql_fetch_object($result)) {
			$default_currency = $row->default_currency;
		}
		
		$sql = "UPDATE currencies
				SET default_currency = '0',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE currencies
				SET default_currency = '1',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'
				WHERE currency = '" . mysql_real_escape_string($default_currency) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `settings` 
				DROP `default_currency`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0001',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
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
				SET default_function = '1',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'
				WHERE function = 'Web Server SSL/TLS Certificate'";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE ssl_cert_types
				SET default_type = '1',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'
				WHERE type = 'Wildcard'";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0002',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0002;

	}

	// upgrade database from 2.0002 to 2.0003
	if ($current_db_version == 2.0002) {

		$sql = "DROP TABLE `ssl_cert_types`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `ssl_certs` 
				DROP `type_id`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `ssl_fees` 
				DROP `type_id`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0003',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
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
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
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
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
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
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
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
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
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
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
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
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
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
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		while ($row = mysql_fetch_object($result)) {
			$sql_temp = "INSERT INTO user_settings
						 (user_id, insert_time) VALUES 
						 ('" . mysql_real_escape_string($row->id) . "', '" . mysql_real_escape_string($current_timestamp) . "');";
			$result_temp = mysql_query($sql_temp,$connection) or die(mysql_error());
		}

		$sql = "UPDATE settings
				SET db_version = '2.001',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.001;

	}

	// upgrade database from 2.0010 to 2.0011
	if ($current_db_version == 2.001) {

		$sql = "ALTER TABLE `settings` 
					DROP `number_of_domains`, 
					DROP `number_of_ssl_certs`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0011',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0011;

	}

	// upgrade database from 2.0011 to 2.0012
	if ($current_db_version == 2.0011) {

		$sql = "ALTER TABLE `user_settings` 
				CHANGE `display_domain_account` `display_domain_account` INT(1) NOT NULL DEFAULT '1'";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0012',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0012;

	}

	// upgrade database from 2.0012 to 2.0013
	if ($current_db_version == 2.0012) {

		$sql = "ALTER TABLE `categories` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `currencies` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `dns` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `domains` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `fees` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `ip_addresses` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `owners` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `registrars` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `registrar_accounts` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `segments` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `segments` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `ssl_accounts` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `ssl_certs` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `ssl_cert_types` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `ssl_fees` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `ssl_providers` 
				DROP `test_data`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0013',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
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
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0014',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0014;

	}

	// upgrade database from 2.0014 to 2.0015
	if ($current_db_version == 2.0014) {

		$sql = "ALTER TABLE `user_settings` 
					ADD `display_domain_fee` INT(1) NOT NULL DEFAULT '0' AFTER `display_domain_tld`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `user_settings` 
					ADD `display_ssl_fee` INT(1) NOT NULL DEFAULT '0' AFTER `display_ssl_expiry_date`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0015',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0015;

	}

	// upgrade database from 2.0015 to 2.0016
	if ($current_db_version == 2.0015) {

		$sql = "ALTER TABLE `segment_data` 
					ADD `active` INT(1) NOT NULL DEFAULT '0' AFTER `domain`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `segment_data` 
					ADD `inactive` INT(1) NOT NULL DEFAULT '0' AFTER `active`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `segment_data` 
					ADD `missing` INT(1) NOT NULL DEFAULT '0' AFTER `inactive`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0016',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0016;

	}

	// upgrade database from 2.0016 to 2.0017
	if ($current_db_version == 2.0016) {

		$sql = "ALTER TABLE `segment_data` 
					ADD `filtered` INT(1) NOT NULL DEFAULT '0' AFTER `missing`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0017',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0017;

	}

	// upgrade database from 2.0017 to 2.0018
	if ($current_db_version == 2.0017) {

		$sql = "ALTER TABLE `ssl_certs` 
					CHANGE `domain_id` `domain_id` INT(10) NOT NULL DEFAULT '0'";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0018',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0018;

	}

	// upgrade database from 2.0018 to 2.0019
	if ($current_db_version == 2.0018) {

		$sql = "ALTER TABLE `ssl_certs` 
					CHANGE `domain_id` `domain_id` INT(10) NOT NULL";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0019',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0019;

	}

	// upgrade database from 2.0019 to 2.0020
	if ($current_db_version == 2.0019) {

		$sql = "ALTER TABLE `user_settings`  
					ADD `expiration_emails` INT(1) NOT NULL DEFAULT '1' AFTER `user_id`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0020',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0020;

	}

	// upgrade database from 2.0020 to 2.0021
	if ($current_db_version == 2.002) {

		$sql = "ALTER TABLE `settings` 
					ADD `full_url` VARCHAR(100) NOT NULL DEFAULT 'http://' AFTER `id`";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$full_url = substr($_SERVER["HTTP_REFERER"], 0, -1);
		
		$sql = "UPDATE settings
				SET full_url = '" . $full_url . "'";
		$result = mysql_query($sql,$connection);

		$sql = "UPDATE settings
				SET db_version = '2.0021',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0021;

	}

	// upgrade database from 2.0021 to 2.0022
	if ($current_db_version == 2.0021) {

		$sql = "ALTER TABLE `settings`  
					ADD `timezone` VARCHAR(10) NOT NULL DEFAULT 'Etc/GMT' AFTER `email_address`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0022',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
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
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "INSERT INTO `timezones` 
				(`timezone`, `insert_time`) VALUES 
				('Africa/Abidjan', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Accra', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Addis_Ababa', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Algiers', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Asmara', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Asmera', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Bamako', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Bangui', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Banjul', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Bissau', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Blantyre', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Brazzaville', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Bujumbura', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Cairo', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Casablanca', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Ceuta', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Conakry', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Dakar', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Dar_es_Salaam', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Djibouti', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Douala', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/El_Aaiun', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Freetown', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Gaborone', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Harare', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Johannesburg', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Juba', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Kampala', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Khartoum', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Kigali', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Kinshasa', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Lagos', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Libreville', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Lome', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Luanda', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Lubumbashi', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Lusaka', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Malabo', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Maputo', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Maseru', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Mbabane', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Mogadishu', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Monrovia', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Nairobi', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Ndjamena', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Niamey', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Nouakchott', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Ouagadougou', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Porto-Novo', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Sao_Tome', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Timbuktu', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Tripoli', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Tunis', '" . mysql_real_escape_string($current_timestamp) . "'), ('Africa/Windhoek', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Adak', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Anchorage', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Anguilla', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Antigua', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Araguaina', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Argentina/Buenos_Aires', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Argentina/Catamarca', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Argentina/ComodRivadavia', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Argentina/Cordoba', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Argentina/Jujuy', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Argentina/La_Rioja', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Argentina/Mendoza', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Argentina/Rio_Gallegos', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Argentina/Salta', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Argentina/San_Juan', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Argentina/San_Luis', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Argentina/Tucuman', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Argentina/Ushuaia', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Aruba', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Asuncion', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Atikokan', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Atka', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Bahia', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Bahia_Banderas', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Barbados', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Belem', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Belize', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Blanc-Sablon', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Boa_Vista', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Bogota', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Boise', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Buenos_Aires', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Cambridge_Bay', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Campo_Grande', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Cancun', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Caracas', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Catamarca', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Cayenne', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Cayman', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Chicago', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Chihuahua', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Coral_Harbour', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Cordoba', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Costa_Rica', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Creston', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Cuiaba', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Curacao', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Danmarkshavn', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Dawson', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Dawson_Creek', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Denver', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Detroit', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Dominica', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Edmonton', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Eirunepe', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/El_Salvador', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Ensenada', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Fort_Wayne', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Fortaleza', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Glace_Bay', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Godthab', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Goose_Bay', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Grand_Turk', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Grenada', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Guadeloupe', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Guatemala', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Guayaquil', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Guyana', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Halifax', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Havana', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Hermosillo', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Indiana/Indianapolis', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Indiana/Knox', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Indiana/Marengo', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Indiana/Petersburg', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Indiana/Tell_City', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Indiana/Vevay', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Indiana/Vincennes', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Indiana/Winamac', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Indianapolis', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Inuvik', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Iqaluit', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Jamaica', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Jujuy', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Juneau', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Kentucky/Louisville', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Kentucky/Monticello', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Knox_IN', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Kralendijk', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/La_Paz', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Lima', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Los_Angeles', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Louisville', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Lower_Princes', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Maceio', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Managua', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Manaus', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Marigot', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Martinique', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Matamoros', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Mazatlan', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Mendoza', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Menominee', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Merida', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Metlakatla', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Mexico_City', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Miquelon', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Moncton', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Monterrey', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Montevideo', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Montreal', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Montserrat', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Nassau', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/New_York', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Nipigon', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Nome', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Noronha', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/North_Dakota/Beulah', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/North_Dakota/Center', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/North_Dakota/New_Salem', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Ojinaga', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Panama', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Pangnirtung', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Paramaribo', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Phoenix', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Port-au-Prince', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Port_of_Spain', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Porto_Acre', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Porto_Velho', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Puerto_Rico', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Rainy_River', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Rankin_Inlet', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Recife', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Regina', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Resolute', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Rio_Branco', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Rosario', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Santa_Isabel', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Santarem', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Santiago', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Santo_Domingo', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Sao_Paulo', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Scoresbysund', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Shiprock', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Sitka', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/St_Barthelemy', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/St_Johns', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/St_Kitts', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/St_Lucia', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/St_Thomas', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/St_Vincent', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Swift_Current', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Tegucigalpa', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Thule', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Thunder_Bay', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Tijuana', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Toronto', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Tortola', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Vancouver', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Virgin', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Whitehorse', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Winnipeg', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Yakutat', '" . mysql_real_escape_string($current_timestamp) . "'), ('America/Yellowknife', '" . mysql_real_escape_string($current_timestamp) . "'), ('Antarctica/Casey', '" . mysql_real_escape_string($current_timestamp) . "'), ('Antarctica/Davis', '" . mysql_real_escape_string($current_timestamp) . "'), ('Antarctica/DumontDUrville', '" . mysql_real_escape_string($current_timestamp) . "'), ('Antarctica/Macquarie', '" . mysql_real_escape_string($current_timestamp) . "'), ('Antarctica/Mawson', '" . mysql_real_escape_string($current_timestamp) . "'), ('Antarctica/McMurdo', '" . mysql_real_escape_string($current_timestamp) . "'), ('Antarctica/Palmer', '" . mysql_real_escape_string($current_timestamp) . "'), ('Antarctica/Rothera', '" . mysql_real_escape_string($current_timestamp) . "'), ('Antarctica/South_Pole', '" . mysql_real_escape_string($current_timestamp) . "'), ('Antarctica/Syowa', '" . mysql_real_escape_string($current_timestamp) . "'), ('Antarctica/Vostok', '" . mysql_real_escape_string($current_timestamp) . "'), ('Arctic/Longyearbyen', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Aden', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Almaty', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Amman', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Anadyr', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Aqtau', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Aqtobe', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Ashgabat', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Ashkhabad', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Baghdad', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Bahrain', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Baku', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Bangkok', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Beirut', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Bishkek', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Brunei', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Calcutta', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Choibalsan', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Chongqing', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Chungking', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Colombo', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Dacca', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Damascus', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Dhaka', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Dili', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Dubai', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Dushanbe', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Gaza', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Harbin', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Hebron', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Ho_Chi_Minh', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Hong_Kong', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Hovd', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Irkutsk', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Istanbul', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Jakarta', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Jayapura', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Jerusalem', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Kabul', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Kamchatka', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Karachi', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Kashgar', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Kathmandu', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Katmandu', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Khandyga', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Kolkata', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Krasnoyarsk', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Kuala_Lumpur', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Kuching', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Kuwait', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Macao', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Macau', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Magadan', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Makassar', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Manila', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Muscat', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Nicosia', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Novokuznetsk', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Novosibirsk', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Omsk', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Oral', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Phnom_Penh', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Pontianak', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Pyongyang', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Qatar', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Qyzylorda', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Rangoon', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Riyadh', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Saigon', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Sakhalin', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Samarkand', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Seoul', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Shanghai', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Singapore', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Taipei', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Tashkent', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Tbilisi', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Tehran', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Tel_Aviv', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Thimbu', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Thimphu', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Tokyo', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Ujung_Pandang', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Ulaanbaatar', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Ulan_Bator', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Urumqi', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Ust-Nera', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Vientiane', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Vladivostok', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Yakutsk', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Yekaterinburg', '" . mysql_real_escape_string($current_timestamp) . "'), ('Asia/Yerevan', '" . mysql_real_escape_string($current_timestamp) . "'), ('Atlantic/Azores', '" . mysql_real_escape_string($current_timestamp) . "'), ('Atlantic/Bermuda', '" . mysql_real_escape_string($current_timestamp) . "'), ('Atlantic/Canary', '" . mysql_real_escape_string($current_timestamp) . "'), ('Atlantic/Cape_Verde', '" . mysql_real_escape_string($current_timestamp) . "'), ('Atlantic/Faeroe', '" . mysql_real_escape_string($current_timestamp) . "'), ('Atlantic/Faroe', '" . mysql_real_escape_string($current_timestamp) . "'), ('Atlantic/Jan_Mayen', '" . mysql_real_escape_string($current_timestamp) . "'), ('Atlantic/Madeira', '" . mysql_real_escape_string($current_timestamp) . "'), ('Atlantic/Reykjavik', '" . mysql_real_escape_string($current_timestamp) . "'), ('Atlantic/South_Georgia', '" . mysql_real_escape_string($current_timestamp) . "'), ('Atlantic/St_Helena', '" . mysql_real_escape_string($current_timestamp) . "'), ('Atlantic/Stanley', '" . mysql_real_escape_string($current_timestamp) . "'), ('Australia/ACT', '" . mysql_real_escape_string($current_timestamp) . "'), ('Australia/Adelaide', '" . mysql_real_escape_string($current_timestamp) . "'), ('Australia/Brisbane', '" . mysql_real_escape_string($current_timestamp) . "'), ('Australia/Broken_Hill', '" . mysql_real_escape_string($current_timestamp) . "'), ('Australia/Canberra', '" . mysql_real_escape_string($current_timestamp) . "'), ('Australia/Currie', '" . mysql_real_escape_string($current_timestamp) . "'), ('Australia/Darwin', '" . mysql_real_escape_string($current_timestamp) . "'), ('Australia/Eucla', '" . mysql_real_escape_string($current_timestamp) . "'), ('Australia/Hobart', '" . mysql_real_escape_string($current_timestamp) . "'), ('Australia/LHI', '" . mysql_real_escape_string($current_timestamp) . "'), ('Australia/Lindeman', '" . mysql_real_escape_string($current_timestamp) . "'), ('Australia/Lord_Howe', '" . mysql_real_escape_string($current_timestamp) . "'), ('Australia/Melbourne', '" . mysql_real_escape_string($current_timestamp) . "'), ('Australia/North', '" . mysql_real_escape_string($current_timestamp) . "'), ('Australia/NSW', '" . mysql_real_escape_string($current_timestamp) . "'), ('Australia/Perth', '" . mysql_real_escape_string($current_timestamp) . "'), ('Australia/Queensland', '" . mysql_real_escape_string($current_timestamp) . "'), ('Australia/South', '" . mysql_real_escape_string($current_timestamp) . "'), ('Australia/Sydney', '" . mysql_real_escape_string($current_timestamp) . "'), ('Australia/Tasmania', '" . mysql_real_escape_string($current_timestamp) . "'), ('Australia/Victoria', '" . mysql_real_escape_string($current_timestamp) . "'), ('Australia/West', '" . mysql_real_escape_string($current_timestamp) . "'), ('Australia/Yancowinna', '" . mysql_real_escape_string($current_timestamp) . "'), ('Brazil/Acre', '" . mysql_real_escape_string($current_timestamp) . "'), ('Brazil/DeNoronha', '" . mysql_real_escape_string($current_timestamp) . "'), ('Brazil/East', '" . mysql_real_escape_string($current_timestamp) . "'), ('Brazil/West', '" . mysql_real_escape_string($current_timestamp) . "'), ('Canada/Atlantic', '" . mysql_real_escape_string($current_timestamp) . "'), ('Canada/Central', '" . mysql_real_escape_string($current_timestamp) . "'), ('Canada/East-Saskatchewan', '" . mysql_real_escape_string($current_timestamp) . "'), ('Canada/Eastern', '" . mysql_real_escape_string($current_timestamp) . "'), ('Canada/Mountain', '" . mysql_real_escape_string($current_timestamp) . "'), ('Canada/Newfoundland', '" . mysql_real_escape_string($current_timestamp) . "'), ('Canada/Pacific', '" . mysql_real_escape_string($current_timestamp) . "'), ('Canada/Saskatchewan', '" . mysql_real_escape_string($current_timestamp) . "'), ('Canada/Yukon', '" . mysql_real_escape_string($current_timestamp) . "'), ('Chile/Continental', '" . mysql_real_escape_string($current_timestamp) . "'), ('Chile/EasterIsland', '" . mysql_real_escape_string($current_timestamp) . "'), ('Cuba', '" . mysql_real_escape_string($current_timestamp) . "'), ('Egypt', '" . mysql_real_escape_string($current_timestamp) . "'), ('Eire', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Amsterdam', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Andorra', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Athens', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Belfast', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Belgrade', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Berlin', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Bratislava', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Brussels', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Bucharest', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Budapest', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Busingen', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Chisinau', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Copenhagen', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Dublin', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Gibraltar', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Guernsey', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Helsinki', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Isle_of_Man', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Istanbul', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Jersey', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Kaliningrad', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Kiev', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Lisbon', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Ljubljana', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/London', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Luxembourg', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Madrid', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Malta', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Mariehamn', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Minsk', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Monaco', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Moscow', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Nicosia', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Oslo', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Paris', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Podgorica', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Prague', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Riga', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Rome', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Samara', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/San_Marino', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Sarajevo', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Simferopol', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Skopje', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Sofia', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Stockholm', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Tallinn', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Tirane', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Tiraspol', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Uzhgorod', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Vaduz', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Vatican', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Vienna', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Vilnius', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Volgograd', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Warsaw', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Zagreb', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Zaporozhye', '" . mysql_real_escape_string($current_timestamp) . "'), ('Europe/Zurich', '" . mysql_real_escape_string($current_timestamp) . "'), ('Greenwich', '" . mysql_real_escape_string($current_timestamp) . "'), ('Hongkong', '" . mysql_real_escape_string($current_timestamp) . "'), ('Iceland', '" . mysql_real_escape_string($current_timestamp) . "'), ('Indian/Antananarivo', '" . mysql_real_escape_string($current_timestamp) . "'), ('Indian/Chagos', '" . mysql_real_escape_string($current_timestamp) . "'), ('Indian/Christmas', '" . mysql_real_escape_string($current_timestamp) . "'), ('Indian/Cocos', '" . mysql_real_escape_string($current_timestamp) . "'), ('Indian/Comoro', '" . mysql_real_escape_string($current_timestamp) . "'), ('Indian/Kerguelen', '" . mysql_real_escape_string($current_timestamp) . "'), ('Indian/Mahe', '" . mysql_real_escape_string($current_timestamp) . "'), ('Indian/Maldives', '" . mysql_real_escape_string($current_timestamp) . "'), ('Indian/Mauritius', '" . mysql_real_escape_string($current_timestamp) . "'), ('Indian/Mayotte', '" . mysql_real_escape_string($current_timestamp) . "'), ('Indian/Reunion', '" . mysql_real_escape_string($current_timestamp) . "'), ('Iran', '" . mysql_real_escape_string($current_timestamp) . "'), ('Israel', '" . mysql_real_escape_string($current_timestamp) . "'), ('Jamaica', '" . mysql_real_escape_string($current_timestamp) . "'), ('Japan', '" . mysql_real_escape_string($current_timestamp) . "'), ('Kwajalein', '" . mysql_real_escape_string($current_timestamp) . "'), ('Libya', '" . mysql_real_escape_string($current_timestamp) . "'), ('Mexico/BajaNorte', '" . mysql_real_escape_string($current_timestamp) . "'), ('Mexico/BajaSur', '" . mysql_real_escape_string($current_timestamp) . "'), ('Mexico/General', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Apia', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Auckland', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Chatham', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Chuuk', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Easter', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Efate', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Enderbury', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Fakaofo', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Fiji', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Funafuti', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Galapagos', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Gambier', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Guadalcanal', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Guam', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Honolulu', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Johnston', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Kiritimati', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Kosrae', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Kwajalein', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Majuro', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Marquesas', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Midway', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Nauru', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Niue', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Norfolk', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Noumea', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Pago_Pago', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Palau', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Pitcairn', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Pohnpei', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Ponape', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Port_Moresby', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Rarotonga', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Saipan', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Samoa', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Tahiti', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Tarawa', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Tongatapu', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Truk', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Wake', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Wallis', '" . mysql_real_escape_string($current_timestamp) . "'), ('Pacific/Yap', '" . mysql_real_escape_string($current_timestamp) . "'), ('Poland', '" . mysql_real_escape_string($current_timestamp) . "'), ('Portugal', '" . mysql_real_escape_string($current_timestamp) . "'), ('Singapore', '" . mysql_real_escape_string($current_timestamp) . "'), ('Turkey', '" . mysql_real_escape_string($current_timestamp) . "'), ('US/Alaska', '" . mysql_real_escape_string($current_timestamp) . "'), ('US/Aleutian', '" . mysql_real_escape_string($current_timestamp) . "'), ('US/Arizona', '" . mysql_real_escape_string($current_timestamp) . "'), ('US/Central', '" . mysql_real_escape_string($current_timestamp) . "'), ('US/East-Indiana', '" . mysql_real_escape_string($current_timestamp) . "'), ('US/Eastern', '" . mysql_real_escape_string($current_timestamp) . "'), ('US/Hawaii', '" . mysql_real_escape_string($current_timestamp) . "'), ('US/Indiana-Starke', '" . mysql_real_escape_string($current_timestamp) . "'), ('US/Michigan', '" . mysql_real_escape_string($current_timestamp) . "'), ('US/Mountain', '" . mysql_real_escape_string($current_timestamp) . "'), ('US/Pacific', '" . mysql_real_escape_string($current_timestamp) . "'), ('US/Pacific-New', '" . mysql_real_escape_string($current_timestamp) . "'), ('US/Samoa', '" . mysql_real_escape_string($current_timestamp) . "'), ('Zulu', '" . mysql_real_escape_string($current_timestamp) . "');";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0023',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0023;

	}

	// upgrade database from 2.0023 to 2.0024
	if ($current_db_version == 2.0023) {

		$sql = "ALTER TABLE `settings` 
					CHANGE `timezone` `timezone` VARCHAR(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'Canada/Pacific'";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0024',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
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
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "INSERT INTO `hosting` 
					(`name`, `default_host`, `insert_time`) VALUES 
					('[no hosting]', 1, '" . mysql_real_escape_string($current_timestamp) . "');";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `domains`  
					ADD `hosting_id` int(10) NOT NULL default '1' AFTER `ip_id`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "SELECT id
				FROM hosting
				WHERE name = '[no hosting]'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		while ($row = mysql_fetch_object($result)) {
			$temp_hosting_id = $row->id;
		}
		
		$sql = "UPDATE domains
				SET hosting_id = '" . mysql_real_escape_string($temp_hosting_id) . "',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
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
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0025;

	}

	// upgrade database from 2.0025 to 2.0026
	if ($current_db_version == 2.0025) {

		$sql = "ALTER TABLE `user_settings` 
					ADD `display_domain_host` INT(1) NOT NULL DEFAULT '0' AFTER `display_domain_dns`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0026',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0026;

	}

	// upgrade database from 2.0026 to 2.0027
	if ($current_db_version == 2.0026) {

		$sql = "ALTER TABLE `registrar_accounts`  
					ADD `password` VARCHAR(100) NOT NULL AFTER `username`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0027',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0027;

	}

	// upgrade database from 2.0027 to 2.0028
	if ($current_db_version == 2.0027) {

		$sql = "ALTER TABLE `ssl_accounts`  
					ADD `password` VARCHAR(100) NOT NULL AFTER `username`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0028',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0028;

	}

	// upgrade database from 2.0028 to 2.0029
	if ($current_db_version == 2.0028) {

		$sql = "ALTER TABLE `dns`  
					ADD `ip1` VARCHAR(255) NOT NULL AFTER `dns10`,  
					ADD `ip2` VARCHAR(255) NOT NULL AFTER `ip1`,  
					ADD `ip3` VARCHAR(255) NOT NULL AFTER `ip2`,  
					ADD `ip4` VARCHAR(255) NOT NULL AFTER `ip3`,  
					ADD `ip5` VARCHAR(255) NOT NULL AFTER `ip4`,  
					ADD `ip6` VARCHAR(255) NOT NULL AFTER `ip5`,  
					ADD `ip7` VARCHAR(255) NOT NULL AFTER `ip6`,  
					ADD `ip8` VARCHAR(255) NOT NULL AFTER `ip7`,  
					ADD `ip9` VARCHAR(255) NOT NULL AFTER `ip8`,  
					ADD `ip10` VARCHAR(255) NOT NULL AFTER `ip9`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "ALTER TABLE `settings`  
					ADD `expiration_email_days` INT(3) NOT NULL DEFAULT '60' AFTER `timezone`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0029',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0029;

	}

	// upgrade database from 2.0029 to 2.003
	if ($current_db_version == 2.0029) {

		$sql = "ALTER TABLE `domains`  
					ADD `notes_fixed_temp` INT(1) NOT NULL DEFAULT '0' AFTER `notes`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "SELECT id, status, status_notes, notes
				FROM domains";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		while ($row = mysql_fetch_object($result)) {

			if ($row->status != "" || $row->status_notes != "" || $row->notes != "") {

				$full_status = "";
				$full_status_notes = "";
				$new_notes = "";
				
				if ($row->status != "") {
		
					$full_status .= "--------------------\r\n";
					$full_status .= "OLD STATUS - INSERTED " . $current_timestamp . "\r\n";
					$full_status .= "The Status field was removed because it was redundant.\r\n";
					$full_status .= "--------------------\r\n";
					$full_status .= $row->status . "\r\n";
					$full_status .= "--------------------";
		
				} else {
					
					$full_status = "";
					
				}
		
				if ($row->status_notes != "") {
		
					$full_status_notes .= "--------------------\r\n";
					$full_status_notes .= "OLD STATUS NOTES - INSERTED " . $current_timestamp . "\r\n";
					$full_status_notes .= "The Status Notes field was removed because it was redundant.\r\n";
					$full_status_notes .= "--------------------\r\n";
					$full_status_notes .= $row->status_notes . "\r\n";
					$full_status_notes .= "--------------------";
		
				} else {
					
					$full_status_notes = "";
					
				}
				
				if ($row->notes != "") {
					
					if ($full_status != "" && $full_status_notes != "") {
						
						$new_notes = $full_status . "\r\n\r\n" . $full_status_notes . "\r\n\r\n" . $row->notes;
	
					} elseif ($full_status != "" && $full_status_notes == "") {
						
						$new_notes = $full_status . "\r\n\r\n" . $row->notes;
	
					} elseif ($full_status == "" && $full_status_notes != "") {
						
						$new_notes = $full_status_notes . "\r\n\r\n" . $row->notes;
	
					} elseif ($full_status == "" && $full_status_notes == "") {
						
						$new_notes = $row->notes;
	
					}
					
				} elseif ($row->notes == "") {
	
					if ($full_status != "" && $full_status_notes != "") {
						
						$new_notes = $full_status . "\r\n\r\n" . $full_status_notes;
	
					} elseif ($full_status != "" && $full_status_notes == "") {
						
						$new_notes = $full_status;
	
					} elseif ($full_status == "" && $full_status_notes != "") {
						
						$new_notes = $full_status_notes;
	
					}
	
				}
				
				$sql_update = "UPDATE domains
							   SET notes = '" . trim(mysql_real_escape_string($new_notes)) . "',
							   	   notes_fixed_temp = '1',
								   update_time = '" . mysql_real_escape_string($current_timestamp) . "'
							   WHERE id = '" . mysql_real_escape_string($row->id) . "'";
				$result_update = mysql_query($sql_update,$connection) or die(mysql_error());

			} else {

				$sql_update = "UPDATE domains
							   SET notes_fixed_temp = '1',
								   update_time = '" . mysql_real_escape_string($current_timestamp) . "'
							   WHERE id = '" . mysql_real_escape_string($row->id) . "'";
				$result_update = mysql_query($sql_update,$connection) or die(mysql_error());
			}

		}
		
		$sql = "SELECT *
				FROM domains
				WHERE notes_fixed_temp = '0'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		if (mysql_num_rows($result) > 0) {
			
			echo "DATABASE UPDATE v2.003 FAILED: PLEASE CONTACT YOUR " . strtoupper($software_title) . " ADMINISTRATOR IMMEDIATELY";
			exit;
			
		} else {

			$sql = "ALTER TABLE `domains` 
						DROP `status`, 
						DROP `status_notes`,
						DROP `notes_fixed_temp`";
			$result = mysql_query($sql,$connection) or die(mysql_error());

		}
		
		$sql = "UPDATE settings
				SET db_version = '2.003',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.003;

	}

	// upgrade database from 2.003 to 2.0031
	if ($current_db_version == 2.003) {

		$sql = "ALTER TABLE `categories` 
					DROP `active`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `currencies` 
					DROP `active`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `dns` 
					DROP `active`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `hosting` 
					DROP `active`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `ip_addresses` 
					DROP `active`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `owners` 
					DROP `active`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `registrars` 
					DROP `active`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `registrar_accounts` 
					DROP `active`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `segments` 
					DROP `active`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `ssl_accounts` 
					DROP `active`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `ssl_cert_types` 
					DROP `active`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `ssl_providers` 
					DROP `active`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `ssl_providers` 
					DROP `active`;";
		$result = mysql_query($sql,$connection);

		$sql = "UPDATE settings
				SET db_version = '2.0031',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0031;

	}

	// upgrade database from 2.0031 to 2.0032
	if ($current_db_version == 2.0031) {

		$sql = "ALTER TABLE `fees` 
				ADD `transfer_fee` FLOAT NOT NULL AFTER `renewal_fee`";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `ssl_fees` 
				ADD `transfer_fee` FLOAT NOT NULL AFTER `renewal_fee`";
		$result = mysql_query($sql,$connection);

		$sql = "UPDATE settings
				SET db_version = '2.0032',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0032;

	}

	// upgrade database from 2.0032 to 2.0033
	if ($current_db_version == 2.0032) {

		$sql = "ALTER TABLE `ssl_fees` 
				DROP `transfer_fee`;";
		$result = mysql_query($sql,$connection);

		$sql = "UPDATE settings
				SET db_version = '2.0033',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0033;

	}

	// upgrade database from 2.0033 to 2.0034
	if ($current_db_version == 2.0033) {

		$sql = "ALTER TABLE `domains` 
				CHANGE `owner_id` `owner_id` INT(10) NOT NULL DEFAULT '1'";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `domains` 
				CHANGE `registrar_id` `registrar_id` INT(10) NOT NULL DEFAULT '1'";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `domains` 
				CHANGE `account_id` `account_id` INT(10) NOT NULL DEFAULT '1'";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `domains` 
				CHANGE `dns_id` `dns_id` INT(10) NOT NULL DEFAULT '1'";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `fees` 
				CHANGE `registrar_id` `registrar_id` INT(10) NOT NULL";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `registrar_accounts` 
				CHANGE `owner_id` `owner_id` INT(10) NOT NULL";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `ssl_accounts` 
				CHANGE `owner_id` `owner_id` INT(10) NOT NULL";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `ssl_certs` 
				CHANGE `owner_id` `owner_id` INT(10) NOT NULL";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `ssl_certs` 
				CHANGE `ssl_provider_id` `ssl_provider_id` INT(10) NOT NULL";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `ssl_certs` 
				CHANGE `account_id` `account_id` INT(10) NOT NULL";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `ssl_fees` 
				CHANGE `ssl_provider_id` `ssl_provider_id` INT(10) NOT NULL";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `ssl_fees` 
				CHANGE `type_id` `type_id` INT(10) NOT NULL";
		$result = mysql_query($sql,$connection);

		$sql = "UPDATE settings
				SET db_version = '2.0034',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0034;

	}

	// upgrade database from 2.0034 to 2.0035
	if ($current_db_version == 2.0034) {

		$sql = "ALTER DATABASE " . $dbname . " 
				CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE categories CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE currencies CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE dns CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE domains CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE fees CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE hosting CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE ip_addresses CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE owners CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE registrars CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE registrar_accounts CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE segments CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE segment_data CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE settings CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE ssl_accounts CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE ssl_certs CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE ssl_cert_types CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE ssl_fees CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE ssl_providers CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE timezones CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE users CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE user_settings CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE categories CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE currencies CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE dns CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE domains CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE fees CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE hosting CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE ip_addresses CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE owners CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE registrars CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE registrar_accounts CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE segments CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE segment_data CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE settings CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE ssl_accounts CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE ssl_certs CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE ssl_cert_types CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE ssl_fees CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE ssl_providers CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE timezones CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE users CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE user_settings CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "UPDATE settings
				SET db_version = '2.0035',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0035;

	}

	// upgrade database from 2.0035 to 2.0036
	if ($current_db_version == 2.0035) {

		$sql = "DROP TABLE `currency_data`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER DATABASE " . $dbname . " 
				CHARACTER SET utf8 
				DEFAULT CHARACTER SET utf8 
				COLLATE utf8_unicode_ci
				DEFAULT COLLATE utf8_unicode_ci;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `currencies`  
				ADD `symbol` VARCHAR(4) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `conversion`,  
				ADD `symbol_order` INT(1) NOT NULL DEFAULT '0' AFTER `symbol`,  
				ADD `symbol_space` INT(1) NOT NULL DEFAULT '0' AFTER `symbol_order`,
				ADD `newly_inserted` INT(1) NOT NULL DEFAULT '1' AFTER `symbol_space`";
		$result = mysql_query($sql,$connection);

		$sql = "UPDATE currencies
				SET newly_inserted = '0',
					update_time = '" . $current_timestamp . "'";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `settings`  
				ADD `default_currency` VARCHAR(3) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `email_address`";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `user_settings`  
				ADD `default_currency` VARCHAR(3) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `user_id`";
		$result = mysql_query($sql,$connection);

		$sql = "UPDATE settings
				SET default_currency = '" . $_SESSION['default_currency'] . "',
					update_time = '" . $current_timestamp . "'";
		$result = mysql_query($sql,$connection);

		$sql = "UPDATE user_settings
				SET default_currency = '" . $_SESSION['default_currency'] . "',
					update_time = '" . $current_timestamp . "'";
		$result = mysql_query($sql,$connection);

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
		
		$sql = "SELECT id, currency
				FROM currencies
				WHERE newly_inserted = '0'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		while ($row = mysql_fetch_object($result)) {
			
			$sql_find_new = "SELECT id, symbol
							 FROM currencies
							 WHERE newly_inserted = '1'
							   AND currency = '" . $row->currency . "'";
			$result_find_new = mysql_query($sql_find_new,$connection);
			$total_results = mysql_num_rows($result_find_new);
			
			while ($row_find_new = mysql_fetch_object($result_find_new)) {
			
				if ($total_results > 0) {
					
					$sql_update_old = "UPDATE currencies
									   SET symbol = '" . $row_find_new->symbol . "'
									   WHERE id = '" . $row->id . "'";
					$result_update_old = mysql_query($sql_update_old,$connection);

					$sql_delete_new = "DELETE FROM currencies
									   WHERE id = '" . $row_find_new->id . "'";
					$result_delete_new = mysql_query($sql_delete_new,$connection);

				}
				
			}

		}

		$sql = "ALTER TABLE `currencies` 
				DROP `newly_inserted`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE settings
				SET db_version = '2.0036',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0036;

	}

	// upgrade database from 2.0036 to 2.0037
	if ($current_db_version == 2.0036) {
		
		$sql = "SELECT currency
				FROM currencies
				WHERE default_currency = '1'";
		$result = mysql_query($sql,$connection);
		while ($row = mysql_fetch_object($result)) { $temp_currency = $row->currency; }
		
		$sql = "UPDATE settings
				SET default_currency = '" . $temp_currency . "'";
		$result = mysql_query($sql,$connection);
		
		$_SESSION['default_currency'] = $temp_currency;

		$sql = "SELECT name, symbol, symbol_order, symbol_space
				FROM currencies
				WHERE currency = '" . $_SESSION['default_currency'] . "'";
		$result = mysql_query($sql,$connection);
	
		while ($row = mysql_fetch_object($result)) {
			$_SESSION['default_currency_name'] = $row->name;
			$_SESSION['default_currency_symbol'] = $row->symbol;
			$_SESSION['default_currency_symbol_order'] = $row->symbol_order;
			$_SESSION['default_currency_symbol_space'] = $row->symbol_space;
		}

		$sql = "ALTER TABLE `currencies` 
				DROP `default_currency`;";
		$result = mysql_query($sql,$connection);

		$sql = "ALTER TABLE `user_settings` 
				DROP `default_currency`;";
		$result = mysql_query($sql,$connection);
		
		$sql = "UPDATE settings
				SET db_version = '2.0037',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0037;

	}

	// upgrade database from 2.0037 to 2.0038
	if ($current_db_version == 2.0037) {

		$sql = "ALTER TABLE `user_settings`  
				ADD `default_currency` varchar(3) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL after user_id";
		$result = mysql_query($sql,$connection);
		
		$sql = "SELECT default_currency
				FROM settings";
		$result = mysql_query($sql,$connection);
		while ($row = mysql_fetch_object($result)) {
			$temp_default_currency = $row->default_currency;
			$_SESSION['default_currency'] = $row->default_currency;
		}
		
		$sql = "SELECT name, symbol, symbol_order, symbol_space
				FROM currencies
				WHERE currency = '" . $_SESSION['default_currency'] . "'";
		$result = mysql_query($sql,$connection);
		
		while ($row = mysql_fetch_object($result)) {

			$_SESSION['default_currency_name'] = $row->name; 
			$_SESSION['default_currency_symbol'] = $row->symbol; 
			$_SESSION['default_currency_symbol_order'] = $row->symbol_order; 
			$_SESSION['default_currency_symbol_space'] = $row->symbol_space; 

		}

		$sql = "UPDATE user_settings
				SET default_currency = '" . $temp_default_currency . "'";
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
		
		$sql = "SELECT id
				FROM users";
		$result = mysql_query($sql,$connection);
			
		while ($row = mysql_fetch_object($result)) {
			
			$sql_conversion = "SELECT id, conversion
							   FROM currencies
							   WHERE conversion != '0'";
			$result_conversion = mysql_query($sql_conversion,$connection);
			
			while ($row_conversion = mysql_fetch_object($result_conversion)) {
				
				$sql_insert = "INSERT INTO currency_conversions
							   (currency_id, user_id, conversion, insert_time, update_time) VALUES 
							   ('" . $row_conversion->id . "', '" . $row->id . "', '" . $row_conversion->conversion . "', '" . $current_timestamp . "', '" . $current_timestamp . "')";
				$result_insert = mysql_query($sql_insert,$connection);
				
			}
			
		}

		$sql = "ALTER TABLE `currencies` 
				DROP `conversion`;";
		$result = mysql_query($sql,$connection);

		$sql = "UPDATE settings
				SET db_version = '2.0038',
					update_time = '" . mysql_real_escape_string($current_timestamp) . "'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 2.0038;

	}

	if ($direct == "1") {
	
		$_SESSION['result_message'] .= "Your Database Has Been Updated<BR>";
			
		header("Location: " . $_SERVER['HTTP_REFERER']);
		exit;
		
	} else {
			
		$_SESSION['result_message'] .= "Your Database Has Been Updated<BR>";
		
	}

} else {

	if ($direct == "1") {
	
		$_SESSION['result_message'] .= "Your Database is already up-to-date<BR>";
		
		header("Location: " . $_SERVER['HTTP_REFERER']);
		exit;
	
	} else {
		
		$_SESSION['result_message'] .= "Your Database is already up-to-date<BR>";
	
	}
	
}
?>