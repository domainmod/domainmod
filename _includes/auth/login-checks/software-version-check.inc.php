<?php
/**
 * /_includes/auth/login-checks/software-version-check.inc.php
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
$most_recent_version = file_get_contents('https://raw.githubusercontent.com/aysmedia/domainmod/master/version-db.txt');

if ($_SESSION['system_db_version'] != $most_recent_version) {

    $_SESSION['result_message'] .= "A newer version of DomainMOD is available. <a target=\"_blank\" href=\"http://domainmod.org/upgrade/\">Click here for upgrade instructions</a>.<BR>";

}
