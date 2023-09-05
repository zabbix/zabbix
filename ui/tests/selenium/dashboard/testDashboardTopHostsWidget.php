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
require_once dirname(__FILE__).'/../traits/TagTrait.php';
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

/**
 * @dataSource TopHostsWidget
 *
 * @backup widget, profiles
 *
 * @onAfter clearData
 */
class testDashboardTopHostsWidget extends CWebTest {

	use TagTrait;

	/**
	 * Widget name for update.
	 */
	private static $updated_name = 'Top hosts update';

	private function getCreateDashboardId() {
		return CDataHelper::get('TopHostsWidget.dashboardids.top_host_create');
	}

	/**
	 * SQL query to get widget and widget_field tables to compare hash values, but without widget_fieldid
	 * because it can change.
	 */
	private $sql = 'SELECT wf.widgetid, wf.type, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
			' wf.value_itemid, wf.value_graphid, wf.value_sysmapid, w.widgetid, w.dashboard_pageid, w.type, w.name, w.x, w.y,'.
			' w.width, w.height'.
			' FROM widget_field wf'.
			' INNER JOIN widget w'.
			' ON w.widgetid=wf.widgetid ORDER BY wf.widgetid, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
			' wf.value_itemid, wf.value_graphid';

	/**
	 * Attach MessageBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class
		];
	}

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

	public function testDashboardTopHostsWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$this->getCreateDashboardId());
		$dialog = CDashboardElement::find()->one()->edit()->addWidget();
		$form = $dialog->asForm();
		$this->assertEquals('Add widget', $dialog->getTitle());
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Top hosts')]);
		$this->assertEquals(['Type', 'Show header', 'Name', 'Refresh interval', 'Host groups', 'Hosts', 'Host tags',
				'Show hosts in maintenance', 'Columns', 'Order', 'Order column', 'Host count'], $form->getLabels()->asText()
		);
		$form->getRequiredLabels(['Columns', 'Order column', 'Host count']);

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
			'Host count' => ['value' => 10, 'maxlength' => 3]
		];
		$this->checkFieldsAttributes($fields, $form);

		// Check Columns table.
		$this->assertEquals(['', 'Name', 'Data', 'Action'],
				$form->getFieldContainer('Columns')->query('id:list_columns')->asTable()->one()->getHeadersText()
		);

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
		$column_form = $column_dialog->asForm();

		$this->assertEquals('New column', $column_dialog->getTitle());
		$this->assertEquals(['Name', 'Data', 'Text', 'Item', 'Time shift', 'Aggregation function', 'Aggregation interval',
				'Display', 'History data', 'Base color', 'Min', 'Max', 'Decimal places', 'Thresholds'],
				$column_form->getLabels()->asText()
		);
		$form->getRequiredLabels(['Name', 'Item', 'Aggregation interval']);

		$column_default_fields = [
			'Name' => ['value' => '', 'maxlength' => 255],
			'Data' => ['value' => 'Item value', 'options' => ['Item value', 'Host name', 'Text']],
			'Text' => ['value' => '', 'placeholder' => 'Text, supports {INVENTORY.*}, {HOST.*} macros','maxlength' => 255,
					'visible' => false, 'enabled' => false
			],
			'Aggregation function' => ['value' => 'none', 'options' => ['none', 'min', 'max', 'avg', 'count', 'sum',
					'first', 'last']
			],
			'Aggregation interval' => ['value' => '1h', 'maxlength' => 255, 'visible' => false, 'enabled' => false],
			'Item' => ['value' => ''],
			'Time shift' => ['value' => '', 'placeholder' => 'none', 'maxlength' => 255],
			'Display' => ['value' => 'As is', 'labels' => ['As is', 'Bar', 'Indicators']],
			'History data' => ['value' => 'Auto', 'labels' => ['Auto', 'History', 'Trends']],
			'xpath:.//input[@id="base_color"]/..' => ['color' => ''],
			'Min' => ['value' => '', 'placeholder' => 'calculated', 'maxlength' => 255, 'visible' => false, 'enabled' => false],
			'Max' => ['value' => '', 'placeholder' => 'calculated', 'maxlength' => 255, 'visible' => false, 'enabled' => false],
			'Decimal places' => ['value' => 2, 'maxlength' => 2] ,
			'Thresholds' => ['visible' => true]
		];
		$this->checkFieldsAttributes($column_default_fields, $column_form);

		foreach (['Host name', 'Text'] as $data) {
			$column_form->fill(['Data' => CFormElement::RELOADABLE_FILL($data)]);

			$column_default_fields['Data']['value'] = ($data === 'Host name') ? 'Host name' : 'Text';
			$column_default_fields['Text']['visible'] = $data === 'Text';
			$column_default_fields['Text']['enabled'] = $data === 'Text';
			$column_default_fields['Aggregation function']['visible'] = false;
			$column_default_fields['Aggregation function']['enabled'] = false;
			$column_default_fields['Item']['visible'] = false;
			$column_default_fields['Item']['enabled'] = false;
			$column_default_fields['Time shift']['visible'] = false;
			$column_default_fields['Time shift']['enabled'] = false;
			$column_default_fields['Display']['visible'] = false;
			$column_default_fields['Display']['enabled'] = false;
			$column_default_fields['History data']['visible'] = false;
			$column_default_fields['History data']['enabled'] = false;
			$column_default_fields['Min']['visible'] = false;
			$column_default_fields['Min']['enabled'] = false;
			$column_default_fields['Max']['visible'] = false;
			$column_default_fields['Max']['enabled'] = false;
			$column_default_fields['Decimal places']['visible'] = false;
			$column_default_fields['Decimal places']['enabled'] = false;
			$column_default_fields['Thresholds']['visible'] = false;
			$column_default_fields['Thresholds']['enabled'] = false;

			$this->checkFieldsAttributes($column_default_fields, $column_form);
		}

		// Check hintboxes.
		$column_form->fill(['Data' => CFormElement::RELOADABLE_FILL('Item value')]);

		// Adding those fields new info icons appear.
		$warning_visibility = [
			'Aggregation function' => ['none' => false, 'min' => true, 'max' => true, 'avg' => true, 'count' => true,
					'sum' => true, 'first' => true, 'last' => true
			],
			'Display' => ['As is' => false, 'Bar' => true, 'Indicators' => true],
			'History data' => ['Auto' => false, 'History' => false, 'Trends' => true]
		];

		// Check warning and hintbox message, as well as Aggregation interval, Min/Max and Thresholds fields visibility.
		foreach ($warning_visibility as $warning_label => $options) {
			$hint_text = ($warning_label === 'History data')
				? 'This setting applies only to numeric data. Non-numeric data will always be taken from history.'
				: 'With this setting only numeric items will be displayed in this column.';
			$warning_button = $column_form->getLabel($warning_label)->query('xpath:.//button[@data-hintbox]')->one();

			foreach ($options as $option => $visible) {
				$column_form->fill([$warning_label => $option]);
				$this->assertTrue($warning_button->isVisible($visible));

				if ($visible) {
					$warning_button->click();

					// Check hintbox text.
					$hint_dialog = $this->query('xpath://div[@class="overlay-dialogue"]')->one()->waitUntilVisible();
					$this->assertEquals($hint_text, $hint_dialog->getText());

					// Close the hintbox.
					$hint_dialog->query('xpath:.//button[@class="btn-overlay-close"]')->one()->click();
					$hint_dialog->waitUntilNotPresent();
				}

				// Together with hintbox there are some additional fields' dependency.
				if ($warning_label === 'Aggregation function') {
					$this->assertTrue($column_form->getField('Aggregation interval')->isVisible($visible));
				}

				if ($warning_label === 'Display') {
					foreach (['Min', 'Max'] as $field) {
						$this->assertTrue($column_form->getField($field)->isVisible($visible));
					}
				}
			}
		}

		// Check Thresholds table.
		$this->assertEquals(['', 'Threshold', 'Action'],
			$column_form->getFieldContainer('Thresholds')->query('id:thresholds_table')->asTable()->one()->getHeadersText()
		);

		$thresholds_icon = $column_form->getLabel('Thresholds')->query('xpath:.//button[@data-hintbox]')->one();
		$this->assertFalse($thresholds_icon->isVisible());
		$thresholds_container = $column_form->getFieldContainer('Thresholds');
		$thresholds_container->query('button:Add')->one()->waitUntilClickable()->click();

		$this->checkFieldsAttributes([
				'xpath:.//input[@id="thresholds_0_color"]/..' => ['color' => 'FF465C'],
				'id:thresholds_0_threshold' => ['value' => '', 'maxlength' => 255]
			], $column_form
		);
		$this->assertEquals(2, $thresholds_container->query('button', ['Add', 'Remove'])->all()
				->filter(CElementFilter::CLICKABLE)->count()
		);

		$thresholds_icon->click();
		$hint_dialog = $this->query('xpath://div[@class="overlay-dialogue"]')->one()->waitUntilVisible();
		$this->assertEquals('With this setting only numeric items will be displayed in this column.',
				$hint_dialog->getText()
		);
		$hint_dialog->query('xpath:.//button[@class="btn-overlay-close"]')->one()->click();
		$hint_dialog->waitUntilNotPresent();
	}

	/**
	 * Check fields attributes for given form.
	 *
	 * @param array         $data	provided data
	 * @param CFormElement  $form	form to be checked
	 */
	private function checkFieldsAttributes($data, $form) {
		foreach ($data as $label => $attributes) {
			$field = $form->getField($label);
			$this->assertTrue($field->isVisible(CTestArrayHelper::get($attributes, 'visible', true)));
			$this->assertTrue($field->isEnabled(CTestArrayHelper::get($attributes, 'enabled', true)));

			if (array_key_exists('value', $attributes)) {
				$this->assertEquals($attributes['value'], $field->getValue());
			}

			if (array_key_exists('maxlength', $attributes)) {
				$this->assertEquals($attributes['maxlength'], $field->getAttribute('maxlength'));
			}

			if (array_key_exists('placeholder', $attributes)) {
				$this->assertEquals($attributes['placeholder'], $field->getAttribute('placeholder'));
			}

			if (array_key_exists('labels', $attributes)) {
				$this->assertEquals($attributes['labels'], $field->asSegmentedRadio()->getLabels()->asText());
			}

			if (array_key_exists('options', $attributes)) {
				$this->assertEquals($attributes['options'], $field->asDropdown()->getOptions()->asText());
			}

			if (array_key_exists('color', $attributes)) {
				$this->assertEquals($attributes['color'], $form->query($label)->asColorPicker()->one()->getValue());
			}
		}
	}


