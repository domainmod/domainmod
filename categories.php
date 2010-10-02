<?php
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
$page_title = "Category Breakdown";
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
$sql = "select id, name, owner, default_category
		from categories
		where active = '1'
		order by name asc";
$result = mysql_query($sql,$connection) or die(mysql_error());
$number_of_categories = mysql_num_rows($result);
?>
Here you can create categories that you can use to help organize your domains.
<BR><BR>
<strong>Number of Active Categories:</strong> <?=$number_of_categories?>
<?php if (mysql_num_rows($result) > 0) { ?>
<BR><BR>
<table width="100%" border="1" cellspacing="0" cellpadding="0">
<tr height="30">
	<td width="500">
   	<font class="subheadline">Category</font></td>
	<td width="250">
   	<font class="subheadline">Owner</font></td>
	<td>
    	<font class="subheadline"># of Domains</font>
    </td>
</tr>
<?php while ($row = mysql_fetch_object($result)) { ?>
<tr height="20">
    <td>
		<a class="subtlelink" href="edit/category.php?pcid=<?=$row->id?>"><?=$row->name?></a><?php if ($row->default_category == "1") echo "<a title=\"Default Category\"><font color=\"#DD0000\"><strong>*</strong></font></a>"; ?>
	</td>
    <td>
		<a class="subtlelink" href="edit/category.php?pcid=<?=$row->id?>"><?=$row->owner?></a>
	</td>
	<td>
    <?php
	$sql2 = "select count(*) as total_count
			 from domains
			 where cat_id = '$row->id'
			 and active = '1'";
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
</table>
<BR><font color="#DD0000"><strong>*</strong></font> = Default Category
<?php } ?>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>