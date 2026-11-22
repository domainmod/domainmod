<?php
/**
 * /reporting/index.php
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
require_once __DIR__ . '/../_includes/start-session.inc.php';
require_once __DIR__ . '/../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();
$form = new DomainMOD\Form();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/reporting-main.inc.php';

$system->authCheck();

$report = $_REQUEST['report'] ?? '';
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
// Read-only users can't update the conversion rates, so they don't get the reminder to
if (($_SESSION['s_read_only'] ?? '') != '1') {
    echo $layout->calloutTop() . sprintf(_('Before running any reports you should %supdate the conversion rates%s.'), '<a href="' . $web_root . '/maintenance/update-conversions.php?return=reporting&amp;' . (new DomainMOD\Csrf())->query() . '">', '</a>') . $layout->calloutBottom();
} ?>
<h2 class="h5 mb-3"><?php echo _('Renewals'); ?></h2>
<div class="row g-3"><?php
    echo $layout->landingTile('/reporting/renewal-calendar.php', _('Renewal Calendar'), _('What expires on each day, domains and SSL together'), 'bi-calendar3', 'info');
    echo $layout->landingTile('/reporting/domains/upcoming-renewals.php', _('Domain Renewals'), _('What renewals will cost, month by month'), 'bi-calendar-event', 'danger');
    echo $layout->landingTile('/reporting/ssl/upcoming-renewals.php', _('SSL Renewals'), _('What SSL renewals will cost, month by month'), 'bi-calendar-event', 'danger'); ?>
</div>
<h2 class="h5 mt-4 mb-3"><?php echo _('Pricing'); ?></h2>
<div class="row g-3"><?php
    echo $layout->landingTile('/reporting/domains/tld-pricing.php', _('TLD Pricing'), _('The cheapest registrar for each TLD, and possible savings'), 'bi-tags', 'success');
    echo $layout->landingTile('/reporting/ssl/ssl-pricing.php', _('SSL Pricing'), _('The cheapest provider for each certificate type'), 'bi-shield-lock', 'success');
    echo $layout->landingTile('/reporting/domains/registrar-fees.php?all=0', _('Domain Fees'), _("Each registrar's fees for your TLDs, and any that are missing"), 'bi-cash-coin', 'success');
    echo $layout->landingTile('/reporting/ssl/provider-fees.php?all=0', _('SSL Fees'), _("Each SSL provider's fees for your certificate types, and any that are missing"), 'bi-cash-coin', 'success'); ?>
</div>
<h2 class="h5 mt-4 mb-3"><?php echo _('Needs Attention'); ?></h2>
<div class="row g-3"><?php
    echo $layout->landingTile('/reporting/domains/at-risk.php', _('At-Risk Domains'), sprintf(_('Expiring in the next %s days without auto-renewal or privacy'), DomainMOD\Reporting::AT_RISK_DAYS), 'bi-exclamation-triangle', 'warning');
    echo $layout->landingTile('/reporting/ssl/at-risk.php', _('At-Risk SSL Certificates'), sprintf(_('Overdue, or expiring in the next %s days'), DomainMOD\Reporting::AT_RISK_DAYS), 'bi-shield-exclamation', 'warning');
    // To show this tile to admins only (as the sidebar's Data Warehouse section is), wrap it in
    // if (isset($_SESSION['s_is_admin']) && $_SESSION['s_is_admin'] === 1) { ... }
    echo $layout->landingTile('/reporting/dw/potential-problems.php', _('Potential Problems'), _('Data Warehouse accounts and zones that need a look'), 'bi-heart-pulse', 'info');
    // The Change History filtered to domains (its default range is the last 30 days)
    echo $layout->landingTile('/history/?type=domain', _('Recent Domain Changes'), _('What changed on your domains in the last 30 days'), 'bi-clock-history', 'info'); ?>
</div>
<div class="row g-3 mt-4"><?php
    echo $layout->landingLinks(_('Domain Costs'), array(
        array('/reporting/domains/costs.php?group=category', _('Category'), _('Domain Cost by Category')),
        array('/reporting/domains/costs.php?group=dns', _('DNS Profile'), _('Domain Cost by DNS Profile')),
        array('/reporting/domains/costs.php?group=ip-address', _('IP Address'), _('Domain Cost by IP Address')),
        array('/reporting/domains/costs.php?group=month', _('Month'), _('Domain Cost by Month')),
        array('/reporting/domains/costs.php?group=owner', _('Owner'), _('Domain Cost by Owner')),
        array('/reporting/domains/costs.php?group=registrar', _('Registrar'), _('Domain Cost by Registrar')),
        array('/reporting/domains/costs.php?group=tld', _('TLD'), _('Domain Cost by TLD')),
        array('/reporting/domains/costs.php?group=host', _('Web Host'), _('Domain Cost by Web Host'))));
    echo $layout->landingLinks(_('SSL Costs'), array(
        array('/reporting/ssl/costs.php?group=category', _('Category'), _('SSL Cost by Category')),
        array('/reporting/ssl/costs.php?group=domain', _('Domain'), _('SSL Cost by Domain')),
        array('/reporting/ssl/costs.php?group=ip-address', _('IP Address'), _('SSL Cost by IP Address')),
        array('/reporting/ssl/costs.php?group=month', _('Month'), _('SSL Cost by Month')),
        array('/reporting/ssl/costs.php?group=owner', _('Owner'), _('SSL Cost by Owner')),
        array('/reporting/ssl/costs.php?group=provider', _('Provider'), _('SSL Cost by Provider')),
        array('/reporting/ssl/costs.php?group=type', _('Type'), _('SSL Cost by Type')))); ?>
</div>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
