<?php
// account.php
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

$page_title = "Editting A Registrar Account";
$software_section = "accounts";

$raid = $_GET['raid'];

// Form Variables
$new_company_id = $_POST['new_company_id'];
$new_registrar_id = $_POST['new_registrar_id'];
$new_username = $_POST['new_username'];
$new_reseller = $_POST['new_reseller'];
$new_notes = $_POST['new_notes'];
$new_raid = $_POST['new_raid'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_username != "") {

		$sql = "update registrar_accounts
				set company_id = '$new_company_id',
					registrar_id = '$new_registrar_id',
					username = '" . mysql_real_escape_string($new_username) . "',
					notes = '" . mysql_real_escape_string($new_notes) . "',
					reseller = '$new_reseller',
					update_time = '$current_timestamp'
				where id = '$new_raid'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$raid = $new_raid; 
		
		$_SESSION['session_result_message'] = "Account Updated<BR>";

	} else {
	
		if ($username == "") { $_SESSION['session_result_message'] .= "Please Enter A Username<BR>"; }

	}

} else {

	$sql = "select company_id, registrar_id, username, notes, reseller
			from registrar_accounts
			where id = '$raid'";
	$result = mysql_query($sql,$connection);
	
	while ($row = mysql_fetch_object($result)) { 
	
		$new_company_id = $row->company_id;
		$new_registrar_id = $row->registrar_id;
		$new_username = $row->username;
		$new_notes = $row->notes;
		$new_reseller = $row->reseller;
	
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
<form name="form1" method="post" action="<?=$PHP_SELF?>">
<strong>Company:</strong><BR><BR>
<?php
$sql_company = "select id, name
				from companies
				where active = '1'
				order by name asc";
$result_company = mysql_query($sql_company,$connection) or die(mysql_error());
echo "<select name=\"new_company_id\">";
while ($row_company = mysql_fetch_object($result_company)) {

	if ($row_company->id == $new_company_id) {

		echo "<option value=\"$row_company->id\" selected>$row_company->name</option>";
	
	} else {

		echo "<option value=\"$row_company->id\">$row_company->name</option>";
	
	}
}
echo "</select>";
?>
<BR><BR>
<strong>Registrar:</strong><BR><BR>
<?php
$sql_registrar = "select id, name
				  from registrars
				  where active = '1'
				  order by name asc";
$result_registrar = mysql_query($sql_registrar,$connection) or die(mysql_error());
echo "<select name=\"new_registrar_id\">";
while ($row_registrar = mysql_fetch_object($result_registrar)) {

	if ($row_registrar->id == $new_registrar_id) {

		echo "<option value=\"$row_registrar->id\" selected>$row_registrar->name</option>";
	
	} else {

		echo "<option value=\"$row_registrar->id\">$row_registrar->name</option>";
	
	}
}
echo "</select>";
?>
<BR><BR>
<strong>Username:</strong><BR><BR>
<input name="new_username" type="text" size="50" maxlength="255" value="<?=$new_username?>">
<BR><BR>
<strong>Reseller Account?</strong><BR><BR>
<select name="new_reseller">";
<option value="0"<?php if ($new_reseller == "0") echo " selected"; ?>>No</option>
<option value="1"<?php if ($new_reseller == "1") echo " selected"; ?>>Yes</option>
</select>
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=$new_notes?></textarea>
<BR><BR><BR>
<input type="hidden" name="new_raid" value="<?=$raid?>">
<input type="submit" name="button" value="Update This Account &raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>