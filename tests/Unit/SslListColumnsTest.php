<?php
/**
 * /tests/Unit/SslListColumnsTest.php
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
use DomainMOD\SslListColumns;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\TestCase;

/*
 * The SSL Certificates list's columns, sorting, search expressions, and cells, built from a copy of the session's
 * display settings. The constructor's Currency only fetches the shared Database object, so one that never connected is
 * put in its place for each test and removed again afterwards.
 */
class SslListColumnsTest extends TestCase
{
    const FIELDS_SQL = ', sslfd.purchaser_name, sslfd.renewed, sslfd.notes_area, sslfd.order_number, sslfd.issued, sslfd.checked_at, sslfd.portal';

    protected function setUp(): void
    {
        $this->setDatabase(Instance::withoutConstructor(Database::class));
    }

    protected function tearDown(): void
    {
        $this->setDatabase(null);
    }

    public function testOnlyTheNameShowsWhenEveryOptionalColumnIsOff()
    {
        $columns = new SslListColumns(SslListColumns::sessionCopy(array()), '');

        // No control column: the list keeps every column and scrolls sideways instead of using Responsive
        $this->assertSame(array('name'), array_column($columns->columns(), 'key'));
        $this->assertSame(array(0 => array('sslc.name')), $columns->sortMap());
        $this->assertSame(array(array(0, 'asc')), $columns->defaultOrder());
        $this->assertSame(array('lower' => array(), 'typed' => array(), 'idn' => array(), 'encoded' => array('sslc.name')),
            $columns->searchExpressions());
    }

    public function testEveryColumnInDisplaySettingsOrderWithItsTitle()
    {
        $columns = new SslListColumns($this->settings(), self::FIELDS_SQL);

        $this->assertSame(array('name', 'expiry', 'fee', 'domain', 'provider', 'account', 'type', 'ip', 'category',
            'owner', 'cf_purchaser_name', 'cf_renewed', 'cf_order_number', 'cf_issued', 'cf_checked_at', 'cf_portal'),
            array_column($columns->columns(), 'key'));
        $this->assertSame(array('Name', 'Expiry', 'Fee', 'Domain', 'Provider', 'Account', 'Type', 'IP Address', 'Category',
            'Owner', 'Purchaser', 'Renewed', 'Order Number', 'Issued', 'Checked At', 'Portal'),
            array_column($columns->columns(), 'title'));
        // The expiry date is the default order when it shows
        $this->assertSame(array(array(1, 'asc')), $columns->defaultOrder());
    }

    public function testTheSortMapUsesFixedExpressions()
    {
        $map = (new SslListColumns($this->settings(), self::FIELDS_SQL))->sortMap();

        $this->assertSame(array('sslc.name'), $map[0]);
        $this->assertSame(array('sslc.expiry_date'), $map[1]);
        $this->assertSame(array('sslc.total_cost * cc.conversion'), $map[2]);
        $this->assertSame(array('d.domain'), $map[3]);
        $this->assertSame(array('sslp.name', 'o.name', 'sslpa.username'), $map[5]);
        $this->assertSame(array('ip.name', 'ip.ip'), $map[7]);
        $this->assertSame(array('sslfd.purchaser_name'), $map[10]);
    }

    public function testDeletedFieldsAndTextAreasGetNoColumn()
    {
        // The session still lists every field, but only purchaser_name exists now, and notes_area is a Text Area
        $columns = new SslListColumns($this->settings(), ', sslfd.purchaser_name, sslfd.notes_area');

        $this->assertSame(array('cf_purchaser_name'), array_values(array_filter(array_column($columns->columns(), 'key'),
            function ($key) { return str_starts_with($key, 'cf_'); })));
    }

