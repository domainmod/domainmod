<?php
/**
 * /tests/Unit/SchedulerHealthTest.php
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

use DomainMOD\Scheduler;
use DomainMOD\Time;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

// The Task Scheduler's health check (Scheduler::healthState() and the warning behind it). SOFTWARE_VERSION and
// DEMO_INSTALLATION are constants, so each test runs in its own process and defines them itself.
#[RunTestsInSeparateProcesses]
class SchedulerHealthTest extends TestCase
{
    private const NOW = 1794787200; // 2026-11-16 00:00:00 UTC

    public static function healthCases()
    {
        return array(
            'never run' => array('1970-01-01 00:00:00', true, 'never'),
            'a zero date from an older install' => array('0000-00-00 00:00:00', true, 'never'),
            'just ran' => array('2026-11-16 00:00:00', true, 'ok'),
            'ten minutes ago' => array('2026-11-15 23:50:00', true, 'ok'),
            'exactly at the threshold' => array('2026-11-15 22:00:00', true, 'ok'),
            'a second past the threshold' => array('2026-11-15 21:59:59', true, 'stale'),
            'days ago' => array('2026-11-01 08:00:00', true, 'stale'),
            'a clock slightly ahead' => array('2026-11-16 00:05:00', true, 'ok'),
            'stale but every task disabled' => array('2026-11-01 08:00:00', false, 'off'),
            'never run and every task disabled' => array('1970-01-01 00:00:00', false, 'off'),
            'the time could not be read' => array(null, true, 'off'),
        );
    }

    #[DataProvider('healthCases')]
    public function testWorksOutTheHealthFromTheLastRun($last_run, $any_active, $expected)
    {
        $this->assertSame($expected, Scheduler::cronHealth($last_run, self::NOW, $any_active));
    }

    public function testTheThresholdIsTwoHours()
    {
        $this->assertSame(7200, Scheduler::CRON_STALE_SECONDS);
    }

    public function testRecordsTheRunInUtc()
    {
        define('SOFTWARE_VERSION', '4.26.0');
        $statement = $this->createMock(\PDOStatement::class);
        $statement->expects($this->once())->method('bindValue')
            ->with('cron_last_run', $this->matchesRegularExpression('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/'), \PDO::PARAM_STR);
        $statement->expects($this->once())->method('execute')->willReturn(true);
        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->once())->method('prepare')
            ->with($this->stringContains('SET cron_last_run = :cron_last_run'))->willReturn($statement);

        $scheduler = $this->scheduler($pdo);
        $scheduler->recordCronRun();

        $this->assertSame(array(), $scheduler->logged);
        $this->assertSame(array(), $scheduler->fallback);
    }

    public function testAFailedWriteIsLoggedAndDoesNotStopCron()
    {
        define('SOFTWARE_VERSION', '4.26.0');
        $scheduler = $this->scheduler($this->failingPdo());
        $scheduler->recordCronRun();

        $this->assertSame(array('The run of cron.php could not be recorded'), $scheduler->logged);
        $this->assertSame(array(), $scheduler->fallback);
    }

    public function testAFailedWriteWithAFailingLogGoesToPhpsErrorLog()
    {
        define('SOFTWARE_VERSION', '4.26.0');
        $scheduler = $this->scheduler($this->failingPdo(), true);
        $scheduler->recordCronRun();

        $this->assertCount(1, $scheduler->fallback);
        $this->assertStringContainsString('The run of cron.php could not be recorded', $scheduler->fallback[0]);
    }

    public function testReadsTheLastRunOnceTheDatabaseIsUpgraded()
    {
        define('SOFTWARE_VERSION', '4.26.0');
        $scheduler = $this->scheduler($this->pdoReturning(array('2026-11-15 23:50:00')), false, '4.26.0');

        $this->assertSame('2026-11-15 23:50:00', $scheduler->getCronLastRun());
    }

    public function testDoesNotReadTheColumnWhileTheUpgradeIsWaiting()
    {
        define('SOFTWARE_VERSION', '4.26.0');
        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->never())->method('query');
        $scheduler = $this->scheduler($pdo, false, '4.25.0');

        $this->assertNull($scheduler->getCronLastRun());
        $this->assertSame('off', $scheduler->healthState());
        $this->assertSame(array(), $scheduler->logged);
    }

    public function testDoesNotRecordTheRunWhileTheUpgradeIsWaiting()
    {
        define('SOFTWARE_VERSION', '4.26.0');
        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->never())->method('prepare');
        $scheduler = $this->scheduler($pdo, false, '4.25.0');
        $scheduler->recordCronRun();

        $this->assertSame(array(), $scheduler->logged);
        $this->assertSame(array(), $scheduler->fallback);
    }

    // Before the version bump, the code and the database are both at the older version, and the column isn't there yet
    public function testLeavesTheColumnAloneBeforeTheUpdateThatAddsIt()
    {
        define('SOFTWARE_VERSION', '4.25.0');
        define('DEMO_INSTALLATION', false);
        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->never())->method('query');
        $pdo->expects($this->never())->method('prepare');
        $scheduler = $this->scheduler($pdo, false, '4.25.0');

        $this->assertNull($scheduler->getCronLastRun());
        $this->assertSame('off', $scheduler->healthState());
        $scheduler->recordCronRun();
        $this->assertSame(array(), $scheduler->logged);
        $this->assertSame(array(), $scheduler->fallback);
    }

    public function testReadsTheLastRunInALaterVersion()
    {
        define('SOFTWARE_VERSION', '4.27.0');
        $scheduler = $this->scheduler($this->pdoReturning(array('2026-11-15 23:50:00')), false, '4.27.0');

        $this->assertSame('2026-11-15 23:50:00', $scheduler->getCronLastRun());
    }

    public static function versionCases()
    {
        return array(
            'the release before' => array('4.25.0', false),
            'a two-digit patch before' => array('4.25.10', false),
            'the release that adds it' => array('4.26.0', true),
            'a patch after' => array('4.26.01', true),
            'a later major version' => array('5.0.0', true),
            'empty' => array('', false),
            'no version read' => array(false, false),
            'null' => array(null, false),
        );
    }

    #[DataProvider('versionCases')]
    public function testKnowsWhichDatabaseVersionsHaveTheColumn($db_version, $expected)
    {
        $this->assertSame($expected, Scheduler::hasCronLastRun($db_version));
    }

    public function testTheColumnArrivesIn4260()
    {
        $this->assertSame('4.26.0', Scheduler::CRON_LAST_RUN_DB_VERSION);
    }

    public function testAFailedReadShowsNothing()
    {
        define('SOFTWARE_VERSION', '4.26.0');
        define('DEMO_INSTALLATION', false);
        $scheduler = $this->scheduler($this->failingPdo(), true, '4.26.0');

        $this->assertNull($scheduler->getCronLastRun());
        $this->assertSame('off', $scheduler->healthState());
        $this->assertFalse($scheduler->hasRunRecently());
        $this->assertNotEmpty($scheduler->fallback);
    }

    public function testAFailedVersionCheckShowsNothing()
    {
        define('SOFTWARE_VERSION', '4.26.0');
        $scheduler = $this->scheduler($this->createMock(\PDO::class), true, new \PDOException('gone'));

        $this->assertNull($scheduler->getCronLastRun());
        $this->assertStringContainsString('could not be read', $scheduler->fallback[0]);
    }

    public static function stateCases()
    {
        return array(
            'ok' => array('2026-11-15 23:50:00', 1, 'ok', true),
            'stale' => array('2026-11-15 20:00:00', 1, 'stale', false),
            'never' => array('1970-01-01 00:00:00', 6, 'never', false),
            'every task disabled' => array('2026-11-15 20:00:00', 0, 'off', false),
        );
    }

    // The time now is the real clock's, so the last runs are worked out from it.
    #[DataProvider('stateCases')]
    public function testHealthStateAndTheGettingStartedStep($last_run, $active_tasks, $expected, $ticked)
    {
        define('SOFTWARE_VERSION', '4.26.0');
        define('DEMO_INSTALLATION', false);
        if ($last_run !== '1970-01-01 00:00:00') {
            $last_run = gmdate('Y-m-d H:i:s', time() - (self::NOW - strtotime($last_run . ' UTC')));
        }
        $scheduler = $this->scheduler($this->pdoReturning(array($last_run, (string) $active_tasks, $last_run, (string) $active_tasks)), false, '4.26.0');

        $this->assertSame($expected, $scheduler->healthState());
        $this->assertSame($ticked, $scheduler->hasRunRecently());
    }

    public function testADemoInstallShowsNothing()
    {
        define('SOFTWARE_VERSION', '4.26.0');
        define('DEMO_INSTALLATION', true);
        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->never())->method('query');

        $this->assertSame('off', $this->scheduler($pdo, false, '4.26.0')->healthState());
    }

    // A Scheduler with a stand-in database and log, and PHP's error log captured. $db_version is the database's
    // version, or an exception for a version check that fails.
    private function scheduler($pdo, $failing_log = false, $db_version = '4.26.0')
    {
        return new class($pdo, $failing_log, $db_version) extends Scheduler {
            public $logged = array();
            public $fallback = array();
            private $failing_log;
            private $db_version;

            public function __construct($pdo, $failing_log, $db_version)
            {
                $this->deeb = (object) array('cnxx' => $pdo);
                $this->time = new Time();
                $this->failing_log = $failing_log;
                $this->db_version = $db_version;
            }

            protected function getDbVersion()
            {
                if ($this->db_version instanceof \Throwable) throw $this->db_version;
                return $this->db_version;
            }

            protected function getLog()
            {
                $scheduler = $this;
                return new class($scheduler) {
                    private $scheduler;

                    public function __construct($scheduler)
                    {
                        $this->scheduler = $scheduler;
                    }

                    public function error($message, $extra_info = array())
                    {
                        $this->scheduler->logEntry($message);
                    }
                };
            }

            public function logEntry($message)
            {
                if ($this->failing_log) throw new \PDOException('The log table is unavailable');
                $this->logged[] = $message;
            }

            protected function fallbackLog($message)
            {
                $this->fallback[] = $message;
            }
        };
    }

    // A database connection whose every query and statement fails, as on a full disk or a missing column
    private function failingPdo()
    {
        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willThrowException(new \PDOException('Unknown column'));
        $pdo->method('query')->willThrowException(new \PDOException('Unknown column'));
        return $pdo;
    }

    // A database connection whose queries return these single values in turn
    private function pdoReturning(array $values)
    {
        $statements = array();
        foreach ($values as $value) {
            $statement = $this->createMock(\PDOStatement::class);
            $statement->method('fetchColumn')->willReturn($value);
            $statements[] = $statement;
        }
        $pdo = $this->createMock(\PDO::class);
        $pdo->method('query')->willReturnOnConsecutiveCalls(...$statements);
        return $pdo;
    }

} //@formatter:on
