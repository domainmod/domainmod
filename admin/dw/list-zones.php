<?php
/**
 * /admin/dw/list-zones.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>
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
include("../../_includes/start-session.inc.php");
include("../../_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "settings/dw-list-zones.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck($web_root);
$system->checkAdminUser($_SESSION['s_is_admin'], $web_root);

$domain = $_GET['domain'];
$export_data = $_GET['export_data'];

// Search Navigation Variables
$numBegin = $_REQUEST['numBegin'];
$begin = $_REQUEST['begin'];
$num = $_REQUEST['num'];

if ($_SESSION['s_dw_view_all'] == "1") {

    $where_clause = "";
    $where_clause_no_join = "";

} else {

    $where_clause = " AND z.server_id = '" . $_SESSION['s_dw_server_id'] . "' ";
    $where_clause_no_join = " AND server_id = '" . $_SESSION['s_dw_server_id'] . "' ";
    $where_clause_no_join_first_line = " WHERE server_id = '" . $_SESSION['s_dw_server_id'] . "' ";

}

if ($domain != "") {

    $sql = "SELECT z.*, s.id AS dw_server_id, s.name AS dw_server_name, s.host AS dw_server_host
            FROM dw_dns_zones AS z, dw_servers AS s
            WHERE z.server_id = s.id
              AND z.domain = '" . mysqli_real_escape_string($connection, $domain) . "'" .
              $where_clause . "
            ORDER BY s.name, z.zonefile, z.domain";

} else {

    $sql = "SELECT z.*, s.id AS dw_server_id, s.name AS dw_server_name, s.host AS dw_server_host
            FROM dw_dns_zones AS z, dw_servers AS s
            WHERE z.server_id = s.id" .
              $where_clause . "
            ORDER BY s.name, z.zonefile, z.domain";

}

if ($export_data == "1") {

    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $export = new DomainMOD\Export();
    $export_file = $export->openFile('dw_dns_zones', strtotime($time->stamp()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    if ($domain != "") {

        $sql_total_records = "SELECT count(*) AS total_dns_record_count
                              FROM dw_dns_records
                              WHERE domain = '" . mysqli_real_escape_string($connection, $domain) . "'" .
                                $where_clause_no_join;

    } else {

        $sql_total_records = "SELECT count(*) AS total_dns_record_count
                              FROM dw_dns_records" .
                              $where_clause_no_join_first_line;

    }
    $result_total_records = mysqli_query($connection, $sql_total_records);

    while ($row_total_dns_record_count = mysqli_fetch_object($result_total_records)) {

        $total_records_temp = $row_total_dns_record_count->total_dns_record_count;

    }

    $row_contents = array(
        'Number of DNS Zones:',
        number_format(mysqli_num_rows($result))
    );
    $export->writeRow($export_file, $row_contents);

    $row_contents = array(
        'Number of DNS Records:',
        number_format($total_records_temp)
    );
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    if ($domain != "") {

        $row_contents = array(
            'Domain Filter:',
            $domain
        );
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

    }

    $row_contents = array(
        'Server Name',
        'Host',
        'Domain',
        'DNS Zone File',
        'Original/Primary Zone Source',
        'Zone Admin Email',
        'Serial',
        'Zone Refresh TTL',
        'Retry Interval',
        'Zone Expiration',
        'Minimum Record TTL',
        'Authoritative Name Server',
        'DNS Record',
        'Record TTL',
        'Record Class',
        'Record Type',
        'IP Address',
        'Canonical Name',
        'Mail Server',
        'Mail Server Priority',
        'Text Record Data',
        'Zone Line #',
        'Number of Lines',
        'Raw Line Output',
        'Inserted (into DW)'
    );
    $export->writeRow($export_file, $row_contents);

    if (mysqli_num_rows($result) > 0) {

        while ($row_dw_dns_zone_temp = mysqli_fetch_object($result)) {

            $sql_get_records = "SELECT *
                                FROM dw_dns_records
                                WHERE server_id = '" . $row_dw_dns_zone_temp->dw_server_id . "'
                                  AND domain = '" . $row_dw_dns_zone_temp->domain . "'
                                ORDER BY new_order ASC";
            $result_get_records = mysqli_query($connection, $sql_get_records);

            while ($row_get_records = mysqli_fetch_object($result_get_records)) {

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
<?php include(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
    <?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<?php
$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

if (mysqli_num_rows($result) == 0) {

    echo "Your search returned 0 results.";

} else { ?>

    <a href="list-zones.php?export_data=1"><?php echo $layout->showButton('button', 'Export'); ?></a><BR><BR><?php

    $dwdisplay = new DomainMOD\DwDisplay(); ?>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th>Zone</th>
            <th>Data</th>
        </tr>
        </thead>
        <tbody><?php

        while ($row = mysqli_fetch_object($result)) { ?>

            <tr>
                <td></td>
                <td>
                    <?php echo $dwdisplay->zoneSidebar($connection, $row->server_id, $row->domain, '1', '1'); ?>
                </td>
                <td>
                    <?php echo $dwdisplay->zone($connection, $row->server_id, $row->domain); ?>
                </td>
            </tr><?php

        } ?>

        </tbody>
    </table><?php

} ?>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
