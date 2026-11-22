<?php
/**
 * /classes/DomainMOD/Date.php
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

    // Returns the start and end dates of a date range picker value, or two empty strings unless both are real dates.
    public function splitAndCheckRange($daterange)
    {
        $start_date = substr((string) $daterange, 0, 10);
        $end_date = substr((string) $daterange, -10, 10);

        if (!$this->checkDateFormat($start_date) || !$this->checkDateFormat($end_date)) {

            return array('', '');

        }

        return array($start_date, $end_date);
    }

    /*
     * A date (YYYY-MM-DD) moved forward by whole calendar months, landing on $day (1 to 31) or on the target month's
     * last day when the month is shorter, or '' if the date or day isn't valid or the result is past 9999-12-31. Keeping
     * the renewal day separately is what stops a monthly renewal drifting: with day 31, January 31 becomes February 28
     * (or 29), then March 31, and with day 30, January 30 becomes February 28, then March 30. PHP's own "+1 month" isn't
     * used, since it turns January 31 into a date in March.
     */
    public function addMonths($date, $months, $day)
    {
        if (!$this->checkDateFormat((string) $date)) return '';

        $day = (int) $day;
        $months = (int) $months;
        if ($day < 1 || $day > 31 || $months < 0) return '';

        list($year, $month) = array_map('intval', explode('-', $date));

        $index = $year * 12 + ($month - 1) + $months;
        $new_year = intdiv($index, 12);
        if ($new_year > 9999) return '';

        $new_month = $index % 12 + 1;
        $new_day = min($day, $this->daysInMonth($new_year, $new_month));

        return sprintf('%04d-%02d-%02d', $new_year, $new_month, $new_day);
    }

    // The number of days in a month (PHP's calendar extension isn't always installed).
    public function daysInMonth($year, $month)
    {
        return (int) (new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month)))->format('t');
    }

} //@formatter:on
