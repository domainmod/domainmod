<?php
namespace GJClasses;

class System
{
    public $log;

    public function __construct()
    {
        $this->log = new Log('class.system');
    }

}
