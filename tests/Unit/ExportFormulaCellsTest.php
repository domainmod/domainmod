<?php
/**
 * /tests/Unit/ExportFormulaCellsTest.php
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

/*
 * Exports wrote names, notes, and other text as is, so a cell starting with =, +, -, @, a tab, or a carriage return
 * could run as a formula when the file was opened in a spreadsheet. Export::writeRow(), which every export writes its
 * rows with, now gives such text an apostrophe in front (Export::protectCell()), and leaves numbers and amounts as
 * numbers.
 */
class ExportFormulaCellsTest extends TestCase
{
    public static function protectedText()
    {
        return array(
            'a formula' => array('=SUM(A1:A9)'),
            'a plus formula' => array('+SUM(A1)'),
            'a minus formula' => array('-2+3'),
            'a command' => array("=cmd|' /C calc'!A0"),
            'an at sign' => array('@SUM(A1)'),
            'a tab first' => array("\t=1"),
            'a carriage return first' => array("\r=1"),
            'a phone number' => array('+1 604 555 0100'),
            'a sign then a word with a pipe' => array('-1 cmd|x'),
            'a sign then a word with brackets' => array('-1 SUM(A1)'),
            'a sign then two words' => array('-12.50 US D'),
            'an exponent' => array('-1e5'),
            'a line feed first' => array("\n=1"),
            'spaces and line feeds first' => array("  \n\n@SUM(A1)"),
            'a full-width equals sign' => array('＝SUM(A1)'),
            'a full-width plus sign' => array('＋SUM(A1)'),
            'a full-width minus sign' => array('－2＋3'),
            'a full-width at sign' => array('＠SUM(A1)'),
            'a number after a line feed' => array("\n-12.50"),
            'a comma for decimals' => array('-12,50'),
        );
    }

    #[DataProvider('protectedText')]
    public function testTextThatCouldRunGetsAnApostrophe($value)
    {
        $this->assertSame("'" . $value, Export::protectCell($value));
    }

    public static function untouchedValues()
    {
        return array(
            'plain text' => array('example.com'),
            'a trigger later on' => array('a=b+c'),
            'a space then text' => array(' a=b'),
            'a line feed then text' => array("\nRenewed early"),
            'only spaces and line feeds' => array(" \n "),
            'already protected' => array("'=1"),
            'empty' => array(''),
            'a negative amount' => array('-12.50'),
            'a negative amount with thousands' => array('-1,234.50'),
            'a plus number' => array('+3'),
            'a negative amount with a symbol after it' => array('-12.50 USD'),
            'a negative amount with a symbol and no space' => array('-12.50€'),
            'a right-to-left symbol after it' => array("-5.00 \u{062F}.\u{0625}"),
            'a symbol with a slash after it' => array('-12.50 S/.'),
            'a symbol before it' => array('$-12.50'),
            'a lone dash' => array('-'),
            'an int' => array(-5),
            'a float' => array(-12.5),
            'null' => array(null),
        );
    }

    #[DataProvider('untouchedValues')]
    public function testNumbersAmountsAndOtherTextAreLeftAsTheyAre($value)
    {
        $this->assertSame($value, Export::protectCell($value));
    }

    public function testWriteRowProtectsEveryTextCellAndNoNumber()
    {
        $file = fopen('php://memory', 'w+');
        (new Export())->writeRow($file, array('=HYPERLINK("http://example.com")', '-12.50', -12.5, 3, '@note', 'name'));
        rewind($file);
        $bytes = stream_get_contents($file);
        fclose($file);

        $this->assertSame('"\'=HYPERLINK(""http://example.com"")",-12.50,-12.5,3,\'@note,name' . "\n", $bytes);
    }

    public function testAnAlreadyProtectedValueIsNotProtectedTwice()
    {
        $export = new Export();
        $this->assertSame("'=1", Export::protectCell($export->protectFormula('=1')));
    }

    public function testEveryExportWritesThroughWriteRow()
    {
        // Only Export writes CSV, so no export can write a row without the rule
        $root = dirname(__DIR__, 2);
        $found = array();
        $skip = array('.data', '.git', 'docs', 'node_modules', 'temp', 'tests', 'theme', 'vendor');
        // Local scratch files for trying out classes by hand, which git ignores (as in SecurityPageTest)
        $skip_files = array('/modules/test.php', '/test.php');
        $files = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            function ($file, $key, $iterator) use ($skip) {
                return !($iterator->hasChildren() && in_array($file->getFilename(), $skip, true));
            }));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') continue;
            $path = substr($file->getPathname(), strlen($root));
            if (in_array($path, $skip_files, true)) continue;
            if (strpos((string) file_get_contents($file->getPathname()), 'fputcsv(') !== false) $found[] = $path;
        }
        $this->assertSame(array('/classes/DomainMOD/Export.php'), $found);

        // And within Export, only the methods that write a row: writeRow() (each cell through protectCell()), the blank
        // row, and the fixed line that marks an unfinished file
        $source = file_get_contents($root . '/classes/DomainMOD/Export.php');
        preg_match_all('/function (\w+)\((?:(?!\n    (?:public|protected|private) ).)*?fputcsv\(/s', $source, $matches);
        $this->assertSame(array('writeRow', 'writeBlankRow', 'writeIncompleteRow'), $matches[1]);
    }
}
