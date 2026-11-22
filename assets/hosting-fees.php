<?php
/**
 * /assets/hosting-fees.php
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
$assets = new DomainMOD\Assets();
$currency = new DomainMOD\Currency();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-hosting-fees.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

// Before the database update there are no hosting fees to list
if (!DomainMOD\Hosting::isAvailable($pdo)) {

    $_SESSION['s_message_danger'] .= DomainMOD\Hosting::unavailableMessage() . '<BR>';
    header("Location: index.php");
    exit;

}

// An ID sent as anything but digits (such as an array, which an (int) cast would turn into 1) counts as 0, which
// finds nothing
$hosting_id = is_string($_GET['hosting_id'] ?? null) && ctype_digit($_GET['hosting_id']) ? (int) $_GET['hosting_id'] : 0;
$export_data = is_string($_GET['export_data'] ?? null) && ctype_digit($_GET['export_data'])
    ? (int) $_GET['export_data'] : 0;

// The fees listed are this hosting provider's, so it has to exist (a bad link, or a provider deleted since the link
// was made, sends the user back to the provider list)
$stmt = $pdo->prepare("
    SELECT id
    FROM hosting
    WHERE id = :hosting_id");
$stmt->bindValue('hosting_id', $hosting_id, PDO::PARAM_INT);
$stmt->execute();
$provider_found = $stmt->fetchColumn() !== false;
$stmt->closeCursor();

if (!$provider_found) {

    $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . _('That hosting provider no longer exists.') . '<BR>';
    header("Location: hosting.php");
    exit;

}

$provider_name = $assets->getHost($hosting_id);

// The saved fees, one row per fee (each is one service type and billing term at this provider)
$stmt = $pdo->prepare("
    SELECT f.id, f.billing_term, f.initial_fee, f.renewal_fee, f.misc_fee, f.insert_time, f.update_time, t.type, c.currency, c.symbol, c.symbol_order, c.symbol_space
    FROM hosting_fees AS f, hosting_types AS t, currencies AS c
    WHERE f.currency_id = c.id
      AND f.type_id = t.id
      AND f.hosting_id = :hosting_id
    ORDER BY t.type ASC, f.billing_term ASC");
$stmt->bindValue('hosting_id', $hosting_id, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetchAll();

// The missing fees, one row per service type and billing term that this provider's hosting services use without a fee
$stmt = $pdo->prepare("
    SELECT t.id, t.type, hs.billing_term, COUNT(*) AS total_services
    FROM hosting_services AS hs, hosting_types AS t
    WHERE hs.type_id = t.id
      AND hs.hosting_id = :hosting_id
      AND hs.fee_id = '0'
    GROUP BY t.id, t.type, hs.billing_term
    ORDER BY t.type ASC, hs.billing_term ASC");
$stmt->bindValue('hosting_id', $hosting_id, PDO::PARAM_INT);
$stmt->execute();
$missing = $stmt->fetchAll();

if ($export_data === 1) {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('hosting_fee_list'), strtotime($time->stamp()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        _('Status'),
        _('Hosting Provider'),
        _('Service Type'),
        _('Billing Term'),
        _('Initial Fee'),
        _('Renewal Fee'),
        _('Misc Fee'),
        _('Currency'),
        _('Inserted'),
        _('Updated')
    );
    $export->writeRow($export_file, $row_contents);

    foreach ($result as $row) {

        $row_contents = array(
            _('Saved'),
            $provider_name,
            $row->type,
            DomainMOD\Hosting::termLabel($row->billing_term),
            $row->initial_fee,
            $row->renewal_fee,
            $row->misc_fee,
            $row->currency,
            $time->toUserTimezone($row->insert_time),
            $time->toUserTimezone($row->update_time)
        );
        $export->writeRow($export_file, $row_contents);

    }

    // Each missing fee as its own row, with no amounts
    foreach ($missing as $row) {

        $row_contents = array(
            _('Missing'),
            $provider_name,
            $row->type,
            DomainMOD\Hosting::termLabel($row->billing_term),
            '',
            '',
            '',
            '',
            '',
            ''
        );
        $export->writeRow($export_file, $row_contents);

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
<div class="domainmod-intro"><?php echo _('Below is a list of all the fees associated with'); ?> <a href="edit/host.php?whid=<?php echo urlencode($hosting_id); ?>"><?php echo $provider_name; ?></a>.</div>
<BR>
<?php
// The page's actions, drawn in the page header (see Layout::renderPageActions())
// Users who can edit fees get links to them: each missing fee to Add Fee for its service type and billing term, and each
// saved fee to its edit page (read-only users see them as plain text, as on the fee reports)
$can_edit = ($_SESSION['s_read_only'] ?? '') != '1';
$layout->setPageAddAction(_('Add Fee'), 'add/hosting-fee.php?hosting_id=' . urlencode($hosting_id));
$layout->addPageAction(_('Export'), 'hosting-fees.php?hosting_id=' . urlencode($hosting_id) . '&export_data=1', 'bi-download');

if ($missing) { ?>

    <h2 class="h4"><?php echo _('Missing Fees'); ?></h2>
    <p><?php // The count isn't shown, so it picks between the two sentences itself (ngettext()'s plural forms follow a shown number)
    echo sprintf(count($missing) === 1
        ? _('Some of your hosting services with %s have no fee yet. The combination of service type and billing term below needs one, so add this fee as soon as possible.')
        : _('Some of your hosting services with %s have no fee yet. Each combination of service type and billing term below needs one, so add these fees as soon as possible.'),
        $provider_name); ?></p>
    <table class="table table-sm w-auto mb-5">
        <thead>
        <tr>
            <th><?php echo _('Service Type'); ?></th>
            <th><?php echo _('Billing Term'); ?></th>
            <th class="text-end"><?php echo _('Hosting Services'); ?></th>
        </tr>
        </thead>
        <tbody><?php

        foreach ($missing as $row) { ?>

            <tr>
            <td><?php
                if ($can_edit) { ?>
                    <a href="add/hosting-fee.php?hosting_id=<?php echo urlencode($hosting_id); ?>&type_id=<?php echo urlencode($row->id); ?>&billing_term=<?php echo urlencode($row->billing_term); ?>"><?php echo $row->type; ?></a><?php
                } else {
                    echo $row->type;
                } ?>
            </td>
            <td><?php echo htmlspecialchars(DomainMOD\Hosting::termLabel($row->billing_term), ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="text-end"><?php echo number_format($row->total_services); ?></td>
            </tr><?php

        } ?>

        </tbody>
    </table><?php

}

if (!$result) {

    echo $layout->emptyState('bi-cash-coin', _('No fees for this hosting provider yet'), sprintf(_('Add a fee for each service type and billing term so %s can work out costs.'), SOFTWARE_TITLE));

} else {

    // Each fee cell sorts by its amount (a dash, for no fee, as 0), so every fee column sorts as numbers, even one
    // with no fees in it, and the billing term sorts by its number of months ?>
    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th><span class="visually-hidden"><?php echo _('Details'); ?></span></th>
            <th><?php echo _('Service Type'); ?></th>
            <th><?php echo _('Billing Term'); ?></th>
            <th class="text-end"><?php echo _('Initial Fee'); ?></th>
            <th class="text-end"><?php echo _('Renewal Fee'); ?></th>
            <th class="text-end"><?php echo _('Misc Fee'); ?></th>
            <th class="ps-4"><?php echo _('Currency'); ?></th>
        </tr>
        </thead>
        <tbody><?php

        foreach ($result as $row) { ?>

            <tr>
            <td></td>
            <td>
                <?php
                if ($can_edit) { ?>
                    <a href="edit/hosting-fee.php?hosting_id=<?php echo urlencode($hosting_id); ?>&fee_id=<?php echo urlencode($row->id); ?>"><?php echo $row->type; ?></a><?php
                } else {
                    echo $row->type;
                } ?>
            </td>
            <td data-order="<?php echo (int) $row->billing_term; ?>"><?php echo htmlspecialchars(DomainMOD\Hosting::termLabel($row->billing_term), ENT_QUOTES, 'UTF-8'); ?></td>
            <td class="text-end" data-order="<?php echo (float) $row->initial_fee; ?>"><?php
                if ($row->initial_fee > 0) {

                    echo $currency->format($row->initial_fee, $row->symbol, $row->symbol_order, $row->symbol_space);

                } else {

                    echo '-';

                }?>
            </td>
            <td class="text-end" data-order="<?php echo (float) $row->renewal_fee; ?>">
                <?php
                if ($row->renewal_fee > 0) {

                    echo $currency->format($row->renewal_fee, $row->symbol, $row->symbol_order, $row->symbol_space);

                } else {

                    echo '-';

                }?>
            </td>
            <td class="text-end" data-order="<?php echo (float) $row->misc_fee; ?>">
                <?php
                if ($row->misc_fee > 0) {

                    echo $currency->format($row->misc_fee, $row->symbol, $row->symbol_order, $row->symbol_space);

                } else {

                    echo '-';

                }?>
            </td>
            <td class="ps-4">
                <?php echo $row->currency; ?>
            </td>
            </tr><?php

        } ?>

        </tbody>
    </table><?php

}
require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
</body>
</html>
