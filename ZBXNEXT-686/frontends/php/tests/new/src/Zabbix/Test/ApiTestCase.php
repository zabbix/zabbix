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
	private static $pdo;

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

		$configFileName = __DIR__.'/../../../config/'.$this->config.'.ini';

		if (!is_readable($configFileName)) {
			throw new \Exception(sprintf('Can not find config file "%s" for config "%s"', $configFileName, $this->config));
		}

		$this->parsedConfig = parse_ini_file($configFileName);

		$this->database = new TestDatabase($this->getPdo());
	}

	/**
	 * @return APIGatewayInterface
	 */
	protected function getGateway() {
		$gateway = new FileAPIGateway();
		$gateway->configure($this->gatewayConfiguration, $this->parsedConfig);

		return $gateway;
	}

	protected function tearDown() {
		$this->database->clear();
	}

	/**
	 * @return \PDO
	 */
	protected function getPdo()
	{
		if (!self::$pdo) {
			self::$pdo = new \PDO($this->parsedConfig['database_dsn'], $this->parsedConfig['database_user'],
				$this->parsedConfig['database_password']
			);

			self::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		}

		return self::$pdo;
	}

}
