<?php
/**
 * /tests/Unit/EmailLogoTest.php
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
use DomainMOD\Smtp;
use DomainMOD\Tests\Support\Instance;
use PHPMailer\PHPMailer\PHPMailer;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

class EmailLogoTest extends TestCase
{
    private const HTML = '<html><body><p>Héllo</p><img src="cid:domainmod-logo"></body></html>';
    private const TEXT = "Héllo\n\nThe plain-text version, exactly as given.\n";

    protected function setUp(): void
    {
        if (!defined('SOFTWARE_TITLE')) define('SOFTWARE_TITLE', 'DomainMOD');
        if (!defined('EMAIL_ENCODING_TYPE')) define('EMAIL_ENCODING_TYPE', 'UTF-8');
    }

    // A PHPMailer that builds the whole message but never hands it to mail() or an SMTP server.
    private function fakeMailer($sent = true)
    {
        $mail = $this->getMockBuilder(PHPMailer::class)->onlyMethods(array('postSend'))->getMock();
        $mail->method('postSend')->willReturn($sent);
        return $mail;
    }

    // An Email set to send through PHP's mail(), using the given mailer.
    private function phpMailEmail($mail)
    {
        $email = $this->getMockBuilder(Email::class)->disableOriginalConstructor()->onlyMethods(array('getMailer'))->getMock();
        $email->method('getMailer')->willReturn($mail);
        $email->log = $this->createMock(Log::class);
        $email->from_address = 'from@example.invalid';
        $email->use_smtp = '0';
        return $email;
    }

    // An Smtp with made-up settings, using the given mailer.
    private function smtp($mail)
    {
        $smtp = $this->getMockBuilder(Smtp::class)->disableOriginalConstructor()->onlyMethods(array('getMailer'))->getMock();
        $smtp->method('getMailer')->willReturn($mail);
        $smtp->log = $this->createMock(Log::class);
        $smtp->format = new Format();
        $smtp->server = 'smtp.example.invalid';
        $smtp->protocol = 'tls';
        $smtp->port = '587';
        $smtp->email_address = 'smtp@example.invalid';
        $smtp->username = 'user';
        $smtp->password = 'secret';
        return $smtp;
    }

    // The logo attachments on a mailer, as [Content-ID, file name, type, disposition].
    private function logoAttachments($mail)
    {
        $logos = array();
        foreach ($mail->getAttachments() as $attachment) {
            $logos[] = array($attachment[7], $attachment[2], $attachment[4], $attachment[6]);
        }
        return $logos;
    }

    private function assertLogoEmbedded($mail)
    {
        $this->assertSame(array(array('domainmod-logo', 'logo.png', 'image/png', 'inline')), $this->logoAttachments($mail));
        $this->assertTrue($mail->inlineImageExists());

        $mime = $mail->getSentMIMEMessage();
        $this->assertStringContainsString('multipart/related', $mime);
        $this->assertStringContainsString('multipart/alternative', $mime);
        $this->assertStringContainsString('Content-ID: <domainmod-logo>', $mime);
        $this->assertStringContainsString('Content-Type: image/png; name=logo.png', $mime);
        $this->assertStringContainsString('Content-Type: text/plain; charset=UTF-8', $mime);
        $this->assertStringContainsString(chunk_split(base64_encode(file_get_contents(DIR_ROOT . '/images/logo.png')), 76, "\r\n"), $mime);
    }

    public function testLogoHtmlShowsTheAttachedImageAndLinksToTheInstallation()
    {
        $email = Instance::withoutConstructor(Email::class, array('full_url' => 'https://example.invalid/dm'));
        $html = $email->logoHtml();

        $this->assertStringContainsString('<img src="cid:domainmod-logo" width="204" height="32" border="0" alt="DomainMOD"', $html);
        $this->assertStringContainsString('<a title="DomainMOD" href="https://example.invalid/dm/">', $html);
        $this->assertStringNotContainsString('images/logo.png', $html);
    }

    public function testLogoSitsOnAWhiteCellForDarkModeMailApps()
    {
        $html = Instance::withoutConstructor(Email::class, array('full_url' => 'https://example.invalid'))->logoHtml();

        $this->assertMatchesRegularExpression('/<td bgcolor="#FFFFFF" style="background-color: #FFFFFF;"><a [^>]*><img /', $html);
    }

    public function testLogoLinkIsEscaped()
    {
        $html = Instance::withoutConstructor(Email::class, array('full_url' => 'https://example.invalid/?a=1&b="2"'))->logoHtml();

        $this->assertStringContainsString('href="https://example.invalid/?a=1&amp;b=&quot;2&quot;/"', $html);
    }

    #[RunInSeparateProcess]
    public function testExpirationEmailUsesTheSharedLogo()
    {
        define('DEFAULT_LANGUAGE', 'en_US.UTF-8');
        $email = Instance::withoutConstructor(Email::class, array('full_url' => 'https://example.invalid', 'number_of_days' => 60));

        $html = $email->messageTopHtml('Upcoming Expirations');

        $this->assertStringContainsString($email->logoHtml(), $html);
        $this->assertStringNotContainsString('images/logo.png', $html);
    }

    #[RunInSeparateProcess]
    public function testExpirationEmailAsksMailAppsNotToInvertIt()
    {
        define('DEFAULT_LANGUAGE', 'en_US.UTF-8');
        $email = Instance::withoutConstructor(Email::class, array('full_url' => 'https://example.invalid', 'number_of_days' => 60));

        $html = $email->messageTopHtml('Upcoming Expirations');

        $this->assertStringContainsString('<meta name="color-scheme" content="light only">', $html);
        $this->assertStringContainsString('<meta name="supported-color-schemes" content="light only">', $html);
        $this->assertLessThan(strpos($html, '</head>'), strpos($html, 'color-scheme'));
    }

    public function testPhpMailEmbedsTheLogoAndSendsBothVersions()
    {
        $mail = $this->fakeMailer();
        $email = $this->phpMailEmail($mail);
        $email->log->expects($this->once())->method('info');

        $this->assertTrue($email->send('Test', 'to@example.invalid', 'Subject', self::HTML, self::TEXT));

        $this->assertSame('mail', $mail->Mailer);
        $this->assertSame(self::HTML, $mail->Body);
        $this->assertSame(self::TEXT, $mail->AltBody);
        $this->assertLogoEmbedded($mail);
    }

    public function testPhpMailKeepsTheSameSenderDetails()
    {
        $mail = $this->fakeMailer();
        $this->phpMailEmail($mail)->send('Test', 'to@example.invalid', 'Subject', self::HTML, self::TEXT);

        $this->assertSame('from@example.invalid', $mail->From);
        $this->assertSame('DomainMOD', $mail->FromName);
        $this->assertSame('from@example.invalid', $mail->Sender);
        $this->assertSame(array(array('from@example.invalid', '')), $mail->getReplyToAddresses());
        $this->assertSame(array(array('to@example.invalid', '')), $mail->getToAddresses());
        $this->assertSame('UTF-8', $mail->CharSet);
        $this->assertSame('Subject', $mail->Subject);
    }

    public function testPhpMailReturnsFalseAndLogsWhenTheEmailCantBeSent()
    {
        $email = $this->phpMailEmail($this->fakeMailer(false));
        $email->log->expects($this->once())->method('error')->with('Test Email :: SEND FAILED');
        $email->log->expects($this->never())->method('info');

        $this->assertFalse($email->send('Test', 'to@example.invalid', 'Subject', self::HTML, self::TEXT));
    }

    public function testPhpMailFailsInsteadOfSendingWithNoFromAddress()
    {
        $mail = $this->getMockBuilder(PHPMailer::class)->onlyMethods(array('postSend'))->getMock();
        $mail->expects($this->never())->method('postSend');
        $email = $this->phpMailEmail($mail);
        $email->from_address = 'not an address';
        $email->log->expects($this->once())->method('error')->with('Test Email :: SEND FAILED');

        $this->assertFalse($email->send('Test', 'to@example.invalid', 'Subject', self::HTML, self::TEXT));
    }

    public function testSmtpEmbedsTheLogoAndSendsBothVersions()
    {
        $mail = $this->fakeMailer();
        $smtp = $this->smtp($mail);
        $smtp->log->expects($this->once())->method('info');

        $this->assertTrue($smtp->send('Test', 'to@example.invalid', 'reply@example.invalid', 'Subject', self::HTML, self::TEXT));

        $this->assertSame('smtp', $mail->Mailer);
        $this->assertSame('smtp@example.invalid', $mail->From);
        $this->assertSame(array(array('reply@example.invalid', 'DomainMOD Admin')), $mail->getReplyToAddresses());
        $this->assertSame(self::HTML, $mail->Body);
        $this->assertSame(self::TEXT, $mail->AltBody);
        $this->assertLogoEmbedded($mail);
    }

    public function testNoLogoIsAttachedWhenTheEmailDoesntShowIt()
    {
        $mail = $this->fakeMailer();
        $this->phpMailEmail($mail)->send('Test', 'to@example.invalid', 'Subject', '<p>No logo</p>', 'No logo');

        $this->assertSame(array(), $this->logoAttachments($mail));
        $this->assertStringNotContainsString('multipart/related', $mail->getSentMIMEMessage());
    }

} //@formatter:on
