<?php
/**
 * /reporting/domains/renewals.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (C) 2010-2015 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: http://chetcuti.com
 *
 * DomainMOD is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later
 * version.
 *
 * DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with DomainMOD. If not, see
 * http://www.gnu.org/licenses/.
 *
 */
?>
<?php
include("../../_includes/start-session.inc.php");
include("../../_includes/config.inc.php");
include("../../_includes/database.inc.php");
include("../../_includes/software.inc.php");
include("../../_includes/auth/auth-check.inc.php");
include("../../_includes/timestamps/current-timestamp.inc.php");
include("../../_includes/timestamps/current-timestamp-basic.inc.php");
include("../../_includes/system/functions/check-date-format.inc.php");
include("../../_includes/system/functions/error-reporting.inc.php");

$page_title = $reporting_section_title;
$page_subtitle = "Domain Renewal Report";
$software_section = "reporting-domain-renewal-report";
$report_name = "domain-renewal-report";

// Form Variables
$export = $_GET['export'];
$all = $_GET['all'];
$new_expiry_start = $_REQUEST['new_expiry_start'];
$new_expiry_end = $_REQUEST['new_expiry_end'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if (!checkDateFormat($new_expiry_start) || !checkDateFormat($new_expiry_end) || $new_expiry_start > $new_expiry_end) {

		if (!checkDateFormat($new_expiry_start)) $_SESSION['result_message'] .= "The start date is invalid<BR>";
		if (!checkDateFormat($new_expiry_end)) $_SESSION['result_message'] .= "The end date is invalid<BR>";
		if ($new_expiry_start > $new_expiry_end) $_SESSION['result_message'] .= "The end date proceeds the start date<BR>";

	}

	$all = "0";

}

if ($all == "1") {

	$range_string = "";
	
} else {

	$range_string = " AND d.expiry_date between '$new_expiry_start' AND '$new_expiry_end' ";
	
}

$sql = "SELECT d.id, d.domain, d.tld, d.expiry_date, d.function, d.notes, d.privacy, d.active, d.insert_time, d.update_time, ra.username, r.name AS registrar_name, o.name AS owner_name, (d.total_cost * cc.conversion) AS converted_renewal_fee, cc.conversion, cat.name AS category_name, cat.stakeholder AS category_stakeholder, dns.name AS dns_profile, ip.name, ip.ip, ip.rdns, h.name AS wh_name
		FROM domains AS d, registrar_accounts AS ra, registrars AS r, owners AS o, fees AS f, currencies AS c, currency_conversions AS cc, categories AS cat, dns, ip_addresses AS ip, hosting AS h
		WHERE d.account_id = ra.id
		  AND ra.registrar_id = r.id
		  AND ra.owner_id = o.id
		  AND d.registrar_id = f.registrar_id
		  AND d.tld = f.tld
		  AND f.currency_id = c.id
		  AND c.id = cc.currency_id
		  AND d.cat_id = cat.id
		  AND d.dns_id = dns.id
		  AND d.ip_id = ip.id
		  AND d.hosting_id = h.id
		  AND cc.user_id = '" . $_SESSION['user_id'] . "'
		  AND d.active NOT IN ('0', '10')
		  " . $range_string . "
		ORDER BY d.expiry_date asc, d.domain";	
$result = mysqli_query($connection, $sql) or outputOldSqlError($connection);
$total_results = mysqli_num_rows($result);

$result_cost = mysqli_query($connection, $sql) or outputOldSqlError($connection);
$total_cost = 0;
while ($row_cost = mysqli_fetch_object($result_cost)) {
	$temp_total_cost = $temp_total_cost + $row_cost->converted_renewal_fee;
}

$temp_input_amount = $temp_total_cost;
$temp_input_conversion = "";
$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
include("../../_includes/system/convert-and-format-currency.inc.php");
$total_cost = $temp_output_amount;

