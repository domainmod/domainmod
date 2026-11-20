<?php
/**
 * /tests/Unit/LoginSignInTest.php
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

use DomainMOD\Login;
use PHPUnit\Framework\TestCase;

// Login::completeSignIn() is the one place that sets a session as signed in
class LoginSignInTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = array();
    }

    protected function tearDown(): void
    {
        $_SESSION = array();
    }

    // Login without its database: the version and the session ID are recorded instead
    protected function login()
    {
        return new class extends Login {
            public $regenerated = 0;
            public $can_regenerate = true;

            public function __construct()
            {
            }

            protected function getDbVersion()
            {
                return '4.26.0';
            }

            protected function regenerateSession()
            {
                $this->regenerated++;
                return $this->can_regenerate;
            }
        };
    }

    public function testSetsTheSessionAsSignedIn()
    {
        $_SESSION['s_csrf_token'] = 'the token from before';
        $_SESSION['s_user_redirect'] = '/domains/';
        $_SESSION['s_last_login_recorded'] = 1;
        $login = $this->login();

        $this->assertTrue($login->completeSignIn(7, 'jo', 'the hash'));

        $this->assertSame(7, $_SESSION['s_user_id']);
        $this->assertSame('jo', $_SESSION['s_username']);
        $this->assertSame('the hash', $_SESSION['s_stored_hash']);
        $this->assertSame('4.26.0', $_SESSION['s_system_db_version']);
        $this->assertSame(1, $_SESSION['s_is_logged_in']);
        // A new session ID and token, so neither carries over from before signing in
        $this->assertSame(1, $login->regenerated);
        $this->assertNotSame('the token from before', $_SESSION['s_csrf_token']);
        // checks.php records this sign-in once
        $this->assertArrayNotHasKey('s_last_login_recorded', $_SESSION);
        // The page asked for before signing in is still there for checks.php
        $this->assertSame('/domains/', $_SESSION['s_user_redirect']);
    }

    public function testASessionWhoseIdCantBeReplacedIsntSignedIn()
    {
        $login = $this->login();
        $login->can_regenerate = false;

        $this->assertFalse($login->completeSignIn(7, 'jo', 'the hash'));
        $this->assertArrayNotHasKey('s_is_logged_in', $_SESSION);
        $this->assertArrayNotHasKey('s_user_id', $_SESSION);
    }

    public function testTheIdIsReplacedOnceWhenTheCallerAlreadyDidIt()
    {
        $login = $this->login();
        $login->can_regenerate = false;

        // two-factor.php replaces the ID before using a code, so it isn't replaced (or refused) a second time
        $this->assertTrue($login->completeSignIn(7, 'jo', 'the hash', null, true));
        $this->assertSame(0, $login->regenerated);
        $this->assertSame(1, $_SESSION['s_is_logged_in']);
    }

    public function testPermissionsAreSetBothWays()
    {
        $login = $this->login();

        $login->setPermissions((object) array('admin' => '1', 'read_only' => '0'));
        $this->assertSame(1, $_SESSION['s_is_admin']);
        $this->assertSame('0', $_SESSION['s_read_only']);

        // Demoted since: the session loses what it had
        $login->setPermissions((object) array('admin' => '0', 'read_only' => '1'));
        $this->assertArrayNotHasKey('s_is_admin', $_SESSION);
        $this->assertSame('1', $_SESSION['s_read_only']);

        // No active user: nothing more than read-only
        $_SESSION['s_is_admin'] = 1;
        $login->setPermissions(false);
        $this->assertArrayNotHasKey('s_is_admin', $_SESSION);
        $this->assertSame('1', $_SESSION['s_read_only']);
    }

} //@formatter:on
