<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/class.cwebtest.php';

class testPageDashboard extends CWebTest {

	public $graphCpu = 'CPU load';
	public $graphCpuId = 524;
	public $graphMemory = 'Memory usage';
	public $graphMemoryId = 534;
	public $screenClock = 'Test screen (clock)';
	public $screenClockId = 200001;
	public $mapTest = 'Test map 1';
	public $mapTestId = 3;

	public function testPageDashboard_CheckLayoutForDifferentUsers() {
		$users = ['super-admin', 'admin', 'user', 'guest'];
		foreach ($users as $user) {
			switch ($user) {
				case 'super-admin' :
					$this->zbxTestLogin('zabbix.php?action=dashboard.view');
					$this->zbxTestCheckTitle('Dashboard');
					$this->zbxTestCheckHeader('Dashboard');
					$this->zbxTestCheckNoRealHostnames();
					break;
				case 'admin' :
					$this->authenticateUser('09e7d4286dfdca4ba7be15e0f3b2b55c', 4);
					$this->zbxTestOpen('zabbix.php?action=dashboard.view');
					$this->zbxTestCheckTitle('Dashboard');
					$this->zbxTestCheckHeader('Dashboard');
					$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-widget-container']/div[8]//a[@href='zabbix.php?action=discovery.view&druleid=3']", 'External network');
					break;
				case 'user';
					$this->authenticateUser('09e7d4286dfdca4ba7be15e0f3b2b55d', 5);
					$this->zbxTestOpen('zabbix.php?action=dashboard.view');
					$this->zbxTestCheckTitle('Dashboard');
					$this->zbxTestCheckHeader('Dashboard');
					$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-widget-container']/div[8]//tr[@class='nothing-to-show']/td", 'No permissions to referred object or it does not exist!');
					break;
				case 'guest';
					$this->authenticateUser('09e7d4286dfdca4ba7be15e0f3b2b55e', 2);
					$this->zbxTestOpen('zabbix.php?action=dashboard.view');
					$this->zbxTestCheckTitle('Dashboard');
					$this->zbxTestCheckHeader('Dashboard');
					$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-widget-container']/div[8]//tr[@class='nothing-to-show']/td", 'No permissions to referred object or it does not exist!');
					break;
			}
			if ($user != 'super-admin'){
				$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-widget-container']/div[3]//tr[@class='nothing-to-show']/td", 'No graphs added.');
				$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-widget-container']/div[4]//tr[@class='nothing-to-show']/td", 'No screens added.');
				$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-widget-container']/div[5]//tr[@class='nothing-to-show']/td", 'No maps added.');
				$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-widget-container']/div[6]//tr[@class='nothing-to-show']/td", 'No data found.');
				$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-widget-container']/div[7]//tr[@class='nothing-to-show']/td", 'No data found.');
				$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-widget-container']/div[9]//tr[@class='nothing-to-show']/td", 'No data found.');
				$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-widget-container']/div[10]//tr[@class='nothing-to-show']/td", 'No data found.');
			}
			$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-widget-container']/div[3]//h4", 'Favourite graphs');
			$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-widget-container']/div[4]//h4", 'Favourite screens');
			$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-widget-container']/div[5]//h4", 'Favourite maps');
			$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-widget-container']/div[6]//h4", 'Problems');
			$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-widget-container']/div[7]//h4", 'Web monitoring');
			$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-widget-container']/div[8]//h4", 'Discovery status');
			$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-widget-container']/div[9]//h4", 'Host status');
			$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-widget-container']/div[10]//h4", 'System status');
			$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-widget-container']/div[11]//h4", 'Status of Zabbix');
		}
	}

