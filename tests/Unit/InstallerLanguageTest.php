<?php
/**
 * /tests/Unit/InstallerLanguageTest.php
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

use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

/*
 * init.inc.php used the language chosen in the installer whenever the session held it, before the user's own, and
 * nothing cleared it after the installation, so the person who installed DomainMOD kept seeing the installer's language
 * after choosing another one in their profile, until they signed out. A signed-in user's language now comes first, and
 * checks.php clears the installer's answers once the first sign-in has loaded the user's settings. The language tests
 * run init.inc.php's language block, each in its own process, since it defines DEFAULT_LANGUAGE.
 */
#[RunTestsInSeparateProcesses]
class InstallerLanguageTest extends TestCase
{
    public function testASignedInUsersOwnLanguageWinsOverTheInstallers()
    {
        $this->assertSame('en_US.UTF-8', self::languageFor(array(
            's_is_logged_in' => 1,
            's_installation_language' => 'nl_NL.UTF-8',
            's_default_language' => 'en_US.UTF-8')));
    }

    public function testTheInstallerUsesTheLanguageChosenInIt()
    {
        $this->assertSame('nl_NL.UTF-8', self::languageFor(array('s_installation_language' => 'nl_NL.UTF-8')));
    }

    public function testASignedOutSessionStillUsesTheInstallersLanguage()
    {
        // Such as a session left from before signing out cleared everything
        $this->assertSame('nl_NL.UTF-8', self::languageFor(array(
            's_installation_language' => 'nl_NL.UTF-8',
            's_default_language' => 'de_DE.UTF-8')));
    }

    public function testASignedInUserWithoutTheInstallersLanguageGetsTheirOwn()
    {
        $this->assertSame('de_DE.UTF-8', self::languageFor(array(
            's_is_logged_in' => 1,
            's_default_language' => 'de_DE.UTF-8')));
    }

    public function testASessionWithNeitherUsesTheServersLanguage()
    {
        $this->assertSame('fr_FR.UTF-8', self::languageFor(array(), array('LANG' => 'fr_FR.UTF-8')));
    }

    public function testASessionWithNothingAtAllIsInEnglish()
    {
        $this->assertSame('en_US.UTF-8', self::languageFor(array()));
    }

    public function testChecksClearsTheInstallersAnswersOnceTheUsersSettingsAreLoaded()
    {
        $source = file_get_contents(DIR_ROOT . '/checks.php');
        $this->assertNotFalse($source);
        $source = self::withoutComments($source);

        $loaded = self::offset('/\$_SESSION\[\'s_default_language\'\]\s*=\s*\$result->default_language\s*;/', $source);
        // Straight after the installer mode's own unset, which starts a statement, so it can't be made conditional on its
        // own (such as by an if without braces)
        $cleared = self::offset('/(?<=[;}])\s*unset\s*\(\s*\$_SESSION\[\'s_installation_mode\'\]\s*\)\s*;\s*' .
            'unset\s*\(\s*\$_SESSION\[\'s_installation_language\'\]\s*,\s*' .
            '\$_SESSION\[\'s_installation_currency\'\]\s*,\s*\$_SESSION\[\'s_installation_timezone\'\]\s*\)\s*;/',
            $source);

        $this->assertNotNull($loaded);
        $this->assertNotNull($cleared);
        $this->assertLessThan($cleared, $loaded);
        // On every sign-in that gets that far, not inside a branch
        $this->assertSame(0, self::depthAt($source, $cleared));
    }

    // DEFAULT_LANGUAGE after running init.inc.php's language block with this session and environment
    private static function languageFor($session, $env = array())
    {
        $source = file_get_contents(DIR_INC . '/init.inc.php');
        if (!preg_match('/^\/\/ Language Settings$.*?(?=^const DEFAULT_ENCODING_TYPE)/ms', $source, $match)) {
            self::fail('init.inc.php has no language block');
        }
        $_SESSION = $session;
        $_ENV = $env;
        eval($match[0]);
        return DEFAULT_LANGUAGE;
    }

    // How many of the code's blocks are open at the offset (0 at the top level of the page)
    private static function depthAt($source, $offset)
    {
        $depth = 0;
        foreach (\PhpToken::tokenize($source) as $token) {
            if ($token->pos >= $offset) break;
            if (in_array($token->id, array(ord('{'), T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES), true)) $depth++;
            if ($token->id === ord('}')) $depth--;
        }
        return $depth;
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
