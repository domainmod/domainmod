<?php
/**
 * /assets/ip-addresses.php
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
include("../_includes/start-session.inc.php");
include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/auth/auth-check.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");
include("../_includes/system/functions/error-reporting.inc.php");

$page_title = "Domain & SSL IP Addresses";
$software_section = "ip-addresses";

$export = $_GET['export'];

$sql = "(SELECT ip.id, ip.name, ip.ip, ip.rdns, ip.notes, ip.insert_time, ip.update_time
		 FROM ip_addresses AS ip, domains AS d
		 WHERE ip.id = d.ip_id
		   AND d.active NOT IN ('0', '10')
		 GROUP BY ip.name)
		UNION
		(SELECT ip.id, ip.name, ip.ip, ip.rdns, ip.notes, ip.insert_time, ip.update_time
		 FROM ip_addresses AS ip, ssl_certs AS sslc
		 WHERE ip.id = sslc.ip_id
		   AND sslc.active NOT IN ('0')
		 GROUP BY ip.name)
		ORDER BY name";

if ($export == "1") {

	$result = mysqli_query($connection, $sql) or OutputOldSQLError($connection);

	$current_timestamp_unix = strtotime($current_timestamp);
	$export_filename = "ip_address_list_" . $current_timestamp_unix . ".csv";
	include("../_includes/system/export/header.inc.php");

	$row_content[$count++] = $page_title;
	include("../_includes/system/export/write-row.inc.php");

	fputcsv($file_content, $blank_line);

	$row_content[$count++] = "Status";
	$row_content[$count++] = "IP Address Name";
	$row_content[$count++] = "IP Address";
	$row_content[$count++] = "rDNS";
	$row_content[$count++] = "Domains";
	$row_content[$count++] = "SSL Certs";
	$row_content[$count++] = "Default Domain IP Address?";
	$row_content[$count++] = "Default SSL IP Address?";
	$row_content[$count++] = "Notes";
	$row_content[$count++] = "Inserted";
	$row_content[$count++] = "Updated";
	include("../_includes/system/export/write-row.inc.php");

	if (mysqli_num_rows($result) > 0) {
	
		$has_active = "1";
	
		while ($row = mysqli_fetch_object($result)) {
	
			$new_ipid = $row->id;

			if ($current_ipid != $new_ipid) {
				$exclude_ip_address_string_raw .= "'" . $row->id . "', ";
			}
	
			$sql_total_count = "SELECT count(*) AS total_count
								FROM domains
								WHERE active NOT IN ('0', '10')
								  AND ip_id = '" . $row->id . "'";
			$result_total_count = mysqli_query($connection, $sql_total_count);
			while ($row_total_count = mysqli_fetch_object($result_total_count)) { 
				$total_domains = $row_total_count->total_count; 
			}
	
			$sql_total_count = "SELECT count(*) AS total_count
								FROM ssl_certs
								WHERE active NOT IN ('0')
								  AND ip_id = '" . $row->id . "'";
			$result_total_count = mysqli_query($connection, $sql_total_count);
			while ($row_total_count = mysqli_fetch_object($result_total_count)) { 
				$total_certs = $row_total_count->total_count; 
			}
	
			if ($row->id == $_SESSION['default_ip_address_domains']) {
			
				$is_default_domains = "1";
				
			} else {
			
				$is_default_domains = "";
			
			}
	
			if ($row->id == $_SESSION['default_ip_address_ssl']) {
			
				$is_default_ssl = "1";
				
			} else {
			
				$is_default_ssl = "";
			
			}

			$row_content[$count++] = "Active";
			$row_content[$count++] = $row->name;
			$row_content[$count++] = $row->ip;
			$row_content[$count++] = $row->rdns;
			$row_content[$count++] = $total_domains;
			$row_content[$count++] = $total_certs;
			$row_content[$count++] = $is_default_domains;
			$row_content[$count++] = $is_default_ssl;
			$row_content[$count++] = $row->notes;
			$row_content[$count++] = $row->insert_time;
			$row_content[$count++] = $row->update_time;
			include("../_includes/system/export/write-row.inc.php");
	
			$current_ipid = $row->id;
	
		}
	
	}
	
	$exclude_ip_address_string = substr($exclude_ip_address_string_raw, 0, -2); 
	
	if ($exclude_ip_address_string == "") {
	
		$sql = "SELECT id, name, ip, rdns, notes, insert_time, update_time
				FROM ip_addresses
				ORDER BY name ASC, ip ASC";
	
	} else {
	
		$sql = "SELECT id, name, ip, rdns, notes, insert_time, update_time
				FROM ip_addresses
				WHERE id NOT IN (" . $exclude_ip_address_string . ")
				ORDER BY name ASC, ip ASC";
	
	}
	
	$result = mysqli_query($connection, $sql) or OutputOldSQLError($connection);
	
	if (mysqli_num_rows($result) > 0) {
		
		$has_inactive = "1";
	
		while ($row = mysqli_fetch_object($result)) {
	
			if ($row->id == $_SESSION['default_ip_address_domains']) {
			
				$is_default_domains = "1";
				
			} else {
			
				$is_default_domains = "";
			
			}
	
			if ($row->id == $_SESSION['default_ip_address_ssl']) {
			
				$is_default_ssl = "1";
				
			} else {
			
				$is_default_ssl = "";
			
			}

			$row_content[$count++] = "Inactive";
			$row_content[$count++] = $row->name;
			$row_content[$count++] = $row->ip;
			$row_content[$count++] = $row->rdns;
			$row_content[$count++] = 0;
			$row_content[$count++] = 0;
			$row_content[$count++] = $is_default_domains;
			$row_content[$count++] = $is_default_ssl;
			$row_content[$count++] = $row->notes;
			$row_content[$count++] = $row->insert_time;
			$row_content[$count++] = $row->update_time;
			include("../_includes/system/export/write-row.inc.php");
	
		}
	
	}

	include("../_includes/system/export/footer.inc.php");

}
?>
<?php include("../_includes/doctype.inc.php"); ?>
<html>
<head>	
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include("../_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../_includes/layout/header.inc.php"); ?>
Below is a list of all the IP Addresses that are stored in <?php echo $software_title; ?>.<BR><BR>
[<a href="<?php echo $PHP_SELF; ?>?export=1">EXPORT</a>]<?php

$result = mysqli_query($connection, $sql) or OutputOldSQLError($connection);

if (mysqli_num_rows($result) > 0) {

	$has_active = "1"; ?>
	<table class="main_table" cellpadding="0" cellspacing="0">
	<tr class="main_table_row_heading_active">
		<td class="main_table_cell_heading_active">
			<font class="main_table_heading">Active IP Names (<?php echo mysqli_num_rows($result); ?>)</font>
		</td>
		<td class="main_table_cell_heading_active">
			<font class="main_table_heading">IP Address</font>
		</td>
		<td class="main_table_cell_heading_active">
			<font class="main_table_heading">rDNS</font>
		</td>
		<td class="main_table_cell_heading_active">
			<font class="main_table_heading">Domains</font>
		</td>
		<td class="main_table_cell_heading_active">
			<font class="main_table_heading">SSL Certs</font>
		</td>
	</tr><?php 
	
	while ($row = mysqli_fetch_object($result)) {

		$new_ipid = $row->id;
	
		if ($current_ipid != $new_ipid) {
			$exclude_ip_address_string_raw .= "'" . $row->id . "', ";
		} ?>

        <tr class="main_table_row_active">
            <td class="main_table_cell_active">
                <a class="invisiblelink" href="edit/ip-address.php?ipid=<?php echo $row->id; ?>"><?php echo $row->name; ?></a><?php if ($_SESSION['default_ip_address_domains'] == $row->id) echo "<a title=\"Default Domain IP Address\"><font class=\"default_highlight\">*</font></a>"; ?><?php if ($_SESSION['default_ip_address_ssl'] == $row->id) echo "<a title=\"Default SSL IP Address\"><font class=\"default_highlight_secondary\">*</font></a>"; ?>
            </td>
            <td class="main_table_cell_active">
                <a class="invisiblelink" href="edit/ip-address.php?ipid=<?php echo $row->id; ?>"><?php echo $row->ip; ?></a>
            </td>
            <td class="main_table_cell_active">
                <a class="invisiblelink" href="edit/ip-address.php?ipid=<?php echo $row->id; ?>"><?php echo $row->rdns; ?></a>
            </td>
            <td class="main_table_cell_active"><?php
				$sql_total_count = "SELECT count(*) AS total_count
									FROM domains
									WHERE active NOT IN ('0', '10')
									  AND ip_id = '" . $row->id . "'";
				$result_total_count = mysqli_query($connection, $sql_total_count);
				while ($row_total_count = mysqli_fetch_object($result_total_count)) { 
					$total_domains = $row_total_count->total_count; 
				}
				
				if ($total_domains >= 1) { ?>
			
					<a class="nobold" href="../domains.php?ipid=<?php echo $row->id; ?>"><?php echo number_format($total_domains); ?></a><?php
					
				} else {
					
					echo "-";
					
				} ?>
            </td>
            <td class="main_table_cell_active"><?php
				$sql_total_count = "SELECT count(*) AS total_count
									FROM ssl_certs
									WHERE active NOT IN ('0')
									  AND ip_id = '" . $row->id . "'";
				$result_total_count = mysqli_query($connection, $sql_total_count);
				while ($row_total_count = mysqli_fetch_object($result_total_count)) { 
					$total_certs = $row_total_count->total_count; 
				}
				
				if ($total_certs >= 1) { ?>
			
					<a class="nobold" href="../ssl-certs.php?sslipid=<?php echo $row->id; ?>"><?php echo number_format($total_certs); ?></a><?php
					
				} else {
					
					echo "-";
					
				} ?>
            </td>
        </tr><?php 

		$current_ipid = $row->id;

	}

}

if ($_SESSION['display_inactive_assets'] == "1") {

    $exclude_ip_address_string = substr($exclude_ip_address_string_raw, 0, -2);

    if ($exclude_ip_address_string == "") {

        $sql = "SELECT id, name, ip, rdns
                FROM ip_addresses
                ORDER BY name ASC, ip ASC";

    } else {

        $sql = "SELECT id, name, ip, rdns
                FROM ip_addresses
                WHERE id NOT IN (" . $exclude_ip_address_string . ")
                ORDER BY name ASC, ip ASC";

    }

    $result = mysqli_query($connection, $sql) or OutputOldSQLError($connection);

    if (mysqli_num_rows($result) > 0) {

        $has_inactive = "1";
        if ($has_active == "1") echo "<BR>";
        if ($has_active != "1" && $has_inactive == "1") echo "<table class=\"main_table\" cellpadding=\"0\" cellspacing=\"0\">"; ?>

        <tr class="main_table_row_heading_inactive">
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">Inactive IP Names (<?php echo mysqli_num_rows($result); ?>)</font>
        </td>
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">IP Address</font>
        </td>
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">rDNS</font>
        </td>
        </tr><?php

        while ($row = mysqli_fetch_object($result)) { ?>

            <tr class="main_table_row_inactive">
            <td class="main_table_cell_inactive">
                <a class="invisiblelink"
                   href="edit/ip-address.php?ipid=<?php echo $row->id; ?>"><?php echo $row->name; ?></a><?php if ($_SESSION['default_ip_address_domains'] == $row->id) echo "<a title=\"Default Domain IP Address\"><font class=\"default_highlight\">*</font></a>"; ?><?php if ($_SESSION['default_ip_address_ssl'] == $row->id) echo "<a title=\"Default SSL IP Address\"><font class=\"default_highlight_secondary\">*</font></a>"; ?>
            </td>
            <td class="main_table_cell_inactive">
                <a class="invisiblelink"
                   href="edit/ip-address.php?ipid=<?php echo $row->id; ?>"><?php echo $row->ip; ?></a>
            </td>
            <td class="main_table_cell_inactive">
                <a class="invisiblelink"
                   href="edit/ip-address.php?ipid=<?php echo $row->id; ?>"><?php echo $row->rdns; ?></a>
            </td>
            </tr><?php

        }

    }

}

if ($has_active == "1" || $has_inactive == "1") echo "</table>";

if ($_SESSION['display_inactive_assets'] != "1") { ?>
    <BR><em>Inactive IP Addresses are currently not displayed. <a class="invisiblelink" href="../system/display-settings.php">Click here to display them</a>.</em><BR><?php
}

if ($has_active || $has_inactive) { ?>
	<BR><font class="default_highlight">*</font> = Default Domain IP Address&nbsp;&nbsp;<font class="default_highlight_secondary">*</font> = Default SSL IP Address<?php 
}

if (!$has_active && !$has_inactive) { ?>
	<BR><BR>You don't currently have any IP Addresses. <a href="add/ip-address.php">Click here to add one</a>.<?php 
} ?>
<?php include("../_includes/layout/footer.inc.php"); ?>
</body>
</html>
