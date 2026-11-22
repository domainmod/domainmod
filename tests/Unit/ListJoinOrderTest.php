<?php
/**
 * /tests/Unit/ListJoinOrderTest.php
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
use DomainMOD\ListQuery;
use DomainMOD\LiveDns;
use DomainMOD\SslList;
use DomainMOD\Tests\Support\FakeDatabase;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\TestCase;

/*
 * The server-side lists' fixed join order (ListQuery::fixedJoinOrder()): their queries gain STRAIGHT_JOIN only once the
 * database is at 4.26.0 or later and has the custom field data index, and otherwise stay exactly as they were. The
 * database is a FakeDatabase, which answers the version and index checks.
 */
class ListJoinOrderTest extends TestCase
{
    // The lists' FROM and WHERE before the fixed join order, which they must still give when the check doesn't pass. The
    // SSL list's domain is a LEFT JOIN, since a certificate doesn't need a domain. The Domains list joins no hosting
    // table until the 4.27.0 update adds hosting services (see testTheHostingServiceJoinComesWithTheUpdate()).
    const DOMAINS_FROM = "
            FROM domains AS d, registrar_accounts AS ra, registrars AS r, owners AS o, categories AS cat, fees AS f, currencies AS c, currency_conversions AS cc, dns AS dns, ip_addresses AS ip, domain_field_data AS dfd
            WHERE d.account_id = ra.id
              AND ra.registrar_id = r.id
              AND ra.owner_id = o.id
              AND d.cat_id = cat.id
              AND d.fee_id = f.id
              AND d.dns_id = dns.id
              AND d.ip_id = ip.id
              AND f.currency_id = c.id
              AND c.id = cc.currency_id
              AND d.id = dfd.domain_id
              AND cc.user_id = '7'";

    const SSL_FROM = "
            FROM ssl_certs AS sslc LEFT JOIN domains AS d ON d.id = sslc.domain_id, ssl_accounts AS sslpa,
                 ssl_providers AS sslp, owners AS o, ssl_fees AS f, currencies AS c, currency_conversions AS cc,
                 ssl_cert_types AS sslcf, ip_addresses AS ip, categories AS cat, ssl_cert_field_data AS sslfd
            WHERE sslc.account_id = sslpa.id
              AND sslpa.ssl_provider_id = sslp.id
              AND sslpa.owner_id = o.id
              AND sslc.fee_id = f.id
              AND f.currency_id = c.id
              AND c.id = cc.currency_id
              AND sslc.type_id = sslcf.id
              AND sslc.ip_id = ip.id
              AND sslc.cat_id = cat.id
              AND sslc.id = sslfd.ssl_id
              AND cc.user_id = '7'";

