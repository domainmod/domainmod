<?php
/**
 * /tests/Unit/SignInLanguageTest.php
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
 * Once the password is right, the user's own language is used for the code step (kept with the pending sign-in, and
 * only on two-factor.php within its time limit, so an abandoned code step doesn't leave it behind) and set for the
 * session only when sign-in completes, before checks.php (4.27.0: an Arabic user's code page was in English, and so was
 * the first message after signing in). These are page scripts, so this checks their source with comments removed.
 */
class SignInLanguageTest extends TestCase
{
    public function testThePendingSignInCarriesTheUsersLanguageAndItsTimeLimit()
    {
        $source = self::code('index.php');
        $this->assertNotNull(self::offset('/\$user_language\s*=\s*\$login->getUserLanguage\s*\(\s*\$user_id\s*\)\s*;/', $source));
        $this->assertStringContainsString("'language' => \$user_language,", $source);
        $this->assertStringContainsString("'language_until' => \$now + DomainMOD\\TwoFactor::PENDING_SECONDS", $source);
        // Nothing sets the session's language before sign-in completes
        $complete = strpos($source, '$login->completeSignIn(');
        $this->assertNotFalse($complete);
        $this->assertSame(0, preg_match('/\$_SESSION\s*\[\s*\'s_default_language\'\s*\]\s*=/', substr($source, 0, $complete)));
    }

    public function testBothSignInPathsSetTheLanguageJustBeforeChecks()
    {
        $set = '\$_SESSION\s*\[\s*\'s_default_language\'\s*\]\s*=\s*';
        $this->assertNotNull(self::offset("/if\\s*\\(\\s*\\\$user_language\\s*!==\\s*''\\s*\\)\\s*" . $set .
            '\$user_language\s*;\s*header\s*\(\s*"Location: checks\.php"\s*\)\s*;/', self::code('index.php')));
        $this->assertNotNull(self::offset("/if\\s*\\(\\s*\\(\\s*\\\$pending\\s*\\[\\s*'language'\\s*\\]\\s*\\?\\?\\s*''\\s*\\)\\s*!==\\s*''\\s*\\)\\s*" .
            $set . "\\\$pending\\s*\\[\\s*'language'\\s*\\]\\s*;\\s*header\\s*\\(\\s*'Location: checks\\.php'\\s*\\)\\s*;/", self::code('two-factor.php')));
    }

    public function testTheCodeStepUsesThePendingLanguageOnlyOnItsPageAndWithinItsTimeLimit()
    {
        $init = self::code('_includes/init.inc.php');
        $this->assertNotNull(self::offset("/basename\\s*\\(\\s*\\\$_SERVER\\s*\\[\\s*'SCRIPT_NAME'\\s*\\]\\s*\\?\\?\\s*''\\s*\\)\\s*===\\s*'two-factor\\.php'/", $init));
        $this->assertNotNull(self::offset("/time\\s*\\(\\s*\\)\\s*<=\\s*\\(int\\)\\s*\\(\\s*\\\$_SESSION\\s*\\[\\s*'s_two_factor_pending'\\s*\\]\\s*\\[\\s*'language_until'\\s*\\]/", $init));
        // After the signed-in and installer languages, before a leftover session language
        $pending = strpos($init, "'two-factor.php'");
        $this->assertGreaterThan(strpos($init, "isset(\$_SESSION['s_installation_language'])"), $pending);
        $this->assertLessThan(strpos($init, "} elseif (isset(\$_SESSION['s_default_language'])) {"), $pending);
    }

    // The file's source with each comment replaced by a space, so code that's commented out doesn't count.
    private static function code($file)
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);
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
