<?php
/**
 * /tests/Unit/SslQueueLiveMatchTest.php
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
use DomainMOD\SslQueue;
use DomainMOD\Tests\Support\FakeDatabase;
use DomainMOD\Tests\Support\Instance;
use DomainMOD\Tests\Support\RowByRowDatabase;
use PHPUnit\Framework\TestCase;

/*
 * Which existing certificates a saved SSL Queue lookup is matched to. A name match only takes a live record (Active or
 * Pending, as the SSL Certificates list's Live filter has it): an expired record with the same name never stops a new
 * certificate being added, and is never written over. The very certificate already recorded (the same fingerprint) is
 * still matched whatever the record's status, and isn't changed, so a certificate imported as Expired isn't added
 * twice.
 */
class SslQueueLiveMatchTest extends TestCase
{

    private function queue($pdo)
    {
        $database = Instance::withoutConstructor(Database::class, array('cnxx' => $pdo));

        return Instance::withoutConstructor(SslQueue::class, array('deeb' => $database));
    }

    private function call($queue, $method, ...$arguments)
    {
        return (new \ReflectionMethod(SslQueue::class, $method))->invoke($queue, ...$arguments);
    }

    /*
     * A database whose single-row locks (lockRow()) are answered by table and bound id from $locked, array(table =>
     * array(id => row)), and whose other queries are answered as FakeDatabase answers them
     */
    private function database(array $answers, array $locked = array())
    {
        return new class($answers, $locked) extends FakeDatabase {

            private $locked;
            private $sql = '';
            private $id = null;

            public function __construct(array $answers, array $locked)
            {
                parent::__construct($answers);
                $this->locked = $locked;
            }

            public function prepare($sql)
            {
                $this->sql = $sql;
                $this->id = null;
                return parent::prepare($sql);
            }

            public function bindValue($name, $value, $type = null)
            {
                if ($name === 'id') $this->id = $value;
                return true;
            }

            public function fetch($mode = null)
            {
                foreach ($this->locked as $table => $rows) {
                    if (strpos($this->sql, 'FROM `' . $table . '`') !== false) return $rows[$this->id] ?? false;
                }
                return parent::fetch($mode);
            }

        };
    }

    private function certificate($id, $name, $active, $revision = null, $fingerprint = null)
    {
        return (object) array('id' => $id, 'name' => $name, 'active' => $active, 'revision' => $revision,
            'fingerprint' => $fingerprint);
    }

    private function details($host = 'gregchetcuti.com', $fingerprint = 'AA:BB:CC')
    {
        return array('host' => $host, 'fingerprint' => $fingerprint);
    }

    public function testOnlyActiveAndPendingStatusesAreLive()
    {
        foreach (array(1, 2, 3, 4, 5, 6, 7, 8, 9, '1', '3') as $active) $this->assertTrue(SslQueue::isLive($active));
        foreach (array(0, '0', 10, '10', 11, -1, null, '') as $active) $this->assertFalse(SslQueue::isLive($active));
    }

    public function testADomainsExpiredRecordsWithTheSameNameAreLeftOut()
    {
        $pdo = $this->database(array('LEFT JOIN ssl_cert_status' => array(
            $this->certificate(4, 'gregchetcuti.com', '0', 2, 'OLD:ONE'),
            $this->certificate(7, 'gregchetcuti.com', '0'),
            $this->certificate(9, 'GregChetcuti.com', '1', 5, 'LIVE:ONE'),
            $this->certificate(11, 'gregchetcuti.com', '3'),
            $this->certificate(12, 'www.gregchetcuti.com', '1'))));

        $matches = $this->call($this->queue($pdo), 'certificatesNamed', 12, array('gregchetcuti.com'), true);

        $this->assertSame(array(9, 11), array_map(function ($certificate) {
            return (int) $certificate->id;
        }, $matches));
        $this->assertStringContainsString('c.active', $pdo->queries[0]);
        // the lock still covers every certificate of the domain, so a record's status can't change before the save
        $this->assertStringContainsString('FOR UPDATE', $pdo->queries[0]);
        $this->assertStringNotContainsString('c.active IN', $pdo->queries[0]);
    }

    public function testADomainWithOnlyExpiredRecordsHasNoNameMatch()
    {
        $pdo = $this->database(array('LEFT JOIN ssl_cert_status' => array(
            $this->certificate(4, 'gregchetcuti.com', '0'), $this->certificate(7, 'gregchetcuti.com', '0'))));

        $this->assertSame(array(), $this->call($this->queue($pdo), 'certificatesNamed', 12, array('gregchetcuti.com'), false));
    }

