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
 * Test suite for Script Manual Input
 *
 * @required-components server
 * @configurationDataProvider serverConfigurationProvider
 * @backup hosts,scripts
 *
 * @onAfter deleteData
 */
class testScriptManualInput extends CIntegrationTest {
	private static $hostid;
	private static $hostgroupid;
	private static $scriptids;

	public function createTestHostData(): bool {
		static $initialized = false;
		/*
		 * First, create host group and a host in that group
		 *
		 * Calling for a script execution requires a host context,
		 * regardless of where the script will eventually execute.
		 *
		 * Although a default Zabbix installation already defines a host, the
		 * server itself, we want the tests to be self-contained.
		 */
		if ($initialized)
			return true;

		global $DB;
		if (!isset($DB['DB']))
			DBconnect($error);

		$response = CDataHelper::call('hostgroup.create', ['name' => 'Test Hosts']);

		self::$hostgroupid = $response['groupids'][0];

		$response = CDataHelper::call('host.create', [
			'host' => 'Test Host',
			'groups' => [
				[
					'groupid' => self::$hostgroupid
				]
			]
		]);

		self::$hostid = $response['hostids'][0];

		$initialized = true;

		return true;
	}

	/**
	 * @depends prepareTestHostData
	 */
	public function createTestScriptData(): bool {
		static $initialized = false;

		if ($initialized)
			return true;

		global $DB;
		if (!isset($DB['DB']))
			DBconnect($error);

		// Create scripts with and without manual input support.
		$scripts = [
			/*
			 * See [1] for a reference of these script object descriptions.
			 *
			 * Required fields for script creation are:
			 *     name, type, scope
			 * Required fields when the 'type' is set to 'Script':
			 *     command
			 * Required fields when 'manualinput' is set to 'Enabled':
			 *     manualinput_validator, manualinput_validator_type, manualinput_prompt
			 * Required fields when 'manualinput_validator_type' is set to 'String':
			 *     manualinput_default_value
			 *
			 * Note:
			 * When a script is created and is set to take manual input,
			 * the API will also verify that 'manualinput_default_value'
			 * passes the specified 'manualinput_validator', when the
			 * validator is a regular expression
			 * ('manualinput_validator_type' is ZBX_SCRIPT_MANUALINPUT_TYPE_STRING).
			 *
			 * If no value for 'manualinput_default_value' is provided,
			 * the API will fall back to setting it to an empty string,
			 * which may not pass the validator, and thus fail the script
			 * creation.
			 *
			 * We explicitly set 'manualinput_prompt' and 'manualinput_default_value'
			 * values here to appease the API, but they're not actually
			 * relied upon in these tests.
			 *
			 * [1]: https://www.zabbix.com/documentation/current/en/manual/api/reference/script/object
			 */
			[
				// Script that does not take any additional input
				'name' => 'Manual Input Test Script #1',
				'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
				'command' => "echo 'Your mindmacro has been expanded'",
				'scope' => ZBX_SCRIPT_SCOPE_HOST,
				'manualinput' => ZBX_SCRIPT_MANUALINPUT_DISABLED
			],
			[
				// Script accepts one or more lowercase characters for {MANUALINPUT}
				'name' => 'Manual Input Test Script #2',
				'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
				'command' => "echo 'Your {MANUALINPUT} has been expanded'",
				'scope' => ZBX_SCRIPT_SCOPE_HOST,
				'manualinput' => ZBX_SCRIPT_MANUALINPUT_ENABLED,
				'manualinput_validator_type' => ZBX_SCRIPT_MANUALINPUT_TYPE_STRING,
				'manualinput_validator' => '^[a-z]+$',
				'manualinput_prompt' => 'Tis but a prompt',
				'manualinput_default_value' => 'abcdefghijklmnopqrstuvwxyz'
			],
			[
				// Script accept one of a set of options for {MANUALINPUT}
				'name' => 'Manual Input Test Script #3',
				'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT,
				'command' => "echo 'Your {MANUALINPUT} has been expanded'",
				'scope' => ZBX_SCRIPT_SCOPE_HOST,
				'manualinput' => ZBX_SCRIPT_MANUALINPUT_ENABLED,
				'manualinput_validator_type' => ZBX_SCRIPT_MANUALINPUT_TYPE_LIST,
				'manualinput_validator' => 'macro,mind',
				'manualinput_prompt' => "T'is but a prompt"
			]
		];

		$response = CDataHelper::call('script.create', $scripts);

		self::$scriptids = $response['scriptids'];

		$initialized = true;

		return true;
	}

	/**
	 * Component configuration provider for server related tests.
	 *
	 * @return array
	 */
	public function serverConfigurationProvider(): array {
		return [
			self::COMPONENT_SERVER => [
				'StartTrappers' => 1,
				'EnableGlobalScripts' => 1
			]
		];
	}

