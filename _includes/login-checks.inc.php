<?php
/**
 * /_includes/login-checks.inc.php
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
$system->authCheck($web_root);

$_SESSION['running_login_checks'] = 1;

// Compare database and software versions (to see if a database upgrade is needed)
if ($_SESSION['system_db_version'] != $software_db_version) {

    include(DIR_INC . "update-database.inc.php");
    $_SESSION['run_update_includes'] = "1";

} else {

    $_SESSION['needs_database_upgrade'] = "0";

}

// Check for existing Domain and SSL assets
$system->checkExistingAssets($connection);

unset($_SESSION['running_login_checks']);

unset($_SESSION['installation_mode']);

$login->setLastLogin($connection, $_SESSION['user_id'], $_SESSION['email_address']);

$_SESSION['last_login'] = $time->time();
