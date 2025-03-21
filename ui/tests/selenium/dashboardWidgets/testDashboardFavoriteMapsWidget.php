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


require_once __DIR__.'/../../include/CWebTest.php';

/**
 * @backup profiles
 *
 * @onBefore prepareDashboardData
 */
class testDashboardFavoriteMapsWidget extends CWebTest {

	const MAP_NAME = 'Test map for favourite widget';

	protected static $dashboardid;
	protected static $mapid;

	public static function prepareDashboardData() {
		$dashboards = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard with favorite maps widget',
				'private' => 1,
				'pages' => [
					[
						'widgets' => [
							[
								'type' => 'favmaps',
								'x' => 0,
								'y' => 0,
								'width' => 12,
								'height' => 4
							]
						]
					]
				]
			]
		]);
		self::$dashboardid = $dashboards['dashboardids'][0];

		$hosts = CDataHelper::call('host.create', [
			[
				'host' => 'Map host',
				'groups' => ['groupid' => 4] // Zabbix servers.
			]
		]);

		$maps = CDataHelper::call('map.create', [
			[
				'name' => self::MAP_NAME,
				'width' => 500,
				'height' => 500,
				'selements' => [
					[
						'elements' => [['hostid' => $hosts['hostids'][0]]],
						'elementtype' => SYSMAP_ELEMENT_TYPE_HOST,
						'iconid_off' => 186
					]
				]
			]
		]);
		self::$mapid = $maps['sysmapids'][0];
	}

	public function testDashboardFavoriteMapsWidget_AddFavoriteMap() {
		$this->page->login()->open('sysmaps.php')->waitUntilReady();
		$this->page->assertHeader('Maps');
		$this->query('link', self::MAP_NAME)->waitUntilClickable()->one()->click();

		$this->page->waitUntilReady();
		$button = $this->query('xpath://button[@id="addrm_fav"]')->waitUntilVisible()->one();
		$this->assertEquals('Add to favorites', $button->getAttribute('title'));
		$button->waitUntilClickable()->click();
		$this->query('id:addrm_fav')->one()->waitUntilAttributesPresent(['title' => 'Remove from favorites']);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$widget = CDashboardElement::find()->one()->getWidget('Favorite maps')->waitUntilReady()->getContent();
		$this->assertEquals('zabbix.php?action=map.view&sysmapid='.self::$mapid,
				$widget->query('link', self::MAP_NAME)->one()->getAttribute('href')
		);
		$this->assertEquals(1, CDBHelper::getCount('SELECT profileid FROM profiles WHERE idx='.
				zbx_dbstr('web.favorite.sysmapids').' AND value_id='.zbx_dbstr(self::$mapid))
		);
	}

	public function testDashboardFavoriteMapsWidget_RemoveFavoriteMaps() {
		$favorite_maps = CDBHelper::getAll('SELECT value_id FROM profiles WHERE idx='.zbx_dbstr('web.favorite.sysmapids'));

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$widget = CDashboardElement::find()->one()->getWidget('Favorite maps')->getContent();

		foreach ($favorite_maps as $map) {
			// Added variable due to External Hook.
			$xpath = './/button[@data-sysmapid='.CXPathHelper::escapeQuotes($map['value_id']);
			$remove_item = $widget->query('xpath', $xpath.' and contains(@onclick, "rm4favorites")]')->waituntilClickable()->one();
			$remove_item->click();
			$remove_item->waitUntilNotVisible();
		}

		$this->assertEquals('No maps added.', $widget->query('class:no-data-message')->waitUntilVisible()->one()->getText());
		$this->assertEquals(0, CDBHelper::getCount('SELECT profileid FROM profiles WHERE idx='.
				zbx_dbstr('web.favorite.sysmapids'))
		);
	}
}
