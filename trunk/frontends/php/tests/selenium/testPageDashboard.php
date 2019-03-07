<?php
/*
** Zabbix
** Copyright (C) 2001-2019 Zabbix SIA
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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';

/**
 * @backup profiles
 */
class testPageDashboard extends CLegacyWebTest {

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
					$this->authenticateUser('09e7d4286dfdca4ba7be15e0f3b2b55b', 1);
					$this->zbxTestOpen('zabbix.php?action=dashboard.view');
					$this->zbxTestCheckNoRealHostnames();
					break;
				case 'admin' :
					$this->authenticateUser('09e7d4286dfdca4ba7be15e0f3b2b55c', 4);
					$this->zbxTestOpen('zabbix.php?action=dashboard.view');
					break;
				case 'user';
					$this->authenticateUser('09e7d4286dfdca4ba7be15e0f3b2b55d', 5);
					$this->zbxTestOpen('zabbix.php?action=dashboard.view');
					break;
				case 'guest';
					$this->authenticateUser('09e7d4286dfdca4ba7be15e0f3b2b55e', 2);
					$this->zbxTestOpen('zabbix.php?action=dashboard.view');
					break;
			}
			$this->zbxTestCheckTitle('Dashboard');
			$this->zbxTestCheckHeader('Global view');
			if ($user != 'super-admin') {
				$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[8]//tr[@class='nothing-to-show']/td", 'No graphs added.');
				$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[7]//tr[@class='nothing-to-show']/td", 'No maps added.');
				$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[6]//tr[@class='nothing-to-show']/td", 'No data found.');
				$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[4]//tr[@class='nothing-to-show']/td", 'No data found.');
			}
			$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[8]//h4", 'Favourite graphs');
			$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[7]//h4", 'Favourite maps');
			$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[6]//h4", 'Problems');
			$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[5]//h4", 'Local');
			$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[4]//h4", 'Problems by severity');
			$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[3]//h4", 'System information');

			// Logout.
			$this->zbxTestLogout();
			$this->zbxTestWaitForPageToLoad();
			$this->webDriver->manage()->deleteAllCookies();
		}
	}

	public function testPageDashboard_AddFavouriteGraphs() {
		$this->zbxTestLogin('charts.php');
		$this->zbxTestCheckHeader('Graphs');
		$this->zbxTestDropdownSelectWait('graphid', $this->graphCpu);
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//button[@id='addrm_fav']"));
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Add to favourites');
		$this->zbxTestClickWait('addrm_fav');
		$this->query('id:addrm_fav')->one()->waitUntilAttributesPresent(['title' => 'Remove from favourites']);
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Remove from favourites');

		$this->zbxTestDropdownSelectWait('graphid', $this->graphMemory);
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//button[@id='addrm_fav']"));
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Add to favourites');
		$this->zbxTestClickWait('addrm_fav');
		$this->query('id:addrm_fav')->one()->waitUntilAttributesPresent(['title' => 'Remove from favourites']);
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Remove from favourites');

		$this->zbxTestOpen('zabbix.php?action=dashboard.view');
		$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[8]//a[@href='charts.php?graphid=$this->graphCpuId']", 'ЗАББИКС Сервер: '.$this->graphCpu);
		$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[8]//a[@href='charts.php?graphid=$this->graphMemoryId']", 'ЗАББИКС Сервер: '.$this->graphMemory);
		$this->assertEquals(1, CDBHelper::getCount("SELECT profileid FROM profiles WHERE idx='web.favorite.graphids' AND value_id=$this->graphCpuId"));
		$this->assertEquals(1, CDBHelper::getCount("SELECT profileid FROM profiles WHERE idx='web.favorite.graphids' AND value_id=$this->graphMemoryId"));
	}

	public function testPageDashboard_RemoveFavouriteGraphs() {
		// Disable debug mode. Debug button overlaps delete graph icon.
		DBexecute("UPDATE usrgrp SET debug_mode=0 WHERE usrgrpid=7");
		$exception = null;

		try {
			$this->zbxTestLogin('zabbix.php?action=dashboard.view');
			$FavouriteGraphs = DBfetchArray(DBselect("SELECT value_id FROM profiles WHERE idx='web.favorite.graphids'"));
			foreach ($FavouriteGraphs as $FavouriteGraph) {
				$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath("//div[@class='dashbrd-grid-container']/div[8]//button[@onclick=\"rm4favorites('graphid','".$FavouriteGraph['value_id']."')\"]"));
				$this->zbxTestClickXpathWait("//div[@class='dashbrd-grid-container']/div[8]//button[@onclick=\"rm4favorites('graphid','".$FavouriteGraph['value_id']."')\"]");
				$this->zbxTestWaitUntilElementNotVisible(WebDriverBy::xpath("//div[@class='dashbrd-grid-container']/div[8]//button[@onclick=\"rm4favorites('graphid','".$FavouriteGraph['value_id']."')\"]"));
			}
			$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[8]//tr[@class='nothing-to-show']/td", 'No graphs added.');
			$this->assertEquals(0, CDBHelper::getCount("SELECT profileid FROM profiles WHERE idx='web.favorite.graphids'"));
		}
		catch (Exception $e) {
			$exception = $e;
		}

		// Enable debug mode.
		DBexecute("UPDATE usrgrp SET debug_mode=1 WHERE usrgrpid=7");
		if ($exception !== null) {
			throw $exception;
		}
	}

	public function testPageDashboard_AddFavouriteMap() {
		$this->zbxTestLogin('sysmaps.php');
		$this->zbxTestCheckHeader('Maps');
		$this->zbxTestClickLinkTextWait($this->mapTest);
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//button[@id='addrm_fav']"));
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Add to favourites');
		$this->zbxTestClickWait('addrm_fav');
		$this->query('id:addrm_fav')->one()->waitUntilAttributesPresent(['title' => 'Remove from favourites']);
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Remove from favourites');

		$this->zbxTestOpen('zabbix.php?action=dashboard.view');
		$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[7]//a[@href='zabbix.php?action=map.view&sysmapid=$this->mapTestId']", $this->mapTest);
		$this->assertEquals(1, CDBHelper::getCount("SELECT profileid FROM profiles WHERE idx='web.favorite.sysmapids' AND value_id=$this->mapTestId"));
	}

	public function testPageDashboard_RemoveFavouriteMaps() {
		$this->zbxTestLogin('zabbix.php?action=dashboard.view');
		$FavouriteScreens = DBfetchArray(DBselect("SELECT value_id FROM profiles WHERE idx='web.favorite.sysmapids'"));
		foreach ($FavouriteScreens as $FavouriteScreen) {
			$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath("//div[@class='dashbrd-grid-container']/div[7]//button[@onclick=\"rm4favorites('sysmapid','".$FavouriteScreen['value_id']."')\"]"));
			$this->zbxTestClickXpathWait("//div[@class='dashbrd-grid-container']/div[7]//button[@onclick=\"rm4favorites('sysmapid','".$FavouriteScreen['value_id']."')\"]");
			$this->zbxTestWaitUntilElementNotVisible(WebDriverBy::xpath("//div[@class='dashbrd-grid-container']/div[7]//button[@onclick=\"rm4favorites('sysmapid','".$FavouriteScreen['value_id']."')\"]"));
		}
		$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[7]//tr[@class='nothing-to-show']/td", 'No maps added.');
		$this->assertEquals(0, CDBHelper::getCount("SELECT profileid FROM profiles WHERE idx='web.favorite.sysmapids'"));
	}

	public function testPageDashboard_FullScreen() {
		$this->zbxTestLogin('zabbix.php?action=dashboard.view');
		$this->zbxTestCheckHeader('Global view');
		$this->zbxTestAssertElementPresentXpath("//header");
		$this->zbxTestAssertAttribute("//button[contains(@class, 'btn-max')]", 'title', 'Fullscreen');

		$this->zbxTestClickXpathWait("//button[contains(@class, 'btn-max')]");
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath('//button[@title="Kiosk mode"]'));
		$this->zbxTestCheckHeader('Global view');
		$this->zbxTestAssertElementNotPresentXpath("//header");
		$this->zbxTestAssertElementPresentXpath("//div[@class='header-title table']");
		$this->zbxTestAssertElementPresentXpath("//ul[contains(@class, 'filter-breadcrumb')]");
		$this->zbxTestAssertAttribute("//button[contains(@class, 'btn-kiosk')]", 'title', 'Kiosk mode');
	}

	/**
	 * @depends testPageDashboard_FullScreen
	 */
	public function testPageDashboard_KioskMode() {
		$this->zbxTestLogin('zabbix.php?action=dashboard.view', false);
		$this->zbxTestCheckHeader('Global view');
		$this->zbxTestAssertElementNotPresentXpath("//header");

		$this->zbxTestClickXpathWait("//button[contains(@class, 'btn-kiosk')]");
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath('//button[@title="Normal view"]'));
		$this->zbxTestAssertElementNotPresentXpath("//header");
		$this->zbxTestAssertElementNotPresentXpath("//div[@class='header-title table']");
		$this->zbxTestAssertElementNotPresentXpath("//ul[contains(@class, 'filter-breadcrumb')]");
		$this->zbxTestAssertAttribute("//button[contains(@class, 'btn-min')]", 'title', 'Normal view');

		$this->webDriver->executeScript('arguments[0].click();', [$this->webDriver->findElement(WebDriverBy::className('btn-min'))]);
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath("//button[contains(@class, 'btn-max')]"));
		$this->zbxTestAssertAttribute("//button[contains(@class, 'btn-max')]", 'title', 'Fullscreen');
		$this->zbxTestAssertElementPresentXpath("//header");
		$this->zbxTestAssertElementPresentXpath("//div[@class='header-title table']");
		$this->zbxTestAssertElementPresentXpath("//ul[contains(@class, 'filter-breadcrumb')]");
	}

	public function testPageDashboard_KioskModeUrlParameter() {
		// Set layout mode to kiosk view.
		$this->zbxTestLogin('zabbix.php?action=dashboard.view&kiosk=1', false);
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath('//button[@title="Normal view"]'));
		$this->zbxTestAssertElementNotPresentXpath("//header");
		$this->zbxTestAssertElementNotPresentXpath("//div[@class='header-title table']");
		$this->zbxTestAssertElementNotPresentXpath("//ul[contains(@class, 'filter-breadcrumb')]");
		$this->zbxTestAssertAttribute("//button[contains(@class, 'btn-min')]", 'title', 'Normal view');

		//  Set layout mode to full screen.
		$this->zbxTestOpen('zabbix.php?action=dashboard.view&fullscreen=1', false);
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath('//button[@title="Kiosk mode"]'));
		$this->zbxTestCheckHeader('Global view');
		$this->zbxTestAssertElementNotPresentXpath("//header");
		$this->zbxTestAssertElementPresentXpath("//div[@class='header-title table']");
		$this->zbxTestAssertElementPresentXpath("//ul[contains(@class, 'filter-breadcrumb')]");
		$this->zbxTestAssertAttribute("//button[contains(@class, 'btn-kiosk')]", 'title', 'Kiosk mode');

		// Set layout mode to default layout.
		$this->zbxTestOpen('zabbix.php?action=dashboard.view&fullscreen=0');
		$this->zbxTestCheckHeader('Global view');
		$this->zbxTestAssertElementPresentXpath("//header");
		$this->zbxTestAssertAttribute("//button[contains(@class, 'btn-max')]", 'title', 'Fullscreen');
	}
}
