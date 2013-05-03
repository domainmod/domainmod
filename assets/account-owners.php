<?php
// /assets/account-owners.php
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

$page_title = "Domain Registrar & SSL Provider Account Owners";
$software_section = "account-owners";

$sql = "SELECT id
		FROM owners";
$result = mysql_query($sql,$connection);
if (mysql_num_rows($result) == 0) $zero_owners = "1";
?>
<?php include("../_includes/doctype.inc.php"); ?>
<html>
<head>	
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../_includes/layout/header.inc.php"); ?>
<?php
$sql = "SELECT id, name
		FROM owners
		WHERE id IN (SELECT owner_id FROM domains WHERE owner_id != '0' AND active NOT IN ('0','10') GROUP BY owner_id)
		ORDER BY name asc";
$result = mysql_query($sql,$connection) or die(mysql_error());
?>
Below is a list of all the Domain Registrar and SSL Provider Account Owners that are stored in your <?=$software_title?>.
<?php if ($zero_owners != "1") { ?><BR><BR><BR><font class="subheadline">Domains</font><?php } ?>
<?php if (mysql_num_rows($result) > 0) { ?>
<?php $has_active_domain = "1"; ?>
<table class="main_table">
<tr class="main_table_row_heading_active">
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">Active Owners (<?=mysql_num_rows($result)?>)</font>
    </td>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">Accounts</font>
    </td>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">Domains</font>
    </td>
</tr>
<?php while ($row = mysql_fetch_object($result)) { ?>
<tr class="main_table_row_active">
    <td class="main_table_cell_active">
		<a class="invisiblelink" href="edit/account-owner.php?oid=<?=$row->id?>"><?=$row->name?><?php if ($_SESSION['default_owner'] == $row->id) echo "<a title=\"Default Owner\"><font class=\"default_highlight\">*</font></a>"; ?></a>
	</td>
	<td class="main_table_cell_active">
    <?php
	$sql_total_count = "SELECT count(*) AS total_count
						FROM registrar_accounts
						WHERE owner_id = '$row->id'";
	$result_total_count = mysql_query($sql_total_count,$connection);
	while ($row_total_count = mysql_fetch_object($result_total_count)) { $total_accounts = $row_total_count->total_count; }
	?>
    	<?php if ($total_accounts >= 1) { ?>

	        <a class="nobold" href="registrar-accounts.php?oid=<?=$row->id?>"><?=number_format($total_accounts)?></a>

        <?php } else { ?>

	        <?=number_format($total_accounts)?>
        
        <?php } ?>
    </td>
	<td class="main_table_cell_active">
    <?php
	$sql3 = "SELECT count(*) AS total_count
			 FROM domains
			 WHERE active NOT IN ('0', '10')
			   AND owner_id = '$row->id'";
	$result3 = mysql_query($sql3,$connection);
	while ($row3 = mysql_fetch_object($result3)) { $total_domains = $row3->total_count; }
	?>

    	<?php if ($total_domains >= 1) { ?>

	    	<a class="nobold" href="../domains.php?oid=<?=$row->id?>"><?=number_format($total_domains)?></a>

        <?php } else { ?>

	        <?=number_format($total_domains)?>
        
        <?php } ?>

    </td>
</tr>
<?php } ?>
<?php } ?>
<?php
if ($has_active_domain == "1") {

	$sql = "SELECT id, name
			FROM owners
			WHERE id NOT IN (SELECT owner_id FROM domains WHERE owner_id != '0' AND active NOT IN ('0','10') GROUP BY owner_id)
			ORDER BY name asc";

} else {
	
	$sql = "SELECT id, name
			FROM owners
			ORDER BY name asc";
	
}
$result = mysql_query($sql,$connection) or die(mysql_error());
?>
<?php if (mysql_num_rows($result) > 0) { 
$has_inactive_domains = "1";
if ($has_active_domain == "1") echo "<BR>";
if ($has_active_domain != "1" && $has_inactive_domains == "1") echo "<table class=\"main_table\">";
?>
<tr class="main_table_row_heading_inactive">
	<td class="main_table_cell_heading_inactive">
    	<font class="main_table_heading">Inactive Owners (<?=mysql_num_rows($result)?>)</font>
    </td>
	<td class="main_table_cell_heading_inactive">
    	<font class="main_table_heading">Accounts</font>
    </td>
</tr>
<?php while ($row = mysql_fetch_object($result)) { ?>
<tr class="main_table_row_inactive">
    <td class="main_table_cell_inactive">
		<a class="invisiblelink" href="edit/account-owner.php?oid=<?=$row->id?>"><?=$row->name?><?php if ($_SESSION['default_owner'] == $row->id) echo "<a title=\"Default Owner\"><font class=\"default_highlight\">*</font></a>"; ?></a>
	</td>
	<td class="main_table_cell_inactive">
    <?php
	$sql_total_count = "SELECT count(*) AS total_count
						FROM registrar_accounts
						WHERE owner_id = '$row->id'";
	$result_total_count = mysql_query($sql_total_count,$connection);
	while ($row_total_count = mysql_fetch_object($result_total_count)) { $total_accounts = $row_total_count->total_count; }
	?>
    	<?php if ($total_accounts >= 1) { ?>

	        <a class="nobold" href="registrar-accounts.php?oid=<?=$row->id?>"><?=number_format($total_accounts)?></a>

        <?php } else { ?>

	        <?=number_format($total_accounts)?>
        
        <?php } ?>
    </td>
</tr>
<?php } ?>
<?php } ?>
<?php
if ($has_active_domain == "1" || $has_inactive_domains == "1") echo "</table>";
?>

