<?php
/**
 * /reset.php
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
include("_includes/start-session.inc.php");
include("_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$maint = new DomainMOD\Maintenance();
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "database.inc.php");

$system->loginCheck();

$page_title = "Reset Password";
$software_section = "resetpassword";

$new_data = $_REQUEST['new_data'];

if ($new_data != "") {

    $query = "SELECT first_name, last_name, username, email_address
              FROM users
              WHERE (username = ? OR email_address = ?)
                AND active = '1'";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('ss', $new_data, $new_data);
        $q->execute();
        $q->store_result();
        $q->bind_result($first_name, $last_name, $username, $email_address);

        if ($q->num_rows() == 1) {

            while ($q->fetch()) {

                $new_password = substr(md5(time()), 0, 8);

                $sql_update = "UPDATE users
                               SET `password` = password('" . $new_password . "'),
                                   new_password = '1',
                                   update_time = '" . $time->stamp() . "'
                               WHERE username = '" . $username . "'
                                 AND email_address = '" . $email_address . "'";
                $result_update = mysqli_query($connection, $sql_update);

                include(DIR_INC . "email/send-new-password.inc.php");

                $_SESSION['s_message_success'] .= "If there is a matching username or email address in the system your new password will been emailed to you.<BR>";

                header("Location: " . $web_root . "/");
                exit;

            }

        } else {

            $_SESSION['s_message_success'] .= "If there is a matching username or email address in the system your new password will been emailed to you.<BR>";

            header("Location: " . $web_root . "/");
            exit;

        }

        $q->close();

    } else $error->outputSqlError($conn, "ERROR");

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        if ($new_data == "") {
            $_SESSION['s_message_danger'] .= "Enter your username or email address<BR>";
        }

    }

}
?>
<?php include(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
    <?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body class="hold-transition skin-red" onLoad="document.forms[0].elements[0].focus()">
<?php include(DIR_INC . "layout/header-login.inc.php"); ?>
<?php
    echo $form->showFormTop('');
    echo $form->showInputText('new_data', 'Username or Email Address', '', $new_data, '100', '', '', '', '');
    echo $form->showSubmitButton('Reset Password', '', '');
    echo $form->showFormBottom('');
?>
<BR><a href="<?php echo $web_root; ?>/">Cancel Password Reset</a>
<?php include(DIR_INC . "layout/footer-login.inc.php"); ?>
</body>
</html>
