<?php
/**
 * /classes/DomainMOD/Upgrade.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2022 Greg Chetcuti <greg@chetcuti.com>
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

class Upgrade
{
    public $deeb;
    public $log;
    public $time;

    public function __construct()
    {
        $this->deeb = Database::getInstance();
        $this->log = new Log('class.upgrade');
        $this->time = new Time();
    }

    public function database($new_version)
    {
        $pdo = $this->deeb->cnxx;
        $timestamp = $this->time->stamp();
        $pdo->query("
            UPDATE settings
            SET db_version = '" . $new_version . "',
                update_time = '" . $timestamp . "'");
    }

    public function logFailedUpgrade($old_version, $new_version, $error)
    {
        $log_message = 'Upgrade failed';
        $log_extra = array('Current Version' => $old_version, 'New Version' => $new_version, 'Error' => $error);
        $this->log->critical($log_message, $log_extra);
    }

} //@formatter:on
