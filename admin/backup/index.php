<?php
/**
 * /admin/backup/index.php
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

/*
 * The restore lock (see _includes/restore-gate.inc.php), taken before the first database read. A restore or Finish
 * Restore takes it exclusively, and anything else takes it shared. While the restore flag is there (a restore that died
 * with nobody holding the lock), the page takes it exclusively too, and shows only Finish Restore.
 */
$restore_post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
// A POST from a session that isn't signed in, has no administrator access (as cached at sign-in), or lacks its security
// token never takes the exclusive lock (it's refused below, as any page refuses it)
$restore_token_valid = $restore_post && (new DomainMOD\Csrf())->isValid();
$restore_refused_post = $restore_post && (($_SESSION['s_is_logged_in'] ?? 0) != 1 || ($_SESSION['s_is_admin'] ?? 0) != 1
    || !$restore_token_valid);
// A signed-in session with its token but without the cached flag (a sign-in that stopped at the upgrade notice, such as
// on a database whose version doesn't match, never caches it) has its access checked live under the shared lock below,
// and moves on to the exclusive lock only if it's an administrator who isn't read-only
$restore_live_check = $restore_refused_post && ($_SESSION['s_is_logged_in'] ?? 0) == 1 && $restore_token_valid
    && ($_SESSION['s_is_admin'] ?? 0) != 1;
// A session whose administrator access was checked live for a restore (below) may also finish that restore, if it died:
// its marker holds the user and that restore's own id, which the flag must still hold
$restore_marker = $_SESSION['s_restore_admin_checked'] ?? null;
$restore_admin_session = ($_SESSION['s_is_logged_in'] ?? 0) == 1 && (($_SESSION['s_is_admin'] ?? 0) == 1
    || (is_array($restore_marker) && (int) ($restore_marker['user'] ?? 0) === (int) ($_SESSION['s_user_id'] ?? -1)
        && is_string($restore_marker['restore'] ?? null) && $restore_marker['restore'] === DomainMOD\RestoreRecovery::flagId()));
