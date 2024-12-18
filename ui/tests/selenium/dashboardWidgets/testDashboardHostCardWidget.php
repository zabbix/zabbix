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
 * @backup dashboard, globalmacro
 *
 * @dataSource AllItemValueTypes
 *
 * @onBefore prepareHostCardWidgetData
 */
class testDashboardHostCardWidget extends testWidgets {

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
	 * Ids of created Dashboards for Host Card widget check.
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

	public static function prepareHostCardWidgetData() {
		CDataHelper::call('hostgroup.create', [
			[
				'name' => 'Maintenance host group for HostCard widget'
			],
			[
				'name' => 'Host tags group for HostCard widget'
			],
			[
				'name' => 'Disabled hosts for HostCard widget'
			]
		]);
		$groupids = CDataHelper::getIds('name');

		$response = CDataHelper::createHosts([
			[
				'host' => 'Host with full inventory list',
				'description' => 'Long Description Long Description Long Description Long Description Long Description '
						. 'Long Description Long Description Long Description Long Description Long Description '
						. 'Long Description Long Description Long Description Long Description Long Description ',
				'groups' => [['groupid' => 4]], // Zabbix servers.
				'items' => [
					[
						'name' => 'Numeric for HostCard widget 1',
						'key_' => 'numeric_host_card[1]',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				],
				'inventory_mode' => 0,
				'inventory' => [
					'alias' => 'ZabbixOwl',
					'asset_tag' => 'AT-12345',
					'chassis' => '1234-5678-9101',
					'contact' => 'owl@zabbix.com',
					'contract_number' => 'C-123456',
					'date_hw_decomm' => '2024-12-18',
					'date_hw_expiry' => '2025-12-18',
					'date_hw_install' => '2023-12-18',
					'date_hw_purchase' => '2023-01-01',
					'deployment_status' => 'In production',
					'hardware' => 'Hardware 7.2',
					'hardware_full' => 'Full hardware details',
					'host_networks' => '111.111.11',
					'host_netmask' => '255.255.255.0',
					'host_router' => '111.111.1.1',
					'hw_arch' => 'x86_64',
					'installer_name' => 'John Smith',
					'location' => 'Data Center 1',
					'location_lat' => '37.7749',
					'location_lon' => '-122.4194',
					'macaddress_a' => '00:1A:2B:3C:4D:5E',
					'macaddress_b' => '00:1A:2B:3C:4D:5F',
					'model' => 'Model Owl',
					'name' => 'My Server',
					'notes' => 'This is a critical server.',
					'oob_ip' => '100.100.100.10',
					'oob_netmask' => '255.255.255.0',
					'oob_router' => '100.100.100.10',
					'os' => 'Ubuntu 22.04',
					'os_full' => 'Ubuntu 22.04 LTS',
					'os_short' => 'Ubuntu',
					'poc_1_cell' => '+1234567890',
					'poc_1_email' => 'zabbix@owl.com',
					'poc_1_name' => 'Zabbix Owl',
					'poc_1_notes' => 'Available 24/7',
					'poc_1_phone_a' => '+123456789',
					'poc_1_phone_b' => '+987654321',
					'poc_1_screen' => 'No',
					'poc_2_cell' => '+1234567891',
					'poc_2_email' => 'owl@zabbix.com',
					'poc_2_name' => 'Owl Zabbix',
					'poc_2_notes' => 'Backup contact',
					'poc_2_phone_a' => '+123456789',
					'poc_2_phone_b' => '+987654321',
					'poc_2_screen' => 'Yes',
					'serialno_a' => 'SN1234567890',
					'serialno_b' => 'SN0987654321',
					'site_address_a' => 'Riga',
					'site_address_b' => 'Riga',
					'site_address_c' => 'Riga',
					'site_city' => 'Riga',
					'site_country' => 'Rigaland',
					'site_notes' => 'Near the central park',
					'site_rack' => 'Rack 42U',
					'site_state' => 'StateName',
					'site_zip' => '12345',
					'software' => 'Zabbix Agent 7.2',
					'software_app_a' => 'App1',
					'software_app_b' => 'App2',
					'software_app_c' => 'App3',
					'tag' => 'Critical',
					'type' => 'Server',
					'type_full' => 'Physical Server',
					'url_a' => 'http://localhost.com',
					'url_b' => 'http://localhost.org',
					'url_c' => 'http://localhost.org',
					'vendor' => 'Vendor'
				]
			],
			[
				'host' => 'Display',
				'groups' => [['groupid' => 4]], // Zabbix servers.
				'items' => [
					[
						'name' => 'Item 1',
						'key_' => 'hostcard_display_1',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Item 2',
						'key_' => 'hostcard_display_2',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Item 3',
						'key_' => 'hostcard_display_3',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Item 4',
						'key_' => 'hostcard_display_4',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Item 5',
						'key_' => 'hostcard_display_5',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					]
				]
			],
			[
				'host' => 'Host for maintenance icon in HostCard widget',
				'groups' => [['groupid' => $groupids['Maintenance host group for HostCard widget']]],
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
				'host' => 'Host tags group for HostCard widget',
				'groups' => [['groupid' => $groupids['Host tags group for HostCard widget']]],
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
				'groups' => [['groupid' => $groupids['Disabled hosts for HostCard widget']]],
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
		$maintenance_hostid = $response['hostids']['Host for maintenance icon in HostCard widget'];

		foreach ([100, 200, 300, 400, 500] as $i => $value) {
			CDataHelper::addItemData($itemids['Display:hostcard_display_'.($i + 1)], $value);
		}

		// Create Maintenance and host in maintenance.
		$maintenances = CDataHelper::call('maintenance.create', [
			[
				'name' => 'HostCard host maintenance',
				'active_since' => time() - 1000,
				'active_till' => time() + 31536000,
				'groups' => [['groupid' => $groupids['Maintenance host group for HostCard widget']]],
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
				'name' => 'Dashboard for creating HostCard widgets',
				'auto_start' => 0,
				'pages' => [[]]
			],
			[
				'name' => 'Dashboard for simple updating HostCard widget',
				'auto_start' => 0,
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'hostcard',
								'name' => 'Update Host card',
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 5,
								'fields' => [
									[
										'type' => 1,
										'name' => 'items.0',
										'value' => 'Numeric for hostcard 1'
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for canceling HostCard widget',
				'auto_start' => 0,
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'hostcard',
								'name' => 'CancelHostCardWidget',
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 5,
								'fields' => [
									[
										'type' => 1,
										'name' => 'items.0',
										'value' => 'Numeric for host 1'
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for deleting HostCard widget',
				'auto_start' => 0,
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'hostcard',
								'name' => 'DeleteHostCardWidget',
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 5,
								'fields' => [
									[
										'type' => 1,
										'name' => 'items.0',
										'value' => 'Numeric for hostcard 1'
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for HostCard screenshot',
				'auto_start' => 0,
				'pages' => [
					[
						'name' => '3 dots',
						'widgets' => [
							[
								'type' => 'hostcard',
								'x' => 0,
								'y' => 0,
								'width' => 6,
								'height' => 2,
								'view_mode' => 0
							]
						]
					],
					[
						'name' => 'items and 3 dots',
						'widgets' => [
							[
								'type' => 'hostcard',
								'x' => 0,
								'y' => 0,
								'width' => 9,
								'height' => 2,
								'view_mode' => 0
							]
						]
					],
					[
						'name' => '5 items grouped',
						'widgets' => [
							[
								'type' => 'hostcard',
								'x' => 0,
								'y' => 0,
								'width' => 13,
								'height' => 4,
								'view_mode' => 0
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

	public function testDashboardHostCardWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for creating HostCard widgets'])->waitUntilReady();

		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$form = $dashboard->edit()->addWidget()->asForm();
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Host card')]);

		// Check name field maxlength.
		$this->assertEquals(255, $form->getField('Name')->getAttribute('maxlength'));

		// Check fields, labels and required fields.
		$this->assertEquals(['Type', 'Show header', 'Name', 'Refresh interval', 'Host', 'Show suppressed problems', 'Show'],
				array_values($form->getLabels(CElementFilter::VISIBLE)->asText())
		);

		// Check fields "Refresh interval" values.
		$this->assertEquals(['Default (1 minute)',  'No refresh', '10 seconds', '30 seconds', '1 minute', '2 minutes', '10 minutes',  '15 minutes'],
				$form->getField('Refresh interval')->asDropdown()->getOptions()->asText());

		// Check default values.
		$default_values = [
			'Name' => '',
			'Refresh interval' => 'Default (1 minute)',
			'Host' => '',
			'Show header' => true,
			'Show suppressed problems' => false,
			'id:sections_0' => 'Monitoring',
			'id:sections_1' => 'Availability',
			'id:sections_2' => 'Monitored by',
		];

		$form->checkValue($default_values);

		// Check Select popup dropdowns for Host groups and Hosts.
		$popup_menu_selector = 'xpath:.//button[contains(@class, "zi-chevron-down")]';

		$label = $form->getField('Host');

		// Check Select dropdown menu button.
		$menu_button = $label->query($popup_menu_selector)->asPopupButton()->one();
		$this->assertEquals(['Host', 'Widget', 'Dashboard'], $menu_button->getMenu()->getItems()->asText());

		// After selecting Dashboard from dropdown menu, check hint and field value.
		if ($label === 'Host') {
			$menu_button->select('Dashboard');
			$form->checkValue(['Host' => 'Dashboard']);
			$this->assertTrue($label->query('xpath:.//span[@data-hintbox-contents="Dashboard is used as data source."]')
					->one()->isVisible()
			);
		}

		// After selecting Widget from dropdown menu, check overlay dialog appearance and title.
		$menu_button->select('Widget');
		$dialogs = COverlayDialogElement::find()->all();
		$this->assertEquals('Widget', $dialogs->last()->waitUntilReady()->getTitle());
		$dialogs->last()->close(true);

		// After clicking on Select button, check overlay dialog appearance and title.
		$label = ['Host', 'Hosts'];
		$field = $form->getField($label[0]);
		$field->query('button:Select')->waitUntilCLickable()->one()->click();
		$dialogs = COverlayDialogElement::find()->all();
		$this->assertEquals($label[1], $dialogs->last()->waitUntilReady()->getTitle());
		$dialogs->last()->close(true);

		// Check default and available options in 'Show' section.
		$show_options = ['Host groups', 'Description', 'Monitoring', 'Availability', 'Monitored by', 'Templates', 'Inventory', 'Tags'];
		$show_form = $form->query("xpath", "//div[contains(@class, 'form-field')]//table[@id='sections-table']")->one();

		// Clear all default options
		$show_form->query('button:Remove')->all()->click();

		for($i = 0; $i <= 7; $i++) {
			$show_form->query('button:Add')->one()->click();
			// Inventory option.
			if($i == 7){
				$this->assertTrue($form->getField('Inventory fields')->isVisible());
			}

			$this->assertEquals($show_options[$i], $show_form->query("xpath", "//z-select[@id='sections_".$i."']"
					. "/button[contains(@class, 'focusable')]")->one()->getText()
			);
		}

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
						'Name' => 'Host is not selected',
						'Host' => ''
					],
					'error_message' => [
						'Invalid parameter "Host": cannot be empty.'
					]
				]
			],
			// #1.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Display',
						'Name' => '',
						'Show header' => false
					]
				]
			],
			// #2.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Display'
					]
				]
			],
			// #3.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Display',
						'Name' => 'Host card for "Display" host',
						'Show header' => true,
						'Show suppressed problems' => true,
					],
					'Show' => [
						'sections_0' => 'Host groups'
					]
				]
			],
			// #4.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Display',
						'Name' => 'Host card for "Display" host',
						'Show header' => true,
						'Show suppressed problems' => true,
					],
					'Show' => [
						'sections_0' => 'Host groups',
						'sections_1' => 'Description'
					]
				]
			],
			// #5.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Display',
						'Name' => 'Host card for "Display" host',
						'Show header' => true,
						'Show suppressed problems' => true,
					],
					'Show' => [
						'sections_0' => 'Host groups',
						'sections_1' => 'Description',
						'sections_2' => 'Monitoring'
					]
				]
			],
			// #6.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Display',
						'Name' => 'Host card for "Display" host',
						'Show header' => true,
						'Show suppressed problems' => true,
					],
					'Show' => [
						'sections_0' => 'Host groups',
						'sections_1' => 'Description',
						'sections_2' => 'Monitoring',
						'sections_3' => 'Availability'
					]
				]
			],
			// #7.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Display',
						'Name' => 'Host card for "Display" host',
						'Show header' => true,
						'Show suppressed problems' => true,
					],
					'Show' => [
						'sections_0' => 'Host groups',
						'sections_1' => 'Description',
						'sections_2' => 'Monitoring',
						'sections_3' => 'Availability',
						'sections_4' => 'Monitored by'
					]
				]
			],
			// #8.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Display',
						'Name' => 'Host card for "Display" host',
						'Show header' => true,
						'Show suppressed problems' => true,
					],
					'Show' => [
						'sections_0' => 'Host groups',
						'sections_1' => 'Description',
						'sections_2' => 'Monitoring',
						'sections_3' => 'Availability',
						'sections_4' => 'Monitored by',
						'sections_5' => 'Templates'
					]
				]
			],
			// #9.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Host with full inventory list',
						'Name' => 'Host card for "Host with full inventory list" host',
						'Show header' => true,
						'Show suppressed problems' => true,
					],
					'Show' => [
						'sections_0' => 'Host groups',
						'sections_1' => 'Description',
						'sections_2' => 'Monitoring',
						'sections_3' => 'Availability',
						'sections_4' => 'Monitored by',
						'sections_5' => 'Templates',
						'sections_6' => 'Inventory'
					]
				]
			],
			// #10.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Host with full inventory list',
						'Name' => 'Host card for "Host with full inventory list" host',
						'Show header' => true,
						'Show suppressed problems' => true,
					],
					'Show' => [
						'sections_0' => 'Host groups',
						'sections_1' => 'Description',
						'sections_2' => 'Monitoring',
						'sections_3' => 'Availability',
						'sections_4' => 'Monitored by',
						'sections_5' => 'Templates',
						'sections_6' => 'Inventory',
						'sections_7' => 'Tags'
					]
				]
			],
			// #11.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Display',
						'Name' => '😅😅😅Name of Host card widget 😅😅😅',
						'Show header' => true,
						'Show suppressed problems' => true,
					],
					'Show' => [
						'sections_0' => 'Host groups',
						'sections_1' => 'Description',
						'sections_2' => 'Templates'
					]
				]
			],
			// #12.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Display',
						'Name' => 'Наименование виджета для карточки хоста',
						'Show header' => false,
						'Show suppressed problems' => true,
					],
					'Show' => [
						'sections_0' => 'Host groups',
						'sections_1' => 'Description',
						'sections_2' => 'Templates'
					]
				]
			],
			// #13.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Host with full inventory list',
						'Name' => 'longtextlongtextlongtextlongtextlongtextlongtextlongtextlongtextlongtextlongtext'
								. 'longtextlongtextlongtextlongtextlongtextlongtextlongtextlongtextlongtextlongtext',
						'Show header' => false,
						'Show suppressed problems' => false,
					],
					'Show' => [
						'sections_0' => 'Host groups',
						'sections_1' => 'Templates'
					]
				]
			],
			// #14.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Host with full inventory list',
						'Name' => 'Few inventory fields is selected',
						'Show header' => false,
						'Show suppressed problems' => false,
					],
					'Show' => [
						'sections_0' => 'Inventory',
						'sections_1' => 'Description',
						'sections_2' => 'Templates'
					],
					'Inventory' => ['Name','OS','Hardware (Full details)','Contact']
				]
			]
		];
	}

	/**
	 * Create Host Card widget.
	 *
	 * @dataProvider getCreateData
	 */
	public function testDashboardHostCardWidget_Create($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for creating HostCard widgets'])->waitUntilReady();

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
	 * Host Card widget simple update without any field change.
	 */
	public function testDashboardHostCardWidget_SimpleUpdate() {
		// Hash before simple update.
		self::$old_hash = CDBHelper::getHash(self::SQL);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for simple updating HostCard widget'])->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dashboard->edit()->getWidget('UpdateHostCard')->edit()->submit();
		$dashboard->getWidget('UpdateHostCard');
		$dashboard->save();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Compare old hash and new one.
		$this->assertEquals(self::$old_hash, CDBHelper::getHash(self::SQL));
	}

	/**
	 * Creates the base widget used for the update scenario.
	 */
	public function prepareHostCardUpdate() {
		$providedData = $this->getProvidedData();
		$data = reset($providedData);

		// Create a dashboard with the widget for updating.
		$response = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for HostCard update '.md5(serialize($data)),
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'hostcard',
								'name' => 'UpdateHostCard',
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 5,
								'fields' => [
									[
										'type' => 1
										// add fields
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
	 * Update Host Card widget.
	 *
	 * @onBefore prepareHostCardUpdate
	 *
	 * @dataProvider getCreateData
	 */
	public function testDashboardHostCardWidget_Update($data) {
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
	 * Delete Host Card widget.
	 */
	public function testDashboardHostCardWidget_Delete() {
		$widget_name = 'DeleteHostCardWidget';
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for deleting HostCard widget'])->waitUntilReady();
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
						'Name' => 'Resolved macros'
					],
					'result' => 'Display hostcard_display_1 100'
				]
			]
		];
	}

	/**
	 * Check different data display on Host Card widget.
	 *
	 * @onBefore prepareHostCardUpdate
	 *
	 * @dataProvider getDisplayData
	 */
	public function testDashboardHostCardWidget_Display($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$disposable_dashboard_id)->waitUntilReady();
		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$this->fillWidgetForm($data, 'update', $dashboard);
		$dashboard->save();

		// Check message that dashboard saved.
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		$this->page->waitUntilReady();
		$widget = $dashboard->getWidget($data['fields']['Name']);

		// Check that correct value displayed on HostCard widget.
		$content = $widget->getContent();
		if (array_key_exists('check_label', $data)) {
			$displayed = $content->query('class', $data['check_label'])->one()->getText();
			$this->assertEquals($displayed, $data['result']);
			$this->assertFalse($content->query('class', $data['turned_off_label'])->exists());
		}
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
					'save_dashboard' => false
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
	 * Check cancel scenarios for Host Card widget.
	 *
	 * @dataProvider getCancelData
	 */
	public function testDashboardHostCardWidget_Cancel($data) {
		self::$old_hash = CDBHelper::getHash(self::SQL);
		$new_name = 'Widget to be cancelled';

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for canceling HostCard widget']
		);
		$dashboard = CDashboardElement::find()->one()->edit();
		self::$old_widget_count = $dashboard->getWidgets()->count();

		// Start updating or creating a widget.
		if (CTestArrayHelper::get($data, 'update', false)) {
			$form = $dashboard->getWidget('CancelHostCardWidget')->edit();
		}
		else {
			$form = $dashboard->addWidget()->asForm();
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Host card')]);
		}

		$form->fill([
			'Name' => $new_name,
			'Refresh interval' => '15 minutes',
			'Host' => 'ЗАББИКС Сервер'
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
				foreach (['CancelHostCardWidget' => true, $new_name => false] as $name => $valid) {
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
	 * Check different compositions for Host Card widget.
	 */
	public function testDashboardHostCardWidget_Screenshots() {
		$this->page->login();

		for ($i = 1; $i <= 3; $i++) {
			$this->page->open('zabbix.php?action=dashboard.view&dashboardid='.
					self::$dashboardid['Dashboard for HostCard screenshot'].'&page='.$i)->waitUntilReady();

			$element = CDashboardElement::find()->one()->getWidget('Host card');
			$this->assertScreenshot($element, 'hostcard_'.$i);
		}
	}

	/**
	 * Create or update Host Card widget.
	 *
	 * @param array             $data         data provider
	 * @param string            $action       create/update HostCard widget
	 * @param CDashboardElement $dashboard    given dashboard
	 */
	protected function fillWidgetForm($data, $action, $dashboard) {
		$form = ($action === 'create')
			? $dashboard->edit()->addWidget()->asForm()
			: $dashboard->getWidget('UpdateHostCard')->edit();

		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Host card')]);
		$form->fill($data['fields']);

		if (array_key_exists('Show', $data)) {
			$this->fillShowOptions($data['Show'], $form);

			if (array_key_exists('Inventory', $data)) {
				$form->getField('Inventory fields')->fill($data['Inventory']);
			}
		}

		$form->submit();
	}

	protected function fillShowOptions($data, $form) {
		$show_form = $form->query("xpath", "//div[contains(@class, 'form-field')]//table[@id='sections-table']")->one();
		$show_form->query('button:Remove')->all()->click();

		foreach($data as $fieldid => $option){
			$form->getFieldContainer('Show')->query('button:Add')->one()->waitUntilClickable()->click();
			$show_form->query('id:'.$fieldid)->asDropdown()->one()->fill($option);
		}
	}

	protected function selectInventory($data, $form) {
		$show_form = $form->query("xpath", "//div[contains(@class, 'form-field')]//table[@id='sections-table']")->one();
		$show_form->query('button:Remove')->all()->click();

		foreach($data as $inventory_field){
			$form->getFieldContainer('Show')->query('button:Add')->one()->waitUntilClickable()->click();
			$show_form->query('id:'.$fieldid)->asDropdown()->one()->fill($option);
		}
	}

	/**
	 * Check created or updated Host Card widget.
	 *
	 * @param array             $data         data provider
	 * @param string            $action       create/update HostCard widget
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
				? (($data['fields']['Name'] === '') ? 'Host card' : $data['fields']['Name'])
				: 'Host card';

			$dashboard->getWidget($header);
			$dashboard->save();

			// Check message that dashboard saved.
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');

			// Check widget amount that it is added.
			$this->assertEquals(self::$old_widget_count + (($action === 'create') ? 1 : 0), $dashboard->getWidgets()->count());

			$form = $dashboard->getWidget($header)->edit()->asForm();

			$form->checkValue($data['fields']);
			COverlayDialogElement::find()->one()->close();
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		}
	}

	/**
	 * Add or Check tags in Host Card widget.
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
