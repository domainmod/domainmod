<?php
/**
 * /tests/Unit/FeePagePostedTextTest.php
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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;


/*
 * The registrar and SSL provider fee pages read the posted TLD and currency only when they're text. Sanitize::text()
 * passes its value to htmlspecialchars(), so a field posted as an array (new_tld[]=com) once stopped the page with a
 * TypeError instead of showing the usual field error. Every value a fee page hands to Sanitize::text() straight from
 * $_POST or $_GET has an is_string() check for the same field in the same statement.
 */
class FeePagePostedTextTest extends TestCase
{
    public static function pages()
    {
        return array(
            'add registrar fee' => array('assets/add/registrar-fee.php'),
            'edit registrar fee' => array('assets/edit/registrar-fee.php'),
            'add SSL provider fee' => array('assets/add/ssl-provider-fee.php'),
            'edit SSL provider fee' => array('assets/edit/ssl-provider-fee.php'),
        );
    }

    #[DataProvider('pages')]
    public function testPostedTextIsCheckedBeforeItIsSanitized($page)
    {
        $source = file_get_contents(DIR_ROOT . '/' . $page);
        $this->assertNotFalse($source);
        $this->assertSame(array(), self::uncheckedReads($source));
    }

    public function testTheCheckRefusesTheOldReads()
    {
        // The reads the pages used before the fix, and other spellings of them, which the check has to refuse
        $this->assertSame(array(1, 2, 3, 4, 5, 7, 9), self::uncheckedReads(
            "\$new_tld = isset(\$_POST['new_tld']) ? \$sanitize->text(\$_POST['new_tld']) : '';\n" .
            "\$new_tld = \$sanitize->text(\$_POST['new_tld'] ?? '');\n" .
            "\$tld = \$sanitize->text( \$_GET[\"tld\"] );\n" .
            // checked, but for another field
            "\$new_currency = is_string(\$_POST['new_tld'] ?? null) ? \$sanitize->text(\$_POST['new_currency']) : '';\n" .
            // the request value on the line after the call
            "\$new_tld = \$sanitize->text(\n    \$_POST['new_tld']);\n" .
            "\$new_tld = \$sanitize->text\n    (\$_POST['new_tld']);\n" .
            // a check turned around
            "\$new_tld = !is_string(\$_POST['new_tld'] ?? null) ? \$sanitize->text(\$_POST['new_tld']) : '';\n"));
        $this->assertSame(array(), self::uncheckedReads(
            "\$new_tld = is_string(\$_POST['new_tld'] ?? null) ? \$sanitize->text(\$_POST['new_tld']) : '';\n" .
            "\$new_currency = is_string(\$_POST['new_currency'] ?? null)\n    ? \$sanitize->text(\$_POST['new_currency']) : '';\n"));
    }

    // The lines (numbered from 1) where a request value is handed to Sanitize::text() without an is_string() check for
    // the same field in the same statement (not turned around with !)
    private static function uncheckedReads($source)
    {
        preg_match_all('/\$sanitize\s*->\s*text\s*\(\s*\$_(POST|GET|REQUEST)\s*\[\s*([\'"])([^\'"]+)\2\s*\]/', $source, $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $unchecked = array();
        foreach ($matches as $match) {
            $offset = $match[0][1];
            $start = strrpos(substr($source, 0, $offset), ';');
            $start = $start === false ? 0 : $start + 1;
            $end = strpos($source, ';', $offset);
            $statement = substr($source, $start, ($end === false ? strlen($source) : $end) - $start);
            if (!preg_match('/(?<![!\w])is_string\(\s*\$_' . $match[1][0] . '\s*\[\s*[\'"]' . preg_quote($match[3][0], '/') .
                '[\'"]\s*\]/', $statement)) {
                $unchecked[] = substr_count($source, "\n", 0, $offset) + 1;
            }
        }
        return $unchecked;
    }

} //@formatter:on
