<?php
/**
 * /tests/Unit/DomainQueueStaleProcessingTest.php
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

use DomainMOD\DomainQueue;
use DomainMOD\Log;
use DomainMOD\Maintenance;
use DomainMOD\QueryBuild;
use DomainMOD\Scheduler;
use DomainMOD\System;
use DomainMOD\Tests\Support\FakeDatabase;
use DomainMOD\Time;
use PHPUnit\Framework\TestCase;

/*
 * A Domain Queue run flags the lists and domains it takes as processing, and only clears each flag as it finishes, so
 * a run that was killed part-way (a time limit or a crash) left them flagged, and no later run processed them again.
 * The queue only runs while it holds its task's lock, so each run now starts by clearing any flags left over. For that
 * to be safe, a domain whose registrar has no API class (or that fails) is put back instead of ending the run, marking
 * a domain ready and importing it happen in one transaction, and Maintenance's Clear Queue Processing takes the lock
 * too, so it can't clear a live run's flags.
 */
class DomainQueueStaleProcessingTest extends TestCase
{
    const RELEASE_LISTS = "UPDATE domain_queue_list SET processing = '0' WHERE processing = '1'";
    const RELEASE_DOMAINS = "UPDATE domain_queue SET ready_to_import = IF(finished = '0', '0', ready_to_import), "
        . "processing = '0' WHERE processing = '1'";
    const NOT_PROCESSING = "UPDATE domain_queue SET processing = '0' WHERE id = :queue_domain_id";
    const READY = "UPDATE domain_queue SET ready_to_import = '1' WHERE id";
    const FINISHED = "UPDATE domain_queue SET processing = '0', ready_to_import = '2', finished = '1' WHERE id";

    public function testAListRunClearsLeftoverListFlagsFirst()
    {
        $pdo = new FakeDatabase();
        $this->queue($pdo)->processQueueList();

        $release = $this->firstQuery($pdo, self::RELEASE_LISTS);
        $this->assertNotNull($release);
        $this->assertLessThan($this->firstQuery($pdo, 'FROM domain_queue_list'), $release);
        $this->assertLessThan($this->firstQuery($pdo, "UPDATE domain_queue_list SET processing = '1'"), $release);
    }

    public function testADomainRunClearsLeftoverDomainFlagsFirst()
    {
        $pdo = new FakeDatabase();
        $this->queue($pdo)->processQueueDomain();

        $release = $this->firstQuery($pdo, self::RELEASE_DOMAINS);
        $this->assertNotNull($release);
        $this->assertLessThan($this->firstQuery($pdo, 'FROM domain_queue AS dq'), $release);
        $this->assertLessThan($this->firstQuery($pdo, "UPDATE domain_queue SET processing = '1'"), $release);
    }

    // Before, the first domain with an unknown registrar ended the run, so the domains after it were never processed
    public function testADomainWithAnUnknownRegistrarDoesNotStopTheOthers()
    {
        $pdo = $this->transactionDatabase(array('FROM domain_queue AS dq' => array(
            $this->row(1, 'No Such Registrar'), $this->row(2, 'Another Unknown Registrar'))));
        $queue = $this->queue($pdo);
        $queue->log = $this->createMock(Log::class);
        $queue->log->expects($this->exactly(2))->method('error');

        $queue->processQueueDomain();

        $this->assertSame(array(1, 2), $this->putBack($pdo));
    }

    public function testAFailedDomainDoesNotStopTheOthers()
    {
        $pdo = $this->transactionDatabase(array('FROM domain_queue AS dq' => array(
            $this->row(1, 'Fails'), $this->row(2, 'Fails'))));
        $queue = $this->queue($pdo, new \RuntimeException('The registrar could not be reached'));

        $queue->processQueueDomain();

        $this->assertSame(2, $queue->lookups);
        $this->assertSame(array(1, 2), $this->putBack($pdo));
    }

    public function testADomainIsMarkedReadyAndImportedInOneTransaction()
    {
        // The fee-change lock's name includes the database's name, which the app always defines
        if (!defined('DB_NAME')) define('DB_NAME', 'domainmod_test');
        $pdo = $this->importDatabase();
        $this->queue($pdo, array('', '2030-01-01', array('ns1.example.com'), '0', '1'))->processQueueDomain();

        $begin = array_search('BEGIN', $pdo->events, true);
        $commit = array_search('COMMIT', $pdo->events, true);
        $this->assertNotFalse($begin);
        $this->assertNotFalse($commit);
        foreach (array(self::READY, 'INSERT INTO domains', 'INSERT INTO domain_field_data', self::FINISHED) as $text) {
            $position = $this->first($pdo->events, $text);
            $this->assertNotNull($position, $text);
            $this->assertGreaterThan($begin, $position, $text);
            $this->assertLessThan($commit, $position, $text);
        }
        $this->assertNotContains('ROLLBACK', $pdo->events);
    }

