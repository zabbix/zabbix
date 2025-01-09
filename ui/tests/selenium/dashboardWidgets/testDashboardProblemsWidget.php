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
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * @backup config, widget
 *
 * @onBefore prepareDashboardData
 */
class testDashboardProblemsWidget extends CWebTest {

	private static $dashboardid;
	private static $update_widget = 'Problem widget for updating';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	/**
	 * SQL query to get widget and widget_field tables to compare hash values, but without widget_fieldid
	 * because it can change.
	 */
	private $sql = 'SELECT wf.widgetid, wf.type, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
			' wf.value_itemid, wf.value_graphid, wf.value_sysmapid, w.widgetid, w.dashboard_pageid, w.type, w.name, w.x, w.y,'.
			' w.width, w.height'.
			' FROM widget_field wf'.
			' INNER JOIN widget w'.
			' ON w.widgetid=wf.widgetid ORDER BY wf.widgetid, wf.name, wf.value_int, wf.value_str, wf.value_groupid,'.
			' wf.value_itemid, wf.value_graphid, wf.value_hostid';

	public function prepareDashboardData() {
		$response = CDataHelper::call('dashboard.create', [
			'name' => 'Problem widget dashboard',
			'auto_start' => 0,
			'pages' => [
				[
					'name' => 'First Page',
					'display_period' => 3600,
					'widgets' => [
						[
							'type' => 'problems',
							'name' => 'Problem widget for updating',
							'x' => 0,
							'y' => 6,
							'width' => 37,
							'height' => 4,
							'view_mode' => 0,
							'fields' => [
								['type' => 0, 'name' => 'severities', 'value' => 0],
								['type' => 0, 'name' => 'severities', 'value' => 4],
								['type' => 0, 'name' => 'severities', 'value' => 2],
								['type' => 0, 'name' => 'evaltype', 'value' => 2],
								['type' => 0, 'name' => 'rf_rate', 'value' => '900'],
								['type' => 0, 'name' => 'show', 'value' => 3],
								['type' => 0, 'name' => 'show_lines', 'value' => 12],
								['type' => 0, 'name' => 'show_opdata', 'value' => 1],
								['type' => 0, 'name' => 'show_suppressed', 'value' => 1],
								['type' => 0, 'name' => 'show_tags', 'value' => 2],
								['type' => 0, 'name' => 'sort_triggers', 'value' => 15],
								['type' => 0, 'name' => 'show_timeline', 'value' => 0],
								['type' => 0, 'name' => 'tag_name_format', 'value' => 1],
								['type' => 0, 'name' => 'tags.0.operator', 'value' => 1],
								['type' => 0, 'name' => 'tags.1.operator', 'value' => 1],
								['type' => 0, 'name' => 'acknowledgement_status', 'value' => 1],
								['type' => 1, 'name' => 'problem', 'value' => 'test2'],
								['type' => 1, 'name' => 'tags.0.value', 'value' => '2'],
								['type' => 1, 'name' => 'tags.1.value', 'value' => '33'],
								['type' => 1, 'name' => 'tag_priority', 'value' => '1,2'],
								['type' => 1, 'name' => 'tags.0.tag', 'value' => 'tag2'],
								['type' => 1, 'name' => 'tags.1.tag', 'value' => 'tagg33'],
								['type' => 2, 'name' => 'exclude_groupids', 'value' => 50014],
								['type' => 2, 'name' => 'groupids', 'value' => 50005],
								['type' => 3, 'name' => 'hostids', 'value' => 99026]
							]
						],
						[
							'type' => 'problems',
							'name' => 'Problem widget for delete',
							'x' => 0,
							'y' => 0,
							'width' => 44,
							'height' => 6,
							'view_mode' => 0
						]
					]
				]
			]
		]);

		$this->assertArrayHasKey('dashboardids', $response);
		self::$dashboardid = $response['dashboardids'][0];
	}

	public function testDashboardProblemsWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dialog =  CDashboardElement::find()->one()->edit()->addWidget();
		$form = $dialog->asForm();

		$this->assertEquals('Add widget', $dialog->getTitle());
		$form->fill(['Type' => 'Problems']);
		$dialog->waitUntilReady();

