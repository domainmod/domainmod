<?php
/**
 * /debug/off/index.php
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

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/settings/system-debug.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'] ?? 0);
$csrf = new DomainMOD\Csrf();
// A refused request loads debug.inc.php first, so the refusal is logged in debugging mode, as on other pages.
if (!$csrf->isValid()) {
    require_once DIR_INC . '/debug.inc.php';
    $csrf->check($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/');
}
$system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/'); // read-only users can't delete or change data

$deeb->cnxx->query("UPDATE settings SET debug_mode = '0'");

// Read the setting only once it has changed, so the debugging alert at the top of the page matches it.
require_once DIR_INC . '/debug.inc.php';
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<p><?php echo _('Debugging mode is off.'); ?></p>
<div class="d-flex flex-wrap gap-2"><?php
echo $layout->linkButton('../on/?' . $csrf->query(), _('Turn Debugging Mode On')); ?>
</div>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
