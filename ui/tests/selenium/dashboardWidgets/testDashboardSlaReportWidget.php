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


require_once __DIR__.'/../common/testSlaReport.php';
require_once __DIR__.'/../../include/helpers/CDataHelper.php';

/**
 * @backup dashboard, profiles
 *
 * @dataSource Services, Sla
 *
 * @onBefore prepareDashboardData
 * @onBefore getDateTimeData
 */
class testDashboardSlaReportWidget extends testSlaReport {

	private static $dashboardid;
	private static $slaid;
	private static $monthly_sla = 'SLA Monthly';
	private static $create_page = 'Page for creating widgets';
	private static $update_widget = 'Update widgets';
	private static $delete_widget = 'Widget for delete';

	private static $default_values = [
		'SLA' => '',
		'Service' => '',
		'From' => '',
		'To' => '',
		'Show periods' => 20
	];

	/*
	 * SQL query to get widget and widget_field tables to compare hash values, but without widget_fieldid
	 * because it can change.
	 */
	private $sql = 'SELECT wf.widgetid, wf.type, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
			' wf.value_itemid, wf.value_graphid, wf.value_sysmapid, w.widgetid, w.dashboard_pageid, w.type, w.name, w.x, w.y,'.
			' w.width, w.height'.
			' FROM widget_field wf'.
			' INNER JOIN widget w'.
			' ON w.widgetid=wf.widgetid ORDER BY wf.widgetid, wf.name, wf.value_int, wf.value_str, wf.value_groupid,'.
			' wf.value_itemid, wf.value_graphid';

