<?php
/**
 * /classes/DomainMOD/TwoFactor.php
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
 * Two-factor sign-in with an authenticator app. See .data/plans/TWO-FACTOR-SIGNIN-PLAN.local.md for the design.
 *
 * The secret at rest: with $secret_key set in config.inc.php, a secret is stored as 'enc1:' plus base64 of a 12-byte
 * nonce, the AES-256-GCM ciphertext, and its tag, with the user's id as additional data, so a secret copied to another
 * user's row doesn't decrypt. Without a key, it's stored as 'raw:' plus the base32 secret.
 */
class TwoFactor
{
    // The first database version with the two-factor columns and tables
    const DB_VERSION = '4.27.0';
    // Per user, across sessions: this many wrong codes lock the code step for LOCK_SECONDS
    const MAX_FAILURES = 10;
    const LOCK_SECONDS = 900;
    // A remembered device skips the code step for this many days
    const REMEMBER_DAYS = 30;
    // A right password authorises one Settings action for this long
    const PERMISSION_SECONDS = 600;
    // A sign-in waiting for its code expires after this long, or after this many wrong codes
    const PENDING_SECONDS = 300;
    const PENDING_MAX_WRONG = 5;
    // The project's "never" value for a DATETIME
    const NEVER = '1970-01-01 00:00:00';
    const ENCRYPTED_PREFIX = 'enc1:';
    const RAW_PREFIX = 'raw:';
    const CIPHER = 'aes-256-gcm';
    const NONCE_BYTES = 12;
    const TAG_BYTES = 16;
    // Totp::newSecret() makes 20-byte secrets, so anything else is damaged
    const SECRET_BYTES = 20;

    public $secret_key;
    public $pdo;
    public $totp;
    public $recovery;
    public $time;

    public function __construct()
    {
        $this->secret_key = self::configuredKey();
        $this->pdo = Database::getInstance()->cnxx;
        $this->totp = new Totp();
        $this->recovery = new RecoveryCode();
        $this->time = new Time();
    }

    // $secret_key from config.inc.php, through software.inc.php
    public static function configuredKey()
    {
        return defined('SECRET_KEY') ? SECRET_KEY : '';
    }

    // Whether a database at this version has the two-factor columns and tables, like User::hasGettingStartedSetting()
    public static function isAvailable($db_version)
    {
        return is_string($db_version) && $db_version !== '' && version_compare($db_version, self::DB_VERSION, '>=');
    }

    /*
     * Whether the live database has them, read now rather than from the session, which can be out of date after
     * another admin upgrades. If the version can't be read, or isn't a version at all (such as a blank one after a
     * partial import), this throws, so callers fail closed rather than skipping the second step.
     */
    public function isAvailableNow()
    {
        return self::isAvailableStrict($this->getDbVersion());
    }

    /*
     * isAvailable() for a version just read from the database, which throws if it isn't a version at all. Every
     * version from 4.0 on has three parts (such as 4.06.00), so a shorter one there (such as 4.27 after a damaged
     * import) is refused rather than read as an older version.
     */
    public static function isAvailableStrict($db_version)
    {
        $parts = is_string($db_version) && preg_match('/^[0-9]+(\.[0-9]+)+$/D', $db_version) === 1 ? explode('.', $db_version) : array();
        if ($parts === array() || ((int) $parts[0] >= 4 && count($parts) !== 3)) {
            throw new \RuntimeException('The database version could not be read');
        }

        return self::isAvailable($db_version);
    }

    // The database's version (System::getDbVersion())
    protected function getDbVersion()
    {
        return (new System())->getDbVersion();
    }

    /*
     * Logs a two-factor event, in debug mode only (Log does nothing otherwise). Logging writes to the same database,
     * which may be what failed, so a failed log write is dropped rather than stopping the sign-in.
     */
    protected function logEvent($level, $message, $user_id)
    {
        if (!defined('DEBUG_MODE') || DEBUG_MODE != 1) return;

        try {

            (new Log('class.twofactor'))->$level($message, array('User ID' => (int) $user_id));

        } catch (\Throwable $e) {

            return;

        }
    }

    // A new random stamp. Changing the stamp signs out the user's other sessions and forgets their remembered devices.
    public function newStamp()
    {
        return bin2hex(random_bytes(16));
    }

    /*
     * The user's two-factor state, as an associative array, or false if there's no active user with that id.
     * 'secret' is the stored value, as written (empty when two-factor sign-in is off).
     */
    public function getState($user_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT u.id, u.username, u.email_address, u.first_name, u.last_name, u.`password`, u.new_password, u.admin,
                u.`read_only`, u.two_factor_secret AS secret, u.two_factor_last_step AS last_step,
                u.two_factor_failures AS failures, u.two_factor_locked_until AS locked_until,
                u.two_factor_stamp AS stamp, u.two_factor_codes_confirmed AS codes_confirmed,
                u.two_factor_enabled_time AS enabled_time, s.two_factor_required AS required
            FROM users AS u, settings AS s
            WHERE u.id = :user_id
              AND u.active = '1'");
        $stmt->bindValue('user_id', (int) $user_id, \PDO::PARAM_INT);
        $stmt->execute();
        $state = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        return $state ?: false;
    }

    public function isOn($state)
    {
        return is_array($state) && (string) $state['secret'] !== '';
    }

    public function isLocked($state, $now)
    {
        return is_array($state) && (int) $state['locked_until'] > (int) $now;
    }

    /*
     * The sign-in steps still to do for this state, in the order checks.php sends the user through them: 'password'
     * (Change Password), 'setup' (required, and no secret yet), 'replace' (the secret can't be decrypted), and 'codes'
     * (recovery codes not yet confirmed as saved, only for a user with a secret).
     */
    public function pendingSteps($state)
    {
        if (!is_array($state)) return array();

        $steps = array();
        if ((string) $state['new_password'] === '1') $steps[] = 'password';
        if (!$this->isOn($state)) {
            if ((string) $state['required'] === '1') $steps[] = 'setup';
        } else {
            if ($this->decodeSecret($state['secret'], $state['id']) === false) $steps[] = 'replace';
            if ((string) $state['codes_confirmed'] !== '1') $steps[] = 'codes';
        }

        return $steps;
    }

