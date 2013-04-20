<?php
// /ssl-cert-renewals.php
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
include("_includes/timestamps/current-timestamp.inc.php");
include("_includes/timestamps/current-timestamp-basic.inc.php");

$page_title = "SSL Certificate Renewal Export";

// Form Variables
$export = $_GET['export'];
$new_expiry_start = $_REQUEST['new_expiry_start'];
$new_expiry_end = $_REQUEST['new_expiry_end'];

$sql = "SELECT sslc.id, sslc.domain_id, sslc.name, sslcf.type, sslc.expiry_date, sslc.notes, sslc.active, sslpa.username, sslp.name AS ssl_provider_name, o.name AS owner_name, f.renewal_fee AS renewal_fee, cc.conversion
		FROM ssl_certs AS sslc, ssl_accounts AS sslpa, ssl_providers AS sslp, owners AS o, ssl_fees AS f, currencies AS cc, ssl_cert_types AS sslcf
		WHERE sslc.account_id = sslpa.id
		  AND sslc.type_id = sslcf.id
		  AND sslpa.ssl_provider_id = sslp.id
		  AND sslpa.owner_id = o.id
		  AND sslc.ssl_provider_id = f.ssl_provider_id
		  AND sslc.type_id = f.type_id
		  AND f.currency_id = cc.id
		  AND sslc.active NOT IN ('0')
		  AND sslc.expiry_date between '$new_expiry_start' AND '$new_expiry_end'
		ORDER BY sslc.expiry_date asc, sslc.name asc";	

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	function MyCheckDate( $postedDate ) {
	   if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $postedDate, $datebit)) {
		  return checkdate($datebit[2] , $datebit[3] , $datebit[1]);
	   } else {
		  return false;
	   }
	} 	

	if (MyCheckDate($new_expiry_start) && MyCheckDate($new_expiry_end)) {

		$sql_currency = "SELECT currency
						 FROM currencies
						 WHERE default_currency = '1'
						 LIMIT 1";
		$result_currency = mysql_query($sql_currency,$connection);
		while ($row_currency = mysql_fetch_object($result_currency)) { $default_currency = $row_currency->currency; }

		$result = mysql_query($sql,$connection) or die(mysql_error());
		$result2 = mysql_query($sql,$connection) or die(mysql_error());

	} else {

		if (!MyCheckDate($new_expiry_start)) { $_SESSION['session_result_message'] .= "The starting expiry date you entered is invalid<BR>"; }
		if (!MyCheckDate($new_expiry_end)) { $_SESSION['session_result_message'] .= "The ending expiry date you entered is invalid<BR>"; }

	}

}

$full_export = "";

