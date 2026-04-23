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

	public function prepareMaintenanceData() {
		CDataHelper::call('maintenance.create', [
			[
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

			]
		]);
	}

	const EXPECTED_MAINTENANCE = [
		'Name' => self::MAINTENANCE_NAME,
		'Maintenance type' => 'With data collection',
		'Active since' => '2023-04-18 00:01',
		'Active till' => '2030-03-10 23:59',
		'Host groups' => 'Zabbix servers',
		'id:tags_evaltype' => 'Or',
		'id:tags_0_tag' => 'Tag1',
		'id:tags_0_operator' => 'Contains',
		'id:tags_0_value' => 'A',
		'id:tags_1_tag' => 'Tag2',
		'id:tags_1_operator' => 'Equals',
		'id:tags_1_value' => 'B',
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


	// opening with Create done, but layout needs to be checked with edit as well
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

	// done, except screenshots
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
			}

			// Check maintenance duration dropdown (hours and minutes) values.
			foreach (['name:period_hours' => 23, 'name:period_minutes' => 59] as $name => $max) {
				$options = $period_overlay->getField($name)->asDropdown()->getOptions()->asText();
				$this->assertEquals(range(0, $max), array_map('intval', $options));
			}

			if ($mode === 'Create') {
				// Check footer buttons.
				$this->assertEquals(['Add', 'Cancel'], $dialog->getFooter()->query('button')->all()
						->filter(CElementFilter::CLICKABLE)->asText()
				);
				// Check screenshots.
				$this->page->removeFocus();

				// Remove Add and Cancel buttons edge curling from screenshots as their rendering is unstable.
				$dialog_footer = COverlayDialogElement::find()->waitUntilReady()->all()->last()->getFooter();
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
				$period_overlay->fill(['Period type' => 'One time only']);
				$period_overlay->submit();
				$period_overlay->waitUntilNotVisible();
			} else {
				// Check footer buttons.
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

	//done
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

	/**
	 * Create maintenance with periods and host group.
	 */
	/*public function testFormMaintenance_Create() {
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
	}*/

	public static function getCreateData() {
		return [
			// #0 Minimal required fields
			[
				[
					'fields' => [
						'Name' => 'Minimal fields',
						'Hosts' => 'ЗАББИКС Сервер'
					]
				]
			],
			// #1 Maximally filling all possible fields
			[
				[
					'fields' => [
						'Name' => 'Maximal fields',
						'Maintenance type' => 'With data collection',
						'Active since' => '1970-01-02 00:00',
						'Active till' => '2038-01-19 00:00',
						'Host groups' => ['Zabbix servers', 'Hypervisors'],
						'Hosts' => ['ЗАББИКС Сервер'],//vajag ieimportet vel vienu hostu mosh
						'id:tags_evaltype' => 'Or',
						'Description' => 'This is a maintenance description for covering all fields.'
					],
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => 'TagOne',
							'value' => 'ValueOne'
						],
						[
							'tag' => 'TagTwo',
							'value' => 'ValueTwo'
						]
					],
					'periods' => [
						[
							'fields' => [
								'Period type' => 'One time only',
								'Date' => '2026-03-20 15:30',
								'name:period_hours' => '1',
								'name:period_minutes' => '30'
							]
						],
						[
							'fields' => [
								'Period type' => 'Daily',
								'id:every_day' => '2',
								'name:period_hours' => '0',
								'name:period_minutes' => '5'
							]
						],
						[
							'fields' => [
								'Period type' => 'Weekly',
								'id:weekly_days_1' => true,
								'id:weekly_days_4' => true,
								'id:weekly_days_16' => true,
								'id:every_week' => '2',
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
					'expected_periods' => [
						[
							'Period type' => 'One time only',
							'Schedule' => '2026-03-20 15:30',
							'Period' => '1h 30m'
						],
						[
							'Period type' => 'Daily',
							'Schedule' => 'At 00:00 every 2 days',
							'Period' => '5m'
						],
						[
							'Period type' => 'Weekly',
							'Schedule' => 'At 05:30 Monday, Wednesday, Friday of every 2 weeks',
							'Period' => '1d 2h 40m'
						],
						[
							'Period type' => 'Monthly',
							'Schedule' => 'At 23:55 on second Wednesday, Sunday of every January, July',
							'Period' => '2d 20h 10m'
						]
					]
				]
			],
			// #2 Leading and trailing spaces
			[
				[
					'fields' => [
						'Name' => '  Trim test  ',
						'Active since' => '2025-01-01 00:00', // 8.0 šiem arī var likt atstarpes
						'Active till' => '2030-01-01 00:00',
						'Host groups' => 'Zabbix servers',
						'Hosts' => 'ЗАББИКС Сервер',
						'Description' => '  This description should be trimmed  '
					],
					'tags' => [
						[
							'action' => USER_ACTION_UPDATE,
							'index' => 0,
							'tag' => '  TagTrim  ',
							'value' => '  ValueTrim  '
						]
					],
					'expected' => [
						'Name' => 'Trim test',
						'Active since' => '2025-01-01 00:00', // 8.0 šiem arī var likt atstarpes
						'Active till' => '2030-01-01 00:00',
						'Host groups' => 'Zabbix servers',
						'Hosts' => 'ЗАББИКС Сервер',
						'Description' => 'This description should be trimmed',
						'id:tags_0_tag' => 'TagTrim',
						'id:tags_0_value' => 'ValueTrim'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCreateData
	 */
	public function testFormMaintenance_Create($data) {
		$this->page->login()->open('zabbix.php?action=maintenance.list')->waitUntilReady();
		$this->query('button:Create maintenance period')->one()->click();
		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->asForm();

		$form->fill($data['fields']);

		// Fill Tags if they exist
		if (array_key_exists('tags', $data)) {
			$form->query('id:tags')->asMultifieldTable()->one()->fill($data['tags']);
		}

		//Use provided periods or default to 'Daily'
		$periods = array_key_exists('periods', $data)
			? $data['periods']
			: [['fields' => ['Period type' => 'Daily']]];

		foreach ($periods as $period) {
			$form->getField('Periods')->query('button:Add')->one()->click();
			$period_overlay = COverlayDialogElement::find()->waitUntilReady()->all()->last()->asForm();
			$period_overlay->fill($period['fields']);
			$period_overlay->submit();
			$period_overlay->waitUntilNotVisible();
		}

		$form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Maintenance period created');

		// Open the newly created maintenance form and assert saved values
		$search_name = trim($data['fields']['Name']);
		$this->query('link', $search_name)->one()->click();

		//Use 'expected' if defined, otherwise use 'fields'
		$expected_form = $data['expected'] ?? $data['fields'];
		//Use 'expected_periods' if defined, otherwise use 'Daily' with default values
		$expected_periods = array_key_exists('expected_periods', $data)
			? $data['expected_periods']
			: [['Period type' => 'Daily', 'Schedule' => 'At 00:00 every 1 day', 'Period' => '1h']];

		$this->checkMaintenanceForm($expected_form, $expected_periods);
		$dialog->close();

		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM maintenances WHERE name='.zbx_dbstr($search_name)));
		//$this->assertEquals(3, CDBHelper::getCount('SELECT NULL FROM maintenance_tag WHERE value='.zbx_dbstr($value)));
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

	//done
	//nedrikst but aiz update tags, tad kriit, jo tagi mainijusies
	// nu vai ari vajag tags update laikaa updeitot const EXPECTED_MAINTENANCE
	public function testFormMaintenance_Clone() {
		$suffix = ' (cloned)';
		$clone_name = self::MAINTENANCE_NAME.$suffix;

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

		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM maintenances WHERE name='.zbx_dbstr(self::MAINTENANCE_NAME)));
		$this->assertEquals(1, CDBHelper::getCount('SELECT NULL FROM maintenances WHERE name='.zbx_dbstr($clone_name)));
	}

	//done
	public function testFormMaintenance_Delete() {
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

		$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM maintenances WHERE name='.zbx_dbstr(self::MAINTENANCE_NAME)));
	}

	/**
	 * Check the content of the Maintenance form and the Periods table.
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
