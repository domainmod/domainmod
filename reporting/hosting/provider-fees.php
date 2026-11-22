<?php
/**
 * /reporting/hosting/provider-fees.php
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
<?php
require_once __DIR__ . '/../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$layout = new DomainMOD\Layout;
$time = new DomainMOD\Time();
$currency = new DomainMOD\Currency();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/reporting-hosting-fees.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

// Nothing to report before the database update adds the hosting tables
if (!DomainMOD\Hosting::isAvailable($pdo)) {
    require_once DIR_INC . '/layout/hosting-report-unavailable.inc.php';
    exit;
}

// Rates for every currency in use, as the other hosting reports load them
(new DomainMOD\Conversion())->ensureRates($_SESSION['s_user_id'] ?? 0, $_SESSION['s_default_currency'] ?? '');

$export_data = (int) ($_GET['export_data'] ?? 0);
$all = $_GET['all'] ?? '';
$all = is_string($all) ? $all : '';

/*
 * Every service type and billing term that has hosting services without a fee, by provider, for the Missing Fees
 * section. A fee is per provider, service type, and billing term, so a type missing both its monthly and yearly fees is
 * two rows.
 */
$missing_fees = $pdo->query("
    SELECT h.id, h.name AS provider_name, ht.id AS type_id, ht.type, hs.billing_term
    FROM hosting_services AS hs
    JOIN hosting AS h ON hs.hosting_id = h.id
    JOIN hosting_types AS ht ON hs.type_id = ht.id
    WHERE hs.fee_id = '0'
    GROUP BY h.id, h.name, ht.id, ht.type, hs.billing_term
    ORDER BY h.name, h.id, ht.type, ht.id, hs.billing_term")->fetchAll();

/*
 * The saved fees, one row per fee (grouping by provider and type, as the SSL report does, would merge a type's monthly
 * and yearly fees): every fee, or only those a hosting service that isn't Cancelled uses
 */
$result = $pdo->query("
    SELECT h.id, h.name AS provider_name, ht.id AS type_id, ht.type, f.id AS fee_id, f.billing_term, f.initial_fee,
        f.renewal_fee, f.misc_fee, f.insert_time, f.update_time, c.currency, c.symbol, c.symbol_order, c.symbol_space
    FROM hosting_fees AS f
    JOIN hosting AS h ON f.hosting_id = h.id
    JOIN hosting_types AS ht ON f.type_id = ht.id
    JOIN currencies AS c ON f.currency_id = c.id" . ($all == "1" ? "" : "
    WHERE EXISTS (
        SELECT 1
        FROM hosting_services AS hs
        WHERE hs.fee_id = f.id
          AND hs.active NOT IN ('" . DomainMOD\Hosting::CANCELLED . "'))") . "
    ORDER BY h.name, h.id, ht.type, ht.id, f.billing_term, f.id")->fetchAll();

// The number of hosting services (not Cancelled) using each fee, read once rather than once per row
$service_counts = array();
foreach ($pdo->query("
    SELECT fee_id, count(*) AS total
    FROM hosting_services
    WHERE fee_id != '0'
      AND active NOT IN ('" . DomainMOD\Hosting::CANCELLED . "')
    GROUP BY fee_id")->fetchAll() as $count_row) {
    $service_counts[(int) $count_row->fee_id] = (int) $count_row->total;
}

$total_rows = count($result);

// A billing term's label, or its number when it isn't an allowed term
$term_text = function ($term) {
    return DomainMOD\Hosting::validTerm((string) $term) ? DomainMOD\Hosting::termLabel($term) : (string) (int) $term;
};

if ($total_rows > 0) {

    if ($export_data === 1) {

        $export = new DomainMOD\Export();

        if ($all == "1") {

            $export_file = $export->openFile(_('hosting_fee_report_all'), strtotime($time->stamp()));

        } else {

            $export_file = $export->openFile(_('hosting_fee_report_active'), strtotime($time->stamp()));

        }

        $row_contents = array($page_title);
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

        if ($all == "1") {

            $row_contents = array(_('All Hosting Provider Fees'));

        } else {

            $row_contents = array(_('Active Hosting Provider Fees'));

        }
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

        $row_contents = array(
            _('Provider'),
            _('Service Type'),
            _('Billing Term'),
            _('Initial'),
            _('Renewal'),
            _('Misc'),
            _('Currency'),
            _('Hosting Services'),
            _('Inserted'),
            _('Updated')
        );
        $export->writeRow($export_file, $row_contents);

        foreach ($result as $row) {

            $row_contents = array(
                $row->provider_name,
                $row->type,
                $term_text($row->billing_term),
                $currency->format($row->initial_fee, $row->symbol, $row->symbol_order, $row->symbol_space),
                $currency->format($row->renewal_fee, $row->symbol, $row->symbol_order, $row->symbol_space),
                $currency->format($row->misc_fee, $row->symbol, $row->symbol_order, $row->symbol_space),
                $row->currency,
                $service_counts[(int) $row->fee_id] ?? 0,
                $time->toUserTimezone($row->insert_time),
                $time->toUserTimezone($row->update_time)
            );
            $export->writeRow($export_file, $row_contents);

        }
        $export->closeFile($export_file);

    }

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
<?php
// The page's actions, drawn in the page header (see Layout::renderPageActions())
$layout->addPageAction(_('View All'), 'provider-fees.php?all=1', 'bi-list-ul');
$layout->addPageAction(_('Active Only'), 'provider-fees.php?all=0', 'bi-funnel');
if ($total_rows > 0) $layout->addPageAction(_('Export Report'), 'provider-fees.php?export_data=1&all=' . urlencode($all), 'bi-download');

// Users who can edit fees get links to them: a provider's name to its fees, and each fee to its edit page (or, when
// it's missing, to Add Hosting Fee for that service type and term)
$can_edit = ($_SESSION['s_read_only'] ?? '') != '1';

// A provider's name, muted after its first row
$provider_name = function ($row, $first) use ($can_edit) {
    $class = $first ? '' : ' class="text-body-secondary"';
    if (!$can_edit) return '<span' . $class . '>' . $row->provider_name . '</span>';
    return '<a href="../../assets/hosting-fees.php?hosting_id=' . (int) $row->id . '"' . $class . '>' . $row->provider_name . '</a>';
};

if ($missing_fees) { ?>

    <h2 class="h4" id="missing"><?php echo _('Missing Fees'); ?></h2>
    <p><?php echo _('Some hosting services have no fee yet, so the Hosting list shows them with "No fee" and leaves them out of its totals. Each service type and billing term below needs a fee at its provider.'); ?></p>
    <div class="table-responsive mb-4"><table class="table table-sm table-striped mb-0">
        <thead>
        <tr>
            <th scope="col"><?php echo _('Provider'); ?></th>
            <th scope="col"><?php echo _('Service Type'); ?></th>
            <th scope="col"><?php echo _('Billing Term'); ?></th>
        </tr>
        </thead>
        <tbody><?php
        $last_provider = '';
        foreach ($missing_fees as $row) {
            $first_of_provider = $row->id != $last_provider;
            // A fee can only be added for an allowed term, so a stored term outside the list gets no link
            $term_link = $can_edit && DomainMOD\Hosting::validTerm((string) $row->billing_term); ?>

            <tr>
                <td><?php echo $provider_name($row, $first_of_provider); ?></td>
                <td><?php
                    if ($term_link) { ?>
                        <a href="../../assets/add/hosting-fee.php?hosting_id=<?php echo (int) $row->id; ?>&amp;type_id=<?php echo (int) $row->type_id; ?>&amp;billing_term=<?php echo (int) $row->billing_term; ?>&amp;return=missing"><?php echo $row->type; ?></a><?php
                    } else {
                        echo $row->type;
                    } ?></td>
                <td><?php echo htmlspecialchars($term_text($row->billing_term), ENT_QUOTES, 'UTF-8'); ?></td>
            </tr><?php

            $last_provider = $row->id;
        } ?>

        </tbody>
    </table></div><?php

}

if ($total_rows > 0) { ?>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th><span class="visually-hidden"><?php echo _('Details'); ?></span></th>
            <th><?php echo _('Provider'); ?></th>
            <th><?php echo _('Service Type'); ?></th>
            <th><?php echo _('Billing Term'); ?></th>
            <th><?php echo _('Initial'); ?></th>
            <th><?php echo _('Renewal'); ?></th>
            <th><?php echo _('Misc'); ?></th>
            <th><?php echo _('Currency'); ?></th>
            <th><?php echo _('Hosting Services'); ?></th>
            <th><?php echo _('Last Updated'); ?></th>
        </tr>
        </thead>
        <tbody><?php

        $last_provider = "";

        foreach ($result as $row) {

            $last_updated = $time->lastChangedDate($row->update_time, $row->insert_time);

            // Every row names its provider, so a row collapsed on a phone still says whose fee it is, and the rows
            // after a provider's first are muted, so its fees still read as a group
            $first_of_provider = $row->id != $last_provider;
            $service_count = $service_counts[(int) $row->fee_id] ?? 0; ?>

            <tr>
                <td></td>
                <td><?php echo $provider_name($row, $first_of_provider); ?></td>
                <td><?php
                    if ($can_edit) { ?>
                        <a href="../../assets/edit/hosting-fee.php?hosting_id=<?php echo (int) $row->id; ?>&amp;fee_id=<?php echo (int) $row->fee_id; ?>"><?php echo $row->type; ?></a><?php
                    } else {
                        echo $row->type;
                    } ?>
                </td>
                <td data-order="<?php echo (int) $row->billing_term; ?>"><?php echo htmlspecialchars($term_text($row->billing_term), ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo $currency->format($row->initial_fee, $row->symbol, $row->symbol_order, $row->symbol_space); ?></td>
                <td><?php echo $currency->format($row->renewal_fee, $row->symbol, $row->symbol_order, $row->symbol_space); ?></td>
                <td><?php echo $currency->format($row->misc_fee, $row->symbol, $row->symbol_order, $row->symbol_space); ?></td>
                <td><?php echo $row->currency; ?></td>
                <td><?php
                    // The count opens the list filtered by provider, service type, and billing term, so it shows
                    // those services and no others
                    if (!$service_count) {

                        echo "-";

                    } else {

                        echo '<a href="../../hosting/index.php?' . htmlspecialchars(http_build_query(array('hid' => (int) $row->id, 'htid' => (int) $row->type_id, 'hterm' => (int) $row->billing_term)), ENT_QUOTES, 'UTF-8') . '">' . number_format($service_count) . '</a>';

                    } ?>
                </td>
                <td><?php echo $last_updated; ?></td>
            </tr><?php

            $last_provider = $row->id;

        } ?>

        </tbody>
    </table><?php

} elseif ($all == "1" || (int) $pdo->query("SELECT count(*) FROM hosting_fees")->fetchColumn() === 0) {

    // No fees at all reads as "none yet" in either view, since "none in use" would suggest there are some

    echo $layout->emptyState('bi-hdd-stack', _('No hosting provider fees yet'), _('Add fees for your hosting providers, and this report lists them.'), '', '', '/assets/hosting.php', _('Hosting Providers'));

} else {

    echo $layout->emptyState('bi-hdd-stack', _('No fees in use'), _('None of your hosting provider fees are used by a hosting service that isn\'t cancelled.'), '', '', 'provider-fees.php?all=1', _('View All'));

} ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
