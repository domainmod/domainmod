<?php
/**
 * /checks.php
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

$login = new DomainMOD\Login();
$maint = new DomainMOD\Maintenance();
$system = new DomainMOD\System();
$time = new DomainMOD\Timestamp();

include(DIR_INC . "head.inc.php");
include(DIR_INC . "config.inc.php");
include(DIR_INC . "software.inc.php");
include(DIR_INC . "database.inc.php");

$system->authCheck();

$upgrade_approved = $_GET['u'];

$_SESSION['running_login_checks'] = 1;

/*
 * If the database and software versions are different and the user hasn't already approved the upgrade, send them to
 * upgrade.php where it asks the user to confirm the upgrade
 *
 * If If the database and software versions are different and the user HAS approved the upgrade, perform the upgrade
 */
if ($_SESSION['system_db_version'] !== $software_version && $upgrade_approved != '1') {

    header("Location: notice.php?a=u");
    exit;

} elseif ($_SESSION['system_db_version'] !== $software_version && $upgrade_approved == '1') {

    include(DIR_INC . "update.inc.php");
    $_SESSION['is_upgrading'] = '1';

} else {

    $_SESSION['needs_database_upgrade'] = "0";

}

// Check GitHub to see if a newer version is available
$system->checkVersion($software_version);

// Check for existing Domain and SSL assets
$system->checkExistingAssets($connection);

unset($_SESSION['running_login_checks']);

unset($_SESSION['installation_mode']);

$login->setLastLogin($connection, $_SESSION['user_id'], $_SESSION['email_address']);

$_SESSION['last_login'] = $time->time();

if ($_SESSION['version_error'] != '1') {

    if ($_SESSION['system_new_version'] == '1') {

        if ($_SESSION['is_admin'] === 1) {

            $_SESSION['result_message'] .= "A new version of DomainMOD is available for download. <a target=\"_blank\" href=\"http://domainmod.org/upgrade/\">Click
here for upgrade instructions</a>.<BR>";

        }

    }

    $queryB = new DomainMOD\QueryBuild();

    $sql = $queryB->missingFees('domains');
    $_SESSION['missing_domain_fees'] = $system->checkForRows($connection, $sql);

    $queryB = new DomainMOD\QueryBuild();

    $sql = $queryB->missingFees('ssl_certs');
    $_SESSION['missing_ssl_fees'] = $system->checkForRows($connection, $sql);

    if ($_SESSION['is_new_password'] == 1) {

        $_SESSION['result_message'] .= "Your password should be changed for security purposes<BR>";
        header("Location: system/change-password.php");
        exit;

    }

}

unset($_SESSION['is_upgrading']);

if (isset($_SESSION['user_redirect'])) {

    $temp_redirect = $_SESSION['user_redirect'];
    unset($_SESSION['user_redirect']);

    header("Location: $temp_redirect");
    exit;

} else {

    header("Location: domains.php");

    exit;

}
