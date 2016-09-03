<?php
/**
 * /reporting/domains/cost-by-registrar.php
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
$date = new DomainMOD\Date();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "settings/reporting-domain-cost-by-registrar.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck($web_root);

$export_data = $_GET['export_data'];
$all = $_GET['all'];
$daterange = $_REQUEST['daterange'];

list($new_start_date, $new_end_date) = $date->splitAndCheckRange($daterange);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $date = new DomainMOD\Date();

    if ($new_start_date > $new_end_date) {

        $_SESSION['s_message_danger'] .= 'The end date proceeds the start date<BR>';
        $submission_failed = '1';

    }

    $all = '0';

}

$range_string = $reporting->getRangeString($all, 'd.expiry_date', $new_start_date, $new_end_date);

$sql = "SELECT r.id, r.name AS registrar_name, o.name AS owner_name, ra.id AS registrar_account_id, ra.username, SUM(d.total_cost * cc.conversion) AS total_cost, count(*) AS number_of_domains
        FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc, registrars AS r,
            registrar_accounts AS ra, owners AS o
        WHERE d.fee_id = f.id
          AND f.currency_id = c.id
          AND c.id = cc.currency_id
          AND d.registrar_id = r.id
          AND d.account_id = ra.id
          AND d.owner_id = o.id
          AND d.active NOT IN ('0', '10')
          AND cc.user_id = '" . $_SESSION['s_user_id'] . "'
          " . $range_string . "
        GROUP BY r.name, o.name, ra.username
        ORDER BY r.name, o.name, ra.username";
$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
$total_rows = mysqli_num_rows($result);

$sql_grand_total = "SELECT SUM(d.total_cost * cc.conversion) AS grand_total, count(*) AS number_of_domains_total
                    FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc, registrars AS r, registrar_accounts AS ra, owners AS o
                    WHERE d.fee_id = f.id
                      AND f.currency_id = c.id
                      AND c.id = cc.currency_id
                      AND d.registrar_id = r.id
                      AND d.account_id = ra.id
                      AND d.owner_id = o.id
                      AND d.active NOT IN ('0', '10')
                      AND cc.user_id = '" . $_SESSION['s_user_id'] . "'
                      " . $range_string . "";
$result_grand_total = mysqli_query($connection, $sql_grand_total) or $error->outputOldSqlError($connection);

while ($row_grand_total = mysqli_fetch_object($result_grand_total)) {
    $grand_total = $row_grand_total->grand_total;
    $number_of_domains_total = $row_grand_total->number_of_domains_total;
}

$grand_total = $currency->format($grand_total, $_SESSION['s_default_currency_symbol'],
    $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

if ($submission_failed != '1' && $total_rows > 0) {

    if ($export_data == '1') {

        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $export = new DomainMOD\Export();

        if ($all == '1') {

            $export_file = $export->openFile('domain_cost_by_registrar_report_all', strtotime($time->stamp()));

        } else {

            $export_file = $export->openFile(
                'domain_cost_by_registrar_report',
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
            'Number of Domains:',
            $number_of_domains_total
        );
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

        $row_contents = array(
            'Registrar',
            'Cost',
            'Domains',
            'Per Domain',
            'Registrar Account',
            'Cost',
            'Domains',
            'Per Domain'
        );
        $export->writeRow($export_file, $row_contents);

        $new_registrar = '';
        $last_registrar = '';

        if (mysqli_num_rows($result) > 0) {

            while ($row = mysqli_fetch_object($result)) {

                $new_registrar = $row->registrar_name;

                $sql_registrar_total = "SELECT SUM(d.total_cost * cc.conversion) AS registrar_total,
                                            count(*) AS number_of_domains_registrar
                                        FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc,
                                            registrars AS r, registrar_accounts AS ra, owners AS o
                                        WHERE d.fee_id = f.id
                                          AND f.currency_id = c.id
                                          AND c.id = cc.currency_id
                                          AND d.registrar_id = r.id
                                          AND d.account_id = ra.id
                                          AND d.owner_id = o.id
                                          AND d.active NOT IN ('0', '10')
                                          AND cc.user_id = '" . $_SESSION['s_user_id'] . "'
                                          AND r.id = '" . $row->id . "'
                                          " . $range_string . "";
                $result_registrar_total
                    = mysqli_query($connection, $sql_registrar_total) or $error->outputOldSqlError($connection);

                while ($row_registrar_total = mysqli_fetch_object($result_registrar_total)) {
                    $temp_registrar_total = $row_registrar_total->registrar_total;
                    $number_of_domains_registrar = $row_registrar_total->number_of_domains_registrar;
                }

                $per_domain_account = $row->total_cost / $row->number_of_domains;

                $row->total_cost = $currency->format($row->total_cost, $_SESSION['s_default_currency_symbol'],
                    $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

                $per_domain_account = $currency->format($per_domain_account, $_SESSION['s_default_currency_symbol'],
                    $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

                $per_domain_registrar = $temp_registrar_total / $number_of_domains_registrar;

                $temp_registrar_total = $currency->format($temp_registrar_total, $_SESSION['s_default_currency_symbol'],
                    $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

                $per_domain_registrar = $currency->format($per_domain_registrar, $_SESSION['s_default_currency_symbol'],
                    $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

                $row_contents = array(
                    $row->registrar_name,
                    $temp_registrar_total,
                    $number_of_domains_registrar,
                    $per_domain_registrar,
                    $row->owner_name . ' (' . $row->username . ')',
                    $row->total_cost,
                    $row->number_of_domains,
                    $per_domain_account
                );
                $export->writeRow($export_file, $row_contents);

                $last_registrar = $row->registrar_name;

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
            <th>Registrar</th>
            <th>Cost</th>
            <th>Domains</th>
            <th>Per Domain</th>
            <th>Account</th>
            <th>Cost</th>
            <th>Domains</th>
            <th>Per Domain</th>
        </tr>
        </thead>
        <tbody><?php

        $new_registrar = '';
        $last_registrar = '';

        while ($row = mysqli_fetch_object($result)) {

            $new_registrar = $row->registrar_name;

            $sql_registrar_total = "SELECT SUM(d.total_cost * cc.conversion) AS registrar_total, count(*) AS number_of_domains_registrar
                                    FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc, registrars AS r, registrar_accounts AS ra, owners AS o
                                    WHERE d.fee_id = f.id
                                      AND f.currency_id = c.id
                                      AND c.id = cc.currency_id
                                      AND d.registrar_id = r.id
                                      AND d.account_id = ra.id
                                      AND d.owner_id = o.id
                                      AND d.active NOT IN ('0', '10')
                                      AND cc.user_id = '" . $_SESSION['s_user_id'] . "'
                                      AND r.id = '" . $row->id . "'
                                      " . $range_string . "";
            $result_registrar_total = mysqli_query($connection, $sql_registrar_total) or $error->outputOldSqlError($connection);

            while ($row_registrar_total = mysqli_fetch_object($result_registrar_total)) {

                $temp_registrar_total = $row_registrar_total->registrar_total;
                $number_of_domains_registrar = $row_registrar_total->number_of_domains_registrar;

            }

            $per_domain_account = $row->total_cost / $row->number_of_domains;

            $row->total_cost = $currency->format($row->total_cost, $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

            $per_domain_account = $currency->format($per_domain_account, $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

            $per_domain_registrar = $temp_registrar_total / $number_of_domains_registrar;

            $temp_registrar_total = $currency->format($temp_registrar_total, $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

            $per_domain_registrar = $currency->format($per_domain_registrar, $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

            if ($new_registrar != $last_registrar || $new_registrar == '') { ?>

                <tr>
                    <td></td>
                    <td><?php echo $row->registrar_name; ?></td>
                    <td><?php echo $temp_registrar_total; ?></td>
                    <td><a href="../../domains/index.php?rid=<?php echo $row->id; ?>"><?php echo $number_of_domains_registrar; ?></a></td>
                    <td><?php echo $per_domain_registrar; ?></td>
                    <td><?php echo $row->owner_name; ?> (<?php echo $row->username; ?>)</td>
                    <td><?php echo $row->total_cost; ?></td>
                    <td><a href="../../domains/index.php?raid=<?php echo $row->registrar_account_id; ?>"><?php echo $row->number_of_domains; ?></a></td>
                    <td><?php echo $per_domain_account; ?></td>
                </tr><?php

                $last_registrar = $row->registrar_name;

            } else { ?>

                <tr>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td><?php echo $row->owner_name; ?> (<?php echo $row->username; ?>)</td>
                    <td><?php echo $row->total_cost; ?></td>
                    <td><a href="../../domains/index.php?raid=<?php echo $row->registrar_account_id; ?>"><?php echo $row->number_of_domains; ?></a></td>
                    <td><?php echo $per_domain_account; ?></td>
                </tr><?php

                $last_registrar = $row->registrar_name;

            }

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
