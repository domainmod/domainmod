<?php
// /add/ssl-type.php
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
include("../_includes/start-sessoin.inc.php");
include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/auth/auth-check.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");

$page_title = "Adding A New SSL Type";
$software_section = "ssl-types";

// Form Variables
$new_type = $_POST['new_type'];
$new_notes = $_POST['new_notes'];
$new_default_type = $_POST['new_default_type'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_type != "") {
		
		if ($new_default_type == "1") {
			
			$sql = "UPDATE ssl_cert_types
					SET default_type = '0',
						update_time = '$current_timestamp'";
			$result = mysql_query($sql,$connection);
			
		} else { 
		
			$sql = "SELECT count(*) as total_count
					FROM ssl_cert_types
					WHERE default_type = '1'";
			$result = mysql_query($sql,$connection);
			while ($row = mysql_fetch_object($result)) { $temp_total = $row->total_count; }
			if ($temp_total == "0") $new_default_type = "1";
		
		}

		$sql = "INSERT INTO ssl_cert_types
				(type, notes, default_type, insert_time) VALUES 
				('" . mysql_real_escape_string($new_type) . "', '" . mysql_real_escape_string($new_notes) . "', '$new_default_type', '$current_timestamp')";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$_SESSION['session_result_message'] = "SSL Type <font class=\"highlight\">$new_type</font> Added<BR>";
		
		header("Location: ../ssl-types.php");
		exit;

	} else {
	
		$_SESSION['session_result_message'] .= "Please enter the SSL Type<BR>";

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
<form name="add_type_form" method="post" action="<?=$PHP_SELF?>">
<strong>Type:</strong><BR><BR>
<input name="new_type" type="text" value="<?=$new_type?>" size="50" maxlength="255">
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=$new_notes?></textarea>
<BR><BR>
<strong>Default Type?:</strong>&nbsp;
<input name="new_default_type" type="checkbox" value="1"<?php if ($new_default_type == "1") echo " checked";?>>
<BR><BR><BR>
<input type="submit" name="button" value="Add This SSL Type &raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>