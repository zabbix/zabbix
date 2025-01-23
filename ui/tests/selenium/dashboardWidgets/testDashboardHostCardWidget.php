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

		// Get array with Host Group names.
		$groupids = CDataHelper::getIds('name');

		// Get Template IDs.
		$template1 = CDBHelper::getValue('SELECT hostid FROM hosts WHERE name='.zbx_dbstr('Zabbix server health'));
		$template2 = CDBHelper::getValue('SELECT hostid FROM hosts WHERE name='.zbx_dbstr('Inheritance test template'));

		// Create Proxy.
		$proxies = CDataHelper::call('proxy.create', [
			[
				'name' => 'Proxy for host card widget',
				'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
			]
		]);

		// Create Proxy groups.
		$proxie_group = CDataHelper::call('proxygroup.create', [
			[
				'name' => 'Proxy group for host card widget',
				'failover_delay' => '10',
				'min_online' => '1'
			]
		]);

		$response = CDataHelper::createHosts([
			[
				'host' => 'Host with full inventory list',
				'description' => 'Long Description Long Description Long Description Long Description Long Description '.
						'Long Description Long Description Long Description Long Description Long Description '.
						'Long Description Long Description Long Description Long Description Long Description ',
				'groups' => [['groupid' => 4]], // Zabbix servers.
				'interfaces' => [
					'type' => INTERFACE_TYPE_AGENT,
					'main' => 1,
					'useip' => 1,
					'ip' => '127.0.0.1',
					'dns' => '',
					'port' => '10050'
				],
				'templates' => [
					[
						'templateid' => $template1
					],
					[
						'templateid' => $template2
					]
				],
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
				],
				'monitored_by' => 2,
				'proxy_groupid' => $proxie_group['proxy_groupids'][0]
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
				],
				'monitored_by' => 1,
				'proxyid' => $proxies['proxyids'][0]
			],
			[
				'host' => 'Disabled host',
				'groups' => [['groupid' => 4]], // Zabbix servers.
				'status' => 1,
				'inventory_mode' => 0,
				'inventory' => [
					'location_lat' => '10',
					'location_lon' => '10'
				]
			]
		]);
		$itemids = $response['itemids'];
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
				'name' => 'Dashboard for HostCard widget update',
				'auto_start' => 0,
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'hostcard',
								'name' => 'Host card',
								'x' => 0,
								'y' => 0,
								'width' => 20,
								'height' => 5,
								'fields' => [
									[
										'type' => 3,
										'name' => 'hostid.0',
										'value' => 10084
									],
									[
										'type' => 0,
										'name' => 'sections.0',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'sections.1',
										'value' => 3
									],
									[
										'type' => 0,
										'name' => 'sections.2',
										'value' => 4
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
										'type' => 3,
										'name' => 'hostid.0',
										'value' => 10084
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
										'type' => 3,
										'name' => 'hostid.0',
										'value' => 10084
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for HostCard widget display check',
				'auto_start' => 0,
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'hostcard',
								'name' => 'Display host card with 1 column layout (Default)',
								'x' => 0,
								'y' => 0,
								'width' => 19,
								'height' => 5,
								'fields' => [
									[
										'type' => 3,
										'name' => 'hostid.0',
										'value' => 10084
									],
									[
										'type' => 0,
										'name' => 'sections.0',
										'value' => 3
									],
									[
										'type' => 0,
										'name' => 'sections.1',
										'value' => 4
									],
									[
										'type' => 0,
										'name' => 'sections.2',
										'value' => 5
									]
								]
							],
							[
								'type' => 'hostcard',
								'name' => 'Display host card with 2 column layout',
								'x' => 19,
								'y' => 0,
								'width' => 36,
								'height' => 5,
								'fields' => [
									[
										'type' => 3,
										'name' => 'hostid.0',
										'value' => $response['hostids']['Host with full inventory list']
									],
									[
										'type' => 0,
										'name' => 'sections.0',
										'value' => 3
									],
									[
										'type' => 0,
										'name' => 'sections.1',
										'value' => 4
									],
									[
										'type' => 0,
										'name' => 'sections.2',
										'value' => 5
									],
									[
										'type' => 0,
										'name' => 'sections.3',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'sections.4',
										'value' => 6
									],
									[
										'type' => 0,
										'name' => 'sections.5',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'sections.6',
										'value' => 7
									],
									[
										'type' => 0,
										'name' => 'sections.7',
										'value' => 0
									]
								]
							],
							[
								'type' => 'hostcard',
								'name' => 'Display host card with 3 column layout',
								'x' => 0,
								'y' => 5,
								'width' => 64,
								'height' => 5,
								'fields' => [
									[
										'type' => 3,
										'name' => 'hostid.0',
										'value' => $response['hostids']['Host with full inventory list']
									],
									[
										'type' => 0,
										'name' => 'sections.0',
										'value' => 3
									],
									[
										'type' => 0,
										'name' => 'sections.1',
										'value' => 4
									],
									[
										'type' => 0,
										'name' => 'sections.2',
										'value' => 5
									],
									[
										'type' => 0,
										'name' => 'sections.3',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'sections.4',
										'value' => 6
									],
									[
										'type' => 0,
										'name' => 'sections.5',
										'value' => 2
									],
									[
										'type' => 0,
										'name' => 'sections.6',
										'value' => 7
									],
									[
										'type' => 0,
										'name' => 'sections.7',
										'value' => 0
									]
								]
							],
							[
								'type' => 'hostcard',
								'name' => 'Check link to proxy configuration form',
								'x' => 0,
								'y' => 10,
								'width' => 25,
								'height' => 5,
								'fields' => [
									[
										'type' => 3,
										'name' => 'hostid.0',
										'value' => $response['hostids']['Host with items with tags']
									],
									[
										'type' => 0,
										'name' => 'sections.0',
										'value' => 4
									]
								]
							],
							[
								'type' => 'hostcard',
								'name' => 'Check link to proxy group configuration form',
								'x' => 25,
								'y' => 10,
								'width' => 25,
								'height' => 5,
								'fields' => [
									[
										'type' => 3,
										'name' => 'hostid.0',
										'value' => $response['hostids']['Host for maintenance icon in HostCard widget']
									],
									[
										'type' => 0,
										'name' => 'sections.0',
										'value' => 4
									]
								]
							],
							[
								'type' => 'hostcard',
								'name' => 'Disabled host',
								'x' => 0,
								'y' => 15,
								'width' => 25,
								'height' => 5,
								'fields' => [
									[
										'type' => 3,
										'name' => 'hostid.0',
										'value' => $response['hostids']['Disabled host']
									],
									[
										'type' => 0,
										'name' => 'sections.0',
										'value' => 6
									]
								]
							]
						]
					]
				]
			]
		]);
		self::$dashboardid = CDataHelper::getIds('name');
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
		$this->assertTrue($form->isRequired('Host'));

		// Check fields "Refresh interval" values.
		$this->assertEquals(['Default (1 minute)',  'No refresh', '10 seconds', '30 seconds', '1 minute', '2 minutes', '10 minutes',  '15 minutes'],
				$form->getField('Refresh interval')->asDropdown()->getOptions()->asText()
		);

		// Check default values.
		$default_values = [
			'Name' => '',
			'Refresh interval' => 'Default (1 minute)',
			'Host' => '',
			'Show header' => true,
			'Show suppressed problems' => false,
			'id:sections_0' => 'Monitoring',
			'id:sections_1' => 'Availability',
			'id:sections_2' => 'Monitored by'
		];

		$form->checkValue($default_values);
		$label = $form->getField('Host');

		// Check Select dropdown menu button.
		$menu_button = $label->query('xpath:.//button[contains(@class, "zi-chevron-down")]')->asPopupButton()->one();
		$this->assertEquals(['Host', 'Widget', 'Dashboard'], $menu_button->getMenu()->getItems()->asText());

		// After selecting Dashboard from dropdown menu, check hint and field value.
		$menu_button->select('Dashboard');
		$form->checkValue(['Host' => 'Dashboard']);
		$this->assertTrue($label->query('xpath', './/span[@data-hintbox-contents="Dashboard is used as data source."]')
				->one()->isVisible()
		);

		// After selecting Widget from dropdown menu, check overlay dialog appearance and title.
		$menu_button->select('Widget');
		$dialogs = COverlayDialogElement::find()->all();
		$this->assertEquals('Widget', $dialogs->last()->waitUntilReady()->getTitle());
		$dialogs->last()->close(true);

		// After clicking on Select button, check overlay dialog appearance and title.
		$label->query('button:Select')->waitUntilCLickable()->one()->click();
		$dialogs = COverlayDialogElement::find()->all();
		$this->assertEquals('Hosts', $dialogs->last()->waitUntilReady()->getTitle());
		$dialogs->last()->close(true);

		// Check default and available options in 'Show' section.
		$show_form = $form->getFieldContainer('Show');

		// Clear all default options
		$show_form->query('button:Remove')->all()->click();

		$show_options = ['Host groups', 'Description', 'Monitoring', 'Availability', 'Monitored by', 'Templates',
				'Inventory', 'Tags'];
		$disabled_result = [];
		foreach ($show_options as $i => $option) {
			$show_form->query('button:Add')->one()->click();

			// Check that added correct option by default.
			$this->assertEquals($option, $show_form->query('xpath', '//z-select[@id="sections_'.$i.'"]'.
					'/button[contains(@class, "focusable")]')->one()->getText()
			);

			// Check that added options are disabled in dropdown menu.
			$disabled = $show_form->query('xpath', '//z-select[@id="sections_'.$i.'"]')->one()->asDropdown()
					->getOptions()->filter(new CElementFilter(CElementFilter::ATTRIBUTES_PRESENT, ['disabled']))->asText();
			$this->assertEquals($disabled_result, $disabled);
			$disabled_result[] = $option;
		}

		// Check that Add button became disabled.
		$this->assertFalse($show_form->query('button:Add')->one()->isEnabled());

		// If the Inventory option was selected, the Inventory fields field becomes visible.
		$show_form->query('button:Remove')->all()->click();
		$show_form->query('button:Add')->one()->click();

		foreach ($show_options as $option) {
			$show_form->query('xpath', './/z-select[@id="sections_0"]')->one()->asDropdown()->select($option);

			if ($option === 'Inventory') {
				$this->assertTrue($form->getField('Inventory fields')->isVisible());
				$form->getField('Inventory fields')->query('button:Select')->waitUntilCLickable()->one()->click();
				$inventory_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
				$this->assertEquals('Inventory', $inventory_dialog->getTitle());
				$inventory_dialog->close(true);
			}
			else {
				$this->assertFalse($form->getField('Inventory fields')->isVisible());
			}
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
						'Name' => 'Trimmed name_1  ',
						'Show header' => true
					],
					'Show' => [],
					'trim' => true
				]
			],
			// #2.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Display',
						'Name' => '  Trimmed name_2',
						'Show header' => true
					],
					'Show' => [],
					'trim' => true
				]
			],
			// #3.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Display',
						'Name' => '  Trimmed name_3  ',
						'Show header' => true
					],
					'Show' => [],
					'trim' => true
				]
			],
			// #4.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Display',
						'Name' => '🙂🙂🙂🙂🙂🙂🙂🙂',
						'Show header' => false
					],
					'Show' => []
				]
			],
			// #5.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Display',
						'Name' => '"],*,a[x=": "],*,a[x="/\|'
					]
				]
			],
			// #6.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Display',
						'Name' => '<img src=\"x\" onerror=\"alert("ERROR");\"/>',
						'Show header' => true,
						'Show suppressed problems' => true
					],
					'Show' => [
						'sections_0' => 'Host groups'
					]
				]
			],
			// #7.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Display',
						'Name' => '<script>alert("ERROR")</script>',
						'Show header' => true,
						'Show suppressed problems' => true
					],
					'Show' => [
						'sections_0' => 'Host groups',
						'sections_1' => 'Description'
					]
				]
			],
			// #8.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Display',
						'Name' => 'Simple name for Host Card widget',
						'Show header' => true,
						'Show suppressed problems' => true
					],
					'Show' => [
						'sections_0' => 'Host groups',
						'sections_1' => 'Description',
						'sections_2' => 'Monitoring'
					]
				]
			],
			// #9.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Display',
						'Name' => 'empty space    in the middle',
						'Show header' => true,
						'Show suppressed problems' => true
					],
					'Show' => [
						'sections_0' => 'Host groups',
						'sections_1' => 'Description',
						'sections_2' => 'Monitoring',
						'sections_3' => 'Availability'
					]
				]
			],
			// #10.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Display',
						'Name' => 'Наименование карточки Виджета',
						'Show header' => true,
						'Show suppressed problems' => true
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
			// #11.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Display',
						'Name' => '105\'; --DROP TABLE Users',
						'Show header' => true,
						'Show suppressed problems' => true
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
			// #12.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Host with full inventory list',
						'Name' => '127.0.0.1',
						'Show header' => true,
						'Show suppressed problems' => true
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
			// #13.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Host with full inventory list',
						'Name' => 'Host card for "Host with full inventory list" host',
						'Show header' => true,
						'Show suppressed problems' => true
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
					],
					'Screenshot' => true
				]
			],
			// #14.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Display',
						'Name' => '😅😅😅 TESThostCARD 😅😅😅',
						'Show header' => true,
						'Show suppressed problems' => true
					],
					'Show' => [
						'sections_0' => 'Host groups',
						'sections_1' => 'Description',
						'sections_2' => 'Templates'
					]
				]
			],
			// #15.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Display',
						'Name' => 'HostMixedCase',
						'Show header' => false,
						'Show suppressed problems' => true
					],
					'Show' => [
						'sections_0' => 'Host groups',
						'sections_1' => 'Description',
						'sections_2' => 'Templates'
					]
				]
			],
			// #16.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Host with full inventory list',
						'Name' => 'longtextlongtextlongtextlongtextlongtextlongtextlongtextlongtextlongtextlongtext'
								. 'longtextlongtextlongtextlongtextlongtextlongtextlongtextlongtextlongtextlongtext',
						'Show header' => false,
						'Show suppressed problems' => false
					],
					'Show' => [
						'sections_0' => 'Host groups',
						'sections_1' => 'Templates'
					]
				]
			],
			// #17.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Host with full inventory list',
						'Name' => 'Test-テスト-01',
						'Show header' => false,
						'Show suppressed problems' => false
					],
					'Show' => [
						'sections_0' => 'Inventory',
						'sections_1' => 'Description',
						'sections_2' => 'Templates'
					],
					'Inventory' => ['Name', 'OS', 'Hardware (Full details)', 'Contact']
				]
			],
			// #18.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Host with full inventory list',
						'Name' => 'test<s><\x3cscript>alert(\'XSS\')</script><s>'
					]
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
				self::$dashboardid['Dashboard for HostCard widget update'])->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$dashboard->edit()->getWidget('Host card')->edit()->submit();
		$dashboard->getWidget('Host card');
		$dashboard->save();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Compare old hash and new one.
		$this->assertEquals(self::$old_hash, CDBHelper::getHash(self::SQL));
	}

	/**
	 * Update Host Card widget.
	 *
	 * @backup widget
	 * @dataProvider getCreateData
	 */
	public function testDashboardHostCardWidget_Update($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for HostCard widget update'])->waitUntilReady();

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
					'Header' => 'Display host card with 1 column layout (Default)',
					'Sections' => [
						'availability',
						'monitored-by',
						'templates'
					],
					'Severity' => [
						'average' => 1,
						'warning' => 5
					],
					'Host' => 'ЗАББИКС Сервер',
					'Availability' => ['ZBX'],
					'Monitored by' => ['Zabbix server'],
					'Templates' => [
						'Linux by Zabbix agent',
						'Zabbix server health'
					]
				]
			],
			// #1.
			[
				[
					'Header' => 'Display host card with 2 column layout',
					'Sections' => [
						'availability',
						'inventory',
						'monitored-by',
						'monitoring',
						'templates',
						'description',
						'host-groups'
					],
					'Host' => 'Host with full inventory list',
					'Availability' => ['ZBX'],
					'Monitored by' => ['Zabbix server'],
					'Monitoring' => [
						'Dashboards' => 1,
						'Latest data' => 103,
						'Graphs' => 4,
						'Web' => 4
					],
					'Templates' => [
						'Inheritance test template',
						'Zabbix server health'
					],
					'Tags' => [
						'class: software',
						'target: server',
						'target: zabbix'
					],
					'Description' => 'Long Description Long Description Long Description Long Description Long '.
							'Description Long Description Long Description Long Description Long Description Long '.
							'Description Long Description Long Description Long Description Long Description Long '.
							'Description',
					'Host groups' => ['Zabbix servers']
				]
			],
			// #2.
			[
				[
					'Header' => 'Display host card with 3 column layout',
					'Sections' => [
						'monitored-by',
						'monitoring',
						'templates',
						'description',
						'host-groups'
					],
					'Host' => 'Host with full inventory list',
					'Availability' => ['ZBX'],
					'Monitored by' => ['Zabbix server'],
					'Monitoring' => [
						'Dashboards' => 1,
						'Latest data' => 103,
						'Graphs' => 4,
						'Web' => 4
					],
					'Templates' => [
						'Inheritance test template',
						'Zabbix server health'
					],
					'Tags' => [
						'class: software',
						'target: server',
						'target: zabbix'
					],
					'Description' => 'Long Description Long Description Long Description Long Description Long '.
							'Description Long Description Long Description Long Description Long Description Long '.
							'Description Long Description Long Description Long Description Long Description Long '.
							'Description',
					'Host groups' => ['Zabbix servers'],
					'Inventory' => [
						'Type' => 'Server',
						'Type (Full details)' => 'Physical Server',
						'Name' => 'My Server',
						'Alias' => 'ZabbixOwl',
						'OS' => 'Ubuntu 22.04',
						'OS (Full details)' => 'Ubuntu 22.04 LTS',
						'OS (Short)' => 'Ubuntu',
						'Serial number A' => 'SN1234567890',
						'Serial number B' => 'SN0987654321',
						'Tag' => 'Critical',
						'Asset tag' => 'AT-12345',
						'MAC address A' => '00:1A:2B:3C:4D:5E',
						'MAC address B' => '00:1A:2B:3C:4D:5F',
						'Hardware' => 'Hardware 7.2',
						'Hardware (Full details)' => 'Full hardware details',
						'Software' => 'Zabbix Agent 7.2',
						'Software application A' => 'App1',
						'Software application B' => 'App2',
						'Software application C' => 'App3',
						'Contact' => 'owl@zabbix.com',
						'Location' => 'Data Center 1',
						'Location latitude' => '37.7749',
						'Location longitude' => '-122.4194',
						'Notes' => 'This is a critical server.',
						'Chassis' => '1234-5678-9101',
						'Model' => 'Model Owl',
						'HW architecture' => 'x86_64',
						'Vendor' => 'Vendor',
						'Contract number' => 'C-123456',
						'Installer name' => 'John Smith',
						'Deployment status' => 'In production',
						'URL A' => 'http://localhost.com',
						'URL B' => 'http://localhost.org',
						'URL C' => 'http://localhost.org',
						'Host networks' => '111.111.11',
						'Host subnet mask' => '255.255.255.0',
						'Host router' => '111.111.1.1',
						'OOB IP address' => '100.100.100.10',
						'OOB subnet mask' => '255.255.255.0',
						'OOB router' => '100.100.100.10',
						'Date HW purchased' => '2023-01-01',
						'Date HW installed' => '2023-12-18',
						'Date HW maintenance expires' => '2025-12-18',
						'Date HW decommissioned' => '2024-12-18',
						'Site address A' => 'Riga',
						'Site address B' => 'Riga'
					]
				]
			],
			// #3.
			[
				[
					'Header' => 'Check link to proxy configuration form',
					'Host' => 'Host with items with tags',
					'Sections' => ['monitored-by'],
					'Monitored by' => [
						'Proxy',
						'Proxy for host card widget'
					]
				]
			],
			// #4.
			[
				[
					'Header' => 'Check link to proxy group configuration form',
					'Host' => 'Host for maintenance icon in HostCard widget',
					'Sections' => ['monitored-by'],
					'Monitored by' => [
						'Proxy Group',
						'Proxy group for host card widget'
					]
				]
			],
			// #5.
			[
				[
					'Header' => 'Disabled host',
					'Host' => 'Disabled host',
					'Sections' => ['monitored-by'],
					'Monitored by' => ['Zabbix server'],
					'Disabled' => true,
					'Inventory' => [
						'Location latitude' => '10',
						'Location longitude' => '10'
					]
				]
			]
		];
	}

	/**
	 * Check different data display on Host Card widget.
	 *
	 * @dataProvider getDisplayData
	 */
	public function testDashboardHostCardWidget_Display($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for HostCard widget display check'])->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard::find()->one()->getWidget($data['Header']);

		if (array_key_exists('Disabled', $data)) {
			$status = $widget->query('xpath', './/div[@class="red"]')->one();
			$this->assertTrue($status->isVisible());
			$this->assertEquals('rgba(227, 55, 52, 1)', $status->getCSSValue('color'));
			$this->assertEquals(trim($status->getText()), '(Disabled)');
		}
		else {
			$this->assertEquals($data['Host'], $widget->query('xpath', './/div[@class="host-name"]')->one()->getText());
		}

		if (array_key_exists('Availability', $data)) {
			$availabilities = $widget->query('xpath', './/div[@class="section section-availability"]//span')->all();
			foreach ($availabilities as $available) {
				$this->assertTrue(in_array($available->getText(), $data['Availability']));
			}
		}

		// TODO: Finish when ZBX-25709 will be done.
		// Need to check icon type, link to proxy/proxy group configuration form, no link for Zabbix server.
		// if (array_key_exists('Monitored by', $data)) {
		//
		// }

		if (array_key_exists('Tags', $data)) {
			$tags = $widget->query('xpath', './/div[@class="section section-tags"]'.
					'//div[@class="tags"]//span[@class="tag"]')->all();
			foreach ($tags as $tag) {
				$this->assertTrue(in_array($tag->getText(), $data['Tags']));
			}
		}

		if (array_key_exists('Monitoring', $data)) {
			foreach ($data['Monitoring'] as $entity => $value) {
				$this->assertEquals($value, $widget->query('xpath', './/div[@class="monitoring-item" '.
						'and .//a[@class="monitoring-item-name" '.
						'and normalize-space(text())='.CXPathHelper::escapeQuotes($entity).']]//span[@class="entity-count"]')
						->one()->getText()
				);
			}
		}

		if (array_key_exists('Templates', $data)) {
			$template_elements = $widget->query('xpath', './/div[@class="section section-templates"]'.
					'//span[@class="template-name"]')->all();

			foreach ($template_elements as $template_element) {
				$this->assertTrue(in_array($template_element->getText(), $data['Templates']));
			}
		}

		if (array_key_exists('Description', $data)) {
			$this->assertEquals($data['Description'], $widget->query('xpath', './/div[@class="section section-description"]')
					->one()->getText()
			);
		}

		if (array_key_exists('Host groups', $data)) {
			$host_groups_elements = $widget->query('xpath',
					'.//div[@class="section section-host-groups"]//span[@class="host-group-name"]')->all();
			foreach ($host_groups_elements as $host_groups_element) {
				$this->assertTrue(in_array($host_groups_element->getText(), $data['Host groups']));
			}
		}

		if (array_key_exists('Inventory', $data)){
			foreach ($data['Inventory'] as $name => $value) {
				$this->assertTrue( $widget->query('xpath', './/div[@class="inventory-field-name" and @title="'.$name.'"]')
						->one()->isVisible()
				);
				$this->assertTrue( $widget->query('xpath', './/div[@class="inventory-field-value" and @title="'.$value.'"]')
						->one()->isVisible()
				);
			}
		}
	}

	public static function getLinkData() {
		return [
			// #0.
			[
				[
					'Header' => 'Problems',
					'Title' => 'Problems'
				]
			],
			// #1.
			[
				[
					'Class'  => 'section-name',
					'Link' => 'Inventory',
					'Header' => 'Host inventory',
					'Title' => 'Host inventory'
				]
			],
			// #2.
			[
				[
					'Class'  => 'monitoring-item',
					'Link'   => 'Dashboards',
					'Header' => 'Host dashboards',
					'Title'  => 'Dashboards'
				]
			],
			// #3.
			[
				[
					'Class'  => 'monitoring-item',
					'Link'   => 'Latest data',
					'Header' => 'Latest data',
					'Title'  => 'Latest data'
				]
			],
			// #4.
			[
				[
					'Class'  => 'monitoring-item',
					'Link'   => 'Graphs',
					'Header' => 'Graphs',
					'Title'  => 'Custom graphs'
				]
			],
			// #5.
			[
				[
					'Class'  => 'monitoring-item',
					'Link'   => 'Web',
					'Header' => 'Web monitoring',
					'Title'  => 'Web monitoring'
				]
			]
		];
	}

	/**
	 * Check correct links in Host Card widget.
	 *
	 * @dataProvider getLinkData
	 */
	public function testDashboardHostCardWidget_CheckLinks($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for HostCard widget display check'])->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard::find()->one()->getWidget('Display host card with 3 column layout');

		if ($data['Header'] === 'Problems') {
			$widget->query('xpath', '//div[@class="sections-header"]//a[@class="problem-icon-link"]')->one()->click();
		}
		else {
			$section = ($data['Link'] == 'Inventory') ? 'inventory' : 'monitoring';
			$class = ($data['Link'] == 'Inventory') ? 'section-name' : 'monitoring-item';
			$widget->query('xpath', './/div[@class="section section-'.$section.'"]//div[@class='.
					CXPathHelper::escapeQuotes($class).']/a[text()='.
					CXPathHelper::escapeQuotes($data['Link']).']')->one()->click();
		}

		$this->page->waitUntilReady();
		$this->page->assertHeader($data['Header']);
		$this->page->assertTitle($data['Title']);
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

	public function getWidgetName() {
		return [
			[
				[
					'Name' => 'Display host card with 2 column layout'
				]
			],
			[
				[
					'Name' => 'Display host card with 3 column layout'
				]
			],
			[
				[
					'Name' => 'Display host card with 1 column layout (Default)'
				]
			]
		];
	}

	/**
	 * Check different compositions for Host Card widget.
	 *
	 * @dataProvider getWidgetName
	 */
	public function testDashboardHostCardWidget_Screenshots($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for HostCard widget display check'])->waitUntilReady();
		$this->assertScreenshot(CDashboardElement::find()->one()->getWidget($data['Name']), 'hostcard_'.$data['Name']);
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
			: $dashboard->getWidget('Host card')->edit();

		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Host card')]);
		$form->fill($data['fields']);

		if (array_key_exists('Show', $data)) {
			$this->fillShowOptions($data['Show'], $form);

			if (array_key_exists('Inventory', $data)) {
				$form->getField('Inventory fields')->fill($data['Inventory']);
			}
		}

		if (array_key_exists('Screenshot', $data) && $action === 'create') {
			$this->assertScreenshot($form->query('class:table-forms-separator')->waitUntilPresent()->one(),
					'Full list of show options'.$data['fields']['Host']
			);
		}

		$form->submit();
	}

	/**
	 * Fill Show options.
	 *
	 * @param array             $data         data provider
	 * @param CFormElement      $form         place, where you need to select Show options
	 */
	protected function fillShowOptions($data, $form) {
		$show_form = $form->getFieldContainer('Show');
		$show_form->query('button:Remove')->all()->click();

		foreach($data as $fieldid => $option) {
			$show_form->query('button:Add')->one()->waitUntilClickable()->click();
			$show_form->query('id', $fieldid)->asDropdown()->one()->fill($option);
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
			// Trim leading and trailing spaces from expected results if necessary.
			if (array_key_exists('trim', $data)) {
				$data['fields']['Name'] = trim($data['fields']['Name']);
			}

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
			$this->checkShowOptions($data, $form);
			COverlayDialogElement::find()->one()->close();
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		}
	}

	/**
	 * Check selected Show options.
	 *
	 * @param array             $data         data provider
	 * @param CFormElement      $form         form, where you need to check selected Show options
	 */
	protected function checkShowOptions($data, $form) {
		$select_buttons = $form->getFieldContainer('Show')->query('xpath', './/z-select/button')->all();
		$selected_options = [];
		foreach ($select_buttons as $button) {
			$selected_options[] = $button->getText();
		}

		if (array_key_exists('Show', $data)) {
			$this->assertEquals(array_values($data['Show']), $selected_options);
		}
		else {
			$this->assertEquals($selected_options, ['Monitoring', 'Availability', 'Monitored by']);
		}
	}
}
