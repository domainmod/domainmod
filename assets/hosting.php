<?php
/**
 * /assets/hosting.php
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
require_once DIR_INC . '/settings/assets-hosting.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$export_data = (int) ($_GET['export_data'] ?? 0);

$result = $pdo->query("
    SELECT id, `name`, url, notes, creation_type_id, created_by, insert_time, update_time
    FROM hosting
    ORDER BY `name` ASC")->fetchAll();

/*
 * Once the database has the hosting tables, each provider shows how many hosting accounts it has and how many hosting
 * services that aren't Cancelled use it, and counts as in use (for Show Inactive and the export's Status) when an
 * account or one of those services uses it. Domains don't name a provider any more (they choose a hosting service).
 * Before the update, while they still do, each provider shows how many active domains use it instead, as plain text
 * (the Domains list no longer filters by provider), and counts as in use when any do.
 */
$hosting_available = DomainMOD\Hosting::isAvailable($pdo);
$stmt_domain_count = !DomainMOD\Hosting::legacyDomainColumn($pdo) ? null : $pdo->prepare( // Legacy: domains.hosting_id before 4.27.0
    "SELECT count(*)
    FROM domains
    WHERE hosting_id = :hosting_id
      AND active NOT IN ('0', '10')");
$count_domains = function ($hosting_id) use ($stmt_domain_count) {
    if ($stmt_domain_count === null) return 0;
    $stmt_domain_count->bindValue('hosting_id', (int) $hosting_id, PDO::PARAM_INT);
    $stmt_domain_count->execute();
    $total = (int) $stmt_domain_count->fetchColumn();
    $stmt_domain_count->closeCursor();
    return $total;
};
$stmt_account_count = $hosting_available ? $pdo->prepare("
    SELECT count(*)
    FROM hosting_provider_accounts
    WHERE hosting_id = :hosting_id") : null;
$count_hosting_provider_accounts = function ($hosting_id) use ($stmt_account_count) {
    if ($stmt_account_count === null) return 0;
    $stmt_account_count->bindValue('hosting_id', (int) $hosting_id, PDO::PARAM_INT);
    $stmt_account_count->execute();
    $total = (int) $stmt_account_count->fetchColumn();
    $stmt_account_count->closeCursor();
    return $total;
};
$stmt_hosting_count = $hosting_available ? $pdo->prepare("
    SELECT count(*)
    FROM hosting_services
    WHERE hosting_id = :hosting_id
      AND active NOT IN ('0')") : null;
$count_hosting_services = function ($hosting_id) use ($stmt_hosting_count) {
    if ($stmt_hosting_count === null) return 0;
    $stmt_hosting_count->bindValue('hosting_id', (int) $hosting_id, PDO::PARAM_INT);
    $stmt_hosting_count->execute();
    $total = (int) $stmt_hosting_count->fetchColumn();
    $stmt_hosting_count->closeCursor();
    return $total;
};

if ($export_data === 1) {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('hosting_provider_list'), strtotime($time->stamp()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(_('Status'), _('Hosting Provider'));
    if ($hosting_available) {
        $row_contents[] = _('Hosting Accounts');
        $row_contents[] = _('Hosting Services');
    } else {
        $row_contents[] = _('Domains');
    }
    $row_contents = array_merge($row_contents, array(
        _('Default Hosting Provider') . '?',
        _('URL'),
        _('Notes'),
        _('Creation Type'),
        _('Created By'),
        _('Inserted'),
        _('Updated')
    ));
    $export->writeRow($export_file, $row_contents);

    if ($result) {

        foreach ($result as $row) {

            $total_domains = $count_domains($row->id);
            $total_hosting_provider_accounts = $count_hosting_provider_accounts($row->id);
            $total_hosting_services = $count_hosting_services($row->id);

            if ($row->id == $_SESSION['s_default_host']) {

                $is_default = '1';

            } else {

                $is_default = '0';

            }

            if ($total_domains >= 1 || $total_hosting_provider_accounts >= 1 || $total_hosting_services >= 1) {

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

            $row_contents = array($status, $row->name);
            if ($hosting_available) {
                $row_contents[] = number_format($total_hosting_provider_accounts);
                $row_contents[] = number_format($total_hosting_services);
            } else {
                $row_contents[] = number_format($total_domains);
            }
            $row_contents = array_merge($row_contents, array(
                $is_default,
                $row->url,
                $row->notes,
                $creation_type,
                $created_by,
                $time->toUserTimezone($row->insert_time),
                $time->toUserTimezone($row->update_time)
            ));
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
<p class="domainmod-intro"><?php echo sprintf(_('Below is a list of all the %s that have been added to %s.'), _('Hosting Providers'), SOFTWARE_TITLE); ?></p>
<?php
// The page's actions, drawn in the page header (see Layout::renderPageActions())
$layout->setPageAddAction(_('Add Hosting Provider'), 'add/host.php');
$layout->addPageAction(_('Export'), 'hosting.php?export_data=1', 'bi-download');

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
            <th><?php echo _('Provider'); ?></th><?php
            if ($hosting_available) { ?>
            <th><?php echo _('Accounts'); ?></th>
            <th><?php echo _('Services'); ?></th><?php
            } else { ?>
            <th><?php echo _('Domains'); ?></th><?php
            } ?>
            <th class="ps-4"><?php echo _('Options'); ?></th>
        </tr>
        </thead>
        <tbody><?php

        foreach ($result as $row) {

            $total_domains = $count_domains($row->id);
            $total_hosting_provider_accounts = $count_hosting_provider_accounts($row->id);
            $total_hosting_services = $count_hosting_services($row->id);

            if ($total_domains >= 1 || $total_hosting_provider_accounts >= 1 || $total_hosting_services >= 1 || $_SESSION['s_display_inactive_assets'] == '1') {

                $shown++; ?>

                <tr>
                <td></td>
                <td>
                    <a href="edit/host.php?whid=<?php echo $row->id; ?>"><?php echo $row->name; ?></a><?php if ($_SESSION['s_default_host'] == $row->id) echo $default_marker; ?>
                </td><?php

                if (!$hosting_available) { ?>

                    <td><?php echo $total_domains >= 1 ? number_format($total_domains) : '-'; ?></td><?php

                } else { ?>

                    <td><?php

                        if ($total_hosting_provider_accounts >= 1) { ?>

                            <a href="hosting-accounts.php?whid=<?php echo $row->id; ?>"><?php echo number_format($total_hosting_provider_accounts); ?></a><?php

                        } else {

                            echo '-';

                        } ?>

                    </td>
                    <td><?php

                        if ($total_hosting_services >= 1) { ?>

                            <a href="../hosting/index.php?hid=<?php echo $row->id; ?>"><?php echo number_format($total_hosting_services); ?></a><?php

                        } else {

                            echo '-';

                        } ?>

                    </td><?php

                } ?>

                <td class="ps-4"><?php
                    // Each provider's fees, once the database has the hosting tables
                    if ($hosting_available) { ?><a href="hosting-fees.php?hosting_id=<?php echo $row->id; ?>"><?php echo _('Fees'); ?></a><?php } ?>
                    <?php if ($row->url != '') { ?><a<?php if ($hosting_available) echo ' class="ms-2"'; ?> target="_blank" rel="noopener" href="<?php echo $row->url; ?>" data-bs-toggle="tooltip" data-bs-title="<?php echo htmlspecialchars(_('Website'), ENT_QUOTES, 'UTF-8'); ?>"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i><span class="visually-hidden"><?php echo _('Website'); ?></span></a><?php } ?>
                </td>
                </tr><?php

            }

        } ?>

        </tbody>
    </table><?php

    if ($shown === 0) {

        ob_end_clean();
        echo $layout->emptyState('bi-eye-slash', _('No hosting providers are in use'), _('Unused hosting providers are hidden. Choose Show Inactive to see them.'), '', '', '/settings/display/', _('Display Settings'));

    } else {

        ob_end_flush();

    }

} else {

    echo $layout->emptyState('bi-server', _('No hosting providers yet'), _('Add the companies that host your websites.'));

} ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
</body>
</html>
