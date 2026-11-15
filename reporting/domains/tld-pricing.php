<?php
/**
 * /reporting/domains/tld-pricing.php
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
$dashboard = new DomainMOD\Dashboard();
$chart = new DomainMOD\Chart();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/reporting-domain-tld-pricing.inc.php';

$system->authCheck();
// Rates for every currency a fee uses, or the comparisons below would leave out the fees that use a missing one
(new DomainMOD\Conversion())->ensureRates($_SESSION['s_user_id'] ?? 0, $_SESSION['s_default_currency'] ?? '');

$export_data = (int) ($_GET['export_data'] ?? 0);

$prices = $reporting->tldPrices();
$overview = $reporting->tldOverview($prices);
$savings = $reporting->tldSavings($prices);

// The TLD to compare: one from the overview (the one with the most domains when none is asked for, and none when an
// unknown one is asked for), and the price type: renewal, initial, or transfer (anything else shows renewal)
$tlds = array_column($overview['rows'], 'tld');
if (isset($_GET['tld'])) {
    $tld = is_string($_GET['tld']) && in_array($_GET['tld'], $tlds, true) ? $_GET['tld'] : '';
} else {
    $tld = $tlds[0] ?? '';
}
$price_type = $_GET['price'] ?? 'renewal';
if (!is_string($price_type) || !in_array($price_type, array('renewal', 'initial', 'transfer'), true)) $price_type = 'renewal';

$price_names = array('renewal' => _('Renewal'), 'initial' => _('Initial'), 'transfer' => _('Transfer'));

// The comparison's htmx requests get this whole page, from which htmx takes just the comparison. X-DomainMOD-Result
// marks it as a real result for the footer's script, so a redirect (such as a lapsed session's) is never swapped in,
// and HX-Push-Url puts the comparison's own address in the address bar.
if ($system->isHtmxRequest()) {
    header('X-DomainMOD-Result: 1');
    header('HX-Push-Url: ' . WEB_ROOT . '/reporting/domains/tld-pricing.php?' . http_build_query(array_filter(array('tld' => $tld, 'price' => $price_type))));
}

$selected = null;
if ($tld !== '') {
    foreach ($overview['rows'] as $row) {
        if ($row['tld'] === $tld) $selected = $reporting->tldRow($tld, $row['fees'], $row['domains'], $price_type);
    }
}

// The user's live domains at each registrar, per TLD, for the selected TLD's table
$domains_at = array();
foreach ($prices['domains'] as $domain) {
    $domains_at[$domain->tld][$domain->registrar_id] = ($domains_at[$domain->tld][$domain->registrar_id] ?? 0) + 1;
}

$total_saving = array_sum(array_column($savings['rows'], 'saving'));

// An amount in the user's currency, as the other reports show it
$money = function ($amount) use ($currency) {
    return $currency->format($amount, $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);
};

$registrar_names = function ($row, $indexes) {
    return implode(', ', array_map(function ($index) use ($row) { return $row['fees'][$index]->registrar_name; }, $indexes));
};

$intro = _('Which of your registrars is cheapest and most expensive for each TLD, and how much you could save by moving domains to the cheapest registrar you already have fees for. It only uses the fees you\'ve entered.');
$prices_note = _('The TLDs and the savings use each registrar\'s renewal fee plus its misc fee, in your currency, and the TLD comparison uses whichever fee you pick there, plus the misc fee. Privacy is shown separately and counted in the savings only for domains with privacy on.');
$savings_note = _('Savings compare the fees you\'ve entered and leave out transfer fees. A registrar\'s prices can change, so check before moving a domain.');

$query_string = function ($extra) use ($tld, $price_type) {
    $parts = array();
    if ($tld !== '') $parts['tld'] = $tld;
    $parts['price'] = $price_type;
    return http_build_query(array_merge($parts, $extra));
};

if ($export_data === 1 && $overview['rows']) {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('tld_pricing_report'), strtotime($time->stamp()));

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array($prices_note);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(_('TLDs'));
    $export->writeRow($export_file, $row_contents);

    $row_contents = array(
        _('TLD'),
        _('Your Domains'),
        _('Cheapest'),
        _('Cheapest Price'),
        _('Most Expensive'),
        _('Most Expensive Price'),
        _('Spread'),
        _('Registrars')
    );
    $export->writeRow($export_file, $row_contents);

    foreach ($overview['rows'] as $row) {
        $row_contents = array(
            '.' . $row['tld'],
            $row['domains'],
            $registrar_names($row, $row['cheapest']),
            $money($row['low']),
            $registrar_names($row, $row['most_expensive']),
            $money($row['high']),
            $row['registrars'] > 1 ? $money($row['spread']) : '',
            $row['registrars']
        );
        $export->writeRow($export_file, $row_contents);
    }

    $export->writeBlankRow($export_file);

    $row_contents = array(_('Possible Savings'));
    $export->writeRow($export_file, $row_contents);

    $row_contents = array(_('Total Yearly Saving') . ':', $money($total_saving));
    $export->writeRow($export_file, $row_contents);

    $row_contents = array(
        _('Domain'),
        _('TLD'),
        _('Current Registrar'),
        _('Current Yearly Price'),
        _('Cheapest Registrar'),
        _('Cheapest Yearly Price'),
        _('Yearly Saving')
    );
    $export->writeRow($export_file, $row_contents);

    foreach ($savings['rows'] as $row) {
        $row_contents = array(
            $row['domain']->domain,
            '.' . $row['domain']->tld,
            $row['domain']->registrar_name,
            $money($row['current']),
            $row['destination']->registrar_name,
            $money($row['destination_cost']),
            $money($row['saving'])
        );
        $export->writeRow($export_file, $row_contents);
    }

    if ($selected) {

        $export->writeBlankRow($export_file);

        $row_contents = array(sprintf(_('Registrars for .%s'), $selected['tld']));
        $export->writeRow($export_file, $row_contents);

        $row_contents = array(
            _('Registrar'),
            _('Renewal'),
            _('Initial'),
            _('Transfer'),
            _('Privacy'),
            _('Misc'),
            _('Fee Currency'),
            _('Your Domains')
        );
        $export->writeRow($export_file, $row_contents);

        foreach ($selected['fees'] as $fee) {
            $row_contents = array(
                $fee->registrar_name,
                $money($fee->converted['renewal']),
                $money($fee->converted['initial']),
                $money($fee->converted['transfer']),
                $money($fee->converted['privacy']),
                $money($fee->converted['misc']),
                $fee->currency,
                $domains_at[$selected['tld']][$fee->registrar_id] ?? 0
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
<?php echo $intro; ?><BR><BR>
<?php echo $prices_note; ?>
<?php echo $layout->calloutBottom(); ?>

<?php
// The page's actions, drawn in the page header (see Layout::renderPageActions())
// Export keeps its id, since the in-place comparison below swaps it for one with the new filters (hx-select-oob)
$layout->addPageAction(_('View Full Report'), $report_filename, 'bi-arrow-counterclockwise');
if ($overview['rows']) $layout->addPageAction(_('Export Report'), $report_filename . '?' . $query_string(array('export_data' => 1)), 'bi-download', array('id' => 'tld-pricing-export'));

if (!$overview['rows']) {

    echo _('No results.') . '<BR>';

    if ($overview['left_out'] > 0) { ?>
        <p class="small text-body-secondary"><?php echo sprintf(ngettext('%s fee is left out, since its currency has no conversion rate for you.', '%s fees are left out, since their currency has no conversion rate for you.', $overview['left_out']), number_format($overview['left_out'])); ?></p><?php
    }

} else { ?>

    <div class="row"><?php
        echo $dashboard->displayPanel(_('Possible Yearly Savings'), count($savings['rows']), 'success', 'bi-piggy-bank', '/reporting/domains/tld-pricing.php?' . $query_string(array()) . '#savings', _('TLD Pricing'), $money($total_saving));
        echo $dashboard->displayPanel(_('TLDs Compared'), $savings['tlds_compared'], 'secondary', 'bi-tags', '/reporting/domains/tld-pricing.php?' . $query_string(array()) . '#overview', _('TLD Pricing')); ?>
    </div>

    <h2 class="h4 mt-4" id="overview"><?php echo _('TLDs'); ?></h2><?php

    if ($overview['left_out'] > 0) { ?>
        <p class="small text-body-secondary"><?php echo sprintf(ngettext('%s fee is left out, since its currency has no conversion rate for you.', '%s fees are left out, since their currency has no conversion rate for you.', $overview['left_out']), number_format($overview['left_out'])); ?></p><?php
    } ?>

    <table id="<?php echo $slug; ?>-overview" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th><?php echo _('TLD'); ?></th>
            <th><?php echo _('Your Domains'); ?></th>
            <th><?php echo _('Cheapest'); ?></th>
            <th><?php echo _('Most Expensive'); ?></th>
            <th><?php echo _('Spread'); ?></th>
            <th><?php echo _('Registrars'); ?></th>
        </tr>
        </thead>
        <tbody><?php

        foreach ($overview['rows'] as $row) { ?>

            <tr>
                <td></td>
                <td><a href="<?php echo $report_filename; ?>?<?php echo htmlspecialchars(http_build_query(array('tld' => $row['tld'], 'price' => $price_type)), ENT_QUOTES, 'UTF-8'); ?>#tld-view">.<?php echo $row['tld']; ?></a></td>
                <td data-order="<?php echo (int) $row['domains']; ?>"><?php echo number_format($row['domains']); ?></td>
                <td data-order="<?php echo $row['low']; ?>"><?php echo $registrar_names($row, $row['cheapest']); ?> (<?php echo $money($row['low']); ?>)</td>
                <td data-order="<?php echo $row['high']; ?>"><?php
                    if ($row['registrars'] > 1) {
                        echo $registrar_names($row, $row['most_expensive']); ?> (<?php echo $money($row['high']); ?>)<?php
                    } else {
                        echo $registrar_names($row, $row['most_expensive']); ?> (<?php echo $money($row['high']); ?>) <span class="small text-body-secondary"><?php echo _('Only one registrar'); ?></span><?php
                    } ?></td>
                <td data-order="<?php echo $row['registrars'] > 1 ? round($row['spread'], 2) : -1; ?>"><?php echo $row['registrars'] > 1 ? $money($row['spread']) : ''; ?></td>
                <td data-order="<?php echo (int) $row['registrars']; ?>"><?php echo number_format($row['registrars']); ?></td>
            </tr><?php

        } ?>

        </tbody>
    </table>

    <h2 class="h4 mt-5" id="tld-view"><?php echo _('Compare a TLD'); ?></h2><?php

    // The comparison updates in place with htmx: the picker and the price links fetch this page and swap in just this
    // section, keeping the scroll position, and the address bar follows. Without JavaScript they load the page as
    // usual. The export button's link carries the TLD and price, so it's swapped too.
    $compare_htmx = 'hx-target="#tld-compare" hx-select="#tld-compare" hx-select-oob="#tld-pricing-export" hx-swap="outerHTML" hx-sync="#tld-compare:replace"'; ?>

    <div id="tld-compare">

    <form method="get" action="<?php echo $report_filename; ?>" hx-get="<?php echo $report_filename; ?>" <?php echo $compare_htmx; ?> class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <label for="tld" class="form-label fw-bold mb-0"><?php echo _('TLD'); ?></label>
        <select name="tld" id="tld" class="form-select w-auto"><?php
            foreach ($tlds as $option) { ?>
                <option value="<?php echo htmlspecialchars($option, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $option === $tld ? ' selected' : ''; ?>>.<?php echo $option; ?></option><?php
            } ?>
        </select>
        <input type="hidden" name="price" value="<?php echo $price_type; ?>">
        <?php echo $layout->showButton('submit', _('Compare')); ?>
    </form><?php

    if ($selected) { ?>

        <p class="mb-1"><?php
            $links = array();
            foreach ($price_names as $key => $name) {
                $url = $report_filename . '?' . htmlspecialchars(http_build_query(array('tld' => $tld, 'price' => $key)), ENT_QUOTES, 'UTF-8');
                $links[] = $key === $price_type
                    ? '<strong>' . $name . '</strong>'
                    : '<a href="' . $url . '" hx-get="' . $url . '" ' . $compare_htmx . '>' . $name . '</a>';
            }
            echo _('Price') . ': ' . implode(' | ', $links); ?>
        </p>
        <p class="small text-body-secondary" data-domainmod-focus><?php echo sprintf(_('Showing each registrar\'s price for .%1$s: %2$s plus misc.'), $selected['tld'], $price_names[$price_type]); ?></p><?php

        echo $chart->priceBars('tld-pricing-chart', sprintf(_('%1$s Price by Registrar for .%2$s'), $price_names[$price_type], html_entity_decode($selected['tld'], ENT_QUOTES, 'UTF-8')),
            array_map(function ($fee) { return $fee->registrar_name; }, $selected['fees']),
            array_map(function ($fee) use ($price_type) { return round($fee->price[$price_type], 2); }, $selected['fees']),
            $selected['cheapest'], '', 3); ?>

        <div class="table-responsive"><table class="table table-sm table-striped mt-3">
            <thead>
            <tr>
                <th scope="col"><?php echo _('Registrar'); ?></th>
                <th scope="col" class="text-end"><?php echo $price_names[$price_type] . ' + ' . _('Misc'); ?></th>
                <th scope="col" class="text-end"><?php echo _('Renewal'); ?></th>
                <th scope="col" class="text-end"><?php echo _('Initial'); ?></th>
                <th scope="col" class="text-end"><?php echo _('Transfer'); ?></th>
                <th scope="col" class="text-end"><?php echo _('Privacy'); ?></th>
                <th scope="col" class="text-end"><?php echo _('Misc'); ?></th>
                <th scope="col"><?php echo _('Fee Currency'); ?></th>
                <th scope="col" class="text-end"><?php echo _('Your Domains'); ?></th>
            </tr>
            </thead>
            <tbody><?php

            foreach ($selected['fees'] as $index => $fee) { ?>

                <tr>
                    <td><?php echo $fee->registrar_name; ?><?php
                        if ($selected['registrars'] > 1 && in_array($index, $selected['cheapest'], true)) {
                            echo ' ' . $layout->statusBadge(_('Cheapest'), _('The lowest price for this TLD among your registrars'), 'success');
                        }
                        if ($selected['registrars'] > 1 && in_array($index, $selected['most_expensive'], true)) {
                            echo ' ' . $layout->statusBadge(_('Most Expensive'), _('The highest price for this TLD among your registrars'), 'secondary');
                        } ?></td>
                    <td class="text-end"><?php echo $money($fee->price[$price_type]); ?></td>
                    <td class="text-end"><?php echo $money($fee->converted['renewal']); ?></td>
                    <td class="text-end"><?php echo $money($fee->converted['initial']); ?></td>
                    <td class="text-end"><?php echo $money($fee->converted['transfer']); ?></td>
                    <td class="text-end"><?php echo $money($fee->converted['privacy']); ?></td>
                    <td class="text-end"><?php echo $money($fee->converted['misc']); ?></td>
                    <td><?php echo $fee->currency; ?></td>
                    <td class="text-end"><?php echo number_format($domains_at[$selected['tld']][$fee->registrar_id] ?? 0); ?></td>
                </tr><?php

            } ?>

            </tbody>
        </table></div><?php

        if ($selected['registrars'] < 2) { ?>
            <p class="small text-body-secondary"><?php echo _('Only one of your registrars has a fee for this TLD, so there\'s nothing to compare it with.'); ?></p><?php
        }

    } ?>

    </div>

    <h2 class="h4 mt-5" id="savings"><?php echo _('Possible Savings'); ?></h2><?php

    if ($savings['rows']) { ?>

        <strong><?php echo _('Total Yearly Saving'); ?>:</strong> <?php echo $money($total_saving); ?><BR>
        <strong><?php echo _('Number of Domains'); ?>:</strong> <?php echo number_format(count($savings['rows'])); ?><BR><BR>

        <table id="<?php echo $slug; ?>-savings" class="<?php echo $datatable_class; ?>">
            <thead>
            <tr>
                <th width="20px"></th>
                <th><?php echo _('Domain'); ?></th>
                <th><?php echo _('TLD'); ?></th>
                <th><?php echo _('Current Registrar'); ?></th>
                <th><?php echo _('Current Yearly Price'); ?></th>
                <th><?php echo _('Cheapest Registrar'); ?></th>
                <th><?php echo _('Cheapest Yearly Price'); ?></th>
                <th><?php echo _('Yearly Saving'); ?></th>
            </tr>
            </thead>
            <tbody><?php

            foreach ($savings['rows'] as $row) { ?>

                <tr>
                    <td></td>
                    <td><a href="<?php echo WEB_ROOT; ?>/domains/edit.php?did=<?php echo (int) $row['domain']->id; ?>"><?php echo $row['domain']->domain; ?></a></td>
                    <td>.<?php echo $row['domain']->tld; ?></td>
                    <td><?php echo $row['domain']->registrar_name; ?></td>
                    <td data-order="<?php echo round($row['current'], 2); ?>"><?php echo $money($row['current']); ?></td>
                    <td><?php echo $row['destination']->registrar_name; ?></td>
                    <td data-order="<?php echo round($row['destination_cost'], 2); ?>"><?php echo $money($row['destination_cost']); ?></td>
                    <td data-order="<?php echo round($row['saving'], 2); ?>"><?php echo $money($row['saving']); ?></td>
                </tr><?php

            } ?>

            </tbody>
        </table><?php

    } else {

        echo '<p>' . _('No savings found among the domains that could be compared.') . '</p>';

    }

    if ($savings['left_out'] > 0) { ?>
        <p class="small text-body-secondary"><?php echo sprintf(ngettext('%s domain is left out, since its fee is missing or its fee\'s currency has no conversion rate for you.', '%s domains are left out, since their fees are missing or their fees\' currencies have no conversion rates for you.', $savings['left_out']), number_format($savings['left_out'])); ?></p><?php
    } ?>

    <p class="small text-body-secondary"><?php echo $savings_note; ?></p><?php

}
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
<?php require_once DIR_INC . '/layout/chart-footer.inc.php'; ?>
</body>
</html>
