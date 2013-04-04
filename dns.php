<?php
// dns.php
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

$page_title = "DNS Profile Breakdown";
$software_section = "dns";
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
$sql = "SELECT id, name, number_of_servers, default_dns
		FROM dns
		WHERE id IN (SELECT dns_id FROM domains WHERE dns_id != '0' AND active NOT IN ('0','10') GROUP BY dns_id)
		ORDER BY name asc";
$result = mysql_query($sql,$connection);
?>
Below is a list of all the DNS Profiles that are stored in your <?=$software_title?>.<BR><BR>
<?php if (mysql_num_rows($result) > 0) { ?>
<?php $has_active = "1"; ?>
<strong>Number of Active DNS Profiles:</strong> <?=mysql_num_rows($result)?><BR>
<BR>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
<tr height="20">
	<td width="325">
    	<font class="subheadline">Profile Name</font>
    </td>
	<td width="150">
    	<font class="subheadline"># of Servers</font>
    </td>
	<td>
    	<font class="subheadline"># of Domains</font>
    </td>
</tr>
<?php while ($row = mysql_fetch_object($result)) { ?>
<tr height="20">
    <td>
		<a class="subtlelink" href="edit/dns.php?dnsid=<?=$row->id?>"><?=$row->name?></a><?php if ($row->default_dns == "1") echo "<a title=\"Default DNS Profile\"><font color=\"#DD0000\"><strong>*</strong></font></a>"; ?>
	</td>
    <td>
        <a class="subtlelink" href="edit/dns.php?dnsid=<?=$row->id?>"><?=$row->number_of_servers?></a>
	</td>
	<td>
    	<?php
		$sql2 = "SELECT count(*) AS total_count
				 FROM domains
				 WHERE dns_id = '$row->id'
				   AND active NOT IN ('0', '10')";
		$result2 = mysql_query($sql2,$connection);
		while ($row2 = mysql_fetch_object($result2)) {
			$total_dns_count = $row2->total_count;
		}
		?>
        <a class="nobold" href="domains.php?dnsid=<?=$row->id?>"><?=number_format($total_dns_count)?></a>
    </td>
</tr>
<?php } ?>
</table>
<?php } ?>
<?php
if ($has_active == "1") {

	$sql = "SELECT id, name, number_of_servers, default_dns
			FROM dns
			WHERE id NOT IN (SELECT dns_id FROM domains WHERE dns_id != '0' AND active NOT IN ('0','10') GROUP BY dns_id)
			ORDER BY name asc";

} else {
	
	$sql = "SELECT id, name, number_of_servers, default_dns
			FROM dns
			WHERE active = '1'
			ORDER BY name asc";
	
}
$result = mysql_query($sql,$connection) or die(mysql_error());
?>
<?php if (mysql_num_rows($result) > 0) { 
$has_inactive = "1";
if ($has_active == "1") echo "<BR>";
?>
<strong>Number of Inactive DNS Profiles:</strong> <?=mysql_num_rows($result)?><BR><BR>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
<tr height="20">
	<td width="325">
    	<font class="subheadline">Profile Name</font>
    </td>
	<td>
    	<font class="subheadline"># of Servers</font>
    </td>
</tr>
<?php while ($row = mysql_fetch_object($result)) { ?>
<tr height="20">
    <td>
		<a class="subtlelink" href="edit/dns.php?dnsid=<?=$row->id?>"><?=$row->name?></a><?php if ($row->default_dns == "1") echo "<a title=\"Default DNS Profile\"><font color=\"#DD0000\"><strong>*</strong></font></a>"; ?>
	</td>
    <td>
        <a class="subtlelink" href="edit/dns.php?dnsid=<?=$row->id?>"><?=$row->number_of_servers?></a>
	</td>
</tr>
<?php } ?>
</table>
<?php } ?>
<?php if ($has_active || $has_inactive) { ?>
		<BR><font color="#DD0000"><strong>*</strong></font> = Default DNS Profile
<?php } ?>
<?php if (!$has_active && !$has_inactive) { ?>
		You don't currently have any DNS Profiles. <a href="add/dns.php">Click here to add one</a>.
<?php } ?>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>