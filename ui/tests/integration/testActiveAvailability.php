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
 * Test suite to check if trigger state is updated properly
 * when item state toggles between normal and unsupported
 *
 * @backup hosts, host_rtdata, proxy, proxy_rtdata, auditlog, changelog, config, ha_node, expressions, globalmacro
 * @backup interface, item_rtdata, items, regexps, task, task_data
 * @hosts test
 */
class testActiveAvailability extends CIntegrationTest {

	private static $hostid;
	private static $interfaceid;

	const HOST_NAME = 'test';
	const ACT_FILE_NAME = '/tmp';

	/**
	 * Component configuration provider for server.
	 *
	 * @return array
	 */
	public function serverConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 0
			],
			self::COMPONENT_AGENT => [
				'Hostname' => self::HOST_NAME,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort'),
				'HeartbeatFrequency' => 5
			]
		];
	}

	/**
	 * Component configuration provider for proxy.
	 *
	 * @return array
	 */
	public function activeProxyConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20
			],
			self::COMPONENT_PROXY => [
				'ProxyMode' => PROXY_OPERATING_MODE_ACTIVE,
				'DebugLevel' => 4,
				'LogFileSize' => 20,
				'Hostname' => 'active proxy',
				'Server' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort')
			],
			self::COMPONENT_AGENT => [
				'Hostname' => self::HOST_NAME,
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_PROXY, 'ListenPort'),
				'HeartbeatFrequency' => 5
			]
		];
	}

	/**
	 * Component configuration provider for proxy.
	 *
	 * @return array
	 */
	public function passiveProxyConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 4,
				'LogFileSize' => 20,
				'ProxyDataFrequency' => 1
			],
			self::COMPONENT_PROXY => [
				'ProxyMode' => PROXY_OPERATING_MODE_PASSIVE,
				'DebugLevel' => 4,
				'LogFileSize' => 20,
				'Hostname' => 'passive proxy',
				'Server' => '127.0.0.1'
			],
			self::COMPONENT_AGENT => [
				'Hostname' => self::HOST_NAME,
				'DebugLevel' => 4,
				'LogFileSize' => 20,
				'ServerActive' => '127.0.0.1:'.PHPUNIT_PORT_PREFIX.self::PROXY_PORT_SUFFIX,
				'HeartbeatFrequency' => 5
			]
		];
	}

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		// Create host "test"
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

		// Get host interface ids.
		$response = $this->call('host.get', [
			'output' => ['host'],
			'hostids' => [self::$hostid],
			'selectInterfaces' => ['interfaceid']
		]);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('interfaces', $response['result'][0]);
		$this->assertArrayHasKey(0, $response['result'][0]['interfaces']);
		self::$interfaceid = $response['result'][0]['interfaces'][0]['interfaceid'];

		// Create active item
		$response = $this->call('item.create', [
			'hostid' => self::$hostid,
			'name' => "act1",
			'key_' => 'vfs.dir.size['.self::ACT_FILE_NAME.']',
			'interfaceid' => 0,
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'value_type' => ITEM_VALUE_TYPE_TEXT,
			'delay' => '1s'
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));

		return true;
	}

	public function testActiveAvailability_initialAvailability() {
		$response = $this->callUntilDataIsPresent('host.get', [
			'output' => 'extend',
			'hostids' => [self::$hostid],
			'filter' => [
				'active_available' => INTERFACE_AVAILABLE_UNKNOWN
			]
		], 10, 1);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('active_available', $response['result'][0]);
	}

	/**
	 * @required-components server, agent
	 * @configurationDataProvider serverConfigurationProvider
	 */
	public function testActiveAvailability_serverWithAvailableAgent() {
		$response = $this->callUntilDataIsPresent('host.get', [
			'output' => 'extend',
			'hostids' => [self::$hostid],
			'filter' => [
				'active_available' => INTERFACE_AVAILABLE_TRUE
			]
		], 10, 3);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('active_available', $response['result'][0]);

		$this->stopComponent(self::COMPONENT_AGENT);
		$response = $this->callUntilDataIsPresent('host.get', [
			'output' => 'extend',
			'hostids' => [self::$hostid],
			'filter' => [
				'active_available' => INTERFACE_AVAILABLE_FALSE
			]
		], 15, 3);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('active_available', $response['result'][0]);
	}


	/**
	 * @required-components server, agent
	 * @configurationDataProvider serverConfigurationProvider
	 * @backup hosts,host_rtdata
	 */
	public function testActiveAvailability_disabledHostCheck() {
		$response = $this->call('host.update', [
			'hostid' => self::$hostid,
			'status' => HOST_STATUS_NOT_MONITORED
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);

		$response = $this->callUntilDataIsPresent('host.get', [
			'output' => 'extend',
			'hostids' => [self::$hostid],
			'filter' => [
				'active_available' => INTERFACE_AVAILABLE_UNKNOWN
			]
		], 15, 4);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('active_available', $response['result'][0]);
	}

	/**
	 *
	 * @required-components server,proxy,agent
	 * @configurationDataProvider activeProxyConfigurationProvider
	 * @backup hosts,host_rtdata,proxy,proxy_rtdata
	 */
	public function testActiveAvailability_activeProxyActiveAvailCheck() {
		$response = $this->call('proxy.create', [
			'name' => 'active proxy',
			'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
			'hosts' => [
				[
					"hostid" => self::$hostid
				]
			]
		]);
		$this->assertArrayHasKey("proxyids", $response['result']);

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->reloadConfigurationCache(self::COMPONENT_PROXY);
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'sending configuration data to proxy');
		self::waitForLogLineToBePresent(self::COMPONENT_PROXY, 'received configuration data from server');

		$response = $this->callUntilDataIsPresent('host.get', [
			'output' => 'extend',
			'hostids' => [self::$hostid],
			'filter' => [
				'active_available' => INTERFACE_AVAILABLE_TRUE
			]
		], 15, 5);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('active_available', $response['result'][0]);

		$this->stopComponent(self::COMPONENT_AGENT);

		$response = $this->callUntilDataIsPresent('host.get', [
			'output' => 'extend',
			'hostids' => [self::$hostid],
			'filter' => [
				'active_available' => INTERFACE_AVAILABLE_FALSE
			]
		], 15, 5);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('active_available', $response['result'][0]);
	}

	/**
	 *
	 * @required-components server,proxy,agent
	 * @configurationDataProvider passiveProxyConfigurationProvider
	 * @backup hosts,host_rtdata,proxy,proxy_rtdata
	 */
	public function testActiveAvailability_passiveProxyActiveAvailCheck() {
		$response = $this->call('proxy.create', [
			'name' => 'passive proxy',
			'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
			'hosts' => [
				[
					"hostid" => self::$hostid
				]
			],
			'address' => '127.0.0.1',
			'port' => PHPUNIT_PORT_PREFIX.self::PROXY_PORT_SUFFIX
		]);
		$this->assertArrayHasKey("proxyids", $response['result']);

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->reloadConfigurationCache(self::COMPONENT_PROXY);
		self::waitForLogLineToBePresent(self::COMPONENT_SERVER, 'sending configuration data to proxy');
		self::waitForLogLineToBePresent(self::COMPONENT_PROXY, 'received configuration data from server');

		$response = $this->callUntilDataIsPresent('host.get', [
			'output' => 'extend',
			'hostids' => [self::$hostid],
			'filter' => [
				'active_available' => INTERFACE_AVAILABLE_TRUE
			]
		], 15, 5);

		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('active_available', $response['result'][0]);

		$this->stopComponent(self::COMPONENT_AGENT);

		$response = $this->callUntilDataIsPresent('host.get', [
			'output' => 'extend',
			'hostids' => [self::$hostid],
			'filter' => [
				'active_available' => INTERFACE_AVAILABLE_FALSE
			]
		], 15, 5);
		$this->assertArrayHasKey(0, $response['result']);
		$this->assertArrayHasKey('active_available', $response['result'][0]);
	}
}

