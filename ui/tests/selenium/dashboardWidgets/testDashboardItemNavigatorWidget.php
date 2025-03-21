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


require_once __DIR__.'/../../include/CWebTest.php';

/**
 * @dataSource AllItemValueTypes
 *
 * @backup dashboard
 *
 * @onBefore prepareData
 */
class testDashboardItemNavigatorWidget extends testWidgets {

	/**
	 * Attach MessageBehavior, TableBehavior and TagBehavior to the test.
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class,
			CTagBehavior::class
		];
	}

	protected static $dashboardid;
	protected static $groupids;
	protected static $update_widget = 'Update Item navigator widget';
	const DEFAULT_WIDGET = 'Default Item navigator widget';
	const DELETE_WIDGET = 'Widget for delete';
	const DEFAULT_DASHBOARD = 'Dashboard for Item navigator widget test';
	const DASHBOARD_FOR_WIDGET_CREATE = 'Dashboard for Item navigator widget create/update test';

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
								'type' => 'itemnavigator',
								'name' => self::DEFAULT_WIDGET,
								'x' => 0,
								'y' => 0,
								'width' => 36,
								'height' => 5
							],
							[
								'type' => 'itemnavigator',
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
								'type' => 'itemnavigator',
								'name' => self::$update_widget,
								'x' => 0,
								'y' => 0,
								'width' => 36,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'ZBXIN'
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
			['name' => 'First Group for Item navigator check'],
			['name' => 'Second Group for Item navigator check']
		]);
		self::$groupids = CDataHelper::getIds('name');

		// Create hosts.
		CDataHelper::createHosts([
			[
				'host' => 'First host for Item navigator widget',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.7.1',
						'dns' => '',
						'port' => '11197'
					]
				],
				'groups' => [
					'groupid' => self::$groupids['First Group for Item navigator check']
				]
			],
			[
				'host' => 'Second host for Item navigator widget',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.7.2',
						'dns' => '',
						'port' => '11198'
					]
				],
				'groups' => [
					'groupid' => self::$groupids['Second Group for Item navigator check']
				]
			]
		]);
	}

	public function testDashboardItemNavigatorWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid[self::DEFAULT_DASHBOARD])->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dialog = $dashboard->edit()->addWidget();
		$this->assertEquals('Add widget', $dialog->getTitle());
		$form = $dialog->asForm();
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Item navigator')]);

		// Check default state.
		$default_state = [
			'Type' => 'Item navigator',
			'Name' => '',
			'Show header' => true,
			'Refresh interval' => 'Default (1 minute)',
			'Host groups' => '',
			'Hosts' => '',
			'Host tags' => 'And/Or',
			'id:host_tags_0_tag' => '',
			'id:host_tags_0_operator' => 'Contains',
			'id:host_tags_0_value' => '',
			'Item patterns' => '',
			'Item tags' => 'And/Or',
			'id:item_tags_0_tag' => '',
			'id:item_tags_0_operator' => 'Contains',
			'id:item_tags_0_value' => '',
			'State' => 'All',
			'Show problems' => 'Unsuppressed',
			'Group by' => [],
			'Item limit' => 100
		];

		$form->checkValue($default_state);
		$this->assertEquals(['Item limit'], $form->getRequiredLabels());

		// Check dropdown options.
		$this->getGroupByTable()->fill(['attribute' => 'Host group']);

		$options = [
			'Refresh interval' => ['Default (1 minute)', 'No refresh', '10 seconds', '30 seconds', '1 minute',
				'2 minutes', '10 minutes', '15 minutes'
			],
			'id:host_tags_0_operator' => ['Exists', 'Equals', 'Contains', 'Does not exist', 'Does not equal',
				'Does not contain'
			],
			'id:item_tags_0_operator' => ['Exists', 'Equals', 'Contains', 'Does not exist', 'Does not equal',
				'Does not contain'
			],
			'Group by' => ['Host group', 'Host name', 'Host tag value', 'Item tag value']
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
			'id:hostids__ms' => [
				'placeholder' => 'type here to search'
			],
			'id:host_tags_0_tag' => [
				'maxlength' => 255,
				'placeholder' => 'tag'
			],
			'id:host_tags_0_value' => [
				'maxlength' => 255,
				'placeholder' => 'value'
			],
			'id:items__ms' => [
				'placeholder' => 'patterns'
			],
			'id:item_tags_0_tag' => [
				'maxlength' => 255,
				'placeholder' => 'tag'
			],
			'id:item_tags_0_value' => [
				'maxlength' => 255,
				'placeholder' => 'value'
			],
			'id:group_by_0_tag_name' => [
				'maxlength' => 255,
				'placeholder' => 'tag'
			],
			'Item limit' => [
				'maxlength' => 4
			]
		];
		foreach ($inputs as $field => $attributes) {
			foreach ($attributes as $attribute => $value) {
				$this->assertEquals($value, $form->getField($field)->getAttribute($attribute));
			}
		}

		// Check radio buttons.
		$selection_elements = [
			'Host tags' => ['And/Or', 'Or'],
			'Item tags' => ['And/Or', 'Or'],
			'State' => ['All', 'Normal', 'Not supported'],
			'Show problems' => ['All', 'Unsuppressed', 'None']
		];
		foreach ($selection_elements as $name => $labels) {
			$this->assertEquals($labels, $form->getField($name)->getLabels()->asText());
		}

		// Check 'Host tags', 'Item tags' and 'Group by' table buttons.
		foreach (['id:tags_table_host_tags', 'id:tags_table_item_tags', 'id:group_by-table'] as $locator) {
			$this->assertEquals(2, $form->query($locator)->one()->query('button', ['Add', 'Remove'])->all()
					->filter((CElementFilter::CLICKABLE))->count()
			);
		}

		// Check if footer buttons present and clickable.
		$this->assertEquals(['Add', 'Cancel'], $dialog->getFooter()->query('button')->all()
				->filter(CElementFilter::CLICKABLE)->asText()
		);

		// Check Hosts and Host groups popup menu options.
		foreach (['Hosts', 'Host groups'] as $label) {
			$selector = $form->getField($label);
			$popup_menu = $selector->query('xpath:.//button[contains(@class, "zi-chevron-down")]')->one();

			foreach ([$selector->query('button:Select')->one(), $popup_menu] as $button) {
				$this->assertTrue($button->isClickable());
			}

			$options = ($label === 'Hosts') ? ['Hosts', 'Widget', 'Dashboard'] : ['Host groups', 'Widget'];
			$this->assertEquals($options, $popup_menu->asPopupButton()->getMenu()->getItems()->asText());

			foreach ($options as $title) {
				$popup_menu->asPopupButton()->getMenu()->select($title);

				if ($title === 'Dashboard') {
					$form->checkValue([$label => 'Dashboard']);
					$this->assertTrue($selector->query('xpath:.//span[@data-hintbox-contents="Dashboard is used as data source."]')
							->one()->isVisible()
					);
				}
				else {
					$dialogs = COverlayDialogElement::find()->all()->waitUntilReady();
					$this->assertEquals($title, $dialogs->last()->getTitle());
					$dialogs->last()->close();
				}
			}
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
						'Item limit' => ''
					],
					'error' => 'Invalid parameter "Item limit": value must be one of 1-9999.'
				]
			],
			// #1.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item limit' => ' '
					],
					'error' => 'Invalid parameter "Item limit": value must be one of 1-9999.'
				]
			],
			// #2.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item limit' => '0'
					],
					'error' => 'Invalid parameter "Item limit": value must be one of 1-9999.'
				]
			],
			// #3.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item limit' => 'test'
					],
					'error' => 'Invalid parameter "Item limit": value must be one of 1-9999.'
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
						['attribute' => 'Host name'],
						['attribute' => 'Host name']
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
						['attribute' => 'Item tag value', 'tag' => 'windows'],
						['attribute' => 'Item tag value', 'tag' => 'windows']
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
						['attribute' => 'Host tag value', 'tag' => 'server'],
						['attribute' => 'Host tag value', 'tag' => 'server']
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
						['attribute' => 'Host name'],
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
						['attribute' => 'Item tag value', 'tag' => 'memory'],
						['attribute' => 'Item tag value', 'tag' => 'cpu'],
						['attribute' => 'Item tag value', 'tag' => 'memory']
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
						['attribute' => 'Host tag value', 'tag' => 'server'],
						['attribute' => 'Host tag value', 'tag' => 'cpu'],
						['attribute' => 'Host tag value', 'tag' => 'server']
					],
					'error' => 'Invalid parameter "Group by": rows must be unique.'
				]
			],
			// #11.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [],
					'group_by' => [
						['attribute' => 'Host name'],
						['attribute' => 'Item tag value', 'tag' => 'memory'],
						['attribute' => 'Host name']
					],
					'error' => 'Invalid parameter "Group by": rows must be unique.'
				]
			],
			// #12.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [],
					'group_by' => [
						['attribute' => 'Host tag value']
					],
					'error' => 'Invalid parameter "Group by": tag cannot be empty.'
				]
			],
			// #13.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [],
					'group_by' => [
						['attribute' => 'Item tag value']
					],
					'error' => 'Invalid parameter "Group by": tag cannot be empty.'
				]
			],
			// #14.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => []
				]
			],
			// #15.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show header' => false,
						'Refresh interval' => 'No refresh'
					]
				]
			],
			// #16.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host groups' => 'First Group for Item navigator check',
						'Refresh interval' => '10 seconds'
					]
				]
			],
			// #17.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host groups' => [
							'First Group for Item navigator check',
							'Second Group for Item navigator check'
						],
						'Refresh interval' => '30 seconds'
					]
				]
			],
			// #18.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Hosts' => [
							'First host for Item navigator widget',
							'Second host for Item navigator widget'
						],
						'Refresh interval' => '1 minute'
					]
				]
			],
			// #19.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host tags' => 'Or',
						'Refresh interval' => '2 minutes'
					]
				]
			],
			// #20.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Item patterns' => 'available*',
						'Refresh interval' => '10 minutes'
					]
				]
			],
			// #21.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Item tags' => 'Or',
						'Refresh interval' => '15 minutes'
					]
				]
			],
			// #22.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host tags' => 'And/Or',
						'Item tags' => 'And/Or',
						'Item limit' => '1',
						'Refresh interval' => 'Default (1 minute)'
					]
				]
			],
			// #23.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Item limit' => '9999'
					]
				]
			],
			// #24.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'State => Normal',
						'State' => 'Normal'
					]
				]
			],
			// #25.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'State => Not supported',
						'State' => 'Not supported'
					]
				]
			],
			// #26.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Show problems => All',
						'Show problems' => 'All'
					]
				]
			],
			// #27.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Show problems => None',
						'Show problems' => 'None'
					]
				]
			],
			// #28.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Check all "Group by" attributes'
					],
					'group_by' => [
						['attribute' => 'Host tag value', 'tag' => STRING_255],
						['attribute' => 'Item tag value', 'tag' => STRING_255],
						['attribute' => 'Host name'],
						['attribute' => 'Host group']
					]
				]
			],
			// #29.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => STRING_255,
						'Show header' => true,
						'Host groups' => [
							'First Group for Item navigator check',
							'Second Group for Item navigator check'
						],
						'Hosts' => [
							'First host for Item navigator widget',
							'Second host for Item navigator widget'
						],
						'Host tags' => 'Or',
						'Item patterns' => 'memory*',
						'Item tags' => 'Or',
						'State' => 'All',
						'Show problems' => 'Unsuppressed',
						'Item limit' => '111'
					],
					'tags' => [
						'host' => [
							['name' => STRING_255, 'operator' => 'Does not contain', 'value' => STRING_255]
						],
						'item' => [
							['name' => STRING_255, 'operator' => 'Does not equal', 'value' => STRING_255]
						]
					]
				]
			],
			// #30.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => '  Test trailing spaces  ',
						'Item limit' => ' 1 ',
						'Item tags' => 'And/Or'
					],
					'tags' => [
						'host' => [
							['name' => '  Host  ', 'operator' => 'Does not equal', 'value' => '  test  ']
						],
						'item' => [
							['name' => '  Item  ', 'operator' => 'Does not contain', 'value' => '  test  ']
						]
					],
					'trim' => ['Name', 'Item limit', 'id:host_tags_0_tag', 'id:host_tags_0_value', 'id:item_tags_0_tag', 'id:item_tags_0_value']
				]
			],
			// #31.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Empty tag and value'
					],
					'tags' => [
						'host' => [
							['name' => '', 'operator' => 'Contains', 'value' => '']
						],
						'item' => [
							['name' => '', 'operator' => 'Contains', 'value' => '']
						]
					]
				]
			],
			// #32.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Different types of macro in input fields {$A}'
					],
					'tags' => [
						'host' => [
							['name' => '{HOST.NAME}', 'operator' => 'Does not contain', 'value' => '{HOST.CONN}']
						],
						'item' => [
							['name' => '{ITEM.NAME}', 'operator' => 'Does not contain', 'value' => '{ITEM.VALUE}']
						]
					],
					'group_by' => [
						['attribute' => 'Host tag value', 'tag' => '{HOST.NAME}'],
						['attribute' => 'Item tag value', 'tag' => '{ITEM.NAME}']
					]
				]
			],
			// #33 Check that host and item tags table contains entries with UTF-8 4-byte characters, empty tag/value and all possible operators.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Check tags table'
					],
					'tags' => [
						'host' => [
							['name' => 'empty value', 'operator' => 'Equals', 'value' => ''],
							['name' => '', 'operator' => 'Does not contain', 'value' => 'empty tag'],
							['name' => 'Check host tag with operator - Equals ⚠️', 'operator' => 'Equals', 'value' => 'Warning ⚠️'],
							['name' => 'Check host tag with operator - Exists', 'operator' => 'Exists'],
							['name' => 'Check host tag with operator - Contains ❌', 'operator' => 'Contains', 'value' => 'tag value ❌'],
							['name' => 'Check host tag with operator - Does not exist', 'operator' => 'Does not exist'],
							['name' => 'Check host tag with operator - Does not equal', 'operator' => 'Does not equal', 'value' => 'Average'],
							['name' => 'Check host tag with operator - Does not contain', 'operator' => 'Does not contain', 'value' => 'Disaster']
						],
						'item' => [
							['name' => 'empty value', 'operator' => 'Equals', 'value' => ''],
							['name' => '', 'operator' => 'Does not contain', 'value' => 'empty tag'],
							['name' => 'Check item tag with operator - Equals 🌵', 'operator' => 'Equals', 'value' => 'Warning 🌵'],
							['name' => 'Check item tag with operator - Exists', 'operator' => 'Exists'],
							['name' => 'Check item tag with operator - Contains 🐙', 'operator' => 'Contains', 'value' => 'tag value 🐙'],
							['name' => 'Check item tag with operator - Does not exist', 'operator' => 'Does not exist'],
							['name' => 'Check item tag with operator - Does not equal', 'operator' => 'Does not equal', 'value' => 'Average'],
							['name' => 'Check item tag with operator - Does not contain', 'operator' => 'Does not contain', 'value' => 'Disaster']
						]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getWidgetData
	 */
	public function testDashboardItemNavigatorWidget_Create($data) {
		$this->checkWidgetForm($data);
	}

