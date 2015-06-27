<?php
/**
 * /assets/edit/ip-address.php
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

$page_title = "Editing An IP Address";
$software_section = "ip-addresses-edit";

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$ipid = $_GET['ipid'];
$new_name = $_POST['new_name'];
$new_ip = $_POST['new_ip'];
$new_rdns = $_POST['new_rdns'];
$new_ipid = $_POST['new_ipid'];
$new_notes = $_POST['new_notes'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($new_name != "" && $new_ip != "") {

        $query = "UPDATE ip_addresses
                  SET name = ?,
                      ip = ?,
                      rdns = ?,
                      notes = ?,
                      update_time = ?
                  WHERE id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $timestamp = $time->time();

            $q->bind_param('sssssi', $new_name, $new_ip, $new_rdns, $new_notes, $timestamp, $new_ipid);
            $q->execute();
            $q->close();

        } else $error->outputSqlError($conn, "ERROR");

        $ipid = $new_ipid;

        $_SESSION['result_message'] = "IP Address <font class=\"highlight\">$new_name ($new_ip)</font> Updated<BR>";

        header("Location: ../ip-addresses.php");
        exit;

    } else {

        if ($new_name == "") $_SESSION['result_message'] .= "Please enter a name for the IP Address<BR>";
        if ($new_ip == "") $_SESSION['result_message'] .= "Please enter the IP Address<BR>";

    }

} else {

    $query = "SELECT `name`, ip, rdns, notes
              FROM ip_addresses
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $ipid);
        $q->execute();
        $q->store_result();
        $q->bind_result($new_name, $new_ip, $new_rdns, $new_notes);
        $q->fetch();
        $q->close();

    } else $error->outputSqlError($conn, "ERROR");

}

if ($del == "1") {

    $query = "SELECT ip_id
              FROM domains
              WHERE ip_id = ?
              LIMIT 1";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $ipid);
        $q->execute();
        $q->store_result();

        if ($q->num_rows() > 0) {

            $_SESSION['result_message'] = "This IP Address has domains associated with it and cannot be deleted<BR>";

        } else {

            $_SESSION['result_message'] = "Are you sure you want to delete this IP Address?<BR><BR><a
                href=\"ip-address.php?ipid=$ipid&really_del=1\">YES, REALLY DELETE THIS IP ADDRESS</a><BR>";

        }

        $q->close();

    } else $error->outputSqlError($conn, "ERROR");

}

if ($really_del == "1") {

    $query = "DELETE FROM ip_addresses
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $ipid);
        $q->execute();
        $q->close();

    } else $error->outputSqlError($conn, "ERROR");

    $_SESSION['result_message'] = "IP Address <font class=\"highlight\">$new_name ($new_ip)</font> Deleted<BR>";

    header("Location: ../ip-addresses.php");
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
<form name="edit_ip_address_form" method="post">
<strong>IP Address Name (100)</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong>
        </font></a><BR><BR>
<input name="new_name" type="text" size="50" maxlength="100" value="<?php if ($new_name != "")
    echo htmlentities($new_name); ?>">
<BR><BR>
<strong>IP Address (100)</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong>
        </font></a><BR><BR>
<input name="new_ip" type="text" size="50" maxlength="100" value="<?php if ($new_ip != "") echo $new_ip; ?>">
<BR><BR>
<strong>rDNS (100)</strong><BR><BR>
<input name="new_rdns" type="text" size="50" maxlength="100" value="<?php if ($new_rdns != "") echo $new_rdns; ?>">
<BR><BR>
<strong>Notes</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?></textarea>
<input type="hidden" name="new_ipid" value="<?php echo $ipid; ?>">
<BR><BR>
<input type="submit" name="button" value="Update This IP Address &raquo;">
</form>
<BR><BR><a href="ip-address.php?ipid=<?php echo $ipid; ?>&del=1">DELETE THIS IP ADDRESS</a>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
