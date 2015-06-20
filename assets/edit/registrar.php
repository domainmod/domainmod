<?php
/**
 * /assets/edit/registrar.php
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
include(DIR_INC . "head.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "database.inc.php");
include(DIR_INC . "auth/auth-check.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$error = new DomainMOD\Error();
$time = new DomainMOD\Timestamp();

$page_title = "Editing A Registrar";
$software_section = "registrars-edit";

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$rid = $_GET['rid'];
$new_registrar = $_POST['new_registrar'];
$new_url = $_POST['new_url'];
$new_notes = $_POST['new_notes'];
$new_rid = $_POST['new_rid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_registrar != "" && $new_url != "") {

        $query = "UPDATE registrars
                  SET `name` = ?,
                      url = ?,
                      notes = ?,
                      update_time = ?
                  WHERE id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $timestamp = $time->time();

            $q->bind_param('ssssi', $new_registrar, $new_url, $new_notes, $timestamp, $new_rid);
            $q->execute();
            $q->close();

        } else { $error->outputSqlError($conn, "ERROR"); }

        $rid = $new_rid;

		$_SESSION['result_message'] = "Registrar <font class=\"highlight\">$new_registrar</font> Updated<BR>";

		header("Location: ../registrars.php");
		exit;
		
	} else {

		if ($new_registrar == "") $_SESSION['result_message'] .= "Please enter the registrar name<BR>";
		if ($new_url == "") $_SESSION['result_message'] .= "Please enter the registrar's URL<BR>";

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

    } else { $error->outputSqlError($conn, "ERROR"); }

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

    } else { $error->outputSqlError($conn, "ERROR"); }

    $query = "SELECT registrar_id
              FROM domains
              WHERE registrar_id = '" . $rid . "'
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

	} else { $error->outputSqlError($conn, "ERROR"); }

    if ($existing_registrar_accounts > 0 || $existing_domains > 0) {
		
		if ($existing_registrar_accounts > 0) $_SESSION['result_message'] .= "This Registrar has Registrar Accounts
            associated with it and cannot be deleted<BR>";
		if ($existing_domains > 0) $_SESSION['result_message'] .= "This Registrar has domains associated with it and
            cannot be deleted<BR>";

	} else {

		$_SESSION['result_message'] = "Are you sure you want to delete this Registrar?<BR><BR><a
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

    } else { $error->outputSqlError($conn, "ERROR"); }

    $query = "DELETE FROM registrar_accounts
              WHERE registrar_id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $rid);
        $q->execute();
        $q->close();

    } else { $error->outputSqlError($conn, "ERROR"); }

    $query = "DELETE FROM registrars
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $rid);
        $q->execute();
        $q->close();

    } else { $error->outputSqlError($conn, "ERROR"); }

    $_SESSION['result_message'] = "Registrar <font class=\"highlight\">$new_registrar</font> Deleted<BR>";

	include(DIR_INC . "auth/login-checks/domain-and-ssl-asset-check.inc.php");
	
	header("Location: ../registrars.php");
	exit;

}
?>
<?php include(DIR_INC . "doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<form name="edit_registrar_form" method="post">
<strong>Registrar Name (100)</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong>
    </font></a><BR><BR>
<input name="new_registrar" type="text" value="<?php echo htmlentities($new_registrar); ?>" size="50" maxlength="100">
<BR><BR>
<strong>Registrar's URL (100)</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong>
    </font></a><BR><BR>
<input name="new_url" type="text" value="<?php echo htmlentities($new_url); ?>" size="50" maxlength="100">
<BR><BR>
<strong>Notes</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?></textarea>
<input type="hidden" name="new_rid" value="<?php echo $rid; ?>">
<BR><BR>
<input type="submit" name="button" value="Update This Registrar &raquo;">
</form>
<BR><BR><a href="registrar-fees.php?rid=<?php echo $rid; ?>">EDIT THIS REGISTRAR'S FEES</a><BR>
<BR><a href="registrar.php?rid=<?php echo $rid; ?>&del=1">DELETE THIS REGISTRAR</a>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
