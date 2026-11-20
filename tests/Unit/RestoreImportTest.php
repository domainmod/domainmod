<?php
/**
 * /tests/Unit/RestoreImportTest.php
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

use DomainMOD\RestoreImport;
use PHPUnit\Framework\TestCase;

/*
 * Backup & Restore imports the backup with RestoreImport. The importer it extends caught a failed statement, printed
 * the error, and returned, so the page went on as if the restore had worked and logged everyone out. A backup cut
 * short (ending partway through a statement, or one of DomainMOD's own backups without its closing line) was
 * imported as far as it went, with no error at all. RestoreImport now stops at the first failed statement and throws,
 * and checks the whole file first, so a cut-short file throws before any statement runs. The connection here is a
 * stand-in that records each statement.
 */
class RestoreImportTest extends TestCase
{
    const UNDATED_HEADER = "-- mysqldump-php https://github.com/ifsnop/mysqldump-php\n--\n-- Host: localhost\n";
    const HEADER = self::UNDATED_HEADER . "-- Date: Fri, 20 Nov 2026 12:00:00 -0800\n";
    const FOOTER = "\n-- Dump completed on: Fri, 20 Nov 2026 12:00:00 -0800\n";

    private $files = array();

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) unlink($file);
        }
    }

    private function file($contents)
    {
        $file = tempnam(sys_get_temp_dir(), 'domainmod-restore-test-');
        file_put_contents($file, $contents);
        $this->files[] = $file;
        return $file;
    }

    // Imports $contents through a stand-in connection that fails any statement containing $fail_on. Returns the
    // statements it ran and the exception thrown (or null).
    private function import($contents, $fail_on = null)
    {
        $connection = new RestoreImportTestConnection($fail_on);
        $error = null;

        try {

            new RestoreImportTestImport($connection, $this->file($contents));

        } catch (\Exception $e) {

            $error = $e;

        }

        return array($connection->statements, $error);
    }

    public function testACompleteBackupRunsEveryStatement()
    {
        list($statements, $error) = $this->import(self::HEADER . "DROP TABLE IF EXISTS `a`;\nCREATE TABLE `a` (\n  `id` int\n);\nINSERT INTO `a` VALUES (1);\n" . self::FOOTER);

        $this->assertNull($error);
        $this->assertSame(array("DROP TABLE IF EXISTS `a`;\n", "CREATE TABLE `a` (\n  `id` int\n);\n", "INSERT INTO `a` VALUES (1);\n"), $statements);
    }

    public function testAFailedStatementStopsTheRestoreAndThrows()
    {
        list($statements, $error) = $this->import(self::HEADER . "INSERT INTO `a` VALUES (1);\nINSERT INTO `broken` VALUES (2);\nINSERT INTO `a` VALUES (3);\n" . self::FOOTER, 'broken');

        $this->assertNotNull($error);
        $this->assertSame('Statement 2 in the backup file failed (database error 0).', $error->getMessage());
        $this->assertSame(array("INSERT INTO `a` VALUES (1);\n", "INSERT INTO `broken` VALUES (2);\n"), $statements);
    }

    public function testABackupThatEndsPartwayThroughAStatementThrows()
    {
        list($statements, $error) = $this->import(self::HEADER . "INSERT INTO `a` VALUES (1);\nINSERT INTO `a` VALUES (2),\n(3");

        $this->assertNotNull($error);
        $this->assertStringContainsString('ends partway through a statement', $error->getMessage());
        $this->assertSame(array(), $statements);
    }

    // Nothing runs, so a backup cut short after a DROP TABLE doesn't lose the table
    public function testADomainmodBackupWithoutItsLastLineThrowsBeforeAnythingRuns()
    {
        list($statements, $error) = $this->import(self::HEADER . "DROP TABLE IF EXISTS `a`;\n");

        $this->assertNotNull($error);
        $this->assertStringContainsString('missing its last line', $error->getMessage());
        $this->assertSame(array(), $statements);
    }

    public function testABackupCutShortInsideItsLastLineThrows()
    {
        list($statements, $error) = $this->import(self::HEADER . "INSERT INTO `a` VALUES (1);\n\n-- Dump completed on: Fri, 20 Nov");

        $this->assertNotNull($error);
        $this->assertStringContainsString('missing its last line', $error->getMessage());
        $this->assertSame(array(), $statements);
    }

    // A backup with a date in its header has one in its closing line too, so a closing line cut short before the date
    // is caught
    public function testADatedBackupNeedsADatedLastLine()
    {
        $dated = self::HEADER . "INSERT INTO `a` VALUES (1);\n";

        list(, $error) = $this->import($dated . "\n-- Dump completed\n");
        $this->assertNotNull($error);
        $this->assertStringContainsString('missing its last line', $error->getMessage());

        list(, $error) = $this->import($dated . self::FOOTER);
        $this->assertNull($error);
    }

    // MariaDB runs /*! */ comments, so one left without its semicolon is a statement cut short
    public function testAnExecutableCommentWithoutItsSemicolonThrows()
    {
        list($statements, $error) = $this->import("INSERT INTO `a` VALUES (1);\n/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */\n");

        $this->assertNotNull($error);
        $this->assertStringContainsString('ends partway through a statement', $error->getMessage());
        $this->assertSame(array(), $statements);
    }

    // Backup & Restore checks the file this way before it starts, so a cut-short backup signs no one out
    public function testCheckFileChecksWithoutConnecting()
    {
        RestoreImport::checkFile($this->file(self::HEADER . "INSERT INTO `a` VALUES (1);\n" . self::FOOTER));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('missing its last line');
        RestoreImport::checkFile($this->file(self::HEADER . "INSERT INTO `a` VALUES (1);\n"));
    }

    // MariaDB's error message can quote the statement, so a saved secret in it never reaches the page or the log
    public function testTheDatabaseErrorTextIsntPassedOn()
    {
        list(, $error) = $this->import("INSERT INTO `broken` VALUES ('s3cret-api-key');\n", 'broken');

        $this->assertNotNull($error);
        $this->assertStringNotContainsString('s3cret', $error->getMessage());
        $this->assertStringNotContainsString('Unknown table', $error->getMessage());
    }

    // MariaDB runs /*M! */ comments too
    public function testAMariadbCommentWithoutItsSemicolonThrows()
    {
        list(, $error) = $this->import("INSERT INTO `a` VALUES (1);\n/*M!999999\\- enable the sandbox mode */\n");

        $this->assertNotNull($error);
        $this->assertStringContainsString('ends partway through a statement', $error->getMessage());
    }

    // A comment after the last statement isn't a statement that was cut short
    public function testACommentAfterTheLastStatementIsFine()
    {
        foreach (array("/* End of backup */\n", "# End of backup\n", "  -- End of backup\n") as $comment) {
            list($statements, $error) = $this->import("INSERT INTO `a` VALUES (1);\n" . $comment);

            $this->assertNull($error, $comment);
            $this->assertCount(1, $statements, $comment);
        }
    }

    // However long the comment, what follows it is still checked
    public function testAStatementCutShortAfterALongCommentThrows()
    {
        list($statements, $error) = $this->import("INSERT INTO `a` VALUES (1);\n/* " . str_repeat('x', 2000000) . " */ INSERT INTO `a` VALUES (2),\n");

        $this->assertNotNull($error);
        $this->assertStringContainsString('ends partway through a statement', $error->getMessage());
        $this->assertSame(array(), $statements);
    }

    public function testABackupMadeWithoutTheDumpDateStillCountsAsComplete()
    {
        list(, $error) = $this->import(self::UNDATED_HEADER . "INSERT INTO `a` VALUES (1);\n\n-- Dump completed\n");

        $this->assertNull($error);
    }

    // Backups made some other way (such as with phpMyAdmin) have no closing line to check
    public function testAnotherToolsBackupNeedsNoClosingLine()
    {
        list($statements, $error) = $this->import("-- phpMyAdmin SQL Dump\nINSERT INTO `a` VALUES (1);\n");

        $this->assertNull($error);
        $this->assertCount(1, $statements);
    }

    // An empty file, or one cut short inside its header, restores nothing
    public function testAFileWithNoStatementsThrows()
    {
        foreach (array('', "-- mysqldump-php", "-- phpMyAdmin SQL Dump\n/* nothing */\n") as $contents) {
            list(, $error) = $this->import($contents);

            $this->assertNotNull($error, $contents);
            $this->assertStringContainsString('no SQL statements', $error->getMessage(), $contents);
        }
    }

    public function testAMissingFileThrows()
    {
        $this->expectException(\RuntimeException::class);
        new RestoreImportTestImport(new RestoreImportTestConnection(null), sys_get_temp_dir() . '/domainmod-restore-test-missing.sql');
    }

}

// RestoreImport with the stand-in connection instead of a real one
class RestoreImportTestImport extends RestoreImport
{
    private $test_connection;

    public function __construct($connection, $filename)
    {
        $this->test_connection = $connection;
        parent::__construct($filename, 'user', 'pass', 'db', 'localhost', array(), false);
    }

    protected function createconnection()
    {
        return $this->test_connection;
    }
}

// Records each statement, and fails the ones containing $fail_on, as mysqli::query() does (returning false)
class RestoreImportTestConnection
{
    public $connect_errno = 0;
    public $connect_error = '';
    public $error = '';
    public $statements = array();
    private $fail_on;

    public function __construct($fail_on)
    {
        $this->fail_on = $fail_on;
    }

    public function query($statement)
    {
        $this->statements[] = $statement;
        if ($this->fail_on !== null && strpos($statement, $this->fail_on) !== false) {
            $this->error = "Unknown table 'broken' near '" . trim($statement) . "'";
            return false;
        }
        return true;
    }

    public function close()
    {
        return true;
    }

} //@formatter:on