	public static function getCreateData() {
		return [
			// #0 minimum needed values to create and submit widget.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [],
					'column_fields' => [
						[
							'Name' => 'Min values',
							'Data' => 'Item value',
							'Item' => 'Available memory'
						]
					]
				]
			],
			// #1 all fields filled for main form with all tags.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Name of Top hosts widget',
						'Refresh interval' => 'Default (1 minute)',
						'Host groups' => 'Zabbix servers',
						'Hosts' => 'ЗАББИКС Сервер',
						'Order' => 'Bottom N',
						'Host count' => '99'
					],
					'tags' => [
						['name' => 'value', 'value' => '12345', 'operator' => 'Contains'],
						['name' => '@#$%@', 'value' => 'a1b2c3d4', 'operator' => 'Equals'],
						['name' => 'AvF%21', 'operator' => 'Exists'],
						['name' => '_', 'operator' => 'Does not exist'],
						['name' => 'кириллица', 'value' => 'BcDa', 'operator' => 'Does not equal'],
						['name' => 'aaa6', 'value' => 'bbb6', 'operator' => 'Does not contain']
					],
					'column_fields' => [
						[
							'Name' => 'All fields',
							'Data' => 'Item value',
							'Item' => 'Available memory'
						]
					]
				]
			],
			// #2 change order column for several items.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Several item columns',
						'Order column' => '2 column'
					],
					'column_fields' => [
						[
							'Name' => '1 column',
							'Data' => 'Item value',
							'Item' => 'Available memory'
						],
						[
							'Name' => '2 column',
							'Data' => 'Item value',
							'Item' => 'Available memory in %'
						]
					]
				]
			],
			// #3 several item columns with different Aggregation function
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'All available aggregatino function'
					],
					'column_fields' => [
						[
							'Name' => 'Column 1',
							'Data' => 'Item value',
							'Name' => 'min',
							'Aggregation function' => 'min',
							'Aggregation interval' => '20s',
							'Item' => 'Available memory'
						],
						[
							'Name' => 'Column 2',
							'Data' => 'Item value',
							'Name' => 'max',
							'Aggregation function' => 'max',
							'Aggregation interval' => '20m',
							'Item' => 'Available memory'
						],
						[
							'Name' => 'Column 3',
							'Data' => 'Item value',
							'Name' => 'avg',
							'Aggregation function' => 'avg',
							'Aggregation interval' => '20h',
							'Item' => 'Available memory'
						],
						[
							'Name' => 'Column 4',
							'Data' => 'Item value',
							'Name' => 'count',
							'Aggregation function' => 'count',
							'Aggregation interval' => '20d',
							'Item' => 'Available memory'
						],
						[
							'Name' => 'Column 5',
							'Data' => 'Item value',
							'Name' => 'sum',
							'Aggregation function' => 'sum',
							'Aggregation interval' => '20w',
							'Item' => 'Available memory'
						],
						[
							'Name' => 'Column 6',
							'Data' => 'Item value',
							'Name' => 'first',
							'Aggregation function' => 'first',
							'Aggregation interval' => '20M',
							'Item' => 'Available memory'
						],
						[
							'Name' => 'Column 7',
							'Data' => 'Item value',
							'Name' => 'last',
							'Aggregation function' => 'last',
							'Aggregation interval' => '20y',
							'Item' => 'Available memory'
						]
					],
					'screenshot' => true
				]
			],
			// #4 several item columns with different display, time shift, min/max and history data.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Different display and history data fields'
					],
					'column_fields' => [
						[
							'Name' => 'Column_1',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'As is',
							'History data' => 'History',
							'Time shift' => '1'
						],
						[
							'Name' => 'Column_2',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'As is',
							'History data' => 'Trends'
						],
						[
							'Name' => 'Column_3',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'Bar',
							'History data' => 'Auto',
							'Min' => '2',
							'Max' => ''
						],
						[
							'Name' => 'Column_4',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'Bar',
							'History data' => 'History',
							'Min' => '',
							'Max' => '100'
						],
						[
							'Name' => 'Column_5',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'Bar',
							'History data' => 'Trends',
							'Min' => '50',
							'Max' => '100'
						],
						[
							'Name' => 'Column_6',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'Indicators',
							'History data' => 'Auto',
							'Min' => '2',
							'Max' => ''
						],
						[
							'Name' => 'Column_7',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'Indicators',
							'History data' => 'History',
							'Min' => '',
							'Max' => '100'
						],
						[
							'Name' => 'Column_7',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'Indicators',
							'History data' => 'Trends',
							'Min' => '50',
							'Max' => '100'
						]
					]
				]
			],
			// #5 add column with different Base color.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Another base color'
					],
					'column_fields' => [
						[
							'Name' => 'Column name',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Base color' => '039BE5'
						]
					]
				]
			],
			// #6 add column with Threshold without color change.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'One Threshold'
					],
					'column_fields' => [
						[
							'Name' => 'Column with threshold',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Thresholds' => [
								[
									'threshold' => '5'
								]
							]
						]
					]
				]
			],
			// #7 add several columns with Threshold without color change.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Several Threshold'
					],
					'column_fields' => [
						[
							'Name' => 'Column with some thresholds',
							'Data' => 'Item value',
							'Item' => 'Available memory',
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
			// #8 add several columns with Threshold with color change and without color.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Several Thresholds with colors'
					],
					'column_fields' => [
						[
							'Name' => 'Thresholds with colors',
							'Data' => 'Item value',
							'Item' => 'Available memory',
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
			// #9 add Host name columns.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Host name columns'
					],
					'column_fields' => [
						[
							'Data' => 'Host name',
							'Name' => 'This is host name',
							'Base color' => '039BE5'
						],
						[
							'Name' => 'Host name column 2',
							'Data' => 'Host name'
						],
						[
							'Name' => 'Host name column 3',
							'Data' => 'Item value',
							'Item' => 'Available memory'
						]
					]
				]
			],
			// #10 add Text columns.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Text columns'
					],
					'column_fields' => [
						[
							'Name' => 'Text column name 1',
							'Data' => 'Text',
							'Text' => 'Here is some text'
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
							'Base color' => '039BE5'
						],
						[
							'Name' => 'Text column name 4',
							'Data' => 'Item value',
							'Item' => 'Available memory'
						]
					]
				]
			],
			// #11 error message adding widget without any column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Widget without columns'
					],
					'main_error' => [
						'Invalid parameter "Columns": an array is expected.',
						'Invalid parameter "Order column": an integer is expected.'
					]
				]
			],
			// #12 error message adding widget without item column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
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
			// #13 add characters in host count field.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Host count error with item column',
						'Host count' => 'zzz'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item' => 'Available memory'
						]
					],
					'main_error' => [
						'Invalid parameter "Host count": value must be one of 1-100.'
					]
				]
			],
			// #14 add incorrect value to host count field without item column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Host count error without item column',
						'Host count' => '333'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Host name'
						]
					],
					'main_error' => [
						'Invalid parameter "Host count": value must be one of 1-100.'
					]
				]
			],
			// #15 color error in host name column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Color error in Host name column'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Host name',
							'Base color' => '!@#$%^'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/base_color": a hexadecimal color code (6 symbols) is expected.'
					]
				]
			],
			// #16 check error adding text column without any value.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
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
			// #17 color error in text column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Error in text column color'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Text',
							'Text' => 'Here is some text',
							'Base color' => '!@#$%^'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/base_color": a hexadecimal color code (6 symbols) is expected.'
					]
				]
			],
			// #18 error when there is no item in item column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Error without item in item column'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value'
						]
					],
					'column_error' => [
						'Invalid parameter "/1": the parameter "item" is missing.'
					]
				]
			],
			// #19 error when incorrect time shift added.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Incorrect time shift'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Time shift' => 'zzz'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/timeshift": a time unit is expected.'
					]
				]
			],
			// #20 error when 1M time shift added.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => '1M time shift'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Time shift' => '1M'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/timeshift": a time unit is expected.'
					]
				]
			],
			// #21 error when 1y time shift added.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => '1y time shift'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Time shift' => '1y'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/timeshift": a time unit is expected.'
					]
				]
			],
			// #22 error when incorrect aggregation interval added.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Incorrect aggregation interval'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Aggregation function' => 'count',
							'Aggregation interval' => 'zzz'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/aggregate_interval": a time unit is expected.'
					]
				]
			],
			// #23 error when empty aggregation function added.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Empty aggregation interval'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Aggregation function' => 'count',
							'Aggregation interval' => ''
						]
					],
					'column_error' => [
						'Invalid parameter "/1/aggregate_interval": cannot be empty.'
					]
				]
			],
			// #24 error when incorrect min value added.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Incorrect min value'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'Bar',
							'Min' => 'zzz'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/min": a number is expected.'
					]
				]
			],
			// #25 error when incorrect max value added.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Incorrect max value'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Display' => 'Bar',
							'Max' => 'zzz'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/max": a number is expected.'
					]
				]
			],
			// #26 color error in item column.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Error in item column color'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Base color' => '!@#$%^'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/base_color": a hexadecimal color code (6 symbols) is expected.'
					]
				]
			],
			// #27 color error when incorrect hexadecimal added in first threshold.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Error in item column threshold color'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Thresholds' => [
								[
									'threshold' => '1',
									'color' => '!@#$%^'
								]
							]
						]
					],
					'column_error' => [
						'Invalid parameter "/1/thresholds/1/color": a hexadecimal color code (6 symbols) is expected.'
					]
				]
			],
			// #28 color error when incorrect hexadecimal added in second threshold.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Error in item column second threshold color'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item' => 'Available memory',
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
						'Invalid parameter "/1/thresholds/2/color": a hexadecimal color code (6 symbols) is expected.'
					]
				]
			],
			// #29 error message when incorrect value added to threshold.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Name' => 'Error in item column second threshold color'
					],
					'column_fields' => [
						[
							'Name' => 'test name',
							'Data' => 'Item value',
							'Item' => 'Available memory',
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
			]
		];
	}

	/**
	 * Create Top Hosts widget.
	 *
	 * @dataProvider getCreateData
	 */
	public function testDashboardTopHostsWidget_Create($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$this->getCreateDashboardId());
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
			$this->setTagSelector('id:tags_table_tags');
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

		// Check error message in main widget form.
		if (array_key_exists('main_error', $data)) {
			$this->assertMessage(TEST_BAD, null, $data['main_error']);
		}

		if ($data['expected'] === TEST_GOOD) {
			// Make sure that the widget is present before saving the dashboard.
			$header = CTestArrayHelper::get($data['main_fields'], 'Name', 'Top hosts');
			$dashboard->getWidget($header);
			$dashboard->save();

			// Check message that widget added.
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Check widget amount that it is added.
			$this->assertEquals($old_widget_count + 1, $dashboard->getWidgets()->count());
			$this->checkWidget($header, $data, 'create');
		}
		else {
			COverlayDialogElement::find()->one()->close();
			$dashboard->save();

			// Check message that widget added.
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');
			$this->assertEquals($old_widget_count, $dashboard->getWidgets()->count());
		}
	}

	/**
	 * Top Hosts widget simple update without any field change.
	 */
	public function testDashboardTopHostsWidget_SimpleUpdate() {
		// Hash before simple update.
		$old_hash = CDBHelper::getHash($this->sql);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$this->getCreateDashboardId());
		$dashboard = CDashboardElement::find()->one();
		$dashboard->edit()->getWidget(self::$updated_name)->edit()->submit();
		$dashboard->save();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Compare old hash and new one.
		$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
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
							'Item' => 'Available memory',
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
						'Invalid parameter "/1/thresholds/1/color": a hexadecimal color code (6 symbols) is expected.'
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
							'Item' => 'Available memory',
							'Display' => 'Indicators',
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
							'Item' => 'Available memory',
							'Display' => 'Indicators',
							'History data' => 'Trends',
							'Max' => 'zzz'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/max": a number is expected.'
					]
				]
			],
			// #3 Incorrect threshold value.
			// TODO Uncomment when ZBXNEXT-7687 (14) is fixed.
