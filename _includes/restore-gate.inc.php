<?php
/**
 * /_includes/restore-gate.inc.php
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
/*
 * The restore gate. A restore (Backup & Restore, or php restore.php) holds temp/restore.lock exclusively and writes
 * temp/restore.flag while the database is being replaced, and the upgrade holds the lock exclusively while it migrates.
 * Every other request takes the lock shared, here, before anything reads the database, and keeps it until the request
 * ends (PHP releases it then), so nothing reads or writes part-way into a restore or an upgrade. Shared locks don't wait
 * on each other, so this costs ordinary pages nothing.
 *
 * It runs from the end of _includes/init.inc.php, before the autoloader and the database, so it's plain functions with
 * no classes. The entry scripts in domainmod_restore_gate_exempt() take the lock themselves, in their own order.
 *
 * When the lock file can't be used (a temp folder the web server can't write, so the file was never created, or a lock
 * file it can't open), the lock falls back to MariaDB named locks instead (see domainmod_restore_db_lock()), so DomainMOD keeps
 * working, and administrators are told to make temp/ writable. Every exclusive holder takes the database lock as well as
 * the file lock, so requests using either are always kept out. Restores need a writable temp folder, so they're refused
 * while it isn't.
 *
 * Every process running one install (web server, cron, and the command line) must share one temp folder, as they always
 * have: a request holding a shared lock on its own copy of the file isn't seen by a holder using another copy. The
 * database lock likewise needs one database server: named locks aren't shared across a cluster's nodes (Galera turns
 * GET_LOCK() down, which ends as the busy refusal, never a pass). And the lock file has to be one every process can
 * see: a temp/restore.lock that's a symlink some of them can't follow (open_basedir, say) isn't supported.
 */

// The restore's files in the temp folder
function domainmod_restore_path($name)
{
    // The staged record and the verdict are PHP files that exit straight away (see RestoreRecovery::guarded()), so a
    // web server that serves temp/ despite its .htaccess shows nothing of them
    $names = array('lock' => 'restore.lock', 'flag' => 'restore.flag', 'staged' => 'restore.staged.php',
        'result' => 'restore.result.php');
    return DIR_TEMP . '/' . $names[$name];
}

/*
 * Called when the shared database connection can't be made (DomainMOD\Database::getInstance()): a request waiting to
 * take the database lock on connecting gets the busy message, as it would for a busy lock, rather than an error page
 */
function domainmod_restore_db_connect_failed()
{
    global $domainmod_restore_db_pending;
    if (!empty($domainmod_restore_db_pending)) domainmod_restore_refuse(domainmod_restore_message(false));
}

// Whether the restore flag is there (a restore is running, or one died and hasn't been finished)
function domainmod_restore_flag_exists()
{
    clearstatcache();
    return is_file(domainmod_restore_path('flag'));
}

/*
 * Takes the restore lock, shared or exclusive, and keeps it until the request ends or domainmod_restore_unlock(). It
 * reports what it found rather than refusing by itself: array('state' => 'held', 'busy', 'fallback', or 'error', 'flag'
 * => whether the restore flag exists, checked once the lock's state is known). 'busy' means another process holds it in
 * a way that conflicts. 'fallback' (shared only) means the lock file can't be used and the database can't be reached
 * from here yet (the gate, before the bootstrap), so the database lock is taken on connecting (see
 * domainmod_restore_db_connected()). 'error' means neither lock can be used safely (logged with error_log(), never the
 * database, which a restore may be replacing). An exclusive request waits up to $wait seconds. A lock already held in
 * the same mode, or exclusively, is reported as held.
 *
 * Exclusive holders (a restore, the upgrade, the installer, a recovery) always take the database lock (see
 * domainmod_restore_db_lock()), which keeps out requests that fell back to it, and the file lock too whenever the file
 * exists, failing if an existing file can't be locked, since requests may hold it shared. Only they create the file
 * (mode 0664, whatever the umask), while holding the database lock, so a shared holder never finds a new file
 * behind an exclusive holder that didn't lock it. Shared holders lock the file when they can open it (read-only, so a
 * cron job running as another user can too), and otherwise take the database lock.
 */
