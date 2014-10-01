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

	/**
	 * Authentication token of the currently logged in user.
	 *
	 * @var string
	 */
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
	 * Log the user in.
	 *
	 * @param string $user
	 * @param string $password
	 *
	 * @throws Exception
	 */
	protected function login($user, $password) {
		$response = $this->api->callMethod('user.login', array(
			'user' => $user,
			'password' => $password
		));

		if ($response->isError()) {
			throw new Exception(sprintf('Cannot authenticate user: %1$s', $response->getErrorData()));
		}

		$this->auth = $response->getResult();
	}

	/**
	 * Return the authentication token of the currently logged in user.
	 *
	 * @return string
	 */
	protected function getAuth() {
		return $this->auth;
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
	 * Returns true of the given method requires an authentication token in the request.
	 *
	 * @param string $method
	 *
	 * @return bool
	 */
	protected function requiresAuthentication($method) {
		return $this->api->requiresAuthentication($method);
	}

	/**
	 * Call an API method with the given parameters.
	 *
	 * @param string 		$method
	 * @param array	 		$params
	 * @param string|null 	$id			defaults to a random number
	 * @param string 		$jsonRpc	defaults to "2.0"
	 * @param string 		$auth		defaults to authentication token of the base user
	 *
	 * @return \CApiResponse
	 */
	protected function call($method, $params, $auth = null, $id = null, $jsonRpc = '2.0') {
		if ($id === null) {
			$id = rand();
		}

		if ($auth === null && $this->api->requiresAuthentication($method)) {
			$auth = $this->getAuth();
		}

		return $this->api->callMethod($method, $params, $auth, $id, $jsonRpc);
	}

	/**
	 * Executes the given request and returns the result.
	 *
	 * This method should provide the ability to make invalid requests.
	 *
	 * @param array $request	a JSON RPC request
	 *
	 * @return \CApiResponse
	 */
	protected function executeRequest(array $request) {
		return $this->api->callMethod(
			$request['method'], $request['params'], $request['auth'], $request['id'], $request['jsonrpc']
		);
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
