<?php
/**
 * /reporting/domains/cost-by-registrar.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2024 Greg Chetcuti <greg@chetcuti.com>
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
require_once __DIR__ . '/../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$layout = new DomainMOD\Layout;
$date = new DomainMOD\Date();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$reporting = new DomainMOD\Reporting();
$currency = new DomainMOD\Currency();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/reporting-domain-cost-by-registrar.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$export_data = (int) ($_GET['export_data'] ?? 0);
$daterange = isset($_REQUEST['daterange']) ? $sanitize->text($_REQUEST['daterange']) : '';

list($new_start_date, $new_end_date) = $date->splitAndCheckRange($daterange);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $date = new DomainMOD\Date();

    if ($new_start_date > $new_end_date) {

        $_SESSION['s_message_danger'] .= _('The end date proceeds the start date') . '<BR>';
        $submission_failed = '1';

    }

}

$range_string = $reporting->getRangeString('d.expiry_date', $new_start_date, $new_end_date);

$result = $pdo->query("
    SELECT r.id, r.name AS registrar_name, o.name AS owner_name, ra.id AS registrar_account_id, ra.username, SUM(d.total_cost * cc.conversion) AS total_cost, count(*) AS number_of_domains
    FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc, registrars AS r,
        registrar_accounts AS ra, owners AS o
    WHERE d.fee_id = f.id
      AND f.currency_id = c.id
      AND c.id = cc.currency_id
      AND d.registrar_id = r.id
      AND d.account_id = ra.id
      AND d.owner_id = o.id
      AND d.active NOT IN ('0', '10')
      AND cc.user_id = '" . $_SESSION['s_user_id'] . "'" .
      $range_string . "
    GROUP BY r.name, o.name, ra.username
    ORDER BY r.name, o.name, ra.username")->fetchAll();

$total_rows = count($result);

$result_grand_total = $pdo->query("
    SELECT SUM(d.total_cost * cc.conversion) AS grand_total, count(*) AS number_of_domains_total
    FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc, registrars AS r, registrar_accounts AS ra, owners AS o
    WHERE d.fee_id = f.id
      AND f.currency_id = c.id
      AND c.id = cc.currency_id
      AND d.registrar_id = r.id
      AND d.account_id = ra.id
      AND d.owner_id = o.id
      AND d.active NOT IN ('0', '10')
      AND cc.user_id = '" . $_SESSION['s_user_id'] . "'" .
      $range_string)->fetchAll();

foreach ($result_grand_total as $row_grand_total) {

    $grand_total = $row_grand_total->grand_total;
    $number_of_domains_total = $row_grand_total->number_of_domains_total;

}

$grand_total = $currency->format($grand_total, $_SESSION['s_default_currency_symbol'],
    $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

$submission_failed = $submission_failed ?? 0;
if ($submission_failed != '1' && $total_rows > 0) {

    if ($export_data === 1) {

        $export = new DomainMOD\Export();

        if ($daterange == '') {

            $export_file = $export->openFile(_('domain_cost_by_registrar_report_all'), strtotime($time->stamp()));

        } else {

            $export_file = $export->openFile(
                _('domain_cost_by_registrar_report'),
                $new_start_date . '--' . $new_end_date
            );

        }

        $row_contents = array($page_title);
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

        if ($daterange == '') {

            $row_contents = array(_('Date Range') . ':', strtoupper(_('All')));

        } else {

            $row_contents = array(_('Date Range') . ':', $daterange);

        }
        $export->writeRow($export_file, $row_contents);

        $row_contents = array(
            _('Total Cost') . ':',
            $grand_total,
            $_SESSION['s_default_currency']
        );
        $export->writeRow($export_file, $row_contents);

        $row_contents = array(
            _('Number of Domains') . ':',
            $number_of_domains_total
        );
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

        $row_contents = array(
            _('Registrar'),
            _('Cost'),
            _('Domains'),
            _('Per Domain'),
            _('Registrar Account'),
            _('Cost'),
            _('Domains'),
            _('Per Domain')
        );
        $export->writeRow($export_file, $row_contents);

        $new_registrar = '';
        $last_registrar = '';

        if ($result) {

            foreach ($result as $row) {

                $new_registrar = $row->registrar_name;

                $result_registrar_total = $pdo->query("
                    SELECT SUM(d.total_cost * cc.conversion) AS registrar_total, count(*) AS number_of_domains_registrar
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
                      AND r.id = '" . $row->id . "'" .
                      $range_string)->fetchAll();

                foreach ($result_registrar_total as $row_registrar_total) {

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
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
    <?php require_once DIR_INC . '/layout/date-range-picker-head.inc.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm select2-red<?php echo $layout->bodyDarkMode(); ?>">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php require_once DIR_INC . '/layout/reporting-block.inc.php'; ?>
<?php
if ($submission_failed != '1' && $total_rows > 0) { ?>

    <?php require_once DIR_INC . '/layout/reporting-block-sub.inc.php'; ?>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th><?php echo _('Registrar'); ?></th>
            <th><?php echo _('Cost'); ?></th>
            <th><?php echo _('Domains'); ?></th>
            <th><?php echo _('Per Domain'); ?></th>
            <th><?php echo _('Account'); ?></th>
            <th><?php echo _('Cost'); ?></th>
            <th><?php echo _('Domains'); ?></th>
            <th><?php echo _('Per Domain'); ?></th>
        </tr>
        </thead>
        <tbody><?php

        $new_registrar = '';
        $last_registrar = '';

        foreach ($result as $row) {

            $new_registrar = $row->registrar_name;

            $result_registrar_total = $pdo->query("
                SELECT SUM(d.total_cost * cc.conversion) AS registrar_total, count(*) AS number_of_domains_registrar
                FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc, registrars AS r, registrar_accounts AS ra, owners AS o
                WHERE d.fee_id = f.id
                  AND f.currency_id = c.id
                  AND c.id = cc.currency_id
                  AND d.registrar_id = r.id
                  AND d.account_id = ra.id
                  AND d.owner_id = o.id
                  AND d.active NOT IN ('0', '10')
                  AND cc.user_id = '" . $_SESSION['s_user_id'] . "'
                  AND r.id = '" . $row->id . "'" .
                  $range_string)->fetchAll();

            foreach ($result_registrar_total as $row_registrar_total) {

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

    echo _('No results.') . '<BR>';

}
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
<?php require_once DIR_INC . '/layout/date-range-picker-footer.inc.php'; ?>
</body>
</html>