	/**
	 * Create dashboards with widgets for test and define the corresponding dashboard ID.
	 */
	public static function prepareDashboardData() {
		self::$slaid = CDBHelper::getValue('SELECT slaid FROM sla WHERE name='.zbx_dbstr(self::$monthly_sla));

		$response = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for SLA report widget tests',
				'private' => 0,
				'auto_start' => 1,
				'pages' => [
					[
						'name' => 'Page with widgets',
						'widgets' => [
							[
								'type' => 'slareport',
								'name' => self::$update_widget,
								'width' => 72,
								'height' => 3,
								'fields' => [
									[
										'type' => 10,
										'name' => 'slaid',
										'value' => self::$slaid
									]
								]
							],
							[
								'type' => 'slareport',
								'name' => self::$delete_widget,
								'x' => 0,
								'y' => 3,
								'width' => 72,
								'height' => 3,
								'fields' => [
									[
										'type' => 10,
										'name' => 'slaid',
										'value' => self::$slaid
									]
								]
							]
						]
					],
					[
						'name' => self::$create_page,
						'widgets' => []
					]
				]
			]
		]);

		self::$dashboardid = $response['dashboardids'][0];
	}

	public function testDashboardSlaReportWidget_ConfigurationFormLayout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);

		// Add a widget.
		$dialog = CDashboardElement::find()->one()->edit()->addWidget();
		$form = $dialog->asForm();
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('SLA report')]);
		$dialog->waitUntilReady();

		$this->assertEquals(['Type', 'Show header', 'Name', 'Refresh interval', 'SLA', 'Service', 'Show periods', 'From', 'To'],
				$form->getLabels()->asText()
		);
		$form->checkValue(['Show header' => true, 'Refresh interval' => 'Default (No refresh)']);

		// Check attributes of input elements.
		$inputs = [
			'Name' => [
				'maxlength' => 255,
				'placeholder' => 'default'
			],
			'id:slaid_ms' => [
				'placeholder' => 'type here to search',
				'aria-required' => 'true'
			],
			'id:serviceid_ms' => [
				'placeholder' => 'type here to search',
				'aria-required' => 'false'
			],
			'Show periods' => [
				'maxlength' => 3,
				'value' => 20
			],
			'id:date_period_from' => [
				'maxlength' => 255,
				'placeholder' => 'YYYY-MM-DD'
			],
			'id:date_period_to' => [
				'maxlength' => 255,
				'placeholder' => 'YYYY-MM-DD'
			]
		];

		foreach ($inputs as $field => $attributes) {
			foreach ($attributes as $attribute => $value) {
				$this->assertEquals($value, $form->getField($field)->getAttribute($attribute));
			}
		}

		// Check that the date pickers are present.
		foreach (['id:date_period_from_calendar', 'id:date_period_to_calendar'] as $selector) {
			$this->assertTrue($form->query($selector)->one()->isVisible());
		}

		// Check the list of available SLAs and services.
		$sla_data = [
			'field' => 'SLA',
			'headers' => ['Name', 'Status'],
			'column_data' => [
				'Name' => [
					'Disabled SLA',
					'Disabled SLA Annual',
					'SLA Annual',
					'SLA Daily',
					'SLA Monthly',
					'SLA Quarterly',
					'SLA Weekly',
					'SLA with schedule and downtime',
					'SLA для удаления - 頑張って', 'Update SLA'
				],
				'Status' => [
					'Disabled',
					'Disabled',
					'Enabled',
					'Enabled',
					'Enabled',
					'Enabled',
					'Enabled',
					'Enabled',
					'Enabled',
					'Enabled'
				]
			],
			'table_selector' => 'xpath://form[@id="sla"]/table',
			'buttons' => ['Cancel']
		];

		$service_data = [
			'field' => 'Service',
			'headers' => ['Name', 'Tags', 'Problem tags'],
			'table_selector' => 'xpath://form[@name="services_form"]/table',
			'buttons' => ['Filter', 'Reset', 'Cancel'],
			'check_row' => [
				'Name' => 'Simple actions service',
				'Tags' => 'problem: falsetest: test789',
				'Problem tags' => 'problem: true'
			]
		];

		foreach ([$sla_data, $service_data] as $dialog_data) {
			$this->checkDialogContents($dialog_data, true);
		}

		$dialog->close();
	}

	public function getSlaReportConfigurationFormData() {
		return [
			// Missing SLA.
			[
				[
					'fields' => [
						'Service' => 'Service with problem'
					],
					'expected' => TEST_BAD,
					'error' => 'Invalid parameter "SLA": cannot be empty.'
				]
			],
			// Non-numeric show periods.
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'Show periods' => 'abc'
					],
					'expected' => TEST_BAD,
					'error' => 'Invalid parameter "Show periods": value must be one of 1-100.'
				]
			],
			// Too large value in show periods.
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'Show periods' => '101'
					],
					'expected' => TEST_BAD,
					'error' => 'Invalid parameter "Show periods": value must be one of 1-100.'
				]
			],
			// Floating point value in show periods.
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'Show periods' => '0.5'
					],
					'expected' => TEST_BAD,
					'error' => 'Invalid parameter "Show periods": value must be one of 1-100.'
				]
			],
			// Negative value in show periods.
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'Show periods' => '-5'
					],
					'expected' => TEST_BAD,
					'error' => 'Invalid parameter "Show periods": value must be one of 1-100.'
				]
			],
			// String type From and To dates.
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'From' => 'yesterday',
						'To' => 'today + 1 day'
					],
					'expected' => TEST_BAD,
					'error' => [
						'Invalid parameter "From": a date is expected.',
						'Invalid parameter "To": a date is expected.'
					]
				]
			],
			// Wrong From date and TO date format.
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'From' => '2022/01/01',
						'To' => '2022/02/01'
					],
					'expected' => TEST_BAD,
					'error' => [
						'Invalid parameter "From": a date is expected.',
						'Invalid parameter "To": a date is expected.'
					]
				]
			],
			// From date and To date too far in the past.
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'From' => '1968-01-01',
						'To' => '1969-10-10'
					],
					'expected' => TEST_BAD,
					'error' => [
						'Invalid parameter "From": a date is expected.',
						'Invalid parameter "To": a date is expected.'
					]
				]
			],
			// From date and To date too far in the future.
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'From' => '2040-01-01',
						'To' => '2050-10-10'
					],
					'expected' => TEST_BAD,
					'error' => [
						'Invalid parameter "From": a date is expected.',
						'Invalid parameter "To": a date is expected.'
					]
				]
			],
			// SLA report for disabled SLA without Service.
			[
				[
					'fields' => [
						'SLA' => 'Disabled SLA Annual'
					],
					'no_data' => true,
					'expected' => 'SLA is disabled.'
				]
			],
			// SLA report for disabled SLA with Service.
			[
				[
					'fields' => [
						'SLA' => 'Disabled SLA Annual',
						'Service' => 'Service with problem'
					],
					'no_data' => true,
					'expected' => 'SLA is disabled.'
				]
			]
		];
	}

	/**
	 * @dataProvider getSlaReportConfigurationFormData
	 * @dataProvider getSlaDataWithService
	 * @dataProvider getSlaDataWithoutService
	 */
	public function testDashboardSlaReportWidget_Create($data) {
		$this->executeAction($data);
	}

	/**
	 * @dataProvider getSlaReportConfigurationFormData
	 * @dataProvider getSlaDataWithService
	 * @dataProvider getSlaDataWithoutService
	 */
	public function testDashboardSlaReportWidget_Update($data) {
		$this->executeAction($data, 'update');
	}

	public function testDashboardSlaReportWidget_SimpleUpdate() {
		$initial_values = CDBHelper::getHash($this->sql);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$dashboard->edit();

		$form = $dashboard->getWidget(self::$update_widget)->edit();
		$form->submit();

		// Wait for the widget to be loaded and save dashboard (wait implemented inside the getWidget method).
		$dashboard->getWidget(self::$update_widget);
		$dashboard->save();

		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$this->assertEquals($initial_values, CDBHelper::getHash($this->sql));
	}

	public function getCancelActionsData() {
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
	 * @dataProvider getCancelActionsData
	 */
	public function testDashboardSlaReportWidget_Cancel($data) {
		$old_hash = CDBHelper::getHash($this->sql);
		$new_name = 'Widget to be cancelled';

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one()->edit();
		$old_widget_count = $dashboard->getWidgets()->count();

		// Start updating or creating a widget.
		if (CTestArrayHelper::get($data, 'update', false)) {
			$form = $dashboard->getWidget(self::$update_widget)->edit();
		}
		else {
			$form = $dashboard->addWidget()->asForm();

			if ($form->getField('Type')->getValue() !== 'SLA report') {
				$form->fill(['Type' => CFormElement::RELOADABLE_FILL('SLA report')]);
			}
		}
		$form->fill([
			'Name' => $new_name,
			'Refresh interval' => '15 minutes',
			'SLA' => 'SLA Weekly',
			'Service' => 'Simple actions service',
			'Show periods' => '2',
			'From' => '2022-01-01',
			'To' => '2022-01-10'
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
				foreach ([self::$update_widget => true, $new_name => false] as $name => $valid) {
					$this->assertTrue($dashboard->getWidget($name, $valid)->isValid($valid));
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
		// Confirm that no changes were made to the widget.
		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
	}

	public function testDashboardSlaReportWidget_Delete() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one()->edit();
		$widget = $dashboard->getWidget(self::$delete_widget);
		$dashboard->deleteWidget(self::$delete_widget);
		$widget->waitUntilNotPresent();

		$dashboard->save();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		// Confirm that widget is not present on dashboard.
		$this->assertFalse($dashboard->getWidget(self::$delete_widget, false)->isValid());
		$widget_sql = 'SELECT NULL FROM widget_field wf LEFT JOIN widget w ON w.widgetid=wf.widgetid'.
				' WHERE w.name='.zbx_dbstr(self::$delete_widget);
		$this->assertEquals(0, CDBHelper::getCount($widget_sql));
	}

	/**
	 * Perform SLA report widget creation or update and verify the result.
	 *
	 * @param array		$data		widget relate data from data provider.
	 * @param string	$action		string that specifies whether create or update action should be performed.
	 */
	private function executeAction($data, $action = 'create') {
		$data['fields']['Name'] = 'SLA report '.microtime();

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$dashboard->edit();

		// Open SLA report widget configuration form.
		if ($action === 'create') {
			$dashboard->selectPage(self::$create_page);

			$form = $dashboard->addWidget()->asForm();

			if ($form->getField('Type')->getValue() !== 'SLA report') {
				$form->fill(['Type' => CFormElement::RELOADABLE_FILL('SLA report')]);
			}
		}
		else {
			$form = $dashboard->getWidget(self::$update_widget)->edit();
			// Assign default values for the fields originally not mentioned in data provider.
			$data['fields'] = array_merge(self::$default_values, $data['fields']);
		}

		/**
		 * In SLA report widget the number of returned periods is not limited only by Show periods value, and not
		 * by the creation date or current date. So to use the $reporting_periods array, Show periods should be filled.
		 */
		if (!array_key_exists('error', $data)
				&& CTestArrayHelper::get($data['fields'], 'Service', '') === ''
				&& !array_key_exists('no_data', $data)
				&& in_array($data['reporting_period'], ['Monthly', 'Quarterly', 'Annually'])) {
			$data['fields']['Show periods'] = count(self::$reporting_periods[$data['reporting_period']]);
		}

		// Type mode chooses the 1st entry in the list, which for some cases in data provider is incorrect.
		CMultiselectElement::setDefaultFillMode(CMultiselectElement::MODE_SELECT);
		$form->fill($data['fields']);
		CMultiselectElement::setDefaultFillMode(CMultiselectElement::MODE_TYPE);
		$form->submit();

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, null, $data['error']);
			COverlayDialogElement::find()->one()->close();
			$dashboard->save();

			$this->page->waitUntilReady();
			$this->assertFalse($dashboard->getWidget($data['fields']['Name'], false)->isValid());
		}
		else {
			COverlayDialogElement::ensureNotPresent();
			// Wait for the widget to be loaded and save dashboard (wait implemented inside the getWidget method).
			$dashboard->getWidget($data['fields']['Name']);
			$dashboard->save();

			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			if ($action === 'create') {
				$dashboard->selectPage(self::$create_page);
			}
			else {
				self::$update_widget = $data['fields']['Name'];
			}

			if (CTestArrayHelper::get($data['fields'], 'Service', '') === '') {
				$this->checkLayoutWithoutService($data, true);
			}
			else {
				$this->checkLayoutWithService($data, true);
			}
		}
	}

	public function getSlaWidgetDataWithCustomDates() {
		return [
			// Daily with custom dates.
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'Service' => 'Service with problem',
						'From' => '2020-02-28',
						'To' => '2020-03-02'
					],
					'reporting_period' => 'Daily',
					'expected_periods' => [
						'2020-03-02',
						'2020-03-01',
						'2020-02-29',
						'2020-02-28'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'Service' => 'Service with problem',
						'From' => '2021-06-29'
					],
					'reporting_period' => 'Daily',
					'expected_periods' => [
						'2021-07-18',
						'2021-07-17',
						'2021-07-16',
						'2021-07-15',
						'2021-07-14',
						'2021-07-13',
						'2021-07-12',
						'2021-07-11',
						'2021-07-10',
						'2021-07-09',
						'2021-07-08',
						'2021-07-07',
						'2021-07-06',
						'2021-07-05',
						'2021-07-04',
						'2021-07-03',
						'2021-07-02',
						'2021-07-01',
						'2021-06-30',
						'2021-06-29'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'Service' => 'Service with problem',
						'From' => '2021-06-29',
						'Show periods' => 7
					],
					'reporting_period' => 'Daily',
					'expected_periods' => [
						'2021-07-05',
						'2021-07-04',
						'2021-07-03',
						'2021-07-02',
						'2021-07-01',
						'2021-06-30',
						'2021-06-29'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'Service' => 'Service with problem',
						'To' => '2021-06-29'
					],
					'reporting_period' => 'Daily',
					'expected_periods' => [
						'2021-06-29',
						'2021-06-28',
						'2021-06-27',
						'2021-06-26',
						'2021-06-25',
						'2021-06-24',
						'2021-06-23',
						'2021-06-22',
						'2021-06-21',
						'2021-06-20',
						'2021-06-19',
						'2021-06-18',
						'2021-06-17',
						'2021-06-16',
						'2021-06-15',
						'2021-06-14',
						'2021-06-13',
						'2021-06-12',
						'2021-06-11',
						'2021-06-10'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'Service' => 'Service with problem',
						'To' => '2021-06-29',
						'Show periods' => 7
					],
					'reporting_period' => 'Daily',
					'expected_periods' => [
						'2021-06-29',
						'2021-06-28',
						'2021-06-27',
						'2021-06-26',
						'2021-06-25',
						'2021-06-24',
						'2021-06-23'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'Service' => 'Service with problem',
						'From' => 'yesterday'
					],
					'reporting_period' => 'Daily'
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'Service' => 'Service with problem',
						'To' => 'yesterday - 1 day',
						'Show periods' => 3
					],
					'reporting_period' => 'Daily'
				]
			],
			// Oldest periods should be cut off if Show periods doesn't cover the whole From -> To period.
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'From' => '2022-06-01',
						'To' => '2022-06-25'
					],
					'reporting_period' => 'Daily',
					'expected_periods' => [
						'2022-06-06',
						'2022-06-07',
						'2022-06-08',
						'2022-06-09',
						'2022-06-10',
						'2022-06-11',
						'2022-06-12',
						'2022-06-13',
						'2022-06-14',
						'2022-06-15',
						'2022-06-16',
						'2022-06-17',
						'2022-06-18',
						'2022-06-19',
						'2022-06-20',
						'2022-06-21',
						'2022-06-22',
						'2022-06-23',
						'2022-06-24',
						'2022-06-25'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'From' => '2022-06-01'
					],
					'reporting_period' => 'Daily',
					'expected_periods' => [
						'2022-06-01',
						'2022-06-02',
						'2022-06-03',
						'2022-06-04',
						'2022-06-05',
						'2022-06-06',
						'2022-06-07',
						'2022-06-08',
						'2022-06-09',
						'2022-06-10',
						'2022-06-11',
						'2022-06-12',
						'2022-06-13',
						'2022-06-14',
						'2022-06-15',
						'2022-06-16',
						'2022-06-17',
						'2022-06-18',
						'2022-06-19',
						'2022-06-20'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'To' => '2021-05-06'
					],
					'reporting_period' => 'Daily',
					'expected_periods' => [
						'2021-04-17',
						'2021-04-18',
						'2021-04-19',
						'2021-04-20',
						'2021-04-21',
						'2021-04-22',
						'2021-04-23',
						'2021-04-24',
						'2021-04-25',
						'2021-04-26',
						'2021-04-27',
						'2021-04-28',
						'2021-04-29',
						'2021-04-30',
						'2021-05-01',
						'2021-05-02',
						'2021-05-03',
						'2021-05-04',
						'2021-05-05',
						'2021-05-06'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'From' => 'yesterday'
					],
					'reporting_period' => 'Daily'
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'To' => 'yesterday',
						'Show periods' => 5
					],
					'reporting_period' => 'Daily'
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Weekly',
						'Service' => 'Simple actions service',
						'From' => '2021-09-25',
						'To' => '2021-10-04'
					],
					'reporting_period' => 'Weekly',
					'expected_periods' => [
						'2021-10-03 – 10-09',
						'2021-09-26 – 10-02',
						'2021-09-19 – 09-25'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Weekly',
						'Service' => 'Simple actions service',
						'From' => '2021-09-25'
					],
					'reporting_period' => 'Weekly',
					'expected_periods' => [
						'2022-01-30 – 02-05',
						'2022-01-23 – 01-29',
						'2022-01-16 – 01-22',
						'2022-01-09 – 01-15',
						'2022-01-02 – 01-08',
						'2021-12-26 – 01-01',
						'2021-12-19 – 12-25',
						'2021-12-12 – 12-18',
						'2021-12-05 – 12-11',
						'2021-11-28 – 12-04',
						'2021-11-21 – 11-27',
						'2021-11-14 – 11-20',
						'2021-11-07 – 11-13',
						'2021-10-31 – 11-06',
						'2021-10-24 – 10-30',
						'2021-10-17 – 10-23',
						'2021-10-10 – 10-16',
						'2021-10-03 – 10-09',
						'2021-09-26 – 10-02',
						'2021-09-19 – 09-25'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Weekly',
						'Service' => 'Simple actions service',
						'From' => '2021-09-25',
						'Show periods' => 4
					],
					'reporting_period' => 'Weekly',
					'expected_periods' => [
						'2021-10-10 – 10-16',
						'2021-10-03 – 10-09',
						'2021-09-26 – 10-02',
						'2021-09-19 – 09-25'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Weekly',
						'Service' => 'Simple actions service',
						'To' => '2022-02-02'
					],
					'reporting_period' => 'Weekly',
					'expected_periods' => [
						'2022-01-30 – 02-05',
						'2022-01-23 – 01-29',
						'2022-01-16 – 01-22',
						'2022-01-09 – 01-15',
						'2022-01-02 – 01-08',
						'2021-12-26 – 01-01',
						'2021-12-19 – 12-25',
						'2021-12-12 – 12-18',
						'2021-12-05 – 12-11',
						'2021-11-28 – 12-04',
						'2021-11-21 – 11-27',
						'2021-11-14 – 11-20',
						'2021-11-07 – 11-13',
						'2021-10-31 – 11-06',
						'2021-10-24 – 10-30',
						'2021-10-17 – 10-23',
						'2021-10-10 – 10-16',
						'2021-10-03 – 10-09',
						'2021-09-26 – 10-02',
						'2021-09-19 – 09-25'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Weekly',
						'Service' => 'Simple actions service',
						'To' => '2022-02-02',
						'Show periods' => 6
					],
					'reporting_period' => 'Weekly',
					'expected_periods' => [
						'2022-01-30 – 02-05',
						'2022-01-23 – 01-29',
						'2022-01-16 – 01-22',
						'2022-01-09 – 01-15',
						'2022-01-02 – 01-08',
						'2021-12-26 – 01-01'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Weekly',
						'Service' => 'Simple actions service',
						'From' => 'today - 2 weeks'
					],
					'reporting_period' => 'Weekly'
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Weekly',
						'Service' => 'Simple actions service',
						'To' => 'today - 2 weeks',
						'Show periods' => 8
					],
					'reporting_period' => 'Weekly'
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Weekly',
						'From' => '2021-12-29',
						'To' => '2022-01-09'
					],
					'reporting_period' => 'Weekly',
					'expected_periods' => [
						'2021-12-26 – 01-01',
						'2022-01-02 – 01-08',
						'2022-01-09 – 01-15'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Weekly',
						'From' => '2021-12-29'
					],
					'reporting_period' => 'Weekly',
					'expected_periods' => [
						'2021-12-26 – 01-01',
						'2022-01-02 – 01-08',
						'2022-01-09 – 01-15',
						'2022-01-16 – 01-22',
						'2022-01-23 – 01-29',
						'2022-01-30 – 02-05',
						'2022-02-06 – 02-12',
						'2022-02-13 – 02-19',
						'2022-02-20 – 02-26',
						'2022-02-27 – 03-05',
						'2022-03-06 – 03-12',
						'2022-03-13 – 03-19',
						'2022-03-20 – 03-26',
						'2022-03-27 – 04-02',
						'2022-04-03 – 04-09',
						'2022-04-10 – 04-16',
						'2022-04-17 – 04-23',
						'2022-04-24 – 04-30',
						'2022-05-01 – 05-07',
						'2022-05-08 – 05-14'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Weekly',
						'From' => '2021-12-29',
						'Show periods' => 1
					],
					'reporting_period' => 'Weekly',
					'expected_periods' => [
						'2021-12-26 – 01-01'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Weekly',
						'To' => '2021-06-01'
					],
					'reporting_period' => 'Weekly',
					'expected_periods' => [
						'2021-01-17 – 01-23',
						'2021-01-24 – 01-30',
						'2021-01-31 – 02-06',
						'2021-02-07 – 02-13',
						'2021-02-14 – 02-20',
						'2021-02-21 – 02-27',
						'2021-02-28 – 03-06',
						'2021-03-07 – 03-13',
						'2021-03-14 – 03-20',
						'2021-03-21 – 03-27',
						'2021-03-28 – 04-03',
						'2021-04-04 – 04-10',
						'2021-04-11 – 04-17',
						'2021-04-18 – 04-24',
						'2021-04-25 – 05-01',
						'2021-05-02 – 05-08',
						'2021-05-09 – 05-15',
						'2021-05-16 – 05-22',
						'2021-05-23 – 05-29',
						'2021-05-30 – 06-05'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Weekly',
						'To' => '2021-06-01',
						'Show periods' => 10
					],
					'reporting_period' => 'Weekly',
					'expected_periods' => [
						'2021-03-28 – 04-03',
						'2021-04-04 – 04-10',
						'2021-04-11 – 04-17',
						'2021-04-18 – 04-24',
						'2021-04-25 – 05-01',
						'2021-05-02 – 05-08',
						'2021-05-09 – 05-15',
						'2021-05-16 – 05-22',
						'2021-05-23 – 05-29',
						'2021-05-30 – 06-05'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Weekly',
						'From' => 'today - 3 weeks',
						'Show periods' => 11
					],
					'reporting_period' => 'Weekly'
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Weekly',
						'To' => 'today - 3 weeks'
					],
					'reporting_period' => 'Weekly'
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'Service' => 'Simple actions service',
						'From' => '2020-01-01',
						'To' => '2020-02-29'
					],
					'reporting_period' => 'Monthly',
					'expected_periods' => [
						'2020-02',
						'2020-01'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'Service' => 'Simple actions service',
						'From' => '2020-01-01'
					],
					'reporting_period' => 'Monthly',
					'expected_periods' => [
						'2021-08',
						'2021-07',
						'2021-06',
						'2021-05',
						'2021-04',
						'2021-03',
						'2021-02',
						'2021-01',
						'2020-12',
						'2020-11',
						'2020-10',
						'2020-09',
						'2020-08',
						'2020-07',
						'2020-06',
						'2020-05',
						'2020-04',
						'2020-03',
						'2020-02',
						'2020-01'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'Service' => 'Simple actions service',
						'From' => '2020-01-01',
						'Show periods' => 3
					],
					'reporting_period' => 'Monthly',
					'expected_periods' => [
						'2020-03',
						'2020-02',
						'2020-01'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'Service' => 'Simple actions service',
						'To' => '2023-02-15'
					],
					'reporting_period' => 'Monthly',
					'expected_periods' => [
						'2023-02',
						'2023-01',
						'2022-12',
						'2022-11',
						'2022-10',
						'2022-09',
						'2022-08',
						'2022-07',
						'2022-06',
						'2022-05',
						'2022-04',
						'2022-03',
						'2022-02',
						'2022-01',
						'2021-12',
						'2021-11',
						'2021-10',
						'2021-09',
						'2021-08',
						'2021-07'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'Service' => 'Simple actions service',
						'To' => '2023-02-15',
						'Show periods' => 4
					],
					'reporting_period' => 'Monthly',
					'expected_periods' => [
						'2023-02',
						'2023-01',
						'2022-12',
						'2022-11'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'Service' => 'Simple actions service',
						'From' => 'today - 2 months'
					],
					'reporting_period' => 'Monthly'
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'Service' => 'Simple actions service',
						'To' => 'today - 2 months',
						'Show periods' => 6
					],
					'reporting_period' => 'Monthly'
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'From' => '2020-01-01',
						'To' => '2020-02-29'
					],
					'reporting_period' => 'Monthly',
					'expected_periods' => [
						'2020-01',
						'2020-02'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'From' => '2020-01-01'
					],
					'reporting_period' => 'Monthly',
					'expected_periods' => [
						'2020-01',
						'2020-02',
						'2020-03',
						'2020-04',
						'2020-05',
						'2020-06',
						'2020-07',
						'2020-08',
						'2020-09',
						'2020-10',
						'2020-11',
						'2020-12',
						'2021-01',
						'2021-02',
						'2021-03',
						'2021-04',
						'2021-05',
						'2021-06',
						'2021-07',
						'2021-08'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'From' => '2020-01-01',
						'Show periods' => 2
					],
					'reporting_period' => 'Monthly',
					'expected_periods' => [
						'2020-01',
						'2020-02'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'To' => '2023-02-15'
					],
					'reporting_period' => 'Monthly',
					'expected_periods' => [
						'2021-07',
						'2021-08',
						'2021-09',
						'2021-10',
						'2021-11',
						'2021-12',
						'2022-01',
						'2022-02',
						'2022-03',
						'2022-04',
						'2022-05',
						'2022-06',
						'2022-07',
						'2022-08',
						'2022-09',
						'2022-10',
						'2022-11',
						'2022-12',
						'2023-01',
						'2023-02'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'To' => '2023-02-15',
						'Show periods' => 3
					],
					'reporting_period' => 'Monthly',
					'expected_periods' => [
						'2022-12',
						'2023-01',
						'2023-02'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'From' => 'today - 2 months',
						'Show periods' => 5
					],
					'reporting_period' => 'Monthly'
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'To' => 'today - 2 months'
					],
					'reporting_period' => 'Monthly'
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Quarterly',
						'Service' => 'Simple actions service',
						'From' => '2021-05-01',
						'To' => '2021-10-01'
					],
					'reporting_period' => 'Quarterly',
					'expected_periods' => [
						'2021-10 – 12',
						'2021-07 – 09',
						'2021-04 – 06'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Quarterly',
						'Service' => 'Simple actions service',
						'From' => '2017-12-03'
					],
					'reporting_period' => 'Quarterly',
					'expected_periods' => [
						'2022-07 – 09',
						'2022-04 – 06',
						'2022-01 – 03',
						'2021-10 – 12',
						'2021-07 – 09',
						'2021-04 – 06',
						'2021-01 – 03',
						'2020-10 – 12',
						'2020-07 – 09',
						'2020-04 – 06',
						'2020-01 – 03',
						'2019-10 – 12',
						'2019-07 – 09',
						'2019-04 – 06',
						'2019-01 – 03',
						'2018-10 – 12',
						'2018-07 – 09',
						'2018-04 – 06',
						'2018-01 – 03',
						'2017-10 – 12'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Quarterly',
						'Service' => 'Simple actions service',
						'From' => '2021-02-10',
						'Show periods' => 7
					],
					'reporting_period' => 'Quarterly',
					'expected_periods' => [
						'2022-07 – 09',
						'2022-04 – 06',
						'2022-01 – 03',
						'2021-10 – 12',
						'2021-07 – 09',
						'2021-04 – 06',
						'2021-01 – 03'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Quarterly',
						'Service' => 'Simple actions service',
						'To' => '2026-05-01'
					],
					'reporting_period' => 'Quarterly',
					'expected_periods' => [
						'2026-04 – 06',
						'2026-01 – 03',
						'2025-10 – 12',
						'2025-07 – 09',
						'2025-04 – 06',
						'2025-01 – 03',
						'2024-10 – 12',
						'2024-07 – 09',
						'2024-04 – 06',
						'2024-01 – 03',
						'2023-10 – 12',
						'2023-07 – 09',
						'2023-04 – 06',
						'2023-01 – 03',
						'2022-10 – 12',
						'2022-07 – 09',
						'2022-04 – 06',
						'2022-01 – 03',
						'2021-10 – 12',
						'2021-07 – 09'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Quarterly',
						'Service' => 'Simple actions service',
						'To' => '2026-05-01',
						'Show periods' => 4
					],
					'reporting_period' => 'Quarterly',
					'expected_periods' => [
						'2026-04 – 06',
						'2026-01 – 03',
						'2025-10 – 12',
						'2025-07 – 09'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Quarterly',
						'Service' => 'Simple actions service',
						'From' => 'first day of this month - 6 months'
					],
					'reporting_period' => 'Quarterly'
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Quarterly',
						'Service' => 'Simple actions service',
						'To' => 'today',
						'Show periods' => 6
					],
					'reporting_period' => 'Quarterly'
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Quarterly',
						'From' => '2021-05-01',
						'To' => '2021-10-01'
					],
					'reporting_period' => 'Quarterly',
					'expected_periods' => [
						'2021-04 – 06',
						'2021-07 – 09',
						'2021-10 – 12'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Quarterly',
						'From' => '2017-12-03'
					],
					'reporting_period' => 'Quarterly',
					'expected_periods' => [
						'2017-10 – 12',
						'2018-01 – 03',
						'2018-04 – 06',
						'2018-07 – 09',
						'2018-10 – 12',
						'2019-01 – 03',
						'2019-04 – 06',
						'2019-07 – 09',
						'2019-10 – 12',
						'2020-01 – 03',
						'2020-04 – 06',
						'2020-07 – 09',
						'2020-10 – 12',
						'2021-01 – 03',
						'2021-04 – 06',
						'2021-07 – 09',
						'2021-10 – 12',
						'2022-01 – 03',
						'2022-04 – 06',
						'2022-07 – 09'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Quarterly',
						'From' => '2021-02-02',
						'Show periods' => 3
					],
					'reporting_period' => 'Quarterly',
					'expected_periods' => [
						'2021-01 – 03',
						'2021-04 – 06',
						'2021-07 – 09'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Quarterly',
						'To' => '2026-05-01'
					],
					'reporting_period' => 'Quarterly',
					'expected_periods' => [
						'2021-07 – 09',
						'2021-10 – 12',
						'2022-01 – 03',
						'2022-04 – 06',
						'2022-07 – 09',
						'2022-10 – 12',
						'2023-01 – 03',
						'2023-04 – 06',
						'2023-07 – 09',
						'2023-10 – 12',
						'2024-01 – 03',
						'2024-04 – 06',
						'2024-07 – 09',
						'2024-10 – 12',
						'2025-01 – 03',
						'2025-04 – 06',
						'2025-07 – 09',
						'2025-10 – 12',
						'2026-01 – 03',
						'2026-04 – 06'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Quarterly',
						'To' => '2022-10-01',
						'Show periods' => 6
					],
					'reporting_period' => 'Quarterly',
					'expected_periods' => [
						'2021-07 – 09',
						'2021-10 – 12',
						'2022-01 – 03',
						'2022-04 – 06',
						'2022-07 – 09',
						'2022-10 – 12'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Quarterly',
						'From' => 'first day of this month - 3 months',
						'Show periods' => 4
					],
					'reporting_period' => 'Quarterly'
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Quarterly',
						'To' => 'first day of this month - 1 month'
					],
					'reporting_period' => 'Quarterly'
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Annual',
						'Service' => 'Service with problem',
						'From' => '2020-05-01',
						'To' => '2025-12-31'
					],
					'reporting_period' => 'Annually',
					'expected_periods' => [
						'2025',
						'2024',
						'2023',
						'2022',
						'2021',
						'2020'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Annual',
						'Service' => 'Service with problem',
						'From' => '2002-12-03'
					],
					'reporting_period' => 'Annually',
					'expected_periods' => [
						'2021',
						'2020',
						'2019',
						'2018',
						'2017',
						'2016',
						'2015',
						'2014',
						'2013',
						'2012',
						'2011',
						'2010',
						'2009',
						'2008',
						'2007',
						'2006',
						'2005',
						'2004',
						'2003',
						'2002'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Annual',
						'Service' => 'Service with problem',
						'From' => '2012-12-03',
						'Show periods' => 7
					],
					'reporting_period' => 'Annually',
					'expected_periods' => [
						'2018',
						'2017',
						'2016',
						'2015',
						'2014',
						'2013',
						'2012'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Annual',
						'Service' => 'Service with problem',
						'To' => '2037-01-01'
					],
					'reporting_period' => 'Annually',
					'expected_periods' => [
						'2037',
						'2036',
						'2035',
						'2034',
						'2033',
						'2032',
						'2031',
						'2030',
						'2029',
						'2028',
						'2027',
						'2026',
						'2025',
						'2024',
						'2023',
						'2022',
						'2021',
						'2020',
						'2019',
						'2018'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Annual',
						'Service' => 'Service with problem',
						'To' => '2037-01-01',
						'Show periods' => 10
					],
					'reporting_period' => 'Annually',
					'expected_periods' => [
						'2037',
						'2036',
						'2035',
						'2034',
						'2033',
						'2032',
						'2031',
						'2030',
						'2029',
						'2028'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Annual',
						'Service' => 'Service with problem',
						'From' => 'today - 10 years'
					],
					'reporting_period' => 'Annually'
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Annual',
						'Service' => 'Service with problem',
						'From' => 'today + 3 months',
						'Show periods' => 4
					],
					'reporting_period' => 'Annually'
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Annual',
						'From' => '2019-05-01',
						'To' => '2024-10-01'
					],
					'reporting_period' => 'Annually',
					'expected_periods' => [
						'2019',
						'2020',
						'2021',
						'2022',
						'2023',
						'2024'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Annual',
						'From' => '2002-12-03'
					],
					'reporting_period' => 'Annually',
					'expected_periods' => [
						'2002',
						'2003',
						'2004',
						'2005',
						'2006',
						'2007',
						'2008',
						'2009',
						'2010',
						'2011',
						'2012',
						'2013',
						'2014',
						'2015',
						'2016',
						'2017',
						'2018',
						'2019',
						'2020',
						'2021'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Annual',
						'From' => '2022-12-03',
						'Show periods' => 3
					],
					'reporting_period' => 'Annually',
					'expected_periods' => [
						'2022',
						'2023',
						'2024'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Annual',
						'To' => '2037-02-01'
					],
					'reporting_period' => 'Annually',
					'expected_periods' => [
						'2018',
						'2019',
						'2020',
						'2021',
						'2022',
						'2023',
						'2024',
						'2025',
						'2026',
						'2027',
						'2028',
						'2029',
						'2030',
						'2031',
						'2032',
						'2033',
						'2034',
						'2035',
						'2036',
						'2037'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Annual',
						'To' => '2037-02-01',
						'Show periods' => 5
					],
					'reporting_period' => 'Annually',
					'expected_periods' => [
						'2033',
						'2034',
						'2035',
						'2036',
						'2037'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Annual',
						'From' => 'today - 6 months',
						'Show periods' => 5
					],
					'reporting_period' => 'Annually'
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Annual',
						'To' => 'tomorrow'
					],
					'reporting_period' => 'Annually'
				]
			],
			// Using non-complete date in From and To fields.
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'From' => '2021',
						'To' => '2021'
					],
					'reporting_period' => 'Monthly',
					'expected_periods' => [
						'2021-01',
						'2021-02',
						'2021-03',
						'2021-04',
						'2021-05',
						'2021-06',
						'2021-07',
						'2021-08',
						'2021-09',
						'2021-10',
						'2021-11',
						'2021-12'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'Service' => 'Service with problem',
						'From' => 'now',
						'Show periods' => 3
					],
					'reporting_period' => 'Daily',
					'equivalent_timestamps' => [
						'From' => 'today'
					]
				]
			],
			// Months are excluded as strtotime() calculates month subtraction incorrectly on the last days on the month.
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'From' => 'now-1y-1w-1d',
						'Show periods' => 3
					],
					'reporting_period' => 'Daily',
					'equivalent_timestamps' => [
						'From' => 'today - 1 year - 1 week - 1 day'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'From' => 'now/d',
						'Show periods' => 1
					],
					'reporting_period' => 'Daily',
					'equivalent_timestamps' => [
						'From' => 'today'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'Service' => 'Service with problem',
						'From' => 'now/w',
						'Show periods' => 3
					],
					'reporting_period' => 'Daily',
					'equivalent_timestamps' => [
						'From' => 'this week'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'From' => 'now/M'
					],
					'reporting_period' => 'Daily',
					'equivalent_timestamps' => [
						'From' => date('Y-m-d', strtotime('first day of this month'))
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'Service' => 'Service with problem',
						'From' => 'now/y',
						'Show periods' => 3
					],
					'reporting_period' => 'Daily',
					'equivalent_timestamps' => [
						'From' => '1 January this Year'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'To' => 'now',
						'Show periods' => 3
					],
					'reporting_period' => 'Daily',
					'equivalent_timestamps' => [
						'To' => 'today'
					]
				]
			],
			// Months are excluded as strtotime() calculates month subtraction incorrectly on the last days on the month.
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'To' => 'now-1y-1w-1d',
						'Show periods' => 3
					],
					'reporting_period' => 'Daily',
					'equivalent_timestamps' => [
						'To' => 'now - 1 year - 1 week - 1 day'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'To' => 'now/d',
						'Show periods' => 1
					],
					'reporting_period' => 'Daily',
					'equivalent_timestamps' => [
						'To' => 'today'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'To' => 'now/w',
						'Show periods' => 3
					],
					'reporting_period' => 'Daily',
					'equivalent_timestamps' => [
						'To' => 'next week -1 day'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'To' => 'now/M'
					],
					'reporting_period' => 'Daily',
					'equivalent_timestamps' => [
						'To' => date('Y-m-d', strtotime('last day of this month'))
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Daily',
						'To' => 'now/y',
						'Show periods' => 3
					],
					'reporting_period' => 'Daily',
					'equivalent_timestamps' => [
						'To' => '31 December this Year'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Weekly',
						'From' => 'now/w-3w',
						'Show periods' => 3
					],
					'reporting_period' => 'Weekly',
					'equivalent_timestamps' => [
						'From' => 'this week - 3 weeks'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Weekly',
						'Service' => 'Simple actions service',
						'To' => 'now/w+3w',
						'Show periods' => 3
					],
					'reporting_period' => 'Weekly',
					'equivalent_timestamps' => [
						'To' => 'next week -1 day + 3 weeks'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Monthly',
						'Service' => 'Simple actions service',
						'From' => 'now/M-1M',
						'Show periods' => 3
					],
					'reporting_period' => 'Monthly',
					'equivalent_timestamps' => [
						'From' => date('Y-m', strtotime('first day of this month')).' - 1 month'
					]
				]
			],
			// TODO: uncomment the below case when ZBX-21821 is fixed.
