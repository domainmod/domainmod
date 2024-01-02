<?php
/**
 * /reporting/index.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2024 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: http://chetcuti.com
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
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm select2-red<?php echo $layout->bodyDarkMode(); ?>">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php echo sprintf(_('Before running any reports you should %supdate the conversion rates%s.'), '<a href="' . $web_root . '/maintenance/update-conversions.php">', '</a>'); ?>

<BR><BR>
<div class="row">
    <?php echo $layout->contentBoxTop(_('Domain Reports'), '3'); ?>
    <a href='domains/cost-by-category.php'><?php echo _('Cost by Category'); ?></a><BR>
    <a href='domains/cost-by-dns.php'><?php echo _('Cost by DNS Profile'); ?></a><BR>
    <a href='domains/cost-by-ip-address.php'><?php echo _('Cost by IP Address'); ?></a><BR>
    <a href='domains/cost-by-month.php'><?php echo _('Cost by Month'); ?></a><BR>
    <a href='domains/cost-by-owner.php'><?php echo _('Cost by Owner'); ?></a><BR>
    <a href='domains/cost-by-registrar.php'><?php echo _('Cost by Registrar'); ?></a><BR>
    <a href='domains/cost-by-tld.php'><?php echo _('Cost by TLD'); ?></a><BR>
    <a href='domains/cost-by-host.php'><?php echo _('Cost by Web Host'); ?></a><BR>
    <a href='domains/registrar-fees.php?all=0'><?php echo _('Registrar Fees'); ?></a>
    <?php echo $layout->contentBoxBottom(); ?>

    <?php echo $layout->contentBoxTop(_('SSL Certificate Reports'), '3'); ?>
    <a href='ssl/cost-by-category.php'><?php echo _('Cost by Category'); ?></a><BR>
    <a href='ssl/cost-by-domain.php'><?php echo _('Cost by Domain'); ?></a><BR>
    <a href='ssl/cost-by-ip-address.php'><?php echo _('Cost by IP Address'); ?></a><BR>
    <a href='ssl/cost-by-month.php'><?php echo _('Cost by Month'); ?></a><BR>
    <a href='ssl/cost-by-owner.php'><?php echo _('Cost by Owner'); ?></a><BR>
    <a href='ssl/cost-by-provider.php'><?php echo _('Cost by Provider'); ?></a><BR>
    <a href='ssl/cost-by-type.php'><?php echo _('Cost by Type'); ?></a><BR>
    <a href='ssl/provider-fees.php?all=0'><?php echo _('Provider Fees'); ?></a><BR>
    &nbsp;
    <?php echo $layout->contentBoxBottom(); ?>

    <?php echo $layout->contentBoxTop(_('Data Warehouse Reports'), '3'); ?>
    <a href='dw/potential-problems.php?generate=1'><?php echo _('Potential Problems'); ?></a><BR>
    &nbsp;<BR>
    &nbsp;<BR>
    &nbsp;<BR>
    &nbsp;<BR>
    &nbsp;<BR>
    &nbsp;<BR>
    &nbsp;<BR>
    &nbsp;
    <?php echo $layout->contentBoxBottom(); ?>

    <div class="col-md-3">
        &nbsp;
    </div>
</div>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
