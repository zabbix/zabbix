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
 * Test suite for history.push API methods (pushing of history)
 *
 * @required-components server
 * @configurationDataProvider defaultConfigurationProvider
 * @hosts test_agentprotocol
 * @backup items,hosts,item_rtdata,host_rtdata,actions,conditions,operations,opmessage,opmessage_grp,opmessage_usr,opcommand
 */
class testAgentJsonProtocol extends CIntegrationTest {
	const HOSTNAME = 'test_agentprotocol';
	const TRAPPER_KEY = 'trap';
	const SCRIPT_OUTPUT_TIMEOUT = 7;

	private static $hostid;
	private static $sid;
	private static $itemid;
	private static $triggerid;
	private static $actionid;
	private static $interfaceid;
	private static $agent_script_output_file;

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		self::$sid = CAPIHelper::getSessionId();
		$response = $this->call('host.create', [
			[
				'host' => self::HOSTNAME,
				'interfaces' => [
					'type' => INTERFACE_TYPE_AGENT,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
				],
				'groups' => [['groupid' => 4]],
				'status' => HOST_STATUS_MONITORED
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid = $response['result']['hostids'][0];

		$response = $this->call('host.get', [
			'output' => ['host'],
			'hostids' => [self::$hostid],
			'selectInterfaces' => ['interfaceid'],
			'sortfield' => 'hostid'
		]);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('interfaces', $response['result'][0]);
		$this->assertTrue(count($response['result']) == 1);
		self::$interfaceid = $response['result'][0]['interfaces'][0]['interfaceid'];

		$response = $this->call('item.create', [
			[
				'name' => 'ping',
				'key_' => 'agent.ping',
				'hostid' => self::$hostid,
				'type' => ITEM_TYPE_ZABBIX,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => '5s',
				'interfaceid' => self::$interfaceid
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));

		$response = $this->call('item.create', [
			[
				'name' => 'run',
				'key_' => 'system.run[sleep 5 && echo ok]',
				'hostid' => self::$hostid,
				'type' => ITEM_TYPE_ZABBIX,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'timeout' => '6s',
				'delay' => '7s',
				'interfaceid' => self::$interfaceid
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));
		self::$itemid = $response['result']['itemids'][0];

		$response = $this->call('item.create', [
			[
				'name' => self::TRAPPER_KEY,
				'key_' => self::TRAPPER_KEY,
				'type' => ITEM_TYPE_TRAPPER,
				'hostid' => self::$hostid,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));

		$response = $this->call('trigger.create', [
			'description' => 'Trapper received 1',
			'expression' => 'last(/'.self::HOSTNAME.'/'.self::TRAPPER_KEY.')>0'
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertEquals(1, count($response['result']['triggerids']));
		self::$triggerid = $response['result']['triggerids'][0];

		$now = new DateTimeImmutable();
		self::$agent_script_output_file = '/tmp/agent_script_exec_' . $now->format('Hms_dmy');
		$response = $this->call('script.create', [
			'name' => 'Timeout test: script on agent',
			'command' => 'sleep ' . self::SCRIPT_OUTPUT_TIMEOUT .' && echo 1 > ' . self::$agent_script_output_file ,
			'execute_on' => ZBX_SCRIPT_EXECUTE_ON_AGENT,
			'scope' => ZBX_SCRIPT_SCOPE_ACTION,
			'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT
		]);
		$this->assertArrayHasKey('scriptids', $response['result']);
		$scriptid_action = $response['result']['scriptids'][0];

		$response = $this->call('action.create', [
			'esc_period' => '1h',
			'eventsource' => EVENT_SOURCE_TRIGGERS,
			'status' => ACTION_STATUS_ENABLED,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => self::$triggerid
					]
				],
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR
			],
			'name' => 'Trapper received 1',
			'operations' => [
				[
					'esc_period' => 0,
					'esc_step_from' => 1,
					'esc_step_to' => 1,
					'operationtype' => OPERATION_TYPE_COMMAND,
					'opcommand' => [
						'scriptid' => $scriptid_action
					],
					'opcommand_hst' => [
						[
							'hostid'=> self::$hostid
						]
					]
				]
			],
			'pause_suppressed' => 0,
			'recovery_operations' => [
				[
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage' => [
						'default_msg' => 1,
						'mediatypeid' => 0
					],
					'opmessage_grp' => [
						['usrgrpid' => 7]
					]
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));
		self::$actionid = $response['result']['actionids'][0];

		return true;
	}

	/**
	 * Component configuration provider for agent related tests.
	 *
	 * @return array
	 */
	public function defaultConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20,
				'Timeout' => 8
			],
			self::COMPONENT_AGENT => [
				'DebugLevel' => 5,
				'LogFileSize' => 20,
				'Hostname' => self::HOSTNAME,
				'AllowKey' => 'system.run[*]'
			],
			self::COMPONENT_AGENT2 => [
				'DebugLevel' => 5,
				'LogFileSize' => 20,
				'Hostname' => self::HOSTNAME,
				'AllowKey' => 'system.run[*]'
			],
			self::COMPONENT_AGENT_3_0 => [
				'DebugLevel' => 5,
				'LogFileSize' => 20,
				'Timeout' => 10,
				'Hostname' => self::HOSTNAME,
				'EnableRemoteCommands' => 1
			]
		];
	}

	private function checkItemTest($agent_component, $sleep_sec) {
		$item_test_data = [
			'item' => [
				'type' => ITEM_TYPE_ZABBIX,
				'key' => 'system.run[sleep ' . $sleep_sec . ' && echo ok]',
				'timeout' => '6s',
				"value_type" => "4"
			],
			'host' => [
				'tls_connect' => HOST_ENCRYPTION_NONE,
				'proxyid' => '0',
				'interface' => [
					'address' => '127.0.0.1',
					'port' => (int) $this->getConfigurationValue($agent_component, 'ListenPort'),
					'type' => INTERFACE_TYPE_UNKNOWN
				]
			]
		];

		$server = new CZabbixServer('localhost', $this->getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'), 8, 10, ZBX_SOCKET_BYTES_LIMIT);
		$result = $server->testItem($item_test_data, self::$sid);

		$this->assertFalse($result === false);
		$this->assertArrayHasKey('item', $result);
		$this->assertArrayNotHasKey('error', $result['item']);
		$this->assertArrayHasKey('result', $result['item']);
		$this->assertEquals($result['item']['result'], 'ok');
	}

	/**
	 * Test 'Item test' on agent, agent2, agent 3.0
	 *
	 * @backup history_text
	 * @required-components server, agent, agent2, agent_3.0
	 */
	public function testAgentJsonProtocol_testItem() {
		self::$sid = CAPIHelper::getSessionId();
		$this->checkItemTest(self::COMPONENT_AGENT, 4);
		$this->checkItemTest(self::COMPONENT_AGENT2, 4);
		$this->checkItemTest(self::COMPONENT_AGENT_3_0, 2);

		return true;
	}

	private function checkActionScriptOnAgent() {
		$this->sendSenderValue(self::HOSTNAME, self::TRAPPER_KEY, 1);

		$response = $this->callUntilDataIsPresent('alert.get', [
			'output' => ['status'],
			'actionids' => self::$actionid,
			'sortfield' => 'clock',
			'sortorder' => 'DESC',
			'limit' => 1
		], 5, 10);
		$this->assertCount(1, $response['result']);
		$this->assertArrayHasKey('status', $response['result'][0]);
		$this->assertEquals(ALERT_STATUS_SENT, $response['result'][0]['status']);

		sleep(self::SCRIPT_OUTPUT_TIMEOUT);

		$this->assertFileExists(self::$agent_script_output_file);
		unlink(self::$agent_script_output_file);
		$this->sendSenderValue(self::HOSTNAME, self::TRAPPER_KEY, 0);
	}

	/**
	 * Test execution of script on agent
	 *
	 * @required-components server, agent
	 * @backup alerts, history_uint, interface
	 */
	public function testAgentJsonProtocol_testActionScriptOnAgent() {
		$this->checkActionScriptOnAgent();

		return true;
	}

	private function setInterfacePort($port) {
		$this->call('hostinterface.update', [
			'interfaceid' => self::$interfaceid,
			'port' => $port
		]);
	}

	/**
	 * Test execution of script on agent 3.0
	 *
	 * @required-components server, agent_3.0
	 * @backup alerts, history_uint, interface
	 */
	public function testAgentJsonProtocol_testActionScriptOnAgent3_0() {
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->setInterfacePort($this->getConfigurationValue(self::COMPONENT_AGENT_3_0, 'ListenPort'));
		$this->startComponent(self::COMPONENT_SERVER);

		$this->checkActionScriptOnAgent();

		return true;
	}

	/**
	 * Test execution of script on agent2
	 *
	 * @required-components server, agent2
	 * @backup alerts, history_uint, interface
	 */
	public function testAgentJsonProtocol_testActionScriptOnAgent2() {
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->setInterfacePort($this->getConfigurationValue(self::COMPONENT_AGENT2, 'ListenPort'));
		$this->startComponent(self::COMPONENT_SERVER);

		$this->checkActionScriptOnAgent();

		return true;
	}

	private function checkPassiveCheck() {
		$response = $this->callUntilDataIsPresent('history.get', [
			'output' => ['value'],
			'itemids' => [self::$itemid],
			'history' => ITEM_VALUE_TYPE_TEXT,
			'sortorder' => 'DESC',
			'sortfield' => 'clock',
			'limit' => 1
		], 30, 2);
		$this->assertCount(1, $response['result']);
		$this->assertArrayHasKey('value', $response['result'][0]);
		$this->assertEquals('ok', $response['result'][0]['value']);
	}

	/**
	 * Test passive check on agent
	 *
	 * @required-components server, agent
	 */
	public function testAgentJsonProtocol_passiveCheck() {
		$this->checkPassiveCheck();

		return true;
	}

	/**
	 * Test passive check on agent 3.0
	 *
	 * @backup interface
	 * @required-components server, agent_3.0
	 */
	public function testAgentJsonProtocol_passiveCheckAgent3_0() {
		$this->setInterfacePort($this->getConfigurationValue(self::COMPONENT_AGENT_3_0, 'ListenPort'));
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->checkPassiveCheck();

		return true;
	}

	/**
	 * Test passive check on agent2
	 *
	 * @required-components server, agent2
	 */
	public function testAgentJsonProtocol_passiveCheckAgent2() {
		$this->setInterfacePort($this->getConfigurationValue(self::COMPONENT_AGENT2, 'ListenPort'));
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->checkPassiveCheck();

		return true;
	}

	private function checkDiscovery($agent_component) {
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->stopComponent($agent_component);

		$this->call('host.delete', [self::$hostid]);

		$response = $this->call('drule.create', [
			'name' => 'agent_discovery_drule',
			'delay' => '5s',
			'iprange' => '127.0.0.1-2',
			'dchecks' => [
				[
					'type' => SVC_AGENT,
					'key_' => 'system.run[sleep 2 && echo ' . self::HOSTNAME . ']',
					'host_source' => 3,
					'name_source' => 3,
					'ports' => $this->getConfigurationValue($agent_component, 'ListenPort'),
					'uniq' => 1
				]
			]
		]);
		$this->assertArrayHasKey('result', $response, 'Failed to create a discovery rule');
		$this->assertArrayHasKey('druleids', $response['result'], 'Failed to create a discovery rule');
		$this->assertCount(1, $response['result'], 'Failed to create a discovery rule');
		$druleid = $response['result']['druleids'][0];

		$response = $this->call('action.create', [
			'name' => 'agent_discovery_action',
			'eventsource' => EVENT_SOURCE_DISCOVERY,
			'status' => ACTION_STATUS_ENABLED,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_DRULE,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => $druleid
					],
					[
						'conditiontype' => ZBX_CONDITION_TYPE_DSTATUS,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => DOBJECT_STATUS_UP
					]
				],
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR
			],
			'operations' => [
				['operationtype' => OPERATION_TYPE_HOST_ADD]
			]
		]);
		$this->assertArrayHasKey('result', $response, 'Failed to create a discovery action');
		$this->assertArrayHasKey('actionids', $response['result'], 'Failed to create a discovery action');
		$this->assertCount(1, $response['result'], 'Failed to create a discovery action');

		$this->startComponent(self::COMPONENT_SERVER);
		$this->startComponent($agent_component);

		$response = $this->callUntilDataIsPresent('host.get', [
			'output' => 'extend'
		], 10, 5);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('host', $response['result'][0]);
	}

