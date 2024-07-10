<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


require_once dirname(__FILE__).'/../common/testWidgets.php';

/**
 * @backup config, hstgrp, dashboard, maintenances
 *
 * @dataSource GlobalMacros
 *
 * @onBefore prepareTopHostsDisplayData
 */
class testDashboardTopHostsWidgetDisplay extends testWidgets {

	protected static $dashboardid;

	public function prepareTopHostsDisplayData() {
		$dashboards = CDataHelper::call('dashboard.create', [
			'name' => 'Dashboard for Top Hosts display check',
			'auto_start' => 0,
			'pages' => [
				[
					'name' => 'First Page',
					'display_period' => 3600
				]
			]
		]);
		self::$dashboardid = $dashboards['dashboardids'][0];

		$template_groups = CDataHelper::call('templategroup.create', [['name' => 'Top Hosts test template group']]);
		$template_group = $template_groups['groupids'][0];

		$templates = CDataHelper::createTemplates([
			[
				'host' => 'Template1',
				'groups' => ['groupid' => $template_group],
				'items' => [
					[
						'name' => 'Item1',
						'key_' => 'key[1]',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Item2',
						'key_' => 'key[2]',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			],
			[
				'host' => 'Template2',
				'groups' => ['groupid' => $template_group],
				'items' => [
					[
						'name' => 'Item1',
						'key_' => 'key[1]',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			]
		]);

		$template1 = $templates['templateids']['Template1'];
		$template2 = $templates['templateids']['Template2'];

		$host_groups = CDataHelper::call('hostgroup.create', [['name' => 'Top Hosts test host group']]);
		$host_group = $host_groups['groupids'][0];

		CDataHelper::call('host.create', [
			[
				'host' => 'HostA',
				'groups' => ['groupid' => $host_group],
				'templates' => [['templateid' => $template1]],
				'tags' => [['tag' => 'host', 'value' => 'A']]
			],
			[
				'host' => 'HostB',
				'groups' => ['groupid' => $host_group],
				'templates' => [['templateid' => $template1]],
				'tags' => [['tag' => 'host', 'value' => 'B']]
			],
			[
				'host' => 'HostC',
				'groups' => ['groupid' => $host_group],
				'templates' => [['templateid' => $template2]],
				'tags' => [['tag' => 'host', 'value' => 'B'], ['tag' => 'host', 'value' => 'C'], ['tag' => 'tag']]
			]
		]);

		$hostids = CDataHelper::getIds('host');

		$itemids = [];
		foreach ($hostids as $host) {
			$itemids[] = CDBHelper::getValue('SELECT itemid FROM items WHERE key_='.
					zbx_dbstr('key[1]').' AND hostid='.zbx_dbstr($host)
			);
		}

		foreach ($itemids as $i => $itemid) {
			CDataHelper::addItemData($itemid, $i);
		}

		// Create item on host in maintenance and add data to it.
		$response = CDataHelper::createHosts([
			[
				'host' => 'Host in maintenance',
				'groups' => [['groupid' => $host_group]],
				'items' => [
					[
						'name' => 'Maintenance trapper',
						'key_' => 'maintenance_trap',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			]
		]);

		$maintenance_itemid = $response['itemids']['Host in maintenance:maintenance_trap'];
		$maintenance_hostid = $response['hostids']['Host in maintenance'];

		CDataHelper::addItemData($maintenance_itemid, 100);

		// Create Maintenance and host in maintenance.
		$maintenances = CDataHelper::call('maintenance.create', [
			[
				'name' => 'Maintenance for Top Hosts widget',
				'maintenance_type' => MAINTENANCE_TYPE_NORMAL,
				'description' => 'Maintenance for icon check in Top Hosts widget',
				'active_since' => time() - 100,
				'active_till' => time() + 31536000,
				'hosts' => [['hostid' => $maintenance_hostid]],
				'timeperiods' => [[]]
			]
		]);
		$maintenanceid = $maintenances['maintenanceids'][0];

		DBexecute('UPDATE hosts SET maintenanceid='.zbx_dbstr($maintenanceid).
				', maintenance_status=1, maintenance_type='.MAINTENANCE_TYPE_NORMAL.', maintenance_from='.zbx_dbstr(time()-1000).
				' WHERE hostid='.zbx_dbstr($maintenance_hostid)
		);
	}

	public static function getCheckTopHostsTableData() {
		return [
			// #0 Filtered by hosts, in column: item which came from two different templates.
			[
				[
					'fields' => [
						'Name' => 'Item on different hosts from one template',
						'Hosts' => ['HostA', 'HostB', 'HostC']
					],
					'Columns' => [
						[
							'fields' => [
								'Name' => 'Host',
								'Data' => 'Host name'
							]
						],
						[
							'fields' => [
								'Name' => 'Column1',
								'Item name' => [
									'values' => 'Item1',
									'context' => ['values' => 'HostA']
								]
							]
						]
					],
					'result' => [
						['Host' => 'HostC', 'Column1' => '2.00'],
						['Host' => 'HostB', 'Column1' => '1.00'],
						['Host' => 'HostA', 'Column1' => '0.00']
					],
					'headers' => ['Host', 'Column1']
				]
			],
			// #1 Filtered by host group, Host limit is set less than filtered result.
			[
				[
					'fields' => [
						'Name' => 'Show lines < then possible result',
						'Host groups' => ['Top Hosts test host group'],
						'Host limit' => 2
					],
					'Columns' => [
						[
							'fields' => [
								'Name' => 'Item',
								'Item name' => [
									'values' => 'Item1',
									'context' => ['values' => 'HostA']
								]
							]
						]
					],
					'result' => [
						['Item' => '2.00'],
						['Item' => '1.00']
					],
					'headers' => ['Item']
				]
			],
			// #2 Filtered so that widget shows No data.
			// TODO: This case is failing until ZBX-24828 is fixed.
//			[
//				[
//					'fields' => [
//						'Name' => 'No data'
//					],
//					'Columns' => [
//						[
//							'fields' => [
//								'Name' => 'Item2',
//								'Item name' => [
//									'values' => 'Item2',
//									'context' => ['values' => 'HostA']
//								]
//							]
//						]
//					],
//					'result' => [],
//					'headers' => ['Item2']
//				]
//			],
			// #3 Filtered by tags, columns: text and item, order newest in bottom.
			[
				[
					'fields' => [
						'Name' => 'Hosts filtered by tag'
					],
					'Tags' => [
						'tags' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'tag' => 'host',
								'operator' => 'Equals',
								'value' => 'B'
							]
						]
					],
					'Columns' => [
						[
							'fields' => [
								'Name' => 'Text column',
								'Data' => 'Text',
								'Text' => '🙂🙃み け め 𒁥 test_text:'
							]
						],
						[
							'fields' => [
								'Name' => '🙂🙃み け め 𒁥',
								'Item name' => [
									'values' => 'Item1',
									'context' => ['values' => 'HostA']
								]
							]
						]
					],
					'result' => [
						['Text column' => '🙂🙃み け め 𒁥 test_text:', '🙂🙃み け め 𒁥' => '1.00'],
						['Text column' => '🙂🙃み け め 𒁥 test_text:', '🙂🙃み け め 𒁥' => '2.00']
					],
					'headers' => ['Text column', '🙂🙃み け め 𒁥']
				]
			],
			// #4 Filtered by tags with OR operator, different macros used in columns.
			[
				[
					'fields' => [
						'Name' => 'Hosts filtered by tag, macros in columns',
						'Order by' => 'Host name'
					],
					'Tags' => [
						'evaluation' => 'Or',
						'tags' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'tag' => 'host',
								'operator' => 'Equals',
								'value' => 'B'
							],
							[
								'action' => USER_ACTION_ADD,
								'tag' => 'tag',
								'operator' => 'Exists'
							]
						]
					],
					'Columns' => [
						[
							'fields' => [
								'Name' => 'Host name',
								'Data' => 'Host name'
							]
						],
						[
							'fields' => [
								'Name' => 'Text: Macro in host',
								'Data' => 'Text',
								'Text' => '{HOST.HOST}' // This will be resolved in widget.
							]
						],
						[
							'fields' => [
								'Name' => '{#LLD_MACRO}',
								'Item name' => [
									'values' => 'Item1',
									'context' => ['values' => 'HostB']
								]
							]
						],
						[
							'fields' => [
								'Name' => '{HOST.HOST}',
								'Item name' => [
									'values' => 'Item1',
									'context' => ['values' => 'HostB']
								]
							]
						],
						[
							'fields' => [
								'Name' => '{$USERMACRO}',
								'Item name' => [
									'values' => 'Item2',
									'context' => ['values' => 'HostA']
								]
							]
						],
						[
							'fields' => [
								'Name' => '{$1} Resolved',
								'Data' => 'Text',
								'Text' => '{$1}' // This will be resolved in widget.
							]
						],
					],
					'result' => [
						[
							'Host name' => 'HostB',
							'Text: Macro in host' => 'HostB',  // Resolved global macro.
							'{#LLD_MACRO}' => '1.00',
							'{HOST.HOST}' => '1.00',
							'{$USERMACRO}' => '',
							'{$1} Resolved' => 'Numeric macro' // Resolved user macro.
						],
						[
							'Host name' => 'HostC',
							'Text: Macro in host' => 'HostC', // Resolved global macro.
							'{#LLD_MACRO}' => '2.00',
							'{HOST.HOST}' => '2.00',
							'{$USERMACRO}' => '',
							'{$1} Resolved' => 'Numeric macro' // Resolved user macro.
						]
					],
					'headers' => ['Host name', 'Text: Macro in host', '{#LLD_MACRO}', '{HOST.HOST}',
							'{$USERMACRO}', '{$1} Resolved'
					]
				]
			],
			// #5 Filtered by Host group, not including Host in maintenance.
			[
				[
					'fields' => [
						'Name' => 'Hosts group without maintenance',
						'Host groups' => 'Top Hosts test host group'
					],
					'Columns' => [
						[
							'fields' => [
								'Name' => 'Host',
								'Data' => 'Host name'
							]
						],
						[
							'fields' => [
								'Name' => 'Maintenance Trapper',
								'Item name' => [
									'values' => 'Maintenance trapper',
									'context' => ['values' => 'Host in maintenance']
								]
							]
						],
						[
							'fields' => [
								'Name' => 'Item1',
								'Item name' => [
									'values' => 'Item1',
									'context' => ['values' => 'HostA']
								],
								'Decimal places' => 5
							]
						]
					],
					'result' => [
						['Host' => 'HostA', 'Maintenance Trapper' => '', 'Item1' => '0.00000'],
						['Host' => 'HostB', 'Maintenance Trapper' => '', 'Item1' => '1.00000'],
						['Host' => 'HostC', 'Maintenance Trapper' => '', 'Item1' => '2.00000']
					],
					'headers' => ['Host', 'Maintenance Trapper', 'Item1']
				]
			],
			// #6 Filtered by Host group, including Host in maintenance.
			[
				[
					'fields' => [
						'Name' => 'Hosts group with maintenance',
						'Host groups' => 'Top Hosts test host group',
						'Show hosts in maintenance' => true
					],
					'Columns' => [
						[
							'fields' => [
								'Name' => 'Host',
								'Data' => 'Host name'
							]
						],
						[
							'fields' => [
								'Name' => 'Maintenance Trapper',
								'Item name' => [
									'values' => 'Maintenance trapper',
									'context' => ['values' => 'Host in maintenance']
								],
								'Decimal places' => 4
							]
						],
						[
							'fields' => [
								'Name' => 'Item1',
								'Item name' => [
									'values' => 'Item1',
									'context' => ['values' => 'HostA']
								]
							]
						]
					],
					'result' => [
						['Host' => 'HostA', 'Maintenance Trapper' => '', 'Item1' => '0.00'],
						['Host' => 'HostB', 'Maintenance Trapper' => '', 'Item1' => '1.00'],
						['Host' => 'HostC', 'Maintenance Trapper' => '', 'Item1' => '2.00'],
						['Host' => 'Host in maintenance', 'Maintenance Trapper' => '100.0000', 'Item1' => '']
					],
					'headers' => ['Host', 'Maintenance Trapper', 'Item1'],
					'check_maintenance' => [
						'Host in maintenance' => "Maintenance for Top Hosts widget [Maintenance with data collection]\n".
							"Maintenance for icon check in Top Hosts widget"
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCheckTopHostsTableData
	 *
	 * @onAfter deleteWidgets
	 */
	public function testDashboardTopHostsWidgetDisplay_CheckTable($data) {
		$this->checkWidgetDisplay($data, 'Top hosts', $data['headers']);
	}
}
