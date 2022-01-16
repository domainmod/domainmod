<?php
/**
 * /_includes/layout/table-maintenance.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2022 Greg Chetcuti <greg@chetcuti.com>
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

    $message = _('Some of your Registrars/TLDs are missing domain fees.') . " <a href=\"" . $web_root . "/assets/registrar-fees-missing.php\">" . _('Click here to fix this') . "</a>.<BR>" . sprintf(_("If you've already updated all new TLDs, you should %supdate the domain fees system-wide%s (this may take some time)"), "<a href='" . $web_root . "/maintenance/update-domain-fees.php'>", '</a>');

    echo $system->showMaintenanceTable($message);

}

if ($_SESSION['s_missing_ssl_fees'] == 1) {

    $message = _('Some of your SSL Certificate Types are missing fees.') . " <a href=\"" . $web_root . "/assets/ssl-provider-fees-missing.php\">" . _('Click here to fix this') . "</a>.<BR>" . sprintf(_("If you've already updated all new SSL Types, you should %supdate the SSL fees system-wide%s (this may take some time)"), "<a href='" . $web_root . "/maintenance/update-ssl-fees.php'>", '</a>');

    echo $system->showMaintenanceTable($message);

}

if (DEBUG_MODE == 1) {

    $message = _('Debugging mode is currently enabled.') . " <a href=\"" . $web_root . "/admin/debug-log/\">" . _('View Debug Log') . "</a><BR><BR>";

    if ($_SESSION['s_is_admin'] == 1) {

        $message .= sprintf(_('You can disable debugging mode in %sSettings%s.'), " <a href='" . $web_root . "/admin/settings/'>", "</a>");


    } else {

        $message .= sprintf(_('Please contact your %s administrator to disable debugging'), SOFTWARE_TITLE);

    }

    echo $system->showDebugTable($message);

}
