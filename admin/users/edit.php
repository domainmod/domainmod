<?php
/**
 * /admin/users/edit.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2016 Greg Chetcuti <greg@chetcuti.com>
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
include("../../_includes/start-session.inc.php");
include("../../_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "settings/admin-users-edit.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'], $web_root);

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$uid = $_GET['uid'];

if ($new_uid == '') $new_uid = $uid;

$new_first_name = $_POST['new_first_name'];
$new_last_name = $_POST['new_last_name'];
$new_username = $_POST['new_username'];
$new_email_address = $_POST['new_email_address'];
$new_is_admin = $_POST['new_is_admin'];
$new_is_active = $_POST['new_is_active'];
$new_uid = $_POST['new_uid'];

$sql = "SELECT username
        FROM users
        WHERE id = '" . $uid . "'";
$result = mysqli_query($connection, $sql);

while ($row = mysqli_fetch_object($result)) {

    if ($row->username == 'admin' && $_SESSION['s_username'] != 'admin') {

        $_SESSION['s_message_danger'] .= 'You\'re trying to edit an invalid user<BR>';

        header("Location: index.php");
        exit;

    }

}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_first_name != '' && $new_last_name != '' && $new_username != '' && $new_email_address != '') {

    // Check to see if another user already has the username
    $sql = "SELECT username
            FROM users
            WHERE username = '" . mysqli_real_escape_string($connection, $new_username) . "'
            AND id != '" . $new_uid . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
    $is_username_taken = mysqli_num_rows($result);
    if ($is_username_taken > 0) {
        $invalid_username = 1;
        $new_username = '';
    }

    // Make sure they aren't trying to assign a reserved username
    if ($new_username == 'admin' || $new_username == 'administrator') {

        $sql = "SELECT username
                FROM users
                WHERE username = '" . mysqli_real_escape_string($connection, $new_username) . "'
                AND id = '" . $new_uid . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);
        $is_it_my_username = mysqli_num_rows($result);

        if ($is_it_my_username == 0) {

            $invalid_username = 1;
            $new_username = '';

        }

    }

}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_first_name != '' && $new_last_name != '' && $new_username != '' && $new_email_address != '' && $invalid_username != 1) {

    $sql = "UPDATE users
            SET first_name = '" . mysqli_real_escape_string($connection, $new_first_name) . "',
                last_name = '" . mysqli_real_escape_string($connection, $new_last_name) . "',
                username = '" . mysqli_real_escape_string($connection, $new_username) . "',
                email_address = '" . mysqli_real_escape_string($connection, $new_email_address) . "',
                admin = '" . $new_is_admin . "',
                active = '" . $new_is_active . "',
                update_time = '" . $time->stamp() . "'
            WHERE id = '" . $new_uid . "'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $_SESSION['s_message_success'] .= 'User ' . $new_first_name . ' ' . $new_last_name . ' (' . $new_username . ') Updated<BR>';

    if ($_SESSION['s_username'] == $new_username) {

        $_SESSION['s_first_name'] = $new_first_name;
        $_SESSION['s_last_name'] = $new_last_name;
        $_SESSION['s_email_address'] = $new_email_address;

    }

    header("Location: index.php");
    exit;

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        if ($invalid_username == 1 || $new_username == '') $_SESSION['s_message_danger'] .= 'You have entered an invalid username<BR>';
        if ($new_first_name == '') $_SESSION['s_message_danger'] .= 'Enter the user\'s first name<BR>';
        if ($new_last_name == '') $_SESSION['s_message_danger'] .= 'Enter the user\'s last name<BR>';
        if ($new_email_address == '') $_SESSION['s_message_danger'] .= 'Enter the user\'s email address<BR>';

    } else {

        $sql = "SELECT first_name, last_name, username, email_address, admin, active
                FROM users
                WHERE id = '" . $uid . "'";
        $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

        while ($row = mysqli_fetch_object($result)) {

            $new_first_name = $row->first_name;
            $new_last_name = $row->last_name;
            $new_username = $row->username;
            $new_email_address = $row->email_address;
            $new_is_admin = $row->admin;
            $new_is_active = $row->active;

        }

    }
}
if ($del == '1') {

    $_SESSION['s_message_danger'] = 'Are you sure you want to delete this User?<BR><BR><a href="edit.php?uid=' . $uid . '&really_del=1">YES, REALLY DELETE THIS USER</a><BR>';

}

if ($really_del == '1') {

    $sql = "SELECT id
            FROM users
            WHERE username = 'admin'";
    $result = mysqli_query($connection, $sql);
    while ($row = mysqli_fetch_object($result)) {
        $temp_uid = $row->id;
    }

    if ($uid == $temp_uid || $uid == $_SESSION['s_user_id']) {

        if ($uid == $temp_uid) $_SESSION['s_message_danger'] = 'The user admin cannot be deleted<BR>';
        if ($uid == $_SESSION['s_user_id']) $_SESSION['s_message_danger'] = 'You can\'t delete yourself<BR>';

    } else {

        $sql = "DELETE FROM user_settings
                WHERE user_id = '" . $uid . "'";
        $result = mysqli_query($connection, $sql);

        $sql = "DELETE FROM users
                WHERE id = '" . $uid . "'";
        $result = mysqli_query($connection, $sql);

        $_SESSION['s_message_success'] = 'User ' . $new_first_name . ' ' . $new_last_name . ' (' . $new_username . ') Deleted<BR>';

        header("Location: index.php");
        exit;

    }

}
?>
<?php include(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
    <?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<?php
echo $form->showFormTop('');
echo $form->showInputText('new_first_name', 'First Name (50)', '', $new_first_name, '50', '', '', '');
echo $form->showInputText('new_last_name', 'Last Name (50)', '', $new_last_name, '50', '', '', '');

if ($new_username == 'admin' || $new_username == 'administrator') { ?>

    <strong>Username</strong><BR><?php echo $new_username; ?><BR><BR><?php

} else {

    echo $form->showInputText('new_username', 'Username (30)', '', $new_username, '30', '', '', '');

}

echo $form->showInputText('new_email_address', 'Email Address (100)', '', $new_email_address, '100', '', '', '');

if ($new_username == 'admin' || $new_username == 'administrator') { ?>

    <strong>Admin Privileges?</strong>&nbsp;&nbsp;Yes<BR><BR><?php

} else {

    echo $form->showRadioTop('Admin Privileges?', '', '');
    echo $form->showRadioOption('new_is_admin', '1', 'Yes', $new_is_admin, '<BR>', '&nbsp;&nbsp;&nbsp;&nbsp;');
    echo $form->showRadioOption('new_is_admin', '0', 'No', $new_is_admin, '', '');
    echo $form->showRadioBottom('');

}

if ($new_username == 'admin' || $new_username == 'administrator') { ?>

    <strong>Active Account?</strong>&nbsp;&nbsp;Yes<BR><BR><?php

} else {

    echo $form->showRadioTop('Active Account?', '', '');
    echo $form->showRadioOption('new_is_active', '1', 'Yes', $new_is_active, '<BR>', '&nbsp;&nbsp;&nbsp;&nbsp;');
    echo $form->showRadioOption('new_is_active', '0', 'No', $new_is_active, '', '');
    echo $form->showRadioBottom('');

}

if ($new_username == 'admin' || $new_username == 'administrator') {

    echo $form->showInputHidden('new_username', 'admin');
    echo $form->showInputHidden('new_is_admin', '1');
    echo $form->showInputHidden('new_is_active', '1');

}

echo $form->showInputHidden('new_uid', $uid);
echo $form->showSubmitButton('Save', '', '');

echo $form->showFormBottom('');
?>
<BR><a href="reset-password.php?new_username=<?php echo $new_username; ?>&display=1">RESET AND DISPLAY PASSWORD</a><BR>
<BR><a href="reset-password.php?new_username=<?php echo $new_username; ?>">RESET AND EMAIL NEW PASSWORD TO USER</a><BR>
<BR><a href="edit.php?uid=<?php echo $uid; ?>&del=1">DELETE THIS USER</a>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
