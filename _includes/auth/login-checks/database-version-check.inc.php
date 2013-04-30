<?php
// /_includes/auth/login-checks/database-version-check.inc.php
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
$sql_db_check = "SELECT db_version
				FROM settings";
$result_db_check = mysql_query($sql_db_check,$connection) or die(mysql_error());

while ($row_db_check = mysql_fetch_object($result_db_check)) {

	if ($row_db_check->db_version != $most_recent_db_version) { 
	
		include($_SESSION['full_server_path'] . "/_includes/system/update-database.inc.php");
		$_SESSION['run_update_includes'] = "1";

	} else {

		$_SESSION['needs_database_upgrade'] = "0";

	}

}
?>