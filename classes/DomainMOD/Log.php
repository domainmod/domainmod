<?php
/**
 * /classes/DomainMOD/Log.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2017 Greg Chetcuti <greg@chetcuti.com>
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

class Log
{
    public $db;

    public function __construct($db)
    {
        $this->db = $db;
        $this->user_id = $_SESSION['s_user_id'];
        $this->url = $_SERVER['REQUEST_URI'];
        $this->error = new Error();
        $this->time = new Time();
    }

    public function error($error_message)
    {
        $sql = "INSERT INTO `log_error`
                (user_id, `error`, `url`, insert_time)
                 VALUES
                ('" . $this->user_id . "', '" . $error_message . "', '" . $this->url . "', '" .
                    $this->time->stamp() . "')";
        $result = mysqli_query($this->db, $sql) or $this->error->outputSqlError($this->db, '1', 'ERROR');
        return;
    }

} //@formatter:on
