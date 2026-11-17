<?php
/**
 * /admin/dw/dw.php
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

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/dw-main.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'] ?? 0);
$pdo = $deeb->cnxx;
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
$result = $pdo->query("
    SELECT id
    FROM dw_servers
    LIMIT 1")->fetchAll();

if (!$result) {

    $has_servers = 0;

} else {

    $has_servers = 1;

}
?>
<?php
// The page's actions, drawn in the page header (see Layout::renderPageActions()). Rebuild DW starts the build straight
// away, so it asks first.
$layout->addPageAction(_('Manage Servers'), 'servers.php', 'bi-hdd-stack');
if ($has_servers == 1) {

    $layout->addPageAction(_('Rebuild DW'), 'rebuild.php?' . (new DomainMOD\Csrf())->query(), 'bi-arrow-repeat', array('confirm' => array('message' => _('Rebuild the Data Warehouse now? This can take a while.'))));

}

if ($has_servers == 0) {

    echo $layout->emptyState('bi-database', _('No servers in the Data Warehouse yet'), _('Add a WHM server to import its accounts, DNS zones, and records.'), '/admin/dw/add-server.php', _('Add Web Server'), '/admin/dw/intro.php', _('What is the Data Warehouse?'));

}

if ($has_servers == 1) {

    // the Data Warehouse tables only exist once it has been built
    $dwaccounts_check = new DomainMOD\DwAccounts();
    $dwzones_check = new DomainMOD\DwZones();
    $dw_built = $dwaccounts_check->checkForAccountTable() && $dwzones_check->checkForZoneTable();

    $servers = $pdo->query("
        SELECT id, `name`, build_status, build_start_time, build_end_time, build_time, has_ever_been_built,
            dw_accounts, dw_dns_zones, dw_dns_records
        FROM dw_servers
        ORDER BY `name`, `host`")->fetchAll();

    $full_build = $pdo->query("
        SELECT build_status_overall, build_start_time_overall, build_end_time_overall, build_time_overall,
            has_ever_been_built_overall
        FROM dw_servers
        ORDER BY build_end_time_overall DESC
        LIMIT 1")->fetch();

    // Whether servers are still waiting their turn in the build, which is otherwise in its cleanup
    $servers_left = $pdo->query("
        SELECT id
        FROM dw_servers
        WHERE build_status = '0'
        LIMIT 1")->fetchColumn() !== false;

    // A full build is running while it has a start but no end, and its status isn't done
    $is_building = !DomainMOD\Time::isUnset($full_build->build_start_time_overall)
        && DomainMOD\Time::isUnset($full_build->build_end_time_overall) && $full_build->build_status_overall == 0;

    /*
     * The status of the last full build ($is_full_build) or of one server's build: 'successful', 'building', 'cleanup'
     * (the full build, once no server is waiting), 'pending' (a server waiting its turn), 'never' (never built), or ''
     * when nothing applies
     */
    $build_status = function ($start, $end, $status, $ever_built, $is_full_build) use ($is_building, $servers_left) {
        $started = !DomainMOD\Time::isUnset($start);
        $ended = !DomainMOD\Time::isUnset($end);
        if (!$started) {
            if ($is_full_build) return $ever_built == 0 ? 'never' : '';
            return $ever_built == 1 || $is_building ? 'pending' : 'never';
        }
        if (!$ended && $status == 0) return $is_full_build && !$servers_left ? 'cleanup' : 'building';
        if ($ever_built == 0) return 'building';
        return $ended ? 'successful' : '';
    };

    // Each status's label and badge colour
    $status_labels = array(
        'successful' => array(_('Successful'), 'success'),
        'building' => array(_('Building') . '...', 'info'),
        'cleanup' => array(_('Cleanup') . '...', 'info'),
        'pending' => array(_('Pending'), 'secondary'),
        'never' => array(_('Never Built'), 'secondary'));

    // A build's time in seconds as "45s" or "2m 5s", or "-" when it has none
    $build_time = function ($seconds) {
        $seconds = (int) $seconds;
        if ($seconds <= 0) return '-';
        if ($seconds <= 60) return number_format($seconds) . 's';
        return intval($seconds / 60) . 'm ' . ($seconds % 60) . 's';
    };

    // A server's count, right-aligned (like the times, as DataTables aligns numbers and dates), linking to its list
    // while there's something to show and no build is running
    $count_cell = function ($count, $url) use ($dw_built, $is_building) {
        $count = (int) $count;
        $text = number_format($count);
        if ($url !== '' && $count > 0 && $dw_built && !$is_building) {
            $text = '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">' . $text . '</a>';
        }
        return '<td class="text-end" data-order="' . $count . '">' . $text . '</td>';
    };

    $full_status = $build_status($full_build->build_start_time_overall, $full_build->build_end_time_overall,
        $full_build->build_status_overall, $full_build->has_ever_been_built_overall, true); ?>

    <h2 class="h4 mt-4"><?php echo _('Last full build'); ?></h2><?php

    echo $layout->factList(array(
        array('label' => _('Build Start'), 'value' => $time->toUserTimezone($full_build->build_start_time_overall, 'Y-m-d H:i', '-'), 'ltr' => true),
        array('label' => _('Build Time'), 'value' => $build_time($full_build->build_time_overall), 'ltr' => true),
        array('label' => _('Build Status'), 'value' => $full_status === '' ? '-' : $status_labels[$full_status][0]))); ?>

    <h2 class="h4 mt-4"><?php echo _('Servers'); ?></h2>
    <table id="<?php echo $slug; ?>-servers" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th><span class="visually-hidden"><?php echo _('Details'); ?></span></th>
            <th><?php echo _('Server'); ?></th>
            <th class="text-end"><?php echo _('Accounts'); ?></th>
            <th class="text-end"><?php echo _('DNS Zones'); ?></th>
            <th class="text-end"><?php echo _('DNS Records'); ?></th>
            <th class="text-end"><?php echo _('Last Build'); ?></th>
            <th class="text-end"><?php echo _('Build Time'); ?></th>
            <th><?php echo _('Status'); ?></th>
        </tr>
        </thead>
        <tbody><?php

        if (count($servers) > 1) { ?>

            <tr>
                <td></td>
                <td><em><?php echo _('All servers'); ?></em></td>
                <?php echo $count_cell(array_sum(array_column($servers, 'dw_accounts')), 'list-accounts.php?server=all'); ?>
                <?php echo $count_cell(array_sum(array_column($servers, 'dw_dns_zones')), 'list-zones.php?server=all'); ?>
                <?php echo $count_cell(array_sum(array_column($servers, 'dw_dns_records')), ''); ?>
                <td></td>
                <td></td>
                <td></td>
            </tr><?php

        }

        foreach ($servers as $server) {

            $status = $build_status($server->build_start_time, $server->build_end_time, $server->build_status,
                $server->has_ever_been_built, false); ?>

            <tr>
                <td></td>
                <td><?php echo $server->name; ?></td>
                <?php echo $count_cell($server->dw_accounts, 'list-accounts.php?server=' . (int) $server->id); ?>
                <?php echo $count_cell($server->dw_dns_zones, 'list-zones.php?server=' . (int) $server->id); ?>
                <?php echo $count_cell($server->dw_dns_records, ''); ?>
                <td class="text-end"><?php echo $layout->ltr($time->toUserTimezone($server->build_start_time, 'Y-m-d H:i', '-')); ?></td>
                <td class="text-end"><?php echo $layout->ltr($build_time($server->build_time)); ?></td>
                <td><?php
                    if ($status !== '') {
                        $label = htmlspecialchars($status_labels[$status][0], ENT_QUOTES, 'UTF-8');
                        echo $layout->statusBadge($label, $status_labels[$status][0], $status_labels[$status][1]);
                    } ?></td>
            </tr><?php

        } ?>

        </tbody>
    </table><?php

}
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
