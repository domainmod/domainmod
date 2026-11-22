<?php
/**
 * /tests/Unit/ConversionFailedLookupTest.php
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

use DomainMOD\Conversion;
use DomainMOD\Log;
use DomainMOD\Tests\Support\FakeDatabase;
use DomainMOD\Time;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;


/*
 * When the rate service couldn't be reached, Conversion::updateRates() saved the failed lookup's result (false, null,
 * or 0) as the rate, so the Task Scheduler's Update Conversion Rates left 0.0000 for those currencies and silently
 * zeroed every fee in them. A rate that isn't a positive number the database can store is now logged and skipped: an
 * existing rate is kept, a missing one isn't added (Conversion::ensureRates() adds it once a lookup works), and the
 * message says some rates weren't changed.
 */
class ConversionFailedLookupTest extends TestCase
{
    public static function failedLookups()
    {
        return array(
            'unreachable' => array(false),
            'no rate in the answer' => array(null),
            'unknown service' => array(0.0),
            'empty text' => array(''),
            'negative' => array(-1.5),
            'infinite' => array(INF),
            'too big for a float' => array('1e400'),
            'too big to store' => array(1e9),
            'not a number' => array('unavailable'),
        );
    }

    #[DataProvider('failedLookups')]
    public function testAFailedLookupKeepsTheExistingRate($failed_rate)
    {
        // The user already has a rate for each currency (one fake answers every query on the table).
        $pdo = self::database(array('currency_conversions' => array((object) array('id' => 9))));
        $conversion = $this->conversion($pdo, array('CAD' => $failed_rate, 'EUR' => 0.92));
        $conversion->log->expects($this->once())->method('warning')
            ->with('Unable to get a conversion rate for a user currency, so it wasn\'t changed',
                array('User ID' => 5, 'Currency' => 'CAD', 'Default Currency' => 'USD'));

        $this->assertSame("Some conversion rates couldn't be fetched, so they weren't changed.<BR>",
            $conversion->updateRates('USD', 5));
        // So the callers show the message as a warning, not a success (found at 4.27.0's [K])
        $this->assertSame(1, $conversion->skipped);

        // Only the default currency (1) and the rate that worked are saved.
        $this->assertCount(2, $pdo->queriesWith('UPDATE currency_conversions'));
        $this->assertSame(array(), $pdo->queriesWith('INSERT INTO currency_conversions'));
        $this->assertSame(array('1', '0.92'), $pdo->savedRates);
    }

    #[DataProvider('failedLookups')]
    public function testAFailedLookupDoesNotAddAMissingRate($failed_rate)
    {
        // The user has no rates yet.
        $pdo = self::database(array('currency_conversions' => array()));
        $conversion = $this->conversion($pdo, array('CAD' => $failed_rate, 'EUR' => 0.92));
        $conversion->log->expects($this->once())->method('warning');

        $this->assertSame("Some conversion rates couldn't be fetched, so they weren't changed.<BR>",
            $conversion->updateRates('USD', 5));

        $this->assertCount(2, $pdo->queriesWith('INSERT INTO currency_conversions'));
        $this->assertSame(array(), $pdo->queriesWith('UPDATE currency_conversions'));
        $this->assertSame(array('1', '0.92'), $pdo->savedRates);
    }

    public function testWorkingLookupsAreAllSaved()
    {
        $pdo = self::database(array('currency_conversions' => array((object) array('id' => 9))));
        $conversion = $this->conversion($pdo, array('CAD' => '1.38', 'EUR' => 0.92));
        $conversion->log->expects($this->never())->method('warning');

        $this->assertSame('Conversion Rates updated<BR>', $conversion->updateRates('USD', 5));
        $this->assertSame(0, $conversion->skipped);

        $this->assertSame(array('1', '1.38', '0.92'), $pdo->savedRates);
    }

    #[DataProvider('failedLookups')]
    public function testAFailedLookupIsNotAValidRate($failed_rate)
    {
        $this->assertFalse(Conversion::validRate($failed_rate));
    }

    public function testWorkingRatesAreValid()
    {
        $this->assertTrue(Conversion::validRate(1));
        $this->assertTrue(Conversion::validRate(0.000123));
        $this->assertTrue(Conversion::validRate('1.38'));
        $this->assertTrue(Conversion::validRate(Conversion::RATE_MAX));
    }

    public function testALookupThatThrowsIsAFailedLookup()
    {
        // The vendor lookup for this service lowercases the currency codes first, so an array throws a TypeError there
        // before any request is made, standing in for a TypeError from an answer in an unexpected shape.
        $conversion = $this->getMockBuilder(Conversion::class)->disableOriginalConstructor()->onlyMethods(array())->getMock();
        $conversion->log = $this->createMock(Log::class);
        $conversion->log->expects($this->once())->method('warning')
            ->with('Conversion rate lookup failed', $this->arrayHasKey('Error'));

        $session = $_SESSION ?? null;
        $_SESSION['s_system_currency_converter'] = 'fcra';
        try {
            $this->assertFalse($conversion->getConvRate(array('CAD'), 'USD'));
        } finally {
            if ($session === null) unset($_SESSION); else $_SESSION = $session;
        }
    }

    // A Conversion whose fee currencies are USD, CAD, and EUR, and whose rate lookups give $rates.
    private function conversion($pdo, array $rates)
    {
        $conversion = $this->getMockBuilder(Conversion::class)->disableOriginalConstructor()
            ->onlyMethods(array('getActiveCurrencies', 'getConvRate'))->getMock();
        $conversion->method('getActiveCurrencies')->willReturn(array(
            (object) array('id' => 1, 'currency' => 'USD'),
            (object) array('id' => 2, 'currency' => 'CAD'),
            (object) array('id' => 3, 'currency' => 'EUR'),
        ));
        $conversion->method('getConvRate')->willReturnCallback(function ($from_currency) use ($rates) {
            return $rates[$from_currency];
        });
        $conversion->deeb = (object) array('cnxx' => $pdo);
        $conversion->log = $this->createMock(Log::class);
        $conversion->time = $this->createMock(Time::class);
        return $conversion;
    }

    // A FakeDatabase that also takes bindParam(), as updateRates() uses, and keeps every rate it's asked to save.
    private static function database(array $answers)
    {
        return new class($answers) extends FakeDatabase {
            public $savedRates = array();

            public function bindParam($name, &$value, $type = null)
            {
                return true;
            }

            public function bindValue($name, $value, $type = null)
            {
                if ($name === 'conversion_rate') $this->savedRates[] = $value;
                return true;
            }
        };
    }

} //@formatter:on
