<?php
/**
 * /admin/reset-password.php
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
$system->checkAdminUser($_SESSION['s_is_admin'], $web_root);

$page_title = "Reset Password";
$software_section = "system";

$new_username = $_GET['new_username'];
$display = $_GET['display'];

if ($new_username != "") {

    $query = "SELECT id, username, email_address
              FROM users
              WHERE username = ?
                AND active = '1'";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('s', $new_username);
        $q->execute();
        $q->store_result();
        $q->bind_result($id, $username, $email_address);

        if ($q->num_rows() === 1) {

            while ($q->fetch()) {

                $new_password = substr(md5(time()), 0, 8);

                $query = "UPDATE users
                          SET password = password(?),
                              new_password = '1',
                              update_time = ?
                          WHERE username = ?
                            AND email_address = ?";
                $q = $conn->stmt_init();

                if ($q->prepare($query)) {

                    $timestamp = $time->time();

                    $q->bind_param('ssss', $new_password, $timestamp, $username, $email_address);
                    $q->execute();
                    $q->close();

                } else {
                    $error->outputSqlError($conn, "ERROR");
                }

                if ($display == "1") {

                    $_SESSION['s_result_message'] .= "The new password for " . $username . " is " . $new_password .
                        "<BR>";

                } else {

                    include(DIR_INC . "email/send-new-password.inc.php");
                    $_SESSION['s_result_message'] .= "The password has been reset and emailed to the account holder<BR>";

                }

                header("Location: edit/user.php?uid=$id");
                exit;

            }

        } else {

            $_SESSION['s_result_message'] .= "You have entered an invalid username<BR>";

            header("Location: users.php");
            exit;

        }

        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'GET') {

        if ($new_username == "") $_SESSION['s_result_message'] .= "Enter the username<BR>";

        header("Location: users.php");
        exit;

    }

}
