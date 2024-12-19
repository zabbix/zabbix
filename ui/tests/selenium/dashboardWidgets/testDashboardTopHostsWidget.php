<?php
/*
** Copyright (C) 2001-2024 Zabbix SIA
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


require_once dirname(__FILE__).'/../common/testWidgets.php';

/**
 * @dataSource AllItemValueTypes, ItemValueWidget, GlobalMacros, TopHostsWidget
 *
 * @backup dashboard
 *
 * @onBefore prepareData
 */
class testDashboardTopHostsWidget extends testWidgets {

	/**
	 * Attach Behaviors to the test.
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			[
				'class' => CTagBehavior::class,
				'tag_selector' => 'id:tags_table_tags'
			],
			CTableBehavior::class,
			CWidgetBehavior::class
		];
	}

	protected static $updated_name = 'Top hosts update';
	protected static $aggregation_itemids;
	protected static $top_hosts_itemids;
	protected static $dashboardids;
	protected static $other_dashboardids;
	protected static $dashboardid;
	const DASHBOARD_UPDATE = 'top_host_update';
	const DASHBOARD_CREATE = 'top_host_create';
	const DASHBOARD_DELETE = 'top_host_delete';
	const DASHBOARD_REMOVE = 'top_host_remove';
	const DASHBOARD_SCREENSHOTS = 'top_host_screenshots';
	const DASHBOARD_TEXT_ITEMS = 'top_host_text_items';
	const DASHBOARD_ZOOM = 'Dashboard for zoom filter check';
	const DASHBOARD_THRESHOLD = 'Dashboard for threshold(s) check';
	const DASHBOARD_AGGREGATION = 'Dashboard for aggregation function data check';
	const DEFAULT_WIDGET_NAME = 'Top hosts';

	/**
	 * Get threshold table element with mapping set.
	 *
	 * @return CMultifieldTable
	 */
	protected function getTreshholdTable() {
		return $this->query('id:thresholds_table')->asMultifieldTable([
			'mapping' => [
				'' => [
					'name' => 'color',
					'selector' => 'class:color-picker',
					'class' => 'CColorPickerElement'
				],
				'Threshold' => [
					'name' => 'threshold',
					'selector' => 'xpath:./input',
					'class' => 'CElement'
				]
			]
		])->waitUntilVisible()->one();
	}

	/**
	 * Get Highlights table element with mapping set.
	 *
	 * @return CMultifieldTable
	 */
	protected function getHighlightsTable() {
		return $this->query('id:highlights_table')->asMultifieldTable([
			'mapping' => [
				'' => [
					'name' => 'color',
					'selector' => 'class:color-picker',
					'class' => 'CColorPickerElement'
				],
				'Regular expression' => [
					'name' => 'regexp',
					'selector' => 'xpath:./input',
					'class' => 'CElement'
				]
			]
		])->waitUntilVisible()->one();
	}

	public static function prepareData() {
		self::$dashboardids = CDataHelper::get('TopHostsWidget.dashboardids');
		self::$other_dashboardids = CDataHelper::get('ItemValueWidget.dashboardids');
		self::$aggregation_itemids = CDataHelper::get('ItemValueWidget.itemids');
		self::$top_hosts_itemids = CDataHelper::get('TopHostsWidget.itemids');

		// Add value to items for CheckTextItems test.
		CDataHelper::addItemData(99086, 1000); // 1_item.
		CDataHelper::addItemData(self::$top_hosts_itemids['top_hosts_trap_text'], 'Text for text item');
		CDataHelper::addItemData(self::$top_hosts_itemids['top_hosts_trap_log'], 'Logs for text item');
		CDataHelper::addItemData(self::$top_hosts_itemids['top_hosts_trap_char'], 'characters_here');
	}

