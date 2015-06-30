<?php
/**
 * /_includes/update.inc.php
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
$sql = "SELECT db_version
        FROM settings";
$result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

while ($row = mysqli_fetch_object($result)) {
    $current_db_version = (string) $row->db_version;
}

if ($current_db_version < $software_version) {

    if ($current_db_version >= '1.1' && $current_db_version <= '2.0056') {

        include('updates/1.1-2.0057.inc.php');

    }

    include('updates/2.0057-current.inc.php');

    $_SESSION['system_upgrade_available'] = "0";

    $sql = "UPDATE settings
            SET upgrade_available = '0'";
    $result = mysqli_query($connection, $sql) or $error->outputOldSqlError($connection);

    $_SESSION['result_message'] .= "Your Database Has Been Updated<BR>";

} elseif ($current_db_version > $software_version) {

    $_SESSION['result_message'] .= "Your versions are currently out-of-sync. The software on your server is older than
        your database version. This should not happen. Please logout and log back in, and if the problem persists
        please contact your " . $software_title . " administrator<BR>";

} else {

    $_SESSION['result_message'] .= "Your Database is already up-to-date<BR>";

}
