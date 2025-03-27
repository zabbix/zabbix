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
 * Test suite for testing -T, --test-config option of Server, Proxy, Agent, Agent2 and Web Service.
 *
 * @onBefore prepareTestEnv
 * @onAfter cleanupTestEnv
 */
class testConfigTestOption extends CIntegrationTest {
	private static $components = [
		self::COMPONENT_SERVER,
		self::COMPONENT_PROXY,
		self::COMPONENT_AGENT,
		self::COMPONENT_AGENT2
	];

	// override CIntegrationTest::startComponent(), we want to prepare the configuration but not start the component
	protected function startComponent($component, $waitLogLineOverride = '', $skip_pid = false) {}


	public static function prepareTestEnv(): void {
		putenv('TestDebugLevel=5');
		putenv('TestStringVar=test_string_value');
	}

	public static function cleanupTestEnv(): void {
		putenv('TestDebugLevel');
		putenv('TestStringVar');
	}

	/**
	 * This function will perform the actual test of specified component.
	 *
	 * @return array
	 */
	private function testComponent($component, $options, $expected_exit_code, $equals) {
		$config = PHPUNIT_CONFIG_DIR.'zabbix_' . $component . '.conf';

		if (!file_exists($config)) {
			throw new Exception('There is no configuration file for component "' . $component . '".');
		}

		self::clearLog($component);

		$bin_path = PHPUNIT_BINARY_DIR.'zabbix_' . $component;

		$command = $bin_path . ' -c ' . $config . ' ' . $options . ' 2>&1';

		exec($command, $output, $exit_code);

		if ($equals)
			$this->assertEquals($exit_code, $expected_exit_code, "testing $component with \"$options\":\n" . implode("\n", $output));
		else
			$this->assertNotEquals($exit_code, $expected_exit_code, "testing $component with \"$options\":\n" . implode("\n", $output));
	}

	/**
	 * Configuration provider, return valid configuration for each component.
	 *
	 * @return array
	 */
	public function validConfigurationProvider($useConfigVars) {
		$config = [];
		foreach (self::$components as $component) {
			if ($useConfigVars) {
				$config[$component] = ['DebugLevel' => '${TestDebugLevel}'];
			} else {
				$config[$component] = ['DebugLevel' => 5];
			}
		}
		return $config;
	}

	/**
	 * Configuration provider, return invalid configuration for each component.
	 *
	 * @return array
	 */
	public function invalidConfigurationProvider($useConfigVars) {
		$config = [];

		foreach (self::$components as $component) {
			if ($useConfigVars) {
				$config[$component] = ['DebugLevel' => '${TestStringVar}']; // wrong type
			} else {
				$config[$component] = ['DebugLeve' => 5];
			}
		}
		return $config;
	}

	/**
	 * Test each component with valid configuration. Must exit with 0 exit code.
	 *
	 * @testWith [false]
	 *           [true]
	 */
	public function testConfigTestOption_checkingValidServerConfig($useConfigVars) {
		foreach (self::$components as $component) {
			self::prepareComponentConfiguration($component,
					self::validConfigurationProvider($useConfigVars));

			foreach (['-T', '--test-config'] as $options) {
				self::testComponent($component, $options, 0, true);
			}
		}
	}

	/**
	 * Test each component with invalid configuration. Must exit with non-zero exit code.
	 *
	 * @testWith [false]
	 *           [true]
	 */
	public function testConfigTestOption_checkingInvalidServerConfig($useConfigVars) {
		foreach (self::$components as $component) {
			self::prepareComponentConfiguration($component,
					self::invalidConfigurationProvider($useConfigVars));

			foreach (['-T', '--test-config'] as $options) {
				self::testComponent($component, $options, 0, false);
			}
		}
	}

	/**
	 * Test each component with invalid command-line parameters. Must exit with non-zero exit code.
	 *
	 * @testWith [false]
	 *           [true]
	 */
	public function testConfigTestOption_checkingInvalidOptions($useConfigVars) {
		foreach (self::$components as $component) {
			self::prepareComponentConfiguration($component,
					self::validConfigurationProvider($useConfigVars));

			foreach (['-T -R log_level_decrease'] as $options) {
				self::testComponent($component, $options, 0, false);
			}
		}
	}
}
