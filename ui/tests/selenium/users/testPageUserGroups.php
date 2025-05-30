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

/**
 * @backup usrgrp
 */
class testPageUserGroups extends CLegacyWebTest {

	/**
	 * Attach MessageBehavior, TableBehavior and TagBehavior to the test.
	 */
	public function getBehaviors() {
		return [
			CTableBehavior::class
		];
	}

	public static function allGroups() {
		return CDBHelper::getDataProvider("select * from usrgrp where name<>'Disabled' and name<>'Internal' order by usrgrpid");
	}

	public function testPageUserGroups_CheckLayout() {
		$this->zbxTestLogin('zabbix.php?action=usergroup.list');
		$this->zbxTestCheckTitle('Configuration of user groups');
		$this->zbxTestCheckHeader('User groups');

		$this->zbxTestAssertElementPresentXpath("//thead//th/a[text()='Name']");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'#')]");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Members')]");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Frontend access')]");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Debug mode')]");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Status')]");

		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
		$this->zbxTestAssertElementPresentXpath("//div[@class='table-stats'][contains(text(),'Displaying')]");

		$this->zbxTestAssertElementPresentXpath("//button[text()='Enable'][@disabled]");
		$this->zbxTestAssertElementPresentXpath("//button[text()='Disable'][@disabled]");
		$this->zbxTestAssertElementPresentXpath("//button[text()='Enable debug mode'][@disabled]");
		$this->zbxTestAssertElementPresentXpath("//button[text()='Disable debug mode'][@disabled]");
		$this->zbxTestAssertElementPresentXpath("//button[text()='Delete'][@disabled]");
	}

	/**
	* @dataProvider allGroups
	*/
	public function testPageUserGroups_SimpleUpdate($group) {
		$usrgrpid = $group['usrgrpid'];
		$name = $group['name'];

		$sqlHashGroup = "select * from usrgrp where name='$name' order by usrgrpid";
		$oldHashGroup = CDBHelper::getHash($sqlHashGroup);
		$sqlHashUsersGroups = "select * from users_groups where usrgrpid=$usrgrpid order by id";
		$oldHashUsersGroups = CDBHelper::getHash($sqlHashUsersGroups);

		$this->zbxTestLogin('zabbix.php?action=usergroup.list');
		$this->zbxTestCheckTitle('Configuration of user groups');
		$this->zbxTestClickLinkText($name);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckHeader('User groups');
		$this->zbxTestCheckTitle('Configuration of user groups');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'User group updated');
		$this->zbxTestTextPresent($name);

		$this->assertEquals($oldHashGroup, CDBHelper::getHash($sqlHashGroup));
		$this->assertEquals($oldHashUsersGroups, CDBHelper::getHash($sqlHashUsersGroups));
	}

	/**
	* @dataProvider allGroups
	*/
	public function testPageUserGroups_MassDisable($group) {
		$usrgrpid = $group['usrgrpid'];
		$name = $group['name'];

		$cannotDisable = ('Zabbix administrators' == $name);

		$sqlHashGroups = "select * from usrgrp where usrgrpid<>$usrgrpid order by usrgrpid";
		$oldHashGroups = CDBHelper::getHash($sqlHashGroups);

		$this->zbxTestLogin('zabbix.php?action=usergroup.list');
		$this->zbxTestCheckTitle('Configuration of user groups');

		$this->zbxTestCheckboxSelect('usrgrpids_'.$usrgrpid);
		$this->zbxTestClickXpath('//button[text()="Disable"]');

		$this->zbxTestAcceptAlert();
		$this->zbxTestCheckTitle('Configuration of user groups');
		if ($cannotDisable) {
			$this->zbxTestTextPresent('User cannot add oneself to a disabled group or a group with disabled GUI access.');
		}
		else {
			$this->zbxTestTextPresent('User group updated');
		}

		$sql = "select * from usrgrp where usrgrpid=$usrgrpid and users_status=".GROUP_STATUS_DISABLED;
		if ($cannotDisable) {
			$this->assertEquals(0, CDBHelper::getCount($sql));
		}
		else {
			$this->assertEquals(1, CDBHelper::getCount($sql));
		}

		$this->assertEquals($oldHashGroups, CDBHelper::getHash($sqlHashGroups));
	}

	/**
	* @dataProvider allGroups
	*/
	public function testPageUserGroups_MassEnable($group) {
		$usrgrpid = $group['usrgrpid'];
		$name = $group['name'];

		$sqlHashGroups = "select * from usrgrp where usrgrpid<>$usrgrpid order by usrgrpid";
		$oldHashGroups = CDBHelper::getHash($sqlHashGroups);

		$this->zbxTestLogin('zabbix.php?action=usergroup.list');
		$this->zbxTestCheckTitle('Configuration of user groups');

		$this->zbxTestCheckboxSelect('usrgrpids_'.$usrgrpid);
		$this->zbxTestClickXpath('//button[text()="Enable"]');

		$this->zbxTestAcceptAlert();
		$this->zbxTestCheckTitle('Configuration of user groups');
		$this->zbxTestTextPresent('User group updated');

		$sql="select * from usrgrp where usrgrpid=$usrgrpid and users_status=".GROUP_STATUS_ENABLED;
		$this->assertEquals(1, CDBHelper::getCount($sql));

		$this->assertEquals($oldHashGroups, CDBHelper::getHash($sqlHashGroups));
	}

	/**
	* @dataProvider allGroups
	*/
	public function testPageUserGroups_MassEnableDEBUG($group) {
		$usrgrpid = $group['usrgrpid'];
		$name = $group['name'];

		$sqlHashGroups = "select * from usrgrp where usrgrpid<>$usrgrpid order by usrgrpid";
		$oldHashGroups = CDBHelper::getHash($sqlHashGroups);

		$this->zbxTestLogin('zabbix.php?action=usergroup.list');
		$this->zbxTestCheckTitle('Configuration of user groups');

		$this->zbxTestCheckboxSelect('usrgrpids_'.$usrgrpid);
		$this->zbxTestClickXpath('//button[text()="Enable debug mode"]');
		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of user groups');
		$this->zbxTestTextPresent('User group updated');

		$sql="select * from usrgrp where usrgrpid=$usrgrpid and debug_mode=".GROUP_DEBUG_MODE_ENABLED;
		$this->assertEquals(1, CDBHelper::getCount($sql));

		$this->assertEquals($oldHashGroups, CDBHelper::getHash($sqlHashGroups));
	}

	/**
	* @dataProvider allGroups
	*/
	public function testPageUserGroups_MassDisableDEBUG($group) {
		$usrgrpid = $group['usrgrpid'];
		$name = $group['name'];

		$sqlHashGroups = "select * from usrgrp where usrgrpid<>$usrgrpid order by usrgrpid";
		$oldHashGroups = CDBHelper::getHash($sqlHashGroups);

		$this->zbxTestLogin('zabbix.php?action=usergroup.list');
		$this->zbxTestCheckTitle('Configuration of user groups');

		$this->zbxTestCheckboxSelect('usrgrpids_'.$usrgrpid);
		$this->zbxTestClickXpath('//button[text()="Disable debug mode"]');
		$this->zbxTestAcceptAlert();

		$this->zbxTestCheckTitle('Configuration of user groups');
		$this->zbxTestTextPresent('User group updated');

		$sql = "select * from usrgrp where usrgrpid=$usrgrpid and debug_mode=".GROUP_DEBUG_MODE_DISABLED;
		$this->assertEquals(1, CDBHelper::getCount($sql));

		$this->assertEquals($oldHashGroups, CDBHelper::getHash($sqlHashGroups));
	}

	public function testPageUserGroups_FilterByName() {
		$this->zbxTestLogin('zabbix.php?action=usergroup.list');
		$this->zbxTestInputTypeOverwrite('filter_name', 'Zabbix administrators');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText("//tbody/tr[1]/td[2]/a", 'Zabbix administrators');
		$this->page->waitUntilReady();
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
	}

	public function testPageUserGroups_FilterNone() {
		$this->zbxTestLogin('zabbix.php?action=usergroup.list');
		$this->zbxTestInputTypeOverwrite('filter_name', '1928379128ksdhksdjfh');
		$this->zbxTestClickButtonText('Apply');
		$this->assertFalse($this->query('xpath://div[@class="table-stats"]')->one(false)->isValid());
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
		$this->zbxTestInputTypeOverwrite('filter_name', '%');
		$this->zbxTestClickButtonText('Apply');
		$this->page->waitUntilReady();
		$this->assertTableStats(0);
	}

	public function testPageUserGroups_FilterByStatus() {
		$this->zbxTestLogin('zabbix.php?action=usergroup.list');
		$this->zbxTestInputTypeOverwrite('filter_name', 'Zabbix administrators');
		$this->zbxTestClickXpathWait("//label[@for='filter_user_status_1']");
		$this->zbxTestClickButtonText('Apply');
		$this->page->waitUntilReady();
		$this->assertTableStats(1);
	}

	public function testPageUserGroups_FilterReset() {
		$this->zbxTestLogin('zabbix.php?action=usergroup.list');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestClickButtonText('Apply');
		$this->page->waitUntilReady();
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
	}
}
