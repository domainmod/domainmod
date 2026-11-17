<?php
/**
 * /notice.php
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
require_once __DIR__ . '/_includes/start-session.inc.php';
require_once __DIR__ . '/_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$system = new DomainMOD\System();
$notice = new DomainMOD\Notice();
$layout = new DomainMOD\Layout();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/system-notice.inc.php';

$system->authCheck();

$action = $_GET['a'] ?? '';

// u = Upgrade DomainMOD Database, only while an upgrade is waiting. Otherwise there's no notice to show.
if (DomainMOD\Notice::showsUpgrade($action, $_SESSION['s_system_db_version'] ?? '', SOFTWARE_VERSION)) {

    // Only an administrator who isn't read-only gets the button. Anyone else is asked to have one run the upgrade.
    if ($notice->userCanRunUpgrade($_SESSION['s_user_id'] ?? 0)) {

        $notice->dbUpgrade();

    } else {

        $notice->dbUpgradeWaiting();

    }

} else {

    header('Location: ' . WEB_ROOT . '/dashboard/');
    exit;

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php
$page_align = 'center';
require_once DIR_INC . '/layout/header-bare.inc.php'; ?>
<div class="card card-outline card-danger mb-5">
    <div class="card-header">
        <h2 class="card-title fs-5"><i class="bi bi-info-circle text-danger me-2" aria-hidden="true"></i><?php echo $_SESSION['s_notice_page_title']; ?></h2>
    </div>
    <div class="card-body"><?php echo $_SESSION['s_notice']; ?></div>
</div>
<?php require_once DIR_INC . '/layout/footer-bare.inc.php'; ?>
</body>
</html>
