<?php
/**
 * /reset.php
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

$user_identifier = $_REQUEST['user_identifier'] ?? '';

if ($user_identifier != '') {

    $stmt = $pdo->prepare("
        SELECT first_name, last_name, username, email_address
        FROM users
        WHERE (username = :username OR email_address = :email_address)
          AND active = '1'");
    $stmt->bindValue('username', $user_identifier, PDO::PARAM_STR);
    $stmt->bindValue('email_address', $user_identifier, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    $message_success = $_SESSION['s_message_success'] ?? '';
    $message_success .= _("If there's a matching username or email address your new password will been emailed to you.") . '<BR>';

    if (!$result) {

        header('Location: ' . $web_root . "/");
        exit;

    } else {

        $new_password = $user->generatePassword(30);

        $stmt = $pdo->prepare("
            UPDATE users
            SET `password` = CONCAT('*', UPPER(SHA1(UNHEX(SHA1(:new_password))))),
                new_password = '1',
                update_time = :timestamp
            WHERE username = :username
              AND email_address = :email_address");
        $stmt->bindValue('new_password', $new_password, PDO::PARAM_STR);
        $bind_timestamp = $time->stamp();
        $stmt->bindValue('timestamp', $bind_timestamp, PDO::PARAM_STR);
        $stmt->bindValue('username', $result->username, PDO::PARAM_STR);
        $stmt->bindValue('email_address', $result->email_address, PDO::PARAM_STR);
        $stmt->execute();

        $first_name = $result->first_name;
        $last_name = $result->last_name;
        $username = $result->username;
        $email_address = $result->email_address;
        require_once DIR_INC . '/email/send-new-password.inc.php';

        header('Location: ' . $web_root . "/");
        exit;

    }

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        $_SESSION['s_message_danger'] = $_SESSION['s_message_danger'] ?? '';

        if ($user_identifier == "") {
            $_SESSION['s_message_danger'] .= _('Enter your username or email address') . '<BR>';
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
<body class="hold-transition login-page text-sm">
<?php require_once DIR_INC . '/layout/header-login.inc.php'; ?>

<div class="login-box">
    <div class="card card-outline card-danger">
        <div class="card-body">
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
            </form>
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
