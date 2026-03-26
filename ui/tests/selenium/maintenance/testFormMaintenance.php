<?php
/*
** Copyright (C) 2001-2026 Zabbix SIA
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

require_once __DIR__.'/../../include/CLegacyWebTest.php';
require_once __DIR__.'/../behaviors/CMessageBehavior.php';
require_once __DIR__.'/../behaviors/CTableBehavior.php';

/**
 * @backup maintenances
 *
 * @onBefore prepareMaintenanceData
 */
class testFormMaintenance extends CLegacyWebTest {

	/**
	 * Attach MessageBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTableBehavior::class
		];
	}

	const MAINTENANCE_NAME = 'Test maintenance';
	const PERIODS_TABLE = 'id:timeperiods';

	public function prepareMaintenanceData() {
		CDataHelper::call('maintenance.create', [
			[
				'name' => self::MAINTENANCE_NAME,
				'maintenance_type' => MAINTENANCE_TYPE_NORMAL,
				'active_since' => 1681765260, // 18.04.2023 00:01
				'active_till' => 1899410340, // 10.03.2030 23:59
				'description' => 'Test description update',
				'groups' => [['groupid' => 4]], // Zabbix servers.
				'tags_evaltype' => MAINTENANCE_TAG_EVAL_TYPE_OR,
				'tags' => [
					['tag' => 'Tag1', 'operator' => MAINTENANCE_TAG_OPERATOR_LIKE, 'value' => 'A'],
					['tag' => 'Tag2', 'operator' => MAINTENANCE_TAG_OPERATOR_EQUAL, 'value' => 'B']
				],
				'timeperiods' => [
					[
						'timeperiod_type' => TIMEPERIOD_TYPE_ONETIME,
						'start_date' => 1774013400, // 20.03.2026.
						'period' => 5400
					],
					[
						'timeperiod_type' => TIMEPERIOD_TYPE_DAILY,
						'start_time' => 50400, // 14:00
						'every' => 3,
						'period' => 300
					],
					[
						'timeperiod_type' => TIMEPERIOD_TYPE_WEEKLY,
						'start_time' => 3600, // 01:00
						'every' => 2,
						'dayofweek' => 1 + 64, // Monday + Sunday.
						'period' => 7200
					],
					[
						'timeperiod_type' => TIMEPERIOD_TYPE_MONTHLY,
						'start_time' => 82800, // 23:00
						'day' => 15,
						'month' => 1 + 1024, // January + November.
						'period' => 86399940
					]
				]

			]
		]);
	}

	public function testFormMaintenance_Layout() {
		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();
		$this->query('button:Create maintenance period')->one()->waitUntilClickable()->click();

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->asForm();

		$today = date('Y-m-d');
		$tomorrow = date('Y-m-d', strtotime('+1 day'));

		// Check default state.
		$default_state = [
			'Name' => '',
			'Maintenance type' => 'With data collection',
			'Active since' => $today.' 00:00',
			'Active till' => $tomorrow.' 00:00',
			'Periods' => [],
			'Host groups' => '',
			'Hosts' => '',
			'id:tags_evaltype' => 'And/Or',
			'id:tags_0_tag' => '',
			'id:tags_0_operator_0' => 'Contains',
			'id:tags_0_value' => '',
			'Description' => ''
		];

		$form->checkValue($default_state);

		// Check asterisk in required field labels.
		$this->assertEquals(['Name', 'Active since', 'Active till', 'Periods'], $form->getRequiredLabels());
		$this->assertTrue($this->query('xpath://label[contains(@class,"form-label-asterisk") and contains(text(),
			"At least one host group or host must be selected.")]')->exists()
		);

		$check_fields = [
			'Name' => ['maxlength' => 128],
			'id:active_since' => ['maxlength' => 255, 'placeholder' => 'YYYY-MM-DD hh:mm'],
			'id:active_till' => ['maxlength' => 255, 'placeholder' => 'YYYY-MM-DD hh:mm'],
			'id:groupids__ms' => ['placeholder' => 'type here to search'],
			'id:hostids__ms' => ['placeholder' => 'type here to search'],
			'id:tags_0_tag' => ['maxlength' => 255, 'placeholder' => 'tag'],
			'id:tags_0_value' => ['maxlength' => 255, 'placeholder' => 'value'],
			'Description' => ['maxlength' => 65535],
		];
		foreach ($check_fields as $field => $attributes) {
			foreach ($attributes as $attribute => $value) {
				$this->assertEquals($value, $form->getField($field)->getAttribute($attribute));
			}
		}

		// Check Periods table.
		$periods_table = $this->query(self::PERIODS_TABLE)->asTable()->one();
		$this->assertEquals(['Period type', 'Schedule', 'Period', 'Action'], $periods_table->getHeadersText());
		$this->assertEquals(0, $periods_table->getRows()->count());

		// Check radio buttons.
		$radio_buttons = [
			'Maintenance type' => ['With data collection', 'No data collection'],
			'id:tags_evaltype' => ['And/Or', 'Or'],
			'id:tags_0_operator' => ['Contains', 'Equals']
		];
		foreach ($radio_buttons as $name => $labels) {
			$this->assertEquals($labels, $form->getField($name)->getLabels()->asText());
		}

		// Check Periods and Tags table buttons.
		$this->assertEquals(1, $form->getField('Periods')->query('button:Add')->all()->filter((CElementFilter::CLICKABLE))->count());
		$this->assertEquals(2, $form->query('id:tags')->one()->query('button', ['Add', 'Remove'])->all()
				->filter((CElementFilter::CLICKABLE))->count()
		);

		// Check calendar buttons.
		foreach (['active_since_calendar', 'active_till_calendar'] as $button) {
			$this->assertTrue($form->query('id', $button)->one()->isClickable());
		}

		// Check Select buttons and dialogs they open.
		foreach (['Host groups', 'Hosts'] as $field) {
			$this->assertTrue($form->getField($field)->query('button:Select')->one()->isClickable());
			$form->getField($field)->query('button:Select')->one()->click();

			$list = COverlayDialogElement::find()->waitUntilReady()->all()->last();
			$this->assertEquals($field, $list->getTitle());
			$this->assertEquals(['Select', 'Cancel'], $list->getFooter()->query('button')->all()
				->filter(CElementFilter::CLICKABLE)->asText()
			);
			$list->close();
		}

		// Check footer buttons.
		$this->assertEquals(['Add', 'Cancel'], $dialog->getFooter()->query('button')->all()
				->filter(CElementFilter::CLICKABLE)->asText()
		);
	}

	public function testFormMaintenance_PeriodFormLayout() {
		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();
		$this->query('button:Create maintenance period')->one()->waitUntilClickable()->click();

		$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();

		$datetime_now = date('Y-m-d H:i');
		$datetime_plus_one_minute = date('Y-m-d H:i', strtotime($datetime_now . ' +1 minute'));

		$form->getField('Periods')->query('button:Add')->one()->waitUntilClickable()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->all()->last();
		$period_overlay = $dialog->asForm();

		// Check default state.
		$default_state = [
			'Period type' => 'One time only',
			'id:period_days' => '0',
			'name:period_hours' => '1',
			'name:period_minutes' => '0'
		];

		$period_overlay->checkValue($default_state);
		$this->assertTrue($period_overlay->checkValue(['Date' => $datetime_now], false)
				|| $period_overlay->checkValue(['Date' => $datetime_plus_one_minute], false));

		// Check asterisk in required field labels.
		$this->assertEquals(['Date', 'Maintenance period length'], $period_overlay->getRequiredLabels());

		$check_fields = [
			'id:start_date' => ['maxlength' => 255, 'placeholder' => 'YYYY-MM-DD hh:mm'],
			'id:period_days' => ['maxlength' => 3]
		];
		foreach ($check_fields as $field => $attributes) {
			foreach ($attributes as $attribute => $value) {
				$this->assertEquals($value, $period_overlay->getField($field)->getAttribute($attribute));
			}
		}

		// Check calendar button.
		$this->assertTrue($period_overlay->query('id:start_date_calendar')->one()->isClickable());

		// Check dropdown options.
		$options = [
			'Period type' => ['One time only', 'Daily', 'Weekly', 'Monthly'],
			'name:period_hours' => array_map('strval', range(0, 23)),
			'name:period_minutes' => array_map('strval', range(0, 59))

		];
		foreach ($options as $field => $values) {
			$this->assertEquals($values, $period_overlay->getField($field)->asDropdown()->getOptions()->asText());
		}

		// Check footer buttons.
		$this->assertEquals(['Add', 'Cancel'], $dialog->getFooter()->query('button')->all()
				->filter(CElementFilter::CLICKABLE)->asText()
		);

		// Check screenshots.
		$periods = [
			'One time only',
			'Daily',
			'Weekly',
			'Monthly',
			'Monthly with Day of week period'
		];

		foreach ($periods as $period_type) {
			if ($period_type === 'Monthly with Day of week period') {
				$period_overlay->asForm()->fill(['Period type' => 'Monthly', 'Date' => 'Day of week']);
			}
			else {
				$period_overlay->asForm()->fill(['Period type' => $period_type]);
			}

			$period_overlay->waitUntilReady();
			$this->page->removeFocus();

			// Remove Add and Cancel buttons edge curling from screenshots as their rendering is unstable.
			$dialog_footer = $period_overlay->getFooter();
			foreach (['Add', 'Cancel'] as $button) {
				$this->page->getDriver()->executeScript('arguments[0].style.borderRadius=0;',
					[$dialog_footer->query('button', $button)->one()]
				);
			}

			if ($period_type === 'One time only') {
				$this->assertScreenshotExcept($period_overlay, [$period_overlay->query('id:start_date')->one()],
						$period_type
				);
			}
			else {
				$this->assertScreenshot($period_overlay, $period_type);
			}
		}

		COverlayDialogElement::closeAll();
	}

	public static function getCancelData() {
		return [
			// #0 Cancel create.
			[
				[
					'action' => 'create',
					'title' => 'create'
				]
			],
			// #1 Cancel update.
			[
				[
					'action' => 'update',
					//'maintenance_type' = 'No data collection',
					'title' => 'update without maintenance type change'
				]
			],
			// #2 Cancel update when changing maintenance type.
			[
				[
					'action' => 'update',
					'title' => 'update with maintenance type change',
				]
			],
			// #3 Cancel clone
			[
				[
					'action' => 'clone',
					'title' => 'clone'
				]
			],
			// #4 Cancel delete.
			[
				[
					'action' => 'delete'
				]
			]
		];
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testFormMaintenance_Cancel($data) {
		$sql_hash = 'SELECT * FROM maintenances ORDER BY maintenanceid';
		$old_hash = CDBHelper::getHash($sql_hash);

		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();

		if ($data['action'] === 'create') {
			$this->query('button:Create maintenance period')->one()->waitUntilClickable()->click();
		}
		else {
			$this->query('link', self::MAINTENANCE_NAME)->waitUntilClickable()->one()->click();
		}

		// te vajag ielikt delete ar ifu, ja delete, tad sito visu izlaizam
		// ja delete, tad click delete un pazinojumaa cancel

		$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();

		if ($data['action'] !== 'delete') {
			if ($data['action'] === 'clone') {
				COverlayDialogElement::find()->waitUntilReady();
				$this->query('button:Clone')->one()->click()->waitUntilNotVisible();
			}

			if ($data['title'] === 'update with maintenance type change') {
				$maintenance_type = 'No data collection';
			}
			else {
				$maintenance_type = 'With data collection';
			}

			$test_data = [
				'field_values' => [
					'Name' => 'Cancel maintenance '.$data['title'],
					'Maintenance type' => $maintenance_type,
					'Active since' => '2020-01-03 11:24',
					'Active till' => '2029-11-21 00:25',
					'Host groups' => '',
					'Hosts' => 'ЗАББИКС Сервер',
					'Description' => 'Description of '.$data['title']
				],
				'periods' => [
					['Period type' => 'One time only'],
					['Period type' => 'Daily']
				],
				'tags' => [
					[
						'action' => USER_ACTION_UPDATE,
						'index' => 0,
						'tag' => 'EditedTag1',
						'value' => 'EditedA'
					],
					[
						'tag' => 'TagAdded1',
						'value' => 'ValueAdded1'
					],
					[
						'tag' => 'TagAdded2',
						'value' => 'ValueAdded2'
					]
				]
			];

			// Fill tags first since when maintenance type 'No data collection' is selected, tags become disabled
			$form->query('id:tags')->asMultifieldTable()->one()->fill($test_data['tags']);

			// Modify the existing periods and tags.
			if ($data['action'] === 'update' || $data['action'] === 'clone')  {

				// Remove 4th defined period.
				$timeperiods_table = $this->query('id:timeperiods')->one();
				$timeperiods_table->query('xpath:.//tr[@data-row_index="3"]')->one()->query('button:Remove')->one()->click();

				// Edit 2nd defined period.
				$timeperiods_table->query('xpath:.//tr[@data-row_index="1"]')->one()->query('button:Edit')->one()->click();
				$period_overlay = COverlayDialogElement::find()->waitUntilReady()->all()->last()->asForm();
				$period_overlay->fill(['id:every_day' => '2']);
				$period_overlay->submit();
				$period_overlay->waitUntilNotVisible();

				// Remove 2nd tag.
				$form->query('id:tags')->asMultifieldTable()->one()->query('id:tags_1_remove')->one()->click();
			}

			$form->fill($test_data['field_values']);

			foreach ($test_data['periods'] as $period) {
				$form->query('button:Add')->one()->click();
				$period_overlay = COverlayDialogElement::find()->waitUntilReady()->all()->last()->asForm();
				$period_overlay->fill($period);
				$period_overlay->submit();
				$period_overlay->waitUntilNotVisible();
			}
		}
		else {
			//COverlayDialogElement::find()->waitUntilReady()->one()
			COverlayDialogElement::find()->waitUntilReady()->one()->getFooter()->query('button:Delete')->one()->click();
			$this->page->dismissAlert();
		}

		// Close the form.
		$this->query('button:Cancel')->one()->click();
		COverlayDialogElement::ensureNotPresent();

		// Check the result in DB.
		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));

		// Open form to check changes was not saved.
		$this->query('link', self::MAINTENANCE_NAME)->one()->waitUntilClickable()->click();
		$form->invalidate();
		$form->checkValue(['Name' => self::MAINTENANCE_NAME]);

		// Check that 4th period exist.
		//$this->assertTableHasData([['Period type' => 'Monthly']], self::PERIODS_TABLE);
		$this->query('button:Cancel')->one()->click();
		COverlayDialogElement::ensureNotPresent();
	}

	/**
	 * Create maintenance with periods and host group.
	 */
	public function testFormMaintenance_Create() {
		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();
		$this->page->assertTitle('Configuration of maintenance periods');
		$this->page->assertHeader('Maintenance periods');
		$this->query('button:Create maintenance period')->one()->waitUntilClickable()->click();

		// Type maintenance name.
		$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
		$form->fill(['Name' => 'Tessssssst', 'Host groups' => 'Zabbix servers', 'id:tags_evaltype' => 'Or']);

		$periods = [
			[
				'fields' => '',
				'result' => [['Period type' => 'One time only']]
			],
			[
				'fields' => [
					'Period type' => 'Daily'
				],
				'result' => [['Period type' => 'Daily', 'Schedule' => 'At 00:00 every 1 day']]
			],
			[
				'fields' => [
					'Period type' => 'Weekly',
					'Monday' => true,
					'Sunday' => true
				],
				'result' => [['Period type' => 'Weekly', 'Schedule' => 'At 00:00 Monday, Sunday of every 1 week']]
			],
			[
				'fields' => [
					'Period type' => 'Monthly',
					'January' => true,
					'November' => true
				],
				'result' => [['Period type' => 'Monthly', 'Schedule' => 'At 00:00 on day 1 of every January, November']]
			]
		];
		foreach ($periods as $period) {
			$form->query('button:Add')->one()->click();
			$period_overlay = COverlayDialogElement::find()->waitUntilReady()->all()->last()->asForm();
			$period_overlay->fill($period['fields']);
			$period_overlay->submit();
			$period_overlay->waitUntilNotVisible();
			$this->assertTableHasData($period['result'], self::PERIODS_TABLE);
		}

		// Add problem tags.
		$value = 'Value';
		$tags = [
			[
				'action' => USER_ACTION_UPDATE,
				'index' => 0,
				'tag' => 'Tag1',
				'value' => $value
			],
			[
				'tag' => 'Tag2',
				'value' => $value
			],
			[
				'tag' => 'Tag3',
				'value' => $value
			]
		];
		$this->query('id:tags')->asMultifieldTable()->one()->fill($tags);

		// Create maintenance and check the results in frontend.
		$form->submit();
		COverlayDialogElement::ensureNotPresent();
		$this->assertMessage(TEST_GOOD, 'Maintenance period created');
		$this->assertTableHasData([['Name' => self::MAINTENANCE_NAME, 'Type' => 'With data collection']]);

		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM maintenances WHERE name='.zbx_dbstr(self::MAINTENANCE_NAME)));
		$this->assertEquals(3, CDBHelper::getCount('SELECT NULL FROM maintenance_tag WHERE value='.zbx_dbstr($value)));
	}

