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


require_once __DIR__.'/../common/testWidgets.php';

/**
 * @backup config, hstgrp, widget
 *
 * @dataSource UserPermissions
 *
 * @onBefore prepareDashboardData, prepareProblemsData
 */
class testDashboardProblemsWidgetDisplay extends testWidgets {

	protected static $dashboardid;
	protected static $time;
	protected static $acktime;
	protected static $cause_problemid;
	protected static $symptom_problemid;
	protected static $symptom_problemid2;
	protected static $eventid_for_widget_text;
	protected static $eventid_for_widget_unsigned;

	/**
	 * Attach Behaviors to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class,
			CWidgetBehavior::class,
			[
				'class' => CTagBehavior::class,
				'tag_selector' => 'id:tags_table_tags'
			]
		];
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
		// Remove PROBLEM event status blinking to get correct status in table column.
		CDataHelper::call('settings.update', [
			'problem_unack_style' => 0
		]);

		// Create hostgroup for hosts with items triggers.
		$hostgroups = CDataHelper::call('hostgroup.create', [
			['name' => 'Group for Problems Widgets'],
			['name' => 'Group for Cause and Symptoms']
		]);
		$this->assertArrayHasKey('groupids', $hostgroups);
		$problem_groupid = $hostgroups['groupids'][0];
		$symptoms_groupid = $hostgroups['groupids'][1];

		// Create host for items and triggers.
		$hosts = CDataHelper::call('host.create', [
			[
				'host' => 'Host for Problems Widgets',
				'groups' => [['groupid' => $problem_groupid]]
			],
			[
				'host' => 'Host for Cause and Symptoms',
				'groups' => [['groupid' => $symptoms_groupid]]
			]
		]);
		$this->assertArrayHasKey('hostids', $hosts);
		$problem_hostid = $hosts['hostids'][0];
		$symptoms_hostid = $hosts['hostids'][1];

		// Create items on previously created hosts.
		$problem_item_names = ['float', 'char', 'log', 'unsigned', 'text'];

		$problem_items_data = [];
		foreach ($problem_item_names as $i => $item) {
			$problem_items_data[] = [
				'hostid' => $problem_hostid,
				'name' => $item,
				'key_' => $item,
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => $i
			];
		}

		$problem_items = CDataHelper::call('item.create', $problem_items_data);
		$this->assertArrayHasKey('itemids', $problem_items);
		$problem_itemids = CDataHelper::getIds('name');

		$symptoms_items_data = [];
		foreach (['trap1', 'trap2', 'trap3'] as $i => $item) {
			$symptoms_items_data[] = [
				'hostid' => $symptoms_hostid,
				'name' => $item,
				'key_' => $item,
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => $i
			];
		}

		$symptoms_items = CDataHelper::call('item.create', $symptoms_items_data);
		$this->assertArrayHasKey('itemids', $symptoms_items);
		$symptoms_itemids = CDataHelper::getIds('name');

		// Create triggers based on items.
		CDataHelper::call('trigger.create', [
			[
				'description' => 'Trigger for widget 1 float',
				'expression' => 'last(/Host for Problems Widgets/float)=0',
				'opdata' => 'Item value: {ITEM.LASTVALUE}',
				'priority' => TRIGGER_SEVERITY_NOT_CLASSIFIED
			],
			[
				'description' => 'Trigger for widget 1 char',
				'expression' => 'last(/Host for Problems Widgets/char)=0',
				'priority' => TRIGGER_SEVERITY_INFORMATION,
				'manual_close' => 1
			],
			[
				'description' => 'Trigger for widget 2 log',
				'expression' => 'last(/Host for Problems Widgets/log)=0',
				'priority' => TRIGGER_SEVERITY_WARNING
			],
			[
				'description' => 'Trigger for widget 2 unsigned',
				'expression' => 'last(/Host for Problems Widgets/unsigned)=0',
				'opdata' => 'Item value: {ITEM.LASTVALUE}',
				'priority' => TRIGGER_SEVERITY_AVERAGE
			],
			[
				'description' => 'Trigger for widget text',
				'expression' => 'last(/Host for Problems Widgets/text)=0',
				'priority' => TRIGGER_SEVERITY_HIGH
			],
			[
				'description' => 'Cause problem',
				'expression' => 'last(/Host for Cause and Symptoms/trap1)=0',
				'priority' => TRIGGER_SEVERITY_INFORMATION
			],
			[
				'description' => 'Symptom problem',
				'expression' => 'last(/Host for Cause and Symptoms/trap2)=0',
				'priority' => TRIGGER_SEVERITY_INFORMATION
			],
			[
				'description' => 'Symptom problem 2',
				'expression' => 'last(/Host for Cause and Symptoms/trap3)=0',
				'priority' => TRIGGER_SEVERITY_WARNING
			]
		]);

		foreach (array_values($problem_itemids) as $itemid) {
			CDataHelper::addItemData($itemid, 0);
		}

		foreach (array_values($symptoms_itemids) as $itemid) {
			CDataHelper::addItemData($itemid, 0);
		}

		// Create events and problems.
		self::$time = time();
		CDBHelper::setTriggerProblem(array_keys(CDataHelper::getIds('description')), TRIGGER_VALUE_TRUE, ['clock' => self::$time]);

		// Manual close is true for the problem: Trigger for widget 1 char.
		DBexecute('UPDATE triggers SET value=1, manual_close=1 WHERE description='.
				zbx_dbstr('Trigger for widget 1 char')
		);

		// Get event ids.
		$eventids = [];
		$event_names = [
			'Cause problem', 'Symptom problem', 'Symptom problem 2', 'Trigger for widget text', 'Trigger for widget 2 unsigned'
		];
		foreach ($event_names as $event_name) {
			$eventids[$event_name] = CDBHelper::getValue('SELECT eventid FROM events WHERE name='.zbx_dbstr($event_name));
		}

		self::$cause_problemid = $eventids['Cause problem'];
		self::$symptom_problemid = $eventids['Symptom problem'];
		self::$symptom_problemid2 = $eventids['Symptom problem 2'];
		self::$eventid_for_widget_text = $eventids['Trigger for widget text'];
		self::$eventid_for_widget_unsigned = $eventids['Trigger for widget 2 unsigned'];

		// Set cause and symptoms.
		DBexecute('UPDATE problem SET cause_eventid='.self::$cause_problemid.' WHERE name IN ('.
				zbx_dbstr('Symptom problem').', '.zbx_dbstr('Symptom problem 2').')'
		);
		DBexecute('INSERT INTO event_symptom (eventid, cause_eventid) VALUES ('.self::$symptom_problemid.', '.
				self::$cause_problemid.')'
		);
		DBexecute('INSERT INTO event_symptom (eventid, cause_eventid) VALUES ('.self::$symptom_problemid2.', '.
				self::$cause_problemid.')'
		);

		// Suppress the problem: 'Trigger for widget text'.
		DBexecute('INSERT INTO event_suppress (event_suppressid, eventid, maintenanceid, suppress_until, userid) VALUES '.
				'(100990, '.self::$eventid_for_widget_text.', NULL, 0, 1)'
		);

		// Acknowledge the problem: 'Trigger for widget 2 unsigned' and get acknowledge time.
		CDataHelper::call('event.acknowledge', [
			'eventids' => self::$eventid_for_widget_unsigned,
			'action' => 6,
			'message' => 'Acknowledged event'
		]);

		$event = CDataHelper::call('event.get', [
			'eventids' => self::$eventid_for_widget_unsigned,
			'selectAcknowledges' => ['clock']
		]);
		self::$acktime = CTestArrayHelper::get($event, '0.acknowledges.0.clock');
	}

	public static function getCheckWidgetTableData() {
		return [
			// #0 Filtered by Host group.
			[
				[
					'fields' => [
						'Name' => 'Group filter',
						'Host groups' => 'Group for Problems Widgets'
					],
					'result' => [
						['Problem • Severity' => 'Trigger for widget 2 unsigned'],
						['Problem • Severity' => 'Trigger for widget 2 log'],
						['Problem • Severity' => 'Trigger for widget 1 char'],
						['Problem • Severity' => 'Trigger for widget 1 float']
					],
					'actions' => [
						'Trigger for widget 2 unsigned' => [
							// Green tick.
							'color-positive' => true,

							// Message bubble.
							'zi-alert-with-content' => [
								[
									'Time' => 'acknowledged',
									'User' => 'Admin (Zabbix Administrator)',
									'Message' => 'Acknowledged event'
								]
							],

							// Actions arrow icon.
							'zi-bullet-right-with-content' => [
								[
									'Time' => 'acknowledged',
									'User/Recipient' => 'Admin (Zabbix Administrator)',
									'Action' => '',
									'Message/Command' => 'Acknowledged event',
									'Status' => '',
									'Info' => ''
								],
								[
									'Time' => 'created',
									'User/Recipient' => '',
									'Action' => '',
									'Message/Command' => '',
									'Status' => '',
									'Info' => ''
								]
							]
						]
					]
				]
			],
			// #1 Filtered by Host group, show suppressed.
			[
				[
					'fields' => [
						'Name' => 'Group, unsuppressed filter',
						'Host groups' => 'Group for Problems Widgets',
						'Show suppressed problems' => true
					],
					'result' => [
						['Problem • Severity' => 'Trigger for widget text'],
						['Problem • Severity' => 'Trigger for widget 2 unsigned'],
						['Problem • Severity' => 'Trigger for widget 2 log'],
						['Problem • Severity' => 'Trigger for widget 1 char'],
						['Problem • Severity' => 'Trigger for widget 1 float']
					],
					'check_suppressed_icon' => [
						'problem' => 'Trigger for widget text',
						'text' => "Suppressed till: Indefinitely\nManually by: Admin (Zabbix Administrator)"
					]
				]
			],
			// #2 Filtered by Host group, show unacknowledged.
			[
				[
					'fields' => [
						'Name' => 'Group, unacknowledged filter',
						'Host groups' => 'Group for Problems Widgets',
						'Acknowledgement status' => 'Unacknowledged'
					],
					'result' => [
						['Problem • Severity' => 'Trigger for widget 2 log'],
						['Problem • Severity' => 'Trigger for widget 1 char'],
						['Problem • Severity' => 'Trigger for widget 1 float']
					]
				]
			],
			// #3 Filtered by Host group, Sort by problem.
			[
				[
					'fields' => [
						'Name' => 'Group, sort by Problem ascending filter',
						'Host groups' => 'Group for Problems Widgets',
						'Sort entries by' => 'Problem (ascending)',
						'Show' => 'Problems'
					],
					'result' => [
						['Problem • Severity' => 'Trigger for widget 1 char'],
						['Problem • Severity' => 'Trigger for widget 1 float'],
						['Problem • Severity' => 'Trigger for widget 2 log'],
						['Problem • Severity' => 'Trigger for widget 2 unsigned']
					],
					'headers' => ['Time', 'Info', 'Host', 'Problem • Severity', 'Duration', 'Update', 'Actions']
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
						['Problem • Severity' => 'Trigger for widget 1 float'],
						['Problem • Severity' => 'Trigger for widget 1 char'],
						['Problem • Severity' => 'Trigger for widget 2 log'],
						['Problem • Severity' => 'Trigger for widget 2 unsigned']
					],
					'headers' => ['Time', 'Recovery time', 'Status', 'Info', 'Host', 'Problem • Severity', 'Duration',
							'Update', 'Actions'
					]
				]
			],
			// #5 Filtered by Host, Problem.
			[
				[
					'fields' => [
						'Name' => 'Group, Problem filter',
						'Hosts' => 'Host for Problems Widgets',
						'Problem' => 'Trigger for widget 2',
						'Show timeline' => true
					],
					'result' => [
						['Problem • Severity' => 'Trigger for widget 2 unsigned'],
						['Problem • Severity' => 'Trigger for widget 2 log']
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
							'Group to check Overview',
							'Group for Cause and Symptoms'
						]
					],
					'result' => [
						['Problem • Severity' => 'Trigger for tag permissions Oracle'],
						['Problem • Severity' => 'Trigger for tag permissions MySQL']
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
						[
							'Time' => true,
							'Recovery time' => '',
							'Status' => 'PROBLEM',
							'Info' => '',
							'Host' => 'Host for Problems Widgets',
							'Problem • Severity' => 'Trigger for widget 2 log',
							'Update' => 'Update'
						],
						[
							'Time' => true,
							'Recovery time' => '',
							'Status' => 'PROBLEM',
							'Info' => '',
							'Host' => 'Host for Problems Widgets',
							'Problem • Severity' => 'Trigger for widget 1 float',
							'Update' => 'Update'
						]
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
								'name' => 'Delta',
								'operator' => 'Exists'
							]
						]
					],
					'result' => [
						[
							'Problem • Severity' => 'Fourth test trigger with tag priority',
							'Tags' => 'Delta: t'

						],
						[
							'Problem • Severity' => 'First test trigger with tag priority',
							'Tags' => 'Alpha: a'
						]
					],
					'headers' => ['Time', '', '', 'Recovery time', 'Status', 'Info', 'Host', 'Problem • Severity',
							'Duration', 'Update', 'Actions', 'Tags'
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
								'name' => 'Eta',
								'operator' => 'Equals',
								'value' => 'e'
							]
						]
					],
					'result' => [
						[
							'Problem • Severity' => 'Fourth test trigger with tag priority',
							'Tags' => 'Eta: eDelta: t'
						],
						[
							'Problem • Severity' => 'Second test trigger with tag priority',
							'Tags' => 'Eta: eBeta: b'
						]
					],
					'check_tag_ellipsis' => [
						'Fourth test trigger with tag priority' => 'Delta: tEta: eGamma: gTheta: t',
						'Second test trigger with tag priority' => 'Beta: bEpsilon: eEta: eZeta: z'
					],
					'headers' => ['Time', '', '', 'Recovery time', 'Status', 'Info', 'Host', 'Problem • Severity',
							'Duration', 'Update', 'Actions', 'Tags'
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
						'Show timeline' => false,
						'Show' => 'History'
					],
					'Tags' => [
						'evaluation' => 'Or',
						'tags' => [
							[
								'name' => 'Theta',
								'operator' => 'Contains',
								'value' => 't'
							],
							[
								'name' => 'Tag4',
								'operator' => 'Exists'
							]
						]
					],
					'result' => [
						[
							'Problem • Severity' => 'Test trigger to check tag filter on problem page',
							'Tags' => 'DatSer: abcser: abcdef'
						],
						[
							'Problem • Severity' => 'Fourth test trigger with tag priority',
							'Tags' => 'The: tDel: tEta: e'
						],
						[
							'Problem • Severity' => 'Third test trigger with tag priority',
							'Tags' => 'The: tAlp: aIot: i'
						]
					],
					'headers' => ['Time', 'Recovery time', 'Status', 'Info', 'Host', 'Problem • Severity', 'Duration',
							'Update', 'Actions', 'Tags'
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
								'name' => 'Theta',
								'operator' => 'Equals',
								'value' => 't'
							],
							[
								'name' => 'Kappa',
								'operator' => 'Does not exist'
							]
						]
					],
					'result' => [
						[
							'Problem • Severity' => 'Fourth test trigger with tag priority',
							'Tags' => 'get'
						]
					],
					'headers' => ['Time', 'Recovery time', 'Status', 'Info', 'Host', 'Problem • Severity', 'Duration',
							'Update', 'Actions', 'Tags'
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
						'Show suppressed problems' => true,
						'Show' => 'Recent problems'
					],
					'result' => [
						[
							'Problem • Severity' => 'Trigger for widget text',
							'Operational data' => '0'
						],
						[
							'Problem • Severity' => 'Trigger for widget 2 unsigned',
							'Operational data' => 'Item value: 0'
						],
						[
							'Problem • Severity' => 'Trigger for widget 2 log',
							'Operational data' => '0'
						],
						[
							'Problem • Severity' => 'Trigger for widget 1 char',
							'Operational data' => '0'
						],
						[
							'Problem • Severity' => 'Trigger for widget 1 float',
							'Operational data' => 'Item value: 0'
						]
					],
					'headers' => ['Time', '', '', 'Recovery time', 'Status', 'Info', 'Host', 'Problem • Severity',
							'Operational data', 'Duration', 'Update', 'Actions'
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
						'Acknowledgement status' => 'Unacknowledged'
					],
					'result' => [
						['Problem • Severity' => 'Trigger for widget 2 log'],
						['Problem • Severity' => 'Trigger for widget 1 char'],
						['Problem • Severity' => 'Trigger for widget 1 float (Item value: 0)']
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
			],
			// #15 Filtered by Host group, show symptoms = false.
			[
				[
					'fields' => [
						'Name' => 'Host group, show symptoms = false',
						'Host groups' => 'Group for Cause and Symptoms',
						'Show symptoms' => false
					],
					'result' => [
						['Problem • Severity' => 'Cause problem'],
						['Problem • Severity' => 'Symptom problem 2'],
						['Problem • Severity' => 'Symptom problem']
					],
					'headers' => ['', '', 'Time', '', '', 'Recovery time', 'Status', 'Info', 'Host', 'Problem • Severity',
							'Duration', 'Update', 'Actions'
					]
				]
			],
			// #16 Filtered by Host group, show symptoms = true.
			[
				[
					'fields' => [
						'Name' => 'Host group, show symptoms = true',
						'Host groups' => 'Group for Cause and Symptoms',
						'Show symptoms' => true
					],
					'result' => [
						['Problem • Severity' => 'Symptom problem 2'],
						['Problem • Severity' => 'Symptom problem'],
						['Problem • Severity' => 'Cause problem'],
						['Problem • Severity' => 'Symptom problem 2'],
						['Problem • Severity' => 'Symptom problem']
					],
					'headers' => ['', '', 'Time', '', '', 'Recovery time', 'Status', 'Info', 'Host', 'Problem • Severity',
							'Duration', 'Update', 'Actions'
					]
				]
			],
			// #17 Filtered so there is no data in result.
			[
				[
					'fields' => [
						'Name' => 'No data',
						'Host groups' => 'Inheritance test'
					],
					'result' => []
				]
			],
			// #18 Filtered by the same include/exclude group.
			[
				[
					'fields' => [
						'Name' => 'Include exclude group',
						'Host groups' => 'Another group to check Overview',
						'Exclude host groups' => 'Another group to check Overview'
					],
					'result' => []
				]
			],
			// #19 Unacknowledged.
			[
				[
					'fields' => [
						'Name' => 'Unacknowledged problems',
						'Severity' => 'Warning',
						'Acknowledgement status' => 'Unacknowledged'
					],
					'result' => [
						['Problem • Severity' => 'Trigger for widget 2 log'],
						['Problem • Severity' => 'Test trigger with tag'],
						['Problem • Severity' => 'Fourth test trigger with tag priority'],
						['Problem • Severity' => 'Third test trigger with tag priority'],
						['Problem • Severity' => 'Second test trigger with tag priority'],
						['Problem • Severity' => 'First test trigger with tag priority'],
						['Problem • Severity' => '1_trigger_Warning']
					]
				]
			],
			// #20 Acknowledged.
			[
				[
					'fields' => [
						'Name' => 'Acknowledged problems',
						'Acknowledgement status' => 'Acknowledged'
					],
					'result' => [
						['Problem • Severity' => '4_trigger_Average'],
						['Problem • Severity' => '3_trigger_Average'],
						['Problem • Severity' => '2_trigger_Information']
					]
				]
			],
			// #21 Acknowledged by me.
			[
				[
					'fields' => [
						'Name' => 'Acknowledged by me problems',
						'Acknowledgement status' => 'Acknowledged',
						'id:acknowledged_by_me' => true
					],
					'result' => [
						['Problem • Severity' => '3_trigger_Average'],
						['Problem • Severity' => '2_trigger_Information']
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCheckWidgetTableData
	 *
	 * @onAfter deleteWidgets
	 */
	public function testDashboardProblemsWidgetDisplay_CheckTable($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$form = $this->openWidgetAndFill($dashboard, 'Problems', $data['fields']);

		if (array_key_exists('Tags', $data)) {
			$form->getField('id:evaltype')->fill(CTestArrayHelper::get($data['Tags'], 'evaluation', 'And/Or'));
			$this->setTags($data['Tags']['tags']);
		}

		$form->submit();

		// Check saved dashboard.
		$form->waitUntilNotVisible();
		$dashboard->save();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Assert widget's table.
		$dashboard->getWidget($data['fields']['Name'])->waitUntilReady();
		$table = $this->query('class:list-table')->asTable()->one();

		// Change time for actual value, because it cannot be used in data provider.
		foreach ($data['result'] as &$row) {
			if (CTestArrayHelper::get($row, 'Time')) {
				$row['Time'] = date('H:i:s', self::$time);
			}
			unset($row);
		}

		// Check clicks on Acknowledge and Actions icons and hints' contents.
		if (CTestArrayHelper::get($data, 'actions')) {
			foreach ($data['actions'] as $problem => $action) {
				$action_cell = $table->findRow('Problem • Severity', $problem)->getColumn('Actions');

				foreach ($action as $class => $hint_rows) {
					$icon = $action_cell->query('xpath:.//*['.CXPathHelper::fromClass($class).']')->one();
					$this->assertTrue($icon->isVisible());

					if ($class !== 'color-positive') {
						// Click on icon and open hint.
						$icon->click();
						$hint = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->asOverlayDialog()
								->waitUntilReady()->one();
						$hint_table = $hint->query('class:list-table')->asTable()->waitUntilVisible()->one();

						// Check rows in hint's table.
						foreach ($hint_table->getRows() as $i => $row) {
							$hint_rows[$i]['Time'] = ($hint_rows[$i]['Time'] === 'acknowledged')
								? date('Y-m-d H:i:s', self::$acktime)
								: date('Y-m-d H:i:s', self::$time);
							$row->assertValues($hint_rows[$i]);
						}

						$hint->close();
					}
				}
			}
		}

		// When there are shown less lines than filtered, table appears unusual and doesn't fit for framework functions.
		if (CTestArrayHelper::get($data['fields'], 'Show lines')) {
			$this->assertEquals(count($data['result']) + 1, $table->getRows()->count());

			// Assert table rows.
			$result = [];
			for ($i = 0; $i < count($data['result']); $i++) {
				$result[] = $table->getRow($i)->getColumn('Problem • Severity')->getText();
			}

			$this->assertEquals($data['result'], $result);

			// Assert table stats.
			$this->assertEquals($data['stats'], $table->getRow(count($data['result']))->getText());
		}
		elseif (empty($data['result'])) {
			$this->assertTableData();
		}
		else {
			$this->assertTableHasData($data['result']);
		}

		// Assert table headers depending on widget settings.
		$headers = (CTestArrayHelper::get($data, 'headers', ['Time', '', '', 'Recovery time', 'Status', 'Info',
				'Host', 'Problem • Severity', 'Duration', 'Update', 'Actions']
		));
		$this->assertEquals($headers, $table->getHeadersText());

		if (CTestArrayHelper::get($data['fields'], 'Show timeline')) {
			$this->assertTrue($table->query('class:timeline-td')->exists());
		}

		if (CTestArrayHelper::get($data, 'check_tag_ellipsis')) {
			foreach ($data['check_tag_ellipsis'] as $problem => $ellipsis_text) {
				$table->findRow('Problem • Severity', $problem)->getColumn('Tags')->query('class:zi-more')
						->waitUntilClickable()->one()->click();
				$hint = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->asOverlayDialog()
						->waitUntilVisible()->one();
				$this->assertEquals($ellipsis_text, $hint->getText());
				$hint->close();
			}
		}

		// Check eye icon for suppressed problem.
		if (CTestArrayHelper::get($data, 'check_suppressed_icon')) {
			$table->findRow('Problem • Severity', $data['check_suppressed_icon']['problem'])->getColumn('Info')
					->query('class:zi-eye-off')->waitUntilClickable()->one()->click();
			$hint = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->asOverlayDialog()
					->waitUntilVisible()->one();
			$this->assertEquals($data['check_suppressed_icon']['text'], $hint->getText());
			$hint->close();
		}
	}
}
