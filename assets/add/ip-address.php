<?php
/**
 * /assets/add/ip-address.php
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

$page_title = "Adding A New IP Address";
$software_section = "ip-addresses-add";

// Form Variables
$new_name = $_POST['new_name'];
$new_ip = $_POST['new_ip'];
$new_rdns = $_POST['new_rdns'];
$new_notes = $_POST['new_notes'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($new_name != '' && $new_ip != '') {

        $query = "INSERT INTO ip_addresses
                  (`name`, ip, rdns, notes, insert_time)
                  VALUES
                  (?, ?, ?, ?, ?)";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $timestamp = $time->time();

            $q->bind_param('sssss', $new_name, $new_ip, $new_rdns, $new_notes, $timestamp);
            $q->execute();
            $q->close();

        } else {
            $error->outputSqlError($conn, "ERROR");
        }

        $_SESSION['result_message'] = "IP Address <div class=\"highlight\">" . $new_name . " (" . $new_ip . ")</div>
            Added<BR>";

        header("Location: ../ip-addresses.php");
        exit;

    } else {

        if ($new_name == '') {
            $_SESSION['result_message'] .= "Please enter a name for the IP address<BR>";
        }
        if ($new_ip == '') {
            $_SESSION['result_message'] .= "Please enter the IP address<BR>";
        }

    }

}
?>
<?php include(DIR_INC . 'doctype.inc.php'); ?>
<html>
<head>
    <title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
    <?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()" ;>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<form name="add_ip_address_form" method="post">
    <strong>IP Address Name (100)</strong>
    <a title="Required Field"><div class="default_highlight"><strong>*</strong></div></a><BR><BR>
    <input name="new_name" type="text" size="50" maxlength="100" value="<?php echo $new_name; ?>">
    <BR><BR>
    <strong>IP Address (100)</strong>
    <a title="Required Field"><div class="default_highlight"><strong>*</strong></div></a><BR><BR>
    <input name="new_ip" type="text" size="50" maxlength="100" value="<?php echo $new_ip; ?>">
    <BR><BR>
    <strong>rDNS (100)</strong><BR><BR>
    <input name="new_rdns" type="text" size="50" maxlength="100" value="<?php echo $new_rdns; ?>">
    <BR><BR>
    <strong>Notes</strong><BR><BR>
    <textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?></textarea>
    <BR><BR>
    <input type="submit" name="button" value="Add This IP Address &raquo;">
</form>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
