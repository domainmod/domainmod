<?php
// ssl-function.php
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

$page_title = "Editting An SSL Function";
$software_section = "ssl-functions";

// 'Delete Function' Confirmation Variables
$del = $_GET['del'];
$really_del = $_GET['really_del'];

$sslfid = $_GET['sslfid'];

// Form Variables
$new_function = $_REQUEST['new_function'];
$new_notes = $_REQUEST['new_notes'];
$new_default_function = $_REQUEST['new_default_function'];
$new_sslfid = $_REQUEST['new_sslfid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_function != "") {

		if ($new_default_function == "1") {
			
			$sql = "UPDATE ssl_cert_functions
					SET default_function = '0',
					    update_time = '$current_timestamp'";
			$result = mysql_query($sql,$connection);
			
		} else { 
		
			$sql = "SELECT default_function
					FROM ssl_cert_functions
					WHERE default_function = '1'
					  AND id != '$new_sslfid'";
			$result = mysql_query($sql,$connection);
			while ($row = mysql_fetch_object($result)) { $temp_default_function = $row->default_function; }
			if ($temp_default_function == "") { $new_default_function = "1"; }
		
		}

		$sql = "UPDATE ssl_cert_functions
				SET function = '" . mysql_real_escape_string($new_function) . "', 
					notes = '" . mysql_real_escape_string($new_notes) . "',
					default_function = '$new_default_function',
					update_time = '$current_timestamp'
				WHERE id = '$new_sslfid'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$new_function = $new_function;
		$new_notes = $new_notes;
		$new_default_function = $new_default_function;

		$sslfid = $new_sslfid;
		
		$_SESSION['session_result_message'] = "Function <font class=\"highlight\">$new_function</font> Updated<BR>";

	} else {
	
		$_SESSION['session_result_message'] = "Please enter the function name<BR>";

	}

} else {

	$sql = "SELECT function, notes, default_function
			FROM ssl_cert_functions
			WHERE id = '$sslfid'";
	$result = mysql_query($sql,$connection);
	
	while ($row = mysql_fetch_object($result)) { 
	
		$new_function = $row->function;
		$new_notes = $row->notes;
		$new_default_function = $row->default_function;

	}

}
if ($del == "1") {

	$sql = "SELECT function_id
			FROM ssl_certs
			WHERE function_id = '$sslfid'";
	$result = mysql_query($sql,$connection);
	
	while ($row = mysql_fetch_object($result)) {
		$existing_ssl_cert = 1;
	}
	
	if ($existing_ssl_cert > 0) {

		$_SESSION['session_result_message'] = "This Function has SSL certificates associated with it and cannot be deleted<BR>";

	} else {

		$_SESSION['session_result_message'] = "Are you sure you want to delete this Function?<BR><BR><a href=\"$PHP_SELF?sslfid=$sslfid&really_del=1\">YES, REALLY DELETE THIS FUNCTION</a><BR>";

	}

}

if ($really_del == "1") {

	$sql = "DELETE FROM ssl_cert_functions
			WHERE id = '$sslfid'";
	$result = mysql_query($sql,$connection);
	
	$_SESSION['session_result_message'] = "Function <font class=\"highlight\">$new_function</font> Deleted<BR>";
	
	header("Location: ../ssl-functions.php");
	exit;

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
<form name="edit_function_form" method="post" action="<?=$PHP_SELF?>">
<strong>Function Name:</strong><BR><BR>
<input name="new_function" type="text" value="<?php if ($new_function != "") echo $new_function; ?>
" size="50" maxlength="255">
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=$new_notes?></textarea>
<BR><BR>
<strong>Default Function?:</strong>&nbsp;
<input name="new_default_function" type="checkbox" value="1"<?php if ($new_default_function == "1") echo " checked"; ?>>
<BR><BR><BR>
<input type="hidden" name="new_sslfid" value="<?=$sslfid?>">
<input type="submit" name="button" value="Update This Function &raquo;">
</form>
<BR><a href="<?=$PHP_SELF?>?sslfid=<?=$sslfid?>&del=1">DELETE THIS FUNCTION</a>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>