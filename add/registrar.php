<?php
// registrar.php
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
$software_section = "registrars";

// Form Variables
$new_registrar = mysql_real_escape_string($_POST['new_registrar']);
$new_url = mysql_real_escape_string($_POST['new_url']);
$new_notes = mysql_real_escape_string($_POST['new_notes']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_registrar != "" && $new_url != "") {

		$sql = "insert into registrars
				(name, url, notes, insert_time)
				values ('$new_registrar', '$new_url', '$new_notes', '$current_timestamp')";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$_SESSION['session_result_message'] = "Registrar Added<BR>";

		header("Location: ../registrars.php");
		exit;

	} else {
	
		if ($new_registrar == "") $_SESSION['session_result_message'] .= "Please Enter The Registrar Name<BR>";
		if ($new_url == "") $_SESSION['session_result_message'] .= "Please Enter The Registrar's URL<BR>";

	}

}
$page_title = "Adding A New Registrar";
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
<strong>Registrar Name:</strong><BR><BR>
<input name="new_registrar" type="text" value="<?=stripslashes($new_registrar)?>" size="50" maxlength="255">
<BR><BR>
<strong>Registrar's URL:</strong><BR><BR>
<input name="new_url" type="text" value="<?=stripslashes($new_url)?>" size="50" maxlength="255">
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=stripslashes($new_notes)?></textarea>
<BR><BR><BR>
<input type="submit" name="button" value="Add This Registrar &raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>