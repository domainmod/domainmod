<?php
/**
 * /classes/DomainMOD/Database.php
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

class Database
{

    public $cnxx;
    public $db_details;
    public $connection_options = array();
    public $certificate_checked = false;
    private static $instance;

    private function __construct()
    {
        list($this->cnxx, $this->connection_options, $this->certificate_checked) = self::connect();

        $this->cnxx->exec("SET NAMES utf8");
        $this->cnxx->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
        $this->cnxx->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_OBJ);
        $this->cnxx->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->cnxx->setAttribute(self::mysqlAttribute('USE_BUFFERED_QUERY'), true);

        $this->db_details = strtolower($this->cnxx->getAttribute(\PDO::ATTR_SERVER_VERSION));
    }

    // PHP 8.4 added Pdo\Mysql::ATTR_*, and PHP 8.5 deprecates the old PDO::MYSQL_ATTR_* names
    public static function mysqlAttribute($name)
    {
        return class_exists('Pdo\\Mysql') ? constant('Pdo\\Mysql::ATTR_' . $name) : constant('PDO::MYSQL_ATTR_' . $name);
    }

    /*
     * The CA file for 'verify': $dbsslca, otherwise the system's trusted CAs, looked up the way OpenSSL and PHP do
     * (php.ini's openssl.cafile, then the SSL_CERT_FILE environment variable, then the compiled-in default file).
     * CA directories aren't used: MySQL clients need their files hashed (c_rehash), which is easy to get wrong
     */
    public static function caFile()
    {
        if (DB_SSL_CA !== '') return DB_SSL_CA;
        $locations = openssl_get_cert_locations();
        $env_name = $locations['default_cert_file_env'] ?? '';
        $candidates = array($locations['ini_cafile'] ?? '', $env_name !== '' ? (string) getenv($env_name) : '',
                            $locations['default_cert_file'] ?? '');
        foreach ($candidates as $candidate) {
            if ($candidate !== '' && is_readable($candidate)) return $candidate;
        }
        return '';
    }

    // The PDO options for TLS: checking the server's certificate, or not
    public static function tlsOptions($verify)
    {
        if ($verify) {
            return array(self::mysqlAttribute('SSL_CA') => self::caFile(),
                         self::mysqlAttribute('SSL_VERIFY_SERVER_CERT') => true);
        }
        // An empty CA with verification off turns on TLS without checking the server's certificate
        return array(self::mysqlAttribute('SSL_CA') => '', self::mysqlAttribute('SSL_VERIFY_SERVER_CERT') => false);
    }

    /*
     * DB_SSL_MODE: 'auto' (the default): connect as before, and only if the server or account refuses an
     * unencrypted connection (3159, or 1045 for accounts that REQUIRE SSL), connect again with TLS (encrypted,
     * certificate not checked). 'verify': always TLS, checking the certificate. 'off': never TLS.
     * Returns array(PDO, the TLS options used, whether the certificate was checked)
     */
    public static function connect()
    {
        $dsn = "mysql:host=" . DB_HOSTNAME . ";dbname=" . DB_NAME . ";charset=utf8";
        $base = array(self::mysqlAttribute('INIT_COMMAND') => 'SET sql_mode="NO_ENGINE_SUBSTITUTION"',
                      \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION);

        if (!in_array(DB_SSL_MODE, array('auto', 'verify', 'off'), true)) {
            throw new \RuntimeException('Database TLS: $dbsslmode in config.inc.php must be auto, verify, or off');
        }

        if (DB_SSL_MODE === 'verify') {
            $ca = self::caFile();
            if ($ca === '' || !is_readable($ca)) {
                throw new \RuntimeException('Database TLS: no readable CA file to check the server certificate (check $dbsslca in config.inc.php)');
            }
            $tls = self::tlsOptions(true);
            try {
                return array(new \PDO($dsn, DB_USERNAME, DB_PASSWORD, $base + $tls), $tls, true);
            } catch (\PDOException $e) {
                throw new \RuntimeException('Database TLS: could not connect with certificate checking (check $dbsslmode and $dbsslca in config.inc.php): ' . $e->getMessage(), 0, $e);
            }
        }

        try {
            return array(new \PDO($dsn, DB_USERNAME, DB_PASSWORD, $base), array(), false);
        } catch (\PDOException $e) {
            $code = (int) ($e->errorInfo[1] ?? $e->getCode());
            if (DB_SSL_MODE !== 'auto' || !in_array($code, array(3159, 1045), true)) throw $e;
            $tls = self::tlsOptions(false);
            try {
                return array(new \PDO($dsn, DB_USERNAME, DB_PASSWORD, $base + $tls), $tls, false);
            } catch (\PDOException $retry) {
                // The server requires TLS (3159), so the encrypted attempt's error (e.g. a wrong password) is the
                // useful one. After a 1045, the first error is: on a server without TLS the retry fails for that reason
                if ($code === 3159) throw $retry;
                throw $e;
            }
        }
    }

    public function isEncrypted()
    {
        $row = $this->cnxx->query("SHOW SESSION STATUS LIKE 'Ssl_cipher'")->fetch(\PDO::FETCH_NUM);
        return $row && $row[1] !== '';
    }

    public function getConnectionStatus()
    {
        if (!$this->isEncrypted()) return 'not encrypted';
        return $this->certificate_checked ? 'encrypted (certificate checked)' : 'encrypted (certificate not checked)';
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
