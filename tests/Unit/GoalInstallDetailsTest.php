<?php
/**
 * /tests/Unit/GoalInstallDetailsTest.php
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

use DomainMOD\Goal;
use DomainMOD\Time;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/*
 * The install type and whether the files are writable, recorded with the install and upgrade activity and sent with it
 * (DomainMOD\Goal), once the database update that adds their columns has run. SOFTWARE_VERSION is a constant, so each
 * test runs in its own process and defines it itself.
 */
#[RunTestsInSeparateProcesses]
class GoalInstallDetailsTest extends TestCase
{
    public function testAnUpgradeRecordsTheInstallDetails()
    {
        define('SOFTWARE_VERSION', '4.27.0');
        $bound = array();
        $sql = '';
        $goal = $this->goal($this->pdo(true, $sql, $bound));
        $goal->upgrade('4.26.0');

        $this->assertStringContainsString('`language`, install_type, files_writable, insert_time', $sql);
        $this->assertStringContainsString(':act_language, :act_install_type, :act_files_writable, :insert_time', $sql);
        $this->assertSame('git', $bound['act_install_type']);
        $this->assertSame('no', $bound['act_files_writable']);
        $this->assertSame('4.26.0', $bound['act_old_version']);
    }

    public function testAnInstallRecordsTheInstallDetails()
    {
        define('SOFTWARE_VERSION', '4.27.0');
        $bound = array();
        $sql = '';
        $goal = $this->goal($this->pdo(true, $sql, $bound));
        $goal->installation();

        $this->assertStringContainsString("('i', 'n/a', :act_software_version", $sql);
        $this->assertStringContainsString(':act_install_type, :act_files_writable', $sql);
        $this->assertSame('git', $bound['act_install_type']);
    }

    // An upgrade from an older version records its activity in an update block before the one that adds the columns
    public function testWithoutTheColumnsTheActivityIsRecordedAsBefore()
    {
        define('SOFTWARE_VERSION', '4.26.0');
        $bound = array();
        $sql = '';
        $goal = $this->goal($this->pdo(false, $sql, $bound));
        $goal->upgrade('4.25.0');

        $this->assertStringContainsString('(type, old_version, new_version, ip, agent, `language`, insert_time)', $sql);
        $this->assertArrayNotHasKey('act_install_type', $bound);
        $this->assertFalse($goal->details_read);
    }

    public function testAFailedColumnCheckRecordsTheActivityWithoutThem()
    {
        define('SOFTWARE_VERSION', '4.27.0');
        $bound = array();
        $statement = $this->createMock(\PDOStatement::class);
        $statement->method('bindValue')->willReturnCallback(function ($name, $value) use (&$bound) {
            $bound[$name] = $value;
            return true;
        });
        $statement->expects($this->once())->method('execute')->willReturn(true);
        $pdo = $this->createMock(\PDO::class);
        $pdo->method('query')->willThrowException(new \PDOException('denied'));
        $pdo->method('prepare')->willReturn($statement);

        $this->goal($pdo)->upgrade('4.26.0');

        $this->assertArrayNotHasKey('act_install_type', $bound);
    }

    public function testTheDetailsAreSentWithTheActivity()
    {
        $row = (object) array('id' => 7, 'type' => 'u', 'old_version' => '4.26.0', 'new_version' => '4.27.0',
            'ip' => '10.0.0.1', 'agent' => 'Agent', 'language' => 'en', 'insert_time' => '2026-11-19 10:00:00',
            'install_type' => 'docker', 'files_writable' => 'unknown');
        $goal = $this->goalSending(true, $row);
        $goal->remote();

        $this->assertStringContainsString('SELECT id, type, old_version, new_version, ip, agent, `language`, insert_time, install_type, files_writable', $goal->select);
        $this->assertCount(1, $goal->sent);
        $this->assertStringStartsWith('https://domainmod.org/upgraded/index.php?ov=4.26.0&nv=4.27.0&ip=10.0.0.1', $goal->sent[0]);
        $this->assertStringEndsWith('&it=docker&w=unknown', $goal->sent[0]);
    }

