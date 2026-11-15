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
<?php echo $layout->calloutTop(); ?>
<?php echo sprintf(_('Before running any reports you should %supdate the conversion rates%s.'), '<a href="' . $web_root . '/maintenance/update-conversions.php?' . (new DomainMOD\Csrf())->query() . '">', '</a>'); ?>
<?php echo $layout->calloutBottom(); ?>
<h2 class="h5 mb-3"><?php echo _('Renewals'); ?></h2>
<div class="row g-3"><?php
    echo $layout->landingTile('/reporting/renewal-calendar.php', _('Renewal Calendar'), _('What expires on each day, domains and SSL together'), 'bi-calendar3', 'info');
    echo $layout->landingTile('/reporting/domains/upcoming-renewals.php', _('Upcoming Renewals'), _('What renewals will cost, month by month'), 'bi-calendar-event', 'danger');
    echo $layout->landingTile('/reporting/ssl/upcoming-renewals.php', _('SSL Upcoming Renewals'), _('What SSL renewals will cost, month by month'), 'bi-calendar-event', 'danger');
    echo $layout->landingTile('/reporting/domains/tld-pricing.php', _('TLD Pricing'), _('The cheapest registrar for each TLD, and possible savings'), 'bi-tags', 'success'); ?>
</div>
<h2 class="h5 mt-4 mb-3"><?php echo _('Needs Attention'); ?></h2>
<div class="row g-3"><?php
    echo $layout->landingTile('/reporting/domains/at-risk.php', _('At-Risk Domains'), sprintf(_('Expiring in the next %s days without auto renewal or privacy'), DomainMOD\Reporting::AT_RISK_DAYS), 'bi-exclamation-triangle', 'warning');
    echo $layout->landingTile('/reporting/ssl/at-risk.php', _('SSL At-Risk Certificates'), sprintf(_('Overdue, or expiring in the next %s days'), DomainMOD\Reporting::AT_RISK_DAYS), 'bi-shield-exclamation', 'warning');
    // To show this tile to admins only (as the sidebar's Data Warehouse section is), wrap it in
    // if (isset($_SESSION['s_is_admin']) && $_SESSION['s_is_admin'] === 1) { ... }
    echo $layout->landingTile('/reporting/dw/potential-problems.php', _('Potential Problems'), _('Data Warehouse accounts and zones that need a look'), 'bi-heart-pulse', 'info'); ?>
</div>
<div class="row g-3 mt-4"><?php
    echo $layout->landingLinks(_('Domain Costs'), array(
        array('/reporting/domains/cost-by-category.php', _('Category'), _('Domain Cost by Category')),
        array('/reporting/domains/cost-by-dns.php', _('DNS Profile'), _('Domain Cost by DNS Profile')),
        array('/reporting/domains/cost-by-ip-address.php', _('IP Address'), _('Domain Cost by IP Address')),
        array('/reporting/domains/cost-by-month.php', _('Month'), _('Domain Cost by Month')),
        array('/reporting/domains/cost-by-owner.php', _('Owner'), _('Domain Cost by Owner')),
        array('/reporting/domains/cost-by-registrar.php', _('Registrar'), _('Domain Cost by Registrar')),
        array('/reporting/domains/cost-by-tld.php', _('TLD'), _('Domain Cost by TLD')),
        array('/reporting/domains/cost-by-host.php', _('Web Host'), _('Domain Cost by Web Host')),
        array('/reporting/domains/registrar-fees.php?all=0', _('Registrar Fees'), _('Registrar Fees'))));
    echo $layout->landingLinks(_('SSL Costs'), array(
        array('/reporting/ssl/cost-by-category.php', _('Category'), _('SSL Cost by Category')),
        array('/reporting/ssl/cost-by-domain.php', _('Domain'), _('SSL Cost by Domain')),
        array('/reporting/ssl/cost-by-ip-address.php', _('IP Address'), _('SSL Cost by IP Address')),
        array('/reporting/ssl/cost-by-month.php', _('Month'), _('SSL Cost by Month')),
        array('/reporting/ssl/cost-by-owner.php', _('Owner'), _('SSL Cost by Owner')),
        array('/reporting/ssl/cost-by-provider.php', _('Provider'), _('SSL Cost by Provider')),
        array('/reporting/ssl/cost-by-type.php', _('Type'), _('SSL Cost by Type')),
        array('/reporting/ssl/provider-fees.php?all=0', _('Provider Fees'), _('Provider Fees')))); ?>
</div>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
