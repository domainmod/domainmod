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
include("../../_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$error = new DomainMOD\Error();
$system = new DomainMOD\System();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();
$system->checkAdminUser();

$page_title = "System Information";
$software_section = "admin-system-info";
?>
<?php echo $system->doctype(); ?>
<html>
<head>
<title><?php echo $system->pageTitle($software_title, $page_title); ?></title>
<?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body>
<?php include(DIR_INC . "layout/header.inc.php"); ?>
<?php
$query = "SELECT db_version
          FROM settings";
$q = $conn->stmt_init();

if ($q->prepare($query)) {

    $q->execute();
    $q->store_result();
    $q->bind_result($db_version);
    $q->fetch();
    $q->close();

} else { $error->outputSqlError($conn, "ERROR"); }
?>
<strong>Operating System:</strong> <?php echo php_uname(); ?><BR>
<strong>Web Server:</strong> <?php echo $_SERVER['SERVER_SOFTWARE']; ?><BR>
<strong>PHP:</strong> <?php echo phpversion(); ?><BR>
<strong>MySQL:</strong> <?php echo mysqli_get_server_info($connection); ?><BR>
<strong>DomainMOD DB:</strong> <?php echo number_format($db_version, 4); ?><BR>
<?php include(DIR_INC . "layout/footer.inc.php"); ?>
</body>
</html>
