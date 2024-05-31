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

/**
 * Test suite for discovery rules
 *
 * @backup hosts,drules,actions,operations,optag,host_tag
 * @backup auditlog,changelog,config,ha_node
 */
class testDiscoveryRules extends CIntegrationTest {
	const DRULE_WITH_SUCCESS_NAME = 'Test discovery rule';
	const DRULE_WITH_ERR_NAME = 'Test discovery rule with error';
	const ACTION_WITH_SUCCESS_NAME = 'Test discovery action';
	const ACTION_WITH_ERR_NAME = 'Test discovery action with error';
	const PROXY_NAME = 'Test proxy';
	const VALID_OID = 'iso.3.6.1.2.1.1.1.0';
	const INVALID_OID = 'invalid.OID';
	const EXPECTED_INVALID_OID_ERR_MSG = "'SNMPv2c agent' checks failed: " . '"snmp_parse_oid(): cannot parse OID "' . self::INVALID_OID . '"."';
	const SLEEP_TIME = 1;
	const MAX_ATTEMPTS_DISCOVERY = 60;

	private static $druleWithSuccessId;
	private static $druleWithErrId;
	private static $actionWithSuccessId;
	private static $actionWithWithErrId;
	private static $discoveredHostId;
	private static $proxyId;

	private function waitForDiscoveryWithTags($expectedTags, $notExpectedTags = []) {
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
				self::$discoveredHostId = $discoveredHost['hostid'];

				$tags = $discoveredHost['tags'];
				$this->assertCount(count($expectedTags), $tags, 'Unexpected tags count was detected');

				foreach($expectedTags as $expectedTag) {
					$this->assertContains($expectedTag, $tags, 'Expected tag was not found after discovery');
				}

				foreach($notExpectedTags as $notExpectedTag) {
					$this->assertNotContains($notExpectedTag, $tags, 'Unexpected tag was found after discovery');
				}

				break;
			} catch (Exception $e) {
				if ($i == self::MAX_ATTEMPTS_DISCOVERY - 1)
					throw $e;
				else
					sleep(self::SLEEP_TIME);
			}
		}
	}

	private function waitForDiscovery($hostname) {
		$response = $this->callUntilDataIsPresent('host.get', [
			'output' => 'extend'
		], self::MAX_ATTEMPTS_DISCOVERY, self::SLEEP_TIME);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('host', $response['result'][0]);
	}

	private function waitForDiscoveryErr($errStr) {
		for ($i = 0; $i < self::MAX_ATTEMPTS_DISCOVERY; $i++) {
			try {
				$response = $this->call('drule.get', [
					'filter' => [
						'name' => self::DRULE_WITH_ERR_NAME,
					],
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

	private function deleteActions($ids) {
		$response = $this->call('action.delete', $ids);
		$this->assertArrayHasKey('result', $response, "Failed to clear actions with IDs " . implode(', ', $ids));
		$this->assertArrayHasKey('actionids', $response['result'], "Failed to clear actions with IDs " . implode(', ', $ids));
		$this->assertCount(count($ids), $response['result']['actionids'], "Failed to clear actions with IDs " . implode(', ', $ids));
	}

	private function deleteDrules($ids) {
		$response = $this->call('drule.delete', $ids);
		$this->assertArrayHasKey('result', $response, "Failed to network discovery rules with IDs " . implode(', ', $ids));
		$this->assertArrayHasKey('druleids', $response['result'], "Failed to network discovery rules with IDs " . implode(', ', $ids));
		$this->assertCount(count($ids), $response['result']['druleids'],  "Failed to network discovery rules with IDs " . implode(', ', $ids));
	}

	private function createDruleSnmpv2($name, $iprange, $oid, $proxyId) {
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
					'uniq' => 0,
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

		return $response['result']['druleids'][0];
	}

	private function createActionHostAdd($druleId, $actionName) {
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
					'operationtype' => OPERATION_TYPE_HOST_ADD,
				]
			]
		]);
		$this->assertArrayHasKey('result', $response, 'Failed to create a discovery action "' . $actionName . '"');
		$this->assertArrayHasKey('actionids', $response['result'], 'Failed to create a discovery action "' . $actionName . '"');
		$this->assertCount(1, $response['result'], 'Failed to create a discovery action "' . $actionName . '"');

		return $this->getActionIdByName($actionName);
	}

	private function createProxy() {
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

		self::$proxyId = $response['result']['proxyids'][0];
	}

	private function deleteProxy() {
		$response = $this->call('proxy.delete', [self::$proxyId]);

		$this->assertArrayHasKey('result', $response, 'Failed to delete proxy');
		$this->assertArrayHasKey('proxyids',  $response['result'], 'Failed to delete proxy');
		$this->assertCount(1, $response['result']['proxyids'], 'Failed to delete proxy');
		$this->assertEquals(self::$proxyId, $response['result']['proxyids'][0], 'Failed to delete proxy');

		self::$proxyId = 0;
	}

	private function deleteAllHosts() {
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

	private function getActionIdByName($name) {
		$response = $this->call('action.get', [
			'filter' => [
				'name' => $name
			]
		]);

		$this->assertArrayHasKey('result', $response, 'Failed to retrieve the discovery action');
		$this->assertCount(1, $response['result'], 'Failed to retrieve the discovery action');
		$discoveryAction = $response['result'][0];
		$this->assertArrayHasKey('actionid', $discoveryAction, 'Failed to get actionid of the discovery action');

		return $discoveryAction['actionid'];
	}

	/**
	 * Configuration provider for proxy in database mode
	 *
	 * @return array
	 */
	public function proxyDBModeconfigurationProvider()
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
	public function proxyMemoryModeconfigurationProvider()
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
	public function proxyHybridModeconfigurationProvider()
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
	public function prepareData() {
		$this->deleteAllHosts();

		$response = $this->call('drule.create', [
			'name' => self::DRULE_WITH_SUCCESS_NAME,
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
		self::$druleWithSuccessId = $response['result']['druleids'][0];

		$response = $this->call('action.create', [
			'name' => self::ACTION_WITH_SUCCESS_NAME,
			'eventsource' => EVENT_SOURCE_DISCOVERY,
			'status' => ACTION_STATUS_ENABLED,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_DRULE,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => self::$druleWithSuccessId
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
		$this->assertCount(1, $response['result'], 'Failed to create a discovery action');

		self::$actionWithSuccessId = $this->getActionIdByName(self::ACTION_WITH_SUCCESS_NAME);
	}

	/**
	 * @required-components server
	 */
	public function testDiscoveryRules_opAddHostTags()
	{
		$this->waitForDiscoveryWithTags([
			['tag' => 'add_tag1', 'value' => 'add_value1'],
			['tag' => 'add_tag2', 'value' => 'add_value2']
		]);
	}

	/**
	 * @depends testDiscoveryRules_opAddHostTags
	 * @required-components server
	 */
	public function testDiscoveryRules_opDelHostTags()
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
			'actionid' => self::$actionWithSuccessId,
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

		$this->deleteActions([self::$actionWithSuccessId]);
		$this->deleteDrules([self::$druleWithSuccessId]);
		$this->deleteAllHosts();
	}

	/**
	 * @depends testDiscoveryRules_opDelHostTags
	 * @required-components server
	 */
	public function testDiscoveryRules_snmpErrorViaServer() {
		/* Restarting Zabbix server is not mandatory. It just helps to speed the test up. */
		$this->stopComponent(self::COMPONENT_SERVER);
		self::$druleWithErrId =  $this->createDruleSnmpv2(self::DRULE_WITH_ERR_NAME, '127.0.0.1/16',
				self::INVALID_OID, NULL);
		self::$actionWithWithErrId = $this->createActionHostAdd(self::$druleWithErrId,
				self::ACTION_WITH_ERR_NAME);

		$this->startComponent(self::COMPONENT_SERVER);
		$this->waitForDiscoveryErr(self::EXPECTED_INVALID_OID_ERR_MSG);

		$this->deleteActions([self::$actionWithWithErrId]);
		$this->deleteDrules([self::$druleWithErrId]);
	}

	private function proxyTest() {
		/* Restarting Zabbix server is not mandatory. It just helps to speed the test up. */
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->createProxy();
		self::$druleWithSuccessId = $this->createDruleSnmpv2(self::DRULE_WITH_SUCCESS_NAME, '127.0.0.1/30',
				self::VALID_OID, self::$proxyId);
		self::$druleWithErrId = $this->createDruleSnmpv2(self::DRULE_WITH_ERR_NAME, '127.0.0.1/30',
				self::INVALID_OID, self::$proxyId);
		self::$actionWithSuccessId = $this->createActionHostAdd(self::$druleWithSuccessId,
				self::ACTION_WITH_SUCCESS_NAME);
		self::$actionWithWithErrId = $this->createActionHostAdd(self::$druleWithErrId,
				self::ACTION_WITH_ERR_NAME);

		$this->startComponent(self::COMPONENT_SERVER);
		$this->waitForDiscoveryErr(self::EXPECTED_INVALID_OID_ERR_MSG);
		$this->waitForDiscovery('localhost');

		$this->deleteActions([self::$actionWithSuccessId, self::$actionWithWithErrId]);
		$this->deleteDrules([self::$druleWithSuccessId, self::$druleWithErrId]);
		$this->deleteAllHosts();
		$this->deleteProxy();
	}

	/**
	 * @depends testDiscoveryRules_snmpErrorViaServer
	 * @required-components server,proxy
	 * @configurationDataProvider proxyDBModeconfigurationProvider
	 */
	public function testDiscoveryRules_snmpErrorViaProxyDBMode() {
		$this->proxyTest();
	}

	/**
	 * @depends testDiscoveryRules_snmpErrorViaProxyDBMode
	 * @required-components server,proxy
	 * @configurationDataProvider proxyMemoryModeconfigurationProvider
	 */
	public function testDiscoveryRules_snmpErrorViaProxyMemoryMode() {
		$this->proxyTest();
	}

	/**
	 * @depends testDiscoveryRules_snmpErrorViaProxyMemoryMode
	 * @required-components server,proxy
	 * @configurationDataProvider proxyHybridModeconfigurationProvider
	 */
	public function testDiscoveryRules_snmpErrorViaProxyHybridMode() {
		$this->proxyTest();
	}
}
