<?php
/**
 * /reset.php
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

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$maint = new DomainMOD\Maintenance();
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$user = new DomainMOD\User();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/config-demo.inc.php';
require_once DIR_INC . '/debug.inc.php';

$system->loginCheck();
$pdo = $deeb->cnxx;

$page_title = _('Reset Password');
$software_section = "resetpassword";

// How long a password reset link stays valid, in seconds
$reset_link_lifetime = 3600;

$user_identifier = $_POST['user_identifier'] ?? '';
$reset_id = (int) ($_REQUEST['id'] ?? 0);
$reset_expires = (int) ($_REQUEST['expires'] ?? 0);
$reset_token = (string) ($_REQUEST['token'] ?? '');
$show_confirm = false;

if ($reset_id > 0 || $reset_token !== '') {

    // A reset link: check it, then (on the confirmation POST) reset the password
    $result = false;

    if ($reset_id > 0 && $reset_expires >= time() && $reset_token !== '') {

        $stmt = $pdo->prepare("
            SELECT id, first_name, last_name, username, email_address, `password`
            FROM users
            WHERE id = :user_id
              AND active = '1'");
        $stmt->bindValue('user_id', $reset_id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();

        if ($result && !hash_equals($user->getResetToken($result->id, $reset_expires, $result->password), $reset_token)) {
            $result = false;
        }

    }

    if (!$result) {

        $_SESSION['s_message_danger'] = $_SESSION['s_message_danger'] ?? '';
        $_SESSION['s_message_danger'] .= _('This password reset link is invalid or has expired') . '<BR>';
        header('Location: ' . $web_root . "/reset.php");
        exit;

    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        $new_password = $user->generatePassword(30);

        // Matching on the old hash means only one request can use the link
        $stmt = $pdo->prepare("
            UPDATE users
            SET `password` = :new_hash,
                new_password = '1',
                update_time = :timestamp
            WHERE id = :user_id
              AND `password` = :old_hash");
        $stmt->bindValue('new_hash', $user->generateHash($new_password), PDO::PARAM_STR);
        $bind_timestamp = $time->stamp();
        $stmt->bindValue('timestamp', $bind_timestamp, PDO::PARAM_STR);
        $stmt->bindValue('user_id', $result->id, PDO::PARAM_INT);
        $stmt->bindValue('old_hash', $result->password, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->rowCount() !== 1) {

            $_SESSION['s_message_danger'] = $_SESSION['s_message_danger'] ?? '';
            $_SESSION['s_message_danger'] .= _('This password reset link is invalid or has expired') . '<BR>';
            header('Location: ' . $web_root . "/reset.php");
            exit;

        }

        $first_name = $result->first_name;
        $last_name = $result->last_name;
        $username = $result->username;
        $email_address = $result->email_address;
        require_once DIR_INC . '/email/send-new-password.inc.php';

        $_SESSION['s_message_success'] = $_SESSION['s_message_success'] ?? '';
        $_SESSION['s_message_success'] .= _('Your new password has been emailed to you') . '<BR>';
        header('Location: ' . $web_root . "/");
        exit;

    }

    // A GET only shows the confirmation button, so a mail scanner following the link doesn't reset the password
    $show_confirm = true;

} elseif ($user_identifier != '') {

    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, username, email_address, `password`
        FROM users
        WHERE (username = :username OR email_address = :email_address)
          AND active = '1'");
    $stmt->bindValue('username', $user_identifier, PDO::PARAM_STR);
    $stmt->bindValue('email_address', $user_identifier, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    // The same message either way, so the page doesn't reveal which accounts exist
    $_SESSION['s_message_success'] = $_SESSION['s_message_success'] ?? '';
    $_SESSION['s_message_success'] .= _("If there's a matching username or email address, a link to reset your password has been emailed to it") . '<BR>';

    /*
     * Finish the response before sending any email, so the response time doesn't reveal whether the account exists
     * (sending can take a while, especially over SMTP)
     */
    session_write_close();
    ignore_user_abort(true);
    header('Location: ' . $web_root . "/");
    header('Connection: close');
    header('Content-Length: 0');
    while (ob_get_level() > 0) {
        ob_end_flush();
    }
    flush();
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }

    // Nothing changes yet: the user gets a link, and the password is only reset once the link is used
    if ($result) {

        $expires = time() + $reset_link_lifetime;
        $reset_query = 'id=' . $result->id . '&expires=' . $expires . '&token=' .
            $user->getResetToken($result->id, $expires, $result->password);

        $first_name = $result->first_name;
        $last_name = $result->last_name;
        $username = $result->username;
        $email_address = $result->email_address;
        require_once DIR_INC . '/email/send-reset-link.inc.php';

    }

    exit;

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        $_SESSION['s_message_danger'] = $_SESSION['s_message_danger'] ?? '';
        $_SESSION['s_message_danger'] .= _('Enter your username or email address') . '<BR>';

    }

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<html>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<body class="hold-transition login-page text-sm">
<?php require_once DIR_INC . '/layout/header-login.inc.php'; ?>

<div class="login-box">
    <div class="card card-outline card-danger">
        <div class="card-body"><?php

            if ($show_confirm) { ?>

            <p class="login-box-msg"><?php echo _('Click the button below to reset your password. Your new password will be emailed to you.'); ?></p>

            <form method="post">
                <input type="hidden" name="id" value="<?php echo $reset_id; ?>">
                <input type="hidden" name="expires" value="<?php echo $reset_expires; ?>">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($reset_token, ENT_QUOTES); ?>">
                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-danger btn-block"><?php echo _('Reset Password'); ?></button>
                    </div>
                </div>
            </form><?php

            } else { ?>

            <p class="login-box-msg"><?php echo _('Username or Email Address'); ?></p>

            <form method="post">
                <div class="input-group mb-3">
                    <input type="text" class="form-control" placeholder="Username or Email Address" maxlength="100" name="user_identifier">
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-user"></span>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-danger btn-block"><?php echo _('Reset Password'); ?></button>
                    </div>
                    <!-- /.col -->
                </div>
            </form><?php

            } ?>
        </div>
        <!-- /.login-card-body -->
    </div>
    <p class="mt-3 mb-1">
        <a href="./"><?php echo _('Sign In'); ?></a>
    </p>
</div>
<!-- /.login-box -->
<?php require_once DIR_INC . '/layout/footer-login.inc.php'; ?>
</body>
</html>
