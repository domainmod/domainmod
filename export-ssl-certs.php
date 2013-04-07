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

$page_title = "Export SSL Certificates";

// Form Variables
$export = $_GET['export'];
$new_expiry_start = $_REQUEST['new_expiry_start'];
$new_expiry_end = $_REQUEST['new_expiry_end'];

if ($export == "1") {

	$sql = "SELECT sslc.id, sslc.domain_id, sslc.name, sslcf.type, sslc.expiry_date, sslc.notes, sslc.active, sslpa.username, sslp.name AS ssl_provider_name, o.name AS owner_name, f.renewal_fee AS renewal_fee, cc.conversion
			FROM ssl_certs AS sslc, ssl_accounts AS sslpa, ssl_providers AS sslp, owners AS o, ssl_fees AS f, currencies AS cc, ssl_cert_types AS sslcf
			WHERE sslc.account_id = sslpa.id
			  AND sslc.type_id = sslcf.id
			  AND sslpa.ssl_provider_id = sslp.id
			  AND sslpa.owner_id = o.id
			  AND sslc.ssl_provider_id = f.ssl_provider_id
			  AND sslc.type_id = f.type_id
			  AND f.currency_id = cc.id
			  AND sslc.expiry_date between '$new_expiry_start' AND '$new_expiry_end'
			ORDER BY sslc.expiry_date asc";	

} else {

	$sql = "SELECT sslc.id, sslc.domain_id, sslc.name, sslcf.type, sslc.expiry_date, sslc.notes, sslc.active, sslpa.username, sslp.name AS ssl_provider_name, o.name AS owner_name, f.renewal_fee AS renewal_fee, cc.conversion
			FROM ssl_certs AS sslc, ssl_accounts AS sslpa, ssl_providers AS sslp, owners AS o, ssl_fees AS f, currencies AS cc, ssl_cert_types AS sslcf
			WHERE sslc.account_id = sslpa.id
			  AND sslc.type_id = sslcf.id
			  AND sslpa.ssl_provider_id = sslp.id
			  AND sslpa.owner_id = o.id
			  AND sslc.ssl_provider_id = f.ssl_provider_id
			  AND sslc.type_id = f.type_id
			  AND f.currency_id = cc.id
			  AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9')
			  AND sslc.expiry_date between '$new_expiry_start' AND '$new_expiry_end'
			ORDER BY sslc.expiry_date asc";	

}

$result = mysql_query($sql,$connection) or die(mysql_error());
$result2 = mysql_query($sql,$connection) or die(mysql_error());

$full_export = "";

if ($export == "1") {

	$full_export .= "\"All prices are listed in " . $_SESSION['session_default_currency'] . "\"\n\n";

	$full_export .= "\"SSL STATUS\",\"Expiry Date\",\"Renew?\",\"Renewal Fee\",\"Host / Label\",\"Domain\",\"IP Address Name\",\"IP Address\",\"IP Address rDNS\",\"SSL Type\",\"Owner\",\"SSL Provider\",\"Username\",\"Notes\"\n";

	while ($row = mysql_fetch_object($result)) {
		
		$temp_renewal_fee = number_format($row->renewal_fee * $row->conversion, 2, '.', ',');
		$total_renewal_fee_export = $total_renewal_fee_export + $temp_renewal_fee;

		if ($row->active == "0") { $ssl_status = "EXPIRED"; } 
		elseif ($row->active == "1") { $ssl_status = "ACTIVE"; } 
		elseif ($row->active == "2") { $ssl_status = "PENDING (REGISTRATION)"; } 
		elseif ($row->active == "3") { $ssl_status = "PENDING (RENEWAL)"; } 
		elseif ($row->active == "4") { $ssl_status = "PENDING (OTHER)"; } 
		else { $ssl_status = "ERROR -- PROBLEM WITH CODE IN EXPORT-SSL-CERTS.PHP"; } 
		
		$sql_domain = "SELECT d.domain, ip.name, ip.ip, ip.rdns
					   FROM domains AS d, ip_addresses AS ip
					   WHERE d.ip_id = ip.id
					     AND d.id = '$row->domain_id'";
		$result_domain = mysql_query($sql_domain,$connection);
		
		while ($row_domain = mysql_fetch_object($result_domain)) {
			$full_domain_name = $row_domain->domain;
			$full_ip_name = $row_domain->name;
			$full_ip_address = $row_domain->ip;
			$full_ip_rdns = $row_domain->rdns;
		}

		$full_export .= "\"$ssl_status\",\"$row->expiry_date\",\"$row->to_renew\",\"\$$temp_renewal_fee\",\"$row->name\",\"$full_domain_name\",\"$full_ip_name\",\"$full_ip_address\",\"$full_ip_rdns\",\"$row->type\",\"$row->owner_name\",\"$row->ssl_provider_name\",\"$row->username\",\"$row->notes\"\n";
	}
	
	$full_export .= "\n";
	
	$full_export .= "\"\",\"\",\"Total Cost:\",\"\$" . number_format($total_renewal_fee_export, 2, '.', ',') . "\",\"" . $_SESSION['session_default_currency'] . "\"\n";
	
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
<strong>Number of SSL Certificates to Export:</strong> <?=number_format(mysql_num_rows($result))?><BR><BR>
<?php } ?>
Before exporting your SSL Certificates you should <a href="system/update-conversion-rates.php">update the conversion rates</a>.
<BR><BR>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
<tr>
<td class="search-table"><BR>
<table width="100%" border="0" cellspacing="0" cellpadding="0">
<tr>
<td class="search-table-inside">
<form name="export_ssl_certs_form" method="post" action="<?=$PHP_SELF?>">
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
<table class="main_table">
<tr class="main_table_row_heading_active">
<?php if ($_SESSION['session_display_ssl_expiry_date'] == "1") { ?>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">Expiry Date</font>
    </td>
<?php } ?>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">Host / Label</font>
    </td>
