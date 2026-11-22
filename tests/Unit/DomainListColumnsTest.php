<?php
/**
 * /tests/Unit/DomainListColumnsTest.php
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

use DomainMOD\DomainListColumns;
use DomainMOD\Tests\Support\FieldRows;
use DomainMOD\Tests\Support\UnconnectedDatabase;
use PHPUnit\Framework\TestCase;

/*
 * The Domains list's columns, search expressions, and cells. The constructor creates a Currency, which only fetches
 * the shared database object, so an unconnected one stands in. useNumericSort() runs a query, so it isn't tested
 * here.
 */
class DomainListColumnsTest extends TestCase
{
    const FIELDS_SQL = ', dfd.renew_date, dfd.checked_at, dfd.note, dfd.is_flagged, dfd.site, dfd.long_note';

    protected function setUp(): void
    {
        UnconnectedDatabase::install();
    }

    protected function tearDown(): void
    {
        UnconnectedDatabase::remove();
    }

    private static function field($field, $name, $type_id, $value = '1')
    {
        return array('field' => $field, 'name' => $name, 'type_id' => $type_id, 'value' => $value);
    }

    // A full session goes through sessionCopy(), as the Domains page's does, so a setting the copy dropped (such as the
    // currency symbol) would be missing here too, and the tests that use it would fail
    private static function settings(array $overrides = array())
    {
        $session = array(
            's_default_currency' => 'USD',
            's_default_currency_symbol' => '$',
            's_default_currency_symbol_order' => '0',
            's_default_currency_symbol_space' => '0',
            's_user_id' => 3,
        );
        $session['s_cdf_data'] = array(
            self::field('renew_date', 'Renew Date', '4'),
            self::field('checked_at', 'Checked At', '5'),
            self::field('note', 'Note', '2'),
            self::field('is_flagged', 'Flagged', '1'),
            self::field('site', 'Site', '6'),
            self::field('long_note', 'Long Note', '3'),
            self::field('hidden', 'Hidden', '2', '0'),
            self::field('deleted_field', 'Deleted', '2'));
        return DomainListColumns::sessionCopy(array_merge($session, $overrides));
    }

    private static function keys(DomainListColumns $columns)
    {
        return array_column($columns->columns(), 'key');
    }

    public function testSessionCopyKeepsOnlyTheColumnsSettings()
    {
        $copy = DomainListColumns::sessionCopy(array('s_display_domain_tld' => '1', 's_user_id' => 3));

        // Written out, not taken from SESSION_KEYS, so dropping a setting the list needs (such as s_read_only, which
        // hides the fee edit links) or adding one it doesn't fails here. Compared sorted, since only the names matter.
        $keys = array_keys($copy);
        sort($keys);
        $expected = array('s_display_domain_expiry_date', 's_display_domain_fee', 's_display_domain_tld',
            's_display_domain_registrar', 's_display_domain_account', 's_display_domain_dns', 's_display_domain_ip',
            's_display_domain_host', 's_display_domain_category', 's_display_domain_owner', 's_display_domain_lock',
            's_display_domain_live_dns', 's_cdf_data', 's_default_currency', 's_default_currency_symbol', 's_default_currency_symbol_order',
            's_default_currency_symbol_space', 's_read_only');
        sort($expected);
        $this->assertSame($expected, $keys);
        $this->assertSame('1', $copy['s_display_domain_tld']);
        $this->assertSame('', $copy['s_display_domain_fee']);
        // and a setting the list doesn't use stays out, whatever SESSION_KEYS holds
        $this->assertArrayNotHasKey('s_user_id', $copy);
    }

