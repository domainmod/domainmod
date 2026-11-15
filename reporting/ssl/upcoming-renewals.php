<?php
/**
 * /reporting/ssl/upcoming-renewals.php
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
require_once DIR_INC . '/settings/reporting-ssl-upcoming-renewals.inc.php';

$system->authCheck();
// Rates for every currency in use, or the lists and reports below would leave out what uses a missing one
(new DomainMOD\Conversion())->ensureRates($_SESSION['s_user_id'] ?? 0, $_SESSION['s_default_currency'] ?? '');

$export_data = (int) ($_GET['export_data'] ?? 0);
// The window the certificate list covers: 30, 60, or 90 days (anything else shows 90)
$days = (int) ($_GET['days'] ?? 90);
if (!in_array($days, array(30, 60, 90), true)) $days = 90;

$today = $time->timeBasic();
$rows = $reporting->renewalCerts($reporting->renewalChartEnd($today));
$months = $reporting->renewalMonths($rows, $today);

// An amount in the user's currency, as the other reports show it
$money = function ($amount) use ($currency) {
    return $currency->format($amount, $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);
};

// The 30, 60, and 90 day windows' tiles (from today through that many days ahead) and the chosen window's
// certificates (overdue ones first, then those expiring by the window's end)
$windows = array();
foreach (array(30, 60, 90) as $window_days) {
    $window_end = $time->timeBasicPlusDays($window_days);
    $windows[$window_days] = array('count' => 0, 'cost' => 0.0, 'missing' => 0);
    foreach ($rows as $row) {
        if ($row->expiry_date < $today || $row->expiry_date > $window_end) continue;
        $windows[$window_days]['count']++;
        if ($row->has_fee && $row->has_rate) {
            $windows[$window_days]['cost'] += (float) $row->converted_cost;
        } else {
            $windows[$window_days]['missing']++;
        }
    }
}

$window_end = $time->timeBasicPlusDays($days);
$list = array_values(array_filter($rows, function ($row) use ($window_end) {
    return $row->expiry_date <= $window_end;
}));

$list_cost = 0.0;
$list_missing = 0;
foreach ($list as $row) {
    if ($row->has_fee && $row->has_rate) {
        $list_cost += (float) $row->converted_cost;
    } else {
        $list_missing++;
    }
}

// What a certificate's cost cell shows: its renewal cost, or why it has none
$cost_text = function ($row) use ($money) {
    if (!$row->has_fee) return _('No fee');
    if (!$row->has_rate) return _('No rate');
    return $money($row->converted_cost);
};

// The days left as a number (negative once overdue), and a cost as a number (no fee or no rate sort first), so the
// list's columns sort by value
$days_left = function ($row) use ($today) {
    return (int) round((strtotime($row->expiry_date) - strtotime($today)) / 86400);
};
$cost_order = function ($row) {
    return !$row->has_fee || !$row->has_rate ? -1 : round((float) $row->converted_cost, 2);
};

// Days from today to the expiry date, or "Overdue" once it's passed
$days_left_text = function ($row) use ($days_left) {
    $left = $days_left($row);
    return $left < 0 ? _('Overdue') : number_format($left);
};

$window_title = sprintf(_('Overdue SSL certificates and SSL certificates expiring in the next %s days'), $days);

if ($export_data === 1 && $list) {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('ssl_upcoming_renewals_report'), $days . '_' . _('days') . '_' . $today);

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array($window_title . ':', number_format(count($list)));
    $export->writeRow($export_file, $row_contents);

    $row_contents = array(_('Renewal Cost') . ':', $money($list_cost), $_SESSION['s_default_currency']);
    $export->writeRow($export_file, $row_contents);

    if ($list_missing > 0) {
        $row_contents = array(sprintf(ngettext('%s SSL certificate has no fee or rate and is left out of the cost.', '%s SSL certificates have no fee or rate and are left out of the cost.', $list_missing), number_format($list_missing)));
        $export->writeRow($export_file, $row_contents);
    }

    $export->writeBlankRow($export_file);

    $row_contents = array(
        _('Certificate'),
        _('Domain'),
        _('Expiry Date'),
        _('Days Left'),
        _('Provider'),
        _('Account'),
        _('Type'),
        _('Renewal Cost')
    );
    $export->writeRow($export_file, $row_contents);

    foreach ($list as $row) {
        $row_contents = array(
            $row->name,
            $row->domain,
            $row->expiry_date,
            $days_left_text($row),
            $row->provider_name,
            $row->owner_name . ' (' . $row->username . ')',
            $row->type,
            $cost_text($row)
        );
        $export->writeRow($export_file, $row_contents);
    }

    $export->writeBlankRow($export_file);

    $row_contents = array(_('Month'), _('SSL Certificates'), _('Renewal Cost'), _('No Fee or Rate'));
    $export->writeRow($export_file, $row_contents);

    foreach ($months as $month) {
        $row_contents = array($month['label'], $month['count'], $money($month['cost']), $month['missing']);
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
<?php echo $layout->calloutTop(); ?>
<?php echo _('What your SSL certificates will cost to renew over the next year, month by month, and which ones expire soon. Each renewal is counted as one year at its renewal fee plus any other fees, in your currency. Expired certificates are left out.'); ?>
<?php echo $layout->calloutBottom(); ?>

<a href="<?php echo $report_filename; ?>"><?php echo $layout->showButton('button', _('View Full Report')); ?></a>&nbsp;&nbsp;<?php
if ($list) { ?>
    <a href="<?php echo $report_filename; ?>?export_data=1&days=<?php echo $days; ?>"><?php echo $layout->showButton('button', _('Export Report')); ?></a><?php
} ?>
<BR><BR>

<div class="row"><?php
    foreach ($windows as $window_days => $window) {
        $detail = $money($window['cost']);
        if ($window['missing'] > 0) $detail .= ' ' . sprintf(_('(%s without a fee or rate)'), number_format($window['missing']));
        echo $dashboard->displayPanel(sprintf(_('Next %s Days'), $window_days), $window['count'], 'danger', 'bi-calendar-event', '/reporting/ssl/upcoming-renewals.php?days=' . $window_days . '#cert-list', _('SSL Upcoming Renewals'), $detail);
    } ?>
</div>

<?php
if ($rows) {

    echo $chart->bar('ssl-upcoming-renewals-chart', _('SSL Renewal Costs, Next 12 Months'), array_column($months, 'label'), array_column($months, 'cost'), !empty($months[0]['overdue']), '<div class="mt-3">' . $reporting->renewalMonthsTable($months, 'certs') . '</div>');

} ?>

<h4 class="mt-5" id="cert-list"><?php echo $window_title; ?></h4>

<div class="mb-3">
<div class="btn-group" role="group" aria-label="<?php echo htmlspecialchars(_('Window'), ENT_QUOTES, 'UTF-8'); ?>"><?php
    foreach (array(30, 60, 90) as $window_days) { ?>
        <a href="<?php echo $report_filename; ?>?days=<?php echo $window_days; ?>#cert-list" class="btn btn-sm <?php echo $window_days === $days ? 'btn-danger' : 'btn-outline-secondary'; ?>"<?php echo $window_days === $days ? ' aria-current="true"' : ''; ?>><?php echo sprintf(_('%s Days'), $window_days); ?></a><?php
    } ?>
</div>
</div>

<?php
if ($list) { ?>

    <strong><?php echo _('Renewal Cost'); ?>:</strong> <?php echo $money($list_cost); ?> <?php echo $_SESSION['s_default_currency']; ?><BR>
    <strong><?php echo _('Number of SSL Certs'); ?>:</strong> <?php echo number_format(count($list)); ?><BR><?php

    if ($list_missing > 0) { ?>
        <em><?php echo sprintf(ngettext('%s SSL certificate has no fee or rate and is left out of the cost.', '%s SSL certificates have no fee or rate and are left out of the cost.', $list_missing), number_format($list_missing)); ?></em><BR><?php
    } ?>
    <BR>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th><?php echo _('Certificate'); ?></th>
            <th><?php echo _('Domain'); ?></th>
            <th><?php echo _('Expiry Date'); ?></th>
            <th><?php echo _('Days Left'); ?></th>
            <th><?php echo _('Provider'); ?></th>
            <th><?php echo _('Account'); ?></th>
            <th><?php echo _('Type'); ?></th>
            <th><?php echo _('Renewal Cost'); ?></th>
        </tr>
        </thead>
        <tbody><?php

        foreach ($list as $row) { ?>

            <tr>
                <td></td>
                <td><a href="<?php echo WEB_ROOT; ?>/ssl/edit.php?sslcid=<?php echo (int) $row->id; ?>"><?php echo $row->name; ?></a></td>
                <td><?php if ($row->domain_id !== null) { ?><a href="<?php echo WEB_ROOT; ?>/domains/edit.php?did=<?php echo (int) $row->domain_id; ?>"><?php echo $row->domain; ?></a><?php } ?></td>
                <td data-order="<?php echo $row->expiry_date; ?>"><?php echo $row->expiry_date; ?></td>
                <td data-order="<?php echo $days_left($row); ?>"><?php echo $days_left_text($row); ?></td>
                <td><?php echo $row->provider_name; ?></td>
                <td><?php echo $row->owner_name; ?> (<?php echo $row->username; ?>)</td>
                <td><?php echo $row->type; ?></td>
                <td data-order="<?php echo $cost_order($row); ?>"><?php echo $cost_text($row); ?></td>
            </tr><?php

        } ?>

        </tbody>
    </table><?php

} else {

    echo _('No results.') . '<BR>';

}
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; //@formatter:on ?>
<?php require_once DIR_INC . '/layout/chart-footer.inc.php'; ?>
</body>
</html>