if ($restore_refused_post) $restore_post = false;
// A restore and Finish Restore keep their files in temp/, so neither starts while it isn't writable (everything else
// goes on with the database lock in place of the lock file)
if ($restore_post && !DomainMOD\RestoreLock::restoreReady()) {
    $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . htmlspecialchars(DomainMOD\RestoreLock::restoreMessage(), ENT_QUOTES, 'UTF-8') . '<BR>';
    header('Location: ' . WEB_ROOT . '/admin/backup/');
    exit;
}
if ($restore_post) {

    $restore_lock = DomainMOD\RestoreLock::exclusive();
    if ($restore_lock['state'] === 'busy') domainmod_restore_refuse(DomainMOD\RestoreLock::message($restore_lock['flag']));
    // A restore or Finish Restore never runs without the exclusive lock, the lock file's included
    if (($restore_lock['reason'] ?? '') === 'database') domainmod_restore_refuse(domainmod_restore_db_unreachable_message('restore'));
    if ($restore_lock['state'] !== 'held') domainmod_restore_refuse(domainmod_restore_lock_error_message());
    if (!domainmod_restore_holds_file_exclusive()) domainmod_restore_refuse(DomainMOD\RestoreLock::restoreMessage());

} else {

    $restore_lock = domainmod_restore_lock(false);
    if ($restore_lock['state'] === 'busy') domainmod_restore_refuse(DomainMOD\RestoreLock::message($restore_lock['flag']));
    // As the gate does: neither the lock file nor the database lock can be used safely (such as a temp folder that
    // can't be searched, where the flag can't be checked)
    if ($restore_lock['state'] === 'error') domainmod_restore_refuse(domainmod_restore_lock_error_message());
    // A POST without its token, or any request from a session that isn't an administrator's (as cached at sign-in, or
    // checked live for its own restore), never moves on to the exclusive lock, so it can't hold up an administrator's
    // Finish Restore (access is checked again live once the exclusive lock is held)
    if ($restore_lock['flag'] && (!$restore_admin_session || ($restore_refused_post && !$restore_token_valid))) {
        domainmod_restore_refuse(DomainMOD\RestoreLock::message(true));
    }
    // A session let in by its earlier live check only (not the flag cached at sign-in) is checked live again before it
    // takes the exclusive lock, so an account that's lost its access since can't hold up an administrator's Finish Restore
    if ($restore_lock['flag'] && ($_SESSION['s_is_admin'] ?? 0) != 1) {
        try {
            $restore_auth = new DomainMOD\System();
            $restore_flags = $restore_auth->authCheckResult() === 'valid' ? $restore_auth->authFlags() : array('admin' => 0, 'read_only' => 1);
        } catch (Throwable $e) {
            // Tables a dead restore left that the check can't read: the restore message, which says how to finish it
            $restore_flags = array('admin' => 0, 'read_only' => 1);
        }
        if ($restore_flags['admin'] !== 1 || $restore_flags['read_only'] !== 0) domainmod_restore_refuse(DomainMOD\RestoreLock::message(true));
    }
    if ($restore_lock['state'] === 'held' && $restore_lock['flag']) {
        domainmod_restore_unlock();
        $restore_lock = DomainMOD\RestoreLock::exclusive();
        // A lock that can't be taken at all (such as a lock file this user can't open) is a permissions problem, not a
        // busy one, so it's named
        if ($restore_lock['state'] === 'error') domainmod_restore_refuse(($restore_lock['reason'] ?? '') === 'database'
            ? domainmod_restore_db_unreachable_message('restore') : DomainMOD\RestoreLock::restoreMessage());
        if ($restore_lock['state'] !== 'held') domainmod_restore_refuse(DomainMOD\RestoreLock::message($restore_lock['flag']));
        // Finish Restore is only offered where it can run
        if (!domainmod_restore_holds_file_exclusive() || !DomainMOD\RestoreLock::restoreReady()) {
            domainmod_restore_refuse(DomainMOD\RestoreLock::restoreMessage());
        }
        // A Finish Restore from the session whose access was checked live for the restore goes ahead (the live checks
        // below still apply)
        if ($restore_refused_post && $restore_token_valid) {
            $restore_post = true;
            $restore_refused_post = false;
            $restore_live_check = false;
        }
    }
    if ($restore_live_check && $restore_lock['state'] === 'held' && !$restore_lock['flag']) {
        // The same sign-in and access check every page makes (the session itself is valid, and the account is an
        // active administrator who isn't read-only), read live
        $restore_auth = new DomainMOD\System();
        $restore_valid = $restore_auth->authCheckResult() === 'valid';
        $restore_flags = $restore_valid ? $restore_auth->authFlags() : array('admin' => 0, 'read_only' => 1);
        // A session that's no longer valid goes on as a page view, whose sign-in check signs it out with its own message.
        // Anyone else is told why nothing happened.
        if ($restore_valid && ($restore_flags['admin'] !== 1 || $restore_flags['read_only'] !== 0)) {
            $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . htmlspecialchars(_("Only an administrator who isn't read-only can restore a backup."), ENT_QUOTES, 'UTF-8') . '<BR>';
            header('Location: ' . WEB_ROOT . '/admin/backup/');
            exit;
        }
    }
    // A valid administrator's session then goes on to the restore
    if ($restore_live_check && $restore_lock['state'] === 'held' && !$restore_lock['flag'] && ($restore_valid ?? false)) {
        if (!DomainMOD\RestoreLock::restoreReady()) {
            $_SESSION['s_message_danger'] = ($_SESSION['s_message_danger'] ?? '') . htmlspecialchars(DomainMOD\RestoreLock::restoreMessage(), ENT_QUOTES, 'UTF-8') . '<BR>';
            header('Location: ' . WEB_ROOT . '/admin/backup/');
            exit;
        }
        // As for any restore: the exclusive lock, the lock file's included
        domainmod_restore_unlock();
        $restore_lock = DomainMOD\RestoreLock::exclusive();
        if ($restore_lock['state'] === 'busy') domainmod_restore_refuse(DomainMOD\RestoreLock::message($restore_lock['flag']));
        if (($restore_lock['reason'] ?? '') === 'database') domainmod_restore_refuse(domainmod_restore_db_unreachable_message('restore'));
        if ($restore_lock['state'] !== 'held') domainmod_restore_refuse(domainmod_restore_lock_error_message());
        if (!domainmod_restore_holds_file_exclusive()) domainmod_restore_refuse(DomainMOD\RestoreLock::restoreMessage());
        // Remembered once the restore itself starts (see below)
        $restore_via_live_check = true;
        $restore_post = true;
        $restore_refused_post = false;
    }

}

