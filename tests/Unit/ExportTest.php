<?php
/**
 * /tests/Unit/ExportTest.php
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

use DomainMOD\Export;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExportTest extends TestCase
{
    public static function formulaTriggers()
    {
        return array('equals' => array('='), 'plus' => array('+'), 'minus' => array('-'), 'at' => array('@'),
            'tab' => array("\t"), 'carriage return' => array("\r"));
    }

    #[DataProvider('formulaTriggers')]
    public function testProtectFormulaPrefixesEachTrigger($trigger)
    {
        $this->assertSame("'" . $trigger . 'SUM(A1)', (new Export())->protectFormula($trigger . 'SUM(A1)'));
    }

    public static function safeValues()
    {
        return array(
            'plain text' => array('admin', 'admin'),
            'a trigger later on' => array('a=b+c', 'a=b+c'),
            'a space first' => array(' =1', ' =1'),
            'a line feed first' => array("\n=1", "\n=1"),
            'an apostrophe first' => array("'=1", "'=1"),
            'empty' => array('', ''),
            'null' => array(null, ''),
            'a number' => array(42, '42'),
        );
    }

    #[DataProvider('safeValues')]
    public function testProtectFormulaLeavesEverythingElse($value, $expected)
    {
        $this->assertSame($expected, (new Export())->protectFormula($value));
    }

    public function testProtectFormulaAlsoPrefixesANegativeNumber()
    {
        // It's for text anyone could have supplied, where a leading minus is treated like any other formula character
        $this->assertSame("'-5", (new Export())->protectFormula(-5));
    }

    private function written(callable $write)
    {
        $file = fopen('php://memory', 'w+');
        $write(new Export(), $file);
        rewind($file);
        $bytes = stream_get_contents($file);
        fclose($file);
        return $bytes;
    }

    public function testWriteRowGivesStandardCsv()
    {
        $bytes = $this->written(function ($export, $file) {
            $export->writeRow($file, array('plain', 'with "quote"', 'a,b', "line\nbreak", 'back\\"slash', 'ends\\',
                ' padded ', '', null, 5, 1.5));
        });

        // Quotes are doubled, a backslash is an ordinary character, and each row ends with a line feed
        $this->assertSame('plain,"with ""quote""","a,b","line' . "\n" . 'break","back\\""slash",ends\\," padded ",,,5,1.5'
            . "\n", $bytes);
    }

    public function testWriteRowStripsTheRightToLeftIsolationMarks()
    {
        $bytes = $this->written(function ($export, $file) {
            $export->writeRow($file, array("\u{2066}\$1,234.50\u{2069}", "\u{2066}-5.00 \u{062F}.\u{0625}\u{2069}"));
        });

        $this->assertSame('"$1,234.50","-5.00 ' . "\u{062F}.\u{0625}" . '"' . "\n", $bytes);
    }

    public function testWriteBlankRow()
    {
        $this->assertSame("\n", $this->written(function ($export, $file) {
            $export->writeBlankRow($file);
        }));
    }

    public function testARowReadsBackAsWritten()
    {
        $row = array('with "quote"', 'back\\"slash', 'ends\\', "line\r\nbreak", 'a,b');
        $bytes = $this->written(function ($export, $file) use ($row) {
            $export->writeRow($file, $row);
            $export->writeRow($file, array('next', 'row'));
        });

        $file = fopen('php://memory', 'w+');
        fwrite($file, $bytes);
        rewind($file);
        $this->assertSame($row, fgetcsv($file, null, ',', '"', ''));
        $this->assertSame(array('next', 'row'), fgetcsv($file, null, ',', '"', ''));
        fclose($file);
    }

} //@formatter:on
