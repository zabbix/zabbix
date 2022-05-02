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
		'templategroupid' => null,
		'templateids' => [
			'api_test_hosts_f_tpl' => null,
			'api_test_hosts_c_tpl' => null,
			'api_test_hosts_a_tpl' => null,
			'api_test_hosts_e_tpl' => null,
			'api_test_hosts_b_tpl' => null,
			'api_test_hosts_d_tpl' => null,
			'api_test_hosts_tpl_with_item' => null
		],
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
			]
		];
		$maintenances = CDataHelper::call('maintenance.create', $maintenances_data);
		$this->assertArrayHasKey('maintenanceids', $maintenances);
		self::$data['maintenanceids'] = $maintenances['maintenanceids'];

		// Create host group.
		$templategroups = CDataHelper::call('templategroup.create', [
			[
				'name' => 'API test templates'
			]
		]);
		$this->assertArrayHasKey('groupids', $templategroups);
		self::$data['templategroupid'] = $templategroups['groupids'][0];

		// Create templates that will be added to host prototypes and essentially discovered hosts.
		$templates_data = [
			// Templates for discovered hosts. When host.get "limitSelects" is used, templates should be sorted A-Z.
			[
				'host' => 'api_test_hosts_f_tpl',
				'name' => 'API test hosts - F template',
				'groups' => [
					[
						'groupid' => self::$data['templategroupid']
					]
				]
			],
			[
				'host' => 'api_test_hosts_c_tpl',
				'name' => 'API test hosts - C template',
				'groups' => [
					[
						'groupid' => self::$data['templategroupid']
					]
				]
			],
			[
				'host' => 'api_test_hosts_a_tpl',
				'name' => 'API test hosts - A template',
				'groups' => [
					[
						'groupid' => self::$data['templategroupid']
					]
				]
			],
			[
				'host' => 'api_test_hosts_e_tpl',
				'name' => 'API test hosts - E template',
				'groups' => [
					[
						'groupid' => self::$data['templategroupid']
					]
				]
			],
			[
				'host' => 'api_test_hosts_b_tpl',
				'name' => 'API test hosts - B template',
				'groups' => [
					[
						'groupid' => self::$data['templategroupid']
					]
				]
			],
			[
				'host' => 'api_test_hosts_d_tpl',
				'name' => 'API test hosts - D template',
				'groups' => [
					[
						'groupid' => self::$data['templategroupid']
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
						'groupid' => self::$data['templategroupid']
					]
				]
			]
		];
		$templates = CDataHelper::call('template.create', $templates_data);
		$this->assertArrayHasKey('templateids', $templates);
		self::$data['templateids']['api_test_hosts_f_tpl'] = $templates['templateids'][0];
		self::$data['templateids']['api_test_hosts_c_tpl'] = $templates['templateids'][1];
		self::$data['templateids']['api_test_hosts_a_tpl'] = $templates['templateids'][2];
		self::$data['templateids']['api_test_hosts_e_tpl'] = $templates['templateids'][3];
		self::$data['templateids']['api_test_hosts_b_tpl'] = $templates['templateids'][4];
		self::$data['templateids']['api_test_hosts_d_tpl'] = $templates['templateids'][5];
		self::$data['templateids']['api_test_hosts_tpl_with_item'] = $templates['templateids'][6];

		/*
		 * Create item for that one template. Item IDs do not matter, because there is only one and it will removed
		 * after tests are complete together with this template.
		 */
		$items = CDataHelper::call('item.create', [
			'hostid' => self::$data['templateids']['api_test_hosts_tpl_with_item'],
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
						'templateid' => self::$data['templateids']['api_test_hosts_f_tpl']
					],
					[
						'templateid' => self::$data['templateids']['api_test_hosts_c_tpl']
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
						'templateid' => self::$data['templateids']['api_test_hosts_f_tpl']
					],
					[
						'templateid' => self::$data['templateids']['api_test_hosts_c_tpl']
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
						'templateid' => self::$data['templateids'][ 'api_test_hosts_tpl_with_item']
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

				// Does not matter if templates are manually added or automatically. Important is the order.
				'templates' => [
					[
						'templateid' => self::$data['templateids'][ 'api_test_hosts_f_tpl']
					],
					[
						'templateid' => self::$data['templateids'][ 'api_test_hosts_c_tpl']
					],
					[
						'templateid' => self::$data['templateids'][ 'api_test_hosts_a_tpl']
					],
					[
						'templateid' => self::$data['templateids'][ 'api_test_hosts_e_tpl']
					],
					[
						'templateid' => self::$data['templateids'][ 'api_test_hosts_b_tpl']
					],
					[
						'templateid' => self::$data['templateids'][ 'api_test_hosts_d_tpl']
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

			if (array_key_exists('templates', $host_prototype) && $host_prototype['templates']) {
				foreach ($host_prototype['templates'] as $template) {
					$upd_hosts_templates[] = [
						'values' => ['link_type' => TEMPLATE_LINK_LLD],
						'where' => [
							'hostid' => $hosts['hostids'][$idx],
							'templateid' => $template['templateid']
						]
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

		/*
		 * Add few manual templates to discovered hosts to test the template removal. Do not use API at this point.
		 * Cannot use CDBHelper::getValue, because it will add "LIMIT 1" at the end of query and MySQL does not support
		 * a syntaxt like that.
		 */
		$nextid = CDBHelper::getAll(
			'SELECT i.nextid'.
			' FROM ids i'.
			' WHERE i.table_name='.zbx_dbstr('hosts_templates').
				' AND i.field_name='.zbx_dbstr('hosttemplateid').
			' FOR UPDATE'
		)[0]['nextid'] + 1;
		$hosts_templates_data = [
			// Host contains only manual templates.
			[
				'hostid' => self::$data['hostids']['discovered_manual_templates'],
				'templateid' => self::$data['templateids']['api_test_hosts_a_tpl'],
				'link_type' => TEMPLATE_LINK_MANUAL
			],
			[
				'hostid' => self::$data['hostids']['discovered_manual_templates'],
				'templateid' => self::$data['templateids']['api_test_hosts_e_tpl'],
				'link_type' => TEMPLATE_LINK_MANUAL
			],

			// Host contains automatically added templates and manually added templates.
			[
				'hostid' => self::$data['hostids']['discovered_auto_and_manual_templates'],
				'templateid' => self::$data['templateids']['api_test_hosts_a_tpl'],
				'link_type' => TEMPLATE_LINK_MANUAL
			],
			[
				'hostid' => self::$data['hostids']['discovered_auto_and_manual_templates'],
				'templateid' => self::$data['templateids']['api_test_hosts_e_tpl'],
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

		$this->call('host.delete', $hostids, $expected_error);

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
					'host' => 'API test hosts create fail'
				],
				'expected_error' => 'Host "API test hosts create fail" cannot be without host group.'
			],
			'Test host.create common error - empty group' => [
				'request' => [
					'host' => 'API test hosts create fail',
					'groups' => []
				],
				'expected_error' => 'Host "API test hosts create fail" cannot be without host group.'
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
					'host' => 'API test hosts create fail',
					'groups' => [
						[]
					]
				],
				'expected_error' => 'Incorrect value for field "groups": the parameter "groupid" is missing.'
			],
			'Test host.create common error - invalid group' => [
				'request' => [
					'host' => 'API test hosts create fail',
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
					'host' => 'API test hosts create fail',
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
					'host' => 'API test hosts create fail',
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
					'host' => 'API test hosts create fail',
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
					'host' => 'API test hosts create success minimal',
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
					'host' => 'API test hosts create success empty interfaces',
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
					'selectTags' => API_OUTPUT_EXTEND
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

	/**
	 * Data provider for host.get to check field presence and exclusion.
	 *
	 * @return array
	 */
	public static function getHostGetFieldPresenceData() {
		return [
			'Check if {"output": "extend"} includes "inventory_mode" and excludes write-only properties' => [
				'request' => [
					'output' => API_OUTPUT_EXTEND,
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
	 * Test host.get and field presence and exclusion.
	 *
	 * @dataProvider getHostGetFieldPresenceData
	 */
	public function testHost_GetFieldPresenceAndExclusion($request, $expected_result) {
		// Replace ID placeholders with real IDs. Host IDs can also be one host ID as string.
		$request['hostids'] = self::$data['hostids']['write_only'];
		$expected_result['hostid'] = self::$data['hostids']['write_only'];

		$result = $this->call('host.get', $request);

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
	 * Data provider for host.update to check how templates are replaced.
	 *
	 * @return array
	 */
	public static function getHostUpdateTemplatesData() {
		return [
			'Test host.update - host has no templates' => [
				'request' => [
					'hostid' => 'discovered_no_templates',
					'templates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl'
						],
						[
							'templateid' => 'api_test_hosts_c_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						]
					]
				]
			],

			// Replace the templates.
			'Test host.update - host has manual templates' => [
				'request' => [
					'hostid' => 'discovered_manual_templates',
					'templates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl'
						],
						[
							'templateid' => 'api_test_hosts_c_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						]
					]
				]
			],

			// Try to replace with manual templates, but it's not possible. They will remain auto.
			'Test host.update - host has auto templates (same)' => [
				'request' => [
					'hostid' => 'discovered_auto_templates',
					'templates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl'
						],
						[
							'templateid' => 'api_test_hosts_c_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						]
					]
				]
			],

			// Try to replace the templates, but only manual templates are added, since original ones are auto.
			'Test host.update - host has auto templates (different)' => [
				'request' => [
					'hostid' => 'discovered_auto_templates',
					'templates' => [
						[
							'templateid' => 'api_test_hosts_a_tpl'
						],
						[
							'templateid' => 'api_test_hosts_e_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						],
						[
							'templateid' => 'api_test_hosts_a_tpl',
							'host' => 'api_test_hosts_a_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						],
						[
							'templateid' => 'api_test_hosts_e_tpl',
							'host' => 'api_test_hosts_e_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						]
					]
				]
			],

			// Try to replace the templates, but only manual templates are replaced. Auto templates remain.
			'Test host.update - host has manual and auto templates' => [
				'request' => [
					'hostid' => 'discovered_auto_and_manual_templates',
					'templates' => [
						[
							'templateid' => 'api_test_hosts_b_tpl'
						],
						[
							'templateid' => 'api_test_hosts_d_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						],
						[
							'templateid' => 'api_test_hosts_b_tpl',
							'host' => 'api_test_hosts_b_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						],
						[
							'templateid' => 'api_test_hosts_d_tpl',
							'host' => 'api_test_hosts_d_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						]
					]
				]
			],

			/*
			 * Possibly incorrect behavior in API in case templates that are added are same as the ones that should be
			 * cleared. Currenlty templates that are added take priority. They do not cancel each other out.
			 */
			'Test host.update with clear - host has no templates (same)' => [
				'request' => [
					'hostid' => 'discovered_no_templates',
					'templates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl'
						],
						[
							'templateid' => 'api_test_hosts_c_tpl'
						]
					],
					'templates_clear' => [
						[
							'templateid' => 'api_test_hosts_f_tpl'
						],
						[
							'templateid' => 'api_test_hosts_c_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						]
					]
				]
			],
			'Test host.update with clear - host has no templates (different)' => [
				'request' => [
					'hostid' => 'discovered_no_templates',
					'templates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl'
						],
						[
							'templateid' => 'api_test_hosts_c_tpl'
						]
					],
					'templates_clear' => [
						[
							'templateid' => 'api_test_hosts_a_tpl'
						],
						[
							'templateid' => 'api_test_hosts_e_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						]
					]
				]
			],
			'Test host.update with clear only - host has no templates' => [
				'request' => [
					'hostid' => 'discovered_no_templates',
					'templates_clear' => [
						[
							'templateid' => 'api_test_hosts_a_tpl'
						],
						[
							'templateid' => 'api_test_hosts_e_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => []
				]
			],
			'Test host.update with clear - host has manual templates (same)' => [
				'request' => [
					'hostid' => 'discovered_manual_templates',
					'templates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl'
						],
						[
							'templateid' => 'api_test_hosts_c_tpl'
						]
					],
					'templates_clear' => [
						[
							'templateid' => 'api_test_hosts_f_tpl'
						],
						[
							'templateid' => 'api_test_hosts_c_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						]
					]
				]
			],
			'Test host.update with clear non-existing - host has manual templates' => [
				'request' => [
					'hostid' => 'discovered_manual_templates',
					'templates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl'
						],
						[
							'templateid' => 'api_test_hosts_a_tpl'
						]
					],
					'templates_clear' => [
						[
							'templateid' => 'api_test_hosts_c_tpl'
						],
						[
							'templateid' => 'api_test_hosts_e_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						],
						[
							'templateid' => 'api_test_hosts_a_tpl',
							'host' => 'api_test_hosts_a_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						]
					]
				]
			],
			'Test host.update with clear existing - host has manual templates' => [
				'request' => [
					'hostid' => 'discovered_manual_templates',
					'templates' => [
						[
							'templateid' => 'api_test_hosts_a_tpl'
						]
					],
					'templates_clear' => [
						[
							'templateid' => 'api_test_hosts_e_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_a_tpl',
							'host' => 'api_test_hosts_a_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						]
					]
				]
			],
			'Test host.update with clear non-existing - host has auto templates' => [
				'request' => [
					'hostid' => 'discovered_auto_templates',
					'templates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl'
						]
					],
					'templates_clear' => [
						[
							'templateid' => 'api_test_hosts_e_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						]
					]
				]
			],
			'Test host.update with clear existing - host has auto templates' => [
				'request' => [
					'hostid' => 'discovered_auto_templates',
					'templates' => [
						[
							'templateid' => 'api_test_hosts_c_tpl'
						]
					],
					'templates_clear' => [
						[
							'templateid' => 'api_test_hosts_f_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						]
					]
				]
			],
			'Test host.update with clear - host has auto templates' => [
				'request' => [
					'hostid' => 'discovered_auto_templates',
					'templates' => [
						[
							'templateid' => 'api_test_hosts_a_tpl'
						],
						[
							'templateid' => 'api_test_hosts_e_tpl'
						],
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						]
					],
					'templates_clear' => [
						[
							'templateid' => 'api_test_hosts_a_tpl'
						],
						[
							'templateid' => 'api_test_hosts_c_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						],
						[
							'templateid' => 'api_test_hosts_a_tpl',
							'host' => 'api_test_hosts_a_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						],
						[
							'templateid' => 'api_test_hosts_e_tpl',
							'host' => 'api_test_hosts_e_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						]
					]
				]
			]
		];
	}

	/**
	 * Test host.update by adding new templates and replacing templates.
	 *
	 * @dataProvider getHostUpdateTemplatesData
	 */
	public function testHost_UpdateTemplates($request, $expected_result) {
		// Replace ID placeholders with real IDs.
		$request['hostid'] = self::$data['hostids'][$request['hostid']];

		foreach (['templates', 'templates_clear'] as $field) {
			if (array_key_exists($field, $request)) {
				foreach ($request[$field] as &$template) {
					$template['templateid'] = self::$data['templateids'][$template['templateid']];
				}
				unset($template);
			}
		}

		foreach ($expected_result['parentTemplates'] as &$template) {
			$template['templateid'] = self::$data['templateids'][$template['templateid']];
		}
		unset($template);

		$hosts_old = $this->store([$request['hostid']]);

		// Update templates on hosts.
		$hosts_upd = $this->call('host.update', $request);
		$this->assertArrayHasKey('hostids', $hosts_upd['result']);

		// Check data after update.
		$hosts = $this->call('host.get', [
			'output' => ['hostid', 'host'],
			'selectParentTemplates' => ['templateid', 'host', 'link_type'],
			'hostids' => $request['hostid']
		]);
		$host = reset($hosts['result']);

		$this->assertSame($expected_result['parentTemplates'], $host['parentTemplates'],
			'host.update with templates failed for host "'.$host['host'].'".'
		);

		$this->revert($hosts_old);
	}

	/**
	 * Data provider for host.massupdate to check how templates are replaced.
	 *
	 * @return array
	 */
	public static function getHostMassUpdateTemplatesData() {
		return [
			'Test host.massupdate - host has no templates' => [
				'request' => [
					'hosts' => [
						[
							'hostid' => 'discovered_no_templates'
						]
					],
					'templates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl'
						],
						[
							'templateid' => 'api_test_hosts_c_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						]
					]
				]
			],

			// Replace the templates.
			'Test host.massupdate - host has manual templates' => [
				'request' => [
					'hosts' => [
						[
							'hostid' => 'discovered_manual_templates'
						]
					],
					'templates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl'
						],
						[
							'templateid' => 'api_test_hosts_c_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						]
					]
				]
			],

			// Try to replace with manual templates, but it's not possible. They will remain auto.
			'Test host.massupdate - host has auto templates (same)' => [
				'request' => [
					'hosts' => [
						[
							'hostid' => 'discovered_auto_templates'
						]
					],
					'templates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl'
						],
						[
							'templateid' => 'api_test_hosts_c_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						]
					]
				]
			],

			// Try to replace the templates, but only manual templates are added, since original ones are auto.
			'Test host.massupdate - host has auto templates (different)' => [
				'request' => [
					'hosts' => [
						[
							'hostid' => 'discovered_auto_templates'
						]
					],
					'templates' => [
						[
							'templateid' => 'api_test_hosts_a_tpl'
						],
						[
							'templateid' => 'api_test_hosts_e_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						],
						[
							'templateid' => 'api_test_hosts_a_tpl',
							'host' => 'api_test_hosts_a_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						],
						[
							'templateid' => 'api_test_hosts_e_tpl',
							'host' => 'api_test_hosts_e_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						]
					]
				]
			],

			// Try to replace the templates, but only manual templates are replaced. Auto templates remain.
			'Test host.massupdate - host has manual and auto templates' => [
				'request' => [
					'hosts' => [
						[
							'hostid' => 'discovered_auto_and_manual_templates'
						]
					],
					'templates' => [
						[
							'templateid' => 'api_test_hosts_b_tpl'
						],
						[
							'templateid' => 'api_test_hosts_d_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						],
						[
							'templateid' => 'api_test_hosts_b_tpl',
							'host' => 'api_test_hosts_b_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						],
						[
							'templateid' => 'api_test_hosts_d_tpl',
							'host' => 'api_test_hosts_d_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						]
					]
				]
			],

			/*
			 * Possibly incorrect behavior in API in case templates that are added are same as the ones that should be
			 * cleared. Currenlty templates that are added take priority. They do not cancel each other out.
			 */
			'Test host.massupdate with clear - host has no templates (same)' => [
				'request' => [
					'hosts' => [
						[
							'hostid' => 'discovered_no_templates'
						]
					],
					'templates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl'
						],
						[
							'templateid' => 'api_test_hosts_c_tpl'
						]
					],
					'templates_clear' => [
						[
							'templateid' => 'api_test_hosts_f_tpl'
						],
						[
							'templateid' => 'api_test_hosts_c_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						]
					]
				]
			],
			'Test host.massupdate with clear - host has no templates (different)' => [
				'request' => [
					'hosts' => [
						[
							'hostid' => 'discovered_no_templates'
						]
					],
					'templates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl'
						],
						[
							'templateid' => 'api_test_hosts_c_tpl'
						]
					],
					'templates_clear' => [
						[
							'templateid' => 'api_test_hosts_a_tpl'
						],
						[
							'templateid' => 'api_test_hosts_e_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						]
					]
				]
			],
			'Test host.massupdate with clear only - host has no templates' => [
				'request' => [
					'hosts' => [
						[
							'hostid' => 'discovered_no_templates'
						]
					],
					'templates_clear' => [
						[
							'templateid' => 'api_test_hosts_a_tpl'
						],
						[
							'templateid' => 'api_test_hosts_e_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => []
				]
			],
			'Test host.massupdate with clear - host has manual templates (same)' => [
				'request' => [
					'hosts' => [
						[
							'hostid' => 'discovered_manual_templates'
						]
					],
					'templates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl'
						],
						[
							'templateid' => 'api_test_hosts_c_tpl'
						]
					],
					'templates_clear' => [
						[
							'templateid' => 'api_test_hosts_f_tpl'
						],
						[
							'templateid' => 'api_test_hosts_c_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						]
					]
				]
			],
			'Test host.massupdate with clear non-existing - host has manual templates' => [
				'request' => [
					'hosts' => [
						[
							'hostid' => 'discovered_manual_templates'
						]
					],
					'templates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl'
						],
						[
							'templateid' => 'api_test_hosts_a_tpl'
						]
					],
					'templates_clear' => [
						[
							'templateid' => 'api_test_hosts_c_tpl'
						],
						[
							'templateid' => 'api_test_hosts_e_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						],
						[
							'templateid' => 'api_test_hosts_a_tpl',
							'host' => 'api_test_hosts_a_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						]
					]
				]
			],
			'Test host.massupdate with clear existing - host has manual templates' => [
				'request' => [
					'hosts' => [
						[
							'hostid' => 'discovered_manual_templates'
						]
					],
					'templates' => [
						[
							'templateid' => 'api_test_hosts_a_tpl'
						]
					],
					'templates_clear' => [
						[
							'templateid' => 'api_test_hosts_e_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_a_tpl',
							'host' => 'api_test_hosts_a_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						]
					]
				]
			],
			'Test host.massupdate with clear non-existing - host has auto templates' => [
				'request' => [
					'hosts' => [
						[
							'hostid' => 'discovered_auto_templates'
						]
					],
					'templates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl'
						]
					],
					'templates_clear' => [
						[
							'templateid' => 'api_test_hosts_e_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						]
					]
				]
			],
			'Test host.massupdate with clear existing - host has auto templates' => [
				'request' => [
					'hosts' => [
						[
							'hostid' => 'discovered_auto_templates'
						]
					]	,
					'templates' => [
						[
							'templateid' => 'api_test_hosts_c_tpl'
						]
					],
					'templates_clear' => [
						[
							'templateid' => 'api_test_hosts_f_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						]
					]
				]
			],
			'Test host.massupdate with clear - host has auto templates' => [
				'request' => [
					'hosts' => [
						[
							'hostid' => 'discovered_auto_templates'
						]
					],
					'templates' => [
						[
							'templateid' => 'api_test_hosts_a_tpl'
						],
						[
							'templateid' => 'api_test_hosts_e_tpl'
						],
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						]
					],
					'templates_clear' => [
						[
							'templateid' => 'api_test_hosts_a_tpl'
						],
						[
							'templateid' => 'api_test_hosts_c_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						],
						[
							'templateid' => 'api_test_hosts_a_tpl',
							'host' => 'api_test_hosts_a_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						],
						[
							'templateid' => 'api_test_hosts_e_tpl',
							'host' => 'api_test_hosts_e_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						]
					]
				]
			],
			'Test host.massupdate with clear - host has manual and auto templates' => [
				'request' => [
					'hosts' => [
						[
							'hostid' => 'discovered_auto_and_manual_templates'
						]
					],
					'templates' => [
						[
							'templateid' => 'api_test_hosts_e_tpl'
						],
						[
							'templateid' => 'api_test_hosts_f_tpl'
						],
						[
							'templateid' => 'api_test_hosts_b_tpl'
						]
					],
					'templates_clear' => [
						[
							'templateid' => 'api_test_hosts_a_tpl'
						],
						[
							'templateid' => 'api_test_hosts_c_tpl'
						],
						[
							'templateid' => 'api_test_hosts_d_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						],
						[
							'templateid' => 'api_test_hosts_e_tpl',
							'host' => 'api_test_hosts_e_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						],
						[
							'templateid' => 'api_test_hosts_b_tpl',
							'host' => 'api_test_hosts_b_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						]
					]
				]
			]
		];
	}

	/**
	 * Test host.massupdate by adding new templates and replacing templates.
	 *
	 * @dataProvider getHostMassUpdateTemplatesData
	 */
	public function testHost_MassUpdateTemplates($request, $expected_result) {
		// Replace ID placeholders with real IDs.
		$hostids = [];

		foreach ($request['hosts'] as &$host) {
			$host['hostid'] = self::$data['hostids'][$host['hostid']];
			$hostids[] = $host['hostid'];
		}
		unset($host);

		foreach (['templates', 'templates_clear'] as $field) {
			if (array_key_exists($field, $request)) {
				foreach ($request[$field] as &$template) {
					$template['templateid'] = self::$data['templateids'][$template['templateid']];
				}
				unset($template);
			}
		}

		foreach ($expected_result['parentTemplates'] as &$template) {
			$template['templateid'] = self::$data['templateids'][$template['templateid']];
		}
		unset($template);

		$hosts_old = $this->store($hostids);

		// Update templates on hosts.
		$hosts_upd = $this->call('host.massupdate', $request);
		$this->assertArrayHasKey('hostids', $hosts_upd['result']);

		// Check data after update.
		$hosts = $this->call('host.get', [
			'output' => ['hostid', 'host'],
			'selectParentTemplates' => ['templateid', 'host', 'link_type'],
			'hostids' => $hostids
		]);
		$hosts = $hosts['result'];

		foreach ($hosts as $host) {
			$this->assertSame($expected_result['parentTemplates'], $host['parentTemplates'],
				'host.massupdate with templates failed for host "'.$host['host'].'".'
			);
		}

		$this->revert($hosts_old);
	}

	/**
	 * Data provider for host.massadd to check how templates are added.
	 *
	 * @return array
	 */
	public static function getHostMassAddTemplatesData() {
		return [
			'Test host.massadd - host has no templates' => [
				'request' => [
					'hosts' => [
						[
							'hostid' => 'discovered_no_templates'
						]
					],
					'templates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl'
						],
						[
							'templateid' => 'api_test_hosts_c_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						]
					]
				]
			],

			// Add templates to exising manual templates.
			'Test host.massadd - host has manual templates' => [
				'request' => [
					'hosts' => [
						[
							'hostid' => 'discovered_manual_templates'
						]
					],
					'templates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl'
						],
						[
							'templateid' => 'api_test_hosts_c_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						],
						[
							'templateid' => 'api_test_hosts_a_tpl',
							'host' => 'api_test_hosts_a_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						],
						[
							'templateid' => 'api_test_hosts_e_tpl',
							'host' => 'api_test_hosts_e_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						]
					]
				]
			],

			// Add same templates to exising auto templates. Not possible, since those templates are already auto.
			'Test host.massadd - host has auto templates (same)' => [
				'request' => [
					'hosts' => [
						[
							'hostid' => 'discovered_auto_templates'
						]
					],
					'templates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl'
						],
						[
							'templateid' => 'api_test_hosts_c_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						]
					]
				]
			],

			// Add different templates to exising auto templates.
			'Test host.massadd - host has auto templates (different)' => [
				'request' => [
					'hosts' => [
						[
							'hostid' => 'discovered_auto_templates'
						]
					],
					'templates' => [
						[
							'templateid' => 'api_test_hosts_a_tpl'
						],
						[
							'templateid' => 'api_test_hosts_e_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						],
						[
							'templateid' => 'api_test_hosts_a_tpl',
							'host' => 'api_test_hosts_a_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						],
						[
							'templateid' => 'api_test_hosts_e_tpl',
							'host' => 'api_test_hosts_e_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						]
					]
				]
			],

			// Add templates to exising auto and manual templates.
			'Test host.massadd - host has manual and auto templates' => [
				'request' => [
					'hosts' => [
						[
							'hostid' => 'discovered_auto_and_manual_templates'
						]
					],
					'templates' => [
						[
							'templateid' => 'api_test_hosts_b_tpl'
						],
						[
							'templateid' => 'api_test_hosts_d_tpl'
						]
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						],
						[
							'templateid' => 'api_test_hosts_a_tpl',
							'host' => 'api_test_hosts_a_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						],
						[
							'templateid' => 'api_test_hosts_e_tpl',
							'host' => 'api_test_hosts_e_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						],
						[
							'templateid' => 'api_test_hosts_b_tpl',
							'host' => 'api_test_hosts_b_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						],
						[
							'templateid' => 'api_test_hosts_d_tpl',
							'host' => 'api_test_hosts_d_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						]
					]
				]
			]
		];
	}

	/**
	 * Test host.massadd by adding new templates to exising manual and auto templates.
	 *
	 * @dataProvider getHostMassAddTemplatesData
	 */
	public function testHost_MassAddTemplates($request, $expected_result) {
		// Replace ID placeholders with real IDs.
		$hostids = [];

		foreach ($request['hosts'] as &$host) {
			$host['hostid'] = self::$data['hostids'][$host['hostid']];
			$hostids[] = $host['hostid'];
		}
		unset($host);

		foreach ($request['templates'] as &$template) {
			$template['templateid'] = self::$data['templateids'][$template['templateid']];
		}
		unset($template);

		foreach ($expected_result['parentTemplates'] as &$template) {
			$template['templateid'] = self::$data['templateids'][$template['templateid']];
		}
		unset($template);

		$hosts_old = $this->store($hostids);

		// Add templates on hosts.
		$hosts_upd = $this->call('host.massadd', $request);
		$this->assertArrayHasKey('hostids', $hosts_upd['result']);

		// Check data after update.
		$hosts = $this->call('host.get', [
			'output' => ['hostid', 'host'],
			'selectParentTemplates' => ['templateid', 'host', 'link_type'],
			'hostids' => $hostids
		]);
		$hosts = $hosts['result'];

		foreach ($hosts as $host) {
			$this->assertSame($expected_result['parentTemplates'], $host['parentTemplates'],
				'host.massadd with templates failed for host "'.$host['host'].'".'
			);
		}

		$this->revert($hosts_old);
	}

	/**
	 * Data provider for host.massremove to check how templates are removed.
	 *
	 * @return array
	 */
	public static function getHostMassRemoveTemplatesData() {
		return [
			'Test host.massremove - host has no templates' => [
				'request' => [
					'hostids' => [
						'discovered_no_templates'
					],
					'templateids' => [
						'api_test_hosts_f_tpl',
						'api_test_hosts_c_tpl'
					]
				],
				'expected_result' => [
					'parentTemplates' => []
				]
			],
			'Test host.massremove - host has manual templates' => [
				'request' => [
					'hostids' => [
						'discovered_manual_templates'
					],
					'templateids' => [
						'api_test_hosts_a_tpl'
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_e_tpl',
							'host' => 'api_test_hosts_e_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						]
					]
				]
			],
			'Test host.massremove - host has auto templates' => [
				'request' => [
					'hostids' => [
						'discovered_auto_templates'
					],
					'templateids' => [
						'api_test_hosts_f_tpl'
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						]
					]
				]
			],
			'Test host.massremove - host has manual and auto templates' => [
				'request' => [
					'hostids' => [
						'discovered_auto_and_manual_templates'
					],
					'templateids' => [
						'api_test_hosts_f_tpl',
						'api_test_hosts_a_tpl'
					]
				],
				'expected_result' => [
					'parentTemplates' => [
						[
							'templateid' => 'api_test_hosts_f_tpl',
							'host' => 'api_test_hosts_f_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						],
						[
							'templateid' => 'api_test_hosts_c_tpl',
							'host' => 'api_test_hosts_c_tpl',
							'link_type' => (string) TEMPLATE_LINK_LLD
						],
						[
							'templateid' => 'api_test_hosts_e_tpl',
							'host' => 'api_test_hosts_e_tpl',
							'link_type' => (string) TEMPLATE_LINK_MANUAL
						]
					]
				]
			]
		];
	}

	/**
	 * Test host.massremove to remove the templates from hosts.
	 *
	 * @dataProvider getHostMassRemoveTemplatesData
	 */
	public function testHost_MassRemoveTemplates($request, $expected_result) {
		// Replace ID placeholders with real IDs.
		foreach ($request['hostids'] as &$hostid) {
			$hostid = self::$data['hostids'][$hostid];
		}
		unset($hostid);

		foreach ($request['templateids'] as &$templateid) {
			$templateid = self::$data['templateids'][$templateid];
		}
		unset($templateid);

		foreach ($expected_result['parentTemplates'] as &$template) {
			$template['templateid'] = self::$data['templateids'][$template['templateid']];
		}
		unset($template);

		$hosts_old = $this->store($request['hostids']);

		// Add templates on hosts.
		$hosts_upd = $this->call('host.massremove', $request);
		$this->assertArrayHasKey('hostids', $hosts_upd['result']);

		// Check data after update.
		$hosts = $this->call('host.get', [
			'output' => ['hostid', 'host'],
			'selectParentTemplates' => ['templateid', 'host', 'link_type'],
			'hostids' => $request['hostids']
		]);
		$hosts = $hosts['result'];

		foreach ($hosts as $host) {
			$this->assertSame($expected_result['parentTemplates'], $host['parentTemplates'],
				'host.massremove with templates failed for host "'.$host['host'].'".'
			);
		}

		$this->revert($hosts_old);
	}

	/**
	 * Data provider for host.update to check inheritance.
	 *
	 * @return array
	 */
	public static function getHostInheritanceData() {
		return [
			'Test host.update inheritance - host has no templates' => [
				'request' => [
					'hostid' => 'discovered_no_templates',
					// Add template and then check if results match.
					'templates' => [
						[
							'templateid' => 'api_test_hosts_tpl_with_item'
						]
					]
				],
				'expected_results' => [
					'update' => [
						'item_keys' => [
							'api_test_hosts_tpl_item'
						]
					],
					'unlink' => [
						'item_keys' => [
							'api_test_hosts_tpl_item'
						]
					],
					'clear' => [
						'item_keys' => []
					]
				]
			],
			'Test host.update inheritance - host has auto templates' => [
				'request' => [
					'hostid' => 'discovered_clear',
					// This does not matter because auto templates cannot be replaced.
					'templates' => []
				],
				'expected_results' => [
					'update' => [
						'item_keys' => [
							'api_test_hosts_tpl_item'
						]
					],
					'unlink' => [
						'item_keys' => [
							'api_test_hosts_tpl_item'
						]
					],
					'clear' => [
						'item_keys' => [
							'api_test_hosts_tpl_item'
						]
					]
				]
			]
		];
	}

	/**
	 * Test host.update and check if item(s) exist on host after template has been added. Test unlink and check if items
	 * remain on host. Test clear and check if items are removed from host as well.
	 *
	 * @dataProvider getHostInheritanceData
	 */
	public function testHost_Inheritance($host, $expected_results) {
		// Replace ID placeholder with real ID.
		$host['hostid'] = self::$data['hostids'][$host['hostid']];

		foreach ($host['templates'] as &$template) {
			$template['templateid'] = self::$data['templateids'][$template['templateid']];
		}
		unset($template);

		$hosts_old = $this->store([$host['hostid']]);

		// Add/replace templates on host and check if items are inherited on host.
		$this->call('host.update', $host);
		$item_keys = $this->getItemKeysOnHost($host['hostid']);
		$this->assertSame($expected_results['update']['item_keys'], $item_keys,
			'Updating templates failed: mismatching results on host with ID "'.$host['hostid'].'".'
		);

		// Then unlink the template from host and check if item still exists on host.
		$this->revert($hosts_old);
		$item_keys = $this->getItemKeysOnHost($host['hostid']);
		$this->assertSame($expected_results['unlink']['item_keys'], $item_keys,
			'Unlinking templates failed: mismatching results on host with ID "'.$host['hostid'].'".'
		);

		// Add/replace templates on host again to make the link and check if items are still on host.
		$this->call('host.update', $host);
		$item_keys = $this->getItemKeysOnHost($host['hostid']);
		$this->assertSame($expected_results['update']['item_keys'], $item_keys,
			'Re-updating templates failed: mismatching results on host with ID "'.$host['hostid'].'".'
		);

		// Then clear the template from host and check if items no longer exist on host.
		$hosts = $this->call('host.get', [
			'output' => ['hostid', 'host'],
			'selectParentTemplates' => ['templateid'],
			'hostids' => [$host['hostid']]
		]);
		$this->revert($hosts['result'], true);
		$item_keys = $this->getItemKeysOnHost($host['hostid']);
		$this->assertSame($expected_results['clear']['item_keys'], $item_keys,
			'Clearing templates failed: mismatching results on host with ID "'.$host['hostid'].'".'
		);
	}

	/**
	 * Data provider for host.get to check templates.
	 *
	 * @return array
	 */
	public static function getHostGetTemplatesData() {
		return [
			'Test host.get - host has no templates' => [
				'request' => [
					'output' => ['hostid'],
					'hostids' => [
						'discovered_no_templates'
					],
					'selectParentTemplates' => [
						'templateid', 'host', 'link_type'
					]
				],
				'expected_results' => [
					[
						'hostid' => 'discovered_no_templates',
						'parentTemplates' => []
					]
				]
			],
			'Test host.get - host has manual templates' => [
				'request' => [
					'output' => ['hostid'],
					'hostids' => [
						'discovered_manual_templates'
					],
					'selectParentTemplates' => [
						'templateid', 'host', 'link_type'
					]
				],
				'expected_results' => [
					[
						'hostid' => 'discovered_manual_templates',
						'parentTemplates' => [
							[
								'templateid' => 'api_test_hosts_a_tpl',
								'host' => 'api_test_hosts_a_tpl',
								'link_type' => (string) TEMPLATE_LINK_MANUAL
							],
							[
								'templateid' => 'api_test_hosts_e_tpl',
								'host' => 'api_test_hosts_e_tpl',
								'link_type' => (string) TEMPLATE_LINK_MANUAL
							]
						]
					]
				]
			],
			'Test host.get - host has auto templates' => [
				'request' => [
					'output' => ['hostid'],
					'hostids' => [
						'discovered_auto_templates'
					],
					'selectParentTemplates' => [
						'templateid', 'host', 'link_type'
					]
				],
				'expected_results' => [
					[
						'hostid' => 'discovered_auto_templates',
						'parentTemplates' => [
							[
								'templateid' => 'api_test_hosts_f_tpl',
								'host' => 'api_test_hosts_f_tpl',
								'link_type' => (string) TEMPLATE_LINK_LLD
							],
							[
								'templateid' => 'api_test_hosts_c_tpl',
								'host' => 'api_test_hosts_c_tpl',
								'link_type' => (string) TEMPLATE_LINK_LLD
							]
						]
					]
				]
			],
			'Test host.get - host has auto and manual templates' => [
				'request' => [
					'output' => ['hostid'],
					'hostids' => [
						'discovered_auto_and_manual_templates'
					],
					'selectParentTemplates' => [
						'templateid', 'host', 'link_type'
					]
				],
				'expected_results' => [
					[
						'hostid' => 'discovered_auto_and_manual_templates',
						'parentTemplates' => [
							[
								'templateid' => 'api_test_hosts_f_tpl',
								'host' => 'api_test_hosts_f_tpl',
								'link_type' => (string) TEMPLATE_LINK_LLD
							],
							[
								'templateid' => 'api_test_hosts_c_tpl',
								'host' => 'api_test_hosts_c_tpl',
								'link_type' => (string) TEMPLATE_LINK_LLD
							],
							[
								'templateid' => 'api_test_hosts_a_tpl',
								'host' => 'api_test_hosts_a_tpl',
								'link_type' => (string) TEMPLATE_LINK_MANUAL
							],
							[
								'templateid' => 'api_test_hosts_e_tpl',
								'host' => 'api_test_hosts_e_tpl',
								'link_type' => (string) TEMPLATE_LINK_MANUAL
							]
						]
					]
				]
			],
			'Test host.get with limits null' => [
				'request' => [
					'output' => ['hostid'],
					'hostids' => [
						'discovered_limit_selects'
					],
					'selectParentTemplates' => [
						'templateid', 'host', 'link_type'
					],
					'limitSelects' => null
				],
				'expected_results' => [
					[
						'hostid' => 'discovered_limit_selects',
						'parentTemplates' => [
							[
								'templateid' => 'api_test_hosts_f_tpl',
								'host' => 'api_test_hosts_f_tpl',
								'link_type' => (string) TEMPLATE_LINK_LLD
							],
							[
								'templateid' => 'api_test_hosts_c_tpl',
								'host' => 'api_test_hosts_c_tpl',
								'link_type' => (string) TEMPLATE_LINK_LLD
							],
							[
								'templateid' => 'api_test_hosts_a_tpl',
								'host' => 'api_test_hosts_a_tpl',
								'link_type' => (string) TEMPLATE_LINK_LLD
							],
							[
								'templateid' => 'api_test_hosts_e_tpl',
								'host' => 'api_test_hosts_e_tpl',
								'link_type' => (string) TEMPLATE_LINK_LLD
							],
							[
								'templateid' => 'api_test_hosts_b_tpl',
								'host' => 'api_test_hosts_b_tpl',
								'link_type' => (string) TEMPLATE_LINK_LLD
							],
							[
								'templateid' => 'api_test_hosts_d_tpl',
								'host' => 'api_test_hosts_d_tpl',
								'link_type' => (string) TEMPLATE_LINK_LLD
							]
						]
					]
				]
			],
			'Test host.get with limits zero' => [
				'request' => [
					'output' => ['hostid'],
					'hostids' => [
						'discovered_limit_selects'
					],
					'selectParentTemplates' => [
						'templateid', 'host', 'link_type'
					],
					'limitSelects' => '0'
				],
				'expected_results' => [
					[
						'hostid' => 'discovered_limit_selects',
						'parentTemplates' => [
							[
								'templateid' => 'api_test_hosts_a_tpl',
								'host' => 'api_test_hosts_a_tpl',
								'link_type' => (string) TEMPLATE_LINK_LLD
							],
							[
								'templateid' => 'api_test_hosts_b_tpl',
								'host' => 'api_test_hosts_b_tpl',
								'link_type' => (string) TEMPLATE_LINK_LLD
							],
							[
								'templateid' => 'api_test_hosts_c_tpl',
								'host' => 'api_test_hosts_c_tpl',
								'link_type' => (string) TEMPLATE_LINK_LLD
							],
							[
								'templateid' => 'api_test_hosts_d_tpl',
								'host' => 'api_test_hosts_d_tpl',
								'link_type' => (string) TEMPLATE_LINK_LLD
							],
							[
								'templateid' => 'api_test_hosts_e_tpl',
								'host' => 'api_test_hosts_e_tpl',
								'link_type' => (string) TEMPLATE_LINK_LLD
							],
							[
								'templateid' => 'api_test_hosts_f_tpl',
								'host' => 'api_test_hosts_f_tpl',
								'link_type' => (string) TEMPLATE_LINK_LLD
							]
						]
					]
				]
			],
			'Test host.get with limits three' => [
				'request' => [
					'output' => ['hostid'],
					'hostids' => [
						'discovered_limit_selects'
					],
					'selectParentTemplates' => [
						'templateid', 'host', 'link_type'
					],
					'limitSelects' => '3'
				],
				'expected_results' => [
					[
						'hostid' => 'discovered_limit_selects',
						'parentTemplates' => [
							[
								'templateid' => 'api_test_hosts_a_tpl',
								'host' => 'api_test_hosts_a_tpl',
								'link_type' => (string) TEMPLATE_LINK_LLD
							],
							[
								'templateid' => 'api_test_hosts_b_tpl',
								'host' => 'api_test_hosts_b_tpl',
								'link_type' => (string) TEMPLATE_LINK_LLD
							],
							[
								'templateid' => 'api_test_hosts_c_tpl',
								'host' => 'api_test_hosts_c_tpl',
								'link_type' => (string) TEMPLATE_LINK_LLD
							]
						]
					]
				]
			]
		];
	}

	/**
	 * Test host.get to check if hosts have the necessary templates and order.
	 *
	 * @dataProvider getHostGetTemplatesData
	 */
	public function testHost_GetTemplates($request, $expected_results) {
		// Replace ID placeholder with real ID.
		foreach ($request['hostids'] as &$hostid) {
			$hostid = self::$data['hostids'][$hostid];
		}
		unset($hostid);

		foreach ($expected_results as &$result) {
			$result['hostid'] = self::$data['hostids'][$result['hostid']];

			foreach ($result['parentTemplates'] as &$template) {
				$template['templateid'] = self::$data['templateids'][$template['templateid']];
			}
			unset($template);
		}
		unset($result);

		$host = $this->call('host.get', $request);

		$this->assertSame($expected_results, $host['result']);
	}

	/**
	 * Get a list of items keys on host.
	 *
	 * @param string $hostid
	 */
	private function getItemKeysOnHost(string $hostid) {
		$items_new = $this->call('item.get', [
			'output' => ['key_'],
			'hostids' => $hostid
		]);
		$items_new = $items_new['result'];

		$items = [];
		foreach ($items_new as $item) {
			$items[] = $item['key_'];
		}

		return $items;
	}

	/**
	 * Get the original host value.
	 *
	 * @param array $hostids
	 *
	 * @return array
	 */
	private function store(array $hostids) {
		// Get data before update.
		$hosts_old = $this->call('host.get', [
			'output' => ['hostid', 'host'],
			'selectParentTemplates' => ['templateid'],
			'hostids' => $hostids
		]);

		return $hosts_old['result'];
	}

	/**
	 * Revert hosts back to original state before the update to test other cases like massupdate, massremove etc.
	 *
	 * @param array  $hosts                       Array of hosts.
	 * @param string $hosts[]['hostid']           Host ID that will have the data reverted.
	 * @param string $hosts[]['host']             Host technical name in case of error.
	 * @param string $hosts[]['parentTemplates']  Array of host original templates.
	 */
	private function revert(array $hosts, $clear = false) {
		foreach ($hosts as &$host) {
			if ($clear) {
				$host['templates_clear'] = $host['parentTemplates'];
			}
			else {
				$host['templates'] = $host['parentTemplates'];
			}
			unset($host['parentTemplates']);

			$name = $host['host'];
			unset($host['host']);

			$host_upd = $this->call('host.update', $host);

			$this->assertArrayHasKey('hostids', $host_upd['result'], 'host.update failded for host "'.$name.'"');
		}
		unset($host);
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
		CDataHelper::call('template.delete', array_values(self::$data['templateids']));

		// Delete host group.
		CDataHelper::call('hostgroup.delete', [self::$data['hostgroupid']]);
	}
}
