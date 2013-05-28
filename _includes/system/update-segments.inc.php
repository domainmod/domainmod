<?php
// /_includes/system/update-segments.inc.php
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

include($_SESSION['full_server_path'] . "/_includes/timestamps/current-timestamp.inc.php");

$sql_segment1 = "UPDATE segment_data
				 SET active = '1', 
				 	 update_time = '" . mysql_real_escape_string($current_timestamp) . "'
				 WHERE domain IN (SELECT domain FROM domains WHERE active NOT IN ('0', '10'))";
$result_segment1 = mysql_query($sql_segment1,$connection);

$sql_segment2 = "UPDATE segment_data
				 SET inactive = '1', 
				 	 update_time = '" . mysql_real_escape_string($current_timestamp) . "'
				 WHERE domain IN (SELECT domain FROM domains WHERE active IN ('0', '10'))";
$result_segment2 = mysql_query($sql_segment2,$connection);

$sql_segment3 = "UPDATE segment_data
				 SET missing = '1', 
				 	 update_time = '" . mysql_real_escape_string($current_timestamp) . "'
				 WHERE domain NOT IN (SELECT domain FROM domains)";
$result_segment3 = mysql_query($sql_segment3,$connection);

if ($direct == "1") {

	$_SESSION['result_message'] .= "Segments Updated<BR>";
	
	header("Location: " . $_SERVER['HTTP_REFERER']);
	exit;

} else {
	
	$_SESSION['result_message'] .= "Segments Updated<BR>";

}
?>