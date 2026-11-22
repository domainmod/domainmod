<?php
/**
 * /tests/Unit/LiveDnsListTest.php
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
use DomainMOD\DomainList;
use DomainMOD\DomainListColumns;
use DomainMOD\ListQuery;
use DomainMOD\LiveDns;
use DomainMOD\Tests\Support\FakeDatabase;
use DomainMOD\Tests\Support\Instance;
use DomainMOD\Tests\Support\QuotingPdo;
use DomainMOD\Tests\Support\UnconnectedDatabase;
use PHPUnit\Framework\TestCase;

/*
 * The Domains list's Live DNS parts: the filter's value and SQL, the join (in both join modes, and only while the
 * database has the table), the ldc1_ columns, the column, its badges, sort, and export text. Whether the database has
 * the table (LiveDns::isAvailable()) is set directly, since there's no database here.
 */
class LiveDnsListTest extends TestCase
{

    protected function setUp(): void
    {
        UnconnectedDatabase::install();
        (new \ReflectionProperty(ListQuery::class, 'fixed_join_order'))->setValue(null, array());
    }

    protected function tearDown(): void
    {
        UnconnectedDatabase::remove();
        LiveDns::resetAvailable();
        (new \ReflectionProperty(ListQuery::class, 'fixed_join_order'))->setValue(null, array());
    }

    // Whether the database has the table: for the shared connection, a stand-in that answers isAvailable()'s two
    // queries, and for a connection given to DomainList, what isAvailable() remembered for it
    private function available($value, $pdo = null)
    {
        if ($pdo === null) {
            (new \ReflectionProperty(Database::class, 'instance'))->setValue(null, Instance::withoutConstructor(Database::class,
                array('cnxx' => new FakeDatabase(array('db_version' => $value ? '4.27.0' : '4.26.0',
                    'COLUMNS' => (string) (count(LiveDns::TABLE_COLUMNS) + 1))))));
            return;
        }
        $property = new \ReflectionProperty(LiveDns::class, 'available');
        $known = $property->getValue();
        $known[spl_object_id($pdo)] = $value;
        $property->setValue(null, $known);
    }

    // A list whose join order is decided ($fixed), on a connection where Live DNS is or isn't available
    private function domainList($live_dns, $fixed)
    {
        $pdo = new QuotingPdo();
        $this->available($live_dns, $pdo);
        $list = new DomainList($pdo, 1, array());
        $known = (new \ReflectionProperty(ListQuery::class, 'fixed_join_order'));
        $known->setValue(null, array(DomainList::class . '#' . spl_object_id($pdo) => $fixed));
        return $list;
    }

    private function settings(array $overrides = array())
    {
        return $overrides + array('s_display_domain_expiry_date' => '', 's_display_domain_fee' => '',
            's_display_domain_tld' => '', 's_display_domain_registrar' => '', 's_display_domain_account' => '',
            's_display_domain_dns' => '', 's_display_domain_ip' => '', 's_display_domain_host' => '',
            's_display_domain_category' => '', 's_display_domain_owner' => '', 's_display_domain_lock' => '',
            's_display_domain_live_dns' => '', 's_cdf_data' => array(), 's_default_currency' => 'USD',
            's_default_currency_symbol' => '$', 's_default_currency_symbol_order' => '0',
            's_default_currency_symbol_space' => '0', 's_read_only' => '');
    }

    private function row(array $values = array())
    {
        return (object) ($values + array('id' => 9, 'domain' => 'example.com', 'tld' => 'com', 'active' => '1',
            'privacy' => '0'));
    }

    public function testTheFilterNeedsTheTable()
    {
        $this->available(true);
        foreach (array_keys(LiveDns::FILTERS) as $value) $this->assertSame($value, DomainList::normalizeLiveDns($value));
        $this->assertSame('', DomainList::normalizeLiveDns('anything'));
        $this->assertSame('', DomainList::normalizeLiveDns(array('ns')));
        $this->assertSame('ip', DomainList::normalizeState(array('livedns' => 'ip'))['livedns']);

        LiveDns::resetAvailable();
        $this->available(false);
        $this->assertSame('', DomainList::normalizeLiveDns('ns'));
        $this->assertSame('', DomainList::normalizeState(array('livedns' => 'ns'))['livedns']);
    }

