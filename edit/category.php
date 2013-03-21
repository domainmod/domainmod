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
$software_section = "categories";

$pcid = $_GET['pcid'];

// Form Variables
$new_category = mysql_real_escape_string($_REQUEST['new_category']);
$new_owner = mysql_real_escape_string($_REQUEST['new_owner']);
$new_notes = mysql_real_escape_string($_REQUEST['new_notes']);
$new_default_category = $_REQUEST['new_default_category'];
$new_pcid = $_REQUEST['new_pcid'];

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

		$sql = "update categories
				set name = '$new_category', 
				owner = '$new_owner',
				notes = '$new_notes',
				default_category = '$new_default_category',
				update_time = '$current_timestamp'
				where id = '$new_pcid'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$new_category = stripslashes($new_category);
		$new_owner = stripslashes($new_owner);
		$new_default_category = stripslashes($new_default_category);
		$new_notes = stripslashes($new_notes);

		$pcid = $new_pcid;
		
		$_SESSION['session_result_message'] = "Category Updated<BR>";

	} else {
	
		$_SESSION['session_result_message'] = "Please Enter The Category Name<BR>";

	}

} else {

	$sql = "select name, owner, notes, default_category
			from categories
			where id = '$pcid'";
	$result = mysql_query($sql,$connection);
	
	while ($row = mysql_fetch_object($result)) { 
	
		$new_category = $row->name;
		$new_owner = $row->owner;
		$new_default_category = $row->default_category;
		$new_notes = $row->notes;
	}

}
$page_title = "Editting A Category";
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../_includes/header.inc.php"); ?>
<form name="form1" method="post" action="<?=$PHP_SELF?>">
<strong>Category Name:</strong><BR><BR>
<input name="new_category" type="text" value="<?php if ($new_category != "") echo stripslashes($new_category); ?>
" size="50" maxlength="255">
<BR><BR>
<strong>Owner/Stakeholder:</strong><BR><BR>
<input name="new_owner" type="text" value="<?php if ($new_owner != "") echo stripslashes($new_owner); ?>
" size="50" maxlength="255">
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=stripslashes($new_notes)?></textarea>
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