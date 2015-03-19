<?php
// /_includes/system/installation-check.inc.php
// 
// DomainMOD is an open source application written in PHP & MySQL used to track and manage your web resources.
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
$full_install_path = $full_server_path . "/install/";

if (is_dir($full_install_path)) {

	if(!mysqli_num_rows( mysqli_query($connection, "SHOW TABLES LIKE '" . categories . "'")) || !mysqli_num_rows( mysqli_query($connection, "SHOW TABLES LIKE '" . owners . "'")) || !mysqli_num_rows( mysqli_query($connection, "SHOW TABLES LIKE '" . currencies . "'")) || !mysqli_num_rows( mysqli_query($connection, "SHOW TABLES LIKE '" . dns . "'")) || !mysqli_num_rows( mysqli_query($connection, "SHOW TABLES LIKE '" . domains . "'")) || !mysqli_num_rows( mysqli_query($connection, "SHOW TABLES LIKE '" . fees . "'")) || !mysqli_num_rows( mysqli_query($connection, "SHOW TABLES LIKE '" . registrars . "'")) || !mysqli_num_rows( mysqli_query($connection, "SHOW TABLES LIKE '" . registrar_accounts . "'")) || !mysqli_num_rows( mysqli_query($connection, "SHOW TABLES LIKE '" . segments . "'")) || !mysqli_num_rows( mysqli_query($connection, "SHOW TABLES LIKE '" . ssl_accounts . "'")) || !mysqli_num_rows( mysqli_query($connection, "SHOW TABLES LIKE '" . ssl_certs . "'")) || !mysqli_num_rows( mysqli_query($connection, "SHOW TABLES LIKE '" . ssl_fees . "'")) || !mysqli_num_rows( mysqli_query($connection, "SHOW TABLES LIKE '" . ssl_providers . "'")) || !mysqli_num_rows( mysqli_query($connection, "SHOW TABLES LIKE '" . users . "'")) || !mysqli_num_rows( mysqli_query($connection, "SHOW TABLES LIKE '" . settings . "'"))) {
		
		$_SESSION['installation_mode'] = 1;
		$_SESSION['first_login'] = 1;

		if ($web_root == "") {

			$_SESSION['result_message'] .= "<a href=\"/install/\">Please click here to install</a><BR>";

		} else {

			$_SESSION['result_message'] .= "<a href=\"" . $web_root . "/install/\">Please click here to install</a><BR>";

		}

	} else {

		$_SESSION['installation_mode'] = 0;

	}
	
}
?>
