<?php
// /_includes/system/mark-updates-read.inc.php
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
$direct = $_GET['direct'];

if ($direct == "1") { 

	include("../start-session.inc.php");
	include("../config.inc.php");
	include("../database.inc.php");
	include("../software.inc.php");
	include("../auth/auth-check.inc.php");

}

$id = $_GET['id'];

if ($id != "") {

	$sql_mark_read = "DELETE FROM `update_data`
					  WHERE user_id = '" . $_SESSION['user_id'] . "'
						AND update_id = '" . mysql_real_escape_string($id) . "'";
	$result_mark_read = mysql_query($sql_mark_read,$connection) or die(mysql_error());

	$sql_check_for_more = "SELECT id
						   FROM `update_data`
						   WHERE user_id = '" . $_SESSION['user_id'] . "'";
	$result_check_for_more = mysql_query($sql_check_for_more,$connection) or die(mysql_error());
	
	if (mysql_num_rows($result_check_for_more) == 0) { 

		$_SESSION['are_there_updates'] = "0";

	}

} else {

	$sql_mark_read = "DELETE FROM `update_data`
					  WHERE user_id = '" . $_SESSION['user_id'] . "'";
	$result_mark_read = mysql_query($sql_mark_read,$connection) or die(mysql_error());
	
	$_SESSION['are_there_updates'] = "0";

}

if ($direct == "1") {

	if ($id != "") {

		$_SESSION['result_message'] .= "Update marked as read<BR>";
		
	} else {

		$_SESSION['result_message'] .= "All updates have been marked as read<BR>";
		
	}

	header("Location: " . $_SERVER['HTTP_REFERER']);
	exit;

} else {

	if ($id != "") {

		$_SESSION['result_message'] .= "Update marked as read<BR>";
		
	} else {

		$_SESSION['result_message'] .= "All updates have been marked as read<BR>";
		
	}

}
?>