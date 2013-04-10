<?php
// /add/ip-address.php
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
$new_default_ip_address = $_POST['new_default_ip_address'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

	if ($new_name != '' && $new_ip != '') {

		if ($new_default_ip_address == "1") {
			
			$sql = "UPDATE ip_addresses
					SET default_ip_address = '0',
						update_time = '$current_timestamp'";
			$result = mysql_query($sql,$connection);
			
		} else { 
		
			$sql = "SELECT count(*) as total_count
					FROM ip_addresses
					WHERE default_ip_address = '1'";
			$result = mysql_query($sql,$connection);
			while ($row = mysql_fetch_object($result)) { $temp_total = $row->total_count; }
			if ($temp_total == "0") $new_default_ip_address = "1";
		
		}
		
		$sql = "INSERT INTO ip_addresses
				(name, ip, rdns, notes, default_ip_address, insert_time) VALUES 
				('" . mysql_real_escape_string($new_name) . "', '" . mysql_real_escape_string($new_ip) . "', '" . mysql_real_escape_string($new_rdns) . "', '" . mysql_real_escape_string($new_notes) . "', '$new_default_ip_address', '$current_timestamp')";

		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$_SESSION['session_result_message'] = "IP Address <font class=\"highlight\">$new_name ($new_ip)</font> Added<BR>";
		header("Location: ../ip-addresses.php");
		exit;
		
	} else {
	
		if ($new_name == '') { $_SESSION['session_result_message'] .= "Please enter a name for the IP address<BR>"; }
		if ($new_ip == '') { $_SESSION['session_result_message'] .= "Please enter the IP address<BR>"; }

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
<BR><BR>
<strong>Default IP Address?:</strong>&nbsp;
<input name="new_default_ip_address" type="checkbox" id="new_default_ip_address" value="1"<?php if ($new_default_ip_address == "1") echo " checked";?>>
<BR><BR><BR>
<input type="submit" name="button" value="Add This IP Address &raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>