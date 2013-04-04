<?php
// ip-addresses.php
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

include("_includes/config.inc.php");
include("_includes/database.inc.php");
include("_includes/software.inc.php");
include("_includes/auth/auth-check.inc.php");

$page_title = "IP Address Breakdown";
$software_section = "ip-addresses";
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("_includes/header.inc.php"); ?>
<?php
$sql = "SELECT id, name, ip, rdns, default_ip_address
		FROM ip_addresses
		WHERE id IN (SELECT ip_id FROM domains WHERE ip_id != '0' AND active NOT IN ('0','10') GROUP BY ip_id)
		ORDER BY default_ip_address desc, name asc";
$result = mysql_query($sql,$connection);
?>
Below is a list of all the IP Addresses that are stored in your <?=$software_title?>.<BR><BR>
<?php if (mysql_num_rows($result) > 0) { ?>
<?php $has_active = "1"; ?>
<strong>Number of Active IP Addresses:</strong> <?=mysql_num_rows($result)?><BR>
<BR>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
    <tr height="20">
        <td width="300">
            <font class="subheadline">IP Name</font>
        </td>
        <td width="190">
            <font class="subheadline">IP Address</font>
        </td>
        <td width="210">
            <font class="subheadline">rDNS</font>
        </td>
        <td>
            <font class="subheadline"># of Domains</font>
        </td>
    </tr>

    <?php
    while ($row = mysql_fetch_object($result)) { ?>

        <tr height="20">
            <td>
                <a class="subtlelink" href="edit/ip-address.php?ipid=<?=$row->id?>"><?=$row->name?></a><?php if ($row->default_ip_address == "1") echo "<a title=\"Default IP Address\"><font color=\"#DD0000\"><strong>*</strong></font></a>"; ?></a>
            </td>
            <td>
                <a class="subtlelink" href="edit/ip-address.php?ipid=<?=$row->id?>"><?=$row->ip?></a>
            </td>
            <td>
                <a class="subtlelink" href="edit/ip-address.php?ipid=<?=$row->id?>"><?=$row->rdns?></a>
            </td>
            <td>
                <?php
                $sql2 = "SELECT count(*) AS total_count
                         FROM domains
                         WHERE ip_id = '$row->id'
                           AND active NOT IN ('0', '10')";
                $result2 = mysql_query($sql2,$connection);
                while ($row2 = mysql_fetch_object($result2)) {
                    $total_ip_count = $row2->total_count;
                }
                ?>
                <a class="nobold" href="domains.php?ipid=<?=$row->id?>"><?=number_format($total_ip_count)?></a>
            </td>
        </tr>
    <?php 
    } ?>

</table>
<?php 
} ?>
<?php
if ($has_active == "1") {

	$sql = "SELECT id, name, ip, rdns, default_ip_address
			FROM ip_addresses
			WHERE id NOT IN (SELECT ip_id FROM domains WHERE ip_id != '0' AND active NOT IN ('0','10') GROUP BY ip_id)
			ORDER BY default_ip_address desc, name asc";

} else {
	
	$sql = "SELECT id, name, ip, rdns, default_ip_address
			FROM ip_addresses
			WHERE active = '1'
			ORDER BY default_ip_address desc, name asc";
	
}
$result = mysql_query($sql,$connection);
?>
<?php if (mysql_num_rows($result) > 0) { 
$has_inactive = "1";
if ($has_active == "1") echo "<BR>";
?>
<strong>Number of Inactive IP Addresses:</strong> <?=mysql_num_rows($result)?><BR><BR>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
    <tr height="20">
        <td width="300">
            <font class="subheadline">IP Name</font>
        </td>
        <td width="190">
            <font class="subheadline">IP Address</font>
        </td>
        <td>
            <font class="subheadline">rDNS</font>
        </td>
    </tr>

    <?php
    while ($row = mysql_fetch_object($result)) { ?>

        <tr height="20">
            <td>
                <a class="subtlelink" href="edit/ip-address.php?ipid=<?=$row->id?>"><?=$row->name?><?php if ($row->default_ip_address == "1") echo "<a title=\"Default IP Address\"><font color=\"#DD0000\"><strong>*</strong></font></a>"; ?></a></a>
            </td>
            <td>
                <a class="subtlelink" href="edit/ip-address.php?ipid=<?=$row->id?>"><?=$row->ip?></a>
            </td>
            <td>
                <a class="subtlelink" href="edit/ip-address.php?ipid=<?=$row->id?>"><?=$row->rdns?></a>
            </td>
        </tr>
    <?php 
    } ?>

</table>
<?php 
} ?>
<?php if ($has_active || $has_inactive) { ?>
		<BR><font color="#DD0000"><strong>*</strong></font> = Default IP Address
<?php } ?>
<?php if (!$has_active && !$has_inactive) { ?>
		You don't currently have any IP Addresses. <a href="add/ip-address.php">Click here to add one</a>.
<?php } ?>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>