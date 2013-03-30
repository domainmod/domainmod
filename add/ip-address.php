<?php
// ip-address.php
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

$page_title = "Adding A New IP Address";
$software_section = "ip-addresses";

// Form Variables
$new_name = $_POST['new_name'];
$new_ip = $_POST['new_ip'];
$new_rdns = $_POST['new_rdns'];
$new_notes = $_POST['new_notes'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_name != '' && $new_ip != '') {
		
		$sql = "insert into ip_addresses
				(name, ip, rdns, notes, insert_time)
				values ('" . mysql_real_escape_string($new_name) . "', '" . mysql_real_escape_string($new_ip) . "', '" . mysql_real_escape_string($new_rdns) . "', '" . mysql_real_escape_string($new_notes) . "', '$current_timestamp')";

		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$_SESSION['session_result_message'] = "IP Address Added ($new_name)<BR>";
		
	} else {
	
		if ($new_name == '') { $_SESSION['session_result_message'] .= "Please Enter A Name For The IP Address<BR>"; }
		if ($new_ip == '') { $_SESSION['session_result_message'] .= "Please Enter The IP Address<BR>"; }

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
<form name="add_ip_address_form" method="post" action="<?=$PHP_SELF?>">
<strong>IP Address Name:</strong><BR><BR>
<input name="new_name" type="text" size="50" maxlength="255" value="<?=$new_name?>">
<BR><BR>
<strong>IP Address:</strong><BR><BR>
<input name="new_ip" type="text" size="50" maxlength="255" value="<?=$new_ip?>">
<BR><BR>
<strong>rDNS:</strong><BR><BR>
<input name="new_rdns" type="text" size="50" maxlength="255" value="<?=$new_rdns?>">
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=$new_notes?></textarea>
<BR><BR><BR>
<input type="submit" name="button" value="Add This IP Address &raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>