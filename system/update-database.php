<?php
// update-database.php
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

// If the user isn't an administrator, redirect them to $full_redirect
$full_redirect = "index.php";
include("../_includes/auth/admin-user-check.inc.php");

include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/timestamps/current-timestamp.inc.php");
include("../_includes/auth/auth-check.inc.php");
$page_title = "Update Database";
$software_section = "system";

$sql = "select db_version
		from settings";
$result = mysql_query($sql,$connection) or die(mysql_error());

while ($row = mysql_fetch_object($result)) {
	$current_db_version = $row->db_version;
}

if ($current_db_version < $most_recent_db_version) {

/*
	// upgrade database from 1.1 to 1.2
	if ($current_db_version == 1.1) {

		$sql = "SQL code to execute";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$sql = "update settings
				set db_version = '1.2'";
		$result = mysql_query($sql,$connection) or die(mysql_error());
		
		$current_db_version = 1.2;
		
	}

*/

	$_SESSION['session_result_message'] .= "Database Updated<BR>";

} else {

	$_SESSION['session_result_message'] .= "Your database is already up-to-date<BR>";
	
}

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
?>