    public function testCandidatesWithNoDomainAreLiveOnlyUnlessAnyStatusIsAsked()
    {
        $rows = array((object) array('id' => 3, 'name' => 'chetcuti.com', 'active' => '0'),
            (object) array('id' => 5, 'name' => 'chetcuti.com', 'active' => '1'),
            (object) array('id' => 8, 'name' => 'chetcuti.com', 'active' => '10'));

        $pdo = new RowByRowDatabase(array('FROM ssl_certs' => $rows), array('FROM ssl_certs'));
        $this->assertSame(array(5 => 'chetcuti.com'), $this->call($this->queue($pdo), 'noDomainCandidates', array('chetcuti.com')));
        $this->assertStringContainsString('active', $pdo->queries[0]);

        $pdo = new RowByRowDatabase(array('FROM ssl_certs' => $rows), array('FROM ssl_certs'));
        $this->assertSame(array(3 => 'chetcuti.com', 5 => 'chetcuti.com', 8 => 'chetcuti.com'),
            $this->call($this->queue($pdo), 'noDomainCandidates', array('chetcuti.com'), true));
    }

    // Every status is noted, so a record made live while the lookup runs still has its status row updated
    public function testARowNotesItsRecordsOfEveryStatus()
    {
        $pdo = $this->database(array('LEFT JOIN ssl_cert_status' => array(
            $this->certificate(4, 'gregchetcuti.com', '0', 2), $this->certificate(9, 'gregchetcuti.com', '1', 5)),
            "WHERE domain_id = '0'" => false));
        $row = (object) array('host' => 'gregchetcuti.com', 'exact_domain' => 0, 'domain_id' => 12);

        $this->assertSame(array(4 => array(2, 'gregchetcuti.com'), 9 => array(5, 'gregchetcuti.com')),
            $this->call($this->queue($pdo), 'noteRevisions', $row));

        $pdo = new RowByRowDatabase(array('FROM ssl_certs' => array((object) array('id' => 3, 'name' => 'chetcuti.com', 'active' => '0')),
            'FROM ssl_cert_status' => array(3 => '7')), array('FROM ssl_certs'));
        $row = (object) array('host' => 'chetcuti.com', 'exact_domain' => 0, 'domain_id' => 0);

        $this->assertSame(array(3 => array(7, 'chetcuti.com')), $this->call($this->queue($pdo), 'noteRevisions', $row));
    }

    // Greg's case (2026-11-21): two expired records with the host's name, so the new certificate is added
    public function testExpiredRecordsWithTheSameNameDontStopTheImport()
    {
        $pdo = $this->database(array('LEFT JOIN ssl_cert_status' => array(
            $this->certificate(4, 'gregchetcuti.com', '0'), $this->certificate(7, 'gregchetcuti.com', '0')),
            'AND s.fingerprint = :fingerprint' => array()));
        $row = (object) array('domain_id' => 12);
        $domain = (object) array('domain' => 'gregchetcuti.com');

        $this->assertSame(array(0, null, null),
            $this->call($this->queue($pdo), 'existingMatch', $row, $this->details(), $domain, array()));
    }

    public function testALiveRecordWithTheSameNameIsStillMatched()
    {
        $pdo = $this->database(array('LEFT JOIN ssl_cert_status' => array(
            $this->certificate(4, 'gregchetcuti.com', '0', 1), $this->certificate(9, 'gregchetcuti.com', '1', 5))));
        $row = (object) array('domain_id' => 12);
        $domain = (object) array('domain' => 'gregchetcuti.com');

        $this->assertSame(array(9, array(5, 'gregchetcuti.com'), null),
            $this->call($this->queue($pdo), 'existingMatch', $row, $this->details(), $domain, array()));
        $this->assertSame(array(), $pdo->queriesWith('AND s.fingerprint = :fingerprint'));
    }

    // The very certificate on an expired record is already in, and the record isn't changed (no name match)
    public function testTheSameCertificateOnAnExpiredRecordIsStillMatched()
    {
        $pdo = $this->database(array('LEFT JOIN ssl_cert_status' => array($this->certificate(4, 'gregchetcuti.com', '0', 1, 'AA:BB:CC')),
            'AND s.fingerprint = :fingerprint' => array((object) array('id' => 4, 'name' => 'gregchetcuti.com'))));
        $row = (object) array('domain_id' => 12);
        $domain = (object) array('domain' => 'gregchetcuti.com');

        $this->assertSame(array(4, null, null),
            $this->call($this->queue($pdo), 'existingMatch', $row, $this->details(), $domain, array()));
    }