    // Before, a run that died after a domain was marked ready left it where no run looks, and a failure part-way
    // through the import left the domain imported without its custom field row
    public function testAFailedImportIsRolledBackAndTheDomainPutBack()
    {
        $pdo = $this->importDatabase();
        $queue = $this->queue($pdo, array('', '2030-01-01', array('ns1.example.com'), '0', '1'));
        $queue->maint = $this->createStub(Maintenance::class);
        $queue->maint->method('updateDomainFee')->willThrowException(new \RuntimeException('Lost the connection'));

        $queue->processQueueDomain();

        $rollback = array_search('ROLLBACK', $pdo->events, true);
        $this->assertNotFalse($rollback);
        $this->assertNotContains('COMMIT', $pdo->events);
        $this->assertLessThan($rollback, $this->first($pdo->events, 'INSERT INTO domain_field_data'));
        $this->assertGreaterThan($rollback, $this->first($pdo->events, self::NOT_PROCESSING));
    }

    public function testClearQueueProcessingRefusesDuringALiveRun()
    {
        $pdo = new FakeDatabase(array("WHERE slug = 'domain-queue'" => '7'));
        $queue = $this->queue($pdo);
        $queue->schedule = $this->createMock(Scheduler::class);
        $queue->schedule->expects($this->once())->method('getTaskLock')->with(7)->willReturn(false);
        $queue->schedule->expects($this->never())->method('releaseTaskLock');

        $this->assertFalse($queue->clearProcessing());
        $this->assertNull($this->firstQuery($pdo, self::RELEASE_LISTS));
        $this->assertNull($this->firstQuery($pdo, self::RELEASE_DOMAINS));
    }

    public function testClearQueueProcessingClearsBothUnderTheLock()
    {
        $pdo = new FakeDatabase(array("WHERE slug = 'domain-queue'" => '7'));
        $queue = $this->queue($pdo);
        $queue->schedule = $this->createMock(Scheduler::class);
        $queue->schedule->expects($this->once())->method('getTaskLock')->with(7)
            ->willReturnCallback(function () use ($pdo) { $pdo->queries[] = 'LOCK'; return true; });
        $queue->schedule->expects($this->once())->method('releaseTaskLock')->with(7)
            ->willReturnCallback(function () use ($pdo) { $pdo->queries[] = 'UNLOCK'; });

        $this->assertIsString($queue->clearProcessing());
        $lock = array_search('LOCK', $pdo->queries, true);
        $unlock = array_search('UNLOCK', $pdo->queries, true);
        foreach (array(self::RELEASE_LISTS, self::RELEASE_DOMAINS) as $text) {
            $position = $this->firstQuery($pdo, $text);
            $this->assertNotNull($position, $text);
            $this->assertGreaterThan($lock, $position, $text);
            $this->assertLessThan($unlock, $position, $text);
        }
    }

    /*
     * A DomainQueue on the fake database. Its registrar lookup returns $registrar_info for every domain (or throws it),
     * and the IP, DNS, category, and hosting steps return IDs without looking anything up.
     */
    private function queue($pdo, $registrar_info = false)
    {
        $queue = new class extends DomainQueue {
            public $registrar_info = false;
            public $lookups = 0;

            public function __construct()
            {
            }

            public function fetchRegistrarInfo($api_registrar_name, $registrar_account_id, $domain)
            {
                $this->lookups++;
                if ($this->registrar_info instanceof \Throwable) throw $this->registrar_info;
                return $this->registrar_info === false ? parent::fetchRegistrarInfo($api_registrar_name,
                    $registrar_account_id, $domain) : $this->registrar_info;
            }

            public function updateIp($queue_domain_id, $domain, $created_by) { return 3; }
            public function updateDnsServers($queue_domain_id, $dns_servers, $created_by) { return 4; }
            public function updateCategory($queue_domain_id, $created_by) { return 5; }
            public function updateHosting($queue_domain_id, $created_by) { return 6; }
        };
        $queue->registrar_info = $registrar_info;
        $queue->deeb = (object) array('cnxx' => $pdo);
        $queue->log = $this->createStub(Log::class);
        $queue->maint = $this->createStub(Maintenance::class);
        $queue->queryB = $this->createStub(QueryBuild::class);
        $queue->system = $this->createStub(System::class);
        $queue->time = $this->createStub(Time::class);
        return $queue;
    }

