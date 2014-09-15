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
	protected $database;

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
		$this->database->clear();
	}

	/**
	 * @return \PDO
	 */
	protected function getPdo() {
		return $this->pdo;
	}

	/**
	 * @return FixtureLoader
	 */
	protected function getFixtureLoader() {
		return $this->fixtureLoader;
	}

}
