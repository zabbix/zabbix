<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once 'vendor/autoload.php';

require_once dirname(__FILE__).'/CAPITest.php';
require_once dirname(__FILE__).'/CZabbixClient.php';
require_once dirname(__FILE__).'/helpers/CLogHelper.php';

/**
 * Base class for integration tests.
 */
class CIntegrationTest extends CAPITest {

	// Default iteration count for wait operations.
	const WAIT_ITERATIONS			= 60;

	// Default delays (in seconds):
	const WAIT_ITERATION_DELAY			= 1; // Wait iteration delay.
	const CACHE_RELOAD_DELAY			= 5; // Configuration cache reload delay.
	const HOUSEKEEPER_EXEC_DELAY	= 5; // Housekeeper execution delay.
	const DATA_PROCESSING_DELAY		= 5; // Data processing delay.

	// Zabbix component constants.
	const COMPONENT_SERVER	= 'server';
	const COMPONENT_PROXY	= 'proxy';
	const COMPONENT_AGENT	= 'agentd';
	const COMPONENT_AGENT2	= 'agent2';

	// Zabbix component port constants.
	const AGENT_PORT_SUFFIX = '50';
	const SERVER_PORT_SUFFIX = '51';
	const PROXY_PORT_SUFFIX = '52';
	const AGENT2_PORT_SUFFIX = '53';

	/**
	 * Components required by test suite.
	 *
	 * @var array
	 */
	private static $suite_components = [];

	/**
	 * Hosts to be enabled for test suite.
	 *
	 * @var array
	 */
	private static $suite_hosts = [];

	/**
	 * Configuration provider for test suite.
	 *
	 * @var array
	 */
	private static $suite_configuration = [];

	/**
	 * Components required by test case.
	 *
	 * @var array
	 */
	private $case_components = [];

	/**
	 * Hosts to be enabled for test case.
	 *
	 * @var array
	 */
	private $case_hosts = [];

	/**
	 * Configuration provider for test case.
	 *
	 * @var array
	 */
	private static $case_configuration = [];

	/**
	 * Process annotations defined on suite / case level.
	 *
	 * @param string $type    annotation type ('class' or 'method')
	 *
	 * @throws Exception    on invalid configuration provider
	 */
	protected function processAnnotations($type) {
		$annotations = $this->getAnnotationsByType($this->annotations, $type);
		$result = [
			'components'	=> [],
			'hosts'			=> [],
			'configuration'	=> []
		];

		// Get required components.
		foreach ($this->getAnnotationTokensByName($annotations, 'required-components') as $component) {
			if ($component === 'agent') {
				$component = self::COMPONENT_AGENT;
			}

			self::validateComponent($component);
			$result['components'][$component] = true;
		}

		$result['components'] = array_keys($result['components']);

		// Get hosts to enable.
		foreach ($this->getAnnotationTokensByName($annotations, 'hosts') as $host) {
			$result['hosts'][$host] = true;
		}

		$result['hosts'] = array_keys($result['hosts']);

		// Get configuration from configuration data provider.
		foreach ($this->getAnnotationTokensByName($annotations, 'configurationDataProvider') as $provider) {
			if (!method_exists($this, $provider) || !is_array($config = call_user_func([$this, $provider]))) {
				throw new Exception('Configuration data provider "'.$provider.'" is not valid.');
			}

			$result['configuration'] = array_merge($result['configuration'], $config);
		}

		return $result;
	}

	/**
	 * Set status for hosts.
	 *
	 * @param array   $hosts     array of hostids or host names
	 * @param integer $status    status to be set
	 */
	protected static function setHostStatus($hosts, $status) {
		if (is_scalar($hosts)) {
			$hosts = [$hosts];
		}

		if ($hosts && is_array($hosts)) {
			$filters = [];
			$criteria = [];

			foreach ($hosts as $host) {
				$filters[(is_numeric($host) ? 'hostid' : 'host')][] = zbx_dbstr($host);
			}

			foreach ($filters as $key => $values) {
				$criteria[] = $key.' in ('.implode(',', $values).')';
			}

			DBexecute('UPDATE hosts SET status='.zbx_dbstr($status).' WHERE '.implode(' OR ', $criteria));
		}
	}

