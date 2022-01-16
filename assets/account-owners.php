<?php
/**
 * /assets/account-owners.php
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
require_once DIR_INC . '/settings/assets-owners.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$export_data = (int) $_GET['export_data'];

$result = $pdo->query("
    SELECT id, `name`, notes, creation_type_id, created_by, insert_time, update_time
    FROM owners
    ORDER BY `name` ASC")->fetchAll();

if ($export_data === 1) {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('account_owner_list'), strtotime($time->stamp()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        _('Status'),
        _('Owner'),
        _('Registrar Accounts'),
        _('Domains'),
        _('SSL Provider Accounts'),
        _('SSL Certs'),
        _('Default Domain Owner') . '?',
        _('Default SSL Owner') . '?',
        _('Notes'),
        _('Creation Type'),
        _('Created By'),
        _('Inserted'),
        _('Updated')
    );
    $export->writeRow($export_file, $row_contents);

    if ($result) {

        foreach ($result as $row) {

            $total_registrar_accounts = $pdo->query("
                SELECT count(*)
                FROM registrar_accounts
                WHERE owner_id = '" . $row->id . "'")->fetchColumn();

            $total_domains = $pdo->query("
                SELECT count(*)
                FROM domains
                WHERE active NOT IN ('0', '10')
                  AND owner_id = '" . $row->id . "'")->fetchColumn();

            $total_ssl_provider_accounts = $pdo->query("
                SELECT count(*)
                FROM ssl_accounts
                WHERE owner_id = '" . $row->id . "'")->fetchColumn();

            $total_certs = $pdo->query("
                SELECT count(*)
                FROM ssl_certs
                WHERE active NOT IN ('0')
                  AND owner_id = '" . $row->id . "'")->fetchColumn();

            if ($row->id == $_SESSION['s_default_owner_domains']) {

                $is_default_domains = '1';

            } else {

                $is_default_domains = '0';

            }

            if ($row->id == $_SESSION['s_default_owner_ssl']) {

                $is_default_ssl = '1';

            } else {

                $is_default_ssl = '0';

            }

            if ($total_domains >= 1 || $total_certs >= 1) {

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
                $row->name,
                $total_registrar_accounts,
                $total_domains,
                $total_ssl_provider_accounts,
                $total_certs,
                $is_default_domains,
                $is_default_ssl,
                $row->notes,
                $creation_type,
                $created_by,
                $time->toUserTimezone($row->insert_time),
                $time->toUserTimezone($row->update_time)
            );
            $export->writeRow($export_file, $row_contents);

            $current_oid = $row->id;

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
<?php echo sprintf(_('Below is a list of all the %s that have been added to %s.'), _('Account Owners'), SOFTWARE_TITLE); ?><BR>
<BR>
<a href="add/account-owner.php"><?php echo $layout->showButton('button', _('Add Owner')); ?></a>
<a href="account-owners.php?export_data=1"><?php echo $layout->showButton('button', _('Export')); ?></a><BR><BR><?php

if ($result) { ?>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th><?php echo _('Owners'); ?></th>
            <th><?php echo _('Registrar Accounts'); ?></th>
            <th><?php echo _('Domains'); ?></th>
            <th><?php echo _('SSL Accounts'); ?></th>
            <th><?php echo _('SSL Certs'); ?></th>
        </tr>
        </thead>

        <tbody><?php

        foreach ($result as $row) {

            $total_domain_accounts = $pdo->query("
                SELECT count(*)
                FROM registrar_accounts
                WHERE owner_id = '" . $row->id . "'")->fetchColumn();

            $total_domains = $pdo->query("
                SELECT count(*)
                FROM domains
                WHERE active NOT IN ('0', '10')
                  AND owner_id = '" . $row->id . "'")->fetchColumn();

            $total_ssl_accounts = $pdo->query("
                SELECT count(*)
                FROM ssl_accounts
                WHERE owner_id = '" . $row->id . "'")->fetchColumn();

            $total_certs = $pdo->query("
                SELECT count(*)
                FROM ssl_certs
                WHERE active NOT IN ('0')
                  AND owner_id = '" . $row->id . "'")->fetchColumn();

            if (($total_domains >= 1 || $total_certs >= 1) || $_SESSION['s_display_inactive_assets'] == '1') { ?>

                <tr>
                <td></td>
                <td>
                    <a href="edit/account-owner.php?oid=<?php echo $row->id; ?>"><?php echo $row->name; ?></a><?php if ($_SESSION['s_default_owner_domains'] == $row->id) echo '<strong>*</strong>'; ?><?php if ($_SESSION['s_default_owner_ssl'] == $row->id) echo '<strong>^</strong>'; ?>
                </td>
                <td><?php

                    if ($total_domain_accounts >= 1) { ?>

                        <a href="registrar-accounts.php?oid=<?php echo $row->id; ?>"><?php echo number_format($total_domain_accounts); ?></a><?php

                    } else {

                        echo "-";

                    } ?>

                </td>
                <td><?php

                    if ($total_domains >= 1) { ?>

                        <a href="../domains/index.php?oid=<?php echo $row->id; ?>"><?php echo number_format($total_domains); ?></a><?php

                    } else {

                        echo "-";

                    } ?>

                </td>
                <td><?php

                    if ($total_ssl_accounts >= 1) { ?>

                        <a href="ssl-accounts.php?oid=<?php echo $row->id; ?>"><?php echo number_format($total_ssl_accounts); ?></a><?php

                    } else {

                        echo "-";

                    } ?>

                </td>
                <td><?php

                    if ($total_certs >= 1) { ?>

                        <a href="../ssl/index.php?oid=<?php echo $row->id; ?>"><?php echo number_format($total_certs); ?></a><?php

                    } else {

                        echo "-";

                    } ?>

                </td>
                </tr><?php

            }

        } ?>

        </tbody>
    </table>

    <strong>*</strong> = <?php echo _('Default Domain Owner'); ?> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>^</strong> = <?php echo _('Default SSL Owner'); ?> (<a href="../settings/defaults/"><?php echo strtolower(_('Set Defaults')); ?></a>)<BR><BR><?php

} else { ?>

    <BR><?php echo _("You don't currently have any Owners."); ?> <a href="add/account-owner.php"><?php echo _('Click here to add one'); ?></a>.<?php

} ?>
<?php require_once DIR_INC . '/layout/asset-footer.inc.php'; ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
</body>
</html>
