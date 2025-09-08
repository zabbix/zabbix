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
 * Test suite for macros with context. It tests the most basic use case from the documentation page.
 * Trapper items are used instead of low level discovery in this test for portability.
 *
 * https://www.zabbix.com/documentation/current/en/manual/config/macros/user_macros_context
 *
 * The following macros are defined:
 *     {$LOW_SPACE_LIMIT} = 10
 *     {$LOW_SPACE_LIMIT:/home} = 20
 *     {$LOW_SPACE_LIMIT:regex:"^/[a-z]+$"} = 30
 *
 * Trapper items are created with triggers having the following expressions:
 *
 *     last(/<host>'/trap1)={$LOW_SPACE_LIMIT}
 *     last(/<host>'/trap2)={$LOW_SPACE_LIMIT:/home}
 *     last(/<host>'/trap3)={$LOW_SPACE_LIMIT:/etc}
 *     last(/<host>'/trap4)={$LOW_SPACE_LIMIT:/tmp}
 *     last(/<host>'/trap5)={$LOW_SPACE_LIMIT:/var}
 *     last(/<host>'/trap5)={$LOW_SPACE_LIMIT:404}
 *
 * Agent items are created with the following keys
 *
 *     system.run["echo {$LOW_SPACE_LIMIT}",wait]
 *     system.run["echo {$LOW_SPACE_LIMIT:/home}",wait]
 *     system.run["echo {$LOW_SPACE_LIMIT:/etc}",wait]
 *     system.run["echo {$LOW_SPACE_LIMIT:/tmp}",wait]
 *     system.run["echo {$LOW_SPACE_LIMIT:/var}",wait]
 *     system.run["echo {$LOW_SPACE_LIMIT:404}",wait
 *
 * The macros in trigger expressions, agent item keys are expected to be expanded like so:
 *     {$LOW_SPACE_LIMIT} => 10
 *     {$LOW_SPACE_LIMIT:/home} = > 20
 *     {$LOW_SPACE_LIMIT:/etc}, {$LOW_SPACE_LIMIT:/tmp}, {$LOW_SPACE_LIMIT:/var} => 30
 *     {$LOW_SPACE_LIMIT:404} => 10
 *
 * Values are sent to trappers which so trigger expressions are expected to become true.
 * Problems for all triggers should start.
 *
 * Agent items are expected to return different values depending on the macro context.
 *
 * @onAfter clearData
 */
class testUserMacrosWithContext extends CIntegrationTest {
	const HOSTNAME = 'host_user_macros_with_context';
	const TRAPPER_ITEMS_COUNT = 6;
	const AGENT_ITEMS_COUNT = 6;
	private static $hostId;
	private static $hostInterfaceId;
	private static $macroIds = [];
	private static $triggerIds = [];
	private static $agentItemIds = [];

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
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

		// Create trapper items
		$trapperItems = [];
		for ($i = 0; $i < self::TRAPPER_ITEMS_COUNT; $i++) {
			$trapperItems[] = [
				'hostid' => self::$hostId,
				'name' => sprintf("trapper item %d", $i+1),
				'key_' => sprintf("trap%d", $i+1),
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			];
		}
		$response = $this->call('item.create', $trapperItems);
		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('itemids', $response['result']);
		for ($i = 0; $i < self::TRAPPER_ITEMS_COUNT; $i++) {
			$this->assertArrayHasKey($i, $response['result']['itemids']);
		}

		// Create agent items
		$agentItemNamesKeys = [
			// name, key
			['agent item {$LOW_SPACE_LIMIT}',	'system.run["echo {$LOW_SPACE_LIMIT}",wait]'],
			['agent item {$LOW_SPACE_LIMIT:/home}',	'system.run["echo {$LOW_SPACE_LIMIT:/home}",wait]'],
			['agent item {$LOW_SPACE_LIMIT:/etc}',	'system.run["echo {$LOW_SPACE_LIMIT:/etc}",wait]'],
			['agent item {$LOW_SPACE_LIMIT:/tmp}',	'system.run["echo {$LOW_SPACE_LIMIT:/tmp}",wait]'],
			['agent item {$LOW_SPACE_LIMIT:/var}',	'system.run["echo {$LOW_SPACE_LIMIT:/var}",wait]'],
			['agent item {$LOW_SPACE_LIMIT:404}',	'system.run["echo {$LOW_SPACE_LIMIT:404}",wait]']
		];
		$agentItems = [];
		for ($i = 0; $i < self::AGENT_ITEMS_COUNT; $i++) {
			$agentItems[] = [
				'hostid' => self::$hostId,
				'interfaceid' => self::$hostInterfaceId,
				'name' => $agentItemNamesKeys[$i][0],
				'key_' => $agentItemNamesKeys[$i][1],
				'type' => ITEM_TYPE_ZABBIX,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'delay' => '1s'
			];
		}
		$response = $this->call('item.create', $agentItems);
		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('itemids', $response['result']);
		for ($i = 0; $i < self::AGENT_ITEMS_COUNT; $i++) {
			$this->assertArrayHasKey($i, $response['result']['itemids']);
			self::$agentItemIds[] = $response['result']['itemids'][$i];
		}

		// Create triggers, one trigger for each trapper item
		$triggerExpressions = [
			'last(/'.self::HOSTNAME.'/trap1)={$LOW_SPACE_LIMIT}',
			'last(/'.self::HOSTNAME.'/trap2)={$LOW_SPACE_LIMIT:/home}',
			'last(/'.self::HOSTNAME.'/trap3)={$LOW_SPACE_LIMIT:/etc}',
			'last(/'.self::HOSTNAME.'/trap4)={$LOW_SPACE_LIMIT:/tmp}',
			'last(/'.self::HOSTNAME.'/trap5)={$LOW_SPACE_LIMIT:/var}',
			'last(/'.self::HOSTNAME.'/trap6)={$LOW_SPACE_LIMIT:404}'
		];
		$triggers = [];
		for ($i = 0; $i < self::TRAPPER_ITEMS_COUNT; $i++) {
			$triggers[] = [
				'expression' => $triggerExpressions[$i],
				'event_name' => sprintf("event%d", $i+1),
				'description' => 'description'
				// 'description' => sprintf("description %d", $i+1)
			];
		}
		$response = $this->call('trigger.create', $triggers);
		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('triggerids', $response['result']);
		for ($i = 0; $i < self::TRAPPER_ITEMS_COUNT; $i++) {
			$this->assertArrayHasKey($i, $response['result']['triggerids']);
			self::$triggerIds[] = $response['result']['triggerids'][$i];
		}

		// Create macros
		$response = $this->call('usermacro.create', [

			[
				'hostid' => self::$hostId,
				'macro'  => '{$LOW_SPACE_LIMIT}',
				'value'  => '10'
			],
			[
				'hostid' => self::$hostId,
				'macro'  => '{$LOW_SPACE_LIMIT:/home}',
				'value'  => '20'
			],
			[
				'hostid' => self::$hostId,
				'macro'  => '{$LOW_SPACE_LIMIT:regex:"^/[a-z]+$"}',
				'value'  => '30'
			]
		]);
		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('hostmacroids', $response['result']);
		for ($i = 0; $i < 3; $i++) {
			$this->assertArrayHasKey($i, $response['result']['hostmacroids']);
			self::$macroIds[] = $response['result']['hostmacroids'][$i];
		}
	}

	/**
	 *
	 * @required-components server, agent
	 *
	 * Note: agent is not required for this test.
	 * However, starting agent when server is started is the easiest way to ensure that agent items configured for
	 * other test cases do not become unavailable after failing with network errors.
	 */
	public function testUserMacrosWithContext_inTriggerExpressions() {
		$senderValues = [
			['host' => self::HOSTNAME, 'key' => 'trap1', 'value' => 10],
			['host' => self::HOSTNAME, 'key' => 'trap2', 'value' => 20],
			['host' => self::HOSTNAME, 'key' => 'trap3', 'value' => 30],
			['host' => self::HOSTNAME, 'key' => 'trap4', 'value' => 30],
			['host' => self::HOSTNAME, 'key' => 'trap5', 'value' => 30],
			['host' => self::HOSTNAME, 'key' => 'trap6', 'value' => 10]
		];

		$this->sendSenderValues($senderValues);

		$response = $this->callUntilDataIsPresent('problem.get', [
			'output' => ['name'],
			'hostids' => [self::$hostId],
			'source' => EVENT_SOURCE_TRIGGERS,
			'object' => EVENT_OBJECT_TRIGGER
		]);
		$this->assertArrayHasKey('result', $response);
		$evtNames = [];
		for ($i = 0; $i < count($response['result']); $i++) {
			$this->assertArrayHasKey($i, $response['result']);
			$this->assertArrayHasKey('name', $response['result'][$i]);
			$evtNames[] = $response['result'][$i]['name'];
		}

		$this->assertContains('event1', $evtNames, 'Test case for macro {$LOW_SPACE_LIMIT} in trigger expression failed');
		$this->assertContains('event2', $evtNames, 'Test case for macro {$LOW_SPACE_LIMIT:/home} in trigger expression failed');
		$this->assertContains('event3', $evtNames, 'Test case for macro {$LOW_SPACE_LIMIT:/etc} in trigger expression failed');
		$this->assertContains('event4', $evtNames, 'Test case for macro {$LOW_SPACE_LIMIT:/tmp} in trigger expression failed');
		$this->assertContains('event5', $evtNames, 'Test case for macro {$LOW_SPACE_LIMIT:/var} in trigger expression failed');
		$this->assertContains('event6', $evtNames, 'Test case for macro {$LOW_SPACE_LIMIT:404} in trigger expression failed');
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
	 *
	 * @required-components server, agent
	 * @configurationDataProvider agentConfigurationProvider
	 */
	public function testUserMacrosWithContext_inAgentItemKeys() {
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

		$this->assertEquals('10', $lastValues[0], 'Test case for macro {$LOW_SPACE_LIMIT} in agent item key failed');
		$this->assertEquals('20', $lastValues[1], 'Test case for macro {$LOW_SPACE_LIMIT:/home} in agent item key failed');
		$this->assertEquals('30', $lastValues[2], 'Test case for macro {$LOW_SPACE_LIMIT:/etc} in agent item key failed');
		$this->assertEquals('30', $lastValues[3], 'Test case for macro {$LOW_SPACE_LIMIT:/tmp} in agent item key failed');
		$this->assertEquals('30', $lastValues[4], 'Test case for macro {$LOW_SPACE_LIMIT:/var} in agent item key failed');
		$this->assertEquals('10', $lastValues[5], 'Test case for macro {$LOW_SPACE_LIMIT:404} in agent item key failed');
	}

	/**
	 * Test macro resolution in item names by API.
	 * It helps to test consistency in macro resolution between server and API.
	 */
	public function testUserMacrosWithContext_inItemNamesResolutionByAPI() {
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

		$this->assertEquals('agent item 10', $namesResolved[0], 'Test case for macro {$LOW_SPACE_LIMIT} in agent item name failed');
		$this->assertEquals('agent item 20', $namesResolved[1], 'Test case for macro {$LOW_SPACE_LIMIT:/home} in agent item name failed');
		$this->assertEquals('agent item 30', $namesResolved[2], 'Test case for macro {$LOW_SPACE_LIMIT:/etc} in agent item name failed');
		$this->assertEquals('agent item 30', $namesResolved[3], 'Test case for macro {$LOW_SPACE_LIMIT:/tmp} in agent item name failed');
		$this->assertEquals('agent item 30', $namesResolved[4], 'Test case for macro {$LOW_SPACE_LIMIT:/var} in agent item name failed');
		$this->assertEquals('agent item 10', $namesResolved[5], 'Test case for macro {$LOW_SPACE_LIMIT:404} in agent item name failed');
	}

	/**
	 * Delete data objects created for this test suite
	 *
	 */
	public static function clearData(): void {
		// Triggers, items, and user macros should be cascade deleted.
		CDataHelper::call('host.delete', [self::$hostId]);
	}
}
