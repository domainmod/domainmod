<?php
/**
 * /reporting/ssl/at-risk.php
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
require_once DIR_INC . '/settings/reporting-ssl-at-risk.inc.php';

$system->authCheck();
// Rates for every currency in use, or the lists and reports below would leave out what uses a missing one
(new DomainMOD\Conversion())->ensureRates($_SESSION['s_user_id'] ?? 0, $_SESSION['s_default_currency'] ?? '');
$pdo = $deeb->cnxx;

$export_data = (int) ($_GET['export_data'] ?? 0);
// Which certificates the list shows: all of them, or those a tile counts (anything else shows all)
$show = (string) ($_GET['show'] ?? 'all');
if (!in_array($show, array('all', 'expiring', 'domain', 'overdue', 'nofee'), true)) $show = 'all';

$expiration_days = (int) $pdo->query("
    SELECT expiration_days
    FROM settings")->fetchColumn();

$today = $time->timeBasic();
$risk_days = DomainMOD\Reporting::AT_RISK_DAYS;
$risk_end = $time->timeBasicPlusDays($risk_days);
$at_risk = $reporting->atRiskCerts($reporting->renewalCerts($time->timeBasicPlusDays($risk_days + 1)), $today, $risk_end);

$counts = array('expiring' => 0, 'domain' => 0, 'overdue' => 0, 'nofee' => 0);
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

$days_left_text = function ($row) use ($days_left) {
    $left = $days_left($row);
    return $left < 0 ? _('Overdue') : number_format($left);
};

// The reasons, as badges in the list and as plain text in the export. Domain Problem's tooltip names the problem.
$reasons = array(
    'overdue' => array(_('Overdue'), _('Past its expiry date'), 'danger'),
    'expiring' => array(_('Expiring Soon'), sprintf(_('Expires in the next %s days'), $risk_days), 'warning'),
    'domain' => array(_('Domain Problem'), '', 'warning'),
    'nofee' => array(_('No Fee or Rate'), _('No fee, or no conversion rate for its currency'), 'secondary'));
$domain_problems = array(
    'expired' => _('Its domain has expired'),
    'inactive' => _('Its domain is inactive'),
    'first' => _('Its domain expires before the certificate'),
    'missing' => _('Its domain is missing'));

$reason_names = function ($row) use ($reasons) {
    return implode(', ', array_map(function ($key) use ($reasons) { return $reasons[$key][0]; }, $row->reasons));
};

$intro = sprintf(_('SSL certificates expiring in the next %1$s days, plus any past their expiry date. This report always looks %1$s days ahead, separately from the Expiration Days setting (currently %2$s) that the Dashboard and the reminder emails use. DomainMOD doesn\'t record whether a certificate renews itself, so every certificate in the window is listed.'), $risk_days, sprintf(ngettext('%s day', '%s days', $expiration_days), $expiration_days));

if ($export_data === 1 && $list) {

    $export = new DomainMOD\Export();
    $export_file = $export->openFile(_('ssl_at_risk_certificates_report'), $today);

    $row_contents = array($page_title);
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array($intro);
    $export->writeRow($export_file, $row_contents);

    $row_contents = array(_('Number of SSL Certs') . ':', number_format(count($list)));
    $export->writeRow($export_file, $row_contents);

    $export->writeBlankRow($export_file);

    $row_contents = array(
        _('Certificate'),
        _('Domain'),
        _('Domain Expiry'),
        _('Expiry Date'),
        _('Days Left'),
        _('Provider'),
        _('Account'),
        _('Type'),
        _('Renewal Cost'),
        _('Reasons')
    );
    $export->writeRow($export_file, $row_contents);

    foreach ($list as $row) {
        $row_contents = array(
            $row->name,
            $row->domain,
            $row->domain_expiry_date,
            $row->expiry_date,
            $days_left_text($row),
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

<a href="<?php echo $report_filename; ?>"><?php echo $layout->showButton('button', _('View Full Report')); ?></a>&nbsp;&nbsp;<?php
if ($list) { ?>
    <a href="<?php echo $report_filename; ?>?export_data=1&show=<?php echo $show; ?>"><?php echo $layout->showButton('button', _('Export Report')); ?></a><?php
} ?>
<BR><BR>

<?php
// The tiles count reasons, and a certificate can have more than one, so a line first gives the number behind them
$total_at_risk = count($at_risk);
if ($counts['overdue'] === 0) {
    echo '<p class="domainmod-intro">' . sprintf(ngettext('%s at-risk SSL certificate, not overdue.', '%s at-risk SSL certificates, none of them overdue.', $total_at_risk), number_format($total_at_risk));
} else {
    echo '<p class="domainmod-intro">' . sprintf(ngettext('%1$s at-risk SSL certificate, %2$s overdue.', '%1$s at-risk SSL certificates, %2$s of them overdue.', $total_at_risk), number_format($total_at_risk), number_format($counts['overdue']));
}
if (array_sum($counts) > $total_at_risk) echo ' ' . _('A certificate can have more than one reason, so the tiles can add up to more.');
echo '</p>'; ?>
<div class="row"><?php
    echo $dashboard->displayPanel(_('Overdue'), $counts['overdue'], 'danger', 'bi-exclamation-triangle', '/reporting/ssl/at-risk.php?show=overdue#cert-list', _('SSL At-Risk Certificates'));
    echo $dashboard->displayPanel(sprintf(_('Expiring Soon (%s days)'), $risk_days), $counts['expiring'], 'warning', 'bi-calendar-event', '/reporting/ssl/at-risk.php?show=expiring#cert-list', _('SSL At-Risk Certificates'));
    echo $dashboard->displayPanel(_('Domain Problem'), $counts['domain'], 'warning', 'bi-globe', '/reporting/ssl/at-risk.php?show=domain#cert-list', _('SSL At-Risk Certificates'));
    echo $dashboard->displayPanel(_('No Fee or Rate'), $counts['nofee'], 'secondary', 'bi-question-circle', '/reporting/ssl/at-risk.php?show=nofee#cert-list', _('SSL At-Risk Certificates')); ?>
</div>

<h4 class="mt-4" id="cert-list"><?php echo $show === 'all' ? _('All At-Risk SSL Certificates') : $reasons[$show][0]; ?></h4><?php

if ($show !== 'all') { ?>
    <a href="<?php echo $report_filename; ?>#cert-list"><?php echo _('Show all at-risk SSL certificates'); ?></a><BR><BR><?php
}

if ($list) { ?>

    <strong><?php echo _('Number of SSL Certs'); ?>:</strong> <?php echo number_format(count($list)); ?><BR><BR>

    <table id="<?php echo $slug; ?>" class="<?php echo $datatable_class; ?>">
        <thead>
        <tr>
            <th width="20px"></th>
            <th><?php echo _('Certificate'); ?></th>
            <th><?php echo _('Reasons'); ?></th>
            <th><?php echo _('Domain'); ?></th>
            <th><?php echo _('Domain Expiry'); ?></th>
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
                <td data-order="<?php echo htmlspecialchars($reason_names($row), ENT_QUOTES, 'UTF-8'); ?>"><?php
                    foreach ($row->reasons as $key) {
                        $description = $key === 'domain' ? $domain_problems[$row->domain_problem] : $reasons[$key][1];
                        echo $layout->statusBadge($reasons[$key][0], $description, $reasons[$key][2]) . ' ';
                    } ?></td>
                <td><?php if ($row->domain_id !== null) { ?><a href="<?php echo WEB_ROOT; ?>/domains/edit.php?did=<?php echo (int) $row->domain_id; ?>"><?php echo $row->domain; ?></a><?php } ?></td>
                <td data-order="<?php echo (string) $row->domain_expiry_date; ?>"><?php echo $row->domain_expiry_date; ?></td>
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
</body>
</html>
