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


require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CTagBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';
require_once dirname(__FILE__).'/../common/testWidgets.php';

/**
 * @backup dashboard, profiles, hosts, globalmacro
 *
 * @dataSource AllItemValueTypes
 *
 * @onBefore prepareHoneycombWidgetData
 */
class testDashboardHoneycombWidget extends testWidgets {

	/**
	 * Attach MessageBehavior, TagBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CMessageBehavior::class,
			CTagBehavior::class,
			CTableBehavior::class
		];
	}

	/**
	 * SQL query to get widget and widget_field tables to compare hash values, but without widget_fieldid
	 * because it can change.
	 */
	const SQL = 'SELECT wf.widgetid, wf.type, wf.name, wf.value_int, wf.value_str, wf.value_groupid, wf.value_hostid,'.
			' wf.value_itemid, wf.value_graphid, wf.value_sysmapid, w.widgetid, w.dashboard_pageid, w.type, w.name, w.x, w.y,'.
			' w.width, w.height'.
			' FROM widget_field wf'.
			' INNER JOIN widget w'.
			' ON w.widgetid=wf.widgetid'.
			' WHERE wf.name!=\'reference\''.
			' ORDER BY wf.widgetid, wf.name, wf.value_int, wf.value_str, wf.value_groupid,'.
			' wf.value_hostid, wf.value_itemid, wf.value_graphid';

	/**
	 * Ids of created Dashboards for Honeycomb widget check.
	 */
	protected static $dashboardid;

