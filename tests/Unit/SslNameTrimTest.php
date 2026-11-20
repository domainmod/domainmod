<?php
/**
 * /tests/Unit/SslNameTrimTest.php
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

use DomainMOD\Sanitize;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;


/*
 * The Add and Edit SSL Certificate pages once read the posted Host / Label without trimming it, and the only check was
 * that it wasn't empty, so a label of just spaces saved (and the Add page's toast linked to the new certificate with no
 * visible text). Both pages now trim the posted name before sanitizing it, so a blank one fails the empty check, and a
 * name posted as an array is read as empty (as on the fee pages) instead of stopping the page with a TypeError.
 */
class SslNameTrimTest extends TestCase
{
    public static function pages()
    {
        return array(
            'add SSL certificate' => array('ssl/add.php'),
            'edit SSL certificate' => array('ssl/edit.php'),
        );
    }

    #[DataProvider('pages')]
    public function testThePostedNameIsTrimmedBeforeItIsChecked($page)
    {
        $source = file_get_contents(DIR_ROOT . '/' . $page);
        $this->assertNotFalse($source);
        $this->assertSame(1, preg_match_all('/\$sanitize\s*->\s*text\s*\([^;]*\$_POST\s*\[\s*([\'"])new_name\1/', $source));
        $this->assertSame(array(), self::untrimmedReads($source));
        // The trimmed value is the one the page checks and saves, and a name posted as an array is refused.
        $this->assertSame(1, preg_match_all('/^\$new_name\s*=\s*is_string\(\s*\$_POST\s*\[\s*\'new_name\'\s*\]\s*\?\?\s*null\s*\)\s*\?\s*' .
            '\$sanitize\s*->\s*text\s*\(\s*trim\s*\(\s*\$_POST\s*\[\s*\'new_name\'\s*\]\s*\)\s*\)\s*:\s*\'\'\s*;$/m', $source));
        $this->assertMatchesRegularExpression('/\$new_name\s*!=\s*""/', $source);
    }

    public function testTheCheckRefusesTheOldRead()
    {
        $this->assertSame(array(1, 2, 3), self::untrimmedReads(
            "\$new_name = isset(\$_POST['new_name']) ? \$sanitize->text(\$_POST['new_name']) : '';\n" .
            "\$new_name = trim(\$sanitize->text(\$_POST[\"new_name\"]));\n" .
            // Trimmed, but only of tabs.
            "\$new_name = \$sanitize->text(trim(\$_POST['new_name'], \"\\t\"));\n"));
        $this->assertSame(array(), self::untrimmedReads(
            "\$new_name = isset(\$_POST['new_name']) ? \$sanitize->text(trim(\$_POST['new_name'])) : '';\n"));
    }

    public function testATrimmedBlankNameIsEmptyOnceSanitized()
    {
        $sanitize = new Sanitize();

        $this->assertSame('', $sanitize->text(trim(" \t \n ")));
        $this->assertSame('www.example.com', $sanitize->text(trim('  www.example.com  ')));
    }

    // The lines (numbered from 1) where the posted name is sanitized without trim() first, or with a trim() given its
    // own list of characters, which can leave spaces.
    private static function untrimmedReads($source)
    {
        preg_match_all('/\$sanitize\s*->\s*text\s*\(\s*(trim\s*\(\s*)?\$_POST\s*\[\s*([\'"])new_name\2\s*\]\s*(\))?/',
            $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $untrimmed = array();
        foreach ($matches as $match) {
            // trim() with only the posted value, so it removes its default characters, spaces included.
            $trimmed = isset($match[1], $match[3]) && $match[1][0] !== '' && $match[3][0] !== '';
            if (!$trimmed) {
                $untrimmed[] = substr_count($source, "\n", 0, $match[0][1]) + 1;
            }
        }
        return $untrimmed;
    }

} //@formatter:on
