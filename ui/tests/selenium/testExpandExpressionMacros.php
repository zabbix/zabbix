<?php
/*
** Zabbix
** Copyright (C) 2001-2021 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CWebTest.php';

/**
 * @onBefore prepareItemsData, prepareMapsData, writeValuesToItems
 *
 * @onAfter clearData
 */
class testExpandExpressionMacros extends CWebTest {

	/**
	 * The id of the hostgroup for hosts with items and graph.
	 *
	 * @var integer
	 */
	protected static $hostgroupid ;

	/**
	 * The id of the host for macro with last function.
	 *
	 * @var integer
	 */
	protected static $last_hostid;

	/**
	 * The id of the host for macro with avg function.
	 *
	 * @var integer
	 */
	protected static $avg_hostid;

	/**
	 * The id of the host for macro with min function.
	 *
	 * @var integer
	 */
	protected static $min_hostid;

	/**
	 * The id of the host for macro with max function.
	 *
	 * @var integer
	 */
	protected static $max_hostid;

	/**
	 * The id of the item on $last_hostid.
	 *
	 * @var integer
	 */
	protected static $last_itemid;

	/**
	 * The id of the item on $avg_hostid.
	 *
	 * @var integer
	 */
	protected static $avg_itemid;

	/**
	 * The id of the item on $min_hostid.
	 *
	 * @var integer
	 */
	protected static $min_itemid;

	/**
	 * The id of the item on $max_hostid.
	 *
	 * @var integer
	 */
	protected static $max_itemid;

	/**
	 * The id of the map with expanded expression macros.
	 *
	 * @var integer
	 */
	protected static $mapid;

	public function prepareItemsData() {
		// Create hostgroup for hosts with items and graphs.
		$hostgroups = CDataHelper::call('hostgroup.create', [
			[
				'name' => 'Group for macro expand testing'
			]
		]);
		$this->assertArrayHasKey('groupids', $hostgroups);
		self::$hostgroupid = $hostgroups['groupids'][0];

		// Create hosts for items and graphs.
		$hosts = CDataHelper::call('host.create', [
			[
				'host' => 'Host for expression macro Last',
				'groups' => [
					['groupid' => self::$hostgroupid]
				]
			],
			[
				'host' => 'Host for expression macro Avg',
				'groups' => [
					['groupid' => self::$hostgroupid]
				]
			],
			[
				'host' => 'Host for expression macro Min',
				'groups' => [
					['groupid' => self::$hostgroupid]
				]
			],
			[
				'host' => 'Host for expression macro Max',
				'groups' => [
					['groupid' => self::$hostgroupid]
				]
			]
		]);
		$this->assertArrayHasKey('hostids', $hosts);
		self::$last_hostid = $hosts['hostids'][0];
		self::$avg_hostid = $hosts['hostids'][1];
		self::$min_hostid = $hosts['hostids'][2];
		self::$max_hostid = $hosts['hostids'][3];

		// Create items on previously created hosts.
		$items = CDataHelper::call('item.create', [
			[
				'hostid' => self::$last_hostid,
				'name' => 'trapper',
				'key_' => 'trapper',
				'type' => 2,
				'value_type' => 0
			],
			[
				'hostid' => self::$avg_hostid,
				'name' => 'trapper',
				'key_' => 'trapper',
				'type' => 2,
				'value_type' => 0
			],
			[
				'hostid' => self::$min_hostid,
				'name' => 'trapper',
				'key_' => 'trapper',
				'type' => 2,
				'value_type' => 0
			],
			[
				'hostid' => self::$max_hostid,
				'name' => 'trapper',
				'key_' => 'trapper',
				'type' => 2,
				'value_type' => 0
			],
		]);
		self::$last_itemid = $items['itemids'][0];
		self::$avg_itemid = $items['itemids'][1];
		self::$min_itemid = $items['itemids'][2];
		self::$max_itemid = $items['itemids'][3];

		// Create graphs with expression macros in names.
		CDataHelper::call('graph.create', [
			[
				'name' => 'Last trapper value: {?last(/Host for expression macro Last/trapper)}',
				'width' => 900,
				'height' => 200,
				'gitems' => [
					[
						'itemid' => self::$last_itemid,
						'color'=> '00AA00'
					]
				]
			],
			[
				'name' => 'Avg trapper value: {?avg(/{HOST.HOST}/trapper,1h)}',
				'width' => 900,
				'height' => 200,
				'gitems' => [
					[
						'itemid' => self::$avg_itemid,
						'color'=> '00AA00'
					]
				]
			],
			[
				'name' => 'Max trapper value: {?max(/Host for expression macro Min/trapper,1w)}',
				'width' => 900,
				'height' => 200,
				'gitems' => [
					[
						'itemid' => self::$min_itemid,
						'color'=> '00AA00'
					]
				]
			],
			[
				'name' => 'Min trapper value: {?min(/{HOST.HOST}/trapper,1d)}',
				'width' => 900,
				'height' => 200,
				'gitems' => [
					[
						'itemid' => self::$max_itemid,
						'color'=> '00AA00'
					]
				]
			]
		]);
	}

	public function getGraphData() {
		return [
			[
				[
					'host_name' => 'Host for expression macro Last'
				]
			],
			[
				[
					'host_name' => 'Host for expression macro Avg'
				]
			],
			[
				[
					'host_name' => 'Host for expression macro Min'
				]
			],
			[
				[
					'host_name' => 'Host for expression macro Max'
				]
			]
		];
	}

