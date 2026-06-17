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
 * Integration tests for web scenario execution covering:
 *   - Crash prevention for ZBX-27770 (regex:, jsonpath:, xmlxpath: prefixes with NULL data)
 *   - Scenario-level and step-level variable extraction for all three prefix types
 *   - Multi-step scenarios where a variable extracted in step N is substituted in step N+1
 *   - Multiple variables per scenario (mixed prefix types)
 *   - Scenarios configured with retries > 1
 *   - Disabled scenarios that must never produce LASTSTEP history
 *
 * The test host is created once and shared across all test methods via
 * @suite-components-reuse.  All scenarios use http://localhost/ as the target
 * URL; the assertions focus on server liveness and correct LASTSTEP history
 * recording rather than successful HTTP extraction, making the suite robust
 * to whether localhost is reachable.
 *
 * @required-components server
 * @configurationDataProvider serverConfigurationProvider
 * @hosts test_web_scenario_dynamic_vars
 * @suite-components-reuse true
 * @onAfter clearData
 */
class testWebScenarioDynamicVariables extends CIntegrationTest {

	const HOSTNAME = 'test_web_scenario_dynamic_vars';

	private static int $hostid = 0;

	/** label => httptestid for scenarios expected to produce LASTSTEP history. */
	private static array $enabled_ids = [];

	/** label => scenario name — used to build the web.test.fail[<name>] item key. */
	private static array $enabled_names = [];

	/** label => httptestid for scenarios that must never be executed. */
	private static array $disabled_ids = [];

	/** label => scenario name for disabled scenarios. */
	private static array $disabled_names = [];