		$this->assertEquals(['Type', 'Show header', 'Name', 'Refresh interval', 'Show', 'Host groups',
				'Exclude host groups', 'Hosts', 'Problem', 'Severity', 'Problem tags', 'Show tags', 'Tag name',
				'Tag display priority', 'Show operational data', 'Show symptoms', 'Show suppressed problems',
				'Acknowledgement status', 'Sort entries by', 'Show timeline', 'Show lines'],
				$form->getLabels()->asText()
		);

		// Check default fields.
		$fields = [
			'Name' => ['value' => '', 'placeholder' => 'default', 'maxlength' => 255, 'enabled' => true],
			'Refresh interval' => ['value' => 'Default (1 minute)', 'enabled' => true],
			'id:show_header' => ['value' => true, 'enabled' => true],
			'Show' => ['value' => 'Recent problems', 'enabled' => true],
			'Host groups' => ['value' => '', 'enabled' => true],
			'Exclude host groups' => ['value' => '', 'enabled' => true],
			'Hosts' => ['value' => '', 'enabled' => true],
			'Problem' => ['value' => '', 'maxlength' => 2048, 'enabled' => true],

			// Severity checkboxes.
			'id:severities_0' => ['value' => false, 'enabled' => true],
			'id:severities_1' => ['value' => false, 'enabled' => true],
			'id:severities_2' => ['value' => false, 'enabled' => true],
			'id:severities_3' => ['value' => false, 'enabled' => true],
			'id:severities_4' => ['value' => false, 'enabled' => true],
			'id:severities_5' => ['value' => false, 'enabled' => true],

			// Tags table.
			'id:evaltype' => ['value' => 'And/Or', 'enabled' => true],
			'id:tags_0_tag' => ['value' => '', 'placeholder' => 'tag', 'enabled' => true, 'maxlength' => 255],
			'id:tags_0_operator' => ['value' => 'Contains', 'enabled' => true],
			'id:tags_0_value' => ['value' => '', 'placeholder' => 'value', 'enabled' => true, 'maxlength' => 255],

			'Show tags' => ['value' => 'None', 'enabled' => true],
			'Tag name' => ['value' => 'Full', 'enabled' => false],
			'Tag display priority' => ['value' => '', 'placeholder' => 'comma-separated list', 'enabled' => false, 'maxlength' => 2048],
			'Show operational data' => ['value' => 'None', 'enabled' => true],
			'Show symptoms' => ['value' => false, 'enabled' => true],
			'Show suppressed problems' => ['value' => false, 'enabled' => true],
			'id:acknowledgement_status' => ['value' => 'All', 'enabled' => true],
			'id:acknowledged_by_me' => ['value' => false, 'enabled' => false],
			'Sort entries by' => ['value' => 'Time (descending)', 'enabled' => true],
			'Show timeline' => ['value' => true, 'enabled' => true],
			'Show lines' => ['value' => 25, 'enabled' => true, 'maxlength' => 4]
		];

		foreach ($fields as $field => $attributes) {
			$this->assertTrue($form->getField($field)->isVisible());
			$this->assertEquals($attributes['value'], $form->getField($field)->getValue());
			$this->assertTrue($form->getField($field)->isEnabled($attributes['enabled']));

			if (array_key_exists('maxlength', $attributes)) {
				$this->assertEquals($attributes['maxlength'], $form->getField($field)->getAttribute('maxlength'));
			}

			if (array_key_exists('placeholder', $attributes)) {
				$this->assertEquals($attributes['placeholder'], $form->getField($field)->getAttribute('placeholder'));
			}
		}

		$this->assertEquals(['Show lines'], $form->getRequiredLabels());

		// Check dropdowns options presence.
		$dropdowns = [
			'Refresh interval' => ['Default (1 minute)', 'No refresh', '10 seconds', '30 seconds', '1 minute', '2 minutes',
					'10 minutes', '15 minutes'
			],
			'id:tags_0_operator' => ['Exists', 'Equals', 'Contains', 'Does not exist', 'Does not equal', 'Does not contain'],
			'Sort entries by' => ['Time (descending)', 'Time (ascending)', 'Severity (descending)', 'Severity (ascending)',
					'Problem (descending)', 'Problem (ascending)', 'Host (descending)', 'Host (ascending)'
			]
		];

