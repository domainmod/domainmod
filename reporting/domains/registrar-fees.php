<?php
/**
 * /reporting/domains/registrar-fees.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2022 Greg Chetcuti <greg@chetcuti.com>
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
require_once __DIR__ . '/../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$layout = new DomainMOD\Layout;
$time = new DomainMOD\Time();
$reporting = new DomainMOD\Reporting();
$currency = new DomainMOD\Currency();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/reporting-domain-fees.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$export_data = (int) $_GET['export_data'];
$all = $_GET['all'];

if ($all == "1") {

    $result = $pdo->query("
        SELECT r.id, r.name AS registrar, f.id AS fee_id, f.tld, f.initial_fee, f.renewal_fee, f.transfer_fee,
            f.privacy_fee, f.misc_fee, f.insert_time, f.update_time, c.currency, c.symbol, c.symbol_order,
            c.symbol_space, count(*) AS number_of_fees_total
        FROM registrars AS r, fees AS f, currencies AS c
        WHERE r.id = f.registrar_id
          AND f.currency_id = c.id
        GROUP BY r.name, f.tld
        ORDER BY r.name, f.tld")->fetchAll();

} else {

    $result = $pdo->query("
        SELECT r.id, r.name AS registrar, d.tld, f.id AS fee_id, f.initial_fee, f.renewal_fee, f.transfer_fee,
            f.privacy_fee, f.misc_fee, f.insert_time, f.update_time, c.currency, c.symbol, c.symbol_order,
            c.symbol_space, count(*) AS number_of_fees_total
        FROM registrars AS r, domains AS d, fees AS f, currencies AS c
        WHERE r.id = d.registrar_id
          AND d.fee_id = f.id
          AND f.currency_id = c.id
          AND d.active NOT IN ('0', '10')
        GROUP BY r.name, d.tld
        ORDER BY r.name, d.tld")->fetchAll();

}

$total_rows = count($result);

if ($total_rows > 0) {

    if ($export_data === 1) {

        $export = new DomainMOD\Export();

        if ($all == "1") {

            $export_file = $export->openFile(_('registrar_fee_report_all'), strtotime($time->stamp()));

        } else {

            $export_file = $export->openFile(_('registrar_fee_report_active'), strtotime($time->stamp()));

        }

        $row_contents = array($page_title);
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

        if ($all == "1") {

            $row_contents = array(_('All Registrar Fees'));

        } else {

            $row_contents = array(_('Active Registrar Fees'));

        }
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

        $row_contents = array(
            _('Registrar'),
            _('TLD'),
            _('Initial Fee'),
            _('Renewal Fee'),
            _('Transfer Fee'),
            _('Privacy Fee'),
            _('Misc Fee'),
            _('Currency'),
            _('Domains'),
            _('Inserted'),
            _('Updated')
        );
        $export->writeRow($export_file, $row_contents);

        $new_registrar = "";
        $last_registrar = "";
        $new_tld = "";
        $last_tld = "";

        if ($result) {

            foreach ($result as $row) {

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

                $row_contents[$count++] = $pdo->query("
                    SELECT count(*)
                    FROM domains
                    WHERE registrar_id = '" . $row->id . "'
                      AND fee_id = '" . $row->fee_id . "'
                      AND active NOT IN ('0', '10')")->fetchColumn();

                $row_contents[$count++] = $time->toUserTimezone($row->insert_time);
                $row_contents[$count++] = $time->toUserTimezone($row->update_time);
                $export->writeRow($export_file, $row_contents);

                $last_registrar = $row->registrar;

            }

        }
        $export->closeFile($export_file);

    }

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm select2-red<?php echo $layout->bodyDarkMode(); ?>">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php if ($total_rows > 0) { //@formatter:off ?>
    <a href="registrar-fees.php?export_data=1&all=<?php echo urlencode($all); ?>"><?php echo $layout->showButton('button', _('Export Report')); ?></a><BR><BR>
<?php } //@formatter:on ?>

<a href="registrar-fees.php?all=1"><?php echo $layout->showButton('button', _('View All')); ?></a>&nbsp;&nbsp;&nbsp;or&nbsp;&nbsp;&nbsp;<a href="registrar-fees.php?all=0"><?php echo $layout->showButton('button', _('Active Only')); ?></a><BR><BR>

<?php if ($total_rows > 0) { ?>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th><?php echo _('Registrar'); ?></th>
            <th><?php echo _('TLD'); ?></th>
            <th><?php echo _('Initial'); ?></th>
            <th><?php echo _('Renewal'); ?></th>
            <th><?php echo _('Transfer'); ?></th>
            <th><?php echo _('Privacy'); ?></th>
            <th><?php echo _('Misc'); ?></th>
            <th><?php echo _('Currency'); ?></th>
            <th><?php echo _('Domains'); ?></th>
            <th><?php echo _('Last Updated'); ?></th>
        </tr>
        </thead>
        <tbody><?php

        $new_registrar = "";
        $last_registrar = "";
        $new_tld = "";
        $last_tld = "";

        foreach ($result as $row) {

            $new_registrar = $row->registrar;
            $new_tld = $row->tld;

            if ($row->update_time == '1970-01-01 00:00:00') {
                $row->update_time = $row->insert_time;
            }
            $last_updated = $time->toUserTimezone(date('Y-m-d', strtotime($row->update_time)));

            if ($new_registrar != $last_registrar || $new_registrar == "") { ?>

                <tr>
                    <td></td>
                    <td>
                        <?php echo $row->registrar; ?>
                    </td>
                    <td>
                        .<?php echo $row->tld; ?>
                    </td>
                    <td><?php
                        $row->initial_fee = $currency->format($row->initial_fee, $row->symbol, $row->symbol_order, $row->symbol_space);
                        echo $row->initial_fee; ?>
                    </td>
                    <td>
                        <?php
                        $row->renewal_fee = $currency->format($row->renewal_fee, $row->symbol, $row->symbol_order, $row->symbol_space);
                        echo $row->renewal_fee;
                        ?>
                    </td>
                    <td>
                        <?php
                        $row->transfer_fee = $currency->format($row->transfer_fee, $row->symbol, $row->symbol_order, $row->symbol_space);
                        echo $row->transfer_fee;
                        ?>
                    </td>
                    <td>
                        <?php
                        $row->privacy_fee = $currency->format($row->privacy_fee, $row->symbol, $row->symbol_order, $row->symbol_space);
                        echo $row->privacy_fee;
                        ?>
                    </td>
                    <td>
                        <?php
                        $row->misc_fee = $currency->format($row->misc_fee, $row->symbol, $row->symbol_order, $row->symbol_space);
                        echo $row->misc_fee;
                        ?>
                    </td>
                    <td><?php echo $row->currency; ?></td>
                    <td>
                        <?php
                        $result_domain_count = $pdo->query("
                            SELECT count(*)
                            FROM domains
                            WHERE registrar_id = '" . $row->id . "'
                              AND fee_id = '" . $row->fee_id . "'
                              AND active NOT IN ('0', '10')")->fetchColumn();

                        if (!$result_domain_count) {

                            echo "-";

                        } else {

                            echo "<a href=\"../../domains/index.php?rid=" . $row->id . "&tld="
                                . $row->tld . "\">" . $result_domain_count . "</a>";

                        } ?>
                    </td>
                    <td><?php echo $last_updated; ?></td>
                </tr><?php

                $last_registrar = $row->registrar;
                $last_tld = $row->tld;

            } else { ?>

                <tr>
                    <td></td>
                    <td>&nbsp;</td>
                    <td>
                       .<?php echo $row->tld; ?>
                    </td>
                    <td>
                        <?php
                        $row->initial_fee = $currency->format($row->initial_fee, $row->symbol, $row->symbol_order, $row->symbol_space);
                        echo $row->initial_fee;
                        ?>
                    </td>
                    <td>
                        <?php
                        $row->renewal_fee = $currency->format($row->renewal_fee, $row->symbol, $row->symbol_order, $row->symbol_space);
                        echo $row->renewal_fee;
                        ?>
                    </td>
                    <td>
                        <?php
                        $row->transfer_fee = $currency->format($row->transfer_fee, $row->symbol, $row->symbol_order, $row->symbol_space);
                        echo $row->transfer_fee;
                        ?>
                    </td>
                    <td>
                        <?php
                        $row->privacy_fee = $currency->format($row->privacy_fee, $row->symbol, $row->symbol_order, $row->symbol_space);
                        echo $row->privacy_fee;
                        ?>
                    </td>
                    <td>
                        <?php
                        $row->misc_fee = $currency->format($row->misc_fee, $row->symbol, $row->symbol_order, $row->symbol_space);
                        echo $row->misc_fee;
                        ?>
                    </td>
                    <td><?php echo $row->currency; ?></td>
                    <td>
                        <?php
                        $result_domain_count = $pdo->query("
                            SELECT count(*)
                            FROM domains
                            WHERE registrar_id = '" . $row->id . "'
                              AND fee_id = '" . $row->fee_id . "'
                              AND active NOT IN ('0', '10')")->fetchColumn();

                        if (!$result_domain_count) {

                            echo "-";

                        } else {

                            echo "<a href=\"../../domains/index.php?rid=" . $row->id . "&tld="
                                . $row->tld . "\">" . $result_domain_count . "</a>";

                        } ?>
                    </td>
                    <td><?php echo $last_updated; ?></td>
                </tr><?php

                $last_registrar = $row->registrar;
                $last_tld = $row->tld;

            }

        } ?>

        </tbody>
    </table><?php

} ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
