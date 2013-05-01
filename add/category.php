<?php
// /add/category.php
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

$page_title = "Adding A New Category";
$software_section = "categories";

// Form Variables
$new_category = $_POST['new_category'];
$new_stakeholder = $_POST['new_stakeholder'];
$new_notes = $_POST['new_notes'];
$new_default_category = $_POST['new_default_category'];

if ($_SESSION['http_referer_set'] != "1") {
	$_SESSION['http_referer'] = $_SERVER['HTTP_REFERER'];
	$_SESSION['http_referer_set'] = "1";
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_category != "") {
		
		if ($new_default_category == "1") {
			
			$sql = "UPDATE categories
					SET default_category = '0',
						update_time = '$current_timestamp'";
			$result = mysql_query($sql,$connection);
			
		} else { 
		
			$sql = "SELECT count(*) as total_count
					FROM categories
					WHERE default_category = '1'";
			$result = mysql_query($sql,$connection);
			while ($row = mysql_fetch_object($result)) { $temp_total = $row->total_count; }
			if ($temp_total == "0") $new_default_category = "1";
		
		}

		$sql = "INSERT INTO categories 
				(name, stakeholder, notes, default_category, insert_time) VALUES 
				('" . mysql_real_escape_string($new_category) . "', '" . mysql_real_escape_string($new_stakeholder) . "', '" . mysql_real_escape_string($new_notes) . "', '$new_default_category', '$current_timestamp')";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$_SESSION['result_message'] = "Category <font class=\"highlight\">$new_category</font> Added<BR>";
		
		$_SESSION['http_referer_set'] = "";
		header("Location: " . $_SESSION['http_referer']);
		exit;

	} else {
	
		$_SESSION['result_message'] .= "Please enter the category name<BR>";

	}

}
?>
<?php include("../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php include("../_includes/header.inc.php"); ?>
<form name="add_category_form" method="post" action="<?=$PHP_SELF?>">
<strong>Category Name</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
<input name="new_category" type="text" value="<?=$new_category?>" size="50" maxlength="255">
<BR><BR>
<strong>Stakeholder</strong><BR><BR>
<input name="new_stakeholder" type="text" value="<?=$new_stakeholder?>" size="50" maxlength="255">
<BR><BR>
<strong>Notes</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=$new_notes?></textarea>
<BR><BR>
<strong>Default Category?</strong>&nbsp;
<input name="new_default_category" type="checkbox" id="new_default_category" value="1"<?php if ($new_default_category == "1") echo " checked";?>>
<BR><BR><BR>
<input type="submit" name="button" value="Add This Category &raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>