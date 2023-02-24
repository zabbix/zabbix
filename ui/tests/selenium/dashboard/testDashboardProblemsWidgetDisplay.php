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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * @backup config, hstgrp, widget
 *
 * @onBefore prepareDashboardData, prepareProblemsData
 */
class testDashboardProblemsWidgetDisplay extends CWebTest {

	use TableTrait;

	private static $hostid;
	private static $dashboardid;
	private static $itemids;
	private static $triggerids;
	private static $acktime;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}


	public function prepareDashboardData() {
		$response = CDataHelper::call('dashboard.create', [
			'name' => 'Dashboard for Problem widget check',
			'auto_start' => 0,
			'pages' => [
				[
					'name' => 'First Page',
					'display_period' => 3600
				]
			]
		]);

		$this->assertArrayHasKey('dashboardids', $response);
		self::$dashboardid = $response['dashboardids'][0];
	}

	public function prepareProblemsData() {
		// Create hostgroup for hosts with items triggers.
		$hostgroups = CDataHelper::call('hostgroup.create', [['name' => 'Group for Problems Widgets']]);
		$this->assertArrayHasKey('groupids', $hostgroups);
		$groupid = $hostgroups['groupids'][0];

		// Create host for items and triggers.
		$hosts = CDataHelper::call('host.create', [
			'host' => 'Host for Problems Widgets',
			'groups' => [['groupid' => $groupid]]
		]);
		$this->assertArrayHasKey('hostids', $hosts);
		self::$hostid = $hosts['hostids'][0];

		// Create items on previously created host.
		$item_names = ['float', 'char', 'log', 'unsigned', 'text'];

		$items_data = [];
		foreach ($item_names as $i => $item) {
			$items_data[] = [
				'hostid' => self::$hostid,
				'name' => $item,
				'key_' => $item,
				'type' => 2,
				'value_type' => $i
			];
		}

		$items = CDataHelper::call('item.create', $items_data);
		$this->assertArrayHasKey('itemids', $items);
		self::$itemids = CDataHelper::getIds('name');

		// Create triggers based on items.
		$triggers = CDataHelper::call('trigger.create', [
			[
				'description' => 'Trigger for widget 1 float',
				'expression' => 'last(/Host for Problems Widgets/float)=0',
				'opdata' => 'Item value: {ITEM.LASTVALUE}',
				'priority' => 0
			],
			[
				'description' => 'Trigger for widget 1 char',
				'expression' => 'last(/Host for Problems Widgets/char)=0',
				'priority' => 1,
				'manual_close' => 1
			],
			[
				'description' => 'Trigger for widget 2 log',
				'expression' => 'last(/Host for Problems Widgets/log)=0',
				'priority' => 2
			],
			[
				'description' => 'Trigger for widget 2 unsigned',
				'expression' => 'last(/Host for Problems Widgets/unsigned)=0',
				'opdata' => 'Item value: {ITEM.LASTVALUE}',
				'priority' => 3
			],
			[
				'description' => 'Trigger for widget text',
				'expression' => 'last(/Host for Problems Widgets/text)=0',
				'priority' => 4
			]
		]);
		$this->assertArrayHasKey('triggerids', $triggers);
		self::$triggerids = CDataHelper::getIds('description');

		// Create events.
		$time = time();
		$i=0;

		foreach (array_values(self::$itemids) as $itemid) {
			CDataHelper::addItemData($itemid, 0);
		}

		foreach (self::$triggerids as $name => $id) {
			DBexecute('INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES ('.
				(1009950 + $i).', 0, 0, '.zbx_dbstr($id).', '.$time.', 0, 1, '.zbx_dbstr($name).', '.zbx_dbstr($i).')'
			);
			$i++;
		}

		// Create problems.
		$j=0;
		foreach (self::$triggerids as $name => $id) {
			DBexecute('INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES ('.
				(1009950 + $j).', 0, 0, '.zbx_dbstr($id).', '.$time.', 0, '.zbx_dbstr($name).', '.zbx_dbstr($j).')'
			);
			$j++;
		}

		// Change triggers' state to Problem. Manual close is true for the problem: Trigger for widget 1 char'.
		DBexecute('UPDATE triggers SET value = 1 WHERE description IN ('.zbx_dbstr('Trigger for widget 1 float').', '.
				zbx_dbstr('Trigger for widget 2 log').', '.zbx_dbstr('Trigger for widget 2 unsigned').', '.
				zbx_dbstr('Trigger for widget text').')'
		);
		DBexecute('UPDATE triggers SET value = 1, manual_close = 1 WHERE description = '.zbx_dbstr('Trigger for widget 1 char'));

		// Suppress the problem: 'Trigger for widget text'.
		DBexecute('INSERT INTO event_suppress (event_suppressid, eventid, maintenanceid, suppress_until) VALUES (100990, 1009954, NULL, 0)');

		// Acknowledge the problem: 'Trigger for widget 2 unsigned' and get acknowledge time.
		CDataHelper::call('event.acknowledge', [
			'eventids' => 1009953,
			'action' => 6,
			'message' => 'Acknowledged event'
		]);

		$event = CDataHelper::call('event.get', [
			'eventids' => 1009953,
			'select_acknowledges' => ['clock']
		]);
		self::$acktime = CTestArrayHelper::get($event, '0.acknowledges.0.clock');
	}

	public static function getCheckWidgetTable() {
		return [
			// #0 Filtered by Host group.
			[
				[
					'fields' => [
						'Name' => 'Group filter',
						'Host groups' => 'Group for Problems Widgets'
					],
					'result' => [
						'Trigger for widget 2 unsigned',
						'Trigger for widget 2 log',
						'Trigger for widget 1 char',
						'Trigger for widget 1 float'
					]
				]
			],
			// #1 Filtered by Host group, show suppressed.
			[
				[
					'fields' => [
						'Name' => 'Group, unsupressed filter',
						'Host groups' => 'Group for Problems Widgets',
						'Show suppressed problems' => true
					],
					'result' => [
						'Trigger for widget text',
						'Trigger for widget 2 unsigned',
						'Trigger for widget 2 log',
						'Trigger for widget 1 char',
						'Trigger for widget 1 float'
					]
				]
			],
			// #2 Filtered by Host group, show unacknowledged.
			[
				[
					'fields' => [
						'Name' => 'Group, unucknowledged filter',
						'Host groups' => 'Group for Problems Widgets',
						'Show unacknowledged only' => true
					],
					'result' => [
						'Trigger for widget 2 log',
						'Trigger for widget 1 char',
						'Trigger for widget 1 float'
					]
				]
			],
			// #3 Filtered by Host group, Sort by problem.
			[
				[
					'fields' => [
						'Name' => 'Group, sort by Problem ascending filter',
						'Host groups' => 'Group for Problems Widgets',
						'Sort entries by' => 'Problem (ascending)'
					],
					'result' => [
						'Trigger for widget 1 char',
						'Trigger for widget 1 float',
						'Trigger for widget 2 log',
						'Trigger for widget 2 unsigned'
					],
					'headers' => ['Time', 'Recovery time', 'Status', 'Info', 'Host', 'Problem • Severity', 'Duration',
						'Ack', 'Actions'
					]
				]
			],
			// #4 Filtered by Host, Sort by severity.
			[
				[
					'fields' => [
						'Name' => 'Group, sort by Severity ascending filter',
						'Hosts' => 'Host for Problems Widgets',
						'Sort entries by' => 'Severity (ascending)'
					],
					'result' => [
						'Trigger for widget 1 float',
						'Trigger for widget 1 char',
						'Trigger for widget 2 log',
						'Trigger for widget 2 unsigned'
					],
					'headers' => ['Time', 'Recovery time', 'Status', 'Info', 'Host', 'Problem • Severity', 'Duration',
						'Ack', 'Actions'
					]
				]
			],
			// #5 Filtered by Host, Problem.
			[
				[
					'fields' => [
						'Name' => 'Group, Problem filter',
						'Hosts' => 'Host for Problems Widgets',
						'Problem' => 'Trigger for widget 2'
					],
					'result' => [
						'Trigger for widget 2 unsigned',
						'Trigger for widget 2 log'
					]
				]
			],
			// #6 Filtered by Excluded groups.
			[
				[
					'fields' => [
						'Name' => 'Group, Excluded groups',
						'Exclude host groups' => [
							'Group for Problems Widgets',
							'Zabbix servers',
							'Group to check triggers filtering',
							'Another group to check Overview',
							'Group to check Overview'
						]
					],
					'result' => [
						'Trigger for tag permissions Oracle',
						'Trigger for tag permissions MySQL'
					]
				]
			],
			// #7 Filtered by Host, some severities.
			[
				[
					'fields' => [
						'Name' => 'Group, some severities',
						'Hosts' => 'Host for Problems Widgets',
						'id:severities_0' => true,
						'id:severities_2' => true,
						'id:severities_4' => true
					],
					'result' => [
						'Trigger for widget 2 log',
						'Trigger for widget 1 float'
					]
				]
			],
			// #8 Filtered by Host group, tags.
			[
				[
					'fields' => [
						'Name' => 'Group, tags, show 1',
						'Host groups' => 'Zabbix servers',
						'Show tags' => 1
					],
					'Tags' => [
						'tags' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'tag' => 'Delta',
								'operator' => 'Exists'
							]
						]
					],
					'result' => [
						'Fourth test trigger with tag priority',
						'First test trigger with tag priority'
					],
					'tags_display' => [
						'Delta: t',
						'Alpha: a'
					],
					'headers' => ['Time', '', '', 'Recovery time', 'Status', 'Info', 'Host', 'Problem • Severity', 'Duration',
							'Ack', 'Actions', 'Tags'
					]
				]
			],
			// #9 Filtered by Host group, tag + value.
			[
				[
					'fields' => [
						'Name' => 'Group, tags, show 2',
						'Host groups' => 'Zabbix servers',
						'Show tags' => 2
					],
					'Tags' => [
						'tags' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'tag' => 'Eta',
								'operator' => 'Equals',
								'value' => 'e'
							]
						]
					],
					'result' => [
						'Fourth test trigger with tag priority',
						'Second test trigger with tag priority'
					],
					'tags_display' => [
						'Eta: eDelta: t',
						'Eta: eBeta: b'
					],
					'headers' => ['Time', '', '', 'Recovery time', 'Status', 'Info', 'Host', 'Problem • Severity', 'Duration',
							'Ack', 'Actions', 'Tags'
					]
				]
			],
			// #10 Filtered by Host group, Operator: Or, show 3, shortened.
			[
				[
					'fields' => [
						'Name' => 'Group, tags, show 3, shortened',
						'Host groups' => 'Zabbix servers',
						'Show tags' => 3,
						'Tag name' => 'Shortened',
						'Show timeline' => false
					],
					'Tags' => [
						'evaluation' => 'Or',
						'tags' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'tag' => 'Theta',
								'operator' => 'Contains',
								'value' => 't'
							],
							[
								'tag' => 'Tag4',
								'operator' => 'Exists'
							]
						]
					],
					'result' => [
						'Test trigger to check tag filter on problem page',
						'Fourth test trigger with tag priority',
						'Third test trigger with tag priority'
					],
					'tags_display' => [
						'DatSer: abcser: abcdef',
						'The: tDel: tEta: e',
						'The: tAlp: aIot: i'
					],
					'headers' => ['Time', 'Recovery time', 'Status', 'Info', 'Host', 'Problem • Severity', 'Duration',
							'Ack', 'Actions', 'Tags'
					]
				]
			],
			// #11 Filtered by Host group, tags, show 3, shortened, tag priority.
			[
				[
					'fields' => [
						'Name' => 'Group, tags, show 3, shortened, tag priority',
						'Host groups' => 'Zabbix servers',
						'Show tags' => 3,
						'Tag name' => 'None',
						'Show timeline' => false,
						'Tag display priority' => 'Gamma, Eta'
					],
					'Tags' => [
						'evaluation' => 'And/Or',
						'tags' => [
							[
								'action' => USER_ACTION_UPDATE,
								'index' => 0,
								'tag' => 'Theta',
								'operator' => 'Equals',
								'value' => 't'
							],
							[
								'tag' => 'Kappa',
								'operator' => 'Does not exist'
							]
						]
					],
					'result' => [
						'Fourth test trigger with tag priority'
					],
					'tags_display' => [
						'get'
					],
					'headers' => ['Time', 'Recovery time', 'Status', 'Info', 'Host', 'Problem • Severity', 'Duration',
							'Ack', 'Actions', 'Tags'
					]
				]
			],
			// #12 Filtered by Host, operational data - Separately, Show suppressed.
			[
				[
					'fields' => [
						'Name' => 'Host, operational data - Separately, Show suppressed',
						'Hosts' => 'Host for Problems Widgets',
						'Show operational data' => 'Separately',
						'Show suppressed problems' => true
					],
					'result' => [
						'Trigger for widget text',
						'Trigger for widget 2 unsigned',
						'Trigger for widget 2 log',
						'Trigger for widget 1 char',
						'Trigger for widget 1 float'
					],
					'operational_data' => [
						'0',
						"Item value: ".
								"\n0",
						'0',
						'0',
						"Item value: ".
								"\n0"
					],
					'headers' => ['Time', '', '', 'Recovery time', 'Status', 'Info', 'Host', 'Problem • Severity', 'Operational data',
							'Duration', 'Ack', 'Actions'
					]
				]
			],
			// #13 Filtered by Host, operational data - With problem name, Show unacknowledged.
			[
				[
					'fields' => [
						'Name' => 'Host, operational data - With problem name, Show unacknowledged',
						'Hosts' => 'Host for Problems Widgets',
						'Show operational data' => 'With problem name',
						'Show unacknowledged only' => true
					],
					'result' => [
						'Trigger for widget 2 log',
						'Trigger for widget 1 char',
						"Trigger for widget 1 float (Item value: ".
								"\n0)"
					]
				]
			],
			// #14 Filtered by Host group, show lines = 2.
			[
				[
					'fields' => [
						'Name' => 'Host group, show lines = 2',
						'Host groups' => 'Group for Problems Widgets',
						'Show lines' => 2
					],
					'result' => [
						'Trigger for widget 2 unsigned',
						'Trigger for widget 2 log'
					],
					'stats' => '2 of 4 problems are shown'
				]
			]
		];
	}

	/**
	 * @dataProvider getCheckWidgetTable
	 */
	public function testDashboardProblemsWidgetDisplay_CheckTable($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->addWidget()->asForm();
		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();

		// Fill Problems widget filter.
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Problems')]);
		$form->fill($data['fields']);

		if (array_key_exists('Tags', $data)) {
			$form->getField('id:evaltype')->fill(CTestArrayHelper::get($data['Tags'], 'evaluation', 'And/Or'));
			$form->getField('id:tags_table_tags')->asMultifieldTable()->fill($data['Tags']['tags']);
		}

		$form->submit();

		// Check saved dashboard.
		$dialog->ensureNotPresent();
		$dashboard->save();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Assert Problems widget's table.
		$dashboard->getWidget($data['fields']['Name'])->waitUntilReady();
		$table = $this->query('class:list-table')->asTable()->one();

		// Assert table headers depending on widget settings.
		$headers = (CTestArrayHelper::get($data, 'headers', ['Time', '', '', 'Recovery time', 'Status', 'Info',
				'Host', 'Problem • Severity', 'Duration','Ack', 'Actions']
		));
		$this->assertEquals($headers, $table->getHeadersText());

		// When there are shown less lines than filered, table appears unusual and doesn't fit for framework functions.
		if (CTestArrayHelper::get($data['fields'], 'Show lines')) {
			$this->assertEquals(count($data['result'])+1, $table->getRows()->count());

			// Assert table rows.
			$result = [];
			for ($i=0; $i<count($data['result']); $i++) {
				$result[] = $table->getRow($i)->getColumn('Problem • Severity')->getText();
			}

			$this->assertEquals($data['result'], $result);

			// Assert table stats.
			$this->assertEquals($data['stats'], $table->getRow(count($data['result']))->getText());
		}
		else {
			$this->assertTableDataColumn($data['result'], 'Problem • Severity');
		}

		if (CTestArrayHelper::get($data, 'operational_data')) {
			$this->assertTableDataColumn($data['operational_data'], 'Operational data');
		}

		// Assert Problems widget's tags column.
		if (array_key_exists('Tags', $data)) {
			$this->assertTableDataColumn($data['tags_display'], 'Tags');
		}

		// Delete created widget.
		DBexecute('DELETE FROM widget'.
				' WHERE dashboard_pageid'.
				' IN (SELECT dashboard_pageid'.
					' FROM dashboard_page'.
					' WHERE dashboardid='.self::$dashboardid.
				')'
		);
	}
}
