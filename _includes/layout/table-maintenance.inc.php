<?php
/**
 * /_includes/layout/table-maintenance.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>
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
if ($_SESSION['s_missing_domain_fees'] == 1) {

    $message = "Some of your Registrars/TLDs are missing domain fees. <a href=\"" . $web_root . "/assets/registrar-fees-missing.php\">Click here to fix this</a>.<BR>If you've already updated all new TLDs, you should <a href=\"" . $web_root . "/maintenance/update-domain-fees.php\">update the domain fees system-wide</a> (this may take some time).";

    echo $system->showMaintenanceTable($message);

}

if ($_SESSION['s_missing_ssl_fees'] == 1) {

    $message = "Some of your SSL Certificate Types are missing fees. <a href=\"" . $web_root . "/assets/ssl-provider-fees-missing.php\">Click here to fix this</a>.<BR>If you've already updated all new SSL Types, you should <a href=\"" . $web_root . "/maintenance/update-ssl-fees.php\">update the SSL fees system-wide</a> (this may take some time).";

    echo $system->showMaintenanceTable($message);

}

if (DEBUG_MODE == 1) {

    $message = "Debugging mode is currently enabled. <a href='" . WEB_ROOT . "/admin/debug-log/'>View Debug Log</a><BR><BR>";

    if ($_SESSION['s_is_admin'] == 1) {

        $message .= "You can disable debugging mode in <a href='" . $web_root . "/admin/settings/'>Settings</a>.";

    } else {

        $message .= "Please contact your " . SOFTWARE_TITLE . " administrator to disable debugging.";

    }

    echo $system->showDebugTable($message);

}
