<?php
/*
** Copyright (C) 2001-2025 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

require_once dirname(__FILE__).'/../include/CIntegrationTest.php';

/**
 * Test variables in configuration files
 *
 * @onBefore prepareTestEnv
 * @onAfter cleanupTestEnv
 *
 */
class testConfigVariables extends CIntegrationTest {
	const SERVER_NAME = 'server';
	const PROXY_NAME = 'proxy';
	const STATS_ITEM_NAME = 'stats item';
	const STATS_ITEM_KEY = 'zabbix[stats,,]';
	const START_POLLERS = 12;

	const VALID_NAMES = [
		'var',
		'var123',
		'_var123',
		'a',
		'_'
	];

	const INVALID_USER_PARAMS = [
		'${123var}', // variable name starts with a digit
		'${var-123}', // variable name contains a hyphen
		'${var 123}', // variable name contains a space
		'${EnvVar', // unclosed variable name
		'echo ${EnvVar}', // variable mixed with text
		'${EnvVar1}${EnvVar2}', // multiple variables
	];

	private static $include_files = [
		self::COMPONENT_AGENT => PHPUNIT_CONFIG_DIR . self::COMPONENT_AGENT . '_usrprm_with_vars.conf',
		self::COMPONENT_AGENT2 => PHPUNIT_CONFIG_DIR . self::COMPONENT_AGENT2 . '_usrprm_with_vars.conf'
	];

	private static $proxyids = [];
	private static $hostids = [];
	private static $itemids = [];
	private static $envvars = [];

	private static function setEnv($name, $value) {
		putenv($name . '=' . $value);
		self::$envvars[] = $name;
	}

	public static function prepareTestEnv(): void {
		self::setEnv('StartPollers', self::START_POLLERS);
	}

	public static function cleanupTestEnv(): void {
		CDataHelper::call('history.clear', self::$itemids);
		CDataHelper::call('item.delete', self::$itemids);
		CDataHelper::call('host.delete', self::$hostids);
		CDataHelper::call('proxy.delete', self::$proxyids);

		foreach (self::$include_files as $file) {
			if (file_exists($file)) {
				unlink($file);
			}
		}
		foreach (self::$envvars as $var) {
			putenv($var);
		}
	}

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Create proxy
		CDataHelper::call('proxy.create', [
			'name' => self::PROXY_NAME,
			'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
		]);

		self::$proxyids = CDataHelper::getIds('name');

		// Create hosts for monitoring server and proxy using internal checks
		$interfaces = [
			'type' => 1,
			'main' => 1,
			'useip' => 1,
			'ip' => '127.0.0.1',
			'dns' => '',
			'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
		];

		$groups = ['groupid' => 4];

		$result = CDataHelper::createHosts([
			[
				'host' => self::SERVER_NAME,
				'interfaces' => $interfaces,
				'groups' => $groups,
				'monitored_by' => ZBX_MONITORED_BY_SERVER,
				'status' => HOST_STATUS_MONITORED,
				'items' => [
					[
						'name' => self::STATS_ITEM_NAME,
						'key_' => self::STATS_ITEM_KEY,
						'type' => ITEM_TYPE_INTERNAL,
						'value_type' => ITEM_VALUE_TYPE_TEXT,
						'delay' => '1s'
					]
				]
			],
			[
				'host' => self::PROXY_NAME,
				'interfaces' => $interfaces,
				'groups' => $groups,
				'monitored_by' => ZBX_MONITORED_BY_PROXY,
				'proxyid' => self::$proxyids[self::PROXY_NAME],
				'status' => HOST_STATUS_MONITORED,
				'items' => [
					[
						'name' => self::STATS_ITEM_NAME,
						'key_' => self::STATS_ITEM_KEY,
						'type' => ITEM_TYPE_INTERNAL,
						'value_type' => ITEM_VALUE_TYPE_TEXT,
						'delay' => '1s'
					]
				]
			]
		]);

		self::$hostids = $result['hostids'];
		self::$itemids = $result['itemids'];

