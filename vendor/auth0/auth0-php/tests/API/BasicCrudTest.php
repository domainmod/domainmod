<?php

namespace Auth0\Tests\API;

/**
 * Class BasicCrudTest.
 *
 * @package Auth0\Tests\API
 */
abstract class BasicCrudTest extends ApiTests
{

    /**
     * Tenant domain for the test account.
     *
     * @var string
     */
    protected $domain;

    /**
     * API client to test.
     *
     * @var mixed
     */
    protected $api;

    /**
     * Name of the entity's ID.
     *
     * @var mixed
     */
    protected $id_name = 'id';

    /**
     * Random number used for unique testing names.
     *
     * @var integer
     */
    protected $rand;

    /**
     * Should all results be searched for the created entity?
     *
     * @var boolean
     */
    protected $findCreatedItem = true;

    /**
     * CRUD API client to test.
     *
     * @return mixed
     */
    abstract protected function getApiClient();

    /**
     * Data to use to create the test entity.
     *
     * @return array
     */
    abstract protected function getCreateBody();

    /**
     * Data to use to update the test entity.
     *
     * @return array
     */
    abstract protected function getUpdateBody();

    /**
     * Assertions for the created entity.
     *
     * @param array $created_entity Created entity.
     *
     * @return mixed
     */
    abstract protected function afterCreate(array $created_entity);

    /**
     * Assertions for the updated entity.
     *
     * @param array $updated_entity Updated entity.
     *
     * @return mixed
     */
    abstract protected function afterUpdate(array $updated_entity);

    /**
     * BasicCrudTest constructor.
     * Sets up environment and domain value.
     */
    public function __construct()
    {
        parent::__construct();
        self::$env    = $this->getEnv();
        $this->domain = self::$env['DOMAIN'];
        $this->api    = $this->getApiClient();
        $this->rand   = rand();
    }

    /**
     * Stub "get all entities" method.
     * Can be overridden by child classes for specific test cases.
     *
     * @return mixed
     */
    protected function getAllEntities()
    {
        return $this->api->getAll();
    }

    /**
     * Get the unique identifier for the entity.
     *
     * @param array $entity Entity array.
     *
     * @return mixed
     */
    protected function getId(array $entity)
    {
        return $entity[$this->id_name];
    }

    /**
     * Check that HTTP options have been set correctly.
     *
     * @return void
     */
    public function testHttpOptions()
    {
        $options = $this->api->getApiClient()->get()->getGuzzleOptions();
        $this->assertArrayHasKey('base_uri', $options);
        $this->assertEquals('https://'.$this->domain.'/api/v2/', $options['base_uri']);
    }

    /**
     * All basic CRUD test assertions.
     *
     * @return void
     */
    public function testAll()
    {
        // Test a generic "create entity" method for this API client.
        $created_entity = $this->api->create($this->getCreateBody());
        $this->afterCreate($created_entity);
        $created_entity_id = $this->getId($created_entity);

        // Test a generic "get entity" method.
        $got_entity = $this->api->get($created_entity_id);

        // Make sure what we got matches what we created.
        $this->afterCreate($got_entity);

        // Test a generic "get all entities" method for this API client.
        $all_entities = $this->getAllEntities($created_entity);

        // Look through our returned results for the created item, if indicated.
        if ($this->findCreatedItem && ! empty($all_entities)) {
            $found = false;
            foreach ($all_entities as $value) {
                if ($this->getId($value) === $created_entity_id) {
                    $found = true;
                    break;
                }
            }

            $this->assertTrue($found, 'Created item not found');
        }

        // Test a generic "update entity" method for this API client.
        $updated_entity = $this->api->update($created_entity_id, $this->getUpdateBody());
        $this->afterUpdate($updated_entity);

        // Test a generic "delete entity" method for this API client.
        $this->api->delete($created_entity_id);
    }
}
