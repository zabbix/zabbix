<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup profiles
 */
class testPageDashboard extends CLegacyWebTest {

	public $graphCpu = 'CPU usage';
	public $hostGroup = 'Zabbix servers';
	public $hostName = 'ЗАББИКС Сервер';
	public $graphCpuId = 910;
	public $graphMemory = 'Memory usage';
	public $graphMemoryId = 919;
	public $screenClock = 'Test screen (clock)';
	public $screenClockId = 200001;
	public $mapTest = 'Test map 1';
	public $mapTestId = 3;

	/**
	 * @on-before removeGuestFromDisabledGroup
	 * @on-after addGuestToDisabledGroup
	 */
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
				$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[9]//tr[@class='nothing-to-show']/td", 'No graphs added.');
				$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[8]//tr[@class='nothing-to-show']/td", 'No maps added.');
				$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[7]//tr[@class='nothing-to-show']/td", 'No data found.');
			}
			$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[9]//h4", 'Favourite graphs');
			$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[8]//h4", 'Favourite maps');
			$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[7]//h4", 'Problems');
			$this->zbxTestAssertElementPresentXpath("//div[@class='dashbrd-grid-container']/div[6]//h4[text()='Problems by severity']");
			$this->zbxTestAssertElementPresentXpath("//div[@class='dashbrd-grid-container']/div[5]//h4[text()='Local']");
			$this->zbxTestAssertElementPresentXpath("//div[@class='dashbrd-grid-container']/div[4]//h4[text()='Host availability']");
			$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[3]//h4", 'System information');

			// Logout.
			$this->zbxTestLogout();
			$this->zbxTestWaitForPageToLoad();
			$this->webDriver->manage()->deleteAllCookies();
		}
	}

	public function testPageDashboard_AddFavouriteGraphs() {
		CMultiselectElement::setDefaultFillMode(CMultiselectElement::MODE_SELECT);

		$this->zbxTestLogin('zabbix.php?action=charts.view');
		$this->zbxTestCheckHeader('Graphs');
		$this->query('xpath://a[text()="Filter"]')->one()->click();
		$filter = $this->query('name:zbx_filter')->asForm()->one();
		$filter->fill([
			'Host' => [
				'values' => $this->hostName,
				'context' => $this->hostGroup
			]
		]);
		$filter->getField('Graphs')->select($this->graphCpu);
		$filter->submit();
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//button[@id='addrm_fav']"));
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Add to favourites');
		$this->zbxTestClickWait('addrm_fav');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath('//button[@id="addrm_fav" and @title="Remove from favourites"]'));
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Remove from favourites');

		$filter->query('button:Reset')->one()->click();
		$filter->invalidate();
		$filter->getField('Graphs')->select($this->graphMemory);
		$filter->submit();
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//button[@id='addrm_fav']"));
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Add to favourites');
		$this->zbxTestClickWait('addrm_fav');
		$this->query('id:addrm_fav')->one()->waitUntilAttributesPresent(['title' => 'Remove from favourites']);
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Remove from favourites');

		$this->zbxTestOpen('zabbix.php?action=dashboard.view');
		$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[9]//a[@href='zabbix.php?action=charts.view&view_as=showgraph&filter_search_type=0&filter_graphids%5B0%5D=$this->graphCpuId&filter_set=1']", 'ЗАББИКС Сервер: '.$this->graphCpu);
		$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[9]//a[@href='zabbix.php?action=charts.view&view_as=showgraph&filter_search_type=0&filter_graphids%5B0%5D=$this->graphMemoryId&filter_set=1']", 'ЗАББИКС Сервер: '.$this->graphMemory);
		$this->assertEquals(1, CDBHelper::getCount("SELECT profileid FROM profiles WHERE idx='web.favorite.graphids' AND value_id=$this->graphCpuId"));
		$this->assertEquals(1, CDBHelper::getCount("SELECT profileid FROM profiles WHERE idx='web.favorite.graphids' AND value_id=$this->graphMemoryId"));
	}

	public function testPageDashboard_RemoveFavouriteGraphs() {
		$exception = null;

		try {
			$this->zbxTestLogin('zabbix.php?action=dashboard.view');
			$FavouriteGraphs = DBfetchArray(DBselect("SELECT value_id FROM profiles WHERE idx='web.favorite.graphids'"));
			foreach ($FavouriteGraphs as $FavouriteGraph) {
				$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath("//div[@class='dashbrd-grid-container']/div[9]//button[@onclick=\"rm4favorites('graphid','".$FavouriteGraph['value_id']."')\"]"));
				$this->zbxTestClickXpathWait("//div[@class='dashbrd-grid-container']/div[9]//button[@onclick=\"rm4favorites('graphid','".$FavouriteGraph['value_id']."')\"]");
				$this->zbxTestWaitUntilElementNotVisible(WebDriverBy::xpath("//div[@class='dashbrd-grid-container']/div[9]//button[@onclick=\"rm4favorites('graphid','".$FavouriteGraph['value_id']."')\"]"));
			}
			$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[9]//tr[@class='nothing-to-show']/td", 'No graphs added.');
			$this->assertEquals(0, CDBHelper::getCount("SELECT profileid FROM profiles WHERE idx='web.favorite.graphids'"));
		}
		catch (Exception $e) {
			$exception = $e;
		}

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
		$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[8]//a[@href='zabbix.php?action=map.view&sysmapid=$this->mapTestId']", $this->mapTest);
		$this->assertEquals(1, CDBHelper::getCount("SELECT profileid FROM profiles WHERE idx='web.favorite.sysmapids' AND value_id=$this->mapTestId"));
	}

	public function testPageDashboard_RemoveFavouriteMaps() {
		$this->zbxTestLogin('zabbix.php?action=dashboard.view');
		$FavouriteScreens = DBfetchArray(DBselect("SELECT value_id FROM profiles WHERE idx='web.favorite.sysmapids'"));
		foreach ($FavouriteScreens as $FavouriteScreen) {
			$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath("//div[@class='dashbrd-grid-container']/div[8]//button[@onclick=\"rm4favorites('sysmapid','".$FavouriteScreen['value_id']."')\"]"));
			$this->zbxTestClickXpathWait("//div[@class='dashbrd-grid-container']/div[8]//button[@onclick=\"rm4favorites('sysmapid','".$FavouriteScreen['value_id']."')\"]");
			$this->zbxTestWaitUntilElementNotVisible(WebDriverBy::xpath("//div[@class='dashbrd-grid-container']/div[8]//button[@onclick=\"rm4favorites('sysmapid','".$FavouriteScreen['value_id']."')\"]"));
		}
		$this->zbxTestAssertElementText("//div[@class='dashbrd-grid-container']/div[8]//tr[@class='nothing-to-show']/td", 'No maps added.');
		$this->assertEquals(0, CDBHelper::getCount("SELECT profileid FROM profiles WHERE idx='web.favorite.sysmapids'"));
	}

	public function testPageDashboard_KioskMode() {
		$this->zbxTestLogin('zabbix.php?action=dashboard.view', false);
		$this->zbxTestCheckHeader('Global view');
		$this->zbxTestAssertElementPresentXpath("//header");

		$this->zbxTestClickXpathWait("//button[contains(@class, 'btn-kiosk')]");
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath('//button[@title="Normal view"]'));
		$this->zbxTestAssertElementNotPresentXpath("//header");
		$this->zbxTestAssertElementNotPresentXpath("//header[@class='header-title']");
		$this->zbxTestAssertElementNotPresentXpath("//ul[contains(@class, 'filter-breadcrumb')]");
		$this->zbxTestAssertAttribute("//button[contains(@class, 'btn-min')]", 'title', 'Normal view');

		$this->query('class:btn-min')->one()->forceClick();
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath("//button[contains(@class, 'btn-kiosk')]"));
		$this->zbxTestAssertAttribute("//button[contains(@class, 'btn-kiosk')]", 'title', 'Kiosk mode');
		$this->zbxTestAssertElementPresentXpath("//header");
		$this->zbxTestAssertElementPresentXpath("//header[@class='header-title']");
		$this->zbxTestAssertElementPresentXpath("//ul[contains(@class, 'filter-breadcrumb')]");
	}

	public function testPageDashboard_KioskModeUrlParameter() {
		// Set layout mode to kiosk view.
		$this->zbxTestLogin('zabbix.php?action=dashboard.view&kiosk=1', false);
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath('//button[@title="Normal view"]'));
		$this->zbxTestAssertElementNotPresentXpath("//header");
		$this->zbxTestAssertElementNotPresentXpath("//header[@class='header-title']");
		$this->zbxTestAssertElementNotPresentXpath("//ul[contains(@class, 'filter-breadcrumb')]");
		$this->zbxTestAssertAttribute("//button[contains(@class, 'btn-min')]", 'title', 'Normal view');

		// Set layout mode to default layout.
		$this->zbxTestOpen('zabbix.php?action=dashboard.view&kiosk=0');
		$this->zbxTestCheckHeader('Global view');
		$this->zbxTestAssertElementPresentXpath("//header");
		$this->zbxTestAssertAttribute("//button[contains(@class, 'btn-kiosk')]", 'title', 'Kiosk mode');
	}

	/**
	 * Guest user needs to be out of "Disabled" group to have access to frontend.
	 */
	public static function removeGuestFromDisabledGroup() {
		DBexecute('DELETE FROM users_groups WHERE userid=2 AND usrgrpid=9');
	}

	public function addGuestToDisabledGroup() {
		DBexecute('INSERT INTO users_groups (id, usrgrpid, userid) VALUES (150, 9, 2)');
	}
}
