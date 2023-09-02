<?php

namespace Thamaraiselvam\MysqlImport;

use mysqli;
use Exception;

/**
 * Mysqli class to import sql from a .sql file
*/

class Import
{
    private $db;
    private $filename;
    private $username;
    private $password;
    private $database;
    private $host;
    private $port;

    /**
      * instanciate
      * @param $filename string name of the file to import
      * @param $username string database username
      * @param $password string database password
      * @param $database string database name
      * @param $host string address host localhost or ip address
      * @param $port int port for the host, default is 3306
    */
    public function __construct($filename, $username, $password, $database, $host, $port = 3306)
    {
        //set the varibles to properties
        $this->filename = $filename;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;
        $this->host = $host;
        $this->port = $port;

        //connect to the datase
        $this->connect();

        //open file and import the sql
        $this->openfile();
    }

    /**
     * Connect to the database
    */
    protected function connect()
    {
        $this->db = $this->createconnection();
        if ($this->db->connect_errno) {
            throw new Exception("Failed to connect to MySQL: " . $this->db->connect_error);
        }
    }

    /**
     * run queries
     * @param string $query the query to perform
    */
    protected function query($query)
    {
        if (!$this->db->query($query)) {
            throw new Exception("Error with query: ".$this->db->error."\n");
        }
    }

    /**
     * Open $filename, loop through and import the commands
    */
    protected function openfile()
    {
        try {
            //if file cannot be found throw errror
            if (!file_exists($this->filename)) {
                throw new Exception("Error: File not found.\n");
            }

            // Read in entire file
            $fp = fopen($this->filename, 'r');

            // Temporary variable, used to store current query
            $templine = '';

            // Loop through each line
            while (($line = fgets($fp)) !== false) {
                // Skip it if it's a comment
                if (substr($line, 0, 2) == '--' || $line == '') {
                    continue;
                }

                // Add this line to the current segment
                $templine .= $line;

                // If it has a semicolon at the end, it's the end of the query
                if (substr(trim($line), -1, 1) == ';') {
                    $this->query($templine);

                    // Reset temp variable to empty
                    $templine = '';
                }
            }

            //close the file
            fclose($fp);
        } catch (Exception $e) {
            echo "Error importing: ".$e->getMessage()."\n";
        } finally {
            $this->db->close();
        }
    }

    /**
     * @codeCoverageIgnore
     */
    protected function createconnection()
    {
        return new mysqli($this->host, $this->username, $this->password, $this->database, $this->port);
    }
}
