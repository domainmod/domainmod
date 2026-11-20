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
use PHPUnit\Framework\Attributes\DataProvider;
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

    public static function pagePreferences()
    {
        return array(
            'the default' => array('50', 50, array(50, 75, 100, 250, 500)),
            'a size not in the menu' => array('20', 20, array(20, 50, 75, 100, 250, 500)),
            'a whole number' => array(75, 75, array(75, 50, 100, 250, 500)),
            'the largest size' => array('500', 500, array(500, 50, 75, 100, 250)),
            // capped at 500 rather than falling back to 50 (2ee3db39)
            'over the cap' => array('1000', 500, array(500, 50, 75, 100, 250)),
            'far over the cap' => array('99999999', 500, array(500, 50, 75, 100, 250)),
            'a decimal' => array('25.5', 50, array(50, 75, 100, 250, 500)),
        );
    }

    #[DataProvider('pagePreferences')]
    public function testPageSizesGiveTheWholeMenu($preference, $default, $sizes)
    {
        $this->assertSame(array('default' => $default, 'sizes' => $sizes), DomainList::pageSizes($preference));
    }

    public static function starts()
    {
        return array(
            'zero' => array('0', 0),
            'a whole number' => array(250, 250),
            'the largest' => array('10000000', 10000000),
            'past the largest' => array('10000001', 0),
            'a decimal' => array('1.5', 0),
            'null' => array(null, 0),
        );
    }

    #[DataProvider('starts')]
    public function testCheckedStartLimits($start, $expected)
    {
        $this->assertSame($expected, DomainList::checkedStart($start));
    }

    public function testCheckedLengthRefusesSizesNotInTheMenu()
    {
        $sizes = DomainList::pageSizes('20');

        $this->assertSame(20, DomainList::checkedLength('20', $sizes));
        $this->assertSame(500, DomainList::checkedLength('500', $sizes));
        $this->assertSame(20, DomainList::checkedLength('30', $sizes));
        $this->assertSame(20, DomainList::checkedLength(null, $sizes));
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

    // Each token as text, with an excluded one marked "[not] ", so a token that kept a literal "!" in its text (and
    // wasn't excluded) can't pass for an excluded one
    private static function words(array $tokens)
    {
        return array_map(function ($token) {
            // negate is checked as a real boolean, so a 1 or 0 shows up as "[?] " and fails
            return ($token['negate'] === true ? '[not] ' : ($token['negate'] === false ? '' : '[?] ')) . $token['text'];
        }, $tokens);
    }

    public static function searches()
    {
        return array(
            'words with extra spaces' => array('example  com', array('example', 'com')),
            'an excluded phrase' => array('!"two words"', array('[not] two words')),
            'an empty phrase is ignored' => array('"" example', array('example')),
            'an unclosed quote is a word' => array('"open', array('"open')),
            'an exclamation mark inside a word' => array('a!b', array('a!b')),
            'empty' => array('', array()),
            'Unicode' => array('münchen рф', array('münchen', 'рф')),
        );
    }

    #[DataProvider('searches')]
    public function testSearchTokens($text, $expected)
    {
        $this->assertSame($expected, self::words(DomainList::searchTokens($text)));
    }

    public function testSearchTokensLimitsCountCharactersAndSkipIgnoredExclusions()
    {
        // ignored exclusions don't count toward the ten words, wherever they sit, and every word is still kept
        $this->assertCount(10, DomainList::searchTokens(implode(' ', range(1, 10)) . ' !a !b'));
        $this->assertSame(array('1', '2', '3', '4', '5', '6', '7', '8', '9', '10'),
            self::words(DomainList::searchTokens('!a 1 2 3 !b 4 5 6 7 8 9 10')));
        // eleven real words is one too many, written out rather than taken from the limit constant
        $this->assertFalse(DomainList::searchTokens('1 2 3 4 5 6 7 8 9 10 11'));

        $this->assertCount(1, DomainList::searchTokens(str_repeat('a', 200)));
        // and 201 is one too many, written out rather than taken from the limit constant
        $this->assertFalse(DomainList::searchTokens(str_repeat('a', 201)));
        // the length is counted in characters, not bytes
        $this->assertCount(1, DomainList::searchTokens(str_repeat('ü', 200)));
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

    public function testOrderSqlSkipsBadRequestsButKeepsTheOthers()
    {
        $map = array(1 => array('d.domain'), 2 => array('d.expiry_date'), 3 => array('d.tld'));
        $default = array(array(2, 'asc'));
        $order = array(array(9, 'asc'), array(3, 'sideways'), array(1, 'desc'), array(1, 'asc'), array('x', 'asc'),
            array(0, 'asc'), array(), array(3, 'ASC'));

        $this->assertSame(' ORDER BY d.domain DESC, d.tld ASC, d.domain ASC, d.id ASC',
            DomainList::orderSql($order, $map, $default));
    }

    public function testOrderSqlUsesTheDefaultOnlyWhenNothingValidIsLeft()
    {
        $map = array(1 => array('d.domain'), 2 => array('d.expiry_date'));
        $default = array(array(2, 'asc'));

        $this->assertSame(' ORDER BY d.expiry_date ASC, d.domain ASC, d.id ASC',
            DomainList::orderSql(array(array(7, 'asc'), array(1, '; DROP TABLE domains')), $map, $default));
        $this->assertSame(' ORDER BY d.expiry_date ASC, d.domain ASC, d.id ASC', DomainList::orderSql(array(), $map, $default));
        $this->assertSame(' ORDER BY d.domain ASC, d.id ASC', DomainList::orderSql(array(), $map, array(array(9, 'asc'))));
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

    public function testSavedListKeyIsTheStartOfTheQueryAndParametersSha1()
    {
        $this->assertSame(substr(sha1("SELECT 1\n" . serialize(array())), 0, 12), ListQuery::savedListKey('SELECT 1'));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', ListQuery::savedListKey(null));
    }

    // A Raw List link is checked against the saved list before anything is read
    public function testTheRawListLinksStatus()
    {
        $sql = ' FROM domains AS d WHERE 1 = 1 AND dfd.`renew` = :cff0';
        $params = array('cff0' => 1);
        $link = array('dt_list' => ListQuery::savedListKey($sql, $params), 'dt_type' => 'domains', 'dt_cfk' => 'abc');

        $this->assertSame('ok', ListQuery::rawLinkStatus($link, 'domains', 'domains', $sql, $params));
        // An old bookmark, with none of the keys
        $this->assertSame('unchecked', ListQuery::rawLinkStatus(array(), 'domains', 'domains', $sql, $params));
        // Another tab saved the SSL list, or the same list with other values
        $this->assertSame('mismatch', ListQuery::rawLinkStatus($link, 'domains', 'ssl-certs', $sql, $params));
        $this->assertSame('mismatch', ListQuery::rawLinkStatus($link, 'domains', 'domains', $sql, array('cff0' => 0)));
        $this->assertSame('mismatch', ListQuery::rawLinkStatus($link, 'domains', 'domains', null, $params));
        // A link that names its list but has no custom field filters' key to check
        $this->assertSame('mismatch', ListQuery::rawLinkStatus(array_diff_key($link, array('dt_cfk' => 1)), 'domains',
            'domains', $sql, $params));
        $this->assertSame('mismatch', ListQuery::rawLinkStatus(array('dt_cfk' => array('abc')) + $link, 'domains', 'domains',
            $sql, $params));
    }

    // The custom field filters' values are bound parameters, so the same query with other values is another list
    public function testSavedListKeyChangesWhenOnlyTheParametersChange()
    {
        $sql = ' FROM domains AS d WHERE 1 = 1 AND dfd.`renew` = :cff0';
        $this->assertNotSame(ListQuery::savedListKey($sql, array('cff0' => 1)), ListQuery::savedListKey($sql, array('cff0' => 0)));
        $this->assertSame(ListQuery::savedListKey($sql, array('cff0' => 1)), ListQuery::savedListKey($sql, array('cff0' => 1)));
        // Text that isn't valid UTF-8 still gives its own key
        $this->assertNotSame(ListQuery::savedListKey($sql, array('cff0' => "\xFF")), ListQuery::savedListKey($sql, array('cff0' => "\xFE")));
    }

    private function sslList()
    {
        return Instance::withoutConstructor(SslList::class);
    }

} //@formatter:on
