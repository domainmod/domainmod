<?php
// /edit/currency.php
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
include("../_includes/start-session.inc.php");
include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/auth/auth-check.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");

$page_title = "Editting A Currency";
$software_section = "currencies";

// 'Delete Currency' Confirmation Variables
$del = $_GET['del'];
$really_del = $_GET['really_del'];

$curid = $_GET['curid'];

// Form Variables
$new_name = $_POST['new_name'];
$new_abbreviation = $_POST['new_abbreviation'];
$new_conversion = $_POST['new_conversion'];
$new_notes = $_POST['new_notes'];
$new_curid = $_POST['new_curid'];
$new_default_currency = $_POST['new_default_currency'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_name != "" && $new_abbreviation != "" && $new_conversion != "") {

		if ($new_default_currency == "1") {

			$sql = "UPDATE currencies
					SET default_currency = '0'
					WHERE currency != '$new_abbreviation'";
			$result = mysql_query($sql,$connection);
			
		} else {
			
			$sql = "SELECT default_currency
					FROM currencies
					WHERE default_currency = '1'
					  AND id != '$new_curid'";
			$result = mysql_query($sql,$connection);
			while ($row = mysql_fetch_object($result)) { $temp_default_currency = $row->default_currency; }
			if ($temp_default_currency == "") { $new_default_currency = "1"; }
			
		}

		if ($new_default_currency == "1") $new_conversion = "1";
		
		$sql = "UPDATE currencies
				SET name = '" . mysql_real_escape_string($new_name) . "',
					currency = '" . mysql_real_escape_string($new_abbreviation) . "',
					conversion = '" . mysql_real_escape_string($new_conversion) . "',
					notes = '" . mysql_real_escape_string($new_notes) . "',
					default_currency = '" . $new_default_currency . "',
					update_time = '$current_timestamp'
				WHERE id = '$new_curid'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$curid = $new_curid;
		
		$_SESSION['session_result_message'] = "Currency <font class=\"highlight\">$new_name ($new_abbreviation)</font> Updated<BR><BR><a href=\"../system/update-conversion-rates.php\">You should click here to update the conversion rates</a><BR>";
		
	} else {
	
		if ($new_name == "") { $_SESSION['session_result_message'] .= "Please enter the currency name<BR>"; }
		if ($new_abbreviation == "") { $_SESSION['session_result_message'] .= "Please enter the currency abbreviation<BR>"; }
		if ($new_conversion == "") { $_SESSION['session_result_message'] .= "Please enter the conversion rate<BR>"; }

	}

} else {

	$sql = "SELECT currency, name, conversion, notes, default_currency
			FROM currencies
			WHERE id = '$curid'";
	$result = mysql_query($sql,$connection);
	
	while ($row = mysql_fetch_object($result)) { 
	
		$new_name = $row->name;
		$new_abbreviation = $row->currency;
		$new_conversion = $row->conversion;
		$new_notes = $row->notes;
		$new_default_currency = $row->default_currency;
	
	}

}
if ($del == "1") {

	$sql = "SELECT currency_id
			FROM fees
			WHERE currency_id = '$curid'";
	$result = mysql_query($sql,$connection);
	
	while ($row = mysql_fetch_object($result)) {
		$existing_domain_fees = 1;
	}

	$sql = "SELECT currency_id
			FROM ssl_fees
			WHERE currency_id = '$curid'";
	$result = mysql_query($sql,$connection);
	
	while ($row = mysql_fetch_object($result)) {
		$existing_ssl_fees = 1;
	}
	
	if ($existing_domain_fees > 0 || $existing_ssl_fees > 0) {

		if ($existing_domain_fees > 0) $_SESSION['session_result_message'] .= "This Currency has domain fees associated with it and cannot be deleted<BR>";
		if ($existing_ssl_fees > 0) $_SESSION['session_result_message'] .= "This Currency has SSL fees associated with it and cannot be deleted<BR>";

	} else {

		$_SESSION['session_result_message'] = "Are you sure you want to delete this Currency?<BR><BR><a href=\"$PHP_SELF?curid=$curid&really_del=1\">YES, REALLY DELETE THIS CURRENCY</a><BR>";

	}

}

if ($really_del == "1") {

	$sql = "DELETE FROM currencies 
			WHERE id = '$curid'";
	$result = mysql_query($sql,$connection);
	
	$_SESSION['session_result_message'] = "Currency <font class=\"highlight\">$new_name ($new_abbreviation)</font> Deleted<BR>";
	
	header("Location: ../currencies.php");
	exit;

}
?>
<?php include("../_includes/doctype.inc.php"); ?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../_includes/header.inc.php"); ?>
<?php
$sql = "SELECT name, currency
		FROM currencies
		WHERE default_currency = '1'";
$result = mysql_query($sql,$connection);
while ($row = mysql_fetch_object($result)) {
	$default_name = $row->name;
	$default_currency = $row->currency;
}
?>
<form name="edit_currency_form" method="post" action="<?=$PHP_SELF?>">
<strong>Name ("<em><?=$default_name?></em>")</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
<input name="new_name" type="text" size="50" maxlength="255" value="<?=$new_name?>">
<BR><BR>
<strong>Abbreviation ("<em><?=$default_currency?></em>")</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
<input name="new_abbreviation" type="text" size="50" maxlength="3" value="<?=$new_abbreviation?>">
<BR><BR>
<strong>Conversion Rate</strong><a title="Required Field"><font class="default_highlight"><strong>*</strong></font></a><BR><BR>
<input name="new_conversion" type="text" size="50" maxlength="10" value="<?=$new_conversion?>">
<BR><BR>
<strong>Notes</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=$new_notes?></textarea>
<BR><BR>
<strong>Default Currency?</strong>&nbsp;
<input name="new_default_currency" type="checkbox" value="1"<?php if ($new_default_currency == "1") echo " checked"; ?>>
<BR><BR><BR>
<input type="hidden" name="new_curid" value="<?=$curid?>">
<input type="submit" name="button" value="Update This Currency &raquo;">
</form>
<BR><BR><a href="<?=$PHP_SELF?>?curid=<?=$curid?>&del=1">DELETE THIS CURRENCY</a>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>