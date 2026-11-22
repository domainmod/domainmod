<?php
/**
 * /reporting/hosting/at-risk.php
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
$currency = new DomainMOD\Currency();
$dashboard = new DomainMOD\Dashboard();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/reporting-hosting-at-risk.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

// Nothing to report before the database update adds the hosting tables
if (!DomainMOD\Hosting::isAvailable($pdo)) {
    require_once DIR_INC . '/layout/hosting-report-unavailable.inc.php';
    exit;
}

// Rates for every currency in use, or the lists and reports below would leave out what uses a missing one
(new DomainMOD\Conversion())->ensureRates($_SESSION['s_user_id'] ?? 0, $_SESSION['s_default_currency'] ?? '');

$export_data = (int) ($_GET['export_data'] ?? 0);
// Which services the list shows: all of them, or those a tile counts (anything else shows all)
$show = (string) (is_string($_GET['show'] ?? null) ? $_GET['show'] : 'all');
if (!in_array($show, array('all', 'confirm', 'expiring', 'overdue', 'nofee'), true)) $show = 'all';

$expiration_days = (int) $pdo->query("
    SELECT expiration_days
    FROM settings")->fetchColumn();

// The renewal state and window checks use the hosting date, as the email and the other hosting views do
$today = DomainMOD\Hosting::hostingDate($pdo);
$risk_days = DomainMOD\Reporting::AT_RISK_DAYS;
$risk_end = DomainMOD\Hosting::addDays($today, $risk_days);
$hosting_report = new DomainMOD\HostingReport($pdo, $_SESSION['s_user_id'] ?? 0);
$at_risk = DomainMOD\HostingReport::atRisk($hosting_report->services($risk_end), $today, $risk_end);

$counts = array('confirm' => 0, 'expiring' => 0, 'overdue' => 0, 'nofee' => 0);
foreach ($at_risk as $row) {
    foreach ($row->reasons as $key) {
        $counts[$key]++;
    }
}

$list = $show === 'all' ? $at_risk : array_values(array_filter($at_risk, function ($row) use ($show) {
    return in_array($show, $row->reasons, true);
}));

// An amount in the user's currency, as the other reports show it
$money = function ($amount) use ($currency) {
    return $currency->format($amount, $_SESSION['s_default_currency_symbol'], $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space']);
};

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

// A passed date shows its renewal state ("Overdue" or "Confirm renewal", see DomainMOD\Hosting::renewalState())
$days_left_text = function ($row) use ($days_left, $today) {
    $state = DomainMOD\Hosting::renewalState($row->active, $row->autorenew, $row->expiry_date, $row->billing_term, $today);
    return $state !== '' ? DomainMOD\Hosting::stateLabel($state) : number_format($days_left($row));
};

// Whether the service renews itself, as the list and the export show it
$autorenew_text = function ($row) {
    return $row->autorenew == '1' ? _('Yes') : _('No');
};

// The reasons, as badges in the list and as plain text in the export
$reasons = array(
    'overdue' => array(_('Overdue'), _('Past its renewal date'), 'danger'),
    // TRANSLATORS: %s is the number of days an auto-renewing hosting service waits for its renewal to be confirmed (14).
    'confirm' => array(_('Confirm renewal'), sprintf(ngettext('Auto-renew is on, and the renewal date passed in the last %s day', 'Auto-renew is on, and the renewal date passed in the last %s days', DomainMOD\Hosting::CONFIRM_DAYS), DomainMOD\Hosting::CONFIRM_DAYS), 'warning'),
    'expiring' => array(_('Renewing Soon'), sprintf(_('Renews in the next %s days without auto-renew'), $risk_days), 'warning'),
    'nofee' => array(_('No Fee or Rate'), _('No fee, or no conversion rate for its currency'), 'secondary'));

$reason_names = function ($row) use ($reasons) {
    return implode(', ', array_map(function ($key) use ($reasons) { return $reasons[$key][0]; }, $row->reasons));
};

$intro = sprintf(_('Hosting services past their renewal date, plus those renewing in the next %1$s days without auto-renew. An auto-renewing service in the window is listed only when it has no fee or rate. This report always looks %1$s days ahead, separately from the Expiration Days setting (currently %2$s) that the Dashboard and the reminder emails use.'), $risk_days, sprintf(ngettext('%s day', '%s days', $expiration_days), $expiration_days))
    // TRANSLATORS: %s is the number of days an auto-renewing hosting service waits for its renewal to be confirmed (14).
    . ' ' . sprintf(ngettext('An active, auto-renewing service with a valid billing term whose renewal date passed in the last %s day is listed as Confirm renewal rather than Overdue.', 'An active, auto-renewing service with a valid billing term whose renewal date passed in the last %s days is listed as Confirm renewal rather than Overdue.', DomainMOD\Hosting::CONFIRM_DAYS), DomainMOD\Hosting::CONFIRM_DAYS);

if ($export_data === 1 && $list) {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('hosting_at_risk_services_report'), $today);

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array($intro);
    $export->writeRow($export_file, $row_contents);

    $row_contents = array(_('Number of Hosting Services') . ':', number_format(count($list)));
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        _('Hosting Service'),
        _('Renewal Date'),
        _('Days Left'),
        _('Auto Renew'),
        _('Provider'),
        _('Account'),
        _('Service Type'),
        _('Renewal Cost'),
        _('Reasons')
    );
    $export->writeRow($export_file, $row_contents);

    foreach ($list as $row) {
        $row_contents = array(
            $row->name,
            $row->expiry_date,
            $days_left_text($row),
            $autorenew_text($row),
            $row->provider_name,
            $row->owner_name . ' (' . $row->username . ')',
            $row->type,
            $cost_text($row),
            $reason_names($row)
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
<?php echo $layout->calloutTop(); ?>
<?php echo $intro; ?>
<?php echo $layout->calloutBottom(); ?>

<?php
// The page's actions, drawn in the page header (see Layout::renderPageActions())
$layout->addPageAction(_('View Full Report'), $report_filename, 'bi-arrow-counterclockwise');
if ($list) $layout->addPageAction(_('Export Report'), $report_filename . '?export_data=1&show=' . $show, 'bi-download'); ?>

<?php
// The tiles count reasons, and a service can have more than one, so a line first gives the number behind them
$total_at_risk = count($at_risk);
if ($counts['overdue'] === 0) {
    echo '<p class="domainmod-intro">' . sprintf(ngettext('%s at-risk hosting service, not overdue.', '%s at-risk hosting services, none of them overdue.', $total_at_risk), number_format($total_at_risk));
} else {
    echo '<p class="domainmod-intro">' . sprintf(ngettext('%1$s at-risk hosting service, %2$s overdue.', '%1$s at-risk hosting services, %2$s of them overdue.', $total_at_risk), number_format($total_at_risk), number_format($counts['overdue']));
}
if (array_sum($counts) > $total_at_risk) echo ' ' . _('A service can have more than one reason, so the tiles can add up to more.');
echo '</p>'; ?>
<div class="row gy-4"><?php
    echo $dashboard->displayPanel(_('Overdue'), $counts['overdue'], 'danger', 'bi-exclamation-triangle', '/reporting/hosting/at-risk.php?show=overdue#hosting-list', _('At-Risk Hosting Services'));
    echo $dashboard->displayPanel(_('Confirm renewal'), $counts['confirm'], 'warning', 'bi-hourglass-split', '/reporting/hosting/at-risk.php?show=confirm#hosting-list', _('At-Risk Hosting Services'));
    echo $dashboard->displayPanel(sprintf(_('Renewing Soon (%s days)'), $risk_days), $counts['expiring'], 'warning', 'bi-calendar-event', '/reporting/hosting/at-risk.php?show=expiring#hosting-list', _('At-Risk Hosting Services'));
    echo $dashboard->displayPanel(_('No Fee or Rate'), $counts['nofee'], 'secondary', 'bi-question-circle', '/reporting/hosting/at-risk.php?show=nofee#hosting-list', _('At-Risk Hosting Services')); ?>
</div>

<h2 class="h4 mt-4" id="hosting-list"><?php echo $show === 'all' ? _('All At-Risk Hosting Services') : $reasons[$show][0]; ?></h2><?php

if ($show !== 'all') { ?>
    <a href="<?php echo $report_filename; ?>#hosting-list"><?php echo _('Show all at-risk hosting services'); ?></a><BR><BR><?php
}

if ($list) { ?>

    <strong><?php echo _('Number of Hosting Services'); ?>:</strong> <?php echo number_format(count($list)); ?><BR><BR>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"><span class="visually-hidden"><?php echo _('Details'); ?></span></th>
            <th><?php echo _('Hosting Service'); ?></th>
            <th><?php echo _('Reasons'); ?></th>
            <th><?php echo _('Renewal Date'); ?></th>
            <th><?php echo _('Days Left'); ?></th>
            <th><?php echo _('Auto Renew'); ?></th>
            <th><?php echo _('Provider'); ?></th>
            <th><?php echo _('Account'); ?></th>
            <th><?php echo _('Service Type'); ?></th>
            <th><?php echo _('Renewal Cost'); ?></th>
        </tr>
        </thead>
        <tbody><?php

        foreach ($list as $row) { ?>

            <tr>
                <td></td>
                <td><a href="<?php echo WEB_ROOT; ?>/hosting/edit.php?hsid=<?php echo (int) $row->id; ?>"><?php echo $row->name; ?></a></td>
                <td data-order="<?php echo htmlspecialchars($reason_names($row), ENT_QUOTES, 'UTF-8'); ?>"><?php
                    foreach ($row->reasons as $key) {
                        echo $layout->statusBadge($reasons[$key][0], $reasons[$key][1], $reasons[$key][2]) . ' ';
                    } ?></td>
                <td data-order="<?php echo $row->expiry_date; ?>"><?php echo $row->expiry_date; ?></td>
                <td data-order="<?php echo $days_left($row); ?>"><?php echo $days_left_text($row); ?></td>
                <td><?php echo $autorenew_text($row); ?></td>
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
</body>
</html>
