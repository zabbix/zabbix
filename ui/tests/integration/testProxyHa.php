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
 * Test suite for High availability
 *
 * @backup hosts, proxy, host_rtdata, proxy_rtdata, proxy_group, host_proxy
 */
class testProxyHa extends CIntegrationTest {

	private static $proxy_groupid;
	private static $proxyid1;
	private static $proxyid2;
	private static $hostid1;
	private static $hostid2;
	private static $assigned_proxyids;

	const PG_NAME = "Proxy group 1";
	const PROXY1_HOSTNAME = "P1";
	const PROXY1_HA_HOSTNAME = "P2";

	/**
	 * Component configuration provider for test case 'host is being synced to proxies after reassignment'.
	 *
	 * @return array
	 */
	public function reassignmentConfigurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 5,
				'LogFileSize' => 0,
				'UnavailableDelay' => 5,
				'UnreachableDelay' => 1
			],
			self::COMPONENT_PROXY => [
				'Hostname' => self::PROXY1_HOSTNAME,
				'DebugLevel' => 5,
				'LogFileSize' => 0,
				'Server' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort')
			]
		];
	}

	/**
	 * Component configuration provider.
	 *
	 * @return array
	 */
	public function configurationProvider() {
		return [
			self::COMPONENT_SERVER => [
				'DebugLevel' => 5,
				'LogFileSize' => 0,
				'UnavailableDelay' => 5,
				'UnreachableDelay' => 1
			],
			self::COMPONENT_PROXY => [
				'DebugLevel' => 5,
				'LogFileSize' => 0,
				'Hostname' => self::PROXY1_HOSTNAME,
				'Server' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort')
			],
			self::COMPONENT_PROXY_HANODE1 => [
				'DebugLevel' => 5,
				'LogFileSize' => 0,
				'Hostname' => self::PROXY1_HA_HOSTNAME,
				'Server' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort')
			]
		];
	}

	/**
	 * @required-components server, proxy, proxy_ha1
	 * @inheritdoc
	 */
	public function prepareData() {
		$socketDir = $this->getConfigurationValue(self::COMPONENT_PROXY_HANODE1, 'SocketDir');

		if (file_exists($socketDir) === false) {
			mkdir($socketDir);
		}

		$response = $this->call('proxygroup.create', [
			'name' => 'Proxy group 1',
			'failover_delay' => '10',
			'min_online' => '1'
		]);
		$this->assertArrayHasKey('proxy_groupids', $response['result']);
		$this->assertCount(1, $response['result']['proxy_groupids']);
		self::$proxy_groupid = $response['result']['proxy_groupids'][0];

		$response = $this->call('host.create', [
			'host' => 'host1',
			'interfaces' => [
				'type' => 1,
				'main' => 1,
				'useip' => 1,
				'ip' => '127.0.0.1',
				'dns' => '',
				'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
			],
			'monitored_by' => ZBX_MONITORED_BY_PROXY_GROUP,
			'proxy_groupid' => self::$proxy_groupid,
			'groups' => ['groupid' => 4]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid1 = $response['result']['hostids'][0];

		$response = $this->call('host.create', [
			'host' => 'host2',
			'interfaces' => [
				'type' => 1,
				'main' => 1,
				'useip' => 1,
				'ip' => '127.0.0.2',
				'dns' => '',
				'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
			],
			'monitored_by' => ZBX_MONITORED_BY_PROXY_GROUP,
			'proxy_groupid' => self::$proxy_groupid,
			'groups' => ['groupid' => 4]
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		self::$hostid2 = $response['result']['hostids'][0];

		$response = $this->call('proxy.create', [
			'name' => self::PROXY1_HOSTNAME,
			'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
			'local_address' => '127.0.0.1',
			'local_port' => $this->getConfigurationValue(self::COMPONENT_PROXY, 'ListenPort'),
			'proxy_groupid' => self::$proxy_groupid
		]);
		$this->assertArrayHasKey("proxyids", $response['result']);
		self::$proxyid1 = $response['result']['proxyids'][0];

		$response = $this->call('proxy.create', [
			'name' => self::PROXY1_HA_HOSTNAME,
			'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
			'local_address' => '127.0.0.1',
			'local_port' => $this->getConfigurationValue(self::COMPONENT_PROXY_HANODE1, 'ListenPort'),
			'proxy_groupid' => self::$proxy_groupid
		]);
		$this->assertArrayHasKey("proxyids", $response['result']);
		self::$proxyid2 = $response['result']['proxyids'][0];

		return true;
	}


	/**
	 * Test unassigned group assignment
	 *
	 * @configurationDataProvider configurationProvider
	 * @required-components server, proxy, proxy_ha1
	 */
	public function testProxyHa_tc1() {
		$pg_logline = 'Proxy group "' . self::PG_NAME . '" changed state from \b[a-z]+\b to online';
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, $pg_logline, true, 90, 1, true);

		$assign_logline = '\bassigned hostid (' . self::$hostid1 . '|' . self::$hostid2 . ') to proxyid';
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, $assign_logline, true, 90, 1, true);

		$response = $this->call('host.get', [
			'output' => ['hostid', 'assigned_proxyid'],
			'hostids' => [self::$hostid1, self::$hostid2],
			'preservekeys' => true
		]);
		$this->assertCount(2, $response['result']);
		foreach	($response['result'] as $host) {
			$this->assertArrayHasKey('hostid', $host);
			$this->assertArrayHasKey('assigned_proxyid', $host);
			$this->assertNotEquals('0', $host['assigned_proxyid']);
			self::$assigned_proxyids[$host['hostid']] = $host['assigned_proxyid'];
		}

		return true;
	}

	/**
	 * Test if hosts are not reassigned after restart
	 *
	 * @configurationDataProvider configurationProvider
	 * @required-components server, proxy, proxy_ha1
	 */
	public function testProxyHa_tc2() {
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->startComponent(self::COMPONENT_SERVER);

		$pg_logline = 'Proxy group "' . self::PG_NAME . '" changed state from \b[a-z]+\b to online';
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, $pg_logline, true, 90, 1, true);

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of zbx_dc_sync_configuration()", true, 120, 1, true);

		$response = $this->call('host.get', [
			'output' => ['hostid', 'assigned_proxyid'],
			'hostids' => [self::$hostid1, self::$hostid2],
			'preservekeys' => true
		]);
		$this->assertCount(2, $response['result']);
		foreach	($response['result'] as $host) {
			$this->assertArrayHasKey('hostid', $host);
			$this->assertArrayHasKey('assigned_proxyid', $host);
			$this->assertEquals($host['assigned_proxyid'], self::$assigned_proxyids[$host['hostid']]);
		}

		return true;
	}

	/**
	 * Test if host is reassigned from offline proxy
	 *
	 * @configurationDataProvider configurationProvider
	 * @required-components server, proxy, proxy_ha1
	 */
	public function testProxyHa_tc3() {
		$this->stopComponent(self::COMPONENT_PROXY);

		$monitored_host = null;
		foreach (self::$assigned_proxyids as $hostid => $proxyid) {
			if ($proxyid == self::$proxyid1) {
				$monitored_host = [
					'hostid' => $hostid,
					'initial_proxyid' => $proxyid
				];
				break;
			}
		}
		$this->assertNotNull($monitored_host);

		$px_logline = 'Proxy "' . $this->getConfigurationValue(self::COMPONENT_PROXY, 'Hostname') . '" changed state from \b[a-z]+\b to offline';
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, $px_logline, true, 90, 1, true);

		$assign_logline = 're-assigned hostid ' . $monitored_host['hostid'] . ' to proxyid ' . self::$proxyid2;
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, $assign_logline, true, 90, 1, true);

		$response = $this->call('host.get', [
			'output' => ['hostid', 'assigned_proxyid'],
			'hostids' => [self::$hostid1, self::$hostid2],
			'preservekeys' => true
		]);
		$this->assertCount(2, $response['result']);
		foreach	($response['result'] as $host) {
			$this->assertEquals(self::$proxyid2, $host['assigned_proxyid']);
		}

		$this->startComponent(self::COMPONENT_PROXY);
		$px_logline = 'Proxy "' . $this->getConfigurationValue(self::COMPONENT_PROXY, 'Hostname') . '" changed state from offline to online';
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, $px_logline, true, 90, 1, true);

		$assign_logline = 're-assigned hostid ' . $monitored_host['hostid'] . ' to proxyid ' . $monitored_host['initial_proxyid'];
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, $assign_logline, true, 250, 2, true);

		$response = $this->call('host.get', [
			'output' => ['assigned_proxyid'],
			'hostids' => [$monitored_host['hostid']],
			'preservekeys' => true
		]);
		$this->assertCount(1, $response['result']);
		$this->assertArrayHasKey('assigned_proxyid', $response['result'][$monitored_host['hostid']]);
		$this->assertEquals(self::$proxyid1, $response['result'][$monitored_host['hostid']]['assigned_proxyid']);

		return true;
	}

	/**
	 * Test if host is reassigned from proxy when proxy is removed from group
	 *
	 * @configurationDataProvider configurationProvider
	 * @required-components server, proxy, proxy_ha1
	 */
	public function testProxyHa_tc4() {
		$response = $this->call('proxy.update', [
			'proxyid' => self::$proxyid1,
			'proxy_groupid' => 0
		]);
		$this->assertArrayHasKey("proxyids", $response['result']);
		$this->assertCount(1, $response['result']['proxyids']);

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		$response = $this->call('host.get', [
			'output' => ['assigned_proxyid'],
			'hostids' => [self::$hostid1, self::$hostid2],
			'assigned_proxyid' => self::$proxyid2,
			'preservekeys' => true
		]);
		$this->assertCount(2, $response['result']);

		$assign_logline = '\bassigned hostid (' . self::$hostid1 . '|' . self::$hostid2 . ') to proxyid ' . self::$proxyid2;
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, $assign_logline, true, 120, 2, true);

		return true;
	}

	/**
	 * Test if host is assigned to proxy when proxy is added to group
	 *
	 * @configurationDataProvider configurationProvider
	 * @required-components server, proxy, proxy_ha1
	 */
	public function testProxyHa_tc5() {
		$response = $this->call('proxy.update', [
			'proxyid' => self::$proxyid1,
			'proxy_groupid' => self::$proxy_groupid,
			'local_address' => '127.0.0.1',
			'local_port' => $this->getConfigurationValue(self::COMPONENT_PROXY, 'ListenPort')
		]);
		$this->assertArrayHasKey("proxyids", $response['result']);
		$this->assertCount(1, $response['result']['proxyids']);

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		$response = $this->call('host.get', [
			'output' => ['assigned_proxyid'],
			'hostids' => [self::$hostid1, self::$hostid2],
			'assigned_proxyid' => self::$proxyid2,
			'preservekeys' => true
		]);
		$this->assertCount(2, $response['result']);

		$assign_logline = '\bassigned hostid (' . self::$hostid1 . '|' . self::$hostid2 . ') to proxyid ' . self::$proxyid1;
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, $assign_logline, true, 120, 2, true);

		return true;
	}

	/**
	 * Test if host is unassigned from proxy when removed from proxy group
	 *
	 * @configurationDataProvider configurationProvider
	 * @required-components server, proxy, proxy_ha1
	 */
	public function testProxyHa_tc6() {
		$response = $this->call('host.update', [
			'hostid' => self::$hostid1,
			'monitored_by' => ZBX_MONITORED_BY_SERVER,
			'proxyid' => 0
		]);
		$this->assertArrayHasKey("hostids", $response['result']);
		$this->assertCount(1, $response['result']['hostids']);

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		$response = $this->call('host.get', [
			'output' => ['assigned_proxyid'],
			'hostids' => [self::$hostid1, self::$hostid2],
			'assigned_proxyid' => self::$proxyid2,
			'preservekeys' => true
		]);
		$this->assertCount(2, $response['result']);

		$assign_logline = 'unassigned hostid ' . self::$hostid1;
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, $assign_logline, true, 120, 2, true);

		return true;
	}

	/**
	 * Test if host is assigned to proxy when added to proxy group
	 *
	 * @configurationDataProvider configurationProvider
	 * @required-components server, proxy, proxy_ha1
	 */
	public function testProxyHa_tc7() {
		$response = $this->call('host.update', [
			'hostid' => self::$hostid1,
			'monitored_by' => ZBX_MONITORED_BY_PROXY_GROUP,
			'proxy_groupid' => self::$proxy_groupid
		]);
		$this->assertArrayHasKey("hostids", $response['result']);
		$this->assertCount(1, $response['result']['hostids']);

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);

		$response = $this->call('host.get', [
			'output' => ['assigned_proxyid'],
			'hostids' => [self::$hostid1, self::$hostid2],
			'assigned_proxyid' => self::$proxyid2,
			'preservekeys' => true
		]);
		$this->assertCount(2, $response['result']);

		$assign_logline = '\bassigned hostid ' . self::$hostid1;
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, $assign_logline, true, 120, 2, true);

		return true;
	}

	/**
	 * Test redistribution of discovered hosts
	 *
	 * @configurationDataProvider configurationProvider
	 * @required-components server, proxy, proxy_ha1
	 */
	public function testProxyHa_tc8() {
		$this->stopComponent(self::COMPONENT_SERVER);
		$this->stopComponent(self::COMPONENT_PROXY);
		$this->stopComponent(self::COMPONENT_PROXY_HANODE1);

		$xml = <<<HEREDOC
<?xml version="1.0" encoding="UTF-8"?>
<zabbix_export>
<version>7.0</version>
<host_groups>
<host_group>
	<uuid>6f6799aa69e844b4b3918f779f2abf08</uuid>
	<name>Zabbix servers</name>
</host_group>
</host_groups>
<hosts>
<host>
	<host>Zabbix1</host>
	<name>Zabbix1</name>
	<monitored_by>PROXY_GROUP</monitored_by>
	<proxy_group>
		<name>Proxy group 1</name>
	</proxy_group>
	<groups>
	<group>
		<name>Zabbix servers</name>
	</group>
	</groups>
	<discovery_rules>
	<discovery_rule>
		<name>lld</name>
		<type>SCRIPT</type>
		<key>lld</key>
		<delay>5s</delay>
		<params>hosts = [];
for (var i = 1; i &lt;= 10; i++) {
	hosts.push({
		'{#HOSTNAME}': i
	});
}
return JSON.stringify(hosts);</params>
		<host_prototypes>
		<host_prototype>
			<host>host {#HOSTNAME}</host>
			<name>host {#HOSTNAME}</name>
			<inventory_mode>DISABLED</inventory_mode>
			<group_links>
			<group_link>
				<group>
				<name>Zabbix servers</name>
				</group>
			</group_link>
			</group_links>
			<tags>
				<tag>
					<tag>test</tag>
					<value>proxyha</value>
				</tag>
			</tags>
		</host_prototype>
		</host_prototypes>
	</discovery_rule>
	</discovery_rules>
	<inventory_mode>DISABLED</inventory_mode>
</host>
</hosts>
</zabbix_export>
HEREDOC;

		$response = $this->call('configuration.import', [
			'format' => 'xml',
			'source' => $xml,
			'rules' => [
				'host_groups' =>
				[
				'updateExisting' => true,
				'createMissing' => true
				],
				'hosts' =>
				[
				'updateExisting' => true,
				'createMissing' => true
				],
				'valueMaps' =>
				[
				'updateExisting' => true,
				'createMissing' => true,
				'deleteMissing' => false
				],
				'templateLinkage' =>
				[
				'createMissing' => true,
				'deleteMissing' => false
				],
				'items' =>
				[
				'updateExisting' => true,
				'createMissing' => true,
				'deleteMissing' => false
				],
				'discoveryRules' =>
				[
				'updateExisting' => true,
				'createMissing' => true,
				'deleteMissing' => false
				],
				'triggers' =>
				[
				'updateExisting' => true,
				'createMissing' => true,
				'deleteMissing' => false
				],
				'graphs' =>
				[
				'updateExisting' => true,
				'createMissing' => true,
				'deleteMissing' => false
				],
				'httptests' =>
				[
				'updateExisting' => true,
				'createMissing' => true,
				'deleteMissing' => false
				]

			]
		]);

		$this->startComponent(self::COMPONENT_SERVER);
		$this->startComponent(self::COMPONENT_PROXY);

		$this->startComponent(self::COMPONENT_PROXY_HANODE1);

		$pg_logline = 'Proxy group "' . self::PG_NAME . '" changed state from \b[a-z]+\b to online';
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, $pg_logline, false, 90, 1, true);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'assigned hostid', false, 90, 1);

		$this->reloadConfigurationCache(self::COMPONENT_PROXY);
		$this->reloadConfigurationCache(self::COMPONENT_PROXY_HANODE1);

		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, 'End of lld_update_hosts', true, 120, 1, true);

		$lld_host_count = 10;
		$response = $this->callUntilDataIsPresent('host.get', [
			'output' => ['hostid'],
			'preservekeys' => 'true',
			'tags' => [
				[
					'tag' => 'test',
					'value' => 'proxyha',
					'operator' => 1
				]
			]
		], 30, 1);
		$hostids = array_keys($response['result']);
		$this->assertEquals(count($hostids), $lld_host_count);

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		sleep(1);

		$proxy1_hostcount = CDBHelper::getCount(
			'SELECT NULL FROM host_proxy ' .
			'WHERE proxyid=' . self::$proxyid1 . ' AND hostid IN (' . CDBHelper::escape($hostids) . ')'
		);

		$proxy2_hostcount = CDBHelper::getCount(
			'SELECT NULL FROM host_proxy ' .
			'WHERE proxyid=' . self::$proxyid2 . ' AND hostid IN (' . CDBHelper::escape($hostids) . ')'
		);


		$this->assertEquals($lld_host_count, $proxy1_hostcount + $proxy2_hostcount);
		$this->assertTrue($proxy1_hostcount >= floor($lld_host_count / 2.5));
		$this->assertTrue($proxy2_hostcount >= floor($lld_host_count / 2.5));
	}


	/**
	 * Test if host is being synced to proxies after reassignment
	 *
	 * @configurationDataProvider reassignmentConfigurationProvider
	 * @required-components server, proxy
	 */
	public function testProxyHa_tc9() {
		$response = $this->call('host.get', []);
		$hostids = [];
		foreach ($response['result'] as $host) {
			$hostids[] = $host['hostid'];
		}
		$this->call('host.delete', $hostids);

		$response = $this->call('proxy.get', []);
		$proxyids = [];
		foreach ($response['result'] as $proxy) {
			$proxyids[] = $proxy['proxyid'];
		}
		$this->call('proxy.delete', $proxyids);

		$response = $this->call('proxygroup.delete', [
			self::$proxy_groupid
		]);
		$this->assertArrayHasKey("proxy_groupids", $response['result']);
		$this->assertCount(1, $response['result']['proxy_groupids']);

		$this->stopComponent(self::COMPONENT_SERVER);
		$this->stopComponent(self::COMPONENT_PROXY);
		$this->stopComponent(self::COMPONENT_PROXY_HANODE1);

		$response = $this->call('proxygroup.create', [
			'name' => 'Proxy group X',
			'failover_delay' => '10',
			'min_online' => '1'
		]);
		$this->assertArrayHasKey('proxy_groupids', $response['result']);
		$this->assertCount(1, $response['result']['proxy_groupids']);
		$proxy_groupid = $response['result']['proxy_groupids'][0];

		$response = $this->call('host.create', [
			'host' => 'host3',
			'interfaces' => [
				'type' => 1,
				'main' => 1,
				'useip' => 1,
				'ip' => '127.0.0.1',
				'dns' => '',
				'port' => $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort')
			],
			'groups' => ['groupid' => 4]
		]);

		$this->assertArrayHasKey('hostids', $response['result']);
		$this->assertArrayHasKey(0, $response['result']['hostids']);
		$hostid_trapper = $response['result']['hostids'][0];

		$response = $this->call('item.create', [
			[
				'name' => 'trap',
				'key_' => 'trap',
				'type' => ITEM_TYPE_TRAPPER,
				'hostid' => $hostid_trapper,
				'value_type' => ITEM_VALUE_TYPE_UINT64
			]
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		$this->assertEquals(1, count($response['result']['itemids']));
		$itemid = $response['result']['itemids'][0];

		$response = $this->call('proxy.create', [
			'name' => self::PROXY1_HOSTNAME,
			'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
			"local_address" => "127.0.0.1",
			'local_port' => $this->getConfigurationValue(self::COMPONENT_PROXY, 'ListenPort'),
			'proxy_groupid' => $proxy_groupid
		]);
		$this->assertArrayHasKey("proxyids", $response['result']);
		$proxyid = $response['result']['proxyids'][0];

		$this->startComponent(self::COMPONENT_SERVER);
		$this->startComponent(self::COMPONENT_PROXY);

		$pg_logline = 'Proxy group "Proxy group X" changed state from \b[a-z]+\b to online';
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, $pg_logline, true, 90, 1, true);

		$this->sendSenderValue('host3', 'trap', 333);

		$response = $this->callUntilDataIsPresent('history.get', [
			'output' => ['itemid', 'value', 'clock', 'ns'],
			'history' => ITEM_VALUE_TYPE_UINT64,
			'itemids' => [$itemid],
			'sortfield' => 'clock',
			'sortorder' => 'DESC',
			'limit' => 1
		], 30, 2);
		$this->assertCount(1, $response['result']);

		$response = $this->call('host.update', [
			'hostid' => $hostid_trapper,
			'monitored_by' => ZBX_MONITORED_BY_PROXY_GROUP,
			'proxy_groupid' => $proxy_groupid
		]);
		$this->assertArrayHasKey("hostids", $response['result']);
		$this->assertCount(1, $response['result']['hostids']);

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of zbx_dc_sync_configuration()", true, 120, 1, true);

		$px_logline = 'Proxy "' . self::PROXY1_HOSTNAME . '" changed state from unknown to online';
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, $px_logline, false, 120, 2, true);

		$assign_logline = '\bassigned hostid ' . $hostid_trapper . ' to proxyid ' . $proxyid;
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, $assign_logline, false, 120, 2, true);

		$this->reloadConfigurationCache(self::COMPONENT_SERVER);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of zbx_dc_sync_configuration()", true, 120, 1, true);

		$this->reloadConfigurationCache(self::COMPONENT_PROXY);
		$this->waitForLogLineToBePresent(self::COMPONENT_SERVER, "End of zbx_dc_sync_configuration()", true, 120, 1, true);

		$this->sendSenderValue('host3', 'trap', 1000, self::COMPONENT_PROXY);

		$response = $this->callUntilDataIsPresent('history.get', [
			'output' => ['itemid', 'value', 'clock', 'ns'],
			'history' => ITEM_VALUE_TYPE_UINT64,
			'itemids' => [$itemid]
		], 60, 2);

		$this->assertCount(2, $response['result'], json_encode($response['result']));

		return true;
	}
}