$deeb = DomainMOD\Database::getInstance();
$system = new DomainMOD\System();
$log = new DomainMOD\Log('/admin/backup/index.php');
$layout = new DomainMOD\Layout();
$form = new DomainMOD\Form();

require_once DIR_INC . '/head.inc.php';
/*
 * The sign-in and administrator checks, from the database as it is now (never the session's cached flags), so a restore
 * that removed someone's administrator access also removes their right to finish a restore. While a restore that died
 * part-way has left tables the checks can't read, the page says how to finish it on the server instead of failing.
 */
$restore_unreadable = _("A restore didn't finish, and its database can't be read yet. Finish it on the server with php restore.php --recover.");
try {
    require_once DIR_INC . '/debug.inc.php';
    require_once DIR_INC . '/settings/admin-backup-main.inc.php';
    // With the flag up, a session the restored database can't confirm (such as a settings table with no row yet) gets
    // the recovery message rather than being signed out, since signing in again is refused until the restore finishes
    if ($restore_lock['flag'] && $system->authCheckResult() === 'invalid') domainmod_restore_refuse($restore_unreadable);
    $system->authCheck();
} catch (PDOException $e) {
    if (!$restore_lock['flag']) throw $e;
    domainmod_restore_refuse($restore_unreadable);
}
$auth_flags = $system->authFlags();
$system->checkAdminUser($auth_flags['admin']);
// Read-only users can't restore or finish a restore
if ($restore_post && $auth_flags['read_only'] !== 0) {

    $_SESSION['s_message_danger'] = $_SESSION['s_message_danger'] ?? '';
    $_SESSION['s_message_danger'] .= _('You are not authorized to perform that action.') . '<BR>';
    header('Location: ' . WEB_ROOT . '/admin/backup/');
    exit;

}
$pdo = $deeb->cnxx;

// Restores are off while temp/ isn't writable (everything else goes on, with the database lock where it's needed)
domainmod_restore_warn_admin();

// What's left of an earlier restore: its flag (it died part-way) or a staged copy's record (it died during its scan).
// What the Finish Restore form is shown for: the flag's own value (see RestoreRecovery::finishValue()), or the staged
// record's token. A flag without an id is given one when the page is shown to an administrator who can finish it, never
// on a POST, so a posted form is always checked against the flag as it was shown.
$restore_flag_value = DomainMOD\RestoreRecovery::finishValue(!$restore_post && $auth_flags['read_only'] === 0);
$restore_flag_id = DomainMOD\RestoreRecovery::flagId();
$restore_staged = DomainMOD\RestoreRecovery::stagedRecord();
$restore_finish_for = $restore_flag_value !== '' ? $restore_flag_value
    : ($restore_staged !== null && ($restore_staged['kind'] ?? '') === DomainMOD\RestoreRecovery::KIND_WEB ? 'staged:' . ($restore_staged['token'] ?? '') : '');

// Remove any files left in the web-accessible temp folder by older versions, and warn about any that can't be deleted
$legacy_temp_files = $restore_flag_id === '' ? $system->removeLegacyTempFiles() : array();

if (!empty($legacy_temp_files)) {

    $_SESSION['s_message_danger'] = $_SESSION['s_message_danger'] ?? '';
    $_SESSION['s_message_danger'] .= $system->getLegacyTempFilesWarning($legacy_temp_files) . '<BR>';

}

// Adds messages to the session, which a long restore has let go of (see below), so the next page shows them
$add_messages = function (array $messages, $key = 's_message_danger') {
    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
    $_SESSION[$key] = $_SESSION[$key] ?? '';
    foreach ($messages as $message) {
        if ($message !== '') $_SESSION[$key] .= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '<BR>';
    }
};