function domainmod_restore_lock($exclusive = false, $wait = 0)
{
    global $domainmod_restore_lock_handle, $domainmod_restore_lock_mode, $domainmod_restore_db_pending;

    $mode = $exclusive ? 'exclusive' : 'shared';
    // An exclusive request never takes the shared database lock on connecting (it would hold a slot while waiting for
    // every slot)
    if ($exclusive) $domainmod_restore_db_pending = false;

    if (domainmod_restore_lock_mode() !== '') {
        if (domainmod_restore_lock_mode() === $mode || domainmod_restore_lock_mode() === 'exclusive') {
            return array('state' => 'held', 'flag' => domainmod_restore_flag_exists());
        }
        domainmod_restore_unlock();
    }

    $path = domainmod_restore_path('lock');
    $deadline = microtime(true) + max(0, (float) $wait);
    clearstatcache();

    if ($exclusive) {

        // A temp folder this process can't search would hide the lock file and the flag, so nothing is taken
        if (!is_file(DIR_TEMP . '/index.php')) {
            error_log('DomainMOD: the temp folder ' . DIR_TEMP . " can't be searched, so the restore lock can't be taken");
            return array('state' => 'error', 'flag' => false);
        }
        $pdo = domainmod_restore_db_connection();
        if ($pdo === null) return array('state' => 'error', 'flag' => domainmod_restore_flag_exists(), 'reason' => 'database');
        if (!domainmod_restore_db_lock($pdo, true, $wait)) return array('state' => 'busy', 'flag' => domainmod_restore_flag_exists());

        // Created where this process can write temp/ (the command line too, so a restore run there always holds the file
        // lock, which no lost database connection can take away)
        // Made with its final mode (0664) by the umask, never a chmod() by name afterwards, which a symlink swapped in
        // between could point at another file. 'x' never follows an existing name.
        if (!file_exists($path) && !is_link($path)) {
            $umask = umask(0002);
            $created = @fopen($path, 'x');
            umask($umask);
            if ($created !== false) fclose($created);
            clearstatcache();
        }
        // Something at the lock file's name that this process can't open as a file (such as a symlink whose target
        // open_basedir hides) may be what others are locking, so nothing is taken: the name listed in temp/ but not
        // seen as a file, or a writable temp/ where the name still couldn't be made
        if (!is_file($path)) {
            $listed = @scandir(DIR_TEMP);
            if ((is_array($listed) && in_array('restore.lock', $listed, true)) || is_writable(DIR_TEMP)) {
                domainmod_restore_unlock();
                error_log('DomainMOD: ' . $path . " can't be created or opened, so the restore lock can't be taken");
                return array('state' => 'error', 'flag' => domainmod_restore_flag_exists());
            }
        }
        if (is_file($path)) {
            // An exclusive flock() needs a writable handle on some file systems, so read-write first
            $handle = @fopen($path, 'r+');
            if ($handle === false) $handle = @fopen($path, 'r');
            $locked = false;
            $blocked = true;
            if ($handle !== false) {
                do {
                    $locked = flock($handle, LOCK_EX | LOCK_NB, $blocked);
                    if ($locked || !$blocked || microtime(true) >= $deadline) break;
                    // The file is held elsewhere: the database lock goes back while this waits, so requests using it
                    // keep passing, and is taken again before the next try
                    domainmod_restore_unlock();
                    usleep(200000);
                    if (!domainmod_restore_db_lock($pdo, true, max(0, $deadline - microtime(true)))) {
                        fclose($handle);
                        return array('state' => 'busy', 'flag' => domainmod_restore_flag_exists());
                    }
                } while (true);
            }
            if (!$locked) {
                if ($handle !== false) fclose($handle);
                domainmod_restore_unlock();
                error_log('DomainMOD: unable to lock ' . $path . ' exclusively');
                return array('state' => ($handle !== false && $blocked) ? 'busy' : 'error', 'flag' => domainmod_restore_flag_exists());
            }
            $domainmod_restore_lock_handle = $handle;
            $domainmod_restore_lock_mode = 'exclusive';
        }
        return array('state' => 'held', 'flag' => domainmod_restore_flag_exists());

    }

    $handle = is_file($path) ? @fopen($path, 'r') : false;
    if ($handle !== false) {
        $blocked = true;
        do {
            if (flock($handle, LOCK_SH | LOCK_NB, $blocked)) {
                $domainmod_restore_lock_handle = $handle;
                $domainmod_restore_lock_mode = 'shared';
                return array('state' => 'held', 'flag' => domainmod_restore_flag_exists());
            }
            if (!$blocked || microtime(true) >= $deadline) break;
            usleep(200000);
        } while (true);
        fclose($handle);
        // Held by an exclusive holder. A file system that can't lock at all falls through to the database lock below.
        if ($blocked) return array('state' => 'busy', 'flag' => domainmod_restore_flag_exists());
        error_log('DomainMOD: ' . $path . " can't be locked, so the database lock stands in for it");
    }

    // The lock file can't be used. The database lock stands in for it, but only where temp/ can be searched, since the
    // flag couldn't be seen otherwise.
    if (!is_file(DIR_TEMP . '/index.php')) {
        error_log('DomainMOD: the temp folder ' . DIR_TEMP . " can't be searched, so the restore flag can't be checked");
        return array('state' => 'error', 'flag' => false);
    }
    // 'fallback' only where the database can't be reached from here yet (the gate, before the bootstrap). Anywhere else,
    // a connection that fails is an error, never a pass without a lock.
    if (!defined('DB_NAME') || !class_exists('DomainMOD\\Database')) {
        return array('state' => 'fallback', 'flag' => domainmod_restore_flag_exists());
    }
    $pdo = domainmod_restore_db_connection();
    // A database that can't be reached gets the busy answer, as it does at the gate (see domainmod_restore_db_connect_failed())
    if ($pdo === null) return array('state' => 'busy', 'flag' => domainmod_restore_flag_exists());
    $got = domainmod_restore_db_lock($pdo, false, $wait);
    return array('state' => $got ? 'held' : 'busy', 'flag' => domainmod_restore_flag_exists());
}

