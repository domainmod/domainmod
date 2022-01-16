<?php
/**
 * /assets/index.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2022 Greg Chetcuti <greg@chetcuti.com>
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

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/assets-main.inc.php';

$system->authCheck();
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm select2-red<?php echo $layout->bodyDarkMode(); ?>">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>

<?php echo _('Assets are the building blocks of DomainMOD.'); ?>&nbsp;<?php echo _('Assets include things like the domain registrars and accounts that you use, the SSL certificate types that you own, your DNS Servers and IP Addresses, the categories you want to use for grouping similar domains, and so on.'); ?><BR>
<BR>

<div class="row">
    <?php echo $layout->contentBoxTop(_('Domains'), '3'); ?>
    <a href="registrars.php"><?php echo _('Domain Registrars'); ?></a><BR>
    <a href="registrar-accounts.php"><?php echo _('Domain Registrar Accounts'); ?></a><BR>
    <a href="dns.php"><?php echo _('DNS Profiles'); ?></a><BR>
    <a href="hosting.php"><?php echo _('Web Hosting Providers'); ?></a>
    <?php echo $layout->contentBoxBottom(); ?>

    <?php echo $layout->contentBoxTop(_('SSL Certificates'), '3'); ?>
    <a href="ssl-providers.php"><?php echo _('SSL Providers'); ?></a><BR>
    <a href="ssl-accounts.php"><?php echo _('SSL Provider Accounts'); ?></a><BR>
    <a href="ssl-types.php"><?php echo _('SSL Certificate Types'); ?></a><BR>
    &nbsp;
    <?php echo $layout->contentBoxBottom(); ?>

    <?php echo $layout->contentBoxTop(_('Shared'), '3'); ?>
    <a href="account-owners.php"><?php echo _('Account Owners'); ?></a><BR>
    <a href="categories.php"><?php echo _('Categories'); ?></a><BR>
    <a href="ip-addresses.php"><?php echo _('IP Addresses'); ?></a><BR>
    &nbsp;
    <?php echo $layout->contentBoxBottom(); ?>

    <div class="col-md-3">
        &nbsp;
    </div>
</div>

<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
