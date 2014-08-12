<?php

namespace Zabbix\Test;

use Symfony\Component\Yaml\Yaml;
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
		$configFileName = __DIR__.'/../../../config/'.$this->config.'.ini';

		if (!is_readable($configFileName)) {
			throw new \Exception(sprintf('Can not find config file "%s" for config "%s"', $configFileName, $this->config));
		}

		$config = parse_ini_file($configFileName);

		$this->username = $config['username'];
		$this->password = $config['password'];

		$this->parsedConfig = $config;
	}

	public function getTestConfiguration()
	{
		return $this->parsedConfig;
	}

	/**
	 * @return APIGatewayInterface
	 */
	protected function getGateway() {
		$gateway = new FileAPIGateway();
		$gateway->configure($this->gatewayConfiguration, $this->parsedConfig);

		return $gateway;
	}

	protected function setUp() {
		$annotations = $this->getAnnotations();

		if (isset($annotations['method']['fixtures'])) {
			$fixtures = explode(' ', implode(' ', $annotations['method']['fixtures']));
			$fixtures = array_map(function ($value) {
				return trim($value);
			}, $fixtures);

			foreach ($fixtures as $file) {
				$this->loadDatabaseFixtures($file);
			}
		}
	}

	protected function loadDatabaseFixtures($file, $loaded = array())
	{
		if (in_array($file, $loaded)) {
			return;
		}

		$path = __DIR__ . '/../../../tests/fixtures/'.$file.'.yml';

		if (!is_readable($path)) {
			throw new \Exception(sprintf('Can not find fixture file "%s" (expected location "%s")', $file, $path));
		}

		$pdo = $this->getPdo();

		$fixtures = Yaml::parse(file_get_contents($path));

		// todo: validate here

		foreach ($fixtures as $suite => $data) {
			foreach ($data['require'] as $fixture) {
				$this->loadDatabaseFixtures($fixture, $loaded);
			}

			foreach ($data['cleanup'] as $table) {
				// todo: pre-define truncate order
				$pdo->query('DELETE FROM '.$table);
			}

			foreach ($data['rows'] as $table => $rows) {
				foreach ($rows as $objectName => $fields) {
					$query = 'INSERT INTO '.$table.' (';
					$query .= implode(', ', array_keys($fields));
					$query .= ') VALUES (';
					$query .= implode(', ', array_map(function ($value) {
						return ':'.$value;
					}, array_keys($fields)));
					$query .= ')';

					$query = $pdo->prepare($query);
					$query->execute($fields);
				}
			}

			$loaded[] = $file;
		}

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
