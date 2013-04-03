<?php
// new-password-check.inc.php
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

include("../../database.inc.php");

$sql = "SELECT new_password 
		FROM users
		WHERE id = '" . $_SESSION['session_user_id'] . "' 
		  AND email_address = '" . $_SESSION['session_email_address'] . "'";
$result = mysql_query($sql,$connection) or die(mysql_error());

while ($row = mysql_fetch_object($result)) { $is_it_a_new_password = $row->new_password; }

if ($is_it_a_new_password == 1) {

	$_SESSION['session_result_message'] = "Since your password was recently generated you are required to change it for security purposes.<BR>";

	header("Location: ../../../system/change-password.php");
	exit;

}
?>