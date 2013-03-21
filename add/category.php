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
include("../_includes/timestamps/current-timestamp-basic.inc.php");
$software_section = "categories";

// Form Variables
$new_category = mysql_real_escape_string($_POST['new_category']);
$new_owner = mysql_real_escape_string($_POST['new_owner']);
$new_notes = mysql_real_escape_string($_POST['new_notes']);
$new_default_category = $_POST['new_default_category'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_category != "") {
		
		if ($new_default_category == "1") {
			
			$sql = "update categories
					set default_category = '0',
					update_time = '$current_timestamp'";
			$result = mysql_query($sql,$connection);
			
		} else { 
		
			$sql = "select count(*) as total_count
					from categories
					where default_category = '1'";
			$result = mysql_query($sql,$connection);
			while ($row = mysql_fetch_object($result)) { $temp_total = $row->total_count; }
			if ($temp_total == "0") $new_default_category = "1";
		
		}

		$sql = "insert into categories
				(name, owner, notes, default_category, insert_time)
				values ('$new_category', '$new_owner', '$new_notes', '$new_default_category', '$current_timestamp')";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$_SESSION['session_result_message'] = "Category Added<BR>";
		
		header("Location: ../categories.php");
		exit;

	} else {
	
		$_SESSION['session_result_message'] .= "Please Enter The Category Name<BR>";

	}

}
$page_title = "Adding A New Category";
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php include("../_includes/header.inc.php"); ?>
<form name="form1" method="post" action="<?=$PHP_SELF?>">
<strong>Category Name:</strong><BR><BR>
<input name="new_category" type="text" value="<?=stripslashes($new_category)?>" size="50" maxlength="255">
<BR><BR>
<strong>Owner/Stakeholder:</strong><BR><BR>
<input name="new_owner" type="text" value="<?=stripslashes($new_owner)?>" size="50" maxlength="255">
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=stripslashes($new_notes)?></textarea>
<BR><BR>
<strong>Default Category?:</strong>&nbsp;
<input name="new_default_category" type="checkbox" id="new_default_category" value="1">
<BR><BR><BR>
<input type="submit" name="button" value="Add This Category &raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>