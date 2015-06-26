<?php
/**
 * /system/change-password.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (C) 2010-2015 Greg Chetcuti <greg@chetcuti.com>
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
include("../_includes/start-session.inc.php");
include("../_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$error = new DomainMOD\Error();
$system = new DomainMOD\System();
$time = new DomainMOD\Timestamp();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();

$page_title = "Change Password";
$software_section = "system-change-password";

// Form Variables
$new_password = $_POST['new_password'];
$new_password_confirmation = $_POST['new_password_confirmation'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_password != "" && $new_password_confirmation != "" &&
    $new_password == $new_password_confirmation) {

    $query = "SELECT id
              FROM users
              WHERE id = ?
                AND email_address = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('is', $_SESSION['user_id'], $_SESSION['email_address']);
        $q->execute();
        $q->store_result();

        if ($q->num_rows() === 1) {

            $query_update = "UPDATE users
                             SET password = password(?),
                                 new_password = '0',
                                 update_time = ?
                             WHERE id = ?
                               AND email_address = ?";
            $q_update = $conn->stmt_init();

            if ($q_update->prepare($query_update)) {

                $timestamp = $time->time();

                $q_update->bind_param('ssis', $new_password, $timestamp, $_SESSION['user_id'],
                    $_SESSION['email_address']);
                $q_update->execute();
                $q_update->close();

            } else { $error->outputSqlError($conn, "ERROR"); }

            $_SESSION['result_message'] .= "Your password has been changed<BR>";

            header("Location: index.php");
            exit;

        } else {

            $_SESSION['result_message'] .= "Your password could not be updated<BR>";
            $_SESSION['result_message'] .= "If the problem persists please contact your administrator<BR>";

        }

        $q->close();

    } else { $error->outputSqlError($conn, "ERROR"); }

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
		if ($new_password == "" && $new_password_confirmation == "") {
		
			$_SESSION['result_message'] .= "Your passwords were left blank<BR>";

		} else {

			$_SESSION['result_message'] .= "Your passwords didn't match<BR>";
		
		}
		
	}
}
?>
<?php echo $system->doctype(); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<form name="change_password_form" method="post">
<strong>New Password (255)</strong><BR><BR><input type="password" name="new_password" size="20" maxlength="255">
<BR><BR>
<strong>Confirm New Password</strong><BR><BR><input type="password" name="new_password_confirmation" size="20"
                                                    maxlength="255">
<BR><BR>
<input type="submit" name="button" value="Change Password &raquo;">
</form>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
