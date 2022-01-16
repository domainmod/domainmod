<?php
/**
 * /settings/password/index.php
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

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$user = new DomainMOD\User();
$sanitize = new DomainMOD\Sanitize();
$unsanitize = new DomainMOD\Unsanitize();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/settings-password.inc.php';

$system->authCheck();
$pdo = $deeb->cnxx;

$new_password = $sanitize->text($_POST['new_password']);
$new_password_confirmation = $sanitize->text($_POST['new_password_confirmation']);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_password != "" && $new_password_confirmation != "" && $new_password == $new_password_confirmation) {

    $stmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE id = :user_id
          AND email_address = :email_address");
    $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
    $stmt->bindValue('email_address', $_SESSION['s_email_address'], PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT count(*)
        FROM users
        WHERE id = :user_id
          AND email_address = :email_address");
    $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
    $stmt->bindValue('email_address', $_SESSION['s_email_address'], PDO::PARAM_STR);
    $stmt->execute();
    $user_count = $stmt->fetchColumn();

    if (!$result || $user_count > 1) {

        $_SESSION['s_message_danger'] .= _('Your password could not be updated') . '<BR>';
        $_SESSION['s_message_danger'] .= _('If the problem persists please contact your administrator') . '<BR>';

    } else {

        $temp_password = $unsanitize->text($new_password);
        $temp_hash = $user->generateHash($temp_password);

        $stmt = $pdo->prepare("
            UPDATE users
            SET `password` = :temp_hash,
                new_password = '0',
                update_time = :timestamp
            WHERE id = :user_id
              AND email_address = :email_address");
        $stmt->bindValue('temp_hash', $temp_hash, PDO::PARAM_STR);
        $timestamp = $time->stamp();
        $stmt->bindValue('timestamp', $timestamp, PDO::PARAM_STR);
        $stmt->bindValue('user_id', $_SESSION['s_user_id'], PDO::PARAM_INT);
        $stmt->bindValue('email_address', $_SESSION['s_email_address'], PDO::PARAM_STR);
        $stmt->execute();

        $_SESSION['s_message_success'] .= _('Password changed') . '<BR>';

        if ($_SESSION['s_running_login_checks'] == '1') {

            header('Location: ../../checks.php');

        } else {

            header('Location: index.php');

        }
        exit;


    }

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        if ($new_password == "" && $new_password_confirmation == "") {

            $_SESSION['s_message_danger'] .= _('Your passwords were left blank') . '<BR>';

        } else {

            $_SESSION['s_message_danger'] .= _("Your passwords didn't match") . '<BR>';

        }

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
echo $form->showInputText('new_password', _('New Password') . ' (72)', '', '', '72', '1', '1', '', '');
echo $form->showInputText('new_password_confirmation', _('Confirm New Password') . ' (72)', '', '', '72', '1', '1', '', '');
echo $form->showSubmitButton(_('Change Password'), '', '');
echo $form->showFormBottom('');
?>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
