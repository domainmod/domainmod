<?php
// dns.php
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
$software_section = "dns";

// Form Variables
$new_name = $_POST['new_name'];
$new_notes = $_POST['new_notes'];
$new_dns1 = $_POST['new_dns1'];
$new_dns2 = $_POST['new_dns2'];
$new_dns3 = $_POST['new_dns3'];
$new_dns4 = $_POST['new_dns4'];
$new_dns5 = $_POST['new_dns5'];
$new_dns6 = $_POST['new_dns6'];
$new_dns7 = $_POST['new_dns7'];
$new_dns8 = $_POST['new_dns8'];
$new_dns9 = $_POST['new_dns9'];
$new_dns10 = $_POST['new_dns10'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_name != '') {
		
		$new_number_of_servers = 10;
		
		if ($new_dns10 == '') {
			$new_number_of_servers = '9';
		}
		if ($new_dns9 == '') {
			$new_number_of_servers = '8';
		}
		if ($new_dns8 == '') {
			$new_number_of_servers = '7';
		}
		if ($new_dns7 == '') {
			$new_number_of_servers = '6';
		}
		if ($new_dns6 == '') {
			$new_number_of_servers = '5';
		}
		if ($new_dns5 == '') {
			$new_number_of_servers = '4';
		}
		if ($new_dns4 == '') {
			$new_number_of_servers = '3';
		}
		if ($new_dns3 == '') {
			$new_number_of_servers = '2';
		}
		if ($new_dns2 == '') {
			$new_number_of_servers = '1';
		}
		if ($new_dns1 == '') {
			$new_number_of_servers = '0';
		}

		$sql = "insert into dns
				(name, dns1, dns2, dns3, dns4, dns5, dns6, dns7, dns8, dns9, dns10, notes, number_of_servers, insert_time)
				values ('$new_name', '$new_dns1', '$new_dns2', '$new_dns3', '$new_dns4', '$new_dns5', '$new_dns6', '$new_dns7', '$new_dns8', '$new_dns9', '$new_dns10', '$new_notes', '$new_number_of_servers', '$current_timestamp')";

		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$_SESSION['session_result_message'] = "DNS Profile Added ($new_name)<BR>";
		
	} else {
	
		if ($new_name == '') { $_SESSION['session_result_message'] .= "Please Enter A Name For The DNS Profile<BR>"; }

	}

}
$page_title = "Adding A New DNS Profile";
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
<strong>Profile Name:</strong><BR><BR>
<input name="new_name" type="text" size="50" maxlength="255" value="<?=stripslashes($new_name)?>">
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=stripslashes($new_notes)?></textarea>
<BR><BR>
<strong>DNS 1:</strong><BR><BR>
<input name="new_dns1" type="text" size="50" maxlength="255" value="<?=stripslashes($new_dns1)?>">
<BR><BR>
<strong>DNS 2:</strong><BR><BR>
<input name="new_dns2" type="text" size="50" maxlength="255" value="<?=stripslashes($new_dns2)?>">
<BR><BR>
<strong>DNS 3:</strong><BR><BR>
<input name="new_dns3" type="text" size="50" maxlength="255" value="<?=stripslashes($new_dns3)?>">
<BR><BR>
<strong>DNS 4:</strong><BR><BR>
<input name="new_dns4" type="text" size="50" maxlength="255" value="<?=stripslashes($new_dns4)?>">
<BR><BR>
<strong>DNS 5:</strong><BR><BR>
<input name="new_dns5" type="text" size="50" maxlength="255" value="<?=stripslashes($new_dns5)?>">
<BR><BR>
<strong>DNS 6:</strong><BR><BR>
<input name="new_dns6" type="text" size="50" maxlength="255" value="<?=stripslashes($new_dns6)?>">
<BR><BR>
<strong>DNS 7:</strong><BR><BR>
<input name="new_dns7" type="text" size="50" maxlength="255" value="<?=stripslashes($new_dns7)?>">
<BR><BR>
<strong>DNS 8:</strong><BR><BR>
<input name="new_dns8" type="text" size="50" maxlength="255" value="<?=stripslashes($new_dns8)?>">
<BR><BR>
<strong>DNS 9:</strong><BR><BR>
<input name="new_dns9" type="text" size="50" maxlength="255" value="<?=stripslashes($new_dns9)?>">
<BR><BR>
<strong>DNS 10:</strong><BR><BR>
<input name="new_dns10" type="text" size="50" maxlength="255" value="<?=stripslashes($new_dns10)?>">
<BR><BR><BR>
<input type="submit" name="button" value="Add This DNS Profile &raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>