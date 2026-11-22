<?php
/**
 * /tests/Unit/DarkModeCodeContrastTest.php
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
 * Inline code (such as the status codes in the domain edit page's Registry Status list) in the Nord dark mode, read
 * from css/base.css. Bootstrap's dark mode pink had 4.0:1 contrast on the page, so axe failed it. It now needs at least
 * 4.5:1 (WCAG 1.4.3) on the page, the cards, the darker and lighter Polar Night backgrounds, and striped table rows.
 */
class DarkModeCodeContrastTest extends TestCase
{
    // The Nord dark mode's colour variables, from its main rule in css/base.css
    protected function darkColours()
    {
        $css = file_get_contents(dirname(__DIR__, 2) . '/css/base.css');
        $found = preg_match('/^\[data-bs-theme="dark"\],\s*\[data-bs-theme="dark"\]\[data-lte-primary\] \{([^}]*)\}/m', $css, $matches);
        $this->assertSame(1, $found);
        preg_match_all('/(--bs-[a-z-]+):\s*(#[0-9a-f]{6});/i', $matches[1], $pairs, PREG_SET_ORDER);
        $colours = array();
        foreach ($pairs as $pair) {
            $colours[$pair[1]] = $pair[2];
        }
        return $colours;
    }

    // A #rrggbb colour as its red, green, and blue values from 0 to 255
    protected function rgb($hex)
    {
        return array_map('hexdec', str_split(ltrim($hex, '#'), 2));
    }

    // WCAG's relative luminance of red, green, and blue values from 0 to 255
    protected function luminance(array $rgb)
    {
        $linear = array_map(function ($value) {
            $value /= 255;
            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, $rgb);
        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }

    protected function contrast(array $first, array $second)
    {
        $lighter = max($this->luminance($first), $this->luminance($second));
        $darker = min($this->luminance($first), $this->luminance($second));
        return ($lighter + 0.05) / ($darker + 0.05);
    }

    public function testInlineCodeKeepsAaContrast()
    {
        $colours = $this->darkColours();
        $this->assertArrayHasKey('--bs-code-color', $colours);
        $code = $this->rgb($colours['--bs-code-color']);

        // Striped rows lay Bootstrap's 5% of the emphasis colour over the page
        $page = $this->rgb($colours['--bs-body-bg']);
        $emphasis = $this->rgb($colours['--bs-emphasis-color']);
        $striped = array_map(function ($under, $over) {
            return $under + ($over - $under) * 0.05;
        }, $page, $emphasis);

        $backgrounds = array(
            'page' => $page,
            'darker' => $this->rgb($colours['--bs-tertiary-bg']),
            'lighter' => $this->rgb($colours['--bs-secondary-bg']),
            'striped' => $striped,
        );
        foreach ($backgrounds as $name => $background) {
            $this->assertGreaterThanOrEqual(4.5, $this->contrast($code, $background), $name);
        }
    }

    // Bootstrap's own dark mode pink, which axe found too faint, fails the same check
    public function testBootstrapsPinkFails()
    {
        $colours = $this->darkColours();
        $this->assertLessThan(4.5, $this->contrast($this->rgb('#e685b5'), $this->rgb($colours['--bs-body-bg'])));
    }

} //@formatter:on
