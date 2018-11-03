<?php
namespace GJClasses;

class Database
{
    public $cnxx;
    private static $instance;

    private function __construct()
    {
        $this->cnxx = new \PDO("mysql:host=" . GJC_DB_HOSTNAME . ";dbname=" . GJC_DB_NAME . ";charset=utf8", GJC_DB_USERNAME, GJC_DB_PASSWORD);
        $this->cnxx->exec("SET NAMES utf8");
        $this->cnxx->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
        $this->cnxx->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_OBJ);
        $this->cnxx->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            $object = __CLASS__;
            self::$instance = new $object;
        }
        return self::$instance;
    }
}
