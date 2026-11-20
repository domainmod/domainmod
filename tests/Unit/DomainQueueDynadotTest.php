<?php
/**
 * /tests/Unit/DomainQueueDynadotTest.php
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
use DomainMOD\Dynadot;
use DomainMOD\Log;
use DomainMOD\Tests\Support\FakeDatabase;
use DomainMOD\Time;
use PHPUnit\Framework\TestCase;

/*
 * The Domain Queue's side of Dynadot's RESTful API: rows and lists take turns by their last attempt (rows Dynadot's
 * time budget skipped keep their place), a Dynadot list that fell back to the legacy API is logged after its
 * transaction ends, and Dynadot lists leave time for Dynadot details already waiting. Before the 4.27.0 upgrade adds
 * domain_queue_list.last_attempt, lists keep their old order and aren't stamped.
 */
class DomainQueueDynadotTest extends TestCase
{
    const STAMP_DOMAIN = 'UPDATE domain_queue SET last_attempt = :last_attempt WHERE id = :queue_domain_id';
    const STAMP_LIST = 'UPDATE domain_queue_list SET last_attempt = :last_attempt WHERE id = :id';
    const COLUMN_CHECK = "AND `TABLE_NAME` = 'domain_queue_list'";

    protected function setUp(): void
    {
        (new \ReflectionProperty(DomainQueue::class, 'list_last_attempt'))->setValue(null, null);
        Dynadot::$last_call_skipped = false;
        Dynadot::$last_list_sent = false;
        Dynadot::$last_list_fell_back = null;
        Dynadot::$list_reserve = 0;
    }

    public function testQueuedDomainsGoUntriedFirstThenLongestWaiting()
    {
        $pdo = new FakeDatabase();
        $this->queue($pdo)->getQueueDomain();

        $this->assertStringContainsString("ORDER BY (dq.last_attempt > '1970-01-01 00:00:00'), dq.last_attempt, dq.id", $this->flat($pdo->queries[0]));
    }

    public function testListsTakeTurnsOnceTheColumnIsThere()
    {
        $pdo = new FakeDatabase(array(self::COLUMN_CHECK => '1'));
        $this->queue($pdo)->getQueueList();
        $this->assertStringContainsString("ORDER BY (dql.last_attempt > '1970-01-01 00:00:00'), dql.last_attempt, dql.insert_time DESC", $this->flat(end($pdo->queries)));

        // a 4.26.0 database keeps the newest-first order
        $this->setUp();
        $pdo = new FakeDatabase(array(self::COLUMN_CHECK => '0'));
        $this->queue($pdo)->getQueueList();
        $this->assertStringEndsWith('ORDER BY dql.insert_time DESC', $this->flat(end($pdo->queries)));
    }

    public function testADomainIsStampedOnlyWhenARequestWasSent()
    {
        $cases = array(
            array('Dynadot', false, true),
            array('Dynadot', true, false),   // the budget stopped the call before it sent anything
            array('Namecheap', true, true),  // only Dynadot ever skips, so a leftover flag doesn't matter
        );

        foreach ($cases as list($registrar, $skipped, $stamped)) {

            $this->setUp();
            $pdo = $this->database(array('FROM domain_queue AS dq' => array($this->row(1, $registrar))));
            $queue = $this->queue($pdo);
            $queue->registrar_info = function () use ($skipped) {
                Dynadot::$last_call_skipped = $skipped;
                return array('', '', array(), '', '');
            };

            $queue->processQueueDomain();

            $this->assertSame($stamped, $this->ran($pdo, self::STAMP_DOMAIN) === 1, $registrar . ($skipped ? ' skipped' : ''));

        }
    }

