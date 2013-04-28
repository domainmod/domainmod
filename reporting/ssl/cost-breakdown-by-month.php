<?php
// /reporting/ssl/cost-breakdown-by-month.php
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

$page_title = "SSL Certificate Cost Breakdown by Month";
$software_section = "reporting";

// Form Variables
$export = $_GET['export'];
$all = $_GET['all'];
$new_start_date = $_REQUEST['new_start_date'];
$new_end_date = $_REQUEST['new_end_date'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
		if ((!CheckDateFormat($new_start_date) || !CheckDateFormat($new_end_date)) || $new_start_date > $new_end_date) { 

			if (!CheckDateFormat($new_start_date)) $_SESSION['result_message'] .= "The starting date is invalid<BR>";
			if (!CheckDateFormat($new_end_date)) $_SESSION['result_message'] .= "The ending date is invalid<BR>";
			if ($new_start_date > $new_end_date) $_SESSION['result_message'] .= "The ending date proceeds the starting date<BR>";

			$submission_failed = "1";

		}

		$all = "0";

}

if ($all == "1") {

	$range_string = "";
	
} else {

	$range_string = " AND sslc.expiry_date between '$new_start_date' AND '$new_end_date' ";
	
}

$sql = "SELECT sslc.id, YEAR(sslc.expiry_date) AS year, MONTH(sslc.expiry_date) AS month
		FROM ssl_certs AS sslc, ssl_fees AS f, currencies AS c
		WHERE sslc.fee_id = f.id
		  AND f.currency_id = c.id
		  AND sslc.active NOT IN ('0')
		  " . $range_string . "
		GROUP BY year, month
		ORDER BY year, month";
$result = mysql_query($sql,$connection) or die(mysql_error());
$total_rows = mysql_num_rows($result);

if ($submission_failed != "1" && $total_rows > 0) {

	if ($export == "1") {

		$full_export = "";
		$full_export .= "\"All fees are listed in " . $_SESSION['default_currency'] . "\"\n\n";
		$full_export .= "\"Year\",\"Month\",\"Cost\",\"By Year\"\n";
	
		$new_year = "";
		$last_year = "";
		$new_month = "";
		$last_month = "";
	
		while ($row = mysql_fetch_object($result)) {
			
			$new_year = $row->year;
			$new_month = $row->month;
	
			$sql_monthly_cost = "SELECT SUM(f.renewal_fee * c.conversion) AS monthly_cost
								 FROM ssl_certs AS sslc, ssl_fees AS f, currencies AS c
								 WHERE sslc.fee_id = f.id
								   AND f.currency_id = c.id
								   AND sslc.active NOT IN ('0')
								   AND YEAR(sslc.expiry_date) = '" . $row->year . "'
								   AND MONTH(sslc.expiry_date) = '" . $row->month . "'";
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
	
				$sql_yearly_cost = "SELECT SUM(f.renewal_fee * c.conversion) AS yearly_cost
									FROM ssl_certs AS sslc, ssl_fees AS f, currencies AS c
									WHERE sslc.fee_id = f.id
									  AND f.currency_id = c.id
									  AND sslc.active NOT IN ('0')
									  AND YEAR(sslc.expiry_date) = '" . $row->year . "'";
				$result_yearly_cost = mysql_query($sql_yearly_cost,$connection) or die(mysql_error());
				
				while ($row_yearly_cost = mysql_fetch_object($result_yearly_cost)) {
					$yearly_cost = $row_yearly_cost->yearly_cost;
					$grand_total = $grand_total + $yearly_cost;
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
				
			} else {
	
				$full_export .= "\"\",\"" . $display_month . "\",\"" . $monthly_cost . "\",\"\"\n";
				$last_year = $row->year;
				$last_month = $row->month;
	
			}
	
		}
	
		$full_export .= "\n";
	
		$temp_input_amount = $grand_total;
		$temp_input_conversion = "";
		$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
		$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
		$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
		include("../../_includes/system/convert-and-format-currency.inc.php");
		$grand_total = $temp_output_amount;
	
		$full_export .= "\"\",\"\",\"Grand Total:\",\"" . $grand_total . "\",\"" . $_SESSION['default_currency'] . "\"\n";
		
		$current_timestamp_unix = strtotime($current_timestamp);
		if ($all == "1") {
			$export_filename = "ssl_cost_breakdown_by_month_all_" . $current_timestamp_unix . ".csv";
		} else {
			$export_filename = "ssl_cost_breakdown_by_month_" . $new_start_date . "--" . $new_end_date . ".csv";
		}
		include("../../_includes/system/export-to-csv.inc.php");
		exit;

	}

}
?>
<?php include("../../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../../_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../../_includes/header.inc.php"); ?>
Before running any reports you should <a href="../../system/update-conversion-rates.php">update the conversion rates</a>.<BR>
<BR>
<div class="export-container">
	<form name="export_domains_form" method="post" action="<?=$PHP_SELF?>"> 
    	<a href="<?=$PHP_SELF?>?all=1">View All</a> or Enter a Date Range 
        <input name="new_start_date" type="text" size="10" maxlength="10" <?php if ($new_start_date == "") { echo "value=\"$current_timestamp_basic\""; } else { echo "value=\"$new_start_date\""; } ?>> 
        and 
        <input name="new_end_date" type="text" size="10" maxlength="10" <?php if ($new_end_date == "") { echo "value=\"$current_timestamp_basic\""; } else { echo "value=\"$new_end_date\""; } ?>> 
        &nbsp;&nbsp;<input type="submit" name="button" value="Generate Report &raquo;"> 
        <?php if ($total_rows > 0) { ?>
        &nbsp;&nbsp;[<a href="<?=$PHP_SELF?>?export=1&new_start_date=<?=$new_start_date?>&new_end_date=<?=$new_end_date?>&all=<?=$all?>">Export Report</a>]
        <?php } ?>
	</form>