<?php if ($_SESSION['session_display_ssl_domain'] == "1") { ?>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">Domain</font>
    </td>
<?php } ?>
<?php if ($_SESSION['session_display_ssl_type'] == "1") { ?>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">Type</font>
    </td>
<?php } ?>
<?php if ($_SESSION['session_display_ssl_owner'] == "1") { ?>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">Owner</font>
    </td>
<?php } ?>
<?php if ($_SESSION['session_display_ssl_provider'] == "1") { ?>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">SSL Provider</font>
    </td>
<?php } ?>
<?php if ($_SESSION['session_display_ssl_account'] == "1") { ?>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">SSL Account</font>
    </td>
<?php } ?>
</tr>
<?php while ($row = mysql_fetch_object($result)) { ?>
<?php 
$renewal_fee_individual = $row->renewal_fee * $row->conversion;
$total_renewal_cost = $total_renewal_cost + $renewal_fee_individual; 
?>
<tr class="main_table_row_active">
<?php if ($_SESSION['session_display_ssl_expiry_date'] == "1") { ?>
	<td class="main_table_cell_active">
		<?=$row->expiry_date?>
	</td>
<?php } ?>
	<td class="main_table_cell_active">
		<?=$row->name?>
	</td>
<?php if ($_SESSION['session_display_ssl_domain'] == "1") { ?>
	<td class="main_table_cell_active">
		<?php
		$sql_domain = "SELECT d.domain, ip.name, ip.ip, ip.rdns
					   FROM domains AS d, ip_addresses AS ip
					   WHERE d.ip_id = ip.id
					     AND d.id = '$row->domain_id'";
		$result_domain = mysql_query($sql_domain,$connection);
		
		while ($row_domain = mysql_fetch_object($result_domain)) {
			$full_domain_name = $row_domain->domain;
			$full_ip_name = $row_domain->name;
			$full_ip_address = $row_domain->ip;
			$full_ip_rdns = $row_domain->rdns;
		}
		?>		
		<?=$full_domain_name?>
	</td>
<?php } ?>
<?php if ($_SESSION['session_display_ssl_type'] == "1") { ?>
	<td class="main_table_cell_active">
		<?=$row->type?>
    </td>
<?php } ?>
<?php if ($_SESSION['session_display_ssl_owner'] == "1") { ?>
	<td class="main_table_cell_active">
		<?=$row->owner_name?>
    </td>
<?php } ?>
<?php if ($_SESSION['session_display_ssl_provider'] == "1") { ?>
	<td class="main_table_cell_active">
		<?=$row->ssl_provider_name?>
    </td>
<?php } ?>
<?php if ($_SESSION['session_display_ssl_account'] == "1") { ?>
	<td class="main_table_cell_active">
		<?=$row->ssl_provider_name?> (<?=substr($row->username, 0, 20);?><?php if (strlen($row->username) >= 21) echo "..."; ?>)
    </td>
<?php } ?>
</tr>
<?php } ?>
</table>
<BR><strong>Total Cost:</strong> $<?=number_format($total_renewal_cost,2)?> <?=$_SESSION['session_default_currency']?><BR>
<?php } else { ?>
<BR>The results that will be shown below will display the same columns as you have on your <a href="ssl-certs.php">SSL Certificates</a> page, but when you export the results you will be given even more information.<BR><BR>
The full list of fields in the export is:<BR><BR>
Certificate Status<BR>
Expiry Date<BR>
Renewal Fee<BR>
Total Renewal Cost<BR>
SSL Cert Name<BR>
Associated Domain<BR>
IP Address Name<BR>
IP Address<BR>
IP Address rDNS<BR>
SSL Type<BR>
Owner<BR>
SSL Provider<BR>
SSL Account<BR>
Notes<BR>
<?php } ?>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>