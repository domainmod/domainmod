<?php
/**
 * /assets/dns.php
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
require_once DIR_INC . '/settings/assets-dns.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$export_data = (int) ($_GET['export_data'] ?? 0);

$result = $pdo->query("
    SELECT id, `name`, number_of_servers, dns1, dns2, dns3, dns4, dns5, dns6, dns7, dns8, dns9, dns10, ip1, ip2, ip3,
        ip4, ip5, ip6, ip7, ip8, ip9, ip10, notes, creation_type_id, created_by, insert_time, update_time
    FROM dns
    ORDER BY `name` ASC, number_of_servers DESC")->fetchAll();

if ($export_data === 1) {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('dns_profile_list'), strtotime($time->stamp()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        _('Status'),
        _('DNS Profile'),
        _('DNS Servers'),
        _('Domains'),
        _('Default DNS Profile') . '?',
        _('DNS Server') . ' 1',
        _('IP Address') . ' 1',
        _('DNS Server') . ' 2',
        _('IP Address') . ' 2',
        _('DNS Server') . ' 3',
        _('IP Address') . ' 3',
        _('DNS Server') . ' 4',
        _('IP Address') . ' 4',
        _('DNS Server') . ' 5',
        _('IP Address') . ' 5',
        _('DNS Server') . ' 6',
        _('IP Address') . ' 6',
        _('DNS Server') . ' 7',
        _('IP Address') . ' 7',
        _('DNS Server') . ' 8',
        _('IP Address') . ' 8',
        _('DNS Server') . ' 9',
        _('IP Address') . ' 9',
        _('DNS Server') . ' 10',
        _('IP Address') . ' 10',
        _('Notes'),
        _('Creation Type'),
        _('Created By'),
        _('Inserted'),
        _('Updated')
    );
    $export->writeRow($export_file, $row_contents);

    if ($result) {

        foreach ($result as $row) {

            $total_domains = $pdo->query("
                SELECT count(*)
                FROM domains
                WHERE dns_id = '" . $row->id . "'
                  AND active NOT IN ('0', '10')")->fetchColumn();

            if ($row->id == $_SESSION['s_default_dns']) {

                $is_default = '1';

            } else {

                $is_default = '0';

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
                $row->name,
                number_format($row->number_of_servers),
                number_format($total_domains),
                $is_default,
                $row->dns1,
                $row->ip1,
                $row->dns2,
                $row->ip2,
                $row->dns3,
                $row->ip3,
                $row->dns4,
                $row->ip4,
                $row->dns5,
                $row->ip5,
                $row->dns6,
                $row->ip6,
                $row->dns7,
                $row->ip7,
                $row->dns8,
                $row->ip8,
                $row->dns9,
                $row->ip9,
                $row->dns10,
                $row->ip10,
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
<?php echo sprintf(_('Below is a list of all the %s that have been added to %s.'), _('DNS Profiles'), SOFTWARE_TITLE); ?><BR>
<BR>
<a href="add/dns.php"><?php echo $layout->showButton('button', _('Add DNS Profile')); ?></a>
<a href="dns.php?export_data=1"><?php echo $layout->showButton('button', _('Export')); ?></a><BR><BR><?php

if ($result) { ?>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th><?php echo _('Name'); ?></th>
            <th><?php echo _('Servers'); ?></th>
            <th><?php echo _('Domains'); ?></th>
        </tr>
        </thead>
        <tbody><?php

        foreach ($result as $row) {

            $total_domains = $pdo->query("
                SELECT count(*)
                FROM domains
                WHERE dns_id = '" . $row->id . "'
                  AND active NOT IN ('0', '10')")->fetchColumn();

            if ($total_domains >= 1 || $_SESSION['s_display_inactive_assets'] == '1') { ?>

                <tr>
                <td></td>
                <td>
                    <a href="edit/dns.php?dnsid=<?php echo $row->id; ?>"><?php echo $row->name; ?></a><?php if ($_SESSION['s_default_dns'] == $row->id) echo '<strong>*</strong>'; ?>
                </td>
                <td>
                    <a href="edit/dns.php?dnsid=<?php echo $row->id; ?>"><?php echo $row->number_of_servers; ?></a>
                </td>
                <td><?php

                    if ($total_domains >= 1) { ?>

                        <a href="../domains/index.php?dnsid=<?php echo $row->id; ?>"><?php echo number_format($total_domains); ?></a><?php

                    } else {

                        echo '-';

                    } ?>

                </td>
                </tr><?php

            }

        } ?>

        </tbody>
    </table>

    <strong>*</strong> = <?php echo _('Default'); ?> (<a href="../settings/defaults/"><?php echo strtolower(_('Set Defaults')); ?></a>)<BR><BR><?php

} else { ?>

    <BR><?php echo _("You don't currently have any DNS Profiles."); ?> <a href="add/dns.php"><?php echo _('Click here to add one'); ?></a>.<?php

} ?>
<?php require_once DIR_INC . '/layout/asset-footer.inc.php'; ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
</body>
</html>
