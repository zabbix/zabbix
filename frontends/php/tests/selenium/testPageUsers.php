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

class testPageUsers extends CWebTest {
	public $userAlias = 'Admin';
	public $userName = 'Zabbix';
	public $userSurname = 'Administrator';
	public $userRole = 'Zabbix Super Admin';

	public static function allUsers() {
		return DBdata('select * from users');
	}

	public function testPageUsers_CheckLayout() {
		$this->zbxTestLogin('users.php');
		$this->zbxTestCheckTitle('Configuration of users');
		$this->zbxTestCheckHeader('Users');

		$this->zbxTestDropdownHasOptions('filter_usrgrpid', ['All', 'Disabled', 'Enabled debug mode', 'Guests', 'No access to the frontend', 'Zabbix administrators']);
		$this->zbxTestDropdownSelectWait('filter_usrgrpid', 'Zabbix administrators');
		$this->zbxTestTextNotPresent('guest');
		$this->zbxTestAssertElementText("//tbody/tr[1]/td[2]/a", $this->userAlias);
		$this->zbxTestAssertElementText("//tbody/tr[1]/td[3]", $this->userName);
		$this->zbxTestAssertElementText("//tbody/tr[1]/td[4]", $this->userSurname);
		$this->zbxTestAssertElementText("//tbody/tr[1]/td[5]", $this->userRole);

		$this->zbxTestAssertElementPresentXpath("//thead//th/a[text()='Alias']");
		$this->zbxTestAssertElementPresentXpath("//thead//th/a[text()='Name']");
		$this->zbxTestAssertElementPresentXpath("//thead//th/a[text()='Surname']");
		$this->zbxTestAssertElementPresentXpath("//thead//th/a[text()='User type']");
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
		$alias = $user['alias'];

		DBexecute('UPDATE users SET autologout=0 WHERE userid=2');

		$sqlHashUser = 'select * from users where userid='.$userid;
		$oldHashUser = DBhash($sqlHashUser);
		$sqlHashGroup = 'select * from users_groups where userid='.$userid.' order by id';
		$oldHashGroup = DBhash($sqlHashGroup);
		$sqlHashMedia = 'select * from media where userid='.$userid.' order by mediaid';
		$oldHashMedia = DBhash($sqlHashMedia);

		$this->zbxTestLogin('users.php');
		$this->zbxTestCheckTitle('Configuration of users');
		$this->zbxTestDropdownSelectWait('filter_usrgrpid', 'All');
		$this->zbxTestTextPresent($alias);

		$this->zbxTestClickLinkText($alias);
		$this->zbxTestClickWait('update');
		$this->zbxTestCheckHeader('Users');
		$this->zbxTestWaitUntilMessageTextPresent('msg-good', 'User updated');
		$this->zbxTestTextPresent($alias);
		$this->zbxTestCheckFatalErrors();

		$this->assertEquals($oldHashUser, DBhash($sqlHashUser));
		$this->assertEquals($oldHashGroup, DBhash($sqlHashGroup));
		$this->assertEquals($oldHashMedia, DBhash($sqlHashMedia));
	}

	public function testPageUsers_FilterByAlias() {
		$this->zbxTestLogin('users.php');
		$this->zbxTestDropdownSelectWait('filter_usrgrpid', 'All');
		$this->zbxTestInputTypeOverwrite('filter_alias', $this->userAlias);
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText("//tbody/tr[1]/td[2]/a", $this->userAlias);
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
	}

	public function testPageUsers_FilterNone() {
		$this->zbxTestLogin('users.php');
		$this->zbxTestDropdownSelectWait('filter_usrgrpid', 'All');
		$this->zbxTestInputTypeOverwrite('filter_alias', '1928379128ksdhksdjfh');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText("//div[@class='table-stats']", 'Displaying 0 of 0 found');
		$this->zbxTestInputTypeOverwrite('filter_alias', '%');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText("//div[@class='table-stats']", 'Displaying 0 of 0 found');
	}

	public function testPageUsers_FilterByAllFields() {
		$this->zbxTestLogin('users.php');
		$this->zbxTestDropdownSelectWait('filter_usrgrpid', 'Zabbix administrators');
		$this->zbxTestInputTypeOverwrite('filter_alias', $this->userAlias);
		$this->zbxTestInputTypeOverwrite('filter_name', $this->userName);
		$this->zbxTestInputTypeOverwrite('filter_surname', $this->userSurname);
		$this->zbxTestClickXpath("//ul[@id='filter_type']//label[text()='$this->userRole']");
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestAssertElementText("//tbody/tr[1]/td[2]/a", $this->userAlias);
		$this->zbxTestAssertElementPresentXpath("//div[@class='table-stats'][text()='Displaying 1 of 1 found']");
		$this->zbxTestCheckFatalErrors();
	}

	public function testPageUsers_FilterReset() {
		$this->zbxTestLogin('users.php');
		$this->zbxTestDropdownSelectWait('filter_usrgrpid', 'All');
		$this->zbxTestClickButtonText('Reset');
		$this->zbxTestClickButtonText('Apply');
		$this->zbxTestTextNotPresent('Displaying 0 of 0 found');
	}

	/**
	 * @backup users
	 */
	public function testPageUsers_MassDelete() {
		$result=DBselect("SELECT userid,alias FROM users");

		$this->zbxTestLogin('users.php');
		$this->zbxTestCheckTitle('Configuration of users');
		$this->zbxTestDropdownSelectWait('filter_usrgrpid', 'All');

		while ($user = DBfetch($result)) {
			$id = $user['userid'];
			$alias = $user['alias'];

			$this->zbxTestClickButtonText('Reset');
			$this->zbxTestWaitForPageToLoad();

			$this->zbxTestCheckboxSelect('group_userid_' . $id);
			$this->zbxTestClickButton('user.massdelete');

			$this->zbxTestAcceptAlert();
			$this->zbxTestCheckTitle('Configuration of users');
			if ($alias === 'guest' || $alias === 'Admin') {
				$this->zbxTestWaitUntilMessageTextPresent('msg-bad' ,'Cannot delete user');
				$this->assertNotEquals(0, DBcount("select * from users where userid=$id"));
				$this->assertNotEquals(0, DBcount("select * from users_groups where userid=$id"));
				if ($alias === 'Admin') {
					$this->assertNotEquals(0, DBcount("select * from media where userid=$id"));
				}
				else {
					$this->assertEquals(0, DBcount("select * from media where userid=$id"));
				}
			}
			else {
				$this->zbxTestWaitUntilMessageTextPresent('msg-good' ,'User deleted');
				$this->assertEquals(0, DBcount("select * from users where userid=$id"));
				$this->assertEquals(0, DBcount("select * from users_groups where userid=$id"));
				$this->assertEquals(0, DBcount("select * from media where userid=$id"));
			}
		}
	}
}
