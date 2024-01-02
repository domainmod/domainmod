<?php
/**
 * /assets/ssl-accounts.php
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
require_once DIR_INC . '/settings/assets-ssl-accounts.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$sslpid = (int) ($_GET['sslpid'] ?? 0);
$sslpaid = (int) ($_GET['sslpaid'] ?? 0);
$oid = (int) ($_GET['oid'] ?? 0);
$export_data = (int) ($_GET['export_data'] ?? 0);

if ($sslpid !== 0) { $sslpid_string = ' AND sa.ssl_provider_id = ' . $sslpid . ' '; } else { $sslpid_string = ''; }
if ($sslpaid !== 0) { $sslpaid_string = ' AND sa.id = ' . $sslpaid . ' '; } else { $sslpaid_string = ''; }
if ($oid !== 0) { $oid_string = ' AND sa.owner_id = ' . $oid . ' '; } else { $oid_string = ''; }

$result = $pdo->query("
    SELECT sa.id AS sslpaid, sa.email_address, sa.username, sa.password, sa.owner_id, sa.ssl_provider_id, sa.reseller, sa.reseller_id, o.id AS oid,
        o.name AS oname, sslp.id AS sslpid, sslp.name AS sslpname, sa.notes, sa.creation_type_id, sa.created_by, sa.insert_time, sa.update_time
    FROM ssl_accounts AS sa, owners AS o, ssl_providers AS sslp
    WHERE sa.owner_id = o.id
      AND sa.ssl_provider_id = sslp.id" .
      $sslpid_string .
      $sslpaid_string .
      $oid_string . "
    GROUP BY sa.username, oname, sslpname
    ORDER BY sslpname, username, oname")->fetchAll();

if ($export_data === 1) {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('ssl_provider_account_list'), strtotime($time->stamp()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        _('Status'),
        _('SSL Provider'),
        _('Email Address'),
        _('Username'),
        _('Password'),
        _('Reseller Account') . '?',
        _('Reseller ID'),
        _('Owner'),
        _('SSL Certs'),
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

            $total_certs = $pdo->query("
                SELECT count(*)
                FROM ssl_certs
                WHERE account_id = '" . $row->sslpaid . "'
                  AND active NOT IN ('0')")->fetchColumn();

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
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm select2-red<?php echo $layout->bodyDarkMode(); ?>">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php echo sprintf(_('Below is a list of all the %s that have been added to %s.'), _('SSL Provider Accounts'), SOFTWARE_TITLE); ?><BR>
<BR>
<a href="add/ssl-provider-account.php"><?php echo $layout->showButton('button', _('Add SSL Account')); ?></a>
<a href="ssl-accounts.php?export_data=1&sslpid=<?php echo $sslpid; ?>&sslpaid=<?php echo $sslpaid; ?>&oid=<?php echo $oid; ?>"><?php echo $layout->showButton('button', _('Export')); ?></a><BR><BR><?php

if ($result) { ?>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th><?php echo _('Provider'); ?></th>
            <th><?php echo _('Account'); ?></th>
            <th><?php echo _('Owner'); ?></th>
            <th><?php echo _('SSL Certs'); ?></th>
        </tr>
        </thead>

        <tbody><?php

        foreach ($result as $row) {

            $total_certs = $pdo->query("
                SELECT count(*)
                FROM ssl_certs
                WHERE account_id = '" . $row->sslpaid . "'
                  AND active NOT IN ('0')")->fetchColumn();

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

    <strong>*</strong> = <?php echo _('Default'); ?> (<a href="../settings/defaults/"><?php echo strtolower(_('Set Defaults')); ?></a>)&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<strong>^</strong> = <?php echo _('Reseller'); ?><BR><BR><?php

} else {

    $result = $pdo->query("
        SELECT id
        FROM ssl_providers
        LIMIT 1")->fetchAll();

    if (!$result) { ?>

        <BR><?php echo _('Before adding an SSL Provider Account you must add at least one SSL Provider.'); ?> <a href="add/ssl-provider.php"><?php echo _('Click here to add an SSL Provider'); ?></a>.<BR><?php

    } else { ?>

        <BR><?php echo _("You don't currently have any SSL Provider Accounts."); ?> <a href="add/ssl-provider-account.php"><?php echo _('Click here to add one'); ?></a>.<BR><?php

    }

}
?>
<?php require_once DIR_INC . '/layout/asset-footer.inc.php'; ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
</body>
</html>