	/**
	 * Test update by changing maintenance period and type.
	 *
	 * @depends testFormMaintenance_Create
	 */
	public function testFormMaintenance_Update() {
		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();
		$this->query('link', self::MAINTENANCE_NAME)->one()->waitUntilClickable()->click();
		$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();

		// Change maintenance type.
		$form->fill(['Maintenance type' => 'No data collection']);

		// Remove "One time only".
		$table = $this->query(self::PERIODS_TABLE)->asTable()->one();
		$table->findRow('Period type', 'One time only')->getColumn('Action')->query('button:Remove')->one()->click()->waitUntilNotvisible();

		$periods = [
			[
				'schedule' => 'Weekly',
				'fields' => [
					'Wednesday' => true,
					'Friday' => true
				],
				'result' => [['Period type' => 'Weekly', 'Schedule' => 'At 00:00 Monday, Wednesday, Friday, Sunday of every 1 week']]
			],
			[
				'schedule' => 'Monthly',
				'fields' => [
					'Date' => 'Day of week',
					'June' => true,
					'September' => true
				],
				'result' => [['Period type' => 'Monthly', 'Schedule' => 'At 00:00 on first Wednesday of every January, June, September, November']]
			]
		];
		foreach ($periods as $period) {
			$table->findRow('Period type', $period['schedule'])->getColumn('Action')->query('button:Edit')->one()->click();
			$period_overlay = COverlayDialogElement::find()->waitUntilReady()->all()->last()->asForm();
			$period_overlay->fill($period['fields']);

			if ($period['schedule'] === 'Monthly') {
				$this->query('id:monthly_days_4')->waitUntilPresent()->asCheckbox()->one()->check();
			}

			$period_overlay->submit();
			$period_overlay->waitUntilNotVisible();
			$this->assertTableHasData($period['result'], self::PERIODS_TABLE);
		}

		// Check the results in frontend.
		$form->submit();
		COverlayDialogElement::ensureNotPresent();
		$this->assertMessage(TEST_GOOD, 'Maintenance period updated');
		$this->assertTableHasData([['Name' => self::MAINTENANCE_NAME, 'Type' => 'No data collection']]);

		// Check the results in DB.
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM maintenances WHERE name='.zbx_dbstr(self::MAINTENANCE_NAME)));
	}

