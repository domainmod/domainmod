<?php
namespace Auth0\Tests\Api\Helpers\State;

use Auth0\SDK\API\Helpers\State\SessionStateHandler;
use Auth0\SDK\Store\SessionStore;

/**
 * Class SessionStateHandlerTest
 *
 * @package Auth0\Tests\Api\Helpers\State
 */
class SessionStateHandlerTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Storage engine to use.
     *
     * @var SessionStore
     */
    private $sessionStore;

    /**
     * State handler to use.
     *
     * @var SessionStateHandler
     */
    private $stateHandler;

    /**
     * SessionStateHandlerTest constructor.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->sessionStore = new SessionStore();
        $this->stateHandler = new SessionStateHandler($this->sessionStore);
    }

    /**
     * Test that state is stored and retrieved properly.
     *
     * @return void
     */
    public function testStateStoredCorrectly()
    {
        $uniqid = uniqid();

        // Suppressing "headers already sent" warning related to cookies.
        // phpcs:ignore
        @$this->stateHandler->store($uniqid);
        $this->assertEquals($uniqid, $this->sessionStore->get(SessionStateHandler::STATE_NAME));
    }

    /**
     * Test that the state is being issued correctly.
     *
     * @return void
     */
    public function testStateIssuedCorrectly()
    {
        $state_issued = $this->stateHandler->issue();
        $this->assertEquals($state_issued, $this->sessionStore->get(SessionStateHandler::STATE_NAME));
    }

    /**
     * Test that state validated properly.
     *
     * @return void
     */
    public function testStateValidatesCorrectly()
    {
        $state_issued = $this->stateHandler->issue();
        $this->assertTrue($this->stateHandler->validate($state_issued));
        $this->assertNull($this->sessionStore->get(SessionStateHandler::STATE_NAME));

    }

    /**
     * Test that state validation fails with an incorrect value.
     *
     * @return void
     */
    public function testStateFailsWithIncorrectValue()
    {
        $state_issued = $this->stateHandler->issue();
        $this->assertFalse($this->stateHandler->validate($state_issued.'false'));
        $this->assertNull($this->sessionStore->get(SessionStateHandler::STATE_NAME));
    }
}
