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


require_once dirname(__FILE__) . '/../../include/CWebTest.php';

/**
 * @backup dashboard
 *
 * @dataSource HostAvailabilityWidget
 *
 * @onBefore prepareData
 */
class testDashboardProblemHostsWidget extends testWidgets {

	const DELETE_WIDGET = 'Delete Problem hosts';
	const MAP_WIDGET = 'Map widget for broadcasting';

	protected static $default_widget = 'Update problem hosts';
	protected static $dashboardid;

	/**
	 * Attach MessageBehavior to the test.
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

	public static function prepareData() {
		// Create default problem hosts widgets and broadcaster map widget.
		CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for Problem hosts widget test',
				'pages' => [
					[
						'name' => 'Page with default widgets',
						'widgets' => [
							[
								'type' => 'problemhosts',
								'name' => self::$default_widget,
								'x' => 0,
								'y' => 0,
								'width' => 36,
								'height' => 5,
								'fields' => [
									['type' => ZBX_WIDGET_FIELD_TYPE_INT32, 'name' => 'tags.0.operator', 'value' => 1],
									['type' => ZBX_WIDGET_FIELD_TYPE_STR, 'name' => 'tags.0.value', 'value' => 'default value'],
									['type' => ZBX_WIDGET_FIELD_TYPE_STR, 'name' => 'tags.0.tag', 'value' => 'default tag'],
									['type' => ZBX_WIDGET_FIELD_TYPE_STR,'name' => 'reference','value' => 'AAAAA']
								]
							],
							[
								'type' => 'problemhosts',
								'name' => self::DELETE_WIDGET,
								'x' => 36,
								'y' => 0,
								'width' => 36,
								'height' => 5,
								'fields' => [
									['type' => ZBX_WIDGET_FIELD_TYPE_STR, 'name' => 'reference', 'value' => 'BBBBB']
								]
							],
							[
								'type' => 'map',
								'name' => self::MAP_WIDGET,
								'x' => 0,
								'y' => 5,
								'width' => 36,
								'height' => 5,
								'fields' => [
									['type' => ZBX_WIDGET_FIELD_TYPE_MAP, 'name' => 'sysmapid', 'value' => 1], // Local network map.
									['type' => ZBX_WIDGET_FIELD_TYPE_STR, 'name' => 'reference', 'value' => 'CCCCC']
								]
							]
						]
					]
				]
			]
		]);
		self::$dashboardid = CDataHelper::getIds('name')['Dashboard for Problem hosts widget test'];
	}

	public function testDashboardProblemHostsWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dialog = $dashboard->edit()->addWidget();
		$form = $dialog->asForm();
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Problem hosts')]);

		// Check default state.
		$default_state = [
			'Type' => 'Problem hosts',
			'Show header' => true,
			'Name' => '',
			'Refresh interval' => 'Default (1 minute)',
			'Host groups' => '',
			'Exclude host groups' => '',
			'Hosts' => '',
			'Problem' => '',
			'Not classified' => false,
			'Information' => false,
			'Warning' => false,
			'Average' => false,
			'High' => false,
			'Disaster' => false,
			'Problem tags' => 'And/Or',
			'id:tags_0_tag' => '',
			'id:tags_0_operator' => 'Contains',
			'id:tags_0_value' => '',
			'Show suppressed problems' => false,
			'Hide groups without problems' => false,
			'Problem display' => 'All'
		];

		$form->checkValue($default_state);

		// Check dropdown options.
		$options = [
			'Refresh interval' => ['Default (1 minute)', 'No refresh', '10 seconds', '30 seconds', '1 minute',
				'2 minutes', '10 minutes', '15 minutes'
			],
			'id:tags_0_operator' => ['Exists', 'Equals', 'Contains', 'Does not exist', 'Does not equal',
				'Does not contain'
			]
		];
		foreach ($options as $field => $values) {
			$this->assertEquals($values, $form->getField($field)->asDropdown()->getOptions()->asText());
		}

		// Check input field attributes.
		$inputs = [
			'Name' => [
				'maxlength' => 255,
				'placeholder' => 'default'
			],
			'id:groupids__ms' => [
				'placeholder' => 'type here to search'
			],
			'id:exclude_groupids__ms' => [
				'placeholder' => 'type here to search'
			],
			'id:hostids__ms' => [
				'placeholder' => 'type here to search'
			],
			'id:problem' => [
				'maxlength' => 2048
			],
			'id:tags_0_tag' => [
				'maxlength' => 255,
				'placeholder' => 'tag'
			],
			'id:tags_0_value' => [
				'maxlength' => 255,
				'placeholder' => 'value'
			]
		];
		foreach ($inputs as $field => $attributes) {
			foreach ($attributes as $attribute => $value) {
				$this->assertEquals($value, $form->getField($field)->getAttribute($attribute));
			}
		}

		// Check radio buttons and checkboxes.
		$selection_elements = [
			'Severity' => ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'],
			'Problem tags' => ['And/Or', 'Or'],
			'Problem display' => ['All', 'Separated', 'Unacknowledged only']
		];
		foreach ($selection_elements as $name => $labels) {
			$this->assertEquals($labels, $form->getField($name)->getLabels()->asText());
		}

		// Check 'Problem tags' table buttons.
		$this->assertEquals(2, $form->query('id:tags_table_tags')->one()->query('button', ['Add', 'Remove'])->all()
				->filter((CElementFilter::CLICKABLE))->count()
		);

		// Check if footer buttons present and clickable.
		$this->assertEquals(['Add', 'Cancel'], $dialog->getFooter()->query('button')->all()
				->filter(CElementFilter::CLICKABLE)->asText()
		);

		// Check popup menu options.
		$popup_options = [
			'Host groups' => ['Host groups', 'Widget'],
			'Hosts' => ['Hosts', 'Widget', 'Dashboard']
		];
		foreach ($popup_options as $field => $popup_values) {
			$chevron = $form->getField($field)->query('xpath:.//button[contains(@class, "zi-chevron-down")]')->one();
			$this->assertTrue($chevron->isClickable());

			foreach ($popup_values as $title) {
				$chevron->asPopupButton()->getMenu()->select($title);

				if ($title !== 'Dashboard') {
					$dialog = COverlayDialogElement::find()->waitUntilReady()->all()->last();
					$this->assertEquals($title, $dialog->getTitle());
					$dialog->close();
				}
				else {
					$form->getField($field)->checkValue($title);
				}
			}
		}
		COverlayDialogElement::find()->one()->close();
	}

	public static function getWidgetData() {
		return [
			// #0
			[
				[
					'fields' => [
						'Name' => 'Problem display separated, with tags',
						'Problem display' => 'Separated'
					],
					'tags' => [
						[
							'tag' => 'tag1',
							'operator' => 'Exists'
						],
						[
							'tag' => 'tag2',
							'operator' => 'Equals',
							'value' => 'tag2'
						],
						[
							'tag' => 'кириллица, !@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ🍰',
							'operator' => 'Contains',
							'value' => 'кириллица, !@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ'
						],
						[
							'tag' => 'tag4',
							'operator' => 'Does not exist'
						],
						[
							'tag' => 'tag5',
							'operator' => 'Does not equal',
							'value' => 'value5'
						],
						[
							'tag' => 'tag6',
							'operator' => 'Does not contain',
							'value' => 'value6'
						]
					]
				]
			],
			// #1
			[
				[
					'fields' => [
						'Name' => 'Problem display unacknowledged only',
						'Problem display' => 'Unacknowledged only'
					]
				]
			],
			// #2
			[
				[
					'fields' => [
						'Name' => 'Show header is false',
						'Show header' => false,
						'Refresh interval' => 'No refresh'
					]
				]
			],
			// #3
			[
				[
					'fields' => [
						'Name' => 'Host group and exclude host group specified',
						'Refresh interval' => '10 seconds',
						'Host groups' => ['Zabbix servers'],
						'Exclude host groups' => ['Zabbix servers']
					]
				]
			],
			// #4
			[
				[
					'fields' => [
						'Name' => '   Trimming trailing and leading spaces   ',
						'Refresh interval' => '30 seconds',
						'Problem' => '    abcdefg    '
					],
					'tags' => [
						[
							'tag' => '     trimmed tag     ',
							'operator' => 'Equals',
							'value' => '    trimmed value     '
						]
					],
					'trim' => ['Name', 'Problem', 'tag', 'value']
				]
			],
			// #5
			[
				[
					'fields' => [
						'Name' => 'Cyrillic and other symbols',
						'Refresh interval' => '1 minute',
						'Hosts' => ['ЗАББИКС Сервер'],
						'Problem' => '🍰的是!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ🍰'
					],
					'tags' => [
						[
							'tag' => 'Сервер',
							'operator' => 'Equals',
							'value' => 'Сервер'
						],
						[
							'tag' => '的是',
							'operator' => 'Equals',
							'value' => '🍰!@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ🍰'
						]
					]
				]
			],
			// #6
			[
				[
					'fields' => [
						'Name' => 'Problem name and severity specified',
						'Refresh interval' => '2 minutes',
						'Problem' => 'Test trigger with tag',
						'Severity' => ['Information', 'Warning', 'Disaster']
					]
				]
			],
			// #7
			[
				[
					'fields' => [
						'Name' => 'Show suppressed, hide groups and problem display specified',
						'Refresh interval' => '10 minutes',
						'Show suppressed problems' => true,
						'Hide groups without problems' => true,
						'Problem display' => 'Unacknowledged only'
					]
				]
			],
			// #8
			[
				[
					'fields' => [
						'Name' => 'Multiple values for Host, Host groups, Exclude host groups',
						'Refresh interval' => '10 seconds',
						'Host groups' => ['Zabbix servers', 'Empty group'],
						'Exclude host groups' => ['Zabbix servers', 'Inheritance test'],
						'Hosts' => ['ЗАББИКС Сервер', 'Simple form test host']
					]
				]
			],
			// #9
			[
				[
					'fields' => [
						'Name' => 'Problem tags set to Or',
						'Problem tags' => 'Or'
					],
					'tags' => [
						[
							'tag' => 'tag1',
							'operator' => 'Exists'
						],
						[
							'tag' => 'tag2',
							'operator' => 'Equals',
							'value' => 'tag2'
						],
						[
							'tag' => 'кириллица, !@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ🍰',
							'operator' => 'Contains',
							'value' => 'кириллица, !@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ'
						],
						[
							'tag' => 'tag4',
							'operator' => 'Does not exist'
						],
						[
							'tag' => 'tag5',
							'operator' => 'Does not equal',
							'value' => 'value5'
						],
						[
							'tag' => 'tag6',
							'operator' => 'Does not contain',
							'value' => 'value6'
						]
					]
				]
			],
			// #10
			[
				[
					'fields' => [
						'Name' => 'Delete existing tag'
					],
					'tags' => []
				]
			],
			// #11
			[
				[
					'fields' => [
						'Name' => 'Host group data source override with widget'
					],
					'chevron' => [
						'field' => 'Host groups',
						'override_with' => 'Widget',
						'selection' => self::MAP_WIDGET
					]
				]
			],
			// #12
			[
				[
					'fields' => [
						'Name' => 'Hosts data source override with widget'
					],
					'chevron' => [
						'field' => 'Hosts',
						'override_with' => 'Widget',
						'selection' => self::MAP_WIDGET
					]
				]
			],
			// #13
			[
				[
					'fields' => [
						'Name' => 'Hosts data source override with dashboard'
					],
					'chevron' => [
						'field' => 'Hosts',
						'override_with' => 'Dashboard'
					]
				]
			]
		];
	}

	public static function getCreateDefaultData() {
		return [
			// #14 Submitting empty form with default values.
			[
				[
					'fields' => []
				]
			]
		];
	}

	/**
	 * @dataProvider getWidgetData
	 * @dataProvider getCreateDefaultData
	 */
	public function testDashboardProblemHostsWidget_Create($data) {
		$this->checkWidgetForm($data);
	}

