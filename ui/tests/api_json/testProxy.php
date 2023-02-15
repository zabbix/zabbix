<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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


require_once __DIR__.'/../include/CAPITest.php';

/**
 * @onBefore prepareTestData
 *
 * @onAfter clearData
 */
class testProxy extends CAPITest {

	/**
	 * Non-existent ID, type, status etc.
	 */
	private const INVALID_NUMBER = 999999;

	/**
	 * @var array
	 */
	private static array $data = [
		'proxyids' => [],
		'groupids' => [],
		'hostids' => [],
		'actionids' => [],
		'druleids' => [],

		// Created proxies during proxy.create test (deleted at the end).
		'created' => []
	];

	/**
	 * Prepare data for tests. Create proxies, host groups, hosts, actions, discovery rules.
	 */
	public function prepareTestData(): void {
		$this->prepareTestDataProxies();
		$this->prepareTestDataHostGroups();
		$this->prepareTestDataHosts();
		$this->prepareTestDataActions();
		$this->prepareTestDataDiscoveryRules();
	}

	/**
	 * Create proxies.
	 */
	private function prepareTestDataProxies(): void {
		$proxies = [
			'get_active_defaults' => [
				'host' => 'API test proxy.get - active',
				'status' => HOST_STATUS_PROXY_ACTIVE
			],
			'get_passive_defaults' => [
				'host' => 'API test proxy.get - passive',
				'status' => HOST_STATUS_PROXY_PASSIVE,
				'interface' => [
					'useip' => INTERFACE_USE_IP,
					'ip' => '127.0.0.1',
					'dns' => 'localhost',
					'port' => '10050'
				]
			],
			'get_version_undefined' => [
				'host' => 'API test proxy.get for filter - version undefined',
				'status' => HOST_STATUS_PROXY_ACTIVE
			],
			'get_version_current' => [
				'host' => 'API test proxy.get for filter - version current',
				'status' => HOST_STATUS_PROXY_ACTIVE
			],
			'get_version_outdated' => [
				'host' => 'API test proxy.get for filter - version outdated',
				'status' => HOST_STATUS_PROXY_ACTIVE
			],
			'get_version_unsupported' => [
				'host' => 'API test proxy.get for filter - version unsupported',
				'status' => HOST_STATUS_PROXY_ACTIVE
			],
			'update_active_defaults' => [
				'host' => 'API test proxy.update - active defaults',
				'status' => HOST_STATUS_PROXY_ACTIVE
			],
			'update_passive_defaults' => [
				'host' => 'API test proxy.update - passive defaults',
				'status' => HOST_STATUS_PROXY_PASSIVE,
				'interface' => [
					'useip' => INTERFACE_USE_IP,
					'ip' => '127.0.0.1',
					'dns' => 'localhost',
					'port' => '10050'
				]
			],
			'update_active_psk' => [
				'host' => 'API test proxy.update - active with PSK-based connections from proxy',
				'status' => HOST_STATUS_PROXY_ACTIVE,
				'tls_accept' => HOST_ENCRYPTION_PSK,
				'tls_psk_identity' => 'Test PSK',
				'tls_psk' => '9b8eafedfaae00cece62e85d5f4792c7d9c9bcc851b23216a1d300311cc4f7cb'
			],
			'update_active_cert' => [
				'host' => 'API test proxy.update - active with certificate-based connections from proxy',
				'status' => HOST_STATUS_PROXY_ACTIVE,
				'tls_accept' => HOST_ENCRYPTION_CERTIFICATE
			],
			'update_active_any' => [
				'host' => 'API test proxy.update - active with any connections from proxy',
				'status' => HOST_STATUS_PROXY_ACTIVE,
				'tls_accept' => HOST_ENCRYPTION_NONE + HOST_ENCRYPTION_PSK + HOST_ENCRYPTION_CERTIFICATE,
				'tls_psk_identity' => 'Test PSK',
				'tls_psk' => '9b8eafedfaae00cece62e85d5f4792c7d9c9bcc851b23216a1d300311cc4f7cb'
			],
			'update_passive_dns' => [
				'host' => 'API test proxy.update - passive with DNS name',
				'status' => HOST_STATUS_PROXY_PASSIVE,
				'interface' => [
					'useip' => INTERFACE_USE_DNS,
					'ip' => '127.0.0.1',
					'dns' => 'localhost',
					'port' => '10050'
				]
			],
			'update_passive_ip' => [
				'host' => 'API test proxy.update - passive with IP address',
				'status' => HOST_STATUS_PROXY_PASSIVE,
				'interface' => [
					'useip' => INTERFACE_USE_IP,
					'ip' => '127.0.0.1',
					'dns' => 'localhost',
					'port' => '10050'
				]
			],
			'update_passive_psk' => [
				'host' => 'API test proxy.update - passive with PSK-based connections to proxy',
				'status' => HOST_STATUS_PROXY_PASSIVE,
				'interface' => [
					'useip' => INTERFACE_USE_IP,
					'ip' => '127.0.0.1',
					'dns' => 'localhost',
					'port' => '10050'
				],
				'tls_connect' => HOST_ENCRYPTION_PSK
			],
			'update_passive_cert' => [
				'host' => 'API test proxy.update - passive with certificate-based connections to proxy',
				'status' => HOST_STATUS_PROXY_PASSIVE,
				'interface' => [
					'useip' => INTERFACE_USE_IP,
					'ip' => '127.0.0.1',
					'dns' => 'localhost',
					'port' => '10050'
				],
				'tls_connect' => HOST_ENCRYPTION_CERTIFICATE
			],
			'update_hosts' => [
				'host' => 'API test proxy.update - hosts',
				'status' => HOST_STATUS_PROXY_ACTIVE
			],
			'delete_single' => [
				'host' => 'API test proxy.delete - single',
				'status' => HOST_STATUS_PROXY_ACTIVE
			],
			'delete_multiple_1' => [
				'host' => 'API test proxy.delete - multiple 1',
				'status' => HOST_STATUS_PROXY_ACTIVE
			],
			'delete_multiple_2' => [
				'host' => 'API test proxy.delete - multiple 2',
				'status' => HOST_STATUS_PROXY_ACTIVE
			],
			'delete_used_in_host' => [
				'host' => 'API test proxy.delete - used in hosts',
				'status' => HOST_STATUS_PROXY_ACTIVE
			],
			'delete_used_in_action' => [
				'host' => 'API test proxy.delete - used in actions',
				'status' => HOST_STATUS_PROXY_ACTIVE
			],
			'delete_used_in_discovery' => [
				'host' => 'API test proxy.delete - used in discovery rules',
				'status' => HOST_STATUS_PROXY_ACTIVE
			]
		];
		$db_proxies = CDataHelper::call('proxy.create', array_values($proxies));
		$this->assertArrayHasKey('proxyids', $db_proxies, __FUNCTION__.'() failed: Could not create proxies.');

		self::$data['proxyids'] = array_combine(array_keys($proxies), $db_proxies['proxyids']);

		// Manually update "host_rtdata" table.
		$proxy_rtdata = [
			'get_version_current' => [
				'lastaccess' => 1662034530,
				'version' => 60400,
				'compatibility' => ZBX_PROXY_VERSION_CURRENT
			],
			'get_version_outdated' => [
				'lastaccess' => 1662034225,
				'version' => 60200,
				'compatibility' => ZBX_PROXY_VERSION_OUTDATED
			],
			'get_version_unsupported' => [
				'lastaccess' => 1651407015,
				'version' => 50401,
				'compatibility' => ZBX_PROXY_VERSION_UNSUPPORTED
			]
		];

		$upd_proxy_rtdata = [];
		foreach ($proxy_rtdata as $id_placeholder => $rtdata) {
			$upd_proxy_rtdata[] = [
				'values' => [
					'lastaccess' => $rtdata['lastaccess'],
					'version' => $rtdata['version'],
					'compatibility' => $rtdata['compatibility']
				],
				'where' => ['hostid' => self::$data['proxyids'][$id_placeholder]]
			];
		}

		DB::update('host_rtdata', $upd_proxy_rtdata);
	}

	/**
	 * Create host groups.
	 */
	private function prepareTestDataHostGroups(): void {
		$db_hostgroups = CDataHelper::call('hostgroup.create', [
			'name' => 'API test host group'
		]);
		$this->assertArrayHasKey('groupids', $db_hostgroups, __FUNCTION__.'() failed: Could not create host groups.');

		self::$data['groupids'] = $db_hostgroups['groupids'];
	}

