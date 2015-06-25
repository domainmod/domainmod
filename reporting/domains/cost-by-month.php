<?php
/**
 * /reporting/domains/cost-by-month.php
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

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$currency = new DomainMOD\Currency();
$error = new DomainMOD\Error();
$system = new DomainMOD\System();
$time = new DomainMOD\Timestamp();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck($web_root);

$page_title = $reporting_section_title;
$page_subtitle = "Domain Cost by Month Report";
$software_section = "reporting-domain-cost-by-month-report";
$report_name = "domain-cost-by-month-report";

// Form Variables
$export_data = $_GET['export_data'];
$all = $_GET['all'];
$new_start_date = $_REQUEST['new_start_date'];
$new_end_date = $_REQUEST['new_end_date'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $date = new DomainMOD\Date();

    if ((!$date->checkDateFormat($new_start_date) || !$date->checkDateFormat($new_end_date)) || $new_start_date > $new_end_date) {

        if (!$date->checkDateFormat($new_start_date)) $_SESSION['result_message'] .= "The start date is invalid<BR>";
        if (!$date->checkDateFormat($new_end_date)) $_SESSION['result_message'] .= "The end date is invalid<BR>";
        if ($new_start_date > $new_end_date) $_SESSION['result_message'] .= "The end date proceeds the start date<BR>";

        $submission_failed = "1";

    }

    $all = "0";

}

$range_string = $reporting->getRangeString($all, 'd.expiry_date', $new_start_date, $new_end_date);

$sql = "SELECT d.id, YEAR(d.expiry_date) AS year, MONTH(d.expiry_date) AS month
        FROM domains AS d, fees AS f, currencies AS c
        WHERE d.fee_id = f.id
          AND f.currency_id = c.id
          AND d.active NOT IN ('0', '10')
          " . $range_string . "
        GROUP BY year, month
        ORDER BY year, month";
$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
$total_rows = mysqli_num_rows($result);

$sql_grand_total = "SELECT SUM(d.total_cost * cc.conversion) as grand_total, count(*) AS number_of_domains_total
                    FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc
                    WHERE d.fee_id = f.id
                      AND f.currency_id = c.id
                      AND c.id = cc.currency_id
                      AND cc.user_id = '" . $_SESSION['user_id'] . "'
                      AND d.active NOT IN ('0', '10')
                      " . $range_string . "";
$result_grand_total = mysqli_query($connection, $sql_grand_total) or $error->outputOldSqlError($connection);

while ($row_grand_total = mysqli_fetch_object($result_grand_total)) {
    $grand_total = $row_grand_total->grand_total;
    $number_of_domains_total = $row_grand_total->number_of_domains_total;
}

$grand_total = $currency->format($grand_total, $_SESSION['default_currency_symbol'],
    $_SESSION['default_currency_symbol_order'], $_SESSION['default_currency_symbol_space']);

if ($submission_failed != "1" && $total_rows > 0) {

    if ($export_data == "1") {

        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $export = new DomainMOD\Export();

        if ($all == "1") {

            $export_file = $export->openFile('domain_cost_by_month_report_all', strtotime($time->time()));

        } else {

            $export_file = $export->openFile(
                'domain_cost_by_month_report',
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
            'Total Cost:',
            $grand_total,
            $_SESSION['default_currency']
        );
        $export->writeRow($export_file, $row_contents);

        $row_contents = array(
            'Number of Domains:',
            $number_of_domains_total
        );
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

        $row_contents = array(
            'Year',
            'Month',
            'Cost',
            'By Year'
        );
        $export->writeRow($export_file, $row_contents);

        $new_year = "";
        $last_year = "";
        $new_month = "";
        $last_month = "";

        while ($row = mysqli_fetch_object($result)) {

            $new_year = $row->year;
            $new_month = $row->month;

            $sql_monthly_cost = "SELECT SUM(d.total_cost * cc.conversion) AS monthly_cost
                                 FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc
                                 WHERE d.fee_id = f.id
                                   AND f.currency_id = c.id
                                   AND c.id = cc.currency_id
                                   AND cc.user_id = '" . $_SESSION['user_id'] . "'
                                   AND d.active NOT IN ('0', '10')
                                   AND YEAR(d.expiry_date) = '" . $row->year . "'
                                   AND MONTH(d.expiry_date) = '" . $row->month . "'
                                   " . $range_string . "";
            $result_monthly_cost = mysqli_query($connection, $sql_monthly_cost) or $error->outputOldSqlError($connection);

            while ($row_monthly_cost = mysqli_fetch_object($result_monthly_cost)) {
                $monthly_cost = $row_monthly_cost->monthly_cost;
            }

            $monthly_cost = $currency->format($monthly_cost, $_SESSION['default_currency_symbol'],
                $_SESSION['default_currency_symbol_order'], $_SESSION['default_currency_symbol_space']);

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

            $sql_yearly_cost = "SELECT SUM(d.total_cost * cc.conversion) AS yearly_cost
                                FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc
                                WHERE d.fee_id = f.id
                                  AND f.currency_id = c.id
                                  AND c.id = cc.currency_id
                                  AND cc.user_id = '" . $_SESSION['user_id'] . "'
                                  AND d.active NOT IN ('0', '10')
                                  AND YEAR(d.expiry_date) = '" . $row->year . "'
                                  " . $range_string . "";
            $result_yearly_cost = mysqli_query($connection, $sql_yearly_cost) or $error->outputOldSqlError($connection);

            while ($row_yearly_cost = mysqli_fetch_object($result_yearly_cost)) {
                $yearly_cost = $row_yearly_cost->yearly_cost;
            }

            $yearly_cost = $currency->format($yearly_cost, $_SESSION['default_currency_symbol'],
                $_SESSION['default_currency_symbol_order'], $_SESSION['default_currency_symbol_space']);

            $row_contents = array(
                $row->year,
                $display_month,
                $monthly_cost,
                $yearly_cost
            );
            $export->writeRow($export_file, $row_contents);

            $last_year = $row->year;
            $last_month = $row->month;

        }

        $export->closeFile($export_file);
        exit;

    }

}
?>
<?php include(DIR_INC . "doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?> :: <?php echo $page_subtitle; ?></title>
<?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<?php include(DIR_INC . "layout/reporting-block.inc.php"); ?>
<?php include(DIR_INC . "layout/table-export-top.inc.php"); ?>
    <form name="export_domains_form" method="post">
        <a href="cost-by-month.php?all=1">View All</a> or Expiring Between
        <input name="new_start_date" type="text" size="10" maxlength="10" <?php if ($new_start_date == "") { echo "value=\"" . $time->timeBasic() . "\""; } else { echo "value=\"$new_start_date\""; } ?>>
        and 
        <input name="new_end_date" type="text" size="10" maxlength="10" <?php if ($new_end_date == "") { echo "value=\"" . $time->timeBasic() . "\""; } else { echo "value=\"$new_end_date\""; } ?>>
        &nbsp;&nbsp;<input type="submit" name="button" value="Generate Report &raquo;"> 
        <?php if ($total_rows > 0) { ?>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>[<a href="cost-by-month.php?export_data=1&new_start_date=<?php echo $new_start_date; ?>&new_end_date=<?php echo $new_end_date; ?>&all=<?php echo $all; ?>">EXPORT REPORT</a>]</strong>
        <?php } ?>
    </form>
<?php include(DIR_INC . "layout/table-export-bottom.inc.php"); ?>
<?php
if ($submission_failed != "1" && $total_rows > 0) { ?>

	<BR><font class="subheadline"><?php echo $page_subtitle; ?></font><BR>
	<BR>
    <?php if ($all != "1") { ?>
	    <strong>Date Range:</strong> <?php echo $new_start_date; ?> - <?php echo $new_end_date; ?><BR><BR>
    <?php } else { ?>
	    <strong>Date Range:</strong> ALL<BR><BR>
    <?php } ?>
    <strong>Total Cost:</strong> <?php echo $grand_total; ?> <?php echo $_SESSION['default_currency']; ?><BR><BR>
    <strong>Number of Domains:</strong> <?php echo $number_of_domains_total; ?><BR>
    <table class="main_table" cellpadding="0" cellspacing="0">
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

    while ($row = mysqli_fetch_object($result)) {

        $new_year = $row->year;
        $new_month = $row->month;

        $sql_monthly_cost = "SELECT SUM(d.total_cost * cc.conversion) AS monthly_cost
                             FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc
                             WHERE d.fee_id = f.id
                               AND f.currency_id = c.id
                               AND c.id = cc.currency_id
                               AND cc.user_id = '" . $_SESSION['user_id'] . "'
                               AND d.active NOT IN ('0', '10')
                               AND YEAR(d.expiry_date) = '" . $row->year . "'
                               AND MONTH(d.expiry_date) = '" . $row->month . "'
                               " . $range_string . "";
        $result_monthly_cost = mysqli_query($connection, $sql_monthly_cost) or $error->outputOldSqlError($connection);

        while ($row_monthly_cost = mysqli_fetch_object($result_monthly_cost)) {
            $monthly_cost = $row_monthly_cost->monthly_cost;
        }

        $monthly_cost = $currency->format($monthly_cost, $_SESSION['default_currency_symbol'],
            $_SESSION['default_currency_symbol_order'], $_SESSION['default_currency_symbol_space']);

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

            $sql_yearly_cost = "SELECT SUM(d.total_cost * cc.conversion) AS yearly_cost
                                FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc
                                WHERE d.fee_id = f.id
                                  AND f.currency_id = c.id
                                  AND c.id = cc.currency_id
                                  AND cc.user_id = '" . $_SESSION['user_id'] . "'
                                  AND d.active NOT IN ('0', '10')
                                  AND YEAR(d.expiry_date) = '" . $row->year . "'
                                  " . $range_string . "";
            $result_yearly_cost = mysqli_query($connection, $sql_yearly_cost) or $error->outputOldSqlError($connection);

            while ($row_yearly_cost = mysqli_fetch_object($result_yearly_cost)) {
                $yearly_cost = $row_yearly_cost->yearly_cost;
            }

            $yearly_cost = $currency->format($yearly_cost, $_SESSION['default_currency_symbol'],
                $_SESSION['default_currency_symbol_order'], $_SESSION['default_currency_symbol_space']);
            ?>

			<tr class="main_table_row_active">
				<td class="main_table_cell_active"><?php echo $row->year; ?></td>
				<td class="main_table_cell_active"><?php echo $display_month; ?></td>
				<td class="main_table_cell_active"><?php echo $monthly_cost; ?></td>
				<td class="main_table_cell_active"><?php echo $yearly_cost; ?></td>
			</tr>

            <?php
            $last_year = $row->year;
            $last_month = $row->month;

        } else { ?>
		
			<tr class="main_table_row_active">
				<td class="main_table_cell_active">&nbsp;</td>
				<td class="main_table_cell_active"><?php echo $display_month; ?></td>
				<td class="main_table_cell_active"><?php echo $monthly_cost; ?></td>
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
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
