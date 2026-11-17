<?php
/**
 * /reporting/ssl/ssl-pricing.php
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
$chart = new DomainMOD\Chart();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/reporting-ssl-pricing.inc.php';

$system->authCheck();
// Rates for every currency a fee uses, or the comparisons below would leave out the fees that use a missing one
(new DomainMOD\Conversion())->ensureRates($_SESSION['s_user_id'] ?? 0, $_SESSION['s_default_currency'] ?? '');

$export_data = (int) ($_GET['export_data'] ?? 0);

$prices = $reporting->sslPrices();
$overview = $reporting->sslOverview($prices);

// The certificate type to compare: one from the overview (the one with the most certificates when none is asked for,
// and none when an unknown one is asked for), and the price type: renewal or initial (anything else shows renewal)
$type_ids = array_column($overview['rows'], 'type_id');
if (isset($_GET['type'])) {
    $type_id = is_string($_GET['type']) && ctype_digit($_GET['type']) && in_array((int) $_GET['type'], $type_ids, true) ? (int) $_GET['type'] : 0;
} else {
    $type_id = $type_ids[0] ?? 0;
}
$price_type = $_GET['price'] ?? 'renewal';
if (!is_string($price_type) || !in_array($price_type, array('renewal', 'initial'), true)) $price_type = 'renewal';

$price_names = array('renewal' => _('Renewal'), 'initial' => _('Initial'));

// The comparison's htmx requests get this whole page, from which htmx takes just the comparison. X-DomainMOD-Result
// marks it as a real result for the footer's script, so a redirect (such as a lapsed session's) is never swapped in,
// and HX-Push-Url puts the comparison's own address in the address bar.
if ($system->isHtmxRequest()) {
    header('X-DomainMOD-Result: 1');
    header('HX-Push-Url: ' . WEB_ROOT . '/reporting/ssl/ssl-pricing.php?' . http_build_query(array_filter(array('type' => $type_id, 'price' => $price_type))));
}

$selected = null;
if ($type_id !== 0) {
    foreach ($overview['rows'] as $row) {
        if ($row['type_id'] === $type_id) $selected = $reporting->sslRow($type_id, $row['fees'], $row['certs'], $price_type);
    }
}

// The user's active certificates at each provider, per type, for the selected type's table
$certs_at = array();
foreach ($prices['certs'] as $row) {
    $certs_at[(int) $row->type_id][(int) $row->ssl_provider_id] = (int) $row->certs;
}

// An amount in the user's currency, as the other reports show it
$money = function ($amount) use ($currency) {
    return $currency->format($amount, $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);
};

$provider_names = function ($row, $indexes) {
    return implode(', ', array_map(function ($index) use ($row) { return $row['fees'][$index]->ssl_provider_name; }, $indexes));
};

$intro = _('Which of your SSL providers is cheapest and most expensive for each certificate type. It only uses the fees you\'ve entered.');
$prices_note = _('The certificate types use each provider\'s renewal fee plus its misc fee, in your currency, and the type comparison uses whichever fee you pick there, plus the misc fee.');

$query_string = function ($extra) use ($type_id, $price_type) {
    $parts = array();
    if ($type_id !== 0) $parts['type'] = $type_id;
    $parts['price'] = $price_type;
    return http_build_query(array_merge($parts, $extra));
};

if ($export_data === 1 && $overview['rows']) {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('ssl_pricing_report'), strtotime($time->stamp()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array($prices_note);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(_('Certificate Types'));
    $export->writeRow($export_file, $row_contents);

    $row_contents = array(
        _('Certificate Type'),
        _('Your Certificates'),
        _('Cheapest'),
        _('Cheapest Price'),
        _('Most Expensive'),
        _('Most Expensive Price'),
        _('Spread'),
        _('Providers')
    );
    $export->writeRow($export_file, $row_contents);

    foreach ($overview['rows'] as $row) {
        $row_contents = array(
            $row['type'],
            $row['certs'],
            $provider_names($row, $row['cheapest']),
            $money($row['low']),
            $provider_names($row, $row['most_expensive']),
            $money($row['high']),
            $row['providers'] > 1 ? $money($row['spread']) : '',
            $row['providers']
        );
        $export->writeRow($export_file, $row_contents);
    }

    if ($selected) {

        $export->writeBlankRow($export_file);

        $row_contents = array(sprintf(_('Providers for %s'), $selected['type']));
        $export->writeRow($export_file, $row_contents);

        $row_contents = array(
            _('Provider'),
            _('Renewal'),
            _('Initial'),
            _('Misc'),
            _('Fee Currency'),
            _('Your Certificates')
        );
        $export->writeRow($export_file, $row_contents);

        foreach ($selected['fees'] as $fee) {
            $row_contents = array(
                $fee->ssl_provider_name,
                $money($fee->converted['renewal']),
                $money($fee->converted['initial']),
                $money($fee->converted['misc']),
                $fee->currency,
                $certs_at[$selected['type_id']][(int) $fee->ssl_provider_id] ?? 0
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
<?php echo $layout->calloutTop(); ?>
<p><?php echo $intro; ?></p>
<p class="mb-0"><?php echo $prices_note; ?></p>
<?php echo $layout->calloutBottom(); ?>

<?php
// The page's actions, drawn in the page header (see Layout::renderPageActions())
// Export keeps its id, since the in-place comparison below swaps it for one with the new filters (hx-select-oob)
$layout->addPageAction(_('View Full Report'), $report_filename, 'bi-arrow-counterclockwise');
if ($overview['rows']) $layout->addPageAction(_('Export Report'), $report_filename . '?' . $query_string(array('export_data' => 1)), 'bi-download', array('id' => 'ssl-pricing-export'));

if (!$overview['rows']) {

    echo $layout->emptyState('bi-shield-lock', _('No SSL fees to compare yet'), _('Add fees for your SSL providers, and this report compares their prices for each certificate type.'), '', '', '/assets/ssl-providers.php', _('SSL Providers'));

    if ($overview['left_out'] > 0) { ?>
        <p class="small text-body-secondary"><?php echo sprintf(ngettext('%s fee is left out, since its currency has no conversion rate for you.', '%s fees are left out, since their currency has no conversion rate for you.', $overview['left_out']), number_format($overview['left_out'])); ?></p><?php
    }

} else { ?>

    <h2 class="h4" id="overview"><?php echo _('Certificate Types'); ?></h2><?php

    if ($overview['left_out'] > 0) { ?>
        <p class="small text-body-secondary"><?php echo sprintf(ngettext('%s fee is left out, since its currency has no conversion rate for you.', '%s fees are left out, since their currency has no conversion rate for you.', $overview['left_out']), number_format($overview['left_out'])); ?></p><?php
    } ?>

    <table id="<?php echo $slug; ?>-overview" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th><span class="visually-hidden"><?php echo _('Details'); ?></span></th>
            <th><?php echo _('Certificate Type'); ?></th>
            <th><?php echo _('Your Certificates'); ?></th>
            <th><?php echo _('Cheapest'); ?></th>
            <th><?php echo _('Most Expensive'); ?></th>
            <th><?php echo _('Spread'); ?></th>
            <th><?php echo _('Providers'); ?></th>
        </tr>
        </thead>
        <tbody><?php

        foreach ($overview['rows'] as $row) { ?>

            <tr>
                <td></td>
                <td><a href="<?php echo $report_filename; ?>?<?php echo htmlspecialchars(http_build_query(array('type' => $row['type_id'], 'price' => $price_type)), ENT_QUOTES, 'UTF-8'); ?>#type-view"><?php echo $row['type']; ?></a></td>
                <td data-order="<?php echo (int) $row['certs']; ?>"><?php echo number_format($row['certs']); ?></td>
                <td data-order="<?php echo $row['low']; ?>"><?php echo $provider_names($row, $row['cheapest']); ?> (<?php echo $money($row['low']); ?>)</td>
                <td data-order="<?php echo $row['high']; ?>"><?php
                    echo $provider_names($row, $row['most_expensive']); ?> (<?php echo $money($row['high']); ?>)<?php
                    if ($row['providers'] < 2) { ?> <span class="small text-body-secondary"><?php echo _('Only one provider'); ?></span><?php } ?></td>
                <td data-order="<?php echo $row['providers'] > 1 ? round($row['spread'], 2) : -1; ?>"><?php echo $row['providers'] > 1 ? $money($row['spread']) : ''; ?></td>
                <td data-order="<?php echo (int) $row['providers']; ?>"><?php echo number_format($row['providers']); ?></td>
            </tr><?php

        } ?>

        </tbody>
    </table>

    <h2 class="h4 mt-5" id="type-view"><?php echo _('Compare a Certificate Type'); ?></h2><?php

    // The comparison updates in place with htmx, as TLD Pricing's does: the picker and the price links fetch this page
    // and swap in just this section, keeping the scroll position, and the address bar follows. Without JavaScript they
    // load the page as usual. The export button's link carries the type and price, so it's swapped too.
    $compare_htmx = 'hx-target="#ssl-compare" hx-select="#ssl-compare" hx-select-oob="#ssl-pricing-export" hx-swap="outerHTML" hx-sync="#ssl-compare:replace"'; ?>

    <div id="ssl-compare">

    <form method="get" action="<?php echo $report_filename; ?>" hx-get="<?php echo $report_filename; ?>" <?php echo $compare_htmx; ?> class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <label for="type" class="form-label fw-bold mb-0"><?php echo _('Certificate Type'); ?></label>
        <select name="type" id="type" class="form-select w-auto"><?php
            foreach ($overview['rows'] as $row) { ?>
                <option value="<?php echo (int) $row['type_id']; ?>"<?php echo $row['type_id'] === $type_id ? ' selected' : ''; ?>><?php echo $row['type']; ?></option><?php
            } ?>
        </select>
        <input type="hidden" name="price" value="<?php echo $price_type; ?>">
        <?php echo $layout->showButton('submit', _('Compare')); ?>
    </form><?php

    if ($selected) { ?>

        <div class="btn-group btn-group-sm mb-2" role="group" aria-label="<?php echo htmlspecialchars(_('Price'), ENT_QUOTES, 'UTF-8'); ?>"><?php
            foreach ($price_names as $key => $name) {
                $url = $report_filename . '?' . htmlspecialchars(http_build_query(array('type' => $type_id, 'price' => $key)), ENT_QUOTES, 'UTF-8');
                echo '<a href="' . $url . '" hx-get="' . $url . '" ' . $compare_htmx . ' class="btn ' . ($key === $price_type ? 'btn-danger active" aria-current="true"' : 'btn-outline-secondary"') . '>' . $name . '</a>';
            } ?>
        </div>
        <?php // TRANSLATORS: %1$s is an SSL certificate type, such as "Wildcard", and %2$s is a price type, such as "Renewal". ?>
        <p class="small text-body-secondary" data-domainmod-focus><?php echo sprintf(_('Showing each provider\'s price for %1$s: %2$s plus misc.'), $selected['type'], $price_names[$price_type]); ?></p><?php

        // TRANSLATORS: The chart's title. %1$s is a price type, such as "Renewal", and %2$s is an SSL certificate type, such as "Wildcard".
        echo $chart->priceBars('ssl-pricing-chart', sprintf(_('%1$s Price by Provider for %2$s'), $price_names[$price_type], html_entity_decode($selected['type'], ENT_QUOTES, 'UTF-8')),
            array_map(function ($fee) { return $fee->ssl_provider_name; }, $selected['fees']),
            array_map(function ($fee) use ($price_type) { return round($fee->price[$price_type], 2); }, $selected['fees']),
            $selected['cheapest'], '', 3); ?>

        <p class="small text-body-secondary d-md-none mt-3 mb-1"><?php echo _('Scroll sideways to see every column.'); ?></p>
        <div class="table-responsive domainmod-scroll-table mt-1 mt-md-3" tabindex="0" role="region" aria-labelledby="ssl-compare-caption"><table class="table table-sm table-striped mb-0">
            <?php // TRANSLATORS: The hidden caption of SSL Pricing's comparison table. %s is an SSL certificate type, such as "Wildcard". ?>
            <caption id="ssl-compare-caption" class="visually-hidden"><?php echo sprintf(_('Providers for %s'), $selected['type']); ?></caption>
            <thead>
            <tr>
                <th scope="col"><?php echo _('Provider'); ?></th>
                <th scope="col" class="text-end"><?php echo $price_names[$price_type] . ' + ' . _('Misc'); ?></th>
                <th scope="col" class="text-end"><?php echo _('Renewal'); ?></th>
                <th scope="col" class="text-end"><?php echo _('Initial'); ?></th>
                <th scope="col" class="text-end"><?php echo _('Misc'); ?></th>
                <th scope="col"><?php echo _('Fee Currency'); ?></th>
                <th scope="col" class="text-end"><?php echo _('Your Certificates'); ?></th>
            </tr>
            </thead>
            <tbody><?php

            foreach ($selected['fees'] as $index => $fee) { ?>

                <tr>
                    <td><?php echo $fee->ssl_provider_name; ?><?php
                        if ($selected['providers'] > 1 && in_array($index, $selected['cheapest'], true)) {
                            echo ' ' . $layout->statusBadge(_('Cheapest'), _('The lowest price for this certificate type among your providers'), 'success');
                        }
                        if ($selected['providers'] > 1 && in_array($index, $selected['most_expensive'], true)) {
                            echo ' ' . $layout->statusBadge(_('Most Expensive'), _('The highest price for this certificate type among your providers'), 'secondary');
                        } ?></td>
                    <td class="text-end"><?php echo $money($fee->price[$price_type]); ?></td>
                    <td class="text-end"><?php echo $money($fee->converted['renewal']); ?></td>
                    <td class="text-end"><?php echo $money($fee->converted['initial']); ?></td>
                    <td class="text-end"><?php echo $money($fee->converted['misc']); ?></td>
                    <td><?php echo $fee->currency; ?></td>
                    <td class="text-end"><?php echo number_format($certs_at[$selected['type_id']][(int) $fee->ssl_provider_id] ?? 0); ?></td>
                </tr><?php

            } ?>

            </tbody>
        </table></div><?php

        if ($selected['providers'] < 2) { ?>
            <p class="small text-body-secondary"><?php echo _('Only one of your providers has a fee for this certificate type, so there\'s nothing to compare it with.'); ?></p><?php
        }

    } ?>

    </div><?php

}
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
<?php require_once DIR_INC . '/layout/chart-footer.inc.php'; ?>
</body>
</html>
