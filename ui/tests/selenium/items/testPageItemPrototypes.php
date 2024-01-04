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
require_once dirname(__FILE__).'/../behaviors/CTableBehavior.php';
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';
require_once dirname(__FILE__).'/../common/testPagePrototypes.php';

/**
 * @backup hosts
 *
 * @onBefore prepareItemPrototypeData
 */
class testPageItemPrototypes extends testPagePrototypes {

	/**
	 * Attach MessageBehavior and TableBehavior to the test.
	 *
	 * @return array
	 */
	public function getBehaviors() {
		return [
			CTableBehavior::class,
			CMessageBehavior::class
		];
	}

	public $single_success = 'Item prototype updated';
	public $several_success = 'Item prototypes updated';
	public $sql = 'SELECT null FROM items WHERE itemid=';

	public $headers = ['', '', 'Name', 'Key', 'Interval', 'History', 'Trends', 'Type', 'Create enabled', 'Discover', 'Tags'];
	public $page_name = 'item';
	public $amount = 5;
	public $buttons = [
		'Create enabled' => false,
		'Create disabled' => false,
		'Mass update' => false,
		'Delete' => false,
		'Create item prototype' => true
	];
	public $tag = '5 Item prototype trapper with text type';
	public $clickable_headers = ['Name', 'Key', 'Interval', 'History', 'Trends', 'Type', 'Create enabled', 'Discover'];

	protected static $prototype_itemids;
	protected static $hostids;
	protected static $host_druleids;

	public function prepareItemPrototypeData() {
		$host_result = CDataHelper::createHosts([
			[
				'host' => 'Host for prototype check',
				'interfaces' => [
					[
						'type' => INTERFACE_TYPE_SNMP,
						'main' => INTERFACE_PRIMARY,
						'useip' => INTERFACE_USE_IP,
						'ip' => '127.0.0.1',
						'dns' => '',
						'port' => '161',
						'details' => [
							'version' => 1,
							'community' => 'test'
						]
					]
				],
				'items' => [
					[
						'name' => 'Master item',
						'key_' => 'master_item',
						'type' => ITEM_TYPE_TRAPPER,
						'value_type' => ITEM_VALUE_TYPE_FLOAT,
						'delay' => '0'
					]
				],
				'groups' => [['groupid' => 4]], // Zabbix server
				'discoveryrules' => [
					[
						'name' => 'Drule for prototype check',
						'key_' => 'drule',
						'type' => ITEM_TYPE_TRAPPER,
						'delay' => 0
					]
				]
			]
		]);
		self::$hostids = $host_result['hostids'];
		self::$host_druleids = $host_result['discoveryruleids'];

		$item_prototype  = CDataHelper::call('itemprototype.create', [
			[
				'name' => '1 Item prototype monitored discovered',
				'key_' => '1_key[{#KEY}]',
				'hostid' => self::$hostids['Host for prototype check'],
				'ruleid' => self::$host_druleids['Host for prototype check:drule'],
				'type' => ITEM_TYPE_ZABBIX_ACTIVE,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 15,
				'history' => '60d',
				'trends' => '200d'
			],
			[
				'name' => '2 Item prototype not monitored discovered',
				'key_' => '2_key[{#KEY}]',
				'hostid' => self::$hostids['Host for prototype check'],
				'ruleid' => self::$host_druleids['Host for prototype check:drule'],
				'type' => ITEM_TYPE_INTERNAL,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 30,
				'status' => ITEM_STATUS_DISABLED,
				'history' => '70d',
				'trends' => '250d'
			],
			[
				'name' => '3 Item prototype not monitored not discovered',
				'key_' => '3_key[{#KEY}]',
				'hostid' => self::$hostids['Host for prototype check'],
				'ruleid' => self::$host_druleids['Host for prototype check:drule'],
				'type' => ITEM_TYPE_HTTPAGENT,
				'url' => 'test',
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 45,
				'status' => ITEM_STATUS_DISABLED,
				'discover' => ITEM_NO_DISCOVER,
				'history' => '80d',
				'trends' => '300d'
			],
			[
				'name' => '4 Item prototype monitored not discovered',
				'key_' => '4_key[{#KEY}]',
				'hostid' => self::$hostids['Host for prototype check'],
				'ruleid' => self::$host_druleids['Host for prototype check:drule'],
				'type' => ITEM_TYPE_CALCULATED,
				'params' => '1+1',
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 60,
				'discover' => ITEM_NO_DISCOVER,
				'history' => '90d',
				'trends' => '350d'
			],
			[
				'name' => '5 Item prototype trapper with text type',
				'key_' => '5_key[{#KEY}]',
				'hostid' => self::$hostids['Host for prototype check'],
				'ruleid' => self::$host_druleids['Host for prototype check:drule'],
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'delay' => '',
				'history' => '0',
				'tags' => [
					[
						'tag' => 'name_1',
						'value' => 'value_1'
					],
					[
						'tag' => 'name_2',
						'value' => 'value_2'
					]
				]
			]
		]);
		$this->assertArrayHasKey('itemids', $item_prototype );
		self::$prototype_itemids = CDataHelper::getIds('name');
	}

