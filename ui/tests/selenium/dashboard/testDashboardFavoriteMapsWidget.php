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
 */
class testDashboardFavoriteMapsWidget extends CLegacyWebTest {

	public $mapTest = 'Test map 1';
	public $mapTestId = 3;

	public function testDashboardFavoriteMapsWidget_AddFavouriteMap() {
		$this->zbxTestLogin('zabbix.php?action=dashboard.view&dashboardid=1');
		$FavouriteScreens = DBfetchArray(DBselect("SELECT value_id FROM profiles WHERE idx='web.favorite.sysmapids'"));
		foreach ($FavouriteScreens as $FavouriteScreen) {
			$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath("//div[@class='dashbrd-grid-container']/div[8]//button[@onclick=\"rm4favorites('sysmapid','".$FavouriteScreen['value_id']."')\"]"));
			$this->zbxTestClickXpathWait("//div[@class='dashbrd-grid-container']/div[8]//button[@onclick=\"rm4favorites('sysmapid','".$FavouriteScreen['value_id']."')\"]");
			$this->zbxTestWaitUntilElementNotVisible(WebDriverBy::xpath("//div[@class='dashbrd-grid-container']/div[8]//button[@onclick=\"rm4favorites('sysmapid','".$FavouriteScreen['value_id']."')\"]"));
		}
		$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[8]//tr[@class='nothing-to-show']/td", 'No maps added.');
		$this->assertEquals(0, CDBHelper::getCount("SELECT profileid FROM profiles WHERE idx='web.favorite.sysmapids'"));
	}

	public function testDashboardFavoriteMapsWidget_RemoveFavouriteMaps() {
		$this->zbxTestLogin('zabbix.php?action=dashboard.view&dashboardid=1');
		$FavouriteScreens = DBfetchArray(DBselect("SELECT value_id FROM profiles WHERE idx='web.favorite.sysmapids'"));
		foreach ($FavouriteScreens as $FavouriteScreen) {
			$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath("//div[@class='dashbrd-grid-container']/div[8]//button[@onclick=\"rm4favorites('sysmapid','".$FavouriteScreen['value_id']."')\"]"));
			$this->zbxTestClickXpathWait("//div[@class='dashbrd-grid-container']/div[8]//button[@onclick=\"rm4favorites('sysmapid','".$FavouriteScreen['value_id']."')\"]");
			$this->zbxTestWaitUntilElementNotVisible(WebDriverBy::xpath("//div[@class='dashbrd-grid-container']/div[8]//button[@onclick=\"rm4favorites('sysmapid','".$FavouriteScreen['value_id']."')\"]"));
		}
		$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[8]//tr[@class='nothing-to-show']/td", 'No maps added.');
		$this->assertEquals(0, CDBHelper::getCount("SELECT profileid FROM profiles WHERE idx='web.favorite.sysmapids'"));
	}
}
