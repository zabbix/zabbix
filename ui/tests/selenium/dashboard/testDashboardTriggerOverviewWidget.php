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
require_once dirname(__FILE__).'/../traits/TagTrait.php';
require_once dirname(__FILE__).'/../traits/TableTrait.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

/**
 * @backup widget, profiles, triggers, problem, config
 *
 * @onBefore prepareData
 */
class testDashboardTriggerOverviewWidget extends CWebTest {

	use TagTrait;
	use TableTrait;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	private static $dashboardid;
	private static $create_page = 'Page for creation';
	private static $update_widget = 'Trigger overview for reference';
	private static $delete_widget = 'Trigger overview for delete';
	private static $resolved_trigger = '1_trigger_Average';
	private static $dependency_trigger = 'Trigger disabled with tags';
	private static $icon_host = 'Host for triggers filtering';

	private static $background_classes = [
		'1_trigger_Average' => 'normal-bg cursor-pointer blink',
		'1_trigger_Disaster' => 'disaster-bg',
		'1_trigger_High' => 'high-bg',
		'1_trigger_Not_classified' => 'na-bg',
		'1_trigger_Warning' => 'warning-bg',
		'2_trigger_Information' => 'info-bg',
		'3_trigger_Average' => 'average-bg',
		'Trigger_for_suppression' => 'average-bg',
		'4_trigger_Average' => 'average-bg',
		'Inheritance trigger with tags' => 'average-bg',
		'3_trigger_Disaster' => 'normal-bg',
		'Dependent trigger ONE' => 'normal-bg',
		'Discovered trigger one' => 'normal-bg',
		'Trigger disabled with tags' => 'normal-bg'
	];

	private static $trigger_icons = [
		'2_trigger_Information' => 'icon-ackn',
		'3_trigger_Average' => 'icon-ackn',
		'4_trigger_Average' => 'icon-ackn',
		'Dependent trigger ONE' => 'icon-depend-down',
		'Inheritance trigger with tags' => 'icon-depend-down',
		'Trigger disabled with tags' => 'icon-depend-up'
	];

	/**
	 * SQL query to get widget and widget_field tables to compare hash values, but without widget_fieldid
	 * because it can change.
	 */
	private $sql = 'SELECT wf.widgetid, wf.type, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
			' wf.value_itemid, wf.value_graphid, wf.value_sysmapid, w.widgetid, w.dashboard_pageid, w.type, w.name, w.x, w.y,'.
			' w.width, w.height'.
			' FROM widget_field wf'.
			' INNER JOIN widget w'.
			' ON w.widgetid=wf.widgetid ORDER BY wf.widgetid, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
			' wf.value_itemid, wf.value_graphid';

