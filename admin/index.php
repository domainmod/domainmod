<?php
/**
 * /admin/index.php
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
<h2 class="h5 mt-2"><?php echo _('System'); ?></h2>
<div class="row g-3"><?php
    echo $layout->landingTile('/admin/settings/', _('System Settings'), _('The URL, email, expiry days, and system options'), 'bi-sliders', 'secondary');
    echo $layout->landingTile('/admin/defaults/', _('System Defaults'), _('The default assets for new domains and SSL certificates'), 'bi-ui-checks', 'secondary');
    echo $layout->landingTile('/admin/users/', _('Users'), _('Add, edit, and remove users'), 'bi-people', 'secondary');
    echo $layout->landingTile('/admin/domain-fields/', _('Custom Domain Fields'), _('Your own fields on domains'), 'bi-input-cursor-text', 'secondary');
    echo $layout->landingTile('/admin/ssl-fields/', _('Custom SSL Fields'), _('Your own fields on SSL certificates'), 'bi-input-cursor', 'secondary'); ?>
</div>

<h2 class="h5 mt-4"><?php echo _('Tasks'); ?></h2>
<div class="row g-3"><?php
    echo $layout->landingTile('/admin/scheduler/', _('Task Scheduler'), _('Scheduled tasks, when they run, and Run Now'), 'bi-clock-history', 'secondary');
    echo $layout->landingTile('/admin/maintenance/', _('Maintenance'), _('Clear the queues and the debug log'), 'bi-tools', 'secondary');
    echo $layout->landingTile('/admin/backup/', _('Backup & Restore'), _('Back up the database, or restore a backup'), 'bi-archive', 'secondary'); ?>
</div>

<h2 class="h5 mt-4"><?php echo _('Diagnostics'); ?></h2>
<div class="row g-3"><?php
    echo $layout->landingTile('/admin/tools/', _('Tools'), _('Send a test email to check that email works'), 'bi-wrench-adjustable', 'secondary');
    echo $layout->landingTile('/admin/debug-log/', _('Debug Log'), _('What DomainMOD logged while debugging was on'), 'bi-journal-text', 'secondary');
    echo $layout->landingTile('/admin/info/', _('System Information'), _('PHP, MySQL, and server details for support'), 'bi-info-circle', 'secondary'); ?>
</div>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