    // Each value is fixed SQL with no parameters: an EXISTS on the check stored for the domain's current name, with
    // literal badges, or, for Not checked, a NOT EXISTS, always naming the outer domains table
    public function testEachFilterValueGivesItsFixedSql()
    {
        $this->assertSame(" AND EXISTS (SELECT 1 FROM domain_dns_check AS ldcf WHERE ldcf.domain_id = d.id AND ldcf.domain = d.domain AND ldcf.list_state IN ('both', 'ns', 'ip')) ",
            LiveDns::filterCondition('any'));
        $this->assertStringContainsString("IN ('both', 'ns')", LiveDns::filterCondition('ns'));
        $this->assertStringContainsString("IN ('both', 'ip')", LiveDns::filterCondition('ip'));
        $this->assertStringContainsString("IN ('no_dns')", LiveDns::filterCondition('nodns'));
        $this->assertStringContainsString("IN ('failed')", LiveDns::filterCondition('failed'));
        $this->assertSame(" AND NOT EXISTS (SELECT 1 FROM domain_dns_check AS ldcf WHERE ldcf.domain_id = domains.id AND ldcf.domain = domains.domain) ",
            LiveDns::filterCondition('unchecked', 'domains'));
        $this->assertSame('', LiveDns::filterCondition(''));
        $this->assertSame('', LiveDns::filterCondition("ns' OR 1=1"));
        $this->assertSame('', LiveDns::filterCondition(array('ns')));
        $this->assertStringNotContainsString(':', LiveDns::filterCondition('any'));
    }

    public function testTheFilterSqlCarriesTheCondition()
    {
        $pdo = new QuotingPdo();
        $list = new DomainList($pdo, 1, array());
        $state = DomainList::normalizeState(array());

        foreach (array_keys(LiveDns::FILTERS) as $value) {
            $parts = $list->filterParts(array('livedns' => $value) + $state, '', '', '');
            $this->assertSame(LiveDns::filterCondition($value), $parts['livedns_string'], $value);
            $this->assertStringEndsWith(LiveDns::filterCondition($value), DomainList::filterSql($parts), $value);
        }
        unset($state['livedns']);
        $this->assertSame('', $list->filterParts($state, '', '', '')['livedns_string']);
    }

    // The check is left joined for the domain's current name, last in the fixed order (after the hosting service's join,
    // once there is one), and straight after domains in the comma list, only while the database has the table, so a domain with no row is still listed
    public function testTheJoinInBothModesOnlyWhileAvailable()
    {
        $fixed = $this->domainList(true, true)->fromSql();
        $this->assertStringContainsString('ip_addresses AS ip FORCE INDEX (PRIMARY) LEFT JOIN domain_dns_check AS ldc FORCE INDEX (PRIMARY) ON ldc.domain_id = d.id AND ldc.domain = d.domain', $fixed);

        $fallback = $this->domainList(true, false)->fromSql();
        $this->assertStringContainsString('FROM domains AS d LEFT JOIN domain_dns_check AS ldc ON ldc.domain_id = d.id AND ldc.domain = d.domain, registrar_accounts AS ra', $fallback);
        $this->assertStringNotContainsString('ldc.', preg_replace('/LEFT JOIN domain_dns_check AS ldc ON [^,]+,/', '', $fallback));

        foreach (array(true, false) as $mode) {
            $sql = $this->domainList(false, $mode)->fromSql();
            $this->assertStringNotContainsString('domain_dns_check', $sql);
        }
    }

    public function testTheBadgeColumnsHaveTheirOwnNames()
    {
        $pdo = new QuotingPdo();
        $this->available(true, $pdo);
        $sql = (new DomainList($pdo, 1, array(array('field_name' => 'list_state'))))->selectSql();
        $this->assertStringContainsString('ldc.list_state AS ldc1_list_state, ldc.result AS ldc1_result, ldc.stale_sets AS ldc1_stale_sets', $sql);
        $this->assertStringEndsWith(', dfd.list_state AS cf1_list_state', $sql);

        $other = new QuotingPdo();
        $this->available(false, $other);
        $this->assertStringNotContainsString('ldc1_', (new DomainList($other, 1, array()))->selectSql());
    }

