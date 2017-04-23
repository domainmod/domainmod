<?php
/**
 * /index.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>
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
require_once('_includes/start-session.inc.php');
require_once('_includes/init.inc.php');

require_once(DIR_ROOT . 'classes/Autoloader.php');
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$maint = new DomainMOD\Maintenance();
$login = new DomainMOD\Login();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();

require_once(DIR_INC . 'head.inc.php');
require_once(DIR_INC . 'config.inc.php');
require_once(DIR_INC . 'config-demo.inc.php');
require_once(DIR_INC . 'software.inc.php');
require_once(DIR_INC . 'database.inc.php');

$system->loginCheck();

list($installation_mode, $result_message) = $system->installCheck($dbcon);
$_SESSION['s_installation_mode'] = $installation_mode;
$_SESSION['s_message_danger'] .= $result_message;

if ($_SESSION['s_installation_mode'] == '1') {

    $page_title = "";
    $software_section = "installation";

} else {

    $page_title = "";
    $software_section = "login";

}

$new_username = $_POST['new_username'];
$new_password = $_POST['new_password'];
$from_install_form = $_POST['from_install_form'];
$new_install_email = $_POST['new_install_email'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $from_install_form == '1') {

    if ($new_install_email != '') {

        $_SESSION['new_install_email'] = $new_install_email;

        header("Location: install/");
        exit;

    }

}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_username != "" && $new_password != "" && $from_install_form != '1') {
    
    $_SESSION['s_read_only'] = '1';

    $query = "SELECT id, username
              FROM users
              WHERE username = ?
                AND `password` = password(?)
                AND active = '1'";
    $q = $dbcon->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('ss', $new_username, $new_password);
        $q->execute();
        $q->store_result();
        $q->bind_result($id, $username);

        if ($q->num_rows() == 1) { // Login succeeded

            while ($q->fetch()) {

                $_SESSION['s_user_id'] = $id;
                $_SESSION['s_username'] = $username;

            }

            $sql = "SELECT db_version
                    FROM settings";
            $result = mysqli_query($dbcon, $sql);

            while ($row = mysqli_fetch_object($result)) {

                $_SESSION['s_system_db_version'] = (string) $row->db_version;

            }

            $_SESSION['s_is_logged_in'] = 1;

            header("Location: checks.php");
            exit;

        } else { // Login failed

            $_SESSION['s_message_danger'] .= "Login Failed<BR>";

        }

        $q->close();

    } else $error->outputSqlError($dbcon, "ERROR");

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && $from_install_form != '1') {

        if ($new_username == "" && $new_password == "") {

            $_SESSION['s_message_danger'] .= "Enter your username & password<BR>";

        } elseif ($new_username == "" || $new_password == "") {

            if ($new_username == "") $_SESSION['s_message_danger'] .= "Enter your username<BR>";
            if ($new_password == "") $_SESSION['s_message_danger'] .= "Enter your password<BR>";

        }

    } elseif ($_SERVER['REQUEST_METHOD'] == 'POST' && $from_install_form == '1') {

        $_SESSION['s_message_danger'] .= "<BR>Enter the system/administrator email address<BR>";

    }

}
$new_password = "";
?>
<?php require_once(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <?php
    if ($page_title != "") { ?>
        <title><?php echo $system->pageTitle($software_title, $page_title); ?></title><?php
    } else { ?>
        <title><?php echo $software_title; ?></title><?php
    } ?>
    <?php require_once(DIR_INC . 'layout/head-tags.inc.php'); ?>
</head>
<?php
if ($new_username == "") { ?>

    <body class="hold-transition skin-red" onLoad="document.forms[0].elements[0].focus()"><?php

} else { ?>

    <body class="hold-transition skin-red" onLoad="document.forms[0].elements[1].focus()"><?php

} ?>
<?php require_once(DIR_INC . 'layout/header-login.inc.php'); ?>
<?php
if ($_SESSION['s_installation_mode'] == '0') {

    echo $form->showFormTop('');

    if ($demo_install == "1") { ?> <strong>Demo Username & Password:</strong> "demo"<BR><BR><?php }

    echo $form->showInputText('new_username', 'Username', '', $new_username, '20', '', '', '', '');
    echo $form->showInputText('new_password', 'Password', '', '', '255', '1', '', '', '');
    echo $form->showSubmitButton('Login', '', '');
    echo $form->showFormBottom('');

    if ($demo_install != "1") { ?>

        <BR><a href="reset.php">Forgot your Password?</a><?php

    }

} else {

    $email_address_text = 'This email address will be used in various locations by the system (such as the FROM address when expiration emails are sent to users), as well as be used as the primary system administrator\'s email address.<BR><BR>Please double check that this address is valid, as it will be required if the system administrator forgets their password.';
    echo $form->showFormTop('');
    echo $form->showInputText('new_install_email', 'Enter The System/Administrator Email Address', $email_address_text, $new_install_email, '100', '', '', '', '');
    echo $form->showSubmitButton('Install DomainMOD', '', '');
    echo $form->showInputHidden('from_install_form', '1');
    echo $form->showFormBottom('');


}
?>
<?php require_once(DIR_INC . 'layout/footer-login.inc.php'); ?>
</body>
</html>
