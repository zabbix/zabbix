<?php
/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

/**
 * @backup hosts, widget, profiles
 *
 * @onBefore prepareData
 */
class testDashboardPieChartWidget extends CWebTest
{
	protected static $dashboardid;

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [CMessageBehavior::class];
	}

	/**
	 * Create the needed initial data in database and set static variables.
	 */
	public function prepareData() {
		// Set Pie chart as the default widget type.
		DBexecute('DELETE FROM profiles WHERE idx=\'web.dashboard.last_widget_type\' AND userid=\'1\'');
		DBexecute('INSERT INTO profiles (profileid, userid, idx, value_str, type)'.
			' VALUES (99999,1,\'web.dashboard.last_widget_type\',\'piechart\',3)');

		// Create a dashboard for creating widgets.
		$dashboards = CDataHelper::call('dashboard.create', [
			'name' => 'Pie chart dashboard',
			'auto_start' => 0,
			'pages' => [['name' => 'Pie chart test page']]
		]);
		self::$dashboardid = $dashboards['dashboardids'][0];
	}

	public function getCreateData() {
		return [
			// Missing Host pattern.
			[
				[
					'fields' => [
						'Data set' => ['item' => '*']
					],
					'result' => TEST_BAD,
					'error' => 'Invalid parameter "Data set/1/hosts": cannot be empty.'
				]
			],
			// Missing Item pattern.
			[
				[
					'fields' => [
						'Data set' => ['host' => '*']
					],
					'result' => TEST_BAD,
					'error' => 'Invalid parameter "Data set/1/items": cannot be empty.'
				]
			],
			// Mandatory fields only.
			[
				[
					'fields' => []
				]
			],
			// Largest number of fields possible.
			[
				[
					'fields' => [
						'main_fields' => [
							'Name' => 'Test all possible fields',
							'Show header' => false,
							'Refresh interval' => '30 seconds'
						],
						'Data set' => [
							[
								'host' => ['Host*', 'one', 'two'],
								'item' => ['Item*', 'one', 'two'],
								'Aggregation function' => 'min',
								'Data set aggregation' => 'max',
								'Data set label' => 'Label 1'
							],
							[
								'host' => 'test host 2',
								'item' => 'test item 2',
								'Aggregation function' => 'first',
								'Data set aggregation' => 'count',
								'Data set label' => 'Label 2'
							]
						],
						'Displaying options' => [
							'History data selection' => 'History',
							'Draw' => 'Doughnut',
							'Width' => '40',
							'Space between sectors' => '2',
							'id:merge' => true,
							'id:merge_percent' => '10',
							'Show total value' => true,
							'id:value_size_type' => 'Custom',
							'id:value_size_custom_input' => '25',
							'Decimal places' => '1',
							'id:units_show' => true,
							'id:units' => 'GG',
							'Bold' => true,
							'Color' => '4FC3F7'
						],
						'Time period' => [
							'Time period' => 'Custom',
							'From' => 'now-3h',
							'To' => 'now-2h'
						],
						'Legend' => [
							'Show legend' => true,
							'Show aggregation function' => true,
							'Number of rows' => 2,
							'Number of columns' => 3
						]
					]
				]
			],
			// Unicode values.
			[
				[
					'fields' => [
						'main_fields' => [
							'Name' => '🙂🙃 &nbsp; <script>alert("hi!");</script>'
						],
						'Data set' => [
							'host' => '&nbsp; <script>alert("host");</script>',
							'item' => '&nbsp; <script>alert("item");</script>',
							'Data set label' => '🙂🙃 &nbsp; <script>alert("hi!");</script>'
						],
						'Displaying options' => [
							'Draw' => 'Doughnut',
							'Show total value' => true,
							'id:units_show' => true,
							'id:units' => '🙂🙃 &nbsp; <script>alert("hi!");</script>'
						]
					]
				]
			],
			// Merge sector % value too small.
			[
				[
					'fields' => [
						'Displaying options' => [
							'id:merge' => true,
							'id:merge_percent' => '0'
						]
					],
					'result' => TEST_BAD,
					'error' => 'Invalid parameter "merge_percent": value must be one of 1-10.'
				]
			],
			// Merge sector % value too big.
			[
				[
					'fields' => [
						'Displaying options' => [
							'id:merge' => true,
							'id:merge_percent' => '11'
						]
					],
					'result' => TEST_BAD,
					'error' => 'Invalid parameter "merge_percent": value must be one of 1-10.'
				]
			],
			// Decimal places value too big.
			[
				[
					'fields' => [
						'Displaying options' => [
							'Draw' => 'Doughnut',
							'Show total value' => true,
							'Decimal places' => '7'
						]
					],
					'result' => TEST_BAD,
					'error' => 'Invalid parameter "Decimal places": value must be one of 0-6.'
				]
			],
			// Empty Time period (Widget).
			[
				[
					'fields' => [
						'Time period' => [
							'Time period' => 'Widget'
						]
					],
					'result' => TEST_BAD,
					'error' => 'Invalid parameter "Time period/Widget": cannot be empty.'
				]
			],
			// Empty Time period (Custom).
			[
				[
					'fields' => [
						'Time period' => [
							'Time period' => 'Custom',
							'From' => '',
							'To' => ''
						]
					],
					'result' => TEST_BAD,
					'error' => [
						'Invalid parameter "Time period/From": cannot be empty.',
						'Invalid parameter "Time period/To": cannot be empty.'
					]
				]
			],
			// Invalid Time period (Custom).
			[
				[
					'fields' => [
						'Time period' => [
							'Time period' => 'Custom',
							'From' => '0',
							'To' => '2020-13-32'
						]
					],
					'result' => TEST_BAD,
					'error' => [
						'Invalid parameter "Time period/From": a time is expected.',
						'Invalid parameter "Time period/To": a time is expected.'
					]
				]
			],
			// Different Time period formats.
			[
				[
					'fields' => [
						'Time period' => [
							'Time period' => 'Custom',
							'From' => '2020',
							'To' => '2020-10-10 00:00:00'
						]
					]
				]
			]
		];
	}

	/**
	 * Test creation of Pie chart.
	 *
	 * @dataProvider getCreateData
	 */
	public function testDashboardPieChartWidget_Create($data){
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();

		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();
		$form = $dashboard->edit()->addWidget()->asForm();

		// Fill data and submit.
		$this->fillForm($data['fields'], $form);
		$form->submit();

		$test_good_expected = (CTestArrayHelper::get($data, 'result', TEST_GOOD) === TEST_GOOD);

		if ($test_good_expected) {
			COverlayDialogElement::ensureNotPresent();

			// Save Dashboard.
			$widget = $dashboard->getWidget($this->calculateWidgetName($data['fields']));
			$this->waitForWidgetToLoad($widget);
			$dashboard->save();

			// Assert successful save.
			$message = CMessageElement::find()->waitUntilPresent()->one();
			$this->assertTrue($message->isGood());
			$this->assertEquals('Dashboard updated', $message->getTitle());

			// Assert data in edit form.
			$form = $widget->edit();
			$this->checkForm($data['fields'], $form);
		}
		else {
			// Assert error message.
			$message = CMessageElement::find()->waitUntilPresent()->one();
			$this->assertTrue($message->isBad());

			$errors = is_array($data['error']) ? $data['error'] : [$data['error']];

			foreach ($errors as $i => $error) {
				$this->assertEquals($error, $message->getLines()->get($i)->getText());
			}
		}

		// Check total Widget count.
		$this->assertEquals($old_widget_count + ($test_good_expected ? 1 : 0), $dashboard->getWidgets()->count());
	}

	/**
	 * Waits for a widget to stop loading and to show the pie chart.
	 *
	 * @param CWidgetElement $widget    widget element to wait
	 */
	protected function waitForWidgetToLoad($widget) {
		$widget->query('xpath://div[contains(@class, "is-loading")]')->waitUntilNotPresent();
		$widget->getContent()->query('class:svg-pie-chart')->waitUntilVisible();
	}

	/**
	 * Calculates widget name from field data in data provider.
	 * If no name is provided, then use an MD5 as the name so that it is unique.
	 *
	 * @param array $fields    field data for calculating the widget name
	 */
	protected function calculateWidgetName($fields) {
		return (array_key_exists('main_fields', $fields) && array_key_exists('Name', $fields['main_fields']))
				? $fields['main_fields']['Name']
				: md5(serialize($fields));
	}

	/**
	 * Check Pie chart widget edit form contains the provided data.
	 *
	 * @param array $fields         field data to check
	 * @param CFormElement $form    form to be checked
	 */
	protected function checkForm($fields, $form) {
		// Check main fields
		$main_fields = CTestArrayHelper::get($fields, 'main_fields', []);
		$main_fields['Name'] = $this->calculateWidgetName($fields);
		$form->checkValue($main_fields);

		// Check Data set tab.
		$data_sets = $this->extractDataSets($fields);
		$last = count($data_sets) - 1;

		foreach ($data_sets as $i => $data_set) {
			// Prepare host and item fields.
			$mapping = [
				'host' => 'xpath://div[@id="ds_'.$i.'_hosts_"]/..',
				'item' => 'xpath://div[@id="ds_'.$i.'_items_"]/..'
			];

			// Change 'host' and 'item' keys to the actual selectors these fields have.
			foreach ($mapping as $field => $selector) {
				$data_set = [$selector => $data_set[$field]] + $data_set;
				unset($data_set[$field]);
			}

			// Check fields value.
			$form->checkValue($data_set);

			// Open next data set, if exists.
			if ($i !== $last) {
				$i += 2;
				$form->query('xpath:(//li[contains(@class, "list-accordion-item")])['.$i.']//button')->one()->click();
				$form->invalidate();
			}
		}

		// Check values in other tabs
		$tabs = ['Displaying options', 'Time period', 'Legend'];
		foreach ($tabs as $tab) {
			if (array_key_exists($tab, $fields)) {
				$form->selectTab($tab);
				$form->checkValue($fields[$tab]);
			}
		}
	}

	/**
	 * Fill Pie chart widget edit form with provided data.
	 *
	 * @param array $fields         field data to fill
	 * @param CFormElement $form    form to be filled
	 */
	protected function fillForm($fields, $form) {
		// Fill main fields.
		$main_fields = CTestArrayHelper::get($fields, 'main_fields', []);
		$main_fields['Name'] = $this->calculateWidgetName($fields);
		$form->fill($main_fields);

		// Fill datasets.
		$this->fillDatasets($this->extractDataSets($fields), $form);

		// Fill the other tabs.
		$tabs = ['Displaying options', 'Time period', 'Legend'];

		foreach ($tabs as $tab) {
			if (!array_key_exists($tab, $fields)) {
				continue;
			}

			$form->selectTab($tab);
			$form->fill($fields[$tab]);
		}
	}

	/**
	 * Fill "Data sets" tab with field data.
	 *
	 * @param array $data_sets      array of data sets to be filled
	 * @param CFormElement $form    CFormElement to be filled
	 */
	protected function fillDatasets($data_sets, $form) {
		$last = count($data_sets) - 1;
		// Count of data sets that already exist.
		$count_sets = $form->query('xpath://li[contains(@class, "list-accordion-item")]')->all()->count();

		foreach ($data_sets as $i => $data_set) {
			$mapping = [
				'host' => 'xpath://div[@id="ds_'.$i.'_hosts_"]/..',
				'item' => 'xpath://div[@id="ds_'.$i.'_items_"]/..'
			];

			// Exchange 'host' and 'item' keys for the actual selector the fields have.
			foreach ($mapping as $field => $selector) {
				if (array_key_exists($field, $data_set)) {
					$data_set = [$selector => $data_set[$field]] + $data_set;
					unset($data_set[$field]);
				}
			}

			$form->fill($data_set);

			// Open next dataset if needed. Either create a new one or open an existing one.
			if ($i !== $last) {
				if ($i + 1 < $count_sets) {
					$i += 2;
					$form->query('xpath:(//li[contains(@class, "list-accordion-item")])['.$i.']//button')->one()->click();
				}
				else {
					$form->query('button:Add new data set')->one()->click();
				}

				$form->invalidate();
			}
		}
	}

	/**
	 * Takes field data from data provider and returns uniform Data set array with default values set.
	 *
	 * @param $fields    field data from data provider
	 * @returns array    normalised
	 */
	protected function extractDataSets($fields) {
		$data_sets = array_key_exists('Data set', $fields)
			? $fields['Data set']
			: ['host' => 'Test Host', 'item' => 'Test Item'];

		if (CTestArrayHelper::isAssociative($data_sets)) {
			$data_sets = [$data_sets];
		}

		return $data_sets;
	}
}