	/**
	 * @dataProvider getWidgetData
	 */
	public function testDashboardProblemHostsWidget_Update($data) {
		$this->checkWidgetForm($data, true);
	}

	/**
	 * Function for checking Problem hosts widget form.
	 *
	 * @param array      $data      data provider
	 * @param boolean    $update    true if update scenario, false if create
	 */
	protected function checkWidgetForm($data, $update = false) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();


		// If scenario requires to trim trailing and leading spaces, remove them from data for comparison.
		if (CTestArrayHelper::get($data, 'trim')) {
			$data = CTestArrayHelper::trim($data);
		}

		// Add new widget or update existing widget.
		if ($update) {
			$header = (array_key_exists('Name', $data['fields'])) ? $data['fields']['Name'] : self::$default_widget;
			$form = $dashboard->getWidget(self::$default_widget)->edit();
			COverlayDialogElement::find()->waitUntilReady();

			$unfilled_fields = $form->getValues();
		}
		else {
			$header = (array_key_exists('Name', $data['fields'])) ? $data['fields']['Name'] : 'Problem hosts';
			$form = $dashboard->edit()->addWidget()->asForm();
			COverlayDialogElement::find()->waitUntilReady();
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Problem hosts')]);

			$unfilled_fields = [
				'Show header' => true,
				'Type' => 'Problem hosts',
				'Name' => '',
				'Refresh interval' => 'Default (1 minute)',
				'Host groups' => '',
				'Exclude host groups' => '',
				'Hosts' => '',
				'Problem' => '',
				'Severity' => [],
				'Problem tags' => 'And/Or',
				'Show suppressed problems' => false,
				'Hide groups without problems' => false,
				'Problem display' => 'All'
			];
		}

