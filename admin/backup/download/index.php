<?php
/**
 * /admin/backup/download/index.php
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
// Hold back any notices or warnings printed before the download starts, so they can't break the download headers or
// spill the backup into the page
ob_start();

require_once __DIR__ . '/../../../_includes/start-session.inc.php';
require_once __DIR__ . '/../../../_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$log = new DomainMOD\Log('/admin/backup/download/index.php');

require_once DIR_INC . '/debug.inc.php';

$system->authCheck();
$system->checkAdminUser($_SESSION['s_is_admin'] ?? 0);

$download_filename = 'domainmod-backup.sql';

// Remove any files left in the web-accessible temp folder by older versions (a file that can't be deleted is logged)
$system->removeLegacyTempFiles();

use Ifsnop\Mysqldump as IMysqldump;

// Write the dump outside the web root, and always delete it once the request ends
$backup_dir = sys_get_temp_dir();
$backup_file = $system->isInsideWebRoot($backup_dir) ? false : tempnam($backup_dir, 'domainmod-backup-');

if ($backup_file === false || $system->isInsideWebRoot(dirname($backup_file))) {

    if ($backup_file !== false) {

        unlink($backup_file);

    }

    $log_message = sprintf('Unable to backup %s data', SOFTWARE_TITLE);
    $log_extra = array('Error' => 'Unable to create a temporary file outside the web root (PHP temp folder: ' . $backup_dir . ')');
    $log->critical($log_message, $log_extra);

    throw new Exception('Unable to create a temporary backup file');

}

register_shutdown_function(function () use ($backup_file) {

    if (file_exists($backup_file)) {

        unlink($backup_file);

    }

});

try {

    $DumpSettings = array(
        'add-drop-table' => true
    );

    $dump = new IMysqldump\Mysqldump('mysql:host=' . $dbhostname . ';dbname=' . $dbname, $dbusername, $dbpassword, $DumpSettings);
    $dump->start($backup_file);

} catch (Exception $e) {

    $log_message = sprintf('Unable to backup %s data', SOFTWARE_TITLE);
    $log_extra = array('Error' => $e);
    $log->critical($log_message, $log_extra);

    throw $e;

}

while (ob_get_level() > 0) {

    ob_end_clean();

}

header('Content-Type: application/octet-stream');
header("Content-disposition: attachment; filename=" . $download_filename);
header('Content-Length: ' . filesize($backup_file));
readfile($backup_file);
unlink($backup_file);
exit();
