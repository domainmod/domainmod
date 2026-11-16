<?php
/**
 * /tests/Unit/ListQueryTest.php
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
use DomainMOD\ListQuery;
use DomainMOD\SslList;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\TestCase;

// The parts of the server-side lists' shared query class that need no database (ListQuery, through SslList and
// DomainList, since it's abstract)
class ListQueryTest extends TestCase
{
    public function testPageSizesStartWithTheUsersPreference()
    {
        $this->assertSame(array('default' => 25, 'sizes' => array(25, 50, 75, 100, 250, 500)), SslList::pageSizes('25'));
        $this->assertSame(array('default' => 100, 'sizes' => array(100, 50, 75, 250, 500)), SslList::pageSizes(100));
    }

    public function testPageSizesCapThePreferenceAt500()
    {
        $this->assertSame(array('default' => 500, 'sizes' => array(500, 50, 75, 100, 250)), SslList::pageSizes('9999'));
    }

    public function testPageSizesFallBackTo50()
    {
        foreach (array('', '0', '-5', 'abc', '2.5', null) as $preference) {
            $this->assertSame(50, SslList::pageSizes($preference)['default'], var_export($preference, true));
        }
    }

    public function testCheckedStartAcceptsOnlyWholeNumbersFromZero()
    {
        $this->assertSame(150, SslList::checkedStart('150'));
        $this->assertSame(0, SslList::checkedStart('-1'));
        $this->assertSame(0, SslList::checkedStart('abc'));
        $this->assertSame(0, SslList::checkedStart(array('1')));
        $this->assertSame(0, SslList::checkedStart('99999999999'));
    }

    public function testCheckedLengthAcceptsOnlyTheMenusSizes()
    {
        $sizes = SslList::pageSizes(25);
        $this->assertSame(250, SslList::checkedLength('250', $sizes));
        $this->assertSame(25, SslList::checkedLength('-1', $sizes));
        $this->assertSame(25, SslList::checkedLength('1000', $sizes));
        $this->assertSame(25, SslList::checkedLength(array('50'), $sizes));
    }

    public function testSearchTokensSplitWordsAndQuotedPhrases()
    {
        $this->assertSame(array(
            array('text' => 'alpha', 'negate' => false),
            array('text' => 'two words', 'negate' => false),
            array('text' => 'curly words', 'negate' => false),
            array('text' => 'beta', 'negate' => true)),
            SslList::searchTokens("alpha \"two words\" \u{201C}curly words\u{201D} !beta"));
    }

    public function testSearchTokensIgnoreShortExclusions()
    {
        // "!a" excludes nothing, and a lone "!" is searched for as it is
        $this->assertSame(array(array('text' => '!', 'negate' => false), array('text' => 'x', 'negate' => false)),
            SslList::searchTokens('!a ! x'));
        $this->assertSame(array(), SslList::searchTokens('   '));
    }

    public function testSearchTokensRefuseTextOverTheLimits()
    {
        $this->assertFalse(SslList::searchTokens(str_repeat('a', ListQuery::MAX_SEARCH_LENGTH + 1)));
        $this->assertFalse(SslList::searchTokens(implode(' ', range(1, ListQuery::MAX_SEARCH_WORDS + 1))));
        $this->assertCount(ListQuery::MAX_SEARCH_WORDS, SslList::searchTokens(implode(' ', range(1, ListQuery::MAX_SEARCH_WORDS))));
    }

    public function testRequestShapeAcceptsSingleValuesAndAnOrderList()
    {
        $get = array('draw' => '3', 'oid' => '2', 'search' => array('value' => 'x'),
            'order' => array(array('column' => '1', 'dir' => 'asc')));
        $this->assertTrue(ListQuery::requestShapeOk($get, array('draw', 'oid', 'did')));
    }

    public function testRequestShapeRefusesArraysAndMalformedOrders()
    {
        $keys = array('search_for');
        $this->assertFalse(ListQuery::requestShapeOk(array('search_for' => array('x')), $keys));
        $this->assertFalse(ListQuery::requestShapeOk(array('search' => array('value' => array('x'))), $keys));
        $this->assertFalse(ListQuery::requestShapeOk(array('search' => 'x'), $keys));
        $this->assertFalse(ListQuery::requestShapeOk(array('order' => 'foo'), $keys));
        $this->assertFalse(ListQuery::requestShapeOk(array('order' => array('a' => array('column' => '1', 'dir' => 'asc'))), $keys));
        $this->assertFalse(ListQuery::requestShapeOk(array('order' => array(array('column' => array('1'), 'dir' => 'asc'))), $keys));
        $this->assertFalse(ListQuery::requestShapeOk(array('order' => array(array('column' => '1'))), $keys));
    }

    public function testOrderSqlUsesTheFixedMapAndEndsWithTheListsTieBreak()
    {
        $map = array(1 => array('sslc.name'), 3 => array('sslp.name', 'o.name'));
        $this->assertSame(' ORDER BY sslp.name DESC, o.name DESC, sslc.name ASC, sslc.id ASC',
            SslList::orderSql(array(array(3, 'desc')), $map, array(array(1, 'asc'))));
        $this->assertSame(' ORDER BY sslp.name ASC, o.name ASC, d.domain ASC, d.id ASC',
            DomainList::orderSql(array(array(3, 'ASC')), $map, array(array(1, 'asc'))));
    }

    public function testOrderSqlSkipsUnknownColumnsAndDirections()
    {
        $map = array(1 => array('sslc.name'), 2 => array('sslc.expiry_date'));
        // An unknown column, a column with no sort, an injected direction, and a repeat all fall back to the default
        $this->assertSame(' ORDER BY sslc.expiry_date ASC, sslc.name ASC, sslc.id ASC',
            SslList::orderSql(array(array(9, 'asc'), array(0, 'asc'), array(1, 'asc; DROP TABLE x')), $map, array(array(2, 'asc'))));
        $this->assertSame(' ORDER BY sslc.name DESC, sslc.expiry_date ASC, sslc.name ASC, sslc.id ASC',
            SslList::orderSql(array(array(1, 'desc'), array(2, 'asc'), array(1, 'asc')), $map, array(array(2, 'asc'))));
    }

    public function testSearchSqlMatchesEachWordInTheVisibleColumns()
    {
        $columns = array('lower' => array(), 'typed' => array('CAST(sslc.expiry_date AS CHAR)'), 'idn' => array(),
            'encoded' => array('sslc.name'));
        $search = $this->sslList()->searchSql(SslList::searchTokens('a&b !50%_\\'), $columns);

        $this->assertSame(" AND (CAST(sslc.expiry_date AS CHAR) LIKE :sw0 ESCAPE '\\\\' OR sslc.name LIKE :sw1 ESCAPE '\\\\')"
            . " AND NOT (CAST(sslc.expiry_date AS CHAR) LIKE :sw2 ESCAPE '\\\\' OR sslc.name LIKE :sw3 ESCAPE '\\\\')",
            $search['sql']);
        // Typed as typed, names HTML-encoded, and \, %, and _ escaped so they match themselves
        $this->assertSame(array('sw0' => '%a&b%', 'sw1' => '%a&amp;b%', 'sw2' => '%50\\%\\_\\\\%', 'sw3' => '%50\\%\\_\\\\%'),
            $search['params']);
    }

    public function testSearchSqlMatchesADomainsOtherSpellingOnlyWhenTheDomainShows()
    {
        $hidden = array('lower' => array(), 'typed' => array(), 'idn' => array(), 'encoded' => array('sslc.name'));
        $shown = array('lower' => array('d.domain'), 'typed' => array(), 'idn' => array('d.domain'),
            'encoded' => array('sslc.name'));
        $tokens = SslList::searchTokens('xn--mnchen-3ya.de');

        $this->assertSame(array('sw0' => '%xn--mnchen-3ya.de%'), $this->sslList()->searchSql($tokens, $hidden)['params']);

        $search = $this->sslList()->searchSql($tokens, $shown);
        $this->assertSame(array('sw0' => '%xn--mnchen-3ya.de%', 'sw1' => "%m\u{00FC}nchen.de%", 'sw2' => '%xn--mnchen-3ya.de%'),
            $search['params']);
        $this->assertStringContainsString('d.domain LIKE :sw1', $search['sql']);
    }

    public function testSearchSqlWithNothingToSearchMatchesNothing()
    {
        $none = array('lower' => array(), 'typed' => array(), 'idn' => array(), 'encoded' => array());
        $this->assertSame(array('sql' => ' AND (1 = 0)', 'params' => array()),
            $this->sslList()->searchSql(SslList::searchTokens('x'), $none));
        $this->assertSame(array('sql' => '', 'params' => array()), $this->sslList()->searchSql(array(), $none));
    }

    public function testSavedListKeyIsShortAndFollowsTheQuery()
    {
        $this->assertSame(12, strlen(ListQuery::savedListKey('SELECT 1')));
        $this->assertNotSame(ListQuery::savedListKey('SELECT 1'), ListQuery::savedListKey('SELECT 2'));
    }

    private function sslList()
    {
        return Instance::withoutConstructor(SslList::class);
    }

} //@formatter:on
