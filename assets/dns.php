<?php
// /assets/dns.php
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
	
	$full_export = "";
	$full_export .= "\"" . $page_title . "\"\n\n";
	$full_export .= "\"Status\",\"DNS Profile\",\"DNS Servers\",\"Domains\",\"Default DNS Profile?\",\"DNS Server 1\",\"IP Address 1\",\"DNS Server 2\",\"IP Address 2\",\"DNS Server 3\",\"IP Address 3\",\"DNS Server 4\",\"IP Address 4\",\"DNS Server 5\",\"IP Address 5\",\"DNS Server 6\",\"IP Address 6\",\"DNS Server 7\",\"IP Address 7\",\"DNS Server 8\",\"IP Address 8\",\"DNS Server 9\",\"IP Address 9\",\"DNS Server 10\",\"IP Address 10\",\"Notes\",\"Added\",\"Last Updated\"\n";

	$result = mysql_query($sql,$connection);
	
	if (mysql_num_rows($result) > 0) {
	
		$has_active = "1";
	
		while ($row = mysql_fetch_object($result)) {
	
			$new_dnsid = $row->id;
		
			if ($current_dnsid != $new_dnsid) {
				$exclude_dns_string_raw .= "'" . $row->id . "', ";
			}
	
			$sql_total_count = "SELECT count(*) AS total_count
								FROM domains
								WHERE dns_id = '" . $row->id . "'
								  AND active NOT IN ('0', '10')";
			$result_total_count = mysql_query($sql_total_count,$connection);
			while ($row_total_count = mysql_fetch_object($result_total_count)) {
				$total_dns_count = $row_total_count->total_count;
			}
	
			if ($row->id == $_SESSION['default_dns']) {
			
				$is_default = "1";
				
			} else {
			
				$is_default = "";
			
			}
	
			$full_export .= "\"Active\",\"" . $row->name . "\",\"" . number_format($row->number_of_servers) . "\",\"" . number_format($total_dns_count) . "\",\"" . $is_default . "\",\"" . $row->dns1 . "\",\"" . $row->ip1 . "\",\"" . $row->dns2 . "\",\"" . $row->ip2 . "\",\"" . $row->dns3 . "\",\"" . $row->ip3 . "\",\"" . $row->dns4 . "\",\"" . $row->ip4 . "\",\"" . $row->dns5 . "\",\"" . $row->ip5 . "\",\"" . $row->dns6 . "\",\"" . $row->ip6 . "\",\"" . $row->dns7 . "\",\"" . $row->ip7 . "\",\"" . $row->dns8 . "\",\"" . $row->ip8 . "\",\"" . $row->dns9 . "\",\"" . $row->ip9 . "\",\"" . $row->dns10 . "\",\"" . $row->ip10 . "\",\"" . $row->notes . "\",\"" . $row->insert_time . "\",\"" . $row->update_time . "\"\n";
	
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
	
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	if (mysql_num_rows($result) > 0) { 
	
		$has_inactive = "1";
	
		while ($row = mysql_fetch_object($result)) {
	
			if ($row->id == $_SESSION['default_dns']) {
			
				$is_default = "1";
				
			} else {
			
				$is_default = "";
			
			}
	
			$full_export .= "\"Inactive\",\"" . $row->name . "\",\"" . number_format($row->number_of_servers) . "\",\"0\",\"" . $is_default . "\",\"" . $row->dns1 . "\",\"" . $row->ip1 . "\",\"" . $row->dns2 . "\",\"" . $row->ip2 . "\",\"" . $row->dns3 . "\",\"" . $row->ip3 . "\",\"" . $row->dns4 . "\",\"" . $row->ip4 . "\",\"" . $row->dns5 . "\",\"" . $row->ip5 . "\",\"" . $row->dns6 . "\",\"" . $row->ip6 . "\",\"" . $row->dns7 . "\",\"" . $row->ip7 . "\",\"" . $row->dns8 . "\",\"" . $row->ip8 . "\",\"" . $row->dns9 . "\",\"" . $row->ip9 . "\",\"" . $row->dns10 . "\",\"" . $row->ip10 . "\",\"" . $row->notes . "\",\"" . $row->insert_time . "\",\"" . $row->update_time . "\"\n";
	
		}
	
	}

	$full_export .= "\n";
	$current_timestamp_unix = strtotime($current_timestamp);
	$export_filename = "dns_profile_list_" . $current_timestamp_unix . ".csv";
	include("../_includes/system/export-to-csv.inc.php");
	exit;
}
?>
<?php include("../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../_includes/layout/header.inc.php"); ?>
Below is a list of all the DNS Profiles that are stored in your <?=$software_title?>.<BR><BR>
[<a href="<?=$PHP_SELF?>?export=1">EXPORT</a>]<?php

$result = mysql_query($sql,$connection);

if (mysql_num_rows($result) > 0) {

	$has_active = "1"; ?>
	<table class="main_table">
    <tr class="main_table_row_heading_active">
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Active Profiles (<?=mysql_num_rows($result)?>)</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Servers</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Domains</font>
        </td>
    </tr><?php 
    
    while ($row = mysql_fetch_object($result)) {

	    $new_dnsid = $row->id;
    
        if ($current_dnsid != $new_dnsid) {
			$exclude_dns_string_raw .= "'" . $row->id . "', ";
		} ?>

        <tr class="main_table_row_active">
            <td class="main_table_cell_active">
                <a class="invisiblelink" href="edit/dns.php?dnsid=<?=$row->id?>"><?=$row->name?></a><?php if ($_SESSION['default_dns'] == $row->id) echo "<a title=\"Default DNS Profile\"><font class=\"default_highlight\">*</font></a>"; ?>
            </td>
            <td class="main_table_cell_active">
                <a class="invisiblelink" href="edit/dns.php?dnsid=<?=$row->id?>"><?=$row->number_of_servers?></a>
            </td>
            <td class="main_table_cell_active"><?php
                $sql_total_count = "SELECT count(*) AS total_count
                                    FROM domains
                                    WHERE dns_id = '" . $row->id . "'
                                      AND active NOT IN ('0', '10')";
                $result_total_count = mysql_query($sql_total_count,$connection);
                while ($row_total_count = mysql_fetch_object($result_total_count)) {
                    $total_dns_count = $row_total_count->total_count;
                } ?>
                <a class="nobold" href="../domains.php?dnsid=<?=$row->id?>"><?=number_format($total_dns_count)?></a>
            </td>
        </tr><?php 

		$current_dnsid = $row->id;

    }

}

$exclude_dns_string = substr($exclude_dns_string_raw, 0, -2); 

if ($exclude_dns_string == "") {

	$sql = "SELECT id, name, number_of_servers
			FROM dns
			ORDER BY name, number_of_servers desc";

} else {

	$sql = "SELECT id, name, number_of_servers
			FROM dns
			WHERE id NOT IN (" . $exclude_dns_string . ")
			ORDER BY name, number_of_servers desc";

}

$result = mysql_query($sql,$connection) or die(mysql_error());

if (mysql_num_rows($result) > 0) { 

	$has_inactive = "1";
	if ($has_active == "1") echo "<BR>";
	if ($has_active != "1" && $has_inactive == "1") echo "<table class=\"main_table\">"; ?>

    <tr class="main_table_row_heading_inactive">
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">Inactive Profiles (<?=mysql_num_rows($result)?>)</font>
        </td>
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">Servers</font>
        </td>
    </tr><?php 
	
	while ($row = mysql_fetch_object($result)) { ?>

        <tr class="main_table_row_inactive">
            <td class="main_table_cell_inactive">
                <a class="invisiblelink" href="edit/dns.php?dnsid=<?=$row->id?>"><?=$row->name?></a><?php if ($_SESSION['default_dns'] == $row->id) echo "<a title=\"Default DNS Profile\"><font class=\"default_highlight\">*</font></a>"; ?>
            </td>
            <td class="main_table_cell_inactive">
                <a class="invisiblelink" href="edit/dns.php?dnsid=<?=$row->id?>"><?=$row->number_of_servers?></a>
            </td>
        </tr><?php 

	}

}

if ($has_active == "1" || $has_inactive == "1") echo "</table>";

if ($has_active || $has_inactive) { ?>
		<BR><font class="default_highlight">*</font> = Default DNS Profile<?php 
}

if (!$has_active && !$has_inactive) { ?>
		<BR>You don't currently have any DNS Profiles. <a href="add/dns.php">Click here to add one</a>.<?php 
} ?>
<?php include("../_includes/layout/footer.inc.php"); ?>
</body>
</html>