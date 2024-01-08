<?php
/*
** Zabbix
** Copyright (C) 2001-2024 Zabbix SIA
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


require_once dirname(__FILE__).'/../common/testPagePrototypes.php';

/**
 * @backup hosts
 *
 * @onBefore prepareGraphPrototypeData
 */
class testPageGraphPrototypes extends testPagePrototypes {

	public $headers = ['', 'Name', 'Width', 'Height', 'Graph type', 'Discover'];
	public $page_name = 'graph';
	public $amount = 4;
	public $buttons = [
		'Delete' => false,
		'Create graph prototype' => true
	];
	public $clickable_headers = ['Name', 'Graph type', 'Discover'];

	protected static $prototype_graphids;
	protected static $hostids;
	protected static $host_druleids;

	public function prepareGraphPrototypeData() {
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
				'name' => '1 Item prototype for graphs',
				'key_' => '1_key[{#KEY}]',
				'hostid' => self::$hostids['Host for prototype check'],
				'ruleid' => self::$host_druleids['Host for prototype check:drule'],
				'type' => ITEM_TYPE_TRAPPER,
				'value_type' => ITEM_VALUE_TYPE_UINT64,
				'delay' => 0
			]
		]);
		$this->assertArrayHasKey('itemids', $item_prototype );
		$prototype_itemids = CDataHelper::getIds('name');

		CDataHelper::call('graphprototype.create', [
			[
				'name' => '1 Graph prototype discovered_{#KEY}',
				'width' => 100,
				'height' => 100,
				'graphtype' => 0,
				'gitems' => [
					[
						'itemid' => $prototype_itemids['1 Item prototype for graphs'],
						'color' => '00AA00'
					]
				]
			],
			[
				'name' => '2 Graph prototype not discovered_{#KEY}',
				'width' => 200,
				'height' => 200,
				'graphtype' => 1,
				'discover' => GRAPH_NO_DISCOVER,
				'gitems' => [
					[
						'itemid' => $prototype_itemids['1 Item prototype for graphs'],
						'color' => '00AA00'
					]
				]
			],
			[
				'name' => '3 Graph prototype pie discovered_{#KEY}',
				'width' => 300,
				'height' => 300,
				'graphtype' => 2,
				'gitems' => [
					[
						'itemid' => $prototype_itemids['1 Item prototype for graphs'],
						'color' => '00AA00'
					]
				]
			],
			[
				'name' => '4 Graph prototype exploded not discovered_{#KEY}',
				'width' => 400,
				'height' => 400,
				'graphtype' => 3,
				'discover' => GRAPH_NO_DISCOVER,
				'gitems' => [
					[
						'itemid' => $prototype_itemids['1 Item prototype for graphs'],
						'color' => '00AA00'
					]
				]
			]
		]);
		self::$prototype_graphids = CDataHelper::getIds('name');
	}

	public function testPageGraphPrototypes_Layout() {
		$this->page->login()->open('graphs.php?context=host&sort=name&sortorder=ASC&parent_discoveryid='.
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
						'1 Graph prototype discovered_{#KEY}',
						'2 Graph prototype not discovered_{#KEY}',
						'3 Graph prototype pie discovered_{#KEY}',
						'4 Graph prototype exploded not discovered_{#KEY}'
					]
				]
			],
			// #1 Sort by Graph type.
			[
				[
					'sort_by' => 'Graph type',
					'sort' => 'graphtype',
					'result' => [
						'Exploded',
						'Normal',
						'Pie',
						'Stacked'
					]
				]
			],
			// #2 Sort by Discover.
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
	 * Sort graph prototypes by Name, Graph type and Discover columns.
	 *
	 * @dataProvider getSortingData
	 */
	public function testPageGraphPrototypes_Sorting($data) {
		$this->page->login()->open('graphs.php?context=host&sort='.$data['sort'].'&sortorder=ASC&parent_discoveryid='.
				self::$host_druleids['Host for prototype check:drule'])->waitUntilReady();
		$this->executeSorting($data);
	}

	public static function getButtonLinkData() {
		return [
			// #0 Enable discovering clicking on link in Discover column.
			[
				[
					'name' => '2 Graph prototype not discovered_{#KEY}',
					'column_check' => 'Discover',
					'before' => 'No',
					'after' => 'Yes'
				]
			],
			// #1 Disable discovering clicking on link in Discover column.
			[
				[
					'name' => '1 Graph prototype discovered_{#KEY}',
					'column_check' => 'Discover',
					'before' => 'Yes',
					'after' => 'No'
				]
			]
		];
	}

	/**
	 * Check link from Discover column.
	 *
	 * @dataProvider getButtonLinkData
	 */
	public function testPageGraphPrototypes_ButtonLink($data) {
		$this->page->login()->open('graphs.php?context=host&sort=name&sortorder=ASC&parent_discoveryid='.
				self::$host_druleids['Host for prototype check:drule'])->waitUntilReady();
		$this->executeDiscoverEnable($data);
	}

	public static function getDeleteData() {
		return [
			// #0 Cancel delete.
			[
				[
					'name' => ['1 Graph prototype discovered_{#KEY}'],
					'cancel' => true
				]
			],
			// #1 Delete one.
			[
				[
					'name' => ['2 Graph prototype not discovered_{#KEY}'],
					'message' => 'Graph prototype deleted'
				]
			],
			// #2 Delete more than 1.
			[
				[
					'name' => [
						'3 Graph prototype pie discovered_{#KEY}',
						'4 Graph prototype exploded not discovered_{#KEY}'
					],
					'message' => 'Graph prototypes deleted'
				]
			]
		];
	}

	/**
	 * Check delete scenarios.
	 *
	 * @dataProvider getDeleteData
	 */
	public function testPageGraphPrototypes_Delete($data) {
		$sql = 'SELECT null FROM graphs WHERE graphid=';
		$this->page->login()->open('graphs.php?context=host&sort=name&sortorder=ASC&parent_discoveryid='.
				self::$host_druleids['Host for prototype check:drule'])->waitUntilReady();

		foreach ($data['name'] as $name) {
			$this->assertEquals(1, CDBHelper::getCount($sql.self::$prototype_graphids[$name]));
		}

		$this->executeDelete($data);

		$count = (array_key_exists('cancel', $data)) ? 1 : 0;

		foreach ($data['name'] as $name) {
			$this->assertEquals($count, CDBHelper::getCount($sql.self::$prototype_graphids[$name]));
		}
	}
}
