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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CTagBehavior.php';

/**
 * @backup dashboard
 *
 * @onBefore prepareData
 *
 * @dataSource UserPermissions
 */
class testDashboardTopTriggersWidget extends CWebTest {

	/**
	 * Attach MessageBehavior, TableBehavior and TagBehavior to the test.
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class,
			[
				'class' => CTagBehavior::class,
				'tag_selector' => 'id:tags_table_tags'
			]
		];
	}

	protected static $dashboardid;
	protected static $dashboard_create;
	protected static $dashboard_data;
	protected static $groupids;
	protected static $update_widget = 'Update Top triggers widget';
	const DEFAULT_WIDGET = 'Default Top triggers widget';
	const DELETE_WIDGET = 'Widget for delete';
	const DATA_WIDGET = 'Widget for data check';

	/**
	 * SQL query to get widget and widget_field tables to compare hash values, but without widget_fieldid
	 * because it can change.
	 */
	const SQL = 'SELECT wf.widgetid, wf.type, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
			' wf.value_itemid, wf.value_graphid, wf.value_sysmapid, w.widgetid, w.dashboard_pageid, w.type, w.name, w.x, w.y,'.
			' w.width, w.height'.
			' FROM widget_field wf'.
			' INNER JOIN widget w'.
			' ON w.widgetid=wf.widgetid ORDER BY wf.widgetid, wf.name, wf.value_int, wf.value_str, wf.value_groupid,'.
			' wf.value_itemid, wf.value_graphid';

