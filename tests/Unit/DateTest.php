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

} //@formatter:on
