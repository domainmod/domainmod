<?php
// /system/admin/dw/add/server.php
// 
// Domain Manager - A web-based application written in PHP & MySQL used to manage a collection of domain names.
// Copyright (C) 2010 Greg Chetcuti
// 
// Domain Manager is free software; you can redistribute it and/or modify it under the terms of the GNU General
// Public License as published by the Free Software Foundation; either version 2 of the License, or (at your
// option) any later version.
// 
// Domain Manager is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the
// implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License
// for more details.
// 
// You should have received a copy of the GNU General Public License along with Domain Manager. If not, please 
// see http://www.gnu.org/licenses/
?>
<?php
include("../../../../_includes/start-session.inc.php");

// If the user isn't an administrator, redirect them to $full_redirect
$full_redirect = "../../../../invalid.php";
include("../../../../_includes/auth/admin-user-check.inc.php");

include("../../../../_includes/config.inc.php");
include("../../../../_includes/database.inc.php");
include("../../../../_includes/software.inc.php");
include("../../../../_includes/auth/auth-check.inc.php");
include("../../../../_includes/timestamps/current-timestamp.inc.php");

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
				('" . mysql_real_escape_string($new_name) . "', '" . mysql_real_escape_string($new_host) . "', '" . mysql_real_escape_string($new_protocol) . "', '" . mysql_real_escape_string($new_port) . "', '" . mysql_real_escape_string($new_username) . "', '" . mysql_real_escape_string($new_hash) . "', '" . mysql_real_escape_string($new_notes) . "', '" . $current_timestamp . "')";
		$result = mysql_query($sql,$connection) or die(mysql_error());

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
<?php include("../../../../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../../../../_includes/layout/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php include("../../../../_includes/layout/header.inc.php"); ?>
<form name="dw_add_server_form" method="post" action="<?=$PHP_SELF?>">
<strong>Name (100):</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
Enter the display name for this server.<BR><BR>
<input name="new_name" type="text" size="50" maxlength="100" value="<?=$new_name?>">
<BR><BR>
<strong>Host Name (100):</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
Enter the host name of your WHM installation (ie. server1.yourdomain.com).<BR><BR>
<input name="new_host" type="text" size="50" maxlength="100" value="<?=$new_host?>">
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
<input name="new_port" type="text" size="5" maxlength="5" value="<?=$new_port?>">
<BR><BR>
<strong>Username (100):</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
Enter the username for your WHM installation.<BR><BR>
<input name="new_username" type="text" size="50" maxlength="100" value="<?=$new_username?>">
<BR><BR>
<strong>Hash/Remote Access Key:</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
Enter the hash for you WHM installation. You can retrieve this from your WHM by logging in and searching for "Remote Access". Click on the "Setup Remote Access Key" option on the left, and your hash will be displayed on the right-hand side of the screen.<BR><BR>
<textarea name="new_hash" cols="60" rows="5"><?=$new_hash?></textarea>
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=$new_notes?></textarea>
<BR><BR>
<input type="submit" name="button" value="Add Server &raquo;">
</form>
<?php include("../../../../_includes/layout/footer.inc.php"); ?>
</body>
</html>