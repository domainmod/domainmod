<?php
/**
 * /reporting/ssl/renewals.php
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
include("../../_includes/init.inc.php");
include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "database.inc.php");
include(DIR_INC . "auth/auth-check.inc.php");
include(DIR_INC . "timestamps/current-timestamp.inc.php");
include(DIR_INC . "timestamps/current-timestamp-basic.inc.php");
include(DIR_INC . "classes/Date.class.php");
include(DIR_INC . "classes/Error.class.php");
include(DIR_INC . "classes/Export.class.php");

$error = new DomainMOD\Error();

$page_title = $reporting_section_title;
$page_subtitle = "SSL Certificate Renewal Report";
$software_section = "reporting-ssl-renewal-report";
$report_name = "ssl-renewal-report";

// Form Variables
$export_data = $_GET['export_data'];
$all = $_GET['all'];
$new_start_date = $_REQUEST['new_start_date'];
$new_end_date = $_REQUEST['new_end_date'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $date = new DomainMOD\Date();

    if (!$date->checkDateFormat($new_start_date) || !$date->checkDateFormat($new_end_date) || $new_start_date > $new_end_date) {

        if (!$date->checkDateFormat($new_start_date)) $_SESSION['result_message'] .= "The start date is invalid<BR>";
		if (!$date->checkDateFormat($new_end_date)) $_SESSION['result_message'] .= "The end date is invalid<BR>";
		if ($new_start_date > $new_end_date) $_SESSION['result_message'] .= "The end date proceeds the start date<BR>";

	}

	$all = "0";

}

if ($all == "1") {

	$range_string = "";
	
} else {

	$range_string = " AND sslc.expiry_date between '$new_start_date' AND '$new_end_date' ";
	
}

$sql = "SELECT sslc.id, sslc.domain_id, sslc.name, sslcf.type, sslc.expiry_date, sslc.notes, sslc.active, sslc.insert_time, sslc.update_time, sslpa.username, sslp.name AS ssl_provider_name, o.name AS owner_name, (sslc.total_cost * cc.conversion) AS converted_renewal_fee, cc.conversion, d.domain, ip.name AS ip_name, ip.ip, ip.rdns, cat.name AS cat_name
		FROM ssl_certs AS sslc, ssl_accounts AS sslpa, ssl_providers AS sslp, owners AS o, ssl_fees AS f, currencies AS c, currency_conversions AS cc, domains AS d, ssl_cert_types AS sslcf, ip_addresses AS ip, categories AS cat
		WHERE sslc.account_id = sslpa.id
		  AND sslc.type_id = sslcf.id
		  AND sslpa.ssl_provider_id = sslp.id
		  AND sslpa.owner_id = o.id
		  AND sslc.ssl_provider_id = f.ssl_provider_id
		  AND sslc.type_id = f.type_id
		  AND f.currency_id = c.id
		  AND c.id = cc.currency_id
		  AND sslc.domain_id = d.id
		  AND sslc.ip_id = ip.id
		  AND sslc.cat_id = cat.id
		  AND cc.user_id = '" . $_SESSION['user_id'] . "'
		  AND sslc.active NOT IN ('0')
		  " . $range_string . "
		ORDER BY sslc.expiry_date asc, sslc.name asc";	
$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
$total_results = mysqli_num_rows($result);

$result_cost = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
$total_cost = 0;
while ($row_cost = mysqli_fetch_object($result_cost)) {
	$temp_total_cost = $temp_total_cost + $row_cost->converted_renewal_fee;
}

$temp_input_amount = $temp_total_cost;
$temp_input_conversion = "";
$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
include(DIR_INC . "system/convert-and-format-currency.inc.php");
$total_cost = $temp_output_amount;

if ($export_data == "1") {

	$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $export = new DomainMOD\Export();

    if ($all == "1") {

        $export_file = $export->openFile('ssl_renewal_report_all');

    } else {

        $export_file = $export->openFileAppend(
            'ssl_renewal_report',
            $new_start_date . '--' . $new_end_date
        );

    }

    $row_contents = array($page_subtitle);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    if ($all != "1") {

        $row_contents = array('Date Range:', $new_start_date, $new_end_date);

    } else {

        $row_contents = array('Date Range:', 'ALL');

    }
    $export->writeRow($export_file, $row_contents);

    $row_contents = array(
        'Total Renewal Cost:',
        $total_cost,
        $_SESSION['default_currency']
    );
    $export->writeRow($export_file, $row_contents);

    $row_contents = array(
        'Number of SSL Certificates:',
        number_format($total_results)
    );
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    unset($row_contents);
    $count = 0;

    $row_contents[$count++] = 'SSL Cert Status';
    $row_contents[$count++] = 'Expiry Date';
    $row_contents[$count++] = 'Renew?';
    $row_contents[$count++] = 'Renewal Fee';
    $row_contents[$count++] = 'Host / Label';
    $row_contents[$count++] = 'Domain';
    $row_contents[$count++] = 'SSL Provider';
    $row_contents[$count++] = 'SSL Provider Account';
    $row_contents[$count++] = 'Username';
    $row_contents[$count++] = 'SSL Type';
    $row_contents[$count++] = 'IP Address Name';
    $row_contents[$count++] = 'IP Address';
    $row_contents[$count++] = 'IP Address rDNS';
    $row_contents[$count++] = 'Category';
    $row_contents[$count++] = 'Owner';
    $row_contents[$count++] = 'Notes';

	$sql_field = "SELECT name
				  FROM ssl_cert_fields
				  ORDER BY name";
	$result_field = mysqli_query($connection, $sql_field);
	
	while ($row_field = mysqli_fetch_object($result_field)) {
		
		$row_contents[$count++] = $row_field->name;
	
	}

	$row_contents[$count++] = 'Inserted';
	$row_contents[$count++] = 'Updated';

    $export->writeRow($export_file, $row_contents);

    while ($row = mysqli_fetch_object($result)) {
		
		if ($row->active == "0") { $ssl_status = "EXPIRED"; } 
		elseif ($row->active == "1") { $ssl_status = "ACTIVE"; } 
		elseif ($row->active == "3") { $ssl_status = "PENDING (RENEWAL)"; } 
		elseif ($row->active == "4") { $ssl_status = "PENDING (OTHER)"; } 
		elseif ($row->active == "5") { $ssl_status = "PENDING (REGISTRATION)"; } 
		else { $ssl_status = "ERROR -- PROBLEM WITH CODE IN SSL-CERT-RENEWALS.PHP"; } 
		
		$temp_input_amount = $row->converted_renewal_fee;
		$temp_input_conversion = "";
		$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
		$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
		$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
		include(DIR_INC . "system/convert-and-format-currency.inc.php");
		$export_renewal_fee = $temp_output_amount;

        unset($row_contents);
        $count = 0;

		$row_contents[$count++] = $ssl_status;
		$row_contents[$count++] = $row->expiry_date;
		$row_contents[$count++] = '';
		$row_contents[$count++] = $export_renewal_fee;
		$row_contents[$count++] = $row->name;
		$row_contents[$count++] = $row->domain;
		$row_contents[$count++] = $row->ssl_provider_name;
		$row_contents[$count++] = $row->ssl_provider_name . ', ' . $row->owner_name . ' (' . $row->username . ')';
		$row_contents[$count++] = $row->username;
		$row_contents[$count++] = $row->type;
		$row_contents[$count++] = $row->ip_name;
		$row_contents[$count++] = $row->ip;
		$row_contents[$count++] = $row->rdns;
		$row_contents[$count++] = $row->cat_name;
		$row_contents[$count++] = $row->owner_name;
		$row_contents[$count++] = $row->notes;

		$sql_field = "SELECT field_name
					  FROM ssl_cert_fields
					  ORDER BY name";
		$result_field = mysqli_query($connection, $sql_field);

        if (mysqli_num_rows($result_field) > 0) {

            $array_count = 0;
            $field_data = "";

            while ($row_field = mysqli_fetch_object($result_field)) {

                $field_array[$array_count] = $row_field->field_name;
                $array_count++;

            }

            foreach($field_array as $field) {

                $sql_data = "SELECT " . $field . "
						 FROM ssl_cert_field_data
						 WHERE ssl_id = '" . $row->id . "'";
                $result_data = mysqli_query($connection, $sql_data);

                while ($row_data = mysqli_fetch_object($result_data)) {

                    $row_contents[$count++] = $row_data->{$field};

                }

            }

        }

        $row_contents[$count++] = $row->insert_time;
		$row_contents[$count++] = $row->update_time;

        $export->writeRow($export_file, $row_contents);

    }

    $export->closeFile($export_file);

}
?>
<?php include(DIR_INC . "doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<?php include(DIR_INC . "layout/reporting-block.inc.php"); ?>
<?php include(DIR_INC . "layout/table-export-top.inc.php"); ?>
    <form name="export_ssl_certs_form" method="post">
        <a href="renewals.php?all=1">View All</a> or Expiring Between
        <input name="new_start_date" type="text" size="10" maxlength="10" <?php if ($new_start_date == "") { echo "value=\"$current_timestamp_basic\""; } else { echo "value=\"$new_start_date\""; } ?>> 
        and 
        <input name="new_end_date" type="text" size="10" maxlength="10" <?php if ($new_end_date == "") { echo "value=\"$current_timestamp_basic\""; } else { echo "value=\"$new_end_date\""; } ?>> 
        &nbsp;&nbsp;<input type="submit" name="button" value="Generate Report &raquo;"> 
        <?php if ($total_results > 0) { ?>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>[<a href="renewals.php?export_data=1&new_start_date=<?php echo $new_start_date; ?>&new_end_date=<?php echo $new_end_date; ?>&all=<?php echo $all; ?>">EXPORT REPORT</a>]</strong>
        <?php } ?>
    </form>
<?php include(DIR_INC . "layout/table-export-bottom.inc.php"); ?>
<?php if ($total_results > 0) { ?>
<BR><font class="subheadline"><?php echo $page_subtitle; ?></font><BR><BR>
<?php if ($all != "1") { ?>
	<strong>Date Range:</strong> <?php echo $new_start_date; ?> - <?php echo $new_end_date; ?><BR><BR>
<?php } else { ?>
	<strong>Date Range:</strong> ALL<BR><BR>
<?php } ?>
<strong>Total Cost:</strong> <?php echo $total_cost; ?> <?php echo $_SESSION['default_currency']; ?><BR><BR>
<strong>Number of SSL Certificates:</strong> <?php echo number_format($total_results); ?><BR>
<table class="main_table" cellpadding="0" cellspacing="0">
<tr class="main_table_row_heading_active">
<?php if ($_SESSION['display_ssl_expiry_date'] == "1") { ?>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">Expiry Date</font>
    </td>
<?php } ?>
<?php if ($_SESSION['display_ssl_fee'] == "1") { ?>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">Fee</font>
    </td>
<?php } ?>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">Host / Label</font>
    </td>
<?php if ($_SESSION['display_ssl_domain'] == "1") { ?>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">Domain</font>
    </td>
<?php } ?>
<?php if ($_SESSION['display_ssl_provider'] == "1") { ?>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">SSL Provider</font>
    </td>
<?php } ?>
<?php if ($_SESSION['display_ssl_account'] == "1") { ?>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">SSL Account</font>
    </td>
<?php } ?>
<?php if ($_SESSION['display_ssl_type'] == "1") { ?>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">Type</font>
    </td>
<?php } ?>
<?php if ($_SESSION['display_ssl_ip'] == "1") { ?>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">IP Address</font>
    </td>
<?php } ?>
<?php if ($_SESSION['display_ssl_category'] == "1") { ?>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">Category</font>
    </td>
<?php } ?>
<?php if ($_SESSION['display_ssl_owner'] == "1") { ?>
	<td class="main_table_cell_heading_active">
    	<font class="main_table_heading">Owner</font>
    </td>
<?php } ?>
</tr>
<?php while ($row = mysqli_fetch_object($result)) { ?>
<?php 
$renewal_fee_individual = $row->renewal_fee * $row->conversion;
$total_renewal_cost = $total_renewal_cost + $renewal_fee_individual; 
?>
<tr class="main_table_row_active">
<?php if ($_SESSION['display_ssl_expiry_date'] == "1") { ?>
	<td class="main_table_cell_active">
		<?php echo $row->expiry_date; ?>
	</td>
<?php } ?>
<?php if ($_SESSION['display_ssl_fee'] == "1") { ?>
	<td class="main_table_cell_active">
		<?php
		$temp_input_amount = $row->converted_renewal_fee;
		$temp_input_conversion = "";
		$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
		$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
		$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
		include(DIR_INC . "system/convert-and-format-currency.inc.php");
		echo $temp_output_amount;
		?>
	</td>
<?php } ?>
	<td class="main_table_cell_active">
		<?php echo $row->name; ?>
	</td>
<?php if ($_SESSION['display_ssl_domain'] == "1") { ?>
	<td class="main_table_cell_active">
		<?php echo $row->domain; ?>
	</td>
<?php } ?>
<?php if ($_SESSION['display_ssl_provider'] == "1") { ?>
	<td class="main_table_cell_active">
		<?php echo $row->ssl_provider_name; ?>
    </td>
<?php } ?>
<?php if ($_SESSION['display_ssl_account'] == "1") { ?>
	<td class="main_table_cell_active">
		<?php echo $row->ssl_provider_name; ?>, <?php echo $row->owner_name; ?> (<?php echo substr($row->username, 0, 15); ?><?php if (strlen($row->username) >= 16) echo "..."; ?>)
    </td>
<?php } ?>
<?php if ($_SESSION['display_ssl_type'] == "1") { ?>
	<td class="main_table_cell_active">
		<?php echo $row->type; ?>
    </td>
<?php } ?>
<?php if ($_SESSION['display_ssl_ip'] == "1") { ?>
	<td class="main_table_cell_active">
		<?php echo $row->ip_name; ?> (<?php echo $row->ip; ?>)
    </td>
<?php } ?>
<?php if ($_SESSION['display_ssl_category'] == "1") { ?>
	<td class="main_table_cell_active">
		<?php echo $row->cat_name; ?>
    </td>
<?php } ?>
<?php if ($_SESSION['display_ssl_owner'] == "1") { ?>
	<td class="main_table_cell_active">
		<?php echo $row->owner_name; ?>
    </td>
<?php } ?>
</tr>
<?php } ?>
</table>
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
IP Address Name<BR>
IP Address<BR>
IP Address rDNS<BR>
Category<BR>
Owner<BR>
Notes<BR>
Insert Time<BR>
Last Update Time<BR>
<?php
$sql = "SELECT name
		FROM ssl_cert_fields
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
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
