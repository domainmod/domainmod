<?php
/**
 * /assets/edit/account-owner.php
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
require_once(DIR_INC . 'settings/assets-edit-owner.inc.php');
require_once(DIR_INC . 'database.inc.php');

$system->authCheck($web_root);

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$oid = $_GET['oid'];

$new_owner = $_POST['new_owner'];
$new_notes = $_POST['new_notes'];
$new_oid = $_POST['new_oid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $system->readOnlyCheck($_SERVER['HTTP_REFERER']);

    if ($new_owner != "") {

        $query = "UPDATE owners
                  SET `name` = ?,
                      notes = ?,
                      update_time = ?
                  WHERE id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $timestamp = $time->stamp();

            $q->bind_param('sssi', $new_owner, $new_notes, $timestamp, $new_oid);
            $q->execute();
            $q->close();

        } else {
            $error->outputSqlError($conn, "ERROR");
        }

        $oid = $new_oid;

        $_SESSION['s_message_success'] .= "Owner " . $new_owner . " Updated<BR>";

        header("Location: ../account-owners.php");
        exit;

    } else {

        $_SESSION['s_message_danger'] .= "Enter the owner's name<BR>";

    }

} else {

    $query = "SELECT `name`, notes
              FROM owners
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $oid);
        $q->execute();
        $q->store_result();
        $q->bind_result($new_owner, $new_notes);
        $q->fetch();
        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

}

if ($del == "1") {

    $query = "SELECT owner_id
              FROM registrar_accounts
              WHERE owner_id = ?
              LIMIT 1";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $oid);
        $q->execute();
        $q->store_result();

        if ($q->num_rows() > 0) {

            $existing_registrar_accounts = 1;

        }

        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

    $query = "SELECT owner_id
              FROM ssl_accounts
              WHERE owner_id = ?
              LIMIT 1";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $oid);
        $q->execute();
        $q->store_result();

        if ($q->num_rows() > 0) {

            $existing_ssl_accounts = 1;

        }

        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

    $query = "SELECT owner_id
              FROM domains
              WHERE owner_id = ?
              LIMIT 1";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $oid);
        $q->execute();
        $q->store_result();

        if ($q->num_rows() > 0) {

            $existing_domains = 1;

        }

        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

    $query = "SELECT owner_id
              FROM ssl_certs
              WHERE owner_id = ?
              LIMIT 1";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $oid);
        $q->execute();
        $q->store_result();

        if ($q->num_rows() > 0) {

            $existing_ssl_certs = 1;

        }

        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

    if ($existing_registrar_accounts > 0 || $existing_ssl_accounts > 0 || $existing_domains > 0 ||
        $existing_ssl_certs > 0
    ) {

        if ($existing_registrar_accounts > 0) {
            $_SESSION['s_message_danger'] .= "This Owner has registrar accounts associated with it and cannot be
                deleted<BR>";
        }

        if ($existing_domains > 0) {
            $_SESSION['s_message_danger'] .= "This Owner has domains associated with it and cannot be deleted<BR>";
        }

        if ($existing_ssl_accounts > 0) {
            $_SESSION['s_message_danger'] .= "This Owner has SSL accounts associated with it and cannot be deleted<BR>";
        }

        if ($existing_ssl_certs > 0) {
            $_SESSION['s_message_danger'] .= "This Owner has SSL certificates associated with it and cannot be deleted<BR>";
        }

    } else {

        $_SESSION['s_message_danger'] .= "Are you sure you want to delete this Owner?<BR><BR><a
            href=\"account-owner.php?oid=$oid&really_del=1\">YES, REALLY DELETE THIS OWNER</a><BR>";

    }

}

if ($really_del == "1") {

    $query = "DELETE FROM owners
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $oid);
        $q->execute();
        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

    $_SESSION['s_message_success'] .= "Owner " . $new_owner . " Deleted<BR>";

    header("Location: ../account-owners.php");
    exit;

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
echo $form->showInputText('new_owner', 'Owner Name (100)', '', $new_owner, '100', '', '1', '', '');
echo $form->showInputTextarea('new_notes', 'Notes', '', $new_notes, '', '', '');
echo $form->showInputHidden('new_oid', $oid);
echo $form->showSubmitButton('Save', '', '');
echo $form->showFormBottom('');
?>
<BR><a href="account-owner.php?oid=<?php echo urlencode($oid); ?>&del=1">DELETE THIS OWNER</a>
<?php require_once(DIR_INC . 'layout/footer.inc.php'); ?>
</body>
</html>
