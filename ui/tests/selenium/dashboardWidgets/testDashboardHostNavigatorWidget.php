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


require_once dirname(__FILE__).'/../../include/CWebTest.php';

/**
 * @backup dashboard
 *
 * @onBefore prepareData
 */
class testDashboardHostNavigatorWidget extends CWebTest {

	/**
	 * Attach MessageBehavior, TableBehavior and TagBehavior to the test.
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class,
			[
				'class' => CTagBehavior::class,
				'tag_selector' => 'id:tags_table_host_tags'
			]
		];
	}

	protected static $dashboardid;
	protected static $groupids;
	protected static $update_widget = 'Update Host navigator widget';
	const DEFAULT_WIDGET = 'Default Host navigator widget';
	const DELETE_WIDGET = 'Widget for delete';
	const DEFAULT_DASHBOARD = 'Dashboard for Host navigator widget test';
	const DASHBOARD_FOR_WIDGET_CREATE = 'Dashboard for Host navigator widget create/update test';


	/**
	 * SQL query to get widget and widget_field tables to compare hash values, but without widget_fieldid
	 * because it can change.
	 */
	const SQL = 'SELECT wf.widgetid, wf.type, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
			' wf.value_itemid, wf.value_graphid, wf.value_sysmapid, w.widgetid, w.dashboard_pageid, w.type, w.name, w.x, w.y,'.
			' w.width, w.height'.
			' FROM widget_field wf'.
			' INNER JOIN widget w'.
			' ON w.widgetid=wf.widgetid'.
			' WHERE wf.name!=\'reference\''.
			' ORDER BY wf.widgetid, wf.name, wf.value_int, wf.value_str, wf.value_groupid,'.
			' wf.value_hostid, wf.value_itemid, wf.value_graphid';

	/**
	 * Get 'Group by' table element with mapping set.
	 *
	 * @return CMultifieldTable
	 */
	protected function getGroupByTable() {
		return $this->query('id:group_by-table')->asMultifieldTable([
			'mapping' => [
				'2' => [
					'name' => 'attribute',
					'selector' => 'xpath:./z-select',
					'class' => 'CDropdownElement'
				],
				'3' => [
					'name' => 'tag',
					'selector' => 'xpath:./input',
					'class' => 'CElement'
				]
			]
		])->waitUntilVisible()->one();
	}

	public static function prepareData() {
		$response = CDataHelper::call('dashboard.create', [
			[
				'name' => self::DEFAULT_DASHBOARD,
				'pages' => [
					[
						'name' => 'Page with default widgets',
						'widgets' => [
							[
								'type' => 'hostnavigator',
								'name' => self::DEFAULT_WIDGET,
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 5
							],
							[
								'type' => 'hostnavigator',
								'name' => self::DELETE_WIDGET,
								'x' => 12,
								'y' => 0,
								'width' => 12,
								'height' => 5
							]
						]
					]
				]
			],
			[
				'name' => self::DASHBOARD_FOR_WIDGET_CREATE,
				'pages' => [
					[
						'name' => 'Page with created/updated widgets',
						'widgets' => [
							[
								'type' => 'hostnavigator',
								'name' => self::$update_widget,
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 5
							]
						]
					]
				]
			]
		]);
		self::$dashboardid = CDataHelper::getIds('name');

		// Create hostgroups for hosts.
		CDataHelper::call('hostgroup.create', [
			['name' => 'First Group for Host navigator check'],
			['name' => 'Second Group for Host navigator check']
		]);
		self::$groupids = CDataHelper::getIds('name');

		// Create hosts and trapper items for host navigator form and data test.
		CDataHelper::createHosts([
			[
				'host' => 'Host with host navigator trapper item',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.7.1',
						'dns' => '',
						'port' => '10097'
					]
				],
				'groups' => [
					'groupid' => self::$groupids['First Group for Host navigator check']
				],
				'items' => [
					[
						'name' => 'Host navigator trapper',
						'key_' => 'navigatortrap',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			],
			[
				'host' => 'Host with host navigator trapper item2',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.7.2',
						'dns' => '',
						'port' => '10098'
					]
				],
				'groups' => [
					'groupid' => self::$groupids['Second Group for Host navigator check']
				],
				'items' => [
					[
						'name' => 'Host navigator trapper2',
						'key_' => 'navigatortrap',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			],
			[
				'host' => 'Host navigator',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.7.3',
						'dns' => '',
						'port' => '10099'
					]
				],
				'groups' => [
					'groupid' => self::$groupids['First Group for Host navigator check']
				],
				'items' => [
					[
						'name' => 'Host navigator trapper3',
						'key_' => 'navigatortrap',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			]
		]);
	}

	public function testDashboardHostNavigatorWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid[self::DEFAULT_DASHBOARD])->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dialog = $dashboard->edit()->addWidget();
		$this->assertEquals('Add widget', $dialog->getTitle());
		$form = $dialog->asForm();
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Host navigator')]);

		// Check default state.
		$default_state = [
			'Type' => 'Host navigator',
			'Name' => '',
			'Show header' => true,
			'Refresh interval' => 'Default (1 minute)',
			'Host groups' => '',
			'Hosts' => '',
			'Host status' => 'Any',
			'Host tags' => 'And/Or',
			'id:host_tags_0_tag' => '',
			'id:host_tags_0_operator' => 'Contains',
			'id:host_tags_0_value' => '',
			'Not classified' => false,
			'Information' => false,
			'Warning' => false,
			'Average' => false,
			'High' => false,
			'Disaster' => false,
			'Show hosts in maintenance' => false,
			'Show problems' => 'Unsuppressed',
			'Group by' => [],
			'Host limit' => 100
		];

		$form->checkValue($default_state);
		$this->assertEquals(['Host limit'], $form->getRequiredLabels());

		// Check dropdown options.
		$this->getGroupByTable()->fill(['attribute' => 'Host group']);

		$options = [
			'Refresh interval' => ['Default (1 minute)', 'No refresh', '10 seconds', '30 seconds', '1 minute',
				'2 minutes', '10 minutes', '15 minutes'
			],
			'id:host_tags_0_operator' => ['Exists', 'Equals', 'Contains', 'Does not exist', 'Does not equal',
				'Does not contain'
			],
			'Group by' => ['Host group', 'Tag value', 'Severity']
		];
		foreach ($options as $field => $values) {
			$this->assertEquals($values, $form->getField($field)->asDropdown()->getOptions()->asText());
		}

		$inputs = [
			'Name' => [
				'maxlength' => 255,
				'placeholder' => 'default'
			],
			'id:groupids__ms' => [
				'placeholder' => 'type here to search'
			],
			'id:hosts__ms' => [
				'placeholder' => 'host pattern'
			],
			'id:host_tags_0_tag' => [
				'maxlength' => 255,
				'placeholder' => 'tag'
			],
			'id:host_tags_0_value' => [
				'maxlength' => 255,
				'placeholder' => 'value'
			],
			'id:group_by_0_tag_name' => [
				'maxlength' => 255,
				'placeholder' => 'tag'
			],
			'Host limit' => [
				'maxlength' => 4
			]
		];
		foreach ($inputs as $field => $attributes) {
			foreach ($attributes as $attribute => $value) {
				$this->assertEquals($value, $form->getField($field)->getAttribute($attribute));
			}
		}

		// Check radio buttons and checkboxes.
		$selection_elements = [
			'Host status' => ['Any', 'Enabled', 'Disabled'],
			'Host tags' => ['And/Or', 'Or'],
			'Severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
			'Show problems' => ['All', 'Unsuppressed', 'None']
		];
		foreach ($selection_elements as $name => $labels) {
			$this->assertEquals($labels, $form->getField($name)->getLabels()->asText());
		}

		// Check 'Host tags' and 'Group by' table buttons.
		foreach (['id:tags_table_host_tags', 'id:group_by-table'] as $locator) {
			$this->assertEquals(2, $form->query($locator)->one()->query('button', ['Add', 'Remove'])->all()
					->filter((CElementFilter::CLICKABLE))->count()
			);
		}

		// Check if footer buttons present and clickable.
		$this->assertEquals(['Add', 'Cancel'], $dialog->getFooter()->query('button')->all()
				->filter(CElementFilter::CLICKABLE)->asText()
		);
	}

	public static function getWidgetData() {
		return [
			// #0.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Host limit' => ''
					],
					'error' => 'Invalid parameter "Host limit": value must be one of 1-9999.'
				]
			],
			// #1.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Host limit' => ' '
					],
					'error' => 'Invalid parameter "Host limit": value must be one of 1-9999.'
				]
			],
			// #2.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Host limit' => '0'
					],
					'error' => 'Invalid parameter "Host limit": value must be one of 1-9999.'
				]
			],
			// #3.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Host limit' => 'test'
					],
					'error' => 'Invalid parameter "Host limit": value must be one of 1-9999.'
				]
			],
			// #4.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [],
					'group_by' => [
						['attribute' => 'Host group'],
						['attribute' => 'Host group']
					],
					'error' => 'Invalid parameter "Group by": rows must be unique.'
				]
			],
			// #5.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [],
					'group_by' => [
						['attribute' => 'Severity'],
						['attribute' => 'Severity']
					],
					'error' => 'Invalid parameter "Group by": rows must be unique.'
				]
			],
			// #6.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [],
					'group_by' => [
						['attribute' => 'Host group'],
						['attribute' => 'Severity'],
						['attribute' => 'Host group']
					],
					'error' => 'Invalid parameter "Group by": rows must be unique.'
				]
			],
			// #7.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [],
					'group_by' => [
						['attribute' => 'Tag value']
					],
					'error' => 'Invalid parameter "Group by": tag cannot be empty.'
				]
			],
			// #8.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [],
					'group_by' => [
						['attribute' => 'Tag value'],
						['attribute' => 'Tag value']
					],
					'error' => [
						'Invalid parameter "Group by": tag cannot be empty.',
						'Invalid parameter "Group by": rows must be unique.',
					]
				]
			],
			// #9.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Host limit' => '0'
					],
					'group_by' => [
						['attribute' => 'Tag value'],
						['attribute' => 'Tag value']
					],
					'error' => [
						'Invalid parameter "Group by": tag cannot be empty.',
						'Invalid parameter "Group by": rows must be unique.',
						'Invalid parameter "Host limit": value must be one of 1-9999.'
					]
				]
			],
			// #10.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => []
				]
			],
			// #11.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show header' => false,
						'Refresh interval' => 'No refresh'
					]
				]
			],
			// #12.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host groups' => 'Zabbix servers',
						'Refresh interval' => '10 seconds'
					]
				]
			],
			// #13.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host groups' => [
							'Zabbix servers',
							'First Group for Host navigator check'
						],
						'Refresh interval' => '30 seconds'
					]
				]
			],
			// #14.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Hosts' => [
							'ЗАББИКС Сервер',
							'Host with host navigator trapper'
						],
						'Refresh interval' => '1 minute'
					]
				]
			],
			// #15.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Host status => Enabled',
						'Host status' => 'Enabled',
						'Refresh interval' => '2 minutes'
					]
				]
			],
			// #16.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host tags' => 'Or',
						'Host limit' => '1',
						'Refresh interval' => '10 minutes'
					]
				]
			],
			// #17.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host tags' => 'And/Or',
						'Host limit' => '9999',
						'Refresh interval' => '15 minutes'
					]
				]
			],
			// #18.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Random severities check',
						'id:severities_0' => true,
						'id:severities_3' => true,
						'id:severities_5' => true,
						'Refresh interval' => 'Default (1 minute)'
					]
				]
			],
			// #19.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => '📌 All severities check',
						'id:severities_0' => true,
						'id:severities_1' => true,
						'id:severities_2' => true,
						'id:severities_3' => true,
						'id:severities_4' => true,
						'id:severities_5' => true
					]
				]
			],
			// #20.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show hosts in maintenance' => true,
						'Show problems' => 'All'
					]
				]
			],
			// #21.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show hosts in maintenance' => false,
						'Show problems' => 'None'
					]
				]
			],
			// #22.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Check all "Group by" attributes'
					],
					'group_by' => [
						['attribute' => 'Tag value', 'tag' => 'linux'],
						['attribute' => 'Host group'],
						['attribute' => 'Severity']
					]
				]
			],
			// #23.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => STRING_255,
						'Show header' => true,
						'Host groups' => [
							'Zabbix servers',
							'First Group for Host navigator check'
						],
						'Hosts' => [
							'ЗАББИКС Сервер',
							'Host with host navigator trapper'
						],
						'Host status' => 'Enabled',
						'Host tags' => 'Or',
						'id:severities_3' => true,
						'Show hosts in maintenance' => true,
						'Show problems' => 'All',
						'id:host_tags_0_tag' => STRING_255,
						'id:host_tags_0_operator' => 'Does not contain',
						'id:host_tags_0_value' => STRING_255,
						'Host limit' => '9999'
					]
				]
			],
			// #24.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => '  Test trailing spaces  ',
						'Host limit' => ' 1 ',
						'id:host_tags_0_tag' => '  Host  ',
						'id:host_tags_0_operator' => 'Does not equal',
						'id:host_tags_0_value' => '  test  ',
						'Host tags' => 'And/Or'
					],
					'trim' => ['Name', 'Host limit', 'id:host_tags_0_tag', 'id:host_tags_0_value']
				]
			],
			// #25.
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
			// #26.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Different types of macro in input fields {$A}'
					],
					'tags' => [
						['name' => '{HOST.NAME}', 'operator' => 'Does not contain', 'value' => '{ITEM.VALUE}']
					],
					'group_by' => [
						['attribute' => 'Tag value', 'tag' => '{HOST.NAME}']
					]
				]
			],
			// #27 Check that tags table contains entries with UTF-8 4-byte characters, empty tag/value and all possible operators.
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
	public function testDashboardHostNavigatorWidget_Create($data) {
		$this->checkWidgetForm($data);
	}

	/**
	 * @dataProvider getWidgetData
	 */
	public function testDashboardHostNavigatorWidget_Update($data) {
		$this->checkWidgetForm($data, true);
	}

	public function testDashboardHostNavigatorWidget_SimpleUpdate() {
		$old_hash = CDBHelper::getHash(self::SQL);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid[self::DASHBOARD_FOR_WIDGET_CREATE])->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dashboard->getWidget(self::$update_widget)->edit()->submit();
		$dashboard->save();
		$this->page->waitUntilReady();

		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
	}

	/**
	 * Perform Host navigator widget creation or update and verify the result.
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
			$data['fields']['Name'] = CTestArrayHelper::get($data, 'fields.Name', 'Host navigator '.microtime());
		}

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid[self::DASHBOARD_FOR_WIDGET_CREATE])->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		$form = ($update)
			? $dashboard->getWidget(self::$update_widget)->edit()->asForm()
			: $dashboard->edit()->addWidget()->asForm();

		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Host navigator')]);
		$form->fill($data['fields']);

		if (array_key_exists('tags', $data)) {
			$this->setTags($data['tags']);
		}

		if (array_key_exists('group_by', $data)) {
			$this->getGroupByTable()->fill($data['group_by']);
		}

		if ($expected === TEST_GOOD) {
			$values = $form->getFields()->filter(CElementFilter::VISIBLE)->asValues();
		}

		$form->submit();
		$this->page->waitUntilReady();

		// Trim leading and trailing spaces from expected results if necessary.
		if (array_key_exists('trim', $data)) {
			foreach ($data['trim'] as $field) {
				$data['fields'][$field] = trim($data['fields'][$field]);
			}
		}
		if ($expected === TEST_BAD) {
			$this->assertMessage($data['expected'], null, $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
		}
		else {
			// If name is empty string it is replaced by default widget name "Host navigator".
			$header = ($data['fields']['Name'] === '') ? 'Host navigator' : $data['fields']['Name'];
			if ($update) {
				self::$update_widget = $header;
			}

			COverlayDialogElement::ensureNotPresent();
			$widget = $dashboard->getWidget($header);

			// Save Dashboard to ensure that widget is correctly saved.
			$dashboard->save();
			$this->page->waitUntilReady();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Check widgets count.
			$this->assertEquals($old_widget_count + ($update ? 0 : 1), $dashboard->getWidgets()->count());

			// Check new widget form fields and values in frontend.
			$saved_form = $widget->edit();
			$this->assertEquals($values, $saved_form->getFields()->filter(CElementFilter::VISIBLE)->asValues());
			$saved_form->checkValue($data['fields']);

			if (array_key_exists('tags', $data)) {
				$this->assertTags($data['tags']);
			}

			$saved_form->submit();
			COverlayDialogElement::ensureNotPresent();
			$dashboard->save();
			$this->page->waitUntilReady();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Check new widget update interval.
			$refresh = (CTestArrayHelper::get($data['fields'], 'Refresh interval') === 'Default (1 minute)')
				? '1 minute'
				: (CTestArrayHelper::get($data['fields'], 'Refresh interval', '1 minute'));
			$this->assertEquals($refresh, $widget->getRefreshInterval());
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
	public function testDashboardHostNavigatorWidget_Cancel($data) {
		$old_hash = CDBHelper::getHash(self::SQL);
		$new_name = 'Widget to be cancelled';

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid[self::DEFAULT_DASHBOARD])->waitUntilReady();
		$dashboard = CDashboardElement::find()->one()->edit();
		$old_widget_count = $dashboard->getWidgets()->count();

		// Start updating or creating a widget.
		if (CTestArrayHelper::get($data, 'update', false)) {
			$form = $dashboard->getWidget(self::DEFAULT_WIDGET)->edit();
		}
		else {
			$form = $dashboard->addWidget()->asForm();
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Host navigator')]);
		}

		$form->fill([
			'Name' => $new_name,
			'Refresh interval' => '15 minutes',
			'Host status' => 'Enabled',
			'Host tags' => 'Or',
			'id:host_tags_0_tag' => 'trigger',
			'id:host_tags_0_operator' => 'Does not contain',
			'id:host_tags_0_value' => 'cancel',
			'id:severities_5' => true,
			'Show hosts in maintenance' => true,
			'Show problems' => 'All',
			'Host limit' => '50'
		]);
		$this->getGroupByTable()->fill(['attribute' => 'Tag value', 'tag' => 'windows']);

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

	public function testDashboardHostNavigatorWidget_Delete() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid[self::DEFAULT_DASHBOARD])->waitUntilReady();
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

	/**
	 * Maintenance icon hintbox check.
	 */
	public function testDashboardHostNavigatorWidget_MaintenanceIconHintbox() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid[self::DEFAULT_DASHBOARD])->waitUntilReady();
		$dashboard = CDashboardElement::find()->one()->edit();
		$widget = $dashboard->getWidget(self::DEFAULT_WIDGET);
		$form = $widget->edit()->asForm();
		$form->fill(['Hosts' => 'Available host in maintenance', 'Show hosts in maintenance' => true]);
		$form->submit();
		$dashboard->save();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		$widget->query('xpath://button['.CXPathHelper::fromClass('zi-wrench-alt-small').']')->waitUntilClickable()->one()->click();
		$hint = $widget->query('xpath://div[@data-hintboxid]')->asOverlayDialog()->waitUntilPresent()->all()->last()->getText();
		$hint_text = "Maintenance for Host availability widget [Maintenance with data collection]\n".
				"Maintenance for checking Show hosts in maintenance option in Host availability widget";
		$this->assertEquals($hint_text, $hint);
	}
}
