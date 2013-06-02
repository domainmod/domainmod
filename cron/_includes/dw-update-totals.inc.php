<?php
// /cron/_includes/dw-update-totals.inc.php
// 
// DomainMOD - A web-based application written in PHP & MySQL used to manage a collection of domain names.
// Copyright (C) 2010 Greg Chetcuti
// 
// DomainMOD is free software; you can redistribute it and/or modify it under the terms of the GNU General
// Public License as published by the Free Software Foundation; either version 2 of the License, or (at your
// option) any later version.
// 
// DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the
// implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License
// for more details.
// 
// You should have received a copy of the GNU General Public License along with DomainMOD. If not, please see
// http://www.gnu.org/licenses/
?>
<?php
$sql = "DROP TABLE IF EXISTS dw_server_totals";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql = "CREATE TABLE IF NOT EXISTS `dw_server_totals` (
		`id` int(10) NOT NULL auto_increment,
		`dw_servers` int(10) NOT NULL,
		`dw_accounts` int(10) NOT NULL,
		`dw_dns_zones` int(10) NOT NULL,
		`dw_dns_records` int(10) NOT NULL,
		`insert_time` datetime NOT NULL,
		PRIMARY KEY  (`id`)
		) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
$result = mysql_query($sql,$connection) or die(mysql_error());

$sql_get_total_dw_servers = "SELECT count(*) AS total_dw_servers
							 FROM dw_servers";
$result_get_total_dw_servers = mysql_query($sql_get_total_dw_servers,$connection);
while ($row_get_total_dw_servers = mysql_fetch_object($result_get_total_dw_servers)) $temp_total_dw_servers = $row_get_total_dw_servers->total_dw_servers;

$sql_get_total_dw_accounts = "SELECT count(*) AS total_dw_accounts
							  FROM dw_accounts";
$result_get_total_dw_accounts = mysql_query($sql_get_total_dw_accounts,$connection);
while ($row_get_total_dw_accounts = mysql_fetch_object($result_get_total_dw_accounts)) $temp_total_dw_accounts = $row_get_total_dw_accounts->total_dw_accounts;

$sql_get_total_dnz_zones = "SELECT count(*) AS total_dw_dns_zones
							FROM dw_dns_zones";
$result_get_total_dnz_zones = mysql_query($sql_get_total_dnz_zones,$connection);
while ($row_get_total_dnz_zones = mysql_fetch_object($result_get_total_dnz_zones)) $temp_total_dw_dns_zones = $row_get_total_dnz_zones->total_dw_dns_zones;

$sql_get_total_dns_records = "SELECT count(*) AS total_dw_dns_records
							  FROM dw_dns_records";
$result_get_total_dns_records = mysql_query($sql_get_total_dns_records,$connection);
while ($row_get_total_dns_records = mysql_fetch_object($result_get_total_dns_records)) $temp_total_dw_dns_records = $row_get_total_dns_records->total_dw_dns_records;

$sql_insert_dw_totals = "INSERT INTO dw_server_totals
						 (dw_servers, dw_accounts, dw_dns_zones, dw_dns_records, insert_time) VALUES 
						 ('" . $temp_total_dw_servers . "', '" . $temp_total_dw_accounts . "', '" . $temp_total_dw_dns_zones . "', '" . $temp_total_dw_dns_records . "', '" . date("Y-m-d H:i:s") . "')";
$result_insert_dw_totals = mysql_query($sql_insert_dw_totals,$connection);
?>
