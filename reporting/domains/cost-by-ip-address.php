<?php
/**
 * /reporting/domains/cost-by-ip-address.php
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
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();

$page_title = $reporting_section_title;
$page_subtitle = "Domain Cost by IP Address Report";
$software_section = "reporting-domain-cost-by-ip-address-report";
$report_name = "domain-cost-by-ip-address-report";

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

$reporting = new DomainMOD\Reporting();
$range_string = $reporting->getRangeString($all, 'd.expiry_date', $new_start_date, $new_end_date);

$sql = "SELECT ip.id, ip.name, ip.ip, ip.rdns, SUM(d.total_cost * cc.conversion) AS total_cost, count(*) AS number_of_domains
        FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc, ip_addresses AS ip
        WHERE d.fee_id = f.id
          AND f.currency_id = c.id
          AND c.id = cc.currency_id
          AND d.ip_id = ip.id
          AND d.active NOT IN ('0', '10')
          AND cc.user_id = '" . $_SESSION['user_id'] . "'
          " . $range_string . "
        GROUP BY ip.name
        ORDER BY ip.name";
$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
$total_rows = mysqli_num_rows($result);

$sql_grand_total = "SELECT SUM(d.total_cost * cc.conversion) AS grand_total, count(*) AS number_of_domains_total
                    FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc, ip_addresses AS ip
                    WHERE d.fee_id = f.id
                      AND f.currency_id = c.id
                      AND c.id = cc.currency_id
                      AND d.ip_id = ip.id
                      AND d.active NOT IN ('0', '10')
                      AND cc.user_id = '" . $_SESSION['user_id'] . "'
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

            $export_file = $export->openFile('domain_cost_by_ip_address_report_all', strtotime($time->time()));

        } else {

            $export_file = $export->openFile(
                'domain_cost_by_ip_address_report',
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
            'IP Address Name',
            'IP Address',
            'rDNS',
            'Domains',
            'Cost',
            'Per Domain'
        );
        $export->writeRow($export_file, $row_contents);

        if (mysqli_num_rows($result) > 0) {

            while ($row = mysqli_fetch_object($result)) {

            	$per_domain = $row->total_cost / $row->number_of_domains;

                $per_domain = $currency->format($per_domain, $_SESSION['default_currency_symbol'],
                    $_SESSION['default_currency_symbol_order'], $_SESSION['default_currency_symbol_space']);

                $row->total_cost = $currency->format($row->total_cost,
                    $_SESSION['default_currency_symbol'], $_SESSION['default_currency_symbol_order'],
                    $_SESSION['default_currency_symbol_space']);

                $row_contents = array(
                    $row->name,
                    $row->ip,
                    $row->rdns,
                    $row->number_of_domains,
                    $row->total_cost,
                    $per_domain
                );
                $export->writeRow($export_file, $row_contents);

            }

        }

        $export->closeFile($export_file);
        exit;

    }

}
?>
<?php echo $system->doctype(); ?>
<html>
<head>
<title><?php echo $system->pageTitleSub($software_title, $page_title, $page_subtitle); ?></title>
<?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<?php include(DIR_INC . "layout/reporting-block.inc.php"); ?>
<?php echo $reporting->showTableTop(); ?>
    <form name="export_domains_form" method="post">
        <a href="cost-by-ip-address.php?all=1">View All</a> or Expiring Between
        <input name="new_start_date" type="text" size="10" maxlength="10" <?php if ($new_start_date == "") { echo "value=\"" . $time->timeBasic() . "\""; } else { echo "value=\"$new_start_date\""; } ?>>
        and
        <input name="new_end_date" type="text" size="10" maxlength="10" <?php if ($new_end_date == "") { echo "value=\"" . $time->timeBasic() . "\""; } else { echo "value=\"$new_end_date\""; } ?>>
        &nbsp;&nbsp;<input type="submit" name="button" value="Generate Report &raquo;">
        <?php if ($total_rows > 0) { ?>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>[<a href="cost-by-ip-address.php?export_data=1&new_start_date=<?php echo $new_start_date; ?>&new_end_date=<?php echo $new_end_date; ?>&all=<?php echo $all; ?>">EXPORT REPORT</a>]</strong>
        <?php } ?>
    </form>
<?php echo $reporting->showTableBottom(); ?>
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
        	<font class="main_table_heading">IP Address Name</font>
        </td>
        <td class="main_table_cell_heading_active">
        	<font class="main_table_heading">IP Address</font>
        </td>
        <td class="main_table_cell_heading_active">
        	<font class="main_table_heading">rDNS</font>
        </td>
        <td class="main_table_cell_heading_active">
        	<font class="main_table_heading">Domains</font>
        </td>
        <td class="main_table_cell_heading_active">
        	<font class="main_table_heading">Cost</font>
        </td>
        <td class="main_table_cell_heading_active">
        	<font class="main_table_heading">Per Domain</font>
        </td>
    </tr>

    <?php
    while ($row = mysqli_fetch_object($result)) {

        $per_domain = $row->total_cost / $row->number_of_domains;

        $per_domain = $currency->format($per_domain, $_SESSION['default_currency_symbol'],
            $_SESSION['default_currency_symbol_order'], $_SESSION['default_currency_symbol_space']);

        $row->total_cost = $currency->format($row->total_cost, $_SESSION['default_currency_symbol'],
            $_SESSION['default_currency_symbol_order'], $_SESSION['default_currency_symbol_space']); ?>

        <tr class="main_table_row_active">
            <td class="main_table_cell_active"><a class="invisiblelink" href="../../domains.php?ipid=<?php echo $row->id; ?>"><?php echo $row->name; ?></a></td>
            <td class="main_table_cell_active"><a class="invisiblelink" href="../../domains.php?ipid=<?php echo $row->id; ?>"><?php echo $row->ip; ?></a></td>
            <td class="main_table_cell_active"><a class="invisiblelink" href="../../domains.php?ipid=<?php echo $row->id; ?>"><?php echo $row->rdns; ?></a></td>
            <td class="main_table_cell_active"><a class="invisiblelink" href="../../domains.php?ipid=<?php echo $row->id; ?>"><?php echo $row->number_of_domains; ?></a></td>
            <td class="main_table_cell_active"><?php echo $row->total_cost; ?></td>
            <td class="main_table_cell_active"><?php echo $per_domain; ?></td>
        </tr><?php

    }
        ?>
    </table><?php

}
?>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