/*
 * The database connection for the lock, when the database can be reached from here: the shared connection once the
 * autoloader and the configuration are loaded (an exclusive holder, or a request after its bootstrap), or null in the
 * gate itself, which runs before them. A connection that fails is null too.
 */
function domainmod_restore_db_connection()
{
    if (!defined('DB_NAME') || !class_exists('DomainMOD\\Database')) return null;
    try {
        return DomainMOD\Database::getInstance()->cnxx;
    } catch (\Throwable $e) {
        error_log('DomainMOD: the restore lock could not reach the database: ' . $e->getMessage());
        return null;
    }
}

/*
 * The database lock's names (its gate, and its reader slots), scoped to this install's database, the one thing every
 * process using the same tables shares whatever account it connects with (two accounts on one schema, such as during a
 * credential change, must share one lock). The name is the server's own for the database (read once by
 * domainmod_restore_db_lock()), so every spelling that reaches one database gives the same lock, and different databases
 * never share one.
 */
function domainmod_restore_db_lock_name($part)
{
    global $domainmod_restore_db_schema;
    $schema = (string) ($domainmod_restore_db_schema ?? '') !== '' ? $domainmod_restore_db_schema : DB_NAME;
    return 'domainmod_restore_' . substr(hash('sha256', $schema), 0, 20) . '_' . $part;
}

// Lets go of these named locks in one query (the slots, then the gate, in the order given), so the gate isn't held for a
// round trip per slot
function domainmod_restore_db_release($pdo, array $names)
{
    if (!$names) return;
    $pdo->query("SELECT " . implode(', ', array_map(function ($name) use ($pdo) {
        return 'RELEASE_LOCK(' . $pdo->quote($name) . ')';
    }, array_values($names))))->closeCursor();
}

// The database's name as the server stores it, or '' when it can't be read
function domainmod_restore_db_schema($pdo)
{
    $name = $pdo->query("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = DATABASE()")->fetchColumn();
    return is_string($name) ? $name : '';
}

