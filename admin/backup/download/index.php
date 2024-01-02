<?php
/**
 * /admin/backup/download/index.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2024 Greg Chetcuti <greg@chetcuti.com>
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
require_once __DIR__ . '/../../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$log = new DomainMOD\Log('/admin/backup/download/index.php');

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'] ?? 0);

$download_filename = 'domainmod-backup.sql';

use Ifsnop\Mysqldump as IMysqldump;

try {

    $DumpSettings = array(
        'add-drop-table' => true
    );

    if (file_exists(DIR_TEMP . '/' . $download_filename)) {

        unlink(DIR_TEMP . '/' . $download_filename);

    }

    $dump = new IMysqldump\Mysqldump('mysql:host=' . $dbhostname . ';dbname=' . $dbname, $dbusername, $dbpassword, $DumpSettings);
    $dump->start(DIR_TEMP . '/' . $download_filename);

} catch (Exception $e) {

    $log_message = sprintf('Unable to backup %s data', SOFTWARE_TITLE);
    $log_extra = array('Error' => $e);
    $log->critical($log_message, $log_extra);

    throw $e;

}

header('Content-Type: application/octet-stream');
header("Content-disposition: attachment; filename=" . $download_filename);
readfile(DIR_TEMP . '/' . $download_filename);
exit();
