<?php
/**
 * /tests/Unit/HostingListTest.php
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

use DomainMOD\HostingList;
use DomainMOD\ListQuery;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\TestCase;

/*
 * The Hosting Services list's filters, as SQL fragments, and its joins, without a database, and on a small SQLite copy
 * of its tables, what the joins keep: a service with no fee, no conversion rate, or no IP address stays in the list
 */
class HostingListTest extends TestCase
{
    public function testNormalizeStateReadsWholeNumbersATermAndAKnownStatus()
    {
        $state = HostingList::normalizeState(array('oid' => '3', 'did' => '12', 'hid' => array('4'), 'hipid' => '6abc',
            'hterm' => '12', 'is_active' => '5'));
        // There's no Domain filter any more, so an old did is ignored
        $this->assertSame(array('oid' => 3, 'hid' => 0, 'hpaid' => 0, 'htid' => 0, 'hipid' => 6, 'hcid' => 0,
            'hterm' => 12, 'is_active' => '5'), $state);
    }

    // Only an allowed billing term filters, and anything else is no filter
    public function testTheTermFilterTakesOnlyAllowedTerms()
    {
        foreach (array('1', '3', '6', '12', '24', '36') as $term) {
            $this->assertSame((int) $term, HostingList::normalizeState(array('hterm' => $term))['hterm']);
        }
        foreach (array('', '0', '2', '12abc', '-12', '12.0', array('12')) as $term) {
            $this->assertSame(0, HostingList::normalizeState(array('hterm' => $term))['hterm'], var_export($term, true));
        }
    }

    public function testNormalizeStateKeepsNoIpAndDropsOtherNegatives()
    {
        $this->assertSame(-1, HostingList::NO_IP);
        $this->assertSame(-1, HostingList::normalizeState(array('hipid' => '-1'))['hipid']);
        $this->assertSame(0, HostingList::normalizeState(array('hipid' => '-2'))['hipid']);
        $this->assertSame(5, HostingList::normalizeState(array('hipid' => '5'))['hipid']);
        $this->assertSame(0, HostingList::checkedIpId('-3'));
        $this->assertFalse(defined(HostingList::class . '::NO_DOMAIN'));
        $this->assertFalse(method_exists(HostingList::class, 'domainCondition'));
    }

    public function testNormalizeStateFallsBackToLive()
    {
        foreach (array('', 'live', 'Cancelled', '11', array('ALL')) as $value) {
            $this->assertSame('LIVE', HostingList::normalizeState(array('is_active' => $value))['is_active']);
        }
        $this->assertSame('ALL', HostingList::normalizeState(array('is_active' => 'ALL'))['is_active']);
    }

    // The IP Address filter's condition is written from hs alone, so the filter menus can use it without the IP table
    public function testIpCondition()
    {
        $this->assertSame('', HostingList::ipCondition(0));
        $this->assertSame(" AND NOT EXISTS (SELECT 1 FROM ip_addresses AS nip WHERE nip.id = hs.ip_id) ",
            HostingList::ipCondition(HostingList::NO_IP));
        $this->assertSame(" AND hs.ip_id = '7' AND EXISTS (SELECT 1 FROM ip_addresses AS nip WHERE nip.id = hs.ip_id) ",
            HostingList::ipCondition('7abc'));
    }

    public function testFilterPartsWithNoFilters()
    {
        $parts = $this->hostingList()->filterParts(HostingList::normalizeState(array()), '', 'YYYY-MM-DD', 'YYYY-MM-DD');

        $this->assertSame(" AND hs.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') ", $parts['is_active_string']);
        foreach (array('oid', 'hid', 'hpaid', 'htid', 'hterm', 'hipid', 'hcid', 'range', 'search') as $key) {
            $this->assertSame('', $parts[$key . '_string'], $key);
        }
        $this->assertSame(" AND hs.active IN ('1', '2', '3', '4', '5', '6', '7', '8', '9') ", HostingList::filterSql($parts));
    }