/*
 * Raises this connection's idle timeout (wait_timeout) to a day, unless it's already longer. The current value is read
 * first and a whole number is set: MySQL turns down GREATEST(@@SESSION.wait_timeout, 86400) with error 1232, since
 * GREATEST() of the unsigned variable and a signed number gives a DECIMAL, which MySQL won't take for the variable
 * (MariaDB does).
 */
function domainmod_restore_db_keep_alive($pdo)
{
    $idle = (int) $pdo->query("SELECT @@SESSION.wait_timeout")->fetchColumn();
    if ($idle < 86400) $pdo->exec("SET SESSION wait_timeout = 86400");
}

// How many requests can hold the database lock shared at once (each holds one slot for the whole request)
const DOMAINMOD_RESTORE_DB_SLOTS = 64;

/*
 * The fallback lock, when the lock file can't be used: MariaDB named locks (GET_LOCK()), held by this request's database
 * connection until the request ends (the database lets go of them when the connection closes, so a request that dies
 * frees them at once). Shared and exclusive are made from a gate lock and DOMAINMOD_RESTORE_DB_SLOTS slot locks. A
 * shared holder takes the gate briefly (waiting up to two seconds for other shared holders passing through it), then any
 * free slot, and lets go of the gate. An exclusive holder takes the gate and then every slot without waiting, so it
 * keeps new shared holders out once it has them all. While a slot is still held, it lets go of everything and tries
 * again a moment later, so it never holds the gate (and turns new requests away) while it waits for a long backup or
 * task, nor for a request that turns out not to be allowed to upgrade. Every holder raises its connection's idle timeout, so a long backup,
 * restore, or upgrade can't lose its locks to the server closing an idle connection. Nobody asks for the gate while
 * holding a slot, so the two can't deadlock. Waits up to $wait seconds, and returns whether it got the lock.
 */
