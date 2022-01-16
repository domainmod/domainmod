<?php
/**
 * /admin/domain-fields/index.php
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
require_once DIR_INC . '/settings/admin-custom-domain-fields.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin']);
$pdo = $deeb->cnxx;

$export_data = (int) $_GET['export_data'];

$result = $pdo->query("
    SELECT f.id, f.name, f.field_name, f.description, f.notes, f.creation_type_id, f.created_by, f.insert_time, f.update_time, t.name AS type
    FROM domain_fields AS f, custom_field_types AS t
    WHERE f.type_id = t.id
    ORDER BY f.name")->fetchAll();

if ($export_data === 1) {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('custom_domain_field_list'), strtotime($time->stamp()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        _('Display Name'),
        _('DB Field'),
        _('Data Type'),
        _('Description'),
        _('Notes'),
        _('Creation Type'),
        _('Created By'),
        _('Inserted'),
        _('Updated')
    );
    $export->writeRow($export_file, $row_contents);

    if ($result) {

        foreach ($result as $row) {

            $creation_type = $system->getCreationType($row->creation_type_id);

            if ($row->created_by == '0') {
                $created_by = _('Unknown');
            } else {
                $user = new DomainMOD\User();
                $created_by = $user->getFullName($row->created_by);
            }

            $row_contents = array(
                $row->name,
                $row->field_name,
                $row->type,
                $row->description,
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
<?php echo sprintf(_('Below is a list of all the %s that have been added to %s.'), _('Custom Domain Fields'), SOFTWARE_TITLE); ?><BR>
<BR>
<?php echo sprintf(_('Custom Domain Fields help extend the functionality of %s by allowing the user to create their own data fields.'), SOFTWARE_TITLE); ?>
<?php echo '&nbsp;'; echo _('For example, if you wanted to keep track of which domains are currently setup in Google Analytics, you could create a new Google Analytics check box field and start tracking this information for each of your domains.'); ?>
<?php echo '&nbsp;'; echo _("Combine custom fields with the ability to update them with the Bulk Updater, and the sky's the limit in regards to what data you can easily track!"); ?><BR>
<BR>
<?php echo _('And when you export your domain data, the information contained in your custom fields will automatically be included in the exported data.'); ?><BR>
<BR><?php

if (!$result) {

    echo sprintf(_("It appears as though you haven't created any Custom Domain Fields yet. %sClick here%s to add one."), '<a href="add.php">', '</a>');

} else { ?>

    <a href="add.php"><?php echo $layout->showButton('button', _('Add Custom Field')); ?></a>
    <a href="index.php?export_data=1"><?php echo $layout->showButton('button', _('Export')); ?></a><BR><BR>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th><?php echo _('Name'); ?></th>
            <th><?php echo _('DB Fields'); ?></th>
            <th><?php echo _('Data Type'); ?></th>
            <th><?php echo _('Inserted'); ?></th>
            <th><?php echo _('Updated'); ?></th>
        </tr>
        </thead>
        <tbody><?php

        foreach ($result as $row) { ?>

            <tr>
            <td></td>
            <td>
                <a href="edit.php?cdfid=<?php echo $row->id; ?>"><?php echo $row->name; ?></a>
            </td>
            <td>
                <a href="edit.php?cdfid=<?php echo $row->id; ?>"><?php echo $row->field_name; ?></a>
            </td>
            <td>
                <a href="edit.php?cdfid=<?php echo $row->id; ?>"><?php echo $row->type; ?></a>
            </td>
            <td>
                <a href="edit.php?cdfid=<?php echo $row->id; ?>"><?php echo $time->toUserTimezone($row->insert_time); ?></a>
            </td>
            <td><?php

                if ($row->update_time != '1970-01-01 00:00:00') {

                    $temp_update_time = $time->toUserTimezone($row->update_time);

                } else {

                    $temp_update_time = '-';

                } ?>
                <a href="edit.php?cdfid=<?php echo $row->id; ?>"><?php echo $temp_update_time; ?></a>
            </td>
            </tr><?php

        } ?>

        </tbody>
    </table><?php

} ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
</body>
</html>
