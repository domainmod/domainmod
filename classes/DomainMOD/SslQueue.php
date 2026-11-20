<?php
/**
 * /classes/DomainMOD/SslQueue.php
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
 * The SSL Queue: hostnames whose certificates are read in the background (by the ssl-queue scheduled task) and added
 * as SSL certificates. Its tables come with the 4.27.0 database update, so nothing here uses them before then.
 */
class SslQueue
{
    public $deeb;
    public $log;
    public $system;

    // The database version that adds the SSL Queue's tables
    const AVAILABLE_FROM = '4.27.0';

    // Test-only hook, null outside tests: a callable($event) that process() calls at its start ('start') and when it
    // returns early before the database update ('unavailable')
    public static $test_trace = null;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->log = new Log('class.sslqueue');
        $this->system = new System();
    }

    // Whether the database has been updated to a version with the SSL Queue's tables
    public function isAvailable()
    {
        return version_compare((string) $this->system->getDbVersion(), self::AVAILABLE_FROM, '>=');
    }

    // The ssl-queue task's run. Until the database update it does nothing, so cron and Run Now are safe either way.
    public function process()
    {
        $this->trace('start');

        if (!$this->isAvailable()) {

            $this->trace('unavailable');
            return;

        }
    }

    protected function trace($event)
    {
        if (self::$test_trace !== null) call_user_func(self::$test_trace, $event);
    }

} //@formatter:on
