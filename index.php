<?php
/**
 * /index.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2018 Greg Chetcuti <greg@chetcuti.com>
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

    $stmt = $pdo->prepare("
        SELECT id, username
        FROM users
        WHERE username = :new_username
          AND `password` = password(:new_password)
          AND active = '1'");
    $stmt->bindValue('new_username', $new_username, PDO::PARAM_STR);
    $stmt->bindValue('new_password', $new_password, PDO::PARAM_STR);
    $stmt->execute();
    $result = $stmt->fetch();
    $stmt->closeCursor();

    if (!$result) {

        $log_message = 'Unable to login';
        $log_extra = array('Username' => $new_username, 'Password' => $format->obfusc($new_password));
        $log->warning($log_message, $log_extra);

        $_SESSION['s_message_danger'] .= "Login Failed<BR>";

    } else {

        $_SESSION['s_user_id'] = $result->id;
        $_SESSION['s_username'] = $result->username;

        $_SESSION['s_system_db_version'] = $system->getDbVersion();

        $_SESSION['s_is_logged_in'] = 1;

        header("Location: checks.php");
        exit;

    }

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        if ($new_username == "" && $new_password == "") {

            $_SESSION['s_message_danger'] .= "Enter your username & password<BR>";

        } elseif ($new_username == "" || $new_password == "") {

            if ($new_username == "") $_SESSION['s_message_danger'] .= "Enter your username<BR>";
            if ($new_password == "") $_SESSION['s_message_danger'] .= "Enter your password<BR>";

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
<?php
if ($new_username == "") { ?>

    <body class="hold-transition skin-red" onLoad="document.forms[0].elements[0].focus()"><?php

} else { ?>

    <body class="hold-transition skin-red" onLoad="document.forms[0].elements[1].focus()"><?php

} ?>
<?php require_once DIR_INC . '/layout/header-login.inc.php'; ?>
<?php
echo $form->showFormTop('');

if (DEMO_INSTALLATION == '1') { ?>
    <strong>Demo Username:</strong> demo<BR>
    <strong>Demo Password:</strong> demo<BR><BR><?php
}

echo $form->showInputText('new_username', 'Username', '', $new_username, '20', '', '', '', '');
echo $form->showInputText('new_password', 'Password', '', '', '255', '1', '', '', '');
echo $form->showSubmitButton('Login', '', '');
echo $form->showFormBottom('');

if (DEMO_INSTALLATION != '1') { ?>

    <BR><a href="reset.php">Forgot your Password?</a><?php

}
?>
<?php require_once DIR_INC . '/layout/footer-login.inc.php'; ?>
</body>
</html>
