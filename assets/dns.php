<?php
// /assets/dns.php
// 
// DomainMOD is an open source application written in PHP & MySQL used to track and manage your web resources.
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
include("../_includes/start-session.inc.php");
include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/auth/auth-check.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");

$page_title = "DNS Profiles";
$software_section = "dns";

$export = $_GET['export'];

$sql = "SELECT id, name, number_of_servers, dns1, dns2, dns3, dns4, dns5, dns6, dns7, dns8, dns9, dns10, ip1, ip2, ip3, ip4, ip5, ip6, ip7, ip8, ip9, ip10, notes, insert_time, update_time
		FROM dns
		WHERE id IN (SELECT dns_id 
					 FROM domains 
					 WHERE dns_id != '0' 
					   AND active NOT IN ('0','10') 
					   GROUP BY dns_id)
		ORDER BY name, number_of_servers desc";

if ($export == "1") {

	$result = mysqli_query($connection, $sql) or die(mysqli_error());

	$current_timestamp_unix = strtotime($current_timestamp);
	$export_filename = "dns_profile_list_" . $current_timestamp_unix . ".csv";
	include("../_includes/system/export/header.inc.php");

	$row_content[$count++] = $page_title;
	include("../_includes/system/export/write-row.inc.php");

	fputcsv($file_content, $blank_line);

	$row_content[$count++] = "Status";
	$row_content[$count++] = "DNS Profile";
	$row_content[$count++] = "DNS Servers";
	$row_content[$count++] = "Domains";
	$row_content[$count++] = "Default DNS Profile?";
	$row_content[$count++] = "DNS Server 1";
	$row_content[$count++] = "IP Address 1";
	$row_content[$count++] = "DNS Server 2";
	$row_content[$count++] = "IP Address 2";
	$row_content[$count++] = "DNS Server 3";
	$row_content[$count++] = "IP Address 3";
	$row_content[$count++] = "DNS Server 4";
	$row_content[$count++] = "IP Address 4";
	$row_content[$count++] = "DNS Server 5";
	$row_content[$count++] = "IP Address 5";
	$row_content[$count++] = "DNS Server 6";
	$row_content[$count++] = "IP Address 6";
	$row_content[$count++] = "DNS Server 7";
	$row_content[$count++] = "IP Address 7";
	$row_content[$count++] = "DNS Server 8";
	$row_content[$count++] = "IP Address 8";
	$row_content[$count++] = "DNS Server 9";
	$row_content[$count++] = "IP Address 9";
	$row_content[$count++] = "DNS Server 10";
	$row_content[$count++] = "IP Address 10";
	$row_content[$count++] = "Notes";
	$row_content[$count++] = "Inserted";
	$row_content[$count++] = "Updated";
	include("../_includes/system/export/write-row.inc.php");

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

			$row_content[$count++] = "Active";
			$row_content[$count++] = $row->name;
			$row_content[$count++] = number_format($row->number_of_servers);
			$row_content[$count++] = number_format($total_dns_count);
			$row_content[$count++] = $is_default;
			$row_content[$count++] = $row->dns1;
			$row_content[$count++] = $row->ip1;
			$row_content[$count++] = $row->dns2;
			$row_content[$count++] = $row->ip2;
			$row_content[$count++] = $row->dns3;
			$row_content[$count++] = $row->ip3;
			$row_content[$count++] = $row->dns4;
			$row_content[$count++] = $row->ip4;
			$row_content[$count++] = $row->dns5;
			$row_content[$count++] = $row->ip5;
			$row_content[$count++] = $row->dns6;
			$row_content[$count++] = $row->ip6;
			$row_content[$count++] = $row->dns7;
			$row_content[$count++] = $row->ip7;
			$row_content[$count++] = $row->dns8;
			$row_content[$count++] = $row->ip8;
			$row_content[$count++] = $row->dns9;
			$row_content[$count++] = $row->ip9;
			$row_content[$count++] = $row->dns10;
			$row_content[$count++] = $row->ip10;
			$row_content[$count++] = $row->notes;
			$row_content[$count++] = $row->insert_time;
			$row_content[$count++] = $row->update_time;
			include("../_includes/system/export/write-row.inc.php");

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
	
	$result = mysqli_query($connection, $sql) or die(mysqli_error());
	
	if (mysqli_num_rows($result) > 0) { 
	
		$has_inactive = "1";
	
		while ($row = mysqli_fetch_object($result)) {
	
			if ($row->id == $_SESSION['default_dns']) {
			
				$is_default = "1";
				
			} else {
			
				$is_default = "";
			
			}

			$row_content[$count++] = "Inactive";
			$row_content[$count++] = $row->name;
			$row_content[$count++] = number_format($row->number_of_servers);
			$row_content[$count++] = 0;
			$row_content[$count++] = $is_default;
			$row_content[$count++] = $row->dns1;
			$row_content[$count++] = $row->ip1;
			$row_content[$count++] = $row->dns2;
			$row_content[$count++] = $row->ip2;
			$row_content[$count++] = $row->dns3;
			$row_content[$count++] = $row->ip3;
			$row_content[$count++] = $row->dns4;
			$row_content[$count++] = $row->ip4;
			$row_content[$count++] = $row->dns5;
			$row_content[$count++] = $row->ip5;
			$row_content[$count++] = $row->dns6;
			$row_content[$count++] = $row->ip6;
			$row_content[$count++] = $row->dns7;
			$row_content[$count++] = $row->ip7;
			$row_content[$count++] = $row->dns8;
			$row_content[$count++] = $row->ip8;
			$row_content[$count++] = $row->dns9;
			$row_content[$count++] = $row->ip9;
			$row_content[$count++] = $row->dns10;
			$row_content[$count++] = $row->ip10;
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
<title><?php echo $software_title; ?> :: <?php echo $page_title; ?></title>
<?php include("../_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../_includes/layout/header.inc.php"); ?>
Below is a list of all the DNS Profiles that are stored in your <?php echo $software_title; ?>.<BR><BR>
[<a href="<?php echo $PHP_SELF; ?>?export=1">EXPORT</a>]<?php

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

    $result = mysqli_query($connection, $sql) or die(mysqli_error());

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
<?php include("../_includes/layout/footer.inc.php"); ?>
</body>
</html>
