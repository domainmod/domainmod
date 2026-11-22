<?php
/**
 * /classes/DomainMOD/RestoreRecovery.php
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
 * A restore, from the restore page (the web) or php restore.php (the command line), and the recovery of one that died
 * part-way. Every method here runs with the restore lock held exclusively (see _includes/restore-gate.inc.php).
 *
 * A restore copies the backup into a private staging folder (never temp/), reads the copy once for what it holds (see
 * scan()), writes the restore flag holding a new restore id, imports the copy, writes its verdict to temp/restore.result.php,
 * deletes the copy, and then runs the cleanup (see cleanup()), which decides whether the restored Change History can be
 * trusted and gives every user a new two-factor stamp. The flag is cleared only once the cleanup has finished, so a
 * restore that dies part-way leaves the flag, and the recovery runs the same cleanup from the saved verdict.
 *
 * The recovery runs from four places only: cron.php (from the command line or with its key), Backup & Restore after
 * its sign-in and administrator checks, php restore.php --recover, and a new restore's first step.
 */
class RestoreRecovery
{
    const KIND_WEB = 'web';
    const KIND_CLI = 'cli';
    const MANIFEST_PREFIX = '-- DomainMOD backup manifest: ';

    // The discard reasons the restore marker's action can hold, in the order the cleanup picks them ('' keeps the history)
    // What the staged record and the verdict start with, so a web server that runs them shows nothing
    const GUARD = "<?php exit; ?>\n";

    const REASONS = array('unfinished', 'newer', 'no_history', 'incomplete', 'format');

    // Which kind of process this is: the restore page and every other web request, or the command line
    public static function kind()
    {
        return PHP_SAPI === 'cli' ? self::KIND_CLI : self::KIND_WEB;
    }

    // A hash of the database name and DIR_ROOT, so two installs never touch each other's staged copies
    public static function installHash()
    {
        return substr(hash('sha256', ($GLOBALS['dbname'] ?? '') . "\n" . DIR_ROOT), 0, 16);
    }

    // Whether this process could finish a recovery: it can write temp/ (to clear the flag) and open the lock file to write
    public static function canRecoverHere()
    {
        clearstatcache();
        if (!is_dir(DIR_TEMP) || !is_writable(DIR_TEMP)) return false;
        // The verdict, the staged copy's record, and the flag have to be readable here too, or a verified import would be
        // judged unverified
        foreach (array('result', 'staged', 'flag') as $name) {
            if (is_file(domainmod_restore_path($name)) && !is_readable(domainmod_restore_path($name))) return false;
        }
        // The lock file, where it exists, opens for writing. A missing one passes here, since the recovery's exclusive lock
        // creates it (and recovery never runs without holding it, see domainmod_restore_holds_file_exclusive())
        if (!is_file(domainmod_restore_path('lock'))) return true;
        $handle = @fopen(domainmod_restore_path('lock'), 'r+');
        if ($handle === false) return false;
        fclose($handle);
        return true;
    }

    /*
     * Writes a small file atomically: to a temporary file in the same folder, read back and checked, then renamed into
     * place, so it's either whole or absent
     */
    protected static function writeAtomic($path, $contents, $mode = 0644)
    {
        // Contents that couldn't be made (see guarded()) are a failed write
        if (!is_string($contents)) return false;
        // A guarded .php file's temporary copy ends in .php too, so a copy a crash leaves behind is guarded the same way
        $temp = substr($path, -4) === '.php' ? substr($path, 0, -4) . '.tmp-' . bin2hex(random_bytes(8)) . '.php'
            : $path . '.tmp-' . bin2hex(random_bytes(8));
        // Written and synced to disk before the rename (PHP can't sync the folder itself, so a host that loses power
        // straight after the rename can still lose it)
        $umask = umask(0077);
        $handle = @fopen($temp, 'xb');
        umask($umask);
        $written = $handle !== false && @fwrite($handle, $contents) === strlen($contents) && @fflush($handle)
            && (!function_exists('fsync') || @fsync($handle));
        if ($handle !== false) $written = @fclose($handle) && $written && @chmod($temp, $mode);
        if (!$written || @file_get_contents($temp) !== $contents) {
            @unlink($temp);
            return false;
        }
        if (!@rename($temp, $path)) {
            @unlink($temp);
            return false;
        }
        return true;
    }

    /*
     * The restore id the flag holds, '' when there's no flag, null for a flag without one (a legacy flag), or false when
     * the flag is there but couldn't be read (it's never taken for a legacy flag, which would be given a new id)
     */
    public static function flagId()
    {
        clearstatcache();
        $path = domainmod_restore_path('flag');
        if (!is_file($path)) return '';
        $contents = @file_get_contents($path);
        if ($contents === false) return false;
        $id = trim($contents);
        return preg_match('/^[0-9a-f]{32}$/', $id) ? $id : null;
    }

    /*
     * What a Finish Restore form shown for the flag carries, so a form left open for one flag can't finish a later one:
     * the restore id the flag holds, or '' when there's no flag. With $give_id, a flag without an id is given one
     * first, as the recovery's first step would, but only while this request holds the lock file exclusively, and in a
     * sticky temp folder only when this process's user owns the flag's own entry (there, a flag replaced by another
     * user's copy could leave its owner, such as the command line, unable to remove it). A flag without an id that
     * isn't given one, or one that can't be read, gets 'flag:' and a hash of the file's own state (its device, inode,
     * size, change and modification times, and contents where they can be read, and the flag's own entry where it's a
     * link), so it matches only while that file is unchanged ('' when even its state can't be read).
     */
    public static function finishValue($give_id = false)
    {
        $restore_id = self::flagId();
        if ($restore_id === null && $give_id && function_exists('domainmod_restore_holds_file_exclusive')
            && domainmod_restore_holds_file_exclusive() && (!(@fileperms(DIR_TEMP) & 01000)
            || (function_exists('posix_geteuid') && (@lstat(domainmod_restore_path('flag'))['uid'] ?? -1) === posix_geteuid()))) {
            $new_id = bin2hex(random_bytes(16));
            if (self::writeFlag($new_id)) return $new_id;
        }
        if (is_string($restore_id)) return $restore_id;
        clearstatcache();
        $path = domainmod_restore_path('flag');
        $stat = @stat($path);
        $entry = @lstat($path);
        if ($stat === false || $entry === false) return '';
        $contents = @file_get_contents($path);
        return 'flag:' . hash('sha256', implode("\n", array($stat['dev'], $stat['ino'], $stat['size'], $stat['mtime'],
            $stat['ctime'], $entry['ino'], $entry['ctime'], $contents === false ? 'unreadable' : hash('sha256', $contents))));
    }