		foreach ($dropdowns as $dropdown => $labels) {
			$this->assertEquals($labels, $form->getField($dropdown)->asDropdown()->getOptions()->asText());
		}

		// Check severities fields.
		$severities = ['Not classified', 'Information', 'Warning', 'Average', 'High', 'Disaster'];

		foreach ($severities as $id => $label) {
			$this->assertTrue($form->getField('Severity')->query("xpath:.//label[text()=".
					CXPathHelper::escapeQuotes($label)."]/../input[@id='severities_".$id."']")->exists()
			);
		}

		// Check segmented radiobuttons labels.
		$radios = [
			'Show' => ['Recent problems', 'Problems', 'History'],
			'Problem tags' => ['And/Or', 'Or'],
			'Show tags' => ['None', '1', '2', '3'],
			'Tag name' => ['Full', 'Shortened', 'None'],
			'Show operational data' => ['None', 'Separately', 'With problem name'],
			'Acknowledgement status' => ['All', 'Unacknowledged', 'Acknowledged']
		];

		foreach ($radios as $radio => $labels) {
			$this->assertEquals($labels, $form->getField($radio)->asSegmentedRadio()->getLabels()->asText());
		}

		// Check Tag display options editability.
		foreach ([1 => true, 2 => true, 3 => true, 'None' => false] as $value => $status) {
			$form->getField('Show tags')->asSegmentedRadio()->select($value);
			$this->assertTrue($form->getField('Tag name')->isEnabled($status));
			$this->assertTrue($form->getField('Tag display priority')->isEnabled($status));
		}

		// Check Acknowledgement status fields dependency.
		foreach (['All' => false, 'Unacknowledged' => false, 'Acknowledged' => true] as $label => $status) {
			$form->fill(['Acknowledgement status' => $label]);
			$this->assertTrue($form->getField('id:acknowledged_by_me')->isEnabled($status));
		}

		// Check Show timeline checkbox editability.
		$sort_timeline_statuses = [
			'Time (descending)' => true,
			'Time (ascending)' => true,
			'Severity (descending)' => false,
			'Severity (ascending)' => false,
			'Problem (descending)' => false,
			'Problem (ascending)' => false,
			'Host (descending)' => false,
			'Host (ascending)' => false
		];

		$timeline_field = $form->getField('Show timeline');

		$dropdown = $form->getField('Sort entries by')->asDropdown();
		foreach ($sort_timeline_statuses as $entry => $timeline_status) {
			$dropdown->select($entry);
			$this->assertTrue($timeline_field->isEnabled($timeline_status));
			$this->assertTrue($timeline_field->isChecked($timeline_status));
		}

