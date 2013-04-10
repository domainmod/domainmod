<?php
// /_includes/system/update-tlds.inc.php
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

include($_SESSION['full_server_path'] . "/_includes/config.inc.php");
include($_SESSION['full_server_path'] . "/_includes/database.inc.php");
include($_SESSION['full_server_path'] . "/_includes/software.inc.php");
include($_SESSION['full_server_path'] . "/_includes/auth/auth-check.inc.php");
include($_SESSION['full_server_path'] . "/_includes/timestamps/current-timestamp.inc.php");

$sql = "SELECT id, domain 
		FROM domains 
		ORDER BY domain asc";
$result = mysql_query($sql,$connection);

while ($row = mysql_fetch_object($result)) {
	
	$tld = preg_replace("/^((.*?)\.)(.*)$/", "\\3", $row->domain);
	
	$sql2 = "UPDATE domains
			 SET tld = '$tld',
			 	 update_time = '$current_timestamp'
			 WHERE id = '$row->id'";
	$result2 = mysql_query($sql2,$connection);

}
?>