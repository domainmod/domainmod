<?php
/**
 * /classes/DomainMOD/User.php
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

class User
{
    public $deeb;
    public $time;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->time = new Time();
    }

    public function getAdminId()
    {
        return $this->deeb->cnxx->query("
            SELECT id
            FROM users
            WHERE username = 'admin'")->fetchColumn();
    }

    public function getUserId($username)
    {
        $stmt = $this->deeb->cnxx->prepare("
            SELECT id
            FROM users
            WHERE username = :username");
        $stmt->bindValue('username', $username, \PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->fetchColumn();
    }

    public function getDefaultCurrency($user_id)
    {
        $stmt = $this->deeb->cnxx->prepare("
            SELECT default_currency
            FROM user_settings
            WHERE user_id = :user_id");
        $stmt->bindValue('user_id', $user_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();
        return $result->default_currency;
    }

    public function getFullName($user_id)
    {
        $stmt = $this->deeb->cnxx->prepare("
            SELECT first_name, last_name
            FROM users
            WHERE id = :user_id");
        $stmt->bindValue('user_id', $user_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stmt->closeCursor();
        return $result->first_name . ' ' . $result->last_name;
    }

    // The shortest password people can choose when they set or change one. Existing passwords aren't checked, so nobody
    // with a shorter one is locked out.
    const MIN_PASSWORD_LENGTH = 8;

    // Whether a password someone has typed (as typed, before any escaping) is shorter than the minimum, in characters
    public function passwordTooShort($password)
    {
        return mb_strlen((string) $password, 'UTF-8') < self::MIN_PASSWORD_LENGTH;
    }

    // The message for a password that's too short, in one place so every form says the same thing
    public function passwordTooShortMessage()
    {
        return sprintf(_('The password must be at least %s characters long'), self::MIN_PASSWORD_LENGTH);
    }

    // The hint shown under a password field
    public function passwordLengthHint()
    {
        return sprintf(_('Use at least %s characters.'), self::MIN_PASSWORD_LENGTH);
    }

    // The longest password people can choose, in bytes. Passwords are hashed with bcrypt, which uses only the first 72
    // bytes (fewer characters when some take more than one byte), so a longer one is refused rather than cut short.
    const MAX_PASSWORD_BYTES = 72;

    // Whether a password someone has typed (as typed, before any escaping) is longer than the maximum, in bytes
    public function passwordTooLong($password)
    {
        return strlen((string) $password) > self::MAX_PASSWORD_BYTES;
    }

    // The message for a password that's too long, in one place so every form says the same thing
    public function passwordTooLongMessage()
    {
        return _('The password is too long');
    }

    // Whether a password someone has typed contains a NUL byte, which password_hash() can't take
    public function passwordHasUnusableCharacter($password)
    {
        return strpos((string) $password, "\0") !== false;
    }

    // The message for a password with a character that can't be used, in one place so every form says the same thing
    public function passwordUnusableMessage()
    {
        return _("The password contains a character that can't be used");
    }

    public function generatePassword($password_length = 72)
    {
        $character_pool = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        $max_index = strlen($character_pool) - 1;
        $password = '';
        for ($i = 0; $i < $password_length; $i++) {
            $password .= $character_pool[random_int(0, $max_index)];
        }
        return $password;
    }

    /*
     * The token for a password reset link. It's keyed with the user's stored password hash, so it stops working as
     * soon as the password changes, which makes each link single-use.
     */
    public function getResetToken($user_id, $expires, $stored_hash)
    {
        return hash_hmac('sha256', 'password-reset|' . $user_id . '|' . $expires, $stored_hash);
    }

    public function generateHash($password)
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    // The older password format (the one MySQL's PASSWORD() made): '*' and the upper case SHA1 of the password's binary
    // SHA1, the same as CONCAT('*', UPPER(SHA1(UNHEX(SHA1(x))))) in SQL. It's built here because MySQL 9 removed
    // SHA1(). Passwords are no longer saved in this format. It's used only at sign-in, to recognise a password saved
    // in it and move it to generateHash().
    public function generateLegacyHash($password)
    {
        return '*' . strtoupper(sha1(sha1($password, true)));
    }

    public function setDarkMode()
    {
        $stmt = $this->deeb->cnxx->prepare("
            UPDATE user_settings
            SET dark_mode = :dark_mode,
                update_time = :update_time
            WHERE user_id = :user_id");
        $timestamp = $this->time->stamp();
        $stmt->bindValue('dark_mode', $_SESSION['s_dark_mode'], \PDO::PARAM_INT);
        $stmt->bindValue('update_time', $timestamp, \PDO::PARAM_STR);
        $stmt->bindValue('user_id', $_SESSION['s_user_id'], \PDO::PARAM_INT);
        $stmt->execute();
        return;
    }

    // The database version whose update adds user_settings.display_getting_started, the Getting Started checklist's
    // per-user hide setting
    const GETTING_STARTED_DB_VERSION = '4.26.0';

    /*
     * Whether a database at this version has the Getting Started hide setting. New code can arrive before the database
     * update that adds the column (and, before the version bump, SOFTWARE_VERSION is still the older version too), so
     * the column is used only once the database itself is at that version or later.
     */
    public static function hasGettingStartedSetting($db_version)
    {
        return is_string($db_version) && $db_version !== ''
            && version_compare($db_version, self::GETTING_STARTED_DB_VERSION, '>=');
    }

    // Whether a stored or session value shows the Getting Started checklist: only an explicit 0 hides it, so a missing
    // value (before the database update, or a session from before it) leaves the checklist showing
    public static function showsGettingStarted($value)
    {
        return $value === null || (string) $value !== '0';
    }

    // Whether the Getting Started checklist can be hidden yet (see hasGettingStartedSetting()). A failed check counts as
    // no, so the checklist shows without its Hide link.
    public function canHideGettingStarted()
    {
        try {

            return self::hasGettingStartedSetting($this->getDbVersion());

        } catch (\Throwable $e) {

            $this->logSettingProblem('The database version could not be read', $e);
            return false;

        }
    }

    /*
     * Whether this user shows the Getting Started checklist (1) or has hidden it (0), or null when the setting can't be
     * read: before the database update that adds it, or when the read fails.
     */
    public function getGettingStartedDisplay($user_id)
    {
        if (!$this->canHideGettingStarted()) return null;

        try {

            $stmt = $this->deeb->cnxx->prepare("
                SELECT display_getting_started
                FROM user_settings
                WHERE user_id = :user_id");
            $stmt->bindValue('user_id', $user_id, \PDO::PARAM_INT);
            $stmt->execute();
            $value = $stmt->fetchColumn();
            $stmt->closeCursor();

            return ($value === false || $value === null) ? null : (self::showsGettingStarted($value) ? 1 : 0);

        } catch (\Throwable $e) {

            $this->logSettingProblem('The Getting Started setting could not be read', $e);
            return null;

        }
    }

    /*
     * Saves whether this user shows the Getting Started checklist (1) or hides it (0). Returns whether it was saved: false
     * before the database update that adds the setting, or when the write fails.
     */
    public function setGettingStartedDisplay($user_id, $display)
    {
        if (!$this->canHideGettingStarted()) return false;

        try {

            $stmt = $this->deeb->cnxx->prepare("
                UPDATE user_settings
                SET display_getting_started = :display_getting_started,
                    update_time = :update_time
                WHERE user_id = :user_id");
            $stmt->bindValue('display_getting_started', $display ? 1 : 0, \PDO::PARAM_INT);
            $stmt->bindValue('update_time', $this->time->stamp(), \PDO::PARAM_STR);
            $stmt->bindValue('user_id', $user_id, \PDO::PARAM_INT);
            $stmt->execute();

            return true;

        } catch (\Throwable $e) {

            $this->logSettingProblem('The Getting Started setting could not be saved', $e);
            return false;

        }
    }

    // The database's version (System::getDbVersion())
    protected function getDbVersion()
    {
        return (new System())->getDbVersion();
    }

    /*
     * Logs a problem with a user setting, in debug mode only (Log does nothing otherwise), so a failing read on every
     * Dashboard view never fills a log. Logging writes to the same database, which may be what failed, so a failed log
     * write is dropped rather than stopping the page.
     */
    protected function logSettingProblem($message, $e)
    {
        if (!defined('DEBUG_MODE') || DEBUG_MODE != 1) return;

        try {

            $this->getLog()->error($message, array('Error' => $e->getMessage()));

        } catch (\Throwable $log_e) {

            return;

        }
    }

    protected function getLog()
    {
        return new Log('class.user');
    }

} //@formatter:on
