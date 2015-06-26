<?php
/**
 * /assets/dns.php
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
include("../_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$error = new DomainMOD\Error();
$system = new DomainMOD\System();
$time = new DomainMOD\Timestamp();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();

$page_title = "DNS Profiles";
$software_section = "dns";

$export_data = $_GET['export_data'];

$sql = "SELECT id, name, number_of_servers, dns1, dns2, dns3, dns4, dns5, dns6, dns7, dns8, dns9, dns10, ip1, ip2, ip3, ip4, ip5, ip6, ip7, ip8, ip9, ip10, notes, insert_time, update_time
		FROM dns
		WHERE id IN (SELECT dns_id 
					 FROM domains 
					 WHERE dns_id != '0' 
					   AND active NOT IN ('0','10') 
					   GROUP BY dns_id)
		ORDER BY name, number_of_servers desc";

if ($export_data == "1") {

	$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $export = new DomainMOD\Export();
    $export_file = $export->openFile('dns_profile_list', strtotime($time->time()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        'Status',
        'DNS Profile',
        'DNS Servers',
        'Domains',
        'Default DNS Profile?',
        'DNS Server 1',
        'IP Address 1',
        'DNS Server 2',
        'IP Address 2',
        'DNS Server 3',
        'IP Address 3',
        'DNS Server 4',
        'IP Address 4',
        'DNS Server 5',
        'IP Address 5',
        'DNS Server 6',
        'IP Address 6',
        'DNS Server 7',
        'IP Address 7',
        'DNS Server 8',
        'IP Address 8',
        'DNS Server 9',
        'IP Address 9',
        'DNS Server 10',
        'IP Address 10',
        'Notes',
        'Inserted',
        'Updated'
    );
    $export->writeRow($export_file, $row_contents);

    if (mysqli_num_rows($result) > 0) {

		$has_active = "1";

		while ($row = mysqli_fetch_object($result)) {

			$new_dnsid = $row->id;
		
			if ($current_dnsid != $new_dnsid) {
				$exclude_dns_string_raw .= "'" . $row->id . "', ";
			}
	
			$sql_total_count = "SELECT count(*) AS total_count
								FROM domains
								WHERE dns_id = '" . $row->id . "'
								  AND active NOT IN ('0', '10')";
			$result_total_count = mysqli_query($connection, $sql_total_count);
			while ($row_total_count = mysqli_fetch_object($result_total_count)) {
				$total_dns_count = $row_total_count->total_count;
			}
	
			if ($row->id == $_SESSION['default_dns']) {
			
				$is_default = "1";
				
			} else {
			
				$is_default = "";
			
			}

            $row_contents = array(
                'Active',
                $row->name,
                number_format($row->number_of_servers),
                number_format($total_dns_count),
                $is_default,
                $row->dns1,
                $row->ip1,
                $row->dns2,
                $row->ip2,
                $row->dns3,
                $row->ip3,
                $row->dns4,
                $row->ip4,
                $row->dns5,
                $row->ip5,
                $row->dns6,
                $row->ip6,
                $row->dns7,
                $row->ip7,
                $row->dns8,
                $row->ip8,
                $row->dns9,
                $row->ip9,
                $row->dns10,
                $row->ip10,
                $row->notes,
                $row->insert_time,
                $row->update_time
            );
            $export->writeRow($export_file, $row_contents);

            $current_dnsid = $row->id;

		}

	}

	$exclude_dns_string = substr($exclude_dns_string_raw, 0, -2); 
	
	if ($exclude_dns_string == "") {
	
		$sql = "SELECT id, name, number_of_servers, dns1, dns2, dns3, dns4, dns5, dns6, dns7, dns8, dns9, dns10, ip1, ip2, ip3, ip4, ip5, ip6, ip7, ip8, ip9, ip10, notes, insert_time, update_time
				FROM dns
				ORDER BY name, number_of_servers desc";
	
	} else {
	
		$sql = "SELECT id, name, number_of_servers, dns1, dns2, dns3, dns4, dns5, dns6, dns7, dns8, dns9, dns10, ip1, ip2, ip3, ip4, ip5, ip6, ip7, ip8, ip9, ip10, notes, insert_time, update_time
				FROM dns
				WHERE id NOT IN (" . $exclude_dns_string . ")
				ORDER BY name, number_of_servers desc";
	
	}
	
	$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
	
	if (mysqli_num_rows($result) > 0) { 
	
		$has_inactive = "1";
	
		while ($row = mysqli_fetch_object($result)) {
	
			if ($row->id == $_SESSION['default_dns']) {
			
				$is_default = "1";
				
			} else {
			
				$is_default = "";
			
			}

            $row_contents = array(
                'Inactive',
                $row->name,
                number_format($row->number_of_servers),
                '0',
                $is_default,
                $row->dns1,
                $row->ip1,
                $row->dns2,
                $row->ip2,
                $row->dns3,
                $row->ip3,
                $row->dns4,
                $row->ip4,
                $row->dns5,
                $row->ip5,
                $row->dns6,
                $row->ip6,
                $row->dns7,
                $row->ip7,
                $row->dns8,
                $row->ip8,
                $row->dns9,
                $row->ip9,
                $row->dns10,
                $row->ip10,
                $row->notes,
                $row->insert_time,
                $row->update_time
            );
            $export->writeRow($export_file, $row_contents);

        }

	}

    $export->closeFile($export_file);
    exit;

}
?>
<?php echo $system->doctype(); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
Below is a list of all the DNS Profiles that are stored in <?php echo $software_title; ?>.<BR><BR>
[<a href="dns.php?export_data=1">EXPORT</a>]<?php

$result = mysqli_query($connection, $sql);

if (mysqli_num_rows($result) > 0) {

	$has_active = "1"; ?>
	<table class="main_table" cellpadding="0" cellspacing="0">
    <tr class="main_table_row_heading_active">
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Active Profiles (<?php echo mysqli_num_rows($result); ?>)</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Servers</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Domains</font>
        </td>
    </tr><?php 
    
    while ($row = mysqli_fetch_object($result)) {

	    $new_dnsid = $row->id;
    
        if ($current_dnsid != $new_dnsid) {
			$exclude_dns_string_raw .= "'" . $row->id . "', ";
		} ?>

        <tr class="main_table_row_active">
            <td class="main_table_cell_active">
                <a class="invisiblelink" href="edit/dns.php?dnsid=<?php echo $row->id; ?>"><?php echo $row->name; ?></a><?php if ($_SESSION['default_dns'] == $row->id) echo "<a title=\"Default DNS Profile\"><font class=\"default_highlight\">*</font></a>"; ?>
            </td>
            <td class="main_table_cell_active">
                <a class="invisiblelink" href="edit/dns.php?dnsid=<?php echo $row->id; ?>"><?php echo $row->number_of_servers; ?></a>
            </td>
            <td class="main_table_cell_active"><?php
                $sql_total_count = "SELECT count(*) AS total_count
                                    FROM domains
                                    WHERE dns_id = '" . $row->id . "'
                                      AND active NOT IN ('0', '10')";
                $result_total_count = mysqli_query($connection, $sql_total_count);
                while ($row_total_count = mysqli_fetch_object($result_total_count)) {
                    $total_dns_count = $row_total_count->total_count;
                } ?>
                <a class="nobold" href="../domains.php?dnsid=<?php echo $row->id; ?>"><?php echo number_format($total_dns_count); ?></a>
            </td>
        </tr><?php 

		$current_dnsid = $row->id;

    }

}

if ($_SESSION['display_inactive_assets'] == "1") {

    $exclude_dns_string = substr($exclude_dns_string_raw, 0, -2);

    if ($exclude_dns_string == "") {

        $sql = "SELECT id, name, number_of_servers
                FROM dns
                ORDER BY name, number_of_servers DESC";

    } else {

        $sql = "SELECT id, name, number_of_servers
                FROM dns
                WHERE id NOT IN (" . $exclude_dns_string . ")
                ORDER BY name, number_of_servers DESC";

    }

    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    if (mysqli_num_rows($result) > 0) {

        $has_inactive = "1";
        if ($has_active == "1") echo "<BR>";
        if ($has_active != "1" && $has_inactive == "1") echo "<table class=\"main_table\" cellpadding=\"0\" cellspacing=\"0\">"; ?>

        <tr class="main_table_row_heading_inactive">
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">Inactive Profiles (<?php echo mysqli_num_rows($result); ?>)</font>
        </td>
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">Servers</font>
        </td>
        </tr><?php

        while ($row = mysqli_fetch_object($result)) { ?>

            <tr class="main_table_row_inactive">
            <td class="main_table_cell_inactive">
                <a class="invisiblelink"
                   href="edit/dns.php?dnsid=<?php echo $row->id; ?>"><?php echo $row->name; ?></a><?php if ($_SESSION['default_dns'] == $row->id) echo "<a title=\"Default DNS Profile\"><font class=\"default_highlight\">*</font></a>"; ?>
            </td>
            <td class="main_table_cell_inactive">
                <a class="invisiblelink"
                   href="edit/dns.php?dnsid=<?php echo $row->id; ?>"><?php echo $row->number_of_servers; ?></a>
            </td>
            </tr><?php

        }

    }

}

if ($has_active == "1" || $has_inactive == "1") echo "</table>";

if ($_SESSION['display_inactive_assets'] != "1") { ?>
    <BR><em>Inactive Profiles are currently not displayed. <a class="invisiblelink" href="../system/display-settings.php">Click here to display them</a>.</em><BR><?php
}

if ($has_active || $has_inactive) { ?>
		<BR><font class="default_highlight">*</font> = Default DNS Profile<?php 
}

if (!$has_active && !$has_inactive) { ?>
		<BR>You don't currently have any DNS Profiles. <a href="add/dns.php">Click here to add one</a>.<?php 
} ?>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
