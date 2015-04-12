<?php
/**
 * /system/admin/dw/servers.php
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
include("../../../_includes/start-session.inc.php");

// If the user isn't an administrator, redirect them to $full_redirect
$full_redirect = "../../../invalid.php";
include("../../../_includes/auth/admin-user-check.inc.php");

include("../../../_includes/config.inc.php");
include("../../../_includes/database.inc.php");
include("../../../_includes/software.inc.php");
include("../../../_includes/auth/auth-check.inc.php");
include("../../../_includes/timestamps/current-timestamp.inc.php");
include("../../../_includes/system/functions/error-reporting.inc.php");

$page_title = "Data Warehouse Servers";
$software_section = "admin-dw-manage-servers";

$export = $_GET['export'];

$sql = "SELECT id, name, host, protocol, port, username, hash, notes, dw_accounts, dw_dns_zones, dw_dns_records, build_end_time, insert_time, update_time
		FROM dw_servers
		ORDER BY name, host";

if ($export == "1") {

	$result = mysqli_query($connection, $sql) or outputOldSqlError($connection);

	$current_timestamp_unix = strtotime($current_timestamp);
	$export_filename = "dw_servers_" . $current_timestamp_unix . ".csv";
	include("../../../_includes/system/export/header.inc.php");

	$row_content[$count++] = $page_title;
	include("../../../_includes/system/export/write-row.inc.php");

	fputcsv($file_content, $blank_line);

	$row_content[$count++] = "Name";
	$row_content[$count++] = "Host";
	$row_content[$count++] = "Protocol";
	$row_content[$count++] = "Port";
	$row_content[$count++] = "Username";
	$row_content[$count++] = "Hash";
	$row_content[$count++] = "Notes";
	$row_content[$count++] = "DW Accounts";
	$row_content[$count++] = "DW DNS Zones";
	$row_content[$count++] = "DW DNS Records";
	$row_content[$count++] = "DW Last Built";
	$row_content[$count++] = "Inserted";
	$row_content[$count++] = "Updated";
	include("../../../_includes/system/export/write-row.inc.php");

	if (mysqli_num_rows($result) > 0) {
	
		while ($row = mysqli_fetch_object($result)) {

			$row_content[$count++] = $row->name;
			$row_content[$count++] = $row->host;
			$row_content[$count++] = $row->protocol;
			$row_content[$count++] = $row->port;
			$row_content[$count++] = $row->username;
			$row_content[$count++] = $row->hash;
			$row_content[$count++] = $row->notes;
			$row_content[$count++] = $row->dw_accounts;
			$row_content[$count++] = $row->dw_dns_zones;
			$row_content[$count++] = $row->dw_dns_records;
			$row_content[$count++] = $row->build_end_time;
			$row_content[$count++] = $row->insert_time;
			$row_content[$count++] = $row->update_time;
			include("../../../_includes/system/export/write-row.inc.php");
	
		}
			
	}

	include("../../../_includes/system/export/footer.inc.php");

}
?>
<?php include("../../../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include("../../../_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../../../_includes/layout/header.inc.php"); ?>
<?php
$result = mysqli_query($connection, $sql) or outputOldSqlError($connection);

if (mysqli_num_rows($result) > 0) { ?>

	[<a href="<?php echo $PHP_SELF; ?>?export=1">EXPORT</a>]

    <table class="main_table" cellpadding="0" cellspacing="0">
        <tr class="main_table_row_heading_active">
            <td class="main_table_cell_heading_active">
                <font class="main_table_heading">Name</font>
            </td>
            <td class="main_table_cell_heading_active">
                <font class="main_table_heading">Host</font>
            </td>
            <td class="main_table_cell_heading_active">
                <font class="main_table_heading">Port</font>
            </td>
            <td class="main_table_cell_heading_active">
                <font class="main_table_heading">Username</font>
            </td>
            <td class="main_table_cell_heading_active">
                <font class="main_table_heading">Inserted</font>
            </td>
            <td class="main_table_cell_heading_active">
                <font class="main_table_heading">Updated</font>
            </td>
        </tr><?php
    
        while ($row = mysqli_fetch_object($result)) { ?>
    
            <tr class="main_table_row_active">
                <td class="main_table_cell_active">
                    <a class="invisiblelink" href="edit/server.php?dwsid=<?php echo $row->id; ?>"><?php echo $row->name; ?></a>
                </td>
                <td class="main_table_cell_active">
                    <a class="invisiblelink" href="edit/server.php?dwsid=<?php echo $row->id; ?>"><?php echo $row->protocol; ?>://<?php echo $row->host; ?></a>
                </td>
                <td class="main_table_cell_active">
                    <a class="invisiblelink" href="edit/server.php?dwsid=<?php echo $row->id; ?>"><?php echo $row->port; ?></a>
                </td>
                <td class="main_table_cell_active">
                    <a class="invisiblelink" href="edit/server.php?dwsid=<?php echo $row->id; ?>"><?php echo $row->username; ?></a>
                </td>
                <td class="main_table_cell_active">
                	<?php if ($row->insert_time == "0000-00-00 00:00:00") $row->insert_time = "-"; ?>
                    <a class="invisiblelink" href="edit/server.php?dwsid=<?php echo $row->id; ?>"><?php echo $row->insert_time; ?></a>
                </td>
                <td class="main_table_cell_active">
                	<?php if ($row->update_time == "0000-00-00 00:00:00") $row->update_time = "-"; ?>
                    <a class="invisiblelink" href="edit/server.php?dwsid=<?php echo $row->id; ?>"><?php echo $row->update_time; ?></a>
                </td>
            </tr><?php 

        } ?>
	
	</table><?php

} else {

	echo "You don't currently have any servers setup. <a href=\"add/server.php\">Click here to add one</a>.";
	
}
?>
<?php include("../../../_includes/layout/footer.inc.php"); ?>
</body>
</html>
