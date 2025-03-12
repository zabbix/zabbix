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


require_once dirname(__FILE__).'/../common/testWidgets.php';

/**
 * @backup dashboard, globalmacro
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
	 * Ids of created Dashboards for Honeycomb widget check.
	 */
	protected static $dashboardid;

	/**
	 * Hash before TEST_BAD scenario.
	 */
	protected static $old_hash;

	/**
	 * Widget amount before create/update.
	 */
	protected static $old_widget_count;

	/**
	 * Id of dashboard for update scenarios.
	 */
	protected static $disposable_dashboard_id;

	const DASHBOARD_FOR_MACRO_FUNCTIONS = 'Dashboard for testing macro functions';
	const WIDGET_FOR_MACRO_FUNCTIONS = 'Widget for testing macro functions';

	public static function prepareHoneycombWidgetData() {
		CDataHelper::call('hostgroup.create', [
			[
				'name' => 'Maintenance group'
			],
			[
				'name' => 'Host with tags'
			],
			[
				'name' => 'Items with tags'
			]
		]);
		$groupids = CDataHelper::getIds('name');

		$response = CDataHelper::createHosts([
			[
				'host' => 'Host for honeycomb 1',
				'groups' => [['groupid' => 4]], // Zabbix servers.
				'items' => [
					[
						'name' => 'Numeric for honeycomb 1',
						'key_' => 'num_honey_1',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
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
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Display item 2',
						'key_' => 'honey_display_2',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Display item 3',
						'key_' => 'honey_display_3',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Display item 4',
						'key_' => 'honey_display_4',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Display item 5',
						'key_' => 'honey_display_5',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			],
			[
				'host' => 'Host for maintenance filter',
				'groups' => [['groupid' => $groupids['Maintenance group']]],
				'items' => [
					[
						'name' => 'Maintenance item',
						'key_' => 'maintenance_1',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			],
			[
				'host' => 'Host with tags',
				'groups' => [['groupid' => $groupids['Host with tags']]],
				'tags' => [
					[
						'tag' => 'host_tag_1',
						'value' => 'host_val_1'
					]
				],
				'items' => [
					[
						'name' => 'Host tag item',
						'key_' => 'host_tag_1',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			],
			[
				'host' => 'Host with items with tags',
				'groups' => [['groupid' => $groupids['Items with tags']]],
				'items' => [
					[
						'name' => 'Item tag 1',
						'key_' => 'item_tag_1',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'tags' => [
							[
								'tag' => 'item_tag_1',
								'value' => 'item_val_1'
							]
						]
					],
					[
						'name' => 'Item tag 2',
						'key_' => 'item_tag_2',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'tags' => [
							[
								'tag' => 'item_tag_2',
								'value' => 'item_val_2'
							]
						]
					],
					[
						'name' => 'Item tag 3',
						'key_' => 'item_tag_3',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'tags' => [
							[
								'tag' => 'item_tag_3',
								'value' => 'item_val_3'
							]
						]
					],
					[
						'name' => 'Item tag 4',
						'key_' => 'item_tag_4',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'tags' => [
							[
								'tag' => 'item_tag_1',
								'value' => 'item_val_1'
							]
						]
					],
					[
						'name' => 'Item tag 5',
						'key_' => 'item_tag_5',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'tags' => [
							[
								'tag' => 'item_tag_1',
								'value' => 'item_val_5'
							]
						]
					]
				]
			]
		]);
		$itemids = $response['itemids'];
		$display_hostid = $response['hostids']['Display'];
		$maintenance_hostid = $response['hostids']['Host for maintenance filter'];

		foreach ([100, 200, 300, 400, 500] as $i => $value) {
			CDataHelper::addItemData($itemids['Display:honey_display_'.($i + 1)], $value);
		}

		// Items ids that used in filtering scenario.
		$filtered_items = [
			$itemids['Host for maintenance filter:maintenance_1'],
			$itemids['Host with tags:host_tag_1'],
			$itemids['Host with items with tags:item_tag_1'],
			$itemids['Host with items with tags:item_tag_2'],
			$itemids['Host with items with tags:item_tag_3'],
			$itemids['Host with items with tags:item_tag_4'],
			$itemids['Host with items with tags:item_tag_5']
		];

		foreach ($filtered_items as $itemid) {
			CDataHelper::addItemData($itemid, 100);
		}

		// Create Maintenance and host in maintenance.
		$maintenances = CDataHelper::call('maintenance.create', [
			[
				'name' => 'Honeycomb host maintenance',
				'active_since' => time() - 1000,
				'active_till' => time() + 31536000,
				'groups' => [['groupid' => $groupids['Maintenance group']]],
				'timeperiods' => [[]]
			]
		]);
		$maintenanceid = $maintenances['maintenanceids'][0];

		DBexecute('UPDATE hosts SET maintenanceid='.zbx_dbstr($maintenanceid).
				', maintenance_status=1, maintenance_type='.MAINTENANCE_TYPE_NORMAL.', maintenance_from='.zbx_dbstr(time()-1000).
				' WHERE hostid='.zbx_dbstr($maintenance_hostid)
		);

		CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for creating honeycomb widgets',
				'auto_start' => 0,
				'pages' => [[]]
			],
			[
				'name' => 'Dashboard for simple updating honeycomb widget',
				'auto_start' => 0,
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
									],
									[
										'type' => 1,
										'name' => 'reference',
										'value' => 'GZGZG'
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for canceling honeycomb widget',
				'auto_start' => 0,
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
									],
									[
										'type' => 1,
										'name' => 'reference',
										'value' => 'BUBUA'
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for deleting honeycomb widget',
				'auto_start' => 0,
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
				'name' => 'Dashboard for Honeycomb screenshot',
				'auto_start' => 0,
				'pages' => [
					[
						'name' => '3 dots',
						'widgets' => [
							[
								'type' => 'honeycomb',
								'x' => 0,
								'y' => 0,
								'width' => 6,
								'height' => 2,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => '3',
										'name' => 'hostids.0',
										'value' => $display_hostid
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
								'width' => 9,
								'height' => 2,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => '3',
										'name' => 'hostids.0',
										'value' => $display_hostid
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
								'width' => 13,
								'height' => 4,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => '3',
										'name' => 'hostids.0',
										'value' => $display_hostid
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
						'name' => 'Long horizontal line with interpolation',
						'widgets' => [
							[
								'type' => 'honeycomb',
								'x' => 0,
								'y' => 0,
								'width' => 29,
								'height' => 3,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => '3',
										'name' => 'hostids.0',
										'value' => $display_hostid
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
										'name' => 'interpolation',
										'value' => 1
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
								'width' => 9,
								'height' => 7,
								'view_mode' => 0,
								'fields' => [
									[
										'type' => '3',
										'name' => 'hostids.0',
										'value' => $display_hostid
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
			],
			[
				'name' => self::DASHBOARD_FOR_MACRO_FUNCTIONS,
				'auto_start' => 0,
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'honeycomb',
								'name' => self::WIDGET_FOR_MACRO_FUNCTIONS,
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 5,
								'fields' => [
									[
										'type' => 1,
										'name' => 'items.4',
										'value' => 'Display item 5'
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
			],
			[
				'macro' => self::USER_MACRO,
				'value' => self::USER_MACRO_VALUE
			],
			[
				'macro' => self::USER_SECRET_MACRO,
				'type' => 1,
				'value' => self::USER_MACRO_VALUE
			],
			[
				'macro' => self::MACRO_CHAR,
				'value' => self::MACRO_CHAR_VALUE
			],
			[
				'macro' => self::MACRO_HTML_ENCODE,
				'value' => self::MACRO_HTML_ENCODE_VALUE
			],
			[
				'macro' => self::MACRO_HTML_DECODE,
				'value' => self::MACRO_HTML_DECODE_VALUE
			],
			[
				'macro' => self::MACRO_URL_ENCODE,
				'value' => self::MACRO_URL_ENCODE_VALUE
			],
			[
				'macro' => self::MACRO_URL_DECODE,
				'value' => self::MACRO_URL_DECODE_VALUE
			]
		]);
	}

	public function testDashboardHoneycombWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for creating honeycomb widgets'])->waitUntilReady();

		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$form = $dashboard->edit()->addWidget()->asForm();
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Honeycomb')]);

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
			'Item patterns' => '',
			'Item tags' => 'And/Or',
			'Show hosts in maintenance' => false,
			'Advanced configuration' => false,
			'id:host_tags_0_tag' => '',
			'id:host_tags_0_value' => '',
			'id:item_tags_0_tag' => '',
			'id:item_tags_0_value' => '',
			'Show' => ['Primary label', 'Secondary label'],
			'id:primary_label_type_0' => 'Text',
			'Text' => '{HOST.NAME}',
			'id:primary_label_size_type_0' => 'Auto',
			'id:primary_label_bold' => false,
			'id:secondary_label_type_1' => 'Value',
			'id:secondary_label_decimal_places' => 2,
			'id:secondary_label_size_type_0' => 'Auto',
			'id:secondary_label_bold' => true,
			'id:secondary_label_units' => '',
			'id:secondary_label_units_pos' => 'After value',
			'id:interpolation' => false
		];

		$form->checkValue($default_values);

		// Check color picker default values.
		$color_pickers = [
			'id:lbl_primary_label_color',   // Primary label color.
			'id:lbl_secondary_label_color', // Secondary label color.
			'id:lbl_bg_color'				// Background color.
		];
		foreach ($color_pickers as $id) {
			$color_picker = $form->query($id)->one();
			$this->assertEquals('', $color_picker->getValue());
			$this->assertEquals('Use default', $color_picker->query('xpath:./../button')->one()->getAttribute('title'));
		}

		// Check Select popup dropdowns for Host groups and Hosts.
		$popup_menu_selector = 'xpath:.//button[contains(@class, "zi-chevron-down")]';
		$host_groups = ['Host groups', 'Widget'];
		$hosts = ['Hosts', 'Widget', 'Dashboard'];

		foreach (['Host groups', 'Hosts'] as $label) {
			$field = $form->getField($label);

			// Check Select dropdown menu button.
			$menu_button = $field->query($popup_menu_selector)->asPopupButton()->one();
			$this->assertEquals(($label === 'Host groups') ? $host_groups : $hosts,
					$menu_button->getMenu()->getItems()->asText()
			);

			// After selecting Dashboard from dropdown menu, check hint and field value.
			if ($label === 'Hosts') {
				$menu_button->select('Dashboard');
				$form->checkValue(['Hosts' => 'Dashboard']);
				$this->assertTrue($field->query('xpath:.//span[@data-hintbox-contents="Dashboard is used as data source."]')
						->one()->isVisible()
				);
			}

			// After selecting Widget from dropdown menu, check overlay dialog appearance and title.
			$menu_button->select('Widget');
			$dialogs = COverlayDialogElement::find()->all();
			$this->assertEquals('Widget', $dialogs->last()->waitUntilReady()->getTitle());
			$dialogs->last()->close(true);
		}

		// After clicking on Select button, check overlay dialog appearance and title.
		foreach (['Host groups', 'Hosts', 'Item patterns'] as $label) {
			$field = $form->getField($label);
			$field->query('button:Select')->waitUntilCLickable()->one()->click();
			$dialogs = COverlayDialogElement::find()->all();
			$label = ($label === 'Item patterns') ? 'Items' : $label;
			$this->assertEquals($label, $dialogs->last()->waitUntilReady()->getTitle());
			$dialogs->last()->close(true);
		}

		// Check Show checkboxes and their values.
		$show = $form->getField('Show');
		$this->assertEquals(['Primary label', 'Secondary label'], $show->getLabels()->asText());
		$this->assertTrue($show->isEnabled());

		// Check Add/Remove buttons for Hosts and Items tags tables.
		foreach (['tags_table_host_tags', 'tags_table_item_tags'] as $id) {
			$this->assertEquals(2, $form->query('id', $id)->one()->query('button', ['Add', 'Remove'])
					->all()->filter(CElementFilter::CLICKABLE)->count()
			);
		}

		// Fields layout after Advanced configuration expand.
		$form->fill(['Advanced configuration' => true]);
		$this->query('id:lbl_bg_color')->one()->waitUntilVisible();

		// Check Primary and Secondary label fields that disappear after checking them.
		$advanced_configuration = [
			'Primary label' => '{HOST.NAME}',
			'Secondary label' => '{{ITEM.LASTVALUE}.fmtnum(2)}'
		];

		foreach ($advanced_configuration as $label => $text) {
			$container = $form->getFieldContainer($label);
			$this->assertTrue($container->isVisible());

			// Type radio button. After changing them, some fields appears and other disappear.
			$label_id = ($label === 'Primary label') ? 'primary_label_' : 'secondary_label_';
			$type_label = $container->query('id', $label_id.'type')->asSegmentedRadio()->one();
			$this->assertEquals(['Text', 'Value'], $type_label->getLabels()->asText());

			foreach (['Text', 'Value'] as $type_values) {
				$type_label->select($type_values);

				if ($type_values === 'Text') {
					$this->assertEquals($text, $container->query('xpath:.//textarea')->one()->getText());

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
					$units_checkbox = $container->query('id', $label_id.'units_show')->asCheckbox()->one();
					$units_input = $container->query('xpath:.//div[contains(@class, "form-field")]//input[contains(@id,'.
							' "_label_units")]')->one();
					$position_dropdown = $container->query('id', $label_id.'units_pos')->asDropdown()->one();
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
			$size = $container->query('xpath:.//ul[contains(@id, "label_size_type")]')->asSegmentedRadio()->one();
			$this->assertEquals(['Auto', 'Custom'], $size->getLabels()->asText());

			// Primary and Secondary label has different values in size custom field.
			$size_input_value = ($label === 'Primary label') ? '20' : '30';

			// After clicking on Custom button, new input field appears.
			$size_input_selector = $container->query('id', $label_id.'size')->one();
			$this->assertFalse($size_input_selector->isVisible());
			$size->select('Custom');
			$this->assertTrue($size_input_selector->isVisible());
			$this->assertEquals($size_input_value, $size_input_selector->getAttribute('value'));

			// After clicking on Auto, input field disappear.
			$size->select('Auto');
			$this->assertFalse($size_input_selector->isVisible());

			// Check Bold checkbox. Primary label bold - unchecked. Secondary label bold - checked-in.
			$bold = $container->query('xpath:.//input[contains(@id, "_label_bold")]')->asCheckbox()->one();
			$this->assertTrue($bold->isChecked($label === 'Secondary label'));

			// Uncheck Primary/Secondary label.
			$show->set($label, false);
			$this->assertFalse($container->isVisible());
		}

		// Thresholds warning message.
		$form->getLabel('Thresholds')->query('xpath:./button[@data-hintbox-contents]')->one()->click();
		$warning = $this->query('xpath://div[@data-hintboxid]')->waitUntilVisible();
		$this->assertEquals('This setting applies only to numeric data.', $warning->one()->getText());
		$warning->one()->query('xpath:.//button[@class="btn-overlay-close"]')->one()->click();
		$warning->waitUntilNotPresent();

		// Color interpolation checkbox should be disabled.
		$this->assertFalse($form->query('id:interpolation')->asCheckbox()->one()->isEnabled());

		// Threshold table with adding and removing lines.
		$table = $form->query('id:thresholds-table')->asTable()->one();
		$this->assertEquals(['', 'Threshold', ''], $table->getHeadersText());

		// Check added threshold colors.
		$table->query('button:Add')->one()->click();
		$colorpicker = $table->query('xpath:.//input[@id="thresholds_0_color"]/..')->asColorPicker()->one();
		$this->assertEquals('E65660', $colorpicker->getValue());
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

		COverlayDialogElement::find()->one()->close();
		$dashboard->cancelEditing();
	}

	public static function getCreateData() {
		return [
			// #0.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'No item',
						'Item patterns' => ''
					],
					'error_message' => [
						'Invalid parameter "Item patterns": cannot be empty.'
					]
				]
			],
			// #1.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item patterns' => 'test',
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
						'Item patterns' => 'test',
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
						'Item patterns' => 'test'
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
						'Item patterns' => 'test'
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
						'Item patterns' => 'test'
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
						'Item patterns' => 'test'
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
						'Item patterns' => 'test'
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
						'Item patterns' => 'test',
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
						'Item patterns' => 'test',
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
						'Item patterns' => 'test',
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
						'Item patterns' => 'test',
						'id:secondary_label_type' => 'Text',
						'id:primary_label_size_type' => 'Custom', // Primary label Size - custom.
						'id:secondary_label_size_type' => 'Custom', // Secondary label Size - custom.
						'id:primary_label_size' => 'text', // Primary label Custom size input field.
						'id:secondary_label_size' => 'text',  // Secondary label Custom size input field.
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
						'Item patterns' => '',
						'id:secondary_label_type' => 'Text',
						'id:primary_label_size_type' => 'Custom', // Primary label Size - custom.
						'id:secondary_label_size_type' => 'Custom', // Secondary label Size - custom.
						'id:primary_label_size' => '🙂🙃', // Primary label Custom size input field.
						'id:secondary_label_size' => '🙂🙃',  // Secondary label Custom size input field.
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
						'Invalid parameter "Item patterns": cannot be empty.',
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
						'Item patterns' => 'Numeric for honeycomb 1',
						'Host groups' => 'Zabbix servers',
						'Hosts' => 'Host for honeycomb 1'
					]
				]
			],
			// #14.
			[
				[
					'fields' => [
						'Name' => '',
						'Item patterns' => 'Numeric for honeycomb 1',
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
						'Item patterns' => 'Numeric for honeycomb 1',
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
						'Item patterns' => 'Numeric for honeycomb 1',
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
						'Item patterns' => 'Numeric for honeycomb 1',
						'Show hosts in maintenance' => true
					]
				]
			],
			// #18.
			[
				[
					'fields' => [
						'Name' => 'Different items pattern',
						'Item patterns' => [
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
						'Item patterns' => 'Numeric for honeycomb 1'
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
						'Item patterns' => 'Numeric for honeycomb 1',
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
						'Item patterns' => 'Numeric for honeycomb 1',
						'Background colour' => 'B2DFDB'
					]
				]
			],
			// #22.
			[
				[
					'fields' => [
						'Name' => 'Host and items tags 🙂🙃',
						'Item patterns' => 'Numeric for honeycomb 1'
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
						'Item patterns' => 'Numeric for honeycomb 1',
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

		// Get hash if expected is TEST_BAD.
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			// Hash before update.
			self::$old_hash = CDBHelper::getHash(self::SQL);
		}
		else {
			self::$old_widget_count = CDashboardElement::find()->waitUntilReady()->one()->getWidgets()->count();
		}

		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$this->fillWidgetForm($data, 'create', $dashboard);
		$this->checkWidgetForm($data, 'create', $dashboard);
	}

	/**
	 * Honeycomb widget simple update without any field change.
	 */
	public function testDashboardHoneycombWidget_SimpleUpdate() {
		// Hash before simple update.
		self::$old_hash = CDBHelper::getHash(self::SQL);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for simple updating honeycomb widget'])->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dashboard->edit()->getWidget('UpdateHoneycomb')->edit()->submit();
		$dashboard->getWidget('UpdateHoneycomb');
		$dashboard->save();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Compare old hash and new one.
		$this->assertEquals(self::$old_hash, CDBHelper::getHash(self::SQL));
	}

	/**
	 * Creates the base widget used for the update scenario.
	 */
	public function prepareUpdateHoneycomb() {
		$providedData = $this->getProvidedData();
		$data = reset($providedData);

		// Create a dashboard with the widget for updating.
		$response = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for honeycomb update '.md5(serialize($data)),
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
		self::$disposable_dashboard_id = $response['dashboardids'][0];
	}

	/**
	 * Update Honeycomb widget.
	 *
	 * @onBefore prepareUpdateHoneycomb
	 *
	 * @dataProvider getCreateData
	 */
	public function testDashboardHoneycombWidget_Update($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$disposable_dashboard_id)->waitUntilReady();

		// Get hash if expected is TEST_BAD.
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			// Hash before update.
			self::$old_hash = CDBHelper::getHash(self::SQL);
		}
		else {
			self::$old_widget_count = CDashboardElement::find()->waitUntilReady()->one()->getWidgets()->count();
		}

		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$this->fillWidgetForm($data, 'update', $dashboard);
		$this->checkWidgetForm($data, 'update', $dashboard);
	}

	/**
	 * Delete Honeycomb widget.
	 */
	public function testDashboardHoneycombWidget_Delete() {
		$widget_name = 'DeleteHoneycomb';
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for deleting honeycomb widget'])->waitUntilReady();
		$dashboard = CDashboardElement::find()->one()->waitUntilReady()->edit();
		$widget = $dashboard->getWidget($widget_name);
		$this->assertTrue($widget->isEditable());
		$dashboard->deleteWidget($widget_name);
		$widget->waitUntilNotPresent();
		$dashboard->save();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Check that widget is not present on dashboard and in DB.
		$this->assertFalse($dashboard->getWidget($widget_name, false)->isValid());
		$this->assertEquals(0, CDBHelper::getCount('SELECT * FROM widget_field wf'.
				' LEFT JOIN widget w'.
				' ON w.widgetid=wf.widgetid'.
				' WHERE w.name='.zbx_dbstr($widget_name)
		));
	}

	public static function getDisplayData() {
		return [
			// #0.
			[
				[
					'fields' => [
						'Name' => 'Resolved macros',
						'Item patterns' => 'Display item 1',
						'id:primary_label_type' => 'Text',
						'id:secondary_label_type' => 'Text',
						'id:primary_label' => '{HOST.NAME} {ITEM.KEY} {ITEM.LASTVALUE}',
						'id:secondary_label' => '{HOST.NAME} {ITEM.KEY} {ITEM.LASTVALUE}'
					],
					'result' => 'Display honey_display_1 100'
				]
			],
			// #1.
			[
				[
					'fields' => [
						'Name' => 'Emoji and special symbols displayed',
						'Item patterns' => 'Display item 1',
						'id:primary_label_type' => 'Text',
						'id:secondary_label_type' => 'Text',
						'id:primary_label' => '🙂🙃 āēīõšŗ$%^&*()',
						'id:secondary_label' => '🙂🙃 āēīõšŗ$%^&*()'
					],
					'result' => '🙂🙃 āēīõšŗ$%^&*()'
				]
			],
			// #2.
			[
				[
					'fields' => [
						'Name' => 'Simple text displayed',
						'Item patterns' => 'Display item 1',
						'id:primary_label_type' => 'Text',
						'id:secondary_label_type' => 'Text',
						'id:primary_label' => 'Text for testing',
						'id:secondary_label' => 'Text for testing'
					],
					'result' => 'Text for testing'
				]
			],
			// #3.
			[
				[
					'fields' => [
						'Name' => 'User macros displayed {$TEXT}',
						'Item patterns' => 'Display item 1',
						'id:primary_label_type' => 'Text',
						'id:secondary_label_type' => 'Text',
						'id:primary_label' => '{$TEXT}',
						'id:secondary_label' => '{$TEXT}'
					],
					'result' => 'text_macro'
				]
			],
			// #4.
			[
				[
					'fields' => [
						'Name' => 'Secret macros displayed {$SECRET_TEXT}',
						'Item patterns' => 'Display item 1',
						'id:primary_label_type' => 'Text',
						'id:secondary_label_type' => 'Text',
						'id:primary_label' => '{$SECRET_TEXT}',
						'id:secondary_label' => '{$SECRET_TEXT}'
					],
					'result' => '******'
				]
			],
			// #5.
			[
				[
					'fields' => [
						'Name' => 'LLD macros displayed {#LLD}',
						'Item patterns' => 'Display item 1',
						'id:primary_label_type' => 'Text',
						'id:secondary_label_type' => 'Text',
						'id:primary_label' => '{#LLD}',
						'id:secondary_label' => '{#LLD}'
					],
					'result' => '{#LLD}'
				]
			],
			// #6.
			[
				[
					'fields' => [
						'Name' => 'Non existing global macros displayed {HELLO.WORLD}',
						'Item patterns' => 'Display item 1',
						'id:primary_label_type' => 'Text',
						'id:secondary_label_type' => 'Text',
						'id:primary_label' => '{HELLO.WORLD}',
						'id:secondary_label' => '{HELLO.WORLD}'
					],
					'result' => '{HELLO.WORLD}'
				]
			],
			// #7.
			[
				[
					'fields' => [
						'Name' => '123',
						'Item patterns' => 'Display item 1',
						'id:primary_label_type' => 'Value',
						'id:secondary_label_type' => 'Value',
						'id:primary_label_decimal_places' => '6',
						'id:secondary_label_decimal_places' => '6'
					],
					'result' => '100.000000'
				]
			],
			// #8.
			[
				[
					'fields' => [
						'Name' => 'Value decimal 0',
						'Item patterns' => 'Display item 1',
						'id:primary_label_type' => 'Value',
						'id:secondary_label_type' => 'Value',
						'id:primary_label_decimal_places' => '0',
						'id:secondary_label_decimal_places' => '0'
					],
					'result' => '100'
				]
			],
			// #9.
			[
				[
					'fields' => [
						'Name' => 'Before displayed units',
						'Item patterns' => 'Display item 1',
						'id:primary_label_type' => 'Value',
						'id:secondary_label_type' => 'Value',
						'id:primary_label_decimal_places' => '0',
						'id:secondary_label_decimal_places' => '0',
						'id:primary_label_units_pos' => 'Before value',
						'id:secondary_label_units_pos' => 'Before value',
						'id:primary_label_units' => 'before',
						'id:secondary_label_units' => 'before'
					],
					'result' => 'before 100'
				]
			],
			// #10.
			[
				[
					'fields' => [
						'Name' => 'After displayed units',
						'Item patterns' => 'Display item 1',
						'id:primary_label_type' => 'Value',
						'id:secondary_label_type' => 'Value',
						'id:primary_label_decimal_places' => '0',
						'id:secondary_label_decimal_places' => '0',
						'id:primary_label_units_pos' => 'After value',
						'id:secondary_label_units_pos' => 'After value',
						'id:primary_label_units' => 'after',
						'id:secondary_label_units' => 'after'
					],
					'result' => '100 after'
				]
			],
			// #11.
			[
				[
					'fields' => [
						'Name' => 'Special symbols and emoji check for units',
						'Item patterns' => 'Display item 1',
						'id:primary_label_type' => 'Value',
						'id:secondary_label_type' => 'Value',
						'id:primary_label_decimal_places' => '0',
						'id:secondary_label_decimal_places' => '0',
						'id:primary_label_units_pos' => 'After value',
						'id:secondary_label_units_pos' => 'After value',
						'id:primary_label_units' => '🙂🙃 āēīõšŗ$%^&*()',
						'id:secondary_label_units' => '🙂🙃 āēīõšŗ$%^&*()'
					],
					'result' => '100 🙂🙃 āēīõšŗ$%^&*()'
				]
			],
			// #12.
			[
				[
					'fields' => [
						'Name' => 'User and global macros displayed in units',
						'Item patterns' => 'Display item 1',
						'id:primary_label_type' => 'Value',
						'id:secondary_label_type' => 'Value',
						'id:primary_label_decimal_places' => '0',
						'id:secondary_label_decimal_places' => '0',
						'id:primary_label_units_pos' => 'After value',
						'id:secondary_label_units_pos' => 'After value',
						'id:primary_label_units' => '{$TEXT} {HOST.NAME}',
						'id:secondary_label_units' => '{$TEXT} {HOST.NAME}'
					],
					'result' => '100 {$TEXT} {HOST.NAME}'
				]
			],
			// #13.
			[
				[
					'fields' => [
						'Name' => 'Only primary label displayed',
						'Item patterns' => 'Display item 1',
						'id:show_2' => false,
						'id:primary_label_type' => 'Text',
						'id:primary_label' => 'Only primary'
					],
					'check_label' => 'svg-honeycomb-label-primary',
					'turned_off_label' => 'svg-honeycomb-label-secondary',
					'result' => 'Only primary'
				]
			],
			// #14.
			[
				[
					'fields' => [
						'Name' => 'Only secondary label displayed',
						'Item patterns' => 'Display item 1',
						'id:show_1' => false,
						'id:secondary_label_type' => 'Text',
						'id:secondary_label' => 'Only secondary'
					],
					'check_label' => 'svg-honeycomb-label-secondary',
					'turned_off_label' => 'svg-honeycomb-label-primary',
					'result' => 'Only secondary'
				]
			],
			// #15.
			[
				[
					'fields' => [
						'Name' => 'Colors for value and background',
						'Item patterns' => 'Display item 1',
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
						'svg-honeycomb-label-primary' => 'rgba(102, 187, 106, 1)',
						'svg-honeycomb-label-secondary' => 'rgba(128, 222, 234, 1)'
					],
					'result' => 'COLOR'
				]
			]
		];
	}

	/**
	 * Check different data display on Honeycomb widget.
	 *
	 * @onBefore prepareUpdateHoneycomb
	 *
	 * @dataProvider getDisplayData
	 */
	public function testDashboardHoneycombWidget_Display($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$disposable_dashboard_id)->waitUntilReady();
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$this->fillWidgetForm($data, 'update', $dashboard);
		$dashboard->save();

		// Check message that dashboard saved.
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$this->page->waitUntilReady();
		$widget = $dashboard->getWidget($data['fields']['Name']);

		// Check that correct value displayed on honeycomb.
		$content = $widget->getContent();
		if (array_key_exists('check_label', $data)) {
			$displayed = $content->query('class', $data['check_label'])->one()->getText();
			$this->assertEquals($displayed, $data['result']);
			$this->assertFalse($content->query('class', $data['turned_off_label'])->exists());
		}
		else {
			foreach (['svg-honeycomb-label-primary', 'svg-honeycomb-label-secondary'] as $selector) {
				$displayed = $content->query('class', $selector)->one()->getText();
				$this->assertEquals($displayed, $data['result']);
			}
		}

		// Check that correct colors displayed.
		if (array_key_exists('colors', $data)) {
			foreach ($data['colors'] as $color_selector => $color) {
				if ($color === '#D1C4E9') {
					$this->assertStringContainsString($color, $this->query($color_selector)->one()->getAttribute('style'));
				}
				else {
					$this->assertEquals($color, $this->query('class', $color_selector)->one()->getCSSValue('color'));
				}
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
		self::$old_hash = CDBHelper::getHash(self::SQL);
		$new_name = 'Widget to be cancelled';

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for canceling honeycomb widget']
		);
		$dashboard = CDashboardElement::find()->one()->edit();
		self::$old_widget_count = $dashboard->getWidgets()->count();

		// Start updating or creating a widget.
		if (CTestArrayHelper::get($data, 'update', false)) {
			$form = $dashboard->getWidget('CancelHoneycomb')->edit();
		}
		else {
			$form = $dashboard->addWidget()->asForm();
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Honeycomb')]);
		}

		$form->fill([
			'Name' => $new_name,
			'Advanced configuration' => true,
			'Item patterns' => 'Test_cancel',
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
			$dialog->close(true);
			$dialog->ensureNotPresent();

			if (CTestArrayHelper::get($data, 'update', false)) {
				foreach (['CancelHoneycomb' => true, $new_name => false] as $name => $valid) {
					$this->assertTrue($dashboard->getWidget($name, $valid)->isValid($valid));
				}
			}

			$this->assertEquals(self::$old_widget_count, $dashboard->getWidgets()->count());
		}
		// Save or cancel dashboard update.
		if (CTestArrayHelper::get($data, 'save_dashboard', false)) {
			$dashboard->save();
		}
		else {
			$dashboard->cancelEditing();
		}
		// Confirm that no changes were made to the widget.
		$this->assertEquals(self::$old_hash, CDBHelper::getHash(self::SQL));
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
	 * Creates the base widget used for the update scenario.
	 */
	public function prepareFilteringHoneycomb() {
		$providedData = $this->getProvidedData();
		$data = reset($providedData);

		// Create a dashboard with the widget for updating.
		$response = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for filtering '.md5(serialize($data)),
				'auto_start' => 0,
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'honeycomb',
								'name' => 'UpdateHoneycomb',
								'x' => 0,
								'y' => 0,
								'width' => 30,
								'height' => 7,
								'fields' => [
									[
										'type' => 0,
										'name' => 'show.0',
										'value' => 1
									],
									[
										'type' => 1,
										'name' => 'primary_label',
										'value' => '{ITEM.NAME}'
									],
									[
										'type' => 1,
										'name' => 'items.0',
										'value' => 'test'
									],
									[
										'type' => 1,
										'name' => 'reference',
										'value' => 'BUBUR'
									]
								]
							]
						]
					]
				]
			]
		]);
		self::$disposable_dashboard_id = $response['dashboardids'][0];
	}

	public static function getFilteringData() {
		return [
			// #0 Filter by 3 items.
			[
				[
					'fields' => [
						'Item patterns' => ['Item tag 1', 'Item tag 2', 'Host tag item']
					],
					'filtered_items' => ['Item tag 1', 'Item tag 2', 'Host tag item']
				]
			],
			// #1 Filter by Host group.
			[
				[
					'fields' => [
						'Host groups' => 'Items with tags',
						'Item patterns' => ['Item tag 1', 'Item tag 2', 'Host tag item']
					],
					'filtered_items' => ['Item tag 1', 'Item tag 2']
				]
			],
			// #2 Filter by Host.
			[
				[
					'fields' => [
						'Hosts' => 'Host with tags',
						'Item patterns' => ['Item tag 1', 'Item tag 2', 'Host tag item']
					],
					'filtered_items' => ['Host tag item']
				]
			],
			// #3 Show hosts in maintenance.
			[
				[
					'fields' => [
						'Show hosts in maintenance' => true,
						'Item patterns' => ['Item tag 1', 'Host tag item', 'Maintenance item']
					],
					'filtered_items' => ['Maintenance item', 'Item tag 1', 'Host tag item']
				]
			],
			// #4 Don't show hosts in maintenance.
			[
				[
					'fields' => [
						'Show hosts in maintenance' => false,
						'Item patterns' => ['Item tag 1', 'Host tag item', 'Maintenance item']
					],
					'filtered_items' => ['Item tag 1', 'Host tag item']
				]
			],
			// #5 Filter by Host exists tag.
			[
				[
					'fields' => [
						'Item patterns' => ['Item tag 1', 'Item tag 2', 'Host tag item']
					],
					'tags' => [
						'host_tags' => [
							['name' => 'host_tag_1', 'operator' => 'Exists']
						]
					],
					'filtered_items' => ['Host tag item']
				]
			],
			// #6 Filter by Host doesn't exist tag.
			[
				[
					'fields' => [
						'Item patterns' => ['Item tag 1', 'Item tag 2', 'Host tag item']
					],
					'tags' => [
						'host_tags' => [
							['name' => 'host_tag_1', 'operator' => 'Does not exist']
						]
					],
					'filtered_items' => ['Item tag 1', 'Item tag 2']
				]
			],
			// #7 Filter by Items exists tag.
			[
				[
					'fields' => [
						'Item patterns' => ['Item tag 1', 'Item tag 2', 'Host tag item']
					],
					'tags' => [
						'item_tags' => [
							['name' => 'item_tag_1', 'operator' => 'Exists']
						]
					],
					'filtered_items' => ['Item tag 1']
				]
			],
			// #8 Filter by Items doesn't exist tag.
			[
				[
					'fields' => [
						'Item patterns' => ['Item tag 1', 'Item tag 2', 'Host tag item']
					],
					'tags' => [
						'item_tags' => [
							['name' => 'item_tag_1', 'operator' => 'Does not exist']
						]
					],
					'filtered_items' => ['Item tag 2', 'Host tag item']
				]
			],
			// #9 Filter by Items and Host exists tag.
			[
				[
					'fields' => [
						'Item patterns' => ['Item tag 1', 'Item tag 2', 'Host tag item']
					],
					'tags' => [
						'item_tags' => [
							['name' => 'item_tag_1', 'operator' => 'Exists']
						],
						'host_tags' => [
							['name' => 'host_tag_1', 'operator' => 'Exists']
						]
					],
					'filtered_items' => ['No data']
				]
			],
			// #10 Filter by Items and Host doesn't exist tag.
			[
				[
					'fields' => [
						'Show hosts in maintenance' => True,
						'Item patterns' => ['Item tag 1', 'Item tag 2', 'Host tag item', 'Maintenance item']
					],
					'tags' => [
						'item_tags' => [
							['name' => 'item_tag_1', 'operator' => 'Does not exist']
						],
						'host_tags' => [
							['name' => 'host_tag_1', 'operator' => 'Does not exist']
						]
					],
					'filtered_items' => ['Maintenance item', 'Item tag 2']
				]
			],
			// #11 Item tag Or with operators Contain.
			[
				[
					'fields' => [
						'Item patterns' => ['Item tag 1', 'Item tag 2', 'Item tag 3', 'Item tag 4', 'Item tag 5'],
						'Item tags' => 'Or'
					],
					'tags' => [
						'item_tags' => [
							['name' => 'item_tag_1', 'operator' => 'Contains', 'value' => 'item_val_1'],
							['name' => 'item_tag_3', 'operator' => 'Contains', 'value' => 'item_val_3']
						]
					],
					'filtered_items' => ['Item tag 1', 'Item tag 3', 'Item tag 4']
				]
			],
			// #12 Item tag And/Or with operators Contain.
			[
				[
					'fields' => [
						'Item patterns' => ['Item tag 1', 'Item tag 2', 'Item tag 3', 'Item tag 4', 'Item tag 5'],
						'Item tags' => 'And/Or'
					],
					'tags' => [
						'item_tags' => [
							['name' => 'item_tag_1', 'operator' => 'Contains', 'value' => 'item_val_1'],
							['name' => 'item_tag_3', 'operator' => 'Contains', 'value' => 'item_val_3']
						]
					],
					'filtered_items' => ['No data']
				]
			],
			// #13 Item tag Or with operators Exists.
			[
				[
					'fields' => [
						'Item patterns' => ['Item tag 1', 'Item tag 2', 'Item tag 3', 'Item tag 4', 'Item tag 5'],
						'Item tags' => 'Or'
					],
					'tags' => [
						'item_tags' => [
							['name' => 'item_tag_1', 'operator' => 'Exists'],
							['name' => 'item_tag_3', 'operator' => 'Exists']
						]
					],
					'filtered_items' => ['Item tag 1', 'Item tag 3', 'Item tag 4', 'Item tag 5']
				]
			],
			// #14 Item tag And/Or with operators Exists.
			[
				[
					'fields' => [
						'Item patterns' => ['Item tag 1', 'Item tag 2', 'Item tag 3', 'Item tag 4', 'Item tag 5'],
						'Item tags' => 'And/Or'
					],
					'tags' => [
						'item_tags' => [
							['name' => 'item_tag_1', 'operator' => 'Exists'],
							['name' => 'item_tag_3', 'operator' => 'Exists']
						]
					],
					'filtered_items' => ['No data']
				]
			],
			// #15 Item tag Or with operators Does not exist and Contains.
			[
				[
					'fields' => [
						'Item patterns' => ['Item tag 1', 'Item tag 2', 'Item tag 3', 'Item tag 4', 'Item tag 5'],
						'Item tags' => 'Or'
					],
					'tags' => [
						'item_tags' => [
							['name' => 'item_tag_2', 'operator' => 'Contains', 'value' => 'item_val_2'],
							['name' => 'item_tag_3', 'operator' => 'Does not exist']
						]
					],
					'filtered_items' => ['Item tag 1', 'Item tag 2', 'Item tag 4', 'Item tag 5']
				]
			],
			// #16 Item tag And/Or with operators Does not exist and Contains.
			[
				[
					'fields' => [
						'Item patterns' => ['Item tag 1', 'Item tag 2', 'Item tag 3', 'Item tag 4', 'Item tag 5'],
						'Item tags' => 'And/Or'
					],
					'tags' => [
						'item_tags' => [
							['name' => 'item_tag_2', 'operator' => 'Contains', 'value' => 'item_val_2'],
							['name' => 'item_tag_3', 'operator' => 'Does not exist']
						]
					],
					'filtered_items' => ['Item tag 2']
				]
			]
		];
	}

	/**
	 * Filter honeycomb and check that correct item comb visible on widget.
	 *
	 * @dataProvider getFilteringData
	 *
	 * @onBefore prepareFilteringHoneycomb
	 */
	public function testDashboardHoneycombWidget_CheckFiltering($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$disposable_dashboard_id)->waitUntilReady();
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$this->fillWidgetForm($data, 'update', $dashboard);
		$dashboard->save();

		// Check message that dashboard saved.
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Check that correct combs displayed on honeycomb.
		$filtered = $dashboard->getWidget('UpdateHoneycomb')->getContent()->query('class', 'svg-honeycomb-content')
				->all()->asText();
		$this->assertEquals($data['filtered_items'], $filtered);
	}

	public static function getMacroFunctions() {
		return [
			'Incorrectly added parameter for non-argument macro functions' => [
				[
					'fields' => [
						'Advanced configuration' => true,
						'id:primary_label_type' => 'Text',
						'id:primary_label' => '{{ITEM.NAME}.btoa(\)}, {'.self::USER_MACRO.'.htmldecode(test)}, '.
							'{'.self::USER_MACRO.'.htmlencode(test)}, {{ITEM.NAME}.lowercase([test])}, '.
							'{{ITEM.NAME}.uppercase([test])}, {{ITEM.NAME}.urldecode([test])}, '.
							'{'.self::USER_SECRET_MACRO.'.urlencode(\/)}',
						'id:secondary_label_type' => 'Text',
						'id:secondary_label' => '{{ITEM.NAME}.btoa(\)}, {'.self::USER_MACRO.'.htmldecode(test)}, '.
							'{'.self::USER_MACRO.'.htmlencode(test)}, {{ITEM.NAME}.lowercase([test])}, '.
							'{{ITEM.NAME}.uppercase([test])}, {{ITEM.NAME}.urldecode([test])}, '.
							'{'.self::USER_SECRET_MACRO.'.urlencode(\/)}'
					],
					'result' => [
						'primary' => '*UNKNOWN*, *UNKNOWN*, *UNKNOWN*, *UNKNOWN*, *UNKNOWN*, *UNKNOWN*, *UNKNOWN*',
						'secondary' => '*UNKNOWN*, *UNKNOWN*, *UNKNOWN*, *UNKNOWN*, *UNKNOWN*, *UNKNOWN*, *UNKNOWN*'
					]
				]
			],
			'Secret macro value is not exposed when using macro functions' => [
				[
					'fields' => [
						'Advanced configuration' => true,
						'id:primary_label_type' => 'Text',
						'id:primary_label' => '{'.self::USER_SECRET_MACRO.'.btoa()}, {'.self::USER_SECRET_MACRO.'.htmldecode()}, '.
							'{'.self::USER_SECRET_MACRO.'.htmlencode()}, {'.self::USER_SECRET_MACRO.'.lowercase()}, '.
							'{'.self::USER_SECRET_MACRO.'.uppercase()}, {'.self::USER_SECRET_MACRO.'.regrepl(a, b)}, '.
							'{'.self::USER_SECRET_MACRO.'.tr(a-z, b)}, {'.self::USER_SECRET_MACRO.'.urldecode()}, '.
							'{'.self::USER_SECRET_MACRO.'.urlencode()}',
						'id:secondary_label_type' => 'Text',
						'id:secondary_label' => '{'.self::USER_SECRET_MACRO.'.btoa()}, {'.self::USER_SECRET_MACRO.'.htmldecode()}, '.
							'{'.self::USER_SECRET_MACRO.'.htmlencode()}, {'.self::USER_SECRET_MACRO.'.lowercase()}, '.
							'{'.self::USER_SECRET_MACRO.'.uppercase()}, {'.self::USER_SECRET_MACRO.'.regrepl(a, b)}, '.
							'{'.self::USER_SECRET_MACRO.'.tr(a-z, b)}, {'.self::USER_SECRET_MACRO.'.urldecode()}, '.
							'{'.self::USER_SECRET_MACRO.'.urlencode()}'
					],
					'result' => [
						'primary' => 'KioqKioq, ******, ******, ******, ******, ******, ******, ******, %2A%2A%2A%2A%2A%2A',
						'secondary' => 'KioqKioq, ******, ******, ******, ******, ******, ******, ******, %2A%2A%2A%2A%2A%2A'
					]
				]
			],
			'Built-in macros with non-argument macro functions' => [
				[
					'fields' => [
						'Advanced configuration' => true,
						'id:primary_label_type' => 'Text',
						'id:primary_label' => '{{ITEM.NAME}.btoa()}, {{ITEM.NAME}.htmldecode()}, {{ITEM.NAME}.htmlencode()}, '.
							'{{ITEM.NAME}.lowercase()}, {{ITEM.NAME}.uppercase()}, {{ITEM.NAME}.urlencode()}, '.
							'{{ITEM.NAME}.urldecode()}',
						'id:secondary_label_type' => 'Text',
						'id:secondary_label' => '{{ITEM.NAME}.btoa()}, {{ITEM.NAME}.htmldecode()}, {{ITEM.NAME}.htmlencode()}, '.
							'{{ITEM.NAME}.lowercase()}, {{ITEM.NAME}.uppercase()}, {{ITEM.NAME}.urlencode()}, '.
							'{{ITEM.NAME}.urldecode()}'
					],
					'result' => [
						'primary' => 'RGlzcGxheSBpdGVtIDU=, Display item 5, Display item 5, display item 5, '.
							'DISPLAY ITEM 5, Display%20item%205, Display item 5',
						'secondary' => 'RGlzcGxheSBpdGVtIDU=, Display item 5, Display item 5, display item 5, '.
							'DISPLAY ITEM 5, Display%20item%205, Display item 5'
					]
				]
			],
			'User macros with btoa(), htmlencode(), htmldecode() macro functions' => [
				[
					'fields' => [
						'Advanced configuration' => true,
						'id:primary_label_type' => 'Text',
						'id:primary_label' => '{'.self::USER_MACRO.'.btoa()}, {'.self::MACRO_HTML_ENCODE.'.htmlencode()}, '.
							'{'.self::MACRO_HTML_DECODE.'.htmldecode()}',
						'id:secondary_label_type' => 'Text',
						'id:secondary_label' => '{'.self::USER_MACRO.'.btoa()}, {'.self::MACRO_HTML_ENCODE.'.htmlencode()}, '.
							'{'.self::MACRO_HTML_DECODE.'.htmldecode()}'
					],
					'result' => [
						'primary' => base64_encode(self::USER_MACRO_VALUE).', '.self::MACRO_HTML_DECODE_VALUE.', '.
							self::MACRO_HTML_ENCODE_VALUE,
						'secondary' => base64_encode(self::USER_MACRO_VALUE).', '.self::MACRO_HTML_DECODE_VALUE.', '.
							self::MACRO_HTML_ENCODE_VALUE
					]
				]
			],
			'User macros with urlencode(), urldecode(), uppercase(), lowercase() macro functions' => [
				[
					'fields' => [
						'Advanced configuration' => true,
						'id:primary_label_type' => 'Text',
						'id:primary_label' => '{'.self::MACRO_URL_ENCODE.'.urlencode()}, '.
							'{'.self::MACRO_URL_DECODE.'.urldecode()}, {'.self::USER_MACRO.'.uppercase()}, '.
							'{'.self::USER_MACRO.'.lowercase()}',
						'id:secondary_label_type' => 'Text',
						'id:secondary_label' => '{'.self::MACRO_URL_ENCODE.'.urlencode()}, '.
							'{'.self::MACRO_URL_DECODE.'.urldecode()}, {'.self::USER_MACRO.'.uppercase()}, '.
							'{'.self::USER_MACRO.'.lowercase()}'
					],
					'result' => [
						'primary' => self::MACRO_URL_DECODE_VALUE.', '.self::MACRO_URL_ENCODE_VALUE.
							', MACRO FUNCTION TEST 12345, macro function test 12345',
						'secondary' => self::MACRO_URL_DECODE_VALUE.', '.self::MACRO_URL_ENCODE_VALUE.
							', MACRO FUNCTION TEST 12345, macro function test 12345'
					]
				]
			],
			'Incorrectly used parameters in regrepl(), tr(), regsub(), iregsub() macro functions' => [
				[
					'fields' => [
						'Advanced configuration' => true,
						'id:primary_label_type' => 'Text',
						'id:primary_label' => '{'.self::USER_MACRO.'.regrepl()}, {'.self::MACRO_CHAR.'.regrepl([a])}, '.
							'{'.self::USER_MACRO.'.tr()}, {'.self::USER_MACRO.'.tr(z-a,Z-A)}, {'.self::USER_MACRO.'.tr(1,2,3)}'.
							', {'.self::USER_MACRO.'.regsub()}, {'.self::USER_MACRO.'.iregsub()}',
						'id:secondary_label_type' => 'Text',
						'id:secondary_label' => '{'.self::USER_MACRO.'.regrepl()}, {'.self::MACRO_CHAR.'.regrepl([a])}, '.
							'{'.self::USER_MACRO.'.tr()}, {'.self::USER_MACRO.'.tr(z-a,Z-A)}, {'.self::USER_MACRO.'.tr(1,2,3)}'.
							', {'.self::USER_MACRO.'.regsub()}, {'.self::USER_MACRO.'.iregsub()}'
					],
					'result' => [
						'primary' => '*UNKNOWN*, *UNKNOWN*, *UNKNOWN*, *UNKNOWN*, *UNKNOWN*, *UNKNOWN*, *UNKNOWN*',
						'secondary' => '*UNKNOWN*, *UNKNOWN*, *UNKNOWN*, *UNKNOWN*, *UNKNOWN*, *UNKNOWN*, *UNKNOWN*'
					]
				]
			],
			'Regrepl function - multibyte characters and case sensitive check' => [
				[
					'fields' => [
						'Advanced configuration' => true,
						'id:primary_label_type' => 'Text',
						'id:primary_label' => '{'.self::USER_MACRO.'.regrepl([[:digit:]], /, [A-Z], \)}',
						'id:secondary_label_type' => 'Text',
						'id:secondary_label' => '{'.self::MACRO_CHAR.'.regrepl(🌴, 🌝, [а-я], Q, \d, 🌞)}'
					],
					'result' => [
						'primary' => '\acro function \est /////',
						'secondary' => '🌞🌞🌞 ЙQQQQЖŽzŠsšĒĀīī🌝 ₰₰₰'
					]
				]
			],
			'Regrepl function with big amount of processed data' => [
				[
					'fields' => [
						'Advanced configuration' => true,
						'id:primary_label_type' => 'Text',
						'id:primary_label' => '{'.self::USER_MACRO.''.
							'.regrepl(1{0}, test, 1{0}, test, 1{0},test, 1{0}, test, 1{0}, test, 1{0}, test)}',
						'id:secondary_label_type' => 'Text',
						'id:secondary_label' => '{'.self::USER_MACRO.''.
							'.regrepl(1{0}, test, 1{0}, test, 1{0},test, 1{0}, test, 1{0}, test, 1{0}, test)}'
					],
					'result' => [
						'primary' => '*UNKNOWN*',
						'secondary' => '*UNKNOWN*'
					]
				]
			],
			'Macro functions tr(), uppercase(), lowercase() with non-ascii characters' => [
				[
					'fields' => [
						'Advanced configuration' => true,
						'id:primary_label_type' => 'Text',
						'id:primary_label' => '{'.self::MACRO_CHAR.'.tr(0-9, Ī)}, {'.self::MACRO_CHAR.'.lowercase()}, '.
							'{'.self::MACRO_CHAR.'.uppercase()}',
						'id:secondary_label_type' => 'Text',
						'id:secondary_label' => '{'.self::MACRO_CHAR.'.tr(0-9, Ī)}, {'.self::MACRO_CHAR.'.lowercase()}, '.
							'{'.self::MACRO_CHAR.'.uppercase()}'
					],
					'result' => [
						'primary' => '??? ЙщфхжЖŽzŠsšĒĀīī🌴 ₰₰₰, 000 ЙщфхжЖŽzŠsšĒĀīī🌴 ₰₰₰, 000 ЙщфхжЖŽZŠSšĒĀīī🌴 ₰₰₰',
						'secondary' => '??? ЙщфхжЖŽzŠsšĒĀīī🌴 ₰₰₰, 000 ЙщфхжЖŽzŠsšĒĀīī🌴 ₰₰₰, 000 ЙщфхжЖŽZŠSšĒĀīī🌴 ₰₰₰'
					]
				]
			],
			'Macro function tr() - use of escaping and range' => [
				[
					'fields' => [
						'Advanced configuration' => true,
						'id:primary_label_type' => 'Text',
						'id:primary_label' => '{'.self::MACRO_URL_ENCODE.'.tr("\/","\"")}, {'.self::MACRO_CHAR.'.tr(0-9abcA-L,*)}',
						'id:secondary_label_type' => 'Text',
						'id:secondary_label' => '{'.self::MACRO_URL_ENCODE.'.tr("\/","\"")}, {'.self::MACRO_CHAR.'.tr(0-9abcA-L,*)}'
					],
					'result' => [
						'primary' => 'h:""test.com"macro?functions=urlencode&urld=a🎸, *** ЙщфхжЖŽzŠsšĒĀīī🌴 ₰₰₰',
						'secondary' => 'h:""test.com"macro?functions=urlencode&urld=a🎸, *** ЙщфхжЖŽzŠsšĒĀīī🌴 ₰₰₰'
					]
				]
			],
			'Macro functions regsub(), iregsub() - successful scenarios' => [
				[
					'fields' => [
						'Advanced configuration' => true,
						'id:primary_label_type' => 'Text',
						'id:primary_label' => '{'.self::USER_MACRO.'.regsub(^[0-9]+, Problem)}, '.
							'{'.self::USER_MACRO.'.iregsub(^[0-9]+, Problem)}, {{ITEM.NAME}.regsub(^[0-9]+, Problem)}, '.
							'{{ITEM.NAME}.iregsub(^[0-9]+, Problem)}, {'.self::USER_SECRET_MACRO.'.regsub(^[0-9]+, Problem)}, '.
							'{'.self::USER_SECRET_MACRO.'.iregsub(^[0-9]+, Problem)}',
						'id:secondary_label_type' => 'Text',
						'id:secondary_label' => '{'.self::USER_MACRO.'.regsub(^[0-9]+, Problem)}, '.
							'{'.self::USER_MACRO.'.iregsub(^[0-9]+, Problem)}, {{ITEM.NAME}.regsub(^[0-9]+, Problem)}, '.
							'{{ITEM.NAME}.iregsub(^[0-9]+, Problem)}, {'.self::USER_SECRET_MACRO.'.regsub(^[0-9]+, Problem)}, '.
							'{'.self::USER_SECRET_MACRO.'.iregsub(^[0-9]+, Problem)}'
					],
					'result' => [
						'primary' => 'Problem, Problem, Problem, Problem, Problem, Problem',
						'secondary' => 'Problem, Problem, Problem, Problem, Problem, Problem'
					]
				]
			]
			// TODO: Uncomment and check the test case, after ZBX-25420 fix.
//			'Macro functions regsub(), iregsub() -  empty value in case of no match' => [
//				[
//					'fields' => [
//						'Advanced configuration' => true,
//						'id:primary_label_type' => 'Text',
//						'id:primary_label' => '{'.self::USER_MACRO.'.regsub(0, Problem)}, '.
//							'{'.self::USER_MACRO.'.iregsub(0, Problem)}, {{ITEM.NAME}.regsub(0, Problem)}, '.
//							'{{ITEM.NAME}.iregsub(0, Problem)}, {'.self::USER_SECRET_MACRO.'.regsub(0, Problem)}, '.
//							'{'.self::USER_SECRET_MACRO.'.iregsub(0, Problem)}, ',
//						'id:secondary_label_type' => 'Text',
//						'id:secondary_label' => '{'.self::USER_MACRO.'.regsub(0, Problem)}, '.
//							'{'.self::USER_MACRO.'.iregsub(0, Problem)}, {{ITEM.NAME}.regsub(0, Problem)}, '.
//							'{{ITEM.NAME}.iregsub(0, Problem)}, {'.self::USER_SECRET_MACRO.'.regsub(0, Problem)}, '.
//							'{'.self::USER_SECRET_MACRO.'.iregsub(0, Problem)}, '
//					],
//					'result' => [
//						'primary' => ', , , , ,',
//						'secondary' => ', , , , ,'
//					]
//				]
//			]
		];
	}

	/**
	 * @dataProvider getMacroFunctions
	 */
	public function testDashboardHoneycombWidget_CheckMacroFunctions($data) {
		$this->setWidgetConfiguration(self::$dashboardid[self::DASHBOARD_FOR_MACRO_FUNCTIONS],
				self::WIDGET_FOR_MACRO_FUNCTIONS, $data['fields']
		);
		CDashboardElement::find()->one()->save()->waitUntilReady();

		// Check the resolution of macrofunction.
		$this->assertEquals($data['result']['primary'],
				$this->query('xpath://div[@class="svg-honeycomb-label svg-honeycomb-label-primary"]')->one()->getText()
		);
		$this->assertEquals($data['result']['secondary'],
				$this->query('xpath://div[@class="svg-honeycomb-label svg-honeycomb-label-secondary"]')->one()->getText()
		);
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
	 * Create or update Honeycomb widget.
	 *
	 * @param array             $data         data provider
	 * @param string            $action       create/update honeycomb widget
	 * @param CDashboardElement $dashboard    given dashboard
	 */
	protected function fillWidgetForm($data, $action, $dashboard) {
		$form = ($action === 'create')
			? $dashboard->edit()->addWidget()->asForm()
			: $dashboard->getWidget('UpdateHoneycomb')->edit();

		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Honeycomb')]);
		$form->fill(['Advanced configuration' => true]);
		$this->query('id:lbl_bg_color')->one()->waitUntilVisible();

		// Fill Thresholds values.
		if (array_key_exists('thresholds', $data)) {
			$this->getTreshholdTable()->fill($data['thresholds']);
			unset($data['thresholds']);
		}

		if (array_key_exists('tags', $data)) {
			$this->addOrCheckTags($data['tags'], false);
		}

		$form->fill($data['fields']);
		$form->submit();
	}

	/**
	 * Check created or updated Honeycomb widget.
	 *
	 * @param array             $data         data provider
	 * @param string            $action       create/update honeycomb widget
	 * @param CDashboardElement $dashboard    given dashboard
	 */
	protected function checkWidgetForm($data, $action, $dashboard) {
		if (CTestArrayHelper::get($data, 'expected', TEST_GOOD) === TEST_BAD) {
			$this->assertMessage(TEST_BAD, null, $data['error_message']);
			COverlayDialogElement::find()->one()->close();
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Compare old hash and new one.
			$this->assertEquals(self::$old_hash, CDBHelper::getHash(self::SQL));
		}
		else {
			// Make sure that the widget is present before saving the dashboard.
			$header = (array_key_exists('Name', $data['fields']))
				? (($data['fields']['Name'] === '') ? 'Honeycomb' : $data['fields']['Name'])
				: 'Honeycomb';

			$dashboard->getWidget($header);
			$dashboard->save();

			// Check message that dashboard saved.
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Check widget amount that it is added.
			$this->assertEquals(self::$old_widget_count + (($action === 'create') ? 1 : 0), $dashboard->getWidgets()->count());

			$form = $dashboard->getWidget($header)->edit()->asForm();
			$form->fill(['Advanced configuration' => true]);
			$this->query('id:lbl_bg_color')->one()->waitUntilVisible();

			if (array_key_exists('tags', $data)) {
				$this->addOrCheckTags($data['tags']);
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

	/**
	 * Add or Check tags in Honeycomb widget.
	 *
	 * @param array   $tags     given tags
	 * @param boolean $check    check tags' values after creation or not
	 */
	protected function addOrCheckTags($tags, $check = true) {
		foreach ($tags as $tag => $values) {
			$this->setTagSelector(($tag === 'item_tags') ? 'id:tags_table_item_tags' : 'id:tags_table_host_tags');

			if ($check) {
				$this->assertTags($values);
			}
			else {
				$this->setTags($values);
			}
		}
	}
}