	/**
	 * Create hosts.
	 */
	private function prepareTestDataHosts(): void {
		$hosts = [
			'with_proxy' => [
				'host' => 'api_test_host_with_proxy',
				'name' => 'API test host - with proxy',
				'proxy_hostid' => self::$data['proxyids']['delete_used_in_host'],
				'groups' => [
					[
						'groupid' => self::$data['groupids'][0]
					]
				]
			],
			'without_proxy_1' => [
				'host' => 'api_test_host_without_proxy_1',
				'name' => 'API test host - without proxy 1',
				'groups' => [
					[
						'groupid' => self::$data['groupids'][0]
					]
				]
			],
			'without_proxy_2' => [
				'host' => 'api_test_host_without_proxy_2',
				'name' => 'API test host - without proxy 2',
				'groups' => [
					[
						'groupid' => self::$data['groupids'][0]
					]
				]
			]
		];
		$db_hosts = CDataHelper::call('host.create', array_values($hosts));
		$this->assertArrayHasKey('hostids', $db_hosts, __FUNCTION__.'() failed: Could not create hosts.');

		self::$data['hostids'] = array_combine(array_keys($hosts), $db_hosts['hostids']);
	}

	/**
	 * Create actions.
	 */
	private function prepareTestDataActions(): void {
		$actions = [
			'name' => 'API test discovery action',
			'eventsource' => EVENT_SOURCE_DISCOVERY,
			'filter' => [
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
				'conditions' => [
					[
						'conditiontype' => CONDITION_TYPE_PROXY,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => self::$data['proxyids']['delete_used_in_action']
					]
				]
			],
			'operations' => [
				[
					'operationtype' => OPERATION_TYPE_MESSAGE,
					'opmessage_grp' => [
						[
							'usrgrpid' => 7
						]
					],
					'opmessage' => [
						'mediatypeid' => 0,
						'default_msg' => 1
					]
				]
			]
		];
		$db_actions = CDataHelper::call('action.create', $actions);
		$this->assertArrayHasKey('actionids', $db_actions, __FUNCTION__.'() failed: Could not create actions.');

		self::$data['actionids'] = $db_actions['actionids'];
	}

	/**
	 * Create discovery rules.
	 */
	private function prepareTestDataDiscoveryRules(): void {
		$drules = [
			'name' => 'API test discovery rule',
			'iprange' => '192.168.1.1-255',
			'proxy_hostid' => self::$data['proxyids']['delete_used_in_discovery'],
			'dchecks' => [
				[
					'type' => SVC_AGENT,
					'key_' => 'system.uname',
					'ports' => 10050,
					'uniq' => 0
				]
			]
		];
		$db_drules = CDataHelper::call('drule.create', $drules);
		$this->assertArrayHasKey('druleids', $db_drules, __FUNCTION__.'() failed: Could not create discovery rules.');

		self::$data['druleids'] = $db_drules['druleids'];
	}

