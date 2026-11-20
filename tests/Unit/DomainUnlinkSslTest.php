<?php
/**
 * /tests/Unit/DomainUnlinkSslTest.php
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

use DomainMOD\Domain;
use DomainMOD\Tests\Support\Instance;
use DomainMOD\Tests\Support\RecordingPdo;
use DomainMOD\Time;
use PHPUnit\Framework\TestCase;

/*
 * Domain::unlinkSslCertificates(), which the domain deletes (the domain edit page and the Bulk Updater's Delete Domains)
 * use to keep a deleted domain's SSL certificates, without a domain. It runs on a RecordingPdo, so no database is
 * needed.
 */
class DomainUnlinkSslTest extends TestCase
{

    private function domain(RecordingPdo $pdo)
    {
        return Instance::withoutConstructor(Domain::class, array('deeb' => (object) array('cnxx' => $pdo),
            'time' => new Time()));
    }

    public function testTheCertificatesAreKeptWithNoDomainAndCounted()
    {
        // Three changed rows, so a hard-coded count fails
        $pdo = new RecordingPdo(array('UPDATE ssl_certs' => array(array(array('id' => 1), array('id' => 2), array('id' => 3)))));

        $count = $this->domain($pdo)->unlinkSslCertificates(array(7, '12', 30));

        $this->assertSame(3, $count);
        $this->assertCount(1, $pdo->log);
        $statement = $pdo->statementsWith('UPDATE ssl_certs')[0];
        $this->assertSame('UPDATE ssl_certs SET domain_id = 0, update_time = :update_time WHERE domain_id IN '
            . '(:domain_id0, :domain_id1, :domain_id2)', trim(preg_replace('/\s+/', ' ', $statement['sql'])));
        // Each id is bound as a whole number, and the time as a stamp
        $this->assertSame(array(7, 12, 30), array($statement['params']['domain_id0'], $statement['params']['domain_id1'],
            $statement['params']['domain_id2']));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $statement['params']['update_time']);
        $this->assertCount(4, $statement['params']);
    }

    public function testOneDomainIsOnePlaceholder()
    {
        $pdo = new RecordingPdo(array('UPDATE ssl_certs' => array(array())));

        $this->assertSame(0, $this->domain($pdo)->unlinkSslCertificates(array(5)));
        $statement = $pdo->statementsWith('UPDATE ssl_certs')[0];
        $this->assertStringContainsString('WHERE domain_id IN (:domain_id0)', $statement['sql']);
        $this->assertSame(5, $statement['params']['domain_id0']);
    }

    public function testNoDomainsRunsNothing()
    {
        $pdo = new RecordingPdo();

        $this->assertSame(0, $this->domain($pdo)->unlinkSslCertificates(array()));
        $this->assertSame(array(), $pdo->log);
    }

} //@formatter:on
