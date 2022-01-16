<?php
/**
 * /admin/info/index.php
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

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/admin-info.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin']);
$pdo = $deeb->cnxx;
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<!body class="hold-transition skin-red sidebar-mini">
<body class="hold-transition sidebar-mini layout-fixed text-sm select2-red<?php echo $layout->bodyDarkMode(); ?>">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>

<strong><?php echo SOFTWARE_TITLE; ?> <?php echo _('Version'); ?>:</strong> <?php echo SOFTWARE_VERSION; ?> (<em><?php echo $_SESSION['s_system_db_version']; ?></em>)<BR>
<strong><?php echo _('Local IP Address'); ?>:</strong> <?php echo $_SERVER['SERVER_ADDR']; ?><BR>
<strong><?php echo _('Remote IP Address'); ?>:</strong> <?php echo $system->getIpRemotely(); ?><BR>
<strong><?php echo _('Web Server OS'); ?>:</strong> <?php echo php_uname(); ?><BR>
<strong><?php echo _('Web Server Software'); ?>:</strong> <?php echo $_SERVER['SERVER_SOFTWARE']; ?><BR>
<strong><?php echo _('PHP Version'); ?>:</strong> <?php echo phpversion(); ?><BR>
<strong><?php echo _('PHP Error Log Location'); ?>:</strong> <?php echo ini_get('error_log'); ?><BR>
<strong><?php echo _('MySQL Version'); ?>:</strong> <?php echo $pdo->query('select version()')->fetchColumn(); ?><BR>
<strong><?php echo _('MySQL Mode'); ?>:</strong> <?php echo $pdo->query('select @@sql_mode')->fetchColumn(); ?><BR>
<BR>
<?php
list($null, $requirements, $null) = $system->getRequirements();
echo $requirements;
?>



<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