	public function prepareTopHostsDisplayData() {
		$dashboards = CDataHelper::call('dashboard.create', [
			'name' => 'Dashboard for Top Hosts display check',
			'auto_start' => 0,
			'pages' => [
				[
					'name' => 'First Page',
					'display_period' => 3600
				]
			]
		]);
		self::$dashboardid = $dashboards['dashboardids'][0];

		$template_groups = CDataHelper::call('templategroup.create', [['name' => 'Top Hosts test template group']]);
		$template_group = $template_groups['groupids'][0];

		$templates = CDataHelper::createTemplates([
			[
				'host' => 'Template1',
				'groups' => ['groupid' => $template_group],
				'items' => [
					[
						'name' => 'Item1',
						'key_' => 'key[1]',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Item2',
						'key_' => 'key[2]',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			],
			[
				'host' => 'Template2',
				'groups' => ['groupid' => $template_group],
				'items' => [
					[
						'name' => 'Item1',
						'key_' => 'key[1]',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			]
		]);

		$template1 = $templates['templateids']['Template1'];
		$template2 = $templates['templateids']['Template2'];

		$host_groups = CDataHelper::call('hostgroup.create', [['name' => 'Top Hosts test host group']]);
		$host_group = $host_groups['groupids'][0];

		CDataHelper::call('host.create', [
			[
				'host' => 'HostA',
				'groups' => ['groupid' => $host_group],
				'templates' => [['templateid' => $template1]],
				'tags' => [['tag' => 'host', 'value' => 'A']]
			],
			[
				'host' => 'HostB',
				'groups' => ['groupid' => $host_group],
				'templates' => [['templateid' => $template1]],
				'tags' => [['tag' => 'host', 'value' => 'B']]
			],
			[
				'host' => 'HostC',
				'groups' => ['groupid' => $host_group],
				'templates' => [['templateid' => $template2]],
				'tags' => [['tag' => 'host', 'value' => 'B'], ['tag' => 'host', 'value' => 'C'], ['tag' => 'tag']]
			]
		]);

		$hostids = CDataHelper::getIds('host');

		$itemids = [];
		foreach ($hostids as $host) {
			$itemids[] = CDBHelper::getValue('SELECT itemid FROM items WHERE key_='.
					zbx_dbstr('key[1]').' AND hostid='.zbx_dbstr($host)
			);
		}

		foreach ($itemids as $i => $itemid) {
			CDataHelper::addItemData($itemid, $i);
		}

		// Create item on host in maintenance and add data to it.
		$response = CDataHelper::createHosts([
			[
				'host' => 'Host in maintenance',
				'groups' => [['groupid' => $host_group]],
				'items' => [
					[
						'name' => 'Maintenance trapper',
						'key_' => 'maintenance_trap',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			]
		]);

		$maintenance_itemid = $response['itemids']['Host in maintenance:maintenance_trap'];
		$maintenance_hostid = $response['hostids']['Host in maintenance'];

		CDataHelper::addItemData($maintenance_itemid, 100);

		// Create Maintenance and host in maintenance.
		$maintenances = CDataHelper::call('maintenance.create', [
			[
				'name' => 'Maintenance for Top Hosts widget',
				'maintenance_type' => MAINTENANCE_TYPE_NORMAL,
				'description' => 'Maintenance for icon check in Top Hosts widget',
				'active_since' => time() - 100,
				'active_till' => time() + 31536000,
				'hosts' => [['hostid' => $maintenance_hostid]],
				'timeperiods' => [[]]
			]
		]);
		$maintenanceid = $maintenances['maintenanceids'][0];

		DBexecute('UPDATE hosts SET maintenanceid='.zbx_dbstr($maintenanceid).
			', maintenance_status=1, maintenance_type='.MAINTENANCE_TYPE_NORMAL.', maintenance_from='.zbx_dbstr(time()-1000).
			' WHERE hostid='.zbx_dbstr($maintenance_hostid)
		);
	}

	public function testDashboardTopHostsWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardids[self::DASHBOARD_CREATE]);
		$dialog = CDashboardElement::find()->one()->edit()->addWidget();
		$form = $dialog->asForm();
		$this->assertEquals('Add widget', $dialog->getTitle());
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Top hosts')]);
		$this->assertEquals(['Type', 'Show header', 'Name', 'Refresh interval', 'Host groups', 'Hosts', 'Host tags',
				'Show hosts in maintenance', 'Columns', 'Order by', 'Order', 'Host limit'], $form->getLabels()->asText()
		);
		$this->assertEquals(['Columns', 'Order by', 'Host limit'], $form->getRequiredLabels());

		// Check default fields.
		$fields = [
			'Name' => ['value' => '', 'placeholder' => 'default', 'maxlength' => 255],
			'Refresh interval' => ['value' => 'Default (1 minute)'],
			'Show header' => ['value' => true],
			'id:groupids__ms' => ['value' => '', 'placeholder' => 'type here to search'],
			'id:evaltype' => ['value' => 'And/Or', 'labels' => ['And/Or', 'Or']],
			'id:tags_0_tag' => ['value' => '', 'placeholder' => 'tag', 'maxlength' => 255],
			'id:tags_0_operator' => ['value' => 'Contains', 'options' => ['Exists', 'Equals', 'Contains',
				'Does not exist', 'Does not equal', 'Does not contain']
			],
			'id:tags_0_value' => ['value' => '', 'placeholder' => 'value', 'maxlength' => 255],
			'Order' => ['value' => 'Top N', 'labels' => ['Top N', 'Bottom N']],
			'Host limit' => ['value' => 10, 'maxlength' => 4]
		];
		$this->checkFieldsAttributes($fields, $form);

		// Check Columns table.
		$this->assertEquals(['', 'Name', 'Data', 'Actions'], $form->getFieldContainer('Columns')->asTable()->getHeadersText());

		// Check clickable buttons.
		$dialog_buttons = [
			['count' => 2, 'query' => $dialog->getFooter()->query('button', ['Add', 'Cancel'])],
			['count' => 2, 'query' => $form->query('id:tags_table_tags')->one()->query('button', ['Add', 'Remove'])],
			['count' => 1, 'query' => $form->getFieldContainer('Columns')->query('button:Add')]
		];

		foreach ($dialog_buttons as $field) {
			$this->assertEquals($field['count'], $field['query']->all()->filter(CElementFilter::CLICKABLE)->count());
		}

		// Check Columns popup.
		$form->getFieldContainer('Columns')->query('button:Add')->one()->waitUntilClickable()->click();
		$column_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
		$this->assertEquals('New column', $column_dialog->getTitle());
		$column_form = $column_dialog->asForm();
		$this->assertEquals(['Add', 'Cancel'], $column_dialog->getFooter()->query('button')->all()
				->filter(CElementFilter::CLICKABLE)->asText()
		);
		$visible_labels = ['Name', 'Data', 'Item name', 'Base colour', 'Display item value as', 'Display', 'Thresholds',
			'Decimal places', 'Advanced configuration'
		];
		$hidden_labels = ['Text', 'Sparkline', 'Min', 'Max', 'Highlights', 'Show thumbnail', 'Aggregation function',
			'Time period', 'Widget', 'From', 'To', 'History data'
		];
		$this->assertEquals($visible_labels, array_values($column_form->getLabels()->filter(CElementFilter::VISIBLE)->asText()));
		$this->assertEquals($hidden_labels, array_values($column_form->getLabels()->filter(CElementFilter::NOT_VISIBLE)->asText()));

		$this->assertEquals(['Name', 'Item name'], $column_form->getRequiredLabels());
		$column_form->fill(['Advanced configuration' => true]);

		$column_default_fields = [
			'Name' => ['value' => '', 'maxlength' => 255],
			'Data' => ['value' => 'Item value', 'options' => ['Item value', 'Host name', 'Text']],
			'Text' => ['value' => '', 'placeholder' => 'Text, supports {INVENTORY.*}, {HOST.*} macros', 'maxlength' => 255,
				'visible' => false, 'enabled' => false
			],
			'Item name' => ['value' => ''],
			'xpath:.//input[@id="base_color"]/..' => ['color' => ''],
			'Display item value as' => ['value' => 'Numeric', 'labels' => ['Numeric', 'Text', 'Binary']],
			'Display' => ['value' => 'As is', 'labels' => ['As is', 'Bar', 'Indicators', 'Sparkline']],
			'Min' => ['value' => '', 'placeholder' => 'calculated', 'maxlength' => 255, 'visible' => false, 'enabled' => false],
			'Max' => ['value' => '', 'placeholder' => 'calculated', 'maxlength' => 255, 'visible' => false, 'enabled' => false],
			'id:sparkline_width' => ['value' => 1, 'maxlength' => 2, 'visible' => false, 'enabled' => false],
			'id:sparkline_fill' => ['value' => 3, 'maxlength' => 2, 'visible' => false, 'enabled' => false],
			'xpath:.//input[@id="sparkline_color"]/..' => ['color' => '42A5F5', 'visible' => false, 'enabled' => false],
			'id:sparkline_time_period_data_source' => ['value' => 'Custom', 'labels' => ['Dashboard', 'Widget', 'Custom'],
				'visible' => false, 'enabled' => false
			],
			'id:sparkline_time_period_reference' => ['value' => '', 'visible' => false],
			'id:sparkline_time_period_from' => ['value' => 'now-1h', 'placeholder' => 'YYYY-MM-DD hh:mm:ss', 'maxlength' => 255,
				'visible' => false, 'enabled' => false
			],
			'id:sparkline_time_period_to' => ['value' => 'now', 'placeholder' => 'YYYY-MM-DD hh:mm:ss', 'maxlength' => 255,
				'visible' => false, 'enabled' => false
			],
			'id:sparkline_history' => ['value' => 'Auto', 'labels' => ['Auto', 'History', 'Trends'], 'visible' => false,
				'enabled' => false
			],
			'Thresholds' => ['visible' => true],
			'Highlights' => ['visible' => false],
			'Decimal places' => ['value' => 2, 'maxlength' => 2],
			'Advanced configuration' => ['visible' => true, 'enabled' => true],
			'Aggregation function' => ['value' => 'not used', 'options' => ['not used', 'min', 'max', 'avg', 'count', 'sum',
				'first', 'last']
			],
			'Time period' => ['value' => 'Dashboard', 'labels' => ['Dashboard', 'Widget', 'Custom'], 'visible' => false,
				'enabled' => false
			],
			'Widget' => ['value' => '', 'visible' => false, 'enabled' => false],
			'id:time_period_from' => ['value' => 'now-1h', 'placeholder' => 'YYYY-MM-DD hh:mm:ss', 'maxlength' => 255,
				'visible' => false, 'enabled' => false
			],
			'id:time_period_to' => ['value' => 'now', 'placeholder' => 'YYYY-MM-DD hh:mm:ss', 'maxlength' => 255,
				'visible' => false, 'enabled' => false
			],
			'History data' => ['value' => 'Auto', 'labels' => ['Auto', 'History', 'Trends']],
			'Show thumbnail' => ['value' => false, 'visible' => false, 'enabled' => false]
		];
		$this->checkFieldsAttributes($column_default_fields, $column_form);

		// Reassign new fields' values for comparing them in other 'Data' values.
		foreach (['Aggregation function', 'Item name', 'Display item value as', 'Display', 'History data', 'Min', 'Max',
			'Decimal places', 'Advanced configuration'] as $field) {
			$column_default_fields[$field]['visible'] = false;
			$column_default_fields[$field]['enabled'] = false;
		}

		foreach (['Host name', 'Text'] as $data) {
			$column_form->fill(['Data' => CFormElement::RELOADABLE_FILL($data)]);
			$required_fields = ($data === 'Host name') ? ['Name'] : ['Name', 'Text'];
			$column_default_fields['Data']['value'] = ($data === 'Host name') ? 'Host name' : 'Text';
			$column_default_fields['Text']['visible'] = $data === 'Text';
			$column_default_fields['Text']['enabled'] = $data === 'Text';
			$column_default_fields['Thresholds']['visible'] = false;
			$column_default_fields['Thresholds']['enabled'] = true;
			$column_default_fields['Advanced configuration']['visible'] = false;
			$column_default_fields['Advanced configuration']['enabled'] = true;
			$this->checkFieldsAttributes($column_default_fields, $column_form);
			$this->assertEquals($required_fields, $column_form->getRequiredLabels());
		}

		$column_form->fill(['Data' => CFormElement::RELOADABLE_FILL('Item value')]);

		// 'Sparkline' displayed fields when Display => Sparkline option is set.
		$sparkline_fields = ['id:sparkline_width', 'id:sparkline_fill', 'xpath:.//input[@id="sparkline_color"]/..',
			'id:sparkline_history', 'id:sparkline_time_period_data_source', 'id:sparkline_time_period_from',
			'id:sparkline_time_period_to'
		];

		// Check hidden fields dependency.
		$fields_visibility = [
			'Aggregation function' => ['min', 'max', 'avg', 'count', 'sum', 'first', 'last'],
			'Display' => ['Bar', 'Indicators', 'Sparkline']
		];
		foreach ($fields_visibility as $field => $options) {
			foreach ($options as $option) {
				$column_form->fill([$field => $option]);

				if ($field === 'Aggregation function') {
					$this->assertTrue($column_form->getLabel('Time period')->isDisplayed());
				}

				if ($option === 'Bar' || $option === 'Indicators') {
					foreach (['Min', 'Max'] as $bar_range) {
						$this->assertTrue($column_form->getField($bar_range)->isDisplayed());
					}
				}

				if ($option === 'Sparkline') {
					foreach ($sparkline_fields as $locator) {
						$this->assertTrue($column_form->query($locator)->one()->isDisplayed());
					}

					foreach (['id:sparkline_width', 'id:sparkline_fill'] as $id) {
						$this->assertRangeSliderParameters($column_form, $id, ['min' => '0', 'max' => '10', 'step' => '1']);
					}

				// Check that reference widget multiselect is not visible by default.
				$this->assertFalse($column_form->query('id:sparkline_time_period_reference')->one()->isDisplayed());
				}
			}
		}

		// Check required fields and calendar element when Aggregation and Sparkline Custom/Widget time period is selected.
		foreach (['Custom', 'Widget'] as $time_selector) {
			$column_form->fill([
				'Time period' => $time_selector,
				'id:sparkline_time_period_data_source' => $time_selector
			]);

			if ($time_selector === 'Custom') {
				foreach (['from', 'to'] as $element) {
					$this->assertTrue($column_form->query('id', 'sparkline_time_period_'.$element.'_calendar')->one()
							->isClickable()
					);

					// Check that 'From' and 'To' are required fields.
					$this->assertTrue($column_form->query('xpath:.//label[@for="sparkline_time_period_'.$element.'"]')->one()
							->hasClass('form-label-asterisk')
					);
					$this->assertEquals(['Name', 'Item name', 'From', 'To'], $column_form->getRequiredLabels());
				}
			}
			else {
				foreach (['id:sparkline_time_period_from', 'id:sparkline_time_period_to', 'From', 'To'] as $locator) {
					$this->assertFalse($column_form->getField($locator)->isDisplayed());
				}

				$this->assertEquals(['Name', 'Item name', 'Widget'], $column_form->getRequiredLabels());

				// Check sparkline required field with selected widget time period.
				$this->assertTrue($column_form->query('xpath:.//label[@for="sparkline_time_period_reference_ms"]')
						->one()->hasClass('form-label-asterisk')
				);
			}
		}

		// Check Display item value as and color Thresholds/Highlights tables dependency.
		foreach (['Numeric', 'Text', 'Binary'] as $display) {
			$column_form->fill(['Display item value as' => $display]);

			if ($display === 'Binary') {
				$this->assertTrue($column_form->getField('Show thumbnail')->isVisible());

				foreach (['Display', 'Thresholds', 'Highlights', 'Decimal places'] as $label) {
					$this->assertFalse($column_form->getField($label)->isVisible());
				}
			}
			else {
				$color_table = ($display === 'Numeric')
					? [
						'label' => 'Thresholds',
						'header' => 'Threshold',
						'color_selector' => 'xpath:.//input[@id="thresholds_0_color"]/..',
						'input_selector' => 'id:thresholds_0_threshold',
						'color' => 'FCCB1D'
					]
					: [
						'label' => 'Highlights',
						'header' => 'Regular expression',
						'color_selector' => 'xpath:.//input[@id="highlights_0_color"]/..',
						'input_selector' => 'id:highlights_0_pattern',
						'color' => 'E65660'
					];

				$color_container = $column_form->getFieldContainer(($color_table['label']));
				$this->assertEquals(['', $color_table['header'], ''], $color_container->asTable()->getHeadersText());
				$color_container->query('button:Add')->one()->waitUntilClickable()->click();

				$this->checkFieldsAttributes([
						$color_table['color_selector'] => [$color_table['color']],
						$color_table['input_selector'] => ['value' => '', 'maxlength' => 255]
					], $column_form
				);

				$this->assertEquals(2, $color_container->query('button', ['Add', 'Remove'])->all()
						->filter(CElementFilter::CLICKABLE)->count()
				);
			}
		}

		$column_dialog->close();
		$dialog->close();
	}

	public static function getCreateData() {
		return [
			// #0 Error message adding widget without any column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Widget without columns'
					],
					'main_error' => [
						'Invalid parameter "Columns": cannot be empty.',
						'Invalid parameter "Order by": an integer is expected.'
					]
				]
			],
			// #1 error message adding widget without item column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Widget without item column name'
					],
					'column_fields' => [
						[
							'Data' => 'Host name'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/name": cannot be empty.'
					]
				]
			],
			// #2 Add characters in host limit field.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Host limit error with item column',
						'Host limit' => 'zzz'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item name' => 'Available memory'
						]
					],
					'main_error' => [
						'Invalid parameter "Host limit": value must be one of 1-1000.'
					]
				]
			],
			// #3 Add incorrect value to host limit field without item column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Host limit error without item column',
						'Host limit' => '3333'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Host name'
						]
					],
					'main_error' => [
						'Invalid parameter "Host limit": value must be one of 1-1000.'
					]
				]
			],
			// #4 Colour error in host name column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Colour error in Host name column'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Host name',
							'Base colour' => '!@#$%^'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/base_color": a hexadecimal colour code (6 symbols) is expected.'
					]
				]
			],
			// #5 Check error adding text column without any value.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Error in empty text column'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Text'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/text": cannot be empty.'
					]
				]
			],
			// #6 Colour error in text column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Error in text column colour'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Text',
							'Text' => 'Here is some text',
							'Base colour' => '!@#$%^'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/base_color": a hexadecimal colour code (6 symbols) is expected.'
					]
				]
			],
			// #7 Error when there is no item in item column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Error without item in item column'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/item": cannot be empty.'
					]
				]
			],
			// #8 Error when time period "From" is below minimum time period.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Incorrect time period'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Advanced configuration' => true,
							'Aggregation function' => 'min',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-58s'
						]
					],
					'column_error' => [
						'Minimum time period to display is 1 minute.'
					]
				]
			],
			// #9 Error when time period "From" is above maximum time period.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Maximum time period in From field'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Advanced configuration' => true,
							'Aggregation function' => 'max',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-2y-2s'
						]
					],
					'column_error' => [
						'Maximum time period to display is {days} days.'
					],
					'days_count' => true
				]
			],
			// #10 Error when time period "To" is below minimum time period.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Incorrect time period'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Advanced configuration' => true,
							'Aggregation function' => 'avg',
							'Time period' => 'Custom',
							'id:time_period_to' => 'now-59m-2s'
						]
					],
					'column_error' => [
						'Minimum time period to display is 1 minute.'
					]
				]
			],
			// #11 Error when time period between "From" and "To" fields is > 730 days (731 days in case of leap year).
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Incorrect time period'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Advanced configuration' => true,
							'Aggregation function' => 'count',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-3y-25h',
							'id:time_period_to' => 'now-1y'
						]
					],
					'column_error' => [
						'Maximum time period to display is {days} days.'
					],
					'days_count' => true
				]
			],
			// #12 Error when both time period selectors have invalid values.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Both time selectors have invalid values'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Advanced configuration' => true,
							'Aggregation function' => 'sum',
							'Time period' => 'Custom',
							'id:time_period_from' => 'a',
							'id:time_period_to' => 'b'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/From": a time is expected.',
						'Invalid parameter "/1/To": a time is expected.'
					]
				]
			],
			// #13 Error when both time period selectors are empty.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Both time selectors have invalid values'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Advanced configuration' => true,
							'Aggregation function' => 'first',
							'Time period' => 'Custom',
							'id:time_period_from' => '',
							'id:time_period_to' => ''
						]
					],
					'column_error' => [
						'Invalid parameter "/1/From": cannot be empty.',
						'Invalid parameter "/1/To": cannot be empty.'
					]
				]
			],
			// #14 Error when widget field is empty.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Widget field is empty'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Advanced configuration' => true,
							'Aggregation function' => 'last',
							'Time period' => 'Widget'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/Widget": cannot be empty.'
					]
				]
			],
			/**
			 * TODO: At the moment error handling is inconsistent for column fields. Uncomment or replace expected column
			 *  error(s) after the DEV-3951 fix.
			 */
			// #15 Error when Sparkline time period "From" is below minimum time period.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Incorrect Sparkline time period'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Sparkline',
							'id:sparkline_time_period_from' => 'now-58s'
						]
					],
					'column_error' => [
						'Minimum time period to display is 1 minute.'
//						'Invalid parameter "/1/sparkline/time_period/from": minimum time period to display is 1 minute.'
					]
				]
			],
			// #16 Error when sparkline time period "From" is above maximum time period.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Incorrect Sparkline time period'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Sparkline',
							'id:sparkline_time_period_from' => 'now-2y-2s'
						]
					],
					'column_error' => [
						'Maximum time period to display is {days} days.'
//						'Invalid parameter "/1/sparkline/time_period/from": maximum time period to display is {days} days.'
					],
					'days_count' => true
				]
			],
			// #17 Error when sparkline time period "To" is below minimum time period.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Incorrect Sparkline time period'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Sparkline',
							'id:sparkline_time_period_to' => 'now-59m-2s'
						]
					],
					'column_error' => [
						'Minimum time period to display is 1 minute.'
//						'Invalid parameter "/1/sparkline/time_period/to": minimum time period to display is 1 minute.'
					]
				]
			],
			// #18 Error when sparkline time period between "From" and "To" fields is > 730 days (731 days in case of leap year).
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Incorrect Sparkline time period'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Sparkline',
							'id:sparkline_time_period_from' => 'now-3y-25h',
							'id:sparkline_time_period_to' => 'now-1y'
						]
					],
					'column_error' => [
						'Maximum time period to display is {days} days.'
//						'Invalid parameter "/1/sparkline/time_period/from": maximum time period to display is {days} days.'
					],
					'days_count' => true
				]
			],
			// #19 Error when sparkline time period fields 'From' and 'To' are empty.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Incorrect Sparkline time period'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Sparkline',
							'id:sparkline_time_period_from' => '',
							'id:sparkline_time_period_to' => ''
						]
					],
					'column_error' => [
						'Invalid parameter "Time period/From": cannot be empty.',
						'Invalid parameter "Time period/To": cannot be empty.'
//						'Invalid parameter "/1/sparkline/time_period/from": cannot be empty.',
//						'Invalid parameter "/1/sparkline/time_period/to": cannot be empty.'
					]
				]
			],
			// #20 Error when sparkline time period fields 'From' and 'To' with invalid value.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Incorrect Sparkline time period'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Sparkline',
							'id:sparkline_time_period_from' => '!',
							'id:sparkline_time_period_to' => '@'
						]
					],
					'column_error' => [
						'Invalid parameter "Time period/From": a time is expected.',
						'Invalid parameter "Time period/To": a time is expected.'
//						'Invalid parameter "/1/sparkline/time_period/from": a time is expected.',
//						'Invalid parameter "/1/sparkline/time_period/to": a time is expected.'
					]
				]
			],
			// #21 Error when sparkline widget field is empty.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Widget field is empty'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Sparkline',
							'id:sparkline_time_period_data_source' => 'Widget'
						]
					],
					'column_error' => [
						'Invalid parameter "Time period/Widget": cannot be empty.'
//						'Invalid parameter "/1/sparkline/time_period/widget": cannot be empty.'
					]
				]
			],
			// #22 Error when invalid colour is picked for sparkline charts.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Invalid sparkline colour'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Display' => 'Sparkline',
							'Item name' => 'Available memory',
							'xpath:.//input[@id="sparkline_color"]/..' => '!@#$%^'
						]
					],
					'column_error' => [
						'Invalid parameter "Colour": a hexadecimal colour code (6 symbols) is expected.'
//						'Invalid parameter "/1/sparkline/sparkline_color": a hexadecimal colour code (6 symbols) is expected.'
					]
				]
			],
			// #23 Error when colour picker is empty.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Invalid sparkline colour picker is empty'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Display' => 'Sparkline',
							'Item name' => 'Available memory',
							'xpath:.//input[@id="sparkline_color"]/..' => ''
						]
					],
					'column_error' => [
						'Invalid parameter "Colour": cannot be empty.'
//						'Invalid parameter "/1/sparkline/sparkline_color": cannot be empty.'
					]
				]
			],
			// #24 Error when incorrect min value added.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Incorrect min value'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Bar',
							'Min' => 'zzz'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/min": a number is expected.'
					]
				]
			],
			// #25 Error when incorrect max value added.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Incorrect max value'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Bar',
							'Max' => 'zzz'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/max": a number is expected.'
					]
				]
			],
			// #26 Color error in item column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Error in item column color'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Base colour' => '!@#$%^'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/base_color": a hexadecimal colour code (6 symbols) is expected.'
					]
				]
			],
			// #27 Color error when incorrect hexadecimal added in first threshold.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Error in item column threshold color'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Thresholds' => [
								[
									'threshold' => '1',
									'color' => '!@#$%^'
								]
							]
						]
					],
					'column_error' => [
						'Invalid parameter "/1/thresholds/1/color": a hexadecimal colour code (6 symbols) is expected.'
					]
				]
			],
			// #28 Color error when incorrect hexadecimal added in second threshold.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Error in item column second threshold color'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Thresholds' => [
								[
									'threshold' => '1',
									'color' => '4000FF'
								],
								[
									'threshold' => '2',
									'color' => '!@#$%^'
								]
							]
						]
					],
					'column_error' => [
						'Invalid parameter "/1/thresholds/2/color": a hexadecimal colour code (6 symbols) is expected.'
					]
				]
			],
			// #29 Error message when incorrect value added to threshold.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Name' => 'Error in item column second threshold color'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Thresholds' => [
								[
									'threshold' => 'zzz',
									'color' => '4000FF'
								]
							]
						]
					],
					'column_error' => [
						'Invalid parameter "/1/thresholds/1/threshold": a number is expected.'
					]
				]
			],
			// #30 Minimum needed values to create and submit widget.
			[
				[
					'main_fields' => [],
					'column_fields' => [
						[
							'Name' => 'Min values',
							'Data' => 'Item value',
							'Item name' => 'Available memory'
						]
					]
				]
			],
			// #31 All fields filled for main form with all tags.
			[
				[
					'main_fields' => [
						'Name' => 'Name of Top hosts widget 😅',
						'Refresh interval' => 'Default (1 minute)',
						'Host groups' => 'Zabbix servers',
						'Hosts' => 'ЗАББИКС Сервер',
						'Show hosts in maintenance' => true,
						'Order' => 'Bottom N',
						'Host limit' => '99'
					],
					'tags' => [
						['name' => 'value', 'value' => '12345', 'operator' => 'Contains'],
						['name' => '@#$%@', 'value' => 'a1b2c3d4', 'operator' => 'Equals'],
						['name' => 'AvF%21', 'operator' => 'Exists'],
						['name' => '_', 'operator' => 'Does not exist'],
						['name' => 'кириллица', 'value' => 'BcDa', 'operator' => 'Does not equal'],
						['name' => 'aaa6 😅', 'value' => 'bbb6 😅', 'operator' => 'Does not contain']
					],
					'column_fields' => [
						[
							'Name' => 'All fields 😅',
							'Data' => 'Item value',
							'Item name' => 'Available memory'
						]
					]
				]
			],
			// #32 Change order column for several items.
			[
				[
					'main_fields' => [
						'Name' => 'Several item columns',
						'Order by' => 'duplicated column name'
					],
					'column_fields' => [
						[
							'Name' => 'duplicated column name',
							'Data' => 'Item value',
							'Item name' => 'Available memory'
						],
						[
							'Name' => 'duplicated column name',
							'Data' => 'Item value',
							'Item name' => 'Available memory in %'
						]
					]
				]
			],
			// #33 Several item columns with different Aggregation function and custom "From" time period.
			[
				[
					'main_fields' => [
						'Name' => 'All available aggregation function'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Name' => 'min',
							'Advanced configuration' => true,
							'Aggregation function' => 'min',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1m', // minimum time period to display is 1 minute.
							'Item name' => 'Available memory'
						],
						[
							'Data' => 'Item value',
							'Name' => 'max',
							'Advanced configuration' => true,
							'Aggregation function' => 'max',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-20m',
							'Item name' => 'Available memory'
						],
						[
							'Data' => 'Item value',
							'Name' => 'avg',
							'Advanced configuration' => true,
							'Aggregation function' => 'avg',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-20h',
							'Item name' => 'Available memory'
						],
						[
							'Data' => 'Item value',
							'Name' => 'count',
							'Advanced configuration' => true,
							'Aggregation function' => 'count',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-20d',
							'Item name' => 'Available memory'
						],
						[
							'Data' => 'Item value',
							'Name' => 'sum',
							'Advanced configuration' => true,
							'Aggregation function' => 'sum',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-20w',
							'Item name' => 'Available memory'
						],
						[
							'Data' => 'Item value',
							'Name' => 'first',
							'Advanced configuration' => true,
							'Aggregation function' => 'first',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-20M',
							'Item name' => 'Available memory'
						],
						[
							'Data' => 'Item value',
							'Name' => 'last',
							'Advanced configuration' => true,
							'Aggregation function' => 'last',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-730d', // maximum time period to display is 730 days.
							'Item name' => 'Available memory'
						]
					],
					'screenshot' => true
				]
			],
			// #34 Several item columns with different display, custom "From" time period, min/max and history data.
			[
				[
					'main_fields' => [
						'Name' => 'Different display and history data fields'
					],
					'column_fields' => [
						[
							'Name' => 'Column_1',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'As is',
							'Advanced configuration' => true,
							'Aggregation function' => 'min',
							'Time period' => 'Custom',
							'id:time_period_to' => 'now-30m',
							'History data' => 'History'
						],
						[
							'Name' => 'Column_2',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'As is',
							'Advanced configuration' => true,
							'Aggregation function' => 'max',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-2h',
							'id:time_period_to' => 'now-1h',
							'History data' => 'Trends'
						],
						[
							'Name' => 'Column_3',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Bar',
							'Min' => '2',
							'Max' => '',
							'Advanced configuration' => true,
							'History data' => 'Auto'
						],
						[
							'Name' => 'Column_4',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Bar',
							'Min' => '',
							'Max' => '100',
							'Advanced configuration' => true,
							'History data' => 'History'
						],
						[
							'Name' => 'Column_5',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Bar',
							'Min' => '50',
							'Max' => '100',
							'Advanced configuration' => true,
							'History data' => 'Trends'
						],
						[
							'Name' => 'Column_6',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Indicators',
							'Min' => '2',
							'Max' => '',
							'Advanced configuration' => true,
							'History data' => 'Auto'
						],
						[
							'Name' => 'Column_7',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Indicators',
							'Min' => '',
							'Max' => '100',
							'Advanced configuration' => true,
							'History data' => 'History'
						],
						[
							'Name' => 'Column_8',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Indicators',
							'Min' => '50',
							'Max' => '100',
							'Advanced configuration' => true,
							'History data' => 'Trends'
						]
					]
				]
			],
			// #35 Add column with different Base color.
			[
				[
					'main_fields' => [
						'Name' => 'Another base color'
					],
					'column_fields' => [
						[
							'Name' => 'Column name',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Base colour' => '039BE5'
						]
					]
				]
			],
			// #36 Add sparkline columns with custom configuration.
			[
				[
					'main_fields' => [
						'Name' => 'Sparkline columns with custom configuration'
					],
					'column_fields' => [
						[
							'Name' => 'Sparkline_0',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Sparkline',
							'id:sparkline_width' => '😅',
							'id:sparkline_fill' => '-1',
							'id:sparkline_history' => 'Trends'
						],
						[
							'Name' => 'Sparkline_1',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Sparkline',
							'id:sparkline_width' => '0',
							'id:sparkline_fill' => '10',
							'xpath:.//input[@id="sparkline_color"]/..' => 'BF00FF',
							'id:sparkline_time_period_from' => 'now-33m-33s',
							'id:sparkline_time_period_to' => 'now-32m-33s',
							'id:sparkline_history' => 'Auto'
						],
						[
							'Name' => 'Sparkline_2',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Sparkline',
							'id:sparkline_width' => '10',
							'id:sparkline_fill' => '0',
							'xpath:.//input[@id="sparkline_color"]/..' => '000000',
							'id:sparkline_time_period_from' => 'now-2y',
							'id:sparkline_time_period_to' => 'now-1y',
							'id:sparkline_history' => 'History'
						],
						[
							'Name' => 'Sparkline_3',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Sparkline',
							'id:sparkline_width' => '0',
							'id:sparkline_fill' => '0',
							'xpath:.//input[@id="sparkline_color"]/..' => 'FFBF00',
							'id:sparkline_time_period_from' => 'now-2h',
							'id:sparkline_time_period_to' => 'now-1h',
							'id:sparkline_history' => 'Trends'
						],
						[
							'Name' => 'Sparkline_4',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Sparkline',
							'id:sparkline_width' => '10',
							'id:sparkline_fill' => '10',
							'xpath:.//input[@id="sparkline_color"]/..' => 'BFFF00',
							'id:sparkline_time_period_data_source' => 'Widget',
							'xpath:.//div[@id="sparkline_time_period_reference"]/..' => 'Graph (classic) for time period '.
								'check via widget'
						],
						[
							'Name' => 'Sparkline_5',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Sparkline',
							'id:sparkline_width' => '5',
							'id:sparkline_fill' => '5',
							'xpath:.//input[@id="sparkline_color"]/..' => '558B2F',
							'id:sparkline_time_period_data_source' => 'Dashboard'
						]
					],
					'replace' => true
				]
			],
			// #37 Add column with Threshold without color change.
			[
				[
					'main_fields' => [
						'Name' => 'One Threshold'
					],
					'column_fields' => [
						[
							'Name' => 'Column with threshold',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Thresholds' => [
								[
									'threshold' => '5'
								]
							]
						]
					]
				]
			],
			// #38 Add several columns with Threshold without color change.
			[
				[
					'main_fields' => [
						'Name' => 'Several Threshold'
					],
					'column_fields' => [
						[
							'Name' => 'Column with some thresholds',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Thresholds' => [
								[
									'threshold' => '1'
								],
								[
									'threshold' => '100'
								],
								[
									'threshold' => '1000'
								]
							]
						]
					]
				]
			],
			// #39 Add several columns with Threshold with color change and without color.
			[
				[
					'main_fields' => [
						'Name' => 'Several Thresholds with colors'
					],
					'column_fields' => [
						[
							'Name' => 'Thresholds with colors',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Thresholds' => [
								[
									'threshold' => '1',
									'color' => 'FFEB3B'
								],
								[
									'threshold' => '100',
									'color' => 'AAAAAA'
								],
								[
									'threshold' => '1000',
									'color' => 'AAAAAA'
								],
								[
									'threshold' => '10000',
									'color' => ''
								]
							]
						]
					]
				]
			],
			// #40 Add Host name columns.
			[
				[
					'main_fields' => [
						'Name' => 'Host name columns'
					],
					'column_fields' => [
						[
							'Data' => 'Host name',
							'Name' => 'This is host name',
							'Base colour' => '039BE5'
						],
						[
							'Name' => 'Host name column 2',
							'Data' => 'Host name'
						],
						[
							'Name' => 'Host name column 3',
							'Data' => 'Item value',
							'Item name' => 'Available memory'
						]
					]
				]
			],
			// #41 Add Text columns.
			[
				[
					'main_fields' => [
						'Name' => 'Text columns'
					],
					'column_fields' => [
						[
							'Name' => 'Text column name 1',
							'Data' => 'Text',
							'Text' => 'Here is some text 😅'
						],
						[
							'Data' => 'Text',
							'Text' => 'Here is some text 2',
							'Name' => 'Text column name 2'
						],
						[
							'Data' => 'Text',
							'Text' => 'Here is some text 3',
							'Name' => 'Text column name 3',
							'Base colour' => '039BE5'
						],
						[
							'Name' => 'Text column name 4',
							'Data' => 'Item value',
							'Item name' => 'Available memory'
						]
					]
				]
			],
			// #42 Spaces in input fields.
			[
				[
					'trim' => true,
					'main_fields' => [
						'Name' => '            Spaces            ',
						'Host limit' => ' 1 '
					],
					'tags' => [
						['name' => '   tag     ', 'value' => '     value       ', 'operator' => 'Equals']
					],
					'column_fields' => [
						[
							'Name' => '     Text column name with spaces 1     ',
							'Data' => 'Text',
							'Text' => '          Spaces in text          ',
							'Base colour' => 'A5D6A7'
						],
						[
							'Name' => '     Text column name with spaces 2     ',
							'Data' => 'Host name',
							'Base colour' => '0040FF'
						],
						[
							'Name' => '     🦉Text column name with spaces 3     ',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Advanced configuration' => true,
							'Aggregation function' => 'avg',
							'Time period' => 'Custom',
							'id:time_period_from' => '         now-2h         ',
							'id:time_period_to' => '         now-1h         ',
							'Display' => 'Bar',
							'Min' => '         2       ',
							'Max' => '         200     ',
							'Decimal places' => ' 2',
							'Thresholds' => [
								[
									'threshold' => '    5       '
								]
							],
							'History data' => 'Trends'
						],
						[
							'Name' => '     Text column name with spaces 4🦉     ',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Sparkline',
							'id:sparkline_width' => ' 0',
							'id:sparkline_fill' => ' 5',
							'id:sparkline_time_period_from' => '         now-2m         ',
							'id:sparkline_time_period_to' => '         now-1m         ',
							'id:sparkline_history' => 'Trends'
						]
					]
				]
			],
			// #43 User macros in input fields.
			[
				[
					'main_fields' => [
						'Name' => '{$USERMACRO}'
					],
					'tags' => [
						['name' => '{$USERMACRO}', 'value' => '{$USERMACRO}', 'operator' => 'Equals']
					],
					'column_fields' => [
						[
							'Name' => '{$USERMACRO1}',
							'Data' => 'Text',
							'Text' => '{$USERMACRO2}'
						],
						[
							'Name' => '{$USERMACRO2}',
							'Data' => 'Host name',
							'Base colour' => '0040DD'
						],
						[
							'Name' => '{$USERMACRO3}',
							'Data' => 'Item value',
							'Item name' => 'Available memory'
						]
					]
				]
			],
			// #44 Global macros in input fields.
			[
				[
					'main_fields' => [
						'Name' => '{HOST.HOST}'
					],
					'tags' => [
						['name' => '{HOST.NAME}', 'value' => '{ITEM.NAME}', 'operator' => 'Equals']
					],
					'column_fields' => [
						[
							'Name' => '{INVENTORY.ALIAS}',
							'Data' => 'Text',
							'Text' => '{HOST.DNS}'
						],
						[
							'Name' => '{INVENTORY.ALIAS}',
							'Data' => 'Host name',
							'Base colour' => '0040DD'
						],
						[
							'Name' => '{HOST.IP}',
							'Data' => 'Item value',
							'Item name' => 'Available memory'
						]
					]
				]
			]
		];
	}

	/**
	 * Create Top Hosts widget.
	 *
	 * @dataProvider getCreateData
	 */
	public function testDashboardTopHostsWidget_Create($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardids[self::DASHBOARD_CREATE]);
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();
		$form = $dashboard->edit()->addWidget()->asForm();
		$form->fill(['Type' => 'Top hosts']);
		COverlayDialogElement::find()->waitUntilReady()->one();

		// Add new column.
		if (array_key_exists('column_fields', $data)) {
			$this->fillColumnForm($data, 'create');
		}

		if (array_key_exists('tags', $data)) {
			$this->setTags($data['tags']);
		}

		// Take a screenshot to test draggable object position of columns.
		if (array_key_exists('screenshot', $data)) {
			$this->page->removeFocus();
			$this->assertScreenshot($form->query('id:list_columns')->waitUntilPresent()->one(), 'Top hosts columns');
		}

		$form->fill($data['main_fields']);
		$form->submit();
		$this->page->waitUntilReady();

		// Trim trailing and leading spaces in expected values before comparison.
		if (CTestArrayHelper::get($data, 'trim', false)) {
			$data = CTestArrayHelper::trim($data);
		}

		// Sparkline 'Width' and 'Fill' values are replaced by 0 when invalid data is passed.
		if (array_key_exists('replace', $data)) {
			$data['column_fields'][0]['id:sparkline_width'] = '0';
			$data['column_fields'][0]['id:sparkline_fill'] = '0';
		}

		// Check error message in main widget form.
		if (array_key_exists('main_error', $data)) {
			$this->assertMessage(TEST_BAD, null, $data['main_error']);
		}

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			COverlayDialogElement::find()->one()->close();
			$dashboard->save();

			// Check that new widget is not added.
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');
			$this->assertEquals($old_widget_count, $dashboard->getWidgets()->count());
		}
		else {
			// Make sure that the widget is present before saving the dashboard.
			$header = CTestArrayHelper::get($data['main_fields'], 'Name', self::DEFAULT_WIDGET_NAME);
			$dashboard->getWidget($header);
			$dashboard->save();

			// Check message that dashboard saved.
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Check widget amount that it is added.
			$this->assertEquals($old_widget_count + 1, $dashboard->getWidgets()->count());
			$this->checkWidget($header, $data, 'create');
		}
	}

	/**
	 * Top Hosts widget simple update without any field change.
	 */
	public function testDashboardTopHostsWidget_SimpleUpdate() {
		// Hash before simple update.
		$old_hash = CDBHelper::getHash(self::SQL);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardids[self::DASHBOARD_UPDATE]);
		$dashboard = CDashboardElement::find()->one();
		$dashboard->edit()->getWidget(self::$updated_name)->edit()->submit();
		$dashboard->save();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Compare old hash and new one.
		$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
	}

	public static function getUpdateData() {
		return [
			// #0 Incorrect threshold color.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Name' => 'Incorrect threshold color',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Thresholds' => [
								[
									'action' => USER_ACTION_UPDATE,
									'index' => 0,
									'threshold' => '100',
									'color' => '#$@#$@'
								]
							]
						]
					],
					'column_error' => [
						'Invalid parameter "/1/thresholds/1/color": a hexadecimal colour code (6 symbols) is expected.'
					]
				]
			],
			// #1 Incorrect min value.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Indicators',
							'Advanced configuration' => true,
							'History data' => 'Trends',
							'Min' => 'zzz'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/min": a number is expected.'
					]
				]
			],
			// #2 Incorrect max value.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Indicators',
							'Advanced configuration' => true,
							'History data' => 'Trends',
							'Max' => 'zzz'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/max": a number is expected.'
					]
				]
			],
			// #3 Error message when update Host limit incorrectly.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' => [
						'Host limit' => '0'
					],
					'main_error' => [
						'Invalid parameter "Host limit": value must be one of 1-1000.'
					]
				]
			],
			// #4 Time period "From" is below minimum time period.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Advanced configuration' => true,
							'Aggregation function' => 'min',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-58s'
						]
					],
					'column_error' => [
						'Minimum time period to display is 1 minute.'
					]
				]
			],
			// #5 Time period "From" is above maximum time period.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Advanced configuration' => true,
							'Aggregation function' => 'max',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-2y-1s'
						]
					],
					'column_error' => [
						'Maximum time period to display is {days} days.'
					],
					'days_count' => true
				]
			],
			// #6 Error when time period "To" is below minimum time period.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Advanced configuration' => true,
							'Aggregation function' => 'sum',
							'Time period' => 'Custom',
							'id:time_period_to' => 'now-3542s'
						]
					],
					'column_error' => [
						'Minimum time period to display is 1 minute.'
					]
				]
			],
			// #7 Error when time period between "From" and "To" fields is > 730 days (731 days in case of leap year).
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Advanced configuration' => true,
							'Aggregation function' => 'first',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-26M',
							'id:time_period_to' => 'now-1M'
						]
					],
					'column_error' => [
						'Maximum time period to display is {days} days.'
					],
					'days_count' => true
				]
			],
			// #8 Error when both time period selectors have invalid values.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Advanced configuration' => true,
							'Aggregation function' => 'sum',
							'Time period' => 'Custom',
							'id:time_period_from' => 'a',
							'id:time_period_to' => 'b'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/From": a time is expected.',
						'Invalid parameter "/1/To": a time is expected.'
					]
				]
			],
			// #9 Error when both time period selectors are empty.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Advanced configuration' => true,
							'Aggregation function' => 'sum',
							'Time period' => 'Custom',
							'id:time_period_from' => '',
							'id:time_period_to' => ''
						]
					],
					'column_error' => [
						'Invalid parameter "/1/From": cannot be empty.',
						'Invalid parameter "/1/To": cannot be empty.'
					]
				]
			],
			// #10 Error when widget field is empty.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Advanced configuration' => true,
							'Aggregation function' => 'sum',
							'Time period' => 'Widget'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/Widget": cannot be empty.'
					]
				]
			],
			// #11 No item error in column.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item name' => ''
						]
					],
					'column_error' => [
						'Invalid parameter "/1/item": cannot be empty.'
					]
				]
			],
			// #12 Incorrect base color.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Base colour' => '#$%$@@'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/base_color": a hexadecimal colour code (6 symbols) is expected.'
					]
				]
			],
			/**
			 * TODO: At the moment error handling is inconsistent for column fields. Uncomment or replace expected column
			 *  error(s) after the DEV-3951 fix.
			 */
			// #13 Error when Sparkline time period "From" is below minimum time period.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Sparkline',
							'id:sparkline_time_period_from' => 'now-58s'
						]
					],
					'column_error' => [
						'Minimum time period to display is 1 minute.'
//						'Invalid parameter "/1/sparkline/time_period/from": minimum time period to display is 1 minute.'
					]
				]
			],
			// #14 Error when sparkline time period "From" is above maximum time period.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Sparkline',
							'id:sparkline_time_period_from' => 'now-2y-2s'
						]
					],
					'column_error' => [
						'Maximum time period to display is {days} days.'
//						'Invalid parameter "/1/sparkline/time_period/from": maximum time period to display is {days} days.'
					],
					'days_count' => true
				]
			],
			// #15 Error when sparkline time period "To" is below minimum time period.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Sparkline',
							'id:sparkline_time_period_to' => 'now-59m-2s'
						]
					],
					'column_error' => [
						'Minimum time period to display is 1 minute.'
//						'Invalid parameter "/1/sparkline/time_period/to": minimum time period to display is 1 minute.'
					]
				]
			],
			// #16 Error when sparkline time period between "From" and "To" fields is > 730 days (731 days in case of leap year).
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Sparkline',
							'id:sparkline_time_period_from' => 'now-3y-25h',
							'id:sparkline_time_period_to' => 'now-1y'
						]
					],
					'column_error' => [
						'Maximum time period to display is {days} days.'
//						'Invalid parameter "/1/sparkline/time_period/from": maximum time period to display is {days} days.'
					],
					'days_count' => true
				]
			],
			// #17 Error when sparkline time period From/To are empty.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Sparkline',
							'id:sparkline_time_period_from' => '',
							'id:sparkline_time_period_to' => ''
						]
					],
					'column_error' => [
						'Invalid parameter "Time period/From": cannot be empty.',
						'Invalid parameter "Time period/To": cannot be empty.'
//						'Invalid parameter "/1/sparkline/time_period/from": cannot be empty.',
//						'Invalid parameter "/1/sparkline/time_period/to": cannot be empty.'
					]
				]
			],
			// #18 Error when sparkline time period From/To with invalid value.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Sparkline',
							'id:sparkline_time_period_from' => '!',
							'id:sparkline_time_period_to' => '@'
						]
					],
					'column_error' => [
						'Invalid parameter "Time period/From": a time is expected.',
						'Invalid parameter "Time period/To": a time is expected.'
//						'Invalid parameter "/1/sparkline/time_period/from": a time is expected.',
//						'Invalid parameter "/1/sparkline/time_period/to": a time is expected.'
					]
				]
			],
			// #19 Error when sparkline widget field is empty.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Sparkline',
							'id:sparkline_time_period_data_source' => 'Widget'
						]
					],
					'column_error' => [
						'Invalid parameter "Time period/Widget": cannot be empty.'
//						'Invalid parameter "/1/sparkline/time_period/widget": cannot be empty.'
					]
				]
			],
			// #20 Error when invalid colour is picked for sparkline charts.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Display' => 'Sparkline',
							'Item name' => 'Available memory',
							'xpath:.//input[@id="sparkline_color"]/..' => '!@#$%^'
						]
					],
					'column_error' => [
						'Invalid parameter "Colour": a hexadecimal colour code (6 symbols) is expected.'
//						'Invalid parameter "/1/sparkline/sparkline_color": a hexadecimal colour code (6 symbols) is expected.'
					]
				]
			],
			// #21 Error when colour picker is empty.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Display' => 'Sparkline',
							'Item name' => 'Available memory',
							'xpath:.//input[@id="sparkline_color"]/..' => ''
						]
					],
					'column_error' => [
						'Invalid parameter "Colour": cannot be empty.'
//						'Invalid parameter "/1/sparkline/sparkline_color": cannot be empty.'
					]
				]
			],
			// #22 Update all main fields.
			[
				[
					'main_fields' => [
						'Name' => 'Updated main fields',
						'Refresh interval' => '2 minutes',
						'Host groups' => 'Zabbix servers',
						'Hosts' => 'ЗАББИКС Сервер',
						'Show hosts in maintenance' => true,
						'Order' => 'Bottom N',
						'Order by' => 'test update column 2',
						'Host limit' => '2'
					]
				]
			],
			// #23 Update first item column to Text column and add some values.
			[
				[
					'main_fields' => [
						'Name' => 'Updated column type to text',
						'Show hosts in maintenance' => false
					],
					'column_fields' => [
						[
							'Name' => 'Text column changed',
							'Data' => 'Text',
							'Text' => 'some text 😅',
							'Base colour' => '039BE5'
						]
					]
				]
			],
			// #24 Update first column to Host name column and add some values.
			[
				[
					'main_fields' => [
						'Name' => 'Updated column type to host name'
					],
					'column_fields' => [
						[
							'Name' => 'Host name column update',
							'Data' => 'Host name',
							'Base colour' => 'FF8F00'
						]
					]
				]
			],
			// #25 Update first column to Item column and check time From/To.
			[
				[
					'main_fields' => [
						'Name' => 'Time From/To'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Advanced configuration' => true,
							'Aggregation function' => 'min',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-120s',
							'id:time_period_to' => 'now-1m'
						]
					]
				]
			],
			// #26 Update time From/To.
			[
				[
					'main_fields' => [
						'Name' => 'Time From/To'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Advanced configuration' => true,
							'Aggregation function' => 'max',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1w',
							'id:time_period_to' => 'now-1d'
						]
					]
				]
			],
			// #27 Update time From/To (day before yesterday).
			[
				[
					'main_fields' => [
						'Name' => 'Time shift 10h'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Advanced configuration' => true,
							'Aggregation function' => 'count',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-2d/d',
							'id:time_period_to' => 'now-2d/d'
						]
					]
				]
			],
			// #28 Update time From/To.
			[
				[
					'main_fields' => [
						'Name' => 'Time shift 10w'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Advanced configuration' => true,
							'Aggregation function' => 'count',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-3M',
							'id:time_period_to' => 'now-1M'
						]
					]
				]
			],
			// #29 Update to sparkline fields.
			[
				[
					'main_fields' => [
						'Name' => 'Sparkline fields'
					],
					'column_fields' => [
						[
							'Name' => 'Sparkline_0',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Sparkline',
							'id:sparkline_width' => '-1',
							'id:sparkline_fill' => '',
							'id:sparkline_history' => 'Auto'
						],
						[
							'Name' => 'Sparkline_1',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Sparkline',
							'id:sparkline_width' => '0',
							'id:sparkline_fill' => '10',
							'xpath:.//input[@id="sparkline_color"]/..' => '000000',
							'id:sparkline_time_period_from' => 'now-1w',
							'id:sparkline_time_period_to' => 'now-1d'
						],
						[
							'Name' => 'Sparkline_2',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Sparkline',
							'id:sparkline_width' => '10',
							'id:sparkline_fill' => '0',
							'id:sparkline_time_period_data_source' => 'Widget',
							'xpath:.//div[@id="sparkline_time_period_reference"]/..' => 'Graph (classic) for time period '.
								'check via widget',
							'id:sparkline_history' => 'Trends'
						],
						[
							'Name' => 'Sparkline_3',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Sparkline',
							'id:sparkline_width' => '10',
							'id:sparkline_fill' => '10',
							'id:sparkline_time_period_data_source' => 'Dashboard',
							'id:sparkline_history' => 'History'
						]
					],
					'replace' => true
				]
			],
			// #30 Spaces in input fields.
			[
				[
					'trim' => true,
					'main_fields' => [
						'Name' => '            Updated Spaces            ',
						'Host limit' => ' 1 '
					],
					'tags' => [
						['name' => '   tag     ', 'value' => '     value       ', 'operator' => 'Equals']
					],
					'column_fields' => [
						[
							'Name' => '     Text column name with spaces 1     ',
							'Data' => 'Text',
							'Text' => '         Spaces in text         ',
							'Base colour' => 'A5D6A7'
						],
						[
							'Name' => '     🦉Text column name with spaces2      ',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Bar',
							'Min' => '         2       ',
							'Max' => '         200     ',
							'Decimal places' => ' 2',
							'Thresholds' => [
								[
									'action' => USER_ACTION_UPDATE,
									'index' => 0,
									'threshold' => '    5       '
								],
								[
									'action' => USER_ACTION_UPDATE,
									'index' => 1,
									'threshold' => '    10      '
								]
							],
							'Advanced configuration' => true,
							'Aggregation function' => 'avg',
							'Time period' => 'Custom',
							'id:time_period_from' => '  now-2d/d  ',
							'id:time_period_to' => '  now-2d/d  ',
							'History data' => 'Trends'
						],
						[
							'Name' => '     Text column name with spaces 3🦉     ',
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Sparkline',
							'id:sparkline_width' => ' 0',
							'id:sparkline_fill' => ' 5',
							'id:sparkline_time_period_data_source' => 'Custom',
							'id:sparkline_time_period_from' => '         now-2m         ',
							'id:sparkline_time_period_to' => '         now-1m         ',
							'id:sparkline_history' => 'Trends'
						]
					]
				]
			],
			// #31 User macros in input fields.
			[
				[
					'main_fields' => [
						'Name' => '{$UPDATED_USERMACRO}'
					],
					'tags' => [
						['name' => '{$USERMACRO}', 'value' => '{$USERMACRO}', 'operator' => 'Equals']
					],
					'column_fields' => [
						[
							'Name' => '{$USERMACRO1}',
							'Data' => 'Text',
							'Text' => '{$USERMACRO3}'
						],
						[
							'Name' => '{$USERMACRO2}',
							'Data' => 'Item value',
							'Item name' => 'Available memory'
						]
					]
				]
			],
			// #32 Global macros in input fields.
			[
				[
					'main_fields' => [
						'Name' => '{HOST.HOST} updated'
					],
					'tags' => [
						['name' => '{HOST.NAME}', 'value' => '{ITEM.NAME}', 'operator' => 'Equals']
					],
					'column_fields' => [
						[
							'Name' => '{INVENTORY.ALIAS}',
							'Data' => 'Text',
							'Text' => '{HOST.DNS}'
						],
						[
							'Name' => '{HOST.IP}',
							'Data' => 'Item value',
							'Item name' => 'Available memory'
						]
					]
				]
			],
			// #33 Update item column adding new values and fields.
			[
				[
					'main_fields' => [
						'Name' => 'Updated values for item column 😅'
					],
					'column_fields' => [
						[
							'Data' => 'Host name',
							'Name' => 'Only name changed'
						],
						[
							'Data' => 'Item value',
							'Item name' => 'Available memory',
							'Display' => 'Indicators',
							'Min' => '50',
							'Max' => '100',
							'Advanced configuration' => true,
							'Aggregation function' => 'avg',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-3d',
							'id:time_period_to' => 'now-1d',
							'History data' => 'Trends',
							'Base colour' => '039BE5',
							'Thresholds' => [
								[
									'action' => USER_ACTION_UPDATE,
									'index' => 0,
									'threshold' => '1',
									'color' => 'FFEB3B'
								],
								[
									'action' => USER_ACTION_UPDATE,
									'index' => 1,
									'threshold' => '100',
									'color' => 'AAAAAA'
								]
							]
						]
					],
					'tags' => [
						['name' => 'value', 'value' => '12345', 'operator' => 'Contains'],
						['name' => '@#$%@', 'value' => 'a1b2c3d4', 'operator' => 'Equals'],
						['name' => 'AvF%21', 'operator' => 'Exists'],
						['name' => '_', 'operator' => 'Does not exist'],
						['name' => 'кириллица', 'value' => 'BcDa', 'operator' => 'Does not equal'],
						['name' => 'aaa6 😅', 'value' => 'bbb6 😅', 'operator' => 'Does not contain']
					]
				]
			]
		];
	}

	/**
	 * Update Top Hosts widget.
	 *
	 * @dataProvider getUpdateData
	 */
	public function testDashboardTopHostsWidget_Update($data) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			// Hash before update.
			$old_hash = CDBHelper::getHash(self::SQL);
		}

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardids[self::DASHBOARD_UPDATE]);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->getWidget(self::$updated_name)->edit();

		// Update column.
		if (array_key_exists('column_fields', $data)) {
			$this->fillColumnForm($data, 'update');
		}

		if (array_key_exists('tags', $data)) {
			$this->setTags($data['tags']);
		}

		if (array_key_exists('main_fields', $data)) {
			$form->fill($data['main_fields']);
			$form->submit();
			$this->page->waitUntilReady();
		}

		// Trim trailing and leading spaces in expected values before comparison.
		if (CTestArrayHelper::get($data, 'trim', false)) {
			$data = CTestArrayHelper::trim($data);
		}

		// Sparkline 'Width' and 'Fill' values are replaced by 0 when invalid data is passed.
		if (array_key_exists('replace', $data)) {
			$data['column_fields'][0]['id:sparkline_width'] = '0';
			$data['column_fields'][0]['id:sparkline_fill'] = '0';
		}

		// Check error message in main widget form.
		if (array_key_exists('main_error', $data)) {
			$this->assertMessage(TEST_BAD, null, $data['main_error']);
		}

		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			COverlayDialogElement::find()->one()->close();
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Compare old hash and new one.
			$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
		}
		else {
			self::$updated_name = (array_key_exists('Name', $data['main_fields']))
				? $data['main_fields']['Name']
				: self::$updated_name;

			// Make sure that the widget is present before saving the dashboard.
			$header = CTestArrayHelper::get($data['main_fields'], 'Name', self::$updated_name);
			$dashboard->getWidget($header);
			$dashboard->save();

			// Check message that widget added.
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			$this->checkWidget(self::$updated_name, $data, 'update');
		}
	}

	/**
	 * Delete top hosts widget.
	 */
	public function testDashboardTopHostsWidget_Delete() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardids[self::DASHBOARD_DELETE]);
		$dashboard = CDashboardElement::find()->one()->edit();
		$name = 'Top hosts delete';
		$dashboard->deleteWidget($name);
		$this->page->waitUntilReady();
		$dashboard->save();

		// Check that Dashboard has been saved.
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Confirm that widget is not present on dashboard.
		$this->assertFalse($dashboard->getWidget($name, false)->isValid());

		// Check that widget is removed from DB.
		$widget_sql = 'SELECT * FROM widget_field wf LEFT JOIN widget w ON w.widgetid=wf.widgetid WHERE w.name='.zbx_dbstr($name);
		$this->assertEquals(0, CDBHelper::getCount($widget_sql));
	}

	public static function getRemoveData() {
		return [
			// #0 Remove column.
			[
				[
					'table_id' => 'id:list_columns',
					'remove_selector' => 'xpath:(.//button[@name="remove"])[2]'
				]
			],
			// #1 Remove tag.
			[
				[
					'table_id' => 'id:tags_table_tags',
					'remove_selector' => 'id:tags_0_remove'
				]
			],
			// #2 Remove threshold.
			[
				[
					'table_id' => 'id:thresholds_table',
					'remove_selector' => 'id:thresholds_0_remove'
				]
			]
		];
	}

	/**
	 * Remove tag, column, threshold.
	 *
	 * @dataProvider getRemoveData
	 */
	public function testDashboardTopHostsWidget_Remove($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardids[self::DASHBOARD_REMOVE]);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->getWidget('Top hosts for remove')->edit();

		// Find and count tag/column/threshold before remove.
		if ($data['table_id'] !== 'id:thresholds_table') {
			$table = $form->query($data['table_id'])->one()->asTable();
			$amount_before = $table->getRows()->count();
			$table->query($data['remove_selector'])->one()->click();
		}
		else {
			$form->query('xpath:(.//button[@name="edit"])[1]')->one()->waitUntilVisible()->click();
			$column_form = COverlayDialogElement::find()->waitUntilReady()->asForm()->all()->last();
			$table = $column_form->query($data['table_id'])->one()->asTable();
			$amount_before = $table->getRows()->count();
			$table->query($data['remove_selector'])->one()->click();
			$column_form->submit();
		}

		// After remove column and threshold, form is reloaded.
		if ($data['table_id'] !== 'id:tags_table_tags') {
			$form->waitUntilReloaded()->submit();
		}
		else {
			$form->submit();
		}

		$dashboard->save();

		// Check that Dashboard has been saved.
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		$dashboard->edit()->getWidget('Top hosts for remove')->edit();

		if ($data['table_id'] === 'id:thresholds_table') {
			$form->query('xpath:(.//button[@name="edit"])[1]')->one()->waitUntilVisible()->click();
			$column_form = COverlayDialogElement::find()->waitUntilReady()->asForm()->all()->last();
			$table = $column_form->query($data['table_id'])->one()->asTable();
		}

		// Check that tag/column/threshold removed.
		$this->assertEquals($amount_before - 1, $table->getRows()->count());

		COverlayDialogElement::closeAll();
	}

	/**
	 * Check widget after update/creation.
	 *
	 * @param string    $header        widget name
	 * @param array     $data          values from data provider
	 * @param string    $action        check after creation or update
	 */
	protected function checkWidget($header, $data, $action) {
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->getWidget($header)->edit();
		$form->checkValue($data['main_fields']);

		if (array_key_exists('tags', $data)) {
			$this->assertTags($data['tags']);
		}

		if (array_key_exists('column_fields', $data)) {
			// Count column amount from data provider.
			$column_amount = count($data['column_fields']);
			$table = $form->query('id:list_columns')->one()->asTable();

			// It is required to subtract 1 to ignore header row.
			$row_amount = $table->getRows()->count() - 1;

			if ($action === 'create') {
				// Count row amount from column table and compare with column amount from data provider.
				$this->assertEquals($column_amount, $row_amount);
			}

			// Check values from column form.
			$row_number = 1;
			foreach ($data['column_fields'] as $values) {
				// Check that column table has correct names for added columns.
				$table_name = (array_key_exists('Name', $values)) ? $values['Name'] : '';
				$table->getRow($row_number - 1)->getColumnData('Name', $table_name);

				// Check that column table has correct data.
				if ($values['Data'] === 'Item value') {
					$table_name = $values['Item name'];
				}
				elseif ($values['Data'] === 'Host name') {
					$table_name = $values['Data'];
				}
				else {
					$table_name = $values['Text'];
				}
				$table->getRow($row_number - 1)->getColumnData('Data', $table_name);

				$form->query('xpath:(.//button[@name="edit"])['.$row_number.']')->one()->click();

				$column_dialog = COverlayDialogElement::find()->waitUntilReady()->all()->last();
				$this->assertEquals('Update column', $column_dialog->getTitle());

				// Check Thresholds values.
				if (array_key_exists('Thresholds', $values)) {
					foreach($values['Thresholds'] as &$threshold) {
						unset($threshold['action'], $threshold['index']);
					}
					unset($threshold);

					$this->getTreshholdTable()->checkValue($values['Thresholds']);
					unset($values['Thresholds']);
				}

				// Advanced configuration in saved form is always false.
				$values['Advanced configuration'] = false;
				$column_dialog->asForm()->checkValue($values);
				$this->query('xpath:(//button[text()="Cancel"])[2]')->one()->click();

				// Check next row in a column table.
				if ($row_number < $row_amount) {
					$row_number++;
				}
			}
		}

		COverlayDialogElement::find()->one()->close();
	}

	/**
	 * Create or update "Top hosts" widget.
	 *
	 * @param array     $data      values from data provider
	 * @param string    $action    create or update action
	 */
	protected function fillColumnForm($data, $action) {
		// Starting counting column amount from 1 for xpath.
		if ($action === 'update') {
			$column_count = 1;
		}

		$form = $this->query('id:widget-dialogue-form')->one()->asForm();
		foreach ($data['column_fields'] as $column) {
			// Open the Column configuration add or column update dialog depending on the action type.
			$selector = ($action === 'create') ? 'id:add' : 'xpath:(.//button[@name="edit"])['.$column_count.']';
			$form->query($selector)->waitUntilClickable()->one()->click();
			$column_form = COverlayDialogElement::find()->waitUntilReady()->asForm()->all()->last();

			// Fill Thresholds values.
			if (array_key_exists('Thresholds', $column)) {
				$this->getTreshholdTable()->fill($column['Thresholds']);
				unset($column['Thresholds']);
			}

			// Fill Highlights values.
			if (array_key_exists('Highlights', $column)) {
				$column_form->fill(['Display item value as' => 'Text']);
				$this->getHighlightsTable()->fill($column['Highlights']);
				unset($column['Highlights']);
			}

			$column_form->fill($column);
			$column_form->submit();

			// Updating top host several columns, change it count number.
			if ($action === 'update') {
				$column_count++;
			}

			// Check error message in column form.
			if (array_key_exists('column_error', $data)) {
				// Count of days mentioned in error depends on presence of leap year february in selected period.
				if (CTestArrayHelper::get($data, 'days_count')) {
					$data['column_error'] = str_replace('{days}', CDateTimeHelper::countDays('now', 'P2Y'), $data['column_error']);
				}

				$this->assertMessage(TEST_BAD, null, $data['column_error']);
				$selector = ($action === 'update') ? 'Update column' : 'New column';
				$this->query('xpath://div/h4[text()="'.$selector.'"]/../button[@title="Close"]')->one()->click();
			}

			$column_form->waitUntilNotVisible();
			COverlayDialogElement::find()->waitUntilReady()->one();
		}
	}

	public static function getScreenshotsData() {
		return [
			// #0 As is.
			[
				[
					'main_fields' => [
						'Name' => 'Simple'
					],
					'column_fields' => [
						[
							'Name' => 'test column 1',
							'Data' => 'Item value',
							'Item name' => '1_item'
						]
					],
					'screen_name' => 'as_is'
				]
			],
			// #1 Bar.
			[
				[
					'main_fields' => [
						'Name' => 'Bar'
					],
					'column_fields' => [
						[
							'Name' => 'test column 1',
							'Data' => 'Item value',
							'Item name' => '1_item',
							'Display' => 'Bar',
							'Min' => '0',
							'Max' => '2000',
							'Thresholds' => [
								[
									'threshold' => ''
								]
							]
						]
					],
					'screen_name' => 'bar'
				]
			],
			// #2 Bar with threshold.
			[
				[
					'main_fields' => [
						'Name' => 'Bar threshold'
					],
					'column_fields' => [
						[
							'Name' => 'test column 1',
							'Data' => 'Item value',
							'Item name' => '1_item',
							'Display' => 'Bar',
							'Min' => '0',
							'Max' => '2000',
							'Thresholds' => [
								[
									'threshold' => '500'
								]
							]
						]
					],
					'screen_name' => 'bar_thre'
				]
			],
			// #3 Indicators.
			[
				[
					'main_fields' => [
						'Name' => 'Indicators'
					],
					'column_fields' => [
						[
							'Name' => 'test column 1',
							'Data' => 'Item value',
							'Item name' => '1_item',
							'Display' => 'Indicators',
							'Min' => '0',
							'Max' => '2000',
							'Thresholds' => [
								[
									'threshold' => ''
								]
							]
						]
					],
					'screen_name' => 'indi'
				]
			],
			// #4 Indicators with threshold.
			[
				[
					'main_fields' => [
						'Name' => 'Indicators threshold'
					],
					'column_fields' => [
						[
							'Name' => 'test column 1',
							'Data' => 'Item value',
							'Item name' => '1_item',
							'Display' => 'Indicators',
							'Min' => '500',
							'Max' => '2000',
							'Thresholds' => [
								[
									'threshold' => '1500'
								]
							]
						]
					],
					'screen_name' => 'indi_thre'
				]
			],
			// #5 Combined Bar & Indicators.
			[
				[
					'main_fields' => [
						'Name' => 'Bars & Indicators'
					],
					'column_fields' => [
						[
							'Name' => 'column 0',
							'Data' => 'Item value',
							'Item name' => '1_item'
						],
						[
							'Name' => 'column 1',
							'Data' => 'Item value',
							'Item name' => '1_item',
							'Display' => 'Indicators',
							'Min' => '500',
							'Max' => '2000',
							'Thresholds' => [
								[
									'threshold' => '1500'
								]
							]
						],
						[
							'Name' => 'column 2',
							'Data' => 'Item value',
							'Item name' => '1_item',
							'Display' => 'Indicators',
							'Min' => '0',
							'Max' => '2000',
							'Thresholds' => [
								[
									'threshold' => ''
								]
							]
						],
						[
							'Name' => 'column 3',
							'Data' => 'Item value',
							'Item name' => '1_item',
							'Display' => 'Bar',
							'Min' => '0',
							'Max' => '2000',
							'Thresholds' => [
								[
									'threshold' => '500'
								]
							]
						],
						[
							'Name' => 'column 4',
							'Data' => 'Item value',
							'Item name' => '1_item',
							'Display' => 'Bar',
							'Min' => '0',
							'Max' => '2000',
							'Decimal places' => '0',
							'Thresholds' => [
								[
									'threshold' => ''
								]
							]
						]
					],
					'screen_name' => 'bar_and_indi'
				]
			],
			/**
			 * TODO: Sparkline cases should be uncommented after ZBX-25761 fix.
			 * TODO: Screenshots should be replaced after ZBX-25744 fix.
			 */
			// #6 Sparkline with no fluctuations and custom color.
//			[
//				[
//					'main_fields' => [
//						'Name' => 'No fluctuations'
//					],
//					'column_fields' => [
//						[
//							'Name' => 'test column 1',
//							'Data' => 'Item value',
//							'Item name' => 'Item with type of information - numeric (unsigned)',
//							'Display' => 'Sparkline',
//							'xpath:.//input[@id="sparkline_color"]/..' => 'BFFF00',
//							'id:sparkline_time_period_from' => '2024-12-15 12:00:00',
//							'id:sparkline_time_period_to' => '2024-12-15 13:00:00'
//						]
//					],
//					'item_data' => [
//						[
//							'value' => '1',
//							'name' => 'Item with type of information - numeric (unsigned)',
//							'time' => '2024-12-15 13:00:00'
//						],
//						[
//							'value' => '1',
//							'name' => 'Item with type of information - numeric (unsigned)',
//							'time' => '2024-12-15 12:00:00'
//						]
//					],
//					'screen_name' => 'sparkline'
//				]
//			],
			// #7 Sparkline with uptrend and custom color.
//			[
//				[
//					'main_fields' => [
//						'Name' => 'Uptrend'
//					],
//					'column_fields' => [
//						[
//							'Name' => 'test column 1',
//							'Data' => 'Item value',
//							'Item name' => 'Item with type of information - numeric (unsigned)',
//							'Display' => 'Sparkline',
//							'xpath:.//input[@id="sparkline_color"]/..' => 'B2EBF2',
//							'id:sparkline_time_period_from' => '2024-12-15 12:00:00',
//							'id:sparkline_time_period_to' => '2024-12-15 13:00:00'
//						]
//					],
//					'item_data' => [
//						[
//							'value' => '1',
//							'name' => 'Item with type of information - numeric (unsigned)',
//							'time' => '2024-12-15 13:00:00'
//						],
//						[
//							'value' => '0',
//							'name' => 'Item with type of information - numeric (unsigned)',
//							'time' => '2024-12-15 12:00:00'
//						]
//					],
//					'screen_name' => 'sparkline_up'
//				]
//			],
			// #8 Sparkline with downtrend and custom color.
//			[
//				[
//					'main_fields' => [
//						'Name' => 'Downtrend'
//					],
//					'column_fields' => [
//						[
//							'Name' => 'test column 1',
//							'Data' => 'Item value',
//							'Item name' => 'Item with type of information - numeric (unsigned)',
//							'Display' => 'Sparkline',
//							'xpath:.//input[@id="sparkline_color"]/..' => 'EF5350',
//							'id:sparkline_time_period_from' => '2024-12-15 12:00:00',
//							'id:sparkline_time_period_to' => '2024-12-15 13:00:00'
//						]
//					],
//					'item_data' => [
//						[
//							'value' => '0',
//							'name' => 'Item with type of information - numeric (unsigned)',
//							'time' => '2024-12-15 13:00:00'
//						],
//						[
//							'value' => '1',
//							'name' => 'Item with type of information - numeric (unsigned)',
//							'time' => '2024-12-15 12:00:00'
//						]
//					],
//					'screen_name' => 'sparkline_down'
//				]
//			],
			// #9 'Fill' and 'Width' fields are equal 0.
//			[
//				[
//					'main_fields' => [
//						'Name' => 'Invisible sparkline'
//					],
//					'column_fields' => [
//						[
//							'Name' => 'test column 1',
//							'Data' => 'Item value',
//							'Item name' => 'Item with type of information - numeric (unsigned)',
//							'Display' => 'Sparkline',
//							'id:sparkline_width' => '0',
//							'id:sparkline_fill' => '0',
//							'id:sparkline_time_period_from' => '2024-12-15 12:00:00',
//							'id:sparkline_time_period_to' => '2024-12-15 13:00:00'
//						]
//					],
//					'item_data' => [
//						[
//							'value' => '0',
//							'name' => 'Item with type of information - numeric (unsigned)',
//							'time' => '2024-12-15 13:00:00'
//						],
//						[
//							'value' => '1',
//							'name' => 'Item with type of information - numeric (unsigned)',
//							'time' => '2024-12-15 12:00:00'
//						]
//					],
//					'screen_name' => 'sparkline_transparent'
//				]
//			],
			// #10 Sparkline with fluctuation and default color.
//			[
//				[
//					'main_fields' => [
//						'Name' => 'With fluctuation'
//					],
//					'column_fields' => [
//						[
//							'Name' => 'test column 1',
//							'Data' => 'Item value',
//							'Item name' => 'Item with type of information - numeric (unsigned)',
//							'Display' => 'Sparkline',
//							'id:sparkline_time_period_from' => '2024-12-15 12:00:00',
//							'id:sparkline_time_period_to' => '2024-12-15 13:00:00'
//						]
//					],
//					'item_data' => [
//						[
//							'value' => '1',
//							'name' => 'Item with type of information - numeric (unsigned)',
//							'time' => '2024-12-15 13:00:00'
//						],
//						[
//							'value' => '5',
//							'name' => 'Item with type of information - numeric (unsigned)',
//							'time' => '2024-12-15 12:30:00'
//						],
//						[
//							'value' => '1',
//							'name' => 'Item with type of information - numeric (unsigned)',
//							'time' => '2024-12-15 12:00:00'
//						]
//					],
//					'screen_name' => 'sparkline_fluctuation'
//				]
//			],
			// #11 Sparkline with fluctuations and custom color.
//			[
//				[
//					'main_fields' => [
//						'Name' => 'With fluctuations'
//					],
//					'column_fields' => [
//						[
//							'Name' => 'test column 1',
//							'Data' => 'Item value',
//							'Item name' => 'Item with type of information - numeric (unsigned)',
//							'Display' => 'Sparkline',
//							'xpath:.//input[@id="sparkline_color"]/..' => 'FFBF00',
//							'id:sparkline_time_period_from' => '2024-12-15 12:00:00',
//							'id:sparkline_time_period_to' => '2024-12-15 13:00:00'
//						]
//					],
//					'item_data' => [
//						[
//							'value' => '10',
//							'name' => 'Item with type of information - numeric (unsigned)',
//							'time' => '2024-12-15 13:00:00'
//						],
//						[
//							'value' => '15',
//							'name' => 'Item with type of information - numeric (unsigned)',
//							'time' => '2024-12-15 12:50:00'
//						],
//						[
//							'value' => '2',
//							'name' => 'Item with type of information - numeric (unsigned)',
//							'time' => '2024-12-15 12:40:00'
//						],
//						[
//							'value' => '7',
//							'name' => 'Item with type of information - numeric (unsigned)',
//							'time' => '2024-12-15 12:30:00'
//						],
//						[
//							'value' => '1',
//							'name' => 'Item with type of information - numeric (unsigned)',
//							'time' => '2024-12-15 12:20:00'
//						],
//						[
//							'value' => '5',
//							'name' => 'Item with type of information - numeric (unsigned)',
//							'time' => '2024-12-15 12:10:00'
//						],
//						[
//							'value' => '0',
//							'name' => 'Item with type of information - numeric (unsigned)',
//							'time' => '2024-12-15 12:00:00'
//						]
//					],
//					'screen_name' => 'sparkline_fluctuation2'
//				]
//			],
			// #12 Two sparkline columns.
//			[
//				[
//					'main_fields' => [
//						'Name' => 'Two sparkline columns'
//					],
//					'column_fields' => [
//						[
//							'Name' => 'test column 1',
//							'Data' => 'Item value',
//							'Item name' => 'Item with type of information - numeric (unsigned)',
//							'Display' => 'Sparkline',
//							'xpath:.//input[@id="sparkline_color"]/..' => 'BFFF00',
//							'id:sparkline_time_period_from' => '2024-12-15 12:00:00',
//							'id:sparkline_time_period_to' => '2024-12-15 13:00:00'
//						],
//						[
//							'Name' => 'test column 2',
//							'Data' => 'Item value',
//							'Item name' => 'Item with type of information - numeric (float)',
//							'Display' => 'Sparkline',
//							'xpath:.//input[@id="sparkline_color"]/..' => 'FFBF00'
//						]
//					],
//					'item_data' => [
//						[
//							'value' => '1',
//							'name' => 'Item with type of information - numeric (unsigned)',
//							'time' => '2024-12-15 13:00:00'
//						],
//						[
//							'value' => '1',
//							'name' => 'Item with type of information - numeric (unsigned)',
//							'time' => '2024-12-15 12:00:00'
//						],
//						[
//							'value' => '1.11',
//							'name' => 'Item with type of information - numeric (float)',
//							'time' => '2024-12-15 13:00:00'
//						],
//						[
//							'value' => '5.55',
//							'name' => 'Item with type of information - numeric (float)',
//							'time' => '2024-12-15 12:30:00'
//						],
//						[
//							'value' => '2.22',
//							'name' => 'Item with type of information - numeric (float)',
//							'time' => '2024-12-15 12:00:00'
//						]
//					],
//					'screen_name' => 'sparkline_columns'
//				]
//			]
		];
	}

	/**
	 * Check widget bars, indicators and sparkline with screenshots.
	 *
	 * @backup !history, !history_log, !history_str, !history_text, !history_uint
	 * @dataProvider getScreenshotsData
	 */
	public function testDashboardTopHostsWidget_WidgetAppearance($data) {
		if (array_key_exists('item_data', $data)) {
			foreach ($data['item_data'] as $params) {
				CDataHelper::addItemData(self::$aggregation_itemids[$params['name']], $params['value'], strtotime($params['time']));
			}
		}

		$this->createTopHostsWidget($data, self::$dashboardids[self::DASHBOARD_SCREENSHOTS]);

		// Check widget added and assert screenshots.
		$element = CDashboardElement::find()->one()->getWidget($data['main_fields']['Name']);
		$this->assertScreenshot($element, $data['screen_name']);
	}

	public static function getCheckTextItemsData() {
		return [
			// #0 Text item - value displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Text value item'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item name' => 'top_hosts_trap_text'
						]
					],
					'text' => "column1\nText for text item"
				]
			],
			// #1 Text item, history data Trends - value displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Text trends history'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item name' => 'top_hosts_trap_text',
							'Display item value as' => 'Numeric',
							'Advanced configuration' => true,
							'History data' => 'Trends'
						]
					],
					'text' => "column1\nText for text item"
				]
			],
			// #2 Text item, display Bar - value not displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Text display bar'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item name' => 'top_hosts_trap_text',
							'Display item value as' => 'Numeric',
							'Display' => 'Bar'
						]
					],
					'text' => "column1\nNo data found"
				]
			],
			// #3 Text item, display Indicators - value not displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Text display indicators'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item name' => 'top_hosts_trap_text',
							'Display item value as' => 'Numeric',
							'Display' => 'Indicators'
						]
					],
					'text' => "column1\nNo data found"
				]
			],
			// #4 Text item, display sparkline - value not displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Text display sparkline'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item name' => 'top_hosts_trap_text',
							'Display item value as' => 'Numeric',
							'Display' => 'Sparkline'
						]
					],
					'text' => "column1\nNo data found"
				]
			],
			// #5 Text item, Aggregation function max - value not displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Text aggregation function'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item name' => 'top_hosts_trap_text',
							'Display item value as' => 'Numeric',
							'Advanced configuration' => true,
							'Aggregation function' => 'max'
						]
					],
					'text' => "column1\nNo data found"
				]
			],
			// #6 Text item, Threshold - value is displayed ignoring thresholds.
			[
				[
					'main_fields' => [
						'Name' => 'Text threshold'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item name' => 'top_hosts_trap_text',
							'Display item value as' => 'Numeric',
							'Thresholds' => [
								[
									'threshold' => '10'
								]
							]
						]
					],
					'text' => "column1\nText for text item"
				]
			],
			// #7 Log item - value displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Log value item'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item name' => 'top_hosts_trap_log'
						]
					],
					'text' => "column1\nLogs for text item"
				]
			],
			// #8 Log item, history data Trends - value displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Log trends history'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item name' => 'top_hosts_trap_log',
							'Display item value as' => 'Numeric',
							'Advanced configuration' => true,
							'History data' => 'Trends'
						]
					],
					'text' => "column1\nLogs for text item"
				]
			],
			// #9 Log item, display Bar - value not displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Log display bar'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item name' => 'top_hosts_trap_log',
							'Display item value as' => 'Numeric',
							'Display' => 'Bar'
						]
					],
					'text' => "column1\nNo data found"
				]
			],
			// #10 Log item, display Indicators - value not displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Log display indicators'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item name' => 'top_hosts_trap_log',
							'Display item value as' => 'Numeric',
							'Display' => 'Indicators'
						]
					],
					'text' => "column1\nNo data found"
				]
			],
			// #11 Log item, display sparkline - value not displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Log display sparkline'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item name' => 'top_hosts_trap_log',
							'Display item value as' => 'Numeric',
							'Display' => 'Sparkline'
						]
					],
					'text' => "column1\nNo data found"
				]
			],
			// #12 Log item, Aggregation function max - value not displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Log aggregation function'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item name' => 'top_hosts_trap_log',
							'Display item value as' => 'Numeric',
							'Advanced configuration' => true,
							'Aggregation function' => 'max'
						]
					],
					'text' => "column1\nNo data found"
				]
			],
			// #13 Log item, Threshold - value is displayed ignoring thresholds.
			[
				[
					'main_fields' => [
						'Name' => 'Log threshold'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item name' => 'top_hosts_trap_log',
							'Display item value as' => 'Numeric',
							'Thresholds' => [
								[
									'threshold' => '10'
								]
							]
						]
					],
					'text' => "column1\nLogs for text item"
				]
			],
			// #14 Char item - value displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Char value item'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item name' => 'top_hosts_trap_char'
						]
					],
					'text' => "column1\ncharacters_here"
				]
			],
			// #15 Char item, history data Trends - value displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Char trends history'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item name' => 'top_hosts_trap_char',
							'Display item value as' => 'Numeric',
							'Advanced configuration' => true,
							'History data' => 'Trends'
						]
					],
					'text' => "column1\ncharacters_here"
				]
			],
			// #16 Char item, display Bar - value not displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Char display bar'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item name' => 'top_hosts_trap_char',
							'Display item value as' => 'Numeric',
							'Display' => 'Bar'
						]
					],
					'text' => "column1\nNo data found"
				]
			],
			// #17 Char item, display Indicators - value not displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Char display indicators'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item name' => 'top_hosts_trap_char',
							'Display item value as' => 'Numeric',
							'Display' => 'Indicators'
						]
					],
					'text' => "column1\nNo data found"
				]
			],
			// #18 Char item, display sparkline - value not displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Char display sparkline'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item name' => 'top_hosts_trap_char',
							'Display item value as' => 'Numeric',
							'Display' => 'Sparkline'
						]
					],
					'text' => "column1\nNo data found"
				]
			],
			// #19 Char item, Aggregation function max - value not displayed.
			[
				[
					'main_fields' => [
						'Name' => 'Char aggregation function'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item name' => 'top_hosts_trap_char',
							'Advanced configuration' => true,
							'Display item value as' => 'Numeric',
							'Aggregation function' => 'max'
						]
					],
					'text' => "column1\nNo data found"
				]
			],
			// #20 Char item, Threshold - value is displayed ignoring thresholds.
			[
				[
					'main_fields' => [
						'Name' => 'Char threshold'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item name' => 'top_hosts_trap_char',
							'Thresholds' => [
								[
									'threshold' => '10'
								]
							]
						]
					],
					'text' => "column1\ncharacters_here"
				]
			]
		];
	}

	/**
	 * Changing column parameters, check that text value is visible/non-visible.
	 *
	 * @dataProvider getCheckTextItemsData
	 */
	public function testDashboardTopHostsWidget_CheckTextItems($data) {
		$this->createTopHostsWidget($data, self::$dashboardids[self::DASHBOARD_TEXT_ITEMS]);

		// Check if value displayed in column table.
		$this->assertEquals($data['text'], CDashboardElement::find()->one()->getWidget($data['main_fields']['Name'])
				->getContent()->getText()
		);
	}

	public static function getWidgetTimePeriodData() {
		return [
			// Widget with default configuration.
			[
				[
					'widgets' => [
						[
							'widget_type' => 'Top hosts',
							'main_fields' => [
								'Name' => 'Default widget'
							],
							'column_fields' => [
								[
									'Name' => 'Column default',
									'Item name' => 'Available memory'
								]
							]
						]
					]
				]
			],
			// Widget with "Custom" time period configuration.
			[
				[
					'widgets' => [
						[
							'widget_type' => 'Top hosts',
							'main_fields' => [
								'Name' => 'Item widget with "Custom" time period'
							],
							'column_fields' => [
								[
									'Name' => 'Column with "Custom" time period',
									'Item name' => 'Available memory',
									'Advanced configuration' => true,
									'Aggregation function' => 'min',
									'Time period' => 'Custom'
								]
							]
						]
					]
				]
			],
			// Two widgets with "Widget" and "Custom" time period configuration.
			[
				[
					'widgets' => [
						[
							'widget_type' => 'Graph (classic)',
							'main_fields' => [
								'Name' => 'Graph widget with "Custom" time period',
								'Graph' => 'Linux: System load',
								'Time period' => 'Custom',
								'id:time_period_from' => 'now-5400',
								'id:time_period_to' => 'now-1800'
							]
						],
						[
							'widget_type' => 'Top hosts',
							'main_fields' => [
								'Name' => 'Item widget with "Widget" time period'
							],
							'column_fields' => [
								[
									'Name' => 'Column with "Widget" time period',
									'Item name' => 'Available memory',
									'Advanced configuration' => true,
									'Aggregation function' => 'max',
									'Time period' => 'Widget',
									'Widget' => 'Graph widget with "Custom" time period'
								]
							]
						]
					]
				]
			],
			[
				[
					'widgets' => [
						[
							'widget_type' => 'Graph (classic)',
							'main_fields' => [
								'Name' => 'Graph widget with "Custom" time period',
								'Graph' => 'Linux: System load',
								'Time period' => 'Custom',
								'id:time_period_from' => 'now-5400',
								'id:time_period_to' => 'now-1800'
							]
						],
						[
							'widget_type' => 'Top hosts',
							'main_fields' => [
								'Name' => 'Item widget with "Widget" time period'
							],
							'column_fields' => [
								[
									'Name' => 'Column default',
									'Item name' => 'Available memory'
								],
								[
									'Name' => 'Column with "Widget" time period',
									'Item name' => 'Available memory',
									'Advanced configuration' => true,
									'Aggregation function' => 'avg',
									'Time period' => 'Widget',
									'Widget' => 'Graph widget with "Custom" time period'
								],
								[
									'Name' => 'Column with "Custom" time period',
									'Item name' => 'Available memory',
									'Advanced configuration' => true,
									'Aggregation function' => 'count',
									'Time period' => 'Custom'
								]
							]
						]
					]
				]
			],
			// Top hosts widget with time period "Dashboard" (enabled zoom filter).
			[
				[
					'widgets' => [
						[
							'widget_type' => 'Top hosts',
							'main_fields' => [
								'Name' => 'Top hosts widget with "Dashboard" time period'
							],
							'column_fields' => [
								[
									'Name' => 'Column with "Dashboard" time period',
									'Item name' => 'Available memory in %',
									'Advanced configuration' => true,
									'Aggregation function' => 'sum',
									'Time period' => 'Dashboard'
								]
							]
						]
					],
					'zoom_filter' => true,
					'filter_layout' => true
				]
			],
			// Two widgets with time period "Dashboard" and "Custom" time period configuration.
			[
				[
					'widgets' => [
						[
							'widget_type' => 'Top hosts',
							'main_fields' => [
								'Name' => 'Top hosts widget with "Custom" time period'
							],
							'column_fields' => [
								[
									'Name' => 'Column with "Custom" time period',
									'Item name' => 'Available memory in %',
									'Advanced configuration' => true,
									'Aggregation function' => 'first',
									'Time period' => 'Custom',
									'id:time_period_from' => 'now-2y',
									'id:time_period_to' => 'now-1y'
								]
							]
						],
						[
							'widget_type' => 'Action log',
							'main_fields' => [
								'Name' => 'Action log widget with Dashboard time period' // time period default state.
							]
						]
					],
					'zoom_filter' => true
				]
			],
			[
				[
					'widgets' => [
						[
							'widget_type' => 'Graph (classic)',
							'main_fields' => [
								'Name' => 'Graph widget with "Custom" time period',
								'Graph' => 'Linux: System load',
								'Time period' => 'Custom',
								'id:time_period_from' => 'now-5400',
								'id:time_period_to' => 'now-1800'
							]
						],
						[
							'widget_type' => 'Top hosts',
							'main_fields' => [
								'Name' => 'Top hosts widget with "Custom" time period'
							],
							'column_fields' => [
								[
									'Name' => 'Column with "Custom" time period',
									'Item name' => 'Available memory in %',
									'Advanced configuration' => true,
									'Aggregation function' => 'first',
									'Time period' => 'Custom',
									'id:time_period_from' => 'now-2y',
									'id:time_period_to' => 'now-1y'
								],
								[
									'Name' => 'Column with "Dashboard" time period',
									'Item name' => 'Available memory in %',
									'Advanced configuration' => true,
									'Aggregation function' => 'last',
									'Time period' => 'Dashboard'
								],
								[
									'Name' => 'Column default',
									'Item name' => 'Available memory'
								],
								[
									'Name' => 'Column with "Widget" time period',
									'Item name' => 'Available memory',
									'Advanced configuration' => true,
									'Aggregation function' => 'min',
									'Time period' => 'Widget',
									'Widget' => 'Graph widget with "Custom" time period'
								]
							]
						]
					],
					'zoom_filter' => true
				]
			]
		];
	}

	/**
	 * Check that dashboard time period filter appears regarding widget configuration.
	 *
	 * @dataProvider getWidgetTimePeriodData
	 */
	public function testDashboardTopHostsWidget_TimePeriodFilter($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$other_dashboardids[self::DASHBOARD_ZOOM]);
		$dashboard = CDashboardElement::find()->one();

		foreach ($data['widgets'] as $widget) {
			$form = $dashboard->edit()->addWidget()->asForm();
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL($widget['widget_type'])]);

			// Add new column.
			if (array_key_exists('column_fields', $widget)) {
				$this->fillColumnForm($widget, 'create');
			}

			$form->fill($widget['main_fields']);
			$form->submit();
			COverlayDialogElement::ensureNotPresent();
			$this->page->waitUntilReady();
			$dashboard->save();
		}

		$dashboard->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		if (array_key_exists('zoom_filter', $data)) {
			// Check that zoom filter tab link is valid.
			$this->assertTrue($this->query('xpath:.//a[@href="#tab_1"]')->one()->isValid());

			// Check zoom filter layout.
			if (array_key_exists('filter_layout', $data)) {
				$filter = CFilterElement::find()->one();
				$this->assertEquals('Last 1 hour', $filter->getSelectedTabName());
				$this->assertEquals('Last 1 hour', $filter->query('link:Last 1 hour')->one()->getText());

				// Check time selector fields layout.
				foreach (['id:from' => 'now-1h', 'id:to' => 'now'] as $selector => $value) {
					$input = $this->query($selector)->one();
					$this->assertEquals($value, $input->getValue());
					$this->assertEquals(255, $input->getAttribute('maxlength'));
				}

				$buttons = [
					'xpath://button[contains(@class, "btn-time-left")]' => true,
					'xpath://button[contains(@class, "btn-time-right")]' => false,
					'button:Zoom out' => true,
					'button:Apply' => true,
					'id:from_calendar' => true,
					'id:to_calendar' => true
				];
				foreach ($buttons as $selector => $enabled) {
					$this->assertTrue($this->query($selector)->one()->isEnabled($enabled));
				}

				$this->assertEquals(1, $this->query('button:Apply')->all()->filter(CElementFilter::CLICKABLE)->count());
				$this->assertTrue($filter->isExpanded());
			}
		}
		else {
			$this->assertFalse($this->query('xpath:.//a[@href="#tab_1"]')->one(false)->isValid());
		}

		// Clear particular dashboard for next test case.
		DBexecute('DELETE FROM widget'.
				' WHERE dashboard_pageid'.
				' IN (SELECT dashboard_pageid'.
					' FROM dashboard_page'.
					' WHERE dashboardid='.self::$other_dashboardids[self::DASHBOARD_ZOOM].
				')'
		);
	}

	public static function getThresholdData() {
		return [
			// Numeric (unsigned) item without data.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Threshold and numeric item but without data',
							'Item name' => 'Item with type of information - numeric (unsigned)',
							'Thresholds' => [
								['color' => 'AABBCC', 'threshold' => '1'],
								['color' => 'CCBBAA', 'threshold' => '2']
							]
						]
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Numeric (float) item without data.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Two thresholds and numeric item without data',
							'Item name' => 'Item with type of information - numeric (float)',
							'Thresholds' => [
								['color' => 'AABBCC', 'threshold' => '0'],
								['color' => 'CCDDAA', 'threshold' => '1.01']
							]
						]
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Log) item without data and with aggregation function min.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (log) item without data and with aggregation function min',
							'Item name' => 'Item with type of information - Log',
							'Display item value as' => 'Numeric',
							'Advanced configuration' => true,
							'Aggregation function' => 'min',
							'Thresholds' => [
								['color' => '7E57C2', 'threshold' => '0']
							]
						]
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Character) item without data and with aggregation function max.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (Character) item without data and with aggregation function max',
							'Item name' => 'Item with type of information - Character',
							'Display item value as' => 'Numeric',
							'Advanced configuration' => true,
							'Aggregation function' => 'max',
							'Thresholds' => [
								['color' => '7E57C2', 'threshold' => '1']
							]
						]
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Text) item without data and with aggregation function avg.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (Text) item without data and with aggregation function avg',
							'Item name' => 'Item with type of information - Text',
							'Display item value as' => 'Numeric',
							'Advanced configuration' => true,
							'Aggregation function' => 'avg',
							'Thresholds' => [
								['color' => '7E57C2', 'threshold' => '-1']
							]
						]
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Log) item without data and with aggregation function sum.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (log) item without data and with aggregation function sum',
							'Item name' => 'Item with type of information - Log',
							'Display item value as' => 'Numeric',
							'Advanced configuration' => true,
							'Aggregation function' => 'sum',
							'Thresholds' => [
								['color' => '7E57C2', 'threshold' => '0']
							]
						]
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Character) item without data and with aggregation function first.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (Character) item without data and with aggregation function first',
							'Item name' => 'Item with type of information - Character',
							'Advanced configuration' => true,
							'Aggregation function' => 'first',
							'Highlights' => [
								['color' => '7E57C2', 'regexp' => '-1']
							]
						]
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Text) item without data and with aggregation function last.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (Text) item without data and with aggregation function last',
							'Item name' => 'Item with type of information - Text',
							'Advanced configuration' => true,
							'Aggregation function' => 'last',
							'Highlights' => [
								['color' => '7E57C2', 'regexp' => '0.00']
							]
						]
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Log) item without data and with aggregation function not used.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (log) item without data and with aggregation function not used',
							'Item name' => 'Item with type of information - Log',
							'Highlights' => [
								['color' => '7E57C2', 'regexp' => '0']
							]
						]
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Log) item without data but with aggregation function count (return 0).
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (Log) item without data but with aggregation function count',
							'Item name' => 'Item with type of information - Character',
							'Advanced configuration' => true,
							'Aggregation function' => 'count',
							'Highlights' => [
								['color' => '7E57C2', 'regexp' => '1']
							]
						]
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Character) item without data but with aggregation function count (return 0).
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (Character) item without data but with aggregation function count',
							'Item name' => 'Item with type of information - Character',
							'Advanced configuration' => true,
							'Aggregation function' => 'count',
							'Highlights' => [
								['color' => '7E57C2', 'regexp' => '1']
							]
						]
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Text) item without data but with aggregation function count (return 0).
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (Text) item without data but with aggregation function count',
							'Item name' => 'Item with type of information - Text',
							'Advanced configuration' => true,
							'Aggregation function' => 'count',
							'Highlights' => [
								['color' => '7E57C2', 'regexp' => '1']
							]
						]
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Log) item without data but with aggregation function count (return 0) and threshold equals 0.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (log) item without data but with aggregation function count and threshold that match 0',
							'Item name' => 'Item with type of information - Log',
							'Advanced configuration' => true,
							'Aggregation function' => 'count',
							'Highlights' => [
								['color' => '7E57C2', 'regexp' => '0']
							]
						]
					]
				]
			],
			// Non-numeric (Character) item without data but with aggregation function count (return 0) and threshold equals 0.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (Character) item without data but with aggregation function count and threshold that match 0',
							'Item name' => 'Item with type of information - Character',
							'Advanced configuration' => true,
							'Aggregation function' => 'count',
							'Highlights' => [
								['color' => '7E57C2', 'regexp' => '0']
							]
						]
					]
				]
			],
			// Non-numeric (Text) item without data but with aggregation function count (return 0) and threshold equals 0.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Non-numeric (Text) item without data but with aggregation function count and threshold that match 0',
							'Item name' => 'Item with type of information - Text',
							'Advanced configuration' => true,
							'Aggregation function' => 'count',
							'Highlights' => [
								['color' => '7E57C2', 'regexp' => '0']
							]
						]
					]
				]
			],
			// Numeric (unsigned) item with data and aggregation function not used.
			[
				[
					'numeric' => true,
					'column_fields' => [
						[
							'Name' => 'Thresholds and numeric (unsigned) item',
							'Item name' => 'Item with type of information - numeric (unsigned)',
							'Thresholds' => [
								['color' => 'AABBCC', 'threshold' => '1'],
								['color' => 'CCDDAA', 'threshold' => '2']
							]
						]
					],
					'value' => '1'
				]
			],
			// Numeric (float) item with data and aggregation function not used.
			[
				[
					'numeric' => true,
					'column_fields' => [
						[
							'Name' => 'Thresholds and numeric (float) item',
							'Item name' => 'Item with type of information - numeric (float)',
							'Thresholds' => [
								['color' => 'AABBCC', 'threshold' => '1.01'],
								['color' => 'CCDDAA', 'threshold' => '2.01']
							]
						]
					],
					'value' => '1.02'
				]
			],
			// Numeric (unsigned) item with data and aggregation function count.
			[
				[
					'numeric' => true,
					'column_fields' => [
						[
							'Name' => 'Numeric (unsigned) item with thresholds and aggregation function count',
							'Item name' => 'Item with type of information - numeric (unsigned)',
							'Advanced configuration' => true,
							'Aggregation function' => 'count',
							'Thresholds' => [
								['color' => 'AABBCC', 'threshold' => '1'],
								['color' => 'CCDDAA', 'threshold' => '2']
							]
						]
					],
					'value' => '1'
				]
			],
			// Numeric (float) item with data and aggregation function count.
			[
				[
					'numeric' => true,
					'column_fields' => [
						[
							'Name' => 'Numeric (float) item with thresholds and aggregation function count',
							'Item name' => 'Item with type of information - numeric (float)',
							'Advanced configuration' => true,
							'Aggregation function' => 'count',
							'Thresholds' => [
								['color' => 'AABBCC', 'threshold' => '0.99'],
								['color' => 'CCDDAA', 'threshold' => '1.99']
							]
						]
					],
					'value' => '1.02'
				]
			],
			// Non-numeric (Text) item with data and aggregation function count.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Thresholds and non-nmeric (Text) item',
							'Item name' => 'Item with type of information - Text',
							'Advanced configuration' => true,
							'Aggregation function' => 'count',
							'Highlights' => [
								['color' => 'DDAAFF', 'regexp' => '1'],
								['color' => 'FFDDAA', 'regexp' => '2']
							]
						]
					],
					'value' => 'test'
				]
			],
			// Non-numeric (Log) item with data and aggregation function count.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Thresholds and non-nmeric (Log) item',
							'Item name' => 'Item with type of information - Log',
							'Advanced configuration' => true,
							'Aggregation function' => 'count',
							'Highlights' => [
								['color' => 'DDAAFF', 'regexp' => '1'],
								['color' => 'FFDDAA', 'regexp' => '2']
							]
						]
					],
					'value' => 'test'
				]
			],
			// Non-numeric (Character) item with data and aggregation function count.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Thresholds and non-nmeric (Character) item',
							'Item name' => 'Item with type of information - Character',
							'Advanced configuration' => true,
							'Aggregation function' => 'count',
							'Highlights' => [
								['color' => 'DDAAFF', 'regexp' => '1'],
								['color' => 'FFDDAA', 'regexp' => '2']
							]
						]
					],
					'value' => 'test'
				]
			],
			// Numeric (unsigned) item with data and aggregation function min.
			[
				[
					'numeric' => true,
					'column_fields' => [
						[
							'Name' => 'Numeric (unsigned) item with threshold and aggregation function min',
							'Item name' => 'Item with type of information - numeric (unsigned)',
							'Advanced configuration' => true,
							'Aggregation function' => 'min',
							'Thresholds' => [
								['color' => 'AABBCC', 'threshold' => '1'],
								['color' => 'CCDDAA', 'threshold' => '2']
							]
						]
					],
					'expected_color' => 'AABBCC',
					'value' => '1'
				]
			],
			// Numeric (float) item with data and aggregation function max.
			[
				[
					'numeric' => true,
					'column_fields' => [
						[
							'Name' => 'Numeric (float) item with threshold and aggregation function max',
							'Item name' => 'Item with type of information - numeric (float)',
							'Advanced configuration' => true,
							'Aggregation function' => 'max',
							'Thresholds' => [
								['color' => '7CB342', 'threshold' => '0.00'],
								['color' => 'FFF9C4', 'threshold' => '1.01']
							]
						]
					],
					'expected_color' => 'FFF9C4',
					'value' => '1.01'
				]
			],
			// Numeric (unsigned) item with data and aggregation function avg.
			[
				[
					'numeric' => true,
					'column_fields' => [
						[
							'Name' => 'Numeric (unsigned) item with threshold and aggregation function avg',
							'Item name' => 'Item with type of information - numeric (unsigned)',
							'Advanced configuration' => true,
							'Aggregation function' => 'avg',
							'Thresholds' => [
								['color' => '7CB342', 'threshold' => '1'],
								['color' => 'FFF9C4', 'threshold' => '2']
							]
						]
					],
					'expected_color' => '7CB342',
					'value' => '1'
				]
			],
			// Numeric (float) item with data and aggregation function sum.
			[
				[
					'numeric' => true,
					'column_fields' => [
						[
							'Name' => 'Numeric (float) item with threshold and aggregation function sum',
							'Item name' => 'Item with type of information - numeric (float)',
							'Advanced configuration' => true,
							'Aggregation function' => 'sum',
							'Thresholds' => [
								['color' => 'D32F2F', 'threshold' => '1.11'],
								['color' => '8BC34A', 'threshold' => '2.22']
							]
						]
					],
					'expected_color' => '8BC34A',
					'value' => '2.22'
				]
			],
			// Numeric (unsigned) item with data and aggregation function first.
			[
				[
					'numeric' => true,
					'column_fields' => [
						[
							'Name' => 'Numeric (unsigned) item with threshold and aggregation function first',
							'Item name' => 'Item with type of information - numeric (unsigned)',
							'Advanced configuration' => true,
							'Aggregation function' => 'first',
							'Thresholds' => [
								['color' => 'D32F2F', 'threshold' => '0'],
								['color' => '8BC34A', 'threshold' => '1']
							]
						]
					],
					'expected_color' => 'D32F2F',
					'value' => '0'
				]
			],
			// Numeric (float) item with data and aggregation function last.
			[
				[
					'numeric' => true,
					'column_fields' => [
						[
							'Name' => 'Numeric (float) item with threshold and aggregation function last',
							'Item name' => 'Item with type of information - numeric (float)',
							'Advanced configuration' => true,
							'Aggregation function' => 'last',
							'Thresholds' => [
								['color' => 'D32F2F', 'threshold' => '-1.00'],
								['color' => '8BC34A', 'threshold' => '0.00']
							]
						]
					],
					'expected_color' => '8BC34A',
					'value' => '0'
				]
			],
			// Non-numeric (Log) item with data and aggregation function last.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Thresholds and non-nmeric (Log) item with aggregation function last',
							'Item name' => 'Item with type of information - Log',
							'Advanced configuration' => true,
							'Aggregation function' => 'last', //'min' is not available for text elements, changed to 'last'
							'Highlights' => [
								['color' => 'DDAAFF', 'regexp' => '0']
							]
						]
					],
					'value' => 'test',
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Character) item with data and aggregation function first.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Thresholds and non-nmeric (Character) item with aggregation function first',
							'Item name' => 'Item with type of information - Character',
							'Advanced configuration' => true,
							'Aggregation function' => 'first', //'max' is not available for text elements, changed to 'first'
							'Highlights' => [
								['color' => 'D32F2F', 'regexp' => '-1'],
								['color' => '8BC34A', 'regexp' => '0']
							]
						]
					],
					'value' => 'test',
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Text) item with data and aggregation function last.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Thresholds and non-nmeric (Text) item with aggregation function last',
							'Item name' => 'Item with type of information - Text',
							'Advanced configuration' => true,
							'Aggregation function' => 'last', //'avg' is not available for text elements, changed to 'last'
							'Highlights' => [
								['color' => 'D1C4E9', 'regexp' => '1'],
								['color' => '80CBC4', 'regexp' => '2']
							]
						]
					],
					'value' => 'test',
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Log) item with data and aggregation function first.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Thresholds and non-nmeric (Log) item with aggregation function first',
							'Item name' => 'Item with type of information - Log',
							'Advanced configuration' => true,
							'Aggregation function' => 'first', //'sum' is not available for text elements, changed to 'first'
							'Highlights' => [
								['color' => 'D1C4E9', 'regexp' => '1'],
								['color' => '80CBC4', 'regexp' => '2']
							]
						]
					],
					'value' => 'test',
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Character) item with data and aggregation function first.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Thresholds and non-nmeric (Character) item with aggregation function first',
							'Item name' => 'Item with type of information - Character',
							'Advanced configuration' => true,
							'Aggregation function' => 'first',
							'Highlights' => [
								['color' => 'D1C4E9', 'regexp' => '0']
							]
						]
					],
					'value' => 'test',
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Text) item with data and aggregation function last.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Thresholds and non-nmeric (Text) item with aggregation function last',
							'Item name' => 'Item with type of information - Text',
							'Advanced configuration' => true,
							'Aggregation function' => 'last',
							'Highlights' => [
								['color' => 'D1C4E9', 'regexp' => '0']
							]
						]
					],
					'value' => 'test',
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Log) item with data and aggregation function not used.
			[
				[
					'column_fields' => [
						[
							'Name' => 'Thresholds and non-nmeric (Log) item with aggregation function not used',
							'Item name' => 'Item with type of information - Log',
							'Highlights' => [
								['color' => 'D1C4E9', 'regexp' => '0']
							]
						]
					],
					'value' => 'test',
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			]
		];
	}

	/**
	 * @backup !history, !history_log, !history_str, !history_text, !history_uint
	 *
	 * @dataProvider getThresholdData
	 */
	public function testDashboardTopHostsWidget_ThresholdColor($data) {
		$time = strtotime('now');
		$this->createTopHostsWidget($data, self::$other_dashboardids[self::DASHBOARD_THRESHOLD]);
		$dashboard = CDashboardElement::find()->one();

		foreach ($data['column_fields'] as $column) {
			$colors = CTestArrayHelper::get($column, 'Thresholds') ? $column['Thresholds'] : $column['Highlights'];

			foreach ($colors as $color_bar) {
				// Insert item data.
				if (array_key_exists('value', $data)) {
					CDataHelper::addItemData(self::$aggregation_itemids[$column['Item name']], $data['value'], $time);

					if (array_key_exists('numeric', $data)) {
						$data['value']++;
					}

					$time++;
				}

				$this->page->refresh()->waitUntilReady();

				$rgb = (array_key_exists('expected_color', $data))
					? implode(', ', sscanf($data['expected_color'], "%02x%02x%02x"))
					: implode(', ', sscanf($color_bar['color'], "%02x%02x%02x"));

				$opacity = (array_key_exists('opacity', $data)) ? '0' : '1';
				$this->assertEquals('rgba('.$rgb.', '.$opacity.')', $dashboard->getWidget(self::DEFAULT_WIDGET_NAME)
						->query('xpath:.//div[contains(@class, "dashboard-widget-tophosts")]/../..//td')->one()
						->getCSSValue('background-color')
				);
			}
		}

		// Necessary for test stability.
		$dashboard->edit()->deleteWidget(self::DEFAULT_WIDGET_NAME)->save();
	}

	public static function getAggregationFunctionData() {
		return [
			// Widget with several columns, common item (value mapping), different aggregation functions and time periods.
			[
				[
					'widget_name' => 'Widget with multiple columns',
					'column_fields' => [
						[
							'Name' => 'Min',
							'Item name' => 'Value mapping',
							'Advanced configuration' => true,
							'Aggregation function' => 'min',
							'Time period' => 'Custom'
						],
						[
							'Name' => 'Max',
							'Item name' => 'Value mapping',
							'Advanced configuration' => true,
							'Aggregation function' => 'max',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-2h',
							'id:time_period_to' => 'now-1h'
						],
						[
							'Name' => 'Avg',
							'Item name' => 'Value mapping',
							'Advanced configuration' => true,
							'Aggregation function' => 'avg',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-7d',
							'id:time_period_to' => 'now-5d'
						],
						[
							'Name' => 'Avg 2',
							'Item name' => 'Value mapping',
							'Advanced configuration' => true,
							'Aggregation function' => 'avg',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-4d',
							'id:time_period_to' => 'now-2d'
						],
						[
							'Name' => 'Count',
							'Item name' => 'Value mapping',
							'Advanced configuration' => true,
							'Aggregation function' => 'count',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-5h-30m',
							'id:time_period_to' => 'now-14400' // -4 hours.
						],
						[
							'Name' => 'Sum',
							'Item name' => 'Value mapping',
							'Advanced configuration' => true,
							'Aggregation function' => 'sum',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-360m',
							'id:time_period_to' => 'now-240m'
						],
						[
							'Name' => 'First',
							'Item name' => 'Value mapping',
							'Advanced configuration' => true,
							'Aggregation function' => 'first',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1h-30m',
							'id:time_period_to' => 'now-30m'
						],
						[
							'Name' => 'Last',
							'Item name' => 'Value mapping',
							'Advanced configuration' => true,
							'Aggregation function' => 'last',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1h-20m-600s',
							'id:time_period_to' => 'now-1800s'
						]
					],
					'item_data' => [
						[
							'name' => 'Value mapping',
							'value' => '0',
							'time' => 'now'
						],
						[
							'name' => 'Value mapping',
							'value' => '0',
							'time' => '-45 minutes'
						],
						[
							'name' => 'Value mapping',
							'value' => '1',
							'time' => '-61 minute'
						],
						[
							'name' => 'Value mapping',
							'value' => '0',
							'time' => '-122 minutes'
						],
						[
							'name' => 'Value mapping',
							'value' => '1',
							'time' => '-270 minutes'
						],
						[
							'name' => 'Value mapping',
							'value' => '0',
							'time' => '-275 minutes'
						],
						[
							'name' => 'Value mapping',
							'value' => '1',
							'time' => '-276 minutes'
						],
						[
							'name' => 'Value mapping',
							'value' => '0',
							'time' => '-63 hours'
						],
						[
							'name' => 'Value mapping',
							'value' => '1',
							'time' => '-3 days'
						],
						[
							'name' => 'Value mapping',
							'value' => '1',
							'time' => '-6 days'
						]
					],
					'result' => [
						[
							'Min' => 'Down (0)',
							'Max' => 'Up (1)',
							'Avg' => 'Up (1)',
							'Avg 2' => '0.50', // Value mapping is ignored if value doesn't equals 0 or 1.
							'Count' => '3.00', // Mapping is not used if aggregation function is 'sum' or 'count'.
							'Sum' => '2.00',
							'First' => 'Up (1)',
							'Last' => 'Down (0)'
						]
					]
				]
			],
			// Value mapping with aggregation function 'not used'.
			[
				[
					'widget_name' => 'Value mapping with aggreagation Not used',
					'column_fields' => [
						[
							// 'not used' is default value for aggregation function field.
							'Name' => 'Value mapping with aggregation function not used',
							'Item name' => 'Value mapping'
						]
					],
					'item_data' => [
						[
							'name' => 'Value mapping',
							'value' => '1',
							'time' => '-15 minutes'
						],
						[
							'name' => 'Value mapping',
							'value' => '0',
							'time' => '-45 minutes'
						],
						[
							'name' => 'Value mapping',
							'value' => '1',
							'time' => '-50 minutes'
						]
					],
					'result' => [
						[
							'Value mapping with aggregation function not used' => 'Up (1)'
						]
					]

				]
			],
			// Numeric items with aggregation function 'min' and 'max', decimal places and Custom time period.
			[
				[
					'widget_name' => 'Aggregation min and max + decimal + custom time period',
					'column_fields' => [
						[
							'Name' => 'Numeric (unsigned)',
							'Item name' => 'Item with type of information - numeric (unsigned)',
							'Decimal places' => '9',
							'Advanced configuration' => true,
							'Aggregation function' => 'min',
							'Time period' => 'Custom'
						],
						[
							'Name' => 'Numeric (float)',
							'Item name' => 'Item with type of information - numeric (float)',
							'Decimal places' => '3',
							'Advanced configuration' => true,
							'Aggregation function' => 'max',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-3h',
							'id:time_period_to' => 'now'
						]
					],
					'item_data' => [
							[
								'name' => 'Item with type of information - numeric (unsigned)',
								'value' => '5',
								'time' => '-5 minutes'
							],
							[
								'name' => 'Item with type of information - numeric (float)',
								'value' => '7.76',
								'time' => '-6 minutes'
							],
							[
								'name' => 'Item with type of information - numeric (unsigned)',
								'value' => '4',
								'time' => '-30 minutes'
							],
							[
								'name' => 'Item with type of information - numeric (unsigned)',
								'value' => '10',
								'time' => '-61 minute'
							],
							[
								'name' => 'Item with type of information - numeric (float)',
								'value' => '7.77',
								'time' => '-90 minutes'
							],
							[
								'name' => 'Item with type of information - numeric (float)',
								'value' => '7.78',
								'time' => '-5 hours'
							]
					],
					'result' => [
						[
							'Numeric (unsigned)' => '4.000000000',
							'Numeric (float)' => '7.770'
						]
					]
				]
			],
			// Numeric (unsigned) item with aggregation function 'avg', default decimal places and Custom time period.
			[
				[
					'widget_name' => 'Avg aggregation + custom time period',
					'column_fields' => [
						[
							'Name' => 'Numeric (unsigned) with avg',
							'Item name' => 'Item with type of information - numeric (unsigned)',
							'Advanced configuration' => true,
							'Aggregation function' => 'avg',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-30m',
							'id:time_period_to' => 'now'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - numeric (unsigned)',
							'value' => '2',
							'time' => '-30 seconds'
						],
						[
							'name' => 'Item with type of information - numeric (unsigned)',
							'value' => '3',
							'time' => '-45 seconds'
						],
						[
							'name' => 'Item with type of information - numeric (unsigned)',
							'value' => '10',
							'time' => '-60 seconds'
						],
						[
							'name' => 'Item with type of information - numeric (unsigned)',
							'value' => '15',
							'time' => '-90 seconds'
						]
					],
					'result' => [
						[
							'Numeric (unsigned) with avg' => '7.50'
						]
					]
				]
			],
			// Item with units, aggregation function 'count' and Custom time period.
			[
				[
					'widget_name' => 'Item with units + count aggregation + custom time period',
					'column_fields' => [
						[
							'Name' => 'Units should not appear',
							'Item name' => 'Item with units',
							'Advanced configuration' => true,
							'Aggregation function' => 'count',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1h-20m-30s',
							'id:time_period_to' => 'now'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with units',
							'value' => '2',
							'time' => '-10 minutes'
						],
						[
							'name' => 'Item with units',
							'value' => '95',
							'time' => '-15 minutes'
						]
					],
					'result' => [
						[
							'Units should not appear' => '2.00' // Item units are not shown if aggregation function is 'count'.
						]
					]
				]
			],
			// Item with units, aggregation function 'sum' and Custom time period.
			[
				[
					'widget_name' => 'Item with units + sum aggregation + custom time period',
					'column_fields' => [
						[
							'Name' => 'Units should appear',
							'Item name' => 'Item with units',
							'Advanced configuration' => true,
							'Aggregation function' => 'sum',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1h-20m-30s',
							'id:time_period_to' => 'now'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with units',
							'value' => '2',
							'time' => '-10 minutes'
						],
						[
							'name' => 'Item with units',
							'value' => '95',
							'time' => '-15 minutes'
						]
					],
					'result' => [
						[
							'Units should appear' => '97.00 %'
						]
					]
				]
			],
			// Numeric (float) item with aggregation function 'first' and Custom time period.
			[
				[
					'widget_name' => 'Float item + first aggregation + custom time period',
					'column_fields' => [
						[
							'Name' => 'First',
							'Item name' => 'Item with type of information - numeric (float)',
							'Advanced configuration' => true,
							'Aggregation function' => 'first',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-30d',
							'id:time_period_to' => 'now-1d'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => '11.11',
							'time' => '-10 days'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => '12.55',
							'time' => '-15 days'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => '12.01',
							'time' => '-20 days'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => '12.99',
							'time' => '-25 days'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => '121.12',
							'time' => '-31 days'
						]
					],
					'result' => [
						[
							'First' => '12.99'
						]
					]
				]
			],
			// Numeric (float) item with aggregation function 'last' and Custom time period with absolute time.
			[
				[
					'widget_name' => 'Float item + last aggregation + custom time period + absolute time',
					'column_fields' => [
						[
							'Name' => 'Last',
							'Item name' => 'Item with type of information - numeric (float)',
							'Advanced configuration' => true,
							'Aggregation function' => 'last',
							'Time period' => 'Custom',
							'id:time_period_from' => '{date} 00:00:00',
							'id:time_period_to' => '{date} 23:59:59'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => '12.33',
							'time' => '{date} 04:00:00'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => '12.55',
							'time' => '{date} 08:00:00'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => '12.99',
							'time' => '{date} 11:00:00'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => '11.99',
							'time' => '{date} 12:00:00'
						]
					],
					'substitute_date' => true,
					'result' => [
						[
							'Last' => '11.99'
						]
					]
				]
			],
			// Non-numeric (Text) item with aggregation function 'count' and Custom time period with relative time.
			[
				[
					'widget_name' => 'Text item + count aggregation + custom time period + relative time',
					'column_fields' => [
						[
							'Name' => 'Non-numeric (Text) item with aggregation function count',
							'Item name' => 'Item with type of information - Text',
							'Advanced configuration' => true,
							'Aggregation function' => 'count',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-2y-1M-2w-1d-10h-30m-20s',
							'id:time_period_to' => 'now-1y-1M-2w-1d-10h-30m-20s'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - Text',
							'value' => 'text 1',
							'time' => '-1 year -1 month -2 weeks -2 days -10 hours -30 minutes -20 seconds'
						],
						[
							'name' => 'Item with type of information - Text',
							'value' => 'text 2',
							'time' => '-1 year -1 month -2 weeks -1 day -20 hours -30 minutes -20 seconds'
						],
						[
							'name' => 'Item with type of information - Text',
							'value' => 'text 3',
							'time' => '-1 year -1 month -3 weeks -2 days -10 hours -30 minutes -20 seconds'
						],
						[
							'name' => 'Item with type of information - Text',
							'value' => 'text 4',
							'time' => '-1 year -2 month -2 weeks -2 days -10 hours -30 minutes -20 seconds'
						]
					],
					'result' => [
						[
							'Non-numeric (Text) item with aggregation function count' => '4'
						]
					]
				]
			],
			// Non-numeric items with aggregation function 'min'/'max'/'avg'/'sum' and Custom time period.
			[
				[
					'widget_name' => 'Non-numeric items with min, max, avg, sum aggregation',
					'no_data_found' => true,
					'column_fields' => [
						[
							'Name' => 'Log item with aggregation function min',
							'Item name' => 'Item with type of information - Log',
							'Display item value as' => 'Numeric',
							'Advanced configuration' => true,
							'Aggregation function' => 'min', // only numeric items will be displayed.
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-2y',
							'id:time_period_to' => 'now-1y'
						],
						[
							'Name' => 'Character item with aggregation function max',
							'Item name' => 'Item with type of information - Character',
							'Display item value as' => 'Numeric',
							'Advanced configuration' => true,
							'Aggregation function' => 'max', // only numeric items will be displayed.
							'Time period' => 'Custom',
							'id:time_period_from' => '2023-12-12 00:00:00',
							'id:time_period_to' => '2023-12-12 10:00:00'
						],
						[
							'Name' => 'Text item with aggregation function avg',
							'Item name' => 'Item with type of information - Text',
							'Display item value as' => 'Numeric',
							'Advanced configuration' => true,
							'Aggregation function' => 'avg', // only numeric items will be displayed.
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1d',
							'id:time_period_to' => 'now'
						],
						[
							'Name' => 'Log item with aggregation function sum',
							'Item name' => 'Item with type of information - Log',
							'Display item value as' => 'Numeric',
							'Advanced configuration' => true,
							'Aggregation function' => 'sum', // only numeric items will be displayed.
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1d',
							'id:time_period_to' => 'now'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - Text',
							'value' => 'Text 1',
							'time' => '-1 hour'
						],
						[
							'name' => 'Item with type of information - Log',
							'value' => 'Log 1',
							'time' => '-2 hours'
						],
						[
							'name' => 'Item with type of information - Text',
							'value' => 'Text 2',
							'time' => '-1 day -1 hour'
						],
						[
							'name' => 'Item with type of information - Log',
							'value' => 'Log 2',
							'time' => '-1 day -2 hours'
						],
						[
							'name' => 'Item with type of information - Character',
							'value' => 'Character 1',
							'time' => '2023-12-12 05:00:00'
						],
						[
							'name' => 'Item with type of information - Log',
							'value' => 'log 1',
							'time' => '-15 month'
						]
					]
				]
			],
			// Non-numeric (Character) item with aggregation function 'first' and Custom time period.
			[
				[
					'widget_name' => 'Char item + first aggregation + custom time period',
					'column_fields' => [
						[
							'Name' => 'Non-numeric (Character) item with aggregation function first',
							'Item name' => 'Item with type of information - Character',
							'Advanced configuration' => true,
							'Aggregation function' => 'first',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1d',
							'id:time_period_to' => 'now'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - Character',
							'value' => 'Character 1',
							'time' => '-1 hour'
						],
						[
							'name' => 'Item with type of information - Character',
							'value' => 'Character 2',
							'time' => '-10 hours'
						],
						[
							'name' => 'Item with type of information - Character',
							'value' => 'Character 3',
							'time' => '-1 day -1 hour'
						]
					],
					'result' => [
						[
							'Non-numeric (Character) item with aggregation function first' => 'Character 2'
						]
					]
				]
			],
			// Non-numeric (Text) item with aggregation function 'last' and Custom time period.
			[
				[
					'widget_name' => 'Text item + last aggregation + custom time period',
					'column_fields' => [
						[
							'Name' => 'Non-numeric (Text) item with aggregation function last',
							'Item name' => 'Item with type of information - Text',
							'Advanced configuration' => true,
							'Aggregation function' => 'last',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1d',
							'id:time_period_to' => 'now'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - Text',
							'value' => 'text 2',
							'time' => '-1 hour'
						],
						[
							'name' => 'Item with type of information - Text',
							'value' => 'text 1',
							'time' => '-1 hour -1 minute'
						],
						[
							'name' => 'Item with type of information - Text',
							'value' => 'text 3',
							'time' => '-8 days'
						]
					],
					'result' => [
						[
							'Non-numeric (Text) item with aggregation function last' => 'text 2'
						]
					]
				]
			],
			// Numeric (unsigned) item with aggregation function 'avg', trends history data and Custom time period.
			[
				[
					'widget_name' => 'Numeric item + avg aggregation + trends + custom time period',
					'column_fields' => [
						[
							'Name' => 'Numeric (unsigned) item with trends and aggregation function avg',
							'Item name' => 'Item with type of information - numeric (unsigned)',
							'Advanced configuration' => true,
							'Aggregation function' => 'avg',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1h',
							'id:time_period_to' => 'now',
							'History data' => 'Trends'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - numeric (unsigned)',
							'value' => [
								[
									'num' => '3',
									'avg' => '4',
									'min' => '2',
									'max' => '7'
								]
							],
							'time' => 'now'
						],
						[
							'name' => 'Item with type of information - numeric (unsigned)',
							'value' => [
								[
									'num' => '5',
									'avg' => '5',
									'min' => '1',
									'max' => '8'
								]
							],
							'time' => '-1 hour'
						]
					],
					'result' => [
						[
							'Numeric (unsigned) item with trends and aggregation function avg' => '4.00'
						]
					]
				]
			],
			// Numeric (float) item with aggregation function 'min', trends history data and Custom time period.
			[
				[
					'widget_name' => 'Numeric item + min aggregation + trends + custom time period',
					'column_fields' => [
						[
							'Name' => 'Numeric (float) item with trends and aggregation function min',
							'Item name' => 'Item with type of information - numeric (float)',
							'Advanced configuration' => true,
							'Aggregation function' => 'min',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-2h',
							'id:time_period_to' => 'now-1h',
							'History data' => 'Trends'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => [
								[
									'num' => '10',
									'avg' => '3.33',
									'min' => '1.11',
									'max' => '5.55'
								]
							],
							'time' => 'now'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => [
								[
									'num' => '11',
									'avg' => '2.22',
									'min' => '1.51',
									'max' => '3.33'
								]
							],
							'time' => '-1 hour'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => [
								[
									'num' => '51',
									'avg' => '5.55',
									'min' => '1.09',
									'max' => '8.88'
								]
							],
							'time' => '-2 hours'
						]
					],
					'result' => [
						[
							'Numeric (float) item with trends and aggregation function min' => '1.51'
						]
					]
				]
			],
			// Numeric (float) item with aggregation function 'max', trends history data and Custom time period.
			[
				[
					'widget_name' => 'Numeric item + max aggregation + trends + custom time period',
					'column_fields' => [
						[
							'Name' => 'Numeric (float) item with trends and aggregation function max',
							'Item name' => 'Item with type of information - numeric (float)',
							'Advanced configuration' => true,
							'Aggregation function' => 'max',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-3h',
							'id:time_period_to' => 'now-2h',
							'History data' => 'Trends'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => [
								[
									'num' => '101',
									'avg' => '5.89',
									'min' => '1.77',
									'max' => '11.10'
								]
							],
							'time' => '-2 hours'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => [
								[
									'num' => '101',
									'avg' => '5.87',
									'min' => '1.05',
									'max' => '11.11'
								]
							],
							'time' => '-3 hours'
						]
					],
					'result' => [
						[
							'Numeric (float) item with trends and aggregation function max' => '11.10'
						]
					]
				]
			],
			// Numeric (unsigned) item with aggregation function 'count', trends history data and Custom time period.
			[
				[
					'widget_name' => 'Numeric item + count aggregation + trends + custom time period',
					'column_fields' => [
						[
							'Name' => 'Numeric (unsigned) item with trends and aggregation function count',
							'Item name' => 'Item with type of information - numeric (unsigned)',
							'Advanced configuration' => true,
							'Aggregation function' => 'count',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1h',
							'id:time_period_to' => 'now',
							'History data' => 'Trends'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - numeric (unsigned)',
							'value' => [
								[
									'num' => '7',
									'avg' => '5',
									'min' => '1',
									'max' => '8'
								]
							],
							'time' => 'now'
						],
						[
							'name' => 'Item with type of information - numeric (unsigned)',
							'value' => [
								[
									'num' => '9',
									'avg' => '3',
									'min' => '2',
									'max' => '7'
								]
							],
							'time' => '-1 hour'
						]
					],
					'result' => [
						[
							'Numeric (unsigned) item with trends and aggregation function count' => '7.00' // num result.
						]
					]
				]
			],
			// Numeric (float) item with aggregation function 'sum', trends history data and Custom time period.
			[
				[
					'widget_name' => 'Numeric item + sum aggregation + trends + custom time period',
					'column_fields' => [
						[
							'Name' => 'Numeric (float) item with trends and aggregation function sum',
							'Item name' => 'Item with type of information - numeric (float)',
							'Advanced configuration' => true,
							'Aggregation function' => 'sum',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-2d',
							'id:time_period_to' => 'now-1d',
							'History data' => 'Trends'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => [
								[
									'num' => '5',
									'avg' => '3.33',
									'min' => '1.11',
									'max' => '55.55'
								]
							],
							'time' => 'now'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => [
								[
									'num' => '7',
									'avg' => '7.77',
									'min' => '3.33',
									'max' => '11.11'
								]
							],
							'time' => '-1 day'
						]
					],
					'result' => [
						[
							'Numeric (float) item with trends and aggregation function sum' => '54.39' // num * avg result.
						]
					]
				]
			],
			// Numeric (unsigned) item with aggregation function 'first', trends history data and Custom time period.
			[
				[
					'widget_name' => 'Numeric item + first aggregation + trends + custom time period',
					'column_fields' => [
						[
							'Name' => 'Numeric (unsigned) item with trends and aggregation function first',
							'Item name' => 'Item with type of information - numeric (unsigned)',
							'Advanced configuration' => true,
							'Aggregation function' => 'first',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-2w',
							'id:time_period_to' => 'now-1w',
							'History data' => 'Trends'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - numeric (unsigned)',
							'value' => [
								[
									'num' => '168',
									'avg' => '8',
									'min' => '2',
									'max' => '14'
								]
							],
							'time' => 'now'
						],
						[
							'name' => 'Item with type of information - numeric (unsigned)',
							'value' => [
								[
									'num' => '336',
									'avg' => '6',
									'min' => '4',
									'max' => '8'
								]
							],
							'time' => '-1 week'
						]
					],
					'result' => [
						[
							'Numeric (unsigned) item with trends and aggregation function first' => '6.00' // avg result.
						]
					]
				]
			],
			// Numeric (float) item with aggregation function 'last', trends history data and Custom time period.
			[
				[
					'widget_name' => 'Numeric item + last aggregation + trends + custom time period',
					'column_fields' => [
						[
							'Name' => 'Numeric (float) item with trends and aggregation function last',
							'Item name' => 'Item with type of information - numeric (float)',
							'Advanced configuration' => true,
							'Aggregation function' => 'last',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1w',
							'id:time_period_to' => 'now',
							'History data' => 'Trends'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => [
								[
									'num' => '168',
									'avg' => '8.11',
									'min' => '2.58',
									'max' => '17.89'
								]
							],
							'time' => 'now'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => [
								[
									'num' => '336',
									'avg' => '6.78',
									'min' => '4.13',
									'max' => '8.09'
								]
							],
							'time' => '-1 week'
						]
					],
					'result' => [
						[
							'Numeric (float) item with trends and aggregation function last' => '8.11' // avg result.
						]
					]
				]
			],
			// Check that widget with bar/indicators returns 'No data found' if non-numeric data is selected.
			[
				[
					'widget_name' => 'Not displaying non-numeric data for bar indicators',
					'no_data_found' => true,
					'column_fields' => [
						[
							'Name' => 'Min',
							'Item name' => 'Item with type of information - Log',
							'Display item value as' => 'Numeric',
							'Display' => 'Bar', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '10',
							'Advanced configuration' => true,
							'Aggregation function' => 'min',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-2h',
							'id:time_period_to' => 'now-1h'
						],
						[
							'Name' => 'Max',
							'Item name' => 'Item with type of information - Character',
							'Display item value as' => 'Numeric',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '10',
							'Advanced configuration' => true,
							'Aggregation function' => 'max',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1w',
							'id:time_period_to' => 'now-1d'
						],
						[
							'Name' => 'Avg',
							'Item name' => 'Item with type of information - Text',
							'Display item value as' => 'Numeric',
							'Display' => 'Bar', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '10',
							'Advanced configuration' => true,
							'Aggregation function' => 'avg',
							'Time period' => 'Custom',
							'id:time_period_from' => '2023-12-12 00:00:00',
							'id:time_period_to' => '2023-12-12 10:00:00'
						],
						[
							'Name' => 'Sum',
							'Item name' => 'Item with type of information - Log',
							'Display item value as' => 'Numeric',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '10',
							'Advanced configuration' => true,
							'Aggregation function' => 'sum',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1h-30m',
							'id:time_period_to' => 'now-30m'
						],
						[
							'Name' => 'First',
							'Item name' => 'Item with type of information - Character',
							'Display item value as' => 'Numeric',
							'Display' => 'Bar', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '10',
							'Advanced configuration' => true,
							'Aggregation function' => 'first',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-30m',
							'id:time_period_to' => 'now'
						],
						[
							'Name' => 'Last',
							'Item name' => 'Item with type of information - Text',
							'Display item value as' => 'Numeric',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '10',
							'Advanced configuration' => true,
							'Aggregation function' => 'last',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-2d',
							'id:time_period_to' => 'now-1d'
						],
						[
							'Name' => 'Not used',
							'Item name' => 'Item with type of information - Log',
							'Display item value as' => 'Numeric',
							'Display' => 'Bar', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '10'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - Character',
							'value' => 'Character 1',
							'time' => '-15 minutes'
						],
						[
							'name' => 'Item with type of information - Log',
							'value' => 'Log 1',
							'time' => '-45 minutes'
						],
						[
							'name' => 'Item with type of information - Log',
							'value' => 'Log 2',
							'time' => '-1 hour -10 minutes'
						],
						[
							'name' => 'Item with type of information - Text',
							'value' => 'Text 1',
							'time' => '-1 day -1 hour'
						],
						[
							'name' => 'Item with type of information - Character',
							'value' => 'Character 2',
							'time' => '-3 days'
						],
						[
							'name' => 'Item with type of information - Text',
							'value' => 'Text 2',
							'time' => '2023-12-12 05:00:00'
						]
					]
				]
			],
			// Check that widget displays bar/idnicators when aggregation function 'count' is used for non-numeric item.
			[
				[
					'widget_name' => 'Displaying count for non-numeric data via bar indicators',
					'column_fields' => [
						[
							'Name' => 'Count Log',
							'Item name' => 'Item with type of information - Log',
							'Display item value as' => 'Numeric',
							'Display' => 'Bar', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '1',
							'Advanced configuration' => true,
							'Aggregation function' => 'count',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-2h',
							'id:time_period_to' => 'now-1h'
						],
						[
							'Name' => 'Count Character',
							'Item name' => 'Item with type of information - Character',
							'Display item value as' => 'Numeric',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '1',
							'Thresholds' => [
								['color' => '4000FF', 'threshold' => '0'],
								['color' => 'BFFF00', 'threshold' => '0.5']
							],
							'Advanced configuration' => true,
							'Aggregation function' => 'count',
							'Time period' => 'Custom',
							'id:time_period_from' => 'now-1w',
							'id:time_period_to' => 'now-1d'
						],
						[
							'Name' => 'Count Text',
							'Item name' => 'Item with type of information - Text',
							'Display item value as' => 'Numeric',
							'Display' => 'Bar', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '1',
							'Thresholds' => [
								['color' => '4000FF', 'threshold' => '0'],
								['color' => 'D1C4E9', 'threshold' => '1']
							],
							'Advanced configuration' => true,
							'Aggregation function' => 'count',
							'Time period' => 'Custom',
							'id:time_period_from' => '2023-12-12 00:00:00',
							'id:time_period_to' => '2023-12-12 10:00:00'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - Log',
							'value' => 'Log 1',
							'time' => '-1 hour -15 minutes'
						],
						[
							'name' => 'Item with type of information - Character',
							'value' => 'Character',
							'time' => '-3 days'
						],
						[
							'name' => 'Item with type of information - Text',
							'value' => 'Text',
							'time' => '2023-12-12 05:00:00'
						]
					],
					'screen_name' => 'mixed_non_numeric'
				]
			],
			// Non-numeric items without data but with aggregation function count (return 0) that are displayed as bar/indicators.
			[
				[
					'widget_name' => 'Displaying 0 count for item without data via bar indicators',
					'column_fields' => [
						[
							'Name' => 'Count Log',
							'Item name' => 'Item with type of information - Log',
							'Display item value as' => 'Numeric',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '1',
							'Advanced configuration' => true,
							'Aggregation function' => 'count'
						],
						[
							'Name' => 'Count Character',
							'Item name' => 'Item with type of information - Character',
							'Display item value as' => 'Numeric',
							'Display' => 'Bar', // With this setting only numeric data will be displayed.
							'Min' => '-1',
							'Max' => '1',
							'Thresholds' => [
								['color' => 'BFFF00', 'threshold' => '0'],
								['color' => '4000FF', 'threshold' => '1']
							],
							'Advanced configuration' => true,
							'Aggregation function' => 'count'
						],
						[
							'Name' => 'Count Text',
							'Item name' => 'Item with type of information - Text',
							'Display item value as' => 'Numeric',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '1',
							'Thresholds' => [
								['color' => '4000FF', 'threshold' => '0'],
								['color' => 'D1C4E9', 'threshold' => '1']
							],
							'Advanced configuration' => true,
							'Aggregation function' => 'count'
						]
					],
					'screen_name' => 'mixed_non_numeric_without_item_data'
				]
			],
			// Numeric items without data that are displayed as bar/indicators.
			[
				[
					'widget_name' => 'Display data for numeric items as bar indicators',
					'no_data_found' => true,
					'column_fields' => [
						[
							'Name' => 'Min',
							'Item name' => 'Item with type of information - numeric (unsigned)',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '1',
							'Advanced configuration' => true,
							'Aggregation function' => 'min'
						],
						[
							'Name' => 'Max',
							'Item name' => 'Item with type of information - numeric (float)',
							'Display' => 'Bar', // With this setting only numeric data will be displayed.
							'Min' => '-1',
							'Max' => '1',
							'Advanced configuration' => true,
							'Aggregation function' => 'max'
						],
						[
							'Name' => 'Avg',
							'Item name' => 'Item with type of information - numeric (unsigned)',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '1',
							'Advanced configuration' => true,
							'Aggregation function' => 'avg'
						],
						[
							'Name' => 'Sum',
							'Item name' => 'Item with type of information - numeric (float)',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '1',
							'Advanced configuration' => true,
							'Aggregation function' => 'sum'
						],
						[
							'Name' => 'First',
							'Item name' => 'Item with type of information - numeric (unsigned)',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '1',
							'Advanced configuration' => true,
							'Aggregation function' => 'first'
						],
						[
							'Name' => 'Last',
							'Item name' => 'Item with type of information - numeric (float)',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '1',
							'Thresholds' => [
								['color' => '4000FF', 'threshold' => '0'],
								['color' => 'D1C4E9', 'threshold' => '1']
							],
							'Advanced configuration' => true,
							'Aggregation function' => 'last'
						]
					]
				]
			],
			// Numeric items with data and aggregation function min/max/avg/count that are displayed as bar/indicators.
			[
				[
					'widget_name' => 'Display data with min, max, avg, count aggregation as bar indicators',
					'column_fields' => [
						[
							'Name' => 'Min',
							'Item name' => 'Item with type of information - numeric (unsigned)',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '-1',
							'Max' => '10',
							'Thresholds' => [
								['color' => 'FFF9C4', 'threshold' => '-1']
							],
							'Advanced configuration' => true,
							'Aggregation function' => 'min',
							'Time period' => 'Custom'
						],
						[
							'Name' => 'Max',
							'Item name' => 'Item with type of information - numeric (float)',
							'Display' => 'Bar', // With this setting only numeric data will be displayed.
							'Min' => '-1.00',
							'Max' => '10.00',
							'Advanced configuration' => true,
							'Aggregation function' => 'max',
							'Time period' => 'Custom'
						],
						[
							'Name' => 'Avg',
							'Item name' => 'Item with type of information - numeric (unsigned)',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '10',
							'Advanced configuration' => true,
							'Aggregation function' => 'avg',
							'Time period' => 'Custom'
						],
						[
							'Name' => 'Count',
							'Item name' => 'Item with type of information - numeric (float)',
							'Display' => 'Bar', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '10.00',
							'Thresholds' => [
								['color' => '4000FF', 'threshold' => '1.00'],
								['color' => 'D1C4E9', 'threshold' => '5.00']
							],
							'Advanced configuration' => true,
							'Aggregation function' => 'count',
							'Time period' => 'Custom'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - numeric (unsigned)',
							'value' => '1',
							'time' => '-15 minutes'
						],
						[
							'name' => 'Item with type of information - numeric (unsigned)',
							'value' => '5',
							'time' => '-30 minutes'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => '1.11',
							'time' => '-20 minutes'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => '5.44',
							'time' => '-40 minutes'
						]
					],
					'screen_name' => 'mixed_numeric'
				]
			],
			// Numeric items with data and aggregation function sum/first/last that are displayed as bar/indicators.
			[
				[
					'widget_name' => 'Display data with sum, first, last aggregation as bar indicators',
					'column_fields' => [
						[
							'Name' => 'Sum',
							'Item name' => 'Item with type of information - numeric (unsigned)',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '10',
							'Advanced configuration' => true,
							'Aggregation function' => 'sum'
						],
						[
							'Name' => 'First',
							'Item name' => 'Item with type of information - numeric (float)',
							'Display' => 'Bar', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '10.00',
							'Thresholds' => [
								['color' => '4000FF', 'threshold' => '-1.11'],
								['color' => 'D1C4E9', 'threshold' => '1.11']
							],
							'Advanced configuration' => true,
							'Aggregation function' => 'first'
						],
						[
							'Name' => 'Last',
							'Item name' => 'Item with type of information - numeric (unsigned)',
							'Display' => 'Indicators', // With this setting only numeric data will be displayed.
							'Min' => '0',
							'Max' => '10',
							'Thresholds' => [
								['color' => '4000FF', 'threshold' => '0'],
								['color' => 'D1C4E9', 'threshold' => '1']
							],
							'Advanced configuration' => true,
							'Aggregation function' => 'last'
						]
					],
					'item_data' => [
						[
							'name' => 'Item with type of information - numeric (unsigned)',
							'value' => '1',
							'time' => '-15 minutes'
						],
						[
							'name' => 'Item with type of information - numeric (unsigned)',
							'value' => '5',
							'time' => '-30 minutes'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => '1.11',
							'time' => '-20 minutes'
						],
						[
							'name' => 'Item with type of information - numeric (float)',
							'value' => '5.55',
							'time' => '-40 minutes'
						]
					],
					'screen_name' => 'mixed_numeric2'
				]
			]
		];
	}

	/**
	 * @backup !history, !history_log, !history_str, !history_text, !history_uint, !trends_uint, !trends
	 *
	 * @dataProvider getAggregationFunctionData
	 */
	public function testDashboardTopHostsWidget_AggregationFunctionData($data) {
		// Substitute macro in date related fields in test case where fixed history data (not trends) is checked.
		if (CTestArrayHelper::get($data, 'substitute_date')) {
			$data = $this->replaceDateMacroInData($data, 'today - 1 week', ['id:time_period_from', 'id:time_period_to']);
		}

		if (array_key_exists('item_data', $data)) {
			foreach ($data['item_data'] as $params) {
				CDataHelper::addItemData(self::$aggregation_itemids[$params['name']], $params['value'], strtotime($params['time']));
			}
		}

		$this->createTopHostsWidget($data, self::$other_dashboardids[self::DASHBOARD_AGGREGATION], $data['widget_name']);
		$dashboard = CDashboardElement::find()->one();

		if (array_key_exists('screen_name', $data)) {
			$this->assertScreenshot($dashboard->getWidget($data['widget_name']), $data['screen_name']);
		}
		else {
			$table_data = (array_key_exists('no_data_found', $data)) ? '' : $data['result'];
			$this->assertTableData($table_data, 'xpath://h4[text()='.CXPathHelper::escapeQuotes($data['widget_name']).']/../..//table');
		}

		// Necessary not to fill dashboard with widgets and, therefore, avoid slowing down dashboard performance during test.
		$dashboard->edit()->deleteWidget($data['widget_name'])->save();
	}

	/**
	 * Function used to create Top Hosts widget with special columns.
	 *
	 * @param array     $data			data provider values
	 * @param string    $dashboardid    id of the dashboard where to create Top Hosts widget
	 * @param string	$widget_name	name of the widget to be created
	 *
	 * @return CDashboardElement
	 */
	protected function createTopHostsWidget($data, $dashboardid, $widget_name = null) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$fields = array_key_exists('main_fields', $data) ? $data['main_fields'] : ['Name' => $widget_name];
		$form = $this->openWidgetAndFill($dashboard, 'Top hosts', $fields);

		// Fill Tags.
		if (array_key_exists('Tags', $data)) {
			$form->getField('id:evaltype')->fill(CTestArrayHelper::get($data['Tags'], 'evaluation', 'And/Or'));
			$this->setTags($data['Tags']['tags']);
		}

		// Add new column(s) and save widget.
		$this->fillColumnForm($data, 'create');

		// 'Order by' can only be filled after columns are added.
		if (array_key_exists('Order by', $data)) {
			$form->fill(['Order by' => $data['Order by']]);
		}

		$form->submit();
		COverlayDialogElement::ensureNotPresent();

		if (array_key_exists('main_fields', $data)) {
			$dashboard->getWidget($data['main_fields']['Name'])->waitUntilReady();
		}

		$dashboard->save();
		$dashboard->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		return $dashboard;
	}

	/**
	 * Check fields attributes for given form.
	 *
	 * @param array           $data    provided data
	 * @param CFormElement    $form    form to be checked
	 */
	protected function checkFieldsAttributes($data, $form) {
		foreach ($data as $label => $attributes) {
			$field = $form->getField($label);
			$this->assertTrue($field->isVisible(CTestArrayHelper::get($attributes, 'visible', true)));
			$this->assertTrue($field->isEnabled(CTestArrayHelper::get($attributes, 'enabled', true)));

			foreach ($attributes as $attribute => $value) {
				switch ($attribute) {
					case 'value':
						$this->assertEquals($value, $field->getValue());
						break;

					case 'maxlength':
					case 'placeholder':
						$this->assertEquals($value, $field->getAttribute($attribute));
						break;

					case 'labels':
						$this->assertEquals($value, $field->asSegmentedRadio()->getLabels()->asText());
						break;

					case 'options':
						$this->assertEquals($value, $field->asDropdown()->getOptions()->asText());
						break;

					case 'color':
						$this->assertEquals($value, $form->query($label)->asColorPicker()->one()->getValue());
						break;
				}
			}
		}
	}

	/**
	 * Test function for assuring that binary items are not available in Top hosts widget.
	 */
	public function testDashboardTopHostsWidget_CheckAvailableItems() {
		$this->checkAvailableItems('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardids[self::DASHBOARD_CREATE], self::DEFAULT_WIDGET_NAME
		);
	}

	public static function getCheckWidgetTableData() {
		return [
			// #0 Filtered by hosts, in column: item which came from two different templates.
			[
				[
					'main_fields' => [
						'Name' => 'Item on different hosts from one template',
						'Hosts' => ['HostA', 'HostB', 'HostC']
					],
					'column_fields' => [
						[
							'Name' => 'Host',
							'Data' => 'Host name'
						],
						[
							'Name' => 'Column1',
							'Item name' => [
								'values' => 'Item1',
								'context' => ['values' => 'HostA']
							]
						]
					],
					'result' => [
						['Host' => 'HostC', 'Column1' => '2.00'],
						['Host' => 'HostB', 'Column1' => '1.00'],
						['Host' => 'HostA', 'Column1' => '0.00']
					],
					'headers' => ['Host', 'Column1']
				]
			],
			// #1 Filtered by host group, Host limit is set less than filtered result.
			[
				[
					'main_fields' => [
						'Name' => 'Show lines < then possible result',
						'Host groups' => ['Top Hosts test host group'],
						'Host limit' => 2
					],
					'column_fields' => [
						[
							'Name' => 'Item',
							'Item name' => [
								'values' => 'Item1',
								'context' => ['values' => 'HostA']
							]
						]
					],
					'result' => [
						['Item' => '2.00'],
						['Item' => '1.00']
					],
					'headers' => ['Item']
				]
			],
			// #2 Filtered so that widget shows No data.
			[
				[
					'main_fields' => [
						'Name' => 'No data'
					],
					'column_fields' => [
						[
							'Name' => 'Item2',
							'Item name' => [
								'values' => 'Item2',
								'context' => ['values' => 'HostA']
							]
						]
					],
					'result' => [],
					'headers' => ['Item2']
				]
			],
			// #3 Filtered by tags, columns: text and item, order newest in bottom.
			[
				[
					'main_fields' => [
						'Name' => 'Hosts filtered by tag'
					],
					'Tags' => [
						'tags' => [
							[
								'name' => 'host',
								'operator' => 'Equals',
								'value' => 'B'
							]
						]
					],
					'column_fields' => [
						[
							'Name' => 'Text column',
							'Data' => 'Text',
							'Text' => '🙂🙃み け め 𒁥 test_text:'
						],
						[
							'Name' => '🙂🙃み け め 𒁥',
							'Item name' => [
								'values' => 'Item1',
								'context' => ['values' => 'HostA']
							]
						]
					],
					'result' => [
						['Text column' => '🙂🙃み け め 𒁥 test_text:', '🙂🙃み け め 𒁥' => '1.00'],
						['Text column' => '🙂🙃み け め 𒁥 test_text:', '🙂🙃み け め 𒁥' => '2.00']
					],
					'headers' => ['Text column', '🙂🙃み け め 𒁥']
				]
			],
			// #4 Filtered by tags with OR operator, different macros used in columns.
			[
				[
					'main_fields' => [
						'Name' => 'Hosts filtered by tag, macros in columns'
					],
					'Tags' => [
						'evaluation' => 'Or',
						'tags' => [
							[
								'name' => 'host',
								'operator' => 'Equals',
								'value' => 'B'
							],
							[
								'name' => 'tag',
								'operator' => 'Exists'
							]
						]
					],
					'column_fields' => [
						[
							'Name' => 'Host name',
							'Data' => 'Host name'
						],
						[
							'Name' => 'Text: Macro in host',
							'Data' => 'Text',
							'Text' => '{HOST.HOST}' // This will be resolved in widget.
						],
						[
							'Name' => '{#LLD_MACRO}',
							'Item name' => [
								'values' => 'Item1',
								'context' => ['values' => 'HostB']
							]
						],
						[
							'Name' => '{HOST.HOST}',
							'Item name' => [
								'values' => 'Item1',
								'context' => ['values' => 'HostB']
							]
						],
						[
							'Name' => '{$USERMACRO}',
							'Item name' => [
								'values' => 'Item2',
								'context' => ['values' => 'HostA']
							]
						],
						[
							'Name' => '{$1} Resolved',
							'Data' => 'Text',
							'Text' => '{$1}' // This will be resolved in widget.
						]
					],
					'Order by' => 'Host name',
					'result' => [
						[
							'Host name' => 'HostB',
							'Text: Macro in host' => 'HostB', // Resolved global macro.
							'{#LLD_MACRO}' => '1.00',
							'{HOST.HOST}' => '1.00',
							'{$USERMACRO}' => '',
							'{$1} Resolved' => 'Numeric macro' // Resolved user macro.
						],
						[
							'Host name' => 'HostC',
							'Text: Macro in host' => 'HostC', // Resolved global macro.
							'{#LLD_MACRO}' => '2.00',
							'{HOST.HOST}' => '2.00',
							'{$USERMACRO}' => '',
							'{$1} Resolved' => 'Numeric macro' // Resolved user macro.
						]
					],
					'headers' => ['Host name', 'Text: Macro in host', '{#LLD_MACRO}', '{HOST.HOST}',
						'{$USERMACRO}', '{$1} Resolved'
					]
				]
			],
			// #5 Filtered by Host group, not including Host in maintenance.
			[
				[
					'main_fields' => [
						'Name' => 'Hosts group without maintenance',
						'Host groups' => 'Top Hosts test host group'
					],
					'column_fields' => [
						[
							'Name' => 'Host',
							'Data' => 'Host name'
						],
						[
							'Name' => 'Maintenance Trapper',
							'Item name' => [
								'values' => 'Maintenance trapper',
								'context' => ['values' => 'Host in maintenance']
							]
						],
						[
							'Name' => 'Item1',
							'Item name' => [
								'values' => 'Item1',
								'context' => ['values' => 'HostA']
							],
							'Decimal places' => 5
						]
					],
					'result' => [
						['Host' => 'HostA', 'Maintenance Trapper' => '', 'Item1' => '0.00000'],
						['Host' => 'HostB', 'Maintenance Trapper' => '', 'Item1' => '1.00000'],
						['Host' => 'HostC', 'Maintenance Trapper' => '', 'Item1' => '2.00000']
					],
					'headers' => ['Host', 'Maintenance Trapper', 'Item1']
				]
			],
			// #6 Filtered by Host group, including Host in maintenance.
			[
				[
					'main_fields' => [
						'Name' => 'Hosts group with maintenance',
						'Host groups' => 'Top Hosts test host group',
						'Show hosts in maintenance' => true
					],
					'column_fields' => [
						[
							'Name' => 'Host',
							'Data' => 'Host name'
						],
						[
							'Name' => 'Maintenance Trapper',
							'Item name' => [
								'values' => 'Maintenance trapper',
								'context' => ['values' => 'Host in maintenance']
							],
							'Decimal places' => 4
						],
						[
							'Name' => 'Item1',
							'Item name' => [
								'values' => 'Item1',
								'context' => ['values' => 'HostA']
							]
						]
					],
					'result' => [
						['Host' => 'HostA', 'Maintenance Trapper' => '', 'Item1' => '0.00'],
						['Host' => 'HostB', 'Maintenance Trapper' => '', 'Item1' => '1.00'],
						['Host' => 'HostC', 'Maintenance Trapper' => '', 'Item1' => '2.00'],
						['Host' => 'Host in maintenance', 'Maintenance Trapper' => '100.0000', 'Item1' => '']
					],
					'headers' => ['Host', 'Maintenance Trapper', 'Item1'],
					'check_maintenance' => [
						'Host in maintenance' => "Maintenance for Top Hosts widget [Maintenance with data collection]\n".
								"Maintenance for icon check in Top Hosts widget"
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getCheckWidgetTableData
	 *
	 * @onBeforeOnce prepareTopHostsDisplayData
	 *
	 * @onAfter deleteWidgets
	 */
	public function testDashboardTopHostsWidget_CheckWidgetTable($data) {
		$dashboard = $this->createTopHostsWidget($data, static::$dashboardid);

		// Assert widget's table.
		$dashboard->getWidget($data['main_fields']['Name'])->waitUntilReady();
		$table = $this->query('class:list-table')->asTable()->one();

		if (empty($data['result'])) {
			$this->assertTableData();
		}
		else {
			$this->assertTableHasData($data['result']);
		}

		// Assert table headers depending on widget settings.
		$this->assertEquals($data['headers'], $table->getHeadersText());

		// Check maintenance icon and hint text.
		if (CTestArrayHelper::get($data, 'check_maintenance')) {
			foreach ($data['check_maintenance'] as $host => $hint_text) {
				$this->query('xpath://td/a[text()='.CXPathHelper::escapeQuotes($host).
						']/..//button[contains(@class,"wrench")]')->waitUntilClickable()->one()->click();
				$hint = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->waitUntilPresent();
				$this->assertEquals($hint_text, $hint->one()->getText());
				$hint->one()->query('xpath:.//button[@class="btn-overlay-close"]')->one()->click();
				$hint->waitUntilNotPresent();
			}
		}
	}
}
