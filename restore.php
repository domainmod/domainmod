<?php
/**
 * /restore.php
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
<?php //@formatter:off
/*
 * Restores a backup from the command line, for backups too large for the restore page's upload, and finishes a restore
 * that died part-way when nobody can sign in. It does exactly what the restore page does (see
 * DomainMOD\RestoreRecovery), with no upload limit. Run it as the web server's user, with the cron job stopped:
 *
 *     php restore.php <backup file> --temp-dir=<private folder outside the web root>
 *     php restore.php --recover          (finish a restore that died part-way, or remove its staged copy)
 *     php restore.php --forget-staged    (forget a staged copy that's already been deleted by hand)
 */
require_once __DIR__ . '/_includes/init.inc.php';
require_once DIR_INC . '/config.inc.php';
require_once DIR_INC . '/software.inc.php';
require_once DIR_ROOT . '/vendor/autoload.php';

if (PHP_SAPI !== 'cli') {

    http_response_code(403);
    echo sprintf(_('This script can only be run from the command line. To restore from your browser, sign in to %s as an administrator.'), SOFTWARE_TITLE);
    exit;

}

$say = function ($message) {
    echo $message . "\n";
};

list($mode, $file, $temp_dir) = DomainMOD\RestoreRecovery::cliArguments(array_slice($argv, 1));
$usage = _('Usage: php restore.php <backup file> --temp-dir=<folder>, php restore.php --recover, or php restore.php --forget-staged');

if ($mode === 'alone') {

    $say(_('--recover and --forget-staged each run on their own, with no backup file or --temp-dir.'));
    $say($usage);
    exit(2);

}
if ($mode === 'usage') {

    $say($usage);
    exit(2);

}

// A restore, its recovery, and forgetting a staged copy all keep or remove files in temp/, so none of them runs while
// this user can't write there
if (!DomainMOD\RestoreLock::restoreReady()) {
    $say(DomainMOD\RestoreLock::restoreMessage(false));
    exit(1);
}

if (!DomainMOD\RestoreRecovery::canRecoverHere()) {

    // The check that failed: the lock file, or the restore's own files, which have to be readable here
    $unreadable = '';
    foreach (array('result' => 'temp/restore.result.php', 'staged' => 'temp/restore.staged.php', 'flag' => 'temp/restore.flag') as $name => $shown) {
        clearstatcache();
        if (is_file(domainmod_restore_path($name)) && !is_readable(domainmod_restore_path($name))) $unreadable = $shown;
    }
    clearstatcache();
    if ($unreadable !== '') {
        $say(sprintf(_("Can't read %s. Run this as the web server's user."), $unreadable));
    } elseif (!is_writable(DIR_TEMP)) {
        $say(_("Can't write to DomainMOD's temp folder. Make it writable by the web server's user, then try again."));
    } else {
        $say(_("Can't open temp/restore.lock for writing. Run this as the web server's user."));
    }
    exit(1);

}

$kind = DomainMOD\RestoreRecovery::KIND_CLI;

if ($mode === 'forget') {

    // Asked before the lock is taken, so a prompt left open never holds DomainMOD up, and checked again under the lock
    $record = DomainMOD\RestoreRecovery::stagedRecord();
    if ($record === null) {
        $say(_('There is no staged backup copy to forget.'));
        exit(0);
    }
    $say(sprintf(_('This forgets the staged backup copy at %s without deleting it. Only do this if the file is already gone. Type yes to go on:'), $record['path'] ?? ''));
    if (trim((string) fgets(STDIN)) !== 'yes') {
        $say(_('Nothing was changed.'));
        exit(1);
    }
    $forget_path = $record['path'] ?? '';

}

$lock = DomainMOD\RestoreLock::exclusive(30);
if (($lock['reason'] ?? '') === 'database') {

    $say(domainmod_restore_db_unreachable_message('restore'));
    exit(1);

}
if ($lock['state'] === 'error') {

    // A lock that can't be taken at all (such as a file system that refuses flock()) won't come free by trying again
    $say(domainmod_restore_lock_error_message());
    exit(1);

}
if ($lock['state'] !== 'held') {

    $say(DomainMOD\RestoreLock::message($lock['flag']));
    exit(1);

}
// The lock file too, which no lost database connection can take away while the import runs
if (!domainmod_restore_holds_file_exclusive()) {

    $say(DomainMOD\RestoreLock::restoreMessage(false));
    exit(1);

}


if ($mode === 'forget') {

    $record = DomainMOD\RestoreRecovery::stagedRecord();
    if ($record === null || ($record['path'] ?? '') !== $forget_path) {
        $say(_('The staged backup copy changed while you were answering, so nothing was changed.'));
        exit(1);
    }
    $forgotten = DomainMOD\RestoreRecovery::forgetStaged();
    $say($forgotten ? _('The staged backup copy was forgotten.') : _("The staged backup copy's record couldn't be removed."));
    exit($forgotten ? 0 : 1);

}

if ($mode === 'recover') {

    $had_flag = DomainMOD\RestoreRecovery::flagId() !== '';
    $had_staged = DomainMOD\RestoreRecovery::stagedRecord() !== null;
    $recovery = DomainMOD\RestoreRecovery::recover($kind);
    foreach ($recovery['notices'] as $notice) $say($notice);
    if (!$recovery['ok']) {
        $say(_("The restore couldn't be finished. Please check the server's error log, then try again."));
        exit(1);
    }
    if ($had_flag) $say(_('The restore was finished.'));
    // Its clean-up is done, but an import that wasn't confirmed finished may have left the database partly restored
    if ($had_flag && empty($recovery['imported'])) {
        $say(_('Your database may now be partly restored, so restore a complete backup before going on.'));
    }
    // A staged copy that's still there was named in a notice above
    if (DomainMOD\RestoreRecovery::stagedRecord() !== null) exit(1);
    if (!$had_flag) $say($had_staged ? _('The staged backup copy was removed.') : _('Nothing was left to finish.'));
    exit(0);

}

// The staged copy goes in a private folder the operator names, never inside DomainMOD's own folder
$temp_real = realpath($temp_dir);
$root_real = realpath(DIR_ROOT);
if ($temp_real === false || !is_dir($temp_real) || !is_writable($temp_real)) {

    $say(_("The --temp-dir folder doesn't exist or can't be written to."));
    exit(1);

}
if ($temp_real === $root_real || strpos($temp_real . DIRECTORY_SEPARATOR, $root_real . DIRECTORY_SEPARATOR) === 0) {

    $say(_("The --temp-dir folder can't be inside DomainMOD's folder. Use a private folder outside the web root."));
    exit(1);

}
if (!is_file($file) || !is_readable($file)) {

    $say(_('The backup file could not be read.'));
    exit(1);

}

$deeb = DomainMOD\Database::getInstance();
$restore = DomainMOD\RestoreRecovery::restore($file, $temp_real, $kind, function ($copy) use ($deeb, $dbusername, $dbpassword, $dbname, $dbhostname) {
    new DomainMOD\RestoreImport($copy, $dbusername, $dbpassword, $dbname, $dbhostname, $deeb->connection_options, $deeb->certificate_checked);
});

foreach ($restore['notices'] as $notice) $say($notice);
if ($restore['error'] !== '') {

    foreach (DomainMOD\RestoreRecovery::failureLines($restore, false) as $line) $say($line);
    exit(1);

}

$say(_('The restore finished.'));
exit(0); //@formatter:on
