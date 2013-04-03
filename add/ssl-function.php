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

$page_title = "Adding A New SSL Function";
$software_section = "ssl-functions";

// Form Variables
$new_function = $_POST['new_function'];
$new_notes = $_POST['new_notes'];
$new_default_function = $_POST['new_default_function'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_function != "") {
		
		if ($new_default_function == "1") {
			
			$sql = "UPDATE ssl_cert_functions
					SET default_function = '0',
						update_time = '$current_timestamp'";
			$result = mysql_query($sql,$connection);
			
		} else { 
		
			$sql = "SELECT count(*) as total_count
					FROM ssl_cert_functions
					WHERE default_function = '1'";
			$result = mysql_query($sql,$connection);
			while ($row = mysql_fetch_object($result)) { $temp_total = $row->total_count; }
			if ($temp_total == "0") $new_default_function = "1";
		
		}

		$sql = "INSERT INTO ssl_cert_functions
				(function, notes, default_function, insert_time) VALUES 
				('" . mysql_real_escape_string($new_function) . "', '" . mysql_real_escape_string($new_notes) . "', '$new_default_function', '$current_timestamp')";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$_SESSION['session_result_message'] = "Function <font class=\"highlight\">$new_function</font> Added<BR>";
		
		header("Location: ../ssl-functions.php");
		exit;

	} else {
	
		$_SESSION['session_result_message'] .= "Please enter the SSL function name<BR>";

	}

}
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/head-tags.inc.php"); ?>
</head>
<body onLoad="document.forms[0].elements[0].focus()";>
<?php include("../_includes/header.inc.php"); ?>
<form name="add_function_form" method="post" action="<?=$PHP_SELF?>">
<strong>Function :</strong><BR><BR>
<input name="new_function" type="text" value="<?=$new_function?>" size="50" maxlength="255">
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=$new_notes?></textarea>
<BR><BR>
<strong>Default Function?:</strong>&nbsp;
<input name="new_default_function" type="checkbox" id="new_default_function" value="1">
<BR><BR><BR>
<input type="submit" name="button" value="Add This Function &raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>