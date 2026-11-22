<?php
/**
 * /assets/hosting-types.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2026 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: https://greg.ca
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
require_once DIR_INC . '/settings/assets-hosting-types.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

// Before the database update there are no hosting tables to list
if (!DomainMOD\Hosting::isAvailable($pdo)) {

    $_SESSION['s_message_danger'] .= DomainMOD\Hosting::unavailableMessage() . '<BR>';
    header("Location: index.php");
    exit;

}

$export_data = (int) ($_GET['export_data'] ?? 0);

$result = $pdo->query("
    SELECT id, type, notes, creation_type_id, created_by, insert_time, update_time
    FROM hosting_types
    ORDER BY type ASC")->fetchAll();

// How many hosting services that aren't Cancelled use each service type
$stmt_count = $pdo->prepare("
    SELECT count(*)
    FROM hosting_services
    WHERE type_id = :type_id
      AND active NOT IN ('0')");
$count_services = function ($type_id) use ($stmt_count) {
    $stmt_count->bindValue('type_id', (int) $type_id, PDO::PARAM_INT);
    $stmt_count->execute();
    $total = (int) $stmt_count->fetchColumn();
    $stmt_count->closeCursor();
    return $total;
};
$default_type = (int) ($_SESSION['s_default_hosting_type'] ?? 0);

if ($export_data === 1) {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('hosting_service_type_list'), strtotime($time->stamp()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        _('Status'),
        _('Service Type'),
        _('Hosting Services'),
        _('Default Service Type') . '?',
        _('Notes'),
        _('Creation Type'),
        _('Created By'),
        _('Inserted'),
        _('Updated')
    );
    $export->writeRow($export_file, $row_contents);

    if ($result) {

        foreach ($result as $row) {

            $total_services = $count_services($row->id);

            if ((int) $row->id === $default_type) {

                $is_default = '1';

            } else {

                $is_default = '0';

            }

            if ($total_services >= 1) {

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
                $row->type,
                number_format($total_services),
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
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<p class="domainmod-intro"><?php echo sprintf(_('Below is a list of all the %s that have been added to %s.'), _('Service Types'), SOFTWARE_TITLE); ?>
    <?php echo _('Types are shared by every provider. A fee is set per provider, type, and billing term, so if one provider sells two shared plans at different prices, add a type for each (such as Shared Starter and Shared Pro).'); ?></p>
<?php
// The page's actions, drawn in the page header (see Layout::renderPageActions())
$layout->setPageAddAction(_('Add Type'), 'add/hosting-type.php');
$layout->addPageAction(_('Export'), 'hosting-types.php?export_data=1', 'bi-download');

if ($result) {

    // Unused assets are shown or hidden for the rest of the session (the toggle returns to this page)
    if ($_SESSION['s_display_inactive_assets'] == '1') {
        $layout->addPageAction(_('Hide Inactive'), WEB_ROOT . '/settings/toggles/inactive-assets/?' . (new DomainMOD\Csrf())->query(), 'bi-eye-slash');
    } else {
        $layout->addPageAction(_('Show Inactive'), WEB_ROOT . '/settings/toggles/inactive-assets/?' . (new DomainMOD\Csrf())->query(), 'bi-eye');
    }
    $layout->addPageAction(_('Set Defaults'), WEB_ROOT . '/settings/defaults/', 'bi-person-gear');

    // The default (see Settings, User Defaults) as a star, with its meaning in a tooltip and for screen readers
    // Each icon's wrapper holds a space before it, which keeps the hidden text apart from the name when the list is
    // searched or sorted. As an inline block, the wrapper doesn't show that space.
    $default_marker = '<span class="d-inline-block ms-2"> ' . $layout->statusBadge(htmlspecialchars(_('Default'), ENT_QUOTES, 'UTF-8'), _('Default'), 'warning', 'bi-star-fill') . '</span>';

    // The rows are buffered, so if every one is hidden as unused, the empty table gives way to an empty state
    $shown = 0;
    ob_start(); ?>
    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th><span class="visually-hidden"><?php echo _('Details'); ?></span></th>
            <th><?php echo _('Type'); ?></th>
            <th><?php echo _('Services'); ?></th>
        </tr>
        </thead>
        <tbody><?php

        foreach ($result as $row) {

            $total_services = $count_services($row->id);

            if ($total_services >= 1 || $_SESSION['s_display_inactive_assets'] == '1') {

                $shown++; ?>

                <tr>
                <td></td>
                <td>
                    <a href="edit/hosting-type.php?htid=<?php echo $row->id; ?>"><?php echo $row->type; ?></a><?php if ((int) $row->id === $default_type) echo $default_marker; ?>
                </td>
                <td><?php

                    if ($total_services >= 1) { ?>

                        <a href="../hosting/index.php?htid=<?php echo $row->id; ?>"><?php echo number_format($total_services); ?></a><?php

                    } else {

                        echo '-';

                    } ?>

                </td>
                </tr><?php

            }

        } ?>

        </tbody>
    </table><?php

    if ($shown === 0) {

        ob_end_clean();
        echo $layout->emptyState('bi-eye-slash', _('No service types are in use'), _('Unused service types are hidden. Choose Show Inactive to see them.'), '', '', '/settings/display/', _('Display Settings'));

    } else {

        ob_end_flush();

    }

} else {

    echo $layout->emptyState('bi-stack', _('No service types yet'), _('Add the kinds of service your providers sell, such as Shared, VPS, and Email.'));

} ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
</body>
</html>
