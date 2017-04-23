<?php
/**
 * /assets/add/ssl-provider-account.php
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

$system = new DomainMOD\System();
$error = new DomainMOD\Error();
$time = new DomainMOD\Time();
$form = new DomainMOD\Form();

require_once(DIR_INC . 'head.inc.php');
require_once(DIR_INC . 'config.inc.php');
require_once(DIR_INC . 'software.inc.php');
require_once(DIR_INC . 'settings/assets-add-ssl-account.inc.php');
require_once(DIR_INC . 'database.inc.php');

$system->authCheck($web_root);
$system->readOnlyCheck($_SERVER['HTTP_REFERER']);

$new_owner_id = $_POST['new_owner_id'];
$new_ssl_provider_id = $_POST['new_ssl_provider_id'];
$new_email_address = $_POST['new_email_address'];
$new_username = $_POST['new_username'];
$new_password = $_POST['new_password'];
$new_reseller = $_POST['new_reseller'];
$new_reseller_id = $_POST['new_reseller_id'];
$new_notes = $_POST['new_notes'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($new_username != "" && $new_owner_id != "" && $new_ssl_provider_id != "" && $new_owner_id != "0" && $new_ssl_provider_id != "0") {

        $query = "INSERT INTO ssl_accounts
                  (owner_id, ssl_provider_id, email_address, username, `password`, reseller, reseller_id, notes, created_by, insert_time)
                  VALUES
                  (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $q = $dbcon->stmt_init();

        if ($q->prepare($query)) {

            $timestamp = $time->stamp();

            $q->bind_param('iisssissis', $new_owner_id, $new_ssl_provider_id, $new_email_address, $new_username,
                    $new_password, $new_reseller, $new_reseller_id, $new_notes, $_SESSION['s_user_id'], $timestamp);
            $q->execute() or die('1');
            $q->close();

        } else {
            $error->outputSqlError($dbcon, '1', 'ERROR');
        }

        $query = "SELECT `name`
                  FROM ssl_providers
                  WHERE id = ?";
        $q = $dbcon->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('i', $new_ssl_provider_id);
            $q->execute();
            $q->store_result();
            $q->bind_result($temp_ssl_provider);
            $q->fetch();
            $q->close();

        } else {
            $error->outputSqlError($dbcon, '1', 'ERROR');
        }

        $query = "SELECT `name`
                  FROM owners
                  WHERE id = ?";
        $q = $dbcon->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('i', $new_owner_id);
            $q->execute();
            $q->store_result();
            $q->bind_result($temp_owner);
            $q->fetch();
            $q->close();

        } else {
            $error->outputSqlError($dbcon, '1', 'ERROR');
        }

        $_SESSION['s_message_success'] .= "SSL Account " . $new_username . " (" . $temp_ssl_provider . ", " . $temp_owner . ") Added<BR>";

        if ($_SESSION['s_has_ssl_account'] != '1') {

            $system->checkExistingAssets($dbcon);

            header("Location: ../../ssl/index.php");

        } else {

            header("Location: ../ssl-accounts.php");

        }
        exit;

    } else {

        if ($new_owner_id == '' || $new_owner_id == '0') {

            $_SESSION['s_message_danger'] .= "Choose the owner<BR>";

        }

        if ($new_ssl_provider_id == '' || $new_ssl_provider_id == '0') {

            $_SESSION['s_message_danger'] .= "Choose the SSL Provider<BR>";

        }

        if ($new_username == "") { $_SESSION['s_message_danger'] .= "Enter a username<BR>"; }

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

$query = "SELECT id, `name`
              FROM ssl_providers
              ORDER BY `name` ASC";
$q = $dbcon->stmt_init();

if ($q->prepare($query)) {
    $q->execute();
    $q->store_result();
    $q->bind_result($id, $name);

    echo $form->showDropdownTop('new_ssl_provider_id', 'SSL Provider', '', '1', '');

    if ($new_ssl_provider_id == '') {

        $to_compare = $_SESSION['s_default_ssl_provider'];

    } else {

        $to_compare = $new_ssl_provider_id;
    }

    while ($q->fetch()) {

        echo $form->showDropdownOption($id, $name, $to_compare);

    }

    echo $form->showDropdownBottom('');

    $q->close();

} else {
    $error->outputSqlError($dbcon, '1', 'ERROR');
}

$query = "SELECT id, `name`
          FROM owners
          ORDER BY `name` ASC";
$q = $dbcon->stmt_init();

if ($q->prepare($query)) {

    $q->execute();
    $q->store_result();
    $q->bind_result($id, $name);

    echo $form->showDropdownTop('new_owner_id', 'Account Owner', '', '1', '');

    if ($new_owner_id == '') {

        $to_compare = $_SESSION['s_default_owner_ssl'];

    } else {

        $to_compare = $new_owner_id;
    }

    while ($q->fetch()) {

        echo $form->showDropdownOption($id, $name, $to_compare);

    }

    echo $form->showDropdownBottom('');

    $q->close();

} else {
    $error->outputSqlError($dbcon, '1', 'ERROR');
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
echo $form->showInputTextarea('new_notes', 'Notes', '', $new_notes, '', '', '');
echo $form->showSubmitButton('Add SSL Provider Account', '', '');
echo $form->showFormBottom('');
?>
<?php require_once(DIR_INC . 'layout/footer.inc.php'); ?>
</body>
</html>
