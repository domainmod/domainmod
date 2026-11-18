<?php
/**
 * /tests/Unit/CsrfTest.php
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

use DomainMOD\Csrf;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CsrfTest extends TestCase
{
    private $saved;

    protected function setUp(): void
    {
        $this->saved = array($_GET, $_POST, $_SERVER['REQUEST_METHOD'] ?? null,
            array_key_exists('s_csrf_token', $_SESSION ?? array()) ? array($_SESSION['s_csrf_token']) : null);
        $_GET = array();
        $_POST = array();
        unset($_SERVER['REQUEST_METHOD'], $_SESSION['s_csrf_token']);
    }

    protected function tearDown(): void
    {
        list($_GET, $_POST, $method, $token) = $this->saved;
        if ($method === null) {
            unset($_SERVER['REQUEST_METHOD']);
        } else {
            $_SERVER['REQUEST_METHOD'] = $method;
        }
        // Put back any token an earlier test left (even a null one), rather than only clearing it
        if ($token === null) {
            unset($_SESSION['s_csrf_token']);
        } else {
            $_SESSION['s_csrf_token'] = $token[0];
        }
    }

    public function testGetTokenMakesA64CharacterHexTokenOnceAndKeepsIt()
    {
        $csrf = new Csrf();
        $token = $csrf->getToken();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
        $this->assertSame($token, $csrf->getToken());
        $this->assertSame($token, (new Csrf())->getToken());
        $this->assertSame($token, $_SESSION['s_csrf_token']);
    }

    public function testGetTokenReplacesAnEmptyOrBrokenToken()
    {
        foreach (array('', array('x'), 42) as $broken) {
            $_SESSION['s_csrf_token'] = $broken;
            $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (new Csrf())->getToken());
        }
    }

    public function testNewTokenReplacesIt()
    {
        $csrf = new Csrf();
        $old = $csrf->getToken();
        $new = $csrf->newToken();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $new);
        $this->assertNotSame($old, $new);
        $this->assertSame($new, $csrf->getToken());
    }

    public function testEachNewSessionGetsItsOwnToken()
    {
        $first = (new Csrf())->getToken();
        unset($_SESSION['s_csrf_token']);
        $second = (new Csrf())->getToken();

        $this->assertNotSame($first, $second);
        // and the first session's token isn't accepted in the second
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST['csrf_token'] = $first;
        $this->assertFalse((new Csrf())->isValid());
    }

    public function testFieldAndQueryCarryTheToken()
    {
        $csrf = new Csrf();
        $token = $csrf->getToken();

        $this->assertSame('<input type="hidden" name="csrf_token" value="' . $token . '">', $csrf->field());
        $this->assertSame('csrf_token=' . $token, $csrf->query());
    }

    public function testAPostIsValidOnlyWithTheTokenInItsForm()
    {
        $csrf = new Csrf();
        $token = $csrf->getToken();
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $_POST = array('csrf_token' => $token);
        $this->assertTrue($csrf->isValid());

        // a POST doesn't take the token from its address
        $_POST = array();
        $_GET = array('csrf_token' => $token);
        $this->assertFalse($csrf->isValid());
    }

    public function testAGetIsValidOnlyWithTheTokenInItsAddress()
    {
        $csrf = new Csrf();
        $token = $csrf->getToken();

        // with no request method, it's read as a GET
        $_GET = array('csrf_token' => $token);
        $this->assertTrue($csrf->isValid());

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->assertTrue($csrf->isValid());

        // a GET doesn't take the token from a form
        $_GET = array();
        $_POST = array('csrf_token' => $token);
        $this->assertFalse($csrf->isValid());
    }

    public static function badSubmissions()
    {
        return array(
            'missing' => array(null),
            'wrong' => array(str_repeat('0', 64)),
            'empty' => array(''),
            'an array' => array(array('x')),
        );
    }

    #[DataProvider('badSubmissions')]
    public function testIsInvalidWithAMissingOrWrongToken($submitted)
    {
        $csrf = new Csrf();
        $token = $csrf->getToken();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        if ($submitted !== null) $_POST = array('csrf_token' => $submitted);

        $this->assertNotSame($token, $submitted);
        $this->assertFalse($csrf->isValid());
    }

    public function testIsInvalidWithTheTokenAndAnythingMore()
    {
        $csrf = new Csrf();
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = array('csrf_token' => $csrf->getToken() . ' ');

        $this->assertFalse($csrf->isValid());
    }

    public function testIsInvalidWhenTheSessionHasNoToken()
    {
        $_GET = array('csrf_token' => '');

        $this->assertFalse((new Csrf())->isValid());
        $this->assertArrayNotHasKey('s_csrf_token', $_SESSION ?? array());
    }

    public static function urls()
    {
        return array(
            'among others, with a fragment' => array('page.php?a=1&csrf_token=x&b=2#top', 'page.php?a=1&b=2#top'),
            'the only parameter' => array('page.php?csrf_token=x', 'page.php'),
            'written with a dot' => array('page.php?csrf.token=x&a=1', 'page.php?a=1'),
            'written with a space' => array('page.php?csrf token=x', 'page.php'),
            'written as an array' => array('page.php?csrf_token[]=x&a=1', 'page.php?a=1'),
            'percent-encoded' => array('page.php?csrf%5Ftoken=x&a=1', 'page.php?a=1'),
            'another case is another name' => array('page.php?CSRF_TOKEN=x', 'page.php?CSRF_TOKEN=x'),
            'empty pairs' => array('page.php?a=1&&b=2', 'page.php?a=1&b=2'),
            'only in the fragment' => array('page.php#csrf_token=x', 'page.php#csrf_token=x'),
            'no query' => array('page.php', 'page.php'),
        );
    }

    #[DataProvider('urls')]
    public function testRemoveTokenRemovesOnlyTheToken($url, $expected)
    {
        $this->assertSame($expected, (new Csrf())->removeToken($url));
    }

    public function testRemoveParameterRemovesTheNamedParameter()
    {
        $this->assertSame('cron.php?a=1', (new Csrf())->removeParameter('cron.php?key=secret&a=1', 'key'));
        $this->assertSame('cron.php?keys=1', (new Csrf())->removeParameter('cron.php?keys=1', 'key'));
    }

    public static function redirects()
    {
        return array(
            'a page on this site, without the token' => array('/domains/index.php?csrf_token=x&a=1', '/domains/index.php?a=1'),
            'a relative page' => array('index.php', 'index.php'),
            'another host' => array('https://evil.example/', '/'),
            'a scheme-relative address' => array('//evil.example/', '/'),
            'a backslash' => array('/\\evil.example', '/'),
            'a script' => array('javascript:alert(1)', '/'),
            'empty' => array('', '/'),
        );
    }

    #[DataProvider('redirects')]
    public function testCheckRedirectsOnlyWithinTheSite($url, $expected)
    {
        // WEB_ROOT isn't defined in the tests, so the site's root is '/'
        $this->assertSame($expected, (new \ReflectionMethod(Csrf::class, 'safeRedirect'))->invoke(new Csrf(), $url));
    }

} //@formatter:on
