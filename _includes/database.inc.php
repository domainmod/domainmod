<?php
/**
 * /_includes/database.inc.php
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
$connection = mysqli_connect($dbhostname, $dbusername, $dbpassword, $dbname);

if(mysqli_connect_errno()) {
    echo "Database Connection Error: " . mysqli_connect_errno();
    exit();
}

mysqli_query($connection, "SET NAMES UTF8") or trigger_error(htmlentities(mysqli_error($connection)), E_USER_ERROR);
mysqli_query($connection, "SET CHARACTER SET utf8") or trigger_error(htmlentities(mysqli_error($connection)), E_USER_ERROR);
mysqli_query($connection, "SET COLLATION_CONNECTION = utf8_unicode_ci") or trigger_error(htmlentities(mysqli_error($connection)), E_USER_ERROR);
