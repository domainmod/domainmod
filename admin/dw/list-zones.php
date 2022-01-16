<?php
/**
 * /admin/dw/list-zones.php
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
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/dw-list-zones.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin']);
$pdo = $deeb->cnxx;

$domain = $_GET['domain'];
$export_data = (int) $_GET['export_data'];

if ($_SESSION['s_dw_view_all'] == "1") {

    $where_clause = "";
    $where_clause_no_join = "";

} else {

    $where_clause = " AND z.server_id = '" . $_SESSION['s_dw_server_id'] . "' ";
    $where_clause_no_join = " AND server_id = '" . $_SESSION['s_dw_server_id'] . "' ";
    $where_clause_no_join_first_line = " WHERE server_id = '" . $_SESSION['s_dw_server_id'] . "' ";

}

if ($domain != "") {

    $stmt = $pdo->prepare("
        SELECT z.*, s.id AS dw_server_id, s.name AS dw_server_name, s.host AS dw_server_host
        FROM dw_dns_zones AS z, dw_servers AS s
        WHERE z.server_id = s.id
          AND z.domain = :domain" .
          $where_clause . "
        ORDER BY s.name, z.zonefile, z.domain");
    $stmt->bindValue('domain', $domain, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetchAll();

} else {

    $result = $pdo->query("
        SELECT z.*, s.id AS dw_server_id, s.name AS dw_server_name, s.host AS dw_server_host
        FROM dw_dns_zones AS z, dw_servers AS s
        WHERE z.server_id = s.id" .
          $where_clause . "
        ORDER BY s.name, z.zonefile, z.domain")->fetchAll();

}

if ($export_data === 1) {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('dw_dns_zones'), strtotime($time->stamp()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    if ($domain != "") {

        $stmt = $pdo->prepare("
            SELECT count(*)
            FROM dw_dns_records
            WHERE domain = :domain" .
            $where_clause_no_join);
        $stmt->bindValue('domain', $domain, PDO::PARAM_STR);
        $stmt->execute();
        $total_records_temp = $stmt->fetchColumn();

    } else {

        $total_records_temp = $pdo->query("
            SELECT count(*)
            FROM dw_dns_records" .
            $where_clause_no_join_first_line)->fetchColumn();

    }

    $row_contents = array(
        _('Number of DNS Zones') . ':',
        number_format(count($result))
    );
    $export->writeRow($export_file, $row_contents);

    $row_contents = array(
        _('Number of DNS Records') . ':',
        number_format($total_records_temp)
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
        _('Host'),
        _('Domain'),
        _('DNS Zone File'),
        _('Original/Primary Zone Source'),
        _('Zone Admin Email'),
        _('Serial'),
        _('Zone Refresh TTL'),
        _('Retry Interval'),
        _('Zone Expiration'),
        _('Minimum Record TTL'),
        _('Authoritative Name Server'),
        _('DNS Record'),
        _('Record TTL'),
        _('Record Class'),
        _('Record Type'),
        _('IP Address'),
        _('Canonical Name'),
        _('Mail Server'),
        _('Mail Server Priority'),
        _('Text Record Data'),
        _('Zone Line #'),
        _('Number of Lines'),
        _('Raw Line Output'),
        _('Inserted (into DW)')
    );
    $export->writeRow($export_file, $row_contents);

    if ($result) {

        foreach ($result as $row_dw_dns_zone_temp) {

            $result_get_records = $pdo->query("
                SELECT *
                FROM dw_dns_records
                WHERE server_id = '" . $row_dw_dns_zone_temp->dw_server_id . "'
                  AND domain = '" . $row_dw_dns_zone_temp->domain . "'
                ORDER BY new_order ASC")->fetchAll();

            foreach ($result_get_records as $row_get_records) {

                $row_contents = array(
                    $row_dw_dns_zone_temp->dw_server_name,
                    $row_dw_dns_zone_temp->dw_server_host,
                    $row_get_records->domain,
                    $row_get_records->zonefile,
                    $row_get_records->mname,
                    $row_get_records->rname,
                    $row_get_records->serial,
                    $row_get_records->refresh,
                    $row_get_records->retry,
                    $row_get_records->expire,
                    $row_get_records->minimum,
                    $row_get_records->nsdname,
                    $row_get_records->name,
                    $row_get_records->ttl,
                    $row_get_records->class,
                    $row_get_records->type,
                    $row_get_records->address,
                    $row_get_records->cname,
                    $row_get_records->exchange,
                    $row_get_records->preference,
                    $row_get_records->txtdata,
                    $row_get_records->line,
                    $row_get_records->nlines,
                    $row_get_records->raw,
                    $time->toUserTimezone($row_get_records->insert_time)
                );
                $export->writeRow($export_file, $row_contents);

            }

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
<?php
if (!$result) {

    echo _('Your search returned 0 results.');

} else { ?>

    <a href="list-zones.php?export_data=1"><?php echo $layout->showButton('button', _('Export')); ?></a><BR><BR><?php

    $dwdisplay = new DomainMOD\DwDisplay(); ?>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th><?php echo _('Zone'); ?></th>
            <th><?php echo _('Data'); ?></th>
        </tr>
        </thead>
        <tbody><?php

        foreach ($result as $row) { ?>

            <tr>
                <td></td>
                <td>
                    <?php echo $dwdisplay->zoneSidebar($row->server_id, $row->domain, '1', '1'); ?>
                </td>
                <td>
                    <?php echo $dwdisplay->zone($row->server_id, $row->domain); ?>
                </td>
            </tr><?php

        } ?>

        </tbody>
    </table><?php

} ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
