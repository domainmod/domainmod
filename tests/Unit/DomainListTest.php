<?php
/**
 * /tests/Unit/DomainListTest.php
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

use DomainMOD\DomainList;
use DomainMOD\Tests\Support\QuotingPdo;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * DomainList's own helpers that build values and SQL text without running a query. The helpers it shares with the SSL
 * Certificates list (page sizes, search words, ordering, and the saved list key) are in ListQueryTest. The queries
 * themselves need a database, so they aren't tested here.
 */
class DomainListTest extends TestCase
{
    public function testNormalizeStateChecksEveryFilter()
    {
        $state = DomainList::normalizeState(array('pcid' => '5abc', 'oid' => '-2', 'rid' => array('3'), 'segid' => '7',
            'tld' => 'XN--P1AI', 'is_active' => '10'));

        $this->assertSame(array('pcid' => 5, 'oid' => -2, 'dnsid' => 0, 'ipid' => 0, 'whid' => 0, 'rid' => 0, 'raid' => 0,
            'segid' => 7, 'tld' => 'рф', 'is_active' => '10'), $state);
    }

    public static function tldFilters()
    {
        return array(
            'none' => array(null, ''),
            'a plain TLD' => array('com', 'com'),
            'upper case' => array('CO.UK', 'co.uk'),
            'punycode' => array('xn--p1ai', 'рф'),
            'invalid' => array('bad tld', ''),
            'HTML' => array('<b>', ''),
            'an array' => array(array('com'), ''),
            // as on the page, "0" skips the check and stays as it is
            'zero' => array('0', '0'),
        );
    }

    #[DataProvider('tldFilters')]
    public function testNormalizeStateTld($tld, $expected)
    {
        $this->assertSame($expected, DomainList::normalizeState(array('tld' => $tld))['tld']);
    }

    public static function statuses()
    {
        return array(array('0', '0'), array('9', '9'), array('ALL', 'ALL'), array('LIVE', 'LIVE'), array('11', 'LIVE'),
            array('live', 'LIVE'), array('', 'LIVE'), array(array('1'), 'LIVE'), array(1, '1'));
    }

    #[DataProvider('statuses')]
    public function testNormalizeStateStatus($status, $expected)
    {
        $this->assertSame($expected, DomainList::normalizeState(array('is_active' => $status))['is_active']);
    }

    public function testKeywordConditionQuotesTheTermAndAddsItsStoredForm()
    {
        $pdo = new QuotingPdo();

        $this->assertSame("(d.domain LIKE '%example%')", DomainList::keywordCondition($pdo, 'd.domain', 'EXAMPLE'));
        $this->assertSame("(d.domain LIKE '%xn--mnchen-3ya.de%' OR d.domain LIKE '%münchen.de%')",
            DomainList::keywordCondition($pdo, 'd.domain', 'xn--mnchen-3ya.de'));
        // a sanitized term is decoded first, then quoted
        $this->assertSame("(d.domain LIKE '%tom\\'s%')", DomainList::keywordCondition($pdo, 'd.domain', 'Tom&#039;s'));
    }

    public function testFilterPartsCastsIdsAndQuotesText()
    {
        $list = new DomainList(new QuotingPdo(), 1, array());
        $state = DomainList::normalizeState(array('rid' => '3', 'raid' => '12', 'tld' => 'de', 'is_active' => '5'));

        $parts = $list->filterParts($state, 'shop', '2026-01-01', "2026-12-31");

        $this->assertSame(" AND d.active = '5' ", $parts['is_active_string']);
        $this->assertSame(" AND r.id = '3' ", $parts['rid_string']);
        $this->assertSame(" AND d.account_id = '12' ", $parts['raid_string']);
        $this->assertSame('', $parts['oid_string']);
        $this->assertSame('', $parts['segid_string']);
        $this->assertSame(" AND (d.expiry_date >= '2026-01-01' AND d.expiry_date <= '2026-12-31')", $parts['range_string']);
        $this->assertSame(" AND d.tld = 'de' ", $parts['tld_string']);
        $this->assertSame(" AND (d.domain LIKE '%shop%') ", $parts['search_string']);
        $this->assertSame(" AND d.active = '5' " . " AND r.id = '3' " . " AND d.account_id = '12' "
            . $parts['range_string'] . $parts['tld_string'] . $parts['search_string'], DomainList::filterSql($parts));
    }

