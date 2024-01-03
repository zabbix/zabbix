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

	protected static $prototype_itemids;
	protected static $hostids;
	protected static $host_druleids;

	public function prepareItemPrototypeData() {
		$host_result = CDataHelper::createHosts([
			[
				'host' => 'Host for item prototype check',
				'groups' => [['groupid' => 4]], // Zabbix server
				'discoveryrules' => [
					[
						'name' => 'Drule for item prototype check',
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
				'hostid' => self::$hostids['Host for item prototype check'],
				'ruleid' => self::$host_druleids['Host for item prototype check:drule'],
				'type' => ITEM_TYPE_ZABBIX_ACTIVE,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 15,
				'history' => '60d',
				'trends' => '200d'
			],
			[
				'name' => '2 Item prototype not monitored discovered',
				'key_' => '2_key[{#KEY}]',
				'hostid' => self::$hostids['Host for item prototype check'],
				'ruleid' => self::$host_druleids['Host for item prototype check:drule'],
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
				'hostid' => self::$hostids['Host for item prototype check'],
				'ruleid' => self::$host_druleids['Host for item prototype check:drule'],
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
				'hostid' => self::$hostids['Host for item prototype check'],
				'ruleid' => self::$host_druleids['Host for item prototype check:drule'],
				'type' => ITEM_TYPE_CALCULATED,
				'params' => '1+1',
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 60,
				'discover' => ITEM_NO_DISCOVER,
				'history' => '90d',
				'trends' => '350d'
			]
		]);
		$this->assertArrayHasKey('itemids', $item_prototype );
		self::$prototype_itemids = CDataHelper::getIds('name');
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
						'4 Item prototype monitored not discovered'
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
						'4_key[{#KEY}]'
					]
				]
			],
			// #2 Sort by Interval.
			[
				[
					'sort_by' => 'Interval',
					'sort' => 'delay',
					'result' => [
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
				self::$host_druleids['Host for item prototype check:drule'])->waitUntilReady();
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
					'after' => ['Yes', 'Yes', 'Yes', 'Yes']
				]
			],
			// #7 Disable all host prototypes clicking on Create disabled button.
			[
				[
					'button' => 'Create disabled',
					'column_check' => 'Create enabled',
					'after' => ['No', 'No', 'No', 'No']
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
			 	self::$host_druleids['Host for item prototype check:drule'])->waitUntilReady();
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
				self::$host_druleids['Host for item prototype check:drule'])->waitUntilReady();

		foreach ($data['name'] as $name) {
			$this->assertEquals(1, CDBHelper::getCount($this->sql.self::$prototype_itemids[$name]));
		}

		$this->executeDelete($data);

		$count = (array_key_exists('cancel', $data)) ? 1 : 0;

		foreach ($data['name'] as $name) {
			$this->assertEquals($count, CDBHelper::getCount($this->sql.self::$prototype_itemids[$name]));
		}
	}

	public function testPageItemPrototypes_NotDisplayedValues() {

	}

//	// Returns all item protos
//	public static function data() {
//		return CDBHelper::getDataProvider(
//			'SELECT h.status,i.name,i.itemid,d.parent_itemid,h.hostid,di.name AS d_name'.
//			' FROM items i,item_discovery d,items di,hosts h'.
//			' WHERE i.itemid=d.itemid'.
//				' AND h.hostid=i.hostid'.
//				' AND d.parent_itemid=di.itemid'.
//				' AND i.key_ LIKE \'%-layout-test%\''
//		);
//	}

