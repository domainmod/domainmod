<?php
/**
 * /tests/Unit/HostingListColumnsTest.php
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
use DomainMOD\HostingListColumns;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\TestCase;

/*
 * The Hosting Services list's columns, sorting, search expressions, and cells, built from a copy of the session's display
 * settings. The constructor's Currency only fetches the shared Database object, so one that never connected is put in
 * its place for each test and removed again afterwards.
 */
class HostingListColumnsTest extends TestCase
{
    protected function setUp(): void
    {
        $this->setDatabase(Instance::withoutConstructor(Database::class));
    }

    protected function tearDown(): void
    {
        $this->setDatabase(null);
    }

    // The name, hostname, and billing term always show, the hostname straight after the name
    public function testTheNameTermAndHostnameShowWhenEveryOptionalColumnIsOff()
    {
        $columns = new HostingListColumns(HostingListColumns::sessionCopy(array()), array());

        $this->assertSame(array('name', 'hostname', 'term'), array_column($columns->columns(), 'key'));
        $this->assertSame(array(array(0, 'asc')), $columns->defaultOrder());
        $this->assertSame(array('lower' => array(), 'typed' => array(), 'idn' => array(), 'encoded' => array('hs.name', 'hs.hostname')),
            $columns->searchExpressions());
    }

    public function testEveryColumnInOrderWithItsTitle()
    {
        $columns = new HostingListColumns($this->settings(), $this->fields());

        $this->assertSame(array('name', 'hostname', 'expiry', 'term', 'fee', 'yearly', 'provider', 'account', 'type',
            'ip', 'category', 'owner', 'cf_plan_code', 'cf_renewed'), array_column($columns->columns(), 'key'));
        $this->assertSame(array('Name', 'Hostname', 'Renewal Date', 'Billing Term', 'Fee', 'Yearly Cost', 'Provider',
            'Account', 'Type', 'IP Address', 'Category', 'Owner', 'Plan Code', 'Renewed'),
            array_column($columns->columns(), 'title'));
        // The renewal date is the default order when it shows
        $this->assertSame(array(array(2, 'asc')), $columns->defaultOrder());
    }

    public function testTheSortMapUsesFixedExpressions()
    {
        $map = (new HostingListColumns($this->settings(), $this->fields()))->sortMap();

        $this->assertSame(array('hs.name'), $map[0]);
        $this->assertSame(array('hs.hostname'), $map[1]);
        $this->assertSame(array('hs.expiry_date'), $map[2]);
        $this->assertSame(array('hs.billing_term'), $map[3]);
        $this->assertSame(array('hs.total_cost * cc.conversion'), $map[4]);
        $this->assertSame(array('(CASE WHEN hs.billing_term IN (1, 3, 6, 12, 24, 36) THEN hs.total_cost * cc.conversion'
            . ' * 12 / hs.billing_term ELSE NULL END)'), $map[5]);
        $this->assertSame(array('h.name', 'o.name', 'hpa.username'), $map[7]);
        $this->assertSame(array('hsfd.plan_code'), $map[12]);
    }

    public function testSearchExpressionsFollowTheVisibleColumns()
    {
        $search = (new HostingListColumns($this->settings(), $this->fields()))->searchExpressions();

        // No domain is searched, and a service with no IP address is searched as the "None" its cell shows, so an
        // excluded word never turns its condition NULL
        $this->assertSame(array(), $search['lower']);
        $this->assertSame(array(), $search['idn']);
        $this->assertSame(array('hs.name', 'hs.hostname', 'h.name', "CONCAT(h.name, ', ', o.name, ' (', hpa.username, ')')",
            'ht.type', "COALESCE(CONCAT(ip.name, ' (', ip.ip, ')'), 'None')", 'cat.name', 'o.name'), $search['encoded']);
        $this->assertSame(array('CAST(hs.expiry_date AS CHAR)', 'hsfd.plan_code'), $search['typed']);
    }

    public function testSessionCopyKeepsOnlyTheListsKeys()
    {
        $copy = HostingListColumns::sessionCopy(array('s_display_hosting_fee' => '1', 's_user_id' => 5));

        $this->assertSame(HostingListColumns::SESSION_KEYS, array_keys($copy));
        $this->assertSame('1', $copy['s_display_hosting_fee']);
    }

