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
include("../_includes/timestamps/current-timestamp.inc.php");

$page_title = "Editting A Currency";
$software_section = "currencies";

$curid = $_GET['curid'];

// Form Variables
$new_name = $_POST['new_name'];
$new_abbreviation = $_POST['new_abbreviation'];
$new_default_currency = $_POST['new_default_currency'];
$new_notes = $_POST['new_notes'];
$new_curid = $_POST['new_curid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_name != "" && $new_abbreviation != "") {

		if ($new_default_currency != 1) {
			
			$sql = "select currency
					from currencies
					where default_currency = '1'";
			$result = mysql_query($sql,$connection);
			
			while ($row = mysql_fetch_object($result)) {
				$default_currency_abbreviation = $row->currency;
			}
			
			if ($default_currency_abbreviation == $new_abbreviation) {
				$new_default_currency = "1";
			}
			
		} elseif ($new_default_currency == "1") {
			
			$sql = "update currencies
					set default_currency = '0',
					update_time = '$current_timestamp'";
			$result = mysql_query($sql,$connection);
			
		}
		
		$sql = "update currencies
				set currency = '" . mysql_real_escape_string($new_abbreviation) . "',
					name = '" . mysql_real_escape_string($new_name) . "',
					notes = '" . mysql_real_escape_string($new_notes) . "',
					default_currency = '$new_default_currency',
					update_time = '$current_timestamp'
				where id = '$new_curid'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$curid = $new_curid;
		
		$_SESSION['session_result_message'] = "Currency Updated<BR><BR><a href=\"../system/update-exchange-rates.php\">You should click here to update the exchange rates</a><BR>";

	} else {
	
		if ($new_name == "") { $_SESSION['session_result_message'] .= "Please Enter The Currency Name<BR>"; }
		if ($new_abbreviation == "") { $_SESSION['session_result_message'] .= "Please Enter The Abbreviation<BR>"; }

	}

} else {

	$sql = "select currency, name, notes, default_currency
			from currencies
			where id = '$curid'";
	$result = mysql_query($sql,$connection);
	
	while ($row = mysql_fetch_object($result)) { 
	
		$new_name = $row->name;
		$new_abbreviation = $row->currency;
		$new_notes = $row->notes;
		$new_default_currency = $row->default_currency;
	
	}

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
<form name="edit_currency_form" method="post" action="<?=$PHP_SELF?>">
<strong>Name:</strong><BR><BR>
<input name="new_name" type="text" size="50" maxlength="255" value="<?=$new_name?>">
<BR><BR>
<strong>Abbreviation:</strong><BR><BR>
<input name="new_abbreviation" type="text" size="50" maxlength="3" value="<?=$new_abbreviation?>">
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=$new_notes?></textarea>
<BR><BR>
<?php if ($new_default_currency != 1) { ?>
<strong>Set as default currency?</strong>&nbsp;
<input name="new_default_currency" type="checkbox" id="new_default_currency" value="1"<?php if ($new_default_currency == "1") echo " checked"; ?>>
<?php } else { ?>
<strong>This is currently set as the default currency.</strong>&nbsp;
<?php } ?>
<BR><BR><BR>
<input type="hidden" name="new_curid" value="<?=$curid?>">
<input type="submit" name="button" value="Update This Currency &raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>