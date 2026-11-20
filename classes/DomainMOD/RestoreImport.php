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
 * encrypted, and checking the server's certificate when it was checked (see Database::connect()). A restore that
 * can't finish throws (see openfile()).
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
    protected $restore_filename;
    protected $restore_connection;

    public function __construct($filename, $username, $password, $database, $host, $tls_options, $verify, $port = 3306)
    {
        $this->restore_filename = $filename;
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
            $this->restore_connection = new \mysqli($this->restore_host, $this->restore_username, $this->restore_password,
                                                    $this->restore_database, $this->restore_port);
            return $this->restore_connection;
        }

        $db = mysqli_init();
        $db->ssl_set(null, null, $this->restore_verify ? Database::caFile() : null, null, null);
        $db->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, $this->restore_verify);
        $flags = MYSQLI_CLIENT_SSL | ($this->restore_verify ? 0 : MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT);
        // A failed connection throws (PHP's default mysqli error mode) or sets connect_errno, which the importer checks
        @$db->real_connect($this->restore_host, $this->restore_username, $this->restore_password,
                           $this->restore_database, $this->restore_port, null, $flags);
        $this->restore_connection = $db;
        return $db;
    }

    /*
     * Runs the backup's statements in order, the same way as the importer this extends, but stops at the first one that
     * fails and throws, where the importer printed the error and returned as if the restore had worked. The whole file
     * is checked first (see checkFile()), so a backup that's been cut short throws before anything is changed.
     */
    protected function openfile()
    {
        try {

            self::checkFile($this->restore_filename);

            // MariaDB's error message can quote the statement, which can hold a saved password or API key, so only
            // the statement's number and the error's code are passed on
            $number = 0;
            self::readStatements($this->restore_filename, function ($statement) use (&$number) {
                $number++;
                try {
                    $this->query($statement);
                } catch (\Exception $e) {
                    $code = $e instanceof \mysqli_sql_exception ? $e->getCode() : ($this->restore_connection->errno ?? 0);
                    throw new \RuntimeException(sprintf(_('Statement %1$d in the backup file failed (database error %2$d).'), $number, (int) $code));
                }
            });

        } finally {

            if ($this->restore_connection instanceof \mysqli) $this->restore_connection->close();

        }
    }

    /*
     * Throws if the backup file can't be read to the end, has no statements, or has been cut short: it ends partway
     * through a statement, or it's one of DomainMOD's own backups without its closing "-- Dump completed" line. Nothing
     * is run.
     */
    public static function checkFile($filename)
    {
        self::readStatements($filename, null);
    }

    // Reads the backup's statements in order, handing each to $run when it's given, and throws as checkFile() says
    protected static function readStatements($filename, $run)
    {
        $fp = false;

        try {

            $fp = is_file($filename) ? fopen($filename, 'r') : false;
            if ($fp === false) throw new \RuntimeException(_('The backup file could not be read.'));

            $statement = '';
            $own_backup = null;
            $dated = false;
            $last_line = '';
            $statements = 0;

            while (($line = fgets($fp)) !== false) {

                // DomainMOD's backups (made with mysqldump-php) start with its header line, and have a date in the
                // header when they have one in the closing line
                if ($own_backup === null) $own_backup = strpos($line, '-- mysqldump-php ') === 0;
                if (strpos($line, '-- Date: ') === 0) $dated = true;
                if (trim($line) !== '') $last_line = trim($line);

                // Skip comments
                if (substr($line, 0, 2) == '--' || $line == '') continue;

                $statement .= $line;

                // A semicolon at the end of a line ends the statement
                if (substr(trim($line), -1, 1) == ';') {
                    $statements++;
                    if ($run !== null) $run($statement);
                    $statement = '';
                }

            }

            if (!feof($fp)) throw new \RuntimeException(_('The backup file could not be read to the end.'));

            // Anything left after the last statement, apart from ordinary comments, is a statement that was cut short
            if (trim(self::withoutComments($statement)) !== '') {
                throw new \RuntimeException(_('The backup file ends partway through a statement, so it may have been cut short.'));
            }

            if ($statements === 0) throw new \RuntimeException(_('The backup file has no SQL statements in it.'));

            $footer = $dated ? '/^-- Dump completed on: \S.* [+-][0-9]{4}$/D' : '/^-- Dump completed$/D';
            if ($own_backup && preg_match($footer, $last_line) !== 1) {
                throw new \RuntimeException(_('The backup file is missing its last line, so it may have been cut short.'));
            }

        } finally {

            if (is_resource($fp)) fclose($fp);

        }
    }

    // $text without its ordinary comments: /* */ blocks (MariaDB runs the /*! */ and /*M! */ kinds, so those stay), and
    // lines starting with # or --
    protected static function withoutComments($text)
    {
        $lines = array();
        foreach (explode("\n", $text) as $line) {
            $trimmed = ltrim($line);
            if (strpos($trimmed, '#') !== 0 && strpos($trimmed, '--') !== 0) $lines[] = $line;
        }
        $text = implode("\n", $lines);

        $kept = '';
        $offset = 0;

        while (($open = strpos($text, '/*', $offset)) !== false && ($close = strpos($text, '*/', $open + 2)) !== false) {

            $kept .= substr($text, $offset, $open - $offset);
            $comment = substr($text, $open, $close + 2 - $open);
            if (strpos($comment, '/*!') === 0 || strpos($comment, '/*M!') === 0) $kept .= $comment;
            $offset = $close + 2;

        }

        return $kept . substr($text, $offset);
    }

} //@formatter:on
