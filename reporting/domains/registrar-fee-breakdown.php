<?php
// /reporting/domains/registrar-fee-breakdown.php
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

$page_title = "Registrar Fee Breakdown";
$software_section = "reporting";

// Form Variables
$export = $_GET['export'];
$all = $_GET['all'];

if ($all == "1") {

	$range_string = "";
	
} else {

	$range_string = " AND d.active NOT IN ('0', '10') ";
	
}

$sql = "SELECT r.name AS registrar, d.tld, f.initial_fee, f.renewal_fee, f.transfer_fee, f.insert_time, f.update_time, c.currency, c.symbol, c.symbol_order, c.symbol_space
		FROM registrars AS r, domains AS d, fees AS f, currencies AS c
		WHERE r.id = d.registrar_id
		  AND d.fee_id = f.id
		  AND f.currency_id = c.id
		  " . $range_string . "
		GROUP BY r.name, d.tld
		ORDER BY r.name, d.tld";
$result = mysql_query($sql,$connection) or die(mysql_error());
$total_rows = mysql_num_rows($result);

if ($total_rows > 0) {

	if ($export == "1") {

		$full_export = "";

		if ($all == "1") {
			$full_export .= "\"All Registrar Fees\"\n\n";
		} else {
			$full_export .= "\"Active Registrar Fees\"\n\n";
		}

		$full_export .= "\"Registrar\",\"TLD\",\"Initial Fee\",\"Renewal Fee\",\"Transfer Fee\",\"Currency\",\"Last Updated\"\n";
	
		$new_registrar = "";
		$last_registrar = "";
		$new_tld = "";
		$last_tld = "";
	
		while ($row = mysql_fetch_object($result)) {
			
			$new_registrar = $row->registrar;
			$new_tld = $row->tld;

			if ($row->update_time == "0000-00-00 00:00:00") {
				$row->update_time = $row->insert_time;	
			}
			$last_updated = date('Y-m-d', strtotime($row->update_time));

			$temp_input_amount = $row->initial_fee;
			$temp_input_conversion = "";
			$temp_input_currency_symbol = $row->symbol;
			$temp_input_currency_symbol_order = $row->symbol_order;
			$temp_input_currency_symbol_space = $row->symbol_space;
			include("../../_includes/system/convert-and-format-currency.inc.php");
			$row->initial_fee = $temp_output_amount;

			$temp_input_amount = $row->renewal_fee;
			$temp_input_conversion = "";
			$temp_input_currency_symbol = $row->symbol;
			$temp_input_currency_symbol_order = $row->symbol_order;
			$temp_input_currency_symbol_space = $row->symbol_space;
			include("../../_includes/system/convert-and-format-currency.inc.php");
			$row->renewal_fee = $temp_output_amount;

			$temp_input_amount = $row->transfer_fee;
			$temp_input_conversion = "";
			$temp_input_currency_symbol = $row->symbol;
			$temp_input_currency_symbol_order = $row->symbol_order;
			$temp_input_currency_symbol_space = $row->symbol_space;
			include("../../_includes/system/convert-and-format-currency.inc.php");
			$row->transfer_fee = $temp_output_amount;

			if ($new_registrar != $last_registrar || $new_registrar == "") {
	
				$full_export .= "\"" . $row->registrar . "\",\"." . $row->tld . "\",\"" . $row->initial_fee . "\",\"" . $row->renewal_fee . "\",\"" . $row->transfer_fee . "\",\"" . $row->currency . "\",\"" . $last_updated . "\"\n";
				$last_registrar = $row->registrar;
				$last_tld = $row->tld;
				
			} else {
	
				$full_export .= "\"\",\"." . $row->tld . "\",\"" . $row->initial_fee . "\",\"" . $row->renewal_fee . "\",\"" . $row->transfer_fee . "\",\"" . $row->currency . "\",\"" . $last_updated . "\"\n";
				$last_registrar = $row->registrar;
				$last_tld = $row->tld;
	
			}
	
		}
	
		$full_export .= "\n";

		$current_timestamp_unix = strtotime($current_timestamp);
		if ($all == "1") {
			$export_filename = "registrar_fee_breakdown_all_" . $current_timestamp_unix . ".csv";
		} else {
			$export_filename = "registrar_fee_breakdown_active_" . $current_timestamp_unix . ".csv";
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
<div class="export-container">
	<a href="<?=$PHP_SELF?>?all=1">View All</a> or <a href="<?=$PHP_SELF?>?all=0">Active Only</a>
    <?php if ($total_rows > 0) { ?>
    &nbsp;&nbsp;[<a href="<?=$PHP_SELF?>?export=1&all=<?=$all?>">Export Report</a>]
    <?php } ?>
</div>
<BR>
<?php if ($all == "1") { ?>
	<strong>All Registrar Fees</strong><BR><BR>
<?php } else { ?>
	<strong>Active Registrar Fees</strong><BR><BR>
<?php } ?>
<?php
if ($total_rows > 0) { ?>

    <table class="main_table">
    <tr class="main_table_row_heading_active">
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Registrar</font></td>
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">TLD</font></td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Initial Fee</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Renewal Fee</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Transfer Fee</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Currency</font>
        </td>
        <td class="main_table_cell_heading_active">
            <font class="main_table_heading">Last Updated</font>
        </td>
    </tr>
    <?php

	$new_registrar = "";
	$last_registrar = "";
	$new_tld = "";
	$last_tld = "";

    while ($row = mysql_fetch_object($result)) {
		
		$new_registrar = $row->registrar;
		$new_tld = $row->tld;
		
		if ($row->update_time == "0000-00-00 00:00:00") {
			$row->update_time = $row->insert_time;	
		}
		$last_updated = date('Y-m-d', strtotime($row->update_time));

		if ($new_registrar != $last_registrar || $new_registrar == "") { ?>

			<tr class="main_table_row_active">
				<td class="main_table_cell_active"><strong><?=$row->registrar?></strong></td>
				<td class="main_table_cell_active">.<?=$row->tld?></td>
				<td class="main_table_cell_active">
                	<?php
					$temp_input_amount = $row->initial_fee;
					$temp_input_conversion = "";
					$temp_input_currency_symbol = $row->symbol;
					$temp_input_currency_symbol_order = $row->symbol_order;
					$temp_input_currency_symbol_space = $row->symbol_space;
					include("../../_includes/system/convert-and-format-currency.inc.php");
					$row->initial_fee = $temp_output_amount;
					?>
					<?=$row->initial_fee?>
                </td>
				<td class="main_table_cell_active">
                	<?php
					$temp_input_amount = $row->renewal_fee;
					$temp_input_conversion = "";
					$temp_input_currency_symbol = $row->symbol;
					$temp_input_currency_symbol_order = $row->symbol_order;
					$temp_input_currency_symbol_space = $row->symbol_space;
					include("../../_includes/system/convert-and-format-currency.inc.php");
					$row->renewal_fee = $temp_output_amount;
					?>
					<?=$row->renewal_fee?>
                </td>
				<td class="main_table_cell_active">
                	<?php
					$temp_input_amount = $row->transfer_fee;
					$temp_input_conversion = "";
					$temp_input_currency_symbol = $row->symbol;
					$temp_input_currency_symbol_order = $row->symbol_order;
					$temp_input_currency_symbol_space = $row->symbol_space;
					include("../../_includes/system/convert-and-format-currency.inc.php");
					$row->transfer_fee = $temp_output_amount;
					?>
					<?=$row->transfer_fee?>
                </td>
				<td class="main_table_cell_active"><?=$row->currency?></td>
				<td class="main_table_cell_active"><?=$last_updated?></td>
			</tr>

            <?php
			$last_registrar = $row->registrar;
			$last_tld = $row->tld;
			
		} else { ?>
		
			<tr class="main_table_row_active">
				<td class="main_table_cell_active">&nbsp;</td>
				<td class="main_table_cell_active">.<?=$row->tld?></td>
				<td class="main_table_cell_active">
                	<?php
					$temp_input_amount = $row->initial_fee;
					$temp_input_conversion = "";
					$temp_input_currency_symbol = $row->symbol;
					$temp_input_currency_symbol_order = $row->symbol_order;
					$temp_input_currency_symbol_space = $row->symbol_space;
					include("../../_includes/system/convert-and-format-currency.inc.php");
					$row->initial_fee = $temp_output_amount;
					?>
					<?=$row->initial_fee?>
                </td>
				<td class="main_table_cell_active">
                	<?php
					$temp_input_amount = $row->renewal_fee;
					$temp_input_conversion = "";
					$temp_input_currency_symbol = $row->symbol;
					$temp_input_currency_symbol_order = $row->symbol_order;
					$temp_input_currency_symbol_space = $row->symbol_space;
					include("../../_includes/system/convert-and-format-currency.inc.php");
					$row->renewal_fee = $temp_output_amount;
					?>
					<?=$row->renewal_fee?>
                </td>
				<td class="main_table_cell_active">
                	<?php
					$temp_input_amount = $row->transfer_fee;
					$temp_input_conversion = "";
					$temp_input_currency_symbol = $row->symbol;
					$temp_input_currency_symbol_order = $row->symbol_order;
					$temp_input_currency_symbol_space = $row->symbol_space;
					include("../../_includes/system/convert-and-format-currency.inc.php");
					$row->transfer_fee = $temp_output_amount;
					?>
					<?=$row->transfer_fee?>
                </td>
				<td class="main_table_cell_active"><?=$row->currency?></td>
				<td class="main_table_cell_active"><?=$last_updated?></td>
			</tr>

            <?php
			$last_registrar = $row->registrar;
			$last_tld = $row->tld;

		}

    }
	?>
    </table>
	
	<?php
} 
?>
<?php include("../../_includes/footer.inc.php"); ?>
</body>
</html>