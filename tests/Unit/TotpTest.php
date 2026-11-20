<?php
/**
 * /tests/Unit/TotpTest.php
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

use DomainMOD\Totp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// Two-factor sign-in codes, checked against the published test vectors (RFC 4226, RFC 6238, and RFC 4648)
class TotpTest extends TestCase
{
    const RFC_SECRET = '12345678901234567890';

    public static function hotpVectors()
    {
        $codes = array('755224', '287082', '359152', '969429', '338314', '254676', '287922', '162583', '399871',
            '520489');
        $vectors = array();
        foreach ($codes as $counter => $code) $vectors['counter ' . $counter] = array($counter, $code);
        return $vectors;
    }

    #[DataProvider('hotpVectors')]
    public function testHotpMatchesRfc4226($counter, $expected)
    {
        $this->assertSame($expected, (new Totp())->code(self::RFC_SECRET, $counter));
    }

    public static function totpVectors()
    {
        return array(
            array(59, '94287082'),
            array(1111111109, '07081804'),
            array(1111111111, '14050471'),
            array(1234567890, '89005924'),
            array(2000000000, '69279037'),
            // A timestamp beyond the signed 32-bit range (its step, 666666666, still fits in 32 bits)
            array(20000000000, '65353130'));
    }

    #[DataProvider('totpVectors')]
    public function testTotpMatchesRfc6238($unix_time, $expected)
    {
        $totp = new Totp();
        $this->assertSame($expected, $totp->code(self::RFC_SECRET, $totp->step($unix_time), 8));
    }

    // The counter is always eight bytes, so a step past 2^32 doesn't wrap round to an earlier one
    public function testHotpUsesAllSixtyFourBitsOfTheCounter()
    {
        $totp = new Totp();
        $this->assertNotSame($totp->code(self::RFC_SECRET, 0), $totp->code(self::RFC_SECRET, 2 ** 32));
        $this->assertNotSame($totp->code(self::RFC_SECRET, 1), $totp->code(self::RFC_SECRET, 2 ** 32 + 1));
    }

    public static function base32Vectors()
    {
        return array(
            array('', ''),
            array('f', 'MY'),
            array('fo', 'MZXQ'),
            array('foo', 'MZXW6'),
            array('foob', 'MZXW6YQ'),
            array('fooba', 'MZXW6YTB'),
            array('foobar', 'MZXW6YTBOI'));
    }

    #[DataProvider('base32Vectors')]
    public function testBase32MatchesRfc4648($bytes, $encoded)
    {
        $totp = new Totp();
        $this->assertSame($encoded, $totp->base32Encode($bytes));
        $this->assertSame($bytes, $totp->base32Decode($encoded));
    }

    public function testBase32DecodeAcceptsPaddingSpacesAndLowerCase()
    {
        $totp = new Totp();
        $this->assertSame('foobar', $totp->base32Decode('MZXW6YTBOI======'));
        $this->assertSame('foobar', $totp->base32Decode('mzxw 6ytb oi'));
    }

    public function testBase32DecodeRefusesInvalidCharacters()
    {
        $totp = new Totp();
        $this->assertFalse($totp->base32Decode('MZXW1'));
        $this->assertFalse($totp->base32Decode('MZXW-6'));
        $this->assertFalse($totp->base32Decode(array('MZXW6')));
    }

    public function testNewSecretIsTwentyRandomBytes()
    {
        $totp = new Totp();
        $secret = $totp->newSecret();
        $this->assertSame(20, strlen($secret));
        $this->assertNotSame($secret, $totp->newSecret());
    }

    public function testVerifyAcceptsTheStepEitherSide()
    {
        $totp = new Totp();
        $now = 1700000000;
        $step = $totp->step($now);

        $this->assertSame($step, $totp->verify(self::RFC_SECRET, $totp->code(self::RFC_SECRET, $step), $now, 0));
        $this->assertSame($step - 1, $totp->verify(self::RFC_SECRET, $totp->code(self::RFC_SECRET, $step - 1), $now, 0));
        $this->assertSame($step + 1, $totp->verify(self::RFC_SECRET, $totp->code(self::RFC_SECRET, $step + 1), $now, 0));
    }

    public function testVerifyRefusesTwoStepsAway()
    {
        $totp = new Totp();
        $now = 1700000000;
        $step = $totp->step($now);

        $this->assertFalse($totp->verify(self::RFC_SECRET, $totp->code(self::RFC_SECRET, $step - 2), $now, 0));
        $this->assertFalse($totp->verify(self::RFC_SECRET, $totp->code(self::RFC_SECRET, $step + 2), $now, 0));
    }

    public function testVerifyRefusesAStepAlreadyUsed()
    {
        $totp = new Totp();
        $now = 1700000000;
        $step = $totp->step($now);
        $code = $totp->code(self::RFC_SECRET, $step);

        $this->assertFalse($totp->verify(self::RFC_SECRET, $code, $now, $step));
        $this->assertFalse($totp->verify(self::RFC_SECRET, $code, $now, $step + 1));
        $this->assertSame($step, $totp->verify(self::RFC_SECRET, $code, $now, $step - 1));
    }

    public function testVerifyReturnsTheLaterStepWhenTwoStepsShareACode()
    {
        // Every step gives the same code here, as two neighbouring steps occasionally do
        $totp = new class extends Totp {
            public $steps_checked = array();

            public function code($secret, $step, $digits = self::DIGITS)
            {
                $this->steps_checked[] = $step;
                return '123456';
            }
        };
        $now = 1700000000;
        $step = $totp->step($now);

        $this->assertSame($step + 1, $totp->verify(self::RFC_SECRET, '123456', $now, 0));
        // Every step in the window is checked, whichever matches, so the time taken doesn't give the step away
        $this->assertSame(array($step - 1, $step, $step + 1), $totp->steps_checked);
        $this->assertFalse($totp->verify(self::RFC_SECRET, '123456', $now, $step + 1));
        // Nor once the window moves on to a later step that gives the same code
        $this->assertFalse($totp->verify(self::RFC_SECRET, '123456', $now + Totp::PERIOD, $step + 1));
        $this->assertFalse($totp->verify(self::RFC_SECRET, '123456', $now + 2 * Totp::PERIOD, $step + 1));
        $this->assertFalse($totp->verify(self::RFC_SECRET, '123456', $now + 3 * Totp::PERIOD, $step + 1));
        // Once the used step is well past, the code is just a new code
        $this->assertSame($step + 5, $totp->verify(self::RFC_SECRET, '123456', $now + 4 * Totp::PERIOD, $step + 1));
    }

    public function testVerifyKeepsALeadingZero()
    {
        $totp = new Totp();
        $now = 1700000510;

        $this->assertSame('047164', $totp->code(self::RFC_SECRET, $totp->step($now)));
        $this->assertSame($totp->step($now), $totp->verify(self::RFC_SECRET, '047164', $now, 0));
    }

    public function testVerifyTakesSixDigitsOnlyAndStripsSpaces()
    {
        $totp = new Totp();
        $now = 1700000000;
        $code = $totp->code(self::RFC_SECRET, $totp->step($now));

        $this->assertSame($totp->step($now), $totp->verify(self::RFC_SECRET, substr($code, 0, 3) . ' ' . substr($code, 3), $now, 0));
        $this->assertFalse($totp->verify(self::RFC_SECRET, $code . '0', $now, 0));
        $this->assertFalse($totp->verify(self::RFC_SECRET, substr($code, 1), $now, 0));
        $this->assertFalse($totp->verify(self::RFC_SECRET, 'abcdef', $now, 0));
        $this->assertFalse($totp->verify(self::RFC_SECRET, $code . "\n", $now, 0));
        $this->assertFalse($totp->verify(self::RFC_SECRET, array($code), $now, 0));
        // An empty secret never works, even with the code it would give
        $this->assertFalse($totp->verify('', $totp->code('', $totp->step($now)), $now, 0));
    }

    public function testClockOffsetFindsAServerClockThatIsOff()
    {
        $totp = new Totp();
        $now = 1700000000;
        $step = $totp->step($now);

        // The phone is five minutes ahead of the server
        $this->assertSame(5, $totp->clockOffsetMinutes(self::RFC_SECRET, $totp->code(self::RFC_SECRET, $step + 10), $now));
        $this->assertSame(-5, $totp->clockOffsetMinutes(self::RFC_SECRET, $totp->code(self::RFC_SECRET, $step - 10), $now));
        // Within the window it's not a clock problem, and beyond ten minutes it's just a wrong code
        $this->assertFalse($totp->clockOffsetMinutes(self::RFC_SECRET, $totp->code(self::RFC_SECRET, $step), $now));
        $this->assertFalse($totp->clockOffsetMinutes(self::RFC_SECRET, $totp->code(self::RFC_SECRET, $step + 30), $now));
    }

    public function testOtpauthUriEncodesTheAccount()
    {
        $uri = (new Totp())->otpauthUri('foobar', 'jo smith+1@example.com:8080', 'DomainMOD');

        // A colon separates the issuer from the account, so one in the account (a host's port) becomes a hyphen
        $this->assertSame('otpauth://totp/DomainMOD:jo%20smith%2B1%40example.com-8080?secret=MZXW6YTBOI'
            . '&issuer=DomainMOD&algorithm=SHA1&digits=6&period=30', $uri);
    }

} //@formatter:on
