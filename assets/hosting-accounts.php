<?php
/**
 * /assets/hosting-accounts.php
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
require_once DIR_INC . '/settings/assets-hosting-accounts.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

// Before the database update there are no hosting tables to list
if (!DomainMOD\Hosting::isAvailable($pdo)) {

    $_SESSION['s_message_danger'] .= DomainMOD\Hosting::unavailableMessage() . '<BR>';
    header("Location: index.php");
    exit;

}

$whid = (int) ($_GET['whid'] ?? 0);
$hpaid = (int) ($_GET['hpaid'] ?? 0);
$oid = (int) ($_GET['oid'] ?? 0);
$export_data = (int) ($_GET['export_data'] ?? 0);

if ($whid !== 0) { $whid_string = ' AND hpa.hosting_id = ' . $whid . ' '; } else { $whid_string = ''; }
if ($hpaid !== 0) { $hpaid_string = ' AND hpa.id = ' . $hpaid . ' '; } else { $hpaid_string = ''; }
if ($oid !== 0) { $oid_string = ' AND hpa.owner_id = ' . $oid . ' '; } else { $oid_string = ''; }

$result = $pdo->query("
    SELECT hpa.id AS hpaid, hpa.email_address, hpa.username, hpa.owner_id, hpa.hosting_id, hpa.reseller, hpa.reseller_id, o.id AS oid,
        o.name AS oname, h.id AS whid, h.name AS hname, hpa.notes, hpa.creation_type_id, hpa.created_by, hpa.insert_time, hpa.update_time
    FROM hosting_provider_accounts AS hpa, owners AS o, hosting AS h
    WHERE hpa.owner_id = o.id
      AND hpa.hosting_id = h.id" .
      $whid_string .
      $hpaid_string .
      $oid_string . "
    GROUP BY hpa.username, oname, hname
    ORDER BY hname, username, oname")->fetchAll();

// How many hosting services that aren't Cancelled use each account (the account_id key makes each a seek). The count
// matches the Hosting Services list's default status, which its link opens.
$stmt_count = $pdo->prepare("
    SELECT count(*)
    FROM hosting_services
    WHERE account_id = :account_id
      AND active NOT IN ('0')");
$count_services = function ($account_id) use ($stmt_count) {
    $stmt_count->bindValue('account_id', (int) $account_id, PDO::PARAM_INT);
    $stmt_count->execute();
    $total = (int) $stmt_count->fetchColumn();
    $stmt_count->closeCursor();
    return $total;
};
$default_account = (int) ($_SESSION['s_default_hosting_provider_account'] ?? 0);

if ($export_data === 1) {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('hosting_account_list'), strtotime($time->stamp()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        _('Status'),
        _('Hosting Provider'),
        _('Email Address'),
        _('Username'),
        _('Reseller Account') . '?',
        _('Reseller ID'),
        _('Owner'),
        _('Hosting Services'),
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

            $total_services = $count_services($row->hpaid);

            if ((int) $row->hpaid === $default_account) {

                $is_default = '1';

            } else {

                $is_default = '0';

            }

            if ($row->reseller == '0') {

                $is_reseller = '0';

            } else {

                $is_reseller = '1';

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
                $row->hname,
                $row->email_address,
                $row->username,
                $is_reseller,
                $row->reseller_id,
                $row->oname,
                $total_services,
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
<p class="domainmod-intro"><?php echo sprintf(_('Below is a list of all the %s that have been added to %s.'), _('Hosting Accounts'), SOFTWARE_TITLE); ?></p>
<?php
// The page's actions, drawn in the page header (see Layout::renderPageActions())
$layout->setPageAddAction(_('Add Account'), 'add/hosting-provider-account.php');
$layout->addPageAction(_('Export'), 'hosting-accounts.php?export_data=1&whid=' . $whid . '&hpaid=' . $hpaid . '&oid=' . $oid, 'bi-download');

if ($result) {

    // Unused assets are shown or hidden for the rest of the session (the toggle returns to this page)
    if ($_SESSION['s_display_inactive_assets'] == '1') {
        $layout->addPageAction(_('Hide Inactive'), WEB_ROOT . '/settings/toggles/inactive-assets/?' . (new DomainMOD\Csrf())->query(), 'bi-eye-slash');
    } else {
        $layout->addPageAction(_('Show Inactive'), WEB_ROOT . '/settings/toggles/inactive-assets/?' . (new DomainMOD\Csrf())->query(), 'bi-eye');
    }
    $layout->addPageAction(_('Set Defaults'), WEB_ROOT . '/settings/defaults/', 'bi-person-gear');

    // The default account (see Settings, User Defaults) and reseller accounts as icons, with their meanings in a tooltip
    // and for screen readers
    // Each icon's wrapper holds a space before it, which keeps the hidden text apart from the name when the list is
    // searched or sorted. As an inline block, the wrapper doesn't show that space.
    $default_marker = '<span class="d-inline-block ms-2"> ' . $layout->statusBadge(htmlspecialchars(_('Default'), ENT_QUOTES, 'UTF-8'), _('Default'), 'warning', 'bi-star-fill') . '</span>';
    $reseller_marker = '<span class="d-inline-block ms-2"> ' . $layout->statusBadge(htmlspecialchars(_('Reseller'), ENT_QUOTES, 'UTF-8'), _('Reseller'), 'info', 'bi-shop') . '</span>';

    // The rows are buffered, so if every one is hidden as unused, the empty table gives way to an empty state
    $shown = 0;
    ob_start(); ?>
    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th><span class="visually-hidden"><?php echo _('Details'); ?></span></th>
            <th><?php echo _('Provider'); ?></th>
            <th><?php echo _('Account'); ?></th>
            <th><?php echo _('Owner'); ?></th>
            <th class="pe-3"><?php echo _('Services'); ?></th>
        </tr>
        </thead>

        <tbody><?php

        foreach ($result as $row) {

            $total_services = $count_services($row->hpaid);

            if ($total_services >= 1 || $_SESSION['s_display_inactive_assets'] == '1') {

                $shown++; ?>

                <tr>
                <td></td>
                <td>
                    <a href="edit/host.php?whid=<?php echo $row->whid; ?>"><?php echo $row->hname; ?></a>
                </td>
                <td>
                    <a href="edit/hosting-provider-account.php?hpaid=<?php echo $row->hpaid; ?>"><?php echo $row->username; ?></a><?php
                    if ((int) $row->hpaid === $default_account) echo $default_marker; ?><?php
                    if ($row->reseller == '1') echo $reseller_marker; ?>
                </td>
                <td>
                    <a href="edit/account-owner.php?oid=<?php echo $row->oid; ?>"><?php echo $row->oname; ?></a>
                </td>
                <td class="pe-3"><?php

                    if ($total_services >= 1) { ?>

                        <a href="../hosting/index.php?oid=<?php echo $row->oid; ?>&hid=<?php echo $row->whid; ?>&hpaid=<?php echo $row->hpaid; ?>"><?php echo number_format($total_services); ?></a><?php

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
        echo $layout->emptyState('bi-eye-slash', _('No hosting accounts are in use'), _('Unused hosting accounts are hidden. Choose Show Inactive to see them.'), '', '', '/settings/display/', _('Display Settings'));

    } else {

        ob_end_flush();

    }

} else {

    $result = $pdo->query("
        SELECT id
        FROM hosting
        LIMIT 1")->fetchAll();

    if (!$result) {

        echo $layout->emptyState('bi-server', _('Add a hosting provider first'), _('Hosting accounts belong to a hosting provider.'), '/assets/add/host.php', _('Add Hosting Provider'));

    } elseif (($whid !== 0 || $hpaid !== 0 || $oid !== 0) && $pdo->query("SELECT id FROM hosting_provider_accounts LIMIT 1")->fetchColumn()) {

        // Opened from a link that filters the list (such as one hosting provider's accounts), and nothing matches
        echo $layout->emptyState('bi-search', _('No hosting accounts match'), _('Try changing or clearing the filters.'), '', '', '/assets/hosting-accounts.php', _('Show all hosting accounts'));

    } else {

        echo $layout->emptyState('bi-person-badge', _('No hosting accounts yet'), _('Add the accounts you hold at your hosting providers.'));

    }

}
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
</body>
</html>
