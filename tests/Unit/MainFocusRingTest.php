<?php
/**
 * /tests/Unit/MainFocusRingTest.php
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

use DomainMOD\Layout;
use PHPUnit\Framework\TestCase;

/*
 * The main content area's focus ring, read from css/base.css. AdminLTE lets the area scroll, so Chromium makes it
 * focusable. After the "Skip to main content" link, or a click on the page's background once the keyboard had been
 * used, the browser drew a focus ring around the whole area. That ring is removed, and nothing else loses its outline.
 */
class MainFocusRingTest extends TestCase
{
    // Every rule in css/base.css (those inside @media too), as its selector and declarations, with comments removed
    protected function rules()
    {
        $css = file_get_contents(dirname(__DIR__, 2) . '/css/base.css');
        $css = preg_replace('#/\*.*?\*/#s', '', $css);
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);
        $rules = array();
        foreach ($matches as $match) {
            $rules[] = array('selector' => preg_replace('/\s+/', ' ', trim($match[1])), 'body' => $match[2]);
        }
        return $rules;
    }

    public function testOnlyTheMainContentAreaLosesItsOutline()
    {
        $removed = array();
        foreach ($this->rules() as $rule) {
            if (preg_match('/(^|;)\s*outline(-style|-width|-color)?\s*:\s*(none|hidden|transparent|0*\.?0+[a-z%]*)\s*(!important\s*)?(;|$)/i', $rule['body'])) {
                $removed[] = $rule['selector'];
            }
        }
        $this->assertSame(array('.app-main:focus'), $removed);
    }

    // The rule reaches the element the "Skip to main content" link moves to
    public function testTheMainElementIsTheSkipLinkTarget()
    {
        $header = file_get_contents(dirname(__DIR__, 2) . '/_includes/layout/header.inc.php');
        $this->assertMatchesRegularExpression('/<main class="app-main" id="main"[ >]/', $header);
        $this->assertStringContainsString('<a href="#main" class="skip-link">', (new Layout())->skipLinks());
    }

} //@formatter:on
