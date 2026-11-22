<?php
/**
 * /tests/Unit/ChangeHistoryHostingTest.php
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

use DomainMOD\ChangeHistory;
use DomainMOD\ChangeHistoryList;
use DomainMOD\ChangeHistoryListColumns;
use DomainMOD\CustomFieldSchema;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/*
 * Hosting services in the Change History: which columns are recorded and which aren't, the fields that follow the
 * account, the custom field tables and display columns, and how the Change History page names and shows them
 */
#[RunTestsInSeparateProcesses]
class ChangeHistoryHostingTest extends TestCase
{
    protected function setUp(): void
    {
        define('WEB_ROOT', '/dm');
        define('SOFTWARE_TITLE', 'DomainMOD');
    }

    public function testEveryRecordedColumnIsListed()
    {
        $this->assertSame(array('name' => 'text', 'account_id' => 'id', 'owner_id' => 'id', 'hosting_id' => 'id',
            'type_id' => 'id', 'ip_id' => 'id', 'cat_id' => 'id', 'hostname' => 'text', 'billing_term' => 'int',
            'renewal_day' => 'int', 'expiry_date' => 'date', 'autorenew' => 'int', 'notes' => 'long', 'active' => 'int',
            'total_cost' => 'cost'), ChangeHistory::FIELDS['hosting']);
        // A service no longer has a single domain (domains pick their service in 13a), and Server became Hostname
        $this->assertArrayNotHasKey('domain_id', ChangeHistory::FIELDS['hosting']);
        $this->assertArrayNotHasKey('server', ChangeHistory::FIELDS['hosting']);
        $this->assertSame(array('creation_type_id', 'created_by', 'insert_time'), ChangeHistory::SNAPSHOT_FACTS['hosting']);
    }

    // Internal columns, and the old service username (on the deny list), are never recorded
    public function testSkippedColumnsAreNotRecorded()
    {
        foreach (array('revision', 'fee_id', 'fee_fixed', 'username', 'creation_type_id', 'created_by', 'insert_time',
                     'update_time') as $column) {
            $this->assertArrayNotHasKey($column, ChangeHistory::FIELDS['hosting'], $column);
        }
        $this->assertContains('username', ChangeHistory::DENY);
        $this->assertSame(array(), array_intersect(array_keys(ChangeHistory::FIELDS['hosting']), ChangeHistory::DENY));
    }

    // The owner and provider are recorded only when they change without the account changing, as for SSL
    public function testTheOwnerAndProviderFollowTheAccount()
    {
        $this->assertSame(array('owner_id', 'hosting_id'), ChangeHistory::FOLLOWS_ACCOUNT['hosting']);
        foreach (ChangeHistory::FOLLOWS_ACCOUNT['hosting'] as $column) {
            $this->assertArrayHasKey($column, ChangeHistory::FIELDS['hosting']);
        }
    }

    // Every recorded column and the field data's key are real columns of the installer's tables
    public function testTheRecordedColumnsMatchTheInstaller()
    {
        $install = file_get_contents(DIR_ROOT . '/install/go.php');
        $this->assertSame(1, preg_match('/CREATE TABLE IF NOT EXISTS `hosting_services` \((.*?)\) ENGINE/s', $install, $match));
        foreach (array_keys(ChangeHistory::FIELDS['hosting']) as $column) {
            $this->assertStringContainsString('`' . $column . '`', $match[1], $column);
        }
        $info = ChangeHistory::ITEM_TYPES['hosting'];
        $this->assertSame(array('hosting_services', 'name', 'hosting_service_field_data', 'service_id',
            'hosting_service_fields', 'hosting_fees'), array($info['table'], $info['name'], $info['field_data'],
            $info['field_id'], $info['fields'], $info['fee_table']));
        $this->assertSame(1, preg_match('/CREATE TABLE IF NOT EXISTS `hosting_service_field_data` \((.*?)\) ENGINE/s', $install, $match));
        $this->assertStringContainsString('`service_id`', $match[1]);
    }

    // Each item type's display columns have their own prefix, and none starts with another, so one type's clean-up never
    // matches another's columns
    public function testEachTypeHasItsOwnDisplayColumns()
    {
        $this->assertSame('dispchf_', CustomFieldSchema::displayPrefix('hosting'));
        $this->assertSame('dispcsf_', CustomFieldSchema::displayPrefix('ssl'));
        $this->assertSame('dispcdf_', CustomFieldSchema::displayPrefix('domain'));
        foreach (CustomFieldSchema::DISPLAY_PREFIXES as $type => $prefix) {
            foreach (CustomFieldSchema::DISPLAY_PREFIXES as $other => $other_prefix) {
                if ($type !== $other) $this->assertStringStartsNotWith($other_prefix, $prefix);
            }
        }
        $this->expectException(\InvalidArgumentException::class);
        CustomFieldSchema::displayPrefix('nope');
    }

    public function testTheTypeFilterTakesHostingServices()
    {
        $state = ChangeHistoryList::normalizeState(array('type' => 'hosting', 'id' => '7'));
        $this->assertSame(array('hosting', 7), array($state['type'], $state['id']));
        $this->assertSame('Hosting Service', ChangeHistoryListColumns::typeName('hosting'));
    }

    public function testEveryRecordedFieldHasALabel()
    {
        $names = ChangeHistoryListColumns::fieldNames('hosting');
        foreach (array_keys(ChangeHistory::FIELDS['hosting']) as $field) {
            $this->assertArrayHasKey($field, $names, $field);
        }
        $this->assertSame('Renewal Date', $names['expiry_date']);
        $this->assertSame('Account', $names['account_id']);
        $this->assertSame('Type', $names['type_id']);
        $this->assertSame('Hostname', $names['hostname']);
        $this->assertSame('Hosting Provider', $names['hosting_id']);
        $this->assertArrayNotHasKey('domain_id', $names);
        $this->assertArrayNotHasKey('server', $names);
    }

