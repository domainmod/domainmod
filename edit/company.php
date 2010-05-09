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
$software_section = "companies";

$cid = $_GET['cid'];

// Form Variables
$new_company = $_POST['new_company'];
$new_notes = $_POST['new_notes'];
$new_cid = $_POST['new_cid'];
$IS_SUBMITTED = $_POST['IS_SUBMITTED'];

if ($IS_SUBMITTED == "1") {

	if ($new_company != "") {

		$sql = "update companies
				set name = '$new_company',
					notes = '$new_notes',
					update_time = '$current_timestamp'
				where id = '$new_cid'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$new_company = stripslashes($new_company);

		$cid = $new_cid;
		
		$_SESSION['session_result_message'] = "Company Updated<BR>";

	} else {
	
		$_SESSION['session_result_message'] = "Please Enter The Company Name<BR>";

	}

} else {

	$sql = "select name, notes
			from companies
			where id = '$cid'";
	$result = mysql_query($sql,$connection);
	
	while ($row = mysql_fetch_object($result)) { 
	
		$new_company = $row->name;
		$new_notes = $row->notes;
	
	}

}
$page_title = "Editting A Company";
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
<strong>Company Name:</strong><BR><BR>
<input name="new_company" type="text" value="<?php if ($new_company != "") echo stripslashes($new_company); ?>
" size="50" maxlength="255">
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=stripslashes($new_notes)?></textarea>
<BR><BR><BR>
<input type="hidden" name="new_cid" value="<?=$cid?>">
<input type="hidden" name="IS_SUBMITTED" value="1">
<input type="submit" name="button" value="Update This Company &raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>