		$form->fill($data['fields']);

		if (array_key_exists('chevron', $data)) {
			$chevron = $form->getField($data['chevron']['field'])->query('class:zi-chevron-down-small')->one();

			$menu = $chevron->asPopupButton()->getMenu();
			$menu->select($data['chevron']['override_with']);

			if ($data['chevron']['override_with'] !== 'Dashboard') {
				$dialog = COverlayDialogElement::find()->all()->waitUntilReady()->last();
				$this->assertEquals($data['chevron']['override_with'], $dialog->getTitle());
				$dialog->query('link', $data['chevron']['selection'])->waitUntilClickable()->one()->click();
			}
		}

		if (array_key_exists('tags', $data)) {
			$tags_table = $form->getField('id:tags_table_tags')->asMultifieldTable();
			$tags_table->clear();
			$tags_table->fill($data['tags']);
		}

		$form->submit();

		$widget = $dashboard->getWidget($header);

		// Save dashboard, assert widget created by widget count.
		$dashboard->save()->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$this->assertEquals($old_widget_count + ($update ? 0 : 1), $dashboard->getWidgets()->count());

		// Write new name to updated widget name.
		if ($update) {
			self::$default_widget = $header;
		}

		// Edit widget, assert fields are the same.
		$saved_form = $widget->edit();
		$expected_fields = array_merge($unfilled_fields, $data['fields']);
		if (array_key_exists('chevron', $data)) {
			if (($data['chevron']['override_with'] !== 'Dashboard')) {
				$chevron_fields = [
					$data['chevron']['field'] => [$data['chevron']['selection']]
				];
			}
			else {
				$chevron_fields = [
					$data['chevron']['field'] => ['Dashboard']
				];
			}
			$expected_fields = array_merge($expected_fields, $chevron_fields);
		}
		$this->assertEquals($expected_fields, $saved_form->getFields()->asValues());

