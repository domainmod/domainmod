<?php

use Auth0\SDK\Store\SessionStore;

/**
 * Class SessionStoreTest.
 * Tests the SessionStore class.
 */
class SessionStoreTest extends PHPUnit_Framework_TestCase
{
    /**
     * Session key for test values.
     */
    const TEST_KEY = 'never_compromise_on_identity';

    /**
     * Session value to test.
     */
    const TEST_VALUE = '__Auth0__';

    /**
     * Expected cookie lifetime of 1 week.
     * 60 s/min * 60 min/h * 24 h/day * 7 days.
     */
    const COOKIE_LIFETIME = 604800;

    /**
     * Reusable instance of SessionStore class to be tested.
     *
     * @var SessionStore
     */
    public static $sessionStore;

    /**
     * Full session array key.
     *
     * @var string
     */
    public static $sessionKey;

    /**
     * Test fixture for class, runs once before any tests.
     *
     * @return void
     */
    public static function setUpBeforeClass()
    {
        self::$sessionStore = new SessionStore();
        self::$sessionKey   = 'auth0__'.self::TEST_KEY;
    }

    /**
     * Test that SessionStore::initSession ran and cookie params are stored correctly.
     *
     * @return void
     */
    public function testInitSession()
    {
        // Suppressing "headers already sent" warning related to cookies.
        // phpcs:ignore
        @self::$sessionStore->set(self::TEST_KEY, self::TEST_VALUE);

        // Make sure we have a session to check.
        $this->assertNotEmpty(session_id());

        $cookieParams = session_get_cookie_params();
        $this->assertEquals(self::COOKIE_LIFETIME, $cookieParams['lifetime']);
    }

    /**
     * Test that SessionStore::getSessionKeyName returns the expected name.
     *
     * @return void
     */
    public function testGetSessionKey()
    {
        $test_this_key_name = self::$sessionStore->getSessionKeyName(self::TEST_KEY);
        $this->assertEquals(self::$sessionKey, $test_this_key_name);
    }

    /**
     * Test that SessionStore::set stores the correct value.
     *
     * @return void
     *
     * @runInSeparateProcess
     */
    public function testSet()
    {
        // Make sure this key does not exist yet so we can test that it was set.
        $this->assertFalse(isset($_SESSION[self::$sessionKey]));

        // Suppressing "headers already sent" warning related to cookies.
        // phpcs:ignore
        @self::$sessionStore->set(self::TEST_KEY, self::TEST_VALUE);

        $this->assertEquals(self::TEST_VALUE, $_SESSION[self::$sessionKey]);
    }

    /**
     * Test that SessionStore::get stores the correct value.
     *
     * @return void
     *
     * @runInSeparateProcess
     */
    public function testGet()
    {
        session_start();
        $_SESSION[self::$sessionKey] = self::TEST_VALUE;
        $this->assertEquals(self::TEST_VALUE, self::$sessionStore->get(self::TEST_KEY));
    }

    /**
     * Test that SessionStore::delete trashes the stored value.
     *
     * @return void
     *
     * @runInSeparateProcess
     */
    public function testDelete()
    {
        session_start();
        $_SESSION[self::$sessionKey] = self::TEST_VALUE;
        $this->assertTrue(isset($_SESSION[self::$sessionKey]));

        self::$sessionStore->delete(self::TEST_KEY);
        $this->assertNull(self::$sessionStore->get(self::TEST_KEY));
        $this->assertFalse(isset($_SESSION[self::$sessionKey]));
    }

    /**
     * Test that custom base names can be set and return the correct value.
     *
     * @return void
     *
     * @runInSeparateProcess
     */
    public function testCustomSessionBaseName()
    {
        $test_base_name = 'test_base_name';

        self::$sessionStore = new SessionStore( $test_base_name );
        $test_this_key_name = self::$sessionStore->getSessionKeyName(self::TEST_KEY);
        $this->assertEquals($test_base_name.'_'.self::TEST_KEY, $test_this_key_name);

        // Suppressing "headers already sent" warning related to cookies.
        // phpcs:ignore
        @self::$sessionStore->set(self::TEST_KEY, self::TEST_VALUE);
        $this->assertEquals(self::TEST_VALUE, self::$sessionStore->get(self::TEST_KEY));
    }

    /**
     * Test that custom cookie expires can be set.
     *
     * @return void
     *
     * @runInSeparateProcess
     */
    public function testCustomCookieExpires()
    {
        $custom_expires = mt_rand( 11111, 99999 );

        $this->assertEmpty(session_id());
        self::$sessionStore = new SessionStore( null, $custom_expires );

        // Suppressing "headers already sent" warning related to cookies.
        // phpcs:ignore
        @self::$sessionStore->set(self::TEST_KEY, self::TEST_VALUE);

        $this->assertNotEmpty(session_id());
        $cookieParams = session_get_cookie_params();
        $this->assertEquals($custom_expires, $cookieParams['lifetime']);
    }
}
