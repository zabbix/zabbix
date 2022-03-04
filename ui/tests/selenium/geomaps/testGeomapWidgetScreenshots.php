<?php
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
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
require_once dirname(__FILE__).'/../../include/helpers/CDataHelper.php';

/**
 * @backup config, widget, hosts
 *
 * @onBefore prepareZoomData
 *
 * This annotation is needed because some tile providers produce errors like:
 * https://stamen-tiles-b.a.ssl.fastly.net/terrain/18/148702/80340.png -
 * Failed to load resource: the server responded with a status of 404 (Not Found)
 * @ignoreBrowserErrors
 */
class testGeomapWidgetScreenshots extends CWebTest {

	// Dashboard for zoom screenshot tests.
	protected static $zoom_dashboardid;

	// Host data for zoom screenshot tests.
	protected static $cities = [
			'hostgroupid',
			'hostids' => [
				'Riga' => null,
				'Tallin' => null,
				'Vilnius' => null,
				'Oslo' => null,
				'Bergen' => null
			],
			'itemids' => [
				'Riga' => null,
				'Tallin' => null,
				'Vilnius' => null,
				'Oslo' => null,
				'Bergen' => null
			],
			'triggerids' => [
				'Riga' => null,
				'Tallin' => null,
				'Vilnius' => null,
				'Oslo' => null,
				'Bergen' => null
			]
		];

	public function prepareZoomData() {
		// Create hostgroup for hosts with items triggers.
		$hostgroups = CDataHelper::call('hostgroup.create', [['name' => 'Baltics']]);
		$this->assertArrayHasKey('groupids', $hostgroups);
		self::$cities['hostgroupid'] = $hostgroups['groupids'][0];

		// Create hosts for items and triggers.
		$cities_location = [
			['name' => 'Riga', 'latitude' => '56.9546328976717', 'longitude' => '24.1207979437706'],
			['name' => 'Tallin', 'latitude' => '59.4349125678522', 'longitude' => '24.7568789765827'],
			['name' => 'Vilnius', 'latitude' => '54.6879298114432', 'longitude' => '25.2793571402776'],
			['name' => 'Oslo', 'latitude' => '59.9161327671058', 'longitude' => '10.7554327978315'],
			['name' => 'Bergen', 'latitude' => '60.3995117455505', 'longitude' => '5.20166836521941']
		];

		$hosts_data = [];
		foreach ($cities_location as $city) {
				$hosts_data[] = [
					'host' => $city['name'],
					'groups' => [['groupid' => self::$cities['hostgroupid']]],
					'inventory_mode' => 0,
					'inventory' => ['location_lat' => $city['latitude'], 'location_lon' => $city['longitude']]
				];
		}

		$hosts = CDataHelper::call('host.create', $hosts_data);
		$this->assertArrayHasKey('hostids', $hosts);

		$hostids = CDataHelper::getIds('host');
		self::$cities['hostids']['Riga'] = $hostids['Riga'];
		self::$cities['hostids']['Tallin'] = $hostids['Tallin'];
		self::$cities['hostids']['Vilnius'] = $hostids['Vilnius'];
		self::$cities['hostids']['Oslo'] = $hostids['Oslo'];
		self::$cities['hostids']['Bergen'] = $hostids['Bergen'];

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

		self::$cities['itemids']['Riga'] = $items['itemids'][0];
		self::$cities['itemids']['Tallin'] = $items['itemids'][1];
		self::$cities['itemids']['Vilnius'] = $items['itemids'][2];
		self::$cities['itemids']['Oslo'] = $items['itemids'][3];
		self::$cities['itemids']['Bergen'] = $items['itemids'][4];

		// Create triggers based on items.
		$triggers_data = [];
		foreach ($cities_location as $i => $city) {
			$triggers_data[] = [
				'description' => 'Trigger '.$city['name'],
				'expression' => 'last(/'.$city['name'].'/trapper)=0',
				'priority' => $i
			];
		}

		$triggers = CDataHelper::call('trigger.create', $triggers_data);

		self::$cities['triggerids']['Riga'] = $triggers['triggerids'][0];
		self::$cities['triggerids']['Tallin'] = $triggers['triggerids'][1];
		self::$cities['triggerids']['Vilnius'] = $triggers['triggerids'][2];
		self::$cities['triggerids']['Oslo'] = $triggers['triggerids'][3];
		self::$cities['triggerids']['Bergen'] = $triggers['triggerids'][4];

		// Create dashboard with geomap widgets.
		$dashboards = CDataHelper::call('dashboard.create', [
			'name' => 'Geomap zoom widget dashboard',
			'auto_start' => 0,
			'pages' => [
				[
					'name' => 'First Page',
					'display_period' => 3600,
					'widgets' => [
						[
							'type' => 'geomap',
							'name' => 'Geomap for screenshots, 5',
							'x' => 0,
							'y' => 0,
							'width' => 13,
							'height' => 7,
							'view_mode' => 0,
							'fields' => [
								[
									'type' => '2',
									'name' => 'groupids',
									'value' => self::$cities['hostgroupid']
								],
								[
									'type' => '1',
									'name' => 'default_view',
									'value' => '56.97778948828843, 24.211604679275183,5'
								]
							]
						],
						[
							'type' => 'geomap',
							'name' => 'Geomap for screenshots, 10',
							'x' => 13,
							'y' => 0,
							'width' => 11,
							'height' => 7,
							'view_mode' => 0,
							'fields' => [
								[
									'type' => '2',
									'name' => 'groupids',
									'value' => self::$cities['hostgroupid']
								],
								[
									'type' => '1',
									'name' => 'default_view',
									'value' => '56.97778948828843, 24.211604679275183,10'
								]
							]
						],
						[
							'type' => 'geomap',
							'name' => 'Geomap for screenshots, 30',
							'x' => 0,
							'y' => 7,
							'width' => 13,
							'height' => 7,
							'view_mode' => 0,
							'fields' => [
								[
									'type' => '2',
									'name' => 'groupids',
									'value' => self::$cities['hostgroupid']
								],
								[
									'type' => '1',
									'name' => 'default_view',
									'value' => '56.97778948828843, 24.211604679275183,30'
								]
							]
						],
						[
							'type' => 'geomap',
							'name' => 'Geomap for screenshots, no zoom',
							'x' => 13,
							'y' => 7,
							'width' => 11,
							'height' => 7,
							'view_mode' => 0,
							'fields' => [
								[
									'type' => '2',
									'name' => 'groupids',
									'value' => self::$cities['hostgroupid']
								]
							]
						],
						[
							'type' => 'geomap',
							'name' => 'Geomap for screenshots, 3',
							'x' => 0,
							'y' => 14,
							'width' => 13,
							'height' => 7,
							'view_mode' => 0,
							'fields' => [
								[
									'type' => '2',
									'name' => 'groupids',
									'value' => self::$cities['hostgroupid']
								],
								[
									'type' => '1',
									'name' => 'default_view',
									'value' => '56.97778948828843, 24.211604679275183,3'
								]
							]
						]
					]
				]
			]
		]);

		$this->assertArrayHasKey('dashboardids', $dashboards);
		self::$zoom_dashboardid = $dashboards['dashboardids'][0];
	}

