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
use DomainMOD\Hosting;
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
    // Whether hosting is available is kept per connection for the request, so each test starts without it
    protected function setUp(): void
    {
        Hosting::forgetAvailability();
    }

    protected function tearDown(): void
    {
        Hosting::forgetAvailability();
    }

    public function testNormalizeStateChecksEveryFilter()
    {
        $state = DomainList::normalizeState(array('pcid' => '5abc', 'oid' => '-2', 'rid' => array('3'), 'segid' => '7',
            'tld' => 'XN--P1AI', 'is_active' => '10'));

        $this->assertSame(array('pcid' => 5, 'oid' => -2, 'dnsid' => 0, 'ipid' => 0, 'rid' => 0, 'raid' => 0,
            'segid' => 7, 'hsid' => 0, 'tld' => 'рф', 'is_active' => '10', 'lock' => ''), $state);
    }

    public static function serviceFilters()
    {
        return array(
            'none' => array(null, 0),
            'a service' => array('12', 12),
            'no service' => array('-1', DomainList::NO_SERVICE),
            'another negative number' => array('-2', 0),
            'text' => array('abc', 0),
            'an array' => array(array('12'), 0),
        );
    }

    // The Hosting Service filter: a service's id, NO_SERVICE, or no filter
    #[DataProvider('serviceFilters')]
    public function testNormalizeStateHostingService($hsid, $expected)
    {
        $this->assertSame($expected, DomainList::normalizeState(array('hsid' => $hsid))['hsid']);
    }

    // The Hosting Provider filter it replaced is ignored, since a provider's id isn't a service's
    public function testAnOldHostingProviderFilterIsIgnored()
    {
        $state = DomainList::normalizeState(array('whid' => '25'));

        $this->assertArrayNotHasKey('whid', $state);
        $this->assertSame(0, $state['hsid']);
        $parts = self::withHosting()->filterParts($state, '', '', '');
        $this->assertArrayNotHasKey('whid_string', $parts);
        $this->assertSame('', $parts['hsid_string']);
        $this->assertStringNotContainsString('25', DomainList::filterSql($parts));
    }

    // The condition is written on the domains table alone, so the filter menus that query domains without the joins
    // can use it
    public function testTheServiceConditionIsWrittenOnDomainsAlone()
    {
        $this->assertSame('', DomainList::serviceCondition(0));
        $this->assertSame('', DomainList::serviceCondition(-5));
        $this->assertSame(" AND d.service_id = '12' ", DomainList::serviceCondition('12'));
        $this->assertSame(" AND (d.service_id = 0 OR NOT EXISTS (SELECT 1 FROM hosting_services AS nhs WHERE nhs.id = d.service_id)) ",
            DomainList::serviceCondition(DomainList::NO_SERVICE));
        foreach (array(12, DomainList::NO_SERVICE) as $hsid) {
            $this->assertSame(0, preg_match('/(?<!n)hs\./', DomainList::serviceCondition($hsid)));
            $this->assertStringNotContainsString('hosting_id', DomainList::serviceCondition($hsid));
        }
    }

    // A Domains list on a database with hosting services (see DomainList::hostingAvailable())
    private static function withHosting()
    {
        return new class(new QuotingPdo(), 1, array()) extends DomainList {
            public function hostingAvailable()
            {
                return true;
            }
        };
    }

    public function testTheServiceFilterOnlyOnceHostingIsAvailable()
    {
        $state = DomainList::normalizeState(array('hsid' => '12'));

        $parts = self::withHosting()->filterParts($state, '', '', '');
        $this->assertSame(" AND d.service_id = '12' ", $parts['hsid_string']);
        $this->assertSame(" AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') " . " AND d.service_id = '12' ",
            DomainList::filterSql($parts));

        $none = self::withHosting()->filterParts(DomainList::normalizeState(array('hsid' => '-1')), '', '', '');
        $this->assertSame(DomainList::serviceCondition(DomainList::NO_SERVICE), $none['hsid_string']);

        // Before the update there are no services, so the filter is no filter
        $before = (new DomainList(new QuotingPdo(), 1, array()))->filterParts($state, '', '', '');
        $this->assertSame('', $before['hsid_string']);
    }

    // The service's columns are selected only once hosting is available, but their names are reserved either way, so a
    // custom field can't take one before the update and then clash after it
    public function testTheServiceColumnsAndTheirReservedNames()
    {
        $this->assertStringContainsString(', hs.id AS service_id, hs.name AS service_name', self::withHosting()->selectSql());
        $this->assertStringNotContainsString('hs.', (new DomainList(new QuotingPdo(), 1, array()))->selectSql());
        $this->assertStringNotContainsString('h.', DomainList::SELECT_COLUMNS);
        $this->assertStringNotContainsString('whid', DomainList::SELECT_COLUMNS);

        $names = DomainList::builtInColumnNames();
        $this->assertContains('service_id', $names);
        $this->assertContains('service_name', $names);
        $this->assertNotContains('whid', $names);
        $this->assertNotContains('wh_name', $names);
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
            'rid' => '26', 'raid' => '27'));

        $parts = $list->filterParts($state, '', '', '');

        $this->assertSame(" AND d.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') "
            . " AND d.cat_id = '21' " . " AND o.id = '22' " . " AND dns.id = '23' " . " AND ip.id = '24' "
            . " AND r.id = '26' " . " AND d.account_id = '27' ", DomainList::filterSql($parts));
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
