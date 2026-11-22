<?php
/**
 * /reporting/domains/at-risk.php
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

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/reporting-domain-at-risk.inc.php';

$system->authCheck();
// Rates for every currency in use, or the lists and reports below would leave out what uses a missing one
(new DomainMOD\Conversion())->ensureRates($_SESSION['s_user_id'] ?? 0, $_SESSION['s_default_currency'] ?? '');
$pdo = $deeb->cnxx;

$export_data = (int) ($_GET['export_data'] ?? 0);
// The transfer lock and registry status reasons come with the 4.27.0 database update (DomainMOD\DomainStatus)
$status_available = DomainMOD\DomainStatus::isAvailable($pdo);
// Which domains the list shows: all of them, or those a tile counts (anything else shows all)
$show = is_string($_GET['show'] ?? null) ? $_GET['show'] : 'all';
$show_values = array('all', 'overdue', 'autorenew', 'nofee');
if ($status_available) $show_values = array_merge($show_values, array('lock', 'registry'));
if (!in_array($show, $show_values, true)) $show = 'all';

$expiration_days = (int) $pdo->query("
    SELECT expiration_days
    FROM settings")->fetchColumn();

$today = $time->userToday();
$risk_days = DomainMOD\Reporting::AT_RISK_DAYS;
$risk_end = $time->userTodayPlusDays($risk_days, $today);
$at_risk = $reporting->atRiskDomains($reporting->renewalDomainsWithStatus($time->userTodayPlusDays($risk_days + 1, $today)), $today, $risk_end);

// Why each domain is listed (it can be more than one)
$is_overdue = function ($row) use ($today) {
    return $row->expiry_date < $today;
};
$conditions = array(
    'overdue' => $is_overdue,
    'autorenew' => function ($row) use ($is_overdue) { return !$is_overdue($row) && $row->autorenew != '1'; },
    'nofee' => function ($row) { return !$row->has_fee || !$row->has_rate; });
if ($status_available) {
    // A transfer lock only matters for a domain still to renew, while the registry's hold or deletion matters for every one
    $conditions['lock'] = function ($row) use ($is_overdue) { return !$is_overdue($row) && (int) $row->transfer_lock === DomainMOD\DomainStatus::LOCK_OFF; };
    $conditions['registry'] = function ($row) { return (int) $row->registry_problem === 1; };
}

$counts = array();
foreach ($conditions as $key => $condition) {
    $counts[$key] = count(array_filter($at_risk, $condition));
}

$list = $show === 'all' ? $at_risk : array_values(array_filter($at_risk, $conditions[$show]));

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

$days_left_text = function ($row) use ($days_left) {
    $left = $days_left($row);
    return $left < 0 ? _('Overdue') : number_format($left);
};

$yes_no = function ($value) {
    return $value == '1' ? _('Yes') : _('No');
};

// The reasons, as badges in the list and as plain text in the export
$reasons = array(
    'overdue' => array(_('Overdue'), _('Past its expiry date'), 'danger'),
    'autorenew' => array(_('Auto-Renewal Off'), _('Auto-renewal is off'), 'warning'),
    'nofee' => array(_('No Fee or Rate'), _('No fee, or no conversion rate for its currency'), 'secondary'));
if ($status_available) {
    $reasons['lock'] = array(_('Transfer Lock Off'), _('The transfer lock is off'), 'warning');
    $reasons['registry'] = array(_('Registry Hold or Deletion'), _('The registry has it on hold or is deleting or restoring it'), 'danger');
}

$row_reasons = function ($row) use ($conditions) {
    $keys = array();
    foreach ($conditions as $key => $condition) {
        if ($condition($row)) $keys[] = $key;
    }
    return $keys;
};

// The list's heading, also written to the export
$list_heading = $show === 'all' ? _('All At-Risk Domains') : $reasons[$show][0];

$expiration_days_text = sprintf(ngettext('%s day', '%s days', $expiration_days), $expiration_days);
if ($status_available) {
    $intro = sprintf(_('This report lists the live domains that might not renew: those expiring in the next %1$s days that have auto-renewal or the transfer lock turned off, are on hold at the registry, or are being deleted or restored by the registry, and all live domains past their expiry date.'), $risk_days) . ' ' . sprintf(_('This report always looks %1$s days ahead, separately from the Expiration Days setting (currently %2$s) that the Dashboard and the reminder emails use.'), $risk_days, $expiration_days_text);
} else {
    $intro = sprintf(_('Domains that might not renew: those expiring in the next %1$s days with auto-renewal off, plus any past their expiry date. This report always looks %1$s days ahead, separately from the Expiration Days setting (currently %2$s) that the Dashboard and the reminder emails use.'), $risk_days, $expiration_days_text);
}

// Domains without a transfer lock that expire later aren't listed here, so a line links to all of them on the Domains
// list, counted by the list's own query (the same joins and filters), so the number matches what the link shows
$unlocked_count = 0;
if ($status_available) {
    $unlocked_list = new DomainMOD\DomainList($pdo, $_SESSION['s_user_id'], array());
    $unlocked_count = $unlocked_list->count(DomainMOD\DomainList::filterSql($unlocked_list->filterParts(
        DomainMOD\DomainList::normalizeState(array('is_active' => 'LIVE')), '', '', '')) . DomainMOD\DomainStatus::filterCondition('unlocked'));
}

if ($export_data === 1 && $list) {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('at_risk_domains_report'), $today);

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array($intro);
    $export->writeRow($export_file, $row_contents);

    $row_contents = array($list_heading);
    $export->writeRow($export_file, $row_contents);

    $row_contents = array(_('Number of Domains') . ':', number_format(count($list)));
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        _('Domain'),
        _('Expiry Date'),
        _('Days Left'),
        _('Registrar'),
        _('Account'),
        _('Auto-Renewal'),
        _('Renewal Cost'),
        _('Reasons')
    );
    $export->writeRow($export_file, $row_contents);

    foreach ($list as $row) {
        $row_contents = array(
            $row->domain,
            $row->expiry_date,
            $days_left_text($row),
            $row->registrar_name,
            $row->owner_name . ' (' . $row->username . ')',
            $yes_no($row->autorenew),
            $cost_text($row),
            implode(', ', array_map(function ($key) use ($reasons) { return $reasons[$key][0]; }, $row_reasons($row)))
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
// The tiles count reasons, and a domain can have more than one, so a line first gives the number of domains behind them
$total_at_risk = count($at_risk);
$expiring_at_risk = $total_at_risk - $counts['overdue'];
echo '<p class="domainmod-intro">' . sprintf(ngettext('%1$s at-risk domain: %2$s overdue and %3$s expiring in the next %4$s days.', '%1$s at-risk domains: %2$s overdue and %3$s expiring in the next %4$s days.', $total_at_risk), number_format($total_at_risk), number_format($counts['overdue']), number_format($expiring_at_risk), number_format($risk_days));
if (array_sum($counts) > $total_at_risk) echo ' ' . _('A domain can have more than one reason, so the tiles can add up to more.');
echo '</p>'; ?>
<div class="row"><?php
    echo $dashboard->displayPanel(_('Overdue'), $counts['overdue'], 'danger', 'bi-exclamation-triangle', '/reporting/domains/at-risk.php?show=overdue#domain-list', _('At-Risk Domains'));
    echo $dashboard->displayPanel(sprintf(_('Auto-Renewal Off (%s days)'), $risk_days), $counts['autorenew'], 'warning', 'bi-arrow-repeat', '/reporting/domains/at-risk.php?show=autorenew#domain-list', _('At-Risk Domains'));
    echo $dashboard->displayPanel(_('No Fee or Rate'), $counts['nofee'], 'secondary', 'bi-question-circle', '/reporting/domains/at-risk.php?show=nofee#domain-list', _('At-Risk Domains'));
    if ($status_available) {
        echo $dashboard->displayPanel(sprintf(_('Transfer Lock Off (%s days)'), $risk_days), $counts['lock'], 'warning', 'bi-unlock', '/reporting/domains/at-risk.php?show=lock#domain-list', _('At-Risk Domains'));
        echo $dashboard->displayPanel(_('Registry Hold or Deletion'), $counts['registry'], 'danger', 'bi-slash-circle', '/reporting/domains/at-risk.php?show=registry#domain-list', _('At-Risk Domains'));
    } ?>
</div><?php
if ($unlocked_count > 0) {
    echo '<p>' . htmlspecialchars(sprintf(ngettext('%s live domain has no transfer lock.', '%s live domains have no transfer lock.', $unlocked_count), number_format($unlocked_count)), ENT_QUOTES, 'UTF-8')
        . ' <a href="' . WEB_ROOT . '/domains/?lock=unlocked">' . _('View on the Domains list') . '</a></p>';
} ?>

<h2 class="h4 mt-4" id="domain-list"><?php echo $list_heading; ?></h2><?php

if ($show !== 'all') { ?>
    <a href="<?php echo $report_filename; ?>#domain-list"><?php echo _('Show all at-risk domains'); ?></a><BR><BR><?php
}

if ($list) { ?>

    <strong><?php echo _('Number of Domains'); ?>:</strong> <?php echo number_format(count($list)); ?><BR><BR>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"><span class="visually-hidden"><?php echo _('Details'); ?></span></th>
            <th><?php echo _('Domain'); ?></th>
            <th><?php echo _('Reasons'); ?></th>
            <th><?php echo _('Expiry Date'); ?></th>
            <th><?php echo _('Days Left'); ?></th>
            <th><?php echo _('Registrar'); ?></th>
            <th><?php echo _('Account'); ?></th>
            <th><?php echo _('Auto-Renewal'); ?></th>
            <th><?php echo _('Renewal Cost'); ?></th>
        </tr>
        </thead>
        <tbody><?php

        foreach ($list as $row) { ?>

            <tr>
                <td></td>
                <td><a href="<?php echo WEB_ROOT; ?>/domains/edit.php?did=<?php echo (int) $row->id; ?>"><?php echo $row->domain; ?></a></td>
                <td data-order="<?php echo htmlspecialchars(implode(', ', array_map(function ($key) use ($reasons) { return $reasons[$key][0]; }, $row_reasons($row))), ENT_QUOTES, 'UTF-8'); ?>"><?php
                    foreach ($row_reasons($row) as $key) {
                        echo $layout->statusBadge($reasons[$key][0], $reasons[$key][1], $reasons[$key][2]) . ' ';
                    } ?></td>
                <td data-order="<?php echo $row->expiry_date; ?>"><?php echo $row->expiry_date; ?></td>
                <td data-order="<?php echo $days_left($row); ?>"><?php echo $days_left_text($row); ?></td>
                <td><?php echo $row->registrar_name; ?></td>
                <td><?php echo $row->owner_name; ?> (<?php echo $row->username; ?>)</td>
                <td><?php echo $yes_no($row->autorenew); ?></td>
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
