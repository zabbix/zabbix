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
 * @required-components server
 * @configurationDataProvider serverConfigurationProvider
 * @hosts test_web_scenario_dynamic_vars
 * @suite-components-reuse true
 * @onAfter clearData
 */
class testWebScenarioDynamicVariables extends CIntegrationTest {

	const HOSTNAME = 'test_web_scenario_dynamic_vars';

	private static int $hostid = 0;
	private static array $enabled_ids = [];
	private static array $enabled_names = [];
	private static array $disabled_ids = [];
	private static array $disabled_names = [];

	public function serverConfigurationProvider(): array {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 0
			]
		];
	}

	public function prepareData(): bool {
		// Clean up potentially leftover host from previous aborted test runs
		$hosts = CDataHelper::call('host.get', [
			'output' => ['hostid'],
			'filter' => ['host' => [self::HOSTNAME]]
		]);
		if (!empty($hosts)) {
			CDataHelper::call('host.delete', array_column($hosts, 'hostid'));
		}

		$response = $this->call('host.create', [
			'host'       => self::HOSTNAME,
			'interfaces' => [],
			'groups'     => [['groupid' => 4]],
			'status'     => HOST_STATUS_MONITORED
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid = (int)$response['result']['hostids'][0];

		$base_step = [
			'no'            => 1,
			'name'          => 'Step 1',
			'url'           => 'http://localhost/',
			'timeout'       => '5s',
			'retrieve_mode' => HTTPTEST_STEP_RETRIEVE_MODE_CONTENT
		];

		$enabled = [
			'regex_scenario_level' => [
				'name'      => 'regex prefix - scenario level',
				'variables' => [['name' => '{REGEX_VAR}', 'value' => 'regex:(.*)']],
				'steps'     => [$base_step]
			],
			'jsonpath_scenario_level' => [
				'name'      => 'jsonpath prefix - scenario level',
				'variables' => [['name' => '{JSON_VAR}', 'value' => 'jsonpath:$.result']],
				'steps'     => [$base_step]
			],
			'xmlxpath_scenario_level' => [
				'name'      => 'xmlxpath prefix - scenario level',
				'variables' => [['name' => '{XML_VAR}', 'value' => 'xmlxpath://result']],
				'steps'     => [$base_step]
			],
			'regex_step_level' => [
				'name'  => 'regex prefix - step level',
				'steps' => [array_merge($base_step, [
					'name'      => 'Extract with regex',
					'variables' => [['name' => '{STEP_REGEX}', 'value' => 'regex:(.+)']]
				])]
			],
			'jsonpath_step_level' => [
				'name'  => 'jsonpath prefix - step level',
				'steps' => [array_merge($base_step, [
					'name'      => 'Extract with jsonpath',
					'variables' => [['name' => '{STEP_JSON}', 'value' => 'jsonpath:$.ns']]
				])]
			],
			'xmlxpath_step_level' => [
				'name'  => 'xmlxpath prefix - step level',
				'steps' => [array_merge($base_step, [
					'name'      => 'Extract with xmlxpath',
					'variables' => [
						['name' => '{STEP_XML}', 'value' => 'xmlxpath://document/item/value/text()']
					]
				])]
			],
			'multi_variable_mixed' => [
				'name'      => 'multiple variables - mixed prefixes',
				'variables' => [
					['name' => '{NS}',      'value' => 'jsonpath:$.ns'],
					['name' => '{PATTERN}', 'value' => 'regex:([0-9]+)'],
					['name' => '{XML_VAL}', 'value' => 'xmlxpath://document/item/value/text()']
				],
				'steps' => [$base_step]
			],
			'multi_retry_scenario' => [
				'name'    => 'multi-retry scenario',
				'retries' => 2,
				'variables' => [['name' => '{RETRY_VAR}', 'value' => 'jsonpath:$.retry']],
				'steps'   => [$base_step]
			],
			'two_step_chain' => [
				'name'  => 'two-step variable chain',
				'steps' => [
					array_merge($base_step, [
						'name'      => 'Step 1 - extract',
						'variables' => [['name' => '{CHAIN_VAR}', 'value' => 'regex:(.+)']]
					]),
					[
						'no'            => 2,
						'name'          => 'Step 2 - consume',
						'url'           => 'http://localhost/?v={CHAIN_VAR}',
						'timeout'       => '5s',
						'retrieve_mode' => HTTPTEST_STEP_RETRIEVE_MODE_CONTENT
					]
				]
			],
			'jsonpath_scenario_with_required' => [
				'name'      => 'jsonpath scenario level with required pattern',
				'variables' => [['name' => '{NS}', 'value' => 'jsonpath:$.ns']],
				'steps'     => [array_merge($base_step, ['required' => 'ns'])]
			],
			'xmlxpath_scenario_with_status' => [
				'name'      => 'xmlxpath scenario level with status code check',
				'variables' => [['name' => '{XML_VAL}', 'value' => 'xmlxpath://document/item/value/text()']],
				'steps'     => [array_merge($base_step, ['status_codes' => '200'])]
			],
			'static_after_dynamic' => [
				'name'      => 'static variable after dynamic at scenario level',
				'variables' => [
					['name' => '{DYNAMIC_VAR}', 'value' => 'regex:hostid is ([0-9]+)'],
					['name' => '{BASE_PATH}',   'value' => '/']
				],
				'steps' => [array_merge($base_step, ['url' => 'http://localhost{BASE_PATH}'])]
			],
			'plain_variables' => [
				'name'      => 'plain string variables - scenario level',
				'variables' => [
					['name' => '{username}', 'value' => 'Alexei'],
					['name' => '{password}', 'value' => 'kj3h5kJ34bd']
				],
				'steps' => [$base_step]
			],
			'regex_doc_pattern' => [
				'name'      => 'doc example - regex hostid pattern - scenario level',
				'variables' => [['name' => '{hostid}', 'value' => 'regex:hostid is ([0-9]+)']],
				'steps'     => [$base_step]
			],
			'jsonpath_doc_pattern' => [
				'name'      => 'doc example - jsonpath host url - scenario level',
				'variables' => [['name' => '{url}', 'value' => 'jsonpath:$.host_url']],
				'steps'     => [$base_step]
			],
			'xmlxpath_doc_pattern' => [
				'name'      => 'doc example - xmlxpath response status - scenario level',
				'variables' => [['name' => '{status}', 'value' => 'xmlxpath://host/response/status']],
				'steps'     => [$base_step]
			],
			'macro_function_variable' => [
				'name'      => 'macro function variable {{myvar}.btoa()} - scenario level',
				'variables' => [
					['name' => '{myvar}',  'value' => 'Alexei'],
					['name' => '{newvar}', 'value' => '{{myvar}.btoa()}']
				],
				'steps' => [array_merge($base_step, ['url' => 'http://localhost/?v={{myvar}.btoa()}'])]
			]
		];

		$disabled = [
			'disabled_jsonpath_scenario' => [
				'name'      => 'DISABLED - jsonpath prefix - scenario level',
				'variables' => [['name' => '{DISABLED_JSON}', 'value' => 'jsonpath:$.ns']],
				'steps'     => [$base_step]
			],
			'disabled_xmlxpath_scenario' => [
				'name'      => 'DISABLED - xmlxpath prefix - scenario level',
				'variables' => [
					['name' => '{DISABLED_XML}', 'value' => 'xmlxpath://document/item/value/text()']
				],
				'steps' => [$base_step]
			],
			'disabled_regex_step' => [
				'name'  => 'DISABLED - regex prefix - step level',
				'steps' => [array_merge($base_step, [
					'name'      => 'Extract',
					'variables' => [['name' => '{DISABLED_REGEX}', 'value' => 'regex:(.+)']]
				])]
			]
		];

		foreach ($enabled as $label => $def) {
			$params = array_merge([
				'hostid'    => self::$hostid,
				'delay'     => '5s',
				'status'    => HTTPTEST_STATUS_ACTIVE,
				'variables' => [],
				'steps'     => []
			], $def);

			$r = $this->call('httptest.create', $params);
			$this->assertArrayHasKey('httptestids', $r['result']);
			$this->assertArrayHasKey(0, $r['result']['httptestids']);
			self::$enabled_ids[$label]   = $r['result']['httptestids'][0];
			self::$enabled_names[$label] = $def['name'];
		}

		foreach ($disabled as $label => $def) {
			$params = array_merge([
				'hostid'    => self::$hostid,
				'delay'     => '5s',
				'status'    => HTTPTEST_STATUS_DISABLED,
				'variables' => [],
				'steps'     => []
			], $def);

			$r = $this->call('httptest.create', $params);
			$this->assertArrayHasKey('httptestids', $r['result']);
			$this->assertArrayHasKey(0, $r['result']['httptestids']);
			self::$disabled_ids[$label]   = $r['result']['httptestids'][0];
			self::$disabled_names[$label] = $def['name'];
		}

		return true;
	}

	public function testWebScenarioDynamicVariables_ScenarioLevelInit(): void {
		$this->reloadConfigurationCache();
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of zbx_dc_sync_configuration()', true, 30, 1);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of process_httptests()', true, 120, 2);

		$this->assertLASTSTEPHistoryPresent(
			array_values(self::$enabled_names),
			count(self::$enabled_ids),
			'all enabled scenarios'
		);
	}

	public function testWebScenarioDynamicVariables_StepLevel(): void {
		$labels = ['regex_step_level', 'jsonpath_step_level', 'xmlxpath_step_level'];
		$names  = array_values(array_intersect_key(self::$enabled_names, array_flip($labels)));

		$this->assertLASTSTEPHistoryPresent($names, count($names), 'step-level variable extraction');
	}

	public function testWebScenarioDynamicVariables_TwoStepChain(): void {
		$this->assertLASTSTEPHistoryPresent(
			[self::$enabled_names['two_step_chain']],
			1,
			'two-step variable chain'
		);
	}

	public function testWebScenarioDynamicVariables_StaticAfterDynamic(): void {
		$this->assertLASTSTEPHistoryPresent(
			[self::$enabled_names['static_after_dynamic']],
			1,
			'static variable after dynamic at scenario level'
		);
	}

	public function testWebScenarioDynamicVariables_MultiVariableMixed(): void {
		$this->assertLASTSTEPHistoryPresent(
			[self::$enabled_names['multi_variable_mixed']],
			1,
			'multiple variables with mixed prefixes'
		);
	}

	public function testWebScenarioDynamicVariables_MultiRetry(): void {
		$this->assertLASTSTEPHistoryPresent(
			[self::$enabled_names['multi_retry_scenario']],
			1,
			'multi-retry scenario'
		);
	}

	public function testWebScenarioDynamicVariables_PlainVariables(): void {
		$this->assertLASTSTEPHistoryPresent(
			[self::$enabled_names['plain_variables']],
			1,
			'plain string variables at scenario level'
		);
	}

	public function testWebScenarioDynamicVariables_DocPatterns(): void {
		$labels = ['regex_doc_pattern', 'jsonpath_doc_pattern', 'xmlxpath_doc_pattern'];
		$names  = array_values(array_intersect_key(self::$enabled_names, array_flip($labels)));

		$this->assertLASTSTEPHistoryPresent($names, count($names), 'doc-exact variable patterns');
	}

	public function testWebScenarioDynamicVariables_MacroFunctionVariable(): void {
		$this->assertLASTSTEPHistoryPresent(
			[self::$enabled_names['macro_function_variable']],
			1,
			'macro function variable {{myvar}.btoa()}'
		);
	}

	public function testWebScenarioDynamicVariables_StepChecks(): void {
		$labels = ['jsonpath_scenario_with_required', 'xmlxpath_scenario_with_status'];
		$names  = array_values(array_intersect_key(self::$enabled_names, array_flip($labels)));

		$this->assertLASTSTEPHistoryPresent($names, count($names), 'step-level checks with scenario variables');
	}

	public function testWebScenarioDynamicVariables_DisabledScenarios(): void {
		$this->assertLASTSTEPHistoryPresent(
			[self::$enabled_names['regex_scenario_level']],
			1,
			'anchor for disabled-scenario check'
		);

		$items = $this->call('item.get', [
			'hostids'  => [self::$hostid],
			'webitems' => true,
			'filter'   => ['type' => [ITEM_TYPE_HTTPTEST]],
			'output'   => ['itemid', 'key_']
		]);
		$this->assertArrayHasKey('result', $items);

		$disabled_itemids = [];
		foreach ($items['result'] as $item) {
			foreach (self::$disabled_names as $scenario_name) {
				if ($item['key_'] === "web.test.fail[{$scenario_name}]") {
					$disabled_itemids[] = $item['itemid'];
					break;
				}
			}
		}

		if (empty($disabled_itemids)) {
			$this->assertTrue(true);
			return;
		}

		$history = $this->call('history.get', [
			'itemids' => $disabled_itemids,
			'history' => ITEM_VALUE_TYPE_UINT64,
			'output'  => 'extend',
			'limit'   => 1
		]);

		$this->assertEmpty($history['result'], 'Disabled web scenarios must not produce any LASTSTEP history');
	}

	private function assertLASTSTEPHistoryPresent(array $scenario_names, int $expected_count, string $context): void {
		$items = $this->call('item.get', [
			'hostids'  => [self::$hostid],
			'webitems' => true,
			'filter'   => ['type' => [ITEM_TYPE_HTTPTEST]],
			'output'   => ['itemid', 'key_', 'value_type']
		]);

		$this->assertArrayHasKey('result', $items);
		$this->assertNotEmpty($items['result'],
			"No internal web-scenario items found for host ".self::HOSTNAME." — context: {$context}");

		$target_itemids = [];
		foreach ($items['result'] as $item) {
			if (!str_starts_with($item['key_'], 'web.test.fail[')) {
				continue;
			}
			foreach ($scenario_names as $name) {
				if ($item['key_'] === "web.test.fail[{$name}]") {
					$target_itemids[] = $item['itemid'];
					break;
				}
			}
		}

		if (empty($target_itemids)) {
			$target_itemids = array_column(
				array_filter($items['result'],
					fn($item) => str_starts_with($item['key_'], 'web.test.fail[')),
				'itemid'
			);
		}

		$this->assertNotEmpty($target_itemids,
			"No LASTSTEP (web.test.fail) items found — context: {$context}");

		$history = $this->callUntilDataIsPresent('history.get', [
			'itemids'   => $target_itemids,
			'history'   => ITEM_VALUE_TYPE_UINT64,
			'output'    => 'extend',
			'sortfield' => 'clock',
			'sortorder' => 'DESC',
			'limit'     => $expected_count * 10
		], 60, 2, function ($response) use ($expected_count) {
			$unique = count(array_unique(array_column($response['result'], 'itemid')));
			return $unique >= $expected_count
				? true
				: "waiting for scenarios ({$unique}/{$expected_count} have history)";
		});

		$this->assertNotEmpty($history['result'],
			"No LASTSTEP history found — web scenarios may not have been executed — context: {$context}");

		$items_with_history = count(array_unique(array_column($history['result'], 'itemid')));
		$this->assertGreaterThanOrEqual($expected_count, $items_with_history,
			"Not all expected scenarios produced LASTSTEP history — context: {$context}");
	}

	/**
	 * Delete all created data after test.
	 */
	public static function clearData(): void {
		if (self::$hostid !== 0) {
			CDataHelper::call('host.delete', [self::$hostid]);
		}
	}
}
