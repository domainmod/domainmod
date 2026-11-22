<?php
/**
 * /assets/categories.php
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
require_once DIR_INC . '/settings/assets-categories.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$export_data = (int) ($_GET['export_data'] ?? 0);

$result = $pdo->query("
    SELECT id, `name`, stakeholder, notes, creation_type_id, created_by, insert_time, update_time
    FROM categories
    ORDER BY `name`")->fetchAll();

// Once the database has the hosting tables, each category also shows how many hosting services that aren't Cancelled use it,
// which count as use as its domains and SSL certificates do
$hosting_available = DomainMOD\Hosting::isAvailable($pdo);
$stmt_hosting_count = $hosting_available ? $pdo->prepare("
    SELECT count(*)
    FROM hosting_services
    WHERE cat_id = :asset_id
      AND active NOT IN ('0')") : null;
$count_hosting_services = function ($asset_id) use ($stmt_hosting_count) {
    if ($stmt_hosting_count === null) return 0;
    $stmt_hosting_count->bindValue('asset_id', (int) $asset_id, PDO::PARAM_INT);
    $stmt_hosting_count->execute();
    $total = (int) $stmt_hosting_count->fetchColumn();
    $stmt_hosting_count->closeCursor();
    return $total;
};
$default_hosting = (int) ($_SESSION['s_default_category_hosting'] ?? 0);

if ($export_data === 1) {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('category_list'), strtotime($time->stamp()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        _('Status'),
        _('Category'),
        _('Stakeholder'),
        _('Domains'),
        _('Hosting Services'),
        _('SSL Certs'),
        _('Default Domain Category') . '?',
        _('Default Hosting Category') . '?',
        _('Default SSL Category') . '?',
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
                WHERE active NOT IN ('0', '10')
                  AND cat_id = '" . $row->id . "'")->fetchColumn();

            $total_certs = $pdo->query("
                SELECT count(*)
                FROM ssl_certs
                WHERE active NOT IN ('0')
                  AND cat_id = '" . $row->id . "'")->fetchColumn();

            $total_hosting_services = $count_hosting_services($row->id);

            if ($row->id == $_SESSION['s_default_category_domains']) {

                $is_default_domains = '1';

            } else {

                $is_default_domains = '0';

            }

            if ($row->id == $_SESSION['s_default_category_ssl']) {

                $is_default_ssl = '1';

            } else {

                $is_default_ssl = '0';

            }

            $is_default_hosting = (int) $row->id === $default_hosting ? '1' : '0';

            if ($total_domains >= 1 || $total_certs >= 1 || $total_hosting_services >= 1) {

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
                $row->stakeholder,
                $total_domains,
                $total_hosting_services,
                $total_certs,
                $is_default_domains,
                $is_default_hosting,
                $is_default_ssl,
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
<p class="domainmod-intro"><?php echo sprintf(_('Below is a list of all the %s that have been added to %s.'), _('Categories'), SOFTWARE_TITLE); ?></p>
<?php
// The page's actions, drawn in the page header (see Layout::renderPageActions())
$layout->setPageAddAction(_('Add Category'), 'add/category.php');
$layout->addPageAction(_('Export'), 'categories.php?export_data=1', 'bi-download');

if ($result) {

    // Unused assets are shown or hidden for the rest of the session (the toggle returns to this page)
    if ($_SESSION['s_display_inactive_assets'] == '1') {
        $layout->addPageAction(_('Hide Inactive'), WEB_ROOT . '/settings/toggles/inactive-assets/?' . (new DomainMOD\Csrf())->query(), 'bi-eye-slash');
    } else {
        $layout->addPageAction(_('Show Inactive'), WEB_ROOT . '/settings/toggles/inactive-assets/?' . (new DomainMOD\Csrf())->query(), 'bi-eye');
    }
    $layout->addPageAction(_('Set Defaults'), WEB_ROOT . '/settings/defaults/', 'bi-person-gear');

    // Each row's defaults (see Settings, User Defaults) as icons, with their meanings in a tooltip and for screen readers
    // Each icon's wrapper holds a space before it, which keeps the hidden text apart from the name when the list is
    // searched or sorted. As an inline block, the wrapper doesn't show that space.
    $default_domains_marker = '<span class="d-inline-block ms-2"> ' . $layout->statusBadge(htmlspecialchars(_('Default for domains'), ENT_QUOTES, 'UTF-8'), _('Default for domains'), 'warning', 'bi-globe2') . '</span>';
    $default_ssl_marker = '<span class="d-inline-block ms-2"> ' . $layout->statusBadge(htmlspecialchars(_('Default for SSL certificates'), ENT_QUOTES, 'UTF-8'), _('Default for SSL certificates'), 'warning', 'bi-shield-lock') . '</span>';
    $default_hosting_marker = '<span class="d-inline-block ms-2"> ' . $layout->statusBadge(htmlspecialchars(_('Default for hosting services'), ENT_QUOTES, 'UTF-8'), _('Default for hosting services'), 'warning', 'bi-server') . '</span>';

    // The rows are buffered, so if every one is hidden as unused, the empty table gives way to an empty state
    $shown = 0;
    ob_start(); ?>
    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th><span class="visually-hidden"><?php echo _('Details'); ?></span></th>
            <th><?php echo _('Category'); ?></th>
            <th><?php echo _('Stakeholder'); ?></th>
            <th><?php echo _('Domains'); ?></th><?php
            if ($hosting_available) { ?>
            <th><?php echo _('Hosting Services'); ?></th><?php
            } ?>
            <th><?php echo _('SSL Certs'); ?></th>
        </tr>
        </thead>
        <tbody><?php

        foreach ($result as $row) {

            $total_domains = $pdo->query("
                SELECT count(*)
                FROM domains
                WHERE active NOT IN ('0', '10')
                  AND cat_id = '" . $row->id . "'")->fetchColumn();

            $total_certs = $pdo->query("
                SELECT count(*)
                FROM ssl_certs
                WHERE active NOT IN ('0')
                  AND cat_id = '" . $row->id . "'")->fetchColumn();

            $total_hosting_services = $count_hosting_services($row->id);

            if (($total_domains >= 1 || $total_certs >= 1 || $total_hosting_services >= 1) || $_SESSION['s_display_inactive_assets'] == '1') {

                $shown++; ?>

                <tr>
                <td></td>
                <td>
                    <a href="edit/category.php?pcid=<?php echo $row->id; ?>"><?php echo $row->name; ?></a><?php if ($_SESSION['s_default_category_domains'] == $row->id) echo $default_domains_marker; ?><?php if ($hosting_available && $default_hosting === (int) $row->id) echo $default_hosting_marker; ?><?php if ($_SESSION['s_default_category_ssl'] == $row->id) echo $default_ssl_marker; ?>
                </td>
                <td>
                    <a href="edit/category.php?pcid=<?php echo $row->id; ?>"><?php echo $row->stakeholder; ?></a>
                </td>
                <td><?php

                    if ($total_domains >= 1) { ?>

                        <a href="../domains/index.php?pcid=<?php echo $row->id; ?>"><?php echo number_format($total_domains); ?></a><?php

                    } else {

                        echo "-";

                    } ?>
                </td><?php

                if ($hosting_available) { ?>

                    <td><?php

                        if ($total_hosting_services >= 1) { ?>

                            <a href="../hosting/index.php?hcid=<?php echo $row->id; ?>"><?php echo number_format($total_hosting_services); ?></a><?php

                        } else {

                            echo "-";

                        } ?>
                    </td><?php

                } ?>
                <td><?php

                    if ($total_certs >= 1) { ?>

                        <a href="../ssl/index.php?sslpcid=<?php echo $row->id; ?>"><?php echo number_format($total_certs); ?></a><?php

                    } else {

                        echo "-";

                    } ?>
                </td>
                </tr><?php

            }

        } ?>

        </tbody>
    </table><?php

    if ($shown === 0) {

        ob_end_clean();
        echo $layout->emptyState('bi-eye-slash', _('No categories are in use'), _('Unused categories are hidden. Choose Show Inactive to see them.'), '', '', '/settings/display/', _('Display Settings'));

    } else {

        ob_end_flush();

    }

} else {

    echo $layout->emptyState('bi-tags', _('No categories yet'), _('Add categories to group your domains and certificates.'));

} ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
</body>
</html>
