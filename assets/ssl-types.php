<?php
/**
 * /assets/ssl-types.php
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
<?php //@formatter:off
require_once('../_includes/start-session.inc.php');
require_once('../_includes/init.inc.php');

require_once(DIR_ROOT . 'classes/Autoloader.php');
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();

require_once(DIR_INC . 'head.inc.php');
require_once(DIR_INC . 'config.inc.php');
require_once(DIR_INC . 'software.inc.php');
require_once(DIR_INC . 'settings/assets-ssl-types.inc.php');
require_once(DIR_INC . 'database.inc.php');

$system->authCheck($web_root);

$export_data = $_GET['export_data'];

$sql = "SELECT id, type, notes, creation_type_id, created_by, insert_time, update_time
        FROM ssl_cert_types
        ORDER BY type ASC";

if ($export_data == '1') {

    $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

    $export = new DomainMOD\Export();
    $export_file = $export->openFile('ssl_certificate_type_list', strtotime($time->stamp()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        'Status',
        'SSL Type',
        'SSL Certs',
        'Default SSL Type?',
        'Notes',
        'Creation Type',
        'Created By',
        'Inserted',
        'Updated'
    );
    $export->writeRow($export_file, $row_contents);

    if (mysqli_num_rows($result) > 0) {

        while ($row = mysqli_fetch_object($result)) {

            $sql_total_count = "SELECT count(*) AS total_count
                                FROM ssl_certs
                                WHERE type_id = '$row->id'
                                  AND active NOT IN ('0')";
            $result_total_count = mysqli_query($dbcon, $sql_total_count);

            while ($row_total_count = mysqli_fetch_object($result_total_count)) {
                $total_certs = $row_total_count->total_count;
            }

            if ($row->id == $_SESSION['s_default_ssl_type']) {

                $is_default = '1';

            } else {

                $is_default = '0';

            }

            if ($total_certs >= 1) {

                $status = 'Active';

            } else {

                $status = 'Inactive';

            }

            $creation_type = $system->getCreationType($dbcon, $row->creation_type_id);

            if ($row->created_by == '0') {
                $created_by = 'Unknown';
            } else {
                $user = new DomainMOD\User();
                $created_by = $user->getFullName($dbcon, $row->created_by);
            }

            $row_contents = array(
                $status,
                $row->type,
                number_format($total_certs),
                $is_default,
                $row->notes,
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
<?php require_once(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
    <?php require_once(DIR_INC . 'layout/head-tags.inc.php'); ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php require_once(DIR_INC . 'layout/header.inc.php'); ?>
Below is a list of all the SSL Certificates Types that are stored in <?php echo $software_title; ?>.<BR><BR>
<a href="add/ssl-type.php"><?php echo $layout->showButton('button', 'Add SSL Type'); ?></a>&nbsp;&nbsp;&nbsp;
<a href="ssl-types.php?export_data=1"><?php echo $layout->showButton('button', 'Export'); ?></a><BR><BR><?php

$result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');

if (mysqli_num_rows($result) > 0) { ?>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th>Type</th>
            <th>SSL Certs</th>
        </tr>
        </thead>
        <tbody><?php

        while ($row = mysqli_fetch_object($result)) {

            $sql_total_count = "SELECT count(*) AS total_count
                                FROM ssl_certs
                                WHERE type_id = '" . $row->id . "'
                                  AND active NOT IN ('0')";
            $result_total_count = mysqli_query($dbcon, $sql_total_count);

            while ($row_total_count = mysqli_fetch_object($result_total_count)) {
                $total_certs = $row_total_count->total_count;
            }

            if ($total_certs >= 1 || $_SESSION['s_display_inactive_assets'] == '1') { ?>

                <tr>
                <td></td>
                <td>
                    <a href="edit/ssl-type.php?ssltid=<?php echo $row->id; ?>"><?php echo $row->type; ?></a><?php if ($_SESSION['s_default_ssl_type'] == $row->id) echo '<strong>*</strong>'; ?>
                </td>
                <td><?php

                    if ($total_certs >= 1) { ?>

                        <a href="../ssl/index.php?ssltid=<?php echo $row->id; ?>"><?php echo number_format($total_certs); ?></a><?php

                    } else {

                        echo '-';

                    } ?>

                </td>
                </tr><?php

            }

        } ?>

        </tbody>
    </table>

    <strong>*</strong> = Default (<a href="../settings/defaults/">set defaults</a>)<BR><BR><?php

} else { ?>

    <BR>You don't currently have any SSL Types. <a href="add/ssl-type.php">Click here to add one</a>.<?php

} ?>
<?php require_once(DIR_INC . 'layout/asset-footer.inc.php'); ?>
<?php require_once(DIR_INC . 'layout/footer.inc.php'); //@formatter:on ?>
</body>
</html>
