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

/**
 * @backup dashboard
 *
 * @onBefore prepareData
 */
class testDashboardHostNavigatorWidget extends testWidgets {

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
	const MAINTENANCE_HOSTNAME = 'Host in maintenance';
	const DEFAULT_WIDGET = 'Default Host navigator widget';
	const DELETE_WIDGET = 'Widget for delete';
	const DEFAULT_DASHBOARD = 'Dashboard for Host navigator widget test';
	const DASHBOARD_FOR_WIDGET_CREATE = 'Dashboard for Host navigator widget create/update test';

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
		CDataHelper::call('dashboard.create', [
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
								'width' => 36,
								'height' => 5
							],
							[
								'type' => 'hostnavigator',
								'name' => self::DELETE_WIDGET,
								'x' => 36,
								'y' => 0,
								'width' => 36,
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
								'width' => 36,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'ZXCVB'
									]
								]
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

		// Create hosts.
		$result = CDataHelper::createHosts([
			[
				'host' => 'First host for host navigator widget',
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
				]
			],
			[
				'host' => 'Second host for host navigator widget',
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
				]
			],
			[
				'host' => self::MAINTENANCE_HOSTNAME,
				'groups' => ['groupid' => 4] // Zabbix servers.
			]
		]);

		$maintenace_hostid = $result['hostids'][self::MAINTENANCE_HOSTNAME];

		$maintenances = CDataHelper::call('maintenance.create', [
			[
				'name' => 'Maintenance for Host navigator widget',
				'maintenance_type' => MAINTENANCE_TYPE_NORMAL,
				'description' => 'Maintenance for icon check in Host navigator widget',
				'active_since' => time() - 100,
				'active_till' => time() + 31536000,
				'groups' => [['groupid' => 4]], // Zabbix servers.
				'timeperiods' => [[]]
			]
		]);
		$maintenanceid = $maintenances['maintenanceids'][0];

		DBexecute("INSERT INTO maintenances_hosts (maintenance_hostid, maintenanceid, hostid) VALUES (1000000, ".
				zbx_dbstr($maintenanceid).",".zbx_dbstr($maintenace_hostid).")"
		);

		DBexecute("UPDATE hosts SET maintenanceid=".zbx_dbstr($maintenanceid).
				", maintenance_status=1, maintenance_type=".MAINTENANCE_TYPE_NORMAL.", maintenance_from=".zbx_dbstr(time()-1000).
				" WHERE hostid=".zbx_dbstr($maintenace_hostid)
		);
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
			'Host patterns' => '',
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
				'placeholder' => 'patterns'
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

		// Check Host groups popup menu options.
		$selector = $form->getField('Host groups');
		$popup_menu = $selector->query('xpath:.//button[contains(@class, "zi-chevron-down")]')->one();

		foreach ([$selector->query('button:Select')->one(), $popup_menu] as $button) {
			$this->assertTrue($button->isClickable());
		}

		$options = ['Host groups', 'Widget'];
		foreach ($options as $title) {
			$this->assertEquals($options, $popup_menu->asPopupButton()->getMenu()->getItems()->asText());
			$popup_menu->asPopupButton()->getMenu()->select($title);
			$dialogs = COverlayDialogElement::find()->all()->waitUntilReady();
			$this->assertEquals($title, $dialogs->last()->getTitle());
			$dialogs->last()->close();
		}

		COverlayDialogElement::find()->one()->close();
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
						['attribute' => 'Tag value', 'tag' => 'linux'],
						['attribute' => 'Tag value', 'tag' => 'linux']
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
						['attribute' => 'Severity'],
						['attribute' => 'Tag value', 'tag' => 'windows'],
						['attribute' => 'Severity']
					],
					'error' => 'Invalid parameter "Group by": rows must be unique.'
				]
			],
			// #8.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [],
					'group_by' => [
						['attribute' => 'Host group'],
						['attribute' => 'Tag value', 'tag' => 'windows'],
						['attribute' => 'Host group']
					],
					'error' => 'Invalid parameter "Group by": rows must be unique.'
				]
			],
			// #9.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [],
					'group_by' => [
						['attribute' => 'Tag value', 'tag' => 'windows'],
						['attribute' => 'Tag value', 'tag' => 'cpu'],
						['attribute' => 'Tag value', 'tag' => 'windows']
					],
					'error' => 'Invalid parameter "Group by": rows must be unique.'
				]
			],
			// #10.
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
			// #11.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => []
				]
			],
			// #12.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show header' => false,
						'Refresh interval' => 'No refresh'
					]
				]
			],
			// #13.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host groups' => 'Zabbix servers',
						'Refresh interval' => '10 seconds'
					]
				]
			],
			// #14.
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
			// #15.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host patterns' => [
							'ЗАББИКС Сервер',
							'First host for host navigator widget'
						],
						'Refresh interval' => '1 minute'
					]
				]
			],
			// #16.
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
			// #17.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Host status => Disabled',
						'Host status' => 'Disabled',
						'Refresh interval' => '10 minutes'
					]
				]
			],
			// #18.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Host status => Any',
						'Host status' => 'Any',
						'Refresh interval' => '15 minutes'
					]
				]
			],
			// #19.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host tags' => 'Or',
						'Host limit' => '1',
						'Refresh interval' => 'Default (1 minute)'
					]
				]
			],
			// #20.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host tags' => 'And/Or',
						'Host limit' => '9999'
					]
				]
			],
			// #21.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Random severities check',
						'Not classified' => true,
						'Average' => true,
						'Disaster' => true
					]
				]
			],
			// #22.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => '📌 All severities check',
						'Not classified' => true,
						'Information' => true,
						'Warning' => true,
						'Average' => true,
						'High' => true,
						'Disaster' => true
					]
				]
			],
			// #23.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Maintenance check',
						'Show hosts in maintenance' => true,
						'Show problems' => 'All'
					]
				]
			],
			// #24.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show hosts in maintenance' => false,
						'Show problems' => 'None'
					]
				]
			],
			// #25.
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
			// #26.
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
						'Host patterns' => [
							'First host for host navigator widget',
							'Second host for host navigator widget'
						],
						'Host status' => 'Enabled',
						'Host tags' => 'Or',
						'Average' => true,
						'Show hosts in maintenance' => true,
						'Show problems' => 'All',
						'Host limit' => '9999'
					],
					'tags' => [
						['name' => STRING_255, 'operator' => 'Does not contain', 'value' => STRING_255]
					]
				]
			],
			// #27.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => '  Test trailing spaces  ',
						'Host limit' => ' 1 ',
						'Host tags' => 'And/Or'
					],
					'tags' => [
						['name' => '  Host  ', 'operator' => 'Does not equal', 'value' => '  test  ']
					],
					'trim' => ['Name', 'Host limit', 'id:host_tags_0_tag', 'id:host_tags_0_value']
				]
			],
			// #28.
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
			// #29.
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
			// #30 Check that tags table contains entries with UTF-8 4-byte characters, empty tag/value and all possible operators.
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
		$this->setWidgetConfiguration(self::$dashboardid[self::DASHBOARD_FOR_WIDGET_CREATE], self::$update_widget);
		CDashboardElement::find()->one()->save();
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
		if ($data['expected'] === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::SQL);
		}

		$data['fields']['Name'] = ($data['fields'] === [])
			? ''
			: CTestArrayHelper::get($data, 'fields.Name', 'Host navigator '.microtime());

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid[self::DASHBOARD_FOR_WIDGET_CREATE])->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		$form = $update
			? $dashboard->getWidget(self::$update_widget)->edit()->asForm()
			: $dashboard->edit()->addWidget()->asForm();

		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Host navigator')]);

		if (array_key_exists('tags', $data)) {
			$this->setTags($data['tags']);
		}

		$form->fill($data['fields']);

		if (array_key_exists('group_by', $data)) {
			$this->getGroupByTable()->fill($data['group_by']);
		}

		if ($data['expected'] === TEST_GOOD) {
			$values = $form->getFields()->filter(CElementFilter::VISIBLE)->asValues();
		}

		$form->submit();

		// Trim leading and trailing spaces from expected results if necessary.
		if (CTestArrayHelper::get($data, 'trim', false)) {
			$data = CTestArrayHelper::trim($data);
		}

		if ($data['expected'] === TEST_BAD) {
			$this->assertMessage($data['expected'], null, $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
			COverlayDialogElement::find()->one()->close();
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
			$dashboard->save()->waitUntilReady();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Check widgets count.
			$this->assertEquals($old_widget_count + ($update ? 0 : 1), $dashboard->getWidgets()->count());

			// Check new widget update interval.
			$refresh = (CTestArrayHelper::get($data['fields'], 'Refresh interval') === 'Default (1 minute)')
				? '1 minute'
				: (CTestArrayHelper::get($data['fields'], 'Refresh interval', '1 minute'));
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
			'Disaster' => true,
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
		$this->setWidgetConfiguration(self::$dashboardid[self::DEFAULT_DASHBOARD], self::DEFAULT_WIDGET,
				['Host patterns' => self::MAINTENANCE_HOSTNAME, 'Show hosts in maintenance' => true]);
		$dashboard = CDashboardElement::find()->one();
		$dashboard->save();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		$widget = $dashboard->getWidget(self::DEFAULT_WIDGET);
		$widget->query('xpath://button['.CXPathHelper::fromClass('zi-wrench-alt-small').']')->waitUntilClickable()->one()->click();
		$hint = $widget->query('xpath://div[@data-hintboxid]')->asOverlayDialog()->waitUntilPresent()->all()->last()->getText();
		$hint_text = "Maintenance for Host navigator widget [Maintenance with data collection]\n".
				"Maintenance for icon check in Host navigator widget";
		$this->assertEquals($hint_text, $hint);
	}

	/**
	 * Row highlight check.
	 */
	public function testDashboardHostNavigatorWidget_RowHighlight() {
		$this->setWidgetConfiguration(self::$dashboardid[self::DEFAULT_DASHBOARD], self::DEFAULT_WIDGET,
				['Host patterns' => 'Second host for host navigator widget']);
		$this->checkRowHighlight(self::DEFAULT_WIDGET, 'Second host for host navigator widget', true);
		CDashboardElement::find()->one()->save();
		$this->checkRowHighlight(self::DEFAULT_WIDGET, 'Second host for host navigator widget');
	}
}