    // Writes the flag holding a restore id, atomically, so a crash while writing it leaves no flag
    public static function writeFlag($restore_id)
    {
        return self::writeAtomic(domainmod_restore_path('flag'), $restore_id);
    }

    /*
     * The saved verdict and decision for a restore id, or null when there's none for it (missing, unreadable, or another
     * id). With $strict, a verdict that's there but can't be read throws instead, so the clean-up stops and leaves the
     * flag for a later recovery rather than taking the restore for an unfinished one.
     */
    public static function readResult($restore_id, $strict = false)
    {
        clearstatcache();
        $path = domainmod_restore_path('result');
        if (!is_file($path)) return null;
        $contents = @file_get_contents($path);
        if ($contents === false && $strict) throw new \RuntimeException("The restore's verdict couldn't be read from the temp folder");
        $result = self::unguarded($contents);
        if (!is_array($result) || ($result['restore_id'] ?? '') !== $restore_id) return null;
        return $result;
    }

    public static function writeResult(array $result)
    {
        return self::writeAtomic(domainmod_restore_path('result'), self::guarded($result), 0644);
    }

    // A record as the contents of a PHP file that exits before its JSON, so serving the file shows nothing, or false when
    // it can't be encoded (such as a staging path that isn't valid UTF-8), which writeAtomic() treats as a failed write
    protected static function guarded(array $record)
    {
        $json = json_encode($record);
        return $json === false ? false : self::GUARD . $json;
    }

    // A record read back from guarded()'s contents, or null when it isn't one
    protected static function unguarded($contents)
    {
        $contents = (string) $contents;
        if (strncmp($contents, self::GUARD, strlen(self::GUARD)) !== 0) return null;
        $record = json_decode(substr($contents, strlen(self::GUARD)), true);
        return is_array($record) ? $record : null;
    }

    /*
     * The staged copy's record (path, kind, token, marker), or null when there's none. A path or marker whose file name
     * doesn't carry this install's hash is left out (as ''), so nothing here ever deletes any other file.
     */
    public static function stagedRecord()
    {
        clearstatcache();
        $path = domainmod_restore_path('staged');
        if (!is_file($path)) return null;
        $record = self::unguarded(@file_get_contents($path));
        if (!is_array($record)) $record = array();
        $record += array('path' => '', 'kind' => '', 'token' => '', 'marker' => '');
        foreach (array('path' => 'sql', 'marker' => 'marker') as $key => $extension) {
            if (!self::isStagedName((string) $record[$key], $extension)) $record[$key] = '';
        }
        return $record;
    }

    // Whether a path names one of this install's staged files (domainmod-restore-<install hash>-<restore id>.<extension>)
    public static function isStagedName($path, $extension)
    {
        return preg_match('/^domainmod-restore-' . preg_quote(self::installHash(), '/') . '-[0-9a-f]{32}\.' . $extension . '$/D',
            basename((string) $path)) === 1;
    }

    /*
     * Deletes the staged copy the record names and clears the record, but only for the same kind of process, and only
     * when the copy is confirmed gone: unlink() worked, or the marker beside it holds the recorded token (so this process
     * really sees the folder the copy was made in) and the copy isn't there. The marker goes last. Returns true when no
     * record is left.
     */
    public static function clearStaged($kind)
    {
        $record = self::stagedRecord();
        if ($record === null) return true;
        if (($record['kind'] ?? '') !== $kind) return false;

        $copy = (string) ($record['path'] ?? '');
        $marker = (string) ($record['marker'] ?? '');
        clearstatcache();
        $gone = false;
        if ($copy !== '' && is_file($copy)) {
            $gone = @unlink($copy);
        } elseif ($marker !== '' && is_file($marker) && hash_equals((string) $record['token'], trim((string) @file_get_contents($marker)))) {
            $gone = true;
        }

        if (!$gone) {
            error_log('DomainMOD: a staged backup copy could not be confirmed deleted (' . $copy . ')');
            return false;
        }

        if (!@unlink(domainmod_restore_path('staged'))) return false;
        if ($marker !== '') @unlink($marker);
        return true;
    }

    /*
     * php restore.php's arguments as its mode (restore, recover, forget, alone, or usage), backup file, and --temp-dir
     * folder. --recover and --forget-staged work on what an earlier restore left, so either one given with a backup
     * file, a --temp-dir, or the other one is 'alone', refused with its own message instead of quietly ignoring the
     * rest. An argument it doesn't know, a second file, or a restore without both its file and folder is 'usage'.
     */
    public static function cliArguments(array $arguments)
    {
        $file = '';
        $temp_dir = null;
        $modes = array();
        foreach ($arguments as $argument) {
            if ($argument === '--recover') {
                $modes['recover'] = true;
            } elseif ($argument === '--forget-staged') {
                $modes['forget'] = true;
            } elseif (strpos($argument, '--temp-dir=') === 0) {
                $temp_dir = substr($argument, strlen('--temp-dir='));
            } elseif ($file === '' && strpos($argument, '--') !== 0) {
                // An empty argument (such as an unset shell variable) leaves the file still to come, as it always has
                $file = $argument;
            } else {
                return array('usage', '', '');
            }
        }
        if ($modes) {
            if (count($modes) > 1 || $file !== '' || $temp_dir !== null) return array('alone', '', '');
            return array(isset($modes['recover']) ? 'recover' : 'forget', '', '');
        }
        if ($file === '' || (string) $temp_dir === '') return array('usage', '', '');
        return array('restore', $file, $temp_dir);
    }

    // Clears the staged copy's record without checking the copy, for php restore.php --forget-staged
    public static function forgetStaged()
    {
        $record = self::stagedRecord();
        if ($record === null) return true;
        if (!@unlink(domainmod_restore_path('staged'))) return false;
        if (!empty($record['marker'])) @unlink($record['marker']);
        return true;
    }

