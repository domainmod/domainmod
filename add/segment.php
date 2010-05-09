<?php
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
$software_section = "segments";

// Form Variables
$new_name = $_POST['new_name'];
$new_description = $_POST['new_description'];
$new_segment = $_POST['new_segment'];
$new_notes = $_POST['new_notes'];
$IS_SUBMITTED = $_POST['IS_SUBMITTED'];

if ($IS_SUBMITTED == "1") {

	if ($new_name != "" && $new_segment != "") {

		$lines = explode("\r\n", $new_segment);
		$number_of_domains = count($lines);

		$new_segment_formatted = "'" . $new_segment;
		$new_segment_formatted = $new_segment_formatted . "'";
		$new_segment_formatted = preg_replace("/\r\n/", "','", $new_segment_formatted);
		$new_segment_formatted = str_replace (" ", "", $new_segment_formatted);
		$new_segment_formatted = trim($new_segment_formatted);
		$new_segment_formatted = addslashes($new_segment_formatted);

		$sql = "insert into segments
				(name, description, segment, number_of_domains, notes, insert_time)
				values ('$new_name', '$new_description', '$new_segment_formatted', '$number_of_domains', '$new_notes', '$current_timestamp')";
		$result = mysql_query($sql,$connection) or die(mysql_error());

		$_SESSION['session_result_message'] = "Segment Added<BR>";
		
		header("Location: ../segments.php");
		exit;

	} else {
	
		if ($new_name == "") { $_SESSION['session_result_message'] .= "Please Enter The Segment Name<BR>"; }
		if ($new_segment == "") { $_SESSION['session_result_message'] .= "Please Enter The Segment<BR>"; }

	}

}

$page_title = "Adding A New Segment";
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
<strong>Segment Name:</strong><BR><BR>
<input name="new_name" type="text" value="<?=stripslashes($new_name)?>" size="50" maxlength="255">
<BR><BR>
<strong>Description:</strong><BR><BR>
<textarea name="new_description" cols="60" rows="5"><?=stripslashes($new_description)?></textarea>
<BR><BR>
<strong>Segment:</strong><BR><BR>
Enter the domains one per line.<BR><BR>
<textarea name="new_segment" cols="60" rows="5"><?=stripslashes($new_segment)?></textarea>
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=stripslashes($new_notes)?></textarea>
<BR><BR><BR>
<input type="hidden" name="IS_SUBMITTED" value="1">
<input type="submit" name="button" value="Add This Segment &raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>