    public function testTheLayoutKeyFollowsTheVisibleColumns()
    {
        $settings = $this->settings();
        $all = new SslListColumns($settings, self::FIELDS_SQL);
        $settings['s_display_ssl_fee'] = '0';
        $no_fee = new SslListColumns($settings, self::FIELDS_SQL);

        $this->assertSame(12, strlen($all->layoutKey()));
        $this->assertSame($all->layoutKey(), (new SslListColumns($this->settings(), self::FIELDS_SQL))->layoutKey());
        $this->assertNotSame($all->layoutKey(), $no_fee->layoutKey());
    }

    public function testSearchExpressionsFollowTheVisibleColumns()
    {
        $search = (new SslListColumns($this->settings(), self::FIELDS_SQL))->searchExpressions();

        // A certificate with no domain has a NULL domain, read as '', so an excluded word doesn't hide it
        $this->assertSame(array("COALESCE(d.domain, '')"), $search['lower']);
        $this->assertSame(array("COALESCE(d.domain, '')"), $search['idn']);
        $this->assertSame(array('sslc.name', 'sslp.name', "CONCAT(sslp.name, ', ', o.name, ' (', sslpa.username, ')')",
            'sslcf.type', "CONCAT(ip.name, ' (', ip.ip, ')')", 'cat.name', 'o.name'), $search['encoded']);
        $this->assertSame(array('CAST(sslc.expiry_date AS CHAR)', 'sslfd.purchaser_name', 'sslfd.order_number',
            "CASE WHEN sslfd.issued IN ('1970-01-01', '0000-00-00') THEN '' ELSE CAST(sslfd.issued AS CHAR) END",
            "CASE WHEN sslfd.checked_at IN ('1970-01-01 00:00:00', '0000-00-00 00:00:00') THEN '' ELSE CAST(sslfd.checked_at AS CHAR) END"),
            $search['typed']);
    }

    public function testSessionCopyKeepsOnlyTheListsKeys()
    {
        $copy = SslListColumns::sessionCopy(array('s_display_ssl_fee' => '1', 's_user_id' => 5));

        $this->assertSame(SslListColumns::SESSION_KEYS, array_keys($copy));
        $this->assertSame('1', $copy['s_display_ssl_fee']);
        $this->assertSame('', $copy['s_display_ssl_owner']);
    }

    public function testCellsAreDrawnAsTheListHasAlwaysDrawnThem()
    {
        $settings = $this->settings();
        $settings['s_display_ssl_fee'] = '0'; // the fee goes through Currency::format(), which needs a language
        $cells = (new SslListColumns($settings, self::FIELDS_SQL))->cells($this->row(), '2026-11-15');

        $this->assertStringContainsString('aria-label="Pending (Renewal)"', $cells[0]);
        $this->assertStringEndsWith('&nbsp;<a href="edit.php?sslcid=7">a &amp; b</a>', $cells[0]);
        $this->assertSame('<a href="edit.php?sslcid=7" class="domainmod-css-expired" data-bs-toggle="tooltip"'
            . ' data-bs-title="Expired" aria-label="2026-01-02, Expired">2026-01-02</a>', $cells[1]);
        $this->assertSame('<a href="../domains/edit.php?did=3">example.com</a>', $cells[2]);
        $this->assertSame('<a href="../assets/edit/ssl-provider.php?sslpid=4">Prov</a>', $cells[3]);
        $this->assertSame('<a href="../assets/edit/ssl-provider.php?sslpid=4">Prov</a>, '
            . '<a href="../assets/edit/account-owner.php?oid=5">Own</a> '
            . '(<a href="../assets/edit/ssl-provider-account.php?sslpaid=6">abcdefghijklmno...</a>)', $cells[4]);
        $this->assertSame('<a href="../assets/edit/ssl-type.php?ssltid=8">Web</a>', $cells[5]);
        $this->assertSame('<a href="../assets/edit/ip-address.php?ipid=9">Main (10.0.0.1)</a>', $cells[6]);
        $this->assertSame('<a href="../assets/edit/category.php?pcid=10">Cat</a>', $cells[7]);
        $this->assertSame('<a href="../assets/edit/account-owner.php?oid=5">Own</a>', $cells[8]);
        // Custom fields: text escaped, a check box as Yes or No, a number, an empty date, a time stamp, and a URL
        $this->assertSame('&lt;script&gt;', $cells[9]);
        $this->assertSame('Yes', $cells[10]);
        $this->assertSame('42', $cells[11]);
        $this->assertSame('', $cells[12]);
        $this->assertSame('2026-02-03 04:05:06', $cells[13]);
        $this->assertStringContainsString('https://example.com/', $cells[14]);
    }

