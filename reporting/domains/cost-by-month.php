<?php
// /reporting/domains/cost-by-month.php
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
$page_subtitle = "Domain Cost by Month Report";
$software_section = "reporting";
$report_name = "domain-cost-by-month-report";

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

	$range_string = " AND d.expiry_date between '" . $new_start_date . "' AND '" . $new_end_date . "' ";
	
}

$sql = "SELECT d.id, YEAR(d.expiry_date) AS year, MONTH(d.expiry_date) AS month
		FROM domains AS d, fees AS f, currencies AS c
		WHERE d.fee_id = f.id
		  AND f.currency_id = c.id
		  AND d.active NOT IN ('0', '10')
		  " . $range_string . "
		GROUP BY year, month
		ORDER BY year, month";
$result = mysql_query($sql,$connection) or die(mysql_error());
$total_rows = mysql_num_rows($result);

$sql_grand_total = "SELECT SUM(f.renewal_fee * cc.conversion) as grand_total
					FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc
					WHERE d.fee_id = f.id
					  AND f.currency_id = c.id
					  AND c.id = cc.currency_id
					  AND cc.user_id = '" . $_SESSION['user_id'] . "'
					  AND d.active NOT IN ('0', '10')
					  " . $range_string . "";
$result_grand_total = mysql_query($sql_grand_total,$connection) or die(mysql_error());
while ($row_grand_total = mysql_fetch_object($result_grand_total)) {
	$grand_total = $row_grand_total->grand_total;
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
		$full_export .= "\"Total Cost:\",\"" . $grand_total . "\",\"" . $_SESSION['default_currency'] . "\"\n\n";
		$full_export .= "\"Year\",\"Month\",\"Cost\",\"By Year\"\n";
	
		$new_year = "";
		$last_year = "";
		$new_month = "";
		$last_month = "";
	
		while ($row = mysql_fetch_object($result)) {
			
			$new_year = $row->year;
			$new_month = $row->month;
	
			$sql_monthly_cost = "SELECT SUM(f.renewal_fee * cc.conversion) AS monthly_cost
								 FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc
								 WHERE d.fee_id = f.id
								   AND f.currency_id = c.id
								   AND c.id = cc.currency_id
								   AND cc.user_id = '" . $_SESSION['user_id'] . "'
								   AND d.active NOT IN ('0', '10')
								   AND YEAR(d.expiry_date) = '" . $row->year . "'
								   AND MONTH(d.expiry_date) = '" . $row->month . "'
		  						   " . $range_string . "";
			$result_monthly_cost = mysql_query($sql_monthly_cost,$connection) or die(mysql_error());
			
			while ($row_monthly_cost = mysql_fetch_object($result_monthly_cost)) {
				$monthly_cost = $row_monthly_cost->monthly_cost;
			}
	
			$temp_input_amount = $monthly_cost;
			$temp_input_conversion = "";
			$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
			$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
			$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
			include("../../_includes/system/convert-and-format-currency.inc.php");
			$monthly_cost = $temp_output_amount;
	
			if ($row->month == "1") { $display_month = "January"; } 
			elseif ($row->month == "2") { $display_month = "February"; }
			elseif ($row->month == "3") { $display_month = "March"; }
			elseif ($row->month == "4") { $display_month = "April"; }
			elseif ($row->month == "5") { $display_month = "May"; }
			elseif ($row->month == "6") { $display_month = "June"; }
			elseif ($row->month == "7") { $display_month = "July"; }
			elseif ($row->month == "8") { $display_month = "August"; }
			elseif ($row->month == "9") { $display_month = "September"; }
			elseif ($row->month == "10") { $display_month = "October"; }
			elseif ($row->month == "11") { $display_month = "November"; }
			elseif ($row->month == "12") { $display_month = "December"; }
	
			$sql_yearly_cost = "SELECT SUM(f.renewal_fee * cc.conversion) AS yearly_cost
								FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc
								WHERE d.fee_id = f.id
								  AND f.currency_id = c.id
								  AND c.id = cc.currency_id
								  AND cc.user_id = '" . $_SESSION['user_id'] . "'
								  AND d.active NOT IN ('0', '10')
								  AND YEAR(d.expiry_date) = '" . $row->year . "'
								  " . $range_string . "";
			$result_yearly_cost = mysql_query($sql_yearly_cost,$connection) or die(mysql_error());
			
			while ($row_yearly_cost = mysql_fetch_object($result_yearly_cost)) {
				$yearly_cost = $row_yearly_cost->yearly_cost;
			}

			$temp_input_amount = $yearly_cost;
			$temp_input_conversion = "";
			$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
			$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
			$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
			include("../../_includes/system/convert-and-format-currency.inc.php");
			$yearly_cost = $temp_output_amount;

			$full_export .= "\"" . $row->year . "\",\"" . $display_month . "\",\"" . $monthly_cost . "\",\"" . $yearly_cost . "\"\n";
			$last_year = $row->year;
			$last_month = $row->month;

		}
	
		$full_export .= "\n";

		$current_timestamp_unix = strtotime($current_timestamp);
		if ($all == "1") {
			$export_filename = "domain_cost_by_month_report_all_" . $current_timestamp_unix . ".csv";
		} else {
			$export_filename = "domain_cost_by_month_report_" . $new_start_date . "--" . $new_end_date . ".csv";
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
    <form name="export_domains_form" method="post" action="<?=$PHP_SELF?>"> 
        <a href="<?=$PHP_SELF?>?all=1">View All</a> or Enter a Date Range 
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
    <table class="main_table">
    <tr class="main_table_row_heading_active">
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Year</font></td>
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Month</font></td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Cost</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">By Year</font>
        </td>
    </tr>
    <?php

	$new_year = "";
	$last_year = "";
	$new_month = "";
	$last_month = "";

    while ($row = mysql_fetch_object($result)) {
		
		$new_year = $row->year;
		$new_month = $row->month;

		$sql_monthly_cost = "SELECT SUM(f.renewal_fee * cc.conversion) AS monthly_cost
							 FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc
							 WHERE d.fee_id = f.id
							   AND f.currency_id = c.id
							   AND c.id = cc.currency_id
							   AND cc.user_id = '" . $_SESSION['user_id'] . "'
							   AND d.active NOT IN ('0', '10')
							   AND YEAR(d.expiry_date) = '" . $row->year . "'
							   AND MONTH(d.expiry_date) = '" . $row->month . "'
		  					   " . $range_string . "";
		$result_monthly_cost = mysql_query($sql_monthly_cost,$connection) or die(mysql_error());
		
		while ($row_monthly_cost = mysql_fetch_object($result_monthly_cost)) {
			$monthly_cost = $row_monthly_cost->monthly_cost;
		}

		$temp_input_amount = $monthly_cost;
		$temp_input_conversion = "";
		$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
		$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
		$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
		include("../../_includes/system/convert-and-format-currency.inc.php");
		$monthly_cost = $temp_output_amount;

		if ($row->month == "1") { $display_month = "January"; } 
		elseif ($row->month == "2") { $display_month = "February"; }
		elseif ($row->month == "3") { $display_month = "March"; }
		elseif ($row->month == "4") { $display_month = "April"; }
		elseif ($row->month == "5") { $display_month = "May"; }
		elseif ($row->month == "6") { $display_month = "June"; }
		elseif ($row->month == "7") { $display_month = "July"; }
		elseif ($row->month == "8") { $display_month = "August"; }
		elseif ($row->month == "9") { $display_month = "September"; }
		elseif ($row->month == "10") { $display_month = "October"; }
		elseif ($row->month == "11") { $display_month = "November"; }
		elseif ($row->month == "12") { $display_month = "December"; }

		if ($new_year > $last_year || $new_year == "") {

			$sql_yearly_cost = "SELECT SUM(f.renewal_fee * cc.conversion) AS yearly_cost
								FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc
								WHERE d.fee_id = f.id
								  AND f.currency_id = c.id
								  AND c.id = cc.currency_id
								  AND cc.user_id = '" . $_SESSION['user_id'] . "'
								  AND d.active NOT IN ('0', '10')
								  AND YEAR(d.expiry_date) = '" . $row->year . "'
		  						  " . $range_string . "";
			$result_yearly_cost = mysql_query($sql_yearly_cost,$connection) or die(mysql_error());
			
			while ($row_yearly_cost = mysql_fetch_object($result_yearly_cost)) {
				$yearly_cost = $row_yearly_cost->yearly_cost;
			}

			$temp_input_amount = $yearly_cost;
			$temp_input_conversion = "";
			$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
			$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
			$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
			include("../../_includes/system/convert-and-format-currency.inc.php");
			$yearly_cost = $temp_output_amount;
			?>

			<tr class="main_table_row_active">
				<td class="main_table_cell_active"><?=$row->year?></td>
				<td class="main_table_cell_active"><?=$display_month?></td>
				<td class="main_table_cell_active"><?=$monthly_cost?></td>
				<td class="main_table_cell_active"><?=$yearly_cost?></td>
			</tr>

            <?php
			$last_year = $row->year;
			$last_month = $row->month;
			
		} else { ?>
		
			<tr class="main_table_row_active">
				<td class="main_table_cell_active">&nbsp;</td>
				<td class="main_table_cell_active"><?=$display_month?></td>
				<td class="main_table_cell_active"><?=$monthly_cost?></td>
				<td class="main_table_cell_active">&nbsp;</td>
			</tr>

            <?php
			$last_year = $row->year;
			$last_month = $row->month;

		}

    }
	?>
    </table>
	
	<?php
} 
?>
<?php include("../../_includes/layout/footer.inc.php"); ?>
</body>
</html>