    // The pages a session with unfinished sign-in steps can still open (paths from the install's root)
    const STEP_PAGES = array('/checks.php', '/notice.php', '/settings/security/index.php');

    /*
     * What System::authCheckResult() makes of a signed-in session once the database has two-factor sign-in, given the
     * user's live state, the session, and the page's path from the install's root: 'invalid' (the account's password
     * or stamp has changed, or "require for everyone" is on and the session skipped the second step without setup to do),
     * 'steps_pending' (a sign-in step is still to do and this page isn't one that finishes it), or 'valid'.
     */
    public function sessionResult($state, $session, $page)
    {
        if (!is_array($state)) return 'invalid';

        // Checked again here against the same read as the rest, so the decision never mixes two account states
        if (!hash_equals((string) $state['password'], (string) ($session['s_stored_hash'] ?? ''))) return 'invalid';

        // A missing session value counts as '', the column's default
        if (!hash_equals((string) $state['stamp'], (string) ($session['s_two_factor_stamp'] ?? ''))) return 'invalid';

        $steps = $this->pendingSteps($state);
        if ((string) $state['required'] === '1' && (int) ($session['s_two_factor_verified'] ?? 0) !== 1
            && !in_array('setup', $steps, true)) {
            return 'invalid';
        }

        if ($steps !== array() && !in_array($page, self::STEP_PAGES, true)) return 'steps_pending';

        return 'valid';
    }

    /*
     * The actions settings/security/ allows for this state, so the server enforces each sign-in step, not just the
     * forms shown. 'password_<action>' is the password check that gives an action's permission. While Change Password
     * is pending, nothing is allowed. While another sign-in step is pending, only what that step needs: setup,
     * replacing a secret that can't be decrypted, or the recovery codes (confirming them while the session still has
     * them, otherwise regenerating them with a code or the recovery permission, or replacing the authenticator with
     * the recovery permission, which issues new codes too). Turning off is never allowed then. $has_recovery_permission is hasRecoveryPermission() for the
     * session, and $has_codes_shown whether the session still holds the plain codes of the current set.
     */
    public function allowedActions($state, $has_recovery_permission, $has_codes_shown)
    {
        $steps = $this->pendingSteps($state);
        if (in_array('password', $steps, true)) return array();

        $recovery = $has_recovery_permission ? array('password_replace', 'replace', 'password_regenerate', 'regenerate_recovery') : array();

        if (in_array('setup', $steps, true)) return array('password_setup', 'setup');
        if (in_array('replace', $steps, true)) return array('password_replace', 'replace');
        // With the codes still in the session, they're confirmed (not replaced from another tab while being saved)
        if (in_array('codes', $steps, true)) return $has_codes_shown ? array('confirm_codes') : array_merge(array('regenerate'), $recovery);

        if (!$this->isOn($state)) return array('password_setup', 'setup');

        $actions = array_merge(array('regenerate', 'forget_devices'), $recovery);
        if ((string) $state['required'] !== '1') $actions[] = 'turn_off';

        return $actions;
    }

    /*
     * What a code check is tied to: the account state read when the attempt started. Every write that follows a right
     * code matches on all of it, so a password change, a deactivation, an admin reset, a new setup, or a lock that
     * lands in between always wins. $settings is true for a Settings action, whose writes also need new_password to be
     * 0, so a temporary password can't authorise them.
     */
    public function context($state, $password_hash, $settings)
    {
        return array(
            'user_id' => (int) $state['id'],
            'password_hash' => (string) $password_hash,
            'stamp' => (string) $state['stamp'],
            'secret' => (string) $state['secret'],
            'last_step' => (int) $state['last_step'],
            'settings' => (bool) $settings);
    }

    // The conditions every conditional write shares, as SQL, with their values added to $values
    protected function contextConditions($context, $now, &$values, $alias = '')
    {
        $values['ctx_user_id'] = $context['user_id'];
        $values['ctx_password_hash'] = $context['password_hash'];
        $values['ctx_stamp'] = $context['stamp'];
        $values['ctx_secret'] = $context['secret'];
        $values['ctx_now'] = (int) $now;

        $sql = $alias . "id = :ctx_user_id
              AND " . $alias . "active = '1'
              AND " . $alias . "`password` = :ctx_password_hash
              AND " . $alias . "two_factor_stamp = :ctx_stamp
              AND " . $alias . "two_factor_secret = :ctx_secret
              AND " . $alias . "two_factor_locked_until <= :ctx_now";
        if ($context['settings']) $sql .= "
              AND " . $alias . "new_password = '0'";

        return $sql;
    }

