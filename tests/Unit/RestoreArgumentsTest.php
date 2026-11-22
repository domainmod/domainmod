<?php
/**
 * /tests/Unit/RestoreArgumentsTest.php
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

use DomainMOD\RestoreRecovery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * php restore.php's arguments (RestoreRecovery::cliArguments()). --recover quietly ignored a backup file or --temp-dir
 * given with it, which was confusing on the command line, so --recover and --forget-staged now refuse a backup file,
 * a --temp-dir, or each other.
 */
class RestoreArgumentsTest extends TestCase
{
    public static function arguments()
    {
        return array(
            'restore' => array(array('backup.sql', '--temp-dir=/private'), array('restore', 'backup.sql', '/private')),
            'restore, folder first' => array(array('--temp-dir=/private', 'backup.sql'), array('restore', 'backup.sql', '/private')),
            'recover' => array(array('--recover'), array('recover', '', '')),
            'forget' => array(array('--forget-staged'), array('forget', '', '')),
            'recover with a file' => array(array('--recover', 'backup.sql'), array('alone', '', '')),
            'a file, then recover' => array(array('backup.sql', '--recover'), array('alone', '', '')),
            'recover with a folder' => array(array('--recover', '--temp-dir=/private'), array('alone', '', '')),
            'recover with an empty folder' => array(array('--recover', '--temp-dir='), array('alone', '', '')),
            'recover with both' => array(array('backup.sql', '--temp-dir=/private', '--recover'), array('alone', '', '')),
            'forget with a file' => array(array('--forget-staged', 'backup.sql'), array('alone', '', '')),
            'recover and forget' => array(array('--recover', '--forget-staged'), array('alone', '', '')),
            'recover twice' => array(array('--recover', '--recover'), array('recover', '', '')),
            'nothing' => array(array(), array('usage', '', '')),
            'no folder' => array(array('backup.sql'), array('usage', '', '')),
            'an empty folder' => array(array('backup.sql', '--temp-dir='), array('usage', '', '')),
            'no file' => array(array('--temp-dir=/private'), array('usage', '', '')),
            'an empty argument first' => array(array('', 'backup.sql', '--temp-dir=/private'), array('restore', 'backup.sql', '/private')),
            'recover with an empty argument' => array(array('--recover', ''), array('recover', '', '')),
            'two files' => array(array('a.sql', 'b.sql', '--temp-dir=/private'), array('usage', '', '')),
            'an unknown option' => array(array('--recover', '--force'), array('usage', '', '')),
        );
    }

    #[DataProvider('arguments')]
    public function testArguments(array $arguments, array $expected)
    {
        $this->assertSame($expected, RestoreRecovery::cliArguments($arguments));
    }

    // restore.php says why it refused, then how to run it, and stops with the usage exit code
    public function testRestorePhpRefusesAMix()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/restore.php');
        $this->assertStringContainsString('list($mode, $file, $temp_dir) = DomainMOD\RestoreRecovery::cliArguments(array_slice($argv, 1));', $source);
        $this->assertMatchesRegularExpression("~if \\(\\\$mode === 'alone'\\) \\{\\s+\\\$say\\(_\\('--recover and --forget-staged each run on their own, "
            . "with no backup file or --temp-dir\\.'\\)\\);\\s+\\\$say\\(\\\$usage\\);\\s+exit\\(2\\);~", $source);
        // Both checks come before anything reads or locks temp/
        $ready = strpos($source, 'RestoreLock::restoreReady()');
        foreach (array('alone', 'usage') as $mode) {
            $branch = strpos($source, "if (\$mode === '" . $mode . "') {");
            $this->assertNotFalse($branch, $mode);
            $this->assertLessThan($ready, $branch, $mode);
        }
    }

} //@formatter:on
