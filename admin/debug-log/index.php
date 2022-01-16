<?php
/**
 * /admin/debug-log/index.php
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
$log = new DomainMOD\Log('/admin/debug-log/index.php');
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/admin-debug-log-main.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin']);
$pdo = $deeb->cnxx;

$export_data = (int) $_GET['export_data'];

$result = $pdo->query("
    SELECT id, user_id, area, `level`, message, extra, url, insert_time
    FROM log
    ORDER BY insert_time DESC, id DESC")->fetchAll();

if ($export_data === 1) {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('debug_log'), strtotime($time->stamp()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        _('ID'),
        _('User ID'),
        _('Area'),
        _('Level'),
        _('Message'),
        _('Extra'),
        _('URL'),
        _('Inserted')
    );
    $export->writeRow($export_file, $row_contents);

    if (!$result) {

        $log_message = 'Unable to retrieve debugging data';
        $log->critical($log_message);

    } else {

        foreach ($result as $row) {

            $row_contents = array(
                $row->id,
                $row->user_id,
                $row->area,
                $row->level,
                $row->message,
                $row->extra,
                $row->url,
                $row->insert_time
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
<?php
require_once DIR_INC . '/layout/header.inc.php';

if (!$result) {

    echo _('The Debug Log is empty');

    if (DEBUG_MODE != '1') { ?>

        <BR><BR><?php echo sprintf(_('Debugging can be enabled in %sSettings%s.'), '<a href="' . WEB_ROOT . '/admin/settings/">', '</a>'); ?><?php

    }

} else { ?>

    <a href="../maintenance/clear-log.php"><?php echo $layout->showButton('button', _('Clear Debug Log')); ?></a>
    <a href="index.php?export_data=1"><?php echo $layout->showButton('button', _('Export')); ?></a><BR><BR>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th class="all"><?php echo _('ID'); ?></th>
            <th class="none"><?php echo _('User ID'); ?></th>
            <th class="all"><?php echo _('Area'); ?></th>
            <th class="all"><?php echo _('Level'); ?></th>
            <th class="all"><?php echo _('Message'); ?></th>
            <th><?php echo _('Extra'); ?></th>
            <th class="none"><?php echo _('URL'); ?></th>
            <th><?php echo _('Insert Time'); ?></th>
        </tr>
        </thead>
        <tbody><?php

        foreach ($result as $row) { ?>

            <tr>
            <td></td>
            <td>
                <?php echo $row->id; ?>
            </td>
            <td>
                <?php echo $row->user_id; ?>
            </td>
            <td>
                <?php echo $row->area; ?>
            </td>
            <td>
                <?php echo $row->level; ?>
            </td>
            <td>
                <?php echo $row->message; ?>
            </td>
            <td>
                <?php echo $row->extra; ?>
            </td>
            <td>
                <?php echo $row->url; ?>
            </td>
            <td>
                <?php echo $time->toUserTimezone($row->insert_time); ?>
            </td>
            </tr><?php

        } ?>

        </tbody>
    </table><?php

} ?>
<BR>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
