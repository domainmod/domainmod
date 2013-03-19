<?php
// currency.php
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
$software_section = "currencies";

// Form Variables
$new_name = $_POST['new_name'];
$new_abbreviation = $_POST['new_abbreviation'];
$new_conversion = $_POST['new_conversion'];
$new_default_currency = $_POST['new_default_currency'];
$new_notes = $_POST['new_notes'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_name != "" && $new_abbreviation != "" && $new_conversion != "") {

		$sql = "insert into currencies
				(currency, name, conversion, notes, default_currency, insert_time)
				values ('$new_abbreviation', '$new_name', '$new_conversion', '$new_notes', '$new_default_currency', '$current_timestamp')";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$_SESSION['session_result_message'] = "Currency Added<BR>";
		
		header("Location: ../currencies.php");
		exit;

	} else {
	
		if ($new_name == "") $_SESSION['session_result_message'] .= "Please Enter The Currency Name<BR>";
		if ($new_abbreviation == "") $_SESSION['session_result_message'] .= "Please Enter The Currency Abbreviation<BR>";
		if ($new_conversion == "") $_SESSION['session_result_message'] .= "Please Enter The Currency Conversion<BR>";

	}

}
$page_title = "Adding A New Currency";
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
<strong>Name:</strong><BR><BR>
<input name="new_name" type="text" value="<?=stripslashes($new_name)?>" size="50" maxlength="255">
<BR><BR>
<strong>Abbreviation:</strong><BR><BR>
<input name="new_abbreviation" type="text" value="<?=stripslashes($new_abbreviation)?>" size="50" maxlength="3">
<BR><BR>
<strong>Conversion:</strong><BR><BR>
<input name="new_conversion" type="text" value="<?=stripslashes($new_conversion)?>" size="50" maxlength="255">
<BR><BR>
<strong>Default Currency?:</strong>&nbsp;
<input name="new_default_currency" type="checkbox" id="new_default_currency" value="1">
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=stripslashes($new_notes)?></textarea>
<BR><BR><BR>
<input type="submit" name="button" value="Add This Currency &raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>