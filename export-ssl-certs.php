<?php
// export-ssl-certs.php
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
include("_includes/timestamps/current-timestamp-basic.inc.php");
$page_title = "Expiring SSL Certificates";

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

if ($export == "1") {

	$sql = "select sslc.id, sslc.name, sslc.ip, sslct.type, sslcf.function, sslc.expiry_date, sslc.notes, sslc.active, sslpa.username, sslp.name as ssl_provider_name, c.name as company_name, f.renewal_fee as renewal_fee, cc.conversion
			from ssl_certs as sslc, ssl_accounts as sslpa, ssl_providers as sslp, companies as c, ssl_fees as f, currencies as cc, ssl_cert_types as sslct, ssl_cert_functions as sslcf
			where sslc.account_id = sslpa.id
			and sslc.type_id = sslct.id
			and sslc.function_id = sslcf.id
			and sslpa.ssl_provider_id = sslp.id
			and sslpa.company_id = c.id
			and sslc.ssl_provider_id = f.ssl_provider_id
			and sslc.type_id = f.type_id
			and sslc.function_id = f.function_id
			and f.currency_id = cc.id
			and sslc.expiry_date between '$new_expiry_start' and '$new_expiry_end'
			order by sslc.expiry_date asc
			";	

} else {

	$sql = "select sslc.id, sslc.name, sslc.ip, sslct.type, sslcf.function, sslc.expiry_date, sslc.notes, sslc.active, sslpa.username, sslp.name as ssl_provider_name, c.name as company_name, f.renewal_fee as renewal_fee, cc.conversion
			from ssl_certs as sslc, ssl_accounts as sslpa, ssl_providers as sslp, companies as c, ssl_fees as f, currencies as cc, ssl_cert_types as sslct, ssl_cert_functions as sslcf
			where sslc.account_id = sslpa.id
			and sslc.type_id = sslct.id
			and sslc.function_id = sslcf.id
			and sslpa.ssl_provider_id = sslp.id
			and sslpa.company_id = c.id
			and sslc.ssl_provider_id = f.ssl_provider_id
			and sslc.type_id = f.type_id
			and sslc.function_id = f.function_id
			and f.currency_id = cc.id
			and sslc.active in ('1', '2', '3', '4', '5', '6', '7', '8', '9')
			and sslc.expiry_date between '$new_expiry_start' and '$new_expiry_end'
			order by sslc.expiry_date asc
			";	

}

$result = mysql_query($sql,$connection) or die(mysql_error());
$result2 = mysql_query($sql,$connection) or die(mysql_error());

$full_export = "";

if ($export == "1") {

	$full_export .= "\"All prices are listed in $default_currency\"\n\n";

	$full_export .= "\"SSL STATUS\",\"Expiry Date\",\"Renew?\",\"Renewal Fee\",\"Host / Label\",\"IP Address\",\"Function\",\"Type\",\"Company\",\"Registrar\",\"Username\"\n";

	while ($row = mysql_fetch_object($result)) {
		
		$temp_renewal_fee = number_format($row->renewal_fee * $row->conversion, 2, '.', ',');
		$total_renewal_fee_export = $total_renewal_fee_export + $temp_renewal_fee;

		if ($row->active == "0") { 
			$ssl_status = "EXPIRED";
		} elseif ($row->active == "1") { 
			$ssl_status = "ACTIVE";
		} elseif ($row->active == "2") { 
			$ssl_status = "PENDING (REGISTRATION)";
		} elseif ($row->active == "3") { 
			$ssl_status = "PENDING (RENEWAL)";
		} elseif ($row->active == "4") { 
			$ssl_status = "PENDING (OTHER)";
		} else { 
			$ssl_status = "ERROR -- PROBLEM WITH CODE IN EXPORT-SSL-CERTS.PHP"; 
		} 

		$full_export .= "\"$ssl_status\",\"$row->expiry_date\",\"$row->to_renew\",\"\$$temp_renewal_fee\",\"$row->name\",\"$row->ip\",\"$row->function\",\"$row->type\",\"$row->company_name\",\"$row->ssl_provider_name\",\"$row->username\"\n";
	}
	
	$full_export .= "\n";
	
	$full_export .= "\"\",\"\",\"Total Cost:\",\"\$" . number_format($total_renewal_fee_export, 2, '.', ',') . "\",\"$default_currency\"\n";
	
	$export = "0";
	
header('Content-Type: text/plain');
$full_content_disposition = "Content-Disposition: attachment; filename=\"export_ssl_$new_expiry_start--$new_expiry_end.csv\"";
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
<strong>Number of Expiring SSL Certificates:</strong> <?=number_format(mysql_num_rows($result))?><BR><BR>
<?php } ?>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
<tr>
<td class="search-table"><BR>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
<tr>
<td class="search-table-inside">
<form name="form1" method="post" action="<?=$PHP_SELF?>">
Expiring Between 
  <input name="new_expiry_start" type="text" size="10" maxlength="10" <?php if ($new_expiry_start == "") { echo "value=\"$current_timestamp_basic\""; } else { echo "value=\"$new_expiry_start\""; } ?>> 
  and 
  <input name="new_expiry_end" type="text" size="10" maxlength="10" <?php if ($new_expiry_end == "") { echo "value=\"$current_timestamp_basic\""; } else { echo "value=\"$new_expiry_end\""; } ?>> 
  &nbsp;&nbsp;<input type="submit" name="button" value="Find Expiring &raquo;">
</form>
</td>
<td class="search-table-inside" width="200" valign="middle" align="center">
<?php if (mysql_num_rows($result) > 0) { ?>
<a href="export-ssl-certs.php?export=1&new_expiry_start=<?=$new_expiry_start?>&new_expiry_end=<?=$new_expiry_end?>">Export Results</a><BR>
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
    	<font class="subheadline">Host / Label</font>
    </td>
	<td>
    	<font class="subheadline">IP Address</font>
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
		<?=$row->name?>
	</td>
	<td valign="top">
		<?=$row->ip?>
	</td>
	<td valign="top">
		<?=$row->company_name?>
    </td>
	<td valign="top">
		<?=$row->ssl_provider_name?> (<?=substr($row->username, 0, 10);?>...)
    </td>
</tr>
<?php } ?>
</table>
<BR><strong>Total Cost:</strong> $<?=number_format($total_renewal_cost,2)?> <?=$default_currency?><BR>
<?php } ?>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>