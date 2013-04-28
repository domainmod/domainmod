<?php
// /domain-renewals.php
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
include("_includes/system/functions/check-date-format.inc.php");

$page_title = "Domain Renewal Export";

// Form Variables
$export = $_GET['export'];
$all = $_GET['all'];
$new_expiry_start = $_REQUEST['new_expiry_start'];
$new_expiry_end = $_REQUEST['new_expiry_end'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if (!CheckDateFormat($new_expiry_start) || !CheckDateFormat($new_expiry_end) || $new_expiry_start > $new_expiry_end) {

		if (!CheckDateFormat($new_expiry_start)) $_SESSION['result_message'] .= "The start date is invalid<BR>";
		if (!CheckDateFormat($new_expiry_end)) $_SESSION['result_message'] .= "The end date is invalid<BR>";
		if ($new_expiry_start > $new_expiry_end) $_SESSION['result_message'] .= "The end date proceeds the start date<BR>";

	}

	$all = "0";

}

if ($all == "1") {

	$range_string = "";
	
} else {

	$range_string = " AND d.expiry_date between '$new_expiry_start' AND '$new_expiry_end' ";
	
}

$sql = "SELECT d.id, d.domain, d.tld, d.expiry_date, d.function, d.notes, d.privacy, d.active, ra.username, r.name AS registrar_name, o.name AS owner_name, f.initial_fee, f.renewal_fee, cc.conversion, cat.name AS category_name, cat.stakeholder AS category_stakeholder, dns.name AS dns_profile, ip.name, ip.ip, ip.rdns, h.name AS wh_name
		FROM domains AS d, registrar_accounts AS ra, registrars AS r, owners AS o, fees AS f, currencies AS cc, categories AS cat, dns, ip_addresses AS ip, hosting AS h
		WHERE d.account_id = ra.id
		  AND ra.registrar_id = r.id
		  AND ra.owner_id = o.id
		  AND d.registrar_id = f.registrar_id
		  AND d.tld = f.tld
		  AND f.currency_id = cc.id
		  AND d.cat_id = cat.id
		  AND d.dns_id = dns.id
		  AND d.ip_id = ip.id
		  AND d.hosting_id = h.id
		  AND d.active NOT IN ('0', '10')
		  " . $range_string . "
		ORDER BY d.expiry_date asc, d.domain";	
$result = mysql_query($sql,$connection) or die(mysql_error());
$total_results = mysql_num_rows($result);

$full_export = "";

