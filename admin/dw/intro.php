<?php
/**
 * /admin/dw/intro.php
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
require_once __DIR__ . '/../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/dw-intro.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin']);
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm select2-red<?php echo $layout->bodyDarkMode(); ?>">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<a href="dw.php"><?php echo $layout->showButton('button', _('Proceed to the Data Warehouse')); ?></a><BR><BR>

<?php echo sprintf(_('%s has a Data Warehouse framework built right into it, which allows you to import the data stored on your web server. Currently the only web servers that are supported are ones that run WHM/cPanel.'), SOFTWARE_TITLE); ?><BR>
<BR>
<?php echo sprintf(_("If your web server doesn't run WHM/cPanel, or you don't want to import your web server data into %s, you can ignore this section."), SOFTWARE_TITLE); ?><BR>
<BR>
<strong><?php echo strtoupper(_('Note')); ?>:</strong> <?php echo sprintf(_('Importing your web server(s) into the Data Warehouse will %snot%s modify any of your other %s data, nor any of the data on your web server.'), '<strong>', '</strong>', SOFTWARE_TITLE); ?><BR>

<BR><h3><?php echo _('Automating Builds'); ?></h3>
<?php echo sprintf(_("If you're going to use the Data Warehouse it's recommended that you setup the %ssystem cron/scheduled job%s in order to automate your builds."), '<a target="_blank" href="https://domainmod.org/docs/userguide/getting-started/#cron-job">', '</a>'); ?>

<?php echo _("There's a lot of work being done in the background during a build, and more often than not a web browser will timeout if you try to build through the software instead of using the cron job, leading to incomplete and missing information in your Data Warehouse."); ?>

<?php echo sprintf(_("After you've setup the cron job you will be able to manage the Data Warehouse scheduling through %s's Task Scheduler"), SOFTWARE_TITLE, '<a target="_blank" href="https://domainmod.org/docs/userguide/administration#task-scheduler">', '</a>'); ?><BR>

<BR>
<h3><?php echo _('Supported Data'); ?></h3>
<?php echo _('The following WHM sections are currently supported, but our end goal is to have every piece of WHM information that can be retrieved via the API stored in the Data Warehouse.'); ?><BR>
<BR>

<strong><?php echo _('Accounts'); ?></strong><BR>
<?php echo _('Domain, IP Address, Owner, User, Contact Email, Plan, Theme, Shell, Partition, Disk Limit, Disk Usage, Max Addons, Max FTP Accounts, Max Email Lists, Max Parked Domains, Max POP Accounts, Max SQL Accounts, Max Subdomains, Creation Date, Suspend Status, Suspend Reason, Suspend Time, Max Email Per Hour, Failed Email % Before Defer, Min Failed Email # Before Defer'); ?><BR>
<BR>
<strong><?php echo _('DNS Zones'); ?></strong><BR>
<?php echo _('Zone File Name, Original/Primary Source of Zone Data, Admin Email, Serial #, Refresh, Retry, Expiry, Minimum TTL, Authoritative Name Server'); ?><BR>
<BR>
<strong><?php echo _('DNS Records'); ?></strong><BR>
<?php echo _('TTL, Class, Type, IP Address, CNAME, Mail Server, Mail Server Priority, TXT Data, Line # of Zone, # of Lines, RAW Data'); ?><BR><BR>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
