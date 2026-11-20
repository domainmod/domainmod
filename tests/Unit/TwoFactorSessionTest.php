<?php
/**
 * /tests/Unit/TwoFactorSessionTest.php
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
use DomainMOD\System;
use DomainMOD\Tests\Support\FakeWriteDatabase;
use DomainMOD\Tests\Support\Instance;
use DomainMOD\Time;
use DomainMOD\TwoFactor;
use PHPUnit\Framework\TestCase;

// Two-factor sign-in's session rules: the stamp, unfinished sign-in steps, remembered devices, and permissions
class TwoFactorSessionTest extends TestCase
{
    const NOW = 1700000000;

    protected function setUp(): void
    {
        $_SESSION = array();
        unset($_SERVER['HTTPS']);
    }

    protected function tearDown(): void
    {
        $_SESSION = array();
        unset($_SERVER['HTTPS']);
    }

    protected function twoFactor($db = null)
    {
        return Instance::withoutConstructor(TwoFactor::class, array('secret_key' => 'a key', 'pdo' => $db, 'time' => new Time()));
    }

    protected function state(array $values = array())
    {
        return $values + array('id' => 7, 'secret' => '', 'new_password' => '0', 'required' => '0',
            'codes_confirmed' => '1', 'stamp' => 'the stamp', 'last_step' => 0, 'password' => 'the hash');
    }

    protected function context()
    {
        return array('user_id' => 7, 'password_hash' => 'the hash', 'stamp' => 'the stamp', 'secret' => 'raw:X',
            'last_step' => 0, 'settings' => false);
    }

    public function testAMatchingStampIsValid()
    {
        $session = array('s_stored_hash' => 'the hash', 's_two_factor_stamp' => 'the stamp');

        $this->assertSame('valid', $this->twoFactor()->sessionResult($this->state(), $session, '/domains/index.php'));
    }

    public function testAChangedStampOrAMissingOneSignsTheSessionOut()
    {
        $two_factor = $this->twoFactor();

        $this->assertSame('invalid', $two_factor->sessionResult($this->state(), array('s_stored_hash' => 'the hash', 's_two_factor_stamp' => 'an old stamp'), '/domains/index.php'));
        // A session from before the upgrade has none, and every user has a stamp after it
        $this->assertSame('invalid', $two_factor->sessionResult($this->state(), array('s_stored_hash' => 'the hash'), '/domains/index.php'));
        // A missing value counts as '', the column's default
        $this->assertSame('valid', $two_factor->sessionResult($this->state(array('stamp' => '')), array('s_stored_hash' => 'the hash'), '/domains/index.php'));
        // The password is checked against the same read, so a change between two reads can't slip through
        $this->assertSame('invalid', $two_factor->sessionResult($this->state(array('password' => 'a new hash')),
            array('s_stored_hash' => 'the hash', 's_two_factor_stamp' => 'the stamp'), '/domains/index.php'));
        $this->assertSame('invalid', $two_factor->sessionResult(false, array('s_stored_hash' => 'the hash', 's_two_factor_stamp' => 'the stamp'), '/domains/index.php'));
    }

    public function testUnfinishedStepsOnlyOpenThePagesThatFinishThem()
    {
        $two_factor = $this->twoFactor();
        $state = $this->state(array('new_password' => '1'));
        $session = array('s_stored_hash' => 'the hash', 's_two_factor_stamp' => 'the stamp');

        $this->assertSame('steps_pending', $two_factor->sessionResult($state, $session, '/dashboard/index.php'));
        $this->assertSame('steps_pending', $two_factor->sessionResult($state, $session, '/domains/list-data.php'));
        // The Security page (settings/two-factor/) has the Password card, so it finishes the password step too
        foreach (array('/checks.php', '/notice.php', '/settings/two-factor/index.php') as $page) {
            $this->assertSame('valid', $two_factor->sessionResult($state, $session, $page));
        }
        // settings/password/ only forwards to the Security page now, so it isn't one of them
        $this->assertSame('steps_pending', $two_factor->sessionResult($state, $session, '/settings/password/index.php'));
    }

    public function testRequiredSetupRestrictsTheSessionAndAnUnverifiedOneIsSignedOut()
    {
        $two_factor = $this->twoFactor();
        $session = array('s_stored_hash' => 'the hash', 's_two_factor_stamp' => 'the stamp', 's_two_factor_verified' => 0);

        // No secret yet: setup is the step, so the session stays, restricted
        $this->assertSame('steps_pending', $two_factor->sessionResult($this->state(array('required' => '1')), $session, '/dashboard/index.php'));
        // A secret, but this session skipped the second step: signed out
        $on = $this->state(array('required' => '1', 'secret' => $two_factor->encodeSecret('12345678901234567890', 7)));
        $this->assertSame('invalid', $two_factor->sessionResult($on, $session, '/dashboard/index.php'));
        $this->assertSame('valid', $two_factor->sessionResult($on, array('s_two_factor_verified' => 1) + $session, '/dashboard/index.php'));
        // Turning the requirement off releases a session that was waiting on setup
        $this->assertSame('valid', $two_factor->sessionResult($this->state(), $session, '/dashboard/index.php'));
    }

    public function testBeforeTheUpgradeASessionThatKnewAboutTwoFactorIsInvalid()
    {
        $system = Instance::withoutConstructor(System::class);
        $result = new \ReflectionMethod(System::class, 'twoFactorResult');

        $this->assertSame('valid', $result->invoke($system, 7, '4.26.0'));
        // Such as after restoring a backup from before the upgrade
        $_SESSION['s_two_factor_stamp'] = 'a stamp';
        $this->assertSame('invalid', $result->invoke($system, 7, '4.26.0'));
        // A version that can't be read fails closed
        unset($_SESSION['s_two_factor_stamp']);
        $this->assertSame('invalid', $result->invoke($system, 7, ''));
    }

    public function testCompleteSignInKeepsTheStamp()
    {
        $login = new class extends Login {
            public function __construct()
            {
            }

            protected function getDbVersion()
            {
                return '4.27.0';
            }

            protected function regenerateSession()
            {
                return true;
            }
        };

        $login->completeSignIn(7, 'jo', 'the hash', array('stamp' => 'the stamp', 'verified' => 1));
        $this->assertSame('the stamp', $_SESSION['s_two_factor_stamp']);
        $this->assertSame(1, $_SESSION['s_two_factor_verified']);

        // Before the upgrade, a sign-in carries no two-factor keys
        $login->completeSignIn(7, 'jo', 'the hash');
        $this->assertArrayNotHasKey('s_two_factor_stamp', $_SESSION);
        $this->assertArrayNotHasKey('s_two_factor_verified', $_SESSION);
    }

    public function testRememberingADeviceStoresOnlyTheTokensHash()
    {
        $_SERVER['HTTPS'] = 'on';
        $db = new FakeWriteDatabase(array('SELECT id, two_factor_last_step' => array('id' => 7, 'last_step' => 0)));

        $saved = $this->twoFactor($db)->rememberDevice($this->context(), self::NOW);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', $saved['token']);
        $insert = $db->executedWith('INSERT INTO user_remembered_devices')[0];
        $this->assertSame(hash('sha256', $saved['token']), $insert['values']['token_hash']);
        $this->assertNotContains($saved['token'], $insert['values']);
        $this->assertSame(self::NOW + 30 * 86400, $insert['values']['expires']);
        $this->assertSame('the stamp', $insert['values']['stamp']);
        // Expired rows for the user go at the same time, in a transaction that locked and rechecked the account
        $this->assertStringContainsString('expires <= :now', $db->executedWith('DELETE FROM user_remembered_devices')[0]['sql']);
        $this->assertSame(array('BEGIN', 'COMMIT'), $db->transactions());
        $this->assertStringContainsString('two_factor_stamp = :ctx_stamp', $db->executedWith('FOR UPDATE')[0]['sql']);
    }

    public function testADeviceIsntRememberedIfTheAccountChangedOrTheSaveFails()
    {
        $_SERVER['HTTPS'] = 'on';
        $db = new FakeWriteDatabase(array('SELECT id, two_factor_last_step' => false));

        $this->assertFalse($this->twoFactor($db)->rememberDevice($this->context(), self::NOW)['token']);
        $this->assertSame(array(), $db->executedWith('INSERT'));

        // Such as a deadlock: the sign-in still finishes, without the cookie
        $db = new FakeWriteDatabase(array('SELECT id, two_factor_last_step' => array('id' => 7, 'last_step' => 0),
            'INSERT INTO user_remembered_devices' => new \PDOException('Deadlock')));
        $two_factor = $this->getMockBuilder(TwoFactor::class)->disableOriginalConstructor()->onlyMethods(array('logEvent'))->getMock();
        $two_factor->pdo = $db;
        $two_factor->time = new Time();
        $this->assertFalse($two_factor->rememberDevice($this->context(), self::NOW)['token']);
        $this->assertSame(array('BEGIN', 'ROLLBACK'), $db->transactions());
    }

    public function testNoDeviceIsRememberedOverPlainHttpOrOnTheDemo()
    {
        $db = new FakeWriteDatabase();

        $this->assertFalse($this->twoFactor($db)->rememberDevice($this->context(), self::NOW)['token']);
        $_SERVER['HTTPS'] = 'off';
        $this->assertFalse($this->twoFactor($db)->rememberDevice($this->context(), self::NOW)['token']);
        $this->assertSame(array(), $db->executedWith('INSERT'));
        $this->assertFalse(TwoFactor::canRememberDevices());
        $_SERVER['HTTPS'] = 'on';
        $this->assertTrue(TwoFactor::canRememberDevices());
    }

    public function testADeviceIsOnlyAcceptedForItsUserStampAndPassword()
    {
        $db = new FakeWriteDatabase(array('SELECT u.two_factor_stamp' => 'the stamp'));
        $token = str_repeat('ab', 32);

        $this->assertSame('the stamp', $this->twoFactor($db)->findDevice(7, 'the hash', $token, self::NOW));
        $lookup = $db->executedWith('FROM user_remembered_devices')[0];
        foreach (array('d.token_hash = :token_hash', 'd.user_id = :user_id', "u.active = '1'", 'u.`password` = :password_hash',
                     "u.two_factor_secret != ''", 'd.stamp = u.two_factor_stamp', 'd.expires > :now') as $condition) {
            $this->assertStringContainsString($condition, $lookup['sql']);
        }
        $this->assertSame(hash('sha256', $token), $lookup['values']['token_hash']);
        $this->assertSame(7, $lookup['values']['user_id']);

        // Not a token at all: no lookup
        $db = new FakeWriteDatabase(array('SELECT u.two_factor_stamp' => 'the stamp'));
        $this->assertFalse($this->twoFactor($db)->findDevice(7, 'the hash', 'not a token', self::NOW));
        $this->assertFalse($this->twoFactor($db)->findDevice(7, 'the hash', array($token), self::NOW));
        $this->assertSame(array(), $db->log);

        // No matching row
        $db = new FakeWriteDatabase(array('SELECT u.two_factor_stamp' => false));
        $this->assertFalse($this->twoFactor($db)->findDevice(7, 'the hash', $token, self::NOW));
    }

    public function testForgettingDevicesMatchesTheSession()
    {
        $db = new FakeWriteDatabase();

        $this->twoFactor($db)->forgetDevices(7, 'the hash', 'the stamp');

        $sql = $db->executedWith('DELETE d')[0]['sql'];
        foreach (array("u.active = '1'", 'u.`password` = :password_hash', 'u.two_factor_stamp = :stamp') as $condition) {
            $this->assertStringContainsString($condition, $sql);
        }
    }

    public function testAPasswordChangeForgetsDevicesInTheSameTransaction()
    {
        $two_factor = $this->getMockBuilder(TwoFactor::class)->disableOriginalConstructor()->onlyMethods(array('isAvailableNow'))->getMock();
        $two_factor->method('isAvailableNow')->willReturn(true);
        $db = new FakeWriteDatabase();
        $two_factor->pdo = $db;

        $this->assertTrue($two_factor->changePassword(7, function () use ($db) {
            $db->prepare('UPDATE users SET `password`')->execute();
            return true;
        }));
        $markers = array_map(function ($entry) {
            return is_string($entry) ? $entry : strtok(trim($entry['sql']), ' ');
        }, $db->log);
        $this->assertSame(array('BEGIN', 'UPDATE', 'DELETE', 'COMMIT'), $markers);
        $this->assertCount(1, $db->executedWith('DELETE FROM user_remembered_devices'));

        // If forgetting the devices fails, the new password isn't saved either
        $db = new class extends FakeWriteDatabase {
            public function execute($params = null)
            {
                if (strpos($this->sql, 'DELETE FROM user_remembered_devices') !== false) throw new \PDOException('Failed');
                return parent::execute($params);
            }
        };
        $two_factor->pdo = $db;
        try {
            $two_factor->changePassword(7, function () use ($db) {
                $db->prepare('UPDATE users SET `password`')->execute();
                return true;
            });
            $this->fail('The failure should reach the page');
        } catch (\PDOException $e) {
            $this->assertSame(array('BEGIN', 'ROLLBACK'), $db->transactions());
        }

        // A password write that changed nothing forgets nothing
        $db = new FakeWriteDatabase();
        $two_factor->pdo = $db;
        $this->assertFalse($two_factor->changePassword(7, function () {
            return false;
        }));
        $this->assertSame(array(), $db->executedWith('DELETE'));
    }

    public function testARestoreGivesEveryoneANewStamp()
    {
        $db = new FakeWriteDatabase(array('SELECT id' => array('7', '8')));

        $this->twoFactor($db)->newStampsForEveryone();

        $updates = $db->executedWith('SET two_factor_stamp = :new_stamp');
        $this->assertCount(2, $updates);
        $this->assertNotSame($updates[0]['values']['new_stamp'], $updates[1]['values']['new_stamp']);
        $this->assertSame(array('BEGIN', 'COMMIT'), $db->transactions());
    }

    public function testAPermissionIsTiedToTheAccountAndLastsTenMinutes()
    {
        $two_factor = $this->twoFactor();
        $two_factor->grantPermission('setup', 7, 'the hash', 'the stamp', self::NOW);

        $this->assertTrue($two_factor->hasPermission('setup', 7, 'the hash', 'the stamp', self::NOW + 599));
        $this->assertFalse($two_factor->hasPermission('setup', 7, 'the hash', 'the stamp', self::NOW + 600));
        $this->assertFalse($two_factor->hasPermission('regenerate', 7, 'the hash', 'the stamp', self::NOW));
        $this->assertFalse($two_factor->hasPermission('setup', 8, 'the hash', 'the stamp', self::NOW));
        $this->assertFalse($two_factor->hasPermission('setup', 7, 'a new hash', 'the stamp', self::NOW));
        $this->assertFalse($two_factor->hasPermission('setup', 7, 'the hash', 'a new stamp', self::NOW));

        $two_factor->usePermission('setup');
        $this->assertFalse($two_factor->hasPermission('setup', 7, 'the hash', 'the stamp', self::NOW));
    }

    public function testTheRecoveryPermissionIsTiedToItsStamp()
    {
        $two_factor = $this->twoFactor();
        $two_factor->grantRecoveryPermission(7, 'the stamp');

        $this->assertTrue($two_factor->hasRecoveryPermission(7, 'the stamp'));
        $this->assertFalse($two_factor->hasRecoveryPermission(7, 'a new stamp'));
        $this->assertFalse($two_factor->hasRecoveryPermission(8, 'the stamp'));
        $two_factor->useRecoveryPermission();
        $this->assertFalse($two_factor->hasRecoveryPermission(7, 'the stamp'));
    }

    public function testSettingsActionsFollowTheSignInSteps()
    {
        $two_factor = $this->twoFactor();
        $on = array('secret' => $two_factor->encodeSecret('12345678901234567890', 7));

        // Change Password comes first: nothing else until it's done
        $this->assertSame(array(), $two_factor->allowedActions($this->state(array('new_password' => '1') + $on), true, true));
        // Required setup, or a secret that can't be decrypted: only what that step needs
        $this->assertSame(array('password_setup', 'setup'), $two_factor->allowedActions($this->state(array('required' => '1')), true, false));
        $this->assertSame(array('password_replace', 'replace'), $this->twoFactor()->allowedActions($this->state(array('secret' => 'enc1:broken')), true, false));
        // Replacing comes before the codes, even with the newest codes still in the session
        $this->assertSame(array('password_replace', 'replace'), $this->twoFactor()->allowedActions($this->state(array('secret' => 'enc1:broken', 'codes_confirmed' => '0')), true, true));
        // Unconfirmed codes: confirming them (while the session still has them) or regenerating, never turning off
        $codes = $this->state(array('codes_confirmed' => '0') + $on);
        $this->assertSame(array('confirm_codes'), $two_factor->allowedActions($codes, true, true));
        $this->assertSame(array('regenerate'), $two_factor->allowedActions($codes, false, false));
        $this->assertSame(array('regenerate', 'password_replace', 'replace', 'password_regenerate', 'regenerate_recovery'),
            $two_factor->allowedActions($codes, true, false));
    }

    public function testSettingsActionsInAnOrdinarySession()
    {
        $two_factor = $this->twoFactor();
        $on = $this->state(array('secret' => $two_factor->encodeSecret('12345678901234567890', 7)));

        $this->assertSame(array('password_setup', 'setup'), $two_factor->allowedActions($this->state(), false, false));
        $this->assertSame(array('regenerate', 'forget_devices', 'turn_off'), $two_factor->allowedActions($on, false, false));
        // Replacing a working authenticator needs the recovery permission
        $this->assertContains('replace', $two_factor->allowedActions($on, true, false));
        // Turning off isn't offered while it's required
        $this->assertNotContains('turn_off', $two_factor->allowedActions(array('required' => '1') + $on, false, false));
    }

    // Rows for lockAdmin(): the admin (7) and another user (8), as a fake answers every row query with the same row
    protected function adminDatabase($admin, array $row_counts = array())
    {
        return new FakeWriteDatabase(array('FROM users' => $admin), $row_counts);
    }

    protected function adminRow(array $values = array())
    {
        return $values + array('id' => 7, 'username' => 'ada', 'email_address' => 'a@example.test', 'first_name' => 'Ada',
            'last_name' => 'Admin', 'password' => 'the hash', 'active' => '1', 'admin' => '1', 'read_only' => '0',
            'secret' => 'raw:X', 'stamp' => 'the stamp');
    }

    public function testAnAdminResetClearsTheUserAndChecksTheAdminAgain()
    {
        $db = $this->adminDatabase($this->adminRow());

        $two_factor = $this->twoFactor($db);
        $this->assertSame('own', $two_factor->adminReset(7, 'the hash', 'the stamp', 7));
        $this->assertSame(array(), $db->log);

        // A demoted, read-only, or changed admin is refused, and nothing changes
        foreach (array(array('admin' => '0'), array('read_only' => '1'), array('password' => 'a new hash'), array('stamp' => 'a new stamp'), array('active' => '0')) as $change) {
            $db = $this->adminDatabase($this->adminRow($change));
            $this->assertSame('refused', $this->twoFactor($db)->adminReset(7, 'the hash', 'the stamp', 8));
            $this->assertSame(array('BEGIN', 'ROLLBACK'), $db->transactions());
            $this->assertSame(array(), $db->executedWith('SET two_factor'));
        }
    }

    public function testAnAdminResetLocksSettingsThenUsersByIdAndClears()
    {
        $two_factor = new class extends TwoFactor {
            public function __construct()
            {
            }

            protected function lockAdmin($admin_id, $password_hash, $stamp, $other_id = null)
            {
                $this->pdo->query('SELECT two_factor_required FROM settings FOR UPDATE');
                return array(7 => array('id' => 7), 8 => array('id' => 8, 'username' => 'bo', 'secret' => 'raw:X'));
            }
        };
        $db = new FakeWriteDatabase();
        $two_factor->pdo = $db;
        $two_factor->time = new Time();

        $result = $two_factor->adminReset(7, 'the hash', 'the stamp', 8);

        $this->assertSame('bo', $result['username']);
        $this->assertSame(array('BEGIN', 'COMMIT'), $db->transactions());
        $clear = $db->executedWith("SET two_factor_secret = ''")[0];
        $this->assertSame(8, $clear['values']['user_id']);
        // A new random stamp, so the user's signed-in sessions end
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $clear['values']['new_stamp']);
        $this->assertCount(1, $db->executedWith('DELETE FROM user_recovery_codes'));
        $this->assertCount(1, $db->executedWith('DELETE FROM user_remembered_devices'));
    }

    public function testOnlyThePrimaryAdministratorCanResetTheirAccount()
    {
        $two_factor = new class extends TwoFactor {
            public $acting = 'ada';

            public function __construct()
            {
            }

            protected function lockAdmin($admin_id, $password_hash, $stamp, $other_id = null)
            {
                return array(7 => array('id' => 7, 'username' => $this->acting),
                    8 => array('id' => 8, 'username' => 'admin', 'secret' => 'raw:X'));
            }
        };
        $db = new FakeWriteDatabase();
        $two_factor->pdo = $db;
        $two_factor->time = new Time();

        $this->assertSame('primary', $two_factor->adminReset(7, 'the hash', 'the stamp', 8));
        $this->assertSame(array('BEGIN', 'ROLLBACK'), $db->transactions());
        $this->assertSame(array(), $db->executedWith('SET two_factor'));

        // The acting admin's username comes from the locked row, so a renamed session can't get around it
        $two_factor->acting = 'admin';
        $this->assertSame('admin', $two_factor->adminReset(7, 'the hash', 'the stamp', 8)['username']);
    }

    public function testLockAdminLocksTheRowsInIdOrder()
    {
        $db = $this->adminDatabase($this->adminRow());
        $lock = new \ReflectionMethod(TwoFactor::class, 'lockAdmin');

        $lock->invoke($this->twoFactor($db), 9, 'the hash', 'the stamp', 4);

        $locks = $db->executedWith('FOR UPDATE');
        $this->assertStringContainsString('FROM settings', $locks[0]['sql']);
        $this->assertSame(4, $locks[1]['values']['user_id']);
        $this->assertSame(9, $locks[2]['values']['user_id']);
    }

    public function testRequireNeedsTheAdminToHaveItOnAndEndsSessionsThatSkipped()
    {
        $db = new FakeWriteDatabase(array('SELECT id, username' => $this->adminRow(array('secret' => '')), 'WHERE two_factor_secret' => array('8', '9')));
        $this->assertSame('not_on', $this->twoFactor($db)->setRequired(7, 'the hash', 'the stamp', true));
        $this->assertSame(array('BEGIN', 'ROLLBACK'), $db->transactions());

        // Turning it off needs no secret
        $db = new FakeWriteDatabase(array('SELECT id, username' => $this->adminRow(array('secret' => ''))));
        $this->assertSame('ok', $this->twoFactor($db)->setRequired(7, 'the hash', 'the stamp', false));
        $this->assertSame(array(), $db->executedWith('SET two_factor_stamp'));

        // Turning it on gives everyone without it a new stamp
        $db = new FakeWriteDatabase(array('SELECT id, username' => $this->adminRow(), "WHERE two_factor_secret = ''" => array('8', '9')));
        $this->assertSame('ok', $this->twoFactor($db)->setRequired(7, 'the hash', 'the stamp', true));
        $this->assertSame(1, $db->executedWith('SET two_factor_required')[0]['values']['required']);
        $stamps = $db->executedWith('SET two_factor_stamp = :new_stamp');
        $this->assertSame(array(8, 9), array_column(array_column($stamps, 'values'), 'user_id'));
        $new_stamps = array_column(array_column($stamps, 'values'), 'new_stamp');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $new_stamps[0]);
        $this->assertNotSame($new_stamps[0], $new_stamps[1]);
        $this->assertSame(array('BEGIN', 'COMMIT'), $db->transactions());

        // A demoted admin is refused
        $db = new FakeWriteDatabase(array('SELECT id, username' => $this->adminRow(array('admin' => '0'))));
        $this->assertSame('refused', $this->twoFactor($db)->setRequired(7, 'the hash', 'the stamp', false));
    }

} //@formatter:on