    public function testCellsLinkToTheHostingPages()
    {
        $settings = $this->settings();
        $settings['s_display_hosting_fee'] = '0'; // the fee goes through Currency::format(), which needs a language
        $cells = (new HostingListColumns($settings, $this->fields()))->cells($this->row(), '2026-11-15');

        $this->assertStringContainsString('aria-label="Pending (Setup)"', $cells[0]);
        $this->assertStringEndsWith('&nbsp;<a href="edit.php?hsid=7">a &amp; b</a>', $cells[0]);
        $this->assertSame('<a href="edit.php?hsid=7" class="domainmod-css-expired" data-bs-toggle="tooltip"'
            . ' data-bs-title="Renewal date passed" aria-label="2026-01-02, Renewal date passed">'
            . '<i class="bi bi-exclamation-triangle" aria-hidden="true"></i>&nbsp;2026-01-02</a>', $cells[2]);
        $this->assertSame('3 Months', $cells[3]);
        $this->assertSame('web-01.example.com', $cells[1]);
        $this->assertSame('<a href="../assets/edit/host.php?whid=4">Host</a>', $cells[4]);
        $this->assertStringEndsWith('(<a href="../assets/edit/hosting-provider-account.php?hpaid=6">abcdefghijklmno...</a>)', $cells[5]);
        $this->assertSame('<a href="../assets/edit/hosting-type.php?htid=8">VPS</a>', $cells[6]);
        $this->assertSame('<a href="../assets/edit/ip-address.php?ipid=9">Main (10.0.0.1)</a>', $cells[7]);
        $this->assertSame('P1', $cells[10]);
        $this->assertSame('Yes', $cells[11]);
    }

    /*
     * An Active, auto-renewing service's passed date waits for its renewal to be confirmed (amber, with an hourglass,
     * "Confirm renewal") for 14 days, then turns overdue (red, with a warning sign, "Renewal date passed"), the accessible
     * name keeping the date either way. With auto-renew off it's overdue at once, and the renewal date itself isn't
     * marked.
     */
    public function testAPassedDateShowsItsRenewalState()
    {
        $columns = new HostingListColumns(array('s_display_hosting_expiry_date' => '1'), array());
        $row = $this->row();
        $row->active = '1';

        $this->assertSame('<a href="edit.php?hsid=7" class="domainmod-css-confirm" data-bs-toggle="tooltip"'
            . ' data-bs-title="Confirm renewal" aria-label="2026-01-02, Confirm renewal">'
            . '<i class="bi bi-hourglass-split" aria-hidden="true"></i>&nbsp;2026-01-02</a>', $columns->cells($row, '2026-01-03')[2]);
        $this->assertStringContainsString('class="domainmod-css-confirm"', $columns->cells($row, '2026-01-16')[2]);
        $this->assertSame('<a href="edit.php?hsid=7" class="domainmod-css-expired" data-bs-toggle="tooltip"'
            . ' data-bs-title="Renewal date passed" aria-label="2026-01-02, Renewal date passed">'
            . '<i class="bi bi-exclamation-triangle" aria-hidden="true"></i>&nbsp;2026-01-02</a>', $columns->cells($row, '2026-01-17')[2]);
        $this->assertSame('<a href="edit.php?hsid=7">2026-01-02</a>', $columns->cells($row, '2026-01-02')[2]);

        $row->autorenew = '0';
        $this->assertStringContainsString('class="domainmod-css-expired"', $columns->cells($row, '2026-01-03')[2]);
    }

    public function testACancelledServicesDateIsNotMarked()
    {
        $row = $this->row();
        $row->active = '0';
        $cells = (new HostingListColumns(array('s_display_hosting_expiry_date' => '1'), array()))->cells($row, '2026-11-15');

        $this->assertStringContainsString('aria-label="Cancelled"', $cells[0]);
        $this->assertSame('<a href="edit.php?hsid=7">2026-01-02</a>', $cells[2]);
    }

    // There's no domain column, whatever the old Display Settings key says
    public function testThereIsNoDomainColumn()
    {
        $columns = new HostingListColumns(array('s_display_hosting_domain' => '1'), array());
        $this->assertNotContains('domain', array_column($columns->columns(), 'key'));
        $this->assertNotContains('s_display_hosting_domain', HostingListColumns::SESSION_KEYS);
    }

