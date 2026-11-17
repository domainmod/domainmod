<?php
/**
 * /admin/dw/list-accounts.php
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
require_once DIR_INC . '/settings/dw-list-accounts.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'] ?? 0);

// The Data Warehouse tables only exist once it has been built.
$dw_check = new DomainMOD\DwAccounts();
if (!$dw_check->checkForAccountTable()) {

    header("Location: dw.php");
    exit;

}
$pdo = $deeb->cnxx;

// The Data Warehouse page's Servers table links here with ?server=<id> (or all) to choose what to view, then the page
// comes back without it, so Export and the domain links keep working from the session as before
if (isset($_GET['server'])) {

    $dwdisplay = new DomainMOD\DwDisplay();
    $dwdisplay->chooseServer($_GET['server']);
    header("Location: list-accounts.php");
    exit;

}

$domain = $_GET['domain'] ?? '';
$export_data = (int) ($_GET['export_data'] ?? 0);

// The server chosen on the Data Warehouse page (none yet if this page was opened directly).
$dw_server_id = (int) ($_SESSION['s_dw_server_id'] ?? 0);

if (($_SESSION['s_dw_view_all'] ?? '') == "1") {

    $where_clause = " ";
    $order_clause = " ORDER BY a.unix_startdate DESC, s.name ASC, a.domain ASC ";

} else {

    $where_clause = " AND a.server_id = '" . $dw_server_id . "' ";
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
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php';

if (!$result) {

    // Why the list is empty decides what shows: nothing chosen yet, a domain with no match, or none at all
    $dw_view_all = (($_SESSION['s_dw_view_all'] ?? '') == '1');

    if (!$dw_view_all && $dw_server_id === 0) {

        echo $layout->emptyState('bi-database', _('Choose what to view'), _("Choose a server's accounts or DNS zones on the Data Warehouse page."), '', '', '/admin/dw/dw.php', _('Data Warehouse'));

    } elseif ($domain != '' && !$dw_view_all) {

        echo $layout->emptyState('bi-search', _('No accounts for this domain on this server'), _('It may be on another server.'), '', '', '/admin/dw/dw.php', _('View all servers'));

    } elseif ($domain != '') {

        echo $layout->emptyState('bi-search', _('No accounts for this domain'), _('No accounts for this domain were found in the Data Warehouse.'), '', '', '/admin/dw/list-accounts.php', _('Show all accounts'));

    } elseif (!$dw_view_all) {

        echo $layout->emptyState('bi-person-badge', _('No accounts on this server'), _('Rebuild the Data Warehouse to import them.'), '', '', '/admin/dw/dw.php', _('Data Warehouse'));

    } else {

        echo $layout->emptyState('bi-person-badge', _('No accounts in the Data Warehouse'), _('Rebuild the Data Warehouse to import them.'), '', '', '/admin/dw/dw.php', _('Data Warehouse'));

    }

} else {

    // The page's actions, drawn in the page header (see Layout::renderPageActions())
    $layout->addPageAction(_('Export'), 'list-accounts.php?export_data=1', 'bi-download');

    // Each account's figures in their own columns, with the rest in Responsive's details row (DwDisplay::accountColumns()) ?>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th><span class="visually-hidden"><?php echo _('Details'); ?></span></th>
            <?php echo implode('', DomainMOD\DwDisplay::accountHeaderCells()); ?>
        </tr>
        </thead>
        <tbody><?php

            foreach ($result as $row) { ?>

                <tr>
                    <td></td>
                    <?php echo implode('', DomainMOD\DwDisplay::accountCells($row)); ?>
                </tr><?php

            } ?>

        </tbody>
    </table><?php

} ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