	public static function prepareData() {
		// Create hostgroups for hosts.
		CDataHelper::call('hostgroup.create', [
			['name' => 'First Group for TOP triggers check'],
			['name' => 'Second Group for TOP triggers check']
		]);
		self::$groupids = CDataHelper::getIds('name');

		// Create hosts and trapper items for top triggers data test.
		CDataHelper::createHosts([
			[
				'host' => 'Host with top triggers trapper',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.9.1',
						'dns' => '',
						'port' => '10077'
					]
				],
				'groups' => [
					'groupid' => self::$groupids['First Group for TOP triggers check']
				],
				'items' => [
					[
						'name' => 'Top triggers trapper',
						'key_' => 'toptrap',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			],
			[
				'host' => 'Host with top triggers trapper2',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.9.2',
						'dns' => '',
						'port' => '10078'
					]
				],
				'groups' => [
					'groupid' => self::$groupids['Second Group for TOP triggers check']
				],
				'items' => [
					[
						'name' => 'Top triggers trapper2',
						'key_' => 'toptrap',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			],
			[
				'host' => 'TOP triggers',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.9.3',
						'dns' => '',
						'port' => '10079'
					]
				],
				'groups' => [
					'groupid' => self::$groupids['First Group for TOP triggers check']
				],
				'items' => [
					[
						'name' => 'Top triggers trapper3',
						'key_' => 'toptrap',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			]
		]);

		CDataHelper::call('trigger.create', [
			[
				'description' => 'Problem Disaster',
				'expression' => 'last(/Host with top triggers trapper/toptrap)=5',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_DISASTER
			],
			[
				'description' => 'Problem High',
				'expression' => 'last(/Host with top triggers trapper/toptrap)=4',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_HIGH
			],
			[
				'description' => 'Severity status: High',
				'expression' => 'last(/Host with top triggers trapper2/toptrap)=4',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_HIGH
			],
			[
				'description' => 'Problem Average',
				'expression' => 'last(/Host with top triggers trapper/toptrap)=3',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_AVERAGE
			],
			[
				'description' => 'Problem Warning',
				'expression' => 'last(/Host with top triggers trapper/toptrap)=2',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_WARNING
			],
			[
				'description' => 'Severity status: Warning⚠️',
				'expression' => 'last(/Host with top triggers trapper2/toptrap)=2',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_WARNING
			],
			[
				'description' => 'Issue: Warning',
				'expression' => 'last(/TOP triggers/toptrap)=2',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_WARNING
			],
			[
				'description' => 'Problem with tag',
				'expression' => 'last(/TOP triggers/toptrap)=2',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_WARNING,
				'tags' => [
					[
						'tag' => 'test1',
						'value' => 'tag1'
					]
				]
			],
			[
				'description' => 'Problem Information',
				'expression' => 'last(/Host with top triggers trapper/toptrap)=1',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_INFORMATION
			],
			[
				'description' => 'Trigger from {HOST.HOST}',
				'expression' => 'last(/Host with top triggers trapper2/toptrap)=1',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_INFORMATION
			],
			[
				'description' => 'Problem Not classified',
				'expression' => 'last(/Host with top triggers trapper/toptrap)=0',
				'type' => 1,
				'priority' => TRIGGER_SEVERITY_NOT_CLASSIFIED
			]
		]);

		$response = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for Top triggers widget test',
				'pages' => [
					[
						'name' => 'Page with default widgets',
						'widgets' => [
							[
								'type' => 'toptriggers',
								'name' => self::DEFAULT_WIDGET,
								'x' => 0,
								'y' => 0,
								'width' => 36,
								'height' => 3
							],
							[
								'type' => 'toptriggers',
								'name' => self::DELETE_WIDGET,
								'x' => 36,
								'y' => 0,
								'width' => 36,
								'height' => 3
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for Top triggers widget create/update test',
				'pages' => [
					[
						'name' => 'Page with created/updated widgets',
						'widgets' => [
							[
								'type' => 'toptriggers',
								'name' => self::$update_widget,
								'x' => 0,
								'y' => 0,
								'width' => 36,
								'height' => 3
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for checking top triggers data',
				'pages' => [
					[
						'name' => 'Page with data widget',
						'widgets' => [
							[
								'type' => 'toptriggers',
								'name' => self::DATA_WIDGET,
								'x' => 0,
								'y' => 0,
								'width' => 36,
								'height' => 3
							]
						]
					]
				]
			]
		]);
		self::$dashboardid = $response['dashboardids'][0];
		self::$dashboard_create = $response['dashboardids'][1];
		self::$dashboard_data = $response['dashboardids'][2];
	}

	public function testDashboardTopTriggersWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dialog = $dashboard->edit()->addWidget();
		$this->assertEquals('Add widget', $dialog->getTitle());
		$form = $dialog->asForm();
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Top triggers')]);

		// Check default state.
		$default_state = [
			'Type' => 'Top triggers',
			'Name' => '',
			'Show header' => true,
			'Refresh interval' => 'Default (No refresh)',
			'Host groups' => '',
			'Hosts' => '',
			'Problem' => '',
			'id:severities_0' => false,
			'id:severities_1' => false,
			'id:severities_2' => false,
			'id:severities_3' => false,
			'id:severities_4' => false,
			'id:severities_5' => false,
			'Problem tags' => 'And/Or',
			'id:tags_0_tag' => '',
			'id:tags_0_operator' => 'Contains',
			'id:tags_0_value' => '',
			'Trigger limit' => 10
		];

		$form->checkValue($default_state);
		$this->assertEquals(['Trigger limit'], $form->getRequiredLabels());

		// Check attributes of input elements.
		$inputs = [
			'Name' => [
				'maxlength' => 255,
				'placeholder' => 'default'
			],
			'id:groupids__ms' => [
				'placeholder' => 'type here to search'
			],
			'id:hostids__ms' => [
				'placeholder' => 'type here to search'
			],
			'Problem' => [
				'maxlength' => 2048
			],
			'id:tags_0_tag' => [
				'maxlength' => 255,
				'placeholder' => 'tag'
			],
			'id:tags_0_value' => [
				'maxlength' => 255,
				'placeholder' => 'value'
			],
			'Trigger limit' => [
				'maxlength' => 4
			]
		];
		foreach ($inputs as $field => $attributes) {
			foreach ($attributes as $attribute => $value) {
				$this->assertEquals($value, $form->getField($field)->getAttribute($attribute));
			}
		}

		$this->assertEquals(['Default (No refresh)', 'No refresh', '10 seconds', '30 seconds', '1 minute',
				'2 minutes', '10 minutes', '15 minutes'], $form->getField('Refresh interval')->getOptions()->asText()
		);

		// Check radio buttons and checkboxes.
		$selection_elements = [
			'Problem tags' => ['And/Or', 'Or'],
			'Severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster']
		];
		foreach ($selection_elements as $name => $labels) {
			$this->assertEquals($labels, $form->getField($name)->getLabels()->asText());
		}

		// Check tag operators and tag table buttons.
		$this->assertEquals(['Exists', 'Equals', 'Contains', 'Does not exist', 'Does not equal',
				'Does not contain'], $form->getField('id:tags_0_operator')->asDropdown()->getOptions()->asText()
		);
		$this->assertEquals(2, $form->query('id:tags_table_tags')->one()->query('button', ['Add', 'Remove'])->all()
				->filter((CElementFilter::CLICKABLE))->count()
		);

		// Check if footer buttons present and clickable.
		$this->assertEquals(['Add', 'Cancel'], $dialog->getFooter()->query('button')->all()
				->filter(CElementFilter::CLICKABLE)->asText()
		);

		$dialog->close();
	}

	public static function getWidgetData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Trigger limit' => ''
					],
					'error' => 'Invalid parameter "Trigger limit": value must be one of 1-1000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Trigger limit' => ' '
					],
					'error' => 'Invalid parameter "Trigger limit": value must be one of 1-1000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Trigger limit' => '0'
					],
					'error' => 'Invalid parameter "Trigger limit": value must be one of 1-1000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Trigger limit' => '1001'
					],
					'error' => 'Invalid parameter "Trigger limit": value must be one of 1-1000.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Trigger limit' => 'x'
					],
					'error' => 'Invalid parameter "Trigger limit": value must be one of 1-1000.'
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => []
				]
			],
			// Widget name "Top triggers", if no name is given.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show header' => false,
						'Refresh interval' => 'No refresh'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host groups' => 'Zabbix servers',
						'Refresh interval' => '10 seconds'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host groups' => [
							'Zabbix servers',
							'First Group for TOP triggers check'
						],
						'Refresh interval' => '30 seconds'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Hosts' => 'ЗАББИКС Сервер',
						'Refresh interval' => '1 minute'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Hosts' => [
							'ЗАББИКС Сервер',
							'Host with top triggers trapper'
						],
						'Refresh interval' => '2 minutes'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Problem' => 'Top trigger_1 💡',
						'Refresh interval' => '10 minutes'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Not classified severity check',
						'id:severities_0' => true,
						'Refresh interval' => '15 minutes'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Random severities checked',
						'id:severities_0' => true,
						'id:severities_3' => true,
						'id:severities_5' => true,
						'Refresh interval' => 'Default (No refresh)'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => '📌 All severities checked',
						'id:severities_0' => true,
						'id:severities_1' => true,
						'id:severities_2' => true,
						'id:severities_3' => true,
						'id:severities_4' => true,
						'id:severities_5' => true
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Problem tags' => 'Or',
						'Trigger limit' => '1'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Problem tags' => 'And/Or',
						'Trigger limit' => '100'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => STRING_255,
						'Show header' => false,
						'Host groups' => [
							'Zabbix servers',
							'First Group for TOP triggers check'
						],
						'Hosts' => [
							'ЗАББИКС Сервер',
							'Host with top triggers trapper'
						],
						'Problem' => STRING_2048,
						'id:severities_3' => true,
						'Problem tags' => 'Or',
						'id:tags_0_tag' => STRING_255,
						'id:tags_0_operator' => 'Does not contain',
						'id:tags_0_value' => STRING_255,
						'Trigger limit' => '99'
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => ' Test trailing spaces ',
						'Trigger limit' => ' 1 ',
						'Problem' => ' BOOM ',
						'id:tags_0_tag' => ' Trigger ',
						'id:tags_0_operator' => 'Does not equal',
						'id:tags_0_value' => ' test ',
						'Problem tags' => 'And/Or'
					],
					'trim' => ['Name', 'Problem', 'Trigger limit', 'id:tags_0_tag', 'id:tags_0_value']
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Empty tag and value'
					],
					'tags' => [
						['name' => '', 'operator' => 'Contains', 'value' => '']
					]
				]
			],
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Different types of macro in input fields {$A}',
						'Problem' => '{HOST.HOST} {#ID}'
					],
					'tags' => [
						['name' => '{HOST.NAME}', 'operator' => 'Does not contain', 'value' => '{ITEM.VALUE}']
					]
				]
			],
			// Check that tags table contains entries with UTF-8 4-byte characters, empty tag/value and all possible operators.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Check tags table'
					],
					'tags' => [
						['name' => 'empty value', 'operator' => 'Equals', 'value' => ''],
						['name' => '', 'operator' => 'Does not contain', 'value' => 'empty tag'],
						['name' => 'Check tag with operator - Equals ⚠️', 'operator' => 'Equals', 'value' => 'Warning ⚠️'],
						['name' => 'Check tag with operator - Exists', 'operator' => 'Exists'],
						['name' => 'Check tag with operator - Contains ❌', 'operator' => 'Contains', 'value' => 'tag value ❌'],
						['name' => 'Check tag with operator - Does not exist', 'operator' => 'Does not exist'],
						['name' => 'Check tag with operator - Does not equal', 'operator' => 'Does not equal', 'value' => 'Average'],
						['name' => 'Check tag with operator - Does not contain', 'operator' => 'Does not contain', 'value' => 'Disaster']
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getWidgetData
	 */
	public function testDashboardTopTriggersWidget_Create($data) {
		$this->checkWidgetForm($data);
	}

	/**
	 * @dataProvider getWidgetData
	 */
	public function testDashboardTopTriggersWidget_Update($data) {
		$this->checkWidgetForm($data, true);
	}

	public function testDashboardTopTriggersWidget_SimpleUpdate() {
		$old_hash = CDBHelper::getHash(self::SQL);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_create)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dashboard->getWidget(self::$update_widget)->edit()->submit();
		$dashboard->save();
		$this->page->waitUntilReady();

		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
	}

	/**
	 * Perform Top triggers widget creation or update and verify the result.
	 *
	 * @param boolean $update	updating is performed
	 */
	protected function checkWidgetForm($data, $update = false) {
		$expected = CTestArrayHelper::get($data, 'expected', TEST_GOOD);
		if ($expected === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::SQL);
		}

		if ($data['fields'] === []) {
			$data['fields']['Name'] = '';
		}
		else {
			$data['fields']['Name'] = CTestArrayHelper::get($data, 'fields.Name', 'Top triggers ' . microtime());
		}

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_create)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		$form = ($update)
			? $dashboard->getWidget(self::$update_widget)->edit()->asForm()
			: $dashboard->edit()->addWidget()->asForm();

		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Top triggers')]);
		$form->fill($data['fields']);

		if (CTestArrayHelper::get($data,'tags')) {
			$this->setTags($data['tags']);
		}

		if ($expected === TEST_GOOD) {
			$values = $form->getFields()->filter(CElementFilter::VISIBLE)->asValues();
		}

		$form->submit();

		// Trim leading and trailing spaces from expected results if necessary.
		if (array_key_exists('trim', $data)) {
			foreach ($data['trim'] as $field) {
				$data['fields'][$field] = trim($data['fields'][$field]);
			}
		}

		if ($expected === TEST_BAD) {
			$this->assertMessage($data['expected'], null, $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));

			COverlayDialogElement::find()->one()->close();
		}
		else {
			// If name is empty string it is replaced by default name "Top triggers".
			$header = ($data['fields']['Name'] === '') ? 'Top triggers' : $data['fields']['Name'];
			if ($update) {
				self::$update_widget = $header;
			}

			COverlayDialogElement::ensureNotPresent();
			$widget = $dashboard->getWidget($header);

			// Save Dashboard to ensure that widget is correctly saved.
			$dashboard->save()->waitUntilReady();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Check widgets count.
			$this->assertEquals($old_widget_count + ($update ? 0 : 1), $dashboard->getWidgets()->count());

			// Check new widget update interval.
			$refresh = (CTestArrayHelper::get($data['fields'], 'Refresh interval') === 'Default (No refresh)')
				? 'No refresh'
				: (CTestArrayHelper::get($data['fields'], 'Refresh interval', 'No refresh'));
			$this->assertEquals($refresh, $widget->getRefreshInterval());

			// Check new widget form fields and values in frontend.
			$saved_form = $widget->edit();
			$this->assertEquals($values, $saved_form->getFields()->filter(CElementFilter::VISIBLE)->asValues());
			$saved_form->checkValue($data['fields']);

			if (array_key_exists('tags', $data)) {
				$this->assertTags($data['tags']);
			}

			// Close widget window and cancel editing the dashboard.
			COverlayDialogElement::find()->one()->close();
			$dashboard->cancelEditing();
		}
	}

	public static function getCancelData() {
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
	 * @dataProvider getCancelData
	 */
	public function testDashboardTopTriggersWidget_Cancel($data) {
		$old_hash = CDBHelper::getHash(self::SQL);
		$new_name = 'Widget to be cancelled';

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one()->edit();
		$old_widget_count = $dashboard->getWidgets()->count();

		// Start updating or creating a widget.
		if (CTestArrayHelper::get($data, 'update', false)) {
			$form = $dashboard->getWidget(self::DEFAULT_WIDGET)->edit();
		}
		else {
			$form = $dashboard->addWidget()->asForm();
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Top triggers')]);
		}

		$form->fill([
			'Name' => $new_name,
			'Refresh interval' => '15 minutes',
			'Problem' => 'BOOM',
			'Problem tags' => 'Or',
			'id:tags_0_tag' => 'trigger',
			'id:tags_0_operator' => 'Does not contain',
			'id:tags_0_value' => 'cancel'
		]);

		// Save or cancel widget.
		if (CTestArrayHelper::get($data, 'save_widget', false)) {
			$form->submit();

			// Check that changes took place on the unsaved dashboard.
			$this->assertTrue($dashboard->getWidget($new_name)->isVisible());
		}
		else {
			$dialog = COverlayDialogElement::find()->one();
			$dialog->query('button:Cancel')->one()->click();
			$dialog->ensureNotPresent();

			if (CTestArrayHelper::get($data, 'update', false)) {
				foreach ([self::DEFAULT_WIDGET => true, $new_name => false] as $name => $valid) {
					$dashboard->getWidget($name, false)->isValid($valid);
				}
			}

			$this->assertEquals($old_widget_count, $dashboard->getWidgets()->count());
		}

		// Save or cancel dashboard update.
		if (CTestArrayHelper::get($data, 'save_dashboard', false)) {
			$dashboard->save();
		}
		else {
			$dashboard->cancelEditing();
		}

		$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
	}

	public function testDashboardTopTriggersWidget_Delete() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one()->edit();
		$widget = $dashboard->getWidget(self::DELETE_WIDGET);
		$dashboard->deleteWidget(self::DELETE_WIDGET);
		$widget->waitUntilNotPresent();
		$dashboard->save();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Check that widget is not present on dashboard.
		$this->assertFalse($dashboard->getWidget(self::DELETE_WIDGET, false)->isValid());
		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM widget_field wf'.
				' LEFT JOIN widget w'.
					' ON w.widgetid=wf.widgetid'.
					' WHERE w.name='.zbx_dbstr(self::DELETE_WIDGET)
		));
	}

	public static function getWidgetTableData() {
		return [
			// Check widget data with all possible severity types and different problems count.
			[
				[
					'trigger_data' => [
						[
							'name' => 'Problem Not classified',
							'time' => strtotime('now'),
							'problem_count' => '6'
						],
						[
							'name' => 'Problem Information',
							'time' => strtotime('-2 minutes'),
							'problem_count' => '5'
						],
						[
							'name' => 'Severity status: Warning⚠️',
							'time' => strtotime('-3 minutes'),
							'problem_count' => '4'
						],
						[
							'name' => 'Problem Average',
							'time' => strtotime('-4 minutes'),
							'problem_count' => '3'
						],
						[
							'name' => 'Problem High',
							'time' => strtotime('-5 minutes'),
							'problem_count' => '2'
						],
						[
							'name' => 'Problem Disaster',
							'time' => strtotime('now'),
							'problem_count' => '1'
						]
					],
					'expected' => [
						[
							'Host' => 'Host with top triggers trapper',
							'Trigger' => 'Problem Not classified',
							'Severity' => 'Not classified',
							'Number of problems' => '6'
						],
						[
							'Host' => 'Host with top triggers trapper',
							'Trigger' => 'Problem Information',
							'Severity' => 'Information',
							'Number of problems' => '5'
						],
						[
							'Host' => 'Host with top triggers trapper2',
							'Trigger' => 'Severity status: Warning⚠️',
							'Severity' => 'Warning',
							'Number of problems' => '4'
						],
						[
							'Host' => 'Host with top triggers trapper',
							'Trigger' => 'Problem Average',
							'Severity' => 'Average',
							'Number of problems' => '3'
						],
						[
							'Host' => 'Host with top triggers trapper',
							'Trigger' => 'Problem High',
							'Severity' => 'High',
							'Number of problems' => '2'
						],
						[
							'Host' => 'Host with top triggers trapper',
							'Trigger' => 'Problem Disaster',
							'Severity' => 'Disaster',
							'Number of problems' => '1'
						],
						[
							'Host' => 'Host for tag permissions',
							'Trigger' => 'Trigger for tag permissions MySQL',
							'Severity' => 'Not classified',
							'Number of problems' => '1'
						],
						[
							'Host' => 'Host for tag permissions',
							'Trigger' => 'Trigger for tag permissions Oracle',
							'Severity' => 'Not classified',
							'Number of problems' => '1'
						]
					],
					'background_color' => [
						'Problem Not classified' => 'na-bg',
						'Problem Information' => 'info-bg',
						'Severity status: Warning⚠️' => 'warning-bg',
						'Problem Average' => 'average-bg',
						'Problem High' => 'high-bg',
						'Problem Disaster' => 'disaster-bg'
					]
				]
			],
			// Check results from particular host group.
			[
				[
					'fields' => [
						'Host groups' => 'Second Group for TOP triggers check'
					],
					'trigger_data' => [
						[
							'name' => 'Problem Not classified',
							'time' => strtotime('now'),
							'problem_count' => '3'
						],
						[
							'name' => 'Severity status: Warning⚠️',
							'time' => strtotime('-5 minutes'),
							'problem_count' => '1'
						]
					],
					'expected' => [
						[
							'Host' => 'Host with top triggers trapper2',
							'Trigger' => 'Severity status: Warning⚠️',
							'Severity' => 'Warning',
							'Number of problems' => '1'
						]
					],
					'background_color' => [
						'Severity status: Warning⚠️' => 'warning-bg'
					]
				]
			],
			// Check results from particular host.
			[
				[
					'fields' => [
						'Host groups' => '',
						'Hosts' => 'Host with top triggers trapper'
					],
					'trigger_data' => [
						[
							'name' => 'Problem Not classified',
							'time' => strtotime('now'),
							'problem_count' => '3'
						],
						[
							'name' => 'Problem Average',
							'time' => strtotime('-2 minutes'),
							'problem_count' => '1'
						],
						[
							'name' => 'Severity status: High',
							'time' => strtotime('-5 minutes'),
							'problem_count' => '5'
						]
					],
					'expected' => [
						[
							'Host' => 'Host with top triggers trapper',
							'Trigger' => 'Problem Not classified',
							'Severity' => 'Not classified',
							'Number of problems' => '3'
						],
						[
							'Host' => 'Host with top triggers trapper',
							'Trigger' => 'Problem Average',
							'Severity' => 'Average',
							'Number of problems' => '1'
						]
					],
					'background_color' => [
						'Problem Not classified' => 'na-bg',
						'Problem Average' => 'average-bg'
					]
				]
			],
			// Check trigger with macro.
			[
				[
					'fields' => [
						'Hosts' => '',
						'Problem' => 'Trigger from '
					],
					'trigger_data' => [
						[
							'name' => 'Problem Not classified',
							'time' => strtotime('now'),
							'problem_count' => '1'
						],
						[
							'name' => 'Trigger from {HOST.HOST}',
							'time' => strtotime('-2 minutes'),
							'problem_count' => '1'
						]
					],
					'expected' => [
						[
							'Host' => 'Host with top triggers trapper2',
							'Trigger' => 'Trigger from Host with top triggers trapper2',
							'Severity' => 'Information',
							'Number of problems' => '1'
						]
					],
					'background_color' => [
						'Trigger from Host with top triggers trapper2' => 'info-bg'
					]
				]
			],
			// Filter problems by severity.
			[
				[
					'fields' => [
						'Problem' => '',
						'High' => true
					],
					'trigger_data' => [
						[
							'name' => 'Problem High',
							'time' => strtotime('now'),
							'problem_count' => '1'
						],
						[
							'name' => 'Severity status: High',
							'time' => strtotime('now'),
							'problem_count' => '1'
						],
						[
							'name' => 'Problem Information',
							'time' => strtotime('-2 minutes'),
							'problem_count' => '1'
						]
					],
					'expected' => [
						[
							'Host' => 'Host with top triggers trapper',
							'Trigger' => 'Problem High',
							'Severity' => 'High',
							'Number of problems' => '1'
						],
						[
							'Host' => 'Host with top triggers trapper2',
							'Trigger' => 'Severity status: High',
							'Severity' => 'High',
							'Number of problems' => '1'
						]
					],
					'background_color' => [
						'Problem High' => 'high-bg',
						'Severity status: High' => 'high-bg'
					]
				]
			],
			// Filter problems by severities.
			[
				[
					'fields' => [
						'Average' => true,
						'High' => true,
						'Disaster' => true
					],
					'trigger_data' => [
						[
							'name' => 'Problem Disaster',
							'time' => strtotime('now'),
							'problem_count' => '1'
						],
						[
							'name' => 'Problem Average',
							'time' => strtotime('now'),
							'problem_count' => '1'
						],
						[
							'name' => 'Severity status: High',
							'time' => strtotime('now'),
							'problem_count' => '1'
						],
						[
							'name' => 'Problem Information',
							'time' => strtotime('-2 minutes'),
							'problem_count' => '1'
						]
					],
					'expected' => [
						[
							'Host' => 'Host with top triggers trapper',
							'Trigger' => 'Problem Disaster',
							'Severity' => 'Disaster',
							'Number of problems' => '1'
						],
						[
							'Host' => 'Host with top triggers trapper2',
							'Trigger' => 'Severity status: High',
							'Severity' => 'High',
							'Number of problems' => '1'
						],
						[
							'Host' => 'Host with top triggers trapper',
							'Trigger' => 'Problem Average',
							'Severity' => 'Average',
							'Number of problems' => '1'
						]
					],
					'background_color' => [
						'Problem Disaster' => 'disaster-bg',
						'Severity status: High' => 'high-bg',
						'Problem Average' => 'average-bg'
					]
				]
			],
			[
				[
					'fields' => [
						'High' => false,
						'Average' => false,
						'Disaster' => false,
						'Trigger limit' => '2'
					],
					'trigger_data' => [
						[
							'name' => 'Problem Disaster',
							'time' => strtotime('now'),
							'problem_count' => '1'
						],
						[
							'name' => 'Severity status: High',
							'time' => strtotime('now'),
							'problem_count' => '1'
						],
						[
							'name' => 'Problem Average',
							'time' => strtotime('now'),
							'problem_count' => '1'
						],
						[
							'name' => 'Severity status: Warning⚠️',
							'time' => strtotime('now'),
							'problem_count' => '1'
						],
						[
							'name' => 'Problem Not classified',
							'time' => strtotime('now'),
							'problem_count' => '1'
						],
						[
							'name' => 'Problem Information',
							'time' => strtotime('now'),
							'problem_count' => '1'
						]
					],
					'expected' => [
						[
							'Host' => 'Host with top triggers trapper',
							'Trigger' => 'Problem Disaster',
							'Severity' => 'Disaster',
							'Number of problems' => '1'
						],
						[
							'Host' => 'Host with top triggers trapper2',
							'Trigger' => 'Severity status: High',
							'Severity' => 'High',
							'Number of problems' => '1'
						]
					],
					'background_color' => [
						'Problem Disaster' => 'disaster-bg',
						'Severity status: High' => 'high-bg'
					]
				]
			],
			// Check problems by tag name/value.
			[
				[
					'tags' => true,
					'fields' => [
						'Warning' => true,
						'Trigger limit' => '10',
						'id:tags_0_tag' => 'test1',
						'id:tags_0_value' => 'tag1'
					],
					'trigger_data' => [
						[
							'name' => 'Problem with tag',
							'time' => strtotime('-2 minutes'),
							'problem_count' => '1',
							'tag' => 'test1',
							'value' => 'tag1'
						],
						[
							'name' => 'Problem Warning',
							'time' => strtotime('now'),
							'problem_count' => '1',
							'tag' => 'test2',
							'value' => 'tag2'
						],
						[
							'name' => 'Issue: Warning',
							'time' => strtotime('-1 minute'),
							'problem_count' => '1',
							'tag' => 'test2',
							'value' => 'tag2'
						]
					],
					'expected' => [
						[
							'Host' => 'TOP triggers',
							'Trigger' => 'Problem with tag',
							'Severity' => 'Warning',
							'Number of problems' => '1'
						]
					],
					'background_color' => [
						'Problem with tag' => 'warning-bg'
					]
				]
			],
			// Check results with several filtering parameters.
			[
				[
					'fields' => [
						'id:tags_0_tag' => '',
						'id:tags_0_value' => '',
						'Host groups' => 'First Group for TOP triggers check',
						'Problem' => 'Issue'
					],
					'trigger_data' => [
						[
							'name' => 'Problem Warning',
							'time' => strtotime('now'),
							'problem_count' => '1'
						],
						[
							'name' => 'Severity status: High',
							'time' => strtotime('now'),
							'problem_count' => '1'
						],
						[
							'name' => 'Severity status: Warning⚠️',
							'time' => strtotime('now'),
							'problem_count' => '1'
						],
						[
							'name' => 'Issue: Warning',
							'time' => strtotime('now'),
							'problem_count' => '1'
						],
						[
							'name' => 'Problem Information',
							'time' => strtotime('now'),
							'problem_count' => '1'
						]
					],
					'expected' => [
						[
							'Host' => 'TOP triggers',
							'Trigger' => 'Issue: Warning',
							'Severity' => 'Warning',
							'Number of problems' => '1'
						]
					],
					'background_color' => [
						'Issue: Warning' => 'warning-bg'
					]
				]
			],
			// Check filter results using time selector (From -> now-24h, To -> now).
			[
				[
					'fields' => [
						'Information' => true,
						'Host groups' => '',
						'Problem' => ''
					],
					'trigger_data' => [
						[
							'name' => 'Problem Information',
							'time' => strtotime('now'),
							'problem_count' => '11'
						],
						[
							'name' => 'Problem Warning',
							'time' => strtotime('-2 days'),
							'problem_count' => '2'
						]
					],
					'expected' => [
						[
							'Host' => 'Host with top triggers trapper',
							'Trigger' => 'Problem Information',
							'Severity' => 'Information',
							'Number of problems' => '11'
						]
					],
					'background_color' => [
						'Problem Information' => 'info-bg'
					]
				]
			]
		];
	}

	/**
	 * @backup !problem, !events, !event_tag, !problem_tag, !alerts, !service_problem, !event_symptom, !acknowledges, !event_recovery, !event_suppress
	 *
	 * @dataProvider getWidgetTableData
	 */
	public function testDashboardTopTriggersWidget_WidgetTableData($data) {
		foreach ($data['trigger_data'] as $params) {
			for ($i = 1; $i <= $params['problem_count']; $i++) {
				CDBHelper::setTriggerProblem($params['name'], TRIGGER_VALUE_TRUE, ['clock' => $params['time']]);
			}
		}

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_data)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dashboard->waitUntilReady();

		// Set specific time selector in zoom filter.
		$filter = CFilterElement::find()->one();
		if ($filter->isTabSelected('Last 1 day') === false) {
			$filter->query('link:Last 1 day')->one()->click();
			$this->page->waitUntilReady();
			$dashboard->waitUntilReady();
		}

		if (array_key_exists('fields', $data)) {
			$form = $dashboard->getWidget(self::DATA_WIDGET)->edit()->asForm();
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Top triggers')]);
			$form->fill($data['fields']);
			$form->submit();
			COverlayDialogElement::ensureNotPresent();
			$dashboard->save();
			$dashboard->waitUntilReady();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		}

		$this->assertTableData($data['expected']);

		foreach ($data['background_color'] as $trigger => $colors) {
			$table = $dashboard->getWidget(self::DATA_WIDGET)->getContent()->asTable();
			$this->assertEquals($colors, $table->findRow('Trigger', $trigger)->getColumn('Severity')
					->getAttribute('class')
			);
		}
	}

	public function testDashboardTopTriggersWidget_ContextMenu() {
		// Create problem.
		CDBHelper::setTriggerProblem('First test trigger with tag priority', TRIGGER_VALUE_TRUE);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboard_data)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dashboard->waitUntilReady();

		$data = [
			'trigger_menu' => [
				'VIEW' => [
					'Problems' => 'zabbix.php?action=problem.view&filter_set=1&triggerids%5B%5D=99252',
					'History' => [ 'Number of processes' => 'history.php?action=showgraph&itemids%5B%5D=42253']
				],
				'CONFIGURATION' => [
					'Trigger' => 'menu-popup-item',
					'Items' => [ 'Number of processes' => 'menu-popup-item']
				]
			],
			'host_menu' => [
				'VIEW' => [
					'Dashboards' => 'zabbix.php?action=host.dashboard.view&hostid=10084',
					'Problems' => 'zabbix.php?action=problem.view&hostids%5B%5D=10084&filter_set=1',
					'Latest data' => 'zabbix.php?action=latest.view&hostids%5B%5D=10084&filter_set=1',
					'Graphs' => 'zabbix.php?action=charts.view&filter_hostids%5B%5D=10084&filter_set=1',
					'Web' => 'menu-popup-item disabled',
					'Inventory' => 'hostinventories.php?hostid=10084'
				],
				'CONFIGURATION' => [
					'Host' => 'zabbix.php?action=popup&popup=host.edit&hostid=10084',
					'Items' => 'zabbix.php?action=item.list&filter_set=1&filter_hostids%5B%5D=10084&context=host',
					'Triggers' => 'zabbix.php?action=trigger.list&filter_set=1&filter_hostids%5B%5D=10084&context=host',
					'Graphs' => 'graphs.php?filter_set=1&filter_hostids%5B%5D=10084&context=host',
					'Discovery' => 'host_discovery.php?filter_set=1&filter_hostids%5B%5D=10084&context=host',
					'Web' => 'httpconf.php?filter_set=1&filter_hostids%5B%5D=10084&context=host'
				],
				'SCRIPTS' => [
					'Detect operating system' => 'menu-popup-item',
					'Ping' => 'menu-popup-item',
					'Traceroute' => 'menu-popup-item'
				]
			]
		];

		// Check host context menu links.
		$this->query('link', 'ЗАББИКС Сервер')->one()->waitUntilClickable()->click();
		$this->checkContextMenuLinks($data['host_menu']);

		// Check trigger context menu links.
		$this->query('link', 'First test trigger with tag priority')->one()->waitUntilClickable()->click();
		$this->checkContextMenuLinks($data['trigger_menu']);
	}

	/**
	 * Check context menu links.
	 *
	 * @param array $data	data provider with fields values
	 */
	protected function checkContextMenuLinks($data) {
		// Check popup menu.
		$popup = CPopupMenuElement::find()->waitUntilVisible()->one();
		$this->assertTrue($popup->hasTitles(array_keys($data)));

		$menu_level1_items = [];
		foreach (array_values($data) as $menu_items) {
			foreach ($menu_items as $menu_level1 => $link) {
				$menu_level1_items[] = $menu_level1;

				if (is_array($link)) {
					foreach ($link as $menu_level2 => $attribute) {
						// Check 2-level menu links.
						$item_link = $popup->getItem($menu_level1)->query('xpath:./../ul//a')->one();
						$this->assertEquals($menu_level2, $item_link->getText());
						$this->assertStringContainsString($attribute,
								$item_link->getAttribute(($attribute === 'menu-popup-item') ? 'class' : 'href')
						);
					}
				}
				else {
					// Check 1-level menu links.
					if (str_contains($link, 'menu-popup-item')) {
						$this->assertEquals($link, $popup->getItem($menu_level1)->getAttribute('class'));
					}
					else {
						$this->assertTrue($popup->query("xpath:.//a[text()=".CXPathHelper::escapeQuotes($menu_level1).
								" and contains(@href, ".CXPathHelper::escapeQuotes($link).")]")->exists()
						);
					}
				}
			}
		}

		$this->assertTrue($popup->hasItems($menu_level1_items));
		$popup->close();
	}
}