if ($export == "1") {

	$result = mysql_query($sql,$connection) or die(mysql_error());

	$full_export .= "\"All prices are listed in " . $default_currency . "\"\n\n";

	$full_export .= "\"SSL STATUS\",\"Expiry Date\",\"Renew?\",\"Renewal Fee\",\"Host / Label\",\"Domain\",\"SSL Provider\",\"Username\",\"SSL Type\",\"Owner\",\"IP Address Name\",\"IP Address\",\"IP Address rDNS\",\"Notes\"\n";

	while ($row = mysql_fetch_object($result)) {
		
		$temp_renewal_fee = $row->renewal_fee * $row->conversion;
		$total_renewal_fee_export = $total_renewal_fee_export + $temp_renewal_fee;

		if ($row->active == "0") { $ssl_status = "EXPIRED"; } 
		elseif ($row->active == "1") { $ssl_status = "ACTIVE"; } 
		elseif ($row->active == "3") { $ssl_status = "PENDING (RENEWAL)"; } 
		elseif ($row->active == "4") { $ssl_status = "PENDING (OTHER)"; } 
		elseif ($row->active == "5") { $ssl_status = "PENDING (REGISTRATION)"; } 
		else { $ssl_status = "ERROR -- PROBLEM WITH CODE IN SSL-CERT-RENEWALS.PHP"; } 
		
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

		$temp_input_amount = $temp_renewal_fee;
		$temp_input_conversion = "";
		include("_includes/system/convert-and-format-currency.inc.php");
		$export_renewal_fee = $temp_output_amount;

		$full_export .= "\"$ssl_status\",\"$row->expiry_date\",\"$row->to_renew\",\"" . $export_renewal_fee . "\",\"$row->name\",\"$full_domain_name\",\"$row->ssl_provider_name\",\"$row->username\",\"$row->type\",\"$row->owner_name\",\"$full_ip_name\",\"$full_ip_address\",\"$full_ip_rdns\",\"$row->notes\"\n";

	}
	
	$full_export .= "\n";

	$temp_input_amount = $total_renewal_fee_export;
	$temp_input_conversion = "";
	include("_includes/system/convert-and-format-currency.inc.php");
	$total_export_renewal_fee = $temp_output_amount;

	$full_export .= "\"\",\"\",\"Total Cost:\",\"" . $total_export_renewal_fee . "\",\"" . $default_currency . "\"\n";
	
	$export = "0";
	
header('Content-Type: text/plain');
$full_content_disposition = "Content-Disposition: attachment; filename=\"ssl_renewals_$new_expiry_start--$new_expiry_end.csv\"";
header("$full_content_disposition");
header('Content-Transfer-Encoding: binary');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
echo $full_export;
exit;
}
?>
<?php include("_includes/doctype.inc.php"); ?>
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
                        </form><BR>
					</td>
					<td class="search-table-inside" width="200" valign="middle" align="center">
						<?php if (mysql_num_rows($result) > 0) { ?>
                                <a href="ssl-cert-renewals.php?export=1&new_expiry_start=<?=$new_expiry_start?>&new_expiry_end=<?=$new_expiry_end?>">Export Results</a><BR>
                        <?php } ?>
					</td>
				</tr>
			</table>

		</td>
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
<?php if ($_SESSION['session_display_ssl_fee'] == "1") { ?>
	<td class="main_table_cell_heading_active">
		<?php
        $sql_currency = "SELECT currency
                         FROM currencies
                         WHERE default_currency = '1'";
        $result_currency = mysql_query($sql_currency,$connection);
        while ($row_currency = mysql_fetch_object($result_currency)) { $temp_currency = $row_currency->currency; }
        ?>
    	<font class="main_table_heading">Fee (<?=$temp_currency?>)</font>
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
<?php if ($_SESSION['session_display_ssl_fee'] == "1") { ?>
	<td class="main_table_cell_active">
		<?php
		$temp_input_amount = $row->renewal_fee;
		$temp_input_conversion = $row->conversion;
		include("_includes/system/convert-and-format-currency.inc.php");
		echo $temp_output_amount;
		?>
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
</tr>
<?php } ?>
</table>
<?php
$temp_input_amount = $total_renewal_cost;
$temp_input_conversion = "";
include("_includes/system/convert-and-format-currency.inc.php");
$total_cost = $temp_output_amount;
?>
<BR><strong>Total Cost:</strong> <?=$total_cost?> <?=$default_currency?><BR>
<?php } else { ?>
<BR>The results that will be shown below will display the same columns as you have on your <a href="ssl-certs.php">SSL Certificates</a> page, but when you export the results you will be given even more information.<BR><BR>
The full list of fields in the export is:<BR><BR>
Certificate Status<BR>
Expiry Date<BR>
Renewal Fee<BR>
Total Renewal Cost<BR>
SSL Cert Name<BR>
Associated Domain<BR>
SSL Provider<BR>
SSL Account<BR>
SSL Type<BR>
Owner<BR>
IP Address Name<BR>
IP Address<BR>
IP Address rDNS<BR>
Notes<BR>
<?php } ?>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>