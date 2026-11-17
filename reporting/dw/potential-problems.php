<?php
/**
 * /reporting/dw/potential-problems.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2026 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: https://greg.ca
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
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$reporting = new DomainMOD\Reporting();
$dashboard = new DomainMOD\Dashboard();
$chart = new DomainMOD\Chart();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/reporting-dw-potential-problems.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

// The Reporting index links here with ?generate=1. Everything shows with or without it, so it's still accepted.
$generate = $_GET['generate'] ?? 0;
$export_data = (int) ($_GET['export_data'] ?? 0);

// the Data Warehouse tables only exist once it has been built
$dwaccounts = new DomainMOD\DwAccounts();
$dwzones = new DomainMOD\DwZones();
$dw_built = $dwaccounts->checkForAccountTable() && $dwzones->checkForZoneTable();

// Each problem's rows, with the server's name, and the domain's id in DomainMOD when it has the domain (the Data
// Warehouse stores domains the same way as the domains table, so they match exactly)
$result_accounts_without_a_dns_zone = !$dw_built ? array() : $pdo->query("
    SELECT a.domain, a.user, s.name AS server_name,
        (SELECT MIN(d.id) FROM domains AS d WHERE d.domain = a.domain) AS domain_id
    FROM dw_accounts AS a
    LEFT JOIN dw_servers AS s ON a.server_id = s.id
    WHERE a.domain NOT IN (SELECT domain FROM dw_dns_zones)
    ORDER BY a.domain")->fetchAll();
$temp_accounts_without_a_dns_zone = count($result_accounts_without_a_dns_zone);

$result_dns_zones_without_an_account = !$dw_built ? array() : $pdo->query("
    SELECT z.domain, z.zonefile, s.name AS server_name,
        (SELECT MIN(d.id) FROM domains AS d WHERE d.domain = z.domain) AS domain_id
    FROM dw_dns_zones AS z
    LEFT JOIN dw_servers AS s ON z.server_id = s.id
    WHERE z.domain NOT IN (SELECT domain FROM dw_accounts)
    ORDER BY z.domain")->fetchAll();
$temp_dns_zones_without_an_account = count($result_dns_zones_without_an_account);

$result_suspended_accounts = !$dw_built ? array() : $pdo->query("
    SELECT a.domain, a.user, a.suspendreason, a.suspendtime, s.name AS server_name,
        (SELECT MIN(d.id) FROM domains AS d WHERE d.domain = a.domain) AS domain_id
    FROM dw_accounts AS a
    LEFT JOIN dw_servers AS s ON a.server_id = s.id
    WHERE a.suspended = '1'
    ORDER BY a.domain")->fetchAll();
$temp_suspended_accounts = count($result_suspended_accounts);

// Each server's accounts split three ways that don't overlap (suspended, no DNS zone but not suspended, and the rest
// healthy), plus its DNS zones without an account, which aren't accounts and so aren't part of the bar
$result_servers = !$dw_built ? array() : $pdo->query("
    SELECT s.id, s.name,
        (SELECT COUNT(*) FROM dw_accounts AS a WHERE a.server_id = s.id) AS accounts,
        (SELECT COUNT(*) FROM dw_accounts AS a WHERE a.server_id = s.id AND a.suspended = '1') AS suspended,
        (SELECT COUNT(*) FROM dw_accounts AS a WHERE a.server_id = s.id AND a.suspended != '1'
           AND a.domain NOT IN (SELECT domain FROM dw_dns_zones)) AS no_zone,
        (SELECT COUNT(*) FROM dw_dns_zones AS z WHERE z.server_id = s.id
           AND z.domain NOT IN (SELECT domain FROM dw_accounts)) AS no_account
    FROM dw_servers AS s
    ORDER BY s.name")->fetchAll();
foreach ($result_servers as $server) {
    $server->healthy = (int) $server->accounts - (int) $server->suspended - (int) $server->no_zone;
}

$has_problems = $temp_accounts_without_a_dns_zone != 0 || $temp_dns_zones_without_an_account != 0 || $temp_suspended_accounts != 0;

// A Data Warehouse value for the page: server names are stored escaped already, and everything else as WHM sent it
$escape = function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};

// The domain, linking to its DomainMOD edit page when DomainMOD has it, otherwise with a "Not in DomainMOD" badge
$domain_cell = function ($row) use ($escape, $layout) {
    if ($row->domain_id) {
        return '<a href="' . WEB_ROOT . '/domains/edit.php?did=' . (int) $row->domain_id . '">' . $escape($row->domain) . '</a>';
    }
    return $escape($row->domain) . ' ' . $layout->statusBadge(_('Not in DomainMOD'), _('This domain is on the server, but DomainMOD doesn\'t have it'), 'secondary');
};

// WHM sends the suspension time as a Unix timestamp, and 0 when there isn't one
$suspended_when = function ($row) {
    return (int) $row->suspendtime > 0 ? DomainMOD\DwDisplay::timestampDate($row->suspendtime) : '';
};

if ($export_data === 1) {

    $export = new DomainMOD\Export();

    $export_file = $export->openFile(_('dw_potential_problems_report'), strtotime($time->stamp()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    foreach ($result_accounts_without_a_dns_zone as $row_accounts_without_a_dns_zone) {

        $row_contents = array(sprintf(_('Accounts without a DNS Zone (%s)'), $temp_accounts_without_a_dns_zone), $row_accounts_without_a_dns_zone->domain);
        $export->writeRow($export_file, $row_contents);

    }

    foreach ($result_dns_zones_without_an_account as $row_dns_zones_without_an_account) {

        $row_contents = array(sprintf(_('DNS Zones without an Account (%s)'), $temp_dns_zones_without_an_account), $row_dns_zones_without_an_account->domain);
        $export->writeRow($export_file, $row_contents);

    }

    foreach ($result_suspended_accounts as $row_suspended_accounts) {

        $row_contents = array(sprintf(_('Suspended Accounts (%s)'), $temp_suspended_accounts), $row_suspended_accounts->domain);
        $export->writeRow($export_file, $row_contents);

    }
    $export->closeFile($export_file);

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
if (!$dw_built) {

    echo $layout->calloutTop() . _('The Data Warehouse hasn\'t been built yet, so there\'s nothing to report.') . $layout->calloutBottom();

} else {

    if ($has_problems) {

        // The page's actions, drawn in the page header (see Layout::renderPageActions())
        $layout->addPageAction(_('Export'), $report_filename . '?export_data=1', 'bi-download');

    } ?>

    <div class="row"><?php
        echo $dashboard->displayPanel(_('Accounts without a DNS Zone'), $temp_accounts_without_a_dns_zone, 'warning', 'bi-diagram-2', '/reporting/dw/potential-problems.php?generate=1#no-zone', _('DW Potential Problems'));
        echo $dashboard->displayPanel(_('DNS Zones without an Account'), $temp_dns_zones_without_an_account, 'warning', 'bi-question-diamond', '/reporting/dw/potential-problems.php?generate=1#no-account', _('DW Potential Problems'));
        echo $dashboard->displayPanel(_('Suspended Accounts'), $temp_suspended_accounts, 'danger', 'bi-pause-circle', '/reporting/dw/potential-problems.php?generate=1#suspended', _('DW Potential Problems')); ?>
    </div><?php

    if (!$has_problems) { ?>

        <div class="callout callout-success mt-4"><?php echo _('No problems found. Every account has a DNS zone, every DNS zone has an account, and no accounts are suspended.'); ?></div><?php

    }

    if ($result_servers) {

        ob_start(); ?>

        <table id="<?php echo $slug; ?>-servers" class="<?php echo $datatable_class; ?> mt-3">
            <thead>
            <tr>
                <th><span class="visually-hidden"><?php echo _('Details'); ?></span></th>
                <th><?php echo _('Server'); ?></th>
                <th><?php echo _('Accounts'); ?></th>
                <th><?php echo _('Healthy'); ?></th>
                <th><?php echo _('No DNS Zone'); ?></th>
                <th><?php echo _('Suspended'); ?></th>
                <th><?php echo _('DNS Zones without an Account'); ?></th>
            </tr>
            </thead>
            <tbody><?php
            foreach ($result_servers as $server) { ?>

                <tr>
                    <td></td>
                    <td><?php echo $server->name; ?></td>
                    <td><?php echo number_format((int) $server->accounts); ?></td>
                    <td><?php echo number_format($server->healthy); ?></td>
                    <td><?php echo number_format((int) $server->no_zone); ?></td>
                    <td><?php echo number_format((int) $server->suspended); ?></td>
                    <td><?php echo number_format((int) $server->no_account); ?></td>
                </tr><?php

            } ?>

            </tbody>
        </table><?php

        $servers_table = ob_get_clean();

        echo $chart->stackedBars('dw-server-health-chart', _('Accounts by Server'),
            array_map(function ($server) { return $server->name; }, $result_servers),
            array(
                array('name' => _('Healthy'), 'values' => array_column($result_servers, 'healthy'), 'colour' => '3'),
                array('name' => _('No DNS Zone'), 'values' => array_map('intval', array_column($result_servers, 'no_zone')), 'colour' => '4'),
                array('name' => _('Suspended'), 'values' => array_map('intval', array_column($result_servers, 'suspended')), 'colour' => '2')),
            $servers_table);

    }

    if ($temp_accounts_without_a_dns_zone != 0) { ?>

        <h2 class="h4 mt-4" id="no-zone"><?php echo sprintf(_('Accounts without a DNS Zone (%s)'), $temp_accounts_without_a_dns_zone); ?></h2>
        <table id="<?php echo $slug; ?>-no-zone" class="<?php echo $datatable_class; ?>">
            <thead>
            <tr>
                <th><span class="visually-hidden"><?php echo _('Details'); ?></span></th>
                <th><?php echo _('Domain'); ?></th>
                <th><?php echo _('Server'); ?></th>
                <th><?php echo _('cPanel User'); ?></th>
            </tr>
            </thead>
            <tbody><?php
            foreach ($result_accounts_without_a_dns_zone as $row) { ?>

                <tr>
                    <td></td>
                    <td data-order="<?php echo $escape($row->domain); ?>"><?php echo $domain_cell($row); ?></td>
                    <td><?php echo $row->server_name; ?></td>
                    <td><?php echo $escape($row->user); ?></td>
                </tr><?php

            } ?>

            </tbody>
        </table><?php

    }

    if ($temp_dns_zones_without_an_account != 0) { ?>

        <h2 class="h4 mt-4" id="no-account"><?php echo sprintf(_('DNS Zones without an Account (%s)'), $temp_dns_zones_without_an_account); ?></h2>
        <table id="<?php echo $slug; ?>-no-account" class="<?php echo $datatable_class; ?>">
            <thead>
            <tr>
                <th><span class="visually-hidden"><?php echo _('Details'); ?></span></th>
                <th><?php echo _('Domain'); ?></th>
                <th><?php echo _('Server'); ?></th>
                <th><?php echo _('Zone File'); ?></th>
            </tr>
            </thead>
            <tbody><?php
            foreach ($result_dns_zones_without_an_account as $row) { ?>

                <tr>
                    <td></td>
                    <td data-order="<?php echo $escape($row->domain); ?>"><?php echo $domain_cell($row); ?></td>
                    <td><?php echo $row->server_name; ?></td>
                    <td><?php echo $escape($row->zonefile); ?></td>
                </tr><?php

            } ?>

            </tbody>
        </table><?php

    }

    if ($temp_suspended_accounts != 0) { ?>

        <h2 class="h4 mt-4" id="suspended"><?php echo sprintf(_('Suspended Accounts (%s)'), $temp_suspended_accounts); ?></h2>
        <table id="<?php echo $slug; ?>-suspended" class="<?php echo $datatable_class; ?>">
            <thead>
            <tr>
                <th><span class="visually-hidden"><?php echo _('Details'); ?></span></th>
                <th><?php echo _('Domain'); ?></th>
                <th><?php echo _('Server'); ?></th>
                <th><?php echo _('cPanel User'); ?></th>
                <th><?php echo _('Reason'); ?></th>
                <th><?php echo _('Suspended'); ?></th>
            </tr>
            </thead>
            <tbody><?php
            foreach ($result_suspended_accounts as $row) { ?>

                <tr>
                    <td></td>
                    <td data-order="<?php echo $escape($row->domain); ?>"><?php echo $domain_cell($row); ?></td>
                    <td><?php echo $row->server_name; ?></td>
                    <td><?php echo $escape($row->user); ?></td>
                    <td><?php echo $escape($row->suspendreason); ?></td>
                    <td data-order="<?php echo (int) $row->suspendtime; ?>"><?php echo $suspended_when($row); ?></td>
                </tr><?php

            } ?>

            </tbody>
        </table><?php

    }

}
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
<?php require_once DIR_INC . '/layout/chart-footer.inc.php'; ?>
</body>
</html>