    public function testWithoutTheColumnsNothingExtraIsSent()
    {
        $row = (object) array('id' => 7, 'type' => 'i', 'old_version' => 'n/a', 'new_version' => '4.26.0',
            'ip' => '10.0.0.1', 'agent' => 'Agent', 'language' => 'en', 'insert_time' => '2026-11-19 10:00:00');
        $goal = $this->goalSending(false, $row);
        $goal->remote();

        $this->assertStringNotContainsString('install_type', $goal->select);
        $this->assertStringNotContainsString('&it=', $goal->sent[0]);
    }

    public function testTheValuesAreEncodedInTheUrl()
    {
        $row = (object) array('id' => 7, 'type' => 'i', 'old_version' => 'n/a', 'new_version' => '4.27.0',
            'ip' => '', 'agent' => '', 'language' => 'en', 'insert_time' => '2026-11-19 10:00:00',
            'install_type' => 'a&b c', 'files_writable' => 'y=s');
        $goal = $this->goalSending(true, $row);
        $goal->remote();

        $this->assertStringEndsWith('&it=a%26b+c&w=y%3Ds', $goal->sent[0]);
    }

    /*
     * A stand-in database: SHOW COLUMNS lists goal_activity's columns (with the two new ones when $has_columns), and the
     * one prepared insert's SQL and bound values are collected.
     */
    private function pdo($has_columns, &$sql, &$bound)
    {
        $columns = array('id', 'type', 'old_version', 'new_version', 'ip', 'agent', 'language', 'new_activity', 'insert_time', 'update_time');
        if ($has_columns) array_splice($columns, 7, 0, array('install_type', 'files_writable'));
        $show = $this->createMock(\PDOStatement::class);
        $show->method('fetchAll')->with(\PDO::FETCH_COLUMN)->willReturn($columns);

        $insert = $this->createMock(\PDOStatement::class);
        $insert->method('bindValue')->willReturnCallback(function ($name, $value) use (&$bound) {
            $bound[$name] = $value;
            return true;
        });
        $insert->expects($this->once())->method('execute')->willReturn(true);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('query')->with('SHOW COLUMNS FROM `goal_activity`')->willReturn($show);
        $pdo->expects($this->once())->method('prepare')->willReturnCallback(function ($query) use (&$sql, $insert) {
            $sql = $query;
            return $insert;
        });
        return $pdo;
    }

    // A Goal with a stand-in database, whose install is a Git clone the web server can't write to
    private function goal($pdo)
    {
        return new class($pdo) extends Goal {
            public $details_read = false;

            public function __construct($pdo)
            {
                $this->deeb = (object) array('cnxx' => $pdo);
                $this->time = new Time();
            }

            protected function getInstallDetails()
            {
                $this->details_read = true;
                return array('git', 'no');
            }
        };
    }

    // A Goal whose one waiting activity row is $row, with the URLs it would send collected instead of sent
    private function goalSending($has_columns, $row)
    {
        $select = $this->createMock(\PDOStatement::class);
        $select->method('fetchAll')->willReturn(array($row));
        $update = $this->createMock(\PDOStatement::class);
        $update->method('execute')->willReturn(true);
        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($update);

        $goal = new class($has_columns, $pdo) extends Goal {
            public $sent = array();
            public $select = '';
            private $has_columns;

            public function __construct($has_columns, $pdo)
            {
                $this->has_columns = $has_columns;
                $this->time = new Time();
                $this->deeb = (object) array('cnxx' => $pdo);
            }

            protected function hasInstallColumns()
            {
                return $this->has_columns;
            }

            public function triggerGoal($goal_url)
            {
                $this->sent[] = $goal_url;
            }
        };

        $pdo->method('query')->willReturnCallback(function ($query) use ($goal, $select) {
            $goal->select = $query;
            return $select;
        });

        return $goal;
    }

} //@formatter:on
