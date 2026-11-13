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
 * Test suite for moving a host between proxy groups when the host_proxy link of the host is deleted
 * and re-created (with a new hostproxyid) before the server configuration cache is synced.
 *
 * The scenario:
 *   1) host is assigned to proxy 1 in group 1;
 *   2) proxy 2 (the only proxy of group 2) is stopped, so group 2 goes offline;
 *   3) host is moved to group 2 - proxy group manager deletes its host_proxy row, but cannot assign
 *      it to a new proxy because group 2 is offline;
 *   4) proxy 2 is started, group 2 recovers and proxy group manager inserts a new host_proxy row;
 *   5) server configuration cache is synced - the same sync now contains both the insert of the new
 *      host_proxy row and the removal of the old one.
 *
 * The server must end up with the host assigned to proxy 2 only: values sent by proxy 2 must be
 * accepted and deleting the host must remove it from proxy 2 configuration.
 *
 * Components must keep running between test cases: restarting the server would rebuild its
 * configuration cache from scratch and hide the state left by the host_proxy sync.
 *
 * @suite-components-reuse true
 * @required-components server, proxy, proxy_ha1, agent
 * @onAfter clearData
 */
class testProxyGroupHostReassign extends CIntegrationTest {

	const PROXY_GROUP1_NAME = 'Reassign proxy group 1';
	const PROXY_GROUP2_NAME = 'Reassign proxy group 2';
	const PROXY1_NAME = 'Reassign proxy 1';
	const PROXY2_NAME = 'Reassign proxy 2';
	const HOSTNAME = 'pg_reassign_agent';
	const DIRECT_HOSTNAME = 'pg_reassign_direct';
	const RENAME_HOSTNAME = 'pg_reassign_rename';
	const RENAMED_HOSTNAME = 'pg_reassign_renamed';
	const DIRECT_OTHER_HOSTNAME = 'pg_reassign_direct_other';
	const ITEM_KEY = 'agent.ping';
	const FAILOVER_DELAY = 10;
	const USERNAME = 'pg_reassign_admin';
	const PASSWORD = 'zabbix_pg_reassign';

