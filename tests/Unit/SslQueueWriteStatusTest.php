<?php
/**
 * /tests/Unit/SslQueueWriteStatusTest.php
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
use DomainMOD\Sanitize;
use DomainMOD\SslQueue;
use DomainMOD\Tests\Support\Instance;
use DomainMOD\Tests\Support\RecordingPdo;
use DomainMOD\Time;
use PHPUnit\Framework\TestCase;

// The certificate dates writeStatus() saves, here both past 2038, which are floats on 32-bit PHP
class SslQueueWriteStatusTest extends TestCase
{

    public function testTheSavedDatesAreTheCertificatesOwn()
    {
        $pdo = new RecordingPdo();
        $database = Instance::withoutConstructor(Database::class, array('cnxx' => $pdo));
        $queue = Instance::withoutConstructor(SslQueue::class, array('deeb' => $database, 'sanitize' => new Sanitize(),
            'time' => new Time()));
        $details = array('host' => 'www.example.com', 'fingerprint' => str_repeat('a', 64), 'issuer' => 'Example CA',
            'names' => array('www.example.com'), 'valid_from' => 2572858791, 'valid_to' => 2572945191, 'trusted' => true);

        $queue->writeStatus(7, $details, 'ok', true);

        $params = $pdo->log[0]['params'];
        $this->assertSame('2051-07-13 10:59:51', $params['valid_from']);
        $this->assertSame('2051-07-14 10:59:51', $params['valid_to']);
    }

} //@formatter:on
