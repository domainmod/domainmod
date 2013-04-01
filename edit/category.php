<?php
// category.php
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
session_start();

include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/auth/auth-check.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");

$page_title = "Editting A Category";
$software_section = "categories";

$pcid = $_GET['pcid'];

// Form Variables
$new_category = $_REQUEST['new_category'];
$new_stakeholder = $_REQUEST['new_stakeholder'];
$new_notes = $_REQUEST['new_notes'];
$new_default_category = $_REQUEST['new_default_category'];
$new_pcid = $_REQUEST['new_pcid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_category != "") {

		if ($new_default_category == "1") {
			
			$sql = "UPDATE categories
					SET default_category = '0',
					    update_time = '$current_timestamp'";
			$result = mysql_query($sql,$connection);
			
		} else { 
		
			$sql = "SELECT count(*) AS total_count
					FROM categories
					WHERE default_category = '1'";
			$result = mysql_query($sql,$connection);
			while ($row = mysql_fetch_object($result)) { $temp_total = $row->total_count; }
			if ($temp_total == "0") $new_default_category = "1";
		
		}

		$sql = "UPDATE categories
				SET name = '" . mysql_real_escape_string($new_category) . "', 
					stakeholder = '" . mysql_real_escape_string($new_stakeholder) . "',
					notes = '" . mysql_real_escape_string($new_notes) . "',
					default_category = '$new_default_category',
					update_time = '$current_timestamp'
				WHERE id = '$new_pcid'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$new_category = $new_category;
		$new_stakeholder = $new_stakeholder;
		$new_default_category = $new_default_category;
		$new_notes = $new_notes;

		$pcid = $new_pcid;
		
		$_SESSION['session_result_message'] = "Category Updated<BR>";

	} else {
	
		$_SESSION['session_result_message'] = "Please Enter The Category Name<BR>";

	}

} else {

	$sql = "SELECT name, stakeholder, notes, default_category
			FROM categories
			WHERE id = '$pcid'";
	$result = mysql_query($sql,$connection);
	
	while ($row = mysql_fetch_object($result)) { 
	
		$new_category = $row->name;
		$new_stakeholder = $row->stakeholder;
		$new_default_category = $row->default_category;
		$new_notes = $row->notes;
	}

}
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../_includes/header.inc.php"); ?>
<form name="edit_category_form" method="post" action="<?=$PHP_SELF?>">
<strong>Category Name:</strong><BR><BR>
<input name="new_category" type="text" value="<?php if ($new_category != "") echo $new_category; ?>
" size="50" maxlength="255">
<BR><BR>
<strong>Stakeholder:</strong><BR><BR>
<input name="new_stakeholder" type="text" value="<?php if ($new_stakeholder != "") echo $new_stakeholder; ?>
" size="50" maxlength="255">
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=$new_notes?></textarea>
<BR><BR>
<strong>Default Category?:</strong>&nbsp;
<input name="new_default_category" type="checkbox" value="1"<?php if ($new_default_category == "1") echo " checked"; ?>>
<BR><BR><BR>
<input type="hidden" name="new_pcid" value="<?=$pcid?>">
<input type="submit" name="button" value="Update This Category &raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>