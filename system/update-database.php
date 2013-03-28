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

$sql = "select db_version
		from settings";
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
		
		$sql = "update settings
				set db_version = '1.2', 
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
		
		$sql = "update settings
				set db_version = '1.3', 
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.3;
		
	}

	// upgrade database from 1.3 to 1.4
	if ($current_db_version == 1.3) {

		$sql = "ALTER TABLE `ip_addresses` 
				ADD `notes` longtext NOT NULL AFTER `ip`";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$sql = "update settings
				set db_version = '1.4', 
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.4;
		
	}

	// upgrade database from 1.4 to 1.5
	if ($current_db_version == 1.4) {

		$sql = "ALTER TABLE `domains`  
				ADD `ip_id` int(10) NOT NULL default '0' 
				AFTER `dns_id`";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$sql = "update settings
				set db_version = '1.5', 
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.5;
		
	}

	// upgrade database from 1.5 to 1.6
	if ($current_db_version == 1.5) {

		$sql = "ALTER TABLE `domains` 
				CHANGE `ip_id` `ip_id` INT(10) NOT NULL DEFAULT '1'";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "UPDATE `domains` SET ip_id = '1'";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "TRUNCATE `ip_addresses`";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$sql = "INSERT INTO `ip_addresses` (`id`, `name`, `ip`, `insert_time`) VALUES
										   ('1', '[no ip address]', '-', '$current_timestamp')";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$sql = "update settings
				set db_version = '1.6', 
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.6;
		
	}

	// upgrade database from 1.6 to 1.7
	if ($current_db_version == 1.6) {

		$sql = "ALTER TABLE `ssl_certs` DROP `ip`;";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$sql = "update settings
				set db_version = '1.7', 
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.7;
		
	}

	// upgrade database from 1.7 to 1.8
	if ($current_db_version == 1.7) {

		$sql = "ALTER TABLE `ip_addresses`  
				ADD `test_data` int(1) NOT NULL default '0' AFTER `notes`";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$sql = "update settings
				set db_version = '1.8', 
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.8;
		
	}

	// upgrade database from 1.8 to 1.9
	if ($current_db_version == 1.8) {

		$sql = "ALTER TABLE `settings`  
				ADD `email_address` VARCHAR(255) NOT NULL AFTER `db_version`";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$sql = "update settings
				set db_version = '1.9', 
					email_address = 'code@aysmedia.com',
					update_time = '$current_timestamp'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.9;
		
	}

	$_SESSION['session_result_message'] .= "Database Updated<BR>";

} else {

	$_SESSION['session_result_message'] .= "Your database is already up-to-date<BR>";
	
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
?>