<?php
/**
 * /tests/Unit/SegmentResultsSqlTest.php
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

use DomainMOD\Segment;
use PHPUnit\Framework\TestCase;

// The segment results lists' query lists every domain its summary counts, without a database
class SegmentResultsSqlTest extends TestCase
{
    public function testEachListReadsItsOwnFlag()
    {
        $this->assertStringContainsString("AND inactive = '1'", Segment::resultsSql('inactive'));
        $this->assertStringContainsString("AND filtered = '1'", Segment::resultsSql('filtered'));
    }

    public function testAnUnknownTypeFallsBackToInactive()
    {
        $this->assertSame(Segment::resultsSql('inactive'), Segment::resultsSql("missing' OR '1"));
    }

    public function testOnlyDomainsAreRequired()
    {
        foreach (array('inactive', 'filtered') as $type) {
            $sql = preg_replace('/\s+/', ' ', Segment::resultsSql($type));
            $from = substr($sql, strpos($sql, ' FROM '), strpos($sql, ' WHERE ') - strpos($sql, ' FROM '));

            // A domain without a fee, category, DNS profile, IP address, host, or account (an inner join) was left out
            $this->assertStringStartsWith(' FROM domains AS d LEFT JOIN', $from);
            $this->assertStringNotContainsString(',', $from);
            $this->assertSame(0, preg_match('/(?<!LEFT) JOIN/', $from));
            foreach (array('registrar_accounts AS ra', 'registrars AS r', 'owners AS o', 'fees AS f',
                         'currency_conversions AS cc', 'categories AS cat', 'dns ON', 'ip_addresses AS ip',
                         'hosting AS h') as $table) {
                $this->assertStringContainsString('LEFT JOIN ' . $table, $from);
            }

            // The user's conversion is part of the join, or a domain without a fee would drop out again
            $this->assertStringContainsString('AND cc.user_id = :user_id', $from);
            $this->assertStringNotContainsString('user_id', substr($sql, strpos($sql, ' WHERE ')));
        }
    }
} //@formatter:on
