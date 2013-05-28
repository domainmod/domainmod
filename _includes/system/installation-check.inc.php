<?php
// /_includes/system/installation-check.inc.php
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
$full_install_path = $full_server_path . "/install/";

if (is_dir($full_install_path)) {

	if(!mysql_num_rows( mysql_query("SHOW TABLES LIKE '" . categories . "'")) || !mysql_num_rows( mysql_query("SHOW TABLES LIKE '" . owners . "'")) || !mysql_num_rows( mysql_query("SHOW TABLES LIKE '" . currencies . "'")) || !mysql_num_rows( mysql_query("SHOW TABLES LIKE '" . dns . "'")) || !mysql_num_rows( mysql_query("SHOW TABLES LIKE '" . domains . "'")) || !mysql_num_rows( mysql_query("SHOW TABLES LIKE '" . fees . "'")) || !mysql_num_rows( mysql_query("SHOW TABLES LIKE '" . registrars . "'")) || !mysql_num_rows( mysql_query("SHOW TABLES LIKE '" . registrar_accounts . "'")) || !mysql_num_rows( mysql_query("SHOW TABLES LIKE '" . segments . "'")) || !mysql_num_rows( mysql_query("SHOW TABLES LIKE '" . ssl_accounts . "'")) || !mysql_num_rows( mysql_query("SHOW TABLES LIKE '" . ssl_certs . "'")) || !mysql_num_rows( mysql_query("SHOW TABLES LIKE '" . ssl_fees . "'")) || !mysql_num_rows( mysql_query("SHOW TABLES LIKE '" . ssl_providers . "'")) || !mysql_num_rows( mysql_query("SHOW TABLES LIKE '" . users . "'")) || !mysql_num_rows( mysql_query("SHOW TABLES LIKE '" . settings . "'"))) {
		
		$_SESSION['installation_mode'] = 1;
		$_SESSION['first_login'] = 1;

		if ($web_root == "/") {

			$_SESSION['result_message'] .= "<a href=\"/install/\">Please click here to install</a><BR>";

		} else {
			$_SESSION['result_message'] .= "<a href=\"" . $web_root . "/install/\">Please click here to install</a><BR>";
		}

	} else {

		$_SESSION['installation_mode'] = 0;

	}
	
}
?>