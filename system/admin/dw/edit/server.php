<?php
// /system/admin/dw/edit/server.php
// 
// DomainMOD is an open source application written in PHP & MySQL used to track and manage your web resources.
// Copyright (C) 2010 Greg Chetcuti
// 
// DomainMOD is free software; you can redistribute it and/or modify it under the terms of the GNU General
// Public License as published by the Free Software Foundation; either version 2 of the License, or (at your
// option) any later version.
// 
// DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the
// implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License
// for more details.
// 
// You should have received a copy of the GNU General Public License along with DomainMOD. If not, please see
// http://www.gnu.org/licenses/
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

$page_title = "Editing A Server";
$software_section = "admin-dw-manage-servers-edit";

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

	if ($new_name != "" && $new_host != "" && $new_protocol != "" && $new_port != "" && $new_username != "" && $new_hash != "") {

		$sql = "UPDATE dw_servers
				SET name = '" . mysqli_real_escape_string($new_name) . "', 
					host = '" . mysqli_real_escape_string($new_host) . "',
					protocol = '" . mysqli_real_escape_string($new_protocol) . "',
					port = '" . mysqli_real_escape_string($new_port) . "',
					username = '" . mysqli_real_escape_string($new_username) . "',
					hash = '" . mysqli_real_escape_string($new_hash) . "',
					notes = '" . mysqli_real_escape_string($new_notes) . "',
					update_time = '" . $current_timestamp . "'
				WHERE id = '" . $new_dwsid . "'";
		$result = mysqli_query($connection, $sql) or die(mysqli_error());

		$dwsid = $new_dwsid;
		
		$_SESSION['result_message'] = "Server <font class=\"highlight\">" . $new_name . " (" . $new_host . ")</font> Updated<BR>";

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

} else {

	$sql = "SELECT id, name, host, protocol, port, username, hash, notes
			FROM dw_servers
			WHERE id = '" . $dwsid . "'";
	$result = mysqli_query($connection, $sql) or die(mysqli_error());
	
	while ($row = mysqli_fetch_object($result)) { 

		$new_name = $row->name;
		$new_host = $row->host;
		$new_protocol = $row->protocol;
		$new_port = $row->port;
		$new_username = $row->username;
		$new_hash = $row->hash;
		$new_notes = $row->notes;

	}

}
if ($del == "1") {
	
	$_SESSION['result_message'] = "Are you sure you want to delete this Server?<BR><BR><a href=\"$PHP_SELF?dwsid=$dwsid&really_del=1\">YES, REALLY DELETE THIS SERVER</a><BR>";

}

if ($really_del == "1") {

	$sql = "SELECT name, host
			FROM dw_servers
			WHERE id = '" . $dwsid . "'";
	$result = mysqli_query($connection, $sql);
	
	while ($row = mysqli_fetch_object($result)) {
		
		$new_name = $row->name;
		$new_host = $row->host;

	}

	$sql = "DELETE FROM dw_accounts
			WHERE server_id = '" . $dwsid . "'";
	$result = mysqli_query($connection, $sql);

	$sql = "DELETE FROM dw_dns_records
			WHERE server_id = '" . $dwsid . "'";
	$result = mysqli_query($connection, $sql);

	$sql = "DELETE FROM dw_dns_zones
			WHERE server_id = '" . $dwsid . "'";
	$result = mysqli_query($connection, $sql);

	$sql = "DELETE FROM dw_servers
			WHERE id = '" . $dwsid . "'";
	$result = mysqli_query($connection, $sql);

	include("../../../../cron/_includes/dw-update-totals.inc.php");

	$_SESSION['result_message'] = "Server <font class=\"highlight\">" . $new_name . " (" . $new_host . ")</font> Deleted<BR>";
	
	header("Location: ../servers.php");
	exit;

}
?>
<?php include("../../../../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title; ?> :: <?php echo $page_title; ?></title>
<?php include("../../../../_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../../../../_includes/layout/header.inc.php"); ?>
<form name="dw_edit_server_form" method="post" action="<?php echo $PHP_SELF; ?>">
<strong>Name (100):</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
Enter the display name for this server.<BR><BR>
<input name="new_name" type="text" size="50" maxlength="100" value="<?php if ($new_name != "") echo htmlentities($new_name); ?>">
<BR><BR>
<strong>Host Name (100):</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
Enter the host name of your WHM installation (ie. server1.example.com).<BR><BR>
<input name="new_host" type="text" size="50" maxlength="100" value="<?php if ($new_host != "") echo $new_host; ?>">
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
<input name="new_port" type="text" size="5" maxlength="5" value="<?php if ($new_port != "") echo $new_port; ?>">
<BR><BR>
<strong>Username (100):</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
Enter the username for your WHM installation.<BR><BR>
<input name="new_username" type="text" size="50" maxlength="100" value="<?php if ($new_username != "") echo $new_username; ?>">
<BR><BR>
<strong>Hash/Remote Access Key:</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
Enter the hash for you WHM installation. You can retrieve this from your WHM by logging in and searching for "Remote Access". Click on the "Setup Remote Access Key" option on the left, and your hash will be displayed on the right-hand side of the screen.<BR><BR>
<textarea name="new_hash" cols="60" rows="5"><?php if ($new_hash != "") echo $new_hash; ?></textarea>
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?php if ($new_notes != "") echo $new_notes; ?></textarea>
<BR><BR>
<input type="hidden" name="new_dwsid" value="<?php echo $dwsid; ?>">
<input type="submit" name="button" value="Update Server &raquo;">
</form>
<BR><BR><a href="<?php echo $PHP_SELF; ?>?dwsid=<?php echo $dwsid; ?>&del=1">DELETE THIS SERVER</a>
<?php include("../../../../_includes/layout/footer.inc.php"); ?>
</body>
</html>
