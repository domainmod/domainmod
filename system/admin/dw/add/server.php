<?php
/**
 * /system/admin/dw/add/server.php
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
include("../../../../_includes/start-session.inc.php");
include("../../../../_includes/init.inc.php");
include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "database.inc.php");
include(DIR_INC . "auth/auth-check.inc.php");
include(DIR_INC . "auth/admin-user-check.inc.php");
require_once(DIR_INC . "classes/Autoloader.class.php");

spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$error = new DomainMOD\Error();
$time = new DomainMOD\Timestamp();

$page_title = "Adding A Server";
$software_section = "admin-dw-manage-servers-add";

$new_name = $_POST['new_name'];
$new_host = $_POST['new_host'];
$new_protocol = $_POST['new_protocol'];
$new_port = $_POST['new_port'];
$new_username = $_POST['new_username'];
$new_hash = $_POST['new_hash'];
$new_notes = $_POST['new_notes'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_name != "" && $new_host != "" && $new_protocol != "" && $new_port != "" && $new_username != "" && $new_hash != "") {

		$sql = "INSERT INTO dw_servers
				(name, host, protocol, port, username, hash, notes, insert_time) VALUES 
				('" . mysqli_real_escape_string($connection, $new_name) . "', '" . mysqli_real_escape_string($connection, $new_host) . "', '" . mysqli_real_escape_string($connection, $new_protocol) . "', '" . mysqli_real_escape_string($connection, $new_port) . "', '" . mysqli_real_escape_string($connection, $new_username) . "', '" . mysqli_real_escape_string($connection, $new_hash) . "', '" . mysqli_real_escape_string($connection, $new_notes) . "', '" . $time->time() . "')";
		$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

		$_SESSION['result_message'] = "Server <font class=\"highlight\">" . $new_name . " (" . $new_host . ")</font> Added<BR>";

		header("Location: ../servers.php");
		exit;

	} else {

		if ($new_name == "") $_SESSION['result_message'] .= "Please enter a display name for the server<BR>";
		if ($new_host == "") $_SESSION['result_message'] .= "Please enter the hostname<BR>";
		if ($new_protocol == "") $_SESSION['result_message'] .= "Please enter the protocol<BR>";
		if ($new_port == "") $_SESSION['result_message'] .= "Please enter the port<BR>";
		if ($new_username == "") $_SESSION['result_message'] .= "Please enter the username<BR>";
		if ($new_hash == "") $_SESSION['result_message'] .= "Please enter the hash<BR>";

	}

}
?>
<?php include(DIR_INC . "doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<form name="dw_add_server_form" method="post">
<strong>Name (100):</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
Enter the display name for this server.<BR><BR>
<input name="new_name" type="text" size="50" maxlength="100" value="<?php echo $new_name; ?>">
<BR><BR>
<strong>Host Name (100):</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
Enter the host name of your WHM installation (ie. server1.example.com).<BR><BR>
<input name="new_host" type="text" size="50" maxlength="100" value="<?php echo $new_host; ?>">
<BR><BR>
<strong>Protocol (5):</strong><BR><BR>
Enter the protocol you connect with.<BR><BR>
<select name="new_protocol">
<option value="https"<?php if ($new_protocol == "https") echo " selected";?>>Secured (https)</option>
<option value="http"<?php if ($new_protocol == "http") echo " selected";?>>Unsecured (http)</option>
</select>
<BR><BR>
<strong>Port (5):</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
Enter the port that you connect to (usually 2086 or 2087).<BR><BR>
<input name="new_port" type="text" size="5" maxlength="5" value="<?php echo $new_port; ?>">
<BR><BR>
<strong>Username (100):</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
Enter the username for your WHM installation.<BR><BR>
<input name="new_username" type="text" size="50" maxlength="100" value="<?php echo $new_username; ?>">
<BR><BR>
<strong>Hash/Remote Access Key:</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
Enter the hash for you WHM installation. You can retrieve this from your WHM by logging in and searching for "Remote Access". Click on the "Setup Remote Access Key" option on the left, and your hash will be displayed on the right-hand side of the screen.<BR><BR>
<textarea name="new_hash" cols="60" rows="5"><?php echo $new_hash; ?></textarea>
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?></textarea>
<BR><BR>
<input type="submit" name="button" value="Add Server &raquo;">
</form>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
