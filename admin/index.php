<?php
/**
 * /admin/index.php
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
require_once DIR_INC . '/settings/admin-main.inc.php';

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
<a href="<?php echo $web_root; ?>/admin/settings/"><?php echo _('System Settings'); ?></a><BR><BR>
<a href="<?php echo $web_root; ?>/admin/defaults/"><?php echo _('System Defaults'); ?></a><BR><BR>
<a href="<?php echo $web_root; ?>/admin/users/"><?php echo _('Users'); ?></a><BR><BR>
<a href="<?php echo $web_root; ?>/admin/domain-fields/"><?php echo _('Custom Domain Fields'); ?></a><BR><BR>
<a href="<?php echo $web_root; ?>/admin/ssl-fields/"><?php echo _('Custom SSL Fields'); ?></a><BR><BR>
<a href="<?php echo $web_root; ?>/admin/scheduler/"><?php echo _('Task Scheduler'); ?></a><BR><BR>
<a href="<?php echo $web_root; ?>/admin/maintenance/"><?php echo _('Maintenance'); ?></a><BR><BR>
<a href="<?php echo $web_root; ?>/admin/backup/"><?php echo _('Backup & Restore'); ?></a><BR><BR>
<a href="<?php echo $web_root; ?>/admin/debug-log/"><?php echo _('Debug Log'); ?></a><BR><BR>
<a href="<?php echo $web_root; ?>/admin/info/"><?php echo _('System Information'); ?></a><BR>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
