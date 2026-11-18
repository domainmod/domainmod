<?php
/**
 * /tests/Unit/CurrencyTest.php
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

use DomainMOD\Currency;
use DomainMOD\Export;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class CurrencyTest extends TestCase
{
    private function currency()
    {
        return Instance::withoutConstructor(Currency::class);
    }

    public static function formats()
    {
        // amount, symbol, order (1 puts the symbol after), space (1 adds one), expected
        return array(
            'symbol first, no space' => array(1234.5, '$', '0', '0', '$1,234.50'),
            'symbol first, with a space' => array(1234.5, '$', '0', '1', '$ 1,234.50'),
            'symbol after, no space' => array(1234.5, '€', '1', '0', '1,234.50€'),
            'symbol after, with a space' => array(1234.5, '€', '1', '1', '1,234.50 €'),
            'whole-number settings' => array(1234.5, '$', 1, 1, '1,234.50 $'),
            'no amount' => array(null, '$', '0', '0', '$0.00'),
            'a negative amount' => array(-5, '$', '0', '0', '$-5.00'),
            'rounded half up' => array(0.125, '£', '0', '0', '£0.13'),
            'millions' => array(1234567.891, '¥', '0', '1', '¥ 1,234,567.89'),
            'an Arabic symbol' => array(10, "\u{062F}.\u{0625}", '1', '1', "10.00 \u{062F}.\u{0625}"),
        );
    }

    #[DataProvider('formats')]
    public function testFormatInALeftToRightLanguage($amount, $symbol, $order, $space, $expected)
    {
        $this->assertSame($expected, $this->currency()->format($amount, $symbol, $order, $space));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFormatIsolatesTheAmountInARightToLeftLanguage()
    {
        define('DEFAULT_LANGUAGE', 'ar_SA');

        $formatted = $this->currency()->format(-5, "\u{062F}.\u{0625}", '1', '1');

        $this->assertSame("\u{2066}-5.00 \u{062F}.\u{0625}\u{2069}", $formatted);
        $this->assertSame("\u{2066}\$1,234.50\u{2069}", $this->currency()->format(1234.5, '$', '0', '0'));

        // Export takes the marks out again, so exported files are as before
        $file = fopen('php://memory', 'w+');
        (new Export())->writeRow($file, array($formatted));
        rewind($file);
        $this->assertSame("\"-5.00 \u{062F}.\u{0625}\"\n", stream_get_contents($file));
        fclose($file);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testFormatAddsNoMarksInEnglish()
    {
        define('DEFAULT_LANGUAGE', 'en_US');

        $this->assertSame('$1,234.50', $this->currency()->format(1234.5, '$', '0', '0'));
    }

} //@formatter:on