	public function testPageDashboard_AddFavouriteGraphs() {
		$this->zbxTestLogin('charts.php');
		$this->zbxTestCheckHeader('Graphs');
		$this->zbxTestDropdownSelectWait('graphid', $this->graphCpu);
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//button[@id='addrm_fav']"));
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Add to favourites');
		$this->zbxTestClickWait('addrm_fav');
		$this->zbxTestCheckFatalErrors();
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Remove from favourites');

		$this->zbxTestDropdownSelectWait('graphid', $this->graphMemory);
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//button[@id='addrm_fav']"));
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Add to favourites');
		$this->zbxTestClickWait('addrm_fav');
		$this->zbxTestCheckFatalErrors();
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Remove from favourites');

		$this->zbxTestOpen('zabbix.php?action=dashboard.view');
		$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-widget-container']/div[3]//a[@href='charts.php?graphid=$this->graphCpuId']", 'ЗАББИКС Сервер: '.$this->graphCpu);
		$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-widget-container']/div[3]//a[@href='charts.php?graphid=$this->graphMemoryId']", 'ЗАББИКС Сервер: '.$this->graphMemory);
		$this->zbxTestCheckFatalErrors();
		$this->assertEquals(1, DBcount("SELECT profileid FROM profiles WHERE idx='web.favorite.graphids' AND value_id=$this->graphCpuId"));
		$this->assertEquals(1, DBcount("SELECT profileid FROM profiles WHERE idx='web.favorite.graphids' AND value_id=$this->graphMemoryId"));
	}

