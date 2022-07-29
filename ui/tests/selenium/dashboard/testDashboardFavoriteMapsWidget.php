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

require_once dirname(__FILE__).'/../../include/CLegacyWebTest.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup profiles
 *
 * @onBefore prepareDashboardData
 */
class testDashboardFavoriteMapsWidget extends CLegacyWebTest {

	protected static $dashboardid;
	public $mapTest = 'Test map 1';
	public $mapTestId = 3;

	public static function prepareDashboardData() {
		$response = CDataHelper::call('dashboard.create', [
			[
				'name' => 'Dashboard with favorite maps widget',
				'private' => 1,
				'pages' => [
					[
						'name' => 'Page 1',
						'widgets' => [
							[
								'type' => 'favmaps',
								'x' => 13,
								'y' => 0,
								'width' => 4,
								'height' => 4
							]
						]
					]
				]
			]
		]);
		self::$dashboardid = $response['dashboardids'][0];
	}

	public function testDashboardFavoriteMapsWidget_AddFavouriteMap() {
		$this->zbxTestLogin('sysmaps.php');
		$this->zbxTestCheckHeader('Maps');
		$this->zbxTestClickLinkTextWait($this->mapTest);
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//button[@id='addrm_fav']"));
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Add to favorites');
		$this->zbxTestClickWait('addrm_fav');
		$this->query('id:addrm_fav')->one()->waitUntilAttributesPresent(['title' => 'Remove from favorites']);
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Remove from favorites');

		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$this->zbxTestAssertElementText('//div[@class="dashboard-grid-widget-container"]/div[2]//a[@href="zabbix.php?action=map.view&sysmapid='.$this->mapTestId.'"]', $this->mapTest);
		$this->assertEquals(1, CDBHelper::getCount('SELECT profileid FROM profiles WHERE idx='.zbx_dbstr('web.favorite.sysmapids').' AND value_id='.$this->mapTestId));
	}

	public function testDashboardFavoriteMapsWidget_RemoveFavouriteMaps() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid='.self::$dashboardid)->waitUntilReady();
		$widget_content = CDashboardElement::find()->one()->getWidget('Favorite maps')->getContent();
		$favourite_maps = DBfetchArray(DBselect('SELECT value_id FROM profiles WHERE idx='.
				zbx_dbstr('web.favorite.sysmapids')));

		foreach ($favourite_maps as $favourite_map) {
			$remove_item = $this->query('xpath://button[@data-sysmapid='.zbx_dbstr($favourite_map['value_id']).
					' and contains(@onclick, "rm4favorites")]')->waituntilClickable()->one();
			$remove_item->click();
			$remove_item->waitUntilNotVisible();
		}

		$this->assertTrue($widget_content->query('xpath://table//td[text()="No maps added."]')->waitUntilVisible()
				->one()->isPresent());
		$this->assertEquals(0, CDBHelper::getCount('SELECT profileid FROM profiles WHERE idx='.
				zbx_dbstr('web.favorite.sysmapids')));
	}
}