    // The fingerprint match on the domain isn't limited to the host's own name
    public function testTheSameCertificateUnderAnotherNameOnTheDomainIsStillMatched()
    {
        $pdo = $this->database(array('LEFT JOIN ssl_cert_status' => array($this->certificate(4, 'www.gregchetcuti.com', '1', 1, 'AA:BB:CC')),
            'AND s.fingerprint = :fingerprint' => array((object) array('id' => 4, 'name' => 'www.gregchetcuti.com'))));
        $row = (object) array('domain_id' => 12);
        $domain = (object) array('domain' => 'gregchetcuti.com');

        $this->assertSame(array(4, null, null),
            $this->call($this->queue($pdo), 'existingMatch', $row, $this->details('api.gregchetcuti.com'), $domain, array()));
    }

    public function testAnExpiredCandidateWithNoDomainDoesntStopTheImport()
    {
        $pdo = $this->database(array(), array(
            'ssl_certs' => array(3 => (object) array('id' => 3, 'name' => 'chetcuti.com', 'domain_id' => 0, 'active' => '0')),
            'ssl_cert_status' => array(3 => (object) array('revision' => 2, 'fingerprint' => 'OLD:ONE'))));
        $row = (object) array('domain_id' => 0);

        $this->assertSame(array(0, null, null),
            $this->call($this->queue($pdo), 'existingMatch', $row, $this->details('chetcuti.com'), null, array(3 => 'chetcuti.com')));
    }

    public function testALiveCandidateWithNoDomainIsMatchedBeforeAnExpiredOneHoldingTheCertificate()
    {
        $pdo = $this->database(array(), array(
            'ssl_certs' => array(
                3 => (object) array('id' => 3, 'name' => 'chetcuti.com', 'domain_id' => 0, 'active' => '0'),
                6 => (object) array('id' => 6, 'name' => 'Chetcuti.com', 'domain_id' => 0, 'active' => '1')),
            'ssl_cert_status' => array(3 => (object) array('revision' => 2, 'fingerprint' => 'AA:BB:CC'))));
        $row = (object) array('domain_id' => 0);

        $this->assertSame(array(6, array(null, 'Chetcuti.com'), null),
            $this->call($this->queue($pdo), 'existingMatch', $row, $this->details('chetcuti.com'), null,
                array(3 => 'chetcuti.com', 6 => 'Chetcuti.com')));
    }

    public function testAnExpiredCandidateHoldingTheCertificateIsMatchedWithoutChangingIt()
    {
        $locked = array(
            'ssl_certs' => array(3 => (object) array('id' => 3, 'name' => 'chetcuti.com', 'domain_id' => 0, 'active' => '0')),
            'ssl_cert_status' => array(3 => (object) array('revision' => 2, 'fingerprint' => 'AA:BB:CC')));

        // with no domain, and for a row with a domain (it then shows the record's lack of one)
        $pdo = $this->database(array(), $locked);
        $this->assertSame(array(3, null, null), $this->call($this->queue($pdo), 'existingMatch', (object) array('domain_id' => 0),
            $this->details('chetcuti.com'), null, array(3 => 'chetcuti.com')));

        $pdo = $this->database(array('LEFT JOIN ssl_cert_status' => array(), 'AND s.fingerprint = :fingerprint' => array()), $locked);
        $this->assertSame(array(3, null, array(0, '')), $this->call($this->queue($pdo), 'existingMatch',
            (object) array('domain_id' => 12), $this->details('chetcuti.com'), (object) array('domain' => 'chetcuti.com'),
            array(3 => 'chetcuti.com')));

        // a lookup with no fingerprint never matches an expired record
        $pdo = $this->database(array(), $locked);
        $this->assertSame(array(0, null, null), $this->call($this->queue($pdo), 'existingMatch', (object) array('domain_id' => 0),
            $this->details('chetcuti.com', ''), null, array(3 => 'chetcuti.com')));
    }

    public function testACandidateThatStoppedBeingLiveSinceTheReadIsntMatchedByName()
    {
        $pdo = $this->database(array(), array(
            'ssl_certs' => array(5 => (object) array('id' => 5, 'name' => 'chetcuti.com', 'domain_id' => 0, 'active' => '0'))));

        $this->assertSame(array(0, null, null), $this->call($this->queue($pdo), 'existingMatch', (object) array('domain_id' => 0),
            $this->details('chetcuti.com'), null, array(5 => 'chetcuti.com')));
    }

    public function testTheSaveReadsTheCandidatesOfAnyStatus()
    {
        $source = file_get_contents(__DIR__ . '/../../classes/DomainMOD/SslQueue.php');

        $this->assertStringContainsString("\$candidates = \$this->noDomainCandidates(array(strtolower(Domain::forApi(\$details['host']))), true);", $source);
        $this->assertStringContainsString("\$this->existingMatch(\$row, \$details, \$domain, \$candidates);", $source);
    }

} //@formatter:on
