<?php
/**
 * /tests/Unit/SmtpPlainRelayTest.php
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

use DomainMOD\Form;
use DomainMOD\Format;
use DomainMOD\Log;
use DomainMOD\Smtp;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;


/*
 * System Settings only offered TLS or SSL for the SMTP server, and Smtp::send() always signed in, so DomainMOD couldn't
 * send through a relay with no encryption and no sign-in, such as a local one. The protocol can now be None (saved as
 * an empty value), and Smtp::send() only signs in when an SMTP Username is set. With None, PHPMailer doesn't switch
 * to TLS on its own either, so a relay with a self-signed certificate works. A username and password are never sent
 * without encryption: the settings page refuses them with None, and Smtp::send() sends nothing if they're stored with
 * a protocol other than TLS or SSL anyway.
 */
class SmtpPlainRelayTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('SOFTWARE_TITLE')) define('SOFTWARE_TITLE', 'DomainMOD');
        if (!defined('EMAIL_ENCODING_TYPE')) define('EMAIL_ENCODING_TYPE', 'UTF-8');
    }

    public function testARelayWithNoEncryptionOrSignInIsUsedAsIs()
    {
        $mail = $this->sentWith('', '', '');

        $this->assertSame('', $mail->SMTPSecure);
        $this->assertFalse($mail->SMTPAuth);
        $this->assertFalse($mail->SMTPAutoTLS);
        $this->assertSame('dev-mail', $mail->Host);
        $this->assertSame(1025, (int) $mail->Port);
    }

    public function testAServerWithAUsernameIsSignedInTo()
    {
        $mail = $this->sentWith('tls', 'user', 'secret');

        $this->assertSame('tls', $mail->SMTPSecure);
        $this->assertTrue($mail->SMTPAuth);
        $this->assertSame('user', $mail->Username);
        $this->assertSame('secret', $mail->Password);
    }

    public static function unencryptedProtocols()
    {
        return array('none' => array(''), 'not one DomainMOD saves' => array('foo'));
    }

    #[DataProvider('unencryptedProtocols')]
    public function testASignInIsNeverSentWithoutEncryption($protocol)
    {
        $mail = $this->getMockBuilder(PHPMailer::class)->onlyMethods(array('postSend'))->getMock();
        $mail->expects($this->never())->method('postSend');
        $smtp = $this->smtp($mail, $protocol, 'user', 'secret');
        $smtp->log->expects($this->once())->method('error')
            ->with('Test Email :: SEND FAILED', $this->callback(function ($extra) {
                return $extra['Error'] === 'An SMTP username is set, but the protocol isn\'t TLS or SSL, so nothing was sent. ' .
                    'In the System Settings, choose TLS or SSL, or leave the username and password blank.' &&
                    $extra['Password'] !== 'secret';
            }));

        $this->assertFalse($smtp->send('Test', 'to@example.invalid', 'reply@example.invalid', 'Subject', '<p>Hi</p>', 'Hi'));
    }

    public function testTheSettingsPageOffersNoneAndRefusesOtherProtocols()
    {
        $source = file_get_contents(DIR_ROOT . '/admin/settings/index.php');
        $this->assertNotFalse($source);

        $this->assertMatchesRegularExpression("/\\\$form->showRadioOption\\(\\s*'new_smtp_protocol'\\s*,\\s*''\\s*,\\s*_\\('None'\\)/", $source);
        // Only TLS, SSL, or none is saved, whatever is posted.
        $this->assertMatchesRegularExpression("/\\\$new_smtp_protocol\\s*=\\s*in_array\\(\\s*\\\$_POST\\['new_smtp_protocol'\\]\\s*\\?\\?\\s*''\\s*,\\s*" .
            "array\\(\\s*'tls'\\s*,\\s*'ssl'\\s*,\\s*''\\s*\\)\\s*,\\s*true\\s*\\)/", $source);

        // A username or a typed password with None isn't saved, and the username field says why.
        $this->assertMatchesRegularExpression("/\\\$smtp_sign_in_unencrypted\\s*=\\s*\\\$new_smtp_protocol\\s*===\\s*''\\s*&&\\s*" .
            "\\(\\s*\\\$new_smtp_username\\s*!==\\s*''\\s*\\|\\|\\s*\\\$new_smtp_password\\s*!==\\s*''\\s*\\)\\s*;/", $source);
        $this->assertMatchesRegularExpression("/if\\s*\\(\\s*\\\$_SERVER\\['REQUEST_METHOD'\\]\\s*==\\s*'POST'[^{]*&&\\s*!\\\$smtp_sign_in_unencrypted\\s*\\)\\s*\\{/", $source);
        $this->assertMatchesRegularExpression("/if\\s*\\(\\s*\\\$smtp_sign_in_unencrypted\\s*\\)\\s*\\\$form->addFieldError\\(\\s*'new_smtp_username'/", $source);
        // A typed password isn't put back in the field, so its error says to type it again only with TLS or SSL.
        $this->assertMatchesRegularExpression("/if\\s*\\(\\s*\\\$new_smtp_password\\s*!==\\s*''\\s*&&\\s*!\\\$smtp_sign_in_unencrypted\\s*\\)\\s*" .
            "\\\$form->addFieldError\\(\\s*'new_smtp_password'\\s*,\\s*_\\('Enter the new value again'\\)/", $source);
        $this->assertMatchesRegularExpression("/if\\s*\\(\\s*\\\$new_smtp_password\\s*!==\\s*''\\s*&&\\s*\\\$smtp_sign_in_unencrypted\\s*\\)\\s*" .
            "\\\$form->addFieldError\\(\\s*'new_smtp_password'\\s*,\\s*_\\('Choose TLS or SSL and enter the new value again, or leave it blank\\.'\\)/",
            $source);
    }

    public function testTheProtocolHelpSitsOnItsOwnLineAboveTheChoices()
    {
        $form = new Form();

        $this->assertSame('<div class="mb-3"><strong>Protocol</strong><BR>Help.<BR>',
            trim($form->showRadioTop('Protocol', 'Help.', '')));
        $this->assertSame('<div class="mb-3"><strong>Protocol</strong><BR>', trim($form->showRadioTop('Protocol', '', '')));
    }

    // The mailer Smtp::send() used, with the given protocol and login, after a send that never reaches a server.
    private function sentWith($protocol, $username, $password)
    {
        $mail = $this->getMockBuilder(PHPMailer::class)->onlyMethods(array('postSend'))->getMock();
        $mail->method('postSend')->willReturn(true);

        $smtp = $this->smtp($mail, $protocol, $username, $password);
        $this->assertTrue($smtp->send('Test', 'to@example.invalid', 'reply@example.invalid', 'Subject', '<p>Hi</p>', 'Hi'));
        return $mail;
    }

    // An Smtp with the given protocol and login, using the given mailer.
    private function smtp($mail, $protocol, $username, $password)
    {
        $smtp = $this->getMockBuilder(Smtp::class)->disableOriginalConstructor()->onlyMethods(array('getMailer'))->getMock();
        $smtp->method('getMailer')->willReturn($mail);
        $smtp->log = $this->createMock(Log::class);
        $smtp->format = new Format();
        $smtp->server = 'dev-mail';
        $smtp->protocol = $protocol;
        $smtp->port = '1025';
        $smtp->email_address = 'smtp@example.invalid';
        $smtp->username = $username;
        $smtp->password = $password;
        return $smtp;
    }

} //@formatter:on