	public function testFormMaintenance_UpdateTags() {
		$maintenance = self::MAINTENANCE_NAME;
		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();
		$this->query('link', $maintenance)->one()->waitUntilClickable()->click();
		$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
		$form->fill(['id:tags_evaltype' => 'And/Or']);

		// Update tags.
		$tag = 'Tag';
		$tags = [
			[
				'action' => USER_ACTION_UPDATE,
				'index' => 0,
				'tag' => 'Tag',
				'value' => 'A1'
			],
			[
				'action' => USER_ACTION_UPDATE,
				'index' => 1,
				'tag' => 'Tag',
				'value' => 'B1'
			]
		];
		$this->query('id:tags')->asMultifieldTable()->one()->fill($tags);
		$this->query('xpath://label[@for="tags_0_operator_1"]')->one()->click();
		$this->query('xpath://label[@for="tags_1_operator_0"]')->one()->click();

		$form->submit();
		COverlayDialogElement::ensureNotPresent();
		$this->assertMessage(TEST_GOOD, 'Maintenance period updated');

		$this->assertEquals(2, CDBHelper::getCount('SELECT NULL FROM maintenance_tag WHERE tag='.zbx_dbstr($tag)));
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM maintenance_tag WHERE value=\'A1\' AND operator=0'));
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM maintenance_tag WHERE value=\'B1\' AND operator=2'));
	}

	/**
	 * Test cloning of maintenance.
	 *
	 */
	public function testFormMaintenance_Clone() {
		$suffix = ' (clone)';
		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();
		$this->query('link', self::MAINTENANCE_NAME)->one()->waitUntilClickable()->click();
		COverlayDialogElement::find()->waitUntilReady();

		// Clone maintenance, rename the clone and save it.
		$this->query('button:Clone')->one()->click()->waitUntilNotVisible();
		$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
		$form->fill(['Name' => self::MAINTENANCE_NAME.$suffix]);
		$form->submit();
		COverlayDialogElement::ensureNotPresent();

		// Check the result in frontend.
		$this->assertMessage(TEST_GOOD, 'Maintenance period created');
		$this->assertTableHasData([['Name' => self::MAINTENANCE_NAME], ['Name' => self::MAINTENANCE_NAME.$suffix]]);

		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM maintenances WHERE name='.zbx_dbstr(self::MAINTENANCE_NAME)));
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM maintenances WHERE name='.zbx_dbstr(self::MAINTENANCE_NAME.$suffix)));
	}

	/**
	 * Test deleting of maintenance.
	 *
	 */
	public function testFormMaintenance_Delete() {
		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();
		$this->query('link', self::MAINTENANCE_NAME)->one()->waitUntilClickable()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();

		// Delete a maintenance and check the result in frontend.
		$dialog->getFooter()->query('button:Delete')->one()->click();
		$this->page->acceptAlert();
		COverlayDialogElement::ensureNotPresent();
		$this->assertMessage(TEST_GOOD, 'Maintenance period deleted');

		$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM maintenances WHERE name='.zbx_dbstr(self::MAINTENANCE_NAME)));
	}
}