    /*
     * A fake database that also keeps $events: each statement's SQL when it runs (not when it's prepared), and the
     * transactions, as 'BEGIN', 'COMMIT', and 'ROLLBACK', in order. $executed has each statement that ran, with the
     * values bound to it.
     */
    private function transactionDatabase(array $answers)
    {
        return new class($answers) extends FakeDatabase {
            public $events = array();
            public $executed = array();
            private $in_transaction = false;

            public function query($sql) { $statement = $this->prepare($sql); $this->events[] = $sql; return $statement; }
            public function exec($sql) { $this->events[] = $sql; return 0; }

            // Each statement keeps its own SQL and answer, since the import prepares two before running either
            public function prepare($sql)
            {
                parent::prepare($sql);
                return new class($this, $sql, $this->answer) {
                    private $pdo;
                    private $sql;
                    private $answer;
                    private $values = array();

                    public function __construct($pdo, $sql, $answer)
                    {
                        $this->pdo = $pdo;
                        $this->sql = $sql;
                        $this->answer = $answer;
                    }

                    public function bindValue($name, $value, $type = null) { $this->values[$name] = $value; return true; }
                    public function bindParam($name, &$value, $type = null) { return true; }

                    public function execute($params = null)
                    {
                        $this->pdo->events[] = $this->sql;
                        $this->pdo->executed[] = array('sql' => $this->sql, 'values' => $this->values);
                        return true;
                    }

                    public function fetchColumn() { return $this->answer; }
                    public function fetch($mode = null) { return $this->answer; }
                    public function fetchAll($mode = null) { return is_array($this->answer) ? $this->answer : array(); }
                    public function closeCursor() { return true; }
                };
            }

            public function beginTransaction() { $this->events[] = 'BEGIN'; $this->in_transaction = true; return true; }
            public function commit() { $this->events[] = 'COMMIT'; $this->in_transaction = false; return true; }
            public function rollBack() { $this->events[] = 'ROLLBACK'; $this->in_transaction = false; return true; }
            public function inTransaction() { return $this->in_transaction; }
            public function lastInsertId($name = null) { return '42'; }
        };
    }

    // One domain waiting in the queue, not yet in the main domain table, that imports as domain 42
    private function importDatabase()
    {
        return $this->transactionDatabase(array(
            'FROM domain_queue AS dq' => array($this->row(1, 'Example Registrar')),
            "AND already_in_domains != '1'" => array((object) array('id' => 1, 'owner_id' => 1, 'registrar_id' => 1,
                'account_id' => 1, 'domain' => 'example.com', 'tld' => 'com', 'expiry_date' => '2030-01-01',
                'cat_id' => 5, 'dns_id' => 4, 'ip_id' => 3, 'hosting_id' => 6, 'autorenew' => '1', 'privacy' => '0',
                'created_by' => 1, 'insert_time' => '2026-01-01 00:00:00')),
            'AND creation_type_id = :creation_type_id' => '42',
            // The import holds the fee-change lock until its transaction ends
            'GET_LOCK(' => '1'));
    }

    private function row($id, $api_registrar_name)
    {
        return (object) array('id' => $id, 'api_registrar_id' => 1, 'domain' => 'example' . $id . '.com',
            'owner_id' => 1, 'registrar_id' => 1, 'account_id' => 1, 'created_by' => 1,
            'api_registrar_name' => $api_registrar_name);
    }

    // The position of the first query with $text in it (whitespace runs read as one space), or null
    private function firstQuery(FakeDatabase $pdo, $text)
    {
        return $this->first($pdo->queries, $text);
    }

    // The position of the first entry in $list with $text in it (whitespace runs read as one space), or null
    private function first(array $list, $text)
    {
        foreach ($list as $index => $sql) {
            if (strpos($this->flat($sql), $text) !== false) return $index;
        }
        return null;
    }

    // The queue domain IDs put back to wait for the next run, in the order their statements ran
    private function putBack($pdo)
    {
        $ids = array();
        foreach ($pdo->executed as $statement) {
            if (strpos($this->flat($statement['sql']), self::NOT_PROCESSING) !== false) {
                $ids[] = $statement['values']['queue_domain_id'];
            }
        }
        return $ids;
    }

    private function flat($sql)
    {
        return trim(preg_replace('/\s+/', ' ', $sql));
    }

} //@formatter:on
