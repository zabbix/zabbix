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
 * Test suite for discovery rules
 *
 * @backup hosts
 *
 * @onAfter deleteData
 */
class testDiscoveryRules extends CIntegrationTest {
	const DRULE_NAME = 'Test discovery rule';
	const DRULE_NAME_ERR = 'Test discovery rule with error';
	const DISCOVERY_ACTION_NAME = 'Test discovery action';
	const DISCOVERY_ACTION_NAME_ERR = 'Test discovery action with error';
	const PROXY_NAME = 'Test proxy';
	const SLEEP_TIME = 1;
	const MAX_ATTEMPTS_DISCOVERY = 60;

	/* For tests with real SNMP agent */
	const SNMPAGENT_VALID_OID = 'iso.3.6.1.2.1.1.1.0';
	const SNMPAGENT_INVALID_OID = 'invalid.OID';
	const SNMPAGENT_EXPECTED_INVALID_OID_ERR_MSG = "'SNMPv2c agent' checks failed: " . '"snmp_parse_oid(): cannot parse OID "' . self::SNMPAGENT_INVALID_OID . '"."';

	/* For tests with simulated SNMP agent */
	const SNMPSIM_HOST_IP = '127.0.10.3';
	const SNMPSIM_HOST_PORT = '1024';
	const SNMPSIM_DRULE_IP_RANGE = '127.0.10.3';
	const SNMPSIM_VALID_OID = 'iso.3.6.1.2.1.1.1.0';
	const SNMPSIM_DRULE_CONTEXT_NAME = 'host/test/test';
	const SNMPSIM_USERNAME = 'zabbix';
	const SNMPSIM_DRULE_SECURITY_LEVEL = ITEM_SNMPV3_SECURITYLEVEL_AUTHPRIV;
	const SNMPSIM_AUTH_PROTOCOL = 'MD5';
	const SNMPSIM_DRULE_AUTH_PROTOCOL = ITEM_SNMPV3_AUTHPROTOCOL_MD5;
	const SNMPSIM_AUTH_KEY = 'zabbixAuth';
	const SNMPSIM_PRIV_PROTOCOL = 'DES';
	const SNMPSIM_DRULE_PRIVACY_PROTOCOL = ITEM_SNMPV3_PRIVPROTOCOL_DES;
	const SNMPSIM_PRIV_KEY = 'zabbixPriv';
	const SNMPSIM_PROCESS_USER = 'nobody';
	const SNMPSIM_PROCESS_GROUP = 'nogroup';
	const SNMPSIM_DATA_DIR_REL_PATH = 'data/snmpsim';

	private static $discoveryActionId;
	private static $discoveredHostId;

	/* IDs for cleanup in the end of the test */
	private static $drules = array();
	private static $discoveryActions = array();
	private static $proxies = array();

	private static function snmpsimStart(): void {
		$datadir = realpath(dirname(__FILE__)) . '/' . self::SNMPSIM_DATA_DIR_REL_PATH;

		$cmd = 'snmpsimd';
		$cmd .= ' --v3-user=' . self::SNMPSIM_USERNAME;
		$cmd .= ' --v3-auth-key=' . self::SNMPSIM_AUTH_KEY;
		$cmd .= ' --v3-priv-key=' . self::SNMPSIM_PRIV_KEY;
		$cmd .= ' --v3-auth-proto=' . self::SNMPSIM_AUTH_PROTOCOL;
		$cmd .= ' --v3-priv-proto=' . self::SNMPSIM_PRIV_PROTOCOL;
		$cmd .= ' --process-user=' . self::SNMPSIM_PROCESS_USER;
		$cmd .= ' --process-group=' . self::SNMPSIM_PROCESS_GROUP;
		$cmd .= ' --agent-udpv4-endpoint=' . self::SNMPSIM_DRULE_IP_RANGE . ':' . self::SNMPSIM_HOST_PORT;
		$cmd .= ' --data-dir=' . $datadir;
		$cmd .= ' > /dev/null 2>&1 &';
		shell_exec($cmd);
	}

	private static function snmpsimStop(): void {
		shell_exec('pkill snmpsimd > /dev/null 2>&1 &');
	}

