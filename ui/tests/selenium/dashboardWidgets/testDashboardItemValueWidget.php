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


require_once dirname(__FILE__).'/../../include/CWebTest.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';
require_once dirname(__FILE__).'/../common/testWidgets.php';

/**
 * Test for checking Item Value Widget.
 *
 * @backup dashboard
 *
 * @dataSource WebScenarios, AllItemValueTypes, ItemValueWidget
 *
 * @onBefore prepareData
 */
class testDashboardItemValueWidget extends testWidgets {

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

	protected static $itemids;
	protected static $dashboardids;
	protected static $old_name = 'New widget';
	protected static $threshold_widget = 'Widget with thresholds';
	const DASHBOARD = 'Dashboard for Single Item value Widget test';
	const DASHBOARD_ZOOM = 'Dashboard for zoom filter check';
	const DASHBOARD_THRESHOLD = 'Dashboard for threshold(s) check';
	const DASHBOARD_AGGREGATION = 'Dashboard for aggregation function data check';
	const DATA_WIDGET = 'Widget for aggregation function data check';

	/**
	 * Get threshold table element with mapping set.
	 *
	 * @return CMultifieldTable
	 */
	protected function getThresholdTable() {
		return $this->query('id:thresholds-table')->asMultifieldTable([
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

	public static function prepareData() {
		self::$dashboardids = CDataHelper::get('ItemValueWidget.dashboardids');
		self::$itemids = CDataHelper::get('ItemValueWidget.itemids');
	}

	/**
	 * Test of the Item Value widget form fields layout.
	 */
	public function testDashboardItemValueWidget_FormLayout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardids[self::DASHBOARD])->waitUntilReady();
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$dialog = $dashboard->edit()->addWidget();
		$form = $dialog->asForm();
		$this->assertEquals('Add widget', $dialog->getTitle());
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Item value')]);

		// Check default values with default Advanced configuration (false).
		$default_values = [
			'Name' => '',
			'Refresh interval' => 'Default (1 minute)',
			'Item' => '',
			'Show header' => true,
			'id:show_1' => true, // Description.
			'id:show_2' => true, // Value.
			'id:show_3' => true, // Time.
			'id:show_4' => true, // Change indicator.
			'id:show_5' => false, // Sparkline.
			'Advanced configuration' => false,
			'id:override_hostid_ms' => ''
		];
		foreach ($default_values as $field => $value) {
			$this->assertEquals($value, $form->getField($field)->getValue());
		}

		// Show Sparkline fields.
		$form->fill(['id:show_5' => true]);

		// Check checkboxes dependency on Advanced configuration checkbox.
		$description = [
			'id:description',
			'id:desc_h_pos',
			'id:desc_v_pos',
			'id:desc_size',
			'id:desc_bold',
			'xpath:.//input[@id="desc_color"]/..'
		];

		$values = [
			'id:decimal_places',
			'id:decimal_size',
			'id:value_h_pos',
			'id:value_size',
			'id:value_v_pos',
			'id:value_bold',
			'xpath:.//input[@id="value_color"]/..'
		];

		$units = [
			'id:units',
			'id:units_pos',
			'id:units_size',
			'id:units_bold',
			'xpath:.//input[@id="units_color"]/..'
		];

		$time = [
			'id:time_h_pos',
			'id:time_v_pos',
			'id:time_size',
			'id:time_bold',
			'xpath:.//input[@id="time_color"]/..'
		];

		$indicator_colors = [
			'xpath:.//input[@id="up_color"]/..',
			'xpath:.//input[@id="down_color"]/..',
			'xpath:.//input[@id="updown_color"]/..'
		];

		$sparkline = [
			'id:sparkline_width',
			'id:sparkline_fill',
			'xpath:.//input[@id="sparkline_color"]/..',
			'id:sparkline_time_period_data_source',
			'id:sparkline_time_period_from',
			'id:sparkline_time_period_to',
			'id:sparkline_history'
		];

		// Merge all Advanced fields into one array.
		$fields = array_merge($description, $values, $units, $time, $indicator_colors, $sparkline, ['Background colour']);

		foreach ([false, true] as $advanced_config) {
			$form->fill(['Advanced configuration' => $advanced_config]);

			// Check that dynamic item checkbox is not depending on Advanced configuration checkbox state.
			$dynamic_field = $form->getField('Override host');
			$this->assertTrue($dynamic_field->isVisible());
			$this->assertTrue($dynamic_field->isEnabled());

			// Check fields visibility depending on Advanced configuration checkbox state.
			foreach ($fields as $field) {
				$this->assertTrue($form->getField($field)->isVisible($advanced_config));
			}

			// Check that reference widget multiselect is not visible by default.
			$this->assertFalse($form->query('xpath:.//div[@id="sparkline_time_period_reference"]')->one()->isDisplayed());

			// Check advanced fields when Advanced configuration is true.
			if ($advanced_config) {
				// Check hintbox.
				$form->getLabel('Description')->query('class:zi-help-filled-small')->one()->click();
				$hint = $this->query('xpath:.//div[@data-hintboxid]')->waitUntilPresent();

				// Assert text.
				$this->assertEquals("Supported macros:".
						"\n{HOST.*}".
						"\n{ITEM.*}".
						"\n{INVENTORY.*}".
						"\nUser macros", $hint->one()->getText());

				// Close the hint-box.
				$hint->one()->query('xpath:.//button[@class="btn-overlay-close"]')->one()->click();
				$hint->waitUntilNotPresent();

				// Check default values with Advanced configuration = true.
				$default_values_advanced = [
					'id:description' => '{ITEM.NAME}',
					'id:desc_h_pos' => 'Center',
					'id:desc_v_pos' => 'Bottom',
					'id:desc_size' => 15,
					'id:desc_bold' => false,
					'Decimal places' => 2,
					'id:decimal_size' => 35,
					'id:value_h_pos' => 'Center',
					'id:value_v_pos' => 'Middle',
					'id:value_size' => 45,
					'id:value_bold' => true,
					'id:units' => '',
					'Position' => 'After value',
					'id:units_size' => 35,
					'id:units_bold' => true,
					'id:time_h_pos' => 'Center',
					'id:time_v_pos' => 'Top',
					'id:time_size' => 15,
					'id:time_bold' => false,
					'id:sparkline_width' => 1,
					'id:sparkline_fill' => 3,
					'id:sparkline_color' => '42A5F5',
					'id:sparkline_time_period_data_source' => 'Custom',
					'id:sparkline_time_period_reference' => '',
					'id:sparkline_time_period_from' => 'now-1h',
					'id:sparkline_time_period_to' => 'now',
					'id:sparkline_history' => 'Auto',
					'Aggregation function' => 'not used',
					'Time period' => 'Dashboard',
					'id:time_period_reference' => '',
					'id:time_period_from' => 'now-1h',
					'id:time_period_to' => 'now',
					'History data' => 'Auto'
				];
				foreach ($default_values_advanced as $field => $value) {
					$this->assertEquals($value, $form->getField($field)->getValue());
				}

				foreach (['id:sparkline_width', 'id:sparkline_fill'] as $id) {
					$this->assertRangeSliderParameters($form, $id, ['min' => '0', 'max' => '10', 'step' => '1']);
				}

				// Aggregation fields are defined by label name and are visible only when aggregation function is selected.
				$radio_buttons = [
					'id:sparkline_time_period_data_source' => ['Dashboard', 'Widget', 'Custom'],
					'id:sparkline_history' => ['Auto', 'History', 'Trends'],
					'Time period' => ['Dashboard', 'Widget', 'Custom'],
					'History data' => ['Auto', 'History', 'Trends']
				];
				foreach ($radio_buttons as $locator => $labels) {
					$this->assertEquals($labels, $form->getField($locator)->getLabels()->asText());
				}

				// Check Thresholds table.
				$thresholds_container = $form->getFieldContainer('Thresholds');
				$this->assertEquals(['', 'Threshold', ''], $thresholds_container->asTable()->getHeadersText());
				$thresholds_icon = $form->getLabel('Thresholds')->query('xpath:.//button[@data-hintbox]')->one();
				$this->assertTrue($thresholds_icon->isVisible());
				$thresholds_container->query('button:Add')->one()->waitUntilClickable()->click();
				$this->assertEquals('', $form->getField('id:thresholds_0_threshold')->getValue());
				$this->assertEquals(2, $thresholds_container->query('button', ['Add', 'Remove'])->all()
						->filter(CElementFilter::CLICKABLE)->count()
				);

				// Check Thresholds warning icon text.
				$thresholds_icon->click();
				$hint_dialog = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->one()->waitUntilVisible();
				$this->assertEquals('This setting applies only to numeric data.', $hint_dialog->getText());
				$hint_dialog->query('xpath:.//button[@class="btn-overlay-close"]')->one()->click();
				$hint_dialog->waitUntilNotPresent();

				// Check required fields with selected widget time period.
				$form->fill(['Aggregation function' => 'min', 'id:sparkline_time_period_data_source' => 'Widget', 'Time period' => 'Widget']);
				$this->assertEquals(['Item', 'Show', 'Description', 'Widget'], $form->getRequiredLabels());

				// Check sparkline required field with selected widget time period.
				$this->assertTrue($form->query('xpath:.//label[@for="sparkline_time_period_reference_ms"]')
						->one()->hasClass('form-label-asterisk')
				);

				// Check warning and hintbox message.
				$warning_visibility = [
					'Aggregation function' => [
						'not used' => false,
						'min' => true,
						'max' => true,
						'avg' => true,
						'count' => false,
						'sum' => true,
						'first' => false,
						'last' => false
					],
					'History data' => [
						'Auto' => false,
						'History' => false,
						'Trends' => true
					]
				];

				foreach ($warning_visibility as $warning_label => $options) {
					$hint_text = ($warning_label === 'History data')
						? 'This setting applies only to numeric data. Non-numeric data will always be taken from history.'
						: 'With this setting only numeric items will be displayed.';
					$warning_button = $form->getLabel($warning_label)->query('xpath:.//button[@data-hintbox]')->one();

					foreach ($options as $option => $visible) {
						$form->fill([$warning_label => $option]);
						$this->assertTrue($warning_button->isVisible($visible));

						if ($visible) {
							$warning_button->click();

							// Check hintbox text.
							$hint_dialog = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->one()->waitUntilVisible();
							$this->assertEquals($hint_text, $hint_dialog->getText());

							// Close the hintbox.
							$hint_dialog->query('xpath:.//button[@class="btn-overlay-close"]')->one()->click();
							$hint_dialog->waitUntilNotPresent();
						}

						if ($warning_label === 'Aggregation function' && $option !== 'not used') {
							$this->assertTrue($form->getLabel('Time period')->isDisplayed());
						}
					}
				}

				// Check attributes.
				$inputs = [
					'Name' => [
						'maxlength' => 255,
						'placeholder' => 'default'
					],
					'id:itemid_ms' => [
						'placeholder' => 'type here to search'
					],
					'id:description' => [
						'maxlength' => 2048
					],
					'id:desc_size' => [
						'maxlength' => 3
					],
					'id:decimal_places' => [
						'maxlength' => 2
					],
					'id:decimal_size' => [
						'maxlength' => 3
					],
					'id:value_size' => [
						'maxlength' => 3
					],
					'id:units' => [
						'maxlength' => 255
					],
					'id:units_size' => [
						'maxlength' => 3
					],
					'id:time_size' => [
						'maxlength' => 3
					],
					'id:sparkline_width' => [
						'maxlength' => 2
					],
					'id:sparkline_fill' => [
						'maxlength' => 2
					],
					// Sparkline widget multiselect field relative xpath.
					'xpath:.//div[@id="sparkline_time_period_reference"]/input' => [
						'placeholder' => 'type here to search'
					],
					'id:sparkline_time_period_from' => [
						'maxlength' => 255,
						'placeholder' => 'YYYY-MM-DD hh:mm:ss'
					],
					'id:sparkline_time_period_to' => [
						'maxlength' => 255,
						'placeholder' => 'YYYY-MM-DD hh:mm:ss'
					],
					'id:thresholds_0_threshold' => [
						'maxlength' => 255
					],
					'xpath:.//input[@id="thresholds_0_color"]/..' => [
						'color' => 'E65660'
					],
					'id:time_period_from' => [
						'maxlength' => 255,
						'placeholder' => 'YYYY-MM-DD hh:mm:ss'
					],
					'id:time_period_to' => [
						'maxlength' => 255,
						'placeholder' => 'YYYY-MM-DD hh:mm:ss'
					],
					// Widget multiselect field relative xpath.
					'xpath:.//div[@id="time_period_reference"]/input' => [
						'placeholder' => 'type here to search'
					],
					'id:override_hostid_ms' => [
						'placeholder' => 'type here to search'
					]
				];
				foreach ($inputs as $field => $attributes) {
					foreach ($attributes as $attribute => $value) {
						if ($attribute === 'color') {
							$this->assertEquals($value, $form->query($field)->asColorPicker()->one()->getValue());
						}
						else {
							$this->assertEquals($value, $form->getField($field)->getAttribute($attribute));
						}
					}
				}

				// Check required fields with selected Custom time period.
				$form->fill(['Aggregation function' => 'min', 'id:sparkline_time_period_data_source' => 'Custom', 'Time period' => 'Custom']);
				$this->assertEquals(['Item', 'Show', 'Description', 'From', 'To'], $form->getRequiredLabels());

				foreach (['time_period_from', 'time_period_to'] as $element) {
					$this->assertTrue($form->query('xpath:.//label[@for="'.$element.'"]')->one()->hasClass('form-label-asterisk'));
				}

				// Check fields editability depending on "Show" checkboxes.
				$config_editability = [
					'id:show_1' => $description,
					'id:show_2' => $values,
					'id:units_show' => $units,
					'id:show_3' => $time,
					'id:show_4' => $indicator_colors,
					'id:show_5' => $sparkline
				];

				foreach ($config_editability as $config => $elements) {
					foreach ([false, true] as $state) {
						$form->fill([$config => $state]);

						foreach ($elements as $element) {
							$this->assertTrue($form->getField($element)->isEnabled($state));
						}
					}
				}

				// Check if footer buttons are present and clickable.
				$this->assertEquals(['Add', 'Cancel'], $dialog->getFooter()->query('button')->all()
						->filter(CElementFilter::CLICKABLE)->asText()
				);
			}
		}

		// Check Override host field.
		$override = $form->getField('Override host');
		$popup_menu = $override->query('xpath:.//button[contains(@class, "zi-chevron-down")]')->one();

		foreach ([$override->query('button:Select')->one(), $popup_menu] as $button) {
			$this->assertTrue($button->isClickable());
		}

		$menu = $popup_menu->asPopupButton()->getMenu();
		$this->assertEquals(['Widget', 'Dashboard'], $menu->getItems()->asText());
		$menu->select('Dashboard');
		$form->checkValue(['Override host' => 'Dashboard']);
		$this->assertTrue($override->query('xpath:.//span[@data-hintbox-contents="Dashboard is used as data source."]')
				->one()->isVisible()
		);

		$override->query('button:Select')->waitUntilCLickable()->one()->click();
		$dialogs = COverlayDialogElement::find()->all();
		$this->assertEquals('Widget', $dialogs->last()->getTitle());

		$dialog_count = $dialogs->count();
		for ($i = $dialog_count - 1; $i >= 0; $i--) {
			$dialogs->get($i)->close(true);
		}
	}

	/**
	 * Assert range input attributes.
	 *
	 * @param CFormElement $form               parent form
	 * @param string       $id                 id of the range input
	 * @param array        $expected_values    the attribute values expected
	 */
	protected function assertRangeSliderParameters($form, $id, $expected_values) {
		$range = $form->getField($id)->query('xpath://div/input[@type="range"]')->one();

		foreach ($expected_values as $attribute => $expected_value) {
			$this->assertEquals($expected_value, $range->getAttribute($attribute));
		}
	}

	public static function getWidgetData() {
		return [
			// #0.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Refresh interval' => '30 seconds'
					],
					'item' => [],
					'error' => ['Invalid parameter "Item": cannot be empty.']
				]
			],
			// #1.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'id:show_1' => false, // Description.
						'id:show_2' => false, // Value.
						'id:show_3' => false, // Time.
						'id:show_4' => false, // Change indicator.
						'id:show_5' => false // Sparkline.
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'error' => ['Invalid parameter "Show": at least one option must be selected.']
				]
			],
			// #2.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Advanced configuration' => true,
						// Description size in % relative to the size of the widget.
						'id:desc_size' => '0',
						// Value decimal part's size relative in %.
						'id:decimal_size' => '0',
						// Value size in % relative to the size of the widget.
						'id:value_size' => '0',
						// Value units size in % relative to the size of the widget.
						'id:units_size' => '0',
						// Time size in % relative to the size of the widget.
						'id:time_size' => '0'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'error' => [
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.'
					]
				]
			],
			// #3.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Advanced configuration' => true,
						// Description size in % relative to the size of the widget.
						'id:desc_size' => '101',
						// Value decimal part's size relative in %.
						'id:decimal_size' => '102',
						// Value size in % relative to the size of the widget.
						'id:value_size' => '103',
						// Value units size in % relative to the size of the widget.
						'id:units_size' => '104',
						// Time size in % relative to the size of the widget.
						'id:time_size' => '105'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'error' => [
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.'
					]
				]
			],
			// #4.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Advanced configuration' => true,
						// Description size in % relative to the size of the widget.
						'id:desc_size' => '-1',
						// Value decimal part's size relative in %.
						'id:decimal_size' => '-2',
						// Value size in % relative to the size of the widget.
						'id:value_size' => '-3',
						// Value units size in % relative to the size of the widget.
						'id:units_size' => '-4',
						// Time size in % relative to the size of the widget.
						'id:time_size' => '-5'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'error' => [
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.'
					]
				]
			],
			// #5.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Advanced configuration' => true,
						// Description size in % relative to the size of the widget.
						'id:desc_size' => 'aqua',
						// Value decimal part's size relative in %.
						'id:decimal_size' => 'один',
						// Value size in % relative to the size of the widget.
						'id:value_size' => 'some',
						// Value units size in % relative to the size of the widget.
						'id:units_size' => '@6$',
						// Time size in % relative to the size of the widget.
						'id:time_size' => '_+(*'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'error' => [
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.',
						'Invalid parameter "Size": value must be one of 1-100.'
					]
				]
			],
			// #6.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Advanced configuration' => true,
						'id:decimal_places' => '-1'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'error' => [
						'Invalid parameter "Decimal places": value must be one of 0-10.'
					]
				]
			],
			// #7.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Advanced configuration' => true,
						'id:decimal_places' => '99'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'error' => [
						'Invalid parameter "Decimal places": value must be one of 0-10.'
					]
				]
			],
			// #8.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Advanced configuration' => true,
						'id:description' => ''
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'error' => [
						'Invalid parameter "Description": cannot be empty.'
					]
				]
			],
			// #9.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Advanced configuration' => true
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'thresholds' => [
						['threshold' => '-']
					],
					'error' => [
						'Invalid parameter "Thresholds/1/threshold": a number is expected.'
					]
				]
			],
			// #10.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Advanced configuration' => true
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'thresholds' => [
						['threshold' => 'a']
					],
					'error' => [
						'Invalid parameter "Thresholds/1/threshold": a number is expected.'
					]
				]
			],
			// #11.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Advanced configuration' => true
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'thresholds' => [
						['threshold' => '1a%?']
					],
					'error' => [
						'Invalid parameter "Thresholds/1/threshold": a number is expected.'
					]
				]
			],
			// #12.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Advanced configuration' => true
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'thresholds' => [
						['threshold' => '1.79E+400']
					],
					'error' => [
						'Invalid parameter "Thresholds/1/threshold": a number is too large.'
					]
				]
			],
			// #13.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Advanced configuration' => true
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'thresholds' => [
						['threshold' => '1'],
						['threshold' => 'a']
					],
					'error' => [
						'Invalid parameter "Thresholds/2/threshold": a number is expected.'
					]
				]
			],
			// #14.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Advanced configuration' => true
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'thresholds' => [
						['threshold' => '1'],
						['threshold' => '1']
					],
					'error' => [
						'Invalid parameter "Thresholds/2": value (threshold)=(1) already exists.'
					]
				]
			],
			// #15.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Advanced configuration' => true
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'thresholds' => [
						['threshold' => '1', 'color' => '']
					],
					'error' => [
						'Invalid parameter "Thresholds/1/color": cannot be empty.'
					]
				]
			],
			// #16.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Advanced configuration' => true
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'thresholds' => [
						['threshold' => '1', 'color' => 'AABBCC'],
						['threshold' => '2', 'color' => '']
					],
					'error' => [
						'Invalid parameter "Thresholds/2/color": cannot be empty.'
					]
				]
			],
			// #17.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Advanced configuration' => true
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'thresholds' => [
						['threshold' => 'a', 'color' => 'AABBCC']
					],
					'error' => [
						'Invalid parameter "Thresholds/1/threshold": a number is expected.'
					]
				]
			],
			// #18.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'id:show_5' => true, // Sparkline.
						'Advanced configuration' => true,
						'id:sparkline_time_period_from' => 'now-58s',
						'Aggregation function' => 'min',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-58s'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'error' => [
						'Minimum time period to display is 1 minute.',
						'Minimum time period to display is 1 minute.'
					]
				]
			],
			// #19.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'id:show_5' => true,
						'Advanced configuration' => true,
						'id:sparkline_time_period_from' => 'now-63158401',
						'Aggregation function' => 'max',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-63158401' // 731 days and 1 second.
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'error' => [
						'Maximum time period to display is {days} days.',
						'Maximum time period to display is {days} days.'
					],
					'days_count' => true
				]
			],
			// #20.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'id:show_5' => true,
						'Advanced configuration' => true,
						'id:sparkline_time_period_from' => '',
						'Aggregation function' => 'avg',
						'Time period' => 'Custom',
						'id:time_period_from' => ''
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'error' => [
						'Invalid parameter "Sparkline: Time period/From": cannot be empty.',
						'Invalid parameter "Time period/From": cannot be empty.'
					]
				]
			],
			// #21.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'id:show_5' => true,
						'Advanced configuration' => true,
						'id:sparkline_time_period_from' => '!',
						'Aggregation function' => 'count',
						'Time period' => 'Custom',
						'id:time_period_from' => 'a'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'error' => [
						'Invalid parameter "Sparkline: Time period/From": a time is expected.',
						'Invalid parameter "Time period/From": a time is expected.'
					]
				]
			],
			// #22.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'id:show_5' => true,
						'Advanced configuration' => true,
						'id:sparkline_time_period_to' => 'now-59m-2s',
						'Aggregation function' => 'sum',
						'Time period' => 'Custom',
						'id:time_period_to' => 'now-59m-2s'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'error' => [
						'Minimum time period to display is 1 minute.',
						'Minimum time period to display is 1 minute.'
					]
				]
			],
			// #23.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'id:show_5' => true,
						'Advanced configuration' => true,
						'id:sparkline_time_period_from' => 'now-7y',
						'id:sparkline_time_period_to' => 'now-4y',
						'Aggregation function' => 'first',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-4y',
						'id:time_period_to' => 'now-1y'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'error' => [
						'Maximum time period to display is {days} days.',
						'Maximum time period to display is {days} days.'
					],
					'days_count' => true
				]
			],
			// #24.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'id:show_5' => true,
						'Advanced configuration' => true,
						'id:sparkline_time_period_to' => '',
						'Aggregation function' => 'last',
						'Time period' => 'Custom',
						'id:time_period_to' => ''
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'error' => [
						'Invalid parameter "Sparkline: Time period/To": cannot be empty.',
						'Invalid parameter "Time period/To": cannot be empty.'
					]
				]
			],
			// #25.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'id:show_5' => true,
						'Advanced configuration' => true,
						'id:sparkline_time_period_to' => '@',
						'Aggregation function' => 'min',
						'Time period' => 'Custom',
						'id:time_period_to' => 'b'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'error' => [
						'Invalid parameter "Sparkline: Time period/To": a time is expected.',
						'Invalid parameter "Time period/To": a time is expected.'
					]
				]
			],
			// #26.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'id:show_5' => true,
						'Advanced configuration' => true,
						'id:sparkline_time_period_from' => '🐙',
						'id:sparkline_time_period_to' => '🐙',
						'Aggregation function' => 'max',
						'Time period' => 'Custom',
						'id:time_period_from' => 'b',
						'id:time_period_to' => 'b'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'error' => [
						'Invalid parameter "Sparkline: Time period/From": a time is expected.',
						'Invalid parameter "Sparkline: Time period/To": a time is expected.',
						'Invalid parameter "Time period/From": a time is expected.',
						'Invalid parameter "Time period/To": a time is expected.'
					]
				]
			],
			// #27.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'id:show_5' => true,
						'Advanced configuration' => true,
						'id:sparkline_time_period_from' => '',
						'id:sparkline_time_period_to' => '',
						'Aggregation function' => 'avg',
						'Time period' => 'Custom',
						'id:time_period_from' => '',
						'id:time_period_to' => ''
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'error' => [
						'Invalid parameter "Sparkline: Time period/From": cannot be empty.',
						'Invalid parameter "Sparkline: Time period/To": cannot be empty.',
						'Invalid parameter "Time period/From": cannot be empty.',
						'Invalid parameter "Time period/To": cannot be empty.'
					]
				]
			],
			// #28.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'id:show_5' => true,
						'Advanced configuration' => true,
						'id:sparkline_time_period_data_source' => 'Widget',
						'Aggregation function' => 'min',
						'Time period' => 'Widget'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'error' => [
						'Invalid parameter "Sparkline: Time period/Widget": cannot be empty.',
						'Invalid parameter "Time period/Widget": cannot be empty.'
					]
				]
			],
			// #29.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'id:show_5' => true,
						'Advanced configuration' => true,
						'xpath:.//input[@id="sparkline_color"]/..' => 'FFFFFG'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'error' => [
						'Invalid parameter "Sparkline: Colour": a hexadecimal colour code (6 symbols) is expected.'
					]
				]
			],
			// #30.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'id:show_5' => true,
						'Advanced configuration' => true,
						'xpath:.//input[@id="sparkline_color"]/..' => ''
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'error' => [
						'Invalid parameter "Sparkline: Colour": cannot be empty.'
					]
				]
			],
			// #31.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory'
					]
				]
			],
			// #32.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Any name',
						'Refresh interval' => 'No refresh'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory'
					]
				]
			],
			// #33.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Имя виджета',
						'Refresh interval' => '10 seconds',
						// Description checkbox.
						'id:show_1' => true,
						// Value checkbox.
						'id:show_2' => false,
						// Time checkbox.
						'id:show_3' => true,
						// Change indicator checkbox.
						'id:show_4' => false,
						// Sparkline checkbox.
						'id:show_5' => true,
						'Advanced configuration' => true,
						'id:description' => 'Несколько слов. Dāži vārdi.',
						// Description horizontal position.
						'id:desc_h_pos' => 'Right',
						// Description vertical position.
						'id:desc_v_pos' => 'Bottom',
						// Description size in % relative to the size of the widget.
						'id:desc_size' => '1',
						// Time horizontal position.
						'id:time_h_pos' => 'Right',
						// Time vertical position.
						'id:time_v_pos' => 'Middle',
						// Time size in % relative to the size of the widget.
						'id:time_size' => '21'
					],
					'item' => [
						'Test item host' => 'Master item'
					]
				]
			],
			// #34.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show header' => false,
						'Name' => '#$%^&*()!@{}[]<>,.|',
						'Refresh interval' => '10 minutes',
						// Description checkbox.
						'id:show_1' => false,
						// Value checkbox.
						'id:show_2' => true,
						// Time checkbox.
						'id:show_3' => false,
						// Change indicator checkbox.
						'id:show_4' => true,
						// Sparkline checkbox.
						'id:show_5' => false,
						'Advanced configuration' => true,
						// Value units type.
						'id:units' => 'Some Units',
						// Value units position.
						'id:units_pos' => 'Below value',
						// Value units size in % relative to the size of the widget.
						'id:units_size' => '100',
						'id:units_bold' => true
					],
					'item' => [
						'Simple form test host' => 'Response code for step "step 1 of scenario 1" of scenario "Scenario for Update".'
					]
				]
			],
			// #35.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'New Single Item Widget',
						'Refresh interval' => 'Default (1 minute)',
						// Description checkbox.
						'id:show_1' => true,
						// Value checkbox.
						'id:show_2' => true,
						// Time checkbox.
						'id:show_3' => true,
						// Change indicator checkbox.
						'id:show_4' => true,
						// Sparkline checkbox.
						'id:show_5' => false,
						'Advanced configuration' => true,
						'id:description' => 'Some description here.',
						// Description horizontal position.
						'id:desc_h_pos' => 'Left',
						// Description vertical position.
						'id:desc_v_pos' => 'Top',
						// Description size in % relative to the size of the widget.
						'id:desc_size' => '11',
						// Description bold font checkbox.
						'id:desc_bold' => true,
						// Value decimal places count.
						'id:decimal_places' => '3',
						// Value horizontal position.
						'id:value_h_pos' => 'Right',
						// Value vertical position.
						'id:value_v_pos' => 'Bottom',
						// Value decimal part's size relative in %.
						'id:decimal_size' => '32',
						// Value size in % relative to the size of the widget.
						'id:value_size' => '46',
						'id:value_bold' => true,
						'id:units' => 's',
						// Value units position.
						'id:units_pos' => 'Before value',
						// Value units size in % relative to the size of the widget.
						'id:units_size' => '36',
						'id:units_bold' => true,
						// Time horizontal position.
						'id:time_h_pos' => 'Left',
						// Time vertical position.
						'id:time_v_pos' => 'Bottom',
						// Time size in % relative to the size of the widget.
						'id:time_size' => '13',
						'id:time_bold' => true,
						'Override host' => 'Dashboard'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory'
					]
				]
			],
			// #36.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Show header' => false,
						'Name' => 'Color pick',
						'Refresh interval' => '10 minutes',
						// Description checkbox.
						'id:show_1' => true,
						// Value checkbox.
						'id:show_2' => true,
						// Time checkbox.
						'id:show_3' => true,
						// Change indicator checkbox.
						'id:show_4' => true,
						// Sparkline checkbox.
						'id:show_5' => true,
						'Advanced configuration' => true,
						'id:units' => 'B',
						// Value units position.
						'id:units_pos' => 'Below value',
						// Value units size in % relative to the size of the widget.
						'id:units_size' => '99',
						'id:units_bold' => true,
						'Background colour' => 'FFAAAA',
						'xpath:.//input[@id="desc_color"]/..' => 'AABBCC',
						'xpath:.//input[@id="value_color"]/..' => 'CC11CC',
						'xpath:.//input[@id="units_color"]/..' => 'BBCC55',
						'xpath:.//input[@id="time_color"]/..' => '11AA00',
						'xpath:.//input[@id="up_color"]/..' => '00FF00',
						'xpath:.//input[@id="down_color"]/..' => 'FF0000',
						'xpath:.//input[@id="sparkline_color"]/..' => 'AB47BC'
					],
					'item' => [
						'Simple form test host' => 'Response code for step "step 1 of scenario 1" of scenario "Template_Web_scenario".'
					]
				]
			],
			// #37.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Item Widget with threshold',
						'Refresh interval' => '1 minute',
						'Advanced configuration' => true
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'thresholds' => [
						['threshold' => '0.01']
					]
				]
			],
			// #38.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'One threshold with color',
						'Refresh interval' => '2 minutes',
						'Advanced configuration' => true
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'thresholds' => [
						['color' => 'EF6C00', 'threshold' => '0.02']
					]
				]
			],
			// #39.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Thresholds',
						'Refresh interval' => '10 minutes',
						'Advanced configuration' => true
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'thresholds' => [
						['threshold' => ' 0.9999 '],
						['color' => 'AABBCC', 'threshold' => ' 1 '],
						['threshold' => ' 5K '],
						['color' => 'FFEB3B', 'threshold' => ' 1G '],
						['threshold' => ' 999999999999999 ']
					],
					'trim' => true
				]
			],
			// #40.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Aggregation function "min"',
						'Refresh interval' => '15 minutes',
						'Advanced configuration' => true,
						'Aggregation function' => 'min'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					]
				]
			],
			// #41.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Aggregation function "max" with custom "From"',
						'Refresh interval' => '1 minute',
						'Advanced configuration' => true,
						'Aggregation function' => 'max',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-1h-30m'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					]
				]
			],
			// #42.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Aggregation function "avg" with custom time period',
						'Advanced configuration' => true,
						'Aggregation function' => 'avg',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-2d/d',
						'id:time_period_to' => 'now-2d/d'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					]
				]
			],
			// #43.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Aggregation function setup',
						'Advanced configuration' => true,
						'Aggregation function' => 'count',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-2h',
						'id:time_period_to' => 'now-1h',
						'History data' => 'History'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					]
				]
			],
			// #44.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Aggregation function setup 2',
						'Advanced configuration' => true,
						'Aggregation function' => 'sum',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-5400',
						'id:time_period_to' => 'now-1800',
						'History data' => 'Trends'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					]
				]
			],
			// #45.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Aggregation function setup 3',
						'Advanced configuration' => true,
						'Aggregation function' => 'first',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-1M',
						'id:time_period_to' => 'now-1w',
						'History data' => 'Auto'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					]
				]
			],
			// #46.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Aggregation function setup 4',
						'Advanced configuration' => true,
						'Aggregation function' => 'last',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-2y',
						'id:time_period_to' => 'now-1y'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					]
				]
			],
			// #47.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Aggregation function with "Widget" time period',
						'Advanced configuration' => true,
						'Aggregation function' => 'min',
						'Time period' => 'Widget',
						'Widget' => 'Graph (classic) for time period'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					]
				]
			],
			// #48.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Aggregation function with "Dashboard" time period',
						'Advanced configuration' => true,
						'Aggregation function' => 'max',
						'Time period' => 'Dashboard'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					]
				]
			],
			// #49.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Sparkline custom width and fill',
						'id:show_5' => true, // Sparkline.
						'Advanced configuration' => true,
						'id:sparkline_width' => '10',
						'id:sparkline_fill' => '10'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					]
				]
			],
			// #50.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Sparkline custom width and fill 2',
						'id:show_5' => true, // Sparkline.
						'Advanced configuration' => true,
						'id:sparkline_width' => '0',
						'id:sparkline_fill' => '0'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					]
				]
			],
			// #51.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Sparkline setup',
						'id:show_5' => true,
						'Advanced configuration' => true,
						'id:sparkline_time_period_data_source' => 'Custom', // Sparkline time period.
						'id:sparkline_time_period_from' => 'now-2h',
						'id:sparkline_time_period_to' => 'now-1h',
						'id:sparkline_history' => 'History' // Sparkline history data.
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					]
				]
			],
			// #52.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Sparkline setup 2',
						'id:show_5' => true,
						'Advanced configuration' => true,
						'id:sparkline_time_period_data_source' => 'Custom',
						'id:sparkline_time_period_from' => 'now-7200',
						'id:sparkline_time_period_to' => 'now-2400',
						'id:sparkline_history' => 'Trends'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					]
				]
			],
			// #53.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Sparkline setup 3',
						'id:show_5' => true,
						'Advanced configuration' => true,
						'id:sparkline_time_period_data_source' => 'Custom',
						'id:sparkline_time_period_from' => 'now-3M',
						'id:sparkline_time_period_to' => 'now-2M',
						'id:sparkline_history' => 'Auto'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					]
				]
			],
			// #54.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Sparkline with "Dashboard" time period',
						'id:show_5' => true,
						'Advanced configuration' => true,
						'id:sparkline_time_period_data_source' => 'Dashboard'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					]
				]
			],
			// #55.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Sparkline with "Widget" time period',
						'id:show_5' => true,
						'Advanced configuration' => true,
						'id:sparkline_time_period_data_source' => 'Widget',
						'xpath:.//div[@id="sparkline_time_period_reference"]/..' => 'Graph (classic) for time period'
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					]
				]
			],
			// #56.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => ' Test trailing spaces ',
						'id:show_5' => true,
						'Advanced configuration' => true,
						'id:description' => ' {ITEM.NAME} ',
						'id:desc_size' => ' 1 ',
						'Decimal places' => ' 1',
						'id:decimal_size' => ' 1 ',
						'id:value_size' => ' 1 ',
						'id:units' => ' s ',
						'id:units_size' => ' 1 ',
						'id:time_size' => ' 1 ',
						'id:sparkline_width' => ' 5',
						'id:sparkline_fill' => ' 7',
						'id:sparkline_time_period_data_source' => 'Custom',
						'id:sparkline_time_period_from' => ' now-2y ',
						'id:sparkline_time_period_to' => ' now-1y ',
						'Aggregation function' => 'min',
						'Time period' => 'Custom',
						'id:time_period_from' => ' now-2w ',
						'id:time_period_to' => ' now-1w '
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'trim' => true
				]
			]
		];
	}

	/**
	 * @backupOnce dashboard
	 *
	 * @dataProvider getWidgetData
	 */
	public function testDashboardItemValueWidget_Create($data) {
		$this->checkWidgetForm($data);
	}

	public static function getWidgetUpdateData() {
		return [
			// #57.
			[
				[
					'expected' => TEST_GOOD,
					'threshold_widget' => true,
					'fields' => [
						'Name' => 'Widget with thresholds - update',
						'Refresh interval' => '10 minutes',
						'Advanced configuration' => true
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'thresholds' => [
						['action' => USER_ACTION_UPDATE, 'index' => 0, 'color' => 'AABBCC', 'threshold' => '1'],
						['action' => USER_ACTION_UPDATE, 'index' => 1, 'threshold' => '999999999999999']
					]
				]
			],
			// #58.
			[
				[
					'expected' => TEST_GOOD,
					'threshold_widget' => true,
					'fields' => [
						'Name' => 'Widget with thresholds - remove',
						'Refresh interval' => '10 minutes',
						'Advanced configuration' => true
					],
					'item' => [
						'ЗАББИКС Сервер' => 'Available memory in %'
					],
					'thresholds' => [
						['action' => USER_ACTION_REMOVE, 'index' => 0],
						['action' => USER_ACTION_REMOVE, 'index' => 0]
					]
				]
			]
		];
	}

	/**
	 * @dataProvider getWidgetData
	 * @dataProvider getWidgetUpdateData
	 */
	public function testDashboardItemValueWidget_Update($data) {
		$this->checkWidgetForm($data, true);
	}

	/**
	 * Function for check the changes.
	 *
	 * @param boolean $update	updating is performed
	 */
	public function checkWidgetForm($data, $update = false) {
		$expected = CTestArrayHelper::get($data, 'expected', TEST_GOOD);
		if ($expected === TEST_BAD) {
			$old_hash = CDBHelper::getHash(self::SQL);
		}

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardids[self::DASHBOARD])->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		$name = ($update && array_key_exists('threshold_widget', $data)) ? self::$threshold_widget : self::$old_name;
		$form = ($update)
			? $dashboard->getWidget($name)->edit()->asForm()
			: $dashboard->edit()->addWidget()->asForm();

		COverlayDialogElement::find()->one()->waitUntilReady();
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Item value')]);
		$data['fields']['Item'][] = implode(array_values($data['item']));
		$form->fill($data['fields']);

		if ($update && !CTestArrayHelper::get($data['fields'], 'Name')) {
			$form->fill(['Name' => '']);
		}

		if (array_key_exists('thresholds', $data)) {
			$this->getThresholdTable()->fill($data['thresholds']);
		}

		if ($expected === TEST_GOOD) {
			$values = $form->getFields()->filter(CElementFilter::VISIBLE)->asValues();
		}

		$form->submit();

		if (array_key_exists('trim', $data)) {
			$data = CTestArrayHelper::trim($data);
		}

		if ($expected === TEST_BAD) {
			// Count of days mentioned in error depends ot presence of leap year february in selected period.
			if (CTestArrayHelper::get($data, 'days_count')) {
				$data['error'] = str_replace('{days}', CDateTimeHelper::countDays('now', 'P2Y'), $data['error']);
			}

			$this->assertMessage($data['expected'], null, $data['error']);
			$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));

			COverlayDialogElement::find()->one()->close();
		}
		else {
			COverlayDialogElement::ensureNotPresent();

			// Prepare data to check widget "Item" field, should be in the format "Host name: Item name".
			$data['fields']['Item'] = [];
			foreach ($data['item'] as $host => $item) {
				$data['fields']['Item'][] = $host.': '.$item;
			}

			$header = CTestArrayHelper::get($data['fields'], 'Name')
				? $data['fields']['Name']
				: implode($data['fields']['Item']);

			$widget = $dashboard->getWidget($header);

			// Save Dashboard to ensure that widget is correctly saved.
			$dashboard->save()->waitUntilReady();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Check widget count.
			$this->assertEquals($old_widget_count + ($update ? 0 : 1), $dashboard->getWidgets()->count());

			// Check new widget update interval.
			$refresh = (CTestArrayHelper::get($data['fields'], 'Refresh interval') === 'Default (1 minute)')
				? '1 minute'
				: (CTestArrayHelper::get($data['fields'], 'Refresh interval', '1 minute'));
			$this->assertEquals($refresh, $widget->getRefreshInterval());

			// Check new widget form fields and values in frontend.
			$saved_form = $widget->edit();

			// Open "Advanced configuration" block if it was filled with data.
			if (CTestArrayHelper::get($data, 'fields.Advanced configuration', false)) {
				// After form submit "Advanced configuration" is closed.
				$saved_form->checkValue(['Advanced configuration' => false]);
				$saved_form->fill(['Advanced configuration' => true]);
			}

			$this->assertEquals($values, $saved_form->getFields()->filter(CElementFilter::VISIBLE)->asValues());
			$saved_form->checkValue($data['fields']);

			// Check that widget is saved in DB for correct dashboard and correct dashboard page.
			$this->assertEquals(1,
					CDBHelper::getCount('SELECT * FROM widget w'.
						' WHERE EXISTS ('.
							'SELECT NULL'.
							' FROM dashboard_page dp'.
							' WHERE w.dashboard_pageid=dp.dashboard_pageid'.
								' AND dp.dashboardid='.self::$dashboardids[self::DASHBOARD].
								' AND w.name ='.zbx_dbstr(CTestArrayHelper::get($data['fields'], 'Name', '')).')'
			));

			// Check that original widget was not left in DB.
			if ($update) {
				$this->assertEquals(0, CDBHelper::getCount('SELECT NULL FROM widget WHERE name='.zbx_dbstr($name)));
			}

			// Close widget window and cancel editing the dashboard.
			COverlayDialogElement::find()->one()->close();
			$dashboard->cancelEditing();

			// Write new name to update widget for update scenario.
			if ($update) {
				if (array_key_exists('threshold_widget', $data)) {
					self::$threshold_widget = $header;
				}
				else {
					self::$old_name = $header;
				}
			}
		}
	}

	public function testDashboardItemValueWidget_SimpleUpdate() {
		$this->checkNoChanges();
	}

	public static function getCancelData() {
		return [
			// Cancel creating widget with saving the dashboard.
			[
				[
					'cancel_form' => true,
					'create_widget' => true,
					'save_dashboard' => true
				]
			],
			// Cancel updating widget with saving the dashboard.
			[
				[
					'cancel_form' => true,
					'create_widget' => false,
					'save_dashboard' => true
				]
			],
			// Create widget without saving the dashboard.
			[
				[
					'cancel_form' => false,
					'create_widget' => true,
					'save_dashboard' => false
				]
			],
			// Update widget without saving the dashboard.
			[
				[
					'cancel_form' => false,
					'create_widget' => false,
					'save_dashboard' => false
				]
			]
		];
	}

	/**
	 * @dataProvider getCancelData
	 */
	public function testDashboardItemValueWidget_Cancel($data) {
		$this->checkNoChanges($data['cancel_form'], $data['create_widget'], $data['save_dashboard']);
	}

	/**
	 * Function for checking canceling form or submitting without any changes.
	 *
	 * @param boolean $cancel            true if cancel scenario, false if form is submitted
	 * @param boolean $create            true if create scenario, false if update
	 * @param boolean $save_dashboard    true if dashboard will be saved, false if not
	 */
	private function checkNoChanges($cancel = false, $create = false, $save_dashboard = true) {
		$old_hash = CDBHelper::getHash(self::SQL);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardids[self::DASHBOARD]);
		$dashboard = CDashboardElement::find()->one();
		$old_widget_count = $dashboard->getWidgets()->count();

		$form = $create
			? $dashboard->edit()->addWidget()->asForm()
			: $dashboard->getWidget(self::$old_name)->edit();

		$dialog = COverlayDialogElement::find()->one()->waitUntilReady();

		if (!$create) {
			$values = $form->getFields()->filter(CElementFilter::VISIBLE)->asValues();
		}
		else {
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Item value')]);
		}

		if ($cancel || !$save_dashboard) {
			$form->fill([
				'Item' => 'Available memory in %',
				'Name' => 'Widget to cancel'
			]);
		}

		if ($cancel) {
			$dialog->query('button:Cancel')->one()->click();
		}
		else {
			$form->submit();
		}

		COverlayDialogElement::ensureNotPresent();

		if (!$cancel) {
			$dashboard->waitUntilReady()->getWidget(!$save_dashboard ? 'Widget to cancel' : self::$old_name)->waitUntilReady();
		}

		if ($save_dashboard) {
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		}
		else {
			$dashboard->cancelEditing();
		}

		$this->assertEquals($old_widget_count, $dashboard->getWidgets()->count());

		// Check that updating widget form values did not change in frontend.
		if (!$create && !$save_dashboard) {
			$this->assertEquals($values, $dashboard->getWidget(self::$old_name)->edit()->getFields()
					->filter(CElementFilter::VISIBLE)->asValues()
			);

			COverlayDialogElement::find()->one()->close();
		}

		// Check that DB hash is not changed.
		$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
	}

	public function testDashboardItemValueWidget_Delete() {
		$name = 'Widget to delete';
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardids[self::DASHBOARD]);
		$dashboard = CDashboardElement::find()->one()->edit();
		$old_widget_count = $dashboard->getWidgets()->count();
		$this->assertEquals(true, $dashboard->getWidget($name)->isEditable());
		$dashboard->deleteWidget($name);
		$dashboard->save();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$this->assertEquals($old_widget_count - 1, $dashboard->getWidgets()->count());
		$this->assertEquals('', CDBHelper::getRow('SELECT * from widget WHERE name = '.zbx_dbstr('Widget to delete')));
	}

	public static function getWarningMessageData() {
		return [
			[
				[
					'numeric' => false,
					'fields' => [
						'Item' => 'System description',
						'Name' => 'Item Widget with type of information - characters',
						'Advanced configuration' => true
					],
					'selector' => 'id:item-thresholds-warning',
					'label' => 'Thresholds',
					'warning_message' => 'This setting applies only to numeric data.'
				]
			],
			[
				[
					'numeric' => false,
					'fields' => [
						'Item' => 'Get filesystems',
						'Name' => 'Item Widget with type of information - text',
						'Advanced configuration' => true
					],
					'selector' => 'id:item-thresholds-warning',
					'label' => 'Thresholds',
					'warning_message' => 'This setting applies only to numeric data.'
				]
			],
			[
				[
					'numeric' => false,
					'fields' => [
						'Item' => 'item_testPageHistory_CheckLayout_Log',
						'Name' => 'Item Widget with type of information - log',
						'Advanced configuration' => true
					],
					'selector' => 'id:item-thresholds-warning',
					'label' => 'Thresholds',
					'warning_message' => 'This setting applies only to numeric data.'
				]
			],
			[
				[
					'numeric' => false,
					'fields' => [
						'Item' => 'System description',
						'Name' => 'Type of information - characters & aggregation function - min',
						'Advanced configuration' => true,
						'Aggregation function' => 'min'
					],
					'selector' => 'id:item-aggregate-function-warning',
					'label' => 'Aggregation function',
					'warning_message' => 'With this setting only numeric items will be displayed.'
				]
			],
			[
				[
					'numeric' => false,
					'fields' => [
						'Item' => 'Get filesystems',
						'Name' => 'Type of information - text & aggregation function - max',
						'Advanced configuration' => true,
						'Aggregation function' => 'max'
					],
					'selector' => 'id:item-aggregate-function-warning',
					'label' => 'Aggregation function',
					'warning_message' => 'With this setting only numeric items will be displayed.'
				]
			],
			[
				[
					'numeric' => false,
					'fields' => [
						'Item' => 'item_testPageHistory_CheckLayout_Log',
						'Name' => 'Type of information - log & aggregation function - avg',
						'Advanced configuration' => true,
						'Aggregation function' => 'avg'
					],
					'selector' => 'id:item-aggregate-function-warning',
					'label' => 'Aggregation function',
					'warning_message' => 'With this setting only numeric items will be displayed.'
				]
			],
			[
				[
					'numeric' => false,
					'fields' => [
						'Item' => 'System description',
						'Name' => 'Type of information - characters & aggregation function - sum',
						'Advanced configuration' => true,
						'Aggregation function' => 'sum'
					],
					'selector' => 'id:item-aggregate-function-warning',
					'label' => 'Aggregation function',
					'warning_message' => 'With this setting only numeric items will be displayed.'
				]
			],
			[
				[
					'numeric' => false,
					'fields' => [
						'Item' => 'System description',
						'Name' => 'Item Widget with type of information - characters',
						'Advanced configuration' => true,
						'History data' => 'Trends'
					],
					'selector' => 'id:item-history-data-warning',
					'label' => 'History data',
					'warning_message' => 'This setting applies only to numeric data. Non-numeric data will always be taken from history.'
				]
			],
			[
				[
					'numeric' => false,
					'fields' => [
						'Item' => 'Get filesystems',
						'Name' => 'Item Widget with type of information - text',
						'Advanced configuration' => true,
						'History data' => 'Trends'
					],
					'selector' => 'id:item-history-data-warning',
					'label' => 'History data',
					'warning_message' => 'This setting applies only to numeric data. Non-numeric data will always be taken from history.'
				]
			],
			[
				[
					'numeric' => false,
					'fields' => [
						'Item' => 'item_testPageHistory_CheckLayout_Log',
						'Name' => 'Item Widget with type of information - log',
						'Advanced configuration' => true,
						'History data' => 'Trends'
					],
					'selector' => 'id:item-history-data-warning',
					'label' => 'History data',
					'warning_message' => 'This setting applies only to numeric data. Non-numeric data will always be taken from history.'
				]
			],
			[
				[
					'numeric' => false,
					'any_type_of_information' => true,
					'fields' => [
						'Item' => 'Operating system',
						'Name' => 'Type of information - characters & aggregation function - not used',
						'Advanced configuration' => true,
						'Aggregation function' => 'not used'
					],
					'selector' => 'id:item-aggregate-function-warning',
					'label' => 'Aggregation function'
				]
			],
			[
				[
					'numeric' => false,
					'any_type_of_information' => true,
					'fields' => [
						'Item' => 'Operating system',
						'Name' => 'Type of information - characters & aggregation function - count',
						'Advanced configuration' => true,
						'Aggregation function' => 'count'
					],
					'selector' => 'id:item-aggregate-function-warning',
					'label' => 'Aggregation function'
				]
			],
			[
				[
					'numeric' => false,
					'any_type_of_information' => true,
					'fields' => [
						'Item' => 'Zabbix proxies stats',
						'Name' => 'Type of information - text & aggregation function - first',
						'Advanced configuration' => true,
						'Aggregation function' => 'first'
					],
					'selector' => 'id:item-aggregate-function-warning',
					'label' => 'Aggregation function'
				]
			],
			[
				[
					'numeric' => false,
					'any_type_of_information' => true,
					'fields' => [
						'Item' => 'item_testPageHistory_CheckLayout_Log',
						'Name' => 'Type of information - log & aggregation function - last',
						'Advanced configuration' => true,
						'Aggregation function' => 'last'
					],
					'selector' => 'id:item-aggregate-function-warning',
					'label' => 'Aggregation function'
				]
			],
			[
				[
					'numeric' => true,
					'fields' => [
						'Item' => 'Free swap space',
						'Name' => 'Item Widget with type of information - Numeric (unsigned)',
						'Advanced configuration' => true
					],
					'selector' => 'id:item-thresholds-warning',
					'label' => 'Thresholds'
				]
			],
			[
				[
					'numeric' => true,
					'fields' => [
						'Item' => 'Interrupts per second',
						'Name' => 'Item Widget with type of information - Numeric (float)',
						'Advanced configuration' => true
					],
					'selector' => 'id:item-thresholds-warning',
					'label' => 'Thresholds'
				]
			],
			[
				[
					'numeric' => true,
					'fields' => [
						'Item' => 'Available memory',
						'Name' => 'Type of information - Numeric (unsigned) & aggregation function - not used',
						'Advanced configuration' => true,
						'Aggregation function' => 'not used'
					],
					'selector' => 'id:item-aggregate-function-warning',
					'label' => 'Aggregation function'
				]
			],
			[
				[
					'numeric' => true,
					'fields' => [
						'Item' => 'Free swap space',
						'Name' => 'Type of information - Numeric (unsigned) & aggregation function - min',
						'Advanced configuration' => true,
						'Aggregation function' => 'min'
					],
					'selector' => 'id:item-aggregate-function-warning',
					'label' => 'Aggregation function'
				]
			],
			[
				[
					'numeric' => true,
					'fields' => [
						'Item' => 'Interrupts per second',
						'Name' => 'Type of information - Numeric (float) & aggregation function - max',
						'Advanced configuration' => true,
						'Aggregation function' => 'max'
					],
					'selector' => 'id:item-aggregate-function-warning',
					'label' => 'Aggregation function'
				]
			],
			[
				[
					'numeric' => true,
					'fields' => [
						'Item' => 'Free swap space',
						'Name' => 'Type of information - Numeric (unsigned) & aggregation function - avg',
						'Advanced configuration' => true,
						'Aggregation function' => 'avg'
					],
					'selector' => 'id:item-aggregate-function-warning',
					'label' => 'Aggregation function'
				]
			],
			[
				[
					'numeric' => true,
					'fields' => [
						'Item' => 'Available memory in %',
						'Name' => 'Type of information - Numeric (float) & aggregation function - count',
						'Advanced configuration' => true,
						'Aggregation function' => 'count'
					],
					'selector' => 'id:item-aggregate-function-warning',
					'label' => 'Aggregation function'
				]
			],
			[
				[
					'numeric' => true,
					'fields' => [
						'Item' => 'Free swap space',
						'Name' => 'Type of information - Numeric (unsigned) & aggregation function - sum',
						'Advanced configuration' => true,
						'Aggregation function' => 'sum'
					],
					'selector' => 'id:item-aggregate-function-warning',
					'label' => 'Aggregation function'
				]
			],
			[
				[
					'numeric' => true,
					'fields' => [
						'Item' => 'Interrupts per second',
						'Name' => 'Type of information - Numeric (float) & aggregation function - first',
						'Advanced configuration' => true,
						'Aggregation function' => 'first'
					],
					'selector' => 'id:item-aggregate-function-warning',
					'label' => 'Aggregation function'
				]
			],
			[
				[
					'numeric' => true,
					'fields' => [
						'Item' => 'System local time',
						'Name' => 'Type of information - Numeric (unsigned) & aggregation function - last',
						'Advanced configuration' => true,
						'Aggregation function' => 'last'
					],
					'selector' => 'id:item-aggregate-function-warning',
					'label' => 'Aggregation function'
				]
			],
			[
				[
					'numeric' => true,
					'fields' => [
						'Item' => 'Free swap space',
						'Name' => 'Item Widget with type of information - Numeric (unsigned)',
						'Advanced configuration' => true,
						'History data' => 'Trends'
					],
					'selector' => 'id:item-history-data-warning',
					'label' => 'History data'
				]
			],
			[
				[
					'numeric' => true,
					'fields' => [
						'Item' => 'Interrupts per second',
						'Name' => 'Item Widget with type of information - Numeric (float)',
						'Advanced configuration' => true,
						'History data' => 'Trends'
					],
					'selector' => 'id:item-history-data-warning',
					'label' => 'History data'
				]
			]
		];
	}

	/**
	 * Check warning message, when item type is not numeric.
	 *
	 * @dataProvider getWarningMessageData
	 */
	public function testDashboardItemValueWidget_WarningMessage($data) {
		$info = 'class:zi-i-warning';
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardids[self::DASHBOARD]);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->addWidget()->asForm();
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Item value')]);
		$form->fill($data['fields']);

		if ($data['numeric'] === true || array_key_exists('any_type_of_information', $data)) {
			// Check that warning item is not displayed.
			$form->query($data['selector'])->one()->waitUntilNotVisible();

			// Check that info icon is not displayed.
			$this->assertFalse($form->getLabel($data['label'])->query($info)->one()->isVisible());
		}
		else {
			// Check that warning item is displayed.
			$form->query($data['selector'])->one()->waitUntilVisible();

			// Check that info icon is displayed.
			$this->assertTrue($form->getLabel($data['label'])->query($info)->one()->isVisible());

			// Check hint-box.
			$form->query($data['selector'])->one()->click();
			$hint = $form->query('xpath://div[@class="overlay-dialogue wordbreak"]')->one()->waitUntilVisible();
			$this->assertEquals($data['warning_message'], $hint->getText());

			// Close the hint-box.
			$hint->query('xpath:.//button[@class="btn-overlay-close"]')->one()->click()->waitUntilNotVisible();
		}

		COverlayDialogElement::find()->one()->close();
	}

	public static function getThresholdData() {
		return [
			// Numeric (unsigned) item without data.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - numeric (unsigned)',
						'Name' => 'Threshold and numeric item but without data',
						'Advanced configuration' => true
					],
					'thresholds' => [
						['color' => 'AABBCC', 'threshold' => '1']
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Numeric (float) item without data.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - numeric (float)',
						'Name' => 'Two thresholds and numeric item without data',
						'Advanced configuration' => true
					],
					'thresholds' => [
						['color' => 'AABBCC', 'threshold' => '0'],
						['color' => 'CCDDAA', 'threshold' => '1']
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Log) item without data and with aggregation function min.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Log',
						'Name' => 'Non-numeric (log) item without data and with aggregation function min',
						'Advanced configuration' => true,
						'Aggregation function' => 'min'
					],
					'thresholds' => [
						['color' => '7E57C2', 'threshold' => '0']
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Character) item without data and with aggregation function max.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Character',
						'Name' => 'Non-numeric (Character) item without data and with aggregation function max',
						'Advanced configuration' => true,
						'Aggregation function' => 'max'
					],
					'thresholds' => [
						['color' => '7E57C2', 'threshold' => '1']
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Text) item without data and with aggregation function avg.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Text',
						'Name' => 'Non-numeric (Text) item without data and with aggregation function avg',
						'Advanced configuration' => true,
						'Aggregation function' => 'avg'
					],
					'thresholds' => [
						['color' => '7E57C2', 'threshold' => '-1']
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Log) item without data and with aggregation function sum.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Log',
						'Name' => 'Non-numeric (log) item without data and with aggregation function sum',
						'Advanced configuration' => true,
						'Aggregation function' => 'sum'
					],
					'thresholds' => [
						['color' => '7E57C2', 'threshold' => '0']
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Character) item without data and with aggregation function first.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Character',
						'Name' => 'Non-numeric (Character) item without data and with aggregation function first',
						'Advanced configuration' => true,
						'Aggregation function' => 'first'
					],
					'thresholds' => [
						['color' => '7E57C2', 'threshold' => '-1']
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Text) item without data and with aggregation function last.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Text',
						'Name' => 'Non-numeric (Text) item without data and with aggregation function last',
						'Advanced configuration' => true,
						'Aggregation function' => 'last'
					],
					'thresholds' => [
						['color' => '7E57C2', 'threshold' => '0.00']
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Log) item without data and with aggregation function not used.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Log',
						'Name' => 'Non-numeric (log) item without data and with aggregation function not used',
						'Advanced configuration' => true
					],
					'thresholds' => [
						['color' => '7E57C2', 'threshold' => '0']
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Log) item without data but with aggregation function count (return 0).
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Log',
						'Name' => 'Non-numeric (log) item without data but with aggregation function count',
						'Advanced configuration' => true,
						'Aggregation function' => 'count'
					],
					'thresholds' => [
						['color' => '7E57C2', 'threshold' => '1']
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Character) item without data but with aggregation function count (return 0).
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Character',
						'Name' => 'Non-numeric (Character) item without data but with aggregation function count',
						'Advanced configuration' => true,
						'Aggregation function' => 'count'
					],
					'thresholds' => [
						['color' => '7E57C2', 'threshold' => '1']
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Text) item without data but with aggregation function count (return 0).
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Text',
						'Name' => 'Non-numeric (Text) item without data but with aggregation function count',
						'Advanced configuration' => true,
						'Aggregation function' => 'count'
					],
					'thresholds' => [
						['color' => '7E57C2', 'threshold' => '1']
					],
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Log) item without data but with aggregation function count (return 0) and threshold equals 0.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Log',
						'Name' => 'Non-numeric (log) item without data but with aggregation function count and threshold that match 0',
						'Advanced configuration' => true,
						'Aggregation function' => 'count'
					],
					'thresholds' => [
						['color' => '7E57C2', 'threshold' => '0']
					]
				]
			],
			// Non-numeric (Character) item without data but with aggregation function count (return 0) and threshold equals 0.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Character',
						'Name' => 'Non-numeric (Character) item without data but with aggregation function count and threshold that match 0',
						'Advanced configuration' => true,
						'Aggregation function' => 'count'
					],
					'thresholds' => [
						['color' => '7E57C2', 'threshold' => '0']
					]
				]
			],
			// Non-numeric (Text) item without data but with aggregation function count (return 0) and threshold equals 0.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Text',
						'Name' => 'Non-numeric (Text) item without data but with aggregation function count and threshold that match 0',
						'Advanced configuration' => true,
						'Aggregation function' => 'count'
					],
					'thresholds' => [
						['color' => '7E57C2', 'threshold' => '0']
					]
				]
			],
			// Numeric (unsigned) item with data and aggregation function not used.
			[
				[
					'numeric' => true,
					'fields' => [
						'Item' => 'Item with type of information - numeric (unsigned)',
						'Name' => 'Thresholds and numeric (unsigned) item',
						'Advanced configuration' => true
					],
					'thresholds' => [
						['color' => 'AABBCC', 'threshold' => '1'],
						['color' => 'CCDDAA', 'threshold' => '2']
					],
					'value' => '1'
				]
			],
			// Numeric (float) item with data and aggregation function not used.
			[
				[
					'numeric' => true,
					'fields' => [
						'Item' => 'Item with type of information - numeric (float)',
						'Name' => 'Thresholds and numeric (float) item',
						'Advanced configuration' => true
					],
					'thresholds' => [
						['color' => 'AABBCC', 'threshold' => '1.01'],
						['color' => 'CCDDAA', 'threshold' => '2.01']
					],
					'value' => '1.02'
				]
			],
			// Numeric (unsigned) item with data and aggregation function count.
			[
				[
					'numeric' => true,
					'fields' => [
						'Item' => 'Item with type of information - numeric (unsigned)',
						'Name' => 'Numeric (unsigned) item with thresholds and aggregation function count',
						'Advanced configuration' => true,
						'Aggregation function' => 'count'
					],
					'thresholds' => [
						['color' => 'AABBCC', 'threshold' => '1'],
						['color' => 'CCDDAA', 'threshold' => '2']
					],
					'value' => '1'
				]
			],
			// Numeric (float) item with data and aggregation function count.
			[
				[
					'numeric' => true,
					'fields' => [
						'Item' => 'Item with type of information - numeric (float)',
						'Name' => 'Numeric (float) item with thresholds and aggregation function count',
						'Advanced configuration' => true,
						'Aggregation function' => 'count'
					],
					'thresholds' => [
						['color' => 'AABBCC', 'threshold' => '0.99'],
						['color' => 'CCDDAA', 'threshold' => '1.99']
					],
					'value' => '1.02'
				]
			],
			// Non-numeric (Text) item with data and aggregation function count.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Text',
						'Name' => 'Thresholds and non-nmeric (Text) item',
						'Advanced configuration' => true,
						'Aggregation function' => 'count'
					],
					'thresholds' => [
						['color' => 'DDAAFF', 'threshold' => '1'],
						['color' => 'FFDDAA', 'threshold' => '2']
					],
					'value' => 'test'
				]
			],
			// Non-numeric (Log) item with data and aggregation function count.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Log',
						'Name' => 'Thresholds and non-nmeric (Log) item',
						'Advanced configuration' => true,
						'Aggregation function' => 'count'
					],
					'thresholds' => [
						['color' => 'DDAAFF', 'threshold' => '1'],
						['color' => 'FFDDAA', 'threshold' => '2']
					],
					'value' => 'test'
				]
			],
			// Non-numeric (Character) item with data and aggregation function count.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Character',
						'Name' => 'Thresholds and non-nmeric (Character) item',
						'Advanced configuration' => true,
						'Aggregation function' => 'count'
					],
					'thresholds' => [
						['color' => 'DDAAFF', 'threshold' => '1'],
						['color' => 'FFDDAA', 'threshold' => '2']
					],
					'value' => 'test'
				]
			],
			// Numeric (unsigned) item with data and aggregation function min.
			[
				[
					'numeric' => true,
					'fields' => [
						'Item' => 'Item with type of information - numeric (unsigned)',
						'Name' => 'Numeric (unsigned) item with threshold and aggregation function min',
						'Advanced configuration' => true,
						'Aggregation function' => 'min'
					],
					'thresholds' => [
						['color' => 'AABBCC', 'threshold' => '1'],
						['color' => 'CCDDAA', 'threshold' => '2']
					],
					'expected_color' => 'AABBCC',
					'value' => '1'
				]
			],
			// Numeric (float) item with data and aggregation function max.
			[
				[
					'numeric' => true,
					'fields' => [
						'Item' => 'Item with type of information - numeric (float)',
						'Name' => 'Numeric (float) item with threshold and aggregation function max',
						'Advanced configuration' => true,
						'Aggregation function' => 'max'
					],
					'thresholds' => [
						['color' => '7CB342', 'threshold' => '0.00'],
						['color' => 'FFF9C4', 'threshold' => '1.01']
					],
					'expected_color' => 'FFF9C4',
					'value' => '1.01'
				]
			],
			// Numeric (unsigned) item with data and aggregation function avg.
			[
				[
					'numeric' => true,
					'fields' => [
						'Item' => 'Item with type of information - numeric (unsigned)',
						'Name' => 'Numeric (unsigned) item with threshold and aggregation function avg',
						'Advanced configuration' => true,
						'Aggregation function' => 'avg'
					],
					'thresholds' => [
						['color' => '7CB342', 'threshold' => '1'],
						['color' => 'FFF9C4', 'threshold' => '2']
					],
					'expected_color' => '7CB342',
					'value' => '1'
				]
			],
			// Numeric (float) item with data and aggregation function sum.
			[
				[
					'numeric' => true,
					'fields' => [
						'Item' => 'Item with type of information - numeric (float)',
						'Name' => 'Numeric (float) item with threshold and aggregation function sum',
						'Advanced configuration' => true,
						'Aggregation function' => 'sum'
					],
					'thresholds' => [
						['color' => 'D32F2F', 'threshold' => '1.11'],
						['color' => '8BC34A', 'threshold' => '2.22']
					],
					'expected_color' => '8BC34A',
					'value' => '2.22'
				]
			],
			// Numeric (unsigned) item with data and aggregation function first.
			[
				[
					'numeric' => true,
					'fields' => [
						'Item' => 'Item with type of information - numeric (unsigned)',
						'Name' => 'Numeric (unsigned) item with threshold and aggregation function first',
						'Advanced configuration' => true,
						'Aggregation function' => 'first'
					],
					'thresholds' => [
						['color' => 'D32F2F', 'threshold' => '0'],
						['color' => '8BC34A', 'threshold' => '1']
					],
					'expected_color' => 'D32F2F',
					'value' => '0'
				]
			],
			// Numeric (float) item with data and aggregation function last.
			[
				[
					'numeric' => true,
					'fields' => [
						'Item' => 'Item with type of information - numeric (float)',
						'Name' => 'Numeric (float) item with threshold and aggregation function last',
						'Advanced configuration' => true,
						'Aggregation function' => 'last'
					],
					'thresholds' => [
						['color' => 'D32F2F', 'threshold' => '-1.00'],
						['color' => '8BC34A', 'threshold' => '0.00']
					],
					'expected_color' => '8BC34A',
					'value' => '0'
				]
			],
			// Non-numeric (Log) item with data and aggregation function min.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Log',
						'Name' => 'Thresholds and non-nmeric (Log) item with aggregation function min',
						'Advanced configuration' => true,
						'Aggregation function' => 'min'
					],
					'thresholds' => [
						['color' => 'DDAAFF', 'threshold' => '0']
					],
					'value' => 'test',
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Character) item with data and aggregation function max.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Character',
						'Name' => 'Thresholds and non-nmeric (Character) item with aggregation function max',
						'Advanced configuration' => true,
						'Aggregation function' => 'max'
					],
					'thresholds' => [
						['color' => 'D32F2F', 'threshold' => '-1'],
						['color' => '8BC34A', 'threshold' => '0']
					],
					'value' => 'test',
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Text) item with data and aggregation function avg.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Text',
						'Name' => 'Thresholds and non-nmeric (Text) item with aggregation function avg',
						'Advanced configuration' => true,
						'Aggregation function' => 'avg'
					],
					'thresholds' => [
						['color' => 'D1C4E9', 'threshold' => '1'],
						['color' => '80CBC4', 'threshold' => '2']
					],
					'value' => 'test',
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Log) item with data and aggregation function sum.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Log',
						'Name' => 'Thresholds and non-nmeric (Log) item with aggregation function sum',
						'Advanced configuration' => true,
						'Aggregation function' => 'sum'
					],
					'thresholds' => [
						['color' => 'D1C4E9', 'threshold' => '1'],
						['color' => '80CBC4', 'threshold' => '2']
					],
					'value' => 'test',
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Character) item with data and aggregation function first.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Character',
						'Name' => 'Thresholds and non-nmeric (Character) item with aggregation function first',
						'Advanced configuration' => true,
						'Aggregation function' => 'first'
					],
					'thresholds' => [
						['color' => 'D1C4E9', 'threshold' => '0']
					],
					'value' => 'test',
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Text) item with data and aggregation function last.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Text',
						'Name' => 'Thresholds and non-nmeric (Text) item with aggregation function last',
						'Advanced configuration' => true,
						'Aggregation function' => 'last'
					],
					'thresholds' => [
						['color' => 'D1C4E9', 'threshold' => '0']
					],
					'value' => 'test',
					'expected_color' => '000000',
					'opacity' => 'transparent'
				]
			],
			// Non-numeric (Text) item with data and aggregation function not used.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Text',
						'Name' => 'Thresholds and non-nmeric (Text) item with aggregation function not used',
						'Advanced configuration' => true
					],
					'thresholds' => [
						['color' => 'D1C4E9', 'threshold' => '0']
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
	public function testDashboardItemValueWidget_ThresholdColor($data) {
		$time = strtotime('now');
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardids[self::DASHBOARD_THRESHOLD]);
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->addWidget()->asForm();
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Item value')]);
		$form->fill($data['fields']);
		$this->getThresholdTable()->fill($data['thresholds']);

		$form->submit();
		COverlayDialogElement::ensureNotPresent();
		$this->page->waitUntilReady();
		$dashboard->save();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Value for threshold trigger.
		foreach ($data['thresholds'] as $threshold) {
			// Insert item data.
			if (array_key_exists('value', $data)) {
				CDataHelper::addItemData(self::$itemids[$data['fields']['Item']], $data['value'], $time);

				if (array_key_exists('numeric', $data)) {
					$data['value']++;
				}

				$time++;
			}

			$this->page->refresh()->waitUntilReady();

			$rgb = (array_key_exists('expected_color', $data))
				? implode(', ', sscanf($data['expected_color'], "%02x%02x%02x"))
				: implode(', ', sscanf($threshold['color'], "%02x%02x%02x"));

			$opacity = (array_key_exists('opacity', $data)) ? '0' : '1';
			$this->assertEquals('rgba('.$rgb.', '.$opacity.')', $dashboard->getWidget($data['fields']['Name'])
					->query('xpath:.//div[contains(@class, "dashboard-widget-item")]/div/a')->one()->getCSSValue('background-color')
			);
		}

		// Necessary for test stability.
		$dashboard->edit()->deleteWidget($data['fields']['Name'])->save();
	}

	public static function getWidgetTimePeriodData() {
		return [
			// Widget with default configuration.
			[
				[
					'widgets' => [
						[
							'widget_type' => 'Item value',
							'fields' => [
								'Name' => 'Default widget',
								'Item' => 'Available memory'
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
							'widget_type' => 'Item value',
							'fields' => [
								'Name' => 'Item widget with "Custom" time period',
								'Item' => 'Available memory',
								'Advanced configuration' => true,
								'Aggregation function' => 'min',
								'Time period' => 'Custom'
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
							'fields' => [
								'Name' => 'Graph widget with "Custom" time period',
								'Graph' => 'System load',
								'Time period' => 'Custom',
								'id:time_period_from' => 'now-5400',
								'id:time_period_to' => 'now-1800'
							]
						],
						[
							'widget_type' => 'Item value',
							'fields' => [
								'Name' => 'Item widget with "Widget" time period',
								'Item' => 'Available memory',
								'Advanced configuration' => true,
								'Aggregation function' => 'max',
								'Time period' => 'Widget',
								'Widget' => 'Graph widget with "Custom" time period'
							]
						]
					]
				]
			],
			// Item value widget with time period "Dashboard" (enabled zoom filter).
			[
				[
					'widgets' => [
						[
							'widget_type' => 'Item value',
							'fields' => [
								'Name' => 'Item value widget with "Dashboard" time period',
								'Item' => 'Available memory in %',
								'Advanced configuration' => true,
								'Aggregation function' => 'avg',
								'Time period' => 'Dashboard'
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
							'widget_type' => 'Item value',
							'fields' => [
								'Name' => 'Item value widget with "Custom" time period',
								'Item' => 'Available memory in %',
								'Advanced configuration' => true,
								'Aggregation function' => 'sum',
								'Time period' => 'Custom',
								'id:time_period_from' => 'now-2y',
								'id:time_period_to' => 'now-1y'
							]
						],
						[
							'widget_type' => 'Action log',
							'fields' => [
								'Name' => 'Action log widget with Dashboard time period' // time period default state.
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
	public function testDashboardItemValueWidget_TimePeriodFilter($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardids[self::DASHBOARD_ZOOM])->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();

		foreach ($data['widgets'] as $widget) {
			$form = $dashboard->edit()->addWidget()->asForm();
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL($widget['widget_type'])]);
			$form->fill($widget['fields']);
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
					' WHERE dashboardid='.self::$dashboardids[self::DASHBOARD_ZOOM].
				')'
		);
	}

	public function testDashboardItemValueWidget_TimePeriodIcon() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardids[self::DASHBOARD_ZOOM])->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$form = $dashboard->edit()->addWidget()->asForm();
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Item value')]);

		$data = [
			'Item' => 'Available memory in %',
			'Name' => 'Item value widget with "Custom" time period',
			'Advanced configuration' => true,
			'Aggregation function' => 'min',
			'Time period' => 'Custom'
		];
		$form->fill($data);
		$form->submit();
		COverlayDialogElement::ensureNotPresent();
		$dashboard->waitUntilReady();

		// Check that time period icon is displayed.
		$time_icon = 'xpath:.//button[@class="btn-icon zi-time-period"]';
		$this->assertTrue($dashboard->query($time_icon)->one()->isVisible());

		// Check hint-box.
		$dashboard->query($time_icon)->one()->click();
		$hint = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->one()->waitUntilVisible();
		$this->assertEquals('Last 1 hour', $hint->getText());

		// Close the hint-box.
		$hint->query('xpath:.//button[@class="btn-overlay-close"]')->one()->click()->waitUntilNotVisible();

		$dashboard->edit()->getWidget($data['Name'])->edit();
		$form->fill(['Advanced configuration' => true, 'Aggregation function' => 'not used']);
		$form->submit();
		COverlayDialogElement::ensureNotPresent();
		$dashboard->waitUntilReady();
		$this->assertFalse($dashboard->query($time_icon)->one(false)->isValid());

		// Necessary action for avoiding problems with next test.
		$dashboard->save();
	}

	public static function getAggregationFunctionData() {
		return [
			// Item with value mapping, aggregation function 'min' and default Custom time period.
			[
				[
					'fields' => [
						'Item' => 'Value mapping',
						'Advanced configuration' => true,
						'Aggregation function' => 'min',
						'Time period' => 'Custom'
					],
					'item_data' => [
						[
							'value' => '0',
							'time' => 'now'
						],
						[
							'value' => '1',
							'time' => '-61 minute'
						]
					],
					'value_mapping' => true,
					'expected_value' => 'Down (0)',
					'arrow' => 'up-down' // Item value has changed comparing with previous hour.
				]
			],
			// Item with value mapping and aggregation function 'max'.
			[
				[
					'fields' => [
						'Item' => 'Value mapping',
						'Advanced configuration' => true,
						'Aggregation function' => 'max',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-2h',
						'id:time_period_to' => 'now-1h'
					],
					'item_data' => [
						[
							'value' => '0',
							'time' => '-1 minute'
						],
						[
							'value' => '1',
							'time' => '-62 minutes'
						],
						[
							'value' => '0',
							'time' => '-122 minutes'
						]
					],
					'value_mapping' => true,
					'expected_value' => 'Up (1)',
					'arrow' => 'up-down'
				]
			],
			// Item with value mapping, aggregation function 'avg' and Custom time period with relative time.
			[
				[
					'fields' => [
						'Item' => 'Value mapping',
						'Advanced configuration' => true,
						'Aggregation function' => 'avg',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-7d',
						'id:time_period_to' => 'now-5d'
					],
					'item_data' => [
						[
							'value' => '1',
							'time' => '-6 days'
						]
					],
					'value_mapping' => true,
					'expected_value' => 'Up (1)'
				]
			],
			// Item with value mapping, aggregation function 'avg' and Custom time period.
			[
				[
					'fields' => [
						'Item' => 'Value mapping',
						'Advanced configuration' => true,
						'Aggregation function' => 'avg',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-4d',
						'id:time_period_to' => 'now-2d'
					],
					'item_data' => [
						[
							'value' => '1',
							'time' => '-3 days'
						],
						[
							'value' => '0',
							'time' => '-63 hours'
						]
					],
					'expected_value' => '0.50' // Value mapping is ignored if value doesn't equals 0 or 1.
				]
			],
			// Item with value mapping and aggregation function 'count'.
			[
				[
					'fields' => [
						'Item' => 'Value mapping',
						'Advanced configuration' => true,
						'Aggregation function' => 'count',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-5h-30m',
						'id:time_period_to' => 'now-14400' // -4 hours.
					],
					'item_data' => [
						[
							'value' => '1',
							'time' => '-270 minutes'
						],
						[
							'value' => '0',
							'time' => '-275 minutes'
						],
						[
							'value' => '1',
							'time' => '-276 minutes'
						]
					],
					'expected_value' => '3.00' // Mapping is not used if aggregation function is 'sum' or 'count'.
				]
			],
			// Item with value mapping and aggregation function 'sum'.
			[
				[
					'fields' => [
						'Item' => 'Value mapping',
						'Advanced configuration' => true,
						'Aggregation function' => 'sum',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-360m',
						'id:time_period_to' => 'now-240m' // - 4 hours.
					],
					'item_data' => [
						[
							'value' => '1',
							'time' => '-270 minutes'
						],
						[
							'value' => '0',
							'time' => '-275 minutes'
						],
						[
							'value' => '1',
							'time' => '-280 minutes'
						]
					],
					'expected_value' => '2.00' // Mapping is not used if aggregation function is 'sum' or 'count'.
				]
			],
			// Item with value mapping and aggregation function 'first'.
			[
				[
					'fields' => [
						'Item' => 'Value mapping',
						'Advanced configuration' => true,
						'Aggregation function' => 'first',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-1h-30m',
						'id:time_period_to' => 'now-30m'
					],
					'item_data' => [
						[
							'value' => '0',
							'time' => '-45 minutes'
						],
						[
							'value' => '1',
							'time' => '-50 minutes'
						],
						[
							'value' => '0',
							'time' => '-2 hours'
						]
					],
					'value_mapping' => true,
					'expected_value' => 'Up (1)',
					'arrow' => 'up-down'
				]
			],
			// Item with value mapping and aggregation function 'last'.
			[
				[
					'fields' => [
						'Item' => 'Value mapping',
						'Advanced configuration' => true,
						'Aggregation function' => 'last',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-1h-20m-600s',
						'id:time_period_to' => 'now-1800s'
					],
					'item_data' => [
						[
							'value' => '1',
							'time' => '-15 minutes'
						],
						[
							'value' => '0',
							'time' => '-45 minutes'
						],
						[
							'value' => '1',
							'time' => '-50 minutes'
						]
					],
					'value_mapping' => true,
					'expected_value' => 'Down (0)'
				]
			],
			// Item with value mapping and aggregation function 'not used'.
			[
				[
					'fields' => [
						'Item' => 'Value mapping',
						'Advanced configuration' => true,
						'Aggregation function' => 'not used'
					],
					'item_data' => [
						[
							'value' => '1',
							'time' => '-15 minutes'
						],
						[
							'value' => '0',
							'time' => '-45 minutes'
						],
						[
							'value' => '1',
							'time' => '-50 minutes'
						]
					],
					'value_mapping' => true,
					'expected_value' => 'Up (1)',
					'arrow' => 'up-down'
				]
			],
			// Numeric (unsigned) item with aggregation function 'min', decimal places and default Custom time period.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - numeric (unsigned)',
						'Advanced configuration' => true,
						'Decimal places' => '9',
						'Aggregation function' => 'min',
						'Time period' => 'Custom'
					],
					'item_data' => [
						[
							'value' => '5',
							'time' => '-5 minutes'
						],
						[
							'value' => '4',
							'time' => '-30 minutes'
						],
						[
							'value' => '10',
							'time' => '-61 minute'
						]
					],
					'expected_value' => '4.000000000',
					'arrow' => 'down'
				]
			],
			// Numeric (float) item with aggregation function 'max', decimal places and Custom time period.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - numeric (float)',
						'Advanced configuration' => true,
						'Decimal places' => '3',
						'Aggregation function' => 'max',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-3h',
						'id:time_period_to' => 'now'
					],
					'item_data' => [
						[
							'value' => '7.76',
							'time' => '-5 minutes'
						],
						[
							'value' => '7.77',
							'time' => '-90 minutes'
						],
						[
							'value' => '7.78',
							'time' => '-5 hours'
						]
					],
					'expected_value' => '7.770',
					'arrow' => 'down'
				]
			],
			// Numeric (unsigned) item with aggregation function 'avg', default decimal places and Custom time period.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - numeric (unsigned)',
						'Advanced configuration' => true,
						'Decimal places' => '2',
						'Aggregation function' => 'avg',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-30m',
						'id:time_period_to' => 'now'
					],
					'item_data' => [
						[
							'value' => '2',
							'time' => '-30 seconds'
						],
						[
							'value' => '3',
							'time' => '-45 seconds'
						],
						[
							'value' => '10',
							'time' => '-60 seconds'
						],
						[
							'value' => '15',
							'time' => '-90 seconds'
						]
					],
					'expected_value' => '7.50'
				]
			],
			// Item with units, aggregation function 'count' and Custom time period.
			[
				[
					'fields' => [
						'Item' => 'Item with units',
						'Advanced configuration' => true,
						'Aggregation function' => 'count',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-1h-20m-30s',
						'id:time_period_to' => 'now'
					],
					'item_data' => [
						[
							'value' => '2',
							'time' => '-10 minutes'
						],
						[
							'value' => '95',
							'time' => '-15 minutes'
						]
					],
					// Item units are not shown if aggregation function is 'count' except when units are set in widget configuration.
					'expected_value' => '2.00',
					'arrow' => 'up'
				]
			],
			// Item with units, aggregation function 'count', widget units override and Custom time period.
			[
				[
					'fields' => [
						'Item' => 'Item with units',
						'Advanced configuration' => true,
						'id:units' => '$',
						'Aggregation function' => 'count',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-1h-20m-30s',
						'id:time_period_to' => 'now'
					],
					'item_data' => [
						[
							'value' => '2',
							'time' => '-10 minutes'
						],
						[
							'value' => '95',
							'time' => '-15 minutes'
						]
					],
					// Item units are not shown if aggregation function is 'count' except when units are set in widget configuration.
					'units' => true,
					'expected_value' => '2.00$',
					'arrow' => 'up'
				]
			],
			// Item with units, aggregation function 'sum' and Custom time period.
			[
				[
					'fields' => [
						'Item' => 'Item with units',
						'Advanced configuration' => true,
						'id:units' => '',
						'Aggregation function' => 'sum',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-1h-20m-30s',
						'id:time_period_to' => 'now'
					],
					'item_data' => [
						[
							'value' => '2',
							'time' => '-10 minutes'
						],
						[
							'value' => '95',
							'time' => '-15 minutes'
						]
					],
					'units' => true,
					'expected_value' => '97.00%'
				]
			],
			// Numeric (float) item with aggregation function 'first' and Custom time period.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - numeric (float)',
						'Advanced configuration' => true,
						'Aggregation function' => 'first',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-30d',
						'id:time_period_to' => 'now-1d'
					],
					'item_data' => [
						[
							'value' => '11.11',
							'time' => '-10 days'
						],
						[
							'value' => '12.55',
							'time' => '-15 days'
						],
						[
							'value' => '12.01',
							'time' => '-20 days'
						],
						[
							'value' => '12.99',
							'time' => '-25 days'
						],
						[
							'value' => '121.12',
							'time' => '-31 days'
						]
					],
					'expected_value' => '12.99'
				]
			],
			// Numeric (float) item with aggregation function 'last' and Custom time period with absolute time.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - numeric (float)',
						'Advanced configuration' => true,
						'Aggregation function' => 'last',
						'Time period' => 'Custom',
						'id:time_period_from' => '{date} 00:00:00',
						'id:time_period_to' => '{date} 23:59:59'
					],
					'item_data' => [
						[
							'value' => '10.33',
							'time' => '{date} 04:00:00'
						],
						[
							'value' => '12.55',
							'time' => '{date} 08:00:00'
						],
						[
							'value' => '12.99',
							'time' => '{date} 11:00:00'
						],
						[
							'value' => '11.99',
							'time' => '{date} 12:00:00'
						]
					],
					'substitute_date' => true,
					'expected_value' => '11.99'
				]
			],
			// Non-numeric (Text) item with aggregation function 'count' and Custom time period with relative time.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Text',
						'Advanced configuration' => true,
						'Aggregation function' => 'count',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-2y-1M-2w-1d-10h-30m-20s',
						'id:time_period_to' => 'now-1y-1M-2w-1d-10h-30m-20s'
					],
					'item_data' => [
						[
							'value' => 'text 1',
							'time' => '-1 year -1 month -2 weeks -2 days -10 hours -30 minutes -20 seconds'
						],
						[
							'value' => 'text 2',
							'time' => '-1 year -1 month -2 weeks -1 day -20 hours -30 minutes -20 seconds'
						],
						[
							'value' => 'text 3',
							'time' => '-1 year -1 month -3 weeks -2 days -10 hours -30 minutes -20 seconds'
						],
						[
							'value' => 'text 4',
							'time' => '-1 year -2 month -2 weeks -2 days -10 hours -30 minutes -20 seconds'
						]
					],
					'expected_value' => '4.00',
					'arrow' => 'up'
				]
			],
			// Non-numeric (Log) item with aggregation function 'min' and Custom time period with relative time.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Log',
						'Advanced configuration' => true,
						'Aggregation function' => 'min', // only numeric items will be displayed.
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-2y',
						'id:time_period_to' => 'now-1y'
					],
					'item_data' => [
						[
							'value' => 'log 1',
							'time' => '-15 month'
						]
					],
					'non_numeric' => true,
					'expected_value' => 'No data'
				]
			],
			// Non-numeric (Character) item with aggregation function 'max' and Custom time period with absolute time.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Character',
						'Advanced configuration' => true,
						'Aggregation function' => 'max', // only numeric items will be displayed.
						'Time period' => 'Custom',
						'id:time_period_from' => '2023-12-12 00:00:00',
						'id:time_period_to' => '2023-12-12 10:00:00'
					],
					'item_data' => [
						[
							'value' => 'Character 1',
							'time' => '2023-12-12 05:00:00'
						]
					],
					'non_numeric' => true,
					'expected_value' => 'No data'
				]
			],
			// Non-numeric (Text) item with aggregation function 'avg' and Custom time period with relative time.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Text',
						'Advanced configuration' => true,
						'Aggregation function' => 'avg', // only numeric items will be displayed.
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-1d',
						'id:time_period_to' => 'now'
					],
					'item_data' => [
						[
							'value' => 'Text 1',
							'time' => '-1 hour'
						],
						[
							'value' => 'Text 2',
							'time' => '-1 day -1 hour'
						]
					],
					'non_numeric' => true,
					'expected_value' => 'No data'
				]
			],
			// Non-numeric (Log) item with aggregation function 'sum' and Custom time period.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Log',
						'Advanced configuration' => true,
						'Aggregation function' => 'sum', // only numeric items will be displayed.
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-1d',
						'id:time_period_to' => 'now'
					],
					'item_data' => [
						[
							'value' => 'Log 1',
							'time' => '-1 hour'
						],
						[
							'value' => 'Log 2',
							'time' => '-1 day -1 hour'
						]
					],
					'non_numeric' => true,
					'expected_value' => 'No data'
				]
			],
			// Non-numeric (Character) item with aggregation function 'first' and Custom time period.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Character',
						'Advanced configuration' => true,
						'Aggregation function' => 'first',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-1d',
						'id:time_period_to' => 'now'
					],
					'item_data' => [
						[
							'value' => 'Character 1',
							'time' => '-1 hour'
						],
						[
							'value' => 'Character 2',
							'time' => '-10 hours'
						],
						[
							'value' => 'Character 3',
							'time' => '-1 day -1 hour'
						]
					],
					'non_numeric' => true,
					'expected_value' => 'Character 2',
					'arrow' => 'up-down'
				]
			],
			// Non-numeric (Text) item with aggregation function 'last' and Custom time period.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - Text',
						'Advanced configuration' => true,
						'Aggregation function' => 'last',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-1w',
						'id:time_period_to' => 'now'
					],
					'item_data' => [
						[
							'value' => 'text 2',
							'time' => '-1 hour'
						],
						[
							'value' => 'text 1',
							'time' => '-1 hour -1 minute'
						],
						[
							'value' => 'text 3',
							'time' => '-8 days'
						]
					],
					'non_numeric' => true,
					'expected_value' => 'text 2',
					'arrow' => 'up-down'
				]
			],
			// Numeric (unsigned) item with aggregation function 'avg', trends history data and Custom time period.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - numeric (unsigned)',
						'Advanced configuration' => true,
						'Aggregation function' => 'avg',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-1h',
						'id:time_period_to' => 'now',
						'History data' => 'Trends'
					],
					'item_data' => [
						[
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
					'expected_value' => '4.00',
					'arrow' => 'down'
				]
			],
			// Numeric (float) item with aggregation function 'min', trends history data and Custom time period.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - numeric (float)',
						'Advanced configuration' => true,
						'Aggregation function' => 'min',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-2h',
						'id:time_period_to' => 'now-1h',
						'History data' => 'Trends'
					],
					'item_data' => [
						[
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
					'expected_value' => '1.51',
					'arrow' => 'up'
				]
			],
			// Numeric (float) item with aggregation function 'max', trends history data and Custom time period.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - numeric (float)',
						'Advanced configuration' => true,
						'Aggregation function' => 'max',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-3h',
						'id:time_period_to' => 'now-2h',
						'History data' => 'Trends'
					],
					'item_data' => [
						[
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
					'expected_value' => '11.10',
					'arrow' => 'down'
				]
			],
			// Numeric (unsigned) item with aggregation function 'count', trends history data and Custom time period.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - numeric (unsigned)',
						'Advanced configuration' => true,
						'Aggregation function' => 'count',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-1h',
						'id:time_period_to' => 'now',
						'History data' => 'Trends'
					],
					'item_data' => [
						[
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
					'expected_value' => '7.00', // num result.
					'arrow' => 'down'
				]
			],
			// Numeric (float) item with aggregation function 'sum', trends history data and Custom time period.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - numeric (float)',
						'Advanced configuration' => true,
						'Aggregation function' => 'sum',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-2d',
						'id:time_period_to' => 'now-1d',
						'History data' => 'Trends'
					],
					'item_data' => [
						[
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
					'expected_value' => '54.39' // num * avg result.
				]
			],
			// Numeric (unsigned) item with aggregation function 'first', trends history data and Custom time period.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - numeric (unsigned)',
						'Advanced configuration' => true,
						'Aggregation function' => 'first',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-2w',
						'id:time_period_to' => 'now-1w',
						'History data' => 'Trends'
					],
					'item_data' => [
						[
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
					'expected_value' => '6.00' // avg result.
				]
			],
			// Numeric (float) item with aggregation function 'last', trends history data and Custom time period.
			[
				[
					'fields' => [
						'Item' => 'Item with type of information - numeric (float)',
						'Advanced configuration' => true,
						'Aggregation function' => 'last',
						'Time period' => 'Custom',
						'id:time_period_from' => 'now-1w',
						'id:time_period_to' => 'now',
						'History data' => 'Trends'
					],
					'item_data' => [
						[
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
					'expected_value' => '8.11', // avg result.
					'arrow' => 'up'
				]
			]
		];
	}

	/**
	 * @backup !history, !history_log, !history_str, !history_text, !history_uint, !trends_uint, !trends
	 *
	 * @dataProvider getAggregationFunctionData
	 */
	public function testDashboardItemValueWidget_AggregationFunctionData($data) {
		// Substitute macro in date related fields in test case where fixed history data (not trends) is checked.
		if (CTestArrayHelper::get($data, 'substitute_date')) {
			$data = $this->replaceDateMacroInData($data, 'today - 1 week', ['id:time_period_from', 'id:time_period_to']);
		}

		foreach ($data['item_data'] as $params) {
			$params['time'] = strtotime($params['time']);
			CDataHelper::addItemData(self::$itemids[$data['fields']['Item']], $params['value'], $params['time']);
		}

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardids[self::DASHBOARD_AGGREGATION]
		)->waitUntilReady();

		$dashboard = CDashboardElement::find()->one();
		$dashboard->waitUntilReady();

		$form = $dashboard->getWidget(self::DATA_WIDGET)->edit();
		$form->fill($data['fields']);
		$form->submit();
		$dashboard->save();
		$dashboard->waitUntilReady();
		$content = $dashboard->getWidget(self::DATA_WIDGET)->getContent();
		$item_value = $content->query('class:value')->one()->getText();

		if (array_key_exists('units', $data)) {
			$widget_value = $item_value.$content->query('class:decimals')->one()->getText()
					.$content->query('class:units')->one()->getText();
		}
		else {
			$widget_value = (array_key_exists('value_mapping', $data) || array_key_exists('non_numeric', $data))
				? $item_value
				: $item_value.$content->query('class:decimals')->one()->getText();
		}

		$this->assertEquals($data['expected_value'], $widget_value);

		if (array_key_exists('arrow', $data)) {
			$this->assertTrue($this->query('class:svg-arrow-'.$data['arrow'])->one()->isVisible());
		}
	}

	/**
	 * Test function for assuring that binary items are not available in Item Value widget.
	 */
	public function testDashboardItemValueWidget_CheckAvailableItems() {
		$this->checkAvailableItems('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardids[self::DASHBOARD],
				'Item value'
		);
	}
}
