<?php
/**
 * /classes/DomainMOD/System.php
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

class System
{
    // The database version that adds settings.latest_version and latest_version_checked (see hasLatestVersion())
    const LATEST_VERSION_DB_VERSION = '4.27.0';

    /*
     * Set once this page has shown an error message (showMessageDanger()), so a form below it doesn't take the focus
     * away from the message (see Form::showInputText())
     */
    public static $error_shown = false;

    public $deeb;
    public $log;
    // The signed-in user's flags from the last valid authCheckResult() (see authFlags())
    protected $auth_flags = array('admin' => 0, 'read_only' => 1);
    public $layout;
    public $sanitize;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->log = new Log('class.system');
        $this->layout = new Layout();
        $this->sanitize = new Sanitize();
    }

    public function getRequirements()
    {
        list($req_text, $req_html_short) = $this->getReqServerSoft();
        list($req_text, $req_html_short) = $this->getReqExtensions($req_text, $req_html_short);
        list($req_text, $req_html_short) = $this->getReqSettings($req_text, $req_html_short);
        return array($req_text, $req_html_short);
    }

    public function getReqServerSoft()
    {
        $req_text = '';
        $req_html_short = '';

        // SERVER SOFTWARE
        $req_text .= _('Server Software') . ': ';
        $req_html_short .= '<STRONG>' . _('Server Software') . ':</STRONG> ';

        // PHP
        $software = $this->reqPhpText();

        if ($this->reqPhpPasses()) {

            $req_text .= $software . ': ' . _('Pass') . ', ';
            $req_html_short .= $software . ': ' . $this->layout->highlightText('green', _('Pass')) . ', ';

        } else {

            $req_text .= $software . ': ' . _('Fail') . ', ';
            $req_html_short .= $software . ': ' . $this->layout->highlightText('red', _('Fail')) . ', ';

        }

        // DATABASE (MariaDB or MySQL)
        $software = $this->reqDbText();

        if ($this->reqDbPasses()) {

            $req_text .= $software . ': ' . _('Pass') . ', ';
            $req_html_short .= $software . ': ' . $this->layout->highlightText('green', _('Pass')) . ', ';

        } else {

            $req_text .= $software . ': ' . _('Fail');
            $req_html_short .= $software . ': ' . $this->layout->highlightText('red', _('Fail')) . ', ';

        }

        $req_html_short = substr($req_html_short, 0, -2);

        return array($req_text, $req_html_short);
    }

    public function getReqExtensions($req_text, $req_html_short):array
    {
        // PHP Extensions
        $req_text .= ' / PHP Extensions: ';
        $req_html_short .= '<BR><STRONG>' . _('PHP Extensions') . ':</STRONG> ';

        foreach ($this->reqExtensionList() as $key => $value) {

            if (extension_loaded($key)) {

                $req_text .= $value . ': ' . _('Enabled') . ', ';
                $req_html_short .= $value . ': ' . $this->layout->highlightText('green', _('Enabled')) . ', ';

            } else {

                $req_text .= $value . ': ' . _('Disabled') . ', ';
                $req_html_short .= $value . ': ' . $this->layout->highlightText('red', _('Disabled')) . ', ';

            }

        }

        $req_text = substr($req_text, 0, -2);
        $req_html_short = substr($req_html_short, 0, -2);

        return array($req_text, $req_html_short);
    }

    public function getReqSettings($req_text, $req_html_short)
    {
        // PHP SETTINGS
        $req_text .= ' / PHP Settings: ';
        $req_html_short .= '<BR><STRONG>' . _('PHP Settings') . ':</STRONG> ';

        foreach ($this->reqSettingList() as $value) {

            if (ini_get($value)) {

                $req_text .= $value . ': ' . _('Enabled') . ', ';
                $req_html_short .= $value . ': ' . $this->layout->highlightText('green', _('Enabled')) . ', ';

            } else {

                $req_text .= $value . ': ' . _('Disabled') . ', ';
                $req_html_short .= $value . ': ' . $this->layout->highlightText('red', _('Disabled')) . ', ';

            }

        }

        $req_text = substr($req_text, 0, -2);
        $req_html_short = substr($req_html_short, 0, -2);

        return array($req_text, $req_html_short);
    }

    /*
     * The requirements themselves, shared by the plain-text and HTML lists above and by the checklist below
     */
    public function reqPhpText()
    {
        return 'PHP 8.2-8.5';
    }

    public function reqPhpPasses()
    {
        return version_compare(phpversion(), '8.2', '>=');
    }

    public function reqDbText()
    {
        return 'MariaDB (10.4-12.3) or MySQL (5.7-9.7)';
    }

    // The lowest version of the database DomainMOD is running on that it supports
    public function reqDbMinimum()
    {
        if ($this->deeb->getDbType() == 'MariaDB') {

            return '10.4';

        } elseif ($this->deeb->getDbType() == 'MySQL') {

            return '5.7';

        } else {

            return '0';

        }
    }

    public function reqDbPasses()
    {
        return version_compare($this->deeb->getDbVersion(), $this->reqDbMinimum(), '>=');
    }

    // The PHP extensions, each with the name people know it by
    public function reqExtensionList()
    {
        return array('pdo_mysql' => 'PDO',
                     'curl' => 'cURL',
                     'openssl' => 'OpenSSL',
                     'gettext' => 'gettext',
                     'mysqli' => 'MySQLi');
    }

    public function reqSettingList()
    {
        return array('allow_url_fopen');
    }

    /*
     * Every requirement as a row for the checklist (requirementsChecklist()): its group, name, whether it's met, its
     * status words, and a note (the version this server has, or what DomainMOD uses it for), plus how to fix it
     */
    public function getRequirementChecks()
    {
        $db_type = $this->deeb->getDbType();
        $checks = array();

        $checks[] = array('group' => 'software',
                          'name' => $this->reqPhpText(),
                          'ok' => $this->reqPhpPasses(),
                          'status' => array(_('Pass'), _('Fail')),
                          'note' => sprintf(_('You have %s'), phpversion()),
                          'fix' => sprintf(_('Ask your host to upgrade PHP to version %s or newer.'), '8.2'));

        $checks[] = array('group' => 'software',
                          'name' => $this->reqDbText(),
                          'ok' => $this->reqDbPasses(),
                          'status' => array(_('Pass'), _('Fail')),
                          'note' => sprintf(_('You have %s'), $db_type . ' ' . $this->deeb->getDbVersion()),
                          'fix' => sprintf(_('Ask your host to upgrade %1$s to version %2$s or newer.'), $db_type, $this->reqDbMinimum()));

        // What DomainMOD uses each extension for, from where the code relies on it
        $uses = array('pdo_mysql' => _('Needed for everything DomainMOD stores'),
                      'curl' => _('Needed for registrar, RDAP, and Data Warehouse lookups'),
                      'openssl' => _('Needed for secure connections'),
                      'gettext' => _('Needed to show DomainMOD in your language'),
                      'mysqli' => _('Needed to restore a backup'));

        foreach ($this->reqExtensionList() as $key => $value) {

            $checks[] = array('group' => 'extensions',
                              'name' => $value,
                              'ok' => extension_loaded($key),
                              'status' => array(_('Enabled'), _('Disabled')),
                              'note' => $uses[$key] ?? '',
                              'fix' => sprintf(_('Ask your host to enable the %s PHP extension.'), $key));

        }

        $uses = array('allow_url_fopen' => _('Used to check for new versions, with cURL as a fallback'));

        foreach ($this->reqSettingList() as $value) {

            $checks[] = array('group' => 'settings',
                              'name' => $value,
                              'ok' => (bool) ini_get($value),
                              'status' => array(_('Enabled'), _('Disabled')),
                              'note' => $uses[$value] ?? '',
                              'fix' => sprintf(_('Ask your host to turn on %s in PHP\'s settings.'), $value));

        }

        return $checks;
    }

    /*
     * The requirements as a checklist: a summary line first, then the full list, shown by default, so people can see
     * each check was made. When something isn't met, what needs attention (and how to fix it) comes first.
     * $on_installer adds the line telling people they can carry on. $collapsible puts the full list in a box that
     * opens on request (the upgrade notice), closed while everything is met and open when something needs attention.
     */
    public function requirementsChecklist($on_installer = false, $collapsible = false)
    {
        $checks = $this->getRequirementChecks();
        $total = count($checks);
        $failed = array_values(array_filter($checks, function ($check) { return !$check['ok']; }));
        $failed_count = count($failed);

        $groups = array('attention' => array(_('Needs attention'), $failed),
                        'software' => array(_('Server Software'), array()),
                        'extensions' => array(_('PHP Extensions'), array()),
                        'settings' => array(_('PHP Settings'), array()));

        foreach ($checks as $check) {
            if ($check['ok']) $groups[$check['group']][1][] = $check;
        }

        ob_start(); ?>

        <div class="domainmod-requirements">
            <div class="domainmod-requirements-summary <?php echo $failed_count === 0 ? 'is-met' : 'is-not-met'; ?>"><?php
                if ($failed_count === 0) { ?>
                    <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                    <div><strong><?php echo sprintf(ngettext('Your server meets all %s requirements.', 'Your server meets all %s requirements.', $total), number_format($total)); ?></strong><?php
                    if ($on_installer) echo ' ' . _("You're ready to continue."); ?></div><?php
                } else { ?>
                    <i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i>
                    <div><strong><?php echo sprintf(ngettext('%1$s of %2$s requirements isn\'t met.', '%1$s of %2$s requirements aren\'t met.', $failed_count), number_format($failed_count), number_format($total)); ?></strong>
                    <?php echo $on_installer ? ngettext("You can still install, but some features might not work until it's fixed.", "You can still install, but some features might not work until they're fixed.", $failed_count) : ngettext("Some features might not work until it's fixed.", "Some features might not work until they're fixed.", $failed_count); ?></div><?php
                } ?>
            </div>
            <?php if ($collapsible) { ?>
            <details class="card mb-0 domainmod-message-why"<?php echo $failed_count === 0 ? '' : ' open'; ?>>
                <summary class="card-header fw-semibold"><?php echo _('All Requirements'); ?></summary>
                <div class="card-body p-2">
            <?php } ?>
            <div class="domainmod-requirements-list"><?php
                foreach ($groups as $key => $group) {
                    list($label, $rows) = $group;
                    if (!$rows) continue; ?>
                    <div class="domainmod-requirements-group<?php echo $key === 'attention' ? ' is-attention' : ''; ?>" id="domainmod-requirements-<?php echo $key; ?>"><?php echo $label; ?></div>
                    <ul class="domainmod-requirements-rows" aria-labelledby="domainmod-requirements-<?php echo $key; ?>"><?php
                    foreach ($rows as $row) { ?>
                        <li class="<?php echo $row['ok'] ? 'is-met' : 'is-not-met'; ?>">
                            <i class="bi <?php echo $row['ok'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill'; ?>" aria-hidden="true"></i>
                            <div>
                                <span class="domainmod-requirements-name"><?php echo htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="visually-hidden">: <?php echo $row['ok'] ? $row['status'][0] : $row['status'][1]; ?>.</span><?php
                                if ($row['note'] !== '') { ?>
                                <span class="domainmod-requirements-note"><?php echo htmlspecialchars($row['note'], ENT_QUOTES, 'UTF-8'); ?><?php
                                    // A full stop for screen readers, so the note and the fix after it aren't read as one sentence
                                    if (!$row['ok']) { ?><span class="visually-hidden">.</span><?php } ?></span><?php
                                }
                                if (!$row['ok']) { ?>
                                <span class="domainmod-requirements-fix"><?php echo htmlspecialchars($row['fix'], ENT_QUOTES, 'UTF-8'); ?></span><?php
                                } ?>
                            </div>
                        </li><?php
                    } ?>
                    </ul><?php
                } ?>
            </div>
            <?php if ($collapsible) { ?>
                </div>
            </details>
            <?php } ?>
        </div><?php

        return ob_get_clean();
    }

    public function installMode()
    {
        $result = $this->checkForSettingsTable();
        $install_mode = !$result ? 1 : 0;
        return $install_mode;
    }

    public function removeLegacyTempFiles()
    {
        // Older versions left database backups, restore uploads, and CSV imports in the web-accessible temp folder
        // Returns the files that couldn't be deleted, so admins can be told to delete them by hand
        $legacy_files = array('domainmod-backup.sql', 'domainmod-restore.sql', 'import.csv');
        $undeleted_files = array();

        foreach ($legacy_files as $legacy_file) {

            if (file_exists(DIR_TEMP . '/' . $legacy_file) && !@unlink(DIR_TEMP . '/' . $legacy_file)) {

                $log_message = 'Unable to delete a sensitive file left in the temp folder by an older version';
                $log_extra = array('File' => DIR_TEMP . '/' . $legacy_file);
                $this->log->critical($log_message, $log_extra);

                $undeleted_files[] = DIR_TEMP . '/' . $legacy_file;

            }

        }

        return $undeleted_files;
    }

    public function getLegacyTempFilesWarning($undeleted_files)
    {
        $file_list = '';

        foreach ($undeleted_files as $undeleted_file) {

            $file_list .= '<strong>' . htmlspecialchars($undeleted_file, ENT_QUOTES, 'UTF-8') . '</strong><BR>';

        }

        return sprintf(_('These files were left behind by an older version of %s. They contain sensitive data, may be publicly accessible, and could not be deleted automatically. Please delete them from your server now:'), SOFTWARE_TITLE) . '<BR>' . $file_list;
    }

    public function isInsideWebRoot($path)
    {
        $real_path = realpath($path);

        if ($real_path === false) {

            return true;

        }

        // Check against both the DomainMOD folder and the web server's document root, which may be a parent of it. If
        // the document root isn't known, assume the path could be web-accessible.
        if (empty($_SERVER['DOCUMENT_ROOT'])) {

            return true;

        }

        $roots = array(DIR_ROOT, $_SERVER['DOCUMENT_ROOT']);

        foreach ($roots as $root) {

            $real_root = realpath($root);

            if ($real_root === false) {

                return true;

            }

            if (strpos($real_path . DIRECTORY_SEPARATOR, rtrim($real_root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) === 0) {

                return true;

            }

        }

        return false;
    }

    public function checkForSettingsTable()
    {
        return $this->deeb->cnxx->query("SHOW TABLES LIKE 'settings'")->fetchColumn();
    }

    /*
     * Checks GitHub for the newest release and saves the answer (see checkLatestVersion()). Returns the administrators'
     * upgrade message when a newer version is available, otherwise 'No Upgrade Available'. A failed check changes
     * nothing, so it answers from what was saved before.
     */
    public function checkVersion($current_version)
    {
        $result = $this->checkLatestVersion($current_version);

        if ($result === 'available' || ($result === 'failed' && ($_SESSION['s_system_upgrade_available'] ?? '') == '1')) {

            return $this->getUpgradeMessage();

        }

        return 'No Upgrade Available';
    }

    /*
     * Checks GitHub for the newest release (getLiveVersion()) and saves what it found. Returns 'available' (a newer
     * version than $current_version), 'current', or 'failed'. A successful check saves whether an upgrade is
     * available, the newest version, and the time of the check, together. A failed or malformed answer changes
     * nothing, so a short GitHub outage doesn't clear the "new version" notice. Before the database update that adds
     * the version and time (see canUseLatestVersion()), only whether an upgrade is available is saved. A save that fails
     * is logged and counts as a failed check, so it never stops sign-in, the Task Scheduler, or the Upgrade page.
     */
    public function checkLatestVersion($current_version)
    {
        $live_version = trim((string) $this->getLiveVersion());

        if (!self::isReleaseVersion($live_version)) return 'failed';

        $upgrade_available = version_compare((string) $current_version, $live_version, '<') ? 1 : 0;
        $pdo = $this->deeb->cnxx;

        try {

            if ($this->canUseLatestVersion()) {

                $stmt = $pdo->prepare("
                    UPDATE settings
                    SET upgrade_available = :upgrade_available,
                        latest_version = :latest_version,
                        latest_version_checked = :latest_version_checked");
                $stmt->bindValue('upgrade_available', $upgrade_available, \PDO::PARAM_INT);
                $stmt->bindValue('latest_version', $live_version, \PDO::PARAM_STR);
                $stmt->bindValue('latest_version_checked', (new Time())->stamp(), \PDO::PARAM_STR);
                $stmt->execute();

            } else {

                $stmt = $pdo->prepare("
                    UPDATE settings
                    SET upgrade_available = :upgrade_available");
                $stmt->bindValue('upgrade_available', $upgrade_available, \PDO::PARAM_INT);
                $stmt->execute();

            }

        } catch (\Throwable $e) {

            $this->logLatestVersionProblem('The latest version could not be saved', $e);
            return 'failed';

        }

        $_SESSION['s_system_upgrade_available'] = (string) $upgrade_available;

        return $upgrade_available === 1 ? 'available' : 'current';
    }

    // Whether a version read from GitHub's version.txt is a release's version (such as 4.27.0 or 4.24.02)
    public static function isReleaseVersion($version)
    {
        return is_string($version) && preg_match('/^\d+\.\d+\.\d+$/', $version) === 1;
    }

    /*
     * The newest release the last successful check found, and when (UTC), as array('version' => ..., 'checked' => ...),
     * or null when they can't be read: before the database update that adds them, or when the read fails. Before the
     * first successful check, the version is '' and the time is 1970-01-01 (see Time::isUnset()).
     */
    public function getLatestVersion()
    {
        try {

            if (!$this->canUseLatestVersion()) return null;

            $row = $this->deeb->cnxx->query("
                SELECT latest_version, latest_version_checked
                FROM settings")->fetch(\PDO::FETCH_ASSOC);

            if (!is_array($row)) return null;

            return array('version' => (string) $row['latest_version'], 'checked' => (string) $row['latest_version_checked']);

        } catch (\Throwable $e) {

            $this->logLatestVersionProblem('The latest version could not be read', $e);
            return null;

        }
    }

    /*
     * Whether a database at this version has settings.latest_version and latest_version_checked. New code can arrive
     * before the database update that adds them (and, before the version bump, SOFTWARE_VERSION is still the older
     * version too), so they're used only once the database itself is at that version or later.
     */
    public static function hasLatestVersion($db_version)
    {
        return is_string($db_version) && $db_version !== ''
            && version_compare($db_version, self::LATEST_VERSION_DB_VERSION, '>=');
    }

    // Whether the columns can be read and written now: the database has them, and no database upgrade is waiting. A
    // failed check counts as no, so sign-in and the Task Scheduler carry on as before.
    protected function canUseLatestVersion()
    {
        try {

            $db_version = $this->getDbVersion();

        } catch (\Throwable $e) {

            $this->logLatestVersionProblem('The database version could not be read', $e);
            return false;

        }

        return $db_version === SOFTWARE_VERSION && self::hasLatestVersion($db_version);
    }

    // Logs a problem with the saved latest version. Logging writes to the same database, which may be what failed, so a
    // failed log write goes to PHP's own error log instead.
    protected function logLatestVersionProblem($message, $e)
    {
        try {

            $this->log->error($message, array('Error' => $e->getMessage()));

        } catch (\Throwable $log_e) {

            error_log('DomainMOD: ' . $message . ': ' . $e->getMessage());

        }
    }

    public function getLiveVersion()
    {
        $version_file = 'https://raw.githubusercontent.com/domainmod/domainmod/master/version.txt';
        return $this->getFileContents(_('Get Live Version'), 'error', $version_file);
    }

    public function getDbVersion()
    {
        return $this->deeb->cnxx->query("
            SELECT db_version
            FROM settings")->fetchColumn();
    }

    // The notice administrators see when a new version is available, linking to the Upgrade page (admin/upgrade/), which
    // shows the steps for their kind of install
    public function getUpgradeMessage()
    {
        return sprintf(_('A new version of %s is available for download.'), SOFTWARE_TITLE) . ' <a href="'
            . htmlspecialchars(WEB_ROOT . '/admin/upgrade/', ENT_QUOTES, 'UTF-8') . '">'
            . _('Click here for upgrade instructions') . '</a>.<BR>';
    }

    public function checkExistingAssets()
    {
        $queryB = new QueryBuild();

        $sql = $queryB->singleAsset('registrars');
        $_SESSION['s_has_registrar'] = $this->checkForRows($sql);
        $sql = $queryB->singleAsset('registrar_accounts');
        $_SESSION['s_has_registrar_account'] = $this->checkForRows($sql);
        $sql = $queryB->singleAsset('domains');
        $_SESSION['s_has_domain'] = $this->checkForRows($sql);
        $sql = $queryB->singleAsset('ssl_providers');
        $_SESSION['s_has_ssl_provider'] = $this->checkForRows($sql);
        $sql = $queryB->singleAsset('ssl_accounts');
        $_SESSION['s_has_ssl_account'] = $this->checkForRows($sql);
        $sql = $queryB->singleAsset('ssl_certs');
        $_SESSION['s_has_ssl_cert'] = $this->checkForRows($sql);

        // The hosting tables exist only once the database has been updated (DomainMOD\Hosting)
        if (Hosting::isAvailable($this->deeb->cnxx)) {
            $sql = $queryB->singleAsset('hosting_provider_accounts');
            $_SESSION['s_has_hosting_provider_account'] = $this->checkForRows($sql);
            $sql = $queryB->singleAsset('hosting_services');
            $_SESSION['s_has_hosting_service'] = $this->checkForRows($sql);
        } else {
            $_SESSION['s_has_hosting_provider_account'] = '0';
            $_SESSION['s_has_hosting_service'] = '0';
        }
    }

    public function checkForRows($sql)
    {
        $result = $this->deeb->cnxx->query($sql)->fetchColumn();
        if (!$result) {
            return '0';
        } else {
            return '1';
        }
    }

    public function authCheck()
    {
        $result = $this->authCheckResult();
        if ($result === 'signed_out') {
            $_SESSION['s_user_redirect'] = $_SERVER["REQUEST_URI"];
            $_SESSION['s_message_danger'] = $_SESSION['s_message_danger'] ?? '';
            $_SESSION['s_message_danger'] .= _('You must be logged in to access this area.') . '<BR>';
            header('Location: ' . WEB_ROOT . '/');
            exit;
        } elseif ($result === 'steps_pending') {
            // A sign-in step (such as Change Password or two-factor setup) is still to do, and checks.php sends the
            // user to it
            header('Location: ' . WEB_ROOT . '/checks.php');
            exit;
        } elseif ($result === 'invalid') {
            $_SESSION['s_message_danger'] = $_SESSION['s_message_danger'] ?? '';
            $_SESSION['s_message_danger'] .= _('You must be logged in to access this area.') . '<BR>';
            header('Location: ' . WEB_ROOT . '/logout.php');
            exit;
        }
    }

    /*
     * authCheck()'s checks without its redirects, for a script that answers in JSON (such as domains/list-data.php):
     * 'valid', 'signed_out' (no signed-in session), 'invalid' (an incomplete session, or a user who's gone, inactive,
     * or whose password or two-factor sign-in has changed), or 'steps_pending' (a sign-in step is still to do). A valid session's colour mode is refreshed, as authCheck() always has.
     */
    public function authCheckResult()
    {
        $this->auth_flags = array('admin' => 0, 'read_only' => 1);
        if (($_SESSION['s_is_logged_in'] ?? 0) != 1) return 'signed_out';

        $pdo = $this->deeb->cnxx;
        // The colour mode comes along too, so a change saved in another browser shows on this one's next page, and
        // the database version, for the two-factor checks below
        $stmt = $pdo->prepare("
            SELECT u.id, u.`password`, u.admin, u.read_only, us.dark_mode, (SELECT db_version FROM settings LIMIT 1) AS db_version
            FROM users AS u
            LEFT JOIN user_settings AS us ON us.user_id = u.id
            WHERE u.username = :new_username
              AND u.active = '1'");
        $stmt->bindValue('new_username', $_SESSION['s_username'] ?? '', \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stored_hash = $result === false ? false : $result['password'];
        // An incomplete session, or a user who's gone or inactive, is logged out too
        $session_hash = $_SESSION['s_stored_hash'] ?? '';
        if ($session_hash === '' || $stored_hash === false || $session_hash != $stored_hash) return 'invalid';

        $two_factor_result = $this->twoFactorResult((int) $result['id'], $result['db_version']);
        if ($two_factor_result !== 'valid') return $two_factor_result;

        if ($result['dark_mode'] !== null) $_SESSION['s_dark_mode'] = (int) $result['dark_mode'];

        $this->auth_flags = array('admin' => (int) $result['admin'], 'read_only' => (int) $result['read_only']);
        return 'valid';
    }

    /*
     * The signed-in user's administrator and read-only flags as the database has them now, from the same row the last
     * valid authCheckResult() authenticated, never the values the session cached at sign-in (so a restore that removed
     * someone's administrator access also removes their right to finish a restore). Both are 0 and 1 respectively until
     * a check has passed.
     */
    public function authFlags()
    {
        return $this->auth_flags;
    }

    /*
     * Two-factor sign-in's part of authCheckResult(), from the live database version (never the session's, which can
     * be out of date): before the database has it, a session that knew about it (one created on an upgraded database,
     * such as before a restore from older backup) is invalid, and every other session is as before. After, see
     * TwoFactor::sessionResult(). A version that can't be read fails closed.
     *
     * It also keeps s_two_factor_on (1 or 0) in the session from the same read, for the user menu's MFA line, so the
     * line follows a change made in another tab of the same session without a read of its own. Before the database has
     * two-factor sign-in, or when the read fails or finds no active user, the key is removed and the menu leaves the
     * line out.
     */
    protected function twoFactorResult($user_id, $db_version)
    {
        try {

            if (!TwoFactor::isAvailableStrict($db_version)) {
                unset($_SESSION['s_two_factor_on']);
                return isset($_SESSION['s_two_factor_stamp']) ? 'invalid' : 'valid';
            }

            $two_factor = $this->newTwoFactor();
            $state = $two_factor->getState($user_id);
            if (is_array($state)) {
                $_SESSION['s_two_factor_on'] = $two_factor->isOn($state) ? 1 : 0;
            } else {
                unset($_SESSION['s_two_factor_on']);
            }
            return $two_factor->sessionResult($state, $_SESSION, $this->currentPage());

        } catch (\Throwable $e) {

            unset($_SESSION['s_two_factor_on']);
            return 'invalid';

        }
    }

    // A TwoFactor for twoFactorResult() (tests replace it)
    protected function newTwoFactor()
    {
        return new TwoFactor();
    }

    // This page's path from the install's root, such as /settings/password/index.php
    protected function currentPage()
    {
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        if (WEB_ROOT !== '' && strncmp($script, WEB_ROOT . '/', strlen(WEB_ROOT) + 1) === 0) $script = substr($script, strlen(WEB_ROOT));
        return $script;
    }

    /*
     * The installer's first step whose answer isn't in the session yet, as its address, or '' when every answer is there.
     * The Ready to Install step and install/go.php use it, so nothing installs with an answer missing (such as when
     * someone opens the last step directly, or their session has expired).
     */
    // Reads the session only, so the installer can check it before it waits for the restore lock (install/go.php)
    public static function installFirstMissingStep()
    {
        $steps = array(
            'language' => array('s_installation_language'),
            'currency' => array('s_installation_currency'),
            'timezone' => array('s_installation_timezone'),
            'administrator' => array('s_installation_admin_first_name', 's_installation_admin_last_name',
                's_installation_admin_username', 's_installation_admin_email', 's_installation_admin_hash'),
            'email-system' => array('new_system_email'));

        foreach ($steps as $step => $keys) {
            foreach ($keys as $key) {
                if ((string) ($_SESSION[$key] ?? '') === '') return WEB_ROOT . '/install/' . $step . '/';
            }
        }

        return '';
    }

    public function installCheck()
    {
        if ($this->installMode() === 0) {
            $_SESSION['s_message_danger'] = $_SESSION['s_message_danger'] ?? '';
            $_SESSION['s_message_danger'] .= sprintf(_('%s is already installed.'), SOFTWARE_TITLE) . "<BR>";
            header('Location: ' . WEB_ROOT . '/');
            exit;
        }
    }

    public function readOnlyCheck($redirect_url)
    {
        if (($_SESSION['s_read_only'] ?? '') == '1') {
            $_SESSION['s_message_danger'] = $_SESSION['s_message_danger'] ?? '';
            $_SESSION['s_message_danger'] .= _('You are not authorized to perform that action.') . '<BR>';
            header('Location: ' . $this->sanitize->text($redirect_url));
            exit;
        }
    }

    public function loginCheck()
    {
	    $logged_in = $_SESSION['s_is_logged_in'] ?? '';
        if ($logged_in == 1) {
            header('Location: ' . WEB_ROOT . '/dashboard/');
            exit;
        }
    }

    public function checkAdminUser($is_admin)
    {
        if ($is_admin !== 1) {
            header('Location: ' . WEB_ROOT . "/invalid.php");
            exit;
        }
    }

    public function getDebugMode()
    {
        $pdo = $this->deeb->cnxx;
        $result = $this->checkForSettingsTable();
        if (!$result) return '0';
        $stmt = $pdo->query("SHOW COLUMNS FROM `settings` LIKE 'debug_mode'");
        if ($stmt === false) return '0';
        $result = $stmt->fetchColumn();
        if (!$result) {
            return '0';
        } else {
            return $pdo->query("SELECT debug_mode FROM settings")->fetchColumn();
        }
    }

    /*
     * Whether this request came from htmx (its HX-Request header), so a page can answer with just the part that changed
     * instead of a redirect or the whole page
     */
    public function isHtmxRequest()
    {
        return ($_SERVER['HTTP_HX_REQUEST'] ?? '') === 'true';
    }

    /*
     * Answers an htmx request for a task (such as a maintenance task) with the result messages the next page would have
     * shown, in the same alerts, and clears them so they don't show again. The X-DomainMOD-Result header tells the
     * footer's script this is a real result, not a page that a redirect (such as a failed check's) landed on.
     */
    public function htmxTaskResult()
    {
        header('X-DomainMOD-Result: 1');
        $result = '';
        if (($_SESSION['s_message_danger'] ?? '') != '') $result .= $this->showMessageDanger($_SESSION['s_message_danger']);
        if (($_SESSION['s_message_warning'] ?? '') != '') $result .= $this->showMessageWarning($_SESSION['s_message_warning']);
        if (($_SESSION['s_message_success'] ?? '') != '') $result .= $this->showMessageSuccess($_SESSION['s_message_success']);
        $_SESSION['s_message_danger'] = '';
        $_SESSION['s_message_warning'] = '';
        $_SESSION['s_message_success'] = '';
        return $result;
    }

    /*
     * The page messages are slim, dismissible alerts at the form width: an icon, the message, and a close button, with
     * no heading and no ARIA role (js/messages.js reads each one to screen readers once, through the footer's shared
     * announcer). A short success becomes a toast in the main layout, so the page doesn't move (see js/messages.js).
     */
    public function showMessageSuccess($result_message)
    {
        $result_message = $this->trimMessage($result_message);
        $toast = $this->isShortMessage($result_message);
        return $this->slimMessage('success', 'bi-check-circle-fill', $result_message,
            $toast ? ' domainmod-toast-source' : '', $toast ? ' data-domainmod-toast' : '');
    }

    // A success that always stays in the page, for the sign-in, bare, and installer layouts, which have no toasts
    public function showMessageSuccessInline($result_message)
    {
        return $this->slimMessage('success', 'bi-check-circle-fill', $this->trimMessage($result_message));
    }

    public function showMessageDanger($result_message)
    {
        self::$error_shown = true;
        return $this->slimMessage('danger', 'bi-exclamation-octagon-fill', $this->trimMessage($result_message));
    }

    public function showMessageInfo($result_message)
    {
        return $this->slimMessage('info', 'bi-info-circle-fill', $this->trimMessage($result_message));
    }

    // Something the user should act on after a change, such as an item saved without a fee (DomainMOD\FeeStatus)
    public function showMessageWarning($result_message)
    {
        return $this->slimMessage('warning', 'bi-exclamation-triangle-fill', $this->trimMessage($result_message));
    }

    public function showMaintenanceTable($result_message)
    {
        return $this->slimMessage('warning', 'bi-exclamation-triangle-fill',
            '<strong>' . _('Attention required:') . '</strong> ' . $this->trimMessage($result_message));
    }

    public function showDebugTable($result_message)
    {
        return $this->slimMessage('info', 'bi-bug-fill', $this->trimMessage($result_message));
    }

    /*
     * A result in parts (DomainRefresh::refresh()), $type being 'success' or 'danger': the bold summary, any reasons as
     * plain lines, then the changes (a count sentence and a list), and any notes behind a "Why?" disclosure, so the
     * reason something failed is always visible and only the background detail is tucked away. The parts are already
     * translated and escaped. It stays in the page, never a toast.
     */
    public function showMessageResult($type, $parts)
    {
        if ($type === 'danger') self::$error_shown = true;
        $changes = $parts['changes'] ?? array();
        $notes = $parts['notes'] ?? array();
        $html = '<strong>' . ($parts['summary'] ?? '') . '</strong>';
        foreach ($parts['reasons'] ?? array() as $reason) {
            $html .= '<div>' . $reason . '</div>';
        }
        if ($changes) {
            $count = count($changes);
            $html .= '<div>' . sprintf(ngettext('%s field changed.', '%s fields changed.', $count), number_format($count)) . '</div>';
            $html .= '<ul class="mb-0">';
            foreach ($changes as $change) {
                $html .= '<li>' . $change . '</li>';
            }
            $html .= '</ul>';
        }
        if ($notes) {
            $html .= '<details class="domainmod-message-why"><summary>' . htmlspecialchars(_('Why?'), ENT_QUOTES, 'UTF-8') . '</summary>';
            $html .= '<div>' . implode('<br>', $notes) . '</div></details>';
        }
        return $this->slimMessage($type === 'danger' ? 'danger' : 'success',
            $type === 'danger' ? 'bi-exclamation-octagon-fill' : 'bi-check-circle-fill', $html);
    }

    protected function slimMessage($type, $icon, $message, $extra_class = '', $extra_attributes = '')
    {
        ob_start(); ?>
        <div class="alert alert-<?php echo $type; ?> alert-dismissible d-flex align-items-start gap-2 py-2 domainmod-message<?php echo $extra_class; ?>"<?php echo $extra_attributes; ?>>
            <i class="bi <?php echo $icon; ?> domainmod-message-icon" aria-hidden="true"></i>
            <div class="domainmod-message-text"><?php echo $message; ?></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php echo htmlspecialchars(_('Close'), ENT_QUOTES, 'UTF-8'); ?>"></button>
        </div><?php
        return ob_get_clean();
    }

    // The message without the <BR> tags and whitespace callers leave at its start and end, so the alert doesn't end
    // with blank lines. Everything else, inner <BR> tags included, is left as it is.
    protected function trimMessage($result_message)
    {
        return preg_replace('/^(?:\s|&nbsp;|<br\s*\/?>)+|(?:\s|&nbsp;|<br\s*\/?>)+$/i', '', (string) $result_message);
    }

    // A success short enough for a toast: one part (no inner <BR>) and at most 100 characters of text. It can hold a
    // link, such as the new domain in "Domain example.com added", since the toast has no timeout.
    protected function isShortMessage($result_message)
    {
        if (preg_match('/<br\s*\/?>/i', $result_message)) return false;
        $text = html_entity_decode(strip_tags($result_message), ENT_QUOTES, 'UTF-8');
        $length = function_exists('mb_strlen') ? mb_strlen($text, 'UTF-8') : preg_match_all('/./us', $text);
        return $length > 0 && $length <= 100;
    }

    public function getCreationType($creation_type_id)
    {
        $pdo = $this->deeb->cnxx;
        $stmt = $pdo->prepare("
            SELECT `name`
            FROM creation_types
            WHERE id = :creation_type_id");
        $stmt->bindValue('creation_type_id', $creation_type_id, \PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve creation type';
            $log_extra = array('Creation Type ID' => $creation_type_id);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getCreationTypeId($creation_type)
    {
        $pdo = $this->deeb->cnxx;
        $stmt = $pdo->prepare("
            SELECT id
            FROM creation_types
            WHERE `name` = :creation_type");
        $stmt->bindValue('creation_type', $creation_type, \PDO::PARAM_STR);
        $stmt->execute();
        $result = $stmt->fetchColumn();

        if (!$result) {

            $log_message = 'Unable to retrieve creation type ID';
            $log_extra = array('Creation Type' => $creation_type, 'Result' => $result);
            $this->log->critical($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getFileContents($file_title, $log_severity, $filename)
    {

        if (ini_get('allow_url_fopen') && extension_loaded('openssl')) {

            $file_contents = $this->getFileContFopen($filename);

        } elseif (extension_loaded('curl')) {

            $file_contents = $this->getFileContCurl($filename);

        } else {

            $log_message = 'Unable to get file contents';
            list($requirements) = $this->getRequirements();
            $log_extra = array('File Title' => $file_title, 'Requirements' => $requirements);
            $this->log->{$log_severity}($log_message, $log_extra);
            $file_contents = '';

        }

        return $file_contents;
    }

    public function getFileContFopen($filename)
    {
        // PHP reads HTTP options from the 'http' key for https:// URLs too. The timeout is per read, not a total limit.
        $context = stream_context_create(array(
            'http' => array('header' => "Connection: close\r\n", 'timeout' => 15),
            'ssl' => HttpClient::streamSslOptions()));
        return file_get_contents($filename, false, $context);
    }

    public function getFileContCurl($filename)
    {
        $handle = HttpClient::init($filename, 10, 15);
        return HttpClient::exec($handle, 'Remote file');
    }

    public function getIpRemotely()
    {
        return $this->publicIp($this->getFileContents(_('External IP API Call') . ' (ipify)', 'warning', 'https://api.ipify.org'));
    }

    /*
     * The address an IP lookup answered with, or '' when the answer isn't an IP address (a failed call, or an error
     * page), so nothing else from the outside service is shown.
     */
    public function publicIp($answer)
    {
        $answer = trim((string) $answer);
        return filter_var($answer, FILTER_VALIDATE_IP) !== false ? $answer : '';
    }

} //@formatter:on
