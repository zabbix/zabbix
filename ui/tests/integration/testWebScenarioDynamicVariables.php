<?php declare(strict_types = 1);
/*
** Copyright (C) 2001-2026 Zabbix SIA
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
 * Test web scenario execution with dynamic variable extraction prefixes (ZBX-27770).
 *
 * Verifies that the server does not crash and continues processing when a web scenario
 * has scenario-level variables whose values start with regex:, jsonpath:, or xmlxpath:
 * prefixes at the point where data is NULL (before any HTTP step response is available).
 *
 * @required-components server
 * @configurationDataProvider serverConfigurationProvider
 * @hosts test_web_scenario_dynamic_vars
 * @suite-components-reuse true
 * @onAfter clearData
 */
class testWebScenarioDynamicVariables extends CIntegrationTest {

	const HOSTNAME = 'test_web_scenario_dynamic_vars';

	private static int $hostid;
	private static array $httptestids = [];

	public function serverConfigurationProvider(): array {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 0
			]
		];
	}

	/**
	 * Creates the host and three web scenarios, one for each dynamic variable prefix type.
	 */
	public function prepareData(): bool {
		$response = $this->call('host.create', [
			'host' => self::HOSTNAME,
			'interfaces' => [],
			'groups' => [['groupid' => 4]],
			'status' => HOST_STATUS_MONITORED
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid = (int)$response['result']['hostids'][0];

		$scenarios = [
			[
				'name' => 'ZBX-27770 regex prefix variable',
				'variables' => [
					['name' => '{REGEX_VAR}', 'value' => 'regex:(.*)']
				]
			],
			[
				'name' => 'ZBX-27770 jsonpath prefix variable',
				'variables' => [
					['name' => '{JSON_VAR}', 'value' => 'jsonpath:$.result']
				]
			],
			[
				'name' => 'ZBX-27770 xmlxpath prefix variable',
				'variables' => [
					['name' => '{XML_VAR}', 'value' => 'xmlxpath://result']
				]
			]
		];

		foreach ($scenarios as $scenario) {
			$response = $this->call('httptest.create', [
				'hostid' => self::$hostid,
				'name' => $scenario['name'],
				'delay' => '5s',
				'status' => HTTPTEST_STATUS_ACTIVE,
				'variables' => $scenario['variables'],
				'steps' => [
					[
						'no' => 1,
						'name' => 'Step 1',
						'url' => 'http://localhost/',
						'timeout' => '5s',
						'retrieve_mode' => HTTPTEST_STEP_RETRIEVE_MODE_CONTENT
					]
				]
			]);
			$this->assertArrayHasKey('httptestids', $response['result']);
			$this->assertArrayHasKey(0, $response['result']['httptestids']);
			self::$httptestids[] = $response['result']['httptestids'][0];
		}

		return true;
	}

	/**
	 * Verifies the server survives processing web scenarios with regex:, jsonpath:, and
	 * xmlxpath: prefixed scenario-level variables.
	 *
	 * Before ZBX-27770 was fixed, passing NULL data to http_process_variables() for any
	 * of those prefixes caused an immediate crash. The assertions below confirm that the
	 * HTTP poller ran to completion and the server remained alive.
	 */
	public function testWebScenarioDynamicVariables_ZBX27770(): void {
		$this->reloadConfigurationCache();
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of zbx_dc_sync_configuration()', true, 30, 1);

		// Wait for the HTTP poller to complete at least one polling cycle.
		// A crash at httpmacro.c http_process_variables() would prevent this line from appearing.
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of process_httptests()', true, 120, 2);

		// Confirm the server is still alive and responding after processing the scenarios.
		// apiinfo.version must be called without an Authorization header.
		$version = $this->callRaw([
			'jsonrpc' => '2.0',
			'method' => 'apiinfo.version',
			'params' => [],
			'id' => 1
		]);
		$this->assertArrayHasKey('result', $version,
			'Server became unresponsive — possible crash in http_process_variables()');
		$this->assertNotEmpty($version['result'],
			'Server became unresponsive — possible crash in http_process_variables()');

		// Retrieve the internal LASTSTEP items (ITEM_VALUE_TYPE_UINT64) created automatically
		// by httptest.create for each web scenario. These always receive a history value
		// (0 = all steps passed, N = step N failed) regardless of HTTP connectivity.
		$items = $this->call('item.get', [
			'hostids' => [self::$hostid],
			'webitems' => true,
			'filter' => ['type' => [ITEM_TYPE_HTTPTEST]],
			'output' => ['itemid', 'key_', 'value_type']
		]);
		$this->assertArrayHasKey('result', $items);
		$this->assertNotEmpty($items['result'],
			'No internal web scenario items found for host '.self::HOSTNAME);

		$laststep_itemids = array_column(
			array_filter($items['result'],
				fn($item) => str_starts_with($item['key_'], 'web.test.fail[')),
			'itemid'
		);

		$this->assertNotEmpty($laststep_itemids,
			'No LASTSTEP items (web.test.fail) found for the web scenarios');

		// Poll until ALL scenarios have history — not just the first one to arrive.
		// callUntilDataIsPresent returns as soon as any result appears; the callback
		// ensures we only stop once every expected item has at least one history entry.
		$expected_count = count($laststep_itemids);
		$history = $this->callUntilDataIsPresent('history.get', [
			'itemids' => $laststep_itemids,
			'history' => ITEM_VALUE_TYPE_UINT64,
			'output' => 'extend',
			'sortfield' => 'clock',
			'sortorder' => 'DESC',
			'limit' => $expected_count * 10
		], 60, 2, function($response) use ($expected_count) {
			$unique = count(array_unique(array_column($response['result'], 'itemid')));
			return $unique >= $expected_count
				? true
				: "waiting for all scenarios ({$unique}/{$expected_count} have history)";
		});

		$this->assertNotEmpty($history['result'],
			'No LASTSTEP history found — web scenarios may not have been executed');

		// The count of distinct items with history must match the number of created scenarios.
		$items_with_history = count(array_unique(array_column($history['result'], 'itemid')));
		$this->assertEquals(count(self::$httptestids), $items_with_history,
			'Not all web scenarios produced LASTSTEP history values');
	}

	public static function clearData(): void {
		if (!empty(self::$httptestids)) {
			CDataHelper::call('httptest.delete', self::$httptestids);
			self::$httptestids = [];
		}

		$hosts = CDataHelper::call('host.get', [
			'output' => ['hostid'],
			'filter' => ['host' => [self::HOSTNAME]]
		]);

		if (!empty($hosts['result'])) {
			$hostids = array_column($hosts['result'], 'hostid');
			CDataHelper::call('host.delete', $hostids);
		}

		self::$hostid = 0;
	}
}
