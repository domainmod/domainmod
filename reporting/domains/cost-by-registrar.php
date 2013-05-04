<?php
// /reporting/domains/cost-by-registrar.php
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
$page_subtitle = "Domain Cost by Registrar Report";
$software_section = "reporting";
$report_name = "domain-cost-by-registrar-report";

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

$sql = "SELECT r.id, r.name AS registrar_name, o.name AS owner_name, ra.username, SUM(f.renewal_fee * cc.conversion) as total_cost, count(*) AS number_of_domains
		FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc, registrars AS r, registrar_accounts AS ra, owners AS o
		WHERE d.fee_id = f.id
		  AND f.currency_id = c.id
		  AND c.id = cc.currency_id
		  AND d.registrar_id = r.id
		  AND d.account_id = ra.id
		  AND d.owner_id = o.id
		  AND d.active NOT IN ('0', '10')
		  AND cc.user_id = '" . $_SESSION['user_id'] . "'
		  " . $range_string . "
		GROUP BY r.name, o.name, ra.username
		ORDER BY r.name, o.name, ra.username";
$result = mysql_query($sql,$connection) or die(mysql_error());
$total_rows = mysql_num_rows($result);

$sql_grand_total = "SELECT SUM(f.renewal_fee * cc.conversion) as grand_total
					FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc
					WHERE d.fee_id = f.id
					  AND f.currency_id = c.id
					  AND c.id = cc.currency_id
					  AND d.active NOT IN ('0', '10')
					  AND cc.user_id = '" . $_SESSION['user_id'] . "'
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
		$full_export .= "\"Registrar\",\"Domains\",\"Cost\",\"Per Domain\",\"Registrar Account\",\"Domains\",\"Cost\",\"Per Domain\"\n";

		$new_registrar = "";
		$last_registrar = "";
	
		while ($row = mysql_fetch_object($result)) {

			$new_registrar = $row->registrar_name;

			$sql_registrar_total = "SELECT SUM(f.renewal_fee * cc.conversion) as registrar_total, count(*) AS number_of_domains_registrar
									FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc, registrars AS r, registrar_accounts AS ra, owners AS o
									WHERE d.fee_id = f.id
									  AND f.currency_id = c.id
									  AND c.id = cc.currency_id
									  AND d.registrar_id = r.id
									  AND d.account_id = ra.id
									  AND d.owner_id = o.id
									  AND d.active NOT IN ('0', '10')
									  AND cc.user_id = '" . $_SESSION['user_id'] . "'
									  AND r.id = '" . $row->id . "'
									  " . $range_string . "";
			$result_registrar_total = mysql_query($sql_registrar_total,$connection) or die(mysql_error());
			while ($row_registrar_total = mysql_fetch_object($result_registrar_total)) { 
				$temp_registrar_total = $row_registrar_total->registrar_total; 
				$number_of_domains_registrar = $row_registrar_total->number_of_domains_registrar; 
			}

			$per_domain_account = $row->total_cost / $row->number_of_domains;

			$temp_input_amount = $row->total_cost;
			$temp_input_conversion = "";
			$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
			$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
			$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
			include("../../_includes/system/convert-and-format-currency.inc.php");
			$row->total_cost = $temp_output_amount;

			$temp_input_amount = $per_domain_account;
			$temp_input_conversion = "";
			$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
			$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
			$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
			include("../../_includes/system/convert-and-format-currency.inc.php");
			$per_domain_account = $temp_output_amount;

			$per_domain_registrar = $temp_registrar_total / $number_of_domains_registrar;

			$temp_input_amount = $temp_registrar_total;
			$temp_input_conversion = "";
			$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
			$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
			$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
			include("../../_includes/system/convert-and-format-currency.inc.php");
			$temp_registrar_total = $temp_output_amount;

			$temp_input_amount = $per_domain_registrar;
			$temp_input_conversion = "";
			$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
			$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
			$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
			include("../../_includes/system/convert-and-format-currency.inc.php");
			$per_domain_registrar = $temp_output_amount;

			$full_export .= "\"" . $row->registrar_name . "\",\"" . $number_of_domains_registrar . "\",\"" . $temp_registrar_total . "\",\"" . $per_domain_registrar . "\",\"" . $row->owner_name . " (" . $row->username . ")\",\"" . $row->number_of_domains . "\",\"" . $row->total_cost . "\",\"" . $per_domain_account . "\"\n";
			$last_registrar = $row->registrar_name;

		}

		$full_export .= "\n";

		$current_timestamp_unix = strtotime($current_timestamp);
		if ($all == "1") {
			$export_filename = "domain_cost_by_registrar_report_all_" . $current_timestamp_unix . ".csv";
		} else {
			$export_filename = "domain_cost_by_registrar_report_" . $new_start_date . "--" . $new_end_date . ".csv";
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
        <font class="main_table_heading">Registrar</font></td>
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Domains</font></td>
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Cost</font></td>
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Per Domain</font></td>
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Registrar Account</font></td>
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Domains</font></td>
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Cost</font></td>
        <td class="main_table_cell_heading_active">
        <font class="main_table_heading">Per Domain</font></td>
    </tr>

	<?php
	$new_registrar = "";
	$last_registrar = "";

	while ($row = mysql_fetch_object($result)) {

		$new_registrar = $row->registrar_name;

		$sql_registrar_total = "SELECT SUM(f.renewal_fee * cc.conversion) as registrar_total, count(*) AS number_of_domains_registrar
								FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc, registrars AS r, registrar_accounts AS ra, owners AS o
								WHERE d.fee_id = f.id
								  AND f.currency_id = c.id
								  AND c.id = cc.currency_id
								  AND d.registrar_id = r.id
								  AND d.account_id = ra.id
								  AND d.owner_id = o.id
								  AND d.active NOT IN ('0', '10')
								  AND cc.user_id = '" . $_SESSION['user_id'] . "'
								  AND r.id = '" . $row->id . "'
								  " . $range_string . "";
		$result_registrar_total = mysql_query($sql_registrar_total,$connection) or die(mysql_error());
		while ($row_registrar_total = mysql_fetch_object($result_registrar_total)) { 
			$temp_registrar_total = $row_registrar_total->registrar_total; 
			$number_of_domains_registrar = $row_registrar_total->number_of_domains_registrar; 
		}

		$per_domain_account = $row->total_cost / $row->number_of_domains;

		$temp_input_amount = $row->total_cost;
		$temp_input_conversion = "";
		$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
		$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
		$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
		include("../../_includes/system/convert-and-format-currency.inc.php");
		$row->total_cost = $temp_output_amount;

		$temp_input_amount = $per_domain_account;
		$temp_input_conversion = "";
		$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
		$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
		$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
		include("../../_includes/system/convert-and-format-currency.inc.php");
		$per_domain_account = $temp_output_amount;

		$per_domain_registrar = $temp_registrar_total / $number_of_domains_registrar;

		$temp_input_amount = $temp_registrar_total;
		$temp_input_conversion = "";
		$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
		$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
		$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
		include("../../_includes/system/convert-and-format-currency.inc.php");
		$temp_registrar_total = $temp_output_amount;

		$temp_input_amount = $per_domain_registrar;
		$temp_input_conversion = "";
		$temp_input_currency_symbol = $_SESSION['default_currency_symbol'];
		$temp_input_currency_symbol_order = $_SESSION['default_currency_symbol_order'];
		$temp_input_currency_symbol_space = $_SESSION['default_currency_symbol_space'];
		include("../../_includes/system/convert-and-format-currency.inc.php");
		$per_domain_registrar = $temp_output_amount;

		if ($new_registrar != $last_registrar || $new_registrar == "") { ?>
	
            <tr class="main_table_row_active">
                <td class="main_table_cell_active"><?=$row->registrar_name?></td>
                <td class="main_table_cell_active"><?=$number_of_domains_registrar?></td>
                <td class="main_table_cell_active"><?=$temp_registrar_total?></td>
                <td class="main_table_cell_active"><?=$per_domain_registrar?></td>
                <td class="main_table_cell_active"><?=$row->owner_name?> (<?=$row->username?>)</td>
                <td class="main_table_cell_active"><?=$row->number_of_domains?></td>
                <td class="main_table_cell_active"><?=$row->total_cost?></td>
                <td class="main_table_cell_active"><?=$per_domain_account?></td>
            </tr><?php

			$last_registrar = $row->registrar_name;

		} else { ?>

            <tr class="main_table_row_active">
                <td class="main_table_cell_active"></td>
                <td class="main_table_cell_active"></td>
                <td class="main_table_cell_active"></td>
                <td class="main_table_cell_active"></td>
                <td class="main_table_cell_active"><?=$row->owner_name?> (<?=$row->username?>)</td>
                <td class="main_table_cell_active"><?=$row->number_of_domains?></td>
                <td class="main_table_cell_active"><?=$row->total_cost?></td>
                <td class="main_table_cell_active"><?=$per_domain_account?></td>
            </tr><?php

			$last_registrar = $row->registrar_name;

		}

	}
		?>
    </table><?php

} 
?>
<?php include("../../_includes/layout/footer.inc.php"); ?>
</body>
</html>