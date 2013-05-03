<?php
// /assets/hosting.php
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

$page_title = "Web Hosting Providers";
$software_section = "hosting";
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
		FROM hosting
		WHERE id IN (SELECT hosting_id FROM domains WHERE hosting_id != '0' AND active NOT IN ('0','10') GROUP BY hosting_id)
		ORDER BY name";
$result = mysql_query($sql,$connection) or die(mysql_error());
$number_of_hosting_providers = mysql_num_rows($result);
?>
Below is a list of all the Web Hosting Providers that are stored in your <?=$software_title?>.<BR>
<?php if (mysql_num_rows($result) > 0) { ?>
<?php $has_active = "1"; ?>
<table class="main_table">
<tr class="main_table_row_heading_active">
	<td class="main_table_cell_heading_active">
   	<font class="main_table_heading">Active Hosts (<?=$number_of_hosting_providers?>)</font></td>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">Domains</font>
    </td>
</tr>
<?php while ($row = mysql_fetch_object($result)) { ?>
<tr class="main_table_row_active">
    <td class="main_table_cell_active">
		<a class="invisiblelink" href="edit/host.php?whid=<?=$row->id?>"><?=$row->name?></a><?php if ($_SESSION['default_host'] == $row->id) echo "<a title=\"Default Web Host\"><font class=\"default_highlight\">*</font></a>"; ?>
	</td>
	<td class="main_table_cell_active">
    <?php
	$sql_total_count = "SELECT count(*) AS total_count
						FROM domains
						WHERE hosting_id = '$row->id'
						  AND active NOT IN ('0', '10')";
	$result_total_count = mysql_query($sql_total_count,$connection);
	while ($row_total_count = mysql_fetch_object($result_total_count)) { $active_domains = $row_total_count->total_count; }
	?>
    	<?php if ($active_domains == "0") { ?>
	        <?=number_format($active_domains)?>
        <?php } else { ?>
	        <a class="nobold" href="../domains.php?whid=<?=$row->id?>"><?=number_format($active_domains)?></a>
        <?php } ?>
    </td>
</tr>
<?php } ?>
<?php } ?>
<?php
if ($has_active == "1") {

	$sql = "SELECT id, name
			FROM hosting
			WHERE id NOT IN (SELECT hosting_id FROM domains WHERE hosting_id != '0' AND active NOT IN ('0','10') GROUP BY hosting_id)
			ORDER BY name";

} else {
	
	$sql = "SELECT id, name
			FROM hosting
			ORDER BY name";
	
}
$result = mysql_query($sql,$connection) or die(mysql_error());
$number_of_hosting_providers = mysql_num_rows($result);
?>
<?php if (mysql_num_rows($result) > 0) { 
$has_inactive = "1";
if ($has_active == "1") echo "<BR>";
if ($has_active != "1" && $has_inactive == "1") echo "<table class=\"main_table\">";
?>
<tr class="main_table_row_heading_inactive">
	<td class="main_table_cell_heading_inactive">
   	<font class="main_table_heading">Inactive Hosts (<?=$number_of_hosting_providers?>)</font></td>
</tr>
<?php while ($row = mysql_fetch_object($result)) { ?>
<tr class="main_table_row_inactive">
    <td class="main_table_cell_inactive">
		<a class="invisiblelink" href="edit/host.php?whid=<?=$row->id?>"><?=$row->name?></a><?php if ($_SESSION['default_host'] == "1") echo "<a title=\"Default Web Host\"><font class=\"default_highlight\">*</font></a>"; ?>
	</td>
	</td>
</tr>
<?php } ?>
<?php } ?>
<?php
if ($has_active == "1" || $has_inactive == "1") echo "</table>";
?>
<?php if ($has_active || $has_inactive) { ?>
		<BR><font class="default_highlight">*</font> = Default Web Host
<?php } ?>
<?php if (!$has_active && !$has_inactive) { ?>
		<BR>You don't currently have any Web Hosting Providers. <a href="add/host.php">Click here to add one</a>.
<?php } ?>
<?php include("../_includes/layout/footer.inc.php"); ?>
</body>
</html>