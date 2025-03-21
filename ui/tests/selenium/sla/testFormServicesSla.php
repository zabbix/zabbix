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
require_once __DIR__.'/../behaviors/CMessageBehavior.php';
require_once __DIR__.'/../behaviors/CTableBehavior.php';

/**
 * @backup sla
 *
 * @dataSource Services, Sla
 */
class testFormServicesSla extends CWebTest {

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
		$default_values = [
			'Name' => '',
			'SLO' => '',
			'Reporting period' => 'Weekly',
			'Time zone' => CDateTimeHelper::getTimeZoneFormat('System default'),
			'Schedule' => '24x7',
			'Effective date' => date('Y-m-d'),
			'Description' => '',
			'Enabled' => true,
			'name:service_tags[0][operator]' => 'Equals'
		];
		$form->checkValue($default_values);

		// Note that count of available timezones may differ based on the local environment configuration and php version.
		$timezones = $form->getField('Time zone')->getOptions()->asText();
		$this->assertGreaterThan(415, count($timezones));
		$this->assertContains(CDateTimeHelper::getTimeZoneFormat('Europe/Riga'), $timezones);

		// Check that mandatory fields are marked accordingly.
		foreach (['Name', 'SLO', 'Effective date', 'Service tags'] as $sla_label) {
			$this->assertEquals('form-label-asterisk', $form->getLabel($sla_label)->getAttribute('class'));
		}

		// Check radio buttons and their labels.
		$radio_buttons = [
			'Reporting period' => ['Daily', 'Weekly', 'Monthly', 'Quarterly', 'Annually'],
			'Schedule' => ['24x7', 'Custom']
		];

		foreach ($radio_buttons as $name => $labels) {
			$this->assertEquals($labels, $form->getField($name)->getLabels()->asText());
		}

		// Check that schedule table is not visible if the 24x7 schedule is selected.
		$schedule_table = $form->query('id:schedule')->one();
		$this->assertFalse($schedule_table->isVisible());

		// Switch to custom schedule
		$form->getField('Schedule')->fill('Custom');
		$this->assertTrue($schedule_table->isVisible());

		// Check custom schedule checkboxes default values.
		$days = [
			'Sunday' => false,
			'Monday' => true,
			'Tuesday' => true,
			'Wednesday' => true,
			'Thursday' => true,
			'Friday' => true,
			'Saturday' => false
		];
		$form->checkValue($days);

		// Check the default status of the SLA.
		$this->assertTrue($form->getField('Enabled')->isChecked());

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

		// Check available tag operations.
		$this->assertSame(['Equals', 'Contains'], $form->getField('name:service_tags[0][operator]')->getOptions()->asText());

		$tags_table_elements = [
			'headers' => ['Name', 'Operation', 'Value', ''],
			'buttons' => ['Add', 'Remove'],
			'count' => 2
		];
		// Check tags table headers.
		$this->checkTableElements($tags_table_elements, $form->query('id:service-tags')->asMultifieldTable()->one());

		// Check the layout of the Excluded downtimes tab.
		$form->selectTab('Excluded downtimes');
		$downtimes_table = $form->query('id:excluded-downtimes')->asMultifieldTable()->waitUntilVisible()->one();

