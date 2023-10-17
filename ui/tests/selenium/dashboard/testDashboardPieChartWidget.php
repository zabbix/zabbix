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
	protected const TYPE_ITEM_PATTERN = 'Item pattern';
	protected const TYPE_ITEM_LIST = 'Item list';
	protected const HOST_NAME = 'Host for Pie charts';

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

		// Create a host for Pie chart testing.
		$response = CDataHelper::createHosts([
			[
				'host' => self::HOST_NAME,
				'groups' => [['groupid' => '6']]
			]
		]);
		$host_id = $response['hostids'][self::HOST_NAME];
		CDataHelper::call('item.create', [
			[
				'hostid' => $host_id,
				'name' => 'item-1',
				'key_' => 'key-1',
				'type' => 2, // Zabbix trapper.
				'value_type' => 3 // Unint.
			],
			[
				'hostid' => $host_id,
				'name' => 'item-2',
				'key_' => 'key-2',
				'type' => 2, // Zabbix trapper.
				'value_type' => 3 // Unint.
			]
		]);
	}

	public function getLayoutData() {
		return [
			[
				[
					'action' => 'create',
					'header_text' => 'Add widget',
					'primary_button_text' => 'Add'
				]
			],
			[
				[
					'action' => 'edit',
					'header_text' => 'Edit widget',
					'primary_button_text' => 'Apply'
				]
			],
		];
	}

	/**
	 * Test the elements and layout of the Pie chart create and edit form.
	 *
	 * @dataProvider getLayoutData
	 */
	public function testDashboardPieChartWidget_LayoutEdit($data) {
		// Open the correct form.
		if ($data['action'] === 'create') {
			$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
			$dashboard = CDashboardElement::find()->one();
			$form = $dashboard->edit()->addWidget()->asForm();
		}
		else if ($data['action'] === 'edit') {
			$widget_name = 'Layout widget';
			$this->createCleanWidget($widget_name);
			$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
			$dashboard = CDashboardElement::find()->one();
			$form = $dashboard->edit()->getWidget($widget_name)->edit();
		}

		$dialog = COverlayDialogElement::find()->one();
		$form->highlight();
	}

	public function getCreateData() {
		return [
			// Mandatory fields only.
			[
				[
					'fields' => []
				]
			],
			// Mandatory fields only - Data set type Item list.
			[
				[
					'fields' => [
						'Data set' => [
							'type' => self::TYPE_ITEM_LIST,
							'host' => self::HOST_NAME,
							'items' => [
								['name' =>'item-1']
							]
						]
					]
				]
			],
			// Largest number of fields possible. Data set aggregation has to be 'none' because of Total type item.
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
								'color' => '00BCD4',
								'Aggregation function' => 'min',
								'Data set aggregation' => 'none',
								'Data set label' => 'Label 1'
							],
							[
								'type' => self::TYPE_ITEM_LIST,
								'host' => self::HOST_NAME,
								'Aggregation function' => 'max',
								'Data set aggregation' => 'none',
								'Data set label' => 'Label 2',
								'items' => [
									[
										'name' => 'item-1',
										'il_color' => '000000',
										'il_type' => 'Total'
									],
									[
										'name' => 'item-2'
									]
								]
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
			// Several data sets.
			[
				[
					'fields' => [
						'Data set' => [[], [], [], [], []]
					]
				]
			],
			// Missing Data set.
			[
				[
					'fields' => [
						'delete_data_set' => true
					],
					'result' => TEST_BAD,
					'error' => 'Invalid parameter "Data set": cannot be empty.'
				]
			],
			// Missing Host pattern.
			[
				[
					'fields' => [
						'remake_data_set' => true,
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
						'remake_data_set' => true,
						'Data set' => ['host' => '*']
					],
					'result' => TEST_BAD,
					'error' => 'Invalid parameter "Data set/1/items": cannot be empty.'
				]
			],
			// Missing Item list.
			[
				[
					'fields' => [
						'Data set' => [
							'type' => self::TYPE_ITEM_LIST
							]
					],
					'result' => TEST_BAD,
					'error' => 'Invalid parameter "Data set/1/itemids": cannot be empty.'
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

		$this->assertEditFormAfterSave($data);

		// Check total Widget count.
		$this->assertEquals(
			$old_widget_count + (int) $this->isTestGood($data),
			$dashboard->getWidgets()->count()
		);
	}

	/**
	 * Test updating of Pie chart.
	 *
	 * @dataProvider getCreateData
	 */
	public function testDashboardPieChartWidget_Update($data){
		// Create a Pie chart widget for editing.
		$widget_name = 'Edit widget';
		$this->createCleanWidget($widget_name);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();

		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();
		$form = $dashboard->edit()->getWidget($widget_name)->edit();

		// Fill data and submit.
		$this->fillForm($data['fields'], $form);
		$form->submit();

		// Assert result.
		$this->assertEditFormAfterSave($data);

		// Check total Widget count.
		$this->assertEquals($old_widget_count, $dashboard->getWidgets()->count());
	}

	/**
	 * Resets the dashboard and creates a single Pie chart widget.
	 *
	 * @param string $widget_name    name of the widget to be created
	 */
	protected function createCleanWidget($widget_name){
		CDataHelper::call('dashboard.update',
			[
				'dashboardid' => self::$dashboardid,
				'pages' => [
					[
						'widgets' => [
							[
								'name' => $widget_name,
								'type' => 'piechart',
								'fields' => [
									[
										'type' => '1',
										'name' => 'ds.0.hosts.0',
										'value' => 'Test Host'
									],
									[
										'type' => '1',
										'name' => 'ds.0.items.0',
										'value' => 'Test Items'
									],
									[
										'type' => '1',
										'name' => 'ds.0.color',
										'value' => 'FF465C'
									],
								]
							]
						]
					]
				]
			]
		);
	}

	/**
	 * Calculates if the expected test result is TEST_GOOD.
	 *
	 * @param $data    data from data provider
	 * @return bool    TRUE if TEST_GOOD expected, else FALSE
	 */
	protected function isTestGood($data) {
		return CTestArrayHelper::get($data, 'result', TEST_GOOD) === TEST_GOOD;
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
	 * Asserts that the data is saved and displayed as expected in the Edit form.
	 *
	 * @param $data    data from data provider
	 */
	protected function assertEditFormAfterSave($data) {
		$dashboard = CDashboardElement::find()->one();

		if ($this->isTestGood($data)) {
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

			// Check main fields
			$main_fields = CTestArrayHelper::get($data['fields'], 'main_fields', []);
			$main_fields['Name'] = $this->calculateWidgetName($data['fields']);
			$form->checkValue($main_fields);

			$data_sets = $this->extractDataSets($data['fields']);
			$last = count($data_sets) - 1;

			// Check Data set tab.
			foreach ($data_sets as $i => $data_set) {
				$type = CTestArrayHelper::get($data_set, 'type', self::TYPE_ITEM_PATTERN);
				unset($data_set['type']);

				// Additional steps for Item list.
				if ($type === self::TYPE_ITEM_LIST) {
					// Check Host and all Items.
					foreach ($data_set['items'] as $item) {
						$this->assertTrue($form->query('link', $data_set['host'].': '.$item['name'])->exists());
					}

					unset($data_set['host']);
				}
				$data_set = $this->remapDataSet($data_set, $i);
				$form->checkValue($data_set);

				// Open the next data set, if it exists.
				if ($i !== $last) {
					$form->query('xpath://li[contains(@class, "list-accordion-item")]['.
						($i + 2).']//button[contains(@class, "list-accordion-item-toggle")]')->one()->click();
					$form->invalidate();
				}
			}

			// Check values in other tabs
			$tabs = ['Displaying options', 'Time period', 'Legend'];
			foreach ($tabs as $tab) {
				if (array_key_exists($tab, $data['fields'])) {
					$form->selectTab($tab);
					$form->checkValue($data['fields'][$tab]);
				}
			}
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
		$delete = CTestArrayHelper::get($fields, 'delete_data_set');
		$remake = CTestArrayHelper::get($fields, 'remake_data_set');

		if ($delete || $remake) {
			$form->query('xpath://button[@title="Delete"]')->one()->click();

			if ($remake) {
				$form->query('button:Add new data set')->one()->click();
				$form->invalidate();
			}
		}

		if (!$delete) {
			$this->fillDatasets($this->extractDataSets($fields), $form);
		}

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
		// Count of data sets that already exist (needed for updating).
		$count_sets = $form->query('xpath://li[contains(@class, "list-accordion-item")]')->all()->count();

		foreach ($data_sets as $i => $data_set) {
			$type = CTestArrayHelper::get($data_set, 'type', self::TYPE_ITEM_PATTERN);
			unset($data_set['type']);

			// Special case: the first Data set is of type Item list.
			$deleted_first_set = false;
			if ($i === 0 && $type === self::TYPE_ITEM_LIST && $count_sets === 1) {
				$form->query('xpath://button[@title="Delete"]')->one()->click();
				$deleted_first_set = true;
			}

			// Open the Data set or create a new one.
			if ($i + 1 < $count_sets) {
				$form->query('xpath://li[contains(@class, "list-accordion-item")]['.
						($i + 1).']//button[contains(@class, "list-accordion-item-toggle")]')->one()->click();
			}
			else if ($i !== 0 || $deleted_first_set) {
				// Only add a new Data set if it is not the first one or the first one was deleted.
				$this->addNewDataSet($type, $form);
			}

			$form->invalidate();

			// Need additional steps when Data set type is Item list, but only if Host is set at all.
			if ($type === self::TYPE_ITEM_LIST && array_key_exists('host', $data_set)) {
				// Select Host.
				$form->query('button:Add')->one()->click();
				$dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
				$select = $dialog->query('class:multiselect-control')->asMultiselect()->one();
				$select->fill($data_set['host']);
				unset($data_set['host']);

				// Select Items.
				$table = $dialog->query('class:list-table')->asTable()->waitUntilReady()->one();
				foreach ($data_set['items'] as $item) {
					$table->findRow('Name', $item['name'])->select();
				}
				$dialog->query('xpath://div[@class="overlay-dialogue-footer"]//button[text()="Select"]')->one()->click();
				$dialog->waitUntilNotVisible();
			}

			$data_set = $this->remapDataSet($data_set, $i);
			$form->fill($data_set);
		}
	}

	/**
	 * Adds a new Data set of the correct type.
	 *
	 * @param $type    type of the data set
	 * @param $form    widget edit form element
	 */
	protected function addNewDataSet($type, $form) {
		if ($type === self::TYPE_ITEM_PATTERN) {
			$form->query('button:Add new data set')->one()->click();
		}
		else {
			$dropdown_button = $form->query('id:dataset-menu')->one();
			$dropdown_button->click();
			$dropdown_button->asPopupButton()->getMenu()->select(self::TYPE_ITEM_LIST);
		}
	}

	/**
	 * Exchanges generic field names for the actual field selectors in a Data set form.
	 *
	 * @param $data_set    Data set data
	 * @param $number      the position of this data set in UI
	 * @return array       remapped Data set
	 */
	protected function remapDataSet($data_set, $number) {
		// Key - selector mapping.
		$mapping = [
			'host' => 'xpath://div[@id="ds_'.$number.'_hosts_"]/..',
			'item' => 'xpath://div[@id="ds_'.$number.'_items_"]/..',
			'color' => 'xpath://input[@id="ds_'.$number.'_color"]/..',
			'il_color' => 'xpath://input[@id="items_'.$number.'_{id}_color"]/..',
			'il_type' => 'xpath://z-select[@id="items_'.$number.'_{id}_type"]'
		];

		// Exchange the keys for the actual selectors and clear the old key.
		foreach ($data_set as $data_set_key => $data_set_value) {
			if (!array_key_exists($data_set_key, $mapping)) {
				// Only change mapped keys.
				continue;
			}

			$data_set += [$mapping[$data_set_key] => $data_set_value];
			unset($data_set[$data_set_key]);
		}

		// Also map item fields for Item list.
		if (array_key_exists('items', $data_set)) {
			// An Item list can have several items.
			foreach ($data_set['items'] as $item_id => $item) {

				// An item can have several fields.
				foreach ($item as $field_key => $field_value) {
					if (array_key_exists($field_key, $mapping)) {
						// Set the item ID in selector, it starts at 1.
						$mapped_value = str_replace('{id}', $item_id + 1, $mapping[$field_key]);
						$data_set += [$mapped_value => $field_value];
					}
				}
			}

			unset($data_set['items']);
		}

		return $data_set;
	}

	/**
	 * Takes field data from a data provider and sets the defaults for Data sets.
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

		foreach ($data_sets as $i => $data_set) {
			if ($data_set === []) {
				$data_sets[$i] = ['host' => 'Test Host '.$i, 'item' => 'Test Item '.$i];
			}
		}

		return $data_sets;
	}
}
