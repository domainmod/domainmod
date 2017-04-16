<?php
/**
 * /settings/password/index.php
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
require_once('../../_includes/start-session.inc.php');
require_once('../../_includes/init.inc.php');

require_once(DIR_ROOT . 'classes/Autoloader.php');
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$error = new DomainMOD\Error();
$system = new DomainMOD\System();
$form = new DomainMOD\Form();
$time = new DomainMOD\Time();

require_once(DIR_INC . 'head.inc.php');
require_once(DIR_INC . 'config.inc.php');
require_once(DIR_INC . 'software.inc.php');
require_once(DIR_INC . 'settings/settings-password.inc.php');
require_once(DIR_INC . 'database.inc.php');

$system->authCheck($web_root);

$new_password = $_POST['new_password'];
$new_password_confirmation = $_POST['new_password_confirmation'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_password != "" && $new_password_confirmation != "" &&
    $new_password == $new_password_confirmation
) {

    $query = "SELECT id
              FROM users
              WHERE id = ?
                AND email_address = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('is', $_SESSION['s_user_id'], $_SESSION['s_email_address']);
        $q->execute();
        $q->store_result();

        if ($q->num_rows() === 1) {

            $query_update = "UPDATE users
                             SET `password` = password(?),
                                 new_password = '0',
                                 update_time = ?
                             WHERE id = ?
                               AND email_address = ?";
            $q_update = $conn->stmt_init();

            if ($q_update->prepare($query_update)) {

                $timestamp = $time->stamp();

                $q_update->bind_param('ssis', $new_password, $timestamp, $_SESSION['s_user_id'],
                    $_SESSION['s_email_address']);
                $q_update->execute();
                $q_update->close();

            } else {
                $error->outputSqlError($conn, "ERROR");
            }

            $_SESSION['s_message_success'] .= "Password changed<BR>";

            header("Location: ../index.php");
            exit;

        } else {

            $_SESSION['s_message_danger'] .= "Your password could not be updated<BR>";
            $_SESSION['s_message_danger'] .= "If the problem persists please contact your administrator<BR>";

        }

        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        if ($new_password == "" && $new_password_confirmation == "") {

            $_SESSION['s_message_danger'] .= "Your passwords were left blank<BR>";

        } else {

            $_SESSION['s_message_danger'] .= "Your passwords didn't match<BR>";

        }

    }
}
?>
<?php require_once(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
    <?php require_once(DIR_INC . 'layout/head-tags.inc.php'); ?>
</head>
<body class="hold-transition skin-red sidebar-mini">
<?php require_once(DIR_INC . 'layout/header.inc.php'); ?>

<?php
echo $form->showFormTop('');
echo $form->showInputText('new_password', 'New Password (255)', '', '', '255', '1', '1', '', '');
echo $form->showInputText('new_password_confirmation', 'Confirm New Password', '', '', '255', '1', '1', '', '');
echo $form->showSubmitButton('Change Password', '', '');
echo $form->showFormBottom('');
?>
<?php require_once(DIR_INC . 'layout/footer.inc.php'); ?>
</body>
</html>