	/**
	 * Component configuration provider for server related tests.
	 *
	 * @return array
	 */
	public function disabledScriptsConfigurationProvider(): array {
		return [
			self::COMPONENT_SERVER => [
				'EnableGlobalScripts' => 0
			]
		];
	}

	/**
	 * @dataProvider validRequestDataProvider
	 *
	 * @param array  $request_params   Parameters to the script.execute API call
	 * @param string $expected_result  String matching a successful API call return value
	 */
	public function testScriptManualInput_ValidRequests(array $request_params, string $expected_result): void {
		$response = $this->call('script.execute', $request_params);

		$this->assertArrayHasKey('result', $response);

		$this->assertEquals('success', $response['result']['response']);
		$this->assertEquals($expected_result, $response['result']['value']);
	}

	/**
	 * @dataProvider invalidRequestDataProvider
	 *
	 * @param array  $request_params   Parameters to the script.execute API call
	 * @param string $expected_result  String matching a failed API call error value
	 */
	public function testScriptManualInput_InvalidRequests(array $request_params, string $expected_result): void {
		$response = $this->call('script.execute', $request_params, $expected_result);
	}

	/**
	 * Test functionality of EnableGlobalScripts option
	 *
	 * @configurationDataProvider disabledScriptsConfigurationProvider
	 * @backup scripts
	 *
	 */
	public function testScriptManualInput_DisabledGlobalScripts() {
		// CAPITest::call() has assertions for 'result' key in a response, these will throw an exception
		$this->expectException(\PHPUnit\Framework\ExpectationFailedException::class);
		$this->call('script.execute', [
			'scriptid' => self::$scriptids[0],
			'hostid' => self::$hostid
		]);

		$response = $this->call('script.update', [
			'scriptid' => self::$scriptids[0],
			'execute_on' => ZBX_SCRIPT_EXECUTE_ON_PROXY
		]);
		$this->assertArrayHasKey("scriptids", $response['result']);

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		sleep(2);

		$this->expectException(\PHPUnit\Framework\ExpectationFailedException::class);
		$this->call('script.execute', [
			'scriptid' => self::$scriptids[0],
			'hostid' => self::$hostid
		]);
	}

	/**
	 * @return array<int, array>
	 */
	public function invalidRequestDataProvider(): array {
		$this->createTestHostData();
		$this->createTestScriptData();

		return [
			/*
			 * In general, there are only two scenarios in which a
			 * request will fail in regards to manual input:
			 *     (1): there is no manual input provided at all
			 *     (2): the input didn't pass validation
			 */
			[
				'request_params' => [
					'hostid' => self::$hostid,
					'scriptid' => self::$scriptids[1],
					'manualinput' => '5h0uld n0t m4tch a ^[a-z]+$ pattern'
				],
				'expected_result' => 'Provided script user input failed validation.'
			],
			[
				'request_params' => [
					'hostid' => self::$hostid,
					'scriptid' => self::$scriptids[2],
					'manualinput' => 'orcam'
				],
				'expected_result' => 'Provided script user input failed validation.'
			],
			[
				'request_params' => [
					'hostid' => self::$hostid,
					'scriptid' => self::$scriptids[2]
				],
				'expected_result' => 'Script takes user input, but none was provided.'
			]
		];
	}

	/**
	 * @return array<int, array>
	 */
	public function validRequestDataProvider(): array {
		$this->createTestHostData();
		$this->createTestScriptData();

		return [
			[
				/*
				 * This case tests the invocation of a script that doesn't take additional input but provides it in the
				 * request anyway. In such a case, the script should still execute, and, upon success, the response
				 * should still contain a value from the script execution, but the server should log a warning message
				 * indicating this.
				 */
				'request_params' => [
					'hostid' => self::$hostid,
					'scriptid' => self::$scriptids[0],
					'manualinput' => 'abcdefghijklmnopqrstuvwxyz'
				],
				'expected_result' => "Your mindmacro has been expanded\n"
			],
			[
				'request_params' => [
					'hostid' => self::$hostid,
					'scriptid' => self::$scriptids[1],
					'manualinput' => 'abcdefghijklmnopqrstuvwxyz'
				],
				'expected_result' => "Your abcdefghijklmnopqrstuvwxyz has been expanded\n"
			],
			[
				'request_params' => [
					'hostid' => self::$hostid,
					'scriptid' => self::$scriptids[2],
					'manualinput' => 'macro'
				],
				'expected_result' => "Your macro has been expanded\n"
			]
		];
	}

	/**
	 * Delete data objects created for this test suite
	 */
	public static function deleteData(): void {
		CDataHelper::call('script.delete', self::$scriptids);
		CDataHelper::call('host.delete', [self::$hostid]);
		CDataHelper::call('hostgroup.delete', [self::$hostgroupid]);
	}
}