    public function testFilterPartsMapsEachIdFilterToItsColumn()
    {
        $list = new DomainList(new QuotingPdo(), 1, array());
        $state = DomainList::normalizeState(array('pcid' => '21', 'oid' => '22', 'dnsid' => '23', 'ipid' => '24',
            'whid' => '25', 'rid' => '26', 'raid' => '27'));

        $parts = $list->filterParts($state, '', '', '');

        $this->assertSame(" AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') "
            . " AND d.cat_id = '21' " . " AND o.id = '22' " . " AND dns.id = '23' " . " AND ip.id = '24' "
            . " AND h.id = '25' " . " AND r.id = '26' " . " AND d.account_id = '27' ", DomainList::filterSql($parts));
    }

    public function testFilterPartsWithoutFilters()
    {
        $list = new DomainList(new QuotingPdo(), 1, array());
        $parts = $list->filterParts(DomainList::normalizeState(array()), '', 'YYYY-MM-DD', 'YYYY-MM-DD');

        $this->assertSame(" AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') ", DomainList::filterSql($parts));
        $all = $list->filterParts(DomainList::normalizeState(array('is_active' => 'ALL')), '', '', '');
        $this->assertSame(" AND d.active IN ('0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10') ", $all['is_active_string']);
    }

    public function testSearchSqlBindsEveryWordAndEscapesLikeCharacters()
    {
        $list = new DomainList(new QuotingPdo(), 1, array());
        $columns = array('lower' => array('d.domain', 'd.tld'), 'typed' => array('CAST(d.expiry_date AS CHAR)'),
            'encoded' => array('r.name'));

        $search = $list->searchSql(DomainList::searchTokens('50%_off\\ !"Tom\'s"'), $columns);

        $escape = " ESCAPE '\\\\'";
        $this->assertSame(" AND (d.domain LIKE :sw0" . $escape . " OR d.tld LIKE :sw1" . $escape
            . " OR CAST(d.expiry_date AS CHAR) LIKE :sw2" . $escape . " OR r.name LIKE :sw3" . $escape . ")"
            . " AND NOT (d.domain LIKE :sw4" . $escape . " OR d.tld LIKE :sw5" . $escape
            . " OR CAST(d.expiry_date AS CHAR) LIKE :sw6" . $escape . " OR r.name LIKE :sw7" . $escape . ")", $search['sql']);
        $this->assertSame(array('sw0' => '%50\\%\\_off\\\\%', 'sw1' => '%50\\%\\_off\\\\%', 'sw2' => '%50\\%\\_off\\\\%',
            'sw3' => '%50\\%\\_off\\\\%', 'sw4' => "%tom's%", 'sw5' => "%tom's%", 'sw6' => "%Tom's%",
            'sw7' => '%Tom&#039;s%'), $search['params']);
    }

    public function testSearchSqlAlsoMatchesTheStoredFormOfAnIdn()
    {
        $list = new DomainList(new QuotingPdo(), 1, array());
        $columns = array('lower' => array('d.domain'), 'typed' => array(), 'encoded' => array());

        $search = $list->searchSql(DomainList::searchTokens('XN--MNCHEN-3YA.DE'), $columns);

        $this->assertSame(" AND (d.domain LIKE :sw0 ESCAPE '\\\\' OR d.domain LIKE :sw1 ESCAPE '\\\\')", $search['sql']);
        $this->assertSame(array('sw0' => '%xn--mnchen-3ya.de%', 'sw1' => '%münchen.de%'), $search['params']);
    }

    public function testSearchSqlWithoutWords()
    {
        $list = new DomainList(new QuotingPdo(), 1, array());

        $this->assertSame(array('sql' => '', 'params' => array()),
            $list->searchSql(array(), array('lower' => array('d.domain'), 'typed' => array(), 'encoded' => array())));
    }

} //@formatter:on
