<?php
// /edit/registrar.php
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
include("../_includes/start-session.inc.php");
include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/auth/auth-check.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");

$page_title = "Editting A Registrar";
$software_section = "registrars";

// 'Delete Registrar' Confirmation Variables
$del = $_GET['del'];
$really_del = $_GET['really_del'];

$rid = $_GET['rid'];

if ($_SESSION['http_referer_set'] != "1") {
	$_SESSION['http_referer'] = $_SERVER['HTTP_REFERER'];
	$_SESSION['http_referer_set'] = "1";
}

// Form Variables
$new_registrar = $_POST['new_registrar'];
$new_url = $_POST['new_url'];
$new_notes = $_POST['new_notes'];
$new_rid = $_POST['new_rid'];
$new_default_registrar = $_POST['new_default_registrar'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_registrar != "" && $new_url != "") {

		if ($new_default_registrar == "1") {

			$sql = "UPDATE registrars
					SET default_registrar = '0',
					    update_time = '$current_timestamp'";
			$result = mysql_query($sql,$connection) or die(mysql_error());
			
		} else { 
		
			$sql = "SELECT default_registrar
					FROM registrars
					WHERE default_registrar = '1'
					  AND id != '$new_rid'";
			$result = mysql_query($sql,$connection) or die(mysql_error());
			while ($row = mysql_fetch_object($result)) { $temp_default_registrar = $row->default_registrar; }
			if ($temp_default_registrar == "") { $new_default_registrar = "1"; }
		
		}

		$sql = "UPDATE registrars
				SET name = '" . mysql_real_escape_string($new_registrar) . "', 
					url = '" . mysql_real_escape_string($new_url) . "', 
					notes = '" . mysql_real_escape_string($new_notes) . "',
					default_registrar = '$new_default_registrar',
					update_time = '$current_timestamp'
				WHERE id = '$new_rid'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$rid = $new_rid;

		$_SESSION['result_message'] = "Registrar <font class=\"highlight\">$new_registrar</font> Updated<BR>";

		$_SESSION['http_referer_set'] = "";
		header("Location: " . $_SESSION['http_referer']);
		exit;
		
	} else {

		if ($new_registrar == "") $_SESSION['result_message'] .= "Please enter the registrar name<BR>";
		if ($new_url == "") $_SESSION['result_message'] .= "Please enter the registrar's URL<BR>";

	}

} else {

	$sql = "SELECT name, url, notes, default_registrar
			FROM registrars
			WHERE id = '$rid'";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	while ($row = mysql_fetch_object($result)) { 
	
		$new_registrar = $row->name;
		$new_url = $row->url;
		$new_notes = $row->notes;
		$new_default_registrar = $row->default_registrar;
	
	}

}
if ($del == "1") {

	$sql = "SELECT registrar_id
			FROM registrar_accounts
			WHERE registrar_id = '$rid'";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	while ($row = mysql_fetch_object($result)) {
		$existing_registrar_accounts = 1;
	}

	$sql = "SELECT registrar_id
			FROM domains
			WHERE registrar_id = '$rid'";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	while ($row = mysql_fetch_object($result)) {
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
			WHERE registrar_id = '$rid'";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "DELETE FROM registrar_accounts
			WHERE registrar_id = '$rid'";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$sql = "DELETE FROM registrars 
			WHERE id = '$rid'";
	$result = mysql_query($sql,$connection) or die(mysql_error());

	$_SESSION['result_message'] = "Registrar <font class=\"highlight\">$new_registrar</font> Deleted<BR>";

	include("../_includes/auth/login-checks/domain-and-ssl-asset-check.inc.php");
	
	header("Location: ../registrars.php");
	exit;

}
?>
<?php include("../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../_includes/header.inc.php"); ?>
<form name="edit_registrar_form" method="post" action="<?=$PHP_SELF?>">
<strong>Registrar Name</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
<input name="new_registrar" type="text" value="<?=$new_registrar?>" size="50" maxlength="255">
<BR><BR>
<strong>Registrar's URL</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
<input name="new_url" type="text" value="<?=$new_url?>" size="50" maxlength="255">
<BR><BR>
<strong>Notes</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=$new_notes?></textarea>
<BR><BR>
<strong>Default Registrar?</strong>&nbsp;
<input name="new_default_registrar" type="checkbox" value="1"<?php if ($new_default_registrar == "1") echo " checked"; ?>>
<BR><BR><BR>
<input type="hidden" name="new_rid" value="<?=$rid?>">
<input type="submit" name="button" value="Update This Registrar &raquo;">
</form>
<BR><BR><a href="registrar-fees.php?rid=<?=$rid?>">EDIT THIS REGISTRAR'S FEES</a><BR>
<BR><a href="<?=$PHP_SELF?>?rid=<?=$rid?>&del=1">DELETE THIS REGISTRAR</a>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>