	public function testPageDashboard_RemoveFavouriteGraphs() {
		$this->zbxTestLogin('zabbix.php?action=dashboard.view');
		$FavouriteGraphs = DBfetchArray(DBselect("SELECT value_id FROM profiles WHERE idx='web.favorite.graphids'"));
		foreach ($FavouriteGraphs as $FavouriteGraph) {
			$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath("//div[@class='dashbrd-grid-widget-container']/div[3]//button[@onclick=\"rm4favorites('graphid','".$FavouriteGraph['value_id']."')\"]"));
			$this->zbxTestClickXpathWait("//div[@class='dashbrd-grid-widget-container']/div[3]//button[@onclick=\"rm4favorites('graphid','".$FavouriteGraph['value_id']."')\"]");
			$this->zbxTestWaitUntilElementNotVisible(WebDriverBy::xpath("//div[@class='dashbrd-grid-widget-container']/div[3]//button[@onclick=\"rm4favorites('graphid','".$FavouriteGraph['value_id']."')\"]"));
		}
		$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-widget-container']/div[3]//tr[@class='nothing-to-show']/td", 'No graphs added.');
		$this->zbxTestCheckFatalErrors();
		$this->assertEquals(0, DBcount("SELECT profileid FROM profiles WHERE idx='web.favorite.graphids'"));
	}

	public function testPageDashboard_AddFavouriteScreen() {
		$this->zbxTestLogin('screenconf.php');
		$this->zbxTestCheckHeader('Screens');
		$this->zbxTestClickLinkTextWait($this->screenClock);
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//button[@id='addrm_fav']"));
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Add to favourites');
		$this->zbxTestClickWait('addrm_fav');
		$this->zbxTestCheckFatalErrors();
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Remove from favourites');

		$this->zbxTestOpen('zabbix.php?action=dashboard.view');
		$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-widget-container']/div[4]//a[@href='screens.php?elementid=$this->screenClockId']", $this->screenClock);
		$this->zbxTestCheckFatalErrors();
		$this->assertEquals(1, DBcount("SELECT profileid FROM profiles WHERE idx='web.favorite.screenids' AND value_id=$this->screenClockId"));
	}

	public function testPageDashboard_RemoveFavouriteScreens() {
		$this->zbxTestLogin('zabbix.php?action=dashboard.view');
		$FavouriteScreens = DBfetchArray(DBselect("SELECT value_id FROM profiles WHERE idx='web.favorite.screenids'"));
		foreach ($FavouriteScreens as $FavouriteScreen) {
			$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath("//div[@class='dashbrd-grid-widget-container']/div[4]//button[@onclick=\"rm4favorites('screenid','".$FavouriteScreen['value_id']."')\"]"));
			$this->zbxTestClickXpathWait("//div[@class='dashbrd-grid-widget-container']/div[4]//button[@onclick=\"rm4favorites('screenid','".$FavouriteScreen['value_id']."')\"]");
			$this->zbxTestWaitUntilElementNotVisible(WebDriverBy::xpath("//div[@class='dashbrd-grid-widget-container']/div[4]//button[@onclick=\"rm4favorites('screenid','".$FavouriteScreen['value_id']."')\"]"));
		}
		$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-widget-container']/div[4]//tr[@class='nothing-to-show']/td", 'No screens added.');
		$this->zbxTestCheckFatalErrors();
		$this->assertEquals(0, DBcount("SELECT profileid FROM profiles WHERE idx='web.favorite.screenids'"));
	}

	public function testPageDashboard_AddFavouriteMap() {
		$this->zbxTestLogin('sysmaps.php');
		$this->zbxTestCheckHeader('Maps');
		$this->zbxTestClickLinkTextWait($this->mapTest);
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//button[@id='addrm_fav']"));
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Add to favourites');
		$this->zbxTestClickWait('addrm_fav');
		$this->zbxTestCheckFatalErrors();
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Remove from favourites');

		$this->zbxTestOpen('zabbix.php?action=dashboard.view');
		$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-widget-container']/div[5]//a[@href='zabbix.php?action=map.view&sysmapid=$this->mapTestId']", $this->mapTest);
		$this->zbxTestCheckFatalErrors();
		$this->assertEquals(1, DBcount("SELECT profileid FROM profiles WHERE idx='web.favorite.sysmapids' AND value_id=$this->mapTestId"));
	}

	public function testPageDashboard_RemoveFavouriteMaps() {
		$this->zbxTestLogin('zabbix.php?action=dashboard.view');
		$FavouriteScreens = DBfetchArray(DBselect("SELECT value_id FROM profiles WHERE idx='web.favorite.sysmapids'"));
		foreach ($FavouriteScreens as $FavouriteScreen) {
			$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath("//div[@class='dashbrd-grid-widget-container']/div[5]//button[@onclick=\"rm4favorites('sysmapid','".$FavouriteScreen['value_id']."')\"]"));
			$this->zbxTestClickXpathWait("//div[@class='dashbrd-grid-widget-container']/div[5]//button[@onclick=\"rm4favorites('sysmapid','".$FavouriteScreen['value_id']."')\"]");
			$this->zbxTestWaitUntilElementNotVisible(WebDriverBy::xpath("//div[@class='dashbrd-grid-widget-container']/div[5]//button[@onclick=\"rm4favorites('sysmapid','".$FavouriteScreen['value_id']."')\"]"));
		}
		$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-widget-container']/div[5]//tr[@class='nothing-to-show']/td", 'No maps added.');
		$this->zbxTestCheckFatalErrors();
		$this->assertEquals(0, DBcount("SELECT profileid FROM profiles WHERE idx='web.favorite.sysmapids'"));
	}

	public function testPageDashboard_FullScreen() {
		$this->zbxTestLogin('zabbix.php?action=dashboard.view');
		$this->zbxTestCheckHeader('Dashboard');

		$this->zbxTestAssertAttribute("//button[@class='btn-max']", 'title', 'Fullscreen');
		$this->zbxTestClickXpathWait("//button[@class='btn-max']");
		$this->zbxTestCheckHeader('Dashboard');
		$this->zbxTestAssertElementNotPresentXpath("//header[@role='banner']");
		$this->zbxTestCheckFatalErrors();

		$this->zbxTestAssertAttribute("//button[@class='btn-min']", 'title', 'Normal view');
		$this->zbxTestClickXpathWait("//button[@class='btn-min']");
		$this->zbxTestAssertAttribute("//button[@class='btn-max']", 'title', 'Fullscreen');
		$this->zbxTestAssertElementPresentXpath("//header[@role='banner']");
		$this->zbxTestCheckFatalErrors();
	}
}
