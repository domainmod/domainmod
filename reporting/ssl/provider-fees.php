<?php
/**
 * /reporting/ssl/provider-fees.php
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
$reporting = new DomainMOD\Reporting();
$currency = new DomainMOD\Currency();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/reporting-ssl-fees.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

// The provider comparison that was here is now SSL Pricing, so an old link to it (which carries the type) opens
// SSL Pricing with that type
if (isset($_GET['type'])) {
    $type = is_string($_GET['type']) && ctype_digit($_GET['type']) ? $_GET['type'] : '';
    header('Location: ssl-pricing.php' . ($type !== '' ? '?' . http_build_query(array('type' => $type)) : '') . '#type-view', true, 302);
    exit;
}

$export_data = (int) ($_GET['export_data'] ?? 0);
$all = $_GET['all'] ?? '';

// Every SSL certificate type that has certificates without a fee, by provider, for the Missing Fees section
$missing_fees = $pdo->query("
    SELECT sslp.id, sslp.name AS ssl_provider, sslt.id AS type_id, sslt.type
    FROM ssl_certs AS sslc
    JOIN ssl_providers AS sslp ON sslc.ssl_provider_id = sslp.id
    JOIN ssl_cert_types AS sslt ON sslc.type_id = sslt.id
    WHERE sslc.fee_id = '0'
    GROUP BY sslp.id, sslp.name, sslt.id, sslt.type
    ORDER BY sslp.name, sslp.id, sslt.type")->fetchAll();

if ($all == "1") {

    $result = $pdo->query("
        SELECT sslp.id, sslp.name AS ssl_provider, sslt.id AS type_id, sslt.type, f.id AS fee_id, f.initial_fee,
            f.renewal_fee, f.misc_fee, f.insert_time, f.update_time, c.currency, c.symbol, c.symbol_order,
            c.symbol_space
        FROM ssl_providers AS sslp, ssl_fees AS f, currencies AS c, ssl_cert_types AS sslt
        WHERE sslp.id = f.ssl_provider_id
          AND f.currency_id = c.id
          AND f.type_id = sslt.id
        GROUP BY sslp.name, sslt.type
        ORDER BY sslp.name, sslt.type")->fetchAll();

} else {

    $result = $pdo->query("
        SELECT sslp.id, sslp.name AS ssl_provider, sslt.id AS type_id, sslt.type, f.id AS fee_id, f.initial_fee,
            f.renewal_fee, f.misc_fee, f.insert_time, f.update_time, c.currency, c.symbol, c.symbol_order,
            c.symbol_space
        FROM ssl_providers AS sslp, ssl_certs AS sslc, ssl_fees AS f, currencies AS c, ssl_cert_types AS sslt
        WHERE sslp.id = sslc.ssl_provider_id
          AND sslc.fee_id = f.id
          AND f.currency_id = c.id
          AND sslc.type_id = sslt.id
          AND sslc.active NOT IN ('0')
        GROUP BY sslp.name, sslt.type
        ORDER BY sslp.name, sslt.type")->fetchAll();

}

$total_rows = count($result);

if ($total_rows > 0) {

    if ($export_data === 1) {

        $export = new DomainMOD\Export();

        if ($all == "1") {

            $export_file = $export->openFile(_('ssl_provider_fee_report_all'), strtotime($time->stamp()));

        } else {

            $export_file = $export->openFile(_('ssl_provider_fee_report_active'), strtotime($time->stamp()));

        }

        $row_contents = array($page_title);
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

        if ($all == "1") {

            $row_contents = array(_('All SSL Provider Fees'));

        } else {

            $row_contents = array(_('Active SSL Provider Fees'));

        }
        $export->writeRow($export_file, $row_contents);

        $export->writeBlankRow($export_file);

        $row_contents = array(
            _('Provider'),
            _('Type'),
            _('Initial'),
            _('Renewal'),
            _('Misc'),
            _('Currency'),
            _('SSL Certificates'),
            _('Inserted'),
            _('Updated')
        );
        $export->writeRow($export_file, $row_contents);

        $new_ssl_provider = "";
        $last_ssl_provider = "";
        $new_type = "";
        $last_type = "";

        if ($result) {

            foreach ($result as $row) {

                $new_ssl_provider = $row->ssl_provider;
                $new_type = $row->type;

                $row->initial_fee = $currency->format($row->initial_fee, $row->symbol, $row->symbol_order,
                    $row->symbol_space);

                $row->renewal_fee = $currency->format($row->renewal_fee, $row->symbol, $row->symbol_order,
                    $row->symbol_space);

                $row->misc_fee = $currency->format($row->misc_fee, $row->symbol, $row->symbol_order,
                    $row->symbol_space);

                unset($row_contents);
                $count = 0;

                $row_contents[$count++] = $row->ssl_provider;
                $row_contents[$count++] = $row->type;
                $row_contents[$count++] = $row->initial_fee;
                $row_contents[$count++] = $row->renewal_fee;
                $row_contents[$count++] = $row->misc_fee;
                $row_contents[$count++] = $row->currency;

                $row_contents[$count++] = $pdo->query("
                    SELECT count(*)
                    FROM ssl_certs
                    WHERE ssl_provider_id = '" . $row->id . "'
                      AND fee_id = '" . $row->fee_id . "'
                      AND active NOT IN ('0')")->fetchColumn();

                $row_contents[$count++] = $time->toUserTimezone($row->insert_time);
                $row_contents[$count++] = $time->toUserTimezone($row->update_time);
                $export->writeRow($export_file, $row_contents);

                $last_ssl_provider = $row->ssl_provider;
                $last_type = $row->type;

            }

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

// Users who can edit fees get links to them: a provider's name to its fees, and each type to its fee (or, when it's
// missing, to Add SSL Provider Fee for it)
$can_edit = ($_SESSION['s_read_only'] ?? '') != '1';

// A provider's name, muted after its first row
$provider_name = function ($row, $first) use ($can_edit) {
    $class = $first ? '' : ' class="text-body-secondary"';
    if (!$can_edit) return '<span' . $class . '>' . $row->ssl_provider . '</span>';
    return '<a href="../../assets/ssl-provider-fees.php?sslpid=' . (int) $row->id . '"' . $class . '>' . $row->ssl_provider . '</a>';
};

if ($missing_fees) {

    $missing_by_provider = array();
    foreach ($missing_fees as $row) {
        $missing_by_provider[$row->id]['row'] = $row;
        $missing_by_provider[$row->id]['types'][] = $can_edit
            ? '<a href="../../assets/add/ssl-provider-fee.php?sslpid=' . (int) $row->id . '&amp;type_id=' . (int) $row->type_id . '&amp;return=missing">' . $row->type . '</a>'
            : $row->type;
    } ?>

    <h2 class="h4" id="missing"><?php echo _('Missing Fees'); ?></h2>
    <p><?php echo _('Some SSL certificates have no fee yet, so they can\'t be costed and are hidden from the SSL Certificates list. Each type below needs a fee at its provider.'); ?></p>
    <div class="table-responsive mb-4"><table class="table table-sm table-striped mb-0">
        <thead>
        <tr>
            <th scope="col"><?php echo _('Provider'); ?></th>
            <th scope="col"><?php echo _('Missing SSL Type Fees'); ?></th>
        </tr>
        </thead>
        <tbody><?php
        foreach ($missing_by_provider as $missing) { ?>

            <tr>
                <td><?php echo $provider_name($missing['row'], true); ?></td>
                <td><?php echo implode(', ', $missing['types']); ?></td>
            </tr><?php

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
            <th><?php echo _('Type'); ?></th>
            <th><?php echo _('Initial'); ?></th>
            <th><?php echo _('Renewal'); ?></th>
            <th><?php echo _('Misc'); ?></th>
            <th><?php echo _('Currency'); ?></th>
            <th><?php echo _('SSL Certificates'); ?></th>
            <th><?php echo _('Last Updated'); ?></th>
        </tr>
        </thead>
        <tbody><?php

        $last_ssl_provider = "";

        foreach ($result as $row) {

            if (DomainMOD\Time::isUnset($row->update_time)) {
                $row->update_time = $row->insert_time;
            }
            if (DomainMOD\Time::isUnset($row->update_time)) {
                $last_updated = '';
            } else {
                $last_updated = $time->toUserTimezone(date('Y-m-d', strtotime($row->update_time)));
            }

            // Every row names its provider, so a row collapsed on a phone still says whose fee it is, and the rows
            // after a provider's first are muted, so its fees still read as a group
            $first_of_provider = $row->ssl_provider != $last_ssl_provider || $row->ssl_provider == ""; ?>

            <tr>
                <td></td>
                <td><?php echo $provider_name($row, $first_of_provider); ?></td>
                <td><?php
                    if ($can_edit) { ?>
                        <a href="../../assets/edit/ssl-provider-fee.php?sslpid=<?php echo (int) $row->id; ?>&amp;fee_id=<?php echo (int) $row->fee_id; ?>"><?php echo $row->type; ?></a><?php
                    } else {
                        echo $row->type;
                    } ?>
                </td>
                <td><?php echo $currency->format($row->initial_fee, $row->symbol, $row->symbol_order, $row->symbol_space); ?></td>
                <td><?php echo $currency->format($row->renewal_fee, $row->symbol, $row->symbol_order, $row->symbol_space); ?></td>
                <td><?php echo $currency->format($row->misc_fee, $row->symbol, $row->symbol_order, $row->symbol_space); ?></td>
                <td><?php echo $row->currency; ?></td>
                <td>
                    <?php
                    $result_ssl_count = $pdo->query("
                        SELECT count(*)
                        FROM ssl_certs
                        WHERE ssl_provider_id = '" . $row->id . "'
                          AND fee_id = '" . $row->fee_id . "'
                          AND active NOT IN ('0')")->fetchColumn();

                    if (!$result_ssl_count) {

                        echo "-";

                    } else {

                        echo "<a href=\"../../ssl/index.php?sslpid=" . $row->id .
                            "&ssltid=" . $row->type_id . "\">" . $result_ssl_count . "</a>";

                    } ?>
                </td>
                <td><?php echo $last_updated; ?></td>
            </tr><?php

            $last_ssl_provider = $row->ssl_provider;

        } ?>

        </tbody>
    </table><?php

} elseif ($all == "1" || (int) $pdo->query("SELECT count(*) FROM ssl_fees")->fetchColumn() === 0) {

    // No fees at all reads as "none yet" in either view, since "none in use" would suggest there are some

    echo $layout->emptyState('bi-shield-lock', _('No SSL provider fees yet'), _('Add fees for your SSL providers, and this report lists them.'), '', '', '/assets/ssl-providers.php', _('SSL Providers'));

} else {

    echo $layout->emptyState('bi-shield-lock', _('No fees in use'), _('None of your SSL provider fees are used by a live SSL certificate.'), '', '', 'provider-fees.php?all=1', _('View All'));

} ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
