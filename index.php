<?php
/**
 * /index.php
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
require_once __DIR__ . '/_includes/start-session.inc.php';
require_once __DIR__ . '/_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$log = new DomainMOD\Log('/index.php');
$maint = new DomainMOD\Maintenance();
$layout = new DomainMOD\Layout();
$login = new DomainMOD\Login();
$user = new DomainMOD\User();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();
$format = new DomainMOD\Format();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/config-demo.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/login.inc.php';

$system->loginCheck();
$pdo = $deeb->cnxx;

$_SESSION['s_installation_mode'] = $system->installMode();

if ($_SESSION['s_installation_mode'] === 1) {

    header("Location: install/");
    exit;

}

$new_username = $_POST['new_username'];
$new_password = $_POST['new_password'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_username != "" && $new_password != "") {

    $_SESSION['s_read_only'] = '1';

    // Check to see if the user's password is using the old md5 hashing
    $stmt = $pdo->prepare("
        SELECT id
        FROM users
        WHERE username = :new_username
          AND `password` = password(:new_password)
          AND active = '1'");
    $stmt->bindValue('new_username', $new_username, PDO::PARAM_STR);
    $stmt->bindValue('new_password', $new_password, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    // Update the stored password to use stronger hashing than md5
    if ($result) {

        $new_hashed_password = $user->generateHash($new_password);

        $stmt = $pdo->prepare("
            UPDATE `users`
            SET password = :new_hashed_password
            WHERE username = :new_username
              AND `password` = password(:new_password)
              AND active = '1'");
        $stmt->bindValue('new_hashed_password', $new_hashed_password, PDO::PARAM_STR);
        $stmt->bindValue('new_username', $new_username, PDO::PARAM_STR);
        $stmt->bindValue('new_password', $new_password, PDO::PARAM_STR);
        $stmt->execute();

    }

    // Check to see if the user's password matches
    $stmt = $pdo->prepare("
        SELECT `password`
        FROM users
        WHERE username = :new_username
          AND active = '1'");
    $stmt->bindValue('new_username', $new_username, PDO::PARAM_STR);
    $stmt->execute();
    $stored_hash = $stmt->fetchColumn();

    if (password_verify($new_password, $stored_hash)) {

        $_SESSION['s_user_id'] = $user->getUserId($new_username);
        $_SESSION['s_username'] = $new_username;
        $_SESSION['s_stored_hash'] = $stored_hash;

        $_SESSION['s_system_db_version'] = $system->getDbVersion();

        $_SESSION['s_is_logged_in'] = 1;

        header("Location: checks.php");
        exit;

    } else {

        $log_message = 'Unable to login';
        $log_extra = array('Username' => $new_username, 'Password' => $format->obfusc($new_password));
        $log->warning($log_message, $log_extra);

        $_SESSION['s_message_danger'] .= _('Login Failed') . '<BR>';

    }

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        if ($new_username == "" && $new_password == "") {

            $_SESSION['s_message_danger'] .= _('Enter your username & password') . '<BR>';

        } elseif ($new_username == "" || $new_password == "") {

            if ($new_username == "") $_SESSION['s_message_danger'] .= _('Enter your username') . '<BR>';
            if ($new_password == "") $_SESSION['s_message_danger'] .= _('Enter your password') . '<BR>';

        }

    }

}
$new_password = "";
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
<body class="hold-transition login-page text-sm">
<?php require_once DIR_INC . '/layout/header-login.inc.php'; ?>

<div class="card card-outline card-danger">
    <div class="card-body login-card-body">
        <p class="login-box-msg"><?php echo _('Please enter your username and password to sign in'); ?></p><?php

        if (DEMO_INSTALLATION == true) { ?>

            <strong><?php echo _('Demo Username'); ?>:</strong> demo<BR>
            <strong><?php echo _('Demo Password'); ?>:</strong> demo<BR><BR><?php

        } ?>

        <form action="" method="post">
            <div class="input-group mb-3">
                <input type="text" class="form-control" placeholder="Username" name="new_username" maxlength="20">
                <div class="input-group-append">
                    <div class="input-group-text">
                        <span class="fas fa-user"></span>
                    </div>
                </div>
            </div>
            <div class="input-group mb-3">
                <input type="password" class="form-control" placeholder="Password" name="new_password" maxlength="72">
                <div class="input-group-append">
                    <div class="input-group-text">
                        <span class="fas fa-lock"></span>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-8">
                    &nbsp;
                </div>
                <!-- /.col -->
                <div class="col-4">
                    <button type="submit" class="btn btn-danger btn-block">Sign In</button>
                </div>
                <!-- /.col -->
            </div>
        </form>
    </div>
    <!-- /.login-card-body -->
</div><?php

if (DEMO_INSTALLATION == false) { ?>

    <BR>
    <p class="mb-1">
        <a href="reset.php"><?php echo _('Forgot your Password'); ?>?</a>
    </p><?php

}
?>

<?php require_once DIR_INC . '/layout/footer-login.inc.php'; ?>
</body>
</html>