if ($export == "1") {

	$result = mysql_query($sql,$connection) or die(mysql_error());

	$full_export .= "\"All fees are listed in " . $_SESSION['default_currency'] . "\"\n\n";

	$full_export .= "\"Domain Status\",\"Expiry Date\",\"Renew?\",\"Initial Fee\",\"Renewal Fee\",\"Domain\",\"TLD\",\"Function\",\"WHOIS Status\",\"Registrar\",\"Username\",\"DNS Profile\",\"IP Address Name\",\"IP Address\",\"IP Address rDNS\",\"Web Host\",\"Category\",\"Category Stakeholder\",\"Owner\",\"Notes\"\n";

	while ($row = mysql_fetch_object($result)) {

		$temp_initial_fee = $row->initial_fee * $row->conversion;
		$total_initial_fee_export = $total_initial_fee_export + $temp_initial_fee;

		$temp_renewal_fee = $row->renewal_fee * $row->conversion;
		$total_renewal_fee_export = $total_renewal_fee_export + $temp_renewal_fee;

		if ($row->active == "0") { $domain_status = "EXPIRED"; } 
		elseif ($row->active == "1") { $domain_status = "ACTIVE"; } 
		elseif ($row->active == "2") { $domain_status = "IN TRANSFER"; } 
		elseif ($row->active == "3") { $domain_status = "PENDING (RENEWAL)"; } 
		elseif ($row->active == "4") { $domain_status = "PENDING (OTHER)"; } 
		elseif ($row->active == "5") { $domain_status = "PENDING (REGISTRATION)"; } 
		elseif ($row->active == "10") { $domain_status = "SOLD"; } 
		else { $domain_status = "ERROR -- PROBLEM WITH CODE IN DOMAIN-RENEWALS.PHP"; } 
		
		if ($row->privacy == "1") {
			$privacy_status = "Private";
		} elseif ($row->privacy == "0") {
			$privacy_status = "Public";
		}

		$temp_input_amount = $temp_initial_fee;
		$temp_input_conversion = "";
		$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
		$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
		$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
		include("_includes/system/convert-and-format-currency.inc.php");
		$export_initial_fee = $temp_output_amount;

		$temp_input_amount = $temp_renewal_fee;
		$temp_input_conversion = "";
		$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
		$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
		$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
		include("_includes/system/convert-and-format-currency.inc.php");
		$export_renewal_fee = $temp_output_amount;

		$full_export .= "\"$domain_status\",\"$row->expiry_date\",\"\",\"" . $export_initial_fee . "\",\"" . $export_renewal_fee . "\",\"$row->domain\",\".$row->tld\",\"$row->function\",\"$privacy_status\",\"$row->registrar_name\",\"$row->username\",\"$row->dns_profile\",\"$row->name\",\"$row->ip\",\"$row->rdns\",\"$row->wh_name\",\"$row->category_name\",\"$row->category_stakeholder\",\"$row->owner_name\",\"$row->notes\"\n";
	}
	
	$full_export .= "\n";

	$temp_input_amount = $total_initial_fee_export;
	$temp_input_conversion = "";
	$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
	$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
	$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
	include("_includes/system/convert-and-format-currency.inc.php");
	$total_export_initial_fee = $temp_output_amount;

	$temp_input_amount = $total_renewal_fee_export;
	$temp_input_conversion = "";
	$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
	$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
	$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
	include("_includes/system/convert-and-format-currency.inc.php");
	$total_export_renewal_fee = $temp_output_amount;

	$full_export .= "\"\",\"\",\"Total Cost:\",\"" . $total_export_initial_fee . "\",\"" . $total_export_renewal_fee . "\",\n";

	$current_timestamp_unix = strtotime($current_timestamp);
	if ($all == "1") {
		$export_filename = "domain_renewals_all_" . $current_timestamp_unix . ".csv";
	} else {
		$export_filename = "domain_renewals_" . $new_expiry_start . "--" . $new_expiry_end . ".csv";
	}
	include("_includes/system/export-to-csv.inc.php");
	exit;
}
?>
<?php include("_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("_includes/header.inc.php"); ?>
Before exporting your domains you should <a href="system/update-conversion-rates.php">update the conversion rates</a>.<BR>
<BR>
<?php include("_includes/layout/table-export-top.inc.php"); ?>
    <form name="export_domains_form" method="post" action="<?=$PHP_SELF?>"> 
        <a href="<?=$PHP_SELF?>?all=1">View All</a> or Expiring Between 
        <input name="new_expiry_start" type="text" size="10" maxlength="10" <?php if ($new_expiry_start == "") { echo "value=\"$current_timestamp_basic\""; } else { echo "value=\"$new_expiry_start\""; } ?>> 
        and 
        <input name="new_expiry_end" type="text" size="10" maxlength="10" <?php if ($new_expiry_end == "") { echo "value=\"$current_timestamp_basic\""; } else { echo "value=\"$new_expiry_end\""; } ?>> 
        &nbsp;&nbsp;<input type="submit" name="button" value="Show Expiring &raquo;"> 
        <?php if ($total_results > 0) { ?>
        &nbsp;&nbsp;[<a href="domain-renewals.php?export=1&new_expiry_start=<?=$new_expiry_start?>&new_expiry_end=<?=$new_expiry_end?>&all=<?=$all?>">Export Results</a>]
        <?php } ?>
    </form>
<?php include("_includes/layout/table-export-bottom.inc.php"); ?>
<?php if ($total_results > 0) { ?>
<BR><strong>Number of Domains to Export:</strong> <?=number_format($total_results)?><BR><BR>
<table class="main_table">
<tr class="main_table_row_heading_active">
<?php if ($_SESSION['display_domain_expiry_date'] == "1") { ?>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">Expiry Date</font>
    </td>
<?php } ?>
<?php if ($_SESSION['display_domain_fee'] == "1") { ?>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">Fee</font>
    </td>
<?php } ?>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">Domain Name</font>
    </td>
<?php if ($_SESSION['display_domain_tld'] == "1") { ?>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">TLD</font>
    </td>
<?php } ?>
<?php if ($_SESSION['display_domain_registrar'] == "1") { ?>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">Registrar</font>
    </td>
<?php } ?>
<?php if ($_SESSION['display_domain_account'] == "1") { ?>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">Registrar Account</font>
    </td>
<?php } ?>
<?php if ($_SESSION['display_domain_dns'] == "1") { ?>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">DNS Profile</font>
    </td>
<?php } ?>
<?php if ($_SESSION['display_domain_ip'] == "1") { ?>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">IP Address</font>
    </td>
<?php } ?>
<?php if ($_SESSION['display_domain_host'] == "1") { ?>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">Web Host</font>
    </td>
<?php } ?>
<?php if ($_SESSION['display_domain_category'] == "1") { ?>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">Category</font>
    </td>
<?php } ?>
<?php if ($_SESSION['display_domain_owner'] == "1") { ?>
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
<?php if ($_SESSION['display_domain_expiry_date'] == "1") { ?>
	<td class="main_table_cell_active">
		<?=$row->expiry_date?>
	</td>
<?php } ?>
<?php if ($_SESSION['display_domain_fee'] == "1") { ?>
	<td class="main_table_cell_active">
		<?php
		$temp_input_amount = $row->renewal_fee;
		$temp_input_conversion = $row->conversion;
		$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
		$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
		$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
		include("_includes/system/convert-and-format-currency.inc.php");
		echo $temp_output_amount;
		?>
	</td>
<?php } ?>
	<td class="main_table_cell_active">
		<?=$row->domain?>
	</td>
<?php if ($_SESSION['display_domain_tld'] == "1") { ?>
	<td class="main_table_cell_active">
		.<?=$row->tld?>
	</td>
<?php } ?>
<?php if ($_SESSION['display_domain_registrar'] == "1") { ?>
	<td class="main_table_cell_active">
		<?=$row->registrar_name?>
    </td>
<?php } ?>
<?php if ($_SESSION['display_domain_account'] == "1") { ?>
	<td class="main_table_cell_active">
		<?=$row->registrar_name?> (<?=substr($row->username, 0, 15);?><?php if (strlen($row->username) >= 16) echo "..."; ?>)
    </td>
<?php } ?>
<?php if ($_SESSION['display_domain_dns'] == "1") { ?>
	<td class="main_table_cell_active">
		<?=$row->dns_profile?>
	</td>
<?php } ?>
<?php if ($_SESSION['display_domain_ip'] == "1") { ?>
	<td class="main_table_cell_active">
		<?=$row->name?> (<?=$row->ip?>)
	</td>
<?php } ?>
<?php if ($_SESSION['display_domain_host'] == "1") { ?>
	<td class="main_table_cell_active">
		<?=$row->wh_name?>
	</td>
<?php } ?>
<?php if ($_SESSION['display_domain_category'] == "1") { ?>
	<td class="main_table_cell_active">
		<?=$row->category_name?>
	</td>
<?php } ?>
<?php if ($_SESSION['display_domain_owner'] == "1") { ?>
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
$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
include("_includes/system/convert-and-format-currency.inc.php");
$total_cost = $temp_output_amount;
?>
<BR><strong>Total Cost:</strong> <?=$total_cost?> <?=$_SESSION['default_currency']?><BR>
<?php } else {?>
<BR>The results that will be shown below will display the same columns as you have on your <a href="domains.php">Domains</a> page, but when you export the results you will be given even more information.<BR><BR>
The full list of fields in the export is:<BR><BR>
Domain Status<BR>
Expiry Date<BR>
Initial Fee<BR>
Total Initial Cost<BR>
Renewal Fee<BR>
Total Renewal Cost<BR>
Domain<BR>
TLD<BR>
Domain Function<BR>
WHOIS Status<BR>
Domain Registrar<BR>
Registrar Account<BR>
DNS Profile<BR>
IP Address Name<BR>
IP Address<BR>
IP Address rDNS<BR>
Web Hosting Provider<BR>
Category<BR>
Category Stakeholder<BR>
Owner<BR>
Notes<BR>
<?php } ?>
<?php include("_includes/footer.inc.php"); ?>
</body>
</html>