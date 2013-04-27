<?php
// /ip-addresses.php
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
include("_includes/start-session.inc.php");
include("_includes/config.inc.php");
include("_includes/database.inc.php");
include("_includes/software.inc.php");
include("_includes/auth/auth-check.inc.php");

$page_title = "IP Addresses";
$software_section = "ip-addresses";
?>
<?php include("_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("_includes/header.inc.php"); ?>
<?php
$sql = "SELECT id, name, ip, rdns, default_ip_address
		FROM ip_addresses
		WHERE id IN (SELECT ip_id FROM domains WHERE ip_id != '0' AND active NOT IN ('0','10') GROUP BY ip_id)
		ORDER BY name, ip, rdns";
$result = mysql_query($sql,$connection);
?>
Below is a list of all the IP Addresses that are stored in your <?=$software_title?>.<BR>
<?php if (mysql_num_rows($result) > 0) { ?>
<?php $has_active = "1"; ?>
<table class="main_table">
    <tr class="main_table_row_heading_active">
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">IP Address Name</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Active IPs (<?=mysql_num_rows($result)?>)</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">rDNS</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Domains</font>
        </td>
    </tr>

    <?php
    while ($row = mysql_fetch_object($result)) { ?>

        <tr class="main_table_row_active">
            <td class="main_table_cell_active">
                <a class="invisiblelink" href="edit/ip-address.php?ipid=<?=$row->id?>"><?=$row->name?></a><?php if ($row->default_ip_address == "1") echo "<a title=\"Default IP Address\"><font class=\"default_highlight\">*</font></a>"; ?></a>
            </td>
            <td class="main_table_cell_active">
                <a class="invisiblelink" href="edit/ip-address.php?ipid=<?=$row->id?>"><?=$row->ip?></a>
            </td>
            <td class="main_table_cell_active">
                <a class="invisiblelink" href="edit/ip-address.php?ipid=<?=$row->id?>"><?=$row->rdns?></a>
            </td>
            <td class="main_table_cell_active">
                <?php
                $sql_total_count = "SELECT count(*) AS total_count
									FROM domains
									WHERE ip_id = '$row->id'
									  AND active NOT IN ('0', '10')";
                $result_total_count = mysql_query($sql_total_count,$connection);
                while ($row_total_count = mysql_fetch_object($result_total_count)) {
                    $total_ip_count = $row_total_count->total_count;
                }
                ?>
                <a class="nobold" href="domains.php?ipid=<?=$row->id?>"><?=number_format($total_ip_count)?></a>
            </td>
        </tr>
    <?php 
    } ?>
<?php 
} ?>
<?php
if ($has_active == "1") {

	$sql = "SELECT id, name, ip, rdns, default_ip_address
			FROM ip_addresses
			WHERE id NOT IN (SELECT ip_id FROM domains WHERE ip_id != '0' AND active NOT IN ('0','10') GROUP BY ip_id)
			ORDER BY name, ip, rdns";

} else {
	
	$sql = "SELECT id, name, ip, rdns, default_ip_address
			FROM ip_addresses
			ORDER BY name, ip, rdns";
	
}
$result = mysql_query($sql,$connection);
?>
<?php if (mysql_num_rows($result) > 0) { 
$has_inactive = "1";
if ($has_active == "1") echo "<BR>";
if ($has_active != "1" && $has_inactive == "1") echo "<table class=\"main_table\">";
?>
    <tr class="main_table_row_heading_inactive">
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">IP Address Name</font>
        </td>
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">Inactive IPs (<?=mysql_num_rows($result)?>)</font>
        </td>
        <td class="main_table_cell_heading_inactive">
            <font class="main_table_heading">rDNS</font>
        </td>
    </tr>

    <?php
    while ($row = mysql_fetch_object($result)) { ?>

        <tr class="main_table_row_inactive">
            <td class="main_table_cell_inactive">
                <a class="invisiblelink" href="edit/ip-address.php?ipid=<?=$row->id?>"><?=$row->name?><?php if ($row->default_ip_address == "1") echo "<a title=\"Default IP Address\"><font class=\"default_highlight\">*</font></a>"; ?></a></a>
            </td>
            <td class="main_table_cell_inactive">
                <a class="invisiblelink" href="edit/ip-address.php?ipid=<?=$row->id?>"><?=$row->ip?></a>
            </td>
            <td class="main_table_cell_inactive">
                <a class="invisiblelink" href="edit/ip-address.php?ipid=<?=$row->id?>"><?=$row->rdns?></a>
            </td>
        </tr>
    <?php 
    } ?>

<?php 
} ?>
<?php
if ($has_active == "1" || $has_inactive == "1") echo "</table>";
?>
<?php if ($has_active || $has_inactive) { ?>
		<BR><font class="default_highlight">*</font> = Default IP Address
<?php } ?>
<?php if (!$has_active && !$has_inactive) { ?>
		<BR>You don't currently have any IP Addresses. <a href="add/ip-address.php">Click here to add one</a>.
<?php } ?>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>