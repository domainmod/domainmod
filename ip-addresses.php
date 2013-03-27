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
This is a breakdown of the IP Addresses that are currently in use.
<BR><BR>
<?php
$sql = "select id, name, ip
		from ip_addresses
		where id in (select ip_id from domains where ip_id != '0' and active not in ('0','10') group by ip_id)
		order by name asc";
$result = mysql_query($sql,$connection);
?>
<strong>Number of Active IP Addresses:</strong> <?=mysql_num_rows($result)?>
<?php
if (mysql_num_rows($result) > 0) { ?>

    <BR><BR>
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
    <tr height="30">
        <td width="300">
            <font class="subheadline">IP Name</font>
        </td>
        <td width="200">
            <font class="subheadline">IP Address</font>
        </td>
        <td>
            <font class="subheadline"># of Domains</font>
        </td>
    </tr>

	<?php
    while ($row = mysql_fetch_object($result)) { ?>

        <tr height="20">
            <td>
                <a class="subtlelink" href="edit/ip-address.php?ipid=<?=$row->id?>"><?=$row->name?></a>
            </td>
            <td>
                <a class="subtlelink" href="edit/ip-address.php?ipid=<?=$row->id?>"><?=$row->ip?></a>
            </td>
            <td>
                <?php
                $sql2 = "select count(*) as total_count
                         from domains
                         where ip_id = '$row->id'
						 and active not in ('0', '10')";
                $result2 = mysql_query($sql2,$connection);
                while ($row2 = mysql_fetch_object($result2)) {
                    $total_ip_count = $row2->total_count;
                }
                ?>
                <a class="nobold" href="domains.php?ipid=<?=$row->id?>"><?=number_format($total_ip_count)?></a>
            </td>
        </tr>

    <?php } ?>

	</table>
	<?php 
} ?>
<?php
$sql = "select id, name, ip
		from ip_addresses
		where id not in (select ip_id from domains where ip_id != '0' and active not in ('0','10') group by ip_id)
		order by name asc";
$result = mysql_query($sql,$connection);
?>
<?php
if (mysql_num_rows($result) > 0) { ?>
<BR><BR>
<strong>Number of Inactive IP Addresses:</strong> <?=mysql_num_rows($result)?>

    <BR><BR>
    <table width="100%" border="0" cellspacing="0" cellpadding="0">
    <tr height="30">
        <td width="300">
            <font class="subheadline">IP Name</font>
        </td>
        <td>
            <font class="subheadline">IP Address</font>
        </td>
    </tr>

	<?php
    while ($row = mysql_fetch_object($result)) { ?>

        <tr height="20">
            <td>
                <a class="subtlelink" href="edit/ip-address.php?ipid=<?=$row->id?>"><?=$row->name?></a>
            </td>
            <td>
                <a class="subtlelink" href="edit/ip-address.php?ipid=<?=$row->id?>"><?=$row->ip?></a>
            </td>
        </tr>

    <?php } ?>

	</table>
	<?php 
} ?>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>