	/**
	 * @inheritdoc
	 */
	protected function onBeforeTestSuite() {
		parent::onBeforeTestSuite();

		$result = $this->processAnnotations('class');
		self::$suite_components = $result['components'];
		self::$suite_hosts = $result['hosts'];
		self::$suite_configuration = self::getDefaultComponentConfiguration();

		foreach (self::getComponents() as $component) {
			if (!array_key_exists($component, $result['configuration'])) {
				continue;
			}

			self::$suite_configuration[$component]
				= array_merge(self::$suite_configuration[$component], $result['configuration'][$component]);
		}

		try {
			if ($this->prepareData() === false) {
				throw new Exception('Failed to prepare data for test suite.');
			}
		} catch (Exception $exception) {
			self::markTestSuiteSkipped();
			throw $exception;
		}

		self::setHostStatus(self::$suite_hosts, HOST_STATUS_MONITORED);
	}

	/**
	 * Prepare data for test suite.
	 */
	public function prepareData() {
		// Code is not missing here.

		return true;
	}

	/**
	 * Callback executed before every test case.
	 *
	 * @before
	 */
	public function onBeforeTestCase() {
		parent::onBeforeTestCase();

		$result = $this->processAnnotations('method');
		$this->case_components = array_diff($result['components'], self::$suite_components);
		$this->case_hosts = array_diff($result['hosts'], self::$suite_hosts);
		self::$case_configuration = self::$suite_configuration;

		foreach (self::getComponents() as $component) {
			if (!array_key_exists($component, $result['configuration'])) {
				continue;
			}

			self::$case_configuration[$component] = array_merge(self::$case_configuration[$component],
				$result['configuration'][$component]
			);
		}

		self::setHostStatus($this->case_hosts, HOST_STATUS_MONITORED);

		foreach ($this->case_components as $component) {
			if (in_array($component, self::$suite_components)) {
				throw new Exception('Component "'.$component.'" already started on suite level.');
			}
		}

		$components = array_merge(self::$suite_components, $this->case_components);

		foreach ($components as $component) {
			self::prepareComponentConfiguration($component, self::$case_configuration);
			self::startComponent($component);
		}
	}

	/**
	 * Callback executed after every test case.
	 *
	 * @after
	 */
	public function onAfterTestCase() {
		$components = array_merge(self::$suite_components, $this->case_components);

		foreach ($components as $component) {
			try {
				self::stopComponent($component);
			}
			catch (Exception $e) {
				self::zbxAddWarning($e->getMessage());
			}
		}

		self::setHostStatus($this->case_hosts, HOST_STATUS_NOT_MONITORED);

		parent::onAfterTestCase();

		self::$case_configuration = [];
	}

	/**
	 * Callback executed after every test suite.
	 *
	 * @afterClass
	 */
	public static function onAfterTestSuite() {
		foreach (self::$suite_components as $component) {
			try {
				self::stopComponent($component);
			}
			catch (Exception $e) {
				self::zbxAddWarning($e->getMessage());
			}
		}

		if (self::$suite_hosts) {
			global $DB;
			DBconnect($error);
			self::setHostStatus(self::$suite_hosts, HOST_STATUS_NOT_MONITORED);
			DBclose();
		}

		parent::onAfterTestSuite();
	}

	/**
	 * Get list of possible component names.
	 *
	 * @return array
	 */
	private static function getComponents() {
		return [
			self::COMPONENT_SERVER, self::COMPONENT_PROXY, self::COMPONENT_AGENT, self::COMPONENT_AGENT2
		];
	}

	/**
	 * Validate component name.
	 *
	 * @param string $component    component name to be validated.
	 *
	 * @throws Exception    on invalid component name
	 */
	private static function validateComponent($component) {
		if (!in_array($component, self::getComponents())) {
			throw new Exception('Unknown component name "'.$component.'".');
		}
	}

