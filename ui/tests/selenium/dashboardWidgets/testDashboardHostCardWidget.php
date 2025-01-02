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
				'description' => 'Long Description Long Description Long Description Long Description Long Description '
						. 'Long Description Long Description Long Description Long Description Long Description '
						. 'Long Description Long Description Long Description Long Description Long Description ',
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
								'width' => 72,
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
					],
					'Screenshot' => true
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
					'Inventory' => ['Name', 'OS', 'Hardware (Full details)', 'Contact']
				]
			],
			// #15.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Host with full inventory list',
						'Name' => 'test<s><\x3cscript>alert(\'XSS\')</script><s>',
					]
				]
			],
			// #16.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Host with full inventory list',
						'Name' => '"; DROP TABLE users; --"'
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

	public static function getUpdateData() {
		return [
			// #0.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Host is not selected 321',
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
						'Name' => 'Changed widget name and host',
						'Host' => 'Display'
					]
				]
			],
			// 2.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Display',
						'Name' => 'Changed name and suppressed checkbox',
						'Show suppressed problems' => false,
					]
				]
			],
			// 3.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => '😅😅😅Name of Host card widget 😅😅😅',
						'Show header' => true,
					],
					'Show' => [
						'sections_0' => 'Host groups'
					]
				]
			],
			// 4.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Disabled header and changed refresh interval',
						'Show header' => false,
						'Refresh interval'=> '10 minutes'
					],
					'Show' => [
						'sections_0' => 'Host groups'
					]
				]
			],
			// 5.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Host with full inventory list',
						'Name' => '"; DROP TABLE users; --"'
					]
				]
			],
			// 6.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Host with full inventory list',
						'Name' => 'test<s><\x3cscript>alert(\'XSS\')</script><s>',
					]
				]
			],
			// 7.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Name' => 'Added few show sections',
					],
					'Show' => [
						'sections_0' => 'Inventory',
						'sections_1' => 'Description',
						'sections_2' => 'Templates'
					],
				]
			],
			// 8.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Host with full inventory list',
						'Name' => 'Updated inventory list',
					],
					'Show' => [
						'sections_0' => 'Host groups',
						'sections_1' => 'Description',
						'sections_2' => 'Monitoring',
						'sections_3' => 'Inventory',
					],
					'Inventory' => ['Name', 'OS', 'Hardware (Full details)', 'Contact']
				]
			],
			// 9.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Host with full inventory list',
						'Name' => 'Added show sections',
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
				]
			],
		];
	}

	/**
	 * Update Host Card widget.
	 *
	 * @backup widget
	 * @dataProvider getUpdateData
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
					'Sections' => ['availability', 'monitored-by', 'templates'],
					'Severity' => [
						'average' => 1,
						'warning' => 5
					],
					'Host' => 'ЗАББИКС Сервер',
					'Availability' => ['ZBX'],
					'Monitored by' => ['Zabbix server'],
					'Templates' => ['Linux by Zabbix agent', 'Zabbix server health']
				]
			],
			// #1.
			[
				[
					'Header' => 'Display host card with 2 column layout',
					'Sections' => ['availability', 'inventory', 'monitored-by', 'monitoring', 'templates',
						'description', 'host-groups'
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
					'Templates' => ['Inheritance test template', 'Zabbix server health'],
					'Tags' => ['class: software', 'target: server', 'target: zabbix'],
					'Description' => 'Long Description Long Description Long Description Long Description Long '
						.'Description Long Description Long Description Long Description Long Description Long '
						.'Description Long Description Long Description Long Description Long Description Long '
						.'Description',
					'Host groups' => ['Zabbix servers']
				]
			],
			// #2.
			[
				[
					'Header' => 'Display host card with 3 column layout',
					'Sections' => ['monitored-by', 'monitoring', 'templates', 'description', 'host-groups'],
					'Host' => 'Host with full inventory list',
					'Availability' => ['ZBX'],
					'Monitored by' => ['Zabbix server'],
					'Monitoring' => [
						'Dashboards' => 1,
						'Latest data' => 103,
						'Graphs' => 4,
						'Web' => 4
					],
					'Templates' => ['Inheritance test template', 'Zabbix server health'],
					'Tags' => ['class: software', 'target: server', 'target: zabbix'],
					'Description' => 'Long Description Long Description Long Description Long Description Long '
						.'Description Long Description Long Description Long Description Long Description Long '
						.'Description Long Description Long Description Long Description Long Description Long '
						.'Description',
					'Host groups' => ['Zabbix servers']
				]
			],
			// #3.
			[
				[
					'Header' => 'Check link to proxy configuration form',
					'Host' => 'Host with items with tags',
					'Sections' => ['monitored-by'],
					'Monitored by' => ['Proxy', 'Proxy for host card widget']
				]
			],
			// #4.
			[
				[
					'Header' => 'Check link to proxy group configuration form',
					'Host' => 'Host for maintenance icon in HostCard widget',
					'Sections' => ['monitored-by'],
					'Monitored by' => ['Proxy Group', 'Proxy group for host card widget']
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

		$this->assertEquals($data['Host'], $widget->query('xpath:.//div[@class="host-name"]')
				->one()->getText());

		if (array_key_exists('Availability', $data)) {
			$availabilities = $widget->query('xpath:.//div[@class="section section-availability"]//span')->all();
			foreach ($availabilities as $available) {
				$this->AssertTrue(in_array($available->getText(), $data['Availability']));
			}
		}
		if (array_key_exists('Monitored by', $data)) {
			// TODO: Finish when ZBX-25709 will be done.
		}
		if (array_key_exists('Tags', $data)) {
			$tag_elements = $widget->query('xpath:.//div[@class="section section-tags"]'
					. '//div[@class="tags"]//span[@class="tag"]');
			$tags = $tag_elements->all();
			foreach ($tags as $tag) {
				$this->AssertTrue(in_array($tag->getText(), $data['Tags']));
			}
		}
		if (array_key_exists('Monitoring', $data)) {
			foreach ($data['Monitoring'] as $entity => $value) {
				$this->assertEquals($value, $widget->query('xpath:.//div[@class="monitoring-item" '
						. 'and .//a[@class="monitoring-item-name" '
						. 'and normalize-space(text())="'.$entity.'"]]//span[@class="entity-count"]')->one()->getText()
					);
			}
		}
		if (array_key_exists('Templates', $data)) {
			$templates = $widget->query('xpath:.//div[@class="section section-templates"]//span[@class="template-name"]');
			$templateElements = $templates->all();
			foreach ($templateElements as $templateElement) {
				$template = $templateElement->getText();
				$this->AssertTrue(in_array($template, $data['Templates']));
			}
		}
		if (array_key_exists('Description', $data)) {
			$this->assertEquals($data['Description'], $widget->query('xpath:.//div[@class="section section-description"]')
					->one()->getText()
			);
		}
		if (array_key_exists('Host groups', $data)) {
			$host_groups = $widget->query('xpath:.//div[@class="section section-host-groups"]//span[@class="host-group-name"]');
			$host_groups_elements = $host_groups->all();
			foreach ($host_groups_elements as $host_groups_element) {
				$host_group = $host_groups_element->getText();
				$this->AssertTrue(in_array($host_group, $data['Host groups']));
			}
		}
	}

	/**
	 * Check correct links in Host Card widget.
	 */
	public function testDashboardHostCardWidget_DisplayLinks() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for HostCard widget display check'])->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();
		$widget = $dashboard::find()->one()->getWidget('Display host card with 3 column layout');

		// Problem link.
		$widget->query('xpath://div[@class="sections-header"]//a[@class="problem-icon-link"]')->one()->click();
		$this->page->waitUntilReady();
		$this->page->assertHeader('Problems');
		$this->page->assertTitle('Problems');

		// Inventory link.
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for HostCard widget display check'])->waitUntilReady();
		$widget->query('xpath:.//div[@class="section section-inventory"]'
				. '//div[@class="section-name"]'
				. '/a[text()="Inventory"]')->one()->click();
		$this->page->waitUntilReady();
		$this->page->assertHeader('Host inventory');
		$this->page->assertTitle('Host inventory');

		// Dashboard link.
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for HostCard widget display check'])->waitUntilReady();
		$widget->query('xpath:.//div[@class="section section-monitoring"]'
				. '//div[@class="monitoring-item"]'
				. '/a[text()="Dashboards"]')->one()->click();
		$this->page->waitUntilReady();
		$this->page->assertHeader('Host dashboards');
		$this->page->assertTitle('Dashboards');

		// Latest data link.
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for HostCard widget display check'])->waitUntilReady();
		$widget->query('xpath:.//div[@class="section section-monitoring"]'
				. '//div[@class="monitoring-item"]'
				. '/a[text()="Latest data"]')->one()->click();
		$this->page->waitUntilReady();
		$this->page->assertHeader('Latest data');
		$this->page->assertTitle('Latest data');

		// Graphs link.
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for HostCard widget display check'])->waitUntilReady();
		$widget->query('xpath:.//div[@class="section section-monitoring"]'
				. '//div[@class="monitoring-item"]'
				. '/a[text()="Graphs"]')->one()->click();
		$this->page->waitUntilReady();
		$this->page->assertHeader('Graphs');
		$this->page->assertTitle('Custom graphs');

		// Web link.
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboardid['Dashboard for HostCard widget display check'])->waitUntilReady();
		$widget->query('xpath:.//div[@class="section section-monitoring"]'
				. '//div[@class="monitoring-item"]'
				. '/a[text()="Web"]')->one()->click();
		$this->page->waitUntilReady();
		$this->page->assertHeader('Web monitoring');
		$this->page->assertTitle('Web monitoring');
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
		$this->assertScreenshot(CDashboardElement::find()->one()->getWidget($data['Name']),
				'hostcard_' .$data['Name']
			);
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

		if (array_key_exists('Screenshot', $data)) {
			$this->assertScreenshot($form->query('class:table-forms-separator')->waitUntilPresent()->one(),
					'Full list of show options' .$data['Host']
			);
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
}
