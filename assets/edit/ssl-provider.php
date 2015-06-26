<?php
/**
 * /assets/edit/ssl-provider.php
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

$page_title = "Editing An SSL Provider";
$software_section = "ssl-providers-edit";

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$sslpid = $_GET['sslpid'];
$new_ssl_provider = $_POST['new_ssl_provider'];
$new_url = $_POST['new_url'];
$new_notes = $_POST['new_notes'];
$new_sslpid = $_POST['new_sslpid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_ssl_provider != "" && $new_url != "") {

        $query = "UPDATE ssl_providers
                  SET `name` = ?,
                      url = ?,
                      notes = ?,
                      update_time = ?
                  WHERE id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $timestamp = $time->time();

            $q->bind_param('ssssi', $new_ssl_provider, $new_url, $new_notes, $timestamp, $new_sslpid);
            $q->execute();
            $q->close();

        } else { $error->outputSqlError($conn, "ERROR"); }

        $sslpid = $new_sslpid;

		$_SESSION['result_message'] = "SSL Provider <font class=\"highlight\">$new_ssl_provider</font> Updated<BR>";

		header("Location: ../ssl-providers.php");
		exit;

	} else {

		if ($new_ssl_provider == "") $_SESSION['result_message'] .= "Please enter the SSL provider's name<BR>";
		if ($new_url == "") $_SESSION['result_message'] .= "Please enter the SSL provider's URL<BR>";

	}

} else {

    $query = "SELECT `name`, url, notes
              FROM ssl_providers
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $sslpid);
        $q->execute();
        $q->store_result();
        $q->bind_result($new_ssl_provider, $new_url, $new_notes);
        $q->fetch();
        $q->close();

    } else { $error->outputSqlError($conn, "ERROR"); }

}

if ($del == "1") {

    $query = "SELECT ssl_provider_id
              FROM ssl_accounts
              WHERE ssl_provider_id = ?
              LIMIT 1";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $sslpid);
        $q->execute();
        $q->store_result();

        if ($q->num_rows() > 0) {

            $existing_ssl_provider_accounts = 1;

        }

        $q->close();

    } else { $error->outputSqlError($conn, "ERROR"); }

    $query = "SELECT ssl_provider_id
              FROM ssl_certs
              WHERE ssl_provider_id = ?
              LIMIT 1";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $sslpid);
        $q->execute();
        $q->store_result();

        if ($q->num_rows() > 0) {

            $existing_ssl_certs = 1;

        }

        $q->close();

    } else { $error->outputSqlError($conn, "ERROR"); }

    if ($existing_ssl_provider_accounts > 0 || $existing_ssl_certs > 0) {
		
		if ($existing_ssl_provider_accounts > 0) $_SESSION['result_message'] .= "This SSL Provider has Accounts
            associated with it and cannot be deleted<BR>";
		if ($existing_ssl_certs > 0) $_SESSION['result_message'] .= "This SSL Provider has SSL Certificates associated
            with it and cannot be deleted<BR>";

	} else {

		$_SESSION['result_message'] = "Are you sure you want to delete this SSL Provider?<BR><BR><a
            href=\"ssl-provider.php?sslpid=$sslpid&really_del=1\">YES, REALLY DELETE THIS SSL PROVIDER</a><BR>";

	}

}

if ($really_del == "1") {

    $query = "DELETE FROM ssl_fees
              WHERE ssl_provider_id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $sslpid);
        $q->execute();
        $q->close();

    } else { $error->outputSqlError($conn, "ERROR"); }

    $query = "DELETE FROM ssl_accounts
              WHERE ssl_provider_id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $sslpid);
        $q->execute();
        $q->close();

    } else { $error->outputSqlError($conn, "ERROR"); }

    $query = "DELETE FROM ssl_providers
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $sslpid);
        $q->execute();
        $q->close();

    } else { $error->outputSqlError($conn, "ERROR"); }

    $_SESSION['result_message'] = "SSL Provider <font class=\"highlight\">$new_ssl_provider</font> Deleted<BR>";

	$system->checkExistingAssets($connection);

    header("Location: ../ssl-providers.php");
	exit;

}
?>
<?php echo $system->doctype(); ?>
<html>
<head>
<title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
<?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<form name="edit_ssl_provider_form" method="post">
<strong>SSL Provider Name (100)</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong>
        </font></a><BR><BR>
<input name="new_ssl_provider" type="text" value="<?php echo htmlentities($new_ssl_provider); ?>" size="50" maxlength="100">
<BR><BR>
<strong>SSL Provider's URL (100)</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong>
        </font></a><BR><BR>
<input name="new_url" type="text" value="<?php echo htmlentities($new_url); ?>" size="50" maxlength="100">
<BR><BR>
<strong>Notes</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?></textarea>
<input type="hidden" name="new_sslpid" value="<?php echo $sslpid; ?>">
<BR><BR>
<input type="submit" name="button" value="Update This SSL Provider &raquo;">
</form>
<BR><BR><a href="ssl-provider-fees.php?sslpid=<?php echo $sslpid; ?>">EDIT THIS SSL PROVIDER'S FEES</a><BR>
<BR><a href="ssl-provider.php?sslpid=<?php echo $sslpid; ?>&del=1">DELETE THIS SSL PROVIDER</a>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
