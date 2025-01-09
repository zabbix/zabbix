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


require_once dirname(__FILE__).'/../common/testPagePrototypes.php';

/**
 * @backup hosts
 *
 * @onBefore prepareItemPrototypeData
 */
class testPageItemPrototypes extends testPagePrototypes {

	public $source = 'item';
	public $tag = 'Yw Item prototype trapper with text type';

	protected $link = 'zabbix.php?action=item.prototype.list&context=host&sort=name&sortorder=ASC&parent_discoveryid=';
	protected static $prototype_itemids;
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
						'delay' => 0
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
		$hostids = $host_result['hostids']['Host for prototype check'];
		self::$host_druleids = $host_result['discoveryruleids']['Host for prototype check:drule'];

		$item_prototype = CDataHelper::call('itemprototype.create', [
			[
				'name' => '3a Item prototype monitored discovered',
				'key_' => '3a_key[{#KEY}]',
				'hostid' => $hostids,
				'ruleid' => self::$host_druleids,
				'type' => ITEM_TYPE_ZABBIX_ACTIVE,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => '15',
				'history' => '1h',
				'trends' => '24h'
			],
			[
				'name' => '15 Item prototype not monitored discovered',
				'key_' => '15_key[{#KEY}]',
				'hostid' => $hostids,
				'ruleid' => self::$host_druleids,
				'type' => ITEM_TYPE_INTERNAL,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => '33m',
				'status' => ITEM_STATUS_DISABLED,
				'history' => '61m',
				'trends' => '86450s'
			],
			[
				'name' => '33b4 Item prototype not monitored not discovered',
				'key_' => '33b4_key[{#KEY}]',
				'hostid' => $hostids,
				'ruleid' => self::$host_druleids,
				'type' => ITEM_TYPE_HTTPAGENT,
				'url' => 'test',
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => '15h',
				'status' => ITEM_STATUS_DISABLED,
				'discover' => ITEM_NO_DISCOVER,
				'history' => '2d',
				'trends' => '2d'
			],
			[
				'name' => 'a3 Item prototype monitored not discovered',
				'key_' => 'a3_key[{#KEY}]',
				'hostid' => $hostids,
				'ruleid' => self::$host_druleids,
				'type' => ITEM_TYPE_CALCULATED,
				'params' => '1+1',
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => '1d',
				'discover' => ITEM_NO_DISCOVER,
				'history' => '1w',
				'trends' => '1w'
			],
			[
				'name' => 'Yw Item prototype trapper with text type',
				'key_' => 'Yw_key[{#KEY}]',
				'hostid' => $hostids,
				'ruleid' => self::$host_druleids,
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_TEXT,
				'delay' => '',
				'history' => 0,
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
		self::$entity_count = count(self::$prototype_itemids);
	}

	public function testPageItemPrototypes_Layout() {
		$this->page->login()->open($this->link.self::$host_druleids)->waitUntilReady();
		$this->checkLayout();
	}

	/**
	 * Sort item prototypes by Name, Key, Interval, History, Trends, Type, Create enabled and Discover columns.
	 *
	 * @dataProvider getItemPrototypesSortingData
	 */
	public function testPageItemPrototypes_Sorting($data) {
		$this->page->login()->open('zabbix.php?action=item.prototype.list&context=host&sort='.$data['sort'].'&sortorder=ASC&'.
				'parent_discoveryid='.self::$host_druleids)->waitUntilReady();
		$this->executeSorting($data);
	}

	/**
	 * Check Create enabled/disabled buttons and links from Create enabled and Discover columns.
	 *
	 * @dataProvider getItemPrototypesButtonLinkData
	 */
	public function testPageItemPrototypes_ButtonLink($data) {
		$this->page->login()->open($this->link.self::$host_druleids)->waitUntilReady();
		$this->checkTableAction($data);
	}

	/**
	 * Check delete scenarios.
	 *
	 * @dataProvider getItemPrototypesDeleteData
	 */
	public function testPageItemPrototypes_Delete($data) {
		$this->page->login()->open($this->link.self::$host_druleids)->waitUntilReady();

		$ids = [];
		foreach ($data['name'] as $name) {
			$ids[] = self::$prototype_itemids[$name];
		}

		$this->checkDelete($data, $ids);
	}

	/**
	 * Check that empty values displayed in Trends and Interval columns. SNMP, Zabbix trappers has empty values in trends column.
	 * Dependent items has empty update interval column.
	 *
	 * @dataProvider getItemPrototypesNotDisplayedValuesData
	 */
	public function testPageItemPrototypes_NotDisplayedValues($data) {
		$this->page->login()->open($this->link.self::$host_druleids)->waitUntilReady();
		$this->checkNotDisplayedValues($data);
	}
}
