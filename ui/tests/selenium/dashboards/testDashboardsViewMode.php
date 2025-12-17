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
		$dashboard = $dashboard = CDashboardElement::find()->one()->waitUntilReady();

		$this->page->assertTitle('Dashboard');
		$this->page->assertHeader('Global view');

		$no_data_widgets = [
			'No data found' => 'Top hosts by CPU utilization',
			'No data found' => 'Current problems',
			'No permissions to referred object or it does not exist!' => 'Zabbix server',
			'No permissions to referred object or it does not exist!' => 'Server performance'
		];
		$system_rows = $dashboard->getWidget('System information')->getContent()->asTable()->getRows()->count();
		$host_total = $dashboard->getWidget('Host availability')->getContent()->query('class:host-avail-total')->one()->getText();
		$problems_warning = $dashboard->getWidget('Problems by severity')->getContent()->query('class:warning-bg')->one()->getText();
		$geomap_icon = $dashboard->getWidget('Geomap')->getContent()->query('class:leaflet-marker-icon');

		if ($data['username'] !== 'Admin') {
			foreach ($no_data_widgets as $content => $header) {
				$widget = $dashboard->getWidget($header)->getContent();
				$this->assertEquals($content, $widget->query('class:no-data-message')->one()->getText());
			}

			$this->assertEquals(2, $system_rows);
			$this->assertEquals("0\nTotal", $host_total);
			$this->assertEquals("0\nWarning", $problems_warning);
			$this->assertFalse($geomap_icon->exists());

			// Check that no bad messages are displayed.
			$this->assertFalse($this->query('class:msg-bad')->one(false)->isValid());
		}
		else {
			foreach (array_values($no_data_widgets) as $header) {
				$widget = $dashboard->getWidget($header)->getContent();
				// Check widget has no "no-data-message" class, meaning it has content.
				$this->assertFalse($widget->query('class:no-data-message')->one(false)->isValid());
			}

			$this->zbxTestCheckNoRealHostnames();
			$this->assertEquals(10, $system_rows);
			$this->assertNotEquals("0\nTotal", $host_total);
			$this->assertNotEquals("0\nWarning", $problems_warning);
			$this->assertTrue($geomap_icon->exists());
		}

		$widget_headers = ['Top hosts by CPU utilization', 'System information', 'Host availability', 'Problems by severity',
			'Geomap', 'Current problems', 'Local time', 'Graph'];
		foreach ($widget_headers as $header) {
			$this->assertTrue($dashboard->getWidget($header)->isValid());
		}

		$this->page->logout();
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
