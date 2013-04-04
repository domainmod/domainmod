<?php
// owners.php
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

$page_title = "Owner Breakdown";
$software_section = "owners";
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
$sql = "SELECT id, name, default_owner
		FROM owners
		WHERE id IN (SELECT owner_id FROM domains WHERE owner_id != '0' AND active NOT IN ('0','10') GROUP BY owner_id)
		ORDER BY default_owner desc, name asc";
$result = mysql_query($sql,$connection) or die(mysql_error());
?>
Below is a list of all the Domain Registrar and SSL Provider Account Owners that are stored in your <?=$software_title?>.<BR><BR>
<?php if (mysql_num_rows($result) > 0) { ?>
<?php $has_active = "1"; ?>
<strong>Number of Active Owners:</strong> <?=mysql_num_rows($result)?><BR>
<BR>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
<tr height="20">
	<td width="225">
    	<font class="subheadline">Owner Name</font>
    </td>
	<td width="150">
    	<font class="subheadline"># of Accounts</font>
    </td>
	<td>
    	<font class="subheadline"># of Domains</font>
    </td>
</tr>
<?php while ($row = mysql_fetch_object($result)) { ?>
<tr height="20">
    <td>
		<a class="subtlelink" href="edit/owner.php?oid=<?=$row->id?>"><?=$row->name?><?php if ($row->default_owner == "1") echo "<a title=\"Default Owner\"><font color=\"#DD0000\"><strong>*</strong></font></a>"; ?></a>
	</td>
	<td>
    <?php
	$sql2 = "SELECT count(*) AS total_count
			 FROM registrar_accounts
			 WHERE active = '1'
			   AND owner_id = '$row->id'";
	$result2 = mysql_query($sql2,$connection);
	while ($row2 = mysql_fetch_object($result2)) { $total_accounts = $row2->total_count; }
	?>
    	<?php if ($total_accounts >= 1) { ?>

	        <a class="nobold" href="registrar-accounts.php?oid=<?=$row->id?>"><?=number_format($total_accounts)?></a>

        <?php } else { ?>

	        <?=number_format($total_accounts)?>
        
        <?php } ?>
    </td>
	<td>
    <?php
	$sql3 = "SELECT count(*) AS total_count
			 FROM domains
			 WHERE active NOT IN ('0', '10')
			   AND owner_id = '$row->id'";
	$result3 = mysql_query($sql3,$connection);
	while ($row3 = mysql_fetch_object($result3)) { $total_domains = $row3->total_count; }
	?>

    	<?php if ($total_domains >= 1) { ?>

	    	<a class="nobold" href="domains.php?oid=<?=$row->id?>"><?=number_format($total_domains)?></a>

        <?php } else { ?>

	        <?=number_format($total_domains)?>
        
        <?php } ?>

    </td>
</tr>
<?php } ?>
</table>
<?php } ?>
<?php
if ($has_active == "1") {

	$sql = "SELECT id, name, default_owner
			FROM owners
			WHERE id NOT IN (SELECT owner_id FROM domains WHERE owner_id != '0' AND active NOT IN ('0','10') GROUP BY owner_id)
			ORDER BY default_owner desc, name asc";

} else {
	
	$sql = "SELECT id, name, default_owner
			FROM owners
			WHERE active = '1'
			ORDER BY default_owner desc, name asc";
	
}
$result = mysql_query($sql,$connection) or die(mysql_error());
?>
<?php if (mysql_num_rows($result) > 0) { 
$has_inactive = "1";
if ($has_active == "1") echo "<BR>";
?>
<strong>Number of Inactive Owners:</strong> <?=mysql_num_rows($result)?><BR><BR>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
<tr height="20">
	<td width="225">
    	<font class="subheadline">Owner Name</font>
    </td>
	<td>
    	<font class="subheadline"># of Accounts</font>
    </td>
</tr>
<?php while ($row = mysql_fetch_object($result)) { ?>
<tr height="20">
    <td>
		<a class="subtlelink" href="edit/owner.php?oid=<?=$row->id?>"><?=$row->name?><?php if ($row->default_owner == "1") echo "<a title=\"Default Owner\"><font color=\"#DD0000\"><strong>*</strong></font></a>"; ?></a>
	</td>
	<td>
    <?php
	$sql2 = "SELECT count(*) AS total_count
			 FROM registrar_accounts
			 WHERE active = '1'
			   AND owner_id = '$row->id'";
	$result2 = mysql_query($sql2,$connection);
	while ($row2 = mysql_fetch_object($result2)) { $total_accounts = $row2->total_count; }
	?>
    	<?php if ($total_accounts >= 1) { ?>

	        <a class="nobold" href="registrar-accounts.php?oid=<?=$row->id?>"><?=number_format($total_accounts)?></a>

        <?php } else { ?>

	        <?=number_format($total_accounts)?>
        
        <?php } ?>
    </td>
</tr>
<?php } ?>
</table>
<?php } ?>
<?php if ($has_active || $has_inactive) { ?>
		<BR><font color="#DD0000"><strong>*</strong></font> = Default Owner
<?php } ?>
<?php if (!$has_active && !$has_inactive) { ?>
		You don't currently have any Owners. <a href="add/owner.php">Click here to add one</a>.
<?php } ?>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>