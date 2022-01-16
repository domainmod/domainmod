<?php
/**
 * /admin/users/index.php
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
require_once DIR_INC . '/settings/admin-users-main.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin']);
$pdo = $deeb->cnxx;

$export_data = (int) $_GET['export_data'];

$result = $pdo->query("
    SELECT u.id, u.first_name, u.last_name, u.username, u.email_address, u.admin, u.read_only, u.active, u.number_of_logins, u.last_login, u.creation_type_id, u.created_by, u.insert_time, u.update_time, us.default_language, us.default_currency, us.default_timezone
    FROM users AS u, user_settings AS us
    WHERE u.id = us.user_id
    ORDER BY u.first_name, u.last_name, u.username, u.email_address")->fetchAll();

if ($export_data === 1) {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('user_list'), strtotime($time->stamp()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        _('Status'),
        _('First Name'),
        _('Last Name'),
        _('Username'),
        _('Email Address'),
        _('Admin') . '?',
        _('Read-Only') . '?',
        _('Default Language'),
        _('Default Currency'),
        _('Default Timezone'),
        _('Number of Logins'),
        _('Last Login'),
        _('Creation Type'),
        _('Created By'),
        _('Inserted'),
        _('Updated')
    );
    $export->writeRow($export_file, $row_contents);

    if ($result) {

        foreach ($result as $row) {

            if ($row->admin == '1') {

                $is_admin = '1';

            } else {

                $is_admin = '0';

            }

            if ($row->read_only == '1') {

                $is_read_only = '1';

            } else {

                $is_read_only = '0';

            }

            if ($row->active == '1') {

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
                $row->first_name,
                $row->last_name,
                $row->username,
                $row->email_address,
                $is_admin,
                $is_read_only,
                $row->default_language,
                $row->default_currency,
                $row->default_timezone,
                $row->number_of_logins,
                $time->toUserTimezone($row->last_login),
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
<?php echo sprintf(_('Below is a list of all users that have access to %s.'), SOFTWARE_TITLE); ?><BR>
<BR>
<a href="add.php"><?php echo $layout->showButton('button', _('Add User')); ?></a>
<a href="index.php?export_data=1"><?php echo $layout->showButton('button', _('Export')); ?></a><BR><BR><?php

if ($result) { ?>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th><?php echo _('User'); ?></th>
            <th><?php echo _('Username'); ?></th>
            <th><?php echo _('Email'); ?></th>
        </tr>
        </thead>
        <tbody><?php

        foreach ($result as $row) { ?>

            <tr>
            <td></td>
            <td>
                <a <?php if ($row->active != '1') { ?>style="text-decoration: line-through;"
                   <?php } ?>href="edit.php?uid=<?php echo $row->id; ?>"><?php echo $row->first_name; ?>
                    &nbsp;<?php echo $row->last_name; ?></a><?php if ($row->admin == '1') echo "&nbsp;&nbsp;<strong>" . _('A') . "</strong>"; ?><?php if ($row->read_only == '1') echo "&nbsp;&nbsp;<strong>" . _('R') . "</strong>"; ?>
            </td>
            <td>
                <a href="edit.php?uid=<?php echo $row->id; ?>"><?php echo $row->username; ?></a>
            </td>
            <td>
                <a href="edit.php?uid=<?php echo $row->id; ?>"><?php echo $row->email_address; ?></a>
            </td>
            </tr><?php

        } ?>

        </tbody>
    </table>

    <strong><?php echo _('A'); ?></strong> = <?php echo _('Admin'); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    <strong><?php echo _('R'); ?></strong> = <?php echo _('Read-Only'); ?>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span class="domainmod-css-line-through"><?php echo strtoupper(_('Strike')); ?></span> = <?php echo _('Inactive'); ?><?php

} ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