	/**
	 * Function creates dashboards with widgets and adjusts trigger and problems config for the test.
	 */
	public static function prepareData() {
		$response = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for Trigger overview widgets',
				'private' => 0,
				'auto_start' => 0,
				'pages' => [
					[
						'name' => 'Page with widgets',
						'widgets' => [
							[
								'type' => 'trigover',
								'name' => self::$update_widget,
								'width' => 24,
								'height' => 4
							],
							[
								'type' => 'trigover',
								'name' => self::$delete_widget,
								'x' => 0,
								'y' => 4,
								'width' => 24,
								'height' => 4,
								'fields' => [
									[
										'type' => 0,
										'name' => 'show_suppressed',
										'value' => 1
									]
								]
							]
						]
					],
					[
						'name' => self::$create_page,
						'widgets' => []
					]
				]
			]
		]);

		self::$dashboardid = $response['dashboardids'][0];
		$timestamp = time();

		// Resolve one of existing problems to create a recent problem.
		$triggerids = CDBHelper::getColumn('SELECT triggerid FROM triggers WHERE description IN ('.
				zbx_dbstr(self::$resolved_trigger).', '.zbx_dbstr(self::$dependency_trigger).')', 'triggerid'
		);
		DBexecute('UPDATE triggers SET value=0 WHERE triggerid='.zbx_dbstr($triggerids[0]));
		DBexecute('UPDATE triggers SET lastchange='.zbx_dbstr($timestamp).' WHERE triggerid='.zbx_dbstr($triggerids[0]));
		DBexecute('UPDATE problem SET r_eventid=9001 WHERE objectid='.zbx_dbstr($triggerids[0]));
		DBexecute('UPDATE problem SET r_clock='.zbx_dbstr($timestamp).' WHERE objectid='.zbx_dbstr($triggerids[0]));

		// Change the resolved triggers blinking period as the default value is too small for this test.
		CDataHelper::call('settings.update', ['blink_period' => '5m']);

		// Enable the trigger that other triggers depend on.
		CDataHelper::call('trigger.update', [['triggerid' => $triggerids[1], 'status' => 0]]);
	}

	public function testDashboardTriggerOverviewWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$form = CDashboardElement::find()->one()->edit()->addWidget()->asForm();
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Trigger overview')]);
		$this->assertEquals(['Type', 'Name', 'Refresh interval', 'Show', 'Host groups', 'Hosts', 'Tags',
				'Show suppressed problems', 'Hosts location'], $form->getLabels()->asText()
		);

		$default_values = [
			'Show header' => true,
			'Name' => '',
			'Refresh interval' => 'Default (1 minute)',
			'Show' => 'Recent problems',
			'Host groups' => '',
			'Hosts' => '',
			'id:evaltype' => 'And/Or',
			'id:tags_0_tag' => '',
			'id:tags_0_value' => '',
			'id:tags_0_operator' => 'Contains',
			'Show suppressed problems' => false,
			'Hosts location' => 'Left'
		];

		$form->checkValue($default_values);

		// Check field lengths and placeholders.
		foreach (['Name' => 'default', 'id:tags_0_tag' => 'tag', 'id:tags_0_value' => 'value'] as $field => $placeholder) {
			$field = $form->getField($field);
			$this->assertEquals(255, $field->getAttribute('maxlength'));
			$this->assertEquals($placeholder, $field->getAttribute('placeholder'));
		}

		// Check operators dropdown options.
		$this->assertEquals(['Exists', 'Equals', 'Contains', 'Does not exist', 'Does not equal',
				'Does not contain'], $form->getField('id:tags_0_operator')->asDropdown()->getOptions()->asText()
		);

		// Check possible values of radio buttons.
		$radio_buttons = [
			'Show' => ['Recent problems', 'Problems', 'Any'],
			'Tags' => ['And/Or', 'Or'],
			'Hosts location' => ['Left', 'Top']
		];

		foreach ($radio_buttons as $radio_button => $values) {
			$radio_element = $form->getField($radio_button);
			$this->assertEquals($values, $radio_element->getLabels()->asText());
		}

		// Check buttons in Tags table.
		$this->assertEquals(2, $form->query('id:tags_table_tags')->one()->query('button', ['Add', 'Remove'])->all()
				->filter(new CElementFilter(CElementFilter::CLICKABLE))->count()
		);

		// Close the widget configuration form dialog to avoid possible alerts in following tests.
		COverlayDialogElement::find()->one()->close();
	}

	public function getWidgetData() {
		return [
			// Create a widget with default values including default name.
			[
				[
					'expected' => [
						'1_Host_to_check_Monitoring_Overview' => [
							'1_trigger_Average',
							'1_trigger_Disaster',
							'1_trigger_High',
							'1_trigger_Not_classified',
							'1_trigger_Warning',
							'2_trigger_Information'
						],
						'3_Host_to_check_Monitoring_Overview' => [
							'3_trigger_Average'
						],
						'4_Host_to_check_Monitoring_Overview' => [
							'4_trigger_Average'
						],
						'Host for triggers filtering' => [
							'Inheritance trigger with tags'
						]
					]
				]
			],
			// Create a widget that displays only problems.
			[
				[
					'fields' => [
						'Name' => 'Show problems',
						'Show' => 'Problems'
					],
					'expected' => [
						'1_Host_to_check_Monitoring_Overview' => [
							'1_trigger_Disaster',
							'1_trigger_High',
							'1_trigger_Not_classified',
							'1_trigger_Warning',
							'2_trigger_Information'
						],
						'3_Host_to_check_Monitoring_Overview' => [
							'3_trigger_Average'
						],
						'4_Host_to_check_Monitoring_Overview' => [
							'4_trigger_Average'
						],
						'Host for triggers filtering' => [
							'Inheritance trigger with tags'
						]
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Show all triggers for specific host',
						'Show' => 'Any',
						'Hosts' => ['3_Host_to_check_Monitoring_Overview']
					],
					'expected' => [
						'3_Host_to_check_Monitoring_Overview' => [
							'3_trigger_Average',
							'3_trigger_Disaster'
						]
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Show problems for specific hostgroup',
						'Show' => 'Problems',
						'Host groups' => ['Group to check Overview']
					],
					'expected' => [
						'1_Host_to_check_Monitoring_Overview' => [
							'1_trigger_Disaster',
							'1_trigger_High',
							'1_trigger_Not_classified',
							'1_trigger_Warning',
							'2_trigger_Information'
						],
						'3_Host_to_check_Monitoring_Overview' => [
							'3_trigger_Average'
						]
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Show problems for multiple hostgroups',
						'Show' => 'Problems',
						'Host groups' => ['Group to check Overview', 'Another group to check Overview']
					],
					'expected' => [
						'1_Host_to_check_Monitoring_Overview' => [
							'1_trigger_Disaster',
							'1_trigger_High',
							'1_trigger_Not_classified',
							'1_trigger_Warning',
							'2_trigger_Information'
						],
						'3_Host_to_check_Monitoring_Overview' => [
							'3_trigger_Average'
						],
						'4_Host_to_check_Monitoring_Overview' => [
							'4_trigger_Average'
						]
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Show recent problems for multiple hosts',
						'Show' => 'Any',
						'Hosts' => ['3_Host_to_check_Monitoring_Overview', '4_Host_to_check_Monitoring_Overview']
					],
					'expected' => [
						'3_Host_to_check_Monitoring_Overview' => [
							'3_trigger_Average',
							'3_trigger_Disaster'
						],
						'4_Host_to_check_Monitoring_Overview' => [
							'4_trigger_Average'
						]
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Hostgroup without triggers',
						'Host groups' => ['Dynamic widgets HG1 (H1 and H2)']
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Host without triggers',
						'Hosts' => ['Dynamic widgets H1']
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Combination of non-related Host and Hostgroup',
						'Host groups' => ['Group to check Overview'],
						'Hosts' => ['4_Host_to_check_Monitoring_Overview']
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Show suppressed problems + hosts on top',
						'Show suppressed problems' => true,
						'Hosts location' => 'Top'
					],
					'expected' => [
						'1_Host_to_check_Monitoring_Overview' => [
							'1_trigger_Average',
							'1_trigger_Disaster',
							'1_trigger_High',
							'1_trigger_Not_classified',
							'1_trigger_Warning',
							'2_trigger_Information'
						],
						'3_Host_to_check_Monitoring_Overview' => [
							'3_trigger_Average'
						],
						'4_Host_to_check_Monitoring_Overview' => [
							'4_trigger_Average'
						],
						'Host for suppression' => [
							'Trigger_for_suppression'
						],
						'Host for triggers filtering' => [
							'Inheritance trigger with tags'
						]
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Filter triggers by tag with default operator'
					],
					'tags' => [
						['name' => 'server', 'operator' => 'Contains', 'value' => 'sel']
					],
					'expected' => [
						'Host for triggers filtering' => [
							'Inheritance trigger with tags'
						]
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Filter triggers by 2 tags with Or operator',
						'Tags' => 'Or'
					],
					'tags' => [
						['name' => 'Street', 'operator' => 'Exists'],
						['name' => 'webhook', 'operator' => 'Equals', 'value' => '1']
					],
					'expected' => [
						'1_Host_to_check_Monitoring_Overview' => [
							'1_trigger_High'
						],
						'Host for triggers filtering' => [
							'Inheritance trigger with tags'
						]
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Does not exists and Does not equal tag operators',
						'Show' => 'Problems'
					],
					'tags' => [
						['name' => 'Street', 'operator' => 'Does not exist'],
						['name' => 'webhook', 'operator' => 'Does not equal', 'value' => '1']
					],
					'expected' => [
						'1_Host_to_check_Monitoring_Overview' => [
							'1_trigger_Disaster',
							'1_trigger_Not_classified',
							'1_trigger_Warning',
							'2_trigger_Information'
						],
						'3_Host_to_check_Monitoring_Overview' => [
							'3_trigger_Average'
						],
						'4_Host_to_check_Monitoring_Overview' => [
							'4_trigger_Average'
						]
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Виджет с tag + 良い１日を un žšī!@#$%^&*()_ vardā'
					],
					'tags' => [
						['name' => 'Street', 'operator' => 'Does not contain', 'value' => 'elza']
					],
					'expected' => [
						'1_Host_to_check_Monitoring_Overview' => [
							'1_trigger_Average',
							'1_trigger_Disaster',
							'1_trigger_High',
							'1_trigger_Not_classified',
							'1_trigger_Warning',
							'2_trigger_Information'
						],
						'3_Host_to_check_Monitoring_Overview' => [
							'3_trigger_Average'
						],
						'4_Host_to_check_Monitoring_Overview' => [
							'4_trigger_Average'
						]
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'No result - filter triggers by 2 tags with And operator'
					],
					'tags' => [
						['name' => 'server', 'operator' => 'Contains', 'value' => 'sel'],
						['name' => 'webhook', 'operator' => 'Equals', 'value' => '1']
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Filter by 2 tags without value',
						'Tags' => 'Or'
					],
					'tags' => [
						['name' => 'server', 'operator' => 'Contains', 'value' => ''],
						['name' => 'webhook', 'operator' => 'Equals', 'value' => '']
					],
					'expected' => [
						'Host for triggers filtering' => [
							'Inheritance trigger with tags'
						]
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Only tag specified only by value'
					],
					'tags' => [
						['name' => '', 'operator' => 'Contains', 'value' => '1']
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Check triggers with dependency icons',
						'Show' => 'Any',
						'Hosts' => [self::$icon_host]
					],
					'expected' => [
						'Host for triggers filtering' => [
							'Dependent trigger ONE',
							'Discovered trigger one',
							'Inheritance trigger with tags',
							'Trigger disabled with tags'
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getWidgetData
	 */
	public function testDashboardTriggerOverviewWidget_Create($data) {
		$this->checkWidgetAction($data);
	}

	/**
	 * @dataProvider getWidgetData
	 */
	public function testDashboardTriggerOverviewWidget_Update($data) {
		$this->checkWidgetAction($data, false);
	}

	public function testDashboardTriggerOverviewWidget_SimpleUpdate() {
		$old_hash = CDBHelper::getHash($this->sql);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$dashboard->edit();

		$form = $dashboard->getWidget(self::$update_widget)->edit();
		$form->submit();
		COverlayDialogElement::ensureNotPresent();

		$widget = $dashboard->getWidget(self::$update_widget);
		$widget->waitUntilReady();
		$dashboard->save();

		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
	}

	public function getCancelActionsData() {
		return [
			// Cancel update widget.
			[
				[
					'update' => true,
					'save_widget' => true,
					'save_dashboard' => false
				]
			],
			[
				[
					'update' => true,
					'save_widget' => false,
					'save_dashboard' => true
				]
			],
			// Cancel create widget.
			[
				[
					'save_widget' => true,
					'save_dashboard' => false
				]
			],
			[
				[
					'save_widget' => false,
					'save_dashboard' => true
				]
			]
		];
	}

	/**
	 * @dataProvider getCancelActionsData
	 */
	public function testDashboardTriggerOverviewWidget_Cancel($data) {
		$old_hash = CDBHelper::getHash($this->sql);
		$new_name = 'Widget to be cancelled';

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one()->edit();

		// Start updating or creating a widget.
		if (CTestArrayHelper::get($data, 'update', false)) {
			$form = $dashboard->getWidget(self::$update_widget)->edit();
		}
		else {
			$form = $dashboard->addWidget()->asForm();

			if ($form->getField('Type')->getValue() !== 'Trigger overview') {
				$form->getField('Type')->fill('Trigger overview');
				$form->invalidate();
			}
		}

		$form->fill([
			'Name' => $new_name,
			'Refresh interval' => '10 minutes',
			'Show' => 'Any',
			'Host groups' => ['Another group to check Overview'],
			'Hosts' => ['4_Host_to_check_Monitoring_Overview'],
			'Tags' => 'Or',
			'Show suppressed problems' => 'true',
			'Hosts location' => 'Top'
		]);

		$this->setTagSelector('id:tags_table_tags');
		$this->setTags([['name' => 'webhook', 'operator' => 'Equals', 'value' => '1']]);

		// Save or cancel widget.
		if (CTestArrayHelper::get($data, 'save_widget', false)) {
			$form->submit();

			// Check that changes took place on the unsaved dashboard (widget got renamed).
			$this->assertTrue($dashboard->getWidget($new_name)->isValid());
		}
		else {
			$dialog = COverlayDialogElement::find()->one();
			$dialog->query('button:Cancel')->one()->click();
			$dialog->ensureNotPresent();

			if (CTestArrayHelper::get($data, 'update', false)) {
				foreach ([self::$update_widget => true, $new_name => false] as $name => $valid) {
					$this->assertTrue($dashboard->getWidget($name, $valid)->isValid($valid));
				}
			}
		}

		// Save or cancel dashboard update.
		if (CTestArrayHelper::get($data, 'save_dashboard', false)) {
			$dashboard->save();
		}
		else {
			$dashboard->cancelEditing();
		}
		// Confirm that no changes were made to the widget.
		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
	}

	public function testDashboardSlaReportWidget_Delete() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one()->edit();
		$widget = $dashboard->getWidget(self::$delete_widget);

		$dashboard->deleteWidget(self::$delete_widget);
		$widget->waitUntilNotPresent();
		$dashboard->save();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Confirm that widget is not present on dashboard.
		$this->assertFalse($dashboard->getWidget(self::$delete_widget, false)->isValid());
		$widget_sql = 'SELECT null FROM widget_field wf'.
				' LEFT JOIN widget w'.
					' ON w.widgetid=wf.widgetid'.
					' WHERE w.name='.zbx_dbstr(self::$delete_widget);

		$this->assertEquals(0, CDBHelper::getCount($widget_sql));
	}

	/**
	 * Function checks the content of the Dependent and Depends on popups.
	 * Icons with Acknowledge popup menus are not checked as they are covered in testPageTriggerUrl test.
	 */
	public function testDashboardTriggerOverviewWidget_CheckDependencyPopups() {
		$popup_content = [
			'Dependent trigger ONE' => [
				'Depends on' => ['Trigger disabled with tags']
			],
			'Inheritance trigger with tags' => [
				'Depends on' => ['Trigger disabled with tags']
			],
			'Trigger disabled with tags' => [
				'Dependent' => ['Inheritance trigger with tags', 'Dependent trigger ONE']
			]
		];

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one()->edit();

		$form = $dashboard->getWidget(self::$update_widget)->edit();
		$form->fill(['Show' => 'Any', 'Hosts' => [self::$icon_host]]);
		$form->submit();

		// Wait for the widget to be ready and save dashboard (the wait is performed within the getWidget() method).
		$dashboard->getWidget(self::$update_widget);
		$dashboard->save();

		// Get the table row with all triggers (since all of them belong to a single host).
		$row = $dashboard->getWidget(self::$update_widget)->getContent()->asTable()->findRow('Hosts', self::$icon_host);

		foreach ($popup_content as $trigger => $dependency) {
			// Locate hint and check table headers in hint.
			$hint_table = $row->getColumn($trigger)->query('class:hint-box')->one()->asTable();
			$this->assertEquals(array_keys($dependency), $hint_table->getHeadersText());

			// Gather data from rows and compare result with reference.
			$hint_rows = $hint_table->getRows()->asText();

			$this->assertEquals(array_values($dependency), [$hint_rows]);
		}
	}

	/**
	 * Create or update a Trigger overview widget and check the result.
	 *
	 * @param array		$data		widget related data from data provider
	 * @param boolean	$create		flag that specifies whether a create action is performed
	 */
	public function checkWidgetAction($data, $create = true) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$dashboard->edit();

		if ($create) {
			$dashboard->selectPage(self::$create_page);
			$form = $dashboard->addWidget()->asForm();

			// Set type to Trigger overview in case if this field has a different value.
			if ($form->getField('Type')->getValue() !== 'Trigger overview') {
				$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Trigger overview')]);
			}
		}
		else {
			$form = $dashboard->getWidget(self::$update_widget)->edit();

			// Values from the previous cases should be cleaned-up in case of update scenario before filling-in data.
			$this->cleanupFormBeforeFill($form, CTestArrayHelper::get($data, 'fields', []));
		}

		// Fill form in case if values that are different from widget default configuration should be filled.
		if (array_key_exists('fields', $data)) {
			$form->fill($data['fields']);
		}

		if (CTestArrayHelper::get($data,'tags', false)) {
			$this->setTagSelector('id:tags_table_tags');
			$this->setTags($data['tags']);
		}

		$form->submit();
		COverlayDialogElement::ensureNotPresent();

		$widget_name = (array_key_exists('fields', $data)) ? $data['fields']['Name'] : 'Trigger overview';
		$widget = $dashboard->getWidget($widget_name);
		$dashboard->save();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		if ($create) {
			$dashboard->selectPage(self::$create_page);
		}
		else {
			self::$update_widget = (array_key_exists('fields', $data)) ? $data['fields']['Name'] : 'Trigger overview';
		}

		$table = $widget->getContent()->asTable();

		if (CTestArrayHelper::get($data, 'fields.Hosts location') === 'Top') {
			$expected_headers = ['Triggers'];
			$expected_rows = [];
		}
		else {
			$expected_headers = ['Hosts'];
			$expected_rows = array_keys(CTestArrayHelper::get($data, 'expected', []));
		}

		// Check empty result widget and proceed to next case.
		if (!array_key_exists('expected', $data)) {
			$this->assertEquals($expected_headers, $table->getHeadersText());
			$this->assertTableData(null, "xpath://h4[text()=".CXPathHelper::escapeQuotes($data['fields']['Name']).
					"]/../..//table"
			);

			return;
		}

		// Check widget content based on the alignment chosen in Hosts location field.
		foreach ($data['expected'] as $host => $triggers) {
			if (CTestArrayHelper::get($data, 'fields.Hosts location') === 'Top') {
				$expected_headers[] = $host;
				foreach ($triggers as $trigger) {
					$expected_rows[] = $trigger;
					$cell = $table->findRow('Triggers', $trigger)->getColumn($host);
					$this->checkTriggerCell($cell, $trigger);
				}
			}
			else {
				$row = $table->findRow('Hosts', $host);

				foreach ($triggers as $trigger) {
					$expected_headers[] = $trigger;
					$cell = $row->getColumn($trigger);
					$this->checkTriggerCell($cell, $trigger);
				}
			}
		}

		// Rows are sorted alphabetically in widget, so the same should apply to the reference array.
		$expected_rows = array_values($expected_rows);
		sort($expected_rows);

		$this->assertEquals($expected_headers, $table->getHeadersText());
		$this->assertTableDataColumn($expected_rows, $expected_headers[0], 'xpath://h4[text()='.
				CXPathHelper::escapeQuotes($widget_name).']/../..//table[@class="list-table"]'
		);
	}

	/**
	 * Remove the previously entered values from widget configuration form.
	 *
	 * @param CFormElement $form	widget configuration form element
	 */
	private function cleanupFormBeforeFill($form) {
		$default_values = [
			'Name' => '',
			'Show' => 'Recent problems',
			'Host groups' => '',
			'Hosts' => '',
			'Tags' => 'And/Or',
			'Show suppressed problems' => false,
			'Hosts location' => 'Left'
		];

		foreach ($default_values as $field_name => $value) {
			$field = $form->getField($field_name);

			if ($field->getValue() !== $value) {
				if (in_array($field, ['Host groups', 'Hosts'])) {
					$field->clear();
				}
				else {
					$field->fill($value);
				}
			}
		}

		// Remove tags left from previous case and add a blank row for new data.
		if ($form->getField('id:tags_0_tag')->getValue() !== '' || $form->getField('id:tags_0_value')->getValue() !== '') {
			$form->query('button:Remove')->all()->click();
			$form->query('button:Add')->one()->click();
		}
	}

	/**
	 * Check the severity and the icon displayed in the table cell under attention.
	 *
	 * @param CElement	$cell		table cell that represents the trigger to be checked
	 * @param string	$trigger	the name of the trigger that is represented by the trigger cell
	 */
	private function checkTriggerCell ($cell, $trigger) {
		// Check the colour of the background.
		$this->assertStringStartsWith(self::$background_classes[$trigger], $cell->getAttribute('class'));

		// Check trigger icon if such should exist.
		if (in_array($trigger, self::$trigger_icons)) {
			$element = (self::$trigger_icons[$trigger] === 'icon-ackn') ? 'span' : 'a';
			$icon = $cell->query('xpath:.//'.$element)->one();
			$this->assertTrue($icon->isValid());
			$this->assertStringStartsWith(self::$trigger_icons[$trigger], $cell->getAttribute('class'));
		}
	}
}
