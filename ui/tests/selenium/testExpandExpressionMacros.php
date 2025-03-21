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

require_once __DIR__.'/../include/CWebTest.php';

/**
 * @onBefore prepareItemsData, prepareMapsData, writeValuesToItems
 *
 * @onAfter clearData
 */
class testExpandExpressionMacros extends CWebTest {

		protected static $data = [
			'hostgroupid',
			'hostids' => [
				'last' => null,
				'avg' => null,
				'min' => null,
				'max' => null
			],
			'itemids' => [
				'last' => null,
				'avg' => null,
				'min' => null,
				'max' => null
			],
			'mapid'
		];

	public function prepareItemsData() {
		// Create hostgroup for hosts with items and graphs.
		$hostgroups = CDataHelper::call('hostgroup.create', [
			[
				'name' => 'Group for macro expand testing'
			]
		]);
		$this->assertArrayHasKey('groupids', $hostgroups);
		self::$data['hostgroupid'] = $hostgroups['groupids'][0];

		// Create hosts for items and graphs.
		$hosts_data = [];
		foreach (['Last', 'Avg', 'Min', 'Max'] as $type) {
			$hosts_data[] = [
				'host' => 'Host for expression macro '.$type,
				'groups' => [
					['groupid' => self::$data['hostgroupid']]
				]
			];
		}

		$hosts = CDataHelper::call('host.create', $hosts_data);
		$this->assertArrayHasKey('hostids', $hosts);

		$hostids = CDataHelper::getIds('host');
		self::$data['hostids']['last'] = $hostids['Host for expression macro Last'];
		self::$data['hostids']['avg'] = $hostids['Host for expression macro Avg'];
		self::$data['hostids']['min'] = $hostids['Host for expression macro Min'];
		self::$data['hostids']['max'] = $hostids['Host for expression macro Max'];

		// Create items on previously created hosts.
		$items_data = [];
		foreach ($hostids as $hostid) {
			$items_data[] = [
				'hostid' => $hostid,
				'name' => 'trapper',
				'key_' => 'trapper',
				'type' => 2,
				'value_type' => 0
			];
		}

		$items = CDataHelper::call('item.create', $items_data);

		self::$data['itemids']['last'] = $items['itemids'][0];
		self::$data['itemids']['avg'] = $items['itemids'][1];
		self::$data['itemids']['min'] = $items['itemids'][2];
		self::$data['itemids']['max'] = $items['itemids'][3];

		// Create graphs with expression macros in names.
		$prepared_graphs = [
			[
				'name' => 'Last trapper value: {?last(/Host for expression macro Last/trapper)}',
				'itemid' => self::$data['itemids']['last']
			],
			[
				'name' => 'Avg trapper value: {?avg(/{HOST.HOST}/trapper,1h)}',
				'itemid' => self::$data['itemids']['avg']
			],
			[
				'name' => 'Max trapper value: {?max(/Host for expression macro Min/trapper,1w)}',
				'itemid' => self::$data['itemids']['min']
			],
			[
				'name' => 'Min trapper value: {?min(/{HOST.HOST}/trapper,1d)}',
				'itemid' => self::$data['itemids']['max']
			]
		];

		$graphs_data = [];
		foreach ($prepared_graphs as $graph) {
			$graphs_data[] = [
				'name' => $graph['name'],
				'width' => 900,
				'height' => 200,
				'gitems' => [
					[
						'itemid' => $graph['itemid'],
						'color'=> '00AA00'
					]
				]
			];
		}

		CDataHelper::call('graph.create', $graphs_data);
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
		$time = time() - 100;
		$last_time = time();

		DBexecute("INSERT INTO history (itemid, clock, value, ns) VALUES (".zbx_dbstr(self::$data['itemids']['last']).
				", ".zbx_dbstr($time).", 2, 0)");
		DBexecute("INSERT INTO history (itemid, clock, value, ns) VALUES (".zbx_dbstr(self::$data['itemids']['last']).
				", ".$last_time.", 4, 0)");

		DBexecute("INSERT INTO history (itemid, clock, value, ns) VALUES (".zbx_dbstr(self::$data['itemids']['avg']).
				", ".zbx_dbstr($time).", 3, 0)");
		DBexecute("INSERT INTO history (itemid, clock, value, ns) VALUES (".zbx_dbstr(self::$data['itemids']['avg']).
				", ".zbx_dbstr($last_time).", 5, 0)");

		DBexecute("INSERT INTO history (itemid, clock, value, ns) VALUES (".zbx_dbstr(self::$data['itemids']['min']).
				", ".zbx_dbstr($time).", 1, 0)");
		DBexecute("INSERT INTO history (itemid, clock, value, ns) VALUES (".zbx_dbstr(self::$data['itemids']['min']).
				", ".zbx_dbstr($last_time).", 3, 0)");

		DBexecute("INSERT INTO history (itemid, clock, value, ns) VALUES (".zbx_dbstr(self::$data['itemids']['max']).
				", ".zbx_dbstr($time).", 7, 0)");
		DBexecute("INSERT INTO history (itemid, clock, value, ns) VALUES (".zbx_dbstr(self::$data['itemids']['max']).
				", ".zbx_dbstr($last_time).", 2, 0)");
	}

