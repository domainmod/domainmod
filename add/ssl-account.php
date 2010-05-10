<?php
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
$software_section = "ssl-accounts";

// Form Variables
$new_company_id = $_POST['new_company_id'];
$new_ssl_provider_id = $_POST['new_ssl_provider_id'];
$new_username = $_POST['new_username'];
$new_reseller = $_POST['new_reseller'];
$new_notes = $_POST['new_notes'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_username != "") {

		$sql = "insert into ssl_accounts
				(company_id, ssl_provider_id, username, notes, reseller, insert_time)
				values ('$new_company_id', '$new_ssl_provider_id', '$new_username', '$new_notes', '$new_reseller', '$current_timestamp')";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$_SESSION['session_result_message'] = "SSL Account Added<BR>";
		
		header("Location: ../ssl-accounts.php");
		exit;

	} else {
	
		if ($username == "") { $_SESSION['session_result_message'] .= "Please Enter A Username<BR>"; }

	}

}
$page_title = "Adding A New SSL Provider Account";
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
<strong>SSL Provider:</strong><BR><BR>
<?php
$sql_ssl_provider = "select id, name
				from ssl_providers
				where active = '1'
				order by name asc";
$result_ssl_provider = mysql_query($sql_ssl_provider,$connection) or die(mysql_error());
echo "<select name=\"new_ssl_provider_id\">";
while ($row_ssl_provider = mysql_fetch_object($result_ssl_provider)) {

	if ($row_ssl_provider->id == $new_ssl_provider_id) {

		echo "<option value=\"$row_ssl_provider->id\" selected>$row_ssl_provider->name</option>";
	
	} else {

		echo "<option value=\"$row_ssl_provider->id\">$row_ssl_provider->name</option>";
	
	}
}
echo "</select>";
?>
<BR><BR>
<strong>Username:</strong><BR><BR>
<input name="new_username" type="text" size="50" maxlength="255" value="<?=stripslashes($new_username)?>">
<BR><BR>
<strong>Reseller Account?</strong><BR><BR>
<select name="new_reseller">";
<option value="0" selected>No</option>
<option value="1">Yes</option>
</select>
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=stripslashes($new_notes)?>
</textarea>
<BR><BR><BR>
<input type="submit" name="button" value="Add This SSL Provider Account &raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>