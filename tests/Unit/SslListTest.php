<?php
/**
 * /tests/Unit/SslListTest.php
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

use DomainMOD\SslList;
use PHPUnit\Framework\TestCase;

// The SSL Certificates list's filters, as SQL fragments, without a database
class SslListTest extends TestCase
{
    public function testNormalizeStateReadsWholeNumbersAndAKnownStatus()
    {
        $state = SslList::normalizeState(array('oid' => '3', 'did' => '12abc', 'sslpid' => array('4'), 'is_active' => '5'));
        $this->assertSame(array('oid' => 3, 'did' => 12, 'sslpid' => 0, 'sslpaid' => 0, 'ssltid' => 0, 'sslipid' => 0,
            'sslpcid' => 0, 'is_active' => '5'), $state);
    }

    public function testNormalizeStateFallsBackToLive()
    {
        foreach (array('', 'live', 'Expired', '11', array('ALL')) as $value) {
            $this->assertSame('LIVE', SslList::normalizeState(array('is_active' => $value))['is_active']);
        }
        $this->assertSame('ALL', SslList::normalizeState(array('is_active' => 'ALL'))['is_active']);
    }

    public function testFilterPartsWithNoFilters()
    {
        $parts = $this->sslList()->filterParts(SslList::normalizeState(array()), '', 'YYYY-MM-DD', 'YYYY-MM-DD');

        $this->assertSame(" AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') ", $parts['is_active_string']);
        foreach (array('oid', 'did', 'sslpid', 'sslpaid', 'ssltid', 'sslipid', 'sslpcid', 'range', 'search') as $key) {
            $this->assertSame('', $parts[$key . '_string'], $key);
        }
        $this->assertSame(" AND sslc.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') ", SslList::filterSql($parts));
    }

    public function testFilterPartsForEveryFilter()
    {
        $state = SslList::normalizeState(array('oid' => '1', 'did' => '2', 'sslpid' => '3', 'sslpaid' => '4',
            'ssltid' => '5', 'sslipid' => '6', 'sslpcid' => '7', 'is_active' => '0'));
        $parts = $this->sslList()->filterParts($state, 'it&#39;s', '2026-01-01', '2026-12-31');

        $this->assertSame(" AND sslc.active = '0' ", $parts['is_active_string']);
        $this->assertSame(" AND o.id = '1' ", $parts['oid_string']);
        $this->assertSame(" AND d.id = '2' ", $parts['did_string']);
        $this->assertSame(" AND sslp.id = '3' ", $parts['sslpid_string']);
        $this->assertSame(" AND sslc.account_id = '4' ", $parts['sslpaid_string']);
        $this->assertSame(" AND sslc.type_id = '5' ", $parts['ssltid_string']);
        $this->assertSame(" AND sslc.ip_id = '6' ", $parts['sslipid_string']);
        $this->assertSame(" AND sslc.cat_id = '7' ", $parts['sslpcid_string']);
        $this->assertSame(" AND (sslc.expiry_date >= [2026-01-01] AND sslc.expiry_date <= [2026-12-31])", $parts['range_string']);
        // The keyword is matched as it is against the encoded name, and decoded against the domain, each quoted by PDO
        $this->assertSame(" AND (sslc.name LIKE [%it&#39;s%] OR sslc.domain_id IN (SELECT kd.id FROM domains AS kd"
            . " WHERE (kd.domain LIKE [%it's%]))) ", $parts['search_string']);

        $this->assertSame($parts['is_active_string'] . $parts['oid_string'] . $parts['did_string'] . $parts['sslpid_string']
            . $parts['sslpaid_string'] . $parts['ssltid_string'] . $parts['sslipid_string'] . $parts['sslpcid_string']
            . $parts['range_string'] . $parts['search_string'], SslList::filterSql($parts));
    }

    // The keyword also matches the certificate's domain, as the page's filter menus match it. The domain is matched
    // against the decoded, lowercased keyword, and also against its normalized (IDN) form when there is one and it's
    // different.
    public function testKeywordAlsoMatchesTheDomain()
    {
        $state = SslList::normalizeState(array());

        $parts = $this->sslList()->filterParts($state, 'Example.COM', 'YYYY-MM-DD', 'YYYY-MM-DD');
        $this->assertSame(" AND (sslc.name LIKE [%Example.COM%] OR sslc.domain_id IN (SELECT kd.id FROM domains AS kd"
            . " WHERE (kd.domain LIKE [%example.com%]))) ",
            $parts['search_string']);

        $parts = $this->sslList()->filterParts($state, 'xn--mnchen-3ya.de', 'YYYY-MM-DD', 'YYYY-MM-DD');
        $this->assertSame(" AND (sslc.name LIKE [%xn--mnchen-3ya.de%] OR sslc.domain_id IN (SELECT kd.id FROM domains AS kd"
            . " WHERE (kd.domain LIKE [%xn--mnchen-3ya.de%] OR kd.domain LIKE [%münchen.de%]))) ", $parts['search_string']);
    }

    public function testOrderEndsWithTheNameAndId()
    {
        $this->assertSame(array('sslc.name ASC', 'sslc.id ASC'), SslList::TIE_BREAK);
    }

    // An SslList with a PDO that never connects and shows what it quotes in square brackets
    private function sslList()
    {
        $pdo = new class extends \PDO {
            public function __construct()
            {
            }

            public function quote(string $string, int $type = \PDO::PARAM_STR): string|false
            {
                return '[' . $string . ']';
            }
        };
        return new SslList($pdo, 1, '');
    }

} //@formatter:on
