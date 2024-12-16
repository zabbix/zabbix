<?php
/*
** Copyright (C) 2001-2024 Zabbix SIA
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

class CAutoregClient extends CZabbixClient {
	public function sendRequest($host, $ip) {
		parent::request([
			'request' => 'active checks',
			'host' => $host,
			'ip' => $ip
		]);
	}
}

/**
 * Test suite for action notifications
 *
 * @required-components server, agent
 * @configurationDataProvider defaultConfigurationProvider
 * @onAfter clearData
 * @hosts test_hostconn
 */
class testHostConnMacroValidation extends CIntegrationTest {

	private static $hostid;
	private static $triggerid;
	private static $triggerid_action;
	private static $triggerid_neg;
	private static $triggerid_action_neg;
	private static $trigger_actionid;
	private static $trigger_actionid_neg;
	private static $scriptid_action;
	private static $scriptid;
	private static $drule_actionid;
	private static $druleid;
	private static $hostmacroid;
	private static $interfaceid;
	private static $trap2_itemid;
	private static $ext_itemid;

	const ITEM_TRAP = 'trap1';
	const ITEM_TRAP2 = 'trap2_allowedhosts';
	const ITEM_EXT = 'item_external';
	const HOST_NAME = 'test_hostconn';

	/**
	 * Prematurely enable global scripts so prepareData wouldn't fail.
	 *
	 */
	private function updateServerStatus() {
		$server_status = [
			"version" => ZABBIX_VERSION,
			"configuration" => [
				"enable_global_scripts" => true,
				"allow_software_update_check" => true
			]
		];

		DBexecute("update config set server_status='".json_encode($server_status)."'");
	}

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		$response = $this->call('host.create', [
			'host' => self::HOST_NAME,
			'interfaces' => [
				[
					'type' => 1,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
				]
			],
			'groups' => [
				[
					'groupid' => 4
				]
			]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid = $response['result']['hostids'][0];

		$response = $this->call('host.get', [
			'output' => ['host'],
			'hostids' => [self::$hostid],
			'selectInterfaces' => ['interfaceid']
		]);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('interfaces', $response['result'][0]);
		$this->assertArrayHasKey(0, $response['result'][0]['interfaces']);
		self::$interfaceid = $response['result'][0]['interfaces'][0]['interfaceid'];

		$response = $this->call('item.create', [
			'hostid' => self::$hostid,
			'name' => self::ITEM_TRAP,
			'key_' => self::ITEM_TRAP,
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_UINT64
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));

		$response = $this->call('trigger.create', [
			'description' => 'Trigger for HOST.CONN test',
			'expression' => 'last(/'.self::HOST_NAME.'/'.self::ITEM_TRAP.')>0'
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertCount(1, $response['result']['triggerids']);
		self::$triggerid = $response['result']['triggerids'][0];

		$response = $this->call('trigger.create', [
			'description' => 'Trigger for HOST.CONN test via action',
			'expression' => 'last(/'.self::HOST_NAME.'/'.self::ITEM_TRAP.')>100'
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertCount(1, $response['result']['triggerids']);
		self::$triggerid_action = $response['result']['triggerids'][0];

		$response = $this->call('trigger.create', [
			'description' => 'Trigger for negative HOST.CONN test',
			'expression' => 'last(/'.self::HOST_NAME.'/'.self::ITEM_TRAP.')>0'
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertCount(1, $response['result']['triggerids']);
		self::$triggerid_neg = $response['result']['triggerids'][0];

		$response = $this->call('trigger.create', [
			'description' => 'Trigger for negative HOST.CONN test via action',
			'expression' => 'last(/'.self::HOST_NAME.'/'.self::ITEM_TRAP.')>2000'
		]);
		$this->assertArrayHasKey('triggerids', $response['result']);
		$this->assertCount(1, $response['result']['triggerids']);
		self::$triggerid_action_neg = $response['result']['triggerids'][0];

		$response = $this->call('usermacro.create', [
			'hostid' => self::$hostid,
			'macro' => '{$INJADDR}',
			'value' => '127.0.0.1'
		]);
		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('hostmacroids', $response['result']);
		self::$hostmacroid = $response['result']['hostmacroids'][0];

		$response = $this->call('hostinterface.update', [
			'interfaceid' => self::$interfaceid,
			'useip' => 0,
			'dns' => '{$INJADDR}',
			'ip' => ''
		]);
		$this->assertArrayHasKey('interfaceids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['interfaceids']);
		self::$interfaceid = $response['result']['interfaceids'][0];

		$this->updateServerStatus();

		$response = $this->call('script.create', [
			'name' => 'inj test',
			'command' => 'echo -n hello {HOST.CONN}',
			'execute_on' => ZBX_SCRIPT_EXECUTE_ON_SERVER,
			'scope' => ZBX_SCRIPT_SCOPE_HOST,
			'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT
		]);
		$this->assertArrayHasKey('scriptids', $response['result']);
		self::$scriptid = $response['result']['scriptids'][0];

		$response = $this->call('script.create', [
			'name' => 'inj test action',
			'command' => 'echo -n hello {HOST.CONN}',
			'execute_on' => ZBX_SCRIPT_EXECUTE_ON_SERVER,
			'scope' => ZBX_SCRIPT_SCOPE_ACTION,
			'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT
		]);
		$this->assertArrayHasKey('scriptids', $response['result']);
		self::$scriptid_action = $response['result']['scriptids'][0];

		DBexecute("update config set server_status=''");

		$response = $this->call('action.create', [
			'esc_period' => '1m',
			'eventsource' => EVENT_SOURCE_TRIGGERS,
			'status' => 0,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => self::$triggerid_action
					]
				],
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR
			],
			'name' => 'action_trigger_trap',
			'operations' => [
				[
					'operationtype' => OPERATION_TYPE_COMMAND,
					'esc_period' => '0s',
					'esc_step_from' => 1,
					'esc_step_to' => 2,
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
					'opcommand_grp' => [
						[
							'groupid' => 4
						]
					],
					'opcommand' => [
						'scriptid' => self::$scriptid_action
					]
				]
			],
			'pause_suppressed' => 0
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));
		self::$trigger_actionid = $response['result']['actionids'][0];

		$response = $this->call('action.create', [
			'esc_period' => '1m',
			'eventsource' => EVENT_SOURCE_TRIGGERS,
			'status' => 0,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_TRIGGER,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => self::$triggerid_action_neg
					]
				],
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR
			],
			'name' => 'Action on trigger (negative case)',
			'operations' => [
				[
					'operationtype' => OPERATION_TYPE_COMMAND,
					'esc_period' => '0s',
					'esc_step_from' => 1,
					'esc_step_to' => 2,
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
					'opcommand_grp' => [
						[
							'groupid' => 4
						]
					],
					'opcommand' => [
						'scriptid' => self::$scriptid_action
					]
				]
			],
			'pause_suppressed' => 0
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));
		self::$trigger_actionid_neg = $response['result']['actionids'][0];

		$response = $this->call('item.create', [
			'hostid' => self::$hostid,
			'name' => self::ITEM_TRAP2,
			'key_' => self::ITEM_TRAP2,
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'trapper_hosts' => '{HOST.CONN}'
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));
		self::$trap2_itemid = $response['result']['itemids'][0];

		$response = $this->call('item.create', [
			'hostid' => self::$hostid,
			'name' => self::ITEM_EXT,
			'key_' => 'script[{HOST.CONN}]',
			'type' => ITEM_TYPE_EXTERNAL,
			'delay' => '30s',
			'value_type' => ITEM_VALUE_TYPE_UINT64
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));
		self::$ext_itemid = $response['result']['itemids'][0];

		$response = $this->call('drule.create', [
				'name' => 'test discovery rule HOST.CONN',
				'iprange' => '127.0.0.1-10',
				'dchecks' => [
					[
						'type' => SVC_AGENT,
						'key_' => 'agent.variant',
						'ports' => PHPUNIT_PORT_PREFIX.self::AGENT_PORT_SUFFIX,
						'uniq' => 1,
						'host_source' => 3,
						'name_source' => 3
					]
				],
				'delay' => '10s'
			]
		);
		$this->assertArrayHasKey('druleids', $response['result']);
		$this->assertEquals(1, count($response['result']['druleids']));
		self::$druleid = $response['result']['druleids'][0];

		$response = $this->call('action.create', [
			'name' => 'create_host_d',
			'eventsource' => EVENT_SOURCE_DISCOVERY,
			'status' => 0,
			'operations' => [
				[
					'operationtype' => OPERATION_TYPE_COMMAND,
					'opcommand_grp' => [
						[
							'groupid' => 4
						]
					],
					'opcommand' => [
						'scriptid' => self::$scriptid_action
					]
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertEquals(1, count($response['result']['actionids']));
		self::$drule_actionid = $response['result']['actionids'][0];

		return true;
	}

	/**
	 * Component configuration provider for agent related tests.
	 *
	 * @return array
	 */
	public function traceConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 5,
				'LogFileSize' => 0
			]
		];
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
				'LogFileSize' => 0,
				'EnableGlobalScripts' => 1
			],
			self::COMPONENT_AGENT => [
				'Hostname' => self::HOST_NAME,
				'AllowKey' => 'system.run[*]'
			]
		];
	}

	/**
	 * Test regression of validation.
	 *
	 * @required-components server, agent
	 * @configurationDataProvider defaultConfigurationProvider
	 */
	public function testHostConnMacroValidation_testValidMacroManualHostScript() {
		$response = $this->callUntilDataIsPresent('script.execute', [
			'scriptid' => self::$scriptid,
			'hostid' => self::$hostid
		], 30, 2);
		$this->assertArrayHasKey('response', $response['result']);
		$this->assertEquals('success', $response['result']['response']);
		$this->assertArrayHasKey('value', $response['result']);
		$this->assertEquals('hello 127.0.0.1', $response['result']['value']);
	}

	/**
	 * Test regression of validation.
	 *
	 * @required-components server, agent
	 * @depends testHostConnMacroValidation_testValidMacroManualHostScript
	 * @configurationDataProvider defaultConfigurationProvider
	 */
	public function testHostConnMacroValidation_testValidMacroManualEventScript() {
		$response = $this->call('script.update', [
			'scriptid' => self::$scriptid,
			'scope' => ZBX_SCRIPT_SCOPE_EVENT
		]);
		$this->assertArrayHasKey('scriptids', $response['result']);

		$this->sendSenderValue(self::HOST_NAME, self::ITEM_TRAP, 1);

		$response = $this->callUntilDataIsPresent('event.get', [
			'objectids' => self::$triggerid,
			'sortfield' => 'clock',
			'sortorder' => 'DESC',
			'limit' => 1
		], 30, 2);
		$this->assertArrayHasKey(0, $response['result']);
		$eventid = $response['result'][0]['eventid'];

		$response = $this->callUntilDataIsPresent('script.execute', [
			'scriptid' => self::$scriptid,
			'eventid' => $eventid
		], 30, 2);
		$this->assertArrayHasKey('response', $response['result']);
		$this->assertEquals('success', $response['result']['response']);
		$this->assertArrayHasKey('value', $response['result']);
		$this->assertEquals('hello 127.0.0.1', $response['result']['value']);
		$this->sendSenderValue(self::HOST_NAME, self::ITEM_TRAP, 0);
	}

	/**
	 * Test regression of validation.
	 *
	 * @required-components server, agent
	 * @depends testHostConnMacroValidation_testValidMacroManualEventScript
	 * @configurationDataProvider defaultConfigurationProvider
	 */
	public function testHostConnMacroValidation_testValidMacroAction() {
		$this->sendSenderValue(self::HOST_NAME, self::ITEM_TRAP, 101);

		$response = $this->callUntilDataIsPresent('alert.get', [
			'actionids' => [self::$trigger_actionid],
			'sortfield' => 'alertid',
			'sortorder' => 'DESC',
			'limit' => 1
		], 5, 2);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals('test_hostconn:echo -n hello 127.0.0.1', $response['result'][0]['message']);

		$this->sendSenderValue(self::HOST_NAME, self::ITEM_TRAP, 0);
	}

	/**
	 * Test regression of validation.
	 *
	 * @required-components server, agent
	 * @depends testHostConnMacroValidation_testValidMacroAction
	 * @configurationDataProvider defaultConfigurationProvider
	 */
	public function testHostConnMacroValidation_testValidMacroAllowedHosts() {
		$this->sendSenderValue(self::HOST_NAME, self::ITEM_TRAP2, 1);

		$response = $this->callUntilDataIsPresent('history.get', [
			'itemids' => [self::$trap2_itemid]
		], 5, 2);
		$this->assertEquals(1, count($response['result']), json_encode($response['result']));
	}

	/**
	 * Test regression of validation.
	 *
	 * @required-components server, agent
	 * @configurationDataProvider defaultConfigurationProvider
	 */
	public function testHostConnMacroValidation_testValidMacroItemKey() {
		$this->clearLog(self::COMPONENT_SERVER);

		$this->call('task.create', [
			'type' => ZBX_TM_TASK_CHECK_NOW,
			'request' => [
				'itemid' => self::$ext_itemid
			]
		]);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "In get_value_external() key:'script[127.0.0.1]'", true, 60, 1);
	}

