<?php
/**
 * /settings/profile/index.php
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

$error = new DomainMOD\Error();
$system = new DomainMOD\System();
$conversion = new DomainMOD\Conversion();
$form = new DomainMOD\Form();
$time = new DomainMOD\Time();
$timestamp = $time->stamp();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "settings/settings-profile.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck($web_root);

$new_first_name = $_POST['new_first_name'];
$new_last_name = $_POST['new_last_name'];
$new_email_address = $_POST['new_email_address'];
$new_currency = $_POST['new_currency'];
$new_timezone = $_POST['new_timezone'];
$new_expiration_email = $_POST['new_expiration_email'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_first_name != "" && $new_last_name != "" && $new_email_address != "") {

    $query = "SELECT id
              FROM users
              WHERE id = ?
                AND email_address = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $_SESSION['s_message_success'] .= "Your profile was updated<BR>";

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

                $q_u->bind_param('sssssis', $new_first_name, $new_last_name, $new_email_address, $timestamp,
                    $_SESSION['s_user_id'], $_SESSION['s_email_address']);
                $q_u->execute();
                $q_u->close();

            } else {
                $error->outputSqlError($conn, "ERROR");
            }

            $query = "SELECT default_currency, default_timezone
                      FROM user_settings
                      WHERE user_id = ?";
            $q = $conn->stmt_init();

            if ($q->prepare($query)) {

                $q->bind_param('i', $_SESSION['s_user_id']);
                $q->execute();
                $q->store_result();
                $q->bind_result($user_currency, $user_timezone);

                while ($q->fetch()) {

                    $saved_currency = $user_currency;
                    $saved_timezone = $user_timezone;

                }

                $q->close();

            } else $error->outputSqlError($conn, "ERROR");

            if ($saved_currency != $new_currency) {

                $query = "SELECT id
                          FROM currencies
                          WHERE currency = ?";
                $q = $conn->stmt_init();

                if ($q->prepare($query)) {

                    $q->bind_param('s', $new_currency);
                    $q->execute();
                    $q->store_result();
                    $q->bind_result($id);

                    while ($q->fetch()) {

                        $temp_new_currency_id = $id;

                    }

                    $q->close();

                } else $error->outputSqlError($conn, "ERROR");

                $sql_new_currency = "SELECT id
                                     FROM currency_conversions
                                     WHERE user_id = '" . $_SESSION['s_user_id'] . "'
                                       AND currency_id = '" . $temp_new_currency_id . "'";
                $result_new_currency = mysqli_query($connection, $sql_new_currency);

                if (mysqli_num_rows($result_new_currency) == 0) {

                    //@formatter:off
                    $sql_insert_currency = "INSERT INTO currency_conversions
                                            (currency_id, user_id, conversion, insert_time, update_time) VALUES
                                            ('" . $temp_new_currency_id . "', '" . $_SESSION['s_user_id'] . "', '1', '" .
                                             $timestamp . "', '" . $timestamp . "')";
                    $result_insert_currency = mysqli_query($connection, $sql_insert_currency);
                    //@formatter:on

                }

                $_SESSION['s_message_success']
                    .= $conversion->updateRates($connection, $new_currency, $_SESSION['s_user_id']);

            }

            $query = "UPDATE user_settings
                      SET default_currency = ?,
                          default_timezone = ?,
                          expiration_emails = ?,
                          update_time = ?
                      WHERE user_id = ?";
            $q = $conn->stmt_init();

            if ($q->prepare($query)) {

                $q->bind_param('ssisi', $new_currency, $new_timezone, $new_expiration_email, $timestamp, $_SESSION['s_user_id']);
                $q->execute();
                $q->close();

            } else $error->outputSqlError($conn, "ERROR");

            $_SESSION['s_first_name'] = $new_first_name;
            $_SESSION['s_last_name'] = $new_last_name;
            $_SESSION['s_email_address'] = $new_email_address;
            $_SESSION['s_default_currency'] = $new_currency;
            $_SESSION['s_default_timezone'] = $new_timezone;
            $_SESSION['s_expiration_email'] = $new_expiration_email;

            $query = "SELECT `name`, symbol, symbol_order, symbol_space
                      FROM currencies
                      WHERE currency = ?";
            $q = $conn->stmt_init();

            if ($q->prepare($query)) {

                $q->bind_param('s', $new_currency);
                $q->execute();
                $q->store_result();
                $q->bind_result($t_name, $t_symbol, $t_order, $t_space);

                while ($q->fetch()) {

                    $_SESSION['s_default_currency_name'] = $t_name;
                    $_SESSION['s_default_currency_symbol'] = $t_symbol;
                    $_SESSION['s_default_currency_symbol_order'] = $t_order;
                    $_SESSION['s_default_currency_symbol_space'] = $t_space;

                }

                $q->close();

            } else $error->outputSqlError($conn, "ERROR");

            header("Location: ../index.php");
            exit;

        } else {

            $_SESSION['s_message_danger'] .= "Your profile could not be updated<BR>";
            $_SESSION['s_message_danger'] .= "If the problem persists please contact your administrator<BR>";

        }

        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

} else {

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {

        if ($new_email_address == "") $_SESSION['s_message_danger'] .= "Your email address could not be updated<BR>";
        if ($new_first_name == "") $_SESSION['s_message_danger'] .= "Your first name could not be updated<BR>";
        if ($new_last_name == "") $_SESSION['s_message_danger'] .= "Your last name could not be updated<BR>";

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

if ($new_first_name != "") { $temp_first_name = $new_first_name; } else { $temp_first_name = $_SESSION['s_first_name']; }
echo $form->showInputText('new_first_name', 'First Name (50)', '', $temp_first_name, '50', '', '1', '', '');

if ($new_last_name != "") { $temp_last_name = $new_last_name; } else { $temp_last_name = $_SESSION['s_last_name']; }
echo $form->showInputText('new_last_name', 'Last Name (50)', '', $temp_last_name, '50', '', '1', '', '');

if ($new_email_address != "") { $temp_email_address = $new_email_address; } else { $temp_email_address = $_SESSION['s_email_address']; }
echo $form->showInputText('new_email_address', 'Email Address (100)', '', $temp_email_address, '100', '', '1', '', '');

echo $form->showDropdownTop('new_currency', 'Currency', '', '', '');
$sql = "SELECT currency, `name`, symbol
        FROM currencies
        ORDER BY name";
$result = mysqli_query($connection, $sql);
while ($row = mysqli_fetch_object($result)) {
    echo $form->showDropdownOption($row->currency, $row->name . ' (' . $row->currency . ' ' . $row->symbol . ')', $_SESSION['s_default_currency']);
}
echo $form->showDropdownBottom('');

echo $form->showDropdownTop('new_timezone', 'Time Zone', '', '', '');
$sql = "SELECT timezone
        FROM timezones
        ORDER BY timezone";
$result = mysqli_query($connection, $sql);
while ($row = mysqli_fetch_object($result)) {
    echo $form->showDropdownOption($row->timezone, $row->timezone, $_SESSION['s_default_timezone']);
}
echo $form->showDropdownBottom('');

if ($new_expiration_email != "") { $temp_expiration_email = $new_expiration_email; } else { $temp_expiration_email = $_SESSION['s_expiration_email']; }
echo $form->showRadioTop('Subscribe to Domain & SSL Certificate expiration emails?', '', '');
echo $form->showRadioOption('new_expiration_email', '1', 'Yes', $temp_expiration_email, '<BR>', '&nbsp;&nbsp;&nbsp;&nbsp;');
echo $form->showRadioOption('new_expiration_email', '0', 'No', $temp_expiration_email, '', '');
echo $form->showRadioBottom('');

echo $form->showSubmitButton('Update Profile', '', '');
echo $form->showFormBottom('');
?>

<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