	/**
	 * @dataProvider getWidgetData
	 */
	public function testDashboardItemNavigatorWidget_Update($data) {
		$this->checkWidgetForm($data, true);
	}

	public function testDashboardItemNavigatorWidget_SimpleUpdate() {
		$old_hash = CDBHelper::getHash(self::SQL);
		$this->setWidgetConfiguration(self::$dashboardid[self::DASHBOARD_FOR_WIDGET_CREATE], self::$update_widget);
		CDashboardElement::find()->one()->save();
		$this->page->waitUntilReady();

		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
	}

	/**
	 * Perform Item navigator widget creation or update and verify the result.
	 *
	 * @param boolean $update	updating is performed
	 */
	protected function checkWidgetForm($data, $update = false) {
		if ($data['expected'] === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::SQL);
		}

		$data['fields']['Name'] = ($data['fields'] === [])
			? ''
			: CTestArrayHelper::get($data, 'fields.Name', 'Item navigator '.microtime());

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid[self::DASHBOARD_FOR_WIDGET_CREATE])->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		$form = $update
			? $dashboard->getWidget(self::$update_widget)->edit()->asForm()
			: $dashboard->edit()->addWidget()->asForm();

		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Item navigator')]);

		if (array_key_exists('tags', $data)) {
			foreach ($data['tags'] as $entity => $values) {
				$this->setTagSelector('id:tags_table_'.$entity.'_tags');
				$this->setTags($values);
			}
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
			// If name is empty string it is replaced by default widget name "Item navigator".
			$header = ($data['fields']['Name'] === '') ? 'Item navigator' : $data['fields']['Name'];
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
				foreach ($data['tags'] as $entity => $values) {
					$this->setTagSelector('id:tags_table_'.$entity.'_tags');
					$this->assertTags($values);
				}
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
	public function testDashboardItemNavigatorWidget_Cancel($data) {
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
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Item navigator')]);
		}

		$form->fill([
			'Name' => $new_name,
			'Refresh interval' => '15 minutes',
			'Host tags' => 'Or',
			'id:host_tags_0_tag' => 'host',
			'id:host_tags_0_operator' => 'Does not contain',
			'id:host_tags_0_value' => 'cancel',
			'Item patterns' => 'available*',
			'id:item_tags_0_tag' => 'item',
			'id:item_tags_0_operator' => 'Does not contain',
			'id:item_tags_0_value' => 'cancel',
			'State' => 'Normal',
			'Show problems' => 'All',
			'Item limit' => '777'
		]);
		$this->getGroupByTable()->fill([
			['attribute' => 'Host tag value', 'tag' => 'windows'],
			['attribute' => 'Item tag value', 'tag' => 'memory']
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

	public function testDashboardItemNavigatorWidget_Delete() {
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
	 * Row highlight check.
	 */
	public function testDashboardItemNavigatorWidget_RowHighlight() {
		$this->setWidgetConfiguration(self::$dashboardid[self::DEFAULT_DASHBOARD], self::DEFAULT_WIDGET,
				['Item patterns' => '*memory in*']);
		$this->checkRowHighlight(self::DEFAULT_WIDGET, 'Available memory in %', true);
		CDashboardElement::find()->one()->save();
		$this->checkRowHighlight(self::DEFAULT_WIDGET, 'Available memory in %');
	}

	/**
	 * Test function for assuring that all types of items are available in Item navigator widget.
	 */
	public function testDashboardItemNavigatorWidget_CheckAvailableItems() {
		$this->checkAvailableItems('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid[self::DEFAULT_DASHBOARD],
				'Item navigator'
		);
	}
}
