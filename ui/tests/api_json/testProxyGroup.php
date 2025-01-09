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


require_once __DIR__.'/../include/CAPITest.php';

/**
 * @backup   ids
 *
 * @onBefore prepareTestData
 * @onAfter  clearData
 */
class testProxyGroup extends CAPITest {

	/**
	 * Non-existent ID, type, status etc.
	 */
	private const INVALID_NUMBER = 999999;

	/**
	 * @var array
	 */
	private static array $data = [
		'proxy_groupids' => [],
		'proxyids' => [],
		'groupids' => [],
		'hostids' => [],

		// Created proxy groups during proxygroup.create test (deleted at the end).
		'created' => []
	];

	/**
	 * Prepare data for tests. Create proxy groups, proxies, host groups, hosts.
	 */
	public function prepareTestData(): void {
		$this->prepareTestDataProxyGroups();
		$this->prepareTestDataProxies();
		$this->prepareTestDataHostGroups();
		$this->prepareTestDataHosts();
	}

	/**
	 * Create proxy groups.
	 */
	private function prepareTestDataProxyGroups(): void {
		$proxy_groups = [
			'defaults' => [
				'name' => 'API test proxy group - with defaults'
			],
			'with_1_proxy' => [
				'name' => 'API test proxy group - with 1 proxy',
				'min_online' => 3
			],
			'with_3_proxies' => [
				'name' => 'API test proxy group - with 3 proxies'
			],
			'state_offline' => [
				'name' => 'API test proxy group - state offline'
			],
			'state_recovering' => [
				'name' => 'API test proxy group - state recovering'
			],
			'state_online' => [
				'name' => 'API test proxy group - state online'
			],
			'state_degrading' => [
				'name' => 'API test proxy group - state degrading'
			]
		];

		$db_proxy_groups = CDataHelper::call('proxygroup.create', array_values($proxy_groups));
		$this->assertArrayHasKey('proxy_groupids', $db_proxy_groups,
			__FUNCTION__.'() failed: Could not create proxy groups.'
		);

		self::$data['proxy_groupids'] = array_combine(array_keys($proxy_groups), $db_proxy_groups['proxy_groupids']);

		// Manually update "proxy_group_rtdata" table.
		$states = [
			'state_offline' => [
				'state' => ZBX_PROXYGROUP_STATE_OFFLINE
			],
			'state_recovering' => [
				'state' => ZBX_PROXYGROUP_STATE_RECOVERING
			],
			'state_online' => [
				'state' => ZBX_PROXYGROUP_STATE_ONLINE
			],
			'state_degrading' => [
				'state' => ZBX_PROXYGROUP_STATE_DEGRADING
			]
		];

		$proxy_groups = [];

		foreach ($states as $id_placeholder => $state) {
			$proxy_groups[] = [
				'values' => $state,
				'where' => ['proxy_groupid' => self::$data['proxy_groupids'][$id_placeholder]]
			];
		}

		DB::update('proxy_group_rtdata', $proxy_groups);
	}

	/**
	 * Create proxies.
	 */
	private function prepareTestDataProxies(): void {
		$proxies = [
			'without_proxy_group' => [
				'name' => 'API test proxy group - without proxy group',
				'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
			],
			'with_proxy_group' => [
				'name' => 'API test proxy group - with proxy group',
				'proxy_groupid' => self::$data['proxy_groupids']['with_1_proxy'],
				'local_address' => 'localhost',
				'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
			],
			'with_proxy_group_1' => [
				'name' => 'API test proxy group - with proxy group 1',
				'proxy_groupid' => self::$data['proxy_groupids']['with_3_proxies'],
				'local_address' => 'proxy1.lan',
				'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
			],
			'with_proxy_group_2' => [
				'name' => 'API test proxy group - with proxy group 2',
				'proxy_groupid' => self::$data['proxy_groupids']['with_3_proxies'],
				'local_address' => 'proxy2.lan',
				'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
			],
			'with_proxy_group_3' => [
				'name' => 'API test proxy group - with proxy group 3',
				'proxy_groupid' => self::$data['proxy_groupids']['with_3_proxies'],
				'local_address' => 'proxy3.lan',
				'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
			]
		];

		$db_proxies = CDataHelper::call('proxy.create', array_values($proxies));
		$this->assertArrayHasKey('proxyids', $db_proxies, __FUNCTION__.'() failed: Could not create proxies.');

		self::$data['proxyids'] = array_combine(array_keys($proxies), $db_proxies['proxyids']);
	}

