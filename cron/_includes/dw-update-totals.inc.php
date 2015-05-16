<?php
/**
 * /cron/_includes/dw-update-totals.inc.php
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
$query = "DROP TABLE IF EXISTS dw_server_totals";
$q = $conn->stmt_init();

if ($q->prepare($query)) {
    $q->execute();
    $q->close();

} else { $error->outputSqlError($conn, "ERROR"); }

$query = "CREATE TABLE IF NOT EXISTS `dw_server_totals` (
          `id` int(10) NOT NULL auto_increment,
          `dw_servers` int(10) NOT NULL,
          `dw_accounts` int(10) NOT NULL,
          `dw_dns_zones` int(10) NOT NULL,
          `dw_dns_records` int(10) NOT NULL,
          `insert_time` datetime NOT NULL,
          PRIMARY KEY  (`id`)
          ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;";
$q = $conn->stmt_init();

if ($q->prepare($query)) {

    $q->execute();
    $q->close();

} else { $error->outputSqlError($conn, "ERROR"); }

$query = "SELECT count(*) AS total_dw_servers
          FROM dw_servers";
$q = $conn->stmt_init();
if ($q->prepare($query)) {
    $q->execute();
    $q->store_result();
    $q->bind_result($temp_total_dw_servers);
    $q->fetch();
    $q->close();

} else { $error->outputSqlError($conn, "ERROR"); }

$query = "SELECT count(*) AS total_dw_accounts
          FROM dw_accounts";
$q = $conn->stmt_init();

if ($q->prepare($query)) {
    $q->execute();
    $q->store_result();
    $q->bind_result($temp_total_dw_accounts);
    $q->fetch();
    $q->close();

} else { $error->outputSqlError($conn, "ERROR"); }

$query = "SELECT count(*) AS total_dw_dns_zones
          FROM dw_dns_zones";
$q = $conn->stmt_init();

if ($q->prepare($query)) {

    $q->execute();
    $q->store_result();
    $q->bind_result($temp_total_dw_dns_zones);
    $q->fetch();
    $q->close();

} else { $error->outputSqlError($conn, "ERROR"); }

$query = "SELECT count(*) AS total_dw_dns_records
          FROM dw_dns_records";
$q = $conn->stmt_init();

if ($q->prepare($query)) {

    $q->execute();
    $q->store_result();
    $q->bind_result($temp_total_dw_dns_records);
    $q->fetch();
    $q->close();

} else { $error->outputSqlError($conn, "ERROR"); }

$query = "INSERT INTO dw_server_totals
          (dw_servers, dw_accounts, dw_dns_zones, dw_dns_records, insert_time)
           VALUES
          (?, ?, ?, ?, ?)";
$q = $conn->stmt_init();

if ($q->prepare($query)) {

    $q->bind_param('iiiis', $temp_total_dw_servers, $temp_total_dw_accounts, $temp_total_dw_dns_zones,
        $temp_total_dw_dns_records, $time->time());
    $q->execute();
    $q->close();

} else { $error->outputSqlError($conn, "ERROR"); }
