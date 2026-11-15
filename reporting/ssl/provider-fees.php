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
$chart = new DomainMOD\Chart();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/reporting-ssl-fees.inc.php';

$system->authCheck();
// Rates for every currency a fee uses, or the comparison below would leave out the fees that use a missing one
(new DomainMOD\Conversion())->ensureRates($_SESSION['s_user_id'] ?? 0, $_SESSION['s_default_currency'] ?? '');
$pdo = $deeb->cnxx;

$export_data = (int) ($_GET['export_data'] ?? 0);
$all = $_GET['all'] ?? '';


// The comparison: every entered fee (whatever View All or Active Only shows), with its yearly price (renewal plus
// misc) in the user's currency, the user's active certificates on it, and whether its currency has a rate
$stmt = $pdo->prepare("
    SELECT f.id AS fee_id, f.ssl_provider_id, sslp.name AS ssl_provider, f.type_id, sslt.type, f.renewal_fee, f.misc_fee, c.currency,
        c.symbol, c.symbol_order, c.symbol_space, (cc.id IS NOT NULL) AS has_rate, (f.renewal_fee + f.misc_fee) * cc.conversion AS yearly_price,
        (SELECT COUNT(*) FROM ssl_certs AS sslc WHERE sslc.fee_id = f.id AND sslc.active NOT IN ('0')) AS certs
    FROM ssl_fees AS f
    JOIN ssl_providers AS sslp ON f.ssl_provider_id = sslp.id
    JOIN ssl_cert_types AS sslt ON f.type_id = sslt.id
    JOIN currencies AS c ON f.currency_id = c.id
    LEFT JOIN currency_conversions AS cc ON cc.currency_id = f.currency_id AND cc.user_id = :user_id
    ORDER BY sslt.type, sslp.name");
$stmt->bindValue('user_id', (int) ($_SESSION['s_user_id'] ?? 0), PDO::PARAM_INT);
$stmt->execute();
$comparison_fees = $stmt->fetchAll();

$fees_by_type = array();
$type_names = array();
$fee_certs = array();
$fees_left_out = 0;
foreach ($comparison_fees as $fee) {
    $fee_certs[$fee->fee_id] = (int) $fee->certs;
    if (!$fee->has_rate) {
        $fees_left_out++;
        continue;
    }
    $fees_by_type[$fee->type_id][] = $fee;
    $type_names[$fee->type_id] = $fee->type;
}

// The cheapest fees of each type, by yearly price to the cent (ties share it), for the table's badges
$cheapest_fees = array();
foreach ($fees_by_type as $type_id => $fees) {
    $low = min(array_map(function ($fee) { return round((float) $fee->yearly_price, 2); }, $fees));
    foreach ($fees as $fee) {
        if (round((float) $fee->yearly_price, 2) == $low) $cheapest_fees[$fee->fee_id] = true;
    }
}

// The types a comparison can show (fees with rates at two or more providers), and the one picked: from the request
// when it's one of them, otherwise the one with the most active certificates
$comparable_types = array();
foreach ($fees_by_type as $type_id => $fees) {
    if (count(array_unique(array_column($fees, 'ssl_provider_id'))) >= 2) {
        $comparable_types[(int) $type_id] = array_sum(array_column($fees, 'certs'));
    }
}
$type_id = (int) ($_GET['type'] ?? 0);
if (!isset($comparable_types[$type_id])) {
    $type_id = 0;
    foreach ($comparable_types as $id => $certs) {
        if ($type_id === 0 || $certs > $comparable_types[$type_id]
            || ($certs == $comparable_types[$type_id] && strcmp($type_names[$id], $type_names[$type_id]) < 0)) {
            $type_id = $id;
        }
    }
}

$compared = array();
if ($type_id !== 0) {
    $compared = $fees_by_type[$type_id];
    usort($compared, function ($a, $b) {
        $difference = round((float) $a->yearly_price, 2) <=> round((float) $b->yearly_price, 2);
        return $difference !== 0 ? $difference : strcmp($a->ssl_provider, $b->ssl_provider);
    });
}

// The badges after a fee's type in the table: Cheapest (of every entered fee for its type) and In use
$type_badges = function ($fee_id) use ($cheapest_fees, $fee_certs, $layout) {
    $badges = '';
    if (isset($cheapest_fees[$fee_id])) {
        $badges .= ' ' . $layout->statusBadge(_('Cheapest'), _('The lowest yearly price for this certificate type, of all the fees you\'ve entered'), 'success');
    }
    if (($fee_certs[$fee_id] ?? 0) > 0) {
        $badges .= ' ' . $layout->statusBadge(sprintf(_('In use (%s)'), number_format($fee_certs[$fee_id])), sprintf(ngettext('%s active certificate uses this fee', '%s active certificates use this fee', $fee_certs[$fee_id]), number_format($fee_certs[$fee_id])), 'secondary');
    }
    return $badges;
};

// The comparison's htmx requests get this whole page, from which htmx takes just the comparison. X-DomainMOD-Result
// marks it as a real result for the footer's script, so a redirect (such as a lapsed session's) is never swapped in,
// and HX-Push-Url puts the comparison's own address in the address bar.
if ($system->isHtmxRequest()) {
    header('X-DomainMOD-Result: 1');
    header('HX-Push-Url: ' . WEB_ROOT . '/reporting/ssl/provider-fees.php?' . http_build_query(array('type' => $type_id, 'all' => is_string($all) ? $all : '')));
}

// An amount in the user's currency, as the other reports show it
$money = function ($amount) use ($currency) {
    return $currency->format($amount, $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);
};

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
            _('SSL Provider'),
            _('Certificate Type'),
            _('Initial Fee'),
            _('Renewal Fee'),
            _('Misc Fee'),
            _('Currency'),
            _('Certs'),
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
<?php if ($total_rows > 0) { //@formatter:off ?>
    <a href="provider-fees.php?export_data=1&all=<?php echo urlencode($all); ?>"><?php echo $layout->showButton('button', _('Export Report')); ?></a><BR><BR>
<?php } //@formatter:on ?>
<a href="provider-fees.php?all=1"><?php echo $layout->showButton('button', _('View All')); ?></a>&nbsp;&nbsp;&nbsp;or&nbsp;&nbsp;&nbsp;<a href="provider-fees.php?all=0"><?php echo $layout->showButton('button', _('Active Only')); ?></a><BR><BR>

<h4 id="comparison"><?php echo _('Compare Providers'); ?></h4>

<?php // The comparison updates in place with htmx (the picker fetches this page and swaps in just this section, keeping
      // the scroll position, and the address bar follows), and without JavaScript it loads the page as usual ?>
<div id="ssl-compare"><?php

if ($compared) { ?>

    <form method="get" action="provider-fees.php" hx-get="provider-fees.php" hx-target="#ssl-compare" hx-select="#ssl-compare" hx-swap="outerHTML" hx-sync="#ssl-compare:replace" class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <label for="type" class="form-label fw-bold mb-0"><?php echo _('Certificate Type'); ?></label>
        <select name="type" id="type" class="form-select w-auto"><?php
            foreach ($comparable_types as $id => $certs) { ?>
                <option value="<?php echo (int) $id; ?>"<?php echo $id === $type_id ? ' selected' : ''; ?>><?php echo $type_names[$id]; ?></option><?php
            } ?>
        </select>
        <input type="hidden" name="all" value="<?php echo is_string($all) ? htmlspecialchars($all, ENT_QUOTES, 'UTF-8') : ''; ?>">
        <?php echo $layout->showButton('submit', _('Compare')); ?>
    </form><?php

    ?>
    <p class="small text-body-secondary" tabindex="-1" data-domainmod-focus><?php echo sprintf(_('Showing each provider\'s yearly price for %s.'), $type_names[$type_id]); ?></p><?php

    $low = round((float) $compared[0]->yearly_price, 2);
    $highlight = array();
    foreach ($compared as $index => $fee) {
        if (round((float) $fee->yearly_price, 2) == $low) $highlight[] = $index;
    }

    ob_start(); ?>

    <table class="table table-sm table-striped mt-3 mb-0">
        <thead>
        <tr>
            <th scope="col"><?php echo _('Provider'); ?></th>
            <th scope="col" class="text-end"><?php echo _('Yearly Price'); ?></th>
            <th scope="col" class="text-end"><?php echo _('Renewal'); ?></th>
            <th scope="col" class="text-end"><?php echo _('Misc'); ?></th>
            <th scope="col"><?php echo _('Fee Currency'); ?></th>
            <th scope="col" class="text-end"><?php echo _('Your Certificates'); ?></th>
        </tr>
        </thead>
        <tbody><?php
        foreach ($compared as $index => $fee) { ?>

            <tr>
                <td><?php echo $fee->ssl_provider; ?><?php
                    if (in_array($index, $highlight, true)) echo ' ' . $layout->statusBadge(_('Cheapest'), _('The lowest yearly price for this certificate type, of all the fees you\'ve entered'), 'success'); ?></td>
                <td class="text-end"><?php echo $money($fee->yearly_price); ?></td>
                <td class="text-end"><?php echo $currency->format($fee->renewal_fee, $fee->symbol, $fee->symbol_order, $fee->symbol_space); ?></td>
                <td class="text-end"><?php echo $currency->format($fee->misc_fee, $fee->symbol, $fee->symbol_order, $fee->symbol_space); ?></td>
                <td><?php echo $fee->currency; ?></td>
                <td class="text-end"><?php echo number_format((int) $fee->certs); ?></td>
            </tr><?php

        } ?>

        </tbody>
    </table>
    <p class="small text-body-secondary mt-2 mb-0"><?php echo _('The yearly price is each provider\'s renewal fee plus its misc fee, in your currency, with the renewal and misc fees in the fee\'s own currency. The comparison includes every fee you\'ve entered for this type, whether or not a certificate uses it.'); ?></p><?php

    $after = ob_get_clean();

    echo $chart->priceBars('ssl-fee-comparison-chart', sprintf(_('Yearly Price by Provider for %s'), html_entity_decode($type_names[$type_id], ENT_QUOTES, 'UTF-8')),
        array_map(function ($fee) { return $fee->ssl_provider; }, $compared),
        array_map(function ($fee) { return round((float) $fee->yearly_price, 2); }, $compared),
        $highlight, $after); ?>
    <BR><?php

} else { ?>

    <p><?php echo _('A comparison needs fees from at least two providers for the same certificate type.'); ?></p><?php

} ?>
</div><?php

// Fees left out of the comparison and the Cheapest badges, said wherever there's a comparison or not
if ($fees_left_out > 0) { ?>
    <p class="small text-body-secondary"><?php echo sprintf(ngettext('%s fee is left out of the comparison and the Cheapest badges, since its currency has no conversion rate for you.', '%s fees are left out of the comparison and the Cheapest badges, since their currency has no conversion rate for you.', $fees_left_out), number_format($fees_left_out)); ?></p><?php
} ?>

<?php if ($total_rows > 0) { ?>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th><?php echo _('Provider'); ?></th>
            <th><?php echo _('Type'); ?></th>
            <th><?php echo _('Initial'); ?></th>
            <th><?php echo _('Renewal'); ?></th>
            <th><?php echo _('Misc'); ?></th>
            <th><?php echo _('Currency'); ?></th>
            <th><?php echo _('Certs'); ?></th>
            <th><?php echo _('Last Update'); ?></th>
        </tr>
        </thead>
        <tbody><?php

        $new_ssl_provider = "";
        $last_ssl_provider = "";
        $new_type = "";
        $last_type = "";

        foreach ($result as $row) {

            $new_ssl_provider = $row->ssl_provider;
            $new_type = $row->type;

            if ($row->update_time == '1970-01-01 00:00:00') {
                $row->update_time = $row->insert_time;
            }
            $last_updated = $time->toUserTimezone(date('Y-m-d', strtotime($row->update_time)));

            if ($new_ssl_provider != $last_ssl_provider || $new_ssl_provider == "") { ?>

                <tr>
                    <td></td>
                    <td>
                        <?php echo $row->ssl_provider; ?>
                    </td>
                    <td>
                        <?php echo $row->type; ?><?php echo $type_badges($row->fee_id); ?>
                    </td>
                    <td><?php
                        $row->initial_fee = $currency->format($row->initial_fee, $row->symbol, $row->symbol_order, $row->symbol_space);
                        echo $row->initial_fee; ?>
                    </td>
                    <td>
                        <?php
                        $row->renewal_fee = $currency->format($row->renewal_fee, $row->symbol, $row->symbol_order, $row->symbol_space);
                        echo $row->renewal_fee;
                        ?>
                    </td>
                    <td>
                        <?php
                        $row->misc_fee = $currency->format($row->misc_fee, $row->symbol, $row->symbol_order, $row->symbol_space);
                        echo $row->misc_fee;
                        ?>
                    </td>
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
                $last_type = $row->type;

            } else { ?>

                <tr>
                    <td></td>
                    <td>&nbsp;</td>
                    <td>
                        <?php echo $row->type; ?><?php echo $type_badges($row->fee_id); ?>
                    </td>
                    <td>
                        <?php
                        $row->initial_fee = $currency->format($row->initial_fee, $row->symbol, $row->symbol_order, $row->symbol_space);
                        echo $row->initial_fee;
                        ?>
                    </td>
                    <td>
                        <?php
                        $row->renewal_fee = $currency->format($row->renewal_fee, $row->symbol, $row->symbol_order, $row->symbol_space);
                        echo $row->renewal_fee;
                        ?>
                    </td>
                    <td>
                        <?php
                        $row->misc_fee = $currency->format($row->misc_fee, $row->symbol, $row->symbol_order, $row->symbol_space);
                        echo $row->misc_fee;
                        ?>
                    </td>
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
                $last_type = $row->type;

            }

        } ?>

        </tbody>
    </table><?php

} ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
<?php require_once DIR_INC . '/layout/chart-footer.inc.php'; ?>
</body>
</html>
