<?php
/**
 * /classes/DomainMOD/System.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (C) 2010-2015 Greg Chetcuti <greg@chetcuti.com>
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
?>
<?php
namespace DomainMOD;

class System
{

    public function installCheck($connection, $web_root)
    {

        $full_install_path = DIR_ROOT . "install/";

        if (is_dir($full_install_path) &&

            !mysqli_num_rows(mysqli_query($connection, "SHOW TABLES LIKE '" . `dw_servers` . "'"))
        ) {

            $installation_mode = 1;
            $result_message = "<a href=\"" . $web_root . "/install/\">Click here to install</a><BR>";

        } else {

            $installation_mode = 0;
            $result_message = '';

        }

        return array($installation_mode, $result_message);

    }

    public function checkVersion($connection, $software_version)
    {

        $live_version = file_get_contents('https://raw.githubusercontent.com/domainmod/domainmod/master/version-db.txt');

        if ($software_version != $live_version && $live_version != '') {

            $sql = "UPDATE settings
                    SET upgrade_available = '1'";


        } else {

            $sql = "UPDATE settings
                    SET upgrade_available = '0'";

        }

        mysqli_query($connection, $sql);

        return true;

    }

    public function checkForRows($connection, $sql)
    {

        $result = mysqli_query($connection, $sql);

        if (mysqli_num_rows($result) >= 1) {

            return '1';

        } else {

            return '0';

        }

    }

    public function checkForRow($connection, $sql)
    {

        $result = mysqli_query($connection, $sql);

        if (mysqli_num_rows($result) == 1) {

            return '1';

        } else {

            return '0';

        }

    }

    public function buildSqlUserLogin($username, $password)
    {

        return "SELECT id
                FROM users
                WHERE username = '" . $username . "'
                  AND password = password('" . $password . "')
                  AND active = '1'";

    }

    public function buildSqlMissingFees($table)
    {

        return "SELECT id
                FROM " . $table . "
                WHERE fee_id = '0'
                  AND active NOT IN ('0', '10')";

    }

    public function buildSqlSingleAsset($table)
    {

        return "SELECT id
                FROM " . $table . "
                LIMIT 1";

    }

    public function buildSqlCurrencyExist($currency_id, $user_id)
    {

        return "SELECT id
                FROM currency_conversions
                WHERE currency_id = '" . $currency_id . "'
                  AND user_id = '" . $user_id . "'";

    }

    public function checkExistingAssets($connection)
    {

        $sql = $this->buildSqlSingleAsset('registrars');
        $_SESSION['has_registrar'] = $this->checkForRows($connection, $sql);

        $sql = $this->buildSqlSingleAsset('registrar_accounts');
        $_SESSION['has_registrar_account'] = $this->checkForRows($connection, $sql);

        $sql = $this->buildSqlSingleAsset('domains');
        $_SESSION['has_domain'] = $this->checkForRows($connection, $sql);

        $sql = $this->buildSqlSingleAsset('ssl_providers');
        $_SESSION['has_ssl_provider'] = $this->checkForRows($connection, $sql);

        $sql = $this->buildSqlSingleAsset('ssl_accounts');
        $_SESSION['has_ssl_account'] = $this->checkForRows($connection, $sql);

        $sql = $this->buildSqlSingleAsset('ssl_certs');
        $_SESSION['has_ssl_cert'] = $this->checkForRows($connection, $sql);

        return true;

    }

    public function authCheck($web_root)
    {

        if ($_SESSION['is_logged_in'] != 1) {

            $_SESSION['user_redirect'] = $_SERVER["REQUEST_URI"];

            $_SESSION['result_message'] = "You must be logged in to access this area<BR>";

            header("Location: " . $web_root . "/");
            exit;

        }

    }

    public function loginCheck($web_root)
    {

        if ($_SESSION['is_logged_in'] == 1) {

            if (isset($_SESSION['running_login_checks'])) {

                include(DIR_INC . "login-checks.inc.php");

            }

            header("Location: " . $web_root . "/domains.php");
            exit;

        }

    }

    public function checkAdminUser($web_root)
    {

        if ($_SESSION['is_admin'] !== 1) {

            header("Location: " . $web_root . "/invalid.php");
            exit;

        }

    }

}
