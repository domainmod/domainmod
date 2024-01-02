<?php
/**
 * /classes/DomainMOD/Date.php
 *
 * This file is part of DomainMOD, an open source domain and internet asset manager.
 * Copyright (c) 2010-2024 Greg Chetcuti <greg@chetcuti.com>
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

class Date
{
    public $time;

    public function __construct()
    {
        $this->time = new Time();
    }

    public function checkDateFormat($input_date)
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $input_date, $output_date)) {

            return checkdate($output_date[2], $output_date[3], $output_date[1]);

        } else {

            return false;

        }
    }

    public function splitAndCheckRange($daterange)
    {
        $start_date = substr($daterange, 0, 10);
        $end_date = substr($daterange, -10, 10);
        return array($start_date, $end_date);
    }

} //@formatter:on
