<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

require_once dirname(__FILE__).'/../include/CIntegrationTest.php';

/**
 * Test suite for discovery rules
 *
 * @backup hosts,drules,actions,operations,optag,host_tag
 * @backup auditlog,changelog,config,ha_node
 */
class testDiscoveryRules extends CIntegrationTest {
	const DRULE_NAME = 'Test discovery rule';
	const DACTION_NAME = 'Test discovery action';
	const PROXY_NAME = 'Test proxy';
	const INVALID_OID = 'invalid.OID';
	const EXPECTED_INVALID_OID_ERR_MSG = "'SNMPv2c agent' checks failed: " . '"snmp_parse_oid(): cannot parse OID "' . self::INVALID_OID . '"."';
	const SLEEP_TIME = 1;
	const MAX_ATTEMPTS_DISCOVERY = 20;
	const MAX_ATTEMPTS_DISCOVERY_ERR = 30;

	private static $discoveryRuleId;
	private static $discoveryActionId;
	private static $discoveredHostId;
	private static $proxyId;

	private function waitForDiscovery($expectedTags, $notExpectedTags = []) {
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

	private function waitForDiscoveryErr($errStr) {
		for ($i = 0; $i < self::MAX_ATTEMPTS_DISCOVERY_ERR; $i++) {
			try {
				$response = $this->call('drule.get', [
					'filter' => [
						'name' => self::DRULE_NAME,
					],
				]);

				$this->assertArrayHasKey('result', $response, 'Failed to get discovery rule error');
				$this->assertCount(1, $response['result'], 'Failed to get discovery rule error');

				$drule = $response['result'][0];
				$this->assertArrayHasKey('name', $drule, 'Failed to get discovery rule error');
				$this->assertEquals($errStr, $drule['error'], 'Failed to get discovery rule error');

				break;
			} catch (Exception $e) {
				if ($i == self::MAX_ATTEMPTS_DISCOVERY_ERR - 1)
					throw $e;
				else
					sleep(self::SLEEP_TIME);
			}
		}
	}

	private function deleteAction() {
		$response = $this->call('action.delete', [self::$discoveryActionId]);
		$this->assertArrayHasKey('result', $response, "Failed to clear action '" . self::DACTION_NAME . "'");
		$this->assertArrayHasKey('actionids', $response['result'], "Failed to clear action '" . self::DACTION_NAME . self::DACTION_NAME . "'");
		$this->assertCount(1, $response['result']['actionids'], "Failed to clear action '" . self::DACTION_NAME . self::DACTION_NAME . "'");
		$this->assertEquals(self::$discoveryActionId,  $response['result']['actionids'][0], "Failed to clear action '" . self::DACTION_NAME . "'");
		self::$discoveryActionId = 0;
	}

	private function deleteDrule() {
		$response = $this->call('drule.delete', [self::$discoveryRuleId]);
		$this->assertArrayHasKey('result', $response, "Failed to network discovery rule '" . self::DRULE_NAME . "'");
		$this->assertArrayHasKey('druleids', $response['result'], "Failed to network discovery rule '" . self::DRULE_NAME . "'");
		$this->assertCount(1, $response['result']['druleids'], "Failed to network discovery rule '" . self::DRULE_NAME . "'");
		$this->assertEquals(self::$discoveryRuleId, $response['result']['druleids'][0], "Failed to network discovery rule '" . self::DRULE_NAME . "'");
		self::$discoveryRuleId = 0;
	}

	private function createDruleSnmpv2($iprange, $proxyId=NULL) {
		$drule = [
			'name' => self::DRULE_NAME,
			'delay' => '1s',
			'iprange' => $iprange,
			'concurrency_max' => ZBX_DISCOVERY_CHECKS_UNLIMITED,
			'dchecks' => [
				[
					'type' => SVC_SNMPv2c,
					'key_' => self::INVALID_OID,
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

		self::$discoveryRuleId = $response['result']['druleids'][0];
	}

	private function createDactionHostAdd($druleId) {
		$response = $this->call('action.create', [
			'name' => self::DACTION_NAME,
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
		$this->assertArrayHasKey('result', $response, 'Failed to create a discovery action');
		$this->assertArrayHasKey('actionids', $response['result'], 'Failed to create a discovery action');
		$this->assertCount(1, $response['result'], 'Failed to create a discovery action');

		self::$discoveryActionId = $this->getDactionIdByName(self::DACTION_NAME);
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

	private function getDactionIdByName($name) {
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
		self::$discoveryRuleId = $response['result']['druleids'][0];

		$response = $this->call('action.create', [
			'name' => self::DACTION_NAME,
			'eventsource' => EVENT_SOURCE_DISCOVERY,
			'status' => ACTION_STATUS_ENABLED,
			'filter' => [
				'conditions' => [
					[
						'conditiontype' => ZBX_CONDITION_TYPE_DRULE,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => self::$discoveryRuleId
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

		self::$discoveryActionId = $this->getDactionIdByName(self::DACTION_NAME);
	}

	/**
	 * @required-components server
	 */
	public function testDiscoveryRules_opAddHostTags()
	{
		$this->waitForDiscovery([
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

		$this->waitForDiscovery([
			['tag' => 'add_tag1', 'value' => 'add_value1'],
			['tag' => 'add_tag2', 'value' => 'add_value2']
		],
		[
			['tag' => 'del_tag3', 'value' => 'del_value3'],
			['tag' => 'del_tag4', 'value' => 'del_value4']
		]);

		$this->deleteAction();
		$this->deleteDrule();
		$this->deleteAllHosts();
	}

	/**
	 * @depends testDiscoveryRules_opDelHostTags
	 * @required-components server
	 */
	public function testDiscoveryRules_snmpErrorViaServer() {
		/* Restarting Zabbix server is not mandatory. It just helps to speed the test up. */
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->createDruleSnmpv2('127.0.0.1');
		$this->createDactionHostAdd(self::$discoveryRuleId);

		$this->startComponent(self::COMPONENT_SERVER);
		$this->waitForDiscoveryErr(self::EXPECTED_INVALID_OID_ERR_MSG);

		$this->deleteAction();
		$this->deleteDrule();
	}

	/**
	 * @depends testDiscoveryRules_snmpErrorViaServer
	 * @required-components server,proxy
	 * @configurationDataProvider proxyDBModeconfigurationProvider
	 */
	public function testDiscoveryRules_snmpErrorViaProxyDBMode() {
		/* Restarting Zabbix server is not mandatory. It just helps to speed the test up. */
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->createProxy();
		$this->createDruleSnmpv2('127.0.0.1/16', self::$proxyId);
		$this->createDactionHostAdd(self::$discoveryRuleId);

		$this->startComponent(self::COMPONENT_SERVER);
		$this->waitForDiscoveryErr(self::EXPECTED_INVALID_OID_ERR_MSG);

		$this->deleteAction();
		$this->deleteDrule();
		$this->deleteProxy();
	}

	/**
	 * @depends testDiscoveryRules_snmpErrorViaProxyDBMode
	 * @required-components server,proxy
	 * @configurationDataProvider proxyMemoryModeconfigurationProvider
	 */
	public function testDiscoveryRules_snmpErrorViaProxyMemoryMode() {
		/* Restarting Zabbix server is not mandatory. It just helps to speed the test up. */
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->createProxy();
		$this->createDruleSnmpv2('127.0.0.0/16', self::$proxyId);
		$this->createDactionHostAdd(self::$discoveryRuleId);

		$this->startComponent(self::COMPONENT_SERVER);
		$this->waitForDiscoveryErr(self::EXPECTED_INVALID_OID_ERR_MSG);

		$this->deleteAction();
		$this->deleteDrule();
		$this->deleteProxy();
	}

	/**
	 * @depends testDiscoveryRules_snmpErrorViaProxyMemoryMode
	 * @required-components server,proxy
	 * @configurationDataProvider proxyHybridModeconfigurationProvider
	 */
	public function testDiscoveryRules_snmpErrorViaProxyHybridMode() {
		/* Restarting Zabbix server is not mandatory. It just helps to speed the test up. */
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->createProxy();
		$this->createDruleSnmpv2('127.0.0.0/16', self::$proxyId);
		$this->createDactionHostAdd(self::$discoveryRuleId);

		$this->startComponent(self::COMPONENT_SERVER);
		$this->waitForDiscoveryErr(self::EXPECTED_INVALID_OID_ERR_MSG);

		$this->deleteAction();
		$this->deleteDrule();
		$this->deleteProxy();
	}
}