    // The notice administrators see while a staged copy's record remains, or '' when there's none
    public static function stagedNotice()
    {
        $record = self::stagedRecord();
        if ($record === null) return '';
        // A record that can't be read (such as after a permissions change) names itself instead of an empty path
        if (($record['path'] ?? '') === '') {
            return _("A staged backup copy's record, temp/restore.staged.php, can't be read. Make it readable by the web server's user, then open Backup & Restore and press Finish Restore, or run php restore.php --recover on the server if the restore was started there.");
        }
        $text = sprintf(_('A staged backup copy is still at %s.'), $record['path'] ?? '');
        if (($record['kind'] ?? '') === self::KIND_CLI) {
            return $text . ' ' . _('Run php restore.php --recover on the server to remove it.') . ' '
                . _("If the copy is already gone and that can't clear its record, run php restore.php --forget-staged.");
        }
        return $text . ' ' . _('Please delete it, then open Backup & Restore and press Finish Restore.') . ' '
            . _("If Finish Restore can't clear it once the copy is gone, run php restore.php --forget-staged on the server.");
    }

    /*
     * Copies the backup into $folder as domainmod-restore-<install hash>-<restore id>.sql (mode 0600), after writing a
     * marker beside it and the record of where it is, so a crash at any point leaves something the next run can clean up.
     * Leftover markers of this install in the folder (from earlier crashes) are deleted first. Returns the copy's path,
     * or throws.
     */
    public static function stage($source, $folder, $kind, $restore_id)
    {
        $prefix = rtrim($folder, '/') . '/domainmod-restore-' . self::installHash() . '-';
        foreach (glob($prefix . '*.marker') ?: array() as $leftover) @unlink($leftover);

        $copy = $prefix . $restore_id . '.sql';
        $marker = $prefix . $restore_id . '.marker';
        $token = bin2hex(random_bytes(16));
        // Both files are private from the moment they're created, whatever the process's umask
        $umask = umask(0077);
        $written = @file_put_contents($marker, $token);
        umask($umask);
        if ($written !== strlen($token)) {
            throw new \RuntimeException(_("The backup file couldn't be copied for the restore."));
        }
        // Readable by every process that may finish the restore (the web server and the command line can be different
        // users and groups), like the flag. It holds only the copy's path and the marker's token, in a PHP file that
        // shows nothing if temp/ is served.
        if (!self::writeAtomic(domainmod_restore_path('staged'), self::guarded(array('path' => $copy, 'kind' => $kind,
            'token' => $token, 'marker' => $marker)), 0644)) {
            @unlink($marker);
            throw new \RuntimeException(_("The backup file couldn't be copied for the restore."));
        }

        $in = @fopen($source, 'rb');
        $umask = umask(0077);
        $out = @fopen($copy, 'xb');
        umask($umask);
        $copied = $in !== false && $out !== false && @chmod($copy, 0600) && stream_copy_to_stream($in, $out) !== false;
        if ($in !== false) fclose($in);
        if ($out !== false) $copied = fclose($out) && $copied;
        if (!$copied || filesize($copy) !== filesize($source)) {
            self::clearStaged($kind);
            throw new \RuntimeException(_("The backup file couldn't be copied for the restore."));
        }
        return $copy;
    }

    /*
     * Reads a backup once, before anything is changed, for what the cleanup needs to know: the tables it creates, the
     * database version in its settings rows, its manifest (the backup page's table row counts), and whether the file
     * ends cleanly (see RestoreImport::checkFile()). These don't depend on how far the import later gets.
     */
    public static function scan($file)
    {
        $facts = array('tables' => array(), 'version' => '', 'manifest' => null, 'complete' => true, 'error' => '');

        try {
            RestoreImport::checkFile($file);
        } catch (\Exception $e) {
            $facts['complete'] = false;
            $facts['error'] = $e->getMessage();
        }

        $fp = @fopen($file, 'r');
        if ($fp === false) {
            $facts['complete'] = false;
            return $facts;
        }

        $settings_columns = null;
        $in_settings = false;
        while (($line = fgets($fp)) !== false) {
            if (strpos($line, self::MANIFEST_PREFIX) === 0) {
                $manifest = json_decode(trim(substr($line, strlen(self::MANIFEST_PREFIX))), true);
                if (is_array($manifest) && isset($manifest['tables']) && is_array($manifest['tables'])) {
                    $facts['manifest'] = array_map('intval', $manifest['tables']);
                }
                continue;
            }
            if (preg_match('/^CREATE TABLE `([^`]+)`/', $line, $match)) {
                $facts['tables'][] = $match[1];
                $in_settings = $match[1] === 'settings';
                if ($in_settings) $settings_columns = array();
                continue;
            }
            if ($in_settings) {
                // The column lines, until the line that closes the definition
                if (preg_match('/^\s+`([^`]+)`/', $line, $match)) {
                    $settings_columns[] = $match[1];
                } elseif (strpos(ltrim($line), ')') === 0) {
                    $in_settings = false;
                }
                continue;
            }
            if ($facts['version'] === '' && $settings_columns && strpos($line, 'INSERT INTO `settings` VALUES (') === 0) {
                $values = self::firstTuple(substr($line, strlen('INSERT INTO `settings` VALUES ')));
                $position = array_search('db_version', $settings_columns, true);
                if ($position !== false && isset($values[$position])) $facts['version'] = (string) $values[$position];
            }
        }
        fclose($fp);

        $facts['tables'] = array_values(array_unique($facts['tables']));
        return $facts;
    }

    // The values of the first row of an extended INSERT's VALUES list, unquoted, or array() when it can't be read
    public static function firstTuple($text)
    {
        $text = ltrim($text);
        if ($text === '' || $text[0] !== '(') return array();

        $values = array();
        $current = '';
        $quoted = false;
        $was_quoted = false;
        $length = strlen($text);
        for ($i = 1; $i < $length; $i++) {
            $char = $text[$i];
            if ($quoted) {
                if ($char === '\\' && $i + 1 < $length) {
                    $next = $text[++$i];
                    $current .= strtr($next, array('n' => "\n", 'r' => "\r", 't' => "\t", '0' => "\0", 'Z' => "\x1a"));
                } elseif ($char === "'") {
                    if ($i + 1 < $length && $text[$i + 1] === "'") {
                        $current .= "'";
                        $i++;
                    } else {
                        $quoted = false;
                    }
                } else {
                    $current .= $char;
                }
                continue;
            }
            if ($char === "'") {
                $quoted = true;
                $was_quoted = true;
            } elseif ($char === ',' || $char === ')') {
                $value = $was_quoted ? $current : trim($current);
                $values[] = (!$was_quoted && strtoupper($value) === 'NULL') ? null : $value;
                $current = '';
                $was_quoted = false;
                if ($char === ')') return $values;
            } else {
                $current .= $char;
            }
        }
        return array();
    }

