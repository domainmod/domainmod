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

    // The Domain filter keeps NO_DOMAIN (-1) and positive ids, and any other negative number is no filter
    public function testNormalizeStateKeepsNoDomainAndDropsOtherNegatives()
    {
        $this->assertSame(-1, SslList::NO_DOMAIN);
        $this->assertSame(-1, SslList::normalizeState(array('did' => '-1'))['did']);
        $this->assertSame(0, SslList::normalizeState(array('did' => '-2'))['did']);
        $this->assertSame(0, SslList::normalizeState(array('did' => '-100'))['did']);
        $this->assertSame(5, SslList::normalizeState(array('did' => '5'))['did']);
        $this->assertSame(0, SslList::normalizeState(array('did' => ''))['did']);
        $this->assertSame(0, SslList::checkedDomainId('-3'));
        $this->assertSame(-1, SslList::checkedDomainId(-1));
        $this->assertSame(12, SslList::checkedDomainId('12'));
    }

    /*
     * The Domain filter's condition is written from sslc alone (and a subquery on domains AS nd), so it works in the
     * STATUS menu's query, which doesn't join the domains: nothing for 0, an existing domain for an id, and no domain
     * (domain_id 0, or a domain that no longer exists) for NO_DOMAIN
     */
    public function testDomainCondition()
    {
        $this->assertSame('', SslList::domainCondition(0));
        $this->assertSame('', SslList::domainCondition(-2));
        $this->assertSame(" AND NOT EXISTS (SELECT 1 FROM domains AS nd WHERE nd.id = sslc.domain_id) ",
            SslList::domainCondition(SslList::NO_DOMAIN));
        $this->assertSame(" AND sslc.domain_id = '7' AND EXISTS (SELECT 1 FROM domains AS nd WHERE nd.id = sslc.domain_id) ",
            SslList::domainCondition(7));
        $this->assertSame(" AND sslc.domain_id = '7' AND EXISTS (SELECT 1 FROM domains AS nd WHERE nd.id = sslc.domain_id) ",
            SslList::domainCondition('7abc'));

        foreach (array(SslList::NO_DOMAIN, 7) as $did) {
            $condition = SslList::domainCondition($did);
            preg_match_all('/\b([a-z]+)\.[a-z_]+/', $condition, $aliases);
            $this->assertSame(array('nd', 'sslc'), self::sorted(array_unique($aliases[1])), (string) $did);
            $this->assertStringNotContainsString(' d.', $condition);
        }
    }

    // The list's filters give NO_DOMAIN's condition for did=-1
    public function testFilterPartsForNoDomain()
    {
        $parts = $this->sslList()->filterParts(SslList::normalizeState(array('did' => '-1')), '', 'YYYY-MM-DD', 'YYYY-MM-DD');
        $this->assertSame(SslList::domainCondition(SslList::NO_DOMAIN), $parts['did_string']);
        $this->assertStringContainsString($parts['did_string'], SslList::filterSql($parts));

        $parts = $this->sslList()->filterParts(SslList::normalizeState(array('did' => '-2')), '', 'YYYY-MM-DD', 'YYYY-MM-DD');
        $this->assertSame('', $parts['did_string']);
    }

    // The domain is a LEFT JOIN in both join modes, so a certificate with no domain stays in the list
    public function testTheListLeftJoinsTheDomain()
    {
        $list = new class extends SslList {
            public $fixed = false;

            public function __construct()
            {
                $this->user_id = 1;
                $this->field_columns = '';
            }

            public function fixedJoinOrder()
            {
                return $this->fixed;
            }
        };

        $from = $list->fromSql();
        $this->assertStringContainsString('ssl_certs AS sslc LEFT JOIN domains AS d ON d.id = sslc.domain_id', $from);
        $this->assertStringNotContainsString('sslc.domain_id = d.id', $from);

        $list->fixed = true;
        $from = $list->fromSql();
        $this->assertStringContainsString(' LEFT JOIN domains AS d FORCE INDEX (PRIMARY) ON d.id = sslc.domain_id ', $from);
        $this->assertStringNotContainsString('sslc.domain_id = d.id', $from);
        $this->assertStringNotContainsString('STRAIGHT_JOIN domains', $from);
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
        $this->assertSame(" AND sslc.domain_id = '2' AND EXISTS (SELECT 1 FROM domains AS nd WHERE nd.id = sslc.domain_id) ",
            $parts['did_string']);
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

    private static function sorted(array $values)
    {
        sort($values);
        return array_values($values);
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
        return new SslList($pdo, 1, array());
    }

} //@formatter:on