	public function writeValuesToItems() {
		// Add values for items.
		$time = time()-100;
		$last_time = time();

		DBexecute("INSERT INTO history (itemid, clock, value, ns) VALUES (".self::$last_itemid.", ".$time.", 2, 0)");
		DBexecute("INSERT INTO history (itemid, clock, value, ns) VALUES (".self::$last_itemid.", ".$last_time.", 4, 0)");

		DBexecute("INSERT INTO history (itemid, clock, value, ns) VALUES (".self::$avg_itemid.", ".$time.", 3, 0)");
		DBexecute("INSERT INTO history (itemid, clock, value, ns) VALUES (".self::$avg_itemid.", ".$last_time.", 5, 0)");

		DBexecute("INSERT INTO history (itemid, clock, value, ns) VALUES (".self::$min_itemid.", ".$time.", 1, 0)");
		DBexecute("INSERT INTO history (itemid, clock, value, ns) VALUES (".self::$min_itemid.", ".$last_time.", 3, 0)");

		DBexecute("INSERT INTO history (itemid, clock, value, ns) VALUES (".self::$max_itemid.", ".$time.", 7, 0)");
		DBexecute("INSERT INTO history (itemid, clock, value, ns) VALUES (".self::$max_itemid.", ".$last_time.", 2, 0)");
	}

	/**
	 * Test for checking expression macro expand in graph names.
	 *
	 * @dataProvider getGraphData
	 */
	public function testExpandExpressionMacros_Graph($data) {
		$this->page->login()->open('zabbix.php?action=host.view&groupids%5B%5D='.self::$hostgroupid)
				->waitUntilReady();
		$table = $this->query('xpath://form[@name="host_view"]/table[@class="list-table"]')->asTable()
				->waitUntilReady()->one();
		$table->findRow('Name', $data['host_name'])->getColumn('Graphs')->query('tag:a')->one()->click();
		$this->page->waitUntilReady();
		$this->waitUntilGraphIsLoaded();
		// TODO: This sleep is added here because of DEV-1908.
		sleep(1);
		$covered_region = [
			'x' => 80,
			'y' => 33,
			'width' => 1144,
			'height' => 279
		];
		$this->assertScreenshotExcept($this->waitUntilGraphIsLoaded(), $covered_region, $data['host_name']);
	}

	/**
	 * Function for waiting loader ring.
	 */
	private function waitUntilGraphIsLoaded() {
		try {
			$this->query('xpath://div[contains(@class,"is-loading")]/img')->waitUntilPresent();
		}
		catch (\Exception $ex) {
			// Code is not missing here.
		}

		return $this->query('xpath://div[not(contains(@class,"is-loading"))]/img')->waitUntilPresent()->one();
	}

	public function prepareMapsData() {
		// Create map with macros in elements names.
		$maps = CDataHelper::call('map.create', [
			[
				'name' => 'Map with expression macros',
				'width' => 500,
				'height' => 500,
				'label_type'=> 0,
				'selements' =>  [
					// Host 'Host for expression macro Avg'.
					[
						'selementid' => '20',
						'elements' => [
							['hostid' => self::$avg_hostid]
						],
						'elementtype' => 0,
						'iconid_off' => '151',
						'label' => '{?avg(/{HOST.HOST}/trapper,1h)}',
						'x' => '139',
						'y' => '27'
					],
					// Image.
					[
						'selementid' => '21',
						'elementtype' => 4,
						'iconid_off' => '6',
						'label' => '{?last(/Host for expression macro Last/trapper)}',
						'x' => '250',
						'y' => '350'
					],
					// Host 'Host for expression macro Min'.
					[
						'selementid' => '22',
						'elements' => [
							['hostid' => self::$min_hostid]
						],
						'elementtype' => 0,
						'iconid_off' => '151',
						'label' => '{?min(/{HOST.HOST}/trapper,1d)}',
						'x' => '89',
						'y' => '377',
						'iconid_off' => '141'
					],
				],
				'links' => [
					// Link between 'Host for expression macro Avg' and 'Host for expression macro Min'.
					[
						'selementid1' => '20',
						'selementid2' => '22',
						'label' => '{?max(/Host for expression macro Max/trapper,1w)}',
					]
				]
			]
		]);
		$this->assertArrayHasKey('sysmapids', $maps);
		self::$mapid = $maps['sysmapids'][0];
	}

	/**
	 * Test for checking expression macro expand in map's elements.
	 */
	public function testExpandExpressionMacros_Map() {
		$this->page->login()->open('zabbix.php?action=map.view&sysmapid='.self::$mapid)->waitUntilReady();
		$map_image = $this->query('xpath://div[@id="flickerfreescreen_mapimg"]/div/*[name()="svg"]')
				->waitUntilPresent()->one();
		$covered_region = [
			'x' => 410,
			'y' => 484,
			'width' => 82,
			'height' => 13
		];
		$this->assertScreenshotExcept($map_image, $covered_region, 'Map with expression macros');
	}

	/**
	 * Delete all created data after test.
	 */
	public static function clearData() {
		// Delete Hosts.
		CDataHelper::call('host.delete', [
				self::$last_hostid,
				self::$avg_hostid,
				self::$max_hostid,
				self::$min_hostid
		]);

		// Delete Host group.
		CDataHelper::call('hostgroup.delete', [
				self::$hostgroupid
		]);

		// Delete Maps.
		CDataHelper::call('map.delete', [
				self::$mapid
		]);
	}
}
