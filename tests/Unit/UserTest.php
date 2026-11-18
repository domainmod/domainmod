<?php
/**
 * /tests/Unit/UserTest.php
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

use DomainMOD\Tests\Support\Instance;
use DomainMOD\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    private function user()
    {
        return Instance::withoutConstructor(User::class);
    }

    public static function lengths()
    {
        return array(
            'seven characters' => array('abcdefg', true),
            'eight characters' => array('abcdefgh', false),
            'seven two-byte characters (14 bytes)' => array(str_repeat('é', 7), true),
            'eight two-byte characters' => array(str_repeat('é', 8), false),
            'seven four-byte characters (28 bytes)' => array(str_repeat("\u{1F600}", 7), true),
            'empty' => array('', true),
            'null' => array(null, true),
        );
    }

    #[DataProvider('lengths')]
    public function testTheMinimumIsCountedInCharacters($password, $too_short)
    {
        $this->assertSame($too_short, $this->user()->passwordTooShort($password));
    }

    public static function sizes()
    {
        return array(
            '72 bytes' => array(str_repeat('a', 72), false),
            '73 bytes' => array(str_repeat('a', 73), true),
            '36 two-byte characters (72 bytes)' => array(str_repeat('é', 36), false),
            '37 two-byte characters (74 bytes)' => array(str_repeat('é', 37), true),
            '19 four-byte characters (76 bytes)' => array(str_repeat("\u{1F600}", 19), true),
        );
    }

    #[DataProvider('sizes')]
    public function testTheMaximumIsCountedInBytes($password, $too_long)
    {
        $this->assertSame($too_long, $this->user()->passwordTooLong($password));
    }

    public function testANulByteIsRefused()
    {
        $this->assertTrue($this->user()->passwordHasUnusableCharacter("pass\0word"));
        $this->assertTrue($this->user()->passwordHasUnusableCharacter("\0"));
        $this->assertFalse($this->user()->passwordHasUnusableCharacter('password with spaces'));
    }

    public function testTheMessagesNameTheLimits()
    {
        $user = $this->user();

        $this->assertSame(8, User::MIN_PASSWORD_LENGTH);
        $this->assertSame(72, User::MAX_PASSWORD_BYTES);
        $this->assertSame('The password must be at least 8 characters long', $user->passwordTooShortMessage());
        $this->assertSame('Use at least 8 characters.', $user->passwordLengthHint());
        $this->assertSame('The password is too long', $user->passwordTooLongMessage());
        $this->assertSame("The password contains a character that can't be used", $user->passwordUnusableMessage());
    }

    public function testGenerateHashMakesAModernHashThatVerifies()
    {
        $hash = $this->user()->generateHash('correct horse');

        $this->assertTrue(password_verify('correct horse', $hash));
        $this->assertFalse(password_verify('wrong horse', $hash));
        $this->assertStringStartsWith('$2y$', $hash);
        $this->assertNotSame($hash, $this->user()->generateHash('correct horse'));
    }

    public function testGenerateHashTakesTheLongestAllowedPassword()
    {
        $password = str_repeat('é', 36);

        $this->assertTrue(password_verify($password, $this->user()->generateHash($password)));
    }

    public function testGenerateLegacyHashMatchesMysqlPassword()
    {
        // MySQL 5.7's SELECT PASSWORD('password')
        $this->assertSame('*2470C0C06DEE42FD1618BB99005ADCA2EC9D1E19', $this->user()->generateLegacyHash('password'));
    }

    public function testTheResetTokenChangesWithTheStoredHash()
    {
        $user = $this->user();
        $token = $user->getResetToken(3, 1800000000, '$2y$10$first');

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertSame($token, $user->getResetToken(3, 1800000000, '$2y$10$first'));
        // HMAC-SHA256 of 'password-reset|3|1800000000', keyed with the stored hash
        $this->assertSame('30f68e472d8c8c00517c3c12a3edec256d40e2c30f2ec96778dcf18b5e70d4ad', $token);
        $this->assertNotSame($token, $user->getResetToken(3, 1800000000, '$2y$10$second'));
        $this->assertNotSame($token, $user->getResetToken(4, 1800000000, '$2y$10$first'));
        $this->assertNotSame($token, $user->getResetToken(3, 1800000001, '$2y$10$first'));
    }

    public function testGeneratePasswordGivesTheAskedLengthFromLettersAndDigits()
    {
        $user = $this->user();

        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{72}$/', $user->generatePassword());
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9]{12}$/', $user->generatePassword(12));
        $this->assertSame('', $user->generatePassword(0));
        $this->assertNotSame($user->generatePassword(), $user->generatePassword());
    }

} //@formatter:on
