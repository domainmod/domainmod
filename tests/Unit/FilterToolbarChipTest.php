<?php
/**
 * /tests/Unit/FilterToolbarChipTest.php
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

/*
 * FilterToolbar's chips and kept menu option in right-to-left languages: a chip's value is isolated so it keeps its
 * characters in order (".co.uk" doesn't become "co.uk."), and stays escaped.
 */
class FilterToolbarChipTest extends TestCase
{
    public function testAChipsValueIsIsolated()
    {
        $toolbar = new FilterToolbar('domain-filters', 'index.php', 'index.php?reset=1');
        $toolbar->addFilter('tld', 'co.uk', '', '.co.uk', 'TLD');

        $html = $toolbar->toolbar();

        $this->assertStringContainsString('<span class="domainmod-filter-chip-text">TLD: <bdi>.co.uk</bdi></span>', $html);
        // the label for screen readers stays plain text
        $this->assertStringContainsString('aria-label="Remove filter: TLD: .co.uk"', $html);
    }

    public function testAnIsolatedValueIsStillEscaped()
    {
        $toolbar = new FilterToolbar('domain-filters', 'index.php', 'index.php?reset=1');
        $toolbar->addFilter('rid', 5, '', 'Old <Registrar> & "Co"', 'Registrar <x>');

        $html = $toolbar->toolbar();

        $this->assertStringContainsString('<span class="domainmod-filter-chip-text">Registrar &lt;x&gt;: <bdi>Old &lt;Registrar&gt; &amp; &quot;Co&quot;</bdi></span>', $html);
        $this->assertStringNotContainsString('<Registrar>', $html);
    }

    public function testAChipWithoutANameIsntIsolated()
    {
        $toolbar = new FilterToolbar('domain-filters', 'index.php', 'index.php?reset=1');
        $toolbar->addFilter('daterange', '2026-01-01 - 2026-12-31', '', 'Expiring: 2026-01-01 - 2026-12-31');

        $html = $toolbar->toolbar();

        $this->assertStringContainsString('<span class="domainmod-filter-chip-text">Expiring: 2026-01-01 - 2026-12-31</span>', $html);
        $this->assertStringNotContainsString('<bdi>', $html);
    }

    public function testTheKeptOptionTakesItsDirectionFromItsText()
    {
        $toolbar = new FilterToolbar('domain-filters', 'index.php', 'index.php?reset=1');
        $toolbar->addFilter('tld', 'co.uk', '', '.co.uk', 'TLD');

        $this->assertSame('<option value="co.uk" dir="auto" selected>.co.uk</option>', $toolbar->keepOption('tld', array('com', 'net')));
        $this->assertSame('', $toolbar->keepOption('tld', array('co.uk')));
    }

} //@formatter:on
