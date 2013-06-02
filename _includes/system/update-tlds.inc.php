<?php
// /_includes/system/update-tlds.inc.php
// 
// DomainMOD - A web-based application written in PHP & MySQL used to manage a collection of domain names.
// Copyright (C) 2010 Greg Chetcuti
// 
// DomainMOD is free software; you can redistribute it and/or modify it under the terms of the GNU General
// Public License as published by the Free Software Foundation; either version 2 of the License, or (at your
// option) any later version.
// 
// DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the
// implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License
// for more details.
// 
// You should have received a copy of the GNU General Public License along with DomainMOD. If not, please see
// http://www.gnu.org/licenses/
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

$sql = "SELECT id, domain 
		FROM domains 
		ORDER BY domain asc";
$result = mysql_query($sql,$connection);

while ($row = mysql_fetch_object($result)) {
	
	$tld = preg_replace("/^((.*?)\.)(.*)$/", "\\3", $row->domain);
	
	$sql_update = "UPDATE domains
				   SET tld = '$tld'
				   WHERE id = '$row->id'";
	$result_update = mysql_query($sql_update,$connection);

}

if ($direct == "1") {

	$_SESSION['result_message'] .= "TLDs Updated<BR>";
	
	header("Location: " . $_SERVER['HTTP_REFERER']);
	exit;

} else {
	
	$_SESSION['result_message'] .= "TLDs Updated<BR>";

}
?>
