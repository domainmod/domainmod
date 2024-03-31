<?php
/**
 * /classes/DomainMOD/Database.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2024 Greg Chetcuti <greg@chetcuti.com>
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

class Database
{

    public $cnxx;
    public $db_details;
    private static $instance;

    private function __construct()
    {
        // SSL cannot be enabled with PDO::setAttribute because the connection already exists
        $options = array(
            \PDO::MYSQL_ATTR_INIT_COMMAND => 'SET sql_mode="NO_ENGINE_SUBSTITUTION"',
            \PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => DB_SSL_VERIFY_CERT
        );
        if (DB_SSL_CA !== false) {
            $options[\PDO::MYSQL_ATTR_SSL_CA] = DB_SSL_CA;
        }
        if (DB_SSL_CAPATH !== false) {
            $options[\PDO::MYSQL_ATTR_SSL_CAPATH] = DB_SSL_CAPATH;
        }
        $dsn = "mysql:host=" . DB_HOSTNAME . ";dbname=" . DB_NAME . ";charset=utf8";
        $this->cnxx = new \PDO($dsn, DB_USERNAME, DB_PASSWORD, $options);

        $this->cnxx->exec("SET NAMES utf8");
        $this->cnxx->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
        $this->cnxx->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_OBJ);
        $this->cnxx->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->cnxx->setAttribute(\PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

        $this->db_details = strtolower($this->cnxx->getAttribute(\PDO::ATTR_SERVER_VERSION));
    }

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $object = __CLASS__;
            self::$instance = new $object;
        }
        return self::$instance;
    }

    public function getDbType():string
    {
        if(str_contains($this->db_details, 'mariadb')) {

            return 'MariaDB';

        } else {

            return 'MySQL';

        }

    }

    public function getDbVersion():string
    {
        if ($this->getDbType() == "MariaDB") {

            $return = substr($this->db_details, 0, strpos($this->db_details, "-"));

        } else {

            $return = $this->db_details;

        }

        return $return;
    }

} //@formatter:on
