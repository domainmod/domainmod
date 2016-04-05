<?php
/**
 * /admin/dw/edit-server.php
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
include(DIR_INC . "settings/dw-edit-server.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'], $web_root);

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$dwsid = $_GET['dwsid'];
$new_name = $_POST['new_name'];
$new_host = $_POST['new_host'];
$new_protocol = $_POST['new_protocol'];
$new_port = $_POST['new_port'];
$new_username = $_POST['new_username'];
$new_hash = $_POST['new_hash'];
$new_notes = $_POST['new_notes'];
$new_dwsid = $_POST['new_dwsid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($new_name != "" && $new_host != "" && $new_protocol != "" && $new_port != "" && $new_username != "" &&
        $new_hash != ""
    ) {

        $query = "UPDATE dw_servers
                  SET `name` = ?,
                      `host` = ?,
                      protocol = ?,
                      `port` = ?,
                      username = ?,
                      `hash` = ?,
                      notes = ?,
                      update_time = ?
                  WHERE id = ?";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $timestamp = $time->stamp();

            $q->bind_param('sssissssi', $new_name, $new_host, $new_protocol, $new_port, $new_username, $new_hash,
                $new_notes, $timestamp, $new_dwsid);
            $q->execute();
            $q->close();

        } else {
            $error->outputSqlError($conn, "ERROR");
        }

        $dwsid = $new_dwsid;

        $_SESSION['s_message_success'] .= "Server " . $new_name . " (" . $new_host . ") Updated<BR>";

        header("Location: servers.php");
        exit;

    } else {

        if ($new_name == "") {
            $_SESSION['s_message_danger'] .= "Enter a display name for the server<BR>";
        }
        if ($new_host == "") {
            $_SESSION['s_message_danger'] .= "Enter the hostname<BR>";
        }
        if ($new_protocol == "") {
            $_SESSION['s_message_danger'] .= "Enter the protocol<BR>";
        }
        if ($new_port == "") {
            $_SESSION['s_message_danger'] .= "Enter the port<BR>";
        }
        if ($new_username == "") {
            $_SESSION['s_message_danger'] .= "Enter the username<BR>";
        }
        if ($new_hash == "") {
            $_SESSION['s_message_danger'] .= "Enter the hash<BR>";
        }

    }

} else {

    $query = "SELECT `name`, `host`, protocol, `port`, username, `hash`, notes
              FROM dw_servers
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $dwsid);
        $q->execute();
        $q->store_result();
        $q->bind_result($new_name, $new_host, $new_protocol, $new_port, $new_username, $new_hash, $new_notes);
        $q->fetch();
        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

}
if ($del == "1") {

    $_SESSION['s_message_danger'] .= "Are you sure you want to delete this Server?<BR><BR><a
    href=\"edit-server.php?dwsid=$dwsid&really_del=1\">YES, REALLY DELETE THIS SERVER</a><BR>";

}

if ($really_del == "1") {

    $query = "SELECT `name`, `host`
              FROM dw_servers
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $dwsid);
        $q->execute();
        $q->store_result();
        $q->bind_result($new_name, $new_host);
        $q->fetch();
        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

    $query = "DELETE FROM dw_accounts
              WHERE server_id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $dwsid);
        $q->execute();
        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

    $query = "DELETE FROM dw_dns_records
              WHERE server_id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $dwsid);
        $q->execute();
        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

    $query = "DELETE FROM dw_dns_zones
              WHERE server_id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $dwsid);
        $q->execute();
        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

    $query = "DELETE FROM dw_servers
              WHERE id = ?";
    $q = $conn->stmt_init();

    if ($q->prepare($query)) {

        $q->bind_param('i', $dwsid);
        $q->execute();
        $q->close();

    } else {
        $error->outputSqlError($conn, "ERROR");
    }

    $dwstats = new DomainMOD\DwStats();
    $dwstats->updateDwTotalsTable($connection);

    $_SESSION['s_message_success'] .= "Server " . $new_name . " (" . $new_host . ") Deleted<BR>";

    header("Location: servers.php");
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
echo $form->showInputText('new_name', 'Name (100)', 'Enter the display name for this server', $new_name, '100', '', '1', '', '');
echo $form->showInputText('new_host', 'Host Name (100)', 'Enter the host name of your WHM installation (ie. server1.example.com).', $new_host, '100', '', '1', '', '');
echo $form->showDropdownTop('new_protocol', 'Protocol (5)', 'Enter the protocol you connect with.', '1', '');
echo $form->showDropdownOption('https', 'Secured (https)', $new_protocol);
echo $form->showDropdownOption('http', 'Unsecured (http)', $new_protocol);
echo $form->showDropdownBottom('');
echo $form->showInputText('new_port', 'Port (5)', 'Enter the port that you connect to (usually 2086 or 2087).', $new_port, '5', '', '1', '', '');
echo $form->showInputText('new_username', 'Username (100)', 'Enter the username for your WHM installation.', $new_username, '100', '', '1', '', '');
echo $form->showInputTextarea('new_hash', 'Hash/Remote Access Key', 'Enter the hash for you WHM installation. You can retrieve this from your WHM by logging in and searching for "Remote Access". Click on the "Setup Remote Access Key" option on the left, and your hash will be displayed on the right-hand side of the screen.', $new_hash, '1', '', '');
echo $form->showInputTextarea('new_notes', 'Notes', '', $new_notes, '', '', '');
echo $form->showInputHidden('new_dwsid', $dwsid);
echo $form->showSubmitButton('Save', '', '');
echo $form->showFormBottom('');
?>
<BR><a href="edit-server.php?dwsid=<?php echo urlencode($dwsid); ?>&del=1">DELETE THIS SERVER</a>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
