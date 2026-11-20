<?php
/**
 * /tests/Unit/DomainListLockTest.php
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
use DomainMOD\DomainStatus;
use DomainMOD\Tests\Support\FakeDatabase;
use DomainMOD\Tests\Support\Instance;
use DomainMOD\Tests\Support\QuotingPdo;
use DomainMOD\Tests\Support\UnconnectedDatabase;
use PHPUnit\Framework\TestCase;

/*
 * The Domains list's registry status parts: the Registry Status filter's value and SQL, the Transfer Lock column and
 * its cells, the domain cell's problem badges, and the ds1_ columns a custom field can't clash with. Whether the
 * database has the columns (DomainStatus::isAvailable()) is set directly, since there's no database here.
 */
class DomainListLockTest extends TestCase
{

    // The column classes use Currency, which takes the shared database object, so an unconnected one stands in
    protected function setUp(): void
    {
        UnconnectedDatabase::install();
    }

    protected function tearDown(): void
    {
        UnconnectedDatabase::remove();
        DomainStatus::resetAvailable();
    }

    // Whether the database has the columns: for the shared connection, a stand-in that answers isAvailable()'s two
    // queries, and for a connection given to DomainList, what isAvailable() remembered for it
    private function available($value, $pdo = null)
    {
        if ($pdo === null) {
            (new \ReflectionProperty(Database::class, 'instance'))->setValue(null, Instance::withoutConstructor(Database::class,
                array('cnxx' => new FakeDatabase(array('db_version' => $value ? '4.27.0' : '4.26.0', 'COLUMNS' => '5')))));
            return;
        }
        $property = new \ReflectionProperty(DomainStatus::class, 'available');
        $known = $property->getValue();
        $known[spl_object_id($pdo)] = $value;
        $property->setValue(null, $known);
    }

    private function settings(array $overrides = array())
    {
        return $overrides + array('s_display_domain_expiry_date' => '', 's_display_domain_fee' => '',
            's_display_domain_tld' => '', 's_display_domain_registrar' => '', 's_display_domain_account' => '',
            's_display_domain_dns' => '', 's_display_domain_ip' => '', 's_display_domain_host' => '',
            's_display_domain_category' => '', 's_display_domain_owner' => '', 's_display_domain_lock' => '',
            's_cdf_data' => array(), 's_default_currency' => 'USD', 's_default_currency_symbol' => '$',
            's_default_currency_symbol_order' => '0', 's_default_currency_symbol_space' => '0', 's_read_only' => '');
    }

    private function row(array $values = array())
    {
        return (object) ($values + array('id' => 9, 'domain' => 'example.com', 'tld' => 'com', 'active' => '1',
            'privacy' => '0', 'ds1_transfer_lock' => 0, 'ds1_epp_status' => '', 'ds1_epp_status_time' => '1970-01-01 00:00:00'));
    }

    public function testTheLockFilterNeedsTheColumns()
    {
        $this->available(true);
        $this->assertSame('unlocked', DomainList::normalizeLock('unlocked'));
        $this->assertSame('', DomainList::normalizeLock('anything'));
        $this->assertSame('', DomainList::normalizeLock(array('unlocked')));
        $this->assertSame('hold', DomainList::normalizeState(array('lock' => 'hold'))['lock']);

        DomainStatus::resetAvailable();
        $this->available(false);
        $this->assertSame('', DomainList::normalizeLock('unlocked'));
        $this->assertSame('', DomainList::normalizeState(array('lock' => 'unlocked'))['lock']);
    }

    public function testTheFilterSqlCarriesTheLockCondition()
    {
        $pdo = new QuotingPdo();
        $list = new DomainList($pdo, 1, array());
        $state = DomainList::normalizeState(array());

        foreach (DomainStatus::FILTERS as $lock) {
            $parts = $list->filterParts(array('lock' => $lock) + $state, '', '', '');
            $this->assertSame(DomainStatus::filterCondition($lock), $parts['lock_string'], $lock);
            $this->assertStringEndsWith(DomainStatus::filterCondition($lock), DomainList::filterSql($parts), $lock);
        }

        // no filter, and a state from before the filter existed
        $this->assertSame('', $list->filterParts($state, '', '', '')['lock_string']);
        unset($state['lock']);
        $this->assertSame('', $list->filterParts($state, '', '', '')['lock_string']);
        $parts = $list->filterParts($state, '', '', '');
        unset($parts['lock_string']);
        $this->assertStringNotContainsString('transfer_lock', DomainList::filterSql($parts));
    }

    public function testTheStatusColumnsHaveTheirOwnNames()
    {
        $pdo = new QuotingPdo();
        $this->available(true, $pdo);
        $sql = (new DomainList($pdo, 1, array(array('field_name' => 'transfer_lock'))))->selectSql();

        $this->assertStringContainsString('d.transfer_lock AS ds1_transfer_lock, d.epp_status AS ds1_epp_status, d.epp_status_time AS ds1_epp_status_time', $sql);
        $this->assertStringEndsWith(', dfd.transfer_lock', $sql);

        $other = new QuotingPdo();
        $this->available(false, $other);
        $this->assertStringNotContainsString('ds1_', (new DomainList($other, 1, array()))->selectSql());
    }

    public function testTheTransferLockColumn()
    {
        $this->available(true);
        $columns = new DomainListColumns($this->settings(array('s_display_domain_owner' => '1', 's_display_domain_lock' => '1')), array());

        $keys = array_column($columns->columns(), 'key');
        $this->assertSame(array('domain', 'owner', 'lock'), $keys);
        $this->assertSame('Transfer Lock', $columns->columns()[2]['title']);
        $this->assertSame(array('d.transfer_lock', 'd.epp_status_time'), $columns->columns()[2]['sort']);

        $cells = $columns->cells($this->row(array('o_id' => 1, 'owner_name' => 'Owner', 'ds1_transfer_lock' => 2,
            'ds1_epp_status' => 'clientTransferProhibited', 'ds1_epp_status_time' => '2026-11-19 10:00:00')), '2026-11-20');
        $this->assertStringContainsString('Locked', end($cells));
        $this->assertStringContainsString('#domainmod-section-registry-status', end($cells));
    }

    public function testNoColumnWithoutTheDatabaseColumnsWhateverTheSessionSays()
    {
        $this->available(false);
        $columns = new DomainListColumns($this->settings(array('s_display_domain_lock' => '1')), array());

        $this->assertSame(array('domain'), array_column($columns->columns(), 'key'));
    }

    public function testTheDomainCellShowsProblemBadges()
    {
        $this->available(false);
        $columns = new DomainListColumns($this->settings(), array());

        $cell = $columns->cells($this->row(array('ds1_epp_status' => 'serverHold')), '2026-11-20')[0];
        $this->assertStringContainsString('bi-slash-circle', $cell);

        // a row without the columns (before the database update) has none
        $plain = $this->row();
        unset($plain->ds1_epp_status);
        $this->assertStringNotContainsString('bi-slash-circle', $columns->cells($plain, '2026-11-20')[0]);
    }

} //@formatter:on
