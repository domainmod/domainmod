<?php
/**
 * /assets/edit/registrar-account.php
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

$page_title = "Editing A Registrar Account";
$software_section = "registrar-accounts-edit";

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$raid = $_GET['raid'];
$new_owner_id = $_POST['new_owner_id'];
$new_registrar_id = $_POST['new_registrar_id'];
$new_username = $_POST['new_username'];
$new_password = $_POST['new_password'];
$new_reseller = $_POST['new_reseller'];
$new_notes = $_POST['new_notes'];
$new_raid = $_POST['new_raid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($new_username != "" && $new_owner_id != "" && $new_registrar_id != "" && $new_owner_id != "0" &&
        $new_registrar_id != "0"
    ) {

        $query = "UPDATE registrar_accounts
                  SET owner_id = ?,
                      registrar_id = ?,
                      username = ?,
                      `password` = ?,
                      notes = ?,
                      reseller = ?,
                      update_time = ?
                  WHERE id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $timestamp = $time->time();

            $q->bind_param('iisssisi', $new_owner_id, $new_registrar_id, $new_username, $new_password, $new_notes,
                $new_reseller, $timestamp, $new_raid);
            $q->execute();
            $q->close();

        } else {
            $error->outputSqlError($conn, "ERROR");
        }

        $query = "UPDATE domains
                  SET owner_id = ?
                  WHERE account_id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('ii', $new_owner_id, $new_raid);
            $q->execute();
            $q->close();

        } else {
            $error->outputSqlError($conn, "ERROR");
        }

        $raid = $new_raid;

        $query = "SELECT `name`
                  FROM registrars
                  WHERE id = '" . $new_registrar_id . "'";
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

        $_SESSION['result_message'] = "Registrar Account <div class=\"highlight\">$new_username ($temp_registrar,
            $temp_owner)</div> Updated<BR>";

        header("Location: ../registrar-accounts.php");
        exit;

    } else {

        if ($username == "") {
            $_SESSION['result_message'] .= "Please enter the username<BR>";
        }

    }

} else {

    $query = "SELECT owner_id, registrar_id, username, `password`, notes, reseller
              FROM registrar_accounts
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $raid);
        $q->execute();
        $q->store_result();
        $q->bind_result($new_owner_id, $new_registrar_id, $new_username, $new_password, $new_notes, $new_reseller);
        $q->fetch();
        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

}

if ($del == "1") {

    $query = "SELECT account_id
              FROM domains
              WHERE account_id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $raid);
        $q->execute();
        $q->store_result();

        if ($q->num_rows() > 0) {

            $existing_domains = 1;

        }

        if ($existing_domains > 0) {

            $_SESSION['result_message'] = "This Registrar Account has domains associated with it and cannot be deleted
                <BR>";

        } else {

            $_SESSION['result_message'] = "Are you sure you want to delete this Registrar Account?<BR><BR><a
                href=\"registrar-account.php?raid=$raid&really_del=1\">YES, REALLY DELETE THIS DOMAIN REGISTRAR
                ACCOUNT</a><BR>";

        }

        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

}

if ($really_del == "1") {

    $query = "SELECT ra.username AS username, o.name AS owner_name, r.name AS registrar_name
              FROM registrar_accounts AS ra, owners AS o, registrars AS r
              WHERE ra.owner_id = o.id
                AND ra.registrar_id = r.id
                AND ra.id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $raid);
        $q->execute();
        $q->store_result();
        $q->bind_result($temp_username, $temp_owner_name, $temp_registrar_name);
        $q->fetch();
        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

    $query = "DELETE FROM registrar_accounts
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $raid);
        $q->execute();
        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

    $_SESSION['result_message'] = "Registrar Account <div class=\"highlight\">$temp_username ($temp_registrar_name,
        $temp_owner_name)</div> Deleted<BR>";

    $system->checkExistingAssets($connection);

    header("Location: ../registrar-accounts.php");
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
<form name="edit_account_form" method="post">
    <strong>Owner</strong><BR><BR>
    <?php
    $query = "SELECT id, `name`
          FROM owners
          ORDER BY `name` ASC";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->execute();
        $q->store_result();
        $q->bind_result($id, $name);

        echo "<select name=\"new_owner_id\">";

        while ($q->fetch()) {

            if ($id == $new_owner_id) {

                echo "<option value=\"$id\" selected>$name</option>";

            } else {

                echo "<option value=\"$id\">$name</option>";

            }

        }

        echo "</select>";

        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }
    ?>
    <BR><BR>
    <strong>Registrar</strong><BR><BR>
    <?php
    $query = "SELECT id, `name`
          FROM registrars
          ORDER BY `name` ASC";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->execute();
        $q->store_result();
        $q->bind_result($id, $name);

        echo "<select name=\"new_registrar_id\">";

        while ($q->fetch()) {

            if ($id == $new_registrar_id) {

                echo "<option value=\"$id\" selected>$name</option>";

            } else {

                echo "<option value=\"$id\">$name</option>";

            }
        }
        echo "</select>";

        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }
    ?>
    <BR><BR>
    <strong>Username (100)</strong><a title="Required Field">
        <div class="default_highlight">*</div>
    </a><BR><BR>
    <input name="new_username" type="text" size="50" maxlength="100" value="<?php echo htmlentities($new_username); ?>">
    <BR><BR>
    <strong>Password (255)</strong><BR><BR>
    <input name="new_password" type="text" size="50" maxlength="255" value="<?php echo htmlentities($new_password); ?>">
    <BR><BR>
    <strong>Reseller Account?</strong><BR><BR>
    <select name="new_reseller">";
        <option value="0"<?php if ($new_reseller == "0") echo " selected"; ?>>No</option>
        <option value="1"<?php if ($new_reseller == "1") echo " selected"; ?>>Yes</option>
    </select>
    <BR><BR>
    <strong>Notes</strong><BR><BR>
    <textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?></textarea>
    <BR><BR>
    <input type="hidden" name="new_raid" value="<?php echo $raid; ?>">
    <input type="submit" name="button" value="Update This Registrar Account &raquo;">
</form>
<BR><BR><a href="registrar-account.php?raid=<?php echo $raid; ?>&del=1">DELETE THIS REGISTRAR ACCOUNT</a>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
