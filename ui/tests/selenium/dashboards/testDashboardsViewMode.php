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


require_once __DIR__.'/../../include/CLegacyWebTest.php';

use Facebook\WebDriver\WebDriverBy;

/**
 * @backup profiles
 */
class testDashboardsViewMode extends CLegacyWebTest {

	public static function getCheckLayoutForDifferentUsersData() {
		return [
			// #0 Super admin.
			[
				[
					'username' => 'Admin',
					'sessionid' => '09e7d4286dfdca4ba7be15e0f3b2b55b'
				]
			],
			// #1 Admin.
			[
				[
					'username' => 'admin-zabbix',
					'sessionid' => '09e7d4286dfdca4ba7be15e0f3b2b55c'
				]
			],
			//#2 User.
			[
				[
					'username' => 'user-zabbix',
					'sessionid' => '09e7d4286dfdca4ba7be15e0f3b2b55d'
				]
			],
			// #3 Guest.
			[
				[
					'username' => 'guest',
					'sessionid' => '09e7d4286dfdca4ba7be15e0f3b2b55e'
				]
			]
		];
	}

	/**
	 * @dataProvider getCheckLayoutForDifferentUsersData
	 *
	 * @onBefore removeGuestFromDisabledGroup
	 * @onAfter addGuestToDisabledGroup
	 */
	public function testDashboardViewMode_CheckLayoutForDifferentUsers($data) {
		$userid = CDBHelper::getValue('SELECT userid FROM users WHERE username='.zbx_dbstr($data['username']));
		$this->authenticateUser($data['sessionid'], $userid);
		$this->zbxTestOpen('zabbix.php?action=dashboard.view&dashboardid=1');
		$this->zbxTestCheckTitle('Dashboard');
		$this->zbxTestCheckHeader('Global view');
		if ($data['username'] !== 'Admin') {
			$this->zbxTestAssertElementText("//div[@class='dashboard-grid']/div[2]//div[contains(@class, 'no-data-message')]",
					'No data found');
			$this->zbxTestAssertElementText("//div[@class='dashboard-grid']/div[3]//div[@class='no-data-message']",
					'No permissions to referred object or it does not exist!');
		}
		else {
			$this->zbxTestCheckNoRealHostnames();
			$this->zbxTestAssertElementPresentXpath("//div[@class='dashboard-grid']/div[3]//h4[text()='Performance']");
		}
		$this->zbxTestAssertElementText("//div[@class='dashboard-grid']/div[2]//h4", 'Top hosts by CPU utilization');
		$this->zbxTestAssertElementText("//div[@class='dashboard-grid']/div[4]//h4", 'System information');
		$this->zbxTestAssertElementText("//div[@class='dashboard-grid']/div[7]//h4", 'Host availability');
		$this->zbxTestAssertElementText("//div[@class='dashboard-grid']/div[8]//h4", 'Problems by severity');
		$this->zbxTestAssertElementText("//div[@class='dashboard-grid']/div[9]//h4", 'Geomap');
		$this->zbxTestAssertElementText("//div[@class='dashboard-grid']/div[10]//h4", 'Current problems');
		$this->zbxTestAssertElementPresentXpath("//div[@class='dashboard-grid']/div[5]//h4[text()='Local time']");
		$this->zbxTestAssertElementPresentXpath("//div[@class='dashboard-grid']/div[6]//h4[text()='Graph']");

		// Logout.
		$this->zbxTestLogout();
		$this->zbxTestWaitForPageToLoad();
		$this->webDriver->manage()->deleteAllCookies();
	}

	public function testDashboardsViewMode_KioskMode() {
		$this->zbxTestLogin('zabbix.php?action=dashboard.view&dashboardid=1', false);
		$this->zbxTestCheckHeader('Global view');
		$this->zbxTestAssertElementPresentXpath("//header");

		$this->zbxTestClickXpathWait("//button[contains(@class, 'btn-kiosk')]");
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath('//button[@title="Normal view"]'));
		$this->zbxTestAssertElementNotPresentXpath("//header");
		$this->zbxTestAssertElementNotPresentXpath("//header[@class='header-title']");
		$this->zbxTestAssertElementNotPresentXpath("//ul[contains(@class, 'filter-breadcrumb')]");
		$this->zbxTestAssertAttribute('//button['.CXPathHelper::fromClass('btn-dashboard-normal').']', 'title', 'Normal view');

		$this->query('class:btn-dashboard-normal')->one()->forceClick();
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath("//button[contains(@class, 'btn-kiosk')]"));
		$this->zbxTestAssertAttribute("//button[contains(@class, 'btn-kiosk')]", 'title', 'Kiosk mode');
		$this->zbxTestAssertElementPresentXpath("//header");
		$this->zbxTestAssertElementPresentXpath("//header[@class='header-title']");
		$this->zbxTestAssertElementPresentXpath('//ul[@class="breadcrumbs"]');
	}

	public function testDashboardsViewMode_KioskModeUrlParameter() {
		// Set layout mode to kiosk view.
		$this->zbxTestLogin('zabbix.php?action=dashboard.view&kiosk=1', false);
		$this->zbxTestWaitForPageToLoad();
		$this->zbxTestWaitUntilElementPresent(WebDriverBy::xpath('//button[@title="Normal view"]'));
		$this->zbxTestAssertElementNotPresentXpath("//header");
		$this->zbxTestAssertElementNotPresentXpath("//header[@class='header-title']");
		$this->zbxTestAssertElementNotPresentXpath("//ul[contains(@class, 'filter-breadcrumb')]");
		$this->zbxTestAssertAttribute('//button['.CXPathHelper::fromClass('btn-dashboard-normal').']', 'title', 'Normal view');

		// Set layout mode to default layout.
		$this->zbxTestOpen('zabbix.php?action=dashboard.view&kiosk=0');
		$this->zbxTestCheckHeader('Global view');
		$this->zbxTestAssertElementPresentXpath("//header");
		$this->zbxTestAssertAttribute("//button[contains(@class, 'btn-kiosk')]", 'title', 'Kiosk mode');
	}

	/**
	 * Guest user needs to be out of "Disabled" group to have access to frontend.
	 */
	public function removeGuestFromDisabledGroup() {
		DBexecute('DELETE FROM users_groups WHERE userid=2 AND usrgrpid=9');
	}

	public function addGuestToDisabledGroup() {
		DBexecute('INSERT INTO users_groups (id, usrgrpid, userid) VALUES (1550, 9, 2)');
	}
}