//			[
//				[
//					'expected' => TEST_BAD,
//					'column_fields' => [
//						[
//							'Data' => 'Item value',
//							'Item' => 'Available memory',
//							'Thresholds' => [
//								[
//									'threshold' => '     '
//								]
//							]
//						]
//					],
//					'column_error' => [
//						'Invalid parameter "/1/thresholds/3/threshold": a number is expected.'
//					]
//				]
//			],
			// #4 Error message when update Host count incorrectly.
			[
				[
					'expected' => TEST_BAD,
					'main_fields' =>  [
						'Host count' => '0'
					],
					'main_error' => [
						'Invalid parameter "Host count": value must be one of 1-100.'
					]
				]
			],
			// #5 Time shift error in column.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Time shift' => 'zzz'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/timeshift": a time unit is expected.'
					]
				]
			],
			// #6 Time shift error in column when add 1M.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Time shift' => '1M'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/timeshift": a time unit is expected.'
					]
				]
			],
			// #7 Time shift error in column when add 1y.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Time shift' => '1y'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/timeshift": a time unit is expected.'
					]
				]
			],
			// #8 Aggregation interval error in column.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Aggregation function' => 'max',
							'Aggregation interval' => 'zzz'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/aggregate_interval": a time unit is expected.'
					]
				]
			],
			// #9 Empty aggregation interval error in column.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Aggregation function' => 'max',
							'Aggregation interval' => ''
						]
					],
					'column_error' => [
						'Invalid parameter "/1/aggregate_interval": cannot be empty.'
					]
				]
			],
			// #10 No item error in column.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => ''
						]
					],
					'column_error' => [
						'Invalid parameter "/1": the parameter "item" is missing.'
					]
				]
			],
			// #11 Incorrect base color.
			[
				[
					'expected' => TEST_BAD,
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Base color' => '#$%$@@'
						]
					],
					'column_error' => [
						'Invalid parameter "/1/base_color": a hexadecimal color code (6 symbols) is expected.'
					]
				]
			],
			// #12 Update all main fields.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Updated main fields',
						'Refresh interval' => '2 minutes',
						'Host groups' => 'Zabbix servers',
						'Hosts' => 'ЗАББИКС Сервер',
						'Order' => 'Bottom N',
						'Order column' => 'test update column 2',
						'Host count' => '2'
					]
				]
			],
			// #13 Update first item column to Text column and add some values.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Updated column type to text'
					],
					'column_fields' => [
						[
							'Name' => 'Text column changed',
							'Data' => 'Text',
							'Text' => 'some text',
							'Base color' => '039BE5'
						]
					]
				]
			],
			// #14 Update first column to Host name column and add some values.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Updated column type to host name'
					],
					'column_fields' => [
						[
							'Name' => 'Host name column update',
							'Data' => 'Host name',
							'Base color' => 'FF8F00'
						]
					]
				]
			],
			// #15 Update first column to Item column and check time suffix - seconds.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Time shift 10s'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Time shift' => '10s'
						]
					]
				]
			],
			// #16 Time suffix "minutes" is checked in this case.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Time shift 10m'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Time shift' => '10m'
						]
					]
				]
			],
			// #17 Time suffix "hours" is checked in this case.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Time shift 10h'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Time shift' => '10h'
						]
					]
				]
			],
			// #18 Time suffix "weeks" is checked in this case.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Time shift 10w'
					],
					'column_fields' => [
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Time shift' => '10w'
						]
					]
				]
			],
			// #19 Update item column adding new values and fields.
			[
				[
					'expected' => TEST_GOOD,
					'main_fields' =>  [
						'Name' => 'Updated values for item column'
					],
					'column_fields' => [
						[
							'Data' => 'Host name',
							'Name' => 'Only name changed'
						],
						[
							'Data' => 'Item value',
							'Item' => 'Available memory',
							'Time shift' => '1',
							'Display' => 'Indicators',
							'History data' => 'Trends',
							'Min' => '50',
							'Max' => '100',
							'Aggregation function' => 'avg',
							'Aggregation interval' => '20h',
							'Base color' => '039BE5',
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
						['name' => 'aaa6', 'value' => 'bbb6', 'operator' => 'Does not contain']
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
		if ($data['expected'] === TEST_BAD) {
			// Hash before update.
			$old_hash = CDBHelper::getHash($this->sql);
		}

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$this->getCreateDashboardId());
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->getWidget(self::$updated_name)->edit();

		// Update column.
		if (array_key_exists('column_fields', $data)) {
			$this->fillColumnForm($data, 'update');
		}

		if (array_key_exists('tags', $data)) {
			$this->setTagSelector('id:tags_table_tags');
			$this->setTags($data['tags']);
		}

		if (array_key_exists('main_fields', $data)) {
			$form->fill($data['main_fields']);
			$form->submit();
			$this->page->waitUntilReady();
		}

		// Check error message in main widget form.
		if (array_key_exists('main_error', $data)) {
			$this->assertMessage(TEST_BAD, null, $data['main_error']);
		}

		if ($data['expected'] === TEST_GOOD) {
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
		else {
			COverlayDialogElement::find()->one()->close();
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Compare old hash and new one.
			$this->assertEquals($old_hash, CDBHelper::getHash($this->sql));
		}
	}

	/**
	 * Delete top hosts widget.
	 */
	public function testDashboardTopHostsWidget_Delete() {
		$name = 'Top hosts delete';

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$this->getCreateDashboardId());
		$dashboard = CDashboardElement::find()->one()->edit();
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
			// #0 remove column.
			[
				[
					'table_id' => 'id:list_columns',
					'remove_selector' => 'xpath:(.//button[@name="remove"])[2]'
				]
			],
			// #1 remove tag.
			[
				[
					'table_id' => 'id:tags_table_tags',
					'remove_selector' => 'id:tags_0_remove'
				]
			],
			// #2 remove threshold.
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
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$this->getCreateDashboardId());
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
	}

	/**
	 * Check widget after update/creation.
	 *
	 * @param string $header		widget name
	 * @param array	 $data			values from dataprovider
	 * @param string $action		check after creation or update
	 */
	private function checkWidget($header, $data, $action) {
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
					$table_name = $values['Item'];
				}
				elseif ($values['Data'] === 'Host name') {
					$table_name = $values['Data'];
				}
				else {
					$table_name = $values['Text'];
				}
				$table->getRow($row_number - 1)->getColumnData('Data', $table_name);

				$form->query('xpath:(.//button[@name="edit"])['.$row_number.']')->one()->click();
				$column_form = COverlayDialogElement::find()->waitUntilReady()->asForm()->all()->last();
				$form_header = $this->query('xpath://div[@class="overlay-dialogue modal modal-popup"]//h4')->one()->getText();
				$this->assertEquals('Update column', $form_header);

				// Check Thresholds values.
				if (array_key_exists('Thresholds', $values)) {
					foreach($values['Thresholds'] as &$threshold) {
						unset($threshold['action'], $threshold['index']);
					}
					unset($threshold);

					$this->getTreshholdTable()->checkValue($values['Thresholds']);
					unset($values['Thresholds']);
				}

				$column_form->checkValue($values);
				$this->query('xpath:(//button[text()="Cancel"])[2]')->one()->click();

				// Check next row in a column table.
				if ($row_number < $row_amount) {
					$row_number++;
				}
			}
		}
	}

	/**
	 * Create or update top hosts widget.
	 *
	 * @param array  $data			values from dataprovider
	 * @param string $action		create or update action
	 */
	private function fillColumnForm($data, $action) {
		// Starting counting column amount from 1 for xpath.
		if ($action === 'update') {
			$column_count = 1;
		}

		$form = $this->query('id:widget-dialogue-form')->one()->asForm();
		foreach ($data['column_fields'] as $values) {
			// Open the Column configuration add or column update dialog depending on the action type.
			$selector = ($action === 'create') ? 'id:add' : 'xpath:(.//button[@name="edit"])['.$column_count.']';
			$form->query($selector)->waitUntilClickable()->one()->click();
			$column_form = COverlayDialogElement::find()->waitUntilReady()->asForm()->all()->last();

			// Fill Thresholds values.
			if (array_key_exists('Thresholds', $values)) {
				$this->getTreshholdTable()->fill($values['Thresholds']);
				unset($values['Thresholds']);
			}

			$column_form->fill($values);
			$column_form->submit();

			// Updating top host several columns, change it count number.
			if ($action === 'update') {
				$column_count++;
			}

			// Check error message in column form.
			if (array_key_exists('column_error', $data)) {
				$this->assertMessage(TEST_BAD, null, $data['column_error']);
				$selector = ($action === 'update') ? 'Update column' : 'New column';
				$this->query('xpath://div/h4[text()="'.$selector.'"]/../button[@title="Close"]')
						->one()->click();
			}

			$column_form->waitUntilNotVisible();
			COverlayDialogElement::find()->waitUntilReady()->one();
		}
	}

	public static function getBarScreenshotsData() {
		return [
			// #0 As is.
			[
				[
					'main_fields' =>  [
						'Name' => 'Simple'
					],
					'column_fields' => [
						[
							'Name' => 'test column 1',
							'Data' => 'Item value',
							'Item' => '1_item'
						]
					],
					'screen_name' => 'as_is'
				]
			],
			// #1 Bar.
			[
				[
					'main_fields' =>  [
						'Name' => 'Bar'
					],
					'column_fields' => [
						[
							'Name' => 'test column 1',
							'Data' => 'Item value',
							'Item' => '1_item',
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
					'main_fields' =>  [
						'Name' => 'Bar threshold'
					],
					'column_fields' => [
						[
							'Name' => 'test column 1',
							'Data' => 'Item value',
							'Item' => '1_item',
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
					'main_fields' =>  [
						'Name' => 'Indicators'
					],
					'column_fields' => [
						[
							'Name' => 'test column 1',
							'Data' => 'Item value',
							'Item' => '1_item',
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
					'main_fields' =>  [
						'Name' => 'Indicators threshold'
					],
					'column_fields' => [
						[
							'Name' => 'test column 1',
							'Data' => 'Item value',
							'Item' => '1_item',
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
			// #5 All 5 types visually.
			[
				[
					'main_fields' =>   [
						'Name' => 'All three'
					],
					'column_fields' => [
						[
							'Name' => 'test column 0',
							'Data' => 'Item value',
							'Item' => '1_item'
						],
						[
							'Name' => 'test column 1',
							'Data' => 'Item value',
							'Item' => '1_item',
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
							'Name' => 'test column 2',
							'Data' => 'Item value',
							'Item' => '1_item',
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
							'Name' => 'test column 3',
							'Data' => 'Item value',
							'Item' => '1_item',
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
							'Name' => 'test column 4',
							'Data' => 'Item value',
							'Item' => '1_item',
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
					'screen_name' => 'all_types'
				]
			]
		];
	}

	/**
	 * Check widget bars with screenshots.
	 *
	 * @dataProvider getBarScreenshotsData
	 */
	public function testDashboardTopHostsWidget_WidgetAppearance($data) {
		$this->createTopHostsWidget($data, 'top_host_screenshots');

		// Check widget added and assert screenshots.
		$element = CDashboardElement::find()->one()->getWidget($data['main_fields']['Name'])
				->query('class:list-table')->one();
		$this->assertScreenshot($element, $data['screen_name']);
	}

	public static function getCheckTextItemsData() {
		return [
			// #0 text item - value displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Text value item'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_text'
						]
					],
					'text' => "column1\nText for text item"
				]
			],
			// #1 text item, history data Trends - value displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Text trends history'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_text',
							'History data' => 'Trends'
						]
					],
					'text' => "column1\nText for text item"
				]
			],
			// #2 text item, display Bar - value not displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Text display bar'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_text',
							'Display' => 'Bar'
						]
					],
					'text' => "column1\nNo data found."
				]
			],
			// #3 text item, display Indicators - value not displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Text display indicators'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_text',
							'Display' => 'Indicators'
						]
					],
					'text' => "column1\nNo data found."
				]
			],
			// #4 text item, Aggregation function max - value not displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Text aggregation function'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_text',
							'Aggregation function' => 'max'
						]
					],
					'text' => "column1\nNo data found."
				]
			],
			// #5 text item, Threshold - value not displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Text threshold'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_text',
							'Thresholds' => [
								[
									'threshold' => '10'
								]
							]
						]
					],
					'text' => "column1\nNo data found."
				]
			],
			// #6 log item - value displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Log value item'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_log'
						]
					],
					'text' => "column1\nLogs for text item"
				]
			],
			// #7 log item, history data Trends - value displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Log trends history'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_log',
							'History data' => 'Trends'
						]
					],
					'text' => "column1\nLogs for text item"
				]
			],
			// #8 log item, display Bar - value not displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Log display bar'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_log',
							'Display' => 'Bar'
						]
					],
					'text' => "column1\nNo data found."
				]
			],
			// #9 log item, display Indicators - value not displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Log display indicators'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_log',
							'Display' => 'Indicators'
						]
					],
					'text' => "column1\nNo data found."
				]
			],
			// #10 log item, Aggregation function max - value not displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Log aggregation function'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_log',
							'Aggregation function' => 'max'
						]
					],
					'text' => "column1\nNo data found."
				]
			],
			// #11 log item, Threshold - value not displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Log threshold'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_log',
							'Thresholds' => [
								[
									'threshold' => '10'
								]
							]
						]
					],
					'text' => "column1\nNo data found."
				]
			],
			// #12 char item - value displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Char value item'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_char'
						]
					],
					'text' => "column1\ncharacters_here"
				]
			],
			// #13 char item, history data Trends - value displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Char trends history'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_char',
							'History data' => 'Trends'
						]
					],
					'text' => "column1\ncharacters_here"
				]
			],
			// #14 char item, display Bar - value not displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Char display bar'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_char',
							'Display' => 'Bar'
						]
					],
					'text' => "column1\nNo data found."
				]
			],
			// #15 char item, display Indicators - value not displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Char display indicators'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_char',
							'Display' => 'Indicators'
						]
					],
					'text' => "column1\nNo data found."
				]
			],
			// #16 char item, Aggregation function max - value not displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Char aggregation function'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_char',
							'Aggregation function' => 'max'
						]
					],
					'text' => "column1\nNo data found."
				]
			],
			// #17 char item, Threshold - value not displayed.
			[
				[
					'main_fields' =>  [
						'Name' => 'Char threshold'
					],
					'column_fields' => [
						[
							'Name' => 'column1',
							'Data' => 'Item value',
							'Item' => 'top_hosts_trap_char',
							'Thresholds' => [
								[
									'threshold' => '10'
								]
							]
						]
					],
					'text' => "column1\nNo data found."
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
		$this->createTopHostsWidget($data, 'top_host_text_items');

		// Check if value displayed in column table.
		$this->assertEquals($data['text'], CDashboardElement::find()->one()->getWidget($data['main_fields']['Name'])
				->getContent()->getText());
	}

	/**
	 * Function used to create Top Hosts widget with special columns for CheckTextItems and WidgetAppearance scenarios.
	 *
	 * @param array  $data	data provider values.
	 * @param string $name	name of the dashboard where to create Top Hosts widget.
	 */
	private function createTopHostsWidget($data, $name) {
		$dashboardid = CDataHelper::get('TopHostsWidget.dashboardids.'.$name);
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.$dashboardid);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->addWidget()->asForm();
		$form->fill(['Type' => 'Top hosts']);
		COverlayDialogElement::find()->waitUntilReady()->one();

		// Add new column and save widget.
		$this->fillColumnForm($data, 'create');
		$form->fill($data['main_fields'])->submit();
		COverlayDialogElement::ensureNotPresent();
		$dashboard->getWidget($data['main_fields']['Name'])->waitUntilReady();
		$dashboard->save();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
	}

	/**
	 * Delete all created data after test.
	 */
	public static function clearData() {
		$dashboardids = CDBHelper::getColumn("SELECT * from dashboard where name LIKE 'top_host_%'", 'dashboardid');
		CDataHelper::call('dashboard.delete', $dashboardids);

		$itemids = CDBHelper::getColumn("SELECT * FROM items WHERE name LIKE 'top_hosts_trap%'", 'itemid');
		CDataHelper::call('item.delete', $itemids);
	}
}
