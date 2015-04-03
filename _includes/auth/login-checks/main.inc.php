<?php
/**
 * /_includes/auth/login-checks/main.inc.php
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
include("_includes/config.inc.php");
include("_includes/database.inc.php");
include("_includes/software.inc.php");
include("_includes/auth/auth-check.inc.php");
include("_includes/timestamps/current-timestamp.inc.php");

$_SESSION['running_login_checks'] = 1;

// Check the database version
include("_includes/auth/login-checks/database-version-check.inc.php");

// Check if there are Domain and SSL assets
include("_includes/auth/login-checks/domain-and-ssl-asset-check.inc.php");

unset($_SESSION['running_login_checks']);

unset($_SESSION['installation_mode']);

$sql_user_update = "UPDATE users
					SET last_login = '$current_timestamp',
						number_of_logins = number_of_logins + 1,
						update_time = '$current_timestamp'
					WHERE id = '" . $_SESSION['user_id'] . "'
					  AND email_address = '" . $_SESSION['email_address'] . "'";
$result_user_update = mysqli_query($connection, $sql_user_update) or die(mysqli_error());

$_SESSION['last_login'] = $current_timestamp;
?>