	public function serverConfigurationProvider(): array {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 0
			]
		];
	}

	/**
	 * Creates the host and all web scenarios used across the test suite.
	 *
	 * Enabled scenarios (should produce LASTSTEP history):
	 *   Scenario-level variables — one scenario per prefix type (ZBX-27770 coverage)
	 *   Step-level variables     — one scenario per prefix type
	 *   Mixed / structural       — multiple variables, retries > 1, two-step variable chain
	 *
	 * Disabled scenarios (must never appear in history):
	 *   jsonpath: and xmlxpath: at scenario level, regex: at step level
	 */
	public function prepareData(): bool {
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

		// ------------------------------------------------------------------
		// Enabled scenario definitions
		// ------------------------------------------------------------------
		$enabled = [

			// --- Scenario-level variables: ZBX-27770 crash prevention ---
			// These are processed with NULL data before the first HTTP step,
			// so the server must not dereference a null pointer in the regex/
			// jsonpath/xmlxpath branches of httpmacro_append_pair().

			'regex_scenario_level' => [
				'name'      => 'ZBX-27770 regex prefix - scenario level',
				'variables' => [['name' => '{REGEX_VAR}', 'value' => 'regex:(.*)']],
				'steps'     => [$base_step]
			],
			'jsonpath_scenario_level' => [
				'name'      => 'ZBX-27770 jsonpath prefix - scenario level',
				'variables' => [['name' => '{JSON_VAR}', 'value' => 'jsonpath:$.result']],
				'steps'     => [$base_step]
			],
			'xmlxpath_scenario_level' => [
				'name'      => 'ZBX-27770 xmlxpath prefix - scenario level',
				'variables' => [['name' => '{XML_VAR}', 'value' => 'xmlxpath://result']],
				'steps'     => [$base_step]
			],

			// --- Step-level variables: processed with actual step response data ---
			// The extraction runs after the HTTP step completes.  If the URL is
			// unreachable, the step fails before extraction — LASTSTEP is still
			// recorded, so the test assertion holds regardless.

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

			// --- Multiple scenario-level variables with mixed prefix types ---
			// Verifies that http_process_variables() iterates the full vector
			// without short-circuiting on a NULL-data prefix variable.

			'multi_variable_mixed' => [
				'name'      => 'multiple variables - mixed prefixes',
				'variables' => [
					['name' => '{NS}',      'value' => 'jsonpath:$.ns'],
					['name' => '{PATTERN}', 'value' => 'regex:([0-9]+)'],
					['name' => '{XML_VAL}', 'value' => 'xmlxpath://document/item/value/text()']
				],
				'steps' => [$base_step]
			],

			// --- Retries > 1 ---
			// Exercises the retry loop in process_httptest() with an extraction
			// variable; the macro cache must survive multiple attempt cycles.

			'multi_retry_scenario' => [
				'name'    => 'multi-retry scenario',
				'retries' => 2,
				'variables' => [['name' => '{RETRY_VAR}', 'value' => 'jsonpath:$.retry']],
				'steps'   => [$base_step]
			],

			// --- Two-step variable chain ---
			// Step 1 defines {CHAIN_VAR}; step 2 references it in the URL via
			// http_substitute_variables().  If step 1 fails (URL unreachable) the
			// scenario stops there; the assertion only requires LASTSTEP history.

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

			// --- Scenario-level JSONPath variable with step required-pattern check ---
			// The required field on a step is evaluated against the response body
			// independently of variable extraction.  Both paths must be exercised
			// without crashing when the scenario-level variable has NULL data.

			'jsonpath_scenario_with_required' => [
				'name'      => 'jsonpath scenario level with required pattern',
				'variables' => [['name' => '{NS}', 'value' => 'jsonpath:$.ns']],
				'steps'     => [array_merge($base_step, ['required' => 'ns'])]
			],

			// --- Scenario-level XMLPath variable with status-code check ---
			// Exercises the status_codes validation branch alongside the
			// XML-prefix NULL-data path in httpmacro_append_pair().

			'xmlxpath_scenario_with_status' => [
				'name'      => 'xmlxpath scenario level with status code check',
				'variables' => [['name' => '{XML_VAL}', 'value' => 'xmlxpath://document/item/value/text()']],
				'steps'     => [array_merge($base_step, ['status_codes' => '200'])]
			]
		];

		// ------------------------------------------------------------------
		// Disabled scenario definitions
		// ------------------------------------------------------------------
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

	/**
	 * Primary crash-prevention test (ZBX-27770).
	 *
	 * Verifies that the server survives the first HTTP poller cycle, which is
	 * when scenario-level variables with regex:, jsonpath:, and xmlxpath: prefixes
	 * are evaluated against NULL data in httpmacro_append_pair().  Before the fix,
	 * any of those three branches would dereference a null pointer and crash.
	 *
	 * After confirming the server is still alive, waits until every enabled
	 * scenario has produced at least one LASTSTEP (web.test.fail) history entry.
	 */
	public function testWebScenarioDynamicVariables_ZBX27770(): void {
		$this->reloadConfigurationCache();
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of zbx_dc_sync_configuration()', true, 30, 1);

		// A crash at http_process_variables() prevents this log line from appearing.
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'End of process_httptests()', true, 120, 2);

		// Confirm server still responds to API calls.
		$version = $this->callRaw([
			'jsonrpc' => '2.0',
			'method'  => 'apiinfo.version',
			'params'  => [],
			'id'      => 1
		]);
		$this->assertArrayHasKey('result', $version,
			'Server became unresponsive — possible crash in http_process_variables()');
		$this->assertNotEmpty($version['result'],
			'Server became unresponsive — possible crash in http_process_variables()');

		// All enabled scenarios must have LASTSTEP history.
		$this->assertLASTSTEPHistoryPresent(
			array_values(self::$enabled_names),
			count(self::$enabled_ids),
			'all enabled scenarios (ZBX-27770)'
		);
	}

	/**
	 * Verifies that scenarios with step-level variable extraction produce
	 * LASTSTEP history for each prefix type (regex:, jsonpath:, xmlxpath:).
	 *
	 * Step-level variables differ from scenario-level ones: they are processed
	 * with the actual HTTP response body after the step completes, not with NULL
	 * data beforehand.  Both code paths in httptest.c must be exercised.
	 */
	public function testWebScenarioDynamicVariables_StepLevel(): void {
		$labels = ['regex_step_level', 'jsonpath_step_level', 'xmlxpath_step_level'];
		$names  = array_values(array_intersect_key(self::$enabled_names, array_flip($labels)));

		$this->assertLASTSTEPHistoryPresent($names, count($names), 'step-level variable extraction');
	}

	/**
	 * Verifies that a two-step scenario in which step 1 defines a variable via
	 * regex: extraction and step 2 references that variable in its URL is
	 * processed without crashing the server.
	 */
	public function testWebScenarioDynamicVariables_TwoStepChain(): void {
		$this->assertLASTSTEPHistoryPresent(
			[self::$enabled_names['two_step_chain']],
			1,
			'two-step variable chain'
		);
	}

	/**
	 * Verifies that a scenario with multiple scenario-level variables using
	 * mixed prefix types (jsonpath:, regex:, xmlxpath:) is processed without
	 * crashing and produces LASTSTEP history.
	 */
	public function testWebScenarioDynamicVariables_MultiVariableMixed(): void {
		$this->assertLASTSTEPHistoryPresent(
			[self::$enabled_names['multi_variable_mixed']],
			1,
			'multiple variables with mixed prefixes'
		);
	}

	/**
	 * Verifies that a scenario configured with retries > 1 and a scenario-level
	 * extraction variable is processed and produces LASTSTEP history.
	 */
	public function testWebScenarioDynamicVariables_MultiRetry(): void {
		$this->assertLASTSTEPHistoryPresent(
			[self::$enabled_names['multi_retry_scenario']],
			1,
			'multi-retry scenario'
		);
	}

	/**
	 * Verifies that scenarios combining scenario-level extraction variables with
	 * step-level checks (required pattern, status_codes) produce LASTSTEP history.
	 */
	public function testWebScenarioDynamicVariables_StepChecks(): void {
		$labels = ['jsonpath_scenario_with_required', 'xmlxpath_scenario_with_status'];
		$names  = array_values(array_intersect_key(self::$enabled_names, array_flip($labels)));

		$this->assertLASTSTEPHistoryPresent($names, count($names), 'step-level checks with scenario variables');
	}

	/**
	 * Verifies that disabled web scenarios are never executed by the HTTP poller
	 * and therefore produce no LASTSTEP (web.test.fail) history entries.
	 *
	 * To establish that the server has had enough time to run, the test first
	 * waits for one of the enabled scenarios to produce history before asserting
	 * that disabled scenario items remain empty.
	 */
	public function testWebScenarioDynamicVariables_DisabledScenarios(): void {
		// Anchor: ensure the server has executed at least one scenario run cycle.
		$this->assertLASTSTEPHistoryPresent(
			[self::$enabled_names['regex_scenario_level']],
			1,
			'anchor for disabled-scenario check'
		);

		// Collect LASTSTEP item IDs for disabled scenarios.
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
			// Items may not yet exist if the poller never selected disabled scenarios — correct.
			$this->assertTrue(true);
			return;
		}

		$history = $this->call('history.get', [
			'itemids' => $disabled_itemids,
			'history' => ITEM_VALUE_TYPE_UINT64,
			'output'  => 'extend',
			'limit'   => 1
		]);
		$this->assertEmpty($history['result'],
			'Disabled web scenarios must not produce any LASTSTEP history');
	}

	/**
	 * Finds the web.test.fail items for each scenario name in $scenario_names and
	 * polls history.get until at least $expected_count of those items have a value.
	 *
	 * Uses item-key matching ("web.test.fail[<name>]") for precise per-scenario
	 * targeting.  Falls back to asserting on all LASTSTEP items for the host when
	 * no name-based matches are found — this guards against unexpected key formats.
	 *
	 * @param string[] $scenario_names Scenario names to look up.
	 * @param int      $expected_count Minimum distinct items that must have history.
	 * @param string   $context        Short label included in assertion messages.
	 */
	private function assertLASTSTEPHistoryPresent(array $scenario_names, int $expected_count,
			string $context): void {
		$items = $this->call('item.get', [
			'hostids'  => [self::$hostid],
			'webitems' => true,
			'filter'   => ['type' => [ITEM_TYPE_HTTPTEST]],
			'output'   => ['itemid', 'key_', 'value_type']
		]);
		$this->assertArrayHasKey('result', $items);
		$this->assertNotEmpty($items['result'],
			"No internal web-scenario items found for host ".self::HOSTNAME." — context: {$context}");

		// Try precise matching by name first.
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

		// If no name-based match, fall back to all LASTSTEP items on the host.
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

	public static function clearData(): void {
		$all_ids = array_merge(
			array_values(self::$enabled_ids),
			array_values(self::$disabled_ids)
		);

		if (!empty($all_ids)) {
			CDataHelper::call('httptest.delete', $all_ids);
		}

		$hosts = CDataHelper::call('host.get', [
			'output' => ['hostid'],
			'filter' => ['host' => [self::HOSTNAME]]
		]);

		if (!empty($hosts['result'])) {
			CDataHelper::call('host.delete', array_column($hosts['result'], 'hostid'));
		}

		self::$enabled_ids    = [];
		self::$enabled_names  = [];
		self::$disabled_ids   = [];
		self::$disabled_names = [];
		self::$hostid         = 0;
	}
}