	private static $proxy_groupid1;
	private static $proxy_groupid2;
	private static $proxyid1;
	private static $proxyid2;
	private static $hostid;
	private static $itemid;
	private static $direct_hostid;
	private static $rename_hostid;
	private static $direct_other_hostid;
	private static $usrgrpid;
	private static $roleid;
	private static $userid;
	private static $user_sid;

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
				// Configuration cache must be synced only by the test, otherwise the host_proxy row
				// removal and insert could be split between two syncs.
				'CacheUpdateFrequency' => 3600
			],
			self::COMPONENT_PROXY => [
				'ProxyMode' => PROXY_OPERATING_MODE_ACTIVE,
				'DebugLevel' => 3,
				'LogFileSize' => 0,
				'Hostname' => self::PROXY1_NAME,
				'Server' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort')
			],
			self::COMPONENT_PROXY_HANODE1 => [
				'ProxyMode' => PROXY_OPERATING_MODE_ACTIVE,
				'DebugLevel' => 3,
				'LogFileSize' => 0,
				'Hostname' => self::PROXY2_NAME,
				'Server' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_SERVER, 'ListenPort')
			],
			self::COMPONENT_AGENT => [
				'Hostname' => self::HOSTNAME,
				// the host ends up on proxy 2, active checks are verified only there
				'ServerActive' => '127.0.0.1:'.self::getConfigurationValue(self::COMPONENT_PROXY_HANODE1,
						'ListenPort'),
				'RefreshActiveChecks' => 5,
				'HeartbeatFrequency' => 0
			]
		];
	}

	/**
	 * @inheritdoc
	 */
	public function prepareData() {
		$response = $this->call('proxygroup.create', [
			[
				'name' => self::PROXY_GROUP1_NAME,
				'failover_delay' => (string) self::FAILOVER_DELAY,
				'min_online' => '1'
			],
			[
				'name' => self::PROXY_GROUP2_NAME,
				'failover_delay' => (string) self::FAILOVER_DELAY,
				'min_online' => '1'
			]
		]);
		$this->assertArrayHasKey('proxy_groupids', $response['result']);
		$this->assertCount(2, $response['result']['proxy_groupids']);
		self::$proxy_groupid1 = $response['result']['proxy_groupids'][0];
		self::$proxy_groupid2 = $response['result']['proxy_groupids'][1];

		$response = $this->call('proxy.create', [
			'name' => self::PROXY1_NAME,
			'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
			'local_address' => '127.0.0.1',
			'local_port' => $this->getConfigurationValue(self::COMPONENT_PROXY, 'ListenPort'),
			'proxy_groupid' => self::$proxy_groupid1
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);
		self::$proxyid1 = $response['result']['proxyids'][0];

		$socket_dir = $this->getConfigurationValue(self::COMPONENT_PROXY_HANODE1, 'SocketDir');
		if (is_dir($socket_dir) === false) {
			mkdir($socket_dir);
		}

		$response = $this->call('proxy.create', [
			'name' => self::PROXY2_NAME,
			'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
			'local_address' => '127.0.0.1',
			'local_port' => $this->getConfigurationValue(self::COMPONENT_PROXY_HANODE1, 'ListenPort'),
			'proxy_groupid' => self::$proxy_groupid2
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);
		self::$proxyid2 = $response['result']['proxyids'][0];

		$response = $this->call('host.create', [
			'host' => self::HOSTNAME,
			'groups' => [['groupid' => 4]],
			'monitored_by' => ZBX_MONITORED_BY_PROXY_GROUP,
			'proxy_groupid' => self::$proxy_groupid1
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		self::$hostid = $response['result']['hostids'][0];

		$response = $this->call('item.create', [
			'name' => 'Agent ping',
			'key_' => self::ITEM_KEY,
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'delay' => '1s',
			'hostid' => self::$hostid
		]);
		$this->assertArrayHasKey('itemids', $response['result']);
		self::$itemid = $response['result']['itemids'][0];

		// Admin that may not monitor through proxy group 2 in general, but has write access to the host.
		// Item test of the host on proxy 2 is then allowed only if server resolves the host as assigned
		// to proxy 2 by its host_proxy link.
		$response = $this->call('usergroup.create', [
			'name' => 'Reassign item test group',
			'hostgroup_rights' => [
				['id' => 4, 'permission' => PERM_READ_WRITE]
			],
			'proxy_group_mode' => PROXY_GROUP_MODE_DENY,
			'proxy_groups' => [
				['proxy_groupid' => self::$proxy_groupid2]
			]
		]);
		$this->assertArrayHasKey('usrgrpids', $response['result']);
		self::$usrgrpid = $response['result']['usrgrpids'][0];

		$response = $this->call('role.create', [
			'name' => 'Reassign item test role',
			'type' => USER_TYPE_ZABBIX_ADMIN,
			'rules' => ['api.access' => ZBX_ROLE_RULE_ENABLED]
		]);
		$this->assertArrayHasKey('roleids', $response['result']);
		self::$roleid = $response['result']['roleids'][0];

		$response = $this->call('user.create', [
			'username' => self::USERNAME,
			'passwd' => self::PASSWORD,
			'roleid' => self::$roleid,
			'autologout' => '0',
			'usrgrps' => [
				['usrgrpid' => self::$usrgrpid]
			]
		]);
		$this->assertArrayHasKey('userids', $response['result']);
		self::$userid = $response['result']['userids'][0];

		// Log in without replacing the administrator session used by the rest of the test.
		$response = CAPIHelper::callRaw([
			'jsonrpc' => '2.0',
			'method' => 'user.login',
			'params' => ['username' => self::USERNAME, 'password' => self::PASSWORD],
			'id' => 1
		]);
		$this->assertArrayHasKey('result', $response, 'Failed to log in as "'.self::USERNAME.'": '.
				json_encode($response));
		self::$user_sid = $response['result'];

		return true;
	}

	public static function clearData(): void {
		if (CAPIHelper::getSessionId() === null) {
			CAPIHelper::authorize(PHPUNIT_LOGIN_NAME, PHPUNIT_LOGIN_PWD);
		}

		$response = CAPIHelper::call('host.get', [
			'output' => ['hostid'],
			'filter' => ['host' => [self::HOSTNAME, self::DIRECT_HOSTNAME, self::RENAME_HOSTNAME,
				self::RENAMED_HOSTNAME, self::DIRECT_OTHER_HOSTNAME
			]]
		]);
		$hostids = array_column($response['result'], 'hostid');
		if (count($hostids) > 0) {
			CAPIHelper::call('host.delete', $hostids);
		}
		self::$hostid = null;
		self::$direct_hostid = null;
		self::$rename_hostid = null;
		self::$direct_other_hostid = null;

		if (self::$userid !== null) {
			CAPIHelper::call('user.delete', [self::$userid]);
			self::$userid = null;
			self::$user_sid = null;
		}

		if (self::$usrgrpid !== null) {
			CAPIHelper::call('usergroup.delete', [self::$usrgrpid]);
			self::$usrgrpid = null;
		}

		if (self::$roleid !== null) {
			CAPIHelper::call('role.delete', [self::$roleid]);
			self::$roleid = null;
		}

		$proxyids = [];
		foreach ([self::$proxyid1, self::$proxyid2] as $proxyid) {
			if ($proxyid !== null) {
				$proxyids[] = $proxyid;
			}
		}
		if (count($proxyids) > 0) {
			CAPIHelper::call('proxy.delete', $proxyids);
		}
		self::$proxyid1 = null;
		self::$proxyid2 = null;

		$proxy_groupids = [];
		foreach ([self::$proxy_groupid1, self::$proxy_groupid2] as $proxy_groupid) {
			if ($proxy_groupid !== null) {
				$proxy_groupids[] = $proxy_groupid;
			}
		}
		if (count($proxy_groupids) > 0) {
			CAPIHelper::call('proxygroup.delete', $proxy_groupids);
		}
		self::$proxy_groupid1 = null;
		self::$proxy_groupid2 = null;
	}

	/**
	 * Move the host to an offline group so its host_proxy row is deleted, then bring the group back so a
	 * new host_proxy row is inserted, and sync the server configuration cache only after both changes.
	 *
	 * @configurationDataProvider configurationProvider
	 */
	public function testProxyGroupHostReassign_reassignAfterGroupRecovery() {
		// Make the server aware of the proxies, groups and the host.
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		$this->waitForAssignedProxy(self::$hostid, self::$proxyid1);

		// Load the host_proxy row assigning the host to proxy 1 into the server configuration cache.
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		// Take group 2 offline so that the host moved there cannot be assigned right away.
		$this->takeGroup2Offline();

		$response = $this->call('host.update', [
			'hostid' => self::$hostid,
			'monitored_by' => ZBX_MONITORED_BY_PROXY_GROUP,
			'proxy_groupid' => self::$proxy_groupid2
		]);
		$this->assertArrayHasKey('hostids', $response['result']);

		// Let proxy group manager know about the host relocation.
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		// The old host_proxy row is deleted, while the offline group cannot take the host yet.
		$this->waitForAssignedProxy(self::$hostid, 0);

		// Bring group 2 back - proxy group manager inserts a new host_proxy row for the host.
		$this->startComponent(self::COMPONENT_PROXY_HANODE1);

		$this->waitForAssignedProxy(self::$hostid, self::$proxyid2);

		// The same configuration sync gets both the new host_proxy row and the removal of the old one.
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		$this->assertFalse(self::isLogLinePresent(self::COMPONENT_SERVER, 'Crashing ...', false),
				'Server crashed after syncing host_proxy changes, see '.
				self::getLogPath(self::COMPONENT_SERVER));
	}

	/**
	 * Values collected by the new proxy must be accepted by the server. Server discards proxy values of
	 * hosts that it does not consider monitored by that proxy.
	 *
	 * @depends testProxyGroupHostReassign_reassignAfterGroupRecovery
	 */
	public function testProxyGroupHostReassign_activeChecksCollected() {
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY_HANODE1);

		$time_from = time();

		$response = $this->callUntilDataIsPresent('history.get', [
			'history' => ITEM_VALUE_TYPE_UINT64,
			'itemids' => [self::$itemid],
			'time_from' => $time_from
		], 60, 1);

		$this->assertGreaterThan(0, count($response['result']),
				'Expected "'.self::ITEM_KEY.'" values collected by proxy "'.self::PROXY2_NAME.'" to be accepted'.
				' by server');
	}

	/**
	 * Item test of the host on the new proxy by a user that is not allowed to monitor through its proxy
	 * group in general. Server must allow it because the host is assigned to that proxy - which is
	 * resolved through the host_proxy index of the configuration cache.
	 *
	 * @depends testProxyGroupHostReassign_reassignAfterGroupRecovery
	 */
	public function testProxyGroupHostReassign_itemTestOnAssignedProxy() {
		$client = $this->getClient(self::COMPONENT_SERVER);

		// Without a host the user must be denied - only the host assignment can allow the test.
		$result = $client->testItem($this->getItemTestData(null), self::$user_sid);
		$this->assertFalse($result, 'Item test on proxy "'.self::PROXY2_NAME.'" without host is expected to be'.
				' denied, got: '.json_encode($result));
		$this->assertStringContainsString('Proxy monitoring permission denied', $client->getError());

		$result = $client->testItem($this->getItemTestData(self::$hostid), self::$user_sid);
		$this->assertNotFalse($result, 'Item test of the host assigned to proxy "'.self::PROXY2_NAME.'" failed: '.
				$client->getError());
		$this->assertArrayHasKey('item', $result, 'Unexpected item test result: '.json_encode($result));
		$this->assertArrayNotHasKey('error', $result['item'], 'Item test returned error: '.
				json_encode($result['item']));
		$this->assertArrayHasKey('result', $result['item'], 'Unexpected item test result: '.
				json_encode($result));
		$this->assertEquals('1', $result['item']['result']);
	}

	/**
	 * Deleting the host must remove it from the new proxy, and the proxy configuration request must not
	 * crash the server.
	 *
	 * @depends testProxyGroupHostReassign_reassignAfterGroupRecovery
	 */
	public function testProxyGroupHostReassign_deleteHost() {
		self::stopComponent(self::COMPONENT_AGENT);

		$response = $this->call('host.delete', [self::$hostid]);
		$this->assertArrayHasKey('hostids', $response['result']);
		self::$hostid = null;

		// Update proxy 2 so it gets configuration updates even if the host deletion did not reach it,
		// making the server walk through the hosts registered for proxy 2.
		$response = $this->call('proxy.update', [
			'proxyid' => self::$proxyid2,
			'description' => 'updated after host deletion'
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY_HANODE1);

		$this->assertFalse(self::isLogLinePresent(self::COMPONENT_SERVER, 'Crashing ...', false),
				'Server crashed while sending configuration to proxy "'.self::PROXY2_NAME.'", see '.
				self::getLogPath(self::COMPONENT_SERVER));

		$this->waitForProxyActiveCheck(self::COMPONENT_PROXY_HANODE1, self::HOSTNAME, false,
				'Deleted host is still monitored by proxy "'.self::PROXY2_NAME.'"');
	}

	/**
	 * Switch a proxy group host to a directly assigned proxy and back to the group before the server syncs
	 * the removal of its old host_proxy row:
	 *   1) host is in group 1, assigned to proxy 1 (host_proxy row R1);
	 *   2) host is switched to be monitored directly by proxy 2 - server moves it from proxy 1 to proxy 2,
	 *      proxy group manager deletes R1;
	 *   3) host is switched back to group 1 - server keeps it on proxy 2 until the group assigns it;
	 *   4) server syncs the R1 removal - it must take the host off proxy 2 (where it is registered now),
	 *      not off proxy 1 (which R1 points to), otherwise proxy 2 keeps the host forever and is left with
	 *      a dangling pointer once the host is deleted.
	 *
	 * @depends testProxyGroupHostReassign_reassignAfterGroupRecovery
	 */
	public function testProxyGroupHostReassign_directProxyAndBack() {
		$response = $this->call('host.create', [
			'host' => self::DIRECT_HOSTNAME,
			'groups' => [['groupid' => 4]],
			'monitored_by' => ZBX_MONITORED_BY_PROXY_GROUP,
			'proxy_groupid' => self::$proxy_groupid1
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		self::$direct_hostid = $response['result']['hostids'][0];

		$response = $this->call('item.create', [
			'name' => 'Agent ping',
			'key_' => self::ITEM_KEY,
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'delay' => '1s',
			'hostid' => self::$direct_hostid
		]);
		$this->assertArrayHasKey('itemids', $response['result']);

		// 1) assign the host to proxy 1 and load the R1 host_proxy row into the configuration cache
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$row = $this->waitForHostProxyRow(self::$direct_hostid, function ($row) {
			return ($row !== false && bccomp($row['proxyid'], self::$proxyid1) == 0)
				? true
				: 'Host is expected to be assigned to proxy 1, host_proxy: '.json_encode($row);
		});
		$old_hostproxyid = $row['hostproxyid'];
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		// 2) monitor the host directly by proxy 2
		$response = $this->call('host.update', [
			'hostid' => self::$direct_hostid,
			'monitored_by' => ZBX_MONITORED_BY_PROXY,
			'proxyid' => self::$proxyid2
		]);
		$this->assertArrayHasKey('hostids', $response['result']);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		// Proxy group manager deletes R1, but this sync has not seen that yet. The host_proxy table is checked
		// directly, because assigned_proxyid of the API is always 0 for hosts that are not in a proxy group.
		$this->waitForHostProxyRow(self::$direct_hostid, function ($row) {
			return ($row === false) ? true : 'Host is expected to have no host_proxy row, got: '.json_encode($row);
		});

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY_HANODE1);
		$this->waitForProxyActiveCheck(self::COMPONENT_PROXY_HANODE1, self::DIRECT_HOSTNAME, true,
				'Host directly assigned to proxy "'.self::PROXY2_NAME.'" is not monitored by it');

		// 3) switch the host back to group 1
		$response = $this->call('host.update', [
			'hostid' => self::$direct_hostid,
			'monitored_by' => ZBX_MONITORED_BY_PROXY_GROUP,
			'proxy_groupid' => self::$proxy_groupid1
		]);
		$this->assertArrayHasKey('hostids', $response['result']);

		// 4) the same sync gets the host back in group 1 and the removal of R1
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		// Group 1 assigns the host again with a new host_proxy row, load it. The row id is checked, because
		// assigned_proxyid of the API would already report proxy 1 if R1 was still present.
		$this->waitForHostProxyRow(self::$direct_hostid, function ($row) use ($old_hostproxyid) {
			return ($row !== false && bccomp($row['hostproxyid'], $old_hostproxyid) != 0
					&& bccomp($row['proxyid'], self::$proxyid1) == 0)
				? true
				: 'Host is expected to be assigned to proxy 1 by a new host_proxy row, got: '.json_encode($row);
		});
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY);
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY_HANODE1);

		$this->waitForProxyActiveCheck(self::COMPONENT_PROXY, self::DIRECT_HOSTNAME, true,
				'Host assigned back to proxy "'.self::PROXY1_NAME.'" is not monitored by it');
		$this->waitForProxyActiveCheck(self::COMPONENT_PROXY_HANODE1, self::DIRECT_HOSTNAME, false,
				'Host moved back to group "'.self::PROXY_GROUP1_NAME.'" is still monitored by proxy "'.
				self::PROXY2_NAME.'"');

		// delete the host and make the server walk through the hosts registered for proxy 2
		$response = $this->call('host.delete', [self::$direct_hostid]);
		$this->assertArrayHasKey('hostids', $response['result']);
		self::$direct_hostid = null;

		$response = $this->call('proxy.update', [
			'proxyid' => self::$proxyid2,
			'description' => 'updated after direct host deletion'
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY_HANODE1);

		$this->assertFalse(self::isLogLinePresent(self::COMPONENT_SERVER, 'Crashing ...', false),
				'Server crashed while sending configuration to proxy "'.self::PROXY2_NAME.'", see '.
				self::getLogPath(self::COMPONENT_SERVER));
	}

	/**
	 * Rename a proxy group host and then remove its host_proxy row:
	 *   1) host is in group 1, assigned to proxy 1 (host_proxy row R1);
	 *   2) host is renamed - server moves the host_proxy index entry to the new name;
	 *   3) host is moved to offline group 2 - proxy group manager deletes R1;
	 *   4) server syncs the R1 removal alone - it must find R1 under the new name and take the host off
	 *      proxy 1, otherwise proxy 1 keeps the host and the index entry of the new name is left pointing
	 *      to the freed host_proxy row;
	 *   5) group 2 recovers and assigns the host to proxy 2 by a new host_proxy row, which must be found by
	 *      the new name.
	 *
	 * @depends testProxyGroupHostReassign_reassignAfterGroupRecovery
	 */
	public function testProxyGroupHostReassign_renameHost() {
		$response = $this->call('host.create', [
			'host' => self::RENAME_HOSTNAME,
			'groups' => [['groupid' => 4]],
			'monitored_by' => ZBX_MONITORED_BY_PROXY_GROUP,
			'proxy_groupid' => self::$proxy_groupid1
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		self::$rename_hostid = $response['result']['hostids'][0];

		$response = $this->call('item.create', [
			'name' => 'Agent ping',
			'key_' => self::ITEM_KEY,
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'delay' => '1s',
			'hostid' => self::$rename_hostid
		]);
		$this->assertArrayHasKey('itemids', $response['result']);

		// 1) assign the host to proxy 1 and load the R1 host_proxy row into the configuration cache
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$row = $this->waitForHostProxyRow(self::$rename_hostid, function ($row) {
			return ($row !== false && bccomp($row['proxyid'], self::$proxyid1) == 0)
				? true
				: 'Host is expected to be assigned to proxy 1, host_proxy: '.json_encode($row);
		});
		$old_hostproxyid = $row['hostproxyid'];
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		// 2) rename the host while R1 is still assigned
		$response = $this->call('host.update', [
			'hostid' => self::$rename_hostid,
			'host' => self::RENAMED_HOSTNAME
		]);
		$this->assertArrayHasKey('hostids', $response['result']);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY);
		$this->waitForProxyActiveCheck(self::COMPONENT_PROXY, self::RENAMED_HOSTNAME, true,
				'Renamed host is not monitored by proxy "'.self::PROXY1_NAME.'"');

		// 3) move the host to offline group 2, proxy group manager deletes R1
		$this->takeGroup2Offline();

		$response = $this->call('host.update', [
			'hostid' => self::$rename_hostid,
			'monitored_by' => ZBX_MONITORED_BY_PROXY_GROUP,
			'proxy_groupid' => self::$proxy_groupid2
		]);
		$this->assertArrayHasKey('hostids', $response['result']);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		$this->waitForHostProxyRow(self::$rename_hostid, function ($row) {
			return ($row === false) ? true : 'Host is expected to have no host_proxy row, got: '.json_encode($row);
		});

		// 4) sync the R1 removal alone, while group 2 cannot assign the host yet
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY);
		$this->waitForProxyActiveCheck(self::COMPONENT_PROXY, self::RENAMED_HOSTNAME, false,
				'Renamed host moved out of group "'.self::PROXY_GROUP1_NAME.'" is still monitored by proxy "'.
				self::PROXY1_NAME.'"');

		// 5) bring group 2 back and load the new host_proxy row
		$this->startComponent(self::COMPONENT_PROXY_HANODE1);

		$this->waitForHostProxyRow(self::$rename_hostid, function ($row) use ($old_hostproxyid) {
			return ($row !== false && bccomp($row['hostproxyid'], $old_hostproxyid) != 0
					&& bccomp($row['proxyid'], self::$proxyid2) == 0)
				? true
				: 'Host is expected to be assigned to proxy 2 by a new host_proxy row, got: '.json_encode($row);
		});
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY_HANODE1);
		$this->waitForProxyActiveCheck(self::COMPONENT_PROXY_HANODE1, self::RENAMED_HOSTNAME, true,
				'Renamed host assigned to proxy "'.self::PROXY2_NAME.'" is not monitored by it');

		// Item test permission is resolved through the host_proxy index by the new host name. The agent was
		// stopped by a previous test, it is needed only for the passive check value.
		$this->startComponent(self::COMPONENT_AGENT);

		$client = $this->getClient(self::COMPONENT_SERVER);
		$result = $client->testItem($this->getItemTestData(self::$rename_hostid), self::$user_sid);

		self::stopComponent(self::COMPONENT_AGENT);

		$this->assertNotFalse($result, 'Item test of the renamed host assigned to proxy "'.self::PROXY2_NAME.
				'" failed: '.$client->getError());
		$this->assertArrayHasKey('item', $result, 'Unexpected item test result: '.json_encode($result));
		$this->assertArrayNotHasKey('error', $result['item'], 'Item test returned error: '.
				json_encode($result['item']));
		$this->assertArrayHasKey('result', $result['item'], 'Unexpected item test result: '.
				json_encode($result));
		$this->assertEquals('1', $result['item']['result']);

		// delete the host and make the server walk through the hosts registered for both proxies
		$response = $this->call('host.delete', [self::$rename_hostid]);
		$this->assertArrayHasKey('hostids', $response['result']);
		self::$rename_hostid = null;

		$response = $this->call('proxy.update', [
			['proxyid' => self::$proxyid1, 'description' => 'updated after renamed host deletion'],
			['proxyid' => self::$proxyid2, 'description' => 'updated after renamed host deletion']
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY);
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY_HANODE1);

		$this->assertFalse(self::isLogLinePresent(self::COMPONENT_SERVER, 'Crashing ...', false),
				'Server crashed after deleting the renamed host, see '.self::getLogPath(self::COMPONENT_SERVER));

		$this->waitForProxyActiveCheck(self::COMPONENT_PROXY_HANODE1, self::RENAMED_HOSTNAME, false,
				'Deleted renamed host is still monitored by proxy "'.self::PROXY2_NAME.'"');
	}

	/**
	 * Switch a proxy group host to a directly assigned proxy and then to another proxy group before the server
	 * syncs the removal of its old host_proxy row:
	 *   1) host is in group 1, assigned to proxy 1 (host_proxy row R1);
	 *   2) host is switched to be monitored directly by proxy 2 - server moves it from proxy 1 to proxy 2,
	 *      proxy group manager deletes R1;
	 *   3) host is switched to group 2 - server keeps it on proxy 2 until the group assigns it;
	 *   4) the same sync gets the R1 removal - it must take the host off proxy 2 (where it is registered now),
	 *      not off proxy 1 (which R1 points to);
	 *   5) group 2 assigns the host to proxy 2 by a new host_proxy row R2 - if the host was left on proxy 2 in
	 *      step 4, registering it again makes a duplicate entry in the proxy 2 host list, and after the host
	 *      is deleted proxy 2 is left with a dangling pointer.
	 *
	 * @depends testProxyGroupHostReassign_reassignAfterGroupRecovery
	 */
	public function testProxyGroupHostReassign_directProxyToOtherGroup() {
		// group 2 must be able to take the host right away
		$this->callUntilDataIsPresent('proxygroup.get', [
			'output' => ['proxy_groupid', 'state'],
			'proxy_groupids' => [self::$proxy_groupid2]
		], 60, 1, function ($response) {
			return ($response['result'][0]['state'] == ZBX_PROXYGROUP_STATE_ONLINE)
				? true
				: 'Proxy group "'.self::PROXY_GROUP2_NAME.'" is not online, state: '.
					$response['result'][0]['state'];
		});

		$response = $this->call('host.create', [
			'host' => self::DIRECT_OTHER_HOSTNAME,
			'groups' => [['groupid' => 4]],
			'monitored_by' => ZBX_MONITORED_BY_PROXY_GROUP,
			'proxy_groupid' => self::$proxy_groupid1
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		self::$direct_other_hostid = $response['result']['hostids'][0];

		$response = $this->call('item.create', [
			'name' => 'Agent ping',
			'key_' => self::ITEM_KEY,
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'delay' => '1s',
			'hostid' => self::$direct_other_hostid
		]);
		$this->assertArrayHasKey('itemids', $response['result']);

		// 1) assign the host to proxy 1 and load the R1 host_proxy row into the configuration cache
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$row = $this->waitForHostProxyRow(self::$direct_other_hostid, function ($row) {
			return ($row !== false && bccomp($row['proxyid'], self::$proxyid1) == 0)
				? true
				: 'Host is expected to be assigned to proxy 1, host_proxy: '.json_encode($row);
		});
		$old_hostproxyid = $row['hostproxyid'];
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		// 2) monitor the host directly by proxy 2
		$response = $this->call('host.update', [
			'hostid' => self::$direct_other_hostid,
			'monitored_by' => ZBX_MONITORED_BY_PROXY,
			'proxyid' => self::$proxyid2
		]);
		$this->assertArrayHasKey('hostids', $response['result']);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		// Proxy group manager deletes R1. The server must not sync it yet, so the removal ends up in the same
		// sync as the move to group 2.
		$this->waitForHostProxyRow(self::$direct_other_hostid, function ($row) {
			return ($row === false) ? true : 'Host is expected to have no host_proxy row, got: '.json_encode($row);
		});

		// 3) switch the host to group 2
		$response = $this->call('host.update', [
			'hostid' => self::$direct_other_hostid,
			'monitored_by' => ZBX_MONITORED_BY_PROXY_GROUP,
			'proxy_groupid' => self::$proxy_groupid2
		]);
		$this->assertArrayHasKey('hostids', $response['result']);

		// 4) the same sync gets the host in group 2 and the removal of R1
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		// 5) group 2 assigns the host to proxy 2 with a new host_proxy row, load it
		$this->waitForHostProxyRow(self::$direct_other_hostid, function ($row) use ($old_hostproxyid) {
			return ($row !== false && bccomp($row['hostproxyid'], $old_hostproxyid) != 0
					&& bccomp($row['proxyid'], self::$proxyid2) == 0)
				? true
				: 'Host is expected to be assigned to proxy 2 by a new host_proxy row, got: '.json_encode($row);
		});
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY);
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY_HANODE1);

		$this->waitForProxyActiveCheck(self::COMPONENT_PROXY_HANODE1, self::DIRECT_OTHER_HOSTNAME, true,
				'Host moved to group "'.self::PROXY_GROUP2_NAME.'" is not monitored by proxy "'.self::PROXY2_NAME.'"');
		$this->waitForProxyActiveCheck(self::COMPONENT_PROXY, self::DIRECT_OTHER_HOSTNAME, false,
				'Host moved out of group "'.self::PROXY_GROUP1_NAME.'" is still monitored by proxy "'.
				self::PROXY1_NAME.'"');

		// delete the host and make the server walk through the hosts registered for proxy 2
		$response = $this->call('host.delete', [self::$direct_other_hostid]);
		$this->assertArrayHasKey('hostids', $response['result']);
		self::$direct_other_hostid = null;

		$response = $this->call('proxy.update', [
			'proxyid' => self::$proxyid2,
			'description' => 'updated after direct to other group host deletion'
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY_HANODE1);

		$this->assertFalse(self::isLogLinePresent(self::COMPONENT_SERVER, 'Crashing ...', false),
				'Server crashed while sending configuration to proxy "'.self::PROXY2_NAME.'", see '.
				self::getLogPath(self::COMPONENT_SERVER));

		$this->waitForProxyActiveCheck(self::COMPONENT_PROXY_HANODE1, self::DIRECT_OTHER_HOSTNAME, false,
				'Deleted host is still monitored by proxy "'.self::PROXY2_NAME.'"');
	}

	/**
	 * Stop proxy 2 and wait until it and group 2 (where it is the only proxy) are offline.
	 */
	private function takeGroup2Offline() {
		self::stopComponent(self::COMPONENT_PROXY_HANODE1);

		$this->callUntilDataIsPresent('proxy.get', [
			'output' => ['proxyid', 'state'],
			'proxyids' => [self::$proxyid2]
		], 60, 1, function ($response) {
			return ($response['result'][0]['state'] == ZBX_PROXY_STATE_OFFLINE)
				? true
				: 'Proxy "'.self::PROXY2_NAME.'" is not offline yet, state: '.$response['result'][0]['state'];
		});

		$this->callUntilDataIsPresent('proxygroup.get', [
			'output' => ['proxy_groupid', 'state'],
			'proxy_groupids' => [self::$proxy_groupid2]
		], 60, 1, function ($response) {
			return ($response['result'][0]['state'] == ZBX_PROXYGROUP_STATE_OFFLINE)
				? true
				: 'Proxy group "'.self::PROXY_GROUP2_NAME.'" is not offline yet, state: '.
					$response['result'][0]['state'];
		});
	}

	/**
	 * Wait until the host_proxy row of the host satisfies the condition.
	 *
	 * @param string   $hostid     host to check
	 * @param callable $condition  receives the row (false if there is none), returns true or error message
	 *
	 * @return array|false  the last host_proxy row
	 */
	private function waitForHostProxyRow($hostid, callable $condition) {
		$result = null;
		$row = false;

		for ($i = 0; $i < 90; $i++) {
			$row = CDBHelper::getRow('SELECT hostproxyid,proxyid FROM host_proxy WHERE hostid='.zbx_dbstr($hostid));
			$result = $condition($row);

			if ($result === true) {
				break;
			}

			sleep(1);
		}

		$this->assertTrue($result === true, (string) $result);

		return $row;
	}

	/**
	 * Wait until the proxy starts or stops serving active checks of the host.
	 *
	 * @param string $component  proxy component
	 * @param string $hostname   host to request active checks for
	 * @param bool   $expected   true to wait for the item to be served, false to wait for it to disappear
	 * @param string $message    failure message
	 */
	private function waitForProxyActiveCheck($component, $hostname, $expected, $message) {
		$keys = [];

		for ($i = 0; $i < 15; $i++) {
			$checks = $this->getClient($component)->getActiveChecks($hostname);
			$keys = is_array($checks) ? array_column($checks, 'key') : [];

			if (in_array(self::ITEM_KEY, $keys) === $expected) {
				break;
			}

			sleep(1);
		}

		$this->assertSame($expected, in_array(self::ITEM_KEY, $keys), $message.', active checks: '.
				json_encode($keys));
	}

	/**
	 * Get item test request data for a passive agent.ping check executed by proxy 2.
	 *
	 * @param string|null $hostid  host to test the item on, null to test without a host
	 *
	 * @return array
	 */
	private function getItemTestData($hostid) {
		$host = [
			'proxyid' => self::$proxyid2,
			'tls_connect' => HOST_ENCRYPTION_NONE,
			'interface' => [
				'address' => '127.0.0.1',
				'port' => (int) $this->getConfigurationValue(self::COMPONENT_AGENT, 'ListenPort'),
				'type' => INTERFACE_TYPE_UNKNOWN
			]
		];

		if ($hostid !== null) {
			$host['hostid'] = $hostid;
		}

		return [
			'item' => [
				'type' => ITEM_TYPE_ZABBIX,
				'key' => self::ITEM_KEY,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'timeout' => '3s'
			],
			'host' => $host
		];
	}

	/**
	 * Wait until the host is assigned to the specified proxy in the database.
	 *
	 * @param string     $hostid   host to check
	 * @param string|int $proxyid  expected assigned proxy, 0 to wait for the host to be unassigned
	 */
	private function waitForAssignedProxy($hostid, $proxyid) {
		$this->callUntilDataIsPresent('host.get', [
			'output' => ['hostid', 'assigned_proxyid'],
			'hostids' => [$hostid]
		], 90, 1, function ($response) use ($proxyid) {
			$assigned = $response['result'][0]['assigned_proxyid'];

			return ((string) $assigned === (string) $proxyid)
				? true
				: 'Host is expected to be assigned to proxy '.$proxyid.', got: '.$assigned;
		});
	}
}