    public function testFilterPartsForEveryFilterAreWrittenOnTheHostingServiceAlone()
    {
        $state = HostingList::normalizeState(array('oid' => '1', 'hid' => '3', 'hpaid' => '4',
            'htid' => '5', 'hterm' => '3', 'hipid' => '6', 'hcid' => '7', 'is_active' => '0'));
        $parts = $this->hostingList()->filterParts($state, 'it&#39;s', '2026-01-01', '2026-12-31');

        $this->assertSame(" AND hs.active = '0' ", $parts['is_active_string']);
        $this->assertSame(" AND hs.owner_id = '1' ", $parts['oid_string']);
        $this->assertSame(" AND hs.hosting_id = '3' ", $parts['hid_string']);
        $this->assertSame(" AND hs.account_id = '4' ", $parts['hpaid_string']);
        $this->assertSame(" AND hs.type_id = '5' ", $parts['htid_string']);
        $this->assertSame(" AND hs.billing_term = '3' ", $parts['hterm_string']);
        $this->assertSame(" AND hs.ip_id = '6' AND EXISTS (SELECT 1 FROM ip_addresses AS nip WHERE nip.id = hs.ip_id) ", $parts['hipid_string']);
        $this->assertSame(" AND hs.cat_id = '7' ", $parts['hcid_string']);
        $this->assertSame(" AND (hs.expiry_date >= [2026-01-01] AND hs.expiry_date <= [2026-12-31])", $parts['range_string']);
        // The keyword is matched as it is against the encoded name and hostname
        $this->assertSame(" AND (hs.name LIKE [%it&#39;s%] OR hs.hostname LIKE [%it&#39;s%]) ", $parts['search_string']);

        $sql = HostingList::filterSql($parts);
        preg_match_all('/\b([a-z]+)\.[a-z_]+/', $sql, $aliases);
        $this->assertSame(array('hs', 'nip'), self::sorted(array_unique($aliases[1])));
        $this->assertStringNotContainsString('domain', $sql);

        // A menu's filters leave out its own
        $this->assertStringNotContainsString("hs.hosting_id", HostingList::filterSqlWithout($parts, 'hid'));
        $this->assertStringContainsString("hs.account_id = '4'", HostingList::filterSqlWithout($parts, 'hid'));
    }

