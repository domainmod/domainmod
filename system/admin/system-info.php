<?php
/**
 * /system/admin/system-info.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (C) 2010-2015 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: http://chetcuti.com
 *
 * DomainMOD is free software: you can redistribute it and/or modify it under the terms of the GNU General Public
 * License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later
 * version.
 *
 * DomainMOD is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with DomainMOD. If not, see
 * http://www.gnu.org/licenses/.
 *
 */
?>
<?php
include("../../_includes/start-session.inc.php");

// If the user isn't an administrator, redirect them to $full_redirect
$full_redirect = "../../invalid.php";
include("../../_includes/auth/admin-user-check.inc.php");

include("../../_includes/config.inc.php");
include("../../_includes/database.inc.php");
include("../../_includes/software.inc.php");
include("../../_includes/timestamps/current-timestamp.inc.php");
include("../../_includes/auth/auth-check.inc.php");

$page_title = "System Information";
$software_section = "admin-system-info";
?>
<?php include("../../_includes/doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title; ?> :: <?php echo $page_title; ?></title>
<?php include("../../_includes/layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include("../../_includes/layout/header.inc.php"); ?>
<?php
$sql = "SELECT db_version
		FROM settings";
$result = mysqli_query($connection, $sql) or die(mysqli_error());
while ($row = mysqli_fetch_object($result)) {
	$db_version = $row->db_version;
}
?>
<strong>Operating System:</strong> <?php echo php_uname();; ?><BR>
<strong>Web Server:</strong> <?php echo $_SERVER['SERVER_SOFTWARE']; ?><BR>
<strong>PHP:</strong> <?php echo phpversion(); ?><BR>
<strong>MySQL:</strong> <?php echo mysqli_get_server_info($connection); ?><BR>
<strong>DomainMOD DB:</strong> <?php echo number_format($db_version, 4); ?><BR>
<?php include("../../_includes/layout/footer.inc.php"); ?>
</body>
</html>
