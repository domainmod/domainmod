<?php
/**
 * /tests/Unit/CustomFieldFilterTest.php
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

use DomainMOD\CustomField;
use DomainMOD\CustomFieldFilter;
use DomainMOD\DomainList;
use DomainMOD\FilterToolbar;
use DomainMOD\Form;
use DomainMOD\ListQuery;
use DomainMOD\SslList;
use DomainMOD\Tests\Support\RecordingPdo;
use PHPUnit\Framework\TestCase;

// The lists' custom field filters (CustomFieldFilter): the checked state, the SQL and its parameters, the keys that
// tell a request its fields have changed, the chips, and the panel's controls
class CustomFieldFilterTest extends TestCase
{
    // One field of each type, in name order, as CustomField::getFilterFields() returns them
    const FIELDS = array(
        array('id' => 4, 'name' => 'Checked At', 'field_name' => 'checked_at', 'type_id' => 5),
        array('id' => 6, 'name' => 'Long Note', 'field_name' => 'long_note', 'type_id' => 3),
        array('id' => 2, 'name' => 'Note', 'field_name' => 'note', 'type_id' => 2),
        array('id' => 3, 'name' => 'Renew Date', 'field_name' => 'renew_date', 'type_id' => 4),
        array('id' => 1, 'name' => 'Renew Manually', 'field_name' => 'renew_manually', 'type_id' => 1),
        array('id' => 5, 'name' => 'Site', 'field_name' => 'site', 'type_id' => 6));

    private function filter(array $fields = self::FIELDS, $alias = 'dfd')
    {
        return new CustomFieldFilter($fields, $alias);
    }

    public function testGoodValuesOfEveryType()
    {
        $state = $this->filter()->normalize(array('cf_renew_manually' => '0', 'cf_note' => 'transfer',
            'cf_long_note' => 'a b', 'cf_site' => 'example', 'cf_renew_date' => '2026-01-01 - 2026-06-30',
            'cf_checked_at' => '2026-02-01 - 2026-02-01'));

        $this->assertSame(array('checked_at', 'long_note', 'note', 'renew_date', 'renew_manually', 'site'), array_keys($state));
        $this->assertSame(array('key' => 'cf_renew_manually', 'value' => '0'), $state['renew_manually']);
        $this->assertSame(array('key' => 'cf_renew_date', 'value' => '2026-01-01 - 2026-06-30'), $state['renew_date']);
    }

    public function testBadValuesAreDropped()
    {
        $long = str_repeat('x', CustomFieldFilter::MAX_TEXT_LENGTH + 1);
        $state = $this->filter()->normalize(array('cf_renew_manually' => '2', 'cf_note' => array('x'), 'cf_site' => $long,
            'cf_long_note' => '   ', 'cf_renew_date' => '2026-06-30 - 2026-01-01', 'cf_checked_at' => '2026-02-30 - 2026-03-01',
            'cfe_note' => 'blank', 'cfe_renew_manually' => 'empty', 'cf_unknown' => '1', 'cf_' => '1'));

        $this->assertSame(array(), $this->filter()->normalize(array('cf_note' => "first\nsecond", 'cf_site' => "a\rb",
            'cf_long_note' => "\xFF", 'cf_renew_date' => '', 'cf_checked_at' => '')));
        $this->assertSame(array(), $this->filter()->normalize(array('cf_note' => "a\x00b", 'cf_site' => "a\tb")));

        $this->assertSame(array(), $state);
        $this->assertSame(array(), $this->filter()->normalize(array('cf_renew_date' => 'x2026-01-01 - 2026-06-30')));
        $this->assertSame(array(), $this->filter()->normalize(array('cf_renew_date' => '2026-01-01 to 2026-06-30')));
        // Exactly the limit is kept
        $this->assertCount(1, $this->filter()->normalize(array('cf_note' => str_repeat('é', CustomFieldFilter::MAX_TEXT_LENGTH))));
    }

    public function testAValueWinsOverEmptyAndDropsFilledIn()
    {
        $state = $this->filter()->normalize(array('cf_note' => 'x', 'cfe_note' => 'empty', 'cf_site' => 'y',
            'cfe_site' => 'filled', 'cfe_renew_date' => 'empty', 'cfe_checked_at' => 'filled'));

        $this->assertSame(array('key' => 'cf_note', 'value' => 'x'), $state['note']);
        $this->assertSame(array('key' => 'cf_site', 'value' => 'y'), $state['site']);
        $this->assertSame(array('key' => 'cfe_renew_date', 'value' => 'empty'), $state['renew_date']);
        $this->assertSame(array('key' => 'cfe_checked_at', 'value' => 'filled'), $state['checked_at']);

        // A blank value, or one the list drops (a half-typed range), is no value, so the field's Filled In menu still counts
        $this->assertSame(array('note' => array('key' => 'cfe_note', 'value' => 'empty')),
            $this->filter()->normalize(array('cf_note' => '  ', 'cfe_note' => 'empty')));
        $this->assertSame(array('renew_date' => array('key' => 'cfe_renew_date', 'value' => 'empty')),
            $this->filter()->normalize(array('cf_renew_date' => '2026-11-', 'cfe_renew_date' => 'empty')));
    }

    // Text fields named a, b, c, ..., each filtered with $length characters
    private function capped($count, $length)
    {
        $fields = array();
        $request = array();
        for ($n = 0; $n < $count; $n++) {
            $name = str_repeat(chr(97 + $n % 26), 1 + intdiv($n, 26));
            $fields[] = array('id' => $n + 1, 'name' => $name, 'field_name' => $name, 'type_id' => 2);
            $request['cf_' . $name] = str_repeat('z', $length);
        }
        return array($fields, $request);
    }

    public function testTheFiltersAreCappedInFieldOrder()
    {
        // Twenty filters of 98 bytes ("cf_a=" and 93 letters), with "&" between them, come to 1,979 bytes
        list($fields, $request) = $this->capped(20, 93);
        $filter = new CustomFieldFilter($fields, 'dfd');

        $state = $filter->normalize($request, $dropped);
        $encoded = strlen(http_build_query(CustomFieldFilter::requestKeys($state)));
        $this->assertSame(1979, $encoded);
        $this->assertFalse($dropped);
        $this->assertCount(20, $state);

        // Exactly at the cap (one more letter in each, and two in the first), the set is kept whole
        $request = array_map(function ($value) { return $value . 'z'; }, $request);
        $request['cf_a'] .= 'z';
        $state = $filter->normalize($request, $dropped);
        $this->assertSame(CustomFieldFilter::MAX_ENCODED_BYTES, strlen(http_build_query(CustomFieldFilter::requestKeys($state))));
        $this->assertFalse($dropped);

        // One byte over drops the last filter in field order, and only that one
        $request['cf_b'] .= 'z';
        $state = $filter->normalize($request, $dropped);
        $this->assertTrue($dropped);
        $this->assertCount(19, $state);
        $this->assertArrayNotHasKey('t', $state);

        // Many short filters pass it
        list($fields, $request) = $this->capped(60, 3);
        $this->assertCount(60, (new CustomFieldFilter($fields, 'dfd'))->normalize($request, $dropped));
        $this->assertFalse($dropped);
    }

    public function testConditionsForEveryType()
    {
        $filter = $this->filter();
        $state = $filter->normalize(array('cf_renew_manually' => '1', 'cf_note' => '50%_off\\', 'cfe_long_note' => 'empty',
            'cfe_site' => 'filled', 'cf_renew_date' => '2026-01-01 - 2026-06-30', 'cf_checked_at' => '2026-02-01 - 2026-02-03'));
        $conditions = $filter->conditions($state);

        $this->assertSame(" AND dfd.`checked_at` >= :cff0 AND dfd.`checked_at` <= :cff1 AND dfd.`checked_at` NOT IN"
            . " ('1970-01-01 00:00:00', '0000-00-00 00:00:00')"
            . " AND dfd.`long_note` = ''"
            . " AND dfd.`note` LIKE :cff2 ESCAPE '\\\\'"
            . " AND dfd.`renew_date` >= :cff3 AND dfd.`renew_date` <= :cff4 AND dfd.`renew_date` NOT IN ('1970-01-01', '0000-00-00')"
            . " AND dfd.`renew_manually` = :cff5"
            . " AND dfd.`site` != ''", $conditions['sql']);
        $this->assertSame(array('cff0' => '2026-02-01 00:00:00', 'cff1' => '2026-02-03 23:59:59', 'cff2' => '%50\\%\\_off\\\\%',
            'cff3' => '2026-01-01', 'cff4' => '2026-06-30', 'cff5' => 1), $conditions['params']);
    }

    public function testEmptyDatesAreThePlaceholders()
    {
        $filter = $this->filter();
        $conditions = $filter->conditions($filter->normalize(array('cfe_renew_date' => 'empty', 'cfe_checked_at' => 'filled')));

        $this->assertSame(" AND dfd.`checked_at` NOT IN ('1970-01-01 00:00:00', '0000-00-00 00:00:00')"
            . " AND dfd.`renew_date` IN ('1970-01-01', '0000-00-00')", $conditions['sql']);
        $this->assertSame(array(), $conditions['params']);
    }

    public function testNoIdentifierComesFromTheRequest()
    {
        $filter = $this->filter();
        $conditions = $filter->conditions($filter->normalize(array('cf_d.id = 1 OR 1' => '1', 'cf_renew_manually`' => '1',
            'cf_password' => 'x')));
        $this->assertSame(array('sql' => '', 'params' => array()), $conditions);

        // A field whose database name isn't letters and underscores, or whose type is unknown, is left out
        $odd = new CustomFieldFilter(array(array('id' => 1, 'name' => 'Bad', 'field_name' => 'bad`name', 'type_id' => 2),
            array('id' => 2, 'name' => 'Odd', 'field_name' => 'odd', 'type_id' => 9)), 'dfd');
        $this->assertFalse($odd->hasFields());
        $this->assertSame(array(), $odd->normalize(array('cf_bad`name' => 'x', 'cf_odd' => 'x')));
    }

    public function testTheSubqueryHasItsOwnNamesAndTheGivenColumns()
    {
        $filter = $this->filter(self::FIELDS, 'sslfd');
        $state = $filter->normalize(array('cf_renew_manually' => '0', 'cf_note' => 'x'));
        $subquery = $filter->subquery($state, SslList::FIELD_DATA_TABLE, SslList::FIELD_DATA_ID, 'sslc.id');

        $this->assertSame(" AND sslc.id IN (SELECT cfd.ssl_id FROM ssl_cert_field_data AS cfd WHERE 1 = 1"
            . " AND cfd.`note` LIKE :cfs0 ESCAPE '\\\\' AND cfd.`renew_manually` = :cfs1)", $subquery['sql']);
        $this->assertSame(array('cfs0' => '%x%', 'cfs1' => 0), $subquery['params']);
        $this->assertSame(array('sql' => '', 'params' => array()),
            $filter->subquery(array(), DomainList::FIELD_DATA_TABLE, DomainList::FIELD_DATA_ID, 'd.id'));
    }

    public function testTheFieldsKeyFollowsTheFieldSetNotItsOrderOrNames()
    {
        $key = $this->filter()->fieldsKey();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{12}$/', $key);

        $renamed = self::FIELDS;
        $renamed[2]['name'] = 'Notes';
        $this->assertSame($key, $this->filter(array_reverse($renamed))->fieldsKey());

        $added = array_merge(self::FIELDS, array(array('id' => 7, 'name' => 'X', 'field_name' => 'x', 'type_id' => 2)));
        $this->assertNotSame($key, $this->filter($added)->fieldsKey());
        $this->assertNotSame($key, $this->filter(array_slice(self::FIELDS, 1))->fieldsKey());

        // Deleted and added again under the same name gives a new id
        $replaced = self::FIELDS;
        $replaced[4]['id'] = 9;
        $this->assertNotSame($key, $this->filter($replaced)->fieldsKey());
    }

    public function testTheFilterKeyFollowsTheFilteredFieldsIdsAndTypes()
    {
        $filter = $this->filter();
        $key = $filter->filterKey(array('cf_renew_manually', 'search_for', 'cff'));
        $this->assertSame($key, $filter->filterKey(array('cf_renew_manually')));
        $this->assertNotSame($key, $filter->filterKey(array('cfe_note')));
        $this->assertNotSame($filter->filterKey(array()), $key);

        // The check box replaced by a Text field of the same name: a "No" filter (0) must not become "contains 0"
        $replaced = self::FIELDS;
        $replaced[4] = array('id' => 9, 'name' => 'Renew Manually', 'field_name' => 'renew_manually', 'type_id' => 2);
        $this->assertNotSame($key, $this->filter($replaced)->filterKey(array('cf_renew_manually')));
        $replaced[4]['id'] = 1;
        $this->assertNotSame($key, $this->filter($replaced)->filterKey(array('cf_renew_manually')));

        // A deleted field counts as missing
        $this->assertNotSame($key, $this->filter(array_slice(self::FIELDS, 0, 4))->filterKey(array('cf_renew_manually')));
        // An unrelated field doesn't change it
        $this->assertSame($key, $this->filter(array_slice(self::FIELDS, 4))->filterKey(array('cf_renew_manually')));
    }

    public function testIncomingKeysIncludeADeletedFieldsKeys()
    {
        $this->assertSame(array('cf_gone', 'cfe_old', 'cff', 'cfk'), CustomFieldFilter::incomingKeys(array('cf_gone' => '1',
            'pcid' => '2', 'cfe_old' => 'x', 'cff' => 'a', 'cfk' => 'b', 'cfx' => '1')));
    }

    public function testAPageRequestIsCheckedByCfkThenCffThenTakenAsItIs()
    {
        $filter = $this->filter();
        $request = array('cf_renew_manually' => '1');
        $cfk = $filter->filterKey(array_keys($request));

        $this->assertTrue($filter->requestOk(array('pcid' => '1', 'cff' => 'stale', 'cfk' => 'stale')));
        $this->assertTrue($filter->requestOk($request));
        $this->assertTrue($filter->requestOk($request + array('cff' => $filter->fieldsKey())));
        $this->assertFalse($filter->requestOk($request + array('cff' => 'stale')));
        // cfk alone decides when it's there, whatever cff says
        $this->assertTrue($filter->requestOk($request + array('cff' => 'stale', 'cfk' => $cfk)));
        $this->assertFalse($filter->requestOk($request + array('cff' => $filter->fieldsKey(), 'cfk' => 'stale')));
        $this->assertFalse($filter->requestOk($request + array('cfk' => array($cfk))));
    }

    public function testTheEndpointsChecksInOrder()
    {
        $filter = $this->filter();
        $request = array('cf_renew_manually' => '0');
        $cfk = $filter->filterKey(array_keys($request));

        // Neither custom field keys nor cfk: on to the layout check
        $this->assertTrue($filter->endpointOk(array('draw' => '1', 'cff' => 'x')));
        // Custom field keys without cfk
        $this->assertFalse($filter->endpointOk($request));
        $this->assertTrue($filter->endpointOk($request + array('cfk' => $cfk)));
        // A deleted field's key, and the same field replaced with another type
        $this->assertFalse($this->filter(array_slice(self::FIELDS, 0, 4))->endpointOk($request + array('cfk' => $cfk)));
        $replaced = self::FIELDS;
        $replaced[4]['type_id'] = 2;
        $this->assertFalse($this->filter($replaced)->endpointOk($request + array('cfk' => $cfk)));
        // A cfk with no custom field keys is checked too
        $this->assertFalse($filter->endpointOk(array('cfk' => $cfk)));
    }

    // The endpoint's 409: the filter check comes first, so a field both filtered and shown gives the filter reply
    public function testTheEndpointsConflictIsTheFilterOneFirst()
    {
        $request = array('cf_renew_manually' => '1', 'layout' => 'abc');
        $request['cfk'] = $this->filter()->filterKey(array_keys($request));

        $this->assertNull(CustomFieldFilter::endpointConflict($request, self::FIELDS, 'dfd', 'abc'));
        $this->assertSame('layout', CustomFieldFilter::endpointConflict($request, self::FIELDS, 'dfd', 'def'));
        $this->assertSame('layout', CustomFieldFilter::endpointConflict(array('layout' => array('abc')), self::FIELDS, 'dfd', 'abc'));
        // Deleted, then replaced (the read after the queries gives the same answer from its own fields)
        $this->assertSame('filter', CustomFieldFilter::endpointConflict($request, array_slice(self::FIELDS, 0, 4), 'dfd', 'def'));
        $replaced = self::FIELDS;
        $replaced[4]['id'] = 10;
        $this->assertSame('filter', CustomFieldFilter::endpointConflict($request, $replaced, 'dfd', 'abc'));
        // An unrelated field added since: both checks still pass, so the rows are sent
        $added = array_merge(self::FIELDS, array(array('id' => 8, 'name' => 'New', 'field_name' => 'new', 'type_id' => 2)));
        $this->assertFalse($this->filter()->sameFields($added));
        $this->assertNull(CustomFieldFilter::endpointConflict($request, $added, 'dfd', 'abc'));
        // A draw with no custom field filters goes straight to the layout check
        $this->assertNull(CustomFieldFilter::endpointConflict(array('layout' => 'abc'), array(), 'dfd', 'abc'));
    }

    public function testChipsAndExportLabels()
    {
        $fields = array(array('id' => 1, 'name' => 'R&amp;D &lt;x&gt; &quot;y&quot;', 'field_name' => 'rd', 'type_id' => 2),
            array('id' => 2, 'name' => 'Renew', 'field_name' => 'renew', 'type_id' => 1),
            array('id' => 3, 'name' => 'Due', 'field_name' => 'due', 'type_id' => 4),
            array('id' => 4, 'name' => 'Site', 'field_name' => 'site', 'type_id' => 6));
        $filter = new CustomFieldFilter($fields, 'dfd');
        $state = $filter->normalize(array('cf_rd' => 'a & "b" <c>', 'cf_renew' => '0', 'cf_due' => '2026-01-01 - 2026-06-30',
            'cfe_site' => 'empty'));

        $this->assertSame(array(
            'cf_rd' => array('name' => 'R&D <x> "y"', 'label' => '"a & "b" <c>"'),
            'cf_renew' => array('name' => 'Renew', 'label' => 'No'),
            'cf_due' => array('name' => 'Due', 'label' => '2026-01-01 to 2026-06-30'),
            'cfe_site' => array('name' => 'Site', 'label' => 'Empty')), $filter->describe($state));

        $toolbar = new FilterToolbar('domain-filters', '/domains/index.php', '/domains/index.php');
        $filter->addChips($toolbar, $state);
        $this->assertSame(4, $toolbar->activeCount());
        $this->assertStringContainsString('cf_renew=0', $toolbar->url());
        $html = $toolbar->toolbar();
        $this->assertStringContainsString('R&amp;D &lt;x&gt; &quot;y&quot;', $html);
        $this->assertStringContainsString('&quot;a &amp; &quot;b&quot; &lt;c&gt;&quot;', $html);
        $this->assertStringNotContainsString('<c>', $html);
    }

    public function testThePanelSection()
    {
        $filter = $this->filter();
        $this->assertSame('', (new CustomFieldFilter(array(), 'dfd'))->panelHtml(new Form(), array()));

        $closed = $filter->panelHtml(new Form(), array());
        $this->assertStringContainsString('<details class="domainmod-filter-custom-fields mb-3">', $closed);
        $this->assertStringContainsString('<summary>Custom Fields</summary>', $closed);
        $this->assertStringContainsString('name="cff" value="' . $filter->fieldsKey() . '"', $closed);
        $this->assertSame(6, substr_count($closed, '<fieldset'));
        // A check box has no Filled In menu
        $this->assertSame(5, substr_count($closed, 'name="cfe_'));

        $state = $filter->normalize(array('cf_note' => 'x', 'cfe_site' => 'empty'));
        $open = $filter->panelHtml(new Form(), $state);
        $this->assertStringContainsString('<details class="domainmod-filter-custom-fields mb-3" open>', $open);
        $this->assertStringContainsString('<summary>Custom Fields (2 active)</summary>', $open);
        $this->assertMatchesRegularExpression('/<option value="empty"[^>]*selected>/', $open);
    }

    public function testTheDateKeysAreTheDateAndTimeStampFields()
    {
        $this->assertSame(array('cf_checked_at', 'cf_renew_date'), $this->filter()->dateKeys());
    }

    public function testTheFieldsAreReadOnceInNameOrderWithBadNamesLeftOut()
    {
        $pdo = new RecordingPdo(array('FROM domain_fields' => array(array(
            array('id' => '2', 'name' => 'Note', 'field_name' => 'note', 'type_id' => '2'),
            array('id' => '3', 'name' => 'Bad', 'field_name' => 'bad name', 'type_id' => '2')))));

        $this->assertSame(array(array('id' => 2, 'name' => 'Note', 'field_name' => 'note', 'type_id' => 2)),
            CustomField::readFilterFields($pdo, 'domain_fields'));
        $this->assertStringContainsString('ORDER BY `name` ASC', $pdo->log[0]['sql']);
        $this->assertSame(', dfd.note, dfd.renew', CustomField::fieldsSql(array(array('field_name' => 'note'),
            array('field_name' => 'renew')), 'dfd'));

        $this->expectException(\InvalidArgumentException::class);
        CustomField::readFilterFields($pdo, 'users');
    }

    public function testSameFieldsComparesTheFieldSets()
    {
        $filter = $this->filter();
        $this->assertTrue($filter->sameFields(array_reverse(self::FIELDS)));
        $replaced = self::FIELDS;
        $replaced[0]['id'] = 10;
        $this->assertFalse($filter->sameFields($replaced));
    }

    public function testFieldsChangedErrors()
    {
        $unknown = new \PDOException('Unknown column', 0);
        $unknown->errorInfo = array('42S22', 1054, "Unknown column 'dfd.x'");
        $definition = new \PDOException('Table definition has changed');
        $definition->errorInfo = array('HY000', 1412, 'Table definition has changed, please retry transaction');
        $other = new \PDOException('Lock wait');
        $other->errorInfo = array('HY000', 1205, 'Lock wait timeout');

        $this->assertTrue(CustomFieldFilter::fieldsChangedError($unknown));
        $this->assertTrue(CustomFieldFilter::fieldsChangedError($definition));
        $this->assertFalse(CustomFieldFilter::fieldsChangedError($other));
        $this->assertFalse(CustomFieldFilter::fieldsChangedError(new \RuntimeException('42S22')));
    }

    public function testLikeValueIsPublicForTheFilters()
    {
        $this->assertSame('%a\\%b\\_c\\\\%', ListQuery::likeValue('a%b_c\\'));
    }

} //@formatter:on