//			[
//				[
//					'fields' => [
//						'SLA' => 'SLA Monthly',
//						'To' => 'now/M+1M',
//						'Show periods' => 3
//					],
//					'reporting_period' => 'Monthly',
//					'equivalent_timestamps' => [
//						'To' => date('Y-m', strtotime('last day of this month')).' + 1 month'
//					]
//				]
//			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Annual',
						'From' => 'now/y-1y',
						'Show periods' => 3
					],
					'reporting_period' => 'Annually',
					'equivalent_timestamps' => [
						'From' => '1 January this year - 1 year'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Annual',
						'Service' => 'Service with problem',
						'To' => 'now/y+1y',
						'Show periods' => 3
					],
					'reporting_period' => 'Annually',
					'equivalent_timestamps' => [
						'To' => '31 December this year + 1 year'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Quarterly',
						'From' => 'now/y+3M',
						'Show periods' => 3
					],
					'reporting_period' => 'Quarterly',
					'equivalent_timestamps' => [
						'From' => '1 January this year + 3 month'
					]
				]
			],
			[
				[
					'fields' => [
						'SLA' => 'SLA Quarterly',
						'Service' => 'Simple actions service',
						'To' => 'now/d-100d',
						'Show periods' => 3
					],
					'reporting_period' => 'Quarterly',
					'equivalent_timestamps' => [
						'To' => 'today - 100 days'
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getSlaWidgetDataWithCustomDates
	 */
	public function testDashboardSlaReportWidget_UpdateWithCustomPeriods($data) {
		// Construct the expected result array if such is not present in the data provider.
		if (!array_key_exists('expected_periods', $data)) {
			// If dynamic format is used in From and To fields, equivalent values are used for building the reference array.
			$data_for_period = $data;
			if (array_key_exists('equivalent_timestamps', $data)) {
				foreach ($data_for_period['equivalent_timestamps'] as $field => $value) {
					$data_for_period['fields'][$field] = $value;
				}
			}

			foreach ($this->getWidgetDateTimeData($data_for_period) as $period) {
				$expected_periods[] = $period;
			}
		}
		else {
			$expected_periods = $data['expected_periods'];
		}

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$dashboard->edit();

		// Edit widget.
		$form = $dashboard->getWidget(self::$update_widget)->edit();

		// Assign default values for the fields originally not mentioned in data provider.
		$data['fields'] = array_merge(self::$default_values, $data['fields']);

		// Convert From and To field values to date if it is populated as string and not as a dynamic date.
		if (!array_key_exists('expected_periods', $data) && !array_key_exists('equivalent_timestamps', $data)) {
			foreach (['From', 'To'] as $field) {
				if (CTestArrayHelper::get($data['fields'], $field)) {
					// Convert date to YYY-MM-DD format with required corrections for date strings with specified months.
					$data['fields'][$field] = $this->normalizeDate($data['fields'][$field]);
				}
			}
		}

		// Type mode chooses the 1st entry in the list, which for some cases in data provider is incorrect.
		CMultiselectElement::setDefaultFillMode(CMultiselectElement::MODE_SELECT);
		$form->fill($data['fields']);
		CMultiselectElement::setDefaultFillMode(CMultiselectElement::MODE_TYPE);
		$form->submit();

		// Wait for the widget to be loaded and save dashboard (wait implemented inside the getWidget method).
		$dashboard->getWidget(self::$update_widget);
		$dashboard->save();

		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		$table = CDashboardElement::find()->one()->getWidget(self::$update_widget)->query('class:list-table')->asTable()->one();

		if (CTestArrayHelper::get($data['fields'], 'Service') !== '') {
			$this->assertTableDataColumn($expected_periods, self::$period_headers[$data['reporting_period']]);
		}
		else {
			$headers = $table->getHeadersText();

			unset($headers[0], $headers[1]);
			$this->assertEquals($expected_periods, array_values($headers));
		}
	}

	/**
	 * When subtracting or adding months from/to current date in some rare cases strtotime() considers
	 * that month contains an incorrect count of days. If the string in From/To field contains a number
	 * of months to be added or subtracted, then timestamp is calculated without the operation with
	 * months to get the resulting day and then month is calculated by its number in AD.
	 *
	 * @param string	$date_string	the string that needs to be converted to YYY-MM-DD format
	 *
	 * @return string
	 */
	protected function normalizeDate($date_string) {
		// Check if the string contains operations with months via regex.
		if ((bool)preg_match('( \D (\d+ months|1 month))', $date_string, $month_string)) {
			// Replace months related operation with '' and get the timestamp to determine the day.
			$temp_timestamp = strtotime(preg_replace('( \D (\d+ months|1 month))', '', $date_string));
			$day = date('d', $temp_timestamp);

			// Get the number of month to be added (negative numbers included) to the reference timestamp.
			$months_from_date = preg_replace('/ (months|month)/', '', $month_string[0]);

			// Calculate the year and the month of the resulting date via month number in AD and combine with day.
			$months_total = 12 * (int)date('Y', $temp_timestamp) + (int)date('m', $temp_timestamp) +
					(float)str_replace(' ', '', $months_from_date);
			$year = floor($months_total/12);
			$month = $months_total - $year * 12;

			// In case there is no remaining months after division, then month is december and year should be reduced.
			if ($month == 0) {
				$month = 12;
				$year--;
			}

			/**
			 * Combine the year, month and day into a date.
			 * If result date is invalid (like 2023-02-31) replace the day with last day of month.
			 * date('Y-m-t', timestamp) returns the last day of the month of the defined timestamp.
			 */
			$date_part = $year.'-'.$month;
			$date = (date('Y-m-d', strtotime($date_part.'-'.$day)) === $date_part.'-'.$day)
				? $date_part.'-'.$day
				: date('Y-m-t', strtotime($date_part));

			return $date;
		}
		else {
			return date('Y-m-d', strtotime($date_string));
		}
	}

	/**
	 * Build reference array with reporting periods based on report parameters and reporting period type.
	 * At first the last date that should be included in the report is obtained, and then $show_periods number of
	 * periods (from this date) is written into the reference array.
	 *
	 * @param	array	$data	data provider
	 * @return	array
	 */
	private function getWidgetDateTimeData($data) {
		// By default the last 20 periods are displayed.
		$show_periods = (array_key_exists('Show periods', $data['fields'])) ? $data['fields']['Show periods'] : 20;

		if (array_key_exists('To', $data['fields'])) {
			$to_date = $data['fields']['To'];
		}
		elseif (array_key_exists('From', $data['fields'])) {
			$units = [
				'Daily' => 'days',
				'Weekly' => 'weeks',
				'Monthly' => 'months',
				'Quarterly' => 'months',
				'Annually' => 'years'
			];
			$multiplier = ($data['reporting_period'] === 'Quarterly') ? 3 : 1;

			$to_date = date('Y-m-d', strtotime($data['fields']['From'].' + '.($multiplier * ($show_periods - 1)).
					' '.$units[$data['reporting_period']])
			);
		}
		else {
			$to_date = 'today';
		}

		// Convert date to YYY-MM-DD format with required corrections for date strings with specified months.
		$to_date = $this->normalizeDate($to_date);

		switch ($data['reporting_period']) {
			case 'Daily':
				for ($i = 0; $i < $show_periods; $i++) {
					$period_values[] = date('Y-m-d', strtotime($to_date.' '.-$i.' days'));
				}
				break;

			case 'Weekly':
				// Since in SLA report week starts on Sunday but in php - on Monday, use +1 week if to_date is Sunday.
				$date_string = (date('l', strtotime($to_date)) === 'Sunday')
					? 'this week this sunday'
					: 'this week this sunday - 1 week';

				for ($i = 0; $i < $show_periods; $i++) {
					$start = strtotime($date_string, strtotime($to_date.' '.-$i.' weeks'));
					$end = strtotime(date('Y-m-d', $start).' + 6 days');

					$period_values[] = date('Y-m-d', $start).' – '.date('m-d', $end);
				}
				break;

			case 'Monthly':
				for ($i = 0; $i < $show_periods; $i++) {
					$period_values[] = date('Y-m', strtotime(date('Y-m-01', strtotime($to_date)).' '.-$i.' month'));
				}
				break;

			case 'Quarterly':
				$quarters = ['01 – 03', '04 – 06', '07 – 09', '10 – 12'];
				$to_year = date('Y', strtotime($to_date));
				$to_month = date('m', strtotime($to_date));

				// Calculate the year and the month from which the SLA should be displayed via month number in AD.
				$months_total = 12 * $to_year + $to_month - 3 * ($show_periods - 1);
				$from_year = floor($months_total/12);
				$from_month = $months_total - $from_year * 12;

				// In case there is no remaining months after division, then month is december and year should be reduced.
				if ($from_month == 0) {
					$from_month = 12;
					$from_year--;
				}

				$i = 0;
				for ($year = $to_year; $year >= $from_year; $year--) {
					foreach (array_reverse($quarters) as $quarter) {
						// Get the last and the first month of the quarter under attention.
						$period_end = ltrim(stristr($quarter, '– '), '– ');
						$period_start = substr($quarter, 0, strpos($quarter, " –"));

						// Skip the quarters before the chronologically first quarter to be displayed in first year.
						if ($year == $from_year && $period_end < $from_month) {
							continue;
						}

						// Write periods into reference array if period start is not later than the reports' last month.
						if ($year < $to_year || ($year == $to_year && $period_start <= $to_month)) {
							$period_values[] = $year.'-'.$quarter;

							$i++;
						}
					}
				}
				break;

			case 'Annually':
				for ($i = 0; $i < $show_periods; $i++) {
					$period_values[] = date('Y', strtotime($to_date.' '.-$i.' years'));
				}
				break;
		}

		return (array_key_exists('Service', $data['fields'])) ? $period_values : array_reverse($period_values);
	}
}
