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

/**
 * @backup profiles
 *
 * @onBefore prepareDashboardData
 */
class testDashboardFavoriteMapsWidget extends CWebTest {

	protected static $dashboardid;
	public $map_test = 'Test map 1';
	public $mapid = 3;

	public static function prepareDashboardData() {
		$response = CDataHelper::call('dashboard.create', [
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
		self::$dashboardid = $response['dashboardids'][0];
	}

	public function testDashboardFavoriteMapsWidget_AddFavoriteMap() {
		$this->page->login()->open('sysmaps.php')->waitUntilReady();
		$this->page->assertHeader('Maps');
		$this->query('link', $this->map_test)->waitUntilClickable()->one()->click();

		$this->page->waitUntilReady();
		$button = $this->query('xpath://button[@id="addrm_fav"]')->waitUntilVisible()->one();
		$this->assertEquals('Add to favorites', $button->getAttribute('title'));
		$button->waitUntilClickable()->click();
		$this->query('id:addrm_fav')->one()->waitUntilAttributesPresent(['title' => 'Remove from favorites']);

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$widget = CDashboardElement::find()->one()->getWidget('Favorite maps')->waitUntilReady()->getContent();
		$this->assertEquals('zabbix.php?action=map.view&sysmapid='.$this->mapid,
				$widget->query('link', $this->map_test)->one()->getAttribute('href')
		);
		$this->assertEquals(1, CDBHelper::getCount('SELECT profileid FROM profiles WHERE idx='.
				zbx_dbstr('web.favorite.sysmapids').' AND value_id='.zbx_dbstr($this->mapid))
		);
	}

	public function testDashboardFavoriteMapsWidget_RemoveFavoriteMaps() {
		$favorite_maps = CDBHelper::getAll('SELECT value_id FROM profiles WHERE idx='.zbx_dbstr('web.favorite.sysmapids'));

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$widget = CDashboardElement::find()->one()->getWidget('Favorite maps')->getContent();

		foreach ($favorite_maps as $map) {
			// Added variable due to External Hook.
			$xpath = ".//button[@onclick=\"rm4favorites('sysmapid','".$map['value_id'];
			$remove_item = $widget->query('xpath', $xpath."')\"]")->waituntilClickable()->one();
			$remove_item->click();
			$remove_item->waitUntilNotVisible();
		}

		$this->assertTrue($widget->query('xpath:.//td[text()="No maps added."]')->waitUntilVisible()->one()->isPresent());
		$this->assertEquals(0, CDBHelper::getCount('SELECT profileid FROM profiles WHERE idx='.
				zbx_dbstr('web.favorite.sysmapids'))
		);
	}
}