	public static function prepareHoneycombWidgetData() {

		$response = CDataHelper::createHosts([
			[
				'host' => 'Host for honeycomb 1',
				'groups' => [['groupid' => 4]], // Zabbix servers.
				'items' => [
					[
						'name' => 'Numeric for honeycomb 1',
						'key_' => 'num_honey_1',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => 0
					]
				]
			],
			[
				'host' => 'Display',
				'groups' => [['groupid' => 4]], // Zabbix servers.
				'items' => [
					[
						'name' => 'Display item 1',
						'key_' => 'honey_display_1',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => 0
					],
					[
						'name' => 'Display item 2',
						'key_' => 'honey_display_2',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => 0
					],
					[
						'name' => 'Display item 3',
						'key_' => 'honey_display_3',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => 0
					],
					[
						'name' => 'Display item 4',
						'key_' => 'honey_display_4',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => 0
					],
					[
						'name' => 'Display item 5',
						'key_' => 'honey_display_5',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'delay' => 0
					]
				]
			]
		]);
		$itemids = $response['itemids'];
		$hostid = $response['hostids']['Display'];

		foreach (['1' => 100, '2' => 200, '3' => 300, '4' => 400, '5' => 500] as $key => $value) {
			CDataHelper::addItemData($itemids['Display:honey_display_'.$key], $value);
		}

		CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for creating honeycomb widgets',
				'display_period' => 60,
				'auto_start' => 0,
				'pages' => [[]]
			],
			[
				'name' => 'Dashboard for updating honeycomb widget',
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'honeycomb',
								'name' => 'UpdateHoneycomb',
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 5,
								'fields' => [
									[
										'type' => 1,
										'name' => 'items.0',
										'value' => 'Numeric for honeycomb 1'
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for canceling honeycomb widget',
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'honeycomb',
								'name' => 'CancelHoneycomb',
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 5,
								'fields' => [
									[
										'type' => 1,
										'name' => 'items.0',
										'value' => 'Numeric for honeycomb 1'
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for deleting honeycomb widget',
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'honeycomb',
								'name' => 'DeleteHoneycomb',
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 5,
								'fields' => [
									[
										'type' => 1,
										'name' => 'items.0',
										'value' => 'Numeric for honeycomb 1'
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for honeycomb display',
				'display_period' => 60,
				'auto_start' => 0,
				'pages' => [[]]
			],
			[
				'name' => 'Dashboard for Honeycomb screenshot',
				'display_period' => 3600,
				'auto_start' => 0,
				'pages' => [
					[
						'name' => '3 dots',
						'widgets' => [
							[
								'type' => 'honeycomb',
								'x' => 0,
								'y' => 0,
								'width' => 2,
								'height' => 2,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => '3',
										'name' => 'hostids.0',
										'value' => $hostid
									],
									[
										'type' => 1,
										'name' => 'items.0',
										'value' => 'Display item 1'
									],
									[
										'type' => 1,
										'name' => 'items.1',
										'value' => 'Display item 2'
									],
									[
										'type' => 1,
										'name' => 'items.2',
										'value' => 'Display item 3'
									],
									[
										'type' => 1,
										'name' => 'items.3',
										'value' => 'Display item 4'
									],
									[
										'type' => 1,
										'name' => 'items.4',
										'value' => 'Display item 5'
									],
									[
										'type' => 1,
										'name' => 'reference',
										'value' => 'LEMLX'
									]
								]
							]
						]
					],
					[
						'name' => 'items and 3 dots',
						'widgets' => [
							[
								'type' => 'honeycomb',
								'x' => 0,
								'y' => 0,
								'width' => 3,
								'height' => 2,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => '3',
										'name' => 'hostids.0',
										'value' => $hostid
									],
									[
										'type' => 1,
										'name' => 'items.0',
										'value' => 'Display item 1'
									],
									[
										'type' => 1,
										'name' => 'items.1',
										'value' => 'Display item 2'
									],
									[
										'type' => 1,
										'name' => 'items.2',
										'value' => 'Display item 3'
									],
									[
										'type' => 1,
										'name' => 'items.3',
										'value' => 'Display item 4'
									],
									[
										'type' => 1,
										'name' => 'items.4',
										'value' => 'Display item 5'
									],
									[
										'type' => 1,
										'name' => 'bg_color',
										'value' => 'FFEBEE'
									],
									[
										'type' => 1,
										'name' => 'reference',
										'value' => 'BRZEV'
									]
								]
							]
						]
					],
					[
						'name' => '5 items grouped',
						'widgets' => [
							[
								'type' => 'honeycomb',
								'x' => 0,
								'y' => 0,
								'width' => 5,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => '3',
										'name' => 'hostids.0',
										'value' => $hostid
									],
									[
										'type' => 1,
										'name' => 'items.0',
										'value' => 'Display item 1'
									],
									[
										'type' => 1,
										'name' => 'items.1',
										'value' => 'Display item 2'
									],
									[
										'type' => 1,
										'name' => 'items.2',
										'value' => 'Display item 3'
									],
									[
										'type' => 1,
										'name' => 'items.3',
										'value' => 'Display item 4'
									],
									[
										'type' => 1,
										'name' => 'items.4',
										'value' => 'Display item 5'
									],
									[
										'type' => 0,
										'name' => 'primary_label_bold',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'primary_label_color',
										'value' => '66BB6A'
									],
									[
										'type' => 1,
										'name' => 'secondary_label_color',
										'value' => '0040FF'
									],
									[
										'type' => 1,
										'name' => 'bg_color',
										'value' => 'A1887F'
									],
									[
										'type' => 1,
										'name' => 'reference',
										'value' => 'WUFXS'
									]
								]
							]
						]
					],
					[
						'name' => 'Long horizontal line',
						'widgets' => [
							[
								'type' => 'honeycomb',
								'x' => 0,
								'y' => 0,
								'width' => 8,
								'height' => 3,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => '3',
										'name' => 'hostids.0',
										'value' => $hostid
									],
									[
										'type' => 1,
										'name' => 'items.0',
										'value' => 'Display item 1'
									],
									[
										'type' => 1,
										'name' => 'items.1',
										'value' => 'Display item 2'
									],
									[
										'type' => 1,
										'name' => 'items.2',
										'value' => 'Display item 3'
									],
									[
										'type' => 1,
										'name' => 'items.3',
										'value' => 'Display item 4'
									],
									[
										'type' => 1,
										'name' => 'items.4',
										'value' => 'Display item 5'
									],
									[
										'type' => 1,
										'name' => 'thresholds.0.color',
										'value' => 'FF465C'
									],
									[
										'type' => 1,
										'name' => 'thresholds.0.threshold',
										'value' => '100'
									],
									[
										'type' => 1,
										'name' => 'thresholds.1.color',
										'value' => 'FFD54F'
									],
									[
										'type' => 1,
										'name' => 'thresholds.1.threshold',
										'value' => '500'
									],
									[
										'type' => 1,
										'name' => 'reference',
										'value' => 'NEFEX'
									],
									[
										'type' => 0,
										'name' => 'primary_label_type',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'primary_label_decimal_places',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'primary_label_size_type',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'primary_label_size',
										'value' => 50
									],
									[
										'type' => 0,
										'name' => 'primary_label_bold',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'secondary_label_decimal_places',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'secondary_label_size_type',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'secondary_label_size',
										'value' => 20
									]
								]
							]
						]
					],
					[
						'name' => 'Default vertical line',
						'widgets' => [
							[
								'type' => 'honeycomb',
								'x' => 0,
								'y' => 0,
								'width' => 3,
								'height' => 6,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => '3',
										'name' => 'hostids.0',
										'value' => $hostid
									],
									[
										'type' => 1,
										'name' => 'items.0',
										'value' => 'Display item 1'
									],
									[
										'type' => 1,
										'name' => 'items.1',
										'value' => 'Display item 2'
									],
									[
										'type' => 1,
										'name' => 'items.2',
										'value' => 'Display item 3'
									],
									[
										'type' => 1,
										'name' => 'items.3',
										'value' => 'Display item 4'
									],
									[
										'type' => 1,
										'name' => 'items.4',
										'value' => 'Display item 5'
									],
									[
										'type' => 1,
										'name' => 'reference',
										'value' => 'TOQVG'
									]
								]
							]
						]
					]
				]
			]
		]);
		self::$dashboardid = CDataHelper::getIds('name');

		CDataHelper::call('usermacro.createglobal', [
			[
				'macro' => '{$TEXT}',
				'value' => 'text_macro'
			],
			[
				'macro' => '{$SECRET_TEXT}',
				'type' => 1,
				'value' => 'secret_macro'
			]
		]);
	}

	public function testDashboardHoneycombWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for creating honeycomb widgets'])->waitUntilReady();

		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$form = $dashboard->edit()->addWidget()->waitUntilReady()->asForm();
		if ($form->getField('Type')->getValue() !== 'Honeycomb') {
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Honeycomb')]);
		}

		// Check fields maxlengths.
		$maxlengths = [
			'Name' => 255,
			'id:host_tags_0_tag' => 255,
			'id:host_tags_0_value' => 255,
			'id:item_tags_0_tag' => 255,
			'id:item_tags_0_value' => 255,
			'id:primary_label' => 2048,
			'id:primary_label_decimal_places' => 1,
			'id:primary_label_units' => 2048,
			'id:primary_label_size' => 3,
			'id:secondary_label' => 2048,
			'id:secondary_label_decimal_places' => 1,
			'id:secondary_label_units' => 2048,
			'id:secondary_label_size' => 3
		];

		foreach ($maxlengths as $field => $maxlength) {
			$this->assertEquals($maxlength, $form->getField($field)->getAttribute('maxlength'));
		}

		// Check default values.
		$default_values = [
			'Name' => '',
			'Refresh interval' => 'Default (1 minute)',
			'Host groups' => '',
			'Hosts' => '',
			'Host tags' => 'And/Or',
			'Item pattern' => '',
			'Item tags' => 'And/Or',
			'Show hosts in maintenance' => false,
			'Advanced configuration' => false,
			'id:host_tags_0_tag' => '',
			'id:host_tags_0_value' => '',
			'id:item_tags_0_tag' => '',
			'id:item_tags_0_value' => '',
			'id:show_1' => true,
			'id:show_2' => true,
			'id:primary_label_type_0' => 'Text',
			'Text' => '{HOST.NAME}',
			'id:primary_label_size_type_0' => 'Auto',
			'id:primary_label_bold' => false,
			'id:secondary_label_type_1' => 'Value',
			'id:secondary_label_decimal_places' => 2,
			'id:secondary_label_size_type_0' => 'Auto',
			'id:secondary_label_bold' => true,
			'id:secondary_label_units' => '',
			'id:secondary_label_units_pos' => 'After value'
		];

		$form->checkValue($default_values);

		// Check Select popup dropdowns for Host groups and Hosts.
		$popup_menu_selector = 'xpath:.//button[contains(@class, "zi-chevron-down")]';
		$host_groups = ['Host groups', 'Widget'];
		$hosts = ['Hosts', 'Widget', 'Dashboard'];

		foreach (['Host groups', 'Hosts'] as $label) {
			$field = $form->getField($label);

			// Check Select dropdown menu button.
			$menu = $field->query($popup_menu_selector)->asPopupButton()->one()->getMenu();
			$this->assertEquals(($label === 'Host groups') ? $host_groups : $hosts, $menu->getItems()->asText());

			// After selecting Dashboard from dropdown menu, check hint and field value.
			if ($label === 'Hosts') {
				$field->query($popup_menu_selector)->asPopupButton()->one()->getMenu()->select('Dashboard');
				$form->checkValue(['Hosts' => 'Dashboard']);
				$this->assertTrue($field->query('xpath:.//span[@data-hintbox-contents="Dashboard is used as data source."]')
						->one()->isVisible()
				);
			}

			// After selecting Widget from dropdown menu, check overlay dialog appearance and title.
			$field->query($popup_menu_selector)->asPopupButton()->one()->getMenu()->select('Widget');
			$dialogs = COverlayDialogElement::find()->all();
			$this->assertEquals('Widget', $dialogs->last()->waitUntilReady()->getTitle());
			$dialogs->last()->close(true);
		}

		// After clicking on Select button, check overlay dialog appearance and title.
		foreach (['Host groups', 'Hosts', 'Item pattern'] as $label) {
			$field = $form->getField($label);
			$field->query('button:Select')->waitUntilCLickable()->one()->click();
			$dialogs = COverlayDialogElement::find()->all();
			$label = ($label === 'Item pattern') ? 'Items' : $label;
			$this->assertEquals($label, $dialogs->last()->waitUntilReady()->getTitle());
			$dialogs->last()->close(true);
		}

		// Check Show checkboxes and their values.
		$show = $form->getField('Show')->asCheckboxList();
		$this->assertEquals(['Primary label', 'Secondary label'], $show->getLabels()->asText());
		$this->assertTrue($show->isEnabled());

		// Check Add/Remove buttons for Tags.
		foreach (['Host tags', 'Item tags'] as $tags) {
			$tag_form = $form->query('xpath:.//label[text()='.CXPathHelper::escapeQuotes($tags).']/following::table[1]')->one();
			foreach (['Add', 'Remove'] as $button) {
				$this->assertTrue($tag_form->query('button', $button)->one()->isClickable());
			}
		}

		// Fields layout after Advanced configuration expand.
		$form->fill(['Advanced configuration' => true]);
		$this->query('id:lbl_bg_color')->one()->waitUntilVisible();

		// Check Primary and Secondary label fields that disappear after checking them.
		$advanced_configuration = ['Primary label' => '{HOST.NAME}', 'Secondary label' => '{{ITEM.LASTVALUE}.fmtnum(2)}'];

		foreach ($advanced_configuration as $label => $text) {
			$label_fields = $form->getLabel($label)->query('xpath:./following::div[contains(@class, "fields-group ")]')->one();
			$this->assertTrue($label_fields->isVisible());

			// Type radio button. After changing them, some fields appears and other disappear.
			$type_label = $label_fields->query('xpath:.//ul[contains(@id, "_label_type")]')->asSegmentedRadio()->one();
			$this->assertEquals(['Text', 'Value'], $type_label->getLabels()->asText());

			foreach (['Text', 'Value'] as $type_values) {
				$type_label->select($type_values);

				if ($type_values === 'Text') {
					$this->assertEquals($text, $label_fields->query('xpath:.//textarea')->one()->getText());

					// Check hintboxes.
					$hint_text = "Supported macros:".
						"\n{HOST.*}".
						"\n{ITEM.*}".
						"\n{INVENTORY.*}".
						"\nUser macros";

					$form->getLabel('Text')->query('xpath:./button[@data-hintbox]')->one()->click();
					$hint = $this->query('xpath://div[@data-hintboxid]')->waitUntilVisible();
					$this->assertEquals($hint_text, $hint->one()->getText());
					$hint->one()->query('xpath:.//button[@class="btn-overlay-close"]')->one()->click();
					$hint->waitUntilNotPresent();
				}
				else {
					// New fields and check box appears after selecting Type - Value.
					$units_checkbox = $label_fields->query('xpath:.//input[contains(@id, "label_units_show")]')
							->asCheckbox()->one();
					$units_input = $label_fields->query('xpath:.//div[contains(@class, "form-field")]//input[contains(@id,'.
							' "_label_units")]')->one();
					$position_dropdown = $label_fields->query('tag:z-select')->asDropdown()->one();
					$this->assertEquals(['Before value', 'After value'], $position_dropdown->getOptions()->asText());

					// Checking out Units checkbox - disable Units fields.
					foreach ([false, true] as $status) {
						$units_checkbox->set($status);
						$this->assertTrue($units_checkbox->isChecked($status));
						$this->assertTrue($units_input->isEnabled($status));
						$this->assertTrue($position_dropdown->isEnabled($status));
					}
				}
			}

			// Check size radio button and appearance of new field after clicking on Custom.
			$size = $label_fields->query('xpath:.//ul[contains(@id, "label_size_type")]')->asSegmentedRadio()->one();
			$this->assertEquals(['Auto', 'Custom'], $size->getLabels()->asText());

			// Primary and Secondary label has different values in size custom field.
			$size_input_value = ($label === 'Primary label') ? '20' : '30';

			// After clicking on Custom button, new input field appears.
			$size->select('Custom');
			$size_input_selector = 'xpath:.//input[@type="text" and contains(@id, "_label_size")]';
			$this->assertTrue($label_fields->query($size_input_selector)->one()->isVisible());
			$this->assertEquals($size_input_value, $label_fields->query($size_input_selector)->one()->getAttribute('value'));

			// After clicking on Auto, input field disappear.
			$size->select('Auto');
			$this->assertFalse($label_fields->query($size_input_selector)->one()->isVisible());

			// Check Bold checkbox. Primary label bold - unchecked. Secondary label bold - checked-in.
			$bold = $label_fields->query('xpath:.//input[contains(@id, "_label_bold")]')->asCheckbox()->one();
			$this->assertTrue($bold->isChecked($label === 'Secondary label'));

			// Uncheck Primary/Secondary label.
			$show->query('xpath:.//label[text()='.CXPathHelper::escapeQuotes($label).
					']/../input')->one()->asCheckbox()->set(false);
			$this->assertFalse($label_fields->isVisible());
		}

		// Thresholds warning message.
		$form->getLabel('Thresholds')->query('xpath:./button[@data-hintbox-contents]')->one()->click();
		$warning = $this->query('xpath://div[@data-hintboxid]')->waitUntilVisible();
		$this->assertEquals('This setting applies only to numeric data.', $warning->one()->getText());
		$warning->one()->query('xpath:.//button[@class="btn-overlay-close"]')->one()->click();
		$warning->waitUntilNotPresent();

		// Color interpolation checkbox should be disabled and checked-in.
		$color_interpolation = $form->query('id:interpolation')->asCheckbox()->one();
		$this->assertFalse($color_interpolation->isEnabled());
		$this->assertTrue($color_interpolation->isChecked());

		// Threshold table with adding and removing lines.
		$table = $form->query('id:thresholds-table')->asTable()->one();
		$this->assertEquals(['', 'Threshold', 'Action'], $table->getHeadersText());

		// Check added threshold colors.
		$table->query('button:Add')->one()->click();
		$colorpicker = $table->query('class:color-picker-preview')->one();
		$this->assertEquals('#FF465C', $table->query('class:color-picker-preview')->one()->getAttribute('title'));
		$this->assertTrue($colorpicker->isClickable());

		// Check added threshold input field.
		$threshold_input = $table->query('xpath:.//input[@type="text"]')->one();
		$this->assertEquals(255, $threshold_input->getAttribute('maxlength'));

		// Click on Remove button in Threshold, and check that it disappeared with input and colorpicker.
		$remove = $table->query('button:Remove')->one();
		$remove->click();
		$this->assertFalse($colorpicker->isVisible());
		$this->assertFalse($threshold_input->isVisible());
		$this->assertFalse($remove->isVisible());

		// Background color picker has default value.
		$this->assertEquals('Use default', $form->query('id:lbl_bg_color')->one()->getAttribute('title'));
	}

	public static function getCreateData() {
		return [
			// #0.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'No item',
						'Item pattern' => ''
					],
					'error_message' => [
						'Invalid parameter "Item pattern": cannot be empty.'
					]
				]
			],
			// #1.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item pattern' => 'test',
						'id:show_1' => false, // Show - Primary label.
						'id:show_2' => false // Show - Secondary label.
					],
					'error_message' => [
						'Invalid parameter "Show": at least one option must be selected.'
					]
				]
			],
			// #2.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item pattern' => 'test',
						'Background colour' => 'tests1'
					],
					'error_message' => [
						'Invalid parameter "Background colour": a hexadecimal colour code (6 symbols) is expected.'
					]
				]
			],
			// #3.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item pattern' => 'test',
					],
					'thresholds' => [
						[
							'threshold' => '1',
							'color' => 'TESTS1'
						]
					],
					'error_message' => [
						'Invalid parameter "Thresholds/1/color": a hexadecimal colour code (6 symbols) is expected.'
					]
				]
			],
			// #4.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item pattern' => 'test',
					],
					'thresholds' => [
						[
							'threshold' => 'test',
							'color' => 'FF465C'
						]
					],
					'error_message' => [
						'Invalid parameter "Thresholds/1/threshold": a number is expected.'
					]
				]
			],
			// #5.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item pattern' => 'test',
					],
					'thresholds' => [
						[
							'threshold' => '1',
							'color' => 'FF465C'
						],
						[
							'threshold' => '1',
							'color' => 'TESTS1'
						]
					],
					'error_message' => [
						'Invalid parameter "Thresholds/2/color": a hexadecimal colour code (6 symbols) is expected.'
					]
				]
			],
			// #6.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item pattern' => 'test',
					],
					'thresholds' => [
						[
							'threshold' => '1',
							'color' => 'FF465C'
						],
						[
							'threshold' => 'test',
							'color' => 'FF465C'
						]
					],
					'error_message' => [
						'Invalid parameter "Thresholds/2/threshold": a number is expected.'
					]
				]
			],
			// #7.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item pattern' => 'test',
					],
					'thresholds' => [
						[
							'threshold' => '1',
							'color' => 'FF465C'
						],
						[
							'threshold' => '1',
							'color' => 'FF465C'
						]
					],
					'error_message' => [
						'Invalid parameter "Thresholds/2": value (threshold)=(1) already exists.'
					]
				]
			],
			// #8.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item pattern' => 'test',
						'id:primary_label' => '' // Primary label text field.
					],
					'error_message' => [
						'Invalid parameter "Primary label: Text": cannot be empty.'
					]
				]
			],
			// #9.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item pattern' => 'test',
						'id:primary_label_size_type' => 'Custom', // Primary label Size - custom.
						'id:primary_label_size' => '' // Primary label Custom size input field.
					],
					'error_message' => [
						'Invalid parameter "Primary label: Size": value must be one of 1-100.'
					]
				]
			],
			// #10.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item pattern' => 'test',
						'id:primary_label_type' => 'Value',
						'id:primary_label_decimal_places' => 9,
						'id:secondary_label_type' => 'Value',
						'id:secondary_label_decimal_places' => 9
					],
					'error_message' => [
						'Invalid parameter "Primary label: Decimal places": value must be one of 0-6.',
						'Invalid parameter "Secondary label: Decimal places": value must be one of 0-6.'
					]
				]
			],
			// #11.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item pattern' => 'test',
						'id:secondary_label_type' => 'Text',
						'id:primary_label_size_type' => 'Custom', // Primary label Size - custom.
						'id:secondary_label_size_type' => 'Custom', // Secondary label Size - custom.
						'id:primary_label_size' => '', // Primary label Custom size input field.
						'id:secondary_label_size' => '',  // Secondary label Custom size input field.
						'id:primary_label' => '', // Primary label text field.
						'id:secondary_label' => '' // Secondary label text field.
					],
					'error_message' => [
						'Invalid parameter "Primary label: Text": cannot be empty.',
						'Invalid parameter "Primary label: Size": value must be one of 1-100.',
						'Invalid parameter "Secondary label: Text": cannot be empty.',
						'Invalid parameter "Secondary label: Size": value must be one of 1-100.'
					]
				]
			],
			// #12.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item pattern' => '',
						'id:secondary_label_type' => 'Text',
						'id:primary_label_size_type' => 'Custom', // Primary label Size - custom.
						'id:secondary_label_size_type' => 'Custom', // Secondary label Size - custom.
						'id:primary_label_size' => '', // Primary label Custom size input field.
						'id:secondary_label_size' => '',  // Secondary label Custom size input field.
						'id:primary_label' => '', // Primary label text field.
						'id:secondary_label' => '', // Secondary label text field.
						'xpath:.//input[@id="primary_label_color"]/..' => 'TESTS1', // Primary label Color.
						'xpath:.//input[@id="secondary_label_color"]/..' => 'TESTS2' // Secondary label Color.
					],
					'thresholds' => [
						[
							'threshold' => '1',
							'color' => 'TESTS1'
						]
					],
					'error_message' => [
						'Invalid parameter "Item pattern": cannot be empty.',
						'Invalid parameter "Primary label: Text": cannot be empty.',
						'Invalid parameter "Primary label: Size": value must be one of 1-100.',
						'Invalid parameter "Primary label: Colour": a hexadecimal colour code (6 symbols) is expected.',
						'Invalid parameter "Secondary label: Text": cannot be empty.',
						'Invalid parameter "Secondary label: Size": value must be one of 1-100.',
						'Invalid parameter "Secondary label: Colour": a hexadecimal colour code (6 symbols) is expected.',
						'Invalid parameter "Thresholds/1/color": a hexadecimal colour code (6 symbols) is expected.',
						'Invalid parameter "Thresholds/1/color": a hexadecimal colour code (6 symbols) is expected.'
					]
				]
			],
			// #13.
			[
				[
					'fields' => [
						'Name' => 'With existing item, hosts and hostgroup',
						'Item pattern' => 'Numeric for honeycomb 1',
						'Host groups' => 'Zabbix servers',
						'Hosts' => 'Host for honeycomb 1'
					]
				]
			],
			// #14.
			[
				[
					'fields' => [
						'Name' => 'Primary label only with Text, color, custom.',
						'Item pattern' => 'Numeric for honeycomb 1',
						'id:show_2' => false, // Show - Primary label.
						'id:primary_label' => '{$RANDOM}, some text, {TIME}, 12345, !@#$%^&*, {#WHY}',
						'id:primary_label_size_type' => 'Custom', // Primary label Size - custom.
						'id:primary_label_size' => 99,
						'xpath:.//input[@id="primary_label_color"]/..' => '81C784' // Primary label Color.
					]
				]
			],
			// #15.
			[
				[
					'fields' => [
						'Name' => 'Secondary label only with Text, color, custom.',
						'Item pattern' => 'Numeric for honeycomb 1',
						'id:show_1' => false, // Show - Primary label.
						'id:secondary_label_type' => 'Text',
						'id:secondary_label' => '{$RANDOM}, some text, {TIME}, 12345, !@#$%^&*, {#WHY}',
						'id:secondary_label_size_type' => 'Custom', // Primary label Size - custom.
						'id:secondary_label_size' => 99,
						'xpath:.//input[@id="secondary_label_color"]/..' => '81C784' // Secondary label Color.
					]
				]
			],
			// #16.
			[
				[
					'fields' => [
						'Name' => 'Secondary and primary labels only with values.',
						'Item pattern' => 'Numeric for honeycomb 1',
						'id:primary_label_type' => 'Value',
						'id:secondary_label_type' => 'Value',
						'id:primary_label_decimal_places' => 2,
						'id:secondary_label_decimal_places' => 2,
						'id:primary_label_bold' => true,
						'id:secondary_label_bold' => true,
						'xpath:.//input[@id="primary_label_color"]/..' => '81C784', // Primary label Color.
						'xpath:.//input[@id="secondary_label_color"]/..' => '81C784', // Primary label Color.
						'id:primary_label_units_pos' => 'Before value',
						'id:secondary_label_units_pos' => 'Before value',
						'id:primary_label_units' => 'primary',
						'id:secondary_label_units' => 'secondary'
					]
				]
			],
			// #17.
			[
				[
					'fields' => [
						'Name' => 'Dashboard in Hosts and enabled show maintenance',
						'Hosts' => 'Dashboard',
						'Item pattern' => 'Numeric for honeycomb 1',
						'Show hosts in maintenance' => true
					]
				]
			],
			// #18.
			[
				[
					'fields' => [
						'Name' => 'Different items pattern',
						'Item pattern' => [
							'Numeric for honeycomb 1',
							'random_value',
							'*',
							'<$%^&*#@^',
							'<script>alert("hi!");</script>',
							'test тест 测试 テスト ทดสอบ'
						]
					]
				]
			],
			// #19.
			[
				[
					'fields' => [
						'Name' => 'Enabled color interpolation',
						'Item pattern' => 'Numeric for honeycomb 1'
					],
					'thresholds' => [
						[
							'threshold' => '1',
							'color' => 'FF465C'
						],
						[
							'threshold' => '2',
							'color' => 'FFFF00'
						]
					]
				]
			],
			// #20.
			[
				[
					'fields' => [
						'Name' => 'Disabled color interpolation',
						'Item pattern' => 'Numeric for honeycomb 1',
						'id:interpolation' => false
					],
					'thresholds' => [
						[
							'threshold' => '1',
							'color' => 'FF465C'
						],
						[
							'threshold' => '2',
							'color' => 'FFFF00'
						]
					]
				]
			],
			// #21.
			[
				[
					'fields' => [
						'Name' => 'Correct background color',
						'Item pattern' => 'Numeric for honeycomb 1',
						'Background colour' => 'B2DFDB'
					]
				]
			],
			// #22.
			[
				[
					'fields' => [
						'Name' => 'Host and items tags',
						'Item pattern' => 'Numeric for honeycomb 1'
					],
					'tags' => [
						'item_tags' => [
							['name' => 'value', 'value' => '12345', 'operator' => 'Contains'],
							['name' => '@#$%@', 'value' => 'a1b2c3d4', 'operator' => 'Equals'],
							['name' => 'AvF%21', 'operator' => 'Exists'],
							['name' => '_', 'operator' => 'Does not exist'],
							['name' => 'кириллица', 'value' => 'BcDa', 'operator' => 'Does not equal'],
							['name' => 'aaa6 😅', 'value' => 'bbb6 😅', 'operator' => 'Does not contain']
						],
						'host_tags' => [
							['name' => 'value', 'value' => '12345', 'operator' => 'Contains'],
							['name' => '@#$%@', 'value' => 'a1b2c3d4', 'operator' => 'Equals'],
							['name' => 'AvF%21', 'operator' => 'Exists'],
							['name' => '_', 'operator' => 'Does not exist'],
							['name' => 'кириллица', 'value' => 'BcDa', 'operator' => 'Does not equal'],
							['name' => 'aaa6 😅', 'value' => 'bbb6 😅', 'operator' => 'Does not contain']
						]
					]
				]
			],
			// #23.
			[
				[
					'fields' => [
						'Name' => 'All available fields filled',
						'Item pattern' => 'Numeric for honeycomb 1',
						'Refresh interval' => 'No refresh',
						'Host groups' => 'Zabbix servers',
						'Hosts' => 'Host for honeycomb 1',
						'id:primary_label_type' => 'Value',
						'id:secondary_label_type' => 'Text',
						'id:primary_label_decimal_places' => 6,
						'id:secondary_label' => 'some text',
						'id:primary_label_bold' => true,
						'id:secondary_label_bold' => false,
						'id:primary_label_size' => 99,
						'id:secondary_label_size' => 99,
						'xpath:.//input[@id="primary_label_color"]/..' => '81C784', // Primary label Color.
						'xpath:.//input[@id="secondary_label_color"]/..' => '81C784', // Primary label Color.
						'id:primary_label_units_pos' => 'Before value',
						'id:primary_label_units' => 'primary',
						'Background colour' => 'B2DFDB'
					],
					'thresholds' => [
						[
							'threshold' => '1',
							'color' => 'FF465C'
						],
						[
							'threshold' => '2',
							'color' => 'FFFF00'
						]
					]
				]
			]
		];
	}

	/**
	 * Create Honeycomb widget.
	 *
	 * @dataProvider getCreateData
	 */
	public function testDashboardHoneycombWidget_Create($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for creating honeycomb widgets'])->waitUntilReady();
		$this->checkWidgetForm($data, 'create');
	}

	/**
	 * Honeycomb widget simple update without any field change.
	 */
	public function testDashboardHoneycombWidget_SimpleUpdate() {
		// Hash before simple update.
		$old_hash = CDBHelper::getHash(self::SQL);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for updating honeycomb widget'])->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dashboard->edit()->getWidget('UpdateHoneycomb')->edit()->submit();
		$dashboard->save();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Compare old hash and new one.
		$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
	}

	/**
	 * Update Honeycomb widget.
	 *
	 * @dataProvider getCreateData
	 */
	public function testDashboardHoneycombWidget_Update($data) {
		if (!array_key_exists('expected', $data)) {
			$this->updateToDefault();
		}

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for updating honeycomb widget'])->waitUntilReady();
		$this->checkWidgetForm($data, 'update');
	}

	/**
	 * Delete Honeycomb widget.
	 */
	public function testDashboardHoneycombWidget_Delete() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for deleting honeycomb widget'])->waitUntilReady();
		$dashboard = CDashboardElement::find()->one()->waitUntilReady()->edit();
		$widget = $dashboard->getWidget('DeleteHoneycomb');
		$this->assertTrue($widget->isEditable());
		$dashboard->deleteWidget('DeleteHoneycomb');
		$widget->waitUntilNotPresent();
		$dashboard->save();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Check that widget is not present on dashboard and in DB.
		$this->assertFalse($dashboard->getWidget('DeleteHoneycomb', false)->isValid());
		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM widget_field wf'.
			' LEFT JOIN widget w'.
			' ON w.widgetid=wf.widgetid'.
			' WHERE w.name='.zbx_dbstr('DeleteHoneycomb')
		));
	}

	public static function getDisplayData() {
		return [
			// #0.
			[
				[
					'fields' => [
						'Name' => 'Host name macros',
						'Item pattern' => 'Display item 1',
						'id:primary_label_type' => 'Text',
						'id:secondary_label_type' => 'Text',
						'id:primary_label' => '{HOST.NAME}',
						'id:secondary_label' => '{HOST.NAME}'
					],
					'result' => 'Display'
				]
			],
			// #1.
			[
				[
					'fields' => [
						'Name' => 'Item key macros',
						'Item pattern' => 'Display item 1',
						'id:primary_label_type' => 'Text',
						'id:secondary_label_type' => 'Text',
						'id:primary_label' => '{ITEM.KEY}',
						'id:secondary_label' => '{ITEM.KEY}'
					],
					'result' => 'honey_display_1'
				]
			],
			// #2.
			[
				[
					'fields' => [
						'Name' => 'Item last value macros',
						'Item pattern' => 'Display item 1',
						'id:primary_label_type' => 'Text',
						'id:secondary_label_type' => 'Text',
						'id:primary_label' => '{ITEM.LASTVALUE}',
						'id:secondary_label' => '{ITEM.LASTVALUE}'
					],
					'result' => '100'
				]
			],
			// #3.
			[
				[
					'fields' => [
						'Name' => 'Emoji displayed',
						'Item pattern' => 'Display item 1',
						'id:primary_label_type' => 'Text',
						'id:secondary_label_type' => 'Text',
						'id:primary_label' => '🙂🙃',
						'id:secondary_label' => '🙂🙃'
					],
					'result' => '🙂🙃'
				]
			],
			// #4.
			[
				[
					'fields' => [
						'Name' => 'Symbols displayed',
						'Item pattern' => 'Display item 1',
						'id:primary_label_type' => 'Text',
						'id:secondary_label_type' => 'Text',
						'id:primary_label' => 'āēīõšŗ$%^&*()',
						'id:secondary_label' => 'āēīõšŗ$%^&*()'
					],
					'result' => 'āēīõšŗ$%^&*()'
				]
			],
			// #5.
			[
				[
					'fields' => [
						'Name' => 'Simple text displayed',
						'Item pattern' => 'Display item 1',
						'id:primary_label_type' => 'Text',
						'id:secondary_label_type' => 'Text',
						'id:primary_label' => 'Text for testing',
						'id:secondary_label' => 'Text for testing'
					],
					'result' => 'Text for testing'
				]
			],
			// #6.
			[
				[
					'fields' => [
						'Name' => 'User macros displayed',
						'Item pattern' => 'Display item 1',
						'id:primary_label_type' => 'Text',
						'id:secondary_label_type' => 'Text',
						'id:primary_label' => '{$TEXT}',
						'id:secondary_label' => '{$TEXT}'
					],
					'result' => 'text_macro'
				]
			],
			// #7.
			[
				[
					'fields' => [
						'Name' => 'Secret macros displayed',
						'Item pattern' => 'Display item 1',
						'id:primary_label_type' => 'Text',
						'id:secondary_label_type' => 'Text',
						'id:primary_label' => '{$SECRET_TEXT}',
						'id:secondary_label' => '{$SECRET_TEXT}'
					],
					'result' => '******'
				]
			],
			// #8.
			[
				[
					'fields' => [
						'Name' => 'LLD macros displayed',
						'Item pattern' => 'Display item 1',
						'id:primary_label_type' => 'Text',
						'id:secondary_label_type' => 'Text',
						'id:primary_label' => '{#LLD}',
						'id:secondary_label' => '{#LLD}'
					],
					'result' => '{#LLD}'
				]
			],
			// #9.
			[
				[
					'fields' => [
						'Name' => 'Non existing global macros displayed',
						'Item pattern' => 'Display item 1',
						'id:primary_label_type' => 'Text',
						'id:secondary_label_type' => 'Text',
						'id:primary_label' => '{HELLO.WORLD}',
						'id:secondary_label' => '{HELLO.WORLD}'
					],
					'result' => '{HELLO.WORLD}'
				]
			],
			// #10.
			[
				[
					'fields' => [
						'Name' => 'Value decimal 6',
						'Item pattern' => 'Display item 1',
						'id:primary_label_type' => 'Value',
						'id:secondary_label_type' => 'Value',
						'id:primary_label_decimal_places' => '6',
						'id:secondary_label_decimal_places' => '6'
					],
					'result' => '100.000000'
				]
			],
			// #11.
			[
				[
					'fields' => [
						'Name' => 'Value decimal 0',
						'Item pattern' => 'Display item 1',
						'id:primary_label_type' => 'Value',
						'id:secondary_label_type' => 'Value',
						'id:primary_label_decimal_places' => '0',
						'id:secondary_label_decimal_places' => '0'
					],
					'result' => '100'
				]
			],
			// #12.
			[
				[
					'fields' => [
						'Name' => 'Before displayed units',
						'Item pattern' => 'Display item 1',
						'id:primary_label_type' => 'Value',
						'id:secondary_label_type' => 'Value',
						'id:primary_label_decimal_places' => '0',
						'id:secondary_label_decimal_places' => '0',
						'id:primary_label_units_pos' => 'Before value',
						'id:secondary_label_units_pos' => 'Before value',
						'id:primary_label_units' => 'before',
						'id:secondary_label_units' => 'before',
					],
					'result' => 'before 100'
				]
			],
			// #13.
			[
				[
					'fields' => [
						'Name' => 'After displayed units',
						'Item pattern' => 'Display item 1',
						'id:primary_label_type' => 'Value',
						'id:secondary_label_type' => 'Value',
						'id:primary_label_decimal_places' => '0',
						'id:secondary_label_decimal_places' => '0',
						'id:primary_label_units_pos' => 'After value',
						'id:secondary_label_units_pos' => 'After value',
						'id:primary_label_units' => 'after',
						'id:secondary_label_units' => 'after',
					],
					'result' => '100 after'
				]
			],
			// #14.
			[
				[
					'fields' => [
						'Name' => 'Emoji displayed units',
						'Item pattern' => 'Display item 1',
						'id:primary_label_type' => 'Value',
						'id:secondary_label_type' => 'Value',
						'id:primary_label_decimal_places' => '0',
						'id:secondary_label_decimal_places' => '0',
						'id:primary_label_units_pos' => 'After value',
						'id:secondary_label_units_pos' => 'After value',
						'id:primary_label_units' => '🙂🙃',
						'id:secondary_label_units' => '🙂🙃',
					],
					'result' => '100 🙂🙃'
				]
			],
			// #15.
			[
				[
					'fields' => [
						'Name' => 'Symbols displayed units',
						'Item pattern' => 'Display item 1',
						'id:primary_label_type' => 'Value',
						'id:secondary_label_type' => 'Value',
						'id:primary_label_decimal_places' => '0',
						'id:secondary_label_decimal_places' => '0',
						'id:primary_label_units_pos' => 'After value',
						'id:secondary_label_units_pos' => 'After value',
						'id:primary_label_units' => 'āēīõšŗ$%^&*()',
						'id:secondary_label_units' => 'āēīõšŗ$%^&*()',
					],
					'result' => '100 āēīõšŗ$%^&*()'
				]
			],
			// #16.
			[
				[
					'fields' => [
						'Name' => 'User macros displayed units',
						'Item pattern' => 'Display item 1',
						'id:primary_label_type' => 'Value',
						'id:secondary_label_type' => 'Value',
						'id:primary_label_decimal_places' => '0',
						'id:secondary_label_decimal_places' => '0',
						'id:primary_label_units_pos' => 'After value',
						'id:secondary_label_units_pos' => 'After value',
						'id:primary_label_units' => '{$TEXT}',
						'id:secondary_label_units' => '{$TEXT}',
					],
					'result' => '100 {$TEXT}'
				]
			],
			// #17.
			[
				[
					'fields' => [
						'Name' => 'Global macros displayed units',
						'Item pattern' => 'Display item 1',
						'id:primary_label_type' => 'Value',
						'id:secondary_label_type' => 'Value',
						'id:primary_label_decimal_places' => '0',
						'id:secondary_label_decimal_places' => '0',
						'id:primary_label_units_pos' => 'After value',
						'id:secondary_label_units_pos' => 'After value',
						'id:primary_label_units' => '{HOST.NAME}',
						'id:secondary_label_units' => '{HOST.NAME}',
					],
					'result' => '100 {HOST.NAME}'
				]
			],
			// #18
			[
				[
					'fields' => [
						'Name' => 'Colors for value and background',
						'Item pattern' => 'Display item 1',
						'id:primary_label_type' => 'Text',
						'id:secondary_label_type' => 'Text',
						'id:primary_label' => 'COLOR',
						'id:secondary_label' => 'COLOR',
						'xpath:.//input[@id="primary_label_color"]/..' => '66BB6A', // Primary label Color.
						'xpath:.//input[@id="secondary_label_color"]/..' => '80DEEA', // Primary label Color.
						'Background colour' => 'D1C4E9'
					],
					'colors' => [
						'xpath://*[@class="svg-honeycomb-cell"]' => '#D1C4E9',
						'xpath://*[contains(@class, "svg-honeycomb-label-primary")]' => 'color: rgb(102, 187, 106)',
						'xpath://*[contains(@class, "svg-honeycomb-label-secondary")]' => 'color: rgb(128, 222, 234)'
					],
					'result' => 'COLOR'
				]
			]
		];
	}

	/**
	 * Check different data display on Honeycomb widget.
	 *
	 * @dataProvider getDisplayData
	 */
	public function testDashboardHoneycombWidget_Display($data) {
		$this->updateToDefault();
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for updating honeycomb widget'])->waitUntilReady();
		$this->checkWidgetForm($data, 'update', false);
		CDashboardElement::find()->waitUntilReady()->one()->save();

		// Check message that dashboard saved.
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$this->page->waitUntilReady();

		// Check that correct value displayed on honeycomb.
		foreach (['svg-honeycomb-label-primary', 'svg-honeycomb-label-secondary'] as $selector) {
			$displayed = $this->query('xpath://*[contains(@class, '.CXPathHelper::escapeQuotes($selector).
					')]/div')->one()->getText();
			$this->assertEquals($displayed, $data['result']);
		}

		// Check that correct colors displayed.
		if (array_key_exists('colors', $data)) {
			foreach ($data['colors'] as $color_selector => $color) {
				$displayed_color = $this->query($color_selector)->one()->getAttribute('style');
				$this->assertStringContainsString($color, $displayed_color);
			}
		}
	}

	/**
	 * Test function for assuring that all item types available in Honeycomb widget.
	 */
	public function testDashboardHoneycombWidget_CheckAvailableItems() {
		$this->checkAvailableItems('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for deleting honeycomb widget'], 'Honeycomb'
		);
	}

	public function getCancelData() {
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
	 * Check cancel scenarios for Honeycomb widget.
	 *
	 * @dataProvider getCancelData
	 */
	public function testDashboardHoneycombWidget_Cancel($data) {
		$old_hash = CDBHelper::getHash(self::SQL);
		$new_name = 'Widget to be cancelled';

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for canceling honeycomb widget']
		);
		$dashboard = CDashboardElement::find()->one()->edit();
		$old_widget_count = $dashboard->getWidgets()->count();

		// Start updating or creating a widget.
		if (CTestArrayHelper::get($data, 'update', false)) {
			$form = $dashboard->getWidget('CancelHoneycomb')->edit();
		}
		else {
			$form = $dashboard->addWidget()->asForm();

			if ($form->getField('Type')->getValue() !== 'Honeycomb') {
				$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Honeycomb')]);
			}
		}
		$form->fill([
			'Name' => $new_name,
			'Advanced configuration' => true,
			'Item pattern' => 'Test_cancel',
			'Refresh interval' => '15 minutes',
			'Host groups' => 'Zabbix servers',
			'Hosts' => 'Host for honeycomb 1',
			'id:primary_label_type' => 'Value',
			'id:secondary_label_type' => 'Text',
			'id:primary_label_decimal_places' => 6,
			'id:secondary_label' => 'some text'
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
				foreach (['CancelHoneycomb' => true, $new_name => false] as $name => $valid) {
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
		$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
	}

	/**
	 * Check different comb compositions for Honeycomb widget.
	 */
	public function testDashboardHoneycombWidget_Screenshots() {
		$this->page->login();

		for ($i = 1; $i <= 5; $i++) {
			$this->page->open('zabbix.php?action=dashboard.view&dashboardid='.
					self::$dashboardid['Dashboard for Honeycomb screenshot'].'&page='.$i)->waitUntilReady();

			$element = CDashboardElement::find()->one()->getWidget('Honeycomb');
			$this->assertScreenshot($element, 'honeycomb_'.$i);
		}
	}

	/**
	 * Get threshold table element with mapping set.
	 *
	 * @return CMultifieldTable
	 */
	protected function getTreshholdTable() {
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

	/**
	 * Create or update Honeycomb widget and check after.
	 *
	 * @param array   $data  	data provider
	 * @param string  $action	create/update honeycomb widget
	 * @param boolean $check	check honeycomb values after creation or not
	 */
	protected function checkWidgetForm($data, $action, $check = true) {
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();

		// Check hash if TEST_BAD and check widget amount if TEST_GOOD
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			// Hash before update.
			$old_hash = CDBHelper::getHash(self::SQL);
		}
		else {
			$old_widget_count = $dashboard->getWidgets()->count();
		}

		$form = ($action === 'create')
			? $dashboard->edit()->addWidget()->waitUntilReady()->asForm()
			: $dashboard->getWidget('UpdateHoneycomb')->edit()->asForm();

		if ($form->getField('Type')->getValue() !== 'Honeycomb') {
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Honeycomb')]);
		}

		$form->fill(['Advanced configuration' => true]);
		$this->query('id:lbl_bg_color')->one()->waitUntilVisible();

		// Fill Thresholds values.
		if (array_key_exists('thresholds', $data)) {
			$this->getTreshholdTable()->fill($data['thresholds']);
			unset($data['thresholds']);
		}

		if (array_key_exists('tags', $data)) {
			$this->checkCreateTags($data['tags'], false);
		}

		$form->fill($data['fields']);
		$form->submit();

		if ($check) {
			if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
				$this->assertMessage(TEST_BAD, null, $data['error_message']);
				COverlayDialogElement::find()->one()->close();
				$dashboard->save();
				$this->assertMessage(TEST_GOOD, 'Dashboard updated');

				// Compare old hash and new one.
				$this->assertEquals($old_hash, CDBHelper::getHash(self::SQL));
			}
			else {
				// Make sure that the widget is present before saving the dashboard.
				$header = CTestArrayHelper::get($data['fields'], 'Name', 'Honeycomb');
				$dashboard->getWidget($header);
				$dashboard->save();

				// Check message that dashboard saved.
				$this->assertMessage(TEST_GOOD, 'Dashboard updated');

				// Check widget amount that it is added.
				$this->assertEquals($old_widget_count + (($action === 'create') ? 1 : 0), $dashboard->getWidgets()->count());

				$form = $dashboard->getWidget($header)->edit()->asForm();
				$form->fill(['Advanced configuration' => true]);
				$this->query('id:lbl_bg_color')->one()->waitUntilVisible();

				if (array_key_exists('tags', $data)) {
					$this->checkCreateTags($data['tags']);
				}

				// Check Thresholds values.
				if (array_key_exists('thresholds', $data)) {
					$this->getTreshholdTable()->checkValue($data['thresholds']);
				}

				$form->checkValue($data['fields']);
				COverlayDialogElement::find()->one()->close();
				$dashboard->save();
				$this->assertMessage(TEST_GOOD, 'Dashboard updated');
			}
		}
	}

	/**
	 * Check created tags.
	 */
	protected function checkCreateTags($tags, $check = true) {
		foreach ($tags as $tag => $values) {
			$this->setTagSelector(($tag === 'item_tags') ? 'id:tags_table_item_tags' : 'id:tags_table_host_tags');

			if ($check) {
				$this->assertTags($values);
			} else {
				$this->setTags($values);
			}
		}
	}

	/**
	 * Update honeycomb to default values.
	 */
	protected function updateToDefault() {
		CDataHelper::call('dashboard.update', [
			[
				'dashboardid' => self::$dashboardid['Dashboard for updating honeycomb widget'],
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'honeycomb',
								'name' => 'UpdateHoneycomb',
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 5,
								'fields' => [
									[
										'type' => 1,
										'name' => 'items.0',
										'value' => 'Numeric for honeycomb 1'
									]
								]
							]
						]
					]
				]
			]
		]);
	}
}
