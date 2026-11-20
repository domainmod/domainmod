<?php
/**
 * /tests/Unit/TwoFactorTest.php
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

use DomainMOD\RecoveryCode;
use DomainMOD\Tests\Support\FakeWriteDatabase;
use DomainMOD\Tests\Support\Instance;
use DomainMOD\Time;
use DomainMOD\Totp;
use DomainMOD\TwoFactor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class TwoFactorTest extends TestCase
{
    const SECRET = '12345678901234567890';
    const NOW = 1700000000;

    protected function twoFactor($secret_key, array $properties = array())
    {
        return Instance::withoutConstructor(TwoFactor::class, array('secret_key' => $secret_key) + $properties);
    }

    // $secret_key in config.inc.php reaches TwoFactor through software.inc.php
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testTheConfigKeyIsUsed()
    {
        $web_root = $dbhostname = $dbname = $dbusername = $dbpassword = '';
        $secret_key = ' a long random key ';
        require DIR_INC . '/software.inc.php';

        $this->assertSame('a long random key', TwoFactor::configuredKey());
    }

    public function testWithAKeyTheSecretIsEncryptedAndReadBack()
    {
        $two_factor = $this->twoFactor('a long random key');
        $stored = $two_factor->encodeSecret(self::SECRET, 7);

        $this->assertStringStartsWith('enc1:', $stored);
        $this->assertStringNotContainsString((new Totp())->base32Encode(self::SECRET), $stored);
        $this->assertTrue($two_factor->isEncrypted($stored));
        $this->assertSame(self::SECRET, $two_factor->decodeSecret($stored, 7));
        // A new nonce each time
        $this->assertNotSame($stored, $two_factor->encodeSecret(self::SECRET, 7));
    }

    // A secret encrypted by this version, so a change to how keys or user ids are used can't lock out existing users
    public function testASavedSecretStillDecrypts()
    {
        $stored = 'enc1:BFUHrlAQ/OLdT8f+yw7wcuozcpotZkNszn71fDhBabqZJ4PCRckrRZBNt9yqjsI3';

        $this->assertSame(self::SECRET, $this->twoFactor('a long random key')->decodeSecret($stored, 7));
    }

    public function testAnEncryptedSecretNeedsTheSameKeyAndUser()
    {
        $stored = $this->twoFactor('a long random key')->encodeSecret(self::SECRET, 7);

        $this->assertFalse($this->twoFactor('another key')->decodeSecret($stored, 7));
        $this->assertFalse($this->twoFactor('')->decodeSecret($stored, 7));
        // Copied to another user's row, it doesn't decrypt
        $this->assertFalse($this->twoFactor('a long random key')->decodeSecret($stored, 8));
    }

    public function testATamperedSecretDoesntDecrypt()
    {
        $two_factor = $this->twoFactor('a long random key');
        $data = base64_decode(substr($two_factor->encodeSecret(self::SECRET, 7), 5));
        $data[14] = chr(ord($data[14]) ^ 1);

        $this->assertFalse($two_factor->decodeSecret('enc1:' . base64_encode($data), 7));
        $this->assertFalse($two_factor->decodeSecret('enc1:not base64!', 7));
        $this->assertFalse($two_factor->decodeSecret('enc1:' . base64_encode('short'), 7));
    }

    public function testWithoutAKeyTheSecretIsStoredRaw()
    {
        $two_factor = $this->twoFactor('');
        $stored = $two_factor->encodeSecret(self::SECRET, 7);

        $this->assertSame('raw:' . (new Totp())->base32Encode(self::SECRET), $stored);
        $this->assertFalse($two_factor->isEncrypted($stored));
        $this->assertFalse($two_factor->needsEncrypting($stored));
        $this->assertSame(self::SECRET, $two_factor->decodeSecret($stored, 7));
        // A raw secret isn't tied to the key, and is saved again encrypted once a key is set
        $this->assertSame(self::SECRET, $this->twoFactor('a key')->decodeSecret($stored, 7));
        $this->assertTrue($this->twoFactor('a key')->needsEncrypting($stored));
    }

    public function testUnreadableValuesGiveFalse()
    {
        $two_factor = $this->twoFactor('a key');

        $this->assertFalse($two_factor->decodeSecret('', 7));
        $this->assertFalse($two_factor->decodeSecret('raw:', 7));
        $this->assertFalse($two_factor->decodeSecret('raw:not base32!', 7));
        $this->assertFalse($two_factor->decodeSecret('MZXW6YTBOI', 7));
        $this->assertFalse($two_factor->decodeSecret(null, 7));
        // Damaged: not the 20 bytes every secret has
        $this->assertFalse($two_factor->decodeSecret('raw:MZXW6YTBOI', 7));
        $this->assertFalse($two_factor->decodeSecret($two_factor->encodeSecret('foobar', 7), 7));
    }

    public function testIsAvailableFromTheDatabaseVersion()
    {
        $this->assertFalse(TwoFactor::isAvailable('4.26.0'));
        $this->assertFalse(TwoFactor::isAvailable(''));
        $this->assertFalse(TwoFactor::isAvailable(false));
        $this->assertTrue(TwoFactor::isAvailable('4.27.0'));
        $this->assertTrue(TwoFactor::isAvailable('4.27.01'));
        $this->assertTrue(TwoFactor::isAvailable('5.0.0'));
    }

    public static function unreadableVersions()
    {
        return array('missing' => array(false), 'blank' => array(''), 'not a version' => array('x'),
            'incomplete' => array('4.27'), 'too long' => array('4.27.0.1'));
    }

    #[DataProvider('unreadableVersions')]
    public function testIsAvailableNowFailsClosedWhenTheVersionCantBeRead($db_version)
    {
        $two_factor = $this->getMockBuilder(TwoFactor::class)->disableOriginalConstructor()->onlyMethods(array('getDbVersion'))->getMock();
        $two_factor->method('getDbVersion')->willReturn($db_version);

        $this->expectException(\RuntimeException::class);
        $two_factor->isAvailableNow();
    }

    public function testIsAvailableNowReadsTheLiveVersion()
    {
        $two_factor = $this->getMockBuilder(TwoFactor::class)->disableOriginalConstructor()->onlyMethods(array('getDbVersion'))->getMock();
        $two_factor->method('getDbVersion')->willReturnOnConsecutiveCalls('4.27.0', '4.26.0', '2.0038');

        $this->assertTrue($two_factor->isAvailableNow());
        $this->assertFalse($two_factor->isAvailableNow());
        // Versions from before 4.0 had other shapes
        $this->assertFalse($two_factor->isAvailableNow());
    }

    public function testTheStampIsRandom()
    {
        $two_factor = $this->twoFactor('');
        $stamp = $two_factor->newStamp();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $stamp);
        $this->assertNotSame($stamp, $two_factor->newStamp());
    }

    public function testPendingStepsInTheirOrder()
    {
        $two_factor = $this->twoFactor('a key');
        $on = $this->state(array('secret' => $two_factor->encodeSecret(self::SECRET, 7)));

        $this->assertSame(array(), $two_factor->pendingSteps($this->state()));
        $this->assertSame(array('password', 'setup'), $two_factor->pendingSteps($this->state(array('new_password' => '1', 'required' => '1'))));
        $this->assertSame(array(), $two_factor->pendingSteps($on));
        $this->assertSame(array('codes'), $two_factor->pendingSteps(array('codes_confirmed' => '0') + $on));
        // codes_confirmed is 0 for everyone without two-factor sign-in, so it only counts with a secret
        $this->assertSame(array(), $two_factor->pendingSteps($this->state(array('codes_confirmed' => '0'))));
        // A secret that can't be decrypted (the key was lost) has to be replaced
        $this->assertSame(array('replace'), $this->twoFactor('')->pendingSteps($on));
    }

    public function testAWrongCodeSetsTheLockBeforeRaisingTheCount()
    {
        $db = new FakeWriteDatabase(array('SELECT two_factor_locked_until' => 0));
        $result = $this->withDatabase($db)->recordFailure($this->context(), self::NOW);

        $this->assertSame('wrong', $result);
        // The update and the read of the lock are one transaction, so only the code that set the lock reports it
        $this->assertSame(array('BEGIN', 'COMMIT'), $db->transactions());
        $begin = array_search('BEGIN', $db->log, true);
        $commit = array_search('COMMIT', $db->log, true);
        foreach (array('two_factor_failures + 1', 'SELECT two_factor_locked_until') as $text) {
            $position = array_search($db->executedWith($text)[0], $db->log, true);
            $this->assertGreaterThan($begin, $position);
            $this->assertLessThan($commit, $position);
        }
        $update = $db->executedWith('two_factor_failures + 1')[0];
        // MariaDB and MySQL apply the assignments in order, so the lock has to read the count before it's raised
        $this->assertLessThan(strpos($update['sql'], 'two_factor_failures = IF'), strpos($update['sql'], 'two_factor_locked_until = IF'));
        // The tenth wrong code (nine before it) sets the lock and starts the count again
        $this->assertStringContainsString('two_factor_locked_until = IF(two_factor_failures + 1 >= :max_failures_lock, :locked_until, two_factor_locked_until)', $update['sql']);
        $this->assertStringContainsString('two_factor_failures = IF(two_factor_failures + 1 >= :max_failures_count, 0, two_factor_failures + 1)', $update['sql']);
        $this->assertSame(10, $update['values']['max_failures_lock']);
        $this->assertSame(10, $update['values']['max_failures_count']);
        $this->assertSame(self::NOW + 900, $update['values']['locked_until']);
        $this->assertContextConditions($update, false);
    }

    public function testTheWrongCodeThatSetsTheLockSaysSo()
    {
        $db = new FakeWriteDatabase(array('SELECT two_factor_locked_until' => self::NOW + 900));

        $this->assertSame('lockout', $this->withDatabase($db)->recordFailure($this->context(), self::NOW));
    }

    public function testAWrongCodeAgainstAnAccountThatChangedIsntCharged()
    {
        $db = new FakeWriteDatabase(array(), array('two_factor_failures + 1' => 0));

        $this->assertSame('expired', $this->withDatabase($db)->recordFailure($this->context(), self::NOW));
    }

    public function testARightAuthenticatorCodeIsUsedOnce()
    {
        $two_factor = $this->twoFactor('a key');
        $context = $this->context(array('secret' => $two_factor->encodeSecret(self::SECRET, 7)));
        $db = $this->codeDatabase();
        $step = (new Totp())->step(self::NOW);

        $result = $this->withDatabase($db, $two_factor)->useCode($context, $this->code(), self::NOW);

        $this->assertSame('ok', $result['result']);
        $this->assertSame('authenticator', $result['type']);
        $update = $db->executedWith('two_factor_last_step < :minimum_step')[0];
        // The same step is bound to both placeholders, since emulated prepares are off and a name can't be used twice
        $this->assertSame($step, $update['values']['new_step']);
        $this->assertSame($step, $update['values']['minimum_step']);
        $this->assertContextConditions($update, false);
        $this->assertSame(array('BEGIN', 'COMMIT'), $db->transactions());
    }

    public function testAnAuthenticatorCodeUsedByAnotherRequestFirstIsRefused()
    {
        $two_factor = $this->twoFactor('a key');
        $context = $this->context(array('secret' => $two_factor->encodeSecret(self::SECRET, 7)));
        $db = $this->codeDatabase(array('two_factor_last_step < :minimum_step' => 0));

        $result = $this->withDatabase($db, $two_factor)->useCode($context, $this->code(), self::NOW);

        $this->assertSame('expired', $result['result']);
        $this->assertSame(array('BEGIN', 'ROLLBACK'), $db->transactions());
    }

    public function testTheCodeIsCheckedAgainAgainstTheLockedRow()
    {
        $two_factor = $this->twoFactor('a key');
        $context = $this->context(array('secret' => $two_factor->encodeSecret(self::SECRET, 7)));
        // Another request used this step after the account was read, before the row was locked
        $db = $this->codeDatabase(array(), array('SELECT id, two_factor_last_step' => array('id' => 7, 'last_step' => (new Totp())->step(self::NOW))));

        $result = $this->withDatabase($db, $two_factor)->useCode($context, $this->code(), self::NOW);

        $this->assertSame('expired', $result['result']);
        $this->assertSame(array(), $db->executedWith('two_factor_last_step < :minimum_step'));
        $this->assertSame(array('BEGIN', 'ROLLBACK'), $db->transactions());
    }

    public function testWrongCodesAndTheLockoutAreLogged()
    {
        $two_factor = $this->loggingTwoFactor();
        $context = $this->context(array('secret' => $two_factor->encodeSecret(self::SECRET, 7)));
        $wrong = $this->code() === '000000' ? '111111' : '000000';

        $this->withDatabase($this->codeDatabase(), $two_factor)->useCode($context, $wrong, self::NOW);
        $this->withDatabase($this->codeDatabase(), $two_factor)->useCode($context, 'not a code', self::NOW);
        // The wrong code that sets the lock (as at setup, which charges a wrong code directly)
        $this->withDatabase(new FakeWriteDatabase(array('SELECT two_factor_locked_until' => self::NOW + 900)), $two_factor)
            ->recordFailure($context, self::NOW);
        $this->withDatabase($this->codeDatabase(), $two_factor)->useCode($context, 'abcd-2345-efgh-6723', self::NOW);

        $this->assertSame(array('Wrong MFA code', 'Wrong MFA code', 'Wrong MFA code',
            'MFA code step locked after wrong codes', 'Recovery code used'), $two_factor->events);
    }

    public function testAWrongAuthenticatorCodeIsCharged()
    {
        $two_factor = $this->twoFactor('a key');
        $context = $this->context(array('secret' => $two_factor->encodeSecret(self::SECRET, 7)));
        $db = $this->codeDatabase();
        $wrong = $this->code() === '000000' ? '111111' : '000000';

        $result = $this->withDatabase($db, $two_factor)->useCode($context, $wrong, self::NOW);

        $this->assertSame('wrong', $result['result']);
        $this->assertCount(1, $db->executedWith('two_factor_failures + 1'));
        // Only the charge's own transaction, as no code was used
        $this->assertSame(array('BEGIN', 'COMMIT'), $db->transactions());
    }

    public function testACodeIsntCheckedWhileLocked()
    {
        $two_factor = $this->twoFactor('a key');
        $context = $this->context(array('secret' => $two_factor->encodeSecret(self::SECRET, 7)));
        $db = $this->codeDatabase(array(), array('SELECT two_factor_locked_until' => self::NOW + 60));

        $result = $this->withDatabase($db, $two_factor)->useCode($context, $this->code(), self::NOW);

        $this->assertSame('locked', $result['result']);
        $this->assertSame(array(), $db->executedWith('UPDATE'));
    }

    public function testASecretThatCantBeDecryptedIsntChargedForAnAuthenticatorCode()
    {
        $context = $this->context(array('secret' => $this->twoFactor('the old key')->encodeSecret(self::SECRET, 7)));
        $db = $this->codeDatabase();

        $result = $this->withDatabase($db, $this->twoFactor('a new key'))->useCode($context, $this->code(), self::NOW);

        $this->assertSame('unreadable', $result['result']);
        $this->assertSame(array(), $db->executedWith('UPDATE'));
    }

    public function testARawSecretIsSavedEncryptedOnceAKeyIsSet()
    {
        $two_factor = $this->twoFactor('a key');
        $raw = $this->twoFactor('')->encodeSecret(self::SECRET, 7);
        $db = $this->codeDatabase();

        $this->withDatabase($db, $two_factor)->useCode($this->context(array('secret' => $raw)), $this->code(), self::NOW);

        $update = $db->executedWith('two_factor_last_step < :minimum_step')[0];
        $this->assertStringStartsWith('enc1:', $update['values']['new_secret']);
        $this->assertSame(self::SECRET, $two_factor->decodeSecret($update['values']['new_secret'], 7));
        // Matched on the raw secret it replaces
        $this->assertSame($raw, $update['values']['ctx_secret']);
    }

    public function testSettingsWritesNeedAPasswordThatIsntTemporaryButTheChallengeDoesnt()
    {
        $two_factor = $this->twoFactor('a key');
        $secret = $two_factor->encodeSecret(self::SECRET, 7);
        $wrong = $this->code() === '000000' ? '111111' : '000000';

        foreach (array(true, false) as $settings) {

            $db = $this->codeDatabase();
            $this->withDatabase($db, $two_factor)->useCode($this->context(array('secret' => $secret), $settings), $this->code(), self::NOW);
            $this->withDatabase($db, $two_factor)->useCode($this->context(array('secret' => $secret), $settings), $wrong, self::NOW);

            $this->assertContextConditions($db->executedWith('two_factor_last_step < :minimum_step')[0], $settings);
            $this->assertContextConditions($db->executedWith('two_factor_failures + 1')[0], $settings);

        }
    }

    public function testARightRecoveryCodeIsUsedOnceInATransaction()
    {
        $db = $this->codeDatabase();

        $result = $this->withDatabase($db)->useCode($this->context(), 'abcd-2345-efgh-6723', self::NOW);

        $this->assertSame('ok', $result['result']);
        $this->assertSame('recovery', $result['type']);
        $use = $db->executedWith('UPDATE user_recovery_codes')[0];
        $this->assertStringContainsString('user_id = :user_id', $use['sql']);
        $this->assertStringContainsString('code_hash = :code_hash', $use['sql']);
        $this->assertStringContainsString('used_time = :never', $use['sql']);
        $this->assertSame(7, $use['values']['user_id']);
        $this->assertSame(hash('sha256', 'ABCD2345EFGH6723'), $use['values']['code_hash']);
        $this->assertSame(TwoFactor::NEVER, $use['values']['never']);
        $this->assertSame(array('BEGIN', 'COMMIT'), $db->transactions());
        // The row is locked and rechecked against the whole context before the code is used
        $lock = $db->executedWith('FOR UPDATE')[0];
        $order = array(array_search('BEGIN', $db->log, true), array_search($lock, $db->log, true),
            array_search($use, $db->log, true), array_search('COMMIT', $db->log, true));
        $sorted = $order;
        sort($sorted);
        $this->assertSame($sorted, $order);
        $this->assertContextConditions($lock, false);
    }

    public function testSomethingThatIsntACodeIsChargedWithoutATransactionForIt()
    {
        $db = $this->codeDatabase();

        $result = $this->withDatabase($db)->useCode($this->context(), 'not a code', self::NOW);

        $this->assertSame('wrong', $result['result']);
        $this->assertSame(array(), $db->executedWith('UPDATE user_recovery_codes'));
        $this->assertCount(1, $db->executedWith('two_factor_failures + 1'));
    }

    public function testAWrongRecoveryCodeIsRolledBackThenCharged()
    {
        $db = $this->codeDatabase(array('UPDATE user_recovery_codes' => 0));

        $result = $this->withDatabase($db)->useCode($this->context(), 'abcd-2345-efgh-6723', self::NOW);

        $this->assertSame('wrong', $result['result']);
        $this->assertSame(array('BEGIN', 'ROLLBACK', 'BEGIN', 'COMMIT'), $db->transactions());
        $this->assertCount(1, $db->executedWith('two_factor_failures + 1'));
        // The charge comes after the rollback, so it stays
        $this->assertGreaterThan(array_search('ROLLBACK', $db->log, true), array_search($db->executedWith('two_factor_failures + 1')[0], $db->log, true));
    }

    public function testARecoveryCodeStillWorksIfTheChangeItAuthorisedFails()
    {
        $db = $this->codeDatabase();

        try {
            $this->withDatabase($db)->useCode($this->context(), 'abcd-2345-efgh-6723', self::NOW, true, function () {
                throw new \RuntimeException('Saving failed');
            });
            $this->fail('The exception should reach the caller');
        } catch (\RuntimeException $e) {
            $this->assertSame('Saving failed', $e->getMessage());
        }

        $this->assertSame(array('BEGIN', 'ROLLBACK'), $db->transactions());
        // With $lock_settings, the settings row is locked before the user's
        $this->assertStringContainsString('FROM settings', $db->executedWith('FOR UPDATE')[0]['sql']);
        $this->assertStringContainsString('FROM users', $db->executedWith('FOR UPDATE')[1]['sql']);
    }

    public function testARefusalFromTheChangeRollsBackAndIsTheResult()
    {
        $db = $this->codeDatabase();

        $result = $this->withDatabase($db)->useCode($this->context(), 'abcd-2345-efgh-6723', self::NOW, true, function () {
            return 'required';
        });

        $this->assertSame('required', $result['result']);
        $this->assertSame(array('BEGIN', 'ROLLBACK'), $db->transactions());

        // Whatever the refusal is called, the code was right, so nothing is charged
        foreach (array('wrong', 'no_such_code') as $refusal) {

            $db = $this->codeDatabase();
            $result = $this->withDatabase($db)->useCode($this->context(), 'abcd-2345-efgh-6723', self::NOW, true, function () use ($refusal) {
                return $refusal;
            });

            $this->assertSame($refusal, $result['result']);
            $this->assertSame(array('BEGIN', 'ROLLBACK'), $db->transactions());
            $this->assertSame(array(), $db->executedWith('two_factor_failures + 1'));

        }
        // And a change that gives back anything but true or an array (such as false from a failed save) isn't saved
        $db = $this->codeDatabase();
        $result = $this->withDatabase($db)->useCode($this->context(), 'abcd-2345-efgh-6723', self::NOW, true, function () {
            return false;
        });

        $this->assertSame('failed', $result['result']);
        $this->assertSame(array('BEGIN', 'ROLLBACK'), $db->transactions());

        // 'ok' only ever means the change was saved
        $db = $this->codeDatabase();
        $result = $this->withDatabase($db)->useCode($this->context(), 'abcd-2345-efgh-6723', self::NOW, true, function () {
            return 'ok';
        });

        $this->assertSame('failed', $result['result']);
        $this->assertSame(array('BEGIN', 'ROLLBACK'), $db->transactions());
    }

    public function testAnAccountThatChangedBeforeTheLockChangesNothing()
    {
        $db = $this->codeDatabase(array(), array('SELECT id, two_factor_last_step' => false));

        $result = $this->withDatabase($db)->useCode($this->context(), 'abcd-2345-efgh-6723', self::NOW);

        $this->assertSame('expired', $result['result']);
        $this->assertSame(array(), $db->executedWith('UPDATE user_recovery_codes'));
        $this->assertSame(array('BEGIN', 'ROLLBACK'), $db->transactions());
    }

    public function testSavingASecretReplacesTheCodesStampAndDevicesTogether()
    {
        $two_factor = $this->twoFactor('a key');
        $db = $this->codeDatabase();

        $saved = $this->withDatabase($db, $two_factor)->saveSecret($this->context(array(), true), self::SECRET, 1234, self::NOW);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $saved['stamp']);
        $this->assertCount(10, $saved['codes']);
        $update = $db->executedWith('SET two_factor_secret = :new_secret')[0];
        $this->assertSame($saved['stamp'], $update['values']['new_stamp']);
        $this->assertSame(1234, $update['values']['new_step']);
        $this->assertSame(self::SECRET, $two_factor->decodeSecret($update['values']['new_secret'], 7));
        // Matches the secret being replaced ('' for a first setup), so two setups can't overwrite each other
        $this->assertSame('', $update['values']['ctx_secret']);
        $this->assertContextConditions($update, true);
        $this->assertCount(1, $db->executedWith('DELETE FROM user_recovery_codes'));
        $inserts = $db->executedWith('INSERT INTO user_recovery_codes');
        $this->assertCount(10, $inserts);
        $recovery = new RecoveryCode();
        $this->assertSame($recovery->hash($saved['codes'][0]), $inserts[0]['values']['code_hash']);
        $this->assertCount(1, $db->executedWith('DELETE FROM user_remembered_devices'));
        $this->assertSame(array('BEGIN', 'COMMIT'), $db->transactions());
    }

    public function testASetupThatLostTheRaceSavesNothing()
    {
        $db = $this->codeDatabase(array('SET two_factor_secret = :new_secret' => 0));

        $result = $this->withDatabase($db, $this->twoFactor('a key'))->saveSecret($this->context(array(), true), self::SECRET, 1234, self::NOW);

        $this->assertSame('expired', $result);
        $this->assertSame(array(), $db->executedWith('INSERT INTO user_recovery_codes'));
        $this->assertSame(array('BEGIN', 'ROLLBACK'), $db->transactions());
    }

    public function testConfirmingCodesMatchesTheSetShown()
    {
        $db = new FakeWriteDatabase(array(), array('two_factor_codes_confirmed = \'1\'' => 0));

        $this->assertFalse($this->withDatabase($db)->confirmCodes(7, 'hash', 'stamp'));
        $sql = $db->executedWith('two_factor_codes_confirmed')[0]['sql'];
        foreach (array("active = '1'", '`password` = :password_hash', 'two_factor_stamp = :stamp', "new_password = '0'") as $condition) {
            $this->assertStringContainsString($condition, $sql);
        }
    }

    // TwoFactor with its log entries kept in $events instead of written
    protected function loggingTwoFactor()
    {
        $two_factor = new class extends TwoFactor {
            public $events = array();

            public function __construct()
            {
            }

            protected function logEvent($level, $message, $user_id)
            {
                $this->events[] = $message;
            }
        };
        $two_factor->secret_key = 'a key';
        return $two_factor;
    }

    protected function withDatabase($db, $two_factor = null)
    {
        $two_factor = $two_factor ?? $this->twoFactor('a key');
        $two_factor->pdo = $db;
        $two_factor->totp = new Totp();
        $two_factor->recovery = new RecoveryCode();
        $two_factor->time = new Time();
        return $two_factor;
    }

    // Answers for a code check: not locked, and the account still matches when its row is locked
    protected function codeDatabase(array $row_counts = array(), array $answers = array())
    {
        return new FakeWriteDatabase($answers + array(
            'SELECT two_factor_locked_until' => 0,
            'SELECT id, two_factor_last_step' => array('id' => 7, 'last_step' => 0)), $row_counts);
    }

    protected function state(array $values = array())
    {
        return $values + array('id' => 7, 'secret' => '', 'new_password' => '0', 'required' => '0',
            'codes_confirmed' => '1', 'stamp' => 'a stamp', 'last_step' => 0);
    }

    protected function context(array $values = array(), $settings = false)
    {
        return $values + array('user_id' => 7, 'password_hash' => 'the hash', 'stamp' => 'a stamp', 'secret' => '',
            'last_step' => 0, 'settings' => $settings);
    }

    protected function code()
    {
        $totp = new Totp();
        return $totp->code(self::SECRET, $totp->step(self::NOW));
    }

    // Every write after a checked code matches the state captured for the attempt, plus new_password for Settings
    protected function assertContextConditions($statement, $settings)
    {
        foreach (array('id = :ctx_user_id', "active = '1'", '`password` = :ctx_password_hash', 'two_factor_stamp = :ctx_stamp',
                     'two_factor_secret = :ctx_secret', 'two_factor_locked_until <= :ctx_now') as $condition) {
            $this->assertStringContainsString($condition, $statement['sql']);
        }
        $this->assertSame('the hash', $statement['values']['ctx_password_hash']);
        $this->assertSame(self::NOW, $statement['values']['ctx_now']);
        if ($settings) {
            $this->assertStringContainsString("new_password = '0'", $statement['sql']);
        } else {
            $this->assertStringNotContainsString("new_password = '0'", $statement['sql']);
        }
    }

} //@formatter:on
