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
require_once dirname(__FILE__).'/../behaviors/CMessageBehavior.php';

/**
 * @backup config, widget, hosts
 *
 * @onBefore prepareZoomData
 */
class testGeomapWidgetScreenshots extends CWebTest {

	// Dasboard for zoom screenshot tests.
	protected static $zoom_dashboardid;

	// Host data for zoom screenshot tests.
	protected static $cities = [
			'hostgroupid',
			'hostids' => [
				'Riga' => null,
				'Tallin' => null,
				'Vilnius' => null
			],
			'itemids' => [
				'Riga' => null,
				'Tallin' => null,
				'Vilnius' => null
			],
			'triggerids' => [
				'Riga' => null,
				'Tallin' => null,
				'Vilnius' => null
			]
		];

	public function prepareZoomData() {
		// Create hostgroup for hosts with items triggers.
		$hostgroups = CDataHelper::call('hostgroup.create', [['name' => 'Baltics']]);
		$this->assertArrayHasKey('groupids', $hostgroups);
		self::$cities['hostgroupid'] = $hostgroups['groupids'][0];

		// Create hosts for items and triggers.
		$cities_names = [
			'Riga' => ['latitude' => '56.9546328976717', 'longitude' => '24.1207979437706'],
			'Tallin' => ['latitude' => '59.4349125678522', 'longitude' => '24.7568789765827'],
			'Vilnius' => ['latitude' => '54.6879298114432', 'longitude' => '25.2793571402776']
		];

		$hosts_data = [];
		foreach ($cities_names as $name => $location) {
				$hosts_data[] = [
					'host' => $name,
					'groups' => [['groupid' => self::$cities['hostgroupid']]],
					'inventory_mode' => 0,
					'inventory' => ['location_lat' => $location['latitude'], 'location_lon' => $location['longitude']],
				];
		}

		$hosts = CDataHelper::call('host.create', $hosts_data);
		$this->assertArrayHasKey('hostids', $hosts);

		$hostids = CDataHelper::getIds('host');
		self::$cities['hostids']['Riga'] = $hostids['Riga'];
		self::$cities['hostids']['Tallin'] = $hostids['Tallin'];
		self::$cities['hostids']['Vilnius'] = $hostids['Vilnius'];

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

		// Create triggers based on items.
		$triggers_data = [];
		foreach ($cities_names as $host => $location) {
			$triggers_data[] = [
				'description' => 'Trigger '.$host,
				'expression' => 'last(/'.$host.'/trapper)=0',
			];
		}

		$triggers = CDataHelper::call('trigger.create', $triggers_data);

		self::$cities['triggerids']['Riga'] = $triggers['triggerids'][0];
		self::$cities['triggerids']['Tallin'] = $triggers['triggerids'][1];
		self::$cities['triggerids']['Vilnius'] = $triggers['triggerids'][2];

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
			[
				[
					'Tile provider' => 'default'
				]
			],
			[
				[
					'Tile provider' => 'OpenStreetMap Mapnik'
				]
			],
			[
				[
					'Tile provider' => 'OpenTopoMap'
				]
			],
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
			[
				[
					'Tile provider' => 'USGS US Topo'
				]
			],
			[
				[
					'Tile provider' => 'USGS US Imagery'
				]
			],
			[
				[
					'Tile provider' => 'Other',
					'Tile URL' => 'https://tileserver.memomaps.de/tilegen/{z}/{x}/{y}.png',
					'Attribution' => 'Map <a href="https://memomaps.de/">memomaps.de</a> '.
							'<a href="http://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, '.
							'map data &copy; <a href="https://www.openstreetmap.org/copyright">'.
							'OpenStreetMap</a> contributors',
					'Max zoom level' => '13'
				]
			]
		];
	}

	/**
	 * @dataProvider getZoomWidgetData
	 */
	public function testGeomapWidgetScreenshots_Zoom($data) {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$zoom_dashboardid);
		$this->page->waitUntilReady();
		$this->query("xpath://div[contains(@class,\"is-loading\")]/..//h4")->all()->waitUntilNotPresent();

		// This sleep is needed because after loader ring disappeared map image needs to load anyway.
		sleep(1);

		if ($data['Tile provider'] === 'default') {
			$this->assertWidgetScreenshot($data);
		}
		else {
			$this->page->open('zabbix.php?action=geomaps.edit');
			$form = $this->query('id:geomaps-form')->waitUntilReady()->asForm()->one();
			$form->fill($data);
			$form->submit();

			$this->page->open('zabbix.php?action=dashboard.view&dashboardid='.self::$zoom_dashboardid);
			$this->page->waitUntilReady();
			$this->query("xpath://div[contains(@class,\"is-loading\")]/..//h4")->all()->waitUntilNotPresent();

			// This sleep is needed because after loader ring disappeared map image needs to load anyway.
			sleep(1);
			$this->assertWidgetScreenshot($data);
		}
	}

	private function assertWidgetScreenshot($data) {
		$widgets = [
			'Geomap for screenshots, 5',
			'Geomap for screenshots, 10',
			'Geomap for screenshots, 30',
			'Geomap for screenshots, no zoom'
		];

		foreach ($widgets as $widget) {
			$this->assertScreenshot($this->query("xpath:.//div[@class=\"dashboard-grid-widget\"]//h4[text()=".
					CXPathHelper::escapeQuotes($widget)."]/../..")->waitUntilVisible()->one(), $widget.' '.$data['Tile provider']
			);
		}
	}
}
