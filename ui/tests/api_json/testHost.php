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
 * @onBefore prepareHostsData
 *
 * @onAfter clearData
 */
class testHost extends CAPITest {

	private static $data = [
		'hostgroupid' => null,
		'hostids' => [
			// Test host.delete if hosts are in maintenance.
			'maintenance_1' => null,
			'maintenance_2' => null,
			'maintenance_3' => null,
			'maintenance_4' => null,
			'maintenance_5' => null,
			'maintenance_6' => null,
			'maintenance_7' => null,

			// Test host.get with tags.
			'tags' => null,

			// Test host.get with write-only fields.
			'write_only' => null,

			// Host with LLD rule and host prototype.
			'with_lld_and_prototype' => null,

			// Discovered hosts to test host.update, host.massupdate, host.massadd, host.massremove.
			'discovered_no_templates' => null,
			'discovered_manual_templates' => null,
			'discovered_auto_templates' => null,
			'discovered_auto_and_manual_templates' => null,

			// Discovered host to test if items are cleared as well.
			'discovered_clear' => null,

			// Discovered host to test host.get with limitSelects option.
			'discovered_limit_selects' => null
		],
		'templateids' => null,
		'maintenanceids' => null,
		// Created hosts during host.create test (deleted at the end).
		'created' => []
	];

	public function prepareHostsData() {
		// Create host group.
		$hostgroups = CDataHelper::call('hostgroup.create', [
			[
				'name' => 'API test hosts'
			]
		]);
		$this->assertArrayHasKey('groupids', $hostgroups);
		self::$data['hostgroupid'] = $hostgroups['groupids'][0];

		// Create hosts.
		$hosts_data = [
			// Both hosts are in maintenance, so only one can be deleted.
			[
				'host' => 'api_test_hosts_maintenance_1',
				'name' => 'API test hosts - maintenance 1',
				'groups' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				]
			],
			[
				'host' => 'api_test_hosts_maintenance_2',
				'name' => 'API test hosts - maintenance 2',
				'groups' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				]
			],