//	/**
//	* @dataProvider data
//	*/
//	public function testPageItemPrototypes_CheckLayout($data) {
//		$drule = $data['d_name'];
//		$context = ($data['status'] == HOST_STATUS_TEMPLATE) ? 'template' : 'host';
//		$this->page->login()->open('zabbix.php?action=item.prototype.list&parent_discoveryid='.
//				$data['parent_itemid'].'&context='.$context);
//
//		$this->zbxTestCheckTitle('Configuration of item prototypes');
//		$this->zbxTestCheckHeader('Item prototypes');
//		$this->zbxTestTextPresent($drule);
//		$this->zbxTestTextPresent($data['name']);
//		$this->zbxTestTextPresent('Displaying');
//
//		if ($data['status'] == HOST_STATUS_MONITORED || $data['status'] == HOST_STATUS_NOT_MONITORED) {
//			$this->zbxTestTextPresent('All hosts');
//		}
//		if ($data['status'] == HOST_STATUS_TEMPLATE) {
//			$this->zbxTestTextPresent('All templates');
//		}
//
//		$this->zbxTestTextPresent(['Name', 'Key', 'Interval', 'History', 'Trends', 'Type', 'Create enabled']);
//		$this->zbxTestTextNotPresent('Info');
//		// TODO someday should check that interval is not shown for trapper items, trends not shown for non-numeric items etc
//
//		$this->zbxTestTextPresent(['Create disabled', 'Delete']);
//	}
//
//	/**
//	 * @dataProvider data
//	 * @backupOnce triggers
//	 */
//	public function testPageItemPrototypes_SimpleDelete($data) {
//		$itemid = $data['itemid'];
//		$context = ($data['status'] == HOST_STATUS_TEMPLATE) ? 'template' : 'host';
//		$this->page->login()->open('zabbix.php?action=item.prototype.list&parent_discoveryid='.
//				$data['parent_itemid'].'&context='.$context);
//
//		$this->zbxTestCheckTitle('Configuration of item prototypes');
//		$this->zbxTestCheckboxSelect('itemids_'.$itemid);
//		$this->query('button:Delete')->one()->click();
//
//		$this->zbxTestAcceptAlert();
//
//		$this->zbxTestCheckTitle('Configuration of item prototypes');
//		$this->zbxTestCheckHeader('Item prototypes');
//		$this->assertMessage(TEST_GOOD, 'Item prototype deleted');
//
//		$sql = 'SELECT null FROM items WHERE itemid='.$itemid;
//		$this->assertEquals(0, CDBHelper::getCount($sql));
//	}
//
//	// Returns all discovery rules
//	public static function rule() {
//		return CDBHelper::getDataProvider(
//			'SELECT h.status,i.name,i.itemid,d.parent_itemid,h.hostid,di.name AS d_name'.
//			' FROM items i,item_discovery d,items di,hosts h'.
//			' WHERE i.itemid=d.itemid'.
//				' AND h.hostid=i.hostid'.
//				' AND d.parent_itemid=di.itemid'.
//				' AND h.host LIKE \'%-layout-test%\''
//		);
//	}
//
//	/**
//	 * @dataProvider rule
//	 * @backupOnce triggers
//	 */
//	public function testPageItemPrototypes_MassDelete($rule) {
//		$itemid = $rule['itemid'];
//		$druleid = $rule['parent_itemid'];
//		$drule = $rule['d_name'];
//		$hostid = $rule['hostid'];
//		$context = (str_contains($rule['name'], '001')) ? 'template' : 'host';
//
//		$itemids = CDBHelper::getAll('select itemid from item_discovery where parent_itemid='.$druleid);
//		$itemids = zbx_objectValues($itemids, 'itemid');
//
//		$this->page->login()->open('zabbix.php?action=item.prototype.list&parent_discoveryid='.$druleid.'&context='.$context);
//		$this->zbxTestCheckTitle('Configuration of item prototypes');
//		$this->zbxTestCheckboxSelect('all_items');
//		$this->query('button:Delete')->one()->click();
//
//		$this->zbxTestAcceptAlert();
//
//		$this->page->waitUntilReady();
//		$this->zbxTestCheckTitle('Configuration of item prototypes');
//		$this->zbxTestCheckHeader('Item prototypes');
//		$this->assertMessage(TEST_GOOD, 'Item prototype deleted');
//
//		$sql = 'SELECT null FROM items WHERE '.dbConditionInt('itemid', $itemids);
//		$this->assertEquals(0, CDBHelper::getCount($sql));
//	}
}
