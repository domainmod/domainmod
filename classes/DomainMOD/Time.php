<?php
/**
 * /classes/DomainMOD/Time.php
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

class Time
{

    public function stamp()
    {
        return gmdate('Y-m-d H:i:s', mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y")));
    }

    public function timeLong()
    {
        return gmdate('l, F jS', mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y")));
    }

    public function timeBasic()
    {
        return gmdate("Y-m-d", mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y")));
    }

    public function timeBasicPlusDays($days)
    {
        return gmdate("Y-m-d", mktime(date("H"), date("i"), date("s"), date("m"), date("d") + $days, date("Y")));
    }

    public function timeBasicPlusYears($years)
    {
        return gmdate("Y-m-d", mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y") + $years));
    }

    public function toUserTimezone($input_time, $format = 'Y-m-d H:i:s')
    {
        $date = new \DateTime($input_time, new \DateTimeZone('UTC'));
        $date->setTimezone(new \DateTimeZone($_SESSION['s_default_timezone']));
        return $date->format($format);
    }

    public function toUtcTimezone($input_time, $format = 'Y-m-d H:i:s')
    {
        $date = new \DateTime($input_time, new \DateTimeZone($_SESSION['s_default_timezone']));
        $date->setTimezone(new \DateTimeZone('UTC'));
        return $date->format($format);
    }

} //@formatter:on