    public function testColumnsFollowTheDisplaySettingsAndTheCurrentFields()
    {
        $columns = new DomainListColumns(self::settings(array('s_display_domain_expiry_date' => '1',
            's_display_domain_tld' => '1', 's_display_domain_owner' => '1')), FieldRows::of(self::FIELDS_SQL, self::settings(array('s_display_domain_expiry_date' => '1',
            's_display_domain_tld' => '1', 's_display_domain_owner' => '1'))));

        // Text Areas, hidden fields, and fields that no longer exist get no column
        // No control column: the Domains list keeps every column and scrolls sideways instead of using Responsive
        $this->assertSame(array('domain', 'expiry', 'tld', 'owner', 'cf_renew_date', 'cf_checked_at', 'cf_note',
            'cf_is_flagged', 'cf_site'), self::keys($columns));
        $this->assertSame(array('Domain', 'Expiry', 'TLD', 'Owner', 'Renew Date', 'Checked At', 'Note', 'Flagged', 'Site'),
            array_column($columns->columns(), 'title'));
    }

    public function testEveryOptionalColumnInItsOrder()
    {
        $all = array();
        foreach (DomainListColumns::OPTIONAL as $optional) {
            $all[$optional[0]] = '1';
        }
        $columns = new DomainListColumns(self::settings($all + array('s_cdf_data' => array())), array(), true);

        $this->assertSame(array('domain', 'expiry', 'fee', 'tld', 'registrar', 'account', 'dns', 'ip', 'service',
            'category', 'owner'), self::keys($columns));
        $this->assertSame('Fee (USD)', $columns->columns()[2]['title']);
    }

    /*
     * The Hosting Service column (still switched by the shipped s_display_domain_host setting) comes with the 4.27.0
     * database update, whatever the session says: before it, the list has no join to read it from. Without an answer
     * given, it asks Hosting::isAvailable(), which an unconnected database answers no.
     */
    public function testTheHostingServiceColumnComesWithTheUpdate()
    {
        $settings = self::settings(array('s_display_domain_host' => '1', 's_cdf_data' => array()));

        $after = new DomainListColumns($settings, array(), true);
        $this->assertSame(array('domain', 'service'), self::keys($after));
        $this->assertSame('Hosting Service', $after->columns()[1]['title']);
        $this->assertSame(array(0 => array('d.domain'), 1 => array('hs.name')), $after->sortMap());

        $this->assertSame(array('domain'), self::keys(new DomainListColumns($settings, array(), false)));
        $this->assertSame(array('domain'), self::keys(new DomainListColumns($settings, array())));
    }

    // A domain with no service has a blank cell, so an excluded word (NOT LIKE) mustn't turn the condition NULL and
    // hide it
    public function testTheHostingServiceSearchKeepsDomainsWithNoService()
    {
        $columns = new DomainListColumns(self::settings(array('s_display_domain_host' => '1', 's_cdf_data' => array())), array(), true);

        $this->assertSame(array("COALESCE(hs.name, '')"), $columns->searchExpressions()['encoded']);
    }

    public function testTheHostingServiceCell()
    {
        $columns = new DomainListColumns(self::settings(array('s_display_domain_host' => '1', 's_cdf_data' => array())), array(), true);

        $row = self::assetRow('user');
        $this->assertSame('<a href="../hosting/edit.php?hsid=17">Shared &amp; Co</a>', $columns->cells($row, '2026-11-15')[1]);

        // Blank for a domain with no service, or one whose service no longer exists (the LEFT JOIN's NULLs)
        $row->service_id = null;
        $row->service_name = null;
        $this->assertSame('', $columns->cells($row, '2026-11-15')[1]);
    }

    public function testSortMapAndDefaultOrder()
    {
        $with_expiry = new DomainListColumns(self::settings(array('s_display_domain_expiry_date' => '1',
            's_display_domain_account' => '1', 's_cdf_data' => array(self::field('note', 'Note', '2')))), FieldRows::of(self::FIELDS_SQL, self::settings(array('s_display_domain_expiry_date' => '1',
            's_display_domain_account' => '1', 's_cdf_data' => array(self::field('note', 'Note', '2'))))));

        $this->assertSame(array(0 => array('d.domain'), 1 => array('d.expiry_date'), 2 => array('r.name', 'o.name', 'ra.username'),
            3 => array('dfd.note')), $with_expiry->sortMap());
        $this->assertSame(array(array(1, 'asc')), $with_expiry->defaultOrder());

        $without_expiry = new DomainListColumns(self::settings(array('s_cdf_data' => array())), array());
        // When Expiry is hidden, the list sorts by Domain, the first column
        $this->assertSame(array(array(0, 'asc')), $without_expiry->defaultOrder());
    }