	/**
	 * Wait for component to start.
	 *
	 * @param string $component    component name
	 *
	 * @throws Exception    on failed wait operation
	 */
	protected static function waitForStartup($component) {
		self::validateComponent($component);

		for ($r = 0; $r < self::WAIT_ITERATIONS; $r++) {
			$pid = @file_get_contents(self::getPidPath($component));
			if ($pid && is_numeric($pid) && posix_kill($pid, 0)) {
				switch ($component) {
					case self::COMPONENT_SERVER:
					case self::COMPONENT_PROXY:
						self::waitForLogLineToBePresent($component, 'started [trapper #1]', false, 5, 1);
						break;

					case self::COMPONENT_AGENT:
						self::waitForLogLineToBePresent($component, 'started [listener #1]', false, 5, 1);
						break;

					case self::COMPONENT_AGENT2:
						self::waitForLogLineToBePresent($component, 'using configuration file', false, 5, 1);
						break;
				}
				return;
			}

			sleep(self::WAIT_ITERATION_DELAY);
		}

		throw new Exception('Failed to wait for component "'.$component.'" to start.');
	}

	/**
	 * Wait for component to stop.
	 *
	 * @param string $component    component name
	 *
	 * @throws Exception    on failed wait operation
	 */
	protected static function waitForShutdown($component) {
		self::validateComponent($component);

		for ($r = 0; $r < self::WAIT_ITERATIONS; $r++) {
			if (!file_exists(self::getPidPath($component))) {
				return;
			}

			sleep(self::WAIT_ITERATION_DELAY);
		}

		throw new Exception('Failed to wait for component "'.$component.'" to stop.');
	}

	/**
	 * Execute command and the execution result.
	 *
	 * @param string $command     command to be executed
	 * @param array  $params      parameters to be passed
	 * @param bool   $background  run command in background
	 *
	 * @return string
	 *
	 * @throws Exception    on execution error
	 */
	private static function executeCommand(string $command, array $params, bool $background = false) {
		if ($params) {
			foreach ($params as &$param) {
				$param = escapeshellarg($param);
			}
			unset($param);

			$params = ' '.implode(' ', $params);
		}
		else {
			$params = '';
		}

		$command .= $params.($background ? ' > /dev/null 2>&1 &' : ' 2>&1');

		exec($command, $output, $return);

		if ($return !== 0) {
			$output = $output ? "\n".'Output:'."\n".implode("\n", $output) : '';
			throw new Exception('Failed to execute command "'.$command.'".'.$output);
		}

		return $output;
	}

	/**
	 * Get default configuration of components.
	 *
	 * @return array
	 */
	protected static function getDefaultComponentConfiguration() {
		global $DB;

		$db = [
			'DBName' => $DB['DATABASE'],
			'DBUser' => $DB['USER'],
			'DBPassword' => $DB['PASSWORD']
		];

		if ($DB['SERVER'] !== 'localhost' && $DB['SERVER'] !== '127.0.0.1') {
			$db['DBHost'] = $DB['SERVER'];
		}

		if ($DB['PORT'] != 0) {
			$db['DBPort'] = $DB['PORT'];
		}

		if ($DB['SCHEMA']) {
			$db['DBSchema'] = $DB['SCHEMA'];
		}

		$configuration = [
			self::COMPONENT_SERVER => array_merge($db, [
				'LogFile' => PHPUNIT_COMPONENT_DIR.'zabbix_server.log',
				'PidFile' => PHPUNIT_COMPONENT_DIR.'zabbix_server.pid',
				'SocketDir' => PHPUNIT_COMPONENT_DIR,
				'ListenPort' => PHPUNIT_PORT_PREFIX.self::SERVER_PORT_SUFFIX
			]),
			self::COMPONENT_PROXY => array_merge($db, [
				'LogFile' => PHPUNIT_COMPONENT_DIR.'zabbix_proxy.log',
				'PidFile' => PHPUNIT_COMPONENT_DIR.'zabbix_proxy.pid',
				'SocketDir' => PHPUNIT_COMPONENT_DIR,
				'ListenPort' => PHPUNIT_PORT_PREFIX.self::PROXY_PORT_SUFFIX
			]),
			self::COMPONENT_AGENT => [
				'LogFile' => PHPUNIT_COMPONENT_DIR.'zabbix_agent.log',
				'PidFile' => PHPUNIT_COMPONENT_DIR.'zabbix_agent.pid',
				'ListenPort' => PHPUNIT_PORT_PREFIX.self::AGENT_PORT_SUFFIX
			],
			self::COMPONENT_AGENT2 => [
				'LogFile' => PHPUNIT_COMPONENT_DIR.'zabbix_agent2.log',
				'PidFile' => PHPUNIT_COMPONENT_DIR.'zabbix_agent2.pid',
				'ControlSocket' => PHPUNIT_COMPONENT_DIR.'zabbix_agent2.sock',
				'ListenPort' => PHPUNIT_PORT_PREFIX.self::AGENT2_PORT_SUFFIX
			]
		];

		$configuration[self::COMPONENT_PROXY]['DBName'] .= '_proxy';

		return $configuration;
	}

