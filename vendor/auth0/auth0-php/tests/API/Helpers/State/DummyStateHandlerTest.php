<?php
namespace Auth0\Tests\Api\Helpers\State;

use Auth0\SDK\API\Helpers\State\DummyStateHandler;

/**
 * Class DummyStateHandlerTest
 *
 * @package Auth0\Tests\Api\Helpers\State
 */
class DummyStateHandlerTest extends \PHPUnit_Framework_TestCase
{

    /**
     * State handler to use.
     *
     * @var DummyStateHandler
     */
    private $state;

    /**
     * DummyStateHandlerTest constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->state = new DummyStateHandler();
    }

    /**
     * Test that the state issued is null.
     */
    public function testStateIssuedCorrectly()
    {
        $this->assertNull($this->state->issue());
    }

    /**
     * Test that state always validates to true.
     *
     * @throws \Exception
     */
    public function testStateValidatesCorrectly()
    {
        $this->assertTrue($this->state->validate(uniqid()));

        // Test again with a different value.
        $this->assertTrue($this->state->validate(uniqid()));
    }
}
