<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

	/**
	 * @inheritdoc
	 */
	public function prepareData(): bool {
		/*
		 * Calling for a script execution requires a host context, regardless of where the script will eventually
		 * execute. For tests to be self-contained, a new host and new host group is created.
		 */
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

		// Create scripts with and without manual input support.
		$scripts = [
			/*
			 * Required fields for script creation: 'name', 'type', and 'scope'.
			 * If 'manualinput' = ZBX_SCRIPT_MANUALINPUT_ENABLED, required fields are:
			 *   - 'manualinput_validator', 'manualinput_validator_type', 'manualinput_prompt'.
			 *   - 'manualinput_default_value' (when 'manualinput_validator_type' = ZBX_SCRIPT_MANUALINPUT_TYPE_STRING).
			 *
			 * API validates 'manualinput_default_value' against 'manualinput_validator' (regular expression) if
			 * 'manualinput_validator_type' = ZBX_SCRIPT_MANUALINPUT_TYPE_STRING. If 'manualinput_default_value' is not
			 * provided, it defaults to an empty string.
			 *
			 * The values for 'manualinput_prompt' and 'manualinput_default_value' are set only for API requirements.
			 *
			 * See https://www.zabbix.com/documentation/7.0/en/manual/api/reference/script/object for a reference of
			 * script object descriptions
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
				'StartTrappers' => 1
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
		$response = $this->call('script.execute', $request_params);

		$this->assertArrayHasKey('error', $response);

		$this->assertEquals('Application error.', $response['error']['message']);
		$this->assertEquals($expected_result, $response['error']['data']);
	}

	/**
	 * @return array<int, array>
	 */
	public function invalidRequestDataProvider(): array {
		return [
			/*
			 * Regarding 'manualinput', request failure is generally limited to two scenarios:
			 *   - if no 'manualinput' is provided;
			 *   - if 'manualinput' fails validation.
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
				'expected_result' => 'Your mindmacro has been expanded'
			],
			[
				'request_params' => [
					'hostid' => self::$hostid,
					'scriptid' => self::$scriptids[1],
					'manualinput' => 'abcdefghijklmnopqrstuvwxyz'
				],
				'expected_result' => 'Your abcdefghijklmnopqrstuvwxyz has been expanded'
			],
			[
				'request_params' => [
					'hostid' => self::$hostid,
					'scriptid' => self::$scriptids[2],
					'manualinput' => 'macro'
				],
				'expected_result' => 'Your macro has been expanded'
			]
		];
	}

	/**
	 * Delete data objects created for this test suite
	 */
	public function deleteData(): void {
		CDataHelper::call('script.delete', self::$scriptids);
		CDataHelper::call('host.delete', [self::$hostid]);
		CDataHelper::call('hostgroup.delete', [self::$hostgroupid]);
	}
}