	private function waitForDiscoveryWithTags($expectedTags, $notExpectedTags = []): string {
		for ($i = 0; $i < self::MAX_ATTEMPTS_DISCOVERY; $i++) {
			try {
				$response = $this->call('host.get', [
					'selectTags' => ['tag', 'value']
				]);

				$this->assertArrayHasKey('result', $response, 'Failed to discover host before timeout');
				$this->assertCount(1, $response['result'], 'Failed to discover host before timeout');
				$this->assertArrayHasKey('tags', $response['result'][0], 'Failed to discover host before timeout');

				$discoveredHost = $response['result'][0];
				$this->assertArrayHasKey('hostid', $discoveredHost, 'Failed to get host ID of the discovered host');

				$tags = $discoveredHost['tags'];
				$this->assertCount(count($expectedTags), $tags, 'Unexpected tags count was detected');

				foreach($expectedTags as $expectedTag) {
					$this->assertContains($expectedTag, $tags, 'Expected tag was not found after discovery');
				}

				foreach($notExpectedTags as $notExpectedTag) {
					$this->assertNotContains($notExpectedTag, $tags, 'Unexpected tag was found after discovery');
				}

				return $discoveredHost['hostid'];
			} catch (Exception $e) {
				if ($i == self::MAX_ATTEMPTS_DISCOVERY - 1)
					throw $e;
				else
					sleep(self::SLEEP_TIME);
			}
		}
	}

	private function waitForDiscovery($expected_hostname): string {
		for ($i = 0; $i < self::MAX_ATTEMPTS_DISCOVERY; $i++) {
			try {
				$response = $this->call('host.get', [

				]);

				$this->assertArrayHasKey('result', $response, 'Failed to discover host before timeout');
				$this->assertCount(1, $response['result'], 'Failed to discover host before timeout');

				$discoveredHost = $response['result'][0];
				$this->assertArrayHasKey('hostid', $discoveredHost, 'Failed to get host ID of the discovered host');
				$this->assertEquals($expected_hostname, $discoveredHost['host']);

				break;
			} catch (Exception $e) {
				if ($i == self::MAX_ATTEMPTS_DISCOVERY - 1)
					throw $e;
				else
					sleep(self::SLEEP_TIME);
			}
		}

		return $discoveredHost['hostid'];
	}

	private function waitForDiscoveryErr($errStr): void {
		for ($i = 0; $i < self::MAX_ATTEMPTS_DISCOVERY; $i++) {
			try {
				$response = $this->call('drule.get', [
					'filter' => [
						'name' => self::DRULE_NAME_ERR
					]
				]);

				$this->assertArrayHasKey('result', $response, 'Failed to get discovery rule error');
				$this->assertCount(1, $response['result'], 'Failed to get discovery rule error');

				$drule = $response['result'][0];
				$this->assertArrayHasKey('name', $drule, 'Failed to get discovery rule error');
				$this->assertEquals($errStr, $drule['error'], 'Failed to get discovery rule error');

				break;
			} catch (Exception $e) {
				if ($i == self::MAX_ATTEMPTS_DISCOVERY - 1)
					throw $e;
				else
					sleep(self::SLEEP_TIME);
			}
		}
	}

	private static function deleteAllActions(): void {
		if (count(self::$discoveryActions) > 0) {
			CDataHelper::call('action.delete', self::$discoveryActions);
			self::$discoveryActions = array();
		}

	}

	private static function deleteAllDrules(): void {
		if (count(self::$drules) > 0) {
			CDataHelper::call('drule.delete', self::$drules);
			self::$drules = array();
		}
	}

	private function createDruleSnmpv2($name, $iprange, $oid, $proxyId): string {
		$drule = [
			'name' => $name,
			'delay' => '1s',
			'iprange' => $iprange,
			'concurrency_max' => ZBX_DISCOVERY_CHECKS_UNLIMITED,
			'dchecks' => [
				[
					'type' => SVC_SNMPv2c,
					'key_' => $oid,
					'ports' => '161',
					'snmp_community' => 'public',
					'uniq' => 0
				]
			]
		];

		if (!is_null($proxyId)) {
			$drule['proxyid'] = $proxyId;
		}

		$response = $this->call('drule.create', $drule);
		$this->assertArrayHasKey('result', $response, 'Failed to create a discovery rule');
		$this->assertArrayHasKey('druleids', $response['result'], 'Failed to create a discovery rule');
		$this->assertCount(1, $response['result'], 'Failed to create a discovery rule');

		array_push(self::$drules, $response['result']['druleids'][0]);

		return $response['result']['druleids'][0];
	}

