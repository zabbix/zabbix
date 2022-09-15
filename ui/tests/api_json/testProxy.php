<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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

	private static $data = [
		'proxyids' => [
			'get_active_defaults' => null,
			'get_passive_defaults' => null,
			'get_version_undefined' => null,
			'get_version_current' => null,
			'get_version_outdated' => null,
			'get_version_unsupported' => null,
			'update_active_defaults' => null,
			'update_passive_defaults' => null,
			'update_active_psk' => null,
			'update_active_cert' => null,
			'update_active_any' => null,
			'update_passive_dns' => null,
			'update_passive_ip' => null,
			'update_passive_psk' => null,
			'update_passive_cert' => null,
			'update_hosts' => null,
			'delete_single' => null,
			'delete_multiple_1' => null,
			'delete_multiple_2' => null,
			'delete_host' => null,
			'delete_action' => null,
			'delete_discovery' => null
		],
		'groupids' => null,
		'hostids' => null,
		'actionids' => null,
		'druleids' => null,

		// Created proxies during proxy.create test (deleted at the end).
		'created' => []
	];

	/**
	 * Prepare data for tests. Create host groups, hosts, proxies, actions, discovery rules.
	 */
	public function prepareTestData(): void {
		// Create proxies.
		$proxies_data = [
			[
				'host' => 'API test proxy.get - active',
				'status' => HOST_STATUS_PROXY_ACTIVE
			],
			[
				'host' => 'API test proxy.get - passive',
				'status' => HOST_STATUS_PROXY_PASSIVE,
				'interface' => [
					'useip' => INTERFACE_USE_IP,
					'ip' => '127.0.0.1',
					'dns' => 'localhost',
					'port' => '10050'
				]
			],
			[
				'host' => 'API test proxy.get for filter - version undefined',
				'status' => HOST_STATUS_PROXY_ACTIVE
			],
			[
				'host' => 'API test proxy.get for filter - version current',
				'status' => HOST_STATUS_PROXY_ACTIVE
			],
			[
				'host' => 'API test proxy.get for filter - version outdated',
				'status' => HOST_STATUS_PROXY_ACTIVE
			],
			[
				'host' => 'API test proxy.get for filter - version unsupported',
				'status' => HOST_STATUS_PROXY_ACTIVE
			],
			[
				'host' => 'API test proxy.update - active defaults',
				'status' => HOST_STATUS_PROXY_ACTIVE
			],
			[
				'host' => 'API test proxy.update - passive defaults',
				'status' => HOST_STATUS_PROXY_PASSIVE,
				'interface' => [
					'useip' => INTERFACE_USE_IP,
					'ip' => '127.0.0.1',
					'dns' => 'localhost',
					'port' => '10050'
				]
			],
			[
				'host' => 'API test proxy.update - active with PSK-based connections from proxy',
				'status' => HOST_STATUS_PROXY_ACTIVE,
				'tls_accept' => HOST_ENCRYPTION_PSK,
				'tls_psk_identity' => 'Test PSK',
				'tls_psk' => '9b8eafedfaae00cece62e85d5f4792c7d9c9bcc851b23216a1d300311cc4f7cb'
			],
			[
				'host' => 'API test proxy.update - active with certificate-based connections from proxy',
				'status' => HOST_STATUS_PROXY_ACTIVE,
				'tls_accept' => HOST_ENCRYPTION_CERTIFICATE
			],
			[
				'host' => 'API test proxy.update - active with any connections from proxy',
				'status' => HOST_STATUS_PROXY_ACTIVE,
				'tls_accept' => HOST_ENCRYPTION_NONE + HOST_ENCRYPTION_PSK + HOST_ENCRYPTION_CERTIFICATE,
				'tls_psk_identity' => 'Test PSK',
				'tls_psk' => '9b8eafedfaae00cece62e85d5f4792c7d9c9bcc851b23216a1d300311cc4f7cb'
			],
			[
				'host' => 'API test proxy.update - passive with DNS name',
				'status' => HOST_STATUS_PROXY_PASSIVE,
				'interface' => [
					'useip' => INTERFACE_USE_DNS,
					'ip' => '127.0.0.1',
					'dns' => 'localhost',
					'port' => '10050'
				]
			],
			[
				'host' => 'API test proxy.update - passive with IP address',
				'status' => HOST_STATUS_PROXY_PASSIVE,
				'interface' => [
					'useip' => INTERFACE_USE_IP,
					'ip' => '127.0.0.1',
					'dns' => 'localhost',
					'port' => '10050'
				]
			],
			[
				'host' => 'API test proxy.update - passive with PSK-based connections to proxy',
				'status' => HOST_STATUS_PROXY_PASSIVE,
				'interface' => [
					'useip' => INTERFACE_USE_IP,
					'ip' => '127.0.0.1',
					'dns' => 'localhost',
					'port' => '10050'
				],
				'tls_connect' => HOST_ENCRYPTION_PSK,
			],
			[
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
			[
				'host' => 'API test proxy.update - hosts',
				'status' => HOST_STATUS_PROXY_ACTIVE
			],
			[
				'host' => 'API test proxy.delete - single',
				'status' => HOST_STATUS_PROXY_ACTIVE
			],
			[
				'host' => 'API test proxy.delete - multiple 1',
				'status' => HOST_STATUS_PROXY_ACTIVE
			],
			[
				'host' => 'API test proxy.delete - multiple 2',
				'status' => HOST_STATUS_PROXY_ACTIVE
			],
			[
				'host' => 'API test proxy.delete - used in hosts',
				'status' => HOST_STATUS_PROXY_ACTIVE
			],
			[
				'host' => 'API test proxy.delete - used in actions',
				'status' => HOST_STATUS_PROXY_ACTIVE
			],
			[
				'host' => 'API test proxy.delete - used in discovery rules',
				'status' => HOST_STATUS_PROXY_ACTIVE
			]
		];
		$proxies = CDataHelper::call('proxy.create', $proxies_data);
		$this->assertArrayHasKey('proxyids', $proxies, __FUNCTION__.'() failed: Could not create proxies.');
		self::$data['proxyids'] = [
			'get_active_defaults' => $proxies['proxyids'][0],
			'get_passive_defaults' => $proxies['proxyids'][1],
			'get_version_undefined' => $proxies['proxyids'][2],
			'get_version_current' => $proxies['proxyids'][3],
			'get_version_outdated' => $proxies['proxyids'][4],
			'get_version_unsupported' => $proxies['proxyids'][5],
			'update_active_defaults' => $proxies['proxyids'][6],
			'update_passive_defaults' => $proxies['proxyids'][7],
			'update_active_psk' => $proxies['proxyids'][8],
			'update_active_cert' => $proxies['proxyids'][9],
			'update_active_any' => $proxies['proxyids'][10],
			'update_passive_dns' => $proxies['proxyids'][11],
			'update_passive_ip' => $proxies['proxyids'][12],
			'update_passive_psk' => $proxies['proxyids'][13],
			'update_passive_cert' => $proxies['proxyids'][14],
			'update_hosts' => $proxies['proxyids'][15],
			'delete_single' => $proxies['proxyids'][16],
			'delete_multiple_1' => $proxies['proxyids'][17],
			'delete_multiple_2' => $proxies['proxyids'][18],
			'delete_host' => $proxies['proxyids'][19],
			'delete_action' => $proxies['proxyids'][20],
			'delete_discovery' => $proxies['proxyids'][21]
		];

		// Manually update "host_rtdata" table.
		$proxy_rtdata = [
			$proxies['proxyids'][3] => [
				'lastaccess' => 1662034530,
				'version' => 60400,
				'compatibility' => ZBX_PROXY_VERSION_CURRENT
			],
			$proxies['proxyids'][4] => [
				'lastaccess' => 1662034225,
				'version' => 60200,
				'compatibility' => ZBX_PROXY_VERSION_OUTDATED
			],
			$proxies['proxyids'][5] => [
				'lastaccess' => 1651407015,
				'version' => 50401,
				'compatibility' => ZBX_PROXY_VERSION_UNSUPPORTED
			]
		];

		$upd_proxy_rtdata = [];
		foreach ($proxies['proxyids'] as $proxyid) {
			if (!array_key_exists($proxyid, $proxy_rtdata)) {
				continue;
			}

			$upd_proxy_rtdata[] = [
				'values' => [
					'lastaccess' => $proxy_rtdata[$proxyid]['lastaccess'],
					'version' => $proxy_rtdata[$proxyid]['version'],
					'compatibility' => $proxy_rtdata[$proxyid]['compatibility'],
				],
				'where' => ['hostid' => $proxyid]
			];
		}

		DB::update('host_rtdata', $upd_proxy_rtdata);

		// Create host group.
		$hostgroups = CDataHelper::call('hostgroup.create', [
			'name' => 'API test host group'
		]);
		$this->assertArrayHasKey('groupids', $hostgroups, __FUNCTION__.'() failed: Could not create host groups.');
		self::$data['groupids'] = $hostgroups['groupids'];

		// Create host.
		$hosts_data = [
			[
				'host' => 'api_test_host_with_proxy',
				'name' => 'API test host - with proxy',
				'proxy_hostid' => self::$data['proxyids']['delete_host'],
				'groups' => [
					[
						'groupid' => self::$data['groupids'][0]
					]
				]
			],
			[
				'host' => 'api_test_host_without_proxy_1',
				'name' => 'API test host - without proxy 1',
				'groups' => [
					[
						'groupid' => self::$data['groupids'][0]
					]
				]
			],
			[
				'host' => 'api_test_host_without_proxy_2',
				'name' => 'API test host - without proxy 2',
				'groups' => [
					[
						'groupid' => self::$data['groupids'][0]
					]
				]
			]
		];
		$hosts = CDataHelper::call('host.create', $hosts_data);
		$this->assertArrayHasKey('hostids', $hosts, __FUNCTION__.'() failed: Could not create hosts.');
		self::$data['hostids'] = [
			'api_test_host_with_proxy' => $hosts['hostids'][0],
			'api_test_host_without_proxy_1' => $hosts['hostids'][1],
			'api_test_host_without_proxy_2' => $hosts['hostids'][2]
		];

		// actions
		$actions_data = [
			'name' => 'API test discovery action',
			'eventsource' => EVENT_SOURCE_DISCOVERY,
			'filter' => [
				'evaltype' => CONDITION_EVAL_TYPE_AND_OR,
				'conditions' => [
					[
						'conditiontype' => CONDITION_TYPE_PROXY,
						'operator' => CONDITION_OPERATOR_EQUAL,
						'value' => self::$data['proxyids']['delete_action']
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
		$actions = CDataHelper::call('action.create', $actions_data);
		$this->assertArrayHasKey('actionids', $actions, __FUNCTION__.'() failed: Could not create actions.');
		self::$data['actionids'] = $actions['actionids'];

		// discovery rules
		$drules_data = [
			'name' => 'API test discovery rule',
			'iprange' => '192.168.1.1-255',
			'proxy_hostid' => self::$data['proxyids']['delete_discovery'],
			'dchecks' => [
				[
					'type' => SVC_AGENT,
					'key_' => 'system.uname',
					'ports' => 10050,
					'uniq' => 0
				]
			]
		];
		$drules = CDataHelper::call('drule.create', $drules_data);
		$this->assertArrayHasKey('druleids', $drules, __FUNCTION__.'() failed: Could not create discovery rules.');
		self::$data['druleids'] = $drules['druleids'];
	}

	/**
	 * Data provider for proxy.create. Array contains invalid proxies.
	 *
	 * @return array
	 */
	public static function getProxyCreateDataInvalid(): array {
		return [
			'Test proxy.create missing fields' => [
				'proxy' => [],
				'expected_error' => 'Invalid parameter "/": cannot be empty.'
			],
			'Test proxy.create unexpected field' => [
				'proxy' => [
					'abc' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "abc".'
			],

			// Check host.
			'Test proxy.create missing host' => [
				'proxy' => [
					'description' => ''
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "host" is missing.'
			],
			'Test proxy.create invalid host (empty string)' => [
				'proxy' => [
					'host' => ''
				],
				'expected_error' => 'Invalid parameter "/1/host": cannot be empty.'
			],
			'Test proxy.create UTF-8 proxy host' => [
				'proxy' => [
					'host' => 'АПИ прокси УТФ-8'
				],
				'expected_error' => 'Invalid parameter "/1/host": invalid host name.'
			],
			'Test proxy.create invalid host (does not match naming pattern)' => [
				'proxy' => [
					'host' => 'API create proxy?'
				],
				'expected_error' => 'Invalid parameter "/1/host": invalid host name.'
			],

			// Check status.
			'Test proxy.create missing status' => [
				'proxy' => [
					'host' => 'API create proxy'
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "status" is missing.'
			],
			'Test proxy.create invalid status (empty string)' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => ''
				],
				'expected_error' => 'Invalid parameter "/1/status": an integer is expected.'
			],
			'Test proxy.create invalid status (string)' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1/status": an integer is expected.'
			],
			'Test proxy.create invalid status' => [
				'proxy' => [
					'host' => 'API create proxy',
					'status' => 999999
				],
				'expected_error' => 'Invalid parameter "/1/status": value must be one of '.
					implode(', ', [HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE]).'.'
			],

			// TODO: add more test cases
		];
	}

	/**
	 * Data provider for proxy.create. Array contains valid proxies.
	 *
	 * @return array
	 */
	public static function getProxyCreateDataValid(): array {
		return [
			'Test proxy.create successful single proxy' => [
				'proxy' => [
					'host' => 'API create single proxy',
					'status' => HOST_STATUS_PROXY_ACTIVE
				],
				'expected_error' => null
			],
			'Test proxy.create successful multiple proxies' => [
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
			],

			// TODO: add more test cases
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
			'Test proxy.get unexpected field' => [
				'request' => [
					'abc' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/": unexpected parameter "abc".'
			],

			// Check "proxyids" field.
			'Test proxy.get invalid "proxyids" field (empty string)' => [
				'request' => [
					'proxyids' => ''
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/proxyids": an array is expected.'
			],
			'Test proxy.get invalid "proxyids" field (array with empty string)' => [
				'request' => [
					'proxyids' => ['']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/proxyids/1": a number is expected.'
			],

			// Check filter.
			'Test proxy.get invalid filter (empty string)' => [
				'request' => [
					'filter' => ''
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/filter": an array is expected.'
			],

			// Check unexpected parameters that exist in object, but not in filter.
			'Test proxy.get unexpected parameter in filter' => [
				'request' => [
					'filter' => [
						'proxy_address' => 'proxy_address'
					]
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/filter": unexpected parameter "proxy_address".'
			],

			'Test proxy.get invalid status (string) in filter' => [
				'request' => [
					'filter' => [
						'status' => 'abc'
					]
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/filter/status": an array is expected.'
			],
			'Test proxy.get invalid status in filter' => [
				'request' => [
					'filter' => [
						'status' => 999999
					]
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/filter/status/1": value must be one of '.
					implode(', ', [HOST_STATUS_PROXY_ACTIVE, HOST_STATUS_PROXY_PASSIVE]).'.'
			],
			'Test proxy.get invalid lastaccess (string) in filter' => [
				'request' => [
					'filter' => [
						'lastaccess' => 'abc'
					]
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/filter/lastaccess": an array is expected.'
			],
			'Test proxy.get invalid lastaccess in filter' => [
				'request' => [
					'filter' => [
						'lastaccess' => ['abc']
					]
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/filter/lastaccess/1": an integer is expected.'
			],
			'Test proxy.get invalid lastaccess (out of range) in filter' => [
				'request' => [
					'filter' => [
						'lastaccess' => [-1]
					]
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/filter/lastaccess/1": value must be one of 0-'.ZBX_MAX_DATE.'.'
			],
			'Test proxy.get invalid lastaccess (too large) in filter' => [
				'request' => [
					'filter' => [
						'lastaccess' => [ZBX_MAX_DATE + 1]
					]
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/filter/lastaccess/1": a number is too large.'
			],
			'Test proxy.get invalid compatibility (string) in filter' => [
				'request' => [
					'filter' => [
						'compatibility' => 'abc'
					]
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/filter/compatibility": an array is expected.'
			],
			'Test proxy.get invalid compatibility in filter' => [
				'request' => [
					'filter' => [
						'compatibility' => 999999
					]
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/filter/compatibility/1": value must be one of '.
					implode(', ', [
						ZBX_PROXY_VERSION_UNDEFINED, ZBX_PROXY_VERSION_CURRENT, ZBX_PROXY_VERSION_OUTDATED,
						ZBX_PROXY_VERSION_UNSUPPORTED
					]).'.'
			],

			// Check "search" option.
			'Test proxy.get invalid search (string)' => [
				'request' => [
					'search' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/search": an array is expected.'
			],

			// Check unexpected parameters that exist in object, but not in search.
			'Test proxy.get unexpected parameter in search' => [
				'request' => [
					'search' => [
						'proxyid' => 'proxyid'
					]
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/search": unexpected parameter "proxyid".'
			],

			// Check "output" option.
			'Test proxy.get invalid parameter "output" (string)' => [
				'request' => [
					'output' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/output": value must be "'.API_OUTPUT_EXTEND.'".'
			],
			'Test proxy.get invalid parameter "output"' => [
				'request' => [
					'output' => ['abc']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/output/1": value must be one of "proxyid", "host", "status", "description", "lastaccess", "tls_connect", "tls_accept", "tls_issuer", "tls_subject", "proxy_address", "auto_compress", "version", "compatibility".'
			],

			// Check write-only fields are not returned.
			'Test proxy.get write-only field "tls_psk_identity"' => [
				'request' => [
					'output' => ['tls_psk_identity']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/output/1": value must be one of "proxyid", "host", "status", "description", "lastaccess", "tls_connect", "tls_accept", "tls_issuer", "tls_subject", "proxy_address", "auto_compress", "version", "compatibility".'
			],
			'Test proxy.get write-only field "tls_psk"' => [
				'request' => [
					'output' => ['tls_psk']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/output/1": value must be one of "proxyid", "host", "status", "description", "lastaccess", "tls_connect", "tls_accept", "tls_issuer", "tls_subject", "proxy_address", "auto_compress", "version", "compatibility".'
			],

			// Check "selectHosts" option.
			'Test proxy.get invalid parameter "selectHosts" (string)' => [
				'request' => [
					'selectHosts' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/selectHosts": value must be "'.API_OUTPUT_EXTEND.'".'
			],
			'Test proxy.get invalid parameter "selectHosts"' => [
				'request' => [
					'selectHosts' => ['abc']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/selectHosts/1": value must be one of "hostid", "proxy_hostid", "host", "status", "ipmi_authtype", "ipmi_privilege", "ipmi_username", "ipmi_password", "maintenanceid", "maintenance_status", "maintenance_type", "maintenance_from", "name", "flags", "description", "tls_connect", "tls_accept", "tls_issuer", "tls_subject", "inventory_mode", "active_available".'
			],

			// Check "selectInterface" option.
			'Test proxy.get invalid parameter "selectInterface" (string)' => [
				'request' => [
					'selectInterface' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/selectInterface": value must be "'.API_OUTPUT_EXTEND.'".'
			],
			'Test proxy.get invalid parameter "selectInterface"' => [
				'request' => [
					'selectInterface' => ['abc']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/selectInterface/1": value must be one of "interfaceid", "hostid", "main", "type", "useip", "ip", "dns", "port", "available", "error", "errors_from", "disable_until".'
			],

			// Check common fields that are not flags, but require strict validation.
			'Test proxy.get invalid parameter "searchByAny" (string)' => [
				'request' => [
					'searchByAny' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/searchByAny": a boolean is expected.'
			],
			'Test proxy.get invalid parameter "searchWildcardsEnabled" (string)' => [
				'request' => [
					'searchWildcardsEnabled' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/searchWildcardsEnabled": a boolean is expected.'
			],
			'Test proxy.get invalid parameter "sortfield" (bool)' => [
				'request' => [
					'sortfield' => false
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/sortfield": an array is expected.'
			],
			'Test proxy.get invalid parameter "sortfield"' => [
				'request' => [
					'sortfield' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/sortfield/1": value must be one of "hostid", "host", "status".'
			],
			'Test proxy.get invalid parameter "sortorder" (bool)' => [
				'request' => [
					'sortorder' => false
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/sortorder": an array or a character string is expected.'
			],
			'Test proxy.get invalid parameter "sortorder"' => [
				'request' => [
					'sortorder' => 'abc'
				],
				'expected_result' => [],
				'expected_error' =>
					'Invalid parameter "/sortorder": value must be one of "'.ZBX_SORT_UP.'", "'.ZBX_SORT_DOWN.'".'
			],
			'Test proxy.get invalid parameter "limit" (bool)' => [
				'request' => [
					'limit' => false
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/limit": an integer is expected.'
			],
			'Test proxy.get invalid parameter "editable" (string)' => [
				'request' => [
					'editable' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/editable": a boolean is expected.'
			],
			'Test proxy.get invalid parameter "preservekeys" (string)' => [
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
			'Test proxy.get empty "proxyids" parameter' => [
				'request' => [
					'proxyids' => []
				],
				'expected_result' => [],
				'expected_error' => null
			],

			// Check no fields are returned on empty selection.
			'Test proxy.get empty output' => [
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
			'Test proxy.get "lastaccess", "version", "compatibility"' => [
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
			'Test proxy.get filter by "status"' => [
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
			'Test proxy.get filter by "version"' => [
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
					],
				],
				'expected_error' => null
			],

			// Filter by version compatibility.
			'Test proxy.get filter by "compatibility"' => [
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
			'Test proxy.get search by "host"' => [
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
			// Check proxy ID.
			'Test proxy.update empty request' => [
				'proxy' => [],
				'expected_error' => 'Invalid parameter "/": cannot be empty.'
			],
			'Test proxy.update missing ID' => [
				'proxy' => [[
					'host' => 'API updated proxy'
				]],
				'expected_error' => 'Invalid parameter "/1": the parameter "proxyid" is missing.'
			],
			'Test proxy.update empty ID' => [
				'proxy' => [[
					'proxyid' => '',
					'host' => 'API updated proxy'
				]],
				'expected_error' => 'Invalid parameter "/1/proxyid": a number is expected.'
			],
			'Test proxy.update invalid ID (non-existent)' => [
				'proxy' => [[
					'proxyid' => 999999,
					'host' => 'API updated proxy'
				]],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],

			// TODO: add more test cases
		];
	}

	/**
	 * Data provider for proxy.update. Array contains valid proxy parameters.
	 *
	 * @return array
	 */
	public static function getProxyUpdateDataValid(): array {
		return [
			'Test proxy.update successful single proxy update without changes' => [
				'proxy' => [
					[
						'proxyid' => 'update_active_defaults'
					]
				],
				'expected_error' => null
			],
			'Test proxy.update successful multiple proxies update' => [
				'proxy' => [
					[
						'proxyid' => 'update_active_defaults',
						'name' => 'API test proxy.update - active proxy updated',
						'description' => 'Active proxy'
					],
					[
						'proxyid' => 'update_passive_defaults',
						'name' => 'API test proxy.update - passive proxy updated',
						'description' => 'Passive proxy'
					],
				],
				'expected_error' => null
			],

			'Test proxy.update successful proxy assign to single host' => [
				'proxy' => [
					[
						'proxyid' => 'update_hosts',
						'hosts' => [
							['hostid' => 'api_test_host_without_proxy_1']
						]
					]
				],
				'expected_error' => null
			],
			'Test proxy.update successful proxy assign to multiple hosts' => [
				'proxy' => [
					[
						'proxyid' => 'update_hosts',
						'hosts' => [
							['hostid' => 'api_test_host_without_proxy_1'],
							['hostid' => 'api_test_host_without_proxy_2']
						]
					]
				],
				'expected_error' => null
			],

			// TODO: add more test cases
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

				// Check name.
				$this->assertNotEmpty($proxy_upd['host']);

				if (array_key_exists('host', $proxy)) {
					$this->assertSame($proxy['host'], $proxy_upd['host']);
				}
				else {
					$this->assertSame($db_proxy['host'], $proxy_upd['host']);
				}

				// TODO: add more asserts
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
			'Test proxy.delete with empty ID' => [
				'proxyids' => [''],
				'expected_error' => 'Invalid parameter "/1": a number is expected.'
			],
			'Test proxy.delete with non-existent ID' => [
				'proxyids' => [999999],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Test proxy.delete with two same IDs' => [
				'proxyids' => [0, 0],
				'expected_error' => 'Invalid parameter "/2": value (0) already exists.'
			],

			// Check if deleted proxies used to monitor hosts.
			'Test proxy.delete with attached host' => [
				'proxyids' => ['delete_host'],
				'expected_error' => 'Host "API test host - with proxy" is monitored by proxy "API test proxy.delete - used in hosts".'
			],

			// Check if deleted proxies used in actions.
			'Test proxy.delete with attached action' => [
				'proxyids' => ['delete_action'],
				'expected_error' => 'Proxy "API test proxy.delete - used in actions" is used by action "API test discovery action".'
			],

			// Check if deleted proxies used in network discovery rules.
			'Test proxy.delete with attached discovery rule' => [
				'proxyids' => ['delete_discovery'],
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
			'Test proxy.delete' => [
				'proxy' => ['delete_single'],
				'expected_error' => null
			],
			'Test proxy.delete (multiple)' => [
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
		foreach ($proxies as &$proxy) {
			unset($proxy['hosts'], $proxy['interface']);
		}
		unset($proxy);

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
		return !is_array($id) && $id != '0' && $id !== '' && $id !== null && $id != 999999;
	}
}
