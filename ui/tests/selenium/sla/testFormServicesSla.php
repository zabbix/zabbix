<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../traits/TableTrait.php';

/**
 * @backup sla
 *
 * @dataSource Sla
 */
class testFormServicesSla extends CWebTest {

	use TableTrait;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return ['class' => CMessageBehavior::class];
	}

	private static $sla_sql = 'SELECT * FROM sla ORDER BY slaid';
	private static $update_sla = 'Update SLA';
	private static $sla_with_downtimes = 'SLA with schedule and downtime';
	private static $delete_sla = 'SLA для удаления - 頑張って';

	/**
	 * Check SLA create form layout.
	 */
	public function testFormServicesSla_Layout() {
		$this->page->login()->open('zabbix.php?action=sla.list');
		$this->query('button:Create SLA')->waitUntilClickable()->one()->click();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$this->assertEquals('New SLA', $dialog->getTitle());
		$form = $dialog->query('id:sla-form')->asForm()->one();

		// Check tabs available in the form.
		$this->assertEquals(json_encode(['SLA', 'Excluded downtimes']), json_encode($form->getTabs()));

		// Check fields in SLA tab.
		$sla_tab_labels = [
			'Name',
			'SLO',
			'Reporting period',
			'Time zone',
			'Schedule',
			'Effective date',
			'Service tags',
			'Description',
			'Enabled'
		];

		$this->assertEquals(array_merge($sla_tab_labels, ['Excluded downtimes']), $form->getLabels()->asText());
		foreach ($sla_tab_labels as $label) {
			$this->assertTrue($form->getField($label)->isVisible());
		}

		// Check that mandatory fields are marked accordingly.
		foreach(['Name', 'SLO', 'Effective date', 'Service tags'] as $sla_label) {
			$this->assertEquals('form-label-asterisk', $form->getLabel($sla_label)->getAttribute('class'));
		}

		// Check radio buttons and their values.
		$radio_buttons = [
			[
				'name' => 'Reporting period',
				'values' => ['Daily', 'Weekly', 'Monthly', 'Quarterly', 'Annually'],
				'default' => 'Weekly'
			],
			[
				'name' => 'Schedule',
				'values' => ['24x7', 'Custom'],
				'default' => '24x7'
			]
		];

		foreach ($radio_buttons as $radio_params) {
			$radio_element = $form->getField($radio_params['name']);
			$this->assertEquals($radio_params['default'], $radio_element->getText());
			$this->assertEquals($radio_params['values'], $radio_element->getLabels()->asText());
		}

		// Check that schedule table is not visible if the 24x7 schedule is selected.
		$schedule_table = $form->query('id:schedule')->one();
		$this->assertFalse($schedule_table->isVisible());

		// Switch to custom schedule
		$form->getField('Schedule')->fill('Custom');
		$this->assertTrue($schedule_table->isVisible());

		// Check that schedule table contains rows for each day and their order.
		$this->assertEquals(['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
				$schedule_table->query("xpath:.//label")->all()->asText()
		);

		// Check SLA tab input fields maxlength, placeholders and default values.
		$inputs = [
			[
				'field' => 'Name',
				'maxlength' => 255
			],
			[
				'field' => 'SLO',
				'maxlength' => 7,
				'placeholder' => '99.9'
			],
			[
				'field' => 'id:effective_date',
				'maxlength' => 10,
				'placeholder' => 'YYYY-MM-DD',
				'value' => date('Y-m-d')
			],
			[
				'field' => 'id:service_tags_0_tag',
				'maxlength' => 255,
				'placeholder' => 'tag'
			],
			[
				'field' => 'id:service_tags_0_value',
				'maxlength' => 255,
				'placeholder' => 'value'
			],
			[
				'field' => 'id:description',
				'maxlength' => 65535
			],
			[
				'field' => 'id:schedule_periods_0',
				'maxlength' => 255,
				'placeholder' => '8:00-17:00, …',
				'disabled' => 'true'
			],
			[
				'field' => 'id:schedule_periods_1',
				'maxlength' => 255,
				'placeholder' => '8:00-17:00, …',
				'value' => '8:00-17:00'
			],
			[
				'field' => 'id:schedule_periods_2',
				'maxlength' => 255,
				'placeholder' => '8:00-17:00, …',
				'value' => '8:00-17:00'
			],
			[
				'field' => 'id:schedule_periods_3',
				'maxlength' => 255,
				'placeholder' => '8:00-17:00, …',
				'value' => '8:00-17:00'
			],
			[
				'field' => 'id:schedule_periods_4',
				'maxlength' => 255,
				'placeholder' => '8:00-17:00, …',
				'value' => '8:00-17:00'
			],
			[
				'field' => 'id:schedule_periods_5',
				'maxlength' => 255,
				'placeholder' => '8:00-17:00, …',
				'value' => '8:00-17:00'
			],
			[
				'field' => 'id:schedule_periods_6',
				'maxlength' => 255,
				'placeholder' => '8:00-17:00, …',
				'disabled' => 'true'
			]
		];
		$this->checkInputs($inputs, $form);

		// Check that there's a percentage symbol after the SLo input element.
		$this->assertEquals('%', $form->query('xpath:.//input[@id="slo"]/..')->one()->getText());

		// Check that date picker is present.
		$this->assertTrue($form->query('id:effective_date_calendar')->one()->isVisible());

		$dropdowns = [
			'Time zone' => [
				'count' => 426,
				'default' => 'System default: (UTC+00:00) UTC'
			],
			'name:service_tags[0][operator]' => [
				'values' => ['Equals', 'Contains'],
				'default' => 'Equals'
			]
		];

		$this->checkDropdowns($dropdowns, $form);

		// Check checkboxes default values.
		$checkboxes = [
			'id:schedule_enabled_0' => false,
			'id:schedule_enabled_1' => true,
			'id:schedule_enabled_2' => true,
			'id:schedule_enabled_3' => true,
			'id:schedule_enabled_4' => true,
			'id:schedule_enabled_5' => true,
			'id:schedule_enabled_6' => false,
			'id:status' => true
		];
		foreach ($checkboxes as $locator => $checked) {
			$this->assertEquals($checked, $form->query($locator)->asCheckbox()->one()->isChecked());
		}

		$tags_table_elements = [
			'headers' => ['Name', 'Operation', 'Value', 'Action'],
			'buttons' => ['Add', 'Remove'],
			'count' => 2
		];
		// Check tags table headers.
		$this->checkTableElements($tags_table_elements, $form->query('id:service-tags')->asMultifieldTable()->one());

		// Check the layout of the Excluded downtimes tab.
		$form->selectTab('Excluded downtimes');
		$downtimes_table = $form->query('id:excluded-downtimes')->asMultifieldTable()->waitUntilVisible()->one();

		$downtimes_table_elements = [
			'headers' => ['Start time', 'Duration', 'Name', 'Action'],
			'buttons' => ['Add', 'Edit', 'Remove'],
			'count' => 1
		];
		// Check tags table headers.
		$this->checkTableElements($downtimes_table_elements, $downtimes_table);

		$downtimes_table->query('button:Add')->one()->click();
		$downtimes_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
		$this->assertEquals('New excluded downtime', $downtimes_dialog->getTitle());

		$downtimes_form = $downtimes_dialog->asForm();

		$downtime_labels = ['Name', 'Start time', 'Duration'];
		$this->assertEquals($downtime_labels, $downtimes_form->getLabels()->asText());

		// Check that all three fields are marked as mandatory.
		foreach($downtime_labels as $downtime_label) {
			$this->assertEquals('form-label-asterisk', $downtimes_form->getLabel($downtime_label)->getAttribute('class'));
		}

		$duration_field = $downtimes_form->getField('Duration');
		foreach(['Days', 'Hours', 'Minutes'] as $string) {
			$this->assertStringContainsString($string, $duration_field->getText());
		}

		$downtime_dropdowns = [
			'name:duration_hours' => [
				'count' => 24,
				'default' => '1'
			],
			'name:duration_minutes' => [
				'count' => 60,
				'default' => '0'
			]
		];
		$this->checkDropdowns($downtime_dropdowns, $downtimes_form);

		// Check downtime dialog input fields maxlength, placeholders and default values.
		$downtime_inputs = [
			[
				'field' => 'Name',
				'maxlength' => 255,
				'placeholder' => 'short description'
			],
			[
				'field' => 'id:start_time',
				'maxlength' => 16,
				'placeholder' => 'YYYY-MM-DD hh:mm',
				'value' => date('Y-m-d',strtotime(date('Y-m-d')."+1 days")).' 00:00'
			],
			[
				'field' => 'id:duration_days',
				'maxlength' => 4,
				'value' => '0'
			]
		];
		$this->checkInputs($downtime_inputs, $downtimes_form);

		// Check that date picker is present.
		$this->assertTrue($downtimes_form->query('id:start_time_calendar')->one()->isVisible());

		// Check that both Cancel and Add buttons are present and clickable.
		$this->assertEquals(2, $downtimes_dialog->query('button', ['Add', 'Cancel'])->all()
				->filter(new CElementFilter(CElementFilter::CLICKABLE))->count()
		);

		// Save a scheduled downtime and check how its displayed in the corresponding table.
		$downtimes_form->getField('Name')->fill('!@#$%^&*()_+123Zabbix');
		$downtimes_form->submit();
		$downtimes_dialog->waitUntilNotVisible();

		$downtimes_table->invalidate();

		$table_data = [
			[
				'Start time' => date('Y-m-d',strtotime(date('Y-m-d')."+1 days")).' 00:00',
				'Duration' => '1h',
				'Name' => '!@#$%^&*()_+123Zabbix',
				'Action' => 'Edit Remove'
			]
		];
		$this->assertTableData($table_data, 'id:excluded-downtimes');
	}

	public function getSlaData() {
		return [
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => '',
						'SLO' => '99.9',
						'id:service_tags_0_tag' => 'tag'
					],
					'error' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => ' ',
						'SLO' => '99.9',
						'id:service_tags_0_tag' => 'tag'
					],
					'error' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			// Duplicate SLA name
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => self::$sla_with_downtimes,
						'SLO' => '99.9',
						'id:service_tags_0_tag' => 'tag'
					],
					'error' => 'SLA "'.self::$sla_with_downtimes.'" already exists.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Missing SLO',
						'SLO' => '',
						'id:service_tags_0_tag' => 'tag'
					],
					'error' => 'Incorrect value for field "slo": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Non-nuneric SLO',
						'SLO' => '123abc',
						'id:service_tags_0_tag' => 'tag'
					],
					'error' => 'Invalid parameter "/1/slo": a floating point value is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Negative SLO',
						'SLO' => '-66.6',
						'id:service_tags_0_tag' => 'tag'
					],
					'error' => 'Invalid parameter "/1/slo": value must be within the range of 0-100'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'SLO higher than 100',
						'SLO' => '100.001',
						'id:service_tags_0_tag' => 'tag'
					],
					'error' => 'Invalid parameter "/1/slo": value must be within the range of 0-100'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty custom schedule',
						'SLO' => '99.9',
						'id:service_tags_0_tag' => 'tag',
						'Schedule' => 'Custom',
						'id:schedule_enabled_1' => false,
						'id:schedule_enabled_2' => false,
						'id:schedule_enabled_3' => false,
						'id:schedule_enabled_4' => false,
						'id:schedule_enabled_5' => false
					],
					'error' => 'Incorrect schedule: cannot be empty.'
				]
			],
			// TODO: remove the "'id:schedule_enabled_1' => true" line when ZBX-21084 is fixed.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Non time format custom schedule',
						'SLO' => '99.9',
						'id:service_tags_0_tag' => 'tag',
						'Schedule' => 'Custom',
						'id:schedule_enabled_1' => true,
						'id:schedule_periods_1' => 'all day'
					],
					'error' => 'Incorrect schedule: comma separated list of time periods is expected for scheduled week days.'
				]
			],
			// TODO: remove the "'id:schedule_enabled_1' => true" line when ZBX-21084 is fixed.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => '0 seconds custom schedule',
						'SLO' => '99.9',
						'id:service_tags_0_tag' => 'tag',
						'Schedule' => 'Custom',
						'id:schedule_enabled_1' => true,
						'id:schedule_periods_1' => '00:01-00:01'
					],
					'error' => 'Incorrect schedule: comma separated list of time periods is expected for scheduled week days.'
				]
			],
			// TODO: remove the "'id:schedule_enabled_1' => true" line when ZBX-21084 is fixed.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'wrongly formatted time custom schedule',
						'SLO' => '99.9',
						'id:service_tags_0_tag' => 'tag',
						'Schedule' => 'Custom',
						'id:schedule_enabled_1' => true,
						'id:schedule_periods_1' => '00:01-00:61'
					],
					'error' => 'Incorrect schedule: comma separated list of time periods is expected for scheduled week days.'
				]
			],
			// TODO: remove the "'id:schedule_enabled_1' => true" line when ZBX-21084 is fixed.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Incorrect 2nd part of schedule',
						'SLO' => '99.9',
						'id:service_tags_0_tag' => 'tag',
						'Schedule' => 'Custom',
						'id:schedule_enabled_1' => true,
						'id:schedule_periods_1' => '00:01-00:03,00:09-00:09'
					],
					'error' => 'Incorrect schedule: comma separated list of time periods is expected for scheduled week days.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Missing effective date',
						'SLO' => '99.9',
						'id:service_tags_0_tag' => 'tag',
						'Effective date' => ''
					],
					'error' => 'Incorrect value for field "effective_date": a date is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Wrong format effective date',
						'SLO' => '99.9',
						'id:service_tags_0_tag' => 'tag',
						'Effective date' => '10-10-2022'
					],
					'error' => 'Incorrect value for field "effective_date": a date is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Non-existing date in effective date',
						'SLO' => '99.9',
						'id:service_tags_0_tag' => 'tag',
						'Effective date' => '2022-02-30'
					],
					'error' => 'Incorrect value for field "effective_date": a date is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Effective date too far in the future',
						'SLO' => '99.9',
						'id:service_tags_0_tag' => 'tag',
						'Effective date' => '2060-01-01'
					],
					'error' => 'Invalid parameter "/1/effective_date": a number is too large'
				]
			],
			// TODO: change the error message when ZBX-21085 will be fixed.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Effective date too far in the past',
						'SLO' => '99.9',
						'id:service_tags_0_tag' => 'tag',
						'Effective date' => '1965-01-01'
					],
					'error' => 'Invalid parameter "/1/effective_date": value must be one of 0-2147483647.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Missing tag name',
						'SLO' => '99.9',
						'id:service_tags_0_tag' => '',
						'id:service_tags_0_value' => 'value'
					],
					'error' => 'Invalid parameter "/1/service_tags/1/tag": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Only space in tag name',
						'SLO' => '99.9',
						'id:service_tags_0_tag' => ' ',
						'id:service_tags_0_value' => 'value'
					],
					'error' => 'Invalid parameter "/1/service_tags/1/tag": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Missing excluded downtime name',
						'SLO' => '99.9',
						'id:service_tags_0_tag' => 'tag'
					],
					'excluded_downtimes' => [
						[
							'Name' => ''
						]
					],
					'error' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Only space excluded downtime name',
						'SLO' => '99.9',
						'id:service_tags_0_tag' => 'tag'
					],
					'excluded_downtimes' => [
						[
							'Name' => ' '
						]
					],
					'error' => 'Incorrect value for field "name": cannot be empty.'
				]
			],
			// TODO: Uncomment data provider when ZBX-21085 will be fixed.
