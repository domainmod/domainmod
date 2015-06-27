<?php
/**
 * /assets/edit/ssl-type.php
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

$page_title = "Editing An SSL Type";
$software_section = "ssl-types-edit";

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$ssltid = $_GET['ssltid'];

$new_type = $_REQUEST['new_type'];
$new_notes = $_REQUEST['new_notes'];
$new_ssltid = $_REQUEST['new_ssltid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($new_type != "") {

        $query = "UPDATE ssl_cert_types
                  SET type = ?,
                      notes = ?,
                      update_time = ?
                  WHERE id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $timestamp = $time->time();

            $q->bind_param('sssi', $new_type, $new_notes, $timestamp, $new_ssltid);
            $q->execute();
            $q->close();

        } else { $error->outputSqlError($conn, "ERROR"); }

        $ssltid = $new_ssltid;

        $_SESSION['result_message'] = "SSL Type <font class=\"highlight\">$new_type</font> Updated<BR>";

        header("Location: ../ssl-types.php");
        exit;

    } else {

        $_SESSION['result_message'] = "Please enter the Type name<BR>";

    }

} else {

    $query = "SELECT type, notes
              FROM ssl_cert_types
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $ssltid);
        $q->execute();
        $q->store_result();
        $q->bind_result($new_type, $new_notes);
        $q->fetch();
        $q->close();

    } else { $error->outputSqlError($conn, "ERROR"); }

}
if ($del == "1") {

    $query = "SELECT type_id
              FROM ssl_certs
              WHERE type_id = ?
              LIMIT 1";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $ssltid);
        $q->execute();
        $q->store_result();

        if ($q->num_rows() > 0) {

            $_SESSION['result_message'] = "This Type has SSL certificates associated with it and cannot be deleted<BR>";

        } else {

            $_SESSION['result_message'] = "Are you sure you want to delete this SSL Type?<BR><BR><a
                href=\"ssl-type.php?ssltid=$ssltid&really_del=1\">YES, REALLY DELETE THIS TYPE</a><BR>";

        }

        $q->close();

    } else { $error->outputSqlError($conn, "ERROR"); }

}

if ($really_del == "1") {

    $query = "DELETE FROM ssl_cert_types
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $ssltid);
        $q->execute();
        $q->close();

    } else { $error->outputSqlError($conn, "ERROR"); }

    $_SESSION['result_message'] = "SSL Type <font class=\"highlight\">$new_type</font> Deleted<BR>";

    header("Location: ../ssl-types.php");
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
<form name="edit_type_form" method="post">
<strong>Type Name (100)</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a>
    <BR><BR>
<input name="new_type" type="text" value="<?php if ($new_type != "") echo htmlentities($new_type); ?>
" size="50" maxlength="100">
<BR><BR>
<strong>Notes</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?></textarea>
<input type="hidden" name="new_ssltid" value="<?php echo $ssltid; ?>">
<BR><BR>
<input type="submit" name="button" value="Update This SSL Type &raquo;">
</form>
<BR><BR><a href="ssl-type.php?ssltid=<?php echo $ssltid; ?>&del=1">DELETE THIS TYPE</a>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
