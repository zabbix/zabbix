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

	protected static $dashboardid;
	protected static $old_hash;
	protected static $old_widget_count;
	protected static $hostid;

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

		// Get templates.
		$templates = [];
		$template_names = ['Apache by Zabbix agent', 'Docker by Zabbix agent 2', 'Linux by Zabbix agent',
				'PostgreSQL by Zabbix agent', 'Inheritance test template', 'Zabbix server health',
				'Ceph by Zabbix agent 2'
		];

		foreach ($template_names as $template_name) {
			$result = CDBHelper::getRow('SELECT hostid, name FROM hosts WHERE status = 3 AND name='.zbx_dbstr($template_name));
			$templates['templateid'][$template_name] = $result['hostid'];
		}

		// Get default host groups.
		$host_groups = [];
		$group_names = ['Applications', 'Databases', 'Discovered hosts', 'Hypervisors', 'Linux servers',
				'Virtual machines', 'Zabbix servers'];

		foreach ($group_names as $group_name) {
			$result = CDBHelper::getRow('SELECT groupid, name FROM hstgrp WHERE name ='.zbx_dbstr($group_name));
				$host_groups['groupid'][$group_name] = $result['groupid'];
		}

		// Create Proxy.
		CDataHelper::call('proxy.create', [
			[
				'name' => 'Proxy for host card widget',
				'operating_mode' => PROXY_OPERATING_MODE_ACTIVE
			]
		]);
		$proxies =  CDataHelper::getIds('name');

		// Create Proxy groups.
		CDataHelper::call('proxygroup.create', [
			[
				'name' => 'Proxy group for host card widget',
				'failover_delay' => '10',
				'min_online' => '1'
			]
		]);

		$proxie_group = CDataHelper::getIds('name');
		$response = CDataHelper::createHosts([
			[
				'host' => 'Fully filled host card widget',
				'name' => 'Fully filled host card widget with long name to be truncated should see tree dots in host name widget',
				'description' => STRING_255,
				'groups' => [
					['groupid' => $host_groups['groupid']['Applications']],
					['groupid' => $host_groups['groupid']['Databases']],
					['groupid' => $host_groups['groupid']['Discovered hosts']],
					['groupid' => $host_groups['groupid']['Hypervisors']],
					['groupid' => $host_groups['groupid']['Linux servers']],
					['groupid' => $host_groups['groupid']['Virtual machines']],
					['groupid' => $host_groups['groupid']['Zabbix servers']]
				],
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.0.1',
						'dns' => '',
						'port' => '10050'
					],
					[
						'type' => INTERFACE_TYPE_SNMP,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.2.2.2',
						'dns' => '',
						'port' => 122,
						'details' => [
							'version' => 1,
							'bulk' => 0,
							'community' => '🙃zabbix🙃'
						]
					],
					[
						'type' => INTERFACE_TYPE_IPMI,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_DNS,
						'ip' => '',
						'dns' => 'selenium.test',
						'port' => 30053
					],
					[
						'type' => INTERFACE_TYPE_JMX,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.4.4.4',
						'dns' => '',
						'port' => 426
					]
				],
				'templates' => [
					['templateid' => $templates['templateid']['Apache by Zabbix agent']],
					['templateid' => $templates['templateid']['Docker by Zabbix agent 2']],
					['templateid' => $templates['templateid']['Linux by Zabbix agent']],
					['templateid' => $templates['templateid']['PostgreSQL by Zabbix agent']],
					['templateid' => $templates['templateid']['Zabbix server health']],
					['templateid' => $templates['templateid']['Inheritance test template']],
					['templateid' => $templates['templateid']['Ceph by Zabbix agent 2']]
				],
				'items' => [
					[
						'name' => 'Item tag 1',
						'key_' => 'item_key_1',
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
						'key_' => 'item_key_2',
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
						'key_' => 'item_key_3',
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
						'key_' => 'item_key_4',
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
						'key_' => 'item_key_5',
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
				'monitored_by' => ZBX_MONITORED_BY_SERVER,
				'status' => HOST_STATUS_MONITORED,
				'inventory_mode' => HOST_INVENTORY_MANUAL,
				'inventory' => [
					'hardware' => 'Hardware 7.4',
					'tag' => 'Critical',
					'type' => '',
					'location_lat' => '37.7749',
					'location_lon' => '-122.4194'
				]
			],
			[
				'host' => 'Partially filled host - no truncated',
				'description' => 'Short description',
				'groups' => [
					['groupid' => $host_groups['groupid']['Linux servers']],
					['groupid' => $host_groups['groupid']['Virtual machines']]
				],
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.0.1',
						'dns' => '',
						'port' => '10050'
					]
				],
				'templates' => [
					['templateid' => $templates['templateid']['Linux by Zabbix agent']],
					['templateid' => $templates['templateid']['Zabbix server health']]
				],
				'status' => HOST_STATUS_NOT_MONITORED,
				'inventory_mode' => HOST_INVENTORY_MANUAL,
				'monitored_by' => ZBX_MONITORED_BY_PROXY,
				'proxyid' => $proxies['Proxy for host card widget'],
				'inventory' => [
					'tag' => 'Host card tag'
				]
			],
			[
				'host' => 'Empty filled host',
				'groups' => [
					['groupid' => $host_groups['groupid']['Zabbix servers']]
				],
				'monitored_by' => ZBX_MONITORED_BY_PROXY_GROUP,
				'proxy_groupid' => $proxie_group['Proxy group for host card widget']
			]
		]);

		self::$hostid = $response['hostids']['Fully filled host card widget'];
		$itemids = $response['itemids'];
		var_dump($itemids);
		foreach ([100, 200, 300, 400, 500] as $i => $value) {
			CDataHelper::addItemData($itemids['Fully filled host card widget:item_key_'.($i + 1)], $value);
		}
		// Create Maintenance and host in maintenance.
		$maintenances = CDataHelper::call('maintenance.create', [
			[
				'name' => 'HostCard host maintenance',
				'active_since' => time() - 1000,
				'active_till' => time() + 31536000,
				'hosts' => [['hostid' => $response['hostids']['Fully filled host card widget']]],
				'timeperiods' => [[]],
				'description' => 'Maintenance for checking Icon and maintenance status in Host Card widget'
			]
		]);
		$maintenance_id = $maintenances['maintenanceids'][0];

		DBexecute('UPDATE hosts SET maintenanceid='.zbx_dbstr($maintenance_id).
				', maintenance_status=1, maintenance_type='.MAINTENANCE_TYPE_NORMAL.', maintenance_from='.zbx_dbstr(time()-1000).
				' WHERE hostid='.zbx_dbstr($response['hostids']['Fully filled host card widget'])
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
								'name' => 'Fully filled host card widget',
								'x' => 0,
								'y' => 0,
								'width' => 18,
								'height' => 8,
								'fields' => [
									[
										'type' => 3,
										'name' => 'hostid.0',
										'value' => $response['hostids']['Fully filled host card widget']
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
									],
									[
										'type' => 0,
										'name' => 'sections.3',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'sections.4',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'sections.5',
										'value' => 5
									],
									[
										'type' => 0,
										'name' => 'sections.6',
										'value' => 6
									],
									[
										'type' => 0,
										'name' => 'sections.7',
										'value' => 7
									]
								]
							],
							[
								'type' => 'hostcard',
								'x' => 18,
								'y' => 0,
								'width' => 18,
								'height' => 8,
								'fields' => [
									[
										'type' => 3,
										'name' => 'hostid.0',
										'value' => $response['hostids']['Partially filled host - no truncated']
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
									],
									[
										'type' => 0,
										'name' => 'sections.3',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'sections.4',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'sections.5',
										'value' => 5
									],
									[
										'type' => 0,
										'name' => 'sections.6',
										'value' => 6
									],
									[
										'type' => 0,
										'name' => 'sections.7',
										'value' => 7
									]
								]
							],
							[
								'type' => 'hostcard',
								'name' => 'Display host card with 3 column layout',
								'x' => 36,
								'y' => 0,
								'width' => 18,
								'height' => 8,
								'fields' => [
									[
										'type' => 3,
										'name' => 'hostid.0',
										'value' => $response['hostids']['Empty filled host']
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
									],
									[
										'type' => 0,
										'name' => 'sections.3',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'sections.4',
										'value' => 1
									],
									[
										'type' => 0,
										'name' => 'sections.5',
										'value' => 5
									],
									[
										'type' => 0,
										'name' => 'sections.6',
										'value' => 6
									],
									[
										'type' => 0,
										'name' => 'sections.7',
										'value' => 7
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
				$form->getLabels(CElementFilter::VISIBLE)->asText()
		);
		$this->assertTrue($form->isRequired('Host'));

		// Check fields "Refresh interval" values.
		$this->assertEquals(['Default (1 minute)',  'No refresh', '10 seconds', '30 seconds', '1 minute', '2 minutes', '10 minutes',  '15 minutes'],
				$form->getField('Refresh interval')->getOptions()->asText()
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
			$disabled = $select->getOptions()->filter(CElementFilter::DISABLED)->asText();
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
					'Header' => 'Fully filled host card widget',
					'Sections' => [
						'monitored-by',
						'monitoring',
						'templates',
						'description',
						'host-groups'
					],
					'Host' => 'Host with full inventory list',
					'Availability' => ['ZBX', 'SNMP', 'IPMI', 'JMX'],
					'Monitored by' => ['Zabbix server'],
					'Monitoring' => [
						'Dashboards' => 8,
						'Latest data' => 303,
						'Graphs' => 33,
						'Web' => 4
					],
					'Templates' => ['Apache by Zabbix agent', 'Ceph by Zabbix agent 2', 'Docker by Zabbix agent 2',
							'Inheritance test template', 'Linux by Zabbix agent', 'PostgreSQL by Zabbix agent',
							'Zabbix server health'
					],
					'Tags' => ['class: application', 'class: database', 'class: os', 'class: software', 'target: apache',
							'target: ceph', 'target: docker', 'target: linux', 'target: postgresql', 'target: server',
							'target: zabbix'
					],
					'Description' => STRING_255,
					'Host groups' => ['Applications', 'Databases', 'Discovered hosts', 'Hypervisors', 'Linux servers',
							'Virtual machines', 'Zabbix servers'
					],
					'Inventory' => [
						'Tag' => 'Critical',
						'Hardware' => 'Hardware 7.4',
						'Location latitude' => '37.7749',
						'Location longitude' => '-122.4194'
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
			$status = $widget->query('class', 'red')->one();
			$this->assertTrue($status->isVisible());
			$this->assertEquals('rgba(227, 55, 52, 1)', $status->getCSSValue('color'));
			$this->assertEquals(trim($status->getText()), '(Disabled)');
		}
		else {
			$this->assertEquals($data['Host'], $widget->query('xpath', './/div[@class="host-name"]')->one()->getText());
		}

		if (array_key_exists('Availability', $data)) {
			$availability = $widget->query('class:section-availability')->query('class:status-container')->all()->asText();
			$this->assertEquals($data['Availability'], $availability);
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
			$inventory = $widget->query('class:section-inventory')->query('class:section-body')->one();
			$get_inventory = [];
			foreach ($inventory->query('class:inventory-field-name')->all() as $inventory_field) {
				$inventory_value = $inventory_field->query('xpath:./following-sibling::div[1]')->one();
				$get_inventory[$inventory_field->getText()] = $inventory_value->getText();
			}
			$this->assertEquals($data['Inventory'], $get_inventory);
		}
	}

	public static function getLinkData() {
		return [
			// #0.
			[
				[
					'header' => 'Problems',
					'title' => 'Problems',
					'url' => 'zabbix.php?show=1&name=&acknowledgement_status=0&inventory%5B0%5D%5Bfield%5D=type'.
							'&inventory%5B0%5D%5Bvalue%5D=&evaltype=0&tags%5B0%5D%5Btag%5D=&tags%5B0%5D%5Boperator%5D=0'.
							'&tags%5B0%5D%5Bvalue%5D=&show_tags=3&tag_name_format=0&tag_priority=&show_opdata=0'.
							'&show_timeline=1&filter_name=&filter_show_counter=0&filter_custom_time=0&sort=clock'.
							'&sortorder=DESC&age_state=0&show_symptoms=0&show_suppressed=0&acknowledged_by_me=0'.
							'&compact_view=0&details=0&highlight_row=0'.
							'&action=problem.view&hostids%5B%5D={hostid}'
				]
			],
			// #1.
			[
				[
					'class'  => 'section-name',
					'link' => 'Inventory',
					'header' => 'Host inventory',
					'title' => 'Host inventory',
					'url' => 'hostinventories.php?hostid={hostid}'
				]
			],
			// #2.
			[
				[
					'class'  => 'monitoring-item',
					'link'   => 'Dashboards',
					'header' => 'Host dashboards',
					'title'  => 'Dashboards',
					'url' => 'zabbix.php?action=host.dashboard.view&hostid={hostid}'
				]
			],
			// #3.
			[
				[
					'class'  => 'monitoring-item',
					'link'   => 'Latest data',
					'header' => 'Latest data',
					'title'  => 'Latest data',
					'url' => 'zabbix.php?name=&evaltype=0&tags%5B0%5D%5Btag%5D='.
							'&tags%5B0%5D%5Boperator%5D=0&tags%5B0%5D%5Bvalue%5D=&show_tags=3'.
							'&tag_name_format=0&tag_priority=&state=-1&filter_name=&filter_show_counter=0'.
							'&filter_custom_time=0&sort=name&sortorder=ASC&show_details=0&action=latest.view'.
							'&hostids%5B%5D={hostid}'
				]
			],
			// #4.
			[
				[
					'class'  => 'monitoring-item',
					'link'   => 'Graphs',
					'header' => 'Graphs',
					'title'  => 'Custom graphs',
					'url' => 'zabbix.php?action=charts.view&filter_hostids%5B0%5D={hostid}&filter_show=1&filter_set=1'
				]
			],
			// #5.
			[
				[
					'class'  => 'monitoring-item',
					'link'   => 'Web',
					'header' => 'Web monitoring',
					'title'  => 'Web monitoring',
					'url' => 'zabbix.php?action=web.view&filter_hostids%5B0%5D={hostid}&filter_set=1'
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
		$widget = $dashboard::find()->one()->getWidget('Fully filled host card widget');

		if ($data['header'] === 'Problems') {
			$widget->query('class:sections-header')->query('class:problem-icon-link')->one()->click();
		}
		else {
			$section = ($data['link'] == 'Inventory') ? 'section-inventory' : 'section-monitoring';
			$widget->query('class', $section)->query('link', $data['link'])->one()->click();
		}
		$this->page->waitUntilReady();
		$this->page->assertHeader($data['header']);

		// Replace {id} draft to the real host id.
		$data['url'] = str_replace('{hostid}', self::$hostid, $data['url']);
		$this->assertEquals(PHPUNIT_URL.$data['url'], $this->page->getCurrentUrl());
		$this->page->assertTitle($data['title']);
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
