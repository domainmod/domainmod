<?php
/**
 * /reporting/index.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2021 Greg Chetcuti <greg@chetcuti.com>
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

$report = $_REQUEST['report'];
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php echo sprintf(_('Before running any reports you should %supdate the conversion rates%s.'), '<a href="' . $web_root . '/maintenance/update-conversions.php">', '</a>'); ?>

<div class="row">
    <div class="col-md-3">
        <h3><?php echo _('Domain Reports'); ?></h3>
        <ul>
            <li><a href='domains/cost-by-category.php'><?php echo _('Cost by Category'); ?></a></li>
            <li><a href='domains/cost-by-dns.php'><?php echo _('Cost by DNS Profile'); ?></a></li>
            <li><a href='domains/cost-by-ip-address.php'><?php echo _('Cost by IP Address'); ?></a></li>
            <li><a href='domains/cost-by-month.php'><?php echo _('Cost by Month'); ?></a></li>
            <li><a href='domains/cost-by-owner.php'><?php echo _('Cost by Owner'); ?></a></li>
            <li><a href='domains/cost-by-registrar.php'><?php echo _('Cost by Registrar'); ?></a></li>
            <li><a href='domains/cost-by-tld.php'><?php echo _('Cost by TLD'); ?></a></li>
            <li><a href='domains/cost-by-host.php'><?php echo _('Cost by Web Host'); ?></a></li>
            <li><a href='domains/registrar-fees.php?all=0'><?php echo _('Registrar Fees'); ?></a></li>
        </ul>

    </div>
    <div class="col-md-3">
        <h3><?php echo _('SSL Certificate Reports'); ?></h3>
        <ul>
            <li><a href='ssl/cost-by-category.php'><?php echo _('Cost by Category'); ?></a></li>
            <li><a href='ssl/cost-by-domain.php'><?php echo _('Cost by Domain'); ?></a></li>
            <li><a href='ssl/cost-by-ip-address.php'><?php echo _('Cost by IP Address'); ?></a></li>
            <li><a href='ssl/cost-by-month.php'><?php echo _('Cost by Month'); ?></a></li>
            <li><a href='ssl/cost-by-owner.php'><?php echo _('Cost by Owner'); ?></a></li>
            <li><a href='ssl/cost-by-provider.php'><?php echo _('Cost by Provider'); ?></a></li>
            <li><a href='ssl/cost-by-type.php'><?php echo _('Cost by Type'); ?></a></li>
            <li><a href='ssl/provider-fees.php?all=0'><?php echo _('Provider Fees'); ?></a></li>
        </ul>
    </div>
    <div class="col-md-3">
        <h3><?php echo _('Data Warehouse Reports'); ?></h3>
        <ul>
            <li><a href='dw/potential-problems.php?generate=1'><?php echo _('Potential Problems'); ?></a></li>
        </ul>
    </div>
    <div class="col-md-3">
        &nbsp;
    </div>
</div>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
