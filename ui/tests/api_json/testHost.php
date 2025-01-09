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
require_once __DIR__.'/../include/helpers/CTestDataHelper.php';

/**
 * @onBefore prepareHostsData
 *
 * @onAfter clearData
 *
 * @backup hosts
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

			'discovered_no_macros' => null,
			'discovered_manual_macros' => null,
			'discovered_auto_macros' => null,
			'discovered_auto_and_manual_macros' => null,

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

		// Keys must match the macro names. For example discovered_macro_text_a => {$DISCOVERED_MACRO_TEXT_A}
		'hostmacroids' => [
			'discovered_macro_text_a' => null,
			'discovered_macro_secret_a' => null,
			'discovered_macro_vault_a' => null,
			'discovered_macro_text_b' => null,
			'discovered_macro_secret_b' => null,
			'discovered_macro_vault_b' => null,
			'manual_macro_text_c' => null,
			'manual_macro_secret_c' => null,
			'manual_macro_vault_c' => null,
			'manual_macro_text_d' => null,
			'manual_macro_secret_d' => null,
			'manual_macro_vault_d' => null
		],

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

		// Create template group.
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

				// First two templates. Third and fourth templates are manually added.
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
			],
			[
				'host' => '{#HOST}_no_macros',
				'ruleid' => $discoveryrules['itemids'][0],
				'groupLinks' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				]
			],
			[
				'host' => '{#HOST}_manual_macros',
				'ruleid' => $discoveryrules['itemids'][0],
				'groupLinks' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				]
			],
			[
				'host' => '{#HOST}_auto_macros',
				'ruleid' => $discoveryrules['itemids'][0],
				'groupLinks' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				],
				'macros' => [
					[
						'macro' => '{$DISCOVERED_MACRO_TEXT_A}',
						'value' => 'discovered_macro_text_value_a',
						'description' => 'discovered_macro_text_description_a',
						'type' => ZBX_MACRO_TYPE_TEXT
					],
					[
						'macro' => '{$DISCOVERED_MACRO_SECRET_A}',
						'value' => 'discovered_macro_secret_value_a',
						'description' => 'discovered_macro_secret_description_a',
						'type' => ZBX_MACRO_TYPE_SECRET
					],
					[
						'macro' => '{$DISCOVERED_MACRO_VAULT_A}',
						'value' => 'path/to:macro',
						'description' => 'discovered_macro_vault_description_a',
						'type' => ZBX_MACRO_TYPE_VAULT
					]
				]
			],

			// First three macros are automatic. The rest are manually added.
			[
				'host' => '{#HOST}_auto_and_manual_macros',
				'ruleid' => $discoveryrules['itemids'][0],
				'groupLinks' => [
					[
						'groupid' => self::$data['hostgroupid']
					]
				],
				'macros' => [
					[
						'macro' => '{$DISCOVERED_MACRO_TEXT_B}',
						'value' => 'discovered_macro_text_value_b',
						'description' => 'discovered_macro_text_description_b',
						'type' => ZBX_MACRO_TYPE_TEXT
					],
					[
						'macro' => '{$DISCOVERED_MACRO_SECRET_B}',
						'value' => 'discovered_macro_secret_value_b',
						'description' => 'discovered_macro_secret_description_b',
						'type' => ZBX_MACRO_TYPE_SECRET
					],
					[
						'macro' => '{$DISCOVERED_MACRO_VAULT_B}',
						'value' => 'path/to:macro',
						'description' => 'discovered_macro_vault_description_b',
						'type' => ZBX_MACRO_TYPE_VAULT
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
				'macros' => array_key_exists('macros', $host_prototype) ? $host_prototype['macros'] : [],
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
		self::$data['hostids']['discovered_no_macros'] = $hosts['hostids'][6];
		self::$data['hostids']['discovered_manual_macros'] = $hosts['hostids'][7];
		self::$data['hostids']['discovered_auto_macros'] = $hosts['hostids'][8];
		self::$data['hostids']['discovered_auto_and_manual_macros'] = $hosts['hostids'][9];

		$host_discovery = [];
		$upd_hosts = [];
		$upd_hosts_templates = [];
		$upd_hostmacro = [];

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

			if (array_key_exists('macros', $host_prototype) && $host_prototype['macros']) {
				foreach ($host_prototype['macros'] as $macro) {
					$upd_hostmacro[] = [
						'values' => ['automatic' => ZBX_USERMACRO_AUTOMATIC],
						'where' => [
							'hostid' => $hosts['hostids'][$idx],
							'macro' => $macro['macro']
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

		$res = DB::update('hostmacro', $upd_hostmacro);
		$this->assertSame(true, $res);

		// Add hostmacroid references using the macro name as key: {$MACRO_NAME} => macro_name.
		foreach ($upd_hostmacro as $hostmacro) {
			$db_hostmacro = CDBHelper::getRow(
				'SELECT hm.hostmacroid'.
				' FROM hostmacro hm'.
				' WHERE hm.hostid='.zbx_dbstr($hostmacro['where']['hostid']).
					' AND hm.macro='.zbx_dbstr($hostmacro['where']['macro'])
			);
			$key = str_replace('{$', '', $hostmacro['where']['macro']);
			$key = str_replace('}', '', $key);
			$key = strtolower($key);

			self::$data['hostmacroids'][$key] = $db_hostmacro['hostmacroid'];
		}

		/*
		 * Add few manual templates to discovered hosts to test the template removal. Do not use API at this point.
		 * Cannot use CDBHelper::getValue, because it will add "LIMIT 1" at the end of query and MySQL does not support
		 * a syntax like that.
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

		// Add few manual macros to test host.update method.
		$nextid = CDBHelper::getAll(
			'SELECT i.nextid'.
			' FROM ids i'.
			' WHERE i.table_name='.zbx_dbstr('hostmacro').
				' AND i.field_name='.zbx_dbstr('hostmacroid').
			' FOR UPDATE'
		)[0]['nextid'] + 1;
		$hostmacro_data = [
			[
				'hostid' => self::$data['hostids']['discovered_manual_macros'],
				'macro' => '{$MANUAL_MACRO_TEXT_C}',
				'value' => 'manual_macro_text_value_c',
				'description' => 'manual_macro_text_description_c',
				'type' => ZBX_MACRO_TYPE_TEXT,
				'automatic' => ZBX_USERMACRO_MANUAL
			],
			[
				'hostid' => self::$data['hostids']['discovered_manual_macros'],
				'macro' => '{$MANUAL_MACRO_SECRET_C}',
				'value' => 'manual_macro_secret_value_c',
				'description' => 'manual_macro_secret_description_c',
				'type' => ZBX_MACRO_TYPE_SECRET,
				'automatic' => ZBX_USERMACRO_MANUAL
			],
			[
				'hostid' => self::$data['hostids']['discovered_manual_macros'],
				'macro' => '{$MANUAL_MACRO_VAULT_C}',
				'value' => 'path/to:macro',
				'description' => 'manual_macro_vault_description_c',
				'type' => ZBX_MACRO_TYPE_VAULT,
				'automatic' => ZBX_USERMACRO_MANUAL
			],
			[
				'hostid' => self::$data['hostids']['discovered_auto_and_manual_macros'],
				'macro' => '{$MANUAL_MACRO_TEXT_D}',
				'value' => 'manual_macro_text_value_d',
				'description' => 'manual_macro_text_description_d',
				'type' => ZBX_MACRO_TYPE_TEXT,
				'automatic' => ZBX_USERMACRO_MANUAL
			],
			[
				'hostid' => self::$data['hostids']['discovered_auto_and_manual_macros'],
				'macro' => '{$MANUAL_MACRO_SECRET_D}',
				'value' => 'manual_macro_secreat_value_d',
				'description' => 'manual_macro_secret_description_d',
				'type' => ZBX_MACRO_TYPE_SECRET,
				'automatic' => ZBX_USERMACRO_MANUAL
			],
			[
				'hostid' => self::$data['hostids']['discovered_auto_and_manual_macros'],
				'macro' => '{$MANUAL_MACRO_VAULT_D}',
				'value' => 'path/to:macro',
				'description' => 'manual_macro_vault_description_d',
				'type' => ZBX_MACRO_TYPE_VAULT,
				'automatic' => ZBX_USERMACRO_MANUAL
			]
		];

		$ids = DB::insertBatch('hostmacro', $hostmacro_data);
		$newids = array_fill($nextid, count($hostmacro_data), true);
		$this->assertEquals(array_keys($newids), $ids);

		self::$data['hostmacroids']['manual_macro_text_c'] = $ids[0];
		self::$data['hostmacroids']['manual_macro_secret_c'] = $ids[1];
		self::$data['hostmacroids']['manual_macro_vault_c'] = $ids[2];
		self::$data['hostmacroids']['manual_macro_text_d'] = $ids[3];
		self::$data['hostmacroids']['manual_macro_secret_d'] = $ids[4];
		self::$data['hostmacroids']['manual_macro_vault_d'] = $ids[5];

		self::prepareTestDataHostPskFieldsCreate();
		self::prepareTestDataHostPskFieldsUpdate();
		self::prepareTestDataHostPskFieldsMassUpdate();
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
				'expected_error' => 'Invalid parameter "/": cannot be empty.'
			],
			'Test host.create common error - wrong fields' => [
				'request' => [
					'groups' => []
				],
				'expected_error' => 'Invalid parameter "/1/groups": cannot be empty.'
			],
			'Test host.create common error - missing "groups"' => [
				'request' => [
					'host' => 'API test hosts create fail'
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "groups" is missing.'
			],
			'Test host.create common error - empty group' => [
				'request' => [
					'host' => 'API test hosts create fail',
					'groups' => []
				],
				'expected_error' => 'Invalid parameter "/1/groups": cannot be empty.'
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
				'expected_error' => 'Invalid parameter "/1/groups/1": the parameter "groupid" is missing.'
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
				'expected_error' => 'Invalid parameter "/1/groups/1": object does not exist, or you have no permissions to it.'
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
			],

			// Test create macros.
			'Test host.create - macros (automatic)' => [
				'request' => [
					'host' => 'API test hosts create fail',
					'groups' => [
						[
							'groupid' => 'ID'
						]
					],
					'macros' => [
						[
							'macro' => '{$MANUAL_MACRO_TEXT_NEW_FAIL_ONE}',
							'value' => 'manual_macro_text_value_new_fail_one',
							'description' => 'manual_macro_text_description_new_fail_one',
							'type' => ZBX_MACRO_TYPE_TEXT,
							'automatic' => ZBX_USERMACRO_AUTOMATIC
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/macros/1": unexpected parameter "automatic".'
			],
			'Test host.create - macros (manual)' => [
				'request' => [
					'host' => 'API test hosts create fail',
					'groups' => [
						[
							'groupid' => 'ID'
						]
					],
					'macros' => [
						[
							'macro' => '{$MANUAL_MACRO_TEXT_NEW_FAIL_TWO}',
							'value' => 'manual_macro_text_value_new_fail_two',
							'description' => 'manual_macro_text_description_new_fail_two',
							'type' => ZBX_MACRO_TYPE_TEXT,
							'automatic' => ZBX_USERMACRO_MANUAL
						]
					]
				],
				'expected_error' => 'Invalid parameter "/1/macros/1": unexpected parameter "automatic".'
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
							'value' => 'b',
							'automatic' => '0'
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
					'tls_psk' => null,
					'name_upper' => null
				]
			],
			'Check it is not possible to select write-only fields' => [
				'request' => [
					'output' => ['host', 'tls_psk', 'tls_psk_identity', 'name_upper'],
					'hostids' => 'write_only'
				],
				'expected_result' => [
					'hostid' => 'write_only',

					// Sample of unspecified property.
					'inventory_mode' => null,

					// Write-only properties.
					'tls_psk_identity' => null,
					'tls_psk' => null,
					'name_upper' => null
				]
			],
			'Check direct request of inventory_mode and other properties' => [
				'request' => [
					'output' => ['inventory_mode', 'tls_connect', 'name', 'name_upper'],
					'hostids' => 'write_only'
				],
				'expected_result' => [
					'hostid' => 'write_only',
					'inventory_mode' => '-1',

					// Samples of other specified properties.
					'tls_connect' => '1',
					'name' => 'API test hosts - write-only fields',
					'name_upper' => null
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
				'expected_result' => 'Invalid parameter "/1/templates_clear/1/templateid": cannot be specified the value of parameter "/1/templates/1/templateid".'
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
				'expected_result' => 'Invalid parameter "/1/templates_clear/1/templateid": cannot be specified the value of parameter "/1/templates/1/templateid".'
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
							'templateid' => 'api_test_hosts_f_tpl'
						]
					],
					'templates_clear' => [
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

		if (is_array($expected_result)) {
			foreach ($expected_result['parentTemplates'] as &$template) {
				$template['templateid'] = self::$data['templateids'][$template['templateid']];
			}
			unset($template);
		}

		$hosts_old = $this->backupTemplates([$request['hostid']]);

		// Update templates on hosts.
		$expected_error = is_string($expected_result) ? $expected_result : null;
		$hosts_upd = $this->call('host.update', $request, $expected_error);

		if (is_array($expected_result)) {
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
		}

		$this->restoreTemplates($hosts_old);
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
			 * cleared. Currently templates that are added take priority. They do not cancel each other out.
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
				'expected_result' => 'Invalid parameter "/templates_clear/1/templateid": cannot be specified the value of parameter "/templates/1/templateid".'
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
				'expected_result' => 'Invalid parameter "/templates_clear/1/templateid": cannot be specified the value of parameter "/templates/1/templateid".'
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
							'templateid' => 'api_test_hosts_f_tpl'
						]
					],
					'templates_clear' => [
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

		if (is_array($expected_result)) {
			foreach ($expected_result['parentTemplates'] as &$template) {
				$template['templateid'] = self::$data['templateids'][$template['templateid']];
			}
			unset($template);
		}

		$hosts_old = $this->backupTemplates($hostids);

		// Update templates on hosts.
		$expected_error = is_string($expected_result) ? $expected_result : null;
		$hosts_upd = $this->call('host.massupdate', $request, $expected_error);

		if (is_array($expected_result)) {
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
		}

		$this->restoreTemplates($hosts_old);
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

			// Add templates to existing manual templates.
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

			// Add same templates to existing auto templates. Not possible, since those templates are already auto.
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

			// Add different templates to existing auto templates.
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

			// Add templates to existing auto and manual templates.
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
	 * Test host.massadd by adding new templates to existing manual and auto templates.
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

		$hosts_old = $this->backupTemplates($hostids);

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

		$this->restoreTemplates($hosts_old);
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

		$hosts_old = $this->backupTemplates($request['hostids']);

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

		$this->restoreTemplates($hosts_old);
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

		$hosts_old = $this->backupTemplates([$host['hostid']]);

		// Add/replace templates on host and check if items are inherited on host.
		$this->call('host.update', $host);
		$item_keys = $this->getItemKeysOnHost($host['hostid']);
		$this->assertSame($expected_results['update']['item_keys'], $item_keys,
			'Updating templates failed: mismatching results on host with ID "'.$host['hostid'].'".'
		);

		// Then unlink the template from host and check if item still exists on host.
		$this->restoreTemplates($hosts_old);
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
		$this->restoreTemplates($hosts['result'], true);
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
	 * Data provider for host.get to check macros.
	 *
	 * @return array
	 */
	public static function getHostGetMacrosData() {
		return [
			'Test host.get - host has no macros' => [
				'request' => [
					'output' => ['hostid'],
					'hostids' => [
						'discovered_no_macros'
					],
					'selectMacros' => [
						'macro', 'value', 'description', 'type', 'automatic'
					]
				],
				'expected_results' => [
					[
						'hostid' => 'discovered_no_macros',
						'macros' => []
					]
				]
			],
			'Test host.get - host has manual macros' => [
				'request' => [
					'output' => ['hostid'],
					'hostids' => [
						'discovered_manual_macros'
					],
					'selectMacros' => [
						'macro', 'value', 'description', 'type', 'automatic'
					]
				],
				'expected_results' => [
					[
						'hostid' => 'discovered_manual_macros',
						'macros' => [
							[
								'macro' => '{$MANUAL_MACRO_TEXT_C}',
								'value' => 'manual_macro_text_value_c',
								'description' => 'manual_macro_text_description_c',
								'type' => (string) ZBX_MACRO_TYPE_TEXT,
								'automatic' => (string) ZBX_USERMACRO_MANUAL
							],
							[
								'macro' => '{$MANUAL_MACRO_SECRET_C}',
								'description' => 'manual_macro_secret_description_c',
								'type' => (string) ZBX_MACRO_TYPE_SECRET,
								'automatic' => (string) ZBX_USERMACRO_MANUAL
							],
							[
								'macro' => '{$MANUAL_MACRO_VAULT_C}',
								'value' => 'path/to:macro',
								'description' => 'manual_macro_vault_description_c',
								'type' => (string) ZBX_MACRO_TYPE_VAULT,
								'automatic' => (string) ZBX_USERMACRO_MANUAL
							]
						]
					]
				]
			],
			'Test host.get - host has auto macros' => [
				'request' => [
					'output' => ['hostid'],
					'hostids' => [
						'discovered_auto_macros'
					],
					'selectMacros' => [
						'macro', 'value', 'description', 'type', 'automatic'
					]
				],
				'expected_results' => [
					[
						'hostid' => 'discovered_auto_macros',
						'macros' => [
							[
								'macro' => '{$DISCOVERED_MACRO_TEXT_A}',
								'value' => 'discovered_macro_text_value_a',
								'description' => 'discovered_macro_text_description_a',
								'type' => (string) ZBX_MACRO_TYPE_TEXT,
								'automatic' => (string) ZBX_USERMACRO_AUTOMATIC
							],
							[
								'macro' => '{$DISCOVERED_MACRO_SECRET_A}',
								'description' => 'discovered_macro_secret_description_a',
								'type' => (string) ZBX_MACRO_TYPE_SECRET,
								'automatic' => (string) ZBX_USERMACRO_AUTOMATIC
							],
							[
								'macro' => '{$DISCOVERED_MACRO_VAULT_A}',
								'value' => 'path/to:macro',
								'description' => 'discovered_macro_vault_description_a',
								'type' => (string) ZBX_MACRO_TYPE_VAULT,
								'automatic' => (string) ZBX_USERMACRO_AUTOMATIC
							]
						]
					]
				]
			],
			'Test host.get - host has auto and manual macros' => [
				'request' => [
					'output' => ['hostid'],
					'hostids' => [
						'discovered_auto_and_manual_macros'
					],
					'selectMacros' => [
						'macro', 'value', 'description', 'type', 'automatic'
					]
				],
				'expected_results' => [
					[
						'hostid' => 'discovered_auto_and_manual_macros',
						'macros' => [
							[
								'macro' => '{$DISCOVERED_MACRO_TEXT_B}',
								'value' => 'discovered_macro_text_value_b',
								'description' => 'discovered_macro_text_description_b',
								'type' => (string) ZBX_MACRO_TYPE_TEXT,
								'automatic' => (string) ZBX_USERMACRO_AUTOMATIC
							],
							[
								'macro' => '{$DISCOVERED_MACRO_SECRET_B}',
								'description' => 'discovered_macro_secret_description_b',
								'type' => (string) ZBX_MACRO_TYPE_SECRET,
								'automatic' => (string) ZBX_USERMACRO_AUTOMATIC
							],
							[
								'macro' => '{$DISCOVERED_MACRO_VAULT_B}',
								'value' => 'path/to:macro',
								'description' => 'discovered_macro_vault_description_b',
								'type' => (string) ZBX_MACRO_TYPE_VAULT,
								'automatic' => (string) ZBX_USERMACRO_AUTOMATIC
							],
							[
								'macro' => '{$MANUAL_MACRO_TEXT_D}',
								'value' => 'manual_macro_text_value_d',
								'description' => 'manual_macro_text_description_d',
								'type' => (string) ZBX_MACRO_TYPE_TEXT,
								'automatic' => (string) ZBX_USERMACRO_MANUAL
							],
							[
								'macro' => '{$MANUAL_MACRO_SECRET_D}',
								'description' => 'manual_macro_secret_description_d',
								'type' => (string) ZBX_MACRO_TYPE_SECRET,
								'automatic' => (string) ZBX_USERMACRO_MANUAL
							],
							[
								'macro' => '{$MANUAL_MACRO_VAULT_D}',
								'value' => 'path/to:macro',
								'description' => 'manual_macro_vault_description_d',
								'type' => (string) ZBX_MACRO_TYPE_VAULT,
								'automatic' => (string) ZBX_USERMACRO_MANUAL
							]
						]
					]
				]
			]
		];
	}

	/**
	 * Test host.get to check if hosts have various macros and their properties.
	 *
	 * @dataProvider getHostGetMacrosData
	 */
	public function testHost_GetMacros($request, $expected_results) {
		// Replace ID placeholder with real ID.
		foreach ($request['hostids'] as &$hostid) {
			$hostid = self::$data['hostids'][$hostid];
		}
		unset($hostid);

		foreach ($expected_results as &$result) {
			$result['hostid'] = self::$data['hostids'][$result['hostid']];
		}
		unset($result);

		$host = $this->call('host.get', $request);

		// Ignore the order due to MySQL returning macros in different order.
		$this->assertEqualsCanonicalizing($expected_results, $host['result']);
	}

	/**
	 * Data provider for host.update to check how macros are replaced and converted from automatic to manual.
	 *
	 * @return array
	 */
	public static function getHostUpdateMacrosDataValid() {
		return [
			// Add manual macros to discovered host.
			'Test host.update - host has no macros' => [
				'request' => [
					'hostid' => 'discovered_no_macros',
					'macros' => [
						[
							'macro' => '{$MANUAL_MACRO_TEXT_NEW}',
							'value' => 'manual_macro_text_value_new',
							'description' => 'manual_macro_text_description_new',
							'type' => (string) ZBX_MACRO_TYPE_TEXT
						],
						[
							'macro' => '{$MANUAL_MACRO_SECRET_NEW}',
							'value' => 'manual_macro_secret_value_new',
							'description' => 'manual_macro_secret_description_new',
							'type' => (string) ZBX_MACRO_TYPE_SECRET
						],
						[
							'macro' => '{$MANUAL_MACRO_VAULT_NEW}',
							'value' => 'path/to:macro',
							'description' => 'manual_macro_vault_description_new',
							'type' => (string) ZBX_MACRO_TYPE_VAULT
						]
					]
				],
				'expected_result' => [
					'macros' => [
						[
							'macro' => '{$MANUAL_MACRO_TEXT_NEW}',
							'value' => 'manual_macro_text_value_new',
							'description' => 'manual_macro_text_description_new',
							'type' => (string) ZBX_MACRO_TYPE_TEXT,
							'automatic' => (string) ZBX_USERMACRO_MANUAL
						],
						[
							'macro' => '{$MANUAL_MACRO_SECRET_NEW}',
							'value' => 'manual_macro_secret_value_new',
							'description' => 'manual_macro_secret_description_new',
							'type' => (string) ZBX_MACRO_TYPE_SECRET,
							'automatic' => (string) ZBX_USERMACRO_MANUAL
						],
						[
							'macro' => '{$MANUAL_MACRO_VAULT_NEW}',
							'value' => 'path/to:macro',
							'description' => 'manual_macro_vault_description_new',
							'type' => (string) ZBX_MACRO_TYPE_VAULT,
							'automatic' => (string) ZBX_USERMACRO_MANUAL
						]
					]
				],
				'expected_error' => null
			],

			// Replace macros leaving some old, changing and adding new.
			'Test host.update - host has manual macros' => [
				'request' => [
					'hostid' => 'discovered_manual_macros',
					'macros' => [
						// Leave macro as is.
						[
							'hostmacroid' => 'manual_macro_text_c'
						],
						// Change macro name {$MANUAL_MACRO_SECRET_C}.
						[
							'hostmacroid' => 'manual_macro_secret_c',
							'macro' => '{$MANUAL_MACRO_SECRET_C_CHANGED_NAME}'
						],
						// Replace macro {$MANUAL_MACRO_VAULT_C} with this one.
						[
							'macro' => '{$MANUAL_MACRO_VAULT_C_NEW}',
							'value' => 'path/to:macro',
							'description' => 'manual_macro_vault_description_c_new',
							'type' => (string) ZBX_MACRO_TYPE_VAULT
						]
					]
				],
				'expected_result' => [
					'macros' => [
						[
							'macro' => '{$MANUAL_MACRO_TEXT_C}',
							'value' => 'manual_macro_text_value_c',
							'description' => 'manual_macro_text_description_c',
							'type' => (string) ZBX_MACRO_TYPE_TEXT,
							'automatic' => (string) ZBX_USERMACRO_MANUAL
						],
						[
							'macro' => '{$MANUAL_MACRO_SECRET_C_CHANGED_NAME}',
							'value' => 'manual_macro_secret_value_c',
							'description' => 'manual_macro_secret_description_c',
							'type' => (string) ZBX_MACRO_TYPE_SECRET,
							'automatic' => (string) ZBX_USERMACRO_MANUAL
						],
						[
							'macro' => '{$MANUAL_MACRO_VAULT_C_NEW}',
							'value' => 'path/to:macro',
							'description' => 'manual_macro_vault_description_c_new',
							'type' => (string) ZBX_MACRO_TYPE_VAULT,
							'automatic' => (string) ZBX_USERMACRO_MANUAL
						]
					]
				],
				'expected_error' => null
			],

			// Don't change host macros, leave them automatic.
			'Test host.update - host has auto macros (same)' => [
				'request' => [
					'hostid' => 'discovered_auto_macros',
					'macros' => [
						[
							'hostmacroid' => 'discovered_macro_text_a'
						],
						[
							'hostmacroid' => 'discovered_macro_secret_a'
						],
						[
							'hostmacroid' => 'discovered_macro_vault_a'
						]
					]
				],
				'expected_result' => [
					'macros' => [
						[
							'macro' => '{$DISCOVERED_MACRO_TEXT_A}',
							'value' => 'discovered_macro_text_value_a',
							'description' => 'discovered_macro_text_description_a',
							'type' => (string) ZBX_MACRO_TYPE_TEXT,
							'automatic' => (string) ZBX_USERMACRO_AUTOMATIC
						],
						[
							'macro' => '{$DISCOVERED_MACRO_SECRET_A}',
							'value' => 'discovered_macro_secret_value_a',
							'description' => 'discovered_macro_secret_description_a',
							'type' => (string) ZBX_MACRO_TYPE_SECRET,
							'automatic' => (string) ZBX_USERMACRO_AUTOMATIC
						],
						[
							'macro' => '{$DISCOVERED_MACRO_VAULT_A}',
							'value' => 'path/to:macro',
							'description' => 'discovered_macro_vault_description_a',
							'type' => (string) ZBX_MACRO_TYPE_VAULT,
							'automatic' => (string) ZBX_USERMACRO_AUTOMATIC
						]
					]
				],
				'expected_error' => null
			],

			// Change host macros from automatic to manual.
			'Test host.update - host has auto macros (convert)' => [
				'request' => [
					'hostid' => 'discovered_auto_macros',
					'macros' => [
						// Just convert the macro to manual.
						[
							'hostmacroid' => 'discovered_macro_text_a',
							'automatic' => (string) ZBX_USERMACRO_MANUAL
						],
						// Convert macro to manual and change value and description.
						[
							'hostmacroid' => 'discovered_macro_secret_a',
							'value' => 'discovered_macro_secret_value_a_changed',
							'description' => 'discovered_macro_secret_description_a_changed',
							'automatic' => (string) ZBX_USERMACRO_MANUAL
						],
						// Convert macro to manual and change macro name.
						[
							'hostmacroid' => 'discovered_macro_vault_a',
							'macro' => '{$DISCOVERED_MACRO_VAULT_A_CHANGED}',
							'automatic' => (string) ZBX_USERMACRO_MANUAL
						]
					]
				],
				'expected_result' => [
					'macros' => [
						[
							'macro' => '{$DISCOVERED_MACRO_TEXT_A}',
							'value' => 'discovered_macro_text_value_a',
							'description' => 'discovered_macro_text_description_a',
							'type' => (string) ZBX_MACRO_TYPE_TEXT,
							'automatic' => (string) ZBX_USERMACRO_MANUAL
						],
						[
							'macro' => '{$DISCOVERED_MACRO_SECRET_A}',
							'value' => 'discovered_macro_secret_value_a_changed',
							'description' => 'discovered_macro_secret_description_a_changed',
							'type' => (string) ZBX_MACRO_TYPE_SECRET,
							'automatic' => (string) ZBX_USERMACRO_MANUAL
						],
						[
							'macro' => '{$DISCOVERED_MACRO_VAULT_A_CHANGED}',
							'value' => 'path/to:macro',
							'description' => 'discovered_macro_vault_description_a',
							'type' => (string) ZBX_MACRO_TYPE_VAULT,
							'automatic' => (string) ZBX_USERMACRO_MANUAL
						]
					]
				],
				'expected_error' => null
			],

			// Convert automatic macros to manual, leave some original, add new.
			'Test host.update - host has auto and manual macros' => [
				'request' => [
					'hostid' => 'discovered_auto_and_manual_macros',
					'macros' => [
						[
							// Converts automatic macro to manual with no changes.
							'hostmacroid' => 'discovered_macro_text_b'
						],
						[
							// Converts automatic macro to manual with no changes (value is the same).
							'hostmacroid' => 'discovered_macro_secret_b',
							'value' => 'discovered_macro_secret_value_b',
							'automatic' => (string) ZBX_USERMACRO_MANUAL
						],
						[
							// Converts automatic macro to manual with new name and description.
							'hostmacroid' => 'discovered_macro_vault_b',
							'macro' => '{$DISCOVERED_MACRO_VAULT_B_CHANGED}',
							'description' => 'discovered_macro_vault_description_b_changed',
							'automatic' => (string) ZBX_USERMACRO_MANUAL
						],
						[
							// No conversion will happen, because it's already manual macro.
							'hostmacroid' => 'manual_macro_text_d',
							'automatic' => (string) ZBX_USERMACRO_MANUAL
						],
						[
							// No conversion will happen, because it's already manual macro.
							'hostmacroid' => 'manual_macro_secret_d',
							'value' => 'manual_macro_secret_value_d',
							'automatic' => (string) ZBX_USERMACRO_MANUAL
						],
						[
							// Change macro name and description. No conversion will happen. Already manual macro.
							'hostmacroid' => 'manual_macro_vault_d',
							'macro' => '{$MANUAL_MACRO_VAULT_D_CHANGED}',
							'description' => 'manual_macro_vault_description_d_changed',
							'automatic' => (string) ZBX_USERMACRO_MANUAL
						],
						[
							// Add new macro. No conversion possible. Already manual macro.
							'macro' => '{$MANUAL_MACRO_TEXT_E_NEW}',
							'value' => 'manual_macro_text_value_e_new',
							'description' => 'manual_macro_text_description_e_new',
							'type' => (string) ZBX_MACRO_TYPE_TEXT
						],[
							// Add new macro and try to add the property. No conversion possible. Already manual macro.
							'macro' => '{$MANUAL_MACRO_TEXT_F_NEW}',
							'value' => 'manual_macro_text_value_f_new',
							'description' => 'manual_macro_text_description_f_new',
							'type' => (string) ZBX_MACRO_TYPE_TEXT,
							'automatic' => (string) ZBX_USERMACRO_MANUAL
						]
					]
				],
				'expected_result' => [
					'macros' => [
						[
							'macro' => '{$DISCOVERED_MACRO_TEXT_B}',
							'value' => 'discovered_macro_text_value_b',
							'description' => 'discovered_macro_text_description_b',
							'type' => (string) ZBX_MACRO_TYPE_TEXT,
							'automatic' => (string) ZBX_USERMACRO_AUTOMATIC
						],
						[
							'macro' => '{$DISCOVERED_MACRO_SECRET_B}',
							'value' => 'discovered_macro_secret_value_b',
							'description' => 'discovered_macro_secret_description_b',
							'type' => (string) ZBX_MACRO_TYPE_SECRET,
							'automatic' => (string) ZBX_USERMACRO_MANUAL
						],
						[
							'macro' => '{$DISCOVERED_MACRO_VAULT_B_CHANGED}',
							'value' => 'path/to:macro',
							'description' => 'discovered_macro_vault_description_b_changed',
							'type' => (string) ZBX_MACRO_TYPE_VAULT,
							'automatic' => (string) ZBX_USERMACRO_MANUAL
						],
						[
							'macro' => '{$MANUAL_MACRO_TEXT_D}',
							'value' => 'manual_macro_text_value_d',
							'description' => 'manual_macro_text_description_d',
							'type' => (string) ZBX_MACRO_TYPE_TEXT,
							'automatic' => (string) ZBX_USERMACRO_MANUAL
						],
						[
							'macro' => '{$MANUAL_MACRO_SECRET_D}',
							'value' => 'manual_macro_secret_value_d',
							'description' => 'manual_macro_secret_description_d',
							'type' => (string) ZBX_MACRO_TYPE_SECRET,
							'automatic' => (string) ZBX_USERMACRO_MANUAL
						],
						[
							'macro' => '{$MANUAL_MACRO_VAULT_D_CHANGED}',
							'value' => 'path/to:macro',
							'description' => 'manual_macro_vault_description_d_changed',
							'type' => (string) ZBX_MACRO_TYPE_VAULT,
							'automatic' => (string) ZBX_USERMACRO_MANUAL
						],
						[
							'macro' => '{$MANUAL_MACRO_TEXT_E_NEW}',
							'value' => 'manual_macro_text_value_e_new',
							'description' => 'manual_macro_text_description_e_new',
							'type' => (string) ZBX_MACRO_TYPE_TEXT,
							'automatic' => (string) ZBX_USERMACRO_MANUAL
						],
						[
							'macro' => '{$MANUAL_MACRO_TEXT_F_NEW}',
							'value' => 'manual_macro_text_value_f_new',
							'description' => 'manual_macro_text_description_f_new',
							'type' => (string) ZBX_MACRO_TYPE_TEXT,
							'automatic' => (string) ZBX_USERMACRO_MANUAL
						]
					]
				],
				'expected_error' => null
			],
			'Test host.update - remove automatic and manual macros' => [
				'request' => [
					'hostid' => 'discovered_auto_and_manual_macros',
					'macros' => []
				],
				'expected_result' => [
					'macros' => []
				],
				'expected_error' => null
			]
		];
	}

	public static function getHostUpdateMacrosDataInvalid() {
		return [
			'Test host.update - automatic new macro' => [
				'request' => [
					'hostid' => 'discovered_no_macros',
					'macros' => [
						[
							'macro' => '{$MANUAL_MACRO_TEXT_NEW}',
							'value' => 'manual_macro_text_value_new',
							'description' => 'manual_macro_text_description_new',
							'type' => (string) ZBX_MACRO_TYPE_TEXT,
							'automatic' => (string) ZBX_USERMACRO_AUTOMATIC
						]
					]
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/1/macros/1/automatic": value must be '.ZBX_USERMACRO_MANUAL.'.'
			],
			'Test host.update - change existing automatic macro (missing param)' => [
				'request' => [
					'hostid' => 'discovered_auto_macros',
					'macros' => [
						[
							'hostmacroid' => 'discovered_macro_text_a',
							'macro' => '{$DISCOVERED_MACRO_TEXT_A_NEW}'
							// Parameter "automatic" is mandatory if something needs to be changed on automatic macro.
						]
					]
				],
				'expected_result' => [],
				'expected_error' => 'Not allowed to modify automatic user macro "{$DISCOVERED_MACRO_TEXT_A}".'
			],
			'Test host.update - change existing automatic macro (incorrect value)' => [
				'request' => [
					'hostid' => 'discovered_auto_macros',
					'macros' => [
						[
							'hostmacroid' => 'discovered_macro_text_a',
							'macro' => '{$DISCOVERED_MACRO_TEXT_A_NEW}',
							'automatic' => ZBX_USERMACRO_AUTOMATIC
						]
					]
				],
				'expected_result' => [],
				'expected_error' => 'Invalid parameter "/1/macros/1/automatic": value must be '.ZBX_USERMACRO_MANUAL.'.'
			]
		];
	}

	/**
	 * Test host macro update. Check if 'automatic' property is changed for discovered host macros, macros are properly
	 * updated to new macros etc.
	 *
	 * @dataProvider getHostUpdateMacrosDataValid
	 * @dataProvider getHostUpdateMacrosDataInvalid
	 */
	public function testHost_UpdateMacros($request, $expected_result, $expected_error) {
		// Replace ID placeholders with real IDs.
		$request['hostid'] = self::$data['hostids'][$request['hostid']];

		foreach ($request['macros'] as &$macro) {
			if (array_key_exists('hostmacroid', $macro)) {
				$macro['hostmacroid'] = self::$data['hostmacroids'][$macro['hostmacroid']];
			}
		}
		unset($macro);

		$hosts_old = $this->backupMacros([$request['hostid']]);
		$hosts_upd = $this->call('host.update', $request, $expected_error);

		if ($expected_error === null) {
			$this->assertArrayHasKey('hostids', $hosts_upd['result']);

			$db_hosts = $this->getMacros([$request['hostid']]);
			$db_host = reset($db_hosts);

			foreach ($db_host['macros'] as &$macro) {
				unset($macro['hostid'], $macro['hostmacroid']);
			}
			unset($macro);

			// Ignore the order in which $db_host macros are returned when comparing.
			$this->assertEqualsCanonicalizing($expected_result['macros'], $db_host['macros'],
				'host.update with macros failed for host "'.$db_host['host'].'".'
			);

			$this->restoreMacros($hosts_old);
		}
	}

	public static function prepareTestDataHostPskFieldsCreate() {
		CTestDataHelper::createObjects([
			'host_groups' => [
				['name' => 'API tests hosts group']
			],
			'hosts' => [
				[
					'host' => 'test.example.com',
					'groups' => [['groupid' => ':host_group:API tests hosts group']],
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => 'public',
					'tls_psk' => '79cbf232a3ad3bfe38dee29861f8ba6b'
				]
			]
		]);

		CDataHelper::call('autoregistration.update', [
			'tls_accept' => HOST_ENCRYPTION_PSK,
			'tls_psk_identity' => 'autoregistration',
			'tls_psk' => 'ec30a947e6776ae9efb77f46aefcba04'
		]);
	}

	public static function dataProviderInvalidHostPskFieldsCreate() {
		$groups = [['groupid' => ':host_group:API tests hosts group']];

		return [
			'Field "tls_psk_identity" is required when "tls_connect" is HOST_ENCRYPTION_PSK' => [
				'host' => [
					[
						'host' => 'example.com',
						'groups' => $groups,
						'tls_connect' => HOST_ENCRYPTION_PSK,
						'tls_psk' => '5fce1b3e34b520afeffb37ce08c7cd66'
					]
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "tls_psk_identity" is missing.'
			],
			'Field "tls_psk_identity" cannot be empty when "tls_connect" is HOST_ENCRYPTION_PSK' => [
				'host' => [
					[
						'host' => 'example.com',
						'groups' => $groups,
						'tls_connect' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": cannot be empty.'
			],
			'Field "tls_psk_identity" cannot be set when "tls_connect" is not set to HOST_ENCRYPTION_PSK' => [
				'host' => [
					[
						'host' => 'example.com',
						'groups' => $groups,
						'tls_psk_identity' => 'identity'
					]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value must be empty.'
			],
			'Field "tls_psk_identity" is required when "tls_accept" HOST_ENCRYPTION_PSK flag is set' => [
				'host' => [
					[
						'host' => 'example.com',
						'groups' => $groups,
						'tls_accept' => HOST_ENCRYPTION_PSK,
						'tls_psk' => '5fce1b3e34b520afeffb37ce08c7cd66'
					]
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "tls_psk_identity" is missing.'
			],
			'Field "tls_psk_identity" cannot be empty when "tls_accept" HOST_ENCRYPTION_PSK flag is set' => [
				'host' => [
					[
						'host' => 'example.com',
						'groups' => $groups,
						'tls_accept' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": cannot be empty.'
			],
			'Field "tls_psk" is required when "tls_connect" is HOST_ENCRYPTION_PSK' => [
				'host' => [
					[
						'host' => 'example.com',
						'groups' => $groups,
						'tls_connect' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'example'
					]
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "tls_psk" is missing.'
			],
			'Field "tls_psk" cannot be empty when "tls_connect" is HOST_ENCRYPTION_PSK' => [
				'host' => [
					[
						'host' => 'example.com',
						'groups' => $groups,
						'tls_connect' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'public',
						'tls_psk' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": cannot be empty.'
			],
			'Field "tls_psk" cannot be set when "tls_connect" is not set to HOST_ENCRYPTION_PSK' => [
				'host' => [
					[
						'host' => 'example.com',
						'groups' => $groups,
						'tls_psk' => '5fce1b3e34b520afeffb37ce08c7cd66'
					]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": value must be empty.'
			],
			'Field "tls_psk" is required when "tls_accept" HOST_ENCRYPTION_PSK flag is set' => [
				'host' => [
					[
						'host' => 'example.com',
						'groups' => $groups,
						'tls_accept' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'example'
					]
				],
				'expected_error' => 'Invalid parameter "/1": the parameter "tls_psk" is missing.'
			],
			'Field "tls_psk" cannot be empty when "tls_accept" HOST_ENCRYPTION_PSK flag is set' => [
				'host' => [
					[
						'host' => 'example.com',
						'groups' => $groups,
						'tls_accept' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'public',
						'tls_psk' => ''
					]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": cannot be empty.'
			],
			'Field "tls_psk" should have correct format' => [
				'host' => [
					[
						'host' => 'example.com',
						'groups' => $groups,
						'tls_accept' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'public',
						'tls_psk' => 'fb48829a6f9ebbb70294a75ca09167rr'
					]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": an even number of hexadecimal characters is expected.'
			],
			'Field "tls_psk" cannot have different values for same "tls_psk_identity"' => [
				'host' => [
					[
						'host' => 'bca.example.com',
						'groups' => $groups,
						'tls_accept' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'public',
						'tls_psk' => '5fce1b3e34b520afeffb37ce08c7cd66'
					],
					[
						'host' => 'abc.example.com',
						'groups' => $groups,
						'tls_connect' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'public',
						'tls_psk' => 'fb48829a6f9ebbb70294a75ca0916772'
					]
				],
				'expected_error' => 'Invalid parameter "/2/tls_psk": another tls_psk value is already associated with given tls_psk_identity.'
			],
			'Field "tls_psk" cannot have different values for same "tls_psk_identity" with database check' => [
				'host' => [
					[
						'host' => 'bca.example.com',
						'groups' => $groups,
						'tls_accept' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'public',
						'tls_psk' => '5fce1b3e34b520afeffb37ce08c7cd66'
					]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": another tls_psk value is already associated with given tls_psk_identity.'
			],
			'Field "tls_psk_identity" should have same value of "tls_psk" across all hosts and autoregistration' => [
				'host' => [
					[
						'host' => 'bca.example.com',
						'groups' => $groups,
						'tls_accept' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'autoregistration',
						'tls_psk' => '5fce1b3e34b520afeffb37ce08c7cd66'
					]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": another tls_psk value is already associated with given tls_psk_identity.'
			]
		];
	}

	public static function dataProviderValidHostPskFieldsCreate() {
		$groups = [['groupid' => ':host_group:API tests hosts group']];

		return [
			'Create hosts with "tls_psk"' => [
				'host' => [
					[
						'host' => 'three.example.com',
						'groups' => $groups,
						'tls_connect' => HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'three.example.com',
						'tls_psk' => '6bc6d37628314e1331a21af0be9b4f22'
					],
					[
						'host' => 'four.example.com',
						'groups' => $groups,
						'tls_accept' => HOST_ENCRYPTION_NONE | HOST_ENCRYPTION_PSK,
						'tls_psk_identity' => 'four.example.com',
						'tls_psk' => '10c0086085d3323b4f77af52060ecb24'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider dataProviderInvalidHostPskFieldsCreate
	 * @dataProvider dataProviderValidHostPskFieldsCreate
	 */
	public function testHostPskFields_Create($hosts, $expected_error = null) {
		CTestDataHelper::convertHostReferences($hosts);
		$response = $this->call('host.create', $hosts, $expected_error);

		if ($expected_error === null) {
			self::$data['hostids'] += array_combine(array_column($hosts, 'host'), $response['result']['hostids']);
		}
	}

	public static function prepareTestDataHostPskFieldsUpdate() {
		CTestDataHelper::createObjects([
			'hosts' => [
				[
					'host' => 'psk1.example.com',
					'groups' => [['groupid' => ':host_group:API tests hosts group']],
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => 'example.com',
					'tls_psk' => '79cbf232a3ad3bfe38dee29861f8ba6b'
				],
				[
					'host' => 'psk2.example.com',
					'groups' => [['groupid' => ':host_group:API tests hosts group']],
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => 'example.com',
					'tls_psk' => '79cbf232a3ad3bfe38dee29861f8ba6b'
				],
				[
					'host' => 'psk3.example.com',
					'groups' => [['groupid' => ':host_group:API tests hosts group']],
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => 'psk3.example.com',
					'tls_psk' => 'de4f735c561e5444b0932f7ebd636b85'
				],
				[
					'host' => 'psk4.example.com',
					'groups' => [['groupid' => ':host_group:API tests hosts group']],
					'tls_connect' => HOST_ENCRYPTION_NONE
				]
			]
		]);
	}

	public static function dataProviderInvalidHostPskFieldsUpdate() {
		return [
			'Field "tls_psk_identity" cannot be empty when "tls_connect" is HOST_ENCRYPTION_PSK' => [
				'host' => [
					['hostid' => ':host:psk1.example.com', 'tls_psk_identity' => '']
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": cannot be empty.'
			],
			'Field "tls_psk" cannot have different values for same "tls_psk_identity" on change "tls_psk_identity"' => [
				'host' => [
					['hostid' => ':host:psk3.example.com', 'tls_psk_identity' => 'example.com']
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": another tls_psk value is already associated with given tls_psk_identity.'
			],
			'Field "tls_psk" cannot be empty when "tls_connect" is HOST_ENCRYPTION_PSK' => [
				'host' => [
					['hostid' => ':host:psk1.example.com', 'tls_psk' => '']
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": cannot be empty.'
			],
			'Field "tls_psk" cannot have different values for same "tls_psk_identity" on change "tls_psk"' => [
				'host' => [
					['hostid' => ':host:psk1.example.com', 'tls_psk' => 'de4f735c561e5444b0932f7ebd636b85']
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": another tls_psk value is already associated with given tls_psk_identity.'
			],
			'Field "tls_psk_identity" should have same value of "tls_psk" across all hosts and autoregistration' => [
				'host' => [
					['hostid' => ':host:psk1.example.com', 'tls_psk_identity' => 'autoregistration']
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": another tls_psk value is already associated with given tls_psk_identity.'
			],
			'Field "tls_psk" only default value is allowed when host "tls_connect" != HOST_ENCRYPTION_PSK' => [
				'host' => [
					['hostid' => ':host:psk3.example.com', 'tls_psk' => 'de4f735c561e5444b0932f7ebd636b85', 'tls_connect' => HOST_ENCRYPTION_NONE]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": value must be empty.'
			],
			'Field "tls_psk" only default value is allowed when host "tls_accept" != HOST_ENCRYPTION_PSK' => [
				'host' => [
					['hostid' => ':host:psk4.example.com', 'tls_psk' => 'de4f735c561e5444b0932f7ebd636b85', 'tls_accept' => HOST_ENCRYPTION_NONE]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk": value must be empty.'
			],
			'Field "tls_psk_identity" only default value is allowed when host "tls_connect" != HOST_ENCRYPTION_PSK' => [
				'host' => [
					['hostid' => ':host:psk3.example.com', 'tls_psk_identity' => 'psk3.example.com', 'tls_connect' => HOST_ENCRYPTION_NONE]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value must be empty.'
			],
			'Field "tls_psk_identity" only default value is allowed when host "tls_accept" != HOST_ENCRYPTION_PSK' => [
				'host' => [
					['hostid' => ':host:psk4.example.com', 'tls_psk_identity' => 'psk4.example.com', 'tls_accept' => HOST_ENCRYPTION_NONE]
				],
				'expected_error' => 'Invalid parameter "/1/tls_psk_identity": value must be empty.'
			],
			'Field "tls_issuer" only default value is allowed when host "tls_connect" != HOST_ENCRYPTION_CERTIFICATE' => [
				'host' => [
					['hostid' => ':host:psk3.example.com', 'tls_issuer' => 'psk4.example.com', 'tls_connect' => HOST_ENCRYPTION_NONE]
				],
				'expected_error' => 'Invalid parameter "/1/tls_issuer": value must be empty.'
			],
			'Field "tls_issuer" only default value is allowed when host "tls_accept" != HOST_ENCRYPTION_CERTIFICATE' => [
				'host' => [
					['hostid' => ':host:psk4.example.com', 'tls_issuer' => 'psk4.example.com', 'tls_accept' => HOST_ENCRYPTION_NONE]
				],
				'expected_error' => 'Invalid parameter "/1/tls_issuer": value must be empty.'
			],
			'Field "tls_subject" only default value is allowed when host "tls_connect" != HOST_ENCRYPTION_CERTIFICATE' => [
				'host' => [
					['hostid' => ':host:psk3.example.com', 'tls_subject' => 'psk4.example.com', 'tls_connect' => HOST_ENCRYPTION_NONE]
				],
				'expected_error' => 'Invalid parameter "/1/tls_subject": value must be empty.'
			],
			'Field "tls_subject" only default value is allowed when host "tls_accept" != HOST_ENCRYPTION_CERTIFICATE' => [
				'host' => [
					['hostid' => ':host:psk4.example.com', 'tls_subject' => 'psk4.example.com', 'tls_accept' => HOST_ENCRYPTION_NONE]
				],
				'expected_error' => 'Invalid parameter "/1/tls_subject": value must be empty.'
			]
		];
	}

	public static function dataProviderValidHostPskFieldsUpdate() {
		return [
			'Can update "tls_psk_identity" and "tls_psk"' => [
				'host' => [
					['hostid' => ':host:psk1.example.com', 'tls_psk_identity' => 'psk4.example.com', 'tls_psk' => 'de4f735c561e5444b0932f7ebd636b85']
				]
			],
			'Can update "tls_psk"' => [
				'host' => [
					['hostid' => ':host:psk1.example.com', 'tls_psk' => '11111111111111111111111111111111']
				]
			],
			'Can update "tls_psk_identity"' => [
				'host' => [
					['hostid' => ':host:psk1.example.com', 'tls_psk_identity' => 'psk5.example.com']
				]
			],
			'Can update "tls_psk" for multiple hosts having same value of "tls_psk_identity"' => [
				'host' => [
					['hostid' => ':host:psk1.example.com', 'tls_psk_identity' => 'example.com', 'tls_psk' => '11111111111111111111111111111111'],
					['hostid' => ':host:psk2.example.com', 'tls_psk_identity' => 'example.com', 'tls_psk' => '11111111111111111111111111111111']
				]
			]
		];
	}

	/**
	 * @dataProvider dataProviderInvalidHostPskFieldsUpdate
	 * @dataProvider dataProviderValidHostPskFieldsUpdate
	 */
	public function testHostPskFields_Update($hosts, $expected_error = null) {
		CTestDataHelper::convertHostReferences($hosts);
		$this->call('host.update', $hosts, $expected_error);
	}

	public static function prepareTestDataHostPskFieldsMassUpdate() {
		CTestDataHelper::createObjects([
			'host_groups' => [
				['name' => 'host.massupdate.pskfields']
			],
			'hosts' => [
				[
					'host' => 'host.massupdate.psk1',
					'groups' => [['groupid' => ':host_group:host.massupdate.pskfields']],
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => 'host.massupdate.psk1',
					'tls_psk' => '85fbc6f14e967d7e75b12da395ca9b46'
				],
				[
					'host' => 'host.massupdate.psk2',
					'groups' => [['groupid' => ':host_group:host.massupdate.pskfields']],
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => 'host.massupdate.psk2',
					'tls_psk' => 'dc773e30385b5248b67c29988812d876'
				],
				[
					'host' => 'host.massupdate.psk3',
					'groups' => [['groupid' => ':host_group:host.massupdate.pskfields']],
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => 'host.massupdate.psk3',
					'tls_psk' => '6e801121c08cee058677d3b99e888740'
				],
				[
					'host' => 'host.massupdate.psk4',
					'groups' => [['groupid' => ':host_group:host.massupdate.pskfields']],
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => 'host.massupdate.psk3',
					'tls_psk' => '6e801121c08cee058677d3b99e888740'
				],
				[
					'host' => 'host.massupdate.psk5',
					'groups' => [['groupid' => ':host_group:host.massupdate.pskfields']],
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => 'host.massupdate.psk5',
					'tls_psk' => '3ea2412335c350dcb8a0e76d1152f372'
				]
			]
		]);
	}

	public static function dataProviderInvalidHostPskFieldsMassUpdate() {
		return [
			'Field "tls_accept" is required when "tls_connect" is set' => [
				[
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'hosts' => [
						['hostid' => ':host:host.massupdate.psk1'],
						['hostid' => ':host:host.massupdate.psk2']
					]
				],
				'Both "tls_connect" and "tls_accept" fields must be specified when changing settings of connection encryption.'
			],
			'Field "tls_connect" is required when "tls_accept" is set' => [
				[
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'hosts' => [
						['hostid' => ':host:host.massupdate.psk1']
					]
				],
				'Both "tls_connect" and "tls_accept" fields must be specified when changing settings of connection encryption.'
			],
			'Field "tls_psk" cannot have different values for same "tls_psk_identity" on change "tls_psk_identity"' => [
				[
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => 'host.massupdate.psk1',
					'hosts' => [
						['hostid' => ':host:host.massupdate.psk1'],
						['hostid' => ':host:host.massupdate.psk2']
					]
				],
				'Both "tls_psk_identity" and "tls_psk" fields must be specified when changing the PSK for connection encryption.'
			],
			'Field "tls_psk" cannot have different values for same "tls_psk_identity" on change "tls_psk"' => [
				[
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'tls_psk' => '11111111111111111111111111111111',
					'hosts' => [
						['hostid' => ':host:host.massupdate.psk3']
					]
				],
				'Both "tls_psk_identity" and "tls_psk" fields must be specified when changing the PSK for connection encryption.'
			],
			'Field "tls_psk" only default value is allowed when host "tls_accept" and "tls_connect" != HOST_ENCRYPTION_PSK' => [
				[
					'tls_psk' => '12111111111111111111111111111121',
					'tls_accept' => HOST_ENCRYPTION_NONE,
					'tls_connect' => HOST_ENCRYPTION_CERTIFICATE,
					'hosts' => [
						['hostid' => ':host:host.massupdate.psk5']
					]
				],
				'expected_error' => 'Invalid parameter "/tls_psk": value must be empty.'
			],
			'Field "tls_psk_identity" only default value is allowed when host "tls_accept" and "tls_connect" != HOST_ENCRYPTION_PSK' => [
				[
					'tls_psk_identity' => 'host.massupdate.psk5',
					'tls_accept' => HOST_ENCRYPTION_NONE,
					'tls_connect' => HOST_ENCRYPTION_CERTIFICATE,
					'hosts' => [
						['hostid' => ':host:host.massupdate.psk5']
					]
				],
				'expected_error' => 'Invalid parameter "/tls_psk_identity": value must be empty.'
			],
			'Field "tls_issuer" only default value is allowed when host "tls_accept" and "tls_connect" != HOST_ENCRYPTION_CERTIFICATE' => [
				[
					'tls_issuer' => 'host.massupdate.psk5',
					'tls_accept' => HOST_ENCRYPTION_NONE,
					'tls_connect' => HOST_ENCRYPTION_NONE,
					'hosts' => [
						['hostid' => ':host:host.massupdate.psk5']
					]
				],
				'expected_error' => 'Invalid parameter "/tls_issuer": value must be empty.'
			],
			'Field "tls_subject" only default value is allowed when host "tls_accept" and "tls_connect" != HOST_ENCRYPTION_CERTIFICATE' => [
				[
					'tls_subject' => 'host.massupdate.psk5',
					'tls_accept' => HOST_ENCRYPTION_NONE,
					'tls_connect' => HOST_ENCRYPTION_NONE,
					'hosts' => [
						['hostid' => ':host:host.massupdate.psk5']
					]
				],
				'expected_error' => 'Invalid parameter "/tls_subject": value must be empty.'
			]
		];
	}

	public static function dataProviderValidHostPskFieldsMassUpdate() {
		return [
			'Can update "tls_psk_identity" and "tls_psk"' => [
				[
					'tls_psk_identity' => 'host.massupdate',
					'tls_psk' => 'a296c2411feb2b730bea9742307fba01',
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'hosts' => [
						['hostid' => ':host:host.massupdate.psk1'],
						['hostid' => ':host:host.massupdate.psk2']
					]
				]
			],
			'Can update "tls_psk" and "tls_psk_identity" for multiple hosts having same values of "tls_psk"' => [
				[
					'tls_accept' => HOST_ENCRYPTION_PSK,
					'tls_connect' => HOST_ENCRYPTION_PSK,
					'tls_psk_identity' => 'massupdate.tls_psk_identity',
					'tls_psk' => '11111111111111111111111111111111',
					'hosts' => [
						['hostid' => ':host:host.massupdate.psk3'],
						['hostid' => ':host:host.massupdate.psk4']
					]
				]
			],
			'Can update certificate fields for multiple hosts' => [
				[
					'tls_accept' => HOST_ENCRYPTION_CERTIFICATE,
					'tls_connect' => HOST_ENCRYPTION_CERTIFICATE,
					'tls_issuer' => 'abc',
					'tls_subject' => 'def',
					'hosts' => [
						['hostid' => ':host:host.massupdate.psk3'],
						['hostid' => ':host:host.massupdate.psk4']
					]
				]
			]
		];
	}

	/**
	 * @dataProvider dataProviderInvalidHostPskFieldsMassUpdate
	 * @dataProvider dataProviderValidHostPskFieldsMassUpdate
	 */
	public function testHostPskFields_MassUpdate($data, $expected_error = null) {
		CTestDataHelper::convertHostReferences($data);

		if (array_key_exists('hosts', $data)) {
			CTestDataHelper::convertHostReferences($data['hosts']);
		}

		$this->call('host.massupdate', $data, $expected_error);
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
	 * Get the original host templates.
	 *
	 * @param array $hostids
	 *
	 * @return array
	 */
	private function backupTemplates(array $hostids) {
		// Get data before update.
		$db_hosts = $this->call('host.get', [
			'output' => ['hostid', 'host'],
			'selectParentTemplates' => ['templateid'],
			'hostids' => $hostids
		]);

		return $db_hosts['result'];
	}

	/**
	 * Get current host macros. Due the host.get selectMacros not returning secret values, use regular DB function.
	 *
	 * @param array $hostids
	 *
	 * @return array
	 */
	private function getMacros(array $hostids) {
		$db_hosts = CDBHelper::getAll(
			'SELECT h.host,h.hostid,hm.hostmacroid,hm.macro,hm.value,hm.description,hm.type,hm.automatic'.
			' FROM hosts h'.
			' LEFT JOIN hostmacro hm ON hm.hostid=h.hostid'.
			' WHERE '.dbConditionId('h.hostid', $hostids)
		);

		$result = [];

		foreach ($db_hosts as $db_host) {
			if (!array_key_exists($db_host['hostid'], $result)) {
				$result[$db_host['hostid']] = [
					'hostid' => $db_host['hostid'],
					'host' => $db_host['host'],
					'macros' => []
				];
			}

			if ($db_host['hostmacroid'] != 0) {
				$result[$db_host['hostid']]['macros'][] = [
					'hostmacroid' => $db_host['hostmacroid'],
					'hostid' => $db_host['hostid'],
					'macro' => $db_host['macro'],
					'value' => $db_host['value'],
					'description' => $db_host['description'],
					'type' => $db_host['type'],
					'automatic' => $db_host['automatic']
				];
			}
		}

		return $result;
	}

	/**
	 * Get the original host macros before update.
	 *
	 * @param array $hostids
	 *
	 * @return array
	 */
	private function backupMacros(array $hostids) {
		return $this->getMacros($hostids);
	}

	/**
	 * Revert host templates back to original state before the update to test other cases like massupdate, massremove
	 * and other methods.
	 *
	 * @param array  $hosts                       Array of hosts.
	 * @param string $hosts[]['hostid']           Host ID that will have the data reverted.
	 * @param string $hosts[]['host']             Host technical name in case of error.
	 * @param string $hosts[]['parentTemplates']  Array of host original templates.
	 */
	private function restoreTemplates(array $hosts, $clear = false) {
		foreach ($hosts as $host) {
			$name = $host['host'];

			if ($clear) {
				$host['templates_clear'] = $host['parentTemplates'];
			}
			else {
				$host['templates'] = $host['parentTemplates'];
			}
			unset($host['host'], $host['parentTemplates']);

			$host_upd = $this->call('host.update', $host);

			$this->assertArrayHasKey('hostids', $host_upd['result'], 'host.update failed for host "'.$name.'"');
		}
	}

	/**
	 * Revert host macros back to original state before the update. Instead of using @backup that will backup and
	 * restore all related tables that could take hours, focus only on host macros.
	 *
	 * @param array  $hosts                      Array of hosts and their macros.
	 * @param string $hosts[<hostid>]['host']    Host technical name in case of error.
	 * @param string $hosts[<hostid>]['hostid']  Host ID.
	 * @param string $hosts[<hostid>]['macros']  Array of host original macros.
	 */
	private function restoreMacros(array $hosts) {
		$res = true;

		// Records after update has been made. Possibly new macros are stored.
		$records_current = CDBHelper::getAll(
			'SELECT hm.hostmacroid,hm.hostid,hm.macro,hm.value,hm.description,hm.type,hm.automatic'.
			' FROM hostmacro hm'.
			' WHERE '.dbConditionId('hm.hostid', array_keys($hosts))
		);
		// Otherwise if no records exist, they must have been deleted, so in any case old recods need to be restored.

		foreach ($hosts as $host) {
			$host['macros'] = zbx_toHash($host['macros'], 'hostmacroid');
			$records_current = zbx_toHash($records_current, 'hostmacroid');

			// Host had macros before. Try to restore them.
			if ($host['macros']) {
				$ins_macros = [];
				$del_macros = [];

				$ins_macros = array_diff_key($host['macros'], $records_current);
				$del_macros = array_diff_key($records_current, $host['macros']);

				if ($ins_macros) {
					// Remove old host macro IDs from references that will be re-instertd.
					foreach (self::$data['hostmacroids'] as $key => $hostmacroid) {
						foreach ($ins_macros as $macro) {
							if (bccomp($macro['hostmacroid'], $hostmacroid) == 0) {
								unset(self::$data['hostmacroids'][$key]);
							}
						}
					}

					foreach ($ins_macros as &$macro) {
						unset($macro['hostmacroid']);
					}
					unset($macro);

					// Prepare the new host macro IDs.
					$nextid = CDBHelper::getAll(
						'SELECT i.nextid'.
						' FROM ids i'.
						' WHERE i.table_name='.zbx_dbstr('hostmacro').
							' AND i.field_name='.zbx_dbstr('hostmacroid').
						' FOR UPDATE'
					)[0]['nextid'] + 1;

					$ids = DB::insertBatch('hostmacro', $ins_macros);
					$newids = array_fill($nextid, count($ins_macros), true);

					$this->assertEquals(array_keys($newids), array_values($ids),
						'host.update with macros failed for host "'.$host['host'].'".'
					);

					// Again the macro name must match the key.
					foreach ($ins_macros as $macro) {
						$db_hostmacro = CDBHelper::getRow(
							'SELECT hm.hostmacroid'.
							' FROM hostmacro hm'.
							' WHERE hm.hostid='.zbx_dbstr($macro['hostid']).
								' AND hm.macro='.zbx_dbstr($macro['macro'])
						);

						$key = str_replace('{$', '', $macro['macro']);
						$key = str_replace('}', '', $key);
						$key = strtolower($key);
					}

					// Add macros to references just like in data preparation.
					self::$data['hostmacroids'][$key] = $db_hostmacro['hostmacroid'];
				}

				if ($del_macros) {
					// Delete the macros that were inserted in host.update method.
					$res = $res && DB::delete('hostmacro', [
						'hostmacroid' => array_column($del_macros, 'hostmacroid')
					]);

					// Inserted macros during tests were not added to references. So there is nothing to remove.
				}

				// Check the old records.
				foreach ($host['macros'] as $macro) {
					// If old host macro ID still exists, update it.
					if (in_array($macro['hostmacroid'], self::$data['hostmacroids'])) {
						// Update the macro to previous value regardless of what it was before.
						$hostmacroid = $macro['hostmacroid'];
						unset($macro['hostmacroid']);

						$res = $res && DB::update('hostmacro', [
							'values' => $macro,
							'where' => [
								'hostmacroid' => $hostmacroid
							]
						]);
					}
				}
			}
			// Host did not have macros, but new were added in host.update method. Remove the added ones.
			elseif ($records_current) {
				$res = $res && DB::delete('hostmacro', [
					'hostmacroid' => array_column($records_current, 'hostmacroid')
				]);
			}

			$this->assertSame(true, $res, 'host.update failed for host "'.$host['host'].'"');
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
		CDataHelper::call('template.delete', array_values(self::$data['templateids']));

		// Delete groups.
		CDataHelper::call('hostgroup.delete', [self::$data['hostgroupid']]);
		CDataHelper::call('templategroup.delete', [self::$data['templategroupid']]);

		CTestDataHelper::cleanUp();
		CDataHelper::call('autoregistration.update', ['tls_accept' => HOST_ENCRYPTION_NONE]);
	}
}
