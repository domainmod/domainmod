<?php
/**
 * /tests/Unit/EmailAddressDecodeTest.php
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

use DomainMOD\Email;
use DomainMOD\Format;
use DomainMOD\Log;
use DomainMOD\Sanitize;
use DomainMOD\Smtp;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\TestCase;

/*
 * Email addresses are saved HTML-escaped (Sanitize::text()), and the emails passed the stored values straight to the
 * mailer, so an address with & or ' in it (such as o'brien&co@example.invalid) reached PHPMailer as
 * o&#039;brien&amp;co@example.invalid, which it turned down, and the email didn't go. The recipient, sender, and
 * reply-to addresses are now decoded where they're handed to the mailer, for PHP's mail() and SMTP alike.
 */
class EmailAddressDecodeTest extends TestCase
{
    const TYPED = "o'brien&co@example.invalid";
    const FROM = "from'&x@example.invalid";
    const SMTP_FROM = "smtp'&x@example.invalid";

    protected function setUp(): void
    {
        if (!defined('SOFTWARE_TITLE')) define('SOFTWARE_TITLE', 'DomainMOD');
        if (!defined('EMAIL_ENCODING_TYPE')) define('EMAIL_ENCODING_TYPE', 'UTF-8');
    }

    // An address as it's stored
    private function stored($address)
    {
        return (new Sanitize())->text($address);
    }

    // A PHPMailer that builds the whole message but never hands it to mail() or an SMTP server.
    private function fakeMailer()
    {
        $mail = $this->getMockBuilder(PHPMailer::class)->onlyMethods(array('postSend'))->getMock();
        $mail->method('postSend')->willReturn(true);
        return $mail;
    }

    public function testStoredAddressesReallyAreEscaped()
    {
        $this->assertSame('o&#039;brien&amp;co@example.invalid', $this->stored(self::TYPED));
        $this->assertSame(self::TYPED, Email::address($this->stored(self::TYPED)));
        $this->assertSame('', Email::address(null));
    }

    public function testPhpMailSendsToAndFromTheAddressesAsTyped()
    {
        $mail = $this->fakeMailer();
        $email = $this->getMockBuilder(Email::class)->disableOriginalConstructor()->onlyMethods(array('getMailer'))->getMock();
        $email->method('getMailer')->willReturn($mail);
        $email->log = $this->createMock(Log::class);
        $email->from_address = $this->stored(self::FROM);
        $email->use_smtp = '0';

        $this->assertTrue($email->send('Test', $this->stored(self::TYPED), 'Subject', '<p>HTML</p>', 'Text'));

        $this->assertSame(array(array(self::TYPED, '')), $mail->getToAddresses());
        $this->assertSame(self::FROM, $mail->From);
        $this->assertSame(self::FROM, $mail->Sender);
        $this->assertSame(array(array(self::FROM, '')), $mail->getReplyToAddresses());
    }

    public function testSmtpSendsToAndFromTheAddressesAsTyped()
    {
        $mail = $this->fakeMailer();
        $smtp = $this->getMockBuilder(Smtp::class)->disableOriginalConstructor()->onlyMethods(array('getMailer'))->getMock();
        $smtp->method('getMailer')->willReturn($mail);
        $smtp->log = $this->createMock(Log::class);
        $smtp->format = new Format();
        $smtp->server = 'smtp.example.invalid';
        $smtp->protocol = 'tls';
        $smtp->port = '587';
        $smtp->email_address = $this->stored(self::SMTP_FROM);
        $smtp->username = 'user';
        $smtp->password = 'secret';

        $this->assertTrue($smtp->send('Test', $this->stored(self::TYPED), $this->stored(self::FROM), 'Subject', '<p>HTML</p>', 'Text'));

        $this->assertSame(array(array(self::TYPED, '')), $mail->getToAddresses());
        $this->assertSame(self::SMTP_FROM, $mail->From);
        $this->assertSame(array(array(self::FROM, 'DomainMOD Admin')), $mail->getReplyToAddresses());
    }

} //@formatter:on
