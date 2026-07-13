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


require_once __DIR__.'/../../include/CWebTest.php';
require_once __DIR__.'/../behaviors/CMessageBehavior.php';
require_once __DIR__.'/../behaviors/CTableBehavior.php';

/**
 * @backup maintenances
 *
 * @onBefore prepareMaintenanceData
 */
class testFormMaintenance extends CWebTest {

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

	const EXPECTED_MAINTENANCE = [
		'Name' => self::MAINTENANCE_NAME,
		'Maintenance type' => 'With data collection',
		'Active since' => '2023-04-18 00:01',
		'Active till' => '2030-03-10 23:59',
		'Host groups' => 'Zabbix servers',
		'id:tags_evaltype' => 'Or',
		'Description' => 'Test description'
	];

	const EXPECTED_PERIODS = [
		[
			'Period type' => 'One time only',
			'Schedule' => '2026-03-20 15:30',
			'Period' => '1h 30m'
		],
		[
			'Period type' => 'Daily',
			'Schedule' => 'At 14:00 every 3 days',
			'Period' => '5m'
		],
		[
			'Period type' => 'Weekly',
			'Schedule' => 'At 01:00 Monday, Sunday of every 2 weeks',
			'Period' => '2h'
		],
		[
			'Period type' => 'Monthly',
			'Schedule' => 'At 23:00 on day 15 of every January, November',
			'Period' => '2y 8M 29d'
		]
	];

	protected static $update_maintenance;

	public function prepareMaintenanceData() {
		$maintenance_data = [
			'name' => self::MAINTENANCE_NAME,
			'maintenance_type' => MAINTENANCE_TYPE_NORMAL,
			'active_since' => 1681765260, // 18.04.2023 00:01
			'active_till' => 1899410340, // 10.03.2030 23:59
			'description' => 'Test description',
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
		];

		// Create copies for update scenarios.
		$maintenance_for_update = $maintenance_data;
		$maintenance_for_update['name'] = self::MAINTENANCE_NAME.' (for update)';

		$maintenance_for_period_update = $maintenance_data;
		$maintenance_for_period_update['name'] = self::MAINTENANCE_NAME.' (for period checks)';

		CDataHelper::call('maintenance.create', [
			$maintenance_data,
			$maintenance_for_update,
			$maintenance_for_period_update
		]);

		// Update the static tracker so the Update test clicks the correct link.
		self::$update_maintenance['fields']['Name'] = $maintenance_for_update['name'];
	}

