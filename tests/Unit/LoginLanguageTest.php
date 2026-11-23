<?php
/**
 * /tests/Unit/LoginLanguageTest.php
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
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\TestCase;

// checks.php sends a user to the upgrade notice before it loads their settings, so it first puts their language in the
// session with Login::loadUserLanguage(), and the notice is shown in that language rather than in English.
class LoginLanguageTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = array();
    }

    protected function tearDown(): void
    {
        $_SESSION = array();
    }

    private function login($pdo)
    {
        return Instance::withoutConstructor(Login::class, array('deeb' => (object) array('cnxx' => $pdo)));
    }

    private function pdoReturning($row)
    {
        $statement = $this->createMock(\PDOStatement::class);
        $statement->expects($this->once())->method('bindValue')->with('user_id', 7, \PDO::PARAM_INT);
        $statement->method('fetch')->willReturn($row);
        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->once())->method('prepare')
            ->with($this->stringContains('us.default_language'))->willReturn($statement);
        return $pdo;
    }

    public function testPutsTheUsersLanguageInTheSession()
    {
        $row = (object) array('default_language' => 'de_DE.UTF-8', 'language_name' => 'German');

        $this->assertTrue($this->login($this->pdoReturning($row))->loadUserLanguage(7));
        $this->assertSame('de_DE.UTF-8', $_SESSION['s_default_language']);
        $this->assertSame('German', $_SESSION['s_default_language_name']);
    }

    public function testLeavesTheSessionAsItWasWhenTheUserHasNoSettings()
    {
        $this->assertFalse($this->login($this->pdoReturning(false))->loadUserLanguage(7));
        $this->assertSame(array(), $_SESSION);
    }

    public function testLeavesTheSessionAsItWasWhenTheReadFails()
    {
        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willThrowException(new \PDOException('gone'));

        $this->assertFalse($this->login($pdo)->loadUserLanguage(7));
        $this->assertSame(array(), $_SESSION);
    }

    public function testGetUserLanguageReturnsTheLanguageWithoutChangingTheSession()
    {
        $row = (object) array('default_language' => 'ar_SA.UTF-8', 'language_name' => 'Arabic');

        $this->assertSame('ar_SA.UTF-8', $this->login($this->pdoReturning($row))->getUserLanguage(7)->default_language);
        $this->assertSame(array(), $_SESSION);
    }

    public function testGetUserLanguageReturnsFalseWhenTheReadFails()
    {
        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willThrowException(new \PDOException('gone'));

        $this->assertFalse($this->login($pdo)->getUserLanguage(7));
        $this->assertSame(array(), $_SESSION);
    }

} //@formatter:on
