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
require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * Test suite for macros with regular expression context.
 *
 * This test is based on ZBX-25419.
 *
 * https://www.zabbix.com/documentation/7.0/en/manual/config/macros/user_macros_context
 * > Note that a macro with regular expression context can only be defined in user macro configuration.
 * > If the regex: prefix is used elsewhere as user macro context, like in a trigger expression,
 * > it will be treated as static context.
 *
 * > If more than one user macro with context exists, Zabbix will try to match the simple context macros first and then
 * > context macros with regular expressions in an undefined order.
 * > If a macro with its context is not found on host, linked templates or globally, then the macro without context is
 * searched for.
 *
 * Test case X: Macros with regex context in trigger expressions.
 *
 * 1. Create host with the following macros:
 *     {$TEST} =>33
 *     {$TEST:regex:x} => 15, this regex matches any string which contains symbols "x".
 * 2. Create a trapper item with key "trap" on this host.
 * 3. Create a trigger that will use the macro with
 *    trigger name
 *        trigger {$TEST:regex:abc}
 *    trigger expression:
 *        last(/<host>/<trapper item key>)>{$TEST:regex:abc}
 * 4. Send a value to the trapper item, that would be higher then the value of the context macro
 * but lower than the value of the plain user macro, for example 17.
 * 5. Zabbix should treat the macro "{$TEST:regex:abc}" in trigger expression as a macro with static
 * context "regex:abc" which contains symbol "x" and which should be resolved to "15".
 * The problem event should start because the trigger expression becomes true.
 * 6. Send a value to the trapper item, that would be higher then the value of the plain user macro, for example 34.
 * 7. Make sure that the problem still exists.
 *
 * Test case X: Macros with regex context in Zabbix agent item keys.
 *
 * 1. Reuse the host with its macros from the trigger expression test. Add host interface for Zabbix agent.
 * 2. Create Zabbix agent item with key
 *     system.run["echo {$TEST} {$TEST:regex:abc} {$TEST:regex:x} {$TEST:regex:y}",wait]
 * 3. Get item values an make sure the macros were expanded correctly.
 *
 * Test case Y:  Macros with regex context in trigger expressions.
 *
 * It is different from the test case X in with macro configuration.
 *
 * 1. Create host with the following macros:
 *     {$TEST} =>15
 *     {$TEST:regex:y} => 33, this regex matches any string which contains symbols "y".
 * 2. Create a trapper item with key "trap" on this host.
 * 3. Create a trigger that will use the macro with
 *    trigger name
 *        trigger {$TEST:regex:abc}
 *    trigger expression:
 *        last(/<host>/<trapper item key>)>{$TEST:regex:abc}
 * 4. Send a value to the trapper item, that would be higher then the value of the context macro
 * but lower than the value of the plain user macro, for example 17.
 * 5. Zabbix should treat the macro "{$TEST:regex:abc}" in trigger expression as a macro with static
 * context "regex:abc" which contains symbol "x" and which should be resolved to "15". It should not match
 * the macro {$TEST:regex:y} => 33 in configuration since its static context "regex:abc" does not have the symbol "y".
 * The problem event should start because the trigger expression becomes true.
 * 6. Send a value to the trapper item, that would be higher then the value of the plain user macro, for example 34.
 * 7. Make sure that the problem still exists.
 *
 * Test case Y: Macros with regex context in Zabbix agent item keys.
 *
 * 1. Reuse the host with its macros from the trigger expression test. Add host interface for Zabbix agent.
 * 2. Create Zabbix agent item with key
 *     system.run["echo {$TEST} {$TEST:regex:abc} {$TEST:regex:x} {$TEST:regex:y}",wait]
 * 3. Get item values an make sure the macros were expanded correctly.
 *
 * @onAfter clearData
 */
class testUserMacrosWithContextRegex extends CIntegrationTest {
	const TEMPLATE_NAME_T1 = 'T1'; // template level 1
	const HOSTNAME = 'host_user_macros_with_context_regex';

