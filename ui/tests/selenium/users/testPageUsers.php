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

class testPageUsers extends CLegacyWebTest {
	public $userAlias = 'Admin';
	public $userName = 'Zabbix';
	public $userSurname = 'Administrator';
	public $userRole = 'Super admin role';

	public static function allUsers() {
		return CDBHelper::getDataProvider('select * from users');
	}

	public function testPageUsers_CheckLayout() {
		$this->zbxTestLogin('zabbix.php?action=user.list');
		$this->zbxTestCheckTitle('Configuration of users');
		$this->zbxTestCheckHeader('Users');

		$this->zbxTestDropdownHasOptions('filter_usrgrpid', ['All', 'Disabled', 'Enabled debug mode', 'Guests', 'No access to the frontend', 'Zabbix administrators']);
		$this->zbxTestDropdownSelectWait('filter_usrgrpid', 'Zabbix administrators');
		$this->zbxTestTextNotPresent('guest');
		$this->zbxTestAssertElementText("//tbody/tr[1]/td[2]/a", $this->userAlias);
		$this->zbxTestAssertElementText("//tbody/tr[1]/td[3]", $this->userName);
		$this->zbxTestAssertElementText("//tbody/tr[1]/td[4]", $this->userSurname);
		$this->zbxTestAssertElementText("//tbody/tr[1]/td[5]", $this->userRole);

		$this->zbxTestAssertElementPresentXpath("//thead//th/a[text()='Username']");
		$this->zbxTestAssertElementPresentXpath("//thead//th/a[text()='Name']");
		$this->zbxTestAssertElementPresentXpath("//thead//th/a[text()='Last name']");
		$this->zbxTestAssertElementPresentXpath("//thead//th/a[text()='User role']");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Groups')]");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Is online?')]");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Login')]");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Frontend access')]");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Debug mode')]");
		$this->zbxTestAssertElementPresentXpath("//thead//th[contains(text(),'Status')]");
		$this->zbxTestAssertElementPresentXpath("//button[text()='Unblock'][@disabled]");
		$this->zbxTestAssertElementPresentXpath("//button[text()='Delete'][@disabled]");
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
		$this->zbxTestAssertElementPresentXpath("//div[@class='table-stats'][contains(text(),'Displaying')]");
		$this->zbxTestAssertElementText("//span[@id='selected_count']", '0 selected');
	}

	/**
	* @dataProvider allUsers
	*/
	public function testPageUsers_SimpleUpdate($user) {
		$userid = $user['userid'];
		$alias = $user['username'];

		DBexecute('UPDATE users SET autologout=0 WHERE userid=2');

		$sqlHashUser = 'select * from users where userid='.$userid;
		$oldHashUser = CDBHelper::getHash($sqlHashUser);
		$sqlHashGroup = 'select * from users_groups where userid='.$userid.' order by id';
		$oldHashGroup = CDBHelper::getHash($sqlHashGroup);
		$sqlHashMedia = 'select * from media where userid='.$userid.' order by mediaid';
		$oldHashMedia = CDBHelper::getHash($sqlHashMedia);

		$this->zbxTestLogin('zabbix.php?action=user.list');
		$this->zbxTestCheckTitle('Configuration of users');
		$this->zbxTestDropdownSelectWait('filter_usrgrpid', 'All');
		$this->zbxTestTextPresent($alias);

		$this->zbxTestClickLinkText($alias);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckHeader('Users');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'User updated');
		$this->zbxTestTextPresent($alias);

		$this->assertEquals($oldHashUser, CDBHelper::getHash($sqlHashUser));
		$this->assertEquals($oldHashGroup, CDBHelper::getHash($sqlHashGroup));
		$this->assertEquals($oldHashMedia, CDBHelper::getHash($sqlHashMedia));
	}

	public function testPageUsers_FilterByAlias() {
		$this->zbxTestLogin('zabbix.php?action=user.list');
		$this->zbxTestDropdownSelectWait('filter_usrgrpid', 'All');
		$this->zbxTestInputTypeOverwrite('filter_username', $this->userAlias);
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText("//tbody/tr[1]/td[2]/a", $this->userAlias);
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
	}

	public function testPageUsers_FilterNone() {
		$this->zbxTestLogin('zabbix.php?action=user.list');
		$this->zbxTestDropdownSelectWait('filter_usrgrpid', 'All');
		$this->zbxTestInputTypeOverwrite('filter_username', '1928379128ksdhksdjfh');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText("//div[@class='table-stats']", 'Displaying 0 of 0 found');
		$this->zbxTestInputTypeOverwrite('filter_username', '%');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText("//div[@class='table-stats']", 'Displaying 0 of 0 found');
	}

	public function testPageUsers_FilterByAllFields() {
		$this->zbxTestLogin('zabbix.php?action=user.list');
		$this->zbxTestDropdownSelectWait('filter_usrgrpid', 'Zabbix administrators');
		$this->zbxTestInputTypeOverwrite('filter_username', $this->userAlias);
		$this->zbxTestInputTypeOverwrite('filter_name', $this->userName);
		$this->zbxTestInputTypeOverwrite('filter_surname', $this->userSurname);
		$this->zbxTestClickButtonMultiselect('filter_roles_');
		$this->zbxTestLaunchOverlayDialog('User roles');
		$this->zbxTestClickLinkTextWait($this->userRole);
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText("//tbody/tr[1]/td[2]/a", $this->userAlias);
		$this->zbxTestAssertElementPresentXpath("//div[@class='table-stats'][text()='Displaying 1 of 1 found']");
	}

	public function testPageUsers_FilterReset() {
		$this->zbxTestLogin('zabbix.php?action=user.list');
		$this->zbxTestDropdownSelectWait('filter_usrgrpid', 'All');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
	}

	/**
	 * @backup users
	 */
	public function testPageUsers_MassDelete() {
		$result=DBselect("SELECT userid,username FROM users");

		$this->zbxTestLogin('zabbix.php?action=user.list');
		$this->zbxTestCheckTitle('Configuration of users');
		$this->zbxTestDropdownSelectWait('filter_usrgrpid', 'All');

		while ($user = DBfetch($result)) {
			$id = $user['userid'];
			$alias = $user['username'];

			$this->zbxTestClickButtonText('Reset');
			$this->zbxTestWaitForPageToLoad();

			$this->zbxTestCheckboxSelect('userids_' . $id);
			$this->zbxTestClickButton('user.delete');

			$this->zbxTestAcceptAlert();
			$this->zbxTestCheckTitle('Configuration of users');
			if (in_array($alias, ['guest', 'Admin', 'test-timezone', 'admin user for testFormScheduledReport', 'user-recipient of the report'])) {
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad' ,'Cannot delete user');
				$this->assertNotEquals(0, CDBHelper::getCount("select * from users where userid=$id"));
				$this->assertNotEquals(0, CDBHelper::getCount("select * from users_groups where userid=$id"));
				if ($alias === 'Admin') {
					$this->assertNotEquals(0, CDBHelper::getCount("select * from media where userid=$id"));
				}
				else {
					$this->assertEquals(0, CDBHelper::getCount("select * from media where userid=$id"));
				}
			}
			else {
				$this->zbxTestWaitUntilMessageTextPresent('msg-good' ,'User deleted');
				$this->assertEquals(0, CDBHelper::getCount("select * from users where userid=$id"));
				$this->assertEquals(0, CDBHelper::getCount("select * from users_groups where userid=$id"));
				$this->assertEquals(0, CDBHelper::getCount("select * from media where userid=$id"));
			}
		}
	}
}
