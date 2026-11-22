<?php
/**
 * /tests/Unit/DateTest.php
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
namespace DomainMOD\Tests\Unit;

use DomainMOD\Date;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DateTest extends TestCase
{
    public static function dates()
    {
        return array(
            'a real date' => array('2026-11-15', true),
            'a leap day' => array('2028-02-29', true),
            'a leap day in a common year' => array('2026-02-29', false),
            'month 13' => array('2026-13-01', false),
            'day 32' => array('2026-01-32', false),
            'day 0' => array('2026-01-00', false),
            'no padding' => array('2026-1-5', false),
            'slashes' => array('2026/01/05', false),
            'with a time' => array('2026-01-05 00:00:00', false),
            'a placeholder' => array('YYYY-MM-DD', false),
            'empty' => array('', false),
        );
    }

    #[DataProvider('dates')]
    public function testCheckDateFormatAcceptsRealDatesOnly($date, $expected)
    {
        $this->assertSame($expected, (new Date())->checkDateFormat($date));
    }

    public function testSplitAndCheckRangeReturnsBothDates()
    {
        $this->assertSame(array('2026-01-01', '2026-12-31'), (new Date())->splitAndCheckRange('2026-01-01 - 2026-12-31'));
    }

    public function testSplitAndCheckRangeReadsOneDateAsAOneDayRange()
    {
        $this->assertSame(array('2026-01-01', '2026-01-01'), (new Date())->splitAndCheckRange('2026-01-01'));
    }

    public static function badRanges()
    {
        return array(
            'empty' => array(''),
            'null' => array(null),
            'the placeholder' => array('YYYY-MM-DD - YYYY-MM-DD'),
            'a bad start' => array('2026-02-30 - 2026-12-31'),
            'a bad end' => array('2026-01-01 - 2026-12-32'),
            'a trailing backslash' => array('2026-01-01 - 2026-12-3\\'),
            'a quote in the end' => array("2026-01-01 - 2026-12-3'"),
            'SQL after the start' => array("2026-01-01' OR '1'='1"),
        );
    }

    #[DataProvider('badRanges')]
    public function testSplitAndCheckRangeGivesTwoEmptyStringsForABadRange($range)
    {
        $this->assertSame(array('', ''), (new Date())->splitAndCheckRange($range));
    }

    public static function monthSteps()
    {
        return array(
            'day 31, January to February' => array('2026-01-31', 1, 31, '2026-02-28'),
            'day 31, January to February in a leap year' => array('2028-01-31', 1, 31, '2028-02-29'),
            'day 31, February back to March' => array('2026-02-28', 1, 31, '2026-03-31'),
            'day 30, January to February' => array('2026-01-30', 1, 30, '2026-02-28'),
            'day 30, February back to March' => array('2026-02-28', 1, 30, '2026-03-30'),
            'day 29, January to February' => array('2026-01-29', 1, 29, '2026-02-28'),
            'day 29 in a leap year' => array('2028-01-29', 1, 29, '2028-02-29'),
            'day 28 stays' => array('2026-01-28', 1, 28, '2026-02-28'),
            'day 31 into a 30-day month' => array('2026-03-31', 1, 31, '2026-04-30'),
            'day 31 back from a 30-day month' => array('2026-04-30', 1, 31, '2026-05-31'),
            'a leap day plus 12 months' => array('2028-02-29', 12, 29, '2029-02-28'),
            'then into the next leap year' => array('2029-02-28', 36, 29, '2032-02-29'),
            '24 months' => array('2026-06-15', 24, 15, '2028-06-15'),
            '36 months' => array('2026-06-15', 36, 15, '2029-06-15'),
            'across a year end' => array('2026-11-30', 3, 30, '2027-02-28'),
            'a different day from the date' => array('2026-07-01', 1, 31, '2026-08-31'),
            'zero months moves to the day' => array('2026-02-28', 0, 31, '2026-02-28'),
            'the last valid month' => array('9999-11-30', 1, 31, '9999-12-31'),
            'past 9999-12-31' => array('9999-12-31', 1, 31, ''),
            'not a date' => array('2026-02-30', 1, 30, ''),
            'empty' => array('', 1, 1, ''),
            'day 0' => array('2026-01-01', 1, 0, ''),
            'day 32' => array('2026-01-01', 1, 32, ''),
            'negative months' => array('2026-01-01', -1, 1, ''),
        );
    }

    #[DataProvider('monthSteps')]
    public function testAddMonthsLandsOnTheRenewalDay($date, $months, $day, $expected)
    {
        $this->assertSame($expected, (new Date())->addMonths($date, $months, $day));
    }

    public function testAddMonthsInOneStepMatchesMonthlySteps()
    {
        $date = new Date();
        $stepped = '2026-01-31';
        for ($i = 1; $i <= 30; $i++) {
            $stepped = $date->addMonths($stepped, 1, 31);
            $this->assertSame($stepped, $date->addMonths('2026-01-31', $i, 31));
        }
    }

} //@formatter:on
