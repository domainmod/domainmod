<?php
/**
 * /classes/DomainMOD/RestoreLock.php
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
//@formatter:off
namespace DomainMOD;

/*
 * The restore lock (temp/restore.lock) and flag (temp/restore.flag) for code that runs after the bootstrap, through the
 * gate's functions in _includes/restore-gate.inc.php, which every entry point loads. A restore or the upgrade holds the
 * lock exclusively, and everything else holds it shared.
 */
class RestoreLock
{
    // A stand-in for the gate's lock, for the unit tests, which run without the bootstrap
    public static $test_state = null;

    // Takes (or confirms) the shared lock, as array('state' => 'held', 'busy', or 'error', 'flag' => bool)
    public static function shared()
    {
        if (self::$test_state !== null) return self::$test_state;
        if (!function_exists('domainmod_restore_lock')) return array('state' => 'held', 'flag' => false);
        return domainmod_restore_lock(false);
    }

    // Takes the lock exclusively, waiting up to $wait seconds
    public static function exclusive($wait = 0)
    {
        if (self::$test_state !== null) return self::$test_state;
        return domainmod_restore_lock(true, $wait);
    }

    // The restore message when the flag exists, otherwise the busy message
    public static function message($flag)
    {
        return function_exists('domainmod_restore_message') ? domainmod_restore_message($flag)
            : ($flag ? _('A restore is in progress. Please try again in a minute.')
                : _('DomainMOD is busy. Please try again in a minute.'));
    }

    /*
     * Checks temp/restore.lock once the upgrade or the installer holds the lock, returning '' or what's wrong (which
     * doesn't stop them, since the database lock stands in for a file that can't be used). The file is created by the
     * exclusive holder itself (see _includes/restore-gate.inc.php). A file owned by another user is never replaced (a
     * running restore could still hold it), and on the web a wrong mode is set to 0664. On the command line nothing is
     * changed here, and a file owned by another user is reported (the upgrade still runs, under the database lock and
     * the file lock). Either way it then runs a real test rather than trusting file metadata: it opens the lock file for
     * writing and creates and deletes a test file in temp/, which also catches a read-only mount.
     */
    public static function prepareFile($web)
    {
        $path = DIR_TEMP . '/restore.lock';
        $cli_message = _("Run php update.php as the web server's user, or use Upgrade Database in a browser.");
        $temp_message = _('DomainMOD needs to write to its temp folder. Make it writable by the web server, then try again.');
        $owner_message = _("temp/restore.lock belongs to another user. Change its owner to the web server's user, then try again.");
        $posix = function_exists('posix_geteuid');
        clearstatcache();

        // The file is created only by an exclusive holder, while it holds the database lock (see
        // _includes/restore-gate.inc.php), so a missing one is left for that
        if (!is_file($path)) {
            return $web ? $temp_message : $cli_message;
        } elseif ($posix && fileowner($path) !== posix_geteuid()) {
            return $web ? $owner_message : $cli_message;
        } elseif (!$web && !$posix) {
            return $cli_message;
        }

        clearstatcache();
        if ((fileperms($path) & 0777) !== 0664) {
            if (!$web || !@chmod($path, 0664)) return $web ? $temp_message : $cli_message;
        }

        if (!self::fileTest()) return $web ? $temp_message : $cli_message;
        return '';
    }

    /*
     * The upgrade's lock, taken by both entry points (Upgrade Database in checks.php, and php update.php) before their
     * first database read: the lock is taken exclusively (creating the lock file where temp/ can be written), waiting up
     * to 30 seconds for tasks and saves already running, the file is checked straight away (see prepareFile()), and the
     * lock is held through every version block, so no restore, item write, or task runs alongside a migration. A lock file that can't be prepared doesn't stop the
     * upgrade: the database lock stands in for it (see _includes/restore-gate.inc.php), and administrators are told to
     * make temp/ writable. The upgrade never finishes a restore itself. Returns '' or the message to stop with, before
     * any change.
     */
    public static function forUpgrade($web)
    {
        // The lock first (an exclusive holder creates the lock file where it can, while holding the database lock), then
        // the file's mode and a real test, which can't publish a file nobody holds
        $lock = self::exclusive(30);
        if ($lock['state'] === 'held' && version_compare(SOFTWARE_VERSION, ChangeHistory::FIRST_VERSION, '>=')) self::prepareFile($web);
        if ($lock['flag']) {
            return _("A restore didn't finish. Finish it from Backup & Restore, or with php restore.php --recover, then upgrade.");
        }
        if ($lock['state'] === 'busy') return _('DomainMOD is busy. Please try the upgrade again in a minute.');
        // Neither lock could be had safely (the database can't be reached, or an existing lock file can't be locked)
        if ($lock['state'] === 'error' && ($lock['reason'] ?? '') === 'database') {
            return domainmod_restore_db_unreachable_message('upgrade');
        }
        if ($lock['state'] === 'error') {
            return $web ? _("DomainMOD can't take its restore lock, so the upgrade can't run safely. Please try again in a minute.")
                : _("Run php update.php as the web server's user, or use Upgrade Database in a browser.");
        }
        return '';
    }