	public static function getZoomWidgetData() {
		return [
			// Some providers are commented, because images on screenshots are not stable even after 20 seconds sleep.
//			[
//				[
//					'Tile provider' => 'OpenTopoMap'
//				]
//			],
//			[
//				[
//					'Tile provider' => 'OpenStreetMap Mapnik'
//				]
//			],
			[
				[
					'Tile provider' => 'Stamen Toner Lite'
				]
			],
			[
				[
					'Tile provider' => 'Stamen Terrain'
				]
			],
//			[
//				[
//					'Tile provider' => 'USGS US Topo'
//				]
//			],
//			[
//				[
//					'Tile provider' => 'USGS US Imagery'
//				]
//			],
			[
				[
					'Tile provider' => 'Other',
					'Tile URL' => 'https://tileserver.memomaps.de/tilegen/{z}/{x}/{y}.png',
					'Attribution' => 'Map <a href="https://memomaps.de/">memomaps.de</a> '.
							'<a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, '.
							'map data &copy; <a href="https://www.openstreetmap.org/copyright">'.
							'OpenStreetMap</a> contributors',
					'Max zoom level' => '30'
				]
			]
		];
	}


	/**
	 * Function for creating problems in DB for hosts being screenshoted.
	 */
	public static function pepareProblemsData() {
		// Create events.
		DBexecute('INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (10500, 0, 0, '.
				zbx_dbstr(self::$cities['triggerids']['Riga']).', '.zbx_dbstr(time()).', 0, 1, '.zbx_dbstr('Trigger Riga').', 0)');
		DBexecute('INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (10501, 0, 0, '.
				zbx_dbstr(self::$cities['triggerids']['Tallin']).', '.zbx_dbstr(time()).', 0, 1, '.zbx_dbstr('Trigger Tallin').', 1)');
		DBexecute('INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (10502, 0, 0, '.
				zbx_dbstr(self::$cities['triggerids']['Vilnius']).', '.zbx_dbstr(time()).', 0, 1, '.zbx_dbstr('Trigger Vilnius').', 2)');
		DBexecute('INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (10503, 0, 0, '.
				zbx_dbstr(self::$cities['triggerids']['Oslo']).', '.zbx_dbstr(time()).', 0, 1, '.zbx_dbstr('Trigger Oslo').', 3)');
		DBexecute('INSERT INTO events (eventid, source, object, objectid, clock, ns, value, name, severity) VALUES (10504, 0, 0, '.
				zbx_dbstr(self::$cities['triggerids']['Bergen']).', '.zbx_dbstr(time()).', 0, 1, '.zbx_dbstr('Trigger Bergen').', 4)');

		// Create problems.
		DBexecute('INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES (10500, 0, 0, '.
				zbx_dbstr(self::$cities['triggerids']['Riga']).', '.zbx_dbstr(time()).', 0, '.zbx_dbstr('Trigger Riga').', 0)');
		DBexecute('INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES (10501, 0, 0, '.
				zbx_dbstr(self::$cities['triggerids']['Tallin']).', '.zbx_dbstr(time()).', 0, '.zbx_dbstr('Trigger Tallin').', 1)');
		DBexecute('INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES (10502, 0, 0, '.
				zbx_dbstr(self::$cities['triggerids']['Vilnius']).', '.zbx_dbstr(time()).', 0, '.zbx_dbstr('Trigger Vilnius').', 2)');
		DBexecute('INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES (10503, 0, 0, '.
				zbx_dbstr(self::$cities['triggerids']['Oslo']).', '.zbx_dbstr(time()).', 0, '.zbx_dbstr('Trigger Oslo').', 3)');
		DBexecute('INSERT INTO problem (eventid, source, object, objectid, clock, ns, name, severity) VALUES (10504, 0, 0, '.
				zbx_dbstr(self::$cities['triggerids']['Bergen']).', '.zbx_dbstr(time()).', 0, '.zbx_dbstr('Trigger Bergen').', 4)');

		// Change triggers' state to Problem.
		DBexecute('UPDATE triggers SET value = 1 WHERE description = '.zbx_dbstr('Trigger Riga'));
		DBexecute('UPDATE triggers SET value = 1 WHERE description = '.zbx_dbstr('Trigger Tallin'));
		DBexecute('UPDATE triggers SET value = 1 WHERE description = '.zbx_dbstr('Trigger Vilnius'));
		DBexecute('UPDATE triggers SET value = 1 WHERE description = '.zbx_dbstr('Trigger Oslo'));
		DBexecute('UPDATE triggers SET value = 1 WHERE description = '.zbx_dbstr('Trigger Bergen'));
	}

