<?php
/**
 * /assets/edit/host.php
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
include("../../_includes/start-session.inc.php");
include("../../_includes/init.inc.php");

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

$page_title = "Editing A Web Host";
$software_section = "hosting-edit";

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$whid = $_GET['whid'];
$new_host = $_REQUEST['new_host'];
$new_url = $_POST['new_url'];
$new_notes = $_REQUEST['new_notes'];
$new_whid = $_REQUEST['new_whid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($new_host != "" && $new_url != "") {

        $query = "UPDATE hosting
                  SET `name` = ?,
                      url = ?,
                      notes = ?,
                      update_time = ?
                  WHERE id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $timestamp = $time->time();

            $q->bind_param('ssssi', $new_host, $new_url, $new_notes, $timestamp, $new_whid);
            $q->execute();
            $q->close();

        } else {
            $error->outputSqlError($conn, "ERROR");
        }

        $whid = $new_whid;

        $_SESSION['result_message'] = "Web Host <div class=\"highlight\">$new_host</div> Updated<BR>";

        header("Location: ../hosting.php");
        exit;

    } else {

        if ($new_host == "") $_SESSION['result_message'] .= "Please enter the web host's name<BR>";
        if ($new_url == "") $_SESSION['result_message'] .= "Please enter the web host's URL<BR>";

    }

} else {

    $query = "SELECT `name`, url, notes
              FROM hosting
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $whid);
        $q->execute();
        $q->store_result();
        $q->bind_result($new_host, $new_url, $new_notes);
        $q->fetch();
        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

}

if ($del == "1") {

    $query = "SELECT hosting_id
              FROM domains
              WHERE hosting_id = ?
              LIMIT 1";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $whid);
        $q->execute();
        $q->store_result();

        if ($q->num_rows() > 0) {

            $_SESSION['result_message'] = "This Web Host has domains associated with it and cannot be deleted<BR>";

        } else {

            $_SESSION['result_message'] = "Are you sure you want to delete this Web Host?<BR><BR><a
                href=\"host.php?whid=$whid&really_del=1\">YES, REALLY DELETE THIS WEB HOST</a><BR>";

        }

        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

}

if ($really_del == "1") {

    $query = "DELETE FROM hosting
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $whid);
        $q->execute();
        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

    $_SESSION['result_message'] = "Web Host <div class=\"highlight\">$new_host</div> Deleted<BR>";

    header("Location: ../hosting.php");
    exit;

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
<form name="edit_host_form" method="post">
    <strong>Web Host Name (100)</strong>
    <a title="Required Field">
        <div class="default_highlight"><strong>*</strong></div>
    </a><BR><BR>
    <input name="new_host" type="text" value="<?php if ($new_host != "") echo htmlentities($new_host); ?>
" size="50" maxlength="100">
    <BR><BR>
    <strong>Registrar's URL (100)</strong>
    <a title="Required Field">
        <div class="default_highlight"><strong>*</strong></div>
    </a><BR><BR>
    <input name="new_url" type="text" value="<?php echo htmlentities($new_url); ?>" size="50" maxlength="100">
    <BR><BR>
    <strong>Notes</strong><BR><BR>
    <textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?></textarea>
    <input type="hidden" name="new_whid" value="<?php echo $whid; ?>">
    <BR><BR>
    <input type="submit" name="button" value="Update This Web Host &raquo;">
</form>
<BR><BR><a href="host.php?whid=<?php echo $whid; ?>&del=1">DELETE THIS WEB HOST</a>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
