<?php
// /reporting/ssl/cost-by-provider.php
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
include("../../_includes/start-session.inc.php");
include("../../_includes/config.inc.php");
include("../../_includes/database.inc.php");
include("../../_includes/software.inc.php");
include("../../_includes/auth/auth-check.inc.php");
include("../../_includes/timestamps/current-timestamp.inc.php");
include("../../_includes/timestamps/current-timestamp-basic.inc.php");
include("../../_includes/system/functions/check-date-format.inc.php");

$page_title = $reporting_section_title;
$page_subtitle = "SSL Certificate Cost by Provider Report";
$software_section = "reporting";
$report_name = "ssl-cost-by-provider-report";

// Form Variables
$export = $_GET['export'];
$all = $_GET['all'];
$new_start_date = $_REQUEST['new_start_date'];
$new_end_date = $_REQUEST['new_end_date'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
		if ((!CheckDateFormat($new_start_date) || !CheckDateFormat($new_end_date)) || $new_start_date > $new_end_date) { 

			if (!CheckDateFormat($new_start_date)) $_SESSION['result_message'] .= "The start date is invalid<BR>";
			if (!CheckDateFormat($new_end_date)) $_SESSION['result_message'] .= "The end date is invalid<BR>";
			if ($new_start_date > $new_end_date) $_SESSION['result_message'] .= "The end date proceeds the start date<BR>";

			$submission_failed = "1";

		}

		$all = "0";

}

if ($all == "1") {

	$range_string = "";
	
} else {

	$range_string = " AND sslc.expiry_date between '" . $new_start_date . "' AND '" . $new_end_date . "' ";
	
}

$sql = "SELECT sslp.id, sslp.name AS provider_name, o.name AS owner_name, sslpa.username, SUM(f.renewal_fee * cc.conversion) as total_cost, count(*) AS number_of_certs
		FROM ssl_certs AS sslc, ssl_fees AS f, currencies AS c, currency_conversions AS cc, ssl_providers AS sslp, ssl_accounts AS sslpa, owners AS o
		WHERE sslc.fee_id = f.id
		  AND f.currency_id = c.id
		  AND c.id = cc.currency_id
		  AND sslc.ssl_provider_id = sslp.id
		  AND sslc.account_id = sslpa.id
		  AND sslc.owner_id = o.id
		  AND sslc.active NOT IN ('0')
		  AND cc.user_id = '" . $_SESSION['user_id'] . "'
		  " . $range_string . "
		GROUP BY sslp.name, o.name, sslpa.username
		ORDER BY sslp.name, o.name, sslpa.username";
$result = mysql_query($sql,$connection) or die(mysql_error());
$total_rows = mysql_num_rows($result);

$sql_grand_total = "SELECT SUM(f.renewal_fee * cc.conversion) as grand_total, count(*) AS number_of_certs_total
					FROM ssl_certs AS sslc, ssl_fees AS f, currencies AS c, currency_conversions AS cc
					WHERE sslc.fee_id = f.id
					  AND f.currency_id = c.id
					  AND c.id = cc.currency_id
					  AND sslc.active NOT IN ('0')
					  AND cc.user_id = '" . $_SESSION['user_id'] . "'
					  " . $range_string . "";
$result_grand_total = mysql_query($sql_grand_total,$connection) or die(mysql_error());
while ($row_grand_total = mysql_fetch_object($result_grand_total)) {
	$grand_total = $row_grand_total->grand_total;
	$number_of_certs_total = $row_grand_total->number_of_certs_total;
}

$temp_input_amount = $grand_total;
$temp_input_conversion = "";
$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
include("../../_includes/system/convert-and-format-currency.inc.php");
$grand_total = $temp_output_amount;

