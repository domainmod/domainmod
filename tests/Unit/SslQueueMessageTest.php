<?php
/**
 * /tests/Unit/SslQueueMessageTest.php
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

use DomainMOD\SslQueue;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\TestCase;

/*
 * SslQueue::getMessage(): every result code has its own translated message, and an unknown code shows a general
 * message, never the code itself.
 */
class SslQueueMessageTest extends TestCase
{

    public function testTheMessages()
    {
        $queue = Instance::withoutConstructor(SslQueue::class);
        $expected = array(
            'untrusted' => "Imported, but the certificate isn't trusted.",
            'expired' => 'Imported, but the certificate has expired.',
            'name_mismatch' => "The site's certificate doesn't cover this hostname (it's often the hosting company's shared certificate).",
            'not_yet_valid' => "This certificate isn't valid yet. Retry it after its start date.",
            'no_dns' => "This hostname doesn't resolve.",
            'not_public' => "This hostname resolves to an address that isn't public, so it can't be checked.",
            'no_connect' => 'Nothing answered on port 443.',
            'no_tls' => 'The site answered on port 443, but not with TLS.',
            'timeout' => 'The lookup ran out of time.',
            'stale_choice' => 'Something chosen when this hostname was queued has changed or been deleted. Delete this queue entry and add the hostname again.',
            'local_error' => "DomainMOD couldn't write a temporary file to check this certificate. Check that PHP's temporary folder is writable.",
            'name_too_long' => 'This hostname is longer than the 100 characters an SSL certificate\'s Host / Label can hold.',
            'bad_certificate' => "A date in this certificate can't be stored.",
            'save_failed' => "The certificate couldn't be saved.");

        foreach ($expected as $code => $message) {
            $this->assertSame($message, $queue->getMessage($code), $code);
        }
    }

    public function testEveryCodeHasItsOwnMessage()
    {
        $queue = Instance::withoutConstructor(SslQueue::class);
        $codes = array_merge(SslQueue::$retry_codes, SslQueue::$failure_codes, array('untrusted', 'expired'));
        $messages = array();

        foreach ($codes as $code) {

            $message = $queue->getMessage($code);
            $this->assertNotSame('', $message, $code);
            $this->assertNotSame($queue->getMessage('unknown_code'), $message, $code);
            $messages[] = $message;

        }

        $this->assertSame(count($messages), count(array_unique($messages)));
    }

    public function testAnUnknownCodeDoesntShowRawText()
    {
        $queue = Instance::withoutConstructor(SslQueue::class);

        $this->assertSame('The lookup failed.', $queue->getMessage('<b>raw</b>'));
        $this->assertSame('', $queue->getMessage('ok'));
        $this->assertSame('', $queue->getMessage(''));
    }

} //@formatter:on
