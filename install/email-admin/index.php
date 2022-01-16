<?php
/**
 * /install/email-admin/index.php
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
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/install.email.admin.inc.php';

$system->loginCheck();
$system->installCheck();

$new_admin_email1 = $sanitize->text($_POST['new_admin_email1']);
$new_admin_email2 = $sanitize->text($_POST['new_admin_email2']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($new_admin_email1 != '' && ($new_admin_email1 === $new_admin_email2)) {

        $_SESSION['new_admin_email'] = $new_admin_email1;

        header("Location: ../email-system/");
        exit;

    } else {

        if ($new_admin_email1 === '' && $new_admin_email2 === '') {

            $_SESSION['s_message_danger'] .= _('Please enter and confirm the administrator email address') . '<BR>';

        } else {

            $_SESSION['s_message_danger'] .= _("The administrator email addresses didn't match") . '<BR>';

        }

    }

}
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
<?php echo _('This email address will be used for the first administrator account, which will be created during the installation process.'); ?><BR>
<BR>
<?php
echo $form->showFormTop('');
echo $form->showInputText('new_admin_email1', _('Enter Email'), '', $unsanitize->text($new_admin_email1), '100', '', '', '', '');
echo $form->showInputText('new_admin_email2', _('Confirm Email'), '', $unsanitize->text($new_admin_email2), '100', '', '', '', '');
?>
<a href="../timezone/"><?php echo $layout->showButton('button', _('Go Back')); ?></a>
<?php
echo $form->showSubmitButton(_('Next Step'), '', '');
echo $form->showFormBottom('');
?>
<?php require_once DIR_INC . '/layout/footer-install.inc.php'; ?>
</body>
</html>