    /*
     * Runs a restore from start to finish, with the lock held exclusively: any earlier restore's recovery first, then the
     * staged copy, the scan, the flag, the import ($import is called with the copy's path and throws when the import
     * fails), the verdict, the copy's deletion, and the cleanup. Returns array('started' => whether the database was
     * touched, 'error' => '' or why it didn't finish, 'notices' => what administrators should be told, 'recovered' =>
     * whether an earlier restore was finished first, which changes the database even when this one doesn't start, and
     * 'recovered_imported' => whether that earlier restore's import was confirmed). $restore_id is the id its flag holds,
     * when the caller wants to know it (32 hex characters), otherwise a new one.
     */
    public static function restore($source, $folder, $kind, callable $import, $restore_id = null)
    {
        $notices = array();
        $recovered = false;
        $recovered_imported = false;

        if (self::flagId() !== '') {
            $recovery = self::recover($kind);
            $notices = array_merge($notices, $recovery['notices']);
            if (!$recovery['ok']) {
                // The earlier restore's recovery may have changed the database before it failed, and its flag stays
                return array('started' => false, 'error' => _("An earlier restore that didn't finish couldn't be finished, so this backup wasn't restored."),
                    'notices' => $notices, 'recovered' => false, 'recovery_failed' => true);
            }
            $recovered = true;
            $recovered_imported = !empty($recovery['imported']);
        }

        $result = self::restoreBackup($source, $folder, $kind, $import, $notices, $restore_id);
        $result['recovered'] = $recovered;
        $result['recovered_imported'] = $recovered && $recovered_imported;
        return $result;
    }

    /*
     * What a restore that didn't finish tells the administrator, as lines: the error, then what it left behind and what to
     * do next. $web says whether Finish Restore on Backup & Restore is offered as well as the command line.
     */
    public static function failureLines(array $restore, $web)
    {
        $finish = $web ? _('Once the problem is fixed, open Backup & Restore and press Finish Restore, or run php restore.php --recover on the server.')
            : _('Once the problem is fixed, run php restore.php --recover.');
        $lines = array(sprintf(_("The restore didn't finish: %s"), $restore['error']));
        if (!empty($restore['cleanup_failed'])) {
            $lines[] = _('The backup was imported.') . ' ' . $finish;
        } elseif (!empty($restore['recovery_failed'])) {
            $lines[] = _("DomainMOD stays closed until it's finished.") . ' ' . $finish;
        } elseif (!empty($restore['started'])) {
            $lines[] = _('Your database may now be partly restored, so restore a complete backup before going on.');
            // A clean-up that didn't finish leaves the flag, so DomainMOD stays closed until it's finished
            if (!empty($restore['flag_left'])) {
                $lines[] = _("The restore's clean-up couldn't be completed either, so DomainMOD stays closed until it's finished.") . ' ' . $finish;
            }
        } elseif (!empty($restore['recovered'])) {
            $lines[] = _("An earlier restore that didn't finish was finished first, but this backup wasn't restored.");
            // Its import wasn't confirmed, so the database it left may be partly restored
            if (empty($restore['recovered_imported'])) {
                $lines[] = _('Your database may now be partly restored, so restore a complete backup before going on.');
            }
        } else {
            $lines[] = _('Nothing was changed.');
        }
        return $lines;
    }

    // restore()'s steps after any earlier restore's recovery, from the staged copy on
    protected static function restoreBackup($source, $folder, $kind, callable $import, array $notices, $restore_id = null)
    {

        @unlink(domainmod_restore_path('result'));
        if (!self::clearStaged($kind) || self::stagedRecord() !== null) {
            return array('started' => false, 'error' => self::stagedNotice(), 'notices' => $notices);
        }

        if (!is_string($restore_id) || !preg_match('/^[0-9a-f]{32}$/', $restore_id)) $restore_id = bin2hex(random_bytes(16));
        try {
            $copy = self::stage($source, $folder, $kind, $restore_id);
        } catch (\Exception $e) {
            // A copy that failed part-way and couldn't be removed is named
            if (self::stagedRecord() !== null) $notices[] = self::stagedNotice();
            return array('started' => false, 'error' => $e->getMessage(), 'notices' => $notices);
        }

        $facts = self::scan($copy);
        if (!$facts['complete']) {
            // A backup that's been cut short is turned down before anything changes (a copy that can't be removed is named)
            if (!self::clearStaged($kind)) $notices[] = self::stagedNotice();
            return array('started' => false, 'error' => $facts['error'] !== '' ? $facts['error']
                : _('The backup file could not be read.'), 'notices' => $notices);
        }

        // The database lock still this request's (staging and the scan can take a while, and a database restart meanwhile
        // would have let requests in through it), checked before the flag, so a lost lock changes nothing at all
        if (function_exists('domainmod_restore_lock_still_held') && !domainmod_restore_lock_still_held()) {
            if (!self::clearStaged($kind)) $notices[] = self::stagedNotice();
            return array('started' => false, 'error' => _("DomainMOD's restore lock was lost before the import started, so nothing was changed. Please try again."),
                'notices' => $notices);
        }

        if (!self::writeFlag($restore_id)) {
            if (!self::clearStaged($kind)) $notices[] = self::stagedNotice();
            return array('started' => false, 'error' => _("DomainMOD can't write to its temp folder."),
                'notices' => $notices);
        }

        // Checked again once the flag is out (a lock lost while it was written would have let a request in without seeing
        // it). Nothing has been imported, so the flag comes away again and nothing changes.
        if (function_exists('domainmod_restore_lock_still_held') && !domainmod_restore_lock_still_held()) {
            $flag_removed = @unlink(domainmod_restore_path('flag'));
            if (!self::clearStaged($kind)) $notices[] = self::stagedNotice();
            // A flag that can't be removed (temp/ turned read-only meanwhile) keeps DomainMOD closed until Finish Restore
            return array('started' => false, 'error' => $flag_removed
                ? _("DomainMOD's restore lock was lost before the import started, so nothing was changed. Please try again.")
                : _("DomainMOD's restore lock was lost before the import started, so nothing was imported, but the restore's flag couldn't be removed. Make the temp folder writable, then press Finish Restore."),
                'notices' => $notices);
        }

        $error = '';
        try {
            $import($copy);
        } catch (\Exception $e) {
            $error = $e->getMessage();
        }

        // A verdict that can't be saved (a full temp folder) counts as an unverified import, so the restored history is
        // removed, and the restore says so
        if (!self::writeResult(array('restore_id' => $restore_id, 'tables' => $facts['tables'], 'version' => $facts['version'],
            'manifest' => $facts['manifest'], 'complete' => $facts['complete'], 'import_ok' => $error === ''))) {
            $notices[] = _("The restore's result couldn't be saved in the temp folder, so its import can't be verified.");
        }
        if (!self::clearStaged($kind)) $notices[] = self::stagedNotice();

        $finished = self::finish($restore_id);
        $notices = array_merge($notices, $finished['notices']);
        // An import that worked but whose clean-up didn't leaves the flag and verdict, so the clean-up can be finished
        $cleanup_failed = !$finished['ok'] && $error === '';
        if ($cleanup_failed) $error = _("Its clean-up couldn't be completed. Please check the server's error log.");

        return array('started' => true, 'error' => $error, 'notices' => $notices, 'cleanup_failed' => $cleanup_failed,
            'flag_left' => !$finished['ok']);
    }

