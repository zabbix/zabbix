<?php

namespace Zabbix\Test;

use Zabbix\Test\APIGateway\APIGatewayInterface;
use Zabbix\Test\APIGateway\FileAPIGateway;

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

		$configFile = new \CConfigFile(__DIR__.'/../../../../../conf/zabbix.conf.php');
		$config = $configFile->load();

		$this->pdo = new \PDO('mysql:host='.$config['DB']['SERVER'].';dbname='.$config['DB']['DATABASE'], $config['DB']['USER'],
			$config['DB']['PASSWORD']
		);
		$this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

		$this->database = new TestDatabase($this->getPdo());
	}

	/**
	 * @return APIGatewayInterface
	 */
	protected function getGateway() {
		$gateway = new FileAPIGateway();
		// TODO: make the user name and password configurable
		$gateway->configure($this->gatewayConfiguration, array(
			'username' => 'Admin',
			'password' => 'zabbix'
		));

		return $gateway;
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

}
