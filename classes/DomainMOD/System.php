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

    public function installCheck($dbcon)
    {
        $full_install_path = DIR_ROOT . '/install/';
        $result = mysqli_query($dbcon, "SHOW TABLES LIKE 'settings'");
        $is_installed = mysqli_num_rows($result) > 0;
        if (is_dir($full_install_path) && $is_installed != '1') {
            $installation_mode = 1;
            $result_message = "DomainMOD is not yet installed<BR>";
        } else {
            $installation_mode = 0;
            $result_message = '';
        }
        return array($installation_mode, $result_message);
    }

    public function checkVersion($dbcon, $current_version)
    {
        $live_version = $this->getLiveVersion();
        if ($current_version < $live_version && $live_version != '') {
            $sql = "UPDATE settings SET upgrade_available = '1'";
            mysqli_query($dbcon, $sql);
            $_SESSION['s_system_upgrade_available'] = '1';
            $message = $this->getUpgradeMessage();
        } else {
            $sql = "UPDATE settings SET upgrade_available = '0'";
            mysqli_query($dbcon, $sql);
            $_SESSION['s_system_upgrade_available'] = '0';
            $message = "No Upgrade Available";
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
            curl_setopt($handle, CURLOPT_URL, $version_file);
            $result = curl_exec($handle);
            curl_close($handle);
            $live_version = $result;
        }
        return $live_version;
    }

    public function getUpgradeMessage()
    {
        return "A new version of DomainMOD is available for download. <a target=\"_blank\"
                href=\"http://domainmod.org/upgrade/\">Click here for upgrade instructions</a>.<BR>";
    }

    public function pageTitle($software_title, $page_title)
    {
        return $software_title . " :: " . $page_title;
    }

    public function checkExistingAssets($dbcon)
    {
        $queryB = new QueryBuild();

        $sql = $queryB->singleAsset('registrars');
        $_SESSION['s_has_registrar'] = $this->checkForRows($dbcon, $sql);
        $sql = $queryB->singleAsset('registrar_accounts');
        $_SESSION['s_has_registrar_account'] = $this->checkForRows($dbcon, $sql);
        $sql = $queryB->singleAsset('domains');
        $_SESSION['s_has_domain'] = $this->checkForRows($dbcon, $sql);
        $sql = $queryB->singleAsset('ssl_providers');
        $_SESSION['s_has_ssl_provider'] = $this->checkForRows($dbcon, $sql);
        $sql = $queryB->singleAsset('ssl_accounts');
        $_SESSION['s_has_ssl_account'] = $this->checkForRows($dbcon, $sql);
        $sql = $queryB->singleAsset('ssl_certs');
        $_SESSION['s_has_ssl_cert'] = $this->checkForRows($dbcon, $sql);
        return true;
    }

    public function checkForRows($dbcon, $sql)
    {
        $result = mysqli_query($dbcon, $sql);
        if (mysqli_num_rows($result) >= 1) { return '1'; } else { return '0'; }
    }

    public function checkForRowsResult($dbcon, $sql)
    {
        $result = mysqli_query($dbcon, $sql);
        if (mysqli_num_rows($result) >= 1) { return $result; } else { return '0'; }
    }

    public function authCheck($web_root)
    {
        if ($_SESSION['s_is_logged_in'] != 1) {
            $_SESSION['s_user_redirect'] = $_SERVER["REQUEST_URI"];
            $_SESSION['s_message_danger'] .= "You must be logged in to access this area<BR>";
            header("Location: " . $web_root . "/");
            exit;
        }
    }

    public function readOnlyCheck($redirect_url)
    {
        if ($_SESSION['s_read_only'] == '1') {
            $_SESSION['s_message_danger'] .= "You are not authorized to perform that action<BR>";
            $temp_redirect_url = urlencode($redirect_url);
            header("Location: " . $temp_redirect_url);
            exit;
        }
    }

    public function loginCheck()
    {
        if ($_SESSION['s_is_logged_in'] == 1) {
            header("Location: " . $_SESSION['s_web_root'] . "/dashboard/");
            exit;
        }
    }

    public function checkAdminUser($is_admin, $web_root)
    {
        if ($is_admin !== 1) {
            header("Location: " . $web_root . "/invalid.php");
            exit;
        }
    }

    public function showMessageSuccess($result_message)
    {
        ob_start(); ?>
        <BR>
        <div class="alert alert-success alert-dismissible">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <h4><i class="icon fa fa-check"></i> Success!</h4>
            <?php echo $result_message; ?>
        </div><?php
        return ob_get_clean();
    }

    public function showMessageDanger($result_message)
    {
        ob_start(); ?>
        <BR>
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <h4><i class="icon fa fa-exclamation-triangle"></i> Alert!</h4>
            <?php echo $result_message; ?>
        </div><?php
        return ob_get_clean();
    }

    public function showMaintenanceTable($result_message)
    {
        ob_start(); ?>
        <BR>
        <div class="alert alert-danger alert-dismissible">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <h4><i class="icon fa fa-exclamation-triangle"></i> Alert!</h4>
            <?php echo $result_message; ?>
        </div><?php
        return ob_get_clean();
    }

    public function dynamicQuery($dbcon, $query, $params1, $params2, $binding)
    {
        $error = new Error();
        $qrun = $dbcon->stmt_init();
        if ($qrun->prepare($query)) {

            call_user_func_array(array($qrun, 'bind_param'), array_merge(array($params1), $params2));
            $qrun->execute();
            $qrun->store_result();
            call_user_func_array(array($qrun, 'bind_result'), $binding);

        } else $error->outputSqlError($dbcon, '1', 'ERROR');
        return $qrun;
    }

    public function getCreationType($dbcon, $creation_type_id)
    {
        $error = new Error();
        
        $sql = "SELECT `name`
                FROM creation_types
                WHERE id = '" . $creation_type_id . "'";
        $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');
        
        if (mysqli_num_rows($result) == 1) {
        
            while ($row = mysqli_fetch_object($result)) {
                
                $creation_type = $row->name;
            
            }
        
        } else {
            
            echo 'There was a problem retrieving the creation type';
            exit;
            
        }
        
        return $creation_type;
    
    }

    public function getCreationTypeId($dbcon, $creation_type)
    {
        $error = new Error();
        
        $sql = "SELECT id
                FROM creation_types
                WHERE `name` = '" . $creation_type . "'";
        $result = mysqli_query($dbcon, $sql) or $error->outputSqlError($dbcon, '1', 'ERROR');
        
        if (mysqli_num_rows($result) == 1) {
        
            while ($row = mysqli_fetch_object($result)) {
                
                $creation_type_id = $row->id;
            
            }
        
        } else {
            
            echo 'There was a problem retrieving the creation type ID';
            exit;
            
        }
        
        return $creation_type_id;
    
    }

} //@formatter:on