	public function testFormMaintenance_Layout() {
		foreach ([false, true] as $is_update) {
			$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();

			$button = ($is_update) ? 'link:'.self::MAINTENANCE_NAME : 'button:Create maintenance period';
			$this->query($button)->one()->waitUntilClickable()->click();

			$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
			$form = $dialog->asForm();

			if (!$is_update) {
				// Check default state.
				$default_state = [
					'Name' => '',
					'Maintenance type' => 'With data collection',
					'Active since' => date('Y-m-d').' 00:00',
					'Active till' => date('Y-m-d', strtotime('tomorrow')).' 00:00',
					'Periods' => [],
					'Host groups' => '',
					'Hosts' => '',
					'id:tags_evaltype' => 'And/Or',
					'id:tags_0_tag' => '',
					'id:tags_0_operator' => 'Contains',
					'id:tags_0_value' => '',
					'Description' => ''
				];

				$form->checkValue($default_state);
			}

			// Check asterisk in required field labels.
			$this->assertEquals(['Name', 'Active since', 'Active till', 'Periods'], $form->getRequiredLabels());
			$this->assertTrue($this->query('xpath://label[contains(@class,"form-label-asterisk") and contains(text(),'.
				'"At least one host group or host must be selected.")]')->exists()
			);

			$check_fields = [
				'Name' => ['maxlength' => 128],
				'id:active_since' => ['maxlength' => 255, 'placeholder' => 'YYYY-MM-DD hh:mm'],
				'id:active_till' => ['maxlength' => 255, 'placeholder' => 'YYYY-MM-DD hh:mm'],
				'id:groupids__ms' => ['placeholder' => 'type here to search'],
				'id:hostids__ms' => ['placeholder' => 'type here to search'],
				'id:tags_0_tag' => ['maxlength' => 255, 'placeholder' => 'tag'],
				'id:tags_0_value' => ['maxlength' => 255, 'placeholder' => 'value'],
				'Description' => ['maxlength' => 65535]
			];
			foreach ($check_fields as $field => $attributes) {
				foreach ($attributes as $attribute => $value) {
					$this->assertEquals($value, $form->getField($field)->getAttribute($attribute));
				}
			}

			// Check Periods table.
			$periods_table = $this->query(self::PERIODS_TABLE)->asTable()->one();
			$this->assertEquals(['Period type', 'Schedule', 'Period', 'Actions'], $periods_table->getHeadersText());
			if (!$is_update) {
				$this->assertEquals(0, $periods_table->getRows()->count());
			}

			// Check radio buttons.
			$radio_buttons = [
				'Maintenance type' => ['With data collection', 'No data collection'],
				'id:tags_evaltype' => ['And/Or', 'Or'],
				'id:tags_0_operator' => ['Contains', 'Equals']
			];
			foreach ($radio_buttons as $name => $labels) {
				$this->assertEquals($labels, $form->getField($name)->getLabels()->asText());
			}

			// Check Periods table.
			$periods_table = $this->query(self::PERIODS_TABLE)->asTable()->one();
			$this->assertEquals(['Period type', 'Schedule', 'Period', 'Actions'], $periods_table->getHeadersText());

			$periods_rows_count = ($is_update) ? count(self::EXPECTED_PERIODS) : 0;
			$this->assertEquals($periods_rows_count, $periods_table->getRows()->count());

			$table_buttons = ($is_update) ? ['Add', 'Edit', 'Remove'] : ['Add'];
			// Each row has has 1 "Edit" button and 1 "Remove" button, but the table itself has a single "Add" button.
			$this->assertEquals(($periods_rows_count * 2 + 1), $periods_table->query('button', $table_buttons)->all()
					->filter(CElementFilter::CLICKABLE)->count()
			);

			// Check Tags table.
			$tags_table = $form->query('id:tags')->asMultifieldTable()->one();
			$tags_rows_count = $is_update ? 2 : 1;
			// Each row has 1 "Remove" button, and the table itself has a single "Add" button.
			$this->assertEquals($tags_rows_count + 1, $tags_table->query('button', ['Add', 'Remove'])->all()
					->filter(CElementFilter::CLICKABLE)->count()
			);

			// Change to 'No data collection' and assert tags table elements are disabled.
			$form->fill(['Maintenance type' => 'No data collection']);
			$state_changing_fields = [
				'id:tags_evaltype',
				'id:tags_0_tag',
				'id:tags_0_operator',
				'id:tags_0_value'
			];
			foreach ($state_changing_fields as $field) {
				$this->assertFalse($form->getField($field)->isEnabled());
			}

			$this->assertEquals(0, $tags_table->query('button', ['Add', 'Remove'])->all()
					->filter(CElementFilter::CLICKABLE)->count()
			);

			// Change back to 'With data collection' and verify tags table elements are re-enabled.
			$form->fill(['Maintenance type' => 'With data collection']);
			foreach ($state_changing_fields as $field) {
				$this->assertTrue($form->getField($field)->isEnabled());
			}

			$this->assertEquals($tags_rows_count + 1, $tags_table->query('button', ['Add', 'Remove'])->all()
					->filter(CElementFilter::CLICKABLE)->count()
			);

			// Check calendar buttons.
			foreach (['active_since_calendar', 'active_till_calendar'] as $button) {
				$this->assertTrue($form->query('id', $button)->one()->isClickable());
			}

			// Check Select buttons and dialogs they open.
			foreach (['Host groups', 'Hosts'] as $field) {
				$this->assertTrue($form->getField($field)->query('button:Select')->one()->isClickable());
				$form->getField($field)->query('button:Select')->one()->click();

				$list = COverlayDialogElement::find(1)->waitUntilReady()->one();
				$this->assertEquals($field, $list->getTitle());
				$this->assertEquals(['Select', 'Cancel'], $list->getFooter()->query('button')->all()
					->filter(CElementFilter::CLICKABLE)->asText()
				);
				$list->close();
			}

			$expected_footer = ($is_update) ? ['Update', 'Clone', 'Delete', 'Cancel'] : ['Add', 'Cancel'];

			// Check footer buttons.
			$this->assertEquals($expected_footer, $dialog->getFooter()->query('button')->all()
					->filter(CElementFilter::CLICKABLE)->asText()
			);
		}
	}

