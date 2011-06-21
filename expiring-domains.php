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
include("_includes/timestamps/current-timestamp.inc.php");
$page_title = "Expiring Domains";

// Form Variables
$export = $_GET['export'];
$new_expiry_start = $_REQUEST['new_expiry_start'];
$new_expiry_end = $_REQUEST['new_expiry_end'];

$sql2 = "select currency, name, conversion
		from currencies
		where default_currency = '1'
		and active = '1'";
$result2 = mysql_query($sql2,$connection) or die(mysql_error());

while ($row2 = mysql_fetch_object($result2)) {
	$default_currency = $row2->currency;
	$default_currency_name = $row2->name;
	$default_currency_conversion = $row2->conversion;
}

$sql = "select d.id, d.domain, d.tld, d.expiry_date, d.function, d.status, d.status_notes, d.notes, d.active, ra.username, r.name as registrar_name, c.name as company_name, f.renewal_fee as renewal_fee, cc.conversion, cat.name as category_name, cat.owner as category_owner
		from domains as d, registrar_accounts as ra, registrars as r, companies as c, fees as f, currencies as cc, categories as cat
		where d.account_id = ra.id
		and ra.registrar_id = r.id
		and ra.company_id = c.id
		and d.registrar_id = f.registrar_id
		and d.tld = f.tld
		and f.currency_id = cc.id
		and d.cat_id = cat.id
		and cat.active = '1'
		and d.active not in ('0', '10')
		and d.expiry_date between '$new_expiry_start' and '$new_expiry_end'
		order by d.expiry_date asc
		";	

$result = mysql_query($sql,$connection) or die(mysql_error());
$result2 = mysql_query($sql,$connection) or die(mysql_error());

$full_export = "";

if ($export == "1") {

	$full_export .= "\"All prices are listed in $default_currency\"\n\n";

	$full_export .= "\"Expiry Date\",\"Renew?\",\"Renewal Fee\",\"Domain\",\"TLD\",\"Function\",\"Status\",\"Status Notes\",\"Category\",\"Category Owner\",\"Company\",\"Registrar\",\"Username\"\n";

	while ($row = mysql_fetch_object($result)) {
		
		$temp_renewal_fee = number_format($row->renewal_fee * $row->conversion, 2, '.', ',');
		$total_renewal_fee_export = $total_renewal_fee_export + $temp_renewal_fee;

		$full_export .= "\"$row->expiry_date\",\"$row->to_renew\",\"\$$temp_renewal_fee\",\"$row->domain\",\"$row->tld\",\"$row->function\",\"$row->status\",\"$row->status_notes\",\"$row->category_name\",\"$row->category_owner\",\"$row->company_name\",\"$row->registrar_name\",\"$row->username\"\n";
	}
	
	$full_export .= "\n";
	
	$full_export .= "\"\",\"Total Cost:\",\"\$" . number_format($total_renewal_fee_export, 2, '.', ',') . "\",\"$default_currency\"\n";
	
	$export = "0";
	
header('Content-Type: text/plain');
$full_content_disposition = "Content-Disposition: attachment; filename=\"expiring_$new_expiry_start--$new_expiry_end.csv\"";
header("$full_content_disposition");
header('Content-Transfer-Encoding: binary');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
echo $full_export;
exit;
}
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
$result = $result2;
if (mysql_num_rows($result) > 0) { ?>
<strong>Number of Expiring Domains:</strong> <?=number_format(mysql_num_rows($result))?><BR><BR>
<?php } ?>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
<tr>
<td class="search-table"><BR>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
<tr>
<td class="search-table-inside">
<form name="form1" method="post" action="<?=$PHP_SELF?>">
Expiring Between 
  <input name="new_expiry_start" type="text" size="10" maxlength="10" <?php if ($new_expiry_start == "") { echo "value=\"$current_timestamp_date_only\""; } else { echo "value=\"$new_expiry_start\""; } ?>> 
  and 
  <input name="new_expiry_end" type="text" size="10" maxlength="10" <?php if ($new_expiry_end == "") { echo "value=\"$current_timestamp_date_only\""; } else { echo "value=\"$new_expiry_end\""; } ?>> 
  &nbsp;&nbsp;<input type="submit" name="button" value="Find Expiring &raquo;">
</form>
</td>
<td class="search-table-inside" width="200" valign="middle" align="center">
<?php if (mysql_num_rows($result) > 0) { ?>
<a href="expiring-domains.php?export=1&new_expiry_start=<?=$new_expiry_start?>&new_expiry_end=<?=$new_expiry_end?>">Export Results</a><BR>
<?php } ?>
</td>
</tr>
</table>
</tr>
</table>
<?php if (mysql_num_rows($result) > 0) { ?>
<BR>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
<tr height="30">
	<td>
    	<font class="subheadline">Expiry Date</font>
    </td>
	<td width="15" align="center">&nbsp;
		
    </td>
	<td>
    	<font class="subheadline">Renewal</font>
    </td>
	<td>
    	<font class="subheadline">Domain Name</font>
    </td>
	<td>
    	<font class="subheadline">Category</font>
    </td>
	<td>
    	<font class="subheadline">Company/Account</font>
    </td>
	<td>
    	<font class="subheadline">Registrar (Username)</font>
    </td>
</tr>
<?php while ($row = mysql_fetch_object($result)) { ?>
<tr height="20">
	<td valign="top">
		<?=$row->expiry_date?>
	</td>
	<td valign="top">&nbsp;
		
	</td>
	<td valign="top">
    	<?php 
		$renewal_fee_individual = $row->renewal_fee * $row->conversion;
		$total_renewal_cost = $total_renewal_cost + $renewal_fee_individual; ?>
		$<?=number_format($renewal_fee_individual, 2, '.', ',')?>
	</td>
	<td valign="top">
		<?=$row->domain?>
	</td>
	<td valign="top">
		<?=$row->category_name?>
	</td>
	<td valign="top">
		<?=$row->company_name?>
    </td>
	<td valign="top">
		<?=$row->registrar_name?> (<?=substr($row->username, 0, 10);?>...)
    </td>
</tr>
<?php } ?>
</table>
<BR><strong>Total Cost:</strong> $<?=number_format($total_renewal_cost,2)?> <?=$default_currency?><BR>
<?php } ?>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>