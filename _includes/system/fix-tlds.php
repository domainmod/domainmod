<?php
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

include("../config.inc.php");
include("../database.inc.php");
include("../software.inc.php");
include("../auth/auth-check.inc.php");
include("../timestamps/current-timestamp-basic.inc.php");

$sql = "select id, domain from domains order by domain asc";
$result = mysql_query($sql,$connection);

while ($row = mysql_fetch_object($result)) {
	
	$tld = preg_replace("/^((.*?)\.)(.*)$/", "\\3", $row->domain);
	
	$sql2 = "update domains
			 set tld = '$tld',
			 update_time = '$current_timestamp'
			 where id = '$row->id'";
	$result2 = mysql_query($sql2,$connection);

}

$_SESSION['session_result_message'] = "All TLDs Have Been Fixed<BR>";

header("Location: ../../system/index.php");
exit;
?>