			// Both hosts are in maintenance, so only one can be deleted.
			[
				'host' => 'api_test_hosts_maintenance_3',
				'name' => 'API test hosts - maintenance 3',
				'groups' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				]
			],
			[
				'host' => 'api_test_hosts_maintenance_4',
				'name' => 'API test hosts - maintenance 4',
				'groups' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				]
			],

			// Both hosts are in maintenance, both cannot be deleted at the same time.
			[
				'host' => 'api_test_hosts_maintenance_5',
				'name' => 'API test hosts - maintenance 5',
				'groups' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				]
			],
			[
				'host' => 'api_test_hosts_maintenance_6',
				'name' => 'API test hosts - maintenance 6',
				'groups' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				]
			],

			// One host is in maintenance. Used together with a group.
			[
				'host' => 'api_test_hosts_maintenance_7',
				'name' => 'API test hosts - maintenance 7',
				'groups' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				]
			],

			// Host with tags.
			[
				'host' => 'api_test_hosts_tags',
				'name' => 'API test hosts - tags',
				'groups' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				],
				'tags' => [
					[
						'tag' => 'b',
						'value' => 'b'
					]
				]
			],

			// Host with write-only fields.
			[
				'host' => 'api_test_hosts_write_only_fields',
				'name' => 'API test hosts - write-only fields',
				'groups' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				]
			],

			// Host with LLD rule and host prototype.
			[
				'host' => 'api_test_hosts_lld',
				'name' => 'API test hosts - LLD',
				'groups' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				]
			]
		];
		$hosts = CDataHelper::call('host.create', $hosts_data);
		$this->assertArrayHasKey('hostids', $hosts);
		self::$data['hostids'] = [
			'maintenance_1' => $hosts['hostids'][0],
			'maintenance_2' => $hosts['hostids'][1],
			'maintenance_3' => $hosts['hostids'][2],
			'maintenance_4' => $hosts['hostids'][3],
			'maintenance_5' => $hosts['hostids'][4],
			'maintenance_6' => $hosts['hostids'][5],
			'maintenance_7' => $hosts['hostids'][6],
			'tags' => $hosts['hostids'][7],
			'write_only' => $hosts['hostids'][8],
			'with_lld_and_prototype' => $hosts['hostids'][9]
		];

		// Create maintenances. Use same time period for all maintenances.
		$timeperiods = [
			[
				'period' => 3600,
				'timeperiod_type' => 3,
				'start_time' => 64800,
				'every' => 1,
				'dayofweek' => 64
			]
		];
		$maintenances_data = [
			[
				'name' => 'API test hosts - maintenance with one host',
				'active_since' => '1539723600',
				'active_till' => '1539810000',
				'hosts' => [
					[
						'hostid' => self::$data['hostids']['maintenance_1']
					]
				],
				'timeperiods' => $timeperiods
			],
			[
				'name' => 'API test hosts - maintenance with host and group (deletable)',
				'active_since' => '1539723600',
				'active_till' => '1539810000',
				'hosts' => [
					[
						'hostid' => self::$data['hostids']['maintenance_2']
					]
				],
				'groups' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				],
				'timeperiods' => $timeperiods
			],
			[
				'name' => 'API test hosts - maintenance with two hosts (deletable)',
				'active_since' => '1539723600',
				'active_till' => '1539810000',
				'hosts' => [
					[
						'hostid' => self::$data['hostids']['maintenance_3']
					],
					[
						'hostid' => self::$data['hostids']['maintenance_4']
					]
				],
				'timeperiods' => $timeperiods
			],
			[
				'name' => 'API test hosts - maintenance with two hosts (non-deletable)',
				'active_since' => '1539723600',
				'active_till' => '1539810000',
				'hosts' => [
					[
						'hostid' => self::$data['hostids']['maintenance_5']
					],
					[
						'hostid' => self::$data['hostids']['maintenance_6']
					]
				],
				'timeperiods' => $timeperiods
			],
			[
				'name' => 'API test hosts - maintenance with host and group (non-deletable)',
				'active_since' => '1539723600',
				'active_till' => '1539810000',
				'hosts' => [
					[
						'hostid' => self::$data['hostids']['maintenance_7']
					]
				],
				'groups' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				],
				'timeperiods' => $timeperiods
			],
		];
		$maintenances = CDataHelper::call('maintenance.create', $maintenances_data);
		$this->assertArrayHasKey('maintenanceids', $maintenances);
		self::$data['maintenanceids'] = $maintenances['maintenanceids'];

		// Create templates that will be added to host prototypes and essentially discovered hosts.
		$templates_data = [
			// Templates for discovered hosts. When host.get "limitSelects" is used, templates should be sorted A-Z.
			[
				'host' => 'api_test_hosts_f_tpl',
				'name' => 'API test hosts - F template',
				'groups' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				]
			],
			[
				'host' => 'api_test_hosts_c_tpl',
				'name' => 'API test hosts - C template',
				'groups' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				]
			],
			[
				'host' => 'api_test_hosts_a_tpl',
				'name' => 'API test hosts - A template',
				'groups' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				]
			],
			[
				'host' => 'api_test_hosts_e_tpl',
				'name' => 'API test hosts - E template',
				'groups' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				]
			],
			[
				'host' => 'api_test_hosts_b_tpl',
				'name' => 'API test hosts - B template',
				'groups' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				]
			],
			[
				'host' => 'api_test_hosts_d_tpl',
				'name' => 'API test hosts - D template',
				'groups' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				]
			],

			/*
			 * Template for discovered host, but it has one item. Using "templates_clear" option, item should also be
			 * removed from host, otherwise item should stay there.
			 */
			[
				'host' => 'api_test_hosts_tpl_with_item',
				'name' => 'API test hosts - template with item',
				'groups' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				]
			]
		];
		$templates = CDataHelper::call('template.create', $templates_data);
		$this->assertArrayHasKey('templateids', $templates);
		self::$data['templateids'] = $templates['templateids'];

		/*
		 * Create item for that one template. Item IDs do not matter, because there is only one and it will removed
		 * after tests are complete together with this template.
		 */
		$items = CDataHelper::call('item.create', [
			'hostid' => $templates['templateids'][6],
			'name' => 'API test hosts - template item',
			'key_' => 'api_test_hosts_tpl_item',
			'type' => ITEM_TYPE_TRAPPER,
			'value_type' => ITEM_VALUE_TYPE_FLOAT
		]);
		$this->assertArrayHasKey('itemids', $items);

		// Create LLD rule.
		$discoveryrules = CDataHelper::call('discoveryrule.create', [
			'name' => 'LLD rule',
			'key_' => 'lld_rule',
			'hostid' => self::$data['hostids']['with_lld_and_prototype'],
			'type' => ITEM_TYPE_TRAPPER
		]);
		$this->assertArrayHasKey('itemids', $discoveryrules);

		// Create host prototypes.
		$host_prototypes_data = [
			[
				'host' => '{#HOST}_no_templates',
				'ruleid' => $discoveryrules['itemids'][0],
				'groupLinks' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				]
			],
			[
				'host' => '{#HOST}_manual_templates',
				'ruleid' => $discoveryrules['itemids'][0],
				'groupLinks' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				]
			],
			[
				'host' => '{#HOST}_auto_templates',
				'ruleid' => $discoveryrules['itemids'][0],
				'groupLinks' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				],

				// First two templates.
				'templates' => [
					[
						'templateid' => self::$data['templateids'][0]
					],
					[
						'templateid' => self::$data['templateids'][1]
					]
				]
			],
			[
				'host' => '{#HOST}_auto_and_manual_templates',
				'ruleid' => $discoveryrules['itemids'][0],
				'groupLinks' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				],

				// First two templates. Third and fourth templates are manualy added.
				'templates' => [
					[
						'templateid' => self::$data['templateids'][0]
					],
					[
						'templateid' => self::$data['templateids'][1]
					]
				]
			],
			[
				'host' => '{#HOST}_clear',
				'ruleid' => $discoveryrules['itemids'][0],
				'groupLinks' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				],

				// Template that has item.
				'templates' => [
					[
						'templateid' => $templates['templateids'][5]
					]
				]
			],
			[
				'host' => '{#HOST}_limit_selects',
				'ruleid' => $discoveryrules['itemids'][0],
				'groupLinks' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				],

				// Does not matter if templates are manually added or automatically.
				'templates' => [
					[
						'templateid' => $templates['templateids'][5]
					]
				]
			]
		];
		$host_prototypes = CDataHelper::call('hostprototype.create', $host_prototypes_data);
		$this->assertArrayHasKey('hostids', $host_prototypes);

		// Create discovered hosts.
		$hosts_data = [];

		foreach ($host_prototypes_data as $idx => $host_prototype) {
			$hosts_data[] = [
				'host' => str_replace('{#HOST}', 'discovered', $host_prototype['host']),
				'groups' => $host_prototype['groupLinks'],
				'templates' => array_key_exists('templates', $host_prototype) ? $host_prototype['templates'] : []
			];
		}

		$hosts = CDataHelper::call('host.create', $hosts_data);
		$this->assertArrayHasKey('hostids', $hosts);
		self::$data['hostids']['discovered_no_templates'] = $hosts['hostids'][0];
		self::$data['hostids']['discovered_manual_templates'] = $hosts['hostids'][1];
		self::$data['hostids']['discovered_auto_templates'] = $hosts['hostids'][2];
		self::$data['hostids']['discovered_auto_and_manual_templates'] = $hosts['hostids'][3];
		self::$data['hostids']['discovered_clear'] = $hosts['hostids'][4];
		self::$data['hostids']['discovered_limit_selects'] = $hosts['hostids'][5];

		$host_discovery = [];
		$upd_hosts = [];
		$upd_hosts_templates = [];

		foreach ($host_prototypes_data as $idx => $host_prototype) {
			$host_discovery[] = [
				'hostid' => $hosts['hostids'][$idx],
				'parent_hostid' => $host_prototypes['hostids'][$idx],
				'host' => $host_prototype['host'],
				'lastcheck' => '1648726056'
			];
			$upd_hosts[] = [
				'values' => ['flags' => 4],
				'where' => ['hostid' => $hosts['hostids'][$idx]]
			];

			if (array_key_exists('templates', $host_discovery) && $host_discovery['templates']) {
				foreach ($host_prototype['templates'] as $template) {
					$upd_hosts_templates[] = [
						'values' => ['link_type' => TEMPLATE_LINK_LLD],
						'where' => ['hostid' => $hosts['hostids'][$idx], 'templateid' => $template['templateid']]
					];
				}
			}
		}

		$ids = DB::insertBatch('host_discovery', $host_discovery, false);
		// Since insertBatch() parameter $getids is false, it will use existing IDs. Thus it will return empty array.
		$this->assertSame([], $ids);

		$res = DB::update('hosts', $upd_hosts);
		$this->assertSame(true, $res);

		$res = DB::update('hosts_templates', $upd_hosts_templates);
		$this->assertSame(true, $res);

		// Add few manual templates to discovered hosts to test the template removal. Do not use API at this point.
		$nextid = CDBHelper::getValue(
			'SELECT i.nextid'.
			' FROM ids i'.
			' WHERE i.table_name='.zbx_dbstr('hosts_templates').
				' AND i.field_name='.zbx_dbstr('hosttemplateid').
			' FOR UPDATE'
		) + 1;
		$hosts_templates_data = [
			// Host contains only manual templates.
			[
				'hostid' => self::$data['hostids']['discovered_manual_templates'],
				'templateid' => self::$data['templateids'][2],
				'link_type' => TEMPLATE_LINK_MANUAL
			],
			[
				'hostid' => self::$data['hostids']['discovered_manual_templates'],
				'templateid' => self::$data['templateids'][3],
				'link_type' => TEMPLATE_LINK_MANUAL
			],

			// Host contains automatically added templates and manually added templates.
			[
				'hostid' => self::$data['hostids']['discovered_auto_and_manual_templates'],
				'templateid' => self::$data['templateids'][2],
				'link_type' => TEMPLATE_LINK_MANUAL
			],
			[
				'hostid' => self::$data['hostids']['discovered_auto_and_manual_templates'],
				'templateid' => self::$data['templateids'][3],
				'link_type' => TEMPLATE_LINK_MANUAL
			]
		];
		$ids = DB::insertBatch('hosts_templates', $hosts_templates_data);
		$newids = array_fill($nextid, count($hosts_templates_data), true);
		$this->assertEquals(array_keys($newids), $ids);
	}

	/**
	 * Data provider for host.delete. Array contains valid hosts that are possible to delete.
	 *
	 * @return array
	 */
	public static function getHostDeleteDataValid() {
		return	[
			'Test host.delete host that has a group in same maintenance' => [
				'hostids' => [
					'maintenance_2'
				],
				'expected_error' => null
			],
			'Test host.delete host that another host in same maintenance' => [
				'hostids' => [
					'maintenance_3'
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * Data provider for host.delete. Array contains invalid hosts that are not possible to delete.
	 *
	 * @return array
	 */
	public static function getHostDeleteDataInvalid() {
		return	[
			'Test host.delete single host in maintenance' => [
				'hostids' => [
					'maintenance_1'
				],
				'expected_error' => 'Cannot delete host "api_test_hosts_maintenance_1" because maintenance "API test hosts - maintenance with one host" must contain at least one host or host group.'
			],
			'Test host.delete two hosts in maintenance, one is allowed, the other is not' => [
				'hostids' => [
					'maintenance_1',
					'maintenance_7'
				],
				'expected_error' => 'Cannot delete host "api_test_hosts_maintenance_1" because maintenance "API test hosts - maintenance with one host" must contain at least one host or host group.'
			],
			'Test host.delete both from one maintenance' => [
				'hostids' => [
					'maintenance_5',
					'maintenance_6'
				],
				'expected_error' => 'Cannot delete hosts "api_test_hosts_maintenance_5", "api_test_hosts_maintenance_6" because maintenance "API test hosts - maintenance with two hosts (non-deletable)" must contain at least one host or host group.'
			]
		];
	}

	/**
	 * Test host.delete if one host is in maintenance, more than one host is in maintenance, host and group is in
	 * maintenance etc.
	 *
	 * @dataProvider getHostDeleteDataValid
	 * @dataProvider getHostDeleteDataInvalid
	 */
	public function testHost_Delete($hostids, $expected_error) {
		// Replace ID placeholders with real IDs.
		foreach ($hostids as &$hostid) {
			$hostid = self::$data['hostids'][$hostid];
		}
		unset($hostid);

		$sql_hosts = 'SELECT NULL FROM hosts';
		$old_hash_hosts = CDBHelper::getHash($sql_hosts);

		$result = $this->call('host.delete', $hostids, $expected_error);

		if ($expected_error === null) {
			$this->assertNotSame($old_hash_hosts, CDBHelper::getHash($sql_hosts));
			$this->assertEquals(0, CDBHelper::getCount(
				'SELECT h.hostid FROM hosts h WHERE '.dbConditionId('h.hostid', $hostids)
			));

			// host.delete checks if given "hostid" exists, so they need to be removed from self::$data['hostids']
			foreach ($hostids as $hostid) {
				$key = array_search($hostid, self::$data['hostids']);
				if ($key !== false) {
					unset(self::$data['hostids'][$key]);
				}
			}
		}
		else {
			$this->assertSame($old_hash_hosts, CDBHelper::getHash($sql_hosts));
		}
	}

	/**
	 * Data provider for host.create. Array contains invalid hosts with mandatory fields, invalid field types etc.
	 *
	 * @return array
	 */
	public static function getHostCreateDataCommonInvalid() {
		return [
			'Test host.create common error - empty request' => [
				'request' => [],
				'expected_error' => 'Empty input parameter.'
			],
			'Test host.create common error - wrong fields' => [
				'request' => [
					'groups' => []
				],
				'expected_error' => 'Wrong fields for host "".'
			],
			'Test host.create common error - mssing "groups"' => [
				'request' => [
					'host' => 'API test create fail'
				],
				'expected_error' => 'Host "API test create fail" cannot be without host group.'
			],
			'Test host.create common error - empty group' => [
				'request' => [
					'host' => 'API test create fail',
					'groups' => []
				],
				'expected_error' => 'Host "API test create fail" cannot be without host group.'
			],
			'Test host.create common error - empty host name' => [
				'request' => [
					'host' => '',
					'groups' => [
						[
							'groupid' => 'ID'
						]
					]
				],
				'expected_error' => 'Incorrect characters used for host name "".'
			],
			'Test host.create common error - invalid host name' => [
				'request' => [
					'host' => '&^@%#&^',
					'groups' => [
						[
							'groupid' => 'ID'
						]
					]
				],
				'expected_error' => 'Incorrect characters used for host name "&^@%#&^".'
			],
			'Test host.create common error - missing "groupid"' => [
				'request' => [
					'host' => 'API test create fail',
					'groups' => [
						[]
					]
				],
				'expected_error' => 'Incorrect value for field "groups": the parameter "groupid" is missing.'
			],
			'Test host.create common error - invalid group' => [
				'request' => [
					'host' => 'API test create fail',
					'groups' => [
						[
							'groupid' => '01'
						]
					]
				],
				'expected_error' => 'No permissions to referred object or it does not exist!'
			],
			'Test host.create common error - host already exists' => [
				'request' => [
					'host' => 'Zabbix server',
					'groups' => [
						[
							'groupid' => 'ID'
						]
					]
				],
				'expected_error' => 'Host with the same name "Zabbix server" already exists.'
			]
		];
	}

	/**
	 * Data provider for host.create. Array contains invalid hosts with various optional fields.
	 *
	 * @return array
	 */
	public static function getHostCreateDataInvalid() {
		return [
			// Test create interfaces.
			'Test host.create interfaces (empty)' => [
				'request' => [
					'host' => 'API test create fail',
					'groups' => [
						[
							'groupid' => 'ID'
						]
					],
					'interfaces' => ''
				],
				'expected_error' => 'Incorrect arguments passed to function.'
			],
			'Test host.create interfaces (string)' => [
				'request' => [
					'host' => 'API test create fail',
					'groups' => [
						[
							'groupid' => 'ID'
						]
					],
					'interfaces' => 'string'
				],
				'expected_error' => 'Incorrect arguments passed to function.'
			],
			'Test host.create interfaces (integer)' => [
				'request' => [
					'host' => 'API test create fail',
					'groups' => [
						[
							'groupid' => 'ID'
						]
					],
					'interfaces' => '10'
				],
				'expected_error' => 'Incorrect arguments passed to function.'
			]
		];
	}

	/**
	 * Data provider for host.create. Array contains valid hosts.
	 *
	 * @return array
	 */
	public static function getHostCreateDataValid() {
		return [
			'Test host.create minimal' => [
				'request' => [
					'host' => 'API test create success minimal',
					'groups' => [
						[
							'groupid' => 'ID'
						]
					]
				],
				'expected_error' => null
			],
			'Test host.create empty interfaces' => [
				'request' => [
					'host' => 'API test create success empty interfaces',
					'groups' => [
						[
							'groupid' => 'ID'
						]
					],
					'interfaces' => []
				],
				'expected_error' => null
			]
		];
	}

	/**
	 * Test host.create with common errors like missing fields, optional invalid fields and valid fields.
	 *
	 * @dataProvider getHostCreateDataCommonInvalid
	 * @dataProvider getHostCreateDataInvalid
	 * @dataProvider getHostCreateDataValid
	 */
	public function testHost_Create($hosts, $expected_error) {
		// Accept single and multiple hosts.
		if (!array_key_exists(0, $hosts)) {
			$hosts = zbx_toArray($hosts);
		}

		// Replace ID placeholders with real IDs.
		foreach ($hosts as &$host) {
			// Some tests that should fail may not have the required fields or they may be damaged.
			if (array_key_exists('groups', $host) && $host['groups']) {
				foreach ($host['groups'] as &$group) {
					if (array_key_exists('groupid', $group) && $group['groupid'] === 'ID') {
						$group['groupid'] = self::$data['hostgroupid'];
					}
				}
				unset($group);
			}
		}
		unset($host);

		$sql_hosts = 'SELECT NULL FROM hosts';
		$old_hash_hosts = CDBHelper::getHash($sql_hosts);

		$result = $this->call('host.create', $hosts, $expected_error);

		if ($expected_error === null) {
			$this->assertNotSame($old_hash_hosts, CDBHelper::getHash($sql_hosts));

			$this->assertEquals(count($result['result']['hostids']), count($hosts));

			// Add host IDs to create array, so they can be deleted after tests are complete.
			self::$data['created'] = array_merge(self::$data['created'], $result['result']['hostids']);
		}
		else {
			$this->assertSame($old_hash_hosts, CDBHelper::getHash($sql_hosts));
		}
	}

	/**
	 * Data provider for host.get. Array contains valid host with tags.
	 *
	 * @return array
	 */
	public static function getHostGetTagsDataValid() {
		return [
			'Test host.get tag as extend' => [
				'params' => [
					'hostids' => 'tags',
					'selectTags' => 'extend'
				],
				'expected_result' => [
					'tags' => [
						[
							'tag' => 'b',
							'value' => 'b'
						]
					]
				]
			],
			'Test host.get tag excluding value' => [
				'params' => [
					'hostids' => 'tags',
					'selectTags' => [
						'tag'
					]
				],
				'expected_result' => [
					'tags' => [
						[
							'tag' => 'b'
						]
					]
				]
			],
			'Test host.get tag excluding name' => [
				'params' => [
					'hostids' => 'tags',
					'selectTags' => [
						'value'
					]
				],
				'expected_result' => [
					'tags' => [
						[
							'value' => 'b'
						]
					]
				]
			]
		];
	}

	/**
	 * Test host.get with "selectTags" option.
	 *
	 * @dataProvider getHostGetTagsDataValid
	 */
	public function testHost_SelectTags($params, $expected_result) {
		// Replace ID placeholders with real IDs. Host IDs can also be one host ID as string.
		$params['hostids'] = self::$data['hostids']['tags'];

		$result = $this->call('host.get', $params);

		foreach ($result['result'] as $host) {
			foreach ($expected_result as $field => $expected_value){
				$this->assertArrayHasKey($field, $host, 'Field should be present.');
				$this->assertEquals($host[$field], $expected_value, 'Returned value should match.');
			}
		}
	}

	public static function getHostGetFieldPresenceData() {
		return [
			'Check if {"output": "extend"} includes "inventory_mode" and excludes write-only properties' => [
				'request' => [
					'output' => 'extend',
					'hostids' => 'write_only'
				],
				'expected_result' => [
					'hostid' => '99013',
					'inventory_mode' => '-1',

					// Write-only properties.
					'tls_psk_identity' => null,
					'tls_psk' => null
				]
			],
			'Check it is not possible to select write-only fields' => [
				'request' => [
					'output' => ['host', 'tls_psk', 'tls_psk_identity'],
					'hostids' => 'write_only'
				],
				'expected_result' => [
					'hostid' => 'write_only',

					// Sample of unspecified property.
					'inventory_mode' => null,

					// Write-only properties.
					'tls_psk_identity' => null,
					'tls_psk' => null
				]
			],
			'Check direct request of inventory_mode and other properties' => [
				'request' => [
					'output' => ['inventory_mode', 'tls_connect', 'name'],
					'hostids' => 'write_only'
				],
				'expected_result' => [
					'hostid' => 'write_only',
					'inventory_mode' => '-1',

					// Samples of other specified properties.
					'tls_connect' => '1',
					'name' => 'API test hosts - write-only fields'
				]
			]
		];
	}

	/**
	 * Test host.get and field presence.
	 *
	 * @dataProvider getHostGetFieldPresenceData
	 */
	public function testHost_GetFieldPresenceAndExclusion($request, $expected_result) {
		// Replace ID placeholders with real IDs. Host IDs can also be one host ID as string.
		$request['hostids'] = self::$data['hostids']['write_only'];
		$expected_result['hostid'] = self::$data['hostids']['write_only'];

		$result = $this->call('host.get', $request, null);

		foreach ($result['result'] as $host) {
			foreach ($expected_result as $key => $value) {
				if ($value !== null) {
					$this->assertArrayHasKey($key, $host, 'Key '.$key.' should be present in host output.');
					$this->assertEquals($value, $host[$key], 'Value should match.');
				}
				else {
					$this->assertArrayNotHasKey($key, $host, 'Key '.$key.' should NOT be present in host output');
				}
			}
		}
	}

	/**
	 * Delete all created data after test.
	 */
	public static function clearData() {
		// Delete maintenances.
		CDataHelper::call('maintenance.delete', self::$data['maintenanceids']);

		// Delete hosts and templates.
		$hostids = array_values(self::$data['hostids']);
		$hostids = array_merge($hostids, self::$data['created']);
		CDataHelper::call('host.delete', $hostids);
		CDataHelper::call('template.delete', self::$data['templateids']);

		// Delete host group.
		CDataHelper::call('hostgroup.delete', [self::$data['hostgroupid']]);
	}
}
