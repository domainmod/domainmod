<?php
/**
 * /tests/Unit/FilterToolbarTest.php
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

use DomainMOD\FilterToolbar;
use PHPUnit\Framework\TestCase;

class FilterToolbarTest extends TestCase
{
    private $separator;

    // The expected URLs join their values with "&", PHP's default, which http_build_query() takes from this setting
    protected function setUp(): void
    {
        $this->separator = ini_get('arg_separator.output');
        ini_set('arg_separator.output', '&');
    }

    protected function tearDown(): void
    {
        ini_set('arg_separator.output', $this->separator);
    }

    private function toolbar()
    {
        return new FilterToolbar('domain-filters', 'index.php', 'index.php?reset=1');
    }

    public function testAddFilterIgnoresEmptyAndDefaultValues()
    {
        $toolbar = $this->toolbar();
        $toolbar->addFilter('rid', '', '', 'Registrar');
        $toolbar->addFilter('oid', null, '', 'Owner');
        $toolbar->addFilter('is_active', 'LIVE', 'LIVE', 'Live');
        $toolbar->addFilter('pcid', 0, '0', 'Category');

        $this->assertSame(0, $toolbar->activeCount());
        $this->assertSame('index.php', $toolbar->url());
    }

    public function testAddFilterComparesWithTheDefaultAsText()
    {
        // A status of 0 (Expired) isn't the default status (LIVE), so it's a filter
        $toolbar = $this->toolbar();
        $toolbar->addFilter('is_active', '0', 'LIVE', 'Expired', 'Status');

        $this->assertTrue($toolbar->isActive('is_active'));
        $this->assertSame(1, $toolbar->activeCount());
        $this->assertSame('index.php?is_active=0', $toolbar->url());
    }

    public function testUrlEncodesValuesDropsKeysAndAddsExtras()
    {
        $toolbar = $this->toolbar();
        $toolbar->addFilter('rid', 5, '', 'Registrar A', 'Registrar');
        $toolbar->addFilter('search_for', 'a b&c=d', '', 'a b&c=d');
        $toolbar->addFilter('tld', 'рф', '', 'рф', 'TLD');

        $this->assertSame('index.php?rid=5&search_for=a+b%26c%3Dd&tld=%D1%80%D1%84', $toolbar->url());
        $this->assertSame('index.php?rid=5&tld=%D1%80%D1%84', $toolbar->url(array('search_for')));
        $this->assertSame('index.php?rid=7&search_for=a+b%26c%3Dd&tld=%D1%80%D1%84&expand=1',
            $toolbar->url(array(), array('rid' => 7, 'expand' => 1)));
        $this->assertFalse($toolbar->isActive('oid'));
    }

    public function testStateUrlAlwaysCarriesTheKeywordAndRange()
    {
        $toolbar = $this->toolbar();
        $this->assertSame('index.php?search_for=&daterange=', $toolbar->stateUrl());

        $toolbar->addFilter('rid', 5, '', 'A', 'Registrar');
        $toolbar->addFilter('daterange', '2026-01-01 - 2026-12-31', '', 'Expiring: 2026-01-01 - 2026-12-31');
        $this->assertSame('index.php?rid=5&daterange=2026-01-01+-+2026-12-31&search_for=', $toolbar->stateUrl());
        $this->assertSame('index.php?rid=5&daterange=2026-01-01+-+2026-12-31&search_for=&export_data=1',
            $toolbar->exportUrl());
        $this->assertSame('index.php?rid=5&daterange=2026-01-01+-+2026-12-31&search_for=&start=0',
            $toolbar->stateUrl('search_for', 'daterange', array('start' => 0)));
    }

    public function testKeepOptionAddsAnOptionForAValueTheMenuDoesNotShow()
    {
        $toolbar = $this->toolbar();
        $toolbar->addFilter('rid', 9, '', 'Old <Registrar> & "Co"', 'Registrar');

        $this->assertSame('<option value="9" dir="auto" selected>Old &lt;Registrar&gt; &amp; &quot;Co&quot;</option>',
            $toolbar->keepOption('rid', array(1, 2, 3)));
    }

    public function testKeepOptionAddsNothingWhenTheValueIsShownOrTheFilterIsOff()
    {
        $toolbar = $this->toolbar();
        $toolbar->addFilter('rid', 2, '', 'Registrar B', 'Registrar');

        // compared as text, so an id from the database (a number or a string) matches
        $this->assertSame('', $toolbar->keepOption('rid', array(1, 2, 3)));
        $this->assertSame('', $toolbar->keepOption('rid', array('1', '2')));
        $this->assertSame('', $toolbar->keepOption('oid', array(1)));
    }

    public function testKeepOptionEscapesTheValue()
    {
        $toolbar = $this->toolbar();
        $toolbar->addFilter('tld', '"><b>', '', 'x', 'TLD');

        $this->assertSame('<option value="&quot;&gt;&lt;b&gt;" dir="auto" selected>x</option>', $toolbar->keepOption('tld', array('com')));
    }

    public function testDecode()
    {
        $this->assertSame('Tom\'s <Co> & "Sons"', FilterToolbar::decode('Tom&#039;s &lt;Co&gt; &amp; &quot;Sons&quot;'));
        $this->assertSame('', FilterToolbar::decode(null));
        $this->assertSame('(not found)', FilterToolbar::notFound());
    }

    public function testToolbarShowsEachFilterAsAnEscapedChip()
    {
        $toolbar = $this->toolbar();
        $toolbar->addFilter('rid', 5, '', '<script>x</script>', 'Registrar');
        $toolbar->addFilter('tld', 'de', '', 'de', 'TLD');

        $html = $toolbar->toolbar();

        $this->assertStringContainsString('<span class="domainmod-filter-chip-text">Registrar: <bdi>&lt;script&gt;x&lt;/script&gt;</bdi></span>', $html);
        $this->assertStringNotContainsString('<script>', $html);
        // removing a chip keeps the other filters
        $this->assertStringContainsString('href="index.php?tld=de" aria-label="Remove filter: Registrar: &lt;script&gt;', $html);
        $this->assertStringContainsString('href="index.php?rid=5" aria-label="Remove filter: TLD: de"', $html);
        $this->assertStringContainsString('<span aria-hidden="true">2</span><span class="visually-hidden">(2 active)</span>', $html);
        $this->assertStringContainsString('href="index.php?rid=5&amp;tld=de&amp;expand=1#domain-filters"', $html);
        $this->assertStringContainsString('<a class="domainmod-filter-clear" href="index.php?reset=1">Clear filters</a>', $html);
    }

    public function testToolbarWithoutFiltersHasNoCountOrClearLink()
    {
        $html = $this->toolbar()->toolbar();

        $this->assertStringNotContainsString('badge', $html);
        $this->assertStringNotContainsString('Clear filters', $html);
        $this->assertStringNotContainsString('domainmod-filter-chip', $html);
    }

    public function testTheExpandedToolbarLinksBackWithoutExpand()
    {
        $toolbar = $this->toolbar();
        $toolbar->addFilter('rid', 5, '', 'A', 'Registrar');

        $html = $toolbar->toolbar(true);

        $this->assertStringContainsString('<a class="btn btn-outline-secondary" href="index.php?rid=5"><i class="bi bi-funnel" aria-hidden="true"></i> Hide Filters</a>', $html);
        $this->assertStringNotContainsString('expand=1', $html);
    }

} //@formatter:on
