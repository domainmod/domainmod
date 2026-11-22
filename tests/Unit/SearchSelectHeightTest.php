<?php
/**
 * /tests/Unit/SearchSelectHeightTest.php
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
 * A menu you can search (js/search-select.js) keeps one height whether or not it has focus, so the first click on the
 * button below it isn't lost. Tom Select puts its search box after the chosen option while the menu has focus, which
 * wrapped onto its own line under a long option. The search box lies over the chosen option instead (css/base.css),
 * and the option is hidden only while something is typed, which the script marks with a class.
 */
class SearchSelectHeightTest extends TestCase
{
    protected function source($path)
    {
        return file_get_contents(dirname(__DIR__, 2) . '/' . $path);
    }

    // The CSS rules, comments removed, as selector => declarations
    protected function rules()
    {
        $css = preg_replace('#/\*.*?\*/#s', '', $this->source('css/base.css'));
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);
        $rules = array();
        foreach ($matches as $match) {
            $rules[preg_replace('/\s+/', ' ', trim($match[1]))] = preg_replace('/\s+/', ' ', trim($match[2]));
        }
        return $rules;
    }

    public function testTheSearchBoxLiesOverTheChosenOption()
    {
        $rules = $this->rules();
        $selector = '.ts-wrapper.single.domainmod-search-select.has-items .ts-control > input';
        $this->assertArrayHasKey($selector, $rules);
        $this->assertStringContainsString('position: absolute;', $rules[$selector]);
        $this->assertStringContainsString('inset-inline-start: .75rem;', $rules[$selector]);
        $this->assertStringContainsString('inset-inline-end: var(--ts-pr-caret, 2.25rem);', $rules[$selector]);
        $this->assertSame('visibility: hidden;', $rules['.ts-wrapper.single.domainmod-search-select.domainmod-search-typing .ts-control > .item'] ?? '');
        // No other rule positions the search box, which could put it back in the flow
        foreach ($rules as $rule_selector => $declarations) {
            if (preg_match('/ts-control\s*>\s*input/', $rule_selector) && preg_match('/(^|;)\s*position\s*:\s*([a-z-]+)/', $declarations, $position)) {
                $this->assertSame('absolute', $position[2], $rule_selector);
            }
        }
    }

    public function testTheScriptMarksTypedText()
    {
        $js = $this->source('js/search-select.js');
        $this->assertStringContainsString("tom_select.wrapper.classList.toggle('domainmod-search-typing', tom_select.inputValue() !== '');", $js);
        $this->assertStringContainsString("tom_select.control_input.addEventListener('input', searchSelectTyping);", $js);
        $this->assertStringContainsString("['item_add', 'clear', 'blur', 'dropdown_close'].forEach(function (event) {\n        tom_select.on(event, searchSelectTyping);\n    });", $js);
        // Every searchable menu gets the class the rules name (Form::showDropdownTop() with $searchable)
        $this->assertStringContainsString("document.querySelectorAll('select.domainmod-search-select')", $js);
        $this->assertStringContainsString('domainmod-search-select', $this->source('classes/DomainMOD/Form.php'));
    }

} //@formatter:on