    /*
     * The fee, its currency, the user's conversion rate, and the IP address are LEFT JOINs in both join modes, with
     * the user's id in the conversion rate's ON rather than the WHERE, and no WHERE condition on any LEFT JOINed table,
     * which would drop the services without a match. There's no domains join.
     */
    public function testTheListLeftJoinsTheFeeTheRateAndTheIpAddress()
    {
        $list = $this->joinList();

        $from = $list->fromSql();
        $this->assertStringContainsString('hosting_services AS hs
                 LEFT JOIN hosting_fees AS f ON f.id = hs.fee_id
                 LEFT JOIN currencies AS c ON c.id = f.currency_id
                 LEFT JOIN currency_conversions AS cc ON cc.currency_id = f.currency_id AND cc.user_id = \'7\'
                 LEFT JOIN ip_addresses AS ip ON ip.id = hs.ip_id,', $from);
        $this->assertStringContainsString('AND hs.id = hsfd.service_id', $from);
        self::assertNoWhereOnLeftJoins($this, $from);

        $list->fixed = true;
        $from = $list->fromSql();
        $this->assertStringContainsString(' LEFT JOIN hosting_fees AS f FORCE INDEX (PRIMARY) ON f.id = hs.fee_id ', $from);
        $this->assertStringContainsString(' LEFT JOIN currencies AS c FORCE INDEX (PRIMARY) ON c.id = f.currency_id ', $from);
        $this->assertStringContainsString(" LEFT JOIN currency_conversions AS cc FORCE INDEX (user_currency) ON cc.currency_id = f.currency_id AND cc.user_id = '7' ", $from);
        $this->assertStringContainsString(' LEFT JOIN ip_addresses AS ip FORCE INDEX (PRIMARY) ON ip.id = hs.ip_id ', $from);
        $this->assertStringContainsString(' STRAIGHT_JOIN hosting_types AS ht FORCE INDEX (PRIMARY)', $from);
        $this->assertSame(1, substr_count($from, 'cc.user_id'));
        self::assertNoWhereOnLeftJoins($this, $from);
        $this->assertStringNotContainsString('domains', $from);
    }

    // The fixed order's conversion rate condition gets the instance's user id as a whole number, never a placeholder,
    // since the counts bind only the filters' values
    public function testTheJoinOrderCarriesTheUsersIdAsAWholeNumber()
    {
        $list = $this->joinList();
        $list->fixed = true;
        (new \ReflectionProperty(HostingList::class, 'user_id'))->setValue($list, '12; DROP');
        $this->assertStringContainsString("cc.user_id = '12'", $list->fromSql());
        $this->assertStringNotContainsString(':user', $list->fromSql());
        // The constant itself is left as written
        $this->assertSame('cc.currency_id = f.currency_id', HostingList::JOIN_ORDER[7][3]);
    }

    // The fixed join order is used only once the database has the hosting tables (4.27.0), which the SSL and Domains
    // lists' version (4.26.0) isn't
    public function testTheListIndexesNeedTheHostingVersion()
    {
        $this->assertTrue(HostingList::hasListIndexes('4.27.0'));
        $this->assertTrue(HostingList::hasListIndexes('4.28.0'));
        $this->assertFalse(HostingList::hasListIndexes('4.26.0'));
        $this->assertFalse(HostingList::hasListIndexes(''));
        $this->assertTrue(ListQuery::hasListIndexes('4.26.0'));
    }

    public function testExportOrderAndTieBreak()
    {
        $this->assertSame(array('hs.expiry_date', 'hs.name', 'hs.id'), HostingList::EXPORT_ORDER);
        $this->assertSame(' ORDER BY hs.expiry_date ASC, hs.name ASC, hs.id ASC', HostingList::EXPORT_ORDER_SQL);
        $this->assertSame(array('hs.name ASC', 'hs.id ASC'), HostingList::TIE_BREAK);
        $this->assertSame('hsfd', HostingList::JOIN_ORDER[1][1]);
    }

    // The total uses the yearly figure in the user's currency, which leaves out an invalid term
    public function testTheTotalUsesTheYearlyFigure()
    {
        $sql = HostingList::yearlyTotalSql();
        $this->assertStringContainsString('hs.billing_term IN (1, 3, 6, 12, 24, 36)', $sql);
        $this->assertStringContainsString('hs.total_cost * cc.conversion * 12 / hs.billing_term', $sql);
    }

    // The total and the no-fee count read in one pass. A NULL cost (no fee or no rate) is left out of the SUM().
    public function testTheTotalsReadTheYearlyTotalAndTheNoFeeCount()
    {
        $sql = HostingList::totalsSql();
        $this->assertStringContainsString('SUM((CASE WHEN hs.billing_term IN (1, 3, 6, 12, 24, 36) THEN hs.total_cost * cc.conversion', $sql);
        $this->assertStringContainsString('AS grand_total', $sql);
        $this->assertStringContainsString('COALESCE(SUM(CASE WHEN f.id IS NULL THEN 1 ELSE 0 END), 0) AS no_fee_count', $sql);
    }

    // The no-fee cell's link reads provider_id, and the reserved column names follow the new columns
    public function testTheColumnsHaveTheProviderIdAndTheHostnameAndNoDomainOrUsername()
    {
        $names = HostingList::builtInColumnNames();
        foreach (array('provider_id', 'hostname', 'f_id', 'conversion', 'ip_id', 'yearly_cost') as $name) {
            $this->assertContains($name, $names);
        }
        foreach (array('server', 'hosting_username', 'domain_id', 'domain') as $name) {
            $this->assertNotContains($name, $names);
        }
    }

    /*
     * On SQLite, through the comma-list fromSql() (the fixed order's FORCE INDEX and STRAIGHT_JOIN are MySQL's): a service
     * with no fee stays with NULL fee columns, is left out of the yearly total, and is counted as having no fee. A
     * service with no conversion rate stays too, and is also left out of the total. A service with IP 0, or a gone IP,
     * stays, and NO_IP finds exactly those. Another user's rate is never joined.
     */
    public function testNoFeeNoRateAndNoIpServicesStayInTheList()
    {
        if (!extension_loaded('pdo_sqlite')) $this->markTestSkipped('pdo_sqlite is not loaded');

        $pdo = $this->sqliteList();
        $list = Instance::withoutConstructor(HostingList::class, array('pdo' => $pdo, 'user_id' => 7, 'field_columns' => ''));
        $state = HostingList::normalizeState(array());
        $filter = HostingList::filterSql((new HostingList($pdo, 7, array()))->filterParts($state, '', '', ''));

        $rows = $pdo->query($list->selectSql() . $list->fromSql() . $filter . ' ORDER BY hs.id')->fetchAll(\PDO::FETCH_OBJ);
        $this->assertSame(array(1, 2, 3, 4), array_map('intval', array_column($rows, 'id')));
        // 1 has a fee and a rate, 2 no fee, 3 a fee in a currency the user has no rate for, 4 a gone IP address
        $this->assertNotNull($rows[0]->f_id);
        $this->assertNull($rows[1]->f_id);
        $this->assertNull($rows[1]->renewal_fee);
        $this->assertNull($rows[1]->conversion);
        $this->assertNotNull($rows[2]->f_id);
        $this->assertNull($rows[2]->conversion);
        $this->assertNull($rows[1]->ip_id);
        $this->assertNull($rows[3]->ip_id);
        $this->assertSame(2, (int) $rows[0]->provider_id);
        $this->assertSame(1.5, (float) $rows[0]->conversion);

        $totals = $pdo->query(HostingList::totalsSql() . $list->fromSql() . $filter)->fetch(\PDO::FETCH_OBJ);
        // Only services 1 and 4 can be costed: 10 a month at 1.5, twice, is 360 a year
        $this->assertEqualsWithDelta(360.0, (float) $totals->grand_total, 0.001);
        $this->assertSame(1, (int) $totals->no_fee_count);

        $no_ip = $pdo->query('SELECT hs.id' . $list->fromSql() . $filter . HostingList::ipCondition(HostingList::NO_IP)
            . ' ORDER BY hs.id')->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame(array(2, 4), array_map('intval', $no_ip));
        $one_ip = $pdo->query('SELECT hs.id' . $list->fromSql() . $filter . HostingList::ipCondition(5)
            . ' ORDER BY hs.id')->fetchAll(\PDO::FETCH_COLUMN);
        $this->assertSame(array(1, 3), array_map('intval', $one_ip));
    }

    // A HostingList whose join mode the test sets, with no database
    private function joinList()
    {
        return new class extends HostingList {
            public $fixed = false;

            public function __construct()
            {
                $this->user_id = 7;
                $this->field_columns = '';
            }

            public function fixedJoinOrder()
            {
                return $this->fixed;
            }
        };
    }

    // No WHERE condition names a LEFT JOINed table (f, c, cc, or ip)
    private static function assertNoWhereOnLeftJoins(TestCase $test, $from)
    {
        $where = substr($from, strpos($from, 'WHERE'));
        $test->assertDoesNotMatchRegularExpression('/\b(f|c|cc|ip)\./', $where);
    }

    // An in-memory SQLite copy of the list's tables, with four services (see testNoFeeNoRateAndNoIpServicesStayInTheList())
    private function sqliteList()
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        foreach (array(
            "CREATE TABLE hosting_services (id INTEGER, account_id INTEGER, owner_id INTEGER, hosting_id INTEGER, type_id INTEGER, ip_id INTEGER, cat_id INTEGER, fee_id INTEGER, name TEXT, hostname TEXT, billing_term INTEGER, renewal_day INTEGER, expiry_date TEXT, autorenew INTEGER, total_cost REAL, notes TEXT, active INTEGER, creation_type_id INTEGER, created_by INTEGER, insert_time TEXT, update_time TEXT)",
            "CREATE TABLE hosting_service_field_data (id INTEGER, service_id INTEGER)",
            "CREATE TABLE hosting_provider_accounts (id INTEGER, hosting_id INTEGER, owner_id INTEGER, username TEXT)",
            "CREATE TABLE hosting (id INTEGER, name TEXT)",
            "CREATE TABLE owners (id INTEGER, name TEXT)",
            "CREATE TABLE hosting_fees (id INTEGER, currency_id INTEGER, initial_fee REAL, renewal_fee REAL, misc_fee REAL)",
            "CREATE TABLE currencies (id INTEGER, currency TEXT)",
            "CREATE TABLE currency_conversions (id INTEGER, currency_id INTEGER, user_id INTEGER, conversion REAL)",
            "CREATE TABLE hosting_types (id INTEGER, type TEXT)",
            "CREATE TABLE ip_addresses (id INTEGER, name TEXT, ip TEXT, rdns TEXT)",
            "CREATE TABLE categories (id INTEGER, name TEXT)",
            "INSERT INTO hosting_provider_accounts VALUES (1, 2, 3, 'me')",
            "INSERT INTO hosting VALUES (2, 'Host')",
            "INSERT INTO owners VALUES (3, 'Owner')",
            "INSERT INTO hosting_types VALUES (4, 'VPS')",
            "INSERT INTO ip_addresses VALUES (5, 'Main', '10.0.0.1', '')",
            "INSERT INTO categories VALUES (6, 'Cat')",
            "INSERT INTO currencies VALUES (1, 'CAD'), (2, 'EUR')",
            "INSERT INTO hosting_fees VALUES (8, 1, 0, 9, 1), (9, 2, 0, 9, 1)",
            // The user's rate for CAD, and another user's for EUR, which the user's rows must never join
            "INSERT INTO currency_conversions VALUES (1, 1, 7, 1.5), (2, 2, 99, 2.0), (3, 1, 99, 3.0)",
            "INSERT INTO hosting_services VALUES (1, 1, 3, 2, 4, 5, 6, 8, 'With fee', '', 1, 0, '2026-01-01', 0, 10, '', 1, 1, 0, '', ''), (2, 1, 3, 2, 4, 0, 6, 0, 'No fee', '', 1, 0, '2026-01-01', 0, 0, '', 1, 1, 0, '', ''), (3, 1, 3, 2, 4, 5, 6, 9, 'No rate', '', 1, 0, '2026-01-01', 0, 10, '', 1, 1, 0, '', ''), (4, 1, 3, 2, 4, 77, 6, 8, 'Gone IP', '', 1, 0, '2026-01-01', 0, 10, '', 1, 1, 0, '', '')",
            "INSERT INTO hosting_service_field_data VALUES (1, 1), (2, 2), (3, 3), (4, 4)") as $sql) {
            $pdo->exec($sql);
        }
        return $pdo;
    }

    private static function sorted(array $values)
    {
        sort($values);
        return array_values($values);
    }

    // A HostingList with a PDO that never connects and shows what it quotes in square brackets
    private function hostingList()
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
        return new HostingList($pdo, 1, array());
    }

} //@formatter:on