	/**
	 * Create configuration file for component.
	 *
	 * @param string $component    component name
	 * @param array  $values       configuration array
	 *
	 * @throws Exception    on failed configuration file write
	 */
	protected static function prepareComponentConfiguration($component, $values) {
		self::validateComponent($component);

		$path = PHPUNIT_CONFIG_SOURCE_DIR.'zabbix_'.$component.'.conf';
		if (!file_exists($path) || ($config = @file_get_contents($path)) === false) {
			throw new Exception('There is no configuration file for component "'.$component.'": '.$path.'.');
		}

		if (array_key_exists($component, $values) && $values[$component] && is_array($values[$component])) {
			foreach ($values[$component] as $key => $value) {
				$config = preg_replace('/^(\s*'.$key.'\s*=.*)$/m', '#\1', $config);
				foreach ((array) $value as $val) {
					$config .= "\n".$key.'='.$val;
				}
			}
		}

		if (file_put_contents(PHPUNIT_CONFIG_DIR.'zabbix_'.$component.'.conf', $config) === false) {
			throw new Exception('Failed to create configuration file for component "'.$component.'": '.
					PHPUNIT_CONFIG_DIR.'zabbix_'.$component.'.conf.'
			);
		}
	}

	/**
	 * Start component.
	 *
	 * @param string $component    component name
	 *
	 * @throws Exception    on missing configuration or failed start
	 */
	protected function startComponent($component) {
		self::validateComponent($component);

		$config = PHPUNIT_CONFIG_DIR.'zabbix_'.$component.'.conf';
		if (!file_exists($config)) {
			throw new Exception('There is no configuration file for component "'.$component.'".');
		}

		self::clearLog($component);
		$background = ($component === self::COMPONENT_AGENT2);
		self::executeCommand(PHPUNIT_BINARY_DIR.'zabbix_'.$component, ['-c', $config], $background);
		self::waitForStartup($component);
	}

	/**
	 * Stop component.
	 *
	 * @param string $component    component name
	 *
	 * @throws Exception    on missing configuration or failed stop
	 */
	protected static function stopComponent($component) {
		self::validateComponent($component);

		$pid = @file_get_contents(self::getPidPath($component));
		if ($pid && is_numeric($pid)) {
			posix_kill($pid, SIGTERM);
		}
		self::waitForShutdown($component);
	}

	/**
	 * Get client for component.
	 *
	 * @param string $component    component name
	 *
	 * @throws Exception    on invalid component type
	 */
	protected function getClient($component) {
		self::validateComponent($component);

		if ($component === self::COMPONENT_AGENT || $component === self::COMPONENT_AGENT2) {
			throw new Exception('There is no client available for Zabbix Agent.');
		}

		return new CZabbixClient('localhost', self::getConfigurationValue($component, 'ListenPort', 10051),
				ZBX_SOCKET_TIMEOUT, ZBX_SOCKET_BYTES_LIMIT
		);
	}

	/**
	 * Get name of active component used in test.
	 *
	 * @return string
	 */
	protected function getActiveComponent() {
		$components = [];
		foreach (array_merge(self::$suite_components, $this->case_components) as $component) {
			if ($component !== self::COMPONENT_AGENT && $component !== self::COMPONENT_AGENT2) {
				$components[] = $component;
			}
		}

		if (count($components) === 1) {
			return $components[0];
		}
		else {
			return self::COMPONENT_SERVER;
		}
	}

