<?php
/**
 * /assets/edit/registrar.php
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
include(DIR_INC . "settings/assets-edit-registrar.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$rid = $_REQUEST['rid'];
$new_registrar = $_POST['new_registrar'];
$new_url = $_POST['new_url'];
$new_notes = $_POST['new_notes'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($new_registrar != "") {

        $query = "UPDATE registrars
                  SET `name` = ?,
                      url = ?,
                      notes = ?,
                      update_time = ?
                  WHERE id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $timestamp = $time->stamp();

            $q->bind_param('ssssi', $new_registrar, $new_url, $new_notes, $timestamp, $rid);
            $q->execute();
            $q->close();

        } else {
            $error->outputSqlError($conn, "ERROR");
        }

        $_SESSION['s_message_success'] = "Registrar " . $new_registrar . " Updated<BR>";

        header("Location: ../registrars.php");
        exit;

    } else {

        if ($new_registrar == "") $_SESSION['s_message_danger'] .= "Enter the registrar name<BR>";

    }

} else {

    $query = "SELECT `name`, url, notes
              FROM registrars
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $rid);
        $q->execute();
        $q->store_result();
        $q->bind_result($new_registrar, $new_url, $new_notes);
        $q->fetch();
        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

}

if ($del == "1") {

    $query = "SELECT registrar_id
              FROM registrar_accounts
              WHERE registrar_id = ?
              LIMIT 1";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $rid);
        $q->execute();
        $q->store_result();

        if ($q->num_rows() > 0) {

            $existing_registrar_accounts = 1;

        }

        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

    $query = "SELECT registrar_id
              FROM domains
              WHERE registrar_id = ?
              LIMIT 1";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $rid);
        $q->execute();
        $q->store_result();

        if ($q->num_rows() > 0) {

            $existing_domains = 1;

        }

        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

    if ($existing_registrar_accounts > 0 || $existing_domains > 0) {

        if ($existing_registrar_accounts > 0) $_SESSION['s_message_danger'] .= "This Registrar has Registrar Accounts associated with it and cannot be deleted<BR>";
        if ($existing_domains > 0) $_SESSION['s_message_danger'] .= "This Registrar has domains associated with it and cannot be deleted<BR>";

    } else {

        $_SESSION['s_message_danger'] = "Are you sure you want to delete this Registrar?<BR><BR><a
            href=\"registrar.php?rid=$rid&really_del=1\">YES, REALLY DELETE THIS REGISTRAR</a><BR>";

    }

}

if ($really_del == "1") {

    $query = "DELETE FROM fees
              WHERE registrar_id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $rid);
        $q->execute();
        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

    $query = "DELETE FROM registrar_accounts
              WHERE registrar_id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $rid);
        $q->execute();
        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

    $query = "DELETE FROM registrars
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $rid);
        $q->execute();
        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

    $_SESSION['s_message_success'] = "Registrar " . $new_registrar . " Deleted<BR>";

    $system->checkExistingAssets($connection);

    header("Location: ../registrars.php");
    exit;

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
echo $form->showInputText('new_registrar', 'Registrar Name (100)', '', $new_registrar, '100', '', '', '');
echo $form->showInputText('new_url', 'Registrar\'s URL (100)', '', $new_url, '100', '', '', '');
echo $form->showInputTextarea('new_notes', 'Notes', '', $new_notes, '', '');
echo $form->showInputHidden('rid', $rid);
echo $form->showSubmitButton('Save', '', '');
echo $form->showFormBottom('');
?>
<?php
$rid_clean = (integer) $rid;
?>
<BR><a href="registrar.php?rid=<?php echo $rid_clean; ?>&del=1">DELETE THIS REGISTRAR</a>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
