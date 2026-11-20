<?php
/**
 * /tests/Unit/ProfileLanguageMessageTest.php
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
 * init.inc.php sets up gettext in the user's language before the page runs, so when Settings > Profile saved a new
 * language, "Your profile was updated." (and the conversion rates message, when the currency changed too) was shown
 * in the old one. The page now switches the locale to the new language whenever it differs from the one gettext was
 * set up with (DEFAULT_LANGUAGE, the session's language, which can differ from the saved one when the user changed it
 * in another browser), before any of its success messages are translated. The test images have no locales to
 * translate with, so this checks the order in the page's source.
 */
class ProfileLanguageMessageTest extends TestCase
{
    public function testTheLocaleIsSwitchedBeforeTheMessagesAreTranslated()
    {
        $source = file_get_contents(DIR_ROOT . '/settings/profile/index.php');
        $this->assertNotFalse($source);
        $source = self::withoutComments($source);

        $switch = self::offset('/\bif\s*\(\s*\$new_language\s*!=\s*DEFAULT_LANGUAGE\s*\)\s*\{\s*' .
            'setlocale\s*\(\s*LC_ALL\s*,\s*\$new_language\s*\)\s*;/', $source);
        $rates = self::offset('/\$conversion\s*->\s*updateRates\s*\(/', $source);
        $updated = self::offset('/\b_\s*\(\s*\'Your profile was updated\.\'\s*\)/', $source);

        $this->assertNotNull($switch);
        $this->assertNotNull($rates);
        $this->assertNotNull($updated);
        $this->assertLessThan($rates, $switch);
        $this->assertLessThan($updated, $switch);
        // It's in the same block as the success message, not inside a branch such as the currency change's.
        $this->assertSame(self::depth($source, $updated), self::depth($source, $switch));
    }

    // How many blocks are open at an offset.
    private static function depth($source, $offset)
    {
        $before = substr($source, 0, $offset);
        return substr_count($before, '{') - substr_count($before, '}');
    }

    // The source with each comment replaced by a space, so code that's commented out doesn't count.
    private static function withoutComments($source)
    {
        $code = '';
        foreach (\PhpToken::tokenize($source) as $token) {
            $code .= $token->is(array(T_COMMENT, T_DOC_COMMENT)) ? ' ' : $token->text;
        }
        return $code;
    }

    // The offset of the pattern's only match, or null when the pattern doesn't match exactly once.
    private static function offset($pattern, $source)
    {
        if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE) !== 1) return null;
        return $matches[0][0][1];
    }

} //@formatter:on
