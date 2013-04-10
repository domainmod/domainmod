<?php
// /add/registrar.php
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

$page_title = "Adding A New Registrar";
$software_section = "registrars";

// Form Variables
$new_registrar = $_POST['new_registrar'];
$new_url = $_POST['new_url'];
$new_notes = $_POST['new_notes'];
$new_default_registrar = $_POST['new_default_registrar'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_registrar != "" && $new_url != "") {

		if ($new_default_registrar == "1") {
			
			$sql = "UPDATE registrars
					SET default_registrar = '0',
						update_time = '$current_timestamp'";
			$result = mysql_query($sql,$connection);
			
		} else { 
		
			$sql = "SELECT count(*) as total_count
					FROM registrars
					WHERE default_registrar = '1'";
			$result = mysql_query($sql,$connection);
			while ($row = mysql_fetch_object($result)) { $temp_total = $row->total_count; }
			if ($temp_total == "0") $new_default_registrar = "1";
		
		}

		$sql = "INSERT INTO registrars
				(name, url, notes, default_registrar, insert_time) VALUES 
				('" . mysql_real_escape_string($new_registrar) . "', '" . mysql_real_escape_string($new_url) . "', '" . mysql_real_escape_string($new_notes) . "', '$new_default_registrar', '$current_timestamp')";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$_SESSION['session_result_message'] = "Registrar <font class=\"highlight\">$new_registrar</font> Added<BR>";

		if ($_SESSION['session_need_registrar'] == "1") {
			
			include("../_includes/auth/login-checks/domain-and-ssl-asset-check.inc.php");
			header("Location: ../domains.php");

		} else {

			header("Location: ../registrars.php");
			
		}
		exit;

	} else {
	
		if ($new_registrar == "") $_SESSION['session_result_message'] .= "Please enter the registrar name<BR>";
		if ($new_url == "") $_SESSION['session_result_message'] .= "Please enter the registrar's URL<BR>";

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
<form name="add_registrar_form" method="post" action="<?=$PHP_SELF?>">
<strong>Registrar Name:</strong><BR><BR>
<input name="new_registrar" type="text" value="<?=$new_registrar?>" size="50" maxlength="255">
<BR><BR>
<strong>Registrar's URL:</strong><BR><BR>
<input name="new_url" type="text" value="<?=$new_url?>" size="50" maxlength="255">
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=$new_notes?></textarea>
<BR><BR>
<strong>Default Registrar?:</strong>&nbsp;
<input name="new_default_registrar" type="checkbox" id="new_default_registrar" value="1"<?php if ($new_default_registrar == "1") echo " checked";?>>
<BR><BR><BR>
<input type="submit" name="button" value="Add This Registrar &raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>