<?php
// categories.php
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

$page_title = "Domain Categories";
$software_section = "categories";
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
$sql = "SELECT id, name, stakeholder, default_category
		FROM categories
		WHERE id IN (SELECT cat_id FROM domains WHERE cat_id != '0' AND active NOT IN ('0','10') GROUP BY cat_id)
		ORDER BY name, stakeholder";
$result = mysql_query($sql,$connection) or die(mysql_error());
$number_of_categories = mysql_num_rows($result);
?>
Below is a list of all the Domain Categories that are stored in your <?=$software_title?>.<BR>
<?php if (mysql_num_rows($result) > 0) { ?>
<?php $has_active = "1"; ?>
<table class="main_table">
<tr class="main_table_row_heading_active">
	<td class="main_table_cell_heading_active">
   	<font class="subheadline">Active Categories (<?=$number_of_categories?>)</font></td>
	<td class="main_table_cell_heading_active">
   	<font class="subheadline">Stakeholder</font></td>
	<td class="main_table_cell_heading_active">
    	<font class="subheadline">Domains</font>
    </td>
</tr>
<?php while ($row = mysql_fetch_object($result)) { ?>
<tr class="main_table_row_active">
    <td class="main_table_cell_active">
		<a class="subtlelink" href="edit/category.php?pcid=<?=$row->id?>"><?=$row->name?></a><?php if ($row->default_category == "1") echo "<a title=\"Default Category\"><font class=\"default_highlight\"><strong>*</strong></font></a>"; ?>
	</td>
    <td class="main_table_cell_active">
		<a class="subtlelink" href="edit/category.php?pcid=<?=$row->id?>"><?=$row->stakeholder?></a>
	</td>
	<td class="main_table_cell_active">
    <?php
	$sql2 = "SELECT count(*) AS total_count
			 FROM domains
			 WHERE cat_id = '$row->id'
			   AND active NOT IN ('0', '10')";
	$result2 = mysql_query($sql2,$connection);
	while ($row2 = mysql_fetch_object($result2)) { $active_domains = $row2->total_count; }
	?>
    	<?php if ($active_domains == "0") { ?>
	        <?=number_format($active_domains)?>
        <?php } else { ?>
	        <a class="nobold" href="domains.php?pcid=<?=$row->id?>"><?=number_format($active_domains)?></a>
        <?php } ?>
    </td>
</tr>
<?php } ?>
<?php } ?>
<?php
if ($has_active == "1") {

	$sql = "SELECT id, name, stakeholder, default_category
			FROM categories
			WHERE id NOT IN (SELECT cat_id FROM domains WHERE cat_id != '0' AND active NOT IN ('0','10') GROUP BY cat_id)
			ORDER BY name, stakeholder";

} else {
	
	$sql = "SELECT id, name, stakeholder, default_category
			FROM categories
			WHERE active = '1'
			ORDER BY name, stakeholder";
	
}
$result = mysql_query($sql,$connection) or die(mysql_error());
$number_of_categories = mysql_num_rows($result);
?>
<?php if (mysql_num_rows($result) > 0) { 
$has_inactive = "1";
if ($has_active == "1") echo "<BR>";
?>
<tr class="main_table_row_heading_inactive">
	<td class="main_table_cell_heading_inactive">
   	<font class="subheadline">Inactive Categories (<?=$number_of_categories?>)</font></td>
	<td class="main_table_cell_heading_inactive">
   	<font class="subheadline">Stakeholder</font></td>
</tr>
<?php while ($row = mysql_fetch_object($result)) { ?>
<tr class="main_table_row_inactive">
    <td class="main_table_cell_inactive">
		<a class="subtlelink" href="edit/category.php?pcid=<?=$row->id?>"><?=$row->name?></a><?php if ($row->default_category == "1") echo "<a title=\"Default Category\"><font class=\"default_highlight\"><strong>*</strong></font></a>"; ?>
	</td>
    <td class="main_table_cell_inactive">
		<a class="subtlelink" href="edit/category.php?pcid=<?=$row->id?>"><?=$row->stakeholder?></a>
	</td>
</tr>
<?php } ?>
<?php } ?>
</table>
<?php if ($has_active || $has_inactive) { ?>
		<BR><font class="default_highlight"><strong>*</strong></font> = Default Category
<?php } ?>
<?php if (!$has_active && !$has_inactive) { ?>
		You don't currently have any Categories. <a href="add/category.php">Click here to add one</a>.
<?php } ?>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>