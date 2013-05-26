<?php
// /system/admin/add/domain-field.php
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
include("../../../_includes/system/functions/check-custom-field-format.inc.php");

$page_title = "Adding A Custom Domain Field";
$software_section = "admin-domain-field-add";

$new_name = $_POST['new_name'];
$new_field_name = $_POST['new_field_name'];
$new_description = $_POST['new_description'];
$new_field_type_id = $_POST['new_field_type_id'];
$new_notes = $_POST['new_notes'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && $new_name != "" && $new_field_name != "" && CheckCustomFieldFormat($new_field_name)) {
	
	$sql = "SELECT field_name
			FROM domain_fields
			WHERE field_name = '" . $new_field_name . "'";
	$result = mysql_query($sql,$connection);

	if (mysql_num_rows($result) > 0) { $existing_field_name = 1; }
	
	if ($existing_field_name == 1) {

		$_SESSION['result_message'] .= "The Database Field Name you entered already exists<BR>";
		
	} else {

		$sql = "INSERT INTO domain_fields 
				(name, field_name, description, type_id, notes, insert_time) VALUES 
				('" . $new_name . "', '" . $new_field_name . "', '" . $new_description . "', '" . $new_field_type_id . "', '" . $new_notes . "', '" . $current_timestamp . "')";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		if ($new_field_type_id == '1') { // Check Box

			$sql = "ALTER TABLE `domain_field_data`  
					ADD `" . $new_field_name . "` INT(1) NOT NULL DEFAULT '0'";
			$result = mysql_query($sql,$connection) or die(mysql_error());
			
		} elseif ($new_field_type_id == '2') { // Text

			$sql = "ALTER TABLE `domain_field_data`  
					ADD `" . $new_field_name . "` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
			$result = mysql_query($sql,$connection) or die(mysql_error());
			
		} elseif ($new_field_type_id == '3') { // Text Area

			$sql = "ALTER TABLE `domain_field_data`  
					ADD `" . $new_field_name . "` longtext CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL";
			$result = mysql_query($sql,$connection) or die(mysql_error());
		
		}

		$_SESSION['result_message'] .= "Custom Domain Field <font class=\"highlight\">" . $new_name . " (" . $new_field_name . ")</font> Added<BR>";

		header("Location: ../domain-fields.php");
		exit;

	}

} else {

	if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	
		if ($new_name == "") $_SESSION['result_message'] .= "Enter the Display Name<BR>";
		if (!CheckCustomFieldFormat($new_field_name)) $_SESSION['result_message'] .= "The Database Field Name format is incorrect<BR>";
		
	}

}
?>
<?php include("../../../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../../../_includes/layout/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php include("../../../_includes/layout/header.inc.php"); ?>
<form name="add_domain_field_form" method="post" action="<?=$PHP_SELF?>">
<strong>Display Name (75)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR><input name="new_name" type="text" size="30" maxlength="75" value="<?=$new_name?>"><BR><BR>
<strong>Database Field Name (30)</strong><a title="Required Field"><font class="default_highlight">*</font></a><BR><BR>
The Database Field Name can contain only letters and underscores (ie. sample_field or Sample_Field).<BR><BR>
<font class="default_highlight">WARNING:</font> The Database Field Name cannot be renamed.<BR><BR><input name="new_field_name" type="text" size="20" maxlength="30" value="<?=$new_field_name?>"><BR><BR>
<strong>Data Type</strong><BR><BR>
<font class="default_highlight">WARNING:</font> The Data Type cannot be changed.<BR><BR>
<?php
$sql = "SELECT id, name
		FROM custom_field_types
		ORDER BY name";
$result = mysql_query($sql,$connection) or die(mysql_error());
echo "<select name=\"new_field_type_id\">";
while ($row = mysql_fetch_object($result)) { ?>

	<option value="<?=$row->id?>"><?=$row->name?></option><?php

}
echo "</select>";
?>
<BR><BR>
<strong>Description (255)</strong><BR><BR><input name="new_description" type="text" size="50" maxlength="255" value="<?=$new_description?>">
<BR><BR>
<strong>Notes</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=$new_notes?></textarea>
<BR><BR>
<input type="submit" name="button" value="Add Custom Field &raquo;">
</form>
<?php include("../../../_includes/layout/footer.inc.php"); ?>
</body>
</html>