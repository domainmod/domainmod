<?php
/**
 * /admin/users/edit.php
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

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$log = new DomainMOD\Log('/admin/users/edit.php');
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$currency = new DomainMOD\Currency();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();
$language = new DomainMOD\Language();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/admin-users-edit.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'] ?? 0);
$pdo = $deeb->cnxx;

$del = (int) ($_GET['del'] ?? 0);

$uid = (int) ($_GET['uid'] ?? 0);

$new_first_name = isset($_POST['new_first_name']) ? $sanitize->text($_POST['new_first_name']) : '';
$new_last_name = isset($_POST['new_last_name']) ? $sanitize->text($_POST['new_last_name']) : '';
$new_username = isset($_POST['new_username']) ? $sanitize->text($_POST['new_username']) : '';
$original_username = isset($_POST['original_username']) ? $sanitize->text($_POST['original_username']) : '';
$new_email_address = isset($_POST['new_email_address']) ? $sanitize->text($_POST['new_email_address']) : '';
$new_language = $_POST['new_language'] ?? '';
$new_currency = isset($_POST['new_currency']) ? $sanitize->text($_POST['new_currency']) : '';
$new_timezone = isset($_POST['new_timezone']) ? $sanitize->text($_POST['new_timezone']) : '';
$new_expiration_emails = (int) ($_POST['new_expiration_emails'] ?? 0);
$new_is_admin = (int) ($_POST['new_is_admin'] ?? 0);
$new_read_only = (int) ($_POST['new_read_only'] ?? 0);
$new_is_active = (int) ($_POST['new_is_active'] ?? 0);
$new_uid = (int) ($_POST['new_uid'] ?? 0);

if ($new_uid === 0) $new_uid = $uid;

$stmt = $pdo->prepare("
    SELECT username
    FROM users
    WHERE id = :user_id");
$stmt->bindValue('user_id', $uid, PDO::PARAM_INT);
$stmt->execute();
$result = $stmt->fetchColumn();

if ($result) {

    if ($result == 'admin' && $_SESSION['s_username'] != 'admin') {

        $_SESSION['s_message_danger'] .= _("You don't have permissions to edit the primary administrator account") . '<BR>';

        header("Location: index.php");
        exit;

    }

}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_first_name != '' && $new_last_name != '' && $new_username != '' && $new_email_address != '') {

    $invalid_username = '';
    $invalid_email_address = '';

    // Check to see if the username is already taken
    $stmt = $pdo->prepare("
        SELECT username
        FROM users
        WHERE username = :username
          AND id != :user_id");
    $stmt->bindValue('username', $new_username, PDO::PARAM_STR);
    $stmt->bindValue('user_id', $new_uid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    if ($result) {

        $invalid_username = '1';

    }

    // Check to see if the email address is already taken
    $stmt = $pdo->prepare("
        SELECT username
        FROM users
        WHERE email_address = :email_address
          AND id != :user_id");
    $stmt->bindValue('email_address', $new_email_address, PDO::PARAM_STR);
    $stmt->bindValue('user_id', $new_uid, PDO::PARAM_INT);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    if ($result) {

        $invalid_email_address = '1';

    }

    // Make sure they aren't trying to assign a reserved username
    // If it's the primary admin account editing their own profile the query will return 1, otherwise 0
    if ($new_username == 'admin' || $new_username == 'administrator') {

        $stmt = $pdo->prepare("
            SELECT username
            FROM users
            WHERE username = :new_username
              AND id = :new_uid");
        $stmt->bindValue('new_username', $new_username, PDO::PARAM_STR);
        $stmt->bindValue('new_uid', $new_uid, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetchColumn();

        if (!$result) {

            $invalid_username = '1';
            $new_username = $original_username;

        }

    }

}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_first_name != '' && $new_last_name != '' && $new_username != ''
    && $new_email_address != '' && $invalid_username != '1' && $invalid_email_address != '1'
) {

    try {

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE users
            SET first_name = :first_name,
                last_name = :last_name,
                username = :username,
                email_address = :email_address,
                admin = :admin,
                `read_only` = :read_only,
                active = :active,
                update_time = :update_time
            WHERE id = :user_id");
        $stmt->bindValue('first_name', $new_first_name, PDO::PARAM_STR);
        $stmt->bindValue('last_name', $new_last_name, PDO::PARAM_STR);
        $stmt->bindValue('username', $new_username, PDO::PARAM_STR);
        $stmt->bindValue('email_address', $new_email_address, PDO::PARAM_STR);
        $stmt->bindValue('admin', $new_is_admin, PDO::PARAM_INT);
        $stmt->bindValue('read_only', $new_read_only, PDO::PARAM_INT);
        $stmt->bindValue('active', $new_is_active, PDO::PARAM_INT);
        $bind_timestamp = $time->stamp();
        $stmt->bindValue('update_time', $bind_timestamp, PDO::PARAM_STR);
        $stmt->bindValue('user_id', $new_uid, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare("
            UPDATE user_settings
            SET default_language = :new_language,
                default_currency = :new_currency,
                default_timezone = :new_timezone,
                expiration_emails = :new_expiration_emails,
                update_time = :update_time
            WHERE user_id = :user_id");
        $stmt->bindValue('new_language', $new_language, PDO::PARAM_STR);
        $stmt->bindValue('new_currency', $new_currency, PDO::PARAM_STR);
        $stmt->bindValue('new_timezone', $new_timezone, PDO::PARAM_STR);
        $stmt->bindValue('new_expiration_emails', $new_expiration_emails, PDO::PARAM_INT);
        $bind_timestamp = $time->stamp();
        $stmt->bindValue('update_time', $bind_timestamp, PDO::PARAM_STR);
        $stmt->bindValue('user_id', $new_uid, PDO::PARAM_INT);
        $stmt->execute();

        if ($_SESSION['s_username'] == $new_username) {

            $_SESSION['s_first_name'] = $new_first_name;
            $_SESSION['s_last_name'] = $new_last_name;
            $_SESSION['s_email_address'] = $new_email_address;

        }

        // If they're editing themselves
        if ($_SESSION['s_user_id'] == $new_uid) {

            $_SESSION['s_first_name'] = $new_first_name;
            $_SESSION['s_last_name'] = $new_last_name;
            $_SESSION['s_email_address'] = $new_email_address;
            $_SESSION['s_default_language'] = $new_language;
            $_SESSION['s_default_language_name'] = $language->getLangName($new_language);
            $_SESSION['s_default_currency'] = $new_currency;
            $_SESSION['s_default_timezone'] = $new_timezone;
            $_SESSION['s_expiration_emails'] = $new_expiration_emails;

            list($_SESSION['s_default_currency_name'], $_SESSION['s_default_currency_symbol'],
                $_SESSION['s_default_currency_symbol_order'], $_SESSION['s_default_currency_symbol_space'])
                = $currency->getCurrencyInfo($new_currency);

        }

        if ($pdo->InTransaction()) $pdo->commit();

        $_SESSION['s_message_success'] .= sprintf(_('User %s %s (%s) updated'), $new_first_name, $new_last_name, $new_username) . '<BR>';

        header("Location: index.php");
        exit;

    } catch (Exception $e) {

        if ($pdo->InTransaction()) $pdo->rollback();

        $log_message = 'Unable to update user';
        $log_extra = array('Error' => $e);
        $log->critical($log_message, $log_extra);

        $_SESSION['s_message_danger'] .= $log_message . '<BR>';

        throw $e;

    }

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        if ($new_first_name == '') $_SESSION['s_message_danger'] .= _("Enter the user's first name") . '<BR>';
        if ($new_last_name == '') $_SESSION['s_message_danger'] .= _("Enter the user's last name") . '<BR>';
        if ($invalid_username == '1' || $new_username == '') $_SESSION['s_message_danger'] .= _('You have entered an invalid username') . '<BR>';
        if ($invalid_email_address == '1' || $new_email_address == '') $_SESSION['s_message_danger'] .= _('You have entered an invalid email address') . '<BR>';

    } else {

        $stmt = $pdo->prepare("
            SELECT u.first_name, u.last_name, u.username, u.email_address, us.default_language, us.default_currency, us.default_timezone, us.expiration_emails, u.admin, u.`read_only`, u.active
            FROM users AS u, user_settings AS us
            WHERE u.id = us.user_id
              AND u.id = :user_id");
        $stmt->bindValue('user_id', $uid, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        if ($result) {

            $new_first_name = $result->first_name;
            $new_last_name = $result->last_name;
            $new_username = $result->username;
            $original_username = $result->username;
            $new_email_address = $result->email_address;
            $new_language = $result->default_language;
            $new_currency = $result->default_currency;
            $new_timezone = $result->default_timezone;
            $new_expiration_emails = $result->expiration_emails;
            $new_is_admin = $result->admin;
            $new_read_only = $result->read_only;
            $new_is_active = $result->active;

        }

    }
}

if ($del === 1) {

    $temp_uid = $pdo->query("
        SELECT id
        FROM users
        WHERE username = 'admin'")->fetchColumn();

    if ($uid == $temp_uid || $uid == $_SESSION['s_user_id']) {

        if ($uid == $temp_uid) $_SESSION['s_message_danger'] .= _('The admin user cannot be deleted') . '<BR>';
        if ($uid == $_SESSION['s_user_id']) $_SESSION['s_message_danger'] .= _("You can't delete yourself") . '<BR>';

    } else {

        $stmt = $pdo->prepare("
            DELETE FROM user_settings
            WHERE user_id = :user_id");
        $stmt->bindValue('user_id', $uid, PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $pdo->prepare("
            DELETE FROM users
            WHERE id = :user_id");
        $stmt->bindValue('user_id', $uid, PDO::PARAM_INT);
        $stmt->execute();

        $_SESSION['s_message_success'] .= sprintf(_('User %s %s (%s) deleted'), $new_first_name, $new_last_name, $new_username) . '<BR>';

        header("Location: index.php");
        exit;

    }

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition sidebar-mini layout-fixed text-sm select2-red<?php echo $layout->bodyDarkMode(); ?>">
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>
<?php
echo $form->showFormTop('');
echo $form->showInputText('new_first_name', _('First Name') . ' (50)', '', $unsanitize->text($new_first_name), '50', '', '1', '', '');
echo $form->showInputText('new_last_name', _('Last Name') . ' (50)', '', $unsanitize->text($new_last_name), '50', '', '1', '', '');

if ($new_username == 'admin' || $new_username == 'administrator') { ?>

    <strong><?php echo _('Username'); ?></strong><BR><?php echo $new_username; ?><BR><BR><?php

} else {

    echo $form->showInputText('new_username', _('Username') . ' (30)', '', $unsanitize->text($new_username), '30', '', '1', '', '');

}

echo $form->showInputText('new_email_address', _('Email Address') . ' (100)', '', $unsanitize->text($new_email_address), '100', '', '1', '', '');

echo $form->showDropdownTop('new_language', _('Language'), '', '', '');

$result = $pdo->query("
    SELECT name, language
    FROM languages
    ORDER BY name")->fetchAll();

if ($result) {

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->language, $row->name . ' [' . $row->language . ']', $new_language);

    }

}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_currency', _('Currency'), '', '', '');

$result = $pdo->query("
    SELECT currency, `name`, symbol
    FROM currencies
    ORDER BY name")->fetchAll();

if ($result) {

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->currency, $row->name . ' (' . $row->currency . ' ' . $row->symbol . ')', $new_currency);

    }

}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_timezone', _('Time Zone'), '', '', '');

$result = $pdo->query("
    SELECT timezone
    FROM timezones
    ORDER BY timezone")->fetchAll();

if ($result) {

    foreach ($result as $row) {

        echo $form->showDropdownOption($row->timezone, $row->timezone, $new_timezone);

    }

}
echo $form->showDropdownBottom('');

echo $form->showSwitch(_('Subscribe to Domain & SSL Certificate expiration emails?') . '', sprintf(_("If the instance of PHP running on your %s server isn't configured to send mail, you can use an external SMTP server to send system emails."), SOFTWARE_TITLE), 'new_expiration_emails', $new_expiration_emails, '', '<BR><BR>');

if ($new_username == 'admin' || $new_username == 'administrator') { ?>

    <strong><?php echo _('Admin Privileges') . '?'; ?></strong>&nbsp;&nbsp;<?php echo _('Yes'); ?><BR><BR><?php

} else {

    echo $form->showSwitch(_('Admin Privileges') . '?', '', 'new_is_admin', $new_is_admin, '', '<BR><BR>');

}

if ($new_username == 'admin' || $new_username == 'administrator') { ?>

    <strong><?php echo _('Read-Only') . '?'; ?></strong>&nbsp;&nbsp;<?php echo _('No'); ?><BR><BR><?php

} else {

    echo $form->showSwitch(_('Read-Only User') . '?', '', 'new_read_only', $new_read_only, '', '<BR><BR>');

}

if ($new_username == 'admin' || $new_username == 'administrator') { ?>

    <strong><?php echo _('Active Account') . '?'; ?></strong>&nbsp;&nbsp;<?php echo _('Yes'); ?><BR><BR><?php

} else {

    echo $form->showSwitch(_('Active Account') . '?', '', 'new_is_active', $new_is_active, '', '<BR><BR>');

}

if ($new_username == 'admin' || $new_username == 'administrator') {

    echo $form->showInputHidden('new_username', 'admin');
    echo $form->showInputHidden('new_is_admin', '1');
    echo $form->showInputHidden('new_read_only', '0');
    echo $form->showInputHidden('new_is_active', '1');

}

echo $form->showInputHidden('original_username', $original_username);
echo $form->showInputHidden('new_uid', $uid);
echo $form->showSubmitButton(_('Save'), '', '');

echo $form->showFormBottom('');
?>
<a href="reset-password.php?new_username=<?php echo urlencode($new_username); ?>&display=1"><?php echo $layout->showButton('button', strtoupper(_('Reset and Display Password'))); ?></a>&nbsp;&nbsp;&nbsp;
<a href="reset-password.php?new_username=<?php echo urlencode($new_username); ?>"><?php echo $layout->showButton('button', strtoupper(_('Reset and Email New Password to User'))); ?></a><BR><BR><?php
$layout->deleteButton(_('User'), $new_first_name . ' ' . $new_last_name, 'edit.php?uid=' . $uid . '&del=1'); ?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