	public function testPageItemPrototypes_Layout() {
		$this->page->login()->open('zabbix.php?action=item.prototype.list&context=host&sort=name&sortorder=ASC&parent_discoveryid='.
				self::$host_druleids['Host for prototype check:drule'])->waitUntilReady();
		$this->layout();
	}

	public static function getSortingData() {
		return [
			// #0 Sort by Name.
			[
				[
					'sort_by' => 'Name',
					'sort' => 'name',
					'result' => [
						'1 Item prototype monitored discovered',
						'2 Item prototype not monitored discovered',
						'3 Item prototype not monitored not discovered',
						'4 Item prototype monitored not discovered',
						'5 Item prototype trapper with text type'
					]
				]
			],
			// #1 Sort by Key.
			[
				[
					'sort_by' => 'Key',
					'sort' => 'key_',
					'result' => [
						'1_key[{#KEY}]',
						'2_key[{#KEY}]',
						'3_key[{#KEY}]',
						'4_key[{#KEY}]',
						'5_key[{#KEY}]'
					]
				]
			],
			// #2 Sort by Interval.
			[
				[
					'sort_by' => 'Interval',
					'sort' => 'delay',
					'result' => [
						'',
						15,
						30,
						45,
						60
					]
				]
			],
			// #3 Sort by History.
			[
				[
					'sort_by' => 'History',
					'sort' => 'history',
					'result' => [
						'0',
						'60d',
						'70d',
						'80d',
						'90d'
					]
				]
			],
			// #4 Sort by Trends.
			[
				[
					'sort_by' => 'Trends',
					'sort' => 'trends',
					'result' => [
						'',
						'200d',
						'250d',
						'300d',
						'350d'
					]
				]
			],
			// #5 Sort by Type.
			[
				[
					'sort_by' => 'Type',
					'sort' => 'type',
					'result' => [
						'Zabbix trapper',
						'Zabbix internal',
						'Zabbix agent (active)',
						'Calculated',
						'HTTP agent'
					]
				]
			],
			// #6 Sort by Create enabled.
			[
				[
					'sort_by' => 'Create enabled',
					'sort' => 'status',
					'result' => [
						'Yes',
						'Yes',
						'Yes',
						'No',
						'No'
					]
				]
			],
			// #7 Sort by Discover.
			[
				[
					'sort_by' => 'Discover',
					'sort' => 'discover',
					'result' => [
						'Yes',
						'Yes',
						'Yes',
						'No',
						'No'
					]
				]
			]
		];
	}

	/**
	 * Sort item prototypes.
	 *
	 * @dataProvider getSortingData
	 */
	public function testPageItemPrototypes_Sorting($data) {
		$this->page->login()->open('zabbix.php?action=item.prototype.list&context=host&sort='.$data['sort'].'&sortorder=ASC&parent_discoveryid='.
				self::$host_druleids['Host for prototype check:drule'])->waitUntilReady();
		$this->executeSorting($data);
	}

	public static function getButtonLinkData() {
		return [
			// #0 Click on Create disabled button.
			[
				[
					'name' => '1 Item prototype monitored discovered',
					'button' => 'Create disabled',
					'column_check' => 'Create enabled',
					'before' => 'Yes',
					'after' => 'No'
				]
			],
			// #1 Click on Create enabled button.
			[
				[
					'name' => '2 Item prototype not monitored discovered',
					'button' => 'Create enabled',
					'column_check' => 'Create enabled',
					'before' => 'No',
					'after' => 'Yes'
				]
			],
			// #2 Enabled clicking on link in Create enabled column.
			[
				[
					'name' => '3 Item prototype not monitored not discovered',
					'column_check' => 'Create enabled',
					'before' => 'No',
					'after' => 'Yes'
				]
			],
			// #3 Disabled clicking on link in Create enabled column.
			[
				[
					'name' => '4 Item prototype monitored not discovered',
					'column_check' => 'Create enabled',
					'before' => 'Yes',
					'after' => 'No'
				]
			],
			// #4 Enable discovering clicking on link in Discover column.
			[
				[
					'name' => '3 Item prototype not monitored not discovered',
					'column_check' => 'Discover',
					'before' => 'No',
					'after' => 'Yes'
				]
			],
			// #5 Disable discovering clicking on link in Discover column.
			[
				[
					'name' => '2 Item prototype not monitored discovered',
					'column_check' => 'Discover',
					'before' => 'Yes',
					'after' => 'No'
				]
			],
			// #6 Enable all host prototypes clicking on Create enabled button.
			[
				[
					'button' => 'Create enabled',
					'column_check' => 'Create enabled',
					'after' => ['Yes', 'Yes', 'Yes', 'Yes', 'Yes']
				]
			],
			// #7 Disable all host prototypes clicking on Create disabled button.
			[
				[
					'button' => 'Create disabled',
					'column_check' => 'Create enabled',
					'after' => ['No', 'No', 'No', 'No', 'No']
				]
			]
		];
	}

