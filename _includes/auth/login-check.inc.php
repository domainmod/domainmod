<?php
// login-check.inc.php
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
?><?php
session_start();

if ($_SESSION['session_is_logged_in'] == 1) {

    if (isset($_SESSION['session_running_login_checks'])) {
		header("Location: /_includes/auth/login-checks/main.inc.php");
	    exit;
	}
	
	if ($web_root == "/") {
		header("Location: /domains.php");
	} else {
		header("Location: " . $web_root . "/domains.php");
	}
    exit;
}
?>