use iamdual\Uploader;

if ($restore_post && isset($_POST['finish_restore'])) {

    /*
     * Finish Restore: only for the state the form was shown for, so a form left open from an earlier state can't act on
     * a later restore. The session is let go of first, so a Sign Out in another tab isn't held behind the recovery. A
     * value that isn't a string (a crafted form) is refused like one from an earlier state.
     */
    $finish_restore_posted = is_string($_POST['finish_restore']) ? $_POST['finish_restore'] : '';
    if ($restore_finish_for !== '' && hash_equals($restore_finish_for, $finish_restore_posted)) {

        // The restore's own files have to be readable here, or a verified import would be judged unverified
        if (!DomainMOD\RestoreRecovery::canRecoverHere()) {
            clearstatcache();
            $_SESSION['s_message_danger'] .= (is_writable(DIR_TEMP)
                ? _("The restore's files in the temp folder can't be read by the web server, so the restore can't be finished here. Run php restore.php --recover on the server.")
                : _("The web server can't write to DomainMOD's temp folder, so the restore can't be finished. Make the folder writable by the web server, then try again.")) . '<BR>';
            header("Location: " . WEB_ROOT . "/admin/backup/");
            exit;
        }

        session_write_close();
        $recovery = DomainMOD\RestoreRecovery::recover(DomainMOD\RestoreRecovery::KIND_WEB);
        if ($recovery['ok'] && $restore_flag_id !== '') {
            /*
             * A finished recovery signs the administrator out straight away, as a finished restore does, and the sign-in
             * page says how it went, once (see logout.php): finished, or finished with a database that may be partly
             * restored, with the recovery's own notices. The lock goes first, so waiting for the session (another tab
             * may hold it while it waits for the lock) can't hold both up.
             */
            domainmod_restore_unlock();
            if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
            $_SESSION['s_restore_signin'] = !empty($recovery['imported'])
                ? array('danger' => $recovery['notices'], 'success' => array(_('The restore was finished. Please sign in again.')))
                : array('danger' => array_merge($recovery['notices'], array(_('The restore was finished, but your database may now be partly restored, so restore a complete backup before going on.'),
                    _('Please sign in again.'))), 'success' => array());
            header("Location: " . WEB_ROOT . "/logout.php");
            exit;
        }
        // A staged copy left without a restore was only cleared up, so the administrator stays signed in
        if ($recovery['ok']) $add_messages($recovery['notices']);
        if (!$recovery['ok']) {
            $add_messages(array(_("The restore couldn't be finished. Please check the server's error log, then try again.")));
            // Shown in this response, since the clean-up may already have given every user a new two-factor stamp
            $restore_failed = true;
            // The form shown again carries the flag's value as it is now, since the recovery may have given it an id
            $restore_flag_value = DomainMOD\RestoreRecovery::finishValue();
            if ($restore_flag_value !== '') $restore_finish_for = $restore_flag_value;
        }

    }

    if (empty($restore_failed)) {
        header("Location: " . WEB_ROOT . "/admin/backup/");
        exit;
    }

} elseif ($restore_post && $restore_finish_for !== '') {

    // A restore can't start while an earlier one is waiting to be finished
    header("Location: " . WEB_ROOT . "/admin/backup/");
    exit;

} elseif ($restore_post) {

    try {

        if (isset($_FILES["file"])) {

            $upload = new Uploader($_FILES["file"]);
            $upload->allowed_extensions(array("sql"));
            $upload->max_size(50); // in MB

            // The upload is copied into PHP's temporary folder for the restore (see DomainMOD\RestoreRecovery), never into
            // the web-accessible temp folder, and neither may be inside the web root
            if ($upload->get_tmp_name() !== null && $upload->get_tmp_name() !== '' && $system->isInsideWebRoot(dirname($upload->get_tmp_name()))) {

                // Don't process an upload that PHP stored somewhere web-accessible
                $log_message = 'Upload refused because PHP\'s upload folder is web-accessible';
                $log_extra = array('Upload folder' => dirname($upload->get_tmp_name()));
                $log->critical($log_message, $log_extra);

                $_SESSION['s_message_danger'] = $_SESSION['s_message_danger'] ?? '';
                $_SESSION['s_message_danger'] .= _('This file can\'t be processed because PHP\'s upload folder (upload_tmp_dir) is inside your web root. Please move it outside your web root and try again.') . '<BR>';

            } elseif ($system->isInsideWebRoot(sys_get_temp_dir())) {

                // Don't copy the backup for the restore somewhere web-accessible
                $log_message = 'Restore refused because PHP\'s temporary folder is web-accessible';
                $log_extra = array('Temporary folder' => sys_get_temp_dir());
                $log->critical($log_message, $log_extra);

                $_SESSION['s_message_danger'] = $_SESSION['s_message_danger'] ?? '';
                $_SESSION['s_message_danger'] .= _('This file can\'t be processed because PHP\'s temporary folder (sys_temp_dir) is inside your web root. Please move it outside your web root and try again.') . '<BR>';

            } elseif (!$upload->check() || !is_uploaded_file($upload->get_tmp_name())) {

                $_SESSION['s_message_danger'] = $_SESSION['s_message_danger'] ?? '';
                $_SESSION['s_message_danger'] .= _('Please choose a backup file to restore.') . '<BR>';

            } else {

                $filename = $upload->get_tmp_name();
                $username = $dbusername ?? '';
                $password = $dbpassword ?? '';
                $database = $dbname ?? '';
                $host = $dbhostname ?? '';

                // A session let in by the live check is remembered as this restore's, so it can finish the restore if
                // it dies part-way (see $restore_admin_session, which checks it live again then), and forgotten below
                // if the restore never left a flag
                $restore_id = bin2hex(random_bytes(16));
                if (!empty($restore_via_live_check)) {
                    $_SESSION['s_restore_admin_checked'] = array('user' => (int) ($_SESSION['s_user_id'] ?? 0), 'restore' => $restore_id);
                }

                // The session is let go of once the checks above have passed, so a Sign Out in another tab isn't held
                // behind the restore
                session_write_close();

                /*
                 * The restore (see DomainMOD\RestoreRecovery::restore()): any earlier restore's recovery, a private copy
                 * of the upload, its scan, the flag, the import, the verdict, and the cleanup (which also gives every user
                 * a new two-factor stamp, ending every session). A backup that's been cut short is turned down before
                 * anything changes, so no one is signed out.
                 */
                $restore = DomainMOD\RestoreRecovery::restore($filename, sys_get_temp_dir(), DomainMOD\RestoreRecovery::KIND_WEB,
                    function ($copy) use ($username, $password, $database, $host, $deeb) {
                        new DomainMOD\RestoreImport($copy, $username, $password, $database, $host, $deeb->connection_options, $deeb->certificate_checked);
                    }, $restore_id);

                if ($restore['error'] === '') {

                    // The sign-in page then says the restore finished (see logout.php). The lock goes first, so
                    // waiting for the session (another tab may hold it while it waits for the lock) can't hold both up.
                    domainmod_restore_unlock();
                    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
                    $_SESSION['s_restore_signin'] = array('danger' => array(),
                        'success' => array(_('The restore finished. Please sign in again.')));
                    header("Location: " . WEB_ROOT . "/logout.php");
                    exit;

                }

                try {

                    $log_message = sprintf('Unable to restore %s data', SOFTWARE_TITLE);
                    // Only the message: the exception's trace can show the database password passed to RestoreImport
                    $log_extra = array('Error' => $restore['error']);
                    $log->critical($log_message, $log_extra);

                } catch (Exception $e) {

                    // The part of the restore that ran may have broken the log table too, and the error is still
                    // shown below

                }

                // Shown in this response, since a restore that started gave every user a new two-factor stamp, which
                // ends this session on its next request
                $restore_failed = true;
                // A flag the restore left (an import or a clean-up that didn't finish) shows Finish Restore in this
                // response too, rather than the upload form the gate would refuse
                $restore_flag_id = DomainMOD\RestoreRecovery::flagId();
                if ($restore_flag_id === '' && !empty($restore_via_live_check)) {
                    if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
                    unset($_SESSION['s_restore_admin_checked']);
                }
                if ($restore_flag_id !== '') $restore_finish_for = DomainMOD\RestoreRecovery::finishValue();
                // and a staged copy it couldn't remove is shown as the page shows one on its next visit (the Finish
                // Restore form names the copy, so its notice isn't repeated in the messages)
                $restore_staged = DomainMOD\RestoreRecovery::stagedRecord();
                $restore_notices = $restore['notices'];
                if ($restore_finish_for === '' && $restore_staged !== null && ($restore_staged['kind'] ?? '') === DomainMOD\RestoreRecovery::KIND_WEB) {
                    $restore_finish_for = 'staged:' . ($restore_staged['token'] ?? '');
                    $restore_notices = array_diff($restore_notices, array(DomainMOD\RestoreRecovery::stagedNotice()));
                }

                $add_messages(DomainMOD\RestoreRecovery::failureLines($restore, true));
                $add_messages($restore_notices);

            }

        }

    } catch (Exception $e) {

        $log_message = sprintf('Unable to restore %s data', SOFTWARE_TITLE);
        $log_extra = array('Error' => $e);
        $log->critical($log_message, $log_extra);

        throw $e;

    }

    if (empty($restore_failed)) {
        header("Location: " . WEB_ROOT . "/admin/backup/");
        exit;
    }

}

