<?php
/*
** Zabbix
** Copyright (C) 2001-2017 Zabbix SIA
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
	public function testPageDashboard_CheckLayoutForDifferentUsers() {
		$users = ['super-admin', 'admin', 'user', 'guest'];
		foreach ($users as $user) {
			switch ($user) {
				case 'super-admin' :
		$this->zbxTestLogin('zabbix.php?action=dashboard.view');
		$this->zbxTestCheckTitle('Dashboard');
		$this->zbxTestCheckHeader('Dashboard');
					$this->zbxTestCheckNoRealHostnames();
					$this->zbxTestAssertElementText("//h4[@id='stszbx_header']", 'Status of Zabbix');
					$this->zbxTestAssertElementText("//h4[@id='dscvry_header']", 'Discovery status');
					break;
				case 'admin' :
					$this->authenticateUser('09e7d4286dfdca4ba7be15e0f3b2b55c' , 4);
					$this->zbxTestOpen('zabbix.php?action=dashboard.view');
					$this->zbxTestCheckTitle('Dashboard');
					$this->zbxTestCheckHeader('Dashboard');
					$this->zbxTestAssertElementText("//h4[@id='dscvry_header']", 'Discovery status');
					$this->zbxTestAssertElementText("//div[@id='dscvry_widget']//a[@href='zabbix.php?action=discovery.view&druleid=3']", 'External network');
					$this->zbxTestAssertElementNotPresentXpath("//div[@id='stszbx_widget']");
					break;
				case 'user';
					$this->authenticateUser('09e7d4286dfdca4ba7be15e0f3b2b55d' , 5);
					$this->zbxTestOpen('zabbix.php?action=dashboard.view');
					$this->zbxTestCheckTitle('Dashboard');
					$this->zbxTestCheckHeader('Dashboard');
					$this->zbxTestAssertElementNotPresentXpath("//div[@id='stszbx_widget']");
					$this->zbxTestAssertElementNotPresentXpath("//div[@id='dscvry_widget']");
					break;
				case 'guest';
					$this->zbxTestOpen('zabbix.php?action=dashboard.view');
					$this->zbxTestCheckTitle('Dashboard');
					$this->zbxTestCheckHeader('Dashboard');
					$this->zbxTestAssertElementNotPresentXpath("//div[@id='stszbx_widget']");
					$this->zbxTestAssertElementNotPresentXpath("//div[@id='dscvry_widget']");
					break;
	}
			if ($user != 'super-admin'){
				$this->zbxTestAssertElementText("//div[@id='favgrph']//td", 'No graphs added.');
				$this->zbxTestAssertElementText("//div[@id='favscr']//td", 'No screens added.');
				$this->zbxTestAssertElementText("//div[@id='favmap']//td", 'No maps added.');
				$this->zbxTestAssertElementText("//div[@id='syssum']//td", 'No data found.');
				$this->zbxTestAssertElementText("//div[@id='hoststat']//td", 'No data found.');
				$this->zbxTestAssertElementText("//div[@id='lastiss']//td", 'No data found.');
				$this->zbxTestAssertElementText("//div[@id='webovr']//td", 'No data found.');
			}
			$this->zbxTestAssertElementText("//h4[@id='favgrph_header']", 'Favourite graphs');
			$this->zbxTestAssertElementText("//h4[@id='favscr_header']", 'Favourite screens');
			$this->zbxTestAssertElementText("//h4[@id='favmap_header']", 'Favourite maps');
			$this->zbxTestAssertElementText("//h4[@id='lastiss_header']", 'Last 20 issues');
			$this->zbxTestAssertElementText("//h4[@id='webovr_header']", 'Web monitoring');
			$this->zbxTestAssertElementText("//h4[@id='hoststat_header']", 'Host status');
			$this->zbxTestAssertElementText("//h4[@id='syssum_header']", 'System status');
			$this->webDriver->manage()->deleteAllcookies();
		}
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

	public function testPageDashboard_EnableConfigurationAndCancel() {
		$this->zbxTestLogin('zabbix.php?action=dashboard.view');
		$this->zbxTestCheckHeader('Dashboard');
		$this->zbxTestAssertAttribute("//button[@class='btn-conf']", 'title', 'Configure');
		$this->zbxTestClickXpathWait("//button[@class='btn-conf']");
		$this->zbxTestCheckTitle('Dashboard configuration');
		$this->zbxTestAssertElementText("//span[@class='link-action red']", 'Disabled');
		$this->zbxTestAssertElementPresentXpath("//select[@id='grpswitch'][@disabled]");
		$this->zbxTestAssertElementPresentXpath("//input[@id='maintenance'][@disabled]");
		$this->zbxTestAssertElementPresentXpath("//input[@id='trgSeverity_0'][@disabled]");
		$this->zbxTestAssertElementPresentXpath("//input[@id='trgSeverity_1'][@disabled]");
		$this->zbxTestAssertElementPresentXpath("//input[@id='trgSeverity_2'][@disabled]");
		$this->zbxTestAssertElementPresentXpath("//input[@id='trgSeverity_3'][@disabled]");
		$this->zbxTestAssertElementPresentXpath("//input[@id='trgSeverity_4'][@disabled]");
		$this->zbxTestAssertElementPresentXpath("//input[@id='trgSeverity_5'][@disabled]");
		$this->zbxTestAssertElementPresentXpath("//input[@id='trigger_name'][@disabled]");
		$this->zbxTestAssertElementPresentXpath("//select[@id='extAck'][@disabled]");

		$this->zbxTestClickXpathWait("//span[@class='link-action red']");
		$this->zbxTestAssertElementText("//span[@class='link-action green']", 'Enabled');
		$this->zbxTestAssertElementNotPresentXpath("//select[@id='grpswitch'][@disabled]");
		$this->zbxTestAssertElementNotPresentXpath("//input[@id='maintenance'][@disabled]");
		$this->zbxTestAssertElementNotPresentXpath("//input[@id='trgSeverity_0'][@disabled]");
		$this->zbxTestAssertElementNotPresentXpath("//input[@id='trgSeverity_1'][@disabled]");
		$this->zbxTestAssertElementNotPresentXpath("//input[@id='trgSeverity_2'][@disabled]");
		$this->zbxTestAssertElementNotPresentXpath("//input[@id='trgSeverity_3'][@disabled]");
		$this->zbxTestAssertElementNotPresentXpath("//input[@id='trgSeverity_4'][@disabled]");
		$this->zbxTestAssertElementNotPresentXpath("//input[@id='trgSeverity_5'][@disabled]");
		$this->zbxTestAssertElementNotPresentXpath("//input[@id='trigger_name'][@disabled]");
		$this->zbxTestAssertElementNotPresentXpath("//selecte[@id='extAck'][@disabled]");
		$this->zbxTestDropdownHasOptions('extAck', ['All', 'Separated', 'Unacknowledged only']);
		$this->zbxTestAssertElementNotPresentId('groupids_');
		$this->zbxTestAssertElementNotPresentId('hidegroupids_');

		$this->zbxTestClick('cancel');
		$this->zbxTestClickXpathWait("//button[@class='btn-conf']");
		$this->zbxTestCheckTitle('Dashboard configuration');
		$this->zbxTestAssertElementText("//span[@class='link-action red']", 'Disabled');
		$this->assertEquals(1, count("SELECT profileid FROM profiles WHERE idx='web.dashconf.filter.enable' AND value_int=0"));
	}

	public function testPageDashboard_Configuration() {
		$this->zbxTestLogin('dashconf.php');
		$this->zbxTestCheckHeader('Dashboard');
		$this->zbxTestClickXpathWait("//span[@class='link-action red']");
		$this->zbxTestAssertElementText("//span[@class='link-action green']", 'Enabled');
		$this->zbxTestDropdownSelect('grpswitch', 'Selected');
		$this->zbxTestAssertElementPresentId('groupids_');
		$this->zbxTestAssertElementPresentId('hidegroupids_');

		$this->zbxTestClickAndSwitchToNewWindow("//div[@id='groupids_']/..//button");
		$this->zbxTestClickLinkTextWait('Zabbix servers');
		$this->zbxTestWaitWindowClose();
		$this->zbxTestClickAndSwitchToNewWindow("//div[@id='hidegroupids_']/..//button");
		$this->zbxTestClickLinkTextWait('Discovered hosts');
		$this->zbxTestWaitWindowClose();

		$this->zbxTestCheckboxSelect('maintenance', false);
		$this->zbxTestCheckboxSelect('trgSeverity_0', false);
		$this->zbxTestDropdownSelect('extAck', 'Separated');
		$this->zbxTestInputType('trigger_name', 'test');
		$this->zbxTestClick('update');

		$this->zbxTestCheckHeader('Dashboard');
		$this->zbxTestCheckTitle('Dashboard');
		$this->zbxTestCheckFatalErrors();

		$this->assertEquals(1, count("SELECT profileid FROM profiles WHERE idx='web.dashconf.filter.enable' AND value_int=1"));
		$this->assertEquals(1, count("SELECT profileid FROM profiles WHERE idx='web.dashconf.groups.grpswitch' AND value_int=1"));
		$this->assertEquals(1, count("SELECT profileid FROM profiles WHERE idx='web.dashconf.hosts.maintenance' AND value_int=0"));
		$this->assertEquals(1, count("SELECT profileid FROM profiles WHERE idx='web.dashconf.triggers.severity' AND value_str='1;2;3;4;5'"));
		$this->assertEquals(1, count("SELECT profileid FROM profiles WHERE idx='web.dashconf.triggers.name' AND value_str='test'"));
		$this->assertEquals(1, count("SELECT profileid FROM profiles WHERE idx='web.dashconf.events.extAck' AND value_int=2"));
		$this->assertEquals(1, count("SELECT profileid FROM profiles WHERE idx='web.dashconf.groups.groupids' AND value_id=4"));
		$this->assertEquals(1, count("SELECT profileid FROM profiles WHERE idx='web.dashconf.groups.hide.groupids' AND value_id=5"));
	}
}
