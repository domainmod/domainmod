<?php
/**
 * /tests/Unit/TempFolderTest.php
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
 * The RDAP, WHOIS, and conversion rate caches (and their failed lookup markers) are saved in temp/ (DIR_TEMP). From
 * 2023 until 4.27.0, releases had no temp/ folder, so on a new install nothing could be cached, and a failed lookup was
 * tried again on every request. temp/ ships again with two files. Its .htaccess stops Apache serving anything from it,
 * when Apache reads .htaccess files, and its index.php is the same as in the other folders that aren't pages, so its
 * file list can't be browsed where the web server uses index.php as the folder's index. The files saved in it are kept
 * out of git.
 */
class TempFolderTest extends TestCase
{
    public function testTheTempFolderIsTheOneTheAppUses()
    {
        $init = file_get_contents(DIR_INC . '/init.inc.php');
        $this->assertNotFalse($init);
        $this->assertSame(1, preg_match_all('/^const DIR_TEMP = DIR_ROOT \. \'\/temp\';$/m', $init));
    }

    public function testTempShipsWithTheSameIndexFileAsTheOtherFolders()
    {
        $index = @file_get_contents(DIR_ROOT . '/temp/index.php');
        $this->assertIsString($index);

        $classes_index = file_get_contents(DIR_ROOT . '/classes/index.php');
        $this->assertNotFalse($classes_index);
        $this->assertSame(str_replace(' * /classes/index.php', ' * /temp/index.php', $classes_index), $index);
        $this->assertStringContainsString('header("Location: ../invalid.php");', $index);
    }

    public function testApacheServesNothingFromTemp()
    {
        $htaccess = @file_get_contents(DIR_ROOT . '/temp/.htaccess');
        $this->assertIsString($htaccess);

        // Exactly these directives, so nothing added later (such as Require all granted) can open the folder again,
        // and no line runs on into the next (Apache joins a line ending in a backslash to the next, comments included).
        $this->assertDoesNotMatchRegularExpression('/\\\\\s*$/m', $htaccess);
        $directives = array_values(array_filter(array_map('trim', explode("\n", $htaccess)), function ($line) {
            return $line !== '' && $line[0] !== '#';
        }));
        $this->assertSame(array(
            '<IfModule mod_authz_core.c>',
            'Require all denied',
            '</IfModule>',
            '<IfModule !mod_authz_core.c>',
            'Order allow,deny',
            'Deny from all',
            '</IfModule>',
        ), $directives);
    }

    public function testTempIsInTheReleases()
    {
        $attributes = file_get_contents(DIR_ROOT . '/.gitattributes');
        $this->assertNotFalse($attributes);
        $attributes = preg_replace('/^\xEF\xBB\xBF/', '', $attributes); // Git skips a byte order mark.

        // Every path left out of releases is named in full, so a check can't miss one of Git's wildcard rules. A rule
        // with a wildcard or an escape fails until this test is updated. A full path leaves out that file or folder
        // (and everything in it), and a name with no slash in it is matched in any folder. Git skips comments and
        // patterns that end in a slash.
        foreach (explode("\n", $attributes) as $line) {
            $fields = preg_split('/\s+/', trim($line));
            if ($fields[0] === '' || $fields[0][0] === '#' || substr($fields[0], -1) === '/') continue;
            // A macro could set export-ignore under another name, so one fails until this test is updated.
            $this->assertStringStartsNotWith('[attr]', $fields[0], "Check that temp/ is still in the releases with: $line");
            if (!preg_grep('/^[-!]?export-ignore$/', array_slice($fields, 1))) continue;
            $this->assertDoesNotMatchRegularExpression('/[*?\[\\\\"]/', $fields[0], "Name the path in full in: $line");

            $pattern = ltrim($fields[0], '/');
            $anywhere = strpos($fields[0], '/') === false;
            foreach (array('temp', 'temp/.htaccess', 'temp/index.php') as $path) {
                $left_out = $path === $pattern || strpos($path, $pattern . '/') === 0 ||
                    ($anywhere && in_array($pattern, explode('/', $path), true));
                $this->assertFalse($left_out, "$path is left out of releases by: $line");
            }
        }
    }

    public function testOnlyTheShippedFilesAreTracked()
    {
        $ignore = file_get_contents(DIR_ROOT . '/.gitignore');
        $this->assertNotFalse($ignore);
        $ignore = preg_replace('/^\xEF\xBB\xBF/', '', $ignore); // Git skips a byte order mark.
        // Git ignores spaces at the end of a rule, but not at the start or other characters such as tabs, so only
        // spaces at the end are trimmed.
        $lines = array_map(function ($line) { return rtrim($line, ' '); }, explode("\n", $ignore));

        // The last rule that matches a file wins, so the two files are let back in after everything in temp/ is
        // ignored, and .htaccess after the rule that ignores every .htaccess file.
        $all = array_search('/temp/*', $lines, true);
        $this->assertIsInt($all);
        foreach (array('!/temp/.htaccess', '!/temp/index.php') as $kept) {
            $this->assertGreaterThan($all, array_search($kept, $lines, true));
        }
        $this->assertGreaterThan(array_search('.htaccess', $lines, true), array_search('!/temp/.htaccess', $lines, true));

        // As with .gitattributes, each of the three rules is there once, no other rule may use a wildcard or an
        // escape, and no other rule after /temp/* may name temp, .htaccess, or index.php, or let a file back in, so a
        // check can't miss a rule that ignores the two files or the folder, or lets a saved file in.
        $temp_rules = array('/temp/*', '!/temp/.htaccess', '!/temp/index.php');
        foreach ($temp_rules as $rule) {
            $this->assertCount(1, array_keys($lines, $rule, true), "$rule should be in .gitignore once");
        }
        foreach ($lines as $number => $line) {
            if ($line === '' || $line[0] === '#' || in_array($line, $temp_rules, true)) continue;
            $this->assertDoesNotMatchRegularExpression('/[*?\[\\\\]/', $line, "Name the path in full in: $line");
            if ($number > $all) {
                $this->assertDoesNotMatchRegularExpression('/^!|temp|\.htaccess|index\.php/', $line,
                    "Check that temp/ still ships, with only its two files, with: $line");
            }
        }

        // Ignoring the folder itself would stop the two files being tracked.
        foreach (array('/temp', '/temp/', 'temp', 'temp/') as $folder) {
            $this->assertNotContains($folder, $lines);
        }
    }

} //@formatter:on