// The Change History's share of the database, shown beside the backup, since it's in every backup
$history_share = '';
// Not after a failed restore, whose database may not answer (its messages are shown without it)
if ($restore_finish_for === '' && empty($restore_failed) && DomainMOD\ChangeHistory::isAvailable($pdo)) {

    $stmt = $pdo->prepare("
        SELECT SUM(data_length + index_length) AS total,
               SUM(CASE WHEN table_name IN ('change_history', 'change_operations') THEN data_length + index_length ELSE 0 END) AS history
        FROM information_schema.TABLES
        WHERE table_schema = :dbname");
    $stmt->bindValue('dbname', $dbname, PDO::PARAM_STR);
    $stmt->execute();
    $sizes = $stmt->fetch(PDO::FETCH_ASSOC);
    $stmt->closeCursor();
    if ($sizes && (float) $sizes['total'] > 0) {
        $history_share = sprintf(_('Change history: %1$s MB of %2$s MB'), number_format((float) $sizes['history'] / 1048576, 1),
            number_format((float) $sizes['total'] / 1048576, 1));
    }

}

// A staged copy the Finish Restore form can't clear (the command line's) is named in a message, since the form is only
// shown for the web's own
if ($restore_staged !== null && $restore_finish_for === '') {

    $_SESSION['s_message_danger'] = $_SESSION['s_message_danger'] ?? '';
    $staged_notice = htmlspecialchars(DomainMOD\RestoreRecovery::stagedNotice(), ENT_QUOTES, 'UTF-8');
    if (strpos($_SESSION['s_message_danger'], $staged_notice) === false) $_SESSION['s_message_danger'] .= $staged_notice . '<BR>';

}
?>
<?php require_once DIR_INC . '/doctype.inc.php'; ?>
<?php echo $layout->htmlTag(); ?>
<head>
    <title><?php echo $layout->pageTitle($page_title); ?></title>
    <?php require_once DIR_INC . '/layout/head-tags.inc.php'; ?>
</head>
<?php echo $layout->bodyTag(); ?>
<?php require_once DIR_INC . '/layout/header.inc.php'; ?>

<?php
if ($restore_finish_for !== '') {

    // An earlier restore didn't finish: only Finish Restore is shown until it has
    ob_start(); ?>
<p class="domainmod-span-all"><?php echo $restore_flag_id !== '' ? _("A restore didn't finish.") : htmlspecialchars(DomainMOD\RestoreRecovery::stagedNotice(), ENT_QUOTES, 'UTF-8'); ?></p>
<?php if ($auth_flags['read_only'] !== 0) { // A read-only administrator can't finish it, so they're told who can ?>
<p class="domainmod-span-all"><?php echo _("An administrator who isn't read-only can finish it here, or it can be finished on the server with php restore.php --recover."); ?></p>
<?php } elseif (!DomainMOD\RestoreLock::restoreReady()) { // Finish Restore can't run here until temp/ is fixed ?>
<p class="domainmod-span-all"><?php echo htmlspecialchars(DomainMOD\RestoreLock::restoreMessage(), ENT_QUOTES, 'UTF-8'); ?></p>
<?php } else { ?>
<input type="hidden" name="finish_restore" value="<?php echo htmlspecialchars($restore_finish_for, ENT_QUOTES, 'UTF-8'); ?>">
<div class="domainmod-span-all mb-3"><?php echo $layout->showButton('submit', _('Finish Restore')); ?></div>
<?php } ?><?php
    $finish_fields = ob_get_clean(); ?>
<form action="" method="post" class="domainmod-form">
<?php echo $form->showCsrfField(); ?>
<?php echo $layout->formSection(_('Finish Restore'), 'finish-restore', $finish_fields); ?>
</form>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
<?php
    exit;

} ?>
<div class="domainmod-intro">
<p><?php
echo sprintf(_('This page enables you to perform complete backups and restores of your %s database.'), SOFTWARE_TITLE) . ' ';
echo _('This allows you to export your entire system in a single file so that it can easily be backed up and restored.') . ' ';
echo sprintf(_('This is helpful if you want to perform regular backups of your data, you need to move %s to a new server, or if you want to change your installation method.'), SOFTWARE_TITLE); ?></p>
<p><?php echo _('This process backs up the data saved in your database. If you also want to save your database connection information, you should back up your /_includes/config.inc.php file.'); ?></p>
</div>

<?php
// Back up and restore in titled cards at the forms' width, like the edit pages (Layout::formSection()). The card body
// is a two-column grid on wider screens, so each item spans both columns.
ob_start(); ?>
<dl class="domainmod-span-all mb-3">
    <dt><?php echo _('Database Backup File'); ?></dt>
    <dd class="mb-0"><?php echo $layout->ltr('domainmod-backup.sql'); ?></dd>
</dl>
<?php if ($history_share !== '') { ?>
<p class="domainmod-span-all form-text mb-3"><?php echo htmlspecialchars($history_share, ENT_QUOTES, 'UTF-8'); ?></p>
<?php } ?>
<div class="domainmod-span-all mb-3"><?php echo $layout->linkButton('download/', _('Backup Entire Database'), 'primary'); ?></div><?php
echo '<div class="domainmod-form">' . $layout->formSection(_('Back Up'), 'backup', ob_get_clean()) . '</div>';

ob_start(); ?>
<div class="domainmod-span-all mb-3">
    <label class="form-label" for="domainmod-restore-file"><?php echo _('Database Restore File'); ?></label>
    <input type="file" class="form-control" name="file" id="domainmod-restore-file" accept=".sql" aria-describedby="domainmod-restore-warning domainmod-restore-help">
    <p class="form-text mb-0" id="domainmod-restore-help"><?php echo _("After restoring a database you'll be automatically logged out, at which point you'll be able to log in using the accounts in the data you restored."); ?></p>
</div>
<div class="domainmod-span-all alert alert-danger d-flex align-items-start gap-2 py-2" id="domainmod-restore-warning">
    <i class="bi bi-exclamation-triangle-fill domainmod-message-icon" aria-hidden="true"></i>
    <div class="domainmod-message-text"><strong><?php echo _('Warning'); ?>:</strong> <?php echo sprintf(_('This will completely delete all of the data in your current %s database.'), SOFTWARE_TITLE); ?> <?php echo _("Your current database will be replaced with the data in the domainmod-backup.sql file that you're restoring."); ?></div>
</div>
<div class="domainmod-span-all mb-3"><?php echo $layout->showButton('submit', _('Restore Entire Database')); ?></div><?php
$restore_fields = ob_get_clean(); ?>
<form enctype="multipart/form-data" action="" method="post" class="domainmod-form">
<?php echo $form->showCsrfField(); ?>
<?php echo $layout->formSection(_('Restore'), 'restore', $restore_fields); ?>
</form>
<?php require_once DIR_INC . '/layout/footer.inc.php'; ?>
</body>
</html>
