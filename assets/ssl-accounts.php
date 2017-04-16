<?php
/**
 * /assets/ssl-accounts.php
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
require_once(DIR_INC . 'settings/assets-ssl-accounts.inc.php');
require_once(DIR_INC . 'database.inc.php');

$system->authCheck($web_root);

$sslpid = $_GET['sslpid'];
$sslpaid = $_GET['sslpaid'];
$oid = $_GET['oid'];
$export_data = $_GET['export_data'];

if ($sslpid != '') { $sslpid_string = " AND sa.ssl_provider_id = '$sslpid' "; } else { $sslpid_string = ''; }
if ($sslpaid != '') { $sslpaid_string = " AND sa.id = '$sslpaid' "; } else { $sslpaid_string = ''; }
if ($oid != '') { $oid_string = " AND sa.owner_id = '$oid' "; } else { $oid_string = ''; }

$sql = "SELECT sa.id AS sslpaid, sa.email_address, sa.username, sa.password, sa.owner_id, sa.ssl_provider_id, sa.reseller, sa.reseller_id, o.id AS oid,
            o.name AS oname, sslp.id AS sslpid, sslp.name AS sslpname, sa.notes, sa.creation_type_id, sa.created_by, sa.insert_time, sa.update_time
        FROM ssl_accounts AS sa, owners AS o, ssl_providers AS sslp
        WHERE sa.owner_id = o.id
          AND sa.ssl_provider_id = sslp.id
          $sslpid_string
          $sslpaid_string
          $oid_string
        GROUP BY sa.username, oname, sslpname
        ORDER BY sslpname, username, oname";

if ($export_data == '1') {

    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $export = new DomainMOD\Export();
    $export_file = $export->openFile('ssl_provider_account_list', strtotime($time->stamp()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        'Status',
        'SSL Provider',
        'Email Address',
        'Username',
        'Password',
        'Reseller Account?',
        'Reseller ID',
        'Owner',
        'SSL Certs',
        'Default Account?',
        'Notes',
        'Creation Type',
        'Created By',
        'Inserted',
        'Updated'
    );
    $export->writeRow($export_file, $row_contents);

    if (mysqli_num_rows($result) > 0) {

        while ($row = mysqli_fetch_object($result)) {

            $sql_total_count = "SELECT count(*) AS total_cert_count
                                FROM ssl_certs
                                WHERE account_id = '$row->sslpaid'
                                  AND active NOT IN ('0')";
            $result_total_count = mysqli_query($connection, $sql_total_count);

            while ($row_cert_count = mysqli_fetch_object($result_total_count)) {
                $total_certs = $row_cert_count->total_cert_count;
            }

            if ($row->sslpaid == $_SESSION['s_default_ssl_provider_account']) {

                $is_default = '1';

            } else {

                $is_default = '0';

            }

            if ($row->reseller == '0') {

                $is_reseller = '0';

            } else {

                $is_reseller = '1';

            }

            if ($total_certs >= 1) {

                $status = 'Active';

            } else {

                $status = 'Inactive';

            }

            $creation_type = $system->getCreationType($connection, $row->creation_type_id);

            if ($row->created_by == '0') {
                $created_by = 'Unknown';
            } else {
                $user = new DomainMOD\User();
                $created_by = $user->getFullName($connection, $row->created_by);
            }

            $row_contents = array(
                $status,
                $row->sslpname,
                $row->email_address,
                $row->username,
                $row->password,
                $is_reseller,
                $row->reseller_id,
                $row->oname,
                $total_certs,
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
Below is a list of all the SSL Provider Accounts that are stored in <?php echo $software_title; ?>.<BR><BR>
<a href="add/ssl-provider-account.php"><?php echo $layout->showButton('button', 'Add SSL Account'); ?></a>&nbsp;&nbsp;&nbsp;
<a href="ssl-accounts.php?export_data=1&sslpid=<?php echo urlencode($sslpid); ?>&sslpaid=<?php echo urlencode($sslpaid); ?>&oid=<?php echo urlencode($oid); ?>"><?php echo $layout->showButton('button', 'Export'); ?></a><BR><BR><?php

$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

if (mysqli_num_rows($result) > 0) { ?>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th>Provider</th>
            <th>Account</th>
            <th>Owner</th>
            <th>SSL Certs</th>
        </tr>
        </thead>

        <tbody><?php

        while ($row = mysqli_fetch_object($result)) {

            $sql_total_count = "SELECT count(*) AS total_cert_count
                                FROM ssl_certs
                                WHERE account_id = '$row->sslpaid'
                                  AND active NOT IN ('0')";
            $result_total_count = mysqli_query($connection, $sql_total_count);

            while ($row_total_count = mysqli_fetch_object($result_total_count)) {
                $total_certs = $row_total_count->total_cert_count;
            }

            if ($total_certs >= 1 || $_SESSION['s_display_inactive_assets'] == '1') { ?>

                <tr>
                <td></td>
                <td>
                    <a href="edit/ssl-provider.php?sslpid=<?php echo $row->sslpid; ?>"><?php echo $row->sslpname; ?></a>
                </td>
                <td>
                    <a href="edit/ssl-provider-account.php?sslpaid=<?php echo $row->sslpaid; ?>"><?php echo $row->username; ?></a><?php
                    if ($_SESSION['s_default_ssl_provider_account'] == $row->sslpaid) echo '<strong>*</strong>'; ?><?php
                    if ($row->reseller == '1') echo '<strong>^</strong>'; ?>
                </td>
                <td>
                    <a href="edit/account-owner.php?oid=<?php echo $row->oid; ?>"><?php echo $row->oname; ?></a>
                </td>
                <td><?php

                    if ($total_certs >= 1) { ?>

                        <a href="../ssl/index.php?oid=<?php echo $row->oid; ?>&sslpid=<?php echo $row->sslpid; ?>&sslpaid=<?php echo $row->sslpaid; ?>"><?php echo $total_certs; ?></a><?php

                    } else {

                        echo '-';

                    } ?>

                </td>
                </tr><?php

            }

        } ?>

        </tbody>
    </table>

    <strong>*</strong> = Default (<a href="../settings/defaults/">set defaults</a>)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>^</strong> = Reseller<BR><BR><?php

} else {

    $sql = "SELECT id
            FROM ssl_providers
            LIMIT 1";
    $result = mysqli_query($connection, $sql);

    if (mysqli_num_rows($result) == 0) { ?>

        <BR>Before adding an SSL Provider Account you must add at least one SSL Provider. <a href="add/ssl-provider.php">Click here to add an SSL Provider</a>.<BR><?php

    } else { ?>

        <BR>You don't currently have any SSL Provider Accounts. <a href="add/ssl-provider-account.php">Click here to add one</a>.<BR><?php

    }

}
?>
<?php require_once(DIR_INC . 'layout/asset-footer.inc.php'); ?>
<?php require_once(DIR_INC . 'layout/footer.inc.php'); //@formatter:on ?>
</body>
</html>