</div>
<?php
if ($submission_failed != "1" && $total_rows > 0) { ?>

	<BR>
    All fees are listed in <strong><?=$_SESSION['default_currency']?></strong>.
    <BR><BR>
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

		$sql_monthly_cost = "SELECT SUM(f.renewal_fee * c.conversion) AS monthly_cost
							 FROM ssl_certs AS sslc, ssl_fees AS f, currencies AS c
							 WHERE sslc.fee_id = f.id
							   AND f.currency_id = c.id
							   AND sslc.active NOT IN ('0', '10')
							   AND YEAR(sslc.expiry_date) = '" . $row->year . "'
							   AND MONTH(sslc.expiry_date) = '" . $row->month . "'";
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

			$sql_yearly_cost = "SELECT SUM(f.renewal_fee * c.conversion) AS yearly_cost
								FROM ssl_certs AS sslc, ssl_fees AS f, currencies AS c
								WHERE sslc.fee_id = f.id
								  AND f.currency_id = c.id
								  AND sslc.active NOT IN ('0', '10')
								  AND YEAR(sslc.expiry_date) = '" . $row->year . "'";
			$result_yearly_cost = mysql_query($sql_yearly_cost,$connection) or die(mysql_error());
			
			while ($row_yearly_cost = mysql_fetch_object($result_yearly_cost)) {
				$yearly_cost = $row_yearly_cost->yearly_cost;
				$grand_total = $grand_total + $yearly_cost;
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
				<td class="main_table_cell_active"><strong><?=$row->year?></strong></td>
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
	$temp_input_amount = $grand_total;
	$temp_input_conversion = "";
	$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
	$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
	$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
	include("../../_includes/system/convert-and-format-currency.inc.php");
	$grand_total = $temp_output_amount;
	echo "<BR><strong>Grand Total: </strong>" . $grand_total . " " . $_SESSION['default_currency'] . "";

} 
?>
<?php include("../../_includes/footer.inc.php"); ?>
</body>
</html>