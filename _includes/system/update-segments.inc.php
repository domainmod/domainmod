<?php
/**
 * /_includes/system/update-segments.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (C) 2010-2015 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: http://chetcuti.com
 *
 * DomainMOD is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later
 * version.
 *
 * DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with DomainMOD. If not, see
 * http://www.gnu.org/licenses/.
 *
 */
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
				 	 update_time = '" . mysqli_real_escape_string($connection, $current_timestamp) . "'
				 WHERE domain IN (SELECT domain FROM domains WHERE active NOT IN ('0', '10'))";
$result_segment1 = mysqli_query($connection, $sql_segment1);

$sql_segment2 = "UPDATE segment_data
				 SET inactive = '1', 
				 	 update_time = '" . mysqli_real_escape_string($connection, $current_timestamp) . "'
				 WHERE domain IN (SELECT domain FROM domains WHERE active IN ('0', '10'))";
$result_segment2 = mysqli_query($connection, $sql_segment2);

$sql_segment3 = "UPDATE segment_data
				 SET missing = '1', 
				 	 update_time = '" . mysqli_real_escape_string($connection, $current_timestamp) . "'
				 WHERE domain NOT IN (SELECT domain FROM domains)";
$result_segment3 = mysqli_query($connection, $sql_segment3);

if ($direct == "1") {

	$_SESSION['result_message'] .= "Segments Updated<BR>";
	
	header("Location: " . $_SERVER['HTTP_REFERER']);
	exit;

} else {
	
	$_SESSION['result_message'] .= "Segments Updated<BR>";

}
