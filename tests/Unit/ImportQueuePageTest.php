<?php
/**
 * /tests/Unit/ImportQueuePageTest.php
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

use DomainMOD\Database;
use DomainMOD\DomainQueue;
use DomainMOD\SslQueue;
use DomainMOD\System;
use DomainMOD\Tests\Support\FakeDatabase;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\TestCase;

/*
 * The Import Queue page, which shows the Domain Queue and the SSL Queue together. The menu shows its item only while
 * either queue has rows (SslQueue::inMenu()), going by the session flags that the queues' checks set and clear. The
 * page also shows a count beside each heading (waitingCounts()) and the SSL Queue's status text, which the export shares
 * (statusParts() and addedBy()). The flags are kept and put back around each test.
 */
class ImportQueuePageTest extends TestCase
{
    const FLAGS = array('s_domains_in_list_queue', 's_domains_in_queue', 's_ssl_in_queue');

    private $saved = array();

    protected function setUp(): void
    {
        foreach (self::FLAGS as $flag) {
            if (isset($_SESSION) && array_key_exists($flag, $_SESSION)) $this->saved[$flag] = $_SESSION[$flag];
            unset($_SESSION[$flag]);
        }
    }

    protected function tearDown(): void
    {
        foreach (self::FLAGS as $flag) {
            unset($_SESSION[$flag]);
            if (array_key_exists($flag, $this->saved)) $_SESSION[$flag] = $this->saved[$flag];
        }
    }

    public function testTheMenuItemIsHiddenWhileBothQueuesAreEmpty()
    {
        $this->assertFalse(SslQueue::inMenu(array()));
        $this->assertFalse(SslQueue::inMenu(array('s_domains_in_list_queue' => '0', 's_domains_in_queue' => 0, 's_ssl_in_queue' => '')));
    }

    public function testAnyQueueWithRowsShowsTheMenuItem()
    {
        foreach (self::FLAGS as $flag) {
            $this->assertTrue(SslQueue::inMenu(array($flag => '1')), $flag);
            $this->assertTrue(SslQueue::inMenu(array($flag => 1)), $flag);
        }
    }

    // It's the same for everyone: users who can change data no longer always see it, and read-only users see it too
    public function testWhetherTheUserIsReadOnlyMakesNoDifference()
    {
        $this->assertFalse(SslQueue::inMenu(array('s_read_only' => '0')));
        $this->assertFalse(SslQueue::inMenu(array('s_read_only' => '1')));
        $this->assertTrue(SslQueue::inMenu(array('s_read_only' => '0', 's_ssl_in_queue' => '1')));
        $this->assertTrue(SslQueue::inMenu(array('s_read_only' => '1', 's_domains_in_queue' => '1')));
    }

    public function testTheSslQueueCheckSetsTheFlagWhileItHasRows()
    {
        $this->assertSame('1', $this->sslQueue(array('FROM settings' => '4.27.0', 'FROM ssl_queue' => 7))->checkSslQueue());
        $this->assertSame('1', $_SESSION['s_ssl_in_queue']);
        $this->assertTrue(SslQueue::inMenu($_SESSION));
    }

    public function testTheSslQueueCheckClearsTheFlagOnceItIsEmpty()
    {
        $_SESSION['s_ssl_in_queue'] = '1';

        $this->assertSame('0', $this->sslQueue(array('FROM settings' => '4.27.0', 'FROM ssl_queue' => false))->checkSslQueue());
        $this->assertArrayNotHasKey('s_ssl_in_queue', $_SESSION);
        $this->assertFalse(SslQueue::inMenu($_SESSION));
    }

    public function testBeforeTheDatabaseUpdateTheSslQueueIsNeverRead()
    {
        $_SESSION['s_ssl_in_queue'] = '1';
        $pdo = new FakeDatabase(array('FROM settings' => '4.26.0', 'FROM ssl_queue' => 7));

        $this->assertSame('0', $this->sslQueueOn($pdo)->checkSslQueue());
        $this->assertArrayNotHasKey('s_ssl_in_queue', $_SESSION);
        $this->assertSame(array(), $pdo->queriesWith('ssl_queue'));
    }

