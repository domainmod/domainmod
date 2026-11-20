<?php
/**
 * /tests/Unit/LatestVersionTest.php
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

use DomainMOD\System;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/*
 * The new version check (System::checkLatestVersion() and checkVersion()) and the saved latest version the Upgrade page
 * reads (getLatestVersion()). SOFTWARE_VERSION and WEB_ROOT are constants, so each test runs in its own process and
 * defines them itself.
 */
#[RunTestsInSeparateProcesses]
class LatestVersionTest extends TestCase
{
    public function testASuccessfulCheckSavesAllThreeValuesTogether()
    {
        define('SOFTWARE_VERSION', '4.27.0');
        $bound = array();
        // Every value that's bound is set in the SQL, or PDO refuses the statement
        $pdo = $this->pdoExpectingUpdate(array('upgrade_available = :upgrade_available', 'latest_version = :latest_version', 'latest_version_checked = :latest_version_checked'), $bound);
        $system = $this->system($pdo, "4.28.0\n", '4.27.0');

        $this->assertSame('available', $system->checkLatestVersion('4.27.0'));
        $this->assertSame(1, $bound['upgrade_available']);
        $this->assertSame('4.28.0', $bound['latest_version']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $bound['latest_version_checked']);
        $this->assertSame('1', $_SESSION['s_system_upgrade_available']);
    }

    public function testTheSameVersionIsCurrent()
    {
        define('SOFTWARE_VERSION', '4.27.0');
        $bound = array();
        $system = $this->system($this->pdoExpectingUpdate('latest_version = :latest_version', $bound), '4.27.0', '4.27.0');

        $this->assertSame('current', $system->checkLatestVersion('4.27.0'));
        $this->assertSame(0, $bound['upgrade_available']);
        $this->assertSame('4.27.0', $bound['latest_version']);
        $this->assertSame('0', $_SESSION['s_system_upgrade_available']);
    }

    public function testAnOlderVersionOnGithubIsCurrent()
    {
        define('SOFTWARE_VERSION', '4.27.0');
        $bound = array();
        $system = $this->system($this->pdoExpectingUpdate('latest_version = :latest_version', $bound), '4.26.0', '4.27.0');

        $this->assertSame('current', $system->checkLatestVersion('4.27.0'));
        $this->assertSame(0, $bound['upgrade_available']);
    }

    public static function failedAnswers()
    {
        return array(
            'a failed fetch' => array(false),
            'empty' => array(''),
            'an error page' => array('<html>404: Not Found</html>'),
            'two parts' => array('4.27'),
            'a suffix' => array('4.28.0-beta'),
            'text before it' => array('v4.28.0'),
            'two versions' => array("4.28.0\n4.29.0"),
        );
    }

    #[DataProvider('failedAnswers')]
    public function testAFailedOrMalformedCheckChangesNothing($answer)
    {
        define('SOFTWARE_VERSION', '4.27.0');
        $_SESSION['s_system_upgrade_available'] = '1';
        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->never())->method('prepare');
        $pdo->expects($this->never())->method('query');

        $system = $this->system($pdo, $answer, '4.27.0');