    protected function bindAll($stmt, $values)
    {
        foreach ($values as $name => $value) {
            $stmt->bindValue($name, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
    }

    /*
     * A wrong code: one statement, so no increment is lost. The lock assignment comes first, since MariaDB and MySQL
     * apply a single-table UPDATE's assignments left to right, and it has to read the count before it's raised.
     * Returns 'lockout' when this wrong code set the lock, otherwise 'wrong'. A wrong code checked against an account
     * that has changed since the attempt started isn't charged, and returns 'expired'. The update and the read of the
     * lock that follows are one transaction, so the row stays locked between them and only the wrong code that set
     * the lock reports it (so the lockout email is sent once). Each wrong code and lockout is logged (debug mode).
     */
    public function recordFailure($context, $now)
    {
        $this->pdo->beginTransaction();

        try {

            $result = $this->recordFailureIn($context, $now);
            $this->pdo->commit();

        } catch (\Throwable $e) {

            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;

        }

        if ($result !== 'expired') $this->logEvent('warning', 'Wrong MFA code', $context['user_id']);
        if ($result === 'lockout') $this->logEvent('warning', 'MFA code step locked after wrong codes', $context['user_id']);

        return $result;
    }

    protected function recordFailureIn($context, $now)
    {
        $values = array(
            'max_failures_lock' => self::MAX_FAILURES,
            'locked_until' => (int) $now + self::LOCK_SECONDS,
            'max_failures_count' => self::MAX_FAILURES);
        $stmt = $this->pdo->prepare("
            UPDATE users
            SET two_factor_locked_until = IF(two_factor_failures + 1 >= :max_failures_lock, :locked_until, two_factor_locked_until),
                two_factor_failures = IF(two_factor_failures + 1 >= :max_failures_count, 0, two_factor_failures + 1)
            WHERE " . $this->contextConditions($context, $now, $values));
        $this->bindAll($stmt, $values);
        $stmt->execute();
        if ($stmt->rowCount() !== 1) return 'expired';

        $stmt = $this->pdo->prepare("
            SELECT two_factor_locked_until
            FROM users
            WHERE id = :user_id");
        $stmt->bindValue('user_id', $context['user_id'], \PDO::PARAM_INT);
        $stmt->execute();
        $locked_until = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        return $locked_until > (int) $now ? 'lockout' : 'wrong';
    }

    /*
     * Runs $change inside a transaction that first locks the settings row (when $lock_settings is true, for anything
     * that turns two-factor sign-in on or off), then the user's row, and rechecks the context against the locked row.
     * $change gets the locked row and returns true or an array when it worked, or a string saying why it refused
     * (such as 'expired' or 'wrong'), which rolls everything back. Returns 'expired' (and changes nothing) if the
     * account no longer matches the context, otherwise $change's result. Any exception rolls everything back too.
     */
    public function inTransaction($context, $now, $lock_settings, callable $change)
    {
        $this->pdo->beginTransaction();

        try {

            if ($lock_settings) {
                $this->pdo->query("
                    SELECT two_factor_required
                    FROM settings
                    FOR UPDATE")->fetchAll();
            }

            $values = array();
            $stmt = $this->pdo->prepare("
                SELECT id, two_factor_last_step AS last_step
                FROM users
                WHERE " . $this->contextConditions($context, $now, $values) . "
                FOR UPDATE");
            $this->bindAll($stmt, $values);
            $stmt->execute();
            $locked = $stmt->fetch(\PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            $result = $locked ? $change($locked) : 'expired';
            // Only true or an array counts as worked: anything else (such as false from a failed save) is refused, and
            // 'ok' is kept for a committed change
            if (($result !== true && !is_array($result) && !is_string($result)) || $result === 'ok') $result = 'failed';

            if (is_string($result)) {
                $this->pdo->rollBack();
            } else {
                $this->pdo->commit();
            }

            return $result;

        } catch (\Throwable $e) {

            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;

        }
    }

    /*
     * Checks a code from the user (a six-digit authenticator code, or a recovery code) against the context, and uses
     * it. Every place that checks a code calls this, so they share the per-user lock, the replay rule, and the logging
     * (each wrong code, lockout, unreadable secret, and recovery code use is logged here, in debug mode).
     * $change, if given, runs in the same transaction after the code is used, so if it fails, the code still works.
     *
     * Returns an array with 'result': 'ok', 'wrong', 'lockout' (this wrong code locked the code step), 'locked' (it
     * was already locked, so the code wasn't checked), 'expired' (the account changed since the attempt started), 'unreadable' (an authenticator code, but the secret
     * can't be decrypted), or $change's own refusal, plus 'type' ('authenticator' or 'recovery') and 'change'
     * ($change's result when it worked).
     */
    public function useCode($context, $code, $now, $lock_settings = false, ?callable $change = null)
    {
        $is_authenticator = $this->totp->normalise($code) !== false;
        $type = $is_authenticator ? 'authenticator' : 'recovery';

        if ($this->isContextLocked($context, $now)) return array('result' => 'locked', 'type' => $type, 'change' => null);

        if ($is_authenticator) {

            $secret = $this->decodeSecret($context['secret'], $context['user_id']);
            if ($secret === false) {
                $this->logEvent('error', 'The MFA secret could not be decrypted (check $secret_key)', $context['user_id']);
                return array('result' => 'unreadable', 'type' => $type, 'change' => null);
            }

            if ($this->totp->verify($secret, $code, $now, $context['last_step']) === false) {

                $result = $this->recordFailure($context, $now);

            } else {

                $result = $this->inTransaction($context, $now, $lock_settings, function ($locked) use ($context, $code, $now, $secret, $change) {

                    // Checked again against the last step read under the row lock, so two requests that read the account
                    // before either saved can't both use the code (even when two nearby steps share it)
                    $step = $this->totp->verify($secret, $code, $now, $locked['last_step']);
                    if ($step === false) return 'expired';

                    // Moves a secret saved without encryption to an encrypted one, once a key is set. That's a storage-only
                    // rewrite of the same secret, so the stamp stays.
                    $values = array('new_step' => $step, 'minimum_step' => $step);
                    $new_secret = $this->needsEncrypting($context['secret'])
                        ? $this->encodeSecret($secret, $context['user_id']) : $context['secret'];
                    $values['new_secret'] = $new_secret;
                    $stmt = $this->pdo->prepare("
                        UPDATE users
                        SET two_factor_last_step = :new_step,
                            two_factor_failures = 0,
                            two_factor_secret = :new_secret
                        WHERE " . $this->contextConditions($context, $now, $values) . "
                          AND two_factor_last_step < :minimum_step");
                    $this->bindAll($stmt, $values);
                    $stmt->execute();
                    if ($stmt->rowCount() !== 1) return 'expired';

                    return $change === null ? true : $change(array('secret' => $new_secret));

                });

            }

        } else {

            $hash = $this->recovery->hash($code);
            // Set when the code isn't one of the user's unused codes, rather than a result string a $change could
            // also return
            $not_found = $hash === false;
            $result = 'wrong';
            if ($hash !== false) $result = $this->inTransaction($context, $now, $lock_settings, function () use ($context, $now, $hash, $change, &$not_found) {

                $stmt = $this->pdo->prepare("
                    UPDATE user_recovery_codes
                    SET used_time = :used_time,
                        update_time = :update_time
                    WHERE user_id = :user_id
                      AND code_hash = :code_hash
                      AND used_time = :never");
                $timestamp = $this->time->stamp();
                $stmt->bindValue('used_time', $timestamp, \PDO::PARAM_STR);
                $stmt->bindValue('update_time', $timestamp, \PDO::PARAM_STR);
                $stmt->bindValue('user_id', $context['user_id'], \PDO::PARAM_INT);
                $stmt->bindValue('code_hash', $hash, \PDO::PARAM_STR);
                $stmt->bindValue('never', self::NEVER, \PDO::PARAM_STR);
                $stmt->execute();
                if ($stmt->rowCount() !== 1) {
                    $not_found = true;
                    return 'wrong';
                }

                $values = array();
                $stmt = $this->pdo->prepare("
                    UPDATE users
                    SET two_factor_failures = 0
                    WHERE " . $this->contextConditions($context, $now, $values));
                $this->bindAll($stmt, $values);
                $stmt->execute();

                return $change === null ? true : $change(array('secret' => $context['secret']));

            });

            // Not a code (or one already used): rolled back above, then charged on its own, so the charge stays
            if ($not_found) $result = $this->recordFailure($context, $now);

        }

        // Any refusal, including one from $change (such as 'required'), is the result
        if (is_string($result)) return array('result' => $result, 'type' => $type, 'change' => null);

        if ($type === 'recovery') $this->logEvent('info', 'Recovery code used', $context['user_id']);

        return array('result' => 'ok', 'type' => $type, 'change' => $result);
    }

    // Whether the stored lock is on, read now (the context's state may be older)
    protected function isContextLocked($context, $now)
    {
        $stmt = $this->pdo->prepare("
            SELECT two_factor_locked_until
            FROM users
            WHERE id = :user_id");
        $stmt->bindValue('user_id', $context['user_id'], \PDO::PARAM_INT);
        $stmt->execute();
        $locked_until = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        return $locked_until > (int) $now;
    }

    // Replaces the user's recovery codes with a new set, inside the caller's transaction, and returns them
    protected function replaceRecoveryCodes($user_id)
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM user_recovery_codes
            WHERE user_id = :user_id");
        $stmt->bindValue('user_id', (int) $user_id, \PDO::PARAM_INT);
        $stmt->execute();

        $codes = $this->recovery->generate();
        $stmt = $this->pdo->prepare("
            INSERT INTO user_recovery_codes
            (user_id, code_hash, used_time, insert_time, update_time)
            VALUES
            (:user_id, :code_hash, :never, :insert_time, :update_time)");
        $timestamp = $this->time->stamp();
        foreach ($codes as $code) {
            $stmt->bindValue('user_id', (int) $user_id, \PDO::PARAM_INT);
            $stmt->bindValue('code_hash', $this->recovery->hash($code), \PDO::PARAM_STR);
            $stmt->bindValue('never', self::NEVER, \PDO::PARAM_STR);
            $stmt->bindValue('insert_time', $timestamp, \PDO::PARAM_STR);
            $stmt->bindValue('update_time', $timestamp, \PDO::PARAM_STR);
            $stmt->execute();
        }

        return $codes;
    }

    // Forgets every remembered device for the user, inside the caller's transaction (or on its own)
    public function deleteDevices($user_id)
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM user_remembered_devices
            WHERE user_id = :user_id");
        $stmt->bindValue('user_id', (int) $user_id, \PDO::PARAM_INT);
        $stmt->execute();
    }

    /*
     * Saves a new secret (raw bytes) after its first code was checked against it, with a new set of recovery codes and
     * a new stamp, in one transaction. The context's secret is what's being replaced: '' for a first setup, or the
     * exact secret being replaced, so two setups racing each other can't overwrite one another. Returns
     * array('stamp' => ..., 'codes' => array(...)), or 'expired' if the account changed first.
     */
    public function saveSecret($context, $secret, $step, $now)
    {
        return $this->inTransaction($context, $now, true, function () use ($context, $secret, $step, $now) {

            $stamp = $this->newStamp();
            $values = array(
                'new_secret' => $this->encodeSecret($secret, $context['user_id']),
                'new_step' => (int) $step,
                'new_stamp' => $stamp,
                'enabled_time' => $this->time->stamp(),
                'update_time' => $this->time->stamp());
            $stmt = $this->pdo->prepare("
                UPDATE users
                SET two_factor_secret = :new_secret,
                    two_factor_last_step = :new_step,
                    two_factor_failures = 0,
                    two_factor_stamp = :new_stamp,
                    two_factor_codes_confirmed = '0',
                    two_factor_enabled_time = :enabled_time,
                    update_time = :update_time
                WHERE " . $this->contextConditions($context, $now, $values));
            $this->bindAll($stmt, $values);
            $stmt->execute();
            if ($stmt->rowCount() !== 1) return 'expired';

            $codes = $this->replaceRecoveryCodes($context['user_id']);
            $this->deleteDevices($context['user_id']);

            return array('stamp' => $stamp, 'codes' => $codes);

        });
    }

    /*
     * Replaces the recovery codes and the stamp, inside a transaction already checked against the context (from
     * useCode()'s $change, or inTransaction() for the recovery permission). Returns the same shape as saveSecret().
     */
    public function regenerateCodesIn($context)
    {
        $stamp = $this->newStamp();
        $stmt = $this->pdo->prepare("
            UPDATE users
            SET two_factor_stamp = :new_stamp,
                two_factor_codes_confirmed = '0',
                update_time = :update_time
            WHERE id = :user_id");
        $stmt->bindValue('new_stamp', $stamp, \PDO::PARAM_STR);
        $stmt->bindValue('update_time', $this->time->stamp(), \PDO::PARAM_STR);
        $stmt->bindValue('user_id', $context['user_id'], \PDO::PARAM_INT);
        $stmt->execute();

        $codes = $this->replaceRecoveryCodes($context['user_id']);
        $this->deleteDevices($context['user_id']);

        return array('stamp' => $stamp, 'codes' => $codes);
    }

    /*
     * Turns two-factor sign-in off (or an admin reset), inside a transaction that has locked the settings row and the
     * user's row: clears the secret, the recovery codes, the failure count and lock, and remembered devices, and
     * changes the stamp. Returns the new stamp.
     */
    public function clearIn($user_id)
    {
        $stamp = $this->newStamp();
        $stmt = $this->pdo->prepare("
            UPDATE users
            SET two_factor_secret = '',
                two_factor_last_step = '0',
                two_factor_failures = '0',
                two_factor_locked_until = '0',
                two_factor_stamp = :new_stamp,
                two_factor_codes_confirmed = '0',
                two_factor_enabled_time = :never,
                update_time = :update_time
            WHERE id = :user_id");
        $stmt->bindValue('new_stamp', $stamp, \PDO::PARAM_STR);
        $stmt->bindValue('never', self::NEVER, \PDO::PARAM_STR);
        $stmt->bindValue('update_time', $this->time->stamp(), \PDO::PARAM_STR);
        $stmt->bindValue('user_id', (int) $user_id, \PDO::PARAM_INT);
        $stmt->execute();

        $stmt = $this->pdo->prepare("
            DELETE FROM user_recovery_codes
            WHERE user_id = :user_id");
        $stmt->bindValue('user_id', (int) $user_id, \PDO::PARAM_INT);
        $stmt->execute();

        $this->deleteDevices($user_id);

        return $stamp;
    }

    /*
     * Locks the settings row and then the acting admin's row and $other_id's row (by id, so two transactions can't
     * deadlock), and checks the admin inside the transaction against the session: still active, the same password hash
     * and stamp, still an admin, and not read-only (so a demotion that commits first wins). Returns the locked rows by
     * id, or false. The caller is inside a transaction.
     */
    protected function lockAdmin($admin_id, $password_hash, $stamp, $other_id = null)
    {
        $this->pdo->query("
            SELECT two_factor_required
            FROM settings
            FOR UPDATE")->fetchAll();

        $ids = array_unique(array_filter(array((int) $admin_id, (int) $other_id)));
        sort($ids);
        $rows = array();
        $stmt = $this->pdo->prepare("
            SELECT id, username, email_address, first_name, last_name, `password`, active, admin, `read_only`,
                two_factor_secret AS secret, two_factor_stamp AS stamp
            FROM users
            WHERE id = :user_id
            FOR UPDATE");
        foreach ($ids as $id) {
            $stmt->bindValue('user_id', $id, \PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $stmt->closeCursor();
            if ($row) $rows[(int) $row['id']] = $row;
        }

        $admin = $rows[(int) $admin_id] ?? null;
        if (!$admin || (string) $admin['active'] !== '1' || (string) $admin['admin'] !== '1' || (string) $admin['read_only'] !== '0'
            || !hash_equals((string) $admin['password'], (string) $password_hash) || !hash_equals((string) $admin['stamp'], (string) $stamp)) {
            return false;
        }

        return $rows;
    }

    /*
     * An admin resets another user's two-factor sign-in: clears their secret, recovery codes, failure count, lock, and
     * remembered devices, and changes their stamp (signing them out everywhere), in one transaction. Never the admin's
     * own account (they use Settings, which needs a code), and only the primary administrator (username admin) can
     * reset that account, as only they can edit it (admin/users/edit.php). Returns the user's row (for the email), or
     * 'refused' (the admin no longer matches their session, or isn't allowed), 'own', 'primary', or 'off' (the user
     * doesn't have it on).
     */
    public function adminReset($admin_id, $password_hash, $stamp, $user_id)
    {
        if ((int) $admin_id === (int) $user_id) return 'own';

        $this->pdo->beginTransaction();

        try {

            $rows = $this->lockAdmin($admin_id, $password_hash, $stamp, $user_id);
            $user = $rows ? ($rows[(int) $user_id] ?? null) : null;

            if (!$rows) {
                $result = 'refused';
            } elseif ($user && (string) $user['username'] === 'admin' && (string) $rows[(int) $admin_id]['username'] !== 'admin') {
                $result = 'primary';
            } elseif (!$user || (string) $user['secret'] === '') {
                $result = 'off';
            } else {
                $this->clearIn($user_id);
                $result = $user;
            }

            if (is_array($result)) {
                $this->pdo->commit();
            } else {
                $this->pdo->rollBack();
            }

            return $result;

        } catch (\Throwable $e) {

            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;

        }
    }

    /*
     * Turns "Require MFA for All Users" on or off, in a transaction that locks the settings row and the
     * admin's row and checks the admin again. It can only be turned on by an admin who has two-factor sign-in on
     * themselves. Turning it on also changes the stamp of every user who doesn't have it on, so their sessions end and
     * they set it up at their next sign-in. Returns 'ok', 'refused', or 'not_on' (the admin doesn't have it on).
     */
    public function setRequired($admin_id, $password_hash, $stamp, $required)
    {
        $this->pdo->beginTransaction();

        try {

            $rows = $this->lockAdmin($admin_id, $password_hash, $stamp);

            if (!$rows) {
                $result = 'refused';
            } elseif ($required && (string) $rows[(int) $admin_id]['secret'] === '') {
                $result = 'not_on';
            } else {

                $stmt = $this->pdo->prepare("
                    UPDATE settings
                    SET two_factor_required = :required,
                        update_time = :update_time");
                $stmt->bindValue('required', $required ? 1 : 0, \PDO::PARAM_INT);
                $stmt->bindValue('update_time', $this->time->stamp(), \PDO::PARAM_STR);
                $stmt->execute();

                if ($required) {

                    $stmt = $this->pdo->prepare("
                        SELECT id
                        FROM users
                        WHERE two_factor_secret = ''
                        FOR UPDATE");
                    $stmt->execute();
                    $user_ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                    $stmt->closeCursor();

                    $stmt = $this->pdo->prepare("
                        UPDATE users
                        SET two_factor_stamp = :new_stamp
                        WHERE id = :user_id
                          AND two_factor_secret = ''");
                    foreach ($user_ids as $user_id) {
                        $stmt->bindValue('new_stamp', $this->newStamp(), \PDO::PARAM_STR);
                        $stmt->bindValue('user_id', (int) $user_id, \PDO::PARAM_INT);
                        $stmt->execute();
                    }

                }

                $result = 'ok';

            }

            if ($result === 'ok') {
                $this->pdo->commit();
            } else {
                $this->pdo->rollBack();
            }

            return $result;

        } catch (\Throwable $e) {

            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;

        }
    }

    /*
     * Emails the user about a change to their two-factor sign-in ('on', 'off', 'reset', or 'locked'), so they can act if
     * it wasn't them. $user is a row with first_name, last_name, username, and email_address. A failed email is logged
     * and never stops the page, since the change itself is already saved.
     */
    public function sendNotice($event, $user)
    {
        if (defined('DEMO_INSTALLATION') && DEMO_INSTALLATION == true) return;

        $two_factor_event = $event;
        $first_name = $user['first_name'];
        $last_name = $user['last_name'];
        $username = $user['username'];
        $email_address = $user['email_address'];

        try {

            $two_factor_required = $this->isRequiredIn();
            require DIR_INC . '/email/send-two-factor-notice.inc.php';

        } catch (\Throwable $e) {

            $this->logEvent('error', 'The MFA email could not be sent', $user['id'] ?? 0);

        }
    }

    /*
     * Redirects, then sends the email (sendNotice()): saves and closes the session and finishes the response first, as
     * reset.php does, so a slow or stalled mail server can't keep the page waiting, hold the session's lock, or leave the
     * session without what the page just saved in it. Doesn't return.
     */
    public function redirectThenNotice($location, $event, $user)
    {
        session_write_close();
        ignore_user_abort(true);
        header('Location: ' . $location);
        header('Connection: close');
        header('Content-Length: 0');
        // ob_end_flush() returns false for a buffer that can't be removed, which ends the loop
        while (ob_get_level() > 0 && ob_end_flush()) {
        }
        flush();
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
        $this->sendNotice($event, $user);
        exit;
    }

    // How many users' secrets are stored encrypted, and how many without encryption, for Admin > Settings
    public function secretCounts()
    {
        $row = $this->pdo->query("
            SELECT SUM(two_factor_secret LIKE 'enc1:%') AS encrypted, SUM(two_factor_secret LIKE 'raw:%') AS raw
            FROM users")->fetch(\PDO::FETCH_ASSOC);

        return array('encrypted' => (int) ($row['encrypted'] ?? 0), 'raw' => (int) ($row['raw'] ?? 0));
    }

    /*
     * Deletes a user's recovery codes and remembered devices, inside the caller's transaction, after it has locked the
     * user's row (admin/users/edit.php's delete), so nothing can add more while the user is being deleted.
     */
    public function deleteUserDataIn($user_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT id
            FROM users
            WHERE id = :user_id
            FOR UPDATE");
        $stmt->bindValue('user_id', (int) $user_id, \PDO::PARAM_INT);
        $stmt->execute();
        $stmt->closeCursor();

        $stmt = $this->pdo->prepare("
            DELETE FROM user_recovery_codes
            WHERE user_id = :user_id");
        $stmt->bindValue('user_id', (int) $user_id, \PDO::PARAM_INT);
        $stmt->execute();

        $this->deleteDevices($user_id);
    }

    // Whether the "require for everyone" setting is on, read inside a transaction that has locked the settings row
    public function isRequiredIn()
    {
        return (string) $this->pdo->query("
            SELECT two_factor_required
            FROM settings")->fetchColumn() === '1';
    }

    /*
     * Marks the recovery codes as saved, only for the set the user was shown (its stamp), and only while the account
     * still matches the session. Returns whether it did.
     */
    public function confirmCodes($user_id, $password_hash, $stamp)
    {
        $stmt = $this->pdo->prepare("
            UPDATE users
            SET two_factor_codes_confirmed = '1'
            WHERE id = :user_id
              AND active = '1'
              AND `password` = :password_hash
              AND two_factor_stamp = :stamp
              AND two_factor_secret != ''
              AND new_password = '0'");
        $stmt->bindValue('user_id', (int) $user_id, \PDO::PARAM_INT);
        $stmt->bindValue('password_hash', (string) $password_hash, \PDO::PARAM_STR);
        $stmt->bindValue('stamp', (string) $stamp, \PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->rowCount() === 1;
    }

    /*
     * Gives every user a new random stamp, which signs out every session and forgets every remembered device. Restoring
     * a backup does this, so sessions and devices whose stamps the backup brought back end too.
     */
    public function newStampsForEveryone()
    {
        $this->pdo->beginTransaction();

        try {

            $stmt = $this->pdo->prepare("
                SELECT id
                FROM users
                FOR UPDATE");
            $stmt->execute();
            $user_ids = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            $stmt->closeCursor();

            $stmt = $this->pdo->prepare("
                UPDATE users
                SET two_factor_stamp = :new_stamp
                WHERE id = :user_id");
            foreach ($user_ids as $user_id) {
                $stmt->bindValue('new_stamp', $this->newStamp(), \PDO::PARAM_STR);
                $stmt->bindValue('user_id', (int) $user_id, \PDO::PARAM_INT);
                $stmt->execute();
            }

            $this->pdo->commit();

        } catch (\Throwable $e) {

            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;

        }
    }

    /*
     * Runs a password write ($write, which returns whether it changed the row) and, once two-factor sign-in is
     * available, forgets the user's remembered devices in the same transaction, so a new password always ends them.
     * Returns $write's result.
     */
    public function changePassword($user_id, callable $write)
    {
        if (!$this->isAvailableNow()) return $write();

        $this->pdo->beginTransaction();

        try {

            $changed = $write();
            if ($changed) $this->deleteDevices($user_id);
            $this->pdo->commit();
            return $changed;

        } catch (\Throwable $e) {

            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;

        }
    }

    // The remembered-device cookie: always Secure (it's only used over HTTPS), with the install's own path
    public static function deviceCookieName()
    {
        return '__Secure-domainmod-device';
    }

    public static function deviceCookiePath()
    {
        return (defined('WEB_ROOT') && WEB_ROOT !== '') ? WEB_ROOT . '/' : '/';
    }

    // Whether this request came over HTTPS, as the web server says, never on $force_https alone
    public static function isHttps()
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        return $https !== '' && $https !== 'off';
    }

    // Whether "Remember This Device" can be offered on this request
    public static function canRememberDevices()
    {
        return self::isHttps() && !(defined('DEMO_INSTALLATION') && DEMO_INSTALLATION == true);
    }

    public function deviceTokenHash($token)
    {
        return is_string($token) && preg_match('/^[0-9a-f]{64}$/D', $token) === 1 ? hash('sha256', $token) : false;
    }

    /*
     * Remembers this device after a right code, in its own transaction that locks the user's row and rechecks the
     * context first (so a reset or a lock that lands in between saves nothing), with the context's secret as the code
     * check left it. Returns array('token' => ...), with false for the token if nothing could be saved (including a
     * database error, such as a deadlock), so the sign-in still finishes, without the cookie.
     */
    public function rememberDevice($context, $now)
    {
        if (!self::canRememberDevices()) return array('token' => false);

        try {

            $result = $this->inTransaction($context, $now, false, function () use ($context, $now) {
                return $this->rememberDeviceIn($context['user_id'], $context['stamp'], $now);
            });

        } catch (\Throwable $e) {

            $this->logEvent('error', 'A remembered device could not be saved', $context['user_id']);
            return array('token' => false);

        }

        return is_array($result) ? $result : array('token' => false);
    }

    // rememberDevice()'s writes, inside its transaction: expired rows for the user go at the same time
    protected function rememberDeviceIn($user_id, $stamp, $now)
    {
        $stmt = $this->pdo->prepare("
            DELETE FROM user_remembered_devices
            WHERE user_id = :user_id
              AND expires <= :now");
        $stmt->bindValue('user_id', (int) $user_id, \PDO::PARAM_INT);
        $stmt->bindValue('now', (int) $now, \PDO::PARAM_INT);
        $stmt->execute();

        $token = bin2hex(random_bytes(32));
        $stmt = $this->pdo->prepare("
            INSERT INTO user_remembered_devices
            (user_id, token_hash, expires, stamp, insert_time, update_time)
            VALUES
            (:user_id, :token_hash, :expires, :stamp, :insert_time, :update_time)");
        $timestamp = $this->time->stamp();
        $stmt->bindValue('user_id', (int) $user_id, \PDO::PARAM_INT);
        $stmt->bindValue('token_hash', hash('sha256', $token), \PDO::PARAM_STR);
        $stmt->bindValue('expires', (int) $now + self::REMEMBER_DAYS * 86400, \PDO::PARAM_INT);
        $stmt->bindValue('stamp', (string) $stamp, \PDO::PARAM_STR);
        $stmt->bindValue('insert_time', $timestamp, \PDO::PARAM_STR);
        $stmt->bindValue('update_time', $timestamp, \PDO::PARAM_STR);
        $stmt->execute();

        return array('token' => $token);
    }

    /*
     * The user's current stamp if $token is one of their remembered devices: not expired, remembered under their
     * current stamp, for an active account that still has the password just checked and two-factor sign-in on.
     * Otherwise false.
     */
    public function findDevice($user_id, $password_hash, $token, $now)
    {
        $token_hash = $this->deviceTokenHash($token);
        if ($token_hash === false) return false;

        $stmt = $this->pdo->prepare("
            SELECT u.two_factor_stamp
            FROM user_remembered_devices AS d, users AS u
            WHERE d.token_hash = :token_hash
              AND d.user_id = :user_id
              AND u.id = d.user_id
              AND u.active = '1'
              AND u.`password` = :password_hash
              AND u.two_factor_secret != ''
              AND d.stamp = u.two_factor_stamp
              AND d.expires > :now");
        $stmt->bindValue('token_hash', $token_hash, \PDO::PARAM_STR);
        $stmt->bindValue('user_id', (int) $user_id, \PDO::PARAM_INT);
        $stmt->bindValue('password_hash', (string) $password_hash, \PDO::PARAM_STR);
        $stmt->bindValue('now', (int) $now, \PDO::PARAM_INT);
        $stmt->execute();
        $stamp = $stmt->fetchColumn();
        $stmt->closeCursor();

        return is_string($stamp) && $stamp !== '' ? $stamp : false;
    }

    // How many remembered devices the user's sign-in would still accept
    public function countDevices($user_id, $stamp, $now)
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM user_remembered_devices
            WHERE user_id = :user_id
              AND stamp = :stamp
              AND expires > :now");
        $stmt->bindValue('user_id', (int) $user_id, \PDO::PARAM_INT);
        $stmt->bindValue('stamp', (string) $stamp, \PDO::PARAM_STR);
        $stmt->bindValue('now', (int) $now, \PDO::PARAM_INT);
        $stmt->execute();
        $count = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        return $count;
    }

    // Forget All Devices: only while the account still matches the session
    public function forgetDevices($user_id, $password_hash, $stamp)
    {
        $stmt = $this->pdo->prepare("
            DELETE d
            FROM user_remembered_devices AS d, users AS u
            WHERE d.user_id = u.id
              AND u.id = :user_id
              AND u.active = '1'
              AND u.`password` = :password_hash
              AND u.two_factor_stamp = :stamp");
        $stmt->bindValue('user_id', (int) $user_id, \PDO::PARAM_INT);
        $stmt->bindValue('password_hash', (string) $password_hash, \PDO::PARAM_STR);
        $stmt->bindValue('stamp', (string) $stamp, \PDO::PARAM_STR);
        $stmt->execute();
    }

    /*
     * A single-use permission for one Settings action ('setup', 'replace', or 'regenerate'), given by a right password
     * (or by the sign-in, which just checked it) and kept in the session for 10 minutes, tied to the account, its
     * password hash, and its stamp. It's used up only when the action's transaction commits.
     */
    public function grantPermission($action, $user_id, $password_hash, $stamp, $now)
    {
        $_SESSION['s_two_factor_permissions'][$action] = array('user_id' => (int) $user_id,
            'password_hash' => (string) $password_hash, 'stamp' => (string) $stamp, 'time' => (int) $now);
    }

    public function hasPermission($action, $user_id, $password_hash, $stamp, $now)
    {
        $permission = $_SESSION['s_two_factor_permissions'][$action] ?? null;

        return is_array($permission) && $permission['user_id'] === (int) $user_id
            && hash_equals($permission['password_hash'], (string) $password_hash)
            && hash_equals($permission['stamp'], (string) $stamp)
            && (int) $now - $permission['time'] < self::PERMISSION_SECONDS && (int) $now >= $permission['time'];
    }

    public function usePermission($action)
    {
        unset($_SESSION['s_two_factor_permissions'][$action]);
    }

    /*
     * After a recovery code sign-in: a single-use permission that lasts as long as the session (and survives its own
     * password change), tied to the stamp it was issued with, to set up a new authenticator or regenerate the codes
     * without another code. Using it also needs the password permission for that action.
     */
    public function grantRecoveryPermission($user_id, $stamp)
    {
        $_SESSION['s_two_factor_recovery'] = array('user_id' => (int) $user_id, 'stamp' => (string) $stamp);
    }

    public function hasRecoveryPermission($user_id, $stamp)
    {
        $permission = $_SESSION['s_two_factor_recovery'] ?? null;

        return is_array($permission) && $permission['user_id'] === (int) $user_id
            && hash_equals($permission['stamp'], (string) $stamp);
    }

    public function useRecoveryPermission()
    {
        unset($_SESSION['s_two_factor_recovery']);
    }

    public function remainingCodes($user_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*)
            FROM user_recovery_codes
            WHERE user_id = :user_id
              AND used_time = :never");
        $stmt->bindValue('user_id', (int) $user_id, \PDO::PARAM_INT);
        $stmt->bindValue('never', self::NEVER, \PDO::PARAM_STR);
        $stmt->execute();
        $count = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        return $count;
    }

    public function hasSecretKey()
    {
        return is_string($this->secret_key) && $this->secret_key !== '';
    }

    // The stored form of a secret (raw bytes) for this user
    public function encodeSecret($secret, $user_id)
    {
        if (!$this->hasSecretKey()) return self::RAW_PREFIX . (new Totp())->base32Encode($secret);

        $nonce = random_bytes(self::NONCE_BYTES);
        $tag = '';
        $ciphertext = openssl_encrypt($secret, self::CIPHER, $this->encryptionKey(), OPENSSL_RAW_DATA, $nonce, $tag,
            $this->additionalData($user_id), self::TAG_BYTES);
        if ($ciphertext === false) throw new \RuntimeException('The two-factor secret could not be encrypted');

        return self::ENCRYPTED_PREFIX . base64_encode($nonce . $ciphertext . $tag);
    }

    // The secret (raw bytes) from its stored form, or false if it can't be read: an empty value, an unknown format, a
    // secret of the wrong length, or an encrypted one with no key, the wrong key, or another user's id
    public function decodeSecret($stored, $user_id)
    {
        if (!is_string($stored) || $stored === '') return false;

        if (strncmp($stored, self::RAW_PREFIX, strlen(self::RAW_PREFIX)) === 0) {

            $secret = (new Totp())->base32Decode(substr($stored, strlen(self::RAW_PREFIX)));
            return is_string($secret) && strlen($secret) === self::SECRET_BYTES ? $secret : false;

        }

        if (strncmp($stored, self::ENCRYPTED_PREFIX, strlen(self::ENCRYPTED_PREFIX)) !== 0) return false;
        if (!$this->hasSecretKey()) return false;

        $data = base64_decode(substr($stored, strlen(self::ENCRYPTED_PREFIX)), true);
        if (!is_string($data) || strlen($data) <= self::NONCE_BYTES + self::TAG_BYTES) return false;

        $nonce = substr($data, 0, self::NONCE_BYTES);
        $tag = substr($data, -self::TAG_BYTES);
        $ciphertext = substr($data, self::NONCE_BYTES, -self::TAG_BYTES);
        $secret = openssl_decrypt($ciphertext, self::CIPHER, $this->encryptionKey(), OPENSSL_RAW_DATA, $nonce, $tag,
            $this->additionalData($user_id));

        return is_string($secret) && strlen($secret) === self::SECRET_BYTES ? $secret : false;
    }

    public function isEncrypted($stored)
    {
        return is_string($stored) && strncmp($stored, self::ENCRYPTED_PREFIX, strlen(self::ENCRYPTED_PREFIX)) === 0;
    }

    // Whether a stored secret should be saved again encrypted: it's stored without encryption and a key is now set
    public function needsEncrypting($stored)
    {
        return $this->hasSecretKey() && is_string($stored)
            && strncmp($stored, self::RAW_PREFIX, strlen(self::RAW_PREFIX)) === 0;
    }

    protected function encryptionKey()
    {
        return hash_hkdf('sha256', $this->secret_key, 32, 'domainmod-two-factor');
    }

    protected function additionalData($user_id)
    {
        return 'user:' . (int) $user_id;
    }

} //@formatter:on
