<?php
/**
 * /install/email-system/index.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2019 Greg Chetcuti <greg@chetcuti.com>
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

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/install.email.system.inc.php';

$system->loginCheck();
$system->installCheck();

if ($_SERVER['REQUEST_METHOD'] != 'POST' && $_SESSION['new_system_email']) {

    $new_system_email1 = $_SESSION['new_system_email'];
    $new_system_email2 = $_SESSION['new_system_email'];

} else {

    $new_system_email1 = $_POST['new_system_email1'];
    $new_system_email2 = $_POST['new_system_email2'];

}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($new_system_email1 != '' && ($new_system_email1 === $new_system_email2)) {

        $_SESSION['new_system_email'] = $new_system_email1;

        header("Location: ../email-admin/");
        exit;

    } else {

        if ($new_system_email1 === '' && $new_system_email2 === '') {

            $_SESSION['s_message_danger'] .= "Please enter and confirm the system email address<BR>";

        } else {

            $_SESSION['s_message_danger'] .= "The system email addresses didn't match<BR>";

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
<body class="hold-transition skin-red" onLoad="document.forms[0].elements[0].focus()">
<?php require_once DIR_INC . '/layout/header-install.inc.php'; ?>
This email address will be used in various locations by the system, such as the FROM address when expiration emails are sent to users.<BR>
<BR>
<?php
echo $form->showFormTop('');
echo $form->showInputText('new_system_email1', 'Enter Email', '', $new_system_email1, '100', '', '', '', '');
echo $form->showInputText('new_system_email2', 'Confirm Email', '', $new_system_email2, '100', '', '', '', '');
?>
<BR>
<a href="../requirements/"><?php echo $layout->showButton('button', 'Go Back'); ?></a>
<?php
echo $form->showSubmitButton('Next Step', '', '');
echo $form->showFormBottom('');
?>
<?php require_once DIR_INC . '/layout/footer-install.inc.php'; ?>
</body>
</html>