	/**
	 * Data provider for proxy.create. Array contains invalid proxies.
	 *
	 * @return array
	 */
	public static function getProxyCreateDataInvalid(): array {
		return [
			'Test proxy.create: empty request' => [
				'proxy' => [],
				'expected_error' => 'Invalid parameter "/": cannot be empty.'
			],
			'Test proxy.create: unexpected parameter' => [
				'proxy' => [
					'abc' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "abc".'
			],

			// Check "host".
			'Test proxy.create: missing "host"' => [
				'proxy' => [
					'description' => ''
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "host" is missing.'
			],
			'Test proxy.create: invalid "host" (empty string)' => [
				'proxy' => [
					'host' => ''
				],
				'expected_error' => 'Invalid parameter "/1/host": cannot be empty.'
			],
			'Test proxy.create: invalid "host" (UTF-8 string)' => [
				'proxy' => [
					'host' => 'АПИ прокси УТФ-8'
				],
				'expected_error' => 'Invalid parameter "/1/host": invalid host name.'
			],
			'Test proxy.create: invalid "host" (does not match naming pattern)' => [
				'proxy' => [
					'host' => 'API create proxy?'
				],
				'expected_error' => 'Invalid parameter "/1/host": invalid host name.'
			],
			'Test proxy.create: invalid "host" (too long)' => [
				'proxy' => [
					'host' => str_repeat('h', DB::getFieldLength('hosts', 'host') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/host": value is too long.'
			],
			'Test proxy.create: multiple proxies with the same "host"' => [
				'proxy' => [
					[
						'host' => 'API create proxy',
						'status' => HOST_STATUS_PROXY_ACTIVE
					],
					[
						'host' => 'API create proxy',
						'status' => HOST_STATUS_PROXY_PASSIVE
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (host)=(API create proxy) already exists.'
			],
			'Test proxy.create: invalid "host" (duplicate)' => [
				'proxy' => [
					'host' => 'API test proxy.get - active',
					'status' => HOST_STATUS_PROXY_ACTIVE
				],
				'expected_error' => 'Proxy "API test proxy.get - active" already exists.'
			],

			// Check "status".
			'Test proxy.create: missing "status"' => [
				'proxy' => [
					'host' => 'API create proxy'
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "status" is missing.'
			],
			'Test proxy.create: invalid "status" (string)' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/status": an integer is expected.'
			],
			'Test proxy.create: invalid "status" (not in range)' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/status": value must be one of '.
					implode(', ', [HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE]).'.'
			],

			// Check "description".
			'Test proxy.create: invalid "description" (bool)' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'description' => false
				],
				'expected_error' => 'Invalid parameter "/1/description": a character string is expected.'
			],
			'Test proxy.create: invalid "description" (too long)' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'description' => str_repeat('d', DB::getFieldLength('hosts', 'description') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/description": value is too long.'
			],

			// Check "proxy_address".
			'Test proxy.create: invalid "proxy_address" (bool)' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'proxy_address' => false
				],
				'expected_error' => 'Invalid parameter "/1/proxy_address": a character string is expected.'
			],
			'Test proxy.create: invalid "proxy_address" (IP address range)' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'proxy_address' => '192.168.0-255.0/30'
				],
				'expected_error' => 'Invalid parameter "/1/proxy_address": invalid address range "192.168.0-255.0/30".'
			],
			'Test proxy.create: invalid "proxy_address" (IPv6 address range)' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'proxy_address' => '::ff-0ffff'
				],
				'expected_error' => 'Invalid parameter "/1/proxy_address": invalid address range "::ff-0ffff".'
			],
			'Test proxy.create: invalid "proxy_address" (user macro)' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'proxy_address' => '{$MACRO}'
				],
				'expected_error' => 'Invalid parameter "/1/proxy_address": invalid address range "{$MACRO}".'
			],
			'Test proxy.create: invalid "proxy_address" (too long)' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'proxy_address' => str_repeat('a', DB::getFieldLength('hosts', 'proxy_address') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/proxy_address": value is too long.'
			],

			// Check "hosts".
			'Test proxy.create: invalid "hosts" (string)' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'hosts' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/hosts": an array is expected.'
			],
			'Test proxy.create: invalid "hosts" (array with string)' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'hosts' => ['abc']
				],
				'expected_error' => 'Invalid parameter "/1/hosts/1": an array is expected.'
			],
			'Test proxy.create: missing "hostid" for "hosts"' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'hosts' => [
						[]
					]
				],
				'expected_error' => 'Invalid parameter "/1/hosts/1": the parameter "hostid" is missing.'
			],
			'Test proxy.create: unexpected parameter for "hosts"' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'hosts' => [
						['abc' => '']
					]
				],
				'expected_error' => 'Invalid parameter "/1/hosts/1": unexpected parameter "abc".'
			],
			'Test proxy.create: invalid "hostid" (empty string) for "hosts"' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'hosts' => [
						['hostid' => '']
					]
				],
				'expected_error' => 'Invalid parameter "/1/hosts/1/hostid": a number is expected.'
			],
			'Test proxy.create: invalid "hostid" (non-existent) for "hosts"' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'hosts' => [
						['hostid' => self::INVALID_NUMBER]
					]
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Test proxy.create: invalid "hostid" (duplicate) for "hosts"' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'hosts' => [
						['hostid' => 0],
						['hostid' => 0]
					]
				],
				'expected_error' => 'Invalid parameter "/1/hosts/2": value (hostid)=(0) already exists.'
			],

			// Check "interface".
			'Test proxy.create: invalid "interface" (string)' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'interface' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/interface": an array is expected.'
			],
			'Test proxy.create: unexpected parameter for "interface"' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_PASSIVE,
					'interface' => [
						'abc' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/1/interface": unexpected parameter "abc".'
			],
			'Test proxy.create: invalid "useip" (string) for "interface"' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_PASSIVE,
					'interface' => [
						'useip' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/interface/useip": an integer is expected.'
			],
			'Test proxy.create: invalid "useip" (not in range) for "interface"' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_PASSIVE,
					'interface' => [
						'useip' => self::INVALID_NUMBER
					]
				],
				'expected_error' => 'Invalid parameter "/1/interface/useip": value must be one of '.
					implode(', ', [INTERFACE_USE_DNS, INTERFACE_USE_IP]).'.'
			],
			'Test proxy.create: invalid "ip" (string) for "interface"' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_PASSIVE,
					'interface' => [
						'ip' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/interface/ip": an IP address is expected.'
			],
			'Test proxy.create: invalid "ip" (too long) for "interface"' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_PASSIVE,
					'interface' => [
						'ip' => str_repeat('i', DB::getFieldLength('interface', 'ip') + 1)
					]
				],
				'expected_error' => 'Invalid parameter "/1/interface/ip": value is too long.'
			],
			'Test proxy.create: invalid "dns" (bool) for "interface"' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_PASSIVE,
					'interface' => [
						'dns' => false
					]
				],
				'expected_error' => 'Invalid parameter "/1/interface/dns": a character string is expected.'
			],
			'Test proxy.create: invalid "dns" (too long) for "interface"' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_PASSIVE,
					'interface' => [
						'dns' => str_repeat('d', DB::getFieldLength('interface', 'dns') + 1)
					]
				],
				'expected_error' => 'Invalid parameter "/1/interface/dns": value is too long.'
			],
			'Test proxy.create: invalid "port" (string) for "interface"' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_PASSIVE,
					'interface' => [
						'port' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/interface/port": an integer is expected.'
			],
			'Test proxy.create: invalid "port" (not in range) for "interface"' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_PASSIVE,
					'interface' => [
						'port' => self::INVALID_NUMBER
					]
				],
				'expected_error' =>
					'Invalid parameter "/1/interface/port": value must be one of 0-'.ZBX_MAX_PORT_NUMBER.'.'
			],
			'Test proxy.create: invalid "interface" (not empty)' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'interface' => [
						'port' => 12345
					]
				],
				'expected_error' =>
					'Invalid parameter "/1/interface": should be empty.'
			],

			// Check "tls_connect".
			'Test proxy.create: invalid "tls_connect" (string)' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'tls_connect' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_connect": an integer is expected.'
			],
			'Test proxy.create: invalid "tls_connect" (not in range) for active proxy' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'tls_connect' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/tls_connect": value must be '.HOST_ENCRYPTION_NONE.'.'
			],
			'Test proxy.create: invalid "tls_connect" (not in range) for passive proxy' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_PASSIVE,
					'tls_connect' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/tls_connect": value must be one of '.
					implode(', ', [HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_PSK, HOST_ENCRYPTION_CERTIFICATE]).'.'
			],

			// Check "tls_accept".
			'Test proxy.create: invalid "tls_accept" (string)' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'tls_accept' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_accept": an integer is expected.'
			],
			'Test proxy.create: invalid "tls_accept" (not in range) for active proxy' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'tls_accept' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/tls_accept": value must be one of '.HOST_ENCRYPTION_NONE.'-'.
					(HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE).'.'
			],
			'Test proxy.create: invalid "tls_accept" (not in range) for passive proxy' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_PASSIVE,
					'tls_accept' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/tls_accept": value must be '.HOST_ENCRYPTION_NONE.'.'
			],

			// Check "tls_psk_identity".
			'Test proxy.create: invalid "tls_psk_identity" (bool)' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'tls_psk_identity' => false
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": a character string is expected.'
			],
			'Test proxy.create: invalid "tls_psk_identity" (string) for active proxy #1' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'tls_psk_identity' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value must be empty.'
			],
			'Test proxy.create: invalid "tls_psk_identity" (string) for active proxy #2' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_NONE,
					'tls_psk_identity' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value must be empty.'
			],
			'Test proxy.create: invalid "tls_psk_identity" (string) for active proxy #3' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_CERTIFICATE,
					'tls_psk_identity' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value must be empty.'
			],
			'Test proxy.create: invalid "tls_psk_identity" (string) for passive proxy #1' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_PASSIVE,
					'tls_psk_identity' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value must be empty.'
			],
			'Test proxy.create: invalid "tls_psk_identity" (string) for passive proxy #2' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_PASSIVE,
					'tls_connect' => HOST_ENCRYPTION_NONE,
					'tls_psk_identity' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value must be empty.'
			],
			'Test proxy.create: invalid "tls_psk_identity" (string) for passive proxy #3' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_PASSIVE,
					'tls_connect' => HOST_ENCRYPTION_CERTIFICATE,
					'tls_psk_identity' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value must be empty.'
			],
			'Test proxy.create: invalid "tls_psk_identity" (empty string) for active proxy' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => ''
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": cannot be empty.'
			],
			'Test proxy.create: invalid "tls_psk_identity" (empty string) for passive proxy' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_PASSIVE,
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => ''
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": cannot be empty.'
			],
			'Test proxy.create: invalid "tls_psk_identity" (too long) for active proxy' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => str_repeat('i', DB::getFieldLength('hosts', 'tls_psk_identity') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value is too long.'
			],
			'Test proxy.create: invalid "tls_psk_identity" (too long) for passive proxy' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_PASSIVE,
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => str_repeat('i', DB::getFieldLength('hosts', 'tls_psk_identity') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value is too long.'
			],

			// Check "tls_psk".
			'Test proxy.create: invalid "tls_psk" (bool) for active proxy' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'tls_psk' => false
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": a character string is expected.'
			],
			'Test proxy.create: invalid "tls_psk" (string) for active proxy #1' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'tls_psk' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": value must be empty.'
			],
			'Test proxy.create: invalid "tls_psk" (string) for active proxy #2' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_NONE,
					'tls_psk' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": value must be empty.'
			],
			'Test proxy.create: invalid "tls_psk" (string) for active proxy #3' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_CERTIFICATE,
					'tls_psk' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": value must be empty.'
			],
			'Test proxy.create: invalid "tls_psk" (string) for passive proxy #1' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'tls_psk' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": value must be empty.'
			],
			'Test proxy.create: invalid "tls_psk" (string) for passive proxy #2' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_PASSIVE,
					'tls_connect' => HOST_ENCRYPTION_NONE,
					'tls_psk' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": value must be empty.'
			],
			'Test proxy.create: invalid "tls_psk" (string) for passive proxy #3' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_PASSIVE,
					'tls_connect' => HOST_ENCRYPTION_CERTIFICATE,
					'tls_psk' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": value must be empty.'
			],
			'Test proxy.create: invalid "tls_psk" (too short) for active proxy' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_psk' => 'abc'
				],
				'expected_error' =>
					'Invalid parameter "/1/tls_psk": minimum length is 32 characters.'
			],
			'Test proxy.create: invalid "tls_psk" (too short) for passive proxy' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_PASSIVE,
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'tls_psk' => 'abc'
				],
				'expected_error' =>
					'Invalid parameter "/1/tls_psk": minimum length is 32 characters.'
			],
			'Test proxy.create: invalid "tls_psk" (not PSK) for active proxy' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_psk' => str_repeat('a', 33)
				],
				'expected_error' =>
					'Invalid parameter "/1/tls_psk": an even number of hexadecimal characters is expected.'
			],
			'Test proxy.create: invalid "tls_psk" (not PSK) for passive proxy' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_PASSIVE,
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'tls_psk' => str_repeat('a', 33)
				],
				'expected_error' =>
					'Invalid parameter "/1/tls_psk": an even number of hexadecimal characters is expected.'
			],

			// Check "tls_issuer".
			'Test proxy.create: invalid "tls_issuer" (bool) for active proxy' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'tls_issuer' => false
				],
				'expected_error' => 'Invalid parameter "/1/tls_issuer": a character string is expected.'
			],
			'Test proxy.create: invalid "tls_issuer" (not empty) for active proxy #1' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_NONE,
					'tls_issuer' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_issuer": value must be empty.'
			],
			'Test proxy.create: invalid "tls_issuer" (not empty) for active proxy #2' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_issuer' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_issuer": value must be empty.'
			],
			'Test proxy.create: invalid "tls_issuer" (not empty) for passive proxy #1' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_PASSIVE,
					'tls_connect' => HOST_ENCRYPTION_NONE,
					'tls_issuer' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_issuer": value must be empty.'
			],
			'Test proxy.create: invalid "tls_issuer" (not empty) for passive proxy #2' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_PASSIVE,
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'tls_issuer' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_issuer": value must be empty.'
			],
			'Test proxy.create: invalid "tls_issuer" (too long) for active proxy' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_CERTIFICATE,
					'tls_issuer' => str_repeat('i', DB::getFieldLength('hosts', 'tls_issuer') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/tls_issuer": value is too long.'
			],
			'Test proxy.create: invalid "tls_issuer" (too long) for passive proxy' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_PASSIVE,
					'tls_connect' => HOST_ENCRYPTION_CERTIFICATE,
					'tls_issuer' => str_repeat('i', DB::getFieldLength('hosts', 'tls_issuer') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/tls_issuer": value is too long.'
			],

			// Check "tls_subject".
			'Test proxy.create: invalid "tls_subject" (not empty) for active proxy #1' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_NONE,
					'tls_subject' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_subject": value must be empty.'
			],
			'Test proxy.create: invalid "tls_subject" (not empty) for active proxy #2' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_subject' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_subject": value must be empty.'
			],
			'Test proxy.create: invalid "tls_subject" (not empty) for passive proxy #1' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_PASSIVE,
					'tls_connect' => HOST_ENCRYPTION_NONE,
					'tls_subject' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_subject": value must be empty.'
			],
			'Test proxy.create: invalid "tls_subject" (not empty) for passive proxy #2' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_PASSIVE,
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'tls_subject' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_subject": value must be empty.'
			],
			'Test proxy.create: invalid "tls_subject" (too long) for active proxy' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_CERTIFICATE,
					'tls_subject' => str_repeat('i', DB::getFieldLength('hosts', 'tls_subject') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/tls_subject": value is too long.'
			],
			'Test proxy.create: invalid "tls_subject" (too long) for passive proxy' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => HOST_STATUS_PROXY_PASSIVE,
					'tls_connect' => HOST_ENCRYPTION_CERTIFICATE,
					'tls_subject' => str_repeat('i', DB::getFieldLength('hosts', 'tls_subject') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/tls_subject": value is too long.'
			]
		];
	}

	/**
	 * Data provider for proxy.create. Array contains valid proxies.
	 *
	 * @return array
	 */
	public static function getProxyCreateDataValid(): array {
		return [
			'Test proxy.create: single proxy' => [
				'proxy' => [
					'host' => 'API create single proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE
				],
				'expected_error' => null
			],
			'Test proxy.create: multiple proxies' => [
				'proxy' => [
					[
						'host' => 'API create first proxy',
						'status' => HOST_STATUS_PROXY_ACTIVE
					],
					[
						'host' => 'API create second proxy',
						'status' => HOST_STATUS_PROXY_ACTIVE
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * Test proxy.create with errors like missing fields, optional invalid fields and valid fields.
	 *
	 * @dataProvider getProxyCreateDataInvalid
	 * @dataProvider getProxyCreateDataValid
	 */
	public function testProxy_Create(array $proxies, ?string $expected_error): void {
		// Accept single and multiple proxies just like API method. Work with multidimensional array in result.
		if (!array_key_exists(0, $proxies)) {
			$proxies = zbx_toArray($proxies);
		}

		$sql_proxies = 'SELECT NULL FROM hosts h WHERE '.dbConditionInt('h.status', [
			HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE
		]);
		$old_hash_proxies = CDBHelper::getHash($sql_proxies);

		$result = $this->call('proxy.create', $proxies, $expected_error);

		if ($expected_error === null) {
			// Something was changed in DB.
			$this->assertNotSame($old_hash_proxies, CDBHelper::getHash($sql_proxies));
			$this->assertEquals(count($proxies), count($result['result']['proxyids']));

			// Add proxy IDs to create array, so they can be deleted after tests are complete.
			self::$data['created'] = array_merge(self::$data['created'], $result['result']['proxyids']);

			// Check individual fields according to each proxy status.
			foreach ($result['result']['proxyids'] as $num => $proxyid) {
				$db_proxies = $this->getProxies([$proxyid]);
				$db_proxy = $db_proxies[$proxyid];

				// Required fields.
				$this->assertNotEmpty($db_proxy['host']);
				$this->assertSame($proxies[$num]['host'], $db_proxy['host']);
				$this->assertEquals($proxies[$num]['status'], $db_proxy['status']);

				if (array_key_exists('description', $proxies[$num])) {
					$this->assertSame($proxies[$num]['description'], $db_proxy['description']);
				}
				else {
					$this->assertEmpty($db_proxy['description']);
				}

				if (array_key_exists('proxy_address', $proxies[$num])) {
					$this->assertSame($proxies[$num]['proxy_address'], $db_proxy['proxy_address']);
				}
				else {
					$this->assertEmpty($db_proxy['proxy_address']);
				}

				foreach (['hosts', 'interface'] as $field) {
					if (array_key_exists($field, $proxies[$num]) && $proxies[$num][$field]) {
						$this->assertEqualsCanonicalizing($proxies[$num][$field], $db_proxy[$field]);
					}
					else {
						$this->assertEmpty($db_proxy[$field]);
					}
				}

				foreach (['tls_connect', 'tls_accept'] as $field) {
					if (array_key_exists($field, $proxies[$num])) {
						$this->assertEquals($proxies[$num][$field], $db_proxy[$field]);
					}
					else {
						$this->assertSame(DB::getDefault('hosts', $field), $db_proxy[$field]);
					}
				}

				foreach (['tls_issuer', 'tls_subject', 'tls_psk_identity', 'tls_psk'] as $field) {
					if (array_key_exists($field, $proxies[$num])) {
						$this->assertSame($proxies[$num][$field], $db_proxy[$field]);
					}
					else {
						$this->assertEmpty($db_proxy[$field]);
					}
				}
			}
		}
		else {
			$this->assertSame($old_hash_proxies, CDBHelper::getHash($sql_proxies));
		}
	}

	/**
	 * Data provider for proxy.get. Array contains invalid proxy parameters.
	 *
	 * @return array
	 */
	public static function getProxyGetDataInvalid(): array {
		return [
			// Check unexpected params.
			'Test proxy.get: unexpected parameter' => [
				'request' => [
					'abc' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/": unexpected parameter "abc".'
			],

			// Check "proxyids" field.
			'Test proxy.get: invalid "proxyids" (empty string)' => [
				'request' => [
					'proxyids' => ''
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/proxyids": an array is expected.'
			],
			'Test proxy.get: invalid "proxyids" (array with empty string)' => [
				'request' => [
					'proxyids' => ['']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/proxyids/1": a number is expected.'
			],

			// Check filter.
			'Test proxy.get: invalid "filter" (empty string)' => [
				'request' => [
					'filter' => ''
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/filter": an array is expected.'
			],

			// Check unexpected parameters that exist in object, but not in filter.
			'Test proxy.get: unexpected parameter in "filter"' => [
				'request' => [
					'filter' => [
						'proxy_address' => 'proxy_address'
					]
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/filter": unexpected parameter "proxy_address".'
			],

			// Check "search" option.
			'Test proxy.get: invalid "search" (string)' => [
				'request' => [
					'search' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/search": an array is expected.'
			],

			// Check unexpected parameters that exist in object, but not in search.
			'Test proxy.get: unexpected parameter in "search"' => [
				'request' => [
					'search' => [
						'proxyid' => 'proxyid'
					]
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/search": unexpected parameter "proxyid".'
			],

			// Check "output" option.
			'Test proxy.get: invalid parameter "output" (string)' => [
				'request' => [
					'output' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/output": value must be "'.API_OUTPUT_EXTEND.'".'
			],
			'Test proxy.get: invalid parameter "output" (array with string)' => [
				'request' => [
					'output' => ['abc']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/output/1": value must be one of "proxyid", "host", "status", "description", "lastaccess", "tls_connect", "tls_accept", "tls_issuer", "tls_subject", "proxy_address", "auto_compress", "version", "compatibility".'
			],

			// Check write-only fields are not returned.
			'Test proxy.get: write-only field "tls_psk_identity"' => [
				'request' => [
					'output' => ['tls_psk_identity']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/output/1": value must be one of "proxyid", "host", "status", "description", "lastaccess", "tls_connect", "tls_accept", "tls_issuer", "tls_subject", "proxy_address", "auto_compress", "version", "compatibility".'
			],
			'Test proxy.get: write-only field "tls_psk"' => [
				'request' => [
					'output' => ['tls_psk']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/output/1": value must be one of "proxyid", "host", "status", "description", "lastaccess", "tls_connect", "tls_accept", "tls_issuer", "tls_subject", "proxy_address", "auto_compress", "version", "compatibility".'
			],

			// Check "selectHosts" option.
			'Test proxy.get: invalid parameter "selectHosts" (string)' => [
				'request' => [
					'selectHosts' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/selectHosts": value must be "'.API_OUTPUT_EXTEND.'".'
			],
			'Test proxy.get: invalid parameter "selectHosts" (array with string)' => [
				'request' => [
					'selectHosts' => ['abc']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/selectHosts/1": value must be one of "hostid", "proxy_hostid", "host", "status", "ipmi_authtype", "ipmi_privilege", "ipmi_username", "ipmi_password", "maintenanceid", "maintenance_status", "maintenance_type", "maintenance_from", "name", "flags", "description", "tls_connect", "tls_accept", "tls_issuer", "tls_subject", "inventory_mode", "active_available".'
			],

			// Check "selectInterface" option.
			'Test proxy.get: invalid parameter "selectInterface" (string)' => [
				'request' => [
					'selectInterface' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/selectInterface": value must be "'.API_OUTPUT_EXTEND.'".'
			],
			'Test proxy.get: invalid parameter "selectInterface" (array with string)' => [
				'request' => [
					'selectInterface' => ['abc']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/selectInterface/1": value must be one of "interfaceid", "hostid", "main", "type", "useip", "ip", "dns", "port", "available", "error", "errors_from", "disable_until".'
			],

			// Check common fields that are not flags, but require strict validation.
			'Test proxy.get: invalid parameter "searchByAny" (string)' => [
				'request' => [
					'searchByAny' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/searchByAny": a boolean is expected.'
			],
			'Test proxy.get: invalid parameter "searchWildcardsEnabled" (string)' => [
				'request' => [
					'searchWildcardsEnabled' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/searchWildcardsEnabled": a boolean is expected.'
			],
			'Test proxy.get: invalid parameter "sortfield" (bool)' => [
				'request' => [
					'sortfield' => false
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/sortfield": an array is expected.'
			],
			'Test proxy.get: invalid parameter "sortfield"' => [
				'request' => [
					'sortfield' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/sortfield/1": value must be one of "hostid", "host", "status".'
			],
			'Test proxy.get: invalid parameter "sortorder" (bool)' => [
				'request' => [
					'sortorder' => false
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/sortorder": an array or a character string is expected.'
			],
			'Test proxy.get: invalid parameter "sortorder" (not in range)' => [
				'request' => [
					'sortorder' => 'abc'
				],
				'expected_result' => [],
				'expected_error' =>
					'Invalid parameter "/sortorder": value must be one of "'.ZBX_SORT_UP.'", "'.ZBX_SORT_DOWN.'".'
			],
			'Test proxy.get: invalid parameter "limit" (bool)' => [
				'request' => [
					'limit' => false
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/limit": an integer is expected.'
			],
			'Test proxy.get: invalid parameter "editable" (string)' => [
				'request' => [
					'editable' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/editable": a boolean is expected.'
			],
			'Test proxy.get: invalid parameter "preservekeys" (string)' => [
				'request' => [
					'preservekeys' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/preservekeys": a boolean is expected.'
			]
		];
	}

	/**
	 * Data provider for proxy.get. Array contains valid proxy parameters.
	 *
	 * @return array
	 */
	public static function getProxyGetDataValid(): array {
		return [
			// Check validity of "proxyids" without getting any results.
			'Test proxy.get: empty "proxyids"' => [
				'request' => [
					'proxyids' => []
				],
				'expected_result' => [],
				'expected_error' => null
			],

			// Check no fields are returned on empty selection.
			'Test proxy.get: empty "output"' => [
				'request' => [
					'output' => [],
					'proxyids' => ['get_active_defaults', 'get_passive_defaults']
				],
				'expected_result' => [
					[],
					[]
				],
				'expected_error' => null
			],

			// Check fields from "host_rtdata" table are returned.
			'Test proxy.get: "lastaccess", "version", "compatibility"' => [
				'request' => [
					'output' => ['lastaccess', 'version', 'compatibility'],
					'proxyids' => ['get_version_current', 'get_version_outdated', 'get_version_unsupported']
				],
				'expected_result' => [
					[
						'lastaccess' => '1662034530',
						'version' => '60400',
						'compatibility' => '1'
					],
					[
						'lastaccess' => '1662034225',
						'version' => '60200',
						'compatibility' => '2'
					],
					[
						'lastaccess' => '1651407015',
						'version' => '50401',
						'compatibility' => '3'
					]
				],
				'expected_error' => null
			],

			// Filter by proxy mode.
			'Test proxy.get: filter by "status"' => [
				'request' => [
					'output' => ['host', 'status'],
					'proxyids' => ['get_active_defaults', 'get_passive_defaults'],
					'filter' => [
						'status' => HOST_STATUS_PROXY_ACTIVE
					]
				],
				'expected_result' => [
					[
						'host' => 'API test proxy.get - active',
						'status' => '5'
					]
				],
				'expected_error' => null
			],

			// Filter by Zabbix version.
			'Test proxy.get: filter by "version"' => [
				'request' => [
					'output' => ['host', 'version'],
					'proxyids' => ['get_version_current', 'get_version_outdated', 'get_version_unsupported'],
					'filter' => [
						'version' => ['60000', '60200', '60400']
					]
				],
				'expected_result' => [
					[
						'host' => 'API test proxy.get for filter - version current',
						'version' => '60400'
					],
					[
						'host' => 'API test proxy.get for filter - version outdated',
						'version' => '60200'
					]
				],
				'expected_error' => null
			],

			// Filter by version compatibility.
			'Test proxy.get: filter by "compatibility"' => [
				'request' => [
					'output' => ['host', 'compatibility'],
					'proxyids' => ['get_version_current', 'get_version_outdated', 'get_version_unsupported'],
					'filter' => [
						'compatibility' => [ZBX_PROXY_VERSION_OUTDATED, ZBX_PROXY_VERSION_UNSUPPORTED]
					]
				],
				'expected_result' => [
					[
						'host' => 'API test proxy.get for filter - version outdated',
						'compatibility' => '2'
					],
					[
						'host' => 'API test proxy.get for filter - version unsupported',
						'compatibility' => '3'
					]
				],
				'expected_error' => null
			],

			// Search by proxy name.
			'Test proxy.get: search by "host"' => [
				'request' => [
					'output' => ['host'],
					'search' => [
						'host' => 'API test proxy.get - active'
					]
				],
				'expected_result' => [
					['host' => 'API test proxy.get - active']
				],
				'expected_error' => null
			],

			// Filtering by incorrect data types.
			'Test proxy.get: invalid "status" (string) in "filter"' => [
				'request' => [
					'filter' => [
						'status' => 'abc'
					]
				],
				'expected_result' => [],
				'expected_error' => null
			],
			'Test proxy.get: invalid "status" in "filter"' => [
				'request' => [
					'filter' => [
						'status' => 999999
					]
				],
				'expected_result' => [],
				'expected_error' => null
			],
			'Test proxy.get: invalid "lastaccess" (string) in "filter"' => [
				'request' => [
					'filter' => [
						'lastaccess' => 'abc'
					]
				],
				'expected_result' => [],
				'expected_error' => null
			],
			'Test proxy.get: invalid "lastaccess" (array with string) in "filter"' => [
				'request' => [
					'filter' => [
						'lastaccess' => ['abc']
					]
				],
				'expected_result' => [],
				'expected_error' => null
			],
			'Test proxy.get: invalid "lastaccess" (not in range) in "filter"' => [
				'request' => [
					'filter' => [
						'lastaccess' => [-1]
					]
				],
				'expected_result' => [],
				'expected_error' => null
			],
			'Test proxy.get: invalid "lastaccess" (too large) in "filter"' => [
				'request' => [
					'filter' => [
						'lastaccess' => [ZBX_MAX_DATE + 1]
					]
				],
				'expected_result' => [],
				'expected_error' => null
			],
			'Test proxy.get: invalid "compatibility" (string) for "filter"' => [
				'request' => [
					'filter' => [
						'compatibility' => 'abc'
					]
				],
				'expected_result' => [],
				'expected_error' => null
			],
			'Test proxy.get: invalid "compatibility" (not in range) for "filter"' => [
				'request' => [
					'filter' => [
						'compatibility' => 999999
					]
				],
				'expected_result' => [],
				'expected_error' => null
			]
		];
	}

	/**
	 * Test proxy.get with all options.
	 *
	 * @dataProvider getProxyGetDataInvalid
	 * @dataProvider getProxyGetDataValid
	 */
	public function testProxy_Get(array $request, array $expected_result, ?string $expected_error): void {
		// Replace ID placeholders with real IDs.
		$request = self::resolveIds($request);

		foreach ($expected_result as &$proxy) {
			$proxy = self::resolveIds($proxy);
		}
		unset($proxy);

		$result = $this->call('proxy.get', $request, $expected_error);

		if ($expected_error === null) {
			$this->assertSame($expected_result, $result['result']);
		}
	}

	/**
	 * Data provider for proxy.update. Array contains invalid proxy parameters.
	 *
	 * @return array
	 */
	public static function getProxyUpdateDataInvalid(): array {
		return [
			'Test proxy.update: empty request' => [
				'proxy' => [],
				'expected_error' => 'Invalid parameter "/": cannot be empty.'
			],

			// Check "proxyid".
			'Test proxy.update: missing "proxyid"' => [
				'proxy' => [
					'host' => 'API update proxy'
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "proxyid" is missing.'
			],
			'Test proxy.update: invalid "proxyid" (empty string)' => [
				'proxy' => [
					'proxyid' => ''
				],
				'expected_error' => 'Invalid parameter "/1/proxyid": a number is expected.'
			],
			'Test proxy.update: invalid "proxyid" (non-existent)' => [
				'proxy' => [
					'proxyid' => self::INVALID_NUMBER
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Test proxy.update: multiple proxies with the same "proxyid"' => [
				'proxy' => [
					['proxyid' => 0],
					['proxyid' => 0]
				],
				'expected_error' => 'Invalid parameter "/2": value (proxyid)=(0) already exists.'
			],

			// Check "status".
			'Test proxy.update: invalid "status" (string)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'status' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/status": an integer is expected.'
			],
			'Test proxy.update: invalid "status" (not in range)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'status' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/status": value must be one of '.
					implode(', ', [HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE]).'.'
			],

			// Check "host".
			'Test proxy.update: invalid "host" (bool)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'host' => false
				],
				'expected_error' => 'Invalid parameter "/1/host": a character string is expected.'
			],
			'Test proxy.update: invalid "host" (empty string)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'host' => ''
				],
				'expected_error' => 'Invalid parameter "/1/host": cannot be empty.'
			],
			'Test proxy.update: invalid "host" (too long)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'host' => str_repeat('h', DB::getFieldLength('hosts', 'host') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/host": value is too long.'
			],

			// Check "description".
			'Test proxy.update: invalid "description" (bool)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'description' => false
				],
				'expected_error' => 'Invalid parameter "/1/description": a character string is expected.'
			],
			'Test proxy.update: invalid "description" (too long)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'description' => str_repeat('d', DB::getFieldLength('hosts', 'description') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/description": value is too long.'
			],

			// Check "proxy_address".
			'Test proxy.update: invalid "proxy_address" (bool)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'proxy_address' => false
				],
				'expected_error' => 'Invalid parameter "/1/proxy_address": a character string is expected.'
			],
			'Test proxy.update: invalid "proxy_address" (IP address range)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'proxy_address' => '192.168.0-255.0/30'
				],
				'expected_error' => 'Invalid parameter "/1/proxy_address": invalid address range "192.168.0-255.0/30".'
			],
			'Test proxy.update: invalid "proxy_address" (IPv6 address range)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'proxy_address' => '::ff-0ffff'
				],
				'expected_error' => 'Invalid parameter "/1/proxy_address": invalid address range "::ff-0ffff".'
			],
			'Test proxy.update: invalid "proxy_address" (user macro)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'proxy_address' => '{$MACRO}'
				],
				'expected_error' => 'Invalid parameter "/1/proxy_address": invalid address range "{$MACRO}".'
			],
			'Test proxy.update: invalid "proxy_address" (too long)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'proxy_address' => str_repeat('a', DB::getFieldLength('hosts', 'proxy_address') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/proxy_address": value is too long.'
			],

			// Check "hosts".
			'Test proxy.update: invalid "hosts" (string)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'hosts' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/hosts": an array is expected.'
			],
			'Test proxy.update: invalid "hosts" (array with string)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'hosts' => ['abc']
				],
				'expected_error' => 'Invalid parameter "/1/hosts/1": an array is expected.'
			],
			'Test proxy.update: missing "hostid" for "hosts"' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'hosts' => [
						[]
					]
				],
				'expected_error' => 'Invalid parameter "/1/hosts/1": the parameter "hostid" is missing.'
			],
			'Test proxy.update: unexpected parameter for "hosts"' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'hosts' => [
						['abc' => '']
					]
				],
				'expected_error' => 'Invalid parameter "/1/hosts/1": unexpected parameter "abc".'
			],
			'Test proxy.update: invalid "hostid" (empty string) for "hosts"' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'hosts' => [
						['hostid' => '']
					]
				],
				'expected_error' => 'Invalid parameter "/1/hosts/1/hostid": a number is expected.'
			],
			'Test proxy.update: invalid "hostid" (non-existent) for "hosts"' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'hosts' => [
						['hostid' => self::INVALID_NUMBER]
					]
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Test proxy.update: invalid "hostid" (duplicate) for "hosts"' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'hosts' => [
						['hostid' => 0],
						['hostid' => 0]
					]
				],
				'expected_error' => 'Invalid parameter "/1/hosts/2": value (hostid)=(0) already exists.'
			],

			// Check "interface".
			'Test proxy.update: invalid "interface" (string)' => [
				'proxy' => [
					'proxyid' => 'update_passive_defaults',
					'interface' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/interface": an array is expected.'
			],
			'Test proxy.update: unexpected parameter for "interface"' => [
				'proxy' => [
					'proxyid' => 'update_passive_defaults',
					'interface' => [
						'abc' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/1/interface": unexpected parameter "abc".'
			],
			'Test proxy.update: invalid "useip" (string) for "interface"' => [
				'proxy' => [
					'proxyid' => 'update_passive_defaults',
					'interface' => [
						'useip' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/interface/useip": an integer is expected.'
			],
			'Test proxy.update: invalid "useip" (not in range) for "interface"' => [
				'proxy' => [
					'proxyid' => 'update_passive_defaults',
					'interface' => [
						'useip' => self::INVALID_NUMBER
					]
				],
				'expected_error' => 'Invalid parameter "/1/interface/useip": value must be one of '.
					implode(', ', [INTERFACE_USE_DNS, INTERFACE_USE_IP]).'.'
			],
			'Test proxy.update: invalid "ip" (string) for "interface"' => [
				'proxy' => [
					'proxyid' => 'update_passive_defaults',
					'interface' => [
						'ip' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/interface/ip": an IP address is expected.'
			],
			'Test proxy.create: invalid "ip" (too long) for "interface"' => [
				'proxy' => [
					'proxyid' => 'update_passive_defaults',
					'interface' => [
						'ip' => str_repeat('i', DB::getFieldLength('interface', 'ip') + 1)
					]
				],
				'expected_error' => 'Invalid parameter "/1/interface/ip": value is too long.'
			],
			'Test proxy.update: invalid "dns" (bool) for "interface"' => [
				'proxy' => [
					'proxyid' => 'update_passive_defaults',
					'interface' => [
						'dns' => false
					]
				],
				'expected_error' => 'Invalid parameter "/1/interface/dns": a character string is expected.'
			],
			'Test proxy.update: invalid "dns" (too long) for "interface"' => [
				'proxy' => [
					'proxyid' => 'update_passive_defaults',
					'interface' => [
						'dns' => str_repeat('d', DB::getFieldLength('interface', 'dns') + 1)
					]
				],
				'expected_error' => 'Invalid parameter "/1/interface/dns": value is too long.'
			],
			'Test proxy.update: invalid "port" (string) for "interface"' => [
				'proxy' => [
					'proxyid' => 'update_passive_defaults',
					'interface' => [
						'port' => 'abc'
					]
				],
				'expected_error' => 'Invalid parameter "/1/interface/port": an integer is expected.'
			],
			'Test proxy.update: invalid "port" (not in range) for "interface"' => [
				'proxy' => [
					'proxyid' => 'update_passive_defaults',
					'interface' => [
						'port' => self::INVALID_NUMBER
					]
				],
				'expected_error' =>
					'Invalid parameter "/1/interface/port": value must be one of 0-'.ZBX_MAX_PORT_NUMBER.'.'
			],
			'Test proxy.update: invalid "interface" (not empty)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'interface' => [
						'port' => 12345
					]
				],
				'expected_error' =>
					'Invalid parameter "/1/interface": should be empty.'
			],

			// Check "tls_connect".
			'Test proxy.update: invalid "tls_connect" (string)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'tls_connect' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_connect": an integer is expected.'
			],
			'Test proxy.update: invalid "tls_connect" (not in range) for active proxy' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'tls_connect' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/tls_connect": value must be '.HOST_ENCRYPTION_NONE.'.'
			],
			'Test proxy.update: invalid "tls_connect" (not in range) for passive proxy' => [
				'proxy' => [
					'proxyid' => 'update_passive_defaults',
					'tls_connect' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/tls_connect": value must be one of '.
					implode(', ', [HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_PSK, HOST_ENCRYPTION_CERTIFICATE]).'.'
			],

			// Check "tls_accept".
			'Test proxy.update: invalid "tls_accept" (string)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'tls_accept' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_accept": an integer is expected.'
			],
			'Test proxy.update: invalid "tls_accept" (not in range) for active proxy' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'tls_accept' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/tls_accept": value must be one of '.HOST_ENCRYPTION_NONE.'-'.
					(HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE).'.'
			],
			'Test proxy.update: invalid "tls_accept" (not in range) for passive proxy' => [
				'proxy' => [
					'proxyid' => 'update_passive_defaults',
					'tls_accept' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/tls_accept": value must be '.HOST_ENCRYPTION_NONE.'.'
			],

			// Check "tls_psk_identity".
			'Test proxy.update: invalid "tls_psk_identity" (bool)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'tls_psk_identity' => false
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": a character string is expected.'
			],
			'Test proxy.update: invalid "tls_psk_identity" (string) for active proxy #1' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'tls_psk_identity' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value must be empty.'
			],
			'Test proxy.update: invalid "tls_psk_identity" (string) for active proxy #2' => [
				'proxy' => [
					'proxyid' => 'update_active_cert',
					'tls_psk_identity' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value must be empty.'
			],
			'Test proxy.update: invalid "tls_psk_identity" (empty string) for active proxy #1' => [
				'proxy' => [
					'proxyid' => 'update_active_psk',
					'tls_psk_identity' => ''
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": cannot be empty.'
			],
			'Test proxy.update: invalid "tls_psk_identity" (empty string) for active proxy #2' => [
				'proxy' => [
					'proxyid' => 'update_active_any',
					'tls_psk_identity' => ''
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": cannot be empty.'
			],
			'Test proxy.update: invalid "tls_psk_identity" (too long) for active proxy #1' => [
				'proxy' => [
					'proxyid' => 'update_active_psk',
					'tls_psk_identity' => str_repeat('i', DB::getFieldLength('hosts', 'tls_psk_identity') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value is too long.'
			],
			'Test proxy.update: invalid "tls_psk_identity" (too long) for active proxy #2' => [
				'proxy' => [
					'proxyid' => 'update_active_any',
					'tls_psk_identity' => str_repeat('i', DB::getFieldLength('hosts', 'tls_psk_identity') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value is too long.'
			],
			'Test proxy.update: invalid "tls_psk_identity" (string) for passive proxy #1' => [
				'proxy' => [
					'proxyid' => 'update_passive_defaults',
					'tls_psk_identity' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value must be empty.'
			],
			'Test proxy.update: invalid "tls_psk_identity" (string) for passive proxy #2' => [
				'proxy' => [
					'proxyid' => 'update_passive_cert',
					'tls_psk_identity' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value must be empty.'
			],
			'Test proxy.update: invalid "tls_psk_identity" (empty string) for passive proxy' => [
				'proxy' => [
					'proxyid' => 'update_passive_psk',
					'tls_psk_identity' => ''
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": cannot be empty.'
			],
			'Test proxy.update: invalid "tls_psk_identity" (too long) for passive proxy' => [
				'proxy' => [
					'proxyid' => 'update_passive_psk',
					'tls_psk_identity' => str_repeat('i', DB::getFieldLength('hosts', 'tls_psk_identity') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value is too long.'
			]
		];
	}

	/**
	 * Data provider for proxy.update. Array contains valid proxy parameters.
	 *
	 * @return array
	 */
	public static function getProxyUpdateDataValid(): array {
		return [
			'Test proxy.update: update single proxy without changes' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults'
				],
				'expected_error' => null
			],
			'Test proxy.update: update multiple proxies' => [
				'proxy' => [
					[
						'proxyid' => 'update_active_defaults',
						'host' => 'API test proxy.update - active proxy updated',
						'description' => 'Active proxy'
					],
					[
						'proxyid' => 'update_passive_defaults',
						'host' => 'API test proxy.update - passive proxy updated',
						'description' => 'Passive proxy'
					]
				],
				'expected_error' => null
			],

			// Check proxy can be assigned to host.
			'Test proxy.update: assign proxy to single host' => [
				'proxy' => [
					'proxyid' => 'update_hosts',
					'hosts' => [
						['hostid' => 'without_proxy_1']
					]
				],
				'expected_error' => null
			],
			'Test proxy.update: assign proxy to multiple hosts' => [
				'proxy' => [
					'proxyid' => 'update_hosts',
					'hosts' => [
						['hostid' => 'without_proxy_1'],
						['hostid' => 'without_proxy_2']
					]
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * Test proxy.update method.
	 *
	 * @dataProvider getProxyUpdateDataInvalid
	 * @dataProvider getProxyUpdateDataValid
	 */
	public function testProxy_Update(array $proxies, ?string $expected_error): void {
		// Accept single and multiple proxies just like API method. Work with multidimensional array in result.
		if (!array_key_exists(0, $proxies)) {
			$proxies = zbx_toArray($proxies);
		}

		// Replace ID placeholders with real IDs.
		foreach ($proxies as &$proxy) {
			$proxy = self::resolveIds($proxy);
		}
		unset($proxy);

		$sql_proxies = 'SELECT NULL FROM hosts h WHERE '.dbConditionInt('h.status', [
			HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE
		]);
		$old_hash_proxies = CDBHelper::getHash($sql_proxies);

		if ($expected_error === null) {
			$proxyids = array_column($proxies, 'proxyid');
			$db_proxies = $this->getProxies($proxyids);

			$this->call('proxy.update', $proxies, $expected_error);

			$proxies_upd = $this->getProxies($proxyids);

			// Compare records from DB before and after API call.
			foreach ($proxies as $proxy) {
				$db_proxy = $db_proxies[$proxy['proxyid']];
				$proxy_upd = $proxies_upd[$proxy['proxyid']];

				// Check "host".
				$this->assertNotEmpty($proxy_upd['host']);

				if (array_key_exists('host', $proxy)) {
					$this->assertSame($proxy['host'], $proxy_upd['host']);
				}
				else {
					$this->assertSame($db_proxy['host'], $proxy_upd['host']);
				}

				// Check "status".
				if (array_key_exists('status', $proxy)) {
					$this->assertEquals($proxy['status'], $proxy_upd['status']);
				}
				else {
					// Status has not changed.
					$this->assertEquals($db_proxy['status'], $proxy_upd['status']);
				}

				// Check "description".
				if (array_key_exists('description', $proxy)) {
					$this->assertSame($proxy['description'], $proxy_upd['description']);
				}
				else {
					$this->assertSame($db_proxy['description'], $proxy_upd['description']);
				}

				// Check "proxy_address".
				if (array_key_exists('proxy_address', $proxy)) {
					$this->assertSame($proxy['proxy_address'], $proxy_upd['proxy_address']);
				}
				else {
					$this->assertSame($db_proxy['proxy_address'], $proxy_upd['proxy_address']);
				}

				// Check hosts.
				if (array_key_exists('hosts', $proxy)) {
					if ($proxy['hosts']) {
						$this->assertNotEmpty($proxy_upd['hosts']);
						$this->assertEqualsCanonicalizing($proxy['hosts'], $proxy_upd['hosts']);
					}
					else {
						$this->assertEmpty($proxy_upd['hosts']);
					}
				}
				else {
					$this->assertEqualsCanonicalizing($db_proxy['hosts'], $proxy_upd['hosts']);
				}
			}

			// Restore proxy original data after each test.
			$this->restoreProxies($db_proxies);
		}
		else {
			// Call method and make sure it really returns the error.
			$this->call('proxy.update', $proxies, $expected_error);

			// Make sure nothing has changed as well.
			$this->assertSame($old_hash_proxies, CDBHelper::getHash($sql_proxies));
		}
	}

	/**
	 * Data provider for proxy.delete. Array contains invalid proxies that are not possible to delete.
	 *
	 * @return array
	 */
	public static function getProxyDeleteDataInvalid(): array {
		return [
			// Check proxy IDs.
			'Test proxy.delete: empty ID' => [
				'proxyids' => [''],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			'Test proxy.delete: non-existent ID' => [
				'proxyids' => [self::INVALID_NUMBER],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Test proxy.delete: with two same IDs' => [
				'proxyids' => [0, 0],
				'expected_error' => 'Invalid parameter "/2": value (0) already exists.'
			],

			// Check if deleted proxies used to monitor hosts.
			'Test proxy.delete: used in host' => [
				'proxyids' => ['delete_used_in_host'],
				'expected_error' =>
					'Host "API test host - with proxy" is monitored by proxy "API test proxy.delete - used in hosts".'
			],

			// Check if deleted proxies used in actions.
			'Test proxy.delete: used in action' => [
				'proxyids' => ['delete_used_in_action'],
				'expected_error' =>
					'Proxy "API test proxy.delete - used in actions" is used by action "API test discovery action".'
			],

			// Check if deleted proxies used in network discovery rules.
			'Test proxy.delete: used in discovery rule' => [
				'proxyids' => ['delete_used_in_discovery'],
				'expected_error' => 'Proxy "API test proxy.delete - used in discovery rules" is used by discovery rule "API test discovery rule".'
			]
		];
	}

	/**
	 * Data provider for proxy.delete. Array contains valid proxies.
	 *
	 * @return array
	 */
	public static function getProxyDeleteDataValid(): array {
		return [
			'Test proxy.delete: delete single' => [
				'proxy' => ['delete_single'],
				'expected_error' => null
			],
			'Test proxy.delete: delete multiple' => [
				'proxy' => [
					'delete_multiple_1',
					'delete_multiple_2'
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * Test proxy.delete method.
	 *
	 * @dataProvider getProxyDeleteDataInvalid
	 * @dataProvider getProxyDeleteDataValid
	 */
	public function testProxy_Delete(array $proxyids, ?string $expected_error): void {
		// Replace ID placeholders with real IDs.
		foreach ($proxyids as &$proxyid) {
			if (self::isValidIdPlaceholder($proxyid)) {
				$proxyid = self::$data['proxyids'][$proxyid];
			}
		}
		unset($proxyid);

		$sql_proxies = 'SELECT NULL FROM hosts h WHERE '.dbConditionInt('h.status', [
			HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE
		]);
		$old_hash_proxies = CDBHelper::getHash($sql_proxies);

		$this->call('proxy.delete', $proxyids, $expected_error);

		if ($expected_error === null) {
			$this->assertNotSame($old_hash_proxies, CDBHelper::getHash($sql_proxies));
			$this->assertEquals(0, CDBHelper::getCount(
				'SELECT h.hostid FROM hosts h WHERE '.dbConditionId('h.hostid', $proxyids)
			));

			// proxy.delete checks if given "proxyid" exists, so they need to be removed from self::$data['proxyids']
			foreach ($proxyids as $proxyid) {
				$key = array_search($proxyid, self::$data['proxyids']);
				if ($key !== false) {
					unset(self::$data['proxyids'][$key]);
				}
			}
		}
		else {
			$this->assertSame($old_hash_proxies, CDBHelper::getHash($sql_proxies));
		}
	}

	/**
	 * Get the original proxies before update.
	 *
	 * @param array $proxyids
	 *
	 * @return array
	 */
	private function getProxies(array $proxyids): array {
		$response = $this->call('proxy.get', [
			'output' => ['proxyid', 'host', 'status', 'description', 'lastaccess', 'tls_connect', 'tls_accept',
				'tls_issuer', 'tls_subject', 'proxy_address', 'auto_compress', 'version', 'compatibility'
			],
			'selectHosts' => ['hostid'],
			'selectInterface' => ['useip', 'ip', 'dns', 'port'],
			'proxyids' => $proxyids,
			'preservekeys' => true,
			'nopermissions' => true
		]);

		$options = [
			'output' => ['hostid', 'tls_psk_identity', 'tls_psk'],
			'filter' => ['hostid' => $proxyids]
		];
		$db_proxies = DBselect(DB::makeSql('hosts', $options));

		while ($db_proxy = DBfetch($db_proxies)) {
			$response['result'][$db_proxy['hostid']]['tls_psk_identity'] = $db_proxy['tls_psk_identity'];
			$response['result'][$db_proxy['hostid']]['tls_psk'] = $db_proxy['tls_psk'];
		}

		return $response['result'];
	}

	/**
	 * Restore proxies to their original state.
	 *
	 * @param array $proxies
	 */
	private function restoreProxies(array $proxies): void {
		$this->call('proxy.update', $proxies);
	}

	/**
	 * Delete all created data after test.
	 */
	public static function clearData(): void {
		// Delete actions.
		CDataHelper::call('action.delete', self::$data['actionids']);

		// Delete discovery rules.
		CDataHelper::call('drule.delete', self::$data['druleids']);

		// Delete hosts.
		CDataHelper::call('host.delete', array_values(self::$data['hostids']));

		// Delete host groups.
		CDataHelper::call('hostgroup.delete', self::$data['groupids']);

		// Delete proxies.
		$proxyids = array_values(self::$data['proxyids']);
		$proxyids = array_merge($proxyids, self::$data['created']);
		CDataHelper::call('proxy.delete', $proxyids);
	}

	/**
	 * Helper method to convert placeholders to real IDs.
	 *
	 * @param array $request
	 *
	 * @return array
	 */
	private static function resolveIds(array $request): array {
		if (array_key_exists('proxyids', $request)) {
			if (is_array($request['proxyids'])) {
				foreach ($request['proxyids'] as &$id) {
					if (self::isValidIdPlaceholder($id)) {
						$id = self::$data['proxyids'][$id];
					}
				}
				unset($id);
			}
			elseif (self::isValidIdPlaceholder($request['proxyids'])) {
				$request['proxyids'] = self::$data['proxyids'][$request['proxyids']];
			}
		}
		elseif (array_key_exists('proxyid', $request) && self::isValidIdPlaceholder($request['proxyid'])) {
			$request['proxyid'] = self::$data['proxyids'][$request['proxyid']];
		}

		if (array_key_exists('hosts', $request) && is_array($request['hosts'])) {
			foreach ($request['hosts'] as &$host) {
				if (is_array($host) && array_key_exists('hostid', $host)
						&& self::isValidIdPlaceholder($host['hostid'])) {
					$host['hostid'] = self::$data['hostids'][$host['hostid']];
				}
			}
			unset($host);
		}

		return $request;
	}

	/**
	 * Helper method to check ID placeholder.
	 *
	 * @param $id
	 *
	 * @return bool
	 */
	private static function isValidIdPlaceholder($id): bool {
		// Do not compare != 0 (it will not work) or !== 0 or !== '0' (avoid type check here).
		return !is_array($id) && $id != '0' && $id !== '' && $id !== null && $id != self::INVALID_NUMBER;
	}
}