		if (!empty($data['tags'])) {
			// Function asValues() does not get tags from the form, so we need to check them separately.
			$this->query('id:tags_table_tags')->asMultifieldTable()->one()->checkValue($data['tags']);
		}

		// Close widget edit form and cancel editing.
		COverlayDialogElement::find()->one()->close();
		$dashboard->waitUntilReady()->cancelEditing();
		// TODO: unstable test on Jenkins, appears js error 34749:5 Uncaught
		$dashboard->waitUntilReady();
	}

	public function testDashboardProblemHostsWidget_SimpleUpdate() {
		$this->checkNoChanges();
	}

	public static function getCancelData() {
		return [
			// Cancel creating widget with saving the dashboard.
			[
				[
					'cancel_form' => true,
					'create_widget' => true,
					'save_dashboard' => true
				]
			],
			// Cancel updating widget with saving the dashboard.
			[
				[
					'cancel_form' => true,
					'create_widget' => false,
					'save_dashboard' => true
				]
			],
			// Create widget without saving the dashboard.
			[
				[
					'cancel_form' => false,
					'create_widget' => true,
					'save_dashboard' => false
				]
			],
			// Update widget without saving the dashboard.
			[
				[
					'cancel_form' => false,
					'create_widget' => false,
					'save_dashboard' => false
				]
			]
		];
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testDashboardProblemHostsWidget_Cancel($data){
		$this->checkNoChanges(CTestArrayHelper::get($data, 'cancel_form'), $data['create_widget'], $data['save_dashboard']);
	}

	/**
	 * Check create and update form cancellation and form submission without applying any changes.
	 *
	 * @param boolean $cancel			true if cancel create/update form, false if form is submitted
	 * @param boolean $create			true if create scenario, false if update
	 * @param boolean $save_dashboard	true if dashboard will be saved, false if not
	 */
	protected function checkNoChanges($cancel = false, $create = false, $save_dashboard = true) {
		$old_hash = CDBHelper::getHash(self::SQL);
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		$form = $create
			? $dashboard->edit()->addWidget()->asForm()
			: $dashboard->getWidget(self::$default_widget)->edit();

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();

		if ($create) {
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Problem hosts')]);
		}
		else {
			$values = $form->getFields()->filter(CElementFilter::VISIBLE)->asValues();
		}

		if ($cancel || !$save_dashboard) {
			$form->fill([
				'Name' => 'No save',
				'Refresh interval' => '10 seconds',
				'Host groups' => 'Empty group',
				'Exclude host groups' => 'Group to copy graph',
				'Hosts' => 'Available host',
				'Problem' => 'Test problem',
				'id:severities_3' => true,
				'Show suppressed problems' => true,
				'Problem display' => 'Unacknowledged only'
			]);
			$tags_table = $form->getField('id:tags_table_tags')->asMultifieldTable();
			$tags_table->clear();
			$tags_table->fill([
				'tag' => 'tag',
				'operator' => 'Does not equal',
				'value' => 'value'
			]);
		}

		if ($cancel) {
			$dialog->close();
		}
		else {
			$form->submit();
			COverlayDialogElement::ensureNotPresent();
			$this->assertTrue($dashboard->getWidget(!$save_dashboard ? 'No save' : self::$default_widget)->isPresent());
		}

		if ($save_dashboard) {
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		}
		else {
			$dashboard->cancelEditing();
		}
		$this->assertEquals($old_widget_count, $dashboard->getWidgets()->count());

		// Check that updating widget form values did not change in frontend.
		if (!$create) {
			$this->assertEquals($values, $dashboard->getWidget(self::$default_widget)->edit()->getFields()->asValues());

			COverlayDialogElement::find()->one()->close();
		}

		// Check that DB hash is not changed.
		$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
	}

	public function testDashboardProblemHostsWidget_Delete() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$this->assertTrue($dashboard->edit()->getWidget(self::DELETE_WIDGET)->isEditable());
		$dashboard->deleteWidget(self::DELETE_WIDGET);
		$dashboard->save();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Check that widget is not present on dashboard and in DB.
		$this->assertFalse($dashboard->getWidget(self::DELETE_WIDGET, false)->isValid());
		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM widget_field wf LEFT JOIN widget w'.
				' ON w.widgetid=wf.widgetid WHERE w.name='.zbx_dbstr(self::DELETE_WIDGET)
		));
	}
}