<?php
$sql = "SELECT id, name
		FROM owners
		WHERE id IN (SELECT owner_id FROM ssl_certs WHERE owner_id != '0' AND active NOT IN ('0') GROUP BY owner_id)
		ORDER BY name asc";
$result = mysql_query($sql,$connection) or die(mysql_error());
?>
<?php if ($zero_owners != "1") { ?><BR><BR><font class="subheadline">SSL Certificates</font><?php } ?>
<?php if (mysql_num_rows($result) > 0) { ?>
<?php $has_active_ssl = "1"; ?>
<table class="main_table">
<tr class="main_table_row_heading_active">
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">Active Owners (<?=mysql_num_rows($result)?>)</font>
    </td>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">Accounts</font>
    </td>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">SSL Certs</font>
    </td>
</tr>
<?php while ($row = mysql_fetch_object($result)) { ?>
<tr class="main_table_row_active">
    <td class="main_table_cell_active">
		<a class="invisiblelink" href="edit/account-owner.php?oid=<?=$row->id?>"><?=$row->name?><?php if ($_SESSION['default_owner'] == $row->id) echo "<a title=\"Default Owner\"><font class=\"default_highlight\">*</font></a>"; ?></a>
	</td>
	<td class="main_table_cell_active">
    <?php
	$sql_total_count = "SELECT count(*) AS total_count
						FROM ssl_accounts
						WHERE owner_id = '$row->id'";
	$result_total_count = mysql_query($sql_total_count,$connection);
	while ($row_total_count = mysql_fetch_object($result_total_count)) { $total_accounts = $row_total_count->total_count; }
	?>
    	<?php if ($total_accounts >= 1) { ?>

	        <a class="nobold" href="ssl-accounts.php?oid=<?=$row->id?>"><?=number_format($total_accounts)?></a>

        <?php } else { ?>

	        <?=number_format($total_accounts)?>
        
        <?php } ?>
    </td>
	<td class="main_table_cell_active">
    <?php
	$sql3 = "SELECT count(*) AS total_count
			 FROM ssl_certs
			 WHERE active NOT IN ('0')
			   AND owner_id = '$row->id'";
	$result3 = mysql_query($sql3,$connection);
	while ($row3 = mysql_fetch_object($result3)) { $total_certs = $row3->total_count; }
	?>

    	<?php if ($total_certs >= 1) { ?>

	    	<a class="nobold" href="../ssl-certs.php?oid=<?=$row->id?>"><?=number_format($total_certs)?></a>

        <?php } else { ?>

	        <?=number_format($total_certs)?>
        
        <?php } ?>

    </td>
</tr>
<?php } ?>
<?php } ?>
<?php
if ($has_active_ssl == "1") {

	$sql = "SELECT id, name
			FROM owners
			WHERE id NOT IN (SELECT owner_id FROM ssl_certs WHERE owner_id != '0' AND active NOT IN ('0') GROUP BY owner_id)
			ORDER BY name asc";

} else {
	
	$sql = "SELECT id, name
			FROM owners
			ORDER BY name asc";
	
}
$result = mysql_query($sql,$connection) or die(mysql_error());
?>
<?php if (mysql_num_rows($result) > 0) { 
if ($has_active_ssl) echo "<BR>";
$has_inactive_ssl = "1";
if ($has_active_ssl != "1" && $has_inactive_ssl == "1") echo "<table class=\"main_table\">";
?>
<tr class="main_table_row_heading_inactive">
	<td class="main_table_cell_heading_inactive">
    	<font class="main_table_heading">Inactive Owners (<?=mysql_num_rows($result)?>)</font>
    </td>
	<td class="main_table_cell_heading_inactive">
    	<font class="main_table_heading">Accounts</font>
    </td>
</tr>
<?php while ($row = mysql_fetch_object($result)) { ?>
<tr class="main_table_row_inactive">
    <td class="main_table_cell_inactive">
		<a class="invisiblelink" href="edit/account-owner.php?oid=<?=$row->id?>"><?=$row->name?><?php if ($_SESSION['default_owner'] == $row->id) echo "<a title=\"Default Owner\"><font class=\"default_highlight\">*</font></a>"; ?></a>
	</td>
	<td class="main_table_cell_inactive">
    <?php
	$sql_total_count = "SELECT count(*) AS total_count 
						FROM ssl_accounts
						WHERE owner_id = '$row->id'";
	$result_total_count = mysql_query($sql_total_count,$connection);
	while ($row_total_count = mysql_fetch_object($result_total_count)) { $total_accounts = $row_total_count->total_count; }
	?>
    	<?php if ($total_accounts >= 1) { ?>

	        <a class="nobold" href="ssl-accounts.php?oid=<?=$row->id?>"><?=number_format($total_accounts)?></a>

        <?php } else { ?>

	        <?=number_format($total_accounts)?>
        
        <?php } ?>
    </td>
</tr>
<?php } ?>
<?php } ?>
<?php
if ($has_active_ssl == "1" || $has_inactive_ssl == "1") echo "</table>";
?>
<?php if ($has_active_domain || $has_inactive_domain || $has_active_ssl || $has_inactive_ssl) { ?>
		<BR><font class="default_highlight">*</font> = Default Owner
<?php } ?>
<?php if (!$has_active_domain && !$has_inactive_domain && !$has_active_ssl && !$has_inactive_ssl) { ?>
        <BR><BR>You don't currently have any Owners. <a href="add/account-owner.php">Click here to add one</a>.
<?php } ?>
<?php include("../_includes/layout/footer.inc.php"); ?>
</body>
</html>