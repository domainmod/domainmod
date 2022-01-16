<?php
/**
 * /classes/DomainMOD/System.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2022 Greg Chetcuti <greg@chetcuti.com>
 *
 * Project: http://domainmod.org   Author: http://chetcuti.com
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
    public $deeb;
    public $log;
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
        list($req_text, $req_html_short, $req_html_long) = $this->getReqServerSoft();
        list($req_text, $req_html_short, $req_html_long) = $this->getReqExtensions($req_text, $req_html_short, $req_html_long);
        list($req_text, $req_html_short, $req_html_long) = $this->getReqSettings($req_text, $req_html_short, $req_html_long);
        return array($req_text, $req_html_short, $req_html_long);
    }

    public function getReqServerSoft()
    {
        $req_text = '';
        $req_html_short = '';
        $req_html_long = '';

        // SERVER SOFTWARE
        $req_text .= _('Server Software') . ': ';
        $req_html_short .= '<STRONG>' . _('Server Software') . ':</STRONG> ';
        $req_html_long .= '<STRONG>' . _('Server Software') . '</STRONG><BR>';

        // PHP
        $software = 'PHP v5.5+';
        $min_php_version = '5.5';
        $installed_php_version = phpversion();

        if ($installed_php_version >= $min_php_version) {

            $req_text .= $software . ': ' . _('Pass') . ', ';
            $req_html_short .= $software . ': ' . $this->layout->highlightText('green', _('Pass')) . ', ';
            $req_html_long .= $software . ': ' . $this->layout->highlightText('green', _('Pass')) . '<BR>';

        } else {

            $req_text .= $software . ': ' . _('Fail') . ', ';
            $req_html_short .= $software . ': ' . $this->layout->highlightText('red', _('Fail')) . ', ';
            $req_html_long .= $software . ': ' . $this->layout->highlightText('red', _('Fail')) . '<BR>';

        }

        // MySQL
        $software = _('MySQL');
        if (extension_loaded('pdo_mysql')) {

            $req_text .= $software . ': ' . _('Pass') . ', ';
            $req_html_short .= $software . ': ' . $this->layout->highlightText('green', _('Pass')) . ', ';
            $req_html_long .= $software . ': ' . $this->layout->highlightText('green', _('Pass')) . '<BR>';

        } else {

            $req_text .= $software . ': ' . _('Fail');
            $req_html_short .= $software . ': ' . $this->layout->highlightText('red', _('Fail')) . ', ';
            $req_html_long .= $software . ': ' . $this->layout->highlightText('red', _('Fail')) . '<BR>';

        }

        $req_html_short = substr($req_html_short, 0, -2);

        return array($req_text, $req_html_short, $req_html_long);
    }

    public function getReqExtensions($req_text, $req_html_short, $req_html_long)
    {
        // PHP Extensions
        $req_text .= ' / PHP Extensions: ';
        $req_html_short .= '<BR><STRONG>' . _('PHP Extensions') . ':</STRONG> ';
        $req_html_long .= '<BR><STRONG>' . _('PHP Extensions') . '</STRONG><BR>';

        $extensions = array('pdo_mysql' => 'PDO (MySQL)',
                            'curl' => 'cURL',
                            'openssl' => 'OpenSSL',
                            'gettext' => 'gettext');

        foreach ($extensions as $key => $value) {

            if (extension_loaded($key)) {

                $req_text .= $value . ': ' . _('Enabled') . ', ';
                $req_html_short .= $value . ': ' . $this->layout->highlightText('green', _('Enabled')) . ', ';
                $req_html_long .= $value . ': ' . $this->layout->highlightText('green', _('Enabled')) . '<BR>';

            } else {

                $req_text .= $value . ': ' . _('Disabled') . ', ';
                $req_html_short .= $value . ': ' . $this->layout->highlightText('red', _('Disabled')) . ', ';
                $req_html_long .= $value . ': ' . $this->layout->highlightText('red', _('Disabled')) . '<BR>';

            }

        }

        $req_text = substr($req_text, 0, -2);
        $req_html_short = substr($req_html_short, 0, -2);

        return array($req_text, $req_html_short, $req_html_long);
    }

    public function getReqSettings($req_text, $req_html_short, $req_html_long)
    {
        // PHP SETTINGS
        $req_text .= ' / PHP Settings: ';
        $req_html_short .= '<BR><STRONG>' . _('PHP Settings') . ':</STRONG> ';
        $req_html_long .= '<BR><STRONG>' . _('PHP Settings') . '</STRONG><BR>';

        $settings = array('allow_url_fopen');

        foreach ($settings as $value) {

            if (ini_get($value)) {

                $req_text .= $value . ': ' . _('Enabled') . ', ';
                $req_html_short .= $value . ': ' . $this->layout->highlightText('green', _('Enabled')) . ', ';
                $req_html_long .= $value . ': ' . $this->layout->highlightText('green', _('Enabled')) . '<BR>';

            } else {

                $req_text .= $value . ': ' . _('Disabled') . ', ';
                $req_html_short .= $value . ': ' . $this->layout->highlightText('red', _('Disabled')) . ', ';
                $req_html_long .= $value . ': ' . $this->layout->highlightText('red', _('Disabled')) . '<BR>';

            }

        }

        $req_text = substr($req_text, 0, -2);
        $req_html_short = substr($req_html_short, 0, -2);

        return array($req_text, $req_html_short, $req_html_long);
    }

    public function installMode()
    {
        $result = $this->checkForSettingsTable();
        $install_mode = !$result ? 1 : 0;
        return $install_mode;
    }

    public function checkForSettingsTable()
    {
        return $this->deeb->cnxx->query("SHOW TABLES LIKE 'settings'")->fetchColumn();
    }

    public function checkVersion($current_version)
    {
        $pdo = $this->deeb->cnxx;
        $live_version = $this->getLiveVersion();

        if ($current_version < $live_version && $live_version != '') {

            $pdo->query("UPDATE settings SET upgrade_available = '1'");
            $_SESSION['s_system_upgrade_available'] = '1';
            $message = $this->getUpgradeMessage();

        } else {

            $pdo->query("UPDATE settings SET upgrade_available = '0'");
            $_SESSION['s_system_upgrade_available'] = '0';
            $message = 'No Upgrade Available';

        }
        return $message;
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

    public function getUpgradeMessage()
    {
        return sprintf(_('A new version of %s is available for download.'), SOFTWARE_TITLE) . " <a target=\"_blank\"
                href=\"http://domainmod.org/upgrade/\">" . _('Click here for upgrade instructions') . "</a>.<BR>";
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
        if ($_SESSION['s_is_logged_in'] != 1) {
            $_SESSION['s_user_redirect'] = $_SERVER["REQUEST_URI"];
            $_SESSION['s_message_danger'] .= _('You must be logged in to access this area') . '<BR>';
            header('Location: ' . WEB_ROOT . '/');
            exit;
        } else {
            $pdo = $this->deeb->cnxx;
            $stmt = $pdo->prepare("
                SELECT `password`
                FROM users
                WHERE username = :new_username
                  AND active = '1'");
            $stmt->bindValue('new_username', $_SESSION['s_username'], \PDO::PARAM_STR);
            $stmt->execute();
            $stored_hash = $stmt->fetchColumn();
            if ($_SESSION['s_stored_hash'] != $stored_hash) {
                $_SESSION['s_message_danger'] .= _('You must be logged in to access this area') . '<BR>';
                header('Location: ' . WEB_ROOT . '/logout.php');
                exit;
            }
        }
    }

    public function installCheck()
    {
        if ($this->installMode() === 0) {
            $_SESSION['s_message_danger'] .= sprintf(_('%s is already installed'), SOFTWARE_TITLE) . "<BR><BR>" . sprintf(_('You should delete the %s folder'), '/install/') . "<BR>";
            header('Location: ' . WEB_ROOT . '/');
            exit;
        }
    }

    public function readOnlyCheck($redirect_url)
    {
        if ($_SESSION['s_read_only'] == '1') {
            $_SESSION['s_message_danger'] .= _('You are not authorized to perform that action') . '<BR>';
            header('Location: ' . $this->sanitize->text($redirect_url));
            exit;
        }
    }

    public function loginCheck()
    {
        if ($_SESSION['s_is_logged_in'] == 1) {
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

    public function showMessageSuccess($result_message)
    {
        ob_start(); ?>
        <div class="alert alert-success alert-dismissible">
        <?php /* ?>
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <?php */ ?>
            <h4><i class="icon fa fa-check"></i> <?php echo _('Success'); ?></h4>
            <?php echo $result_message; ?>
        </div><?php
        return ob_get_clean();
    }

    public function showMessageDanger($result_message)
    {
        ob_start(); ?>
        <div class="alert alert-danger alert-dismissible">
            <h4><i class="icon fa fa-exclamation-circle"></i> <?php echo _('Alert'); ?>!</h4>
            <?php echo $result_message; ?>
        </div><?php
        return ob_get_clean();
    }

    public function showMessageInfo($result_message)
    {
        ob_start(); ?>
        <div class="alert alert-info alert-dismissible">
        <?php /* ?>
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <?php */ ?>
            <h4><i class="icon fa fa-info"></i> <?php echo _('Info'); ?></h4>
            <?php echo $result_message; ?>
        </div><?php
        return ob_get_clean();
    }

    public function showMaintenanceTable($result_message)
    {
        ob_start(); ?>
        <div class="alert alert-warning alert-dismissible">
            <h4><i class="icon fa fa-exclamation-triangle"></i> <?php echo _('Attention Required'); ?>!</h4>
            <?php echo $result_message; ?>
        </div><?php
        return ob_get_clean();
    }

    public function showDebugTable($result_message)
    {
        ob_start(); ?>
        <div class="alert alert-info alert-dismissible bg-aqua-active">
            <h4><i class="icon fa fa-info-circle"></i> <?php echo _('Info'); ?></h4>
            <?php echo $result_message; ?>
        </div><?php
        return ob_get_clean();
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
            list($requirements, $null, $null) = $this->getRequirements();
            $log_extra = array('File Title' => $file_title, 'Requirements' => $requirements);
            $this->log->{$log_severity}($log_message, $log_extra);
            $file_contents = '';

        }

        return $file_contents;
    }

    public function getFileContFopen($filename)
    {
        $context = stream_context_create(array('https' => array('header' => 'Connection: close\r\n')));
        return file_get_contents($filename, false, $context);
    }

    public function getFileContCurl($filename)
    {
        $handle = curl_init();
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($handle, CURLOPT_URL, $filename);
        $result = curl_exec($handle);
        curl_close($handle);
        return $result;
    }

    public function getIpRemotely()
    {
        return $this->getFileContents(_('External IP API Call') . ' (ipify)', 'warning', 'https://api.ipify.org');
    }

} //@formatter:on