    /*
     * Finishes a restore that died part-way (the flag is there), or only cleans up a staged copy left by a restore that
     * died during its scan (no flag, but a record of the same kind). A flag without a restore id (written by an older
     * release) is converted first, so every later recovery uses the same id. Returns array('ok', 'notices', and, when a
     * flag was finished, 'imported': whether its verdict confirmed the import finished).
     */
    public static function recover($kind)
    {
        $notices = array();
        // Checked again under the exclusive lock: a verdict or flag this process can't read would be judged unverified (an
        // unreadable flag would be taken for an older release's and given a new id)
        foreach (array('result', 'staged', 'flag') as $name) {
            clearstatcache();
            if (is_file(domainmod_restore_path($name)) && !is_readable(domainmod_restore_path($name))) {
                return array('ok' => false, 'notices' => $notices);
            }
        }
        $restore_id = self::flagId();
        if ($restore_id === false) return array('ok' => false, 'notices' => $notices);

        if ($restore_id === null) {
            $restore_id = bin2hex(random_bytes(16));
            if (!self::writeFlag($restore_id)) return array('ok' => false, 'notices' => $notices);
        }

        if ($restore_id !== '') {
            // Whether the import itself was confirmed finished, read before the clean-up removes the verdict
            $result = self::readResult($restore_id);
            $finished = self::finish($restore_id, $kind);
            $finished['imported'] = is_array($result) && !empty($result['complete']) && !empty($result['import_ok']);
            return $finished;
        }

        if (self::stagedRecord() !== null && !self::clearStaged($kind)) $notices[] = self::stagedNotice();
        return array('ok' => true, 'notices' => $notices);
    }

    // The cleanup, then the staged copy, then the flag (cleared first) and the verdict, as array('ok', 'notices')
    protected static function finish($restore_id, $kind = null)
    {
        $notices = array();
        try {
            self::cleanup($restore_id);
        } catch (\Throwable $e) {
            error_log('DomainMOD: the restore clean-up failed: ' . $e->getMessage());
            return array('ok' => false, 'notices' => $notices);
        }

        if ($kind !== null && self::stagedRecord() !== null && !self::clearStaged($kind)) $notices[] = self::stagedNotice();

        // The flag counts as gone only when temp/ can be searched and it isn't there
        if (!@unlink(domainmod_restore_path('flag'))) {
            $unlink_error = error_get_last()['message'] ?? '';
            clearstatcache();
            if (!is_file(DIR_TEMP . '/index.php') || is_file(domainmod_restore_path('flag'))) {
                // Logged with the reason (such as a flag owned by another user in a sticky temp folder), since the
                // message people see points to the error log
                error_log("DomainMOD: the restore's clean-up finished, but temp/restore.flag couldn't be removed: " . $unlink_error);
                return array('ok' => false, 'notices' => $notices);
            }
        }
        @unlink(domainmod_restore_path('result'));
        return array('ok' => true, 'notices' => $notices);
    }

    // From cron.php: the lock taken exclusively without waiting, the recovery, and the lock let go for the run's own
    public static function fromCron()
    {
        $lock = domainmod_restore_lock(true);
        // Only with the lock file held too, as for every recovery (see domainmod_restore_holds_file_exclusive())
        if ($lock['state'] === 'held' && $lock['flag'] && domainmod_restore_holds_file_exclusive()) {
            $recovery = self::recover(self::kind());
            // Nobody sees this run, so a finished restore whose import wasn't confirmed is logged for the administrator
            if ($recovery['ok'] && empty($recovery['imported'])) {
                error_log("DomainMOD: a restore that didn't finish was cleaned up, but its import wasn't confirmed, so the database may be partly restored. Restore a complete backup before going on.");
            }
        }
        domainmod_restore_unlock();
    }

