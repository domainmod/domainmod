<?php
/**
 * /assets/registrar-accounts.php
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
require_once DIR_INC . '/settings/assets-registrar-accounts.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$rid = (int) $_GET['rid'];
$raid = (int) $_GET['raid'];
$oid = (int) $_GET['oid'];
$export_data = (int) $_GET['export_data'];

if ($rid !== 0) { $rid_string = " AND ra.registrar_id = '" . $rid . "' "; } else { $rid_string = ''; }
if ($raid !== 0) { $raid_string = " AND ra.id = '" . $raid . "' "; } else { $raid_string = ''; }
if ($oid !== 0) { $oid_string = " AND ra.owner_id = '" . $oid . "' "; } else { $oid_string = ''; }

$result = $pdo->query("
    SELECT ra.id AS raid, ra.email_address, ra.username, ra.password, ra.reseller, ra.reseller_id, ra.api_app_name,
        ra.api_key, ra.api_secret, ra.api_ip_id, ra.owner_id, ra.registrar_id, o.id AS oid, o.name AS oname,
        r.id AS rid, r.name AS rname, ra.notes, ra.creation_type_id, ra.created_by, ra.insert_time, ra.update_time
    FROM registrar_accounts AS ra, owners AS o, registrars AS r
    WHERE ra.owner_id = o.id
      AND ra.registrar_id = r.id" .
      $rid_string .
      $raid_string .
      $oid_string . "
    GROUP BY ra.username, oname, rname
    ORDER BY rname, username, oname")->fetchAll();

if ($export_data === 1) {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('registrar_account_list'), strtotime($time->stamp()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        _('Status'),
        _('Registrar'),
        _('Email Address'),
        _('Username'),
        _('Password'),
        _('Reseller Account') . '?',
        _('Reseller ID'),
        _('API App Name'),
        _('API Key'),
        _('API Secret'),
        _('API IP (Name)'),
        _('API IP (IP)'),
        _('Owner'),
        _('Domains'),
        _('Default Account') . '?',
        _('Notes'),
        _('Creation Type'),
        _('Created By'),
        _('Inserted'),
        _('Updated')
    );
    $export->writeRow($export_file, $row_contents);

    if ($result) {

        foreach ($result as $row) {

            if ($row->api_ip_id != '0') {

                $result_temp = $pdo->query("
                    SELECT `name`, ip
                    FROM ip_addresses
                    WHERE id = '" . $row->api_ip_id . "'")->fetchAll();

                foreach ($result_temp as $row_temp) {

                    $api_ip_name = $row_temp->name;
                    $api_ip_address = $row_temp->ip;

                }

            } else {

                $api_ip_name = '';
                $api_ip_address = '';

            }

            $total_domains = $pdo->query("
                SELECT count(*)
                FROM domains
                WHERE account_id = '" . $row->raid . "'
                  AND active NOT IN ('0', '10')")->fetchColumn();

            if ($row->raid == $_SESSION['s_default_registrar_account']) {

                $is_default = '1';

            } else {

                $is_default = '0';

            }

            if ($row->reseller == '0') {

                $is_reseller = '0';

            } else {

                $is_reseller = '1';

            }

            if ($total_domains >= 1) {

                $status = _('Active');

            } else {

                $status = _('Inactive');

            }

            $creation_type = $system->getCreationType($row->creation_type_id);

            if ($row->created_by == '0') {
                $created_by = _('Unknown');
            } else {
                $user = new DomainMOD\User();
                $created_by = $user->getFullName($row->created_by);
            }

            $row_contents = array(
                $status,
                $row->rname,
                $row->email_address,
                $row->username,
                $row->password,
                $is_reseller,
                $row->reseller_id,
                $row->api_app_name,
                $row->api_key,
                $row->api_secret,
                $api_ip_name,
                $api_ip_address,
                $row->oname,
                $total_domain_count,
                $is_default,
                $row->notes,
                $creation_type,
                $created_by,
                $time->toUserTimezone($row->insert_time),
                $time->toUserTimezone($row->update_time)
            );
            $export->writeRow($export_file, $row_contents);

            $current_raid = $row->raid;

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
<?php echo sprintf(_('Below is a list of all the %s that have been added to %s.'), _('Domain Registrar Accounts'), SOFTWARE_TITLE); ?><BR>
<BR>
<a href="add/registrar-account.php"><?php echo $layout->showButton('button', _('Add Registrar Account')); ?></a>
<a href="registrar-accounts.php?export_data=1&rid=<?php echo $rid; ?>&raid=<?php echo $raid; ?>&oid=<?php echo $oid; ?>"><?php echo $layout->showButton('button', _('Export')); ?></a><BR><BR><?php

if ($result) { ?>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th><?php echo _('Registrar'); ?></th>
            <th><?php echo _('Account'); ?></th>
            <th><?php echo _('Owner'); ?></th>
            <th><?php echo _('Domains'); ?></th>
        </tr>
        </thead>

        <tbody><?php

        foreach ($result as $row) {

            $total_domains = $pdo->query("
                SELECT count(*)
                FROM domains
                WHERE account_id = '" . $row->raid . "'
                  AND active NOT IN ('0', '10')")->fetchColumn();

            if ($total_domains >= 1 || $_SESSION['s_display_inactive_assets'] == '1') { ?>

                <tr>
                <td></td>
                <td>
                    <a href="edit/registrar.php?rid=<?php echo $row->rid; ?>"><?php echo $row->rname; ?></a>
                </td>
                <td>
                    <a href="edit/registrar-account.php?raid=<?php echo $row->raid; ?>"><?php echo $row->username; ?></a><?php
                    if ($_SESSION['s_default_registrar_account'] == $row->raid) echo '<strong>*</strong>'; ?><?php
                    if ($row->reseller == '1') echo '<strong>^</strong>'; ?>
                </td>
                <td>
                    <a href="edit/account-owner.php?oid=<?php echo $row->oid; ?>"><?php echo $row->oname; ?></a>
                </td>
                <td><?php

                    if ($total_domains >= 1) { ?>

                        <a href="../domains/index.php?oid=<?php echo $row->oid; ?>&rid=<?php echo $row->rid; ?>&raid=<?php echo $row->raid; ?>"><?php echo $total_domains; ?></a><?php

                    } else {

                        echo '-';

                    } ?>

                </td>
                </tr><?php

            }

        } ?>

        </tbody>
    </table>

    <strong>*</strong> = <?php echo _('Default'); ?> (<a href="../settings/defaults/"><?php echo strtolower(_('Set Defaults')); ?></a>)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>^</strong> = <?php echo _('Reseller'); ?><BR><BR><?php

} else {

    $result = $pdo->query("
        SELECT id
        FROM registrars
        LIMIT 1")->fetchAll();

    if (!$result) { ?>

        <BR><?php echo _('Before adding a Registrar Account you must add at least one Registrar.'); ?> <a href="add/registrar.php"><?php echo _('Click here to add a Registrar'); ?></a>.<BR><?php

    } else { ?>

        <BR><?php echo _("You don't currently have any Registrar Accounts."); ?> <a href="add/registrar-account.php"><?php echo _('Click here to add one'); ?></a>.<BR><?php

    }

}
?>
<?php require_once DIR_INC . '/layout/asset-footer.inc.php'; ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
</body>
</html>
