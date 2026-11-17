<?php
/**
 * /admin/dw/rebuild.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2026 Greg Chetcuti <greg@chetcuti.com>
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
require_once __DIR__ . '/../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$system = new DomainMOD\System();
$layout = new DomainMOD\Layout();
$time = new DomainMOD\Time();
$dw = new DomainMOD\DwBuild();

require_once DIR_INC . '/head.inc.php';
require_once DIR_INC . '/config-demo.inc.php';
require_once DIR_INC . '/debug.inc.php';
require_once DIR_INC . '/settings/dw-rebuild.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'] ?? 0);
$csrf = new DomainMOD\Csrf();
$csrf->check($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/');
$system->readOnlyCheck($_SERVER['HTTP_REFERER'] ?? WEB_ROOT . '/'); // read-only users can't delete or change data

// The build runs before anything is drawn, then the Data Warehouse page shows how it went: a success as a toast, and
// anything else as an alert
if (DEMO_INSTALLATION == false) {

    try {

        $result = $dw->buildResult();

    } catch (Throwable $e) {

        // Always in PHP's error log, and in DomainMOD's own log when debugging is on (unless the database is what failed,
        // which still leaves the message below)
        error_log('DomainMOD: Unable to rebuild the Data Warehouse: ' . $e->getMessage());
        try {
            $log = new DomainMOD\Log('/admin/dw/rebuild.php');
            $log->critical('Unable to rebuild the Data Warehouse', array('Error' => $e->getMessage()));
        } catch (Throwable $log_error) {
            error_log('DomainMOD: Unable to log the Data Warehouse rebuild error: ' . $log_error->getMessage());
        }
        $result = array('status' => 'error', 'message' => _('There was a problem rebuilding the Data Warehouse.'));

    }

} else {

    $result = array('status' => 'success', 'message' => _('Data Warehouse Rebuilt.'));

}

if ($result['status'] === 'success') {

    $_SESSION['s_message_success'] .= $result['message'] . '<BR>';

} else {

    $_SESSION['s_message_danger'] .= $result['message'] . '<BR>';

}

header("Location: dw.php");
exit;