	private function createDruleSnmpv3($name, $proxyId): string {
		$drule = [
			'iprange' => self::SNMPSIM_DRULE_IP_RANGE,
			'name' => $name,
			'delay' => '1s',
			'status' => 0, /* enabled */
			'concurrency_max' => ZBX_DISCOVERY_CHECKS_UNLIMITED,
			'dchecks' => [
				[
					'type' => SVC_SNMPv3,
					'key_' => self::SNMPSIM_VALID_OID,
					'ports' => self::SNMPSIM_HOST_PORT,
					'snmpv3_authpassphrase' => self::SNMPSIM_AUTH_KEY,
					'snmpv3_authprotocol' => self::SNMPSIM_DRULE_AUTH_PROTOCOL,
					'snmpv3_contextname' => self::SNMPSIM_DRULE_CONTEXT_NAME,
					'snmpv3_privpassphrase' => self::SNMPSIM_PRIV_KEY,
					'snmpv3_privprotocol' => self::SNMPSIM_DRULE_PRIVACY_PROTOCOL,
					'snmpv3_securitylevel' => self::SNMPSIM_DRULE_SECURITY_LEVEL,
					'snmpv3_securityname' => self::SNMPSIM_USERNAME,
					'uniq' => 0,
					'host_source' => 2, /* IP */
					'name_source' => 2  /* IP */
				]
			]
		];

		if (!is_null($proxyId)) {
			$drule['proxyid'] = $proxyId;
		}

		$response = $this->call('drule.create', $drule);
		$this->assertArrayHasKey('result', $response, 'Failed to create a discovery rule');
		$this->assertArrayHasKey('druleids', $response['result'], 'Failed to create a discovery rule');
		$this->assertCount(1, $response['result'], 'Failed to create a discovery rule');

		array_push(self::$drules, $response['result']['druleids'][0]);

		return $response['result']['druleids'][0];
	}

	private function createActionHostAdd($druleId, $actionName): string {
		$response = $this->call('action.create', [
			'name' => $actionName,
			'eventsource' => EVENT_SOURCE_DISCOVERY,
			'status' => ACTION_STATUS_ENABLED,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_DRULE,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => $druleId
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
				[
					'operationtype' => OPERATION_TYPE_HOST_ADD
				]
			]
		]);
		$this->assertArrayHasKey('result', $response, 'Failed to create a discovery action "' . $actionName . '"');
		$this->assertArrayHasKey('actionids', $response['result'], 'Failed to create a discovery action "' . $actionName . '"');
		$this->assertCount(1, $response['result']['actionids'], 'Failed to create a discovery action "' . $actionName . '"');

		array_push(self::$discoveryActions, $response['result']['actionids'][0]);

