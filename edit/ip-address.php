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

$page_title = "Editting An IP Address";
$software_section = "ip-addresses";

$ipid = $_GET['ipid'];

// Form Variables
$new_name = $_POST['new_name'];
$new_ip = $_POST['new_ip'];
$new_ipid = $_POST['new_ipid'];
$new_notes = $_POST['new_notes'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

		$sql2 = "update ip_addresses
				 set name = '" . mysql_real_escape_string($new_name) . "',
				 	ip = '" . mysql_real_escape_string($new_ip) . "',
					notes = '" . mysql_real_escape_string($new_notes) . "',
					update_time = '$current_timestamp'
				 where id = '$new_ipid'";
		$result2 = mysql_query($sql2,$connection) or die(mysql_error());
		
		$ipid = $new_ipid;
		
		$_SESSION['session_result_message'] = "IP Address Updated<BR>";

} else {

	$sql = "select name, ip, notes
			from ip_addresses
			where id = '$ipid'";
	$result = mysql_query($sql,$connection);
	
	while ($row = mysql_fetch_object($result)) { 
	
		$new_name = $row->name;
		$new_ip = $row->ip;
		$new_notes = $row->notes;
	
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
<strong>IP Address Name:</strong><BR><BR>
<input name="new_name" type="text" size="50" maxlength="255" value="<?php if ($new_name != "") echo $new_name; ?>">
<BR><BR>
<strong>IP Address:</strong><BR><BR>
<input name="new_ip" type="text" size="50" maxlength="255" value="<?php if ($new_ip != "") echo $new_ip; ?>">
<BR><BR>
<strong>Notes:</strong><BR><BR>
<textarea name="new_notes" cols="60" rows="5"><?=$new_notes?></textarea>
<BR><BR><BR>
<input type="hidden" name="new_ipid" value="<?=$ipid?>">
<input type="submit" name="button" value="Update This IP Address &raquo;">
</form>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>