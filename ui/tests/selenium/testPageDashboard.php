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

require_once dirname(__FILE__).'/../include/CLegacyWebTest.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup profiles
 */
class testPageDashboard extends CLegacyWebTest {

	public $graphCpu = 'CPU utilization';
	public $hostName = 'ЗАББИКС Сервер';
	public $graphMemory = 'Available memory in %';
	public $screenClock = 'Test screen (clock)';
	public $mapTest = 'Test map 1';
	public $mapTestId = 3;

	/**
	 * @onBefore removeGuestFromDisabledGroup
	 * @onAfter addGuestToDisabledGroup
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
				$this->zbxTestAssertElementText("//div[@class='dashboard-grid']/div[8]//tr[@class='nothing-to-show']/td", 'No graphs added.');
				$this->zbxTestAssertElementText("//div[@class='dashboard-grid']/div[7]//tr[@class='nothing-to-show']/td", 'No maps added.');
				$this->zbxTestAssertElementText("//div[@class='dashboard-grid']/div[6]//tr[@class='nothing-to-show']/td", 'No data found.');
			}
			$this->zbxTestAssertElementText("//div[@class='dashboard-grid']/div[8]//h4", 'Favorite graphs');
			$this->zbxTestAssertElementText("//div[@class='dashboard-grid']/div[7]//h4", 'Favorite maps');
			$this->zbxTestAssertElementText("//div[@class='dashboard-grid']/div[6]//h4", 'Problems');
			$this->zbxTestAssertElementPresentXpath("//div[@class='dashboard-grid']/div[5]//h4[text()='Problems by severity']");
			$this->zbxTestAssertElementPresentXpath("//div[@class='dashboard-grid']/div[4]//h4[text()='Local']");
			$this->zbxTestAssertElementPresentXpath("//div[@class='dashboard-grid']/div[3]//h4[text()='Host availability']");
			$this->zbxTestAssertElementText("//div[@class='dashboard-grid']/div[2]//h4", 'System information');

			// Logout.
			$this->zbxTestLogout();
			$this->zbxTestWaitForPageToLoad();
			$this->webDriver->manage()->deleteAllCookies();
		}
	}

	public function testPageDashboard_CheckDasboardPopupLayout() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&new=1')->waitUntilReady();
		$dialog = COverlayDialogElement::find()->waitUntilVisible()->one();
		$this->assertEquals('Dashboard properties', $dialog->getTitle());
		$properties_form = $dialog->query('name:dashboard_properties_form')->asForm()->one();
		$this->assertEquals(['Owner', 'Name', 'Default page display period', 'Start slideshow automatically'],
				$properties_form->getLabels()->asText()
		);

		// Check available display periods.
		$properties_form->checkValue(['Default page display period' => '30 seconds', 'Name' => 'New dashboard']);
		$this->assertEquals('255', $properties_form->query('id:name')->one()->getAttribute('maxlength'));
		$this->assertEquals(['10 seconds', '30 seconds', '1 minute', '2 minutes', '10 minutes', '30 minutes', '1 hour'],
				$properties_form->query('name:display_period')->asDropdown()->one()->getOptions()->asText()
		);

		$properties_form->fill(['Name' => 'Dashboard creation']);
		$properties_form->submit();
		COverlayDialogElement::ensureNotPresent();
		$this->page->waitUntilReady();
		$dashboard = CDashboardElement::find()->one();

		// Check popup-menu options.
		$this->query('id:dashboard-add')->one()->click();
		$add_menu = CPopupMenuElement::find()->one()->waitUntilVisible();
		foreach (['Add widget' => true, 'Add page' => true, 'Paste widget' => false, 'Paste page'=> false] as $item => $enabled) {
			$this->assertTrue($add_menu->getItem($item)->isEnabled($enabled));
		}
		$dashboard->cancelEditing();
	}

	public function testPageDashboard_AddFavouriteGraphs() {
		$cpu_itemid = CDBHelper::getValue('SELECT itemid FROM items WHERE hostid=10084 AND name='.zbx_dbstr($this->graphCpu));
		$memory_itemid = CDBHelper::getValue('SELECT itemid FROM items WHERE hostid=10084 AND name='.zbx_dbstr($this->graphMemory));

		$this->zbxTestLogin('zabbix.php?action=latest.view');
		$this->zbxTestCheckHeader('Latest data');

		$filter = $this->query('name:zbx_filter')->asForm()->one();
		$filter->getField('Hosts')->fill($this->hostName);
		$filter->getField('Name')->fill($this->graphCpu);
		$filter->submit();
		$cpu_link = $this->query('link:Graph')->one();
		$cpu_link->click();

		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//button[@id='addrm_fav']"));
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Add to favorites');
		$this->zbxTestClickWait('addrm_fav');
		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath('//button[@id="addrm_fav" and @title="Remove from favorites"]'));
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Remove from favorites');

		$this->page->open('zabbix.php?action=latest.view');
		$filter->invalidate();
		$filter->getField('Hosts')->fill($this->hostName);
		$filter->getField('Name')->fill($this->graphMemory);
		$filter->submit();
		$memory_link = $this->query('link:Graph')->one();
		$memory_link->click();

		$this->zbxTestWaitUntilElementVisible(WebDriverBy::xpath("//button[@id='addrm_fav']"));
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Add to favorites');
		$this->zbxTestClickWait('addrm_fav');
		$this->query('id:addrm_fav')->one()->waitUntilAttributesPresent(['title' => 'Remove from favorites']);
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Remove from favorites');

		$this->zbxTestOpen('zabbix.php?action=dashboard.view');
		$this->page->waitUntilReady();
		$url_xpath = '//div[@class="dashboard-grid"]/div[8]//a[@href=';

		foreach ([$this->graphCpu => $cpu_itemid, $this->graphMemory => $memory_itemid] as $graph => $itemid) {
			$graph_url = 'history.php?action=showgraph&itemids%5B0%5D='.$itemid;
			$this->zbxTestAssertElementText($url_xpath.CXPathHelper::escapeQuotes($graph_url).']', 'ЗАББИКС Сервер: '.$graph);
			$this->assertEquals(1, CDBHelper::getCount('SELECT profileid FROM profiles WHERE idx='
					.zbx_dbstr('web.favorite.graphids').' AND value_id='.$itemid)
			);
		}
	}

	public function testPageDashboard_RemoveFavouriteGraphs() {
		$exception = null;

		try {
			$this->zbxTestLogin('zabbix.php?action=dashboard.view');
			$FavouriteGraphs = DBfetchArray(DBselect('SELECT value_id FROM profiles WHERE idx='.zbx_dbstr('web.favorite.graphids')));
			foreach ($FavouriteGraphs as $FavouriteGraph) {
				$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath('//div[@class="dashboard-grid-widget-container"]/div[2]//button[@onclick="rm4favorites(\'itemid\',\''.$FavouriteGraph['value_id'].'\')"]'));
				$this->zbxTestClickXpathWait('//div[@class="dashboard-grid-widget-container"]/div[2]//button[@onclick="rm4favorites(\'itemid\',\''.$FavouriteGraph['value_id'].'\')"]');
				$this->zbxTestWaitUntilElementNotVisible(WebDriverBy::xpath('//div[@class="dashboard-grid-widget-container"]/div[2]//button[@onclick="rm4favorites(\'itemid\',\''.$FavouriteGraph['value_id'].'\')"]'));
			}
			$this->zbxTestAssertElementText('//div[@class="dashboard-grid-widget-container"]//tr[@class="nothing-to-show"]/td', 'No graphs added.');
			$this->assertEquals(0, CDBHelper::getCount('SELECT profileid FROM profiles WHERE idx='.zbx_dbstr('web.favorite.graphids')));
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
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Add to favorites');
		$this->zbxTestClickWait('addrm_fav');
		$this->query('id:addrm_fav')->one()->waitUntilAttributesPresent(['title' => 'Remove from favorites']);
		$this->zbxTestAssertAttribute("//button[@id='addrm_fav']", 'title', 'Remove from favorites');

		$this->zbxTestOpen('zabbix.php?action=dashboard.view');
		$this->zbxTestAssertElementText('//div[@class="dashboard-grid-widget-container"]/div[2]//a[@href="zabbix.php?action=map.view&sysmapid='.$this->mapTestId.'"]', $this->mapTest);
		$this->assertEquals(1, CDBHelper::getCount('SELECT profileid FROM profiles WHERE idx='.zbx_dbstr('web.favorite.sysmapids').' AND value_id='.$this->mapTestId));
	}

	public function testPageDashboard_RemoveFavouriteMaps() {
		$this->page->login()->open('zabbix.php?action=dashboard.view&dashboardid=1');
		$widget_content = CDashboardElement::find()->one()->getWidget('Favorite maps')->getContent();
		$favourite_maps = DBfetchArray(DBselect('SELECT value_id FROM profiles WHERE idx='.
				zbx_dbstr('web.favorite.sysmapids')));

		foreach ($favourite_maps as $favourite_map) {
			$widget_content->query('xpath://button[contains(@onclick, "(\'sysmapid\',\''.
					$favourite_map['value_id'].'\')")]')->waitUntilClickable()->one()->click();
			$map_name = CDBHelper::getValue('SELECT name FROM sysmaps WHERE sysmapid='.
					zbx_dbstr($favourite_map['value_id']));
			$widget_content->query('link', $map_name)->waitUntilNotPresent();
		}

		$this->assertTrue($widget_content->query('xpath://table//td[text()="No maps added."]')->waitUntilVisible()
				->one()->isPresent());
		$this->assertEquals(0, CDBHelper::getCount('SELECT profileid FROM profiles WHERE idx='.
				zbx_dbstr('web.favorite.sysmapids')));
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
		$this->zbxTestAssertElementPresentXpath('//ul[@class="breadcrumbs"]');
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
