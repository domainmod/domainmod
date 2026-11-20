<?php
/**
 * /tests/Unit/ApiCredentialsTest.php
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

use DomainMOD\Api;
use DomainMOD\Sanitize;
use DomainMOD\Tests\Support\FakeDatabase;
use DomainMOD\Tests\Support\Instance;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/*
 * The registrar account pages save every field through Sanitize::text(), so a password, API key, or other value with
 * &, quotes, or < and > in it is stored HTML-escaped. Api's getters handed those stored values straight to the
 * registrar APIs, so a secret like p&ss"word was sent as p&amp;ss&quot;word and the registrar refused it. Each getter
 * now decodes the account's values, so the registrar gets them as they were typed.
 */
class ApiCredentialsTest extends TestCase
{
    // A value with every character Sanitize::text() escapes
    const TYPED = 'p&ss"wo\'rd<>&amp;';

    public static function getters()
    {
        return array(
            'getKey' => array('getKey', 'column', array('api_key'), self::TYPED),
            'getKeySecret' => array('getKeySecret', 'row', array('api_key', 'api_secret'), array(self::TYPED, self::TYPED . '2')),
            'getUserKey' => array('getUserKey', 'row', array('username', 'api_key'), array(self::TYPED, self::TYPED . '2')),
            'getUserAppSecret' => array('getUserAppSecret', 'row', array('username', 'api_app_name', 'api_secret'),
                array(self::TYPED, self::TYPED . '2', self::TYPED . '3')),
            'getResellerIdKey' => array('getResellerIdKey', 'row', array('reseller_id', 'api_key'), array(self::TYPED, self::TYPED . '2')),
            'getAccountId' => array('getAccountId', 'row', array('account_id'), self::TYPED),
            'getUserPass' => array('getUserPass', 'row', array('username', 'password'), array(self::TYPED, self::TYPED . '2')),
        );
    }

    #[DataProvider('getters')]
    public function testEachGetterReturnsTheValuesAsTyped($method, $kind, array $columns, $expected)
    {
        // the row as the account pages save it
        $typed = (array) $expected;
        $row = new \stdClass();
        foreach ($columns as $index => $column) $row->$column = (new Sanitize())->text($typed[$index]);
        $answer = ($kind === 'column') ? $row->{$columns[0]} : $row;

        $this->assertSame($expected, $this->api($answer)->$method(7));
    }

    public function testTheIpAddressIsReturnedWithTheUsernameAndKey()
    {
        $row = (object) array('username' => (new Sanitize())->text(self::TYPED),
            'api_key' => (new Sanitize())->text(self::TYPED . '2'), 'ip' => '203.0.113.5');

        $this->assertSame(array(self::TYPED, self::TYPED . '2', '203.0.113.5'), $this->api($row)->getUserKeyIp(7));
    }

    // An Api whose database answers every registrar account query with $answer
    private function api($answer)
    {
        $pdo = new FakeDatabase(array('FROM registrar_accounts' => $answer));
        return Instance::withoutConstructor(Api::class, array('deeb' => (object) array('cnxx' => $pdo)));
    }

} //@formatter:on
