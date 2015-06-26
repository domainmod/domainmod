<?php
/**
 * /raw-list.php
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
include("_includes/start-session.inc.php");
include("_includes/init.inc.php");

require_once(DIR_ROOT . "classes/Autoloader.php");
spl_autoload_register('DomainMOD\Autoloader::classAutoloader');

$system = new DomainMOD\System();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck($web_root);

if ($_SESSION['raw_list_type'] == 'domains') {

    $page_title = "Domains (Raw List)";
    $software_section = "domains";

} elseif ($_SESSION['raw_list_type'] == 'ssl-certs') {

    $page_title = "SSL Certificates (Raw List)";
    $software_section = "ssl-certs";

}
?>
<?php include(DIR_INC . "doctype.inc.php"); ?>
<html>
<head>
<title><?php echo $software_title . " :: " . $page_title; ?></title>
<?php include(DIR_INC . "layout/head-tags.inc.php"); ?>
</head>
<body>
<?php
$result = mysqli_query($connection, $_SESSION['raw_list_query']);

if ($_SESSION['raw_list_type'] == 'domains') {

    while ($row = mysqli_fetch_object($result)) {

        echo $row->domain . "<BR>";

    }

} elseif ($_SESSION['raw_list_type'] == 'ssl-certs') {

    while ($row = mysqli_fetch_object($result)) {

        echo $row->name . "<BR>";

    }

}
?>
</body>
</html>