        $this->assertSame('failed', $system->checkLatestVersion('4.27.0'));
        $this->assertSame('1', $_SESSION['s_system_upgrade_available']);
    }

    public function testAFailedSaveIsLoggedAndCountsAsAFailedCheck()
    {
        define('SOFTWARE_VERSION', '4.27.0');
        $_SESSION['s_system_upgrade_available'] = '0';
        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willThrowException(new \PDOException('gone away'));
        $system = $this->system($pdo, '4.28.0', '4.27.0');

        $this->assertSame('failed', $system->checkLatestVersion('4.27.0'));
        $this->assertSame(array('The latest version could not be saved'), $system->logged);
        $this->assertSame('0', $_SESSION['s_system_upgrade_available']);
    }

    public function testWhileTheUpgradeIsWaitingOnlyTheOldColumnIsSaved()
    {
        define('SOFTWARE_VERSION', '4.27.0');
        $bound = array();
        $pdo = $this->pdoExpectingUpdate('SET upgrade_available = :upgrade_available', $bound);
        $system = $this->system($pdo, '4.28.0', '4.26.0');

        $this->assertSame('available', $system->checkLatestVersion('4.27.0'));
        $this->assertSame(array('upgrade_available' => 1), $bound);
    }

    // Before the version bump, the code and the database are both at the older version, and the columns aren't there yet
    public function testBeforeTheUpdateThatAddsThemOnlyTheOldColumnIsSaved()
    {
        define('SOFTWARE_VERSION', '4.26.0');
        $bound = array();
        $pdo = $this->pdoExpectingUpdate('SET upgrade_available = :upgrade_available', $bound);
        $system = $this->system($pdo, '4.26.0', '4.26.0');

        $this->assertSame('current', $system->checkLatestVersion('4.26.0'));
        $this->assertSame(array('upgrade_available' => 0), $bound);
        $this->assertNull($system->getLatestVersion());
    }

    public function testAFailedVersionReadSavesOnlyTheOldColumn()
    {
        define('SOFTWARE_VERSION', '4.27.0');
        $bound = array();
        $pdo = $this->pdoExpectingUpdate('SET upgrade_available = :upgrade_available', $bound);
        $system = $this->system($pdo, '4.28.0', new \PDOException('gone'));

        $this->assertSame('available', $system->checkLatestVersion('4.27.0'));
        $this->assertSame(array('upgrade_available' => 1), $bound);
        $this->assertSame(array('The database version could not be read'), $system->logged);
    }

    public function testCheckVersionReturnsTheUpgradeMessageOnlyWhenOneIsAvailable()
    {
        define('SOFTWARE_VERSION', '4.27.0');
        define('SOFTWARE_TITLE', 'DomainMOD');
        define('WEB_ROOT', '/dm');
        $bound = array();

        $message = $this->system($this->pdoExpectingUpdate('latest_version', $bound), '4.28.0', '4.27.0')->checkVersion('4.27.0');
        $this->assertStringContainsString('<a href="/dm/admin/upgrade/">', $message);
        $this->assertStringNotContainsString('target=', $message);
        $this->assertStringNotContainsString('domainmod.org', $message);

        $this->assertSame('No Upgrade Available', $this->system($this->pdoExpectingUpdate('latest_version', $bound), '4.27.0', '4.27.0')->checkVersion('4.27.0'));
    }

    public function testAFailedCheckAnswersFromWhatWasSaved()
    {
        define('SOFTWARE_VERSION', '4.27.0');
        define('SOFTWARE_TITLE', 'DomainMOD');
        define('WEB_ROOT', '');
        $pdo = $this->createMock(\PDO::class);

        $_SESSION['s_system_upgrade_available'] = '1';
        $this->assertStringContainsString('<a href="/admin/upgrade/">', $this->system($pdo, '', '4.27.0')->checkVersion('4.27.0'));

        $_SESSION['s_system_upgrade_available'] = '0';
        $this->assertSame('No Upgrade Available', $this->system($pdo, '', '4.27.0')->checkVersion('4.27.0'));
    }

    public function testTheUpgradeMessageEscapesTheWebRoot()
    {
        define('SOFTWARE_TITLE', 'DomainMOD');
        define('WEB_ROOT', '/a"b');

        $this->assertStringContainsString('<a href="/a&quot;b/admin/upgrade/">', $this->system($this->createMock(\PDO::class), '', '')->getUpgradeMessage());
    }

    public function testReadsTheSavedVersionOnceTheDatabaseIsUpgraded()
    {
        define('SOFTWARE_VERSION', '4.27.0');
        $system = $this->system($this->pdoReturningRow(array('latest_version' => '4.28.0', 'latest_version_checked' => '2026-11-19 10:00:00')), '', '4.27.0');

        $this->assertSame(array('version' => '4.28.0', 'checked' => '2026-11-19 10:00:00'), $system->getLatestVersion());
    }

    public function testDoesNotReadTheColumnsWhileTheUpgradeIsWaiting()
    {
        define('SOFTWARE_VERSION', '4.27.0');
        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->never())->method('query');

        $this->assertNull($this->system($pdo, '', '4.26.0')->getLatestVersion());
    }

    public function testAFailedReadIsLoggedAndShowsNothing()
    {
        define('SOFTWARE_VERSION', '4.27.0');
        $pdo = $this->createMock(\PDO::class);
        $pdo->method('query')->willThrowException(new \PDOException('Unknown column'));
        $system = $this->system($pdo, '', '4.27.0');

        $this->assertNull($system->getLatestVersion());
        $this->assertSame(array('The latest version could not be read'), $system->logged);
    }

    public static function versionCases()
    {
        return array(
            'the release before' => array('4.26.0', false),
            'a two-digit patch before' => array('4.26.10', false),
            'the release that adds them' => array('4.27.0', true),
            'a patch after' => array('4.27.01', true),
            'a later major version' => array('5.0.0', true),
            'empty' => array('', false),
            'no version read' => array(false, false),
            'null' => array(null, false),
        );
    }

    #[DataProvider('versionCases')]
    public function testKnowsWhichDatabaseVersionsHaveTheColumns($db_version, $expected)
    {
        $this->assertSame($expected, System::hasLatestVersion($db_version));
    }

    public function testTheColumnsArriveIn4270()
    {
        $this->assertSame('4.27.0', System::LATEST_VERSION_DB_VERSION);
    }

    // A stand-in database whose one prepared statement must contain $sql (one string, or each of several), with every
    // bound value collected in $bound
    private function pdoExpectingUpdate($sql, &$bound)
    {
        $contains = array_map(array($this, 'stringContains'), (array) $sql);
        $statement = $this->createMock(\PDOStatement::class);
        $statement->method('bindValue')->willReturnCallback(function ($name, $value) use (&$bound) {
            $bound[$name] = $value;
            return true;
        });
        $statement->expects($this->once())->method('execute')->willReturn(true);
        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->once())->method('prepare')->with($this->logicalAnd(...$contains))->willReturn($statement);
        return $pdo;
    }

    private function pdoReturningRow($row)
    {
        $statement = $this->createMock(\PDOStatement::class);
        $statement->method('fetch')->willReturn($row);
        $pdo = $this->createMock(\PDO::class);
        $pdo->method('query')->willReturn($statement);
        return $pdo;
    }

    /*
     * A System with a stand-in database and log. $live_version is what GitHub's version.txt answers (false for a failed
     * fetch), and $db_version the database's version, or an exception for a version read that fails.
     */
    private function system($pdo, $live_version, $db_version)
    {
        return new class($pdo, $live_version, $db_version) extends System {
            public $logged = array();
            private $live_version;
            private $db_version;

            public function __construct($pdo, $live_version, $db_version)
            {
                $this->deeb = (object) array('cnxx' => $pdo);
                $this->live_version = $live_version;
                $this->db_version = $db_version;
            }

            public function getLiveVersion()
            {
                return $this->live_version;
            }

            public function getDbVersion()
            {
                if ($this->db_version instanceof \Throwable) throw $this->db_version;
                return $this->db_version;
            }

            protected function logLatestVersionProblem($message, $e)
            {
                $this->logged[] = $message;
            }
        };
    }

} //@formatter:on
