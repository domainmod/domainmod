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
// Which domains the list shows: all of them, or those a tile counts (anything else shows all)
$show = (string) ($_GET['show'] ?? 'all');
if (!in_array($show, array('all', 'overdue', 'autorenew', 'privacy', 'nofee'), true)) $show = 'all';

$expiration_days = (int) $pdo->query("
    SELECT expiration_days
    FROM settings")->fetchColumn();

$today = $time->timeBasic();
$risk_days = DomainMOD\Reporting::AT_RISK_DAYS;
$risk_end = $time->timeBasicPlusDays($risk_days);
$at_risk = $reporting->atRiskDomains($reporting->renewalDomains($time->timeBasicPlusDays($risk_days + 1)), $today, $risk_end);

// Why each domain is listed (it can be more than one)
$is_overdue = function ($row) use ($today) {
    return $row->expiry_date < $today;
};
$conditions = array(
    'overdue' => $is_overdue,
    'autorenew' => function ($row) use ($is_overdue) { return !$is_overdue($row) && $row->autorenew != '1'; },
    'privacy' => function ($row) use ($is_overdue) { return !$is_overdue($row) && $row->privacy != '1'; },
    'nofee' => function ($row) { return !$row->has_fee || !$row->has_rate; });

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
    'autorenew' => array(_('Auto Renewal Off'), _('Auto renewal is off'), 'warning'),
    'privacy' => array(_('Privacy Off'), _('Privacy is off'), 'secondary'),
    'nofee' => array(_('No Fee or Rate'), _('No fee, or no conversion rate for its currency'), 'secondary'));

$row_reasons = function ($row) use ($conditions) {
    $keys = array();
    foreach ($conditions as $key => $condition) {
        if ($condition($row)) $keys[] = $key;
    }
    return $keys;
};

$intro = sprintf(_('Domains expiring in the next %1$s days with auto renewal or privacy off, plus any past their expiry date. This report always looks %1$s days ahead, separately from the Expiration Days setting (currently %2$s) that the Dashboard and the reminder emails use.'), $risk_days, sprintf(ngettext('%s day', '%s days', $expiration_days), $expiration_days));

if ($export_data === 1 && $list) {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('at_risk_domains_report'), $today);

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array($intro);
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
        _('Auto Renewal'),
        _('Privacy'),
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
            $yes_no($row->privacy),
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
<?php echo $intro; ?><BR><BR>
<?php echo _('Privacy off is sometimes deliberate, since some TLDs don\'t offer it, so this is a list to check rather than a list of mistakes.'); ?>
<?php echo $layout->calloutBottom(); ?>

<a href="<?php echo $report_filename; ?>"><?php echo $layout->showButton('button', _('View Full Report')); ?></a>&nbsp;&nbsp;<?php
if ($list) { ?>
    <a href="<?php echo $report_filename; ?>?export_data=1&show=<?php echo $show; ?>"><?php echo $layout->showButton('button', _('Export Report')); ?></a><?php
} ?>
<BR><BR>

<?php
// The tiles count reasons, and a domain can have more than one, so a line first gives the number of domains behind them
$total_at_risk = count($at_risk);
$expiring_at_risk = $total_at_risk - $counts['overdue'];
echo '<p class="domainmod-intro">' . sprintf(ngettext('%1$s at-risk domain: %2$s overdue and %3$s expiring in the next %4$s days.', '%1$s at-risk domains: %2$s overdue and %3$s expiring in the next %4$s days.', $total_at_risk), number_format($total_at_risk), number_format($counts['overdue']), number_format($expiring_at_risk), number_format($risk_days));
if (array_sum($counts) > $total_at_risk) echo ' ' . _('A domain can have more than one reason, so the tiles can add up to more.');
echo '</p>'; ?>
<div class="row"><?php
    echo $dashboard->displayPanel(_('Overdue'), $counts['overdue'], 'danger', 'bi-exclamation-triangle', '/reporting/domains/at-risk.php?show=overdue#domain-list', _('At-Risk Domains'));
    echo $dashboard->displayPanel(sprintf(_('Auto Renewal Off (%s days)'), $risk_days), $counts['autorenew'], 'warning', 'bi-arrow-repeat', '/reporting/domains/at-risk.php?show=autorenew#domain-list', _('At-Risk Domains'));
    echo $dashboard->displayPanel(sprintf(_('Privacy Off (%s days)'), $risk_days), $counts['privacy'], 'secondary', 'bi-incognito', '/reporting/domains/at-risk.php?show=privacy#domain-list', _('At-Risk Domains'));
    echo $dashboard->displayPanel(_('No Fee or Rate'), $counts['nofee'], 'secondary', 'bi-question-circle', '/reporting/domains/at-risk.php?show=nofee#domain-list', _('At-Risk Domains')); ?>
</div>

<h2 class="h4 mt-4" id="domain-list"><?php echo $show === 'all' ? _('All At-Risk Domains') : $reasons[$show][0]; ?></h2><?php

if ($show !== 'all') { ?>
    <a href="<?php echo $report_filename; ?>#domain-list"><?php echo _('Show all at-risk domains'); ?></a><BR><BR><?php
}

if ($list) { ?>

    <strong><?php echo _('Number of Domains'); ?>:</strong> <?php echo number_format(count($list)); ?><BR><BR>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th><?php echo _('Domain'); ?></th>
            <th><?php echo _('Reasons'); ?></th>
            <th><?php echo _('Expiry Date'); ?></th>
            <th><?php echo _('Days Left'); ?></th>
            <th><?php echo _('Registrar'); ?></th>
            <th><?php echo _('Account'); ?></th>
            <th><?php echo _('Auto Renewal'); ?></th>
            <th><?php echo _('Privacy'); ?></th>
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
                <td><?php echo $yes_no($row->privacy); ?></td>
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
