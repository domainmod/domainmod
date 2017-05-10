<?php
/**
 * /assets/registrar-fees.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>
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
require_once('../_includes/start-session.inc.php');
require_once('../_includes/init.inc.php');

require_once(DIR_ROOT . '/classes/Autoloader.php');
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$currency = new DomainMOD\Currency();

require_once(DIR_INC . '/head.inc.php');
require_once(DIR_INC . '/config.inc.php');
require_once(DIR_INC . '/software.inc.php');
require_once(DIR_INC . '/settings/assets-registrar-fees.inc.php');
require_once(DIR_INC . '/database.inc.php');

$system->authCheck();

$rid = $_GET['rid'];
$export_data = $_GET['export_data'];

$query = "SELECT `name`
          FROM registrars
          WHERE id = ?";
$q = $dbcon->stmt_init();

if ($q->prepare($query)) {

    $q->bind_param('i', $rid);
    $q->execute();
    $q->store_result();
    $q->bind_result($t_name);

    while ($q->fetch()) {

        $registrar_name = $t_name;

    }

    $q->close();

} else $error->outputSqlError($dbcon, '1', 'ERROR');

$query = "SELECT f.id, f.tld, f.initial_fee, f.renewal_fee, f.transfer_fee, f.privacy_fee, f.misc_fee, f.insert_time, f.update_time, c.currency, c.symbol, c.symbol_order, c.symbol_space
          FROM fees AS f, currencies AS c
          WHERE f.currency_id = c.id
            AND f.registrar_id = ?
          ORDER BY f.tld ASC";

if ($export_data == '1') {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile('registrar_fee_list', strtotime($time->stamp()));

    $row_contents = array($page_title);
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
        'Inserted',
        'Updated'
    );
    $export->writeRow($export_file, $row_contents);

    $q = $dbcon->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $rid);
        $q->execute();
        $q->store_result();
        $q->bind_result($t_fee_id, $t_fee_tld, $t_fee_initial_fee, $t_fee_renewal_fee, $t_fee_transfer_fee, $t_fee_privacy_fee, $t_fee_misc_fee, $t_fee_insert_time, $t_fee_update_time, $t_currency, $t_symbol, $t_order, $t_space);

        if ($q->num_rows() > 0) {

            while ($q->fetch()) {

                $row_contents = array(
                    $registrar_name,
                    $t_fee_tld,
                    $t_fee_initial_fee,
                    $t_fee_renewal_fee,
                    $t_fee_transfer_fee,
                    $t_fee_privacy_fee,
                    $t_fee_misc_fee,
                    $t_currency,
                    $time->toUserTimezone($t_fee_insert_time),
                    $time->toUserTimezone($t_fee_update_time)
                );
                $export->writeRow($export_file, $row_contents);

            }

        }

        $q->close();

    } else $error->outputSqlError($dbcon, '1', 'ERROR');

    $export->closeFile($export_file);

}
?>
<?php require_once(DIR_INC . '/doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($page_title); ?></title>
    <?php require_once(DIR_INC . '/layout/head-tags.inc.php'); ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php require_once(DIR_INC . '/layout/header.inc.php'); ?>
Below is a list of all the fees associated with <a href="edit/registrar.php?rid=<?php echo urlencode($rid); ?>"><?php echo $registrar_name; ?></a>.<BR><BR>
<a href="add/registrar-fee.php?rid=<?php echo urlencode($rid); ?>"><?php echo $layout->showButton('button', 'Add Fee'); ?></a>&nbsp;&nbsp;&nbsp;
<a href="registrar-fees.php?rid=<?php echo urlencode($rid); ?>&export_data=1"><?php echo $layout->showButton('button', 'Export'); ?></a><BR><BR><?php

$query2 = "SELECT tld
           FROM domains
           WHERE registrar_id = ?
             AND fee_id = '0'
           GROUP BY tld
           ORDER BY tld ASC";
$q2 = $dbcon->stmt_init();

if ($q2->prepare($query2)) {

    $q2->bind_param('i', $rid);
    $q2->execute();
    $q2->store_result();
    $q2->bind_result($t_tld);

    if ($q2->num_rows() > 0) { ?>

        <h4>Missing TLD Fees</h4><?php

        $count = 0;

        while ($q2->fetch()) {

            $temp_all_missing_fees = $temp_all_missing_fees .= "<a href=\"add/registrar-fee.php?rid=" . $rid . "&tld=" . $t_tld . "\">." . $t_tld . "</a>, ";
            $count++;

        }

        $all_missing_fees = substr($temp_all_missing_fees, 0, -2); ?>
        <strong><?php echo $all_missing_fees; ?></strong><BR>
        <?php if ($count == 1) { ?>
        You have domains with <?php echo $registrar_name; ?> that use this TLDs, however there are no fees associated with it yet. You should add this fee as soon as possible.<BR><BR><BR>
        <?php } else { ?>
        You have domains with <?php echo $registrar_name; ?> that use these TLDs, however there are no fees associated with them yet. You should add these fees as soon as possible.<BR><BR><BR>
        <?php }

    }

    $q2->close();

} else $error->outputSqlError($dbcon, '1', 'ERROR');

$q = $dbcon->stmt_init();

if ($q->prepare($query)) {

    $q->bind_param('i', $rid);
    $q->execute();
    $q->store_result();
    $q->bind_result($t_fee_id, $t_fee_tld, $t_fee_initial_fee, $t_fee_renewal_fee, $t_fee_transfer_fee, $t_fee_privacy_fee, $t_fee_misc_fee, $t_fee_insert_time, $t_fee_update_time, $t_currency, $t_symbol, $t_order, $t_space);

    if ($q->num_rows() > 0) { ?>

        <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
            <thead>
            <tr>
                <th width="20px"></th>
                <th>TLD</th>
                <th>Initial</th>
                <th>Renewal</th>
                <th>Transfer</th>
                <th>Privacy</th>
                <th>Misc</th>
                <th>Currency</th>
            </tr>
            </thead>
            <tbody><?php

            while ($q->fetch()) { ?>

                <tr>
                <td></td>
                <td>
                    .<a href="edit/registrar-fee.php?rid=<?php echo urlencode($rid); ?>&fee_id=<?php echo urlencode($t_fee_id); ?>"><?php echo $t_fee_tld; ?></a>
                </td>
                <td><?php
                    if ($t_fee_initial_fee > 0) {

                        $t_fee_initial_fee = $currency->format($t_fee_initial_fee, $t_symbol, $t_order, $t_space);
                        echo $t_fee_initial_fee;

                    } else {

                        echo '-';

                    }?>
                </td>
                <td>
                    <?php
                    if ($t_fee_renewal_fee > 0) {

                        $t_fee_renewal_fee = $currency->format($t_fee_renewal_fee, $t_symbol, $t_order, $t_space);
                        echo $t_fee_renewal_fee;

                    } else {

                        echo '-';

                    }?>
                </td>
                <td>
                    <?php
                    if ($t_fee_transfer_fee > 0) {

                        $t_fee_transfer_fee = $currency->format($t_fee_transfer_fee, $t_symbol, $t_order, $t_space);
                        echo $t_fee_transfer_fee;

                    } else {

                        echo '-';

                    }?>
                </td>
                <td>
                    <?php
                    if ($t_fee_privacy_fee > 0) {

                        $t_fee_privacy_fee = $currency->format($t_fee_privacy_fee, $t_symbol, $t_order, $t_space);
                        echo $t_fee_privacy_fee;

                    } else {

                        echo '-';

                    }?>
                </td>
                <td>
                    <?php
                    if ($t_fee_misc_fee > 0) {

                        $t_fee_misc_fee = $currency->format($t_fee_misc_fee, $t_symbol, $t_order, $t_space);
                        echo $t_fee_misc_fee;

                    } else {

                        echo '-';

                    }?>
                </td>
                <td>
                    <?php echo $t_currency; ?>
                </td>
                </tr><?php

            } ?>

            </tbody>
        </table><?php

    } else { ?>

        <BR>You don't currently have any fees associated with this domain registrar. <a href="add/registrar-fee.php?rid=<?php echo urlencode($rid); ?>">Click here to add one</a>.<?php


    }

    $q->close();

} else $error->outputSqlError($dbcon, '1', 'ERROR');

require_once(DIR_INC . '/layout/footer.inc.php'); //@formatter:on ?>
</body>
</html>
