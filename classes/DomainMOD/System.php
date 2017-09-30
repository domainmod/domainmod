<?php
/**
 * /classes/DomainMOD/System.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>
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
    public $log;

    public function __construct()
    {
        $this->log = new Log('class.system');
    }

    public function db()
    {
        $pdo = new \PDO("mysql:host=" . DB_HOSTNAME . ";dbname=" . DB_NAME . ";charset=utf8", DB_USERNAME, DB_PASSWORD);
        $pdo->exec("SET NAMES utf8");
        $pdo->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
        $pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_OBJ);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    public function installCheck()
    {
        $full_install_path = DIR_ROOT . '/install/';

        $result = $this->checkForSettingsTable();

        if (!$result && is_dir($full_install_path)) {

            $installation_mode = 1;
            $result_message = 'DomainMOD is not yet installed<BR>';

        } else {

            $installation_mode = 0;
            $result_message = '';

        }

        return array($installation_mode, $result_message);
    }

    public function checkForSettingsTable()
    {
        return $this->db()->query("SHOW TABLES LIKE 'settings'")->fetchColumn();
    }

    public function checkVersion($current_version)
    {
        $pdo = $this->db();
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
        $context = stream_context_create(array('https' => array('header' => 'Connection: close\r\n')));
        $version_fgc = file_get_contents($version_file, false, $context);
        if ($version_fgc) {
            $live_version = $version_fgc;
        } else {
            $handle = curl_init();
            curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($handle, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($handle, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($handle, CURLOPT_URL, $version_file);
            $result = curl_exec($handle);
            curl_close($handle);
            $live_version = $result;
        }
        return $live_version;
    }

    public function getDbVersion()
    {
        return $this->db()->query("
            SELECT db_version
            FROM settings")->fetchColumn();
    }

    public function getUpgradeMessage()
    {
        return "A new version of DomainMOD is available for download. <a target=\"_blank\"
                href=\"http://domainmod.org/upgrade/\">Click here for upgrade instructions</a>.<BR>";
    }

    public function pageTitle($page_title)
    {
        return SOFTWARE_TITLE . ' :: ' . $page_title;
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
        $result = $this->db()->query($sql)->fetchColumn();
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
            $_SESSION['s_message_danger'] .= 'You must be logged in to access this area<BR>';
            header('Location: ' . WEB_ROOT . '/');
            exit;
        }
    }

    public function readOnlyCheck($redirect_url)
    {
        if ($_SESSION['s_read_only'] == '1') {
            $_SESSION['s_message_danger'] .= "You are not authorized to perform that action<BR>";
            $temp_redirect_url = urlencode($redirect_url);
            header('Location: ' . $temp_redirect_url);
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
        $pdo = $this->db();
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
        <BR>
        <div class="alert alert-success alert-dismissible">
        <?php /* ?>
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
        <?php */ ?>
            <h4><i class="icon fa fa-check"></i> Success</h4>
            <?php echo $result_message; ?>
        </div><?php
        return ob_get_clean();
    }

    public function showMessageDanger($result_message)
    {
        ob_start(); ?>
        <BR>
        <div class="alert alert-danger alert-dismissible">
            <h4><i class="icon fa fa-exclamation-circle"></i> Alert!</h4>
            <?php echo $result_message; ?>
        </div><?php
        return ob_get_clean();
    }

    public function showMaintenanceTable($result_message)
    {
        ob_start(); ?>
        <BR>
        <div class="alert alert-warning alert-dismissible">
            <h4><i class="icon fa fa-exclamation-triangle"></i> Attention Required!</h4>
            <?php echo $result_message; ?>
        </div><?php
        return ob_get_clean();
    }

    public function showDebugTable($result_message)
    {
        ob_start(); ?>
        <BR>
        <div class="alert alert-info alert-dismissible bg-aqua-active">
            <h4><i class="icon fa fa-info-circle"></i> Info</h4>
            <?php echo $result_message; ?>
        </div><?php
        return ob_get_clean();
    }

    public function getCreationType($creation_type_id)
    {
        $pdo = $this->db();
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
            $this->log->error($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

    public function getCreationTypeId($creation_type)
    {
        $pdo = $this->db();
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
            $this->log->error($log_message, $log_extra);
            return $log_message;

        } else {

            return $result;

        }
    }

} //@formatter:on
