<?php
/**
 * /assets/registrars.php
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
require_once DIR_INC . '/settings/assets-registrars.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$export_data = (int) ($_GET['export_data'] ?? 0);

$result = $pdo->query("
    SELECT id AS rid, `name` AS rname, url, api_registrar_id, notes, creation_type_id, created_by, insert_time, update_time
    FROM registrars
    ORDER BY rname ASC")->fetchAll();

if ($export_data === 1) {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('registrar_list'), strtotime($time->stamp()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        _('Status'),
        _('Registrar'),
        _('Accounts'),
        _('Domains'),
        _('Default Registrar') . '?',
        _('URL'),
        _('Notes'),
        _('API Registrar'),
        _('Creation Type'),
        _('Created By'),
        _('Inserted'),
        _('Updated')
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
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<p class="domainmod-intro"><?php echo sprintf(_('Below is a list of all the %s that have been added to %s.'), _('Domain Registrars'), SOFTWARE_TITLE); ?></p>
<?php
// The page's actions, drawn in the page header (see Layout::renderPageActions())
$layout->setPageAddAction(_('Add Registrar'), 'add/registrar.php');
$layout->addPageAction(_('Export'), 'registrars.php?export_data=1', 'bi-download');

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
            <th></th>
            <th><?php echo _('Registrar'); ?></th>
            <th><?php echo _('Accounts'); ?></th>
            <th><?php echo _('Domains'); ?></th>
            <th class="ps-4"><?php echo _('Options'); ?></th>
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

            if ($total_domains >= 1 || $_SESSION['s_display_inactive_assets'] == '1') {

                $shown++; ?>

                <tr>
                <td></td>
                <td>
                    <a href="edit/registrar.php?rid=<?php echo $row->rid; ?>"><?php echo $row->rname; ?></a><?php if ($_SESSION['s_default_registrar'] == $row->rid) echo $default_marker; ?>
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
                <td class="ps-4">
                    <a href="registrar-fees.php?rid=<?php echo $row->rid; ?>"><?php echo _('Fees'); ?></a><?php if ($row->url != '') { ?><a class="ms-2" target="_blank" rel="noopener" href="<?php echo $row->url; ?>" data-bs-toggle="tooltip" data-bs-title="<?php echo htmlspecialchars(_('Website'), ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i><span class="visually-hidden"><?php echo _('Website'); ?></span></a><?php } ?>
                </td>
                </tr><?php

            }

        } ?>

        </tbody>
    </table><?php

    if ($shown === 0) {

        ob_end_clean();
        echo $layout->emptyState('bi-eye-slash', _('No registrars are in use'), _('Unused registrars are hidden. You can show them in Display Settings.'), '', '', '/settings/display/', _('Display Settings'));

    } else {

        ob_end_flush();

    }

} else {

    echo $layout->emptyState('bi-building', _('No registrars yet'), _('Add the registrars where your domains are registered.'));

} ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
</body>
</html>
