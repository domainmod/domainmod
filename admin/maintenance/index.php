<?php
/**
 * /admin/maintenance/index.php
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
require_once DIR_INC . '/settings/admin-maintenance-main.inc.php';

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
<?php echo $layout->contentBoxTop(_('Maintenance Tasks'), '3'); ?>
<a href="clear-queue-processing.php"><?php echo _('Clear Queue Processing'); ?></a><BR><BR>
<a href="clear-queues.php"><?php echo _('Clear Queues'); ?></a><BR><strong><?php echo strtoupper(_('Warning')); ?>:</strong> <?php echo _('This will completely delete all queue lists and domains, regardless of their status. This should not be run until all legitimate lists and domains in the queue have been successfully processed.'); ?><BR><BR>
<a href="clear-log.php"><?php echo _('Clear Debug Log'); ?></a>
<?php echo $layout->contentBoxBottom(); ?>

<?php //@formatter:on ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
