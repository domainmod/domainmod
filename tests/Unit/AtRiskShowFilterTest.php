<?php
/**
 * /tests/Unit/AtRiskShowFilterTest.php
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

use PHPUnit\Framework\TestCase;

/*
 * The three At-Risk reports read their list filter (?show=) only when it's a string, so a crafted link such as
 * ?show[]=x shows All, as an unknown filter does, instead of raising an "Array to string conversion" warning. No page
 * casts a query string value straight to a string.
 */
class AtRiskShowFilterTest extends TestCase
{
    const REPORTS = array('reporting/domains/at-risk.php', 'reporting/hosting/at-risk.php', 'reporting/ssl/at-risk.php');

    // Each report's filter line, run as the page runs it: a string is kept, and an array or no value gives All, with no
    // warning
    public function testEachReportReadsTheFilterOnlyAsAString()
    {
        foreach (self::REPORTS as $report) {
            $line = self::showLine($report);
            $this->assertSame('overdue', self::showFor($line, array('show' => 'overdue')), $report);
            $this->assertSame('all', self::showFor($line, array('show' => array('x'))), $report);
            $this->assertSame('all', self::showFor($line, array('show' => array('overdue' => 'overdue'))), $report);
            $this->assertSame('all', self::showFor($line, array()), $report);
        }
    }

    // No page casts a $_GET value to a string, which warns for an array
    public function testNoPageCastsAQueryValueToAString()
    {
        $root = dirname(__DIR__, 2);
        $found = array();
        $skip = array('.data', '.git', 'locales', 'node_modules', 'temp', 'tests', 'theme', 'vendor');
        // Local scratch files for trying out classes by hand, which git ignores (as in SecurityPageTest)
        $skip_files = array('/modules/test.php', '/test.php');
        $files = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            function ($file, $key, $iterator) use ($skip) {
                return !($iterator->hasChildren() && in_array($file->getFilename(), $skip, true));
            }));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') continue;
            $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root)));
            if (in_array($path, $skip_files, true)) continue;
            if (preg_match('/\(string\)\s*\(?\s*\$_GET\[/', file_get_contents($file->getPathname()))) $found[] = $path;
        }
        sort($found);
        $this->assertSame(array(), $found);
    }

    // The report's first "$show = ..." line
    private static function showLine($report)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $report);
        if (!preg_match('/^\$show = .*;$/m', $source, $match)) self::fail($report . ' has no $show line');
        return $match[0];
    }

    // $show after running $line with $get as the query string, failing on any warning or notice
    private static function showFor($line, $get)
    {
        $saved = $_GET;
        $_GET = $get;
        set_error_handler(function ($severity, $message) use ($line) {
            throw new \ErrorException($message . ' in: ' . $line, 0, $severity);
        });
        try {
            $show = null;
            eval($line);
            return $show;
        } finally {
            restore_error_handler();
            $_GET = $saved;
        }
    }

} //@formatter:on
