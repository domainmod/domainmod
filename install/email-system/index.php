<?php
/**
 * /install/email-system/index.php
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

$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();
$form = new DomainMOD\Form();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/install.email.system.inc.php';

$system->loginCheck();
$system->installCheck();

$_SESSION['new_system_email'] = $_SESSION['new_system_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] != 'POST') {

    $new_system_email = $unsanitize->text($_SESSION['new_system_email']);

} else {

    $new_system_email = trim((string) ($_POST['new_system_email'] ?? ''));

}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // The installer runs while signed out, so it checks the security token itself (see DomainMOD\Csrf).
    $csrf = new DomainMOD\Csrf();
    $csrf->check($_SERVER['REQUEST_URI'] ?? '');

    if ($new_system_email === '') {

        $form->addFieldError('new_system_email', _('Enter the system email address'));

    } elseif (filter_var($new_system_email, FILTER_VALIDATE_EMAIL) === false || preg_match('/[\x{10000}-\x{10FFFF}]/u', $new_system_email)) {

        $form->addFieldError('new_system_email', _('Enter a valid email address'));

    } elseif (mb_strlen($sanitize->text($new_system_email), 'UTF-8') > 100) {
        // The limit is the database column's, so it's checked on the address as it'll be stored (see DomainMOD\Sanitize)

        $form->addFieldError('new_system_email', sprintf(_('This can be at most %s characters long'), 100));

    } else {

        $_SESSION['new_system_email'] = $sanitize->text($new_system_email);

        header("Location: ../ready/");
        exit;

    }

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <?php
    if ($page_title != "") { ?>
        <title><?php echo $layout->pageTitle($page_title); ?></title><?php
    } else { ?>
        <title><?php echo SOFTWARE_TITLE; ?></title><?php
    } ?>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag('install'); ?>
<?php require_once DIR_INC . '/layout/header-install.inc.php'; ?>
<?php echo _('This email address will be used in various locations by the system, such as the FROM address when expiration emails are sent to users.'); ?><BR>
<BR>
<?php
echo $form->showFormTop('');
echo $form->showInputText('new_system_email', _('Email Address') . ' (100)', '', $new_system_email, '100', '', '1', '', '', '1');
$install_back_url = WEB_ROOT . '/install/administrator/';
$install_next_label = sprintf(_('Next: %s'), _('Ready to Install'));
require DIR_INC . '/layout/install-buttons.inc.php';
echo $form->showFormBottom('');
?>
<?php require_once DIR_INC . '/layout/footer-install.inc.php'; ?>
</body>
</html>
