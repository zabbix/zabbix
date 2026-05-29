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

/**
 * Test suite for autoregistration with proxy group condition.
 * @suite-components-reuse true
 * @required-components server, proxy, agent
 * @onAfter clearData
 */
class testAutoregistrationProxyGroup extends CIntegrationTest {

	const PROXY_GROUP_NAME = 'Autoreg proxy group';
	const PROXY_NAME = 'Autoreg proxy';
	const AGENT_HOSTNAME = 'autoreg_pg_agent';
	const AUTOREG_ACTION_NAME = 'Autoreg by proxy group';
	const DRULE_NAME = 'Autoreg proxy group drule';
	const DISCOVERY_ACTION_NAME = 'Discovery by proxy group';
	const DISCOVERED_HOST = '127.0.0.1';
	const LINUX_TEMPLATEID = 10001;

	private static $proxy_groupid;
	private static $proxyid;
	private static $actionid;
	private static $druleid;
	private static $discovery_actionid;

	/**
	 * Component configuration provider.
	 *
	 * @return array
	 */
	public function configurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 3,
				'LogFileSize' => 0,
			],
			self::COMPONENT_PROXY => [
				'ProxyMode' => PROXY_OPERATING_MODE_ACTIVE,
				'DebugLevel' => 3,
				'LogFileSize' => 0,
				'Hostname' => self::PROXY_NAME,
				'Server' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort')
			],
			self::COMPONENT_AGENT => [
				'Hostname' => self::AGENT_HOSTNAME,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_PROXY, 'ListenPort'),
				'RefreshActiveChecks' => 2,
				'HostInterface' => 'localhost'
			]
		];
	}

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		$response = $this->call('host.get', []);
		$hostids = [];
		foreach ($response['result'] as $host) {
			$hostids[] = $host['hostid'];
		}
		if (count($hostids) > 0) {
			$this->call('host.delete', $hostids);
		}

		$response = $this->call('proxygroup.create', [
			'name' => self::PROXY_GROUP_NAME,
			'failover_delay' => '10',
			'min_online' => '1'
		]);
		$this->assertArrayHasKey('proxy_groupids', $response['result']);
		$this->assertCount(1, $response['result']['proxy_groupids']);
		self::$proxy_groupid = $response['result']['proxy_groupids'][0];

		$response = $this->call('proxy.create', [
			'name' => self::PROXY_NAME,
			'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
			'local_address' => '127.0.0.1',
			'local_port' => $this->getConfigurationValue(self::COMPONENT_PROXY, 'ListenPort'),
			'proxy_groupid' => self::$proxy_groupid
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);
		self::$proxyid = $response['result']['proxyids'][0];

		$response = $this->call('action.create', [
			[
				'name' => self::AUTOREG_ACTION_NAME,
				'eventsource' => EVENT_SOURCE_AUTOREGISTRATION,
				'status' => ACTION_STATUS_ENABLED,
				'filter' => [
					'conditions' => [
						[
							'conditiontype' => ZBX_CONDITION_TYPE_PROXY_GROUP,
							'operator' => CONDITION_OPERATOR_EQUAL,
							'value' => self::$proxy_groupid
						]
					],
					'evaltype' => CONDITION_EVAL_TYPE_AND_OR
				],
				'operations' => [
					[
						'operationtype' => OPERATION_TYPE_HOST_ADD
					],
					[
						'operationtype' => OPERATION_TYPE_TEMPLATE_ADD,
						'optemplate' => [
							[
								'templateid' => self::LINUX_TEMPLATEID
							]
						]
					]
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertCount(1, $response['result']['actionids']);
		self::$actionid = $response['result']['actionids'][0];

		return true;
	}

	public static function clearData(): void {
		if (CAPIHelper::getSessionId() === null) {
			CAPIHelper::authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
		}

		$action_ids = [];
		if (self::$discovery_actionid !== null) {
			$action_ids[] = self::$discovery_actionid;
			self::$discovery_actionid = null;
		}
		if (self::$actionid !== null) {
			$action_ids[] = self::$actionid;
			self::$actionid = null;
		}
		if (count($action_ids) > 0) {
			CAPIHelper::call('action.delete', $action_ids);
		}

		if (self::$druleid !== null) {
			CAPIHelper::call('drule.delete', [self::$druleid]);
			self::$druleid = null;
		}

		$response = CAPIHelper::call('host.get', [
			'output' => ['hostid'],
			'filter' => ['host' => [self::AGENT_HOSTNAME, self::DISCOVERED_HOST]]
		]);
		$hostids = array_column($response['result'], 'hostid');
		if (count($hostids) > 0) {
			CAPIHelper::call('host.delete', $hostids);
		}

		if (self::$proxyid !== null) {
			CAPIHelper::call('proxy.delete', [self::$proxyid]);
			self::$proxyid = null;
		}

		if (self::$proxy_groupid !== null) {
			CAPIHelper::call('proxygroup.delete', [self::$proxy_groupid]);
			self::$proxy_groupid = null;
		}
	}

	/**
	 * Create a network discovery rule that runs on the proxy and probes the
	 * agent via SVC_AGENT (agent.version), plus a discovery action gated on
	 * the proxy group. Verify the host is discovered and linked to the
	 * "Linux by Zabbix agent" template.
	 *
	 * @configurationDataProvider configurationProvider
	 */
	public function testAutoregistrationProxyGroup_discoveryHost() {
		$response = $this->call('drule.create', [
			'name' => self::DRULE_NAME,
			'proxyid' => self::$proxyid,
			'iprange' => self::DISCOVERED_HOST,
			'delay' => '1s',
			'dchecks' => [
				[
					'type' => SVC_AGENT,
					'key_' => 'agent.version',
					'ports' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort'),
					'uniq' => 0,
					'host_source' => ZBX_DISCOVERY_IP,
					'name_source' => ZBX_DISCOVERY_IP
				]
			]
		]);
		$this->assertArrayHasKey('druleids', $response['result']);
		$this->assertCount(1, $response['result']['druleids']);
		self::$druleid = $response['result']['druleids'][0];

		$response = $this->call('action.create', [
			[
				'name' => self::DISCOVERY_ACTION_NAME,
				'eventsource' => EVENT_SOURCE_DISCOVERY,
				'status' => ACTION_STATUS_ENABLED,
				'filter' => [
					'conditions' => [
						[
							'conditiontype' => ZBX_CONDITION_TYPE_PROXY_GROUP,
							'operator' => CONDITION_OPERATOR_EQUAL,
							'value' => self::$proxy_groupid
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
					],
					[
						'operationtype' => OPERATION_TYPE_TEMPLATE_ADD,
						'optemplate' => [
							[
								'templateid' => self::LINUX_TEMPLATEID
							]
						]
					]
				]
			]
		]);
		$this->assertArrayHasKey('actionids', $response['result']);
		$this->assertCount(1, $response['result']['actionids']);
		self::$discovery_actionid = $response['result']['actionids'][0];

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY);

		$response = $this->callUntilDataIsPresent('host.get', [
			'output' => ['hostid', 'host'],
			'filter' => ['host' => self::DISCOVERED_HOST],
			'selectParentTemplates' => ['templateid', 'host']
		], 20, 1);

		$this->assertCount(1, $response['result'],
				'Failed to discover host before timeout: '.json_encode($response['result']));

		$host = $response['result'][0];
		$this->assertEquals(self::DISCOVERED_HOST, $host['host']);

		$this->assertArrayHasKey('parentTemplates', $host);
		$templateids = array_column($host['parentTemplates'], 'templateid');
		$this->assertContains((string) self::LINUX_TEMPLATEID, $templateids,
				'Discovered host is expected to be linked to "Linux by Zabbix agent" template, got: '.
				json_encode($host['parentTemplates']));
	}

	/**
	 * Start agent, then wait for host to be autoregistered through the proxy
	 * group and linked to the "Linux by Zabbix agent" template.
	 *
	 * @configurationDataProvider configurationProvider
	 */
	public function testAutoregistrationProxyGroup_autoregHost() {
		$response = $this->callUntilDataIsPresent('host.get', [
			'output' => ['hostid', 'host', 'monitored_by', 'proxy_groupid'],
			'filter' => ['host' => self::AGENT_HOSTNAME],
			'selectParentTemplates' => ['templateid', 'host']
		], 30, 1);

		$this->assertCount(1, $response['result'],
				'Failed to autoregister host before timeout: '.json_encode($response['result']));

		$host = $response['result'][0];
		$this->assertEquals(self::AGENT_HOSTNAME, $host['host']);
		$this->assertEquals(ZBX_MONITORED_BY_PROXY_GROUP, $host['monitored_by'],
				'Host is expected to be monitored by proxy group');
		$this->assertEquals(self::$proxy_groupid, $host['proxy_groupid'],
				'Host is expected to be assigned to the created proxy group');

		$this->assertArrayHasKey('parentTemplates', $host);
		$templateids = array_column($host['parentTemplates'], 'templateid');
		$this->assertContains((string) self::LINUX_TEMPLATEID, $templateids,
				'Host is expected to be linked to "Linux by Zabbix agent" template, got: '.
				json_encode($host['parentTemplates']));
	}
}
