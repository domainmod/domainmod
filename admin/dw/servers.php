<?php
/**
 * /admin/dw/servers.php
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
require_once DIR_INC . '/settings/dw-servers.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'] ?? 0);
$pdo = $deeb->cnxx;

$export_data = (int) ($_GET['export_data'] ?? 0);

$result = $pdo->query("
    SELECT id, `name`, `host`, protocol, `port`, username, api_token, `hash`, notes, dw_accounts, dw_dns_zones,
        dw_dns_records, build_end_time, creation_type_id, created_by, insert_time, update_time
    FROM dw_servers
    ORDER BY `name`, `host`")->fetchAll();

if ($export_data === 1) {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('dw_servers'), strtotime($time->stamp()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        _('Name'),
        _('Host'),
        _('Protocol'),
        _('Port'),
        _('Username'),
        _('API Token'),
        _('Hash'),
        _('Notes'),
        _('DW Accounts'),
        _('DW DNS Zones'),
        _('DW DNS Records'),
        _('DW Last Built'),
        _('Creation Type'),
        _('Created By'),
        _('Inserted'),
        _('Updated')
    );
    $export->writeRow($export_file, $row_contents);

    if ($result) {

        foreach ($result as $row) {

            $creation_type = $system->getCreationType($row->creation_type_id);

            if ($row->created_by == '0') {
                $created_by = _('Unknown');
            } else {
                $user = new DomainMOD\User();
                $created_by = $user->getFullName($row->created_by);
            }

            $row_contents = array(
                $row->name,
                $row->host,
                $row->protocol,
                $row->port,
                $row->username,
                $row->api_token,
                $row->hash,
                $row->notes,
                $row->dw_accounts,
                $row->dw_dns_zones,
                $row->dw_dns_records,
                $time->toUserTimezone($row->build_end_time),
                $creation_type,
                $created_by,
                $time->toUserTimezone($row->insert_time),
                $time->toUserTimezone($row->update_time)
            );
            $export->writeRow($export_file, $row_contents);

        }

    }

    $export->closeFile($export_file);

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
<a href="add-server.php"><?php echo $layout->showButton('button', _('Add Web Server')); ?></a>
<?php
if ($result) { ?>

    <a href="servers.php?export_data=1"><?php echo $layout->showButton('button', _('Export')); ?></a><BR><BR>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th><?php echo _('Name'); ?></th>
            <th><?php echo _('Host'); ?></th>
            <th><?php echo _('Port'); ?></th>
            <th><?php echo _('Username'); ?></th>
            <th><?php echo _('Inserted'); ?></th>
            <th><?php echo _('Updated'); ?></th>
        </tr>
        </thead>
    <tbody><?php

    foreach ($result as $row) { ?>

        <tr>
            <td></td>
            <td>
                <a href="edit-server.php?dwsid=<?php echo $row->id; ?>"><?php echo $row->name; ?></a>
            </td>
            <td>
                <?php echo $row->protocol; ?>://<?php echo $row->host; ?>
            </td>
            <td>
                <?php echo $row->port; ?>
            </td>
            <td>
                <?php echo $row->username; ?>
            </td>
            <td><?php

                if ($row->insert_time != '1970-01-01 00:00:00') {

                    $temp_time = $time->toUserTimezone($row->insert_time);

                } else {

                    $temp_time = '-';

                }

                echo $temp_time; ?>
            </td>
            <td><?php

                if ($row->update_time != '1970-01-01 00:00:00') {

                    $temp_time = $time->toUserTimezone($row->update_time);

                } else {

                    $temp_time = '-';

                }

                echo $temp_time; ?>
            </td>
        </tr><?php

    } ?>

    </tbody>
    </table><?php

}
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