if ($export == "1") {

	$result = mysqli_query($connection, $sql) or outputOldSqlError($connection);

	$current_timestamp_unix = strtotime($current_timestamp);
	if ($all == "1") {
		$export_filename = "domain_renewal_report_all_" . $current_timestamp_unix . ".csv";
	} else {
		$export_filename = "domain_renewal_report_" . $new_expiry_start . "--" . $new_expiry_end . ".csv";
	}
	include("../../_includes/system/export/header.inc.php");

	$row_content[$count++] = $page_subtitle;
	include("../../_includes/system/export/write-row.inc.php");

	fputcsv($file_content, $blank_line);

	if ($all != "1") {

		$row_content[$count++] = "Date Range:";
		$row_content[$count++] = $new_expiry_start;
		$row_content[$count++] = $new_expiry_end;

	} else {

		$row_content[$count++] = "Date Range:";
		$row_content[$count++] = "ALL";

	}
	include("../../_includes/system/export/write-row.inc.php");

	$row_content[$count++] = "Total Cost:";
	$row_content[$count++] = $total_cost;
	$row_content[$count++] = $_SESSION['default_currency'];
	include("../../_includes/system/export/write-row.inc.php");

	$row_content[$count++] = "Number of Domains:";
	$row_content[$count++] = number_format($total_results);
	include("../../_includes/system/export/write-row.inc.php");

	fputcsv($file_content, $blank_line);

	$row_content[$count++] = "Domain Status";
	$row_content[$count++] = "Expiry Date";
	$row_content[$count++] = "Renew?";
	$row_content[$count++] = "Renewal Fee";
	$row_content[$count++] = "Domain";
	$row_content[$count++] = "TLD";
	$row_content[$count++] = "Function";
	$row_content[$count++] = "WHOIS Status";
	$row_content[$count++] = "Registrar";
	$row_content[$count++] = "Registrar Account";
	$row_content[$count++] = "Username";
	$row_content[$count++] = "DNS Profile";
	$row_content[$count++] = "IP Address Name";
	$row_content[$count++] = "IP Address";
	$row_content[$count++] = "IP Address rDNS";
	$row_content[$count++] = "Web Host";
	$row_content[$count++] = "Category";
	$row_content[$count++] = "Category Stakeholder";
	$row_content[$count++] = "Owner";
	$row_content[$count++] = "Notes";

	$sql_field = "SELECT name
				  FROM domain_fields
				  ORDER BY name";
	$result_field = mysqli_query($connection, $sql_field);

	while ($row_field = mysqli_fetch_object($result_field)) {
		
		$row_content[$count++] = $row_field->name;
	
	}

	$row_content[$count++] = "Inserted";
	$row_content[$count++] = "Updated";
	include("../../_includes/system/export/write-row.inc.php");

	while ($row = mysqli_fetch_object($result)) {

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

		$temp_input_amount = $row->converted_renewal_fee;
		$temp_input_conversion = "";
		$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
		$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
		$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
		include("../../_includes/system/convert-and-format-currency.inc.php");
		$export_renewal_fee = $temp_output_amount;

		$row_content[$count++] = $domain_status;
		$row_content[$count++] = $row->expiry_date;
		$row_content[$count++] = "";
		$row_content[$count++] = $export_renewal_fee;
		$row_content[$count++] = $row->domain;
		$row_content[$count++] = "." . $row->tld;
		$row_content[$count++] = $row->function;
		$row_content[$count++] = $privacy_status;
		$row_content[$count++] = $row->registrar_name;
		$row_content[$count++] = $row->registrar_name . ", " . $row->owner_name . " (" . $row->username . ")";
		$row_content[$count++] = $row->username;
		$row_content[$count++] = $row->dns_profile;
		$row_content[$count++] = $row->name;
		$row_content[$count++] = $row->ip;
		$row_content[$count++] = $row->rdns;
		$row_content[$count++] = $row->wh_name;
		$row_content[$count++] = $row->category_name;
		$row_content[$count++] = $row->category_stakeholder;
		$row_content[$count++] = $row->owner_name;
		$row_content[$count++] = $row->notes;

		$sql_field = "SELECT field_name
					  FROM domain_fields
					  ORDER BY name";
		$result_field = mysqli_query($connection, $sql_field);
		
		$array_count = 0;
		$field_data = "";
		
		while ($row_field = mysqli_fetch_object($result_field)) {
			
			$field_array[$array_count] = $row_field->field_name;
			$array_count++;
		
		}
		
		foreach($field_array as $field) {
		
			$sql_data = "SELECT " . $field . " 
						 FROM domain_field_data
						 WHERE domain_id = '" . $row->id . "'";
			$result_data = mysqli_query($connection, $sql_data);
			
			while ($row_data = mysqli_fetch_object($result_data)) {
		
				$row_content[$count++] = $row_data->{$field};
			
			}
		
		}

		$row_content[$count++] = $row->insert_time;
		$row_content[$count++] = $row->update_time;
		include("../../_includes/system/export/write-row.inc.php");

	}

	include("../../_includes/system/export/footer.inc.php");

}
?>
<?php include("../../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include("../../_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../../_includes/layout/header.inc.php"); ?>
<?php include("../../_includes/layout/reporting-block.inc.php"); ?>
<?php include("../../_includes/layout/table-export-top.inc.php"); ?>
    <form name="export_domains_form" method="post" action="<?php echo $PHP_SELF; ?>">
        <a href="<?php echo $PHP_SELF; ?>?all=1">View All</a> or Expiring Between
        <input name="new_expiry_start" type="text" size="10" maxlength="10" <?php if ($new_expiry_start == "") { echo "value=\"$current_timestamp_basic\""; } else { echo "value=\"$new_expiry_start\""; } ?>> 
        and 
        <input name="new_expiry_end" type="text" size="10" maxlength="10" <?php if ($new_expiry_end == "") { echo "value=\"$current_timestamp_basic\""; } else { echo "value=\"$new_expiry_end\""; } ?>> 
        &nbsp;&nbsp;<input type="submit" name="button" value="Generate Report &raquo;"> 
        <?php if ($total_results > 0) { ?>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>[<a href="<?php echo $PHP_SELF; ?>?export=1&new_expiry_start=<?php echo $new_expiry_start; ?>&new_expiry_end=<?php echo $new_expiry_end; ?>&all=<?php echo $all; ?>">EXPORT REPORT</a>]</strong>
        <?php } ?>
    </form>
<?php include("../../_includes/layout/table-export-bottom.inc.php"); ?>
<?php if ($total_results > 0) { ?>
<BR><font class="subheadline"><?php echo $page_subtitle; ?></font><BR><BR>
<?php if ($all != "1") { ?>
	<strong>Date Range:</strong> <?php echo $new_expiry_start; ?> - <?php echo $new_expiry_end; ?><BR><BR>
<?php } else { ?>
	<strong>Date Range:</strong> ALL<BR><BR>
<?php } ?>
<strong>Total Cost:</strong> <?php echo $total_cost; ?> <?php echo $_SESSION['default_currency']; ?><BR><BR>
<strong>Number of Domains:</strong> <?php echo number_format($total_results); ?><BR>
<table class="main_table" cellpadding="0" cellspacing="0">
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
<?php while ($row = mysqli_fetch_object($result)) { ?>
<tr class="main_table_row_active">
<?php if ($_SESSION['display_domain_expiry_date'] == "1") { ?>
	<td class="main_table_cell_active">
		<?php echo $row->expiry_date; ?>
	</td>
<?php } ?>
<?php if ($_SESSION['display_domain_fee'] == "1") { ?>
	<td class="main_table_cell_active">
		<?php
		$temp_input_amount = $row->converted_renewal_fee;
		$temp_input_conversion = "";
		$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
		$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
		$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
		include("../../_includes/system/convert-and-format-currency.inc.php");
		echo $temp_output_amount;
		?>
	</td>
<?php } ?>
	<td class="main_table_cell_active">
		<?php echo $row->domain; ?>
	</td>
<?php if ($_SESSION['display_domain_tld'] == "1") { ?>
	<td class="main_table_cell_active">
		.<?php echo $row->tld; ?>
	</td>
<?php } ?>
<?php if ($_SESSION['display_domain_registrar'] == "1") { ?>
	<td class="main_table_cell_active">
		<?php echo $row->registrar_name; ?>
    </td>
<?php } ?>
<?php if ($_SESSION['display_domain_account'] == "1") { ?>
	<td class="main_table_cell_active">
		<?php echo $row->registrar_name; ?>, <?php echo $row->owner_name; ?> (<?php echo substr($row->username, 0, 15); ?><?php if (strlen($row->username) >= 16) echo "..."; ?>)
    </td>
<?php } ?>
<?php if ($_SESSION['display_domain_dns'] == "1") { ?>
	<td class="main_table_cell_active">
		<?php echo $row->dns_profile; ?>
	</td>
<?php } ?>
<?php if ($_SESSION['display_domain_ip'] == "1") { ?>
	<td class="main_table_cell_active">
		<?php echo $row->name; ?> (<?php echo $row->ip; ?>)
	</td>
<?php } ?>
<?php if ($_SESSION['display_domain_host'] == "1") { ?>
	<td class="main_table_cell_active">
		<?php echo $row->wh_name; ?>
	</td>
<?php } ?>
<?php if ($_SESSION['display_domain_category'] == "1") { ?>
	<td class="main_table_cell_active">
		<?php echo $row->category_name; ?>
	</td>
<?php } ?>
<?php if ($_SESSION['display_domain_owner'] == "1") { ?>
	<td class="main_table_cell_active">
		<?php echo $row->owner_name; ?>
    </td>
<?php } ?>
</tr>
<?php } ?>
</table>
<?php } else {?>
<BR>The results that will be shown below will display the same columns as you have on your <a href="domains.php">Domains</a> page, but when you export the results you will be given even more information.<BR><BR>
The full list of fields in the export is:<BR><BR>
Domain Status<BR>
Expiry Date<BR>
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
Insert Time<BR>
Last Update Time<BR>
<?php
$sql = "SELECT name
		FROM domain_fields
		ORDER BY name";
$result = mysqli_query($connection, $sql);

if (mysqli_num_rows($result) > 0) {

	echo "<BR><strong>Custom Fields</strong><BR>";

	while ($row = mysqli_fetch_object($result)) {
		echo $row->name . "<BR>";
	}
	
}
?>
<?php } ?>
<?php include("../../_includes/layout/footer.inc.php"); ?>
</body>
</html>