	/**
	 * Test discovery check on agent
	 *
	 * @backup drules,dchecks,dhosts,dservices,hosts
	 * @required-components server, agent
	 */
	public function testAgentJsonProtocol_discoveryAgent() {
		$this->checkDiscovery(self::COMPONENT_AGENT);

		return true;
	}

	/**
	 * Test discovery check on agent 3.0
	 *
	 * @backup drules,dchecks,dhosts,dservices,hosts
	 * @required-components server, agent_3.0
	 */
	public function testAgentJsonProtocol_discoveryAgent3_0() {
		$this->checkDiscovery(self::COMPONENT_AGENT_3_0);

		return true;
	}

	/**
	 * Test discovery check on agent2
	 *
	 * @backup drules,dchecks,dhosts,dservices,hosts
	 * @required-components server, agent2
	 */
	public function testAgentJsonProtocol_discoveryAgent2() {
		$this->checkDiscovery(self::COMPONENT_AGENT2);

		return true;
	}

	/**
	 * Test Zabbix Get functionality
	 *
	 * @required-components agent, agent_3.0, agent2
	 */
	public function testAgentJsonProtocol_zabbixGet() {
		$ports = [
			$this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort'),
			$this->getConfigurationValue(self::COMPONENT_AGENT_3_0, 'ListenPort'),
			$this->getConfigurationValue(self::COMPONENT_AGENT2, 'ListenPort')
		];

		foreach ($ports as $port) {
			$output = shell_exec(PHPUNIT_BASEDIR . '/bin/zabbix_get -s 127.0.0.1 -p ' . $port .
				' -k "system.run[sleep 5 && echo ok]" -t 7');

			$this->assertNotNull($output);
			$this->assertNotFalse($output);
			$this->assertStringContainsString('ok', $output);

		}

		return true;
	}
}