    // a request that reached the registrar and then failed with an error still counts as an attempt
    public function testADomainWhoseLookupThrowsIsStillStamped()
    {
        $pdo = $this->database(array('FROM domain_queue AS dq' => array($this->row(1, 'Dynadot'))));
        $queue = $this->queue($pdo);
        $queue->registrar_info = function () { throw new \RuntimeException('The log insert failed'); };

        $queue->processQueueDomain();

        $this->assertSame(1, $this->ran($pdo, self::STAMP_DOMAIN));
    }

    public function testAnUnknownRegistrarIsNotStamped()
    {
        $pdo = $this->database(array('FROM domain_queue AS dq' => array($this->row(1, 'No Such Registrar'))));
        $queue = $this->queue($pdo);
        $queue->registrar_info = function () { return false; };

        $queue->processQueueDomain();

        $this->assertSame(0, $this->ran($pdo, self::STAMP_DOMAIN));
    }

    public function testAFailedStampDoesNotStopTheRun()
    {
        $pdo = $this->database(array('SET last_attempt' => new \RuntimeException('Lost the connection'),
                                     'FROM domain_queue AS dq' => array($this->row(1, 'Dynadot'), $this->row(2, 'Dynadot'))));
        $queue = $this->queue($pdo);
        $queue->registrar_info = function () { return array('', '', array(), '', ''); };

        $queue->processQueueDomain();

        $this->assertSame(2, $queue->lookups);
        $this->assertCount(2, $pdo->queriesWith('SET last_attempt'));
    }

    public function testARolledBackDynadotListIsStillStampedAndItsFallbackLogged()
    {
        $pdo = $this->database(array(self::COLUMN_CHECK => '1', 'FROM domain_queue_list AS dql' => array($this->row(5, 'Dynadot'))));
        $queue = $this->queue($pdo);
        $queue->list_result = function () {
            Dynadot::$last_list_sent = true;
            Dynadot::$last_list_fell_back = 400;
            throw new \RuntimeException('The import failed');
        };
        $logged = array();
        $queue->log->method('addEntry')->willReturnCallback(function ($level, $message, $extra) use (&$logged, $pdo) {
            $logged[] = array($level, $message, $extra, count($pdo->events));
        });

        $queue->processQueueList();

        $rollback = array_search('ROLLBACK', $pdo->events, true);
        $this->assertNotFalse($rollback);
        $this->assertGreaterThan($rollback, $this->position($pdo, self::STAMP_LIST));
        $this->assertCount(1, $logged);
        $this->assertSame(array('warning', Dynadot::LIST_FALLBACK_MESSAGE, array('List ID' => 5, 'HTTP Status' => 400)), array_slice($logged[0], 0, 3));
        $this->assertGreaterThan($rollback, $logged[0][3]);
    }

    // a list using an account's earlier fallback that couldn't send anything this time isn't reported again
    public function testAFallbackListThatSentNothingIsNotLogged()
    {
        $pdo = $this->database(array(self::COLUMN_CHECK => '1', 'FROM domain_queue_list AS dql' => array($this->row(5, 'Dynadot'))));
        $queue = $this->queue($pdo);
        $queue->list_result = function () {
            Dynadot::$last_list_fell_back = 400;
            return array('', '');
        };
        $queue->log->expects($this->never())->method('addEntry');

        $queue->processQueueList();
    }

    public function testAListIsStampedOnlyWhenItsFirstRequestWasSent()
    {
        $cases = array(
            array('Dynadot', true, '1', 1),
            array('Dynadot', false, '1', 0),  // the time limit stopped it before anything was sent
            array('Namecheap', false, '1', 1),
            array('Dynadot', true, '0', 0),   // a 4.26.0 database has no column to stamp
        );

        foreach ($cases as list($registrar, $sent, $column, $stamps)) {

            $this->setUp();
            $pdo = $this->database(array(self::COLUMN_CHECK => $column, 'FROM domain_queue_list AS dql' => array($this->row(5, $registrar))));
            $queue = $this->queue($pdo);
            $queue->list_result = function () use ($sent) {
                Dynadot::$last_list_sent = $sent;
                return array('', '');
            };

            $queue->processQueueList();

            $this->assertSame($stamps, $this->ran($pdo, self::STAMP_LIST), $registrar . ' ' . var_export($sent, true) . ' ' . $column);

        }
    }

