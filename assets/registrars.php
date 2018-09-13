<?php
/**
 * /assets/registrars.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2018 Greg Chetcuti <greg@chetcuti.com>
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
require_once __DIR__ . '/../_includes/start-session.inc.php';
require_once __DIR__ . '/../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-registrars.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$export_data = $_GET['export_data'];

$result = $pdo->query("
    SELECT id AS rid, `name` AS rname, url, api_registrar_id, notes, creation_type_id, created_by, insert_time, update_time
    FROM registrars
    ORDER BY rname ASC")->fetchAll();

if ($export_data == '1') {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile('registrar_list', strtotime($time->stamp()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        'Status',
        'Registrar',
        'Accounts',
        'Domains',
        'Default Registrar?',
        'URL',
        'Notes',
        'API Registrar',
        'Creation Type',
        'Created By',
        'Inserted',
        'Updated'
    );
    $export->writeRow($export_file, $row_contents);

    if ($result) {

        foreach ($result as $row) {

            $total_accounts = $pdo->query("
                SELECT count(*)
                FROM registrar_accounts
                WHERE registrar_id = '" . $row->rid . "'")->fetchColumn();

            $total_domains = $pdo->query("
                SELECT count(*)
                FROM domains
                WHERE active NOT IN ('0', '10')
                  AND registrar_id = '" . $row->rid . "'")->fetchColumn();

            if ($row->rid == $_SESSION['s_default_registrar']) {

                $is_default = '1';

            } else {

                $is_default = '0';

            }

            if ($total_domains >= 1) {

                $status = 'Active';

            } else {

                $status = 'Inactive';

            }

            $creation_type = $system->getCreationType($row->creation_type_id);

            if ($row->created_by == '0') {
                $created_by = 'Unknown';
            } else {
                $user = new DomainMOD\User();
                $created_by = $user->getFullName($row->created_by);
            }

            $api = new DomainMOD\Api();
            $api_registrar_name = $api->getApiRegistrarName($row->api_registrar_id);

            if ($api_registrar_name == '') {
                $api_registrar_name = 'n/a';
            }

            $row_contents = array(
                $status,
                $row->rname,
                number_format($total_accounts),
                number_format($total_domains),
                $is_default,
                $row->url,
                $row->notes,
                $api_registrar_name,
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
<body class="hold-transition skin-red sidebar-mini">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
Below is a list of all the Domain Registrars that are stored in <?php echo SOFTWARE_TITLE; ?>.<BR><BR>
<a href="add/registrar.php"><?php echo $layout->showButton('button', 'Add Registrar'); ?></a>
<a href="registrars.php?export_data=1"><?php echo $layout->showButton('button', 'Export'); ?></a><BR><BR><?php

if ($result > 0) { ?>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th>Registrar</th>
            <th>Accounts</th>
            <th>Domains</th>
            <th>Options</th>
        </tr>
        </thead>

        <tbody><?php

        foreach ($result as $row) {

            $total_accounts = $pdo->query("
                SELECT count(*)
                FROM registrar_accounts
                WHERE registrar_id = '" . $row->rid . "'")->fetchColumn();

            $total_domains = $pdo->query("
                SELECT count(*)
                FROM domains
                WHERE active NOT IN ('0', '10')
                  AND registrar_id = '" . $row->rid . "'")->fetchColumn();

            if ($total_domains >= 1 || $_SESSION['s_display_inactive_assets'] == '1') { ?>

                <tr>
                <td></td>
                <td>
                    <a href="edit/registrar.php?rid=<?php echo $row->rid; ?>"><?php echo $row->rname; ?></a><?php if ($_SESSION['s_default_registrar'] == $row->rid) echo '<strong>*</strong>'; ?>
                </td>
                <td><?php

                    if ($total_accounts >= 1) { ?>

                        <a href="registrar-accounts.php?rid=<?php echo $row->rid; ?>"><?php echo number_format($total_accounts); ?></a><?php

                    } else {

                        echo '-';

                    } ?>

                </td>
                <td><?php

                    if ($total_domains >= 1) { ?>

                        <a href="../domains/index.php?rid=<?php echo $row->rid; ?>"><?php echo number_format($total_domains); ?></a><?php

                    } else {

                        echo '-';

                    } ?>

                </td>
                <td>
                    <a href="registrar-fees.php?rid=<?php echo $row->rid; ?>">fees</a>&nbsp;&nbsp;<a target="_blank" href="<?php echo $row->url; ?>">www</a>
                </td>
                </tr><?php

            }

        } ?>

        </tbody>
    </table>

    <strong>*</strong> = Default (<a href="../settings/defaults/">set defaults</a>)<BR><BR><?php

} else { ?>

    <BR>You don't currently have any Domain Registrars. <a href="add/registrar.php">Click here to add one</a>.<?php

} ?>
<?php require_once DIR_INC . '/layout/asset-footer.inc.php'; ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
</body>
</html>