    // No IP address (ip_id 0, or a gone IP address, both a NULL ip.id from the LEFT JOIN) shows as "None"
    public function testAServiceWithNoIpAddressShowsNone()
    {
        $row = $this->row();
        $row->ip_id = null;
        $row->ip_name = null;
        $row->ip = null;
        $cells = (new HostingListColumns(array('s_display_hosting_ip' => '1'), array()))->cells($row, '2026-11-15');
        $this->assertSame('None', $cells[3]);
    }

    // A service with no fee shows "No fee" in the fee and yearly cells, linked to Add Hosting Fee with its provider,
    // type, and term under the installation's base path
    public function testAServiceWithNoFeeLinksToAddTheFee()
    {
        $row = $this->row();
        $row->f_id = null;
        $row->conversion = null;
        $cells = (new HostingListColumns(array('s_display_hosting_fee' => '1'), array(), '/dm'))->cells($row, '2026-11-15');

        $link = '<a href="/dm/assets/add/hosting-fee.php?hosting_id=4&amp;type_id=8&amp;billing_term=3">No fee</a>';
        $this->assertSame($link, $cells[3]);
        $this->assertSame($link, $cells[4]);
    }

    // A read-only user, whom the fee page would refuse, sees plain text
    public function testAReadOnlyUserSeesNoFeeAsPlainText()
    {
        $row = $this->row();
        $row->f_id = null;
        $row->conversion = null;
        $cells = (new HostingListColumns(array('s_display_hosting_fee' => '1', 's_read_only' => '1'), array(), '/dm'))
            ->cells($row, '2026-11-15');

        $this->assertSame('No fee', $cells[3]);
        $this->assertSame('No fee', $cells[4]);
    }

    // With a fee but no conversion rate for the user, the cells say so, never a zero cost
    public function testAFeeWithNoConversionRateShowsNoRate()
    {
        $row = $this->row();
        $row->conversion = null;
        $cells = (new HostingListColumns(array('s_display_hosting_fee' => '1'), array(), '/dm'))->cells($row, '2026-11-15');

        $this->assertSame('No rate', $cells[3]);
        $this->assertSame('No rate', $cells[4]);
    }

    // A term that isn't an allowed one shows as its number
    public function testAnInvalidTermShowsAsItsNumber()
    {
        $row = $this->row();
        $row->billing_term = '5';
        $cells = (new HostingListColumns(array(), array()))->cells($row, '2026-11-15');
        $this->assertSame('5', $cells[2]);
    }

    private function fields()
    {
        return array(
            array('id' => 1, 'name' => 'Plan Code', 'field_name' => 'plan_code', 'type_id' => 2),
            array('id' => 2, 'name' => 'Renewed', 'field_name' => 'renewed', 'type_id' => 1),
            array('id' => 3, 'name' => 'Long Notes', 'field_name' => 'long_notes', 'type_id' => 3));
    }

    private function settings()
    {
        $settings = array();
        foreach (HostingListColumns::OPTIONAL as $optional) {
            if ($optional[0] !== '') $settings[$optional[0]] = '1';
        }
        $settings['s_chf_data'] = array(
            array('name' => 'Plan Code', 'field' => 'plan_code', 'type_id' => '2', 'value' => '1'),
            array('name' => 'Renewed', 'field' => 'renewed', 'type_id' => '1', 'value' => '1'),
            array('name' => 'Long Notes', 'field' => 'long_notes', 'type_id' => '3', 'value' => '1'),
            array('name' => 'Gone', 'field' => 'gone', 'type_id' => '2', 'value' => '1'));
        return HostingListColumns::sessionCopy($settings);
    }

    private function row()
    {
        return (object) array('id' => 7, 'name' => 'a &amp; b', 'active' => '5', 'expiry_date' => '2026-01-02', 'autorenew' => '1',
            'billing_term' => '3', 'hostname' => 'web-01.example.com', 'h_id' => 4, 'provider_id' => 4,
            'hosting_provider_name' => 'Host', 'f_id' => 11, 'total_cost' => '10', 'conversion' => '1', 'o_id' => 5, 'owner_name' => 'Own', 'hpa_id' => 6,
            'username' => 'abcdefghijklmnopq', 'type_id' => 8, 'type' => 'VPS', 'ip_id' => 9, 'ip_name' => 'Main',
            'ip' => '10.0.0.1', 'cat_id' => 10, 'cat_name' => 'Cat', 'cf1_plan_code' => 'P1', 'cf1_renewed' => '1');
    }

    private function setDatabase($value)
    {
        (new \ReflectionProperty(Database::class, 'instance'))->setValue(null, $value);
    }

} //@formatter:on
