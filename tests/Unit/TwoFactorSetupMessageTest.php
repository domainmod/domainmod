<?php
/**
 * /tests/Unit/TwoFactorSetupMessageTest.php
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
 * With "Require MFA for All Users" on, checks.php sent a user without two-factor sign-in to its setup page
 * with no word of why. It now adds a message saying an administrator requires it, only for the setup step (replacing
 * a secret and confirming recovery codes explain themselves on the page), before the redirect. checks.php is a page
 * script, so this checks its source, like ProfileLanguageMessageTest.
 */
class TwoFactorSetupMessageTest extends TestCase
{
    public function testTheSetupStepIsExplainedBeforeTheRedirect()
    {
        $source = file_get_contents(DIR_ROOT . '/checks.php');
        $this->assertNotFalse($source);
        $source = self::withoutComments($source);

        // The two-factor branch: the setup step's own block starts the message from an empty string when the session has
        // none yet and adds it, and then the redirect runs for every two-factor step
        $danger = '\$_SESSION\s*\[\s*\'s_message_danger\'\s*\]';
        $pattern = '/\}\s*elseif\s*\(\s*\$sign_in_steps\s*!==\s*array\s*\(\s*\)\s*\)\s*\{\s*' .
            'if\s*\(\s*in_array\s*\(\s*\'setup\'\s*,\s*\$sign_in_steps\s*,\s*true\s*\)\s*\)\s*\{\s*' .
            $danger . '\s*=\s*' . $danger . '\s*\?\?\s*\'\'\s*;\s*' .
            $danger . '\s*\.=\s*_\s*\(\s*\'An administrator requires MFA, so please set it up before ' .
            'continuing\.\'\s*\)\s*\.\s*\'<BR>\'\s*;\s*\}\s*' .
            'header\s*\(\s*"Location: settings\/two-factor\/"\s*\)\s*;\s*exit\s*;\s*\}/';

        $this->assertNotNull(self::offset($pattern, $source));
        // The message isn't added anywhere else, such as for the steps that explain themselves on the page
        $this->assertSame(1, substr_count($source, 'An administrator requires MFA'));
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