	/**
	 * Send value for items to server.
	 *
	 * @param string $type         data type
	 * @param array  $values       item values
	 * @param string $component    component name or null for active component
	 *
	 * @return array    processing result
	 */
	protected function sendDataValues($type, $values, $component = null) {
		if ($component === null) {
			$component = $this->getActiveComponent();
		}

		$result = $this->getClient($component)->sendDataValues($type, $values);

		// Check that discovery data was sent.
		$this->assertTrue(array_key_exists('processed', $result), 'Result doesn\'t contain "processed" count.');
		$this->assertEquals(count($values), $result['processed'],
				'Processed value count doesn\'t match sent value count.'
		);

		sleep(self::DATA_PROCESSING_DELAY);

		return $result;
	}

	/**
	 * Send single item value.
	 *
	 * @param string $type         data type
	 * @param string $host         host name
	 * @param string $key          item key
	 * @param mixed  $value        item value
	 * @param string $component    component name or null for active component
	 *
	 * @return array    processing result
	 */
	protected function sendDataValue($type, $host, $key, $value, $component = null) {
		if (!is_scalar($value)) {
			$value = json_encode($value);
		}

		$data = [
			'host' => $host,
			'key' => $key,
			'value' => $value
		];

		return $this->sendDataValues($type, [$data], $component);
	}

	/**
	 * Send values to trapper items.
	 *
	 * @param array  $values       item values
	 * @param string $component    component name or null for active component
	 *
	 * @return array    processing result
	 */
	protected function sendSenderValues($values, $component = null) {
		return $this->sendDataValues('sender', $values, $component);
	}

	/**
	 * Send single value for trapper item.
	 *
	 * @param string $host         host name
	 * @param string $key          item key
	 * @param mixed  $value        item value
	 * @param string $component    component name or null for active component
	 *
	 * @return array    processing result
	 */
	protected function sendSenderValue($host, $key, $value, $component = null) {
		return $this->sendDataValue('sender', $host, $key, $value, $component);
	}

	/**
	 * Send values to active agent items.
	 *
	 * @param array  $values       item values
	 * @param string $component    component name or null for active component
	 *
	 * @return array    processing result
	 */
	protected function sendAgentValues($values, $component = null) {
		return $this->sendDataValues('agent', $values, $component);
	}

	/**
	 * Send single value for active agent item.
	 *
	 * @param string $host         host name
	 * @param string $key          item key
	 * @param mixed  $value        item value
	 * @param string $component    component name or null for active component
	 *
	 * @return array    processing result
	 */
	protected function sendAgentValue($host, $key, $value, $component = null) {
		return $this->sendDataValue('agent', $host, $key, $value, $component);
	}

	/**
	 * Get list of active checks for host.
	 *
	 * @param string $host         host name
	 * @param string $component    component name or null for active component
	 *
	 * @return array
	 */
	protected function getActiveAgentChecks($host, $component = null) {
		if ($component === null) {
			$component = $this->getActiveComponent();
		}

		$client = $this->getClient($component);
		$checks = $client->getActiveChecks($host);

		if (!is_array($checks)) {
			$this->fail('Cannot retrieve active checks for host "'.$host.'": '.$client->getError().'.');
		}

		return $checks;
	}

	/**
	 * Reload configuration cache.
	 *
	 * @param string $component    component name or null for active component
	 */
	protected function reloadConfigurationCache($component = null) {
		if ($component === null) {
			$component = $this->getActiveComponent();
		}

		self::executeCommand(PHPUNIT_BINARY_DIR.'zabbix_'.$component, ['--runtime-control', 'config_cache_reload']);

		sleep(self::CACHE_RELOAD_DELAY);
	}

	/**
	 * @param string $component    component name or null for active component
	 */
	protected function executeHousekeeper($component = null) {
		if ($component === null) {
			$component = $this->getActiveComponent();
		}

		self::executeCommand(PHPUNIT_BINARY_DIR.'zabbix_'.$component, ['--runtime-control', 'housekeeper_execute']);

		sleep(self::HOUSEKEEPER_EXEC_DELAY);
	}

