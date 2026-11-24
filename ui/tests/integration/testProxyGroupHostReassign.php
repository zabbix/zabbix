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
	const REDIRECT_HOSTNAME = 'pg_reassign_redirect';
	const REDIRECT_RENAMED_HOSTNAME = 'pg_reassign_redirect_renamed';
	const SWAP_HOSTNAME_W = 'pg_reassign_swap_w';
	const SWAP_HOSTNAME_X = 'pg_reassign_swap_x';
	const SWAP_HOSTNAME_Y = 'pg_reassign_swap_y';
	const EXCHANGE_HOSTNAME_W = 'pg_reassign_exchange_w';
	const EXCHANGE_HOSTNAME_X = 'pg_reassign_exchange_x';
	const EXCHANGE_HOSTNAME_Y = 'pg_reassign_exchange_y';
	const PENDING_HOSTNAME_1 = 'pg_reassign_pending_1';
	const PENDING_HOSTNAME_2 = 'pg_reassign_pending_2';
	const PENDING_HOSTNAME_TMP = 'pg_reassign_pending_tmp';
	const PENDING_DIRECT_HOSTNAME = 'pg_reassign_pending_direct';
	const MAPPING_HOSTNAME_1 = 'pg_reassign_mapping_1';
	const MAPPING_HOSTNAME_2 = 'pg_reassign_mapping_2';
	const MAPPING_HOSTNAME_TMP = 'pg_reassign_mapping_tmp';
	const FAILOVER_HOSTNAME = 'pg_reassign_failover';
	const REENTER_HOSTNAME = 'pg_reassign_reenter';
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
	private static $redirect_hostid;
	private static $swap_hostid_a;
	private static $swap_hostid_b;
	private static $failover_hostid;
	private static $reenter_hostid;
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
				self::RENAMED_HOSTNAME, self::DIRECT_OTHER_HOSTNAME, self::REDIRECT_HOSTNAME,
				self::REDIRECT_RENAMED_HOSTNAME, self::SWAP_HOSTNAME_W, self::SWAP_HOSTNAME_X,
				self::SWAP_HOSTNAME_Y, self::EXCHANGE_HOSTNAME_W, self::EXCHANGE_HOSTNAME_X,
				self::EXCHANGE_HOSTNAME_Y, self::PENDING_HOSTNAME_1, self::PENDING_HOSTNAME_2,
				self::PENDING_HOSTNAME_TMP, self::PENDING_DIRECT_HOSTNAME, self::MAPPING_HOSTNAME_1,
				self::MAPPING_HOSTNAME_2, self::MAPPING_HOSTNAME_TMP, self::FAILOVER_HOSTNAME,
				self::REENTER_HOSTNAME
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
		self::$redirect_hostid = null;
		self::$swap_hostid_a = null;
		self::$swap_hostid_b = null;
		self::$failover_hostid = null;
		self::$reenter_hostid = null;

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

		$this->waitForRedirect(self::COMPONENT_SERVER, self::HOSTNAME,
				self::getProxyAddress(self::COMPONENT_PROXY_HANODE1),
				'Server does not redirect the host to proxy "'.self::PROXY2_NAME.'"');

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

		$this->waitForProxyActiveCheck(self::COMPONENT_PROXY_HANODE1, self::HOSTNAME, false,
				'Deleted host is still monitored by proxy "'.self::PROXY2_NAME.'"');
		$this->waitForRedirect(self::COMPONENT_SERVER, self::HOSTNAME, null, 'Server still redirects the deleted host');
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

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY);
		$this->waitForProxyActiveCheck(self::COMPONENT_PROXY, self::DIRECT_HOSTNAME, true,
				'Host assigned to proxy "'.self::PROXY1_NAME.'" is not monitored by it');

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
		$this->waitForRedirect(self::COMPONENT_SERVER, self::DIRECT_HOSTNAME,
				self::getProxyAddress(self::COMPONENT_PROXY),
				'Server does not redirect the host assigned back to proxy "'.self::PROXY1_NAME.'"');

		// delete the host and make the server walk through the hosts registered for proxy 2
		$response = $this->call('host.delete', [self::$direct_hostid]);
		$this->assertArrayHasKey('hostids', $response['result']);
		self::$direct_hostid = null;

		$response = $this->call('proxy.update', [
			'proxyid' => self::$proxyid2,
			'description' => 'updated after direct host deletion'
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);

		// proxy 1 asks for configuration after proxy 2, so it gets the host deletion only if the server
		// sent configuration to proxy 2 without crashing
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY_HANODE1);
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY);

		$this->waitForProxyActiveCheck(self::COMPONENT_PROXY, self::DIRECT_HOSTNAME, false,
				'Deleted host is still monitored by proxy "'.self::PROXY1_NAME.'"');
		$this->waitForRedirect(self::COMPONENT_SERVER, self::DIRECT_HOSTNAME, null,
				'Server still redirects the deleted host');
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

		$this->waitForRedirect(self::COMPONENT_SERVER, self::RENAMED_HOSTNAME,
				self::getProxyAddress(self::COMPONENT_PROXY_HANODE1),
				'Server does not redirect the renamed host to proxy "'.self::PROXY2_NAME.'"');
		$this->waitForRedirect(self::COMPONENT_SERVER, self::RENAME_HOSTNAME, null,
				'Server redirects the old name of the renamed host');

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

		$this->waitForProxyActiveCheck(self::COMPONENT_PROXY_HANODE1, self::RENAMED_HOSTNAME, false,
				'Deleted renamed host is still monitored by proxy "'.self::PROXY2_NAME.'"');
		$this->waitForRedirect(self::COMPONENT_SERVER, self::RENAMED_HOSTNAME, null,
				'Server still redirects the deleted renamed host');
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

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY);
		$this->waitForProxyActiveCheck(self::COMPONENT_PROXY, self::DIRECT_OTHER_HOSTNAME, true,
				'Host assigned to proxy "'.self::PROXY1_NAME.'" is not monitored by it');

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
		$this->waitForRedirect(self::COMPONENT_SERVER, self::DIRECT_OTHER_HOSTNAME,
				self::getProxyAddress(self::COMPONENT_PROXY_HANODE1),
				'Server does not redirect the host moved to group "'.self::PROXY_GROUP2_NAME.'" to proxy "'.
				self::PROXY2_NAME.'"');

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

		$this->waitForProxyActiveCheck(self::COMPONENT_PROXY_HANODE1, self::DIRECT_OTHER_HOSTNAME, false,
				'Deleted host is still monitored by proxy "'.self::PROXY2_NAME.'"');
		$this->waitForRedirect(self::COMPONENT_SERVER, self::DIRECT_OTHER_HOSTNAME, null,
				'Server still redirects the deleted host');
	}

	/**
	 * Renaming a proxy group host must reach the server and the proxies of the group:
	 *   1) both proxies are put to group 1, the host is assigned to one of them (owner) - the other proxy gets
	 *      the host_proxy row by the proxy group configuration and redirects agents of the host to the owner;
	 *   2) host is renamed - server moves its host_proxy index entry to the new name, and proxy group manager
	 *      re-assigns the host to the same proxy, which bumps the host_proxy row revision;
	 *   3) the other proxy gets the updated row and redirects by the new name, server redirects by the new name
	 *      and no longer knows the old one;
	 *   4) host is removed from the proxy group - proxy group manager deletes its host_proxy row;
	 *   5) neither the server nor the other proxy may redirect the host anymore, by the new or the old name.
	 *
	 * @depends testProxyGroupHostReassign_reassignAfterGroupRecovery
	 */
	public function testProxyGroupHostReassign_renameHostRedirect() {
		// 1) put proxy 2 to group 1, so a proxy that does not monitor the host holds its host_proxy row
		$response = $this->call('proxy.update', [
			'proxyid' => self::$proxyid2,
			'proxy_groupid' => self::$proxy_groupid1
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);

		$response = $this->call('host.create', [
			'host' => self::REDIRECT_HOSTNAME,
			'groups' => [['groupid' => 4]],
			'monitored_by' => ZBX_MONITORED_BY_PROXY_GROUP,
			'proxy_groupid' => self::$proxy_groupid1
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		self::$redirect_hostid = $response['result']['hostids'][0];

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		$row = $this->waitForHostProxyRow(self::$redirect_hostid, function ($row) {
			return ($row !== false) ? true : 'Host is expected to be assigned to a proxy of group 1';
		});

		if (bccomp($row['proxyid'], self::$proxyid1) == 0) {
			$owner = self::COMPONENT_PROXY;
			$other = self::COMPONENT_PROXY_HANODE1;
		}
		else {
			$owner = self::COMPONENT_PROXY_HANODE1;
			$other = self::COMPONENT_PROXY;
		}
		$owner_address = self::getProxyAddress($owner);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->reloadConfigurationCacheAndWaitForLogLine($other);

		$this->waitForRedirect(self::COMPONENT_SERVER, self::REDIRECT_HOSTNAME, $owner_address,
				'Server does not redirect the host to its proxy');
		$this->waitForRedirect($other, self::REDIRECT_HOSTNAME, $owner_address,
				'Proxy that does not monitor the host does not redirect it to the owner proxy');

		// 2) rename the host, proxy group manager must bump the revision of the same host_proxy row
		$response = $this->call('host.update', [
			'hostid' => self::$redirect_hostid,
			'host' => self::REDIRECT_RENAMED_HOSTNAME
		]);
		$this->assertArrayHasKey('hostids', $response['result']);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		$this->waitForHostProxyRow(self::$redirect_hostid, function ($new_row) use ($row) {
			return ($new_row !== false && bccomp($new_row['hostproxyid'], $row['hostproxyid']) == 0
					&& bccomp($new_row['proxyid'], $row['proxyid']) == 0
					&& bccomp($new_row['revision'], $row['revision']) > 0)
				? true
				: 'Host rename is expected to bump the revision of host_proxy row '.json_encode($row).
					', got: '.json_encode($new_row);
		});

		// 3) load the updated row on the server and on the other proxy
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->reloadConfigurationCacheAndWaitForLogLine($other);

		$this->waitForRedirect(self::COMPONENT_SERVER, self::REDIRECT_RENAMED_HOSTNAME, $owner_address,
				'Server does not redirect the renamed host to its proxy');
		$this->waitForRedirect($other, self::REDIRECT_RENAMED_HOSTNAME, $owner_address,
				'Proxy that does not monitor the host does not redirect it by the new name');
		$this->waitForRedirect(self::COMPONENT_SERVER, self::REDIRECT_HOSTNAME, null,
				'Server still redirects the old host name');
		$this->waitForRedirect($other, self::REDIRECT_HOSTNAME, null,
				'Proxy still redirects the old host name');

		// 4) remove the host from the proxy group, proxy group manager deletes its host_proxy row
		$response = $this->call('host.update', [
			'hostid' => self::$redirect_hostid,
			'monitored_by' => ZBX_MONITORED_BY_SERVER
		]);
		$this->assertArrayHasKey('hostids', $response['result']);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		$this->waitForHostProxyRow(self::$redirect_hostid, function ($row) {
			return ($row === false) ? true : 'Host is expected to have no host_proxy row, got: '.json_encode($row);
		});

		// 5) load the row removal on the server and on the other proxy - nobody may redirect the host anymore
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->reloadConfigurationCacheAndWaitForLogLine($other);

		$this->waitForRedirect(self::COMPONENT_SERVER, self::REDIRECT_RENAMED_HOSTNAME, null,
				'Server still redirects the host removed from the proxy group');
		$this->waitForRedirect($other, self::REDIRECT_RENAMED_HOSTNAME, null,
				'Proxy still redirects the host removed from the proxy group');
		// the proxy got the new name with the updated host_proxy row, the entry of the old name must not be left
		// pointing to the removed row
		$this->waitForRedirect($other, self::REDIRECT_HOSTNAME, null,
				'Proxy still redirects the old name of the host removed from the proxy group');

		// restore the proxy groups
		$response = $this->call('host.delete', [self::$redirect_hostid]);
		$this->assertArrayHasKey('hostids', $response['result']);
		self::$redirect_hostid = null;

		$response = $this->call('proxy.update', [
			'proxyid' => self::$proxyid2,
			'proxy_groupid' => self::$proxy_groupid2
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY);
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY_HANODE1);
	}

	/**
	 * Rename two proxy group hosts so that one takes the old name of the other within the same sync:
	 *   host A: X -> Y
	 *   host B: W -> X
	 * When B is processed first, its new name X is still held by the host_proxy index entry of A. Taking over
	 * the name must not be lost, and A moving away from X afterwards must not remove (or take) the entry that
	 * now belongs to B. Checked on the server right after the renames are synced, and on the server and the
	 * proxies after the host_proxy rows with the new names are synced.
	 *
	 * @depends testProxyGroupHostReassign_reassignAfterGroupRecovery
	 */
	public function testProxyGroupHostReassign_swapHostNamesRedirect() {
		$this->checkHostRenamesRedirect(['a' => self::SWAP_HOSTNAME_X, 'b' => self::SWAP_HOSTNAME_W], [
			['a', self::SWAP_HOSTNAME_Y],
			['b', self::SWAP_HOSTNAME_X]
		]);
	}

	/**
	 * Rename two proxy group hosts so that they exchange names through an intermediate name within the same sync:
	 *   host A: X -> Y
	 *   host B: W -> X
	 *   host A: Y -> W
	 * The sync sees A: X -> W and B: W -> X, so whichever host is processed first takes the name still held by
	 * the host_proxy index entry of the other one, and the other one then moves to the name just released. The
	 * intermediate name Y never reaches the server and must not be redirected by anyone.
	 *
	 * @depends testProxyGroupHostReassign_reassignAfterGroupRecovery
	 */
	public function testProxyGroupHostReassign_exchangeHostNamesRedirect() {
		$this->checkHostRenamesRedirect(['a' => self::EXCHANGE_HOSTNAME_X, 'b' => self::EXCHANGE_HOSTNAME_W], [
			['a', self::EXCHANGE_HOSTNAME_Y],
			['b', self::EXCHANGE_HOSTNAME_X],
			['a', self::EXCHANGE_HOSTNAME_W]
		]);
	}

	/**
	 * Swapping host names must not make a proxy redirect its own host to the other proxy when the proxy gets the
	 * host-to-proxy mapping with the new names before the rename of its own host.
	 *
	 * Host A (named "1") is monitored by proxy PA, host B (named "2") by proxy PB, both proxies are online in group 1.
	 * The server sends a change of a proxy group host to its proxy only after the server syncs the re-sent mapping row
	 * of the host, while the mapping rows are sent to proxies as soon as proxy group manager updates them:
	 *   1) swap the host names: A "1" -> "tmp", B "2" -> "1", A "tmp" -> "2", and sync the server - proxy group manager
	 *      re-sends the mapping rows of A and B;
	 *   2) sync PA only - PA gets the mapping rows with the new names ("2" is A, "1" is B), but not the rename of its
	 *      own host A, which PA still knows as "1";
	 *   3) sync the server, then PA - PA gets the rename of A from "1" to "2", without mapping rows. PA must not rename
	 *      the mapping found under name "1", which belongs to B. Otherwise PA redirects its own host A, under the new
	 *      name "2", to PB, and does not redirect B anymore, until the mapping of B changes again.
	 *
	 * @depends testProxyGroupHostReassign_reassignAfterGroupRecovery
	 */
	public function testProxyGroupHostReassign_mappingBeforeRenameRedirect() {
		$components = [self::COMPONENT_SERVER, self::COMPONENT_PROXY, self::COMPONENT_PROXY_HANODE1];

		// both proxies in group 1, one host on each
		$response = $this->call('proxy.update', [
			'proxyid' => self::$proxyid2,
			'proxy_groupid' => self::$proxy_groupid1
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);

		$hostids = [
			'a' => $this->createGroup1Host(self::MAPPING_HOSTNAME_1),
			'b' => $this->createGroup1Host(self::MAPPING_HOSTNAME_2)
		];

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		$assigned = function ($row) {
			return ($row !== false) ? true : 'Host is expected to be assigned to a proxy of group 1';
		};
		$rows = [];
		$proxies = [];
		foreach ($hostids as $host => $hostid) {
			$rows[$host] = $this->waitForHostProxyRow($hostid, $assigned);
			$proxies[$host] = $this->getProxyComponent($rows[$host]['proxyid']);
		}

		$this->assertNotSame($proxies['a'], $proxies['b'], 'Hosts are expected to be assigned to different proxies, '.
				'host_proxy rows: '.json_encode($rows));

		$proxy_a = $proxies['a'];
		$address_b = self::getProxyAddress($proxies['b']);

		foreach ($components as $component) {
			$this->reloadConfigurationCacheAndWaitForLogLine($component);
		}

		$this->checkHostRenamesMonitoring(['a' => self::MAPPING_HOSTNAME_1, 'b' => self::MAPPING_HOSTNAME_2], $proxies,
				'before the renames');

		// 1) swap the host names and sync the server, proxy group manager re-sends both mapping rows
		foreach ([
			[$hostids['a'], self::MAPPING_HOSTNAME_TMP],
			[$hostids['b'], self::MAPPING_HOSTNAME_1],
			[$hostids['a'], self::MAPPING_HOSTNAME_2]
		] as [$hostid, $hostname]) {
			$response = $this->call('host.update', [
				'hostid' => $hostid,
				'host' => $hostname
			]);
			$this->assertArrayHasKey('hostids', $response['result']);
		}

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		foreach ($hostids as $host => $hostid) {
			$row = $rows[$host];

			$this->waitForHostProxyRow($hostid, function ($new_row) use ($row) {
				return ($new_row !== false && bccomp($new_row['hostproxyid'], $row['hostproxyid']) == 0
						&& bccomp($new_row['proxyid'], $row['proxyid']) == 0
						&& bccomp($new_row['revision'], $row['revision']) > 0)
					? true
					: 'Host rename is expected to bump the revision of host_proxy row '.json_encode($row).
						', got: '.json_encode($new_row);
			});
		}

		// 2) sync PA only - it gets the mapping rows with the new names, but not the rename of its own host A
		$this->reloadConfigurationCacheAndWaitForLogLine($proxy_a);

		$this->waitForRedirect($proxy_a, self::MAPPING_HOSTNAME_1, $address_b, 'Proxy "'.$proxy_a.
				'" did not get the mapping of host B under its new name "'.self::MAPPING_HOSTNAME_1.'"');
		$this->waitForProxyActiveCheck($proxy_a, self::MAPPING_HOSTNAME_2, false, 'Proxy "'.$proxy_a.
				'" got the rename of its own host A together with the mapping rows, the test does not reproduce the '.
				'scenario');

		// 3) sync the server, then PA - PA gets the rename of A, the mapping rows were already sent
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->reloadConfigurationCacheAndWaitForLogLine($proxy_a);

		$this->waitForRedirect($proxy_a, self::MAPPING_HOSTNAME_2, null, 'Proxy "'.$proxy_a.
				'" redirects its own host A, renamed to "'.self::MAPPING_HOSTNAME_2.'" - renaming A took the '.
				'mapping of host B');
		$this->waitForProxyActiveCheck($proxy_a, self::MAPPING_HOSTNAME_2, true, 'Proxy "'.$proxy_a.
				'" does not monitor its own host A, renamed to "'.self::MAPPING_HOSTNAME_2.'"');
		$this->waitForRedirect($proxy_a, self::MAPPING_HOSTNAME_1, $address_b, 'Proxy "'.$proxy_a.
				'" does not redirect host B, renamed to "'.self::MAPPING_HOSTNAME_1.'", to its proxy');

		// all components agree after another sync
		foreach ($components as $component) {
			$this->reloadConfigurationCacheAndWaitForLogLine($component);
		}

		$this->checkHostRenamesMonitoring(['a' => self::MAPPING_HOSTNAME_2, 'b' => self::MAPPING_HOSTNAME_1], $proxies,
				'after the renames');

		foreach ($components as $component) {
			$this->waitForRedirect($component, self::MAPPING_HOSTNAME_TMP, null, 'Component "'.$component.
					'" redirects temporary name "'.self::MAPPING_HOSTNAME_TMP.'"');
		}

		// delete the hosts, the server and the proxies must stop redirecting them
		$response = $this->call('host.delete', array_values($hostids));
		$this->assertArrayHasKey('hostids', $response['result']);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		foreach ([self::MAPPING_HOSTNAME_1, self::MAPPING_HOSTNAME_2] as $hostname) {
			foreach ($components as $component) {
				$this->waitForRedirect($component, $hostname, null, 'Component "'.$component.
						'" still redirects deleted host "'.$hostname.'"', $component !== self::COMPONENT_SERVER);
			}
		}

		// restore the proxy groups
		$response = $this->call('proxy.update', [
			'proxyid' => self::$proxyid2,
			'proxy_groupid' => self::$proxy_groupid2
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);

		foreach ($components as $component) {
			$this->reloadConfigurationCacheAndWaitForLogLine($component);
		}
	}

	/**
	 * Swapping host names must not make a proxy redirect its own host to the other proxy.
	 *
	 * Host A (named "1") is monitored by proxy PA, host B (named "2") by proxy PB, both proxies are online in group 1.
	 * A proxy learns about hosts from two sources:
	 *   - its own hosts come from the server configuration cache, so a rename reaches the proxy only after the server
	 *     syncs;
	 *   - the host-to-proxy mapping of the group (host_proxy rows) is read straight from the database, so a mapping
	 *     row carries the newest host name, even before the server syncs it.
	 * A change of a proxy group host alone does not make the server send it to its proxy. The proxy gets the change
	 * once the server syncs the re-sent mapping row of the host, so normally the rename and the mapping with the new
	 * name arrive together. Any other change for the same proxy sends the renamed host right away, without its
	 * mapping - here host D, monitored by PA directly, is changed for that.
	 * The test makes PA get the mapping of B under its new name before PA gets the rename of its own host A:
	 *   1) change B (visible name) and sync the server - proxy group manager marks the mapping of B to be re-sent;
	 *   2) swap the host names and change D, without syncing the server: A "1" -> "tmp", B "2" -> "1", A "tmp" -> "2";
	 *   3) sync PA only - PA still knows its own host A as "1", but gets the mapping of B already named "1", so PA
	 *      redirects name "1" to PB;
	 *   4) sync the server, then PA - the change of D makes PA get the rename of A from "1" to "2". PA must rename the
	 *      mapping of A only, not the mapping now found under name "1", which belongs to B. Otherwise PA redirects its
	 *      own host A, under the new name "2", to PB.
	 * About a second after the server sync in step 4, proxy group manager re-sends both renamed mappings. If PA gets
	 * them in the same sync as the rename of A, they repair a wrong mapping right away. PA is synced right after the
	 * server, so without the fix the test fails in most runs, but not in all of them.
	 *
	 * @depends testProxyGroupHostReassign_reassignAfterGroupRecovery
	 */
	public function testProxyGroupHostReassign_renameWithPendingHostmapRedirect() {
		$components = [self::COMPONENT_SERVER, self::COMPONENT_PROXY, self::COMPONENT_PROXY_HANODE1];

		// both proxies in group 1, one host on each
		$response = $this->call('proxy.update', [
			'proxyid' => self::$proxyid2,
			'proxy_groupid' => self::$proxy_groupid1
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);

		$hostids = [
			'a' => $this->createGroup1Host(self::PENDING_HOSTNAME_1),
			'b' => $this->createGroup1Host(self::PENDING_HOSTNAME_2)
		];

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		$assigned = function ($row) {
			return ($row !== false) ? true : 'Host is expected to be assigned to a proxy of group 1';
		};
		$rows = [];
		$proxies = [];
		foreach ($hostids as $host => $hostid) {
			$rows[$host] = $this->waitForHostProxyRow($hostid, $assigned);
			$proxies[$host] = $this->getProxyComponent($rows[$host]['proxyid']);
		}

		$this->assertNotSame($proxies['a'], $proxies['b'], 'Hosts are expected to be assigned to different proxies, '.
				'host_proxy rows: '.json_encode($rows));

		$proxy_a = $proxies['a'];
		$address_b = self::getProxyAddress($proxies['b']);

		// host D is monitored by PA directly, its change makes the server send changed hosts to PA
		$response = $this->call('host.create', [
			'host' => self::PENDING_DIRECT_HOSTNAME,
			'groups' => [['groupid' => 4]],
			'monitored_by' => ZBX_MONITORED_BY_PROXY,
			'proxyid' => $this->getProxyId($proxy_a)
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$direct_hostid = $response['result']['hostids'][0];

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		foreach ($components as $component) {
			$this->reloadConfigurationCacheAndWaitForLogLine($component);
		}

		$this->checkHostRenamesMonitoring(['a' => self::PENDING_HOSTNAME_1, 'b' => self::PENDING_HOSTNAME_2], $proxies,
				'before the renames');

		// 1) change B and sync the server, proxy group manager bumps the revision of the mapping of B
		$response = $this->call('host.update', [
			'hostid' => $hostids['b'],
			'name' => self::PENDING_HOSTNAME_2.' visible'
		]);
		$this->assertArrayHasKey('hostids', $response['result']);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		$bumped = function ($row) {
			return function ($new_row) use ($row) {
				return ($new_row !== false && bccomp($new_row['hostproxyid'], $row['hostproxyid']) == 0
						&& bccomp($new_row['proxyid'], $row['proxyid']) == 0
						&& bccomp($new_row['revision'], $row['revision']) > 0)
					? true
					: 'Host change is expected to bump the revision of host_proxy row '.json_encode($row).
						', got: '.json_encode($new_row);
			};
		};
		$rows['b'] = $this->waitForHostProxyRow($hostids['b'], $bumped($rows['b']));

		// 2) swap the host names and change D, the server does not sync
		foreach ([
			[$hostids['a'], self::PENDING_HOSTNAME_TMP],
			[$hostids['b'], self::PENDING_HOSTNAME_1],
			[$hostids['a'], self::PENDING_HOSTNAME_2]
		] as [$hostid, $hostname]) {
			$response = $this->call('host.update', [
				'hostid' => $hostid,
				'host' => $hostname
			]);
			$this->assertArrayHasKey('hostids', $response['result']);
		}

		$response = $this->call('host.update', [
			'hostid' => $direct_hostid,
			'name' => self::PENDING_DIRECT_HOSTNAME.' visible'
		]);
		$this->assertArrayHasKey('hostids', $response['result']);

		// 3) sync PA only - it gets the mapping of B already named "1", while it still knows its own host A as "1"
		$this->reloadConfigurationCacheAndWaitForLogLine($proxy_a);

		$this->waitForRedirect($proxy_a, self::PENDING_HOSTNAME_1, $address_b, 'Proxy "'.$proxy_a.
				'" did not get the mapping of host B under its new name before the server synced the renames, '.
				'the test does not reproduce the scenario');

		// 4) sync the server, then PA right away, before proxy group manager re-sends the renamed mappings - the change
		// of D makes PA get the rename of A
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->reloadConfigurationCacheAndWaitForLogLine($proxy_a);

		$this->waitForRedirect($proxy_a, self::PENDING_HOSTNAME_2, null, 'Proxy "'.$proxy_a.
				'" redirects its own host A, renamed to "'.self::PENDING_HOSTNAME_2.'" - renaming A took the '.
				'mapping of host B');
		$this->waitForProxyActiveCheck($proxy_a, self::PENDING_HOSTNAME_2, true, 'Proxy "'.$proxy_a.
				'" does not monitor its own host A, renamed to "'.self::PENDING_HOSTNAME_2.'"');
		$this->waitForRedirect($proxy_a, self::PENDING_HOSTNAME_1, $address_b, 'Proxy "'.$proxy_a.
				'" does not redirect host B, renamed to "'.self::PENDING_HOSTNAME_1.'", to its proxy');

		// proxy group manager bumps the revision of both renamed mappings
		foreach ($hostids as $host => $hostid) {
			$this->waitForHostProxyRow($hostid, $bumped($rows[$host]));
		}

		foreach ($components as $component) {
			$this->reloadConfigurationCacheAndWaitForLogLine($component);
		}

		$this->checkHostRenamesMonitoring(['a' => self::PENDING_HOSTNAME_2, 'b' => self::PENDING_HOSTNAME_1], $proxies,
				'after the renames');

		foreach ($components as $component) {
			$this->waitForRedirect($component, self::PENDING_HOSTNAME_TMP, null, 'Component "'.$component.
					'" redirects temporary name "'.self::PENDING_HOSTNAME_TMP.'"');
		}

		// delete the hosts, the server and the proxies must stop redirecting them
		$response = $this->call('host.delete', array_merge(array_values($hostids), [$direct_hostid]));
		$this->assertArrayHasKey('hostids', $response['result']);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		foreach ([self::PENDING_HOSTNAME_1, self::PENDING_HOSTNAME_2] as $hostname) {
			foreach ($components as $component) {
				$this->waitForRedirect($component, $hostname, null, 'Component "'.$component.
						'" still redirects deleted host "'.$hostname.'"', $component !== self::COMPONENT_SERVER);
			}
		}

		// restore the proxy groups
		$response = $this->call('proxy.update', [
			'proxyid' => self::$proxyid2,
			'proxy_groupid' => self::$proxy_groupid2
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);

		foreach ($components as $component) {
			$this->reloadConfigurationCacheAndWaitForLogLine($component);
		}
	}

	/**
	 * Host must fail over between proxies of a group and back, and the proxy that lost the host must redirect it:
	 *   1) host of group 1 is assigned to proxy P;
	 *   2) P is stopped, proxy group manager fails the host over to proxy Q in the database, while the server
	 *      configuration cache still lists the host for P;
	 *   3) P is started and gets the host_proxy row pointing to Q, while the host is still in its hosts table;
	 *   4) server syncs, so P gets the host removed and Q gets the host;
	 *   5) Q monitors the host, P redirects it to Q;
	 *   6) the same with the roles swapped - Q is stopped, the host fails back over to P, P must get the host
	 *      again and Q must redirect it to P.
	 *
	 * @depends testProxyGroupHostReassign_reassignAfterGroupRecovery
	 */
	public function testProxyGroupHostReassign_failoverRedirect() {
		// 1) both proxies in group 1, the host is assigned to one of them
		$response = $this->call('proxy.update', [
			'proxyid' => self::$proxyid2,
			'proxy_groupid' => self::$proxy_groupid1
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);

		$response = $this->call('host.create', [
			'host' => self::FAILOVER_HOSTNAME,
			'groups' => [['groupid' => 4]],
			'monitored_by' => ZBX_MONITORED_BY_PROXY_GROUP,
			'proxy_groupid' => self::$proxy_groupid1
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		self::$failover_hostid = $response['result']['hostids'][0];

		$response = $this->call('item.create', [
			'name' => 'Agent ping',
			'key_' => self::ITEM_KEY,
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'delay' => '1s',
			'hostid' => self::$failover_hostid
		]);
		$this->assertArrayHasKey('itemids', $response['result']);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		$row = $this->waitForHostProxyRow(self::$failover_hostid, function ($row) {
			return ($row !== false) ? true : 'Host is expected to be assigned to a proxy of group 1';
		});

		$owner = $this->getProxyComponent($row['proxyid']);
		$other = ($owner === self::COMPONENT_PROXY) ? self::COMPONENT_PROXY_HANODE1 : self::COMPONENT_PROXY;

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->reloadConfigurationCacheAndWaitForLogLine($owner);
		$this->reloadConfigurationCacheAndWaitForLogLine($other);

		$this->waitForProxyActiveCheck($owner, self::FAILOVER_HOSTNAME, true,
				'Host is not monitored by the proxy it is assigned to');
		$this->waitForRedirect($other, self::FAILOVER_HOSTNAME, self::getProxyAddress($owner),
				'Proxy that does not monitor the host does not redirect it to the owner proxy');

		// 2) - 5) fail the host over to the other proxy, 6) and back to the owner
		$this->failOverHost($owner, $other);
		$this->failOverHost($other, $owner);

		// restore the proxy groups
		$response = $this->call('host.delete', [self::$failover_hostid]);
		$this->assertArrayHasKey('hostids', $response['result']);
		self::$failover_hostid = null;

		$response = $this->call('proxy.update', [
			'proxyid' => self::$proxyid2,
			'proxy_groupid' => self::$proxy_groupid2
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY);
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY_HANODE1);
	}

	/**
	 * Proxy that does not monitor a host must keep redirecting it when the host leaves the proxy group and enters
	 * it again, and the proxy gets the removal of the old host_proxy row and the new row in the same sync:
	 *   1) both proxies are in group 1, the host is assigned to proxy P by host_proxy row R1, the other proxy Q
	 *      redirects it to P;
	 *   2) Q is stopped, so it cannot get the following changes one by one;
	 *   3) host is removed from group 1 - proxy group manager deletes R1;
	 *   4) host is put back to group 1 - proxy group manager assigns it to P (the only online proxy of the group)
	 *      by a new host_proxy row R2;
	 *   5) Q is started and syncs R2 and the removal of R1 together - R2 takes over the host name in the
	 *      host_proxy index first, so the R1 removal must leave the index entry alone;
	 *   6) Q must redirect the host to P.
	 *
	 * @depends testProxyGroupHostReassign_reassignAfterGroupRecovery
	 */
	public function testProxyGroupHostReassign_reenterGroupRedirect() {
		// 1) both proxies in group 1, the host is assigned to one of them
		$response = $this->call('proxy.update', [
			'proxyid' => self::$proxyid2,
			'proxy_groupid' => self::$proxy_groupid1
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);

		$response = $this->call('host.create', [
			'host' => self::REENTER_HOSTNAME,
			'groups' => [['groupid' => 4]],
			'monitored_by' => ZBX_MONITORED_BY_PROXY_GROUP,
			'proxy_groupid' => self::$proxy_groupid1
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		self::$reenter_hostid = $response['result']['hostids'][0];

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		$row = $this->waitForHostProxyRow(self::$reenter_hostid, function ($row) {
			return ($row !== false) ? true : 'Host is expected to be assigned to a proxy of group 1';
		});
		$old_hostproxyid = $row['hostproxyid'];

		$owner = $this->getProxyComponent($row['proxyid']);
		$other = ($owner === self::COMPONENT_PROXY) ? self::COMPONENT_PROXY_HANODE1 : self::COMPONENT_PROXY;
		$owner_address = self::getProxyAddress($owner);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->reloadConfigurationCacheAndWaitForLogLine($owner);
		$this->reloadConfigurationCacheAndWaitForLogLine($other);

		$this->waitForRedirect($other, self::REENTER_HOSTNAME, $owner_address,
				'Proxy that does not monitor the host does not redirect it to the owner proxy');

		// 2) stop the other proxy and wait until proxy group manager sees it offline, so the host cannot be
		// assigned to it when it enters the group again
		self::stopComponent($other);
		$this->waitForProxyOffline($this->getProxyId($other));

		// 3) remove the host from group 1, proxy group manager deletes R1
		$response = $this->call('host.update', [
			'hostid' => self::$reenter_hostid,
			'monitored_by' => ZBX_MONITORED_BY_SERVER
		]);
		$this->assertArrayHasKey('hostids', $response['result']);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		$this->waitForHostProxyRow(self::$reenter_hostid, function ($row) {
			return ($row === false) ? true : 'Host is expected to have no host_proxy row, got: '.json_encode($row);
		});

		// 4) put the host back to group 1, proxy group manager assigns it to the owner by R2
		$response = $this->call('host.update', [
			'hostid' => self::$reenter_hostid,
			'monitored_by' => ZBX_MONITORED_BY_PROXY_GROUP,
			'proxy_groupid' => self::$proxy_groupid1
		]);
		$this->assertArrayHasKey('hostids', $response['result']);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		$owner_proxyid = $this->getProxyId($owner);
		$this->waitForHostProxyRow(self::$reenter_hostid, function ($row) use ($old_hostproxyid, $owner_proxyid) {
			return ($row !== false && bccomp($row['hostproxyid'], $old_hostproxyid) != 0
					&& bccomp($row['proxyid'], $owner_proxyid) == 0)
				? true
				: 'Host is expected to be assigned to the owner proxy by a new host_proxy row, got: '.
					json_encode($row);
		});

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		// 5) the other proxy gets R2 and the removal of R1 in the same configuration sync
		$this->startComponent($other);
		$this->reloadConfigurationCacheAndWaitForLogLine($other);

		// 6) the other proxy must still redirect the host to the owner
		$this->waitForRedirect(self::COMPONENT_SERVER, self::REENTER_HOSTNAME, $owner_address,
				'Server does not redirect the host that entered the proxy group again');
		$this->waitForRedirect($other, self::REENTER_HOSTNAME, $owner_address,
				'Proxy that does not monitor the host does not redirect it after it entered the proxy group again');

		// restore the proxy groups
		$response = $this->call('host.delete', [self::$reenter_hostid]);
		$this->assertArrayHasKey('hostids', $response['result']);
		self::$reenter_hostid = null;

		$response = $this->call('proxy.update', [
			'proxyid' => self::$proxyid2,
			'proxy_groupid' => self::$proxy_groupid2
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY);
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_PROXY_HANODE1);
	}

	/**
	 * Stop the proxy monitoring the failover host until the host fails over to the other proxy of the group, then
	 * start it again and check that the host is monitored by the other proxy and redirected to it.
	 *
	 * @param string $from  proxy component monitoring the host
	 * @param string $to    proxy component the host must fail over to
	 */
	private function failOverHost($from, $to) {
		// stop the proxy, server configuration cache is not synced, so it keeps the host in the proxy list of $from
		self::stopComponent($from);

		$this->waitForAssignedProxy(self::$failover_hostid, $this->getProxyId($to));

		// the stopped proxy gets the host_proxy row pointing to $to, while the host is still in its hosts table
		$this->startComponent($from);
		$this->reloadConfigurationCacheAndWaitForLogLine($from);

		// server moves the host from the proxy list of $from to $to
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);
		$this->reloadConfigurationCacheAndWaitForLogLine($from);
		$this->reloadConfigurationCacheAndWaitForLogLine($to);

		$this->waitForProxyActiveCheck($to, self::FAILOVER_HOSTNAME, true,
				'Host is not monitored by the proxy it failed over to');
		$this->waitForProxyActiveCheck($from, self::FAILOVER_HOSTNAME, false,
				'Host is still monitored by the proxy it failed over from');
		$this->waitForRedirect(self::COMPONENT_SERVER, self::FAILOVER_HOSTNAME, self::getProxyAddress($to),
				'Server does not redirect the host to the proxy it failed over to');
		$this->waitForRedirect($from, self::FAILOVER_HOSTNAME, self::getProxyAddress($to),
				'Proxy the host failed over from does not redirect it to the proxy it failed over to');
	}

	/**
	 * Get proxy ID by proxy component.
	 *
	 * @param string $component
	 *
	 * @return string
	 */
	private function getProxyId($component) {
		return ($component === self::COMPONENT_PROXY) ? self::$proxyid1 : self::$proxyid2;
	}

	/**
	 * Create hosts A and B in proxy group 1, rename them before the server syncs, and check that the server and the
	 * proxies redirect the final names to the proxies of the hosts and no longer redirect any other name used.
	 *
	 * @param array $names    initial host names: ['a' => name of host A, 'b' => name of host B]
	 * @param array $renames  renames applied in this order before the server syncs: [['a' or 'b', new name], ...]
	 */
	private function checkHostRenamesRedirect(array $names, array $renames) {
		$components = [self::COMPONENT_SERVER, self::COMPONENT_PROXY, self::COMPONENT_PROXY_HANODE1];

		// both proxies in group 1, so each proxy holds the host_proxy rows of hosts monitored by the other one
		$response = $this->call('proxy.update', [
			'proxyid' => self::$proxyid2,
			'proxy_groupid' => self::$proxy_groupid1
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);

		// host B is created first, so it gets lower ids and tends to be processed before host A
		$hostids = [];
		foreach ([$names['b'], $names['a']] as $hostname) {
			$hostids[] = $this->createGroup1Host($hostname);
		}
		[self::$swap_hostid_b, self::$swap_hostid_a] = $hostids;
		$hostids = ['a' => self::$swap_hostid_a, 'b' => self::$swap_hostid_b];

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		$assigned = function ($row) {
			return ($row !== false) ? true : 'Host is expected to be assigned to a proxy of group 1';
		};
		$rows = [];
		$owners = [];
		foreach ($hostids as $host => $hostid) {
			$rows[$host] = $this->waitForHostProxyRow($hostid, $assigned);
			$owners[$host] = $this->getProxyComponent($rows[$host]['proxyid']);
		}

		// with both hosts on the same proxy the names would redirect to the same address before and after the renames
		$this->assertNotSame($owners['a'], $owners['b'], 'Hosts are expected to be assigned to different proxies, '.
				'host_proxy rows: '.json_encode($rows));

		foreach ($components as $component) {
			$this->reloadConfigurationCacheAndWaitForLogLine($component);
		}

		$this->checkHostRenamesMonitoring($names, $owners, 'before the renames');

		// rename the hosts, all before the server syncs
		$old_names = $names;
		$used_names = array_values($names);
		foreach ($renames as [$host, $hostname]) {
			$response = $this->call('host.update', [
				'hostid' => $hostids[$host],
				'host' => $hostname
			]);
			$this->assertArrayHasKey('hostids', $response['result']);

			$names[$host] = $hostname;
			$used_names[] = $hostname;
		}
		$stale_names = array_values(array_diff(array_unique($used_names), $names));

		// the same server sync applies all renames to the host_proxy index
		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		foreach ($names as $host => $hostname) {
			$this->waitForRedirect(self::COMPONENT_SERVER, $hostname, self::getProxyAddress($owners[$host]),
					'Server does not redirect new name "'.$hostname.'" of host '.strtoupper($host).' to its proxy');
		}
		foreach ($stale_names as $hostname) {
			$this->waitForRedirect(self::COMPONENT_SERVER, $hostname, null,
					'Server still redirects name "'.$hostname.'" no longer used by any host');
		}

		// proxy group manager bumps the revision of both host_proxy rows, so proxies get the new names
		foreach ($hostids as $host => $hostid) {
			$row = $rows[$host];

			$this->waitForHostProxyRow($hostid, function ($new_row) use ($row) {
				return ($new_row !== false && bccomp($new_row['hostproxyid'], $row['hostproxyid']) == 0
						&& bccomp($new_row['proxyid'], $row['proxyid']) == 0
						&& bccomp($new_row['revision'], $row['revision']) > 0)
					? true
					: 'Host rename is expected to bump the revision of host_proxy row '.json_encode($row).
						', got: '.json_encode($new_row);
			});
		}

		// the proxies have not synced since the renames - their next sync gets both the host renames and the updated
		// host_proxy rows
		foreach ($old_names as $host => $hostname) {
			$this->waitForProxyActiveCheck($owners[$host], $hostname, true, 'Proxy "'.$owners[$host].
					'" got the rename of host '.strtoupper($host).' "'.$hostname.'" before the host_proxy row update');
		}

		// the first round loads the updated host_proxy rows, the second one must not break what the first one built
		foreach (['after the renames', 'after reloading again'] as $stage) {
			foreach ($components as $component) {
				$this->reloadConfigurationCacheAndWaitForLogLine($component);
			}

			$this->checkHostRenamesMonitoring($names, $owners, $stage);

			foreach ($stale_names as $hostname) {
				foreach ($components as $component) {
					$this->waitForRedirect($component, $hostname, null, 'Component "'.$component.
							'" still redirects name "'.$hostname.'" no longer used by any host '.$stage);
				}
			}
		}

		// delete the hosts, the server and the proxies must stop redirecting them
		$response = $this->call('host.delete', array_values($hostids));
		$this->assertArrayHasKey('hostids', $response['result']);
		self::$swap_hostid_a = null;
		self::$swap_hostid_b = null;

		$this->reloadConfigurationCacheAndWaitForLogLine(self::COMPONENT_SERVER);

		foreach ($names as $hostname) {
			foreach ($components as $component) {
				$this->waitForRedirect($component, $hostname, null, 'Component "'.$component.
						'" still redirects deleted host "'.$hostname.'"', $component !== self::COMPONENT_SERVER);
			}
		}

		// restore the proxy groups
		$response = $this->call('proxy.update', [
			'proxyid' => self::$proxyid2,
			'proxy_groupid' => self::$proxy_groupid2
		]);
		$this->assertArrayHasKey('proxyids', $response['result']);

		foreach ($components as $component) {
			$this->reloadConfigurationCacheAndWaitForLogLine($component);
		}
	}

	/**
	 * Create a host monitored by proxy group 1, with an active item, so the proxy monitoring the host can be checked
	 * by the active checks it serves.
	 *
	 * @param string $hostname
	 *
	 * @return string  host ID
	 */
	private function createGroup1Host($hostname) {
		$response = $this->call('host.create', [
			'host' => $hostname,
			'groups' => [['groupid' => 4]],
			'monitored_by' => ZBX_MONITORED_BY_PROXY_GROUP,
			'proxy_groupid' => self::$proxy_groupid1
		]);
		$this->assertArrayHasKey('hostids', $response['result']);
		$hostid = $response['result']['hostids'][0];

		$response = $this->call('item.create', [
			'name' => 'Agent ping',
			'key_' => self::ITEM_KEY,
			'type' => ITEM_TYPE_ZABBIX_ACTIVE,
			'value_type' => ITEM_VALUE_TYPE_UINT64,
			'delay' => '1s',
			'hostid' => $hostid
		]);
		$this->assertArrayHasKey('itemids', $response['result']);

		return $hostid;
	}

	/**
	 * Check that each renamed host is monitored by its proxy under its current name - the proxy serves its active
	 * checks and does not redirect it - and that the server and the other proxy redirect that name to it.
	 *
	 * @param array  $names   host => current host name
	 * @param array  $owners  host => component of the proxy monitoring the host
	 * @param string $stage   test stage for failure messages
	 */
	private function checkHostRenamesMonitoring(array $names, array $owners, $stage) {
		foreach ($names as $host => $hostname) {
			$this->waitForRedirect($owners[$host], $hostname, null, 'Proxy "'.$owners[$host].'" redirects host '.
					strtoupper($host).' "'.$hostname.'" it monitors '.$stage);
			$this->waitForProxyActiveCheck($owners[$host], $hostname, true, 'Host '.strtoupper($host).' "'.
					$hostname.'" is not monitored by its proxy "'.$owners[$host].'" '.$stage);
		}

		$this->checkSwapRedirects([$names['a'] => $owners['a'], $names['b'] => $owners['b']], $stage);
	}

	/**
	 * Check that the server and every proxy not monitoring a host redirect the host name to its proxy.
	 *
	 * @param array  $owners  host name => component of the proxy monitoring the host
	 * @param string $stage   test stage for failure messages
	 */
	private function checkSwapRedirects(array $owners, $stage) {
		foreach ($owners as $hostname => $owner) {
			$address = self::getProxyAddress($owner);

			foreach ([self::COMPONENT_SERVER, self::COMPONENT_PROXY, self::COMPONENT_PROXY_HANODE1] as $component) {
				// the proxy monitoring the host does not redirect it to itself
				if ($component === $owner) {
					continue;
				}

				$this->waitForRedirect($component, $hostname, $address, 'Component "'.$component.
						'" does not redirect host "'.$hostname.'" to its proxy '.$stage);
			}
		}
	}

	/**
	 * Get proxy component by proxy ID.
	 *
	 * @param string $proxyid
	 *
	 * @return string
	 */
	private function getProxyComponent($proxyid) {
		return (bccomp($proxyid, self::$proxyid1) == 0) ? self::COMPONENT_PROXY : self::COMPONENT_PROXY_HANODE1;
	}

	/**
	 * Get the address agents are redirected to for the proxy component.
	 *
	 * @param string $component  proxy component
	 *
	 * @return string
	 */
	private static function getProxyAddress($component) {
		return '127.0.0.1:'.self::getConfigurationValue($component, 'ListenPort');
	}

	/**
	 * Stop proxy 2 and wait until it and group 2 (where it is the only proxy) are offline.
	 */
	private function takeGroup2Offline() {
		self::stopComponent(self::COMPONENT_PROXY_HANODE1);

		$this->waitForProxyOffline(self::$proxyid2);

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
	 * Wait until the proxy is offline.
	 *
	 * @param string $proxyid
	 */
	private function waitForProxyOffline($proxyid) {
		$this->callUntilDataIsPresent('proxy.get', [
			'output' => ['proxyid', 'name', 'state'],
			'proxyids' => [$proxyid]
		], 60, 1, function ($response) {
			return ($response['result'][0]['state'] == ZBX_PROXY_STATE_OFFLINE)
				? true
				: 'Proxy "'.$response['result'][0]['name'].'" is not offline yet, state: '.
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
			$row = CDBHelper::getRow('SELECT hostproxyid,proxyid,revision FROM host_proxy WHERE hostid='.
					zbx_dbstr($hostid));
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

	/**
	 * Wait until the component redirects active checks of the host to the expected address.
	 *
	 * @param string      $component  server or proxy component
	 * @param string      $hostname   host to request active checks for
	 * @param string|null $address    expected redirect address, null to wait for no redirect
	 * @param string      $message    failure message
	 * @param bool        $reload     reload configuration cache of the component before each retry, for changes
	 *                                that reach the component only through its configuration sync
	 */
	private function waitForRedirect($component, $hostname, $address, $message, $reload = false) {
		$redirect = null;
		$error = null;

		for ($i = 0; $i < 15; $i++) {
			$client = new CRedirectAwareClient('localhost', self::getConfigurationValue($component, 'ListenPort'),
				10, 10, ZBX_SOCKET_BYTES_LIMIT, tls_config: ['ACTIVE' => false]
			);
			$client->getActiveChecks($hostname);
			$redirect = $client->redirect;
			$error = $client->getError();

			$redirected = (is_array($redirect) && array_key_exists('address', $redirect)) ? $redirect['address'] : null;

			if ($redirected === $address) {
				return;
			}

			// changes reaching the proxies through the proxy group manager take at least one manager cycle,
			// a reload alone takes only milliseconds, so always wait before the next attempt
			sleep(1);

			if ($reload) {
				$this->reloadConfigurationCacheAndWaitForLogLine($component);
			}
		}

		$this->fail($message.', host: "'.$hostname.'", expected redirect: '.json_encode($address).', got: '.
				json_encode($redirect).', error: '.json_encode($error));
	}
}

/**
 * Client that keeps the redirect block of the response, which is sent with failed status and without info.
 */
class CRedirectAwareClient extends CZabbixClient {

	/**
	 * @var array|null
	 */
	public $redirect = null;

	/**
	 * @inheritdoc
	 */
	protected function normalizeResponse(array &$response): bool {
		if (array_key_exists('redirect', $response)) {
			$this->redirect = $response['redirect'];

			if (!array_key_exists('info', $response)) {
				$response['info'] = 'redirect';
			}
		}

		return parent::normalizeResponse($response);
	}
}
