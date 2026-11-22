<?php
/**
 * /tests/Unit/HostingRenewalColoursTest.php
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
 * The Hosting list's renewal dates (css/datatables/hosting-main.css): a date waiting for its renewal to be confirmed is
 * amber and an overdue one red. In light mode the theme's two text shades (#664D03 and #58151C) were both nearly black,
 * so the two looked alike. Each colour needs at least 4.5:1 contrast (WCAG 1.4.3) on the list's rows, striped and hovered
 * ones included, in light and dark mode, and the two must read as different hues.
 */
class HostingRenewalColoursTest extends TestCase
{
    // The colour a rule in a stylesheet gives (a #rrggbb value or a var(--bs-...) name)
    protected function ruleColour($file, $selector)
    {
        $css = file_get_contents(dirname(__DIR__, 2) . '/' . $file);
        $found = preg_match('/^' . preg_quote($selector, '/') . ' \{\s*color: ([^;]+);\s*\}/m', $css, $matches);
        $this->assertSame(1, $found, $selector);
        return trim($matches[1]);
    }

    // The Nord dark mode's colour variables, from its main rule in css/base.css
    protected function darkColours()
    {
        $css = file_get_contents(dirname(__DIR__, 2) . '/css/base.css');
        $found = preg_match('/^\[data-bs-theme="dark"\],\s*\[data-bs-theme="dark"\]\[data-lte-primary\] \{([^}]*)\}/m', $css, $matches);
        $this->assertSame(1, $found);
        preg_match_all('/(--bs-[a-z-]+):\s*(#[0-9a-f]{6});/i', $matches[1], $pairs, PREG_SET_ORDER);
        $colours = array();
        foreach ($pairs as $pair) $colours[$pair[1]] = $pair[2];
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

    // A colour's hue in degrees (0 is red, 60 yellow) and its HSL lightness from 0 to 1
    protected function hueAndLightness(array $rgb)
    {
        list($red, $green, $blue) = array_map(function ($value) {
            return $value / 255;
        }, $rgb);
        $max = max($red, $green, $blue);
        $min = min($red, $green, $blue);
        $range = $max - $min;
        if ($range == 0) return array(0.0, ($max + $min) / 2);
        if ($max == $red) $hue = fmod(($green - $blue) / $range, 6);
        elseif ($max == $green) $hue = ($blue - $red) / $range + 2;
        else $hue = ($red - $green) / $range + 4;
        return array(fmod($hue * 60 + 360, 360), ($max + $min) / 2);
    }

    // The rows' backgrounds: the page, a striped row (60% of the tertiary background over the page), and a hovered row
    protected function rows(array $page, array $tertiary, array $hover)
    {
        $striped = array_map(function ($over, $under) {
            return $under + ($over - $under) * 0.6;
        }, $tertiary, $page);
        return array('page' => $page, 'striped' => $striped, 'hovered' => $hover);
    }

    /*
     * Each colour keeps 4.5:1 on every row, amber and red are at least 30 degrees of hue apart (the shorter way round,
     * since red sits on both sides of 0), and neither is nearly black (an HSL lightness of at least 25%, which the old
     * light mode shades, both 21%, fell short of)
     */
    protected function assertReadableAndDistinct($mode, $amber, $red, array $rows)
    {
        foreach (array('amber' => $amber, 'red' => $red) as $name => $colour) {
            foreach ($rows as $row => $background) {
                $this->assertGreaterThanOrEqual(4.5, $this->contrast($this->rgb($colour), $background), "$mode $name on the $row row");
            }
            $this->assertGreaterThanOrEqual(0.25, $this->hueAndLightness($this->rgb($colour))[1], "$mode $name lightness");
        }
        $apart = abs($this->hueAndLightness($this->rgb($amber))[0] - $this->hueAndLightness($this->rgb($red))[0]);
        $this->assertGreaterThanOrEqual(30, min($apart, 360 - $apart), "$mode hues");
    }

    public function testLightModeColoursAreReadableAndDistinct()
    {
        $amber = $this->ruleColour('css/datatables/hosting-main.css', '#hosting-main .domainmod-css-confirm');
        $red = $this->ruleColour('css/datatables/hosting-main.css', '#hosting-main .domainmod-css-expired');
        // Bootstrap's light backgrounds: the page (white), --bs-tertiary-bg, and --bs-secondary-bg (a hovered row)
        $this->assertReadableAndDistinct('light', $amber, $red,
            $this->rows($this->rgb('#ffffff'), $this->rgb('#f8f9fa'), $this->rgb('#e9ecef')));
    }

    public function testDarkModeColoursAreReadableAndDistinct()
    {
        $colours = $this->darkColours();
        $amber = $this->ruleColour('css/datatables/hosting-main.css', '[data-bs-theme="dark"] #hosting-main .domainmod-css-confirm');
        $red = $this->ruleColour('css/datatables/hosting-main.css', '[data-bs-theme="dark"] #hosting-main .domainmod-css-expired');
        $this->assertSame('var(--bs-warning-text-emphasis)', $amber);
        $this->assertSame('var(--bs-danger-text-emphasis)', $red);
        $this->assertReadableAndDistinct('dark', $colours['--bs-warning-text-emphasis'], $colours['--bs-danger-text-emphasis'],
            $this->rows($this->rgb($colours['--bs-body-bg']), $this->rgb($colours['--bs-tertiary-bg']),
                $this->rgb($colours['--bs-secondary-bg'])));
    }

} //@formatter:on
