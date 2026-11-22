<?php
/**
 * /tests/Unit/SslQueueNoDomainTest.php
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
 * How the SSL Queue finds certificates with no domain by name: noDomainCandidates() reads ssl_certs alone (never
 * domains, and with no lock), and compares each name in its ASCII form, so a name typed with a Unicode full stop or in
 * Unicode matches its host. noteRevisions() notes those candidates for a row with no domain without reading any
 * domain's certificates.
 */
class SslQueueNoDomainTest extends TestCase
{

    private function certificates()
    {
        return array(
            (object) array('id' => 3, 'name' => 'shop。münchen.de', 'active' => '1'),
            (object) array('id' => 5, 'name' => 'status.example.org', 'active' => '1'),
            (object) array('id' => 8, 'name' => 'Shop.Example.net', 'active' => '1'),
            (object) array('id' => 9, 'name' => 'not a hostname', 'active' => '1'),
            (object) array('id' => 12, 'name' => 'shop.example.net', 'active' => '1'));
    }

    // The certificates' query is answered row by row only, so reading all its rows at once finds nothing
    private function database(array $answers)
    {
        return new RowByRowDatabase($answers, array('FROM ssl_certs'));
    }

    private function queue(FakeDatabase $pdo)
    {
        $database = Instance::withoutConstructor(Database::class, array('cnxx' => $pdo));

        return Instance::withoutConstructor(SslQueue::class, array('deeb' => $database));
    }

    private function call($queue, $method, ...$arguments)
    {
        return (new \ReflectionMethod(SslQueue::class, $method))->invoke($queue, ...$arguments);
    }

    public function testCandidatesAreMatchedInTheirAsciiFormLowestIdFirst()
    {
        $pdo = $this->database(array('FROM ssl_certs' => $this->certificates()));
        $queue = $this->queue($pdo);

        $this->assertSame(array(8 => 'Shop.Example.net', 12 => 'shop.example.net'),
            $this->call($queue, 'noDomainCandidates', array('shop.example.net')));
        $this->assertSame(array(3 => 'shop。münchen.de'), $this->call($queue, 'noDomainCandidates', array('shop.xn--mnchen-3ya.de')));
        $this->assertSame(array(5 => 'status.example.org', 8 => 'Shop.Example.net', 12 => 'shop.example.net'),
            $this->call($queue, 'noDomainCandidates', array('status.example.org', 'shop.example.net', 'not a hostname')));
        $this->assertSame(array(), $this->call($queue, 'noDomainCandidates', array('example.net')));
    }

    public function testTheReadIsOnSslCertsAloneWithoutALock()
    {
        $pdo = $this->database(array('FROM ssl_certs' => $this->certificates()));
        $this->call($this->queue($pdo), 'noDomainCandidates', array('shop.example.net'));

        $this->assertCount(1, $pdo->queries);
        $sql = $pdo->queries[0];
        $this->assertStringContainsString("WHERE domain_id = '0'", $sql);
        $this->assertStringNotContainsString('domains', $sql);
        $this->assertStringNotContainsString('FOR UPDATE', $sql);
        $this->assertStringContainsString('ORDER BY id', $sql);
    }

    public function testARowWithNoDomainNotesOnlyTheCandidates()
    {
        $pdo = $this->database(array('FROM ssl_certs' => $this->certificates(), 'FROM ssl_cert_status' => array(12 => '4')));
        $row = (object) array('host' => 'shop.example.net', 'exact_domain' => 0, 'domain_id' => 0);

        // a candidate with no status row is noted with a null revision
        $this->assertSame(array(8 => array(null, 'Shop.Example.net'), 12 => array(4, 'shop.example.net')),
            $this->call($this->queue($pdo), 'noteRevisions', $row));
        $this->assertSame(array(), $pdo->queriesWith('c.domain_id = :domain_id'));
    }

    public function testATickBoxRowNotesItsWwwNameToo()
    {
        $pdo = $this->database(array('FROM ssl_certs' => array((object) array('id' => 2, 'name' => 'www.example.net', 'active' => '1'),
            (object) array('id' => 6, 'name' => 'example.net', 'active' => '1'))));
        $row = (object) array('host' => 'example.net', 'exact_domain' => 1, 'domain_id' => 0);

        $this->assertSame(array(2 => array(null, 'www.example.net'), 6 => array(null, 'example.net')),
            $this->call($this->queue($pdo), 'noteRevisions', $row));
    }

} //@formatter:on
