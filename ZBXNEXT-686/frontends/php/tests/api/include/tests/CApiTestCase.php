<?php

class CApiTestCase extends PHPUnit_Framework_TestCase {

	/**
	 * A utility object for working with the test database.
	 *
	 * @var CTestDatabase
	 */
	private static $database;

	/**
	 * A client for performing test API requests.
	 *
	 * @var CApiClient
	 */
	private static $api;

	/**
	 * Authentication token of the currently logged in user.
	 *
	 * @var string
	 */
	private $auth;

	/**
	 * @var CFixtureLoader
	 */
	private static $fixtureLoader;

	public static function setUpBeforeClass() {
		// set up the test database object
		self::$database = new CTestDatabase();

		// set up the API client
		self::$api = new CIncludeFileApiClient(new CJson(), API_TEST_DIR.'/../../api_jsonrpc.php');

		// set up the fixture loader
		$client = new CLocalApiClient(new CJson());
		$client->setServiceFactory(new CApiServiceFactory());

		self::$fixtureLoader = new CFixtureLoader(
			new CFixtureFactory(API_TEST_DIR.'/fixtures', new CApiWrapper($client)),
			new CArrayMacroResolver()
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
		$response = self::$api->callMethod('user.login', array(
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
	 * Truncate all tables in the database except for "dbversion".
	 */
	protected function clearDatabase() {
		self::$database->clear(array('dbversion'));
	}

	/**
	 * Load the given fixtures.
	 *
	 * @param array $fixtures
	 *
	 * @return array
	 *
	 * @throws Exception rethrows exceptions
	 */
	protected function loadFixtures(array $fixtures) {
		try {
			$fixtures = self::$fixtureLoader->load($fixtures);
		}
		catch (Exception $e) {
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
		return self::$api->requiresAuthentication($method);
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
	 * @return CApiResponse
	 */
	protected function call($method, $params, $auth = null, $id = null, $jsonRpc = '2.0') {
		if ($id === null) {
			$id = rand();
		}

		if ($auth === null && self::$api->requiresAuthentication($method)) {
			$auth = $this->getAuth();
		}

		return self::$api->callMethod($method, $params, $auth, $id, $jsonRpc);
	}

	/**
	 * Executes the given request and returns the result.
	 *
	 * This method should provide the ability to make invalid requests.
	 *
	 * @param array $request	a JSON RPC request
	 *
	 * @return CApiResponse
	 */
	protected function executeRequest(array $request) {
		return self::$api->callMethod(
			$request['method'], $request['params'], $request['auth'], $request['id'], $request['jsonrpc']
		);
	}

	/**
	 * Check that the body of a response matches the given schema.
	 *
	 * @param mixed 		$definition
	 * @param CApiResponse 	$response
	 * @param string 		$message
	 *
	 * @param CApiResponse $response
	 */
	protected function assertResponse($definition, CApiResponse $response, $message = 'Failed assertion: %1$s') {
		$this->assertArraySchema($definition, $response->getBody(), $message);
	}

	/**
	 * Check that the error data of a failed request matches the given schema.
	 *
	 * TODO: rewrite this method as a proper PHPunit assert using a constraint.
	 *
	 * @param mixed 		$definition
	 * @param CApiResponse 	$response
	 * @param string 		$message
	 *
	 * @throws Exception
	 */
	protected function assertError($definition, CApiResponse $response, $message = 'Failed assertion: %1$s') {
		if (!$response->isError()) {
			throw new Exception(sprintf($message, 'an error was expected but the request has been executed successfully'));
		}
		$this->assertArraySchema($definition, $response->getError(), $message);
	}

	/**
	 * Check that the result of a successful request matches the given schema.
	 *
	 * TODO: rewrite this method as a proper PHPunit assert using a constraint.
	 *
	 * @param mixed 		$definition
	 * @param CApiResponse 	$response
	 * @param string 		$message
	 *
	 * @throws Exception
	 */
	protected function assertResult($definition, CApiResponse $response, $message = 'Failed assertion: %1$s') {
		if ($response->isError()) {
			throw new Exception(sprintf($message,
				'request was expected to be executed successfully but an error occured: '.$response->getErrorData()
			));
		}
		$this->assertArraySchema($definition, $response->getResult(), $message);
	}

	/**
	 * Check that the array matches the given schema.
	 *
	 * TODO: rewrite this method as a proper PHPunit assert using a constraint.
	 *
	 * @param mixed 	$definition
	 * @param mixed 	$response
	 * @param string 	$message
	 *
	 * @throws Exception
	 */
	protected function assertArraySchema($definition, $response, $message = 'Failed assertion: %1$s') {
		$validator = new CTestSchemaValidator(array('schema' => $definition));
		if (!$validator->validate($response)) {
			throw new Exception(sprintf($message, $validator->getError()));
		}
	}

}