    // A certificate with no domain has an empty Domain cell, with no link
    public function testACertificateWithNoDomainHasAnEmptyDomainCell()
    {
        $settings = array('s_display_ssl_domain' => '1');
        $row = $this->row();
        $row->domain_id = 0;
        $row->domain = null;
        $cells = (new SslListColumns($settings, ''))->cells($row, '2026-11-15');
        $this->assertSame('', $cells[1]);

        $cells = (new SslListColumns($settings, ''))->cells($this->row(), '2026-11-15');
        $this->assertSame('<a href="../domains/edit.php?did=3">example.com</a>', $cells[1]);
    }

    public function testAnExpiredCertificatesDateIsNotMarked()
    {
        $row = $this->row();
        $row->active = '0';
        $cells = (new SslListColumns(array('s_display_ssl_expiry_date' => '1'), ''))->cells($row, '2026-11-15');

        $this->assertStringContainsString('aria-label="Expired"', $cells[0]);
        $this->assertSame('<a href="edit.php?sslcid=7">2026-01-02</a>', $cells[1]);
    }

    private function settings()
    {
        $settings = array();
        foreach (SslListColumns::OPTIONAL as $optional) {
            $settings[$optional[0]] = '1';
        }
        $settings['s_csf_data'] = array(
            array('name' => 'Purchaser', 'field' => 'purchaser_name', 'type_id' => '2', 'value' => '1'),
            array('name' => 'Renewed', 'field' => 'renewed', 'type_id' => '1', 'value' => '1'),
            array('name' => 'Notes Area', 'field' => 'notes_area', 'type_id' => '3', 'value' => '1'),
            array('name' => 'Order Number', 'field' => 'order_number', 'type_id' => '2', 'value' => '1'),
            array('name' => 'Issued', 'field' => 'issued', 'type_id' => '4', 'value' => '1'),
            array('name' => 'Checked At', 'field' => 'checked_at', 'type_id' => '5', 'value' => '1'),
            array('name' => 'Portal', 'field' => 'portal', 'type_id' => '6', 'value' => '1'),
            array('name' => 'Hidden', 'field' => 'hidden_field', 'type_id' => '2', 'value' => '0'));
        return SslListColumns::sessionCopy($settings);
    }

    private function row()
    {
        return (object) array('id' => 7, 'name' => 'a &amp; b', 'active' => '3', 'expiry_date' => '2026-01-02',
            'domain_id' => 3, 'domain' => 'example.com', 'sslp_id' => 4, 'ssl_provider_name' => 'Prov', 'o_id' => 5,
            'owner_name' => 'Own', 'sslpa_id' => 6, 'username' => 'abcdefghijklmnopq', 'type_id' => 8, 'type' => 'Web',
            'ip_id' => 9, 'ip_name' => 'Main', 'ip' => '10.0.0.1', 'cat_id' => 10, 'cat_name' => 'Cat',
            'purchaser_name' => '<script>', 'renewed' => '1', 'notes_area' => 'x', 'order_number' => '42',
            'issued' => '1970-01-01', 'checked_at' => '2026-02-03 04:05:06', 'portal' => 'https://example.com/');
    }

    private function setDatabase($value)
    {
        (new \ReflectionProperty(Database::class, 'instance'))->setValue(null, $value);
    }

} //@formatter:on
