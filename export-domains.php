<?php
// export-domains.php
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

$page_title = "Export Domains";

// Form Variables
$export = $_GET['export'];
$new_expiry_start = $_REQUEST['new_expiry_start'];
$new_expiry_end = $_REQUEST['new_expiry_end'];

if ($export == "1") {

	$sql = "SELECT d.id, d.domain, d.tld, d.expiry_date, d.function, d.status, d.status_notes, d.notes, d.active, ra.username, r.name AS registrar_name, c.name AS company_name, f.renewal_fee AS renewal_fee, cc.conversion, cat.name AS category_name, cat.owner AS category_owner, dns.name AS dns_profile, ip.name, ip.ip, ip.rdns
			FROM domains AS d, registrar_accounts AS ra, registrars AS r, companies AS c, fees AS f, currencies AS cc, categories AS cat, dns, ip_addresses AS ip
			WHERE d.account_id = ra.id
			  AND ra.registrar_id = r.id
			  AND ra.company_id = c.id
			  AND d.registrar_id = f.registrar_id
			  AND d.tld = f.tld
			  AND f.currency_id = cc.id
			  AND d.cat_id = cat.id
			  AND d.dns_id = dns.id
			  AND d.ip_id = ip.id
			  AND cat.active = '1'
			  AND d.expiry_date between '$new_expiry_start' AND '$new_expiry_end'
			ORDER BY d.expiry_date asc";	

} else {

	$sql = "SELECT d.id, d.domain, d.tld, d.expiry_date, d.function, d.status, d.status_notes, d.notes, d.active, ra.username, r.name AS registrar_name, c.name AS company_name, f.renewal_fee AS renewal_fee, cc.conversion, cat.name AS category_name, cat.owner AS category_owner, dns.name AS dns_profile, ip.name, ip.ip, ip.rdns
			FROM domains AS d, registrar_accounts AS ra, registrars AS r, companies AS c, fees AS f, currencies AS cc, categories AS cat, dns, ip_addresses AS ip
			WHERE d.account_id = ra.id
			  AND ra.registrar_id = r.id
			  AND ra.company_id = c.id
			  AND d.registrar_id = f.registrar_id
			  AND d.tld = f.tld
			  AND f.currency_id = cc.id
			  AND d.cat_id = cat.id
			  AND d.dns_id = dns.id
			  AND d.ip_id = ip.id
			  AND cat.active = '1'
			  AND d.active NOT IN ('0', '10')
			  AND d.expiry_date between '$new_expiry_start' AND '$new_expiry_end'
			ORDER BY d.expiry_date asc";	

}

$result = mysql_query($sql,$connection) or die(mysql_error());
$result2 = mysql_query($sql,$connection) or die(mysql_error());

$full_export = "";

if ($export == "1") {

	$full_export .= "\"All prices are listed in " . $_SESSION['session_default_currency'] . "\"\n\n";

	$full_export .= "\"DOMAIN STATUS\",\"Expiry Date\",\"Renew?\",\"Renewal Fee\",\"Domain\",\"TLD\",\"DNS Profile\",\"IP Address Name\",\"IP Address\",\"IP Address rDNS\",\"Function\",\"Status\",\"Status Notes\",\"Category\",\"Category Owner\",\"Company\",\"Registrar\",\"Username\"\n";

	while ($row = mysql_fetch_object($result)) {
		
		$temp_renewal_fee = number_format($row->renewal_fee * $row->conversion, 2, '.', ',');
		$total_renewal_fee_export = $total_renewal_fee_export + $temp_renewal_fee;

		if ($row->active == "0") { $domain_status = "EXPIRED"; } 
		elseif ($row->active == "1") { $domain_status = "ACTIVE"; } 
		elseif ($row->active == "2") { $domain_status = "IN TRANSFER"; } 
		elseif ($row->active == "3") { $domain_status = "PENDING (RENEWAL)"; } 
		elseif ($row->active == "4") { $domain_status = "PENDING (OTHER)"; } 
		elseif ($row->active == "5") { $domain_status = "PENDING (REGISTRATION)"; } 
		elseif ($row->active == "10") { $domain_status = "SOLD"; } 
		else { $domain_status = "ERROR -- PROBLEM WITH CODE IN EXPORT-DOMAINS.PHP"; } 

		$full_export .= "\"$domain_status\",\"$row->expiry_date\",\"$row->to_renew\",\"\$$temp_renewal_fee\",\"$row->domain\",\"$row->tld\",\"$row->dns_profile\",\"$row->name\",\"$row->ip\",\"$row->rdns\",\"$row->function\",\"$row->status\",\"$row->status_notes\",\"$row->category_name\",\"$row->category_owner\",\"$row->company_name\",\"$row->registrar_name\",\"$row->username\"\n";
	}
	
	$full_export .= "\n";
	
	$full_export .= "\"\",\"\",\"Total Cost:\",\"\$" . number_format($total_renewal_fee_export, 2, '.', ',') . "\",\"" . $_SESSION['session_default_currency'] . "\"\n";
	
	$export = "0";
	
header('Content-Type: text/plain');
$full_content_disposition = "Content-Disposition: attachment; filename=\"export_domains_$new_expiry_start--$new_expiry_end.csv\"";
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
<strong>Number of Domains to Export:</strong> <?=number_format(mysql_num_rows($result))?><BR><BR>
<?php } ?>
Before exporting your domains you should <a href="system/update-exchange-rates.php">update the exchange rates</a>.
<BR><BR>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
<tr>
<td class="search-table"><BR>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
<tr>
<td class="search-table-inside">
<form name="export_domains_form" method="post" action="<?=$PHP_SELF?>">
Expiring Between 
  <input name="new_expiry_start" type="text" size="10" maxlength="10" <?php if ($new_expiry_start == "") { echo "value=\"$current_timestamp_basic\""; } else { echo "value=\"$new_expiry_start\""; } ?>> 
  and 
  <input name="new_expiry_end" type="text" size="10" maxlength="10" <?php if ($new_expiry_end == "") { echo "value=\"$current_timestamp_basic\""; } else { echo "value=\"$new_expiry_end\""; } ?>> 
  &nbsp;&nbsp;<input type="submit" name="button" value="List Domains &raquo;">
</form>
</td>
<td class="search-table-inside" width="200" valign="middle" align="center">
<?php if (mysql_num_rows($result) > 0) { ?>
<a href="export-domains.php?export=1&new_expiry_start=<?=$new_expiry_start?>&new_expiry_end=<?=$new_expiry_end?>">Export Results</a><BR>
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
    	<font class="subheadline">IP Address</font>
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
		<?=$row->name?> (<?=$row->ip?>)
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
<BR><strong>Total Cost:</strong> $<?=number_format($total_renewal_cost,2)?> <?=$_SESSION['session_default_currency']?><BR>
<?php } ?>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>