	public function testFormMaintenance_CheckPeriodForm() {
		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();
		$this->query('button:Create maintenance period')->one()->waitUntilClickable()->click();

		$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
		$periods_table = $this->query(self::PERIODS_TABLE)->asTable()->one();

		$periods = [
			'One time only',
			'Daily',
			'Weekly',
			'Monthly',
			'Monthly with Day of week period'
		];

		$now = date('Y-m-d H:i');
		$in_one_minute = date('Y-m-d H:i', strtotime($now.' +1 minute'));

		foreach (['Create', 'Edit'] as $mode) {
			if ($mode === 'Create') {
				$form->getField('Periods')->query('button:Add')->one()->waitUntilClickable()->click();
			}
			else {
				$periods_table->getRow(0)->query('button:Edit')->one()->click();
			}

			$dialog = COverlayDialogElement::find(1)->waitUntilReady()->one();
			$period_form = $dialog->asForm();

			// Check initial default state.
			$period_form->checkValue([
				'Period type' => 'One time only',
				'id:period_days' => '0',
				'name:period_hours' => '1',
				'name:period_minutes' => '0'
			]);

			$this->assertTrue($period_form->checkValue(['Date' => $now], false)
					|| $period_form->checkValue(['Date' => $in_one_minute], false));


			foreach ($periods as $period_type) {
				if ($period_type === 'Monthly with Day of week period') {
					$period_form->fill(['Period type' => 'Monthly', 'id:month_date_type' => 'Day of week']);
				} else {
					$period_form->fill(['Period type' => $period_type]);
				}

				$dialog->waitUntilReady();

				// Define expected results.
				$ui_period_type = ($period_type === 'Monthly with Day of week period') ? 'Monthly' : $period_type;

				$check_fields = [];
				$expected_required = [];
				$expected_default_values = [
					'Period type' => $ui_period_type,
					'id:period_days' => '0',
					'name:period_hours' => '1',
					'name:period_minutes' => '0'
				];

				switch ($period_type) {
					case 'One time only':
						$expected_required = ['Date'];
						$check_fields = [
							'id:start_date' => ['maxlength' => 255, 'placeholder' => 'YYYY-MM-DD hh:mm']
						];
						$this->assertTrue($period_form->query('id:start_date_calendar')->one()->isClickable());
						break;

					case 'Daily':
						$expected_required = ['Every day(s)', 'At (hour:minute)'];
						$check_fields = [
							'Every day(s)' => ['maxlength' => 3]
						];
						$expected_default_values += [
							'Every day(s)' => '1',
							'id:hour' => '00',
							'id:minute' => '00'
						];
						break;

					case 'Weekly':
						$expected_required = ['Every week(s)', 'Day of week', 'At (hour:minute)'];
						$check_fields = [
							'Every week(s)' => ['maxlength' => 2]
						];
						$expected_default_values += [
							'Every week(s)' => '1',
							'id:hour' => '00',
							'id:minute' => '00',
							'Day of week' => []
						];
						// Days ID's are powers of two.
						for ($i = 0; $i <= 6; $i++) {
							$this->assertTrue($period_form->query('id:weekly_days_'.pow(2, $i))->one()->isEnabled());
						}
						break;

					case 'Monthly':
						$expected_required = ['Month', 'Day of month', 'At (hour:minute)'];
						$check_fields = [
							'Day of month' => ['maxlength' => 2]
						];
						$expected_default_values += [
							'id:month_date_type' => 'Day of month',
							'Day of month' => '1',
							'id:hour' => '00',
							'id:minute' => '00',
							'Month' => []
						];
						// Months ID's are powers of two.
						for ($i = 0; $i <= 11; $i++) {
							$this->assertTrue($period_form->query('id:months_'.pow(2, $i))->one()->isEnabled());
						}
						break;

					case 'Monthly with Day of week period':
						$expected_required = ['Month', 'Day of week', 'At (hour:minute)'];
						$check_fields = [
						];
						$expected_default_values += [
							'id:month_date_type' => 'Day of week',
							'name:every_dow' => 'first',
							'id:hour' => '00',
							'id:minute' => '00',
							'Day of week' => [],
							'Month' => []
						];

						$this->assertEquals(['first', 'second', 'third', 'fourth', 'last'],
								$period_form->query('name:every_dow')->asDropdown()->one()->getOptions()->asText()
						);

						// Days ID's are powers of two.
						for ($i = 0; $i <= 6; $i++) {
							$this->assertTrue($period_form->query('id:monthly_days_'.pow(2, $i))->one()->isEnabled());
						}
						break;
				}

				// Common fields for all period types.
				if ($period_type !== 'One time only') {
					$period_form->checkValue($expected_default_values);
					$check_fields += [
						'id:hour' => ['maxlength' => 2],
						'id:minute' => ['maxlength' => 2]
					];
				}
				$check_fields += [
					'id:period_days' => ['maxlength' => 3]
				];
				$expected_required[] = 'Maintenance period length';

				$this->assertEquals($expected_required, $period_form->getRequiredLabels());

				foreach ($check_fields as $field => $attributes) {
					foreach ($attributes as $attribute => $value) {
						$this->assertEquals($value, $period_form->getField($field)->getAttribute($attribute));
					}
				}

				// Check labels being visible.
				if (in_array($period_type, ['Weekly', 'Monthly with Day of week period'])) {
					$weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
					if ($period_type === 'Weekly') {
						$days_list = $period_form->getField('Day of week')->asCheckboxList();
					}
					else {
						$days_list = $period_form->query('xpath:.//div[contains(@class, "js-monthly-days")]//ul')
							->asCheckboxList()->one();
					}
					$this->assertTrue($days_list->isEnabled());
					$this->assertEquals($weekdays, $days_list->getLabels()->filter(CElementFilter::VISIBLE)->asText());
				}

				if (in_array($period_type, ['Monthly', 'Monthly with Day of week period'])) {
					$months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October',
						'November', 'December'
					];
					$months_list = $period_form->getField('Month')->asCheckboxList();
					$this->assertTrue($months_list->isEnabled());
					$this->assertEquals($months, $months_list->getLabels()->filter(CElementFilter::VISIBLE)->asText());
				}

				// Check screenshots.
				if ($mode === 'Create') {
					$this->page->removeFocus();

					// Remove Add and Cancel buttons edge curling from screenshots as their rendering is unstable.
					$dialog_footer = $dialog->getFooter();
					foreach (['Add', 'Cancel'] as $button) {
						$this->page->getDriver()->executeScript('arguments[0].style.borderRadius=0;',
							[$dialog_footer->query('button', $button)->one()]
						);
					}

					if ($period_type === 'One time only') {
						$this->assertScreenshotExcept($dialog, [$dialog->query('id:start_date')->one()], $period_type);
					} else {
						$this->assertScreenshot($dialog, $period_type);
					}
				}
			}

			// Check maintenance duration dropdown (hours and minutes) values.
			foreach (['name:period_hours' => 23, 'name:period_minutes' => 59] as $name => $max) {
				$options = $period_form->getField($name)->asDropdown()->getOptions()->asText();
				$this->assertEquals(range(0, $max), array_map('intval', $options));
			}

			// Check footer buttons.
			if ($mode === 'Create') {
				$this->assertEquals(['Add', 'Cancel'], $dialog->getFooter()->query('button')->all()
						->filter(CElementFilter::CLICKABLE)->asText()
				);

				$period_form->fill(['Period type' => 'One time only']);
				$period_form->submit();
				$period_form->waitUntilNotVisible();
			}
			else {
				$this->assertEquals(['Update', 'Cancel'], $dialog->getFooter()->query('button')->all()
						->filter(CElementFilter::CLICKABLE)->asText()
				);
				COverlayDialogElement::closeAll();
			}
		}
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
			// #1 Cancel update without maintenance type change.
			[
				[
					'action' => 'update',
					'title' => 'update without maintenance type change'
				]
			],
			// #2 Cancel update when changing maintenance type.
			[
				[
					'action' => 'update',
					'title' => 'update with maintenance type change'
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
		$action = $data['action'];

		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();

		$button = ($action === 'create') ? 'button:Create maintenance period' : 'link:'.self::MAINTENANCE_NAME;
		$this->query($button)->waitUntilClickable()->one()->click();

		$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();

		if ($action == 'delete') {
			COverlayDialogElement::find()->waitUntilReady()->one()->getFooter()->query('button:Delete')->one()->click();
			$this->page->dismissAlert();
		}
		else {
			if ($action === 'clone') {
				COverlayDialogElement::find()->waitUntilReady();
				$this->query('button:Clone')->one()->click()->waitUntilNotVisible();
			}

			$maintenance_type = ($data['title'] === 'update with maintenance type change')
				? 'No data collection'
				: 'With data collection';

			$test_data = [
				'field_values' => [
					'Name' => 'Cancel maintenance '.$data['title'],
					'Maintenance type' => $maintenance_type,
					'Active since' => '2020-01-03 11:24',
					'Active till' => '2029-11-21 00:25',
					'Host groups' => '',
					'Hosts' => 'ЗАББИКС Сервер',
					'Description' => 'Description of '.$action
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
						'operator' => 'Equals',
						'value' => 'EditedA'
					],
					[
						'tag' => 'TagAdded1',
						'operator' => 'Equals',
						'value' => 'ValueAdded1'
					],
					[
						'tag' => 'TagAdded2',
						'operator' => 'Contains',
						'value' => 'ValueAdded2'
					]
				]
			];

			// Fill tags first since when maintenance type 'No data collection' is selected, tags become disabled.
			$tags_table = $form->query('id:tags')->asMultifieldTable()->one();
			$tags_table->setFieldMapping(['tag', 'operator', 'value']);
			$tags_table->fill($test_data['tags']);

			// Modify the existing periods and tags.
			if ($action === 'update' || $action === 'clone') {

				// Remove 4th defined period.
				$timeperiods_table = $this->query('id:timeperiods')->asTable()->one();
				$timeperiods_table->getRow(3)->query('button:Remove')->one()->click();

				// Edit 2nd defined period.
				$timeperiods_table->getRow(1)->query('button:Edit')->one()->click();
				$period_form = COverlayDialogElement::find(1)->waitUntilReady()->one()->asForm();
				$period_form->fill(['Every day(s)' => '2']);
				$period_form->submit();
				$period_form->waitUntilNotVisible();

				// Remove 2nd tag.
				$form->query('id:tags')->asMultifieldTable()->one()->query('id:tags_1_remove')->one()->click();
			}

			$form->fill($test_data['field_values']);

			foreach ($test_data['periods'] as $period) {
				$form->query('button:Add')->one()->click();
				$period_form = COverlayDialogElement::find(1)->waitUntilReady()->one()->asForm();
				$period_form->fill($period);
				$period_form->submit();
				$period_form->waitUntilNotVisible();
			}
		}

		// Close the form.
		$this->query('button:Cancel')->one()->click();
		COverlayDialogElement::ensureNotPresent();

		// Check the result in DB.
		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));

		// Open form to check changes were not saved.
		$this->query('link', self::MAINTENANCE_NAME)->one()->waitUntilClickable()->click();
		$this->checkMaintenanceForm();
		COverlayDialogElement::find()->one()->close();
	}

	public function testFormMaintenance_PeriodFormCancel() {
		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();

		foreach ([false, true] as $is_update) {
			$button = ($is_update) ? 'link:'.self::MAINTENANCE_NAME : 'button:Create maintenance period';
			$this->query($button)->one()->click();

			$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
			$periods_table = $form->query(self::PERIODS_TABLE)->asTable()->one();

			// Cancel adding a period.
			$initial_count = $periods_table->getRows()->count();
			$form->getField('Periods')->query('button:Add')->one()->click();

			$period_form = COverlayDialogElement::find(1)->waitUntilReady()->one();
			$period_form->asForm()->fill(['Period type' => 'Daily']);
			$period_form->query('button:Cancel')->one()->click();

			$period_form->waitUntilNotVisible();
			$this->assertEquals($initial_count, $periods_table->getRows()->count());

			// Cancel editing a period.
			if ($is_update && $initial_count > 0) {
				$row = $periods_table->getRow(0);
				// Get original row data before update.
				$old_text = $row->getText();

				$row->query('button:Edit')->one()->click();
				$period_form = COverlayDialogElement::find(1)->waitUntilReady()->one();
				$period_form->asForm()->fill(['Period type' => 'Monthly']);
				$period_form->query('button:Cancel')->one()->click();

				$period_form->waitUntilNotVisible();
				$this->assertEquals($old_text, $periods_table->getRow(0)->getText());
			}

			COverlayDialogElement::closeAll();
		}
	}

	public static function getCreateUpdateData() {
		return [
			// #0 Minimal required fields.
			[
				[
					'fields' => [
						'Name' => 'Minimal fields',
						'Hosts' => 'ЗАББИКС Сервер'
					],
					'periods' => [
						[
							'Period type' => 'Daily'
						]
					]
				]
			],
			// #1 Filling all possible form fields and period types, overlapping periods, tag alphabetical ordering.
			[
				[
					'fields' => [
						'Name' => 'Fill all fields',
						'Maintenance type' => 'With data collection',
						'Active since' => '2015-02-03 14:30',
						'Active till' => '2035-09-01 22:55',
						'Host groups' => ['Zabbix servers', 'Hypervisors'],
						'Hosts' => ['ЗАББИКС Сервер'],
						'id:tags_0_tag' => 'tag',
						'id:tags_evaltype' => 'Or',
						'id:tags_0_value' => 'value',
						'Description' => 'This is a maintenance description for covering all fields.'
					],
					'periods' => [
						[
							'Period type' => 'One time only',
							'Date' => '2026-03-20 15:30',
							'id:period_days' => '0',
							'name:period_hours' => '1',
							'name:period_minutes' => '30'
						],
						[
							'Period type' => 'One time only',
							'Date' => '2026-03-20 15:30',
							'id:period_days' => '0',
							'name:period_hours' => '2',
							'name:period_minutes' => '40'
						],
						[
							'Period type' => 'Daily',
							'Every day(s)' => '2',
							'id:hour' => '23',
							'id:minute' => '30',
							'id:period_days' => '0',
							'name:period_hours' => '0',
							'name:period_minutes' => '5'
						],
						[
							'Period type' => 'Weekly',
							'Every week(s)' => '2',
							'Day of week' => ['Monday', 'Wednesday', 'Friday'],
							'id:hour' => '05',
							'id:minute' => '30',
							'id:period_days' => '1',
							'name:period_hours' => '2',
							'name:period_minutes' => '40'
						],
						[
							'Period type' => 'Monthly',
							'Month' => ['February', 'June', 'December'],
							'Date' => 'Day of month',
							'Day of month' => '14',
							'id:hour' => '22',
							'id:minute' => '30',
							'id:period_days' => '0',
							'name:period_hours' => '0',
							'name:period_minutes' => '30'
						],
						[
							'Period type' => 'Monthly',
							'Month' => ['January', 'July'],
							'Date' => 'Day of week',
							'name:every_dow' => 'second',
							'xpath:.//div[contains(@class, "js-monthly-days")]/ul' => ['Wednesday', 'Sunday'],
							'id:hour' => '23',
							'id:minute' => '55',
							'id:period_days' => '2',
							'name:period_hours' => '20',
							'name:period_minutes' => '10'
						]
					],
					'expected_periods' => [
						[
							'Period type' => 'One time only',
							'Schedule' => '2026-03-20 15:30',
							'Period' => '1h 30m'
						],
						[
							'Period type' => 'One time only',
							'Schedule' => '2026-03-20 15:30',
							'Period' => '2h 40m'
						],
						[
							'Period type' => 'Daily',
							'Schedule' => 'At 23:30 every 2 days',
							'Period' => '5m'
						],
						[
							'Period type' => 'Weekly',
							'Schedule' => 'At 05:30 Monday, Wednesday, Friday of every 2 weeks',
							'Period' => '1d 2h 40m'
						],
						[
							'Period type' => 'Monthly',
							'Schedule' => 'At 22:30 on day 14 of every February, June, December',
							'Period' => '30m'
						],
						[
							'Period type' => 'Monthly',
							'Schedule' => 'At 23:55 on second Wednesday, Sunday of every January, July',
							'Period' => '2d 20h 10m'
						]
					]
				]
			],
			// #2 With maintenance type No data collection.
			[
				[
					'fields' => [
						'Name' => 'With No data collection',
						'Maintenance type' => 'No data collection',
						'Host groups' => 'Zabbix servers'
					],
					'periods' => [
						[
							'Period type' => 'Daily'
						]
					]
				]
			],
			// #3 Strings with allowed symbols in all fields, and Active since and Active till in limits.
			[
				[
					'fields' => [
						'Name' => 'Name-., /!?@#$%^&*()_=+[]{}\| ;:<>/ бц 頑張って 😀 &nbsp; \t \r \n 1E+308 %00',
						'Maintenance type' => 'With data collection',
						'Active since' => '1970-01-02 00:00',
						'Active till' => '2038-01-19 00:00',
						'Host groups' => 'Zabbix servers',
						'Hosts' => 'ЗАББИКС Сервер',
						'Description' => 'Description-., /!?@#$%^&*()_=+[]{}\| ;:<>/ бц 頑張って 😀 &nbsp; \t \r \n 1E+308 %00'
					],
					'periods' => [
						[
							'Period type' => 'Daily'
						]
					]
				]
			],
			// #4 Maxlength string in Name and long Description.
			[
				[
					'fields' => [
						'Name' => STRING_128,
						'Host groups' => 'Zabbix servers',
						'Description' => STRING_6000
					],
					'periods' => [
						[
							'Period type' => 'Daily'
						]
					]
				]
			],
			// #5 Leading and trailing spaces, incomplete dates.
			[
				[
					'fields' => [
						'Name' => '  Trim test  ',
						'Active since' => '2025-02-03 05',
						'Active till' => '2030',
						'Host groups' => 'Zabbix servers',
						'Hosts' => 'ЗАББИКС Сервер',
						'Description' => '  This description should be trimmed  '
					],
					'replace_fields' => [
						'Active since' => '2025-02-03 05:00',
						'Active till' => '2030-01-01 00:00'
					],
					'trim' => true,
					'periods' => [
						[
							'Period type' => 'Daily'
						]
					]
				]
			],
			// #6 All mandatory form fields (that are triggered at the same time) empty.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => '',
						'Active since' => '',
						'Active till' => '',
						'Host groups' => '',
						'Hosts' => '',
						'id:tags_evaltype' => 'And/Or'
					],
					'inline_errors' => [
						'Name' => 'This field cannot be empty.',
						'id:active_since' => 'This field cannot be empty.',
						'id:active_till' => 'This field cannot be empty.',
						'xpath://table[@id="timeperiods"]/..' => 'At least one period must be added.',
						'Host groups' => 'At least one host group or host must be selected.'
					]
				]
			],
			// #7 Duplicate name.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => self::MAINTENANCE_NAME,
						'Host groups' => 'Zabbix servers'
					],
					'periods' => [
						[
							'Period type' => 'Daily'
						]
					],
					'inline_errors' => [
						'id:name' => 'This object already exists.'
					]
				]
			],
			// #8 Active since greater than Active till.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Active since > active till error',
						'Active since' => '2025-02-03 00:00',
						'Active till' => '2024-01-01 00:00',
						'Host groups' => 'Zabbix servers'
					],
					'periods' => [
						'Period type' => 'Daily'
					],
					'error_details' => [
						'Invalid parameter "/1/active_till": cannot be less than or equal to the value of parameter "/1/active_since".'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateUpdateData
	 */
	public function testFormMaintenance_Create($data) {
		$this->checkAction($data);
	}

	/**
	 * @dataProvider getCreateUpdateData
	 */
	public function testFormMaintenance_Update($data) {
		$this->checkAction($data, true);
	}

	/**
	 * Check Maintenance creation or update form validation and successful submission.
	 *
	 * @param array    $data    data provider
	 * @param boolean  $update  flag that determines whether update or create operation should be checked
	 */
	protected function checkAction($data, $update = false) {
		$expected = CTestArrayHelper::get($data, 'expected', TEST_GOOD);
		if ($expected === TEST_BAD) {
			$sql = 'SELECT * FROM maintenances ORDER BY maintenanceid';
			$old_hash = CDBHelper::getHash($sql);
		}

		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();

		if ($update) {
			// Add prefix to the Update scenarios taking into account the trailing and leading spaces scenario.
			if ($expected === TEST_GOOD) {
				$data['fields']['Name'] = (CTestArrayHelper::get($data, 'trim'))
					? ' Updated - '.trim($data['fields']['Name']).'  '
					: 'Updated - '.$data['fields']['Name'];

				// After adding prefix in the long name scenario, the length of the name must be limited to 128 symbols.
				if (strlen($data['fields']['Name']) > 128) {
					$data['fields']['Name'] = substr($data['fields']['Name'], 0, 128);
				}
			}

			$this->query('link', self::$update_maintenance['fields']['Name'])->one()->waitUntilClickable()->click();
		}
		else {
			$this->query('button:Create maintenance period')->one()->click();
		}

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->asForm();

		$form->fill($data['fields']);

		if ($update) {
			// Remove all periods.
			$existing_periods = $this->query(self::PERIODS_TABLE)->asTable()->one()->getRows();
			foreach ($existing_periods as $row) {
				$row->query('button:Remove')->one()->click();
			}
		}

		// Fill periods if they exist.
		if (array_key_exists('periods', $data)) {
			foreach ($data['periods'] as $period) {
				$form->getField('Periods')->query('button:Add')->one()->click();
				$period_overlay = COverlayDialogElement::find(1)->waitUntilReady()->one()->asForm();
				$period_overlay->fill($period);
				$period_overlay->submit();
				$period_overlay->waitUntilNotVisible();
			}
		}

		$form->submit();
		$this->page->waitUntilReady();
		$action = ($update) ? 'update' : 'create';

		if ($expected === TEST_BAD) {
			if (array_key_exists('inline_errors', $data)) {
				$this->page->removeFocus();
				$this->assertInlineError($form, $data['inline_errors']);
			}
			else {
				$this->assertMessage(TEST_BAD, 'Cannot '.$action.' maintenance period', $data['error_details']);
			}

			// Check that DB hash has not changed.
			$this->assertEquals($old_hash, CDBHelper::getHash($sql));
			$dialog->close();
		}
		else {
			$this->assertMessage(TEST_GOOD, 'Maintenance period '.$action.'d');

			// Active since and Active till fields get autocompled when not fully specified, so expected data is adjusted.
			if (array_key_exists('replace_fields', $data)) {
				foreach ($data['replace_fields'] as $field => $new_value) {
					$data['fields'][$field] = $new_value;
				}
			}

			// Trim trailing and leading spaces in expected values.
			if (CTestArrayHelper::get($data, 'trim')) {
				$data['fields'] = CTestArrayHelper::trim($data['fields']);
			}

			// If defined, use 'expected_periods' as reference, otherwise use a default configuration of a daily period.
			$default_period = [
				'Period type' => 'Daily',
				'Schedule' => 'At 00:00 every 1 day',
				'Period' => '1h'
			];
			$expected_periods = CTestArrayHelper::get($data, 'expected_periods', [$default_period]);

			if ($update) {
				self::$update_maintenance = [
					'fields' => $data['fields'],
					'periods' => $expected_periods
				];
			}

			$this->query('link', $data['fields']['Name'])->one()->click();

			$this->checkMaintenanceForm($data['fields'], $expected_periods);
			$dialog->close();

			// Check values in DB.
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM maintenances WHERE name='.zbx_dbstr($data['fields']['Name'])));
		}
	}

	public function testFormMaintenance_SimpleUpdate() {
		$sql = 'SELECT * FROM maintenances ORDER BY maintenanceid';
		$old_hash = CDBHelper::getHash($sql);

		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();
		$this->query('link', self::MAINTENANCE_NAME)->one()->waitUntilClickable()->click();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$dialog->query('button:Update')->waitUntilClickable()->one()->click();
		$dialog->ensureNotPresent();

		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Maintenance period updated');

		// Check that DB hash has not changed.
		$this->assertEquals($old_hash, CDBHelper::getHash($sql));
	}

	public static function getPeriodValidationData() {
		return [
			// #0 One time only with empty date.
			[
				[
					'fields' => [
						'Period type' => 'One time only',
						'Date' => ''
					],
					'inline_errors' => [
						'id:start_date' => 'This field cannot be empty.'
					]
				]
			],
			// #1 One time only with wrong date format.
			[
				[
					'fields' => [
						'Period type' => 'One time only',
						'Date' => '2030/02/19'
					],
					'inline_errors' => [
						'id:start_date' => 'Invalid date.'
					]
				]
			],
			// #2 One time only with invalid dates.
			[
				[
					'fields' => [
						'Period type' => 'One time only',
						'Date' => '2030-02-30 00:00'
					],
					'inline_errors' => [
						'id:start_date' => 'Invalid date.'
					]
				]
			],
			// #3 One time only with date out of range (before 1970-01-01).
			[
				[
					'fields' => [
						'Period type' => 'One time only',
						'Date' => '1969-12-31 23:59'
					],
					'inline_errors' => [
						'id:start_date' => 'Invalid date.'
					]
				]
			],
			// #4 One time only with date out of range (after 2038-01-18).
			[
				[
					'fields' => [
						'Period type' => 'One time only',
						'Date' => '2038-01-20 00:00'
					],
					'inline_errors' => [
						'id:start_date' => 'Value must be less than or equal to 2038-01-19 05:14:07.'
					]
				]
			],
			// #5 Daily with 'Every day(s)' 0 and period length 0 hours and 0 minutes.
			[
				[
					'fields' => [
						'Period type' => 'Daily',
						'Every day(s)' => '0',
						'id:period_days' => '0',
						'name:period_hours' => '0',
						'name:period_minutes' => '0'
					],
					'inline_errors' => [
						'Every day(s)' => 'Value must be greater than or equal to 1.',
						'name:period_minutes' => 'Minutes: Minimum value of "Maintenance period length" is 5 minutes.'
					]
				]
			],
			// #6 Daily with invalid At (hour:minute) and maintenance period length days values.
			[
				[
					'fields' => [
						'Period type' => 'Daily',
						'id:hour' => '98',
						'id:minute' => '99',
						'id:period_days' => '-9'
					],
					'inline_errors' => [
						'id:hour' => "Hour: Value must be less than or equal to 23.",
						'id:minute' => "Minute: Value must be less than or equal to 59.",
						'id:period_days' => 'Days: Value must be greater than or equal to 0.'
					]
				]
			],
			// #7 Weekly with 'Every week' 0 and no days selected.
			[
				[
					'fields' => [
						'Period type' => 'Weekly',
						'Every week(s)' => '0'
					],
					'inline_errors' => [
						'Every week(s)' => 'Value must be greater than or equal to 1.',
						'xpath:.//ul[@data-field-name="weekly_days"]' => 'At least one day must be selected.'
					]
				]
			],
			// #8 Monthly (Day of month) with empty months and 'Day of month' 0.
			[
				[
					'fields' => [
						'Period type' => 'Monthly',
						'Day of month' => '0'
					],
					'inline_errors' => [
						'Day of month' => 'Value must be greater than or equal to 1.',
						'xpath:.//ul[@data-field-name="months"]' => 'At least one month must be selected.'
					]
				]
			],
			// #9 Monthly (Day of week) with empty months and no days selected.
			[
				[
					'fields' => [
						'Period type' => 'Monthly',
						'id:month_date_type' => 'Day of week'
					],
					'inline_errors' => [
						'xpath:.//ul[@data-field-name="months"]' => 'At least one month must be selected.',
						'xpath:.//ul[@data-field-name="monthly_days"]' => 'At least one weekday must be selected.'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getPeriodValidationData
	 */
	public function testFormMaintenance_PeriodFormValidation($data) {
		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();
		$target = self::MAINTENANCE_NAME.' (for period checks)';

		$scenarios = [
			['maintenance_action' => 'create', 'period_action' => 'add'],
			['maintenance_action' => 'update', 'period_action' => 'add'],
			['maintenance_action' => 'update', 'period_action' => 'edit']
		];

		foreach ($scenarios as $scenario) {
			if ($scenario['maintenance_action'] === 'create') {
				$this->query('button:Create maintenance period')->one()->click();
			}
			else {
				$this->query('link', $target)->one()->click();
			}

			$form = COverlayDialogElement::find()->waitUntilReady()->one()->asForm();

			if ($scenario['period_action'] === 'add') {
				$form->getField('Periods')->query('button:Add')->one()->click();
			}
			else {
				$form->query(self::PERIODS_TABLE)->asTable()->one()->getRow(1)->query('button:Edit')->one()->click();
			}

			// Fill invalid data and submit.
			$overlay = COverlayDialogElement::find(1)->waitUntilReady()->one();
			$overlay->asForm()->fill($data['fields'])->submit();

			$this->assertInlineError($overlay->asForm(), $data['inline_errors']);

			COverlayDialogElement::closeAll();
		}
	}

	public static function getPeriodUpdateData() {
		return [
			// #0 Data change within the same type (Daily).
			[
				[
					'row_index' => 1, // Daily period.
					'fill' => [
						'Every day(s)' => '5',
						'id:hour' => '12'
					],
					'expected' => ['Period type' => 'Daily', 'Schedule' => 'At 12:00 every 5 days']
				]
			],
			// #1 Type change (Monthly -> Weekly).
			[
				[
					'row_index' => 3, // Monthly period.
					'fill' => [
						'Period type' => 'Weekly',
						'Every week(s)' => '4',
						'id:weekly_days_1' => true
					],
					'expected' => ['Period type' => 'Weekly', 'Schedule' => 'At 23:00 Monday of every 4 weeks']
				]
			]
		];
	}

	/**
	 * @dataProvider getPeriodUpdateData
	 */
	public function testFormMaintenance_PeriodFormUpdate($data) {
		$target_name = self::MAINTENANCE_NAME.' (for period checks)';
		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();
		$this->query('link', $target_name)->one()->click();

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$periods_table = $dialog->query(self::PERIODS_TABLE)->asTable()->one();

		// Open the period overlay for the specific row.
		$periods_table->getRow($data['row_index'])->query('button:Edit')->one()->click();
		$overlay = COverlayDialogElement::find(1)->waitUntilReady()->one()->asForm();

		// Fill and submit changes.
		$overlay->fill($data['fill']);
		$overlay->submit();
		$overlay->waitUntilNotVisible();

		// Check period table is updated accordingly.
		$this->assertTableHasData([$data['expected']], self::PERIODS_TABLE);

		// Save the maintenance form and check changes in periods are saved.
		$dialog->asForm()->submit();
		$this->assertMessage(TEST_GOOD, 'Maintenance period updated');

		$this->query('link', $target_name)->one()->click();
		COverlayDialogElement::find()->waitUntilReady();
		$this->assertTableHasData([$data['expected']], self::PERIODS_TABLE);
		COverlayDialogElement::closeAll();
	}

	public function testFormMaintenance_Clone() {
		$clone_name = self::MAINTENANCE_NAME.' (cloned)';

		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();
		$this->query('link', self::MAINTENANCE_NAME)->one()->waitUntilClickable()->click();
		COverlayDialogElement::find()->waitUntilReady();

		// Clone maintenance, rename the clone and save it.
		$this->query('button:Clone')->one()->click()->waitUntilNotVisible();
		$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
		$form->fill(['Name' => $clone_name]);
		$form->submit();
		COverlayDialogElement::ensureNotPresent();

		// Check the result in maintenance page.
		$this->assertMessage(TEST_GOOD, 'Maintenance period created');
		$this->assertTableHasData([['Name' => self::MAINTENANCE_NAME], ['Name' => $clone_name]]);

		// Open cloned maintenance form and check field values.
		$this->query('link', $clone_name)->one()->waitUntilClickable()->click();
		$clone_data = self::EXPECTED_MAINTENANCE;
		$clone_data['Name'] = $clone_name;
		$this->checkMaintenanceForm($clone_data);
		COverlayDialogElement::find()->one()->close();

		// Check values in DB.
		foreach ([self::MAINTENANCE_NAME, $clone_name] as $maintenance_name) {
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM maintenances WHERE name='.zbx_dbstr($maintenance_name)));
		}
	}

	public function testFormMaintenance_Delete() {
		$maintenance_id = CDBHelper::getValue('SELECT maintenanceid FROM maintenances WHERE name='.zbx_dbstr(self::MAINTENANCE_NAME));

		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();
		$this->query('link', self::MAINTENANCE_NAME)->one()->waitUntilClickable()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();

		// Delete a maintenance and check the result in frontend.
		$dialog->getFooter()->query('button:Delete')->one()->click();
		$this->page->waitUntilAlertIsPresent();
		$this->assertEquals('Delete maintenance period?', $this->page->getAlertText());
		$this->page->acceptAlert();
		COverlayDialogElement::ensureNotPresent();
		$this->assertMessage(TEST_GOOD, 'Maintenance period deleted');

		// Check values in DB.
		$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM maintenances WHERE name='.zbx_dbstr(self::MAINTENANCE_NAME)));
		$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM maintenance_tag WHERE maintenanceid='.$maintenance_id));
	}

	/**
	 * Check the content of the Maintenance form and the Periods table.
	 *
	 * @param array      $data     expected values for the Maintenance form fields
	 * @param array|null $periods  expected data for the Periods table
	 */
	private function checkMaintenanceForm($data = self::EXPECTED_MAINTENANCE, $periods = self::EXPECTED_PERIODS) {
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->asForm();

		$form->checkValue($data);

		if ($periods !== null) {
			$this->assertTableHasData($periods, self::PERIODS_TABLE);
		}
	}
}
