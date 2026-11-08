<?php
/**
 * /classes/DomainMOD/RestoreImport.php
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
 * Restores a backup over the same kind of connection as the main database connection: encrypted when it was
 * encrypted, and checking the server's certificate when it was checked (see Database::connect())
 */
class RestoreImport extends \Thamaraiselvam\MysqlImport\Import
{
    protected $restore_host;
    protected $restore_username;
    protected $restore_password;
    protected $restore_database;
    protected $restore_port;
    protected $restore_tls_options = array();
    protected $restore_verify = false;

    public function __construct($filename, $username, $password, $database, $host, $tls_options, $verify, $port = 3306)
    {
        $this->restore_host = $host;
        $this->restore_username = $username;
        $this->restore_password = $password;
        $this->restore_database = $database;
        $this->restore_port = $port;
        $this->restore_tls_options = $tls_options;
        $this->restore_verify = $verify;
        parent::__construct($filename, $username, $password, $database, $host, $port);
    }

    protected function createconnection()
    {
        if (empty($this->restore_tls_options)) {
            return new \mysqli($this->restore_host, $this->restore_username, $this->restore_password,
                               $this->restore_database, $this->restore_port);
        }

        $db = mysqli_init();
        $db->ssl_set(null, null, $this->restore_verify ? Database::caFile() : null, null, null);
        $db->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, $this->restore_verify);
        $flags = MYSQLI_CLIENT_SSL | ($this->restore_verify ? 0 : MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT);
        // A failed connection throws (PHP's default mysqli error mode) or sets connect_errno, which the importer checks
        @$db->real_connect($this->restore_host, $this->restore_username, $this->restore_password,
                           $this->restore_database, $this->restore_port, null, $flags);
        return $db;
    }

} //@formatter:on
