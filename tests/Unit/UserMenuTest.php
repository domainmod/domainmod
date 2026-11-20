<?php
/**
 * /tests/Unit/UserMenuTest.php
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

use DomainMOD\System;
use DomainMOD\TwoFactor;
use DomainMOD\UserMenu;
use PHPUnit\Framework\TestCase;

// The top bar's user menu: the initials, role badges, last sign-in, and the MFA flag System keeps in the session for it
class UserMenuTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = array();
    }

    protected function tearDown(): void
    {
        $_SESSION = array();
    }

    public function testInitialsAreTheFirstLettersInCapitals()
    {
        $this->assertSame('RA', UserMenu::initials('Robin', 'Ashdown'));
        $this->assertSame('RA', UserMenu::initials(' robin', 'ashdown '));
        $this->assertSame('R', UserMenu::initials('Robin', ''));
        $this->assertSame('A', UserMenu::initials('', 'Ashdown'));
        $this->assertSame('', UserMenu::initials('', '  '));
        $this->assertSame('', UserMenu::initials(null, null));
    }

    public function testInitialsDecodeTheStoredNameAndEscapeTheResult()
    {
        // Names are stored HTML-escaped (Sanitize::text()), so a stored "&amp;" is the one character "&", which is
        // escaped again for output
        $this->assertSame('&amp;O', UserMenu::initials('&amp;Co', 'O&#039;Brien'));
        $this->assertSame('&lt;&quot;', UserMenu::initials('&lt;b&gt;', '&quot;x&quot;'));
    }

    public function testInitialsKeepAWholeCharacterOutsideAscii()
    {
        $initials = UserMenu::initials('émile', 'Łukasz');

        $this->assertSame(1, preg_match('//u', $initials));
        $this->assertSame(function_exists('mb_strtoupper') ? 'ÉŁ' : 'éŁ', $initials);
        // A name that isn't valid UTF-8 is skipped rather than cut into a broken character
        $this->assertSame('A', UserMenu::initials("\xC3", 'Ashdown'));
    }

    public function testRoleLabels()
    {
        $this->assertSame(array('Administrator'), UserMenu::roleLabels(1, '0'));
        $this->assertSame(array('User'), UserMenu::roleLabels(0, '0'));
        $this->assertSame(array('Read-Only'), UserMenu::roleLabels(0, '1'));
        // An admin can be read-only too
        $this->assertSame(array('Administrator', 'Read-Only'), UserMenu::roleLabels(1, '1'));
    }

    public function testLastSignInIsInTheUsersTimeZone()
    {
        $_SESSION['s_default_timezone'] = 'America/Phoenix';

        $this->assertSame('2026-11-19 5:12pm MST', UserMenu::lastSignIn('2026-11-20 00:12:45'));

        $_SESSION['s_default_timezone'] = 'UTC';
        $this->assertSame('2026-11-20 12:12am UTC', UserMenu::lastSignIn('2026-11-20 00:12:45'));
    }

    public function testLastSignInIsLeftOutWhenNeverSetOrUnreadable()
    {
        $this->assertSame('', UserMenu::lastSignIn('1970-01-01 00:00:00'));
        $this->assertSame('', UserMenu::lastSignIn('0000-00-00 00:00:00'));
        $this->assertSame('', UserMenu::lastSignIn(''));
        $this->assertSame('', UserMenu::lastSignIn(null));
        $this->assertSame('', UserMenu::lastSignIn('not a time'));
    }

    public function testSystemKeepsTheMfaFlagFromTheSameRead()
    {
        $state = array('id' => 7, 'secret' => '', 'new_password' => '0', 'required' => '0', 'codes_confirmed' => '1',
            'stamp' => 'the stamp', 'last_step' => 0, 'password' => 'the hash');
        $_SESSION = array('s_stored_hash' => 'the hash', 's_two_factor_stamp' => 'the stamp');

        $this->assertSame('valid', $this->twoFactorResult($state, '4.27.0'));
        $this->assertSame(0, $_SESSION['s_two_factor_on']);

        $this->assertSame('valid', $this->twoFactorResult(array('secret' => 'a secret') + $state, '4.27.0'));
        $this->assertSame(1, $_SESSION['s_two_factor_on']);

        // Before the database has two-factor sign-in, when the version can't be read, and when the state can't be
        // read or has no active user, the menu leaves the line out
        $this->assertSame('valid', $this->twoFactorResult($state, '4.26.0', array('s_stored_hash' => 'the hash', 's_two_factor_on' => 1)));
        $this->assertArrayNotHasKey('s_two_factor_on', $_SESSION);
        $_SESSION['s_two_factor_on'] = 1;
        $this->assertSame('invalid', $this->twoFactorResult($state, ''));
        $this->assertArrayNotHasKey('s_two_factor_on', $_SESSION);
        $_SESSION['s_two_factor_on'] = 1;
        $this->assertSame('invalid', $this->twoFactorResult(new \PDOException('Failed'), '4.27.0'));
        $this->assertArrayNotHasKey('s_two_factor_on', $_SESSION);
        // No active user (such as one deactivated since the sign-in)
        $_SESSION['s_two_factor_on'] = 1;
        $this->assertSame('invalid', $this->twoFactorResult(false, '4.27.0'));
        $this->assertArrayNotHasKey('s_two_factor_on', $_SESSION);
    }

    // System::twoFactorResult() with the user's two-factor state given, rather than read from the database. A
    // Throwable given as the state is thrown when it's read.
    protected function twoFactorResult($state, $db_version, ?array $session = null)
    {
        if ($session !== null) $_SESSION = $session;

        $system = new class($state) extends System {
            public $state;

            public function __construct($state)
            {
                $this->state = $state;
            }

            protected function newTwoFactor()
            {
                return new class($this->state) extends TwoFactor {
                    public $state;

                    public function __construct($state)
                    {
                        $this->state = $state;
                    }

                    public function getState($user_id)
                    {
                        if ($this->state instanceof \Throwable) throw $this->state;
                        return $this->state;
                    }

                    public function pendingSteps($state)
                    {
                        return array();
                    }
                };
            }

            protected function currentPage()
            {
                return '/dashboard/index.php';
            }
        };

        $result = new \ReflectionMethod(System::class, 'twoFactorResult');
        return $result->invoke($system, 7, $db_version);
    }

} //@formatter:on
