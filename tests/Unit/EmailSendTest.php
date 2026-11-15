<?php
/**
 * /tests/Unit/EmailSendTest.php
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
use PHPUnit\Framework\TestCase;

class EmailSendTest extends TestCase
{
    // An Email set to send through PHP's mail(), with intPhpMail() answering as given, so nothing is really sent
    private function phpMailEmail($sent)
    {
        $email = $this->getMockBuilder(Email::class)->disableOriginalConstructor()->onlyMethods(array('intPhpMail'))->getMock();
        $email->use_smtp = '0';
        $email->expects($this->once())->method('intPhpMail')
            ->with('Test', 'to@example.invalid', 'Subject', '<p>HTML</p>')->willReturn($sent);
        return $email;
    }

    public function testReturnsTrueWhenTheEmailIsSent()
    {
        $this->assertTrue($this->phpMailEmail(true)->send('Test', 'to@example.invalid', 'Subject', '<p>HTML</p>', 'Text'));
    }

    public function testReturnsFalseWhenTheEmailCantBeSent()
    {
        // Send Test Email shows a failure instead of "Test Email Sent!" when this is false
        $this->assertFalse($this->phpMailEmail(false)->send('Test', 'to@example.invalid', 'Subject', '<p>HTML</p>', 'Text'));
    }

} //@formatter:on
