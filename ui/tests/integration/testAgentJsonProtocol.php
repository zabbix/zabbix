<?php
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
require_once dirname(__FILE__).'/../include/CAPITest.php';

/**
 * Client that returns the whole server response, including fields other than 'data'.
 */
class CAgentCommandsClient extends CZabbixClient {
	private $raw_response = null;

	protected function normalizeResponse(array &$response): bool {
		$this->raw_response = $response;

		return parent::normalizeResponse($response);
	}

	public function requestRaw(array $params) {
		$this->raw_response = null;
		parent::request($params);

		return $this->raw_response;
	}
}

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
	const RC_HOSTNAME = 'test_agentprotocol_rc';
	const RC_OTHER_HOSTNAME = 'test_agentprotocol_rc_other';
	const RC_UNKNOWN_HOSTNAME = 'test_agentprotocol_rc_unknown';

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
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'trapper_hosts' => '{$TRAPPER.ALLOWED_HOSTS}'
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
			'command' => 'sleep ' . self::SCRIPT_OUTPUT_TIMEOUT .' && echo 1 > ' . self::$agent_script_output_file,
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

		$server = new CZabbixServer('localhost', $this->getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'), 8,
			10, ZBX_SOCKET_BYTES_LIMIT, tls_config: ['ACTIVE' => false]
		);
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
		$this->call('host.update', [
			'hostid' => self::$hostid,
			'interfaces' => [
				[
					'interfaceid' => self::$interfaceid,
					'port' => $port
				]
			]
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

	/**
	 * Send command result to server on behalf of active agent.
	 *
	 * @param CAgentCommandsClient $client
	 * @param string               $host       host name the result is reported for
	 * @param string               $commandid
	 * @param string               $value      command output
	 */
	private static function sendActiveCommandResult(CAgentCommandsClient $client, string $host, string $commandid,
			string $value) {
		$client->requestRaw([
			'request' => 'agent data',
			'session' => md5(uniqid('', true)),
			'host' => $host,
			'data' => [],
			'commands' => [
				['id' => $commandid, 'value' => $value]
			],
			'version' => ZABBIX_VERSION,
			'variant' => 2
		]);
	}

	/**
	 * Test that output of script executed on active agent is taken only from the host the command was sent to.
	 *
	 * @required-components server
	 */
	public function testAgentJsonProtocol_activeCommandResultFromOtherHost() {
		$hostids = [];
		$scriptid = null;
		$multi_handle = null;
		$curl = null;

		try {
			$response = $this->call('host.create', [
				[
					'host' => self::RC_HOSTNAME,
					'groups' => [['groupid' => 4]], // Zabbix servers
					'status' => HOST_STATUS_MONITORED
				],
				[
					'host' => self::RC_OTHER_HOSTNAME,
					'groups' => [['groupid' => 4]], // Zabbix servers
					'status' => HOST_STATUS_MONITORED
				]
			]);
			$this->assertArrayHasKey('hostids', $response['result']);
			$this->assertCount(2, $response['result']['hostids']);
			$hostids = $response['result']['hostids'];

			$response = $this->call('script.create', [
				'name' => 'Active command result test',
				'command' => 'echo ok',
				'execute_on' => ZBX_SCRIPT_EXECUTE_ON_AGENT,
				'scope' => ZBX_SCRIPT_SCOPE_HOST,
				'type' => ZBX_SCRIPT_TYPE_CUSTOM_SCRIPT
			]);
			$this->assertArrayHasKey('scriptids', $response['result']);
			$scriptid = $response['result']['scriptids'][0];

			$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

			// Host has no agent interface, so the command is queued for the active agent and script.execute waits
			// until the agent reports the result. The request is not awaited here so that the agent can be emulated.
			$curl = curl_init(PHPUNIT_URL.'api_jsonrpc.php');
			curl_setopt_array($curl, [
				CURLOPT_POST => true,
				CURLOPT_POSTFIELDS => json_encode([
					'jsonrpc' => '2.0',
					'method' => 'script.execute',
					'params' => [
						'scriptid' => $scriptid,
						'hostid' => $hostids[0]
					],
					'id' => 1
				]),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER => [
					'Content-Type: application/json-rpc',
					'Authorization: Bearer '.CAPIHelper::getSessionId()
				]
			]);

			$multi_handle = curl_multi_init();
			curl_multi_add_handle($multi_handle, $curl);

			$pump = function(float $seconds) use ($multi_handle): int {
				$end = microtime(true) + $seconds;

				do {
					curl_multi_exec($multi_handle, $running);
					curl_multi_select($multi_handle, 0.1);
				} while ($running && microtime(true) < $end);

				return $running;
			};

			$client = new CAgentCommandsClient('localhost', $this->getConfigurationValue(self::COMPONENT_SERVER,
				'ListenPort'), 10, 10, ZBX_SOCKET_BYTES_LIMIT, tls_config: ['ACTIVE' => false]
			);

			$commandid = null;

			for ($i = 0; $i < 30; $i++) {
				$response = $client->requestRaw([
					'request' => 'active checks',
					'host' => self::RC_HOSTNAME
				]);

				if (is_array($response) && array_key_exists('commands', $response)) {
					$commandid = (string) $response['commands'][0]['id'];
					break;
				}

				$this->assertNotEquals(0, $pump(1), 'script.execute finished before the command was sent to agent');
			}

			$this->assertNotNull($commandid, 'Remote command was not sent to active agent');

			// Results reported on behalf of other hosts must be ignored.
			foreach ([self::RC_UNKNOWN_HOSTNAME, self::RC_OTHER_HOSTNAME] as $host) {
				self::sendActiveCommandResult($client, $host, $commandid, 'forged by '.$host);
			}

			// Give server time to pick up an accepted result, it checks command state every second.
			$pump(3);

			self::sendActiveCommandResult($client, self::RC_HOSTNAME, $commandid, 'ok');

			$this->assertEquals(0, $pump(30), 'script.execute did not finish');

			$response = json_decode(curl_multi_getcontent($curl), true);
			$this->assertIsArray($response);
			$this->assertArrayHasKey('result', $response, json_encode($response));
			$this->assertEquals('success', $response['result']['response']);
			$this->assertEquals('ok', $response['result']['value']);
		} finally {
			if ($multi_handle !== null) {
				curl_multi_remove_handle($multi_handle, $curl);
				curl_multi_close($multi_handle);
			}

			if ($scriptid !== null) {
				$this->call('script.delete', [$scriptid]);
			}

			if ($hostids !== []) {
				$this->call('host.delete', $hostids);
			}
		}

		return true;
	}

	private function purgeActions() {
		$params = [
			'output' => 'actionid',
			'preservekeys' => true
		];

		$response = $this->call('action.get', $params);

		$this->assertArrayHasKey('result', $response);

		$ids = array_keys($response['result']);

		if ($ids === []) {
			return;
		}

		$response = $this->call('action.delete', $ids);
	}

	private function checkDiscovery($agent_component) {
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->stopComponent($agent_component);

		$this->purgeActions();
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