    // A domain records its hosting service (13a) in place of the old provider, which domains no longer name
    public function testADomainRecordsItsHostingService()
    {
        $this->assertSame('id', ChangeHistory::FIELDS['domain']['service_id']);
        $this->assertArrayNotHasKey('hosting_id', ChangeHistory::FIELDS['domain']);
        $this->assertNotContains('service_id', ChangeHistory::FOLLOWS_ACCOUNT['domain']);
        $install = file_get_contents(DIR_ROOT . '/install/go.php');
        $this->assertSame(1, preg_match('/CREATE TABLE IF NOT EXISTS `domains` \((.*?)\) ENGINE/s', $install, $match));
        $this->assertStringContainsString('`service_id`', $match[1]);
        $this->assertStringNotContainsString('`hosting_id`', $match[1]);
        // Its name at the time is read from the services (a plain read, as every display name is)
        $operation = file_get_contents(DIR_ROOT . '/classes/DomainMOD/ChangeHistoryOperation.php');
        $this->assertStringContainsString("'service_id' => \"SELECT id, `name` FROM hosting_services WHERE id IN (%s)\"", $operation);
    }

    public function testADomainsHostingServiceReadsInWords()
    {
        $names = ChangeHistoryListColumns::fieldNames('domain');
        $this->assertSame('Hosting Service', $names['service_id']);
        $this->assertArrayNotHasKey('hosting_id', $names);
        $columns = new ChangeHistoryListColumns(array('s_default_timezone' => 'UTC'));
        // None (0), or a service deleted before the change was recorded, reads None
        $this->assertSame('None', $columns->valueText('domain', 'service_id', '0', ''));
        $this->assertSame('None', $columns->valueText('domain', 'service_id', '12', ''));
        $this->assertSame('Shared Pro', $columns->valueText('domain', 'service_id', '12', 'Shared Pro'));
        $this->assertSame('Change Hosting Service', ChangeHistoryListColumns::actionNames()['bulk_CWH']);
    }

    public function testAnItemLinksToItsEditPage()
    {
        $row = (object) array('id' => 10, 'item_type' => 'hosting', 'item_id' => 4, 'change_type' => 'update',
            'last_delete_id' => null);
        $this->assertSame('/dm/hosting/edit.php?hsid=4', ChangeHistoryListColumns::itemUrl($row));
    }

    public function testValuesReadInWords()
    {
        $columns = new ChangeHistoryListColumns(array('s_default_timezone' => 'UTC'));
        $this->assertSame('Cancelled', $columns->valueText('hosting', 'active', '0', ''));
        $this->assertSame('Pending (Setup)', $columns->valueText('hosting', 'active', '5', ''));
        $this->assertSame('12 Months', $columns->valueText('hosting', 'billing_term', '12', ''));
        $this->assertSame('1 Month', $columns->valueText('hosting', 'billing_term', '1', ''));
        // An IP address is optional: 0, or one deleted before the change was recorded, reads None
        $this->assertSame('None', $columns->valueText('hosting', 'ip_id', '0', ''));
        $this->assertSame('None', $columns->valueText('hosting', 'ip_id', '9', ''));
        $this->assertSame('Web 1 (192.0.2.1)', $columns->valueText('hosting', 'ip_id', '9', 'Web 1 (192.0.2.1)'));
        // Other ids keep their number when the name is gone
        $this->assertSame('#4', $columns->valueText('hosting', 'cat_id', '4', ''));
        $this->assertSame('web-01.example.com', $columns->valueText('hosting', 'hostname', 'web-01.example.com', ''));
        $this->assertSame('Yes', $columns->valueText('hosting', 'autorenew', '1', ''));
        $this->assertSame('2026-12-01', $columns->valueText('hosting', 'expiry_date', '2026-12-01', ''));
        // A domain's status 0 still reads Expired
        $this->assertSame('Expired', $columns->valueText('domain', 'active', '0', ''));
    }

    public function testHostingActionsAreNamed()
    {
        $names = ChangeHistoryListColumns::actionNames();
        foreach (array('hosting_fee_add', 'hosting_fee_edit', 'zero_out_hosting_fees', 'hosting_account_edit',
                     'hosting_provider_delete', 'hosting_type_delete', 'update_hosting_fees', 'hosting_renew') as $action) {
            $this->assertNotSame('', $names[$action] ?? '', $action);
        }
        $this->assertSame('Asset Change: Hosting Account Changed',
            ChangeHistoryListColumns::sourceText('asset', 'hosting_account_edit', ''));
        $this->assertSame('Add or Edit Page: Marked Renewed', ChangeHistoryListColumns::sourceText('user', 'hosting_renew', ''));
        // The retired auto-renew task's action (only a pre-release database can have one) shows its source alone
        $this->assertSame('Maintenance', ChangeHistoryListColumns::sourceText('maintenance', 'hosting_auto_renew', ''));
    }

    // A recorded action never asks Hosting::isAvailable() (a plain read, which would open the transaction's snapshot
    // before the write site's own locking reads): a recorded action means the hosting tables are there
    public function testRecordingNeverOpensTheSnapshotThroughHostingIsAvailable()
    {
        $operation = file_get_contents(DIR_ROOT . '/classes/DomainMOD/ChangeHistoryOperation.php');
        $this->assertStringNotContainsString('Hosting::isAvailable(', preg_replace('#//[^\n]*#', '', $operation));
    }
} //@formatter:on