    public function testTheDomainChecksSetAndClearTheirFlags()
    {
        $queue = $this->domainQueue(array('FROM domain_queue_list' => 3, 'FROM domain_queue' => false));
        $queue->checkListQueue();
        $queue->checkDomainQueue();

        $this->assertSame('1', $_SESSION['s_domains_in_list_queue']);
        $this->assertArrayNotHasKey('s_domains_in_queue', $_SESSION);
        $this->assertTrue(SslQueue::inMenu($_SESSION));

        $queue = $this->domainQueue(array('FROM domain_queue_list' => false, 'FROM domain_queue' => false));
        $queue->checkListQueue();
        $queue->checkDomainQueue();

        $this->assertFalse(SslQueue::inMenu($_SESSION));
    }

    public function testWaitingCountsAddTheDomainListsAndDomainsAndCountTheHosts()
    {
        // FakeDatabase answers with the first match, and "FROM domain_queue" is in both domain queries
        $answers = array('FROM settings' => '4.27.0', 'FROM domain_queue_list' => 2, 'FROM domain_queue' => 5, 'FROM ssl_queue' => 4);

        $this->assertSame(array(7, 4), $this->sslQueue($answers)->waitingCounts());

        $answers['FROM settings'] = '4.26.0';
        $this->assertSame(array(7, 0), $this->sslQueue($answers)->waitingCounts());
    }

    public function testStatusPartsGiveTheLabelFlagAndDetail()
    {
        $queue = Instance::withoutConstructor(SslQueue::class);

        $this->assertSame(array('pending', 'Pending', '', ''), $queue->statusParts($this->row()));
        $this->assertSame(array('waiting', 'Waiting for the Domain', '', 'Waiting for example.com to be added. This is checked on the next run.'),
            $queue->statusParts($this->row(array('wait_for_domain' => 1, 'wait_domain' => 'example.com'))));
        $this->assertSame(array('retrying', 'Retrying', '', 'Attempt 1 of 3 failed. The lookup ran out of time.'),
            $queue->statusParts($this->row(array('attempts' => 1, 'message' => 'timeout'))));
        $this->assertSame(array('imported', 'Imported', 'Not trusted', ''),
            $queue->statusParts($this->row(array('finished' => 1, 'message' => 'untrusted'))));
        $this->assertSame(array('already_in_queue', 'Already in the Queue', '', ''),
            $queue->statusParts($this->row(array('finished' => 1, 'already_in_queue' => 1))));
        $this->assertSame(array('failed', 'Failed', '', 'Nothing answered on port 443.'),
            $queue->statusParts($this->row(array('finished' => 1, 'failed' => 1, 'message' => 'no_connect'))));
    }

    public function testAddedByNamesThePersonOrSaysUnknown()
    {
        $this->assertSame('Jo Smith', SslQueue::addedBy((object) array('first_name' => 'Jo', 'last_name' => 'Smith')));
        $this->assertSame('[unknown]', SslQueue::addedBy((object) array('first_name' => null, 'last_name' => null)));
    }

    // A queue row with the state flags, and $values over them
    private function row($values = array())
    {
        return (object) ($values + array('finished' => 0, 'failed' => 0, 'already_in_queue' => 0, 'already_in_ssl' => 0,
            'processing' => 0, 'wait_for_domain' => 0, 'wait_domain' => '', 'attempts' => 0, 'message' => ''));
    }

    private function sslQueue(array $answers)
    {
        return $this->sslQueueOn(new FakeDatabase($answers));
    }

    private function sslQueueOn(FakeDatabase $pdo)
    {
        $database = Instance::withoutConstructor(Database::class, array('cnxx' => $pdo));
        $system = Instance::withoutConstructor(System::class, array('deeb' => $database));

        return Instance::withoutConstructor(SslQueue::class, array('deeb' => $database, 'system' => $system));
    }

    private function domainQueue(array $answers)
    {
        $database = Instance::withoutConstructor(Database::class, array('cnxx' => new FakeDatabase($answers)));

        return Instance::withoutConstructor(DomainQueue::class, array('deeb' => $database));
    }

} //@formatter:on