if ($submission_failed != "1" && $total_rows > 0) {

	if ($export == "1") {

		$full_export = "";
		$full_export .= "\"" . $page_subtitle . "\"\n\n";
		if ($all != "1") {
		    $full_export .= "\"Date Range:\",\"" . $new_start_date . "\",\"" . $new_end_date . "\"\n";
        } else {
		    $full_export .= "\"Date Range:\",\"ALL\"\n";
        }
		$full_export .= "\"Total Cost:\",\"" . $grand_total . "\",\"" . $_SESSION['default_currency'] . "\"\n";
		$full_export .= "\"Number of SSL Certs:\",\"" . $number_of_certs_total . "\"\n\n";
		$full_export .= "\"Provider\",\"Certs\",\"Cost\",\"Per Cert\",\"Provider Account\",\"Certs\",\"Cost\",\"Per Cert\"\n";

		$new_provider = "";
		$last_provider = "";
	
		while ($row = mysql_fetch_object($result)) {

			$new_provider = $row->provider_name;

			$sql_provider_total = "SELECT SUM(f.renewal_fee * cc.conversion) as provider_total, count(*) AS number_of_certs_provider
								   FROM ssl_certs AS sslc, ssl_fees AS f, currencies AS c, currency_conversions AS cc, ssl_providers AS sslp, ssl_accounts AS sslpa, owners AS o
								   WHERE sslc.fee_id = f.id
								     AND f.currency_id = c.id
									 AND c.id = cc.currency_id
									 AND sslc.ssl_provider_id = sslp.id
									 AND sslc.account_id = sslpa.id
									 AND sslc.owner_id = o.id
									 AND sslc.active NOT IN ('0')
									 AND cc.user_id = '" . $_SESSION['user_id'] . "'
									 AND sslp.id = '" . $row->id . "'
									 " . $range_string . "";
			$result_provider_total = mysql_query($sql_provider_total,$connection) or die(mysql_error());
			while ($row_provider_total = mysql_fetch_object($result_provider_total)) { 
				$temp_provider_total = $row_provider_total->provider_total; 
				$number_of_certs_provider = $row_provider_total->number_of_certs_provider; 
			}

			$per_cert_account = $row->total_cost / $row->number_of_certs;

			$temp_input_amount = $row->total_cost;
			$temp_input_conversion = "";
			$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
			$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
			$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
			include("../../_includes/system/convert-and-format-currency.inc.php");
			$row->total_cost = $temp_output_amount;

			$temp_input_amount = $per_cert_account;
			$temp_input_conversion = "";
			$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
			$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
			$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
			include("../../_includes/system/convert-and-format-currency.inc.php");
			$per_cert_account = $temp_output_amount;

			$per_cert_provider = $temp_provider_total / $number_of_certs_provider;

			$temp_input_amount = $temp_provider_total;
			$temp_input_conversion = "";
			$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
			$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
			$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
			include("../../_includes/system/convert-and-format-currency.inc.php");
			$temp_provider_total = $temp_output_amount;

			$temp_input_amount = $per_cert_provider;
			$temp_input_conversion = "";
			$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
			$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
			$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
			include("../../_includes/system/convert-and-format-currency.inc.php");
			$per_cert_provider = $temp_output_amount;

			$full_export .= "\"" . $row->provider_name . "\",\"" . $number_of_certs_provider . "\",\"" . $temp_provider_total . "\",\"" . $per_cert_provider . "\",\"" . $row->owner_name . " (" . $row->username . ")\",\"" . $row->number_of_certs . "\",\"" . $row->total_cost . "\",\"" . $per_cert_account . "\"\n";
			$last_provider = $row->provider_name;

		}

		$full_export .= "\n";

		$current_timestamp_unix = strtotime($current_timestamp);
		if ($all == "1") {
			$export_filename = "ssl_cost_by_provider_report_all_" . $current_timestamp_unix . ".csv";
		} else {
			$export_filename = "ssl_cost_by_provider_report_" . $new_start_date . "--" . $new_end_date . ".csv";
		}
		include("../../_includes/system/export-to-csv.inc.php");
		exit;
	}

}
?>
<?php include("../../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?> :: <?=$page_subtitle?></title>
<?php include("../../_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../../_includes/layout/header.inc.php"); ?>
<?php include("../../_includes/layout/reporting-block.inc.php"); ?>
<?php include("../../_includes/layout/table-export-top.inc.php"); ?>
    <form name="export_ssl_form" method="post" action="<?=$PHP_SELF?>"> 
        <a href="<?=$PHP_SELF?>?all=1">View All</a> or Expiring Between 
        <input name="new_start_date" type="text" size="10" maxlength="10" <?php if ($new_start_date == "") { echo "value=\"$current_timestamp_basic\""; } else { echo "value=\"$new_start_date\""; } ?>> 
        and 
        <input name="new_end_date" type="text" size="10" maxlength="10" <?php if ($new_end_date == "") { echo "value=\"$current_timestamp_basic\""; } else { echo "value=\"$new_end_date\""; } ?>> 
        &nbsp;&nbsp;<input type="submit" name="button" value="Generate Report &raquo;"> 
        <?php if ($total_rows > 0) { ?>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>[<a href="<?=$PHP_SELF?>?export=1&new_start_date=<?=$new_start_date?>&new_end_date=<?=$new_end_date?>&all=<?=$all?>">EXPORT REPORT</a>]</strong>
        <?php } ?>
    </form>
<?php include("../../_includes/layout/table-export-bottom.inc.php"); ?>
<?php
if ($submission_failed != "1" && $total_rows > 0) { ?>

	<BR><font class="subheadline"><?=$page_subtitle?></font><BR>
	<BR>
    <?php if ($all != "1") { ?>
	    <strong>Date Range:</strong> <?=$new_start_date?> - <?=$new_end_date?><BR><BR>
    <?php } else { ?>
	    <strong>Date Range:</strong> ALL<BR><BR>
    <?php } ?>
    <strong>Total Cost:</strong> <?=$grand_total?> <?=$_SESSION['default_currency']?><BR><BR>
    <strong>Number of SSL Certs:</strong> <?=$number_of_certs_total?><BR><BR>
    <table class="main_table">
    <tr class="main_table_row_heading_active">
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Provider</font></td>
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Certs</font></td>
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Cost</font></td>
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Per Cert</font></td>
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Provider Account</font></td>
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Certs</font></td>
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Cost</font></td>
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Per Cert</font></td>
    </tr>

	<?php
	$new_provider = "";
	$last_provider = "";

	while ($row = mysql_fetch_object($result)) {

		$new_provider = $row->provider_name;

		$sql_provider_total = "SELECT SUM(f.renewal_fee * cc.conversion) as provider_total, count(*) AS number_of_certs_provider
							   FROM ssl_certs AS sslc, ssl_fees AS f, currencies AS c, currency_conversions AS cc, ssl_providers AS sslp, ssl_accounts AS sslpa, owners AS o
							   WHERE sslc.fee_id = f.id
							     AND f.currency_id = c.id
								 AND c.id = cc.currency_id
								 AND sslc.ssl_provider_id = sslp.id
								 AND sslc.account_id = sslpa.id
								 AND sslc.owner_id = o.id
								 AND sslc.active NOT IN ('0')
								 AND cc.user_id = '" . $_SESSION['user_id'] . "'
								 AND sslp.id = '" . $row->id . "'
								 " . $range_string . "";
		$result_provider_total = mysql_query($sql_provider_total,$connection) or die(mysql_error());
		while ($row_provider_total = mysql_fetch_object($result_provider_total)) { 
			$temp_provider_total = $row_provider_total->provider_total; 
			$number_of_certs_provider = $row_provider_total->number_of_certs_provider; 
		}

		$per_cert_account = $row->total_cost / $row->number_of_certs;

		$temp_input_amount = $row->total_cost;
		$temp_input_conversion = "";
		$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
		$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
		$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
		include("../../_includes/system/convert-and-format-currency.inc.php");
		$row->total_cost = $temp_output_amount;

		$temp_input_amount = $per_cert_account;
		$temp_input_conversion = "";
		$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
		$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
		$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
		include("../../_includes/system/convert-and-format-currency.inc.php");
		$per_cert_account = $temp_output_amount;

		$per_cert_provider = $temp_provider_total / $number_of_certs_provider;

		$temp_input_amount = $temp_provider_total;
		$temp_input_conversion = "";
		$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
		$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
		$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
		include("../../_includes/system/convert-and-format-currency.inc.php");
		$temp_provider_total = $temp_output_amount;

		$temp_input_amount = $per_cert_provider;
		$temp_input_conversion = "";
		$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
		$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
		$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
		include("../../_includes/system/convert-and-format-currency.inc.php");
		$per_cert_provider = $temp_output_amount;

		if ($new_provider != $last_provider || $new_provider == "") { ?>
	
            <tr class="main_table_row_active">
                <td class="main_table_cell_active"><?=$row->provider_name?></td>
                <td class="main_table_cell_active"><?=$number_of_certs_provider?></td>
                <td class="main_table_cell_active"><?=$temp_provider_total?></td>
                <td class="main_table_cell_active"><?=$per_cert_provider?></td>
                <td class="main_table_cell_active"><?=$row->owner_name?> (<?=$row->username?>)</td>
                <td class="main_table_cell_active"><?=$row->number_of_certs?></td>
                <td class="main_table_cell_active"><?=$row->total_cost?></td>
                <td class="main_table_cell_active"><?=$per_cert_account?></td>
            </tr><?php

			$last_provider = $row->provider_name;

		} else { ?>

            <tr class="main_table_row_active">
                <td class="main_table_cell_active"></td>
                <td class="main_table_cell_active"></td>
                <td class="main_table_cell_active"></td>
                <td class="main_table_cell_active"></td>
                <td class="main_table_cell_active"><?=$row->owner_name?> (<?=$row->username?>)</td>
                <td class="main_table_cell_active"><?=$row->number_of_certs?></td>
                <td class="main_table_cell_active"><?=$row->total_cost?></td>
                <td class="main_table_cell_active"><?=$per_cert_account?></td>
            </tr><?php

			$last_provider = $row->provider_name;

		}

	}
		?>
    </table><?php

} 
?>
<?php include("../../_includes/layout/footer.inc.php"); ?>
</body>
</html>