	/**
	 * Request data from API until data is present (@see call).
	 *
	 * @param string   $method        API method to be called
	 * @param mixed    $params        API call params
	 * @param integer  $iterations    iteration count
	 * @param integer  $delay         iteration delay
	 * @param callable $callback      Callback function to test if API response is valid.
	 *
	 * @return array
	 */
	public function callUntilDataIsPresent($method, $params, $iterations = null, $delay = null, $callback = null) {
		if ($iterations === null) {
			$iterations = self::WAIT_ITERATIONS;
		}

		if ($delay === null) {
			$delay = self::WAIT_ITERATION_DELAY;
		}

		$exception = null;
		for ($i = 0; $i < $iterations; $i++) {
			try {
				$response = $this->call($method, $params);

				if (is_array($response['result']) && count($response['result']) > 0
						&& ($callback === null || call_user_func($callback, $response))) {
					return $response;
				}
			} catch (Exception $e) {
				$exception = $e;
			}

			sleep($delay);
		}

		if ($exception !== null) {
			throw $exception;
		}

		$this->fail('Data requested from '.$method.' API is not present within specified interval. Params used:'.
				"\n".json_encode($params)
		);
	}

	/**
	 * Get path of the log file for component.
	 *
	 * @param string $component    name of the component
	 *
	 * @return string
	 */
	protected static function getLogPath($component) {
		self::validateComponent($component);

		return self::getConfigurationValue($component, 'LogFile', '/tmp/zabbix_'.$component.'.log');
	}

	/**
	 * Get path of the pid file for component.
	 *
	 * @param string $component    name of the component
	 *
	 * @return string
	 */
	protected static function getPidPath($component) {
		self::validateComponent($component);

		return self::getConfigurationValue($component, 'PidFile', '/tmp/zabbix_'.$component.'.log');
	}

	/**
	 * Get current configuration value.
	 *
	 * @param string $component    name of the component
	 * @param string $key          name of the configuration parameter
	 * @param mixed  $default      default value
	 *
	 * @return mixed
	 */
	protected static function getConfigurationValue($component, $key, $default = null) {
		$configuration = (self::$case_configuration) ? self::$case_configuration : self::$suite_configuration;

		if (array_key_exists($component, $configuration) && array_key_exists($key, $configuration[$component])) {
			return $configuration[$component][$key];
		}

		return $default;
	}

	/**
	 * Clear contents of log.
	 *
	 * @param string $component    name of the component
	 */
	protected static function clearLog($component) {
		CLogHelper::clearLog(self::getLogPath($component));
	}

	/**
	 * Check if line is present.
	 *
	 * @param string       $component     name of the component
	 * @param string|array $lines         line(s) to look for
	 * @param boolean      $incremental   flag to be used to enable incremental read
	 *
	 * @return boolean
	 */
	protected static function isLogLinePresent($component, $lines, $incremental = true) {
		return CLogHelper::isLogLinePresent(self::getLogPath($component), $lines, $incremental);
	}

	/**
	 * Wait until line is present in log.
	 *
	 * @param string       $component     name of the component
	 * @param string|array $lines         line(s) to look for
	 * @param boolean      $incremental   flag to be used to enable incremental read
	 * @param integer      $iterations    iteration count
	 * @param integer      $delay         iteration delay
	 *
	 * @throws Exception    on failed wait operation
	 */
	protected static function waitForLogLineToBePresent($component, $lines, $incremental = true, $iterations = null, $delay = null) {
		if ($iterations === null) {
			$iterations = self::WAIT_ITERATIONS;
		}

		if ($delay === null) {
			$delay = self::WAIT_ITERATION_DELAY;
		}

		for ($r = 0; $r < $iterations; $r++) {
			if (self::isLogLinePresent($component, $lines, $incremental)) {
				return;
			}

			sleep($delay);
		}

		if (is_array($lines)) {
			$quoted = [];
			foreach ($lines as $line) {
				$quoted[] = '"'.$line.'"';
			}

			$description = 'any of the lines ['.implode(', ', $quoted).']';
		}
		else {
			$description = 'line "'.$lines.'"';
		}

		throw new Exception('Failed to wait for '.$description.' to be present in '.$component.' log file.');
	}
}
