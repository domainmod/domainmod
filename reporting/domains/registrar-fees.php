<?php
/**
 * /reporting/domains/registrar-fees.php
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
$reporting = new DomainMOD\Reporting();
$system = new DomainMOD\System();
$time = new DomainMOD\Timestamp();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();

$page_title = $reporting_section_title;
$page_subtitle = "Domain Registrar Fee Report";
$software_section = "reporting-domain-registrar-fee-report";
$report_name = "domain-registrar-fee-report";

// Form Variables
$export_data = $_GET['export_data'];
$all = (integer)urlencode($_GET['all']);

if ($all == "1") {

    $sql = "SELECT r.id, r.name AS registrar, f.id AS fee_id, f.tld, f.initial_fee, f.renewal_fee, f.transfer_fee,
                f.privacy_fee, f.misc_fee, f.insert_time, f.update_time, c.currency, c.symbol, c.symbol_order,
                c.symbol_space, count(*) AS number_of_fees_total
            FROM registrars AS r, fees AS f, currencies AS c
            WHERE r.id = f.registrar_id
              AND f.currency_id = c.id
            GROUP BY r.name, f.tld
            ORDER BY r.name, f.tld";

} else {

    $sql = "SELECT r.id, r.name AS registrar, d.tld, f.id AS fee_id, f.initial_fee, f.renewal_fee, f.transfer_fee,
                f.privacy_fee, f.misc_fee, f.insert_time, f.update_time, c.currency, c.symbol, c.symbol_order,
                c.symbol_space, count(*) AS number_of_fees_total
            FROM registrars AS r, domains AS d, fees AS f, currencies AS c
            WHERE r.id = d.registrar_id
              AND d.fee_id = f.id
              AND f.currency_id = c.id
              AND d.active NOT IN ('0', '10')
            GROUP BY r.name, d.tld
            ORDER BY r.name, d.tld";

}

$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
$total_rows = mysqli_num_rows($result);

if ($total_rows > 0) {

    if ($export_data == "1") {

        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        $export = new DomainMOD\Export();

        if ($all == "1") {

            $export_file = $export->openFile('registrar_fee_report_all', strtotime($time->time()));

        } else {

            $export_file = $export->openFile('registrar_fee_report_active', strtotime($time->time()));

        }

        $row_contents = array($page_subtitle);
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

        if ($all == "1") {

            $row_contents = array('All Registrar Fees');

        } else {

            $row_contents = array('Active Registrar Fees');

        }
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

        $row_contents = array(
            'Registrar',
            'TLD',
            'Initial Fee',
            'Renewal Fee',
            'Transfer Fee',
            'Privacy Fee',
            'Misc Fee',
            'Currency',
            'Domains',
            'Inserted',
            'Updated'
        );
        $export->writeRow($export_file, $row_contents);

        $new_registrar = "";
        $last_registrar = "";
        $new_tld = "";
        $last_tld = "";

        if (mysqli_num_rows($result) > 0) {

            while ($row = mysqli_fetch_object($result)) {

                $new_registrar = $row->registrar;
                $new_tld = $row->tld;

                $row->initial_fee = $currency->format($row->initial_fee, $row->symbol, $row->symbol_order,
                    $row->symbol_space);

                $row->renewal_fee = $currency->format($row->renewal_fee, $row->symbol, $row->symbol_order,
                    $row->symbol_space);

                $row->transfer_fee = $currency->format($row->transfer_fee, $row->symbol,
                    $row->symbol_order, $row->symbol_space);

                $row->privacy_fee = $currency->format($row->privacy_fee, $row->symbol, $row->symbol_order,
                    $row->symbol_space);

                $row->misc_fee = $currency->format($row->misc_fee, $row->symbol, $row->symbol_order,
                    $row->symbol_space);

                unset($row_contents);
                $count = 0;

                $row_contents[$count++] = $row->registrar;
                $row_contents[$count++] = '.' . $row->tld;
                $row_contents[$count++] = $row->initial_fee;
                $row_contents[$count++] = $row->renewal_fee;
                $row_contents[$count++] = $row->transfer_fee;
                $row_contents[$count++] = $row->privacy_fee;
                $row_contents[$count++] = $row->misc_fee;
                $row_contents[$count++] = $row->currency;

                $sql_domain_count = "SELECT count(*) AS total_domain_count
                                     FROM domains
                                     WHERE registrar_id = '" . $row->id . "'
                                       AND fee_id = '" . $row->fee_id . "'
                                       AND active NOT IN ('0', '10')";
                $result_domain_count = mysqli_query($connection, $sql_domain_count);

                while ($row_domain_count = mysqli_fetch_object($result_domain_count)) {

                    $row_contents[$count++] = $row_domain_count->total_domain_count;

                }

                $row_contents[$count++] = $row->insert_time;
                $row_contents[$count++] = $row->update_time;
                $export->writeRow($export_file, $row_contents);

                $last_registrar = $row->registrar;

            }

        }

        $export->closeFile($export_file);

    }

}
?>
<?php include(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitleSub($software_title, $page_title, $page_subtitle); ?></title>
    <?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<?php include(DIR_INC . "layout/reporting-block.inc.php"); ?>
<?php echo $reporting->showTableTop(); ?>
<a href="registrar-fees.php?all=1">View All</a> or <a href="registrar-fees.php?all=0">Active Only</a>
<?php if ($total_rows > 0) { //@formatter:off ?>
          &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>[<a href="registrar-fees.php?export_data=1&all=<?php
          echo $all; ?>">EXPORT REPORT</a>]</strong>
<?php } //@formatter:on ?>
<?php echo $reporting->showTableBottom(); ?>

<BR>

<div class="subheadline"><?php echo $page_subtitle; ?></div>
<BR>

<?php if ($all == "1") { ?>
    <strong>All Registrar Fees</strong><BR>
<?php } else { ?>
    <strong>Active Registrar Fees</strong><BR>
<?php }

if ($total_rows > 0) { ?>

    <table class="main_table" cellpadding="0" cellspacing="0">
        <tr class="main_table_row_heading_active">
            <td class="main_table_cell_heading_active">
                <div class="main_table_heading">Registrar</div>
            </td>
            <td class="main_table_cell_heading_active">
                <div class="main_table_heading">TLD</div>
            </td>
            <td class="main_table_cell_heading_active">
                <div class="main_table_heading">Initial Fee</div>
            </td>
            <td class="main_table_cell_heading_active">
                <div class="main_table_heading">Renewal Fee</div>
            </td>
            <td class="main_table_cell_heading_active">
                <div class="main_table_heading">Transfer Fee</div>
            </td>
            <td class="main_table_cell_heading_active">
                <div class="main_table_heading">Privacy Fee</div>
            </td>
            <td class="main_table_cell_heading_active">
                <div class="main_table_heading">Misc Fee</div>
            </td>
            <td class="main_table_cell_heading_active">
                <div class="main_table_heading">Currency</div>
            </td>
            <td class="main_table_cell_heading_active">
                <div class="main_table_heading">Domains</div>
            </td>
            <td class="main_table_cell_heading_active">
                <div class="main_table_heading">Last Updated</div>
            </td>
        </tr>
        <?php

        $new_registrar = "";
        $last_registrar = "";
        $new_tld = "";
        $last_tld = "";

        while ($row = mysqli_fetch_object($result)) {

            $new_registrar = $row->registrar;
            $new_tld = $row->tld;

            if ($row->update_time == "0000-00-00 00:00:00") {
                $row->update_time = $row->insert_time;
            }
            $last_updated = date('Y-m-d', strtotime($row->update_time));

            if ($new_registrar != $last_registrar || $new_registrar == "") { ?>

                <tr class="main_table_row_active">
                    <td class="main_table_cell_active">
                        <a class="invisiblelink" href="../../assets/edit/registrar-fees.php?rid=<?php echo $row->id;
                        ?>"><?php echo $row->registrar; ?></a>
                    </td>
                    <td class="main_table_cell_active">
                        <a class="invisiblelink" href="../../assets/edit/registrar-fees.php?rid=<?php echo $row->id;
                        ?>">.<?php echo $row->tld; ?></a>
                    </td>
                    <td class="main_table_cell_active">
                        <?php

                        $row->initial_fee = $currency->format($row->initial_fee, $row->symbol, $row->symbol_order,
                            $row->symbol_space);

                        $row->initial_fee;
                        ?>
                    </td>
                    <td class="main_table_cell_active">
                        <?php
                        $row->renewal_fee = $currency->format($row->renewal_fee, $row->symbol, $row->symbol_order,
                            $row->symbol_space);
                        echo $row->renewal_fee;
                        ?>
                    </td>
                    <td class="main_table_cell_active">
                        <?php
                        $row->transfer_fee = $currency->format($row->transfer_fee, $row->symbol, $row->symbol_order,
                            $row->symbol_space);
                        echo $row->transfer_fee;
                        ?>
                    </td>
                    <td class="main_table_cell_active">
                        <?php
                        $row->privacy_fee = $currency->format($row->privacy_fee, $row->symbol, $row->symbol_order,
                            $row->symbol_space);
                        echo $row->privacy_fee;
                        ?>
                    </td>
                    <td class="main_table_cell_active">
                        <?php
                        $row->misc_fee = $currency->format($row->misc_fee, $row->symbol, $row->symbol_order,
                            $row->symbol_space);
                        echo $row->misc_fee;
                        ?>
                    </td>
                    <td class="main_table_cell_active"><?php echo $row->currency; ?></td>
                    <td class="main_table_cell_active">
                        <?php
                        $sql_domain_count = "SELECT count(*) AS total_domain_count
                                         FROM domains
                                         WHERE registrar_id = '" . $row->id . "'
                                           AND fee_id = '" . $row->fee_id . "'
                                           AND active NOT IN ('0', '10')";
                        $result_domain_count = mysqli_query($connection, $sql_domain_count);
                        while ($row_domain_count = mysqli_fetch_object($result_domain_count)) {

                            if ($row_domain_count->total_domain_count == 0) {

                                echo "-";

                            } else {

                                echo "<a class=\"invisiblelink\" href=\"../../domains.php?rid=" . $row->id . "&tld="
                                    . $row->tld . "\">" . $row_domain_count->total_domain_count . "</a>";

                            }

                        } ?>
                    </td>
                    <td class="main_table_cell_active"><?php echo $last_updated; ?></td>
                </tr>

                <?php
                $last_registrar = $row->registrar;
                $last_tld = $row->tld;

            } else { ?>

                <tr class="main_table_row_active">
                    <td class="main_table_cell_active">&nbsp;</td>
                    <td class="main_table_cell_active">
                        <a class="invisiblelink" href="../../assets/edit/registrar-fees.php?rid=<?php echo $row->id;
                        ?>">.<?php echo $row->tld; ?></a>
                    </td>
                    <td class="main_table_cell_active">
                        <?php
                        $row->initial_fee = $currency->format($row->initial_fee, $row->symbol, $row->symbol_order,
                            $row->symbol_space);
                        echo $row->initial_fee;
                        ?>
                    </td>
                    <td class="main_table_cell_active">
                        <?php
                        $row->renewal_fee = $currency->format($row->renewal_fee, $row->symbol, $row->symbol_order,
                            $row->symbol_space);
                        echo $row->renewal_fee;
                        ?>
                    </td>
                    <td class="main_table_cell_active">
                        <?php
                        $row->transfer_fee = $currency->format($row->transfer_fee, $row->symbol, $row->symbol_order,
                            $row->symbol_space);
                        echo $row->transfer_fee;
                        ?>
                    </td>
                    <td class="main_table_cell_active">
                        <?php
                        $row->privacy_fee = $currency->format($row->privacy_fee, $row->symbol, $row->symbol_order,
                            $row->symbol_space);
                        echo $row->privacy_fee;
                        ?>
                    </td>
                    <td class="main_table_cell_active">
                        <?php
                        $row->misc_fee = $currency->format($row->misc_fee, $row->symbol, $row->symbol_order,
                            $row->symbol_space);
                        echo $row->misc_fee;
                        ?>
                    </td>
                    <td class="main_table_cell_active"><?php echo $row->currency; ?></td>
                    <td class="main_table_cell_active">
                        <?php
                        $sql_domain_count = "SELECT count(*) AS total_domain_count
                                         FROM domains
                                         WHERE registrar_id = '" . $row->id . "'
                                           AND fee_id = '" . $row->fee_id . "'
                                           AND active NOT IN ('0', '10')";
                        $result_domain_count = mysqli_query($connection, $sql_domain_count);
                        while ($row_domain_count = mysqli_fetch_object($result_domain_count)) {

                            if ($row_domain_count->total_domain_count == 0) {

                                echo "-";

                            } else {

                                echo "<a class=\"invisiblelink\" href=\"../../domains.php?rid=" . $row->id . "&tld="
                                    . $row->tld . "\">" . $row_domain_count->total_domain_count . "</a>";

                            }

                        } ?>
                    </td>
                    <td class="main_table_cell_active"><?php echo $last_updated; ?></td>
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
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