		return $response['result']['actionids'][0];
	}

	private function createProxy(): void {
		$response = $this->call('proxy.create', [
			'name' => self::PROXY_NAME,
			'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
			'hosts' => [],
			'address' => '127.0.0.1',
			'port' => PHPUNIT_PORT_PREFIX.self::PROXY_PORT_SUFFIX
		]);

		$this->assertArrayHasKey('result', $response, 'Failed to create proxy');
		$this->assertArrayHasKey('proxyids',  $response['result'], 'Failed to create proxy');
		$this->assertCount(1, $response['result']['proxyids'], 'Failed to create proxy');

		array_push(self::$proxies, $response['result']['proxyids'][0]);
	}

	private static function deleteProxy(): void {
		if (count(self::$proxies) > 0) {
			CDataHelper::call('proxy.delete', self::$proxies);
			self::$proxies = array();
		}
	}

	private function deleteAllHosts(): void {
		$response = $this->call('host.get', []);

		$hostids = array();
		foreach ($response['result'] as $host) {
			$hostids[] = $host['hostid'];
		}

		$this->call('host.delete', $hostids);

		$response = $this->call('host.get', []);
		$this->assertArrayHasKey('result', $response, 'Failed to clear existing hosts');
		$this->assertCount(0, $response['result'], 'Failed to clear existing hosts');
	}

	/**
	 * Configuration provider for proxy in database mode
	 *
	 * @return array
	 */
	public function proxyDBModeconfigurationProvider(): array
	{
		return [
			self::COMPONENT_PROXY => [
				'ProxyMode' => PROXY_OPERATING_MODE_PASSIVE,
				'Hostname' => self::PROXY_NAME,
				'ListenPort' => PHPUNIT_PORT_PREFIX.self::PROXY_PORT_SUFFIX,
				'ProxyBufferMode' => 'disk',
				'ProxyMemoryBufferSize' => 0
			]
		];
	}

	/**
	 * Configuration provider for proxy in memory mode
	 *
	 * @return array
	 */
	public function proxyMemoryModeconfigurationProvider(): array
	{
		return [
			self::COMPONENT_PROXY => [
				'ProxyMode' => PROXY_OPERATING_MODE_PASSIVE,
				'Hostname' => self::PROXY_NAME,
				'ListenPort' => PHPUNIT_PORT_PREFIX.self::PROXY_PORT_SUFFIX,
				'ProxyBufferMode' => 'memory',
				'ProxyMemoryBufferSize' => '128K'
			]
		];
	}

	/**
	 * Configuration provider for proxy in hybrid mode
	 *
	 * @return array
	 */
	public function proxyHybridModeconfigurationProvider(): array
	{
		return [
			self::COMPONENT_PROXY => [
				'ProxyMode' => PROXY_OPERATING_MODE_PASSIVE,
				'Hostname' => self::PROXY_NAME,
				'ListenPort' => PHPUNIT_PORT_PREFIX.self::PROXY_PORT_SUFFIX,
				'ProxyBufferMode' => 'hybrid',
				'ProxyMemoryBufferSize' => '128K'
			]
		];
	}

	/**
	 * @inheritdoc
	 */
	public function prepareData(): void {
		$this->deleteAllHosts();
		self::snmpsimStart();
	}

	/**
	 * @required-components server
	 */
	public function testDiscoveryRules_opAddHostTags(): void
	{
		$response = $this->call('drule.create', [
			'name' => self::DRULE_NAME,
			'delay' => '1s',
			'iprange' => '127.0.0.1',
			'dchecks' => [
				[
					'type' => SVC_HTTP,
					'ports' => '80'
				]
			]
		]);
		$this->assertArrayHasKey('result', $response, 'Failed to create a discovery rule');
		$this->assertArrayHasKey('druleids', $response['result'], 'Failed to create a discovery rule');
		$this->assertCount(1, $response['result'], 'Failed to create a discovery rule');
		array_push(self::$drules, $response['result']['druleids'][0]);
		$druleId = $response['result']['druleids'][0];

		$response = $this->call('action.create', [
			'name' => self::DISCOVERY_ACTION_NAME,
			'eventsource' => EVENT_SOURCE_DISCOVERY,
			'status' => ACTION_STATUS_ENABLED,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_DRULE,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => $druleId
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
				/* OPERATION_TYPE_HOST_ADD is intentionally missing. It is expected to be run by */
				/* Zabbix server, because OPERATION_TYPE_HOST_TAGS_ADD is present.               */
				[
					'operationtype' => OPERATION_TYPE_HOST_TAGS_ADD,
					'optag' => [
						[
							'tag' => 'add_tag1',
							'value' => 'add_value1'
						],
						[
							'tag' => 'add_tag2',
							'value' => 'add_value2'
						]
					]
				]
			]
		]);
		$this->assertArrayHasKey('result', $response, 'Failed to create a discovery action');
		$this->assertArrayHasKey('actionids', $response['result'], 'Failed to create a discovery action');
		$this->assertCount(1, $response['result']['actionids'], 'Failed to create a discovery action');

		self::$discoveryActionId = $response['result']['actionids'][0];
		array_push(self::$discoveryActions, $response['result']['actionids'][0]);

		self::$discoveredHostId = $this->waitForDiscoveryWithTags([
			['tag' => 'add_tag1', 'value' => 'add_value1'],
			['tag' => 'add_tag2', 'value' => 'add_value2']
		]);
	}

	/**
	 * @depends testDiscoveryRules_opAddHostTags
	 * @required-components server
	 */
	public function testDiscoveryRules_opDelHostTags(): void
	{
		/* Replace tags at the discovered host */
		$response = $this->call('host.update', [
			'hostid' => self::$discoveredHostId,
			'tags' => [
				[
					'tag' => 'del_tag3',
					'value' => 'del_value3'
				],
				[
					'tag' => 'del_tag4',
					'value' => 'del_value4'
				]
			]
		]);

		$this->assertArrayHasKey('result', $response);
		$this->assertCount(1, $response['result']);

		$response = $this->call('action.update', [
			'actionid' => self::$discoveryActionId,
			'operations' => [
				[
					'operationtype' => OPERATION_TYPE_HOST_TAGS_ADD,
					'optag' => [
						[
							'tag' => 'add_tag1',
							'value' => 'add_value1'
						],
						[
							'tag' => 'add_tag2',
							'value' => 'add_value2'
						]
					]
				],
				[
					'operationtype' => OPERATION_TYPE_HOST_TAGS_REMOVE,
					'optag' => [

						[
							'tag' => 'del_tag3',
							'value' => 'del_value3'
						],
						[
							'tag' => 'del_tag4',
							'value' => 'del_value4'
						]
					]
				]
			]
		]);

		$this->waitForDiscoveryWithTags([
			['tag' => 'add_tag1', 'value' => 'add_value1'],
			['tag' => 'add_tag2', 'value' => 'add_value2']
		],
		[
			['tag' => 'del_tag3', 'value' => 'del_value3'],
			['tag' => 'del_tag4', 'value' => 'del_value4']
		]);
	}

	/**
	 * @depends testDiscoveryRules_opDelHostTags
	 * @required-components server
	 */
	public function testDiscoveryRules_snmpErrorViaServer(): void  {
		$this->stopComponent(self::COMPONENT_SERVER);

		self::deleteAllActions();
		self::deleteAllDrules();
		$this->deleteAllHosts();

		$druleId = $this->createDruleSnmpv3(self::DRULE_NAME, NULL);
		$this->createActionHostAdd($druleId, self::DISCOVERY_ACTION_NAME);

		$druleWithErrId = $this->createDruleSnmpv2(self::DRULE_NAME_ERR, '127.0.0.1', self::SNMPAGENT_INVALID_OID, NULL);
		$this->createActionHostAdd($druleWithErrId, self::DISCOVERY_ACTION_NAME_ERR);

		$this->startComponent(self::COMPONENT_SERVER);
		$this->waitForDiscoveryErr(self::SNMPAGENT_EXPECTED_INVALID_OID_ERR_MSG);
		$this->waitForDiscovery(self::SNMPSIM_HOST_IP);
	}

	private function proxyTest(): void {
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->stopComponent(self::COMPONENT_PROXY);

		self::deleteAllActions();
		self::deleteAllDrules();
		$this->deleteAllHosts();
		$this->deleteProxy();

		$proxyId = $this->createProxy();

		$druleId = $this->createDruleSnmpv3(self::DRULE_NAME, $proxyId);
		$this->createActionHostAdd($druleId, self::DISCOVERY_ACTION_NAME);

		$druleWithErrId = $this->createDruleSnmpv2(self::DRULE_NAME_ERR, '127.0.0.1', self::SNMPAGENT_INVALID_OID, $proxyId);
		$this->createActionHostAdd($druleWithErrId, self::DISCOVERY_ACTION_NAME_ERR);

		$this->startComponent(self::COMPONENT_PROXY);
		$this->startComponent(self::COMPONENT_SERVER);

		$this->waitForDiscoveryErr(self::SNMPAGENT_EXPECTED_INVALID_OID_ERR_MSG);
		$this->waitForDiscovery(self::SNMPSIM_HOST_IP);
	}

	/**
	 * @depends testDiscoveryRules_snmpErrorViaServer
	 * @required-components server,proxy
	 * @configurationDataProvider proxyDBModeconfigurationProvider
	 */
	public function testDiscoveryRules_snmpErrorViaProxyDBMode(): void {
		$this->proxyTest();
	}

	/**
	 * @depends testDiscoveryRules_snmpErrorViaProxyDBMode
	 * @required-components server,proxy
	 * @configurationDataProvider proxyMemoryModeconfigurationProvider
	 */
	public function testDiscoveryRules_snmpErrorViaProxyMemoryMode(): void {
		$this->proxyTest();
	}

	/**
	 * @depends testDiscoveryRules_snmpErrorViaProxyMemoryMode
	 * @required-components server,proxy
	 * @configurationDataProvider proxyHybridModeconfigurationProvider
	 */
	public function testDiscoveryRules_snmpErrorViaProxyHybridMode(): void {
		$this->proxyTest();
	}

	/**
	 * Delete data objects created for this test suite
	 */
	public static function deleteData(): void {
		self::snmpsimStop();
		self::deleteAllActions();
		self::deleteAllDrules();
		self::deleteProxy();
	}
}
