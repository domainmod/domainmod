<?php
/**
 * /assets/edit/ssl-provider-account.php
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

$page_title = "Editing An SSL Provider Account";
$software_section = "ssl-provider-accounts-edit";

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$sslpaid = $_GET['sslpaid'];
$new_owner_id = $_POST['new_owner_id'];
$new_ssl_provider_id = $_POST['new_ssl_provider_id'];
$new_username = $_POST['new_username'];
$new_password = $_POST['new_password'];
$new_reseller = $_POST['new_reseller'];
$new_notes = $_POST['new_notes'];
$new_sslpaid = $_POST['new_sslpaid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_username != "" && $new_owner_id != "" && $new_ssl_provider_id != "" && $new_owner_id != "0" &&
        $new_ssl_provider_id != "0") {

        $query = "UPDATE ssl_accounts
                  SET owner_id = ?,
                      ssl_provider_id = ?,
                      username = ?,
                      `password` = ?,
                      notes = ?,
                      reseller = ?,
                      update_time = ?
                      WHERE id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('iisssisi', $new_owner_id, $new_ssl_provider_id, $new_username, $new_password, $new_notes,
                $new_reseller, $time->time(), $new_sslpaid);
            $q->execute();
            $q->close();

        } else { $error->outputSqlError($conn, "ERROR"); }

        $sslpaid = $new_sslpaid;

        $query = "SELECT `name`
                  FROM ssl_providers
                  WHERE id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $q->bind_param('i', $new_ssl_provider_id);
            $q->execute();
            $q->store_result();
            $q->bind_result($temp_ssl_provider);
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

        $_SESSION['result_message'] = "SSL Account <font class=\"highlight\">$new_username ($temp_ssl_provider,
            $temp_owner)</font> Updated<BR>";

		header("Location: ../ssl-accounts.php");
		exit;

	} else {
	
		if ($username == "") { $_SESSION['result_message'] .= "Please enter a username<BR>"; }

	}

} else {

    $query = "SELECT owner_id, ssl_provider_id, username, `password`, notes, reseller
              FROM ssl_accounts
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $sslpaid);
        $q->execute();
        $q->store_result();
        $q->bind_result($new_owner_id, $new_ssl_provider_id, $new_username, $new_password, $new_notes, $new_reseller);
        $q->fetch();
        $q->close();

    } else { $error->outputSqlError($conn, "ERROR"); }

}

if ($del == "1") {

    $query = "SELECT account_id
              FROM ssl_certs
              WHERE account_id = ?
              LIMIT 1";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $sslpaid);
        $q->execute();
        $q->store_result();

        if ($q->num_rows() > 0) {

            $existing_ssl_certs = 1;

        }

        $q->close();

    } else { $error->outputSqlError($conn, "ERROR"); }

    if ($existing_ssl_certs > 0) {

		$_SESSION['result_message'] = "This SSL Account has SSL certificates associated with it and cannot be deleted<BR>";

	} else {

		$_SESSION['result_message'] = "Are you sure you want to delete this SSL Account?<BR><BR><a
            href=\"ssl-provider-account.php?sslpaid=$sslpaid&really_del=1\">YES, REALLY DELETE THIS SSL PROVIDER
            ACCOUNT</a><BR>";

	}

}

if ($really_del == "1") {

    $query = "SELECT a.username as username, o.name as owner_name, p.name as ssl_provider_name
              FROM ssl_accounts as a, owners as o, ssl_providers as p
              WHERE a.owner_id = o.id
                AND a.ssl_provider_id = p.id
                AND a.id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $sslpaid);
        $q->execute();
        $q->store_result();
        $q->bind_result($temp_username, $temp_owner_name, $temp_ssl_provider_name);
        $q->fetch();
        $q->close();

    } else { $error->outputSqlError($conn, "ERROR"); }

    $query = "DELETE FROM ssl_accounts
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $sslpaid);
        $q->execute();
        $q->close();

    } else { $error->outputSqlError($conn, "ERROR"); }

    $_SESSION['result_message'] = "SSL Account <font class=\"highlight\">$temp_username ($temp_ssl_provider_name,
        $temp_owner_name)</font> Deleted<BR>";

	include(DIR_INC . "auth/login-checks/domain-and-ssl-asset-check.inc.php");
	
	header("Location: ../ssl-accounts.php");
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
<form name="edit_ssl_account_form" method="post">
<strong>Owner</strong><BR><BR>
<?php
$query = "SELECT id, `name`
          FROM owners
          ORDER BY name ASC";
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

} else { $error->outputSqlError($conn, "ERROR"); }
?>
<BR><BR>
<strong>SSL Provider</strong><BR><BR>
<?php
$query = "SELECT id, `name`
          FROM ssl_providers
          ORDER BY name ASC";
$q = $conn->stmt_init();

if ($q->prepare($query)) {

    $q->execute();
    $q->store_result();
    $q->bind_result($id, $name);

    echo "<select name=\"new_ssl_provider_id\">";

    while ($q->fetch()) {

        if ($id == $new_ssl_provider_id) {

            echo "<option value=\"$id\" selected>$name</option>";

        } else {

            echo "<option value=\"$id\">$name</option>";

        }

    }

    echo "</select>";

    $q->close();

} else { $error->outputSqlError($conn, "ERROR"); }
?>
<BR><BR>
<strong>Username (100)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR>
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
<input type="hidden" name="new_sslpaid" value="<?php echo $sslpaid; ?>">
<BR><BR>
<input type="submit" name="button" value="Update This SSL Provider Account &raquo;">
</form>
<BR><BR><a href="ssl-provider-account.php?sslpaid=<?php echo $sslpaid; ?>&del=1">DELETE THIS SSL PROVIDER ACCOUNT</a>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