	/**
	 * Create host groups.
	 */
	private function prepareTestDataHostGroups(): void {
		$db_hostgroups = CDataHelper::call('hostgroup.create', [
			'name' => 'API test proxy group - host group'
		]);
		$this->assertArrayHasKey('groupids', $db_hostgroups, __FUNCTION__.'() failed: Could not create host groups.');

		self::$data['groupids'] = $db_hostgroups['groupids'];
	}

	/**
	 * Create hosts.
	 */
	private function prepareTestDataHosts(): void {
		$hosts = [
			'monitored_by_server' => [
				'host' => 'host_monitored_by_server',
				'name' => 'API test proxy group - monitored by server',
				'groups' => [
					[
						'groupid' => self::$data['groupids'][0]
					]
				]
			],
			'monitored_by_proxy_without_group' => [
				'host' => 'host_monitored_by_proxy_without_group',
				'name' => 'API test proxy group - monitored by proxy without group',
				'monitored_by' => ZBX_MONITORED_BY_PROXY,
				'proxyid' => self::$data['proxyids']['without_proxy_group'],
				'groups' => [
					[
						'groupid' => self::$data['groupids'][0]
					]
				]
			],
			'monitored_by_proxy_from_group' => [
				'host' => 'host_monitored_by_proxy_from_group',
				'name' => 'API test proxy group - monitored by proxy from group',
				'monitored_by' => ZBX_MONITORED_BY_PROXY,
				'proxyid' => self::$data['proxyids']['with_proxy_group'],
				'groups' => [
					[
						'groupid' => self::$data['groupids'][0]
					]
				]
			],
			'monitored_by_empty_proxy_group' => [
				'host' => 'host_monitored_by_empty_proxy_group',
				'name' => 'API test proxy group - monitored by empty proxy group',
				'monitored_by' => ZBX_MONITORED_BY_PROXY_GROUP,
				'proxy_groupid' => self::$data['proxy_groupids']['defaults'],
				'groups' => [
					[
						'groupid' => self::$data['groupids'][0]
					]
				]
			],
			'monitored_by_proxy_group' => [
				'host' => 'host_monitored_by_proxy_group',
				'name' => 'API test proxy group - monitored by proxy group',
				'monitored_by' => ZBX_MONITORED_BY_PROXY_GROUP,
				'proxy_groupid' => self::$data['proxy_groupids']['with_3_proxies'],
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

		// Manually insert data into "host_proxy" table.
		$host_proxy = [
			[
				'hostid' => self::$data['hostids']['monitored_by_proxy_group'],
				'proxyid' => self::$data['proxyids']['with_proxy_group_2']
			]
		];

		DB::insert('host_proxy', $host_proxy);
	}

	/**
	 * Data provider for proxygroup.get. Array contains invalid proxy group parameters.
	 *
	 * @return array
	 */
	public static function getProxyGroupGetDataInvalid(): array {
		return [
			// Check unexpected params.
			'Test proxygroup.get: unexpected parameter' => [
				'request' => [
					'abc' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/": unexpected parameter "abc".'
			],

			// Check "proxy_groupids" field.
			'Test proxygroup.get: invalid "proxy_groupids" (empty string)' => [
				'request' => [
					'proxy_groupids' => ''
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/proxy_groupids": an array is expected.'
			],
			'Test proxygroup.get: invalid "proxy_groupids" (array with empty string)' => [
				'request' => [
					'proxy_groupids' => ['']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/proxy_groupids/1": a number is expected.'
			],

			// Check "proxyids" field.
			'Test proxygroup.get: invalid "proxyids" (empty string)' => [
				'request' => [
					'proxyids' => ''
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/proxyids": an array is expected.'
			],
			'Test proxygroup.get: invalid "proxyids" (array with empty string)' => [
				'request' => [
					'proxyids' => ['']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/proxyids/1": a number is expected.'
			],

			// Check filter.
			'Test proxygroup.get: invalid "filter" (empty string)' => [
				'request' => [
					'filter' => ''
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/filter": an array is expected.'
			],

			// Check unexpected parameters that exist in object, but not in filter.
			'Test proxygroup.get: unexpected parameter in "filter"' => [
				'request' => [
					'filter' => [
						'description' => 'description'
					]
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/filter": unexpected parameter "description".'
			],

			// Check "search" option.
			'Test proxygroup.get: invalid "search" (string)' => [
				'request' => [
					'search' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/search": an array is expected.'
			],

			// Check unexpected parameters that exist in object, but not in search.
			'Test proxygroup.get: unexpected parameter in "search"' => [
				'request' => [
					'search' => [
						'proxy_groupid' => 'proxy_groupid'
					]
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/search": unexpected parameter "proxy_groupid".'
			],

			// Check "output" option.
			'Test proxygroup.get: invalid parameter "output" (string)' => [
				'request' => [
					'output' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/output": value must be "extend".'
			],
			'Test proxygroup.get: invalid parameter "output" (array with string)' => [
				'request' => [
					'output' => ['abc']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/output/1": value must be one of "proxy_groupid", "name", "failover_delay", "min_online", "description", "state".'
			],

			// Check "selectProxies" option.
			'Test proxygroup.get: invalid parameter "selectProxies" (string)' => [
				'request' => [
					'selectProxies' => 'abc'
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/selectProxies": value must be one of "extend", "count".'
			],
			'Test proxygroup.get: invalid parameter "selectProxies" (array with string)' => [
				'request' => [
					'selectProxies' => ['abc']
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/selectProxies/1": value must be one of "proxyid", "name", "local_address", "local_port", "operating_mode", "allowed_addresses", "address", "port", "description", "tls_connect", "tls_accept", "tls_issuer", "tls_subject", "custom_timeouts", "timeout_zabbix_agent", "timeout_simple_check", "timeout_snmp_agent", "timeout_external_check", "timeout_db_monitor", "timeout_http_agent", "timeout_ssh_agent", "timeout_telnet_agent", "timeout_script", "timeout_browser", "lastaccess", "version", "compatibility", "state".'
			]
		];
	}

	/**
	 * Data provider for proxygroup.get. Array contains valid proxy group parameters.
	 *
	 * @return array
	 */
	public static function getProxyGroupGetDataValid(): array {
		return [
			// Check validity of "proxy_groupids" without getting any results.
			'Test proxygroup.get: empty "proxy_groupids"' => [
				'request' => [
					'proxy_groupids' => []
				],
				'expected_result' => [],
				'expected_error' => null
			],

			// Check no fields are returned on empty selection.
			'Test proxygroup.get: empty "output"' => [
				'request' => [
					'output' => [],
					'proxy_groupids' => ['defaults', 'with_1_proxy']
				],
				'expected_result' => [
					[],
					[]
				],
				'expected_error' => null
			],

			// Filter by "proxy_groupid".
			'Test proxygroup.get: filter by "proxy_groupid"' => [
				'request' => [
					'output' => ['name'],
					'filter' => [
						'proxy_groupid' => 'defaults'
					]
				],
				'expected_result' => [
					[
						'name' => 'API test proxy group - with defaults'
					]
				],
				'expected_error' => null
			],

			// Filter by "state".
			'Test proxygroup.get: filter by "state"' => [
				'request' => [
					'output' => ['name', 'state'],
					'filter' => [
						'state' => ZBX_PROXYGROUP_STATE_RECOVERING
					]
				],
				'expected_result' => [
					[
						'name' => 'API test proxy group - state recovering',
						'state' => (string) ZBX_PROXYGROUP_STATE_RECOVERING
					]
				],
				'expected_error' => null
			],

			// Search by "name".
			'Test proxygroup.get: search by "name"' => [
				'request' => [
					'output' => ['name'],
					'search' => [
						'name' => 'API test proxy group - state'
					]
				],
				'expected_result' => [
					['name' => 'API test proxy group - state offline'],
					['name' => 'API test proxy group - state recovering'],
					['name' => 'API test proxy group - state online'],
					['name' => 'API test proxy group - state degrading']
				],
				'expected_error' => null
			],

			// Check "selectProxies".
			'Test proxygroup.get: selectProxies=[] excludes proxyid' => [
				'request' => [
					'output' => [],
					'proxy_groupids' => 'with_1_proxy',
					'selectProxies' => []
				],
				'expected_result' => [[
					'proxies' => [
						[]
					]
				]],
				'expected_error' => null
			],
			'Test proxygroup.get: selectProxies="extend" excludes proxy_groupid' => [
				'request' => [
					'output' => [],
					'proxy_groupids' => 'with_1_proxy',
					'selectProxies' => API_OUTPUT_EXTEND
				],
				'expected_result' => [[
					'proxies' => [[
						'proxyid' => 'with_proxy_group',
						'name' => 'API test proxy group - with proxy group',
						'local_address' => 'localhost',
						'local_port' => '10051',
						'operating_mode' => (string) PROXY_OPERATING_MODE_ACTIVE,
						'allowed_addresses' => '',
						'address' => '127.0.0.1',
						'port' => '10051',
						'description' => '',
						'tls_connect' => '1',
						'tls_accept' => '1',
						'tls_issuer' => '',
						'tls_subject' => '',
						'custom_timeouts' => '0',
						'timeout_zabbix_agent' => '',
						'timeout_simple_check' => '',
						'timeout_snmp_agent' => '',
						'timeout_external_check' => '',
						'timeout_db_monitor' => '',
						'timeout_http_agent' => '',
						'timeout_ssh_agent' => '',
						'timeout_telnet_agent' => '',
						'timeout_script' => '',
						'timeout_browser' => '',
						'lastaccess' => '0',
						'version' => '0',
						'compatibility' => '0',
						'state' => '0'
					]]
				]],
				'expected_error' => null
			],
			'Test proxygroup.get: selectProxies="count"' => [
				'request' => [
					'output' => [],
					'proxy_groupids' => ['with_1_proxy', 'with_3_proxies'],
					'selectProxies' => API_OUTPUT_COUNT
				],
				'expected_result' => [
					['proxies' => '1'],
					['proxies' => '3']
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * Test proxygroup.get with all options.
	 *
	 * @dataProvider getProxyGroupGetDataInvalid
	 * @dataProvider getProxyGroupGetDataValid
	 */
	public function testProxyGroup_Get(array $request, array $expected_result, ?string $expected_error): void {
		// Replace ID placeholders with real IDs.
		$request = self::resolveIds($request);

		foreach ($expected_result as &$proxy_group) {
			$proxy_group = self::resolveIds($proxy_group);
		}
		unset($proxy_group);

		$result = $this->call('proxygroup.get', $request, $expected_error);

		if ($expected_error === null) {
			$this->assertSame($expected_result, $result['result']);
		}
	}

	/**
	 * Data provider for proxygroup.create.
	 *
	 * @return array
	 */
	public static function getProxyGroupCreateData(): array {
		return [
			'Test proxygroup.create: empty request' => [
				'proxygroup' => [],
				'expected_error' => 'Invalid parameter "/": cannot be empty.'
			],
			'Test proxygroup.create: unexpected parameter' => [
				'proxygroup' => [
					'abc' => 'abc'
				],
				'expected_error' => 'Invalid parameter "/1": unexpected parameter "abc".'
			],

			// Check "name".
			'Test proxygroup.create: missing "name"' => [
				'proxygroup' => [
					'description' => ''
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "name" is missing.'
			],
			'Test proxygroup.create: invalid "name" (null)' => [
				'proxygroup' => [
					'name' => null
				],
				'expected_error' => 'Invalid parameter "/1/name": a character string is expected.'
			],
			'Test proxygroup.create: invalid "name" (boolean)' => [
				'proxygroup' => [
					'name' => false
				],
				'expected_error' => 'Invalid parameter "/1/name": a character string is expected.'
			],
			'Test proxygroup.create: invalid "name" (empty string)' => [
				'proxygroup' => [
					'name' => ''
				],
				'expected_error' => 'Invalid parameter "/1/name": cannot be empty.'
			],
			'Test proxygroup.create: invalid "name" (too long)' => [
				'proxygroup' => [
					'name' => str_repeat('a', DB::getFieldLength('proxy_group', 'name') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/name": value is too long.'
			],
			'Test proxygroup.create: multiple proxy groups with the same "name"' => [
				'proxygroup' => [
					[
						'name' => 'API create proxy group'
					],
					[
						'name' => 'API create proxy group'
					]
				],
				'expected_error' => 'Invalid parameter "/2": value (name)=(API create proxy group) already exists.'
			],
			'Test proxygroup.create: invalid "name" (duplicate)' => [
				'proxygroup' => [
					'name' => 'API test proxy group - with defaults'
				],
				'expected_error' => 'Proxy group "API test proxy group - with defaults" already exists.'
			],

			// Check "failover_delay".
			'Test proxygroup.create: invalid "failover_delay" (null)' => [
				'proxygroup' => [
					'name' => 'API create proxy group',
					'failover_delay' => null
				],
				'expected_error' => 'Invalid parameter "/1/failover_delay": a character string is expected.'
			],
			'Test proxygroup.create: invalid "failover_delay" (boolean)' => [
				'proxygroup' => [
					'name' => 'API create proxy group',
					'failover_delay' => false
				],
				'expected_error' => 'Invalid parameter "/1/failover_delay": a character string is expected.'
			],
			'Test proxygroup.create: invalid "failover_delay" (empty string)' => [
				'proxygroup' => [
					'name' => 'API create proxy group',
					'failover_delay' => ''
				],
				'expected_error' => 'Invalid parameter "/1/failover_delay": cannot be empty.'
			],

			// Check "min_online".
			'Test proxygroup.create: invalid "min_online" (null)' => [
				'proxygroup' => [
					'name' => 'API create proxy group',
					'min_online' => null
				],
				'expected_error' => 'Invalid parameter "/1/min_online": a number is expected.'
			],
			'Test proxygroup.create: invalid "min_online" (boolean)' => [
				'proxygroup' => [
					'name' => 'API create proxy group',
					'min_online' => false
				],
				'expected_error' => 'Invalid parameter "/1/min_online": a number is expected.'
			],
			'Test proxygroup.create: invalid "min_online" (empty string)' => [
				'proxygroup' => [
					'name' => 'API create proxy group',
					'min_online' => ''
				],
				'expected_error' => 'Invalid parameter "/1/min_online": cannot be empty.'
			],

			// Check "description".
			'Test proxygroup.create: invalid "description" (null)' => [
				'proxygroup' => [
					'name' => 'API create proxy group',
					'description' => null
				],
				'expected_error' => 'Invalid parameter "/1/description": a character string is expected.'
			],
			'Test proxygroup.create: invalid "description" (boolean)' => [
				'proxygroup' => [
					'name' => 'API create proxy group',
					'description' => false
				],
				'expected_error' => 'Invalid parameter "/1/description": a character string is expected.'
			],
			'Test proxygroup.create: invalid "description" (too long)' => [
				'proxygroup' => [
					'name' => 'API create proxy group',
					'description' => str_repeat('a', DB::getFieldLength('proxy_group', 'description') + 1)
				],
				'expected_error' => 'Invalid parameter "/1/description": value is too long.'
			]
		];
	}

	/**
	 * Test proxygroup.create with errors like missing fields, optional invalid fields and valid fields.
	 *
	 * @dataProvider getProxyGroupCreateData
	 */
	public function testProxyGroup_Create(array $proxy_groups, ?string $expected_error): void {
		// Accept single and multiple objects just like API method. Work with multidimensional array in result.
		if (!array_key_exists(0, $proxy_groups)) {
			$proxy_groups = zbx_toArray($proxy_groups);
		}

		// Replace ID placeholders with real IDs.
		foreach ($proxy_groups as &$proxy_group) {
			$proxy_group = self::resolveIds($proxy_group);
		}
		unset($proxy_group);

		$sql_proxy_groups = 'SELECT NULL FROM proxy_group pg';
		$old_hash_proxy_groups = CDBHelper::getHash($sql_proxy_groups);

		$result = $this->call('proxygroup.create', $proxy_groups, $expected_error);

		if ($expected_error === null) {
			// Something was changed in DB.
			$this->assertNotSame($old_hash_proxy_groups, CDBHelper::getHash($sql_proxy_groups));
			$this->assertEquals(count($proxy_groups), count($result['result']['proxy_groupids']));

			// Add proxy group IDs to create array, so they can be deleted after tests are complete.
			self::$data['created'] = array_merge(self::$data['created'], $result['result']['proxy_groupids']);
		}
		else {
			$this->assertSame($old_hash_proxy_groups, CDBHelper::getHash($sql_proxy_groups));
		}
	}

	/**
	 * Data provider for proxygroup.update.
	 *
	 * @return array
	 */
	public static function getProxyGroupUpdateData(): array {
		return [
			'Test proxygroup.update: update single proxy group without changes' => [
				'proxygroup' => [
					'proxy_groupid' => 'defaults'
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * Test proxygroup.update method.
	 *
	 * @dataProvider getProxyGroupUpdateData
	 */
	public function testProxyGroup_Update(array $proxy_groups, ?string $expected_error): void {
		// Accept single and multiple objects just like API method. Work with multidimensional array in result.
		if (!array_key_exists(0, $proxy_groups)) {
			$proxy_groups = zbx_toArray($proxy_groups);
		}

		// Replace ID placeholders with real IDs.
		foreach ($proxy_groups as &$proxy_group) {
			$proxy_group = self::resolveIds($proxy_group);
		}
		unset($proxy_group);

		$sql_proxy_groups = 'SELECT NULL FROM proxy_group';
		$old_hash_proxy_groups = CDBHelper::getHash($sql_proxy_groups);

		if ($expected_error === null) {
			$proxy_groupids = array_column($proxy_groups, 'proxy_groupid');
			$db_proxy_groups = $this->getProxyGroups($proxy_groupids);

			$this->call('proxygroup.update', $proxy_groups, $expected_error);

			$proxy_groups_upd = $this->getProxyGroups($proxy_groupids);

			$db_defaults = DB::getDefaults('proxy_group');

			// Compare records from DB before and after API call.
			foreach ($proxy_groups as $proxy_group) {
				$db_proxy_group = $db_proxy_groups[$proxy_group['proxy_groupid']];
				$proxy_group_upd = $proxy_groups_upd[$proxy_group['proxy_groupid']];

				$this->assertNotEmpty($proxy_group_upd['name']);

				foreach (['name', 'description'] as $field) {
					if (array_key_exists($field, $proxy_group)) {
						$this->assertSame($proxy_group[$field], $proxy_group_upd[$field]);
					}
					else {
						$this->assertSame($db_proxy_group[$field], $proxy_group_upd[$field]);
					}
				}
			}

			// Restore proxy group original data after each test.
			$this->restoreProxyGroups($db_proxy_groups);
		}
		else {
			// Call method and make sure it really returns the error.
			$this->call('proxygroup.update', $proxy_groups, $expected_error);

			// Make sure nothing has changed as well.
			$this->assertSame($old_hash_proxy_groups, CDBHelper::getHash($sql_proxy_groups));
		}
	}

	/**
	 * Data provider for proxygroup.delete.
	 *
	 * @return array
	 */
	public static function getProxyGroupDeleteData(): array {
		return [
			'Test proxygroup.delete: delete single' => [
				'proxygroup' => ['state_offline'],
				'expected_error' => null
			],
			'Test proxygroup.delete: delete multiple' => [
				'proxygroup' => [
					'state_recovering',
					'state_online',
					'state_degrading'
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * Test proxygroup.delete method.
	 *
	 * @dataProvider getProxyGroupDeleteData
	 */
	public function testProxyGroup_Delete(array $proxy_groupids, ?string $expected_error): void {
		// Replace ID placeholders with real IDs.
		foreach ($proxy_groupids as &$proxy_groupid) {
			if (self::isValidIdPlaceholder($proxy_groupid)) {
				$proxy_groupid = self::$data['proxy_groupids'][$proxy_groupid];
			}
		}
		unset($proxy_groupid);

		$sql_proxy_groups = 'SELECT NULL FROM proxy_group';
		$old_hash_proxy_groups = CDBHelper::getHash($sql_proxy_groups);

		$this->call('proxygroup.delete', $proxy_groupids, $expected_error);

		if ($expected_error === null) {
			$this->assertNotSame($old_hash_proxy_groups, CDBHelper::getHash($sql_proxy_groups));
			$this->assertEquals(0, CDBHelper::getCount(
				'SELECT pg.proxy_groupid FROM proxy_group pg WHERE '.dbConditionId('pg.proxy_groupid', $proxy_groupids)
			));

			foreach ($proxy_groupids as $proxy_groupid) {
				$key = array_search($proxy_groupid, self::$data['proxy_groupids']);

				if ($key !== false) {
					unset(self::$data['proxy_groupids'][$key]);
				}
			}
		}
		else {
			$this->assertSame($old_hash_proxy_groups, CDBHelper::getHash($sql_proxy_groups));
		}
	}

	/**
	 * Get the original proxy groups before update.
	 *
	 * @param array $proxy_groupids
	 *
	 * @return array
	 */
	private function getProxyGroups(array $proxy_groupids): array {
		$response = $this->call('proxygroup.get', [
			'output' => ['proxy_groupid', 'name', 'failover_delay', 'min_online', 'description', 'state'],
			'proxy_groupids' => $proxy_groupids,
			'preservekeys' => true
		]);

		return $response['result'];
	}

	/**
	 * Restore proxy groups to their original state.
	 *
	 * @param array $proxy_groups
	 */
	private function restoreProxyGroups(array $proxy_groups): void {
		$upd_states = [];

		foreach ($proxy_groups as &$proxy_group) {
			$upd_states[] = [
				'values' => [
					'state' => $proxy_group['state']
				],
				'where' => ['proxy_groupid' => $proxy_group['proxy_groupid']]
			];
			unset($proxy_group['state']);
		}
		unset($proxy_group);

		$this->call('proxygroup.update', $proxy_groups);

		DB::update('proxy_group_rtdata', $upd_states);
	}

	/**
	 * Delete all created data after test.
	 */
	public static function clearData(): void {
		// Delete hosts.
		CDataHelper::call('host.delete', array_values(self::$data['hostids']));

		// Delete host groups.
		CDataHelper::call('hostgroup.delete', self::$data['groupids']);

		// Delete proxies.
		CDataHelper::call('proxy.delete', self::$data['proxyids']);

		// Delete proxy groups.
		$proxy_groupids = array_values(self::$data['proxy_groupids']);
		$proxy_groupids = array_merge($proxy_groupids, self::$data['created']);
		CDataHelper::call('proxygroup.delete', $proxy_groupids);
	}

	/**
	 * Helper method to convert placeholders to real IDs.
	 *
	 * @param array $request
	 *
	 * @return array
	 */
	private static function resolveIds(array $request): array {
		foreach (['proxy_groupid', 'proxyid'] as $field) {
			if (array_key_exists($field, $request) && self::isValidIdPlaceholder($request[$field])) {
				$request[$field] = self::$data[$field.'s'][$request[$field]];
			}
		}

		foreach (['proxy_groupids', 'proxyids'] as $field) {
			if (!array_key_exists($field, $request)) {
				continue;
			}

			if (is_array($request[$field])) {
				foreach ($request[$field] as &$id) {
					if (self::isValidIdPlaceholder($id)) {
						$id = self::$data[$field][$id];
					}
				}
				unset($id);
			}
			elseif (self::isValidIdPlaceholder($request[$field])) {
				$request[$field] = self::$data[$field][$request[$field]];
			}
		}

		if (array_key_exists('proxies', $request) && is_array($request['proxies'])) {
			foreach ($request['proxies'] as &$proxy) {
				if (is_array($proxy) && array_key_exists('proxyid', $proxy)
						&& self::isValidIdPlaceholder($proxy['proxyid'])) {
					$proxy['proxyid'] = self::$data['proxyids'][$proxy['proxyid']];
				}
			}
			unset($proxy);
		}

		if (array_key_exists('filter', $request) && is_array($request['filter'])
				&& array_key_exists('proxy_groupid', $request['filter'])) {
			if (is_array($request['filter']['proxy_groupid'])) {
				foreach ($request['filter']['proxy_groupid'] as &$id) {
					if (self::isValidIdPlaceholder($id)) {
						$id = self::$data['proxy_groupids'][$id];
					}
				}
				unset($id);
			}
			elseif (self::isValidIdPlaceholder($request['filter']['proxy_groupid'])) {
				$request['filter']['proxy_groupid'] =
					self::$data['proxy_groupids'][$request['filter']['proxy_groupid']];
			}
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
