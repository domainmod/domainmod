<?php
// index.php
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

include("../_includes/config.inc.php");
include("../_includes/database.inc.php");
include("../_includes/software.inc.php");
include("../_includes/auth/auth-check.inc.php");

$page_title = "Control Panel";
$software_section = "system";
?>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title><?=$software_title?> :: <?=$page_title?></title>
<?php include("../_includes/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../_includes/header.inc.php"); ?>
&raquo; <a href="display-settings.php">Display Settings</a>&nbsp;&nbsp;/&nbsp;&nbsp;<a href="update-profile.php">Update Profile</a>&nbsp;&nbsp;/&nbsp;&nbsp;<a href="change-password.php">Change Password</a><BR>
<?php if ($_SESSION['session_is_admin'] == 1) { ?>
	<BR><BR><font class="headline">Admin Tools</font><BR><BR>
	&raquo; <a href="admin/system-settings.php">System Settings</a><BR><BR>
	&raquo; <a href="admin/users.php">User List</a>&nbsp;&nbsp;/&nbsp;&nbsp;<a href="admin/add/user.php">Add New User</a><BR>
<?php } ?>
<?php include("../_includes/footer.inc.php"); ?>
</body>
</html>