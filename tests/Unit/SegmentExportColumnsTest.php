<?php
/**
 * /tests/Unit/SegmentExportColumnsTest.php
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
 * The segments export (segments/index.php?export_data=1) wrote each segment's notes in every row but had no Notes
 * heading, so every column from Creation Type on sat one place to the right of its heading. The page writes the heading
 * row and the data rows as two lists of $row_contents[$count++] lines, so this checks that both lists name the same
 * columns in the same order. The page is a script that streams a file, so its source is read instead of running it.
 */
class SegmentExportColumnsTest extends TestCase
{
    public function testTheHeadingsMatchTheColumnsWritten()
    {
        $export = self::exportSource();

        // The heading row is the block of _('...') lines, and the data rows are the block of $row->... and computed values
        preg_match_all('/\$row_contents\s*\[\s*\$count\s*\+\+\s*\]\s*=\s*_\(\s*([\'"])([^\'"]+)\1\s*\)\s*;/', $export, $headings);
        preg_match_all('/\$row_contents\s*\[\s*\$count\s*\+\+\s*\]\s*=\s*+(?!_\()([^;]+);/', $export, $values);

        $this->assertSame(array('Segment', 'Description', 'Domain', 'Number of Domains in Segment', 'Notes',
            'Creation Type', 'Created By', 'Insert Time', 'Update Time'), $headings[2]);
        $this->assertSame(array('$row->name', '$row->description', '$row->domain', '$row->number_of_domains',
            '$row->notes', '$creation_type', '$created_by', '$time->toUserTimezone($row->insert_time)',
            '$time->toUserTimezone($row->update_time)'), array_map('trim', $values[1]));
    }

    // The per-segment count is a column only in the all-segments export, in the heading row and the data rows alike
    public function testTheSegmentCountColumnHasTheSameConditionInBothRows()
    {
        $export = self::exportSource();
        $guard = '\bif\s*\(\s*\$segid\s*===\s*0\s*\)\s*\{\s*\$row_contents\s*\[\s*\$count\s*\+\+\s*\]\s*=\s*';

        // Each block holds only the count, so its closing brace comes straight after it
        $this->assertMatchesRegularExpression('/' . $guard . '_\(\s*[\'"]Number of Domains in Segment[\'"]\s*\)\s*;\s*\}/', $export);
        $this->assertMatchesRegularExpression('/' . $guard . '\$row->number_of_domains\s*;\s*\}/', $export);
    }

    // The export branch of segments/index.php, without its comments, so a commented-out line doesn't count
    private static function exportSource()
    {
        $source = file_get_contents(DIR_ROOT . '/segments/index.php');
        self::assertNotFalse($source);

        $code = '';
        foreach (token_get_all($source) as $token) {
            if (is_array($token) && in_array($token[0], array(T_COMMENT, T_DOC_COMMENT), true)) continue;
            $code .= is_array($token) ? $token[1] : $token;
        }

        self::assertSame(1, preg_match('/\bif\s*\(\s*\$export_data\s*===\s*1\s*\)\s*\{/', $code, $match, PREG_OFFSET_CAPTURE));
        $start = $match[0][1];
        self::assertSame(1, preg_match('/\$export\s*->\s*closeFile\s*\(/', $code, $close, PREG_OFFSET_CAPTURE, $start));
        $end = $close[0][1];

        return substr($code, $start, $end - $start);
    }
}
