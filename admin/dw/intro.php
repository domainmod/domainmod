<?php
/**
 * /admin/dw/intro.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>
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
require_once('../../_includes/start-session.inc.php');
require_once('../../_includes/init.inc.php');

require_once(DIR_ROOT . '/classes/Autoloader.php');
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();

require_once(DIR_INC . '/head.inc.php');
require_once(DIR_INC . '/config.inc.php');
require_once(DIR_INC . '/software.inc.php');
require_once(DIR_INC . '/settings/dw-intro.inc.php');
require_once(DIR_INC . '/database.inc.php');

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin']);
?>
<?php require_once(DIR_INC . '/doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($page_title); ?></title>
    <?php require_once(DIR_INC . '/layout/head-tags.inc.php'); ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php require_once(DIR_INC . '/layout/header.inc.php'); ?>
<BR><a href="dw.php"><?php echo $layout->showButton('button', 'Proceed to the Data Warehouse'); ?></a><BR><BR>

<?php echo SOFTWARE_TITLE; ?> has a Data Warehouse framework built right into it, which allows you to import the data stored on your web server. Currently the only web servers that are supported are ones that run WHM/cPanel.<BR>
<BR>
If your web server doesn't run WHM/cPanel, or you don't want to import your web server data into <?php echo SOFTWARE_TITLE; ?>, you can ignore this section.<BR>
<BR>
<strong>NOTE:</strong> Importing your web server(s) into the Data Warehouse will <strong>not</strong> modify any of your other <?php echo SOFTWARE_TITLE; ?> data, nor any of the data on your web server.<BR>

<h3>Automating Builds</h3>
If you're going to use the Data Warehouse it's recommended that you setup the <a target="_blank" href="https://domainmod.org/docs/userguide/getting-started/#cron-job">system cron/scheduled job</a> in order to automate your builds. There's a lot of work being done in the background during a build, and more often than not a web browser will timeout if you try to build through the software instead of using the cron job, leading to incomplete and missing information in your Data Warehouse. After you've setup the cron job you will be able to manage the Data Warehouse scheduling through <?php echo SOFTWARE_TITLE; ?>'s <a target="_blank" href="https://domainmod.org/docs/userguide/administration#task-scheduler">Task Scheduler</a>.<BR>

<h3>Supported Data</h3>
The following WHM sections are currently supported, but our end goal is to have every piece of WHM information that can be retrieved via the API stored in the Data Warehouse.<BR>
<BR>

<strong>Accounts</strong><BR>
Domain, IP Address, Owner, User, Contact Email, Plan, Theme, Shell, Partition, Disk Limit, Disk Usage, Max Addons, Max
FTP Accounts, Max Email Lists, Max Parked Domains, Max POP Accounts, Max SQL Accounts, Max Subdomains, Creation Date,
Suspend Status, Suspend Reason, Suspend Time, Max Email Per Hour, Failed Email % Before Defer, Min Failed Email # Before
Defer<BR>
<BR>
<strong>DNS Zones</strong><BR>
Zone File Name, Original/Primary Source of Zone Data, Admin Email, Serial #, Refresh, Retry, Expiry, Minimum TTL,
Authoritative Name Server<BR>
<BR>
<strong>DNS Records</strong><BR>
TTL, Class, Type, IP Address, CNAME, Mail Server, Mail Server Priority, TXT Data, Line # of Zone, # of Lines, RAW
Data<BR><BR>
<?php require_once(DIR_INC . '/layout/footer.inc.php'); ?>
</body>
</html>
