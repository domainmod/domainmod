<?php
/**
 * /admin/dw/intro.php
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
$system->checkAdminUser($_SESSION['s_is_admin'] ?? 0);
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<div class="domainmod-intro">
<div class="mb-3"><?php echo $layout->linkButton('dw.php', _('Proceed to the Data Warehouse'), 'primary'); ?></div>

<p><?php echo sprintf(_('%s has a Data Warehouse framework built right into it, which allows you to import the data stored on your web server. Currently the only web servers that are supported are ones that run WHM/cPanel.'), SOFTWARE_TITLE); ?></p>
<p><?php echo sprintf(_("If your web server doesn't run WHM/cPanel, or you don't want to import your web server data into %s, you can ignore this section."), SOFTWARE_TITLE); ?></p>
<?php // A slim note (like the warnings on other pages, it isn't a page message, so it isn't announced when the page loads) ?>
<div class="alert alert-info d-flex align-items-start gap-2 py-2">
    <i class="bi bi-info-circle-fill domainmod-message-icon" aria-hidden="true"></i>
    <div class="domainmod-message-text"><?php echo sprintf(_('Importing your web server(s) into the Data Warehouse will %snot%s modify any of your other %s data, nor any of the data on your web server.'), '<strong>', '</strong>', SOFTWARE_TITLE); ?></div>
</div>

<h2 class="h3 mt-4"><?php echo _('Automating Builds'); ?></h2>
<p><?php echo sprintf(_("If you're going to use the Data Warehouse it's recommended that you set up the %ssystem cron/scheduled job%s in order to automate your builds."), '<a target="_blank" href="https://domainmod.org/docs/userguide/getting-started/#cron-job">', '</a>') . ' '; ?>
<?php echo _("There's a lot of work being done in the background during a build, and more often than not a web browser will time out if you try to build through the software instead of using the cron job, leading to incomplete and missing information in your Data Warehouse."); ?></p>
<p><?php echo sprintf(_('After you\'ve set up the cron job, you can manage the Data Warehouse\'s schedule in %1$s\'s %2$sTask Scheduler%3$s.'), SOFTWARE_TITLE, '<a href="' . WEB_ROOT . '/admin/scheduler/">', '</a>'); ?></p>

<h2 class="h3 mt-4"><?php echo _('Supported Data'); ?></h2>
<p><?php echo _('The following WHM sections are currently supported, but our end goal is to have every piece of WHM information that can be retrieved via the API stored in the Data Warehouse.'); ?></p>

<h3 class="h5"><?php echo _('Accounts'); ?></h3>
<p><?php echo _('Domain, IP Address, Owner, User, Contact Email, Plan, Theme, Shell, Partition, Disk Limit, Disk Usage, Max Addons, Max FTP Accounts, Max Email Lists, Max Parked Domains, Max POP Accounts, Max SQL Accounts, Max Subdomains, Creation Date, Suspend Status, Suspend Reason, Suspend Time, Max Email Per Hour, Failed Email % Before Defer, Min Failed Email # Before Defer'); ?></p>

<h3 class="h5"><?php echo _('DNS Zones'); ?></h3>
<p><?php echo _('Zone File Name, Original/Primary Source of Zone Data, Admin Email, Serial #, Refresh, Retry, Expiry, Minimum TTL, Authoritative Name Server'); ?></p>

<h3 class="h5"><?php echo _('DNS Records'); ?></h3>
<p><?php echo _('TTL, Class, Type, IP Address, CNAME, Mail Server, Mail Server Priority, TXT Data, Line # of Zone, # of Lines, RAW Data'); ?></p>
</div>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