	const TRAPPER_ITEM_NAME_H = 'trapper item H';
	const TRAPPER_ITEM_NAME_T1 = 'trapper item T1';
	const TRAPPER_ITEM_NAME_G = 'trapper item G';

	const TRAPPER_ITEM_KEY_H = 'trap_h';
	const TRAPPER_ITEM_KEY_T1 = 'trap_t1';
	const TRAPPER_ITEM_KEY_G = 'trap_g';

	const TRIGGER_EXPRESSION_H = 'last(/'.self::HOSTNAME.'/'.self::TRAPPER_ITEM_KEY_H.')>{$HOSTMACRO:regex:abc}';
	const TRIGGER_EXPRESSION_T1 = 'last(/'.self::HOSTNAME.'/'.self::TRAPPER_ITEM_KEY_T1.')>{$TEMPLATEMACRO1:regex:abc}';
	const TRIGGER_EXPRESSION_G = 'last(/'.self::HOSTNAME.'/'.self::TRAPPER_ITEM_KEY_G.')>{$GLOBALMACRO:regex:abc}';

	const TRIGGER_EVT_NAME_H = 'event name|{$HOSTMACRO}|{$HOSTMACRO:regex:abc}|{$HOSTMACRO:regex:x}|';
	const TRIGGER_EVT_NAME_T1 = 'event name|{$TEMPLATEMACRO1}|{$TEMPLATEMACRO1:regex:abc}|{$TEMPLATEMACRO1:regex:x}|';
	const TRIGGER_EVT_NAME_G = 'event name|{$GLOBALMACRO}|{$GLOBALMACRO:regex:abc}|{$GLOBALMACRO:regex:x}|';

	const AGENT_ITEM_NAME_H = 'agent item H {$HOSTMACRO} {$HOSTMACRO:regex:abc} {$HOSTMACRO:regex:x} {$HOSTMACRO:regex:y}';
	const AGENT_ITEM_NAME_T1 = 'agent item T1 {$TEMPLATEMACRO1} {$TEMPLATEMACRO1:regex:abc} {$TEMPLATEMACRO1:regex:x} {$TEMPLATEMACRO1:regex:y}';
	const AGENT_ITEM_NAME_G = 'agent item G {$GLOBALMACRO} {$GLOBALMACRO:regex:abc} {$GLOBALMACRO:regex:x} {$GLOBALMACRO:regex:y}';

	const AGENT_ITEM_KEY_H = 'system.run["echo {$HOSTMACRO} {$HOSTMACRO:regex:abc} {$HOSTMACRO:regex:x} {$HOSTMACRO:regex:y}",wait]';
	const AGENT_ITEM_KEY_T1 = 'system.run["echo {$TEMPLATEMACRO1} {$TEMPLATEMACRO1:regex:abc} {$TEMPLATEMACRO1:regex:x} {$TEMPLATEMACRO1:regex:y}",wait]';
	const AGENT_ITEM_KEY_G = 'system.run["echo {$GLOBALMACRO} {$GLOBALMACRO:regex:abc} {$GLOBALMACRO:regex:x} {$GLOBALMACRO:regex:y}",wait]';

