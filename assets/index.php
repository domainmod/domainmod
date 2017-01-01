<?php
/**
 * /assets/index.php
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
include("../_includes/start-session.inc.php");
include("../_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "settings/assets-main.inc.php");
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
<h3>Domains</h3>
<a href="registrars.php">Domain Registrars</a><BR>
<a href="registrar-accounts.php">Domain Registrar Accounts</a><BR>
<a href="dns.php">DNS Profiles</a><BR>
<a href="hosting.php">Web Hosting Providers</a>

<h3>SSL Certificates</h3>
<a href="ssl-providers.php">SSL Providers</a><BR>
<a href="ssl-accounts.php">SSL Provider Accounts</a><BR>
<a href="ssl-types.php">SSL Certificate Types</a>

<h3>Shared</h3>
<a href="account-owners.php">Account Owners</a><BR>
<a href="categories.php">Categories</a><BR>
<a href="ip-addresses.php">IP Addresses</a><BR><BR>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