		return true;
	}

	/**
	 * Component configuration provider.
	 *
	 * @return array
	 */
	public function configurationProviderWorkerCount() {
		return [
			self::COMPONENT_SERVER => [
				'StartPollers' => '${StartPollers}'
			],
			self::COMPONENT_PROXY => [
				'Hostname' => self::PROXY_NAME,
				'Server' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'StartPollers' => '${StartPollers}'
			]
		];
	}

	/**
	 * Check the number of pollers set with variable in configuration file
	 *
	 * @configurationDataProvider configurationProviderWorkerCount
	 * @required-components server, proxy
	 */
	public function testConfigTestOption_WorkerCount() {
		foreach([self::SERVER_NAME, self::PROXY_NAME] as $component) {
			$maxAttempts = 10;
			$attempt = 0;
			$success = false;

			while ($attempt < $maxAttempts && !$success) {
				$attempt++;

				$response = $this->callUntilDataIsPresent('history.get', [
					'itemids' => self::$itemids[$component . ':' . self::STATS_ITEM_KEY],
					'history' => ITEM_VALUE_TYPE_TEXT,
					"sortfield" => "clock",
					"sortorder" => "DESC",
					"limit" => 1
				]);

				$this->assertArrayHasKey('result', $response);
				$this->assertEquals(1, count($response['result']));
				$this->assertArrayHasKey('value', $response['result'][0]);
				$stats = json_decode($response['result'][0]['value'], true);

				if (!isset($stats['data']['process']['poller']['count'])) {
					sleep(1);
					continue;
				}

				$poller_count = $stats['data']['process']['poller']['count'];
				$this->assertEquals(
					self::START_POLLERS,
					$poller_count,
					'Actual number of pollers does not match the configured one while testing component ' . $component);

				$success = true;
			}

			if (!$success) {
				$this->fail('Failed to get poller count during the max number of attempts for component ' . $component);
			}
		}
	}

	/**
	 * Component configuration provider.
	 *
	 * @return array
	 */
	public function configurationProviderVarNames() {
		foreach (self::VALID_NAMES as $idx => $var_name) {
			// 'valid_usrprm0,echo valid_usrprm var123';
			$var_val = 'valid_usrprm' . $idx . ',echo valid_usrprm ' . $var_name;
			self::setEnv($var_name, $var_val);
		}

		// Currently multiple identical configuration parameters are not allowed by the test environment,
		// so use put them into an file and include it.
		foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $component) {
			$filename = self::$include_files[$component];

			$data = "";
			foreach (self::VALID_NAMES as $name) {
				$data .= 'UserParameter=' . '${' . $name . '}' . PHP_EOL;
			}

			if (file_put_contents($filename, $data) === false) {
				throw new Exception('Failed to create include configuration file for %s', $component);
			}
		}

		return [
			self::COMPONENT_AGENT => [
				'Include' => self::$include_files[self::COMPONENT_AGENT]
			],
			self::COMPONENT_AGENT2 => [
				'Include' => self::$include_files[self::COMPONENT_AGENT2]
			]
		];
	}

	/**
	 * Test valid variable names
	 *
	 * @configurationDataProvider configurationProviderVarNames
	 * @required-components agent,agent2
	 */
	public function testConfigTestOption_VariableNames() {

		foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $component) {
			foreach (self::VALID_NAMES as $idx => $var_name) {
				$usrprm_key = 'valid_usrprm' . $idx;
				$expected_output = 'valid_usrprm ' . $var_name . PHP_EOL;

				$port =	$this->getConfigurationValue($component, 'ListenPort');
				$output = shell_exec(PHPUNIT_BASEDIR . '/bin/zabbix_get -s 127.0.0.1 -p ' . $port .
					' -k ' . $usrprm_key . ' -t 7');

				$this->assertNotNull($output);
				$this->assertNotFalse($output);
				$this->assertEquals($expected_output, $output);
			}
		}
	}

	/**
	 * Test invalid variable names
	 */
	public function testConfigTestOption_InvalidVariableNames() {
		$def_config = self::getDefaultComponentConfiguration();

		foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $component) {
			foreach (self::INVALID_USER_PARAMS as $userParam) {
				$config = [
					$component => [
						'UserParameter' => $userParam
					]
				];

				$config[$component] = array_merge($config[$component], $def_config[$component]);
				self::prepareComponentConfiguration($component, $config);

				$config_path = PHPUNIT_CONFIG_DIR.'zabbix_'.$component.'.conf';
				if (!file_exists($config_path)) {
					throw new Exception('There is no configuration file for component "'.$component.'".');
				}
				$background = ($component === self::COMPONENT_AGENT2);
				$bin_path = PHPUNIT_BINARY_DIR.'zabbix_'.$component;

				self::clearLog($component);
				self::executeCommand($bin_path, ['-c', $config_path], $background);
				if ($component === self::COMPONENT_AGENT) {
					$line = 'cannot load user parameters: user parameter "' . $userParam . '": not comma-separated';
				} else {
					$line = 'Cannot initialize user parameters: cannot add user parameter "' . $userParam . '": not comma-separated';
				}

				try {
					self::waitForLogLineToBePresent($component, $line, true, 5, 1);
				} finally {
					$this->stopComponent($component); // for safety, agent should stop itself
				}
			}
		}
	}

	/**
	 * Test environment variable holding multi-string value (not allowed)
	 */
	public function testConfigTestOption_InvalidVariableWithMultiLineString() {
		self::setEnv("EnvVarMultiLine", "This is multiline\nstring");

		$def_config = self::getDefaultComponentConfiguration();

		foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $component) {
			$config = [
				$component => [
					'UserParameter' => '${EnvVarMultiLine}'
				]
			];

			$config[$component] = array_merge($config[$component], $def_config[$component]);
			self::prepareComponentConfiguration($component, $config);

			$config_path = PHPUNIT_CONFIG_DIR.'zabbix_'.$component.'.conf';
			if (!file_exists($config_path)) {
				throw new Exception('There is no configuration file for component "'.$component.'".');
			}

			$bin_path = PHPUNIT_BINARY_DIR.'zabbix_'.$component;
			$exceptionThrown = false;

			try {
				self::executeCommand($bin_path, ['-c', $config_path]);
			} catch (Exception $e) {
				$exceptionThrown = true;
				if ($component === self::COMPONENT_AGENT) {
					$needle = 'multi-line string in environment variable "EnvVarMultiLine" value "This is multiline';
				} else {
					$needle = 'multi-line string in environment variable "${EnvVarMultiLine}" value "This is multiline\nstring" at line';
				}
				$err_msg = 'String "' . $needle . '" is not found in exception message when starting component "' . $component . '".';
				$this->assertTrue(str_contains($e->getMessage(), $needle), $err_msg);
			} finally {
				$this->stopComponent($component); // for safety, agent should not start
			}

			$this->assertTrue($exceptionThrown, "No error message was detected for environment variable containing multi-line string when starting component " . $component . '".');
		}
	}

	/**
	 * Test undefined environment variable (configuration option should be set to default value in such case)
	 *
	 */
	public function testConfigTestOption_UndefinedVariable() {
		$def_config = self::getDefaultComponentConfiguration();

		foreach ([self::COMPONENT_AGENT, self::COMPONENT_AGENT2] as $component) {
			self::prepareComponentConfiguration($component, $def_config);

			$config_path = PHPUNIT_CONFIG_DIR.'zabbix_'.$component.'.conf';
			if (!file_exists($config_path)) {
				throw new Exception('There is no configuration file for component "'.$component.'".');
			}

			$search = '/^Hostname=.*$/m';
			$replace = 'Hostname=${EnvVarUndefined}';

			$content = file_get_contents($config_path);
			$content = preg_replace($search, $replace, $content);
			file_put_contents($config_path, $content);

			$background = ($component === self::COMPONENT_AGENT2);
			$bin_path = PHPUNIT_BINARY_DIR.'zabbix_'.$component;

			try {
				self::executeCommand($bin_path, ['-c', $config_path], $background);
				self::waitForStartup($component);

				$usrprm_key = 'agent.hostname';
				$expected_output = file_get_contents('/etc/hostname');

				$port =	$this->getConfigurationValue($component, 'ListenPort');
				$output = shell_exec(PHPUNIT_BASEDIR . '/bin/zabbix_get -s 127.0.0.1 -p ' . $port .
					' -k ' . $usrprm_key . ' -t 7');

				$this->assertNotNull($output);
				$this->assertNotFalse($output);
				$this->assertEquals($expected_output, $output);
			} finally {
				$this->stopComponent($component);
			}
		}
	}
}