	private static $hostId;
	private static $hostInterfaceId;
	private static $templateId;
	private static $macroIds = [];
	private static $globalMacroIds = [];
	private static $triggerIdH;
	private static $triggerIdT1;
	private static $triggerIdG;
	private static $triggerIds = [];
	private static $agentItemIds = [];

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Create template
		$response = $this->call('template.create', [
			'host' => self::TEMPLATE_NAME_T1,
			'groups' => ['groupid' => 1] // Templates
		]);
		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('templateids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['templateids']);
		self::$templateId = $response['result']['templateids'][0];

		// Create host
		$response = $this->call('host.create', [
			[
				'host' => self::HOSTNAME,
				'interfaces' => [
					'type' => INTERFACE_TYPE_AGENT,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => PHPUNIT_PORT_PREFIX.self::AGENT_PORT_SUFFIX
				],
				'groups' => [['groupid' => 4]], // Zabbix servers
				'templates' => [['templateid' => self::$templateId]],
				'status' => HOST_STATUS_MONITORED
			]
		]);
		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostId = $response['result']['hostids'][0];

		// Get host interface ID for agent checks.
		$response = $this->call('host.get', [
			'output' => ['host'],
			'hostids' => [self::$hostId],
			'selectInterfaces' => ['interfaceid']
		]);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('interfaces', $response['result'][0]);
		$this->assertArrayHasKey(0, $response['result'][0]['interfaces']);
		self::$hostInterfaceId = $response['result'][0]['interfaces'][0]['interfaceid'];

		// Create items
		$response = $this->call('item.create', [
			// trapper items
			[
				'hostid' => self::$hostId,
				'name' => self::TRAPPER_ITEM_NAME_H,
				'key_' => self::TRAPPER_ITEM_KEY_H,
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			[
				'hostid' => self::$templateId,
				'name' => self::TRAPPER_ITEM_NAME_T1,
				'key_' => self::TRAPPER_ITEM_KEY_T1,
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			[
				'hostid' => self::$hostId,
				'name' => self::TRAPPER_ITEM_NAME_G,
				'key_' => self::TRAPPER_ITEM_KEY_G,
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			],
			// agent items
			[
				'hostid' => self::$hostId,
				'interfaceid' => self::$hostInterfaceId,
				'name' => self::AGENT_ITEM_NAME_H,
				'key_' => self::AGENT_ITEM_KEY_H,
				'type' => ITEM_TYPE_ZABBIX,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'delay' => '1s'
			],
			[
				'hostid' => self::$hostId,
				'interfaceid' => self::$hostInterfaceId,
				'name' => self::AGENT_ITEM_NAME_T1,
				'key_' => self::AGENT_ITEM_KEY_T1,
				'type' => ITEM_TYPE_ZABBIX,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'delay' => '1s'
			],
			[
				'hostid' => self::$hostId,
				'interfaceid' => self::$hostInterfaceId,
				'name' => self::AGENT_ITEM_NAME_G,
				'key_' => self::AGENT_ITEM_KEY_G,
				'type' => ITEM_TYPE_ZABBIX,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'delay' => '1s'
			]

		]);
		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('itemids', $response['result']);
		for ($i = 0; $i < 6; $i++) {
			$this->assertArrayHasKey($i, $response['result']['itemids']);
		}

		self::$agentItemIds[] = $response['result']['itemids'][3];
		self::$agentItemIds[] = $response['result']['itemids'][4];
		self::$agentItemIds[] = $response['result']['itemids'][5];

		// Create triggers
		$response = $this->call('trigger.create', [
			'description' => 'trigger_trap',
			'expression' => self::TRIGGER_EXPRESSION_H,
			'event_name' => self::TRIGGER_EVT_NAME_H
		]);
		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['triggerids']);
		self::$triggerIdH = $response['result']['triggerids'][0];
		self::$triggerIds[] = $response['result']['triggerids'][0];

		$response = $this->call('trigger.create', [
			'description' => 'trigger_trap',
			'expression' => self::TRIGGER_EXPRESSION_T1,
			'event_name' => self::TRIGGER_EVT_NAME_T1
		]);
		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['triggerids']);
		self::$triggerIdT1 = $response['result']['triggerids'][0];
		self::$triggerIds[] = $response['result']['triggerids'][0];

		$response = $this->call('trigger.create', [
			'description' => 'trigger_trap',
			'expression' => self::TRIGGER_EXPRESSION_G,
			'event_name' => self::TRIGGER_EVT_NAME_G
		]);
		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['triggerids']);
		self::$triggerIdG = $response['result']['triggerids'][0];
		self::$triggerIds[] = $response['result']['triggerids'][0];
	}

	/**
	 * Component configuration provider for agent related tests.
	 *
	 * @return array
	 */
	public function agentConfigurationProvider() {
		return [
			self::COMPONENT_AGENT => [
				'Hostname' => self::HOSTNAME,
				'AllowKey' => 'system.run[*]'
			]
		];
	}

	/**
	 * Creates host or template user macros.
	*/
	private function usermacroCreate($macros, $hostId) {
		foreach ($macros as $macro => $value) {
			$response = $this->call('usermacro.create', [
				'hostid' => $hostId,
				'macro'  => $macro,
				'value'  => $value
			]);
			$errMsg = "Failed to create host macro " . $macro . " = " . $value;
			$this->assertArrayHasKey('result', $response, $errMsg);
			$this->assertArrayHasKey('hostmacroids', $response['result'], $errMsg);
			$this->assertArrayHasKey(0, $response['result']['hostmacroids'], $errMsg);
			self::$macroIds[] = $response['result']['hostmacroids'][0];
		}
	}

	/**
	 * Creates global user macros.
	*/
	private function usermacroCreateGlobal($macros) {
		foreach ($macros as $macro => $value) {
			$response = $this->call('usermacro.createglobal', [
				'macro'  => $macro,
				'value'  => $value
			]);
			$errMsg = "Failed to create global macro " . $macro . " = " . $value;
			$this->assertArrayHasKey('result', $response, $errMsg);
			$this->assertArrayHasKey('globalmacroids', $response['result'], $errMsg);
			$this->assertArrayHasKey(0, $response['result']['globalmacroids'], $errMsg);
			self::$globalMacroIds[] = $response['result']['globalmacroids'][0];
		}
	}

	/**
	 * Cleans up all previously defined host or template user macros.
	*/
	private function usermacroCleanup() {
		if (!empty(self::$macroIds)) {
			CDataHelper::call('usermacro.delete', self::$macroIds);
			self::$macroIds = [];
		}
	}

	/**
	 * Cleans up all previously defined global user macros.
	*/
	private function usermacroCleanupGlobal() {
		if (!empty(self::$globalMacroIds)) {
			CDataHelper::call('usermacro.deleteglobal', self::$globalMacroIds);
			self::$globalMacroIds = [];
		}
	}

	/**
	 * Test macro expansion in event name
	 */
	private function testEventName(int $trigggerId, string $expectedEvtName) {
		$response = $this->callUntilDataIsPresent('problem.get', [
			'output' => ['name'],
			'objectids' => $trigggerId,
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER
		]);
		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey(0, $response['result']);
		$event = $response['result'][0];
		$this->assertArrayHasKey('name', $event, 'Failed to get event name');
		$this->assertEquals($expectedEvtName, $event['name'], 'Unexpected event name');
	}

	/**
	 * Test macro expansion in event names
	 */
	private function testAgentItemKey(string $expectedItemValue) {
		$wait_iterations = 10;
		$wait_iteration_delay = 2;

		for ($r = 0; $r < $wait_iterations; $r++) {

			$response = $this->call('item.get', [
				'output' => ['lastvalue', 'lastclock'],
				'itemids' => self::$agentItemIds,
				'preservekeys' => true
			]);
			$this->assertArrayHasKey('result', $response);

			$all_collected = true;

			foreach (self::$agentItemIds as $itemId) {
				$this->assertArrayHasKey($itemId, $response['result']);
				$this->assertArrayHasKey('lastclock', $response['result'][$itemId]);
				if ($response['result'][$itemId]['lastclock'] == 0) {
					$all_collected = false;
				}
			}

			if ($all_collected) {
				break;
			}

			sleep($wait_iteration_delay);
		}

		$lastValues =[];
		foreach (self::$agentItemIds as $itemId) {
			$this->assertArrayHasKey('lastvalue', $response['result'][$itemId]);
			$lastValues[] = $response['result'][$itemId]['lastvalue'];
		}

		$this->assertEquals($expectedItemValue, $lastValues[0], 'Test case for macros {$HOSTMACRO} {$HOSTMACRO:regex:abc} {$HOSTMACRO:regex:x} {$HOSTMACRO:regex:y} in agent item key failed');
		$this->assertEquals($expectedItemValue, $lastValues[1], 'Test case for macros {$TEMPLATEMACRO1} {$TEMPLATEMACRO1:regex:abc} {$TEMPLATEMACRO1:regex:x} {$TEMPLATEMACRO1:regex:y} in agent item key failed');
		$this->assertEquals($expectedItemValue, $lastValues[2], 'Test case for macros {$GLOBALMACRO} {$GLOBALMACRO:regex:abc} {$GLOBALMACRO:regex:x} {$GLOBALMACRO:regex:y} in agent item key failed');
	}

	private function testItemNamesResolutionByAPI($expectedResolution) {
		$response = $this->call('item.get', [
			'output' => ['name_resolved'],
			'itemids' => self::$agentItemIds,
			'preservekeys' => true
		]);
		$this->assertArrayHasKey('result', $response);

		$namesResolved =[];
		foreach (self::$agentItemIds as $itemId) {
			$this->assertArrayHasKey('name_resolved', $response['result'][$itemId]);
			$namesResolved[] = $response['result'][$itemId]['name_resolved'];
		}

		$this->assertEquals('agent item H '.$expectedResolution, $namesResolved[0], 'Test case for macros {$HOSTMACRO} {$HOSTMACRO:regex:abc} {$HOSTMACRO:regex:x} {$HOSTMACRO:regex:y} in agent item name failed');
		$this->assertEquals('agent item T1 '.$expectedResolution, $namesResolved[1], 'Test case for macros {$TEMPLATEMACRO1} {$TEMPLATEMACRO1:regex:abc} {$TEMPLATEMACRO1:regex:x} {$TEMPLATEMACRO1:regex:y} in agent item name failed');
		$this->assertEquals('agent item G '.$expectedResolution, $namesResolved[2], 'Test case for macros {$GLOBALMACRO} {$GLOBALMACRO:regex:abc} {$GLOBALMACRO:regex:x} {$GLOBALMACRO:regex:y} in agent item name failed');
	}

	/**
	 * @required-components server, agent
	 * @configurationDataProvider agentConfigurationProvider
	 */
	public function testUserMacrosWithContextRegex_macrosInEventNamesAndAgentItemKeysX() {
		// Configure macros and reload config cache
		$this->usermacroCreate([
			'{$HOSTMACRO}' => '33',
			'{$HOSTMACRO:regex:x}' => '15'
		], self::$hostId);
		$this->usermacroCreate([
			'{$TEMPLATEMACRO1}' => '33',
			'{$TEMPLATEMACRO1:regex:x}' => '15'
		], self::$templateId);
		$this->usermacroCreateGlobal([
			'{$GLOBALMACRO}' => '33',
			'{$GLOBALMACRO:regex:x}' => '15'
		]);
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		// Perform test
		$senderValues = [
			['host' => self::HOSTNAME, 'key' => self::TRAPPER_ITEM_KEY_H, 'value' => 17],
			['host' => self::HOSTNAME, 'key' => self::TRAPPER_ITEM_KEY_T1, 'value' => 17],
			['host' => self::HOSTNAME, 'key' => self::TRAPPER_ITEM_KEY_G, 'value' => 17]
		];

		$this->sendSenderValues($senderValues);

		$this->testEventName(self::$triggerIdH, 'event name|33|15|15|');
		$this->testEventName(self::$triggerIdT1, 'event name|33|15|15|');
		$this->testEventName(self::$triggerIdG, 'event name|33|15|15|');

		$senderValues = [
			['host' => self::HOSTNAME, 'key' => self::TRAPPER_ITEM_KEY_H, 'value' => 34],
			['host' => self::HOSTNAME, 'key' => self::TRAPPER_ITEM_KEY_T1, 'value' => 34],
			['host' => self::HOSTNAME, 'key' => self::TRAPPER_ITEM_KEY_G, 'value' => 34]
		];

		$this->sendSenderValues($senderValues);

		$this->testEventName(self::$triggerIdH, 'event name|33|15|15|');
		$this->testEventName(self::$triggerIdT1, 'event name|33|15|15|');
		$this->testEventName(self::$triggerIdG, 'event name|33|15|15|');

		$this->testAgentItemKey('33 15 15 15');
	}

	/**
	 * Test macro resolution in item names by API.
	 * It helps to test consistency in macro resolution between server and API.
	 */
	public function testUserMacrosWithContextRegex_macrosInItemNamesResolutionByAPIX() {

		$this->testItemNamesResolutionByAPI('33 15 15 15');
	}

	/**
	 * @required-components server, agent
	 * @configurationDataProvider agentConfigurationProvider
	 */
	public function testUserMacrosWithContextRegex_macrosInEventNamesAndAgentItemKeysY() {
		// Cleanup for the previous test is done here to make sure it runs even if the previous test failed.

		// Recover from problems started during the previous test
		$senderValues = [
			['host' => self::HOSTNAME, 'key' => self::TRAPPER_ITEM_KEY_H, 'value' => 1],
			['host' => self::HOSTNAME, 'key' => self::TRAPPER_ITEM_KEY_T1, 'value' => 1],
			['host' => self::HOSTNAME, 'key' => self::TRAPPER_ITEM_KEY_G, 'value' => 1]
		];

		$this->sendSenderValues($senderValues);

		// Cleanup macros
		$this->usermacroCleanup();
		$this->usermacroCleanupGlobal();

		// Create new macros
		$this->usermacroCreate([
			'{$HOSTMACRO}' => '15',
			'{$HOSTMACRO:regex:y}' => '33'
		], self::$hostId);
		$this->usermacroCreate([
			'{$TEMPLATEMACRO1}' => '15',
			'{$TEMPLATEMACRO1:regex:y}' => '33'
		], self::$templateId);
		$this->usermacroCreateGlobal([
			'{$GLOBALMACRO}' => '15',
			'{$GLOBALMACRO:regex:y}' => '33'
		]);

		// Reload configuration cache
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		// Perform test
		$senderValues = [
			['host' => self::HOSTNAME, 'key' => self::TRAPPER_ITEM_KEY_H, 'value' => 17],
			['host' => self::HOSTNAME, 'key' => self::TRAPPER_ITEM_KEY_T1, 'value' => 17],
			['host' => self::HOSTNAME, 'key' => self::TRAPPER_ITEM_KEY_G, 'value' => 17]
		];

		$this->sendSenderValues($senderValues);

		$this->testEventName(self::$triggerIdH, 'event name|15|15|15|');
		$this->testEventName(self::$triggerIdT1, 'event name|15|15|15|');
		$this->testEventName(self::$triggerIdG, 'event name|15|15|15|');

		$senderValues = [
			['host' => self::HOSTNAME, 'key' => self::TRAPPER_ITEM_KEY_H, 'value' => 34],
			['host' => self::HOSTNAME, 'key' => self::TRAPPER_ITEM_KEY_T1, 'value' => 34],
			['host' => self::HOSTNAME, 'key' => self::TRAPPER_ITEM_KEY_G, 'value' => 34]
		];

		$this->sendSenderValues($senderValues);

		$this->testEventName(self::$triggerIdH, 'event name|15|15|15|');
		$this->testEventName(self::$triggerIdT1, 'event name|15|15|15|');
		$this->testEventName(self::$triggerIdG, 'event name|15|15|15|');

		$this->testAgentItemKey('15 15 15 33');
	}

	/**
	 * Test macro resolution in item names by API.
	 * It helps to test consistency in macro resolution between server and API.
	 */
	public function testUserMacrosWithContextRegex_macrosInItemNamesResolutionByAPIY() {

		$this->testItemNamesResolutionByAPI('15 15 15 33');
	}

	/**
	 * Delete data objects created for this test suite
	 *
	 */
	public static function clearData(): void {
		// Triggers, items, user macros at hosts and templates should be cascade deleted.
		if (!empty(self::$globalMacroIds)) {
			CDataHelper::call('usermacro.deleteglobal', self::$globalMacroIds);
		}
		CDataHelper::call('host.delete', [self::$hostId]);
		CDataHelper::call('template.delete', [self::$templateId]);
	}
}
