<?php
/**
 * /tests/Unit/TileRowGapTest.php
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
 * Every row of Dashboard::displayPanel() cards has a vertical gutter (gy-4, the same 1.5rem as Bootstrap's default
 * horizontal gutter), so cards that wrap onto a second row on smaller screens don't touch (Greg's choice, 2026-11-21).
 * The gutter adds no space below the row, so the gap before the next section stays as it was. A paragraph straight
 * after the row gets mt-4, the same gap, so it doesn't sit against the cards (Greg's choice, 2026-11-21).
 */
class TileRowGapTest extends TestCase
{
    public function testEveryTileRowHasAVerticalGutter()
    {
        $root = dirname(__DIR__, 2);
        $files = $this->tileFiles($root);
        $this->assertSame(array('_includes/layout/cost-report.inc.php', 'reporting/domains/at-risk.php',
            'reporting/domains/tld-pricing.php', 'reporting/domains/upcoming-renewals.php', 'reporting/dw/potential-problems.php',
            'reporting/hosting/at-risk.php', 'reporting/hosting/upcoming-renewals.php', 'reporting/renewal-calendar.php',
            'reporting/ssl/at-risk.php', 'reporting/ssl/upcoming-renewals.php'), $files);
        foreach ($files as $path) {
            $source = file_get_contents($root . '/' . $path);
            $before = substr($source, 0, strpos($source, '$dashboard->displayPanel('));
            // The row the first card is in, the last one opened before it
            preg_match_all('#<div class="(row[^"]*)">#', $before, $rows);
            $this->assertNotEmpty($rows[1], $path);
            $classes = explode(' ', end($rows[1]));
            $this->assertContains('gy-4', $classes, $path);
            // and it's still open when the first card is drawn
            $this->assertStringNotContainsString('</div>', substr($before, strrpos($before, '<div class="row')), $path);
            // Every card is in that row: it doesn't close, and no other row opens, between the first card and the last
            $first = strlen($before);
            $cards = substr($source, $first, strrpos($source, '$dashboard->displayPanel(') - $first);
            $this->assertStringNotContainsString('</div>', $cards, $path);
            $this->assertStringNotContainsString('<div class="row', $cards, $path);
        }
    }

    // A paragraph that comes straight after a tile row, before any other element, has mt-4, as the headings, callouts,
    // and sections after the other rows have their own top margin
    public function testAParagraphAfterATileRowHasATopMargin()
    {
        $root = dirname(__DIR__, 2);
        $paragraphs = array();
        foreach ($this->tileFiles($root) as $path) {
            $source = file_get_contents($root . '/' . $path);
            // The row closes at the first </div> after the last card
            $close = strpos($source, '</div>', strrpos($source, '$dashboard->displayPanel(')) + strlen('</div>');
            if (!preg_match('#<(p|h[1-6]|div|table|ul)\b([^>]*)>#', substr($source, $close), $element)) continue;
            if ($element[1] !== 'p') continue;
            $paragraphs[] = $path;
            $this->assertMatchesRegularExpression('#\bclass="[^"]*\bmt-4\b#', $element[2], $path);
        }
        // The three paragraphs that follow a row: the no-transfer-lock line, the hosting renewals note, and the no-fee note
        $this->assertSame(array('reporting/domains/at-risk.php', 'reporting/hosting/upcoming-renewals.php',
            'reporting/renewal-calendar.php'), $paragraphs);
    }

    // The pages and includes that draw Dashboard::displayPanel() cards
    private function tileFiles($root)
    {
        $files = array();
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;
            $path = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
            if (preg_match('#^(\.git|\.data|classes|tests|theme|vendor)/#', $path)) continue;
            if (strpos(file_get_contents($file->getPathname()), '$dashboard->displayPanel(') !== false) $files[] = $path;
        }
        sort($files);
        return $files;
    }

} //@formatter:on
