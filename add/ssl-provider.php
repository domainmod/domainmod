<?php
// ssl-provider.php
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

$page_title = "Adding A New SSL Provider";
$software_section = "ssl-providers";

// Form Variables
$new_ssl_provider = $_POST['new_ssl_provider'];
$new_url = $_POST['new_url'];
$new_notes = $_POST['new_notes'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_ssl_provider != "" && $new_url != "") {

		$sql = "insert into ssl_providers
				(name, url, notes, insert_time)
				values ('" . mysql_real_escape_string($new_ssl_provider) . "', '" . mysql_real_escape_string($new_url) . "', '" . mysql_real_escape_string($new_notes) . "', '$current_timestamp')";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$_SESSION['session_result_message'] = "SSL Provider Added<BR>";
		
		header("Location: ../ssl-providers.php");
		exit;

	} else {
	
		if ($new_ssl_provider == "") $_SESSION['session_result_message'] .= "Please Enter The SSL Provider Name<BR>";
		if ($new_url == "") $_SESSION['session_result_message'] .= "Please Enter The SSL Provider's URL<BR>";

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
<form name="form1" method="post" action="<?=$PHP_SELF?>">
<strong>SSL Provider Name:</strong><BR><BR>
<input name="new_ssl_provider" type="text" value="<?=$new_ssl_provider?>" size="50" maxlength="255">
<BR><BR>
<strong>SSL Provider's URL:</strong><BR><BR>
<input name="new_url" type="text" value="<?=$new_url?>" size="50" maxlength="255">
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=$new_notes?></textarea>
<BR><BR><BR>
<input type="submit" name="button" value="Add This SSL Provider &raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>