		$downtimes_table_elements = [
			'headers' => ['Start time', 'Duration', 'Name', 'Actions'],
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

		$downtime_default_values = [
			'Name' => '',
			'Start time' => date('Y-m-d', strtotime(date('Y-m-d')."+1 days")).' 00:00',
			'id:duration_days' => '0',
			'name:duration_hours' => '1',
			'name:duration_minutes' => '0'
		];
		$downtimes_form->checkValue($downtime_default_values);

		// Check that all three fields are marked as mandatory.
		foreach ($downtime_labels as $downtime_label) {
			$downtimes_form->isRequired($downtime_label);
		}

		$duration_field = $downtimes_form->getField('Duration');
		foreach (['Days', 'Hours', 'Minutes'] as $string) {
			$this->assertStringContainsString($string, $duration_field->getText());
		}

		// Check that count of available options in  dropdowns is correct.
		foreach (['name:duration_hours' => 24, 'name:duration_minutes' => 60] as $dropdown => $options_count) {
			$this->assertEquals($options_count, count($downtimes_form->getField($dropdown)->getOptions()->asText()));
		}

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
				'value' => date('Y-m-d', strtotime(date('Y-m-d')."+1 days")).' 00:00'
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

		$table_data = [
			[
				'Start time' => date('Y-m-d', strtotime(date('Y-m-d')."+1 days")).' 00:00',
				'Duration' => '1h',
				'Name' => '!@#$%^&*()_+123Zabbix',
				'Actions' => 'Edit Remove'
			]
		];
		$this->assertTableData($table_data, 'id:excluded-downtimes');

		$dialog->close();
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
						'Name' => 'Non-numeric SLO',
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
						'Monday' => false,
						'Tuesday' => false,
						'Wednesday' => false,
						'Thursday' => false,
						'Friday' => false
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
						'Monday' => true,
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
						'Monday' => true,
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
						'Monday' => true,
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
						'Monday' => true,
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
					'downtime_error' => 'Incorrect value for field "name": cannot be empty.'
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
					'downtime_error' => 'Incorrect value for field "name": cannot be empty.'
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
//					'downtime_error' => 'Invalid parameter "/1/excluded_downtimes/1/period_from": a number is too large.'
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
//					'downtime_error' => 'Invalid parameter "/1/excluded_downtimes/1/period_from": a number is too far in the past.'
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
//					'downtime_error' => 'Invalid parameter "/1/excluded_downtimes/1/period_to": a number is too large.'
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
					'downtime_error' => 'Incorrect value for field "start_time": a time is expected.'
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
					'downtime_error' => 'Incorrect value for field "start_time": a time is expected.'
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
					'downtime_error' => 'Incorrect value for field "start_time": a time is expected.'
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
					'downtime_error' => 'Incorrect value for field "start_time": a time is expected.'
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
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Duplicate excluded downtimes',
						'SLO' => '99.999',
						'id:service_tags_0_tag' => 'tag'
					],
					'excluded_downtimes' => [
						[
							'Name' => 'Duplicate downtime',
							'Start time' => '2030-06-01 11:11',
							'name:duration_days' => 3,
							'name:duration_hours' => 2,
							'name:duration_minutes' => 1
						],
						[
							'Name' => 'Duplicate downtime 2',
							'Start time' => '2030-06-01 11:11',
							'name:duration_days' => 3,
							'name:duration_hours' => 2,
							'name:duration_minutes' => 1
						]
					],
					'error' => 'Invalid parameter "/1/excluded_downtimes/2": value (period_from, period_to)='.
							'(1906531860, 1906798320) already exists.'
				]
			],
			[
				[
					'fields' => [
						'Name' => 'All Mandatory and optional fields',
						'SLO' => '33.33',
						'Reporting period' => 'Quarterly',
						'Time zone' => 'America/Nuuk',
						'Schedule' => 'Custom',
						'id:service_tags_0_tag' => 'tag',
						'name:service_tags[0][operator]' => 'Contains',
						'id:service_tags_0_value' => 'мфдгу',
						'Sunday' => true,
						'Saturday' => true,
						'Monday' => false,
						'Tuesday' => false,
						'Wednesday' => false,
						'Thursday' => false,
						'Friday' => false,
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
						'Name' => '   Check trimming trailing and leading spaces   ',
						'SLO' => '  7.77  ',
						'Reporting period' => 'Quarterly',
						'Time zone' => 'America/Nuuk',
						'Schedule' => 'Custom',
						'id:service_tags_0_tag' => '  trim tag  ',
						'name:service_tags[0][operator]' => 'Contains',
						'id:service_tags_0_value' => '   trim value   ',
						'Monday' => true,
						'Tuesday' => true,
						'Wednesday' => false,
						'Thursday' => false,
						'Friday' => false,
						'Saturday' => false,
						'Sunday' => false,
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

	public function getUpdateSlaData() {
		return [
			[
				[
					'fields' => [
						'Name' => 'Edit excluded downtime'
					],
					'excluded_downtimes' => [
						[
							'Name' => 'Updated downtime',
							'Start time' => '2030-11-11 22:22',
							'name:duration_days' => 6,
							'name:duration_hours' => 5,
							'name:duration_minutes' => 4
						]
					],
					'downtime_action' => 'edit'
				]
			],
			[
				[
					'fields' => [
						'Name' => 'Remove excluded downtime'
					],
					'downtime_action' => 'remove'
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
	 * @dataProvider getUpdateSlaData
	 */
	public function testFormServicesSla_Update($data) {
		$this->checkAction($data, true);
	}

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
		$id_to_delete = CDataHelper::get('Sla.slaids')[self::$delete_sla];

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
			$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM '.$table.' WHERE slaid='.zbx_dbstr($id_to_delete)));
		}
	}

	public function testFormServicesSla_CancelCreate() {
		$this->checkActionCancellation();
	}

	public function testFormServicesSla_CancelUpdate() {
		$this->checkActionCancellation('update');
	}

	/**
	 * Check cancellation of create and update actions
	 *
	 * @param string	$action		action to be checked
	 */
	public function checkActionCancellation($action = 'create') {
		$new_values = [
			'Name' => 'New name to Cancel',
			'SLO' => '77.777',
			'Reporting period' => 'Annually',
			'Time zone' => CDateTimeHelper::getTimeZoneFormat('Africa/Bangui'),
			'Effective date' => '2022-09-10',
			'id:service_tags_0_tag' => 'tag',
			'id:service_tags_0_value' => 'value',
			'Description' => 'SLA descruption',
			'Enabled' => false
		];
		$old_hash = CDBHelper::getHash(self::$sla_sql);
		$locator = ($action === 'create') ? 'button:Create SLA' : 'link:'.self::$update_sla;

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
		$this->assertEquals(2, CDBHelper::getCount('SELECT NULL FROM sla WHERE name LIKE ('.zbx_dbstr('%'.self::$sla_with_downtimes).')'));

		// Check cloned sla saved form.
		$this->query('link', $name)->waitUntilClickable()->one()->click();
		$form->invalidate();
		$original_values['Name'] = $name;
		$this->assertEquals($original_values, $form->getFields()->asValues());

		// Check Excluded downtimes were cloned.
		$form->selectTab('Excluded downtimes');
		$this->assertEquals($original_downtimes, $form->getField('Excluded downtimes')->asTable()->getRows()->asText());

		$dialog->close();
	}

	/**
	 * Check SLA creation or update form validation and successful submission.
	 *
	 * @param array		$data		data provider
	 * @param boolean	$update		flag that determines whether update or create operation should be checked
	 */
	private function checkAction($data, $update = false) {
		$expected = CTestArrayHelper::get($data, 'expected', TEST_GOOD);

		if (array_key_exists('Time zone', $data['fields'])) {
			$data['fields']['Time zone'] = CDateTimeHelper::getTimeZoneFormat($data['fields']['Time zone']);
		}

		// Excluded downtimes dialog validation is made without attempting to save the SLA, so no hash needed.
		if ($expected === TEST_BAD && array_key_exists('error', $data)) {
			$old_hash = CDBHelper::getHash(self::$sla_sql);
		}

		// Open service form depending on create or update scenario.
		$this->page->login()->open('zabbix.php?action=sla.list');

		if ($update) {
			$update_sla = (array_key_exists('downtime_action', $data)) ? self::$sla_with_downtimes : self::$update_sla;
			$this->query('link', $update_sla)->waitUntilClickable()->one()->click();
		}
		else {
			$this->query('button:Create SLA')->waitUntilClickable()->one()->click();
		}

		$dialog = COverlayDialogElement::find()->waitUntilReady()->one();
		$form = $dialog->asForm();

		// Add a prefix to the name of the SLA in case of update scenario to avoid duplicate names.
		if ($update && CTesTArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_GOOD) {
			$data['fields']['Name'] = 'Update: '.$data['fields']['Name'];
		}

		$form->fill($data['fields']);

		// Add excluded downtimes if such specified.
		if (array_key_exists('excluded_downtimes', $data) || array_key_exists('downtime_action', $data)) {
			$form->selectTab('Excluded downtimes');
			$downtimes_table = $this->query('id:excluded-downtimes')->asMultifieldTable()->waitUntilVisible()->one();

			// Remove all excluded downtimes if required or proceed with adding or updating downtimes.
			if (CTestArrayHelper::get($data, 'downtime_action') !== 'remove') {
				foreach ($data['excluded_downtimes'] as $downtime) {
					$button = (CTestArrayHelper::get($data, 'downtime_action') === 'edit') ? 'Edit' : 'Add';
					$downtimes_table->query('button', $button)->waitUntilCLickable()->one()->click();
					$downtimes_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
					$downtimes_form = $downtimes_dialog->asForm();
					$downtimes_form->fill($downtime);

					$downtimes_form->submit();

					if ($expected === TEST_GOOD || !array_key_exists('downtime_error', $data)) {
						$downtimes_form->waitUntilNotVisible();

						// Make sure that row was added to table.
						$downtimes_table->invalidate();
						$name = (array_key_exists('trim', $data)) ? trim($downtime['Name']) : $downtime['Name'];
						$this->assertTrue($downtimes_table->findRow('Name', $name)->isValid());
					}
				}
			}
			else {
				$downtimes_table->clear();
			}

			// Excluded downtimes ar validated in their configuration dialog, so the error message should be checked here.
			if (array_key_exists('downtime_error', $data)) {
				$this->assertMessage(TEST_BAD, null, $data['downtime_error']);
				$downtimes_dialog->close();
				$dialog->close();

				return;
			}
		}
		$form->submit();
		$this->page->waitUntilReady();

		if ($expected === TEST_BAD) {
			$this->assertMessage(TEST_BAD, null, $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash(self::$sla_sql));

			$dialog->close();
		}
		else {
			$this->assertMessage(TEST_GOOD, ($update ? 'SLA updated' : 'SLA created'));

			// Trim leading and trailing spaces from expected results if necessary.
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

			// Remove extra spaces in the middle of the name; spaces in links on page are trimmed.
			$name = CTestArrayHelper::get($data, 'trim', false)
				? preg_replace('/\s+/', ' ', $data['fields']['Name'])
				: $data['fields']['Name'];

			if ($update) {
				// Check that old name is not present anymore and write new name to global variable for future cases.
				if (array_key_exists('downtime_action', $data)) {
					$this->assertFalse(in_array(self::$sla_with_downtimes, $db_data));
					self::$sla_with_downtimes = $data['fields']['Name'];
				}
				else {
					$this->assertFalse(in_array(self::$update_sla, $db_data));
					self::$update_sla = $name;
				}
			}

			$this->query('link', $name)->waitUntilClickable()->one()->click();
			$form->invalidate();
			$form->checkValue($data['fields']);

			if (array_key_exists('excluded_downtimes', $data) || array_key_exists('downtime_action', $data)) {
				$form->selectTab('Excluded downtimes');
				$downtimes_table = $this->query('id:excluded-downtimes')->asMultifieldTable()->waitUntilVisible()->one();

				// Check that downtimes were removed or check downtime configuration.
				if (CTestArrayHelper::get($data, 'downtime_action') !== 'remove') {
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
						$downtimes_form->submit()->waitUntilNotVisible();
					}
				}
				else {
					$this->assertEquals([], $downtimes_table->getRows()->asText());
				}
			}

			COverlayDialogElement::find()->one()->close();
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
			$attributes = ['maxlength', 'placeholder', 'value', 'disabled'];
			$this->assertTrue($field->isAttributePresent(array_intersect_key($input, $attributes)));
			$this->assertFalse($field->isAttributePresent(array_diff_key($input, $attributes)));
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
}
