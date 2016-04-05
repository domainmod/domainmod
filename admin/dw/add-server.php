<?php
/**
 * /admin/dw/add-server.php
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
include(DIR_INC . "settings/dw-add-server.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'], $web_root);

$new_name = $_POST['new_name'];
$new_host = $_POST['new_host'];
$new_protocol = $_POST['new_protocol'];
$new_port = $_POST['new_port'];
$new_username = $_POST['new_username'];
$new_hash = $_POST['new_hash'];
$new_notes = $_POST['new_notes'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if ($new_name != "" && $new_host != "" && $new_protocol != "" && $new_port != "" && $new_username != "" &&
        $new_hash != ""
    ) {

        $query = "INSERT INTO dw_servers
                  (`name`, `host`, protocol, `port`, username, `hash`, notes, created_by, insert_time)
                  VALUES
                  (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $q = $conn->stmt_init();

        if ($q->prepare($query)) {

            $timestamp = $time->stamp();

            $q->bind_param('sssisssis', $new_name, $new_host, $new_protocol, $new_port, $new_username, $new_hash,
                $new_notes, $_SESSION['s_user_id'], $timestamp);
            $q->execute();
            $q->close();

        } else {
            $error->outputSqlError($conn, "ERROR");
        }

        $_SESSION['s_message_success'] .= "Server " . $new_name . " (" . $new_host . ") Added<BR>";

        header("Location: servers.php");
        exit;

    } else {

        if ($new_name == "") $_SESSION['s_message_danger'] .= "Enter a display name for the server<BR>";
        if ($new_host == "") $_SESSION['s_message_danger'] .= "Enter the hostname<BR>";
        if ($new_protocol == "") $_SESSION['s_message_danger'] .= "Enter the protocol<BR>";
        if ($new_port == "") $_SESSION['s_message_danger'] .= "Enter the port<BR>";
        if ($new_username == "") $_SESSION['s_message_danger'] .= "Enter the username<BR>";
        if ($new_hash == "") $_SESSION['s_message_danger'] .= "Enter the hash<BR>";

    }

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
echo $form->showSubmitButton('Add Server', '', '');
echo $form->showFormBottom('');
?>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
