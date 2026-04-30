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

	const EXPECTED_TAGS = [
		['tag' => 'Tag1', 'operator' => 'Contains', 'value' => 'A'],
		['tag' => 'Tag2', 'operator' => 'Equals', 'value' => 'B']
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

	protected static $update_maintenance = [
		'fields'=> self::EXPECTED_MAINTENANCE,
		'tags' => self::EXPECTED_TAGS,
		'periods' => self::EXPECTED_PERIODS
	];

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

			if ($is_update) {
				$this->query('link', self::MAINTENANCE_NAME)->one()->waitUntilClickable()->click();
			} else {
				$this->query('button:Create maintenance period')->one()->waitUntilClickable()->click();
			}

			$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
			$form = $dialog->asForm();

			if (!$is_update) {
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
					'id:tags_0_operator' => 'Contains',
					'id:tags_0_value' => '',
					'Description' => ''
				];

				$form->checkValue($default_state);
			}

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

			// Check Periods and Tags table buttons.
			$this->assertEquals(1, $form->getField('Periods')->query('button:Add')->all()->filter((CElementFilter::CLICKABLE))->count());
			if ($is_update) {
				$this->assertEquals(2, $form->query(self::PERIODS_TABLE)->asTable()->one()->getRow(0)->query('button', ['Edit', 'Remove'])
					->all()->filter(CElementFilter::CLICKABLE)->count());
			}

			$tags_table = $form->query('id:tags')->asMultifieldTable()->one();
			$this->assertEquals(1, $tags_table->getRow(0)->query('button:Remove')->all()
				->filter(CElementFilter::CLICKABLE)->count()
			);
			$this->assertEquals(1, $tags_table->query('button:Add')->all()
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
			$this->assertEquals(0, $tags_table->getRow(0)->query('button:Remove')->all()
				->filter(CElementFilter::CLICKABLE)->count()
			);
			$this->assertEquals(0, $tags_table->query('button:Add')->all()
				->filter(CElementFilter::CLICKABLE)->count()
			);

			// Change back to 'With data collection' and verify tags table elements are re-enabled.
			$form->fill(['Maintenance type' => 'With data collection']);
			foreach ($state_changing_fields as $field) {
				$this->assertTrue($form->getField($field)->isEnabled());
			}
			$this->assertEquals(1, $tags_table->getRow(0)->query('button:Remove')->all()
				->filter(CElementFilter::CLICKABLE)->count()
			);
			$this->assertEquals(1, $tags_table->query('button:Add')->all()
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

				$list = COverlayDialogElement::find()->waitUntilReady()->all()->last();
				$this->assertEquals($field, $list->getTitle());
				$this->assertEquals(['Select', 'Cancel'], $list->getFooter()->query('button')->all()
					->filter(CElementFilter::CLICKABLE)->asText()
				);
				$list->close();
			}

			if ($is_update) {
				$expected_footer = ['Update', 'Clone', 'Delete', 'Cancel'];
			} else {
				$expected_footer = ['Add', 'Cancel'];
			}

			// Check footer buttons.
			$this->assertEquals($expected_footer, $dialog->getFooter()->query('button')->all()
					->filter(CElementFilter::CLICKABLE)->asText()
			);
		}
	}

	// one screenshot failing
	public function testFormMaintenance_PeriodFormLayout() {
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

		$datetime_now = date('Y-m-d H:i');
		$datetime_plus_one_minute = date('Y-m-d H:i', strtotime($datetime_now . ' +1 minute'));

		foreach (['Create', 'Edit'] as $mode) {
			if ($mode === 'Create') {
				$form->getField('Periods')->query('button:Add')->one()->waitUntilClickable()->click();
			} else {
				$periods_table->getRow(0)->query('button:Edit')->one()->click();
			}

			$dialog = COverlayDialogElement::find()->waitUntilReady()->all()->last();
			$period_overlay = $dialog->asForm();

			// Check initial default state.
			$period_overlay->checkValue([
				'Period type' => 'One time only',
				'id:period_days' => '0',
				'name:period_hours' => '1',
				'name:period_minutes' => '0'
			]);

			$this->assertTrue($period_overlay->checkValue(['Date' => $datetime_now], false)
					|| $period_overlay->checkValue(['Date' => $datetime_plus_one_minute], false));


			foreach ($periods as $period_type) {
				if ($period_type === 'Monthly with Day of week period') {
					$period_overlay->fill(['Period type' => 'Monthly', 'id:month_date_type' => 'Day of week']);
				} else {
					$period_overlay->fill(['Period type' => $period_type]);
				}

				$period_overlay->waitUntilReady();

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
						$this->assertScreenshotExcept($period_overlay, [$period_overlay->query('id:start_date')->one()], $period_type);
					} else {
						$this->assertScreenshot($period_overlay, $period_type);
					}
				}

				// Initialize expectations.
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
						$this->assertTrue($period_overlay->query('id:start_date_calendar')->one()->isClickable());
						break;

					case 'Daily':
						$expected_required = ['Every day(s)'];
						$check_fields = [
							'id:every_day' => ['maxlength' => 3]
						];
						$expected_default_values += [
							'id:every_day' => '1',
							'id:hour' => '00',
							'id:minute' => '00'
						];
						break;

					case 'Weekly':
						$expected_required = ['Every week(s)', 'Day of week'];
						$check_fields = [
							'id:every_week' => ['maxlength' => 2]
						];
						$expected_default_values += [
							'id:every_week' => '1',
							'id:hour' => '00',
							'id:minute' => '00',
							'Day of week' => []
						];
						// Days ID's are powers of two.
						for ($i = 0; $i <= 6; $i++) {
							$this->assertTrue($period_overlay->query('id:weekly_days_'.pow(2, $i))->one()->isEnabled());
						}
						break;

					case 'Monthly':
						$expected_required = ['Month', 'Day of month'];
						$check_fields = [
							'id:day' => ['maxlength' => 2]
						];
						$expected_default_values += [
							'id:month_date_type' => 'Day of month',
							'id:day' => '1',
							'id:hour' => '00',
							'id:minute' => '00',
							'Month' => []
						];
						// Months ID's are powers of two.
						for ($i = 0; $i <= 11; $i++) {
							$this->assertTrue($period_overlay->query('id:months_'.pow(2, $i))->one()->isEnabled());
						}
						break;

					case 'Monthly with Day of week period':
						$expected_required = ['Month', 'Day of week'];
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
								$period_overlay->query('name:every_dow')->asDropdown()->one()->getOptions()->asText());

						// Days ID's are powers of two.
						for ($i = 0; $i <= 6; $i++) {
							$this->assertTrue($period_overlay->query('id:monthly_days_'.pow(2, $i))->one()->isEnabled());
						}
						break;
				}

				// Common fields for all period types.
				if ($period_type !== 'One time only') {
					$period_overlay->checkValue($expected_default_values);
					$check_fields += [
						'id:hour' => ['maxlength' => 2],
						'id:minute' => ['maxlength' => 2]
					];
				}
				$check_fields += [
					'id:period_days' => ['maxlength' => 3]
				];
				$expected_required[] = 'Maintenance period length';

				$this->assertEquals($expected_required, $period_overlay->getRequiredLabels());

				foreach ($check_fields as $field => $attributes) {
					foreach ($attributes as $attribute => $value) {
						$this->assertEquals($value, $period_overlay->getField($field)->getAttribute($attribute));
					}
				}

				// Check screenshots.
				/*if ($mode === 'Create') {
					$this->page->removeFocus();

					// Remove Add and Cancel buttons edge curling from screenshots as their rendering is unstable.
					$dialog_footer = $dialog->getFooter();
					foreach (['Add', 'Cancel'] as $button) {
						$this->page->getDriver()->executeScript('arguments[0].style.borderRadius=0;',
							[$dialog_footer->query('button', $button)->one()]
						);
					}

					if ($period_type === 'One time only') {
						$this->assertScreenshotExcept($period_overlay, [$period_overlay->query('id:start_date')->one()], $period_type);
					} else {
						$this->assertScreenshot($period_overlay, $period_type);
					}
				}*/
			}

			// Check maintenance duration dropdown (hours and minutes) values.
			foreach (['name:period_hours' => 23, 'name:period_minutes' => 59] as $name => $max) {
				$options = $period_overlay->getField($name)->asDropdown()->getOptions()->asText();
				$this->assertEquals(range(0, $max), array_map('intval', $options));
			}

			// Check footer buttons.
			if ($mode === 'Create') {
				$this->assertEquals(['Add', 'Cancel'], $dialog->getFooter()->query('button')->all()
						->filter(CElementFilter::CLICKABLE)->asText()
				);

				$period_overlay->fill(['Period type' => 'One time only']);
				$period_overlay->submit();
				$period_overlay->waitUntilNotVisible();
			} else {
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
			// #1 Cancel update without mantenance type change.
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
		$action = $data['action'];

		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();

		if ($action === 'create') {
			$this->query('button:Create maintenance period')->one()->waitUntilClickable()->click();
		}
		else {
			$this->query('link', self::MAINTENANCE_NAME)->waitUntilClickable()->one()->click();
		}

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

			$title = $data['title'];
			if ($title === 'update with maintenance type change') {
				$maintenance_type = 'No data collection';
			}
			else {
				$maintenance_type = 'With data collection';
			}

			$test_data = [
				'field_values' => [
					'Name' => 'Cancel maintenance '.$title,
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
			if ($action === 'update' || $action === 'clone')  {

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

		// Close the form.
		$this->query('button:Cancel')->one()->click();
		COverlayDialogElement::ensureNotPresent();

		// Check the result in DB.
		$this->assertEquals($old_hash, CDBHelper::getHash($sql_hash));

		// Open form to check changes were not saved.
		$this->query('link', self::MAINTENANCE_NAME)->one()->waitUntilClickable()->click();
		$this->checkMaintenanceForm();
		COverlayDialogElement::find()->one()->close();
		COverlayDialogElement::ensureNotPresent();
	}

	public function testFormMaintenance_PeriodFormCancel() {
		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();

		foreach ([false, true] as $is_update) {
			if ($is_update) {
				$this->query('link', self::MAINTENANCE_NAME)->one()->click();
			} else {
				$this->query('button:Create maintenance period')->one()->click();
			}

			$form = COverlayDialogElement::find()->waitUntilReady()->asForm()->one();
			$periods_table = $form->query(self::PERIODS_TABLE)->asTable()->one();

			// Cancel adding a period.
			$initial_count = $periods_table->getRows()->count();
			$form->getField('Periods')->query('button:Add')->one()->click();

			$period_overlay = COverlayDialogElement::find()->waitUntilReady()->all()->last();
			$period_overlay->asForm()->fill(['Period type' => 'Daily']);
			$period_overlay->query('button:Cancel')->one()->click();

			$period_overlay->waitUntilNotVisible();
			$this->assertEquals($initial_count, $periods_table->getRows()->count());

			// Cancel editing a period.
			if ($is_update && $initial_count > 0) {
				$row = $periods_table->getRow(0);
				// Save snapshot of the original row content.
				$old_text = $row->getText();

				$row->query('button:Edit')->one()->click();
				$period_overlay = COverlayDialogElement::find()->waitUntilReady()->all()->last();
				$period_overlay->asForm()->fill(['Period type' => 'Monthly']);
				$period_overlay->query('button:Cancel')->one()->click();

				$period_overlay->waitUntilNotVisible();
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
						'Maintenance type' => 'With data collection',
						'Active since' => '2022-04-05 04:20',
						'Active till' => '2023-05-22 20:45',
						'Hosts' => 'ЗАББИКС Сервер',
						'id:tags_evaltype' => 'And/Or'
					],
					'periods' => [
						[
							'fields' => [
								'Period type' => 'Daily'
							]
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
						'id:tags_evaltype' => 'Or',
						'Description' => 'This is a maintenance description for covering all fields.'
					],
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => 'A TagOne',
							'operator' => 'Contains',
							'value' => 'ValueOne'
						],
						[
							'tag' => 'B TagTwo',
							'operator' => 'Equals',
							'value' => 'ValueTwo'
						],
						[
							'tag' => 'B TagTwo',
							'operator' => 'Contains',
							'value' => 'Two'
						]
					],
					'periods' => [
						[
							'fields' => [
								'Period type' => 'One time only',
								'Date' => '2026-03-20 15:30',
								'id:period_days' => '0',
								'name:period_hours' => '1',
								'name:period_minutes' => '30'
							]
						],
						[
							'fields' => [
								'Period type' => 'One time only',
								'Date' => '2026-03-20 15:30',
								'id:period_days' => '0',
								'name:period_hours' => '2',
								'name:period_minutes' => '40'
							]
						],
						[
							'fields' => [
								'Period type' => 'Daily',
								'id:every_day' => '2',
								'id:hour' => '23',
								'id:minute' => '30',
								'id:period_days' => '0',
								'name:period_hours' => '0',
								'name:period_minutes' => '5'
							]
						],
						[
							'fields' => [
								'Period type' => 'Weekly',
								'id:every_week' => '2',
								'id:weekly_days_1' => true,
								'id:weekly_days_4' => true,
								'id:weekly_days_16' => true,
								'id:hour' => '05',
								'id:minute' => '30',
								'id:period_days' => '1',
								'name:period_hours' => '2',
								'name:period_minutes' => '40'
							]
						],
						[
							'fields' => [
								'Period type' => 'Monthly',
								'id:months_2' => true,
								'id:months_32' => true,
								'id:months_2048' => true,
								'Date' => 'Day of month',
								'id:day' => '14',
								'id:hour' => '22',
								'id:minute' => '30',
								'id:period_days' => '0',
								'name:period_hours' => '0',
								'name:period_minutes' => '30'
							]
						],
						[
							'fields' => [
								'Period type' => 'Monthly',
								'id:months_1' => true,
								'id:months_64' => true,
								'Date' => 'Day of week',
								'name:every_dow' => 'second',
								'id:monthly_days_4' => true,
								'id:monthly_days_64' => true,
								'id:hour' => '23',
								'id:minute' => '55',
								'id:period_days' => '2',
								'name:period_hours' => '20',
								'name:period_minutes' => '10'
							]
						]
					],
					'expected_tags' => [
						[
							'tag' => 'A TagOne',
							'operator' => 'Contains',
							'value' => 'ValueOne'
						],
						[
							'tag' => 'B TagTwo',
							'operator' => 'Contains',
							'value' => 'Two'
						],
						[
							'tag' => 'B TagTwo',
							'operator' => 'Equals',
							'value' => 'ValueTwo'
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
							'fields' => [
								'Period type' => 'Daily'
							]
						]
					]
				]
			],
			// #3 Long strings with allowed symbols in all fields.
			[
				[
					'fields' => [
						'Name' => 'Very long name-., /!?@#$%^&*()_=+[]{}\| ;:<>/ бц 頑張って 😀 &nbsp; \t \r \n 1E+308 %00',
						'Maintenance type' => 'With data collection',
						'Active since' => '1970-01-02 00:00',
						'Active till' => '2038-01-19 00:00',
						'Host groups' => 'Zabbix servers',
						'Hosts' => 'ЗАББИКС Сервер',
						'id:tags_evaltype' => 'And/Or',
						'Description' => 'Very long description-., /!?@#$%^&*()_=+[]{}\| ;:<>/ бц 頑張って 😀 &nbsp; \t \r \n 1E+308 %00'
					],
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => 'Very long 1st tag-., /!?@#$%^&*()_=+[]{}\| ;:<>/ бц 頑張って 😀 &nbsp; \t \r \n 1E+308 %00',
							'operator' => 'Equals',
							'value' => 'Very long 1st value-., /!?@#$%^&*()_=+[]{}\| ;:<>/ бц 頑張って 😀 &nbsp; \t \r \n 1E+308 %00'
						],
						[
							'tag' => 'Very long 2nd tag-., /!?@#$%^&*()_=+[]{}\| ;:<>/ бц 頑張って 😀 &nbsp; \t \r \n 1E+308 %00',
							'operator' => 'Contains',
							'value' => 'Very long 2nd value-., /!?@#$%^&*()_=+[]{}\| ;:<>/ бц 頑張って 😀 &nbsp; \t \r \n 1E+308 %00'
						]
					],
					'periods' => [
						[
							'fields' => [
								'Period type' => 'Daily'
							]
						]
					],
					'expected_tags' => [
						[
							'tag' => 'Very long 1st tag-., /!?@#$%^&*()_=+[]{}\| ;:<>/ бц 頑張って 😀 &nbsp; \t \r \n 1E+308 %00',
							'operator' => 'Equals',
							'value' => 'Very long 1st value-., /!?@#$%^&*()_=+[]{}\| ;:<>/ бц 頑張って 😀 &nbsp; \t \r \n 1E+308 %00'
						],
						[
							'tag' => 'Very long 2nd tag-., /!?@#$%^&*()_=+[]{}\| ;:<>/ бц 頑張って 😀 &nbsp; \t \r \n 1E+308 %00',
							'operator' => 'Contains',
							'value' => 'Very long 2nd value-., /!?@#$%^&*()_=+[]{}\| ;:<>/ бц 頑張って 😀 &nbsp; \t \r \n 1E+308 %00'
						]
					]
				]
			],
			// #4 Leading and trailing spaces, incomplete dates.
			[
				[
					'fields' => [
						'Name' => '  Trim test  ',
						'Maintenance type' => 'With data collection',
						'Active since' => '2025-02-03',
						'Active till' => '2030',
						'Host groups' => 'Zabbix servers',
						'Hosts' => 'ЗАББИКС Сервер',
						'id:tags_evaltype' => 'And/Or',
						'Description' => '  This description should be trimmed  '
					],
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => '  TagTrim1  ',
							'operator' => 'Contains',
							'value' => '  ValueTrim1  '
						],
						[
							'tag' => '  TagTrim2  ',
							'operator' => 'Equals',
							'value' => '  ValueTrim2  '
						]
					],
					'periods' => [
						[
							'fields' => [
								'Period type' => 'Daily'
							]
						]
					],
					'expected_fields' => [
						'Name' => 'Trim test',
						'Active since' => '2025-02-03 00:00',
						'Active till' => '2030-01-01 00:00',
						'Host groups' => 'Zabbix servers',
						'Hosts' => 'ЗАББИКС Сервер',
						'Description' => 'This description should be trimmed',
					],
					'expected_tags' => [
						[
							'tag' => 'TagTrim1',
							'operator' => 'Contains',
							'value' => 'ValueTrim1'
						],
						[
							'tag' => 'TagTrim2',
							'operator' => 'Equals',
							'value' => 'ValueTrim2'
						]
					]
				]
			],
			// #5 All mandatory form fields (that are triggered at the same time) empty.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => '',
						'Maintenance type' => 'With data collection',
						'Active since' => '',
						'Active till' => '',
						'id:tags_evaltype' => 'And/Or'
					],
					'error_details' => [
						'Incorrect value for field "name": cannot be empty.',
						'Incorrect value for field "active_since": a time is expected.',
						'Incorrect value for field "active_till": a time is expected.',
						'Field "timeperiods" is mandatory.'
					]
				]
			],
			// #6 Hosts and host groups empty.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Hosts and host groups empty',
						'Maintenance type' => 'With data collection',
						'Active since' => '2031-02-03 14:30',
						'Active till' => '2034-09-01 22:55',
						'Host groups' => '',
						'Hosts' => '',
						'id:tags_evaltype' => 'And/Or'
					],
					'periods' => [
						[
							'fields' => [
								'Period type' => 'Daily'
							]
						]
					],
					'error_details' => [
						'At least one host group or host must be selected.'
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
							'fields' => [
								'Period type' => 'Daily'
							]
						]
					],
					'error_details' => [
						'Maintenance "'.self::MAINTENANCE_NAME.'" already exists.'
					]
				]
			],
			// #8 Duplicate tags.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Duplicate tags',
						'Host groups' => 'Zabbix servers'
					],
					'periods' => [
						[
							'fields' => [
								'Period type' => 'Daily'
							]
						]
					],
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => 'DuplicateTag',
							'value' => 'DuplicateValue'
						],
						[
							'tag' => 'DuplicateTag',
							'value' => 'DuplicateValue'
						]
					],
					'error_details' => [
						'Invalid parameter "/1/tags/2": value (tag, operator, value)=(DuplicateTag, 2, DuplicateValue) already exists.'
					]
				]
			],
			// #9 Empty tag name.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty tag name',
						'Host groups' => 'Zabbix servers'
					],
					'periods' => [
						[
							'fields' => [
								'Period type' => 'Daily'
							]
						]
					],
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => '',
							'value' => 'OnlyValue'
						]
					],
					'error_details' => [
						'Invalid parameter "/1/tags/1/tag": cannot be empty.'
					]
				]
			],
			// #10 Active since greater than Active till.
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
						[
							'fields' => [
								'Period type' => 'Daily'
							]
						]
					],
					'error_details' => [
						'Invalid parameter "/1/active_till": cannot be less than or equal to the value of parameter "/1/active_since".'
					]
				]
			],
		];
	}

	/**
	 * @dataProvider getCreateUpdateData
	 */
	public function testFormMaintenance_Create($data) {
		$expected = CTestArrayHelper::get($data, 'expected', TEST_GOOD);
		if ($expected === TEST_BAD) {
			$sql = 'SELECT * FROM maintenances ORDER BY maintenanceid';
			$old_hash = CDBHelper::getHash($sql);
		}

		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();
		$this->query('button:Create maintenance period')->one()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->asForm();

		$form->fill($data['fields']);

		// Fill tags if they exist.
		if (array_key_exists('tags', $data)) {
			$tags_table = $form->query('id:tags')->asMultifieldTable()->one();
			$tags_table->setFieldMapping(['tag', 'operator', 'value']);
			$tags_table->fill($data['tags']);
		};

		// Fill periods if they exist.
		if (array_key_exists('periods', $data)) {
			foreach ($data['periods'] as $period) {
				$form->getField('Periods')->query('button:Add')->one()->click();
				$period_overlay = COverlayDialogElement::find()->waitUntilReady()->all()->last()->asForm();
				$period_overlay->fill($period['fields']);
				$period_overlay->submit();
				$period_overlay->waitUntilNotVisible();
			}
		}

		$form->submit();
		$this->page->waitUntilReady();

		if ($expected === TEST_BAD) {
			$this->assertMessage(TEST_BAD, 'Cannot create maintenance period', $data['error_details']);
			// Check that DB hash has not changed.
			$this->assertEquals($old_hash, CDBHelper::getHash($sql));
			$dialog->close();
		} else {
			$this->assertMessage(TEST_GOOD, 'Maintenance period created');

			$search_name = trim($data['fields']['Name']);
			$this->query('link', $search_name)->one()->click();

			//Use 'expected_fields' if defined, otherwise use 'fields'.
			$expected_form = $data['expected_fields'] ?? $data['fields'];
			//Use 'expected_periods' if defined, otherwise if one period defined use 'Daily' with default values.
			$expected_periods = CTestArrayHelper::get($data, 'expected_periods');
			if ($expected_periods === null && count($data['periods']) === 1) {
				$expected_periods = [['Period type' => 'Daily', 'Schedule' => 'At 00:00 every 1 day', 'Period' => '1h']];
			}
			// If tags have been entered, use 'expected_tags' if defined, otherwise use 'tags'.
			if (array_key_exists('tags', $data)) {
				$expected_tags = $data['expected_tags'] ?? $data['tags'];
			} else {
				$expected_tags = [];
			}

			$this->checkMaintenanceForm($expected_form, $expected_periods, $expected_tags);
			$dialog->close();

			// Check values in DB.
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM maintenances WHERE name='.zbx_dbstr($search_name)));

			$expected_tag_count = array_key_exists('tags', $data) ? count($data['tags']) : 0;
			$sql_tag_count = 'SELECT NULL FROM maintenance_tag WHERE maintenanceid='.
					'(SELECT maintenanceid FROM maintenances WHERE name='.zbx_dbstr($search_name).')';

			$this->assertEquals($expected_tag_count, CDBHelper::getCount($sql_tag_count));
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

	/**
	 * @dataProvider getCreateUpdateData
	 */
	public function testFormMaintenance_Update($data) {
		$expected = CTestArrayHelper::get($data, 'expected', TEST_GOOD);
		if ($expected === TEST_BAD) {
			$sql = 'SELECT * FROM maintenances ORDER BY maintenanceid';
			$old_hash = CDBHelper::getHash($sql);
		}

		// A suffix is added to TEST_GOOD update scenarios in order to avoid name duplication with create TEST_GOOD scenarios.
		if ($expected === TEST_GOOD) {
			$prefix = 'Update - ';
			$name = $data['fields']['Name'];
			$trimmed_name = trim($name);

			// Update the input (preserving outer spaces), e.g., '  Trim test  ' becomes '  Update - Trim test  '.
			$data['fields']['Name'] = str_replace($trimmed_name, $prefix . $trimmed_name, $name);

			// Update expectation (which is always already trimmed), e.g., 'Trim test' becomes 'Update - Trim test'.
			if (array_key_exists('expected_fields', $data) && array_key_exists('Name', $data['expected_fields'])) {
				$data['expected_fields']['Name'] = $prefix . $data['expected_fields']['Name'];
			}
		}

		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();

		$current_name = self::$update_maintenance['fields']['Name'];
		$this->query('link', $current_name)->one()->waitUntilClickable()->click();

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->asForm();

		// Fill changes.
		$form->fill($data['fields']);

		$tags_table = $form->query('id:tags')->asMultifieldTable()->one();
		$add_button_tags_table = $tags_table->query('button:Add')->one();

		// Check tags table is enabled (is disabled in the case when Maintenance type is No data collection).
		if ($add_button_tags_table->isEnabled()) {

			// Remove all tags table rows except one.
			$tag_rows = $tags_table->getRows();
			if ($tag_rows->count() > 1) {
				// Remove all rows except the first one (index 0).
				foreach ($tag_rows->slice(1) as $row) {
					$row->query('button:Remove')->one()->click();
				}
			}

			// Fill tags if they exist.
			if (array_key_exists('tags', $data)) {
				$tags_table->setFieldMapping(['tag', 'operator', 'value']);
				$tags_table->fill($data['tags']);
			} else {
				// Clear values from the 1st tag row.
				$row0 = $tags_table->getRow(0);
				$row0->query('xpath:.//input[contains(@id, "_tag")]')->one()->clear();
				$row0->query('xpath:.//input[contains(@id, "_value")]')->one()->clear();
			}
		}

		// Remove all periods.
		$periods_table = $this->query(self::PERIODS_TABLE)->asTable()->one();
		if ($periods_table->getRows()->count() >= 1) {
			foreach ($periods_table->getRows() as $row) {
				$row->query('button:Remove')->one()->click();
			}
		}

		// Fill periods if they exist.
		if (array_key_exists('periods', $data)) {
			foreach ($data['periods'] as $period) {
				$form->getField('Periods')->query('button:Add')->one()->click();
				$period_overlay = COverlayDialogElement::find()->waitUntilReady()->all()->last()->asForm();
				$period_overlay->fill($period['fields']);
				$period_overlay->submit();
				$period_overlay->waitUntilNotVisible();
			}
		}

		$form->submit();

		if ($expected === TEST_BAD) {
			$this->assertMessage(TEST_BAD, 'Cannot update maintenance period', $data['error_details']);
			// Check that DB hash has not changed.
			$this->assertEquals($old_hash, CDBHelper::getHash($sql));
			$dialog->close();
		} else {
			$this->assertMessage(TEST_GOOD, 'Maintenance period updated');

			$search_name = trim($data['fields']['Name']);
			$this->query('link', $search_name)->one()->click();

			//Use 'expected_fields' if defined, otherwise use 'fields'.
			$expected_form = $data['expected_fields'] ?? $data['fields'];
			//Use 'expected_periods' if defined, otherwise if one period defined use 'Daily' with default values.
			$expected_periods = CTestArrayHelper::get($data, 'expected_periods');
			if ($expected_periods === null && count($data['periods']) === 1) {
				$expected_periods = [['Period type' => 'Daily', 'Schedule' => 'At 00:00 every 1 day', 'Period' => '1h']];
			}
			// If tags have been entered, use 'expected_tags' if defined, otherwise use 'tags'.
			if (array_key_exists('tags', $data)) {
				$expected_tags = $data['expected_tags'] ?? $data['tags'];
			} else {
				$expected_tags = [];
			}

			$this->checkMaintenanceForm($expected_form, $expected_periods, $expected_tags);
			$dialog->close();

			self::$update_maintenance = [
				'fields' => $expected_form,
				'tags' => $expected_tags,
				'periods' => $expected_periods
			];

			// Check values in DB.
			$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM maintenances WHERE name='.zbx_dbstr($search_name)));

			$expected_tag_count = array_key_exists('tags', $data) ? count($data['tags']) : 0;
			$sql_tag_count = 'SELECT NULL FROM maintenance_tag WHERE maintenanceid='.
					'(SELECT maintenanceid FROM maintenances WHERE name='.zbx_dbstr($search_name).')';

			$this->assertEquals($expected_tag_count, CDBHelper::getCount($sql_tag_count));
		}
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
					'error' => 'Incorrect value for field "start_date": a time is expected.'
				]
			],
			// #1 Daily with 'Every day(s)' 0 and period length 0 hours and 0 minutes.
			[
				[
					'fields' => [
						'Period type' => 'Daily',
						'id:every_day' => '0',
						'id:period_days' => '0',
						'name:period_hours' => '0',
						'name:period_minutes' => '0'
					],
					'error' => [
						'Incorrect value for field "every_day": value must be no less than "1".',
						'Incorrect maintenance period (minimum 5 minutes)'
					]
				]
			],
			// #2 Daily with invalid hour and minute values.
			[
				[
					'fields' => [
						'Period type' => 'Daily',
						'id:hour' => '98',
						'id:minute' => '99'
					],
					'error' => [
						'Incorrect value for field "hour": value must be no greater than "23".',
						'Incorrect value for field "minute": value must be no greater than "59".'
					]
				]
			],
			// #4 Weekly with 'Every week' 0 and no days selected.
			[
				[
					'fields' => [
						'Period type' => 'Weekly',
						'id:every_week' => '0'
					],
					'error' => [
						'Incorrect value for field "every_week": value must be no less than "1".',
						'Field "weekly_days" is mandatory.'
					]
				]
			],
			// #5 Monthly (Day of month) with empty months and 'Day of month' 0.
			[
				[
					'fields' => [
						'Period type' => 'Monthly',
						'id:day' => '0'
					],
					'error' => [
						'Field "months" is mandatory.',
						'Incorrect value for field "day": value must be no less than "1".'
					]
				]
			],
			// #6 Monthly (Day of week) with empty months and no days selected.
			[
				[
					'fields' => [
						'Period type' => 'Monthly',
						'id:month_date_type' => 'Day of week'
					],
					'error' => [
						'Field "months" is mandatory.',
						'Field "monthly_days" is mandatory.'
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
			} else {
				$this->query('link', $target)->one()->click();
			}

			$form = COverlayDialogElement::find()->waitUntilReady()->one()->asForm();

			if ($scenario['period_action'] === 'add') {
				$form->getField('Periods')->query('button:Add')->one()->click();
			} else {
				$form->query(self::PERIODS_TABLE)->asTable()->one()->getRow(1)->query('button:Edit')->one()->click();
			}

			// Fill invalid data and submit.
			$overlay = COverlayDialogElement::find()->waitUntilReady()->all()->last();
			$overlay->asForm()->fill($data['fields'])->submit();

			$this->assertMessage(TEST_BAD, null, $data['error']);

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
						'id:every_day' => '5',
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
						'id:every_week' => '4',
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
		$overlay = COverlayDialogElement::find()->waitUntilReady()->all()->last()->asForm();

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
		COverlayDialogElement::find()->waitUntilReady()->one();
		$this->assertTableHasData([$data['expected']], self::PERIODS_TABLE);
		COverlayDialogElement::closeAll();
	}

	public function testFormMaintenance_Clone() {
		$suffix = ' (cloned)';
		$clone_name = self::MAINTENANCE_NAME.$suffix;

		$sql_original = 'SELECT NULL FROM maintenance_tag WHERE maintenanceid='.
			'(SELECT maintenanceid FROM maintenances WHERE name='.zbx_dbstr(self::MAINTENANCE_NAME).')';
		$expected_tag_count = CDBHelper::getCount($sql_original);

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
		$clone_data = array_merge(self::EXPECTED_MAINTENANCE, ['Name' => $clone_name]);
		$this->checkMaintenanceForm($clone_data);
		COverlayDialogElement::find()->one()->close();

		// Check values in DB.
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM maintenances WHERE name='.zbx_dbstr(self::MAINTENANCE_NAME)));
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM maintenances WHERE name='.zbx_dbstr($clone_name)));

		$sql_clone = 'SELECT NULL FROM maintenance_tag WHERE maintenanceid='.
			'(SELECT maintenanceid FROM maintenances WHERE name='.zbx_dbstr($clone_name).')';

		$this->assertEquals($expected_tag_count, CDBHelper::getCount($sql_original));
		$this->assertEquals($expected_tag_count, CDBHelper::getCount($sql_clone));
	}

	public function testFormMaintenance_Delete() {
		$maintenance_id = CDBHelper::getValue('SELECT maintenanceid FROM maintenances WHERE name='.zbx_dbstr(self::MAINTENANCE_NAME));

		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();
		$this->query('link', self::MAINTENANCE_NAME)->one()->waitUntilClickable()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();

		// Delete a maintenance and check the result in frontend.
		$dialog->getFooter()->query('button:Delete')->one()->click();
		$this->assertTrue($this->page->isAlertPresent());
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
	 */
	private function checkMaintenanceForm($data = self::EXPECTED_MAINTENANCE, $periods = self::EXPECTED_PERIODS, $tags = self::EXPECTED_TAGS) {
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->asForm();

		$form->checkValue($data);

		if ($periods !== null) {
			$this->assertTableHasData($periods, self::PERIODS_TABLE);
		}

		if ($tags !== []) {
			$tags_table = $form->query('id:tags')->asMultifieldTable()->one();
			$tags_table->setFieldMapping(['tag', 'operator', 'value']);
			$tags_table->checkValue($tags);
		}
	}
}
