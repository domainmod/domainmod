<?php
/**
 * /reporting/domains/cost-by-ip-address.php
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
require_once DIR_INC . '/settings/reporting-domain-cost-by-ip.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$export_data = (int) $_GET['export_data'];
$daterange = $sanitize->text($_REQUEST['daterange']);

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
    SELECT ip.id, ip.name, ip.ip, ip.rdns, SUM(d.total_cost * cc.conversion) AS total_cost, count(*) AS number_of_domains
    FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc, ip_addresses AS ip
    WHERE d.fee_id = f.id
      AND f.currency_id = c.id
      AND c.id = cc.currency_id
      AND d.ip_id = ip.id
      AND d.active NOT IN ('0', '10')
      AND cc.user_id = '" . $_SESSION['s_user_id'] . "'
      " . $range_string . "
    GROUP BY ip.name
    ORDER BY ip.name")->fetchAll();

$total_rows = count($result);

$result_grand_total = $pdo->query("
    SELECT SUM(d.total_cost * cc.conversion) AS grand_total, count(*) AS number_of_domains_total
    FROM domains AS d, fees AS f, currencies AS c, currency_conversions AS cc, ip_addresses AS ip
    WHERE d.fee_id = f.id
      AND f.currency_id = c.id
      AND c.id = cc.currency_id
      AND d.ip_id = ip.id
      AND d.active NOT IN ('0', '10')
      AND cc.user_id = '" . $_SESSION['s_user_id'] . "'" .
      $range_string)->fetchAll();

foreach ($result_grand_total as $row_grand_total) {

    $grand_total = $row_grand_total->grand_total;
    $number_of_domains_total = $row_grand_total->number_of_domains_total;

}

$grand_total = $currency->format($grand_total, $_SESSION['s_default_currency_symbol'],
    $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

if ($submission_failed != '1' && $total_rows > 0) {

    if ($export_data === 1) {

        $export = new DomainMOD\Export();

        if ($daterange == '') {

            $export_file = $export->openFile(_('domain_cost_by_ip_address_report_all'), strtotime($time->stamp()));

        } else {

            $export_file = $export->openFile(
                _('domain_cost_by_ip_address_report'),
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
            _('IP Address Name'),
            _('IP Address'),
            _('rDNS'),
            _('Cost'),
            _('Domains'),
            _('Per Domain')
        );
        $export->writeRow($export_file, $row_contents);

        if ($result) {

            foreach ($result as $row) {

                $per_domain = $row->total_cost / $row->number_of_domains;

                $per_domain = $currency->format($per_domain, $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

                $row->total_cost = $currency->format($row->total_cost, $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

                $row_contents = array(
                    $row->name,
                    $row->ip,
                    $row->rdns,
                    $row->total_cost,
                    $row->number_of_domains,
                    $per_domain
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
            <th><?php echo _('Name'); ?></th>
            <th><?php echo _('IP Address'); ?></th>
            <th><?php echo _('rDNS'); ?></th>
            <th><?php echo _('Cost'); ?></th>
            <th><?php echo _('Domains'); ?></th>
            <th><?php echo _('Per Domain'); ?></th>
        </tr>
        </thead>
        <tbody><?php

        foreach ($result as $row) {

            $per_domain = $row->total_cost / $row->number_of_domains;

            $per_domain = $currency->format($per_domain, $_SESSION['s_default_currency_symbol'],
                $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);

            $row->total_cost = $currency->format($row->total_cost, $_SESSION['s_default_currency_symbol'],
                $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']); ?>

            <tr>
                <td></td>
                <td><?php echo $row->name; ?></td>
                <td><?php echo $row->ip; ?></td>
                <td><?php echo $row->rdns; ?></td>
                <td><?php echo $row->total_cost; ?></td>
                <td><a href="../../domains/index.php?ipid=<?php echo $row->id; ?>"><?php echo $row->number_of_domains; ?></a></td>
                <td><?php echo $per_domain; ?></td>
            </tr><?php

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