    // The check is kept for the request, so each test starts without it
    protected function setUp(): void
    {
        (new \ReflectionProperty(ListQuery::class, 'fixed_join_order'))->setValue(null, array());
        Hosting::forgetAvailability();
        LiveDns::resetAvailable();
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(ListQuery::class, 'fixed_join_order'))->setValue(null, array());
        Hosting::forgetAvailability();
        LiveDns::resetAvailable();
    }

    // The list's own version checks (ListQuery::dbVersion()), apart from Hosting::isAvailable()'s
    protected static function listVersionChecks(FakeDatabase $database)
    {
        return array_values(array_filter($database->queriesWith('SELECT db_version'), function ($sql) {
            return strpos($sql, 'LIMIT 1') === false;
        }));
    }

    /*
     * A list on a FakeDatabase that answers the version check with $version and the index check with the list's
     * indexes: all of them when $indexes is 1, none when 0, all but $indexes when it's a list of table.index names, or
     * $indexes itself when it's a Throwable
     */
    protected function onDatabase($class, $version, $indexes, array $answers = array())
    {
        if (!$indexes instanceof \Throwable) $indexes = $indexes === 0 ? array() : self::indexRows($class, $indexes === 1 ? array() : $indexes);
        $database = new FakeDatabase(array('SELECT db_version' => $version, 'STATISTICS' => $indexes) + $answers);
        $list = Instance::withoutConstructor($class, array('pdo' => $database, 'user_id' => 7, 'field_columns' => ''));
        return array($list, $database);
    }

    // The index check's rows for every index the list's fixed join order can use, less the $missing table.index names
    // (the custom field data index is field_data.asset_id), plus some unrelated ones
    protected static function indexRows($class, array $missing)
    {
        $rows = array(array('table_name' => $class::FIELD_DATA_TABLE, 'index_name' => 'PRIMARY', 'column_name' => 'id'),
            array('table_name' => 'domains', 'index_name' => 'domain', 'column_name' => 'domain'));
        if (!in_array('field_data.asset_id', $missing, true)) {
            $rows[] = array('table_name' => $class::FIELD_DATA_TABLE, 'index_name' => 'any_name', 'column_name' => $class::FIELD_DATA_ID);
        }
        // with the joins the Domains list adds once the 4.27.0 update is done (see DomainList::joinOrder())
        $tables = $class::JOIN_ORDER;
        if ($class === DomainList::class) {
            $tables[] = DomainList::SERVICE_JOIN;
            $tables[] = array('domain_dns_check', 'ldc', 'PRIMARY', DomainList::LIVE_DNS_JOIN);
        }
        foreach ($tables as $table) {
            if ($table[2] === '' || in_array($table[0] . '.' . $table[2], $missing, true)) continue;
            $rows[] = array('table_name' => $table[0], 'index_name' => $table[2], 'column_name' => 'id');
        }
        return $rows;
    }

    public function testHasListIndexesFrom4260()
    {
        foreach (array('4.26.0', '4.26.01', '4.27.0', '5.0.0') as $version) {
            $this->assertTrue(ListQuery::hasListIndexes($version), $version);
        }
        foreach (array('4.25.0', '4.24.02', '4.9.0', '', null, false) as $version) {
            $this->assertFalse(ListQuery::hasListIndexes($version), var_export($version, true));
        }
    }

    public function testBeforeTheUpdateTheListsStayAsTheyWere()
    {
        list($domains, $database) = $this->onDatabase(DomainList::class, '4.25.0', 1);
        $this->assertFalse($domains->fixedJoinOrder());
        $this->assertSame(self::DOMAINS_FROM, $domains->fromSql());
        // The index isn't looked up when the version is too old
        $this->assertSame(array(), $database->queriesWith('STATISTICS'));

        list($ssl) = $this->onDatabase(SslList::class, '4.25.0', 1);
        $this->assertSame(self::SSL_FROM, $ssl->fromSql());
    }

    public function testWithoutTheFieldDataIndexTheListsStayAsTheyWere()
    {
        list($domains, $database) = $this->onDatabase(DomainList::class, '4.26.0', array('field_data.asset_id'));
        $this->assertFalse($domains->fixedJoinOrder());
        $this->assertSame(self::DOMAINS_FROM, $domains->fromSql());
        $this->assertCount(1, $database->queriesWith('STATISTICS'));

        list($ssl) = $this->onDatabase(SslList::class, '4.26.0', array('field_data.asset_id'));
        $this->assertSame(self::SSL_FROM, $ssl->fromSql());

        list($none) = $this->onDatabase(SslList::class, '4.26.0', 0);
        $this->assertSame(self::SSL_FROM, $none->fromSql());
    }

    // The fixed order names each lookup's key, so a missing one would stop every list query
    public function testWithoutALookupKeyTheListsStayAsTheyWere()
    {
        foreach (array('currency_conversions.user_currency', 'ip_addresses.PRIMARY', 'registrar_accounts.PRIMARY') as $missing) {
            $this->setUp();
            list($domains) = $this->onDatabase(DomainList::class, '4.26.0', array($missing));
            $this->assertFalse($domains->fixedJoinOrder(), $missing);
            $this->assertSame(self::DOMAINS_FROM, $domains->fromSql());
        }

        list($ssl) = $this->onDatabase(SslList::class, '4.26.0', array('ssl_cert_types.PRIMARY'));
        $this->assertFalse($ssl->fixedJoinOrder());
    }

    // An index the database won't use (ignored on MariaDB, invisible on MySQL) counts as missing
    public function testAnUnusableIndexCountsAsMissing()
    {
        foreach (array(array('IGNORED' => 'YES'), array('IS_VISIBLE' => 'NO')) as $flag) {
            foreach (array('currency_conversions', DomainList::FIELD_DATA_TABLE) as $flagged_table) {
                $this->setUp();
                $rows = array_map(function ($row) use ($flag, $flagged_table) {
                    $row = array_change_key_case($row, CASE_UPPER);
                    return $row['TABLE_NAME'] === $flagged_table && $row['INDEX_NAME'] !== 'PRIMARY' ? $row + $flag : $row;
                }, self::indexRows(DomainList::class, array()));
                $database = new FakeDatabase(array('SELECT db_version' => '4.26.0', 'STATISTICS' => $rows));
                $domains = Instance::withoutConstructor(DomainList::class, array('pdo' => $database, 'user_id' => 7,
                    'field_columns' => ''));
                $this->assertFalse($domains->fixedJoinOrder(), key($flag) . ' on ' . $flagged_table);
                $this->assertSame(self::DOMAINS_FROM, $domains->fromSql());
            }
        }

        // The same flags set to their usable values change nothing
        $this->setUp();
        $rows = array_map(function ($row) {
            return $row + array('IGNORED' => 'NO', 'IS_VISIBLE' => 'YES');
        }, self::indexRows(DomainList::class, array()));
        $database = new FakeDatabase(array('SELECT db_version' => '4.26.0', 'STATISTICS' => $rows));
        $domains = Instance::withoutConstructor(DomainList::class, array('pdo' => $database, 'user_id' => 7, 'field_columns' => ''));
        $this->assertTrue($domains->fixedJoinOrder());
    }

    // The index check asks only about the list's own tables, and takes table and index names in any case
    public function testTheIndexCheckAsksAboutTheListsTables()
    {
        $rows = array_map(function ($row) {
            return array_map('strtoupper', $row);
        }, self::indexRows(DomainList::class, array()));
        $database = new FakeDatabase(array('SELECT db_version' => '4.26.0', 'STATISTICS' => $rows));
        $domains = Instance::withoutConstructor(DomainList::class, array('pdo' => $database, 'user_id' => 7, 'field_columns' => ''));
        $this->assertTrue($domains->fixedJoinOrder());

        $sql = $database->queriesWith('STATISTICS')[0];
        $this->assertStringContainsString('`TABLE_SCHEMA` = DATABASE()', $sql);
        $this->assertStringContainsString('`SEQ_IN_INDEX` = 1', $sql);
        // Eleven tables (the field data table is one of them), each a bound parameter
        $this->assertSame(11, substr_count($sql, ':table'));
    }

    public function testAFailedCheckCountsAsNo()
    {
        list($domains) = $this->onDatabase(DomainList::class, new \PDOException('no settings table'), 1);
        $this->assertFalse($domains->fixedJoinOrder());
        $this->assertSame(self::DOMAINS_FROM, $domains->fromSql());

        list($ssl) = $this->onDatabase(SslList::class, '4.26.0', new \PDOException('no access'));
        $this->assertFalse($ssl->fixedJoinOrder());
        $this->assertSame(self::SSL_FROM, $ssl->fromSql());
    }

    public function testEachListChecksItsOwnFieldDataTable()
    {
        foreach (array(DomainList::class => 'domain_field_data', SslList::class => 'ssl_cert_field_data') as $class => $table) {
            $this->assertSame($table, $class::FIELD_DATA_TABLE);
        }
        $this->assertSame('domain_id', DomainList::FIELD_DATA_ID);
        $this->assertSame('ssl_id', SslList::FIELD_DATA_ID);
    }

    public function testWithTheIndexTheDomainsListJoinsInTheWrittenOrder()
    {
        list($domains) = $this->onDatabase(DomainList::class, '4.26.0', 1);
        $this->assertTrue($domains->fixedJoinOrder());
        $from = $domains->fromSql();
        $this->assertStringStartsWith("
            FROM domains AS d STRAIGHT_JOIN domain_field_data AS dfd STRAIGHT_JOIN registrar_accounts AS ra FORCE INDEX (PRIMARY) STRAIGHT_JOIN registrars AS r FORCE INDEX (PRIMARY) STRAIGHT_JOIN owners AS o FORCE INDEX (PRIMARY) STRAIGHT_JOIN categories AS cat FORCE INDEX (PRIMARY) STRAIGHT_JOIN fees AS f FORCE INDEX (PRIMARY) STRAIGHT_JOIN currencies AS c FORCE INDEX (PRIMARY) STRAIGHT_JOIN currency_conversions AS cc FORCE INDEX (user_currency) STRAIGHT_JOIN dns AS dns FORCE INDEX (PRIMARY) STRAIGHT_JOIN ip_addresses AS ip FORCE INDEX (PRIMARY)
            WHERE ", $from);
        $this->assertSameJoin(self::DOMAINS_FROM, $from, 10, 0);
    }

    /*
     * Once the 4.27.0 update adds hosting services, the Domains list LEFT JOINs each domain's service (a domain doesn't
     * need one), in both forms: last in the fixed order, and attached to domains in the comma list, whose LEFT JOIN's ON
     * can only name domains. Nothing names the old domains.hosting_id or the hosting providers in either.
     */
    public function testTheHostingServiceJoinComesWithTheUpdate()
    {
        list($fixed) = $this->onDatabase(DomainList::class, '4.27.0', 1);
        $this->assertTrue($fixed->fixedJoinOrder());
        $from = $fixed->fromSql();
        $this->assertStringContainsString(" STRAIGHT_JOIN ip_addresses AS ip FORCE INDEX (PRIMARY) LEFT JOIN hosting_services AS hs FORCE INDEX (PRIMARY) ON hs.id = d.service_id
            WHERE ", $from);
        $this->assertSameJoin(str_replace('FROM domains AS d,', 'FROM domains AS d LEFT JOIN hosting_services AS hs ON hs.id = d.service_id,', self::DOMAINS_FROM), $from, 10, 1);

        $this->setUp();
        list($comma) = $this->onDatabase(DomainList::class, '4.27.0', array('field_data.asset_id'));
        $this->assertFalse($comma->fixedJoinOrder());
        $this->assertSame(str_replace('FROM domains AS d,', 'FROM domains AS d LEFT JOIN hosting_services AS hs ON hs.id = d.service_id,',
            self::DOMAINS_FROM), $comma->fromSql());

        foreach (array($from, $comma->fromSql()) as $sql) {
            $this->assertStringNotContainsString('hosting_id', $sql);
            $this->assertStringNotContainsString('hosting AS h', $sql);
        }

        // Before the update, neither form names the services
        $this->setUp();
        list($before) = $this->onDatabase(DomainList::class, '4.26.0', 1);
        $this->assertStringNotContainsString('hosting_services', $before->fromSql());
    }

    public function testWithTheIndexTheSslListJoinsInTheWrittenOrder()
    {
        list($ssl) = $this->onDatabase(SslList::class, '4.27.0', 1);
        $this->assertTrue($ssl->fixedJoinOrder());
        $from = $ssl->fromSql();
        $this->assertStringStartsWith("
            FROM ssl_certs AS sslc STRAIGHT_JOIN ssl_cert_field_data AS sslfd STRAIGHT_JOIN ssl_accounts AS sslpa FORCE INDEX (PRIMARY) STRAIGHT_JOIN ssl_providers AS sslp FORCE INDEX (PRIMARY) STRAIGHT_JOIN owners AS o FORCE INDEX (PRIMARY) STRAIGHT_JOIN ssl_fees AS f FORCE INDEX (PRIMARY) STRAIGHT_JOIN currencies AS c FORCE INDEX (PRIMARY) STRAIGHT_JOIN currency_conversions AS cc FORCE INDEX (user_currency) LEFT JOIN domains AS d FORCE INDEX (PRIMARY) ON d.id = sslc.domain_id STRAIGHT_JOIN ssl_cert_types AS sslcf FORCE INDEX (PRIMARY) STRAIGHT_JOIN ip_addresses AS ip FORCE INDEX (PRIMARY) STRAIGHT_JOIN categories AS cat FORCE INDEX (PRIMARY)
            WHERE ", $from);
        $this->assertSameJoin(self::SSL_FROM, $from, 10, 1);
    }

    /*
     * The fixed order's FROM has the same tables (each once, the asset first and its custom field data second) and the
     * same WHERE as before, with STRAIGHT_JOIN between them ($straight of them) and each lookup read through its key. A
     * LEFT JOIN ($left of them) keeps its ON condition, the same in both forms.
     */
    protected function assertSameJoin($before, $after, $straight, $left)
    {
        list($before_tables, $before_where) = explode('WHERE', $before, 2);
        list($after_tables, $after_where) = explode('WHERE', $after, 2);
        $this->assertSame($before_where, $after_where);

        $split = function ($tables, $separator) {
            $list = array_map('trim', preg_split('/' . $separator . '|\sLEFT JOIN\s/',
                preg_replace(array('/^\s*FROM\s+/', '/ FORCE INDEX \(\w+\)/'), '', $tables)));
            $list = array_map(function ($table) {
                return preg_replace('/\s+/', ' ', $table);
            }, $list);
            sort($list);
            return $list;
        };
        $this->assertSame($split($before_tables, ','), $split($after_tables, 'STRAIGHT_JOIN'));
        $this->assertSame($straight, substr_count($after_tables, 'STRAIGHT_JOIN'));
        $this->assertSame($left, substr_count($after_tables, 'LEFT JOIN'));
        $this->assertSame(count($split($before_tables, ',')) - 1, $straight + $left, 'one join fewer than the tables');
        $this->assertStringNotContainsString(',', $after_tables);
        $this->assertSame($straight + $left - 1, substr_count($after_tables, 'FORCE INDEX ('), 'every lookup through its key');
    }

    public function testTheCheckRunsOncePerRequestForEachConnection()
    {
        list($domains, $database) = $this->onDatabase(DomainList::class, '4.26.0', 1);
        $domains->fromSql();
        $domains->fromSql();
        $this->assertTrue($domains->fixedJoinOrder());

        // Another Domains list on the same connection uses the answer already found
        $same = Instance::withoutConstructor(DomainList::class, array('pdo' => $database, 'user_id' => 7, 'field_columns' => ''));
        $this->assertTrue($same->fixedJoinOrder());
        // The join order's version check, and Live DNS's (see DomainList::joinOrder()), each once for the connection
        $this->assertCount(2, self::listVersionChecks($database));
        $this->assertCount(1, $database->queriesWith('STATISTICS'));

        // A list on another connection checks that one's database
        list($another, $another_database) = $this->onDatabase(DomainList::class, '4.25.0', 1);
        $this->assertFalse($another->fixedJoinOrder());
        $this->assertSame(self::DOMAINS_FROM, $another->fromSql());
        $this->assertCount(2, self::listVersionChecks($another_database));

        // The SSL list checks its own tables, even on a connection the Domains list has checked
        $ssl = Instance::withoutConstructor(SslList::class, array('pdo' => $database, 'user_id' => 7, 'field_columns' => ''));
        $this->assertFalse($ssl->fixedJoinOrder(), 'the fake answers with the Domains list\'s indexes, which lack the SSL tables\' keys');
        $this->assertCount(2, $database->queriesWith('STATISTICS'));
    }

    // The counts, the search box's counts, and the rows use fromSql(), so they gain STRAIGHT_JOIN only when it passes
    public function testListQueriesGainStraightJoinOnlyWhenTheCheckPasses()
    {
        $search = array('sql' => " AND (d.domain LIKE :sw0 ESCAPE '\\\\')", 'params' => array('sw0' => '%a%'));
        $answers = array('SELECT count(*) AS total' => array('total' => '5', 'filtered' => '2'), 'SELECT count(*)' => '5');

        foreach (array(array('4.26.0', 1, true), array('4.26.0', 0, false), array('4.25.0', 1, false)) as $case) {
            list($version, $indexes, $fixed) = $case;
            $this->setUp();
            list($domains, $database) = $this->onDatabase(DomainList::class, $version, $indexes, $answers);

            $this->assertSame(5, $domains->count(" AND d.active = '1' "));
            $this->assertSame(array(5, 2), $domains->counts(" AND d.active = '1' ", $search));
            $domains->rows(" AND d.active = '1' ", $search, ' ORDER BY d.expiry_date ASC', 0, 50);

            $list_queries = $database->queriesWith('FROM domains AS d');
            $this->assertCount(3, $list_queries);
            foreach ($list_queries as $sql) {
                $this->assertSame($fixed, strpos($sql, 'STRAIGHT_JOIN') !== false, $version . ' ' . $indexes . ': ' . $sql);
                $this->assertSame($fixed ? 0 : 1, substr_count($sql, self::DOMAINS_FROM));
            }
        }
    }

} //@formatter:on
