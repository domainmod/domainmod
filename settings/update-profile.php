<?php
/**
 * /settings/update-profile.php
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

$page_title = "Update Profile";
$software_section = "system-update-profile";

$new_first_name = $_POST['new_first_name'];
$new_last_name = $_POST['new_last_name'];
$new_email_address = $_POST['new_email_address'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_email_address != "" && $new_first_name != "" && $new_last_name != "") {

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

            $query_u = "UPDATE users
                        SET first_name = ?,
                            last_name = ?,
                            email_address = ?,
                            update_time = ?
                        WHERE id = ?
                          AND email_address = ?";
            $q_u = $conn->stmt_init();

            if ($q_u->prepare($query_u)) {

                $timestamp = $time->time();

                $q_u->bind_param('ssssis', $new_first_name, $new_last_name, $new_email_address, $timestamp,
                    $_SESSION['s_user_id'], $_SESSION['s_email_address']);
                $q_u->execute();
                $q_u->close();

            } else {
                $error->outputSqlError($conn, "ERROR");
            }

            $_SESSION['s_email_address'] = $new_email_address;
            $_SESSION['s_first_name'] = $new_first_name;
            $_SESSION['s_last_name'] = $new_last_name;

            $_SESSION['s_result_message'] .= "Your profile was updated<BR>";

            header("Location: index.php");
            exit;

        } else {

            $_SESSION['s_result_message'] .= "Your profile could not be updated<BR>";
            $_SESSION['s_result_message'] .= "If the problem persists please contact your administrator<BR>";

        }

        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        if ($new_email_address == "") $_SESSION['s_result_message'] .= "Your email address could not be updated<BR>";
        if ($new_first_name == "") $_SESSION['s_result_message'] .= "Your first name could not be updated<BR>";
        if ($new_last_name == "") $_SESSION['s_result_message'] .= "Your last name could not be updated<BR>";

    }

}
?>
<?php include(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
    <?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<form name="change_email_address_form" method="post">
    <strong>First Name (50):</strong><BR><BR>
    <input name="new_first_name" type="text" size="50" maxlength="50" value="<?php if ($new_first_name != "") {
        echo $new_first_name;
    } else {
        echo $_SESSION['s_first_name'];
    } ?>">
    <BR><BR>
    <strong>Last Name (50):</strong><BR><BR>
    <input name="new_last_name" type="text" size="50" maxlength="50" value="<?php if ($new_last_name != "") {
        echo $new_last_name;
    } else {
        echo $_SESSION['s_last_name'];
    } ?>">
    <BR><BR>
    <strong>Email Address (100):</strong><BR><BR>
    <input name="new_email_address" type="text" size="50" maxlength="100" value="<?php if ($new_email_address != "") {
        echo $new_email_address;
    } else {
        echo $_SESSION['s_email_address'];
    } ?>">
    <BR><BR>
    <input type="submit" name="button" value="Update Profile &raquo;">
</form>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
