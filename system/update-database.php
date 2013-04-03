<?php
// update-database.php
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
session_start();

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

	include("../_includes/auth/login-checks/database-version-check.inc.php");

	$_SESSION['session_result_message'] .= "Database Updated<BR>";

} else {

	$_SESSION['session_result_message'] .= "Your database is already up-to-date<BR>";
	
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
?>