		$dialog->close();
	}

	public static function getCommonData() {
		return [
			// #0 Widget with empty 'Show lines' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Show lines' => ''
					],
					'error' => 'Invalid parameter "Show lines": value must be one of 1-1000.'
				]
			],
			// #1 Widget with zero in 'Show lines' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Show lines' => 0
					],
					'error' => 'Invalid parameter "Show lines": value must be one of 1-1000.'
				]
			],
			// #2 Widget with 999 in 'Show lines' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Show lines' => 9999
					],
					'error' => 'Invalid parameter "Show lines": value must be one of 1-1000.'
				]
			],
			// #3 Widget with text in 'Show lines' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Show lines' => 'test'
					],
					'error' => 'Invalid parameter "Show lines": value must be one of 1-1000.'
				]
			],
			// #4 Widget with negative number in 'Show lines' field.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Show lines' => '-1'
					],
					'error' => 'Invalid parameter "Show lines": value must be one of 1-1000.'
				]
			],
			// #5 Widget with tags.
			[
				[
					'fields' => [
						'id:show_header' => true,
						'Name' => 'Test All fields filled',
						'Refresh interval' => '10 seconds',
						'Show' => 'Problems',
						'Host groups' =>  'Another group to check Overview',
						'Exclude host groups' => 'Group to copy graph',
						'Hosts' =>  'ЗАББИКС Сервер',
						'Problem' => 'New problem for testing',
						'id:severities_0' => true,
						'id:severities_1' => true,
						'id:severities_2' => true,
						'id:severities_3' => true,
						'id:severities_4' => true,
						'id:severities_5' => true,
						'Problem tags' => 'Or',
						'Show tags' => 1,
						'Tag name' => 'Shortened',
						'Tag display priority' => 'tag, tag2, tag4',
						'Show operational data' => 'Separately',
						'Show suppressed problems' => true,
						'Acknowledgement status' => 'Unacknowledged',
						'Sort entries by' => 'Severity (ascending)'
					],
					'tag_fields' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => '!@#$%^&*()_+<>,.\/',
							'operator' => 'Equals',
							'value' => '!@#$%^&*()_+<>,.\/'
						],
						[
							'tag' => 'tag1',
							'operator' => 'Contains',
							'value' => 'value1'
						],
						[
							'tag' => 'tag2',
							'operator' => 'Exists'
						],
						[
							'tag' => 'tag3',
							'operator' => 'Does not exist'
						],
						[
							'tag' => '{$MACRO:A}',
							'operator' => 'Does not equal',
							'value' => '{$MACRO:A}'
						],
						[
							'tag' => '{$MACRO}',
							'operator' => 'Does not contain',
							'value' => '{$MACRO}'
						],
						[
							'tag' => 'Таг',
							'value' => 'Значение'
						]
					]
				]
			],
			// #6 Widget with other form settings.
			[
				[
					'fields' => [
						'Name' => 'Other Settings Options',
						'Refresh interval' => 'No refresh',
						'Show' => 'History',
						'id:severities_0' => true,
						'id:severities_1' => false,
						'id:severities_2' => true,
						'id:severities_3' => false,
						'id:severities_4' => true,
						'id:severities_5' => false,
						'Show tags' => 2,
						'Tag name' => 'None',
						'Show operational data' => 'With problem name',
						'Show symptoms' => true,
						'Show suppressed problems' => true,
						'Acknowledgement status' => 'Unacknowledged',
						'Sort entries by' => 'Problem (ascending)'
					]
				]
			],
			// #7 Widget with random severities.
			[
				[
					'fields' => [
						'Name' => 'Random Severities',
						'Show tags' => 3,
						'id:severities_0' => false,
						'id:severities_1' => true,
						'id:severities_2' => false,
						'id:severities_3' => true,
						'id:severities_4' => true,
						'id:severities_5' => false,
						'Sort entries by' => 'Host (ascending)'
					]
				]
			],
			// #8 Widget with all fields set to minimal.
			[
				[
					'clear_tag_priority' => true,
					'fields' => [
						'id:show_header' => false,
						'Name' => 'Minimal',
						'Refresh interval' => 'Default (1 minute)',
						'Show' => 'Recent problems',
						'Host groups' => '',
						'Exclude host groups' => '',
						'Hosts' => '',
						'Problem' => '',
						'id:severities_0' => false,
						'id:severities_1' => false,
						'id:severities_2' => false,
						'id:severities_3' => false,
						'id:severities_4' => false,
						'id:severities_5' => false,
						'Tag name' => 'Full',
						'Show tags' => 'None',
						'Show operational data' => 'None',
						'Show suppressed problems' => false,
						'Acknowledgement status' => 'All',
						'Sort entries by' => 'Time (descending)',
						'Show timeline' => false,
						'Show lines' => 1
					],
					'tag_fields' => []
				]
			],
			// #9 Cyrillyc and special symbols in inputs.
			[
				[
					'fields' => [
						'Name' => 'кириллица, !@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
						'Problem' => 'кириллица, !@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ',
						'Show tags' => 3,
						'Tag display priority' => 'кириллица, !@#$%^&*()_+-=[]{};:"|,./<>?Ž©µÆ'
					]
				]
			],
			// #10 Widget with leading and trailing spaces in inputs.
			[
				[
					'trim' => true,
					'fields' => [
						'Name' => '                     leading.trailing                ',
						'Problem' => '               leading.trailing                ',
						'Show tags' => 3,
						'Tag display priority' => '            leading.trailing                   '
					]
				]
			],
			// #11 Widget with several groups and hosts in corresponding fields.
			[
				[
					'fields' => [
						'Name' => 'Array of groups',
						'Host groups' => [ 'Group to check Overview',  'Zabbix servers'],
						'Exclude host groups' => ['Group to copy all graph', 'Inheritance test'],
						'Hosts' => ['Host to check graph 1', 'Host for triggers filtering']
					]
				]
			]
		];
	}

	public static function getCreateDefaultData() {
		return [
			[
				[
					'fields' => []
				]
			]
		];
	}

	/**
	 * @backupOnce widget
	 *
	 * @dataProvider getCreateDefaultData
	 * @dataProvider getCommonData
	 */
	public function testDashboardProblemsWidget_Create($data) {
		$this->checkFormProblemsWidget($data);
	}

	/**
	 * @dataProvider getCommonData
	 */
	public function testDashboardProblemsWidget_Update($data) {
		$this->checkFormProblemsWidget($data, true);
	}

	/**
	 * Function for checking Problems widget form.
	 *
	 * @param array      $data      data provider
	 * @param boolean    $update    true if update scenario, false if create
	 */
	public function checkFormProblemsWidget($data, $update = false) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$old_hash = CDBHelper::getHash($this->sql);
		}

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		$form = $update
			? $dashboard->getWidget(self::$update_widget)->edit()
			: $dashboard->edit()->addWidget()->asForm();

		COverlayDialogElement::find()->one()->waitUntilReady();

		if (!$update) {
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Problems')]);
		}
		elseif (CTestArrayHelper::get($data, 'clear_tag_priority', false)) {
			$form->fill(['Show tags' => 1, 'Tag display priority' => '']);
		}

		$form->fill($data['fields']);

		if (array_key_exists('show_header', $data)) {
			$form->getField('id:show_header')->fill($data['show_header']);
		}

		if (array_key_exists('tag_fields', $data)) {
			$tags_table = $form->getField('id:tags_table_tags')->asMultifieldTable();

			if (empty($data['tag_fields'])) {
				$tags_table->clear();
			}
			else {
				if ($update) {
					/**
					 * The Widget for update already has 2 tags, so we need to update them. The first tag has action
					 * and index in data provider, so we add them to the 2nd tag.
					 */
					$data['tag_fields'][1]['action'] = USER_ACTION_UPDATE;
					$data['tag_fields'][1]['index'] = 1;
				}

				$tags_table->fill($data['tag_fields']);
			}
		}

		if (!CTestArrayHelper::get($data, 'expected')) {
			$values = $form->getFields()->asValues();
		}

		$form->submit();

		if (CTestArrayHelper::get($data, 'trim')) {
			$data['fields'] = array_map('trim', $data['fields']);
		}

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, null, $data['error']);

			// Check that DB hash is not changed.
			$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
		}
		else {
			COverlayDialogElement::ensureNotPresent();

			/**
			 *  When name is absent in create scenario it remains default: "Problems",
			 *  if name is absent in update scenario then previous name remains.
			 *  If name is empty string in both scenarios it is replaced by "Problems".
			 */
			if (array_key_exists('Name', $data['fields'])) {
				$header = ($data['fields']['Name'] === '')
					? 'Problems'
					: $data['fields']['Name'];
			}
			else {
				$header = $update ? self::$update_widget : 'Problems';
			}

			$dashboard->getWidget($header);
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');
			$this->assertEquals($old_widget_count + ($update ? 0 : 1), $dashboard->getWidgets()->count());
			$saved_form = $dashboard->getWidget($header)->edit();

			// Write new name to updated widget name.
			if ($update) {
				self::$update_widget = $header;
			}

			// If tags table has been cleared, after form saving there is one empty tag field.
			if (array_key_exists('tag_fields', $data)) {
				if ($data['tag_fields'] === []) {
					$data['tag_fields'] = [['tag' => '', 'operator' => 'Contains', 'value' => '']];
				}

				// Remove 'action' and 'index' fields from tags for comparison.
				$expected = $data['tag_fields'];
				foreach ($expected as &$tag) {
					unset($tag['action'], $tag['index']);
				}
				unset($tag);

				// Function asValues() does not get tags from the form, so we need to check them separately.
				$this->query('id:tags_table_tags')->asMultifieldTable()->one()->checkValue($expected);
			}

			// Check widget form fields and values in frontend.
			$this->assertEquals($values, $saved_form->getFields()->asValues());

			if (array_key_exists('show_header', $data)) {
				$saved_form->checkValue(['id:show_header' => $data['show_header']]);
			}

			// Check that widget is saved in DB.
			$this->assertEquals(1, CDBHelper::getCount('SELECT * FROM widget w'.
					' WHERE EXISTS ('.
						'SELECT NULL'.
						' FROM dashboard_page dp'.
						' WHERE w.dashboard_pageid=dp.dashboard_pageid'.
							' AND dp.dashboardid='.self::$dashboardid.
							' AND w.name ='.zbx_dbstr(CTestArrayHelper::get($data['fields'], 'Name', '')).')'
			));
		}

		COverlayDialogElement::find()->one()->close();
	}

	public function testDashboardProblemsWidget_SimpleUpdate() {
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
	public function testDashboardProblemsWidget_Cancel($data) {
		$this->checkNoChanges($data['cancel_form'], $data['create_widget'], $data['save_dashboard']);
	}

	/**
	 * Function for checking cancelling form or submitting without any changes.
	 *
	 * @param boolean $cancel			true if cancel scenario, false if form is submitted
	 * @param boolean $create			true if create scenario, false if update
	 * @param boolean $save_dashboard	true if dashboard will be saved, false if not
	 */
	private function checkNoChanges($cancel = false, $create = false, $save_dashboard = true) {
		$old_hash = CDBHelper::getHash($this->sql);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		$form = $create
			? $dashboard->edit()->addWidget()->asForm()
			: $dashboard->getWidget(self::$update_widget)->edit();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();

		if ($create) {
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Problems')]);
		}
		else {
			$values = $form->getFields()->asValues();
		}

		if ($cancel || !$save_dashboard) {
			$form->fill([
					'Name' => 'new name',
					'Refresh interval' => '10 minutes',
					'Host groups' => 'Empty group',
					'Show' => 'Problems',
					'Exclude host groups' => 'Group to copy graph',
					'Hosts' => 'Available host',
					'Problem' => 'Test problem',
					'id:severities_3' => true,
					'Show tags' => 2,
					'Tag name' => 'None',
					'Tag display priority' => 'one, two, four',
					'Show operational data' => 'With problem name',
					'Show suppressed problems' => true,
					'Sort entries by' => 'Time (descending)',
					'Show timeline' => false,
					'Show lines' => 99
			]);

			$form->getField('id:evaltype')->fill('Or');
			$form->getField('id:tags_table_tags')->asMultifieldTable()->fill([
					[
						'action' => USER_ACTION_UPDATE,
						'index' => 0,
						'tag' => 'new tag',
						'operator' => 'Does not equal',
						'value' => 'new value'
					]
			]);
		}

		if ($cancel) {
			$dialog->query('button:Cancel')->one()->click();
		}
		else {
			$form->submit();
		}

		COverlayDialogElement::ensureNotPresent();

		if (!$cancel) {
			$this->assertTrue($dashboard->getWidget(!$save_dashboard ? 'new name' : self::$update_widget)->isPresent());
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
		if (!$create && !$save_dashboard) {
			$this->assertEquals($values, $dashboard->getWidget(self::$update_widget)->edit()->getFields()->asValues());

			COverlayDialogElement::find()->one()->close();
		}

		// Check that DB hash is not changed.
		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
	}

	public function testDashboardProblemsWidget_Delete() {
		$name = 'Problem widget for delete';

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$this->assertTrue($dashboard->edit()->getWidget($name)->isEditable());
		$dashboard->deleteWidget($name);
		$dashboard->save();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Check that widget is not present on dashboard and in DB.
		$this->assertFalse($dashboard->getWidget($name, false)->isValid());
		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM widget_field wf'.
				' LEFT JOIN widget w'.
					' ON w.widgetid=wf.widgetid'.
					' WHERE w.name='.zbx_dbstr($name)
		));
	}
}
