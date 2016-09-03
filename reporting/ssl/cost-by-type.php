<?php
/**
 * /reporting/ssl/cost-by-type.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2016 Greg Chetcuti <greg@chetcuti.com>
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
<?php //@formatter:off
include("../../_includes/start-session.inc.php");
include("../../_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$layout = new DomainMOD\Layout;
$time = new DomainMOD\Time();
$reporting = new DomainMOD\Reporting();
$currency = new DomainMOD\Currency();
$form = new DomainMOD\Form();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "settings/reporting-ssl-cost-by-type.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck($web_root);

$export_data = $_GET['export_data'];
$all = $_GET['all'];
$daterange = $_REQUEST['daterange'];

$new_start_date = substr($daterange, 0, 10);
$new_end_date = substr($daterange, -10, 10);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $date = new DomainMOD\Date();

    if ((!$date->checkDateFormat($new_start_date) || !$date->checkDateFormat($new_end_date)) || $new_start_date >
        $new_end_date) {

        if (!$date->checkDateFormat($new_start_date)) $_SESSION['s_message_danger'] .= '"The start date is invalid<BR>';
        if (!$date->checkDateFormat($new_end_date)) $_SESSION['s_message_danger'] .= 'The end date is invalid<BR>';
        if ($new_start_date > $new_end_date) $_SESSION['s_message_danger'] .= 'The end date proceeds the start date<BR>';

        $submission_failed = '1';

    }

    $all = '0';

}

$range_string = $reporting->getRangeString($all, 'sslc.expiry_date', $new_start_date, $new_end_date);

$sql = "SELECT sslt.id, sslt.type, SUM(sslc.total_cost * cc.conversion) AS total_cost, count(*) AS number_of_certs
        FROM ssl_certs AS sslc, ssl_fees AS f, currencies AS c, currency_conversions AS cc, ssl_cert_types AS sslt
        WHERE sslc.fee_id = f.id
          AND f.currency_id = c.id
          AND c.id = cc.currency_id
          AND sslc.type_id = sslt.id
          AND sslc.active NOT IN ('0')
          AND cc.user_id = '" . $_SESSION['s_user_id'] . "'
          " . $range_string . "
        GROUP BY sslt.type
        ORDER BY sslt.type";
$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
$total_rows = mysqli_num_rows($result);

$sql_grand_total = "SELECT SUM(sslc.total_cost * cc.conversion) AS grand_total, count(*) AS number_of_certs_total
                    FROM ssl_certs AS sslc, ssl_fees AS f, currencies AS c, currency_conversions AS cc, ssl_cert_types AS sslt
                    WHERE sslc.fee_id = f.id
                      AND f.currency_id = c.id
                      AND c.id = cc.currency_id
                      AND sslc.type_id = sslt.id
                      AND sslc.active NOT IN ('0')
                      AND cc.user_id = '" . $_SESSION['s_user_id'] . "'
                      " . $range_string . "";
$result_grand_total = mysqli_query($connection, $sql_grand_total) or $error->outputOldSqlError($connection);

while ($row_grand_total = mysqli_fetch_object($result_grand_total)) {
    $grand_total = $row_grand_total->grand_total;
    $number_of_certs_total = $row_grand_total->number_of_certs_total;
}

$grand_total = $currency->format($grand_total, $_SESSION['s_default_currency_symbol'],
    $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

if ($submission_failed != '1' && $total_rows > 0) {

    if ($export_data == '1') {

        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $export = new DomainMOD\Export();

        if ($all == '1') {

            $export_file = $export->openFile('ssl_cost_by_type_report_all', strtotime($time->stamp()));

        } else {

            $export_file = $export->openFile(
                'ssl_cost_by_type_report',
                $new_start_date . '--' . $new_end_date
            );

        }

        $row_contents = array($page_title);
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

        if ($all != '1') {

            $row_contents = array('Date Range:', $new_start_date, $new_end_date);

        } else {

            $row_contents = array('Date Range:', 'ALL');

        }
        $export->writeRow($export_file, $row_contents);

        $row_contents = array(
            'Total Cost:',
            $grand_total,
            $_SESSION['s_default_currency']
        );
        $export->writeRow($export_file, $row_contents);

        $row_contents = array(
            'Number of SSL Certs:',
            $number_of_certs_total
        );
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

        $row_contents = array(
            'Type',
            'Cost',
            'Certs',
            'Per Cert'
        );
        $export->writeRow($export_file, $row_contents);

        if (mysqli_num_rows($result) > 0) {

            while ($row = mysqli_fetch_object($result)) {

                $per_cert = $row->total_cost / $row->number_of_certs;

                $per_cert = $currency->format($per_cert, $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

                $row->total_cost = $currency->format($row->total_cost, $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

                $row_contents = array(
                    $row->type,
                    $row->total_cost,
                    $row->number_of_certs,
                    $per_cert
                );
                $export->writeRow($export_file, $row_contents);

            }

        }
        $export->closeFile($export_file);

    }

} else {

    $total_rows = '0';

}
?>
<?php include(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
    <?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
    <?php include(DIR_INC . "layout/date-range-picker-head.inc.php"); ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<?php include(DIR_INC . "layout/reporting-block.inc.php"); ?>
<?php
if ($submission_failed != '1' && $total_rows > 0) { ?>

    <?php include(DIR_INC . "layout/reporting-block-sub.inc.php"); ?>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th>Type</th>
            <th>Cost</th>
            <th>Certs</th>
            <th>Per Cert</th>
        </tr>
        </thead>
        <tbody><?php

        while ($row = mysqli_fetch_object($result)) {

            $per_cert = $row->total_cost / $row->number_of_certs;

            $per_cert = $currency->format($per_cert, $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

            $row->total_cost = $currency->format($row->total_cost, $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']); ?>

            <tr>
                <td></td>
                <td><?php echo $row->type; ?></td>
                <td><?php echo $row->total_cost; ?></td>
                <td><a href="../../ssl/index.php?ssltid=<?php echo $row->id; ?>"><?php echo $row->number_of_certs; ?></a></td>
                <td><?php echo $per_cert; ?></td>
            </tr><?php

        } ?>

        </tbody>
    </table><?php

} else {

    echo 'No results.<BR><BR>';

}
?>
<?php include(DIR_INC . "layout/footer.inc.php"); //@formatter:on ?>
<?php include(DIR_INC . "layout/date-range-picker-footer.inc.php"); ?>
</body>
</html>
