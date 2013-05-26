<?php
// /system/admin/edit/ssl-field.php
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
include("../../../_includes/start-session.inc.php");

// If the user isn't an administrator, redirect them to $full_redirect
$full_redirect = "index.php";
include("../../../_includes/auth/admin-user-check.inc.php");

include("../../../_includes/config.inc.php");
include("../../../_includes/database.inc.php");
include("../../../_includes/software.inc.php");
include("../../../_includes/timestamps/current-timestamp.inc.php");
include("../../../_includes/auth/auth-check.inc.php");

$page_title = "Editing A Custom SSL Field";
$software_section = "admin-ssl-field-edit";

$del = $_GET['del'];
$really_del = $_GET['really_del'];

$csfid = $_GET['csfid'];

$new_name = $_POST['new_name'];
$new_description = $_POST['new_description'];
$new_csfid = $_POST['new_csfid'];
$new_notes = $_POST['new_notes'];

if ($new_csfid == "") $new_csfid = $csfid;

$sql = "SELECT id
		FROM ssl_cert_fields
		WHERE id = '" . $csfid . "'";
$result = mysql_query($sql,$connection);

if (mysql_num_rows($result) == 0) {

		$_SESSION['result_message'] .= "You're trying to edit an invalid Custom SSL Field<BR>";
		
		header("Location: ../ssl-fields.php");
		exit;

}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_name != "") {

	$sql = "UPDATE ssl_cert_fields
			SET name = '" . $new_name . "',
				description = '" . $new_description . "',
				notes = '" . $new_notes . "',
				update_time = '" . $current_timestamp . "'
			WHERE id = '" . $new_csfid . "'";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	
	$sql = "SELECT field_name
			FROM ssl_cert_fields
			WHERE id = '" . $new_csfid . "'";
	$result = mysql_query($sql,$connection) or die(mysql_error());
	while ($row = mysql_fetch_object($result)) { $temp_field_name = $row->field_name; }
	
	$_SESSION['result_message'] .= "Custom SSL Field <font class=\"highlight\">" . $new_name . " (" . $temp_field_name . ")</font> Updated<BR>";
	
	header("Location: ../ssl-fields.php");
	exit;

} else {

	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
		if ($new_name == "") $_SESSION['result_message'] .= "Enter the display name<BR>";
		
	} else {

		$sql = "SELECT f.name, f.field_name, f.description, f.notes, f.insert_time, f.update_time, t.name AS type
				FROM ssl_cert_fields AS f, custom_field_types AS t
				WHERE f.type_id = t.id
				  AND f.id = '" . $csfid . "'
				ORDER BY f.name";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		while ($row = mysql_fetch_object($result)) {
			
			$new_name = $row->name;
			$new_field_name = $row->field_name;
			$new_description = $row->description;
			$new_field_type = $row->type;
			$new_notes = $row->notes;

		}

	}

}

if ($del == "1") {

	$_SESSION['result_message'] = "Are you sure you want to delete this Custom SSL Field?<BR><BR><a href=\"" . $PHP_SELF . "?csfid=" . $csfid . "&really_del=1\">YES, REALLY DELETE THIS CUSTOM SSL FIELD</a><BR>";

}

if ($really_del == "1") {

	if ($csfid == "") {

		$_SESSION['result_message'] = "The Custom SSL Field cannot be deleted<BR>";

	} else {
		
		$sql = "SELECT name, field_name
				FROM ssl_cert_fields
				WHERE id = '" . $csfid . "'";
		$result = mysql_query($sql,$connection);
		while ($row = mysql_fetch_object($result)) {
			$temp_name = $row->name;
			$temp_field_name = $row->field_name;
		}

		$sql = "ALTER TABLE `ssl_cert_field_data`
				DROP `" . $temp_field_name . "`";
		$result = mysql_query($sql,$connection);

		$sql = "DELETE FROM ssl_cert_fields
				WHERE id = '" . $csfid . "'";
		$result = mysql_query($sql,$connection);

		$_SESSION['result_message'] = "Custom SSL Field <font class=\"highlight\">" . $temp_name . " (" . $temp_field_name . ")</font> Deleted<BR>";
		
		header("Location: ../ssl-fields.php");
		exit;

	}

}
?>
<?php include("../../../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../../../_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../../../_includes/layout/header.inc.php"); ?>
<form name="edit_user_form" method="post" action="<?=$PHP_SELF?>">
<strong>Display Name (75)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR><input name="new_name" type="text" size="30" maxlength="75" value="<?php if ($new_name != "") echo $new_name; ?>"><BR><BR>
<strong>Database Field Name</strong><BR><BR><?=$new_field_name?><BR><BR>
<strong>Data Type</strong><BR><BR>
<?=$new_field_type?>
<BR><BR>
<strong>Description (255)</strong><BR><BR><input name="new_description" type="text" size="50" maxlength="255" value="<?php if ($new_description != "") echo $new_description; ?>">
<BR><BR>
<strong>Notes</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=$new_notes?></textarea>
<input type="hidden" name="new_csfid" value="<?=$csfid?>">
<BR><BR>
<input type="submit" name="button" value="Update Custom Field &raquo;">
</form>
<BR><BR><a href="<?=$PHP_SELF?>?csfid=<?=$csfid?>&del=1">DELETE THIS CUSTOM SSL FIELD</a>
<?php include("../../../_includes/layout/footer.inc.php"); ?>
</body>
</html>