<?php
/**
 * /_includes/system/update-database.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (C) 2010-2015 Greg Chetcuti <greg@chetcuti.com>
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
$sql = "SELECT db_version
		FROM settings";
$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

while ($row = mysqli_fetch_object($result)) {
	$current_db_version = $row->db_version;
}

if ($current_db_version < $most_recent_db_version) {

	// upgrade database from 1.1 to 1.2
	if ($current_db_version == 1.1) {

		$sql = "ALTER TABLE `ssl_certs`  
				ADD `ip` VARCHAR(50) NOT NULL AFTER `name`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$sql = "UPDATE settings
				SET db_version = '1.2', 
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
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
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$sql = "UPDATE settings
				SET db_version = '1.3', 
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 1.3;
		
	}

	// upgrade database from 1.3 to 1.4
	if ($current_db_version == 1.3) {

		$sql = "ALTER TABLE `ip_addresses` 
				ADD `notes` longtext NOT NULL AFTER `ip`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$sql = "UPDATE settings
				SET db_version = '1.4', 
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 1.4;
		
	}

	// upgrade database from 1.4 to 1.5
	if ($current_db_version == 1.4) {

		$sql = "ALTER TABLE `domains`  
				ADD `ip_id` int(10) NOT NULL default '0' AFTER `dns_id`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$sql = "UPDATE settings
				SET db_version = '1.5', 
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 1.5;
		
	}

	// upgrade database from 1.5 to 1.6
	if ($current_db_version == 1.5) {

		$sql = "ALTER TABLE `domains` 
				CHANGE `ip_id` `ip_id` INT(10) NOT NULL DEFAULT '1'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE `domains` 
				SET ip_id = '1',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "TRUNCATE `ip_addresses`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "INSERT INTO `ip_addresses` 
				(`id`, `name`, `ip`, `insert_time`) VALUES 
				('1', '[no ip address]', '-', '" . mysqli_real_escape_string($connection, $time->time()) . "')";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$sql = "UPDATE settings
				SET db_version = '1.6', 
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 1.6;
		
	}

	// upgrade database from 1.6 to 1.7
	if ($current_db_version == 1.6) {

		$sql = "ALTER TABLE `ssl_certs` 
				DROP `ip`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$sql = "UPDATE settings
				SET db_version = '1.7', 
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 1.7;
		
	}

	// upgrade database from 1.7 to 1.8
	if ($current_db_version == 1.7) {

		$sql = "ALTER TABLE `ip_addresses`  
				ADD `test_data` int(1) NOT NULL default '0' AFTER `notes`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$sql = "UPDATE settings
				SET db_version = '1.8', 
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 1.8;
		
	}

	// upgrade database from 1.8 to 1.9
	if ($current_db_version == 1.8) {

		$sql = "ALTER TABLE `settings`  
				ADD `email_address` VARCHAR(255) NOT NULL AFTER `db_version`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$sql = "UPDATE settings
				SET db_version = '1.9', 
					email_address = 'greg@chetcuti.com',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 1.9;
		
	}

	// upgrade database from 1.9 to 1.91
	if ($current_db_version == 1.9) {

		$sql = "ALTER TABLE `ip_addresses` 
				ADD `rdns` VARCHAR(255) NOT NULL DEFAULT '-' AFTER `ip`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$sql = "UPDATE settings
				SET db_version = '1.91',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 1.91;
		
	}

	// upgrade database from 1.91 to 1.92
	if ($current_db_version == 1.91) {

		$sql = "ALTER TABLE `settings` 
				ADD `type` VARCHAR(50) NOT NULL AFTER `id`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings 
				SET type =  'system',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$sql = "UPDATE settings
				SET db_version = '1.92',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'
				WHERE type = 'system'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 1.92;
		
	}

	// upgrade database from 1.92 to 1.93
	if ($current_db_version == 1.92) {

		$sql = "ALTER TABLE `settings` 
				DROP `type`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '1.93',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 1.93;
		
	}

	// upgrade database from 1.93 to 1.94
	if ($current_db_version == 1.93) {

		$sql = "ALTER TABLE `settings` 
				ADD `number_of_domains` INT(5) NOT NULL DEFAULT '50' AFTER `email_address`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `settings` 
				ADD `number_of_ssl_certs` INT(5) NOT NULL DEFAULT '50' AFTER `number_of_domains`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '1.94',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 1.94;
		
	}

	// upgrade database from 1.94 to 1.95
	if ($current_db_version == 1.94) {

		$sql = "ALTER TABLE `currencies` 
				DROP `default_currency`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `settings` 
				ADD `default_currency` VARCHAR(5) NOT NULL DEFAULT 'CAD' AFTER `email_address`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '1.95',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 1.95;
		
	}

	// upgrade database from 1.95 to 1.96
	if ($current_db_version == 1.95) {

		$sql = "ALTER TABLE `currencies` 
				DROP `test_data`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '1.96',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
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
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "INSERT INTO owners 
					(id, name, notes, active, test_data, insert_time, update_time) 
					SELECT id, name, notes, active, test_data, insert_time, update_time FROM companies ORDER BY id;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "DROP TABLE `companies`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `domains` CHANGE `company_id` `owner_id` INT(5) NOT NULL;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `registrar_accounts` CHANGE `company_id` `owner_id` INT(5) NOT NULL;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `ssl_accounts` CHANGE `company_id` `owner_id` INT(5) NOT NULL;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `ssl_certs` CHANGE `company_id` `owner_id` INT(5) NOT NULL;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '1.97',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 1.97;
		
	}

	// upgrade database from 1.97 to 1.98
	if ($current_db_version == 1.97) {

		$sql = "INSERT INTO `categories` 
					(`name`, `owner`, `insert_time`) VALUES 
					('[no category]', '[no stakeholder]', '" . mysqli_real_escape_string($connection, $time->time()) . "');";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$sql = "SELECT id
				FROM categories
				WHERE default_category = '1';";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		if (mysqli_num_rows($result) == 0) {
			$sql_update = "UPDATE categories
						   SET default_category = '1',
						   	   update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'
						   WHERE name = '[no category]'";
			$result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);
		}

		$sql = "ALTER TABLE `dns` 
					ADD `default_dns` INT(1) NOT NULL DEFAULT '0' AFTER `number_of_servers`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "INSERT INTO `dns` 
					(`name`, `dns1`, `dns2`, `number_of_servers`, `insert_time`) VALUES 
					('[no dns]', 'ns1.no-dns.com', 'ns2.no-dns.com', '2', '" . mysqli_real_escape_string($connection, $time->time()) . "');";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "SELECT id
				FROM dns
				WHERE default_dns = '1';";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		if (mysqli_num_rows($result) == 0) {
			$sql_update = "UPDATE dns
						   SET default_dns = '1',
						   	   update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'
						   WHERE name = '[no dns]'";
			$result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);
		}

		$sql = "ALTER TABLE `owners`  
					ADD `default_owner` INT(1) NOT NULL DEFAULT '0' AFTER `notes`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "INSERT INTO `owners` 
					(`name`, `insert_time`) VALUES 
					('[no owner]', '" . mysqli_real_escape_string($connection, $time->time()) . "');";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "SELECT id
				FROM owners
				WHERE default_owner = '1';";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		if (mysqli_num_rows($result) == 0) {
			$sql_update = "UPDATE owners
						   SET default_owner = '1',
						   	   update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'
						   WHERE name = '[no owner]'";
			$result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);
		}

		$sql = "ALTER TABLE `ip_addresses` 
					ADD `default_ip_address` INT(1) NOT NULL DEFAULT '0' AFTER `notes`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "SELECT id
				FROM ip_addresses
				WHERE default_ip_address = '1';";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		if (mysqli_num_rows($result) == 0) {
			$sql_update = "UPDATE ip_addresses
						   SET default_ip_address = '1',
						   	   update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'
						   WHERE name = '[no ip address]'";
			$result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);
		}

		$sql = "UPDATE settings
				SET db_version = '1.98',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 1.98;
		
	}

	// upgrade database from 1.98 to 1.99
	if ($current_db_version == 1.98) {

		$sql = "ALTER TABLE `categories` 
					CHANGE `owner` `stakeholder` VARCHAR(255) NOT NULL;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE `categories`
					SET `stakeholder` = '[no stakeholder]',
						`update_time` = '" . mysqli_real_escape_string($connection, $time->time()) . "'
				WHERE `stakeholder` = '[no category owner]';";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '1.99',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 1.99;
		
	}

	// upgrade database from 1.99 to 2.0001
	if ($current_db_version == 1.99) {

		$sql = "ALTER TABLE `currencies` 
					ADD `default_currency` INT(1) NOT NULL DEFAULT '0' AFTER `notes`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "SELECT default_currency
				FROM settings";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		while ($row = mysqli_fetch_object($result)) {
			$default_currency = $row->default_currency;
		}
		
		$sql = "UPDATE currencies
				SET default_currency = '0',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE currencies
				SET default_currency = '1',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'
				WHERE currency = '" . mysqli_real_escape_string($connection, $default_currency) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `settings` 
				DROP `default_currency`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0001',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0001;
		
	}

	// upgrade database from 2.0001 to 2.0002
	if ($current_db_version == 2.0001) {

		$sql = "ALTER TABLE `ssl_cert_functions` 
					ADD `default_function` INT(1) NOT NULL DEFAULT '0' AFTER `notes`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `ssl_cert_types` 
					ADD `default_type` INT(1) NOT NULL DEFAULT '0' AFTER `notes`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE ssl_cert_functions
				SET default_function = '1',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'
				WHERE function = 'Web Server SSL/TLS Certificate'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE ssl_cert_types
				SET default_type = '1',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'
				WHERE type = 'Wildcard'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0002',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0002;

	}

	// upgrade database from 2.0002 to 2.0003
	if ($current_db_version == 2.0002) {

		$sql = "DROP TABLE `ssl_cert_types`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `ssl_certs` 
				DROP `type_id`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `ssl_fees` 
				DROP `type_id`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0003',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
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
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "INSERT INTO ssl_cert_types 
					(id, type, notes, default_type, active, insert_time, update_time) 
					SELECT id, function, notes, default_function, active, insert_time, update_time FROM ssl_cert_functions ORDER BY id;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "DROP TABLE `ssl_cert_functions`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `ssl_certs` 
					CHANGE `function_id` `type_id` INT(10) NOT NULL";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `ssl_fees` 
					CHANGE `function_id` `type_id` INT(5) NOT NULL";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0004',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0004;

	}

	// upgrade database from 2.0004 to 2.0005
	if ($current_db_version == 2.0004) {

		$sql = "ALTER TABLE `ssl_cert_types`  
					ADD `test_data` int(1) NOT NULL default '0' AFTER `active`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0005',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0005;

	}

	// upgrade database from 2.0005 to 2.0006
	if ($current_db_version == 2.0005) {

		$sql = "ALTER TABLE `ip_addresses` 
					ADD `active` INT(1) NOT NULL DEFAULT '1' AFTER `default_ip_address`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `domains` 
					CHANGE `active` `active` INT(2) NOT NULL DEFAULT '1'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0006',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0006;

	}

	// upgrade database from 2.0006 to 2.0007
	if ($current_db_version == 2.0006) {

		$sql = "ALTER TABLE `registrars` 
					ADD `default_registrar` INT(1) NOT NULL DEFAULT '0' AFTER `notes`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `registrar_accounts` 
					ADD `default_account` INT(1) NOT NULL DEFAULT '0' AFTER `reseller`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `ssl_providers` 
					ADD `default_provider` INT(1) NOT NULL DEFAULT '0' AFTER `notes`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `ssl_accounts` 
					ADD `default_account` INT(1) NOT NULL DEFAULT '0' AFTER `reseller`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0007',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0007;

	}

	// upgrade database from 2.0007 to 2.0008
	if ($current_db_version == 2.0007) {

		$sql = "ALTER TABLE `owners` 
					CHANGE `id` `id` INT(10) NOT NULL AUTO_INCREMENT";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `registrars` 
					CHANGE `id` `id` INT(10) NOT NULL AUTO_INCREMENT";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `ssl_providers` 
					CHANGE `id` `id` INT(10) NOT NULL AUTO_INCREMENT";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0008',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0008;

	}

	// upgrade database from 2.0008 to 2.0009
	if ($current_db_version == 2.0008) {

		$sql = "ALTER TABLE `currencies`  
				ADD `test_data` int(1) NOT NULL default '0' AFTER `active`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0009',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
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
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$sql = "SELECT id
				FROM users";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		while ($row = mysqli_fetch_object($result)) {
			$sql_temp = "INSERT INTO user_settings
						 (user_id, insert_time) VALUES 
						 ('" . mysqli_real_escape_string($connection, $row->id) . "', '" . mysqli_real_escape_string($connection, $time->time()) . "');";
			$result_temp = mysqli_query($connection, $sql_temp) or $error->outputOldSqlError($connection);
		}

		$sql = "UPDATE settings
				SET db_version = '2.001',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.001;

	}

	// upgrade database from 2.0010 to 2.0011
	if ($current_db_version == 2.001) {

		$sql = "ALTER TABLE `settings` 
					DROP `number_of_domains`, 
					DROP `number_of_ssl_certs`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0011',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0011;

	}

	// upgrade database from 2.0011 to 2.0012
	if ($current_db_version == 2.0011) {

		$sql = "ALTER TABLE `user_settings` 
				CHANGE `display_domain_account` `display_domain_account` INT(1) NOT NULL DEFAULT '1'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0012',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0012;

	}

	// upgrade database from 2.0012 to 2.0013
	if ($current_db_version == 2.0012) {

		$sql = "ALTER TABLE `categories` 
				DROP `test_data`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `currencies` 
				DROP `test_data`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `dns` 
				DROP `test_data`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `domains` 
				DROP `test_data`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `fees` 
				DROP `test_data`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `ip_addresses` 
				DROP `test_data`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `owners` 
				DROP `test_data`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `registrars` 
				DROP `test_data`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `registrar_accounts` 
				DROP `test_data`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `segments`
				DROP `test_data`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `ssl_accounts` 
				DROP `test_data`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `ssl_certs` 
				DROP `test_data`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `ssl_cert_types` 
				DROP `test_data`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `ssl_fees` 
				DROP `test_data`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `ssl_providers` 
				DROP `test_data`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0013',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
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
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0014',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0014;

	}

	// upgrade database from 2.0014 to 2.0015
	if ($current_db_version == 2.0014) {

		$sql = "ALTER TABLE `user_settings` 
					ADD `display_domain_fee` INT(1) NOT NULL DEFAULT '0' AFTER `display_domain_tld`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `user_settings` 
					ADD `display_ssl_fee` INT(1) NOT NULL DEFAULT '0' AFTER `display_ssl_expiry_date`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0015',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0015;

	}

	// upgrade database from 2.0015 to 2.0016
	if ($current_db_version == 2.0015) {

		$sql = "ALTER TABLE `segment_data` 
					ADD `active` INT(1) NOT NULL DEFAULT '0' AFTER `domain`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `segment_data` 
					ADD `inactive` INT(1) NOT NULL DEFAULT '0' AFTER `active`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `segment_data` 
					ADD `missing` INT(1) NOT NULL DEFAULT '0' AFTER `inactive`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0016',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0016;

	}

	// upgrade database from 2.0016 to 2.0017
	if ($current_db_version == 2.0016) {

		$sql = "ALTER TABLE `segment_data` 
					ADD `filtered` INT(1) NOT NULL DEFAULT '0' AFTER `missing`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0017',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0017;

	}

	// upgrade database from 2.0017 to 2.0018
	if ($current_db_version == 2.0017) {

		$sql = "ALTER TABLE `ssl_certs` 
					CHANGE `domain_id` `domain_id` INT(10) NOT NULL DEFAULT '0'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0018',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0018;

	}

	// upgrade database from 2.0018 to 2.0019
	if ($current_db_version == 2.0018) {

		$sql = "ALTER TABLE `ssl_certs` 
					CHANGE `domain_id` `domain_id` INT(10) NOT NULL";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0019',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0019;

	}

	// upgrade database from 2.0019 to 2.0020
	if ($current_db_version == 2.0019) {

		$sql = "ALTER TABLE `user_settings`  
					ADD `expiration_emails` INT(1) NOT NULL DEFAULT '1' AFTER `user_id`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0020',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0020;

	}

	// upgrade database from 2.0020 to 2.0021
	if ($current_db_version == 2.002) {

		$sql = "ALTER TABLE `settings` 
					ADD `full_url` VARCHAR(100) NOT NULL DEFAULT 'http://' AFTER `id`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$full_url = substr($_SERVER["HTTP_REFERER"], 0, -1);
		
		$sql = "UPDATE settings
				SET full_url = '" . $full_url . "'";
		$result = mysqli_query($connection, $sql);

		$sql = "UPDATE settings
				SET db_version = '2.0021',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0021;

	}

	// upgrade database from 2.0021 to 2.0022
	if ($current_db_version == 2.0021) {

		$sql = "ALTER TABLE `settings`  
					ADD `timezone` VARCHAR(50) NOT NULL DEFAULT 'Canada/Pacific' AFTER `email_address`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0022',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
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
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "INSERT INTO `timezones` 
				(`timezone`, `insert_time`) VALUES 
				('Africa/Abidjan', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Accra', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Addis_Ababa', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Algiers', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Asmara', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Asmera', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Bamako', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Bangui', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Banjul', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Bissau', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Blantyre', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Brazzaville', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Bujumbura', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Cairo', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Casablanca', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Ceuta', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Conakry', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Dakar', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Dar_es_Salaam', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Djibouti', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Douala', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/El_Aaiun', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Freetown', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Gaborone', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Harare', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Johannesburg', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Juba', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Kampala', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Khartoum', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Kigali', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Kinshasa', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Lagos', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Libreville', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Lome', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Luanda', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Lubumbashi', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Lusaka', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Malabo', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Maputo', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Maseru', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Mbabane', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Mogadishu', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Monrovia', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Nairobi', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Ndjamena', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Niamey', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Nouakchott', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Ouagadougou', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Porto-Novo', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Sao_Tome', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Timbuktu', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Tripoli', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Tunis', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Africa/Windhoek', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Adak', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Anchorage', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Anguilla', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Antigua', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Araguaina', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Argentina/Buenos_Aires', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Argentina/Catamarca', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Argentina/ComodRivadavia', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Argentina/Cordoba', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Argentina/Jujuy', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Argentina/La_Rioja', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Argentina/Mendoza', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Argentina/Rio_Gallegos', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Argentina/Salta', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Argentina/San_Juan', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Argentina/San_Luis', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Argentina/Tucuman', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Argentina/Ushuaia', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Aruba', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Asuncion', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Atikokan', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Atka', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Bahia', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Bahia_Banderas', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Barbados', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Belem', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Belize', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Blanc-Sablon', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Boa_Vista', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Bogota', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Boise', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Buenos_Aires', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Cambridge_Bay', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Campo_Grande', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Cancun', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Caracas', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Catamarca', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Cayenne', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Cayman', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Chicago', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Chihuahua', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Coral_Harbour', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Cordoba', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Costa_Rica', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Creston', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Cuiaba', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Curacao', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Danmarkshavn', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Dawson', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Dawson_Creek', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Denver', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Detroit', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Dominica', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Edmonton', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Eirunepe', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/El_Salvador', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Ensenada', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Fort_Wayne', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Fortaleza', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Glace_Bay', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Godthab', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Goose_Bay', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Grand_Turk', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Grenada', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Guadeloupe', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Guatemala', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Guayaquil', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Guyana', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Halifax', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Havana', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Hermosillo', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Indiana/Indianapolis', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Indiana/Knox', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Indiana/Marengo', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Indiana/Petersburg', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Indiana/Tell_City', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Indiana/Vevay', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Indiana/Vincennes', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Indiana/Winamac', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Indianapolis', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Inuvik', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Iqaluit', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Jamaica', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Jujuy', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Juneau', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Kentucky/Louisville', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Kentucky/Monticello', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Knox_IN', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Kralendijk', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/La_Paz', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Lima', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Los_Angeles', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Louisville', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Lower_Princes', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Maceio', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Managua', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Manaus', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Marigot', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Martinique', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Matamoros', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Mazatlan', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Mendoza', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Menominee', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Merida', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Metlakatla', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Mexico_City', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Miquelon', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Moncton', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Monterrey', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Montevideo', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Montreal', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Montserrat', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Nassau', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/New_York', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Nipigon', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Nome', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Noronha', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/North_Dakota/Beulah', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/North_Dakota/Center', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/North_Dakota/New_Salem', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Ojinaga', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Panama', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Pangnirtung', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Paramaribo', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Phoenix', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Port-au-Prince', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Port_of_Spain', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Porto_Acre', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Porto_Velho', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Puerto_Rico', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Rainy_River', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Rankin_Inlet', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Recife', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Regina', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Resolute', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Rio_Branco', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Rosario', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Santa_Isabel', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Santarem', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Santiago', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Santo_Domingo', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Sao_Paulo', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Scoresbysund', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Shiprock', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Sitka', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/St_Barthelemy', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/St_Johns', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/St_Kitts', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/St_Lucia', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/St_Thomas', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/St_Vincent', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Swift_Current', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Tegucigalpa', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Thule', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Thunder_Bay', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Tijuana', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Toronto', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Tortola', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Vancouver', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Virgin', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Whitehorse', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Winnipeg', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Yakutat', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('America/Yellowknife', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Antarctica/Casey', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Antarctica/Davis', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Antarctica/DumontDUrville', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Antarctica/Macquarie', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Antarctica/Mawson', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Antarctica/McMurdo', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Antarctica/Palmer', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Antarctica/Rothera', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Antarctica/South_Pole', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Antarctica/Syowa', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Antarctica/Vostok', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Arctic/Longyearbyen', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Aden', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Almaty', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Amman', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Anadyr', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Aqtau', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Aqtobe', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Ashgabat', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Ashkhabad', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Baghdad', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Bahrain', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Baku', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Bangkok', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Beirut', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Bishkek', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Brunei', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Calcutta', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Choibalsan', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Chongqing', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Chungking', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Colombo', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Dacca', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Damascus', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Dhaka', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Dili', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Dubai', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Dushanbe', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Gaza', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Harbin', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Hebron', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Ho_Chi_Minh', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Hong_Kong', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Hovd', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Irkutsk', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Istanbul', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Jakarta', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Jayapura', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Jerusalem', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Kabul', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Kamchatka', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Karachi', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Kashgar', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Kathmandu', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Katmandu', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Khandyga', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Kolkata', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Krasnoyarsk', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Kuala_Lumpur', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Kuching', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Kuwait', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Macao', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Macau', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Magadan', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Makassar', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Manila', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Muscat', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Nicosia', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Novokuznetsk', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Novosibirsk', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Omsk', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Oral', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Phnom_Penh', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Pontianak', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Pyongyang', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Qatar', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Qyzylorda', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Rangoon', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Riyadh', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Saigon', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Sakhalin', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Samarkand', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Seoul', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Shanghai', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Singapore', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Taipei', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Tashkent', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Tbilisi', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Tehran', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Tel_Aviv', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Thimbu', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Thimphu', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Tokyo', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Ujung_Pandang', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Ulaanbaatar', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Ulan_Bator', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Urumqi', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Ust-Nera', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Vientiane', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Vladivostok', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Yakutsk', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Yekaterinburg', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Asia/Yerevan', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Atlantic/Azores', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Atlantic/Bermuda', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Atlantic/Canary', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Atlantic/Cape_Verde', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Atlantic/Faeroe', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Atlantic/Faroe', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Atlantic/Jan_Mayen', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Atlantic/Madeira', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Atlantic/Reykjavik', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Atlantic/South_Georgia', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Atlantic/St_Helena', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Atlantic/Stanley', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Australia/ACT', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Australia/Adelaide', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Australia/Brisbane', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Australia/Broken_Hill', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Australia/Canberra', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Australia/Currie', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Australia/Darwin', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Australia/Eucla', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Australia/Hobart', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Australia/LHI', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Australia/Lindeman', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Australia/Lord_Howe', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Australia/Melbourne', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Australia/North', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Australia/NSW', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Australia/Perth', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Australia/Queensland', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Australia/South', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Australia/Sydney', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Australia/Tasmania', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Australia/Victoria', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Australia/West', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Australia/Yancowinna', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Brazil/Acre', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Brazil/DeNoronha', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Brazil/East', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Brazil/West', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Canada/Atlantic', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Canada/Central', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Canada/East-Saskatchewan', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Canada/Eastern', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Canada/Mountain', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Canada/Newfoundland', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Canada/Pacific', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Canada/Saskatchewan', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Canada/Yukon', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Chile/Continental', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Chile/EasterIsland', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Cuba', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Egypt', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Eire', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Amsterdam', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Andorra', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Athens', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Belfast', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Belgrade', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Berlin', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Bratislava', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Brussels', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Bucharest', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Budapest', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Busingen', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Chisinau', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Copenhagen', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Dublin', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Gibraltar', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Guernsey', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Helsinki', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Isle_of_Man', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Istanbul', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Jersey', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Kaliningrad', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Kiev', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Lisbon', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Ljubljana', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/London', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Luxembourg', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Madrid', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Malta', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Mariehamn', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Minsk', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Monaco', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Moscow', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Nicosia', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Oslo', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Paris', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Podgorica', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Prague', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Riga', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Rome', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Samara', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/San_Marino', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Sarajevo', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Simferopol', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Skopje', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Sofia', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Stockholm', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Tallinn', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Tirane', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Tiraspol', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Uzhgorod', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Vaduz', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Vatican', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Vienna', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Vilnius', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Volgograd', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Warsaw', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Zagreb', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Zaporozhye', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Europe/Zurich', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Greenwich', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Hongkong', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Iceland', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Indian/Antananarivo', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Indian/Chagos', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Indian/Christmas', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Indian/Cocos', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Indian/Comoro', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Indian/Kerguelen', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Indian/Mahe', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Indian/Maldives', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Indian/Mauritius', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Indian/Mayotte', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Indian/Reunion', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Iran', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Israel', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Jamaica', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Japan', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Kwajalein', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Libya', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Mexico/BajaNorte', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Mexico/BajaSur', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Mexico/General', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Apia', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Auckland', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Chatham', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Chuuk', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Easter', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Efate', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Enderbury', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Fakaofo', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Fiji', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Funafuti', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Galapagos', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Gambier', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Guadalcanal', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Guam', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Honolulu', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Johnston', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Kiritimati', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Kosrae', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Kwajalein', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Majuro', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Marquesas', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Midway', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Nauru', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Niue', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Norfolk', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Noumea', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Pago_Pago', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Palau', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Pitcairn', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Pohnpei', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Ponape', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Port_Moresby', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Rarotonga', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Saipan', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Samoa', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Tahiti', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Tarawa', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Tongatapu', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Truk', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Wake', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Wallis', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Pacific/Yap', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Poland', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Portugal', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Singapore', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Turkey', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('US/Alaska', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('US/Aleutian', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('US/Arizona', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('US/Central', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('US/East-Indiana', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('US/Eastern', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('US/Hawaii', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('US/Indiana-Starke', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('US/Michigan', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('US/Mountain', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('US/Pacific', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('US/Pacific-New', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('US/Samoa', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ('Zulu', '" . mysqli_real_escape_string($connection, $time->time()) . "');";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0023',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0023;

	}

	// upgrade database from 2.0023 to 2.0024
	if ($current_db_version == 2.0023) {

		$sql = "ALTER TABLE `settings` 
					CHANGE `timezone` `timezone` VARCHAR(50) CHARACTER SET latin1 COLLATE latin1_swedish_ci NOT NULL DEFAULT 'Canada/Pacific'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0024',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
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
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "INSERT INTO `hosting` 
					(`name`, `default_host`, `insert_time`) VALUES 
					('[no hosting]', 1, '" . mysqli_real_escape_string($connection, $time->time()) . "');";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `domains`  
					ADD `hosting_id` int(10) NOT NULL default '1' AFTER `ip_id`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "SELECT id
				FROM hosting
				WHERE name = '[no hosting]'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		while ($row = mysqli_fetch_object($result)) {
			$temp_hosting_id = $row->id;
		}
		
		$sql = "UPDATE domains
				SET hosting_id = '" . mysqli_real_escape_string($connection, $temp_hosting_id) . "',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `domains` 
					CHANGE `owner_id` `owner_id` INT(5) NOT NULL DEFAULT '1'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `domains` 
					CHANGE `registrar_id` `registrar_id` INT(5) NOT NULL DEFAULT '1'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `domains` 
					CHANGE `account_id` `account_id` INT(5) NOT NULL DEFAULT '1'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `domains` 
					CHANGE `dns_id` `dns_id` INT(5) NOT NULL DEFAULT '1'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0025',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0025;

	}

	// upgrade database from 2.0025 to 2.0026
	if ($current_db_version == 2.0025) {

		$sql = "ALTER TABLE `user_settings` 
					ADD `display_domain_host` INT(1) NOT NULL DEFAULT '0' AFTER `display_domain_dns`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0026',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0026;

	}

	// upgrade database from 2.0026 to 2.0027
	if ($current_db_version == 2.0026) {

		$sql = "ALTER TABLE `registrar_accounts`  
					ADD `password` VARCHAR(100) NOT NULL AFTER `username`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0027',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0027;

	}

	// upgrade database from 2.0027 to 2.0028
	if ($current_db_version == 2.0027) {

		$sql = "ALTER TABLE `ssl_accounts`  
					ADD `password` VARCHAR(100) NOT NULL AFTER `username`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0028',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
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
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `settings`  
					ADD `expiration_email_days` INT(3) NOT NULL DEFAULT '60' AFTER `timezone`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0029',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0029;

	}

	// upgrade database from 2.0029 to 2.003
	if ($current_db_version == 2.0029) {

		$sql = "ALTER TABLE `domains`  
					ADD `notes_fixed_temp` INT(1) NOT NULL DEFAULT '0' AFTER `notes`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "SELECT id, status, status_notes, notes
				FROM domains";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		while ($row = mysqli_fetch_object($result)) {

			if ($row->status != "" || $row->status_notes != "" || $row->notes != "") {

				$full_status = "";
				$full_status_notes = "";
				$new_notes = "";
				
				if ($row->status != "") {
		
					$full_status .= "--------------------\r\n";
					$full_status .= "OLD STATUS - INSERTED " . mysqli_real_escape_string($connection, $time->time()) . "\r\n";
					$full_status .= "The Status field was removed because it was redundant.\r\n";
					$full_status .= "--------------------\r\n";
					$full_status .= $row->status . "\r\n";
					$full_status .= "--------------------";
		
				} else {
					
					$full_status = "";
					
				}
		
				if ($row->status_notes != "") {
		
					$full_status_notes .= "--------------------\r\n";
					$full_status_notes .= "OLD STATUS NOTES - INSERTED " . mysqli_real_escape_string($connection, $time->time()) . "\r\n";
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
							   SET notes = '" . trim(mysqli_real_escape_string($connection, $new_notes)) . "',
							   	   notes_fixed_temp = '1',
								   update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'
							   WHERE id = '" . mysqli_real_escape_string($connection, $row->id) . "'";
				$result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);

			} else {

				$sql_update = "UPDATE domains
							   SET notes_fixed_temp = '1',
								   update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'
							   WHERE id = '" . mysqli_real_escape_string($connection, $row->id) . "'";
				$result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);
			}

		}
		
		$sql = "SELECT *
				FROM domains
				WHERE notes_fixed_temp = '0'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		if (mysqli_num_rows($result) > 0) {
			
			echo "DATABASE UPDATE v2.003 FAILED: PLEASE CONTACT YOUR " . strtoupper($software_title) . " ADMINISTRATOR IMMEDIATELY";
			exit;
			
		} else {

			$sql = "ALTER TABLE `domains` 
						DROP `status`, 
						DROP `status_notes`,
						DROP `notes_fixed_temp`";
			$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		}
		
		$sql = "UPDATE settings
				SET db_version = '2.003',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.003;

	}

	// upgrade database from 2.003 to 2.0031
	if ($current_db_version == 2.003) {

		$sql = "ALTER TABLE `categories` 
					DROP `active`;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `currencies` 
					DROP `active`;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `dns` 
					DROP `active`;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `hosting` 
					DROP `active`;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `ip_addresses` 
					DROP `active`;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `owners` 
					DROP `active`;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `registrars` 
					DROP `active`;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `registrar_accounts` 
					DROP `active`;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `segments` 
					DROP `active`;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `ssl_accounts` 
					DROP `active`;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `ssl_cert_types` 
					DROP `active`;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `ssl_providers` 
					DROP `active`;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `ssl_providers` 
					DROP `active`;";
		$result = mysqli_query($connection, $sql);

		$sql = "UPDATE settings
				SET db_version = '2.0031',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0031;

	}

	// upgrade database from 2.0031 to 2.0032
	if ($current_db_version == 2.0031) {

        $sql = "ALTER TABLE `fees`
				ADD `transfer_fee` FLOAT NOT NULL AFTER `renewal_fee`";
        $result = mysqli_query($connection, $sql);

        $sql = "UPDATE fees
				SET transfer_fee = initial_fee,
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
        $result = mysqli_query($connection, $sql);

        // This section was made redundant by DB update v2.0033
        /*
        $sql = "ALTER TABLE `ssl_fees`
				ADD `transfer_fee` FLOAT NOT NULL AFTER `renewal_fee`";
		$result = mysqli_query($connection, $sql);
        */

		$sql = "UPDATE settings
				SET db_version = '2.0032',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0032;

	}

	// upgrade database from 2.0032 to 2.0033
	if ($current_db_version == 2.0032) {

		$sql = "ALTER TABLE `ssl_fees` 
				DROP `transfer_fee`;";
		$result = mysqli_query($connection, $sql);

		$sql = "UPDATE settings
				SET db_version = '2.0033',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0033;

	}

	// upgrade database from 2.0033 to 2.0034
	if ($current_db_version == 2.0033) {

		$sql = "ALTER TABLE `domains` 
				CHANGE `owner_id` `owner_id` INT(10) NOT NULL DEFAULT '1'";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `domains` 
				CHANGE `registrar_id` `registrar_id` INT(10) NOT NULL DEFAULT '1'";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `domains` 
				CHANGE `account_id` `account_id` INT(10) NOT NULL DEFAULT '1'";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `domains` 
				CHANGE `dns_id` `dns_id` INT(10) NOT NULL DEFAULT '1'";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `fees` 
				CHANGE `registrar_id` `registrar_id` INT(10) NOT NULL";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `registrar_accounts` 
				CHANGE `owner_id` `owner_id` INT(10) NOT NULL";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `ssl_accounts` 
				CHANGE `owner_id` `owner_id` INT(10) NOT NULL";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `ssl_certs` 
				CHANGE `owner_id` `owner_id` INT(10) NOT NULL";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `ssl_certs` 
				CHANGE `ssl_provider_id` `ssl_provider_id` INT(10) NOT NULL";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `ssl_certs` 
				CHANGE `account_id` `account_id` INT(10) NOT NULL";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `ssl_fees` 
				CHANGE `ssl_provider_id` `ssl_provider_id` INT(10) NOT NULL";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `ssl_fees` 
				CHANGE `type_id` `type_id` INT(10) NOT NULL";
		$result = mysqli_query($connection, $sql);

		$sql = "UPDATE settings
				SET db_version = '2.0034',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0034;

	}

	// upgrade database from 2.0034 to 2.0035
	if ($current_db_version == 2.0034) {

		$sql = "ALTER DATABASE " . $dbname . " 
				CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE categories CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE currencies CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE dns CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE domains CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE fees CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE hosting CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE ip_addresses CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE owners CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE registrars CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE registrar_accounts CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE segments CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE segment_data CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE settings CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE ssl_accounts CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE ssl_certs CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE ssl_cert_types CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE ssl_fees CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE ssl_providers CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE timezones CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE users CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE user_settings CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE categories CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE currencies CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE dns CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE domains CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE fees CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE hosting CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE ip_addresses CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE owners CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE registrars CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE registrar_accounts CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE segments CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE segment_data CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE settings CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE ssl_accounts CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE ssl_certs CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE ssl_cert_types CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE ssl_fees CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE ssl_providers CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE timezones CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE users CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE user_settings CONVERT TO CHARACTER SET utf8 COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "UPDATE settings
				SET db_version = '2.0035',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0035;

	}

	// upgrade database from 2.0035 to 2.0036
	if ($current_db_version == 2.0035) {

		$sql = "DROP TABLE `currency_data`;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER DATABASE " . $dbname . " 
				CHARACTER SET utf8 
				DEFAULT CHARACTER SET utf8 
				COLLATE utf8_unicode_ci
				DEFAULT COLLATE utf8_unicode_ci;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `currencies`  
				ADD `symbol` VARCHAR(4) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `conversion`,  
				ADD `symbol_order` INT(1) NOT NULL DEFAULT '0' AFTER `symbol`,  
				ADD `symbol_space` INT(1) NOT NULL DEFAULT '0' AFTER `symbol_order`,
				ADD `newly_inserted` INT(1) NOT NULL DEFAULT '1' AFTER `symbol_space`";
		$result = mysqli_query($connection, $sql);

		$sql = "UPDATE currencies
				SET newly_inserted = '0',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `settings`  
				ADD `default_currency` VARCHAR(3) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `email_address`";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `user_settings`  
				ADD `default_currency` VARCHAR(3) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL AFTER `user_id`";
		$result = mysqli_query($connection, $sql);

		$sql = "UPDATE settings
				SET default_currency = '" . $_SESSION['default_currency'] . "',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql);

		$sql = "UPDATE user_settings
				SET default_currency = '" . $_SESSION['default_currency'] . "',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql);

		$sql = "INSERT INTO currencies
				(name, currency, symbol, insert_time) VALUES 
				('Albania Lek', 'ALL', 'Lek', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Afghanistan Afghani', 'AFN', '؋', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Argentina Peso', 'ARS', '$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Aruba Guilder', 'AWG', 'ƒ', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Australia Dollar', 'AUD', '$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Azerbaijan New Manat', 'AZN', '" . 'ман' . "', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Bahamas Dollar', 'BSD', '$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Barbados Dollar', 'BBD', '$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Belarus Ruble', 'BYR', 'p.', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Belize Dollar', 'BZD', 'BZ$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Bermuda Dollar', 'BMD', '$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Bolivia Boliviano', 'BOB', '\$b', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Bosnia and Herzegovina Convertible Marka', 'BAM', 'KM', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Botswana Pula', 'BWP', 'P', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Bulgaria Lev', 'BGN', 'лв', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Brazil Real', 'BRL', 'R$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Brunei Darussalam Dollar', 'BND', '$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Cambodia Riel', 'KHR', '៛', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Canada Dollar', 'CAD', '$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Cayman Islands Dollar', 'KYD', '$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Chile Peso', 'CLP', '$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('China Yuan Renminbi', 'CNY', '¥', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Colombia Peso', 'COP', '$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Costa Rica Colon', 'CRC', '₡', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Croatia Kuna', 'HRK', 'kn', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Cuba Peso', 'CUP', '₱', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Czech Republic Koruna', 'CZK', 'Kč', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Denmark Krone', 'DKK', 'kr', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Dominican Republic Peso', 'DOP', 'RD$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('East Caribbean Dollar', 'XCD', '$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Egypt Pound', 'EGP', '£', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('El Salvador Colon', 'SVC', '$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Estonia Kroon', 'EEK', 'kr', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Euro Member Countries', 'EUR', '€', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Falkland Islands (Malvinas) Pound', 'FKP', '£', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Fiji Dollar', 'FJD', '$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Ghana Cedis', 'GHC', '¢', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Gibraltar Pound', 'GIP', '£', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Guatemala Quetzal', 'GTQ', 'Q', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Guernsey Pound', 'GGP', '£', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Guyana Dollar', 'GYD', '$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Honduras Lempira', 'HNL', 'L', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Hong Kong Dollar', 'HKD', '$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Hungary Forint', 'HUF', 'Ft', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Iceland Krona', 'ISK', 'kr', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('India Rupee', 'INR', 'Rs', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Indonesia Rupiah', 'IDR', 'Rp', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Iran Rial', 'IRR', '﷼', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Isle of Man Pound', 'IMP', '£', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Israel Shekel', 'ILS', '₪', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Jamaica Dollar', 'JMD', 'J$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Japan Yen', 'JPY', '¥', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Jersey Pound', 'JEP', '£', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Kazakhstan Tenge', 'KZT', 'лв', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Korea (North) Won', 'KPW', '₩', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Korea (South) Won', 'KRW', '₩', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Kyrgyzstan Som', 'KGS', 'лв', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Laos Kip', 'LAK', '₭', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Latvia Lat', 'LVL', 'Ls', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Lebanon Pound', 'LBP', '£', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Liberia Dollar', 'LRD', '$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Lithuania Litas', 'LTL', 'Lt', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Macedonia Denar', 'MKD', 'ден', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Malaysia Ringgit', 'RM', 'RM', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Mauritius Rupee', 'MUR', '₨', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Mexico Peso', 'MXN', '$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Mongolia Tughrik', 'MNT', '₮', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Mozambique Metical', 'MZN', 'MT', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Namibia Dollar', 'NAD', '$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Nepal Rupee', 'NPR', '₨', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Netherlands Antilles Guilder', 'ANG', 'ƒ', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('New Zealand Dollar', 'NZD', '$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Nicaragua Cordoba', 'NIO', 'C$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Nigeria Naira', 'NGN', '₦', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Norway Krone', 'NOK', 'kr', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Oman Rial', 'OMR', '﷼', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Pakistan Rupee', 'PKR', '₨', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Panama Balboa', 'PAB', 'B/.', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Paraguay Guarani', 'PYG', 'Gs', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Peru Nuevo Sol', 'PEN', 'S/.', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Philippines Peso', 'PHP', '₱', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Poland Zloty', 'PLN', 'zł', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Qatar Riyal', 'QAR', '﷼', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Romania New Leu', 'RON', 'lei', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Russia Ruble', 'RUB', 'руб', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Saint Helena Pound', 'SHP', '£', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Saudi Arabia Riyal', 'SAR', '﷼', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Serbia Dinar', 'RSD', 'Дин.', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Seychelles Rupee', 'SCR', '₨', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Singapore Dollar', 'SGD', '$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Solomon Islands Dollar', 'SBD', '$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Somalia Shilling', 'SOS', 'S', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('South Africa Rand', 'ZAR', 'R', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Sri Lanka Rupee', 'LKR', '₨', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Sweden Krona', 'SEK', 'kr', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Switzerland Franc', 'CHF', 'CHF', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Suriname Dollar', 'SRD', '$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Syria Pound', 'SYP', '£', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Taiwan New Dollar', 'TWD', 'NT$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Thailand Baht', 'THB', '฿', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Trinidad and Tobago Dollar', 'TTD', 'TT$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Turkey Lira', 'TRY', '₤', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Tuvalu Dollar', 'TVD', '$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Ukraine Hryvna', 'UAH', '₴', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('United Kingdom Pound', 'GBP', '£', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('United States Dollar', 'USD', '$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Uruguay Peso', 'UYU', '\$U', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Uzbekistan Som', 'UZS', 'лв', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Venezuela Bolivar', 'VEF', 'Bs', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Viet Nam Dong', 'VND', '₫', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Yemen Rial', 'YER', '﷼', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Zimbabwe Dollar', 'ZWD', 'Z$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Emirati Dirham', 'AED', 'د.إ', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Malaysian Ringgit', 'MYR', 'RM', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Kuwaiti Dinar', 'KWD', 'ك', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Moroccan Dirham', 'MAD', 'م.', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Iraqi Dinar', 'IQD', 'د.ع', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Bangladeshi Taka', 'BDT', 'Tk', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Bahraini Dinar', 'BHD', 'BD', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Kenyan Shilling', 'KES', 'KSh', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('CFA Franc', 'XOF', 'CFA', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Jordanian Dinar', 'JOD', 'JD', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Tunisian Dinar', 'TND', 'د.ت', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Ghanaian Cedi', 'GHS', 'GH¢', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Central African CFA Franc BEAC', 'XAF', 'FCFA', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Algerian Dinar', 'DZD', 'دج', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('CFP Franc', 'XPF', 'F', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Ugandan Shilling', 'UGX', 'USh', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Tanzanian Shilling', 'TZS', 'TZS', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Ethiopian Birr', 'ETB', 'Br', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Georgian Lari', 'GEL', 'GEL', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Cuban Convertible Peso', 'CUC', 'CUC$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Burmese Kyat', 'MMK', 'K', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Libyan Dinar', 'LYD', 'LD', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Zambian Kwacha', 'ZMK', 'ZK', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Zambian Kwacha', 'ZMW', 'ZK', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Macau Pataca', 'MOP', 'MOP$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Armenian Dram', 'AMD', 'AMD', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Angolan Kwanza', 'AOA', 'Kz', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Papua New Guinean Kina', 'PGK', 'K', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Malagasy Ariary', 'MGA', 'Ar', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Ni-Vanuatu Vatu', 'VUV', 'VT', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Sudanese Pound', 'SDG', 'SDG', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Malawian Kwacha', 'MWK', 'MK', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Rwandan Franc', 'RWF', 'FRw', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Gambian Dalasi', 'GMD', 'D', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Maldivian Rufiyaa', 'MVR', 'Rf', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Congolese Franc', 'CDF', 'FC', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Djiboutian Franc', 'DJF', 'Fdj', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Haitian Gourde', 'HTG', 'G', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Samoan Tala', 'WST', '$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Guinean Franc', 'GNF', 'FG', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Cape Verdean Escudo', 'CVE', '$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Tongan Pa\'anga', 'TOP', 'T$', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Moldovan Leu', 'MDL', 'MDL', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Sierra Leonean Leone', 'SLL', 'Le', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Burundian Franc', 'BIF', 'FBu', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Mauritanian Ouguiya', 'MRO', 'UM', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Bhutanese Ngultrum', 'BTN', 'Nu.', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Swazi Lilangeni', 'SZL', 'SZL', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Tajikistani Somoni', 'TJS', 'TJS', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Turkmenistani Manat', 'TMT', 'm', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Basotho Loti', 'LSL', 'LSL', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Comoran Franc', 'KMF', 'CF', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Sao Tomean Dobra', 'STD', 'STD', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				('Seborgan Luigino', 'SPL', 'SPL', '" . mysqli_real_escape_string($connection, $time->time()) . "')";
		$result = mysqli_query($connection, $sql);
		
		$sql = "SELECT id, currency
				FROM currencies
				WHERE newly_inserted = '0'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		while ($row = mysqli_fetch_object($result)) {
			
			$sql_find_new = "SELECT id, symbol
							 FROM currencies
							 WHERE newly_inserted = '1'
							   AND currency = '" . $row->currency . "'";
			$result_find_new = mysqli_query($connection, $sql_find_new);
			$total_results = mysqli_num_rows($result_find_new);
			
			while ($row_find_new = mysqli_fetch_object($result_find_new)) {
			
				if ($total_results > 0) {
					
					$sql_update_old = "UPDATE currencies
									   SET symbol = '" . $row_find_new->symbol . "'
									   WHERE id = '" . $row->id . "'";
					$result_update_old = mysqli_query($connection, $sql_update_old);

					$sql_delete_new = "DELETE FROM currencies
									   WHERE id = '" . $row_find_new->id . "'";
					$result_delete_new = mysqli_query($connection, $sql_delete_new);

				}
				
			}

		}

		$sql = "ALTER TABLE `currencies` 
				DROP `newly_inserted`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0036',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0036;

	}

	// upgrade database from 2.0036 to 2.0037
	if ($current_db_version == 2.0036) {
		
		$sql = "SELECT currency
				FROM currencies
				WHERE default_currency = '1'";
		$result = mysqli_query($connection, $sql);
		while ($row = mysqli_fetch_object($result)) { $temp_currency = $row->currency; }
		
		$sql = "UPDATE settings
				SET default_currency = '" . $temp_currency . "'";
		$result = mysqli_query($connection, $sql);
		
		$_SESSION['default_currency'] = $temp_currency;

		$sql = "SELECT name, symbol, symbol_order, symbol_space
				FROM currencies
				WHERE currency = '" . $_SESSION['default_currency'] . "'";
		$result = mysqli_query($connection, $sql);
	
		while ($row = mysqli_fetch_object($result)) {
			$_SESSION['default_currency_name'] = $row->name;
			$_SESSION['default_currency_symbol'] = $row->symbol;
			$_SESSION['default_currency_symbol_order'] = $row->symbol_order;
			$_SESSION['default_currency_symbol_space'] = $row->symbol_space;
		}

		$sql = "ALTER TABLE `currencies` 
				DROP `default_currency`;";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `user_settings` 
				DROP `default_currency`;";
		$result = mysqli_query($connection, $sql);
		
		$sql = "UPDATE settings
				SET db_version = '2.0037',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0037;

	}

	// upgrade database from 2.0037 to 2.0038
	if ($current_db_version == 2.0037) {

		$sql = "ALTER TABLE `user_settings`  
				ADD `default_currency` varchar(3) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL after user_id";
		$result = mysqli_query($connection, $sql);
		
		$sql = "SELECT default_currency
				FROM settings";
		$result = mysqli_query($connection, $sql);
		while ($row = mysqli_fetch_object($result)) {
			$temp_default_currency = $row->default_currency;
			$_SESSION['default_currency'] = $row->default_currency;
		}
		
		$sql = "SELECT name, symbol, symbol_order, symbol_space
				FROM currencies
				WHERE currency = '" . $_SESSION['default_currency'] . "'";
		$result = mysqli_query($connection, $sql);
		
		while ($row = mysqli_fetch_object($result)) {

			$_SESSION['default_currency_name'] = $row->name; 
			$_SESSION['default_currency_symbol'] = $row->symbol; 
			$_SESSION['default_currency_symbol_order'] = $row->symbol_order; 
			$_SESSION['default_currency_symbol_space'] = $row->symbol_space; 

		}

		$sql = "UPDATE user_settings
				SET default_currency = '" . $temp_default_currency . "'";
		$result = mysqli_query($connection, $sql);

		$sql = "CREATE TABLE IF NOT EXISTS `currency_conversions` (
				`id` int(10) NOT NULL auto_increment,
				`currency_id` int(10) NOT NULL,
				`user_id` int(10) NOT NULL,
				`conversion` float NOT NULL,
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
				) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
		$result = mysqli_query($connection, $sql);
		
		$sql = "SELECT id
				FROM users";
		$result = mysqli_query($connection, $sql);
			
		while ($row = mysqli_fetch_object($result)) {
			
			$sql_conversion = "SELECT id, conversion
							   FROM currencies
							   WHERE conversion != '0'";
			$result_conversion = mysqli_query($connection, $sql_conversion);
			
			while ($row_conversion = mysqli_fetch_object($result_conversion)) {
				
				$sql_insert = "INSERT INTO currency_conversions
							   (currency_id, user_id, conversion, insert_time, update_time) VALUES 
							   ('" . $row_conversion->id . "', '" . $row->id . "', '" . $row_conversion->conversion . "', '" . mysqli_real_escape_string($connection, $time->time()) . "', '" . mysqli_real_escape_string($connection, $time->time()) . "')";
				$result_insert = mysqli_query($connection, $sql_insert);
				
			}
			
		}

		$sql = "ALTER TABLE `currencies` 
				DROP `conversion`;";
		$result = mysqli_query($connection, $sql);

		$sql = "UPDATE settings
				SET db_version = '2.0038',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0038;

	}

	// upgrade database from 2.0038 to 2.0039
	if ($current_db_version == 2.0038) {


		$sql = "ALTER TABLE `ssl_certs`  
				ADD `ip_id` int(10) NOT NULL AFTER `type_id`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `ssl_certs`  
				ADD `cat_id` int(10) NOT NULL AFTER `ip_id`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "SELECT id, cat_id, ip_id
				FROM domains";
		$result = mysqli_query($connection, $sql);
		
		while ($row = mysqli_fetch_object($result)) {
			
			$sql_update = "UPDATE ssl_certs
						   SET cat_id = '" . $row->cat_id . "',
						   	   ip_id = '" . $row->ip_id . "',
							   update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'
						   WHERE domain_id = '" . $row->id . "'";
			$result_update = mysqli_query($connection, $sql_update);
			
		}

		$sql = "ALTER TABLE `user_settings`  
				ADD `display_ssl_ip` int(1) NOT NULL default '0' AFTER `display_ssl_expiry_date`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `user_settings`  
				ADD `display_ssl_category` int(1) NOT NULL default '0' AFTER `display_ssl_ip`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0039',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0039;

	}

	// upgrade database from 2.0039 to 2.004
	if ($current_db_version == 2.0039) {

		$sql = "ALTER TABLE `user_settings`  
				ADD `default_category` INT(10) NOT NULL default '1' AFTER `default_currency`";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `settings`  
				ADD `default_category` INT(10) NOT NULL default '1' AFTER `default_currency`";
		$result = mysqli_query($connection, $sql);
		
		$sql = "SELECT id
				FROM categories
				WHERE default_category = '1'
				LIMIT 1";
		$result = mysqli_query($connection, $sql);
		while ($row = mysqli_fetch_object($result)) {
			
			$sql_update = "UPDATE user_settings
						   SET default_category = '" . $row->id . "'";
			$result_update = mysqli_query($connection, $sql_update);

			$sql_update = "UPDATE settings
						   SET default_category = '" . $row->id . "'";
			$result_update = mysqli_query($connection, $sql_update);
			
			$_SESSION['default_category'] = $row->id;
			$_SESSION['system_default_category'] = $row->id;

		}

		$sql = "ALTER TABLE `categories` 
				DROP `default_category`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `user_settings`  
				ADD `default_dns` INT(10) NOT NULL default '1' AFTER `default_category`";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `settings`  
				ADD `default_dns` INT(10) NOT NULL default '1' AFTER `default_category`";
		$result = mysqli_query($connection, $sql);

		$sql = "SELECT id
				FROM dns
				WHERE default_dns = '1'
				LIMIT 1";
		$result = mysqli_query($connection, $sql);
		while ($row = mysqli_fetch_object($result)) {
			
			$sql_update = "UPDATE user_settings
						   SET default_dns = '" . $row->id . "'";
			$result_update = mysqli_query($connection, $sql_update);

			$sql_update = "UPDATE settings
						   SET default_dns = '" . $row->id . "'";
			$result_update = mysqli_query($connection, $sql_update);

			$_SESSION['default_dns'] = $row->id;
			$_SESSION['system_default_dns'] = $row->id;

		}

		$sql = "ALTER TABLE `dns` 
				DROP `default_dns`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `user_settings`  
				ADD `default_host` INT(10) NOT NULL default '1' AFTER `default_dns`";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `settings`  
				ADD `default_host` INT(10) NOT NULL default '1' AFTER `default_dns`";
		$result = mysqli_query($connection, $sql);

		$sql = "SELECT id
				FROM hosting
				WHERE default_host = '1'
				LIMIT 1";
		$result = mysqli_query($connection, $sql);
		while ($row = mysqli_fetch_object($result)) {
			
			$sql_update = "UPDATE user_settings
						   SET default_host = '" . $row->id . "'";
			$result_update = mysqli_query($connection, $sql_update);

			$sql_update = "UPDATE settings
						   SET default_host = '" . $row->id . "'";
			$result_update = mysqli_query($connection, $sql_update);

			$_SESSION['default_host'] = $row->id;
			$_SESSION['system_default_host'] = $row->id;

		}

		$sql = "ALTER TABLE `hosting` 
				DROP `default_host`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `user_settings`  
				ADD `default_ip_address` INT(10) NOT NULL default '1' AFTER `default_host`";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `settings`  
				ADD `default_ip_address` INT(10) NOT NULL default '1' AFTER `default_host`";
		$result = mysqli_query($connection, $sql);

		$sql = "SELECT id
				FROM ip_addresses
				WHERE default_ip_address = '1'
				LIMIT 1";
		$result = mysqli_query($connection, $sql);
		while ($row = mysqli_fetch_object($result)) {
			
			$sql_update = "UPDATE user_settings
						   SET default_ip_address = '" . $row->id . "'";
			$result_update = mysqli_query($connection, $sql_update);

			$sql_update = "UPDATE settings
						   SET default_ip_address = '" . $row->id . "'";
			$result_update = mysqli_query($connection, $sql_update);

			$_SESSION['default_ip_address'] = $row->id;
			$_SESSION['system_default_ip_address'] = $row->id;
			
		}

		$sql = "ALTER TABLE `ip_addresses` 
				DROP `default_ip_address`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `user_settings`  
				ADD `default_owner` INT(10) NOT NULL default '1' AFTER `default_ip_address`";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `settings`  
				ADD `default_owner` INT(10) NOT NULL default '1' AFTER `default_ip_address`";
		$result = mysqli_query($connection, $sql);

		$sql = "SELECT id
				FROM owners
				WHERE default_owner = '1'
				LIMIT 1";
		$result = mysqli_query($connection, $sql);
		while ($row = mysqli_fetch_object($result)) {
			
			$sql_update = "UPDATE user_settings
						   SET default_owner = '" . $row->id . "'";
			$result_update = mysqli_query($connection, $sql_update);

			$sql_update = "UPDATE settings
						   SET default_owner = '" . $row->id . "'";
			$result_update = mysqli_query($connection, $sql_update);

			$_SESSION['default_owner'] = $row->id;
			$_SESSION['system_default_owner'] = $row->id;
			
		}

		$sql = "ALTER TABLE `owners` 
				DROP `default_owner`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `user_settings`  
				ADD `default_registrar` INT(10) NOT NULL default '1' AFTER `default_owner`";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `settings`  
				ADD `default_registrar` INT(10) NOT NULL default '1' AFTER `default_owner`";
		$result = mysqli_query($connection, $sql);

		$sql = "SELECT id
				FROM registrars
				WHERE default_registrar = '1'
				LIMIT 1";
		$result = mysqli_query($connection, $sql);
		while ($row = mysqli_fetch_object($result)) {
			
			$sql_update = "UPDATE user_settings
						   SET default_registrar = '" . $row->id . "'";
			$result_update = mysqli_query($connection, $sql_update);

			$sql_update = "UPDATE settings
						   SET default_registrar = '" . $row->id . "'";
			$result_update = mysqli_query($connection, $sql_update);

			$_SESSION['default_registrar'] = $row->id;
			$_SESSION['system_default_registrar'] = $row->id;

		}

		$sql = "ALTER TABLE `registrars` 
				DROP `default_registrar`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `user_settings`  
				ADD `default_registrar_account` INT(10) NOT NULL default '1' AFTER `default_registrar`";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `settings`  
				ADD `default_registrar_account` INT(10) NOT NULL default '1' AFTER `default_registrar`";
		$result = mysqli_query($connection, $sql);

		$sql = "SELECT id
				FROM registrar_accounts
				WHERE default_account = '1'
				LIMIT 1";
		$result = mysqli_query($connection, $sql);
		while ($row = mysqli_fetch_object($result)) {
			
			$sql_update = "UPDATE user_settings
						   SET default_registrar_account = '" . $row->id . "'";
			$result_update = mysqli_query($connection, $sql_update);

			$sql_update = "UPDATE settings
						   SET default_registrar_account = '" . $row->id . "'";
			$result_update = mysqli_query($connection, $sql_update);

			$_SESSION['default_registrar_account'] = $row->id;
			$_SESSION['system_default_registrar_account'] = $row->id;

		}

		$sql = "ALTER TABLE `registrar_accounts` 
				DROP `default_account`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `user_settings`  
				ADD `default_ssl_provider_account` INT(10) NOT NULL default '1' AFTER `default_registrar_account`";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `settings`  
				ADD `default_ssl_provider_account` INT(10) NOT NULL default '1' AFTER `default_registrar_account`";
		$result = mysqli_query($connection, $sql);

		$sql = "SELECT id
				FROM ssl_accounts
				WHERE default_account = '1'
				LIMIT 1";
		$result = mysqli_query($connection, $sql);
		while ($row = mysqli_fetch_object($result)) {
			
			$sql_update = "UPDATE user_settings
						   SET default_ssl_provider_account = '" . $row->id . "'";
			$result_update = mysqli_query($connection, $sql_update);

			$sql_update = "UPDATE settings
						   SET default_ssl_provider_account = '" . $row->id . "'";
			$result_update = mysqli_query($connection, $sql_update);

			$_SESSION['default_ssl_provider_account'] = $row->id;
			$_SESSION['system_default_ssl_provider_account'] = $row->id;

		}

		$sql = "ALTER TABLE `ssl_accounts` 
				DROP `default_account`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `user_settings`  
				ADD `default_ssl_type` INT(10) NOT NULL default '1' AFTER `default_ssl_provider_account`";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `settings`  
				ADD `default_ssl_type` INT(10) NOT NULL default '1' AFTER `default_ssl_provider_account`";
		$result = mysqli_query($connection, $sql);

		$sql = "SELECT id
				FROM ssl_cert_types
				WHERE default_type = '1'
				LIMIT 1";
		$result = mysqli_query($connection, $sql);
		while ($row = mysqli_fetch_object($result)) {
			
			$sql_update = "UPDATE user_settings
						   SET default_ssl_type = '" . $row->id . "'";
			$result_update = mysqli_query($connection, $sql_update);

			$sql_update = "UPDATE settings
						   SET default_ssl_type = '" . $row->id . "'";
			$result_update = mysqli_query($connection, $sql_update);

			$_SESSION['default_ssl_type'] = $row->id;
			$_SESSION['system_default_ssl_type'] = $row->id;

		}

		$sql = "ALTER TABLE `ssl_cert_types` 
				DROP `default_type`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `user_settings`  
				ADD `default_ssl_provider` INT(10) NOT NULL default '1' AFTER `default_ssl_type`";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `settings`  
				ADD `default_ssl_provider` INT(10) NOT NULL default '1' AFTER `default_ssl_type`";
		$result = mysqli_query($connection, $sql);

		$sql = "SELECT id
				FROM ssl_providers
				WHERE default_provider = '1'
				LIMIT 1";
		$result = mysqli_query($connection, $sql);
		while ($row = mysqli_fetch_object($result)) {
			
			$sql_update = "UPDATE user_settings
						   SET default_ssl_provider = '" . $row->id . "'";
			$result_update = mysqli_query($connection, $sql_update);

			$sql_update = "UPDATE settings
						   SET default_ssl_provider = '" . $row->id . "'";
			$result_update = mysqli_query($connection, $sql_update);

			$_SESSION['default_ssl_provider'] = $row->id;
			$_SESSION['system_default_ssl_provider'] = $row->id;

		}

		$sql = "ALTER TABLE `ssl_providers` 
				DROP `default_provider`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `settings`  
				ADD `default_timezone` VARCHAR(50) NOT NULL DEFAULT 'Canada/Pacific' AFTER `default_currency`";
		$result = mysqli_query($connection, $sql);

		$sql = "UPDATE settings
				SET default_timezone = timezone";
		$result = mysqli_query($connection, $sql);

		$sql = "ALTER TABLE `settings` 
				DROP `timezone`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `user_settings`  
				ADD `default_timezone` VARCHAR(50) NOT NULL DEFAULT 'Canada/Pacific' AFTER `default_currency`";
		$result = mysqli_query($connection, $sql);
		
		$sql = "SELECT default_timezone
				FROM settings
				ORDER BY id desc
				LIMIT 1";
		$result = mysqli_query($connection, $sql);
		while ($row = mysqli_fetch_object($result)) { $temp_default_system_timezone = $row->default_timezone; }
		
		$sql = "UPDATE user_settings
				SET default_timezone = '" . $temp_default_system_timezone . "'";
		$result = mysqli_query($connection, $sql);

		$_SESSION['default_timezone'] = $temp_default_system_timezone;
		$_SESSION['system_default_timezone'] = $temp_default_system_timezone;

		$sql = "ALTER TABLE `settings` 
				DROP `default_currency`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `settings` 
				DROP `default_timezone`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.004',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.004;

	}

	// upgrade database from 2.004 to 2.0041
	if ($current_db_version == 2.004) {

		$sql = "ALTER TABLE `user_settings` 
				CHANGE `default_category` `default_category_domains` INT(10) NOT NULL DEFAULT '0'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$sql = "ALTER TABLE `user_settings` 
				CHANGE `default_ip_address` `default_ip_address_domains` INT(10) NOT NULL DEFAULT '0'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `user_settings` 
				CHANGE `default_owner` `default_owner_domains` INT(10) NOT NULL DEFAULT '0'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `user_settings` 
				ADD `default_category_ssl` INT(10) NOT NULL DEFAULT '0' AFTER `default_category_domains`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$sql = "ALTER TABLE `user_settings` 
				ADD `default_ip_address_ssl` INT(10) NOT NULL DEFAULT '0' AFTER `default_ip_address_domains`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `user_settings` 
				ADD `default_owner_ssl` INT(10) NOT NULL DEFAULT '0' AFTER `default_owner_domains`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "SELECT id, default_category_domains, default_ip_address_domains, default_owner_domains
				FROM user_settings";
		$result = mysqli_query($connection, $sql);
		
		while ($row = mysqli_fetch_object($result)) {

			$sql_update = "UPDATE user_settings
						   SET default_category_ssl = '" . $row->default_category_domains . "',
						   	   default_ip_address_ssl = '" . $row->default_ip_address_domains . "',
							   default_owner_ssl = '" . $row->default_owner_domains . "'
						   WHERE id = '" . $row->id . "'";
			$result_update = mysqli_query($connection, $sql_update) or $error->outputOldSqlError($connection);

		}

		$sql = "SELECT default_category_domains, default_ip_address_domains, default_owner_domains
				FROM user_settings
				WHERE user_id = '" . $_SESSION['user_id'] . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		while ($row = mysqli_fetch_object($result)) {

			$default_category_domains = $row->default_category_domains;
			$default_ip_address_domains = $row->default_ip_address_domains;
			$default_owner_domains = $row->default_owner_domains;

		}

		$_SESSION['default_category_domains'] = $default_category_domains;
		$_SESSION['default_category_ssl'] = $default_category_domains;
		$_SESSION['default_ip_address_domains'] = $default_ip_address_domains;
		$_SESSION['default_ip_address_ssl'] = $default_ip_address_domains;
		$_SESSION['default_owner_domains'] = $default_owner_domains;
		$_SESSION['default_owner_ssl'] = $default_owner_domains;

		$sql = "ALTER TABLE `settings` 
				CHANGE `default_category` `default_category_domains` INT(10) NOT NULL DEFAULT '0'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$sql = "ALTER TABLE `settings` 
				CHANGE `default_ip_address` `default_ip_address_domains` INT(10) NOT NULL DEFAULT '0'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `settings` 
				CHANGE `default_owner` `default_owner_domains` INT(10) NOT NULL DEFAULT '0'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `settings` 
				ADD `default_category_ssl` INT(10) NOT NULL DEFAULT '0' AFTER `default_category_domains`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$sql = "ALTER TABLE `settings` 
				ADD `default_ip_address_ssl` INT(10) NOT NULL DEFAULT '0' AFTER `default_ip_address_domains`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `settings` 
				ADD `default_owner_ssl` INT(10) NOT NULL DEFAULT '0' AFTER `default_owner_domains`";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "SELECT default_category_domains, default_ip_address_domains, default_owner_domains
				FROM settings";
		$result = mysqli_query($connection, $sql);
		
		while ($row = mysqli_fetch_object($result)) {

			$default_category_domains = $row->default_category_domains;
			$default_ip_address_domains = $row->default_ip_address_domains;
			$default_owner_domains = $row->default_owner_domains;

		}

		$sql = "UPDATE settings
				SET default_category_ssl = '" . $default_category_domains . "',
					default_ip_address_ssl = '" . $default_ip_address_domains . "',
					default_owner_ssl = '" . $default_owner_domains . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$_SESSION['system_default_category_domains'] = $default_category_domains;
		$_SESSION['system_default_category_ssl'] = $default_category_domains;
		$_SESSION['system_default_ip_address_domains'] = $default_ip_address_domains;
		$_SESSION['system_default_ip_address_ssl'] = $default_ip_address_domains;
		$_SESSION['system_default_owner_domains'] = $default_owner_domains;
		$_SESSION['system_default_owner_ssl'] = $default_owner_domains;

		$sql = "ALTER TABLE `settings` 
				CHANGE `default_dns` `default_dns` INT(10) NOT NULL DEFAULT '0'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `settings` 
				CHANGE `default_host` `default_host` INT(10) NOT NULL DEFAULT '0'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `settings` 
				CHANGE `default_registrar` `default_registrar` INT(10) NOT NULL DEFAULT '0'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `settings` 
				CHANGE `default_registrar_account` `default_registrar_account` INT(10) NOT NULL DEFAULT '0'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `settings` 
				CHANGE `default_ssl_provider_account` `default_ssl_provider_account` INT(10) NOT NULL DEFAULT '0'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `settings` 
				CHANGE `default_ssl_type` `default_ssl_type` INT(10) NOT NULL DEFAULT '0'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `settings` 
				CHANGE `default_ssl_provider` `default_ssl_provider` INT(10) NOT NULL DEFAULT '0'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `user_settings` 
				CHANGE `default_dns` `default_dns` INT(10) NOT NULL DEFAULT '0'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `user_settings` 
				CHANGE `default_host` `default_host` INT(10) NOT NULL DEFAULT '0'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `user_settings` 
				CHANGE `default_registrar` `default_registrar` INT(10) NOT NULL DEFAULT '0'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `user_settings` 
				CHANGE `default_registrar_account` `default_registrar_account` INT(10) NOT NULL DEFAULT '0'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `user_settings` 
				CHANGE `default_ssl_provider_account` `default_ssl_provider_account` INT(10) NOT NULL DEFAULT '0'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `user_settings` 
				CHANGE `default_ssl_type` `default_ssl_type` INT(10) NOT NULL DEFAULT '0'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `user_settings` 
				CHANGE `default_ssl_provider` `default_ssl_provider` INT(10) NOT NULL DEFAULT '0'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0041',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0041;

	}

	// upgrade database from 2.0041 to 2.0042
	if ($current_db_version == 2.0041) {

		// This section was made redundant by DB update v2.005
		/*
		$sql = "CREATE TABLE IF NOT EXISTS `updates` (
				`id` int(10) NOT NULL auto_increment,
				`name` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`update` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
				) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "INSERT INTO updates
				(name, `update`, insert_time, update_time) VALUES 
				('Domain Manager now contains a Software Updates section!', 'After upgrading Domain Manager I\'m sure it would be nice to know what new features have been added, as well as any important changes to the software that you should know about, so I\'ve added a Software Updates section that chronicles the most important and most useful new features. Now after an upgrade you can simply visit the Software Updates section and view a list of the updates since your previous version.', '2013-05-04 00:00:00', '2013-05-04 00:00:00'),
				('Overhaul of Domain Manager Settings Complete!', 'Over the past few months the Domain Manager settings have been undergoing a complete overhaul. The changes include but are not limited to making currency conversions user-based instead of system-based, updating all Domain & SSL default settings to be user-based instead of system-based, separating out Category, IP Address and Owner settings so that Domains & SSLs have thier own options instead of sharing them, adding support for saving passwords for Domain Registrar & SSL Provider accounts, removing the redundant Status and Status Notes fields from the Domains section, and so on.<BR><BR>I\'m constantly trying to improve the software and make it more user-friendly, so if you have any suggestions or feedback feel free to drop me a line at <a class=\"invisiblelink\" href=\"mailto:greg@chetcuti.com\">greg@chetcuti.com</a>.', '2013-05-02 00:00:00', '2013-05-02 00:00:00'),
				('Currencies have been updated to be user-based instead of system-based', 'Now that Currencies have been re-worked to be user-based, every user in the system can set their own default currency, and this currency will be used for them throughout the system. Every setting, webpage, and report in the Domain Manager system will automatically be converted to display monetary values using the user\'s default currency.', '2013-04-29 00:00:00', '2013-04-29 00:00:00'),
				('Domain Manager has been converted to UTF-8', 'The entire Domain Manager system has been converted to use the UTF-8 character set in order to allow for support of non-ASCII characters, such as the characters found in some IDNs (Internationalized Domain Names).', '2013-04-27 00:00:01', '2013-04-27 00:00:01'),
				('Cron job added for automating currency conversions at regular intervals', 'Never worry about having outdated exchange rates again! Domain Manager now includes a cron job that automates currency conversions. This means you can have the cron job set to run overnight, and when you go to use the Domain Manager software in the morning your currency conversions will already be completely up-to-date.<BR><BR>If you would like to use this feature, just let your system administrator know so that they can schedule the cron job to run.', '2013-04-27 00:00:00', '2013-04-27 00:00:00'),
				('Reporting section added', 'Domain Manager now includes a handful of reports that can give you valuable insight into your data, and I\'m always on the lookout for more reports that can be added. If you have any new report ideas, or any suggestions for the current reports, feel free to drop me a line at <a class=\"invisiblelink\" href=\"mailto:greg@chetcuti.com\">greg@chetcuti.com</a>.', '2013-04-25 00:00:00', '2013-04-25 00:00:00'),
				('Added a \'view full notes\' feature to the Domain and SSL Cert edit pages', 'When editing a Domain or SSL certificate, if you want to view the notes but scrolling through the text box just isn\'t your thing, you can now click on a link to view the full notes on a separate page, making them much easier to read.', '2013-04-24 00:00:00', '2013-04-24 00:00:00'),
				('Remove the (redundant) Domain Status and Status Notes fields', 'Although the Domain Status & Status Notes fields were removed because they were redundant, if you had data stored in either of these fields it would have been appended to the primary Notes field when your Domain Manager database was upgraded. So don\'t worry, dropping these two fields didn\'t cause you to lose any data.', '2013-04-20 00:00:00', '2013-04-20 00:00:00'),
				('Update the expiration email so that the System Administrator can set the number of days in the future to display in the email', 'Previously when the daily expiration emails were sent out to users they would automatically include the next 60 days of expirations, but this has now been converted to a system setting so that your system administrator can now specify the number of days to include in the email.', '2013-04-19 00:00:01', '2013-04-19 00:00:01'),
				('A password field has now been added to Registrar & SSL Provider accounts so that passwords can be managed through Domain Manager', '', '2013-04-19 00:00:00', '2013-04-19 00:00:00'),
				('A new \'Web Hosting\' section has been added to the UI so that you can now keep track of your web hosting providers within Domain Manager', '', '2013-04-17 00:00:00', '2013-04-17 00:00:00'),
				('Cron job added for sending an email to users about upcoming Domain and SSL Certificate renewals', 'A cron job has now been added to send a daily email to users letting them know about upcoming domain and SSL expirations, and users can subscribe and unsubscribe from this email through their Control Panel.<BR><BR>If you would like to use this feature, just let your system administrator know so that they can schedule the cron job to run.', '2013-04-15 00:00:00', '2013-04-15 00:00:00'),
				('A logo has now been added to the Domain Manager software in order to pretty things up a little bit', '', '2013-04-10 00:00:00', '2013-04-10 00:00:00'),
				('The Domain & SSL search pages have been updated to allow for the exporting of results', '', '2013-04-07 00:00:00', '2013-04-07 00:00:00'),
				('Update the Segments UI to give the user a lot more information and flexibility', 'Now when filtering your domains using a segment, Domain Manager will tell you which domains in the segment are stored in your Domain Manager (indicating whether or not the domain is active or inactive), as well as which domains don\'t match, and lastly it will tell you which domains matched but were filtered out based on your other search criteria. Each of the resulting lists can be easily viewed and exported for your convenience.<BR><BR>It took quite a bit of work to get this feature implemented, but the segment filtering just felt incomplete without it. It was still a very useful feature, but now it\'s incredibly powerful, and I hope to add on the functionality in the future.', '2013-04-07 00:00:00', '2013-04-07 00:00:00'),
				('Test Data System removed, Demo launched', 'In order to focus on the development of the actual Domain Manager software, I\'ve decided to remove the Test Data System entirely. Although this system allowed users to easily generate some test data and get a feel for the software, it complicated the development process and added unecessary overhead to the software as a whole. Most importantly, it took me away from adding other, more useful features to the core software.<BR><BR>Now instead of testing the software by installing it and generating the test data, you can simply visit <a class=\"invisiblelink\" target=\"_blank\" href=\"http://demos.aysmedia.com/domainmanager/\">http://demos.aysmedia.com/domainmanager/</a> to take Domain Manager for a test drive.', '2013-04-06 00:00:00', '2013-04-06 00:00:00'),
				('A new \'IP Address\' section has been added to the UI so that you can keep track of all your IP Addresses within Domain Manager', '', '2013-03-26 00:00:00', '2013-03-26 00:00:00'),
				('Support has been added for automatic currency updates!', 'Thanks to Yahoo! Finance\'s free API, I\'m happy to announce that currency conversions have been completely automated. Now instead of having to manually update the conversions one-by-one on a regular basis to ensure proper financial reporting, all you have to do is make sure your default currency is set and your conversion rates will be updated automatically and seemlessly in the background while you use the software.<BR><BR>To say that this feature pleases me would be a huge understatement. I personally use the Domain Manager software on a daily basis, and updating the currency conversions manually was always such a boring, tedious task, and I\'m happy that nobody will ever have to go through that process ever again. If I could give Yahoo! Finance a big hug, I would.', '2013-03-20 00:00:01', '2013-03-20 00:00:01'),
				('Domain Manager now contains a Software Updates section!', '<em>[This feature was implemented on 2013-05-04, but it seemed appropriate that the very first post in the Software Updates section be information about the new section itself, so the post was duplicated and backdated]</em><BR><BR>After upgrading Domain Manager I\'m sure it would be nice to know what new features have been added, as well as any important changes to the software that you should know about, so I\'ve added a Software Updates section that chronicles the most important and most useful new features. Now after an upgrade you can simply visit the Software Updates section and view a list of the updates since your previous version.', '2013-03-20 00:00:00', '2013-03-20 00:00:00')";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$sql = "CREATE TABLE IF NOT EXISTS update_data (
				`id` int(10) NOT NULL auto_increment,
				`user_id` int(10) NOT NULL,
				`update_id` int(10) NOT NULL,
				`insert_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
				) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$sql = "SELECT id
				FROM users";
		$result = mysqli_query($connection, $sql);

		while ($row = mysqli_fetch_object($result)) {

			$sql_updates = "SELECT id
							FROM `updates`";
			$result_updates = mysqli_query($connection, $sql_updates);

			while ($row_updates = mysqli_fetch_object($result_updates)) {

				$sql_insert = "INSERT INTO 
							   update_data
							   (user_id, update_id, insert_time) VALUES 
							   ('" . $row->id . "', '" . $row_updates->id . "', '" . mysqli_real_escape_string($connection, $time->time()) . "')";
				$result_insert = mysqli_query($connection, $sql_insert);

			}

		}

		$_SESSION['are_there_updates'] = "1";

		$sql = "UPDATE settings
				SET db_version = '2.0042',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		*/

		$current_db_version = 2.0042;

	}

	// upgrade database from 2.0042 to 2.0043
	if ($current_db_version == 2.0042) {

		$sql = "ALTER TABLE `segments` 
				CHANGE `name` `name` VARCHAR(40) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0043',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0043;

	}

	// upgrade database from 2.0043 to 2.0044
	if ($current_db_version == 2.0043) {

		$sql = "ALTER TABLE `owners` 
				CHANGE `name` `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `categories` 
				CHANGE `name` `name` VARCHAR(150) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `categories` 
				CHANGE `stakeholder` `stakeholder` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `hosting` 
				CHANGE `name` `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `ip_addresses` 
				CHANGE `name` `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `ip_addresses` 
				CHANGE `ip` `ip` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `ip_addresses` 
				CHANGE `rdns` `rdns` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT '-'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `registrar_accounts` 
				CHANGE `username` `username` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `registrar_accounts` 
				CHANGE `password` `password` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `registrars` 
				CHANGE `name` `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `registrars` 
				CHANGE `url` `url` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `ssl_accounts` 
				CHANGE `username` `username` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `ssl_accounts` 
				CHANGE `password` `password` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `ssl_providers` 
				CHANGE `name` `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `ssl_providers` 
				CHANGE `url` `url` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `ssl_cert_types` 
				CHANGE `type` `type` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `users` 
				CHANGE `username` `username` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `users` 
				CHANGE `email_address` `email_address` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `settings` 
				CHANGE `email_address` `email_address` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "ALTER TABLE `ssl_certs` 
				CHANGE `name` `name` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0044',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0044;

	}

	// upgrade database from 2.0044 to 2.0045
	if ($current_db_version == 2.0044) {

		$sql = "ALTER TABLE `segments` 
				CHANGE `name` `name` VARCHAR(35) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0045',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0045;

	}

	// upgrade database from 2.0045 to 2.0046
	if ($current_db_version == 2.0045) {

		// This section was made redundant by DB update v2.005
		/*
		$sql = "INSERT INTO updates
				(name, `update`, insert_time, update_time) VALUES 
				('An Export option has been added to all Asset pages', '', '2013-05-06 00:00:00', '2013-05-06 00:00:00')";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "SELECT id
				FROM users";
		$result = mysqli_query($connection, $sql);

		while ($row = mysqli_fetch_object($result)) {

			$sql_updates = "SELECT id
							FROM `updates`
							WHERE name = 'An Export option has been added to all Asset pages'
							  AND insert_time = '2013-05-06 00:00:00'";
			$result_updates = mysqli_query($connection, $sql_updates);

			while ($row_updates = mysqli_fetch_object($result_updates)) {

				$sql_insert = "INSERT INTO 
							   update_data
							   (user_id, update_id, insert_time) VALUES 
							   ('" . $row->id . "', '" . $row_updates->id . "', '" . mysqli_real_escape_string($connection, $time->time()) . "')";
				$result_insert = mysqli_query($connection, $sql_insert);

			}

		}
		
		$_SESSION['are_there_updates'] = "1";

		$sql = "UPDATE settings
				SET db_version = '2.0046',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		*/
		
		$current_db_version = 2.0046;

	}

	// upgrade database from 2.0046 to 2.0047
	if ($current_db_version == 2.0046) {

		$sql = "ALTER TABLE `hosting`  
				ADD `url` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL after name";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "UPDATE settings
				SET db_version = '2.0047',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$current_db_version = 2.0047;

	}

	// upgrade database from 2.0047 to 2.0048
	if ($current_db_version == 2.0047) {

		$sql = "CREATE TABLE IF NOT EXISTS `custom_field_types` (
				`id` int(10) NOT NULL auto_increment,
				`name` varchar(150) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
				) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$sql = "INSERT INTO custom_field_types
				(id, name, insert_time) VALUES 
				(1, 'Check Box', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				(2, 'Text', '" . mysqli_real_escape_string($connection, $time->time()) . "'),
				(3, 'Text Area', '" . mysqli_real_escape_string($connection, $time->time()) . "')";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "CREATE TABLE IF NOT EXISTS `domain_fields` (
				`id` int(10) NOT NULL auto_increment,
				`name` varchar(75) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`field_name` varchar(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`type_id` int(10) NOT NULL,
				`description` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`notes` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
				) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "CREATE TABLE IF NOT EXISTS `domain_field_data` (
				`id` int(10) NOT NULL auto_increment,
				`domain_id` int(10) NOT NULL,
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
				) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$sql = "SELECT id
				FROM domains";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		while ($row = mysqli_fetch_object($result)) {
			
			$full_id_string .= "('" . $row->id . "', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ";
			
		}

		$full_id_string_formatted = substr($full_id_string, 0, -2);
		
		$sql = "INSERT INTO domain_field_data
				(domain_id, insert_time) VALUES 
				" . $full_id_string_formatted . "";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$full_id_string = "";
		$full_id_string_formatted = "";

		$sql = "CREATE TABLE IF NOT EXISTS `ssl_cert_fields` (
				`id` int(10) NOT NULL auto_increment,
				`name` varchar(75) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`field_name` varchar(30) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`type_id` int(10) NOT NULL,
				`description` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`notes` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
				) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "CREATE TABLE IF NOT EXISTS `ssl_cert_field_data` (
				`id` int(10) NOT NULL auto_increment,
				`ssl_id` int(10) NOT NULL,
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
				) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$sql = "SELECT id
				FROM ssl_certs";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        if (mysqli_num_rows($result) > 0) {

            while ($row = mysqli_fetch_object($result)) {

                $full_id_string .= "('" . $row->id . "', '" . mysqli_real_escape_string($connection, $time->time()) . "'), ";

            }

            $full_id_string_formatted = substr($full_id_string, 0, -2);

            $sql = "INSERT INTO ssl_cert_field_data
                    (ssl_id, insert_time) VALUES
                    " . $full_id_string_formatted . "";
            $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        }

		// This section was made redundant by DB update v2.005
		/*
		$sql = "INSERT INTO updates
				(name, `update`, insert_time, update_time) VALUES 
				('You can now create Custom Domain & SSL Fields!', 'In an effort to allow users more flexibility, as well as track as much data as possible, I\'ve implemented Custom Domain & SSL Fields. Now if there\'s information you want to track for a domain or SSL certificate but the field doesn\'t exist in Domain Manager, you can just add it yourself!<BR><BR>For example, if you wanted to keep track of which domains are currenty setup in Google Analytics, you could create a new Google Analytics check box field and start tracking this information for each of your domains. Or if you were working in a corporate environment and wanted to keep a record of who purchased each of your SSL certificates, you could create a Purchaser Name text field and keep track of this information for every one of your SSL certificates. Combine custom fields with the ability to update them with the Bulk Updater, and the sky\'s the limit in regards to what data you can easily track! (the Bulk Updater currently only supports domains, not SSL certificates)<BR><BR>And when you export your domain & SSL data, the information contained in your custom fields will automatically be included in the exported data.', '2013-05-25 17:00:00', '2013-05-25 17:00:00')";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "SELECT id
				FROM `updates`
				WHERE name = 'You can now create Custom Domain & SSL Fields!'
				  AND insert_time = '2013-05-25 17:00:00'";
		$result = mysqli_query($connection, $sql);
		while ($row = mysqli_fetch_object($result)) { $temp_update_id = $row->id; }
		
		$sql = "SELECT id
				FROM users";
		$result = mysqli_query($connection, $sql);

		while ($row = mysqli_fetch_object($result)) {

			$sql_insert = "INSERT INTO 
						   update_data
						   (user_id, update_id, insert_time) VALUES 
						   ('" . $row->id . "', '" . $temp_update_id . "', '" . mysqli_real_escape_string($connection, $time->time()) . "')";
			$result_insert = mysqli_query($connection, $sql_insert);

		}

		$_SESSION['are_there_updates'] = "1";

		$sql = "UPDATE settings
				SET db_version = '2.0048',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		*/

		$current_db_version = 2.0048;

	}

	// upgrade database from 2.0048 to 2.0049
	if ($current_db_version == 2.0048) {

		$sql = "CREATE TABLE IF NOT EXISTS `dw_servers` (
				`id` int(10) NOT NULL auto_increment,
				`name` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`host` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`protocol` varchar(5) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`port` int(5) NOT NULL,
				`username` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`hash` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`notes` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`dw_accounts` int(10) NOT NULL,
				`dw_dns_zones` int(10) NOT NULL,
				`dw_dns_records` int(10) NOT NULL,
				`build_status` int(1) NOT NULL default '0',
				`build_start_time` datetime NOT NULL,
				`build_end_time` datetime NOT NULL,
				`build_time` int(10) NOT NULL default '0',
				`has_ever_been_built` int(1) NOT NULL default '0',
				`build_status_overall` int(1) NOT NULL default '0',
				`build_start_time_overall` datetime NOT NULL,
				`build_end_time_overall` datetime NOT NULL,
				`build_time_overall` int(10) NOT NULL default '0',
				`has_ever_been_built_overall` int(1) NOT NULL default '0',
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
				) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		// This section was made redundant by DB update v2.005
		/*
		$sql = "INSERT INTO updates
				(name, `update`, insert_time, update_time) VALUES 
				('Domain Manager now includes a Data Warehouse for importing data', 'Domain Manager now has a data warehouse framework built right into it, which allows you to import the data stored on your web servers. Currently the only web servers that are supported are ones that run WHM/cPanel, but I also intend on adding support for Plesk and other systems once I’ve ironed out all the kinks in the framework.<BR><BR>The data warehouse is used for informational purposes only, and you will see its data referenced throughout Domain Manager where applicable. For example, if a domain you’re editing has information stored in your data warehouse, the system will automatically match them up and display the additional information for you, giving you even more insight into your data. You can also view, export, and run reports on the information in your data warehouse.<BR><BR>The following WHM data is currently supported, but my end goal is to have every piece of WHM information that can be retrieved via the API stored in the data warehouse.<BR><BR><strong>ACCOUNTS</strong><BR>Domain, IP Address, Owner, User, Contact Email, Plan, Theme, Shell, Partition, Disk Limit, Disk Usage, Max Addons, Max FTP Accounts, Max Email Lists, Max Parked Domains, Max POP Accounts, Max SQL Accounts, Max Subdomains, Creation Date, Suspend Status, Suspend Reason, Suspend Time, Max Email Per Hour, Failed Email % Before Defer, Min Failed Email # Before Defer<BR><BR><strong>DNS ZONES</strong><BR>Zone File Name, Original/Primary Source of Zone Data, Admin Email, Serial #, Refresh, Retry, Expiry, Minimum TTL, Authoritative Name Server<BR><BR><strong>DNS RECORDS</strong><BR>TTL, Class, Type, IP Address, CNAME, Mail Server, Mail Server Priority, TXT Data, Line # of Zone, # of Lines, RAW Data<BR><BR><font class=\"default_highlight\">NOTE:</font> Importing your server into the data warehouse will not modify any of your Domain Manager data.', '2013-06-01 1:00:00', '2013-06-01 1:00:00')";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
		
		$sql = "SELECT id
				FROM `updates`
				WHERE name = 'Domain Manager now includes a Data Warehouse for importing data'
				  AND insert_time = '2013-06-01 1:00:00'";
		$result = mysqli_query($connection, $sql);
		while ($row = mysqli_fetch_object($result)) { $temp_update_id = $row->id; }
		
		$sql = "SELECT id
				FROM users";
		$result = mysqli_query($connection, $sql);
		
		while ($row = mysqli_fetch_object($result)) {
		
			$sql_insert = "INSERT INTO 
						   update_data
						   (user_id, update_id, insert_time) VALUES 
						   ('" . $row->id . "', '" . $temp_update_id . "', '" . mysqli_real_escape_string($connection, $time->time()) . "')";
			$result_insert = mysqli_query($connection, $sql_insert);
		
		}
		
		$_SESSION['are_there_updates'] = "1";
		*/

		$sql = "UPDATE settings
				SET db_version = '2.0049',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$current_db_version = 2.0049;

	}

	// upgrade database from 2.0049 to 2.005
	if ($current_db_version == 2.0049) {

        // This section was made redundant by DB update v2.0051
        /*
		$sql = "DROP TABLE IF EXISTS `updates`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "DROP TABLE IF EXISTS `update_data`;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "CREATE TABLE IF NOT EXISTS `updates` (
				`id` int(10) NOT NULL auto_increment,
				`name` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`update` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
				`insert_time` datetime NOT NULL,
				`update_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
				) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "INSERT INTO updates
				(name, `update`, insert_time, update_time) VALUES
				('" . $software_title . " now contains a Software Updates section!', '<em>[This feature was implemented on 2013-05-04, but it seemed appropriate that the very first post in the Software Updates section be information about the new section itself, so the post was duplicated and backdated]</em><BR><BR>After upgrading " . $software_title . " I\'m sure it would be nice to know what new features have been added, as well as any important changes to the software that you should know about, so I\'ve added a Software Updates section that chronicles the most important and most useful new features. Now after an upgrade you can simply visit the Software Updates section and view a list of the updates since your previous version.', '2013-03-20 00:00:00', '2013-03-20 00:00:00'),
				('Support has been added for automatic currency updates!', 'Thanks to Yahoo! Finance\'s free API, I\'m happy to announce that currency conversions have been completely automated. Now instead of having to manually update the conversions one-by-one on a regular basis to ensure proper financial reporting, all you have to do is make sure your default currency is set and your conversion rates will be updated automatically and seemlessly in the background while you use the software.<BR><BR>To say that this feature pleases me would be a huge understatement. I personally use " . $software_title . " on a daily basis, and updating the currency conversions manually was always such a boring, tedious task, and I\'m happy that nobody will ever have to go through that process ever again. If I could give Yahoo! Finance a big hug, I would.', '2013-03-20 00:00:01', '2013-03-20 00:00:01'),
				('A new \'IP Address\' section has been added to the UI so that you can keep track of all your IP Addresses within " . $software_title . "', '', '2013-03-26 00:00:00', '2013-03-26 00:00:00'),
				('Test Data System removed, Demo launched', 'In order to focus on the development of the actual " . $software_title . " software, I\'ve decided to remove the Test Data System entirely. Although this system allowed users to easily generate some test data and get a feel for the software, it complicated the development process and added unecessary overhead to the software as a whole. Most importantly, it took me away from adding other, more useful features to the core software.<BR><BR>Now instead of testing the software by installing it and generating the test data, you can simply visit <a class=\"invisiblelink\" target=\"_blank\" href=\"http://demo.domainmod.org\">http://demo.domainmod.org</a> to take " . $software_title . " for a test drive.', '2013-04-06 00:00:00', '2013-04-06 00:00:00'),
				('Update the Segments UI to give the user a lot more information and flexibility', 'Now when filtering your domains using a segment, " . $software_title . " will tell you which domains in the segment are stored in " . $software_title . " (indicating whether or not the domain is active or inactive), as well as which domains don\'t match, and lastly it will tell you which domains matched but were filtered out based on your other search criteria. Each of the resulting lists can be easily viewed and exported for your convenience.<BR><BR>It took quite a bit of work to get this feature implemented, but the segment filtering just felt incomplete without it. It was still a very useful feature, but now it\'s incredibly powerful, and I hope to add on the functionality in the future.', '2013-04-07 00:00:00', '2013-04-07 00:00:00'),
				('The Domain & SSL search pages have been updated to allow for the exporting of results', '', '2013-04-07 00:00:00', '2013-04-07 00:00:00'),
				('A logo has now been added to " . $software_title . " in order to pretty things up a little bit', '', '2013-04-10 00:00:00', '2013-04-10 00:00:00'),
				('Cron job added for sending an email to users about upcoming Domain and SSL Certificate renewals', 'A cron job has now been added to send a daily email to users letting them know about upcoming domain and SSL expirations, and users can subscribe and unsubscribe from this email through their Control Panel.<BR><BR>If you would like to use this feature, just let your system administrator know so that they can schedule the cron job to run.', '2013-04-15 00:00:00', '2013-04-15 00:00:00'),
				('A new \'Web Hosting\' section has been added to the UI so that you can now keep track of your web hosting providers within " . $software_title . "', '', '2013-04-17 00:00:00', '2013-04-17 00:00:00'),
				('A password field has now been added to Registrar & SSL Provider accounts so that passwords can be managed through " . $software_title . "', '', '2013-04-19 00:00:00', '2013-04-19 00:00:00'),
				('Update the expiration email so that the System Adminstrator can set the number of days in the future to display in the email', 'Previously when the daily expiration emails were sent out to users they would automatically include the next 60 days of expirations, but this has now been converted to a system setting so that your system administrator can now specify the number of days to include in the email.', '2013-04-19 00:00:01', '2013-04-19 00:00:01'),
				('Remove the (redundant) Domain Status and Status Notes fields', 'Although the Domain Status & Status Notes fields were removed because they were redundant, if you had data stored in either of these fields it would have been appended to the primary Notes field when your database was upgraded. So don\'t worry, dropping these two fields didn\'t cause you to lose any data.', '2013-04-20 00:00:00', '2013-04-20 00:00:00'),
				('Added a \'view full notes\' feature to the Domain and SSL Cert edit pages', 'When editing a Domain or SSL certificate, if you want to view the notes but scrolling through the text box just isn\'t your thing, you can now click on a link to view the full notes on a separate page, making them much easier to read.', '2013-04-24 00:00:00', '2013-04-24 00:00:00'),
				('Reporting section added', '" . $software_title . " now includes a handful of reports that can give you valuable insight into your data, and I\'m always on the lookout for more reports that can be added. If you have any new report ideas, or any suggestions for the current reports, feel free to drop me a line at <a class=\"invisiblelink\" href=\"mailto:greg@chetcuti.com\">greg@chetcuti.com</a>.', '2013-04-25 00:00:00', '2013-04-25 00:00:00'),
				('Cron job added for automating currency conversions at regular intervals', 'Never worry about having outdated exchange rates again! " . $software_title . " now includes a cron job that automates currency conversions. This means you can have the cron job set to run overnight, and when you go to use " . $software_title . " in the morning your currency conversions will already be completely up-to-date.<BR><BR>If you would like to use this feature, just let your system administrator know so that they can schedule the cron job to run.', '2013-04-27 00:00:00', '2013-04-27 00:00:00'),
				('" . $software_title . " has been converted to UTF-8', 'The entire " . $software_title . " system has been converted to use the UTF-8 character set in order to allow for support of non-ASCII characters, such as the characters found in some IDNs (Internationalized Domain Names).', '2013-04-27 00:00:01', '2013-04-27 00:00:01'),
				('Currencies have been updated to be user-based instead of system-based', 'Now that Currencies have been re-worked to be user-based, every user in the system can set their own default currency, and this currency will be used for them throughout the system. Every setting, webpage, and report in the " . $software_title . " system will automatically be converted to display monetary values using the user\'s default currency.', '2013-04-29 00:00:00', '2013-04-29 00:00:00'),
				('Overhaul of " . $software_title . " Settings Complete!', 'Over the past few months the " . $software_title . " settings have been undergoing a complete overhaul. The changes include but are not limited to making currency conversions user-based instead of system-based, updating all Domain & SSL default settings to be user-based instead of system-based, separating out Category, IP Address and Owner settings so that Domains & SSLs have thier own options instead of sharing them, adding support for saving passwords for Domain Registrar & SSL Provider accounts, removing the redundant Status and Status Notes fields from the Domains section, and so on.<BR><BR>I\'m constantly trying to improve the software and make it more user-friendly, so if you have any suggestions or feedback feel free to drop me a line at <a class=\"invisiblelink\" href=\"mailto:greg@chetcuti.com\">greg@chetcuti.com</a>.', '2013-05-02 00:00:00', '2013-05-02 00:00:00'),
				('" . $software_title . " now contains a Software Updates section!', 'After upgrading " . $software_title . " I\'m sure it would be nice to know what new features have been added, as well as any important changes to the software that you should know about, so I\'ve added a Software Updates section that chronicles the most important and most useful new features. Now after an upgrade you can simply visit the Software Updates section and view a list of the updates since your previous version.', '2013-05-04 00:00:00', '2013-05-04 00:00:00'),
				('An Export option has been added to all Asset pages', '', '2013-05-06 00:00:00', '2013-05-06 00:00:00'),
				('You can now create Custom Domain & SSL Fields!', 'In an effort to allow users more flexibility, as well as track as much data as possible, I\'ve implemented Custom Domain & SSL Fields. Now if there\'s information you want to track for a domain or SSL certificate but the field doesn\'t exist in " . $software_title . ", you can just add it yourself!<BR><BR>For example, if you wanted to keep track of which domains are currenty setup in Google Analytics, you could create a new Google Analytics check box field and start tracking this information for each of your domains. Or if you were working in a corporate environment and wanted to keep a record of who purchased each of your SSL certificates, you could create a Purchaser Name text field and keep track of this information for every one of your SSL certificates. Combine custom fields with the ability to update them with the Bulk Updater, and the sky\'s the limit in regards to what data you can easily track! (the Bulk Updater currently only supports domains, not SSL certificates)<BR><BR>And when you export your domain & SSL data, the information contained in your custom fields will automatically be included in the exported data.', '2013-05-25 17:00:00', '2013-05-25 17:00:00'),
				('" . $software_title . " now includes a Data Warehouse for importing data', '" . $software_title . " now has a data warehouse framework built right into it, which allows you to import the data stored on your web servers. Currently the only web servers that are supported are ones that run WHM/cPanel, but I also intend on adding support for Plesk and other systems once I’ve ironed out all the kinks in the framework.<BR><BR>The data warehouse is used for informational purposes only, and you will see its data referenced throughout " . $software_title . " where applicable. For example, if a domain you’re editing has information stored in your data warehouse, the system will automatically match them up and display the additional information for you, giving you even more insight into your data. You can also view, export, and run reports on the information in your data warehouse.<BR><BR>The following WHM data is currently supported, but my end goal is to have every piece of WHM information that can be retrieved via the API stored in the data warehouse.<BR><BR><strong>ACCOUNTS</strong><BR>Domain, IP Address, Owner, User, Contact Email, Plan, Theme, Shell, Partition, Disk Limit, Disk Usage, Max Addons, Max FTP Accounts, Max Email Lists, Max Parked Domains, Max POP Accounts, Max SQL Accounts, Max Subdomains, Creation Date, Suspend Status, Suspend Reason, Suspend Time, Max Email Per Hour, Failed Email % Before Defer, Min Failed Email # Before Defer<BR><BR><strong>DNS ZONES</strong><BR>Zone File Name, Original/Primary Source of Zone Data, Admin Email, Serial #, Refresh, Retry, Expiry, Minimum TTL, Authoritative Name Server<BR><BR><strong>DNS RECORDS</strong><BR>TTL, Class, Type, IP Address, CNAME, Mail Server, Mail Server Priority, TXT Data, Line # of Zone, # of Lines, RAW Data<BR><BR><font class=\"default_highlight\">NOTE:</font> Importing your server into the data warehouse will not modify any of your " . $software_title . " data.', '2013-06-01 1:00:00', '2013-06-01 1:00:00')";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "CREATE TABLE IF NOT EXISTS update_data (
				`id` int(10) NOT NULL auto_increment,
				`user_id` int(10) NOT NULL,
				`update_id` int(10) NOT NULL,
				`insert_time` datetime NOT NULL,
				PRIMARY KEY  (`id`)
				) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$sql = "SELECT id
				FROM users";
		$result = mysqli_query($connection, $sql);

		while ($row = mysqli_fetch_object($result)) {

			$sql_updates = "SELECT id
							FROM `updates`";
			$result_updates = mysqli_query($connection, $sql_updates);

			while ($row_updates = mysqli_fetch_object($result_updates)) {

				$sql_insert = "INSERT INTO
							   update_data
							   (user_id, update_id, insert_time) VALUES
							   ('" . $row->id . "', '" . $row_updates->id . "', '" . $time->time() . "')";
				$result_insert = mysqli_query($connection, $sql_insert);

			}

		}

		$_SESSION['are_there_updates'] = "1";

		$sql = "UPDATE settings
				SET db_version = '2.005',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        */

        $current_db_version = 2.005;

    }

    // upgrade database from 2.005 to 2.0051
    if ($current_db_version == 2.005) {

        $sql = "DROP TABLE IF EXISTS `updates`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "DROP TABLE IF EXISTS `update_data`;";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $sql = "UPDATE settings
				SET db_version = '2.0051',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0051;

    }

    // upgrade database from 2.0051 to 2.0052
    if ($current_db_version == 2.0051) {

        $sql = "ALTER TABLE `fees`
				ADD `privacy_fee` FLOAT NOT NULL AFTER `transfer_fee`";
        $result = mysqli_query($connection, $sql);

        $sql = "UPDATE settings
				SET db_version = '2.0052',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0052;

    }

    // upgrade database from 2.0052 to 2.0053
    if ($current_db_version == 2.0052) {

        $sql = "ALTER TABLE `fees`
				ADD `misc_fee` FLOAT NOT NULL AFTER `privacy_fee`";
        $result = mysqli_query($connection, $sql);

        $sql = "ALTER TABLE `ssl_fees`
				ADD `misc_fee` FLOAT NOT NULL AFTER `renewal_fee`";
        $result = mysqli_query($connection, $sql);

        $sql = "UPDATE settings
				SET db_version = '2.0053',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0053;

    }

    // upgrade database from 2.0053 to 2.0054
    if ($current_db_version == 2.0053) {

        $sql = "ALTER TABLE `domains`
				ADD `total_cost` FLOAT NOT NULL AFTER `fee_id`";
        $result = mysqli_query($connection, $sql);

        $sql = "SELECT d.id, d.fee_id, f.renewal_fee
                FROM domains AS d, fees AS f
                WHERE d.fee_id = f.id
                ORDER BY domain ASC";

        $result = mysqli_query($connection, $sql);

        while ($row = mysqli_fetch_object($result)) {

            $sql_update = "UPDATE domains
                           SET total_cost = '" . $row->renewal_fee . "'
                           WHERE id = '" . $row->id . "'
                             AND fee_id = '" . $row->fee_id . "'";
            $result_update = mysqli_query($connection, $sql_update);

        }

        $sql = "ALTER TABLE `ssl_certs`
				ADD `total_cost` FLOAT NOT NULL AFTER `fee_id`";
        $result = mysqli_query($connection, $sql);

        $sql = "SELECT s.id, s.fee_id, sf.renewal_fee
                FROM ssl_certs AS s, ssl_fees AS sf
                WHERE s.fee_id = sf.id";
        $result = mysqli_query($connection, $sql);

        while ($row = mysqli_fetch_object($result)) {

            $sql_update = "UPDATE ssl_certs
                           SET total_cost = '" . $row->renewal_fee . "'
                           WHERE id = '" . $row->id . "'
                             AND fee_id = '" . $row->fee_id . "'";
            $result_update = mysqli_query($connection, $sql_update);

        }

        $sql = "UPDATE settings
				SET db_version = '2.0054',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0054;

    }

    // upgrade database from 2.0054 to 2.0055
    if ($current_db_version == 2.0054) {

        $sql = "ALTER TABLE `user_settings`
					ADD `display_inactive_assets` INT(1) NOT NULL DEFAULT '1' AFTER `display_ssl_fee`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $_SESSION['display_inactive_assets'] = "1";

        $sql = "UPDATE settings
				SET db_version = '2.0055',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0055;

    }

    // upgrade database from 2.0055 to 2.0056
    if ($current_db_version == 2.0055) {

        $sql = "ALTER TABLE `user_settings`
					ADD `display_dw_intro_page` INT(1) NOT NULL DEFAULT '1' AFTER `display_inactive_assets`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $_SESSION['display_dw_intro_page'] = "1";

        $sql = "UPDATE settings
				SET db_version = '2.0056',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0056;

    }

    // upgrade database from 2.0056 to 2.0057
    if ($current_db_version == 2.0056) {

        $sql = "ALTER TABLE `settings`
				ADD `upgrade_available` INT(1) NOT NULL DEFAULT '0' AFTER `db_version`";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $_SESSION['system_upgrade_available'] = "0";

        $sql = "UPDATE settings
				SET db_version = '2.0057',
					update_time = '" . mysqli_real_escape_string($connection, $time->time()) . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $current_db_version = 2.0057;

    }

    $_SESSION['system_upgrade_available'] = "0";

    $sql = "UPDATE settings
            SET upgrade_available = '0'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $_SESSION['result_message'] .= "Your Database Has Been Updated<BR>";

} else {

    $_SESSION['result_message'] .= "Your Database is already up-to-date<BR>";

}
