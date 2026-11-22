<?php
/**
 * /tests/Unit/BoxedListTablesTest.php
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

use PHPUnit\Framework\TestCase;

/*
 * The lists with lines between their columns (Domains, SSL Certificates, Watch List, and Change History) are boxed in.
 * Each list's stylesheet gives its scroll box the same border all round as the column dividers, so a scrollbar at the
 * bottom sits inside the box. A separate rule removes the bottom border from the last row's cells, so the bottom isn't
 * drawn twice. The box's rule finds it by the table straight inside it. Each page puts its table straight inside the
 * box it draws for when JavaScript is off, and DataTables does the same with the box it makes for a list that keeps
 * every column.
 */
class BoxedListTablesTest extends TestCase
{
    // Each list's page folder, by the table's id (its stylesheet is css/datatables/<id>.css)
    const LISTS = array('domains-main' => 'domains', 'ssl-main' => 'ssl', 'watch-main' => 'watch',
        'history-main' => 'history');

    // The border between columns
    const LINE = '1px solid var(--bs-border-color-translucent)';

    // Every rule in a list's stylesheet, as its selector (with the spaces evened out) and declarations, with comments
    // removed
    protected function rules($id)
    {
        $css = file_get_contents(dirname(__DIR__, 2) . '/css/datatables/' . $id . '.css');
        $css = preg_replace('#/\*.*?\*/#s', '', $css);
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);
        $rules = array();
        foreach ($matches as $match) {
            $rules[] = array('selector' => preg_replace('/\s+/', ' ', trim($match[1])), 'body' => $match[2]);
        }
        return $rules;
    }

    // Every declaration the rules with this exact selector give, in order, as property and value
    protected function declarations(array $rules, $selector)
    {
        $declarations = array();
        foreach ($rules as $rule) {
            if ($rule['selector'] !== $selector) continue;
            foreach (explode(';', $rule['body']) as $declaration) {
                if (strpos($declaration, ':') === false) continue;
                list($property, $value) = array_map('trim', explode(':', $declaration, 2));
                $declarations[] = array(strtolower($property), $value);
            }
        }
        $this->assertNotEmpty($declarations, $selector);
        return $declarations;
    }

    // The value a property ends up with from these declarations (the last one wins), or null when none sets it
    protected function value(array $declarations, $property)
    {
        $value = null;
        foreach ($declarations as $declaration) {
            if ($declaration[0] === $property) $value = $declaration[1];
        }
        return $value;
    }

    public function testEachScrollBoxHasTheDividersBorderAllRound()
    {
        foreach (array_keys(self::LISTS) as $id) {
            $rules = $this->rules($id);
            $divider = $this->declarations($rules, '#' . $id . ' > :is(thead, tbody) > tr > :not(:last-child)');
            $this->assertSame(self::LINE, $this->value($divider, 'border-inline-end'), $id);
            // The box's only border declaration is the border all round, so nothing after it takes a side away
            $box = $this->declarations($rules, '.domainmod-list-scroll:has(> #' . $id . ')');
            $borders = array_values(array_filter($box, function ($declaration) {
                return strpos($declaration[0], 'border') === 0;
            }));
            $this->assertSame(array(array('border', self::LINE)), $borders, $id);
        }
    }

    // Each row's cells keep their bottom border, apart from the last row's, which would sit on the box's bottom edge
    public function testOnlyTheLastRowLosesItsBottomBorder()
    {
        foreach (array_keys(self::LISTS) as $id) {
            $rules = $this->rules($id);
            $row = $this->declarations($rules, '#' . $id . ' > tbody > tr > td');
            $this->assertSame(self::LINE, $this->value($row, 'border-block-end'), $id);
            // It's found through the box, as the box's border is, so both apply or neither does
            $last_row = $this->declarations($rules, '.domainmod-list-scroll:has(> #' . $id . ') > #' . $id
                . ' > tbody > tr:last-child > td');
            $this->assertSame('0', $this->value($last_row, 'border-block-end'), $id);
        }
    }

    // The table is straight inside the scroll box, with or without JavaScript, so the rule finds the box either way
    public function testEachTableIsStraightInsideItsScrollBox()
    {
        $root = dirname(__DIR__, 2);
        foreach (self::LISTS as $id => $folder) {
            $settings = file_get_contents($root . '/_includes/settings/' . $id . '.inc.php');
            $this->assertStringContainsString('$slug = "' . $id . '";', $settings, $id);
            $page = file_get_contents($root . '/' . $folder . '/index.php');
            // The box's opening tag is on one line, and its label is written by PHP, so the tag holds a closing PHP tag
            $box_then_table = '#<div class="domainmod-list-scroll"[^\n]*>\n\s*<table id="<\?php echo \$slug; \?>"#';
            $this->assertMatchesRegularExpression($box_then_table, $page, $id);
            // A list that keeps every column (no Responsive) is the kind DataTables puts in a box of its own
            $found = preg_match('/\$list_config = array\(.*?<table id=/s', $page, $config);
            $this->assertSame(1, $found, $id);
            $this->assertStringContainsString("'responsive' => false,", $config[0], $id);
        }
        $script = file_get_contents($root . '/js/server-list.js');
        $this->assertStringContainsString('var scrolls = config.responsive === false;', $script);
        $this->assertStringContainsString('(scrolls ? \'r<"domainmod-list-scroll"t>\' : \'rt\')', $script);
    }

} //@formatter:on
