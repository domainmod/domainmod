<?php
/**
 * /assets/add/registrar-account.php
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
include(DIR_INC . "settings/assets-add-registrar-account.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();
$system->readOnlyCheck($_SERVER['HTTP_REFERER']);

$new_owner_id = $_POST['new_owner_id'];
$new_registrar_id = $_POST['new_registrar_id'];
$new_email_address = $_POST['new_email_address'];
$new_username = $_POST['new_username'];
$new_password = $_POST['new_password'];
$new_api_app_name = $_POST['new_api_app_name'];
$new_api_key = $_POST['new_api_key'];
$new_api_secret = $_POST['new_api_secret'];
$new_api_ip_id = $_POST['new_api_ip_id'];
$new_reseller = $_POST['new_reseller'];
$new_reseller_id = $_POST['new_reseller_id'];
$new_notes = $_POST['new_notes'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($new_username != "" && $new_owner_id != "" && $new_registrar_id != "" && $new_owner_id != "0" && $new_registrar_id != "0") {

        $query = "INSERT INTO registrar_accounts
                  (owner_id, registrar_id, email_address, username, `password`, reseller, reseller_id, api_app_name, api_key, api_secret, api_ip_id, notes, created_by, insert_time)
                  VALUES
                  (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $timestamp = $time->stamp();

            $q->bind_param('iisssissssisis', $new_owner_id, $new_registrar_id, $new_email_address, $new_username,
                $new_password, $new_reseller, $new_reseller_id, $new_api_app_name, $new_api_key, $new_api_secret,
                $new_api_ip_id, $new_notes, $_SESSION['s_user_id'], $timestamp);
            $q->execute();
            $q->close();

        } else {
            $error->outputSqlError($conn, "ERROR");
        }

        $query = "SELECT `name`
                  FROM registrars
                  WHERE id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('i', $new_registrar_id);
            $q->execute();
            $q->store_result();
            $q->bind_result($temp_registrar);
            $q->fetch();
            $q->close();

        } else {
            $error->outputSqlError($conn, "ERROR");
        }

        $query = "SELECT `name`
                  FROM owners
                  WHERE id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('i', $new_owner_id);
            $q->execute();
            $q->store_result();
            $q->bind_result($temp_owner);
            $q->fetch();
            $q->close();

        } else {
            $error->outputSqlError($conn, "ERROR");
        }

        $_SESSION['s_message_success'] .= "Registrar Account " . $new_username . " (" . $temp_registrar . ", " . $temp_owner . ") Added<BR>";

        if ($_SESSION['s_has_registrar_account'] != '1') {

            $system->checkExistingAssets($connection);

            header("Location: ../../domains/index.php");

        } else {

            header("Location: ../registrar-accounts.php");

        }
        exit;

    } else {

        if ($new_owner_id == "" || $new_owner_id == "0") {

            $_SESSION['s_message_danger'] .= "Choose the owner<BR>";

        }

        if ($new_registrar_id == "" || $new_registrar_id == "0") {

            $_SESSION['s_message_danger'] .= "Choose the registrar<BR>";

        }

        if ($new_username == "") { $_SESSION['s_message_danger'] .= "Enter a username<BR>"; }

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

$query = "SELECT id, `name`
          FROM registrars
          ORDER BY `name` ASC";
$q = $conn->stmt_init();

if ($q->prepare($query)) {

    $q->execute();
    $q->store_result();
    $q->bind_result($id, $name);

    echo $form->showDropdownTop('new_registrar_id', 'Registrar', '', '1', '');

    if ($new_registrar_id == '') {

        $to_compare = $_SESSION['s_default_registrar'];

    } else {

        $to_compare = $new_registrar_id;
    }

    while ($q->fetch()) {

        echo $form->showDropdownOption($id, $name, $to_compare);

    }

    echo $form->showDropdownBottom('');

    $q->close();

} else {
    $error->outputSqlError($conn, "ERROR");
}

$query = "SELECT id, `name`
          FROM owners
          ORDER BY `name` ASC";
$q = $conn->stmt_init();

if ($q->prepare($query)) {

    $q->execute();
    $q->store_result();
    $q->bind_result($id, $name);

    echo $form->showDropdownTop('new_owner_id', 'Account Owner', '', '1', '');

    if ($new_owner_id == '') {

        $to_compare = $_SESSION['s_default_owner_domains'];

    } else {

        $to_compare = $new_owner_id;
    }

    while ($q->fetch()) {

        echo $form->showDropdownOption($id, $name, $to_compare);

    }

    echo $form->showDropdownBottom('');

    $q->close();

} else {
    $error->outputSqlError($conn, "ERROR");
}

echo $form->showInputText('new_email_address', 'Email Address (100)', '', $new_email_address, '100', '', '', '', '');
echo $form->showInputText('new_username', 'Username (100)', '', $new_username, '100', '', '1', '', '');
echo $form->showInputText('new_password', 'Password (255)', '', $new_password, '255', '', '', '', '');

echo $form->showRadioTop('Reseller Account?', '', '');
if ($new_reseller == '') $new_reseller = '0';
echo $form->showRadioOption('new_reseller', '1', 'Yes', $new_reseller, '<BR>', '&nbsp;&nbsp;&nbsp;&nbsp;');
echo $form->showRadioOption('new_reseller', '0', 'No', $new_reseller, '', '');
echo $form->showRadioBottom('');

echo $form->showInputText('new_reseller_id', 'Reseller ID (100)', '', $new_reseller_id, '100', '', '', '', '');
?>

<div class="box box-default collapsed-box box-solid">
    <div class="box-header with-border">
        <h3 class="box-title">API Credentials</h3>
        <div class="box-tools">
            <button type="button" class="btn btn-box-tool" data-widget="collapse"><i
                    class="fa fa-plus"></i>
            </button>
        </div>
    </div>
    <div class="box-body"><?php

        echo $form->showInputText('new_api_app_name', 'API App Name', '', $new_api_app_name, '255', '', '', '', '');
        echo $form->showInputText('new_api_key', 'API Key', '', $new_api_key, '255', '', '', '', '');
        echo $form->showInputText('new_api_secret', 'API Secret', '', $new_api_secret, '255', '', '', '', '');

        $query = "SELECT id, `name`, ip
                  FROM ip_addresses
                  ORDER BY `name` ASC";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $q->execute();
            $q->store_result();
            $q->bind_result($id, $name, $ip_address);

            echo $form->showDropdownTop('new_api_ip_id', 'API IP Address', 'The IP Address that you whitelisted with the domain registrar for API access.', '', '');

            echo $form->showDropdownOption('0', 'n/a', '0');

            while ($q->fetch()) {

                echo $form->showDropdownOption($id, $name . ' (' . $ip_address . ')', $new_api_ip_id);

            }

            echo $form->showDropdownBottom('');

            $q->close();

        } else {
            $error->outputSqlError($conn, "ERROR");
        } ?>

    </div>
</div><BR><?php

echo $form->showInputTextarea('new_notes', 'Notes', '', $new_notes, '', '', '');
echo $form->showSubmitButton('Add Registrar Account', '', '');
echo $form->showFormBottom('');
?>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