function domainmod_restore_db_lock($pdo, $exclusive, $wait = 0)
{
    global $domainmod_restore_db_mode, $domainmod_restore_db_held, $domainmod_restore_db_schema;

    // The names come from the server's name for the database, so a lock is never taken under any other name
    if ((string) ($domainmod_restore_db_schema ?? '') === '') {
        try {
            $domainmod_restore_db_schema = domainmod_restore_db_schema($pdo);
        } catch (\Throwable $e) {
            $domainmod_restore_db_schema = '';
        }
        if ($domainmod_restore_db_schema === '') return false;
    }

    // A shared slot this request already holds goes first, so it never holds a slot while waiting for the gate (another
    // exclusive request holding the gate would wait for that slot)
    if ($exclusive && domainmod_restore_db_mode() === 'shared') domainmod_restore_unlock();

    $deadline = microtime(true) + max(0, (float) $wait);
    $get = function ($name, $seconds) use ($pdo) {
        $stmt = $pdo->prepare("SELECT GET_LOCK(:lock_name, :wait)");
        $stmt->bindValue('lock_name', $name, PDO::PARAM_STR);
        $stmt->bindValue('wait', max(0, (int) ceil($seconds)), PDO::PARAM_INT);
        $stmt->execute();
        $got = (int) $stmt->fetchColumn() === 1;
        $stmt->closeCursor();
        return $got;
    };
    $release = function (array $names) use ($pdo) {
        domainmod_restore_db_release($pdo, $names);
    };
    $slots = array();
    for ($slot = 0; $slot < DOMAINMOD_RESTORE_DB_SLOTS; $slot++) $slots[] = domainmod_restore_db_lock_name('slot' . $slot);

    // A database error (such as a server restarting) means the lock wasn't had, which the caller refuses as busy
    $held = array();
    try {

        // The connection's idle timeout is raised first, so a request whose work runs on another connection (a backup's
        // dump, a restore's import) can't lose its lock to the server closing this one while it waits
        domainmod_restore_db_keep_alive($pdo);

        $gate = domainmod_restore_db_lock_name('gate');

        if ($exclusive) {
            // Each try is one query to check every slot is free and, only then, one more to take them all, so the gate is
            // held for two round trips at most. Shared holders take a slot only through the gate, so nothing takes one in
            // between.
            $quoted = array_map(array($pdo, 'quote'), $slots);
            do {
                if (!$get($gate, $deadline - microtime(true))) return false;
                $held = array($gate);
                $free = $pdo->query("SELECT " . implode(', ', array_map(function ($name) {
                    return 'IS_FREE_LOCK(' . $name . ')';
                }, $quoted)))->fetch(PDO::FETCH_NUM);
                if (count(array_filter((array) $free, function ($value) { return (int) $value === 1; })) === DOMAINMOD_RESTORE_DB_SLOTS) {
                    $got = $pdo->query("SELECT " . implode(', ', array_map(function ($name) {
                        return 'GET_LOCK(' . $name . ', 0)';
                    }, $quoted)))->fetch(PDO::FETCH_NUM);
                    foreach ($slots as $index => $name) {
                        if ((int) ($got[$index] ?? 0) === 1) $held[] = $name;
                    }
                    if (count($held) === DOMAINMOD_RESTORE_DB_SLOTS + 1) {
                        $domainmod_restore_db_mode = 'exclusive';
                        $domainmod_restore_db_held = $held;
                        return true;
                    }
                }
                // A slot is still in use: everything goes back, so requests keep passing while this waits
                $release(array_reverse($held));
                $held = array();
                if (microtime(true) >= $deadline) return false;
                usleep(200000);
            } while (true);
        }

        if (!$get($gate, max(2, $deadline - microtime(true)))) return false;
        $held[] = $gate;

        // The free slots, found in one query, then the first that can still be had (another request may take one first)
        do {
            $free = $pdo->query("SELECT " . implode(', ', array_map(function ($name) use ($pdo) {
                return 'IS_FREE_LOCK(' . $pdo->quote($name) . ')';
            }, $slots)))->fetch(PDO::FETCH_NUM);
            foreach ($slots as $index => $name) {
                if ((int) ($free[$index] ?? 0) !== 1 || !$get($name, 0)) continue;
                $release(array($gate));
                $domainmod_restore_db_mode = 'shared';
                $domainmod_restore_db_held = array($name);
                return true;
            }
            if (microtime(true) >= $deadline) break;
            usleep(200000);
        } while (true);
        $release(array($gate));
        return false;

    } catch (\Throwable $e) {
        error_log('DomainMOD: the restore lock could not use the database: ' . $e->getMessage());
        try {
            $release(array_reverse($held));
        } catch (\Throwable $release_error) {
        }
        return false;
    }
}

/*
 * Whether this request still holds the lock it took. A database lock is checked whenever one was taken (every one of
 * its named locks must still belong to this connection, which a restart or a killed connection would have ended),
 * even alongside the file lock, since requests that can't use the file rely on it. Otherwise the file lock, which
 * lasts as long as the process. A backup checks this before it's finished, and a restore before its import, since
 * both read or write through another connection.
 */
function domainmod_restore_lock_still_held()
{
    global $domainmod_restore_lock_handle, $domainmod_restore_db_held;
    if (empty($domainmod_restore_db_held)) return is_resource($domainmod_restore_lock_handle ?? null);
    $pdo = domainmod_restore_db_connection();
    if ($pdo === null) return false;
    try {
        foreach ($domainmod_restore_db_held as $name) {
            $stmt = $pdo->prepare("SELECT IS_USED_LOCK(:lock_name) = CONNECTION_ID()");
            $stmt->bindValue('lock_name', $name, PDO::PARAM_STR);
            $stmt->execute();
            $mine = (int) $stmt->fetchColumn() === 1;
            $stmt->closeCursor();
            if (!$mine) return false;
        }
        return true;
    } catch (\Throwable $e) {
        return false;
    }
}

// The mode this request holds the database lock in ('shared' or 'exclusive'), or '' when it holds none
function domainmod_restore_db_mode()
{
    global $domainmod_restore_db_mode;
    return (string) ($domainmod_restore_db_mode ?? '');
}