    /*
     * Whether a restore can run here: temp/ takes a test file (created and deleted, which also catches a read-only
     * mount), since a restore keeps its flag, record, and verdict there. While it can't, restores and Finish Restore are
     * refused with restoreMessage(), and the database lock stands in for the lock file for everything else.
     */
    public static function tempUsable()
    {
        clearstatcache();
        if (!is_dir(DIR_TEMP) || !is_writable(DIR_TEMP)) return false;
        $test = DIR_TEMP . '/restore-test-' . bin2hex(random_bytes(8));
        if (@file_put_contents($test, 'test') !== 4) {
            @unlink($test);
            return false;
        }
        return @unlink($test);
    }

    /*
     * Whether a restore, Finish Restore, or a recovery can run here: temp/ is usable (see tempUsable()), and the lock file
     * opens for writing when it exists, as RestoreRecovery::canRecoverHere() needs, so a restore never starts where its
     * recovery couldn't run
     */
    public static function restoreReady()
    {
        if (!self::tempUsable()) return false;
        clearstatcache();
        // Something else at the lock file's path (a folder, say) can't be locked, so it can't be created either
        if (file_exists(DIR_TEMP . '/restore.lock') && !is_file(DIR_TEMP . '/restore.lock')) return false;
        // A symlink whose target is gone can't be locked or created over (an exclusive holder never follows one)
        if (is_link(DIR_TEMP . '/restore.lock') && !file_exists(DIR_TEMP . '/restore.lock')) return false;
        if (!is_file(DIR_TEMP . '/restore.lock')) return true;
        $handle = @fopen(DIR_TEMP . '/restore.lock', 'r+');
        if ($handle === false) return false;
        // A file system that can't lock at all (such as NFS without its lock manager) isn't ready either. A lock that's
        // only busy (another request holds it) is fine.
        $blocked = false;
        $locked = flock($handle, LOCK_SH | LOCK_NB, $blocked);
        if ($locked) flock($handle, LOCK_UN);
        fclose($handle);
        return $locked || $blocked;
    }

    // Why a restore can't run while restoreReady() is false
    public static function restoreMessage($web = true)
    {
        return $web ? _("Restores need DomainMOD's temp folder and its temp/restore.lock file to be writable by the web server, on a file system that supports file locks. Fix that, then try again.")
            : _("Restores need DomainMOD's temp folder and its temp/restore.lock file to be writable by this user, on a file system that supports file locks. Fix that, or run this as the web server's user.");
    }

    // The real test: the lock file opens for writing, and a test file can be created and deleted in temp/
    public static function fileTest()
    {
        $handle = @fopen(DIR_TEMP . '/restore.lock', 'r+');
        if ($handle === false) return false;
        fclose($handle);

        $test = DIR_TEMP . '/restore-test-' . bin2hex(random_bytes(8));
        if (@file_put_contents($test, 'test') !== 4) {
            @unlink($test);
            return false;
        }
        return @unlink($test);
    }

    /*
     * The check before any recorded write (ChangeHistory::start(), and the custom field adds and deletes): throws with
     * the restore message when the flag exists, or the busy message when the lock is busy. Where neither the lock file
     * nor the database lock can be used safely (the lock's 'error' state), recorded writes stop once the history is on.
     */
    public static function forWrite($history_available = true)
    {
        $lock = self::shared();
        if ($lock['flag']) throw new RestoreLockException(self::message(true));
        if ($lock['state'] === 'busy') throw new RestoreLockException(self::message(false));
        if ($lock['state'] === 'error' && $history_available) {
            throw new RestoreLockException(function_exists('domainmod_restore_lock_error_message')
                ? domainmod_restore_lock_error_message() : 'The restore lock could not be opened');
        }
    }

} //@formatter:on
