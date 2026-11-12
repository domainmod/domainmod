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

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/reporting-dw-potential-problems.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$generate = $_GET['generate'] ?? 0;
$export_data = (int) ($_GET['export_data'] ?? 0);

// the Data Warehouse tables only exist once it has been built
$dwaccounts = new DomainMOD\DwAccounts();
$dwzones = new DomainMOD\DwZones();
$dw_built = $dwaccounts->checkForAccountTable() && $dwzones->checkForZoneTable();

$result_accounts_without_a_dns_zone = !$dw_built ? array() : $pdo->query("
    SELECT domain
    FROM dw_accounts
    WHERE domain NOT IN (SELECT domain FROM dw_dns_zones)
    ORDER BY domain")->fetchAll();
$sql_accounts_without_a_dns_zone = "";
$temp_accounts_without_a_dns_zone = count($result_accounts_without_a_dns_zone);

$result_dns_zones_without_an_account = !$dw_built ? array() : $pdo->query("
    SELECT domain
    FROM dw_dns_zones
    WHERE domain NOT IN (SELECT domain FROM dw_accounts)
    ORDER BY domain")->fetchAll();
$temp_dns_zones_without_an_account = count($result_dns_zones_without_an_account);

$result_suspended_accounts = !$dw_built ? array() : $pdo->query("
    SELECT domain
    FROM dw_accounts
    WHERE suspended = '1'
    ORDER BY domain")->fetchAll();
$temp_suspended_accounts = count($result_suspended_accounts);

if ($export_data === 1) {

    $export = new DomainMOD\Export();

    $export_file = $export->openFile(_('dw_potential_problems_report'), strtotime($time->stamp()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    if ($temp_accounts_without_a_dns_zone == 0) {

        $accounts_without_a_dns_zone_flag = 1;

    } else {

        foreach ($result_accounts_without_a_dns_zone as $row_accounts_without_a_dns_zone) {

            $row_contents = array(sprintf(_('Accounts without a DNS Zone (%s)'), $temp_accounts_without_a_dns_zone), $row_accounts_without_a_dns_zone->domain);
            $export->writeRow($export_file, $row_contents);

        }

    }

    if ($temp_dns_zones_without_an_account == 0) {

        $dns_zones_without_an_account_flag = 1;

    } else {

        foreach ($result_dns_zones_without_an_account as $row_dns_zones_without_an_account) {

            $row_contents = array(sprintf(_('DNS Zones without an Account (%s)'), $temp_dns_zones_without_an_account), $row_dns_zones_without_an_account->domain);
            $export->writeRow($export_file, $row_contents);

        }

    }

    if ($temp_suspended_accounts == 0) {

        $suspended_accounts_flag = 1;

    } else {

        foreach ($result_suspended_accounts as $row_suspended_accounts) {

            $row_contents = array(sprintf(_('Suspended Accounts (%s)'), $temp_suspended_accounts), $row_suspended_accounts->domain);
            $export->writeRow($export_file, $row_contents);

        }

    }
    $export->closeFile($export_file);

} else {

    $total_rows = '0';

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
<?php if ($temp_accounts_without_a_dns_zone != 0 || $temp_dns_zones_without_an_account != 0 || $temp_suspended_accounts != 0) { ?>

    <BR><a href="<?php echo $report_filename; ?>?export_data=1<?php echo $layout->showButton('button', _('Export')); ?></a>
    <BR><BR><?php
}

if ($temp_accounts_without_a_dns_zone != 0 || $temp_dns_zones_without_an_account != 0 || $temp_suspended_accounts != 0) {

    if ($generate == 1) {

        if ($temp_accounts_without_a_dns_zone == 0) {

            $accounts_without_a_dns_zone_flag = 1;

        } else { ?>

            <strong><?php echo sprintf(_('Accounts without a DNS Zone (%s)'), $temp_accounts_without_a_dns_zone); ?></strong><BR><?php

            foreach ($result_accounts_without_a_dns_zone as $row_accounts_without_a_dns_zone) {

                $account_list_raw .= htmlspecialchars($row_accounts_without_a_dns_zone->domain, ENT_QUOTES, 'UTF-8') . ", ";

            }

            $account_list = substr($account_list_raw, 0, -2);

            if ($account_list != '') {

                echo $account_list;

            } else {

                echo "n/a";

            }

            echo "<BR><BR>";

        }

        if ($temp_dns_zones_without_an_account == 0) {

            $dns_zones_without_an_account_flag = 1;

        } else { ?>

            <strong>DNS Zones without an Account (<?php echo $temp_dns_zones_without_an_account; ?>)</strong><BR><?php

            foreach ($result_dns_zones_without_an_account as $row_dns_zones_without_an_account) {

                $zone_list_raw .= htmlspecialchars($row_dns_zones_without_an_account->domain, ENT_QUOTES, 'UTF-8') . ", ";

            }

            $zone_list = substr($zone_list_raw, 0, -2);

            if ($zone_list != '') {

                echo $zone_list;

            } else {

                echo "n/a";

            }

            echo "<BR><BR>";

        }

        if ($temp_suspended_accounts == 0) {

            $suspended_accounts_flag = 1;

        } else { ?>

            <strong>Suspended Accounts (<?php echo $temp_suspended_accounts; ?>)</strong><BR><?php

            foreach ($result_suspended_accounts as $row_suspended_accounts) {

                $suspended_list_raw .= htmlspecialchars($row_suspended_accounts->domain, ENT_QUOTES, 'UTF-8') . ", ";

            }

            $suspended_list = substr($suspended_list_raw, 0, -2);

            if ($suspended_list != '') {

                echo $suspended_list;

            } else {

                echo "n/a";

            }

        }
    }

} elseif (!$dw_built) {

    echo '<BR>' . _('The Data Warehouse hasn\'t been built yet, so there\'s nothing to report.') . '<BR><BR>';

} else {

    echo '<BR>' . _('No results.') . '<BR><BR>';

}
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