	/**
	 * Check Create enabled/disabled buttons and links from Create enabled and Discover columns.
	 *
	 * @dataProvider getButtonLinkData
	 */
	public function testPageItemPrototypes_ButtonLink($data) {
		$this->page->login()->open('zabbix.php?action=item.prototype.list&context=host&sort=name&sortorder=ASC&parent_discoveryid='.
				self::$host_druleids['Host for prototype check:drule'])->waitUntilReady();
		$this->executeDiscoverEnable($data);
	}

	public static function getDeleteData() {
		return [
			// #0 Cancel delete.
			[
				[
					'name' => ['1 Item prototype monitored discovered'],
					'cancel' => true
				]
			],
			// #1 Delete one.
			[
				[
					'name' => ['2 Item prototype not monitored discovered'],
					'message' => 'Item prototype deleted'
				]
			],
			// #2 Delete more than 1.
			[
				[
					'name' => [
						'3 Item prototype not monitored not discovered',
						'4 Item prototype monitored not discovered'
					],
					'message' => 'Item prototypes deleted'
				]
			]
		];
	}

	/**
	 * Check delete scenarios.
	 *
	 * @dataProvider getDeleteData
	 */
	public function testPageItemPrototypes_Delete($data) {
		$this->page->login()->open('zabbix.php?action=item.prototype.list&context=host&sort=name&sortorder=ASC&parent_discoveryid='.
				self::$host_druleids['Host for prototype check:drule'])->waitUntilReady();

		foreach ($data['name'] as $name) {
			$this->assertEquals(1, CDBHelper::getCount($this->sql.self::$prototype_itemids[$name]));
		}

		$this->executeDelete($data);

		$count = (array_key_exists('cancel', $data)) ? 1 : 0;

		foreach ($data['name'] as $name) {
			$this->assertEquals($count, CDBHelper::getCount($this->sql.self::$prototype_itemids[$name]));
		}
	}

	public static function getNotDisplayedValuesData() {
		return [
			// #0 SNMP trapper without interval.
			[
				[
					'fields' => [
						'Name' => 'Empty SNMP interval',
						'Type' => 'SNMP trap',
						'Key' => 'snmp_interval_[{#KEY}]'
					],
					'check' => [
						'Interval' => ''
					]
				]
			],
			// #1 Zabbix trapper without interval.
			[
				[
					'fields' => [
						'Name' => 'Empty Zabbix trapper interval',
						'Type' => 'Zabbix trapper',
						'Key' => 'zabbix_trapper_interval_[{#KEY}]'
					],
					'check' => [
						'Interval' => ''
					]
				]
			],
			// #2 Dependent item without interval.
			[
				[
					'fields' => [
						'Name' => 'Empty dependent item interval',
						'Type' => 'Dependent item',
						'Master item' => 'Master item',
						'Key' => 'dependent_interval_[{#KEY}]'
					],
					'check' => [
						'Interval' => ''
					]
				]
			],
			// #3 Zabbix agent with type of information - text.
			[
				[
					'fields' => [
						'Name' => 'Text zabbix trapper',
						'Type' => 'Zabbix trapper',
						'Type of information' => 'Text',
						'Key' => 'text_[{#KEY}]'
					],
					'check' => [
						'Trends' => ''
					]
				]
			],
			// #4 Zabbix agent with type of information - character.
			[
				[
					'fields' => [
						'Name' => 'Character zabbix trapper',
						'Type' => 'Zabbix trapper',
						'Type of information' => 'Character',
						'Key' => 'character_[{#KEY}]'
					],
					'check' => [
						'Trends' => ''
					]
				]
			],
			// #5 Zabbix agent with type of information - log.
			[
				[
					'fields' => [
						'Name' => 'Log zabbix trapper',
						'Type' => 'Zabbix trapper',
						'Type of information' => 'Log',
						'Key' => 'log_[{#KEY}]'
					],
					'check' => [
						'Trends' => ''
					]
				]
			]
		];
	}

	/**
	 * Check that empty values displayed in Trends and Interval columns.
	 *
	 * @dataProvider getNotDisplayedValuesData
	 */
	public function testPageItemPrototypes_NotDisplayedValues($data) {
		$this->page->login()->open('zabbix.php?action=item.prototype.list&context=host&sort=name&sortorder=ASC&parent_discoveryid='.
				self::$host_druleids['Host for prototype check:drule'])->waitUntilReady();

		$this->query('button:Create item prototype')->one()->click();
		$form = $this->query('name:itemForm')->waitUntilPresent()->asForm()->one();
		$form->fill($data['fields']);
		$form->submit()->waitUntilNotVisible();
		$this->page->waitUntilReady();

		$table = $this->query('class:list-table')->asTable()->one();
		$template_row = $table->findRow('Key', $data['fields']['Key']);
		$template_row->assertValues($data['check']);
	}
}
