<?php
/**
 * /classes/DomainMOD/Error.php
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

class Error
{

    public function getLevel($level)
    {
        if ($level == '1') {
            return E_USER_ERROR;
        } elseif ($level == '2') {
            return E_USER_WARNING;
        } elseif ($level == '3') {
            return E_USER_NOTICE;
        } else {
            return E_USER_ERROR;
        }
    }

    public function outputSqlError($dbcon, $level, $message)
    {
        return trigger_error(htmlentities('[' . strtoupper($message) . ']: ' . mysqli_error($dbcon)), $this->getLevel($level));
    }

} //@formatter:on