    // The sort ranks the badges in LIST_STATES' order, with no row first
    public function testTheSortFollowsTheBadgeOrder()
    {
        foreach (LiveDns::LIST_STATES as $rank => $state) {
            if ($state === 'unchecked') {
                $this->assertStringContainsString('WHEN ldc.list_state IS NULL THEN ' . $rank . ' ', LiveDns::LIST_SORT);
            } elseif ($rank < count(LiveDns::LIST_STATES) - 1) {
                $this->assertStringContainsString("WHEN ldc.list_state = '" . $state . "' THEN " . $rank . ' ', LiveDns::LIST_SORT);
            } else {
                $this->assertStringEndsWith('ELSE ' . $rank . ' END', LiveDns::LIST_SORT);
            }
        }
        $this->assertSame('unchecked', LiveDns::LIST_STATES[0]);
        $this->assertSame('ok', LiveDns::LIST_STATES[count(LiveDns::LIST_STATES) - 1]);
    }

    public function testTheColumnAndItsBadges()
    {
        $this->available(true);
        $columns = new DomainListColumns($this->settings(array('s_display_domain_live_dns' => '1')), array());

        $this->assertSame(array('domain', 'live_dns'), array_column($columns->columns(), 'key'));
        $this->assertSame('Live DNS', $columns->columns()[1]['title']);
        $this->assertSame(array(LiveDns::LIST_SORT), $columns->columns()[1]['sort']);

        $cell = $columns->cells($this->row(array('ldc1_list_state' => 'ns', 'ldc1_result' => 'timeout', 'ldc1_stale_sets' => 'mx')), '2026-11-20')[1];
        $this->assertStringContainsString('Name Servers Differ', $cell);
        $this->assertStringContainsString('bi-exclamation-triangle', $cell);
        $this->assertStringContainsString('data-bs-title="Name Servers Differ (last check failed)"', $cell);
        $this->assertStringContainsString('edit.php?did=9#domainmod-section-live-dns', $cell);

        // No row for the domain's current name (a NULL from the left join)
        $cell = $columns->cells($this->row(array('ldc1_list_state' => null, 'ldc1_result' => null, 'ldc1_stale_sets' => null)), '2026-11-20')[1];
        $this->assertStringContainsString('Not Checked', $cell);
        $this->assertStringNotContainsString('data-bs-title', $cell);
    }

    public function testTheExportTextHasTheSuffix()
    {
        $this->assertSame('OK', LiveDns::exportText($this->row(array('ldc1_list_state' => 'ok', 'ldc1_result' => 'ok', 'ldc1_stale_sets' => ''))));
        $this->assertSame('No DNS (last check was incomplete)', LiveDns::exportText($this->row(array('ldc1_list_state' => 'no_dns',
            'ldc1_result' => 'no_dns', 'ldc1_stale_sets' => 'www_aaaa'))));
        $this->assertSame('Check Failed (last check failed)', LiveDns::exportText($this->row(array('ldc1_list_state' => 'failed',
            'ldc1_result' => 'failed', 'ldc1_stale_sets' => 'ns,a,aaaa,www_a,www_aaaa,mx'))));
        $this->assertSame('Not Checked', LiveDns::exportText($this->row(array('ldc1_list_state' => null))));
    }

    public function testNoColumnWithoutTheTableWhateverTheSessionSays()
    {
        $this->available(false);
        $columns = new DomainListColumns($this->settings(array('s_display_domain_live_dns' => '1')), array());
        $this->assertSame(array('domain'), array_column($columns->columns(), 'key'));
    }

    // The data endpoint takes livedns as a single value, so an array gets its 400
    public function testTheEndpointTakesTheFilterAsASingleValue()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/domains/list-data.php');
        $this->assertMatchesRegularExpression("/single_keys = array\\([^;]*'livedns'\\);/s", $source);
        $this->assertFalse(ListQuery::requestShapeOk(array('livedns' => array('ns')), array('livedns')));
        $this->assertTrue(ListQuery::requestShapeOk(array('livedns' => 'ns'), array('livedns')));
    }

    // Every place domains/index.php builds its own filter SQL carries the Live DNS fragment
    public function testEveryFilterPathOnTheListPageIncludesIt()
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/domains/index.php');
        $this->assertSame(substr_count($source, '$lock_string_d'), substr_count($source, '$livedns_string_d'));
        $this->assertSame(substr_count($source, '$lock_string_plain'), substr_count($source, '$livedns_string_plain'));
        $this->assertStringContainsString('$lock_string
          $livedns_string" . $cf_filter[\'sql\'];', $source);
        $this->assertStringContainsString('. $livedns_string . $cf_filter[\'sql\'];', $source);
        $this->assertStringContainsString("_('Live DNS') . ':',", $source);
        $this->assertStringContainsString("|| \$lock !== '' || \$livedns !== '';", $source);
    }

} //@formatter:on