/*
 * Called once the shared database connection exists (DomainMOD\Database::getInstance()), before its first query: a
 * request the gate let through without the lock file takes the database lock shared now, or stops with the busy message
 */
function domainmod_restore_db_connected($pdo)
{
    global $domainmod_restore_db_pending;
    if (empty($domainmod_restore_db_pending)) return;
    $domainmod_restore_db_pending = false;
    if (domainmod_restore_db_mode() !== '') return;
    if (!domainmod_restore_db_lock($pdo, false, 0)) domainmod_restore_refuse(domainmod_restore_message(false));
    // A restore that failed while this request waited leaves its flag, so it's checked again now the lock is held
    if (domainmod_restore_flag_exists()) {
        domainmod_restore_unlock();
        domainmod_restore_refuse(domainmod_restore_message(true));
    }
}

// The refusal when the restore lock can't be taken because the database can't be reached (the lock's reason 'database')
function domainmod_restore_db_unreachable_message($action)
{
    if ($action === 'install') return _("DomainMOD can't connect to its database, so the installation can't run. Check the database settings in _includes/config.inc.php, then try again.");
    if ($action === 'restore') return _("DomainMOD can't connect to its database, so the restore can't run. Check the database settings in _includes/config.inc.php, then try again.");
    return _("DomainMOD can't connect to its database, so the upgrade can't run. Check the database settings in _includes/config.inc.php, then try again.");
}

// The warning administrators see while a restore can't run here (see DomainMOD\RestoreLock::restoreReady())
function domainmod_restore_fallback_message()
{
    return _("DomainMOD can't write to its temp folder or lock its temp/restore.lock file. It keeps working, using a database lock where it needs one, but restores are turned off until the web server can write to both, on a file system that supports file locks.");
}

// Adds that warning to an administrator's page messages, once, while a restore can't run here
function domainmod_restore_warn_admin()
{
    if (!class_exists('DomainMOD\\RestoreLock') || DomainMOD\RestoreLock::restoreReady()) return;
    $message = htmlspecialchars(domainmod_restore_fallback_message(), ENT_QUOTES, 'UTF-8');
    $_SESSION['s_message_warning'] = $_SESSION['s_message_warning'] ?? '';
    if (strpos($_SESSION['s_message_warning'], $message) === false) $_SESSION['s_message_warning'] .= $message . '<BR>';
}

// Lets go of the restore lock this request holds, if any
function domainmod_restore_unlock()
{
    global $domainmod_restore_lock_handle, $domainmod_restore_lock_mode, $domainmod_restore_db_mode, $domainmod_restore_db_held;

    if (is_resource($domainmod_restore_lock_handle ?? null)) {
        flock($domainmod_restore_lock_handle, LOCK_UN);
        fclose($domainmod_restore_lock_handle);
    }
    $domainmod_restore_lock_handle = null;
    $domainmod_restore_lock_mode = null;

    if (!empty($domainmod_restore_db_held)) {
        $pdo = domainmod_restore_db_connection();
        // A connection that's gone has released them already, so a failed release never replaces the request's answer
        if ($pdo !== null) {
            try {
                domainmod_restore_db_release($pdo, array_reverse($domainmod_restore_db_held));
            } catch (\Throwable $e) {
                // nothing more to do
            }
        }
    }
    $domainmod_restore_db_mode = null;
    $domainmod_restore_db_held = array();
}

// The mode this request holds the lock in ('shared' or 'exclusive'), or '' when it holds none
function domainmod_restore_lock_mode()
{
    global $domainmod_restore_lock_handle, $domainmod_restore_lock_mode;
    if (is_resource($domainmod_restore_lock_handle ?? null)) return (string) $domainmod_restore_lock_mode;
    return domainmod_restore_db_mode();
}

/*
 * Whether this request holds the lock file exclusively. A restore and its recovery need it, not only the database
 * lock: the import runs on its own connection, and a file lock is the one no lost database connection can take away.
 */
function domainmod_restore_holds_file_exclusive()
{
    global $domainmod_restore_lock_handle, $domainmod_restore_lock_mode;
    return is_resource($domainmod_restore_lock_handle ?? null) && $domainmod_restore_lock_mode === 'exclusive';
}