	/**
	 * Test for checking expression macro expand in graph names.
	 *
	 * @dataProvider getGraphData
	 */
	public function testExpandExpressionMacros_Graph($data) {
		$this->page->login()->open('zabbix.php?action=host.view&groupids%5B%5D='.self::$data['hostgroupid'])
				->waitUntilReady();
		$table = $this->query('xpath://form[@name="host_view"]/table[@class="list-table"]')->asTable()
				->waitUntilReady()->one();
		$table->findRow('Name', $data['host_name'])->getColumn('Graphs')->query('tag:a')->one()->click();
		$this->page->waitUntilReady();
		$this->waitUntilGraphIsLoaded();
		// TODO: This sleep is added here because of DEV-1908.
		sleep(1);
		$covered_region = [
			'x' => 78,
			'y' => 33,
			'width' => 1144,
			'height' => 305
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
						'selementid' => 20,
						'elements' => [
							['hostid' => self::$data['hostids']['avg']]
						],
						'elementtype' => 0,
						'iconid_off' => 151,
						'label' => '{?avg(/{HOST.HOST}/trapper,1h)}',
						'x' => 139,
						'y' => 27
					],
					// Image.
					[
						'selementid' => 21,
						'elementtype' => 4,
						'iconid_off' => 6,
						'label' => '{?last(/Host for expression macro Last/trapper)}',
						'x' => 250,
						'y' => 350
					],
					// Host 'Host for expression macro Min'.
					[
						'selementid' => 22,
						'elements' => [
							['hostid' => self::$data['hostids']['min']]
						],
						'elementtype' => 0,
						'iconid_off' => 141,
						'label' => '{?min(/{HOST.HOST}/trapper,1d)}',
						'x' => 89,
						'y' => 377
					]
				],
				'links' => [
					// Link between 'Host for expression macro Avg' and 'Host for expression macro Min'.
					[
						'selementid1' => 20,
						'selementid2' => 22,
						'label' => '{?max(/Host for expression macro Max/trapper,1w)}'
					]
				]
			]
		]);
		$this->assertArrayHasKey('sysmapids', $maps);
		self::$data['mapid'] = $maps['sysmapids'][0];
	}

	/**
	 * Test for checking expression macro expand in map's elements.
	 */
	public function testExpandExpressionMacros_Map() {
		// Open map in view mode.
		$this->page->login()->open('zabbix.php?action=map.view&sysmapid='.self::$data['mapid'])->waitUntilReady();
		$map_image = $this->query('xpath://div[@id="flickerfreescreen_mapimg"]/div/*[name()="svg"]')
				->waitUntilPresent()->one();
		$covered_region = [
			'x' => 365,
			'y' => 484,
			'width' => 145,
			'height' => 13
		];
		$this->assertScreenshotExcept($map_image, $covered_region, 'Map with expression macros');

		// Open map in edit mode.
		$this->query('button:Edit map')->waitUntilClickable()->one()->click();
		$this->page->waitUntilReady();

		// Expand macros is off by default.
		$this->assertTrue($this->query('xpath://button[@id="expand_macros" and text() = "Off"]')
				->waitUntilVisible()->exists()
		);
		$map_edited = $this->query('id:map-area')->waitUntilPresent()->one();
		$this->assertScreenshot($map_edited, 'Edited map macros OFF');

		// Turn expanding macros on.
		$this->query('id:expand_macros')->waitUntilClickable()->one()->click();
		$this->assertTrue($this->query('xpath://button[@id="expand_macros" and text() = "On"]')
				->waitUntilVisible()->exists()
		);
		$this->assertScreenshot($map_edited, 'Edited map macros ON');
	}

	/**
	 * Delete all created data after test.
	 */
	public static function clearData() {
		// Delete Hosts.
		CDataHelper::call('host.delete', [
				self::$data['hostids']['last'],
				self::$data['hostids']['avg'],
				self::$data['hostids']['min'],
				self::$data['hostids']['max']
		]);

		// Delete Host group.
		CDataHelper::call('hostgroup.delete', [
				self::$data['hostgroupid']
		]);

		// Delete Maps.
		CDataHelper::call('map.delete', [
				self::$data['mapid']
		]);
	}
}
