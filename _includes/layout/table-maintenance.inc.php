<?php
/**
 * /_includes/layout/table-maintenance.inc.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2025 Greg Chetcuti <greg@greg.ca>
 *
 * Project: http://domainmod.org   Author: https://greg.ca
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
$missing_domain_fees = $_SESSION['s_missing_domain_fees'] ?? '';
if ($missing_domain_fees == 1) {

    $message = _('Some of your Domains are missing fees. Until you enter the fees, the Domains will not be visible in DomainMOD.') . " <a href=\"" . $web_root . "/assets/registrar-fees-missing.php\">" . _('Click here to fix this') . "</a>.<BR>";
    $message .= _('If you don\'t care about tracking Domain fees, ') . " <a href=\"" . $web_root . "/assets/zero-out-domain-fees.php\">" . _('click here to set all missing fees to 0') . "</a>.<BR>";
    $message .= sprintf(_("If you've already updated all new fees, you should %supdate the Domain fees system-wide%s. This may take some time."), "<a href='" . $web_root . "/maintenance/update-domain-fees.php'>", '</a>');

    echo $system->showMaintenanceTable($message);

}

$missing_ssl_fees = $_SESSION['s_missing_ssl_fees'] ?? '';
if ($missing_ssl_fees == 1) {

    $message = _('Some of your SSL Certificates are missing fees. Until you enter the fees, the SSL Certificates will not be visible in DomainMOD.') . " <a href=\"" . $web_root . "/assets/ssl-provider-fees-missing.php\">" . _('Click here to fix this') . "</a>.<BR>";
    $message .= _('If you don\'t care about tracking SSL Certificate fees, ') . " <a href=\"" . $web_root . "/assets/zero-out-ssl-fees.php\">" . _('click here to set all missing fees to 0') . "</a>.<BR>";
    $message .= sprintf(_("If you've already updated all new fees, you should %supdate the SSL Certificate fees system-wide%s. This may take some time."), "<a href='" . $web_root . "/maintenance/update-ssl-fees.php'>", '</a>');

    echo $system->showMaintenanceTable($message);

}

if (DEBUG_MODE == 1) {

    $message = _('Debugging mode is currently enabled.') . " <a href=\"" . $web_root . "/admin/debug-log/\">" . _('View Debug Log') . "</a><BR><BR>";

    $_SESSION['s_is_admin'] = $_SESSION['s_is_admin'] ?? 0;
    if ($_SESSION['s_is_admin'] == 1) {

        $message .= sprintf(_('You can disable debugging mode in %sSettings%s.'), " <a href='" . $web_root . "/admin/settings/'>", "</a>");


    } else {

        $message .= sprintf(_('Please contact your %s administrator to disable debugging'), SOFTWARE_TITLE);

    }

    echo $system->showDebugTable($message);

}
