<?php

namespace Tests\Thamaraiselvam\MysqlImport;

use Exception;
use ReflectionMethod;
use ReflectionProperty;
use stdClass;
use Thamaraiselvam\MysqlImport\Import;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

class ImportTest extends TestCase
{
    /**
     * @dataProvider constructorSetVariablesDataProvider
     */
    public function testConstructorSetVariables(
        $expectedFilename,
        $expectedUsername,
        $expectedPassword,
        $expectedDatabase,
        $expectedHost
    )
    {
        // Creating mock, we expect
        // that constructor will call
        // connect and openfile methods
        $mock = $this->createPartialMock(
            Import::class,
            array('connect', 'openfile')
        );
        $mock->expects($this->once())->method('connect');
        $mock->expects($this->once())->method('openfile');

        // Call constructor
        $constructMethod = new ReflectionMethod(Import::class, '__construct');
        $constructMethod->invoke(
            $mock,
            $expectedFilename,
            $expectedUsername,
            $expectedPassword,
            $expectedDatabase,
            $expectedHost
        );

        // Getting properties
        $filenameProperty = new ReflectionProperty(Import::class, 'filename');
        $filenameProperty->setAccessible(true);
        $usernameProperty = new ReflectionProperty(Import::class, 'username');
        $usernameProperty->setAccessible(true);
        $passwordProperty = new ReflectionProperty(Import::class, 'password');
        $passwordProperty->setAccessible(true);
        $databaseProperty = new ReflectionProperty(Import::class, 'database');
        $databaseProperty->setAccessible(true);
        $hostProperty = new ReflectionProperty(Import::class, 'host');
        $hostProperty->setAccessible(true);

        // Assertions
        $this->assertEquals($expectedFilename, $filenameProperty->getValue($mock));
        $this->assertEquals($expectedUsername, $usernameProperty->getValue($mock));
        $this->assertEquals($expectedPassword, $passwordProperty->getValue($mock));
        $this->assertEquals($expectedDatabase, $databaseProperty->getValue($mock));
        $this->assertEquals($expectedHost, $hostProperty->getValue($mock));
    }

    public function constructorSetVariablesDataProvider()
    {
        return array(
            array('filename', 'username', 'password', 'database', 'host')
        );
    }

    public function testExceptionOnConnection()
    {
        // Creating mock for mysqli which will return an errno != 0
        $dbMock = new stdClass();
        $dbMock->connect_errno = 1;
        $dbMock->connect_error = 'test error message';

        // Now we reflect a connect method for later invoke
        // This is the only way to run protected method
        // from outside the Import class
        $connectMethod = new ReflectionMethod(Import::class, 'connect');
        $connectMethod->setAccessible(true);

        $mock = $this->getImportMock($dbMock);

        // Setting expected exception and invoke connect method
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to connect to MySQL: test error message');
        $connectMethod->invoke($mock);
    }

    public function testQuery()
    {
        // Creating mock for mysqli with
        // query method mocked
        $dbMock = $this->createPartialMock(
            stdClass::class,
            array('query')
        );
        $dbMock->connect_errno = 0;
        $dbMock->error = 'test db error message';

        // We expect query method to be called twice
        // we don't care about the input arguments
        // but we expect return true on first call
        // and false on second call
        $dbMock->expects($this->exactly(2))
               ->method('query')
               ->with($this->anything())
               ->willReturnOnConsecutiveCalls(true, false);

        $mock = $this->getImportMock($dbMock);

        // Running connect method to init db property
        $connectMethod = new ReflectionMethod(Import::class, 'connect');
        $connectMethod->setAccessible(true);
        $connectMethod->invoke($mock);

        $queryMethod = new ReflectionMethod(Import::class, 'query');
        $queryMethod->setAccessible(true);

        // First query will return true, so we expect null on return
        $this->assertNull($queryMethod->invoke($mock, 'First run'));

        // Second query will return false, exception expected
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Error with query: test db error message\n");
        $queryMethod->invoke($mock, 'Second run');
    }

    /**
     * TODO: Test text provided to query method?
     *
     * @dataProvider openfileDataProvider
     */
    public function testOpenfile($filepath, $queriesCount)
    {
        $mock = $this->getImportMockForOpenfile($queriesCount);
        // Call constructor
        $constructMethod = new ReflectionMethod(Import::class, '__construct');
        $constructMethod->invoke(
            $mock,
            $filepath,
            'does not matter',
            'does not matter',
            'does not matter',
            'does not matter'
        );
    }

    public function openfileDataProvider()
    {
        return array(
            array(dirname(__FILE__) . '/fixtures/sample.txt', 3)
        );
    }

    /**
     * @dataProvider openfileExceptionDataProvider
     */
    public function testOpenfileException($filepath, $expectedOutputMessage)
    {
        $mock = $this->getImportMockForOpenfile(0);
        // Call constructor
        $constructMethod = new ReflectionMethod(Import::class, '__construct');

        $this->expectOutputString($expectedOutputMessage);
        $constructMethod->invoke(
            $mock,
            $filepath,
            'does not matter',
            'does not matter',
            'does not matter',
            'does not matter'
        );
    }

    public function openfileExceptionDataProvider()
    {
        return array(
            array(dirname(__FILE__) . '/fixtures/not_existing_file', "Error importing: Error: File not found.\n\n")
        );
    }

    protected function getImportMockForOpenfile($queryCallTimes)
    {
        // Creating db mock, expecting query method
        // will be called n times and we expected
        // db connection close on finish
        $dbMock = $this->createPartialMock(
            stdClass::class,
            array('query', 'close')
        );
        $dbMock->connect_errno = 0;
        $dbMock->expects($queryCallTimes > 0 ? $this->exactly($queryCallTimes) : $this->never())
               ->method('query')
               ->with($this->anything())
               ->will($this->returnValue(true));

        $dbMock->expects($this->once())
               ->method('close');

        return $this->getImportMock($dbMock);
    }

    protected function getImportMock($dbMock)
    {
        // Creating Import mock and defining
        // createconnection method for later replace
        $mock = $this->createPartialMock(
            Import::class,
            array('createconnection')
        );

        // Replacing createconnection method and
        // defining a return value when called
        $mock->expects($this->once())
             ->method('createconnection')
             ->willReturn($dbMock);

        return $mock;
    }
}