    public function testLayoutKeyChangesWithTheColumns()
    {
        $one = new DomainListColumns(self::settings(array('s_display_domain_tld' => '1')), FieldRows::of(self::FIELDS_SQL, self::settings(array('s_display_domain_tld' => '1'))));
        $same = new DomainListColumns(self::settings(array('s_display_domain_tld' => '1')), FieldRows::of(self::FIELDS_SQL, self::settings(array('s_display_domain_tld' => '1'))));
        $other = new DomainListColumns(self::settings(array('s_display_domain_dns' => '1')), FieldRows::of(self::FIELDS_SQL, self::settings(array('s_display_domain_dns' => '1'))));

        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $one->layoutKey());
        $this->assertSame($one->layoutKey(), $same->layoutKey());
        $this->assertNotSame($one->layoutKey(), $other->layoutKey());
    }

    public function testSearchExpressionsFollowTheVisibleColumns()
    {
        $columns = new DomainListColumns(self::settings(array('s_display_domain_expiry_date' => '1',
            's_display_domain_tld' => '1', 's_display_domain_ip' => '1', 's_display_domain_owner' => '1')), FieldRows::of(self::FIELDS_SQL, self::settings(array('s_display_domain_expiry_date' => '1',
            's_display_domain_tld' => '1', 's_display_domain_ip' => '1', 's_display_domain_owner' => '1'))));

        $expressions = $columns->searchExpressions();

        $this->assertSame(array('d.domain', 'd.tld'), $expressions['lower']);
        $this->assertSame(array("CONCAT(ip.name, ' (', ip.ip, ')')", 'o.name'), $expressions['encoded']);
        // the expiry date, then the Date, Time Stamp, and Text fields (Check Box and URL fields aren't searched)
        $this->assertSame(array(
            'CAST(d.expiry_date AS CHAR)',
            "CASE WHEN dfd.renew_date IN ('1970-01-01', '0000-00-00') THEN '' ELSE CAST(dfd.renew_date AS CHAR) END",
            "CASE WHEN dfd.checked_at IN ('1970-01-01 00:00:00', '0000-00-00 00:00:00') THEN '' ELSE CAST(dfd.checked_at AS CHAR) END",
            'dfd.note'), $expressions['typed']);
    }

    public function testSearchExpressionsWithOnlyTheDomain()
    {
        $columns = new DomainListColumns(self::settings(array('s_cdf_data' => array())), array());

        $this->assertSame(array('lower' => array('d.domain'), 'typed' => array(), 'encoded' => array()),
            $columns->searchExpressions());
    }

    private function customCells($row_values)
    {
        $columns = new DomainListColumns(self::settings(), FieldRows::of(self::FIELDS_SQL, self::settings()));
        // the custom fields under their aliases, as the list's query names them
        $aliased = array();
        foreach ($row_values as $name => $value) $aliased[\DomainMOD\CustomField::listAlias($name)] = $value;
        $row = (object) array_merge(array('id' => 7, 'domain' => 'example.com', 'active' => '1', 'privacy' => '0'),
            $aliased);
        // the domain cell comes first
        return array_slice($columns->cells($row, '2026-11-15'), 1);
    }

    public function testOtherCustomFieldCells()
    {
        $cells = $this->customCells(array('note' => '<b>bold</b>', 'is_flagged' => '1', 'site' => 'javascript:alert(1)'));

        $this->assertSame('&lt;b&gt;bold&lt;/b&gt;', $cells[2]);
        $this->assertSame('Yes', $cells[3]);
        $this->assertSame('javascript:alert(1)', $cells[4]);

        $cells = $this->customCells(array('note' => '', 'is_flagged' => '0', 'site' => 'https://example.com/'));
        $this->assertSame('', $cells[2]);
        $this->assertSame('No', $cells[3]);
        $this->assertSame("[<a target='_blank' rel='noopener noreferrer' href='https://example.com/'>open</a>]", $cells[4]);

        // a field missing from the row reads as empty
        $this->assertSame(array('', '', '', 'No', ''), $this->customCells(array()));
    }

    public function testDomainExpiryAndFeeCells()
    {
        $columns = new DomainListColumns(self::settings(array('s_display_domain_expiry_date' => '1',
            's_display_domain_fee' => '1', 's_cdf_data' => array())), array());
        $row = (object) array('id' => 7, 'domain' => 'example.com', 'active' => '1', 'privacy' => '0',
            'expiry_date' => '2027-01-01', 'total_cost' => '10.5', 'conversion' => '2', 'r_id' => 3, 'f_id' => 4);

        $this->assertSame(array('<a href="edit.php?did=7">example.com</a>', '<a href="edit.php?did=7">2027-01-01</a>',
            '<a href="../assets/edit/registrar-fee.php?rid=3&fee_id=4">$21.00</a>'), $columns->cells($row, '2026-11-15'));

        // A symbol after the amount, with a space, so losing either preference in the session copy fails
        $after = new DomainListColumns(self::settings(array('s_display_domain_fee' => '1', 's_cdf_data' => array(),
            's_default_currency_symbol_order' => '1', 's_default_currency_symbol_space' => '1')), array());
        $this->assertSame('<a href="../assets/edit/registrar-fee.php?rid=3&fee_id=4">21.00 $</a>',
            $after->cells($row, '2026-11-15')[1]);

        // A read-only user's session, through the same copy, gets the fee without the edit link
        $read_only = new DomainListColumns(self::settings(array('s_display_domain_fee' => '1', 's_cdf_data' => array(),
            's_read_only' => '1')), array());
        $this->assertSame('$21.00', $read_only->cells($row, '2026-11-15')[1]);
    }

    public function testAnExpiredLiveDomainIsMarked()
    {
        $columns = new DomainListColumns(self::settings(array('s_display_domain_expiry_date' => '1', 's_cdf_data' => array())), array());
        $row = (object) array('id' => 7, 'domain' => 'example.com', 'active' => '1', 'privacy' => '0',
            'expiry_date' => '2026-11-14');

        $this->assertSame('<a href="edit.php?did=7" class="domainmod-css-expired" data-bs-toggle="tooltip" '
            . 'data-bs-title="Expired" aria-label="2026-11-14, Expired">2026-11-14</a>', $columns->cells($row, '2026-11-15')[1]);

        // an inactive or sold domain isn't marked
        foreach (array('0', '10') as $status) {
            $row->active = $status;
            $this->assertSame('<a href="edit.php?did=7">2026-11-14</a>', $columns->cells($row, '2026-11-15')[1], $status);
        }

        // nor is one that expires today
        $row->active = '1';
        $row->expiry_date = '2026-11-15';
        $this->assertSame('<a href="edit.php?did=7">2026-11-15</a>', $columns->cells($row, '2026-11-15')[1]);
    }

    private static function assetRow($username)
    {
        return (object) array('id' => 7, 'domain' => 'example.com', 'active' => '1', 'privacy' => '0', 'tld' => 'com',
            'r_id' => 11, 'f_id' => 12, 'registrar_name' => 'Registrar', 'o_id' => 13, 'owner_name' => 'Owner',
            'ra_id' => 14, 'username' => $username, 'dnsid' => 15, 'dns_name' => 'DNS', 'ipid' => 16, 'ip_name' => 'Server',
            'ip' => '192.0.2.1', 'service_id' => 17, 'service_name' => 'Shared &amp; Co', 'pcid' => 18,
            'category_name' => 'Category');
    }

    public function testAssetCells()
    {
        $settings = array('s_cdf_data' => array());
        foreach (array('tld', 'registrar', 'account', 'dns', 'ip', 'service', 'category', 'owner') as $key) {
            $settings[DomainListColumns::OPTIONAL[$key][0]] = '1';
        }
        $columns = new DomainListColumns(self::settings($settings), array(), true);

        $this->assertSame(array(
            '<a href="../assets/edit/registrar-fee.php?rid=11&fee_id=12"><bdi dir="ltr">.com</bdi></a>',
            '<a href="../assets/edit/registrar.php?rid=11">Registrar</a>',
            '<a href="../assets/edit/registrar.php?rid=11">Registrar</a>, <a href="../assets/edit/account-owner.php?oid=13">Owner</a> '
                . '(<a href="../assets/edit/registrar-account.php?raid=14">fifteen-chars-x</a>)',
            '<a href="../assets/edit/dns.php?dnsid=15">DNS</a>',
            '<a href="../assets/edit/ip-address.php?ipid=16">Server (192.0.2.1)</a>',
            '<a href="../hosting/edit.php?hsid=17">Shared &amp; Co</a>',
            '<a href="../assets/edit/category.php?pcid=18">Category</a>',
            '<a href="../assets/edit/account-owner.php?oid=13">Owner</a>',
        ), array_slice($columns->cells(self::assetRow('fifteen-chars-x'), '2026-11-15'), 1));
    }

    public function testALongAccountUsernameIsShortened()
    {
        $columns = new DomainListColumns(self::settings(array('s_display_domain_account' => '1', 's_cdf_data' => array())), array());

        // 16 characters or more: the first 15, then an ellipsis
        $this->assertStringEndsWith('raid=14">sixteen-chars-x...</a>)',
            $columns->cells(self::assetRow('sixteen-chars-xyz'), '2026-11-15')[1]);
        $this->assertStringEndsWith('raid=14">sixteen-chars-x...</a>)',
            $columns->cells(self::assetRow('sixteen-chars-x!'), '2026-11-15')[1]);
    }

    // A custom field column's title and type come from the fields as they are now, and the session only says it's shown
    public function testCustomColumnsFollowTheFreshFieldRows()
    {
        $settings = self::settings(array('s_cdf_data' => array(self::field('note', 'Old Title', '2'),
            self::field('gone', 'Gone', '2'), self::field('was_text', 'Was Text', '2'))));
        $fields = array(array('id' => 1, 'name' => 'Note', 'field_name' => 'note', 'type_id' => 2),
            array('id' => 2, 'name' => 'Was Text', 'field_name' => 'was_text', 'type_id' => 3));
        $columns = new DomainListColumns($settings, $fields);

        // The deleted field and the one replaced by a Text Area have no column
        $this->assertSame(array('domain', 'cf_note'), self::keys($columns));
        $this->assertSame('Note', $columns->columns()[1]['title']);
    }

    // A shown field deleted and added again under the same name, as another type or not, changes the layout key
    public function testTheLayoutKeyFollowsTheShownFieldsIdsAndTypes()
    {
        $settings = self::settings(array('s_cdf_data' => array(self::field('note', 'Note', '2'))));
        $field = array('id' => 1, 'name' => 'Note', 'field_name' => 'note', 'type_id' => 2);
        $key = (new DomainListColumns($settings, array($field)))->layoutKey();

        $this->assertSame($key, (new DomainListColumns($settings, array(array('name' => 'Renamed') + $field)))->layoutKey());
        $this->assertNotSame($key, (new DomainListColumns($settings, array(array('id' => 2) + $field)))->layoutKey());
        $this->assertNotSame($key, (new DomainListColumns($settings, array(array('type_id' => 4) + $field)))->layoutKey());
    }

} //@formatter:on