// The message for a request that can't go on: the restore message when the flag exists, otherwise the busy message
function domainmod_restore_message($flag)
{
    return $flag ? _('A restore is in progress. Please try again in a minute.')
        : _('DomainMOD is busy. Please try again in a minute.');
}

// The message administrators see when the lock file can't be opened
function domainmod_restore_lock_error_message()
{
    return _("DomainMOD can't open its restore lock in the temp folder, so scheduled tasks and changes are paused.");
}

// Whether the request expects JSON (the list endpoints' DataTables requests), which htmx requests never do
function domainmod_restore_wants_json()
{
    return ($_SERVER['HTTP_HX_REQUEST'] ?? '') !== 'true'
        && strpos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false;
}

/*
 * A write refused part-way (DomainMOD\RestoreLockException, such as a lock that stayed busy), wherever it's thrown,
 * ends the request with its message, as the gate's own refusal does, rather than an error page. A page's own catch
 * blocks still roll back and log it first. Anything else is left to PHP as before.
 */
function domainmod_restore_exception_handler($e)
{
    if ($e instanceof DomainMOD\RestoreLockException) domainmod_restore_refuse($e->getMessage());
    // Two writes that met (a deadlock, or a row another request changed, errors 1213 and 1020): the transaction that met
    // it was rolled back, though an earlier one in the same request may have been saved, so the message asks the user to
    // check (a form that catches it itself keeps what was typed)
    if (domainmod_restore_is_conflict($e)) domainmod_restore_refuse(domainmod_restore_conflict_message(), 409);
    throw $e;
}

// Whether $e is a database conflict between two writes: a deadlock (1213) or a row changed since it was read (1020)
function domainmod_restore_is_conflict($e)
{
    return $e instanceof PDOException && in_array((int) ($e->errorInfo[1] ?? 0), array(1020, 1213), true);
}

// What the user sees after a conflict (see domainmod_restore_is_conflict())
function domainmod_restore_conflict_message()
{
    return _("Someone else was changing the same records at the same time, so this couldn't finish. Please go back, check what was saved, and try again.");
}

// A Refresh's message for the same (a Refresh may have recorded its status check separately, so it doesn't say nothing
// was saved)
function domainmod_restore_conflict_refresh_message()
{
    return _("Someone else changed this at the same time, so the refresh couldn't finish. Please try again.");
}

// The same, for a form that shows it above what was typed
function domainmod_restore_conflict_form_message()
{
    return _('Someone else changed this at the same time, so nothing was saved. Please try again.');
}

/*
 * Answers $status (503 with Retry-After: 60, or a database conflict's 409) and ends the request, in the request's own
 * form, without reading the database again or building the layout: JSON for the list endpoints' DataTables requests,
 * the message alone for an htmx request, the message as a line of text on the command line, and otherwise a small
 * standalone page in the user's language
 */
function domainmod_restore_refuse($message, $status = 503)
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . "\n");
        exit(1);
    }

    // Output buffered so far is dropped, stopping at a buffer that can't be removed (as Export::endOutputBuffers() does)
    while (ob_get_level() > 0) {
        $buffer = ob_get_status();
        if (!((int) ($buffer['flags'] ?? 0) & PHP_OUTPUT_HANDLER_REMOVABLE) || !@ob_end_clean()) break;
    }
    http_response_code($status);
    if ($status === 503) header('Retry-After: 60');
    header('Cache-Control: no-store');

    if (domainmod_restore_wants_json()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(array('error' => $message), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        exit;
    }

    // An htmx request gets plain text (a 503 or a conflict's 409), marked with X-DomainMOD-Refused, which the footer's
    // shared htmx error handler and the Refresh scripts show as text
    if (($_SERVER['HTTP_HX_REQUEST'] ?? '') === 'true') {
        header('Content-Type: text/plain; charset=utf-8');
        header('X-DomainMOD-Refused: 1');
        echo $message;
        exit;
    }

    $text = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    header('Content-Type: text/html; charset=utf-8');

    // The user's colour mode (as Layout::colourMode() reads it), through color-scheme and the system colours, since the
    // page has no stylesheet: light, dark, or the browser's own preference
    $dark_mode = (($_SESSION['s_is_logged_in'] ?? 0) == 1) ? ($_SESSION['s_dark_mode'] ?? 2) : 2;
    $scheme = $dark_mode === 0 ? 'light' : ($dark_mode === 1 ? 'dark' : 'light dark');

    echo '<!DOCTYPE html>' . "\n" . '<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="color-scheme" content="' . $scheme . '">'
        . '<title>DomainMOD</title></head><body style="font-family: sans-serif; margin: 3em auto; max-width: 40em; padding: 0 1em; background: Canvas; color: CanvasText;">'
        . '<p>' . $text . '</p></body></html>';
    exit;
}