	/**
	 * Test regression of validation.
	 *
	 * @required-components server, agent
	 * @configurationDataProvider defaultConfigurationProvider
	 */
	public function testHostConnMacroValidation_testValidMacroDruleAction() {
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "In discovery_register_host() ip:'127.0.0.1' status:0 value:'1'", false, 60, 1);
	}

	/**
	 * Test injection via invalid macro provided to manual host script.
	 *
	 * @required-components server, agent
	 * @configurationDataProvider defaultConfigurationProvider
	 */
	public function testHostConnMacroValidation_testInvalidMacroManualHostScript() {
		$response = $this->call('script.update', [
			'scriptid' => self::$scriptid,
			'scope' => ZBX_SCRIPT_SCOPE_HOST
		]);
		$this->assertArrayHasKey('scriptids', $response['result']);

		$response = $this->call('usermacro.update', [
			'hostmacroid' => self::$hostmacroid,
			'value' => '127.0.0.1;uname'
		]);
		$this->assertArrayHasKey('result', $response);
		$this->assertArrayHasKey('hostmacroids', $response['result']);

		$this->reloadConfigurationCache();
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of zbx_dc_sync_configuration()", true, 30, 1);

		$response = CAPIHelper::call('script.execute', [
			'scriptid' => self::$scriptid,
			'hostid' => self::$hostid
		]);
		$this->assertArrayHasKey('error', $response);
		$this->assertArrayHasKey('data', $response['error']);
		$this->assertEquals("Invalid macro '{HOST.CONN}' value", $response['error']['data']);
	}

	/**
	 * Test injection via manual event script.
	 *
	 * @required-components server, agent
	 * @depends testHostConnMacroValidation_testInvalidMacroManualHostScript
	 * @configurationDataProvider defaultConfigurationProvider
	 */
	public function testHostConnMacroValidation_testInvalidMacroManualEventScript() {
		$response = $this->call('script.update', [
			'scriptid' => self::$scriptid,
			'scope' => ZBX_SCRIPT_SCOPE_EVENT
		]);
		$this->assertArrayHasKey('scriptids', $response['result']);

		$this->sendSenderValue(self::HOST_NAME, self::ITEM_TRAP, 222);

		$response = $this->callUntilDataIsPresent('event.get', [
			'objectids' => self::$triggerid,
			'sortfield' => 'clock',
			'sortorder' => 'DESC',
			'limit' => 1
		], 30, 2);
		$this->assertArrayHasKey(0, $response['result']);
		$eventid = $response['result'][0]['eventid'];

		$response = CAPIHelper::call('script.execute', [
			'scriptid' => self::$scriptid,
			'eventid' => $eventid
		]);
		$this->assertArrayHasKey('error', $response);
		$this->assertArrayHasKey('data', $response['error']);
		$this->assertEquals("Invalid macro '{HOST.CONN}' value", $response['error']['data']);
		$this->sendSenderValue(self::HOST_NAME, self::ITEM_TRAP, 0);
	}

	/**
	 * Test injection via invalid trigger action operation.
	 *
	 * @required-components server, agent
	 * @depends testHostConnMacroValidation_testInvalidMacroManualEventScript
	 * @configurationDataProvider defaultConfigurationProvider
	 */
	public function testHostConnMacroValidation_testInvalidMacroAction() {
		$this->sendSenderValue(self::HOST_NAME, self::ITEM_TRAP, 9999);

		$response = $this->callUntilDataIsPresent('alert.get', [
			'actionids' => [self::$trigger_actionid_neg]
		], 30, 2);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals("Invalid macro '{HOST.CONN}' value", $response['result'][0]['error']);

		$this->sendSenderValue(self::HOST_NAME, self::ITEM_TRAP, 0);
	}

	/**
	 * Test injection via malicious autoregistration request.
	 *
	 * @required-components server, agent
	 * @configurationDataProvider defaultConfigurationProvider
	 */
	public function testHostConnMacroValidation_testInvalidMacroAutoregistration() {
		$this->clearLog(self::COMPONENT_SERVER);
		$c = new CAutoregClient('localhost', self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort', 10051), 3, 3,
			ZBX_SOCKET_BYTES_LIMIT
		);
		$c->sendRequest(self::HOST_NAME, '127.250.250.250;uname');

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER,
			'cannot send list of active checks to "127.0.0.1": "127.250.250.250;uname" is not a valid IP address',
			true, 30, 2, true
		);
	}

	/**
	 * Test injection via potentially malicious item key contents.
	 *
	 * @required-components server, agent
	 * @configurationDataProvider defaultConfigurationProvider
	 */
	public function testHostConnMacroValidation_testInvalidMacroItemKey() {
		$this->clearLog(self::COMPONENT_SERVER);

		$this->call('task.create', [
			'type' => ZBX_TM_TASK_CHECK_NOW,
			'request' => [
				'itemid' => self::$ext_itemid
			]
		]);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "cannot resolve macro '{HOST.CONN}'", true, 60, 1);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "In get_value_external() key:'script[*UNKNOWN*]'", true, 60, 1);
	}

	/**
	 * Test injection via potentially malicious item key contents.
	 *
	 * @required-components server, agent
	 * @configurationDataProvider defaultConfigurationProvider
	 */
	public function testHostConnMacroValidation_testInvalidMacroAllowedHosts() {
		$this->clearLog(self::COMPONENT_SERVER);
		$this->expectException(\PHPUnit\Framework\ExpectationFailedException::class);
		$this->sendSenderValue(self::HOST_NAME, self::ITEM_TRAP2, 1);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'cannot process item "'.self::ITEM_TRAP2.'"',
			true, 60, 1, true);
	}


	/**
	 * Test circular {HOST.CONN} configuration
	 *
	 * @required-components server
	 * @configurationDataProvider defaultConfigurationProvider
	 */
	public function testHostConnMacroValidation_testHostConnRecursion() {
		$this->stopComponent(self::COMPONENT_SERVER);

		$response = $this->call('hostinterface.update', [
			'interfaceid' => self::$interfaceid,
			'dns' => '{HOST.CONN}'
		]);
		$this->assertArrayHasKey('interfaceids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['interfaceids']);

		$this->startComponent(self::COMPONENT_SERVER);
	}

	/**
	 * Test injection via running an action operation for discovery.
	 *
	 * @required-components server
	 * @configurationDataProvider traceConfigurationProvider
	 */
	public function testHostConnMacroValidation_testHostConnExpansionInSecondaryIf() {
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->clearLog(self::COMPONENT_SERVER);

		$response = $this->call('hostinterface.update', [
			'interfaceid' => self::$interfaceid,
			'dns' => 'zabbix.com',
			'main' => 1
		]);
		$this->assertArrayHasKey('interfaceids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['interfaceids']);

		$response = $this->call('hostinterface.create', [
			[
				'hostid' => self::$hostid,
				'useip' => INTERFACE_USE_DNS,
				'ip' => '',
				'dns' => 'zabbix.com',
				'main' => 1,
				'port' => '10163',
				'type' => INTERFACE_TYPE_SNMP,
				'details' => [
					'version' => 3,
					'bulk' => 1,
					'max_repetitions' => 10,
					'securityname' => 'zabbix',
					'securitylevel' => 0,
					'authprotocol' => 0,
					'privprotocol' => 0,
					'contextname' => 'zabbix'
				]
			],
			[
				'hostid' => self::$hostid,
				'useip' => INTERFACE_USE_DNS,
				'dns' => '{HOST.CONN}',
				'main' => 0,
				'ip' => '',
				'port' => '10164',
				'type' => INTERFACE_TYPE_SNMP,
				'details' => [
					'version' => 3,
					'bulk' => 1,
					'max_repetitions' => 10,
					'securityname' => 'zabbix',
					'securitylevel' => 0,
					'authprotocol' => 0,
					'privprotocol' => 0,
					'contextname' => 'zabbix'
				]
			],
			[
				'hostid' => self::$hostid,
				'type' => INTERFACE_TYPE_IPMI,
				'useip' => INTERFACE_USE_DNS,
				'ip' => '',
				'dns' => 'zabbix.com',
				'port' => '1023',
				'main' => 1
			],
			[
				'hostid' => self::$hostid,
				'type' => INTERFACE_TYPE_IPMI,
				'ip' => '',
				'useip' => INTERFACE_USE_DNS,
				'dns' => '{HOST.CONN}',
				'port' => '10231',
				'main' => 0
			],
			[
				'hostid' => self::$hostid,
				'type' => INTERFACE_TYPE_JMX,
				'useip' => INTERFACE_USE_DNS,
				'dns' => 'zabbix.com',
				'port' => '1234',
				'ip' => '',
				'main' => 1
			],
			[
				'hostid' => self::$hostid,
				'type' => INTERFACE_TYPE_JMX,
				'ip' => '',
				'useip' => INTERFACE_USE_DNS,
				'dns' => '{HOST.CONN}',
				'port' => '12345',
				'main' => 0
			],
			[
				'hostid' => self::$hostid,
				'ip' => '',
				'dns' => '{HOST.CONN}',
				'main' => 0,
				'port' => '20000',
				'type' => INTERFACE_TYPE_AGENT,
				'useip' => INTERFACE_USE_DNS
			]
		]);
		$this->assertArrayHasKey('interfaceids', $response['result']);
		$this->assertCount(7, $response['result']['interfaceids']);

		$this->startComponent(self::COMPONENT_SERVER);

		$log = file_get_contents(self::getLogPath(self::COMPONENT_SERVER));
		$data = explode("\n", $log);
		$synced_ifs = preg_grep("/interfaceid:[0-9]+ hostid:[0-9]+ ip:'' dns:'zabbix.com' /", $data);
		$this->assertCount(8, $synced_ifs);
	}

	/**
	 * Test injection via running an action operation for discovery.
	 *
	 * @required-components server, agent
	 * @configurationDataProvider defaultConfigurationProvider
	 */
	public function testHostConnMacroValidation_testInvalidMacroDruleAction() {
		CDataHelper::call('host.delete', [self::$hostid]);
		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		$response = $this->callUntilDataIsPresent('alert.get', [
			'actionids' => [self::$trigger_actionid_neg]
		], 30, 2);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertEquals("Invalid macro '{HOST.CONN}' value", $response['result'][0]['error']);
	}

	/**
	 * Delete all created data after test.
	 */
	public static function clearData(): void {
		CDataHelper::call('action.delete', [self::$trigger_actionid, self::$trigger_actionid_neg, self::$drule_actionid]);
		CDataHelper::call('drule.delete', [self::$druleid]);
		CDataHelper::call('host.delete', [self::$hostid]);
		CDataHelper::call('script.delete', [self::$scriptid_action, self::$scriptid]);
	}
}
