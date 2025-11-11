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
 * @backup dashboard
 *
 * @dataSource AllItemValueTypes
 *
 * @onBefore prepareItemCardWidgetData
 */
class testDashboardItemCardWidget extends testWidgets {

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
	 * Ids of created Dashboards for Item Card widget check.
	 *
	 * @var array
	 */
	protected static $dashboard_ids;

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
	 * List of created item IDs.
	 *
	 * @var array
	 */
	protected static $itemids;

	/**
	 * List of created trigger IDs.
	 *
	 * @var integer
	 */
	protected static $trigger_ids;

	/**
	 * List of created host IDs.
	 *
	 * @var integer
	 */
	protected static $host_ids;

	/**
	 * List of created template ID.
	 *
	 * @var integer
	 */
	protected static $templateid;

	/**
	 * Created LLD rule id.
	 *
	 * @var integer
	 */
	protected static $discovery_rule_id;

	/**
	 * List of dependent items IDs.
	 *
	 * @var integer
	 */
	protected static $depend_items;

	const HOST_NAME = 'Host for Item Card widget';

	public static function prepareItemCardWidgetData() {
		self::$templateid = CDataHelper::createTemplates([
			[
				'host' => 'Template for item card widget',
				'groups' => ['groupid' => 1], // Templates.
				'items' => [
					[
						'name' => 'Master item from template',
						'key_' => 'custom_item',
						'type' => ITEM_TYPE_IPMI,
						'ipmi_sensor' => 'test',
						'value_type' => ITEM_VALUE_TYPE_STR,
						'delay' => '50m'
					]
				]
			]
		])['templateids']['Template for item card widget'];

		self::$host_ids = CDataHelper::createHosts([
			[
				'host' => self::HOST_NAME,
				'name' => 'Visible host name for Item Card widget',
				'groups' => ['groupid' => 4], //Zabbix servers.
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_AGENT,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_DNS,
						'ip' => '127.0.0.1',
						'dns' => 'zabbixzabbixzabbix.com',
						'port' => 10050
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
				'templates' => [['templateid' => self::$templateid]],
				'items' => [
					[
						'name' => STRING_255,
						'key_' => 'long_long_long_item_name',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_TEXT,
						'description' => STRING_6000,
						'delay' => '13m'
					],
					[
						'name' => 'Item for item card widget',
						'key_' => 'master',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_UINT64,
						'units' => '%',
						'timeout' => '35s',
						'delay' => '100m',
						'history' => '17d',
						'trends' => '17d',
						'inventory_link' => 6,
						'description' => STRING_6000,
						'status' => ITEM_STATUS_ACTIVE,
						'tags' => [
							[
								'tag' => 'numeric',
								'value' => '10'
							],
							[
								'tag' => 'long_text',
								'value' => STRING_128
							],
							[
								'tag' => 'ITC',
								'value' => 'ItemCardTag'
							],
							[
								'tag' => 'target',
								'value' => 'zabbix'
							],
							[
								'tag' => 'target',
								'value' => 'linux'
							],
							[
								'tag' => 'target',
								'value' => 'postgresql'
							]
						]
					],
					[
						'name' => '<img src=\"x\" onerror=\"alert("ERROR");\"/>',
						'key_' => 'xxs',
						'type' => ITEM_TYPE_JMX,
						'value_type' => ITEM_VALUE_TYPE_TEXT,
						'jmx_endpoint' => 'service:jmx:rmi:///jndi/rmi://{HOST.CONN}:{HOST.PORT}/jmxrmi',
						'description' => '<img src=\"x\" onerror=\"alert("ERROR");\"/>',
						'delay' => '13m',
						'status' => ITEM_STATUS_DISABLED
					],
					[
						'name' => '105\'; --DROP TABLE Users',
						'key_' => 'sql_injection',
						'type' => ITEM_TYPE_ZABBIX,
						'value_type' => ITEM_VALUE_TYPE_TEXT,
						'description' => '105\'; --DROP TABLE Users',
						'delay' => '13m'
					],
					[
						'name' => 'Item with text datatype',
						'key_' => 'datatype_text',
						'type' => ITEM_TYPE_SNMP,
						'value_type' => ITEM_VALUE_TYPE_LOG,
						'snmp_oid' => 'walk[222]',
						'delay' => '15m',
						'history' => ITEM_STORAGE_OFF,
						'trends' => ITEM_STORAGE_OFF
					]
				],
				'monitored_by' => ZBX_MONITORED_BY_SERVER,
				'status' => HOST_STATUS_MONITORED,
				'inventory_mode' => HOST_INVENTORY_AUTOMATIC
			]
		]);
		self::$itemids = CDataHelper::getIds('name');

		// Create dependent item.
		$items = [
			self::HOST_NAME => [
				[
					'name' => 'Dependent item 1',
					'key_' => 'dependent_item_1',
					'master_itemid' => self::$itemids['Item for item card widget'],
					'type' => ITEM_TYPE_DEPENDENT,
					'value_type' => ITEM_VALUE_TYPE_FLOAT,
					'description' => 'simple description',
					'tags' => [
						[
							'tag' => 'tagFromItem',
							'value' => '🙃zabbix🙃'
						]
					]
				],
				[
					'name' => 'Dependent item 2',
					'key_' => 'dependent_item_2',
					'master_itemid' => self::$itemids['Item for item card widget'],
					'type' => ITEM_TYPE_DEPENDENT,
					'value_type' => ITEM_VALUE_TYPE_BINARY,
					'history' => '0d'
				]
			]
		];

		$interfaces = CDataHelper::getInterfaces([self::$host_ids['hostids'][self::HOST_NAME]]);

		// Create discovery rule.
		$lld_result = CDataHelper::call('discoveryrule.create', [
			'name' => 'LLD for discovered item',
			'key_' => 'discoveryLLDrule',
			'hostid' => self::$host_ids['hostids'][self::HOST_NAME],
			'type' => ITEM_TYPE_ZABBIX,
			'delay' => '500s',
			'lifetime_type' => ZBX_LLD_DELETE_NEVER,
			'enabled_lifetime_type' => ZBX_LLD_DISABLE_NEVER,
			'interfaceid' => $interfaces['default_interfaces'][self::HOST_NAME][1]
		]);

		self::$discovery_rule_id = $lld_result['itemids'][0];

		// Create item prototype.
		$item_prototype_result = CDataHelper::call('itemprototype.create', [
			[
				'hostid' => self::$host_ids['hostids'][self::HOST_NAME],
				'ruleid' => self::$discovery_rule_id,
				'name' => 'Item prototype {#KEY}',
				'key_' => 'trap[{#KEY}]',
				'type' => ITEM_TYPE_ZABBIX,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => '500s',
				'interfaceid' => $interfaces['default_interfaces'][self::HOST_NAME][1]
			]
		]);

		$item_prototype_id = $item_prototype_result['itemids'][0];

		$discovered_item = [
			'itemid' => 10090002,
			'itemdiscoveryid' => 10090001,
			'item_name' => 'Discovered Item KEY1',
			'key_' => 'trapchik',
			'hostid' => self::$host_ids['hostids'][self::HOST_NAME],
			'status' => ITEM_STATE_NORMAL,
			'item_prototypeid' => $item_prototype_id,
			'ts_delete' => 0,
			'disable_source' => ZBX_DISABLE_SOURCE_LLD,
			'ts_disable' => 0
		];

		// Emulate item discovery in DB.
		DBexecute('INSERT INTO items (itemid, type, hostid, name, description, key_, interfaceid, flags, query_fields,'.
				' params, posts, headers, status) VALUES ('.zbx_dbstr($discovered_item['itemid']).', 2, '.
				zbx_dbstr(self::$host_ids['hostids'][self::HOST_NAME]).', '.zbx_dbstr($discovered_item['item_name']).
				', \'\', '.zbx_dbstr($discovered_item['key_']).', NULL, 4, \'\', \'\', \'\', \'\', '.
				zbx_dbstr($discovered_item['status']).')'
		);
		DBexecute('INSERT INTO item_discovery (itemdiscoveryid, itemid, parent_itemid, ts_delete, disable_source,'.
				' ts_disable, status) VALUES ('.zbx_dbstr($discovered_item['itemdiscoveryid']).', '.
				zbx_dbstr($discovered_item['itemid']).', '.zbx_dbstr($discovered_item['item_prototypeid']).', '.
				zbx_dbstr($discovered_item['ts_delete']).', '.
				zbx_dbstr($discovered_item['disable_source']).', '.zbx_dbstr($discovered_item['ts_disable']).', 1);'
		);

		CDataHelper::createItems('item', $items, self::$host_ids['hostids']);
		self::$depend_items = CDataHelper::getIds('name');

		// Create trigger based on item.
		CDataHelper::call('trigger.create', [
			[
				'description' => 'Not classified trigger',
				'expression' => 'last(/Host for Item Card widget/master)>100',
				'priority' => TRIGGER_SEVERITY_NOT_CLASSIFIED
			],
			[
				'description' => 'Information trigger',
				'expression' => 'last(/Host for Item Card widget/master)>200',
				'priority' => TRIGGER_SEVERITY_INFORMATION
			],
			[
				'description' => 'Warning trigger',
				'expression' => 'last(/Host for Item Card widget/master)>300',
				'priority' => TRIGGER_SEVERITY_WARNING
			],
			[
				'description' => 'Average trigger',
				'expression' => 'last(/Host for Item Card widget/master)>400',
				'priority' => TRIGGER_SEVERITY_AVERAGE
			],
			[
				'description' => 'High trigger',
				'expression' => 'last(/Host for Item Card widget/master)>500',
				'priority' => TRIGGER_SEVERITY_HIGH
			],
			[
				'description' => 'Disaster trigger',
				'expression' => 'last(/Host for Item Card widget/master)>600',
				'priority' => TRIGGER_SEVERITY_DISASTER
			],
			[
				'description' => 'Trigger 1',
				'expression' => 'last(/Host for Item Card widget/datatype_text)>100',
				'priority' => TRIGGER_SEVERITY_NOT_CLASSIFIED
			],
			[
				'description' => 'Disabled trigger',
				'expression' => 'last(/Host for Item Card widget/dependent_item_1)<>0',
				'priority' => TRIGGER_SEVERITY_DISASTER,
				'status' => TRIGGER_STATUS_DISABLED
			]
		]);
		self::$trigger_ids = CDataHelper::getIds('description');

		// Add some metrics to 'Item for item card widget' item, to get Graph image and error notification.
		$item_data = [
			[
				'name' => 'Item for item card widget',
				'value' => 9000,
				'time' => time() - 8640000 // -100 days.
			],
			[
				'name' => 'Item with text datatype',
				'value' => 'QA team',
				'time' => time() - 10368000 // -120 days.
			]
		];

		foreach ($item_data as $params) {
			CDataHelper::addItemData(self::$itemids[$params['name']], $params['value'], $params['time']);
		}

		$trigger_names = ['Not classified trigger', 'Information trigger', 'Warning trigger', 'Average trigger',
				'High trigger', 'Disaster trigger', 'Disaster trigger', 'Trigger 1', 'Trigger 2'
		];
		CDBHelper::setTriggerProblem($trigger_names);

		// Add red error messages.
		DBexecute('UPDATE item_rtdata SET state = 1, error = '.zbx_dbstr('Value of type "string" is not suitable for '.
				'value type "Numeric (unsigned)". Value "hahah"').'WHERE itemid ='.
				zbx_dbstr(self::$itemids['Item for item card widget'])
		);
		DBexecute('UPDATE item_rtdata SET state = 1, error = '.zbx_dbstr('Unsupported item key.').
				'WHERE itemid ='.zbx_dbstr(self::$depend_items['Dependent item 1'])
		);

		$template_itemid = CDBHelper::getValue('SELECT itemid FROM items WHERE name='.
				zbx_dbstr('Master item from template').' AND templateid IS NOT NULL'
		);

		CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard for creating Item Card widgets',
				'private' => PUBLIC_SHARING,
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'geomap',
								'x' => 0,
								'y' => 0,
								'width' => 13,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'EKBHK'
									]
								]
							],
							[
								'type' => 'graph',
								'x' => 13,
								'y' => 0,
								'width' => 16,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH,
										'name' => 'graphid.0',
										'value' => 2232 // Linux: CPU utilization.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'XIBBD'
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for Item Card widget update',
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'geomap',
								'x' => 0,
								'y' => 0,
								'width' => 14,
								'height' => 5
							],
							[
								'type' => 'graph',
								'x' => 14,
								'y' => 0,
								'width' => 14,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_GRAPH,
										'name' => 'graphid.0',
										'value' => 2232 // Linux: CPU utilization.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'reference',
										'value' => 'XIBBD'
									]
								]
							],
							[
								'type' => 'itemcard',
								'name' => 'Item card',
								'x' => 28,
								'y' => 0,
								'width' => 19,
								'height' => 10,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid.0',
										'value' => self::$itemids['Item for item card widget']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.0',
										'value' => '2'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.1',
										'value' => '4'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.2',
										'value' => '6'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.3',
										'value' => '7'
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for canceling Item Card widget',
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'itemcard',
								'name' => 'CancelItemCardWidget',
								'x' => 0,
								'y' => 0,
								'width' => 19,
								'height' => 10,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.0',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.1',
										'value' => 4
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.2',
										'value' => 6
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.3',
										'value' => 7
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid.0',
										'value' => self::$itemids['Item for item card widget']
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for deleting Item Card widget',
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'itemcard',
								'name' => 'DeleteItemCardWidget',
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid.0',
										'value' => self::$itemids['Item for item card widget']
									]
								]
							]
						]
					]
				]
			],
			[
				'name' => 'Dashboard for Item Card widget display check',
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'itemcard',
								'name' => 'Master item from host',
								'x' => 0,
								'y' => 0,
								'width' => 18,
								'height' => 10,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid.0',
										'value' => self::$itemids['Item for item card widget']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.0',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.1',
										'value' => 4
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.2',
										'value' => 6
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.3',
										'value' => 7
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.4',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.5',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.6',
										'value' => 3
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.7',
										'value' => 5
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.8',
										'value' => 8
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.9',
										'value' => 9
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'sparkline.color',
										'value' => 'FFC107'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sparkline.fill',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'sparkline.time_period.from',
										'value' => 'now-1d'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'sparkline.time_period.to',
										'value' => 'now+1d'
									]
								]
							],
							[
								'type' => 'itemcard',
								'name' => 'Dependent Item from host',
								'x' => 18,
								'y' => 0,
								'width' => 18,
								'height' => 10,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid.0',
										'value' => self::$depend_items['Dependent item 1']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.0',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.1',
										'value' => 4
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.2',
										'value' => 6
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.3',
										'value' => 7
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.4',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.5',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.6',
										'value' => 3
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.7',
										'value' => 5
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.8',
										'value' => 8
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.9',
										'value' => 9
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'sparkline.color',
										'value' => '42A5F5'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sparkline.fill',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'sparkline.time_period.from',
										'value' => 'now-1d'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'sparkline.time_period.to',
										'value' => 'now+1d'
									]
								]
							],
							[
								'type' => 'itemcard',
								'x' => 36,
								'y' => 0,
								'width' => 18,
								'height' => 10,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid.0',
										'value' => $template_itemid
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.0',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.1',
										'value' => 4
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.2',
										'value' => 6
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.3',
										'value' => 7
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.4',
										'value' => 0
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.5',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.6',
										'value' => 3
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.7',
										'value' => 5
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.8',
										'value' => 8
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.9',
										'value' => 9
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'sparkline.color',
										'value' => '42A5F5'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sparkline.fill',
										'value' => 1
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'sparkline.time_period.from',
										'value' => 'now-1d'
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_STR,
										'name' => 'sparkline.time_period.to',
										'value' => 'now+1d'
									]
								]
							],
							[
								'type' => 'itemcard',
								'name' => 'Disabled Item',
								'x' => 54,
								'y' => 0,
								'width' => 18,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid.0',
										'value' => self::$itemids['<img src=\"x\" onerror=\"alert("ERROR");\"/>']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.0',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.1',
										'value' => 4
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.2',
										'value' => 6
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.3',
										'value' => 7
									]
								]
							],
							[
								'type' => 'itemcard',
								'name' => 'SNMP interface',
								'x' => 54,
								'y' => 5,
								'width' => 18,
								'height' => 6,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid.0',
										'value' => self::$itemids['Item with text datatype']
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.0',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.1',
										'value' => 4
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.2',
										'value' => 6
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.3',
										'value' => 7
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.4',
										'value' => 3
									]
								]
							],
							[
								'type' => 'itemcard',
								'name' => 'Link to LLD rule',
								'x' => 0,
								'y' => 10,
								'width' => 18,
								'height' => 5,
								'fields' => [
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_ITEM,
										'name' => 'itemid.0',
										'value' => 10090002 // Discovered Item KEY1.
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.0',
										'value' => 2
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.1',
										'value' => 4
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.2',
										'value' => 6
									],
									[
										'type' => ZBX_WIDGET_FIELD_TYPE_INT32,
										'name' => 'sections.3',
										'value' => 7
									]
								]
							]
						]
					]
				]
			]
		]);
		self::$dashboard_ids = CDataHelper::getIds('name');
	}

	public function testDashboardItemCardWidget_Layout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboard_ids['Dashboard for creating Item Card widgets']
		)->waitUntilReady();

		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$form = $dashboard->edit()->addWidget()->asForm();
		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Item card')]);

		// Check name field maxlength.
		$this->assertEquals(255, $form->getField('Name')->getAttribute('maxlength'));

		// Check placeholder for Name, Item and Override host fields.
		$this->assertEquals('default', $form->getField('Name')->getAttribute('placeholder'));

		foreach (['id:itemid_ms', 'id:override_hostid_ms'] as $field) {
			$this->assertEquals('type here to search', $form->query($field)->one()->getAttribute('placeholder'));
		}

		foreach (['Type', 'Show header', 'Name', 'Refresh interval', 'Item', 'Show', 'Override host'] as $label) {
			$this->assertTrue($form->getField($label)->isVisible(true));
		}

		$this->assertEquals(['Item'], $form->getRequiredLabels());

		// Check fields "Refresh interval" values.
		$this->assertEquals(['Default (1 minute)', 'No refresh', '10 seconds', '30 seconds', '1 minute', '2 minutes',
				'10 minutes', '15 minutes'], $form->getField('Refresh interval')->getOptions()->asText()
		);

		// Check default values.
		$default_values = [
			'Name' => '',
			'Refresh interval' => 'Default (1 minute)',
			'Item' => '',
			'Show header' => true,
			'Override host' => ''
		];

		$form->checkValue($default_values);

		// Check dropdowns and select buttons.
		foreach (['Item' => ['Item', 'Widget'], 'Override host' => ['Widget', 'Dashboard']] as $label => $menu_items) {
			$field = $form->getField($label);
			$menu_button = $field->query('xpath:.//button[contains(@class, "zi-chevron-down")]')->asPopupButton()->one();
			$this->assertEquals($menu_items, $menu_button->getMenu()->getItems()->asText());

			foreach ($menu_items as $item) {
				$menu_button->select($item);

				if ($item === 'Dashboard') {
					// Сheck value and hint message when "Dashboard" is selected.
					$form->checkValue([$label => 'Dashboard']);
					$this->assertTrue($field->query('xpath:.//span[@data-hintbox-contents="Dashboard is used as data source."]')
							->one()->isVisible()
					);
				}
				else {
					// Сheck overlay dialog title and close it.
					$dialog_title = ($item === 'Item') ? 'Items' : $item;
					$dialog = COverlayDialogElement::find(1)->waitUntilReady()->one();
					$this->assertEquals($dialog_title, $dialog->getTitle());
					$dialog->close(true);
				}
			}

			$selector_title = ($label === 'Item') ? 'Items' : 'Widget';
			$field->query('button:Select')->waitUntilCLickable()->one()->click();
			$selector_dialog = COverlayDialogElement::find(1)->waitUntilReady()->one();
			$this->assertEquals($selector_title, $selector_dialog->getTitle());
			$selector_dialog->close(true);
		}

		// Check default and available options in "Show" section.
		$rows = [1 => 'Interval and storage', 2 => 'Type of information', 3 => 'Host interface', 4 => 'Type'];
		$option_states = [
			'Description' => true,
			'Error text' => true,
			'Interval and storage' => false,
			'Latest data' => true,
			'Type of information' => false,
			'Triggers' => true,
			'Host interface' => false,
			'Type' => false,
			'Host inventory' => true,
			'Tags' => true
		];

		$table = $this->getShowTable();
		$show_rows = $table->getRows()->filter(CElementFilter::CLASSES_PRESENT, 'form_row', true);
		$i = 1;
		foreach ($show_rows as $row) {
			$this->assertTrue($row->getColumn(0)->query('class:drag-icon')->one()->isEnabled());

			// TODO: after ZBX-27000 is merged, change column index "2" to column name "Name".
			$dropdown = $row->getColumn(2)->query('tag:z-select')->one()->asDropdown();
			$options = $dropdown->getOptions();
			$this->assertEquals($rows[$i], $dropdown->getText());
			$this->assertEquals(array_keys($option_states), $options->asText());

			// If option is selected in the dropdown, it appears in the list as enabled.
			$reference_options = array_merge($option_states, [$rows[$i] => true]);

			// If option does not have a "disabled" attribute, then it is considered enabled.
			foreach ($options as $option) {
				$this->assertEquals($reference_options[$option->getText()], !$option->isAttributePresent('disabled'));
			}

			$this->assertTrue($row->query('button:Remove')->one()->isClickable());

			$i++;
		}

		// Check Sparkline section is not visible in case user did not select "Latest data" in "Show" field.
		$this->assertFalse($form->query('class', 'js-sparkline-row')->one()->isVisible());

		// Clear all default options.
		$table->query('button:Remove')->all()->click();

		// If the 'Latest data' option was selected, the Sparkline becomes visible.
		$table->query('button:Add')->one()->click();

		$sparkline = $form->getFieldContainer('Sparkline');
		foreach ($option_states as $option => $default_state) {
			$table->query('id:sections_0')->one()->asDropdown()->select($option);

			if ($option === 'Latest data') {
				$this->assertTrue($sparkline->isVisible(true));

				// Check sparkline default values.
				$sparkline_default_values = [
					'id:sparkline_width' => 1,
					'id:sparkline_fill' => 3,
					'id:sparkline_time_period_data_source' => 'Custom',
					'id:sparkline_time_period_from' => 'now-1h',
					'id:sparkline_time_period_to' => 'now',
					'id:sparkline_history' => 'Auto'
				];
				foreach ($sparkline_default_values as $field => $value) {
					$this->assertEquals($value, $form->getField($field)->getValue());
					$this->assertTrue($form->getField($field)->isVisible(true));
				}

				// Check default color code.
				$this->assertEquals('#42A5F5', $form->getField('id:lbl_sparkline_color')->getAttribute('title'));

				// Check radio button options.
				$radio_buttons = [
					'id:sparkline_time_period_data_source' => ['Dashboard', 'Widget', 'Custom'],
					'id:sparkline_history' => ['Auto', 'History', 'Trends']
				];
				foreach ($radio_buttons as $locator => $labels) {
					foreach ($labels as $option) {
						$form->getField($locator)->asSegmentedRadio()->select($option);

						if ($locator === 'id:sparkline_time_period_data_source') {
							switch ($option) {
								case 'Dashoboard':
									foreach (['sparkline_time_period_from', 'sparkline_time_period_to', 'sparkline_time_period_reference_ms'] as $field) {
										$this->assertFalse($form->query('id', $field)->one()->isVisible());
									}
									break;
								case 'Widget':
									foreach (['sparkline_time_period_from', 'sparkline_time_period_to'] as $field) {
										$this->assertFalse($form->query('id', $field)->one()->isVisible());
									}
									$this->assertTrue($form->query('id', 'sparkline_time_period_reference_ms')->one()->isVisible());
									$this->assertTrue($form->query('class', 'js-sparkline_time_period-reference')
											->query('button', 'Select')->one()->isClickable()
									);
									$this->assertTrue($form->query('xpath:.//label[@for="sparkline_time_period_reference_ms"]')
											->one()->hasClass('form-label-asterisk')
									);
									break;
								case 'Custom':
									foreach (['sparkline_time_period_from', 'sparkline_time_period_to'] as $field) {
										$this->assertTrue($form->query('id', $field)->one()->isVisible());
										$this->assertTrue($form->query('xpath:.//label[@for="'.$field.'"]')->one()
												->hasClass('form-label-asterisk')
										);

										// Check that user may open a calendar.
										$calendar = $form->query('xpath:.//button[@id="'.$field.'_calendar"]')->one();
										$calendar->waitUntilClickable()->click();
										$calendar_overlay = $this->query('xpath://div[@aria-label="Calendar"]');
										$this->assertTrue($calendar_overlay->exists());
										$calendar->click();
										$this->assertFalse($calendar_overlay->exists());
									}

									$this->assertFalse($form->query('class', 'js-sparkline_time_period-reference')
											->one()->isVisible(true)
									);
									break;
							}
						}
					}
					$this->assertEquals($labels, $form->getField($locator)->getLabels()->asText());
				}

				// Check that apply button disabled if field is empty.
				$form->fill([self::PATH_TO_COLOR_PICKER.'"sparkline[color]"]' => '']);
				$this->assertTrue(CColorPickerElement::isSubmitable(false));
				CColorPickerElement::close();
			}
			else {
				$this->assertTrue($sparkline->isVisible(false));
			}
		}
	}

	public static function getCreateData() {
		return [
			// #0. Check mandotary Item field error message.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item' => ''
					],
					'error_message' => [
						'Invalid parameter "Item": cannot be empty.'
					]
				]
			],
			// #1. Check mandotary sparkline time period fields errors messages.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Item' => STRING_255
					],
					'Show' => [
						['action' => USER_ACTION_UPDATE, 'index' => 0, 'section' => 'Latest data']
					],
					'Sparkline' => [
						'id:sparkline_time_period_from' => '',
						'id:sparkline_time_period_to' => ''
					],
					'error_message' => [
						'Invalid parameter "Sparkline: Time period/From": cannot be empty.',
						'Invalid parameter "Sparkline: Time period/To": cannot be empty.'
					]
				]
			],
			// #2. Checking the filter length for the time period.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Selected more than 731 day for graph filter.',
						'Item' => 'Item for item card widget'
					],
					'Show' => [
						['action' => USER_ACTION_UPDATE, 'index' => 0, 'section' => 'Latest data']
					],
					'Sparkline' => [
						'id:sparkline_time_period_from' => 'now-1000d',
						'id:sparkline_time_period_to' => 'now'
					],
					'error_message' => [
						'Maximum time period to display is 731 days.'
					]
				]
			],
			// #3. Checking the required field Widget.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'Empty widget value',
						'Item' => 'Item for item card widget'
					],
					'Show' => [
						['action' => USER_ACTION_UPDATE, 'index' => 0, 'section' => 'Latest data']
					],
					'Sparkline' => [
						'id:sparkline_time_period_data_source' => 'Widget'
					],
					'error_message' => [
						'Invalid parameter "Sparkline: Time period/Widget": cannot be empty.'
					]
				]
			],
			// #4. Check invalid values ​​for Sparkline width and fill fields.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Show header' => false,
						'Name' => 'Incorrect number for sparkline parameters',
						'Refresh interval' => 'No refresh',
						'Item' => 'Item for item card widget'
					],
					'Show' => [
						['action' => USER_ACTION_UPDATE, 'index' => 0, 'section' => 'Latest data']
					],
					'Sparkline' => [
						'id:sparkline_width' => 5000,
						'id:sparkline_fill' => -5
					],
					'error_message' => [
						'Invalid parameter "Sparkline: Width": value must be one of 0-10.',
						'Invalid parameter "Sparkline: Fill": value must be one of 0-10.'
					]
				]
			],
			// #5. Check invalid values ​​for Sparkline period FROM and TO fields.
			[
				[
					'expected' => TEST_BAD,
					'fields' => [
						'Name' => 'A time is expected',
						'Item' => 'Item for item card widget'
					],
					'Show' => [
						['action' => USER_ACTION_UPDATE, 'index' => 0, 'section' => 'Latest data']
					],
					'Sparkline' => [
						'id:sparkline_time_period_data_source' => 'Custom',
						'id:sparkline_time_period_from' => 'dsa',
						'id:sparkline_time_period_to' => '321'
					],
					'error_message' => [
						'Invalid parameter "Sparkline: Time period/From": a time is expected.',
						'Invalid parameter "Sparkline: Time period/To": a time is expected.'
					]
				]
			],
			// #6. Check the widget name truncation and all user actions in the "Show" section.
			[
				[
					'fields' => [
						'Show header' => true,
						'Name' => '  Trimmed name_3  ',
						'Refresh interval' => 'No refresh',
						'Item' => 'Item for item card widget'
					],
					// Select Latest data in the first element, then delete elements 3-5, then select all elements.
					'Show' => [
						['action' => USER_ACTION_UPDATE, 'index' => 0, 'section' => 'Latest data'],
						['action' => USER_ACTION_REMOVE, 'index' => 3],
						['action' => USER_ACTION_REMOVE, 'index' => 2],
						['action' => USER_ACTION_REMOVE, 'index' => 1],
						['section' => 'Interval and storage'],
						['section' => 'Error text'],
						['section' => 'Description'],
						['section' => 'Tags'],
						['section' => 'Triggers'],
						['section' => 'Host inventory'],
						['section' => 'Type'],
						['section' => 'Type of information'],
						['section' => 'Host interface']
					],
					'Screenshot' => true,
					'trim' => true
				]
			],
			// #7. Check different byte characters.
			[
				[
					'fields' => [
						'Show header' => true,
						'Name' => 'кирилица, ñ ç ö ø, 🙂🙂🙂🙂, みけめ, "],*,a[x=": "],*,a[x="/\|',
						'Refresh interval' => '10 seconds',
						'Item' => 'Item for item card widget'
					],
					'Show' => [
						['action' => USER_ACTION_REMOVE, 'index' => 3],
						['action' => USER_ACTION_REMOVE, 'index' => 2],
						['action' => USER_ACTION_REMOVE, 'index' => 1],
						['action' => USER_ACTION_REMOVE, 'index' => 0]
					]
				]
			],
			// #8. Check XSS attack.
			[
				[
					'fields' => [
						'Name' => '<img src=\"x\" onerror=\"alert("ERROR");\"/>',
						'Refresh interval' => '30 seconds',
						'Item' => '<img src=\"x\" onerror=\"alert("ERROR");\"/>'
					],
					'Show' => [
						['action' => USER_ACTION_UPDATE, 'index' => 0, 'section' => 'Description'],
						['action' => USER_ACTION_REMOVE, 'index' => 3],
						['action' => USER_ACTION_REMOVE, 'index' => 2],
						['action' => USER_ACTION_REMOVE, 'index' => 1]
					]
				]
			],
			// #9. Check SQL injection.
			[
				[
					'fields' => [
						'Name' => '105\'; --DROP TABLE Users',
						'Refresh interval' => '1 minute',
						'Item' => '105\'; --DROP TABLE Users'
					],
					'Show' => [
						['action' => USER_ACTION_UPDATE, 'index' => 0, 'section' => 'Description'],
						['action' => USER_ACTION_REMOVE, 'index' => 2],
						['action' => USER_ACTION_REMOVE, 'index' => 1],
						['action' => USER_ACTION_REMOVE, 'index' => 1]
					]
				]
			],
			// #10. Check that user is able to update and remove show option.
			[
				[
					'fields' => [
						'Name' => 'Update then remove one Show option',
						'Refresh interval' => '2 minutes',
						'Item' => 'Item for item card widget'
					],
					'Show' => [
						['action' => USER_ACTION_UPDATE, 'index' => 0, 'section' => 'Tags'],
						['action' => USER_ACTION_REMOVE, 'index' => 3],
						['action' => USER_ACTION_REMOVE, 'index' => 2],
						['action' => USER_ACTION_UPDATE, 'index' => 1, 'section' => 'Host inventory'],
						['action' => USER_ACTION_REMOVE, 'index' => 1]
					]
				]
			],
			// #11. Check that user is able to fill "Sparkine" section fields.
			[
				[
					'fields' => [
						'Name' => 'Dashboard source for override host field',
						'Refresh interval' => '10 minutes',
						'Item' => 'Item for item card widget',
						'Override host' => 'Dashboard'
					],
					'Show' => [
						['section' => 'Description'],
						['section' => 'Error text'],
						['section' => 'Latest data'],
						['section' => 'Triggers'],
						['section' => 'Host inventory'],
						['section' => 'Tags']
					],
					'Sparkline' => [
						'id:sparkline_width' => 5,
						'id:sparkline_fill' => 3,
						'id:sparkline_time_period_data_source' => 'Dashboard',
						'id:sparkline_history' => 'History',
						'color' => 'F48FB1'
					]
				]
			],
			// #12. Check that user is able to fill override host field and another widget in "Sparkline" section.
			[
				[
					'fields' => [
						'Name' => 'Other widget as the source for override host field',
						'Refresh interval' => '15 minutes',
						'Item' => 'Item for item card widget',
						'Override host' => 'Geomap'
					],
					'Show' => [
						['section' => 'Description'],
						['section' => 'Error text'],
						['section' => 'Latest data'],
						['section' => 'Triggers'],
						['section' => 'Host inventory'],
						['section' => 'Tags']
					],
					'Sparkline' => [
						'id:sparkline_width' => 5,
						'id:sparkline_fill' => 3,
						'id:sparkline_time_period_data_source' => 'Widget',
						'id:sparkline_history' => 'Trends',
						'color' => '9A34A1',
						'widget' => 'ЗАББИКС Сервер: Linux: CPU utilization'
					]
				]
			],
			// #13. Check that user is able to change "Show" section element order.
			[
				[
					'fields' => [
						'Name' => 'User changing Show options',
						'Item' => 'Item for item card widget'
					],
					'Show' => [
						['action' => USER_ACTION_UPDATE, 'index' => 0, 'section' => 'Description'],
						['section' => 'Latest data'],
						['action' => USER_ACTION_UPDATE, 'index' => 1, 'section' => 'Error text'],
						['action' => USER_ACTION_REMOVE, 'index' => 2],
						['section' => 'Triggers'],
						['action' => USER_ACTION_UPDATE, 'index' => 2, 'section' => 'Type of information'],
						['section' => 'Tags'],
						['section' => 'Host interface'],
						['action' => USER_ACTION_UPDATE, 'index' => 4, 'section' => 'Type']
					]
				]
			]
		];
	}

	/**
	 * Create Item Card widget.
	 *
	 * @dataProvider getCreateData
	 */
	public function testDashboardItemCardWidget_Create($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboard_ids['Dashboard for creating Item Card widgets']
		)->waitUntilReady();

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
	 * Item Card widget simple update without any field change.
	 */
	public function testDashboardItemCardWidget_SimpleUpdate() {
		// Hash before simple update.
		self::$old_hash = CDBHelper::getHash(self::SQL);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboard_ids['Dashboard for Item Card widget update']
		)->waitUntilReady();

		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$dashboard->edit()->getWidget('Item card')->edit()->submit();
		$dashboard->getWidget('Item card');
		$dashboard->save();
		$this->page->waitUntilReady();
		$this->assertMessage(TEST_GOOD, 'Dashboard updated');

		// Compare old hash and new one.
		$this->assertEquals(self::$old_hash, CDBHelper::getHash(self::SQL));
	}

	/**
	 * Update Item Card widget.
	 *
	 * @backup widget
	 *
	 * @dataProvider getCreateData
	 */
	public function testDashboardItemCardWidget_Update($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboard_ids['Dashboard for Item Card widget update']
		)->waitUntilReady();

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
	 * Delete Item Card widget.
	 */
	public function testDashboardItemCardWidget_Delete() {
		$widget_name = 'DeleteItemCardWidget';

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboard_ids['Dashboard for deleting Item Card widget']
		)->waitUntilReady();

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
					'Header' => 'Master item from host',
					'Item' => 'Item for item card widget',
					'Host' => 'Visible host name for Item Card widget',
					'Severity' => [
						'Not classified' => 1,
						'Information' => 1,
						'Warning' => 1,
						'Average' => 1,
						'High' => 1,
						'Disaster' => 2
					],
					'Interval and storage' => [
						'column' => '100m',
						'center-column' => '17d',
						'right-column' => '17d'
					],
					'Type of information' => 'Numeric (unsigned)',
					'Host interface' => 'zabbixzabbixzabbix.com:10050',
					'Type' => 'Zabbix agent',
					'Description' => STRING_6000,
					'Error text' => 'Value of type "string" is not suitable for value type "Numeric (unsigned)". Value "hahah"',
					'Latest data' => [
						'column' => '3M 10d',
						'center-column' => '9000 %',
						'right-column' => 'Graph'
					],
					'Check last metric time' => true,
					'Triggers' => [
						[
							'Severity' => 'Not classified',
							'Name' => 'Not classified trigger',
							'Expression' => 'last(/Host for Item Card widget/master)>100',
							'Status' => 'Enabled'
						],
						[
							'Severity' => 'Information',
							'Name' => 'Information trigger',
							'Expression' => 'last(/Host for Item Card widget/master)>200',
							'Status' => 'Enabled'
						],
						[
							'Severity' => 'Warning',
							'Name' => 'Warning trigger',
							'Expression' => 'last(/Host for Item Card widget/master)>300',
							'Status' => 'Enabled'
						],
						[
							'Severity' => 'Average',
							'Name' => 'Average trigger',
							'Expression' => 'last(/Host for Item Card widget/master)>400',
							'Status' => 'Enabled'
						],
						[
							'Severity' => 'High',
							'Name' => 'High trigger',
							'Expression' => 'last(/Host for Item Card widget/master)>500',
							'Status' => 'Enabled'
						],
						[
							'Severity' => 'Disaster',
							'Name' => 'Disaster trigger',
							'Expression' => 'last(/Host for Item Card widget/master)>600',
							'Status' => 'Enabled'
						]
					],
					'Host inventory' => 'OS (Full details)',
					'Tags' => ['ITC: ItemCardTag', 'long_text: '.STRING_128, 'numeric: 10', 'target: linux',
						'target: postgresql', 'target: zabbix'
					],
					'Context menu' => [
						'VIEW' => [
							'Latest data' => 'zabbix.php?action=latest.view&hostids%5B%5D={hostid}'.
									'&name=Item%20for%20item%20card%20widget&filter_set=1',
							'Graph' => 'history.php?action=showgraph&itemids%5B%5D={itemid}',
							'Values' => 'history.php?action=showvalues&itemids%5B%5D={itemid}',
							'500 latest values' => 'history.php?action=showlatest&itemids%5B%5D={itemid}'
						],
						'CONFIGURATION' => [
							'Item' => 'zabbix.php?action=popup&popup=item.edit&context=host&itemid={itemid}',
							'Host' => 'zabbix.php?action=popup&popup=host.edit&hostid={hostid}',
							'Triggers' => [
								'Not classified trigger' => 'zabbix.php?action=popup&popup=trigger.edit'.
										'&triggerid={triggerid}&hostid={hostid}&context=host',
								'Information trigger' => 'zabbix.php?action=popup&popup=trigger.edit'.
										'&triggerid={triggerid}&hostid={hostid}&context=host',
								'Warning trigger' => 'zabbix.php?action=popup&popup=trigger.edit'.
										'&triggerid={triggerid}&hostid={hostid}&context=host',
								'Average trigger' => 'zabbix.php?action=popup&popup=trigger.edit'.
										'&triggerid={triggerid}&hostid={hostid}&context=host',
								'High trigger' => 'zabbix.php?action=popup&popup=trigger.edit'.
										'&triggerid={triggerid}&hostid={hostid}&context=host',
								'Disaster trigger' => 'zabbix.php?action=popup&popup=trigger.edit'.
										'&triggerid={triggerid}&hostid={hostid}&context=host'
							],
							'Create trigger' => 'menu-popup-item',
							'Create dependent item' => 'menu-popup-item',
							'Create dependent discovery rule' => 'host_discovery.php?form=create&hostid={hostid}&type=18'.
									'&master_itemid={itemid}&backurl=zabbix.php%3Faction%3Dlatest.view%26context%3Dhost'.
									'&context=host'
						],
						'ACTIONS' => [
							'Execute now' => 'menu-popup-item'
						]
					]
				]
			],
			// #1.
			[
				[
					'Header' => 'Dependent Item from host',
					'Item' => 'Dependent item 1',
					'Host' => 'Visible host name for Item Card widget',
					'Depended entity' => 'Item for item card widget',
					'Interval and storage' => [
						'column' => '',
						'center-column' => '31d',
						'right-column' => '365d'
					],
					'Type of information' => 'Numeric (float)',
					'Host interface' => 'No data',
					'Type' => 'Dependent item',
					'Description' => 'simple description',
					'Error text' => 'Unsupported item key.',
					'Latest data' => [
						'column' => '',
						'center-column' => '',
						'right-column' => 'Graph'
					],
					'Triggers' => [
						[
							'Severity' => 'Disaster',
							'Name' => 'Disabled trigger',
							'Expression' => 'last(/Host for Item Card widget/dependent_item_1)<>0',
							'Status' => 'Disabled'
						]
					],
					'Host inventory' => '',
					'Tags' => ['tagFromItem: 🙃zabbix🙃'],
					'Context menu' => [
						'VIEW' => [
							'Latest data' => 'zabbix.php?action=latest.view&hostids%5B%5D={hostid}&name=Dependent%20'.
									'item%201&filter_set=1',
							'Graph' => 'history.php?action=showgraph&itemids%5B%5D={itemid}',
							'Values' => 'history.php?action=showvalues&itemids%5B%5D={itemid}',
							'500 latest values' => 'history.php?action=showlatest&itemids%5B%5D={itemid}'
						],
						'CONFIGURATION' => [
							'Item' => 'zabbix.php?action=popup&popup=item.edit&context=host&itemid={itemid}',
							'Host' => 'zabbix.php?action=popup&popup=host.edit&hostid={hostid}',
							'Triggers' => [
								'Disabled trigger' => 'zabbix.php?action=popup&popup=trigger.edit'.
										'&triggerid={triggerid}&hostid={hostid}&context=host'
							],
							'Create trigger' => 'menu-popup-item',
							'Create dependent item' => 'menu-popup-item',
							'Create dependent discovery rule' => 'host_discovery.php?form=create&hostid={hostid}&type=18'.
									'&master_itemid={itemid}&backurl=zabbix.php%3Faction%3Dlatest.view%26context%3Dhost'.
									'&context=host'
						],
						'ACTIONS' => [
							'Execute now' => 'menu-popup-item'
						]
					]
				]
			],
			// #2.
			[
				[
					'Header' => 'Item card',
					'Item' => 'Master item from template',
					'Host' => 'Visible host name for Item Card widget',
					'Depended entity' => 'Template for item card widget',
					'Interval and storage' => [
						'column' => '50m',
						'center-column' => '31d',
						'right-column' => ''
					],
					'Check last metric time' => true,
					'Type of information' => 'Character',
					'Host interface' => 'selenium.test:30053',
					'Tags' => [],
					'Type' => 'IPMI agent',
					'Latest data' => [
						'column' => '',
						'center-column' => '',
						'right-column' => 'History'
					],
					'Host inventory' => ''
				]
			],
			// #3.
			[
				[
					'Header' => 'Disabled Item',
					'Item' => '<img src=\"x\" onerror=\"alert("ERROR");\"/>',
					'Host' => 'Visible host name for Item Card widget',
					'Disabled' => true,
					'Interval and storage' => [
						'column' => '13m',
						'center-column' => '31d',
						'right-column' => ''
					],
					'Type of information' => 'Text',
					'Host interface' => '127.4.4.4:426',
					'Type' => 'JMX agent'
				]
			],
			// #4.
			[
				[
					'Header' => 'SNMP interface',
					'Item' => 'Item with text datatype',
					'Severity' => [
						'Not classified' => 1
					],
					'Host' => 'Visible host name for Item Card widget',
					'Interval and storage' => [
						'column' => '15m',
						'center-column' => '',
						'right-column' => ''
					],
					'Type of information' => 'Log',
					'Host interface' => '127.2.2.2:122',
					'Type' => 'SNMP agent',
					'Latest data' => [
						'column' => '4M',
						'center-column' => 'QA team',
						'right-column' => ''
					]
				]
			],
			// #5.
			[
				[
					'Header' => 'Link to LLD rule',
					'Host' => 'Visible host name for Item Card widget',
					'Warning' => 'The item is not discovered anymore and will not be disabled, will not be deleted.'
				]
			]
		];
	}

	/**
	 * Check different data display on Item Card widget.
	 *
	 * @dataProvider getDisplayData
	 */
	public function testDashboardItemCardWidget_Display($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboard_ids['Dashboard for Item Card widget display check']
		)->waitUntilReady();

		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$widget = $dashboard->getWidget($data['Header']);

		if (array_key_exists('Item', $data)) {
			$item = CTestArrayHelper::get($data, 'Disabled') ? $data['Item']."\n".'Disabled' : $data['Item'];
			$item_selector = $widget->query('class:item-name')->one();
			$this->assertEquals($item, $item_selector->getText());
		}

		if (array_key_exists('Context menu', $data)) {
			$widget->query('link', $data['Item'])->one()->waitUntilClickable()->click();
			$this->checkContextMenuLinks($data['Context menu'], self::$host_ids['hostids'][self::HOST_NAME],
					($data['Item'] === 'Dependent item 1')
						? self::$depend_items[$data['Item']]
						: self::$itemids[$data['Item']]
			);
		}

		if (array_key_exists('Error text', $data)) {
			$item_selector->query('class:zi-i-negative')->one()->click();
			$hint = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->asOverlayDialog()->waitUntilReady()->one();
			$this->assertEquals($data['Error text'], $hint->getText());
			$hint->close();
			$this->assertEquals($data['Error text'], $widget->query('class:section-error')->one()->getText());
		}

		if (array_key_exists('Warning', $data)) {
			$widget->query('class:item-name')->query('class:zi-i-warning')->one()->click();
			$hint = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->asOverlayDialog()->waitUntilReady()->one();
			$this->assertEquals($data['Warning'], $hint->getText());
			$hint->close();
		}

		if (array_key_exists('Disabled', $data)) {
			$status = $widget->query('class:color-negative')->one();
			$this->assertTrue($status->isVisible());
			$this->assertEquals(trim($status->getText()), 'Disabled');
		}

		if (array_key_exists('Severity', $data)) {
			foreach ($data['Severity'] as $severity => $value) {
				$this->assertEquals($value, $widget->query('xpath:.//span[@title='.
						CXPathHelper::escapeQuotes($severity).']')->one()->getText()
				);
			}
		}

		if (array_key_exists('Host', $data)) {
			$hostname = $widget->query('class:sections-header')->query('class:section-path')
					->query('class:path-element')->one();
			$this->assertTrue($hostname->isClickable());
			$this->assertEquals($data['Host'], $hostname->getText());
		}

		if (array_key_exists('Interval and storage', $data)) {
			foreach ($data['Interval and storage'] as $section => $value) {
				$this->assertEquals($value, $widget->query('class:section-interval-and-storage')->query('class', $section)
						->query('class:column-value')->one()->getText()
				);
			}
		}

		if (array_key_exists('Type of information', $data)) {
			$this->asssertSectionValue($widget, 'Type of information', $data['Type of information']);
		}

		if (array_key_exists('Host interface', $data)) {
			$this->asssertSectionValue($widget, 'Host interface', $data['Host interface']);
		}

		if (array_key_exists('Type', $data)) {
			$this->asssertSectionValue($widget, 'Type', $data['Type']);
		}

		if (array_key_exists('Description', $data)) {
			$this->assertEquals($data['Description'], $widget->query('class:section-description')->one()->getText());
		}

		if (array_key_exists('Latest data', $data)) {
			foreach ($data['Latest data'] as $section => $value) {
				// Check value in Latest data section -> Last check column.
				if ($section === 'column') {
					if ($value) {
						$last_check = $widget->query('class:section-latest-data')->query('class', $section)
								->query('class:column-value')->query('class:cursor-pointer')->one();

						$this->assertEquals($value, $last_check->getText());
						$last_check->click();
						$hint = $this->query('xpath://div[@data-hintboxid]')->asOverlayDialog()->waitUntilReady()->all()->last();
						$this->assertTrue($hint->isVisible());
						if (array_key_exists('Check last metric time', $data)) {
							$this->assertEquals($hint->getText(), date('Y-m-d H:i:s', CDBHelper::getValue('SELECT clock'.
									' FROM history_uint WHERE itemid='.self::$itemids[$data['Item']]))
							);
						}
						$hint->close();
					}
					else {
						$this->assertEquals($value, $widget->query('class:section-latest-data')->query('class', $section)
								->query('class:column-value')->one()->getText()
						);
					}
				}
				// Check graph or history link in Latest data section.
				elseif ($section === 'right-column') {
					$this->assertTrue($widget->query('class:section-latest-data')->query('class', $section)
							->query('class:column-value')->one()->isClickable()
					);
				}
				// Check Last value column value in Latest data section.
				else {
					$this->assertEquals($value, $widget->query('class:section-latest-data')->query('class', $section)
							->query('class:column-value')->one()->getText()
					);
				}
			}
		}

		if (array_key_exists('Triggers', $data)) {
			// Check list of triggers.
			$triggers = $widget->query('class:section-triggers')->query('class:triggers')->query('class:trigger')->all();
			$actualNames = array_map('trim', str_replace(',', '', $triggers->asText()));

			// Workaround: PostgreSQL returns unsorted trigger list.
			if ($data['Header'] === 'Master item from host') {
				foreach (array_column($data['Triggers'], 'Name') as $triggername) {
					$this->assertTrue(in_array($triggername, $actualNames));
				}
			}
			else {
				$this->assertEquals(array_column($data['Triggers'], 'Name'), $actualNames);
			}

			// Check trigger counter.
			$this->assertEquals(count($data['Triggers']), $widget->query('class:section-triggers')
					->query('class:section-name')->query('xpath:./sup')->one()->getText()
			);

			// Check table pop-up with trigger data.
			$widget->query('class:section-triggers')->query('class:link-action')->one()->waitUntilClickable()->click();
			$hint = $this->query('xpath://div[@class="overlay-dialogue wordbreak"]')->asOverlayDialog()->waitUntilReady()->one();
			$table = $hint->query('class:list-table')->asTable()->one();

			$this->assertEquals(['Severity', 'Name', 'Expression', 'Status'], $table->getHeadersText());

			foreach ($data['Triggers'] as $trigger) {
				$this->assertTrue($table->findRow('Name', $trigger['Name'])->getColumn('Name')->isVisible());
			}

			$hint->close();
		}

		if (array_key_exists('Host inventory', $data)) {
			$this->asssertSectionValue($widget, 'Host inventory', $data['Host inventory']);
		}

		if (array_key_exists('Tags', $data)) {
			$section = $widget->query('class:section-tags')->query('class:tags')->one();
			$tags = $section->query('class:tag')->all();
			$this->assertEquals($data['Tags'], $tags->asText());

			// Check all tags by clicking on the icon to show hidden tags that do not fit due to the widget width.
			if (!empty($data['Tags']) && count($tags->asArray()) > 1) {
				$section->query('tag:button')->one()->click();
				$hint = $this->query('xpath://div[@data-hintboxid]')->asOverlayDialog()->waitUntilReady()->one();
				$this->assertEquals($data['Tags'], $hint->query('class:tag')->all()->asText());
				$hint->close();
			}

			foreach ($data['Tags'] as $i => $tag) {
				// Only the first tag is visible for these test cases due to the widget width.
				if ($i >= 1) {
					$this->assertTrue($tags->get($i)->isVisible(false));
					continue;
				}

				$tags->get($i)->click();
				$hint = $this->query('xpath://div[@data-hintboxid]')->asOverlayDialog()->waitUntilReady()->one();
				$this->assertEquals($tag, $hint->getText());
				$hint->close();
			}
		}
	}

	/**
	 * Check context menu links.
	 *
	 * @param array $data	data provider with fields values
	 */
	protected function checkContextMenuLinks($data, $hostid, $itemid) {
		$popup = CPopupMenuElement::find()->waitUntilVisible()->one();
		$this->assertTrue($popup->hasTitles(array_keys($data)));

		$menu_level1_items = [];
		foreach (array_values($data) as $menu_items) {
			foreach ($menu_items as $menu_level1 => $link) {
				$menu_level1_items[] = $menu_level1;

				if (is_array($link)) {
					foreach ($link as $menu_level2 => $attribute) {
						// Check 2-level menu links.
						$popup->getItem($menu_level1)->click();
						$item_link = $popup->getItem($menu_level1)->query('xpath:./../ul//a[contains'.
								'(@class, "menu-popup-item") and text()='.CXPathHelper::escapeQuotes($menu_level2).']'
						)->one();

						if (str_contains($attribute, 'menu-popup-item')) {
							$this->assertEquals($attribute, $item_link->getAttribute('class'));
						}
						else {
							$attribute = str_replace(['{triggerid}', '{hostid}'],
									[self::$trigger_ids[$menu_level2], $hostid], $attribute
							);
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
						$link = str_replace(['{hostid}','{itemid}'], [$hostid, $itemid], $link);
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

	public static function getCheckLinksData() {
		return 	[
			[
				[
					'widget_name' => 'Master item from host',
					'link_name' => 'host'
				]
			],
			[
				[
					'widget_name' => 'Master item from host',
					'link_name' => 'graph'
				]
			],
			[
				[
					'widget_name' => 'Master item from host',
					'link_name' => 'severity'
				]
			],
			[
				[
					'widget_name' => 'SNMP interface',
					'link_name' => 'severity'
				]
			],
			[
				[
					'widget_name' => 'Dependent Item from host',
					'link_name' => 'master_item'
				]
			],
			[
				[
					'widget_name' => 'Link to LLD rule',
					'link_name' => 'lld_rule'
				]
			],
			[
				[
					'widget_name' => 'Item card',
					'link_name' => 'template'
				]
			],
			[
				[
					'widget_name' => 'Item card',
					'link_name' => 'history'
				]
			]
		];
	}

	/**
	 * Check links in Item card widget.
	 *
	 * @dataProvider getCheckLinksData
	 */
	public function testDashboardItemCardWidget_CheckLinks($data) {
		global $DB;
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboard_ids['Dashboard for Item Card widget display check']
		)->waitUntilReady();

		$dashboard = CDashboardElement::find()->waitUntilReady()->one();
		$widget = $dashboard->getWidget($data['widget_name']);

		switch ($data['link_name']) {
			case 'host':
				$link = 'zabbix.php?action=popup&popup=host.edit&hostid='.self::$host_ids['hostids'][self::HOST_NAME];
				$this->assertEquals($link, $widget->query('class:sections-header')->query('class:section-path')
						->query('class:path-element')->one()->getAttribute('href')
				);
				break;

			case 'graph':
				$widget->query('class:section-latest-data')->query('class:right-column')->query('link:Graph')->one()->click();
				$this->page->assertTitle('History [refreshed every 30 sec.]');
				$this->assertEquals(PHPUNIT_URL.'history.php?action=showgraph&itemids%5B%5D='.
						self::$itemids['Item for item card widget'], $this->page->getCurrentUrl()
				);
				break;

			case 'severity':
				// PostgreSQL returns a list of triggers in an unclear order, so the database type is also checked here.
				if ($data['widget_name'] === 'Master item from host' && $DB['TYPE'] === ZBX_DB_MYSQL) {
					$link = 'zabbix.php?action=problem.view&hostids%5B0%5D='.self::$host_ids['hostids']
							[self::HOST_NAME].'&triggerids%5B0%5D='.
							self::$trigger_ids['Not classified trigger'].'&triggerids%5B1%5D='.
							self::$trigger_ids['Information trigger'].'&triggerids%5B2%5D='.
							self::$trigger_ids['Warning trigger'].'&triggerids%5B3%5D='.
							self::$trigger_ids['Average trigger'].'&triggerids%5B4%5D='.
							self::$trigger_ids['High trigger'].'&triggerids%5B5%5D='.
							self::$trigger_ids['Disaster trigger'].'&filter_set=1';
				}
				else if ($data['widget_name'] === 'Master item from host' && $DB['TYPE'] === ZBX_DB_POSTGRESQL) {
					$link = 'zabbix.php?action=problem.view&hostids%5B0%5D='.self::$host_ids['hostids']
							[self::HOST_NAME].'&triggerids%5B0%5D='.
							self::$trigger_ids['Warning trigger'].'&triggerids%5B1%5D='.
							self::$trigger_ids['Average trigger'].'&triggerids%5B2%5D='.
							self::$trigger_ids['High trigger'].'&triggerids%5B3%5D='.
							self::$trigger_ids['Disaster trigger'].'&triggerids%5B4%5D='.
							self::$trigger_ids['Not classified trigger'].'&triggerids%5B5%5D='.
							self::$trigger_ids['Information trigger'].'&filter_set=1';
				}
				else{
					$link = 'zabbix.php?action=problem.view&hostids%5B0%5D='.
							self::$host_ids['hostids'][self::HOST_NAME].'&triggerids%5B0%5D='.
							self::$trigger_ids['Trigger 1'].'&filter_set=1';
				}

				$this->assertEquals($link, $widget->query('class:sections-header')->query('class:section-item')
						->query('class:problem-icon-link')->one()->getAttribute('href')
				);
				$widget->query('class:sections-header')->query('class:section-item')->query('class:problem-icon-link')
						->one()->click();
				$this->page->assertTitle('Problems');
				break;

			case 'master_item':
				$link = 'zabbix.php?action=popup&popup=item.edit&context=host&itemid='.self::$itemids['Item for item card widget'];
				$this->assertEquals($link, $widget->query('class:sections-header')->query('class:section-path')
						->query('class:teal')->one()->getAttribute('href')
				);
				$widget->query('class:sections-header')->query('class:section-path')->query('class:teal')
						->one()->click();
				$this->assertEquals(PHPUNIT_URL.$link, $this->page->getCurrentUrl());
				break;

			case 'lld_rule':
				$link = 'zabbix.php?action=item.prototype.list&parent_discoveryid='.self::$discovery_rule_id.'&context=host';
				$this->assertEquals($link, $widget->query('class:sections-header')->query('class:section-path')
						->query('class:link-alt orange')->one()->getAttribute('href')
				);
				$widget->query('class:sections-header')->query('class:section-path')->query('class:link-alt orange')
						->one()->click();
				$this->assertEquals(PHPUNIT_URL.$link, $this->page->getCurrentUrl());
				break;

			case 'template':
				$link = 'zabbix.php?action=item.list&filter_set=1&filter_hostids%5B0%5D='.
						self::$templateid.'&context=template';
				$this->assertEquals($link, $widget->query('class:sections-header')->query('class:section-path')
						->query('class:link-alt')->one()->getAttribute('href')
				);

				$widget->query('class:sections-header')->query('class:section-path')->query('class:link-alt')
						->one()->click();
				$this->assertEquals(PHPUNIT_URL.$link, $this->page->getCurrentUrl());
				break;

			case 'history':
				$widget->query('class:section-latest-data')->query('class:right-column')->query('link:History')->one()->click();
				$this->page->assertTitle('History [refreshed every 30 sec.]');
				$this->assertEquals(PHPUNIT_URL.'history.php?action=showvalues&itemids%5B%5D='.
						CDBHelper::getValue('SELECT itemid FROM items WHERE name='.zbx_dbstr('Master item from template').
								' AND templateid IS NOT NULL'),
						$this->page->getCurrentUrl()
				);
				break;
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
	 * Check cancel scenarios for Item Card widget.
	 *
	 * @dataProvider getCancelData
	 */
	public function testDashboardItemCardWidget_Cancel($data) {
		self::$old_hash = CDBHelper::getHash(self::SQL);
		$new_name = 'Widget to be cancelled';

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboard_ids['Dashboard for canceling Item Card widget']
		);

		$dashboard = CDashboardElement::find()->waitUntilReady()->one()->edit();
		self::$old_widget_count = $dashboard->getWidgets()->count();

		// Start updating or creating a widget.
		if (CTestArrayHelper::get($data, 'update', false)) {
			$form = $dashboard->getWidget('CancelItemCardWidget')->edit();
		}
		else {
			$form = $dashboard->addWidget()->asForm();
			$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Item card')]);
		}

		$form->fill([
			'Name' => $new_name,
			'Refresh interval' => '15 minutes',
			'Item' => 'Item for item card widget'
		]);

		$data = [
			'Show' => [
				['action' => USER_ACTION_UPDATE, 'index' => 0, 'section' => 'Tags'],
				['action' => USER_ACTION_UPDATE, 'index' => 2, 'section' => 'Description'],
				['action' => USER_ACTION_REMOVE, 'index' => 1],
				['section' => 'Error text']
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
				foreach (['CancelItemCardWidget' => true, $new_name => false] as $name => $valid) {
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
					'Name' => 'Master item from host'
				]
			],
			[
				[
					'Name' => 'SNMP interface'
				]
			]
		];
	}

	/**
	 * Check different compositions for Item Card widget.
	 *
	 * @dataProvider getWidgetName
	 */
	public function testDashboardItemCardWidget_Screenshots($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.
				self::$dashboard_ids['Dashboard for Item Card widget display check']
		)->waitUntilReady();

		$widget = CDashboardElement::find()->waitUntilReady()->one()->getWidget($data['Name']);

		// Workaround: PostgreSQL returns unsorted trigger list.
		if ($data['Name'] === 'Master item from host'){
			$this->assertScreenshotExcept(CDashboardElement::find()->waitUntilReady()->one()->getWidget($data['Name']),
					[$widget->query('class:section-triggers')->query('class:triggers')->one()], 'itemcard_'.$data['Name']
			);
		}
		else {
			$this->assertScreenshot($widget, 'itemcard_'.$data['Name']);
		}
	}

	/**
	 * Create or update Item Card widget.
	 *
	 * @param array             $data         data provider
	 * @param string            $action       create/update item card widget
	 * @param CDashboardElement $dashboard    given dashboard
	 */
	protected function fillWidgetForm($data, $action, $dashboard) {
		$form = ($action === 'create')
			? $dashboard->edit()->addWidget()->asForm()
			: $dashboard->getWidget('Item card')->edit();

		$form->fill(['Type' => CFormElement::RELOADABLE_FILL('Item card')]);
		$form->fill($data['fields']);

		if (array_key_exists('Show', $data)) {
			$this->getShowTable()->fill($data['Show']);
		}

		if (array_key_exists('Sparkline', $data)) {
			foreach ($data['Sparkline'] as $field => $value) {
				if ($field === 'color') {
					$color_picker_dialog = $form->query('class:color-picker')->one()->asColorPicker();
					$color_picker_dialog->fill($value);
				}
				elseif ($field === 'widget') {
					$sparkline = $form->query('class:widget-field-sparkline')->one()->waitUntilVisible();
					$sparkline->query('button', 'Select')->one()->waitUntilClickable()->click();
					$dialog = COverlayDialogElement::find()->all()->last()->waitUntilReady();
					$dialog->query('link', $value)->one()->click();
				}
				else {
					$form->getField($field)->fill($value);
				}
			}
		}

		if (array_key_exists('Screenshot', $data) && $action === 'create') {
			$this->assertScreenshot($form->query('class:table-forms-separator')->waitUntilPresent()->one(),
					'Full list of show options'.$data['fields']['Item']
			);
		}

		$form->submit();
	}

	/**
	 * Get "Show" table element with mapping set.
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
	 * Check created or updated Item Card widget.
	 *
	 * @param array             $data         data provider
	 * @param string            $action       create/update item card widget
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
			$data['fields']['Item'] = 'Visible host name for Item Card widget: '.trim($data['fields']['Item'], 255);

			// Make sure that the widget is present before saving the dashboard.
			$header = (array_key_exists('Name', $data['fields']))
				? (($data['fields']['Name'] === '') ? 'Item card' : $data['fields']['Name'])
				: 'Item card';

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
			['section' => 'Interval and storage'],
			['section' => 'Type of information'],
			['section' => 'Host interface'],
			['section' => 'Type']
		];

		foreach ($rows as $row) {
			if (array_key_exists('action', $row)) {
				if ($row['action'] === USER_ACTION_REMOVE) {
					// Remove element at index.
					array_splice($result, $row['index'], 1);
				}
				elseif ($row['action'] === USER_ACTION_UPDATE) {
					// Update existing element.
					$result[$row['index']]['section'] = $row['section'];
				}
			}
			else {
				// If no action is specified, it means we are adding a new section.
				$result[] = ['section' => $row['section']];
			}
		}

		return array_values($result);
	}

	/**
	 * Find and check the value on the specific single-parameter section.
	 *
	 * @param CWidgetElement	$widget				given widget
	 * @param string			$section_name		section label
	 * @param string			$expected_value		expected section value
	 */
	protected function asssertSectionValue($widget, $section_name, $expected_value) {
		$row = $widget->query('xpath:.//div[@class="section-name" and text()='.
				CXPathHelper::escapeQuotes($section_name).']'
		)->one();
		$value = $row->query('xpath:./following-sibling::div[1]')->one()->getText();
		$this->assertEquals($expected_value, $value);
	}
}