	/**
	 * @onBeforeOnce pepareProblemsData
	 *
	 * @dataProvider getZoomWidgetData
	 */
	public function testGeomapWidgetScreenshots_Zoom($data) {
		$this->page->login()->open('zabbix.php?action=geomaps.edit');
		$this->page->waitUntilReady();

		$form = $this->query('id:geomaps-form')->asForm()->one();
		$form->fill($data);
		$form->submit();

		$this->page->open('zabbix.php?action=dashboard.view&dashboardid='.self::$zoom_dashboardid);
		$this->page->waitUntilReady();

		$widgets = [
			'Geomap for screenshots, 5',
			'Geomap for screenshots, 10',
			'Geomap for screenshots, 30',
			'Geomap for screenshots, no zoom',
			'Geomap for screenshots, 3'
		];
		foreach ($widgets as $widget) {
			// Wait until loader disappears.
			$this->query("xpath://h4[text()=".CXPathHelper::escapeQuotes($widget).
					"]/../../div[not(contains(@class,\"is-loading\"))]")->waitUntilPresent()->one();
		}

		// Additional 2 seconds for loading sequence to settle.
		sleep(2);
		// Script for waiting until tile providers are loaded.
		CElementQuery::wait(40)->until(function () {
			return CElementQuery::getDriver()->executeScript(
					'var widgets = ZABBIX.Dashboard._dashboard_pages.keys().next().value._widgets;'.
					'var result = true;'.
					'widgets.forEach(function(_, widget) {'.
					'   var layers = widget._map._layers;'.
					'   var keys = Object.keys(layers);'.
					'   for (var i = 0; i < keys.length; i++) {'.
					'       if (typeof layers[keys[i]]._url === "undefined") {'.
					'           continue;'.
					'       }'.
					'       result &= !layers[keys[i]]._loading;'.
					'   }'.
					'});'.
					'return result;'
			);
		});

		foreach ($widgets as $widget) {
			$id = $widget.' '.$data['Tile provider'];
			$element = $this->query("xpath://div[@class=\"dashboard-grid-widget\"]//h4[text()=".
					CXPathHelper::escapeQuotes($widget)."]/../..")->waitUntilVisible()->one();

			try {
				$this->assertScreenshot($element, $id);
			}
			catch (Exception $e) {
				sleep(3);
				$this->assertScreenshot($element, $id);
			}
		}
	}
}
