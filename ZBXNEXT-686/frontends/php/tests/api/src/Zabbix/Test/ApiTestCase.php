<?php

namespace Zabbix\Test;

use Zabbix\Test\APIGateway\APIGatewayInterface;
use Zabbix\Test\APIGateway\FileAPIGateway;
use Zabbix\Test\Fixtures\FixtureFactory;
use Zabbix\Test\Fixtures\FixtureLoader;

class ApiTestCase extends \PHPUnit_Framework_TestCase {

	/**
	 * Current gateway config
	 *
	 * @var array
	 */
	protected $gatewayConfiguration = array();

	/**
	 * PDO instance
	 *
	 * @var \PDO
	 */
	private $pdo;

	/**
	 * @var TestDatabase
	 */
	private $database;

	/**
	 * @var APIGateway\FileAPIGateway
	 */
	private $gateway;

	/**
	 * @var FixtureLoader
	 */
	private $fixtureLoader;

	/**
	 * Current settings file name
	 *
	 * @var string
	 */
	protected $config = 'settings';

	/**
	 * Parsed config file as defined by $config above
	 *
	 * @var array
	 */
	protected $parsedConfig;

	public function __construct($name = null, array $data = array(), $dataName = '') {
		parent::__construct($name, $data, $dataName);

		// TODO: move all of this to setUpBeforeClass()

		$this->database = new TestDatabase();

		// TODO: use an API client instead of the gateway
		$this->gateway = new FileAPIGateway();
		// TODO: make the user name and password configurable
		$this->gateway->configure($this->gatewayConfiguration, array(
			'username' => 'Admin',
			'password' => 'zabbix'
		));

		$client = new \CLocalApiClient();
		$client->setServiceFactory(new \CApiServiceFactory());

		$this->fixtureLoader = new FixtureLoader(
			new FixtureFactory(new \CApiWrapper($client)),
			new \CArrayMacroResolver()
		);
	}

	/**
	 * @return APIGatewayInterface
	 */
	protected function getGateway() {
		return $this->gateway;
	}

	protected function tearDown() {
		$this->clearDatabase();
	}

	/**
	 * @return \PDO
	 */
	protected function getPdo() {
		return $this->pdo;
	}

	protected function clearDatabase() {
		$this->database->clear();
	}

	protected function loadFixtures(array $fixtures) {
		try {
			$fixtures = $this->fixtureLoader->load($fixtures);
		}
		catch (\Exception $e) {
			$this->clearDatabase();

			throw $e;
		}

		return $fixtures;
	}

	/**
	 * @param $method
	 * @param array $params
	 *
	 * @return APITestResponse
	 */
	protected function callMethod($method, array $params) {
		$request = array(
			'jsonrpc' => '2.0',
			'id' => rand(),
			'params' => $params,
			'method' => $method
		);

		$apiRequest = new APITestRequest($request['method'], $request['params'], $request['id'], $request);

		return $this->gateway->execute($apiRequest);
	}

	protected function assertError(APITestResponse $response, $message = '') {
		if ($message === '') {
			$message = 'Failed asserting that the response contains an error.';
		}

		return $this->assertTrue($response->isError(), $message);
	}

	protected function assertResult(APITestResponse $response, $message = '') {
		if ($message === '') {
			$message = 'Failed asserting that the response contains a result.';
		}

		return $this->assertFalse($response->isError(), $message);
	}

	/**
	 * Validate data according to rules; path holds current validator chain
	 *
	 * TODO: rewrite this method as a proper PHPunit assert using a constraint.
	 *
	 * @param $definition
	 * @param APITestResponse $response
	 *
	 * @throws \Exception
	 */
	protected function assertResponse($definition, APITestResponse $response) {
		$validator = new \CTestSchemaValidator(array('schema' => $definition));
		if (!$validator->validate($response->getResponseData())) {
			throw new \Exception($validator->getError());
		}
	}

}
