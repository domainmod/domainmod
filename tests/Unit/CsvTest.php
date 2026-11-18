<?php
/**
 * /tests/Unit/CsvTest.php
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

use DomainMOD\Csv;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * Csv::checkColumns(), the CSV Importer's check that every row has the template's columns. The files are written here
 * rather than kept as fixtures, so git's line ending rules can't change them.
 */
class CsvTest extends TestCase
{
    private $files = array();

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) unlink($file);
        }
    }

    private function csvFile($contents)
    {
        $file = tempnam(sys_get_temp_dir(), 'domainmod-csv-');
        file_put_contents($file, $contents);
        $this->files[] = $file;
        return $file;
    }

    private static function row($columns = Csv::COLUMNS, $value = 'x')
    {
        return implode(',', array_fill(0, $columns, $value));
    }

    private function checked($contents)
    {
        $csv = Instance::withoutConstructor(Csv::class);
        $valid = $csv->checkColumns($this->csvFile($contents));
        return array($valid, $csv->invalid_rows, $csv->invalid_row_count);
    }

    public function testTheTemplateHas64Columns()
    {
        $this->assertSame(64, Csv::COLUMNS);
    }

    public static function lineEndings()
    {
        return array('LF' => array("\n"), 'CRLF' => array("\r\n"));
    }

    #[DataProvider('lineEndings')]
    public function testAFileWithEveryRowRightPasses($eol)
    {
        $this->assertSame(array(true, array(), 0), $this->checked(self::row() . $eol . self::row() . $eol));
    }

    #[DataProvider('lineEndings')]
    public function testBlankLinesAreSkippedButStillCounted($eol)
    {
        // Rows are counted like a spreadsheet's, so the short row after two blank lines is row 4
        $contents = self::row() . $eol . $eol . $eol . 'a,b' . $eol . self::row() . $eol;

        $this->assertSame(array(false, array(4), 1), $this->checked($contents));
    }

    #[DataProvider('lineEndings')]
    public function testAShortAndALongRowAreBothBad($eol)
    {
        $contents = self::row() . $eol . self::row(63) . $eol . self::row(65) . $eol . self::row();

        $this->assertSame(array(false, array(2, 3), 2), $this->checked($contents));
    }

    public function testCountsEveryBadRowButKeepsTheFirstTen()
    {
        $lines = array(self::row());
        for ($i = 0; $i < 12; $i++) {
            $lines[] = 'short,row';
            $lines[] = self::row();
        }

        $this->assertSame(array(false, array(2, 4, 6, 8, 10, 12, 14, 16, 18, 20), 12),
            $this->checked(implode("\n", $lines) . "\n"));
    }

    public function testACarriageReturnOnlyFileIsOneLongRow()
    {
        // PHP splits lines on a lone carriage return only with the auto_detect_line_endings setting, which is off by
        // default (and deprecated since PHP 8.1), so a file saved with old Mac line endings reads as a single row with
        // too many columns, and nothing is imported
        if (ini_get('auto_detect_line_endings')) {
            $this->markTestSkipped('This PHP has auto_detect_line_endings on, so lone carriage returns split lines');
        }
        $contents = self::row() . "\r" . self::row() . "\r" . self::row() . "\r";

        $this->assertSame(array(false, array(1), 1), $this->checked($contents));
    }

    public function testQuotedValuesKeepTheirCommasAndLineBreaks()
    {
        $values = array_fill(0, Csv::COLUMNS, 'x');
        $values[0] = '"a, b"';
        $values[12] = "\"first line\nsecond line\"";
        $values[13] = '"say ""hi"""';

        $this->assertSame(array(true, array(), 0), $this->checked(implode(',', $values) . "\n"));
    }

    public function testABackslashBeforeAQuoteDoesNotSwallowTheNextRow()
    {
        // With the empty escape character, a value ending in a backslash ends where its quote does (0ee0eb86)
        $values = array_fill(0, Csv::COLUMNS, 'x');
        $values[12] = '"C:\\folder\\"';
        $row = implode(',', $values);

        $this->assertSame(array(true, array(), 0), $this->checked($row . "\n" . self::row() . "\n"));
        // and the next row is still read and checked: a short one is reported as row 2
        $this->assertSame(array(false, array(2), 1), $this->checked($row . "\n" . self::row(3) . "\n"));
    }

    public function testRunningItAgainStartsFromNothing()
    {
        $csv = Instance::withoutConstructor(Csv::class);

        $this->assertFalse($csv->checkColumns($this->csvFile("a,b\n")));
        $this->assertTrue($csv->checkColumns($this->csvFile(self::row() . "\n")));
        $this->assertSame(array(), $csv->invalid_rows);
        $this->assertSame(0, $csv->invalid_row_count);
    }

} //@formatter:on