    /*
     * The restore's cleanup: the Change History's check, the Watch List's, the Refresh Domains task's, then every user's
     * new two-factor stamp (so sessions opened before the restore end), each safe to run twice
     */
    public static function cleanup($restore_id)
    {
        $pdo = Database::getInstance()->cnxx;
        self::historyCleanup($pdo, $restore_id);
        ChangeHistory::forgetAvailability();
        self::watchCleanup($pdo, $restore_id);
        self::refreshCleanup($pdo, $restore_id);

        /*
         * Every user gets a new two-factor stamp, so sessions opened before the restore end. Only a restored database that
         * can't have stamps skips it: no settings or users table (an import that died after dropping one), or a users
         * table without the stamp column (an older one), where every session fails its check anyway. Any other failure,
         * such as a lost connection, leaves the flag for a later recovery.
         */
        $stmt = $pdo->query("
            SELECT TABLE_NAME, COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND ((TABLE_NAME = 'settings' AND COLUMN_NAME = 'db_version')
                OR (TABLE_NAME = 'users' AND COLUMN_NAME = 'two_factor_stamp'))");
        $present = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $stmt->closeCursor();
        if (!in_array('settings', $present, true) || !in_array('users', $present, true)) return;
        // Nor a settings table without its row (an import that died after creating it). With the stamp column there, the
        // stamps are renewed directly, whatever the restored version says.
        if (self::restoredVersion($pdo) === null) return;
        (new TwoFactor())->newStampsForEveryone();
    }

    /*
     * The Watch List after a restore of a 4.27.0 or later database (an older one leaves watch_domains alone: the next
     * Upgrade Database empties it, and WatchList::isAvailable() is false until then). A missing watch_domains (a
     * restore that failed part-way) is created empty, so no page or task queries a missing table. One that's there is
     * emptied unless the verdict says the dump was complete, its import succeeded, and it held watch_domains, since
     * otherwise its rows may be the overwritten install's, which the task would go on looking up. Then the Check Watch
     * List task is added when the restored scheduler has none, leaving an existing one, enabled or not, as it is. Without a scheduler table (an import that died part-way), that step is
     * skipped.
     */
    public static function watchCleanup(\PDO $pdo, $restore_id)
    {
        $version = self::restoredVersion($pdo);
        if ($version === null || version_compare($version, WatchList::AVAILABLE_FROM, '<')) return;

        $stmt = $pdo->query("
            SELECT TABLE_NAME
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME IN ('watch_domains', 'scheduler')");
        $tables = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        $stmt->closeCursor();

        // Read strictly, as the Change History's check does: a verdict that can't be read leaves the flag for a later
        // recovery rather than emptying a table that may have been restored in full
        $result = self::readResult($restore_id, true);
        $replaced = is_array($result) && !empty($result['complete']) && !empty($result['import_ok'])
            && in_array('watch_domains', (array) ($result['tables'] ?? array()), true);

        if (!in_array('watch_domains', $tables, true)) {
            $pdo->exec(WatchList::tableSql());
        } elseif (!$replaced) {
            $pdo->exec("TRUNCATE TABLE `watch_domains`");
        }

        if (in_array('scheduler', $tables, true)) {
            $exists = (int) $pdo->query("SELECT count(*) FROM scheduler WHERE slug = 'watch-list'")->fetchColumn() > 0;
            if (!$exists) {
                $stmt = $pdo->prepare("
                    INSERT INTO scheduler
                    (`name`, description, `interval`, expression, slug, sort_order, is_running, active, `timezone`, next_run,
                     insert_time)
                     VALUES
                    ('Check Watch List', :description, 'Every 10 Minutes', '*/10 * * * *', 'watch-list', '130', '0', '1',
                     'UTC', :next_run, :insert_time)");
                $stmt->bindValue('description', WatchList::TASK_DESCRIPTION, \PDO::PARAM_STR);
                $stmt->bindValue('next_run', (new Scheduler())->getNextRun('*/10 * * * *', 'UTC'), \PDO::PARAM_STR);
                $stmt->bindValue('insert_time', gmdate('Y-m-d H:i:s'), \PDO::PARAM_STR);
                $stmt->execute();
            }
        }

        WatchList::resetAvailable();
    }

    /*
     * The Refresh Domains task after a restore of a 4.27.0 or later database (an older one leaves domain_refresh_status
     * alone: the next Upgrade Database drops and creates it, and DomainRefresh::scheduleAvailable() is false until then).
     * A missing domain_refresh_status, or one without every column this version creates, is created again empty. One
     * that's there is emptied unless the Change History's check kept the restored history (a full backup whose manifest
     * row counts match, domain_refresh_status and domains included, and an import that succeeded), since otherwise its
     * rows may name another portfolio's domains (emptying only means every domain is checked again from the start).
     * Then the task's scheduler row is added, off, when the restored scheduler has none (skipped without a scheduler
     * table), and unless the history was kept, an existing one is turned off, so a restore that stopped part-way can't
     * leave the task checking a portfolio that isn't the one restored. The decision is the one historyCleanup() saved
     * under the restore id (see historyKept()), so a retry after a crash makes the same choice.
     */
    public static function refreshCleanup(\PDO $pdo, $restore_id)
    {
        $version = self::restoredVersion($pdo);
        if ($version === null || version_compare($version, DomainRefresh::AVAILABLE_FROM, '<')) return;

        $kept = self::historyKept($pdo, $restore_id);

        $stmt = $pdo->query("
            SELECT TABLE_NAME, COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME IN ('domain_refresh_status', 'scheduler')");
        $columns = array();
        foreach ($stmt->fetchAll(\PDO::FETCH_NUM) as $row) $columns[$row[0]][] = strtolower($row[1]);
        $stmt->closeCursor();

        if (!isset($columns['domain_refresh_status']) || array_diff(DomainRefresh::STATUS_COLUMNS, $columns['domain_refresh_status'])) {
            $pdo->exec("DROP TABLE IF EXISTS `domain_refresh_status`");
            $pdo->exec(DomainRefresh::tableSql());
        } elseif (!$kept) {
            $pdo->exec("TRUNCATE TABLE `domain_refresh_status`");
        }

        if (isset($columns['scheduler'])) {
            $exists = (int) $pdo->query("SELECT count(*) FROM scheduler WHERE slug = 'domain-refresh'")->fetchColumn() > 0;
            if (!$exists) {
                $stmt = $pdo->prepare("
                    INSERT INTO scheduler
                    (`name`, description, `interval`, expression, slug, sort_order, is_running, active, `timezone`, next_run,
                     insert_time)
                     VALUES
                    (:name, :description, 'Every 10 Minutes', '*/10 * * * *', 'domain-refresh', '120', '0', '0', 'UTC',
                     :next_run, :insert_time)");
                $stmt->bindValue('name', DomainRefresh::TASK_NAME, \PDO::PARAM_STR);
                $stmt->bindValue('description', DomainRefresh::TASK_DESCRIPTION, \PDO::PARAM_STR);
                $stmt->bindValue('next_run', (new Scheduler())->getNextRun('*/10 * * * *', 'UTC'), \PDO::PARAM_STR);
                $stmt->bindValue('insert_time', gmdate('Y-m-d H:i:s'), \PDO::PARAM_STR);
                $stmt->execute();
            } elseif (!$kept) {
                $pdo->exec("UPDATE scheduler SET active = '0' WHERE slug = 'domain-refresh'");
            }
        }

        DomainRefresh::resetScheduleAvailable();
    }

    /*
     * Whether historyCleanup() kept the restored history for this restore: the reason it saved in the verdict ('' when
     * kept), or else the one its restore marker records, never its return value (a retry after a crash returns early).
     * With no saved decision, the history counts as not kept.
     */
    public static function historyKept(\PDO $pdo, $restore_id)
    {
        $result = self::readResult($restore_id, true);
        if (is_array($result) && array_key_exists('reason', $result)) return (string) $result['reason'] === '';

        $stmt = $pdo->query("
            SELECT count(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'change_operations'
              AND COLUMN_NAME = 'note_code'");
        $has_markers = (int) $stmt->fetchColumn() > 0;
        $stmt->closeCursor();
        if (!$has_markers) return false;

        $stmt = $pdo->prepare("
            SELECT `action`
            FROM change_operations
            WHERE source = 'restore'
              AND note_code = :note_code
            ORDER BY id DESC
            LIMIT 1");
        $stmt->bindValue('note_code', 'restore:' . $restore_id, \PDO::PARAM_STR);
        $stmt->execute();
        $reason = $stmt->fetchColumn();
        $stmt->closeCursor();

        return $reason !== false && (string) $reason === '';
    }

    /*
     * The clean-up's reason: the one saved in the verdict by an earlier attempt (a retry after a crash, when the tables
     * the decision looked at may already have changed), or else a new one from $decide, saved before either table
     * changes. If it can't be saved, this throws, nothing changes now, and the flag stays for a later recovery.
     */
    public static function decidedReason($result, callable $decide)
    {
        if ($result !== null && array_key_exists('reason', $result)) return (string) $result['reason'];
        $reason = (string) $decide();
        if ($result !== null && !self::writeResult($result + array('reason' => $reason))) {
            throw new \RuntimeException("The restore's clean-up decision couldn't be saved in the temp folder");
        }
        return $reason;
    }

    /*
     * Keeps the restored history only when the dump was a full backup with a manifest whose row counts match, the import
     * succeeded, and both history tables have this version's columns. Otherwise the history rows are discarded: a table
     * with every column this version creates is only emptied (a newer release's extra columns survive), and one that's
     * missing or lacks a column is created again. The reason is decided once and saved under the restore id before
     * either table changes, so a retry after a crash writes the same notice. Then the restore marker (and, when the
     * history was emptied and the restored version is this one or later, a fresh start marker), each written only once.
     */
    public static function historyCleanup(\PDO $pdo, $restore_id)
    {
        $dbname = $GLOBALS['dbname'] ?? '';
        $columns = self::tableColumns($pdo, $dbname);

        // Already done for this restore: only the later cleanups run
        if (in_array('note_code', $columns['change_operations'] ?? array(), true)) {
            $stmt = $pdo->prepare("SELECT count(*) FROM change_operations WHERE note_code = :note_code");
            $stmt->bindValue('note_code', 'restore:' . $restore_id, \PDO::PARAM_STR);
            $stmt->execute();
            $done = (int) $stmt->fetchColumn() > 0;
            $stmt->closeCursor();
            if ($done) return;
        }

        $result = self::readResult($restore_id, true);
        $reason = self::decidedReason($result, function () use ($pdo, $result, $columns) {
            return self::reason($pdo, $result, $columns);
        });

        $restored_version = self::restoredVersion($pdo);

        if ($reason !== '') {
            foreach (ChangeHistory::tableSql() as $table => $sql) {
                $shape_ok = isset($columns[$table])
                    && !array_diff(ChangeHistory::EXPECTED_COLUMNS[$table], $columns[$table]);
                if ($shape_ok) {
                    // TRUNCATE empties it without undo for every row, however large it is, and keeps its shape
                    $pdo->exec("TRUNCATE TABLE `" . $table . "`");
                } else {
                    $pdo->exec("DROP TABLE IF EXISTS `" . $table . "`");
                    $pdo->exec($sql);
                }
            }
        }

        /*
         * A newer release's history table kept for its shape may have a column this release's writes leave out (one with
         * no default). Since its rows were discarded, it's created again in this release's shape, so the markers and
         * every later recorded change can be written.
         */
        if ($reason !== '' && self::unwritableColumns($pdo)) {
            foreach (ChangeHistory::tableSql() as $table => $sql) {
                $pdo->exec("DROP TABLE IF EXISTS `" . $table . "`");
                $pdo->exec($sql);
            }
        }

        /*
         * The markers: a discarded history's new start marker first (when the restored version records history), so it's
         * the first operation row, as ChangeHistory::isAvailable() reads it, then the restore marker
         */
        $write_markers = function () use ($pdo, $restore_id, $reason, $restored_version) {
            if ($reason !== '' && $restored_version !== null
                && version_compare($restored_version, ChangeHistory::FIRST_VERSION, '>=')) {
                ChangeHistory::writeStartMarker($pdo, 'started:' . $restore_id);
            }
            self::insertRestoreMarker($pdo, $restore_id, $reason);
        };
        $pdo->beginTransaction();
        try {
            $write_markers();
        } catch (\PDOException $e) {
            // A newer release's history table that this release can't write to (a column without a default, error 1364,
            // or an unknown column, error 1054) is created again in this release's shape. It's already empty, since its
            // history was discarded. Any other error leaves the flag for a later recovery.
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($reason === '' || !in_array((int) ($e->errorInfo[1] ?? 0), array(1054, 1364), true)) throw $e;
            foreach (ChangeHistory::tableSql() as $table => $sql) {
                $pdo->exec("DROP TABLE IF EXISTS `" . $table . "`");
                $pdo->exec($sql);
            }
            $pdo->beginTransaction();
            try {
                $write_markers();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
        }
        try {
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }

    // Whether either history table has a column this release doesn't write and that has no default
    protected static function unwritableColumns(\PDO $pdo)
    {
        $stmt = $pdo->query("
            SELECT TABLE_NAME, COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME IN ('change_operations', 'change_history')
              AND IS_NULLABLE = 'NO'
              AND COLUMN_DEFAULT IS NULL
              AND EXTRA NOT LIKE '%auto_increment%'");
        foreach ($stmt->fetchAll(\PDO::FETCH_NUM) as $row) {
            $written = array_diff(ChangeHistory::EXPECTED_COLUMNS[$row[0]], array('id'));
            if (!in_array(strtolower($row[1]), $written, true)) return true;
        }
        return false;
    }

    // The restore marker, inside the caller's transaction, with the reason in its action ('' when the history was kept)
    protected static function insertRestoreMarker(\PDO $pdo, $restore_id, $reason)
    {
        $stmt = $pdo->prepare("
            INSERT INTO change_operations
            (source, `action`, user_id, username, note, note_code, item_count, insert_time)
            VALUES
            ('restore', :action, 0, '', '', :note_code, 0, :insert_time)");
        $stmt->bindValue('action', $reason, \PDO::PARAM_STR);
        $stmt->bindValue('note_code', 'restore:' . $restore_id, \PDO::PARAM_STR);
        $stmt->bindValue('insert_time', gmdate('Y-m-d H:i:s'), \PDO::PARAM_STR);
        $stmt->execute();
    }

    /*
     * Why the restored history is discarded, or '' to keep it. When several apply, the first in this order: the dump was
     * cut off, it came from a newer version, it had no change history, it lacked a table or its manifest, the import
     * failed, the row counts don't match the manifest, and last a history table missing one of this version's columns.
     * A missing or mismatched verdict counts as an unfinished import.
     */
    public static function reason(\PDO $pdo, $result, array $columns)
    {
        if ($result === null) return 'unfinished';
        if (empty($result['complete'])) return 'unfinished';

        $version = (string) ($result['version'] ?? '');
        if ($version !== '' && version_compare($version, SOFTWARE_VERSION, '>')) return 'newer';

        $tables = (array) ($result['tables'] ?? array());
        if (!in_array('change_operations', $tables, true) || !in_array('change_history', $tables, true)) return 'no_history';

        $required = $version === '' ? null : ChangeHistory::backupTables($version);
        if ($required === null || array_diff($required, $tables) || !is_array($result['manifest'] ?? null)) return 'incomplete';

        if (empty($result['import_ok'])) return 'unfinished';

        foreach ($result['manifest'] as $table => $count) {
            if (!preg_match('/^[a-zA-Z0-9_]+$/', (string) $table)) return 'incomplete';
            if (!isset($columns[$table])) return 'incomplete';
            if ((int) $pdo->query("SELECT count(*) FROM `" . $table . "`")->fetchColumn() !== (int) $count) return 'incomplete';
        }
        foreach ($required as $table) {
            if (!array_key_exists($table, $result['manifest'])) return 'incomplete';
        }

        foreach (ChangeHistory::EXPECTED_COLUMNS as $table => $expected) {
            if (!isset($columns[$table]) || array_diff($expected, $columns[$table])) return 'format';
        }
        return '';
    }

    // Every table's columns in the database, by table name
    protected static function tableColumns(\PDO $pdo, $dbname)
    {
        $stmt = $pdo->prepare("
            SELECT TABLE_NAME, COLUMN_NAME
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = :dbname");
        $stmt->bindValue('dbname', $dbname, \PDO::PARAM_STR);
        $stmt->execute();
        $columns = array();
        foreach ($stmt->fetchAll(\PDO::FETCH_NUM) as $row) $columns[$row[0]][] = strtolower($row[1]);
        $stmt->closeCursor();
        return $columns;
    }

    // The restored database's version, or null when it can't be read (an import that died after dropping settings)
    protected static function restoredVersion(\PDO $pdo)
    {
        // Only a missing settings table or row counts as unreadable: a failed query (a lost connection) throws, so the
        // flag stays for a later recovery
        $stmt = $pdo->query("
            SELECT count(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'settings'
              AND COLUMN_NAME = 'db_version'");
        $exists = (int) $stmt->fetchColumn() > 0;
        $stmt->closeCursor();
        if (!$exists) return null;
        $version = $pdo->query("SELECT db_version FROM settings LIMIT 1")->fetchColumn();
        return $version === false ? null : (string) $version;
    }

    // The notice for a restore marker's reason (see REASONS), with %s for the date
    public static function markerMessage($reason)
    {
        $messages = array(
            '' => _("This database was restored from a backup on %s. Changes made between that backup and the restore aren't shown."),
            'no_history' => _('Changes recorded before this restore were removed, since the backup had no change history.'),
            'unfinished' => _("Changes recorded before this restore were removed, since the restore didn't finish or its result couldn't be verified."),
            'incomplete' => _('Changes recorded before this restore were removed, since the backup was incomplete.'),
            'newer' => _('Changes recorded before this restore were removed, since the backup came from a newer version of DomainMOD.'),
            'format' => _("Changes recorded before this restore were removed, since the backup's change history wasn't in the expected format."));
        return $messages[$reason] ?? $messages['unfinished'];
    }

    /*
     * The manifest line the backup page writes just before Ifsnop's completion line: each table and the rows Ifsnop
     * itself wrote (from its info hook), and the version
     */
    public static function manifestLine(array $counts, $version)
    {
        return self::MANIFEST_PREFIX . json_encode(array('version' => (string) $version, 'tables' => $counts)) . PHP_EOL;
    }

    /*
     * Puts the manifest into a finished dump: cuts the file at the start of its completion line, writes the manifest, and
     * writes the same completion line again, then checks the file ends with it
     */
    public static function addManifest($file, array $counts, $version)
    {
        $fp = fopen($file, 'r+');
        if ($fp === false) throw new \RuntimeException('The backup could not be finished');
        try {
            $size = filesize($file);
            $tail_start = max(0, $size - 4096);
            fseek($fp, $tail_start);
            $tail = (string) stream_get_contents($fp);
            $at = strrpos($tail, "\n-- Dump completed");
            if ($at === false) throw new \RuntimeException('The backup has no completion line');
            $footer = substr($tail, $at + 1);
            ftruncate($fp, $tail_start + $at + 1);
            fseek($fp, $tail_start + $at + 1);
            fwrite($fp, self::manifestLine($counts, $version) . $footer);
            fflush($fp);
        } finally {
            fclose($fp);
        }
        clearstatcache();
        $check = fopen($file, 'r');
        fseek($check, max(0, filesize($file) - strlen($footer)));
        $ends = stream_get_contents($check) === $footer;
        fclose($check);
        if (!$ends) throw new \RuntimeException('The backup could not be finished');
    }

} //@formatter:on