/*
 * Whether the entry script takes the lock itself: the first file PHP ran, resolved with realpath() and compared with this
 * fixed list under DIR_ROOT, never anything a page, a request, or a local helper could set. Backup & Restore, checks.php
 * (sign-in, and the upgrade), cron.php, update.php, restore.php, logout.php, and the installer's pages.
 */
function domainmod_restore_gate_exempt()
{
    $files = get_included_files();
    $entry = realpath($files[0] ?? '');
    if ($entry === false) return false;

    foreach (domainmod_restore_gate_exempt_files() as $file) {
        $exempt = realpath(DIR_ROOT . '/' . $file);
        if ($exempt !== false && $exempt === $entry) return true;
    }

    $install = realpath(DIR_ROOT . '/install');
    return $install !== false && strpos($entry, $install . DIRECTORY_SEPARATOR) === 0;
}

// The entry scripts that take the lock themselves (the installer's pages are matched by their folder)
function domainmod_restore_gate_exempt_files()
{
    return array('admin/backup/index.php', 'checks.php', 'cron.php', 'update.php', 'restore.php', 'logout.php');
}

/*
 * The gate itself: takes the lock shared, and refuses with the restore message when the flag exists, or the busy
 * message when the lock is busy (an upgrade or a restore holds it). When the lock file can't be used, the request takes
 * the database lock shared instead, as soon as it connects to the database and before its first query (see
 * domainmod_restore_db_connected()), since the gate runs before the database is configured.
 */
function domainmod_restore_gate()
{
    global $domainmod_restore_db_pending;

    $lock = domainmod_restore_lock(false);

    // The sign-in page also says how a restore that stopped part-way is finished, for someone who can't sign in
    if ($lock['flag'] && realpath(get_included_files()[0] ?? '') === realpath(DIR_ROOT . '/index.php')) {
        domainmod_restore_refuse(domainmod_restore_message(true) . ' '
            . _("If it stopped part-way, an administrator who's still signed in can finish it from Backup & Restore, or it can be finished on the server with php restore.php --recover."));
    }
    if ($lock['flag']) domainmod_restore_refuse(domainmod_restore_message(true));
    if ($lock['state'] === 'busy') domainmod_restore_refuse(domainmod_restore_message(false));
    if ($lock['state'] === 'error') domainmod_restore_refuse(domainmod_restore_lock_error_message());
    if ($lock['state'] === 'fallback') $domainmod_restore_db_pending = true;
}

/*
 * The installer's pages are exempt, so install/go.php can take the lock exclusively itself. The others take the shared
 * lock the same way as any page (the lock file, or the database lock when the file can't be used), so none of them reads
 * the schema while install/go.php is creating it.
 */
function domainmod_restore_gate_installer()
{
    $entry = realpath(get_included_files()[0] ?? '');
    $install = realpath(DIR_ROOT . '/install');
    if ($entry === false || $install === false || strpos($entry, $install . DIRECTORY_SEPARATOR) !== 0) return;
    if ($entry === realpath(DIR_ROOT . '/install/go.php')) return;
    domainmod_restore_gate();
}

if (!domainmod_restore_gate_exempt()) {
    domainmod_restore_gate();
} else {
    domainmod_restore_gate_installer();
}

set_exception_handler('domainmod_restore_exception_handler');
