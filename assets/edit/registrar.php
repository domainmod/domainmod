<?php
// /assets/edit/registrar.php
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
include("../../_includes/start-session.inc.php");
include("../../_includes/config.inc.php");
include("../../_includes/database.inc.php");
include("../../_includes/software.inc.php");
include("../../_includes/auth/auth-check.inc.php");
include("../../_includes/timestamps/current-timestamp.inc.php");

$page_title = "Editing A Registrar";
$software_section = "registrars-edit";

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$rid = $_GET['rid'];
$new_registrar = $_POST['new_registrar'];
$new_url = $_POST['new_url'];
$new_notes = $_POST['new_notes'];
$new_rid = $_POST['new_rid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_registrar != "" && $new_url != "") {

		$sql = "UPDATE registrars
				SET name = '" . mysqli_real_escape_string($new_registrar) . "', 
					url = '" . mysqli_real_escape_string($new_url) . "', 
					notes = '" . mysqli_real_escape_string($new_notes) . "',
					update_time = '" . $current_timestamp . "'
				WHERE id = '" . $new_rid . "'";
		$result = mysqli_query($connection, $sql) or die(mysqli_error());
		
		$rid = $new_rid;

		$_SESSION['result_message'] = "Registrar <font class=\"highlight\">$new_registrar</font> Updated<BR>";

		header("Location: ../registrars.php");
		exit;
		
	} else {

		if ($new_registrar == "") $_SESSION['result_message'] .= "Please enter the registrar name<BR>";
		if ($new_url == "") $_SESSION['result_message'] .= "Please enter the registrar's URL<BR>";

	}

} else {

	$sql = "SELECT name, url, notes
			FROM registrars
			WHERE id = '" . $rid . "'";
	$result = mysqli_query($connection, $sql) or die(mysqli_error());
	
	while ($row = mysqli_fetch_object($result)) { 
	
		$new_registrar = $row->name;
		$new_url = $row->url;
		$new_notes = $row->notes;
	
	}

}
if ($del == "1") {

	$sql = "SELECT registrar_id
			FROM registrar_accounts
			WHERE registrar_id = '" . $rid . "'";
	$result = mysqli_query($connection, $sql) or die(mysqli_error());
	
	while ($row = mysqli_fetch_object($result)) {
		$existing_registrar_accounts = 1;
	}

	$sql = "SELECT registrar_id
			FROM domains
			WHERE registrar_id = '" . $rid . "'";
	$result = mysqli_query($connection, $sql) or die(mysqli_error());
	
	while ($row = mysqli_fetch_object($result)) {
		$existing_domains = 1;
	}

	if ($existing_registrar_accounts > 0 || $existing_domains > 0) {
		
		if ($existing_registrar_accounts > 0) $_SESSION['result_message'] .= "This Registrar has Registrar Accounts associated with it and cannot be deleted<BR>";
		if ($existing_domains > 0) $_SESSION['result_message'] .= "This Registrar has domains associated with it and cannot be deleted<BR>";

	} else {

		$_SESSION['result_message'] = "Are you sure you want to delete this Registrar?<BR><BR><a href=\"$PHP_SELF?rid=$rid&really_del=1\">YES, REALLY DELETE THIS REGISTRAR</a><BR>";

	}

}

if ($really_del == "1") {

	$sql = "DELETE FROM fees
			WHERE registrar_id = '" . $rid . "'";
	$result = mysqli_query($connection, $sql) or die(mysqli_error());

	$sql = "DELETE FROM registrar_accounts
			WHERE registrar_id = '" . $rid . "'";
	$result = mysqli_query($connection, $sql) or die(mysqli_error());

	$sql = "DELETE FROM registrars 
			WHERE id = '" . $rid . "'";
	$result = mysqli_query($connection, $sql) or die(mysqli_error());

	$_SESSION['result_message'] = "Registrar <font class=\"highlight\">$new_registrar</font> Deleted<BR>";

	include("../../_includes/auth/login-checks/domain-and-ssl-asset-check.inc.php");
	
	header("Location: ../registrars.php");
	exit;

}
?>
<?php include("../../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title; ?> :: <?php echo $page_title; ?></title>
<?php include("../../_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../../_includes/layout/header.inc.php"); ?>
<form name="edit_registrar_form" method="post" action="<?php echo $PHP_SELF; ?>">
<strong>Registrar Name (100)</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
<input name="new_registrar" type="text" value="<?php echo htmlentities($new_registrar); ?>" size="50" maxlength="100">
<BR><BR>
<strong>Registrar's URL (100)</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
<input name="new_url" type="text" value="<?php echo htmlentities($new_url); ?>" size="50" maxlength="100">
<BR><BR>
<strong>Notes</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?php echo $new_notes; ?></textarea>
<input type="hidden" name="new_rid" value="<?php echo $rid; ?>">
<BR><BR>
<input type="submit" name="button" value="Update This Registrar &raquo;">
</form>
<BR><BR><a href="registrar-fees.php?rid=<?php echo $rid; ?>">EDIT THIS REGISTRAR'S FEES</a><BR>
<BR><a href="<?php echo $PHP_SELF; ?>?rid=<?php echo $rid; ?>&del=1">DELETE THIS REGISTRAR</a>
<?php include("../../_includes/layout/footer.inc.php"); ?>
</body>
</html>
