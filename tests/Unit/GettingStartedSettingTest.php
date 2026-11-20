<?php
/**
 * /tests/Unit/GettingStartedSettingTest.php
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

use DomainMOD\Dashboard;
use DomainMOD\Database;
use DomainMOD\Tests\Support\FakeDatabase;
use DomainMOD\Tests\Support\Instance;
use DomainMOD\Time;
use DomainMOD\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

// The Getting Started checklist's per-user hide setting (user_settings.display_getting_started, from the 4.26.0 database
// update). DEBUG_MODE is a constant, so each test runs in its own process and defines it itself when it needs it.
#[RunTestsInSeparateProcesses]
class GettingStartedSettingTest extends TestCase
{
    public static function versionCases()
    {
        return array(
            'the release before' => array('4.25.0', false),
            'an older numbering' => array('4.24.02', false),
            'the update that adds it' => array('4.26.0', true),
            'a patch release after it' => array('4.26.01', true),
            'a later minor release' => array('4.27.0', true),
            'a later major release' => array('5.0.0', true),
            'empty' => array('', false),
            'a failed read' => array(false, false),
            'nothing' => array(null, false),
        );
    }

    #[DataProvider('versionCases')]
    public function testTheSettingExistsFromTheDatabaseUpdateOn($db_version, $expected)
    {
        $this->assertSame($expected, User::hasGettingStartedSetting($db_version));
    }

    public static function valueCases()
    {
        return array(
            'no value yet' => array(null, true),
            'shown, from the database' => array('1', true),
            'shown, from the session' => array(1, true),
            'hidden, from the database' => array('0', false),
            'hidden, from the session' => array(0, false),
        );
    }

    #[DataProvider('valueCases')]
    public function testOnlyAnExplicitZeroHidesTheChecklist($value, $expected)
    {
        $this->assertSame($expected, User::showsGettingStarted($value));
    }

    public function testDoesNotReadTheColumnBeforeTheDatabaseUpdate()
    {
        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->never())->method('prepare');
        $user = $this->user($pdo, '4.25.0');

        $this->assertFalse($user->canHideGettingStarted());
        $this->assertNull($user->getGettingStartedDisplay(1));
        $this->assertSame(array(), $user->logged);
    }

    public static function storedCases()
    {
        return array(
            'shown' => array('1', 1),
            'hidden' => array('0', 0),
            'no settings row' => array(false, null),
        );
    }

    #[DataProvider('storedCases')]
    public function testReadsTheSettingOnceTheDatabaseIsUpdated($stored, $expected)
    {
        $statement = $this->createMock(\PDOStatement::class);
        $statement->expects($this->once())->method('bindValue')->with('user_id', 7, \PDO::PARAM_INT);
        $statement->method('fetchColumn')->willReturn($stored);
        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->once())->method('prepare')
            ->with($this->stringContains('SELECT display_getting_started'))->willReturn($statement);

        $this->assertSame($expected, $this->user($pdo, '4.26.0')->getGettingStartedDisplay(7));
    }

    public function testAFailedReadLeavesTheChecklistShowingAndLogsNothingOutsideDebugMode()
    {
        $user = $this->user($this->failingPdo(), '4.26.0');

        $this->assertNull($user->getGettingStartedDisplay(1));
        $this->assertSame(array(), $user->logged);
    }

    public function testAFailedReadIsLoggedInDebugMode()
    {
        define('DEBUG_MODE', 1);
        $user = $this->user($this->failingPdo(), '4.26.0');

        $this->assertNull($user->getGettingStartedDisplay(1));
        $this->assertSame(array('The Getting Started setting could not be read'), $user->logged);
    }

    public function testAFailingLogDoesNotStopThePage()
    {
        define('DEBUG_MODE', 1);
        $user = $this->user($this->failingPdo(), '4.26.0', true);

        $this->assertNull($user->getGettingStartedDisplay(1));
        $this->assertFalse($user->setGettingStartedDisplay(1, 0));
    }

    public function testAFailedVersionCheckLeavesTheChecklistWithoutItsHideLink()
    {
        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->never())->method('prepare');
        $user = $this->user($pdo, new \PDOException('gone'));

        $this->assertFalse($user->canHideGettingStarted());
        $this->assertNull($user->getGettingStartedDisplay(1));
        $this->assertFalse($user->setGettingStartedDisplay(1, 0));
    }

    public function testDoesNotWriteTheColumnBeforeTheDatabaseUpdate()
    {
        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->never())->method('prepare');

        $this->assertFalse($this->user($pdo, '4.25.0')->setGettingStartedDisplay(1, 0));
    }

    public static function saveCases()
    {
        return array(
            'hide' => array(0, 0),
            'show' => array(1, 1),
            'show, from a checkbox' => array('1', 1),
            'hide, from a missing checkbox' => array('', 0),
        );
    }

    #[DataProvider('saveCases')]
    public function testSavesTheSettingForThatUserOnly($display, $saved)
    {
        $bound = array();
        $statement = $this->createMock(\PDOStatement::class);
        $statement->method('bindValue')->willReturnCallback(function ($name, $value, $type) use (&$bound) {
            $bound[$name] = array($value, $type);
            return true;
        });
        $statement->expects($this->once())->method('execute')->willReturn(true);
        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->once())->method('prepare')
            ->with($this->logicalAnd($this->stringContains('SET display_getting_started = :display_getting_started'),
                $this->stringContains('WHERE user_id = :user_id')))->willReturn($statement);

        $this->assertTrue($this->user($pdo, '4.26.0')->setGettingStartedDisplay(7, $display));
        $this->assertSame(array($saved, \PDO::PARAM_INT), $bound['display_getting_started']);
        $this->assertSame(array(7, \PDO::PARAM_INT), $bound['user_id']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $bound['update_time'][0]);
    }

    public function testAFailedWriteReportsThatNothingWasSaved()
    {
        $this->assertFalse($this->user($this->failingPdo(), '4.26.0')->setGettingStartedDisplay(1, 0));
    }

    // A hidden checklist is decided from the session alone, so the Dashboard doesn't count domains for it (this
    // Dashboard has no database, so a query would fail the test).
    public function testTheDashboardLeavesOutAHiddenChecklistWithoutQuerying()
    {
        $_SESSION['s_display_getting_started'] = 0;

        $this->assertFalse(Instance::withoutConstructor(Dashboard::class)->showGettingStarted());
    }

    /*
     * The checklist shows only while there are no domains and no SSL certificates: a user tracking certificates without
     * a domain has clearly started, so they see the Dashboard's totals instead
     */
    public function testTheChecklistShowsOnlyWithNoDomainsAndNoCertificates()
    {
        $cases = array(
            'nothing yet' => array('0', '0', true),
            'certificates without domains' => array('0', '3', false),
            'domains without certificates' => array('2', '0', false),
            'both' => array('2', '3', false),
        );
        foreach ($cases as $name => $case) {
            list($domains, $certs, $expected) = $case;
            $_SESSION['s_display_getting_started'] = 1;
            $pdo = new FakeDatabase(array('FROM domains' => $domains, 'FROM ssl_certs' => $certs));
            (new \ReflectionProperty(Database::class, 'instance'))->setValue(null,
                Instance::withoutConstructor(Database::class, array('cnxx' => $pdo)));

            $this->assertSame($expected, Instance::withoutConstructor(Dashboard::class)->showGettingStarted(), $name);
        }
        (new \ReflectionProperty(Database::class, 'instance'))->setValue(null, null);
    }

    // A User with a stand-in database and log. $db_version is the database's version, or an exception for a version
    // check that fails. A failing log throws when it's written to.
    private function user($pdo, $db_version, $failing_log = false)
    {
        return new class($pdo, $db_version, $failing_log) extends User {
            public $logged = array();
            private $db_version;
            private $failing_log;

            public function __construct($pdo, $db_version, $failing_log)
            {
                $this->deeb = (object) array('cnxx' => $pdo);
                $this->time = new Time();
                $this->db_version = $db_version;
                $this->failing_log = $failing_log;
            }

            protected function getDbVersion()
            {
                if ($this->db_version instanceof \Throwable) throw $this->db_version;
                return $this->db_version;
            }

            protected function getLog()
            {
                $user = $this;
                return new class($user) {
                    private $user;

                    public function __construct($user)
                    {
                        $this->user = $user;
                    }

                    public function error($message, $extra_info = array())
                    {
                        $this->user->logEntry($message);
                    }
                };
            }

            public function logEntry($message)
            {
                if ($this->failing_log) throw new \PDOException('The log table is unavailable');
                $this->logged[] = $message;
            }
        };
    }

    // A database connection whose every query and statement fails, as on a missing column or a lost connection
    private function failingPdo()
    {
        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willThrowException(new \PDOException('Unknown column'));
        $pdo->method('query')->willThrowException(new \PDOException('Unknown column'));
        return $pdo;
    }

} //@formatter:on
