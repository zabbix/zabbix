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
				'name' => 'API test proxy.get - active',
				'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
			],
			'get_passive_defaults' => [
				'name' => 'API test proxy.get - passive',
				'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
				'address' => '127.0.0.1',
				'port' => '10050'
			],
			'get_version_undefined' => [
				'name' => 'API test proxy.get for filter - version undefined',
				'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
			],
			'get_version_current' => [
				'name' => 'API test proxy.get for filter - version current',
				'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
			],
			'get_version_outdated' => [
				'name' => 'API test proxy.get for filter - version outdated',
				'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
			],
			'get_version_unsupported' => [
				'name' => 'API test proxy.get for filter - version unsupported',
				'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
			],
			'update_active_defaults' => [
				'name' => 'API test proxy.update - active defaults',
				'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
			],
			'update_passive_defaults' => [
				'name' => 'API test proxy.update - passive defaults',
				'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
				'address' => '127.0.0.1',
				'port' => '10050'
			],
			'update_active_psk' => [
				'name' => 'API test proxy.update - active with PSK-based connections from proxy',
				'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
				'tls_accept' => HOST_ENCRYPTION_PSK,
				'tls_psk_identity' => 'Test PSK',
				'tls_psk' => '9b8eafedfaae00cece62e85d5f4792c7d9c9bcc851b23216a1d300311cc4f7cb'
			],
			'update_active_cert' => [
				'name' => 'API test proxy.update - active with certificate-based connections from proxy',
				'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
				'tls_accept' => HOST_ENCRYPTION_CERTIFICATE
			],
			'update_active_any' => [
				'name' => 'API test proxy.update - active with any connections from proxy',
				'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
				'tls_accept' => HOST_ENCRYPTION_NONE + HOST_ENCRYPTION_PSK + HOST_ENCRYPTION_CERTIFICATE,
				'tls_psk_identity' => 'Test PSK',
				'tls_psk' => '9b8eafedfaae00cece62e85d5f4792c7d9c9bcc851b23216a1d300311cc4f7cb'
			],
			'update_passive_dns' => [
				'name' => 'API test proxy.update - passive with DNS name',
				'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
				'address' => 'localhost',
				'port' => '10050'
			],
			'update_passive_ip' => [
				'name' => 'API test proxy.update - passive with IP address',
				'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
				'address' => '127.0.0.1',
				'port' => '10050'
			],
			'update_passive_psk' => [
				'name' => 'API test proxy.update - passive with PSK-based connections to proxy',
				'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
				'address' => '127.0.0.1',
				'port' => '10050',
				'tls_connect' => HOST_ENCRYPTION_PSK
			],
			'update_passive_cert' => [
				'name' => 'API test proxy.update - passive with certificate-based connections to proxy',
				'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
				'address' => '127.0.0.1',
				'port' => '10050',
				'tls_connect' => HOST_ENCRYPTION_CERTIFICATE
			],
			'update_hosts' => [
				'name' => 'API test proxy.update - hosts',
				'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
			],
			'delete_single' => [
				'name' => 'API test proxy.delete - single',
				'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
			],
			'delete_multiple_1' => [
				'name' => 'API test proxy.delete - multiple 1',
				'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
			],
			'delete_multiple_2' => [
				'name' => 'API test proxy.delete - multiple 2',
				'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
			],
			'delete_used_in_host' => [
				'name' => 'API test proxy.delete - used in hosts',
				'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
			],
			'delete_used_in_action' => [
				'name' => 'API test proxy.delete - used in actions',
				'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
			],
			'delete_used_in_discovery' => [
				'name' => 'API test proxy.delete - used in discovery rules',
				'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
			],
			'select_hosts_extend' => [
				'name' => 'API test proxy - verify fields returned with selectHosts extend',
				'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
			]
		];
		$db_proxies = CDataHelper::call('proxy.create', array_values($proxies));
		$this->assertArrayHasKey('proxyids', $db_proxies, __FUNCTION__.'() failed: Could not create proxies.');

		self::$data['proxyids'] = array_combine(array_keys($proxies), $db_proxies['proxyids']);

		// Manually update "proxy_rtdata" table.
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
				'where' => ['proxyid' => self::$data['proxyids'][$id_placeholder]]
			];
		}

		DB::update('proxy_rtdata', $upd_proxy_rtdata);
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
				'proxyid' => self::$data['proxyids']['delete_used_in_host'],
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
			],
			'select_fields_host' => [
				'host' => 'host_fields_host',
				'name' => 'API test host - for selectHosts with extend',
				'proxyid' => self::$data['proxyids']['select_hosts_extend'],
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
			'proxyid' => self::$data['proxyids']['delete_used_in_discovery'],
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

			// Check "name".
			'Test proxy.create: missing "host"' => [
				'proxy' => [
					'description' => ''
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "name" is missing.'
			],
			'Test proxy.create: invalid "host" (empty string)' => [
				'proxy' => [
					'name' => ''
				],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			'Test proxy.create: invalid "host" (UTF-8 string)' => [
				'proxy' => [
					'name' => 'АПИ прокси УТФ-8'
				],
				'expected_error' => 'Invalid parameter "/1/name": invalid host name.'
			],
			'Test proxy.create: invalid "host" (does not match naming pattern)' => [
				'proxy' => [
					'name' => 'API create proxy?'
				],
				'expected_error' => 'Invalid parameter "/1/name": invalid host name.'
			],
			'Test proxy.create: invalid "host" (too long)' => [
				'proxy' => [
					'name' => str_repeat('h', DB::getFieldLength('proxy', 'name') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/name": value is too long.'
			],
			'Test proxy.create: multiple proxies with the same "host"' => [
				'proxy' => [
					[
						'name' => 'API create proxy',
						'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
					],
					[
						'name' => 'API create proxy',
						'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
						'address' => '127.0.0.1',
						'port' => '12345'
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (name)=(API create proxy) already exists.'
			],
			'Test proxy.create: invalid "host" (duplicate)' => [
				'proxy' => [
					'name' => 'API test proxy.get - active',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
				],
				'expected_error' => 'Proxy "API test proxy.get - active" already exists.'
			],

			// Check "operating_mode".
			'Test proxy.create: missing "operating_mode"' => [
				'proxy' => [
					'name' => 'API create proxy'
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "operating_mode" is missing.'
			],
			'Test proxy.create: invalid "operating_mode" (string)' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/operating_mode": an integer is expected.'
			],
			'Test proxy.create: invalid "operating_mode" (not in range)' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/operating_mode": value must be one of '.
					implode(', ', [PROXY_OPERATING_MODE_ACTIVE, PROXY_OPERATING_MODE_PASSIVE]).'.'
			],

			// Check "description".
			'Test proxy.create: invalid "description" (bool)' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'description' => false
				],
				'expected_error' => 'Invalid parameter "/1/description": a character string is expected.'
			],
			'Test proxy.create: invalid "description" (too long)' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'description' => str_repeat('d', DB::getFieldLength('proxy', 'description') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/description": value is too long.'
			],

			// Check "allowed_addresses".
			'Test proxy.create: invalid "allowed_addresses" (bool)' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'allowed_addresses' => false
				],
				'expected_error' => 'Invalid parameter "/1/allowed_addresses": a character string is expected.'
			],
			'Test proxy.create: invalid "allowed_addresses" (IP address range)' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'allowed_addresses' => '192.168.0-255.0/30'
				],
				'expected_error' => 'Invalid parameter "/1/allowed_addresses": incorrect address starting from "/30".'
			],
			'Test proxy.create: invalid "allowed_addresses" (IPv6 address range)' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'allowed_addresses' => '::ff-0ffff'
				],
				'expected_error' => 'Invalid parameter "/1/allowed_addresses": incorrect address starting from "::ff-0ffff".'
			],
			'Test proxy.create: invalid "allowed_addresses" (user macro)' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'allowed_addresses' => '{$MACRO}'
				],
				'expected_error' => 'Invalid parameter "/1/allowed_addresses": incorrect address starting from "{$MACRO}".'
			],
			'Test proxy.create: invalid "allowed_addresses" (too long)' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'allowed_addresses' => str_repeat('a', DB::getFieldLength('proxy', 'allowed_addresses') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/allowed_addresses": value is too long.'
			],

			// Check "hosts".
			'Test proxy.create: invalid "hosts" (string)' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'hosts' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/hosts": an array is expected.'
			],
			'Test proxy.create: invalid "hosts" (array with string)' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'hosts' => ['abc']
				],
				'expected_error' => 'Invalid parameter "/1/hosts/1": an array is expected.'
			],
			'Test proxy.create: missing "hostid" for "hosts"' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'hosts' => [
						[]
					]
				],
				'expected_error' => 'Invalid parameter "/1/hosts/1": the parameter "hostid" is missing.'
			],
			'Test proxy.create: unexpected parameter for "hosts"' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'hosts' => [
						['abc' => '']
					]
				],
				'expected_error' => 'Invalid parameter "/1/hosts/1": unexpected parameter "abc".'
			],
			'Test proxy.create: invalid "hostid" (empty string) for "hosts"' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'hosts' => [
						['hostid' => '']
					]
				],
				'expected_error' => 'Invalid parameter "/1/hosts/1/hostid": a number is expected.'
			],
			'Test proxy.create: invalid "hostid" (non-existent) for "hosts"' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'hosts' => [
						['hostid' => self::INVALID_NUMBER]
					]
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Test proxy.create: invalid "hostid" (duplicate) for "hosts"' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'hosts' => [
						['hostid' => 0],
						['hostid' => 0]
					]
				],
				'expected_error' => 'Invalid parameter "/1/hosts/2": value (hostid)=(0) already exists.'
			],

			// Check "interface".
			'Test proxy.create: invalid parameter "interface" 1' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'interface' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "interface".'
			],
			'Test proxy.create: invalid parameter "interface" 2' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
					'interface' => [
						'use_ip' => INTERFACE_USE_IP,
						'ip' => '127.0.0.1',
						'dns' => 'localhost',
						'port' => '10050'
					]
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "interface".'
			],
			'Test proxy.create: empty "address" and "port" for passive proxy' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
					'address' => '',
					'port' => ''
				],
				'expected_error' => 'Invalid parameter "/1/address": cannot be empty.'
			],
			'Test proxy.create: empty "port" for passive proxy' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
					'address' => '127.0.0.1',
					'port' => ''
				],
				'expected_error' => 'Invalid parameter "/1/port": cannot be empty.'
			],
			'Test proxy.create: invalid "port" (string) for passive proxy' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
					'address' => '127.0.0.1',
					'port' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/port": an integer is expected.'
			],
			'Test proxy.create: invalid "address" (too long) for passive proxy' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
					'address' => str_repeat('i', DB::getFieldLength('proxy', 'address') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/address": value is too long.'
			],
			'Test proxy.create: invalid "address" (bool) for passive proxy' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
					'address' => false
				],
				'expected_error' => 'Invalid parameter "/1/address": a character string is expected.'
			],
			'Test proxy.create: invalid parameter "address" (string) for passive proxy' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
					'address' => 'http://',
					'port' => '10050'
				],
				'expected_error' => 'Invalid parameter "/1/address": an IP or DNS is expected.'
			],
			'Test proxy.create: invalid "port" (not in range) for passive proxy' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
					'address' => '127.0.0.1',
					'port' => self::INVALID_NUMBER
				],
				'expected_error' =>	'Invalid parameter "/1/port": value must be one of 0-'.ZBX_MAX_PORT_NUMBER.'.'
			],
			'Test proxy.create: invalid "address" (not empty) for active proxy' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'address' => 'localhost'
				],
				'expected_error' => 'Invalid parameter "/1/address": value must be "127.0.0.1".'
			],
			'Test proxy.create: invalid "port" (not empty int) for active proxy' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'port' => 12345
				],
				'expected_error' =>	'Invalid parameter "/1/port": a character string is expected.'
			],
			'Test proxy.create: invalid "port" (not empty string) for active proxy' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'port' => '12345'
				],
				'expected_error' =>	'Invalid parameter "/1/port": value must be "10051".'
			],

			// Check "tls_connect".
			'Test proxy.create: invalid "tls_connect" (string)' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'tls_connect' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_connect": an integer is expected.'
			],
			'Test proxy.create: invalid "tls_connect" (not in range) for active proxy' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'tls_connect' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/tls_connect": value must be '.HOST_ENCRYPTION_NONE.'.'
			],
			'Test proxy.create: invalid "tls_connect" (not in range) for passive proxy' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
					'address' => '127.0.0.1',
					'port' => '10050',
					'tls_connect' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/tls_connect": value must be one of '.
					implode(', ', [HOST_ENCRYPTION_NONE, HOST_ENCRYPTION_PSK, HOST_ENCRYPTION_CERTIFICATE]).'.'
			],

			// Check "tls_accept".
			'Test proxy.create: invalid "tls_accept" (string)' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'tls_accept' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_accept": an integer is expected.'
			],
			'Test proxy.create: invalid "tls_accept" (not in range) for active proxy' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'tls_accept' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/tls_accept": value must be one of '.HOST_ENCRYPTION_NONE.'-'.
					(HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK | HOST_ENCRYPTION_CERTIFICATE).'.'
			],
			'Test proxy.create: invalid "tls_accept" (not in range) for passive proxy' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
					'address' => '127.0.0.1',
					'port' => '10050',
					'tls_accept' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/tls_accept": value must be '.HOST_ENCRYPTION_NONE.'.'
			],

			// Check "tls_psk_identity".
			'Test proxy.create: invalid "tls_psk_identity" (bool)' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'tls_psk_identity' => false
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": a character string is expected.'
			],
			'Test proxy.create: invalid "tls_psk_identity" (string) for active proxy #1' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'tls_psk_identity' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value must be empty.'
			],
			'Test proxy.create: invalid "tls_psk_identity" (string) for active proxy #2' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_NONE,
					'tls_psk_identity' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value must be empty.'
			],
			'Test proxy.create: invalid "tls_psk_identity" (string) for active proxy #3' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_CERTIFICATE,
					'tls_psk_identity' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value must be empty.'
			],
			'Test proxy.create: invalid "tls_psk_identity" (string) for passive proxy #1' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
					'address' => '127.0.0.1',
					'port' => '10050',
					'tls_psk_identity' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value must be empty.'
			],
			'Test proxy.create: invalid "tls_psk_identity" (string) for passive proxy #2' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
					'address' => '127.0.0.1',
					'port' => '10050',
					'tls_connect' => HOST_ENCRYPTION_NONE,
					'tls_psk_identity' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value must be empty.'
			],
			'Test proxy.create: invalid "tls_psk_identity" (string) for passive proxy #3' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
					'address' => '127.0.0.1',
					'port' => '10050',
					'tls_connect' => HOST_ENCRYPTION_CERTIFICATE,
					'tls_psk_identity' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value must be empty.'
			],
			'Test proxy.create: invalid "tls_psk_identity" (empty string) for active proxy' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => ''
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": cannot be empty.'
			],
			'Test proxy.create: invalid "tls_psk_identity" (empty string) for passive proxy' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
					'address' => '127.0.0.1',
					'port' => '10050',
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => ''
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": cannot be empty.'
			],
			'Test proxy.create: invalid "tls_psk_identity" (too long) for active proxy' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => str_repeat('i', DB::getFieldLength('proxy', 'tls_psk_identity') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value is too long.'
			],
			'Test proxy.create: invalid "tls_psk_identity" (too long) for passive proxy' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
					'address' => '127.0.0.1',
					'port' => '10050',
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => str_repeat('i', DB::getFieldLength('proxy', 'tls_psk_identity') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value is too long.'
			],

			// Check "tls_psk".
			'Test proxy.create: invalid "tls_psk" (bool) for active proxy' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'tls_psk' => false
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": a character string is expected.'
			],
			'Test proxy.create: invalid "tls_psk" (string) for active proxy #1' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'tls_psk' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": value must be empty.'
			],
			'Test proxy.create: invalid "tls_psk" (string) for active proxy #2' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_NONE,
					'tls_psk' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": value must be empty.'
			],
			'Test proxy.create: invalid "tls_psk" (string) for active proxy #3' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_CERTIFICATE,
					'tls_psk' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": value must be empty.'
			],

			'Test proxy.create: invalid "tls_psk" (string) for passive proxy #1' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
					'address' => '127.0.0.1',
					'port' => '10050',
					'tls_connect' => HOST_ENCRYPTION_NONE,
					'tls_psk' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": value must be empty.'
			],
			'Test proxy.create: invalid "tls_psk" (string) for passive proxy #2' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
					'address' => '127.0.0.1',
					'port' => '10050',
					'tls_connect' => HOST_ENCRYPTION_CERTIFICATE,
					'tls_psk' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": value must be empty.'
			],
			'Test proxy.create: invalid "tls_psk" (too short) for active proxy' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_psk' => 'abc'
				],
				'expected_error' =>
					'Invalid parameter "/1/tls_psk": minimum length is 32 characters.'
			],
			'Test proxy.create: invalid "tls_psk" (too short) for passive proxy' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
					'address' => '127.0.0.1',
					'port' => '10050',
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'tls_psk' => 'abc'
				],
				'expected_error' =>
					'Invalid parameter "/1/tls_psk": minimum length is 32 characters.'
			],
			'Test proxy.create: invalid "tls_psk" (not PSK) for active proxy' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_psk' => str_repeat('a', 33)
				],
				'expected_error' =>
					'Invalid parameter "/1/tls_psk": an even number of hexadecimal characters is expected.'
			],
			'Test proxy.create: invalid "tls_psk" (not PSK) for passive proxy' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
					'address' => '127.0.0.1',
					'port' => '10050',
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'tls_psk' => str_repeat('a', 33)
				],
				'expected_error' =>
					'Invalid parameter "/1/tls_psk": an even number of hexadecimal characters is expected.'
			],

			// Check "tls_issuer".
			'Test proxy.create: invalid "tls_issuer" (bool) for active proxy' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'tls_issuer' => false
				],
				'expected_error' => 'Invalid parameter "/1/tls_issuer": a character string is expected.'
			],
			'Test proxy.create: invalid "tls_issuer" (not empty) for active proxy #1' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_NONE,
					'tls_issuer' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_issuer": value must be empty.'
			],
			'Test proxy.create: invalid "tls_issuer" (not empty) for active proxy #2' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_issuer' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_issuer": value must be empty.'
			],
			'Test proxy.create: invalid "tls_issuer" (not empty) for passive proxy #1' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
					'address' => '127.0.0.1',
					'port' => '10050',
					'tls_connect' => HOST_ENCRYPTION_NONE,
					'tls_issuer' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_issuer": value must be empty.'
			],
			'Test proxy.create: invalid "tls_issuer" (not empty) for passive proxy #2' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
					'address' => '127.0.0.1',
					'port' => '10050',
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'tls_issuer' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_issuer": value must be empty.'
			],
			'Test proxy.create: invalid "tls_issuer" (too long) for active proxy' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_CERTIFICATE,
					'tls_issuer' => str_repeat('i', DB::getFieldLength('proxy', 'tls_issuer') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/tls_issuer": value is too long.'
			],
			'Test proxy.create: invalid "tls_issuer" (too long) for passive proxy' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
					'address' => '127.0.0.1',
					'port' => '10050',
					'tls_connect' => HOST_ENCRYPTION_CERTIFICATE,
					'tls_issuer' => str_repeat('i', DB::getFieldLength('proxy', 'tls_issuer') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/tls_issuer": value is too long.'
			],

			// Check "tls_subject".
			'Test proxy.create: invalid "tls_subject" (not empty) for active proxy #1' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_NONE,
					'tls_subject' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_subject": value must be empty.'
			],
			'Test proxy.create: invalid "tls_subject" (not empty) for active proxy #2' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_subject' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_subject": value must be empty.'
			],
			'Test proxy.create: invalid "tls_subject" (not empty) for passive proxy #1' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
					'address' => '127.0.0.1',
					'port' => '10050',
					'tls_connect' => HOST_ENCRYPTION_NONE,
					'tls_subject' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_subject": value must be empty.'
			],
			'Test proxy.create: invalid "tls_subject" (not empty) for passive proxy #2' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
					'address' => '127.0.0.1',
					'port' => '10050',
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'tls_subject' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/tls_subject": value must be empty.'
			],
			'Test proxy.create: invalid "tls_subject" (too long) for active proxy' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE,
					'tls_accept' => HOST_ENCRYPTION_CERTIFICATE,
					'tls_subject' => str_repeat('i', DB::getFieldLength('proxy', 'tls_subject') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/tls_subject": value is too long.'
			],
			'Test proxy.create: invalid "tls_subject" (too long) for passive proxy' => [
				'proxy' => [
					'name' => 'API create proxy',
					'operating_mode' => PROXY_OPERATING_MODE_PASSIVE,
					'address' => '127.0.0.1',
					'port' => '10050',
					'tls_connect' => HOST_ENCRYPTION_CERTIFICATE,
					'tls_subject' => str_repeat('i', DB::getFieldLength('proxy', 'tls_subject') + 1)
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
					'name' => 'API create single proxy',
					'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
				],
				'expected_error' => null
			],
			'Test proxy.create: multiple proxies' => [
				'proxy' => [
					[
						'name' => 'API create first proxy',
						'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
					],
					[
						'name' => 'API create second proxy',
						'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
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

		$sql_proxies = 'SELECT NULL FROM proxy p';
		$old_hash_proxies = CDBHelper::getHash($sql_proxies);

		$result = $this->call('proxy.create', $proxies, $expected_error);

		if ($expected_error === null) {
			// Something was changed in DB.
			$this->assertNotSame($old_hash_proxies, CDBHelper::getHash($sql_proxies));
			$this->assertEquals(count($proxies), count($result['result']['proxyids']));

			// Add proxy IDs to create array, so they can be deleted after tests are complete.
			self::$data['created'] = array_merge(self::$data['created'], $result['result']['proxyids']);

			// Check individual fields according to each proxy operating_mode.
			foreach ($result['result']['proxyids'] as $num => $proxyid) {
				$db_proxies = $this->getProxies([$proxyid]);
				$db_proxy = $db_proxies[$proxyid];

				// Required fields.
				$this->assertNotEmpty($db_proxy['name']);
				$this->assertSame($proxies[$num]['name'], $db_proxy['name']);
				$this->assertEquals($proxies[$num]['operating_mode'], $db_proxy['operating_mode']);

				if (array_key_exists('description', $proxies[$num])) {
					$this->assertSame($proxies[$num]['description'], $db_proxy['description']);
				}
				else {
					$this->assertEmpty($db_proxy['description']);
				}

				if (array_key_exists('allowed_addresses', $proxies[$num])) {
					$this->assertSame($proxies[$num]['allowed_addresses'], $db_proxy['allowed_addresses']);
				}
				else {
					$this->assertSame($db_proxy['allowed_addresses'], DB::getDefault('proxy', 'allowed_addresses'));
				}

				if (array_key_exists('address', $proxies[$num])) {
					$this->assertSame($proxies[$num]['address'], $db_proxy['address']);
				}
				else {
					$this->assertSame($db_proxy['address'], DB::getDefault('proxy', 'address'));
				}

				if (array_key_exists('port', $proxies[$num])) {
					$this->assertSame($proxies[$num]['port'], $db_proxy['port'], 'port should match request');
				}
				else {
					$this->assertSame($db_proxy['port'], DB::getDefault('proxy', 'port'), 'port should match db');
				}

				if (array_key_exists('tls_accept', $proxies[$num])) {
					$this->assertSame($proxies[$num]['tls_accept'], $db_proxy['tls_accept']);
				}
				else {
					$this->assertSame($db_proxy['tls_accept'], DB::getDefault('proxy', 'tls_accept'));
				}

				if (array_key_exists('tls_connect', $proxies[$num])) {
					$this->assertSame($proxies[$num]['tls_connect'], $db_proxy['tls_connect']);
				}
				else {
					$this->assertSame($db_proxy['tls_connect'], DB::getDefault('proxy', 'tls_connect'));
				}

				if (array_key_exists('tls_issuer', $proxies[$num])) {
					$this->assertSame($proxies[$num]['tls_issuer'], $db_proxy['tls_issuer']);
				}
				else {
					$this->assertSame($db_proxy['tls_issuer'], DB::getDefault('proxy', 'tls_issuer'));
				}

				if (array_key_exists('tls_subject', $proxies[$num])) {
					$this->assertSame($proxies[$num]['tls_subject'], $db_proxy['tls_subject']);
				}
				else {
					$this->assertSame($db_proxy['tls_subject'], DB::getDefault('proxy', 'tls_subject'));
				}

				if (array_key_exists('tls_psk_identity', $proxies[$num])) {
					$this->assertSame($proxies[$num]['tls_psk_identity'], $db_proxy['tls_psk_identity']);
				}
				else {
					$this->assertSame($db_proxy['tls_psk_identity'], DB::getDefault('proxy', 'tls_psk_identity'));
				}

				if (array_key_exists('tls_psk', $proxies[$num])) {
					$this->assertSame($proxies[$num]['tls_psk'], $db_proxy['tls_psk']);
				}
				else {
					$this->assertSame($db_proxy['tls_psk'], DB::getDefault('proxy', 'tls_psk'));
				}

				if (array_key_exists('hosts', $proxies[$num])) {
					$this->assertEqualsCanonicalizing($proxies[$num]['hosts'], $db_proxy['hosts']);
				}
				else {
					$this->assertEmpty($db_proxy['hosts']);
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
				'expected_error' => 'Invalid parameter "/output/1": value must be one of "proxyid", "name", "operating_mode", "description", "allowed_addresses", "address", "port", "tls_connect", "tls_accept", "tls_issuer", "tls_subject", "lastaccess", "version", "compatibility".'
			],

			// Check write-only fields are not returned.
			'Test proxy.get: write-only field "tls_psk_identity"' => [
				'request' => [
					'output' => ['tls_psk_identity']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/output/1": value must be one of "proxyid", "name", "operating_mode", "description", "allowed_addresses", "address", "port", "tls_connect", "tls_accept", "tls_issuer", "tls_subject", "lastaccess", "version", "compatibility".'
			],
			'Test proxy.get: write-only field "tls_psk"' => [
				'request' => [
					'output' => ['tls_psk']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/output/1": value must be one of "proxyid", "name", "operating_mode", "description", "allowed_addresses", "address", "port", "tls_connect", "tls_accept", "tls_issuer", "tls_subject", "lastaccess", "version", "compatibility".'
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
				'expected_error' => 'Invalid parameter "/selectHosts/1": value must be one of "hostid", "host", "status", "ipmi_authtype", "ipmi_privilege", "ipmi_username", "ipmi_password", "maintenanceid", "maintenance_status", "maintenance_type", "maintenance_from", "name", "flags", "description", "tls_connect", "tls_accept", "tls_issuer", "tls_subject", "inventory_mode", "active_available".'
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
				'expected_error' => 'Invalid parameter "/sortfield/1": value must be one of "proxyid", "name", "operating_mode".'
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

			// Check fields from "proxy_rtdata" table are returned.
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

			// Filter by proxy operating_mode.
			'Test proxy.get: filter by "operating_mode"' => [
				'request' => [
					'output' => ['name', 'operating_mode'],
					'proxyids' => ['get_active_defaults', 'get_passive_defaults'],
					'filter' => [
						'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
					]
				],
				'expected_result' => [
					[
						'name' => 'API test proxy.get - active',
						'operating_mode' => (string) PROXY_OPERATING_MODE_ACTIVE
					]
				],
				'expected_error' => null
			],

			// Filter by Zabbix version.
			'Test proxy.get: filter by "version"' => [
				'request' => [
					'output' => ['name', 'version'],
					'proxyids' => ['get_version_current', 'get_version_outdated', 'get_version_unsupported'],
					'filter' => [
						'version' => ['60000', '60200', '60400']
					]
				],
				'expected_result' => [
					[
						'name' => 'API test proxy.get for filter - version current',
						'version' => '60400'
					],
					[
						'name' => 'API test proxy.get for filter - version outdated',
						'version' => '60200'
					]
				],
				'expected_error' => null
			],

			// Filter by version compatibility.
			'Test proxy.get: filter by "compatibility"' => [
				'request' => [
					'output' => ['name', 'compatibility'],
					'proxyids' => ['get_version_current', 'get_version_outdated', 'get_version_unsupported'],
					'filter' => [
						'compatibility' => [ZBX_PROXY_VERSION_OUTDATED, ZBX_PROXY_VERSION_UNSUPPORTED]
					]
				],
				'expected_result' => [
					[
						'name' => 'API test proxy.get for filter - version outdated',
						'compatibility' => (string) ZBX_PROXY_VERSION_OUTDATED
					],
					[
						'name' => 'API test proxy.get for filter - version unsupported',
						'compatibility' => (string) ZBX_PROXY_VERSION_UNSUPPORTED
					]
				],
				'expected_error' => null
			],

			// Search by proxy name.
			'Test proxy.get: search by "name"' => [
				'request' => [
					'output' => ['name'],
					'search' => [
						'name' => 'API test proxy.get - active'
					]
				],
				'expected_result' => [
					['name' => 'API test proxy.get - active']
				],
				'expected_error' => null
			],

			// Filtering by incorrect data types.
			'Test proxy.get: invalid "operating_mode" (string) in "filter"' => [
				'request' => [
					'filter' => [
						'operating_mode' => 'abc'
					]
				],
				'expected_result' => [],
				'expected_error' => null
			],
			'Test proxy.get: invalid "operating_mode" in "filter"' => [
				'request' => [
					'filter' => [
						'operating_mode' => self::INVALID_NUMBER
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
			],
			'Test proxy.get: selectHosts=extend excludes proxyid' => [
				'request' => [
					'output' => [],
					'proxyids' => 'select_hosts_extend',
					'selectHosts' => API_OUTPUT_EXTEND
				],
				'expected_result' => [[
					'hosts' => [[
						'hostid' => 'select_fields_host',
						'host' => 'host_fields_host',
						'status' => DB::getDefault('hosts', 'status'),
						'ipmi_authtype' => DB::getDefault('hosts', 'ipmi_authtype'),
						'ipmi_privilege' => DB::getDefault('hosts', 'ipmi_privilege'),
						'ipmi_username' => DB::getDefault('hosts', 'ipmi_username'),
						'ipmi_password' => DB::getDefault('hosts', 'ipmi_password'),
						'maintenanceid' => '0',
						'maintenance_status' => DB::getDefault('hosts', 'maintenance_status'),
						'maintenance_type' => DB::getDefault('hosts', 'maintenance_type'),
						'maintenance_from' => DB::getDefault('hosts', 'maintenance_from'),
						'name' => 'API test host - for selectHosts with extend',
						'flags' => DB::getDefault('hosts', 'flags'),
						'description' => DB::getDefault('hosts', 'description'),
						'tls_connect' => DB::getDefault('hosts', 'tls_connect'),
						'tls_accept' => DB::getDefault('hosts', 'tls_accept'),
						'tls_issuer' => DB::getDefault('hosts', 'tls_issuer'),
						'tls_subject' => DB::getDefault('hosts', 'tls_subject'),
						'inventory_mode' => (string) HOST_INVENTORY_DISABLED,
						'active_available' => '0'
					]]
				]],
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
					'name' => 'API update proxy'
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

			// Check "operating_mode".
			'Test proxy.update: invalid "operating_mode" (string)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'operating_mode' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/operating_mode": an integer is expected.'
			],
			'Test proxy.update: invalid "operating_mode" (not in range)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'operating_mode' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/operating_mode": value must be one of '.
					implode(', ', [PROXY_OPERATING_MODE_ACTIVE, PROXY_OPERATING_MODE_PASSIVE]).'.'
			],

			// Check "host".
			'Test proxy.update: invalid "name" (bool)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'name' => false
				],
				'expected_error' => 'Invalid parameter "/1/name": a character string is expected.'
			],
			'Test proxy.update: invalid "name" (empty string)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'name' => ''
				],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			'Test proxy.update: invalid "name" (too long)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'name' => str_repeat('h', DB::getFieldLength('proxy', 'name') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/name": value is too long.'
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
					'description' => str_repeat('d', DB::getFieldLength('proxy', 'description') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/description": value is too long.'
			],

			// Check "allowed_address".
			'Test proxy.update: invalid "allowed_addresses" (bool)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'allowed_addresses' => false
				],
				'expected_error' => 'Invalid parameter "/1/allowed_addresses": a character string is expected.'
			],
			'Test proxy.update: invalid "allowed_addresses" (IP address range)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'allowed_addresses' => '192.168.0-255.0/30'
				],
				'expected_error' => 'Invalid parameter "/1/allowed_addresses": incorrect address starting from "/30".'
			],
			'Test proxy.update: invalid "allowed_addresses" (IPv6 address range)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'allowed_addresses' => '::ff-0ffff'
				],
				'expected_error' => 'Invalid parameter "/1/allowed_addresses": incorrect address starting from "::ff-0ffff".'
			],
			'Test proxy.update: invalid "allowed_addresses" (user macro)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'allowed_addresses' => '{$MACRO}'
				],
				'expected_error' => 'Invalid parameter "/1/allowed_addresses": incorrect address starting from "{$MACRO}".'
			],
			'Test proxy.update: invalid "allowed_addresses" (too long)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'allowed_addresses' => str_repeat('a', DB::getFieldLength('proxy', 'allowed_addresses') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/allowed_addresses": value is too long.'
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
			'Test proxy.update: unexpected parameter "interface"' => [
				'proxy' => [
					'proxyid' => 'update_passive_defaults',
					'interface' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "interface".'
			],
			'Test proxy.update: invalid "address" (bool) for "interface"' => [
				'proxy' => [
					'proxyid' => 'update_passive_defaults',
					'address' => false
				],
				'expected_error' => 'Invalid parameter "/1/address": a character string is expected.'
			],
			'Test proxy.update: invalid "port" (not in range) for passive proxy' => [
				'proxy' => [
					'proxyid' => 'update_passive_defaults',
					'port' => self::INVALID_NUMBER
				],
				'expected_error' => 'Invalid parameter "/1/port": value must be one of 0-'.ZBX_MAX_PORT_NUMBER.'.'
			],
			'Test proxy.update: invalid "address" (string) for passive proxy' => [
				'proxy' => [
					'proxyid' => 'update_passive_defaults',
					'address' => 'http://'
				],
				'expected_error' => 'Invalid parameter "/1/address": an IP or DNS is expected.'
			],
			'Test proxy.update: invalid "address" (too long) for passive proxy' => [
				'proxy' => [
					'proxyid' => 'update_passive_defaults',
					'address' => str_repeat('a', DB::getFieldLength('proxy', 'address') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/address": value is too long.'
			],
			'Test proxy.update: invalid "port" (bool) for passive proxy' => [
				'proxy' => [
					'proxyid' => 'update_passive_defaults',
					'port' => false
				],
				'expected_error' => 'Invalid parameter "/1/port": a number is expected.'
			],
			'Test proxy.update: invalid "port" (too long) for passive proxy' => [
				'proxy' => [
					'proxyid' => 'update_passive_defaults',
					'port' => str_repeat('d', DB::getFieldLength('proxy', 'port') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/port": value is too long.'
			],
			'Test proxy.update: invalid "port" (string) for passive proxy' => [
				'proxy' => [
					'proxyid' => 'update_passive_defaults',
					'port' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/port": an integer is expected.'
			],
			'Test proxy.update: invalid "port" (not empty int for active proxy)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'port' => 12345
				],
				'expected_error' =>	'Invalid parameter "/1/port": a character string is expected.'
			],
			'Test proxy.update: invalid "address" (not empty for active proxy)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'address' => 'localhost'
				],
				'expected_error' => 'Invalid parameter "/1/address": value must be "127.0.0.1".'
			],
			'Test proxy.update: invalid "port" (not empty string for active proxy)' => [
				'proxy' => [
					'proxyid' => 'update_active_defaults',
					'port' => '12345'
				],
				'expected_error' =>	'Invalid parameter "/1/port": value must be "10051".'
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
					'tls_psk_identity' => str_repeat('i', DB::getFieldLength('proxy', 'tls_psk_identity') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value is too long.'
			],
			'Test proxy.update: invalid "tls_psk_identity" (too long) for active proxy #2' => [
				'proxy' => [
					'proxyid' => 'update_active_any',
					'tls_psk_identity' => str_repeat('i', DB::getFieldLength('proxy', 'tls_psk_identity') + 1)
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
					'tls_psk_identity' => str_repeat('i', DB::getFieldLength('proxy', 'tls_psk_identity') + 1)
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
						'name' => 'API test proxy.update - active proxy updated',
						'description' => 'Active proxy'
					],
					[
						'proxyid' => 'update_passive_defaults',
						'name' => 'API test proxy.update - passive proxy updated',
						'description' => 'Passive proxy',
						'address' => 'localhost',
						'port' => '10051'
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

		$sql_proxies = 'SELECT NULL FROM proxy p';
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

				// Check "name".
				$this->assertNotEmpty($proxy_upd['name']);

				if (array_key_exists('name', $proxy)) {
					$this->assertSame($proxy['name'], $proxy_upd['name']);
				}
				else {
					$this->assertSame($db_proxy['name'], $proxy_upd['name']);
				}

				// Check "operating_mode".
				if (array_key_exists('operating_mode', $proxy)) {
					$this->assertEquals($proxy['operating_mode'], $proxy_upd['operating_mode']);
				}
				else {
					// operating_mode has not changed.
					$this->assertEquals($db_proxy['operating_mode'], $proxy_upd['operating_mode']);
				}

				// Check "description".
				if (array_key_exists('description', $proxy)) {
					$this->assertSame($proxy['description'], $proxy_upd['description']);
				}
				else {
					$this->assertSame($db_proxy['description'], $proxy_upd['description']);
				}

				// Check "allowed_address".
				if (array_key_exists('allowed_addresses', $proxy)) {
					$this->assertSame($proxy['allowed_addresses'], $proxy_upd['allowed_addresses']);
				}
				else {
					$this->assertSame($db_proxy['allowed_addresses'], $proxy_upd['allowed_addresses']);
				}

				// Check "address".
				if (array_key_exists('address', $proxy)) {
					$this->assertSame($proxy['address'], $proxy_upd['address']);
				}
				else {
					$this->assertSame($db_proxy['address'], $proxy_upd['address']);
				}

				// Check "port".
				if (array_key_exists('port', $proxy)) {
					$this->assertSame($proxy['port'], $proxy_upd['port']);
				}
				else {
					$this->assertSame($db_proxy['port'], $proxy_upd['port']);
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

		$sql_proxies = 'SELECT NULL FROM proxy p';
		$old_hash_proxies = CDBHelper::getHash($sql_proxies);

		$this->call('proxy.delete', $proxyids, $expected_error);

		if ($expected_error === null) {
			$this->assertNotSame($old_hash_proxies, CDBHelper::getHash($sql_proxies));
			$this->assertEquals(0, CDBHelper::getCount(
				'SELECT p.proxyid FROM proxy p WHERE '.dbConditionId('p.proxyid', $proxyids)
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
			'output' => ['proxyid', 'name', 'operating_mode', 'description', 'allowed_addresses', 'address', 'port',
				'tls_connect', 'tls_accept', 'tls_issuer', 'tls_subject', 'lastaccess', 'version', 'compatibility'
			],
			'selectHosts' => ['hostid'],
			'proxyids' => $proxyids,
			'preservekeys' => true,
			'nopermissions' => true
		]);

		$options = [
			'output' => ['proxyid', 'tls_psk_identity', 'tls_psk'],
			'filter' => ['proxyid' => $proxyids]
		];
		$db_proxies = DBselect(DB::makeSql('proxy', $options));

		while ($db_proxy = DBfetch($db_proxies)) {
			$response['result'][$db_proxy['proxyid']]['tls_psk_identity'] = $db_proxy['tls_psk_identity'];
			$response['result'][$db_proxy['proxyid']]['tls_psk'] = $db_proxy['tls_psk'];
		}

		return $response['result'];
	}

	/**
	 * Restore proxies to their original state.
	 *
	 * @param array $proxies
	 */
	private function restoreProxies(array $proxies): void {
		$rtdata_fields = array_flip(['lastaccess', 'version', 'compatibility']);
		$upd_proxy_rtdata = [];

		foreach ($proxies as &$proxy) {
			$upd_proxy_rtdata[] = [
				'values' => array_intersect_key($proxy, $rtdata_fields),
				'where' => ['proxyid' => $proxy['proxyid']]
			];
			$proxy = array_diff_key($proxy, $rtdata_fields);
		}
		unset($proxy);

		$this->call('proxy.update', $proxies);

		DB::update('proxy_rtdata', $upd_proxy_rtdata);
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
