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
	 * @var CApiWrapper
	 */
	private $api;

	private $auth;

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

		$this->api = new \CIncludeFileApiClient(new \CJson());

		$client = new \CLocalApiClient(new \CJson());
		$client->setServiceFactory(new \CApiServiceFactory());

		$this->fixtureLoader = new FixtureLoader(
			new FixtureFactory(new \CApiWrapper($client)),
			new \CArrayMacroResolver()
		);
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
	 * @param string $id
	 * @param string $jsonRpc
	 *
	 * @return \CApiResponse
	 */
	protected function callMethod($method, $params, $id = null, $jsonRpc = '2.0') {
		$auth = null;
		if ($this->api->requiresAuthentication($method)) {
			if ($this->auth === null) {
				// TODO: allow to log in as a different user
				$response = $this->api->callMethod('user.login', array(
					'user' => 'Admin',
					'password' => 'zabbix'
				));
				$this->auth = $response->getResult();
			}

			$auth = $this->auth;
		}

		return $this->api->callMethod($method, $params, $auth, $id, $jsonRpc);
	}

	protected function assertError(\CApiResponse $response, $message = '') {
		if ($message === '') {
			$message = 'Failed asserting that the response contains an error.';
		}

		return $this->assertTrue($response->isError(), $message);
	}

	protected function assertResult(\CApiResponse $response, $message = '') {
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
	 * @param \CApiResponse $response
	 *
	 * @throws \Exception
	 */
	protected function assertResponse($definition, \CApiResponse $response) {
		$validator = new \CTestSchemaValidator(array('schema' => $definition));
		if (!$validator->validate($response->getResponseData())) {
			throw new \Exception($validator->getError());
		}
	}

}
