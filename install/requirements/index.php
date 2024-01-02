<?php
/**
 * /install/requirements/index.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2024 Greg Chetcuti <greg@chetcuti.com>
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
require_once DIR_INC . '/settings/install.requirements.inc.php';

$system->loginCheck();
$system->installCheck();
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <?php
    if ($page_title != "") { ?>
        <title><?php echo $layout->pageTitle($page_title); ?></title><?php
    } else { ?>
        <title><?php echo SOFTWARE_TITLE; ?></title><?php
    } ?>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition text-sm">
<?php require_once DIR_INC . '/layout/header-install.inc.php'; ?>
<?php echo _("The first thing we need to do is check to see if your web server meets the software's requirements."); ?><BR>
<BR>
<?php echo sprintf(_("All of the below items should say %s or %s. If they don't, you still may be able to install %s, but certain features might not work completely."), $layout->highlightText('green', 'Pass'), $layout->highlightText('green', 'Enabled'), SOFTWARE_TITLE); ?><BR>
<BR>
<?php echo sprintf(_('If any of the items say %s or %s, we recommend you install and/or update the appropriate software so that all of the requirements are met.'), $layout->highlightText('red', 'Fail'), $layout->highlightText('red', 'Disabled')); ?><BR>
<BR>
<?php
list($null, $null, $requirements) = $system->getRequirements();
echo $requirements;
echo "<BR>";
?>
<a href="../language/"><?php echo $layout->showButton('button', _('Go Back')); ?></a>
<a href="<?php echo WEB_ROOT; ?>/install/currency/"><?php echo $layout->showButton('button', _('Next Step')); ?></a>
<?php require_once DIR_INC . '/layout/footer-install.inc.php'; ?>
</body>
</html>
