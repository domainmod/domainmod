<?php
/**
 * /assets/add/registrar-account.php
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
$time = new DomainMOD\Timestamp();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "database.inc.php");
include(DIR_INC . "auth/auth-check.inc.php");

$page_title = "Adding A New Registrar Account";
$software_section = "registrar-accounts-add";

// Form Variables
$new_owner_id = $_POST['new_owner_id'];
$new_registrar_id = $_POST['new_registrar_id'];
$new_username = $_POST['new_username'];
$new_password = $_POST['new_password'];
$new_reseller = $_POST['new_reseller'];
$new_notes = $_POST['new_notes'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_username != "" && $new_owner_id != "" && $new_registrar_id != "" && $new_owner_id != "0" &&
        $new_registrar_id != "0") {

        $query = "INSERT INTO registrar_accounts
                  (owner_id, registrar_id, username, `password`, notes, reseller, insert_time)
                  VALUES
                  (?, ?, ?, ?, ?, ?, ?)";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $timestamp = $time->time();

            $q->bind_param('iisssis', $new_owner_id, $new_registrar_id, $new_username, $new_password, $new_notes,
                $new_reseller, $timestamp);
            $q->execute();
            $q->close();

        } else { $error->outputSqlError($conn, "ERROR"); }

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

        } else { $error->outputSqlError($conn, "ERROR"); }

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

        } else { $error->outputSqlError($conn, "ERROR"); }

        $_SESSION['result_message'] = "Registrar Account <font class=\"highlight\">" . $new_username . " (" .
            $temp_registrar . ", " . $temp_owner . ")</font> Added<BR>";

		if ($_SESSION['need_registrar_account'] == "1") {
			
			$system->checkExistingAssets($connection);

            header("Location: ../../domains.php");

		} else {

			header("Location: ../registrar-accounts.php");
			
		}
		exit;

	} else {
	
		if ($username == "") { $_SESSION['result_message'] .= "Please enter a username<BR>"; }

	}

}
?>
<?php include(DIR_INC . "doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[2].focus()";>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<form name="add_account_form" method="post">
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

        if ($id == $_SESSION['default_owner_domains']) {

            echo "<option value=\"" . $id . "\" selected>" . $name . "</option>";

        } else {

            echo "<option value=\"" . $id . "\">" . $name . "</option>";

        }

    }

    echo "</select>";

    $q->close();

} else { $error->outputSqlError($conn, "ERROR"); }
?>
<BR><BR>
<strong>Registrar</strong><BR><BR>
<?php
$query = "SELECT id, name
          FROM registrars
          ORDER BY `name` ASC";
$q = $conn->stmt_init();

if ($q->prepare($query)) {

    $q->execute();
    $q->store_result();
    $q->bind_result($id, $name);

    echo "<select name=\"new_registrar_id\">";

    while ($q->fetch()) {

        if ($id == $_SESSION['default_registrar']) {

            echo "<option value=\"" . $id . "\" selected>" . $name . "</option>";

        } else {

            echo "<option value=\"" . $id . "\">" . $name . "</option>";

        }

    }

    echo "</select>";

    $q->close();

} else { $error->outputSqlError($conn, "ERROR"); }
?>
<BR><BR>
<strong>Username (100)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR>
<input name="new_username" type="text" size="50" maxlength="100" value="<?php echo $new_username; ?>">
<BR><BR>
<strong>Password (255)</strong><BR><BR>
<input name="new_password" type="text" size="50" maxlength="255" value="<?php echo $new_password; ?>">
<BR><BR>
<strong>Reseller Account?</strong><BR><BR>
<select name="new_reseller">";
<option value="0"<?php if ($new_reseller != "1") echo " selected"; ?>>No</option>
<option value="1"<?php if ($new_reseller == "1") echo " selected"; ?>>Yes</option>
</select>
<BR><BR>
<strong>Notes</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?>
</textarea>
<BR><BR>
<input type="submit" name="button" value="Add This Account &raquo;">
</form>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
