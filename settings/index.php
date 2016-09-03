<?php
/**
 * /settings/index.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2016 Greg Chetcuti <greg@chetcuti.com>
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
include("../_includes/start-session.inc.php");
include("../_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "settings/settings-main.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck($web_root);
?>
<?php include(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
    <?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<h3>User Settings</h3>
<a href="display/">Display Settings</a>&nbsp;&nbsp;/&nbsp;&nbsp;
<a href="defaults/">User Defaults</a>&nbsp;&nbsp;/&nbsp;&nbsp;
<a href="profile/">User Profile</a>&nbsp;&nbsp;/&nbsp;&nbsp;
<a href="password/">Change Password</a><BR>
<BR>
<h3>Maintenance</h3>
<a href="maintenance/update-domain-fees.php">Update Domain Fees</a>&nbsp;&nbsp;/&nbsp;&nbsp;
<a href="maintenance/update-ssl-fees.php">Update SSL Certificate Fees</a>&nbsp;&nbsp;/&nbsp;&nbsp;
<a href="maintenance/update-conversions.php">Update Conversion Rates</a>&nbsp;&nbsp;/&nbsp;&nbsp;
<a href="maintenance/clear-queue-processing.php">Clear Queue Processing</a><BR><BR>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
