<?php
/**
 * /_includes/classes/Timestamp.class.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (C) 2010-2015 Greg Chetcuti <greg@chetcuti.com>
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
?>
<?php
namespace DomainMOD;

class Timestamp
{

    public function time() {

        $timestamp = date("Y-m-d H:i:s", mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y")));

        return $timestamp;

    }

    public function timeLong() {

        $timestamp = date("l, F jS", mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y")));

        return $timestamp;

    }

    public function timeBasic() {

        $timestamp = date("Y-m-d", mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y")));

        return $timestamp;

    }

    public function timeBasicPlusDays($days) {

        $timestamp = date("Y-m-d", mktime(date("H"), date("i"), date("s"), date("m"), date("d") + $days, date("Y")));

        return $timestamp;

    }

    public function timeBasicPlusYears($years) {

        $timestamp = date("Y-m-d", mktime(date("H"), date("i"), date("s"), date("m"), date("d"), date("Y") + $years));

        return $timestamp;

    }

}
