<?php
// /currencies.php
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

$page_title = "Currencies";
$software_section = "currencies";
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
$sql = "SELECT id, currency, name, conversion
		FROM currencies
		ORDER BY name asc";
$result = mysql_query($sql,$connection) or die(mysql_error());
?>
The below conversion rates are used for accounting and reporting purposes, and at the very least they should be updated before you export your domains or SSL certificates.<BR><BR>
<strong>NOTE:</strong> Thanks to Yahoo! Finance's free API, rate conversions have now been automated! Simply <a href="system/update-conversion-rates.php">click here to update the conversion rates</a>.<BR><BR>
<?php if (mysql_num_rows($result) > 0) { ?>
<?php $has_active = "1"; ?>
<table class="main_table">
<tr class="main_table_row_heading_active">
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">Currency (<?=mysql_num_rows($result)?>)</font>
    </td>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">ABV</font>
    </td>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">Conversion Rate</font>
    </td>
</tr>
<?php
while ($row = mysql_fetch_object($result)) { ?>
<tr class="main_table_row_active">
    <td class="main_table_cell_active">
		<a class="invisiblelink" href="edit/currency.php?curid=<?=$row->id?>"><?=$row->name?></a><?php if ($_SESSION['default_currency'] == $row->currency) echo "<a title=\"Default Currency\"><font class=\"default_highlight\">*</font></a>"; ?>
	</td>
    <td class="main_table_cell_active">
		<?php echo "$row->currency"; ?>
	</td>
    <td class="main_table_cell_active">
		<?php echo "$row->conversion"; ?>
	</td>
</tr>
<?php } ?>
</table>
<?php } ?>
<?php if ($has_active) { ?>
		<BR><font class="default_highlight">*</font> = Default Currency (for rate conversions, reporting, etc.)
<?php } ?>
<?php if (!$has_active) { ?>
		You don't currently have any active Currencies.
<?php } ?>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>