    public function testDynadotListsLeaveTimeForDetailsAlreadyWaiting()
    {
        foreach (array('3' => DomainQueue::DYNADOT_LIST_RESERVE, '0' => 0) as $waiting => $reserve) {

            $this->setUp();
            $pdo = $this->database(array('FROM domain_queue AS dq, api_registrars AS ar' => (string) $waiting,
                                         'FROM domain_queue_list AS dql' => array($this->row(5, 'Dynadot'))));
            $queue = $this->queue($pdo);
            $seen = null;
            $queue->list_result = function () use (&$seen) {
                $seen = Dynadot::$list_reserve;
                return array('', '');
            };

            $queue->processQueueList();

            $this->assertSame($reserve, $seen);
            $this->assertSame(0, Dynadot::$list_reserve);

        }
    }

    /*
     * A DomainQueue on the fake database whose registrar calls are callables ($registrar_info for details, $list_result
     * for lists), with the steps after a successful lookup never reached.
     */
    private function queue($pdo)
    {
        $queue = new class extends DomainQueue {
            public $registrar_info;
            public $list_result;
            public $lookups = 0;

            public function __construct()
            {
            }

            public function fetchRegistrarInfo($api_registrar_name, $registrar_account_id, $domain)
            {
                $this->lookups++;
                return ($this->registrar_info)();
            }

            protected function getRegistrarDomainList($row)
            {
                return ($this->list_result)();
            }

            public function copyToHistoryList() { }
            public function copyToHistoryDomain() { }
            public function processRdapQueue() { }
        };
        $queue->deeb = (object) array('cnxx' => $pdo);
        $queue->log = $this->createMock(Log::class);
        $queue->time = $this->createStub(Time::class);
        return $queue;
    }

    // A fake database that also keeps $events: each statement's SQL when it runs (not when it's prepared), and the
    // transactions ('BEGIN', 'COMMIT', and 'ROLLBACK'), in order
    private function database(array $answers)
    {
        return new class($answers) extends FakeDatabase {
            public $events = array();
            private $in_transaction = false;
            private $prepared = '';

            public function query($sql)
            {
                $statement = parent::prepare($sql);
                $this->events[] = $sql;
                return $statement;
            }

            public function prepare($sql)
            {
                $statement = parent::prepare($sql);
                $this->prepared = $sql;
                return $statement;
            }

            public function execute($params = null)
            {
                $this->events[] = $this->prepared;
                return true;
            }

            public function beginTransaction() { $this->events[] = 'BEGIN'; $this->in_transaction = true; return true; }
            public function commit() { $this->events[] = 'COMMIT'; $this->in_transaction = false; return true; }
            public function rollBack() { $this->events[] = 'ROLLBACK'; $this->in_transaction = false; return true; }
            public function inTransaction() { return $this->in_transaction; }
        };
    }

    private function row($id, $api_registrar_name)
    {
        return (object) array('id' => $id, 'api_registrar_id' => 1, 'domain' => 'example' . $id . '.com',
            'owner_id' => 1, 'registrar_id' => 1, 'account_id' => 1, 'created_by' => 1,
            'api_registrar_name' => $api_registrar_name);
    }

    // How many statements with $text in them ran
    private function ran($pdo, $text)
    {
        return count(array_filter($pdo->events, function ($sql) use ($text) {
            return strpos($this->flat($sql), $text) !== false;
        }));
    }

    private function position($pdo, $text)
    {
        foreach ($pdo->events as $index => $sql) {
            if (strpos($this->flat($sql), $text) !== false) return $index;
        }
        return null;
    }

    private function flat($sql)
    {
        return trim(preg_replace('/\s+/', ' ', $sql));
    }

} //@formatter:on
