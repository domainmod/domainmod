<?php
// currencies.php
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

$page_title = "Currencies";
$software_section = "currencies";
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
$sql = "SELECT id, currency, name, conversion, default_currency
		FROM currencies
		WHERE active = '1'
		ORDER BY name asc";
$result = mysql_query($sql,$connection);
?>
The below conversion rates are used for accounting and reporting purposes, and at the very least they should be updated before you export your domains or SSL certificates.<BR><BR>
<strong>NOTE:</strong> Thanks to Yahoo! Finance's free API, rate conversions have now been automated! Simply <a href="system/update-conversion-rates.php">click here to update the conversion rates</a>.<BR><BR>
<?php if (mysql_num_rows($result) > 0) { ?>
<?php $has_active = "1"; ?>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
<tr height="20">
	<td width="230">
    	<font class="subheadline">Currency (<?=mysql_num_rows($result)?>)</font>
    </td>
	<td width="125">
    	<font class="subheadline">ABV</font>
    </td>
	<td>
    	<font class="subheadline">Conversion Rate</font>
    </td>
</tr>
<?php
while ($row = mysql_fetch_object($result)) { ?>
<tr height="20">
    <td>
		<a class="subtlelink" href="edit/currency.php?curid=<?=$row->id?>"><?=$row->name?></a><?php if ($row->default_currency == 1) echo "<a title=\"Default Currency\"><font class=\"default_highlight\"><strong>*</strong></font></a>"; ?>
<?php if ($row->currency == $_SESSION['session_default_currency']) echo ""; ?>
	</td>
    <td>
		<?php echo "$row->currency"; ?>
	</td>
    <td>
		<?php echo "$row->conversion"; ?>
	</td>
</tr>
<?php } ?>
</table>
<?php } ?>
<?php if ($has_active) { ?>
		<BR><font class="default_highlight"><strong>*</strong></font> = Default Currency (for rate conversions, reporting, etc.)
<?php } ?>
<?php if (!$has_active) { ?>
		You don't currently have any Currencies. <a href="add/currency.php">Click here to add one</a>.
<?php } ?>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>