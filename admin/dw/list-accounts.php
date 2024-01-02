<?php
/**
 * /admin/dw/list-accounts.php
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
require_once DIR_INC . '/settings/dw-list-accounts.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'] ?? 0);
$pdo = $deeb->cnxx;

$domain = $_GET['domain'] ?? '';
$export_data = (int) ($_GET['export_data'] ?? 0);

if ($_SESSION['s_dw_view_all'] == "1") {

    $where_clause = " ";
    $order_clause = " ORDER BY a.unix_startdate DESC, s.name ASC, a.domain ASC ";

} else {

    $where_clause = " AND a.server_id = '" . $_SESSION['s_dw_server_id'] . "' ";
    $order_clause = " ORDER BY s.name ASC, a.unix_startdate DESC ";

}

if ($domain != "") { //@formatter:off

    $stmt = $pdo->prepare("
        SELECT a.*, s.id AS dw_server_id, s.name AS dw_server_name, s.host AS dw_server_host
        FROM dw_accounts AS a, dw_servers AS s
        WHERE a.server_id = s.id
          AND a.domain = :domain" . 
          $where_clause .
        $order_clause);
    $stmt->bindValue('domain', $domain, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetchAll();

} else {

    $result = $pdo->query("
        SELECT a.*, s.id AS dw_server_id, s.name AS dw_server_name, s.host AS dw_server_host
        FROM dw_accounts AS a, dw_servers AS s
        WHERE a.server_id = s.id" .
          $where_clause .
        $order_clause)->fetchAll();

} //@formatter:on

if ($export_data === 1) {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('dw_account_list'), strtotime($time->stamp()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        _('Number of Accounts') . ':', number_format(count($result))
    );
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    if ($domain != "") {

        $row_contents = array(
            _('Domain Filter') . ':',
            $domain
        );
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

    }

    $row_contents = array(
        _('Server Name'),
        _('Server Host'),
        _('Domain'),
        _('IP Address'),
        _('Owner'),
        _('User'),
        _('Email'),
        _('Plan'),
        _('Theme'),
        _('Shell'),
        _('Partition'),
        _('Disk Limit (MB)'),
        _('Disk Used (MB)'),
        _('Max Addons'),
        _('Max FTP'),
        _('Max Email Lists'),
        _('Max Parked Domains'),
        _('Max POP Accounts'),
        _('Max SQL Databases'),
        _('Max Subdomains'),
        _('Start Date'),
        _('Start Date (Unix)'),
        _('Suspended') . '?',
        _('Suspend Reason'),
        _('Suspend Time (Unix)'),
        _('Max Emails Per Hour'),
        _('Max Email Failure % (For Rate Limiting)'),
        _('Min Email Failure # (For Rate Limiting)'),
        _('Inserted (into DW)')
    );
    $export->writeRow($export_file, $row_contents);

    if ($result) {

        foreach ($result as $row) {

            $row_contents = array(
                $row->dw_server_name,
                $row->dw_server_host,
                $row->domain,
                $row->ip,
                $row->owner,
                $row->user,
                $row->email,
                $row->plan,
                $row->theme,
                $row->shell,
                $row->partition,
                $row->disklimit,
                $row->diskused,
                $row->maxaddons,
                $row->maxftp,
                $row->maxlst,
                $row->maxparked,
                $row->maxpop,
                $row->maxsql,
                $row->maxsub,
                $row->startdate,
                $row->unix_startdate,
                $row->suspended,
                $row->suspendreason,
                $row->suspendtime,
                $row->max_email_per_hour,
                $row->max_defer_fail_percentage,
                $row->min_defer_fail_to_trigger_protection,
                $time->toUserTimezone($row->insert_time)
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
<?php require_once DIR_INC . '/layout/header.inc.php';

if (!$result) {

    echo _('Your search returned 0 results.');

} else { ?>

    <a href="list-accounts.php?export_data=1"><?php echo $layout->showButton('button', _('Export')); ?></a><BR><BR><?php

    $dwdisplay = new DomainMOD\DwDisplay(); ?>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th><?php echo _('Account'); ?></th>
            <th><?php echo _('Data'); ?></th>
            <th></th>
            <th></th>
            <th></th>
        </tr>
        </thead>
        <tbody><?php

            foreach ($result as $row) { ?>

                <tr>
                    <td></td>
                    <td>
                        <?php echo $dwdisplay->accountSidebar($row->dw_server_name, $row->domain, '1', '1'); ?>
                    </td>

                    <?php echo $dwdisplay->account($row->server_id, $row->domain); ?>

                </tr><?php

            } ?>

        </tbody>
    </table><?php

} ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
