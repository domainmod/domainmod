<?php
/**
 * /admin/backup/download/index.php
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
// The administrator flag as the database has it now (see System::authFlags()), not the session's cached one
$system->checkAdminUser($system->authFlags()['admin']);
// The dump never runs without the request's shared restore lock (the bootstrap takes it, see restore-gate.inc.php)
if (domainmod_restore_lock_mode() === '') domainmod_restore_refuse(domainmod_restore_lock_error_message());

$download_filename = 'domainmod-backup.sql';

/*
 * The request's shared restore lock (taken by the bootstrap, see _includes/restore-gate.inc.php) is held until the dump
 * has finished, so a restore can't start part-way through it. Every item type's custom field lock is held for the whole
 * dump too (domain fields, then SSL fields, then hosting fields once the database update adds the hosting tables, each
 * with a short wait), so a field can't be added or deleted while the dump reads the table definitions. If any can't be
 * had, nothing is dumped.
 */
$pdo = $deeb->cnxx;
$custom_field_locks = array();
$custom_field_types = DomainMOD\Hosting::isAvailable($pdo) ? array('domain', 'ssl', 'hosting') : array('domain', 'ssl');
foreach ($custom_field_types as $custom_field_type) {

    if (!DomainMOD\ChangeHistory::lockCustomFields($pdo, $custom_field_type, 5)) {

        foreach ($custom_field_locks as $held_type) DomainMOD\ChangeHistory::unlockCustomFields($pdo, $held_type);
        domainmod_restore_refuse(DomainMOD\RestoreLock::message(false));

    }
    $custom_field_locks[] = $custom_field_type;

}
$backup_version = (string) $system->getDbVersion();

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

    $dump = new IMysqldump\Mysqldump('mysql:host=' . $dbhostname . ';dbname=' . $dbname, $dbusername, $dbpassword, $DumpSettings,
        // Not persistent: PHP's persistent connection cache ignores TLS options, so it could reuse an older connection
        array(PDO::ATTR_PERSISTENT => false) + $deeb->connection_options);
    // The rows Ifsnop itself writes for each table (inside its own snapshot), for the manifest the restore checks
    $table_counts = array();
    $dump->setInfoHook(function ($object, $info) use (&$table_counts) {
        if ($object === 'table') $table_counts[(string) $info['name']] = (int) $info['rowCount'];
    });
    $dump->start($backup_file);
    // The dump read through its own connection, so the lock is checked before the backup is finished: a database lock
    // lost meanwhile (its connection killed) means a restore or an upgrade could have run alongside, and the file is
    // thrown away instead
    if (!domainmod_restore_lock_still_held()) {
        throw new DomainMOD\RestoreLockException(_("The backup couldn't be finished, since DomainMOD's restore lock was lost while it ran. Please try again."));
    }
    DomainMOD\RestoreRecovery::addManifest($backup_file, $table_counts, $backup_version);
    foreach ($custom_field_locks as $held_type) DomainMOD\ChangeHistory::unlockCustomFields($pdo, $held_type);

} catch (Exception $e) {

    // Logging never replaces the backup's own error, even when the database connection was lost
    try {
        $log_message = sprintf('Unable to backup %s data', SOFTWARE_TITLE);
        $log_extra = array('Error' => $e);
        $log->critical($log_message, $log_extra);
    } catch (\Throwable $log_error) {
        // nothing more can be done
    }

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
