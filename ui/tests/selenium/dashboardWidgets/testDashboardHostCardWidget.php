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


require_once __DIR__.'/../common/testWidgets.php';

/**
 * @backup dashboard, globalmacro, event_suppress
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
	*
	* @var array
	*/
	protected static $dashboardid;

	/**
	* Dashboard hash before update.
	*
	* @var string
	*/
	protected static $old_hash;

	/**
	* Widget counter.
	*
	* @var integer
	*/
	protected static $old_widget_count;

	/**
	* Id of host 'Fully filled host card widget'.
	*
	* @var integer
	*/
	protected static $hostid;

	public static function prepareHostCardWidgetData() {
		$templateids = [];
		$template_names = ['Apache by Zabbix agent', 'Docker by Zabbix agent 2', 'Linux by Zabbix agent',
				'PostgreSQL by Zabbix agent', 'Zabbix server health', 'Ceph by Zabbix agent 2'
		];

		foreach ($template_names as $template_name) {
			$result = CDBHelper::getRow('SELECT hostid, name FROM hosts WHERE status='.HOST_STATUS_TEMPLATE.' AND name='.
					zbx_dbstr($template_name));
			$templateids[$template_name] = $result['hostid'];
		}

		// Get default host groups.
		$host_groups = [];
		$group_names = ['Applications', 'Databases', 'Discovered hosts', 'Hypervisors', 'Linux servers',
				'Virtual machines', 'Zabbix servers'];

		foreach ($group_names as $group_name) {
			$result = CDBHelper::getRow('SELECT groupid, name FROM hstgrp WHERE name='.zbx_dbstr($group_name));
			$host_groups[$group_name] = $result['groupid'];
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
				'name' => 'Proxy group',
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
					['groupid' => $host_groups['Applications']],
					['groupid' => $host_groups['Databases']],
					['groupid' => $host_groups['Discovered hosts']],
					['groupid' => $host_groups['Hypervisors']],
					['groupid' => $host_groups['Linux servers']],
					['groupid' => $host_groups['Virtual machines']],
					['groupid' => $host_groups['Zabbix servers']]
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
					['templateid' => $templateids['Apache by Zabbix agent']],
					['templateid' => $templateids['Docker by Zabbix agent 2']],
					['templateid' => $templateids['Linux by Zabbix agent']],
					['templateid' => $templateids['PostgreSQL by Zabbix agent']],
					['templateid' => $templateids['Zabbix server health']],
					['templateid' => $templateids['Ceph by Zabbix agent 2']]
				],
				'items' => [
					[
						'name' => 'Item tag 1',
						'key_' => 'item_key_1',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Item tag 2',
						'key_' => 'item_key_2',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Item tag 3',
						'key_' => 'item_key_3',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Item tag 4',
						'key_' => 'item_key_4',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Item tag 5',
						'key_' => 'item_key_5',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
					],
					[
						'name' => 'Item tag 6',
						'key_' => 'item_key_6',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_UINT64
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
					['groupid' => $host_groups['Linux servers']],
					['groupid' => $host_groups['Virtual machines']]
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
					['templateid' => $templateids['Linux by Zabbix agent']],
					['templateid' => $templateids['Zabbix server health']]
				],
				'status' => HOST_STATUS_NOT_MONITORED,
				'inventory_mode' => HOST_INVENTORY_MANUAL,
				'monitored_by' => ZBX_MONITORED_BY_PROXY,
				'proxyid' => $proxies['Proxy for host card widget'],
				'inventory' => [
					'tag' => 'Host card tag',
					'type' => ''
				]
			],
			[
				'host' => 'Empty filled host',
				'groups' => [
					['groupid' => $host_groups['Zabbix servers']]
				],
				'monitored_by' => ZBX_MONITORED_BY_PROXY_GROUP,
				'proxy_groupid' => $proxie_group['Proxy group']
			],
			[
				'host' => 'XSS in visible host name field',
				'name' => '<img src=\"x\" onerror=\"alert("ERROR");\"/>',
				'groups' => [
					['groupid' => $host_groups['Zabbix servers']]
				]
			],
			[
				'host' => 'SQL injection in visible host name field',
				'name' => '105\'; --DROP TABLE Users',
				'groups' => [
					['groupid' => $host_groups['Zabbix servers']]
				]
			]
		]);

		// Create trigger based on item.
		CDataHelper::call('trigger.create', [
			[
				'description' => 'Not classidied trigger',
				'expression' => 'last(/Fully filled host card widget/item_key_1)<>0',
				'priority' => TRIGGER_SEVERITY_NOT_CLASSIFIED
			],
			[
				'description' => 'Information trigger',
				'expression' => 'last(/Fully filled host card widget/item_key_2)<>0',
				'priority' => TRIGGER_SEVERITY_INFORMATION
			],
			[
				'description' => 'Warning trigger',
				'expression' => 'last(/Fully filled host card widget/item_key_3)<>0',
				'priority' => TRIGGER_SEVERITY_WARNING
			],
			[
				'description' => 'Average trigger',
				'expression' => 'last(/Fully filled host card widget/item_key_4)<>0',
				'priority' => TRIGGER_SEVERITY_AVERAGE
			],
			[
				'description' => 'High trigger',
				'expression' => 'last(/Fully filled host card widget/item_key_5)<>0',
				'priority' => TRIGGER_SEVERITY_HIGH
			],
			[
				'description' => 'Disaster trigger',
				'expression' => 'last(/Fully filled host card widget/item_key_6)<>0',
				'priority' => TRIGGER_SEVERITY_DISASTER,
				'type' => 1 // Generate multiple events.
			]
		]);

		self::$hostid = $response['hostids']['Fully filled host card widget'];
		$itemids = $response['itemids'];
		foreach ([100, 200, 300, 400, 500] as $i => $value) {
			CDataHelper::addItemData($itemids['Fully filled host card widget:item_key_'.($i + 1)], $value);
		}

		$trigger_names = ['Not classidied trigger', 'Information trigger', 'Warning trigger', 'Average trigger',
			'High trigger', 'Disaster trigger', 'Disaster trigger'];
		CDBHelper::setTriggerProblem($trigger_names, TRIGGER_VALUE_TRUE, ['clock' => time() - 10000]);

		$eventid = CDBHelper::getValue('SELECT eventid FROM problem WHERE name='.zbx_dbstr('Disaster trigger'));
		CDataHelper::call('event.acknowledge', [
			'eventids' => $eventid,
			'action' => ZBX_PROBLEM_UPDATE_SUPPRESS,
			'suppress_until' => time() + 31536000
		]);

		$suppress_event = CDBHelper::getValue('SELECT COUNT(*) FROM event_suppress') + 1;
		DBexecute('INSERT INTO event_suppress (event_suppressid, eventid, maintenanceid, suppress_until)
				VALUES ('.zbx_dbstr($suppress_event).', '.zbx_dbstr($eventid).', NULL, 0)'
		);

		CDataHelper::call('httptest.create', [
			[
				'name' => 'Web scenario',
				'hostid' => self::$hostid,
				'steps' => [
					[
						'name' => 'Test name',
						'url' => 'http://example.com',
						'status_codes' => '200',
						'no' => '1'
					]
				]
			]
		]);

		// Create Maintenance and host in maintenance.
		$maintenances = CDataHelper::call('maintenance.create', [
			[
				'name' => 'Maintenance for Host Card widget',
				'active_since' => time() - 1000,
				'active_till' => time() + 31536000,
				'hosts' => [['hostid' => self::$hostid]],
				'timeperiods' => [[]],
				'description' => 'Maintenance for checking Icon and maintenance status in Host Card widget',
				'maintenance_type' => MAINTENANCE_TYPE_NORMAL
			]
		]);
		DBexecute('UPDATE hosts SET maintenanceid='.zbx_dbstr($maintenances['maintenanceids'][0]).
				', maintenance_status='.HOST_MAINTENANCE_STATUS_ON.', maintenance_type='.MAINTENANCE_TYPE_NORMAL.
				', maintenance_from='.zbx_dbstr(time()-1000).' WHERE hostid='.zbx_dbstr(self::$hostid)
		);

		$zabbix_server = CDBHelper::getValue('SELECT hostid FROM hosts WHERE name='.zbx_dbstr('ЗАББИКС Сервер'));

		CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for creating HostCard widgets',
				'pages' => [[]]
			],
			[
				'name' => 'Dashboard for HostCard widget update',
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
										'value' => $zabbix_server
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
										'value' => $zabbix_server
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
				'name' => 'Dashboard for deleting HostCard widget',
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
										'value' => $zabbix_server
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for HostCard widget display check',
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'hostcard',
								'name' => 'Fully filled host card widget',
								'x' => 0,
								'y' => 0,
								'width' => 19,
								'height' => 9,
								'fields' => [
									[
										'type' => 3,
										'name' => 'hostid.0',
										'value' => self::$hostid
									],
									[
										'type' => 0,
										'name' => 'show_suppressed',
										'value' => 1
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
								'x' => 19,
								'y' => 0,
								'width' => 18,
								'height' => 9,
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
								'name' => 'Empty host card widget',
								'x' => 37,
								'y' => 0,
								'width' => 17,
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
							],
							[
								'type' => 'hostcard',
								'name' => 'Default host card widget',
								'x' => 54,
								'y' => 0,
								'width' => 18,
								'height' => 4,
								'fields' => [
									[
										'type' => 3,
										'name' => 'hostid.0',
										'value' => $zabbix_server
									],
									[
										'type' => 0,
										'name' => 'rf_rate',
										'value' => 10
									],
									[
										'type' => 0,
										'name' => 'show_suppressed',
										'value' => 1
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
							],
							[
								'type' => 'hostcard',
								'name' => 'Do not show suppressed problems + incomplete inventory list',
								'x' => 54,
								'y' => 4,
								'width' => 18,
								'height' => 4,
								'fields' => [
									[
										'type' => 3,
										'name' => 'hostid.0',
										'value' => self::$hostid
									],
									[
										'type' => 0,
										'name' => 'show_suppressed',
										'value' => 0
									],
									[
										'type' => 0,
										'name' => 'sections.0',
										'value' => 6
									],
									[
										'type' => 0,
										'name' => 'inventory.0',
										'value' => 10
									],
									[
										'type' => 0,
										'name' => 'inventory.1',
										'value' => 25
									],
									[
										'type' => 0,
										'name' => 'inventory.2',
										'value' => 26
									],
									[
										'type' => 0,
										'name' => 'inventory.3',
										'value' => 1
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
		$this->assertEquals(['Host'], $form->getRequiredLabels());

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
			'Show suppressed problems' => false
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
		$show_form = $form->getFieldContainer('Show')->asMultifieldTable(['mapping' => ['' => 'section']]);
		$show_form->checkValue([['section' => 'Monitoring'], ['section' => 'Availability'], ['section' => 'Monitored by']]);

		// Clear all default options
		$show_form->query('button:Remove')->all()->click();

		$show_options = ['Host groups', 'Description', 'Monitoring', 'Availability', 'Monitored by', 'Templates',
				'Inventory', 'Tags'];
		$disabled_result = [];
		foreach ($show_options as $i => $option) {
			$show_form->query('button:Add')->one()->click();

			// Check that added correct option by default.
			$select = $show_form->query('id', 'sections_'.$i)->one()->asDropdown();
			$this->assertEquals($option, $select->getText());

			// Check that added options are disabled in dropdown menu.
			$disabled = $select->getOptions()->filter(CElementFilter::DISABLED)->asText();
			$this->assertEquals($disabled_result, $disabled);
			$disabled_result[] = $option;
		}

		// Check that Add button became disabled.
		$this->assertFalse($show_form->query('button:Add')->one()->isEnabled());

		// If the Inventory option was selected, the Inventory field becomes visible.
		$show_form->query('button:Remove')->all()->click();
		$show_form->query('button:Add')->one()->click();

		$inventory_field = $form->getField('Inventory fields');
		foreach ($show_options as $option) {
			$show_form->query('id:sections_0')->one()->asDropdown()->select($option);

			if ($option === 'Inventory') {
				$this->assertTrue($inventory_field->isVisible(true));
				$inventory_field->query('button:Select')->waitUntilCLickable()->one()->click();
				$inventory_dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
				$this->assertEquals('Inventory', $inventory_dialog->getTitle());
				$inventory_dialog->close(true);
			}
			else {
				$this->assertTrue($inventory_field->isVisible(false));
			}
		}
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
						'Host' => 'Fully filled host card widget with long name to be truncated should see tree dots in host name widget',
						'Name' => '  Trimmed name_3  '
					],
					'Show' => [
						['action' => USER_ACTION_REMOVE, 'index' => 0],
						['action' => USER_ACTION_REMOVE, 'index' => 0],
						['action' => USER_ACTION_REMOVE, 'index' => 0]
					],
					'Show header' => false,
					'Show suppressed problems' => false,
					'trim' => true
				]
			],
			// #2.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Fully filled host card widget with long name to be truncated should see tree dots in host name widget',
						'Name' => 'кирилица, ñ ç ö ø, 🙂🙂🙂🙂, みけめ, "],*,a[x=": "],*,a[x="/\|',
						'Show header' => true,
						'Show suppressed problems' => true,
						'Refresh interval' => 'No refresh'
					],
					'trim' => true
				]
			],
			// #3.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => '<img src=\"x\" onerror=\"alert("ERROR");\"/>',
						'Name' => '<img src=\"x\" onerror=\"alert("ERROR");\"/>',
						'Refresh interval' => '10 seconds'
					],
					'Show' => [
						['action' => USER_ACTION_UPDATE, 'index' => 0, 'section' => 'Description'],
						['action' => USER_ACTION_REMOVE, 'index' => 1],
						['action' => USER_ACTION_REMOVE, 'index' => 1]
					],
					'trim' => true
				]
			],
			// #4.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => '105\'; --DROP TABLE Users',
						'Name' => '105\'; --DROP TABLE Users'
					],
					'Show' => [
						['action' => USER_ACTION_REMOVE, 'index' => 0],
						['action' => USER_ACTION_REMOVE, 'index' => 0],
						['action' => USER_ACTION_REMOVE, 'index' => 0]
					]
				]
			],
			// #5.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Fully filled host card widget with long name to be truncated should see tree dots in host name widget',
						'Name' => 'Simple name for Host Card widget',
						'Refresh interval' => '30 seconds'
					],
					'Show' => [
						['action' => USER_ACTION_UPDATE, 'index' => 0, 'section' => 'Tags'],
						['action' => USER_ACTION_UPDATE, 'index' => 1, 'section' => 'Inventory'],
						['action' => USER_ACTION_REMOVE, 'index' => 1, 'section' => 'Templates']
					]
				]
			],
			// #6.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Fully filled host card widget with long name to be truncated should see tree dots in host name widget',
						'Name' => 'Fully filled host card widget',
						'Refresh interval' => '1 minute'
					],
					'Show' => [
						['section' => 'Host groups'],
						['section' => 'Description'],
						['section' => 'Templates'],
						['section' => 'Inventory'],
						['section' => 'Tags']
					],
					'Screenshot' => true,
					'Inventory' => ['Tag', 'Type', 'Location latitude', 'Location longitude']
				]
			],
			// #7.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'Fully filled host card widget with long name to be truncated should see tree dots in host name widget',
						'Name' => 'Fully filled host card widget 2',
						'Refresh interval' => '2 minutes'
					],
					'Show' => [
						['section' => 'Host groups'],
						['section' => 'Description'],
						['section' => 'Templates'],
						['section' => 'Inventory'],
						['action' => USER_ACTION_UPDATE, 'index' => 0, 'section' => 'Tags'],
						['action' => USER_ACTION_UPDATE, 'index' => 6, 'section' => 'Monitoring'],
						['action' => USER_ACTION_REMOVE, 'index' => 1],
						['section' => 'Availability']
					]
				]
			],
			// #8.
			[
				[
					'expected' => TEST_GOOD,
					'fields' => [
						'Host' => 'ЗАББИКС Сервер',
						'Name' => 'ЗАББИКС Сервер',
						'Refresh interval' => '10 minutes'
					],
					'Show' => [
						['action' => USER_ACTION_UPDATE, 'index' => 0, 'section' => 'Templates'],
						['section' => 'Monitoring'],
						['action' => USER_ACTION_UPDATE, 'index' => 1, 'section' => 'Host groups'],
						['action' => USER_ACTION_REMOVE, 'index' => 2],
						['section' => 'Monitored by'],
						['action' => USER_ACTION_UPDATE, 'index' => 2, 'section' => 'Description'],
						['section' => 'Inventory'],
						['section' => 'Availability'],
						['action' => USER_ACTION_UPDATE, 'index' => 4, 'section' => 'Monitoring']
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
					'Host' => 'Fully filled host card widget with long name to be truncated should see tree dots in host name widget',
					'Maintenance' => [
						'Name' => 'Maintenance for Host Card widget [Maintenance with data collection]',
						'Description' => 'Maintenance for checking Icon and maintenance status in Host Card widget'
					],
					'Availability' => ['ZBX', 'SNMP', 'IPMI', 'JMX'],
					'Monitored by' => [
						'Server' => 'Zabbix server'
					],
					'Monitoring' => [
						'Dashboards' => 8,
						'Latest data' => 291,
						'Graphs' => 29,
						'Web' => 1
					],
					'Templates' => ['Apache by Zabbix agent', 'Ceph by Zabbix agent 2', 'Docker by Zabbix agent 2',
							'Linux by Zabbix agent', 'PostgreSQL by Zabbix agent', 'Zabbix server health'
					],
					'Tags' => ['class: database', 'class: os', 'class: software', 'subclass: containers',
							'subclass: deploy', 'subclass: development', 'subclass: logging', 'subclass: monitoring',
							'subclass: sql', 'subclass: virtualization', 'subclass: webserver', 'target: apache',
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
					],
					'Severity' => [
						'Not classified' => 1,
						'Information' => 1,
						'Warning' => 1,
						'Average' => 1,
						'High' => 1,
						'Disaster' => 2
					],
					'Context menu' => [
						'VIEW' => [
							'Dashboards' => 'zabbix.php?action=host.dashboard.view&hostid={hostid}',
							'Problems' => 'zabbix.php?action=problem.view&hostids%5B%5D={hostid}&filter_set=1',
							'Latest data' => 'zabbix.php?action=latest.view&hostids%5B%5D={hostid}&filter_set=1',
							'Graphs' => 'zabbix.php?action=charts.view&filter_hostids%5B%5D={hostid}&filter_set=1',
							'Web' => 'zabbix.php?action=web.view&filter_hostids%5B%5D={hostid}&filter_set=1',
							'Inventory' => 'hostinventories.php?hostid={hostid}'
						],
						'CONFIGURATION' => [
							'Host' => 'zabbix.php?action=popup&popup=host.edit&hostid={hostid}',
							'Items' => 'zabbix.php?action=item.list&filter_set=1&filter_hostids%5B%5D={hostid}&context=host',
							'Triggers' => 'zabbix.php?action=trigger.list&filter_set=1&filter_hostids%5B%5D={hostid}&context=host',
							'Graphs' => 'zabbix.php?action=graph.list&filter_set=1&filter_hostids%5B%5D={hostid}&context=host',
							'Discovery' => 'host_discovery.php?filter_set=1&filter_hostids%5B%5D={hostid}&context=host',
							'Web' => 'httpconf.php?filter_set=1&filter_hostids%5B%5D={hostid}&context=host'
						],
						'SCRIPTS' => [
							'Detect operating system' => 'menu-popup-item',
							'Ping' => 'menu-popup-item',
							'Traceroute' => 'menu-popup-item'
						]
					]
				]
			],
			// #1.
			[
				[
					'Header' => 'Host card',
					'Host' => 'Partially filled host - no truncated',
					'Disabled' => true,
					'Availability' => ['ZBX'],
					'Monitored by' => [
						'Proxy' => 'Proxy for host card widget'
					],
					'Monitoring' => [
						'Dashboards' => 4,
						'Latest data' => 0,
						'Graphs' => 8,
						'Web' => 0
					],
					'Templates' => ['Linux by Zabbix agent', 'Zabbix server health'],
					'Tags' => ['class: os', 'class: software', 'subclass: logging', 'subclass: monitoring',
							'target: linux', 'target: server', 'target: zabbix'
					],
					'Description' => 'Short description',
					'Host groups' => ['Linux servers', 'Virtual machines'],
					'Inventory' => [
						'Tag' => 'Host card tag'
					]
				]
			],
			// #2.
			[
				[
					'Header' => 'Empty host card widget',
					'Host' => 'Empty filled host',
					'Availability' => [],
					'Monitored by' => [
						'Proxy group' => 'Proxy group'
					],
					'Monitoring' => [
						'Dashboards' => 0,
						'Latest data' => 0,
						'Graphs' => 0,
						'Web' => 0
					],
					'Templates' => [],
					'Tags' => [],
					'Description' => '',
					'Host groups' => ['Zabbix servers'],
					'Inventory' => [],
					'Context menu' => [
						'VIEW' => [
							'Dashboards' => 'menu-popup-item disabled',
							'Problems' => 'zabbix.php?action=problem.view&hostids%5B%5D={hostid}&filter_set=1',
							'Latest data' => 'zabbix.php?action=latest.view&hostids%5B%5D={hostid}&filter_set=1',
							'Graphs' => 'menu-popup-item disabled',
							'Web' => 'menu-popup-item disabled',
							'Inventory' => 'hostinventories.php?hostid={hostid}'
						],
						'CONFIGURATION' => [
							'Host' => 'zabbix.php?action=popup&popup=host.edit&hostid={hostid}',
							'Items' => 'zabbix.php?action=item.list&filter_set=1&filter_hostids%5B%5D={hostid}&context=host',
							'Triggers' => 'zabbix.php?action=trigger.list&filter_set=1&filter_hostids%5B%5D={hostid}&context=host',
							'Graphs' => 'zabbix.php?action=graph.list&filter_set=1&filter_hostids%5B%5D={hostid}&context=host',
							'Discovery' => 'host_discovery.php?filter_set=1&filter_hostids%5B%5D={hostid}&context=host',
							'Web' => 'httpconf.php?filter_set=1&filter_hostids%5B%5D={hostid}&context=host'
						],
						'SCRIPTS' => [
							'Detect operating system' => 'menu-popup-item',
							'Ping' => 'menu-popup-item',
							'Traceroute' => 'menu-popup-item'
						]
					]
				]
			],
			// #3.
			[
				[
					'Header' => 'Default host card widget',
					'Host' => 'ЗАББИКС Сервер',
					'Availability' => ['ZBX'],
					'Monitored by' => [
						'Server' => 'Zabbix server'
					],
					'Monitoring' => [
						'Dashboards' => 4,
						'Latest data' => 125,
						'Graphs' => 8,
						'Web' => 0
					]
				]
			],
			// #4.
			[
				[
					'Header' => 'Do not show suppressed problems + incomplete inventory list',
					'Host' => 'Fully filled host card widget with long name to be truncated should see tree dots in host name widget',
					'Inventory' => [
						'Type' => '',
						'Tag' => 'Critical',
						'Location latitude' => '37.7749',
						'Location longitude' => '-122.4194'
					],
					'Severity' => [
						'Not classified' => 1,
						'Information' => 1,
						'Warning' => 1,
						'Average' => 1,
						'High' => 1,
						'Disaster' => 1
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

		$host = CTestArrayHelper::get($data, 'Disabled') ? $data['Host']."\n".'Disabled' : $data['Host'];
		$host_selector = $widget->query('class:host-name')->one();
		$this->assertEquals($host, $host_selector->getText());
		if (array_key_exists('Disabled', $data)) {
			$status = $widget->query('class:color-negative')->one();
			$this->assertTrue($status->isVisible());
			$this->assertEquals(trim($status->getText()), 'Disabled');
		}

		if (array_key_exists('Context menu', $data)) {
			$hostid = CDBHelper::getValue('SELECT hostid FROM hosts WHERE name='.zbx_dbstr($data['Host']));
			$widget->query('link', $data['Host'])->one()->waitUntilClickable()->click();
			$this->checkContextMenuLinks($data['Context menu'], $hostid);
		}

		if (array_key_exists('Maintenance', $data)) {
			$icon = $host_selector->query('class:zi-wrench-alt-small')->one();
			$this->assertTrue($icon->isVisible());
			$icon->waitUntilClickable()->click();
			$dialog_text =  $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->one()->getText();
			$this->assertEquals($data['Maintenance']['Name']."\n".$data['Maintenance']['Description'], $dialog_text);
			$this->query('xpath://div[@class="overlay-dialogue wordbreak"]/button[@title="Close"]')->one()->click();
		}

		if (array_key_exists('Severity', $data)) {
			$section = $widget->query('class:problem-icon-link')->waitUntilvisible();
			foreach($data['Severity'] as $severity => $value) {
				$this->assertEquals($value, $widget->query('xpath:.//span[@title='.
						CXPathHelper::escapeQuotes($severity).']')->one()->getText()
				);
			}
		}

		if (array_key_exists('Availability', $data)) {
			$availabilities = $widget->query('class:section-availability')->query('class:status-container')->one()
					->query('xpath:.//span')->all();
			$this->assertEquals($data['Availability'], $availabilities->asText());


			foreach ($availabilities as $availability) {
				$availability->click();
				$dialog = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->asOverlayDialog()
						->waitUntilPresent()->one();
				$this->assertTrue($dialog->isVisible());
				$dialog->close();
			}
		}

		if (array_key_exists('Monitored by', $data)) {
			$section = $widget->query('class:section-monitored-by');
			foreach ($data['Monitored by'] as $key => $value) {
				$this->assertEquals($value, $section->query('class:section-body')->one()->getText());

				$icons = [
					'Server' => 'zi-server',
					'Proxy' => 'zi-proxy',
					'Proxy group' => 'zi-proxy-group'
				];
				$this->assertTrue($section->query('class', $icons[$key])->one()->isVisible());

				if ($key === 'Proxy' || $key === 'Proxy group') {
					$widget->query('link', $value)->one()->click();
					$dialog = COverlayDialogElement::find()->one()->waitUntilReady();
					$this->assertEquals($key, $dialog->getTitle());
					$dialog->close();
				}
			}
		}

		if (array_key_exists('Tags', $data)) {
			$section = $widget->query('class:section-tags')->query('class:tags')->one();
			$tags = $section->query('class:tag')->all();
			$this->assertEquals($data['Tags'], $tags->asText());

			// Check all tags by clicking on the icon to show hidden tags that do not fit due to the widget width.
			if (!empty($data['Tags'])) {
				$section->query('tag:button')->one()->click();
				$hint = $this->query('xpath://div[@data-hintboxid]')->asOverlayDialog()->waitUntilPresent()->one();
				$this->assertEquals($data['Tags'], $hint->query('class:tag')->all()->asText());
				$hint->close();
			}

			foreach ($data['Tags'] as $i => $tag) {
				// Only the first 5 tags (0-4) are visible for these test cases due to the widget width.
				if ($i >= 5) {
					$this->assertTrue($tags->get($i)->isVisible(false));
					continue;
				}

				$tags->get($i)->click();
				$hint = $this->query('xpath://div[@data-hintboxid]')->asOverlayDialog()->waitUntilPresent()->one();
				$this->assertEquals($tag, $hint->getText());
				$hint->close();
			}
		}

		if (array_key_exists('Monitoring', $data)) {
			$monitoring = $widget->query('class:section-monitoring')->one();
			$get_monitoring = [];
			foreach ($monitoring->query('class:monitoring-item')->all() as $item) {
				$name = $item->query('class:monitoring-item-name')->one();
				$count = $item->query('class:entity-count')->one()->getText();
				$target = ($count === '0') ? 'span' : 'a';
				$this->assertEquals($target, $name->getTagName());
				$get_monitoring[$name->getText()] = $count;
			}
			$this->assertEquals($data['Monitoring'], $get_monitoring);
		}

		if (array_key_exists('Templates', $data)) {
			$template_elements = $widget->query('class:section-templates')->one()->query('class:template-name')->all();
			$this->assertEquals($data['Templates'], $template_elements->asText());
		}

		if (array_key_exists('Description', $data)) {
			$this->assertEquals($data['Description'], $widget->query('class:section-description')
					->one()->getText()
			);
		}

		if (array_key_exists('Host groups', $data)) {
			$host_groups_elements = $widget->query('class:section-host-groups')->query('class:host-group-name')->all();
			$this->assertEquals($data['Host groups'], $host_groups_elements->asText());
		}

		if (array_key_exists('Inventory', $data)) {
			$inventory = $widget->query('class:section-inventory')->query('class:section-body')->one();
			$get_inventory = [];
			foreach ($inventory->query('class:inventory-field-name')->all() as $inventory_field) {
				$inventory_value = $inventory_field->query('xpath:./following-sibling::div[1]')->one();
				$get_inventory[$inventory_field->getText()] = $inventory_value->getText();
			}
			$this->assertEquals($data['Inventory'], $get_inventory);
		}
	}

	/**
	 * Check context menu links.
	 *
	 * @param array $data	data provider with fields values
	 */
	protected function checkContextMenuLinks($data, $hostid) {
		$popup = CPopupMenuElement::find()->waitUntilVisible()->one();
		$this->assertTrue($popup->hasTitles(array_keys($data)));

		$menu_level1_items = [];
		foreach (array_values($data) as $menu_items) {
			foreach ($menu_items as $menu_level1 => $link) {
				$menu_level1_items[] = $menu_level1;

				if (is_array($link)) {
					foreach ($link as $menu_level2 => $attribute) {
						// Check 2-level menu links.
						$item_link = $popup->getItem($menu_level1)->query('xpath:./../ul//a')->one();

						if (str_contains($attribute, 'menu-popup-item')) {
							$this->assertEquals($attribute, $item_link->getAttribute('class'));
						}
						else {
							$this->assertEquals($menu_level2, $item_link->getText());
							$this->assertStringContainsString($attribute, $item_link->getAttribute('href'));
						}
					}
				}
				else {
					// Check 1-level menu links.
					if (str_contains($link, 'menu-popup-item')) {
						$this->assertEquals($link, $popup->getItem($menu_level1)->getAttribute('class'));
					}
					else {
						$link = str_replace('{hostid}', $hostid, $link);
						$this->assertTrue($popup->query('xpath:.//a[text()='.CXPathHelper::escapeQuotes($menu_level1).
								' and contains(@href, '.CXPathHelper::escapeQuotes($link).')]')->exists()
						);
					}
				}
			}
		}

		$this->assertTrue($popup->hasItems($menu_level1_items));
		$popup->close();
	}

	public static function getLinkData() {
		return [
			// #0.
			[
				[
					'inactive' => true,
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
		$dashboard = CDashboardElement::find()->one()->waitUntilReady();

		// Check for missing reference if there are no objects.
		if (array_key_exists('inactive', $data)) {
			$objects = ['Dashboards', 'Graphs', 'Latest data', 'Web scenarios'];
			$widget = $dashboard::find()->one()->getWidget('Empty host card widget');
			foreach ($objects as $disabled_link) {
				$this->assertTrue($widget->query('class:section-monitoring')
						->query('xpath:.//span[@class="monitoring-item-name" and @title="'.$disabled_link.'"]')->one()
						->isClickable()
				);
			}
		}

		// Check links with existing objects.
		$widget = $dashboard->getWidget('Fully filled host card widget');

		if ($data['header'] === 'Problems') {
			$widget->query('class:sections-header')->query('class:problem-icon-link')->one()->click()->waitUntilNotVisible();
		}
		else {
			$section = ($data['link'] == 'Inventory') ? 'section-inventory' : 'section-monitoring';
			$widget->query('class', $section)->query('link', $data['link'])->one()->click()->waitUntilNotVisible();
		}
		$this->page->waitUntilReady();
		$this->page->assertHeader($data['header']);

		// Replace {id} draft to the real host id.
		$data['url'] = str_replace('{hostid}', self::$hostid, $data['url']);
		$this->assertEquals(PHPUNIT_URL.$data['url'], $this->page->getCurrentUrl());
		$this->page->assertTitle($data['title']);

		// Unstable test on Jenkins, graphs page opens slow and loose session before next test.
		if (CTestArrayHelper::get($data, 'link') === 'Graphs') {
			$this->page->open('browserwarning.php');
		}
	}

	public static function getCancelData() {
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

		$data = [
			'Show' => [
				['action' => USER_ACTION_UPDATE, 'index' => 0, 'section' => 'Tags'],
				['action' => USER_ACTION_REMOVE, 'index' => 1],
				['action' => USER_ACTION_UPDATE, 'index' => 1, 'section' => 'Description'],
				['section' => 'Inventory']
			]
		];
		$this->getShowTable()->fill($data['Show']);

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

	public static function getWidgetName() {
		return [
			[
				[
					'Name' => 'Fully filled host card widget'
				]
			],
			[
				[
					'Name' => 'Host card'
				]
			],
			[
				[
					'Name' => 'Default host card widget'
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
			$this->getShowTable()->fill($data['Show']);

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
	 * Get 'Show' table element with mapping set.
	 *
	 * @return CMultifieldTable
	 */
	protected function getShowTable() {
		return $this->query('id:sections-table')->asMultifieldTable([
			'mapping' => [
				'' => [
					'name' => 'section',
					'selector' => 'xpath:./z-select',
					'class' => 'CDropdownElement'
				]
			]
		])->one();
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

			$dashboard->getWidget($header)->edit()->checkValue($data['fields']);
			$this->getShowTable()->checkValue($this->calculateShowResult(CTestArrayHelper::get($data, 'Show', [])));
			COverlayDialogElement::find()->one()->close();
			$dashboard->save();
			$this->assertMessage(TEST_GOOD, 'Dashboard updated');
		}
	}

	/**
	 * Convert default values into an indexed array.
	 *
	 * @param array             $rows         data provider
	 */
	protected function calculateShowResult($rows) {
		$result = [
			['section' => 'Monitoring'],
			['section' => 'Availability'],
			['section' => 'Monitored by']
		];

		foreach ($rows as $row) {
			if (array_key_exists('action', $row)) {
				if ($row['action'] === USER_ACTION_REMOVE) {
					// Remove element at index.
					array_splice($result, $row['index'], 1);
				} elseif ($row['action'] === USER_ACTION_UPDATE) {
					// Update existing element
					$result[$row['index']]['section'] = $row['section'];
				}
			} else {
				// If no action is specified, it means we are adding a new section.
				$result[] = ['section' => $row['section']];
			}
		}

		return array_values($result);
	}
}
