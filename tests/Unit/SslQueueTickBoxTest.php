<?php
/**
 * /tests/Unit/SslQueueTickBoxTest.php
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
use PHPUnit\Framework\TestCase;

/*
 * The domain paste pages' tick box, SslQueue::queueDomainLookups(): a list over its limit of MAX_HOSTS distinct domains
 * adds no rows and says so, the limit counts domains after duplicates are dropped, and a failure past the limit (such
 * as a database error, or no SSL certificate type to use) gives the "lookups couldn't be" warning rather than an
 * exception.
 */
class SslQueueTickBoxTest extends TestCase
{

    private function domains($count)
    {
        $domains = array();
        for ($i = 1; $i <= $count; $i++) $domains[] = 'domain' . $i . '.example';
        return $domains;
    }

    public function testMoreThanTheLimitAddsNoRowsAndSaysSo()
    {
        $queue = Instance::withoutConstructor(SslQueue::class);

        list($success, $warning) = $queue->queueDomainLookups($this->domains(SslQueue::MAX_HOSTS + 1), 1, 1);

        $this->assertStringContainsString('SSL certificate lookups are only added for lists of up to 1,000 domains.', $success);
        $this->assertSame('', $warning);
    }

    public function testTheLimitCountsDistinctDomains()
    {
        // a database object that never connected, so the first query fails at once
        $queue = Instance::withoutConstructor(SslQueue::class, array('deeb' => Instance::withoutConstructor(Database::class)));

        // 1,000 distinct domains, one of them twice in another form, get past the limit (and then fail without a
        // database, which gives the warning rather than an exception)
        $domains = $this->domains(SslQueue::MAX_HOSTS);
        $domains[] = 'DOMAIN1.EXAMPLE.';

        list($success, $warning) = $queue->queueDomainLookups($domains, 1, 1);

        $this->assertSame('', $success);
        $this->assertStringContainsString("The domains were added to the queue, but their SSL certificate lookups couldn't be.", $warning);
    }

    public function testAnEmptyDefaultsTableQueuesNothingAndWarns()
    {
        // no SSL certificate types at all (MIN(id) gives null), while the categories and IP addresses have rows
        $pdo = new FakeDatabase(array('FROM `categories`' => '3', 'FROM `ip_addresses`' => '1'));
        $queue = Instance::withoutConstructor(SslQueue::class, array('deeb' => Instance::withoutConstructor(Database::class, array('cnxx' => $pdo))));

        list($success, $warning) = $queue->queueDomainLookups(array('example.com'), 1, 1);

        $this->assertSame('', $success);
        $this->assertStringContainsString("their SSL certificate lookups couldn't be.", $warning);
        $this->assertCount(3, $pdo->queriesWith('MIN(id)'));
        $this->assertSame(array(), $pdo->queriesWith('ssl_queue'));
        $this->assertSame(array(), $pdo->queriesWith('GET_LOCK'));
    }

} //@formatter:on
