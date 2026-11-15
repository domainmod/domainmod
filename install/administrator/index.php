<?php
/**
 * /install/administrator/index.php
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
$user = new DomainMOD\User();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/install.administrator.inc.php';

$system->loginCheck();
$system->installCheck();

// The first administrator's details, kept in the session like the other steps' answers. The password is hashed as
// soon as the step is accepted, and only its hash is kept (install/go.php stores it and clears it). The typed password
// is never kept, shown again, or logged.
$fields = array('first_name' => 50, 'last_name' => 50, 'username' => 30, 'email' => 100);
$has_saved_password = ($_SESSION['s_installation_admin_hash'] ?? '') !== '';

$values = array();
foreach ($fields as $field => $maxlength) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $values[$field] = trim((string) ($_POST['new_admin_' . $field] ?? ''));
    } else {
        $values[$field] = $unsanitize->text($_SESSION['s_installation_admin_' . $field] ?? '');
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // The installer runs while signed out, so it checks the security token itself (see DomainMOD\Csrf).
    $csrf = new DomainMOD\Csrf();
    $csrf->check($_SERVER['REQUEST_URI'] ?? '');

    // As typed, since it's only ever hashed
    $new_password = (string) ($_POST['new_admin_password'] ?? '');
    $new_password_confirmation = (string) ($_POST['new_admin_password_confirmation'] ?? '');

    if ($values['first_name'] === '') $form->addFieldError('new_admin_first_name', _("Enter the administrator's first name"));
    if ($values['last_name'] === '') $form->addFieldError('new_admin_last_name', _("Enter the administrator's last name"));
    if ($values['username'] === '') $form->addFieldError('new_admin_username', _("Enter the administrator's username"));
    if ($values['email'] === '') {
        $form->addFieldError('new_admin_email', _("Enter the administrator's email address"));
    } elseif (filter_var($values['email'], FILTER_VALIDATE_EMAIL) === false) {
        $form->addFieldError('new_admin_email', _('Enter a valid email address'));
    }
    // Sign-in compares the username exactly as typed, while the values are stored escaped (see DomainMOD\Sanitize), so
    // the characters escaping would change aren't allowed in a username
    if (preg_match('/[&"\'<>]/', $values['username'])) {
        $form->addFieldError('new_admin_username', _("A username can't contain an ampersand, quotation marks, an apostrophe, or angle brackets"));
    }
    // The database's utf8 columns hold only three-byte characters, so one outside the Basic Multilingual Plane (such as
    // an emoji) can't be saved
    foreach ($fields as $field => $maxlength) {
        if (preg_match('/[\x{10000}-\x{10FFFF}]/u', $values[$field])) {
            $form->addFieldError('new_admin_' . $field, _("This contains a character that can't be saved, such as an emoji"));
        }
    }
    // The limits are the database columns', so they're checked on the values as they'll be stored
    foreach ($fields as $field => $maxlength) {
        if (mb_strlen($sanitize->text($values[$field]), 'UTF-8') > $maxlength) {
            $form->addFieldError('new_admin_' . $field, sprintf(_('This can be at most %s characters long'), $maxlength));
        }
    }

    // Empty password fields keep a password entered earlier. Otherwise both are needed, and they must match.
    if ($new_password === '' && $new_password_confirmation === '' && $has_saved_password) {
        $new_hash = $_SESSION['s_installation_admin_hash'];
    } elseif ($new_password === '' || $new_password_confirmation === '') {
        $new_hash = '';
        $form->addFieldError(array('new_admin_password', 'new_admin_password_confirmation'), _('Enter and confirm the password'));
    } elseif ($new_password !== $new_password_confirmation) {
        $new_hash = '';
        $form->addFieldError(array('new_admin_password', 'new_admin_password_confirmation'), _("The passwords didn't match"));
    } elseif ($user->passwordHasUnusableCharacter($new_password)) {
        // password_hash() can't take a NUL byte, so it's refused here rather than failing below
        $new_hash = '';
        $form->addFieldError(array('new_admin_password', 'new_admin_password_confirmation'), $user->passwordUnusableMessage());
    } elseif ($user->passwordTooShort($new_password)) {
        $new_hash = '';
        $form->addFieldError(array('new_admin_password', 'new_admin_password_confirmation'), $user->passwordTooShortMessage());
    } elseif ($user->passwordTooLong($new_password)) {
        $new_hash = '';
        $form->addFieldError(array('new_admin_password', 'new_admin_password_confirmation'), $user->passwordTooLongMessage());
    } else {
        try {
            $new_hash = $user->generateHash($new_password);
        } catch (\ValueError $e) {
            $new_hash = '';
        }
        if (!is_string($new_hash) || $new_hash === '') {
            $new_hash = '';
            $form->addFieldError(array('new_admin_password', 'new_admin_password_confirmation'), $user->passwordUnusableMessage());
        }
    }

    if (empty(DomainMOD\Form::$field_errors)) {

        foreach ($fields as $field => $maxlength) {
            $_SESSION['s_installation_admin_' . $field] = $sanitize->text($values[$field]);
        }
        $_SESSION['s_installation_admin_hash'] = $new_hash;

        header("Location: ../email-system/");
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
<?php echo sprintf(_("This is the first administrator account, which is created when %s is installed. You'll use it to sign in."), SOFTWARE_TITLE); ?><BR>
<BR>
<?php
echo $form->showFormTop('');
echo $form->showInputText('new_admin_first_name', _('First Name') . ' (50)', '', $values['first_name'], '50', '', '1', '', '', '1');
echo $form->showInputText('new_admin_last_name', _('Last Name') . ' (50)', '', $values['last_name'], '50', '', '1', '', '');
echo $form->showInputText('new_admin_username', _('Username') . ' (30)', '', $values['username'], '30', '', '1', '', '');
echo $form->showInputText('new_admin_email', _('Email Address') . ' (100)', '', $values['email'], '100', '', '1', '', '');
$password_note = $has_saved_password ? $user->passwordLengthHint() . ' ' . _("Leave the password fields empty to keep the password you've already entered.") : $user->passwordLengthHint();
echo $form->showInputText('new_admin_password', _('Password') . ' (72)', $password_note, '', '72', '1', $has_saved_password ? '' : '1', '', '');
echo $form->showInputText('new_admin_password_confirmation', _('Confirm Password') . ' (72)', '', '', '72', '1', $has_saved_password ? '' : '1', '', '');
$install_back_url = WEB_ROOT . '/install/timezone/';
$install_next_label = sprintf(_('Next: %s'), _('System Email'));
require DIR_INC . '/layout/install-buttons.inc.php';
echo $form->showFormBottom('');
?>
<?php require_once DIR_INC . '/layout/footer-install.inc.php'; ?>
</body>
</html>