//			[
//				[
//					'expected' => TEST_BAD,
//					'fields' => [
//						'Name' => 'Excluded downtime start too far in the future',
//						'SLO' => '99.9',
//						'id:service_tags_0_tag' => 'tag'
//					],
//					'excluded_downtimes' => [
//						[
//							'Name' => 'Starts too far in the future',
//							'Start time' =>  '2222-01-01 00:00'
//						]
//					],
//					'error' => 'Invalid parameter "/1/excluded_downtimes/1/period_from": a number is too large.'
//				]
//			],
//			[
//				[
//					'expected' => TEST_BAD,
//					'fields' => [
//						'Name' => 'Excluded downtime start too far in the past',
//						'SLO' => '99.9',
//						'id:service_tags_0_tag' => 'tag'
//					],
//					'excluded_downtimes' => [
//						[
//							'Name' => 'Start too far in the past',
//							'Start time' =>  '1965-01-01 00:00'
//						]
//					],
//					'error' => 'Invalid parameter "/1/excluded_downtimes/1/period_from": a number is too far in the past.'
//				]
//			],
//			[
//				[
//					'expected' => TEST_BAD,
//					'fields' => [
//						'Name' => 'Excluded downtime ends too far in the future',
//						'SLO' => '99.9',
//						'id:service_tags_0_tag' => 'tag'
//					],
//					'excluded_downtimes' => [
//						[
//							'Name' => 'Ends too far in the future',
//							'Start time' =>  '2038-01-01 00:00',
//							'id:duration_days' => 9999
//						]
//					],
//					'error' => 'Invalid parameter "/1/excluded_downtimes/1/period_to": a number is too large.'
//				]
//			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Only time in excluded downtime start time',
						'SLO' => '99.9',
						'id:service_tags_0_tag' => 'tag'
					],
					'excluded_downtimes' => [
						[
							'Name' => 'Only time downtime',
							'Start time' => '00:00'
						]
					],
					'error' => 'Incorrect value for field "start_time": a time is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Non-existing date in excluded downtime start time',
						'SLO' => '99.9',
						'id:service_tags_0_tag' => 'tag'
					],
					'excluded_downtimes' => [
						[
							'Name' => 'Non-existing date in downtime',
							'Start time' => '2022-02-30 00:00'
						]
					],
					'error' => 'Incorrect value for field "start_time": a time is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Non-existing time in excluded downtime start time',
						'SLO' => '99.9',
						'id:service_tags_0_tag' => 'tag'
					],
					'excluded_downtimes' => [
						[
							'Name' => 'Non-existing time in downtime',
							'Start time' => '2022-05-20 24:01'
						]
					],
					'error' => 'Incorrect value for field "start_time": a time is expected.'
				]
			],
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Trailing and leading spaces in excluded downtime start time',
						'SLO' => '99.9',
						'id:service_tags_0_tag' => 'tag'
					],
					'excluded_downtimes' => [
						[
							'Name' => 'Trailing and leading spaces in downtime',
							'Start time' => '  2022-05-20  '
						]
					],
					'error' => 'Incorrect value for field "start_time": a time is expected.'
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Only mandatory fields',
						'SLO' => '1',
						'id:service_tags_0_tag' => 'tag'
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => 'All Mandatory and optional fields',
						'SLO' => '33.33',
						'Reporting period' => 'Quarterly',
						'Time zone' => '(UTC-02:00) America/Nuuk',
						'Schedule' => 'Custom',
						'id:service_tags_0_tag' => 'tag',
						'name:service_tags[0][operator]' => 'Contains',
						'id:service_tags_0_value' => 'мфдгу',
						'id:schedule_enabled_0' => true,
						'id:schedule_enabled_6' => true,
						'id:schedule_enabled_1' => false,
						'id:schedule_enabled_2' => false,
						'id:schedule_enabled_3' => false,
						'id:schedule_enabled_4' => false,
						'id:schedule_enabled_5' => false,
						'id:schedule_periods_0' => '01:33-02:44, 03:55-04:11',
						'id:schedule_periods_6' => '20:33-21:22, 22:55-23:44',
						'Description' => 'SLA description',
						'Enabled' => false
					],
					'excluded_downtimes' => [
						[
							'Name' => '1st successful excluded downtime',
							'Start time' => '2030-06-01 11:11',
							'name:duration_days' => 3,
							'name:duration_hours' => 2,
							'name:duration_minutes' => 1
						],
						[
							'Name' => 'Second successful excluded downtime',
							'Start time' => '2020-06-01 22:22',
							'name:duration_days' => 10,
							'name:duration_hours' => 11,
							'name:duration_minutes' => 12
						]
					]
				]
			],
			[
				[
					'fields' => [
						'Name' => '   Check triming trailing and leading spaces   ',
						'SLO' => '  7.77  ',
						'Reporting period' => 'Quarterly',
						'Time zone' => '(UTC-02:00) America/Nuuk',
						'Schedule' => 'Custom',
						'id:service_tags_0_tag' => '  trim tag  ',
						'name:service_tags[0][operator]' => 'Contains',
						'id:service_tags_0_value' => '   trim value   ',
						'id:schedule_enabled_1' => true,
						'id:schedule_enabled_2' => true,
						'id:schedule_enabled_3' => false,
						'id:schedule_enabled_4' => false,
						'id:schedule_enabled_5' => false,
						'id:schedule_enabled_4' => false,
						'id:schedule_enabled_5' => false,
						'id:schedule_periods_1' => '   00:00-24:00   ',
						'id:schedule_periods_2' => '   01:23-02:34, 03:45-04:56   ',
						'Description' => '   SLA description   ',
						'Enabled' => false
					],
					'excluded_downtimes' => [
						[
							'Name' => '   !@#$%^&*()_+   ',
							'Start time' => '2020-01-01 00:00',
							'name:duration_days' => 6,
							'name:duration_hours' => 6,
							'name:duration_minutes' => 6
						]
					],
					'trim' => [
						'fields' => [
							'Name',
							'SLO',
							'id:service_tags_0_tag',
							'id:service_tags_0_value',
							'id:schedule_periods_1',
							'id:schedule_periods_2'
						],
						'excluded_downtimes' => 'Name'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getSlaData
	 */
	public function testFormServicesSla_Create($data) {
		$this->checkAction($data);
	}

	/**
	 * @dataProvider getSlaData
	 */
	public function testFormServicesSla_Update($data) {
		$this->checkAction($data, true);
	}

	/**
	 * This is failing because the effective date is being updated (seconds) and hash differs.
	 * It looks like a bug to me, but should be discussed with A. Verza or A. Vladišev.
	 */
	public function testFormServicesSla_SimpleUpdate() {
		$old_hash = CDBHelper::getHash(self::$sla_sql);

		$this->page->login()->open('zabbix.php?action=sla.list');
		$this->query('link', self::$sla_with_downtimes)->waitUntilClickable()->one()->click();

		$form = COverlayDialogElement::find()->waitUntilReady()->one()->asForm();
		$form->submit();

		$this->assertMessage(TEST_GOOD, 'SLA updated');
		$this->assertEquals($old_hash, CDBHelper::getHash(self::$sla_sql));
	}

	public function testFormServicesSla_Delete() {
		// Get ID of the SLA to be deleted.
		$id_to_delete = CDataHelper::get('Sla.sla_ids')[self::$delete_sla];

		$this->page->login()->open('zabbix.php?action=sla.list');
		$this->query('link', self::$delete_sla)->waitUntilClickable()->one()->click();

		// Click on the Delete button in the opened SLA configuration dialog.
		COverlayDialogElement::find()->waitUntilReady()->one()->query('button:Delete')->waitUntilClickable()->one()->click();
		$this->page->acceptAlert();
		$this->page->waitUntilReady();

		$this->assertMessage(TEST_GOOD, 'SLA deleted');
		$this->assertFalse($this->query('link', self::$delete_sla)->one(false)->isValid());

		// Check that the records associated with the deleted SLA are not present in all SLA related tables.
		foreach (['sla', 'sla_excluded_downtime', 'sla_schedule', 'sla_service_tag'] as $table) {
			$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM '.$table.' WHERE slaid='.$id_to_delete));
		}
	}

	public function getCancelData() {
		return [
			[
				[
					'action' => 'create'
				]
			],
			[
				[
					'action' => 'update'
				]
			]
		];
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testFormServicesSla_Cancel($data) {
		$new_values = [
			'Name' => 'New name to Cancel',
			'SLO' => '77.777',
			'Reporting period' => 'Annually',
			'Time zone' => '(UTC+01:00) Africa/Bangui',
			'Effective date' => '2022-09-10',
			'id:service_tags_0_tag' => 'tag',
			'id:service_tags_0_value' => 'value',
			'Description' => 'SLA descruption',
			'Enabled' => false
		];
		$old_hash = CDBHelper::getHash(self::$sla_sql);
		$locator = ($data['action'] === 'create') ? 'button:Create SLA' : 'link:'.self::$update_sla;

		$this->page->login()->open('zabbix.php?action=sla.list');
		$this->query($locator)->one()->click();

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->asForm();
		$form->fill($new_values);
		$dialog->query('button:Cancel')->one()->click();

		$dialog->ensureNotPresent();
		$this->assertEquals($old_hash, CDBHelper::getHash(self::$sla_sql));
	}

	public function testFormServicesSla_Clone() {
		$this->page->login()->open('zabbix.php?action=sla.list');
		$this->query('link', self::$sla_with_downtimes)->waitUntilClickable()->one()->click();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
		$form = $dialog->asForm();
		$original_values = $form->getFields()->asValues();

		// Get the Excluded downtimes before cloning.
		$form->selectTab('Excluded downtimes');
		$original_downtimes = $form->getField('Excluded downtimes')->asTable()->getRows()->asText();

		$dialog->query('button:Clone')->waitUntilClickable()->one()->click();
		$dialog->waitUntilReady();
		$form->invalidate();
		$form->selectTab('SLA');
		$name = 'Clone: '.self::$sla_with_downtimes;
		$form->fill(['Name' => $name]);
		$form->submit();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'SLA created');
		// Check that there are 2 SLAs whose name contain the name of the original SLA (the clone has prefix "Clone:").
		$this->assertEquals(2, CDBHelper::getCount('SELECT * FROM sla WHERE name LIKE ('.zbx_dbstr('%'.self::$sla_with_downtimes).')'));

		// Check cloned sla saved form.
		$this->query('link', $name)->waitUntilClickable()->one()->click();
		$form->invalidate();
		$original_values['Name'] = $name;
		$this->assertEquals($original_values, $form->getFields()->asValues());

		// Check Excluded downtimes were cloned.
		$form->selectTab('Excluded downtimes');
		$this->assertEquals($original_downtimes, $form->getField('Excluded downtimes')->asTable()->getRows()->asText());
	}

	/**
	 * Check SLA creation or update form validation and successful submission.
	 *
	 * @param array		$data		data provider
	 * @param boolean	$update		flag that determines whether update or create operation should be checked
	 */
	private function checkAction($data, $update = false) {
		$expected = CTestArrayHelper::get($data, 'expected', TEST_GOOD);

		// Excluded downtimes dialog validation is made without attempting to save the SLA, so no hash needed.
		if ($expected === TEST_BAD && !array_key_exists('excluded_downtimes', $data)) {
			$old_hash = CDBHelper::getHash(self::$sla_sql);
		}

		// Open service form depending on create or update scenario.
		$this->page->login()->open('zabbix.php?action=sla.list');
		if ($update) {
			$this->query('link', self::$update_sla)->waitUntilClickable()->one()->click();
		}
		else {
			$this->query('button:Create SLA')->waitUntilClickable()->one()->click();
		}

		$form = COverlayDialogElement::find()->waitUntilReady()->one()->asForm();

		// Add a prefix to the name of the SLA in case of update scenario to avoid duplicate names.
		if ($update && CTesTArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_GOOD) {
			$data['fields']['Name'] = 'Update: '.$data['fields']['Name'];
		}

		$form->fill($data['fields']);

		// Add excluded downtimes if such specified.
		if (array_key_exists('excluded_downtimes', $data)) {
			$form->selectTab('Excluded downtimes');
			$add_button = $form->query('id:excluded-downtimes')->waitUntilVisible()->one()->query('button:Add')->one();

			foreach ($data['excluded_downtimes'] as $downtime) {
				$add_button->waitUntilClickable()->click();
				$downtimes_form = COverlayDialogElement::find()->all()->last()->waitUntilReady()->asForm();
				$downtimes_form->fill($downtime);
				$downtimes_form->submit();
			}

			// Excluded downtimes ar validated in their configuration dialog, so the error message should be checked here.
			if ($expected === TEST_BAD) {
				$this->assertMessage(TEST_BAD, null, $data['error']);

				return;
			}

		}
		$form->submit();
		$this->page->waitUntilReady();

		if ($expected === TEST_BAD) {
			$this->assertMessage(TEST_BAD, null, $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash(self::$sla_sql));
		}
		else {
			$this->assertMessage(TEST_GOOD, ($update ? 'SLA updated' : 'SLA created'));

			// Trim leading nad trailing spaces from expected results if necessary.
			if (array_key_exists('trim', $data)) {
				foreach ($data['trim'] as $section => $fields) {
					if ($section === 'excluded_downtimes') {
						$data[$section][0][$fields] = trim($data[$section][0][$fields]);
					}
					else {
						foreach ($fields as $field) {
							$data[$section][$field] = trim($data[$section][$field]);
						}
					}
				}
			}

			$db_data = CDBHelper::getColumn('SELECT * FROM sla', 'name');
			$this->assertTrue(in_array($data['fields']['Name'], $db_data));

			if ($update) {
				// For update scenarios check that old name is not present anymore.
				$this->assertFalse(in_array(self::$update_sla, $db_data));

				//  Write new name to global variable for using it in next case.
				self::$update_sla = $data['fields']['Name'];
			}

			$this->query('link', $data['fields']['Name'])->waitUntilClickable()->one()->click();
			$form->invalidate();
			$form->checkValue($data['fields']);

			if (array_key_exists('excluded_downtimes', $data)) {
				$form->selectTab('Excluded downtimes');
				$downtimes_table = $this->query('id:excluded-downtimes')->asMultifieldTable()->waitUntilVisible()->one();

				foreach ($data['excluded_downtimes'] as $downtime) {
					$expected = [
						'Start time' => $downtime['Start time'],
						'Duration' => $downtime['name:duration_days'].'d '.$downtime['name:duration_hours'].'h '.
								$downtime['name:duration_minutes'].'m',
						'Name' => $downtime['Name']
					];
					$row = $downtimes_table->findRow('Name', $expected['Name']);

					foreach ($expected as $column => $value) {
						$this->assertEquals($expected[$column], $row->getColumn($column)->getText());
					}

					$row->query('button:Edit')->one()->click();

					$downtimes_form = COverlayDialogElement::find()->all()->last()->waitUntilReady()->asForm();

					$downtimes_form->checkValue($downtime);
					$downtimes_form->submit();
				}
			}
		}
	}

	/**
	 * Check attributes of input elements.
	 *
	 * @param array			$inputs		reference array with expected input attribute values
	 * @param CFormElement	$form		form that contains the input elements to be checked
	 */
	private function checkInputs($inputs, $form) {
		foreach ($inputs as $input) {
			// Empty attribute "value" is present for all inputs in this form that are empty by default.
			$input['value'] = CTestArrayHelper::get($input, 'value', '');
			$field = $form->getField($input['field']);

			// Check the attribute value or confirm that it doesn't exist.
			foreach (['maxlength', 'placeholder', 'value', 'disabled'] as $attribute) {
				if (array_key_exists($attribute, $input)) {
					$this->assertEquals($input[$attribute], $field->getAttribute($attribute));
				}
				else {
					$this->assertFalse($field->isAttributePresent($attribute));
				}
			}
		}
	}

	/**
	 * Check table headers and buttons.
	 *
	 * @param array						$data		reference array with expected table elements data
	 * @param CMultifieldTableElement	$table		table that contains the elements to be checked
	 */
	private function checkTableElements($data, $table) {
		// Check table headers.
		$this->assertSame($data['headers'], $table->getHeadersText());

		// Check the buttons that are clickable.
		$this->assertEquals($data['count'], $table->query('button', $data['buttons'])->all()
				->filter(new CElementFilter(CElementFilter::CLICKABLE))->count()
		);
	}

	/**
	 * Check all possible options (or their count) and the default option for the provided dropdown element.
	 *
	 * @param array			$dropdowns		reference array with dropdown data
	 * @param CFormElement	$form			$form element that contains the dropdowns to be checked
	 */
	private function checkDropdowns($dropdowns, $form) {
		foreach ($dropdowns as $field => $parameters) {
			$dropdown = $form->getField($field);

			// Check default dropdown value.
			$this->assertEquals($parameters['default'], $dropdown->getText());

			// Check available options or their count.
			if (array_key_exists('count', $parameters)) {
				$timezones = $dropdown->getOptions()->asText();
				$this->assertEquals($parameters['count'], count($timezones));
			}
			else {
				$this->assertSame($parameters['values'], $dropdown->getOptions()->asText());
			}
		}
	}
}
