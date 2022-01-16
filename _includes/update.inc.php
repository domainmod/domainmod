<?php
/**
 * /_includes/update.inc.php
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
$current_db_version = $system->getDbVersion();

$previous_version = $current_db_version;

if ($current_db_version < SOFTWARE_VERSION) {

    if ($current_db_version >= '1.1' && $current_db_version < '2.0022') {

        require_once 'updates/1.1-2.0022.inc.php';

    }

    if ($current_db_version >= '2.0022' && $current_db_version < '2.0038') {

        require_once 'updates/2.0022-2.0038.inc.php';

    }

    if ($current_db_version >= '2.0038' && $current_db_version < '2.0048') {

        require_once 'updates/2.0038-2.0048.inc.php';

    }

    if ($current_db_version >= '2.0048' && $current_db_version < '3.0.1') {

        require_once 'updates/2.0048-3.0.1.inc.php';

    }

    if ($current_db_version >= '3.0.1' && $current_db_version < '4.00.000') {

        require_once 'updates/3.0.1-4.00.000.inc.php';

    }

    if ($current_db_version >= '4.00.000' && $current_db_version < '4.02.000') {

        require_once 'updates/4.00.000-4.02.000.inc.php';

    }

    if ($current_db_version >= '4.02.000' && $current_db_version < '4.06.00') {

        require_once 'updates/4.02.000-4.06.00.inc.php';

    }

    if ($current_db_version >= '4.06.00') {

        require_once 'updates/4.06.00-current.inc.php';

    }

    $_SESSION['s_system_db_version'] = $current_db_version;

    $_SESSION['s_system_upgrade_available'] = '0';

    $pdo->query("
        UPDATE settings
        SET upgrade_available = '0'");

    $_SESSION['s_message_success'] .= _('Your database has been upgraded') . '<BR>';
    $message_success_cli = _('Your database has been upgraded') . "\r\n\r\n";

} elseif ($current_db_version > SOFTWARE_VERSION) {

    $_SESSION['s_message_danger'] .= _('The upgrade process cannot be completed, as your versions are currently out-of-sync.') .
    _('The software on your server is older than your database version. This should never happen.') . "<BR><BR>" . sprintf(_('Please logout and log back in, and if the problem persists please contact your %s administrator.'), SOFTWARE_TITLE) . "<BR>";
    $message_danger_cli = _('The upgrade process cannot be completed, as your versions are currently out-of-sync.') . ' ' . _('The software on your server is older than your database version. This should never happen.') . "\r\n\r\n"; sprintf(_('Please logout and log back in, and if the problem persists please contact your %s administrator.'), SOFTWARE_TITLE) . "\r\n\r\n";

    $_SESSION['s_version_error'] = '1';

} else {

    $_SESSION['s_message_success'] .= _('Your Database is already up-to-date') . '<BR>';
    $message_success_cli = "Your Database is already up-to-date\r\n\r\n";

}
